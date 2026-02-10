<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmexgc extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $tryOtherRegion = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useFirefox();
        /*
        $this->disableImages();
        */
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields, $values);
        $fields['Login2']['Options'] = [
            ""      => "Select your region",
            'India' => 'India',
            'UK'    => 'United Kingdom',
            'USA'   => 'United States',
        ];
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->AccountFields['Login'] = str_replace(['-', ' '], '', $this->AccountFields['Login']);
        // Invalid card number
        if (!is_numeric($this->AccountFields['Login']) || strlen($this->AccountFields['Login']) < 14) {
            throw new CheckException("Please enter a valid card number and try again.", ACCOUNT_INVALID_PASSWORD);
        }/*review*/
        // Invalid Security Code
        if (strlen($this->AccountFields['Pass']) < 4) {
            throw new CheckException("Gift Card Security Code must be 4 characters. Please make corrections and re-submit.", ACCOUNT_INVALID_PASSWORD);
        }/*review*/
        // hard code
        if ($this->AccountFields['Login'] == '32212342233112') {
            throw new CheckException("Please enter a valid card number and try again.", ACCOUNT_INVALID_PASSWORD);
        }/*review*/

        switch ($this->AccountFields['Login2']) {
            case 'India':
                $this->logger->notice("Region India");
                $clientKey = 'india%20gift%20card';

                break;

            case 'UK':
                $this->logger->notice("Region United Kingdom");
                $clientKey = 'uk';

                break;

            default:
                $this->logger->notice("Region USA");
//                $this->http->setDefaultHeader('Referer', "http://www.americanexpress.com/gift-cards/");
                $this->http->GetURL("http://www.americanexpress.com/gift-cards/");
                $clientKey = 'retail%20sales%20channel';
        }

        $this->http->GetURL("https://prepaidbalance.americanexpress.com/GPTHBIWeb/validateIPAction.do?clientkey={$clientKey}");

        if (in_array($this->AccountFields['Login2'], ["USA", ""])) {
            $this->logger->notice("Region USA");
            $this->AccountFields['Login'] = substr($this->AccountFields['Login'], 0, 4) . '-' . substr($this->AccountFields['Login'], 4, 6) . '-' . substr($this->AccountFields['Login'], 10, 5);
        }
        $this->hideOverlay();

        $form = "//form[@name = 'viewTxnHistoryForm']";
        $loginInput = $this->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'cardDetailsVO.cardNumber'] | //input[@name = 'CardNumber']"), 5);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'chksubmit' or @id = 'btnContinue']"), 0);

        if (!$button) {
            $button = $this->waitForElement(WebDriverBy::xpath("//img[@title = 'See Available Funds and History']"), 0);
        }

        if (!$loginInput || !$button) {
            $this->logger->error("something went wrong");
            // save page to logs
            $this->saveResponse();

            return $this->checkErrors();
        }
        $loginInput->sendKeys($this->AccountFields['Login']);
        // save page to logs
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::id("recaptcha"), 0)) {
            // load jq for UK version
            $this->driver->executeScript("
                var jq = document.createElement('script');
                jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
                document.getElementsByTagName('head')[0].appendChild(jq);
                
            ");
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->driver->executeScript("$('#chksubmit, #btnContinue').removeAttr('disabled'); $('#g-recaptcha-response').val(\"" . $captcha . "\");");
        }
        $this->logger->debug("click button");
        $this->saveResponse();

        if (
            $this->AccountFields['Login2'] == 'UK'
            || $this->AccountFields['Login2'] == 'India'
        ) {
            $this->driver->executeScript("recaptchaCallback(); validate();");
        } else {
            $button->click();
        }
        $this->logger->debug("button was clicked");

        $this->hideOverlay();

        $this->saveResponse();
        $this->checkCredentials();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'cardDetailsVO.cscNumber'] | //input[@name = 'inputSecurityCode']"), 5);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'chksubmit' or @id = 'btnNavigate']"), 0);

        if (!$button) {
            $button = $this->waitForElement(WebDriverBy::xpath("//img[@title = 'See Available Funds and History']"), 0);
        }

        if (!$passwordInput || !$button) {
            $this->logger->error("something went wrong");
            // save page to logs
            $this->saveResponse();
            // retries
            if ($this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'A valid Card number must be entered. Please try again.')]"), 0)) {
                throw new CheckRetryNeededException(3, 1);
            }
            // Invalid Captcha
            if ($this->waitForElement(WebDriverBy::xpath("//font[contains(text(), 'Invalid Captcha')]"), 0)) {
                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }
            $this->logger->notice(">>>> '{$this->AccountFields['Login']}'");
            // hard code
            if (in_array($this->AccountFields['Login'], [
                '379016322897736',
                '3720-020079-48884',
            ])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        // The Card security code entered is not valid. Please try again. (AccountID: 4091244)
        if ($this->AccountFields['Login2'] == 'USA' && !is_numeric($this->AccountFields['Pass'])) {
            throw new CheckException("The Card security code entered is not valid. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        // save page to logs
        $this->saveResponse();
        /*
        if ($this->waitForElement(WebDriverBy::id("recaptcha"), 0)) {
            // load jq for UK version
            $this->driver->executeScript("
                var jq = document.createElement('script');
                jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
                document.getElementsByTagName('head')[0].appendChild(jq);
            ");
            $captcha = $this->parseCaptcha();
            if ($captcha === false) {
                return false;
            }
            $this->driver->executeScript("$('#g-recaptcha-response').val(\"".$captcha."\");");
        }
        */

        $button->click();

        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[contains(text(), 'Available Balance')]"), 7);
        // save page to logs
        $this->saveResponse();

        /*$this->http->SetInputValue("cardDetailsVO.cardNumber", $this->AccountFields['Login']);
        $this->http->SetInputValue("cardDetailsVO.email", '');
        $this->http->SetInputValue("timeStamp", date("Y-m-j H:i").preg_replace("/^:0/", ':', date(":s")));
        $this->http->SetInputValue("timeZoneOffset", '6');
        $this->http->PostForm();

        $this->checkCredentials();

        if (!$this->http->ParseForm("viewTxnHistoryForm"))
            return false;
        sleep(1);
        $this->http->FormURL = "https://www279.americanexpress.com/GPTHBIWeb/CSCValidations.do?clientkey={$clientKey}";
        unset($this->http->Form["cardDetailsVO.cardNumber"]);
        $this->http->SetInputValue("cardDetailsVO.email", '');
        $this->http->SetInputValue("buttonPressed", '1');
        $this->http->SetInputValue("timeStamp", date("Y-m-j H:i").preg_replace("/^:0/", ':', date(":s")));
        $this->http->SetInputValue("cardDetailsVO.cscNumber", $this->AccountFields['Pass']);*/

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Internal Server Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkCredentials()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("(//font[contains(normalize-space(), 'Invalid or missing information. Please try again.')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You can enter 15 characters.Re-submit after corrections.
        if ($message = $this->http->FindSingleNode("(//font[contains(text(), 'You can enter 15 characters.Re-submit after corrections.')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The Card security code entered is not valid
        if ($message = $this->http->FindSingleNode("(//font[contains(text(), 'The Card security code entered is not valid.')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The prepaid card associated with the number you entered will need to be replaced.
        if ($message = $this->http->FindSingleNode("(//font[contains(text(), 'The prepaid card associated with the number you entered will need to be replaced.')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We're sorry. We are unable to check the balance of your Card. Please check your balance at amexgiftcard.com/balance.
        if ($message = $this->http->FindSingleNode('(//font[contains(text(), "We\'re sorry. We are unable to check the balance of your Card. Please check your balance at")])[1]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A valid security code must be entered. Please try again.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'A valid security code must be entered. Please try again.')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//font[contains(text(), 'To check your balance for India Gift Card, please click')]") && !$this->tryOtherRegion) {
            $this->tryOtherRegion = true;
            $this->logger->notice("Region India");
            $this->AccountFields['Login2'] = 'India';

            if ($this->LoadLoginForm()) {
                if ($this->Login()) {
                    $this->Parse();
                }
            }
        }

        if ($this->AccountFields['Login2'] = 'UK') {
            // We're sorry, but our system was unable to access the page you were trying to load.
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, but our system was unable to access the page you were trying to load.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return true;
    }

    public function Login()
    {
//        if (!$this->http->PostForm())
//            return false;

        $this->checkCredentials();

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1] | //div[contains(text(), 'Available Balance')]")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Available Balance
        if ($this->SetBalance($this->http->FindSingleNode("//td[contains(., 'Available Balance:')]/following-sibling::td[1]/div/font"))) {
            // Currency
            $this->SetProperty("Currency", $this->http->FindSingleNode("//td[contains(., 'Available Balance:')]/following-sibling::td[1]/div/font", null, true, "/[^\d]+/ims"));
            // Status
            $this->SetProperty("Status", $this->http->FindSingleNode("//td[contains(., 'Status:')]/following-sibling::td[1]/div/font"));
            // Card Number
            $this->SetProperty("CardNumber", $this->http->FindSingleNode("//td[contains(., 'Card Number:')]/following-sibling::td[1]/div/font"));
        } else {// other design
            // Balance - Available Balance
            $this->SetBalance($this->http->FindSingleNode("
                //dt[contains(., 'Available Balance:')]/following-sibling::dd[1]
                | //div[contains(text(), 'Available Balance')]/following-sibling::div[1]
            "));
            // Currency
            $this->SetProperty("Currency", $this->http->FindSingleNode("
                //dt[contains(., 'Available Balance:')]/following-sibling::dd[1]
                | //div[contains(text(), 'Available Balance')]/following-sibling::div[1]
            ", null, true, "/[^\d]+/ims"));
            // Status
            $this->SetProperty("Status", $this->http->FindSingleNode("
                //dt[contains(., 'Status:')]/following-sibling::dd[1]
                | //div[contains(text(), 'Status')]/following-sibling::div[1]
            "));
            // Card Number
            $this->SetProperty("CardNumber", $this->http->FindSingleNode("
                //dt[contains(., 'Card Number:')]/following-sibling::dd[1]
                | //div[contains(text(), 'Card Number')]/following-sibling::div[1]
            "));
        }

        // selenium fix
        if (isset($this->Properties)) {
            foreach ($this->Properties as &$property) {
                $property = str_replace('Â', '', $property);
            }
            $this->Balance = str_replace('Â', '', $this->Balance);
            $this->logger->debug(var_export($this->Properties, true), ['pre' => true]);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'viewTxnHistoryForm']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function hideOverlay()
    {
        $this->logger->notice(__METHOD__);

        if ($this->waitForElement(WebDriverBy::id("consentContainer"), 5)) {
            $this->driver->executeScript("document.getElementById('consentContainer').style.display = 'none';");
        }
    }
}
