<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerBankofamerica extends TAccountChecker
{
    use ProxyList;

    public const ONE_TIME_CODE_QUESTION = 'One time authorization code (was sent to your email). The code will expire 10 minutes after you request it.';
    public const ONE_TIME_CODE_QUESTION_VIA_TEXT = 'One time authorization code (was sent to your mobile phone). The code will expire 10 minutes after you request it.';
    public const SAFE_PASS_CODE_QUESTION = "Please enter SafePass Code which was sent to your mobile device."; /*checked*/
    public const MERRILL_BUSINESS = " - Merrill Business";
    public const PM_FP = "version=1&pm_fpua=mozilla/5.0 (macintosh; intel mac os x 10_13_4) applewebkit/537.36 (khtml, like gecko) chrome/66.0.3359.117 safari/537.36|5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.117 Safari/537.36|MacIntel&pm_fpsc=24|1440|900|830&pm_fpsw=&pm_fptz=5&pm_fpln=lang=en-US|syslang=|userlang=&pm_fpjv=0&pm_fpco=1";
    public const F_VARIABLE = "TF1;015;;;;;;;;;;;;;;;;;;;;;;Mozilla;Netscape;5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.117 Safari/537.36;20030107;undefined;true;;true;MacIntel;undefined;Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.117 Safari/537.36;en-US;undefined;secure.bankofamerica.com;undefined;undefined;undefined;undefined;true;false;1524217764402;5;6/7/2005, 9:33:44 PM;1440;900;;;;;;;4;-300;-360;4/20/2018, 2:49:24 PM;24;1440;830;0;22;;;;;;;;;;;;;;;;;;;15;";
    public const XPATH_WRONG_CAPTCHA = "//p[contains(text(), 'Please enter the correct text from the image')] | //div[contains(text(), 'Please enter the correct text from the image')] | //li[contains(text(), 'Please enter the correct text from the image')]";

    public $regionOptions = [
        ""              => "Please select your website",
        "WorldPoints"   => "managerewardsonline.bankofamerica.com",
        "Bankamericard" => "bankofamerica.com",
        "Merrill"       => "Merrill Business",
    ];

    protected $loginUrl;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        if ($accountInfo['Login'] == 'oauth') {
            return new \AwardWallet\Engine\bankofamerica\APIChecker();
        } else {
            return new static();
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                   => "PostingDate",
            "Description"            => "Description",
            "Amount"                 => "Amount",
            "Currency"               => "Currency",
            "Category"               => "Category",
            "Status"                 => "Info",
            "Type"                   => "Info",
            "Points"                 => "Miles",
        ];
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && ($properties['Currency'] == '$')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login3'] == 'Bankamericard') {
            $arg['RedirectURL'] = 'https://secure.bankofamerica.com/login/sign-in/signOnV2Screen.go';
        } else {
            $arg['RedirectURL'] = "https://www.managerewardsonline.bankofamerica.com/RWDapp/ns/home?mc=mwprwd";
        }
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);

        $api = true;

        if ($api) {
            $fields = array_merge(
                [
                    "AuthInfo" => ["Type" => "oauth"],
                ],
                $fields
            );
            unset($fields['Login']);
            unset($fields['Login2']);
            unset($fields['Pass']);
            unset($fields['SavePassword']);
        }

        if (!$api) {
            // Add field "Region"
            ArrayInsert($fields, "Login", true, [
                "Login3" => [
                    "Type"      => "string",
                    "InputType" => "select",
                    "Required"  => true,
                    "Caption"   => "Website",
                    "Note"      => "Please choose the name of the website where you can see your point balance",
                    "Options"   => $this->regionOptions,
                ],
            ]);
        }
    }

    /**
     * support for merrill.
     */
    public function TuneForm($arAccountFields)
    {
        if (isset($arAccountFields) && $arAccountFields['Login3'] == 'Merrill') {
            return [
                'Title' 	=> 'Bank of America' . self::MERRILL_BUSINESS,
            ];
        }

        return [];
    }

    public static function DisplayName($fields)
    {
        if ($fields['Login3'] == 'Merrill') {
            $fields['DisplayName'] .= self::MERRILL_BUSINESS;
        }

        return $fields['DisplayName'];
    }

    public static function ProviderName($fields)
    {
        if ($fields['Login3'] == 'Merrill') {
            $fields['ProviderName'] .= self::MERRILL_BUSINESS;
        }

        return $fields['ProviderName'];
    }

    public function IsLoggedIn()
    {
//        $this->http->GetURL("https://www.managerewardsonline.com/RWDapp/pointsdetails");
//        return $this->http->FindSingleNode("//div[@id='accountminibox']") !== null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->StepTimeout = 600;
        //		$this->http->UseSSLv3();
        $this->logger->notice("Login 3 => " . $this->AccountFields['Login3']);

        if ($this->AccountFields['Login3'] == 'Bankamericard') {
            $this->loginUrl = 'https://secure.bankofamerica.com/login/sign-in/signOnV2Screen.go';
        } else {
            $this->loginUrl = "https://www.managerewardsonline.bankofamerica.com/RWDapp/ns/home?mc=mwprwd";
        }
        // refs #7130
        $this->http->setCookie("cmTPSet", 'Y', '.bankofamerica.com');
    }

    public function LoadLoginForm()
    {
        throw new CheckException("Authorization required", ACCOUNT_INVALID_PASSWORD);
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        // fix for caribbeanvisa
        if ($this->AccountFields['Login3'] == 'Bankamericard') {
            $this->loginUrl = 'https://secure.bankofamerica.com/login/sign-in/signOnV2Screen.go';
        }
        $this->logger->notice("Login 3 => " . $this->AccountFields['Login3']);
        $this->http->GetURL($this->loginUrl);

        return true;
    }

    public function checkErrorsOfBankamericard()
    {
        //# The information you entered does not match our records. Please verify your information.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The information you entered does not match our records')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The information you entered does not match our records. Please verify your information
        if ($message = $this->http->FindPreg("/The information you entered does not match our records\. Please verify your information\./")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The Online ID or Passcode you entered does not match our records.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The Online ID or Passcode you entered does not match our records.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# There are no open accounts for this Online ID. We are unable to service your request. If you need further assistance you can call 800.933.6262.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There are no open accounts for this Online ID')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Account locked
//        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Account locked') or contains(text(), 'locked your account to keep it safe')]", null, true, "/(We\&#39;ve locked your account to keep it safe\.)/"))
//            throw new CheckException($message, ACCOUNT_LOCKOUT);
        //# We didn't recognize the Passcode you entered.
        if ($message = $this->http->FindPreg("/(We didn[^<\.]+t recognize the Passcode you entered\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We did not recognize the Online ID you entered
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'We did not recognize the Online ID you entered')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We don't recognize your Online ID and/or Passcode. Please try again or visit Forgot Online ID & Passcode?
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 't recognize your Online ID and/or Passcode')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // There are no open accounts for this Online ID. We are unable to service your request.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'There are no open accounts for this Online ID. We are unable to service your request.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# This service is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This service is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are temporarily unable to perform this function. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are temporarily unable to perform this function.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking Passcode Change Required
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Online Banking Passcode Change Required')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking is not available to you at this time.
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Online Banking is not available to you at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking is available.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Online Banking is available.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your access to Online Banking has been locked due to multiple sign-in attempts with invalid information.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your access to Online Banking has been locked due to multiple sign-in attempts with invalid information.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // You've tried too many times to enter your Online ID and Passcode correctly.
        if ($message = $this->http->FindPreg('/You\&#39;ve tried too many times to enter your Online ID and Passcode correctly\./')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // EFTX Terms And Conditions
        if ($this->http->FindSingleNode("//title[contains(text(), 'EFTX Terms And Conditions')]")) {
            $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/remindEftxLogin.go");
        }

        // Account with bug, redirect from "signoff" page
        if ($this->http->currentUrl() == 'https://www.bankofamerica.com/?TYPE=33554433&REALMOID=06-000aea23-f082-1f06-b383-082c0a2840b5&GUID=&SMAUTHREASON=0&METHOD=GET&SMAGENTNAME=-SM-aqqfzgjeqy8S5m8u%2b8h6gZjIC5XifZeAeb5F64xMRkTo1mmai3SO2HDPyq%2bg0LdA&TARGET=-SM-https%3a%2f%2fsecure%2ebankofamerica%2ecom%2fmyaccounts%2fsignoff%2fsignoff--default%2ego') {
            $this->DebugInfo = "Account with bug, redirect from \"signoff\" page";

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking upgrade
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently updating our systems to bring enhanced features to your Online Banking experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // View Online ID/Create New Passcode
        if ($this->http->FindSingleNode('//div[contains(text(), "View Online ID/Create New Passcode")]')
            && $this->http->FindSingleNode('//p[@class = "flow-info" and contains(text(), "We value your security and privacy. For your protection, please provide the following information to verify your identity.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }

    public function checkErrorsOfWorldPoints()
    {
        // Online Banking upgrade
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re currently updating our systems to bring enhanced features to your Online Banking experience.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Site temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Online Banking temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Online Banking temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error 500: usi.basecore.exception.DataPersistenceException
        if ($message = $this->http->FindPreg("/Error 500: usi\.basecore\.exception\.DataPersistenceException/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function checkAnswers()
    {
        $this->logger->debug("state: " . var_export($this->State, true));

        if (isset($this->LastRequestTime)) {
            $timeFromLastRequest = time() - $this->LastRequestTime;
        } else {
            $timeFromLastRequest = SECONDS_PER_DAY * 30;
        }
        $this->logger->debug("time from last code request: " . $timeFromLastRequest);

        if ($timeFromLastRequest > 300 && !empty($this->Answers[self::SAFE_PASS_CODE_QUESTION])) {
            $this->logger->notice("resetting answers, expired");
            unset($this->Answers[self::SAFE_PASS_CODE_QUESTION]);
        }
    }

    public function Login()
    {
        $hardCode = false;
        $this->logger->notice("Login 3 => " . $this->AccountFields['Login3']);

        if ($this->AccountFields['Login3'] == 'Bankamericard') {
            $this->http->FilterHTML = false;

            if (!$this->http->ParseForm("EnterOnlineIDForm")) {
                return false;
            }
            $this->http->FilterHTML = true;
            $this->http->SetInputValue('onlineId', $this->AccountFields['Login']);
            $this->http->SetInputValue('passcode', $this->AccountFields['Pass']);
            $this->http->SetInputValue('pm_fp', self::PM_FP);
            $this->http->SetInputValue('f_variable', self::F_VARIABLE);
            $this->http->unsetInputValue('dummy-onlineId');
            $this->http->unsetInputValue('dummy-passcode');
            $this->http->unsetInputValue('saveMyID');
            $this->http->PostForm();

            // captcha form
            if ($this->http->ParseForm("BumpFlowForm")) {
                $this->logger->notice("Captcha form");
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return false;
                }
                $this->http->SetInputValue("captchaKey", $captcha);
                $this->http->RetryCount = 0;
                $this->http->PostForm();
                $this->http->RetryCount = 2;
                // Please enter the correct text from the image
                if ($message = $this->http->FindSingleNode(self::XPATH_WRONG_CAPTCHA)) {
                    $this->logger->error($message);
                    $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                    throw new CheckRetryNeededException(5, 7);
                }// if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please enter the correct text from the image')]"))
            }// if ($this->http->ParseForm("BumpFlowForm"))

            $this->checkErrorsOfBankamericard();

            if (!$this->parseQuestion()) {
                return false;
            }

            // manual redirect to accounts overview
            if ($this->http->FindPreg("/window\.location\s*=\s*\"\/myaccounts\/signoff\/signoff-default\.go\?timeout=Y\"/ims")
                && $this->http->FindSingleNode("//h1[contains(text(), 'Customer Assistance: Account Notification')]")) {
                $this->logger->notice("manual redirect to accounts overview");
                $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go?target=accountsoverview&request_locale=en-us&source=acl&fsd=y");
            }
            // Select your Business Services
            if ($this->http->FindSingleNode("//h3[contains(text(), 'Choose Online Business Services that suit your needs:')]")
                && $this->http->FindPreg("/Remind me later/")) {
                $this->logger->notice("Select your Business Services: manual redirect to accounts overview");
                $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go?target=accountsoverview&request_locale=en-us&source=acl&fsd=y");
            }
            // Customer Assistance: Update Contact Information
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Customer Assistance: Update Contact Information')]")) {
                $this->logger->notice("Customer Assistance: Update Contact Information: manual redirect to accounts overview");
                $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go?target=accountsoverview&request_locale=en-us&source=acl&fsd=y");
            }

            if ($this->http->FindSingleNode("(//a[contains(@href, 'signoff')]/@href)[1]")) {
                return true;
            }
            /*
             * Your credit card or line of credit account(s) is past due.
             * To bring your account up to date, please pay the Total Minimum Payment Due.
             */
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Customer Assistance: Account Notification')]")
                // Choose your SiteKey image
                || $this->http->FindSingleNode("//h1[contains(text(), 'Choose your SiteKey image')]")
                // Forgot PasscodeForgot Passcode
                || $this->http->FindSingleNode("//p[contains(text(), 'To create a new Passcode, please enter the following.')]")
                // Please set up 3 personal challenge questions and answers we'll use to verify your identity.
                || $this->http->FindSingleNode('//h2[contains(text(), "Please set up 3 personal challenge questions and answers we\'ll use to verify your identity.")]')) {
                $this->throwProfileUpdateMessageException();
            }
            // Create a new Passcode
            if (($this->http->FindSingleNode("//p[contains(text(), 'To create a new Passcode, please enter the following.')]")
                && stristr($this->http->currentUrl(), 'PwdScreen.go?errorCode=error.passcode.locked'))
                || ($this->http->FindSingleNode("//p[contains(text(), 'We value your security and privacy.')]")
                && stristr($this->http->currentUrl(), 'msg=InvalidCredentialsExceptionDenied'))) {
                throw new CheckException("Bank Of America website is asking you to create a new Passcode, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // No match found. Please create a new Online ID and Passcode.
            if ($message = $this->http->FindSingleNode("//b[contains(normalize-space(text()), 'No match found. Please create a')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            $this->checkErrors();

            return false;
        } else {
            for ($n = 0; $n < 1; $n++) { // do not retry for debugging
                $loginLink = $this->http->FindPreg('/a[^>]+href="(' . preg_quote('https://www.bankofamerica.com/banking-information/att-signin-bac.go?token=', '/') . '[^"]+)"/ims');
                // caribbeanvisa
                if (!$loginLink) {
                    $loginLink = $this->http->FindPreg('/option[^>]+value="(' . preg_quote('https://www.bankofamerica.com/banking-information/att-signin-bac.go?token=', '/') . '[^"]+)"/ims');
                }

                $this->logger->notice(get_class($this));
                $this->logger->notice(__METHOD__);

                if (!isset($loginLink) && get_class($this) != 'TAccountCheckerMerill') {
                    return $this->checkErrorsOfWorldPoints();
                }

                if ($this->AccountFields['Login'] == 'mattabees') {
                    $this->logger->debug('test mode for merrill+');
                    $loginLink = 'https://www.bankofamerica.com/banking-information/att-signin-ml.go?token=CbAosvWsSqyNiaioT%2Fbbhp8YLOCWR5T8ti45LxPeBGal5z662Dn56v5v4N1to0l3WiVyWRc6jR7S0dOcj%2BySfTNVJ5sFba8xbZuIfJKKyeab%2FoBOaYrE%2FerVaOCFQtNe4bT3Nt39NMRTbf0Nw7s9GFuhBY1ZdaxJ6Vd1cWQ5L3mrLnoukTB0djbc%2FcYjD7aS';
                }

                if (get_class($this) != 'TAccountCheckerMerill') {
                    $this->logger->debug("login link: " . $loginLink);
                    $this->http->GetURL($loginLink);
                }
                // refs #7130
                $this->http->setCookie("cmTPSet", 'Y', '.bankofamerica.com');

                $siteKeyScript = $this->http->FindPreg('/(inScript=true&gcsl_token=[^"]+)"/ims');

                if (!isset($siteKeyScript)) {
                    return $this->checkErrorsOfWorldPoints();
                }
                $siteKeyScript = "https://secure.bankofamerica.com/login/sign-in/entry/sitekeyWidgetScript.go?" . $siteKeyScript;

                if (!$this->http->GetURL($siteKeyScript)) {
                    return $this->checkErrorsOfWorldPoints();
                }

                $rsaKey = $this->http->FindPreg('/skwEncryptKey:"([^"]+)"/ims');

                if (!isset($rsaKey)) {
                    return false;
                }
                $this->logger->debug("RSA key: " . $rsaKey);
                $this->State["RSAKey"] = $rsaKey;

                $this->logger->notice("sending login");
                $cryptedLogin = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $rsaKey, $this->AccountFields['Login'], MCRYPT_MODE_ECB));
                $this->logger->debug("Key: " . $cryptedLogin);
                // new authorization
                $this->logger->notice(">>> new authorization");
                $cryptedPass = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $rsaKey, $this->AccountFields['Pass'], MCRYPT_MODE_ECB));
                $this->http->PostURL("https://secure.bankofamerica.com/login/widget/skwSignOn.go?callback=jQuery15205395903867477615_" . time() . date("B"), [
                    "onlineId"       => $cryptedLogin,
                    "ibnxs"          => $cryptedPass,
                    "rememeberMe"    => 'false',
                    "f_variable"     => self::F_VARIABLE,
                    "pm_fp"          => self::PM_FP,
                    "creditCardType" => "RAC",
                    "action"         => "showSitekey",
                ]);
                $this->logger->notice("<<< new authorization");

                // captcha form
                $csrfToken = $this->http->FindPreg("/name=\"csrfTokenHidden\" value=\"([^\"]+)/");

                if (($img = $this->http->FindPreg("/<img src=\"([^\"]+)\"[^>]+id=\"imageText\"/")) && $csrfToken) {
                    $this->logger->notice("Captcha form");
                    $captcha = $this->parseCaptcha($img);

                    if ($captcha === false) {
                        return false;
                    }
                    $this->http->RetryCount = 0;
                    $this->http->PostURL("https://secure.bankofamerica.com/login/widget/skwValidateCaptchaText.go?callback=jQuery1520700696801460133_" . time() . date("B"), [
                        "captchaKey"      => $captcha,
                        "csrfTokenHidden" => $csrfToken,
                    ]);
                    $this->http->RetryCount = 2;
                    // Please enter the correct text from the image
                    if ($message = $this->http->FindSingleNode(self::XPATH_WRONG_CAPTCHA)) {
                        $this->logger->error($message);
                        $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                        throw new CheckRetryNeededException(5, 7);
                    }// if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please enter the correct text from the image')]"))
                }// if ($img = $this->http->FindPreg("/<img src=\"([^\"]+)\"[^>]+id=\"imageText\"/"))

            $this->handlerRedirect();

                $this->CheckError($this->findError());
                // security questions
                $this->filterAnswers();

                $question = $this->parseQuestion($cryptedLogin);

                if (isset($question)) {
                    $this->logger->debug("parsed question: " . $question);

                    if ($question == self::SAFE_PASS_CODE_QUESTION) {
                        return false;
                    }

                    if (isset($this->Answers[$question])) {
                        if (!$this->sendAnswer($question, $this->Answers[$question], $rsaKey)) {
                            return false;
                        }
                        $question = $this->parseQuestion($cryptedLogin);
                    }// if (isset($this->Answers[$question]))

                    if (isset($question)) {
                        $this->AskQuestion($question, $this->findError());

                        return false;
                    }// if (isset($question))
                }// if (isset($question))

            return true;
            }
        }

        if ($hardCode) {
            throw new CheckException("You do not have an eligible rewards credit card account to access this website.", ACCOUNT_PROVIDER_ERROR);
        }
        $this->logger->debug("Login end");

        return false;
    }

    // remove \' from questions
    public function filterAnswers()
    {
        foreach ($this->Answers as $question => $answer) {
            $filtered = str_replace("\\'", "'", $question);

            if ($filtered != $question) {
                unset($this->Answers[$question]);
                $this->Answers[$filtered] = $answer;
            }
        }
    }

    public function parseSiteKey()
    {
        if ($this->http->FindPreg("/this is my SiteKey/ims") !== null) {
            $this->http->Log("confirming site key");
            $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/skwEnterPass.go?callback=jQuery15205802314219746824_1361346678888&action=showPasscode_=" . time() . date('B'));
        }
    }

    /* @deprecated */
    public function enterPassword($key, $cryptedLogin)
    {
        $this->logger->notice("entering password");

        if ($this->http->FindSingleNode("//input[@id = 'tlpvt-skw-enter-pass']") === null) {
            return false;
        }

        $cryptedPass = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $this->AccountFields['Pass'], MCRYPT_MODE_ECB));
        $this->http->PostURL("https://secure.bankofamerica.com/login/widget/skwSignOn.go?callback=jQuery152041703297972072884_" . time() . date("B"), [
            "onlineId"       => $cryptedLogin,
            "ibnxs"          => $cryptedPass,
            "rememeberMe"    => 'false',
            "f_variable"     => self::F_VARIABLE,
            "pm_fp"          => self::PM_FP,
            "creditCardType" => "RAC",
            "action"         => "showSitekey",
        ]);

        $this->CheckError($this->findError());
        $this->logger->notice("password sent");

        $this->handlerRedirect();

        return true;
    }

    public function handlerRedirect()
    {
        $this->logger->notice(__METHOD__);
        // captcha redirect
        if ($this->http->FindPreg("/\"skwPageId\":\"skw-captcharedirect-skin\"/")) {
            $this->http->PostURL("https://secure.bankofamerica.com/login/widget/skwSignOn.go?callback=jQuery1520700696801460133_" . time() . date("B"), []);
        }
        // login redirect
        if ($this->http->FindPreg("/\"skwPageId\":\"signin-AO-skin\"/")) {
            $this->http->PostURL("https://secure.bankofamerica.com/login/widget/signinwidgetAuthSuccess.go?callback=jQuery15207370463318202671_" . time() . date("B"), []);
        }

        $this->logger->notice("redirecting to SSO");
        $url = $this->http->FindPreg('/>top\.location="([^"]+)"/ims');

        if (!isset($url)) {
            $this->findError();

            return false;
        }
        $this->http->GetURL($url);
        // postform
        $body = $this->http->Response['body'];

        $this->logger->notice("pinging");

        if (stripos($body, '/myaccounts/signoff/full-signoff-default.go?Pinged=Y') !== false) {
            $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/signoff/full-signoff-default.go?Pinged=Y");
        }

        $this->logger->notice("submitting from SSO");
        $this->http->SetBody($body);

        if ($this->http->ParseForm("postform")) {
            $this->logger->debug("sending form");
            $this->http->PostForm();
        } else {
            $this->logger->error("failed to send form");
            $this->findError();

            return false;
        }

        $this->logger->notice("SAML");

        if ($this->http->FindSingleNode("//body[@onload = 'javascript:document.forms[0].submit()']") !== null
            && $this->http->ParseForm()) {
            $this->http->FormURL = 'https://www.managerewardsonline.com/RWDapp/sitekey';
            $this->logger->debug("sending form");
            $this->http->PostForm();
        }

        return true;
    }

    public function findError()
    {
        $error = $this->http->FindSingleNode("//div[@class = 'skw-error-title' and not(contains(text(), 'Please enter the correct text from the image'))]", null, false);

        if (strstr($error, 'our account is locked')) {
            throw new CheckException($error, ACCOUNT_LOCKOUT);
        }
        // You'll need to set up your challenge questions after you sign in to Online Banking
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "You\'ll need to set up your challenge questions after you sign in to Online Banking.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // You've exceeded the maximum number of attempts to answer your challenge questions and your account is locked
        if ($message = $this->http->FindPreg('/You\&\#39;ve exceeded the maximum number of attempts to answer your challenge questions and your account is locked\./')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // You've exceeded the maximum number of attempts to answer your challenge questions.
        if ($message = $this->http->FindPreg('/You\&\#39;ve exceeded the maximum number of attempts to answer your challenge questions\./')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // View Online ID/Create New Passcode
        if ($this->http->FindSingleNode('//div[contains(text(), "View Online ID/Create New Passcode")]')
            && $this->http->FindSingleNode('//p[@class = "flow-info" and contains(text(), "We value your security and privacy. For your protection, please provide the following information to verify your identity.")]')) {
            $this->throwProfileUpdateMessageException();
        }
        // Forgot Online ID & Passcode
        if (strstr($this->http->currentUrl(), 'https://secure.bankofamerica.com/auth/forgot/reset-entry/')) {
            throw new CheckException("We don't recognize your Online ID and/or Passcode. Please try again or visit Forgot Online ID & Passcode?", ACCOUNT_INVALID_PASSWORD);
        }

        return $error;
    }

    /**
     * returns true if question was parsed.
     */
    public function parseQuestion($cryptedLogin = null)
    {
        $this->logger->notice(__METHOD__);
        $safePass = false;
        // Bankamericard
        if (($this->AccountFields['Login3'] == 'Bankamericard'
            && $this->http->FindPreg("/SafePass is set to protect you at sign in|Please verify your identity using SafePass|SafePass est\&aacute; para protegerle cuando inicia su sesi\&oacute;n/ims")
            && $this->http->ParseForm("ConfirmSitekeySafePassForm"))
            // WorldPoints
            || ($this->AccountFields['Login3'] != 'Bankamericard'
                && ($this->http->FindPreg("/Please enter a new Safepass code/ims") !== null
                || $this->http->FindPreg("/SafePass is set to protect you at sign in/ims") !== null))) {
            $safePass = true;
        }

        if ($this->AccountFields['Login3'] == 'Bankamericard' && !$safePass) {
            // Request Authorization Code
            if ($this->http->currentUrl() == 'https://secure.bankofamerica.com/login/sign-in/displayAuthCodeScreen.go' || $this->http->FindSingleNode("//title[contains(text(), 'Authorization Code Request')]")) {
                /*
                throw new CheckException("We do not support accounts with Authorization Code yet. Please enable SafePass code authorization in your Bank of America profile to get your account updated",ACCOUNT_PROVIDER_ERROR);/*review*/

                $this->logger->notice("Request Authorization Code");
                $pageId = $this->http->FindPreg("/pageId:\"([^\"]+)/ims");
                $inscript = $this->http->FindPreg("/inscript:\"([^\"]+)/ims");

                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                if (isset($pageId, $inscript)) {
                    $this->logger->debug("Loading acwEncryptKey...");
                    $this->http->GetURL("https://secure.bankofamerica.com/login/authcode/authCodeInitialize.go?acw_page_id={$pageId}&inScript={$inscript}");
                    // acwEncryptKey
                    $rsaKey = $this->http->FindPreg("/acwEncryptKey:\"([^\"]+)/ims");
                    $this->logger->debug("acwEncryptKey: " . $rsaKey);

                    if (!$this->sendAuthCode($rsaKey)) {
                        return null;
                    }
                }// if (isset($pageId, $inscript))
            }// Request Authorization Code
            else {
                $this->logger->notice("parseQuestion (Bankamericard): just question");
                $question = $this->http->FindSingleNode("//label[@for = 'tlpvt-challenge-answer']");

                if (!isset($question)) {
                    return true;
                }
                // csrfTokenHidden
                $this->State["csrfTokenHidden"] = $this->http->FindPreg("/csrfTokenHidden\" value=\"([^\"]+)/i");

                if (!$this->http->ParseForm("VerifyCompForm")) {
                    return false;
                }
                $this->State["FormURL"] = $this->http->FormURL;
                $this->Question = $question;
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";

                return true;
            }
        }// if ($this->AccountFields['Login3'] == 'Bankamericard')
        // Sending SafePass Code
        if ($safePass) {
            $this->logger->notice("parseQuestion: SafePass Code");

//                throw new CheckException("We do not support accounts with SafePass code yet",ACCOUNT_PROVIDER_ERROR);

            // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            // check answer
            $this->checkAnswers();
            // notifications
//            $this->sendNotification("bankofamerica (".$this->AccountFields['Login3']."). SafePass Code was sent");
            // csrfTokenHidden
            $this->State["csrfTokenHidden"] = $this->http->FindPreg("/csrfTokenHidden\" value=\"([^\"]+)/i");
            // Send SafePass Code
            $this->State["SafePassCodeSent"] = true;
            $this->State["CodeSentDate"] = time();
            // onlineId
            $this->State["onlineId"] = $cryptedLogin;
            // cookies
            $this->http->setCookie("cmTPSet", "Y", ".bankofamerica.com");
            // initialize
            $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/manageSafepass.go?callback=jQuery15208521170230279849_1400517547228&action=Initialize&device=-1&pageCode=PM7.0&loadeeversion=3.0&safePassNonFlashEnabled=true&_=" . time() . date('B'));
            // send code
            $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/manageSafepass.go?callback=jQuery15203420601023653318_" . time() . date('B') . "&device=0&action=sendCode&pageCode=PM7.0&safePassNonFlashEnabled=true&_=" . time() . date('B'));
            // Ask SafePass Code
            $result = $this->Question = self::SAFE_PASS_CODE_QUESTION;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return $result;
        }// Sending SafePass Code
        else {
            $result = $this->http->FindSingleNode("//label[@for = 'tlpvt-skw-chal-ques']");

            if (isset($result)) {
                // site displays \' instead of '
                $result = str_replace("\\'", "'", $result);
            } else {
                if (stripos($this->http->Response['body'], '"skwPageId":"otp-confirm-id-vrt-skin"')
                    || stripos($this->http->Response['body'], '"skwPageId":"otp-confirm-v2-id-vrt-skin"')) {
                    $result = self::ONE_TIME_CODE_QUESTION;

                    throw new CheckException("We do not support accounts with One Time Authorization Code yet", ACCOUNT_PROVIDER_ERROR); /*review*/

                    if ($this->isBackgroundCheck()) {
                        $this->Cancel();
                    }
                    $this->logger->debug("Loading encryptKey...");
                    $this->http->GetURL("https://secure.bankofamerica.com/login/authcode/authCodeInitialize.go?acw_page_id=VIPAA-OTP-SKW-CHALLENGE&modal=true&callback=jQuery1520587186579592526_1398333851188&_=" . time());
                    $key = $this->http->FindPreg("/encryptKey\":\"([^\"]+)/ims");
                    $this->logger->debug("key: $key");

                    if (!$this->sendAuthCode($key)) {
                        return null;
                    }
                }// if (stripos($this->http->Response['body'], '"skwPageId":"otp-confirm-id-vrt-skin"'))
            }

            return $result;
        }
    }

    public function sendAuthCode($rsaKey)
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login3'] != 'Bankamericard') {
            $this->sendNotification("bankofamerica - {$this->AccountFields['Login3']}. Authorization Code was sent to email");
        }

        $this->logger->debug("Loading html with data");
        $this->http->GetURL("https://secure.bankofamerica.com/login/authcode/authcodeDisplay.go?request_locale=en-us&callback=jQuery152015345151224149156_" . time() . date("B") . "&_=" . time() . date("B"));

        if ($this->AccountFields['Login3'] == 'Bankamericard') {
            // csrfTokenHidden
            $this->State["csrfTokenHidden"] = $this->http->FindPreg("/csrfTokenHidden\" value=\"([^\"]+)/i"); // ???
            $this->checkErrorsOfBankamericard();
        }
        // Email
        $email = $this->http->FindSingleNode("//label[@for = 'tlpvt-email1'] | //label[@class = 'single-email-contact']");
        $this->logger->debug("email: " . $email);
        // Phone
        $phone = $this->http->FindSingleNode("//label[@for = 'rbText1'] | //label[@for = 'tlpvt-phone1'] | (//label[@for = 'tlpvt-phone'])[1] | //label[@class = 'single-mobile-num']");
        $this->logger->debug("phone: " . $phone);
        // first email
        if ($email) {
            $cryptedEmail = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $rsaKey, 'selectedContact|0|contactType|email', MCRYPT_MODE_ECB));
            $question = self::ONE_TIME_CODE_QUESTION;
        }
        // first phone with sms
        elseif ($phone) {
            $cryptedEmail = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $rsaKey, 'selectedContact|0|contactType|text', MCRYPT_MODE_ECB));
            $question = self::ONE_TIME_CODE_QUESTION_VIA_TEXT;
        } else {
            $this->logger->error("error, can't send code");

            return false;
        }
        $cryptedEmail = urlencode($cryptedEmail);
        // Send One Time Code
        $this->State["OneTimeCodeSent"] = true;
        $this->State["CodeSentDate"] = time();
        $this->State["acwEncryptKey"] = $rsaKey;
        unset($this->Answers[self::ONE_TIME_CODE_QUESTION]);
        unset($this->Answers[self::ONE_TIME_CODE_QUESTION_VIA_TEXT]);

        $this->logger->notice("sending code...");
        // this is working
        $this->http->setCookie("BOFA_LOCALE_COOKIE", "en-US", "secure.bankofamerica.com");
        $headers = ["Referer" => "https://secure.bankofamerica.com/login/sign-in/internal/entry/signOn.go"];
        $this->http->GetURL("https://secure.bankofamerica.com/login/authcode/sendAuthCode.go?callback=jQuery152015345151224149156_" . time() . date("B") . "&acw_request_token={$cryptedEmail}&action=processACWRequest&_=" . time() . date("B"), $headers);

        if (!$this->http->FindPreg("/An authorization code was sent /ims")) {
            $this->logger->error("error, can't send code");
            /**
             * You've exceeded the number of attempts to enter your authorization code. We've locked your account to keep it safe.
             *
             * To unlock your account call us at 1.800.933.6262.
             */
            if ($message = $this->http->FindPreg("/You&#39;ve exceeded the number of attempts to enter your authorization code. We&#39;ve locked your account to keep it safe\./")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function sendAnswer($question, $answer, $key)
    {
        $cryptedAnswer = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $answer, MCRYPT_MODE_ECB));
        $this->logger->notice("sending answer: " . $answer);

        if (in_array($question, [self::ONE_TIME_CODE_QUESTION, self::ONE_TIME_CODE_QUESTION_VIA_TEXT])) {
            if ($this->AccountFields['Login3'] != 'Bankamericard') {
                $this->sendNotification("bankofamerica - {$this->AccountFields['Login3']}. Authorization Code was entered");
            }

            $acw_enter_token = urlencode($cryptedAnswer);
            $this->http->GetURL("https://secure.bankofamerica.com/login/authcode/validateAuthCode.go?callback=jQuery15202922289793058067_" . time() . date("B") . "&acw_enter_token={$acw_enter_token}&action=processACWEnter&_=" . time() . date("B"));
            unset($this->Answers[$question]);
            // The authorization code you entered has expired. You can request another authorization code
            if ($error = $this->http->FindPreg("/The authorization code you entered has expired\./")) {
                throw new CheckException("The authorization code you entered has expired. You can request another authorization code", ACCOUNT_PROVIDER_ERROR);
            }
            // Please enter the correct authorization code. We didn't recognize the one you entered.
            if ($error = $this->http->FindPreg("/Please enter the correct authorization code\./")) {
                $this->AskQuestion($question, "Please enter the correct authorization code. We didn't recognize the one you entered.");

                return false;
            }
            // send acwpostform form
            $this->logger->notice("redirect to 'Accounts Overview' (send acwpostform form)");
            $this->http->Form = [];
            $this->http->FormURL = $this->http->FindPreg("/name='acwpostform' method='post' action='([^\']+)/");
            $inputs = $this->http->FindPregAll("/input\s*type='hidden'\s*name='(?<name>[^\']+)'\s*value='(?<value>[^\']+)/", $this->http->Response['body'], PREG_SET_ORDER, true);

            foreach ($inputs as $input) {
                if ($input['name'] == 'otpStatus' && $input['value'] == 'FAILURE') {
                    $this->logger->debug("skip input name='otpStatus' value='FAILURE'");

                    continue;
                }
                $this->http->SetInputValue($input['name'], $input['value']);
            }
            $this->http->PostForm();
        }// if (in_array($question, [self::ONE_TIME_CODE_QUESTION, self::ONE_TIME_CODE_QUESTION_VIA_TEXT]))
        else {
            //		$this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/skwChallenge.go?callback=jQuery15205802314219746824_1361346678887&challengeQuestionAnswer=".urlencode($cryptedAnswer)."&rembme=true&action=checkChallenge&_=".time().date('B'));
            $this->http->PostURL("https://secure.bankofamerica.com/login/widget/skwChallenge.go?callback=jQuery15206373830073004426_" . time() . date("B"), [
                "challengeQuestionAnswer" => $cryptedAnswer,
                "rembme"                  => "true",
                "action"                  => "checkChallenge",
            ]);
            // Yes, remember this computer
            if ($this->http->FindPreg("/Yes, remember this computer/")) {
                $this->http->PostURL("https://secure.bankofamerica.com/login/widget/skwSecurityPref.go?callback=jQuery15209287353952159235_" . time() . date("B"), ["rembComp" => "Y"]);
            }

            $this->handlerRedirect();
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $error = $this->http->FindSingleNode("//h1[contains(text(), 'Unrecognized computer')]");

        if (!isset($error) && $this->http->FindPreg("/Please answer your challenge question so we can\&nbsp;help verify\&nbsp;your identity\./ims")) {
            $error = $this->http->FindPreg("/(We don(?:\'|\&\#39\;)t recognize your answer\.)/ims");
        }

        if (isset($error)) {
            return true;
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'Your credit card or line of credit account(s) is past due.')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // We're sorry, but we're currently unable to complete your request. Please try again later.
        if ($this->http->FindPreg("/errorText1 = \"(We\&\#39;re sorry, but we\&\#39;re currently unable to complete your request\.)\"/")) {
            throw new CheckException("We're sorry, but we're currently unable to complete your request. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("state:");
        $this->logger->debug(var_export($this->State, true), ['pre' => true]);

        if ($this->AccountFields['Login3'] == 'Bankamericard' && $this->Question != self::SAFE_PASS_CODE_QUESTION) {
            $this->logger->notice("ProcessStep (Bankamericard)");

            if (in_array($this->Question, [self::ONE_TIME_CODE_QUESTION, self::ONE_TIME_CODE_QUESTION_VIA_TEXT])) {
                $this->logger->notice(">>> Entering One time authorization code");

                if (!$this->sendAnswer($this->Question, $this->Answers[$this->Question], $this->State["acwEncryptKey"])) {
                    return false;
                }

                return true;
            }// if (in_array($this->Question, [self::ONE_TIME_CODE_QUESTION, self::ONE_TIME_CODE_QUESTION_VIA_TEXT]))
            else {
                $this->logger->notice("ProcessStep (Bankamericard): just question");

                if ((empty($this->http->FormURL) || !strstr($this->http->FormURL, 'validateChallengeAnswer'))
                    && isset($this->State["FormURL"])) {
                    $this->http->FormURL = $this->State["FormURL"];
                } else {
                    $this->http->FormURL = 'https://secure.bankofamerica.com/login/sign-in/validateChallengeAnswerV2.go';
                }
                $this->logger->debug("FormURL: " . $this->http->FormURL);
                $this->http->SetInputValue("challengeQuestionAnswer", $this->Answers[$this->Question]);
                // csrfTokenHidden
                $this->http->SetInputValue("csrfTokenHidden", $this->http->FindSingleNode("//input[@name = 'csrfTokenHidden']/@value"));

                if (empty($this->http->Form["csrfTokenHidden"]) && isset($this->State["csrfTokenHidden"])) {
                    $this->http->SetInputValue("csrfTokenHidden", $this->State["csrfTokenHidden"]);
                }
                $this->http->SetInputValue("rembComp", "Y");

                if (!$this->http->PostForm()) {
                    return false;
                }

                if ($this->checkErrors()) {
                    $this->parseQuestion();

                    return false;
                }// if ($this->checkErrors())
            }
            // enter password
            $this->enteringPasswordBankamericard();

            // You've confirmed your access to Quicken® & QuickBooks®
            if ($this->http->FindPreg('/<h1[^\>]*>(?:You\'ve confirmed|One final step to confirm) your access to Quicken\&reg; \& QuickBooks\&reg;<\/h1>/ims')
                && $this->http->FindPreg("/Remind me later/")) {
                $this->logger->notice("You've confirmed your access to Quicken® & QuickBooks®: manual redirect to accounts overview");
                $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/remindEftxLogin.go");
            }

            return true;
        }// if ($this->AccountFields['Login3'] == 'Bankamericard')
        // SafePass Code
        if ($this->Question == self::SAFE_PASS_CODE_QUESTION) {
            $this->logger->notice("ProcessStep: SafePass Code");
            $this->logger->debug("state: " . var_export($this->State, true));
            // Entering SafePass Code
            if (isset($this->Answers[$this->Question])) {
                $this->logger->debug(">>> Entering SafePass Code");
                $safekey = $this->Answers[$this->Question];
                // cookies
                $this->http->setCookie("cmTPSet", "Y", ".bankofamerica.com");

                if ($this->AccountFields['Login3'] == 'Bankamericard') {
                    $this->http->setDefaultHeader("Referer", "https://secure.bankofamerica.com/login/sign-in/internal/entry/signOn.go");
                }
//                else
//                Referer	https://www.bankofamerica.com/banking-information/att-signin-bac.go?token=CbAosvWsSqyNiaioT%2Fbbhp8YLOCWR5T8ti45LxPeBGal5z662Dn56v5v4N1to0l3MM8%2FcQqzfyBchJXRwG7NYCyTz565WTQhzFTPCMW7fHQIeYZxNFFhikTxZlfPP0bf5nShHUY88TA8ExC8UzW4Ym6O%2FNA76RPWkGpfHrpBZNv91k3VzuKvV2Fo44hQzUg8tBfkKoDU3H1mQkis6yghUyxDRz%2B8gtdjDUaastIP5%2Bs%3D
                $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/manageSafepass.go?callback=jQuery15208521170230279849_1400517547228&safekey={$safekey}&device=0&action=validate&pageCode=PM7.0&safePassNonFlashEnabled=true&_=" . time() . date('B'));
                // otp.validate.code
                $code = $this->http->FindPreg("/\"code\":\"([^\"]+)/ims");
                $this->logger->debug("code: $code");
                // otp.validate.artifact
                $artifact = $this->http->FindPreg("/\"artifact\"\:\"([^\"]+)/ims");

                if (!isset($artifact)) {
                    return false;
                }
                // post next form
                if ($this->AccountFields['Login3'] == 'Bankamericard') {
                    $this->logger->notice("Bankamericard: post next form");
                    // post URL
                    $postURL = 'https://secure.bankofamerica.com/login/sign-in/validateSafepass.go';
                    // data
                    $data = [
                        'csrfTokenHidden=' . isset($this->State["csrfTokenHidden"]) ? $this->State["csrfTokenHidden"] : null,
                        'artifact=' . $artifact,
                    ];
                }// if ($this->AccountFields['Login3'] == 'Bankamericard')
                else {// WorldPoints
                    $this->logger->notice("WorldPoints: post next form");
                    // for cookies
//                    $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/skwValidateSafepass.go?callback=jQuery15203122546840321433_".time().date("B"));
                    // post URL
                    $postURL = "https://secure.bankofamerica.com/login/sign-in/skwValidateSafepass.go?callback=jQuery15203122546840321433_" . time() . date("B");
                    // data
                    $data = [
                        'action=skwValidateSafepass',
                        'artifact=' . $artifact,
                    ];
                }// else {// WorldPoints
                $this->http->PostURL($postURL, implode("&", $data));
                // notifications
//                $this->sendNotification("bankofamerica (".$this->AccountFields['Login3']."). SafePass Code was entered");
                // enter password
                if ($this->AccountFields['Login3'] == 'Bankamericard') {
                    $this->logger->notice("Bankamericard: entering password");
                    $this->enteringPasswordBankamericard();
                } else {// WorldPoints
                    $this->logger->notice("WorldPoints: entering password");
                    $this->parseSiteKey();

                    if (!$this->enterPassword($this->State["RSAKey"], $this->State["onlineId"])) {
                        $this->logger->error("enter password failed, current url: " . $this->http->currentUrl());

                        return false;
                    }// if (!$this->enterPassword($this->State["RSAKey"]))
                }// else {// WorldPoints

                return true;
            }// if (isset($this->Answers[$this->Question]))
            else {
                $this->logger->error(">>> SafePass Code is not found");
            }
        }// if ($this->Question == self::SAFE_PASS_CODE_QUESTION)
        else {
            $this->logger->notice("ProcessStep (WorldPoints)");
            $this->logger->debug("state:");
            $this->logger->debug(var_export($this->State, true), ['pre' => true]);

            if ($this->Question == self::ONE_TIME_CODE_QUESTION) {
                $this->sendAnswer($this->Question, $this->Answers[$this->Question], $this->State["acwEncryptKey"]);

                return true;
            } else {
                unset($this->Answers[self::ONE_TIME_CODE_QUESTION]);

                if ($this->IsLoggedIn()) {
                    $this->logger->debug("yes, already logged in");

                    return true;
                } else {
                    $this->logger->debug("not logged in, loading login form");

                    if ($this->LoadLoginForm()) {
                        $this->logger->debug("form loaded, logging in");

                        return $this->Login();
                    } else {
                        $this->logger->error("failed to load login form");
                    }
                }
            }

            return false;
        }

        return true;
    }

    public function enteringPasswordBankamericard()
    {
        $this->logger->notice(__METHOD__);
        // entering password
        if ($this->http->ParseForm("EnterOnlineIDForm") || $this->http->ParseForm("ConfirmSitekeyForm")) {
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $this->http->PostForm();
            $this->checkErrorsOfBankamericard();
        }// if ($this->http->ParseForm("ConfirmSitekeyForm"))
    }

    public function ParseBankamericard()
    {
        if ($this->http->FindPreg("/You(?: have|'ve) been previewing our new Accounts Overview page. Would you like to return to the standard version/ims")
            || $this->http->FindPreg("/This is your new Accounts Overview page. Would you like to go to the standard view/ims")
            || ($this->http->FindPreg("/What do you think of the new Accounts Overview\?/ims") && stristr($this->http->FindSingleNode("//h1[contains(text(), 'Accounts Overview')]/following-sibling::div[2]", null, true, "/([^<\-]+)/ims"), 'Update Profile'))
            || $this->http->FindSingleNode("//h1[contains(text(), 'Accounts Overview') or contains(text(), 'Resumen de cuentas')]/following-sibling::div[contains(@class, 'profile-section')]", null, true, "/(?:Update Contact Settings Page \| Update Profile \| Security Center|Actualizar página de configuración de contacto \| Actualizar perfil \| Centro de Seguridad|Update Profile \| Security Center|Actualizar perfil \| Centro de Seguridad)/ims")) {
            $this->logger->notice("New design");
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\"customerName\"\s*:\s*\"([^\"]+)/")));

            $links = $this->http->FindNodes("//a[contains(@name, '_details')]/@href");
            $this->logger->debug("Total nodes found: " . (count($links)));
            $links = array_unique($links);
            $this->logger->debug("Total unique nodes found: " . (count($links)));

            if ((count($links) > 0) && !empty($this->Properties['Name'])) {
                $this->SetBalanceNA();
            }

            // Detected cards
            $allDetectedCards = $this->http->XPath->query("//a[contains(@name, '_details')]");
            $this->logger->debug("Total {$allDetectedCards->length} cards were found");

            for ($i = 0; $i < $allDetectedCards->length; $i++) {
                $code = trim($this->http->FindPreg('/\-\s*(\d+)/ims', false, Html::cleanXMLValue($allDetectedCards->item($i)->nodeValue)));

                if (!isset($code)) {
                    $code = $this->http->FindPreg('/\s+(\d{4})$/ims', false, Html::cleanXMLValue($allDetectedCards->item($i)->nodeValue));
                }

                if (empty($code)) {
                    $code = preg_replace(['/\s/', '/-/', '/_/'], '', Html::cleanXMLValue($allDetectedCards->item($i)->nodeValue));
                }
                $displayName = Html::cleanXMLValue($allDetectedCards->item($i)->nodeValue);

                if (!empty($displayName) && !empty($code)) {
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;

                    if (strstr($displayName, 'Alaska Airlines')) {
                        $cardDescription = C_CARD_DESC_ALASKA_AIR;
                    }
                    $this->AddDetectedCard([
                        "Code"            => 'bankofamericaBankamericard' . $code,
                        "DisplayName"     => $displayName,
                        "CardDescription" => $cardDescription,
                    ]);
                }// if (!empty($displayName) && !empty($code))
            }// for ($i = 0; $i < $allDetectedCards->length; $i++)
        } else {
            $this->logger->notice("Old design");
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h1[contains(text(), 'Accounts Overview')]/following-sibling::div[2]", null, true, "/([^<\-]+)/ims")));

            $links = $this->http->FindNodes("//div[contains(@class, 'account-row')]/div/div[contains(@class, 'image-account')]/a[@id]/@href");
            $this->logger->debug("Total nodes found: " . (count($links)));
            $links = array_unique($links);
            $this->logger->debug("Total unique nodes found: " . (count($links)));

            // Detected cards
            $allDetectedCards = $this->http->XPath->query("//div[contains(@class, 'account-row')]/div/div[contains(@class, 'image-account')]");
            $this->logger->debug("Total {$allDetectedCards->length} cards were found");

            for ($i = 0; $i < $allDetectedCards->length; $i++) {
                $code = trim($this->http->FindSingleNode("a", $allDetectedCards->item($i), true, '/\-\s*(\d+)/ims'));

                if (!isset($code)) {
                    $code = $this->http->FindSingleNode("a", $allDetectedCards->item($i), true, '/\s+(\d{4})$/ims');
                }

                if (empty($code)) {
                    $code = preg_replace(['/\s/', '/-/', '/_/'], '', $this->http->FindSingleNode("a", $allDetectedCards->item($i)));
                }
                $displayName = CleanXMLValue($allDetectedCards->item($i)->nodeValue);

                if (!empty($displayName) && !empty($code)) {
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;

                    if (strstr($displayName, 'Alaska Airlines')) {
                        $cardDescription = C_CARD_DESC_ALASKA_AIR;
                    }
                    $this->AddDetectedCard([
                        "Code"            => 'bankofamericaBankamericard' . $code,
                        "DisplayName"     => $displayName,
                        "CardDescription" => $cardDescription,
                    ]);
                }// if (!empty($displayName) && !empty($code))
            }// for ($i = 0; $i < $allDetectedCards->length; $i++)

            // Credit card services will be back soon
            if ($message = $this->http->FindSingleNode("(//strong[contains(text(), 'Credit card services will be back soon')])[1]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ((count($links) == 0) && !empty($this->Properties['Name'])) {
                // Credit Card details are unavailable while we update our systems
                if ($message = $this->http->FindSingleNode("(//div[contains(text(), 'Credit Card details are unavailable while we update our systems')])[1]")) {
                    $this->ErrorCode = ACCOUNT_WARNING;
                    $this->ErrorMessage = $message;
                }
                // For your protection, we've placed a hold on this account.
                if ($message = $this->http->FindPreg("/For your protection, we\&\#39;ve placed a hold on this account\./")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // There was a problem processing your request
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'There was a problem processing your request.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }

            if (!empty($this->Properties['Name'])
                && (count($this->http->FindNodes("//a[contains(@id, 'Checking')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'CHECKING')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'checking')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Loan')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Adv Tiered Interest Chkg')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Alaska Airlines')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Amtrak World MasterCard')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Asiana American Express Card')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Automobile Loan')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Bank of America')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'BankAmericard Better Balance Rewards')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'BankAmericard Platinum Plus')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Regular Savings')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'eBanking')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Home Equity Line')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Line of Credit')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Melaleuca Platinum Plus MasterCard')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Mortgage')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'MSC Corporation')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Spirit Airlines World MasterCard')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Spirit Airlines Platinum Plus MasterCard')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'University of Central Florida Alumni Association')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Virgin Atlantic')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'Visa')]/@href")) > 0
                    || count($this->http->FindNodes("//a[contains(@id, 'kdog')]/@href")) > 0
                    // hard code
                    || count($this->http->FindNodes("//a[contains(@id, 'gladys alaska')]/@href")) > 0)) {
                $this->SetBalanceNA();
            }
        }

        $benefitSubAccounts = [];

        foreach ($links as $link) {
            if (stristr($link, 'avaScript:void')) {
                $this->logger->debug("Skip bad link");

                continue;
            }
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            $xpath = "//div[@id = 'skip-to-h1']//span[contains(@class, 'TL_NPI_AcctName')]";
            $displayName = $this->http->FindSingleNode($xpath);

            if (!isset($displayName)) {
                $xpath = "//div[@id = 'skip-to-h1']//span[contains(@class, 'TL_NPI_L2')]";
                $displayName = $this->http->FindSingleNode($xpath);
            }

            if (isset($displayName)) {
                $this->logger->info($displayName, ['Header' => 3]);
            }
            $code = $this->http->FindSingleNode($xpath, null, true, '/-\s*(\d+)/ims');

            if (!isset($code)) {
                $code = $this->http->FindSingleNode($xpath, null, true, '/\s+(\d{4})$/ims');
            }

            if (!isset($code)) {
                $code = preg_replace(['/\s/', '/-/', '/_/'], '', $this->http->FindSingleNode($xpath));
            }
            $balance = $this->http->FindSingleNode("//div[contains(text(), 'Total Cash Rewards:')]/strong", null, true, '/([\-\d\.\,]+)/ims');

            if (isset($balance)) {
                $currency = '$';
            } else {
                $currency = '';
            }

            if (!isset($balance)) {
                $balance = $this->http->FindSingleNode("//div[contains(text(), 'Total Points:')]/strong", null, true, '/([\-\d\.\,]+)/ims');
            }
            // WorldPoints
            if (!isset($balance)) {
                $balance = $this->http->FindSingleNode("//p[contains(text(), 'Total Rewards:')]/following-sibling::span[1]", null, true, '/([\-\d\.\,]+)/ims');
            }

            if (isset($displayName, $code, $balance)) {
                $subAccount = [
                    'Code'        => 'bankofamericaBankamericard' . $code,
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                    'Currency'    => $currency,
                ];
                $rewards = $this->http->FindSingleNode("//a[@name = 'redeemInternally']/@href");
                $this->logger->notice("Loading details...");
                $this->http->NormalizeURL($rewards);
                $this->http->GetURL($rewards);
                // Pending Points
                $subAccount["PendingRewards"] = $this->http->FindSingleNode("//p[contains(text(), 'Pending')]/following-sibling::p[1] | //p[a[contains(text(), 'Pending')]]/following-sibling::p[1]", null, true, '/\(?([\$\-\s\,\.\d]+)/ims');
                // Earned
                $subAccount["Earned"] = $this->http->FindSingleNode("//p[contains(text(), 'Earned')]/following-sibling::p[1] | //p[a[contains(text(), 'Earned')]]/following-sibling::p[1]", null, true, '/\(?([\$\-\s\,\.\d]+)/ims');
                // Redeemed
                $subAccount["Redeemed"] = $this->http->FindSingleNode("//p[contains(text(), 'Redeemed')]/following-sibling::p[1] | //p[a[contains(text(), 'Redeemed')]]/following-sibling::p[1]", null, true, '/\(?([\$\-\s\,\.\d]+)/ims');
                // Transferred
                $subAccount["Transferred"] = $this->http->FindSingleNode("//p[contains(text(), 'Transferred')]/following-sibling::p[1] | //p[a[contains(text(), 'Transferred')]]/following-sibling::p[1]", null, true, '/\(?([\$\-\s\,\.\d]+)/ims');

                // View rewards & benefits  // refs #16706
                if ($this->http->FindPreg("/View rewards & benefits/")) {
                    $rewardsPage = $this->http->FindPreg("/&adx=([^\&]+)/");
                }

                // Loading page with expiration dates
                if ($balance > 0) {
                    $this->logger->notice("Find expiration date...");

                    if ($expPage = $this->http->FindSingleNode("//a[contains(@href, 'exprSchd')]/@href")) {
                        $this->logger->debug("Loading page with expiration dates");
                        $this->http->NormalizeURL($expPage);
                        $this->http->GetURL($expPage);
                    }// if ($expPage = $this->http->FindSingleNode("//a[contains(@href, 'exprSchd')]/@href"))
                    $exp = $this->http->XPath->query("//table[contains(@summary, 'they will expire')]//tr[td]");
                    $this->logger->debug("Total {$exp->length} expiration dates were found");

                    for ($i = 0; $i < $exp->length; $i++) {
                        $date = "01/" . $this->http->FindSingleNode("td[2]", $exp->item($i));
                        $date = $this->ModifyDateFormat($date);
                        $expiringPoints = $this->http->FindSingleNode("td[1]", $exp->item($i));
                        $this->logger->debug("date: " . $date);

                        if (($d = strtotime($date)) && $expiringPoints > 0) {
                            $subAccount['ExpirationDate'] = $d;
                            $subAccount['PointsToExpire'] = $expiringPoints;

                            break;
                        }// if (($d = strtotime("01/".$date)) && $expiringPoints > 0)
                    }// for ($i = 0; $i < $exp->length; $i++)
                }// if ($balance > 0)
                $subAccounts[] = $subAccount;
                $this->AddDetectedCard([
                    "Code"            => 'bankofamericaBankamericard' . $code,
                    "DisplayName"     => $displayName,
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ]);

                if (!empty($rewardsPage)) {
                    $this->logger->notice("View rewards & benefits...");
                    $this->logger->info("Airline Incidental Travel Credit: {$displayName}", ['Header' => 3]);
                    $data = [
                        "source"              => "rwd",
                        "adx"                 => $rewardsPage,
                        "returnSiteIndicator" => "GAIMW",
                        "target"              => "ATT",
                        "vendorURL"           => "https%3A%2F%2Fwww.managerewardsonline.bankofamerica.com%2FREWARDSapp%2Fpremium%3Forigin%3DBORNEO%26locale%3Den%26mc%3Dpremium",
                    ];
                    $browser = clone $this->http;
                    $this->http->brotherBrowser($browser);
                    $browser->PostURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go", $data);

                    if ($browser->ParseForm("postform")) {
                        $browser->PostForm();

                        if ($browser->ParseForm(null, 1, true, "//form[contains(@action, 'page/sitekey')]")) {
                            $browser->PostForm();
                        }

                        if ($location = $browser->FindPreg("/this.location = \"([^\"]+)/")) {
                            sleep(2);
                            $browser->GetURL($location);
                            // Credit
                            $creditValue = $browser->FindSingleNode('//div[@class = "aic__credit__details__amount"]', null, true, self::BALANCE_REGEXP_EXTENDED);
                            $feeCreditValue = $browser->FindSingleNode('//div[contains(@class, "aic__credit__gauge__labels")]/span[@class = "pull-right"]', null, true, self::BALANCE_REGEXP_EXTENDED);
                            $benefitBalance = $feeCreditValue - $creditValue;
                            $this->logger->debug("Remaining $" . $feeCreditValue . " Airline Incidental Travel Credit: {$benefitBalance}");

                            if (isset($feeCreditValue, $creditValue) && $benefitBalance > 0) {
                                $benefitDisplayName = "Remaining $" . $feeCreditValue . " Airline Incidental Travel Credit";
                                $benefitSubAccounts[] = [
                                    'Code'           => 'amexAirlineFeeCredit' . $code,
                                    'DisplayName'    => $benefitDisplayName,
                                    'Balance'        => $benefitBalance,
                                    'Currency'       => "$",
                                    'ExpirationDate' => strtotime("31 Dec"),
                                ];
                                $this->logger->debug("Adding subAccount...");
                                $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);
                            }// if (isset($feeCreditValue, $creditValue) && $benefitBalance > 0)
                            elseif (!isset($feeCreditValue) || !isset($creditValue)) {
                                $this->sendNotification("bankofamerica - refs #16706. Airline Incidental Travel Credit broken");
                            }
                        }// if ($location = $browser->FindPreg("/this.location = \"([^\"]+)/"))
                        else {
                            $this->sendNotification("bankofamerica - refs #16706. Airline Incidental Travel Credit broken");
                        }
                    }// if ($browser->ParseForm("postform"))
                }// if (!empty($rewardsPage))
                unset($rewardsPage);
            }// if (isset($displayName, $code, $balance))
        }// foreach ($links as $link)

        if (isset($subAccounts)) {
            //# Set Sub Accounts
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            //# SetBalance n\a
            $this->SetBalanceNA();
            //# Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))

        // For your protection, we've placed a hold on this account.
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (($message = $this->http->FindSingleNode('//*[contains(text(), "For your protection, we\'ve placed a hold on this account.") or contains(text(), "For your protection, we\'ve placed a hold on this card because of some unusual activity.")]'))
                && $this->http->FindSingleNode("//a[@name = 'Continue']/@href")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We're sorry, but we are temporarily unable to show credit card account information. Please try again later.
            if ($message = $this->http->FindPreg('/We&\#39;re sorry, but we are temporarily unable to show credit card account information\.\s*Please try again later\./')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // For your protection, we've placed a hold on this card because of some unusual activity.
            if ($message = $this->http->FindSingleNode('//b[contains(text(), "For your protection, we\'ve placed a hold on this card because of some unusual activity.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindPreg('/<b>(For your protection, we&#39;ve placed a hold on this card because of some unusual activity\.)<\/b>/')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // For your protection, we've placed a restriction on a card associated with this account because of some unusual activity.
            if ($message = $this->http->FindSingleNode('//b[contains(text(), "For your protection, we\'ve placed a restriction on a card associated with this account because of some unusual activity.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // refs #14494
        $this->logger->info('FICO® Score', ['Header' => 3]);
        $ficoScore = "/myaccounts/brain/redirect.go?target=creditScore";
        $this->http->NormalizeURL($ficoScore);
        $this->http->GetURL($ficoScore);
        // FICO® SCORE
        $fcioScore = $this->http->FindSingleNode("//input[@id = 'your_score']/@value");
        // FICO Score updated on
        $fcioUpdatedOn = $this->http->FindPreg("/updated_date:\s*'([^\']+)/");

        if ($fcioScore && $fcioUpdatedOn) {
            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                    if (in_array($key, ['Code', 'DisplayName'])) {
                        continue;
                    } elseif ($key == 'Balance') {
                        $this->SetBalance($value);
                    } elseif ($key == 'ExpirationDate') {
                        $this->SetExpirationDate($value);
                    } else {
                        $this->SetProperty($key, $value);
                    }
                }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                unset($this->Properties['SubAccounts']);
            }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                "Code"               => "bankofamericaBankamericardFICO",
                "DisplayName"        => "FICO® Bankcard Score 8 (TransUnion)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)

        // Airline Incidental Travel Credit   // refs #16706
        foreach ($benefitSubAccounts as $benefitSubAccount) {
            $this->AddSubAccount($benefitSubAccount);
        }

        // refs #14648
        $this->logger->info('Zip Code', ['Header' => 3]);
        $this->logger->debug('ZipCodeParseDate: ' . ($this->State['ZipCodeParseDate'] ?? null));
        $this->logger->debug('Time: ' . strtotime("-1 month"));

        if (!isset($this->State['ZipCodeParseDate']) || $this->State['ZipCodeParseDate'] < strtotime("-1 month")) {
            $profile = "/customer/manageContacts/view-profile.go?returnSiteIndicator=GAIMW&isHEMIModPilot=Y&request_locale=en-us&source=add";
            $this->http->NormalizeURL($profile);
            $this->http->GetURL($profile);
            $this->SetProperty("ZipCode", $this->http->FindSingleNode("(//span[@class = 'accInfoZip'])[1]"));
            $this->State['ZipCodeParseDate'] = time();
        }
    }

    public function Parse()
    {
        if ($this->AccountFields['Login3'] == 'Bankamericard') {
            $this->logger->notice("Bankamericard");
            $this->ParseBankamericard();

            return;
        }

        $this->Properties['SubAccounts'] = [];

        if ($iFrame = $this->http->FindSingleNode("//iframe[contains(@src, 'https://secure.bankofamerica.com/myaccounts/sec-redirect')]/@src")) {
            $this->logger->notice(">>> Redirect to account");
            $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
            $this->logger->debug("host: $host");
            $wicketAjaxGet = $this->http->FindPreg("/wicketAjaxGet\(\'([^\']+)/");
            $this->logger->debug(">>> wicket: " . $wicketAjaxGet);
            $this->http->GetURL("https://{$host}/RWDapp/sitekey" . $wicketAjaxGet . "&random=0." . rand(111, 999) . time() . date("B"));

            if ($link = $this->http->FindPreg("/this\.location\s*=\s*\'([^\']+)/ims")) {
                $this->http->GetURL($link);

                if (stripos($this->http->Response['body'], "var tHref = '/RWDapp/ns/howitworks?")) {
                    $this->logger->notice("redirecting to how it works");
                    $query = parse_url($this->http->currentUrl(), PHP_URL_QUERY);
                    $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
                    $this->logger->debug("host: $host");
                    parse_str($query, $params);
                    unset($params['assertion'], $params['tks']);
                    $params['mc'] = 'PWRRWD';
                    //					$this->http->GetURL('https://www.managerewardsonline.com/RWDapp/ns/howitworks?'.ImplodeAssoc('=', '&', $params, true));
                    $this->http->GetURL('https://' . $host . '/RWDapp/ns/howitworks?' . ImplodeAssoc('=', '&', $params, true));
                }
            }// if ($link = $this->http->FindPreg("/this\.location\s*=\s*\'([^\']+)/ims"))
        }// if ($iFrame = $this->http->FindSingleNode("//iframe[contains(@src...

        if ($this->http->ParseForm("accountSelectorForm")) {
            $indexes = $this->http->FindNodes("//form[@id = 'accountSelectorForm']//input[@name = 'aidx']/@value");
            $names = $this->http->FindNodes("//form[@id = 'accountSelectorForm']//input[@name = 'aidx']/following::span[1]");

            if (empty($names)) {
                $names = $this->http->FindNodes("//form[@id = 'accountSelectorForm']//input[@name = 'aidx']/following::label[1]");
            }
            // for loading of next cards
            $mainPage = $this->http->currentUrl();

            if (count($indexes) > 0 && count($indexes) == count($names)) {
                foreach ($indexes as $key => $idx) {
                    $name = $names[$key];
                    $this->logger->notice("loading card $idx: $name");
                    $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
                    $this->logger->debug("host: $host");

                    if ($idx != 1) {
                        $this->http->GetURL($mainPage);
                    }
                    // fix for caribbeanvisa
                    if ($this->AccountFields["ProviderCode"] == 'caribbeanvisa') {
                        $this->http->GetURL("https://{$host}/RMSapp/Ctl/home?aidx=" . $idx);
                    } else {
                        $this->http->GetURL("https://{$host}/RWDapp/home?aidx=" . $idx);
                    }
                    $this->parseAccount($name);
                }
            }
        } else {
            $this->parseAccount('Main');
        }

        if (count($this->Properties['SubAccounts']) > 0 || $this->http->FindPreg("/You do not have an eligible rewards credit card account to access this website/ims")) {
            $this->SetBalanceNA();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//title[text() = 'Rewards | Error']")) {
                throw new CheckException("System error. Please try again later", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/

            if ($this->http->FindSingleNode("//title[text() = 'WorldPoints Rewards | Error']")) {
                throw new CheckException("Your request cannot be completed. You do not have an eligible rewards credit card account to access this website.", ACCOUNT_PROVIDER_ERROR);
            }/*checked*/
            // Points currently not available (fix for caribbeanvisa)
            if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Points currently not available')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Site temporarily unavailable
            if ($message = $this->http->FindSingleNode("//div[@id = 'container']//h1[contains(text(), 'Site temporarily unavailable')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function parseAccount($name)
    {
        $code = 'bankofamericaWorldPoints' . str_replace([' ', '-'], '', $name);
        // detected cards
        if ($name != "Main") {
            $this->AddDetectedCard([
                "Code"            => $code,
                "DisplayName"     => $name,
                "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
            ]);
        }
        $balance = $this->http->FindSingleNode("//*[@id='accountminibox']");

        if (!isset($balance)) {
            $this->logger->debug("New design");
            $balance = $this->http->FindSingleNode("(//td[contains(text(), 'Total available points:')]/following-sibling::td[1])[1]");

            if (!isset($balance)) {
                $balance = $this->http->FindSingleNode("(//p[contains(text(), 'Total available points:')]/strong)[1]");
            }
            $this->logger->debug("Balance: " . $balance);

            if (!isset($balance)) {
                $balance = $this->http->FindPreg("/Total available points:\s*<[^>]+>([^<]+)/ims");
            }
            $this->logger->debug("Balance: " . $balance);
        }

        if (isset($balance)) {
            $balance = preg_replace('/\$\{[^\}]*}/ims', '', $balance);
            $balance = str_replace(",", "", $balance);
            $account = ['Balance' => $balance, 'DisplayName' => $name, 'Code' => $code];
            // detected cards
            if ($name != 'Main') {
                $this->AddDetectedCard([
                    "Code"            => $code,
                    "DisplayName"     => $name,
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ]);
            }
            // Balance - Points Balance
            $pointsBalance = $this->http->FindSingleNode("//tr[@id='pointsbalance']/td[1]/span[1]");

            if (!isset($pointsBalance)) {
                $pointsBalance = $this->http->FindSingleNode("//p[@id='pointsbalance']/span[1]");
            }

            if (!isset($pointsBalance)) {
                $pointsBalance = $this->http->FindSingleNode("(//td[contains(text(), 'Total available points:')]/following-sibling::td[1])[2]");
            }
            // fix for caribbeanvisa
            if (!isset($pointsBalance)) {
                $pointsBalance = $this->http->FindSingleNode("//td[strong[contains(text(), 'Points&nbsp;Balance')]]/preceding-sibling::td[1]");
            }

            if (isset($pointsBalance)) {
                $account["PointsBalance"] = $pointsBalance;
            }
            // Redeemed since last statement
            $sinceLast = $this->http->FindSingleNode("//tr[@id='pointsredeemed']/td[1]");

            if (!isset($sinceLast)) {
                $sinceLast = $this->http->FindSingleNode("//p[@id = 'pointsredeemed']/span[1]");
            }

            if (!isset($sinceLast)) {
                $sinceLast = $this->http->FindSingleNode("//td[contains(text(), 'Redeemed since last statement')]/following-sibling::td[1]");
            }
            // fix for caribbeanvisa
            if (!isset($sinceLast)) {
                $sinceLast = $this->http->FindSingleNode("//td[strong[contains(text(), 'Redeemed')]]/preceding-sibling::td[1]");
            }

            if (isset($sinceLast)) {
                $account["SinceLast"] = $sinceLast;
            }
            // Pending rewards
            $sinceLast = $this->http->FindSingleNode("//tr[@id='pointspending']/td[1]");

            if (!isset($sinceLast)) {
                $sinceLast = $this->http->FindSingleNode("//p[@id='pointspending']/span[1]");
            }

            if (!isset($sinceLast)) {
                $sinceLast = $this->http->FindSingleNode("//td[contains(text(), 'Pending rewards:')]/following-sibling::td[1]");
            }

            if (isset($sinceLast)) {
                $account["PendingRewards"] = $sinceLast;
            }
            // expiration date
            $exp = $this->parseExpiration();

            if ($exp['ExpDateFound']) {
                $account["ExpirationDate"] = $exp['ExpirationDate'];
                $account['PointsToExpire'] = $exp['PointsToExpire'];
            }

            $this->Properties['SubAccounts'][] = $account;
        }// if (isset($balance))
        //$this->http->GetURL("https://www.managerewardsonline.com/RWDapp/signoff");
    }

    public function parseExpiration()
    {
        $exp = [
            'ExpDateFound' => false,
        ];
        $this->logger->notice("parsing expiration dates");
        $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $this->logger->debug("host: $host");
        $this->http->GetURL("https://{$host}/RWDapp/ns/pointsdetails?mc=barrwd&locale=en_US");
        $wicketAjaxGet = $this->http->FindPreg("/wicketAjaxGet\(\'\.?\.?\/?([^\']+)/");
        $this->logger->debug(">>> wicket: " . $wicketAjaxGet);

        if (!isset($wicketAjaxGet)) {
            $this->logger->notice("Ajax url is not found");

            return false;
        }
        $this->http->GetURL("https://{$host}/RWDapp/sitekey" . $wicketAjaxGet . "&random=0." . rand(111, 999) . time() . date("B"));
        // Expiring Points
        $nodes = $this->http->XPath->query("//th[contains(text(), 'Month of expiration') or contains(text(), 'Month of Expiration')]/ancestor::table[1]//tr[td]");
        $this->logger->debug("Total Expiration Dates found " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $date = $this->http->FindSingleNode("td[2]", $nodes->item($i));
                $expiringPoints = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                $this->http->Log("date: " . $date);

                if (preg_match("/(\w+)\s(\d{4})/ims", $date, $matches)) {
                    $d = strtotime($matches[1] . " 1, " . $matches[2]);

                    if ((($d !== false && !isset($exp['ExpirationDate'])) || ($d < $exp['ExpirationDate']))
                        && $expiringPoints > 0) {
                        $exp['ExpDateFound'] = true;
                        $exp['ExpirationDate'] = $d;
                        $exp['PointsToExpire'] = $expiringPoints;
                    }// if (($d !== false && !$exp) || ($d < $exp))
                }// if (preg_match("/(\w+)\s(\d{4})/ims", $date, $matches))
            }// for ($i = 0; $i < $nodes->length; $i++)
        }// if ($nodes->length > 0)
        else {
            $this->logger->notice("Expiration Date isn't found");
        }

        return $exp;
    }

    protected function parseCaptcha($img = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$img) {
            $img = $this->http->FindSingleNode("//img[@id = 'imageText']/@src");
        }

        if (!$img) {
            return false;
        }
        $this->http->NormalizeURL($img);
        $file = $this->http->DownloadFile($img, "jpg");
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);
        unlink($file);

        return $captcha;
    }

    /*
        function LoginToBA(){
    //		$this->http->LogHeaders = true;
    //		$this->http-> veCookies();
    //		$this->http->setCookie("state", "AK", "safe.bankofamerica.com");
            $this->http->FilterHTML = false;
            $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/signOnScreen.go?screenMsg=&request_locale=en-us");
            $this->http->PostURL("https://secure.bankofamerica.com/login/sign-in/entry/signOn.go", array(
                "csrfTokenHidden" => $this->http->FindSingleNode("//input[@name = 'csrfTokenHidden']/@value"),
                "lpOlbResetErrorCounter" => "0",
                "lpPasscodeErrorCounter" => "0",
                "pm_fp" => "version%3D1%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%5F8%5F2%29%20applewebkit%2F537%2E4%20%28khtml%2C%20like%20gecko%29%20chrome%2F22%2E0%2E1229%2E94%20safari%2F537%2E4%7C5%2E0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010%5F8%5F2%29%20AppleWebKit%2F537%2E4%20%28KHTML%2C%20like%20Gecko%29%20Chrome%2F22%2E0%2E1229%2E94%20Safari%2F537%2E4%7CMacIntel%26pm%5Ffpsc%3D24%7C1680%7C1050%7C946%26pm%5Ffpsw%3D%26pm%5Ffptz%3D6%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D1%26pm%5Ffpco%3D1",
                "onlineId" => $this->AccountFields['Login'],
                "rembme" => "on",
            ));
            if($this->http->FindPreg('/DetectFlashVer/ims'))
                $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/signOn.go");
            //$question = $this->http->FindSingleNode("//label[@for = 'tlpvt-challenge-answer']");
            if(!$this->http->ParseForm("confirm-sitekey-form"))
                return false;
            $this->http->Form["f_variable"] = "TF1;015;;;;;;;;;;;;;;;;;;;;;;Mozilla;Netscape;5.0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010_8_2%29%20AppleWebKit/537.4%20%28KHTML%2C%20like%20Gecko%29%20Chrome/22.0.1229.94%20Safari/537.4;20030107;undefined;true;;true;MacIntel;undefined;Mozilla/5.0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010_8_2%29%20AppleWebKit/537.4%20%28KHTML%2C%20like%20Gecko%29%20Chrome/22.0.1229.94%20Safari/537.4;en-US;ISO-8859-1;secure.bankofamerica.com;undefined;undefined;undefined;undefined;true;false;1351007481530;6;Tue%20Jun%2007%202005%2021%3A33%3A44%20GMT+0700%20%28YEKT%29;1680;1050;;11.4;7.7.1;;;;5;-360;-420;Tue%20Oct%2023%202012%2021%3A51%3A21%20GMT+0600%20%28YEKT%29;24;1680;946;0;22;;;;;;Shockwave%20Flash%7CShockwave%20Flash%2011.4%20r402;;;;QuickTime%20Plug-in%207.7.1%7CThe%20QuickTime%20Plugin%20allows%20you%20to%20view%20a%20wide%20variety%20of%20multimedia%20content%20in%20web%20pages.%20For%20more%20information%2C%20visit%20the%20%3CA%20HREF%3Dhttp%3A//www.apple.com/quicktime%3EQuickTime%3C/A%3E%20Web%20site.;;;;;;;;;15;";
            $this->http->Form["password"] = $this->AccountFields['Pass'];
            $this->http->PostForm();
            return true;
            //return $this->Login("https://www.bankofamerica.com/");
        }

        function ParseFiles($filesStartDate){
            $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go?target=eccdocuments&request_locale=en-us&source=overview&fsd=y");
            if($this->http->FindSingleNode("//a[@id = 'anc-tabstrip-find_documentstab']") === null){
                if(!$this->LoginToBA())
                    return array();
                else
                    $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go?target=eccdocuments&request_locale=en-us&source=overview&fsd=y");
            }
            $this->http->PostURL("https://secure.bankofamerica.com/mycommunications/documents/filterAction.go", array(
                "acctSelected" => "All Accounts",
                "accntDropdown" => "All Accounts",
                "docItemSelected" => "All Documents",
                "docDropdown" => "All Documents",
                "dateRangeSelected" => "Past 12 months",
                "datesDropdown" => "Past 12 months",
                "sortOrder" => "D"
            ));
            $rows = $this->http->XPath->query("//table[@id = 'documentInboxModuleStatementTable']//tr[contains(@class, 'even') or contains(@class, 'odd')]");
            $this->http->Log("files rows: ".$rows->length);
            $result = array();
            foreach($rows as $row){
                $date = $this->http->FindSingleNode("td[1]", $row);
                $account = $this->http->FindSingleNode("td[2]", $row);
                $accountNumber = $this->http->FindSingleNode("td[2]", $row, false, '/(\d{4})$/ims');
                $filename = $this->http->FindSingleNode("td[3]/a/text()", $row);
                if(isset($date) && isset($account) && isset($filename)){
                    $d = strtotime($date);
                    if($d !== false && isset($filesStartDate) && $d < $filesStartDate)
                        continue;
                    $this->http->Log("file: $date / $account / $filename");
                    $this->http->ParseEncoding = false;
                    $this->http->ParseDOM = false;
                    $this->http->ParseForms = false;
                    $this->http->LogResponses = false;
                    $this->http->PostURL("https://secure.bankofamerica.com/mycommunications/documents/viewDownload.go", array(
                        "documentId" => $this->http->FindSingleNode(".//input[contains(@id, 'documentId')]/@value", $row),
                        "menu" => "5"
                    ));
                    if(strpos($this->http->Response['body'], '%PDF') === 0)
                        $result[] = array(
                            'AccountNumber' => $accountNumber,
                            'AccountName' => $account,
                            'FileDate' => strtotime($date),
                            'Name' => $filename,
                            'Extension' => 'pdf',
                            'Contents' => $this->http->LastResponseFile(),
                        );
                }
            }
            $this->http->Log(var_export($result, true));
            return $result;
        }
    */
}
