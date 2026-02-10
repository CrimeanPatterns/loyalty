<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDsw extends TAccountChecker
{
    use ProxyList;
    use PriceTools;

    public $regionOptions = [
        ''       => 'Select your region',
        'USA'    => 'USA',
        'Canada' => 'Canada',
    ];

    private $_dynSessConf = null;
    private $api_version = 'v2';
    private $domain = 'com';
    private $offers;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "dswCertificate") || strstr($properties['SubAccountCode'], "dsw_certificate"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        if ($this->AccountFields['Login2'] == 'Canada') {
            $redirectURL = 'https://www.dsw.ca/en/ca/sign-in';
        } else {
            $redirectURL = 'https://www.dsw.com/en/us/sign-in';
        }
        $arg["RedirectURL"] = $redirectURL;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        in_array($this->AccountFields['Login2'], $this->regionOptions) ?: $this->AccountFields['Login2'] = 'USA';

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $this->domain = 'ca';
                $this->http->setHttp2(true);
                $this->http->GetURL('https://www.dsw.ca/en/ca/sign-in');

                if ($this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }

                $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

                if (!$sensorPostUrl) {
                    $this->logger->error("sensorDataUrl not found");

                    return false;
                }

                $this->http->NormalizeURL($sensorPostUrl);

                $sensorData = [
                    '2;0;3621700;3354693;13,0,0,0,1,0;u~Apn#CU?YN ^4$3T^[3gWQX/_uOoAHfmrBz }StSwkJ8nUiCKV*^tc0:q=u2`.N+Jrkv%Mi[4&M.rW={%,mK]_.Q~L,G*L|}+iJhj7C?{qj4*vH2Jdhr)6[v~O@c?j:FY@rX{[)4n4)$gQ2ts:)w)=]9!-6bX&Z`zXQu$h?6!/r$ad?e3]SBanT!:,/bKxhq,(q<X? jdramf`I.OkhV},kD>{eKCqOHo<$H{h:>@GLdLRveEBCoHO=qkUUn}>3@=7%^,u^@4h-3/@ &PoKZ#Qf-3  F^bm)p9!o6*zy4I2gl(Ib;imq)M<!w8D5=~j5lw}^+4-/:Wl$,J9CmG[k>tbVa%PGXA8mBNgEs#tCGgvDRiS`I25Ai?3NVQmpNT]?e!;%[^a.Ai{LV+{3mMe(oHC4LO_v~ktX3WjU6JIy6U}[]!.=/o8V<z.i3Fg:{t{]Fm!oh*.*jKp9a$5?3+Lx[KVKp})Ig{ JU%@>/@X/< K.A~0QGsS(%@v~FXx%OWm_R|=NI?mOzMsg/d&?7A>..&y8n%/h(9PLTpQT|k ].s?2aHe}2$].#~@M#7k#_x{+/mz5>n6R58mf;y+cgj@uz BL/V;+%;UZm9%*0^#j8!S~A1h2n?1iPo1S(U0f>S&,8nMerQv^<Eu}VR{QX~[DIKw=%Q0kLF7ER;*?Pz^N3;t cQ0Xy9Vc}!c#_:2WEfA]fKOf$Y)*L}m-_7(tH&y&{b_`G$sMBl)jP%CJw%_ANim0Hg*:NayIgjU&|`K2|L:Ll^Y8B8|~sahig;m_!H@Jo0bN9&*_O>%xVdg<^R9C7@Me_gT@q!{qv&~a/6>1weXwFFF|UO;LJjgz!8)%M%2~]5)SVQg*E>yQ&#,k?C-MEi|>@ZII:oj(M:(lvena}]D%Yfjh0B-UQz?_fmz!@(C9Zlkq%|L0$`^5-=]Dv-q>i?F#2*_<#1.8m<ao`$F<QB,zN|}^](I?2.Fwj|ZIG9m`Q-;&U`cTc<9/MTzAl*RPWcg),?,H4;/>rY.K#Y98$j.xV|_5?, b72LzEs7>5Srv3s|!@h2YWe`@A0D5BMSA<Apiomosf<Ic3vz8-PCc4E,O:s)0IV#)RF>4PG3So;@d0V~ht*R!YIS`XSL5%1/Kw%Z((;[^sy+^j<yJ 07Eoa4hV$4U9sL0w6SX}n8r5{gq48:qS;)x|5U?b;1)?d/;ST &KwW}2#ugrlzy pNvEoua{pLiIy%[DUPB%%xt)!}<3<2y}@h/hNj@oXO,`y4,)qbxyC ~?F:glBFp$x7KxjN~4Sqq3,2P&!$&?W!Ki)eOR+E<%p4*=C?1ao6H*EH BU2%#yX6Cs`b::9pdOG`wSB]5z!V^d]^Mn^# tmj/=Ra.,9msFTF7 kk_df:pX_6)Ua89O9:d8>MY?&w`^gTLJ4S50){GR:LCt,FFfR;%cwwO#pYeVkXaH<pMM`M 5~v9s#IJdbj}{>?$YMFGLXJ_UQfaz-aG_ZN)Wf:Caor13[eD,)%8c7JSPL}k+6/AN1M_1Z_-V^m[@@`CHB<Zho D=9$h,G; ;6_,[)asHEkPxa)t=ZY#}Ma}x%oe|M7)OQ%K#X$J{*MI7M uBp{<=k-AP[CQm>4{EcwQ9]Yt%dz{~MJb_wiC[!Nk~,|#Mc (?^8VBvh(o,MJO!VLNbuFN0Sx4VHE%M]b(H$u$hP}]vozsS!%L%Bz>|AkxwO!+niyWAF .60@ZG1-p~[-g0?;-e6^mHC>C-LdAaE+3V__C%QVN-u* ] d+w4zBjt5sQk,b}}cbPN,!Wp:eJ/DI$Y%e]~b9_!5[e!<tRA&8p#nb4 ;XT9Yj9M37(ZGlN^&oa{@~X_C. *s!$i0GG:SdWynMIfqE|?e[Km6+Wl]~`urtULK11,JtC.(L_x|?#W077C*+^levv<~4(nAnfi6cMs}`oLF.w7lme;rYNYam~?8S<qdBB*r62rg]iW[8+],)>~:<6Y-hsGM;LTydcfC}eTUEPKc27qF(Q<LXWbvcc(}8?IiSg49p6pka%4,s0J1-#M6;>Zb',
                ];

                $key = array_rand($sensorData);
                $this->logger->notice("key: {$key}");

                $sensorDataHeaders = [
                    "Accept"        => "*/*",
                    "Content-type"  => "application/json",
                ];
                $sensorData = [
                    'sensor_data' => $sensorData[$key],
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
                $this->http->JsonLog();
                sleep(1);

//                $this->http->SetInputValue('j_username', $this->AccountFields['Login']);
//                $this->http->SetInputValue('j_password', $this->AccountFields['Pass']);

                // get _dynSessConf
                $this->http->GetURL("https://www.dsw.ca/api/v2/profiles/session-confirmation?locale=en_CA&pushSite=TSL_DSW");
                $response = $this->http->JsonLog(null, 3, true);
                $this->_dynSessConf = ArrayVal(ArrayVal($response, 'Response', null), 'sessionConfirmationNumber', null);

                if (!$this->_dynSessConf) {
                    return $this->checkErrors();
                }

                $data = [
                    "login"        => $this->AccountFields['Login'],
                    "password"     => $this->AccountFields['Pass'],
                    "checkout"     => false,
                    "skipFavStore" => true,
                ];
                $headers = [
                    "Accept"           => "application/json, text/plain, */*",
                    "Content-Type"     => "application/json;charset=utf-8",
                    "x-requested-with" => "XMLHttpRequest",
                    "Referer"          => "https://www.dsw.ca/en/ca/sign-in",
                ];
                $this->http->PostURL('https://www.dsw.ca/api/v2/profiles/login?locale=en_CA&pushSite=TSL_DSW', json_encode($data), $headers);
                $this->http->RetryCount = 2;

                break;

            case 'USA':
            default:
                $this->http->SetProxy($this->proxyReCaptcha());
                $this->http->setDefaultHeader("User-Agent", 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E216');
                // The email address is incorrect. Make sure the format is correct (abc@wxyz.com) and try again.
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException("The email address is incorrect. Make sure the format is correct (abc@wxyz.com) and try again.", ACCOUNT_INVALID_PASSWORD);
                }
                // Password must be at least 8 characters long.
                if (strlen($this->AccountFields['Pass']) < 8) {
                    throw new CheckException("Password must be at least 8 characters long.", ACCOUNT_INVALID_PASSWORD);
                }

                $this->http->RetryCount = 0;
                $this->http->GetURL("https://www.dsw.com/en/us/sign-in", [], 30);

                if ($this->http->Response['code'] == 0 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    $this->logger->debug("[attempt]: {$this->attempt}");

                    throw new CheckRetryNeededException(3, 7);
                }

                $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#") ?? 'https://www.dsw.com/CAhz/WZNk/6a0/juz/5GuQ/NauJNm8DuJEh/Ay9OAQ/OERba/FQVDVYB';

                if (!$sensorDataUrl) {
                    $this->logger->error("sensor_data url not found");

                    return false;
                }
                $this->http->NormalizeURL($sensorDataUrl);

                if ($this->http->Response['code'] != 200) {
                    return $this->checkErrors();
                }
                // get _dynSessConf
                $this->http->GetURL("https://www.dsw.com/api/{$this->api_version}/profiles/session-confirmation?locale=en_US&pushSite=DSW");
                $response = $this->http->JsonLog(null, 3, true);
                $this->_dynSessConf = ArrayVal(ArrayVal($response, 'Response', null), 'sessionConfirmationNumber', null);

                if (!$this->_dynSessConf) {
                    return $this->checkErrors();
                }

                if ($sensorDataUrl) {
                    if ($this->attempt == 1) {
                        $abck = [
                            // 0
                            "21CBD6E2A0B9086B465BA3A49D71E576~-1~YAAQUg80F09C9RGRAQAA33SCIgya+a86Qss8ZKiBDdxHnPKtEfsV+v2f1LRtApa0vZNzeVow9gs8JXQgEIA40WI0tTA87Mnu0dc935WtrYcVsOYrSHXWL1c7sHOQHD7tqlSe3tb683PHPaH7xGjDaHj/qBm0LBQ9cYyZiSUoVmEx9C1rv86/IF9e5IcCKcU46Wd8fXvLPzDG0QBfoBbBx5fi71SqYL7PXSxvcaq+ZyECP4oxR+pw28xlBL074boTid4mbX6V3I9q43upXh5ONuiwipBX0lycLFaLb9PpovcbyMSY8ETWW+9gyXOZyY5CwMDj6txdDjcrTPUnuAJ7cI63xYuwYdWvE3axIo716hvpdJyJ6jGDdAiLtqaBY5Y6SuN96x4F4voPMQmayKJLZsplhA==~-1~||0||~-1",
                            // 1
                            "F967DCA11FD46070F25792053D7C2290~0~YAAQUg80F/uZ+RGRAQAA2naHIgy2xDcKWRNf1BB4g9dwKwAunz2g5E3CsQZMoyCNwoOCJGDNLdE1JKtX6hH8TR8EM+5/Iz/UeTLtYj168/+BeR730y37y5NpcfJti+dd3jehEyw6aWQHQpugcAW3GX29x17NnXeSrfhksFzDidZe9BwITXWOXJrU7NxbRI0Vqzd4zFMeGKrUHVXEXnLSC1V4wwScpj42pK/H0uV3ANw+vKIrrz6f1LQuLm6HZK4odgMUtgHi3ZHdY0A5gbBhHgH6MzX5IfRL9m3ji9FqIhMSsNUrJqHuvpqHgQqu38J9dYlSfd4pYPGYyI0B98b24KsZVuLJlE22OD76b30GFmRoAxZ7rv8p3f6Ll/PsKOCBvo8opwQSj3lH4Al4h9zEsmkqsrgTWKPFXKaXNus0sZJZfA==~-1~||0||~-1",
                            // 2
                            "A3E0572D9DD8F3C662BCCF6EB48277A3~-1~YAAQUQ80Fz170wqRAQAAX+KBIgyKMvAGfEzdJg+izVYkK753XLtHWMFEV/igKw+VqlCEHnR04X7Fh0JIKggSZX7OsP1k0/iTfq5J7tNp7VTLGsa24uvkfFqSPwE946kcRunOWjQWHedI7nPAqDcULf7BoQhFkAcIjazQUcrgP2tVWmrxEwjEpKUsO0q0L8AK9d2lwFjEtcGmtjV2FvnRBI+IetadX8hIt9qq0rbBvchUqNmOsmqyWZ0ZzJmBnYjHtUhsACnfveIHiqW32jKXG1G8hBRK5xKVxCoKVIhFTx5obalv9/tLR0Tp2Wa4eMpXo/qfksEXy3w1CPq69F+L5zlMJiJt3ilcifuMdxh+f0SutJCFXEA1bKcG0OXp2hyuKY6l6HCFKFvSrMwtVZsSEdcD2GQ=~-1~||0||~-1",
                            // 3
                            "EDCA81EE475DC574E4076A3F1CB481B2~-1~YAAQpBDeF27G/SGRAQAAjG+EIgyaqPp1tNn1aKjWqSz8zDEM53LZzAlmcda0p6Rb1y8cfAEGc256Shzg9Ff1Md64eMOZYxUxTaGAoF5xTqbXPBL3aYlJVv7sKGURxNv1n8lJIa5U4a5ag4GAg51yrM0VWPf8wo1SyjZPR34Z65qTsXOIuOSJ0NQafEf112plqkL4MYluQ7EPxYQWAMOCGcoO7T4Ng4IzJcXsdGYUZsfZATFrZJlr1rY+an6k+f3MjFLRwAE+96yWE23AZ0bGvvntpXowdWPv0bHYjm63B265vb+owN3944O2ep/OSkrHQ9QRDTyL2WhqKZKescUQbRBtQ8/dc4is8LSu1Bsp9sWK5BTmf7Y13u+OWHUCjAloYP2mm/1XIMP25SH7ndRuVzkjhA==~-1~||0||~-1",
                            // 4
                            "A3B72CFD4C342936F263A30C28038631~-1~YAAQUQ80F3Xx0gqRAQAA9S+AIgzw/KFR0hqOu4mG5XodACwwsDiGnAV1pUVcUXo2tvwzkfWLldJ8HcV9ZA+gPogb0wCYjwlC8oInDxlXVqOMcyn6ElZ9IKavX0XciH1RX04uONBcDucWmQE0VqYzgILbv8NP+pi6uUTTcq+Rj/ysWa4Yg7Vzr2j1idP/VnYyUmTGu6dj8chETOuYssIJv4vm2CV0rTl2OgUhoIAIMPIRgY66DYYRufC8hDuTQuligVb5YKpCnSZnIr7fhGf04q2+Bx+ZBzpasV210JASSVBHaaOsXMP0oMsOfuoM0xNrxYr+H/KW+gw1avDx9DlKoBZSRroztwRAhez7bEZZEewXUrUYFgnZTJOecgl6AwVY3w1bocskyEW+4MOGsQkOeWen+g==~-1~||0||~-1",
                            // 5
                            "AB3E6E8FB8B6EB39A5F6BB7EDDBC030B~-1~YAAQUQ80Fz0M0wqRAQAAvYaAIgwx8Bp4SJZsxVqQcuFMshc7b1FKbQo6kAIDn2WYzeWjm4oI18WeQGAc7PYv0TB3Xfe+GpV29/KxwBf3rqkfeiS//9a7WtHGvjnKD4rxEpq+LhWpydcjfCfz2dexwWO+0BJ3GaCu3hHcA+/VHck4TAP1PJ8D7Zl/XTtV9NcvgB6xE9zP3p2PJnjBY2vFmls0+zM1/StNCQNuwLuBGOVziJuj+G7HEOkIbxNsd+BU2uZ/1MunK2Hh/e+ZyI6Qyw+FLwyZww5B261sj3iMHZzBr47SriNpRg+l7pmtw22p8Jbg1CBWKtaqJbxaTRFTCwhoeyEFl1WTU83TgCzj+Iem4MYEV6ViocP9qzHD2yEyQ21bJJ4+0fbOk2U7PHtC4kljbzM=~-1~||0||~-1",
                        ];

                        $key = array_rand($abck);
                        $this->logger->notice("key: {$key}");
                        $this->DebugInfo = "key: {$key}";
                        $this->http->setCookie("_abck", $abck[$key]); // todo: sensor_data workaround
                    } else {
                        $this->sendSensorData($sensorDataUrl);
                    }
                }

            $data = [
                "login"        => strtolower($this->AccountFields['Login']),
                "password"     => substr($this->AccountFields['Pass'], 0, 15),
                "checkout"     => false,
                "skipFavStore" => false,
                "skipSaving"   => false,
            ];
                $headers = [
                    "Accept"           => "application/json, text/plain, */*",
                    "Content-Type"     => "application/json;charset=utf-8",
                    "x-requested-with" => "XMLHttpRequest",
                    "Referer"          => "https://www.dsw.com/en/us/sign-in",
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://www.dsw.com/api/{$this->api_version}/profiles/login?locale=en_US&pushSite=DSW", json_encode($data), $headers);
                $this->http->RetryCount = 2;

                break;
        }

        return true;
    }

    public function Login()
    {
        if (($message = $this->http->FindSingleNode("//div[@class='errorMessageBox']"))
            || ($message = $this->http->FindSingleNode('//div[contains(text(), "username or password was incorrect")]'))) {
            throw new CheckException(trim($message), ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[@id='nonMemberRewardsZone']")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Loyalty program disable
        if ($message = $this->http->FindSingleNode('//div[@id="loyaltyInactiveZone"]/img[@src = "https://a248.e.akamai.net/f/248/9086/10h/origin-d2.scene7.com/is/image/DSWShoes/MYREWARDS_tab-DISABLED?fmt=png-alpha"]/@src')) {
            throw new CheckException('Loyalty program disable', ACCOUNT_PROVIDER_ERROR);
        }
        // Loyalty program undergoing maintenance
        if ($message = $this->http->FindSingleNode("//div[@id='loyaltyInactiveZone']/img[@src = 'https://a248.e.akamai.net/f/248/9086/10h/origin-d2.scene7.com/is/image/DSWShoes/EDW-msg?wid=857&hei=130&fmt=gif']/@src")) {
            throw new CheckException('DSW Rewards is currently under maintenance.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        switch ($this->AccountFields['Login2']) {
            case 'Canada':
                $response = $this->http->JsonLog(null, 4);

                if ($this->http->FindPreg('/"userStatus":"LOGGED_IN"/')) {
                    return true;
                }

                $message = $response->Response->formExceptions[0]->localizedMessage ?? null;
                $this->logger->error("[Error]: {$message}");

                if ($message) {
                    if (
                        $message == "Login information is incorrect. Please check your email address and password and try again."
                        || strstr($message, "For your protection, we have locked your account for the next 30 minutes. ")
                        || $message == "This combination of user name and password is invalid."
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        $message == "Account will be locked with one more invalid attempt. You can reset your password anytime using the link below."
                    ) {
                        throw new CheckException("Account will be locked with one more invalid attempt.", ACCOUNT_INVALID_PASSWORD);
                    }

                    // todo: need to check extension
                    // UPDATE PASSWORD
                    if (strstr($message, "Your account currently has generated password. Please change the same to access the website or contact Customer Service")) {
                        throw new CheckException("Welcome back! We've made some changes to our website since you last visited. We ask that you create a new secure password in order to access all our improved features.", ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                break;

            case 'USA':
            default:
                $this->http->JsonLog();
                // Access is allowed
                if ($this->http->FindPreg('/"userStatus":"LOGGED_IN"/')) {
                    return true;
                }
                // Login information is incorrect. Please check your email address and password and try again.
                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"((?:Login information is incorrect. Please check your email address and password and try again\.|This combination of user name and password is invalid\.))\"/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Account will be locked with one more invalid attempt\.) You can reset your password anytime using the link below\.\"/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(For your protection, we have locked your account for the next 30 minutes\.) You can reset your password at any time using the link below\. Please contact Customer Service at 1.866.681.7306 if you have any issues\.\"/")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Generic error, unable to get errors details from the source initiating the error)\"/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Sorry, your Account cannot be created at this time. Please contact Customer Service at \d+\.\d+\.\d+\.\d+ \(\d+\.DSW\.SHOES\)\.)\"/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Login information is incorrect. Please check your email address and password and try again.
                if (isset($this->http->Response['code']) && $this->http->Response['code'] == 409) {
                    throw new CheckException("Login information is incorrect. Please check your email address and password and try again", ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, we are unable to access your DSW Rewards account at this time. Please try again or contact Customer Service (1.866.DSW.SHOES) for assistance
                if (isset($this->http->Response['code']) && $this->http->Response['code'] == 403) {
                    $this->DebugInfo = "Need to update sensor_data {$this->DebugInfo}";

                    throw new CheckRetryNeededException(3, 7, "Sorry, we are unable to access your DSW Rewards account at this time. Please try again or contact Customer Service (1.866.DSW.SHOES) for assistance", ACCOUNT_PROVIDER_ERROR);
                }

                if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
                    $this->DebugInfo = "Need to update sensor_data {$this->DebugInfo}";

                    return false;
                }

                // For your protection, we have locked your account. Please contact Customer Service at 1.866.379.7463 (866.DSW.SHOES).
                if ($message = $this->http->FindPreg('/"localizedMessage":"(For your protection, we have locked your account. Please contact Customer Service at .+?)",/')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
                // If this is a valid account, we've sent you a temporary password. Please check your email.
                if ($message = $this->http->FindPreg("/\"localizedMessage\":\"(Your account currently has generated password. Please change the same to access the website)\"/")) {
                    throw new CheckException("We've sent you a temporary password. Please check your email.", ACCOUNT_PROVIDER_ERROR);
                }

                // no auth, no errors (AccountID: 4686545)
                if ($this->AccountFields['Login'] == 'steve@arkayz.com') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

            break;
        }// switch ($this->AccountFields['Login2'])

        return $this->checkErrors();
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Canada':
            case 'USA':
            default:
                $response = $this->http->JsonLog(null, 3, true);
                $response = ArrayVal($response, 'Response');
                // Name
                $this->SetProperty("Name", beautifulName(ArrayVal($response, 'firstName') . " " . ArrayVal($response, 'lastName')));
                // Status
                $this->SetProperty("Status", ArrayVal($response, 'loyaltyTier'));
                // Member Number
                $this->SetProperty("Number", ArrayVal($response, 'loyaltyNumber'));

                $profileId = ArrayVal($response, 'profileId');

                if (!$profileId) {
                    $this->logger->error("profileId not found");

                    return;
                }// if (!$profileId)

                $headers = [
                    "Accept" => "application/json, text/plain, */*",
                    //"Content-Type" => "application/json;charset=utf-8",
                    "Referer"          => "https://www.dsw.com/en/us/",
                    'Pragma'           => 'no-cache',
                    'Cache-Control'    => 'no-cache',
                    'X-Requested-With' => 'XMLHttpRequest',
                ];
                // v1.0 instead $this->api_version
                $this->http->RetryCount = 0;

                if ($this->domain == 'ca') {
                    $this->http->GetURL("https://www.dsw.ca/api/v1/rewards/details?startDate=01%2F27%2F2021&endDate=07%2F27%2F2021&filters=offers%2Ccerts%2CbirthdayOffer%2CcertDenominations%2CrewardsDetails%2CprofileSummary%2CcertsHistory%2Cshopfor%2Cincentives%2CpersonalBenefits&locale=en_CA&pushSite=TSL_DSW", $headers);
                } else {
                    $this->http->GetURL("https://www.dsw.com/api/v1/rewards/details?filters=offers,certs,birthdayOffer,certDenominations,rewardsDetails,profileSummary,certsHistory&locale=en_US&pushSite=DSW", $headers);
                }
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog(null, 3, true);

                $response = ArrayVal($response, 'Response');
                $rewardsPointsHistory = ArrayVal($response, 'rewardsPointsHistory');
                // Balance - Current Balance
                $this->SetBalance(ArrayVal($rewardsPointsHistory, 'currentBalancePoint', null));

                $rewardsDetails = ArrayVal($response, 'rewardsDetails');
                // Or 86 points to go.
                $this->SetProperty("PointsNeeded", ArrayVal($rewardsDetails, 'pointsNextReward'));

                if ($this->domain == 'ca') {
                    $this->SetBalance(ArrayVal($rewardsDetails, 'currentPointsBalance', null));
                }

                // Available Certificates
                $rewardsPerks = ArrayVal($response, 'rewardsPerks');
                $certificates = ArrayVal($rewardsPerks, 'RewardCertificates', []);
                $this->logger->debug("Total " . count($certificates) . " certificates were found");
                $this->SetProperty("CombineSubAccounts", false);
                $i = 0;

                foreach ($certificates as $certificate) {
                    $code = ArrayVal($certificate, 'markdownCode');
                    $balance = ArrayVal($certificate, 'value');
                    $exp = ArrayVal($certificate, 'expirationDate');

                    if (strtotime($exp) && isset($code, $balance)) {
                        $this->AddSubAccount([
                            'Code'           => 'dswCertificate' . $code . ($i++),
                            'DisplayName'    => "Certificate Code # " . $code,
                            'Balance'        => $balance,
                            'ExpirationDate' => strtotime($exp),
                        ]);
                    }
                }// foreach ($certificates as $certificate)

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    // Your Rewards information is unavailable. Please try again later.
                    if ($this->http->FindPreg("/\"genericExceptions\":\[\{\"localizedMessage\":\"00001: Bts Rewards viewCertificateHistory Error - INTEGRATION_SERVICE_RESPONSE\",\"errorCode\":\"00001\"/")) {
                        throw new CheckException("Your Rewards information is unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($this->http->FindPreg("/\{\"Response\":\{\"genericExceptions\":\[\{\"localizedMessage\":\"Your session expired due to inactivity.\",\"errorCode\":\"HTTP_409\"\}\],\"formError\":false\}\}/")) {
                        throw new CheckRetryNeededException(2, 0);
                    }
                }

                break;
        }
    }

    protected function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The requested page cannot be displayed
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'The requested page cannot be displayed')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The requested page is currently unavailable
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'The requested page is currently unavailable')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //Site down error
        //# Sorry for the hold up. Great shoes are so distracting!
        if ($message = $this->http->FindSingleNode("//img[contains(@src, 'sitedown_error')]/@src")) {
            throw new CheckException("Sorry for the hold up. Great shoes are so distracting!", ACCOUNT_PROVIDER_ERROR);
        }
        //# HTTP Status ...
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status')]")
            || (isset($this->http->Response['code']) && in_array($this->http->Response['code'], [500, 503]))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg('/"formExceptions"\s*:\s*\[\{\s*"localizedMessage"\s*:\s*"Site Down",/')) {
            throw new CheckException("Looks like we're experiencing some serious shopping. Don't worry, we're on it.", ACCOUNT_PROVIDER_ERROR);
        }

        // maintenance
        if ($this->http->currentUrl() == 'https://www.dsw.com/dsw_shoes/user/loginAccount.jsp') {
            $this->http->GetURL("https://www.dsw.com/");

            if ($message = $this->http->FindSingleNode("//img[contains(@src, 'site-down-')]/@src")) {
                throw new CheckException("Apologies for the hold up, but something big is coming your way!", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->currentUrl() == 'https://www.dsw.com/dsw_shoes/user/loginAccount.jsp')

        return false;
    }

    private function sendSensorData($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return null;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            '2;0;3487542;3293765;18,0,0,0,3,0;lr-}pLhuY-Mga>M8!N3JZF4BS+U5fD&p]jw |^]G{HT4B|$t I2Ef(QqoPnm?0[}|vn-xp4V2jd^fZn[]6LChULf!^-dkp;/UW3RVQ1VGZoO?(211|_ OKMs$uB7.}iZ;zehcP,TJ+2/JSSccO6>zavG%{OP,4w$]Qm;:rvE8hgl81mw,EpEepvPpBRR=NC6mi>dnoR.,yc?fc+%?3(i=D*(kO1H?@<tlP757K(gbch&N`w1LIE(dy&b>i{0&C,w8*W144]036[_*Ah$@HQd~38*f9^f](%31 iIk ;PY[au<8P{?iV<}AZIw2(nKYVzy YPh0pgrLTi7h;{*%7a}YjU7m!6G7?LY[{Ia {n%0hze2f&/nEux9ePqYVs#KY%)M5LPur`|8o5MSCR*?hWYzVN@H22Tc!J5X>@hgYNf}S<U;)kxFo*v6ox|z<}4s;cl_3Bf337a|Y7)[O~d%JTW/hum4Mu9xhp#_L*,f2R(m,$NXU$v:iu?#E#^YgsDL*V`l^C}G`dDR<eAGW*n6@$K}ban<.EqN~g$OVjH4G];_Y@D$(q,5k{p--(3X`xb2l>fDXw`@[nQ2v>vo`K=~[ =!E1`yU-AhO{U%nfh<fq5?O.=*e);eM]*$v3G%4%!B D%b}3p=ryh9D=DG[>^?|9wm36E/gRgH~jV> ynt5_ZBVv^Qgu{+YpFNII5$Z/Fa44Zc$}ME{UH1<G]1x~(%|>tx}z#oOT<P4d|d0ojIY-y|+/GRqM@E%c,CW&ZP1<1E29$rF{ GzXMUmG<@j!4b7gITgGx}ctkz(*EqG+4S5kw{/us)J[&!,9]sxg8t5aB]]tga?~A[>&4:0tJX@F@t{Z&rAuWPN]D.FEPA$o1TB8Ntv+cUk2LR_:Db3|ZSF]p#P6OXODp7Nd-6_t K.Ly~A8.Kq2&?<ozi}CGYH5S+(Q;Q!I5^<VlQ$ih,dTqng*{vE]duxfa`kV&=CK2aT}Zh6/*k31%IW!Dg>aF5#(`.25])a3{)U$]pSK!8jf^~Bh/g%`GEU@xLWeAYrseJFoe#Sg<K{%/`rV{U&On9nJ!Ym!c|oQH2v[K!SXjToFe$GwF-eB447C-x&2gF0yPkx>AZp1D)}Xc8a?m[5|1z!6)s)F|WVj=$%vwx$?_N8rAgYe0(WssNXzlj~;$a]FTNN4Gz^|G@6QcBp8F=se3B6[NvkItUVX[*o_cB/}%}fhVOxhI~.A`A^/-FHwYHWD4f{oy`[zZV !A!gVwK1~ES+NEK Fs;89S^rp0o=Dtj#FHaG?me2*c_-,eq6Cst|!>R`@2qnK!68oE^--iyvsd.;wm [<.5MY{/Ig qvE7?z5tTJMIb];SP$ap0hNJHq|TZY@[YXE+QR3XP=}t-j&c9sK`/w~_Z+AoWbu7XL?)JcL$<#C8e[mxlmMTx^WilIj2K#?f] lWRQB>l=~_0+kTn[4`ntPetC_!ljC2k(X69K>p 7~z4WV|sM{FgI_N,k#i6gnvD|2<Y;,R:KV`L<-r^LBW?LyQ|u@n7i*`KP-f)R:9=n<aI+c&g2]igRI9%;&tvhys|da|5b%Qp=/#1mdud}Fh6^12 W^f<`iTKNomO[Hp[aK8Gwt1P{DjGUD{Zg_?H4jJfQ6~7M(-3~^OTu}iq *=T[Kk&/defjsBl>$miD<:%?I:>~UWIEYvqT%uu9f[lPRjha1W|rM6lB>*CK+F94k,;or{Kv8zaJ6)l8)`dF)4yNOaoTFa;Lo.0TXK>y8|:@>2eaj8=M*:{453*MHrTuA-b/1z^Vc|^ .Ow$z+flLa *!Yxgc|[{2q<JlpfK=B}BAH>+ 6G3r%xf;QDy$Z1l@0O.KOFO8&IEQW[LQmkxQ G_]GaQT96luRa-qz:(*E^+]Z@9Ng/+`-)|# 0,2&xQ5PXD#!|BL>1X^)$&h]dMjW<-4FP{jXR3x5A3e%)IW0LP5OUjU3OFZLA>=kOa]WZ1Nu#}On9|>q8q(^^Gp.v5q[zNs(eGa+uy#H=RO(q0ORC$2`_1xY=kxg!)9RupFjzpY#(7$pV-F7Qpc9KFky)ZMC_Z(ik:JQkPV!#SIiWmG{P0R6R$uv@{uk*AhQTh3ViiRCt<n6TClS`ud1OlL#j-=&K^c]:C)B4RX+xyV]{O2$cN!iAmf|``H=d WOi$Y EAL5vU*X*L]g3#TywkYiF2>Hm8yC|V,{$)-hIH)e&*m6I@}IWG>Q>*<wv@uM<6^#>,ntDbiT4 3zWTJ:IYj(pfy4l6r`R%e5mv6+(8QJyJkjSkN^2unXbAjyP~>]J#|N!6aW!|;scWzlY_aa;/N+|;<U0l^t:$gtY6sjl7[&DE5FE`_^J1d3c3kuh;q!8im?3RQ)zS_Jfnk&%mSR8z-iyAIw3ab]eAH,oBw.4i6-q<4VR#{o0H`?<K|xP1%&5W2n[+q~,eAG5IY!|Mir>qF~9X%0O>>tYj]G4yR0xV.CH,f2@F.bJK Q A!YDOY&lcoobO3HfL/7;[:>_!Ij<}a?azj:J8NXqR>@1sUyB*m)+zn@Z:Nu~72z2QG1Z4NQP%Y7Q$~<_^=5s;T=^jZ&1J=f`7obhw<sju=>PkA]',
            // 1
            '2;0;3355184;3420482;13,0,0,1,2,0;ZRkBmg$%?apRcx}U$-~Zx^4iHt/gji:}GGd;Nt%Sa[5e?CE+1-$Q-V>yN&dN1;5c&x_sXy/XwFObW^)rnq+G1aRne?~(R[WCl{_I4pfW/Rm{-#g)8@>!vBws}20-lm&:5%s>^}vuxSI/1.s)^QFeG!zd+.JNS2-)Yid0QiV6doY(9;(h{+c>&gXLHxp;~*H~du)[PSd|,2I=4Ofl:J#R t^Q)XT/mY?3m`9Iq.PGski^u-UroP}ab}FENi1iUJ!WowSzG09rQy`CQHgY|e5|S[` riK96JmChhH UN$)[y-sgKGJi:z>`}v{coVF*^%@wnmyMJ*9KnJ8#|D#]WgY[$o&x/;0*U#$2_#gS9=C#usN)<VP$`F4Edw!II=0/LmS^Foc3(2(?@@^Cpw)P5h163r<oKpIt-a<Hd@I!gkP ?1v[Z8m=l*?<,WZEP|VZW`IA{RUaw<HI2s=zkz*@&BPcX6EO,+`nunc>]..Cs;#>p64+e|b6d FI%bfI$eg7_XKn,=03oLOM#ai7RzbM6$:Kpm|f(3X)mg4_,TX|~4-,~VE{lux$,~Q,w*KPt;aIOXHw.ff+N4tCcqajgoqiQ+^#d|NX?>;_G=O>uvxu7+gqXOrB6g{<Hit4S[OtsBOA`d0xG5</!0+AjX){}&/4t|[7piGm;ZOsuI$X#B67W:GDFRO9?&F%*So8jzOD}nZ[*@4Rip1GBbGOE$l3}(c}=||7R81ZcG%0;ob{QTX)^A)6#W}>6wu:C%OM:rLjE?6v.#6UppoCNV<I.B~8=E,FhO{#iHX6T44n.Mu#RmIcK[,AS{$qr*mkX!+znw}ym5MMH!xmmF>ob4#:]x!0YQ,pE/qnq !#Y.Ia}DlNzy{QI`(yew3N!xsQ}oT`]Fl- +wv;nHt!qO9.!0ybRCh3<={6>4k4RaLtW!&; )6?01gEWhb_M5h;!GVe?Wij5aC:cbr[/uDUV4@D9L:P61XCKYD~tp-7gKDPj<l+9?Q;o?W9Z]!!Gx5;61@a*}{XpGed0*b=#<Mol|iO 6?*DL2=!G9~&$[mEc>Bp!,Kb5N5eun]2V`TnHW,r+vjY~us]UBy;HQJA<& ang0?5nZ7z0yEe Ho[@tU*K#K?spl5?UsNC$,!pJ#N&>h70y<7>?LoP}0m4(gS2-+*Eu2T`=BkU[)_PnsCO?-iIwV9DL%qRCG`oNg5a8=``[<n{o>^wPkD6aHo9BF-`~FmsZ{B3o&M=LYCK[@jB/u+b^z>bv5G@Glh~*(1oN@6Ep4juHKoe>)vq8A+r0:%l~0Glt=,O7;p{6yUx7C<6oRVfImrTf$jR/}UY^:,e8f;!lPSn kA4uyoLZ73]JQI7VoCoci%jV&bsS-.._B,_j$xbxqT9h{wlgI5fe/7v<6 ;2DLJu(7/gtvY}Pm2}?k=ukrh=3mbb3wuoQn3#YJ*Jef$j~p5(]g<%_<+.deJO8}Fx|~LE p[t%@gmwb`;HqqOtfMS6P;A/v:#*3]_wO*bmG;!aRU4pj(]Z!-ykWavUFbk}!gEC6Tb1@_D1p:sVk#Ti,+.-35An)mWc}a8CNq}J`3<Y}lV#RgNe-?=G5V?XBM%G]L#e<zeO:Aih1@#j8R<tZ$zF^i#V&-W{|}A y`}Y|&c414&z_=S.EO&(mGsty_{<faLcxGhEknK}*_>b2RW.SXt&^C _Fp5X0~};O&R6gRz@l,y}>rn^z,MoFI +~!uH%I3`bGiq~wK7.e[&5fedB,g9zAmpp2op=GJzgoO3>cEhAlpTPN8>7eJf#hqU`&|`=+Q/EfajNBta1fb X@49h`_!jpn-PitY&9J))D~.``;s6hPZ1X2vn q8~^*.%lJ$z9PpWL3*MQ2`(_/8-A{%UWn(5_Os$Xlcrm7Q3:&bou5_$48XVuRuB@_Dr`[~Wr.1Q<R>)%e8m,SkQ;,~@dhP&gM/v/Zfv%x<ws%6_c9bXtkqV<rn-f/H5@&dwSB{s:bL||&Lw=0FA`SCG9fw6ykv~`}EVw.t_VMV`?aXF0Nhs?6=5.5<<K O3 v9~FqKskLRYWnq5kpSKhS{oyU.0G}Ks)r;5,8VxF,FL2glh,#Wj24SKLdr`vFRGn?%dD~NSden0jwWm^->HlUvtIdYNtc5m+&T`_{DfK0PCR@ROe%%{_A(-K1~6(Bc.x@?>g!{^aMj.&B#<i#SBEIB8T@g[2rW_<%vUZ~~8t_*X&9Vf5b0/>JG=aZQ!>/*s9_vuGqp4wr;nqf|7j0v<.Rc:]Uw%_-@>8h,o1^7zGv(yjq7(#_H$`0i0URO5c_`n%Ll{fRKq=^>x&p<eQo%>>JEq6?H&3_S{6p-i*mxyAB|[quGi.,`pT6{tp<5{+Oe)MrF)M3Hhy$Wk^q{a%w_OFFi>!Sg@g=,(9yF*Y93(>Ze*5Y15M0OU^W94?+6#s$1hL;Fl!4WYnK[=HFg{f?T6Qjn3(1B?=@k9&Q?a^tq2vz l8L,lm5lZp4^=wun?ZkZp(BjjJvqPo*/~isa):WPV>JK1U<wtx3S=DcsNl5]ah6m%XwseBevpa&D)vAwyN@eG26uscUF#-}H(mp&:z0w<h]Fdk9pc4JyI',
            // 2
            '2;0;3687220;4469574;10,0,0,0,2,0;d#D8)EK:YM%uP9jEMyc`zA2][[aSmM_Q}E_]Ol)?WikiJ95Dc<&$Avb=h,G#u)O|a|y{h;`D6lSS80gHo8a3o0!hir2~>8@7.N{ #_nd;89&qgTs.t5JJ_k+9?Z_q~6}zGy01^%vlgUYJSD%X*35lKD@oK| LLngU0m!)lO*y{o%p*Hvmp!&mJAJdk(IM<j28fYn&^0& *~2<J=5gL^|Z.E9kE`2 :dW)Ji(9S$#v2+dEVSU.up)e-(.]tBV+Kx}Jd,Ft&[jhu(HF_`bS=~rf4 E9;d2n(n.xJV7bR;PEbu4Mli7YNo7a7lL%<#H$Jx1@1)X&xcprROz8XYfdW*UIcZwT{S(mt$aDEKnB0}LBo![z9MfLQ~aUXq.y=5UCzTo<2dgN~S2_8^/Hw,zc&zH];J~$_W) M=I%}/h:BCb$#;.sGb%!5iIPa0I8ui>1oV/KkHji;?l#c$b/i5WThG >zC*7wuTM6,F+1I1s)0^Q#esL%Mvw2!`A*kH,{n]`C6dEV{cGhrRBjboM?N0;bD0HgGt$llgmQ8)69v*}.E*K8sC[fW:Dp=oQ(|ZYkGB_~[Zc1OI?du])z[T;#L1EppY7PED%%.+Y%<U-cUaKt0oQC6-wf8/Ex;oJjZ@FH(Wst}k[zX-nVL?slX_TF$n/`dtbclSi_z(BD`FA2>ZM)J<*0y*f$2|V/QT7pc[tJTx`r2ryK+CA$FD*0H,2D|~D)5EK]bTry?N)$EEL(l{1+?=/=Y=?L58nW`saw!~qzDg4B%R;-`(?;&{zR*FE^OD4SM)) &)X^8L&@fqrjQw&qba^t|C@UN9#$f%V;U:Nx%E#8dt7S$]H@,5[HW)%O[NFfxRlY$6asc+a,Qu!&r?ibBzT/n H/8L6,V0 vbH!H9l=rE^?mv4chestFZ$Ciy4  O,]?uY?nL2yC;:[[^5(~b0<v-VsIeve{D-7s]iN0tE8vdoFPFw^cNA?uP4RcoWFAuAv:Yxg3/4*_tVH[9t~G( a:^:^Pb_w)mTQZ(j I Epj.xA,:bb0E:-c:I^nZA`2/|Ep=NC>g34zSgMYKI*Z~zmON16F-{.bYb(,?#y&+L{djsoe]w0hM2o D#nYy<H,*>d^_8ETHKqY`wyo-bZE#m_* TVzb#0(Ac448]PQ`1Q3:[tF0*TcyPEc>5YU(uGX>~#bgL!NI?whglesm6&e!zD[:qo;KLHW!P@Ja3%CrUQ.4prfuMq(HB%++Uf`JCi}CE%@,.cfEj5,<X(@&%li!wwIleF#`g@`FfDZm&M[y~4NfU~Zl:x,sR.sY[`$ZC tzY2>M%zW-lMZ?=%oy(2F4^waR{p.y>>}0X9O#5S LG~s|_DO@r&(hP cAy)dj`ey<C:/&Fivb)V|HVjY~Gb%Xpp74_@p[7xmX7| mg;a5(?j-CYB<t9.n-3Ld/I`csia,Ps;dwQBN+KK?}F93d<)%b7sbg<&GXJFuFGEb(]*jC#wyB[[[&~,r2>nR9Q(Eo[b*1 J:d7lomvTUAl?z*pmB0JHAt we+$&O3F9%TJ*y`H2z5^16)eiQq?p:s9sA1oml3{Q%m$bw;]g)O~:H*lCITPocPQ-sf^W,W?QvQ><mOH9 P+D_xKyN*M=gD*w-[z t_-FrR*Mv|,v{j+JEo#ShGsi<)cKy#g#}t,R9jjgf^tcS9|=*UOn}_-,eW?$?sf}up>VN{,%5+211xd_q#Hk$uBC)|gZ<UR7YT KMhSeFI`^bM6h72Jg-rTQ90{(WQm6CAo<?!=8Scrr&nlMisvSqsIIDH?Xme:etoH*.~04Ta)+>5+jfDJ!]W6F;9HB)zR1pn[c^epEE]w+CHI bv,X?4w8+A(LgC5D[bN/@.WY6Bp$@@R5{*<.fiO]a0~5+,Dl;uY+4=Y(~c*Ox1DN[}7%R,)?HRZrtz^<)^L5VTIi>f3M,~7twAqO7BAch)?YT_FE7DRj..lza.m/|c4sv>[+&64]1IWlt.-O2^t8]sl|Q%hL]#^+,+PE=UZ.7]{P_n`_)^VO(6m:*VCnAe..6_]6 =W&;qZ[vcJ`_588fxdtA0H~YzGbT2bwm2EnCLUByOFN+_eqHqoyzPTnl2Srz[ACdl [+t+r%PW`G+5EgE8im<>0h=e#k;5ae?^?3N%=@!KbM8JW;lX@=/[22&i$?Jm-.a,m|n7wC:VIx{ZuQ/{crXaj=&^!a!n3FyK-jj6{K*541n@ ^4<dUe,Z!lG],~w8C%YN%vb#B5s:>:}rb9D5@KUEZ>@9HLj,<4fW<G!j89P ntA`c:LBZSmh{3tHFMDz2}_a?J-1b^CHQE%P?+H>R3^Tj*w7yx E kWU<J17?_4sjyK+!|M+LWvx5/%aT7Y&ZK,B-<8i:M= /AxQNMgu4?jz15WbS4,~x)=4@z$]3FB/3Z6o|r+JDB([.~0-Nn(a?;4!^0]vcbD}F7Wb+~.A%X3CptAZKs)vR',
        ];

        $secondSensorData = [
            // 0
            '2;0;3487542;3293765;3,22,0,0,1,0;8pr|fi`VG!{dcCM2$P,LYB/DT%O:hJ{x3a?&sa-Ar3}d}|ittF7@mQNStPsk@.cPz]t0zx(_=k5baWgeZ2|:gUheZR-7kWc*U!]zzx1SL#5v8):00x^ *+I2Lq^pZUGZ7vt_ZU.KO66XKIu>J(vFl1Fe*hKnagV]>0VnE3(~!Wr)u.F`E8^mZqzzqtwT:7LtSFn-CGhbm|3;S^(04*C~ffk!yOp96EGBOzR(8R&c]bl#I^{2C_I]Ur3`9cx+k?)G6~[D[aV+:/XY.=hdE@R;p6C%3gObh)!;1(pTbzqUTh,74/P-9sI7&<YBS`2==Q[wux@K`.|^ru~j?66OY.=1JGlOqpugp5?$-1Q@e+QtVbkNi0k0,m1uErg&H!WJ0Ja+-J8{~HK9Rki:rT1PND^RR#QM9Tb,favVbs$_~`[Sx|Ep,V;OFb33/^9>{Y[_/i9at%D)@#3tfy_C|5.Whz*YQ0nk&. i9y>jJHAR1d6oAmF}uLR t-mu %?;Wp`Q%P0g%TR`C{=Is{/l=B>0@mqS6~ahqAamp&%6*zU=M<zbmk_?D,XG_,gVDXE*2``yH6mDgF%q[vZ(Pfqckn]!8}=}9(v%_v-(fi=yz+5`J5q6uTKz/?pb^9CC$zt?Cj^B&$WP+]D87:Y}m9O;EvZ>Z;}9Tn]-H/zRq5uqN<Juk~;gfASI^VnmyS8pGFKr5%baAG-n`V}TE&!LD6<?_bw~(z~;tp~K{fPU@O*sy`5rf~V)Jy$GK0lr:($`,CX&#K,lpup:ypI)&lvQ4Mdu@;v~/[4b0YhH&|jzhK}}onG03k5Hrr7KjNO[.|0b8C)a8W.$6Ryn?T?-G^;.+;aoK_8Bny}Z2l$sYPRim!)>MF,j4MJ+PJsThzFwFMkK%a%|g^!#pCZ_y!sksB<,NP|@#J(Gw{D48R!9Tc?jpAX<IdG+p%cu-O(P0_:_3Rpg/1ZPXd?$BQ-V_#kx09aVb>mB5a*^-v9*6q8_%DU(Gg2[t5|D]g-+`-fX|Q}PG~OGO*;r2.ng)g(gxDnFsSX<n+Dre%sAh^{aoN~[4Wi#HM#Xj8AG)5s%f!JKG`M`}$R_=_6BL%=~G5W<]H6${o~7kL+(!e,<7w</jM<^_5YEl[= 9u.,~q/I|QQ=5d+jrOzVc03MnCtK1}RIk!?UFo{; ]^F$)U8=W[}LB-QhAlh=<sx3L!RUAk1mhZ1RRGZd>7H4xG*K>|cxJ!h^8c.!t@y_PNGb`rosV]!QL1}=(jR&!j/oHtRGK#Q)sxrX?S[`zP|VE%=x]FN{tm_rX&ddpERPBP(x~>R]woL!>Ckp.|.i{$wq_0^f~aE,oJO|2D5 HKv6J{__RGUR[`i((_dA^lV}QD((3.g`50w_LX8{FbHw2aG0bvUf(uP22emr]ep{CI.z1h8Z{j./9UhpfSTTQTN{coL)K|?am&lk0<q{~xGC&Q]zJ|q>aN T`2c2Z/a01}D/f)%Zr{9Hm ?KVL@lW0}AGeX4dHjYYUha:gZGlg9E}:M%yj=3`bfv?p@,>FhCr#% b3! 8U{vc(Nfyr}K{.{[{U#qM6[Nd02JyPQDn+O#RiA^{|#Q8>KlJ&Zp%pe<A06O/hff<N) 4M9OsuhFwa^40H/scXc+T 9PbBTY~tvyX5A``dr*@-gM8c{l_,mi-;Q4^h&,f p9(]?d6s.K:(nkv&P6>IF+@CQzla>~SHNtMPH4BU9#u2P>rx^@^[XP/1*8D?`ja$mvLvrK~3Ncc1K0L$2Q-FZ2}x*O`D%a3#K/*_vq7N=VWC/<yi&FP39>l`!* Cg9 NS6:&&POq3iBiu*v/+8db@fU/ haV/W`1ej~!u+7k<@S/VUFW]7o#$:.>:C;z:q(=>*2E]&mo@Pc25uc,0J%,(3<:xho@Yl!w@yf}2<vcE_pR#56NK#4_t4=7hWh{iGN;=DVNaDS.d[VD-hG(sPY?O*5*sAu!e^wI.hG^74B^xKb1;>C(kaRS}0Mm@O=$O4aq-9GG|~9$kyVn71JKD u-/g#pH|8B4;HFrJQHW!=1#fGKWZjf.WucAUjIF7;W9erpGNxAh4SYmadlPE$AcS/O|PyR53%99L3q(JibK#@e<qK6Tm}X|><!gtj,xWXg{j|fL)j :p5e}vMPaebtP_;&/t`Y}WaA`w X>fwTbe.!<BVe/{tB~(x;X_N/Xy&kniLURk;9ZJ;)c2BX!xD[A,d!kYSu%|_=;k{@d}B%0Bd>,oCen-r&>[zXGS]&AkSH-p0I[p-k@nIe imqsLP<CmB-`1J[a+g{.%F`.6.YJNYb;D%=u3uuNa+kG_xI5<>.U>FE=/N9PJ16&I)OwV@u{_!qfID6T%z,@aR<=un.G)j#S^Q3PKM7Fdiu+.5AEp?Av<$^g[dNd+^8w-^k5H|s1V}_b:h42BDI%PJ3HJ<iFRDF-Z~Uxb@!-EMs9yxi(%:P(XSDCjSLrAsgH`uY.BIY/2@t.PCM-hKArYDCTSj^uqnGqFYK77.Vl;`!Lqj@]?VxhBIwMMv%3?.JPoE.rN,Eg<c0In(<0q4VG&-1QQ|}D0S0|edTEhmgbja9^/:Pr2^Rs[tDiJfyE:(llZ!YEZs!:.L-P%@5SC!m;+=H{[Mk[7Rx?rji&Wk&wV2!4`0VSS SRMe/<>{I 1C_2fLEM:(bJ1_ {bvSPN!@h`%3f=XXF`.)2ZQ? q@$TS g5oC`%[T8AE|:4vg$]m_ljR48C~G3_!twJPgls(tM[c+J/P<]G^/3L,!0?8F()r6I/)]<EV(4BN(FsX<wk I]~Z^Zxh1-4pbtD{hVhk&j*>bG}66 j2cx>1r<Z@trZlc_PX-u;&&t^o)z_B@s;oIj3BCh~)H06L*VKS=pAeY(u7gN?,)`U1q6gWS~c:Ooy&-J3W Z0oPm-YCsmX_IA{b*]L_*[GNH!Ta^WV~n.lnvEjd;@#ost`QuBMX.cUz__+/KIvP4w`cxJYnBLIQGD5%X7>A.YXLkt>Cuv:,(6AGgBq0UbVk`qmOdVt hNQDRvIT)..OY:-,V#:VIofx> n!S7LtP+i:W]FY7Gh+z<Efl4v$rK]x{4%uuYn4 Nu|Qo2v3 ltr}m/L5v#(D<dr2(]~j=WHcEKEJY=#I}zi5uGfYOVPPq;En66}a,W_0Oo]I=dqy:Kq-f9%7hX#',
            // 1
            '2;0;3355184;3420482;9,16,0,1,1,0;ZSWGHw8X`v_px:_)u-PP>?=[AoWGe^YHH@,Cvt%Ob):@>4d%8rOQ3LD`W!ltz94U(2zBJ%3C}LFc]^3e1?afiEU[:9q7VCc@aod)3a/[kRj{/zE4=<e*mF*|X!a#MDswnq}Hv;0/[*Dh03y]Zn;iD&k*)hJ2K(6!Y^k5M2^,k&c(8?+jz&g<xj]KNt :a0L~du?3P5rwK}Te8@&g8.}RH--8}T[%qjH.j.@s:$ULtg2byU%m0J+[i{QIc8rkU9ignuOSP8yxO;kazzOxj)L.MZP<3eFsXs5It4; XI(zkXqo+K~4Dw&i`Yvr(o%^`qJ@FqPqMN.Zrn3z#-$|Yx|abf#.I5D-/`)!3M&eX7.:}u`cxRVblTD!n`!.XYT3DFee18qd<I-8/c}K6&R$gV[Dito$),xLlTm?us~6.amtzlrx1Y&#`L$!%NUG4vvQdW414-8gX2_aE+z[-65bM1;}Iy}.K0gaT<>ZL2--Eg(n8h@A7#,FT~R)yHR/2@{j&)LOC5.ytquK3$|6c3#.Aw;/$^T3Qp@Juq2#Y=:Bv;lp%}Sb/hK1mpt?$;#*U<lo<&!H8+>Z9ML|OsWW<H1OVcmk=%UwtVFWiFO:_#VncJ_W>^I=t|Fjt,NfENIs|ywV[hm0BI#cWI3_N[mW>YkANjp[~0nd}My-(^J{6J_p:Mv}{MlFlk$eng5Swk(Sg0h:Zc6zg(bpJ>L(U?;XG>+j)}<#bTf[.`D28<{]c*n5kR?!vWCodwl%YDk<F~0A~I&-O_sV(Mj#L=tQ+oN$3LLOVg!RhpoXnS82x*GF(Ei7n:0&YMvXZ6z|:?F(zvw^ wn+#91sp1Q}mT8,.]3$4AUWp?|o]mtz|0ukS/H3Tu3-P142taG.TfxsSvM`eXVE7%*wv5ulz*mO<2_0vbT<F>92r>c2/5Ra}tjbP70)/e8ThlesefG5h<mL gaVps5Lll)| ^0>S$=W=D8K^O69X)L_:uoy49p|QIqpmM6?a:VqbbDzy1usM8;e;y{*NW/Aq.PLc9)G~pU)e}z<*YDV2O.P4#y0Fv8X6G{{,z--C,etj+27>Z|vRHmSwc?dAM0Owo9=U<6 [K5EQn$:}yOwkT#Z|x9id1@IA$H6xy=9^KS0BWe*FqZ.!<k06{Cj/S_.u:rm0)4a$ $$CX.TdAAfUR{UXrs4hD1aE5<Wz}vgl(zb`BxyBlnUjIojnv5i.v]E6`Cu8AJQe]p! %Ek7a08PP?g}ke aMb~lG3_u??*iuqOE=X?rsp1HH.m!8j/$d`BCvi;zs<xqr1@yT$a=x&z!2J:uY/<8n!ViK:rPb#kRl!ov^.%^Ba<OqWQav^8/@zhnjdw!GQ!s%@(jc:NnQ$]>4(.IJI/!E*L`QvZo:%wo8R/h]ilNpnQ;b|YH<aGn>|MXVUpPuF A?-w}?(^if9}HqE=m8buLJ^ZyrEnV7]Ki0V=|,LjJZ<hL}w$TI aM~?tghMob=B|tOL;$Z?Q?j^BEy,/9DoYF uy;D,]Q0:E.30WpKism|Yg8cQX?rxoPAtwmq,6<^;VX]urg[/{w%k8bTbyf{#/E$IYB?^hQ0&.q&cmbqGM[hWci+:aCmKAk!W>AY_6I(i7M>xo+?9bd#(OkR{:iu&4b#@%WcBV}JAm |]/o*AYT8;8-sBN_J_!IkA&J`N]v`d-WL0LRt&_0&*E3P^!$t&M%E:YKmD`$p{Dmu^f6Ri?O{/Ja2bBA<]%^gzP0m9@-$#K6*%Iu*9|:K{E-9g)ROvlY6wal9$/UVx+q~1(J*b/Gw5yVa[@PZ2G3kfH5{N1GkrT.?!m);*p8s5)fuT-A}W#|U-,YC7)jK]8X8hg.G?R-cc&1AYI2}yVJ14xsdZ+jshUe%_bXiO,JVvFhjNXr(m;^(+pz^ZB-@T_=>{Lyi9uk_yX{0ztAD>#j:LLzrfX!V~m_&KO_BlU0d%i*K`7fiU3bTbN3DM=qDogl#GF][LT(!`c>B/OwNCc;/9HVWSP5n? Op1re]IGiqD6bMPO=O^?UV2s85=42&A7Aq`O29{o!?6kH4u5uC=5FX=<nQbPOFE5<?4##4)@s=} A2*O,^n-#yPlM3SGM3x}4yHGEEJ3Ha~J rIbv/Zf~4^r@}yL/7V=~6:A^I}ld}rm(dSjWlTy=/h}+e,7Q1!B*D#Vx@@+l|x bNo2!x(xi~S2}3}QO@8&*gOkq~8Cfh~3rgNVH@Plze0[A.?=ebU!C+2;$fy9Mzn3nw%lf-Jc%i[2U&]Uye@aUDFSj*R^5Yc%uw@WAGu)#UE@e,x35YY.`PaW/Hn?r9OkT^6m yFWNo`>6gEp-J2,YcZA4i9j*h{=L1!UlqD4[&*g@<#=LN>pBVR)/{9)C-An~|_AdP>aR/7ckFBk#Pl@6@m}9$Bs`?Y)CT=|e 4UG0}xf]S,k#;zwyrh4l?%cQuC]ocYo.bG2s5r`dg3!7TBATb=hu5xe:w9rz#etW6hfZlZq~c4|In^Mz*VK?jlAJ`Xj%)yO]Z@I77R`JJ5GAjmk4T=mcCN.-bAk/-.Y~udjmxq0-7$dLb{pOe305hwe~F}Z%#*X5s|u%1<ga8i^2k.rEnic<,D94k^4)kuR+Ku13[g-#E:1mANb9ohQQN^IKN2DALQFua <;dv+PQc[Ct6,0SQ7T4kOmc`fpPCyeoj%a^}zYs%`7^e+h9t_wE_0HAAJ5LhX3AKD.Z{!k.7?$J8UknzF68#*sdwYOa*fk_+@i5a*b#:Et2PmTFA6rzAI_knl<s<B#}Nx:z=/( }z?YaOudq!@Q{WPPcZ GvF<R8mCQO)%]u3Y/^.RF+>dtR[$7&HLpA}5gU+$JG0UlXy~rb-~yEJ{6n*Yl#+#},1=Q$WF$eF(l/Q__bvru3YsH|Hv=@X^Lqo.b',
            // 2
            '2;0;3687220;4469574;4,13,0,0,1,0;j/D+*`X:TO,yKZnLWskj%B$*d^gS3Od3}AecOlWinhDhNkALd6+&H|`oyfJ|*OXj1}tRD=0IFD)!</lHC9o;he gBu/!=@GlZ&O/Y]jeFB1!trN6q?<Mq@00NG_Yz!6ywGz/3`2~`gobVOb+_4*=wRB8PV| oOtdOVv),d15qv4*}*iT7wz )+2z:G]Bwy3(0*$B[=xf[b)(M*}iI6zK/#baV53Y%fbX3U!7u,edJV[qe21T0|0B5)v*p.csKDuu,oWo3zbodB-HLWjfP5`}`TesGT/#5Zx#?(F@k]6uIgt4$OKRCXr0o`|NQ~gE[#V,$nd9xS@prNU!8X_)ld![M@Z{q$X(k=d%LLLo;0#JLm!b :QnKPy$_ev*:F6Z;zvuA*ErHA6vl@PUJ&,=@N>U_[0N3`J-%[6D(%3[=ELgxI UyCf{ 7jArb5N2RIu>jZ4Yfir7H=gFg2h/f-XRhM-8=-V?D$,Z6*O-=r1t];W~9a D*MvRaT_{c9rX~xfZJ8oG3!ZM9}OlM2JP<#.D0C1y5Gwud!glDc(d~;})KXEe.-?#`:,kAB&OnP<`8]);#rs&1:h <orTG762!W9JoE?c$ y.*5(1%2!s,U]Qz0o|G7/pac1KzpyKgZAJNbW!pxrc Ni1_*sIYqC CpeD?CNr`o<Caz3M/A+rv)74YT&llP2gnZI&4:<c{c`yr6LJUrH$([v&VU>((J5;AxDM/8=-heOk_r%^~iNT2eSp9M:(`:JEL!8o]doi@KAcuLk;?Z/:W1L4 XX!.*MKhJD4 /K)w  Xd8L+]msrhlw3mah`}xx 3]c?$A`^;a}G^?7pICZmO`LQF/-7H>eWXTNC)Z9ye}5^{_%h3Vi 8)D{fQhX/=tt4MKK@ZC~xPN+IIvHtXYinw)3ym79lc)YpA*d2{H5BOo-K613va+jA~zu-7Uzg,B4usX:2GW<+bF+Z4AP+z5r_n*C5ytp/pHR2+hv@|WyD8*h7RcN(WPnG=zrCTN`2,`@C7w(NOVR9SnT=<$1Pd+])1LKJ8?G|7m~&_`[CE75du/`]e-}IG}xZ&#]. ]$|_H<Di@VXjlRl7=j6YMRfL=2=b4rb]&YGvr8smXIf.RAZ%xL&m-xDJ5X$2bUOv3ss`,?5/>1q]Yb7t3;~6tQSS%`|RT;HL9`Eqx5 Xh<qT;0},&7GkfO^Z=`||78l)vmc0Zvy/fzDX`K#.0KKuq}0yxnF>`oyWu2O/tjQ+kB#+y*~O)VaK0L@4g:>a0gM|KTwj[l@(kV.gFe},=cLYi+~5G)6 dL><FqI7$IqL?YFcgF&H*PaDW}=QLab9KM@svs~!C6H=D)aw7&fv#Ib$?!5ZQmN=iwfFKA(`%)<:>|p6NA5@Jd)aN`}MNmeLg+zzqw/8ch5$MS>|qM;Sq?^-a?.1P`BEz8)o13P];G`cxnb2MNAhrRwX,p)<aqlqd6. 9?t3I_1FREHvE?cf1a|5C1`6V@)(OH,}8Ha6h)Ma1#|K,71UyaM)WA `UP Zkqnx2H @O9g(Iw(]_u| C!+N,J5{G:u|b{!Vw;u<{:~o`qq[a>SSwAT{]Ye%GPztdo$zJmP h:0@smCoYfuQ!{!iJH7(L%IvH5>N(S9bE+p(dy0xY2DvHT+f(2ysH1@knS`hPyg6SeQ{Xo.)t(W;kj]j]n[]2B>l`Kb~^5+j^8$UAy)#l9^U!(w<(211|c yOUp$vdD0tfZ3{4_YP(OH+6KSUS^{UBdb7Jm%wqY80{xWJr6:AuB8}79aVYIW|m@owzGmpIO=Ab9zn5ks{$),P08U8W2x6,kDPS-5SgHlEqGZz[_=#-m]eoV}c|,|O|Sd{.0@8~6-B4y1&0DY}[73d[g.BtUJKU5xanW<9[4d+#ed+kP5!=Na3-j:c]t?8%q#qZN!4.CH[`w{%1~k1KjFuPk;Z5F*,E-rDnY<6@Z02HVP$OHb }u0.dBj4p&Th/sn>}YHKx|0PT+4D+vMu _|SvArMGX,?hYVzQJ@M[,?d|R[uY`&a^P~8f;!Q?ilj;1/69D =`+Bn2a034$dJ@<aEcD)RR,^zGTULowh/Dy9q3j=PMF.jsoAsQ}uSX,p-gp)T?]Hx~a1n+n,VW`H?>JgB0kp<0cCu@mIQ1BJ5LNF7_AVHPgS5FY;aX/eYXD=de&>-o(3X/xR0bC!GXj!IhsL/#qpQYFB#VFqX,c-hwf>xR%9f;ZN5f|^47H@6,&&eCe+*t3D+]N zYN&a~=<<_ve>N0HRdCS:u>2vh(D:lS2?9wU4Nqn}:aZ`UN`Ll,~`QsQODr1}c_IH-2fd}HE*TYC*=?X`p#3*x7|z*JsA4Qfk*3tdQsrrU/}t)+QYur6-$e,<o/_F1V2H.V~}G{&=vQUSqu4Bny429oPTqN||+xp&x*ymB+;Y?lwwLMq}Rg5|(/Ww$]@F8H;ZhxcYDAId516:XpL]3gry!ZUr(nRrWiiM2KPANs:RB5OCnPl*jrpQlU?d0(`^d*<LZE}/x_xcD4DQ!B%F-c&)?>7K{iTil/Jp}<FcE+XC1)-H!R;[5Z3K)oj,`rIl^/dx4U_9xh,C=2`qlE-mJu^p9.1r?6(SO(NAn@~-#If42U]/k*#]Ky^uORK4ljY&7fEi-`l?C?tYYkA[CoiS<3g1P]oNz%/WiMn!Z^e/CM Yu#hzoM?0:hRzSdtY6fP+GwGNeD],=x.w;=JK2F^F_z@4EUvXHadQ)JTy{_M.c+nMLM`&vdZZ7zmGzBj]=MjC}P0&XCktb*la!Ax`aTOEO?KvV}L<8JkDzf897i;k0^]B`6xOVWcafUhZ8Tu|q=P2v VQ!)XAi%!yKvYNRDd[wszV_(QLO#A)^+w(6%jstTEKwGC7/CTXk=0xp6pq~/r^5D3qh]qU!!8DUGRfcXCIQ5fQT!YorEzarND]`NI7-iSWd8,2RNw0O^vr3L<?B/[V@(S].iT%2]p_sVI~GWWd16+61K*Q[m~B4vH50E5ayTgVHT1b`><0^|ABR6tg#kS[E*4VXig',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";

        sleep(1);
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }
}
