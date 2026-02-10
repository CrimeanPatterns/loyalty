<?php
class TAccountCheckerCabelas extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.cabelasclubvisa.com/pages/account/overview.jsf");

        if ($this->http->FindSingleNode("//input[contains(@src, 'SignOut')]/@src")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.cabelasclubvisa.com/?WTz_l=MyAccount");

        if (!$this->http->ParseForm("clubvisa-form")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("clubvisa-form:username", $this->AccountFields['Login']);
        $this->http->SetInputValue("clubvisa-form:password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("clubvisa-form:submit.x", "47");
        $this->http->SetInputValue("clubvisa-form:submit.y", "8");

        return true;
    }

    public function checkErrors()
    {
        // Maintenance
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Cabelasclubvisa.com is currently unavailable, due to routine system maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.cabelasclubvisa.com";

        return $arg;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//table[contains(@id, 'clubvisa-form:')]");
        $answerInput = $this->http->FindSingleNode("//table[contains(@id, 'clubvisa-form:')]//input/@name");

        if (!isset($question) || !isset($answerInput)) {
            return false;
        }
        $this->State["answerInput"] = $answerInput;

        if (!$this->http->ParseForm(null, 1, true, "//form[contains(@action, '/pages/security/security-qstns-verify.jsf')]")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetInputValue($this->State["answerInput"], $this->Answers[$this->Question]);
        $this->http->SetInputValue("clubvisa-form:submitButton.x", "47");
        $this->http->SetInputValue("clubvisa-form:submitButton.y", "8");
        $this->http->PostForm();
        $this->logger->notice("answer was entered");
        // An invalid answer was entered. Please try again.
        if ($error = $this->http->FindSingleNode("//td[contains(text(), 'An invalid answer was entered. Please try again.')]")) {
            $this->AskQuestion($this->Question, $error);

            return false;
        }

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//input[contains(@src, 'SignOut')]/@src")) {
            return true;
        }
        // The Username or Password that you entered is invalid.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The Username or Password that you entered is invalid.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->parseQuestion()) {
            return false;
        }
        // Access to this service has been restricted.
        if ($message = $this->http->FindPreg("/(Access to this service has been restricted\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Cabela's CLUB Visa - Online Member Services is temporarily unavailable.
        if ($message = $this->http->FindPreg("/(Cabela\'s CLUB Visa - Online Member Services is temporarily unavailable\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Cabela's CLUB Visa - Identity Verification
        if ($this->http->currentUrl() == 'https://www.cabelasclubvisa.com/pages/public/forgot-password.jsf') {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - You have ... CLUB Points available.
        if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(@class, 'availableClubPoints')]"))) {
            // You have No Points available Now.
            $this->SetWarning($this->http->FindSingleNode("//div[contains(., 'No Points available Now.')]"));
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[contains(text(), 'Account:')]/b")));
        // Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//td[contains(text(), 'Account:')]", null, true, "/Ending\s*In:\s*(\d+)/"));
        // Your Current Level
        $this->SetProperty("Level", $this->http->FindSingleNode("//strong[contains(text(), 'Your Current Level:')]", null, true, "/:\s*([^<]+)/"));
    }
}
