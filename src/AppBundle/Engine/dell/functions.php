<?php

use AwardWallet\Engine\dell\QuestionAnalyzer;

class TAccountCheckerDell extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 7;

    public $regionOptions = [
        ''   => 'Select your region',
        'us' => 'USA',
        'ca' => 'Canada',
        'uk' => 'UK',
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $rewardsPageURL;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'dellDollars')) {
            if (isset($properties['Currency']) && $properties['Currency'] == 'CAD') {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "CAD $%0.2f");
            }

            if (isset($properties['Currency']) && $properties['Currency'] == 'GBP') {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
            }

            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if ($this->AccountFields['Login2'] == '') {
            $this->AccountFields['Login2'] = 'us';
        }
        $this->rewardsPageURL = "https://www.dell.com/myaccount/en-{$this->AccountFields['Login2']}/rewards";
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL($this->rewardsPageURL);
        $login = $this->waitForElement(WebDriverBy::id('SignInModel_EmailAddress'), 5);
        $pass = $this->waitForElement(WebDriverBy::id('userPwd_UserInputSecret'), 0);
        $btn = $this->waitForElement(WebDriverBy::id('btnSignIn'), 0);

        if (!isset($login, $pass, $btn)) {
            $this->saveResponse();

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $this->captchaWorkaround();

        $btn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //span[@class="mh-si-label"]
            | //div[@id = "user_name"]
            | //span[@id = "um-si-label" and not(contains(text(), "Sign In"))]
            | //form[@id="frmSignIn"]//div[@id="validationSummaryText"]
            | //div[@id="divMessageBarContent"]
            | //div[@name="validationSummaryText" and string-length(text()) > 2]
            | //h2[contains(text(), "IN HONOR OF STANDING OUT")]
            | //h2[@data-test-id = "portal-welcome-back-msg"]
            | //p[contains(text(), "A one-time verification code has been sent to your registered email address.")]
            | //p[contains(text(), "Enter the code we sent to")]
        '), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($question = $this->http->FindSingleNode('//p[contains(text(), "A one-time verification code has been sent to your registered email address.")] | //p[contains(text(), "Enter the code we sent to")]')) {
            $this->captchaReporting($this->recognizer);

            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to check QuestionAnalyzer");
            }

            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $message = $this->http->FindSingleNode('//span[starts-with(@id, "imageText_") and contains(@id, "-error")]')
            ?? $this->http->FindSingleNode('//div[@id="divMessageBarContent" and normalize-space(.) != ""]')
            ?? $this->http->FindSingleNode('(//div[@name="validationSummaryText" and string-length(text()) > 2])[1]')
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                stripos($message, 'The characters entered do not match the image') !== false
                || stripos($message, 'Please enter the characters that appear in the image, in order to proceed.') !== false
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($message, 'We are unable to match the details you entered with our records.')
                || strstr($message, 'Email address unauthorized. Please contact Customer Care.')
                || strstr($message, 'We are unable to match the details you entered with our records')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'We are sorry but there seems to be a problem. Please try again after some time.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Please reset your password with OTP.'
                || strstr($message, 'To enhance the safety and security of your data, we recommend using the "Create or Reset password"')
                || strstr($message, 'Your account is now locked.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'To ensure safety and security of your data, we have sent a link to set a new password.')) {
                $this->throwProfileUpdateMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $btnVerify = $this->waitForElement(WebDriverBy::xpath('//button[@id = "btnVerify"]'), 0);
        $this->saveResponse();

        if (!$btnVerify) {
            return false;
        }

        for ($i = 1; $i <= mb_strlen($answer) && $i < 7; $i++) {
            $securityAnswer = $this->waitForElement(WebDriverBy::xpath("//input[@name ='otpBoxmfaOtpBox{$i}']"), 0);

            if ($securityAnswer) {
                $securityAnswer->clear();
                $securityAnswer->sendKeys($answer[$i - 1]);
                usleep(100);
            }
        }

        $this->saveResponse();

        $this->captchaWorkaround();

        $btnVerify->click();

        $this->waitForElement(WebDriverBy::xpath('
            //span[@class="mh-si-label"]
            | //div[@id = "user_name"]
            | //span[@id = "um-si-label" and not(contains(text(), "Sign In"))]
            | //form[@id="frmSignIn"]//div[@id="validationSummaryText"]
            | //div[@name="validationSummaryText" and string-length(text()) > 2]
            | //h2[contains(text(), "IN HONOR OF STANDING OUT")]
            | //h2[@data-test-id = "portal-welcome-back-msg"]
            | ' . $this->getBalanceXpath()
        ), self::WAIT_TIMEOUT);
        $this->saveResponse();

        $message = $this->http->FindSingleNode('//span[starts-with(@id, "imageText_") and contains(@id, "-error")]')
            ?? $this->http->FindSingleNode('(//div[@name="validationSummaryText" and string-length(text()) > 2])[1]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                stripos($message, 'The characters entered do not match the image') !== false
                || stripos($message, 'Please enter the characters that appear in the image, in order to proceed.') !== false
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($message, 'We are unable to match the details you entered with our records.')
                || strstr($message, 'Email address unauthorized. Please contact Customer Care.')
                || strstr($message, 'We are unable to match the details you entered with our records')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'We are sorry but there seems to be a problem. Please try again after some time.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Please reset your password with OTP.'
                || strstr($message, 'To enhance the safety and security of your data, we recommend using the "Create or Reset password"')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'To ensure safety and security of your data, we have sent a link to set a new password.')) {
                $this->throwProfileUpdateMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*

        // refs #24195
        if (
            $res
            && strstr($res->getText(), 'Please enter the characters that appear in the image')
            && $this->captchaWorkaround()
        ) {
            $btnVerify = $this->waitForElement(WebDriverBy::xpath('//button[@id = "btnVerify"]'), 0);
            $this->saveResponse();

            if (!$btnVerify) {
                return false;
            }

            for ($i = 1; $i <= mb_strlen($answer) && $i < 7; $i++) {
                $securityAnswer = $this->waitForElement(WebDriverBy::xpath("//input[@name ='otpBoxmfaOtpBox{$i}']"), 0);

                if ($securityAnswer) {
                    $securityAnswer->clear();
                    $securityAnswer->sendKeys($answer[$i - 1]);
                    usleep(100);
                }
            }

            $this->saveResponse();
            $btnVerify->click();

            $this->waitForElement(WebDriverBy::xpath('
                //span[@class="mh-si-label"]
                | //div[@id = "user_name"]
                | //span[@id = "um-si-label" and not(contains(text(), "Sign In"))]
                | //form[@id="frmSignIn"]//div[@id="validationSummaryText"]
                | //div[@name="validationSummaryText" and string-length(text()) > 2]
                | //h2[contains(text(), "IN HONOR OF STANDING OUT")]
                | //h2[@data-test-id = "portal-welcome-back-msg"]
                | ' . $this->getBalanceXpath()
            ), self::WAIT_TIMEOUT);
            $this->saveResponse();
        }

        */

        return true;
    }

    public function Parse()
    {
        $this->driver->manage()->window()->maximize();

        if ($this->http->currentUrl() != $this->rewardsPageURL) {
            try {
                $this->http->GetURL($this->rewardsPageURL);
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            }
        }

        $this->saveResponse();

        if ($iframeSurvey = $this->waitForElement(WebDriverBy::id('iframeSurvey'), 3)) {
            $this->driver->switchTo()->frame($iframeSurvey);
            $btn = $this->waitForElement(WebDriverBy::xpath('//*[contains(text(), "No, thanks")]'), 0);
            $this->saveResponse();

            if ($btn) {
                $btn->click();
            }
            $this->driver->switchTo()->defaultContent();
        }

        $balanceXPath = $this->getBalanceXpath();
        $resultXpath = $balanceXPath . '            
            | //p[contains(text(), "Join Dell Rewards for free and")]
            | //p[contains(text(), "By joining Dell Rewards, you agree to receive Dell Rewards emails.")]
            | //input[@id = "Password"]
            | //div[@data-testid="ma-rewards-summaryDiffrentCountryDescription"]
        ';
        $res = $this->waitForElement(WebDriverBy::xpath($resultXpath), 10);
        $this->saveResponse();

        if ($wrongCountry = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your Rewards will not be applicable in this country")]'), 0)) {
            throw new CheckException($wrongCountry->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        // AccountID: 5375722 - too long loading
        if (
            $this->http->FindSingleNode('(//p[contains(text(), "Processing....Please wait")])[1]')
            && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Processing....Please wait")]'), 0)
        ) {
            $this->waitForElement(WebDriverBy::xpath($resultXpath), 80);
            $this->saveResponse();
        }

        // TODO: provider bug fix (emmpty rewards page)
        if (!$res) {
            $this->http->GetURL("https://www.dell.com/myaccount/en-{$this->AccountFields['Login2']}/overview");
            $this->waitForElement(WebDriverBy::xpath($resultXpath), 10);
            $this->saveResponse();
        }

        // Balance - Available Rewards - General
        $balanceEl = $this->waitForElement(WebDriverBy::xpath($balanceXPath), 0);

        if (!$balanceEl || !$this->SetBalance($balanceEl->getText())) {
            if ($this->http->FindSingleNode('(//p[
                    contains(text(), "Join Dell Rewards for free and")
                    or contains(., "By joining Dell Rewards, you agree to receive Dell Rewards emails.")
                ])[1]')
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('
            //span[@mh-sign-in-label = "Sign In"]
            | //span[@id = "um-si-label" and not(contains(text(), "Sign In"))]
        ') ?? $this->http->FindSingleNode('//h2[@data-test-id="portal-welcome-back-msg"]', null, true, "/Hi\s+([^,]+)/")));

        if (isset($this->Properties['Name']) && strstr($this->Properties['Name'], "[Object Object]")) {
            $this->logger->notice("Remove wrong Name: '{$this->Properties['Name']}'");
            unset($this->Properties['Name']);
        }

        $expBalanceXPath = [
            'us' => '//h1[@data-test-id = "rewardsDellCash"]',
            'ca' => '//div[contains(@class, "__rewards_pointswrapper")]//h1/strong',
            'uk' => '//h4/strong',
        ];
        // Expiring Balance
        $expBalanceEl = $this->waitForElement(WebDriverBy::xpath($expBalanceXPath[$this->AccountFields['Login2']] . ' | //h5[contains(text(), "point")]/preceding-sibling::h4'), 0);

        if ($expBalanceEl && is_numeric($expBalance = $expBalanceEl->getText())) {
            $this->SetProperty("ExpiringBalance", $expBalance);
        }

        $expDateXPath = [
            'us' => '//p[@data-test-id="rewardsDellCashExpiringDate"]',
            'ca' => '//div[contains(@class, "__rewards_pointswrapper")]//p[contains(text(), "Expiring on")]/span',
            'uk' => '//p[contains(text(), "Expiring on")]/span',
        ];
        // Expiration date
        $expDateEl = $this->waitForElement(WebDriverBy::xpath($expDateXPath[$this->AccountFields['Login2']] . ' | //h5[contains(text(), "point")]/preceding-sibling::p'), 0);

        if ($expDateEl && $exp = strtotime(str_replace('Expiring on ', '', $expDateEl->getText()))) {
            $this->SetExpirationDate($exp);
        }

        // Pending
        $pending = $this->http->FindSingleNode('
            //div[h2[contains(text(), "Dell Rewards")]]//div[h3[contains(text(), "Pending Rewards*")]]/h2
            | //div[h2[contains(text(), "Dell Rewards")]]//div[div[contains(text(), "Pending Rewards*")]]/div[contains(text(), "$")]
        ', null, true, '/\\$(.+)/');

        if ($pending) {
            $this->AddSubAccount([
                "Code"        => "dellPending",
                "DisplayName" => "Pending",
                "Balance"     => $pending,
            ]);
        }

        if ($dollarsEl = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "__rewards_dellcash_wrapper")]//h1 | //h4[contains(text(), "Dell Dollar")]/preceding-sibling::h1'), 0)) {
            $dollars = $dollarsEl->getText();
        }

        if ($dollarsEl && $dollarsExpDateEl = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "__rewards_dellcash_wrapper")]//p[contains(text(), "Expiring on")] | //h5[contains(text(), "Dell Dollar")]/preceding-sibling::p'), 0)) {
            $dollarsExpDate = strtotime($this->http->FindPreg('/Expiring on - (\w{3} \d{1,2}, \d{4})/', false, $dollarsExpDateEl->getText()) ?? '');
        }

        if ($dollarsEl && $dollarsExpBalanceEl = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "__rewards_dellcash_wrapper")]//strong | //h5[contains(text(), "Dell Dollar")]/preceding-sibling::h4'), 0)) {
            $dollarsExpBalance = $dollarsExpBalanceEl->getText();
        }

        if (isset($dollars)) {
            $subAcc = [
                'Code'                  => 'dellDollars',
                'DisplayName'           => 'Cash balance',
                'Balance'               => $dollars,
                'ExpiringBalance'       => $dollarsExpBalance ?? null,
            ];

            $subAcc['Currency'] = [
                'us' => 'USD',
                'ca' => 'CAD',
                'uk' => 'GBP',
            ][$this->AccountFields['Login2']];

            if (!empty($dollarsExpDate)) {
                $subAcc['ExpirationDate'] = $dollarsExpDate;
            }

            $this->AddSubAccount($subAcc);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $imageData = $this->http->FindSingleNode('//img[starts-with(@id, "captcha-image-")]/@src', null, true, "/png;base64\,\s*([^<]+)/ims");
        $this->logger->debug("png;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);
            $image = imagecreatefromstring($imageData);
            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".png";
            imagejpeg($image, $file);
        }

        if (!isset($file)) {
            return false;
        }
        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, [
            'regsense'         => 1,
            'language'         => 2,
            'textinstructions' => 'Only lower register here / Здесь только маленькие буквы',
        ]);
        unlink($file);

        return $captcha;
    }

    private function captchaWorkaround(): bool
    {
        $this->logger->notice(__METHOD__);

        $captchaInput = $this->waitForElement(WebDriverBy::xpath('//input[starts-with(@id, "imageText_")]'), 0);

        if ($captchaInput && $this->waitForElement(WebDriverBy::xpath('//img[starts-with(@id, "captcha-image")]'), 5)) {
            $this->saveResponse();
            $captcha = $this->parseCaptcha();

            if (!$captcha) {
                return false;
            }
            $captchaInput->sendKeys($captcha);
            $this->saveResponse();

            return true;
        }

        return false;
    }

