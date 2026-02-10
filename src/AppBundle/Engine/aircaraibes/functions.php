<?php

class TAccountCheckerAircaraibes extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.aircaraibes.com/space-preference/dashboard';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.aircaraibes.com/mon-compte#identification');

        if (!$this->http->ParseForm('space-preference-user-login-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("space_preference[name]", $this->AccountFields['Login']);
        $this->http->SetInputValue("space_preference[pass]", $this->AccountFields['Pass']);
        $this->http->SetInputValue("persistent_login", "1");
        $this->http->SetInputValue("op", "Se connecter");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            // AccountID: 3293373
            if (
                $this->http->Response['code'] == 500
                && empty($this->http->Response['body'])
                && $this->AccountFields['Login'] == '5001774120'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return null;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//ul[contains(@class, "messages__list")]/li[last()]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Nom d\'utilisateur ou mot de passe non reconnu.')
            ) {
                throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - miles convertibles
        $this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "c-loyalty-status__text")]/span'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h3[contains(@class, "c-pref-hello-title")]/text()[last()]')));
        // Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//p[contains(text(), "Carte Préférence")]/following-sibling::p'));
        // Status expiration
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//p[contains(text(), "Statut valable jusqu’au")]/following-sibling::p'));
        // Status
        $status = $this->http->FindSingleNode('//img[contains(@class, "loyalty-img-card")]/@data-src');
        $this->logger->debug("[Status]: {$status}");
        $status = basename($status);
        $this->logger->debug("[Status]: {$status}");

        switch ($status) {
            case 'card-student.jpg':
                $this->SetProperty("Status", "Student");

                break;

            case 'card-silver.jpg':
            case strstr($status, 'Carte-Pr%C3%A9f%C3%A9rence-Silver.png'):
            case strstr($status, 'Silver.png'):
                $this->SetProperty("Status", "SILVER");

                break;

            case 'card-silver-plus.jpg':
            case strstr($status, 'Carte-Pr%C3%A9f%C3%A9rence-Silver-Plus.png'):
                $this->SetProperty("Status", "SILVER PLUS");

                break;

            case 'card-gold.jpg':
            case strstr($status, 'Carte-Pr%C3%A9f%C3%A9rence-Gold.png'):
            case strstr($status, 'Gold.png'):
                $this->SetProperty("Status", "GOLD");

                break;

            case 'card-diamond.jpg':
                $this->SetProperty("Status", "DIAMANT");

                break;

            default:
                if (empty($this->Properties['Status']) && $this->ErrorCode !== ACCOUNT_ENGINE_ERROR) {
                    $this->sendNotification("refs #17720. ParseAirCaraibesDotCom: unknown Status -> {$status}");
                }
        }

        // Name
        $this->http->GetURL("https://www.aircaraibes.com/space-preference/profile");
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h3[contains(@class, "c-pref-form__title")]')));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href") || $this->http->FindPreg('#/logout#')) {
            return true;
        }

        return false;
    }
}
