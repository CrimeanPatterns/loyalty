<?php

class TAccountCheckerAllaccess extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.unitybyhardrock.com/dashboard';

    public static function FormatBalance($fields, $properties)
    {
        if (
            isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "allaccessRewards"))
        ) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

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
//        curl_setopt($this->http->driver->curl, CURLOPT_SSL_VERIFYHOST, false);
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('usernameUserInput', $this->AccountFields["Login"]);
        $this->http->SetInputValue('username', "PATRON/{$this->AccountFields["Login"]}@carbon.super");

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('password', $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "We\'re currently down for maintenance")]
                | //p[contains(text(), "We are currently performing maintenance to enhance your digital experience.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//label[contains(text(), "A new 6-digit code has been sent to your email address at")]');

        if (!$this->http->ParseForm('codeForm') || !$question) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue("OTPCode", $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }

//        if ($message = $this->http->FindSingleNode("//")) {
//            $this->logger->error("[Error]: {$message}");
//
//            if (strstr($message, 'Code is not valid')) {
//                $this->AskQuestion($this->Question, $message, "Question");
//
//                return false;
//            }
//
//            $this->DebugInfo = $message;
//
//            return false;
//        }

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm("pendingPasswordResetForm")) {
            $this->http->PostForm();
        }

        if ($this->parseQuestion()) {
            return false;
        }

        // login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'shr-card__error-message')]")) {
            $this->logger->error("[Error]: '{$message}'");

            if (
                strstr($message, 'You entered invalid login credentials.')
                // You've failed to log in 2 times. You have 1 more attempt before your account is locked.
                || strstr($message, "You've failed to log in")
                || $message == 'Your login credentials are incorrect or temporarily locked. Try again or call us to unlock your account.'
                || $message == 'Your login credentials are incorrect or temporarily locked. Please try again in a moment or call Customer Care.'
                || strstr($message, 'Access to this account is not available. Your online access has been deleted. ')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'You failed to log in 3 times. Your Unity online account is locked.')
                || strstr($message, 'Your online account has been temporarily locked')
                || strstr($message, 'Your online account is temporarily locked.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Your Unity account is not active") or contains(text(), "We seem to be having technical issues") or contains(text(), "Complete your online signup")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->currentUrl() == 'https://unity.login.hardrock.com/login/activate-your-account') {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Unity Points
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "unity-points-content-balance")]'));
        // Account number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode('//div[contains(@class, "account_number")]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "dashboard-hero-greeting-name")]')));
        // Your Tier Status
        $this->SetProperty("Tier", $this->http->FindSingleNode('//div[contains(@class, "status-summary-card-subtitle")]'));
        // Tier Credits
        $this->SetProperty("TierCredits", $this->http->FindSingleNode('//span[@class = "current-points"]'));
        // Points To Next Tier
        $this->SetProperty("PointsToNextTier", $this->http->FindSingleNode("//div[contains(@class, 'status-summary-content')]/p[contains(text(), 'Earn')]/strong[1]"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'log-out')]")) {
            return true;
        }

        return false;
    }
}
