<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirbaltic extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = "https://www.pins.co/int-en/my-account/card";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $sendCaptchaForm = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
//        $this->http->removeCookies();

        $this->http->GetURL("https://www.pins.co/int-en/login");

        if (!$this->http->ParseForm(null, "//form[contains(@action, '/int-en/login')]")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("_password", $this->AccountFields['Pass']);

        /*if($key = $this->http->FindPreg('/var GOOGLE_RECAPTCHA_SITE_KEY = "(.+?)";/')) {
            $captcha = $this->parseCaptcha($key);
            if ($captcha === false)
                return false;
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('my-g-recaptcha-response', $captcha);
        }*/

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# The requested URL was not found on this server
        if ($message = $this->http->FindPreg("/The requested URL was not found on this server/ims")) {
            throw new CheckException("Site is temporarily down. Please try to access it later.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Upgrading Internet reservations system
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Currently we are upgrading our Internet reservations system')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# There was an error performing your request
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'There was an error performing your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize, but the page you requested is unavailable at this time
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'We apologize, but the page you requested is unavailable at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($this->http->Response['code'] == 504
            || $this->http->FindSingleNode("//h2[contains(text(), 'The server encountered a temporary error and could not complete your request.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseStepCaptcha()
    {
        $this->logger->notice(__METHOD__);

        if ($key = $this->http->FindPreg('/var GOOGLE_RECAPTCHA_SITE_KEY = "([\w\-]+)";/')) {
            $captcha = $this->parseCaptcha($key);

            if ($captcha === false) {
                return false;
            }

            $this->http->PostURL('https://www.pinsforme.com/app/sso', [
                'g-recaptcha-response' => $captcha,
                'submit'               => 'Continue',
            ]);

            return true;
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [404, 500])) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        if ($this->parseQuestion()) {
            $this->captchaReporting($this->recognizer);

            return false;
        }

        $message = $this->http->FindSingleNode('//div[contains(@class, "i-flash-message-container error")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Incorrect e-mail or password')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Failed to send confirmation code please contact us!')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // AccountID: 711898
        if ($this->http->currentUrl() == 'https://www.pins.co/int-en/login/verification' && $this->http->Response['code'] == 500) {
            $this->http->GetURL("https://www.pins.co/int-en/");
            // Balance - My PINS
            $balance = $this->http->FindSingleNode("//span[contains(@class, 'i-header-main__nav-link-user-balance')]", null, true, "/(.+)p/");
            $this->SetBalance($balance);
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(@class, \'i-header-main__nav-link-user-balance\')]/preceding-sibling::node()[1]')));

            return false;
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Main PINS Program Terms & Conditions')]")) {
            $this->captchaReporting($this->recognizer);
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//p[contains(text(), "We have sent a verification code to your phone number ending with")]');

        if (!isset($question) || !$this->http->ParseForm("login_verification")) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue("login_verification[confirmationCode]", $this->Answers[$this->Question]);
        $this->http->SetInputValue("login_verification[rememberDevice]", "1");
        // remove old code
        unset($this->Answers[$this->Question]);

        if (!$this->http->PostForm()) {
            return false;
        }
        // Wrong code
        $error = $this->http->FindSingleNode('//div[contains(@class, "i-flash-message-container error")]');
        $this->logger->error("[Error]: {$error}");

        if (strstr($error, 'Incorrect code')) {
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        // My card
        $this->SetProperty("Number", $this->http->FindSingleNode("(//h5[contains(@class, 'i-account-card__content-card-number')])[1]"));
        // Balance - My PINS
        $balance = $this->http->FindSingleNode("//span[contains(@class, 'i-header-main__nav-link-user-balance')]", null, true, "/(.+)p/");
        $this->SetBalance($balance);

        // get balance from header
        /*
        if (!$balance && $this->http->FindSingleNode("//h1[contains(text(), 'Main PINS Program Terms & Conditions')]")) {
            $user = $this->http->JsonLog(urldecode($this->http->getCookieByName("user", ".pinsforme.com")));
//            $this->SetBalance($user->points ?? null);
            $this->SetBalance($user->points_household ?? null); // like as extension
            // My PINS card number
            $this->SetProperty("Number", $user->membership_number ?? null);
            // Status
            $this->SetProperty("Status", $user->tier_name ?? null);
            // Name
            $this->SetProperty("Name", beautifulName($user->first_name ?? null));

            return false;
        }

        // Expiration Date
        $exp = $this->http->XPath->query("//div[@id = 'balance-expire']/ul/li");
        $this->logger->debug("Total {$exp->length} exp nodes found");

        for ($i = 0; $i < $exp->length; $i++) {
            $points = $this->http->FindSingleNode("b", $exp->item($i));
            $month = $this->http->FindSingleNode("text()[last()]", $exp->item($i), true, "/in\s+([^<]+)/ims");
            $date = '01 ' . $month . ' ' . date('Y');
            $this->logger->debug($date . " / " . $points . " points");

            if ($points > 0 && strtotime($date)) {
                // PINS to Expire
                $this->SetProperty("PointsToExpire", $points);
                $this->SetExpirationDate(strtotime($date));

                break;
            }// if ($points > 0 && strtotime($date))
        }// for ($i = 0; $i < $exp->length; $i++)

        // Expiration Date  // refs #8861
        if ($exp->length == 3 && !isset($this->Properties['PointsToExpire'])) {
            $this->logger->debug("Load history...");
            $date = strtotime("-4 year");
            $this->http->GetURL("https://www.pinsforme.com/en/my-account/account-statement?from_date=" . date("Y-m-d", $date) . "&to_date=" . date("Y-m-d") . "&type=collected");
            $nodes = $this->http->XPath->query("//table[@id = 'all-points']//tr[td]");
            $this->logger->debug("Total {$nodes->length} nodes were found");

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $points = $this->http->FindSingleNode("td[contains(@class, 'points')]", $node);
                $date = $this->http->FindSingleNode("td[contains(@class, 'date')]", $node);

                if (isset($date) && isset($points) && $points > 0) {
                    $pointsEarned[$i] = [
                        'date'   => $date,
                        'points' => $points,
                    ];
                    $balance -= $pointsEarned[$i]['points'];
                    $this->logger->debug("Date {$pointsEarned[$i]['date']} - " . var_export(strtotime($pointsEarned[$i]['date']), true) . " - {$pointsEarned[$i]['points']} / Balance: $balance");

                    if ($balance <= 0) {
                        $this->logger->debug("Date " . $pointsEarned[$i]['date']);
                        $this->logger->debug("Expiration Date " . date("Y-m-d", strtotime("+3 year", strtotime($pointsEarned[$i]['date']))) . " - "
                            . var_export(strtotime("+3 year", strtotime($pointsEarned[$i]['date'])), true));
                        // Earning Date     // refs #4936
                        $this->SetProperty("EarningDate", $pointsEarned[$i]['date']);
                        // Expiration Date
                        $this->SetExpirationDate(strtotime("+3 year", strtotime($pointsEarned[$i]['date'])));
                        // Points to Expire
                        $balance += $pointsEarned[$i]['points'];

                        for ($k = $i - 1; $k >= 0; $k--) {
                            $this->logger->debug("> Balance: {$balance}");

                            if (isset($pointsEarned[$k]['date'])
                                && $pointsEarned[$i]['date'] == $pointsEarned[$k]['date']) {
                                $balance += $pointsEarned[$k]['points'];
                            }
                        }
                        $this->SetProperty("PointsToExpire", $balance);

                        break;
                    }// if ($balance <= 0)
                }//if (isset($date) && isset($points) && $points > 0)
            }// for ($i = $nodes->length - 1; $i >=0; $i--)
        }// if ($exp->length == 3 && !isset($this->Properties['PointsToExpire']))

        $this->http->GetURL("https://www.pinsforme.com/en/my-account/status-level/airbaltic");
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[contains(text(), 'Your status')]/span"));
        // Status PINS
        $this->SetProperty("StatusPoints", $this->http->FindSingleNode("//p[contains(text(), 'Status PINS')]/preceding-sibling::span[1]"));
        // Last year's flights
        $this->SetProperty("LastYearsFlights", $this->http->FindSingleNode('//p[contains(text(), "Last year\'s flights")]/preceding-sibling::span[1]'));
        */

        $this->http->GetURL("https://www.pins.co/int-en/my-account/profile");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(text(), "Your name")]/following-sibling::div')));
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (empty($key)) {
            $key = $this->http->FindSingleNode("//form[@name = 'inline_login_form']//div[@class = 'g-recaptcha']/@data-sitekey");
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

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            !strstr($this->http->currentUrl(), '/login')
            && $this->http->FindNodes("//a[contains(@href,'/int-en/logout')]")
        ) {
            return true;
        }

        return false;
    }
}
