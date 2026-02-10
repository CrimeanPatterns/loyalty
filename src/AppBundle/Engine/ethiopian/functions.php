<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ethiopian\QuestionAnalyzer;

class TAccountCheckerEthiopian extends TAccountChecker
{
    use OtcHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://shebamiles.ethiopianairlines.com/account/my-account/index', [], 10);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://shebamiles.ethiopianairlines.com/account/login/index?redirectUrl=/account/my-account/index");

        if (!$this->http->ParseForm("login-user")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("MemberID", $this->AccountFields["Login"]);
        $this->http->SetInputValue("Password", $this->AccountFields["Pass"]);
        $this->http->SetInputValue("RememberMe", "true");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
//        $arg['SuccessURL'] = 'https://www.ethiopianairlines.com/shebamiles/my-profile/welcome';
        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/An error occurred while processing your request.<p>/") && $this->http->Response['code'] == 503) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Your username or password is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Sorry, we could not send a verification code to your email address')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->currentUrl() == 'https://shebamiles.ethiopianairlines.com/account/login/index?redirectUrl=/account/my-account/index') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->parseQuestion()) {
            return false;
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//p[contains(text(), "One last thing - We’ve sent a verification email to")]');

        if (!$question || !$this->http->ParseForm("login-verification")) {
            return false;
        }

        $this->Question = str_replace(' Please check your email and submit the verification code to access your ShebaMiles account.', '', $question);

        if (!QuestionAnalyzer::isOtcQuestion($this->Question)) {
            $this->sendNotification("need to fix QuestionAnalyzer");
        }

        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->http->SetInputValue("otp", $answer);
        $this->http->SetInputValue("submit", "Submit");

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger alert-dismissible fade show")]/strong')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Login verification failed, please submit the correct verification code again.")
                || strstr($message, "Your verification code is exipired.")
            ) {
                // refs #23505 resend code
                sleep(70); // Please wait 2 minutes to request a new code!
                $this->increaseTimeLimit();
                sleep(50); // Please wait 2 minutes to request a new code!

                if (!$this->http->ParseForm("resend-email")) {
                    return false;
                }

                if (!$this->http->PostForm()) {
                    return false;
                }

                if (!$this->http->ParseForm("login-verification")) {
                    return false;
                }

                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        return true;
    }

    public function Parse()
    {
        // Balance - Award Miles
        $this->SetBalance($this->http->FindSingleNode('//small[contains(text(), "Award Miles")]/following-sibling::p[1]'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//p[contains(@style, "text-transform: capitalize;")]')));
        // Card Number
        $this->SetProperty("MemberID", $this->http->FindSingleNode('//p[contains(@style, "text-transform: capitalize;")]/following-sibling::small'));
        // Tier level
        $this->SetProperty("TierLevel", $this->http->FindSingleNode('//p[contains(@class, "status-color")]'));
        // Total Status Miles
        $this->SetProperty("StatusMiles", $this->http->FindSingleNode('//small[span[contains(text(), "Status Miles")]]/text()[1]'));
        // Tier Qualifing Segments
        $this->SetProperty("CurrentFlightCount", $this->http->FindSingleNode('//small[span[contains(text(), "Qualifying Segments")]]/text()[1]'));
        // Expiration date
        $this->SetProperty('ExpiringBalance', $this->http->FindSingleNode('//small[contains(text(), "Miles expiring on")]/preceding-sibling::small[contains(text(), "You have")]', null, true, "/You have\s*(.+) Sheba/"));

        $exp = $this->http->FindSingleNode('//small[contains(text(), "Miles expiring on")]', null, true, "/expiring on\s*(.+)/");

        if ($exp && strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Logout")]')) {
            return true;
        }

        return false;
    }
}
