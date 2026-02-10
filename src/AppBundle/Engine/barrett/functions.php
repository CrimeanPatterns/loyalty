<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBarrett extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        ''            => 'Select your region',
        'UK'          => 'UK',
        'Ireland'     => 'Ireland',
        'Netherlands' => 'Netherlands',
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $host = 'https://www.hollandandbarrett.com';

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'barrettCouponNetherlands')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'barrettCoupon')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
        /*
        $this->http->SetProxy($this->proxyReCaptcha());
        */

        if ($this->AccountFields['Login2'] == 'Ireland') {
            $this->host = 'https://www.hollandandbarrett.ie';
        } elseif ($this->AccountFields['Login2'] == 'Netherlands') {
            $this->host = 'https://www.hollandandbarrett.nl';
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("{$this->host}/my-account/my-account.jsp", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL($this->host . '/my-account/login.jsp');

        if (!$this->http->ParseForm(null, "//form[contains(@action,'/my-account/login.jsp')]")) {
            if ($this->http->ParseForm(null, '//form[input[@name = "state"]]')) {
                $this->http->SetInputValue('username', $this->AccountFields['Login']);
                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
                $this->http->SetInputValue('action', "default");

                $captcha = $this->parseCaptcha($this->http->FindSingleNode('//img[@alt="captcha" and contains(@src, "data:image")]/@src'));

                if ($captcha !== false) {
                    $this->http->SetInputValue('captcha', $captcha);
                }

                return true;
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue('email', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('/atg/userprofiling/ProfileFormHandler.login', 'Submit');
        $this->http->SetInputValue('login_rememberme', 'true');

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = $this->host . '/my-account/my-account.jsp';

        return $arg;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if (in_array($this->http->currentUrl(), [$this->host, $this->host . "/"])) {
            $url = "{$this->host}/my-account/my-account.jsp";

            if ($this->AccountFields['Login2'] == 'Ireland') {
                $url = "{$this->host}/my-account/overview";
            }

            $this->http->GetURL($url, [], 20);
        }// if ($this->http->currentUrl() == $this->host)

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@id = "error-element-password"]')) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'You need to reset your password, ')
                || $message == 'Invalid email address'
                || strstr($message, 'We have made some changes to our systems and you need to reset your password')
            ) {
                throw new CheckException("You need to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Wrong email or password'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "prompt-alert"]/p')) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (strstr($message, 'Something went wrong, please try again later')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode("
                //*[contains(text(), 'Please enter a valid email or password to sign-in.')]
                | //*[contains(text(),'enter valid email address and password')]
                | //ul[contains(text(), 'Vul een geldig e-mailadres en wachtwoord in om in te loggen')]
            ")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account is temporarily locked
        if ($message = $this->http->FindSingleNode("
                //*[contains(text(), 'Your account is temporarily locked')]
                | //ul[contains(text(), 'Your account is currently locked.')]
            ")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(strip_tags($message), ACCOUNT_LOCKOUT);
        }

        // ReCAPTHCA validation failed. Please try to submit the form again
        if ($this->http->FindSingleNode("//ul[contains(text(), 'ReCAPTHCA validation failed. Please try to submit the form again')]")
            || $this->http->FindSingleNode('//span[@id = "error-element-captcha"]')
        ) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode("//*[contains(@class, 's-account-home')]/.//*[contains(text(), 'Name:') or contains(text(), 'Naam:')]/following-sibling::*[1]")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "Good ")]', null, true, "/Good\s*\w+\s*(\w+\s\w+)/")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "Hello ")]/text()[1]', null, true, "/Hello\s*(.+)/")
            ?? $this->http->FindSingleNode('//h2[contains(text(), "Hoi ")]/text()[1]', null, true, "/Hoi\s*(.+)/")
        ;
        $this->SetProperty('Name', beautifulName(trim($name)));
        // Balance - You've collected ** points
        if ($text = $this->http->FindSingleNode("//*[contains(@class, 'rfl-voucher-list')]/.//*[(contains(., 've collected') or contains(., 'afgelopen kwartaal')) and contains(@class, 'table-title')]/text()[1]")) {
            $this->SetBalance($this->http->FindPreg("/(?:collected|kwartaal) ([\-0-9]+) (?:points|punten)/", false, $text));
            // Your points are worth
            $this->SetProperty('BalanceWorth', $this->http->FindPreg("/(?:worth| van)\s*(.+)\./", false, $text));
        }

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode('//p[contains(text(), "We’ve noticed you aren’t yet a member of rewards for life but if you think we’ve made a mistake")] | //p[contains(text(), "It looks like your rewards account isn\'t yet activated.")]')) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // Card number
        $this->http->GetURL($this->host . '/my-account/myRFLCards.jsp');
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('
            (//p[contains(., "card number:") or contains(., "kaartnummer:")]/b)[1]
            | //div[contains(@class, "desktop") or contains(@class, "laptop-view") or @id = "__next"]//span[contains(@class, "card-number")]
        '));

        // Your current total
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "desktop") or contains(@class, "laptop-view") or @id = "__next"]//span[contains(@class, "currentPoints")]'));

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && (
                $this->http->FindSingleNode('
                    //h3[contains(text(), "Sign in to your rewards for life account")]
                    | //h5[contains(text(), "Join Rewards for Life")]
                ')
                || $this->http->FindPreg('/"fetchErrored":false,"fetchLoading":false},"rfl":\{"data":\{"cards":\[\],/')
            )
        ) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // My Reward Coupons
        $coupons = $this->http->XPath->query("//h3[contains(text(), 'Coupons') or contains(text(), 'waardebonnen')]/following-sibling::div//table//tr[td[contains(., 'Print')] and contains(@class, 'hide-on-mobile')]
            | //div[contains(@class, \"desktop\") or contains(@class, \"laptop-view\") or @id = \"__next\"]//div[h2[contains(text(), 'My Rewards vouchers')]]/following-sibling::ul/li");
        $this->logger->debug("Total {$coupons->length} coupons were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($coupons as $coupon) {
            // Balance - Reward Value
            $balance = $this->http->FindSingleNode('td[1] | .//div[contains(@class, "amount")]', $coupon);
            // Coupon number
            $code = $this->http->FindSingleNode('td[2] | .//p[contains(@class, "voucher-number")]', $coupon);
            // Coupon expires
            $exp = $this->ModifyDateFormat($this->http->FindSingleNode('td[4] | .//p[contains(@class, "voucher-expiry")]', $coupon));

            if (isset($balance, $coupon, $exp) && strtotime($exp)) {
                $this->AddSubAccount([
                    "Code"           => "barrettCoupon{$this->AccountFields['Login2']}{$code}",
                    "DisplayName"    => "Coupon #{$code}",
                    "Balance"        => $balance,
                    "Issued"         => $this->http->FindSingleNode('td[3]', $coupon),
                    "ExpirationDate" => strtotime($exp),
                ]);
            }
        }// foreach ($coupons as $coupon)
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[contains(@action,'/my-account/login.jsp')]//input[contains(@data-callback, 'onSubmit') and contains(@class, 'g-recaptcha captcha-inline')]/@data-sitekey");

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

    protected function parseCaptcha($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('captcha: ' . $data);
        $imageData = $this->http->FindPreg("/svg\+xml;base64\,\s*([^<]+)/ims", false, $data);
        $this->logger->debug("jpeg;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);

            if (!extension_loaded('imagick')) {
                $this->DebugInfo = "imagick not loaded";
                $this->logger->error("imagick not loaded");

                return false;
            }

            $im = new Imagick();
            $im->setBackgroundColor(new ImagickPixel('transparent')); //$im->setResolution(300, 300); // for 300 DPI example
            $im->readImageBlob($imageData);

            /*png settings*/
            $im->setImageFormat("png32");

            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";

            $im->writeImage($file);
            $im->clear();
            $im->destroy();
        }

        if (!isset($file)) {
            return false;
        }

        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("
                (//a[@id = 'header-acc-logout']
                | //li[@class = 'local-nav-item']/a[normalize-space() = 'Logout'])[1]
                | //button[contains(@class, 'sign-out')]
                | //button[contains(text(), 'Sign out')]
            ")
//                | //span[contains(text(), 'Sign out')]
            && !strstr($this->http->currentUrl(), '/my-account/login.jsp?expiration=true')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We are currently performing a scheduled upgrade.")]
                | //span[contains(text(), "This application is currently unavailable. We apologize for the inconvenience.")]
                | //div[contains(text(), "We\'re undergoing a bit of scheduled maintenance.")]
                | //h2[contains(text(), "Our site is currently unavailable whilst we resolve technical issues.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
