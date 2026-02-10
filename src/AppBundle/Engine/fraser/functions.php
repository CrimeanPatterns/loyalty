<?php

class TAccountCheckerFraser extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://www.houseoffraser.co.uk/recognition/recognitionsummary";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'fraserRewardsBalance')) {
            if (isset($properties['Currency']) && $properties['Currency'] == 'GBP') {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");
            } else {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
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
        // Please enter a valid email address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        // reset cookie
        $this->http->removeCookies();
        $this->http->GetURL("https://www.houseoffraser.co.uk/Login?returnurl=/recognition/recognitionsummary");

        if ($this->http->ParseForm("login")) {
            $this->http->SetInputValue('Login.EmailAddress', $this->AccountFields['Login']);
            $this->http->SetInputValue('Login.Password', $this->AccountFields['Pass']);

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-response', $captcha);

            $this->sendSensorData();
            $response = $this->http->JsonLog(null, 0);

            if (
                $this->http->Response['code'] == 403
                || !isset($response->success)
                || !$response->success
                || $response->success === "false"
            ) {
                $this->DebugInfo = "sensor_data broken";

                return false;
            }

            return true;
        }

        $currentUrl = $this->http->currentUrl();
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);

        if (!$client_id || !$state || !$scope) {
            return $this->checkErrors();
        }

        $data = [
            "audience"      => "https://frasers.apis",
            "client_id"     => $client_id,
            "connection"    => "HouseOfFraser-Users-Passthrough",
            "password"      => $this->AccountFields['Pass'],
            "redirect_uri"  => "https://www.houseoffraser.co.uk/auth-callback",
            "response_type" => "code",
            "scope"         => $scope, // "openid profile email offline_access"
            "state"         => $state,
            "tenant"        => "houseoffraser",
            "username"      => $this->AccountFields['Login'],
            "_csrf"         => $this->http->getCookieByName("_csrf"),
            "_intstate"     => "deprecated",
        ];
        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTguMSJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin'          => 'https://auth.houseoffraser.co.uk',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://auth.houseoffraser.co.uk/usernamepassword/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We’re currently working hard to make some improvements to the website
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 're currently working hard to make some improvements to the website')
                or contains(text(), 'Thanks for stopping by, but the House of Fraser website is currently down for maintenance.')
            ]
            | //title[contains(text(), 'This site is currently not available')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - DNS failure
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - DNS failure')]")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            // rewards page not working for all accounts
            if (
                $this->http->Response['code'] == 404
                && $this->http->currentUrl() == 'https://www.houseoffraser.co.uk/recognition/recognitionsummary'
                && $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 404. The requested resource is not found.")]')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Don't worry it's us, not you. We are doing our best to fix the problem
            if (
                $this->http->Response['code'] == 500
                && $this->http->FindSingleNode('//h2[normalize-space() = "Don\'t worry it\'s us, not you. We are doing our best to fix the problem"]')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[contains(@class, "field-validation-error") or contains(text(), "dnnFormMessage dnnFormValidationSummary")]')) {
            $this->logger->error($message);

            // Captcha validation failed, please try again
            if (strstr($message, 'Captcha validation failed, please try again')) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (strstr($message, 'This email address or password is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->http->FindPreg("/<h1>We\&\#39;ve been busy making improvements to our website\. As a result, we ask that you update your password to continue shopping with your account - Please check your email now\./")) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Points Balance - Recognition Points
        if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Points Balance')]/following-sibling::div[2]", null, false, self::BALANCE_REGEXP))) {
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Registering for recognition is currently unavailable. Please try again in 24 hours.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sign up for a Recognition account')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // AccountID: 4571877
            elseif (
                $this->http->FindSingleNode("//h1[contains(text(), 'Have/want Reward Card?')]")
                && $this->http->currentUrl() == 'https://www.houseoffraser.co.uk/recognition/managerewardcard'
            ) {
                // provider bug fix, sometimes it helps
                $this->http->GetURL(self::REWARDS_PAGE_URL);

                if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(text(),'Points Balance')]/following-sibling::div[2]", null, false, self::BALANCE_REGEXP))) {
                    throw new CheckException("You have no any active card in your {$this->AccountFields['DisplayName']} profile.", ACCOUNT_PROVIDER_ERROR);
                }/*review*/
            }
        }
//        $exp = $this->http->FindSingleNode("//div[contains(text(),'Points Balance')]/following-sibling::div[1]/span", null, false, '#\d+/\d+/\d{4}#');
//        $this->logger->debug('Exp Date: ' . $exp);
//        if ($exp = strtotime($this->ModifyDateFormat($exp), false))
//            $this->SetExpirationDate($exp);
        // Card Number
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(text(), 'Status:')]/following-sibling::div", null, false, '/(?:^|Fraser\s*)(\d+)$/'));
        // CURRENTLY WORTH
        $this->SetProperty("BalanceWorth", $this->http->FindSingleNode('//p[span[contains(text(), "Currently Worth:")]]/text()[last()]'));

        // Rewards Balance
        $rewards = $this->http->FindSingleNode("//div[contains(text(),'Rewards Balance')]/following-sibling::div[2]", null, false, self::BALANCE_REGEXP);
        $currency = $this->http->FindSingleNode("//div[contains(text(),'Rewards Balance')]/following-sibling::div[2]", null, false, "/^([^\d\-]+)/");

        if (isset($rewards, $currency)) {
            $subAccount = [
                "Code"        => "fraserRewardsBalance",
                "DisplayName" => "Rewards Balance",
                "Balance"     => $rewards,
                "Currency"    => $currency,
            ];
//            $exp = $this->http->FindSingleNode("//div[contains(text(),'Rewards Balance')]/following-sibling::div[1]/span", null, false, '#\d+/\d+/\d{4}#');
//            if ($exp = strtotime($this->ModifyDateFormat($exp), false))
//                $subAccount['ExpirationDate'] = $exp;
            $this->AddSubAccount($subAccount);
        }

        $this->http->GetURL('https://www.houseoffraser.co.uk/accountinformation/editpersonaldetails');
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[contains(@id,'txtFirstName')]/@value") . ' ' .
            $this->http->FindSingleNode("//input[contains(@id,'txtLastName')]/@value")));
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[@id = "login"]/div[contains(@class, "g-recaptcha")]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a/span[contains(text(), 'SIGN OUT')]")) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "text/plain;charset=UTF-8",
        ];

        $sensorData = [
            '2;2048;3618374;4536375;10,0,0,0,0,0;oUkquhgrv8qM2X&Ti0;?#oX~Qz[8?tjODZ@f]Dx15b|R hHQ9Xe9nKXc?weIE,.ha+j:5z[{%L_/dPy*[%0J-=E^IakFYB<&}G2^hk=ckOpj0xU3d?Ufc:i1bqRD]CdJlwD`r:2c]+jN*k3?Hf;f%xWt2-csKZGz3KgxS?=v6i|MU99p$[zj%^indF4Y`CzO|nL{rvrM6/c|fx)nM_80j6^!clwF5l^k}~EP2fZ?-9.DAI&p `iPNCO]AUMJ[]4ZX$j:2Cx*-]cMmfz3g`iB,q`40DXE[4)%2|G3R:u(.N*kY/LYuCl[)um{afAiwP|0LCBG_C#x7[$gwOX#H)Y#gELiZ{yM%MMyWd.yYzYc,K)un~,p!I @&f|!eQEG4FFibms`ptHCx<<3?=H_}3Mx/IUI8wDz)`p9sQ7v;KPi1-~.jgNhB|^HW*S;DD)Te9lhv`O3+u[x%*% ~kVmNZYb?L@sz@uMphLMqJG^$I$x&q*%q>#/D-oRKV+=dw!^]>*Q>{.m.VYZ@B@~)nR*l_%]~ykfW=v.aHJaBMe,@[JW;w,Y)r*t;IPN$s$Bf+oWG2(YNvQ[zM,7@t0n)oL(Hls&l]]rn|oDPB-ocNl~5}kp(p}_2O3j:.2C;?<R bxDA07yuI)S8.p&7m^Lrk. p^z@)*8Y:E%:`][Kcafz^)|dQ[<,ZT61Ep-tcN&2#TSnB8J?eJ%Pytc%O!s1 iCI%%:ik[+E:)%zD?MI>6%Bp$vCcE*{ebKE#G%W]_qUJ !ncanxCja5TgmJaOB6|}~ Q?d4DM{NLIObps3?L?F 2YX9YTNF;;!G+jFv$,P*z<IZ*IZ]n;RI+2:/[E685QFY32aARj*kc5IX(*X-05&nTK$Z+(AF9SC^L3~cy<}lul2Ei#ZlC-I-Y4F0xWj !>?aEQ3!=NJ29Eh-fXVl8BOF%EdJQIBVd+8GBQ7$TKZOh.;b1d7^0|I20`~rYV(54_r66<A%Ix_Z10j-_-qIj#:30].ymv&Eb1NOt.x3Gjwi)OoA@lHnJ<ol[>,%wu{W}4M#.;D,aK0Cb.$**~Iysv>;Ev{^H{1`pRn@ /liB-&7c,iG.&h/g9`t{e;GBk.OxG%Z2SSi5)>xl6~g.5+_n)JclO->Jj/,?x:(GNm$fFemIp[eH*3nU(d|(p^~xJrR&u>(] 1:y*5=}DK*(x5f_-jBN4z3SCL2_N7X9q(a@%L_Gz`eiXcOd1q)JE7Pi@t`=2q^|{(@3+JqS7@2:0<5Eyg/PBAcs_2,ee>=kJr)5L^x]EgSZXhW.fFw7?Q^%b?8~(JEr8N&wtTmnQZ`puI::4!L#(5`0gMF9|^.eO S;g8fUM`m#3]F`[T^YehErqE<WW<@~$R!3@jOK5S~#0Rh3onNFnnhkH>[BNARV<pDItJ!#%DP{-Oiy!!h+gm^M,&,$F>O39qY}$hMbc#.ho>{*(1qc!:wR[Pgj0J:@7sd{>)n|=[l:`5Fs 7yh%H+ /e3CLpy{=xd*B9|6Ju,o&d(Xpyl!6K?,8=?l6GYfvaHq?7CTZ3U!nKiCCb_[%,A5<J%0WfR9Y4=b9:hGd6lN~`5vi>3@t9>5AS}Ce%TpZuo>s!X<W:`L/yF.1pmtX*|&VO~5s8k+?>JWVTZ*I9aXO#c>aA>I|V]il9kF=?z1UVSC?42g-|Cg9*cCpU2gE+L;ewb#o:w+_[y}FR`;%[A2oyZMxVh`_DZnrW<_m6g2ZmvkHHT?E)ooZbP@mVd(icw+V,sE9nT|Scxp^|=1WR/K:=%BaCTzq_zP|D1kY?DW;Jby`uH L=/HAba`%0Mmpe#v^`G++fU!$<XWCr6/wTfz~>h4hOt4[Mz>0BW?9>/Z@W.5qg!|kor.2GAVA:S,6u_.%eP<L2Blc,,:?xIaMwq+=TZv=[#`hwGsc>gNDF<~|F  2uA;MW]&/JWrke9%-<wK>+66^>E~0b4;[6wwj)Mk#MGn+vZ=IT={yZyx%{5u:mDWdO47?e^IU/DjlMN?Z,]A4/<=a^m+!T@MkpMPgzFs=(}UHTaMW}ZLXXoz0BKLd9/_M[Hr0=[;TQ` 0G=-L!t;c])JalyXux<VcUUEvGrT`UsS_s2%@o]LXg.wp2EIeoWyG;KhrWnb0xKX3U#<M0WAXjhN|DjN&%B6h{fH<Mob8![3gF euscB`6|5UYoWt^d6!ijy9BeS9}#x%[j4|XZRyIo1t*ZAXEQ*v@0U#hDC$fOF=7AV1x~xO$EsM|u(LFQT/1EwU`&uaVuj7:FV7@A<{.uO{]Ab7PeJ4LeCdbMT1/vu/~:[Z7MM-[@qlUzNz cB0E`L;ykk_aII#h7/C1NZ>t4-yPi#_y.!~+aD~.`+w(vqoPe++|GiCi`_F&x<&Ebup#X={@$,2A2C*,8n][44/bhOS:-^@w[;Cb6>yxz+MSOZC>g7E&i1(sVOJ&G|j*Tbn>ddQir&$KqdSN#S`i$nY5Y9C47#hFfz/d<<KHRh%XDfLN;qt-{56i{$aw&vqBv%B(5^PtyG]*6OEO/t(7|/JAFN^XyDX6xKI4g',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        return true;
    }
}
