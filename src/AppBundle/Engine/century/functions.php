<?php

class TAccountCheckerCentury extends TAccountChecker
{
//    public static function GetAccountChecker($accountInfo) {
//        require_once __DIR__."/TAccountCheckerCenturySelenium.php";
//        return new TAccountCheckerCenturySelenium();
//    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.c21stores.com/account", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.c21stores.com/login');

        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("loginEmail", $this->AccountFields["Login"]);
        $this->http->SetInputValue("loginPassword", $this->AccountFields["Pass"]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException("Service is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        // Century21 Stores will be back online SOON!
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Century21 Stores will be back online SOON!')]")) {
            throw new CheckException('Our website is currently undergoing maintenance. We will be back online SOON!', ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        //# An internal server error occurred. Please try again later.
        if ($message = $this->http->FindPreg("/(An internal server error occurred\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Sorry, there was a problem displaying the requested page\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/We're sorry, but something went wrong/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Oops...Something went wrong")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "This page either doesn\'t exist, or it moved somewhere else.")]')) {
            $this->http->GetURL("https://www.c21stores.com");

            if ($this->http->FindSingleNode('//img[@alt = "Please check back soon"]/@alt')) {
                throw new CheckException('Please check back soon for website updates.', ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 422) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        // login successful
        $response = $this->http->JsonLog();
        $redirectUrl = $response->redirectUrl ?? null;

        if ($redirectUrl) {
            $this->http->NormalizeURL($redirectUrl);
            $this->http->GetURL($redirectUrl);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // <p class=\"jm-error-message\">Invalid password. <b>Please note:</b> we’ve upgraded our site. If you haven’t logged in to your C21Stores.com account since <b>2/28</b>, you are required to reset your password for security reasons.</p>
        if ($message = $this->http->FindPreg('#>(Invalid password. <b>Please note:</b>.+?)</p>#')) {
            throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
        }

        $message = $response->error[0] ?? null;

        if (
            $message == "Invalid password. Remember that password is case-sensitive. Please try again."
            || $message == "This account does not exist, please create an account."
        ) {
            throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//*[contains(text(), 'Name:')]/following-sibling::dd[1]")));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//*[contains(text(), 'Member since:')]/following-sibling::dd[1]"));
        // Card number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//*[contains(text(), 'Card Number:')]/following-sibling::dd[1]"));
        // Member level
        $this->SetProperty("MemberLevel", $this->http->FindSingleNode("//*[contains(text(), 'Member Level:')]/following-sibling::dd[1]"));
        // Next reward goal
        $this->SetProperty("NextRewardGoal", $this->http->FindSingleNode("//*[contains(text(), 'Next Reward Goal:')]/following-sibling::dd[1]"));
        // Balance - Current balance
        if (!$this->SetBalance($this->http->FindSingleNode("//*[contains(text(), 'Current Point Balance:')]/following-sibling::dd[1]"))) {
            // Sign Up For Loyalty
            if ($this->http->FindSingleNode("//a[contains(text(), 'Join C21STATUS')]")
                && !empty($this->Properties['Name'])) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_WARNING);
            }
            //# Loyalty is currently unavailable
            elseif ($this->http->FindPreg("/(Currently Unavailable)/ims")) {
                throw new CheckException("Loyalty program info is currently not available. Please check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/
            elseif ($this->http->FindSingleNode("//*[contains(text(), 'Current Point Balance:')]/following-sibling::dd[1]") === "") {
                $this->SetBalanceNA();
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//*[contains(text(), 'Name:')]/following-sibling::dd[1]")) {
            return true;
        }

        return false;
    }
}
