<?php

namespace AwardWallet\Engine\amexbb;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class AmexbbSelenium extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    private const X_ERROR_MESSAGE = "//div[@class = 'bb-formatted-error-message']";
    private const X_ONE_TIME_PASSWORD = "//h1[@class = 'page-one-time-password__title']";
    private const X_LOCKED_ACCOUNT = "//p[contains(text(), 'locked your account')] | //h1[contains(text(), 'Your Account is Locked')]";
    private const X_GOTO_HOME = "//button[contains(text(), 'Go To Home')] | //span[contains(text(), 'Logout')] | //span[contains(text(), 'Hi, ')]";
    private const X_LOGIN_SUCCESSFUL = "//span[contains(text(), 'Logout')] | //span[contains(text(), 'Hi, ')]";
    private const X_CODE_NOT_CORRECT = "//span[contains(text(), 'is not correct')]";
    private const X_CODE_OLD = "//span[contains(text(), 'old verification code')]";

    private const X_SET_LOGGED_IN = [self::X_LOCKED_ACCOUNT, self::X_GOTO_HOME, self::X_LOGIN_SUCCESSFUL];
    private const X_SET_BAD_CODE = [self::X_CODE_NOT_CORRECT, self::X_CODE_OLD];

    /** @var \HttpBrowser */
    public $browser;

    private $headers = [
        "Accept"               => "application/json, text/plain, */*",
        "Accept-Language"      => "en-US",
        "Accept-Encoding"      => "gzip, deflate, br",
        "Channel"              => "WEB",
        "Content-Type"         => "application/json;charset=utf-8",
        "Origin"               => "https://secure.bluebird.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->useFirefox();
        $this->setKeepProfile(true);
        $this->http->saveScreenshots = true;
        /*
        //$this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
        */
        $this->setProxyBrightData();
    }

    public function LoadLoginForm(): bool
    {
        try {
            $this->http->GetURL("https://secure.bluebird.com/login");
        } catch (\NoSuchWindowException | \NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(3, 5);
        } catch (\UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (\NoAlertOpenException | \UnexpectedAlertOpenException $e) {
                $this->logger->error("LoadLoginForm -> exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("LoadLoginForm -> finally");
            }
        }

        $loginInput = $this->waitForElement(\WebDriverBy::xpath("
            //input[@id = 'bb-username']
            | //div[contains(text(), 'Access denied')]
            | //iframe[contains(@src, 'Incapsula')]"), 15);
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath("//div[contains(@class, 'bb-loading__loader')]"), 0)) {
            sleep(5);
            $loginInput = $this->waitForElement(\WebDriverBy::xpath("
                //input[@id = 'bb-username']
                | //div[contains(text(), 'Access denied')]
                | //iframe[contains(@src, 'Incapsula')]
            "), 15);
            $this->saveResponse();
        }

        try {
            if ($loginInput === null) {
                return $this->checkErrors();
            }

            if ($loginInput->getTagName() === 'iframe' || stripos($loginInput->getText(), 'Access denied') !== false) {
                $this->markProxyAsInvalid();

                throw new \CheckRetryNeededException(2, 1);
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
        } catch (\NoSuchWindowException | \NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(3, 5);
        }

        $passInput = $this->waitForElement(\WebDriverBy::id('bb-password'), 1);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $button = $this->waitForElement(\WebDriverBy::id('bb-submit'), 1);
        $this->saveResponse();

        $this->driver->executeScript('
            let oldEval = window.eval;
            window.eval = function(str) {
             // do something with the str string you got
             return oldEval(str);
            }
            
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener(\'load\', function() {
                    if (/accessToken/g.exec( this.responseText )) {
                        localStorage.setItem(\'responseData\', this.responseText);
                    }
                });
                           
                return oldXHROpen.apply(this, arguments);
            };
        ');

        $button->click();

        return true;
    }

    public function Login()
    {
        try {
            $element = $this->waitForElement(\WebDriverBy::xpath(implode(" | ", array_merge(
            [
                self::X_ERROR_MESSAGE,
                self::X_ONE_TIME_PASSWORD,
            ],
            self::X_SET_LOGGED_IN
        ))), 20);
            $this->saveResponse();

            if ($element === null) {
                return false;
            }

            if ($this->waitForElement(\WebDriverBy::xpath(self::X_LOGIN_SUCCESSFUL), 0)) {
                return true;
            }

            $this->saveResponse();

            if ($this->waitForElement(\WebDriverBy::xpath(self::X_ONE_TIME_PASSWORD), 0)) {
                if (
                isset($this->Answers[$this->Question])
                && ($haveCode = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'I have a code.')]"), 0))
            ) {
                    $haveCode->click();
                } else {
                    // Send via email to v*********h@gmail.com
                    $label = $this->waitForElement(\WebDriverBy::xpath("//label[@for ='verifyUsingEmail']"), 1);

                    if ($label === null) {
                        return false;
                    }

                    if ($this->isBackgroundCheck()) {
                        $this->Cancel();
                    }

                    $label->click();
                    $this->waitForElement(\WebDriverBy::xpath("//button[@class = 'bb-navigation-footer__next-btn']"), 0)->click();
                }

                $this->waitEmailSent();

                if (!$this->processOTP()) {
                    return false;
                }
            }

            if ($element = $this->waitForElement(\WebDriverBy::xpath(self::X_LOCKED_ACCOUNT), 0)) {
                throw new \CheckException($element->getText(), ACCOUNT_LOCKOUT);
            }

            if ($message = $this->http->FindSingleNode(self::X_ERROR_MESSAGE)) {
                $this->logger->error("[Error]: {$message}");

                if (
                strstr($message, 'The username and password combination isn\'t right.')
            ) {
                    throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error("[Exception]: {$e->getMessage()}");

            if (
                strstr($e->getMessage(), 'Tried to run command without establishing a connection Build info: version')
            ) {
                throw new \CheckRetryNeededException(2, 0);
            }

            throw $e;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $input = $this->waitForElement(\WebDriverBy::xpath("//input[@data-testid = 'page-one-time-password__textField_verifyCode']"), 3);
        $this->saveResponse();

        if (
            !$input
            && $this->waitForElement(\WebDriverBy::xpath("
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                | //p[contains(text(), 'Health check')]
            "), 0)
            || $this->http->FindSingleNode("
                //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
            ")
        ) {
            $this->saveResponse();

            return $this->LoadLoginForm() && $this->Login();
        }

        return $this->processOTP();
    }

    public function Parse()
    {
        $data = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseData]: " . $data);
        $this->logger->debug(var_export($data, true), ["pre" => true]);
        $data = $this->http->JsonLog($data);

        $this->browser = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $headers = [
            "Authorization" => "Bearer {$data->accessToken}",
        ];
        $this->browser->GetURL("https://ui.bluebird.com/api/me", $this->headers += $headers);
        $response = $this->browser->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Account Number
        $this->SetProperty("Number", $response->accountNumber ?? null);

        $this->browser->GetURL("https://ui.bluebird.com/api/accounts", $this->headers);
        $this->SetProperty("CombineSubAccounts", false);
        $accounts = $this->browser->JsonLog();

        foreach ($accounts as $account) {
            switch ($account->type) {
                case 'main':
                    // Balance - Available balance
                    $this->SetBalance($account->availableBalance);

                    break;

                case 'sub':
                    if (count($accounts) == 1) {
                        // Balance - Available balance
                        $this->SetBalance($account->availableBalance);
                    } else {
                        $this->AddSubAccount([
                            'Code'        => 'amexbb' . $account->id,
                            'DisplayName' => $account->summaryName,
                            'Balance'     => $account->availableBalance,
                            'Number'      => $account->lastFourDigitsOfCardNumber,
                        ], true);
                    }

                    break;

                case "reserved":
                    $this->logger->debug("skip '{$account->name}'");

                    break;

                case 'smartPurse':
                    // SubAccount - Walmart® Buck$
                    $this->AddSubAccount([
                        'Code'        => 'amexbbWalmartBucks',
                        'DisplayName' => "Walmart® Buck$",
                        'Balance'     => $account->availableBalance,
                    ], true);

                    break;

                default:
                    $this->sendNotification("unknown account type {$account->type} // RR");
            }
        }// foreach ($accounts as $account)
    }

    private function processOTP(): bool
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->Answers[$this->Question])) {
            $this->logger->notice("answer not found");

            return false;
        }

        $input = $this->waitForElement(\WebDriverBy::xpath("//input[@data-testid = 'page-one-time-password__textField_verifyCode']"), 3);
        $this->saveResponse();

        if (!$input) {
            return false;
        }
        $input->sendKeys($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->saveResponse();

        $this->driver->executeScript('
            let oldEval = window.eval;
            window.eval = function(str) {
             // do something with the str string you got
             return oldEval(str);
            }
            
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener(\'load\', function() {
                    if (/accessToken/g.exec( this.responseText )) {
                        localStorage.setItem(\'responseData\', this.responseText);
                    }
                });
                           
                return oldXHROpen.apply(this, arguments);
            };
        ');

        $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Verify')]"), 1)->click();

        sleep(5);

        $this->waitForElement(\WebDriverBy::xpath(implode(" | ", array_merge(
            self::X_SET_BAD_CODE,
            self::X_SET_LOGGED_IN
        ))), 10);
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath(self::X_LOCKED_ACCOUNT), 0)) {
            // will be handled in LoadLoginForm
            return true;
        }

        if ($this->waitForElement(\WebDriverBy::xpath(self::X_LOGIN_SUCCESSFUL), 0)) {
            return true;
        }

        if ($element = $this->waitForElement(\WebDriverBy::xpath(self::X_GOTO_HOME), 0)) {
            $element->click();

            return true;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath(self::X_CODE_NOT_CORRECT), 0)) {
            $input->clear();
            $this->holdSession();
            $this->AskQuestion($this->Question, $error->getText(), "Question");

            return false;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath(self::X_CODE_OLD), 0)) {
            $input->clear();
            $message = $error->getText();
            $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Resend it.')]"), 1)->click();
            $this->waitEmailSent($message);

            return false;
        }

        return false;
    }

    private function waitEmailSent($error = null): void
    {
        $this->logger->notice(__METHOD__);
        $sentText = "We just sent a one-time verification code to ";
        $sentElement = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), '$sentText')]"), 7);
        $this->saveResponse();

        if (!$sentElement) {
            return;
        }
        $this->holdSession();
        $email = trim(str_replace($sentText, "", trim($sentElement->getText())), " .");
        $this->AskQuestion("Please enter temporary six digit verification code which was sent to the following email: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.", $error, "Question");
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Oops, we are temporarily unavailable.")]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
