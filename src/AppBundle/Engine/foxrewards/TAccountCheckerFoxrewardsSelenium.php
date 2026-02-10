<?php

class TAccountCheckerFoxrewardsSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome();
        $this->disableImages();
        $this->useCache();
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL('https://www.foxrentacar.com/en/rewards-program/my-rewards.html');
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Signout')]"), 5, true);
        $this->saveResponse();

        if ($logout || $this->http->FindSingleNode("//span[@id = 'loggedin_user_email' and normalize-space(text()) != '']")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL('https://www.foxrentacar.com/en/rewards-program/my-rewards.html');
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
            sleep(5);
            $this->saveResponse();
        }

        $this->waitForElement(WebDriverBy::className("optanon-alert-box-close"), 5);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'login']/a"), 5);

        if (!$loginButton) {
            return $this->checkErrors();
        }

        $this->driver->executeScript('
            var d = document.getElementsByClassName("optanon-alert-box-close");
            if(d.length) d[0].click();'
        );
        //if ($close = $this->waitForElement(WebDriverBy::className("optanon-alert-box-close"), 0))
        //    $close->click();

        $loginButton->click();

        $loginField = $this->waitForElement(WebDriverBy::id('login-email'), 10);

        if (empty($loginField)) {
            return $this->checkErrors();
        }
        $loginField->sendKeys($this->AccountFields["Login"]);
        $passwordInput = $this->waitForElement(WebDriverBy::id('login-password'), 0);

        if (!$passwordInput) {
            return false;
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $rememberme = $this->waitForElement(WebDriverBy::id("rememberme"), 0);

        if ($rememberme) {
            $rememberme->click();
        }

        $login = $this->waitForElement(WebDriverBy::id("login-submit"), 0);

        if (!$login) {
            return false;
        }
        $login->click();

        return true;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        sleep(2); //debug

        while ($time < 30) {
            $this->logger->debug("(time() - \$startTime) = {$time} < 30");
            // look for logout link
            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Signout')]"), 0, true);
            $this->saveResponse();

            if (($logout && !empty($logout->getText())) || $this->http->FindSingleNode("//span[@id = 'loggedin_user_email' and normalize-space(text()) != '']")) {
                return true;
            }
            /*
             * There is a problem with your login, please verify your email and password to login.
             * If you forgot your password, please click 'Forgot Password' above.
             */
            if ($errors = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'There is a problem with your login. Please verify your email and password')]"), 0)) {
                throw new CheckException("There is a problem with your login, please verify your email and password to login.", ACCOUNT_INVALID_PASSWORD);
            }

            sleep(1);
            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < 30)

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->saveResponse();
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // Server error
            || $this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found')]")
            // We're sorry, but there was an error processing your request
            || $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, but there was an error processing your request")]')
            || $this->http->Response['code'] == 503) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.foxrentacar.com/en/rewards-program/my-rewards.html");
        $this->saveResponse();
        // Balance - REWARD POINTS
        $balance = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'pointBalance']"), 10);

        if ($balance) {
            $this->SetBalance($balance->getText());
        }
        // REWARD NUMBER
        $number = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'tsdNumber']"), 10);

        if ($number) {
            $this->SetProperty("Number", $number->getText());
        }

        // Name
        $this->http->GetURL("https://www.foxrentacar.com/en/rewards-program/my-profile.html");
        $name = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'name']"), 10);
        $this->saveResponse();

        if ($name) {
            $this->SetProperty("Name", beautifulName($name->getText()));
        }
    }
}
