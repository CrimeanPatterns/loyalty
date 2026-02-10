<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

// refs #5911
class TAccountCheckerAirbnb extends TAccountChecker
{
    use ProxyList;
    use PriceTools;
    use SeleniumCheckerHelper;

    private const API_KEY = 'd306zoyjsyarp7ifhu67rjxn52tv0t20';
    private $csrfToken;
    private $key;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

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

    public static function GetAccountChecker($accountInfo)
    {
        //if ($accountInfo["Login"] == 'iormark@ya.ru') {
        require_once __DIR__ . "/TAccountCheckerAirbnbSelenium.php";

        return new TAccountCheckerAirbnbSelenium();
        //}

        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(5);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }

        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.airbnb.com/dashboard", [
            'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
        ], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[@title='View Profile']/img/@src")
            || $this->http->FindSingleNode("//div[@id='header']//img[contains(@src, 'aki_policy=profile_small')]/@src")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Incorrect email or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        //$this->selenium('https://www.airbnb.com/');
        $this->getURL('https://www.airbnb.com/');
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 200) {
            $retry = 0;

            do {
                if ($retry > 1) {
                    $this->http->SetProxy($this->proxyReCaptcha());
                }

                if ($retry > 0) {
                    sleep(2);
                    $this->http->GetURL('https://www.airbnb.com/');
                    sleep(2);
                }
                $this->http->GetURL('https://www.airbnb.com/login_modal.json?path=%2F');
                $retry++;
            } while (
                $this->http->Response['code'] == 403
                && $retry < 3
            );
        }

        $this->csrfToken = $this->http->getCookieByName("_csrf_token");

        if (empty($this->csrfToken)) {
            return $this->checkErrors();
        }

        $this->csrfToken = $this->State["csrfToken"] = urldecode($this->csrfToken);

        $this->http->setDefaultHeader("x-csrf-token", $this->csrfToken);
        $this->http->setDefaultHeader("x-requested-with", "XMLHttpRequest");
        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*; q=0.01");
        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");

        $this->http->RetryCount = 0;
        $captcha = $this->parseReCaptcha('6LcZIM8aAAAAAF-MVKDG5e_696lgsoUeqKoXlxsR', true, "unified_email_lookup/signup_login/web");

        if ($captcha === false) {
            return false;
        }
        $this->http->setDefaultHeader('viewport-width', 1920);
        $this->http->setDefaultHeader('X-Airbnb-API-Key', self::API_KEY);
        $this->http->setDefaultHeader('X-Airbnb-Supports-Airlock-V2', 'true');
        $this->http->setDefaultHeader('X-Airbnb-Client-Action-ID', '845aa196-3ebd-402f-aea9-46a6a3fa5f00');
        $this->http->setDefaultHeader('X-CSRF-Without-Token', 1);

        $headers = [
            'Referer'                  => 'https://www.airbnb.com/',
            'Origin'                   => 'https://www.airbnb.com',
            'x-airbnb-recaptcha-token' => "WEB-V3:$captcha",
            "Content-Type"             => "application/json",
        ];
        $data = [
            'authMethod'      => 'EMAIL_AND_PASSWORD',
            'loginIdentifier' => $this->AccountFields['Login'],
        ];
        $this->http->PostURL('https://www.airbnb.com/api/v2/auth_flows?currency=USD&key=' . self::API_KEY . '&locale=en', json_encode($data), $headers);
        $response = $this->http->JsonLog();
        //$this->authWithCaptcha($response);

        $captcha = $this->parseReCaptcha('6LcZIM8aAAAAAF-MVKDG5e_696lgsoUeqKoXlxsR', true, "authenticate/s_l/web_platform");

        if ($captcha === false) {
            return false;
        }

        $headers = [
            'Referer'                  => 'https://www.airbnb.com/',
            'Origin'                   => 'https://www.airbnb.com',
            'X-AIRBNB-RECAPTCHA-TOKEN' => "WEB-V3:$captcha",
        ];

        $data = [
            'email'                       => $this->AccountFields['Login'],
            'password'                    => $this->AccountFields['Pass'],
            'from'                        => 'email_login',
            'origin_url'                  => 'https://www.airbnb.co.uk/?has_logged_out=1#simple-header-profile-menu',
            'page_controller_action_pair' => '',
            'remember_me'                 => 'true',
        ];
        $this->http->PostURL('https://www.airbnb.com/authenticate', $data, $headers);
        $this->csrfToken = $this->http->getCookieByName("_csrf_token");
        $response = $this->http->JsonLog(null, 3);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re experiencing some unexpected issues, but our team is already working to fix the problem")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Airbnb is temporarily unavailable, but we're working hard to fix the problem.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Airbnb is temporarily unavailable, but we\'re working hard to fix the problem.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Well, this is unexpected…
        if (
            $this->http->FindPreg('/"message":"Unable to perform action. Please try again later or contact support if you need immediate assistance."/')
            || $this->http->Response['code'] == 403
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "An error has occurred and we\'re working to fix the problem! We’ll be up and running")]')) {
            throw new CheckRetryNeededException(2, 7, $message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->Response['code'] == 503
            && $this->http->FindPreg("/<BODY>\s*An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function authWithCaptcha($response)
    {
        $this->logger->notice(__METHOD__);

        if (isset($response->client_error_info)) {
            $response = $response->client_error_info;
        }

        if (isset($response->airlock->flow) && $response->airlock->flow == 'captcha_flow'
            && ($key = $this->http->FindPreg('/friction_data":\[\{"data":\{"site_key":"([\w\-_]+)"/'))
        ) {
//            $key = '6LcZIM8aAAAAAF-MVKDG5e_696lgsoUeqKoXlxsR';
//            $captcha = $this->parseReCaptcha($key, true);
            $captcha = $this->parseReCaptcha($key);

            if ($captcha === false) {
                return false;
            }
            // flow":"captcha_flow","friction_data":[{"data":{"site_key":"6LcZIM8aAAAAAF-MVKDG5e_696lgsoUeqKoXlxsR","android_site_key":"6LdAOmsUAAAAADtHajb3Pq1hy547UPtrEKrT6dNq"}
            //{"friction":"captcha","friction_data":{"response":{"captcha_response":""}},"enable_throw_errors":true}
            $data = json_encode([
                'friction'      => 'captcha',
                'friction_data' => [
                    'response' => ['captcha_response' => $captcha],
                ],
                'enable_throw_errors' => true,
            ]);
            $this->http->RetryCount = 0;
            $this->http->PutURL("https://www.airbnb.com/api/v2/airlocks/{$response->airlock->id}?key=" . self::API_KEY . "&_format=v1", $data, [
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
                'Referer'      => 'https://www.airbnb.com/',
            ]);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();

            sleep(2);

            $captcha = $this->parseReCaptcha('6LcZIM8aAAAAAF-MVKDG5e_696lgsoUeqKoXlxsR', true, "authenticate/s_l/web_platform");

            if ($captcha === false) {
                return false;
            }

            $this->http->RetryCount = 0;
            $this->http->setDefaultHeader('x-airbnb-replay-airlock-id', $response->airlock->id);
            $headers = [
                'Referer'                  => 'https://www.airbnb.com/',
                'Origin'                   => 'https://www.airbnb.com',
                'X-AIRBNB-RECAPTCHA-TOKEN' => "wEB-V3:$captcha",
            ];
            $this->http->PostURL('https://www.airbnb.com/authenticate', [
                'email'                       => $this->AccountFields['Login'],
                'password'                    => $this->AccountFields['Pass'],
                'from'                        => 'email_login',
                'origin_url'                  => 'https://www.airbnb.com/login?redirect_params[action]=dashboard&redirect_params[controller]=home',
                'page_controller_action_pair' => '',
                'remember_me'                 => 'true',
            ], $headers);

            $response = $this->http->JsonLog(null, 5);
            sleep(1);
        } elseif (isset($response->airlock->flow) && $response->airlock->flow == 'captcha_flow'
            && isset($response->airlock->friction_data[0]->name) && $response->airlock->friction_data[0]->name == 'arkose_bot_detection'
        ) {
            $captcha = $this->parseFunCaptcha();

            if ($captcha === false) {
                return false;
            }

            $data = json_encode([
                'friction'         => 'arkose_bot_detection',
                'friction_payload' => [
                    'arkose_bot_detection_payload' => ['session_token' => $captcha],
                ],
                'enable_throw_errors' => true,
            ]);
            $this->http->RetryCount = 0;
            $this->http->PutURL("https://www.airbnb.com/api/v2/airlocks/{$response->airlock->id}?key=" . self::API_KEY . "&_format=v1",
                $data, [
                    'X-Airbnb-Supports-Airlock-V2' => null,
                    'Accept'                       => 'application/json',
                    'Content-Type'                 => 'application/json',
                    'Referer'                      => 'https://www.airbnb.com/',
                    'viewport-width'               => '1920',
                    'device-memory'                => 8,
                    'dpr'                          => 1,
                    'ect'                          => '4g',
                ]);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
            $this->http->RetryCount = 0;
            $this->http->setDefaultHeader('x-airbnb-replay-airlock-id', $response->airlock->id);
            $headers = [
                'Referer'                  => 'https://www.airbnb.com/',
                'Origin'                   => 'https://www.airbnb.com',
                'X-AIRBNB-RECAPTCHA-TOKEN' => "wEB-V3:$captcha",
            ];
            $this->http->PostURL('https://www.airbnb.com/authenticate', [
                'email'                       => $this->AccountFields['Login'],
                'password'                    => $this->AccountFields['Pass'],
                'from'                        => 'email_login',
                'origin_url'                  => 'https://www.airbnb.com/login?redirect_params[action]=dashboard&redirect_params[controller]=home',
                'page_controller_action_pair' => '',
                'remember_me'                 => 'true',
            ], $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog(null, 5);
            sleep(1);
        }

        return $response;
    }

    public function Login()
    {
        /*
        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(3, 7);
        }
        */

        $response = $this->http->JsonLog(null, 0);

        $response = $this->authWithCaptcha($response);
        // V2
        if (isset($response->airlockV2)) {
            if ($this->parseQuestionV2($response)) {
                return false;
            }
        }

        if ((isset($response->status) && $response->status == 'ok')) {
            return true;
        }

        if ((isset($response->airlock->flow) && in_array($response->airlock->flow, [
            'account_ownership_verification_for_login',
            'account_ownership_verification_forced_phone_only', ]))
            || (isset($response->message) && $response->message == 'Please enter a two-factor authentication code to continue.')) {
            if ($this->parseQuestion()) {
                return false;
            }
        } elseif (isset($response->airlock->flow) && $response->airlock->flow == 'captcha_flow') {
            throw new CheckRetryNeededException(1, 7, self::CAPTCHA_ERROR_MSG);
        } elseif (isset($response->airlock->flow) && in_array($response->airlock->flow, ['force_password_reset'])) {
            $this->throwProfileUpdateMessageException();
        } elseif (isset($response->airlock->flow) && in_array($response->airlock->flow, ['review_your_account'])) {
            $this->throwAcceptTermsMessageException();
        }

        // Invalid credentials
        $message = $response->message ?? $response->error_message ?? null;
        $this->logger->error("[Error]: {$message}");

        if ($message) {
            // Looks like you haven't set a password yet! We've sent a link to ...@....com to do that.
            if ((strstr($message, "Looks like you haven") && strstr($message, "t set a password yet!"))
                // Please check your email and activate your account
                || strstr($message, "Please check your email and activate your account")
                // The email or password you entered is incorrect.
                || strstr($message, "The email or password you entered is incorrect.")
                // The password you entered is incorrect. Try again, or choose another login option.
                || strstr($message, "The password you entered is incorrect.")
                // No account exists for this email. Make sure it’s typed in correctly, or “sign up” instead.
                || strstr($message, "No account exists for this email.")
                // There isn’t an account associated with this email address. Please try another email.
                || strstr($message, "There isn’t an account associated with this email address. Please try another email.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // You’ve reached the max confirmation attempts. Try again later.
            if (strstr($message, "You’ve reached the max confirmation attempts. Try again later.")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // As a precaution, we occasionally ask hosts to verify some personal information to ensure the integrity of their account.
            if (
                strstr($message, "As a precaution, we occasionally ask hosts to verify some personal information to ensure the integrity of their account.")
                || strstr($message, "You haven’t set a password yet. We just sent a link to create one to ")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // may be captcha not accepting
            if (
                strstr($message, "Unable to perform action. Please try again later or contact support if you need immediate assistance.")
                || strstr($message, "Unable to perform action. Please try again through the website or contact support if you need immediate assistance.")
            ) {
                throw new CheckRetryNeededException(3, 7/*, $message, ACCOUNT_PROVIDER_ERROR*/);
            }
        }// if ($message

        if (isset($response->data->redirect)) {
            $this->http->GetURL($response->data->redirect);
        }
        // Facebook
        if (strstr($this->http->currentUrl(), 'auth_merge_from=email&auth_merge_to=facebook')
            || (isset($response->airlock->friction_data) && count($response->airlock->friction_data) == 2 && $response->airlock->friction_data[1]->name == 'facebook_verification')) {
            throw new CheckException('Sorry, login via Facebook is not supported', ACCOUNT_PROVIDER_ERROR);
        }
        // Google
        if (strstr($this->http->currentUrl(), 'auth_merge_from=email&auth_merge_to=google')) {
            throw new CheckException('Sorry, login via Google is not supported', ACCOUNT_PROVIDER_ERROR);
        }

        if (
            isset($response->airlock->friction_data)
            && count($response->airlock->friction_data) == 2 && $response->airlock->friction_data[0]->data->error_message == "For security, Airbnb limits how often you can login. Please try again soon."
        ) {
            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function parseQuestionV2($response)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        if (empty($response->airlockV2->id)) {
            $this->logger->error('airlockV2->id empty');

            return false;
        }
        // Email
        foreach ($response->airlockV2->frictionVersions as $friction) {
            if ($friction->friction == 'EMAIL_CODE_VERIFICATION') {
                $response->step = $friction->friction;

                break;
            }
        }
        // SMS
        if (empty($response->step)) {
            foreach ($response->airlockV2->frictionVersions as $friction) {
                if ($friction->friction == 'PHONE_VERIFICATION_VIA_TEXT') {
                    $response->step = $friction->friction;

                    break;
                }
            }
        }
        // Phone call
        if (empty($response->step)) {
            foreach ($response->airlockV2->frictionVersions as $friction) {
                if ($friction->friction == 'PHONE_VERIFICATION_VIA_CALL') {
                    $response->step = $friction->friction;

                    break;
                }
            }
        }

        if (!isset($response->step)) {
            return false;
        }

        $this->logger->notice($response->step);

        $headers = [
            'Accept'           => '*/*',
            'Content-Type'     => 'application/json',
            'X-Airbnb-API-Key' => self::API_KEY,
            //            'X-Airbnb-GraphQL-Platform' => 'web',
            //            'X-Airbnb-GraphQL-Platform-Client' => 'minimalist-niobe',
            //            'X-Airbnb-Supports-Airlock-V2' => 'true',
            //            'X-CSRF-Without-Token' => 1,
            //            'device-memory' => 8,
            //            'DPR' => 1,
            //            'ect' => '4g',
            'x-requested-with' => null,
        ];
        $param = http_build_query([
            'operationName' => 'GetAirlockFrictionView',
            'locale'        => 'en',
            'currency'      => 'NOK',
            'variables'     => '{"airlockId":"' . $response->airlockV2->id . '","frictionName":"' . $response->step . '"}',
            'extensions'    => '{"persistedQuery":{"version":1,"sha256Hash":"214269e54b704bf0417d7c90678e6acc266d38ad334530ed1810dc58c7d11f61"}}', // https://a0.muscache.com/airbnb/static/packages/GenericViewSelection-f503a3f4.js
            '_cb'           => $this->randomString(),
        ]);
        //$this->http->disableOriginHeader();
        $this->http->GetURL("https://www.airbnb.com/api/v3/GetAirlockFrictionView?{$param}", $headers);

        if ($this->http->Response['code'] == 403) {
            $this->http->GetURL("https://www.airbnb.com/api/v3/GetAirlockFrictionView?{$param}", $headers);
        }
        $data = $this->http->JsonLog();

        if (!isset($data->data)) {
            return false;
        }

        if ($response->step == 'EMAIL_CODE_VERIFICATION') {
            $email = $data->data->airlock->airlockWithFrictionView->viewPayload->frictionData->obfuscatedEmailAddress;
            $message = "Please enter the Code which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

            $data = json_encode([
                'operationName' => 'EmailCodeSendCode',
                'variables'     => [
                    'payload' => [
                        'airlockId' => $data->data->airlock->airlockWithFrictionView->id,
                    ],
                ],
                'extensions' => [
                    'persistedQuery' => [
                        'version'    => 1,
                        'sha256Hash' => '64361e39f152b74f135467b299523be1e109f8ef17b62c18a2c8dbd313fcb285',
                        // https://a0.muscache.com/airbnb/static/packages/7f27-855c0701.js
                    ],
                ],
            ]);

            $this->http->PostURL("https://www.airbnb.com/api/v3/EmailCodeSendCode?operationName=EmailCodeSendCode&locale=en&currency=NOK&_cb=0v7fwjw13go86u0wyc56g03x06lq",
                $data);
        }

        if ($response->step == 'PHONE_VERIFICATION_VIA_TEXT') {
            $phone = $data->data->airlock->airlockWithFrictionView->viewPayload->frictionData->phoneNumbers[0]->obfuscated;
            $message = "Please enter the Code which was sent to the following phone number: {$phone}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
            // $this->State['phoneNumberId'] = $frictionData->data->phone_numbers[0]->id;

            $data = json_encode([
                'operationName' => 'PhoneTextSendCode',
                'variables'     => [
                    'payload' => [
                        'airlockId'     => $data->data->airlock->airlockWithFrictionView->id,
                        'phoneNumberId' => $data->data->airlock->airlockWithFrictionView->viewPayload->frictionData->phoneNumbers[0]->id,
                    ],
                ],
                'extensions' => [
                    'persistedQuery' => [
                        'version'    => $data->data->airlock->airlockWithFrictionView->id,
                        'sha256Hash' => '748ac8189b0736f25d267facf0894d05f942a55728cfb94712c51cb383df03ca',
                        // https://a0.muscache.com/airbnb/static/packages/b882-16664b03.js
                    ],
                ],
            ]);
            $this->http->PostURL("https://www.airbnb.com/api/v3/PhoneTextSendCode?operationName=PhoneTextSendCode&locale=en&currency=NOK&_cb=0v7fwjw13go86u0wyc56g03x06lq",
                $data);
        }

        if (!isset($message)) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->State['data'] = $data;
        $this->State['csrfToken'] = urldecode($this->http->getCookieByName("_csrf_token"));
        $this->AskQuestion($message, null, $response->step);

        return true;
    }

    public function randomString($length = 28, $string = "0123456789abcdefghijklmnopqrstuvwxyz")
    {
        return substr(str_shuffle($string), 0, $length);
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        $this->State['csrfToken'] = urldecode($this->http->getCookieByName("_csrf_token"));

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        // Two-factor authentication
        // AccountId: 3963881
        if (isset($response->message) && $response->message == 'Please enter a two-factor authentication code to continue.') {
            $this->State['airlockUrl'] = 'https://www.airbnb.com/authenticate';
            $this->Question = $response->message;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = 'two_factor_authentication';

            return true;
        }

        if (empty($response->airlock->id)) {
            return false;
        }
        // Email
        foreach ($response->airlock->friction_data as $frictionData) {
            if ($frictionData->name == 'email_code_verification') {
                $response->step = $frictionData->name;
                $this->logger->notice($response->step);

                if (!isset($frictionData->data->obfuscated_email_address)) {
                    $this->logger->error("Something went wrong");

                    return false;
                }

                $response->message = "Please enter the Code which was sent to the following email address: {$frictionData->data->obfuscated_email_address}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                $data = json_encode([
                    'friction'            => 'email_code_verification',
                    'attempt'             => true,
                    'enable_throw_errors' => true,
                ]);

                break;
            }
        }
        // SMS
        if (empty($response->step)) {
            foreach ($response->airlock->friction_data as $frictionData) {
                if ($frictionData->name == 'phone_verification_via_text') {
                    $response->step = $frictionData->name;
                    $this->logger->notice($response->step);

                    if (
                        !isset($frictionData->data->phone_numbers[0]->obfuscated)
                        || !isset($frictionData->data->phone_numbers[0]->id)
                    ) {
                        $this->logger->error("Something went wrong");

                        return false;
                    }

                    $response->message = "Please enter the Code which was sent to the following phone number: {$frictionData->data->phone_numbers[0]->obfuscated}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                    $this->State['phoneNumberId'] = $frictionData->data->phone_numbers[0]->id;

                    $data = json_encode([
                        'friction'      => 'phone_verification_via_text',
                        'friction_data' => [
                            'optionSelection' => ['phone_number_id' => $this->State['phoneNumberId']],
                        ],
                        'attempt'             => true,
                        'enable_throw_errors' => true,
                    ]);

                    break;
                }
            }
        }
        // App
        if (empty($response->step)) {
            foreach ($response->airlock->friction_data as $frictionData) {
                if ($frictionData->name == 'push_code_verification') {
                    $response->step = $frictionData->name;
                    $this->logger->notice($response->step);
                    $response->message = 'Enter the code that has been sent to the Airbnb app on your phone or tablet';
                    $data = json_encode([
                        'friction'            => 'push_code_verification',
                        'attempt'             => true,
                        'enable_throw_errors' => true,
                    ]);

                    break;
                }
            }
        }
        // Phone call
        if (empty($response->step)) {
            foreach ($response->airlock->friction_data as $frictionData) {
                if ($frictionData->name == 'phone_verification_via_call') {
                    $response->step = $frictionData->name;
                    $this->logger->notice($response->step);

                    if (
                        !isset($frictionData->data->phone_numbers[0]->obfuscated)
                        || !isset($frictionData->data->phone_numbers[0]->id)
                    ) {
                        $this->logger->error("Something went wrong");

                        return false;
                    }

                    $response->message = "Please enter the Code which was sent to the following phone number: {$frictionData->data->phone_numbers[0]->obfuscated}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                    $this->State['phoneNumberId'] = $frictionData->data->phone_numbers[0]->id;

                    $data = json_encode([
                        'friction'      => 'phone_verification_via_call',
                        'friction_data' => [
                            'optionSelection' => ['phone_number_id' => $this->State['phoneNumberId']],
                        ],
                        'attempt'             => true,
                        'enable_throw_errors' => true,
                    ]);

                    break;
                }
            }
        }
        // reverse_caller_id_verification, example: 2505054
        if (empty($response->step)) {
            foreach ($response->airlock->friction_data as $frictionData) {
                if ($frictionData->name == 'reverse_caller_id_verification') {
                    throw new CheckRetryNeededException(4, 5, 'You need to update your security settings. Please add your phone number to keep your account secure.');
                }
            }
        }

        if (!isset($data, $response->step, $response->message)) {
            return false;
        }

        $this->http->unsetDefaultHeader("x-requested-with");

        $this->http->RetryCount = 0;
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'Referer'      => 'https://www.airbnb.com/',
            'x-csrf-token' => $this->State["csrfToken"],
        ];
        $this->State["airlockUrl"] = "https://www.airbnb.com/api/v2/airlocks/{$response->airlock->id}?key=" . self::API_KEY . "&_format=v1";
        $this->http->PutURL($this->State['airlockUrl'], $data, $headers);

        if ($this->http->Response['code'] == 403) {
            sleep(2);
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
            $this->logger->notice('Retry...');
            $this->http->PutURL($this->State['airlockUrl'], $data, $headers);
        }// if ($this->http->Response['code'] == 403)

        $this->State['csrfToken'] = urldecode($this->http->getCookieByName("_csrf_token"));
        $this->http->JsonLog(null, 3, true);
        $this->http->RetryCount = 2;

        if (isset($response->error_code, $response->error_message)) {
            $this->logger->error("something went wrong");

            return false;
        }

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        $this->Question = $response->message;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = $response->step;

        return true;
    }

    public function ProcessStep($step)
    {
        if ($step == 'EMAIL_CODE_VERIFICATION') {
            $data = json_decode($this->State['data']);
            $data->operationName = 'EmailCodeVerifyCode';
            $data->variables->payload->code = $this->Answers[$this->Question];
            $data = json_encode($data);
            $this->http->PostURL("https://www.airbnb.com/api/v3/EmailCodeVerifyCode?operationName=EmailCodeVerifyCode&locale=en&currency=NOK&_cb=1mtbt7q07n1uox17t38yl0lpwcyz", $data);
            unset($this->State['data'], $this->Answers[$this->Question]);
            $response = $this->http->JsonLog();

            if (isset($response->data->airlockEmailCodeVerificationVerifyCode->errors[0]->errorMessage)) {
                $this->AskQuestion($this->Question, $response->data->airlockEmailCodeVerificationVerifyCode->errors[0]->errorMessage, $step);

                return false;
            }

            // Authenticate
            $headers = [
                'Accept'                     => 'application/json, text/javascript, */*; q=0.01',
                'Content-Type'               => 'application/x-www-form-urlencoded; charset=UTF-8',
                'x-airbnb-replay-airlock-id' => $response->airlockEmailCodeVerificationVerifyCode->airlockEmailCodeVerificationVerifyCode->airlock,
                'Referer'                    => 'https://www.airbnb.com/',
                'Origin'                     => 'https://www.airbnb.com',
                'x-csrf-token'               => $this->State["csrfToken"],
                'x-requested-with'           => 'XMLHttpRequest',
            ];
            $this->http->RetryCount = 0;

            $data = [
                'email'                       => $this->AccountFields['Login'],
                'password'                    => $this->AccountFields['Pass'],
                'from'                        => 'email_login',
                'origin_url'                  => 'https://www.airbnb.com/',
                'page_controller_action_pair' => '',
                'remember_me'                 => 'true',
            ];
            $this->http->PostURL('https://www.airbnb.com/authenticate', $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if ($this->http->Response['code'] != 200) {
                //$this->authWithCaptcha($response);
            }

            $this->sendNotification('chek authenticate');

            return true;
        }

        // Old
        if ($step == 'two_factor_authentication') {
            $this->http->RetryCount = 0;
            $this->http->PostURL($this->State['airlockUrl'], [
                'utf8'               => '✓',
                'authenticity_token' => $this->State["csrfToken"],
                'otp'                => $this->Answers[$this->Question],
                'remember_otp'       => true,
            ], [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);
            $this->http->RetryCount = 2;
            unset($this->Answers[$this->Question]);

            if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Two-factor authentication')]")) {
                $this->AskQuestion($this->Question, $message, $step);

                return false;
            }

            return true;
        } elseif ($step == 'phone_verification_via_call'
            || $step == 'push_code_verification'
            || $step == 'email_code_verification'
            || (isset($this->State['phoneNumberId']) && $step == 'phone_verification_via_text')
        ) {
            $friction_data = [
                'response' => ['code' => $this->Answers[$this->Question]],
            ];
            // Phone call, SMS
            if (isset($this->State['phoneNumberId']) && in_array($step, ['phone_verification_via_text', 'phone_verification_via_call'])) {
                $friction_data['optionSelection'] = [
                    'phone_number_id' => $this->State['phoneNumberId'],
                ];
            }
            $data = json_encode([
                'friction'            => $step,
                'friction_data'       => $friction_data,
                'enable_throw_errors' => true,
            ]);
        }

        if (!isset($data)) {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->setDefaultHeader("Referer", "https://www.airbnb.com/");
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'Origin'       => 'https://www.airbnb.com',
            'x-csrf-token' => $this->State["csrfToken"],
        ];
        $this->http->PutURL($this->State['airlockUrl'], $data, $headers);

        if ($this->http->Response['code'] == 403) {
            sleep(2);
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));

            $this->logger->notice('Retry...');
            $this->http->PutURL($this->State['airlockUrl'], $data, $headers);

            if ($this->http->Response['code'] == 403) {
                sleep(2);
                $this->http->SetProxy($this->proxyReCaptcha());
                $this->logger->notice('Retry 2...');
                $this->http->PutURL($this->State['airlockUrl'], $data, $headers);
            }
        }
        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);

        $response = $this->http->JsonLog();

        if (isset($response->error_code, $response->error_message)) {
            $this->AskQuestion($this->Question, $response->error_message, $step);

            return false;
        }

        if (!isset($response->airlock->id)) {
            return false;
        }

        // Authenticate
        $headers = [
            'Accept'                     => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'               => 'application/x-www-form-urlencoded; charset=UTF-8',
            'x-airbnb-replay-airlock-id' => $response->airlock->id,
            //            'x-airbnb-recaptcha-token' => $this->State['airbnbRecaptcha'],
            'Referer'                    => 'https://www.airbnb.com/',
            'Origin'                     => 'https://www.airbnb.com',
            'x-csrf-token'               => $this->State["csrfToken"],
            'x-requested-with'           => 'XMLHttpRequest',
        ];
        $this->http->RetryCount = 0;

        $data = [
            'email'                       => $this->AccountFields['Login'],
            'password'                    => $this->AccountFields['Pass'],
            'from'                        => 'email_login',
            'origin_url'                  => 'https://www.airbnb.com/',
            'page_controller_action_pair' => '',
            'remember_me'                 => 'true',
        ];
        $this->http->PostURL('https://www.airbnb.com/authenticate', $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->http->Response['code'] != 200) {
            $this->authWithCaptcha($response);
        }

        return true;
    }

    public function Parse()
    {
        $this->http->RetryCount = 0;
        $this->http->setDefaultHeader("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8");
        $this->csrfToken = $this->http->getDefaultHeader("x-csrf-token");
        $this->http->unsetDefaultHeader("x-requested-with");
        $this->http->unsetDefaultHeader("x-csrf-token");

        $this->http->GetURL("https://www.airbnb.com/");

        $this->getURL("https://www.airbnb.com/dashboard");

        // Redirecting to airbnb.ca - Airbnb
        if ($this->http->ParseForm('forwarder_form')) {
            if (!$this->http->PostForm()) {
                return;
            }
        }

        if ($this->http->FindSingleNode('//div[contains(text(),"We blocked a suspicious login")]')
            && ($message = $this->http->FindSingleNode('//div[contains(text(),"We prevented someone from signing into your account")]'))) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // Terms of Service Update
        if ($this->http->FindSingleNode('//h2[contains(text()," Terms of Service ")]')
            && strstr($this->http->currentUrl(), 'tos_confirm?')) {
            $this->throwAcceptTermsMessageException();
        }

        // Add a phone number
        // $this->http->FindSingleNode('//div[contains(text(),"Add a phone number")]')
        if ($this->http->FindPreg('#">Add a phone number</div>#')
            && strstr($this->http->currentUrl(), '/airlock?al_id=')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->Response['code'] == 422) {
            // To help protect your information, we’ve temporarily disabled your account. Please login via our website to contact us.
            if ($message = $this->http->FindPreg("/\{\"message\":\"(To help protect your information, we’ve temporarily disabled your account. Please login via our website to contact us\.)/")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
        }// if ($this->http->Response['code'] == 422)
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class = 'panel-body']/h2")));

        // Name
        $this->getURL('https://www.airbnb.com/account-settings/personal-info');
        $name = $this->http->FindSingleNode("//div[contains(text(),'Legal name')]/following-sibling::div[1]/div[1]");

        if (isset($name, $this->Properties['Name']) && mb_strlen($name) > mb_strlen($this->Properties['Name'])) {
            $this->SetProperty('Name', beautifulName($name));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Let’s change your password")]')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->currentUrl() == 'https://www.airbnb.com/account_review') {
                throw new CheckException("Someone from our team will review the information you provided and follow up with you soon at {$this->AccountFields['Login']}.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->currentUrl() == 'https://www.airbnb.com/review_your_account'
                && ($message = $this->http->FindPreg('/"(Your account has been locked for security reasons)"/'))) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
        }

        // refs #16689
        $this->http->GetURL("https://www.airbnb.com/invite?r=50");
        // Balance - Your travel credit - AvailableSe
        $currency = $this->http->FindPreg('/"native_currency":"([A-Z]{3})",/');
        $this->SetProperty('Currency', $currency /*$this->overrideCurrency($balance)*/);
        $locale = $this->http->FindPreg('/"locale":"([a-z]{2}-[A-Z]{2})",/');
        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            'Referer'      => 'https://www.airbnb.com/',
            'x-csrf-token' => $this->State["csrfToken"],
        ];
        $this->getURL("https://www.airbnb.com/api/v2/get_referral_summary?currency={$currency}&key=" . self::API_KEY . "&locale={$locale}", $headers);

        $response = $this->http->JsonLog();

        if (isset($response->available_credit)) {
            $this->SetBalance($response->available_credit);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($balance = $this->http->FindPreg('/<div class="\w+">For referrals made after 1 Oct 2020, Airbnb/')) {
                $this->sendNotification('Balance NA // MI');
                $this->SetBalanceNA();
            }
        }

        // Airbnb gift card balance: 0₽
        $this->SetProperty("CombineSubAccounts", false);
        $this->getURL('https://www.airbnb.com/account-settings/payments/payment-methods');
        $this->key = $this->http->FindPreg('#"baseUrl":"/api","key":"(\w+)"#');
        $query = [
            // don't forget for reservations
            'key'      => $this->key,
            'currency' => $currency,
            'locale'   => 'en',
        ];
        $this->getURL("https://www.airbnb.com/api/v2/bootstrap_datas/payment-methods?" . http_build_query($query));
        $response = $this->http->JsonLog(null, 0);
        $giftCardBalance = $response->bootstrap_data->data->bootstrapData->reduxBootstrap->gift_credit->total_balance_string ?? null;

        if (isset($giftCardBalance)) {
            $giftCardVal = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $giftCardBalance);

            if ($giftCardVal > 0) {
                $this->sendNotification('refs#16689, Check Gift Credit Balance // MI');
                $this->AddSubAccount([
                    "Code"        => "airbnbGiftCreditBalance",
                    "DisplayName" => "Gift Credit Balance",
                    "Balance"     => $giftCardVal,
                    'Currency'    => $currency,
                ]);
            }
        }

        // Your Coupons
        $coupons = $response->bootstrap_data->data->bootstrapData->reduxBootstrap->coupon->coupons ?? null;

        if (isset($coupons) && is_array($coupons)) {
            foreach ($coupons as $coupon) {
                if (isset($coupon->expires_after, $coupon->formatted_localized_amount)) {
                    $balance = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $coupon->formatted_localized_amount);

                    if (($exp = strtotime($coupon->expires_after, false)) && $balance > 0 && $coupon->status == 'active') {
                        $this->AddSubAccount([
                            "Code"           => "airbnbCounon{$coupon->code}",
                            "DisplayName"    => "Coupon code {$coupon->code}",
                            "Balance"        => $balance,
                            'Currency'       => $coupon->currency,
                            'ExpirationDate' => $exp,
                        ]);
                    }
                }
            }
        }
        $this->http->RetryCount = 2;
    }

    public function overrideCurrency($s)
    {
        $s = preg_replace([
            '/^[\d.,\s]+\s*₽$/u',
            '/^\$\s*[\d.,\s]+\s*SGD$/',
        ], [
            'RUB',
            'SGD',
        ], trim($s));

        return $s;
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->setDefaultHeader('x-csrf-token', $this->csrfToken);
        $this->http->setDefaultHeader('X-Requested-With', 'XMLHttpRequest');
        $this->http->setDefaultHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->http->GetURL('https://www.airbnb.com/trips/v1');
        $key = $this->http->FindPreg('#"baseUrl":"/api","key":"(\w+)"#');

        if (empty($key)) {
            if (!empty($this->key)) {
                $this->sendNotification('check key // MI');
                $key = $this->key;
            } else {
                return [];
            }
        }
        $query = [
            '_order'     => 'desc',
            '_format'    => 'for_upcoming',
            'time_scope' => 'upcoming',
            '_limit'     => '50',
            '_offset'    => '0',
            'now'        => date('c'),
            'key'        => $key,
            //'currency' => $this->http->FindPreg('/currency=([A-Z]{3})/'),
            'locale' => 'en',
        ];
        // Upcoming reservations
        $this->http->GetURL("https://www.airbnb.com/api/v2/scheduled_plans?" . http_build_query($query));
        $response = $this->http->JsonLog();
        $notUpcoming = $this->http->FindPreg('/"scheduled_plans":\[\]/');

        if (!$notUpcoming) {
            $result = $this->ParseItineraryPage($response, $query);
        }

        // Past reservations
        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);
            $query['_format'] = 'for_past';
            $query['time_scope'] = 'past';
            $this->http->GetURL("https://www.airbnb.com/api/v2/scheduled_plans?" . http_build_query($query));
            $response = $this->http->JsonLog();

            if (!$this->http->FindPreg('/"scheduled_plans":\[\]/')) {
                $result = array_merge($result, $this->ParseItineraryPage($response, $query));
            } elseif ($notUpcoming) {
                return $this->noItinerariesArr();
            }
        } elseif ($notUpcoming) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    protected function parseReCaptcha($key = null, $isV3 = false, $action = "authenticate/s_l/web_platformr")
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[contains(@class, 'g-recaptcha')]/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://www.airbnb.com/', //$this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($isV3) {
            $this->logger->notice("ReCaptcha V3");
            $parameters += [
                "version"   => "v3",
                "action"    => $action,
                "min_score" => 0.9,
            ];
            /*
            $postData = [
                "type"       => "RecaptchaV3TaskProxyless",
                "websiteURL" => 'https://www.airbnb.com/', //$this->http->currentUrl(),
                "websiteKey" => $key,
                "minScore"   => 0.9,
                "pageAction" => "authenticate/homepage",
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData, false);
            */
        }

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function selenium($url)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

            $selenium->useGoogleChrome();

            //$selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL($url);

            $navMenu = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(@aria-label,"Main navigation menu")]'),
                15);

            if (!$navMenu) {
                return false;
            }
            $navMenu->click();
            $navMenu = $selenium->waitForElement(\WebDriverBy::xpath('//div[@id="simple-header-profile-menu"]//*[contains(text(),"Log in")]'),
                3);

            if ($navMenu) {
                $navMenu->click();
                $navMenu = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(@aria-label,"ontinue with email")]'),
                    3);

                if ($navMenu) {
                    $navMenu->click();
                }
            }

            $login = $selenium->waitForElement(\WebDriverBy::id('email-login-email'), 5);
            $login->sendKeys($this->AccountFields['Login']);
            $button = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(@data-testid,"signup-login-submit-btn")]'), 0);

            if (!$button) {
                return false;
            }
            $button->click();

            sleep(7);

            $this->logger->debug("set pass");
            $selenium->driver->executeScript("let FindReact = function (dom) {
                    for (let key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {
                        return dom[key];
                    }
                    return null;
                };
                FindReact(document.querySelector('input[name=\"user[password]\"]')).onChange({target:{value: '{$this->AccountFields['Pass']}'}, preventDefault:function(){}});
            ");
            /* $pass = $selenium->waitForElement(\WebDriverBy::xpath("//input[@id='email-signup-password']"), 5);
             if (!$pass)
                 return;
             $pass->sendKeys($this->AccountFields['Pass']);*/

            $button = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(@data-testid,"signup-login-submit-btn")]'), 1);

            if ($button) {
                $button->click();
            } else {
                return false;
            }
            $this->savePageToLogs($selenium);

            $this->logger->debug("load response");
            $responseData = null;

            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                //$this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                if (stristr($xhr->request->getUri(), '/authenticate')) {
                    $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                    $responseData = json_encode($xhr->response->getBody());

                    break;
                }
            }

            $selenium->driver->executeScript('
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                        .then((response) => {                
                            if (response.url.indexOf("/authenticate") > -1) {
                                response
                                .clone()
                                .json()
                                .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                            }
                            resolve(response);
                        })
                        .catch((error) => {
                             
                                 console.log(error) ;
                            
                            reject(response);
                        })
                });
            }
            ');

            sleep(7);
            $responseData = $selenium->driver->executeScript('localStorage.getItem("responseData")');

            $this->logger->debug("xhr response: $responseData");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            return $responseData;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }

        return false;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        //$key = $this->http->FindSingleNode('(//script[contains(@src, "-api.arkoselabs.com/v2/")])[1]/@src', null, true, "/v2\/([^\/]+)/");
        $key = 'A19C33CC-B229-4505-A2EA-A3C33C974701';
        $this->logger->debug("[key]: {$key}");

        if (!$key) {
            return false;
        }

        if ($this->attempt > 1) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => 'https://www.airbnb.com',
                    "funcaptchaApiJSSubdomain" => 'client-api.arkoselabs.com',
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
            "pageurl" => 'https://www.airbnb.com',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function getURL($url, $headers = [])
    {
        $this->logger->notice(__METHOD__);
        $retry = 0;

        do {
            $this->logger->notice('Retry getURL ...');

            if ($retry > 1) {
                $this->http->SetProxy($this->proxyReCaptcha());
            }

            if ($retry > 0) {
                sleep(2);
                /*
                if ($retry == 1) {
                    $this->http->SetProxy($this->proxyUK());
                } else if ($retry == 2) {
                    $this->http->SetProxy($this->proxyReCaptcha());
                } else if ($retry == 3) {
                    $this->setProxyBrightData();
                }
                */
            }
            $this->http->GetURL($url, $headers);
            $retry++;
        } while (
            ($this->http->Response['code'] == 403 || strpos($this->http->Error, 'Network error 28 - Operation timed out after ') !== false)
            && $retry < 4
        );
    }

    private function ParseItineraryPage($response, $query)
    {
        $result = [];

        if (!empty($response->scheduled_plans)) {
            $queryItem = [
                '_format' => 'for_trip_day_view',
                'key'     => $query['key'],
                //'currency' => $query['currency'],
                'locale' => $query['locale'],
            ];
            $this->logger->debug("Total " . count($response->scheduled_plans) . " reservations found");

            foreach ($response->scheduled_plans as $item) {
                $this->logger->debug(" ");
                $this->http->GetURL("https://www.airbnb.com/api/v2/scheduled_plans/{$item->uuid}?" . http_build_query($queryItem));
                $item = $this->http->JsonLog();

                if (!empty($item->scheduled_plan)) {
                    // Group by ConfirmationNumber
                    $groupEvents = $groupSubEvents = [];

                    foreach ($item->scheduled_plan->events as $events) {
                        if ($events->airmoji == 'accomodation_home') {
                            $groupEvents[$events->destination->schedulable_id][] = $events;
                        } else /*if (in_array($events->airmoji, ['food_restaurant', 'trips_lifestyle', 'trips_fitness']))*/ {
                            $groupSubEvents[$events->destination->schedulable_id][] = $events;
                        }
                    }
                    // Iteration for first and last element
                    // R - Hotel
                    foreach ($groupEvents as $groupEvent) {
                        $first = reset($groupEvent);
                        $last = end($groupEvent);

                        if ($res = $this->ParseItinerary($first, $last, $item->scheduled_plan, $queryItem)) {
                            $result[] = $res;
                        }
                    }
                    // E - Event
                    if (!empty($groupSubEvents)) {
                        $this->logger->debug('Group Sub Events');

                        foreach ($groupSubEvents as $groupSubEvent) {
                            $first = reset($groupSubEvent);
                            $last = end($groupSubEvent);

                            if ($res = $this->ParseItineraryEvent($first, $last, $item->scheduled_plan, $queryItem)) {
                                $result[] = $res;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function ParseItineraryEvent($first, $last, $plan, $queryItem)
    {
        $this->logger->notice(__METHOD__);

        $result = ['Kind' => 'E'];

        if ($first->airmoji == 'food_restaurant') {
            $result['EventType'] = EVENT_RESTAURANT;
        } elseif ($first->airmoji == 'trips_lifestyle') {
            $result['EventType'] = EVENT_SHOW;
        } elseif ($first->airmoji == 'trips_fitness') {
            $result['EventType'] = EVENT_EVENT;
        }

        $result['ConfNo'] = $first->destination->schedulable_id;
        $this->logger->info('Parse itinerary #' . $result['ConfNo'], ['Header' => 3]);
        $result['Name'] = $first->title;

        $result['StartDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $first->time_range->starts_at)));
        $result['EndDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $last->time_range->ends_at)));

        // Mar 25 - Mar 29
        if (isset($first->map_data->subtitle) && $this->http->FindPreg('/\w+ \d+ - \w+ \d+/', false, $first->map_data->subtitle)) {
            $checkDate = date('M j', $result['StartDate']) . ' - ' . date('M j', $result['EndDate']);
            $this->logger->debug('Date ' . $checkDate . ' == ' . $first->map_data->subtitle);

            if ($checkDate != $first->map_data->subtitle) {
                $this->sendNotification('refs #17439, airbnb - Check date ' . $checkDate . ' !== ' . $first->map_data->subtitle . ' //MI');
            }
        }

        if (!empty($first->actions)) {
            foreach ($first->actions as $action) {
                if (isset($action->type) && in_array($action->type, ['directions', 'action:directions'])) {
                    $result['Address'] = $action->destination->address;

                    break;
                }

                if (isset($action->type) && $action->type == 'contact') {
                    $result['Phone'] = $action->destination->phone_number;

                    break;
                }
            }

            if (empty($result['Address'])) {
                if (isset($first->subtitles[0]->text) && count($first->subtitles) == 1) {
                    $result['Address'] = $first->subtitles[0]->text;
                } elseif (isset($first->subtitles[0]->text) && count($first->subtitles) > 1) {
                    $this->sendNotification('refs #17439 - Check address //MI');
                }
            }
        }

        $queryItem['_format'] = 'for_generic_ro';
        $this->http->GetURL("https://www.airbnb.com/api/v2/scheduled_events/{$first->event_key}?" . http_build_query($queryItem));
        $response = $this->http->JsonLog(null, 0);

        if (isset($response->scheduled_event->rows)) {
            foreach ($response->scheduled_event->rows as $row) {
                if (in_array($row->id, ['payin_details_with_price', 'billing'])) {
                    $result['TotalCharge'] = $this->http->FindPreg('/[\d.,]+/', false, $row->subtitle);
                    $result['Currency'] = $this->currency($row->subtitle);
                } elseif ($row->id == 'confirmation_code') {
                    // Rewrite confirmation code
                    $result['ConfNo'] = $row->subtitle;
                } elseif (empty($result['Address']) && $row->id == 'home_map' && stripos($row->subtitle ?? null, 'We’ll send you the exact address in') !== false) {
                    $this->logger->notice('Parsed itinerary skip');

                    return null;
                }
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseItinerary($first, $last, $plan, $queryItem)
    {
        $this->logger->notice(__METHOD__);

        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $first->destination->schedulable_id;
        $this->logger->info('Parse itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);
        $result['HotelName'] = $plan->header;

        // We’ll send you the exact address in 12 hours and add it to your itinerary.
        if (isset(reset($first->subtitles)->text)) {
            $result['Address'] = reset($first->subtitles)->text;
        }
        $result['CheckInDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $first->time_range->starts_at)));
        $result['CheckOutDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $last->time_range->ends_at)));

        // Mar 25 - Mar 29
        if (isset($first->map_data->subtitle) && $this->http->FindPreg('/\w+ \d+ - \w+ \d+/', false, $first->map_data->subtitle)) {
            $checkDate = date('M j', $result['CheckInDate']) . ' - ' . date('M j', $result['CheckOutDate']);
            $this->logger->debug('Date ' . $checkDate . ' == ' . $first->map_data->subtitle);

            if ($checkDate != $first->map_data->subtitle) {
                $this->sendNotification('refs #17439, airbnb - Check date ' . $checkDate . ' !== ' . $first->map_data->subtitle);
            }
        }

        if (!empty($first->actions)) {
            foreach ($first->actions as $action) {
                if (isset($action->type) && $action->type == 'contact') {
                    $result['Phone'] = $action->destination->phone_number;

                    break;
                }
            }
        }

        $queryItem['_format'] = 'for_generic_ro';
        $this->http->GetURL("https://www.airbnb.com/api/v2/scheduled_events/{$first->event_key}?" . http_build_query($queryItem));
        $response = $this->http->JsonLog();

        if (isset($response->scheduled_event->rows)) {
            foreach ($response->scheduled_event->rows as $row) {
                if (in_array($row->id, ['payin_details_with_price', 'billing'])) {
                    $result['Total'] = $this->http->FindPreg('/[\d.,]+/', false, $row->subtitle);
                    $result['Currency'] = $this->currency($row->subtitle);
                } elseif ($row->id == 'cancellation_policy') {
                    $result['CancellationPolicy'] = $this->http->FindPreg('/^(.+?)\s+The Airbnb service fee is refundable/is', false, $row->subtitle);
                } elseif (empty($result['Address'])
                    && in_array($row->id, ['home_map', 'map'])
                    && stripos($row->subtitle ?? null, 'We’ll send you the exact address in') !== false
                ) {
                    $this->logger->notice('Parsed itinerary skip');

                    return null;
                }
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
