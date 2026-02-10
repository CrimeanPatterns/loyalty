<?php
use AwardWallet\Engine\ProxyList;

class TAccountCheckerDisneyvacationSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private ?TAccountCheckerDisneyvacation $checker = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        //$this->useGoogleChrome();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://disneyvacationclub.disney.go.com/sign-in/?appRedirect=http%3A%2F%2Fdisneyvacationclub.disney.go.com%2F");

        $iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'disneyid-iframe' or @id = 'oneid-iframe']"), 30);
        $this->saveResponse();
        $checker = $this->getChecker();

        if (!$iframe) {
            $this->logger->error('no iframe');

            return $checker->checkErrors();
        }

        $this->driver->switchTo()->frame($iframe);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Email"]'), 7);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "BtnSubmit"]'), 0);

        if (!$loginInput || !$button) {
            return $checker->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $button->click();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Password"]'), 8);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "BtnSubmit"]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$passwordInput || !$button) {
            if ($message = $this->http->FindSingleNode('//*[@id = "InputIdentityFlowValue-error"]')) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == 'Please enter a valid email address.'
                    || strstr($message, 'This email isn\'t properly formatted.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            if ($this->http->FindSingleNode('//h1[span[contains(text(), "Create Your Account")]]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $checker->checkErrors();
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/"access_token"|"errors":\[\{"code":/g.exec( this.responseText )) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                    
                    if (/"sessionId":/g.exec( this.responseText )) {
                        localStorage.setItem("responseDataQuestion", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');


        $apiKey = '';
        /*try {
            $executor = $this->getPuppeteerExecutor();
            $json = $executor->execute(
                __DIR__ . '/puppeteer.js'
            );
        } catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;

            return false;
        }
//            $button->click();
//            $this->logger->debug(var_export($json, true), ['pre' => true]);
//            $this->logger->info(json_encode($json));
        $this->http->JsonLog(json_encode($json));
        $apiKey = ArrayVal($json['headers'], 'api-key');*/

        $this->logger->debug("api-key: {$apiKey}");

        sleep(4);
        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseData]: " . $responseData);

        $responseDataQuestion = $this->driver->executeScript("return localStorage.getItem('responseDataQuestion');");
        $this->logger->info("[Form responseDataQuestion]: " . $responseDataQuestion);

        /*
        $selenium->driver->switchTo()->defaultContent();
        $this->savePageToLogs($selenium);
        */
        $this->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'Welcome Back Home,')]
                | //p[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]
                | //h2/span[contains(text(), 'Please Update Your Account')]
                | //h2[contains(text(), 'Please Update Your Account')]
                | //p[contains(text(), 'To continue, we need the following information:')]
                | //p[contains(text(), 't find an account matching that information.')]
                | //h2[contains(text(), 'Please verify your Membership')]
                | //h1[contains(text(), 'Please Verify Your Membership')]
                | //span[contains(text(), 'Email me at')]
                | //span[contains(text(), 'Text a code to my phone')]
                | //p[contains(text(), 'We sent a code to')]
                | //p[contains(@class, 'login-credentials-error')]
            "), 15);
        $this->saveResponse();

        // invalid credentials false/posirive issue
        if ($sendCode = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Email me at')] | //span[contains(text(), 'Text a code to my phone')]"), 0)) {
            $this->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/"access_token"|"errors":\[\{"code":/g.exec( this.responseText )) {
                                localStorage.setItem("responseData", this.responseText);
                            }
                            
                            if (/"sessionId":/g.exec( this.responseText )) {
                                localStorage.setItem("responseDataQuestion", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                ');

            $sendCode->click();

            $this->waitForElement(WebDriverBy::xpath("
                    //p[contains(text(), 'Welcome Back Home,')]
                    | //p[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')]
                    | //h2/span[contains(text(), 'Please Update Your Account')]
                    | //h2[contains(text(), 'Please Update Your Account')]
                    | //p[contains(text(), 'To continue, we need the following information:')]
                    | //p[contains(text(), 't find an account matching that information.')]
                    | //h2[contains(text(), 'Please verify your Membership')]
                    | //h1[contains(text(), 'Please Verify Your Membership')]
                    | //p[contains(@class, 'login-credentials-error')]
                "), 15);
            $this->saveResponse();

            sleep(4);
            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $responseDataQuestion = $this->driver->executeScript("return localStorage.getItem('responseDataQuestion');");
            $this->logger->info("[Form responseDataQuestion]: " . $responseDataQuestion);
        }

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == "TPR-DVC.WEB-PROD.token") {
                $value = $this->http->FindPreg("/5=([^\;]+)/", false, $cookie['value']);
                $this->logger->debug($value);
                $value = $this->http->JsonLog(base64_decode($value));

                if (!empty($value)) {
                    $this->State['Authorization'] = $value->access_token;
                    $token = $this->State['Authorization'];
                    $this->http->setDefaultHeader("Authorization", "Bearer {$token}");
                    $this->State['SWID'] = $value->swid;
                    $swid = $this->State['SWID'];
                    $this->http->setDefaultHeader("swid", $swid);
                }
            }

            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        if (
            !empty($responseData)
            && (isset($responseData->data->token->access_token) || !empty($responseDataQuestion))
        ) {
            if ($questionObject = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We sent a code to')]"), 0)) {
                $question = $questionObject->getText();
                $error = ArrayVal($this->http->JsonLog($responseData, 5, true), 'error');
                $assessmentId = $error['errors'][0]['data']['assessmentId'] ?? null;
                $responseDataQuestion = $this->http->JsonLog($responseDataQuestion);
                $sessionId = $responseDataQuestion->data->sessionId ?? null;

                if (!$sessionId) {
                    $this->logger->error("something went wrong, sessionId not found");

                    return false;
                }

                $headers = [
                    "Accept"          => "*/*",
                    "Content-Type"    => "application/json",
                    "correlation-id"  => $correlationId ?? $checker->gen_uuid(),
                    "conversation-id" => $conversationId ?? $checker->gen_uuid(),
                    "oneid-reporting" => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=', //todo: fake
                    "device-id"       => 'null',
                    "expires"         => -1,
                    "Origin"          => "https://cdn.registerdisney.go.com",
                    "Referer"         => 'https://cdn.registerdisney.go.com/',
                ];

                $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key', $apiKey));
                $headers["correlation-id"] = ArrayVal($this->http->Response['headers'], 'correlation-id', null) ?? $checker->gen_uuid();
                $headers["conversation-id"] = ArrayVal($this->http->Response['headers'], 'conversation-id', null) ?? $checker->gen_uuid();

                $this->State['2faHeaders'] = $headers;
                $this->State['2faData'] = [
                    "passcode"     => "",
                    "sessionIds"   => [
                        $sessionId,
                    ],
                    "assessmentId" => $assessmentId,
                ];

                $this->Question = $question;
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";

                return false;
            }

            if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the account system is having a problem.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg('/role="alert">We didn\'t find an account matching that information. Make sure you entered it correctly or/')) {
                throw new CheckException("We didn't find an account matching that information. Make sure you entered it correctly or create an account.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode("//h2[
                        contains(text(), 'Please Update Your Account')
                        or contains(text(), 'Stay in touch!')
                    ]
                        | //h2/span[contains(text(), 'Please Update Your Account')]
                    ")
            ) {
                $this->throwAcceptTermsMessageException();
            }

            $this->loginResp = $this->http->JsonLog($responseData, 5, true);
            $checker->jsonToForm($this->loginResp);
            $this->http->SetBody($responseData);

            return true;
        } elseif ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the account system is having a problem.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        } elseif ($this->http->FindSingleNode("//h2[contains(text(), 'Please verify your Membership')] | //h1[contains(text(), 'Please Verify Your Membership')]")) {
            throw new CheckException("Disney Vacation Club website is asking you to verify your Membership, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } elseif ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Yes! I would like to receive updates, special offers and other information from')] | //p[contains(text(), 'To continue, we need the following information:')]"), 0)) {
            $this->throwProfileUpdateMessageException();
        } elseif ($this->http->FindSingleNode("//h2[
                    contains(text(), 'Please Update Your Account')
                    or contains(text(), 'Stay in touch!')
                ]
                    | //h2/span[contains(text(), 'Please Update Your Account')]
                ")
        ) {
            $this->throwAcceptTermsMessageException();
        } elseif (!empty($token) && !empty($swid)) {
//                $this->http->GetURL("https://dvc-lightcycle-api.wdprapps.disney.com/api/v1/profile/{$swid}");
            $this->http->GetURL("https://registerdisney.go.com/jgc/v8/client/TPR-DVC.WEB-PROD/guest/{$swid}?feature=no-password-reuse&expand=profile&expand=displayname&expand=linkedaccounts&expand=marketing&expand=entitlements&expand=s2&langPref=en-US");
            $this->loginResp = $this->http->JsonLog(null, 5, true);
            $checker->jsonToForm($this->loginResp);
            $this->http->SetBody($responseData);

            return true;
        } elseif (!empty($responseData)) {
            $this->loginResp = $this->http->JsonLog($responseData, 5, true);
            $checker->jsonToForm($this->loginResp);
            $this->http->SetBody($responseData);
        }
        return true;
    }



    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }
        $question = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(),"We have sent a verification code to")]'), 0);
        $this->saveResponse();

        if (!$question) {
            return true;
        }

        $question = $question->getText();
        $this->logger->debug($question);
        $this->holdSession();
        $this->AskQuestion($question, null, 'emailCode');

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> $this->Question=$answer");

        $securityAnswer = $this->waitForElement(WebDriverBy::xpath('(//input[contains(@class,"inputGroup-module__item_input__")])[1]'), 0);

        if (!$securityAnswer) {
            return false;
        }

        for ($i = 0; $i < strlen($answer); $i++) {
            $codeInput = $this->waitForElement(WebDriverBy::xpath("(//input[contains(@class,'inputGroup-module__item_input__')])[$i]"), 0);

            if (!$codeInput) {
                $this->logger->error("input not found");

                break;
            }

            $codeInput->clear();
            $codeInput->sendKeys($answer[$i]);
        }
        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class,"tripui-online-btn")and contains(text(),"Sign In")]'), 0);
        if (!$button) {
            return false;
        }
        $button->click();

        $error = $this->waitForElement(WebDriverBy::xpath("
            //form//*[contains(text(), 'Verification code error, please check and try again')]
        "), 7);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($this->Question, $error->getText(), 'emailCode');

            return false;
        }

        $this->logger->debug("success");

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: ".$this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'emailCode') {
            //$this->sendNotification('check 2fa // MI');
            if ($this->processSecurityCheckpoint()) {
                $this->saveResponse();
                $checker = $this->getChecker();
                //return $checker->loginSuccessful();
            }
        }

        return false;
    }

    protected function getChecker(): TAccountCheckerDisneyvacation
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->checker)) {
            $this->checker = new TAccountCheckerDisneyvacation();
            $this->checker->http = new HttpBrowser("none", new CurlDriver());
            $this->checker->http->setProxyParams($this->http->getProxyParams());
            $this->http->brotherBrowser($this->checker->http);
            $this->checker->AccountFields = $this->AccountFields;
            $this->checker->itinerariesMaster = $this->itinerariesMaster;
            $this->checker->HistoryStartDate = $this->HistoryStartDate;
            $this->checker->historyStartDates = $this->historyStartDates;
            $this->checker->http->LogHeaders = $this->http->LogHeaders;
            $this->checker->ParseIts = $this->ParseIts;
            $this->checker->ParsePastIts = $this->ParsePastIts;
            $this->checker->WantHistory = $this->WantHistory;
            $this->checker->WantFiles = $this->WantFiles;
            $this->checker->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->checker->globalLogger = $this->globalLogger;
            $this->checker->logger = $this->logger;
            $this->checker->onTimeLimitIncreased = $this->onTimeLimitIncreased;

            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->logger->debug("set cookies");
                $this->logger->debug($cookie['name']);
                $this->checker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        }
        return $this->checker;
    }

    public function Login()
    {
        $this->savePageToLogs($this);
        $checker = $this->getChecker();
        return $checker->Login();
    }

    public function Parse()
    {
        $checker = $this->getChecker();
        $host = $this->http->getCurrentHost();
        $this->logger->debug("host: $host");
        $checker->Parse($host);
        $this->SetBalance($checker->Balance);
        $this->Properties = $checker->Properties;
        $this->ErrorCode = $checker->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $checker->ErrorMessage;
            $this->DebugInfo = $checker->DebugInfo;
        }
    }

    public function ParseItineraries()
    {
        $checker = $this->getChecker();
        $checker->ParseItineraries();
    }
}
