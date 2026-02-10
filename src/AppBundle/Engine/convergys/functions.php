<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerConvergys extends TAccountChecker
{
    use ProxyList;

    protected $oldParser = false;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->TimeLimit = 500;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // refs #13484
        if ($this->AccountFields['Login'] == 'psz211') {
            $this->oldParser = true;
        }

        if ($this->oldParser) {
            $this->logger->notice("Old parser");
            $this->http->GetURL("https://convergys.corporateperks.com/login");

            if (!$this->http->ParseForm("login")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue("login", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);

            if (!$this->http->PostForm()) {
                return false;
            }
        } else {
            $this->logger->notice("New parser");
            $this->http->GetURL("https://www.perksatwork.com/login");
            $browser = clone $this->http;
            $browser->GetURL("https://www.perksatwork.com/csrf");
            $nxjcsrft = $browser->JsonLog()[0] ?? null;

            if (!$this->http->ParseForm("login-form") || !$nxjcsrft) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue("login", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("nxjcsrft", $nxjcsrft);
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Something has gone wrong, we\'re fixing it now.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        if ($this->oldParser) {
            $this->logger->notice("Old parser");
            // The username or password you entered is incorrect.
            if ($message = $this->http->FindPreg("/(The username or password you entered is incorrect\.)/ims")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        } else {
            if (!$this->parseQuestion()) {
                return false;
            }

            // Oops, we didn't recognize your email & password. Please try again
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Oops, we didn\'t recognize your email & password.")]', null, true, "/(Oops, we didn\'t recognize your email \& password\. Please try again\.)/ims")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // To ensure continued protection of your account, your password has been reset. We believe your credentials for Perks At Work may have been used on other compromised websites. Please click here to Reset your Password
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "To ensure continued protection of your account, your password has been reset.")]', null, false, "/^(.+?)\s*Please click here to Reset your Password/ims")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            /**
             * Welcome to Perks At Work BETA
             * We're excited to introduce you to our new platform.
             */
            if ($this->http->FindSingleNode('//div[contains(text(), "We\'re excited to introduce you to our new platform.")]')) {
                $this->throwProfileUpdateMessageException();
            }

            // error on only one account // AccountID: 4060880
            // This website is no longer available.
            if ($this->AccountFields['Login'] == 'chris.anderson@intel.com'
                && ($message = $this->http->FindSingleNode("//h3[contains(text(), 'This website is no longer available.')]"))) {
                throw new CheckException("Oops, we didn't recognize your email & password. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//p[contains(text(), 'It looks like you might be logging in from a new device. Please check your email')]");

        if (!isset($question)) {
            return true;
        }

        if (!$this->http->ParseForm("mfa-form")) {
            return false;
        }
        $nxjcsrft = $this->getCsrf();
        $this->http->SetInputValue("nxjcsrft", $nxjcsrft);

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetInputValue("input_code", $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }

        if (
            $this->http->Response['code'] == 403
            && ($message = $this->http->FindPreg("/Sorry it looks like your session may have expired/"))
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->http->FindSingleNode('//p[contains(text(), "That code doesn\'t look right. Please try again.")]')) {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        return !$this->checkErrors();
    }

    public function Parse()
    {
        if ($this->oldParser) {
            $this->logger->notice("Old parser");
            // My Statement
            if ($link = $this->http->FindSingleNode("//a[@id = 'ptsTickerLinkStatement']/@href")) {
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);
            }// if ($link = $this->http->FindSingleNode("//a[@id = 'ptsTickerLinkStatement']/@href"))
        }// if ($this->oldParser)
        else {
            $this->http->GetURL("https://www.perksatwork.com/wowpoints");
        }
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Balance')]/following-sibling::td[1]"));
        // Status
        $this->SetProperty("Status", $this->http->FindPreg("/YOUR STATUS:\s*([^<]+)/ims"));
        // Available
        $this->SetProperty("Available", $this->http->FindSingleNode("//td[contains(text(), 'Available')]/following-sibling::td[1]"));
        // Pending
        $this->SetProperty("Pending", $this->http->FindSingleNode("//td[contains(text(), 'Pending')]/following-sibling::td[1]"));
        // Allocated
        $this->SetProperty("Allocated", $this->http->FindSingleNode("//span[contains(@class, 'dynamicPointsAllocated')]"));

        // AccountID: 3748559
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Balance
            $this->SetBalance($this->http->FindSingleNode("//div[strong[contains(text(), 'Your WOWPoints')]]/following-sibling::div[1]/strong"));
            // Status
            $this->SetProperty("Status", $this->http->FindSingleNode("//div[contains(text(), 'Status')]/following-sibling::div[1]"));
            // Available
            $this->SetProperty("Available", $this->http->FindSingleNode("//div[contains(text(), 'Available')]/following-sibling::div[1]", null, true, self::BALANCE_REGEXP_EXTENDED));
            // Pending
            $this->SetProperty("Pending", $this->http->FindSingleNode("//div[contains(text(), 'Pending')]/following-sibling::div[1]", null, true, self::BALANCE_REGEXP_EXTENDED));
            // Allocated
            $this->SetProperty("Allocated", $this->http->FindSingleNode("//div[contains(text(), 'Allocated')]/following-sibling::div[1]", null, true, self::BALANCE_REGEXP_EXTENDED));
        }

        // Personal Settings
        if ($this->oldParser) {
            $this->logger->notice("Old parser");
            $link = $this->http->FindSingleNode("//a[contains(., 'Personal Settings')]/@href");
        } else {
            $link = $this->http->FindSingleNode("//a[contains(., 'Account Settings')]/@href");
        }

        if ($link) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }
        // Name
        $name = Html::cleanXMLValue(
            $this->http->FindSingleNode("//select[@id = 'mrMs' or @id = 'mrMsSelect']/option[@selected]/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'userFName' or @id = 'firstNameInput']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'userLName' or @id = 'lastNameInput']/@value")
        );
        $this->SetProperty("Name", beautifulName($name));

        if ($this->oldParser) {
            $this->logger->notice("Old parser");
            $this->http->GetURL("https://convergys.corporateperks.com/staradvantage/index/uSource/myAcct");
        } else {
            $this->http->GetURL("https://www.perksatwork.com/staradvantage");
        }
        // Qualifying WOWPoints from [this_year]
        $this->SetProperty("QualifyingWOWPoints", $this->http->FindPreg("/Qualifying WOWPoints from \d+:\s*<span[^>]+>([^<]+)/"));
        // Qualifying WOWPoints until 5 STAR Status
        $this->SetProperty("UntilFiveStarStatus", $this->http->FindPreg("/WOWPoints until 5 STAR Status:\s*<span[^>]+>([^<]+)/"));

        // Lifetime Earned
        if ($this->oldParser) {
            $this->http->GetURL("http://convergys.corporateperks.com/pointsleaderboard/index/uSource/H11");
            $this->SetProperty("LifetimeEarned", $this->http->FindSingleNode("//div[@class = 'lifetimeEarnContainer']/text()[last()]"));
        } else {
            $nxjcsrft = $this->http->FindPreg("/name=\"nxjcsrft\" value=\"([^\"]+)/");
            $this->http->PostURL("https://www.perksatwork.com/wowpointswidget/summarybox", [
                "csrfToken" => $nxjcsrft,
                "nxjcsrft"  => $nxjcsrft,
                "timespan"  => "3",
            ]);
            $this->SetProperty("LifetimeEarned", $this->http->FindSingleNode("//td[contains(text(), 'WOWPoints earned in total')]/preceding-sibling::td/strong"));
        }
    }

    protected function getCsrf()
    {
        $this->logger->notice(__METHOD__);

        return $this->http->FindPreg("/html_csrf_tokens = \[\"([^\"]+)/");
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'login-form']//input[@name = 'btnSubmit']/@data-sitekey");
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
