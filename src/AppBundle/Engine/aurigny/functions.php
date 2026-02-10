<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAurigny extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.aurigny.com/dashboard';

    private $currentItin = 0;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
        $this->http->GetURL('https://www.aurigny.com/login');
//        $this->http->GetURL("https://www.aurigny.com/dashboard");
        if (!$this->http->ParseForm(null, "//div[@class='login-form']/form")) {
            return $this->checkErrors();
        }
        */

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are currently conducting maintenance to our services and should return")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->selenium();

        return true;

        $this->http->SetInputValue('frequent_flyer_id', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        if ($key = $this->http->FindSingleNode("//script[contains(@src,'https://www.google.com/recaptcha/api.js?render=')]/@src", null, false, '/render=(\w+)/')) {
            $captcha = $this->parseReCaptcha($key, true);

            if ($captcha !== false) {
                $this->http->SetInputValue('token', $captcha);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# We are currently undergoing system maintenance
        /*if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We are currently undergoing system maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }*/

        return false;
    }

    public function Login()
    {
        $headers = [
            "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
        ];
//        if (!$this->http->PostForm($headers)) {
//            return $this->checkErrors();
//        }

        // Apologies for the interruption
        if ($this->http->currentUrl() == 'https://www.aurigny.com/verify') {
            $this->http->ParseForm("verification"); // not working because no any inputs into form
            /*
            $key = $this->http->FindSingleNode('//form[@id = "verification"]/div[contains(@class, "g-recaptcha")]/@data-sitekey');
            $captcha = $this->parseReCaptcha($key);
            if ($captcha === false) {
                return false;
            }
            $this->http->Form = [];
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->FormURL = 'https://www.aurigny.com/verify';
            $this->http->PostForm($headers);

            $this->http->GetURL('https://www.aurigny.com/login');
            if (!$this->http->ParseForm(null, "//div[@class='login-form']/form")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('frequent_flyer_id', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            if ($key = $this->http->FindSingleNode("//script[contains(@src,'https://www.google.com/recaptcha/api.js?render=')]/@src", null, false, '/render=(\w+)/')) {
                $captcha = $this->parseReCaptcha($key, true);
                if ($captcha !== false) {
                    $this->http->SetInputValue('token', $captcha);
                }
            }
            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
            */
        }

        // login successful
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//text()[
                contains(.,'The email/Frequent Flyer number and/or password entered are incorrect. Please re-enter your details.')
                or contains(.,'Please double check your email addres')
                or contains(.,'The email address or password entered is incorrect.')
            ]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(@class, "text-danger")]/strong')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Sign in failed')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Points')]/following-sibling::td", null, false, self::BALANCE_REGEXP));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//td[contains(text(), 'Name')]/following-sibling::td")));
        // Membership No
        $this->SetProperty('AccountNumber', beautifulName($this->http->FindSingleNode("//td[contains(text(), 'Membership No')]/following-sibling::td")));
        // Joining date
        $this->SetProperty('JoiningDate', beautifulName($this->http->FindSingleNode("//td[contains(text(), 'Joining date')]/following-sibling::td")));
    }

    public function ParseItineraries()
    {
        if (trim($this->http->FindSingleNode("//div[@id='upcoming-flights']//ul[@class='db-flight-list']")) === '') {
            return $this->noItinerariesArr();
        }
        $nodes = $this->http->XPath->query("//div[@id='upcoming-flights']//ul/li");

        $bookings = [];

        foreach ($nodes as $node) {
            $booking = $this->http->FindSingleNode("./div[contains(@class,'booking')]", $node);
            $flightCode = $this->http->FindSingleNode("./div[contains(@class,'flight-origin-dest')]", $node);
            $date = $this->http->FindSingleNode("./div[contains(@class,'date')]", $node, false, '/\w+ (\d+.+)/');

            if (preg_match('/([A-Z]{3}) - ([A-Z]{3})/', $flightCode, $m)) {
                $bookings[$booking][] = [
                    'depCode' => $m[1],
                    'arrCode' => $m[2],
                    'date'    => strtotime($this->ModifyDateFormat($date)),
                ];
            }
        }

        foreach ($bookings as $confNo => $booking) {
            $this->http->GetURL("https://www.aurigny.com/manage/dashboard?reference={$confNo}");

            if ($err = $this->http->FindSingleNode("//p[contains(text(),'Your session has expired. Please sign in again')]")) {
                $this->logger->error($err);

                continue;
            }
            $this->logger->debug('Merge:');
            $this->logger->debug(var_export($booking, true), ['pre' => true]);
            $this->ParseItineraryHtml($confNo, $booking);
        }

        return [];
    }

    protected function parseReCaptcha($key = null, $isV3 = false)
    {
        $this->logger->notice(__METHOD__);
        /*if (!$key) {
            $key = $this->http->FindSingleNode("//script[contains(@src,'https://www.google.com/recaptcha/api.js?render=')]/@src", null, false, '/render=(\w+)/');
        }*/
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

        if ($isV3) {
            $parameters += [
                "version"   => "v3",
                "action"    => "login",
                "min_score" => 0.7,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function ParseItineraryHtml($confNo, $booking)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();
        $this->currentItin++;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$confNo}", ['Header' => 3]);

        $f->general()->confirmation($confNo);

        foreach ($booking as $segment) {
            $s = $f->addSegment();
            $s->departure()->code($segment['depCode']);
            $s->arrival()->code($segment['arrCode']);

            // Wed 21st Sep 2022
            $date = date('D d', $segment['date']);
            $wrapper = $this->http->XPath->query($xpath = "//div[contains(@class,'fn-title') and .//span[contains(text(), '$date')]]/following-sibling::div[1]");
            $this->logger->info($xpath);

            foreach ($wrapper as $wrap) {
                $depTime = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-time origin')]", $wrap);
                $arrTime = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-time destination')]", $wrap);
                $s->departure()->date(strtotime($depTime, $segment['date']));
                $s->arrival()->date(strtotime($arrTime, $segment['date']));

                $depName = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-airport origin desktop-format')]", $wrap);
                $arrName = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-airport destination desktop-format')]", $wrap);
                $s->departure()->name($depName);
                $s->arrival()->name($arrName);

                $flName = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-no')]", $wrap, false, '/([A-Z]{2})\s*\d+/');
                $flNum = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-no')]", $wrap, false, '/[A-Z]{2}\s*(\d+)/');
                $s->airline()->name($flName);
                $s->airline()->number($flNum);

                $duration = $this->http->FindSingleNode(".//div[contains(@class,'fn-flight-duration mobile-format')]", $wrap);
                $s->extra()->duration($duration);
            }
        }

        $fees = $this->http->XPath->query("//div[contains(@class,'fn-title') and .//span[contains(text(), 'Cost Breakdown')]]/following-sibling::div[1]//div[@class='fn-breakdown']");

        foreach ($fees as $fee) {
            $name = $this->http->FindSingleNode(".//div[contains(@class, 'fn-breakdown-col charges')]", $fee);
            $value = $this->http->FindSingleNode(".//div[contains(@class, 'fn-breakdown-col total')]", $fee);

            if (preg_match("/^([^\d]{1,3})([\d,.]+)$/", $value, $m)) {
                $f->price()->fee($name, $m[2]);
            }
        }

        $price = $this->http->FindSingleNode("//div[contains(@class,'fn-title') and .//span[contains(text(), 'Cost Breakdown')]]/following-sibling::div[1]//div[@class='fn-total-col cost']");

        if (preg_match("/^([^\d]{1,3})([\d,.]+)$/", $price, $m)) {
            $f->price()
                ->currency($this->currency($m[1]))
                ->total(PriceHelper::cost($m[2]));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]")) {
            $this->logger->debug('login success');

            return true;
        }

        return false;
    }

    private function currency($s)
    {
        if (preg_match('#^\s*([A-Z]{3})\s*$#', $s, $m)) {
            return $m[1];
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//            $selenium->disableImages();
//            $selenium->useCache();

            $selenium->usePacFile(false);

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.aurigny.com/dashboard");

            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "SIGN IN")]'), 10);

            if ($signIn) {
                $signIn->click();
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "frequent_flyer_id"] | //label[contains(text(), "email address ")]/preceding-sibling::input'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"] | //label[contains(text(), "password")]/preceding-sibling::input'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login") or contains(text(), "Sign In")]'), 0);
            $this->saveToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error('Something went wrong');

                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveToLogs($selenium);

            if ($key = $this->http->FindSingleNode("//script[contains(@src,'https://www.google.com/recaptcha/api.js?render=')]/@src", null, false, '/render=(\w+)/')) {
//                $captcha = $this->parseReCaptcha($key, true);
//                if ($captcha !== false) {
//                    $this->http->SetInputValue('token', $captcha);
//                }
                $selenium->driver->executeScript("
                    document.querySelector('div.login-form button.primary, .btn-primary').click()
                ");
//                $selenium->driver->executeScript("
//                    var el = document.createElement('input');
//                    el.type = 'hidden';
//                    el.value = \"{$captcha}\";
//                    el.name = 'token';
//                    document.querySelector('div.login-form > form').appendChild(el);
//                    document.querySelector('div.login-form > form').submit();
//                ");
            } else {
//                $button->click();
                $selenium->driver->executeScript("
                    document.querySelector('div.login-form button.primary, .btn-primary').click()
                ");
            }

            $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Apologies for the interruption")] | //div[@class = "alert callout"] | //a[@href= "/logout" and contains(text(), "Logout")] | //p[contains(@class, "text-danger")]/strong'), 5);
            $this->saveToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Apologies for the interruption")]'), 5)) {
                $cont = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);

                if (!$cont) {
                    $this->logger->error('Something went wrong');

                    return false;
                }
                $key = $this->http->FindSingleNode('//form[@id = "verification"]/div[contains(@class, "g-recaptcha")]/@data-sitekey');
                $captcha = $this->parseReCaptcha($key);

                if ($captcha === false) {
                    $this->logger->error('Something went wrong');

                    return false;
                }
//                $this->logger->debug("Remove iframe");
//                $selenium->driver->executeScript("$('div.g-recaptcha iframe').remove();");
                $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
                $cont->click();
                $selenium->waitForElement(WebDriverBy::xpath('//div[@class = "alert callout"] | //a[@href= "/logout" and contains(text(), "Logout")]'), 5);

                $selenium->http->GetURL("https://www.aurigny.com/dashboard");

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "frequent_flyer_id"] | //label[contains(text(), "email address / user name")]/preceding-sibling::input'), 10);
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"] | //label[contains(text(), "password")]/preceding-sibling::input'), 0);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login") or contains(text(), "Sign In")]'), 0);
                $this->saveToLogs($selenium);

                if (!$loginInput || !$passwordInput || !$button) {
                    $this->logger->error('Something went wrong');
                    $cookies = $selenium->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                            $cookie['expiry'] ?? null);
                    }

                    return false;
                }
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $this->saveToLogs($selenium);
                $selenium->driver->executeScript("
                    document.querySelector('div.login-form button.primary, .btn-primary').click()
                ");
                $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Apologies for the interruption")] | //div[@class = "alert callout"] | //a[@href= "/logout" and contains(text(), "Logout")] | //p[contains(@class, "text-danger")]/strong'), 5);

                $this->saveToLogs($selenium);
            }

            $this->saveToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
