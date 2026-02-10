<?php

class BofARetryException extends Exception
{
}

class TAccountCheckerBofASeleniumRewards extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const ONE_TIME_CODE_QUESTION_TEXT = 'One time authorization code (was sent to your phone as text message)'; /* review */
    public const ONE_TIME_CODE_QUESTION_EMAIL = 'One time authorization code (was sent to your email)';
    public const SAFE_PASS_CODE_QUESTION = "Please enter SafePass Code which was sent to your mobile device.";

    public function InitBrowser()
    {
        //$this->AccountFields['BrowserState'] = null;
        $this->InitSeleniumBrowser();
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        if (!$this->isNewSession()) {
            $this->startNewSession();
        }
        $this->http->GetURL("https://www.managerewardsonline.bankofamerica.com/RWDapp/ns/home?mc=mwprwd");
        $this->driver->findElement(WebDriverBy::cssSelector('a.unauth-nav-signin-btn'))->click();

        return true;
    }

    public function Login()
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($attempt > 0) {
                $this->http->Log("attempt #{$attempt}");
                $this->startNewSession();
            }
            $loginField = $this->waitForElement(WebDriverBy::id('skw-enter-online-id'), 15);

            if (empty($loginField)) {
                return false;
            }
            $loginField->sendKeys($this->AccountFields['Login']);
            $this->driver->findElement(WebDriverBy::id('skw-enter-online-id-submit'))->click();
            $this->waitAjax();

            try {
                if (!$this->processErrorsAndQuestions()) {
                    return false;
                }
            } catch (BofARetryException $e) {
                $this->http->Log("retrying");

                continue;
            }

            break;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "question":
                $this->driver->findElement(WebDriverBy::id('tlpvt-skw-chal-ques'))->sendKeys($this->Answers[$this->Question]);
                $this->driver->findElement(WebDriverBy::id('skw-chal-ques-submit'))->click();
                $this->waitAjax();
                sleep(2);

                return $this->processErrorsAndQuestions();

                break;
        }

        return false;
    }

    public function Parse()
    {
        $points = $this->waitForElement(WebDriverBy::xpath("(//td[contains(text(), 'Total available points:')]/following-sibling::td[1])[1]"), 0, true);

        if (!empty($points)) {
            $this->SetBalance($points->getText());
            $this->keepSession(false);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'You do not have an eligible rewards credit card account to access this website')]"), 0, true)) {
            $this->SetBalanceNA();
            $this->keepSession(false);
        }
    }

    protected function processErrorsAndQuestions()
    {
        for ($timeout = 0; $timeout < 20; $timeout++) {
            // security question
            $question = $this->waitForElement(WebDriverBy::cssSelector("label[for = 'tlpvt-skw-chal-ques']"), 0, true);

            if (!empty($question)) {
                $question = $question->getText();
                $this->holdSession();
                sleep(2); // wait for error message
                $this->AskQuestion($question, $this->getErrorText(), 'question');

                return false;
            }
            // one time code by email
            $radio = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'otp-delivery-type-email']"), 0, true);

            if (!empty($radio)) {
                $this->holdSession();
                $radio->click();
                //$this->driver->findElement(WebDriverBy::id('otp-select-delivery-type-submit'))->click();
                unset($this->Answers[self::ONE_TIME_CODE_QUESTION_EMAIL]);
                $this->AskQuestion(self::ONE_TIME_CODE_QUESTION_EMAIL, null, 'otc');

                return false;
            }
            // one time code by text message
            $radio = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'otp-delivery-type-txt']"), 0, true);

            if (!empty($radio)) {
                $this->holdSession();
                $radio->click();
                //$this->driver->findElement(WebDriverBy::id('otp-select-delivery-type-submit'))->click();
                unset($this->Answers[self::ONE_TIME_CODE_QUESTION_TEXT]);
                $this->AskQuestion(self::ONE_TIME_CODE_QUESTION_TEXT, null, 'otc');

                return false;
            }
            // safepass
            $button = $this->waitForElement(WebDriverBy::cssSelector("a[title = 'Send SafePass Code refreshes this panel']"), 0, true);

            if (!empty($button)) {
                $this->holdSession();
                //$button->click();
                unset($this->Answers[self::SAFE_PASS_CODE_QUESTION]);
                $this->AskQuestion(self::SAFE_PASS_CODE_QUESTION, null, 'safepass');

                return false;
            }
            // SiteKey image
            $button = $this->waitForElement(WebDriverBy::id("skw-sitekey-yes-submit"), 0, true);

            if (!empty($button)) {
                $button->click();
                $this->waitAjax();
            }
            // password
            $input = $this->waitForElement(WebDriverBy::id("tlpvt-skw-enter-pass"), 0, true);

            if (!empty($input)) {
                $input->sendKeys($this->AccountFields['Pass']);
                $this->driver->findElement(WebDriverBy::id('skw-enter-pass-submit'))->click();
                $this->waitAjax();
            }
            // login error
            $error = $this->getErrorText();

            if (!empty($error)) {
                if ($error == 'SiteKey temporarily unavailable') {
                    throw new BofARetryException();

                    break;
                }

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            // success - account shown
            if ($this->waitForElement(WebDriverBy::cssSelector('div.account-box'), 0, true)) {
                return true;
            }
            // success - signoff shown
            if ($this->waitForElement(WebDriverBy::id('signOffLink'), 0, true)) {
                return true;
            }
            sleep(1);
        }

        return false;
    }

    protected function getErrorText()
    {
        $result = $this->waitForElement(WebDriverBy::cssSelector('div.skw-error-title'), 0, true);

        if (!empty($result)) {
            $result = $result->getText();
            $result = preg_replace('#Common\s+reasons\s+for\s+errors#ims', '', $result);
        }

        return $result;
    }

    protected function waitAjax()
    {
        sleep(1);
        $this->waitFor(function () { return $this->driver->executeScript('return jQuery.active') == 0; });
    }
}
