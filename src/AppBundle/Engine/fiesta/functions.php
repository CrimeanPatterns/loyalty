<?php

class TAccountCheckerFiesta extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.fiestarewards.com/en/web/fiestarewards/mi-perfil";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    /*
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
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL('https://www.fiestarewards.com/en/login');
        $this->http->GetURL('https://sso.posadas.com/auth/realms/Posadas/protocol/openid-connect/auth?client_id=FiestaRewards&redirect_uri=https%3A%2F%2Fwww.fiestarewards.com%2Fweb%2Ffiestarewards&state=7fc0f404-2eb8-4393-8dd0-1e7e2bf03625&response_mode=fragment&response_type=code&scope=openid&nonce=4fef1cba-7867-4130-a977-317d7adca6b2&ui_locales=en');

        if (!$this->http->ParseForm("kc-form-login")) {
            $this->saveResponse();

            return $this->checkErrors();
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 7);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "kc-login"]'), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        /*
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "on");
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//img[@src = "/index_offline.jpg" and @alt="Smiley face"]/@src')) {
            throw new CheckException("Unfortunately, the Fiesta Rewards website seems to be unavailable. Please try to update your account later.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "The server is temporarily unable to service your")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //h2[
                contains(normalize-space(),"Welcome to Fiesta Rewards,")
                or contains(text(), "FR_miperfil_bienvenidoFR")
                or contains(text(), "Welcome to Apreciare, ")
            ]
            | //span[contains(@class, "FR-user-name") and @id]
            | //span[@id = "input-error"]
        '), 20);
        $this->saveResponse();
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (strstr($this->http->currentUrl(), 'https://www.fiestarewards.com/en/login#state=') && $code) {
            $data = [
                "code"         => $code,
                "grant_type"   => "authorization_code",
                "client_id"    => "FiestaRewards",
                "redirect_uri" => "https://www.fiestarewards.com/en/login",
            ];
            $headers = [
                "Accept"       => "*
        /*",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Origin"       => "https://www.fiestarewards.com",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://sso.posadas.com/auth/realms/Posadas/protocol/openid-connect/token", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->id_token)) {
                $this->logger->error("id_token not found");

                return false;
            }

            $this->http->setMaxRedirects(1);
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://sso.posadas.com/auth/realms/Posadas/protocol/openid-connect/auth?client_id=FiestaRewards&redirect_uri=https%3A%2F%2Fwww.fiestarewards.com%2Fweb%2Ffiestarewards%2Fsilentchecksso&state=45585ef7-6d81-4cce-a20b-c47096c7dbe6&response_mode=fragment&response_type=code&scope=openid&nonce=56c49a11-5272-41ac-8a58-0403af0b7a58&prompt=none");
            $this->http->RetryCount = 2;
            $this->http->setMaxRedirects(5);

            $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

            if (!$code) {
                $this->logger->error("code not found");

                return false;
            }

            $data = [
                "code"         => $code,
                "grant_type"   => "authorization_code",
                "client_id"    => "FiestaRewards",
                "redirect_uri" => "https://www.fiestarewards.com/web/fiestarewards/silentchecksso",
            ];
            $headers = [
                "Accept"       => "*
        /*",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Origin"       => "https://www.fiestarewards.com",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://sso.posadas.com/auth/realms/Posadas/protocol/openid-connect/token", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->access_token)) {
                $this->logger->error("access_token not found");

                return false;
            }

            $data = [
                "view" => "1",
                "id"   => $response->access_token,
            ];
            $headers = [
                "Accept"           => "*
        /*",
                "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                "Origin"           => "https://www.fiestarewards.com",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->http->PostURL("https://www.fiestarewards.com/en/web/fiestarewards/login", $data, $headers);

            // profile
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if ($this->loginSuccessful()) {
                return true;
            }

            return false;
        }// if (strstr($this->http->currentUrl(), 'https://www.fiestarewards.com/en/login#state=') && $code)
        */

        if ($this->http->FindSingleNode('//span[contains(@class, "FR-user-name") and @id]')) {
            // profile
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            $this->waitForElement(WebDriverBy::xpath('
                //h2[
                    contains(normalize-space(),"Welcome to Fiesta Rewards,")
                    or contains(text(), "FR_miperfil_bienvenidoFR")
                    or contains(text(), "Welcome to Apreciare, ")
                ]
            '), 20);
            $this->saveResponse();

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->http->FindSingleNode('//span[contains(@class, "FR-user-name") and @id]') == 'Hello, null') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->logger->error("auth failed");

        if ($message = $this->http->FindSingleNode('//span[@id = "input-error"]')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'It is required to update your password') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Oops! Something went wrong.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        */

        if ($this->http->FindSingleNode("
                //h4[contains(text(), 'Your account is not active')]
                | //p[contains(text(), 'FR_USER_STATUS_DESC')]
            ")
        ) {
            if ($this->attempt == 1 && $this->AccountFields['Login'] == 'donroughan@gmail.com') {
                throw new CheckException("Your account is not active", ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckRetryNeededException(2);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Available points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(normalize-space(), "Available points") or contains(normalize-space(),"FR_membresia_ptacumulados")]/following-sibling::p'));
        // Fiesta Rewards level
        $tier = $this->http->FindSingleNode('//p[contains(normalize-space(), "Fiesta Rewards level") or contains(normalize-space(), "FR_membresia_nivFR")]/following-sibling::p');

        switch ($tier) {
            case 'FR_membresia_soc_clasico':
                $tier = 'Classic';

                break;

            case 'FR_membresia_soc_Oro':
                $tier = 'Gold';

                break;

            case 'FR_membresia_soc_Platino':
                $tier = 'Platino';

                break;
        }
        $this->SetProperty("Tier", $tier);
        // Member since 2000
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//p[contains(normalize-space(), "Member since") or contains(normalize-space(), "FR_miperfil_subtitulo")]', null, true, "/(?:Member since|FR_miperfil_subtitulo)\s*(\d{4})$/"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h2[contains(normalize-space(), "Welcome to Fiesta Rewards, ") or contains(text(), "FR_miperfil_bienvenidoFR")]', null, true, "/(?:Welcome to Fiesta Rewards,|FR_miperfil_bienvenidoFR) (.+)$/")));
        // Fiesta Rewards member number
        $this->SetProperty("MemberID", $this->http->FindSingleNode('//p[contains(normalize-space(), "Fiesta Rewards member number") or contains(normalize-space(), "FR_membresia_numFR")]/following-sibling::p'));
        // 14,255 Points to get the next level
        $this->SetProperty("PointsToNextLevel", $this->http->FindSingleNode('//p[contains(normalize-space(), "to get the next level") or contains(normalize-space(), "FR_membresia_ptsigniv_siguiente")]/preceding-sibling::p', null, true, "/([\d,.]+) (?:Points|FR_membresia_ptsigniv)/"));
        // AVAILABLE CERTIFICATES
        $this->logger->info('Certificates', ['Header' => 3]);
        $this->http->GetURL("https://www.fiestarewards.com/en/mis-certificados");
        $vouchers = $this->http->XPath->query('//ul[@id="voucherCard"]/li');
        $this->logger->debug("Total {$vouchers->length} certificates were found");

        if ($vouchers->length == 0) {
            if (!$this->http->FindSingleNode('//div[@id="current-certificates"]/descendant::p[contains(normalize-space(), "You currently have")]/span[contains(normalize-space(),"0 certificates")]')) {
                $this->sendNotification('Something is wrong with the certificates');
            }

            return;
        }

        foreach ($vouchers as $voucher) {
            $displayName = $this->http->FindSingleNode('descendant::h4[contains(@class,"FR-heading")]/text()', $voucher);
            $promoCode = $this->http->FindSingleNode('descendant::p[contains(normalize-space(), "Promocode:")]/following-sibling::p', $voucher);
            $effectiveDate = $this->http->FindSingleNode('descendant::p[contains(normalize-space(), "Effective date:") or contains(normalize-space(), "FR_ICertificados_fecha_vigencia:")]', $voucher, true, "/:\s*(.+?)$/");

            if (
                empty($displayName)
                || empty($promoCode)
                || empty($effectiveDate)
            ) {
                $this->sendNotification("Something is wrong with the sub account");

                return;
            }
            $this->AddSubAccount([
                'Code'          => 'fiesta' . $promoCode,
                'DisplayName'   => $displayName,
                'PromoCode'     => $promoCode,
                'Balance'       => null,
                'EffectiveDate' => $effectiveDate,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h2[
                contains(normalize-space(),"Welcome to Fiesta Rewards,")
                or contains(text(), "FR_miperfil_bienvenidoFR")
                or contains(text(), "Welcome to Apreciare, ")
            ]')
        ) {
            return true;
        }

        return false;
    }
}
