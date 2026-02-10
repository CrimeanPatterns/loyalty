<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirmilesmeSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.airmilesme.com/en-qa/memberlogin";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
//        $this->setProxyBrightData(null, 'static', 'ae');
        $this->UseSelenium();
        $this->useGoogleChrome();
//        $this->useFirefox();
        $this->useCache();
        $this->disableImages();
        $this->http->setUserAgent($this->http->userAgent);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['cookies'])) {
            return false;
        }

        try {
            $this->http->GetURL("https://www.airmilesme.com/dsfsgsdfgsdfgsfs", [], 20);
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }
        $this->driver->manage()->deleteAllCookies();

        foreach ($this->State['cookies'] as $cookie) {
            $this->logger->debug("{$cookie['name']}={$cookie['value']}, {$cookie['domain']}");
            $this->driver->manage()->addCookie(['name' => $cookie['name'], 'value' => $cookie['value'], 'domain' => $cookie['domain']]);
        }

        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            // it works
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(2);
            $this->saveResponse();
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            throw new CheckRetryNeededException(2, 0);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//a/span[contains(text(), 'LOG OUT')]"), 7)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
            $this->http->GetURL("https://www.airmilesme.com/");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException();
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage());
            $this->saveResponse();
        }

        $loginShow = $this->waitForElement(WebDriverBy::id('disableIdLogin'), 10);
        $this->saveResponse();

        if (!$loginShow) {
            // retries, incapsula workaround
            if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]")) {
                throw new CheckRetryNeededException();
            }

            if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')] | //h1[contains(text(), 'This site can’t be reached')]")) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }

        $this->driver->executeScript("document.getElementById('acceptcookie').click()");

        $loginShow->click();

        $login = $this->waitForElement(WebDriverBy::id('login-modal-email-field'), 2);
        $pass = $this->waitForElement(WebDriverBy::id('login-modal-password-field'), 0);
        $button = $this->waitForElement(WebDriverBy::id('loginLabel'), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->parseReCaptchaInit();
        sleep(5);
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our website is currently undergoing')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Zend Optimizer not installed
        if ($message = $this->http->FindSingleNode("//h1[contains(text(),'Zend Optimizer not installed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing some technical difficulties.
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'We are experiencing some technical difficulties.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System Upgrade
        if (preg_match('/article\/general\/system-upgrade\.html/ims', $this->http->currentUrl())) {
            throw new CheckException('System Upgrade. We will be back shortly. Sorry for any inconvenience', ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 503) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 20;

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            $this->saveResponse();
            // catch errors
            if ($message = $this->waitForElement(WebDriverBy::xpath("//h2[contains(@class,'ffp-err-text')]"), 0)) {
                $this->logger->error("[message]: " . $message->getText());
                // Incorrect password. Please try again.
                if (
                    strstr($message->getText(), 'Incorrect password. Please try again.')
                    || strstr($message->getText(), 'Your email address / password does not match, please check and try again')
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }
                // The email address you have entered is not registered
                if (strstr($message->getText(), 'The email address you have entered is not registered')) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }
                // Please verify you're not a robot
                if (strstr($message->getText(), "Please verify you're not a robot")) {
                    throw new CheckRetryNeededException(2, 7, self::CAPTCHA_ERROR_MSG);
                }

                if (strstr($message->getText(), 'Safeguard your Air Miles and update password now')) {
                    return true;
                }
            }// if ($message = $this->waitForElement(WebDriverBy::xpath("//h2[contains(@class,'ffp-err-text')]"), 0))
            // Please enter a valid email address
            if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Please enter a valid email address')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            sleep(1);
            $time = time() - $startTime;
        }// while ($time < $sleep)

        if (in_array($this->AccountFields['Login'], [
            'victordress@gmail.com',
            'kassab.francois@gmail.com',
            'haifaharb@gmail.com',
            'parida.swayam@gmail.com',
            'guruvenketraman@gmail.com',
            'ho.jonathan.k@gmail.com',
            'taraboulsi1@yahoo.com',
            'hamad@alamry.ca',
            'alqassabh@yahoo.com',
            'cassa_khan@hotmail.com',
            'stefano.bianchi1@gmail.com',
            'Macrini@gmail.com',
            'kei@iloveqatar.net',
            'abudy99@hotmail.com',
            'claudio.monti@gmail.com',
            'cyunsun@gmail.com',
            'damien@littlepond.co.uk',
            'banebt@gmail.com',
            'paani83@hotmail.com',
            'salma.g3963@gmail.com',
            'aldilafs@gmail.com',
            'mg-pointscheme@nym.hush.com',
            'mohammed.khaldi.6@gmail.com',
            'Moradii3@yahoo.com',
            'marianne_espinassous@yahoo.fr',
            'queenie.lee@live.ca',
            'jehunt86@googlemail.com',
            'congxu.ke@gmail.com',
            'staderd@gmail.com',
            'amir.hafzalla@gmail.com',
            'viorel@yahoo.com',
            'mikehadjipa@gmail.com',
            'Balazs.torbagyi@oracle.com',
            'ashokblitz@gmail.com',
            'donald.mckay@uk.bp.com',
            'burashid70@gmail.com',
            'samson_samuel@hotmail.com',
            'faixi2020@gmail.com',
            'taibaly.serge@gmail.com',
            'nitin@samavia.com',
            'tarakenkhuis@gmail.com',
            'davidlecea@gmail.com',
            'Szilagyi.hu@gmail.com',
            'scampbell@apcoworldwide.com',
            'mgsmithsky@yahoo.com',
            'mtimosha@icloud.com',
            'alessandro.borgogna@strategyand.pwc.com',
            'mahmoud.elbosily@gmail.com',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            if ($this->http->currentUrl() !== self::REWARDS_PAGE_URL) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeoutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }

        if ($myAccount = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'MY ACCOUNT')]"), 10)) {
            $myAccount = $myAccount->getAttribute('href');
            $this->http->NormalizeURL($myAccount);
            $this->http->GetURL($myAccount);
            $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Card No')]/following-sibling::p[1]"), 10);
        }
        $this->saveResponse();
        $this->State['cookies'] = $this->driver->manage()->getCookies();
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//h2[contains(text(), 'Welcome')]", null, true, "/Welcome\, (.+)/ims"));
        // Card No
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Card No')]/following-sibling::p[1]"));
        // Balance - YOU CAN SPEND
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'YOU CAN SPEND')]/following-sibling::p[1]/text()[1]"));
        // Air Miles Collected
        $this->SetProperty("Collected", $this->http->FindSingleNode("//span[contains(., 'COLLECTED')]", null, true, self::BALANCE_REGEXP));
        // Air Miles Spent
        $this->SetProperty("Spent", $this->http->FindSingleNode("//span[contains(., 'SPENT')]", null, true, self::BALANCE_REGEXP));
        // Air Miles Adjusted
        $this->SetProperty("Adjusted", $this->http->FindSingleNode("//span[contains(., 'ADJUSTED')]", null, true, self::BALANCE_REGEXP));
        // Miles to Expire
        $this->SetProperty("MilesToExpire", $this->http->FindSingleNode("//p[contains(text(), 'AIR MILES EXPIRING')]/following-sibling::p[1]/text()[1]"));
        // Expiration Date
        $exp = $this->http->FindSingleNode("//p[contains(text(), 'AIR MILES EXPIRING')]/following-sibling::p[2]", null, true, "/on\s*([^<]+)/ims");

        if (isset($this->Properties["MilesToExpire"], $exp)) {
            if ($this->Properties["MilesToExpire"] === '0') {
                $this->ClearExpirationDate();
            } else {
                $this->SetExpirationDate(strtotime($exp));
            }
        }// if (isset($this->Properties["MilesToExpire"], $exp))
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    private function parseReCaptchaInit()
    {
        $this->logger->notice(__METHOD__);
        $recaptchaKey = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 5);
        $recaptcha = null;

        if ($recaptchaKey) {
            $recaptcha = $this->parseReCaptcha($recaptchaKey->getAttribute('data-sitekey'));

            if ($recaptcha === false) {
                return false;
            }

            $this->logger->notice("Remove iframe");
            //$this->driver->executeScript("$('div.g-recaptcha iframe').remove();");
            $this->driver->executeScript("$('textarea#g-recaptcha-response').val('{$recaptcha}')");

            return true;
        }

        return false;
    }
}
