<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCitilink extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use DateTimeTools;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
        $this->setScreenResolution([1920, 1080]);

//        $this->http->SetProxy($this->proxyDOP());
//        $this->setProxyBrightData(null, 'static', 'ru');
        if ($this->attempt == 1) {
            $this->setProxyBrightData(null, "dc_ips_ru", "ru");
        } else {
            $this->http->SetProxy($this->proxyReCaptcha());
        }
        $this->http->saveScreenshots = true;
//        $this->disableImages();
//        $this->useCache();
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['cookies'])) {
            return false;
        }

        try {
            $this->http->GetURL("https://www.citilink.ru/dsfsgsdfgsdfgsfs", [], 20);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
        $this->driver->manage()->deleteAllCookies();

        foreach ($this->State['cookies'] as $cookie) {
            $this->logger->debug("{$cookie['name']}={$cookie['value']}, {$cookie['domain']}");
            $this->driver->manage()->addCookie(['name' => $cookie['name'], 'value' => $cookie['value'], 'domain' => $cookie['domain']]);
        }

        $this->http->GetURL("https://www.citilink.ru/profile/club/", [], 20);
        $this->logger->debug($this->http->currentUrl());
        $logout = $this->waitForElement(WebDriverBy::id('exitBtn'), 7, false);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
        $this->SendLoginForm();
        /*
//        $this->http->GetURL("https://m.citilink.ru");
        $this->http->GetURL("https://m.citilink.ru/profile/");
        sleep(10);
        // save page to logs
        $this->driver->executeScript('window.stop();');
        $this->saveResponse();
        $this->driver->executeScript("
                var a = injector = document.querySelector('[ng-app]'); var b = angular.element(a).injector('app.services').get('conf');
                var container = document.createElement('p');
                container.id = 'token';
                container.setAttribute('value', b.getPrimaryToken());
                document.body.appendChild(container);
        ");
        $token = $this->waitForElement(WebDriverBy::id('token'), 5, false);
        $this->saveResponse();
        $key = $this->http->FindPreg("/div vc-recaptcha=\"\" key=\"'([^\"\']+)/");
        if (!$token || !$key)
            return false;
        $token = $token->getAttribute('value');

        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();
        foreach ($cookies as $cookie)
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);

        $this->browser->LogHeaders = true;
        $this->browser->SetProxy($this->http->GetProxy());
//        $this->browser->GetURL($this->http->currentUrl());

        if ($mainjs = $this->http->FindPreg("/src=\"(js\/main\.build\.js\?[^\"]+)/")) {
            $this->http->NormalizeURL($mainjs);
            $this->browser->GetURL($mainjs);
        }
        $this->browser->GetURL("https://m.citilink.ru/app/common/directives/login/login.directive.html?hash=1641afd45568553ef564b7d404a3fac1");
        if (!$this->browser->ParseForm("loginForm"))
            return false;

        $this->browser->Form = [];
        $this->browser->FormURL = 'https://login.citilink.ru/mauth/login/';
        $this->browser->SetInputValue('login', $this->AccountFields["Login"]);
        $this->browser->SetInputValue('password', $this->AccountFields["Pass"]);
        $this->browser->SetInputValue('source', "m.citilink.ru");
        $this->browser->SetInputValue('token', $token);

        $captcha = $this->parseReCaptcha($key);
        if ($captcha === false)
            return false;
        $this->browser->SetInputValue('g-recaptcha-response', $captcha);
        $this->browser->PostForm();


        $this->browser->GetURL("https://api.citilink.ru/v1/profile/");
        $this->browser->JsonLog();
        $this->browser->GetURL("https://api.citilink.ru/v1/profile/clubcard/");
        $this->browser->JsonLog();
        return false;*/

        return true;
    }

    public function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        /*
        $url = $this->http->FindSingleNode("//form[contains(@class, 'auth-form_js')]//div[@class = 'captcha-block__image']/@style", null, true, "/background-image: url\(([^\)]+)/ims");
        if (!$url) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $this->logger->debug("Captcha URL -> ".$url);

        return $this->recognizeCaptchaByURL($this->recognizer, $url, null, ['language' => 1, 'numeric' => 2]);
        */

        $img = $this->waitForElement(WebDriverBy::xpath("//form[contains(@class, 'auth-form_js')]//div[@class = 'CaptchaMic__image-wrapper']"), 0);

        if (!$img) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, ['language' => 1, 'numeric' => 2]);
        unlink($pathToScreenshot);

        return $captcha;
    }

    public function Login()
    {
        $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Выйти")]'), 7, false);
        $this->saveResponse();

        if (
            $logout
            || stristr($this->http->Response['body'], '"userEmail":"' . $this->AccountFields["Login"] . '"')
            || stristr($this->http->Response['body'], '"phone":"' . $this->AccountFields["Login"] . '"')
        ) {
            return true;
        }
        // Неверный логин или пароль
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Неверный логин или пароль')]")) {
            $this->SendLoginForm();
            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Выйти")]'), 7, false);
            $this->saveResponse();

            if ($logout) {
                $this->captchaReporting($this->recognizer);

                return true;
            }// if ($logout)

            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Неверный логин или пароль')]")) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }// if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Неверный логин или пароль')]"))
        }

        // Вы неправильно ввели код с картинки
        if ($this->http->FindSingleNode("//div[contains(text(), 'Вы неправильно ввели код с картинки')]")) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }

        if ($message = $this->http->FindSingleNode('
                //p[span[contains(text(), "нас")] and span[contains(text(), "сломалось")]]
                | //p[span[contains(text(), "технические")] and span[contains(text(), "работы")]]
                | //div[contains(text(), "Превышен лимит попыток входа. Попробуйте позднее")]
            ')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//body[contains(text(), '429: Too Many Requests')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 5);
        }

        return false;
    }

    public function Parse()
    {
        try {
            $this->http->GetURL("https://www.citilink.ru/profile/club/");
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(3);
            $this->http->GetURL("https://www.citilink.ru/profile/club/");
        }
        $this->State['cookies'] = $this->driver->manage()->getCookies();
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "profile-spa")]/span'), 5);
        $profile = $this->waitForElement(WebDriverBy::xpath('//div[@id = "profile-root"]'), 5);

        $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML;'));
        $this->http->SaveResponse();

        $this->saveResponse();

        // Balance - Мои бонусы
        $this->SetBalance($this->http->FindSingleNode("//span[starts-with(text(), 'бонус')]/preceding-sibling::span"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[div[img]]/following-sibling::span")));
        // Карта №
        $this->SetProperty("CardNumber",
            $this->http->FindSingleNode("//b[contains(text(), 'Карта №')]", null, true, "/№\s*([^<\,]+)/") //todo: not found in new design
            ?? $this->http->FindSingleNode("//span[contains(text(), 'Клубная карта')]/following-sibling::span[1]")
        );
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[starts-with(text(), 'Статус') and contains(text(), '-')]", null, true, "/-\s*(.+)/"));

        // Expiration date
        $expNodes = $this->http->XPath->query("//h4[span[contains(text(), 'Бонусы сгорят')]]/following-sibling::div/div");
        $this->logger->debug("Total {$expNodes->length} exp date were found");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $item = $expNodes->item($i);
            $date = $this->dateStringToEnglish($this->http->FindSingleNode("span[1]", $item, true, "/Сгорят\s*([^,]+)/"));

            if (!isset($exp) || strtotime($date) < $exp) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // Expiring balance
                $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("span[2]", $item));
            }// if (isset($exp) || strtotime($date) < $exp)
        }// for ($i = 0; $i < $expNodes->length; $i++)

        if ($expNodes->length == 0 && $profile) {
            $date = $this->dateStringToEnglish($this->http->FindPreg("/Бонусы скоро сгорят\s*[\d\,\.]+\s*Сгорят\s*([^,]+)/", false, $profile->getText()));

            if ($date) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
            }

            $this->SetProperty("ExpiringBalance", $this->http->FindPreg("/Бонусы скоро сгорят\s*([\d\,\.]+)\s*Сгорят\s*[^,]+/", false, $profile->getText()));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//p[contains(text(), 'Если Вы являетесь обладателем карты, активируйте ее.')]")) {
                $this->SetBalance($this->http->FindSingleNode('//a[contains(text(), "Бонусы")]/following-sibling::text()[1] | //div[@class = "auth-user-popup__bonuses"]',
                    null, true, "/([\d,.]+)\s+бонус/"));

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->FindSingleNode("//form[contains(@class, 'pretty_form activate_club_card')]/@method")) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            $this->SetBalance($this->http->FindPreg("/Кабинет для бизнеса\s*(\d+)\s*бонусов\s*1 бонус = 1 рубль, /", false, $profile->getText()));
        }
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey\":\"([^\"]+)/");
        }
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

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function SendLoginForm()
    {
        try {
            if (!$this->http->FindPreg('#citilink\.ru/login/#', false, $this->http->currentUrl())) {
                $this->http->GetURL("https://www.citilink.ru/login/");
            }
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }

        $formXpath = "//form[contains(@class, 'auth-form_js')]";
        $loginInput = $this->waitForElement(WebDriverBy::xpath($formXpath . '//input[@name = "login"]'), 15, false);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath($formXpath . '//input[@name = "pass"]'), 0, false);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            $this->logger->error("something went wrong");

            if ($this->http->FindSingleNode('//title') === '429') {
                $this->logger->error('Error 429 - Слишком частые запросы');

                throw new CheckRetryNeededException();
            }

            return false;
        }

        /*
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = 100000;
        $mover->steps = 50;
        */

        if ($this->waitForElement(WebDriverBy::xpath($formXpath . '//label[contains(., "Слово с картинки")]'), 0)) {
            $captchaInput = $this->waitForElement(WebDriverBy::xpath($formXpath . '//input[@name = "captcha"]'), 0, false);
            $this->saveResponse();

            if (!$captchaInput) {
                return false;
            }
            $captcha = $this->parseCaptcha();

            if ($captcha == null) {
                return false;
            }
            //$captchaInput->sendKeys($captcha);

            /*
            $mover->moveToElement($captchaInput);
            $mover->click();
            $this->saveResponse();
            $mover->sendKeys($captchaInput, $captcha, 10);
            */
            $captchaInput->sendKeys($captcha);
        /*
        $this->driver->executeScript('$(\'input[name = "captcha"]\').val(\''.$captcha.'\')');
        */
        }// if ($this->waitForElement(WebDriverBy::xpath($formXpath.'//label[contains(text(), "Код с картинки")]'), 0))
        else {
            $this->logger->error("captcha not found");
        }

        $this->saveResponse();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        /*
        $mover->moveToElement($loginInput);
        $mover->click();
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
        $mover->moveToElement($passwordInput);
        $mover->click();
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 10);
        */

        $button = $this->waitForElement(WebDriverBy::xpath($formXpath . '//button[@id = "formSubmit" and not(@disabled)]'), 5, false);
        $this->saveResponse();
        $this->driver->executeScript('let menu = document.querySelector(\'.menu_categories\'); if (menu) menu.style.display = "none";');
        $this->saveResponse();

        if (!$button) {
            $this->logger->error("btn not found");

            return false;
        }
        $button->click();

        return true;
    }
}
