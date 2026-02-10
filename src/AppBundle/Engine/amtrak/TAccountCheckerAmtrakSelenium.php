<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmtrakSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    private HttpBrowser $browser;
    protected $amtrak;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
        $this->KeepState = true;
        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(5);
            $agent = $this->http->getDefaultHeader("User-Agent");
            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }

        $this->seleniumOptions->recordRequests = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.amtrak.com/home");

        $accept = $this->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 5);

        if ($accept) {
            $accept->click();
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//button[@amt-auto-test-id='header-sign-in'] | //a[@id = 'guest-reward-desktop']"), 2);

        if ($login) {
            $this->saveResponse();
            $login->click();
            sleep(3);
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 10);

        // provider bug fix
        if (!$login) {
//            $this->saveResponse();
//            $this->driver->executeScript('try { document.querySelector(\'[data-href="https://www.amtrak.com/"]\').click(); } catch (e) {}');
//            $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "English")]'), 5);
//            $this->saveResponse();

            $login = $this->waitForElement(WebDriverBy::xpath("//button[@amt-auto-test-id='header-sign-in'] | //a[@id = 'header-sign-in']"), 5);
            $this->saveResponse();

            if ($login) {
                $login->click();
                sleep(3);
            }

            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "signInName"]'), 10);
        }

        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 10);
        $loginButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "next"]'), 10);
        $this->saveResponse();

        if (!$login || !$password || !$loginButton) {
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $loginButton->click();

        return true;
    }

    public function Login()
    {
        sleep(2);
        $this->waitForElement(WebDriverBy::xpath("
           //*[@id='signin_tnc-btn-b2c']
           | //*[@id='lblUserFirstName']
           | //div[contains(@class, 'error') and @style = 'display: block;']
           | //*[@id='forgotpassword-simple--subheading']/*[self::div or self::h3][contains(text(),'Please select how you would like to receive your code')]
           | //*[@id='forgotpassword-simple--subheading']/*[self::div or self::h3][contains(text(),'A verification code has been sent to your email.')]
        "), 10);
        $this->saveResponse();

        if (!$this->parseQuestion()) {
            return false;
        }

        $agreeTerms = $this->waitForElement(WebDriverBy::id('signin_tnc-btn-b2c'), 0);

        if ($agreeTerms) {
            $agreeTerms->click();
            $this->waitForElement(WebDriverBy::id('lblUserFirstName'), 5);
        }

        $lblUserFirstName = $this->waitForElement(WebDriverBy::id('lblUserFirstName'), 0);
        $modalClose = $this->waitForElement(WebDriverBy::id('email-opt-modal-close'), 0);

        if ($lblUserFirstName || $modalClose) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = 'display: block;']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'The username or password provided in the request are invalid')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $choice = $this->waitForElement(WebDriverBy::xpath("//*[@id='forgotpassword-simple--subheading']/*[self::div or self::h3][contains(text(),'Please select how you would like to receive your code')]"), 1);
        $delay = 0;

        if ($choice) {
            sleep(1);
            $this->saveResponse();
            $emailOption = $this->waitForElement(WebDriverBy::id('email_option'), 0);
            $continue = $this->waitForElement(WebDriverBy::id('continue'), 0);
            if ($emailOption && $continue) {
                $this->saveResponse();
                $emailOption->click();
                sleep(1);
                $continue->click();
                $delay = 10;
            }
        }

        $q = $this->waitForElement(WebDriverBy::xpath("//*[@id='forgotpassword-simple--subheading' and not(@style=\"display: none;\")]/*[self::div or self::h3][contains(text(),'A verification code has been sent to your email.')]"), $delay);

        if ($q) {
            $question = $q->getText();
        } else {
            $q = $this->waitForElement(WebDriverBy::xpath("(//div[contains(@id,'phoneVerificationControl_success_message')])[1]"), 0);
            if (!$q) {
                $this->saveResponse();

                return true;
            }
            $phone = $this->waitForElement(WebDriverBy::xpath("(//div[contains(@id,'phoneVerificationControl_success_message')])[1]/following-sibling::div"), 0);
            $question = $q->getText() . ' ' . $phone->getText();
        }

        $this->saveResponse();

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->logger->debug("question: $question");
        $this->holdSession();
        $this->AskQuestion($question, null, 'question');

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'question') {
            if ($this->processSecurityCheckpoint()) {
                $this->saveResponse();
                return true;
            }
        }

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> $this->Question=$answer");

        $verificationCode = $this->waitForElement(WebDriverBy::xpath('//input[@id = "VerificationCode"]'), 0);

        if (!$verificationCode) {
            $this->saveResponse();

            return false;
        }

        $verificationCode->clear();
        $verificationCode->sendKeys($answer);

        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "emailVerificationControl_but_verify_code" or @id = "phoneVerificationControl_but_verify_code"]'), 0);

        if (!$button) {
            $this->saveResponse();

            return false;
        }

        $button->click();

        $error = $this->waitForElement(WebDriverBy::xpath('//*[@id = "emailVerificationControl_error_message" or @id = "phoneVerificationControl_error_message" or @id = "verificationCode-error"]'), 7);
        $this->saveResponse();

        // To ensure your account is secure, your password must be changed.
        $resetPassword = $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(),'To ensure your account is secure, your password must be changed.')]"), 0);
        $this->saveResponse();

        if ($resetPassword) {
            $this->throwProfileUpdateMessageException();
        }

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($this->Question, $error->getText(), 'question');

            return false;
        }

        $this->logger->debug("success");

        return true;
    }

    protected function getAmtrak()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->amtrak)) {
            $this->amtrak = new TAccountCheckerAmtrak();
            $this->amtrak->http = new HttpBrowser("none", new CurlDriver());
            $this->amtrak->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->amtrak->http);
            $this->amtrak->AccountFields = $this->AccountFields;
            $this->amtrak->http->SetBody($this->http->Response['body']);
            $this->amtrak->itinerariesMaster = $this->itinerariesMaster;
            $this->amtrak->HistoryStartDate = $this->HistoryStartDate;
            $this->amtrak->historyStartDates = $this->historyStartDates;
            $this->amtrak->http->LogHeaders = $this->http->LogHeaders;
            $this->amtrak->ParseIts = $this->ParseIts;
            $this->amtrak->ParsePastIts = $this->ParsePastIts;
            $this->amtrak->WantHistory = $this->WantHistory;
            $this->amtrak->WantFiles = $this->WantFiles;
            $this->amtrak->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->amtrak->http->setDefaultHeader($header, $value);
            }

            $this->amtrak->globalLogger = $this->globalLogger;
            $this->amtrak->logger = $this->logger;
            $this->amtrak->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->amtrak->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->amtrak;
    }

    public function Parse()
    {
        //$this->sendNotification('parse V2 // MI');
        $seleniumDriver = $this->http->driver;
        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        $this->http->GetURL('https://www.amtrak.com/guestrewards/account-overview.html');

        $body = null;
        foreach ($requests as $n => $xhr) {
            $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
            $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
            if (strstr($xhr->request->getUri(), 'dotcom/consumers/profile?agrNumber=')) {
                //$this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                $headers = $this->http->JsonLog(json_encode($xhr->request->getHeaders()));
                if (isset($headers->{'x-b2c-auth-token'})) {
                    $this->http->setDefaultHeader('x-b2c-auth-token', $headers->{'x-b2c-auth-token'});
                }
                $this->http->SetBody(json_encode($xhr->response->getBody()));
                break;
            }
        }
        $amtrak = $this->getAmtrak();
        $amtrak->Parse();
        $this->SetBalance($amtrak->Balance);
        $this->Properties = $amtrak->Properties;
        $this->ErrorCode = $amtrak->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $amtrak->ErrorMessage;
            $this->DebugInfo = $amtrak->DebugInfo;
        }

    }

    public function ParseItineraries()
    {
        $amtrak = $this->getAmtrak();
        return $amtrak->ParseItineraries();
    }
}
