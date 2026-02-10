<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerAtt extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setMaxRedirects(10);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.att.com/my/#/login");

        $login = $this->AccountFields['Login'];
        $this->logger->debug("[Login]: {$login}");

        if (strstr($login, '(') && strstr($login, '-')) {
            $this->logger->debug("fixed login");
            $login = str_replace(['(', ')', '-', ' '], '', $login);
        }
        $this->logger->debug("[Login]: {$login}");

        $this->sendSensorData();

        $data = [
            "CommonData" => [
                "AppName" => "R-MYATT",
            ],
            "UserId"     => $login,
            "Password"   => $this->AccountFields['Pass'],
            "RememberMe" => "Y",
        ];
        $headers = [
            "Accept"         => "application/json",
            "Content-Type"   => "application/json",
            "X-Requested-By" => "MYATT",
            "UserId"         => $login,
        ];
        $this->http->PostURL("https://www.att.com/myatt/lgn/resources/unauth/login/tguard/authenticateandlogin", json_encode($data), $headers);

        return true;
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9166871.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,391043,2576746,1536,880,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.268455965134,794651288373,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.att.com/my/#/login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1589302576746,-999999,17001,0,0,2833,0,0,4,0,0,28FB028CA7D6BD44FA6AA9944D1D7FA8~-1~YAAQxGAZuFSRI75xAQAAh8PQCQNYFNxbM/SCH433sTORMdI7HXmLBWvxFyCsDwZ3uZjxmHJmTt0oJufcqSUlp8ohgVuEy5ABoy/6g9BMu8bTyfzDyur26zQgshVboBuJ4OFYEJK5hsLlTzgG2myWlwlVclGJ0RsLFtPeSy2yqGDRw23DfRmkl7aUo6e4ac7GQ564+1Su7bSkldcMZhspRLGrHSoH+2TzL/VosfvuvmYztiQXcO3Ba2fE3lFXaS6We0qJ+EbpckeTS58lkEXkgerUtGsbsyVB00MiL7JI6ZpKHuuXcoJx~-1~-1~-1,29694,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,12883672-1,2,-94,-118,75047-1,2,-94,-121,;5;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9166871.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.132 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,391043,2576746,1536,880,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8975,0.393380232196,794651288373,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.att.com/my/#/login-1,2,-94,-115,1,32,32,0,0,0,0,612,0,1589302576746,100,17001,0,0,2833,0,0,612,0,0,28FB028CA7D6BD44FA6AA9944D1D7FA8~-1~YAAQxGAZuFaRI75xAQAAGMfQCQNIgn7fBq0fO68xpHcvrHu91c77mTlQpGDz9LeAoPcRsZ11eZZW9vBDDmGmWLQuveW7TE7O2y+uIfbOVecCs7cw80iij7Sh0VEt5a0GExEYDY3Ko3vFWEDcNlQeHQMgwEYUBbugsmudCqPQ56Fwd39ukS7hdjlMtD1Ya+nSS4rrXtFpUp8ruXGm7CJ9+9DVAcAcBHUIJMBJX6N0Qgpwtks6BdOhBGpsKaRhIGnz2kvz0fhU/CPt+NVaXF5vhAR242IdLCYoMG2RyqqRhYN0DHNifp9Ay6yA76LXDxHO8fEyFQY=~-1~-1~-1,30509,24,-252924566,30261693-1,2,-94,-106,9,1-1,2,-94,-119,38,39,40,38,59,60,40,35,38,34,7,7,13,427,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,5584-1,2,-94,-116,12883672-1,2,-94,-118,79219-1,2,-94,-121,;2;7;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /*
         * A temporary system error prevents us from retrieving your account information right now.
         * Please try again later.
         */
        if ($message = $this->http->FindPreg("/A temporary system error prevents us from retrieving your account information right now. Please try again later\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently experiencing a temporary system error that prevents us from retrieving your account information.
        if ($message = $this->http->FindPreg("/We are currently experiencing a temporary system error that prevents us from retrieving your account information\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are enhancing your online experience, and myAT&T is temporarily unavailable. We apologize for the inconvenience.
        if ($message = $this->http->FindPreg("/We are enhancing your online experience, and myAT&T is temporarily unavailable. We apologize for the inconvenience\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Oops. You caught us with our website down. Excuse us while we make myAT&T even better.
         * Please come back Sunday, October 23, after 11 a.m. CT.
         * Thanks.
         */
        if ($message = $this->http->FindPreg("/Oops\. You caught us with our website down\. Excuse us while we make myAT\&amp;T even better\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The doors are opening. Our fastest way to order is att.com
        if ($this->http->FindSingleNode("//img[contains(@alt, 'The doors are opening. Our fastest way to order is att.com') or contains(@src, '//www.att.com/maintenance')]/@alt")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize that we are unable to complete your registration. Please try again at a later time.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize that we are unable to complete your registration. Please try again at a later time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.att.com/olam/loginAction.olamexecute?fromdlom=true";

        return $arg;
    }

    public function skipOffers()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('Checking for "Remind me later" link');
        //		if ($remindMeLaterLink = $this->http->FindSingleNode('//a[./img[contains(@src, "btn-remind-me-later") or contains(@src, "btn-remindmelater")]]/@href')) {
        if ($this->http->FindPreg('/alt=\"Remind Me Later\"/')
            && ($remindMeLaterLink = $this->http->FindPreg('/href=\"(remindMeLaterPromoUserResponse\.myworld\?response=remindLater[^\"]+)\"/'))) {
            $this->http->NormalizeURL($remindMeLaterLink);
            $this->logger->notice('Got it - ' . $remindMeLaterLink . ', clicking');
            $this->http->GetURL($remindMeLaterLink);
        } else {
            $this->logger->notice('None');
        }
        // Accept or Change Your New Access ID
        if ($changeLaterLink = $this->http->FindSingleNode('//a[contains(text(), "I\'ll change it later.")]/@href')) {
            $this->http->NormalizeURL($changeLaterLink);
            $this->logger->notice("Click \"I'll change it later\"");
            $this->http->GetURL($changeLaterLink);
        }
        // Make password resets easier with a wireless number
        if ($this->http->FindPreg("/(<h1>(?:Make password resets easier with a wireless number|Want an easy way to reset your password\?)<\/h1>)/")
            || $this->http->ParseForm("captureCbrCtnBean")) {
            $this->logger->notice("Skip reminder 'Make password resets easier with a wireless number'/ 'Want an easy way to reset your password?'");
            $this->http->FormURL = 'https://www.att.com/olam/captureCbrCtnInterceptonRemindLater.myworld';

            $this->http->SetInputValue("struts.token.name", "token");
            $this->http->SetInputValue("token", $this->http->FindPreg("/name=\"token\"[^>]+value=\"([^\"]+)/"));
            $this->http->SetInputValue("captureCbrCtnBean.selectedCbrCtn", $this->http->FindPreg("/name=\"captureCbrCtnBean.selectedCbrCtn\"[^>]+value=\"([^\"]+)/"));
            $this->http->SetInputValue("captureCbrCtnBean.selectedLinkedAccOrServices", $this->http->FindPreg("/name=\"captureCbrCtnBean.selectedLinkedAccOrServices\"[^>]+value=\"([^\"]+)/"));

            unset($this->http->Form['App_ID']);
            unset($this->http->Form['autoSuggest']);
            unset($this->http->Form['tabPressed']);
            unset($this->http->Form['q']);
            unset($this->http->Form['disp']);
            $this->http->PostForm();
        }
        // Terms and Conditions
        if ($changeLaterLink = $this->http->FindPreg('/href\s*=\s*"(remindMeLaterPromoUserResponse\.myworld\?response=remindLater(?:\&reportActionEvent=A_LGN_PROMO_REMINDLATER_SUB|\s*)[^\"]*)/')) {
            $this->http->NormalizeURL($changeLaterLink);
            $this->logger->notice("Click \"Remind Me Later\"");
            $this->http->GetURL($changeLaterLink);
        }
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 3, true);

        if ($this->http->FindPreg("/Status\":\"SUCCESS\"/")) {
            $this->http->setCookie("TATS-SS-TokenID", ArrayVal($response, 'TATS-SS-TokenID'), ".att.com");
            $this->http->setCookie("PD-ID", ArrayVal($response, 'PD-ID'), ".att.com");

            return true;
        }

        if ($this->http->FindPreg("/Status\":\"PARTIAL_SUCCESS\"/")) {
            $redirectUrl = ArrayVal($response, 'RedirectUrl', null);

            if ($redirectUrl) {
                $this->http->GetURL($redirectUrl);
                /*
                if ($this->http->FindSingleNode('
                    //p[contains(text(), "The system could not sign you in. Please enter your password and try again.")]
                    ')
                    && $this->http->ParseForm("login-form")
                ) {
                    $this->sendNotification("att. second form was sent");
                    $this->http->SetInputValue("phoneNumber", $this->http->FindSingleNode("//input[@id = 'login-phone-phone-number']/@value"));
                    $this->http->PostForm();
                }
                */

                // retries
                if ($this->http->currentUrl() == 'https://www.att.com/my/#/login') {
                    throw new CheckRetryNeededException(2, 10);
                }

                if ($this->http->FindSingleNode('//p[contains(text(), "The page you have requested does not exist.")]')) {
                    throw new CheckRetryNeededException(2, 10);
                }
                // The wireless number you entered is not associated with a valid AT&T PREPAID subscriber. Please check the number and try again.
                // The information provided is not valid. Please try again.
                if ($message = $this->http->FindSingleNode('
                        //p[contains(text(), "The wireless number you entered is not associated with a valid AT&T PREPAID subscriber. Please check the number and try again.")]
                        | //p[contains(text(), "The information provided is not valid. Please try again.")]
                    ')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // We seem to be experiencing system issues. Please try again later.
                // The system cannot sign you in at this time. Try again in 60 minutes.
                // The system cannot sign you in at this time. Call 611 for help.
                if ($message = $this->http->FindSingleNode('
                    //p[contains(text(), "We seem to be experiencing system issues. Please try again later.")]
                    | //p[contains(text(), "The system cannot sign you in at this time. Try again in 60 minutes.")]
                    | //p[contains(text(), "The system cannot sign you in at this time. Call 611 for help.")]
                    ')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return true;
            }// if ($redirectUrl)
            else {
                $this->http->SetInputValue("cancelURL", "https://www.att.com/olam/loginAction.olamexecute?fromdlom=true");
                $this->http->SetInputValue("flow_ind", "LGN");
                $this->http->SetInputValue("isSlidLogin", "true");
                $this->http->SetInputValue("lang", "en");
                $this->http->SetInputValue("myATTIntercept", "true");
                $this->http->SetInputValue("persist", "y");
                $this->http->SetInputValue("remember_me	", "Y");
                $this->http->SetInputValue("rootPath", "/olam/English");
                $this->http->SetInputValue("source", "MYATT2FA");
                $this->http->SetInputValue("urlParameters", "tGuardLoginActionEvent=LoginWidget_Login_Sub&friendlyPageName=myATT Login RWD Pg&lgnSource=olam");
                $this->http->SetInputValue("vhname", "www.att.com");
                $this->http->SetInputValue("loginURL", "https://www.att.com/olam/IdentityFailureAction.olamexecute");
                $this->http->SetInputValue("targetURL", "https://cprodx.att.com/TokenService/nxsATS/WATokenService?appID=m14910&returnURL=https%3A%2F%2Fwww.att.com%2Folam%2FIdentitySuccessAction.olamexecute%3FisReferredFromRWD%3Dtrue");

                // (111) 321-0000
                if ($this->http->FindPreg("/\(\d+\)\s*\d+\-\d+/", false, $this->AccountFields['Login'])) {
                    $this->AccountFields['Login'] = preg_replace("/[^\d]+/", '', $this->AccountFields['Login']);
                }

                $this->http->SetInputValue("userid", $this->AccountFields['Login']);
                $this->http->SetInputValue("password", $this->AccountFields['Pass']);

                $this->http->FormURL = "https://cprodmasx.att.com/commonLogin/igate_wam/multiLogin.do";

                if (!$this->http->PostForm()) {
                    return $this->checkErrors();
                }
                // Multiple Accounts Found
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Multiple Accounts Found')]")
                    || $this->http->FindSingleNode("//h1[contains(text(), 'Multiple User IDs With Same Password')]")) {
                    $this->logger->notice("Multiple Accounts Found -> go to AT&T account");

                    if (!$this->http->ParseForm("form")) {
                        return false;
                    }
                    $this->http->SetInputValue("optionsRadios", $this->http->FindSingleNode("//input[@id = 'GoPhonePaymentCenter' or @id = 'wirelessAcc']/@value"));
                    $this->http->PostForm();
                } elseif (
                    // Merge Accounts
                    $this->http->FindSingleNode("//h1[contains(text(), 'Merge Accounts')]")
                    // Reminder: You created a new password
                    || $this->http->FindSingleNode("//h1[contains(text(), 'Reminder: You created a new password')]")
                ) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->skipOffers();

                // Just a little housekeeping...
                if ($this->http->FindSingleNode("//i[contains(., 'You must fill out all fields to continue')]")) {
                    $this->throwProfileUpdateMessageException();
                }
            }
        }//if ($this->http->FindPreg("/Status\":\"PARTIAL_SUCCESS\"/"))

        // The User ID and password combination you entered doesn't match any entries in our files.
        if ($this->http->FindPreg("/TGuardResponseCode\"\s*:\s*\"(?:E.01.03.050|E.01.03.055|E.01.03.015)\",/")
            || $this->http->FindPreg("/WidgetErrorCode\"\s*:\s*\"(?:E.01.03.050_1)\",/")) {
            throw new CheckException("The User ID and password combination you entered doesn't match any entries in our files.", ACCOUNT_INVALID_PASSWORD);
        }
        // We can't find a match for that User ID and password combination. First time logging in to your account?Â Register for online account access.
        if ($this->http->FindPreg("/TGuardResponseCode\"\s*:\s*\"E.00.11.300\",/")) {
            throw new CheckException("We can't find a match for that User ID and password combination. First time logging in to your account?Â Register for online account access.", ACCOUNT_INVALID_PASSWORD);
        }
        // For your security, your AT&T Access ID has a temporary protection lock.
        if ($this->http->FindPreg("/TGuardResponseCode\"\s*:\s*\"(?:E.01.01.420|E.01.01.410)\",/")) {
            throw new CheckException("For your security, your AT&T Access ID has a temporary protection lock.", ACCOUNT_LOCKOUT);
        }
        // We had to lock your account.
        if ($this->http->FindPreg("/TGuardResponseCode\"\s*:\s*\"(?:E.01.03.016)\",/")) {
            throw new CheckException("We had to lock your account.", ACCOUNT_LOCKOUT);
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (
            stristr($currentUrl, 'https://www.att.com/my/#/passthrough/')
            || stristr($currentUrl, 'https://www.att.com:443/my/#/passthrough/')
            || stristr($currentUrl, 'https://www.paygonline.com/websc/home.html')
            || stristr($currentUrl, 'https://www.paygonline.com/websc/showMlSummaryPage.html')
        ) {
            return true;
        }
        // registration is not completed
        if (stristr($currentUrl, 'https://lsreg.att.net/CommonRegistrationWeb/lsreg/index.jsp#/initiate')
            // Hey, have you looked at your profile lately? Take a minute to check if anything's missing or changed and update it.
            || stristr($currentUrl, 'https://www.att.com/olam/IdentitySuccessAction.olamexecute?fromdlom=true&reportActionEvent=A_LGN_LOGIN_SUB&loginSource=olam')
            || $this->http->FindSingleNode('//p[contains(text(), "Hey, have you looked at your profile lately? Take a minute to check if anything\'s missing or changed and update it.")]')) {
            $this->throwProfileUpdateMessageException();
        }
        /**
         * Let's double-check that it's really you.
         * We want to keep your info safe.
         * We'll send you a code to enter to confirm your identity.
         */
        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->parsePasscode()) {
            return false;
        }

        // provider error, workaround
        if ($this->http->FindPreg("/Status\":\"BACKEND_ERROR\"/")) {
            $message = $this->http->FindPreg("/\"(Uh-oh, your browser might be stuck\. Try clearing your cache, or open this page in a different browser\. \(LGN0191\))/") ?? null;

            throw new CheckRetryNeededException(2, 10, $message);
        }

        // maintenance message
        if ($this->http->FindSingleNode("//p[contains(text(), 'Our best deals and rewards are online, so check back in a bit, and thanks for your patience!')]")) {
            throw new CheckRetryNeededException(2, 1);
        }

        // The functionality that you have requested is currently unavailable. Please try again later.
        if ($message = $this->http->FindSingleNode('//h4[contains(text(), "The functionality that you have requested is currently unavailable. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parsePasscode()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("PasscodeForm")) {
            return false;
        }
        $question = "Please enter your Passcode";
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Passcode";

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("2FAuthSelectOpts")) {
            return false;
        }
        $sendToEmail = $this->http->FindSingleNode("//input[@name = '2FAuthOptionsRadios' and contains(@value, '@')]/@value", null, false);
        $sendToPhone = $this->http->FindSingleNode("(//input[@name = '2FAuthOptionsRadios' and contains(@value, 'XXX-XXX-')]/@value)[1]", null, false);

        if (!$sendToEmail && !$sendToPhone) {
            return false;
        }
        $this->http->SetInputValue("2FAuthOptionsRadios", $sendToEmail ?? $sendToPhone);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->http->PostForm();
        $email = $this->http->FindSingleNode('//p[contains(text(), "We sent it to")]', null, true, '/it\s+to\s+(.+)\.\s*$/');

        if (!$this->http->ParseForm("2FAuthValidate") || !$email) {
            return false;
        }

        if (strstr($email, '@')) {
            $question = "Please enter Code which was sent to the following email address: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            $question = "Please enter Code which was sent to the following phone number: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        switch ($step) {
            case 'Question':
//                $this->sendNotification("2fa - code was entered");
                $this->http->SetInputValue("2FAuthOptCode", $this->Answers[$this->Question]);
                unset($this->Answers[$this->Question]);
                $this->http->SetInputValue("trustDevice", "on");
                $this->http->RetryCount = 0;
                $this->http->PostForm();
                $this->http->RetryCount = 2;

                // Invalid answer
                if ($error = $this->http->FindSingleNode('//span[contains(text(), "Hmm...that code isn\'t working. Check your entry or start over with a new code.")]')) {
                    $this->AskQuestion($this->Question, $error, 'Question');

                    return false;
                }

                break;

            case 'Passcode':
                $this->http->FormURL = 'https://www.att.com/olam/submitSlidPcode.myworld';
                $this->http->SetInputValue("userPasscodeList", $this->Answers[$this->Question]);
                $this->http->PostForm();
                /**
                 * The passcode should be the same code you use to access account information when you call 611.
                 * If you cannot remember your security passcode, Select the 'forgot passcode' link below. (L153).
                 */
                if ($error = $this->http->FindSingleNode('//p[contains(text(), "The passcode should be the same code you use to access account information when you call ")]', null, true, "/([^.]+)/")) {
                    $this->AskQuestion($this->Question, $error, 'Passcode');

                    return false;
                }

                break;
        }

        $this->skipOffers();

        return true;
    }

    public function Parse()
    {
        // Your Personalized Bill Tour is Ready
        if ($noThanksLink = $this->http->FindSingleNode("//a[contains(@href, 'rejectPromoUserResponse.myworld?response=rejected&reportActionEvent=A_LGN_INTER_VIDEO_BILL_NO_THANKS_SUB')]/@href")) {
//            $this->http->NormalizeURL($noThanksLink);
            $this->logger->notice("click 'No Thanks' link...");
            // for direct parser
            $this->http->GetURL("https://www.att.com/olam/" . $noThanksLink);
        }

        if (stristr($this->http->currentUrl(), 'https://www.att.com/my/#/passthrough/')
            || stristr($this->http->currentUrl(), 'https://www.att.com:443/my/#/passthrough/')
            || $this->http->FindPreg("/Status\":\"SUCCESS\"/")) {
            $this->logger->notice("New design");
            $data = [
                "CommonData" => [
                    "AppName"  => "DGlobalNav",
                    "Language" => "EN",
                ],
                "ApplicationReturnUrl" => "https://www.att.com/my/#/accountOverview",
            ];
            $headers = [
                "X-Requested-By"                   => "tesla-gn",
                "Content-Type"                     => "application/json",
                "Accept"                           => "application/json",
                "access-control-allow-credentials" => "true",
            ]; // Cache-Control no-cache=set-cookie

            $this->http->PostURL("https://www.att.com/best/resources/auth/shared/notifications/retrieveContent", json_encode($data), $headers);
            $response = $this->http->JsonLog(null, 3, true);
            // Name
            $customerInformation = ArrayVal($response, 'CustomerInformation');
            $this->SetProperty("Name", beautifulName(ArrayVal($customerInformation, 'FirstName') . ' ' . ArrayVal($customerInformation, 'LastName')));

            $number = $this->http->FindPreg("/\"AccountNumber\":\"([\d\-]+)/");
            $noNumber = $this->http->FindPreg("/\"Title\":\"Update your wireless number\"/");

            // Universe accounts
            if (ArrayVal($response, 'operation') == 'login') {
                $this->parseUniverseAccount();

                return;
            }

            $data = [
                "CommonData" => [
                    "AppName"  => "D-MYATT",
                    "Language" => "EN",
                ],
            ];
            $headers = [
                "X-Requested-By" => "MYATT",
                "Content-Type"   => "application/json",
                "Accept"         => "application/json",
            ];
            $this->http->PostURL("https://www.att.com/myatt/com/resources/auth/shared/native/overview/details", json_encode($data), $headers);
            $response = $this->http->JsonLog();
            // Balance - Total balance
            $this->SetBalance($this->http->FindPreg("/\"bp_tot_bal\":\s*\"([^\"]+)/"));
            // Wireless account
            $this->SetProperty("AccountNumber", $this->http->FindPreg("/\"(?:rsp_acct_num|user.account.wirelessAccountNumber)\":\"([\d\-]+)/"));

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if (!isset($this->Properties['AccountNumber'])
                    && ($this->http->FindPreg("/Add your accounts/")
                        || (!$this->http->FindPreg("/BillAndPayCollapsedCard/") && ($this->http->FindPreg("/RewardsCollapsedCard/") || $this->http->FindPreg("/DirectvNowWatchTVExpandedCard/"))))) {
                    $this->SetBalanceNA();
                }
                // Your account ... was canceled
                if ($noNumber) {
                    $number = $this->http->FindPreg("/Your account (\d+) was canceled/");
                }

                if (!isset($this->Properties['AccountNumber']) && ($message = $this->http->FindPreg("/Your account {$number} was canceled/"))) {
                    $this->SetWarning($message);
                    $this->SetProperty("AccountNumber", $number);
                }// if (!isset($this->Properties['AccountNumber']) && ($message = $this->http->FindPreg("/Your account {$number} was canceled/")))
                // AccountID: 2482293
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && isset($this->Properties['AccountNumber'])
                    && !empty($this->Properties['Name']) && $this->http->FindPreg('/"pu_usage":"\[X\] of \[Y\] \[Z\] used"/')) {
                    $this->SetBalanceNA();
                }
                // hard code: no errors, no auth (AccountID: 3091690)
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && in_array($this->AccountFields['Login'], ['Jon@schalliol.com'])) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                // AccountID: 2622093
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name']) && in_array($this->AccountFields['Login'], ['brandacedean@att.net'])) {
                    $this->SetBalanceNA();
                }

                // There was a technical hiccup on our end. We can't display your balance info right now.
                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name']) && ($message = $this->http->FindPreg('/"bp_tot_bal_msg":"My bill","bp_err_msg":"(There was a technical hiccup on our end. We can\'t display your balance info right now\.)"/'))) {
                    $this->SetWarning($message);
                }
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

            return;
        }// $this->logger->notice("New design");

        // Balance - Your total balance is
        if (!$this->SetBalance(Html::cleanXMLValue($this->http->FindPreg("/Your\s*(?:total\s*|\s*)(?:credit\s*|\s*)balance\s*is:[^<]+[^>]+>\s*<[^>]+>\s*<[^>]+>\s*<[^>]+>([^<]+)/")))) {
            if (!$this->SetBalance(Html::cleanXMLValue($this->http->FindPreg("/Total\s*Amount\s*Due\s*by[^:]+:[^<]+[^>]+>\s*<[^>]+>\s*<[^>]+>\s*<[^>]+>([^<]+)/")))) {
                if (!$this->SetBalance(Html::cleanXMLValue($this->http->FindPreg("/Current\s*Balance(?:[^<]+[^>]|){7}[^>]>+([^<]+)/")))) {
                    if (!$this->SetBalance(Html::cleanXMLValue($this->http->FindPreg("/Current\s*Balance(?:[^<]+[^>]|){5}[^>]>+([^<]+)/")))) {
                        $this->SetBalance(Html::cleanXMLValue($this->http->FindSingleNode("//div[contains(text(), 'Payment Due')]/following-sibling::div[1]/p")));
                    }
                }
            }
        }

        // Expires on ... (AccountID: 2965887)
        if (($exp = $this->http->FindPreg("/Expires\s*on\s*([^<]+)/")) && ($exp = strtotime($exp))) {
            $this->SetExpirationDate($exp);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindPreg("/<h2 class=\"disabletext\">(Non-Linked Accounts)<\/h2>/")
                || $this->http->FindSingleNode("//p[contains(text(), 'Explore your billing options')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Your new total monthly charges will be available as soon as your first bill is ready.')]")
                || $this->http->FindSingleNode('//*[contains(text(), "This ID has limited options since there aren\'t any accounts linked to it.")]')
                || $this->http->FindPreg("/Estimated first bill\:/")
                || $this->http->FindPreg("/Want to access all your info with a single ID and password\?/")
                || $this->http->FindSingleNode("//div[contains(text(), 'Payment Due')]/following-sibling::div[1]/p") === 'Not Available') {
                $this->SetBalanceNA();
            }
            // The account has been disconnected. Please contact Customer Service.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "The account has been disconnected. Please contact Customer Service.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // The system could not log you on. Please enter your password and try again.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "The system could not log you on. Please enter your password and try again.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Account
        $number = $this->http->FindSingleNode("//*[@id = 'DCSextwtAcctNum']/@value");

        if (!isset($number)) {
            $number = $this->http->FindSingleNode("//p[contains(text(), 'Account:')]", null, true, "/Account:\s*([^<]+)/ims");
        }

        if (!isset($number)) {
            $number = $this->http->FindPreg("/id='DCSextwtAcctNum'	webtrendTagAttr='true'	value='([^']+)'/ims");
        }

        if (!isset($number)) {
            $number = $this->http->FindPreg("/id='DCSextwtSLIDAssocAccts'	webtrendTagAttr='true'	value='[^']*UVS\~([^']+)'/ims");
        }

        if (!isset($number)) {
            $number = $this->http->FindPreg("/<p class=\"account\">([^<]+)/ims");
        }

        if (!isset($number)) {
            $this->http->GetURL('https://www.att.com/olam/SLIDMyProfileview.myworld?event=view');
            $number = $this->http->FindSingleNode('//span[contains(text(), "Account Number:")]/following::span[1]', null, true, '/(\w+)/ims');

            if (!isset($number)) {
                $number = $this->http->FindSingleNode('//div[p[contains(text(), "My Wireless Number:")]]/following-sibling::div[1]/label');
            }

            if (!isset($number)) {
                $number = Html::cleanXMLValue($this->http->FindPreg('/Account Number:\s*<[^>]+>\s*<[^>]+>\s*<[^>]+>\s*<[^>]+>([^<]+)/'));
            }

            if (empty($number) && $this->http->FindSingleNode("//i[contains(., 'You must fill out all fields to continue')]")) {
                $this->throwProfileUpdateMessageException();
            }
        }
        $this->SetProperty("AccountNumber", preg_replace("/\~.+/", '', $number));

        if (stristr($this->http->currentUrl(), 'https://www.paygonline.com/websc/home.html')) {
            $this->http->GetURL("https://www.paygonline.com/websc/personalProfileAction.html");
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h6[contains(text(), 'Name:')]/following-sibling::p[1]")));
        }// if (stristr($this->http->currentUrl(), 'https://www.paygonline.com/websc/home.html'))
        else {
            $this->http->GetURL("https://www.att.com/olam/jsp/tiles/common_includes/globalNav/user_info.jsp?hideLoginButton=null&_=" . time() . date("B"));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id = 'globalNavUserFirstName']")));

            // hard code: no errors, no auth (AccountID: 4045908)
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && in_array($this->AccountFields['Login'], ['2023404349'])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    private function parseUniverseAccount()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.att.com/msapi/reporting/v1/customerinfo");
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($response->{'user.attributes.customerName'} ?? null));

        $headers = [
            "Accept"       => "*/*",
            "content-type" => "application/json",
        ];
        $this->http->PostURL("https://www.att.com/msapi/accountservices/v1/overview/getSnapshotDetails", "{}", $headers);
        $response = $this->http->JsonLog(null, 3, false, 'servicesAssociated');
        $accountInfo = $response->content->snapshotCardData->accountInfo[0] ?? null;

        if (!isset($accountInfo->accountNumber) || !isset($accountInfo->accountTenure) || !isset($accountInfo->accountType)) {
            return;
        }
        $this->SetProperty("AccountNumber", $accountInfo->accountNumber);

        $data = [
            "balanceSummaryRequestInfo" => [
                [
                    "accountNumber" => $accountInfo->accountNumber,
                    "accountTenure" => $accountInfo->accountTenure,
                    "accountType"   => $accountInfo->accountType,
                ],
            ],
        ];
        $this->http->PostURL("https://www.att.com/msapi/accountservices/v1/overview/accountBalanceSummary", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        // Balance - Total balance
        $this->SetBalance($response->content->data->amountDue ?? null);
    }
}
