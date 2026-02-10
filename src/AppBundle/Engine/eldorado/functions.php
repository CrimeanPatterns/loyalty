<?php
class TAccountCheckerEldorado extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $timeout = 10;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->disableImages();
        $this->useChromium();
        $this->http->setRandomUserAgent(7);
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://www.eldorado.ru/personal/?loyalty");

        if (!($elem = $this->waitForElement(WebDriverBy::xpath('//form[@id = "popupAuthOrderForm"]//input[@name = "USER_LOGIN"]'), $this->timeout))) {
            return false;
        }
        $elem->clear();
        $elem->sendKeys($this->AccountFields['Login']);

        if (!($elem = $this->waitForElement(WebDriverBy::xpath('//form[@id = "popupAuthOrderForm"]//input[@name = "USER_PASSWORD"]'), $this->timeout))) {
            return false;
        }
        $elem->clear();
        $elem->sendKeys($this->AccountFields['Pass']);

        if (!($elem = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "authSubmit") and not(contains(@class, "disabled"))]'), $this->timeout))) {
            return false;
        }
        $elem->click();

//        if (!$this->http->ParseForm("popupAuthOrderForm"))
//            return false;
//        $this->http->SetInputValue("USER_LOGIN", $this->AccountFields['Login']);
//        $this->http->SetInputValue("USER_PASSWORD", $this->AccountFields['Pass']);
//        unset($this->http->Form['USER_REMEMBER']);

        return true;
    }

