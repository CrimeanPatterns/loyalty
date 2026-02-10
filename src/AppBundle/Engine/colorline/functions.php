<?php

class TAccountCheckerColorline extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.colorline.no/ibe/profile/myBookings.do", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.colorline.no/ibe/common/home.do');
        $this->http->GetURL('https://www.colorline.no/ibe/profile/login.do');

        return $this->loginForm();
    }

    public function loginForm()
    {
        if (!$this->http->ParseForm('loginForm')) {
            return false;
        }
        $this->http->SetInputValue('credentials/loginUsername', $this->AccountFields['Login']);
        $this->http->SetInputValue('credentials/loginPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember', "on");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.colorline.no/ibe/profile/login.do';
//        $arg['CookieURL'] = 'https://www.colorline.no/ibe/profile/login.do';
        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }
        // Success login
        if ($this->loginSuccessful()) {
            return true;
        }
        // Wrong login/pass
        $message = $this->http->FindSingleNode('//div[@class="errors"]/text()[1]');

        if (strpos('Feil kombinasjon av brukernavn og passord.', $message) !== false) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        } else {
            $this->logger->error("Unknown error: {$message}");
        }

        if ($this->http->FindSingleNode("//input[contains(@value, 'Log in') or contains(@value, 'Login')]/@value") && $this->loginForm()) {
            if (!$this->http->PostForm()) {
                return false;
            }
            // Success login
            if ($this->loginSuccessful()) {
                return true;
            }
        }

        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Medlemsnummer eller e-postadressen du har oppgitt er ikke gyldig, vennligst forsøk på nytt.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Vi finner ingen profil som stemmer med opplysningene du har oppgitt under.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Vi finner ingen profil som stemmer med opplysningene du har oppgitt under.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//span[@id = "profile-name"]'));
        // Membership Number
        $this->SetProperty('MembershipNumber', $this->http->FindSingleNode('//span[@class = "customer_number"]'));

        // Profile page
        $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $this->logger->debug("host: $host");
        $this->http->GetURL("https://{$host}/ibe/profile/myColorClub.do");

        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//div[contains(text(), "Medlem siden:") or contains(text(), "Member since")]/following-sibling::div[1]'));
        // Punkte gesamt - Balance
        $this->SetBalance($this->http->FindSingleNode('//span[@class = "bonuspoints"]', null, true, "/([\d\,\.\-]+)/"));
        // Expiring balance
        $expNodes = $this->http->FindNodes("//input[@class = 'pointsToExpireInfo']/@value");

        foreach ($expNodes as $expNode) {
            $this->logger->debug("pointsToExpireInfo -> $expNode");
            $pointsToExpireInfo = explode('|', $expNode);

            if (!isset($exp) || (isset($pointsToExpireInfo[1]) && strtotime($pointsToExpireInfo[1]) < $exp)) {
                if (isset($pointsToExpireInfo[0])) {
                    $this->SetProperty('ExpiringBalance', $pointsToExpireInfo[0]);
                }
                $pointsToExpireInfo[1] = str_replace("/", ".", $pointsToExpireInfo[1]);
                // Expiration date
                if (strtotime($pointsToExpireInfo[1])) {
                    $exp = strtotime($pointsToExpireInfo[1]);
                    $this->SetExpirationDate($exp);
                }// if (strtotime($pointsToExpireInfo[1]))
            }// if (!isset($exp) || (isset($pointsToExpireInfo[1]) && strtotime($pointsToExpireInfo[1]) < $exp))
        }// foreach ($expNodes as $expNode)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($this->Properties['Name'], $this->Properties['MembershipNumber'])) {
            $this->SetBalanceNA();
            // sending notifications
            if (($this->http->FindPreg("/ Punkt/ims") || $this->http->FindPreg("/ Point/ims"))) {
                $this->sendNotification("Color Line (Color Club). Account with Balance");
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'signout')]/@href")) {
            return true;
        }

        return false;
    }
}
