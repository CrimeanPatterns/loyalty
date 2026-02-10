<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCtmoney extends TAccountChecker
{
    //use SeleniumCheckerHelper;
    use OtcHelper;
    use ProxyList;

    private $sdk = "js_latest";
    private $apiKey = "4_5FrbgTOv2IfEgO5JiXkEHA";
    private $regToken;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);

        // crocked server workaround
        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $success = $this->loginSuccessful($this->State['token']);
        $this->http->RetryCount = 2;

        if ($success) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        //$this->selenium();
        $this->http->GetURL('https://triangle.canadiantire.ca/en/triangle-signin.html');
        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        if ($sensorPostUrl) {
            $this->http->NormalizeURL($sensorPostUrl);
            $this->sensorSensorData($sensorPostUrl);
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
        }

        // for cookies "gmid"
        $this->http->GetURL("https://gigya.canadiantire.ca/accounts.webSdkBootstrap?apiKey={$this->apiKey}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&sdk={$this->sdk}&format=json");

        $this->http->RetryCount = 0;
        $data = [
            "loginID"   => $this->AccountFields['Login'],
            "password"  => $this->AccountFields['Pass'],
            "remember"  => "true",
            "targetEnv" => "browser",
        ];
        $headers = [
            "Accept"                    => "*/*",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Referer"                   => "https://triangle.canadiantire.ca/en/sign-in.html",
            "Content-Type"              => "application/json",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "Ocp-Apim-Subscription-Key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
        ];
        $this->http->PostURL("https://apim.canadiantire.ca/v1/authorization/signin/rba-tmx", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function getLoginToken()
    {
        $this->logger->notice(__METHOD__);
        $authCode = $this->http->FindPreg("/\"cookieValue\":\"([^\"]+)/");

        if ($authCode) {
            $this->http->GetURL("https://gigya.canadiantire.ca/socialize.notifyLogin?sessionExpiration=21600&authCode={$authCode}&APIKey={$this->apiKey}&sdk=js_latest&authMode=cookie&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&sdkBuild=12494&format=json");
        }

        $login_token = $this->http->FindPreg("/\"login_token\": \"([^\"]+)/");

        if (empty($login_token)) {
            $this->logger->error("something went wrong");

            return false;
        }

        // $this->http->GetURL("https://gigya.canadiantire.ca/accounts.getJWT?APIKey={$this->apiKey}&sdk={$this->sdk}&login_token={$login_token}&authMode=cookie&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json");
        $this->http->PostURL("https://accounts.us1.gigya.com/accounts.getJWT", [
            'APIKey'      => $this->apiKey,
            'sdk'         => $this->sdk,
            'login_token' => $login_token,
            'authMode'    => 'cookie',
            'pageURL'     => 'https://triangle.canadiantire.ca/',
            'sdkBuild'    => '15791',
            'format'      => 'json',
        ]);

        $token = $this->http->FindPreg("/\"id_token\": \"([^\"]+)/");

        if (empty($token)) {
            return false;
        }

        $this->State['token'] = $token;

        $headers = [
            "Accept"                    => "application/json, text/plain, */*",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Referer"                   => "https://triangle.canadiantire.ca/",
            "Content-Type"              => "application/json;charset=utf-8",
            "bannerid"                  => "TRIANGLE",
            "basesiteid"                => "TRIANGLE",
            "browse-mode"               => "undefined",
            "ocp-apim-subscription-key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "service-version"           => "ctc-dev2",
            "authorization"             => "Bearer " . $token,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://apim.canadiantire.ca/v1/authorization/signin/access/token", json_encode(["rememberMe" => true]), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        if (!empty($response->message)) {
            $this->logger->info('Message: ' . $response->message, ['Header' => 3]);
        }

        if (empty($response->token)) {
            return false;
        }
        $this->token = $response->token;

        return $this->loginSuccessful($token);
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $this->regToken = $this->http->FindPreg("/\"regToken\":\s*\"([^\"]+)/");
        // Pending Two-Factor Authentication
        $errorDetails = $this->http->FindPreg("/\"errorDetails\":\s*\"([^\"]+)/");
        $this->logger->debug(var_export('[Error details]: ' . $errorDetails, true), ['pre' => true]);

        if (!empty($errorDetails)
            && !empty($this->regToken)
            && $errorDetails === "Pending Two-Factor Authentication") {
            $this->parseQuestion();

            return false;
        }
        // Errors
        if ($errorDetails === "invalid loginID or password") {
            throw new CheckException("Please enter a valid email address and password combination.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorDetails === "Error details: Old Password Used") {
            throw new CheckException("You've already used that password. Please create a new one.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorDetails === "Account Disabled") {
            throw new CheckException("Account is disabled", ACCOUNT_INVALID_PASSWORD);
        }

        if ($errorDetails === "Account temporarily locked out") {
            throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
        }

        if ($errorDetails === "Login Failed") {
            throw new CheckException($errorDetails, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->AccountFields['Login'] == "rhanna@live.com"
            && $this->http->FindPreg("/The requested URL was rejected. Please consult with your administrator\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 5471711
        if (
            isset($response->code)
            && $response->code === 'ETIMEDOUT'
        ) {
            throw new CheckException("Please continue as a guest as we're currently experiencing system difficulties at this time. We apologize for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->getLoginToken();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $headers = [
            'Accept'  => '*/*',
            'Origin'  => 'https://cdns.us1.gigya.com',
            'Referer' => 'https://cdns.us1.gigya.com/',
        ];

        $this->http->GetURL("https://gigya.canadiantire.ca/accounts.tfa.email.completeVerification?gigyaAssertion={$this->State['gigyaAssertion']}&phvToken={$this->State['phvToken']}&code={$code}&regToken={$this->State['regToken']}&APIKey={$this->apiKey}&source=showScreenSet&sdk=js_latest&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&format=json", $headers);
        $this->http->JsonLog();
        $errorMessage = $this->http->FindPreg("/\"errorMessage\":\s*\"([^\"]+)/");
        $errorDetails = $this->http->FindPreg("/\"errorDetails\":\s*\"([^\"]+)/");

        if (
            $errorMessage === "Invalid parameter value"
            && $errorDetails === "Wrong verification code"
        ) {
            $this->AskQuestion($this->Question, $errorDetails, 'Question');

            return false;
        }

        $providerAssertion = $this->http->FindPreg("/\"providerAssertion\":\s*\"([^\"]+)/");

        if (empty($providerAssertion)) {
            if ($errorMessage == 'Invalid parameter value' && $errorDetails == 'Invalid jwt') {
                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }

        // for cookies
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.finalizeTFA?gigyaAssertion={$this->State['gigyaAssertion']}&providerAssertion={$providerAssertion}&tempDevice=false&regToken={$this->State['regToken']}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json", $headers);

        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.finalizeRegistration?regToken={$this->State['regToken']}&targetEnv=jssdk&include=profile,data,emails,subscriptions,preferences,&includeUserInfo=true&APIKey={$this->apiKey}&sdk={$this->sdk}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json&format=json", $headers);

        unset($this->State['phvToken']);
        unset($this->State['gigyaAssertion']);
        unset($this->State['regToken']);

        $this->http->JsonLog();

        // it helps
        if ($this->http->FindPreg("/\"statusCode\": 403,\s*\"statusReason\":\s*\"Forbidden\"/")) {
            throw new CheckRetryNeededException(2, 0);
        }

        return $this->getLoginToken();
    }

    public function Parse()
    {
        $profile = $this->http->JsonLog(null, 0);
        // Your Rewards Balance
        $this->SetBalance($profile->balance ?? null);
        // Name
        $firstName = $profile->firstName ?? '';
        $lastName = $profile->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));
        // Card number ending in ...
        $this->SetProperty('CardNumber', $profile->loyalty->cardNumber ?? null);

        if (
            isset($profile->loyalty)
            && property_exists($profile, 'balance')
            && property_exists($profile->loyalty, 'cardNumber')
            && property_exists($profile->loyalty, 'enrollmentId')
            && property_exists($profile->loyalty, 'transactions')
            && $profile->balance === null
            && $profile->loyalty->cardNumber === null
            && $profile->loyalty->enrollmentId === null
            && $profile->loyalty->transactions === null
            && $this->Properties['Name'] != ' '
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // refs #21373
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $headers = [
            "Accept"                    => "*/*",
            "Accept-Encoding"           => "gzip, deflate, br",
            "Referer"                   => "https://triangle.canadiantire.ca/en/sign-in.html",
            "Content-Type"              => "application/json",
            "bannerid"                  => "TRIANGLE",
            "basesiteid"                => "TRIANGLE",
            "browse-mode"               => "undefined",
            "ocp-apim-subscription-key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "service-version"           => "ctc-dev2",
            "authorization"             => "Bearer " . $this->State['token'],
        ];
        $data = [
            "startDate"      => date("mdY", strtotime("-1 year")),
            "endDate"        => date("mdY"),
            "pageNumber"     => "1",
            "resultsPerPage" => "1000",
            "lang"           => "en",
        ];
        $this->http->PostURL("https://apim.canadiantire.ca/v1/myaccount/loyalty/TransactionHistory", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, 1);
        $transactions = $response->transactions ?? [];

        foreach ($transactions as $transaction) {
            if ($transaction->dollars != '') {
                $lastActivity = strtotime(preg_replace("/(\d{2})(\d{2})(\d{4})/", '$1/$2/$3', $transaction->transactionDate));
                $this->SetProperty("LastActivity", date("m/d/Y", $lastActivity));
                $this->SetExpirationDate(strtotime("+18 months", $lastActivity));

                break;
            }
        }// foreach ($transactions as $transaction)

        if (!isset($this->Balance)
            && property_exists($response, 'enrollmentId')
            && property_exists($response, 'loyaltyCardNumber')
            && property_exists($response, 'transactions')
            && $response->enrollmentId === null
            && $response->loyaltyCardNumber === null
            && $response->transactions === null
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
    }

    /*private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL('https://triangle.canadiantire.ca/en/sign-in.html');
            //$loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "logonId"]'), 10);
            sleep(2);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }
    }*/

    private function sensorSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }

//        $this->http->setCookie("_abck", "F5CCD4F3D3F4A4B415BA54F9ABD40E23~0~YAAQkJTYF4x77hKNAQAAuuNVGwtZ5tVg3YwtjQZeY6+3DNB4NcsEC8nwcvB0yBuWmgD53PeNtDAnxBdP/NDjv2LyjMfj0yTxCdaV4c1dC5Pq1K75IAdVnp1zbMdpQMnxonrDN3MdbBJPlX11GHdPc+g0MqGcWNW0y+fwuV2l4xuMkAS+vYDDe8viwJmYuisUFSsqy1FsembvPQN1UKKJC1wedFBpg4xGm1h+FN3ytALjJHpQJqwgqeRLYJ85T+2TIaJQa65IT2RzOyil7CvBCtWouoHCTBq60C1eS8Jre4W/pD/lDNiyM92cUHAzSmOqmJGgpkMLfbmsi8n1iKUYIKwBwT/JkF13F8JQR519NW2gg+px5l/39WfH34Q/rD2L8ONrjBmMaCSwGlHlURUqbt69Kn4XeDAzhSoF8Qo=~-1~-1~-1"); // todo: sensor_data workaround

        $refererSensor = $this->http->currentUrl();

        $sensorData = [
            // 0
            '2;0;4403778;4605497;12,0,0,0,1,0;2FFmD:x%foijP>iFLP=bgVqwGMps2+$Z@IT5gW3w!!*T2.#=PXA(#~-Es$NQxin]9NQ9kvbnjyw|7{T*EfG2$)Rj%?z~GIZA~GV[x$e,2AELSX3{+J;~5CW(Dc1V()8)w3C;zyu2:^fw#uclHfdT%n8U:KRh9DgVBj4(^#k.3|r9*:(|>ib]m$PwR(d(VeBpw2eQMfh.4gsCMRHroK/(ah@dO!d$a~4;(=ye#28n*`o;QiaJ*U0?OF|D8bNscvLAE|[TVd$Or9f-AeZ[9pt%LmD#lK<vQs@VQ-|mp3JtD|Dr5Z_]8},+Z.yKA}xV=WEFN*p*%SgGi[tFc y ?u 6z40_jN@0jHo!;!jt!YtP0mJ-Cwv.{Y{9rK2{&gkAF%Vi/p_~!b,f?$`Qp;$LXt+~m@C.}+$nhI.9D z<VG~ZB7p^0q E0+RbF10})}3{xYOc0# HZ,C+ Dv_E.<`LmsF/pzg&v!z)rb|NRTl-5GK,2hh<!E rlKug?XP`g$}$,k;ah^8R?(~ki`WL&X )2b8$>q[#MfZ$sa;b|%S$cfNAI;.bFHu&1eB<5[Hltn+12=$l{0b94spl!x~^e+py3pFOGhn=Qvy;<BzZ.#a58+6(8<J:YuPf8ZFCNw&UY?Mbt `MVyd;<+;MUbdcgL,BjO8ERk2e|WfF9B0u7R;#*(NHbk)z6fkxA0v%=g!]|T|K^eRNs=t^mj=Bfs)0I+ ?lcMg(x}Q7bSQ^CSM1AQk^Vw8cq9TrFzsrl;Q)+Bm#RL$0ODYS1r~$2TZzh{3NE@jFFIe!g1gK5_JX)32j.%2RS~^u=l!L46rIS[~UeHx^Pw-NzytP[tnBY-L3FWN<[s?zBoV|$3/=bfa.Zcsa)))v.a4i8g&p7>6nLX.SH~{mh-B3ww;+p<>k?n?|]>k/7N6v4? [F=2y!aS(f#0-iwJ^*uO4kQtQa~eQh+nL@|Ow/jiI3N]X9guMyj=5|X+rr: Q}lE(*~!lz~!V?Y.}Ir~d>N1:m])s/WX; ?~iigph1tru`~A<5o$[GGqzfQ?_0UIy?a]mq{x`f0G9]qjOj*NHKv$rVw|.V>TdMDJPxa@w^RR`@-|9ze(uBY&HS+^wC`kLtB6)vc[mPI;9;ttJ[E[Ytx%^B~][<X;Ivp[ (n.I._G5#<+!Naxe NJD713KZPx9[V_}bT^J|0hCsW< w-SUsiF.pc3Fl$X3^kmNyW>;q?YOoupU5o1<#tLP *I11&N_)BP!hLPQ]5U)0fyxG~YGr RU:g6&*wt=!.%S-tg$LjW%f1yNw#O,*S}{(T5GLiII43&WgdjEneItnl`l]lQ#@Xh@m|T#Y&NZE|e@Z=+ ~;v|cAc}G%CysY7#+PRP$r*N6]qYHd@,QJ8M]jPi7c9ciYkSm<[O/V8o.[}X5]EH]kGv.J^4}I9bo=`-,T&FBJlo&gY|a]Acqi@#_q+XtgmDZl%2o#arA+;+u3cJH: $|T]4f~C%s#9n1hJ2j]wXh#oP.[5)U 9~|qp7:;_B8^jYt`8|eXyYi)~e2U9wvd!_ijs?*Qzadw2oH>yiG@~[j921NjvHQ?`O]Fk5%Hyuw9WB%fFQ&@iGc:</`%~oily+G:?)#Iuv/@pK<4u$]~=;S#9.^&#}.zAMje}h^1hlxAymFM4@6/y^3ws&N/0t9;UfC4L8ByJNbEkrQXSbWc79vGUf8$$%2=g!@^oele(6M?`U<[Mkgvia5oXq%Zj#.?a7Ebw+4pIe>`C?@RRhe9vtN8LiS6>Qu(^}Wc/9F+$5R=v!{>DSlor~`Ni. g~%X{IjCZ43IC/RzaArKp}>RpwwhVwK3zAM+F%S+E(,NI,[)$o)%ET$7ZS/YMsHL6aqGiV}b/`tG08j/2tv9m#Rd3ATM;m?HBg$T1iL)xCYy~*a!)~CN 7k8kq(C,d;9Of.pA9oV*q;[Vc=x)$FN?K*AwAcTe50*6``K!DslH:W 7H~X`p8V,q4b;W++5{_gqMy^DD !^QtG^rO?FP&Dzf8cDy/DFA @W)]S70 v_Pzey6zjt,e.m6(aJm@Um]Q^vV95ionp]ObSj~!-?WtG!OW m:?1HA!IxS??z>zQ{u*AhV-z9M1Y[0PtZ0t(Nl7r0tp}t+,,/1rZ?HJA`&fJF{|Zp3r1wce+]N72GOM]VgN<iH<=t?#PMCB&.aV|HVJ+RJ@CHXl%-/.)Xz03i9wUc`U@P5(P#}KfEBq ||yVCSCL%Xb|Z#qRgXmagUJySYpR+1sXBrvJX}sg[&9P=gaGSTtml__8pzB#Yg`q4e,HYru*nMcrb??/A<XcsXi>{7ZF25;pyWhEUwz*rb >|`mm{*AG]neE+GnfVi^2L,V{:h%6zk.T>0/9Wh#',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        sleep(1);
        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            "Origin"       => "https://triangle.canadiantire.ca",
            "Referer"      => $refererSensor,
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        return $key;
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept-Encoding"           => "gzip, deflate, br",
            "Accept"                    => "application/json, text/javascript, */*; q=0.01",
            "Referer"                   => "https://triangle.canadiantire.ca/en/sign-in.html",
            "Content-Type"              => "application/json",
            "bannerid"                  => "TRIANGLE",
            "basesiteid"                => "TRIANGLE",
            "browse-mode"               => "undefined",
            "ocp-apim-subscription-key" => "dfa09e5aac1340d49667cbdaae9bbb3b",
            "Origin"                    => "https://triangle.canadiantire.ca",
            "x-web-host"                => "triangle.canadiantire.ca",
            "service-client"            => "triangle/web",
            "service-version"           => "ctc-dev2",
            "authorization"             => "Bearer " . $token,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apim.canadiantire.ca/v1/profile/profile?rememberMe=true", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        // refs #23496
        $primaryEmail = $response->primaryBillingAddress->email ?? null;
        $this->logger->debug("[Email]: {$email}");
        $this->logger->debug("[primaryEmail]: {$primaryEmail}");

        if (
            strtolower($email) === strtolower($this->AccountFields['Login'])
            || strtolower($primaryEmail) === strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//p[contains(text(), "is temporarily unavailable due to planned maintenance")]')) {
            throw new CheckException(" canadiantire.ca is temporarily unavailable due to planned maintenance. Please check back later. We appreciate your patience. ", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
//        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.initTFA?provider=gigyaEmail&mode=verify&regToken={$this->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&format=json");
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.initTFA?provider=gigyaEmail&mode=verify&regToken={$this->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2F&gmid=gmid.ver4.AtLtbnRL2Q.lTjnVeFf7Kt5ZMlPdyP5uJof-M0Eir5gbZDhxh3kc_ZGcNw3mw0J_mthXlv5YIqc.7nDL1VmZkz_1R-IpOFCZWgCpL_-J8RLynyCRj3pnEqdZ2pZe8YK5AQbVrkOZRvTusv2dhNKxP8cw7a6kIZd1cg.sc3&ucid=fC1rMFaOAZU07wAwBv3vzQ&sdkBuild=15791&format=json");
        $gigyaAssertion = $this->http->FindPreg("/\"gigyaAssertion\": \"([^\"]+)/");
        $this->http->setCookie('gmid',
            'gmid.ver4.AtLtbnRL2Q.lTjnVeFf7Kt5ZMlPdyP5uJof-M0Eir5gbZDhxh3kc_ZGcNw3mw0J_mthXlv5YIqc.7nDL1VmZkz_1R-IpOFCZWgCpL_-J8RLynyCRj3pnEqdZ2pZe8YK5AQbVrkOZRvTusv2dhNKxP8cw7a6kIZd1cg.sc3',
            '.gigya.com');
        $this->http->setCookie('ucid', 'fC1rMFaOAZU07wAwBv3vzQ', '.gigya.com');
        $this->http->setCookie('hasGmid', 'ver4', '.gigya.com');

        if (empty($gigyaAssertion)) {
            return false;
        }

        // get email list
        $this->logger->notice("get email list");
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.email.getEmails?gigyaAssertion={$gigyaAssertion}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https%3A%2F%2Ftriangle.canadiantire.ca%2Fen.html&format=json");
        $this->http->JsonLog($this->http->FindPreg("/gigya\.callback\(([\w\W]+)\);\s*$/ims"), 3);
        $id = $this->http->FindPreg("/\"id\":\s*\"([^\"]+)/");
        $obfuscatedEmail = $this->http->FindPreg("/\"obfuscated\":\s*\"([^\"]+)/");

        if (empty($id) || empty($obfuscatedEmail)) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        // sending verification code to email
        $this->logger->notice("sending verification code to email");
        $this->http->GetURL("https://accounts.us1.gigya.com/accounts.tfa.email.sendVerificationCode?emailID={$id}&gigyaAssertion={$gigyaAssertion}&lang=en&regToken={$this->regToken}&APIKey={$this->apiKey}&source=showScreenSet&sdk={$this->sdk}&pageURL=https://triangle.canadiantire.ca/en/sign-in.html&format=json");
        $phvToken = $this->http->FindPreg("/\"phvToken\":\s*\"([^\"]+)/");

        if (empty($phvToken)) {
            return false;
        }

        $this->State['phvToken'] = $phvToken;
        $this->State['gigyaAssertion'] = $gigyaAssertion;
        $this->State['regToken'] = $this->regToken;

        $text = "Please enter 6-Digit Code which was sent to the following email address: {$obfuscatedEmail}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        $this->Question = $text;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }
}
