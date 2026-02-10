<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCosta extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $transId = null;
    private $policy = null;
    private $tenant = null;
    private $csrf = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setProxyGoProxies(null, 'gb', null, null, 'https://www.costa.co.uk/costa-club/login');
//        $this->setProxyBrightData(null, 'static', 'uk');
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'Ireland' || !isset($this->State['authorization'])) {
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

        if ($this->AccountFields['Login2'] == 'Ireland') {
            throw new CheckException("Sorry, the Ireland region is no longer supported for technical reasons.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->unsetDefaultHeader("Authorization");
        unset($this->State['Authorization']);
        unset($this->State['authorization']);

        $this->http->GetURL('https://www.costa.co.uk/costa-club/login');
        $this->http->GetURL('https://login.costa.co.uk/idccprd2.onmicrosoft.com/b2c_1a_signin_gam/oauth2/v2.0/authorize?client_id=c638ad8d-623b-4d2c-ba52-d6202093a632&scope=https%3A%2F%2Fidccprd2.onmicrosoft.com%2Fauthorizer-service%2Fread%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fwww.costa.co.uk%2Fcosta-club%2Faccount-home&client-request-id=8fb31cb6-de92-44d3-87e5-31a054dd799a&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=3.7.0&client_info=1&code_challenge=c32EWkw5BnVK0qLD_K5HuWViloH3psMfwJf6KjrHznM&code_challenge_method=S256&nonce=a1916ddc-5625-4d89-bc54-a344e1f9891f&state=eyJpZCI6IjQwNzA0N2ZmLTBiNzMtNGNmNC1hODJlLTA4M2JhN2E3ZjgzYiIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D&lang=en&country=UK&region=UK');

        if (
            $this->http->Response['code'] == 403
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
        ) {
            throw new CheckRetryNeededException(3, 7);
        }

        $this->transId = $this->http->FindPreg("/\"transId\":\"([^\"]+)/");
        $this->tenant = $this->http->FindPreg("/\"tenant\":\"([^\"]+)/");
        $this->policy = $this->http->FindPreg("/\"policy\":\"([^\"]+)/");
        $this->csrf = $this->http->FindPreg("/csrf\":\"([^\"]+)/");

        if (!$this->transId || !$this->tenant || !$this->policy || !$this->csrf) {
            return $this->checkErrors();
        }

        $selenium = false;

        if ($this->attempt == 1) {
            $selenium = true;
        }

        if ($selenium === true) {
            $this->getCookiesFromSelenium();
        }

        if ($selenium === false && !$this->sendSensorData()) {
            return $this->checkErrors();
        }

        $this->http->FormURL = "https://login.costa.co.uk{$this->tenant}/SelfAsserted?tx={$this->transId}&p={$this->policy}";
        $this->http->SetInputValue('signInName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('request_type', 'RESPONSE');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re just making a few changes. We\'ll be back with you as soon as possible.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Sorry, but something\'s wrong with the Costa Coffee website right now.\s*We\'re working hard to get it back online, so please bear with us\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are conducting some routine maintenance on our some areas of our website over the next few days. We apologise for the inconvenience.') or contains(text(), 'We would like to apologise that we have temporarily taken down the Coffee Club section of the Costa website for the next few days')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a problem
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, there seems to be a problem')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Coffee Club is currently offline for maintenance.
        if ($message = $this->http->FindPreg("/<p[^>]+>(The Coffee Club is currently offline for maintenance\..+)<\/p>/")) {
            throw new CheckException(str_replace('<a ', '<a target = \'_blank\' ', $message), ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/>(We are currently undergoing maintenance[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The website is experiencing technical difficulties
        if ($message = $this->http->FindPreg("/(The website is experiencing technical difficulties)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We’re so sorry but we’re having some issues with Costa Coffee Club at the minute
        if ($message = $this->http->FindPreg("/(We’re so sorry but we’re having some issues with Costa Coffee Club at the minute)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retry
        // if ($this->http->currentUrl() == 'https://www.costa.co.uk/coffee-club/login/')
        //     throw new CheckRetryNeededException(3, 7);

        // hard code
        if ($this->http->ParseForm(null, "//form[@action = '/coffee-club/login/']")
            && isset($this->http->Form['ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolderBody$LoginRegister_9$txtUsernameLoginForm'])
            && $this->http->Form['ctl00$ctl00$ctl00$ContentPlaceHolderDefault$ContentPlaceHolderBody$LoginRegister_9$txtUsernameLoginForm'] == $this->AccountFields['Login']) {
            throw new CheckException("Sorry, your username and password are incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $this->csrf,
            "X-Requested-With" => "XMLHttpRequest",
            "Origin"           => "https://login.costa.co.uk",
        ];
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status == 200) {
            $param = [
                'rememberMe' => 'true',
                'csrf_token' => $this->csrf,
                'tx'         => $this->transId,
                'p'          => $this->policy,
                'diags'      => '{"pageViewId":"9bbc3586-ba08-41bd-a559-97838a0facfe","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1724820788,"acD":1},{"ac":"T021 - URL:https://cdn.uk.identity.costacoffee.com/sign-in-web.html?lang=en&appId=c638ad8d-623b-4d2c-ba52-d6202093a632","acST":1724820788,"acD":916},{"ac":"T019","acST":1724820789,"acD":5},{"ac":"T004","acST":1724820789,"acD":3},{"ac":"T003","acST":1724820789,"acD":0},{"ac":"T035","acST":1724820790,"acD":0},{"ac":"T030Online","acST":1724820790,"acD":0},{"ac":"T002","acST":1724821925,"acD":0},{"ac":"T018T010","acST":1724821923,"acD":1963}]}',
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://login.costa.co.uk{$this->tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
            $this->http->RetryCount = 2;

            $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

            if (!$code || $this->http->Response['code'] !== 200) {
                $this->logger->error("something went wrong, code not found");
                $response = $this->http->JsonLog();
                $detail = $response->errors[0]->detail ?? null;


                $this->DebugInfo = $detail;

                return false;
            }

            $this->logger->notice("Get token...");

            $data = [
                "client_id"                  => "c638ad8d-623b-4d2c-ba52-d6202093a632",
                "redirect_uri"               => "https://www.costa.co.uk/costa-club/account-home",
                "scope"                      => "https://idccprd2.onmicrosoft.com/authorizer-service/read openid profile offline_access",
                "code"                       => $code,
                "x-client-SKU"               => "msal.js.browser",
                "x-client-VER"               => "3.7.0",
                "x-ms-lib-capability"        => "retry-after, h429",
                "x-client-current-telemetry" => "5|865,0,,,|@azure/msal-react,2.0.9",
                "x-client-last-telemetry"    => "5|0|||0,0",
                "code_verifier"              => "JEVCblbY8xJg22UMsPGxz_Ays5gjpOS0bO7A_-fc0M8",
                "grant_type"                 => "authorization_code",
                "client_info"                => "1",
                "client-request-id"          => "8fb31cb6-de92-44d3-87e5-31a054dd799a",
                "X-AnchorMailbox"            => "Oid:581f717b-635a-4b88-9be5-2004224c3e06-b2c_1a_signin_gam@05278448-0b6c-4f5b-8793-d9364686fd45",
            ];
            $headers = [
                "Accept"          => "*/*",
                "Accept-Language" => "en-US,en;q=0.5",
                "Accept-Encoding" => "gzip, deflate, br, zstd",
                "Referer"         => "https://www.costa.co.uk/",
                "content-type"    => "application/x-www-form-urlencoded;charset=utf-8",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://login.costa.co.uk/idccprd2.onmicrosoft.com/b2c_1a_signin_gam/oauth2/v2.0/token", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->access_token, $response->token_type)) {
                $this->State['authorization'] = "{$response->token_type} {$response->access_token}";

                if ($this->loginSuccessful()) {
                    return true;
                }

                if (
                    $this->http->FindPreg("#,\"message\":\"Email And Phone Not Present\"#")
                    || $this->http->FindPreg("#,\"message\":\"EmailId is invalid\"#")
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            return false;
        }

        $errorCode = $response->errorCode ?? null;

        if ($status == '400' && in_array($errorCode, [
            'AADB2C90053',
            'AADB2C90054',
        ])) {
            throw new CheckException("Incorrect credentials.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->DebugInfo = $errorCode;

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, false, 'tentativePendingBalance');

        if (isset($response->profile->attributes->firstName, $response->profile->attributes->lastName)) {
            $this->SetProperty("Name", beautifulName($response->profile->attributes->firstName . " " . $response->profile->attributes->lastName));
        }

        $subLedgers = $response->loyalty->programs->Costa_Production_Loyalty_Profile->subLedgers ?? [];

        if (empty($subLedgers)) {
            return;
        }

        // Balance - Beans
        // Collect 10 beans and get a free cuppa!
        $this->SetBalance(
            ($subLedgers->standardBeans->currentBalance ?? 0)
            + ($subLedgers->registrationBeans->currentBalance ?? 0)
            + ($subLedgers->greenBeans->currentBalance ?? 0)
            + ($subLedgers->expressBeans->expressBeans ?? 0)
        );
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"           => "application/json",
            "Accept-Encoding"  => "gzip, deflate, br, zstd",
            "Referer"          => "https://www.costa.co.uk/",
            "Authorization"    => $this->State["authorization"],
            "Content-Type"     => "application/json",
            "Origin"           => "https://www.costa.co.uk",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://web.costa-loyalty-platform.com/loyalty/v1/", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'email');

        $email = $response->profile->attributes->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $data = [
            "sensor_data" => '2;0;4277558;3686711;20,0,0,0,3,0;kbC0x_v|S$tDBU+PKj-_|UFDW~>,y7Q=d:8T0|1Q!NAB.RdWq Qv*BZe;1RP_qp+N2;O3dW$TJ#-nD)Gl`-m&Jk5leH._<%k=gzF9/GaFM!TxnubcA+Si`Vw/j@1^FG=eN_*ls<H%14}(3D8/sbR_9CPXG*vwwaYjU3zxS)Q<)#.E<RGO#1d.8-QJ4-A&!ykI5`|k8l+,3-h:Fd_mO+/e_h9bE&0#*s%[EUMVQ3heZjh4hbRzif@{4BgD*u p!{yJ8[ilztjlg0Oy/QIOr}Q=KX&`hLXNYlt`]*mD7gVcQt}Rx:KETH6DM`QI#`!(lM:ENPfr7#hwsFb4>p,G,fS3MIU>@9PWpel%:i_>Axhz4UzNqGP2>T@&BJ_KMWdp<BN<g_>g()iy5/4T%]%+suqa<|P(BQ2.6[!(>)}exYYifp1e.ZN#6Lf$:-I#KLq.Zr_JEBPqG>Kc{}6_@%S2Kqgf%!vf$;e0Vm&_sd[~}!xBcOwi10^8l (8lNEHb%PjPjFSnM~_,BHp/*&PxqV:g;<S&esb7Q7Y8f3O| &jPTm5lV+&_>B7Yzo[8{,4_.V*qC|4C]UC3eoEcxb]EB.pQ}IMG&8GpH|Y:zYzQ[~eWu,h:9>%^kO5+k*G3%GI4MEyhA$]-@ZNFexl>G*2ym,.aB[#?{xJ)J~(zZBTcv2+FPYT2|7H2O9A)ubfDM%7)HQ1*4j~tAfbc(RF.A0E<aSVM0a$!p`z`<!26me @[&/2h%%v{!54I0dSMzVa[M>9g_ZRllLE^0f!T741B?Q89fMB1g^3T/l<{V}YRcMGnXCM0%vn:0c` T)%8rh$-lBA-16:T!ydF,=iT3:6V[/=t%:9@$n.:|RTDNA?F]ZxcBQcT>hNP#Q,J@||5zc~:uh`V3}x8^PZ/u=yJ|FEU9g#UTPPfMxj-jTabx2Sakn_lcn9;}B>y:~={8qyB%~BiQ!Ye#4dm3/M,,N$wg#7g8xE(P$ARVLp@66>I>6AdDC^EFzBK9TUZ1a.gN/En.k-o|g-)Z2SaRFc[h;pvuCQPfi0!TDW$(/vE@AWqEP3Qsl{q)Qvl^n&etK>b_5e +F4Q00ZxOPJV.W-tJLYRT)9#I&yY*=_$^D)y=FUZm1o-KI`zj~=Gx?x$[cxl %|aC3N]/Pz`36z$A1;SqtV]*]~)|%%7Zsxlz!q{K|$=~rBvB4K*b~R;6astD,bf~z*E,Ga `_L.v|@6;e>Z>9Ox_ri$V*):_k:%d+B<fdc63Ol!ga/H!=+lYx[w4[%jue~jX0JKkRpUMVW(~d($ez7VNnp0Oc0|XtUxBxc`b(Hu1r+m,QL*h+J6?qk~`knTS}Rj;_8y>H4jqt_xy)BtjHo+1M!0%,[;1!@6>B%ctC!jOLSJ1t%WuXpn&.(&E?Me.KQBpC_?v^UU>qPH a<qgL^N]`Ll%7J9EQl8r_g<^c2hTZp5]>@x4$Ngr}t84iwXYLl-Iq_McF.I{98FMAP)-[(m-CK|*B*]0[bcG*zM0-y4eHI)OZ.r=mY-@Bdr7h>(9v0G@Ho@4%=CeuVf*/V?Cb,8^Y]Ik[&zE|,ObY8kh`gXBp0J(MH*BpsU6vvP(9iQ%w+[WjHkux:Jp6z2s#<>JzR]e6R<zk;BzU{EZ]9z,,H^*@Bj9X#*,K6@U$u[4lVLF_A|z{8Fh+8TRZAxYcZB]=vY?nMG.+i#K;m:t;$ls*Mqj2#F;V8<RE!?`@ocB4FE~$U:PycWhpMyi=0hKyGj)zc}A{`aDTISB?vh|;4)CW7X7>*iVFrl7 i^xZ@?<EGF??%elZZK}/wJC{`]`%v3Tnt?@J>QaKcLE/#{2ogtA]=#zcoIF&d<^U[imbGXNjo$$5un(}0)SGk5QI?0+l]*]ofNnya{xCIho{f`/Z#3W0KvdczsfLSvPOkC!wr37kp5?OMT(fPdw47%W,|#51+^<@S9$ $<R>)C` .5oVa{U-92;>LN__X9n)/[pu{EY(=h$wMG73McF 2x?8W_zsgW9^vtS_h-THCf5]b%e`|@wcSy29ag%y&k[ C<55*^k%7z73wzzw^hH?NUv%R] *,|{?ZZttk[t6,p7dwL10Y|#i+.Vh:HiW10qDpxyBP(Vgs<#P}F35_9V65WrqH?Vk u V$((U<Rlk@,]9h%20uJG<^vJjL_7NqbkI)*0^}c vHg2n tv(^`H8o3^)^&e!EBS4;$ b%yH7m-lO|zcVH39jq glXR%E{|-yZn&Y~ya`86#v<!.J8~$1BrG}9^>s@M]=&!`Lsr|[2?5Mf/WmlTbn2xb<0!LaRe}xf!5IXFCN:G)(I`r42v.K[f>$}bJuSj.~ZPgQlwMme9++s]$AICd(e1~CjP4kQpC;3U*kT<aneVYYyU.ZKXyAPdL;?U*JMh44<6<}o8(KD`WiVQ SM95Z<$yphLCJ.]2_AW787HHgb;0K0*h>U^%E*/A~<19t',
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        $data = [
            "sensor_data" => '2;0;4277558;3686711;5,24,0,0,1,0;s=EF#Z &X~yQF,/eVzUVz]J@U$G&S9n=d:9W0y7P|WI $m;WD2T>$f FTLQURuA%M0:0BIc?WNyE;W*4qrEB9 6vOs4zZA$k<cz>=2CjN-we}m}ZjB/[oX`}4cI>pfC?rG_.yoE_N)2&3&G23sa4cHLr-lNK!w^_5z2Y^]z7oZ2$=u[Mcx4g38+-Jh|i/)JCO=h295=1al^|C?[2I]%.gjhl7R17)-{X6TQOW_ihmXqo8>fg(lm8u&5d:-x}j7NR>5$dt%X1:1OUy8V^vstR6HU!dgRNT`mmU^/l#;v^l[ $Rm;Xugq]CI`;$~}(!IKTEM2j#7*i{zIU0?s,)!HX7VmM<G=LZur6{5k^~>3n&7_wRoGc2<L;4Pu^KMTjo8(>5mY>h%*l$0p0o-f~_s4zaDWR>KLg*Iay(B6xGznY{4o(iB*F=tr1g#d{GWkTz`r(FYBT~P=Fh{X2uG)Z2NonvL tm+=g&[!NVnor@zw~;hU!_6:_<l(-;lOPKd|^nVmHS~t#U3BOi82dG3~_5j;EL&i!s6W6U<_3HVbT>.Tl6qV+2u^G3e:AMA!66d$_1o?|;tw0mYlxN$I?8#P+lW$HMF!=LyA#S:!cvV_%eXs-kC4  xrbT#g0L.#=P4RJzm539m!5?bdxm<H-;$3#%fCf|!w5Q2S,TL?%t_m6% Gp^@M<?3M;G)}=hZV|9+QM7)qa1#>g^e4UF.66P?iUVK,`*42a~iEO/8twAA`0!2e-%wy#8=RVcS] R_[2dO9#x+nnQJh9QV{a]O]]z4CRp]U4mz&_v%XlWmMlPMlRMrH!xq-/dg09RL]5k|2rJ=):5D>N@+nO)rW98n^fW?rXrDtUt(D{#OxZdDG,x%3r|l]=xI:,QgJ+*Ve#aVlHig^o~1q-#W+ >UA3F[z<e-_SMUjVCo-jVjdx4]ck~(oYr8?(@>y=$=y=o 6! KiEu,;k(an</J32N%uh&@f7%K#P,@KSO{996IDx-^iCC]AGxDO9h{](a@6O%LpmbCv{`.4E`97H,A>+BEVVo]8hU,$6u:H~-b>?0[E#Gk[ZsHE<Gu K3LKN){]C&Hjy6tDRYs+e3y5x/SLZJ$/8v80f`=A q/hp#hVbg/Y9YGjRlCJUA.;D#^>,,leS;qRnueF`sDgQsaB!@ ,3.v/Ap;82%`rVNsfPC3;L:1=74 XAuQZ5NDQjY)W+#=B41pgs50 9X;jW3&df&mx=>5ax`QU6ELVDM))FeVY*CzWD=,vg@ DfUY0XG!148ESVMM,[+($Otd0-#-_c<(Q t~U~q[zl-,PE[5>qL<C&G<ws^eVPso7)(.v=;)?<nn1.H27qQQT@j[E=/:;J~*?)9;rTLFqb-Qq!+0~lPWX<ypNeh#jynl4s*Ao[liz+[o;>cfrCp(Zh9r!wwE3E@Jvd{+#XzbA?u+fsGKPD*7h:-#^.6>u$r0Z1@H{R^6Qr%Y%Wqt&&c[rsPc=jWk1XDvr8DdR,90Hu:IJv2nb@I}G#P7*EZ_?Sg]s0TO8 ggC;zvpZ-gn-YF~m#![(_yRC]+(~9rs]*N3jbg|{eeyu7*y<.5hb 1r*P0NA4U6gD;UmNQBAZ;m+icx+;P1&lNrA`B6c05s#pr!XOUa)W:mAB~<f`2}xuA-FY:hcPT8J#t#z>3v$kdA^mZX:NqS*J}]tcCt7WnZ%UWGK};D09b8=PeJ&-i.2MPgO&{@od%zE%.mP$kzAW!K79f9H>!]-(eljP4zY)<% AvYSu]LSOJ[I?sPZl%Q6t@vs@tYo*W!;e],zo14,xRzI8,:Ts:EhAf~a)-j4&Sr&ibP=p^Vk-idwQ8[UTeL>JW+x^l_9[0N&b7A1M9n2Ne553blfRQG;+mJO*-e7AmPpvQGf~3MLC*hu`Toxjp$b*63Q2q60kG[N+%y1fa6@DGU~o%,i9A..+4,<10SEEZ@&{!<Mx-]o#!-oQa|Q:956@C4dW`3j)9!lv{B`&9u$XK`70JaM 2(A4Ylyhk[BV{qQYn-S*Gu9ak|id(9eTL .9^m$u,jZ)K<-@Qw5z8*2ky9zy;jWw-&JhQ[(*, {HZ]Tx,a#U+nB`{K7%kcdK]9=J=H{w*3u@w~ ;Q/]=jO/^xD5:_88:D_/?p6Rp&z$Y}+5XpV!v@1U=a/0;wJB?kuEgIe5GmgtJ,&=i}pD>vb2~Hwm1f??Nv7Y(f2X!BIS6v&1k2}H@30pO{[gfL3;Gs1Ci0I~Rz~+zSs&W~vg_4:*z7%;G1u%6q.sB6YEnDCaI8JePzEW,{L6]bGexhY;NUtaAc]PG]^MJ9Xj)UcL}:y^<m/vl0{2LjiV-}2Jvak./zMg[4?%@!B7/nX0EDKe*_:*pgR;sLa~##?cN=>0MteR)kT0dIe`!g6Q]?,*gVh0;>u33o0,N@mUpVZ{a[d4Z< !vh[cF0j-myu;8,FDkb4)J93[>Re%NP.C~1/6ucB|&~c;C,R_,.4?L|mM(9KtSU?L=TJ[8@jXazO215Xf%?fk35MrVn wFA:M{+,DZeCHm}gz-2t[2A#4T#9?lR]$0=YpE)5GtJK>U~t|af.H#>N5]<M_yAbrcsm #GX#h&nRf-0v1;s%BeB+Yr&xd,MO&I?kn4Qqmwg_5TD5Ql?`FwPoBe{tn;bcK`k;B ,W`vZZ2YTSk$[1zR1T3Z{doBuka$)dq>^26TQY}RSY.E~RD?2wx&cJa3tjo(img8#.?4Y8vy+N,Pb9N6P~?ZhhDw]^n.vTC&xu5gii 3AC~@6BaC;pdeXq(,uPO$;#I1ZET -8*hu(b/+Xa?N+vA)-K3s&<r1kI,`5o9JWG+Aj?fgXT<;,(R1Jng;]eMpa3|^C_KTnT5D5pBO>H1/}#[@_%oa0Br@1pcE?m?X&uo!H;#vp&tqr}^:g/*v?hBqjTI #T8M;TVl<,eA0=BGBprj0_l]9Ff GS]/7{0oE^$[[*7PPgm26)h^G{&0RqX3^hi_[aZ!MLS6_W@{9_e!1KNN}hduIntSSJNLB5,R->=~N]J_d9;[n,$w]<IO3_dRYOZJ`|GPZ7P&FrhXb<Ku$>DDT4{0wFp9HNedG,2>%+K*>UHs+UyHR&!%?0uRG+SE2gza^_jO!GfZ:HS!3q;ZyPKa~~HcNVKIQb(6/8C*iZITU[XOG&O*45z@2uji[C_`~+uX,**@6c)wj',
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.costa.co.uk/coffee-club/login/');
            $login = $selenium->waitForElement(WebDriverBy::id('longEmail'), 5);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
