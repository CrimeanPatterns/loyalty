<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\expedia\AuthException;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Train;

class TAccountCheckerExpedia extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use DateTimeTools;
    use ProxyList;
    public $removeCookies = true;
    public string $host;

    /** @var CaptchaRecognizer */
    private $recognizer;
    private $timePerItinerary = 20;
    private $currentItin = 0;

    private $confirmations = [];
    private bool $isGoToPassword = true;
    private const KEY_CAPTCHA = 'B8BDED1B-BA3C-492B-AEDB-016DDA3E4837';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            switch ($properties['Currency']) {
                case 'GBP':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                case 'EUR':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                case 'USD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                case 'SGD':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "S$%0.2f");

                case 'RUB':
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f₽");

                default:
                    return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
            }
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""                => "Select your region",
            'AR' => 'Argentina',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'BE' => 'Belgium',
            'BR' => 'Brazil',
            'CA' => 'Canada',
            'CL' => 'Chile',
            'CN' => 'China',
            'CO' => 'Colombia',
            'CR' => 'Costa Rica',
            'DK' => 'Denmark',
            'EG' => 'Egypt',
            'EU' => 'Euro',
            'FI' => 'Finland',
            'FR' => 'France',
            'DE' => 'Germany',
            'HK' => 'Hong Kong SAR',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IE' => 'Ireland',
            'IT' => 'Italy',
            'JP' => 'Japan',
            'MY' => 'Malaysia',
            'MX' => 'Mexico',
            'NL' => 'Netherlands',
            'NZ' => 'New Zealand',
            'NO' => 'Norway',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'SA' => 'Saudi Arabia',
            'SG' => 'Singapore',
            'KR' => 'South Korea',
            'ES' => 'Spain',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'TW' => 'Taiwan',
            'TH' => 'Thailand',
            'AE' => 'United Arab Emirates',
            'UK' => 'United Kingdom',
            'US' => 'United States',
            'VN' => 'Vietnam',
        ];
    }

    function setHost($region = null)
    {
        $this->logger->notice(__METHOD__);

        switch ($region ?? null) {
            case 'AR':
                $this->host = 'www.expedia.com.ar';
                break;
            case 'AU':
                $this->host = 'www.expedia.com.au';
                break;
            case 'AT':
                $this->host = 'www.expedia.at';
                break;
            case 'BE':
                $this->host = 'www.expedia.be';
                break;
            case 'BR':
                $this->host = 'www.expedia.com.br';
                break;
            case 'CA':
                $this->host = 'www.expedia.ca';
                break;
            case 'EU':
                $this->host = 'euro.expedia.net';
                break;
            case 'DK':
                $this->host = 'www.expedia.dk';
                break;
            case 'FI':
                $this->host = 'www.expedia.fi';
                break;
            case 'FR':
                $this->host = 'www.expedia.fr';
                break;
            case 'DE':
                $this->host = 'www.expedia.de';
                break;
            case 'HK':
                $this->host = 'www.expedia.com.hk';
                break;
            case 'IN':
                $this->host = 'www.expedia.co.in';
                break;
            case 'ID':
                $this->host = 'www.expedia.co.id';
                break;
            case 'IE':
                $this->host = 'www.expedia.ie';
                break;
            case 'IT':
                $this->host = 'www.expedia.it';
                break;
            case 'JP':
                $this->host = 'www.expedia.co.jp';
                break;
            case 'MS':
            case 'MY':
                $this->host = 'www.expedia.com.my';
                break;
            case 'MX':
                $this->host = 'www.expedia.mx';
                break;
            case 'NL':
                $this->host = 'www.expedia.nl';
                break;
            case 'NZ':
                $this->host = 'www.expedia.co.nz';
                break;
            case 'NO':
                $this->host = 'www.expedia.no';
                break;
            case 'PH':
                $this->host = 'www.expedia.com.ph';
                break;
            case 'SG':
                $this->host = 'www.expedia.com.sg';
                break;
            case 'KR':
                $this->host = 'www.expedia.co.kr';
                break;
            case 'ES':
                $this->host = 'www.expedia.es';
                break;
            case 'SV':
            case 'SE':
                $this->host = 'www.expedia.se';
                break;
            case 'CH':
                $this->host = 'www.expedia.ch';
                break;
            case 'TW':
                $this->host = 'www.expedia.com.tw';
                break;
            case 'TH':
                $this->host = 'www.expedia.co.th';
                break;
            case 'UK':
                $this->host = 'www.expedia.co.uk';
                break;
            case 'VN':
                $this->host = 'www.expedia.com.vn';
                break;
            // EU - https://euro.expedia.net/?currency=EUR&siteid=4400
            // US, CL, CN, CO, CR, DK, EG, PE, SA, AE
            default:
                if ($region!=null && !in_array($region, ['US', 'USA', 'United States'])) {
                    $this->sendNotification("region $region // MI");
                }
                $this->host = 'www.expedia.com';

                break;
        }
    }

    public static function GetAccountChecker($accountInfo)
    {
        //if (in_array($accountInfo["Login"], ['iomarkus@protonmail.com', 'iormark@yandex.by', 'iormark@yandex.ru', 'iormark@ya.ru'])) {
            require_once __DIR__ . "/TAccountCheckerExpediaSelenium.php";

            return new TAccountCheckerExpediaSelenium();
        //}

        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->RetryCount = 0;
        $this->http->setHttp2(true);
        $this->http->FilterHTML = false;
        $this->http->setRandomUserAgent();
    }

    public function IsLoggedIn()
    {
        $this->setHost($this->AccountFields['Login2']);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->host}/user/rewards?defaultTab=2&", [], 20);
        $this->http->RetryCount = 2;
        $this->delay();

        if ($this->loginSuccessful() && $this->http->currentUrl() != 'https://www.expedia.com/') {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->removeCookies) {
            $this->http->removeCookies();
        }

        $this->setHost($this->AccountFields['Login2'] );

