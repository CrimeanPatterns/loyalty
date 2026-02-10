<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerBooking extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerBookingSelenium.php";

            return new TAccountCheckerBookingSelenium();
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'], $properties['Currency']) && strstr($properties['SubAccountCode'], 'bookingMy')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . " %0.2f");
        }

        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        // detecting as bot (bot: 1,)
        // $this->http->SetProxy($this->proxyReCaptcha()); // they block accounts
        // $this->http->SetProxy($this->proxyDOP()); // they block accounts
//        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION) {
//            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
//        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://secure.booking.com/myreservations.en-us.html', [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful(true)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://secure.booking.com/myreservations.en-us.html?aid=1473858');

        // retries
        if (($this->http->Response['code'] == 500 && $this->http->FindPreg("/Internal Server Error/"))
            || $this->http->Response['code'] == 0) {
            $this->logger->notice("Try load first url one more time");

            throw new CheckRetryNeededException(3, 7);
        }

        $op_token = $this->http->FindPreg("/op_token=([^\&]+)/", false, $this->http->currentUrl());

        if (!$op_token) {
            return false;
        }

//        $this->selenium($this->http->currentUrl());

        $data = [
            "identifier" => [
                "type"  => "IDENTIFIER_TYPE__EMAIL",
                "value" => $this->AccountFields['Login'],
            ],
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
            "X-Booking-Client" => "ap",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://account.booking.com/api/identity/authenticate/v1.0/enter/email/submit?op_token={$op_token}", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $nextStep = $response->nextStep ?? null;

        if ($nextStep != 'STEP_SIGN_IN__PASSWORD') {
            if ($nextStep == 'STEP_EMAIL_MAGIC_LINK_SENT') {
                if ($question = $response->identifier->value ?? null) {
                    $this->AskQuestion("Please copy-paste an authorization link which was sent to your email {$response->identifier->value} to continue the authentication process.", null, "VerificationViaLink"); /*review*/

                    return false;
                }// if ($question = $response->identifier->value ?? null)
            }// if ($nextStep == 'STEP_EMAIL_MAGIC_LINK_SENT')

            return false;
        }

        $data = [
            "context" => [
                "value" => $response->context->value,
            ],
            "authenticator" => [
                "type"  => "AUTHENTICATOR_TYPE__PASSWORD",
                "value" => $this->AccountFields['Pass'],
            ],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://account.booking.com/api/identity/authenticate/v1.0/sign_in/password/submit?op_token={$op_token}", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $nextStep = $response->nextStep ?? null;

        if ($nextStep == 'STEP_SIGN_IN__2FA_PIN') {
            $this->State['context'] = $response->context->value ?? null;
            $this->State['op_token'] = $this->http->FindPreg("/op_token=([^\&]+)/", false, $this->http->currentUrl());

            $this->AskQuestion("We sent a verification code to your phone. Please enter this code in the box below.", null, "Question");

            return false;
        }// if ($nextStep == 'STEP_SIGN_IN__2FA_PIN')

        if ($redirect = $response->redirect_uri ?? null) {
            $this->logger->debug("Redirect to -> {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
            // The password you entered is incorrect
            if ($message = $this->http->FindPreg("/(The password you entered is incorrect\.\s*Is your caps lock off\?)/ims")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // You entered an email address/password combination that doesn't match.
            if ($message = $this->http->FindPreg("/(You entered an email address\/password combination that doesn.'t match\.)/ims")) {
                throw new CheckException(str_replace("\'", "'", $message), ACCOUNT_INVALID_PASSWORD);
            }
            // Please check your email address or password and try again.
            if (strstr($this->http->currentUrl(), 'tmpl=profile/login_light&has_error=generic_fatal_error&has_error_action=&endpoint_url=http')) {
                throw new CheckException('Please check your email address or password and try again.', ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($redirect = $this->http->FindPreg("/document\.location\.href = host \+ \'([^\']+)/ims"))

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $response->error[0]->errorDetails ?? null) {
            $this->logger->error("[Error]: {$message}");

//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->blockScript) && $response->blockScript == "/asapi/captcha") {
            $this->DebugInfo = 'blocked';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        } elseif ($step == "VerificationViaLink") {
            return $this->processVerificationViaLink();
        }

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);

        $data = [
            "context" => [
                "value" => $this->State['context'],
            ],
            "authenticator" => [
                "type"  => "AUTHENTICATOR_TYPE__ONE_TIME_PIN",
                "value" => $this->Answers[$this->Question],
            ],
        ];

        unset($this->Answers[$this->Question]);

        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
            "X-Booking-Client" => "ap",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://account.booking.com/api/identity/authenticate/v1.0/sign_in/2fa_pin/submit?op_token={$this->State['op_token']}", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->error[0]->errorDetails)) {
            $errorDetails = $response->error[0]->errorDetails;
            $this->logger->notice("resetting answers");

            if ($errorDetails == '2fa pin is incorrect') {
                $this->AskQuestion($this->Question, "Enter a valid verification code", "Question");
            }

            return false;
        }

        $this->logger->debug("success");

        if ($redirect = $response->redirect_uri ?? null) {
            $this->logger->debug("Redirect to -> {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }

        return true;
    }

    public function processVerificationViaLink()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question: verification via Link', ['Header' => 3]);

        if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL)) {
            unset($this->Answers[$this->Question]);
            $this->AskQuestion($this->Question, "The link you entered seems to be incorrect", "VerificationViaLink"); /*review*/

            return false;
        }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))
        $this->http->GetURL($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->logger->debug("success");

        return true;
    }

    public function switchAccount($primaryType = 'business')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("switch to {$primaryType} account");
        $secondAccountLink = $this->http->FindSingleNode("//a[contains(text(), 'Switch to the') and contains(text(), 'account')]/@href");
        $userid = $this->http->FindPreg("/\"{$primaryType}UserId\":\s*\"?([^\,\"]+)\"?/"); // fix for '0'

        if (
            isset($userid)
            || $this->http->FindPreg("/b_connected_user_accounts:\s*(\[.+\}\])\,/")
        ) {
            $secondAccountLinkNew = "https://secure.booking.com/profileswitch.en-us.html";
            $this->logger->debug("set secondAccountLinkNew -> '{$secondAccountLinkNew}'");
        }

        if (!$secondAccountLink && empty($secondAccountLinkNew)) {
            $secondAccountLinkNew = $this->http->FindPreg("/b_profile_switch_url:\s*'([^\']+)/");
            $this->logger->debug("parse secondAccountLinkNew -> '{$secondAccountLinkNew}'");
        }

        if (empty($secondAccountLink) && empty($secondAccountLinkNew)) {
            $this->logger->notice("switcher not found");

            return;
        }
        // new switcher
        if (!empty($secondAccountLinkNew)) {
            $this->logger->debug("new switcher");
            $this->http->NormalizeURL($secondAccountLinkNew);
            $this->http->FormURL = $secondAccountLinkNew;
            $this->http->Form = [];
            $token = (
            $this->http->FindPreg("/, token\s*=\s*'(.+?)', input/") ?:
                $this->http->FindPreg("/\"csrfToken\":\"([^\"]+)/") ?:
                    $this->http->FindPreg("/'b_csrf_token':\s*'(.+?)'/")
            );

            if (!$token) {
                $this->sendNotification('switch account token not found // MI');
            }
            $this->http->SetInputValue("bhc_csrf_token", $token);
            $accounts = $this->http->JsonLog($this->http->FindPreg("/b_connected_user_accounts:\s*(\[.+\}\])\,/"));
            $account = $this->http->FindPreg("/\"{$primaryType}UserId\":\s*\"?([^\,\"]+)\"?\,/");

            if (is_array($accounts)) {
                foreach ($accounts as $account) {
                    if ($account->b_active == 0 && $account->b_type == $primaryType) {
                        $this->http->SetInputValue("switch_to_user_id", $account->b_user_id);
                        $type = $account->b_type;

                        if ($type == 'business') {
                            $this->http->SetInputValue("redirect_url", $this->http->FindPreg('/fe_this_url_travel_purpose_business:\s*"([^\"]+)/'));
                        } else {
                            $this->http->SetInputValue("redirect_url", $this->http->FindPreg('/fe_this_url_travel_purpose_leisure:\s*"([^\"]+)/'));

                            if (in_array($this->AccountFields['Login'], [
                                'jtran411@gmail.com', // AccountID: 2904191
                                'travel@radoslavlorkovic.com', // AccountID: 3299939
                                'relder110152@yahoo.com', // AccountID: 5456564
                                'osuhami@gmail.com', // AccountID: 4815409
                                'sancheztallone@hotmail.com', // AccountID: 2675791
                                'steve@jumbocruiser.com', // AccountID: 3482677
                                'hannesvongoesseln@gmail.com', // AccountID: 4604218
                                'alesv8@gmail.com', // AccountID: 4821221
                                'roberto.appel@utschbrasil.com', // AccountID: 5514525
                                'kiwi@macsportstravel.com', // AccountID: 4066597
                                'ecscesar@hotmail.com', // AccountID: 5528687
                                'sbhavin50@gmail.com', // AccountID: 4674972
                                'chokous@gmail.com', // AccountID: 4374483
                                'lilianalevintza@gmail.com', // AccountID: 4606794
                                'vinicius.augustoaz@gmail.com', // AccountID: 5445772
                                'anthonybiddulph@gmail.com', // AccountID: 5371360
                                'mauriciobbastos@gmail.com', // AccountID: 4827401
                                'bernardocavalcante@hotmail.com', // AccountID: 4696986
                                'guillaume@milesaddict.com', // AccountID: 4947644
                            ])
                                || $this->http->currentUrl() == 'https://admin.business.booking.com/direct-sso'
                            ) {
                                $this->http->SetInputValue("redirect_url", preg_replace("/^https:\/\/www.booking.com\/index\.html/", "https://secure.booking.com/myreservations.html", $this->http->Form['redirect_url']));
                            }
                        }
                        $this->http->PostForm();

                        break;
                    }// if ($account->b_active == 0 && $account->b_type == $primaryType)
                }// foreach ($accounts as $account)
            }// if (is_array($accounts))
            elseif (isset($account)) {
                $this->http->SetInputValue("switch_to_user_id", $account);

                if ($primaryType == 'business') {
                    $this->http->SetInputValue("redirect_url", "https://secure.booking.com/company/search.en-us.html?sb_travel_purpose=business");
                } else {
                    $this->http->SetInputValue("redirect_url", "https://www.booking.com/index.en-us.html");
                }
                $this->http->PostForm();
            }
        }// if (!empty($secondAccountLinkNew))
        else {
            $this->http->NormalizeURL($secondAccountLink);
            $this->http->GetURL($secondAccountLink);
        }
    }

    public function Parse()
    {
        if (strstr($this->http->currentUrl(), 'https://account.booking.com/sign-in?op_token=')) {
            $this->http->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
            $this->switchAccount("personal");
        }

        $this->switchAccount("personal");

        if ($this->http->currentUrl() == 'https://admin.business.booking.com/direct-sso') {
            $this->http->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
            $this->switchAccount("personal");
        }

        // Name
        $this->SetProperty("Name", beautifulName(trim(preg_replace(['/^Hi /', '/!$/'], "", $this->http->FindSingleNode("//a[contains(@class, 'popover_trigger')]//span[contains(@class, 'firstname')]")) . ' ' . $this->http->FindSingleNode("//a[contains(@class, 'popover_trigger')]//span[contains(@class, 'lastname')]"))));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName(trim(preg_replace(['/^Hi /', '/!$/'], "", $this->http->FindSingleNode("//span[@id = 'profile-menu-trigger--title']")))));
        }

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        // refs #14675
        $this->http->FilterHTML = false;
        $xBookingAid = $this->http->FindPreg("/'X-Booking-AID'\s*:\s*'([^\']+)/");
        $bLabel = $this->http->FindPreg("/b_label\s*:\s*'([^\']+)/");
        $sid = $this->http->FindPreg("/b_sid\s*:\s*'([^\']+)/");
        $this->http->GetURL("https://www.booking.com/index.html?aid={$xBookingAid};label={$bLabel};sid={$sid};sb_travel_purpose=leisure");

        if ($this->http->FindSingleNode("//div[contains(@class, 'book-challenge-roadtrip__progress')]/i/@class", null, true, "/gesprite/")) {
            $this->SetProperty("Status", "Genius");
        } elseif ($this->http->FindSingleNode("
                //p[contains(@class, 'genius_member_text')]//svg[contains(@class, 'genius-genius-logo')]/@class
                | //div[contains(@class, 'genius_member_text')]//svg[contains(@class, 'genius-genius-logo')]/@class
                | //span[contains(@class, 'user_avatar')]//svg[contains(@class, 'genius-genius-logo')]/@class
                | //span[contains(@class, 'user_avatar')]//svg[contains(@class, '-genius-levels-logo')]/@class
                | //span[contains(@class, 'user_name_block')]//*[contains(@class, 'genius_logo_profile_split')]/@class
            ")
        ) {
            $this->SetProperty("Status", "Genius");
        } else {
            $this->SetProperty("Status", $this->http->FindSingleNode('
                //span[(@class = "user_account_indication") and contains(text(), "Genius Level")]
                | //span[(@id = "profile-menu-trigger--content")]//span[contains(text(), "Genius Level")]
            '));
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindPreg("/b_user_emails:\s*\[\s*\{\s*email: \"{$this->AccountFields['Login']}/")
            && $this->http->FindPreg("/b_reg_user_full_name: \"\",/")
            && !empty($this->Properties['Status'])
        ) {
            $this->SetBalanceNA();
        }

        // Balance - Book
        if (count($this->http->FindNodes("//ul[contains(@class, 'ge_challenge_roadtrip')]/li[contains(@class, 'ge_challenge_check')]")) > 0) {
            $this->SetBalance(count($this->http->FindNodes("//ul[contains(@class, 'ge_challenge_roadtrip')]/li[contains(@class, 'e_challenge_check-booked')]")));
            $deadline = $this->http->FindPreg('/"deadline\":\"([^\"]+)/ims');

            if ($deadline && strtotime($deadline)) {
                $this->SetExpirationDate(strtotime($deadline));
            }
        }// if (count($this->browser->FindNodes("//ul[contains(@class, 'ge_challenge_roadtrip')]/li[contains(@class, 'ge_challenge_check')]")) > 0)
        $this->http->FilterHTML = true;

        // refs #18373
        $this->logger->info('My Rewards', ['Header' => 3]);
        $this->http->GetURL("https://secure.booking.com/rewardshub/overview.en-us.html");

        $headers = [
            "x-booking-aid"                     => $this->http->FindPreg("/'X-Booking-AID'\s*:\s*'([^\']+)/"),
            //            "x-booking-context-affiliate-id" => $this->http->FindPreg("/'X-Booking-AID':\s*'([^\']+)/"),
            "x-booking-context-currency"        => "USD",
            "x-booking-context-ip-country"      => "us",
            "x-booking-context-language"        => "en-us",
            "x-booking-context-visitor-country" => "us",
            "x-booking-csrf"                    => $this->http->FindPreg("/'X-Booking-CSRF'\s*:\s*'([^\']+)/"),
            "x-booking-pageview-id"             => $this->http->FindPreg("/'X-Booking-Pageview-Id'\s*:\s*'([^\']+)/"),
            "x-booking-session-id"              => $this->http->FindPreg("/'X-Booking-Session-Id'\s*:\s*'([^\']+)/"),
            "x-booking-target-host"             => "b-booking-pay-rewards-and-wallet-back-end.service",
        ];

        $user_id = $this->http->FindPreg("/\"b_user_id\":(\d+)/");
        $label = $this->http->FindPreg("/label=([^\&]+)/", false, $this->http->currentUrl());
        $sid = $this->http->FindPreg("/sid=([^\&]+)/", false, $this->http->currentUrl());

        if (!$label) {
            $label = "label={$label}&";
        }

        if (!$user_id || !$sid) {
            $this->logger->error("something went wrong");

            return;
        }

        $activities = $this->http->XPath->query('//div[contains(text(), "Reward activity")]/following-sibling::div[1]/div');
        $this->logger->debug("Total " . $activities->length . " rewards were found");

        $state = $this->http->FindSingleNode('//script[@data-capla-store-data="apollo"]');
        $this->logger->debug(var_export($state, true), ['pre' => true]);
        $response = $this->http->JsonLog(stripcslashes($state), 3, true);

        foreach ($activities as $activity) {
//            $status = $this->http->FindSingleNode("(.//div[contains(., 'Paid') and span])[1]", $activity);
            $exp = $this->http->FindSingleNode('.//div[span[contains(text(), "Expires:")]]', $activity, true, "/:\s*(.+)/");
            $token = $this->http->FindSingleNode('.//div[span[contains(text(), "Details:")]]', $activity, true, "/Number\)\s*(\d+)/");

            if (/*in_array($status, ['cancelled', 'rejected']) || */ strtotime($exp) < time()) {
//                $this->logger->debug("skip {$token} / {$status} / {$exp}");
                $this->logger->debug("skip {$token} / {$exp}");

                continue;
            }

            /*
            if (!in_array($status, ['sent', 'promised', 'action_needed', 'onhold', 'transaction_pending'])) {
                $this->sendNotification("rewards [{$status}]");
            }
            */

            $this->AddSubAccount([
                "Code"           => "bookingMyRewards" . md5($token),
                "DisplayName"    => "Reward (ARN #{$token})",
                "Balance"        => $this->http->FindSingleNode(".//span[contains(text(), '$')]", $activity),
                "Currency"       => $this->http->FindSingleNode(".//span[contains(text(), '$')]", $activity, true, "/([^\d]+)/"),
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($activities as $activity)

        $this->logger->info('My Wallet', ['Header' => 3]);
        $this->http->RetryCount = 0;
        $data = [
            "operationName" => "Wallet",
            "variables"     => [
                "walletQueryInput" => [
                    "includeTransactions" => false,
                    "pagination"          => [
                        "offset"      => 0,
                        "rowsPerPage" => 0,
                    ],
                ],
            ],
            "extensions"    => [],
            "query"         => "query Wallet(\$walletQueryInput: RewardsWalletInput!) {\n  rewardswallet {\n    wallet(input: \$walletQueryInput) {\n      result {\n        bBookingpayBalance {\n          bConvertedTotalWithSymbol\n          bConvertedTotalWithoutSymbol\n          bTotalAmount\n          __typename\n        }\n        bBookingpayCashBalance {\n          bConvertedTotalWithSymbol\n          bConvertedTotalWithoutSymbol\n          bTotalAmount\n          __typename\n        }\n        bBookingpayHasCashBackBalance {\n          bConvertedTotalWithSymbol\n          bConvertedTotalWithoutSymbol\n          bTotalAmount\n          bTotalWithSymbol\n          bTotalCurrencySymbol\n          __typename\n        }\n        bBookingpayCreditCardInstruments {\n          currency\n          instrumentId\n          metaData {\n            expiryDate\n            entityId\n            type\n            countryCode\n            lastDigits\n            cardstashId\n            __typename\n          }\n          __typename\n        }\n        bBookingpayCashBalanceDetailed {\n          balance {\n            bConvertedPartWithSymbol\n            __typename\n          }\n          date\n          __typename\n        }\n        bBookingpayCashBackBalanceDetailed {\n          balance {\n            bConvertedPartWithSymbol\n            __typename\n          }\n          date\n          __typename\n        }\n        bBookingpayWalletCurrency {\n          original\n          converted\n          __typename\n        }\n        bBookingpayZeroBalance {\n          bZeroWithSymbol\n          bZeroAmount\n          __typename\n        }\n        bBookingpayHasTransactions\n        bBookingpayHasReceivedVouchers\n        bBookingpayVouchers {\n          voucherId\n          type\n          title\n          balance {\n            bConvertedVoucherWithSymbol\n            bConvertedVoucherAmount\n            bConvertedVoucherCurrency\n            __typename\n          }\n          conditions {\n            startDate\n            endDate\n            __typename\n          }\n          shortTitle\n          validUntil\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n",
        ];
        $this->http->PostURL("https://secure.booking.com/dml/graphql?{$label}sid={$sid}&lang=en-us", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, "bBookingpayBalance");
        $myWallet = $response->data->rewardswallet->wallet->result->bBookingpayBalance->bConvertedTotalWithSymbol ?? null;

        if (isset($myWallet)) {
            $myWallet = html_entity_decode($myWallet);
            $this->AddSubAccount([
                "Code"        => "bookingMyWallet",
                "DisplayName" => "My Wallet",
                "Balance"     => $myWallet,
                "Currency"    => $this->http->FindPreg("/([^\d]+)/", false, $myWallet),
            ]);
        }
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking number",
                "Type"     => "string",
                "Size"     => 40,
                "Required" => true,
            ],
            "Pin" => [
                "Type"    => "string",
                "Caption" => "PIN Code",
                "Size"    => 40,
                //"Value" => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ParseItineraries()
    {
        $result = $this->parsePersonalItineraries();
        $result = array_merge($result, $this->parseBusinessItineraries());

        return $result;
    }

    public function ParseCancelled($conf)
    {
        $this->logger->notice(__METHOD__);

        $result = [];
        $this->browser->FilterHTML = false;
        // ConfirmationNumber
        $result['Kind'] = "R";
        $result['ConfirmationNumber'] = $conf;
        $result['Status'] = $this->browser->FindSingleNode("//text()[normalize-space()='Your booking was']/following::text()[normalize-space()!=''][1]");
        $result['Cancelled'] = true;

        $this->logger->info('Parse Itinerary #' . $result['ConfirmationNumber'], ['Header' => 4]);

        // HotelName
        $result['HotelName'] = $this->browser->FindSingleNode("//div[@class='cancelled-view__hotel-name']/a");
        // CheckInDate
        $result['CheckInDate'] = strtotime($this->browser->FindSingleNode("//div[normalize-space()='Check-in']/following-sibling::div[1]"));
        // CheckOutDate
        $result['CheckOutDate'] = strtotime($this->browser->FindSingleNode("//div[normalize-space()='Check-out']/following-sibling::div[1]"));
        // Rooms
        $result['Rooms'] = $this->browser->FindSingleNode("//div[normalize-space()='Stay Details' or normalize-space()='Stay details' ]/following-sibling::div[1]",
            null, true, "/(\d+) rooms?/");

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ParseJson($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();

        $f->ota()->confirmation($data->publicReference->formattedReference);

        if (count($data->airOrder->airlineReferencesByLeg) > 1) {
            $this->sendNotification('$data->airOrder->airlineReferencesByLeg > 1 // MI');
        }
        $f->general()->confirmation($data->airOrder->airlineReferencesByLeg[0]->reference, 'Booking reference');

        foreach ($data->passengers as $passenger) {
            $f->general()->traveller("{$passenger->firstName} {$passenger->lastName}");
        }

        foreach ($data->airOrder->flightSegments as $flightSegment) {
            foreach ($flightSegment->legs as $leg) {
                $s = $f->addSegment();
                $s->airline()->name($leg->flightInfo->carrierInfo->operatingCarrier);
                $s->airline()->number($leg->flightInfo->flightNumber);

                $s->departure()->name($leg->departureAirport->name);
                $s->departure()->code($leg->departureAirport->code);
                $s->departure()->date2($leg->departureTime);
                $s->arrival()->name($leg->arrivalAirport->name);
                $s->arrival()->code($leg->arrivalAirport->code);
                $s->arrival()->date2($leg->arrivalTime);
                $s->extra()->cabin($leg->cabinClass);
            }
        }
        $f->price()->total($data->totalPrice->total->units);
        $f->price()->currency($data->totalPrice->total->currencyCode);
        $f->price()->cost($data->totalPrice->baseFare->units);
        //$f->price()->fee($data->totalPrice->fee->units);
        $f->price()->tax($data->totalPrice->tax->units);
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://secure.booking.com/confirmation.en-gb.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm(null, '//input[@name = "pincode"]/ancestor::form[contains(@class, "user_access_form")]')) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $this->http->SetInputValue("bn", $arFields['ConfNo']);
        $this->http->SetInputValue("pincode", $arFields['Pin']);
        $this->sendNotification('check confirmation // MI');

        if (!$this->http->PostForm()) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $checkPay = $this->http->FindSingleNode("//text()[contains(.,'You paid ') and contains(.,' for this booking')]");

        if (!empty($checkPay)) {
            $it = $this->ParseConfirmationPaid();
        } else {
            $it = $this->ParseConfirmationBooking($arFields['ConfNo']);
        }

        if (!ArrayVal($it, 'HotelName')) {
            // Sorry, we don't recognise that PIN code. Please sign in, create an account using the email you booked with, or request a new confirmation email for your reservation.
            // Sorry, that PIN code doesn't match your Booking number. Please double check both numbers and try again.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, we don\'t recognise that PIN code.")] | //div[contains(text(), "Sorry, that PIN code doesn\'t match your Booking number")]')) {
                $it = null;

                return $message;
            }

            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $it = [$it];

        return null;
    }

    public function ParseConfirmationPaid()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        // ConfirmationNumber
        $result['Kind'] = "R";
        $result['ConfirmationNumber'] =
            $this->http->FindSingleNode("//div[contains(text(), 'Confirmation number')]/following-sibling::div[1]", null, true, '/^(\w+)$/');
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode("//h3[contains(@class,'hotel_name')]/a");
        // Address
        $result['Address'] = $this->http->FindSingleNode("//div[contains(text(), 'Address')]/following-sibling::div[1]/a");
        // Phone
        $result['Phone'] = $this->http->FindSingleNode("//div[contains(text(), 'Phone')]/following-sibling::div[1]/descendant::text()[normalize-space()!=''][last()]", null, true, "/\b([+\- \d\(\)]+)$/");

        // CheckInDate
        $checkInDate = $this->http->FindSingleNode("//div[contains(text(), 'Check-in')]/following-sibling::div[1]");

        if (!empty($checkInDate)) {
            $this->logger->debug("CheckInDate: " . $checkInDate);
            $date = strtotime($checkInDate);
            $result['CheckInDate'] = $date;
        }
        // CheckOutDate
        $checkOutDate = $this->http->FindSingleNode("//div[contains(text(), 'Check-out')]/following-sibling::div[1]");

        if (!empty($checkOutDate)) {
            $this->logger->debug("CheckOutDate: " . $checkOutDate);
            $date = strtotime($checkOutDate);
            $result['CheckOutDate'] = $date;
        }

        // Rooms
        $result['Rooms'] = $this->http->FindSingleNode("//div[contains(text(), 'Booking Details')]/following-sibling::div[1]", null, true, "/(\d+) rooms/");
        // CancellationPolicy
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//div[contains(text(), 'Cancellation cost')]/following-sibling::div[1]");
        // RoomType
        $result['RoomType'] = $this->http->FindSingleNode("//div[contains(@class, 'room_title')]");
        // GuestNames
        $result['GuestNames'] = array_map('beautifulName', array_unique($this->http->FindNodes("//div[contains(text(), 'Guest name')]/following-sibling::div[1]")));
        $result['GuestNames'] = array_filter($result['GuestNames'],
            function ($el) {
                return !empty($el);
            });
        // Guests
        $result['Guests'] = array_sum($this->http->FindNodes("//div[contains(text(), 'Guest name')]/ancestor::div[1]/following-sibling::div[1]", null, "/(\d+) guests?/"));

        if (count($result['GuestNames']) > $result['Guests']) {
            $result['Guests'] = count($result['GuestNames']);
        }
        // Total
        $totalStr = $this->http->FindSingleNode("//div[contains(text(), 'Price')]/following-sibling::div[1]");
        $this->logger->debug("Total: [{$totalStr}]");
        $result['Total'] = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $totalStr);
        // Currency
        $currency = $this->http->FindPreg("#[A-Z]{3}#ims", $totalStr);

        if (!$currency) {
            $currency = $this->getCurrencyCode($this->http->FindPreg("#^([^\d\,\.]+)#ims", $totalStr));
        }

        if (!$currency) {
            $currency = $this->currency($totalStr);
        }
        // Currency
        $result['Currency'] = $currency;

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ParseConfirmationBooking($conf = null)
    {
        $this->logger->notice(__METHOD__);
        $confirmationPage = $this->http->FindPreg('/\/confirmation/', false, $this->http->currentUrl());
        $viewBookingUrl = $this->http->FindSingleNode('(//span[contains(text(), "View booking")])[1]/ancestor::a[1]/@href');

        if ($confirmationPage && $viewBookingUrl) {
            $this->http->NormalizeURL($viewBookingUrl);
            $this->http->GetURL($viewBookingUrl);
        }

        if ($status = $this->http->FindSingleNode("//h1[starts-with(normalize-space(),'Your booking was')]/b[contains(.,'cancelled')]")) {
            require_once __DIR__ . "/TAccountCheckerBookingSelenium.php";
            $bookingSelenium = new TAccountCheckerBookingSelenium();
            $bookingSelenium->browser = $this->http;
            $bookingSelenium->logger = $this->logger;
            $bookingSelenium->globalLogger = $this->globalLogger; // fixed notifications

            return $bookingSelenium->ParseCancelled($conf);
        }

        $result = [];
        // ConfirmationNumber
        $result['Kind'] = "R";
        $result['ConfirmationNumber'] =
            $this->http->FindSingleNode("//div[@class = 'mb-book-number']/b[1]") ?:
            $this->http->FindSingleNode("//*[self::p or self::li][contains(text(), 'CONFIRMATION NUMBER:') or contains(text(), 'Confirmation number:')]", null, true, '/:\s*(\w+)/');

        $this->logger->info('Parsing Itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);

        // HotelName
        $result['HotelName'] =
            $this->http->FindSingleNode("//h1[contains(@class, 'hotel-name')]/a")
            ?? $this->http->FindSingleNode("//div[contains(@class, 'hotel-name') and not(contains(text(), 'Stay in Control'))]")
            ?? $this->http->FindSingleNode('//text()[contains(., "ooking at")]/following::a[@data-testid="name-archor"]')
        ;

        $date = $this->http->FindSingleNode("//div[contains(text(),'Check-in')]/following-sibling::*[1]/*[1]") ??
            $this->http->FindSingleNode("//li[contains(text(),'Check-in')]/following-sibling::li[1]");
        $time = $this->http->FindSingleNode("//div[contains(text(),'Check-in')]/following-sibling::*[1]/*[2]") ??
            $this->http->FindSingleNode("//li[contains(text(),'Check-in')]/following-sibling::li[2]");
        $time = $this->http->FindPreg('/^(.+?) - /', false, $time) ??
            $this->http->FindPreg('/^from (.+)/', false, $time);
        $this->logger->debug("CheckInDate: {$date} {$time}");

        if (isset($time)) {
            $result['CheckInDate'] = strtotime($time, strtotime($date));
        } else {
            $result['CheckInDate'] = strtotime($date);
        }

        $date = $this->http->FindSingleNode("//div[contains(text(),'Check-out')]/following-sibling::*[1]/*[1]") ??
            $this->http->FindSingleNode("//li[contains(text(),'Check-out')]/following-sibling::li[1]");
        $time = $this->http->FindSingleNode("//div[contains(text(),'Check-out')]/following-sibling::*[1]/*[2]") ??
            $this->http->FindSingleNode("//li[contains(text(),'Check-out')]/following-sibling::li[2]");
        $time = $this->http->FindPreg('/^.+? - (.+)/', false, $time) ??
            $this->http->FindPreg('/^until (.+)/', false, $time);
        $this->logger->debug("CheckOutDate: {$date} {$time}");

        if (isset($time)) {
            $result['CheckOutDate'] = strtotime($time, strtotime($date));
        } else {
            $result['CheckOutDate'] = strtotime($date);
        }

        // Rooms
        $result['Rooms'] = count($this->http->FindNodes("//div[contains(@class, 'mb-section--room')]"));
        // CancellationPolicy
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//div[contains(@class, 'mb-notice--cancellation')] | //p[contains(@class, 'mb_cancellation_timeline__cancellation-info')]");
        // RoomType
        $result['RoomType'] = $result['RateType'] = $this->http->FindSingleNode("(//h2[contains(@class, 'room-type')])[1]");
        // RoomTypeDescription
        $result['RoomTypeDescription'] = $this->http->FindSingleNode("((//div[@class='section'])[3]/div[@class='room'])[2]//p[contains(text(), 'preference') and not (@class)]", null, true, "/\(([\w\-]+) preference\)/ims");
        // GuestNames
        $result['GuestNames'] = array_map('beautifulName', array_unique($this->http->FindNodes("//div[contains(@class, 'room__guest-info')]//span[contains(@class, 'b_guest_name')]")));
        $result['GuestNames'] = array_filter($result['GuestNames'],
            function ($el) {
                return !empty($el);
            });
        // Guests
        $result['Guests'] =
            $this->http->FindSingleNode("//select[@name = 'nr_guests']/option[@selected and not(contains(text(), 'guest'))]")
            ?? $this->http->FindSingleNode("//span[contains(text(), 'Number of guests:')]/following-sibling::span", null, true, "/(\d+)\s*adult/ims")
        ;

        if (count($result['GuestNames']) > $result['Guests']) {
            $result['Guests'] = count($result['GuestNames']);
        }
        // Total
        $totalStr = $this->http->FindSingleNode("//span[contains(@class, 'mb-price__unit--secondary')]");

        if (!$totalStr) {
            $totalStr = $this->http->FindSingleNode("//span[contains(@class, 'mb-price__unit--primary')]");
        }
        $this->logger->debug("Total: [{$totalStr}]");
        $totalStr = str_replace('Rs. ', '', $totalStr);
        $this->logger->debug("Total: [{$totalStr}]");
        $result['Total'] = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $totalStr);
        // Currency
        $currency = $this->http->FindPreg("#[A-Z]{3}#ims", $totalStr);

        if (!$currency) {
            $currency = $this->getCurrencyCode($this->http->FindPreg("#^([^\d\,\.]+)#ims", false, $totalStr));
        }

        if (!$currency) {
            $currency = $this->currency($totalStr);
        }
        // Currency
        $result['Currency'] = $currency;
        // Phone
        $result['Phone'] = $this->http->FindSingleNode("//span[contains(@class, 'hotel_address')]", null, true, "/Phone:\s*([^<]+)\s*\)\s*$/ims");

        if (!isset($result['Phone'])) {
            $result['Phone'] = $this->http->FindPreg("/mb-hotel-info__block[^>]+>\s*Phone:[^\+\d]+([\+\d\(\)\s]+)/ims");
        }

        if (!isset($result['Phone'])) {
            $result['Phone'] = $this->http->FindSingleNode("//div[contains(@class, 'mb-info__reversed')]//span[@class = 'u-phone']");
        }
        // Address
        $country = $this->http->FindSingleNode('//p[contains(@class, "hotel-address")]/img/@src', null, true, '/\/([^\.\/]+)\/[^\.\/]+\.png$/ims');

        if (isset($country)) {
            $country = ", " . strtoupper($country);
        }
        $this->logger->debug("Country: $country");
        $result['Address'] = $this->http->FindSingleNode("//span[contains(@class, 'hotel_address')]", null, true, "/([^<\(]+)/ims") . $country;

        if (empty($result['Address'])) {
            $result['Address'] = str_replace(',,', ',', implode(', ', $this->http->FindNodes("//div[contains(@class, 'mb-hotel-info__address')]/text()")));
            $result['Address'] = trim($result['Address'], ' ,\t\n');
        }
        // extended address
        if ($hotelLink = $this->http->FindSingleNode("//div[contains(@class, 'mb-hotel-name')]/a/@href | //h1[contains(@class, 'hotel-name')]/a/@href")) {
            $this->logger->notice("Loading hotel descriptions");
            $this->http->GetURL($hotelLink);

            if ($address = $this->http->FindSingleNode("//p[@id = 'showMap2']/span[@data-node_tt_id='location_score_tooltip'][last()]")) {
                $result['Address'] = $address . $country;
            }
        }// if ($hotelLink = $this->http->FindSingleNode("//div[contains(@class, 'mb-hotel-name')]/a/@href"))

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function parsePersonalItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse Personal', ['Header' => 3]);
        $this->http->GetURL('https://secure.booking.com/myreservations.html');
        $csrf = $this->http->FindPreg("/'X-Booking-CSRF':\s*'(.+?)'/");

        if (!$csrf) {
            sleep(3);
            $this->http->GetURL('https://secure.booking.com/myreservations.html');
            $csrf = $this->http->FindPreg("/'X-Booking-CSRF':\s*'(.+?)'/");

            if ($csrf) {
                $this->sendNotification('success retry // MI');
            }
        }

        if (!$csrf) {
            $this->switchAccount('personal');

            if ($this->http->currentUrl() == 'https://admin.business.booking.com/direct-sso') {
                $this->http->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
                $this->switchAccount("personal");
            }

            $csrf = $this->http->FindPreg("/'X-Booking-CSRF':\s*'(.+?)'/");
        }

        if (!$csrf) {
            $this->sendNotification('check personal csrf // MI');

            return [];
        }

        $headers = [
            'X-Booking-CSRF' => $csrf,
        ];
        $this->http->GetURL('https://secure.booking.com/reservations?thumbnail_width=312&thumbnail_height=172&page_size=10&vertical_products=BOOKING_HOTEL,BASIC,ATTRACTIONS,CARS,FLIGHTS', $headers);
        $data = $this->http->JsonLog(null, 3, true);

        if (!isset($data['reservations'])) {
            $this->sendNotification('check personal itineraries // MI');

            return [];
        }

        $result = [];

        foreach (ArrayVal($data, 'reservations', []) as $item) {
            $isPast = $this->arrayVal($item, ['meta', 'is_past']);

            if ($isPast && !$this->ParsePastIts) {
                continue;
            }

            if (!empty(ArrayVal($item, 'encrypted_order_id'))) {
                $this->http->GetURL("https://flights.booking.com/api/order/" . ArrayVal($item, 'encrypted_order_id') . "?includeAvailableExtras=1", $headers);
                $response = $this->http->JsonLog();
                $this->ParseJson($response);

                continue;
            }

            $type = ArrayVal($item, 'reservation_type'); // BOOKING_HOTEL | CARS
            $isCancelled = $this->arrayVal($item, ['meta', 'is_cancelled']);

            if ($isCancelled) {
                $conf = ArrayVal($item, 'id');
                $this->logger->info("Parse Itinerary #{$conf}", ['Header' => 4]);

                if ($type == 'CARS') {
                    $itin = [
                        'Kind'      => 'L',
                        'Number'    => $conf,
                        'Cancelled' => true,
                    ];
                } else {
                    $itin = [
                        'Kind'               => 'R',
                        'ConfirmationNumber' => $conf,
                        'Cancelled'          => true,
                    ];
                }
                $result[] = $itin;
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($itin, true), ['pre' => true]);

                continue;
            }

            if ($type === 'CARS') {
                $conf = ArrayVal($item, 'id');
                $this->logger->info("Parse Itinerary #{$conf}", ['Header' => 4]);
                $itin = [
                    'Kind'            => 'L',
                    'Number'          => $conf,
                    'Total'           => $this->arrayVal($item, ['price', 'value']),
                    'Currency'        => $this->arrayVal($item, ['price', 'currency_code']),
                    'Status'          => $this->arrayVal($item, ['status', 'display_text']),
                    'PickupDatetime'  => strtotime($this->arrayVal($item, ['pick_up', 'datetime', 'iso']), false),
                    'PickupLocation'  => $this->arrayVal($item, ['pick_up', 'location', 'city']),
                    'DropoffDatetime' => strtotime($this->arrayVal($item, ['drop_off', 'datetime', 'iso']), false),
                    'DropoffLocation' => $this->arrayVal($item, ['drop_off', 'location', 'city']),
                    'RentalCompany'   => $this->arrayVal($item, ['product', 'supplier']),
                    'CarModel'        => $this->arrayVal($item, ['product', 'name']),
                    'CarType'         => $this->arrayVal($item, ['product', 'car_class']),
                ];
                $carImageUrl = $this->arrayVal($item, ['product', 'photo']);
                // checking format, because once was with a space
                if (filter_var($carImageUrl, FILTER_VALIDATE_URL) !== false) {
                    $itin['CarImageUrl'] = $carImageUrl;
                }
                $result[] = $itin;
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($itin, true), ['pre' => true]);

                continue;
            }

            $bookingUrl = ArrayVal($item, 'booking_url');

            if ($isPast or !$bookingUrl) {
                $this->logger->error('booking url not found');

                continue;
            }
            $this->http->NormalizeURL($bookingUrl);
            $headers = [
                'X-Booking-CSRF' => $csrf,
            ];
            $this->http->GetURL($bookingUrl, $headers);

            // is_cancelled:0 - but cancelled
            if ($this->http->FindSingleNode("//text()[contains(.,'Your booking was successfully canceled')]")) {
                $conf = ArrayVal($item, 'id');
                $result[] = $this->ParseCancelled($conf);

                continue;
            }
            $checkPay = $this->http->FindSingleNode("//text()[contains(.,'You paid ') and contains(.,' for this booking')]");

            if (!empty($checkPay)) {
                if ($res = $this->ParseConfirmationPaid()) {
                    $result[] = $res;
                }
            } else {
                if ($res = $this->ParseConfirmationBooking()) {
                    $result[] = $res;
                }
            }
        }

        return $result;
    }

    private function requestBusinessItineraries()
    {
        $this->logger->notice(__METHOD__);
        $csrf = $this->http->FindPreg('/"csrfToken":"(.+?)"/');

        $now = new DateTime();
        $zero = new DateTime('1970-01-01');
        $epochDays = $now->diff($zero)->format('%a');
        $payload = '{"operationName":"getHotelReservations","variables":{"input":{"group":null,"label":null,"limit":10,"page":1,"bookedFor":null,"bookedBy":null,"reservationSource":"ALL","reservationType":"ALL","budgetPolicy":"ALL","checkInEpochDay":{"min":' . $epochDays . ',"max":0},"checkOutEpochDay":{"min":0,"max":0},"bookingDateEpoch":{"min":0,"max":0},"affiliatesIds":[]}},"query":"query getHotelReservations($input: InputReservationFilter!) {\n  hotelReservations(input: $input) {\n    status\n    hotelReservations {\n      id\n      pnr\n      pinCode\n      createdEpoch\n      lastChangeEpoch\n      checkInDay\n      checkOutDay\n      bookingUrl\n      cancelled\n      source: bookingSource\n      lync {\n        id\n        tmc\n        obe\n        customer\n        __typename\n      }\n      labels {\n        id\n        name\n        __typename\n      }\n      bookedFor {\n        id\n        firstname\n        lastname\n        __typename\n      }\n      bookedBy {\n        id\n        firstname\n        lastname\n        __typename\n      }\n      groups {\n        id\n        name\n        __typename\n      }\n      price\n      priceProperty\n      hotel {\n        id\n        currency: currencyCode\n        name\n        address\n        city: cityData {\n          name: localizedName\n          latitude\n          longitude\n          __typename\n        }\n        country {\n          cc1\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  hotelReservationsAggregation(input: $input) {\n    total\n    __typename\n  }\n}\n"}';
        $headers = [
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'X-Booking-CSRF'  => $csrf,
        ];
        $this->http->PostURL('https://www.booking.com/bbmanage/data', $payload, $headers);
    }

    private function parseBusinessItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse Business', ['Header' => 3]);
        $this->http->GetURL('https://secure.booking.com/myreservations.html');
        $profileSwitchUrl = $this->http->FindPreg("/b_profile_switch_url:\s*'([^\']+)/");

        if (!$profileSwitchUrl) {
            $this->logger->info('business url not found');

            return [];
        }
        $this->switchAccount();

        if ($this->http->currentUrl() == 'https://admin.business.booking.com/direct-sso') {
            $this->http->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
            $this->switchAccount();
        }

        $this->requestBusinessItineraries();

        $result = [];
        $data = $this->http->JsonLog(null, 0, true);
        $hotelReservations = $this->arrayVal($data, ['data', 'hotelReservations', 'hotelReservations'], []);

        foreach ($hotelReservations as $item) {
            if (ArrayVal($item, 'cancelled') == true) {
                $conf = ArrayVal($item, 'id');
                $this->logger->info("Parse Itinerary #{$conf}", ['Header' => 4]);
                $itin = [
                    'Kind'               => 'R',
                    'ConfirmationNumber' => $conf,
                    'Cancelled'          => true,
                ];
                $result[] = $itin;
                $this->logger->debug('Parsed Itinerary:');
                $this->logger->debug(var_export($itin, true), ['pre' => true]);

                continue;
            }

            $url = ArrayVal($item, 'bookingUrl');
            $this->http->GetURL($url);
            $checkPay = $this->http->FindSingleNode("//text()[contains(.,'You paid ') and contains(.,' for this booking')]");

            if (!empty($checkPay)) {
                $result[] = $this->ParseConfirmationPaid();
            } else {
                $result[] = $this->ParseConfirmationBooking();
            }
        }

        return $result;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function getCurrencyCode($symbol)
    {
        switch ($symbol) {
            case '€':
                $currency = 'EUR';

                break;

            case '$':
                $currency = 'USD';

                break;

            case '£':
                $currency = 'GBP';

                break;

            case 'R$':
                $currency = 'BRL';

                break;

            case 'A$':
                $currency = 'AUD';

                break;

            case 'S$':
                $currency = 'SGD';

                break;

            case 'Rp':
                $currency = 'IDR';

                break;

            case 'Rs':
                $currency = 'INR';

                break;

            case 'P':
                $currency = 'PHP';

                break;

            case '¥':
                $currency = 'JPY';

                break;

            case 'TL':
                $currency = 'TRY';

                break;

            case 'RM':
                $currency = 'MYR';

                break;

            case 'NT$':
                $currency = 'TWD';

                break;

            case 'zł':
                $currency = 'PLN';

                break;

            default:
                if (!empty($currency)) {
                    $this->sendNotification("booking. Migrating to v2, need to fix currency code: {$currency}");
                }
                $currency = null;
        }

        return $currency;
    }

    private function loginSuccessful($isLoggedIn = false)
    {
        $this->logger->notice(__METHOD__);

        if (
            $isLoggedIn == true
            && $this->http->FindSingleNode('//a[contains(@class, "user_access_menu_auth_low_not_me")]')
        ) {
            return false;
        }
        // Access is allowed
        if (
            $this->http->FindSingleNode("//input[contains(@value, 'Sign out')]/@value")
            || $this->http->FindPreg('/input type=."hidden." name=."logout."/')
            || $this->http->FindPreg('/b_user_emails:\s*\[\s*\{\s*email: "([^\"]+)"/')
            || $this->http->FindPreg('/businessUserId":\d+/')
        ) {
            return true;
        }

        return false;
    }

    private function selenium($currentUrl, $auth = false)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL($currentUrl);
            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'username']"), 7);
            /*
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'hidden-password']"), 3);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[@type = "submit"]"), 0);
            // save page to logs
            $this->saveToLogs($selenium);

            if (!$login || !$pass || !$keepMeSignedIn || !$btn) {
                $this->logger->error("something went wrong");

                return false;
            }

            $pass->click();
            $pass->sendKeys($this->AccountFields['Pass']);
            */

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->saveToLogs($selenium);
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo
        }

        return $result;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
}
