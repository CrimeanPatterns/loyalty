<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFidelitySelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const RES_XPATH = '//div[@id = "tc_HeaderLogout"] | //button[contains(text(), "Log out")] | //h1[contains(text(), "We\'ve updated our terms")] | //h2[span[contains(text(), "E-SIGN")]] | //h2[@id = "top-error-msg-single-notification--text"] | //div[@role="alert" and not(@aria-atomic)] | //p[contains(text(), "It looks like your account requires some extra attention.")] | //h1[contains(text(), "We\'ve made a few changes.")] | //h2[span[contains(text(), "Enroll in ID Shield")]] | //div[@id = "top-error-msg-children-notification"] | //p[contains(text(), \'To help protect your accounts, we’ll send a 6-digit code to your \')]';
    private const QUESTION_TWO_FA_CHOICE_XPATH = '//label[@id = "smsradio0"]';
    private const QUESTION_XPATH = '//div[@id = "kba-input"]/label';
    private const QUESTION_TWO_FA_BUTTON_XPATH = '//button[@aria-label="Continue"]';
    private const QUESTION_TWO_FA_XPATH = '//p[contains(text(), "We sent a six-digit code to")]';
    private const REMIND_ME_LATER_XPATH = '//button[@data-test-id="remindMeLater"] | //a[@id = "contactDetailsPrompt"] | //p[@class = "error-text__error"] | //button[@Id = "okClickForRedirection"]';

    private $fidelity;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useFirefox();
        $this->setKeepProfile(true);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://login.fidelityrewards.com/onlineCard/login.do");

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name="Username"]'), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath('//input[@name="Password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'login-button-continue']"), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$btn) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::RES_XPATH . ' | ' . self::QUESTION_XPATH . ' | ' . self::REMIND_ME_LATER_XPATH . " | " . self::QUESTION_TWO_FA_BUTTON_XPATH . " | " . self::QUESTION_TWO_FA_CHOICE_XPATH), 15);
        $question = $this->waitForElement(WebDriverBy::xpath(self::QUESTION_XPATH), 0);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//div[@id = "tc_HeaderLogout"] | //button[contains(text(), "Log out")]')) {
            return true;
        }

        if ($question) {
            $questionInput = $question->getText();
            $this->holdSession();
            $this->AskQuestion($questionInput, null, "Question");

            return false;
        } elseif ($anotherWay = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Try another way')]"), 0)) {
            $anotherWay->click();
            $question2faBtn = $this->waitForElement(WebDriverBy::xpath(self::QUESTION_TWO_FA_BUTTON_XPATH), 10);

            if ($question2faBtn) {
                $this->saveResponse();

                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $question2faBtn->click();
            }
        } elseif ($choice = $this->waitForElement(WebDriverBy::xpath(self::QUESTION_TWO_FA_CHOICE_XPATH), 0)) {
            $choice->click();

            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'otp-cont-button']"), 0);
            $btn->click();
        } elseif ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'To help protect your accounts, we’ll send a 6-digit code to your ')]"), 0)) {
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'otp-cont-button']"), 0);
            $btn->click();
        }

        $question2fa = $this->waitForElement(WebDriverBy::xpath(self::QUESTION_TWO_FA_XPATH), 10);
        $this->saveResponse();

        // We couldn’t confirm your email address.
        if (
            $this->waitForElement(WebDriverBy::xpath('//p[contains(., "We couldn’t confirm your email address.")]'), 0)
            && $anotherWay = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Try another way')]"), 0)
        ) {
            $this->logger->error("We couldn’t confirm your email address. Please try again or try a different email address.");
            $anotherWay->click();
            $question2faBtn = $this->waitForElement(WebDriverBy::xpath(self::QUESTION_TWO_FA_BUTTON_XPATH), 10);

            if ($question2faBtn) {
                $this->saveResponse();

                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $question2faBtn->click();
            }

            $question2faBtn = $this->waitForElement(WebDriverBy::xpath(self::QUESTION_TWO_FA_BUTTON_XPATH), 10);

            if ($question2faBtn) {
                $this->saveResponse();

                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $question2faBtn->click();
            }
        }

        if ($question2fa) {
            $question = $question2fa->getText();
        } else {
            $this->saveResponse();
            $question = $this->http->FindSingleNode(self::QUESTION_TWO_FA_XPATH);
        }

        if ($question) {
            $question = preg_replace('/If you don’t receive the security code, review your phone.+/', '', $question);
            $this->holdSession();
            $this->AskQuestion($question, null, "Question2fa");

            return false;
        }

        $this->remindMeLater();

        if ($this->http->FindSingleNode('//h1[contains(text(), "We\'ve updated our terms")] | //h1[contains(text(), "We\'ve made a few changes.")]')) {
            $this->throwAcceptTermsMessageException();
        }

        if ($this->http->FindSingleNode('//h2[span[contains(text(), "E-SIGN")]] | //p[contains(text(), "It looks like your account requires some extra attention.")] | //h2[span[contains(text(), "Enroll in ID Shield")]]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message =
            $this->http->FindSingleNode('//h2[@id = "top-error-msg-single-notification--text"]')
            ?? $this->http->FindSingleNode('//div[@role="alert" and not(@aria-atomic)]')
            ?? $this->http->FindSingleNode('//p[@class = "error-text__error"]')
            ?? $this->http->FindSingleNode('//div[@id = "top-error-msg-children-notification"]')
        ) {
            $this->logger->error("[Error]: {$message}");
            $message = preg_replace('/^Alert\s*/', '', $message);
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Your username and password are incorrect. Please try again.')
                || strstr($message, 'Try again. Passwords are between 8 and 24 characters.')
                || strstr($message, 'Enter your username again. Your username must be between 7 and 22 characters with no spaces.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'We had to lock your account after too many attempts.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // AccountID: 3786622
        if ($this->http->currentUrl() == 'https://login.fidelityrewards.com/onlineCard/fatalError.do') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($step == 'Question') {
            $questionInput = $this->waitForElement(WebDriverBy::xpath('//div[@id = "kba-input"]/input'), 0);
            $questionInput->clear();
            $questionInput->sendKeys($this->Answers[$this->Question]);
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'kba-continue']"), 0);
            $btn->click();
            $res = $this->waitForElement(WebDriverBy::xpath(self::RES_XPATH . " | " . self::REMIND_ME_LATER_XPATH), 15);
            $this->saveResponse();

            if ($res && strstr($res->getText(), 'That answer doesn’t match. Please try again.')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $res->getText(), "Question");

                return false;
            }
        }

        if ($step == 'Question2fa') {
            $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="idshield-input"]'), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'otp-cont-button']"), 0);
            $this->saveResponse();
            $answer = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            if (!$questionInput) {
                return false;
            }

            $questionInput->clear();
            $questionInput->sendKeys($answer);
            $this->saveResponse();
            $btn->click();

            $res = $this->waitForElement(WebDriverBy::xpath(self::RES_XPATH . " | " . self::REMIND_ME_LATER_XPATH), 15);
            $this->saveResponse();

            $this->remindMeLater();

            if (
                $res
                && (
                    strstr($res->getText(), 'The code you entered is expired. Please try again')
                    || strstr($res->getText(), 'That code doesn’t match.')
                )
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $res->getText(), "Question2fa");

                return false;
            }
        }

        return true;
    }

    public function Parse()
    {
        $fidelity = $this->getFidelity();
        $fidelity->accesstoken = $this->driver->executeScript("return sessionStorage.getItem('AccessToken');");
        $this->logger->debug("[Form AccessToken]: " . $fidelity->accesstoken);

        $fidelity->apiTokenValue = $this->driver->executeScript("return sessionStorage.getItem('ApigeeOAuthToken');");
        $this->logger->debug("[Form ApigeeOAuthToken]: " . $fidelity->apiTokenValue);

        $fidelity->Parse();
        $this->SetBalance($fidelity->Balance);
        $this->Properties = $fidelity->Properties;
        $this->ErrorCode = $fidelity->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $fidelity->ErrorMessage;
            $this->DebugInfo = $fidelity->DebugInfo;
        }
    }

    protected function getFidelity()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->fidelity)) {
            $this->fidelity = new TAccountCheckerFidelity();
            $this->fidelity->http = new HttpBrowser("none", new CurlDriver());
            $this->fidelity->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->fidelity->http);
            $this->fidelity->AccountFields = $this->AccountFields;
            $this->fidelity->itinerariesMaster = $this->itinerariesMaster;
            $this->fidelity->HistoryStartDate = $this->HistoryStartDate;
            $this->fidelity->historyStartDates = $this->historyStartDates;
            $this->fidelity->http->LogHeaders = $this->http->LogHeaders;
            $this->fidelity->ParseIts = $this->ParseIts;
            $this->fidelity->ParsePastIts = $this->ParsePastIts;
            $this->fidelity->WantHistory = $this->WantHistory;
            $this->fidelity->WantFiles = $this->WantFiles;
            $this->fidelity->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->fidelity->http->setDefaultHeader($header, $value);
            }

            $this->fidelity->globalLogger = $this->globalLogger;
            $this->fidelity->logger = $this->logger;
            $this->fidelity->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->fidelity->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->fidelity;
    }

    private function remindMeLater()
    {
        if ($remindMeLater = $this->waitForElement(WebDriverBy::xpath(self::REMIND_ME_LATER_XPATH), 0)) {
            $remindMeLater->click();

            $this->waitForElement(WebDriverBy::xpath(self::RES_XPATH), 15);
            $this->saveResponse();
        }
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
