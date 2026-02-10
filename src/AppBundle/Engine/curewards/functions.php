<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCurewards extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        /*
        $this->http->SetProxy($this->proxyReCaptchaIt7());
        */
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.curewards.com/myaccount/points", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.curewards.com/Login", [], 30);
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('formLogin')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('LoginUserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('LoginPassword', $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Your rewards site is undergoing maintenance during the weekend of October 19th.  During this time you may experience intermittent access issues. We thank you for your patience.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your rewards site is undergoing maintenance during the weekend of ")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We'll be back soon
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "We\'ll be back soon")]/following-sibling::h3[contains(text(), "Thank you for your patience, while we update our site")]')) {
            throw new CheckException("We'll be back soon." . $message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindPreg("/(website is currently being updated)/ims")) {
            throw new CheckException("The CU<i>Rewards</i> website is currently being updated. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, an error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(Sorry, an error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application.
        if (
            $this->http->FindPreg('/<H1>Server Error in \'\/\' Application\./')
            || $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code
        if ($this->http->currentUrl() == 'https://www.curewards.com/mazuma/Innerge/Error'
            && in_array($this->AccountFields['Login'], ['travis_skc7', 'travis_skc22'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(1);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[@class = 'validation-summary-errors']")) {
            $this->logger->error($message);

            if (stristr($message, 'The website has encountered a general error')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (stristr($message, 'The user name or password is incorrect.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (stristr($message, 'Cardholder doesn’t participate')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (stristr($message, 'The service is unavailable')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (stristr($message, 'Please check the I am not a Robot box')) {
                throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
            }
        }// if ($message = $this->http->FindSingleNode("//div[@class = 'validation-summary-errors']"))
        //# Necessary to Verify Account Info
        if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Verify Account Info')]")) {
            throw new CheckException("Please verify your account info", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# Your password has expired and must be reset
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your password has expired and must be reset')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != "https://www.curewards.com/myaccount/points") {
            $this->http->GetURL("https://www.curewards.com/myaccount/points");
        }
        // Total Points Earned YTD
        $this->SetProperty("TotalPointsEarnedYTD", $this->http->FindSingleNode("//span[@id = 'lblPtsSummaryTotalMall']"));
        // Account #
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[@class = 'account-num']", null, true, "/Account\s*([\d]+)/ims"));
        // Balance - Redeemable Points
        if (!$this->SetBalance($this->http->FindSingleNode("//span[@id = 'lblRedeemPtSummary']"))) {
            $this->SetBalance($this->http->FindSingleNode("//span[@id = 'lblUserPoints']/b"));
        }
        // Expiring Points
        $expNodes = $this->http->XPath->query("//h4[contains(., 'Expiring Points')]/following-sibling::div[label[not(@id)]]");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        foreach ($expNodes as $expNode) {
            $date = $this->http->FindSingleNode("label[1]", $expNode);
            $points = $this->http->FindSingleNode("label[2]", $expNode);

            if (!isset($exp) || strtotime($date) > $exp) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $points);
            }// if (!isset($exp) || strtotime($date) > $exp)
        }// foreach ($expNodes as $expNode)

        // Name
        $this->http->GetURL("https://www.curewards.com/myaccount");
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//label[contains(text(), 'First Name')]/following-sibling::label[1]")
            . ' ' . $this->http->FindSingleNode("//label[contains(text(), 'Last Name')]/following-sibling::label[1]"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'formLogin']//div[@class = 'g-recaptcha']/@data-sitekey");
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

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'logoff')]/@href")) {
            return true;
        }

        return false;
    }
}