//    function GetRedirectParams($targetURL = null) {
//        $arg = parent::GetRedirectParams($targetURL);
//        $arg["CookieURL"] = "http://club.eldorado.ru/";
//        $arg["SuccessURL"] = "http://www.eldorado.ru/personal/club/offers/";
//
//        return $arg;
//    }

    public function Login()
    {
        $startTime = time();

        while ((time() - $startTime) < 30) {
            $logout = $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Ваш личный кабинет")]'), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }
            // Неверный логин или пароль
            if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Неверный логин или пароль')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'regErrorMid']"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            // captcha
            /*$iframe = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'g-recaptcha']//iframe"), 0);
            if ($iframe) {

                if (!($elem = $this->waitForElement(WebDriverBy::xpath('//form[@id = "popupAuthOrderForm"]//input[@name = "USER_LOGIN"]'), $this->timeout)))
                    return false;
                $elem->clear();
                $elem->sendKeys($this->AccountFields['Login']);
                if (!($elem = $this->waitForElement(WebDriverBy::xpath('//form[@id = "popupAuthOrderForm"]//input[@name = "USER_PASSWORD"]'), $this->timeout)))
                    return false;
                $elem->clear();
                $elem->sendKeys($this->AccountFields['Pass']);

                $this->driver->switchTo()->frame($iframe);
                $recaptchaAnchor = $this->waitForElement(WebDriverBy::id("recaptcha-anchor"), 20);
                if (!$recaptchaAnchor) {
                    $this->http->Log('Failed to find reCaptcha "I am not a robot" button');
                    throw new CheckRetryNeededException(3, 7);
                }
                $recaptchaAnchor->click();

                $this->http->Log("wait captcha iframe");
                $this->driver->switchTo()->defaultContent();
                $iframe2 = $this->waitForElement(WebDriverBy::xpath("//iframe[@title = 'recaptcha challenge']"), 10, true);
                $this->saveResponse();
                if ($iframe2) {

                    if (!$status) {
                        $this->http->Log('Failed to pass captcha');
                        throw new CheckRetryNeededException(3, 2, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                if (!($elem = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "authSubmit") and not(contains(@class, "disabled"))]'), $this->timeout)))
                    return false;
                $elem->click();

                $startTime = time();
                while ((time() - $startTime) < 30) {
                    $logout = $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Ваш личный кабинет")]'), 0);
                    $this->saveResponse();
                    if ($logout)
                        return true;
                    // Неверный логин или пароль
                    if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Неверный логин или пароль')]"), 0)) {
                        $this->saveResponse();
                        throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                    }
                    if ($message = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'regErrorMid']"), 0))
                        throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);

                    sleep(1);
                }
            }// if ($iframe)
            else
                $this->http->Log('Could not find iFrame with captcha, waiting...');*/

            sleep(1);
        }

//        $this->http->Log("<pre>".var_export($this->http->Form, true)."</pre>", false);
//        $header = ["X-Requested-With" => "XMLHttpRequest",
//                   "Accept" => "application/json, text/javascript, */*; q=0.01"];
//        if (!$this->http->PostForm($header)) {
//            if ($this->http->FindPreg("/message\">/ims") && $this->http->Response['code'] == 500)
//                throw new CheckException("Неверный логин или пароль", ACCOUNT_INVALID_PASSWORD);
//            return false;
//        }
//        $response = $this->http->JsonLog();
//
//        if ($this->http->FindPreg("/\{\"data\":1\}/ims"))
//            $this->http->GetURL("http://www.eldorado.ru/personal/index.php?login=yes&loyalty=");
//
//        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]"))
//            return true;
//        // Неверный логин или пароль
//        if ($this->http->FindPreg("/\"success\":0,\"errors\":\[\],\"message\":\"([^\']+)/ims"))
//            throw new CheckException("Неверный логин или пароль", ACCOUNT_INVALID_PASSWORD);
//        /*
//         * Уважаемый клиент! Ваш PIN-код был ранее изменен на Пароль.
//         * Теперь для  входа на сайт используйте адрес Эл. почты или Номер карты и Пароль.
//         * Если вы забыли свой Пароль, то вы можете изменить его
//         */
//        if (isset($response->message) && strstr($response->message, 'Ваш PIN-код был ранее изменен на Пароль.'))
//            throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);

        return false;
    }

    public function Parse()
    {
        $this->http->PostURL("http://www.eldorado.ru/_ajax/getUserCardBonus.php", ["full" => 1]);
        $response = json_decode($this->http->Response['body']);
        // Balance - всего бонусов
        if (isset($response->result->total)) {
            $this->SetBalance($response->result->total);
        } else {
            $this->http->Log("<pre>" . var_export($response, true) . "</pre>", false);

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && isset($response->STATUS_DATA)) {
                if (strstr($response->STATUS_DATA, 'К сожалению, на текущий момент данные по Программе лояльности недоступны')) {
                    throw new CheckException("Уважаемый клиент! К сожалению, на текущий момент данные по Программе лояльности недоступны.", ACCOUNT_PROVIDER_ERROR);
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && isset($response->STATUS_DATA))
        }
        // Inactive points - Неактивные бонусы
        if (isset($response->result->inactive)) {
            $this->SetProperty("InactivePoints", $response->result->inactive);
        }
        // Reserved points - Резервные бонусы
        if (isset($response->result->reserved) && $response->result->reserved != 0) {
            $this->SetProperty("ReservedPoints", $response->result->reserved);
        }

        $this->http->GetURL("http://www.eldorado.ru/personal/club/operations/");
        $this->saveResponse();
        // Lifetime points
        $this->SetProperty("LifetimePoints", $this->http->FindSingleNode("//tr[td[contains(text(), 'Начислено за весь период:')]]/following-sibling::tr[1]/td[2]/b"));

        // Expiration Date
        $expNodes = $this->http->XPath->query("//tbody[@class = 'added_bonuses']/tr");
        $this->http->Log("Total {$expNodes->length} exp nodes were found");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $date = strtotime($this->http->FindSingleNode("td[5]", $expNodes->item($i)));
            $points = $this->http->FindSingleNode("td[4]", $expNodes->item($i));
            $description = $this->http->FindSingleNode("td[3]", $expNodes->item($i));

            if ((!isset($exp) || $date <= $exp) && $date && $date > time()) {
                if (isset($exp, $expPoints) && $date == $exp) {
                    $expPoints += $points;
                } else {
                    $expPoints = $points;
                }
                $exp = $date;
                // Points to expire
                if (isset($response->result->total) && ($response->result->total - $expPoints) <= 0) {
                    if ($response->result->total < $expPoints) {
                        $expPoints = $response->result->total;
                    }
                    $this->SetProperty("PointsToExpire", $expPoints);
                    $this->SetExpirationDate($exp);

                    break;
                }// if (isset($response->result->total))
            }// if (!isset($exp) || $date <= $exp)
        }// for ($i = 0; $i < $expNodes->length; $i++)

        $this->http->GetURL("http://www.eldorado.ru/personal/club/form/");
        // Name
        $name = CleanXMLValue($this->http->FindSingleNode("//input[@id = 'family']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'fname']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'lname']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Активна с ...
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[@class = 'cardActive']", null, true, '/с\s*([^<]+)/ims'));
        // Карта №
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//div[@class = 'cardNumber']", null, true, '/№\s*([^<]+)/ims'));
    }
}
