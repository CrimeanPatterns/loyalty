<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\ItineraryArrays\CarRental;

class TAccountCheckerAvis extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use OtcHelper;
    use SeleniumCheckerHelper;

    public $regionOptions = [
        ""            => "Select your region",
        'Australia'   => 'Australia',
        'Belgium'     => 'Belgium',
        'Finland'     => 'Finland',
        'France'      => 'France',
        "Germany"     => "Germany",
        "Italy"       => "Italy",
        'Sweden'      => 'Sweden',
        'Norway'      => 'Norway',
        'Spain'       => 'Spain',
        'Switzerland' => 'Switzerland',
        "UK"          => "UK",
        "USA"         => "USA",
    ];
    /** @var CaptchaRecognizer */
    private $recognizer;

    private $lastName = null;
    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Encoding" => "gzip, deflate, br",
        "bookingType"     => "car",
        "channel"         => "Digital",
        "deviceType"      => "bigbrowser",
        "domain"          => "us",
        "locale"          => "en",
        "password"        => "AVISCOM",
        "userName"        => "AVISCOM",
    ];
    private $apiurl = '/webapi';
    private $domain = 'com';

    private $activationStatus = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        if (in_array($this->AccountFields['Login2'], ['USA', 'Australia'])) {
            if (empty($this->State['digital-token'])) {
                return false;
            }

            $this->headers['digital-token'] = $this->State['digital-token'];
            $this->http->GetURL("https://www.avis.{$this->domain}{$this->apiurl}/summary/profile?url=account/my-profile/profile", $this->headers);
            $response = $this->http->JsonLog();

            if (
                isset($response->customerInfo)
                && $response->customerInfo->userState == 'AUTHENTICATED'
            ) {
                return true;
            }
        }// if (in_array($this->AccountFields['Login2'], ['USA', 'Australia']))
        else {
            $urlBooking = [
                'Germany'     => 'https://secure.avis.de',
                'Belgium'     => 'https://secure.avis.be',
                'France'      => 'https://secure.avis.fr',
                'Finland'     => 'https://secure.avis.fi',
                'Italy'       => 'https://secure.avisautonoleggio.it',
                'Norway'      => 'https://secure.avis.no',
                'Spain'       => 'https://secure.avis.es',
                'Sweden'      => 'https://secure.avis.se',
                'Switzerland' => 'https://secure.avis.ch',
                'UK'          => 'https://secure.avis.co.uk',
            ];
            $url = $urlBooking[$this->AccountFields['Login2']] ?? $urlBooking['UK'];
            $this->http->GetURL("{$url}/JsonProviderServlet/?requestType=userdetails");
            $json = $this->http->JsonLog();

            if (isset($json->loggedIn) && $json->loggedIn == true) {
                return true;
            }
        }

        return false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && $properties['SubAccountCode'] == 'avisSpend') {
            if (isset($properties['Currency'])) {
                switch ($properties['Currency']) {
                    case 'CHF':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "CHF%0.2f");

                    case 'EUR':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                    case 'USD':
                        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
                }
            }

            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        if (!in_array($this->AccountFields['Login2'], ['USA', 'Australia']) && false === filter_var($this->AccountFields["Login"], FILTER_VALIDATE_EMAIL)) {
            switch ($this->AccountFields['Login2']) {
                case 'Sweden':
                    throw new CheckException('Leider kann Ihre E-Mail-Adresse nicht erkannt werden. Bitte versuchen Sie es erneut und verwenden Sie folgendes Format: hallo@beispiel.ch', ACCOUNT_INVALID_PASSWORD);

                case 'Spain':
                    throw new CheckException('Lo sentimos, no reconocemos tu email. Por favor inténtalo de nuevo, usa este formato: hola@ejemplo.com', ACCOUNT_INVALID_PASSWORD);

                case 'Switzerland':
                    throw new CheckException('Leider kann Ihre E-Mail-Adresse nicht erkannt werden. Bitte versuchen Sie es erneut und verwenden Sie folgendes Format: hallo@beispiel.ch', ACCOUNT_INVALID_PASSWORD);

                case 'UK':
                    throw new CheckException('Sorry, we don\'t recognise your email address. Please try again, using this format: hello@example.com', ACCOUNT_INVALID_PASSWORD);

                case 'Germany':
                    throw new CheckException('Leider kann Ihre E-Mail-Adresse nicht erkannt werden. Bitte versuchen Sie es erneut und verwenden Sie folgendes Format: hallo@beispiel.de', ACCOUNT_INVALID_PASSWORD);

                case 'Belgium':
                    throw new CheckException('Uw e-mailadres wordt helaas niet herkend. Probeer het opnieuw en gebruik daarbij de volgende opmaak: hallo@voorbeeld.com', ACCOUNT_INVALID_PASSWORD);

                case 'France':
                    throw new CheckException('Nous sommes désolés, votre adresse électronique n’a pas pu être identifiée. Veuillez réessayer en utilisant ce format : bonjour@exemple.fr', ACCOUNT_INVALID_PASSWORD);

                case 'Italy':
                    throw new CheckException('Siamo spiacenti, il sistema non riconosce il tuo indirizzo e-mail. Per favore, prova di nuovo, utilizzando questo fomato: ciao@esempio.com', ACCOUNT_INVALID_PASSWORD);

                case 'Norway':
                    throw new CheckException('Beklager, vi gjenkjenner ikke e-postadressen din. Vennligst prøv igjen ved å bruke dette formatet: hello@example.com', ACCOUNT_INVALID_PASSWORD);
            }
        }

        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case "USA":
            case "Australia":
                unset($this->headers['DIGITAL_TOKEN']);
                unset($this->headers['digital-token']);

                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                // see perfectdrive, payless, avis (USA, Australia)

                if ($this->AccountFields['Login2'] == "Australia") {
                    $this->domain = 'com.au';
                }

                // refs #15704
                $this->logger->debug("[attempt]: {$this->attempt}");

                /*
                if ($this->attempt > 0 && !empty($this->AccountFields['Partner']) && $this->AccountFields['Partner'] != 'awardwallet') {
                    $this->setProxyBrightData();
                }
                */

                $avis_xsrf = $this->avis_xsrf();
                $this->http->GetURL("https://www.avis.{$this->domain}/en/home");
                $this->apiurl = $this->http->FindPreg("/var apiurl = \"([^\"]+)/");

                if (!$this->http->ParseForm("loginForm") || !$this->apiurl || !$avis_xsrf) {
                    // refs #15704
                    if ($this->http->Response['code'] == 403 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
                        && !empty($this->AccountFields['Partner']) && $this->AccountFields['Partner'] != 'awardwallet') {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;

                        throw new CheckRetryNeededException(2, 10);
                    }// if ($this->http->Response['code'] == 403 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)

                    return $this->checkErrors();
                } else {
                    $this->ErrorReason = null;
                }
                $data = [
                    "uName"                    => $this->AccountFields["Login"],
                    "password"                 => $this->AccountFields["Pass"],
                    "rememberMe"               => true,
                    'enterpriseCaptchaEnabled' => "true",
                    "displayControl"           => [
                        "variation" => "Big",
                        "closeBtn"  => true,
                    ],
                ];

                if ($siteKey = $this->http->FindPreg('/enableEnterpriseCaptcha = "true";\s*var enterpriseCaptchaSiteKey\s*=\s*"(.+?)"/')) {
                    $captcha = $this->parseCaptcha($siteKey, 'login');

                    if (!$captcha) {
                        return false;
                    }
                } elseif ($siteKey = $this->http->FindPreg('/var captchaSiteKey = "(.+?)"/')) {
                    $captcha = $this->parseCaptcha($siteKey);

                    if ($captcha === false) {
                        return false;
                    }
                }// if (!$this->http->FindPreg("/var enableCaptcha = false;/"))
                else {
                    $captcha = "";
                }

                if (isset($this->headers["avis_xsrf"]) && $this->headers["avis_xsrf"] == 'undefined') {
                    unset($this->headers["avis_xsrf"]);
                }

                $header = array_merge($this->headers, [
                    "Content-Type"         => "application/json",
                    "g-recaptcha-response" => $captcha,
                ]);
                sleep(3);
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://www.avis.{$this->domain}{$this->apiurl}/profile/login", json_encode($data), $header);
                $this->http->RetryCount = 2;

                if (isset($this->http->Response['headers']['digital-token'])) {
                    $this->headers['digital-token'] = $this->http->Response['headers']['digital-token'];
                }

                if ($this->http->FindPreg("/\"errorList\":\[\{\"type\":\"ERROR\",\"code\":\"0999\",/")) {
                    $captcha = $this->parseCaptcha($siteKey, 'login', 0.7);

                    if (!$captcha) {
                        return false;
                    }

                    $header = array_merge($this->headers, [
                        "Content-Type"         => "application/json",
                        "g-recaptcha-response" => $captcha,
                    ]);
                    sleep(3);
                    $this->http->RetryCount = 0;
                    $this->http->PostURL("https://www.avis.{$this->domain}{$this->apiurl}/profile/login", json_encode($data), $header);
                    $this->http->RetryCount = 2;
                    // We are Sorry, the site has not properly responded to your request. If the problem persists, please contact Avis
                }
                /*
                elseif ($this->http->FindPreg("/\"blockScript\"/")) {
                    $this->http->JsonLog();
                    $this->seleniumAuth();
                }
                */

                break;

            case "Germany":
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.de/mein-avis/buchung-bearbeiten';
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.de/';
                // no break
            case 'Belgium':
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.be/mijn-avis/reservering-wijzigen';
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.be';
                // no break
            case "France":
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.fr/votre-avis/g%C3%A9rer-ma-r%C3%A9servation';
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.fr/';
//            case 'Finland':
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.fi/oma-avis/hallinnoi-varaustasi';
// no break
            case "Finland":
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.fi/oma-avis/hallinnoi-varaustasi';
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.fi';
                // no break
            case 'Italy':
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avisautonoleggio.it/avis-per-te/gestisci-prenotazione';
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avisautonoleggio.it';
                // no break
            case 'Norway':
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.no';
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.no/din-avis/manage-booking';
                // no break
            case 'Spain':
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.es';
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.es/tu-avis/gestionar-reserva';
                // no break
            case 'Sweden':
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.se/ditt-avis/hantera-bokning';
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.se';
                // no break
            case 'Switzerland':
                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.ch';
//                !empty($urlLogin) ?: $urlLogin = 'https://secure.avis.ch/mein-avis/buchung-bearbeiten';
                // no break
            case "UK":
//                !empty($urlLogin) ?: $urlLogin = 'https://www.avis.co.uk/drive-avis/car-hire-services';
                !empty($urlLogin) ?: $urlLogin = 'https://www.avis.co.uk';

                $this->http->GetURL($urlLogin);

                if (!$this->http->ParseForm(null, "//form[contains(@class, 'login-form') or @id = 'loginForm']")) {
                    return $this->checkErrors();
                }

                $this->http->SetInputValue("login-email", $this->AccountFields["Login"]);
                $this->http->SetInputValue("login-hidtext", $this->AccountFields["Pass"]);
                unset($this->http->Form['require-logout']);

                break;