//    public function IsLoggedIn()
//    {
//        $this->http->GetURL(self::REWARDS_PAGE_URL);
//
//        if ($this->loginSuccessful()) {
//            return true;
//        }
//
//        return false;
//    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        /*
        $accName = $this->waitForElement(WebDriverBy::xpath('
            //span[@class="mh-si-label"]
            | //div[@id = "user_name"]
            | //span[@id = "um-si-label" and not(contains(text(), "Sign In"))]
            | //h2[contains(text(), "IN HONOR OF STANDING OUT")]
            | //h2[@data-test-id = "portal-welcome-back-msg"]
        '), 0);
        $this->saveResponse();

        if ($accName || $this->http->FindSingleNode('//span[@class="mh-si-label" and not[contains(text(), "Sign")]]')) {
            return true;
        }

        return false;
        */
        return !is_null($this->http->FindSingleNode('//h2[@data-test-id = "portal-welcome-back-msg"]'));
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            throw new CheckRetryNeededException(2);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error - Read")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function authorization()
    {
        $this->logger->notice(__METHOD__);
        $pass = $this->waitForElement(WebDriverBy::id('Password'), 0);
        $login = $this->waitForElement(WebDriverBy::id('EmailAddress'), 0);
        $captchaInput = $this->waitForElement(WebDriverBy::id('ImageText'), 0);

        $this->saveResponse();

        if (!$pass) {
            $this->logger->notice("password filed not found");

            return false;
        }

        if ($captchaInput) {
            $captcha = $this->parseCaptcha();

            if (!$captcha) {
                return false;
            }
            $captchaInput->sendKeys($captcha);
        }

        if ($login) {
            $login->sendKeys($this->AccountFields['Login']);
        }
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();
        $pass->sendKeys(WebDriverKeys::ENTER);
        $this->saveResponse();

        return true;
    }

    private function getBalanceXpath()
    {
        $this->logger->notice(__METHOD__);

        return implode(' | ', [
            'us'     => '//h1[@data-test-id = "rewardsDellCash"]',
            'uk'     => '//div[@class="ma__rewards-reimagined"]//h1/span[1]',
            'usMain' => '//h4[contains(text(), "point")]/preceding-sibling::h1[contains(@class, "ma__rewards-summary-points-dellCash-fontSize")]',
        ]);
    }
}
