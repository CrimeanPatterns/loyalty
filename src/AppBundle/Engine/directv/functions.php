<?php

class TAccountCheckerDirectv extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setMaxRedirects(10);
    }

    public function LoadLoginForm()
    {
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
        $this->http->GetURL("https://www.directv.com/DTVAPP/login/loggedOut.jsp?DPSLogout=true&_requestid=1829627");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        if ($this->http->InputExists("userName")) {
            $this->http->SetInputValue("userName", $this->AccountFields['Login']);
        } else {
            $this->http->SetInputValue("userid", $this->AccountFields['Login']);
        }
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("/atg/userprofiling/ProfileFormHandler.userPinning", "Submit Query");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.directv.com/DTVAPP/login/loggedOut.jsp?DPSLogout=true&_requestid=1829627";

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // directv.com will be back shortly.
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "directv.com will be back shortly.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry. We cannot process your request at this time. We apologize for the inconvenience. Please try again later.
        if ($message = $this->http->FindSingleNode('//img[@src = "https://cprodmasx.att.com/pics/cannot_process_your_request3.gif"]/@src')) {
            throw new CheckException("We're sorry. We cannot process your request at this time. We apologize for the inconvenience. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // We're sorry, we're experiencing some technical difficulties.
        if ($message = $this->http->FindSingleNode('//a[contains(text(), "We\'re sorry, we\'re experiencing some technical difficulties.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function sendSensorDataFromAtt()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#") ?? 'https://www.att.com/public/57353a4b923147b79d8d603bd4ede';

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9166931.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:76.0) Gecko/20100101 Firefox/76.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,391057,9177000,1536,880,1536,960,1536,474,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,6010,0.522233279261,794679588500,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.att.com/my/#/login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1589359177000,-999999,17002,0,0,2833,0,0,3,0,0,3BD2C6EEFC8A04E603B787FC6141CA68~-1~YAAQoi3+pXvvpNNxAQAAOVQwDQNSPEJ6H7sfyrlDM7SIr2bxYeEJshBv1QL1BGlEgebgi3OWaGles0YV8X0mbEQLHeif4leahsfdG3X8w8ZvINuWaIwsmJeURJCFuueTYYzqANrZ6+PXpLXwQRYiN3cbso98Rha/SU7JamYGHy2k5ixHINMaAHSpuyqW/Gp79NC+WAJYTviWEWHgwcycBTu71IBgC81oVtAL4+v14kRTctZqoyczjFkCmJdsjyta4jU/Hvms+tIHLtztUrT732Y/C6g69BVPXfK8KjO8iz4/qc41kEpYh+mN/mJnDecPedXu+Os=~-1~-1~-1,31084,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,9176984-1,2,-94,-118,73642-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9166931.54-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:76.0) Gecko/20100101 Firefox/76.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,391057,9177000,1536,880,1536,960,1536,474,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,6010,0.18455742492,794679588500,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.att.com/my/#/login-1,2,-94,-115,1,32,32,0,0,0,0,534,0,1589359177000,8,17002,0,0,2833,0,0,535,0,0,3BD2C6EEFC8A04E603B787FC6141CA68~-1~YAAQki3+pcqQKNFxAQAAPG4wDQN0MMywDzork4S+dvOxJI5HfGCOlnF76jkh0FtE1ipnHndY+dCDZQPDaLvFpc15w1s1jtUncieL/904tu1xws5K+hkCEFSw3VB7JI/+lPkaVA+PE1Kc+8sUeUtdyYP0/9DE5m4BfffWhKGfvEHVim1oYixfKjvsM29Ds5O/LKD96urxwm8es54gbgF1NZX0/bvWEaYTP1ahq1KRWxPMYFES65GSHc5EmsU1cAUDR8mfYbHt7Ybz3ZsRZI8g7sUnyal5FUoAivhIiGzSyBKCd5AHUP0zkb8sZtq9L3kgmJ2lMuQ=~-1~-1~-1,30451,283,-1197592547,26067385-1,2,-94,-106,9,1-1,2,-94,-119,0,0,200,0,200,0,200,0,0,200,0,200,0,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,9176984-1,2,-94,-118,76211-1,2,-94,-121,;1;5;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

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
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Multiple Accounts Found
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Multiple Accounts Found')]")) {
            $this->logger->notice("Multiple Accounts Found -> go to Direc TV account");
            $this->http->SetInputValue("optionsRadios", $this->http->FindSingleNode("//input[@id = 'directTV']/@value"));
            $this->http->PostForm();
        }

        if ($this->http->ParseForm(null, 1, true, "//form[contains(@action, 'https://www.directv.com/DTVAPP/auth.jsp')]")) {
            $this->http->PostForm();
            $tokenID = $this->http->FindPreg("/TATS-TokenID\'\+\'=\'\+encodeURIComponent\(\'([^\']+)/ims");

            if (isset($tokenID)) {
                $this->http->PostURL("https://www.directv.com/DTVAPP/ajaxAuth.jsp?fromJsp=auth.jsp&remember=true", [
                    //"fromJsp"  => "auth.jsp",
                    //"remember" => "true",
                    "TATS-TokenID"  => $tokenID,
                ]);
            }// if (isset($tokenID))
        }// if ($this->http->ParseForm(null, 1, true, "//form[contains(@action, 'https://www.directv.com/DTVAPP/auth.jsp')]"))
        else {
            $response_code = $this->http->FindPreg("/response_code\'\+\'=\'\+encodeURIComponent\(\'([^\']+)/ims");

            if (isset($response_code)) {
                $this->http->PostURL("https://www.directv.com/DTVAPP/ajaxAuth.jsp", [
                    "fromJsp"        => "auth.jsp",
                    "nextUrl"        => "https://www.directv.com/DTVAPP/mydirectv/account/myOverview.jsp",
                    "remember"       => "true",
                    "response_code"  => $response_code,
                ]);
            }
        }
        // redirect
        $response = $this->http->JsonLog();

        if (isset($response->redirectUrl)) {
            $this->http->setMaxRedirects(10);
            $this->http->NormalizeURL($response->redirectUrl);
            $this->http->GetURL($response->redirectUrl);
            $this->http->setMaxRedirects(5);
        }// if (isset($response->redirectUrl))

        // provider bug fix
        if ($this->http->Response['code'] == 500) {
            $this->http->GetURL("https://www.directv.com/DTVAPP/login/login.jsp");
        }

        /** hard code */
        // AccountID: 3336732, 4606558
        $att = false;

        if ($this->http->currentUrl() == 'https://www.directv.com/directv_att_welcome') {
            $att = true;
            $this->http->GetURL("https://www.att.com/olam/loginAction.olamexecute?fromdlom=true");
            $this->http->setMaxRedirects(10);

            $login = $this->AccountFields['Login'];
            $this->logger->debug("[Login]: {$login}");

            if (strstr($login, '(') && strstr($login, '-')) {
                $this->logger->debug("fixed login");
                $login = str_replace(['(', ')', '-', ' '], '', $login);
            }
            $this->logger->debug("[Login]: {$login}");

            $this->sendSensorDataFromAtt();

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
            ];
            $this->http->PostURL("https://www.att.com/myatt/lgn/resources/unauth/login/tguard/authenticateandlogin", json_encode($data), $headers);
            $response = $this->http->JsonLog(null, true, true);

            if ($this->http->FindPreg("/Status\":\"SUCCESS\"/")) {
                $this->http->setCookie("TATS-SS-TokenID", ArrayVal($response, 'TATS-SS-TokenID'), ".att.com");
                $this->http->setCookie("PD-ID", ArrayVal($response, 'PD-ID'), ".att.com");
            }

            if ($this->http->FindPreg("/Status\":\"PARTIAL_SUCCESS\"/")) {
                $redirectUrl = ArrayVal($response, 'RedirectUrl', null);

                if ($redirectUrl) {
                    $this->http->GetURL($redirectUrl);

                    // retries
                    if ($this->http->currentUrl() == 'https://www.att.com/my/#/login') {
                        throw new CheckRetryNeededException(2, 10);
                    }

                    if ($this->http->FindSingleNode('//p[contains(text(), "The page you have requested does not exist.")]')) {
                        throw new CheckRetryNeededException(2, 10);
                    }
                    /*
                     * Error!
                     * The wireless number you entered is not associated with a valid AT&T PREPAID subscriber. Please check the number and try again.
                     */
                    if ($message = $this->http->FindSingleNode('//p[contains(text(), "The wireless number you entered is not associated with a valid AT&T PREPAID subscriber. Please check the number and try again.")]')) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                    // We seem to be experiencing system issues. Please try again later.
                    // The system cannot sign you in at this time. Try again in 60 minutes.
                    if ($message = $this->http->FindSingleNode('
                    //p[contains(text(), "We seem to be experiencing system issues. Please try again later.")]
                    | //p[contains(text(), "The system cannot sign you in at this time. Try again in 60 minutes.")]
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

                    // Just a little housekeeping...
                    if ($this->http->FindSingleNode("//i[contains(., 'You must fill out all fields to continue')]")) {
                        $this->throwProfileUpdateMessageException();
                    }
                }
            }//if ($this->http->FindPreg("/Status\":\"PARTIAL_SUCCESS\"/"))

            if ($this->parseQuestion()) {
                return false;
            }
        }
        /** hard code */

        // Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'loggedOut')]/@href)[1]")) {
            return true;
        }
        // Invalid credentials
        if (isset($response_code) && ($message = $this->http->FindSingleNode("//li[@id = '{$response_code}']"))) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The User ID and password combination you entered doesn’t match our records.
        if ($message = $this->http->FindSingleNode('//*[contains(text(), "The User ID and password combination you entered doesn’t match our records.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
//        // Register your account and get even more from DIRECTV!
//        if ($this->http->FindPreg("/Register your account and <br>get even more from DIRECTV!/ims"))
//            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_INVALID_PASSWORD);
        // We've successfully logged you in at AT&T with your DIRECTV ID and password.
        if ($this->http->FindSingleNode('//p[contains(text(), "Looks like you\'re trying to log in with your old DIRECTV ID")]')) {
            throw new CheckException("If you need to manage your DIRECTV accounts please Log in with your DIRECTV ID and password.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        // We noticed you tried to sign in with your AT&T Access ID and password.
        if ($this->http->FindPreg('/We noticed you tried to sign in<br>with your AT\&amp;T Access ID and password\./')) {
            throw new CheckException("We noticed you tried to sign in with your AT&T Access ID and password. If you'd like to manage your existing AT&T account, please go to att.com.", ACCOUNT_INVALID_PASSWORD);
        }/*review*/
        // AT&T Access ID Terms and Conditions
        if ($this->http->FindSingleNode('//h1[contains(text(), "AT&T Access ID Terms and Conditions")]')) {
            throw new CheckException("DIRECTV website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry something went wrong
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry something went wrong")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // registration is not completed
        if ($this->http->FindSingleNode('//p[contains(text(), "Hey, have you looked at your profile lately? Take a minute to check if anything\'s missing or changed and update it.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->currentUrl() == 'https://www.att.com:443/olam/showSLIDOverviewAction.myworld'
            || stristr($this->http->currentUrl(), 'https://www.att.com/my/#/passthrough/')
            || stristr($this->http->currentUrl(), 'https://www.att.com:443/my/#/passthrough/')
            || stristr($this->http->currentUrl(), 'https://cprodmasx.att.com/commonLogin/igate_wam/controller.do')
            || $this->http->currentUrl() == 'https://www.att.com/olam/viewInterstitialPromo.myworld'
            || $this->http->currentUrl() == 'https://www.att.com/olam/passthroughAction.myworld?actionType=Manage'
            /** hard code */
            || $att == true
        /** hard code */
        ) {
            return $this->parseAtt();
        }

        // AccountID: 4066621, 3458306, 3820418, 3336732
        if (in_array($this->AccountFields['Login'], [
            'sid@sidit.org',
            'tdcrepes',
            'kingengr',
            'david4682@att.net',
            'jshin1013@gmail.com',
            'michaelgraham@isp.com',
            'lori8989@gmail.com',
            'jodilane78@gmail.com',
            'bkaplan@silverhalide.net',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /**
         * Welcome to myAT&T.
         * You'll now access and manage your DIRECTV account on myAT&T.
         * The same ID and password lets you manage DIRECTV and other AT&T accounts.
         *
         * Here’s your Access ID. It’s the same as your DIRECTV ID. You’ll keep your existing password.
         * ...@....com
         *
         * Use this ID and password to watch TV, check usage, or pay your bill here with myAT&T.
         * To get started, please accept the AT&T Access ID Terms & Conditions.
         */
        if ($this->http->currentUrl() == 'https://m.att.com/my/#/welcome?origination_point=dtvotf&return_url=https%3A%2F%2Fcprodx.att.com%2FTokenService%2FnxsATS%2FWATokenService%3FappID%3DM81193%26returnURL%3Dhttps%253A%252F%252Fcprodx.att.com%252FTokenService%252FnxsATS%252FWATokenService%253FisPassive%253Dtrue%2526appID%253Dm93639%2526returnURL%253Dhttps%25253A%25252F%25252Fwww.directv.com%25252FDTVAPP%25252Fauth.jsp%25253Fremember%25253Dfalse'
            || $this->http->currentUrl() == 'https://m.att.com/my/#/welcome?origination_point=dtvotf&appID=m93639&return_url=https%3A%2F%2Fcprodx.att.com%2FTokenService%2FnxsATS%2FWATokenService%3FappID%3DM81193%26returnURL%3Dhttps%253A%252F%252Fcprodx.att.com%252FTokenService%252FnxsATS%252FWATokenService%253FisPassive%253Dtrue%2526appID%253Dm93639%2526returnURL%253Dhttps%25253A%25252F%25252Fwww.directv.com%25252FDTVAPP%25252Fauth.jsp%25253Fremember%25253Dfalse'
            || $this->http->FindPreg('/<label[^>]+>\s*Accept\s*AT\&amp;T\s*Access\s*ID\s*Terms\s*\&amp;\s*Conditions\s*<\/label>/')) {
            $this->throwAcceptTermsMessageException();
        }

        // We weren't expecting you so soon! We're working on a better online experience for you.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We weren\'t expecting you so soon! We\'re working on a better online experience for you.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there was a system error. please try again or call us at 1-800-288-2020
        if ($message = $this->http->FindSingleNode('//a[contains(text(), "Sorry, there was a system error. please try again or call us at 1-800-288-2020")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Sorry, there was a system error. please try again or call us at 1-800-288-2020
        if (
            $this->http->currentUrl() == 'https://www.directv.com/DTVAPP/global/404.jsp'
            && $this->http->Response['code'] == 404
            && $this->AccountFields['Login'] == 'map20@yahoo.com'
        ) {
            throw new CheckException("Sorry, there was a system error. please try again or call us at 1-800-288-2020", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $js_redirect = $this->http->FindSingleNode("//a[contains(text(), 'Manage Account')]/@href");

        if (stristr($js_redirect, 'https://www.att.com/olam/passthroughAction.myworld?actionType=Manage&source=')) {
            $this->logger->debug("[JS Redirect]: {$js_redirect}");
            $this->http->GetURL($js_redirect);
            // skip offer
            if ($remindMeLaterLink = $this->http->FindSingleNode("//a[img[@alt = 'Remind Me Later']]/@href")) {
                $this->logger->debug("[Skip offer]: {$remindMeLaterLink}");
                $this->http->NormalizeURL($remindMeLaterLink);
                $this->http->GetURL($remindMeLaterLink);
            }// if ($remindMeLaterLink = $this->http->FindSingleNode("//a[img[@alt = 'Remind Me Later']]/@href"))
            $this->parseAtt();

            return;
        } else {
            $this->http->GetURL("https://www.directv.com/DTVAPP/mydirectv/account/myOverview.jsp?ACM=false&lpos=Header:5");
        }
        // Balance - Current Balance
        $this->SetBalance(str_replace('.$', '', implode('.', $this->http->FindNodes("//div[contains(@class, 'my-billing-amount')]/div"))));
        // Previous Balance
        $this->SetProperty("PreviousBalance", $this->http->FindSingleNode("//div[@class = 'past-balance']", null, true, "/\:\s*([^<]+)/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'welcome-message ')]/b", null, true, "/Welcome,\s*([^\!]+)/ims")));
        // Account No.
        $this->SetProperty("AccountNo", $this->http->FindSingleNode("//div[contains(@class, 'welcome-message ')]/b", null, true, "/Account\s*No\.\s*([^<]+)/ims"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindPreg("/For billing-related inquiries, please contact CenturyLink at the number listed on your CenturyLink bill\.|To make billing-related inquiries or to view your statements, please log into your account at <a href=\"http:\/\/www\.att\.com\">www\.att\.com<\/a>\./")) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        $this->http->GetURL("https://www.directv.com/DTVAPP/mydirectv/account/myAccountInfo.jsp?ACM=false&lpos=Header:5");
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[contains(text(), 'Name:')]/following-sibling::li[1]")));
    }

    /**
     * from att.
     */
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
        $this->sendNotification("2fa - code was entered");
        $this->http->SetInputValue("2FAuthOptCode", $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->http->SetInputValue("trustDevice", "on");
        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;
        // Invalid answer
        if ($error = $this->http->FindSingleNode('//span[contains(text(), "Hmm...that code isn\'t working. Check your entry or start over with a new code.")]')) {
            $this->AskQuestion($this->Question, $error);

            return false;
        }

        return true;
    }

    protected function parseAtt()
    {
        $this->logger->notice('Parsing properties from AT&T');
        $this->logger->debug(var_export($this->AccountFields, true), ['pre' => true]);
        /* @var TAccountCheckerAtt $attChecker */
        $attChecker = new TAccountCheckerAtt();
        $attChecker->logger = $this->logger;
        $attChecker->http = $this->http;
        $attChecker->globalLogger = $this->globalLogger; // fixed notifications
        $attChecker->skipOffers();

        if ($this->parseQuestion()) {
            return false;
        }
        $attChecker->Parse();
        $this->SetBalance($attChecker->Balance);
        $this->Properties = $attChecker->Properties;

        // TWO FORM FACTOR VALIDATION REQUIRED
        if (!isset($this->Properties['AccountNumber'])) {
            $response = $attChecker->http->JsonLog();

            if (isset($response->Result->Description) && $response->Result->Description == 'TWO FORM FACTOR VALIDATION REQUIRED'
                && $attChecker->LoadLoginForm()
            ) {
                if (!$attChecker->Login()) {
                    return false;
                }
                $attChecker->Parse();
                $this->SetBalance($attChecker->Balance);
                $this->Properties = $attChecker->Properties;
            }
        }// if (!isset($this->Properties['AccountNumber']))

        if (isset($this->Properties['AccountNumber'])) {
            $this->SetProperty("AccountNo", $this->Properties['AccountNumber']);
            unset($this->Properties['AccountNumber']);

            if ($attChecker->ErrorCode == ACCOUNT_WARNING) {
                $this->SetWarning($attChecker->ErrorMessage);
            }
        }// if (isset($this->Properties['AccountNumber']))
        // fixed n\a
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $attChecker->ErrorCode == ACCOUNT_CHECKED
            && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        return false;
    }

    /*function ParseFiles($filesStartDate) {
        $this->http->TimeLimit = 500;
//        $this->http->GetURL("https://www.directv.com/DTVAPP/mydirectv/account/payment/myBillingCenter.jsp?panel=paperlessBilling");
        $this->http->GetURL("https://www.directv.com/DTVAPP/EncryptedRedirect.jsp#[dtvparent]https%3A//www.directv.com/DTVAPP/mydirectv/account/payment/myBillingCenter.jsp%3Fpanel%3DpaperlessBilling[/dtvparent]");

        $result = [];
        $files = [];

        if (!$this->http->ParseForm("billDetailDownloadPdf"))
            return $result;
        $pdfForm = $this->http->Form;
        $pdfURL = $this->http->FormURL;

        $this->http->ParseForm("billDetail");
        $billForm = $this->http->Form;
        $billURL = $this->http->FormURL;

        $page = 0;
        $next = null;
        do {
            $page++;
            $this->http->Log("[Page: {$page}]");
            if ($page > 1) {
                $this->http->SetInputValue('currentPage', $next);
                $this->http->PostForm();
            }
            $billIds = $this->http->XPath->query("//a[contains(@href, 'billDetailPopupSubmit') and contains(text(), '/')]");
            $this->http->Log("Total {$billIds->length} links were found");
            foreach($billIds as $billId) {
                $file = [
                    'title'  => CleanXMLValue($billId->nodeValue),
                    'billId' => $this->http->FindSingleNode("@href", $billId, true, "/billDetailPopupSubmit\(\'([^\']+)/ims")
                ];
                $this->http->Log("node: {$file['title']}, {$file['billId']}");
                $files[] = $file;
            }// foreach($billIds as $billId)
            if ($page > 15) {
                $this->http->Log("too many pages");
                break;
            }
        } while (
            $this->http->ParseForm("pagination")
            && ($next = $this->http->FindSingleNode("//a[contains(text(), 'Next>')]/@href", null, true, "/setPage\(\'([^\']+)/ims"))
        );
        foreach ($files as $file) {
            $this->http->Log("downloading {$file['title']}, {$file['billId']}");
            $date = null;
            $date = strtotime($file['title']);
            if (intval($date) >= $filesStartDate) {
//                $this->http->GetURL("https://directv3.ebilling.com/ebill/prod/dtv/static/htm/blank.html");
                // billDetail
                $this->http->Form = $billForm;
                $this->http->FormURL = $billURL;
                $this->http->SetInputValue("billId", $file['billId']);
                $this->http->SetInputValue("pageId", time().date("B"));
                $this->http->PostForm();

                // billDetailDownloadPdf
                $this->http->Form = $pdfForm;
                $this->http->FormURL = $pdfURL;
                $this->http->SetInputValue("billId", $file['billId']);
                $this->http->SetInputValue("pageId", time().date("B"));
                $this->http->PostForm();

                // DownloadFile
                $this->http->ParseForms = false;
                $this->http->ParseEncoding = false;
                $this->http->ParseMetaRedirects = false;
                $this->http->ParseDOM = false;
                $filePDF = "/tmp/captcha-".getmypid()."-".microtime(true);
                if(isset($extension))
                    $filePDF .= ".".$extension;
                file_put_contents($filePDF, $this->http->Response['body']);
                $this->http->ParseForms = true;
                $this->http->ParseEncoding = true;
                $this->http->ParseMetaRedirects = true;
                $this->http->ParseDOM = true;
                $this->http->Log("downloaded file: ".$filePDF, LOG_LEVEL_NORMAL);

                $fileName = $filePDF;
                if (strpos($this->http->Response['body'], '%PDF') === 0) {
                    $result[] = [
                        "FileDate" => $date,
                        "Name" => $file["title"],
                        "Extension" => "pdf",
                        "AccountNumber" => isset($this->Properties['AccountNo'])?$this->Properties['AccountNo']: '',
                        "AccountName" => '',
                        "AccountType" => '',
                        "Contents" => $fileName,
                    ];
                }
                else
                    $this->http->Log("not a PDF");
            }
            else
                $this->http->Log("skip by date");
        }

        return $result;
    }*/
}
