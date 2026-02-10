<?php
class TAccountCheckerCenturySelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useChromium();
        $this->disableImages();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->useCache();
        }
        $this->http->setRandomUserAgent();
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please Enter A Valid Email Address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.c21stores.com/login");
        $login = $this->waitForElement(WebDriverBy::id('login-form-email'), 10);
        $pass = $this->waitForElement(WebDriverBy::id('login-form-password'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//form[@name = 'login-form']//button[@type='submit']"), 0);

        if (!$login || !$pass || !$btn) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return $this->checkErrors();
        }// if (!$login || !$pass || !$btn)

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
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

        return false;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 10;

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Name:')]/following-sibling::span[1]"), 0);
            $this->saveResponse();
            // login successful
            if ($logout) {
                return true;
            }
            // Invalid password. Please note: we’ve upgraded our site. If you haven’t logged in to your C21Stores.com account since 2/28, you are required to reset your password for security reasons.
            if ($message = $this->http->FindSingleNode('//p[@class="jm-error-message" and contains(., " we’ve upgraded our site. If you haven’t logged in to your C21Stores.com account since ")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry, we couldn't find an account matching that email and password.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, we couldn\'t find an account matching that email and password.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // This account has been locked for 30 minutes.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "This account has been locked for 30 minutes.")]')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // The change you wanted was rejected.
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "The change you wanted was rejected.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            sleep(1);
            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < $sleep)

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//*[contains(text(), 'Name:')]/following-sibling::span[1]")));
        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//*[contains(text(), 'Member Since:')]/following-sibling::span[1]"));
        // Card number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//*[contains(text(), 'Card Number:')]/following-sibling::span[1]"));
        // Member level
        $this->SetProperty("MemberLevel", $this->http->FindSingleNode("//*[contains(text(), 'Member Level:')]/following-sibling::span[1]"));
        // Next reward goal
        $this->SetProperty("NextRewardGoal", $this->http->FindSingleNode("//*[contains(text(), 'Next Reward Goal:')]/following-sibling::span[1]"));
        // Balance - Current balance
        if (!$this->SetBalance($this->http->FindSingleNode("//*[contains(text(), 'Current Point Balance:')]/following-sibling::span[1]"))) {
            // Sign Up For Loyalty
            if ($this->http->FindSingleNode("//a[contains(text(), 'Join C21STATUS')]")
                && !empty($this->Properties['Name'])) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_WARNING);
            }
            //# Loyalty is currently unavailable
            elseif ($this->http->FindPreg("/(Currently Unavailable)/ims")) {
                throw new CheckException("Loyalty program info is currently not available. Please check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/
            elseif ($this->http->FindSingleNode("//*[contains(text(), 'Current Point Balance:')]/following-sibling::span[1]") === "") {
                $this->SetBalanceNA();
            }
        }

        $this->http->GetURL("https://www.c21stores.com/users/loyalty");
        $this->waitForElement(WebDriverBy::xpath("//td[contains(normalize-space(text()), 'Expiration Date')]"), 5);
        $this->saveResponse();
        $expText = $this->http->FindSingleNode("//td[contains(normalize-space(text()), 'Expiration Date')]");

        if ($expText && $expText != 'Expiration Date') {
            $this->sendNotification("century - Need to check this account");
        }
    }
}
