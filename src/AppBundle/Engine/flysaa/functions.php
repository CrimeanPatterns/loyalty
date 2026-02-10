<?php

class TAccountCheckerFlysaa extends TAccountChecker
{
    /*
    public static function GetAccountChecker($accountInfo) {
        require_once __DIR__ . '/TAccountCheckerFlysaaSelenium.php';
        return new TAccountCheckerFlysaaSelenium();
    }
    */
    private const REWARDS_PAGE_URL = 'https://voyager.flysaa.com/my-voyager/mileage-summary';

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
        $this->http->GetURL('https://voyager.flysaa.com/login');

        if (!$this->http->ParseForm("_voyagerloginportlet_WAR_saaairwaysportlet_loginForm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('_voyagerloginportlet_WAR_saaairwaysportlet_voyagerId', $this->AccountFields['Login']);
        $this->http->SetInputValue('_voyagerloginportlet_WAR_saaairwaysportlet_pin', $this->AccountFields['Pass']);
        $this->http->SetInputValue('_voyagerloginportlet_WAR_saaairwaysportlet_showSecretHint', 'true');
        $this->http->FormURL = 'https://voyager.flysaa.com/login?p_p_id=voyagerloginportlet_WAR_saaairwaysportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=loginResourceId&p_p_cacheability=cacheLevelPage&p_p_col_id=column-1&p_p_col_count=1';

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($status == 'ok2FA' && $this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindPreg('/^Error:(.+)/')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'PIN number does not match'
                || $message == 'Member Does Not Exist'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Your Account blocked as maximum number of succesfull attempts exceeded.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                $message == 'Service is not available, try again later'
                || strstr($message, 'We are unable to process your request currently')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $question = strip_tags($response->userHash ?? null);

        if (!$question) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->Form = [];
        $this->http->SetInputValue('_voyagerloginportlet_WAR_saaairwaysportlet_formDate', date("UB"));
        $this->http->SetInputValue('_voyagerloginportlet_WAR_saaairwaysportlet_otpCode', $this->Answers[$this->Question]);
        $this->http->FormURL = 'https://voyager.flysaa.com/login?p_p_id=voyagerloginportlet_WAR_saaairwaysportlet&p_p_lifecycle=2&p_p_state=normal&p_p_mode=view&p_p_resource_id=validateOtpCodeResourceId&p_p_cacheability=cacheLevelPage&p_p_col_id=column-1&p_p_col_count=1';
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($message = $this->http->FindPreg('/^Error:(.+)/')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Login verification failed, please try again.') {
                $this->AskQuestion($this->Question, $message, "Question");

                $this->DebugInfo = "2fa | {$message}";

                return false;
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() !== self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }
        // Balance - Available miles
        $this->SetBalance($this->http->FindSingleNode("//dt[contains(text(), 'Available miles')]/following-sibling::dd[1]"));
        // Member Nº
        $this->SetProperty("Number", $this->http->FindSingleNode("//dt[contains(text(), 'Member Nº')]/following-sibling::dd[1]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h3[@class = "relmenu-title"]')));
        // Tier
        $this->SetProperty("Tier", $this->http->FindSingleNode('//div[@class = "page__title-thumbnail"]/span'));
        // Miles to Next Tier
        $this->SetProperty('MilesToTier', $this->http->FindSingleNode("//dt[contains(text(), 'Miles to Next Tier')]/following-sibling::dd[1]"));
        // Current Tier miles
        $this->SetProperty('CurrentTierMiles', $this->http->FindSingleNode("//dt[contains(text(), 'Current Tier miles')]/following-sibling::dd[1]"));
        // Current SAA miles
        $this->SetProperty('CurrentSAAMiles', $this->http->FindSingleNode("//dt[contains(text(), 'Current SAA miles')]/following-sibling::dd[1]"));
        // Tier expiry date
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode("//dt[contains(text(), 'Tier expiry date')]/following-sibling::dd[1]"));
        // Expiration Date
        $this->logger->info('Expiration Date', ['Header' => 3]);
        // Miles due to expire in
        $miles = $this->http->FindSingleNode("//dt[contains(text(), 'Miles due to expire in')]/following-sibling::dd[1]");
        // Expiring balance
        $this->SetProperty("ExpiringBalance", $miles);
        // Expiration Date
        if ($miles > 0) {
            $exp = $this->http->FindSingleNode("//dt[contains(text(), 'Miles due to expire in')]", null, true, "/expire in\s*([^\:]+)/");
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[@title="Logout"]')) {
            return true;
        }

        return false;
    }
}
