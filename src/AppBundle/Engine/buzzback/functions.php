<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerBuzzback extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = "https://panelistapi.cint.com/Profiling/BasicProfiling";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $frameURL = null;
    private $headers = [
        "Accept" => "application/json, text/plain, */*",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("http://panel.buzzback.com/");

        if ($frame = $this->http->FindSingleNode("//frame[contains(@src, 'Buzzback_English-US.HTML')]/@src")) {
            $this->http->NormalizeURL($frame);
            $this->http->GetURL($frame);
        }

        if ($frame = $this->http->FindSingleNode("//iframe/@src")) {
            $this->http->NormalizeURL($frame);
            $this->http->GetURL($frame);
            $this->frameURL = $this->http->currentUrl();
            // Pardon Our Appearance BuzzBack Is Getting A Makeover!
            if ($message = $this->http->FindPreg('/As of Friday April 29th 2016,\s*we\’ll be working on a completely <strong>NEW<\/strong> BuzzBack experience! So stay tuned – soon you\’ll have more ways to participate, be heard and earn great rewards\./ims')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($frame = $this->http->FindSingleNode("//frame/@src"))

        $panelGuid = $this->http->FindPreg("#https://panelist(?:-v2|).cint.com/([^?\/=]+)#", false, $this->http->currentUrl());

        if (!$this->http->FindSingleNode("//div[@id = 'splash-text']") && !$panelGuid) {
            return $this->checkErrors();
        }
        /*
        if (!$this->http->ParseForm(null, "//form[contains(@action, 'sessions?i=1')]"))
            return $this->checkErrors();
        $this->http->SetInputValue("email_address", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $captcha = $this->parseCaptcha();
        if ($captcha === false)
            return false;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->FilterHTML = true;
        */
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $data = [
            "anti_bot_token" => "{\"type\":\"ReCaptcha\",\"content\":\"{$captcha}\",\"panelGuid\":\"{$panelGuid}\"}",
            "client_id"      => "PPR_Web",
            "grant_type"     => "password",
            "panelGuid"      => $panelGuid,
            "password"       => $this->AccountFields['Pass'],
            "username"       => $this->AccountFields['Login'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://panelistapi.cint.com/token", $data);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Warning: Site Maintenance
        if ($this->http->FindPreg("/site_maintenance/ims", false, $this->http->currentUrl())) {
            throw new CheckException("BuzzBack is under maintenance. Please try to check again later.", ACCOUNT_PROVIDER_ERROR);
        }

        /*
        // 404 Not Found
        if ($message = $this->http->FindSingleNode("//title[contains(text(), '404 Not Found')]"))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        */

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->access_token, $response->email)) {
            $this->State['Authorization'] = "Bearer {$response->access_token}";

            return $this->loginSuccessful();
        }
        $message = $response->error_description ?? null;
        // The email or password is incorrect
        if ($message == "The user name or password is incorrect") {
            throw new CheckException("The email or password is incorrect", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0, true);
        // Full Name
        $name = Html::cleanXMLValue(ArrayVal($response, 'firstName') . ' ' . ArrayVal($response, 'lastName'));
        $this->SetProperty("Name", beautifulName($name));

        $this->http->GetURL("https://panelistapi.cint.com/Reward/Balance", $this->headers);
        // Balance - YOUR BALANCE
        $this->SetBalance($this->http->FindPreg("/\"(.+) USD\"/"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://panelist.cint.com/config.js");
        $key = $this->http->FindPreg("/RECAPTCHA_KEY:\s*\'([^\']+)/");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->frameURL, //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Authorization" => $this->State['Authorization'],
            "X-Username"    => $this->AccountFields['Login'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3);

        if (
            isset($response->emailAddress)
            && strtolower($response->emailAddress) == strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }
}
