<?php

// refs #1997
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerExtrabux extends TAccountChecker
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
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.extrabux.com/users/profile';

        return $arg;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.extrabux.com/set-lang/?lang=en&return=%2Fusers%2Fprofile', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //$this->http->setCookie("sync_cookie", "1", "www.extrabux.com", "/");
        //$this->http->GetURL("https://www.extrabux.com/");
        $this->http->GetURL("https://www.extrabux.com/users/login?return=%2Fusers%2Fprofile");

        $csrf = $this->http->FindSingleNode('//meta[@name="csrf-token"]/@content');

        if (!$this->http->ParseForm(null, '//form[@form-type = "email"]') || !$csrf) {
//            if ($this->http->Response['code'] == 200)
//                $this->checkLogin();
            return $this->checkErrors();
        }
        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = 'https://www.extrabux.com/users/login/do';
        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkLogin()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
        $this->http->PostURL('http://www.extrabux.com/users/check-signin-email-address',
            [
                'email'        => $this->AccountFields['Login'],
                'emailAddress' => $this->AccountFields['Login'],
            ]
        );

        if ($this->http->Response['body'] == 'false') {
            throw new CheckException($this->AccountFields['Login'] . " does not yet have an Extrabux account.", ACCOUNT_INVALID_PASSWORD);
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'Service Temporarily Unavailable')]
                | //h1[contains(text(), '502 Bad Gateway')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);
        $csrf = $this->http->FindSingleNode('//meta[@name="csrf-token"]/@content');
        $headers = [
            "X-CSRFToken" => $csrf,
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers)) {
            if (
                $this->http->Response['code'] == 429
                && $this->http->FindPreg("/^error code: 422, please try it again later$/")
            ) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);
        $message = ArrayVal($response, 'message');
        $this->logger->error($message);
        // fixed chinese version
        if (strstr($this->http->currentUrl(), 'http://www.ebates.kr/?cslf=extrabux&e=')
            || strstr($this->http->currentUrl(), 'https://www.ebates.cn/users/profile')
            || strstr($this->http->currentUrl(), 'https://www.ebates.cn/users/login?return=%2Fusers%2Fprofile')
            // new response
            || $this->http->Response['body'] == '{"status":true,"message":""}'
            || $this->http->Response['body'] == '{"status":true,"return":"","message":""}'
            || $this->http->Response['body'] == '{"status":true,"return":"","message":"","messageid":0}'
        ) {
            $this->http->GetURL('https://www.extrabux.com/set-lang/?lang=en&return=%2Fusers%2Faccounting');
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        // invalid credentials
        if (
            // Oops! Email address or password is incorrect. (Remember that your password is case sensitive.)
            strstr($message, 'Oops! Email address or password is incorrect.')
            // Oops! Your account or password is incorrect. (Remember that your password is case sensitive.)
            || strstr($message, 'Oops! Your account or password is incorrect.')
            // Your account has been deactived, please contact us.
            || strstr($message, 'Your account has been deactived')
            || strstr($message, 'Please enter correct Extrabux account')
            || strstr($message, 'Password must be between 6 and 32 characters long.')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message == 'Captcha validation is required.') {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        if ($message == 'Error retrieving credentials from the instance profile metadata server. (cURL error 28: Connection timed out after 1001 milliseconds (see http://curl.haxx.se/libcurl/c/libcurl-errors.html))') {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 1);
        }

//        $this->checkLogin();

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!strstr($this->http->currentUrl(), '://www.extrabux.com/users/accounting')
            || !$this->http->FindSingleNode("//h2[contains(text(), 'Balance Details')]")) {
            $this->http->GetURL('https://www.extrabux.com/set-lang/?lang=en&return=%2Fusers%2Faccounting');
        }

        // Available
        $available =
            $this->http->FindSingleNode('//div[strong[starts-with(text(), "Available:")]]/following-sibling::p/span[span[contains(text(), "USD")]]', null, true, self::BALANCE_REGEXP_EXTENDED)
            // AccountID: 4831416
            ?? $this->http->FindSingleNode('//div[strong[starts-with(text(), "Available:")]]/following-sibling::p/span[span[contains(text(), "GBP")]]', null, true, self::BALANCE_REGEXP_EXTENDED)
            // AccountID: 3321550
            ?? $this->http->FindSingleNode('//div[strong[starts-with(text(), "Available:")]]/following-sibling::p/span[span[contains(text(), "AUD")]]', null, true, self::BALANCE_REGEXP_EXTENDED)
            // AccountID: 6137941
            ?? $this->http->FindSingleNode('//div[strong[starts-with(text(), "Available:")]]/following-sibling::p/span[span[contains(text(), "CAD")]]', null, true, self::BALANCE_REGEXP_EXTENDED)
            ?? $this->http->FindSingleNode('//div[strong[starts-with(text(), "Available:")]]/following-sibling::p/span[span[contains(text(), "RUB")]]', null, true, self::BALANCE_REGEXP_EXTENDED)
        ;

        if ($available !== null) {
            $available = PriceHelper::cost($available);
            $this->AddSubAccount([
                "Code"              => "extrabuxAvailable",
                "DisplayName"       => "Available",
                "Balance"           => $available,
                "BalanceInTotalSum" => true,
            ]);
        }
        // Pending
        $pending = $this->http->FindSingleNode('//div[strong[contains(text(), "Pending:")]]/following-sibling::p/span[span[contains(text(), "USD")]]', null, true, self::BALANCE_REGEXP_EXTENDED);

        // AccountID: 1093643
        if ($pending === null && !$this->http->FindSingleNode('//div[strong[contains(text(), "Pending:")]]/following-sibling::p/span[span[contains(text(), "USD")]]') && $this->http->FindSingleNode('//div[strong[contains(text(), "Pending:")]]/following-sibling::p/span[span[contains(text(), "GBP") or contains(text(), "HKD")]]')) {
            $pending = 0;
        }

        if ($pending !== null) {
            $pending = PriceHelper::cost($pending);
            $this->AddSubAccount([
                "Code"              => "extrabuxPending",
                "DisplayName"       => "Pending",
                "Balance"           => $pending,
                "BalanceInTotalSum" => true,
            ]);
        }
        // Balance - Available + Pending
        if ($pending !== null && $available !== null) {
            $this->SetBalance($pending + $available);
        }

        // Paid
        $this->SetProperty("PaidEarnings", $this->http->FindSingleNode('//div[strong[contains(text(), "Paid:")]]/following-sibling::p/span[span[contains(text(), "USD")]]'));
        // Name
        $this->http->GetURL("https://www.extrabux.com/users/profile");
        $name = $this->http->FindSingleNode('//label[contains(text(), "Name (Native):")]/following-sibling::span[1]');
        $this->logger->debug("[Name]: {$name}");

        if ($name != 'Update') {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindPreg("/grecaptcha\.execute\('([^\']+)', \{action: 'website'\}\)\./");
        $key = $this->http->FindPreg("/grecaptcha\.render\(el,\s*\{\s*'sitekey'\s*:\s*'([^\']+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            //            "version"   => "v3",
            //            "action"    => "website",
            //            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes('//a[contains(@href, "logout")]/@href')
            && !strstr($this->http->currentUrl(), 'users/login')
        ) {
            return true;
        }

        return false;
    }
}