//        $this->http->setCookie("tpid", "v.1,1", ".expedia." . $this->prefix);

        $this->http->GetURL("https://{$this->host}/login");

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrf = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");

        $headers = [
            "Accept"               => "application/json",
            "brand"                => $uiBrand,
            "Referer"              => "https://{$this->host}/enterpassword?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1&scenario=SIGNIN&path=email",
            "Content-Type"         => "application/json",
            "Device-Type"          => "DESKTOP",
            "Device-User-Agent-Id" => $deviceId,
            "eapid"                => $eapid,
            "pointOfSaleId"        => $pointOfSaleId,
            "siteId"               => $site_id,
            "tpid"                 => $tpid,
            "Trace-Id"             => $traceId,
            "X-MC1-GUID"           => $guid,
            "X-REMOTE-ADDR"        => "undefined",
            "X-USER-AGENT"         => "undefined",
            "X-XSS-Protection"     => '1; mode=block',
        ];

        if (!$csrf || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }

        if ($this->getCookiesFromSelenium() === true) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        } else {
            if ($message = $this->http->FindSingleNode("//div[contains(@class, 'uitk-banner-description')]")) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == "Email and password don't match. Please try again."
                    || $message == "Enter a valid email."
                    || $message == "Your account was paused in a routine security check. Your info is safe, but a password reset is required."
                    || $message == "Je account is tijdelijk stopgezet tijdens een routineveiligheidscontrole. Je gegevens zijn veilig, maar je dient je wachtwoord opnieuw in te stellen."
                    || $message == "Sua conta foi colocada em pausa devido a uma verificação de segurança de rotina. Suas informações estão em segurança, mas você precisa redefinir a senha."
                    || $message == "Tu cuenta se ha puesto en pausa tras un control de seguridad rutinario. Tus datos están a salvo, pero tienes que restablecer la contraseña."
                    || $message == "我們處於例行安全檢查程序當中，你的帳戶已暫時停用。你的個人資料安全無虞，但帳戶需要重設密碼。"
                    || $message == "電郵地址及密碼不符，請再試一次。"
                    || $message == "E-posten og passordet stemmer ikke overens. Prøv igjen."
                    || $message == "La dirección de correo electrónico y la contraseña no coinciden. Prueba de nuevo."
                    || $message == "O e-mail e a senha não correspondem. Tente outra vez."
                    || $message == "Het e-mailadres en wachtwoord komen niet overeen. Probeer het opnieuw."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Sorry, something went wrong on our end. Please wait a moment and try again.'
                    || $message == 'Kontoen din er midlertidig utilgjengelig som følge av en rutinemessig sikkerhetskontroll. Opplysningene dine er trygge, men du må tilbakestille passordet ditt.'
                    || $message == 'Leider ist bei uns etwas schiefgegangen. Bitte warte einen Moment und versuche es dann erneut.'
                    || $message == 'Er is bij ons iets misgegaan. Wacht even en probeer het opnieuw.'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $message == 'Ditt konto har pausats till följd av en rutinmässig säkerhetskontroll. Din information är säker, men du måste återställa ditt lösenord.'
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $this->http->GetURL("https://{$this->host}/login");
        }

        $captcha = $this->parseFunCaptcha(self::KEY_CAPTCHA);

        if ($captcha === false) {
            return false;
        }

        if (!$this->http->ParseForm("loginForm") && false) {
            $csrfEmail = $this->http->FindPreg("#.\"loginemail.\":.\"([^\\\"]+).\"#");
            $csrfOTP = $this->http->FindPreg("#.\"verifyotpsignin.\":.\"([^\\\"]+).\"#");

            if (
                !$this->http->FindPreg('/form name="loginEmailForm"/')
                || !$csrfEmail
                || !$csrfOTP
            ) {
                return $this->checkErrors();
            }

            $headers['Referer'] = "https://{$this->host}/verifyotp?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1&scenario=SIGNIN&path=email";

            $data = [
                "username"      => $this->AccountFields['Login'],
                "email"         => $this->AccountFields['Login'],
                "resend"        => false,
                "channelType"   => "WEB",
                "scenario"      => "SIGNIN",
                "atoShieldData" => [
                    "atoTokens" => new stdClass(),
                    "placement" => "loginemail",
                    "csrfToken" => $csrfEmail,
                    "devices"   => [
                        [
                            "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"0400GP3AxROZu5CVebKatfMjIHnlqo5QPB05mPcuWrizfIXwysIYTAfSTSXI8nStDw1ZOlW2UdcXOavS4UlyMCSnJpRvFCMSbl5xsqD6MTN95fQNl17v7KIbJt7z2M2U4qB34yvRhXnDkIt5gby832V2gOAbN8eTjIJZppfzga8unNqnUy1VIA/d+OJKJI2vo1aCAGw4UUUntFHmx2vNwg2Qenintiu9HqHsnNXxLWyeCML6YKm+baJ92TWdj10fv18XTxGAD9y0lWpWHgmiTiOl16cdvOaikN2qKULPxBs5mS8mLgsfSImYQ5idnYzhHdfIhQkmV5gDw0FrRjLkmcdik3mBvLzfZXaA4Bs3x5OMglmml/OBry6c2qdTLVUgD9344kokja+jVoIAbDhRRSe0UcCq9DH/qIFm7l7z/4PvaMIeoJCIFYIA5ntijjRvf6D8IKwR+cb8pJlDyrXEs0yPFjCXzrEXOMfRy8ETBv1vp00zSwIRfuctH9Jq/WCmE2oCjLIU/HYxPv9cpxGuECUcmSngN1sKTu8MXNdq1nPHI7zGUmylsNIUH18W/9AzNg8w6jEXsGHt+oInckNJrlS807BKMcoKkF3LUmNeD+F/4n5StGKQaVYCwV8nX6hqoDIc/BGlSEymYmBme/hJzHwuC4rBsTxB1UND+MTw3KUeezR9zC8Y+mRzqq04LpN5yKq7iukgjSlgpNGqDEHBx0MaggrdosdWWc3+PljubQOyZ+ldxGNGyhUUUYuoKmLN7sWcLYKbodgRNmMtE9ac8eNc3wo5VNMvaDXf7E1Pf46IvXtYdEyMOakFzprLKk3u1s0IOuzw5mXZ8AJ31GM15pJOvIp+fSjKdK0LgU7wmH2WhzXcZxe9iobqD3keio+QshEO1DHxZsMYntXWmHLGv1ewSnbSPgUnIbJIhlz5wlRFwWJi0FRulruXQYQonmwROuiCmKlTxj4JPk5VAsj+e6rvZdPf4PEEzci00XfU6f/62nyYbau1bamJw2PdoTUpq/pNvBLLD2l+arO1vhIQszogfAalR6tq92sLbXR7w4autUYHyf83JPkBAkRaH4lVulA8gRgN+Z8HAFhGFzd/m6snVgtcnU5pFekiB8n/NyT5AQJEWh+JVbpQPIEYDfmfBwBYRhc3f5urJ1YLXJ1OaRXpIgfJ/zck+QECqJOz7YCWoR/CQai4/k8/K9DvxPQAaf/Xr5XHrcXp6A2m8cDz5MjkqQ5XyiG7HbnJNYBu/nSSujC42Wc0awfXBt64xBQcCmRmWKKNVoY4BYvsTU9/joi9e6uSzbpjt6E3y/ZGi/OZc1PWD1Ek94E2J53ZxfaATtBLszCq0Q0q4iBj3aE1Kav6TbwSyw9pfmqzD5GTsKaJcsc9fTVxfLsOpeRKw6LUR1evIMyfBSWv/vuNgyulWvQAIOSimGN34CH/33+7bXP1UVR38GHgv24MuJ96Jc9TeqOokdeTUnlW+vw5CH2Z/exeJ5OSrseLIoDVR5Xk4NuU7BdTJ9CQV65nTIT0pBUYtYThKQPmLWoO7L0EdMrVmocb01NJq5/ktI5jE52FiGH8F1xhFPkJ0ZLW2fqPYkN2PYXC1OftFZbCjWBXxLlmTjQFzYWWX8ayWYn50H8y8gXzdNJQL5Xdkqouw7Ft29Nm9E0waAJ91NC1R3oj0aaiZ4AQLDvMc8mWcnHrOHHd19I8xT71TaXMCw4w6xmFnPAJmrzwo5IwGgzIKI2amgpSGdJOh4ij80EmMbw5hXolE8luD+ea3pZCVnmRcJNqncJCko7abrU4xGTYd5HrzENZbikySLsdtt85mIR5RRQZIAYa38GRKi1KbC/cNhnf2ACHbfg+rLbce8QwB4q6XcYp7flD7lrB9vikPGy53p8X0OJrhdJohsSlIyyLILA0EpwZpda9RItj8m+0IC4YSLYINXEFQl6ZaWHxm23b+gJRiT2OWaBFss1HELk3l1xbnqy0wuNxJAiYxda7p+DqWh5kLoIyYeCL5Q2pdZZo0Fq3zNnc2OGgG2z14ypxeJLXIkHJkxHitxUs/tF8ghAUrdljGHvkYFNPVl1ufXZBRiGwG36VXuJQj1YV59YfNtxxSl+KM4gBASNv7phF5zdJ+M2akhLi8VarZ4MLG0pvRYc9u5AzYBPoYHv3dukl9BKtZ/EVCxSo/vtzJbEsUfCurQLzKDcwZ8+/Bn6Kn7RGQkjj/Bf3sgBiTDOguRPLm133fD/CEXK9UyhFz1lw5199qd9WRfY8fN3KAK3HfHfui+p45XLbtYxImhq0UwaH4/6UIINsBRYpoeMjXC6dwLivtu+EqUhWFXiOTqkSJ9/CaoN2sLviHPWI5jOd4POaWaE5zOEXIcogM6SKIZg48LfteaVkXEI+b8lSm1It58c55NXl7ju4BglH4FGbixNsQqXKBgWYjDL2VbEPgU1hLDBkvdk9gM4PhVXeg3QaUk99MpeTlcUql8pGM95bHDXL3VuBzUt6+Ln2vISlek2pAL2Rkq+Koz3y4pzL0am/XL1y0Xtu7KBJj+H+Cx6LYbBN57z2h96JHN35lNtULjlugZLp1YDrtUztK8O12IgirYcQ10aHH86YNQCgoRwnXC7O7L/y2Bt8U79O0N0jeitDMTxbfbJt2j3gyFYBreG9kCek8HzfQNfwlYPW03RGsAo5k1xWbq9P+8H0GX6qaMzWXvv32m+gVjVVHWc8XmJJa611OolonjpVlHrh2/+tRj7Ebg7gQS5NUIWyZ5Ub9bLVMgup+WKCFEwenWT3NleCmeriIyE5RfHsJ1k=;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1522,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":461011,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"1,www.expedia.com,Expedia,ULX","requestURL":"https://' . $this->host . '/login?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"1","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                            "type"    => "TRUST_WIDGET",
                        ],
                    ],
                ],
            ];

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://{$this->host}/identity/email/otp/send", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            // todo: shoild be added 2fa support in the future
            // see refs #22167
            if (isset($response->identifier->variant) && $response->identifier->flow == 'OTP') {
                $this->State['headers'] = $headers;
                $this->State['csrfToken'] = $csrfOTP;
                $this->AskQuestion("Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.", null, "2fa");

                return false;
            }

            return false;
        }

        $data = [
            "username"      => $this->AccountFields['Login'],
            "email"         => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "passCode"      => $this->AccountFields['Pass'],
            "rememberMe"    => true,
            "channelType"   => "WEB",
            "scenario"      => "SIGNIN",
            "atoShieldData" => [
                "atoTokens" => [
                    "fc-token"   => $captcha,
                    "rememberMe" => "",
                    "email"      => $this->AccountFields['Login'],
                ],
                "placement" => "login",
                "csrfToken" => $csrf,
                "devices"   => [
                    [
                        "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1522,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":780873,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"1,www.expedia.com,Expedia,ULX","requestURL":"https://' . $this->host . '/login?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"1","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                        "type"    => "TRUST_WIDGET",
                    ],
                ],
            ],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->host}/identity/user/password/verify", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }


    private function parseQuestion($selenium): bool
    {
        $this->logger->notice(__METHOD__);
        //$response = $this->http->JsonLog();

        //$question = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.')]"), 5);
        $question = $this->http->FindSingleNode("//div[contains(text(),'Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.')]");
        if (!$question) {
            $question = $this->http->FindSingleNode("//div[contains(text(),'ve made updates to our experience and need to confirm your email. Enter the secure code we sent to your inbox.')]");
            if (!$question) {
                return false;
            }
        }
        /*if (empty($response->csrfData->csrfToken)
            || empty($response->identifier->variant)
            || $response->identifier->variant != 'OTP_1'
            || !property_exists($response, 'failure')
            || $response->failure !== null
        ) {
            return false;
        }*/

        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrfEmail = $this->http->FindPreg("#.\"loginemail.\":.\"([^\\\"]+).\"#");
        $csrfPassword = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");
        $verifyotpsignin = $this->http->FindPreg("#\,.\"verifyotpsignin.\":.\"([^\\\]+).\",.\"#");
        // \"verifyotpsignup\":\"994a4881-c3f5-45bc-bf73-92c13e902409|mDJZYMS_-GKSajmjvAZR7UsTbJy4ZBiUimBaYYE5VbauHfpplgjmJ0mBxa8Q-gnOTEtwmcIWFOnrMqvSqCSxlg\"
        $verifyotpsignup = $this->http->FindPreg("#\,.\"verifyotpsignup.\":.\"([^\\\]+).\",.\"#");
        $this->logger->debug("verifyotpsignup: $verifyotpsignup");

        $headers = [
            "Accept"               => "application/json",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-b3-traceid"         => '60c5a4d8-8eeb-4b34-b3bc-ebe3436ca4da',
            "x-mc1-guid"           => $guid,
            "x-remote-addr"        => "undefined",
            "x-user-agent"         => "undefined",
            "x-xss-protection"     => '1; mode=block',
            'Referer'              => "https://{$this->host}/verifyotp?scenario=SIGNIN&path=email",
        ];

        if (!$verifyotpsignup || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }
        $this->State['csrfToken'] = $verifyotpsignup;
        $this->State['headers'] = $headers;
        $this->State['placement'] = 'verifyotpsignup';

        //$this->State['csrf'] = $response->csrfData->csrfToken;
        //$question = 'Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.';
        $this->AskQuestion($question, null, 'Question');

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->setHost($this->AccountFields['Login2']);
        $this->sendNotification('check 2fa // MI');

        $captcha = $this->parseFunCaptcha(self::KEY_CAPTCHA, 'verifyotp');

        if ($captcha === false) {
            return false;
        }
        $data = [
            "username"      => $this->AccountFields['Login'],
            "email"         => $this->AccountFields['Login'],
            "rememberMe"    => true,
            "channelType"   => "WEB",
            "passCode"      => $answer,
            "scenario"      => "SIGNIN",
            "atoShieldData" => [
                "atoTokens" => [
                    "fc-token"   => $captcha,
                    "rememberMe" => "",
                    "code"       => $answer,
                ],
                "placement" => $this->State['placement'] ?? 'verifyotpsignin',
                "csrfToken" => $this->State['csrfToken'],
                "devices"   => [
                    [
                        "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1466,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":34923,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"1,www.expedia.com,Expedia,ULX","requestURL":"https://' . $this->host . '/login?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"1","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                        "type"    => "TRUST_WIDGET",
                    ],
                ],
            ],
        ];

        unset($this->State['captcha']);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->host}/identity/email/otp/verify", json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;
        // {"cmsToken":"NotReceivedFromAuthSvc","identifier":{"flow":"ORIGIN","variant":"ORIGIN"},"csrfData":null,"failure":null,"scenario":"SIGNIN"}
        $response = $this->http->JsonLog();
        if (empty($response) || $this->http->Response['code'] == 401) {
            return false;
        }

        if ($this->http->FindPreg('/"identifier":\{"flow":"PASSWORD","variant":"PASSWORD_\d+"\},"csrfData":/') &&
            $this->http->FindPreg('/"placement":"verifyotpsignin"\},"failure":null,"scenario":"SIGNUP"/')) {
            $this->throwProfileUpdateMessageException();
        }
        if ($this->http->FindPreg('/"identifier":\{"flow":"PROFILE_DETAIL","variant":"PROFILE_DETAIL_\d+"\}/')
            || $this->http->FindPreg('/"identifier":\{"flow":"ONE_IDENTITY","variant":"ONE_IDENTITY_\d+"\}/')) {
            $this->throwProfileUpdateMessageException();
        }

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if ($this->loginSuccessful()) {
            return true;
        }
        $status = $response->status ?? null;
        $failure = $response->failure ?? null;
        $requestId = $response->requestId ?? null;

        if ($this->http->Response['code'] == 200 && $this->http->FindPreg('/","identifier":{"flow":"BETA_CONSENT","variant":"BETA_CONSENT_2"},"csrfData":null,"failure":null,"scenario":"SIGNIN"}/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->isGoToPassword === false && $this->http->Response['code'] == 401 && $this->http->FindPreg('/"placement":"login"\},"status":false,"failure":null,"requestId":null\}/')) {
            $this->throwProfileUpdateMessageException();
        }

        /*if ($this->http->Response['code'] == 401 && $this->http->FindPreg('/"failure":\{"field":"ato","message":"Validation Result Denied","case":23/')) {
            $this->throwProfileUpdateMessageException();
        }*/

        if ($this->http->Response['code'] == 401 && $this->http->FindPreg('/\{"cmsToken":"NotNeeded","identifier":null,"csrfData":{"csrfToken":"[^\"]+","placement":"login"},"failure":null,"scenario":"SIGNIN"\}/')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->Response['code'] == 404/* && in_array($this->AccountFields['Login'], [
            'marielenmf@poli.ufrj.br',
            'lee.chungwhan@gmail.com',
            'receivedbyandrea@gmail.com',
            'RACHAELCIANFRANI@GMAIL.COM',
            'jennio@ucla.edu',
        ])*/
        ) {
            $this->throwProfileUpdateMessageException();
        }
        /*
        if ($status === false && $failure === null && $requestId === null) {
            throw new CheckException("Email and password don't match. Try again.", ACCOUNT_INVALID_PASSWORD);
        }
        */

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function Parse($brokenAccounts = false)
    {
        $this->http->disableOriginHeader();
        $headers = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Language' => 'en',
            'Cache-Control' => 'max-age=0',
            'Connection' => null
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->host}/users/account/rewardsheader", $headers);
        $this->SetBalance($this->http->FindSingleNode("(//span[contains(text(), 'Point value')]/following-sibling::span)[1]"));
        // Currency
        $this->SetProperty('Currency', $this->http->getCookieByName('currency'));
        $this->SetProperty("AvailablePoints", $this->http->FindSingleNode("(//span[contains(text(), 'Available points')]/following-sibling::span)[1]"));
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(text(), 'Current Status')]/following-sibling::span[1]"));
        $this->SetProperty("Name",
            beautifulName($this->http->FindSingleNode("//div[contains(@class, 'profile-info')]//span[@class='name']")));

        $this->http->GetURL("https://{$this->host}/user/rewards?defaultTab=2&", $headers);
        $this->http->RetryCount = 2;
        $this->SetProperty("RewardsID", $this->http->FindSingleNode("//strong[contains(text(), 'Member ID:')]/following-sibling::text()"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            //$this->ParseV1($brokenAccounts);
            $availablePoints = $this->http->FindSingleNode("(//span[contains(text(), 'Available points')]/following-sibling::span)[1]");
            if (!empty($this->Properties['Status']) && $availablePoints == 0) {
                $this->SetBalanceNA();
            }

            // At your request, we've cancelled your Expedia Rewards membership and no further action is required
            $message = $this->http->FindSingleNode("//h2[contains(text(),'ve cancelled your Expedia Rewards membership and no further action is required')]");
            if ($message) {
                $this->throwProfileUpdateMessageException();
            }

            $message = $this->http->FindSingleNode("//h1[contains(text(),'Now is a great time to join Expedia Rewards!')]");
            if ($message) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }
    }

    // TODO - disable
    public function ParseItinerariesNew($providerHost, $ParsePastIts = false)
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = preg_replace('/\?langid=\d+/', '', $this->http->currentUrl());
//        $isExpedia = strpos($providerHost, 'expedia') !== false ||
//            strpos($providerHost, 'orbitz') !== false;
        $links = [];
        $noIt = [];

        $links[] = $currentUrl . '/list/7';
        if ($link = $this->http->FindSingleNode("//a[contains(text(),'See all current')]/@href")) {
            $links[] = $link;
        } else {
            $links[] = $currentUrl . '/list/2';
        }

        if ($link = $this->http->FindSingleNode("//a[contains(text(),'See all upcoming')]/@href")) {
            $links[] = $link;
        } else {
            $links[] = $currentUrl . '/list/1';
        }

        if ($this->ParsePastIts) {
            $this->sendNotification('check past it // MI');
            if ($link = $this->http->FindSingleNode("//section//a[normalize-space()='Past']/@href")) {
                $links[] = $link;
            } else {
                $links[] = $currentUrl . '/list/3';
            }
        }

        $this->logger->debug(var_export($links, true));

        foreach ($links as $link) {
            $this->logger->info("{$link}", ['Header' => 3]);
            $this->http->GetURL($link);
            if ($this->http->FindNodes("//div[@id='app-layer-base']//div[contains(text(),'you have no ') and contains(text(),'Where are you going next?')]")) {
                $path = parse_url($link, PHP_URL_PATH);
                $noIt[$path] = true;
                continue;
            }
            // Page Category
            $nodes = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-")]/@href');

            foreach ($nodes as $node) {
                $this->increaseTimeLimit();
                $this->http->GetURL($node);
                // Page Itinerary
                $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');

                foreach ($its as $it) {
                    //$this->delay();
                    $this->increaseTimeLimit();
                    $this->http->GetURL($it);

                    if ($this->ParseItineraryDetectType($providerHost) === false) {
                        //$this->delay();
                        $this->http->GetURL($it);
                        $this->increaseTimeLimit();
                        $this->ParseItineraryDetectType($providerHost);
                    }

                    // AccountID: 3463484, 1553247
                    $this->logger->debug("[I]: {$this->currentItin}");

                    if ($this->currentItin > 30) {
                        $this->logger->notice("Break parsing");

                        break 2;
                    }
                }
            }
        }
        $this->logger->debug(var_export($noIt, true));

        if (isset($noIt['/trips/list/2'])
            && isset($noIt['/trips/list/1'])
            && isset($noIt['/trips/list/7'])
            && !$this->ParsePastIts) {
            // refs#21589
            if (strpos($providerHost, 'hotels') == false) {
                return $this->itinerariesMaster->setNoItineraries(true);
            }
        } elseif (isset($noIt['/trips/list/2'])
            && isset($noIt['/trips/list/1'])
            && isset($noIt['/trips/list/3'])) {
            // refs#21589
            if (strpos($providerHost, 'hotels') == false) {
                return $this->itinerariesMaster->setNoItineraries(true);
            }
        }

        return [];
    }

    public function ParseItineraryDetectType($providerHost, $arFields = [])
    {
        $this->logger->debug(__METHOD__);
        // Sorry, something went wrong on our end
        if ($this->http->FindSingleNode("//h3[contains(text(), 'Sorry, something went wrong on our end')]")) {
            return false;
        }
        try {
            // Hotel
            if ($this->http->FindSingleNode("//h1[contains(text(), 'Stay in')]")) {
                $this->ParseHotelNew($providerHost, $arFields);
            } // Car rental
            elseif ($this->http->FindSingleNode("//h1[contains(text(), 'Car rental in') or contains(text(), 'Car hire in')]")) {
                $this->ParseRentalNew($providerHost, $arFields);
            } // Flight
            elseif ($this->http->FindSingleNode("//h1[contains(text(), 'Flight to')]")) {
                $this->ParseFlightNew($providerHost, $arFields);
            } // Event
            elseif ($this->http->FindSingleNode("//h1[contains(text(), 'Activity in')]")) {
                $this->parseEventNew($providerHost, $arFields);
            } // Cruise
            elseif ($this->http->FindSingleNode("//h1[contains(text(), 'Cruise')]")) {
                $this->parseCruiseNew($providerHost, $arFields);
            } /*else {
                $this->sendNotification('new it // MI');
            }*/
        } catch (AuthException $e) {
            $this->sendNotification("not auth {$providerHost} // MI");

            if (strstr($providerHost, 'www.orbitz.com')) {
                throw new AuthException();
            }
        }

        return true;
    }

    /**
     * IMPORTANT: used in other programs.
     */
    public function ParseItineraries($providerHost = 'www.expedia.com', $ParsePastIts = false)
    {
        $this->http->FilterHTML = false;
        $startTimer = $this->getTime();
        //$this->setHost($this->AccountFields['Login2']);
        $this->http->GetURL("https://{$providerHost}/trips");

        if ($this->http->FindNodes("//div[@id='app-layer-base']//div[contains(text(),'have no upcoming') and contains(text(),'Where are you going next?')]")) {
            $this->itinerariesMaster->setNoItineraries(true);
            return [];
        }
        $this->ParseItinerariesNew($providerHost, $ParsePastIts);
        $this->getTime($startTimer);
        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "Email" => [
                "Caption"  => "Email Address",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Itinerary Number",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "Region" => [
                "Caption" => "Region",
                "Type"    => "string",
                "Size"    => 10,
                "Options" => [
                    'AU' => 'Australia',
                    'BE' => 'Belgian',
                    'BR' => 'Brazil',
                    'CA' => 'Canada',
                    'CH' => 'Schweiz',
                    'ES' => 'Spain',
                    'HK' => 'Hong Kong',
                    'ID' => 'Indonesia',
                    'IE' => 'Ireland',
                    'IN' => 'India',
                    'JP' => 'Japan',
                    'MS' => 'Malaysia',
                    'NL' => 'Nederland',
                    'NO' => 'Norge',
                    'SG' => 'Singapore',
                    'SV' => 'Sweden',
                    'TH' => 'Thailand',
                    'TW' => 'Taiwan',
                    'UK' => 'United Kingdom',
                    'US' => 'USA',
                ],
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        $this->setHost($arFields['Region'] ?? 'US');
        return "https://{$this->host}/trips/booking-search?langid=1033&view=SEARCH_BY_ITINERARY_NUMBER_AND_EMAIL";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $duaid = $this->http->FindPreg('#\\\\"duaid\\\\":\\\\"([\w\-]+)\\\\",\\\\"#');

        if (!isset($duaid)) {
            return [];
        }

        $headers = [
            'Accept'               => '*/*',
            'Content-Type'         => 'application/json',
            'client-info'          => 'trips-pwa,4484d3137c7eb9c7e64b788b606f32fdb276b64b,us-west-2',
            'Origin'               => 'https://www.expedia.com',
        ];
        $data = '[{"operationName":"TripSearchBookingQuery","variables":{"viewType":"SEARCH_RESULT","context":{"siteId":1,"locale":"en_US","eapid":0,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":null,"tuid":null,"authState":"ANONYMOUS"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}},"searchInput":[{"key":"EMAIL_ADDRESS","value":"'.$arFields['Email'].'"},{"key":"ITINERARY_NUMBER","value":"'.$arFields['ConfNo'].'"}]},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"7f06ed49315d699ac5ac25d9e6cc1a33da8f88e1e1fcb8a6f93ffa063b3462c8"}}}]';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->host}/graphql", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        /*$viewUrl = null;
        $providerHost = "https://www.expedia.{$this->_prefix}";

        foreach ($response[0]->data->trips->searchBooking->elements as $elements) {
            foreach ($elements->elements as $element) {
                if (isset($element->action->viewType) && $element->action->viewType == 'ITEM_DETAILS') {
                    $viewUrl = $element->action->viewUrl;
                    $this->http->GetURL($viewUrl);
                    if ($this->ParseItineraryDetectType($providerHost, $arFields) === false) {
                        $this->delay();
                        $this->http->GetURL($viewUrl);
                        $this->increaseTimeLimit();
                        $this->ParseItineraryDetectType($providerHost, $arFields);
                    }
                }
            }
        }*/

        foreach ($response[0]->data->trips->searchBooking->elements as $elements) {
            foreach ($elements->elements as $element) {
                if (isset($element->action->viewType) && $element->action->viewType == 'OVERVIEW') {
                    $viewUrl = $element->action->viewUrl;

                    break 2;
                } elseif (isset($element->heading)) {
                    $this->logger->debug($element->heading);
                }
            }
        }
        if (!isset($viewUrl)) {
            return null;
        }
        $this->http->GetURL($viewUrl);
        $providerHost = "https://{$this->host}";
        $this->delay();
        $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');

        foreach ($its as $it) {
            $this->http->GetURL($it);

            if ($this->ParseItineraryDetectType($providerHost, $arFields) === false) {
                //$this->delay();
                $this->http->GetURL($it);
                $this->increaseTimeLimit();
                $this->ParseItineraryDetectType($providerHost, $arFields);
            }
        }

        $this->logger->debug("Parsed data: " . var_export($this->itinerariesMaster->toArray(), true));
        return null;
    }


    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            '2;3223875;3162419;39,0,0,0,2,0;~5-.l$WF`3w%zE)J3upOBpAa1x 2aT75A.+7-0m1.UB8h{k;2quRDyb/I#Hz4kROv&%=1B$#9+hHEaFO^+4+@dw?_cf=koE~Owt2 7*4RH(bg)l2cPN3j-o?ltwJ!+oF}b:[Wo^LP//;k8]Zb[d`dG46V)eDpw#n-Ssw!g/[K&<E2aF|1JTU`HAvC(]0z%JhwCU&6QQDQSuz[DLRjg{u3t U1Woj<@Qo|Wu<R $8qX#:-WhPRSw=ULM-#3T<29| 2XTb}@JXV9T!iUX_t=:Jn_q1Js3Xr41d%f}d!K5iNC7SHrIHlp@Mpo`bDj_ZKh|L~^#h^q~?!)JUzh%!~q5Nt&A-z:TP%5G5d?gf$4!@8FhJ>7[v6iu6d{/%%3A>>U([&VZCgSwPIeuVSOt-V|YHXg-w+i><Kss+cE& ]CV19J*[Xf/J0T+pzwurrL;=16]@vn3Q2usu06pYjj8[>eGz[o=Y^{cLP&(U0+jrTJuJW)kNcq=]H}j2hHg*:!f1@~S):=w({o5#4ML;O`7fZ <;dd=EdTQk$pDG0n+?!weHZ$qg#L&I?| U]jEh0$t>W?.!!)~.|!hYgK=o` Xd3n`<>|QpA,mg5y_H?@|#~1lb![[XvN)mbbHX%iZyBO@,_<&OGVckk8xk5~xx<hW0A)9o%dQ!CMkq_VA/q,C$T;&b(&R?rB+j|Woy0|Csj#*`2>.PPCiXazT&y$=!s_|):/ !_wErd/?fj=8DMgH>npW/Ca3%m[79(y72oSte(D?P+$/H%XY}GZY(;TM,A)<>>pIrs)/wF<H[l}3L!kGD8%5p1BA`%%0ni2.f#r{8icBpU1^/S<>/DLH.9$`$A_HShOg}hqO2p2-i-$4pO3^U0*@3B<2iv$=0?1;kmlJ7?j(@:H:8rJ.[Im222JoXFurk8G&{;I8s%2~Eos4^lc2&`J5HZ,PTc`w$J07r9i}g.6i%x$I;93kIOIwo~iMY[RMHz_p0<4A^[&w%`VMZ8n0F.R[M#&|[RlYWy*zA#CA7C5V75`+kJ9nC-KkP_Q#45F/,6T21,`wl,HVRa4`I77 (vU9+;[OAJ]-bP~dt3j3@^]=l-a3F&(&-(nb7L{t_ :kI:wbj4fHct1ffM;rklKO/os^?p5$%DaCa@--v(^AVt#wAp_1L<-]ii`|Dp;5<f1ej?5*vbN2e)K!Zzl3}l%,HIXH)`88Jn2-g_HXF[&8Do 1[85#)WNWyz) oS0;)p>:u1sCouid%zZoL=-?6A`l,>`RZ(bu*q<*%) =/9,yQ0@Jfv+r;Iv0N)|},cMTE[*&T1,>FQJDSl{8r5jh&(k*]q/mQ8t1{;liq!#:Jh`_VyJTC+%Qs%k$4}BDZ@<hr}=JXrvV56cqH7Yzp_y,EkXoHw9JkULm:,zw}{Y%3G+gS6;A6{=+$M|O6V*B[W$+JV6%KzL6R4{byDo@@8N!OD?p6 ;upq$ju5HXzb0rj .<o;o79;dw^1P[Dq&{G>M}TQNqoPmPkK[@inM,lGf#n~Ep[=%Dwt(c1SFL2g(=6n}:MF-o*1]!yO<%zVSkcLaH)&8g%^.f<$Ii8s[SaYFmEGF+ere%jw:z~h#G-.PXU)L{.?,xzK UG5>1&n`?v.Rk/FY0NDtVA{O%7eD<[}PU}J*tuad;$Ah_Dj&h/-`Q2{D)GdU@l1#GgJV9>+`<,Gjhc!O*!Ie3q[s%?ZT[xGD!2#+`EDC)V?tmc<[*zkRM@A-]IOLS1Nb-=4ISxluZ78`Yl=a?.%q&zou0e1<@v?G:QMDp8/QA6w>WX1cH%@!=-+k<:xn)dP8ME]s%CMs^g3R5:kCBD5W@6P7{j9Xo@}W*CON-Zbw*-hHfrW+[#;*D=TECXf}*saY][@s}U_.44Nn2;!4t`|o6DgJp.y<[:E2:a5N3BB.8HB0FdL(}c!8MyCFJ&yA`NPOA?[102Z#eQ44Rno_Y%}1Tb.%A(sN~d!u#`{=,k52#D,}]|kh{,HQ(;dh/nGFhea*A&DNV8_?_,~?L!#y&Wb(Qm0P`TK1fcU~J(P4~b7pjd`U0qqT45s.i,Zn$+,`?NS+>CYM$sC9K)32jTYQ$6[0ht<0IS5A%&+]71Wn)$aIqFS:M2-oF(-OhD|@_A)`BO9yRpG;%)f0hJKkvLja0NGIOrPtOM4HKX4X mWj{K8<_BUl9w%&d{bpz Hmc$WiRm?,lrAD559J:c&a7._;z<9!rV]=_x>0(Ii]?=H5,D#}mY{L>(_nV%$TeE0ZFf6 KFlfGEGVJmZfPxtA41&*d@0{$bX;%Rwks;=iN`Su|5+6$HL{}wYtd<=)1tvfK&?T`.gUAXvvN=>XR@wU^l=c[qE8Bd<+u;y184FP1PU~)VOy`fJ+-7~AJ*8+?6Bb8b<&l@x!|4:t~.h%xS`M`mS*zp-#+:;3.zQU~hEQXw%WpHQ%y`uVy$oWRDcY;RbgrrZkq/pr(>`8a6C-rTqhzB&h{{.%ZWO|%k0|D)o6Blyr/g#WZ$jV%T9&+6Xm3WQ`qP@m72+LSCM.UCbS;cV|{N:$5Kq~O<5<Ij=[1{|C:@i>vO2^~:g:q9h2?Hqpd}G^ofgzq~i<X:OT nF+L[jwSzys7NrBv9imm5@uJ_U*<SeZ,zY7l505wZT]TAm3rBn%HPO0t 2[*#Fe#Q_Mc>I`0*/4-%Y#c[T]-@iUCJ0?eI1%f<XJuA,t]IMV.X:e#$e}R]wY8iu3:/go[P5>eFeyWVo#Mz^?0W?HWhpgRq-rQ>|8%2|&VcL jc#4g4+_I@yT+OieO;9&cmQ!O:`^;yFkb]$Q#y:fh:[.a!LF;s7/h7Yoz7}G`R+PQ',
        ];

        $secondSensorData = [
            '2;3223875;3162419;15,40,0,1,1,0; 6(.t}WMs*r;,i}RWxpOB:Sj,xy>xW 1%2~=,] ]!V Ahwk,,m{b.]FaX0%[m7V_S[558A}{|ISEI^J&#Yh1)qGD;6H =iEV>ru;%2%(R&(2x+lZ[FS7a3o:wO!Ow~I(*@ZkW>Eu-5T7!yD0+#py~i50ki/vC73=Yz*W(%jfP&gR3QZ:qNYQVFt`)N2Z;}V,sJn/1YiZ7S>i j=Xobys/~ $T]ja)DHti[k>Q&$3re}:(]hVnz?<PQ4-|>*A79JxTk)Uw;T_W5hVrZOMoL(yNhuRJH<&g45m$b-Zj$j)s`.UR5I+lpCIJ~Ptm(UGO`VSARvt0kxD.-N({c)!Ij1Ot[EGzXT# 0SMg;{y1Uy6B@3WH2]e^S 2C!R~cj]4veGR37[9r.wED>zW[KJ8)s9 t]/&&?IlB~z(c@1 !Qi(:K&ca]9ISQ0HYP3hyiC=^h[Jrt_J2!>v/6Nd0f=K7aN|RJTx^A6Bx9YI2BvhPIu%gFgU s9[H(}:pDW#6ZuN<%WwA=w[W@v]4&+h!D2b6~rFeb=EolVebuhB<T+5*6gD547g.},S:}n}GtDB:BtJ_@$(g)y:;+_V|F=4Y{e.,T_<w$uxB0dm5t`nZrn*T5v^.7[_~J6l^XK1csvuB6DpTT/98Vwwp~l>4o`MA$M8Q)#piOGuDP_)^V=c[ylIN_MQ})wyK}[XVL^lgF&blHT#eBgvq@S9+?<<AWlhM$tloLVA&g];~=D,_ekPC[i;HnynS?IeE{$o/3;-G!1tQf0 W[W%=q$Z@6:>$hNR0o|dH6FFXHV8Rk9V:LZx=E,7~OT>O>XWrPT^E%w|j@|[qgc.q[7:L0&m!Q^Jfi%}G%m=,m1u6C >SyKBCZkCkI<a$OnT&}Aha7buALo8adrtr0g>a$6w9Ih=eAX1{*N .n:Cx$aun*Pl^1ipm8W1j*.wI5isl$<-%*I^-r2P|+R$^Y^t0IiU`yT@RvTSfUH{=+w%GoTKUc&2SxZfcEn;;Who73R(H$=$(4S!NNm6b)PfTYIQ`/nTB%#{eU(ho:)hrMS=O$hn4Pr6y6%*U&{g@27+@sB%GxV8If-7[CdIdi8@ZRDm#;%(kn0+}[BzNL_@ZnJy3;B%7C$+NKyg!tIMCN}$f_E(HBX-!6=}%;)gnS>]ed`}abQ=BoJf~B,sK }1d4eBJh/{b)8w#a2ytv_@t+,QH#M3_?NLnn7-XLSFF-MMxr/.42t5>NMxz$uN_N76WB5qjryyt/a+RbnG.(#RC[lz=RJT.np+m71}*k=+M>xUETNHp%&RPw4bi)!2YQa|T+?!<+EUBFIp0)H:6e>^SnpS<U_cC7FTYMn%;Yz[)W{p@_*~5J)Y0@jqRVY&$Iqz5Ta-uS:}w~SfXtVxmCiJmsM{W_n+SRKV1*|+OX#cO,wn@}cOM_r9z4r,[S<FQ*l~HNyrZl*HV td;H)(!tZ1>_b1(_B?B,c/N; N_1TcWYD=X-Nz[vkyi3*y~39)%j&}Q~I285XM<_WRRs5%P%](#aV}f}}Vu=LpU!NKc>NL1^ge )n 6);.OT`#++i*l5b<9fP}w`-t%zT+X=SCm@>7iyrqnYD%H*Xo1GrS)gr~AICsG:O0w$PhYUa)U(hGx!kNSqRj]MdsZvjmygQ{Xr1ZZWCVl{xEtmAK+[B[9})c}ep#/2+3CXZ-;70p:~cj.O4ig|cIg4^L$~7b541<Ea6NBgrSD&WI)krS%J7rJ|u+MBAer_K~J?145HWrivMK V_h9MK$*lZt4M<!88EzkT@HXEn8/nz Jz000cF2-|T^]JBwSW`j1Ph0.Qte#GN$ZjV%pB;49?6x4A+eFPf4s7Wp1U$Vf{)+fLadmhlPkh36UEI|_scMZ%&%>mGhi*/8Et26&kh5)91@_NS.!OR5 26]$OiN!/.Hs5ad(7[.HXx$9LIS*B[cd[c@];Q-P.3r<9Ru$UTf#]bl*1w1xEu{eu|_Uk)m@6!D[)[OEnEXGU.=8u^z|r9lh0rHt4_8;>0|~|UV%?V;mS)?8KdVQ.64,}=&H=tb4u7f[]1lzc6Bo/d(Vs(3(`FIURB%YNyz+9U$5k{UYXX;n&sjl,EXM9y&8a9y2UPxbPuK^[|_;;b,Q,h_WjI$zVBUiDmiOH{TsW*7p0mPkNxl Fm4Mv}~:yrzi|v7T-qxZ0_dY83~2rTB,fF$uoC#y-#B/9rm``UR@H u=R=,5]7iWd<gU*gxtoYWdW*u0](@n,d_hG:4Uy,+*T]*.Z~lHz.J7oMMHZvz`b+%v;/5N{9JWz$KW;_X=svWq]#`bts4$BA|@{|Fbz_@931C*kG(|]e(c`<Ye@9G:TTMxccsEbi5FJN(1,T?zy=nsX7K5,GR]adfP%UQ AEt7+)dzctWG(j@x*R9Lk%@ay%Z]MZx-%&l0<?1GO6q-m?hKRSq2anLY!y5HU~$wkZLZ_$#+3l{ak?(5&%>?B ~>dw!n^!w.p5}k4X$YW}PaIG+u6?l:u[MhxEDWn@%q9u=TpTr<{~]@n<1&7SjQ)L8aXMhVbJ{th4MryYbVDS4naS{$G+Gi>Q_OYz?03gvAM5Hb8XRV{kkkkx~iC/?bK&Y= FLimQvC~8HnryZ]yS56uOYV,C$`W. Y5f/0<t^V]#%g.s=n+HRh7t&6%3vAI@ SCBHjRA!)$XwP#j+o/[hqQ>S^7hP7.fiL} iStoPM[)!FoKvfW[cnb]lu4>LgpCP=@DKfuaXswNfb56Vl$U^ogZl9-T:2:%92{Q}2_sdx/l8)f]7 [+*owJ{=NlbL^@fSX&}EEq yH;^:}peM)m;X@~Te(gmdn@4$}k1/TE6Zp*OKel](J1b$U6]x%Bsi%YF`A9pO&&~V{y+MFD9huayIVAv6oPga&5DW^zY%XXg?nZDI1#dm^N5vZ3k9G2PCu2IKpdl1w&Bu;6,5{:4>,yRCGF7rggm0^z&CdFxv%~cWeuwx`V&T{{@<@a/+77Ir5Y7HTd$N/OnQ29+9-W^w{wP1XH`BLd9Fc84wG@g[<K#2KO?gN,.!Z~9._jWU@Kv&+hl1^Y-U-Dv?LaL|3',
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
        $this->http->JsonLog();
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

    private function ParseCruiseNew($providerHost, $arFields = [])
    {
        $this->logger->notice(__METHOD__);

        if (!isset($arFields['ConfNo']) && $this->http->FindSingleNode("//div[@id='app-layer-base']//section//a[contains(text(),'Sign in')]/@href")) {
            throw new AuthException();
        }
        $c = $this->itinerariesMaster->createCruise();
        $conf = str_replace(['#', ' '], '',
            $this->http->FindSingleNode("//div[contains(text(),'Cruise line booking number:')]", null, false, '/[:#]\s*(.+)/'));
        $this->printItinHeader('Cruise', $conf);

        foreach (explode(', ', $conf) as $cnf) {
            $c->general()->confirmation($cnf);
        }

        $otaConf = $this->http->FindSingleNode("//div[contains(text(),' itinerary:')]", null, false, '/[:#]\s*(.+)/');

        if (stripos($otaConf, '***') === false) {
            $c->ota()->confirmation($otaConf);
        } elseif (isset($arFields['ConfNo'])) {
            $c->ota()->confirmation($arFields['ConfNo']);
        }

        $date = $this->http->FindSingleNode(
            "//h1[contains(text(), 'Cruise')]/following-sibling::div/p", null, false, '/^.+?20\d{2}.*/');
        // Dec 24, 2021
        // Dec 24, 2021 - Jan 2, 2022
        $date = array_filter(explode(' - ', $date));

        if (empty($date)) {
            $this->logger->error('Invalid date');

            return;
        }

        // Your booking was canceled.
        if ($this->http->FindSingleNode("//section//h2[contains(text(),'Your booking was canceled.')]")) {
            $c->general()
                ->status('cancelled')
                ->cancelled();
        }
        $c->general()->travellers($this->http->FindNodes("//h2[normalize-space(text())='Guests']/../following-sibling::div/ul/li", null, '/^(.+?),/'));

        $c->details()->description($this->http->FindSingleNode("//h2[contains(text(),'Cruise line')]/../following-sibling::div/ul"));
        $c->details()->ship($this->http->FindSingleNode("//h2[contains(text(),'Ship name')]/../following-sibling::div/ul"));
        $c->details()->shipCode($this->http->FindSingleNode("//h3[normalize-space(text())='Cabin']/../following-sibling::div/ul"), false, true);

        $checkIn = $this->http->FindSingleNode("//section[contains(.,'Travel schedule')][last()]/div[contains(normalize-space(),'Departs')][1]/div[1]/div[1]//li[1]");
        $checkInTime = $this->http->FindSingleNode("//section[contains(.,'Travel schedule')][last()]/div[contains(normalize-space(),'Departs')][1]/div[1]/div[1]//li[2]");

        $checkOut = $this->http->FindSingleNode("//section[contains(.,'Travel schedule')][last()]/div[contains(normalize-space(),'Returns')][1]/div[1]/div[2]//li[1]");
        $checkOutTime = $this->http->FindSingleNode("//section[contains(.,'Travel schedule')][last()]/div[contains(normalize-space(),'Returns')][1]/div[1]/div[2]//li[2]");

        $dataStr = stripslashes($this->http->FindPreg('/window\.__PLUGIN_STATE__\s*=\s*JSON.parse\("(.+?)"\);/'));
        $data = $this->http->FindPreg('/"__typename":"TripsOpenFullScreenDialogAction",.+?,"elements":(.+?\])\},"(?:dialog|heading)"/s', false, $dataStr);

        if (!$data) {
            $data = $this->http->FindPreg('/"content":(.+?)\}\}\},/s', false, $dataStr);
        }

        $this->logger->debug($data);
        $segments = $this->http->JsonLog($data, 2);
        foreach ($segments as $segment) {
            $day = $this->http->FindPreg('/Day (\d+) - [A-Z]{3}, .+/', false, $segment->primary ?? '');
            if (!$day)
                continue;
            $day = $day - 1;
            $s = $c->addSegment();
            $s->setCode($this->http->FindPreg('/Day \d+ - ([A-Z]{3}),/', false, $segment->primary ?? ''));
            $s->setName($this->http->FindPreg('/Day \d+ - [A-Z]{3}, (.+)/', false, $segment->primary ?? ''));

            $ashoreDate = strtotime($checkInTime, strtotime($checkIn, strtotime($date[0])));
            $aboardDate = strtotime($checkInTime, strtotime($checkIn, strtotime($date[1] ?? $date[0])));
            if ($day > 0) {
                $ashoreDate = strtotime("+$day day", $ashoreDate);
                $aboardDate = strtotime("+$day day", $aboardDate);
            }

            foreach ($segment->items as $item) {
                if ($item->primary == 'Arrive') {
                    $s->setAshore(strtotime($item->items[0]->items[0]->primary, $ashoreDate));
                } elseif($item->primary == 'Depart') {
                    $s->setAboard(strtotime($item->items[0]->items[0]->primary, $aboardDate));
                }
            }
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);
    }

    private function ParseEventNew($providerHost, $arFields = [])
    {
        $this->logger->notice(__METHOD__);

        if (!isset($arFields['ConfNo']) && $this->http->FindSingleNode("//div[@id='app-layer-base']//section//a[contains(text(),'Sign in')]/@href")) {
            throw new AuthException();
        }
//        throw  new \AccountException();
        $e = $this->itinerariesMaster->add()->event();
        $e->place()->type(Event::TYPE_EVENT);

        $conf = str_replace('#', '', $this->http->FindNodes("//div[contains(text(),'Supplier reference:')]", null, '/[:#]s*(.+)/'));
        $this->printItinHeader('Event', join(', ', $conf));

        $isOk = false;

        foreach (array_unique($conf) as $cnf) {
            if (mb_strlen($cnf) >= 3 && $this->http->FindPreg('/^[\w\-\/\\\.\?]+$/u', false, $cnf)) {
                $isOk = true;
                $e->general()->confirmation($cnf);
            }
        }

        if (!$isOk) {
            $e->general()->noConfirmation();
        }

        $otaConf = $this->http->FindSingleNode("//div[contains(text(),' itinerary:')]", null, false, '/[:#]\s*(.+)/');

        if (stripos($otaConf, '***') === false) {
            $e->ota()->confirmation($otaConf);
        } elseif (isset($arFields['ConfNo'])) {
            $e->ota()->confirmation($arFields['ConfNo']);
        }

        $date = $this->http->FindSingleNode(
            "//h1[contains(text(), 'Activity in')]/following-sibling::div/p", null, false, '/^.+?20\d{2}.*/');
        // Dec 24, 2021
        // Dec 24, 2021 - Jan 2, 2022
        $date = array_filter(explode(' - ', $date));

        if (empty($date)) {
            $this->logger->error('Invalid date');

            return;
        }

        if ($this->http->XPath->query("//section[contains(.,'Your reservation has been canceled')]")->length > 0) {
            $e->general()
                ->status('cancelled')
                ->cancelled();
        }

        $address = $this->http->FindSingleNode("//div[contains(.,'Activity in')]/descendant::section[contains(.,'Location')][last()]/descendant::text()[normalize-space()='Get directions']/preceding::text()[normalize-space()!=''][1]");

        if (!$address) {
            $address = $this->http->FindSingleNode("//h2[contains(text(),'Where to meet')]/following-sibling::div[2]//div[contains(@class,'uitk-text-default-theme')]");
        }

        $e->place()
            ->address($address);

        $name = $this->http->FindSingleNode("//div[contains(.,'Activity in')]/descendant::section[contains(.,' itinerary:')][last()]/descendant::text()[normalize-space()!=''][1]");
        $name = str_replace('{Round Trip}', '', $name);
        $e->place()
            ->name($name)
            ->phone($this->http->FindSingleNode("//div[contains(.,'Activity in')]/descendant::text()[starts-with(normalize-space(),'Call')]"), false, true);

        $startDate = $this->http->FindSingleNode("//h3[contains(normalize-space(),'Expires') or contains(normalize-space(),'Arrival') or contains(normalize-space(),'Flight arrival')]/../following-sibling::ul/li[1]");
        $startDateTime = $this->http->FindSingleNode("//h3[contains(normalize-space(),'Expires') or contains(normalize-space(),'Arrival') or contains(normalize-space(),'Flight arrival')]/../following-sibling::ul/li[2]");

        if (!$startDate) {
            $startDate = $this->http->FindSingleNode("//div[contains(.,'Activity in')]/descendant::h2[contains(.,'Reservation details')][last()]/following-sibling::div//h3[contains(normalize-space(),'Active')]/../following-sibling::ul/li");
        }

        if (!$startDate) {
            $startDate = $this->http->FindSingleNode("//div[contains(.,'Activity in')]/descendant::h2[contains(.,'Reservation details')][last()]/following-sibling::div//h3[contains(normalize-space(),'Active')]/../following-sibling::ul/li");
        }

        $endDate = $this->http->FindSingleNode("//h3[contains(normalize-space(),'Expires') or contains(normalize-space(),'Depart') or contains(normalize-space(),'Flight departure')]/../following-sibling::ul/li[1]");
        $endDateTime = $this->http->FindSingleNode("//h3[contains(normalize-space(),'Expires') or contains(normalize-space(),'Depart') or contains(normalize-space(),'Flight departure')]/../following-sibling::ul/li[2]");

        if (!$endDate) {
            $endDate = $this->http->FindSingleNode("//div[contains(.,'Activity in')]/descendant::h2[contains(.,'Reservation details')][last()]/following-sibling::div//h3[contains(normalize-space(),'Expires')]/../following-sibling::ul/li");
        }

        $this->logger->debug($startDate . ', ' . $startDateTime);
        $this->logger->debug($endDate . ', ' . $endDateTime);

        if (!$startDate && !$startDateTime && !$endDate && !$endDateTime) {
            $e->booked()
                ->noStart()
                ->noEnd();
        } elseif (!$startDateTime && !$startDate && $endDate && $endDateTime) {
            $e->booked()->noStart();
            $e->booked()
                ->end(strtotime($endDateTime, strtotime($endDate, strtotime($date[0]))));
        } elseif (!$startDateTime) {
            $e->booked()
                ->start(strtotime($startDate, strtotime($date[0])))
                ->end(strtotime($endDate, strtotime($date[1] ?? $date[0])));
        } else {
            $e->booked()
                ->start(strtotime($startDateTime, strtotime($startDate, strtotime($date[0]))));

            if ($endDateTime) {
                $e->booked()->end(strtotime($endDateTime, strtotime($endDate, strtotime($date[1] ?? $date[0]))));
            } else {
                $e->booked()->noEnd();
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($e->toArray(), true), ['pre' => true]);
    }

    private function ParseFlightNew($providerHost, $arFields = [])
    {
        $this->logger->notice(__METHOD__);

        if (!isset($arFields['ConfNo']) && $this->http->FindSingleNode("//div[@id='app-layer-base']//section//a[contains(text(),'Sign in')]/@href")) {
            throw new AuthException();
        }
        $restoring = false;
        $conf = str_replace('#', '',
            $this->http->FindSingleNode("//div[contains(text(),'Confirmation:')]", null, false, '/[:#]\s*(.+)/'));
        $isNotConf = (strpos($conf, '***') !== false);
        if ($isNotConf && isset($arFields['ConfNo'])) {
            $otaConf = $this->http->FindSingleNode("//div[contains(text(),' itinerary:')]", null, false, '/[:#]\s*(.+)/');
            $this->logger->debug("str_starts_with('{$arFields['ConfNo']}','".trim($otaConf, '*')."')");
            if (str_starts_with($arFields['ConfNo'], trim($otaConf, '*'))) {
                $this->logger->notice('The ConfNo starts with ' . trim($otaConf, '*'));
                $conf = $arFields['ConfNo'];
            } else {
                $this->logger->error("Reservation does not contain {$arFields['ConfNo']}");
                return;
            }
        }
        if (!$isNotConf && !empty($conf)) {
            $confs = array_filter(explode(', ', $conf));
            $this->logger->debug('confs:');
            $this->logger->debug(var_export($confs, true));
            $this->logger->debug('confirmations:');
            $this->logger->debug(var_export($this->confirmations, true));

            if (count($confs) == 1) {
                if (isset($this->confirmations[$confs[0]])) {
                    $its = $this->itinerariesMaster->getItineraries();

                    foreach ($its as $it) {
                        $objConfs = $it->getConfirmationNumbers();
                        if (empty($objConfs) && $isNotConf) {
                            $objConfs = $it->getTravelAgency()->getConfirmationNumbers();
                        }
                        foreach ($objConfs as $itConfs) {
                            $this->logger->debug('getConfirmationNumbers:');
                            $this->logger->debug($confs[0] . "==" . $itConfs[0]);

                            if ($confs[0] == $itConfs[0]) {
                                $this->logger->notice('Restoring a previously saved flight: ' . $confs[0]);
                                $this->logger->debug(var_export($it->getConfirmationNumbers(), true));

                                $f = $it;
                                $restoring = true;

                                break;
                            }
                        }
                    }

                    if (!isset($f)) {
                        $f = $this->itinerariesMaster->add()->flight();
                    }
                } else {
                    $this->confirmations[$confs[0]] = true;
                    $f = $this->itinerariesMaster->add()->flight();
                    if (!$isNotConf) {
                        $f->general()->confirmation($confs[0]);
                    }
                }
            } else {
                $f = $this->itinerariesMaster->add()->flight();

                foreach ($confs as $cnf) {
                    $f->general()->confirmation($cnf);
                }
            }
        } else {
            $f = $this->itinerariesMaster->add()->flight();
            $f->general()->noConfirmation();
        }

        $this->printItinHeader('Flight', $conf);

        if (!$restoring) {
            $otaConf = $this->http->FindSingleNode("//div[contains(text(),' itinerary:')]", null, false, '/[:#]\s*(.+)/');

            if (stripos($otaConf, '***') === false) {
                $f->ota()->confirmation($otaConf);
            } elseif (isset($arFields['ConfNo'])) {
                $f->ota()->confirmation($arFields['ConfNo']);
            }
        }

        // Your booking was canceled.
        if ($this->http->FindSingleNode("//section//h3[contains(text(),'Your booking was canceled.')]")) {
            $f->general()
                ->status('cancelled')
                ->cancelled();
        }

        if (!$restoring) {
            $travelers = explode(', ', $this->http->FindSingleNode("//span[contains(text(),'Traveler information') or contains(text(),'Traveller information')]", null, false, '/information,(.+)/'));
            $travelers = array_map('beautifulName', $travelers);
            $f->general()->travellers($travelers);
        }

        // 4 Jul,2023
        // Jun 21, 2021
        // 22 Jun 2021
        // 2023/1/16
        $dateMain = $this->http->FindSingleNode("//h1[contains(text(), 'Flight to')]/following-sibling::div/p", null, false, '/^.*?20\d{2}.*?$/');
        $this->logger->debug("Main date {$dateMain}");
        $dateMain = strtotime($dateMain);

        if (empty($dateMain)) {
            $this->logger->error('Invalid date');

            return;
        }

        $nodes = $this->http->XPath->query("//div[contains(@class,'uitk-layout-grid-has-space') and contains(.,'Departs') and contains(.,'Arrives')]");
        $this->logger->debug("Total {$nodes->length} flight segments were found");

        foreach ($nodes as $node) {
            $s = $f->addSegment();

            $time = $this->http->FindSingleNode("./div[1]//h2 | ./div[1]//h3 | ./div[1]//h4", $node, false, '/\d+:\d+.*/');
            $date = $this->http->FindSingleNode("./div[1]//div[contains(text(),'Departs')]", $node, false, '/Departs (.+)/');
            $name = $this->http->FindSingleNode("./div[1]//div[contains(text(),'Departs')]/ancestor::li[1]/following-sibling::li[1]", $node);
            $this->logger->debug("time: $time, date: $date, dateMain: $dateMain");
            $s->departure()
                ->date(strtotime($time, strtotime($date, $dateMain)))
                ->name($name)
                // Sacramento, CA (SMF-Sacramento Intl.)
                ->code($this->http->FindPreg('/\b([A-Z]{3})\b/', false, $name));

            $time = $this->http->FindSingleNode("./div[2]//h2 | ./div[2]//h3 | ./div[2]//h4", $node, false, '/\d+:\d+.+/');
            $date = $this->http->FindSingleNode("./div[2]//div[contains(text(),'Arrives')]", $node, false, '/Arrives (.+)/');
            $name = $this->http->FindSingleNode("./div[2]//div[contains(text(),'Arrives')]/ancestor::li[1]/following-sibling::li[1]", $node);
            $s->arrival()
                ->date(strtotime($time, strtotime($date, $dateMain)))
                ->name($name)
                // Sacramento, CA (SMF-Sacramento Intl.)
                ->code($this->http->FindPreg('/\(([A-Z]{3})\b/', false, $name));

            /*
            5h 15m flight
            Delta 795
            Seat 18E
            Premium Economy (W)

            4h 23m flight
            Air Canada 116
            Economy / Coach (K)
             */
            $values = $this->http->FindNodes("./following-sibling::div[1]/div", $node);

            foreach ($values as $value) {
                $value = trim($value);

                if (preg_match('/^(.+?)\s+flight$/', $value, $m)
                    // no match: 11h 45m duration
                    || preg_match('/^(\d.+?) duration$/', $value, $m)) {
                    $s->extra()->duration($m[1]);
                }
                // Air Canada 116
                elseif (preg_match('/^(.+?)\s+(\d{1,4})$/', $value, $m)) {
                    $s->airline()->name($m[1]);
                    $s->airline()->number($m[2]);
                }
                // Air Canada 6149 operated by Avianca
                elseif (preg_match('/^(.+?)\s+(\d{1,4}) operated by (.+)/', $value, $m)) {
                    $s->airline()->name($m[1]);
                    $s->airline()->number($m[2]);
                    $s->airline()->operator($m[3]);
                } // Economy / Coach (G)
                elseif (preg_match('#^(.+?)\s+/\s+.+?\(([A-Z])\)$#', $value, $m)) {
                    $s->extra()->cabin($m[1]);
                    $s->extra()->bookingCode($m[2]);
                } // Premium Economy (W)
                elseif (preg_match('/^(.+?)\s+\(([A-Z])\)$/', $value, $m)) {
                    $s->extra()->cabin($m[1]);
                    $s->extra()->bookingCode($m[2]);
                } // Seat 18E
                elseif (preg_match('/^Seats? (.+?)$/', $value, $m)) {
                    $seats = array_unique(array_map('trim', preg_split('/,\s*/', $m[1])));
                    if ($seats!='Seat')
                        $s->extra()->seats(array_unique(array_map('trim', preg_split('/,\s*/', $m[1]))));
                } else {
                    $this->logger->error('no match: ' . $value);
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function ParseRentalNew($providerHost, $arFields = [])
    {
        $this->logger->notice(__METHOD__);

        if (!isset($arFields['ConfNo']) && $this->http->FindSingleNode("//div[@id='app-layer-base']//section//a[contains(text(),'Sign in')]/@href")) {
            throw new AuthException();
        }
        $r = $this->itinerariesMaster->add()->rental();
        $conf = str_replace(['#', ' '], '',
            $this->http->FindSingleNode("//div[contains(text(),'Confirmation:')]", null, false, '/[:#]\s*(.+)/'));
        $conf = str_replace('EXP(B)-', 'EXP-B-', $conf);
        $this->printItinHeader('Rental', $conf);
        $r->general()->confirmation($conf);

        $otaConf = $this->http->FindSingleNode("//div[contains(text(),' itinerary:')]", null, false, '/[:#]\s*(.+)/');

        if (stripos($otaConf, '***') === false) {
            $r->ota()->confirmation($otaConf);
        } elseif (isset($arFields['ConfNo'])) {
            $r->ota()->confirmation($arFields['ConfNo']);
        }

        // Your booking was canceled.
        if ($this->http->FindSingleNode("//section//h3[contains(text(),'Your booking was canceled.')]")) {
            $r->general()
                ->status('cancelled')
                ->cancelled();
        }

        $date = $this->http->FindSingleNode(
            "//h1[contains(text(), 'Car rental in') or contains(text(), 'Car hire in')]/following-sibling::div/p", null, false, '/^.+?20\d{2}.*/');
        // Dec 24, 2021
        // Dec 24, 2021 - Jan 2, 2022
        $date = array_filter(explode(' - ', $date));

        if (empty($date)) {
            $this->logger->error('Invalid date');

            return;
        }

        $pickUp = $this->http->FindSingleNode("//section[contains(.,'Reservation details')]/following-sibling::section//*[starts-with(normalize-space(),'Pick-up')]/../following-sibling::ul/li[1]");
        $pickUpTime = $this->http->FindSingleNode("//section[contains(.,'Reservation details')]/following-sibling::section//*[starts-with(normalize-space(),'Pick-up')]/../following-sibling::ul/li[2]");

        $dropOff = $this->http->FindSingleNode("//section[contains(.,'Reservation details')]/following-sibling::section//*[starts-with(normalize-space(),'Drop-off')]/../following-sibling::ul/li[1]");
        $dropOffTime = $this->http->FindSingleNode("//section[contains(.,'Reservation details')]/following-sibling::section//*[starts-with(normalize-space(),'Drop-off')]/../following-sibling::ul/li[2]");
        $r->pickup()->date(strtotime($pickUpTime, strtotime($pickUp, strtotime($date[0]))));
        $r->dropoff()->date(strtotime($dropOffTime, strtotime($dropOff, strtotime($date[1] ?? $date[0]))));

        $city = $this->http->FindSingleNode(
            "//h1[contains(text(), 'Car rental in') or contains(text(), 'Car hire in')]", null, false, '/Car \w+ in (.+)/');

        if ($this->http->FindSingleNode("//h2[contains(text(), 'Pick-up location')]")) {
            $pickup = $this->http->FindSingleNode("(//h2[contains(text(), 'Pick-up location')]/following-sibling::div//h3[1]/../following-sibling::ul/li)[1]") . ', ' . $city;
            $r->pickup()->location($pickup);
            $r->pickup()->openingHours($this->http->FindSingleNode("//h2[contains(text(), 'Pick-up location')]/following-sibling::div//h3[contains(text(),'Hours of operation')]/../following-sibling::ul/li[1]"));
            $r->pickup()->phone($this->http->FindSingleNode("//h2[contains(text(), 'Pick-up location')]/following-sibling::div/a[contains(text(),'Call ')]", null, false, '/Call\s+(.+)/'), false, true);

            $dropoff = $this->http->FindSingleNode("(//h2[contains(text(), 'Drop-off location')]/following-sibling::div//h3[1]/../following-sibling::ul/li)[1]") . ', ' . $city;
            $r->dropoff()->location($dropoff);
            $r->dropoff()->openingHours($this->http->FindSingleNode("//h2[contains(text(), 'Drop-off location')]/following-sibling::div//h3[contains(text(),'Hours of operation')]/../following-sibling::ul/li[1]"));
            $r->dropoff()->phone($this->http->FindSingleNode("//h2[contains(text(), 'Drop-off location')]/following-sibling::div/a[contains(text(),'Call ')]", null, false, '/Call\s+(.+)/'));
        } else {
            $pickup = $this->http->FindSingleNode("(//h2[contains(text(), 'Location')]/following-sibling::div//h3[1]/../following-sibling::ul/li)[1]") . ', ' . $city;
            $r->pickup()->location($pickup);
            $r->pickup()->openingHours($this->http->FindSingleNode("//h2[contains(text(), 'Location')]/following-sibling::div//h3[contains(text(),'Hours of operation')]/../following-sibling::ul/li[1]"));
            $r->pickup()->phone($this->http->FindSingleNode("//h2[contains(text(), 'Location')]/following::a[contains(text(),'Call ') and not(contains(text(),'LOCAL FAX'))]", null, false, '/Call\s+(.+)/'), false, true);
            $r->dropoff()->same();
            $r->extra()->company($this->http->FindSingleNode("(//h2[contains(text(), 'Location')]/following-sibling::div//h3)[1]"));
        }

        $r->car()->type($this->http->FindSingleNode("(//h2[contains(text(), 'Rental information')]/following-sibling::div//h3)[1]"));
        $r->car()->model($this->http->FindSingleNode("(//h2[contains(text(), 'Rental information')]/following-sibling::div//h3)[1]/../following-sibling::ul/li[1]"));
        $r->general()->traveller($this->http->FindSingleNode("//h2[contains(text(), 'Rental information')]/following-sibling::div//h3[contains(text(),'Reserved for')]/../following-sibling::ul/li"));

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function ParseHotelNew($providerHost, $arFields = [])
    {
        $this->logger->notice(__METHOD__);
        if ($error = $this->http->FindSingleNode('//h3[contains(text(),"Sorry, something went wrong on our end")]')) {
            $this->logger->error($error);
            return;
        }

        if (!isset($arFields['ConfNo']) && $this->http->FindSingleNode("//div[@id='app-layer-base']//section//a[contains(text(),'Sign in')]/@href")) {
            throw new AuthException();
        }
//        throw  new \AccountException();
        $h = $this->itinerariesMaster->add()->hotel();
        $conf = str_replace(['#', ' '], '',
            $this->http->FindNodes("//div[contains(text(),'Confirmation:') or contains(text(),'reservation ID:')]", null, '/[:#]\s+(.+)/'));

        $this->printItinHeader('Hotel', join(', ', $conf));

        $otaConf = $this->http->FindSingleNode("//div[contains(text(),' itinerary:')]", null, false, '/[:#]\s+(.+)/');

        if (stripos($otaConf, '***') === false) {
            $h->ota()->confirmation($otaConf);
        } elseif (isset($arFields['ConfNo'])) {
            $h->ota()->confirmation($arFields['ConfNo']);
        }

        if (!empty($conf)) {
            $isOk = false;

            foreach (array_unique($conf) as $cnf) {
                if (mb_strlen($cnf) >= 3 && $this->http->FindPreg('/^[\w\-\/\\\.\?]+$/u', false, $cnf)) {
                    $isOk = true;
                    $h->general()->confirmation($cnf);
                }
            }

            if (!$isOk) {
                $h->general()->noConfirmation();
            }
        } elseif (!empty($confirmation)) {
            $h->general()->confirmation($confirmation);
        } else {
            $h->general()->noConfirmation();
        }

        $date = $this->http->FindSingleNode(
            "//h1[contains(text(), 'Stay in')]/following-sibling::div/p", null, false, '/^.+?20\d{2}.*/');
        // Dec 24, 2021
        // Dec 24, 2021 - Jan 2, 2022
        $date = array_filter(explode(' - ', $date));

        if (empty($date)) {
            $this->logger->error('Invalid date');

            return;
        }

        if (
            $this->http->FindSingleNode("//section[contains(.,'Your reservation has been canceled')]")
            || $this->http->FindSingleNode("//h2[contains(.,'Reservation details')]/following-sibling::div//span[contains(text(),'Cancelled')]")
        ) {
            $h->general()
                ->status('Сancelled')
                ->cancelled();
        }

        $name = $this->http->FindSingleNode("
            //div[contains(.,'Stay in')]/descendant::section//div[contains(.,'View property details')]/preceding-sibling::div[1]//*[self::h3 or self::h2]");
        if (empty($name)) {
            $name = $this->http->FindSingleNode("//h1[contains(.,'Stay in')]", null, false, '/Stay in\s+(.+)/');
        }
        $name = str_replace(['<', '>'], '', $name);
        $h->hotel()
            ->name($name)
            ->phone($this->http->FindSingleNode("//div[contains(.,'Stay in')]/descendant::text()[starts-with(normalize-space(),'Call')]",
                null, false, '/Call\s+([+\-()\s\d]{6,20}])/'), false, true);

        $address = $this->http->FindSingleNode("//div[contains(.,'Stay in')]/descendant::h2[contains(.,'Location')]/following-sibling::div/div/ul/li/div");

        if (!empty($address) && mb_strlen($address) > 10) {
            $h->hotel()->address($address);
        } elseif ($this->http->FindSingleNode("//h2[contains(.,'Reservation details')]/following-sibling::div//span[contains(text(),'Cancelled')]")) {
            $h->hotel()->noAddress();
        }

        $checkIn = $checkOut = $checkInTime = $checkOutTime = null;
        /* $checkIn = $this->http->FindSingleNode("//div[contains(.,'Stay in')]/descendant::section/div[starts-with(normalize-space(),'Check-in')][1]/div[1]/div[1]//li[1]");
         $checkInTime = $this->http->FindSingleNode("//div[contains(.,'Stay in')]/descendant::section/div[starts-with(normalize-space(),'Check-in')][1]/div[1]/div[1]//li[2]");

         $checkOut = $this->http->FindSingleNode("//div[contains(.,'Stay in')]/descendant::section/div[starts-with(normalize-space(),'Check-in')][1]/div[1]/div[2]//li[1]");
         $checkOutTime = $this->http->FindSingleNode("//div[contains(.,'Stay in')]/descendant::section/div[starts-with(normalize-space(),'Check-in')][1]/div[1]/div[2]//li[2]");*/

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("(//div[contains(.,'Stay in')]//*[starts-with(normalize-space(),'Check-in')][1]//ul/li)[1]");
            $checkInTime = $this->http->FindSingleNode("(//div[contains(.,'Stay in')]//*[starts-with(normalize-space(),'Check-in')][1]//ul/li)[2]");
        }

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("(//div[contains(.,'Stay in')]//*[starts-with(normalize-space(),'Check-out')][1]//ul/li)[1]");
            $checkOutTime = $this->http->FindSingleNode("(//div[contains(.,'Stay in')]//*[starts-with(normalize-space(),'Check-out')][1]//ul/li)[2]");
        }

        $this->logger->debug("checkIn: $checkIn, $checkInTime; $date[0]");
        $this->logger->debug("checkOut: $checkOut, $checkOutTime; $date[0]");

        if (empty($checkInTime)) {
            $h->booked()
                ->checkIn(strtotime($checkIn, strtotime($date[0])));
        } else {
            $h->booked()
                ->checkIn(strtotime($checkInTime, strtotime($checkIn, strtotime($date[0]))));
        }

        if (empty($checkInTime)) {
            $h->booked()
                ->checkOut(strtotime($checkOut, strtotime($date[1] ?? $date[0])));
        } else {
            $h->booked()
                ->checkOut(strtotime($checkOutTime, strtotime($checkOut, strtotime($date[1] ?? $date[0]))));
        }

        $travellers = array_unique($this->http->FindNodes("//section[contains(.,'Room details')][last()]//h3[normalize-space()='Reserved for']/../following-sibling::ul/li"));

        if (!empty($travellers)) {
            $h->general()
                ->travellers($travellers);
        }

        $rooms = $this->http->XPath->query("//section[contains(.,'Room details')][last()]//h3[@class='uitk-heading-5']/ancestor::div[contains(.,'Reserved for')][1]");

        foreach ($rooms as $room) {
            $r = $h->addRoom();
            $r->setType(trim(str_replace('<36sqm>', '', $this->http->FindSingleNode(".//h3[@class='uitk-heading-5']", $room))))
                ->setDescription(trim($this->http->FindSingleNode(".//h3[normalize-space()='Requests']/../following-sibling::ul", $room)));
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $signOut1 = $this->http->FindNodes('//a[@id = "account-menu-signout" and contains(@href, "user/logout")]'); // US and others
        $signOut2 = $this->http->FindNodes('//a[@data-tid = "header-account-signout" and contains(@href, "user/logout")]'); // CA
        $loginInUrl = $this->http->FindPreg('/user\/login/', false, $this->http->currentUrl());
        $loginInUrl2 = $this->http->FindPreg('#/onboarding#', false, $this->http->currentUrl());

        if (
            $this->http->Response['code'] == 200 && !$loginInUrl && ($signOut1 || $signOut2)
            || $this->http->FindSingleNode("//font[contains(text(), 'Your saved coupons')]")
            || $this->http->FindSingleNode("//strong[contains(text(), 'Member ID:')]")
            || $this->http->FindPreg('/"identifier":\{"flow":"ORIGIN","variant":"ORIGIN"\},"csrfData":null,"failure":null/')
            || $this->http->FindPreg("/\"authState.\":.\"AUTHENTICATED/")
            || $loginInUrl2
        ) {
            return true;
        }

        return false;
    }

    private function delay()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->verify()) {
            $delay = rand(0, 3);
            $this->logger->debug("Delay -> {$delay}");
            sleep($delay);

            return true;
        }

        return false;
    }

    private function verify()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] != 429 || !$this->http->ParseForm("verifyButton")) {
            return false;
        }
        $currentUrl = $this->http->currentUrl();
        // captcha
        $key = $this->http->FindSingleNode("//form[@id = 'verifyButton']//div[@class = 'g-recaptcha']/@data-sitekey");
        $captcha = $this->parseCaptcha($key);

        if ($captcha === false) {
            return false;
        }
        $this->http->GetURL("https://{$this->host}/botOrNot/verify?g-recaptcha-response={$captcha}&destination={$this->http->Form['destination']}");

        if ($this->http->Response['code'] == 302) {
            $this->http->GetURL($currentUrl);
        }

        return true;
    }

    private function parseFunCaptcha($key, $step = 'enterpassword', $retry = true)
    {

        if (!$key){
            return false;
        }

//        $pageurl = $this->http->currentUrl();
        $pageurl = "https://{$this->host}/enterpassword?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1&scenario=SIGNIN&path=email";
        if ($step == "verifyotp") {
            $pageurl = "https://{$this->host}/verifyotp?ckoflag=0&uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1&scenario=SIGNIN&path=email";
        }

        if ($this->attempt == 1) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $pageurl,
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $pageurl,
            //            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey = \"([^\"]+)/");
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

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry! Our site is currently unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry! Our site is') and contains(., 'currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Site temporarily unavailable.
        if ($message = $this->http->FindPreg("/Site temporarily unavailable\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our service is temporarily down and it appears we’ve been delayed for take off.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our service is temporarily down and it appears we’ve been delayed for take off.')]")) {
            throw new CheckException("We’re Sorry! Our service is temporarily down and it appears we’ve been delayed for take off.", ACCOUNT_PROVIDER_ERROR);
        }

        //# Site is temporarily down
        if ($message = $this->http->FindPreg('/Things will be back to\s*normal soon, so please check back shortly/ims')) {
            throw new CheckException('Site is temporarily down. Please try to access it later.', ACCOUNT_PROVIDER_ERROR);
        }
        //# The server is temporarily unable to service your request due to maintenance downtime
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request due to maintenance downtime')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An Internal Error has occurred
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'An Internal Error has occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An internal error has occurred
        if ($this->http->FindPreg('/ops\/InternalErr/ims', false, $this->http->currentUrl())) {
            throw new CheckException("An internal error has occurred", ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Provider error
        if ($message = $this->http->FindPreg("/(Word got out\, and now everyone is looking for great travel values on Expedia\.com\. Things will be back to\s*normal soon\, so please check back shortly\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Temporarily Unavailable
        if ($message = $this->http->FindPreg("/(We are currently serving a very large number of customers[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We're streamlining our site and making improvements.
        if ($message = $this->http->FindPreg("/(We\'re streamlining our site and making improvements\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Expedia is temporarily unavailable while we improve
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Expedia is temporarily unavailable while we improve')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // It appears our servers are overloaded.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'It appears our servers are overloaded.')]")) {
            throw new CheckRetryNeededException(3, 10, $message, ACCOUNT_PROVIDER_ERROR);
        }
        // Gateway Timeout
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Gateway Timeout')]")
            || $this->http->FindPreg("/(Gateway Timeout)/ims")
            || $this->http->FindSingleNode("//title[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/(The server is temporarily unable to service your\s*request\.\s*Please\s*try\s*again\s*later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# It looks like we have an issue with the site. We're working to fix this as soon as possible.
        if ($message = $this->http->FindPreg("/(It looks like we have an issue with the site\.\s*We\'re working to fix this as soon as possible\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // debug - page look like empty, provider bug fix
        if ($this->http->FindPreg('/id="pageId" value=(?:"aws_|")Homepage\"/ims')
            && (strstr($this->http->currentUrl(), "://{$this->host}/?mtype='")
                || $this->http->currentUrl() == 'https://www.expedia.com/')) {
            // AccountID: 4641038
            if ($this->AccountFields['Login2'] == 'BR') {
                $this->http->GetURL('https://www.expedia.com.br/user/go2RegRewards');
                $notMemberYet = $this->http->FindSingleNode("//h1[contains(text(), 'Now is a great time to join Expedia')]");

                if ($notMemberYet) {
                    return true;
                }
            } else {
                $this->http->GetURL("http://{$this->host}/user/rewards?defaultTab=2");
            }

            if (
                $this->loginSuccessful()
            ) {
                return true;
            }
        }

        if ($this->AccountFields['Login2'] == 'UK') {
            $this->http->GetURL("https://www.expedia.co.uk/storefront/model.json?_=" . time() . date("B"));
            $response = $this->http->JsonLog(null, 3, true);
            $name = ArrayVal($response, "userFirstName", null);
            $userType = ArrayVal($response, "userType", null);

            if ($name || $userType == 'Identified') {
                $this->SetProperty("Name", beautifulName($name));

                return true;
            }// if ($name)
        }// if ($this->AccountFields['Login2'] == 'UK')

        return false;
    }




    private function printItinHeader($type, $conf, $level = 4)
    {
        $this->logger->info("[{$this->currentItin}] Parse {$type} #{$conf}", ['Header' => $level]);
        $this->currentItin++;
    }


    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(/*\SeleniumFinderRequest::CHROME_84*/);
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
//                if (in_array($this->prefix, ['com.sv', 'com.hk','com.br', 'ch'])) {
//                    $selenium->http->GetURL("https://www.expedia.{$this->prefix}/en/?langid=1033");
//                }
                $selenium->http->GetURL("https://{$this->host}/login?uurl=e3id%3Dredr%26rurl%3D%2F%3Flogout%3D1");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 5);
            $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$loginButton) {
                $this->logger->error('Failed to find login button');

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $loginButton->click();

            $goToPassword = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'passwordButton']"), 5);
            $this->savePageToLogs($selenium);

            if (!$goToPassword) {
                $this->logger->error('Failed to find "goToPassword" input');
                $this->isGoToPassword = false;
                $parseQuestion = $this->parseQuestion($selenium);
                if ($parseQuestion) {
                    $cookies = $selenium->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                    }

                    $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
                }
                return $parseQuestion;
            }

            $goToPassword->click();

            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'enterPasswordFormPasswordInput']"), 5);
            $this->savePageToLogs($selenium);

            if (!$passwordInput) {
                $this->logger->error('Failed to find "password" input');

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $passwordButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'enterPasswordFormSubmitButton' and not(@disabled)]"), 3);
            $this->savePageToLogs($selenium);

            if (!$passwordButton) {
                $this->logger->error('Failed to find "password" input');

                return false;
            }

            $passwordButton->click();

            $selenium->waitForElement(WebDriverBy::xpath("
                //*[contains(text(), 'Get early access to a more rewarding experience')]
                | //*[@aria-describedby='header-menu-account_circle-description'] 
                | //div[contains(@class, 'uitk-banner-description')]
                | //h3[contains(text(),'Now you can explore and book with one account')]
                
            "), 15);
            if ($selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Get early access to a more rewarding experience')]"), 0)) {
                $notNow = $selenium->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Not now')] | //button[contains(text(), 'Continue')]"), 0);
                if ($notNow) {
                    $notNow->click();
                    sleep(10);
                }
            }
            if ($selenium->waitForElement(WebDriverBy::xpath("//h3[contains(text(),'Now you can explore and book with one account')]"), 0)) {
                $continue = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0);
                if ($continue) {
                    $continue->click();
                    sleep(10);
                }
            }
            try {
                $this->savePageToLogs($selenium);
            } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