//			case "Finland":
//				$this->http->GetURL("https://secure.avis-europe.com/secure/preferred/default.aspx?Locale=fi-FI&Domain=FI&NBE=true");
//				if (!$this->http->ParseForm(null, 1, true, "//form[@id='aspnetForm']"))
//                    return $this->checkErrors();
//				$this->http->SetInputValue('ctl00$main$signIn$txtLogInEmailAddress', $this->AccountFields["Login"]);
//                $this->http->SetInputValue('ctl00$main$signIn$txtLogInPassword', $this->AccountFields["Pass"]);
//				$this->http->SetInputValue('__EVENTTARGET', 'ctl00$main$signIn$btnLogIn');
//				break;
            default:
                $this->logger->error("LoadLoginForm: incorrect region \"" . $this->AccountFields['Login2'] . "\"");

                return false;

                break;
        }

        return true;
    }

    public function Login()
    {
        if (!in_array($this->AccountFields['Login2'], ['USA', 'Australia']) && !$this->http->PostForm()
            && ($this->AccountFields['Login2'] != 'UK' && $this->http->Response['code'] != 400)) {
            return $this->checkErrors();
        }

        switch ($this->AccountFields['Login2']) {
            case "USA":
            case "Australia":
                $response = $this->http->JsonLog(null, 3, true);

                if ($this->http->FindPreg("/CREATE A USERNAME/ims")) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("Go to provider site to create a username", ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindPreg("/\"blockScript\"/")) {
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $this->DebugInfo = 'blockScript';

                    return false;
                }

                if (!empty($message = $this->http->FindSingleNode("//ul/li[1]/span[@class='errorMessage']"))) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                //# Our records indicate that you have not yet activated your profile
                if ($this->http->FindPreg("/(Our records indicate that you have not yet activated your profile\.)/ims")
                    || $this->http->currentUrl() == "https://www.avis.{$this->domain}/car-rental/profile/activate.ac?action=activated") {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("Our records indicate that you have not yet activated your profile.", ACCOUNT_PROVIDER_ERROR);
                }
                // access
                if (ArrayVal($response, 'customerInfo', null)) {
                    $this->captchaReporting($this->recognizer);

                    return true;
                }

                if (ArrayVal($response, 'otpFlow', null) == true) {
                    $this->captchaReporting($this->recognizer);
                    $this->DebugInfo = '2fa';

                    $token = $response['securityAssessmentSummary']['otpTokenverifiers']['emailAddress']['token'] ?? null;
                    $email = $response['securityAssessmentSummary']['otpTokenverifiers']['emailAddress']['value'] ?? null;

                    if (!$email) {
                        $token = $response['securityAssessmentSummary']['otpTokenverifiers']['phoneNumber']['token'];
                        $phone = $response['securityAssessmentSummary']['otpTokenverifiers']['phoneNumber']['value'];
                    }

                    if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                        $this->Cancel();
                    }

                    // For added security, please enter the verification code that has been sent to your email address beginning with ...****...@GMAIL.COM
                    $data = [
                        "v"    => "85AXn53af-oJBEtL2o2WpAjZ", //todo: wtf?
                        "avrt" => $token,
                    ];
                    $this->http->RetryCount = 0;
                    $this->http->PostURL("https://www.google.com/recaptcha/api3/accountchallenge?k=6LeNRRsbAAAAAEOyIR4EyXHfUTJAw9r5WAItrETf", $data);
                    $this->http->RetryCount = 2;

                    // AccountID: 1903081, 811662, 1903081
                    if ($this->http->FindPreg('/\[10,null,null,null,"/')) {
                        $this->http->JsonLog();
                        $this->logger->notice("something went wrong, sent cvode to phone");
                        $email = null;
                        $token = $response['securityAssessmentSummary']['otpTokenverifiers']['phoneNumber']['token'];
                        $phone = $response['securityAssessmentSummary']['otpTokenverifiers']['phoneNumber']['value'];
                        $data["avrt"] = $token;
                        $this->http->RetryCount = 0;
                        $this->http->PostURL("https://www.google.com/recaptcha/api3/accountchallenge?k=6LeNRRsbAAAAAEOyIR4EyXHfUTJAw9r5WAItrETf", $data);
                        $this->http->RetryCount = 2;
                    }

                    $newToken = $this->http->FindPreg('/\[null,\"([^"]+)/');

                    if (!$newToken) {
                        return false;
                    }

                    $this->State['token'] = $newToken;
                    $this->State['headers'] = $this->headers;

                    if ($email) {
                        $this->Question = "For added security, please enter the verification code that has been sent to your email address beginning with {$email}";
                    } elseif (isset($phone)) {
                        $this->Question = "For added security, please enter the verification code that has been sent to your phone {$phone}";
                    }

                    $this->ErrorCode = ACCOUNT_QUESTION;
                    $this->Step = "Question";

                    return false;
                }

                // https://www.avis.com/libs/cq/i18n/dict.en_US.json

                $code = $this->http->FindPreg("/\{\"errorList\":\[\{\"type\":\"ERROR\",\"code\":\"(\d+)\"/");
                $this->logger->error($code);
                /*
                 * The information provided does not match our records.
                 * Please ensure that the information you have entered is correct and try again.
                 */
                if (in_array($code, ['30047', '30034', '30035', '13036'])) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("The information provided does not match our records. Please ensure that the information you have entered is correct and try again.", ACCOUNT_INVALID_PASSWORD);
                }
                // account lockout
                if (in_array($code, ['30033'])) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("The information you have entered below is incorrect. Please make sure that you have correctly entered the username/wizard number and password that is associated with your online profile. You have exceeded the maximum log in attempts. For the security of your information your Username/Wizard number has been disabled and you will be receiving an email shortly explaining how you can reset your profile information. For an immediate reservation without your username/wizard number please call customer sevice at 1-800-230-4898.", ACCOUNT_LOCKOUT);
                }
                // We could not process your request due to a temporary technical problem, please try again.
                if (in_array($code, ['80010'])) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("We could not process your request due to a temporary technical problem, please try again.", ACCOUNT_PROVIDER_ERROR);
                }
                // message: "Your password has expired. A link to change your password has been sent to your email *******@*******.COM"
                $message = $this->http->FindPreg("/\{\"errorList\":\[\{\"type\":\"ERROR\",\"code\":\"\d+\",\"message\":\"([^\"]+)/");

                if (in_array($code, ['30365']) && $message) {
                    $this->captchaReporting($this->recognizer);
                    // wrong error?
                    throw new CheckRetryNeededException(2, 15, $message, ACCOUNT_INVALID_PASSWORD);
                }
                // We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us.
                if (in_array($code, ['40018'])) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us.", ACCOUNT_PROVIDER_ERROR);
                }
                // We could not process your request at this time due to technical issues. Please try again or contact customer service.
                if (in_array($code, ['33999', '401', '6069'])) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckRetryNeededException(2, 10, "We could not process your request at this time due to technical issues. Please try again or contact customer service.");
                }
                // CAPTCHA was not accepted
                if (in_array($code, ['06016'])) {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
                }

                // may be captcha issue?
                if (in_array($code, ['0999'])) {
                    $this->DebugInfo = $code;
//                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(3, 10);
                }

                // INCORRECT ERROR: "For security reasons, please reset your password. A reset link has been sent to your email."
                if (in_array($code, ['30366'])) {
                    throw new CheckRetryNeededException(2, 10, "For security reasons, please reset your password. A reset link has been sent to your email.", ACCOUNT_INVALID_PASSWORD); // may be false positive
                }

                // CAPTCHA was not accepted
                if (in_array($code, ['06028'])) {
                    $this->DebugInfo = "CAPTCHA was not accepted";
                }

                // retry
                if ($this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]")) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckRetryNeededException();
                }

                // AccountID: 4247516
                if (in_array($code, ['89001'])) {
                    throw new CheckException("We are unable to process your request at this time. Please return to the Homepage and start your process again or use the Worldwide Phone Number List to find your Avis Customer Service telephone number.", ACCOUNT_PROVIDER_ERROR);
                }

                // maintenance
                if ($this->http->FindPreg("/^\{\"errorList\":null\}$/")) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("We'll be back soon. The site is undergoing maintenance for an extended period today. Thanks for your patience.", ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $code;

                break;

            case 'Belgium':
            case "Germany":
            case 'Finland':
            case 'France':
            case 'Italy':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case "UK":
                // js redirect
                $this->handleRedirect();

                // refs #16616
                $this->handleRedirect();

                if (
                    !$this->http->FindSingleNode("//input[contains(@id, '-error')]")
                    && $this->http->ParseForm(null, "//form[contains(@class, 'login-form') or @id = 'loginForm']")
                ) {
                    $this->http->SetInputValue("login-email", $this->AccountFields["Login"]);
                    $this->http->SetInputValue("login-hidtext", $this->AccountFields["Pass"]);
                    unset($this->http->Form['require-logout']);

                    if (!$this->http->PostForm()) {
                        return $this->checkErrors();
                    }
                    $this->handleRedirect();
                }
                // refs #16616
                if ($this->AccountFields['Login2'] == 'UK' && $this->http->Response['code'] == 400
                    && $this->http->currentUrl() == 'https://secure.avis.co.uk/') {
                    throw new CheckRetryNeededException(3);
                }

                $errorPassword = [
                    'Sorry, your email address and password don\'t match. Please check and try again',  // UK
                    'Lo sentimos, tu email y contraseña no coinciden. Por favor, compruébalo de nuevo', // Spain
                    'Din e-postadress och lösenord matchar inte. Vänligen kontrollera och försök igen', // Sweden
                    'Es tut uns leid, aber Ihre E-Mail-Adresse und Ihr Passwort stimmen nicht überein', // Germany, Switzerland
                    'Uw e-mailadres en wachtwoord komen niet overeen. Controleer ze en probeer',        // Belgium
                    'Nous sommes désolés mais votre adresse e-mail et votre mot de passe ne correspondent pas.', // France
                    'Siamo spiacenti, il tuo indirizzo e-mail e la tua password non coincidono',        // Italy
                ];

                foreach ($errorPassword as $error) {
                    if ($message = $this->http->FindSingleNode('//em[contains(text(), "' . $error . '")]')) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                }

                // Sorry, your email address and password do not match. Please check and try again.
                if ($error = $this->http->FindSingleNode('//input[@id = "loginform-emailhidtext-error"]/@value')) {
                    $this->logger->error($error);

                    if (
                        // Norway, Sweden, UK, Germany, Italy
                    $message = $this->http->FindPreg("/^(?:Beklager, din profil har blitt låst på grunn av at fem passord-forsøk har feilet\.|Ditt konto har spärrats efter fem försök med felaktig lösenord\.|Sorry, your account has been locked out due to five incorrect password attempts\.|Ihr Konto wurde aus Sicherheitsgründen gesperrt, weil das Passwort fünfmal falsch eingegeben wurde\.|Lo sentimos, tu cuenta ha sido bloqueada debido a que hay registrados cinco intentos fallidos en la contraseña\.|Siamo spiacenti, il tuo account è stato bloccato dopo cinque tentativi errati di inserimento della password\.|Pahoittelemme, mutta tilisi on lukittu viiden virheellisen salasanayrityksen jälkeen\.)/", false, $error)
                    ) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    if (
                        strstr($error, 'Sorry, your email address and password do not match. Please check and try again.')
                        || strstr($error, 'Sorry, your email address and password don\'t match.')
                        || strstr($error, 'Es tut uns leid, aber Ihre E-Mail-Adresse und Ihr Passwort stimmen nicht überein.')
                        || strstr($error, 'Lo sentimos, tu email y contraseña no coinciden. Por favor, compruébalo de nuevo.')
                        || strstr($error, 'Din e-postadress och lösenord matchar inte. Vänligen kontrollera och försök igen.')
                        || strstr($error, 'Valitettavasti sähköpostiosoitteesi ja salasanasi eivät täsmää. Ole hyvä ja tarkista tiedot ja yritä uudelleen.')
                        || strstr($error, 'Uw e-mailadres en wachtwoord komen niet overeen. Controleer ze en probeer het opnieuw.')
                    ) {
                        throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                    }
                }
                $code = $this->http->FindPreg("/errorCode\s*:\s*\"([^\"]+)\",/");

                if ($code) {
                    $this->logger->error("[errorCode]: {$code}");

                    if ($code == 'ES259' || $code == 'es259') {
                        throw new CheckException("Sorry, your email address and password don't match. Please check and try again. (ES259)", ACCOUNT_INVALID_PASSWORD);
                    }

                    if ($code == 'es260') {
                        throw new CheckException("Sorry, we don't recognise your email address. Please check and try again. (ES260)", ACCOUNT_INVALID_PASSWORD);
                    }

                    if ($code == 'ES266' || $code == 'es266') {
                        throw new CheckException("Sorry, your account has been locked out due to five incorrect password attempts. (ES266)", ACCOUNT_LOCKOUT);
                    }

                    return false;
                }
                // Die Seite konnte leider nicht gefunden werden
                if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Die Seite konnte leider nicht gefunden werden")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Provider error
                if ($this->http->FindPreg('#/error-500\.html#', false, $this->http->currentUrl())) {
                    $errors = [
                        //'',  // UK
                        //'', // Spain
                        'Tyvärr verkar det vara problem med vår hemsida, men låt inte det stoppa dig... ', // Sweden
                        //'', // Germany,  Switzerland
                        'De pagina die u zoekt is niet gevonden...',  // Belgium
                        'Verkkosivussamme on jokin ongelma, mutta älä anna sen estää sinua...', // Finland
                        'Sorry, your email address and password don\'t match. Please check and try again', // Italy
                    ];

                    foreach ($errors as $error) {
                        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "' . $error . '")]')) {
                            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                        }
                    }
                }

                if (strpos($this->http->Response['body'], 'your-avis-panel') !== false
                    || $this->http->FindSingleNode('//div[contains(@class, "your-avis-panel")]')
                ) {
                    return true;
                }

                $this->logger->debug("[Host]: {$this->http->getCurrentHost()}");
                // Sorry, we don't recognise your email address. Please check and try again.
                if ((strstr($this->http->currentUrl(), 'https://' . $this->http->getCurrentHost() . ':80/drive-avis/car-hire-services')
                        || strstr($this->http->currentUrl(), 'https://' . $this->http->getCurrentHost() . ':80/mein-avis?require-login=true')
                        || strstr($this->http->currentUrl(), 'https://' . $this->http->getCurrentHost() . '/?require-login=true&login-email=' . urlencode($this->AccountFields["Login"]) . '&login-hidtext='))
                    && strstr($this->http->currentUrl(), 'require-login=true')) {
                    throw new CheckException("Sorry, we don't recognise your email address. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
                }

                // You are seeing this page because we have detected unauthorized activity.
                if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Unauthorized Activity Detected")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

//                if ($this->http->currentUrl() == 'https://secure.avis.co.uk/') {
//                    throw new CheckRetryNeededException(2, 5);
//                }

                if (in_array($this->AccountFields['Login2'], [
                    'Belgium',
                    'France',
                    'Germany',
                    'Italy',
                    'Norway',
                    'Spain',
                    'Sweden',
                    'Switzerland',
                    'UK',
                    'Finland',
                ])) {
                    return true;
                }

                return $this->checkErrors();

                break;
            /*case 'Finland':
                if ($err = $this->http->FindSingleNode("//text()[normalize-space(.)='Käyttäjätunnus tai salasana on väärä']"))
                    throw new CheckException($err, ACCOUNT_INVALID_PASSWORD);
                // Virheellinen sähköpostiosoite
                if ($err = $this->http->FindSingleNode("(//*[@class='error'][normalize-space(.)])[1]"))
                    throw new CheckException($err, ACCOUNT_INVALID_PASSWORD);
                if(!$this->http->FindSingleNode("//a[@id='btnLoyaltyPortal']"))
                    return false;
                if (!$this->http->ParseForm(null, 1, true, "//form[@id='aspnetForm']"))
                    return $this->checkErrors();
                $this->http->SetInputValue('__EVENTTARGET', 'ctl00$main$btnLoyaltyPortal');
                if (!$this->http->PostForm())
                    return $this->checkErrors();
                $this->logger->debug("[Current URL]: ".$this->http->currentUrl());
                if ($this->http->currentUrl() == "https://{$this->http->getCurrentHost()}/Statement")
                    return true;
                /*
                 * Pahoittelemme virhettä. Huomioithan että mikäli olet rekisteröitynyt Avis Preferred
                 * käyttäjäksi viimeisen 24 tunnin sisällä voi kestää jopa 2 päivää ennen kuin lausuntosi tulee näkyviin.
                 * /
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Pahoittelemme virhettä. Huomioithan että mikäli olet rekisteröitynyt Avis Preferred käyttäjäksi viimeisen 24 tunnin sisällä voi kestää jopa 2 päivää ennen kuin lausuntosi tulee näkyviin.')]"))
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                // Pahoittelut, meillä on teknisiä ongelmia. Ole hyvä ja yritä myöhemmin uudelleen.
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Pahoittelut, meillä on teknisiä ongelmia. Ole hyvä ja yritä myöhemmin uudelleen.')]"))
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                break;*/
            default:
                $this->logger->error("Login: incorrect region \"" . $this->AccountFields['Login2'] . "\"");

                return false;

                break;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->debug('Region => ' . $this->AccountFields['Login2']);

        $this->headers = $this->State['headers'];
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = [
            "v"        => "85AXn53af-oJBEtL2o2WpAjZ", //todo: wtf?
            "avrt"     => $this->State['token'],
            "response" => str_replace('==', '..', base64_encode('{"pin":"' . $answer . '"}')),
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/x-www-form-urlencoded;charset=utf-8",
            "Alt-Used"     => "www.google.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.google.com/recaptcha/api3/accountverify?k=6LeNRRsbAAAAAEOyIR4EyXHfUTJAw9r5WAItrETf", $data, $headers);
        $this->http->RetryCount = 2;
        $token = $this->http->FindPreg("/,\"(03A[^\"]+)/ims");

        // The code you entered is not valid. Please try again.
        if (!$token && $this->http->FindPreg("/\",null,null,\[\"[^@]+@[^\"]+\",6,null,15\]\]$/")) {
            $this->AskQuestion($this->Question, "The code you entered is not valid. Please try again.", "Question");

            return false;
        }

        if (!$token) {
            return false;
        }

        unset($this->headers['avis_xsrf']);
        $headers = $this->headers + [
            "token"        => $token,
            "Content-Type" => "application/json",
            "Referer"      => "https://www.avis.{$this->domain}/en/loyalty-profile/avis-preferred/login",
        ];
        $this->http->PostURL("https://www.avis.{$this->domain}{$this->apiurl}/profile/login/mfaAuth", '{"modeOfOTP":"emailAddress"}', $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->customerInfo)) {
            if (isset($response->error) && $response->error == 'Forbidden') {
                $this->DebugInfo = '403 after 2fa';

                throw new CheckRetryNeededException(2, 5);
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case "USA":
            case "Australia":
                $this->State['digital-token'] = $this->headers['digital-token'];
                $response = $this->http->JsonLog(null, 0, true);
                $customerInfo = ArrayVal($response, 'customerInfo');
                $loyaltyDetails = ArrayVal($customerInfo, 'loyaltyDetails');
                // Name
                $this->lastName = ArrayVal($customerInfo, 'lastName');
                $this->SetProperty('Name', beautifulName(ArrayVal($customerInfo, 'firstName') . " " . $this->lastName));
                // Wizard Number
                $this->SetProperty('Number', ArrayVal($customerInfo, 'wizardNumberMasked', null));
                // Status
                $this->SetProperty('Status', ArrayVal($customerInfo, 'membershipStatus'));
                // Balance - Avis Preferred Points (header)
                $this->SetBalance(ArrayVal($loyaltyDetails, 'points'));
                // Rentals to next tier
                $this->SetProperty('RentalsToNextTier', ArrayVal($loyaltyDetails, 'rentalCountToPromote'));
                // Spend to next tier
                $this->SetProperty('SpendToNextTier', ArrayVal($loyaltyDetails, 'rentalAmountToPromote'));

                // not a member
                if (
                    $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                    && (
                        ArrayVal($loyaltyDetails, 'loyalityEnrolled', null) == false
                        || $this->http->FindPreg("/\"loyaltyDetails\":\{\"loyaltyElgibile\":(?:true|false),\"loyalityEnrolled\":(?:true|false),\"loyaltyOptIn\":(?:true|false)\}/")
                        || $this->http->FindPreg("/\"loyaltyDetails\":\{\"rentalAmountToPromote\":\"0\",\"loyaltyElgibile\":(?:true|false),\"loyalityEnrolled\":(?:true|false),\"loyaltyOptIn\":(?:true|false)\}/")
                        || $this->http->FindPreg("/\"loyaltyDetails\":\{\"rentalCountToPromote\":0,\"rentalAmountToPromote\":\"0\",\"rentalAmountPercentage\":0.0,\"rentalCountPercentage\":0,\"loyaltyElgibile\":(?:true|false),\"loyalityEnrolled\":(?:true|false),\"loyaltyOptIn\":(?:true|false)\}/")
                    )
                    && isset($this->Properties['Number'])
                    && !empty($this->Properties['Name'])
                ) {
                    $this->SetBalanceNA();
                }

                // refs #12752
                $this->activationStatus = $customerInfo['webCustomer']['activationStatus'] ?? null;

                if ($this->AccountFields['Login2'] === 'USA') {
                    // refs #19194
                    if ($this->activationStatus === '0') {
                        $this->logger->notice('Skipping exp date: avis preferred not activated');
                    } else {
                        $this->setExpDateUSA();
                    }
                }

                break;

            case 'Belgium':
            case "Germany":
            case 'Finland':
            case 'France':
            case 'Italy':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case "UK":
                $profileUrl = $this->http->currentUrl();
                // refs #23467
                if ($profileUrl == "https://{$this->http->getCurrentHost()}/") {
                    $profileUrl = "https://{$this->http->getCurrentHost()}/avisPreferred";
                }

                if (!strstr($this->http->currentUrl(), '/JsonProviderServlet/?requestType=userdetails')) {
                    $this->http->GetURL('https://' . $this->http->getCurrentHost() . '/JsonProviderServlet/?requestType=userdetails');
                }
                $json = $this->http->JsonLog();

                if (empty($json)) {
                    return false;
                }

                $this->http->GetURL($profileUrl);
                // Name
                if (isset($json->firstName) && isset($json->lastName)) {
                    $this->SetProperty('Name', beautifulName(trim($json->firstName . ' ' . $json->lastName)));
                }
                // Status
                if ($status = $this->http->FindSingleNode("//span[@class = 'card-image']/img/@src")) {
                    $status = basename($status);

                    switch ($status) {
                        case 'aimiaPF.png':
                            $this->SetProperty('Status', "Avis Preferred Plus");

                            break;

                        case 'aimiaPFDrive.Gold.png':
                            $this->SetProperty('Status', "Avis Preferred Plus Drive Gold");

                            break;

                        case 'aimiaPP.png':
                            $this->SetProperty('Status', "Avis President’s Club");

                            break;

                        case 'aimiaP.png':
                            $this->SetProperty('Status', "Avis Preferred");

                            break;

                        case 'Azur.png':// AccountID: 4601968
                            $this->SetProperty('Status', "Azur");

                            break;

                        default:
                            $this->logger->notice("Status: {$status}");
                            $this->sendNotification("Avis (My Avis). New status was found: {$status}");
                    }
                }
                !empty($this->Properties['Status']) ?: $this->SetProperty('Status', $this->http->FindSingleNode('//div[@class="tierSection"]/div[@class="tierBalance"][1]/span[@class="tierTxt"]'));
                // Balance - Rentals
                $this->SetBalance($json->rentals);
                // Your Customer Number
                $this->SetProperty('Number', $json->wizardNumber);

                // Spend to next tier
                $spendsLocale = ['contains(text(), "Spend to next tier:")',           // UK
                    'contains(text(), "Gasto hasta el próximo nivel:")', // Spain
                    'contains(text(), "Spendera till nästa nivå:")',     // Sweden
                    'contains(text(), "Uitgaven tot het volgende niveau:")',     // Belgium
                    'contains(text(), "Umsatz bis zum nächsthöheren Status:")',  // Germany,  Switzerland
                    'contains(text(), "Spendi fino al livello successivo:")',  // Italy
                    'contains(text(), "Matkaa seuraavalle tasolle:")',  // Finland
                    'contains(text(), "Montant avant le niveau suivant :")',  // France
                    'contains(text(), "Igjen til neste nivå:")',  // Norway
                ];
                $this->SetProperty('SpendToNextTier', $this->http->FindSingleNode('//p[' . implode(' or ', $spendsLocale) . ']', null, true, '/\:\s*([^<]+)/ims'));
                $currency = $this->http->FindNodes('//div[@class="loyal-spend-text"]');

                if (!empty($currency)) {
                    $this->logger->debug('[currency]: ' . implode(' ', $currency));
                    $currency = $this->http->FindPreg('/:\s+(.{1,3})\s*[\d.,]+/u', false, implode(' ', $currency));
                    $this->logger->debug('[currency]: ' . $currency);
                    $currency = $this->currency($currency);
                    $this->logger->debug('[currency]: ' . $currency);
                    $valute = $this->currencySymbols[$currency] ?? $currency;
                } else {
                    $currency = 'EUR';
                    $valute = '€';
                }

                // Rentals to next tier
                $rentalLocale = ['contains(text(), "Rentals to next tier:")',              // UK
                    'contains(text(), "Alquileres hasta el próximo nivel:")', // Spain
                    'contains(text(), "Bilhyror till nästa nivå:")',          // Sweden
                    'contains(text(), "Huurauto’s tot het volgende niveau:")',   // Belgium
                    'contains(text(), "Mieten bis zum nächsthöheren Status:")',  // Germany,  Switzerland
                    'contains(text(), "Noleggi necessari per passare al livello successivo:")',  // Italy
                    'contains(text(), "Vuokrausten määrä seuraavaan tasoon:")',  // Finland
                    'contains(text(), "Locations avant le niveau suivant :")',  // France
                    'contains(text(), "Leier til neste nivå:")',  // Norway
                ];
                $this->SetProperty('RentalsToNextTier', $this->http->FindSingleNode('//p[' . implode(' or ', $rentalLocale) . ']', null, true, '/\:\s*([^<]+)/ims'));

                // Anniversary Date
                $rentalLocale = [
                    'contains(text(), "Your Anniversary:")', // UK
                    'contains(text(), "Tu aniversario:")', // Spain
                    'contains(text(), "Din årsdag:")',          // Sweden
                    'contains(text(), "Uw jubileum:")',   // Belgium
                    'contains(text(), "Ihr Jahrestag:")',  // Germany,  Switzerland
                    'contains(text(), "Il tuo anniversario:")',  // Italy
                    'contains(text(), "Sinun Avis Preferred -vuosipäiväsi:")',  // Finland
                    'contains(text(), "Votre anniversaire Preferred:")',  // France
                    'contains(text(), "Jubileumsdagen din:")',  // Norway
                ];
                $this->SetProperty('AnniversaryDate', $this->http->FindSingleNode('//span[' . implode(' or ', $rentalLocale) . ']/following-sibling::span'));

                // SubAccount - Spend    // refs #6146
                if (isset($json->rentalsSpent)) {
                    // for Elite level
                    $this->SetProperty('Spent', $valute . $json->rentalsSpent);
                    $subAccounts[] = [
                        'Code'        => 'avisSpend',
                        'DisplayName' => 'Spend',
                        'Balance'     => $this->Properties['Spent'],
                        'Currency'    => $currency,
                    ];
                    $this->SetProperty('CombineSubAccounts', false);
                    $this->SetProperty('SubAccounts', $subAccounts);
                }

                break;
            /*case 'Finland':
                // Tervetuloa NIKO LINDVALL - Name
                $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class='ltCustomerStatusBoxHeader']/descendant::text()[normalize-space(.)][1]", null, true, "#Tervetuloa (.+)#")));
                // Taso     Avis Preferred - Elite level
                $this->SetProperty('Status', $this->http->FindSingleNode("//td[normalize-space(.)='Taso']/following-sibling::td[1]"));
                // Asiakasnumero     9EF07V - Customer Number
                $this->SetProperty('Number', $this->http->FindSingleNode("//td[normalize-space(.)='Asiakasnumero']/following-sibling::td[1]"));
                // Vuokraukset: 3     - главный баланс
                $this->SetBalance($this->http->FindSingleNode("//text()[normalize-space(.)='Vuokraukset:']/ancestor::td[1]", null, true, "#Vuokraukset:\s+(.+)#"));
                // Käytä: € 224,77 - Spend
                $spent = $this->http->FindSingleNode("//text()[normalize-space(.)='Käytä:']/ancestor::td[1]", null, true, "#Käytä:\s+(.+)#");
                if(preg_match("#^(\S+)\s+([\d\,\.]+)$#", $spent, $m)) {
                    // for Elite level
                    $this->SetProperty('Spent', $spent);
                    $subAccounts[] = [
                        'Code'        => 'avisSpend',
                        'DisplayName' => 'Spend',
                        'Balance'     => $m[2],
                        'Currency'    => $m[1],
                    ];
                    $this->SetProperty('CombineSubAccounts', false);
                    $this->SetProperty('SubAccounts', $subAccounts);
                }
                // Korotus: 2 - Rentals to next tier
                $this->SetProperty('RentalsToNextTier', $this->http->FindSingleNode("//text()[normalize-space(.)='Vuokraukset:']/following::div[@class='ltSliderLegendWrapper'][1]//text()[starts-with(normalize-space(.), 'Korotus:')]", null, true, "#Korotus:\s+(.+)#"));
                // Korotus: €775,23 - Spend to next tier
                $this->SetProperty('SpendToNextTier', $this->http->FindSingleNode("//text()[normalize-space(.)='Käytä (€):']/following::div[@class='ltSliderLegendWrapper'][1]//text()[starts-with(normalize-space(.), 'Korotus:')]", null, true, "#Korotus:\s+(.+)#"));

                break;*/
            default:
                $this->logger->error("Parse: incorrect region \"" . $this->AccountFields['Login2'] . "\"");

                return false;

                break;
        }

        return true;
    }

    public function ParseItineraries(): array
    {
        $result = [];

        switch ($this->AccountFields['Login2']) {
            case 'Belgium':
            case "Germany":
            case 'Finland':
            case 'France':
            case 'Italy':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case "UK":
                $urlBooking = [
                    'Belgium'     => 'https://secure.avis.be/mijn-avis/reservering-wijzigen',
                    'Germany'     => 'https://secure.avis.de/mein-avis/buchung-bearbeiten',
                    'France'      => 'https://secure.avis.fr/votre-avis/g%C3%A9rer-ma-r%C3%A9servation',
                    'Finland'     => 'https://secure.avis.fi/oma-avis/hallinnoi-varaustasi',
                    'Italy'       => 'https://secure.avisautonoleggio.it/avis-per-te/gestisci-prenotazione',
                    'Norway'      => 'https://secure.avis.no/din-avis/manage-booking',
                    'Spain'       => 'https://secure.avis.es/tu-avis/gestionar-reserva',
                    'Sweden'      => 'https://secure.avis.se/ditt-avis/hantera-bokning',
                    'Switzerland' => 'https://secure.avis.ch/mein-avis/buchung-bearbeiten',
                    'UK'          => 'https://secure.avis.co.uk/your-avis/manage-booking',
                ];
                $url = $urlBooking[$this->AccountFields['Login2']] ?? $urlBooking['UK'];
                $res = $this->getUpcomingInfoFromJson();

                if ($res) {
                    [$links, $jsons] = $res;
                    $this->logger->info('Found Links', ['Header' => 3]);
                    $this->logger->info(var_export($links, true), ['pre' => true]);

                    foreach ($jsons as $json) {
                        if (isset($json['bookingNum'])) {
                            $this->http->GetURL($url);

                            if (!$this->http->ParseForm(null, "//input[@name='InputBookingNumber']/ancestor::form[1]")) {
                                return [];
                            }
                            $this->http->SetInputValue('InputBookingNumber', $json['bookingNum']);
                            $this->http->SetInputValue('InputSurname', $json['surName']);
                            $this->http->SetInputValue('InputEmailAddress', $this->AccountFields['Login']);

                            $this->form = $this->http->Form;
                            $this->formUrl = $this->http->FormURL;

                            if (!$this->http->PostForm(['Content-Type' => 'application/x-www-form-urlencoded'])) {
                                return [];
                            }

                            if ($this->http->FindSingleNode("//h1[contains(text(),'Unauthorized Activity Detected')]")) {
                                $this->logger->notice('Retry');
                                $this->http->Form = $this->form;
                                $this->http->FormURL = $this->formUrl;

                                if (!$this->http->PostForm(['Content-Type' => 'application/x-www-form-urlencoded'])) {
                                    return [];
                                }

                                if ($this->http->FindSingleNode("//h1[contains(text(),'Unauthorized Activity Detected')]")) {
                                    $this->sendNotification('fail retry // MI');
                                } else {
                                    $this->sendNotification('success retry // MI');
                                }
                            }
                            $error = $this->http->FindSingleNode('//div[contains(@class, "post-submission-error")]//span[contains(@class, "error")]');

                            if ($error) {
                                $this->logger->error($error);

                                continue;
                            }
                            $this->increaseTimeLimit(120);
                            $this->http->TimeLimit = 500;
                            $this->redirectJavaScript();
                            $it = $this->CheckRentalDetails($json['bookingNum'], false, $this->AccountFields['Login2'], $json);

                            if (is_array($it)) {
                                $result[] = $it;
                            }
                        }// if (isset($json['bookingNum']))
                    }
                }// if ($res)
                elseif ($this->http->FindPreg("/^null$/")) {
                    return $this->noItinerariesArr();
                }

                break;

            case "USA":
            case "Australia":
            default:
                if ($this->activationStatus === '0') {
                    $this->logger->notice('Skipping itineraries: avis preferred not activated');

                    return [];
                }
                $this->http->GetURL("https://www.avis.{$this->domain}{$this->apiurl}/ncore/profile/upcoming-reservations", $this->headers);

                if (isset($this->http->Response['headers']['digital-token'])) {
                    $this->headers['digital-token'] = $this->http->Response['headers']['digital-token'];
                }
                $itineraries = $this->http->JsonLog(null, 0);

                if (!empty($itineraries->resSummaryList) && is_array($itineraries->resSummaryList)) {
                    $this->logger->debug('Found reservations ' . count($itineraries->resSummaryList));

                    foreach ($itineraries->resSummaryList as $itinerary) {
                        $it = $this->CheckRentalDetails($itinerary->confirmationNumber, $this->lastName);

                        if (is_array($it)) {
                            $result[] = $it;
                        } else {
                            $this->logger->error($it);
                        }
                    }// foreach ($response->resSummaryList as $res)
                }// if (!empty($response->resSummaryList) && is_array($response->resSummaryList))
                elseif ($this->http->FindPreg('/^\{"otpFlow":false\}$/')) {
                    $this->http->GetURL("https://www.avis.{$this->domain}/en/loyalty-profile/avis-preferred/dashboard/my-activity/upcoming-reservations");

                    if ($this->http->FindPreg('#<p>No Upcoming Rentals</p>#')) {
                        return $this->noItinerariesArr();
                    }
                }

                break;
        }// switch ($this->AccountFields['Login2'])

        return $result;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        switch ($this->AccountFields['Login2']) {
            case "USA":
                $arg['SuccessURL'] = 'https://www.avis.com/en/loyalty-profile/avis-preferred/dashboard/profile';

                break;

            case "Australia":
                $arg['SuccessURL'] = 'https://www.avis.com.au/en/loyalty-profile/avis-preferred/dashboard/profile';

                break;

            case 'Belgium':
                $arg['SuccessURL'] = 'https://secure.avis.be/';

                break;

            case "Germany":
                $arg['SuccessURL'] = 'https://secure.avis.de/';

                break;

            case "Norway":
                $arg['SuccessURL'] = 'https://secure.avis.no';

                break;
//            case "Finland":
//                $arg['SuccessURL'] = 'https://www.avis.fi/avis-preferred';
//                break;
            case "France":
                $arg['SuccessURL'] = 'https://secure.avis.fr//';

                break;

            case 'Italy':
                $arg['SuccessURL'] = 'https://secure.avisautonoleggio.it/';

                break;

            case 'Sweden':
                $arg['SuccessURL'] = 'https://secure.avis.se/';

                break;

            case 'Spain':
                $arg['SuccessURL'] = 'https://secure.avis.es/';

                break;

            case 'Switzerland':
                $arg['SuccessURL'] = 'https://secure.avis.ch/';

                break;

            case "UK":
                $arg['SuccessURL'] = 'https://secure.avis.co.uk/';

                break;
        }

        return $arg;
    }

    public function ConfirmationNumberURL($arFields)
    {
        switch ($arFields['Region'] ?? '') {
            case 'Belgium':
                return 'https://secure.avis.be/mijn-avis/reservering-wijzigen';

                break;

            case "Germany":
                return 'https://secure.avis.de/mein-avis/buchung-bearbeiten';

                break;

            case "France":
                return 'https://secure.avis.fr/votre-avis/g%C3%A9rer-ma-r%C3%A9servation';

                break;

            case "Finland":
                return 'https://secure.avis.fi/oma-avis/hallinnoi-varaustasi';

                break;

            case 'Italy':
                return 'https://secure.avisautonoleggio.it/avis-per-te/gestisci-prenotazione';

                break;

            case 'Norway':
                return 'https://secure.avis.no/din-avis/manage-booking';

                break;

            case 'Spain':
                return 'https://secure.avis.es/tu-avis/gestionar-reserva';

                break;

            case 'Sweden':
                return 'https://secure.avis.se/ditt-avis/hantera-bokning';

                break;

            case 'Switzerland':
                return 'https://secure.avis.ch/mein-avis/buchung-bearbeiten';

                break;

            case "UK":
                return 'https://secure.avis.co.uk/your-avis/manage-booking';

                break;

            case "Australia":
                return "https://www.avis.com.au/en/reservation/view-modify-cancel";

                break;

            case "USA":
            default:
                return "https://www.avis.com/en/reservation/view-modify-cancel";
        }
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $arFields['Region'] = $this->checkRegionSelection($arFields['Region']);
        $this->logger->debug('Region => ' . $arFields['Region']);

        switch ($arFields['Region']) {
            case "Germany":
            case 'Finland':
            case 'France':
            case 'Italy':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case "UK":
                if ($arFields['Region'] == 'Norway') {
                    $this->sendNotification("avis (Norway) - refs #17862. New valid account // RR");
                }

                if (!isset($arFields["EmailAddress"]) || empty($arFields["EmailAddress"])) {
                    return 'Field "Email Address" is required';
                }
                $this->http->GetURL($this->ConfirmationNumberURL($arFields));
                // english and german actions respectively
                $formFilter = "//form[
                    contains(@action, 'manage-booking') or
                    contains(@action, 'ihre-buchung') or
                    contains(@action, 'buchung-bearbeiten') or
                    contains(@action, 'gestionar-reserva') or
                    contains(@action, 'gestisci-prenotazione') or
                    contains(@action, 'reservering-wijzigen') or
                    contains(@action, 'hallinnoi-varaustasi') or
                    contains(@action, 'gérer-ma-réservation') or
                    contains(@action, 'hantera-bokning')
                ]";

                if (!$this->http->ParseForm(null, 1, true, $formFilter)) {
                    $this->sendNotification("avis - failed to retrieve itinerary by conf #", 'all', true,
                        "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}<br/>Region: {$arFields["Region"]}");

                    return null;
                }
                $this->http->Form['InputBookingNumber'] = $arFields["ConfNo"];
                $this->http->Form['InputSurname'] = $arFields["LastName"];
                $this->http->Form['InputEmailAddress'] = $arFields["EmailAddress"];
                unset($this->http->Form['require-login']);
                unset($this->http->Form['login-hidtext']);

                if (!$this->http->PostForm()) {
                    return null;
                }
                $redirect = $this->http->FindPreg("/window.location='(.+?)'/");

                if ($redirect) {
                    $this->http->NormalizeURL($redirect);
                    $this->http->GetURL($redirect);
                }// if ($redirect)

                if ($res = $this->http->FindSingleNode("//span[@class='error']")) {
                    return $res;
                }
                $result = $this->CheckRentalDetails($arFields["ConfNo"], $arFields["LastName"], $arFields["Region"]);

                if (is_string($result)) {
                    return $result;
                }

                $it = [$result];

                break;

            case "USA":
            case "Australia":
            default:
                if ($arFields['Region'] == "Australia") {
                    $this->domain = 'com.au';
                }

                $this->http->GetURL($this->ConfirmationNumberURL($arFields));
                $this->apiurl = $this->http->FindPreg("/var apiurl = \"([^\"]+)/");

                $avis_xsrf = $this->avis_xsrf();

                if (!$this->apiurl || !$avis_xsrf) {
                    // refs #15704
                    if ($this->http->Response['code'] == 403 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
                        && !empty($this->AccountFields['Partner']) && $this->AccountFields['Partner'] != 'awardwallet') {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;
                        $this->sendNotification('check retry // MI');

                        throw new CheckRetryNeededException(2, 10);
                    }// if ($this->http->Response['code'] == 403 && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
                }

                $result = $this->CheckRentalDetails($arFields["ConfNo"], $arFields["LastName"], $arFields["Region"]);

                if (is_string($result)) {
                    return $result;
                }

                $it = [$result];

                break;
        }

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"       => [
                "Caption"         => "Confirmation #",
                "Type"            => "string",
                "Size"            => 20,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Required"        => true,
            ],
            "LastName"     => [
                "Type"            => "string",
                "Size"            => 40,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Value"           => $this->GetUserField('LastName'),
                "Required"        => true,
            ],
            "EmailAddress" => [
                "Caption"         => "Email Address",
                "Type"            => "string",
                "Size"            => 90,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Required"        => false,
                "Note"            => "required for non U.S./Australia citizens", /*review*/
            ],
            "Region"       => [
                "Options"         => $this->regionOptions,
                "Type"            => "string",
                "Size"            => 7,
                "InputAttributes" => "style=\"width: 300px;\"",
                "Value"           => "USA",
                "Required"        => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Pick-up date"     => "PostingDate",
            "Pick-up location" => "Description",
            "Rental #"         => "Info",
            'Checkout Date'    => "Info.Date",
            'Country'          => 'Info',
            'Voucher Number'   => 'Info',
            'Valid'            => 'Info',
            'EUR Value'        => 'Info',
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;

        switch ($this->AccountFields['Login2']) {
            case "USA":
            case "Australia":
                $this->logger->notice("[Page: {$page}]");
                $this->http->GetURL("https://www.avis.{$this->domain}{$this->apiurl}/profile/past-rentals", $this->headers);

                if ($this->http->FindPreg("/^\{\}$/ims")) {
                    $this->logger->notice("No Past Rentals");

                    return $result;
                }
                $startIndex = sizeof($result);
                $result = array_merge($result, $this->ParsePageHistoryUSA($startIndex, $startDate));

                break;
//            case "Finland":
//                $toDate = date('j/m/Y');
//                $fromDate = date('j/m/Y', strtotime("-5 year"));
//                $this->http->GetURL("https://www.avisloyalty.eu/Statement/GetRentalsBetweenDates?FromDate={$fromDate}&ToDate={$toDate}&X-Requested-With=XMLHttpRequest");
//
//                $this->logger->notice("[Page: {$page}]");
//                $startIndex = sizeof($result);
//                $result = array_merge($result, $this->ParsePageHistoryFinland($startIndex, $startDate));
//                break;
            default:
                $host = [
                    'Belgium'     => 'secure.avis.be',
                    'Germany'     => 'secure.avis.de',
                    'Finland'     => 'secure.avis.fi',
                    'France'      => 'secure.avis.fr',
                    'Italy'       => 'secure.avisautonoleggio.it',
                    'Norway'      => 'secure.avis.no',
                    'Spain'       => 'secure.avis.es',
                    'Sweden'      => 'secure.avis.se',
                    'Switzerland' => 'secure.avis.ch',
                    'UK'          => 'secure.avis.co.uk',
                ];
                $hostUrl = $host[$this->AccountFields['Login2']] ?? $host['UK'];
                $this->http->GetURL("https://" . $hostUrl . "/JsonProviderServlet/?requestType=statementService&_=" . time() . date("B"));

                $this->logger->notice("[Page: {$page}]");
                $startIndex = sizeof($result);
                $result = array_merge($result, $this->ParsePageHistoryUK($startIndex, $startDate));

                break;
        }

        $this->getTime($startTimer);

        return $result;
    }

    public function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->saveScreenshots = true;
            $selenium->seleniumOptions->recordRequests = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.avis.{$this->domain}/en/home");

            $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'res-login-profile']"), 3);
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('try { document.querySelector(\'a[id = "res-login-profile"]\').click(); } catch (e) {}');

            sleep(2);

            $formXpath = "//form[@name = 'loginForm']";
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//input[@id = 'username']"), 3);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//input[@id = 'password']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//button[@id = 'res-login-profile']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $button->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'mainErrorText')]"), 10);
            $this->savePageToLogs($selenium);

            if ($res) {
                try {
                    $this->DebugInfo = $res->getText();
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
            }

            /** @var SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
//                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (strpos($xhr->request->getUri(), 'profile/login') !== false) {
                    $this->logger->debug("xhr response {$n} body: " . htmlspecialchars(json_encode($xhr->response->getBody())));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    private function checkRegionSelection($region)
    {
        $this->logger->notice(__METHOD__);

        if (empty($region) || !in_array($region, array_flip($this->regionOptions))) {
            $region = 'USA';
        }

        if ($region == "Australia") {
            $this->domain = 'com.au';
        }

        if (in_array($region, ['USA', 'Australia'])) {
            $this->http->setHttp2(true);
            /*
            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
            */
//            $this->http->setUserAgent("AwardWallet Service. Contact us at awardwallet.com/contact");
            $this->setProxyGoProxies();
        }

        return $region;
    }

    // get cookies
    private function avis_xsrf()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.avis.{$this->domain}/webapi/init", $this->headers);
        $this->http->RetryCount = 2;
        $this->headers["avis_xsrf"] = $this->http->getCookieByName("AVIS_XSRF") ?? 'undefined';
        $digitalToken = $this->http->Response['headers']['digital-token'] ?? null;

        $this->logger->debug("avis_xsrf: {$this->headers["avis_xsrf"]}");
        $this->logger->debug("digital-token: {$digitalToken}");

        if (!$digitalToken) {
            $this->logger->error("digital-token not found");

            return false;
        }
        $this->headers['digital-token'] = $digitalToken;

        return $this->headers["avis_xsrf"];
    }

    private function parseCaptcha($key, $action = null, $score = 0.7)
    {
        $this->logger->notice(__METHOD__);
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

        // https://www.avis.com/etc/designs/platform/clientlib.min.22.1.1-RELEASE.js
        if ($action) {
//            $parameters += [
//                "invisible" => 1,
//                "version"   => "enterprise",
//                "action"    => "login",
//                "min_score" => 0.3,
//            ];

            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $this->http->currentUrl(),
                "websiteKey"   => $key,
                "minScore"     => $score,
                "pageAction"   => "login",
                "isEnterprise" => true,
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters, true, 3, 1);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("
                //div[contains(text(), 'Sorry - our website is currently undergoing essential maintenance')]
                | //p[contains(text(), 'The site is currently undergoing maintenance and will be back up shortly')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/background-image:url\('\/maintenance\/maintenance1\/Avis.PNG'\);/")) {
            throw new CheckException("Sorry, the site is undergoing some maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        // The page you have requested is no longer available
        if ($message = $this->http->FindSingleNode("//td[@class = 'exception_msg']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We're upgrading Avis.com.
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re upgrading Avis.com.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/(The page you have requested is no longer available)/ims')) {
            throw new CheckException("The page you have requested is no longer available.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re upgrading our website")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, the site is undergoing some maintenance
        if ($message = $this->http->FindSingleNode('//h2[contains(text(), "Sorry, the site is undergoing some maintenance")]', null, true, "/(Sorry, the site is undergoing some maintenance)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error 404
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Error 404')]")
            // Service Unavailable
            || $this->http->FindSingleNode("//h2[contains(text(), 'Service Unavailable')]")
            // Sorry, our website is having issues but don't let that stop you ...
            || $this->http->FindSingleNode('//h2[contains(text(), "Sorry, our website is having issues but don\'t let that stop you ...")]')
            // Service Temporarily Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '500 Internal Server Error')]")
            // Service Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // HTTP Status 500 - URLDecoder: Illegal hex characters in escape (%) pattern - For input string: "PK"
            || $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500 - URLDecoder: Illegal hex characters in escape')]")
            // The server is temporarily unable to service your request. Please try again later.
            || $this->http->FindPreg("/The server is temporarily unable to service your request\.\s*Please try again\s*later\./ims")
            // No backend server available for connection: timed out after
            || ($this->http->FindPreg("/No backend server available for connection: timed out after/ims") && $this->http->Response['code'] == 503)
            // infinite redirects
            || $this->http->currentUrl() == 'https://' . $this->http->getCurrentHost() . '/avis-UK/en_GB/error-500'
            || ($this->http->currentUrl() == 'http://' . $this->http->getCurrentHost() . ':80/drive-avis/car-hire-services'
                && $this->http->Response['code'] == 404)) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // UK
        if (
            !in_array($this->AccountFields['Login2'], ['USA', 'Australia'])
            && (
                ($message = $this->http->FindSingleNode('//title[contains(text(), "We\'re sorry, but something went wrong")]'))
                || $this->http->Response['code'] == 400
            )
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Invalid credentials
        if ($this->AccountFields['Login2'] == 'Belgium' && $this->AccountFields["Pass"] == '**********') {
            throw new CheckException("Uw e-mailadres en wachtwoord komen niet overeen. Controleer ze en probeer het opnieuw.", ACCOUNT_INVALID_PASSWORD);
        }

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");
        // debug
        if (strtolower($currentUrl) == 'https://' . $this->http->getCurrentHost() . '/avisPreferred/' && empty($this->http->Response['body'])) {
            sleep(3);
            $this->http->GetURL("https://{$this->http->getCurrentHost()}/avisPreferred/");

            if (false !== strpos($this->http->Response['body'], 'your-avis-panel') || $this->http->FindSingleNode('//div[contains(@class, "your-avis-panel")]')) {
                return true;
            }
//            throw new CheckRetryNeededException(3, 7);
        }// if ($currentUrl == 'https://secure.avis.co.uk/avisPreferred/' && empty($this->http->Response['body']))

        // maintenance
        if ($this->http->Response['code'] == 503 && !strstr($this->http->getCurrentHost(), 'www.avis.com')) {
            throw new CheckException("Sorry, the site is undergoing some maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        // Switzerland
        // workaround provider bug
        if (
            $this->http->Response['code'] == 404
            && $this->http->FindSingleNode('//span[contains(normalize-space(),"Die gesuchte Seite ist umgezogen.")]')
            && $this->AccountFields['Login2'] == "Switzerland"
            && $this->http->currentUrl() == 'https://secure.avis.ch/de/avisPreferred/'
        ) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://secure.avis.ch/avisPreferred");
            $this->http->RetryCount = 2;

            if (strpos($this->http->Response['body'], 'your-avis-panel') !== false
                || $this->http->FindSingleNode('//div[contains(@class, "your-avis-panel")]')) {
                return true;
            }
        }

        $urlBooking = [
            'https://secure.avis.be/mijn-avis/reservering-wijzigen'                => "be",
            'https://secure.avis.de/mein-avis/buchung-bearbeiten'                  => "de",
            'https://secure.avis.fr/votre-avis/g%C3%A9rer-ma-r%C3%A9servation'     => "fr",
            'https://secure.avis.fi/oma-avis/hallinnoi-varaustasi'                 => "fi",
            'https://secure.avisautonoleggio.it/avis-per-te/gestisci-prenotazione' => "it",
            'https://secure.avis.no/din-avis/manage-booking'                       => "no",
            'https://secure.avis.es/tu-avis/gestionar-reserva'                     => "es",
            'https://secure.avis.se/ditt-avis/hantera-bokning'                     => "se",
            'https://secure.avis.ch/mein-avis/buchung-bearbeiten'                  => "cn",
            'https://www.avis.co.uk/drive-avis/car-hire-services'                  => "co-uk",
        ];

        if (
            $this->http->Response['code'] == 404
            && isset($urlBooking[$this->http->currentUrl()])
        ) {
            $this->http->RetryCount = 0;
            $url = "/data/{$urlBooking[$this->http->currentUrl()]}.json";
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $this->http->RetryCount = 2;

            if ($message = $this->http->FindPreg('/"title": "((?:Es tut uns leid, unsere Seite ist aufgrund von Wartungsarbeiten gerade nicht erreichbar. Doch lassen Sie sich dadurch nicht aufhalten.|Sorry, the site is undergoing some maintenance, but don\'t let that stop you...))",/')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    private function handleRedirect()
    {
        $this->logger->notice(__METHOD__);
        // js redirect
        $location = $this->http->FindPreg("/window\.location='([^\']+)'/");

        if ($location && ($v = $this->http->FindPreg("/var\s*v\s*=\s*(\d+)\s*\*\s*3\.1415926535898/"))) {
            $this->logger->debug("redirect to -> {$location}");
            $cookie = $this->http->FindPreg("/document.cookie = \"([^\=]+)=\"\+v\+\"/");

            if ($cookie) {
                $this->http->setCookie($cookie, floor($v * 3.1415926535898));
                $this->http->GetURL($location);

                return true;
            }// if ($cookie)
        }// if ($location && ($v = $this->http->FindPreg("/var v = (\d+) * 3\.1415926535898/")))

        return false;
    }

    // refs #12752
    private function setExpDateUSA(): void
    {
        $this->logger->notice("setExpDateUSA");
        $this->logger->info("Expiration date", ['Header' => 3]);
        $balance = str_replace(',', '', $this->Balance);
        $transactionBalance = 0;
        $transactionsInfo = [];

        // Step # 1

        // Table "Avis Preferred Points Summary"
        $this->http->GetURL("https://www.avis.{$this->domain}{$this->apiurl}/profile/loyalty-details", $this->headers);

        if ($this->http->Response['code'] !== 200) {
            $this->sendNotification('check exp date // MI');

            return;
        }
        $transactions = $this->http->JsonLog(null, 0);

        if (!empty($transactions->loyaltySummaryList) && is_array($transactions->loyaltySummaryList)) {
            $transactionRows = count($transactions->loyaltySummaryList);
            $this->logger->debug("Total {$transactionRows} transactions were found");
            $lastTransaction = null;

            foreach ($transactions->loyaltySummaryList as $transaction) {
                $dateStr = preg_replace("/T.+/", '', $transaction->interactionDate);
                $dateStr = str_replace("-", "/", $dateStr);
                $postDate = strtotime($dateStr);

                if (!isset($lastTransaction) || $postDate > $lastTransaction) {
                    $lastTransaction = $postDate;
                    $this->logger->debug("set LastTransaction -> {$postDate} ");
                }// if (!isset($lastTransaction) || $postDate < $lastTransaction)

                if ($transaction->actionType == "EARN" && isset($transaction->points)) {
                    $transactionBalance += $transaction->points;
                    $this->logger->debug("Date: {$dateStr} ($postDate) / Points: {$transactionBalance}");
                    $transactionsInfo[] = [
                        "date"   => $postDate,
                        "points" => $transaction->points,
                    ];
                }// if ($transaction->actionType == "EARN" && isset($transaction->points))
            }// foreach ($transactions->loyaltySummaryList as $transaction)
        }// if (!empty($transactions->loyaltySummaryList) && is_array($transactions->loyaltySummaryList))
        elseif ($this->http->FindPreg("/^\[\]$/ims")) {
            $this->logger->notice("No Activity");
        }
        $this->logger->debug("Balance: {$balance} == Transaction Balance: {$transactionBalance}");

        // Step # 2

        if ($balance == $transactionBalance) {
//            $this->http->Log("<pre>".var_export($transactionsInfo, true)."</pre>", false);
            // Sort by date
            usort($transactionsInfo, function ($a, $b) {
                $key = 'date';

                return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
            });
//            $this->http->Log("<pre>".var_export($transactionsInfo, true)."</pre>", false);
            // Step # 2.a

            $count = count($transactionsInfo);

            for ($i = 0; $i < $count; $i++) {
                $balance -= $transactionsInfo[$i]['points'];
                $date = $transactionsInfo[$i]['date'];
                $this->logger->debug("Date {$date} - {$transactionsInfo[$i]['points']} ");
                $this->logger->debug("Balance: $balance");

                if ($balance <= 0) {
                    $this->logger->debug("Expiration Date " . var_export(date("m/d/Y", strtotime("+5 year", $date)), true)
                        . " - " . var_export(strtotime("+5 year", $date), true));
                    // Earning Date     // refs #4936
                    $this->SetProperty("EarningDate", date("m/d/Y", $date));
                    // Expiration Date
                    $expA = strtotime("+5 year", $date);
                    // Expiring Balance
                    $balance += $transactionsInfo[$i]['points'];

                    for ($k = $i - 1; $k >= 0; $k--) {
                        if (isset($transactionsInfo[$k]['date']) && $transactionsInfo[$i]['date'] == $transactionsInfo[$k]['date']) {
                            $this->logger->debug("+ Date {$transactionsInfo[$k]['date']} - " . var_export(strtotime($transactionsInfo[$i]['date']), true) . " - {$transactionsInfo[$k]['points']}");
                            $balance += $transactionsInfo[$k]['points'];
                        }
                    }// for ($k = --$i; $k >= 0; $k--)
                    $expiringBalance = $balance;
                    $this->logger->debug("Possible Expiration A: {$expA} ");
                    $this->logger->debug("Possible Expiring Balance: {$expiringBalance}");

                    break;
                }// if ($balance <= 0)
            }// for ($i = 0; $i < $count; $i++)

            // Step # 2.b

            if (isset($lastTransaction)) {
                $expB = strtotime("+1 year", $lastTransaction);
                $this->logger->debug("Possible Expiration B: {$expB} ");
            }// if (isset($lastTransaction))

            // Step # 2.с

            if (isset($expA, $expB)) {
                $this->http->GetURL("https://www.avis.{$this->domain}{$this->apiurl}/profile/past-rentals", $this->headers);
                $pastRentals = $this->http->JsonLog(null, 0);
                // Table "Past Rentals"
                if (!empty($pastRentals->resSummaryList) && is_array($pastRentals->resSummaryList)) {
                    $countPastRentals = count($pastRentals->resSummaryList);
                    $this->logger->debug("Total {$countPastRentals} past rentals were found");
                    $lastRental = null;

                    foreach ($pastRentals->resSummaryList as $row) {
                        $dateStr = preg_replace("/T.+/", '', $row->pickDateTime);
                        $dateStr = str_replace("-", "/", $dateStr);
                        $postDate = strtotime($dateStr);

                        if (!isset($lastRental) || $postDate > $lastRental && !empty($row->confirmationNumber)) {
                            $lastRental = $postDate;
                            $this->logger->debug("set LastRental -> {$postDate} ");
                        }// if (!isset($lastRental) || $postDate < $lastRental && !empty($row->confirmationNumber))
                    }// foreach ($pastRentals as $row)
                }// if (!empty($pastRentals->resSummaryList) && is_array($pastRentals->resSummaryList))
                elseif ($this->http->FindPreg("/^\{\}$/ims")) {
                    $this->logger->notice("No Past Rentals");
                }

                if (isset($lastRental)) {
                    $expC = strtotime("+1 year", $lastRental);
                    $this->logger->debug("Possible Expiration C: {$expC} ");
                }// if (isset($lastTransaction))

                // Step # 3

                if (!isset($expC) || $expC < $expB) {
                    $expStepThree = $expB;
                    $this->logger->debug("[Step # 3] Possible Expiration from Step # 2.b: {$expStepThree} ");
                }// if (!isset($expC) || $expC < $expB)
                else {
                    $expStepThree = $expC;
                    $this->logger->debug("[Step # 3] Possible Expiration from Step # 2.c: {$expStepThree} ");
                }

                // Step # 4

                if ($expStepThree > $expA) {
                    $this->logger->debug("[Step # 4] Possible Expiration from Step # 2.a: {$expA} ");
                    $this->SetExpirationDate($expA);

                    if (isset($expiringBalance)) {
                        $this->SetProperty("ExpiringBalance", $expiringBalance);
                    }
                }// if ($expStepThree > $expA)
                else {
                    $this->logger->debug("[Step # 4] Possible Expiration from Step # 3: {$expStepThree} ");
                    $this->SetExpirationDate($expStepThree);
                }
            }// if (isset($expA, $expB))
        }// if ($balance == $transactionBalance)
    }

    private function getUpcomingInfoFromJson()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL(sprintf('https://%s/JsonProviderServlet/?requestType=customerBooking&bookingType=upcoming&size=false', $this->http->getCurrentHost()));
        $upcoming = $this->http->JsonLog(null, 3, true);

        if (!$upcoming) {
            $this->logger->notice('No upcoming reservations from json found');

            return null;
        }
        $links = [];
        $jsons = [];

        foreach ($upcoming as $up) {
            $bookingNum = $up['bookingNum'];
            $surName = $up['surName'];

            if ($bookingNum && $surName) {
                $links[] = sprintf('https://%s/yourBooking?InputBookingNumber=%s&InputSurname=%s&backToProfile=true',
                    $this->http->getCurrentHost(),
                    $bookingNum,
                    $surName
                );
                $jsons[] = $up;
            }// if ($bookingNum && $surName)
            else {
                $this->logger->notice('Upcoming json scheme has changed');
            }
        }// foreach ($upcoming as $up)

        return [$links, $jsons];
    }

    private function CheckRentalDetails($sConfNo = null, $sLastName = null, $region = "USA", $json = null)
    {
        $this->logger->notice(__METHOD__);
        /** @var CarRental $result */
        $result = [];

        switch ($region) {
            case 'Belgium':
            case "Germany":
            case 'Finland':
            case 'France':
            case 'Italy':
            case 'Norway':
            case 'Spain':
            case 'Sweden':
            case 'Switzerland':
            case "UK":
                $this->logger->debug(var_export($json, true), ['pre' => true]);
                $result['Kind'] = 'L';
                // Number
                $result["Number"] = $this->http->FindSingleNode("//p[@class = 'reference']");

                // empty booking reference on itinerary page
                if ($result["Number"] === '') {
                    $this->logger->notice("fixed empty booking reference on itinerary page");
                    $result["Number"] = $json['bookingNum'];
                }

                $this->logger->info('Parse Itinerary #' . $result["Number"], ['Header' => 3]);
                // PickupDatetime
                $pickupDatetime = null;

                if ($json) {
                    $this->logger->debug("PickupDatetime from JSON");
                    $pickupDatetime = DateTime::createFromFormat('d/m/Y-H:i', @$json['pickUpDate']);
                    $pickupDatetime = $pickupDatetime ? $pickupDatetime->getTimestamp() : null;
                }// if ($json)

                if (!$pickupDatetime) { // fallback if no date from json
                    $this->logger->debug("PickupDatetime from HTML");
                    $pickupDatetime = $this->http->FindSingleNode("//li[contains(@class, 'pick-up')]//span[@class = 'date']");
                    $pickupTime = $this->http->FindSingleNode("//li[contains(@class, 'pick-up')]//span[@class = 'station-time']/time");
                    $this->logger->debug("PickupDatetime: $pickupDatetime $pickupTime/ " . strtotime($pickupDatetime));
                    $pickupDatetime = strtotime($pickupDatetime);

                    if (!$pickupDatetime) {
                        $this->logger->debug('PickupDatetime: fail');
                        $day = $this->http->FindSingleNode("//li[contains(@class, 'pick-up')]//span[@class = 'day']");
                        $month = $this->http->FindSingleNode("//li[contains(@class, 'pick-up')]//abbr[@class = 'month']/@title");
                        $year = $this->http->FindSingleNode("//input[@id = 'date-from']/@value", null, true, '/F(\d{4})$/');
                        $pickupDatetime = sprintf('%s %s %s', $day, $month, $year);
                        $this->logger->debug("PickupDatetime: $pickupDatetime $pickupTime/ " . strtotime($pickupDatetime));
                        $pickupDatetime = strtotime($pickupDatetime);
                    }
                    $checkpointDate = strtotime("-2 month");

                    if ($pickupDatetime < $checkpointDate) {
                        $pickupDatetime = strtotime("+1 year", $pickupDatetime);
                    }
                    $this->logger->debug("PickupDatetime: " . date("m/d/Y", $pickupDatetime) . " " . $pickupTime);
                    $pickupDatetime = strtotime(date("m/d/Y", $pickupDatetime) . " " . $pickupTime);

                    if (!$pickupDatetime) {
                        $this->sendNotification("avis ({$region}), conf #$sConfNo. PickupDatetime not found");
                    }
                }// if (!$pickupDatetime)
                $result["PickupDatetime"] = $pickupDatetime;
                // PickupHours
                $result["PickupHours"] = $this->http->FindSingleNode("//li[contains(@class, 'pick-up')]//div[@class = 'details']/dl[@class = 'opening-times']//dd");
                // PickupLocation
                $result["PickupLocation"] = implode(' ',
                    $this->http->FindNodes("//li[contains(@class, 'pick-up')]//div[@class = 'details']/address//text()[normalize-space()!=''][not(contains(.,'Apt Madrid Pickup-dropoff'))]"));

                if (strpos($result["PickupLocation"], 'Return:') !== false) {
                    $list = $this->http->FindNodes("//li[contains(@class, 'pick-up')]//div[@class = 'details']/address//text()[string-length(normalize-space())>2][not(contains(.,'Return'))]");

                    if (count($list) === 4) {
                        $result["PickupLocation"] = trim(str_replace("Pickup:", '', implode(' ', $list)));
                    } elseif (strpos($result["PickupLocation"], 'Pickup:') !== false) {
                        $result["PickupLocation"] = $this->http->FindPreg("/Pickup:\s*(.+?)\s*Return:/", false,
                            $result["PickupLocation"]);
                    }
                }

                // DropoffDatetime
                $dropoffDatetime = null;

                if ($json) {
                    $this->logger->debug("DropoffDatetime from JSON");
                    $dropoffDatetime = DateTime::createFromFormat('d/m/Y-H:i', @$json['dropOffDate']);
                    $dropoffDatetime = $dropoffDatetime ? $dropoffDatetime->getTimestamp() : null;
                }// if ($json)

                if (!$dropoffDatetime) {
                    $this->logger->debug("DropoffDatetime from HTML");
                    $dropoffDatetime = $this->http->FindSingleNode("//li[contains(@class, 'drop-off')]//span[@class = 'date']");
                    $dropoffTime = $this->http->FindSingleNode("//li[contains(@class, 'drop-off')]//span[@class = 'station-time']/time");
                    $this->logger->debug("DropoffDatetime: $dropoffDatetime $dropoffTime / " . strtotime($dropoffDatetime));
                    $dropoffDatetime = strtotime($dropoffDatetime);

                    if (!$dropoffDatetime) {
                        $this->logger->debug('DropoffDatetime: fail');
                        $day = $this->http->FindSingleNode("//li[contains(@class, 'drop-off')]//span[@class = 'day']");
                        $month = $this->http->FindSingleNode("//li[contains(@class, 'drop-off')]//abbr[@class = 'month']/@title");
                        $year = $this->http->FindSingleNode("//input[@id = 'date-to']/@value", null, true, '/F(\d{4})$/');
                        $dropoffDatetime = sprintf('%s %s', $day, $month);
                        $this->logger->debug("DropoffDatetime: $dropoffDatetime $pickupTime/ " . strtotime($dropoffDatetime));
                        $dropoffDatetime = strtotime($dropoffDatetime);
                    }

                    if ($dropoffDatetime < $checkpointDate) {
                        $dropoffDatetime = strtotime("+1 year", $dropoffDatetime);
                    }
                    $this->logger->debug("DropoffDatetime: " . date("m/d/Y", $dropoffDatetime) . " " . $dropoffTime);
                    $dropoffDatetime = strtotime(date("m/d/Y", $dropoffDatetime) . " " . $dropoffTime);

                    if (!$dropoffDatetime) {
                        $this->sendNotification("avis ({$region}), conf #$sConfNo. PickupDatetime not found");
                    }
                }// if (!$dropoffDatetime)
                $result["DropoffDatetime"] = $dropoffDatetime;
                // DropoffHours
                $result["DropoffHours"] = $this->http->FindSingleNode("//li[contains(@class, 'drop-off')]//div[@class = 'details']/dl[@class = 'opening-times']//dd");
                // DropoffLocation
                $result["DropoffLocation"] = implode(' ',
                    $this->http->FindNodes("//li[contains(@class, 'drop-off')]//div[@class = 'details']/address//text()[normalize-space()!=''][not(contains(.,'Apt Madrid Pickup-dropoff'))]"));

                if (strpos($result["DropoffLocation"], 'Return:') !== false) {
                    $result["DropoffLocation"] = $this->http->FindPreg("/Return:\s*(.+?)\s*$/", false,
                        $result["DropoffLocation"]);
                }

                // TODO: hard code
                $result["DropoffLocation"] = str_replace(['Bcn T1 & T2 Pick Up Dropoff,', 'Bcn T2 09h To 21h.bus T2 To T1,'], '', $result["DropoffLocation"]);

                // CarType
                $result["CarType"] = $this->http->FindSingleNode("//h3[@class = 'vehicle-heading']/em");
                // CarModel
                $result["CarModel"] = $this->http->FindSingleNode("//h3[@class = 'vehicle-heading']/strong")
                    . ' ' . $this->http->FindSingleNode("//h3[@class = 'vehicle-heading']/span");
                // CarImageUrl
                $result["CarImageUrl"] = $this->http->FindSingleNode("//div[contains(@class, 'vehicle-image')]/img/@data-large");

                if (isset($result["CarImageUrl"])) {
                    $this->http->NormalizeURL($result["CarImageUrl"]);
                }
                // TotalCharge
                $totalText = $this->http->FindSingleNode("//span[@class = 'total-amount']");

                if (!$totalText) {
                    $totalText = $this->http->FindSingleNode("//div[@class = 'totals']/span");
                }
                $totalStr = $this->http->FindPreg('/([\d.,\s]+)/', false, $totalText);

                if (in_array($region, ['Italy', 'Germany', 'Belgium', 'Spain', 'France', 'Sweden'])) {
                    $result["TotalCharge"] = PriceHelper::cost($totalStr, '.', ',');
                } elseif (in_array($region, ['Norway'])) {
                    $result["TotalCharge"] = PriceHelper::cost($totalStr, ' ', ',');
                } else {
                    $result["TotalCharge"] = PriceHelper::cost($totalStr);
                }

                if ($totalText && !$result['TotalCharge']) {
                    $this->sendNotification('check total // MI');
                }
                // Currency
                $result["Currency"] = $this->currency($totalText);

                break;

            case "USA":
            case "Australia":
            default:
                $this->logger->info("Parse Itinerary #{$sConfNo}", ['Header' => 3]);
                $data = [
                    "confirmationNumber" => $sConfNo,
                    "lastName"           => $sLastName,
                ];
                $header = array_merge($this->headers, [
                    "Content-Type"         => "application/json",
                ]);
                $this->http->PostURL("https://www.avis.{$this->domain}{$this->apiurl}/reservation/view", json_encode($data), $header);

                if ($this->http->Response['code'] == 403) {
                    sleep(3);
                    $this->http->PostURL("https://www.avis.{$this->domain}{$this->apiurl}/reservation/view", json_encode($data), $header);

                    if ($this->http->Response['code'] == 403) {
                        sleep(6);

                        return [];
                    }
                }
                $response = $this->http->JsonLog(null, 0, true);
                $reservationSummary = ArrayVal($response, 'reservationSummary');

                $result['Kind'] = 'L';
                // Number
                $result["Number"] = ArrayVal($reservationSummary, 'confirmationNumber', null);

                if (!$result["Number"]) {
                    // The information provided does not match our records. Please ensure that the information you have entered is correct and try again.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(150005|05214)\"/")) {
                        return "The information provided does not match our records. Please ensure that the information you have entered is correct and try again.";
                    }
                    // Reservation Number is not associated with your wizard profile
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(150006)\"/")) {
                        return "Reservation Number is not associated with your wizard profile";
                    }
                    // We are Sorry, the site has not properly responded to your request. If the problem persists, please contact Avis and provide PROBLEM CODE.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(1005)\"/")) {
                        return "We are Sorry, the site has not properly responded to your request. If the problem persists, please contact Avis and provide PROBLEM CODE.";
                    }
                    // Coupon Number ntered cannot be used for these dates.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(1004)\"/")) {
                        return "Coupon Number ntered cannot be used for these dates.";
                    }
                    // Unable to convert from Source Currency
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(1000)\"/")) {
                        return "Unable to convert from Source Currency";
                    }
                    // There are no locations available for your Return address.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(226)\"/")) {
                        return "There are no locations available for your Return address."; // some information can be scraped from main json if it needed
                    }
                    // We could not process your request due to a temporary technical problem, please try again.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(80010)\"/")) {
                        return "We could not process your request due to a temporary technical problem, please try again."; // some information can be scraped from main json if it needed
                    }
                    // There are no locations available for your Pick-up address.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(225)\"/")) {
                        return "There are no locations available for your Pick-up address.";
                    }
                    // Sorry! No Avis locations are available in address provided.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(227)\"/")) {
                        return "Sorry! No Avis locations are available in address provided.";
                    }
                    // We are unable to process your request at this time. Please return to Homepage and start your process again or use Worldwide Phone Number List to find your Avis Customer Phone Service telephone number.
                    // We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us .<0999> Reference Number <12-1-1510554140398-84140-C>
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(0999)\"/")) {
                        return "We are unable to process your request at this time. Please return to Homepage and start your process again or use Worldwide Phone Number List to find your Avis Customer Phone Service telephone number.";
                    }
                    // We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us.
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(40018)\"/")) {
                        return "We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us.";
                    }
                    // We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us .<319> Reference Number <02-UN-1571679259234-41659-cwdc>
                    if ($this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(319)\"/")) {
                        return "We are sorry, the site has not properly responded to your request. Please try again. If the problem persists, please Contact Us .<319>";
                    }

                    if ($code = $this->http->FindPreg("/\"type\":\"ERROR\",\"code\":\"(\d+)\"/")) {
                        $this->sendNotification("USA - #{$sConfNo}. Please check error code: {$code}");
                    }
                }// if (!$result["Number"])

                // TotalCharge
                $rateSummary = ArrayVal($reservationSummary, 'rateSummary');
                $result['TotalCharge'] = ArrayVal($rateSummary, 'estimatedTotal');
                // BaseFare
                $result['BaseFare'] = ArrayVal($rateSummary, 'baseRate');
                // Currency
                $result['Currency'] = ArrayVal($rateSummary, 'currencyCode');
                // TotalTaxAmount
                $result['TotalTaxAmount'] = ArrayVal($rateSummary, 'totalTax');
                // Fees
                $fees = ArrayVal($rateSummary, 'surcharges', []);
                $this->logger->debug("Total " . count($fees) . " fees nodes were found");

                foreach ($fees as $fee) {
                    $result['Fees'][] = [
                        'Name'   => ArrayVal($fee, 'name'),
                        'Charge' => ArrayVal($fee, 'totalSurchargePrice'),
                    ];
                }
                // PickupLocation
                $pickLoc = ArrayVal($reservationSummary, 'pickLoc');
                $result['PickupLocation'] = $this->getAddress(ArrayVal($pickLoc, 'address'));
                $result['PickupPhone'] = ArrayVal($pickLoc, 'phoneNumber');
                $result['PickupHours'] = ArrayVal($pickLoc, 'hoursOfOperation');
                // PickupDatetime
                $result['PickupDatetime'] = ArrayVal($reservationSummary, 'pickDate');
                $pickupTime = ArrayVal($reservationSummary, 'pickTime');
                $this->logger->debug("PickupDatetime -> {$result['PickupDatetime']} / {$pickupTime}");
                $result['PickupDatetime'] = strtotime($result['PickupDatetime'] . ' ' . $pickupTime, false);
                // DropoffLocation
                $dropLoc = ArrayVal($reservationSummary, 'dropLoc');
                $result['DropoffLocation'] = $this->getAddress(ArrayVal($dropLoc, 'address'));
                $result['DropoffPhone'] = ArrayVal($dropLoc, 'phoneNumber');
                $result['DropoffHours'] = ArrayVal($dropLoc, 'hoursOfOperation');

                // DropoffDatetime
                $result['DropoffDatetime'] = ArrayVal($reservationSummary, 'dropDate');
                $dropoffTime = ArrayVal($reservationSummary, 'dropTime');
                $this->logger->debug("DropoffDatetime -> {$result['DropoffDatetime']} / {$dropoffTime}");
                $result['DropoffDatetime'] = strtotime($result['DropoffDatetime'] . ' ' . $dropoffTime, false);

                if (strstr($result['DropoffLocation'], 'Same as Pick-up')) {
                    $this->logger->notice("Correcting DropoffLocation, DropoffHours, DropoffPhone");
                    $result['DropoffLocation'] = $result['PickupLocation'];
                    $result['DropoffHours'] = $result['PickupHours'];
                    $result['DropoffPhone'] = $result['PickupPhone'];
                }
                $vehicle = ArrayVal($reservationSummary, 'vehicle');
                // CarType
                $result['CarType'] = ArrayVal($vehicle, 'carGroup');
                // CarModel
                $result['CarModel'] = trim(ArrayVal($vehicle, 'makeModel')) ?: null;
                // CarImageUrl
                $result['CarImageUrl'] = "https://www.avis.{$this->domain}/content/dam/cars/xl/" . ArrayVal($vehicle, 'makeYr') . "/" . ArrayVal($vehicle, 'makeNme') . "/" . ArrayVal($vehicle, 'image');
                $this->http->NormalizeURL($result['CarImageUrl']);

                foreach (['PickupLocation', 'DropoffLocation'] as $index) {
                    if (isset($result[$index])) {
                        $result[$index] = str_ireplace('View Airport Map', '', $result[$index]);
                    }
                }
                // RenterName
                $personalInfo = $reservationSummary['personalInfo'] ?? [];
                $firstName = $personalInfo['firstName']['value'] ?? '';
                $lastName = $personalInfo['lastName']['value'] ?? '';
                $result['RenterName'] = trim(beautifulName("{$firstName} {$lastName}"));

                break;
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function redirectJavaScript()
    {
        $this->logger->notice(__METHOD__);
        // js redirect
        $location = $this->http->FindPreg("/window\.location='([^\']+)'/");

        if ($location && ($v = $this->http->FindPreg("/var\s*v\s*=\s*(\d+)\s*\*\s*3\.1415926535898/"))) {
            $this->logger->debug("redirect to -> {$location}");
            $cookie = $this->http->FindPreg("/document.cookie = \"([^\=]+)=\"\+v\+\"/");

            if ($cookie) {
                $this->http->setCookie($cookie, floor($v));
                $this->http->GetURL($location);
            }
        }
    }

    private function ParsePageHistoryFinland($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $nodes = $this->http->XPath->query("//div[@id = 'ltGridStatementRentals']/table//tr[td]");
        $this->logger->debug("Total {$nodes->length} history items were found");

        foreach ($nodes as $node) {
            $dateStr = $this->http->FindSingleNode("td[2]", $node);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Pick-up date'] = $postDate;
            $result[$startIndex]['Rental #'] = $this->http->FindSingleNode("td[1]", $node);
            $result[$startIndex]['Checkout Date'] = strtotime($this->http->FindSingleNode("td[3]", $node));
            $result[$startIndex]['Pick-up location'] = $this->http->FindSingleNode("td[4]", $node);
            $result[$startIndex]['Country'] = $this->http->FindSingleNode("td[5]", $node);
            $result[$startIndex]['Voucher Number'] = $this->http->FindSingleNode("td[6]", $node);
            $result[$startIndex]['Valid'] = $this->http->FindSingleNode("td[7]", $node);
            $result[$startIndex]['EUR Value'] = $this->http->FindSingleNode("td[8]", $node);
            $startIndex++;
        }// foreach ($response->rentals as $row)

        return $result;
    }

    private function ParsePageHistoryUK($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $response = json_decode($this->http->Response['body']);

        if (!isset($response->rentals)) {
            $this->logger->debug(var_export($response, true), ["pre" => true]);
        } else {
            $this->logger->debug("Found " . count($response->rentals) . " items");

            foreach ($response->rentals as $row) {
                $dateStr = $row->transaction_date;
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }// if (isset($startDate) && $postDate < $startDate)
                $result[$startIndex]['Pick-up date'] = $postDate;
                $result[$startIndex]['Rental #'] = $row->reference_number;
                $result[$startIndex]['Checkout Date'] = strtotime($row->checkout_date);
                $result[$startIndex]['Pick-up location'] = $row->location;
                $result[$startIndex]['Country'] = $row->country;
                $result[$startIndex]['Voucher Number'] = $row->voucher_number;
                $result[$startIndex]['Valid'] = $row->valid_for_upgrade;
                $result[$startIndex]['EUR Value'] = $row->eur_value;
                $startIndex++;
            }// foreach ($response->rentals as $row)
        }

        return $result;
    }

    private function ParsePageHistoryUSA($startIndex, $startDate)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $history = $this->http->JsonLog(null, 0);
        // Table "Past Rentals"
        if (empty($history->resSummaryList) || !is_array($history->resSummaryList)) {
            return $result;
        }

        $historyRows = count($history->resSummaryList);
        $this->logger->debug("Total {$historyRows} history items were found");

        foreach ($history->resSummaryList as $row) {
            $dateStr = $row->pickDateTime;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Pick-up date'] = $postDate;
            $result[$startIndex]['Rental #'] = $row->confirmationNumber ?? null;
            $result[$startIndex]['Checkout Date'] = strtotime($row->dropDateTime);

            if (isset($row->pickLoc->name)) {
                $result[$startIndex]['Pick-up location'] = $row->pickLoc->name;
            }

            if (isset($row->pickLoc->locationCode)) {
                $result[$startIndex]['Pick-up location'] .= ', ' . $row->pickLoc->locationCode;
            }

            if (isset($row->pickLoc->address->country)) {
                $result[$startIndex]['Country'] = $row->pickLoc->address->country;
            }

            $startIndex++;
        }

        return $result;
    }

    private function getAddress($address): string
    {
        $location = ArrayVal($address, 'address1');

        if (ArrayVal($address, 'address2', null)) {
            $location .= ', ' . ArrayVal($address, 'address2');
        }

        if (ArrayVal($address, 'city', null)) {
            $location .= ', ' . ArrayVal($address, 'city');
        }

        if (ArrayVal($address, 'state', null)) {
            $location .= ', ' . ArrayVal($address, 'state');
        }

        if (ArrayVal($address, 'zipCode', null)) {
            $location .= ' ' . ArrayVal($address, 'zipCode');
        }

        if (ArrayVal($address, 'country', null)) {
            $location .= ', ' . ArrayVal($address, 'country');
        }

        return $location;
    }
}
