<?php
/**
 * @author moskalenko
 */
class TAccountCheckerBahncorporate extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://fahrkarten.bahn.de/grosskunde/start/kmu_start.post?scope=login&lang=de');

        if (!$this->http->ParseForm("formular")) {
            return false;
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->Form['button.weiter_p_js'] = 'true';

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $error = $this->http->FindSingleNode("//div[@class='welcomefiku']");

        if (isset($error)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//*[@class='errormsg']")) {
            throw new CheckException(utf8_encode($message), ACCOUNT_INVALID_PASSWORD);
        }

        /*
        if ($message = $this->http->FindSingleNode('//h1[contains(text(),"gesperrt")]'))
            throw new CheckException(utf8_encode($message), );
        */

        if ($this->http->FindPreg("/Der Benutzername oder das Passwort ist nicht/ims")) {
            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//div[@id='content']//h1[contains(text(),'Account vorubergehend gesperrt')]")) {
            throw new CheckException("The page has been temporarily blocked", ACCOUNT_LOCKOUT);
        }

        return false;
    }

    public function Parse()
    {
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[@class="welcomefiku"]/div[@class="welcomefikuAnrede"]', null, false, "/([^<!]+)/ims"));
        $this->SetProperty('CompanyName', $this->http->FindSingleNode('//div[@class="welcomefikuFirma"]/*[contains(text(), "Firma")]', null, false, '/Firma\s*:\s*(.*)/i'));
        $this->SetProperty('Number', $this->http->FindSingleNode('//div[@class="welcomefikuFirma"]/*[contains(text(), "Kundennummer")]', null, false, '/(\d+)/i'));

        // Prämien-/Statuspunkte abfragen
        $bonus = $this->http->FindSingleNode('//a[contains(@href,"bahncard_punkte_start.post")]/@href', null, false);

        if (!isset($bonus)) {
            throw new CheckException("No Bahn Card is linked to yours account.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->GetURL($bonus)) {
            $this->SetProperty('Name', $this->http->FindSingleNode('//div[contains(@class, "tableCell") and descendant::*[contains(text(), "Name")]]/following::div[1]'));
            // Zu Ihren personlichen Daten konnte kein Punktestand ermittelt werden.
            if ($this->http->FindPreg("/Sie Ihre BahnCard-Nummer und Geburtsdatum in Ihren Anmeldedaten zum Online Punktesammeln/ims")) {
                throw new CheckException("Please update your account information", ACCOUNT_PROVIDER_ERROR);
            }
            //# Balance
            if (!$this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "tableCell") and descendant::*[contains(text(), "mienpunktestand")]]/following::div[1]'))) {
                // Service temporarily unavailable
                if ($this->http->FindPreg("/(die BahnCard-Services sind derzeit leider)/ims")) {
                    throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, die BahnCard-Services sind derzeit leider nicht verfgbar. Bitte versuchen Sie es spter noch einmal. Wir bitten um Ihr Verstndnis. Ihr bahn.corporate-Team", ACCOUNT_PROVIDER_ERROR);
                }
                // An unexpected system error
                if ($this->http->FindPreg("/(aufgrund eines unerwarteten Systemfehlers wurden Sie automatisch vom Buchungssystem der Bahn abgemeldet)/ims")) {
                    throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, aufgrund eines unerwarteten Systemfehlers wurden Sie automatisch vom Buchungssystem der Bahn abgemeldet. Bitte loggen Sie sich wieder ein. Wir bitten um Entschuldigung. Mit freundlichen Grüßen. Ihr bahn.corporate-Team", ACCOUNT_PROVIDER_ERROR);
                }
                /*
                 * Für die Anzeige Ihrer Prämien-/Statuspunkteübersicht werden Ihre Kartennummer
                 * und Ihr Geburtsdatum benötigt. Sind diese Daten unter \"Punkte sammeln\" korrekt eingegeben?
                 */
                if ($this->http->FindPreg('/tigt. Sind diese Daten unter "Punkte sammeln" korrekt eingegeben/ims')
                    && $this->http->FindPreg('/bersicht werden Ihre Kartennummer und Ihr Geburtsdatum ben/ims')) {
                    throw new CheckException("Deutsche Bahn (BahnCard Corporate) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
                } /*review*/
            }
            $this->SetProperty('StatusPonts', $this->http->FindSingleNode('//div[contains(@class, "tableCell") and descendant::*[contains(text(), "Statuspunktestand")]]/following::div[1]'));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["NoCookieURL"] = true;

        return $arg;
    }
}
