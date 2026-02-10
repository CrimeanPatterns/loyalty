<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

require_once __DIR__ . '/../california/functions.php';

class TAccountCheckerCanes extends TAccountCheckerCalifornia
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $loginURL = "https://raisingcanes.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://raisingcanes.myguestaccount.com/guest/account-balance";
    public $code = "canes";
}

class TAccountCheckerCanesOld extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /**
     * like as huhot, canes, whichwich, boloco.
     */
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /* if "id" in url changes use this:
        $this->http->GetURL("http://www.raisingcanes.com/cards/index.html");
        $this->http->GetURL($this->http->FindSingleNode("//a[contains(@href, 'accountbalance.srv')]/@href"));
        */
        $this->http->GetURL("https://raisingcanes.myguestaccount.com/guest/nologin/account-balance");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'account-balance')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue($this->http->FindSingleNode("//input[@id = 'printedCard']/@name"), $this->AccountFields['Login']);
        $this->http->SetInputValue($this->http->FindSingleNode("//form[contains(@action, 'account-balance')]//button/@name"), '');

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        return true;
    }

    public function checkErrors()
    {
        /*
         * February 16, 2017
         *
         * The page you are trying to reach has been temporarily disabled due to security concerns.
         *
         * We are working to restore service. It may be days until service is restored for retrieving your balance through this page.
         *
         * Maintaining the security of your account is our highest priority.
         */
        if ($message = $this->http->FindPreg("/We are working to restore service. It may be days until service is restored for retrieving your balance through this page\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/We are working to restore service and expect it to be available later this month. The balance on your card may be obtained at any participating store\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There was a problem validating the CAPTCHA. Please try again
        if ($this->http->FindSingleNode("//span[contains(text(), 'There was a problem validating the CAPTCHA. Please try again')]")) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $access = $this->http->FindSingleNode("//div[contains(@class, 'pointsBalance')]");

        if (isset($access)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@style, 'color: red; font-size: 0.8em')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Registration Code
        if ($this->parseQuestion()) {
            return false;
        }
        // Invalid card number.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid card number.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Invalid CAPTCHA. Please try again.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid CAPTCHA. Please try again.')]")) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//label[contains(text(), 'Reg Code') or contains(text(), 'Registration Code')]");

        if (!isset($question)) {
            return false;
        }

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'account-balance')]")) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("canes. Registration Code was entered");
        $this->http->SetInputValue("reg_code", $this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }
        // Invalid Card and/or Registration Code
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'Invalid Card and/or Registration Code')]")) {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        return true;
    }

    public function Parse()
    {
        //# Card exchanged
        if ($message = $this->http->FindPreg("/card exchange/ims")) {
            throw new CheckException("Card exchanged", ACCOUNT_PROVIDER_ERROR);
        }

        // Box Combo
        $this->SetProperty("BoxCombo", $this->http->FindSingleNode("//div[contains(text(), 'Box Combo')]", null, true, "/\d+/"));
        // Balance - Visits
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Visits')]", null, true, self::BALANCE_REGEXP));

        // Expiration Date
        $expirationDates = $this->http->FindNodes("//div[contains(text(), 'expire')]");

        if (!empty($expirationDates)) {
            foreach ($expirationDates as $expirationDate) {
                preg_match("/(\d+)\s*expire\s*([^<]+)/ims", $expirationDate, $matches);
                $this->logger->debug(var_export($matches, true), ['pre' => true]);

                if (isset($matches[1], $matches[2]) && (!isset($exp) || $exp > strtotime($matches[2]))) {
                    // Rewards to expire
                    $this->SetProperty('PointsToExpire', $matches[1]);

                    if ($exp = strtotime($matches[2])) {
                        $this->SetExpirationDate($exp);
                    }
                }// if (isset($matches[1], $matches[2]))
            }// foreach ($expirationDates as $expirationDate)
        }// if (!empty($expirationDates))
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://raisingcanes.myguestaccount.com/login/accountbalance.srv?id=%2bPdKoOR9KOQ%3d';

        return $arg;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode("//form[contains(@action, '/guest/nologin/account-balance')]//div[@class = 'g-recaptcha']/@data-sitekey");
        $key = $this->http->FindPreg("/'sitekey':\s*'(\w+)'/");
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
}
