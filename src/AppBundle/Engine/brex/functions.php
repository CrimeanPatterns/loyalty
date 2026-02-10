<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;

class TAccountCheckerBrex extends TAccountChecker
{
//    use OtcHelper;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://dashboard.brex.com/rewards';
    private const XPATH_SUCCESS = '//h1[contains(text(), "Rewards") or contains(text(), "Accounts")]';
    private const XPATH_ERROR = '//p[contains(@class, "okta-form-input-error")] | //div[contains(@class, "okta-form-infobox-error")]/p';
    /**
     * @var HttpBrowser
     */
    public $browser;

    private $headers = [
        "Accept"           => "*/*",
        "Accept-Encoding"  => "gzip, deflate, br",
        "Content-Type"     => "application/json",
        'X-Requested-With' => "XMLHttpRequest",
    ];

    private $graphqlHeaders = [
        "Accept"            => "*/*",
        "content-type"      => "application/json",
        "Origin"            => "https://dashboard.brex.com",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->RetryCount = 0;

        $this->http->maxRequests = 700;

        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['authorization'])) {
            return false;
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }
        unset($this->State['authorization']);
        unset($this->State['login_challenge']);
        unset($this->State['stateToken']);
        unset($this->State['id']);

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

//        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "identifier"]'), 20);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Sign in"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$button) {
            return $this->checkErrors();
        }

        $this->driver->executeScript('var rememberMe = document.querySelector(\'input[name = "rememberMe"]\'); if (rememberMe && rememberMe.checked == false) rememberMe.click();');
        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();
        $button->click();

        sleep(2);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "credentials.passcode"]'), 7);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@value="Sign in" or @value = "Verify"]'), 0);
        $this->saveResponse();

        if (!$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();
        sleep(3);

        /*
         $this->http->GetURL('https://accounts-api.brex.com/oauth2/default/v1/authorize?client_id=dashboard&redirect_uri=https%3A%2F%2Fdashboard.brex.com%2Fauth-invisible.html&response_type=code&scope=openid%20profile%20offline_access&state=NzQsMTA3LDk2LDIyMCwyMDEsMjE2LDIwNyw0OCwxOTIsNzcsMTU2LDExNywxMTcsMTQwLDgxLDYzLDE1MCwyMDAsMTEzLDIyMSwzNSw0MSwyMDQsNjQsNTgsMjUyLDkxLDQ0LDE5MCwyNywxNDEsMTE4&prompt=none&nonce=MjAxLDU2LDEwMiwxMTgsMTE0LDIxMywyMzAsMTEyLDI0OCwxMzUsMTQ0LDE5OSw2MiwzNCwxMywxOTIsMTczLDE2NSwyLDE1NSwxNDksMTgwLDI1NCwxNjAsOTEsNzYsMTE3LDE3NywyMTMsMTc2LDEyNCwyMDQ%3D&code_challenge=h_TKM27hIQmN13rmjIalsOL1NfnM_srdkXOU8qf0bmM&code_challenge_method=S256');

        if ($this->http->Response['code'] != 200) {
            // it works
            if ($this->http->FindPreg("#https://dashboard.brex.com/auth.html\?code=([^&]+)#", false, $this->http->currentUrl())) {
                return true;
            }

            return $this->checkErrors();
        }

        $this->http->PostURL("https://api.veyondcard.com/v1/graphql", '{"query":"query ApplicationSessionQuery { anonymousSession(suspendable: false) { sessionId } }","variables":{},"operationName":"ApplicationSessionQuery"}', $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data->anonymousSession->sessionId)) {
            $this->logger->error("biometrics_id (sessionId) not found");

            return false;
        }

        $this->State['biometrics_id'] = $response->data->anonymousSession->sessionId;

        $data = [
            "username" => $this->AccountFields['Login'],
            "options"  => [
                "warnBeforePasswordExpired" => true,
                "multiOptionalFactorEnroll" => false,
            ],
        ];
        $this->http->PostURL("https://accounts-api.brex.com/api/v1/authn", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->stateToken)) {
            return $this->checkErrors();
        }

        $data = [
            "password"   => $this->AccountFields['Pass'],
            "stateToken" => $response->stateToken,
        ];
        $this->http->PostURL("https://accounts-api.brex.com/api/v1/authn/factors/password/verify?rememberDevice=true", json_encode($data), $this->headers);
        */

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR . ' | //p[contains(text(), "Enter the 6-digit verification code sent") or contains(text(), "You can find the 6-digit code in your authenticator app.") or contains(text(), "Open your authenticator app to find your code.")] | //span[@id = "mfa-email"] | //div[@data-se="phone_number"] | //div[contains(text(), "A code was sent to")] | ' . self::XPATH_SUCCESS), 20);
        $this->saveResponse();

        if ($phone = $this->waitForElement(WebDriverBy::xpath('//div[@data-se="phone_number"]'), 0)) {
            $phone->click();
            $this->saveResponse();

            $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR . ' | //p[contains(text(), "Enter the 6-digit verification code sent") or contains(text(), "You can find the 6-digit code in your authenticator app.") or contains(text(), "Open your authenticator app to find your code.")] | //span[@id = "mfa-email"] | //div[contains(text(), "A code was sent to")] | ' . self::XPATH_SUCCESS), 20);
            $this->saveResponse();
        }

        if ($question = $this->http->FindSingleNode('//p[contains(text(), "Enter the 6-digit verification code sent") or contains(text(), "You can find the 6-digit code in your authenticator app.") or contains(text(), "Open your authenticator app to find your code.")] | //div[contains(text(), "A code was sent to")]')) {
            $questionNodes = $this->http->FindNodes('//div[contains(text(), "A code was sent to")]/node()[position() < 3]');

            if ($questionNodes) {
                $question = trim(implode(" ", $questionNodes));
            }

            $this->holdSession();
            $this->AskQuestion($question, null, 'Question');

            return false;
        }

        if ($this->verificationViaEmail()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode(self::XPATH_ERROR)) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "Wrong email or password. Try again or select forgot password to reset.")
                || strstr($message, "Please enter a password")
                || strstr($message, "Incorrect username/password.")
                || $message == 'Unable to sign in'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        /*
        $response = $this->http->JsonLog();
        $result = $response->status ?? null;

        switch ($result) {
            case 'MFA_REQUIRED':
                $type = $response->_embedded->factors[0]->factorType ?? null;

                if (!isset($response->stateToken)) {
                    $this->logger->error("stateToken not found");

                    return false;
                }

                $this->State['id'] = $response->_embedded->factors[0]->id;

                $data = [
                    "method"     => "emitMFAChallengePagePresented",
                    "stateToken" => $response->stateToken,
                    "params"     => [
                        "useCase" => "mfa",
                    ],
                ];
                $this->http->PostURL("https://accounts.brex.com/activity", json_encode($data), $this->headers);
                $this->http->JsonLog();

                $this->State['stateToken'] = $response->stateToken;

                if ($type == 'question') {
                    $type = $response->_embedded->factors[1]->factorType ?? null;
                    $this->State['id'] = $response->_embedded->factors[1]->id;
                }

                $data = [
                    "passCode"   => "",
                    "stateToken" => $this->State['stateToken'],
                ];
                $this->http->PostURL("https://accounts-api.brex.com/api/v1/authn/factors/{$this->State['id']}/verify?rememberDevice=false", json_encode($data), $this->headers);
                $response = $this->http->JsonLog();
                $status = $response->status ?? null;

                if ($status != 'MFA_CHALLENGE') {
                    return false;
                }

                switch ($type) {
                    case 'sms':
                        $number = $response->_embedded->factor->profile->phoneNumber ?? null;

                        if (!$number) {
                            $this->logger->error("phone not found");

                            return false;
                        }

                        $this->AskQuestion("We sent a 6-digit code to your phone number ending in {$number}.", null, 'Question');

                        return false;

                    case 'token:hotp':
                        $this->AskQuestion("Enter the 6-digit code from your authenticator app.", null, 'Question');

                        return false;

                    default:
                        $this->logger->notice("new type of questions");

                        return false;
                }

                break;

            case 'SUCCESS':
                $sessionToken = $response->sessionToken ?? null;

                if (!isset($sessionToken)) {
                    $this->logger->error("sessionToken not found");

                    $errorSummary = $response->errorSummary ?? null;

                    if (in_array($errorSummary, [
                        "Invalid token provided",
                    ])
                    ) {
                        throw new CheckRetryNeededException(2, 0);
                    }

                    return false;
                }

                $response = $this->authComplete($sessionToken, "id_token");
                $result = $response->result ?? null;
                $email = $response->prompt->email ?? null;

                if ($result == 'prompt_email_link' && $email) {
                    if ($this->getWaitForOtc()) {
                        $this->sendNotification("2fa via link // RR");
                    }

                    $this->AskQuestion("Please copy-paste the “Trust this device” link which was sent to your email {$email} to continue the authentication process.", null, "QuestionLink");

                    return false;
                }

                return $this->finalRedirect($response->redirect_to ?? null);

                break;

            case 'prompt_email_link':
                $email = $response->prompt->email ?? null;

                if ($email) {
                    if ($this->getWaitForOtc()) {
                        $this->sendNotification("mailbox, 2fa via link // RR");
                    }

                    $this->AskQuestion("Please copy-paste the “Trust this device” link which was sent to your email {$email} to continue the authentication process.", null, "QuestionLink");

                    return false;
                }

                break;

            case 'ok':
                return $this->finalRedirect($response->redirect_to ?? null);

                break;

            case 'error':
                $message = $response->error_description ?? null;

                if ($message == 'authentication failed') {
                    throw new CheckException("Wrong email or password", ACCOUNT_INVALID_PASSWORD);
                }

                break;

            default:
                if ($this->http->FindPreg("#https://dashboard.brex.com/auth.html\?code=([^&]+)#", false, $this->http->currentUrl())) {
                    return $this->finalRedirect($this->http->currentUrl());
                }

                $errorSummary = $response->errorCauses[0]->errorSummary ?? null;

                if ($errorSummary) {
                    $this->logger->error("[Error]: {$errorSummary}");

                    if ($errorSummary == "Password is incorrect") {
                        throw new CheckException("Wrong email or password. Try again or select forgot password to reset", ACCOUNT_INVALID_PASSWORD);
                    }

                    return false;
                }

                $this->logger->error("Unknown result");
        }// switch ($result)
        */

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if ($step == "QuestionLink") {
            if (!filter_var($answer, FILTER_VALIDATE_URL)) {
                $this->AskQuestion($this->Question, "The link you entered seems to be incorrect");

                return false;
            }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))
            $this->http->GetURL($answer);

            $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR . ' | ' . self::XPATH_SUCCESS), 10);
            $this->saveResponse();

            return $this->loginSuccessful();
        }

        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="answer" or @name = "credentials.passcode"]'), 5);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@value="Sign in"]'), 0);
        $this->saveResponse();

        if (!$questionInput || !$button) {
            return false;
        }

        $this->driver->executeScript('var rememberMe = document.querySelector(\'input[name = "rememberDevice"]\'); if (rememberMe && rememberMe.checked == false) rememberMe.click();');
        $questionInput->sendKeys($answer);
        $this->saveResponse();
        $button->click();

        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_ERROR
            . ' | //span[@id = "mfa-email"]
            | ' . self::XPATH_SUCCESS
        ), 10);
        $this->saveResponse();

        if ($this->verificationViaEmail()) {
            return false;
        }

        return $this->loginSuccessful();

        /*
        if ($step == "QuestionLink") {
            if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL)) {
                $this->AskQuestion($this->Question, "The link you entered seems to be incorrect");

                return false;
            }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))

            $this->http->GetURL($this->Answers[$this->Question]);
            unset($this->Answers[$this->Question]);

            $this->http->PostURL(str_replace('remember=true', 'isSameBrowser=true', $this->http->currentUrl()), '{"remember":true}');
            $response = $this->http->JsonLog();

            if (isset($response->error) && $response->error == 'link_expired') {
                $this->AskQuestion($this->Question, "The link you entered seems to be expired");

                return false;
            }

            $fallbackRedirect = null;

            if (
                !isset($response->redirect_to)
                && isset($response->fallback_to)
                && $response->fallback_to == 'https://dashboard.brex.com'
            ) {
//                $this->http->GetURL("https://accounts-api.brex.com/oauth2/default/v1/authorize?client_id=dashboard&redirect_uri=https%3A%2F%2Fdashboard.brex.com%2Fauth.html&response_type=code&scope=openid%20profile%20offline_access&state=MTg5LDUwLDE3MSwxMjMsNzgsMjQ4LDEwNCwxMjEsNDIsMTMzLDIxNSwyMjAsODYsMjA1LDIzMSwxMTAsMTk5LDI2LDE1OSwxMDAsMTU5LDYzLDI0MSw2NSw0LDIxNiwxODksOTQsMSwzNiwxMTIsODM%3D&prompt=&nonce=MjE3LDE3NywxOTgsNiwzNiwxOTcsMjEzLDgzLDIzNywyMDMsMTkwLDE0LDE3MSw4NCw3OCw1LDE1MSwxNTYsMjEzLDUyLDEzNCwxOTksNTAsMTIwLDE0Nyw1MSwyMCwzNSwxMjgsMTIsMjUwLDE3Mg%3D%3D&code_challenge=7OXiZ7hyx1kBX-Ng1JjOljFSp0_s-uisvjpoq0RWJiI&code_challenge_method=S256");
                $fallbackRedirect = "https://accounts-api.brex.com/oauth2/default/v1/authorize?client_id=dashboard&redirect_uri=https%3A%2F%2Fdashboard.brex.com%2Fauth.html&response_type=code&scope=openid%20profile%20offline_access&state=MjAxLDY4LDMzLDE1Miw2NCwyMzMsNDQsMjM2LDkyLDAsOTEsMjA0LDIxNiwxMjksMTIzLDEzMSwxMDEsNjMsMTU4LDE0NCwyMjksMTE4LDE4NCwxNTUsNSwxMzQsODcsMTg3LDc4LDE3LDE2NCwxMjE%3D&prompt=&nonce=MTk0LDM5LDE2MiwxNjAsNDYsMTQ4LDEwOSwyNTMsNzYsMTI1LDE2Myw4MSwxNDgsNTAsMjQyLDIzLDYxLDEzNiwxNjYsMjAyLDIzOCwyNSwyMCwxNzIsMjI1LDg3LDIyOCwyMTMsMyw4MCwxOTEsMjM2&code_challenge=Oa6Wir0B9dFjjye5o1bDuFhBqhbKIHGhkOSbelGu-B8&code_challenge_method=S256";
            }

            return $this->finalRedirect($response->redirect_to ?? $fallbackRedirect);
        }

        $data = [
            "passCode"   => $this->Answers[$this->Question],
            "stateToken" => $this->State['stateToken'],
        ];
        unset($this->Answers[$this->Question]);
        $headers = $this->headers;
        */
//        $headers["Accept"] = "application/json, text/javascript, */*; q=0.01";
        /*
        $this->http->PostURL("https://accounts-api.brex.com/api/v1/authn/factors/{$this->State['id']}/verify?rememberDevice=true", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $sessionToken = $response->sessionToken ?? null;

        if (!isset($sessionToken)) {
            $this->logger->error("sessionToken not found");

            $errorSummary = $response->errorSummary ?? null;

            if (in_array($errorSummary, [
                "Invalid token provided",
            ])
            ) {
                throw new CheckRetryNeededException(2, 0);
            }

            if (in_array($errorSummary, [
                "Invalid Passcode/Answer",
            ])
            ) {
                $this->AskQuestion($this->Question, $errorSummary, "Question");
            }

            return false;
        }

        $response = $this->authComplete($sessionToken, "id_token");

        $result = $response->result ?? null;
        $email = $response->prompt->email ?? null;

        $this->logger->debug("[result]: {$result}");
        $this->logger->debug("[email]: {$email}");

        if ($result == 'prompt_email_link' && $email) {
            $data = [
                "loginChallenge" => "",
                "redirectUri"    => $this->State['redirect_uri'],
                "sessionId"      => $this->State['session_id'],
                "userId"         => $response->userId,
            ];
            $this->http->PostURL("https://accounts.brex.com/login/email-link-status", json_encode($data));
            $this->http->JsonLog();

            if ($this->getWaitForOtc()) {
                $this->sendNotification("mailbox, 2fa via link // RR");
            }

            $this->AskQuestion("Please copy-paste the “Trust this device” link which was sent to your email {$email} to continue the authentication process.", null, "QuestionLink");

            return false;
        }

        $message = $response->error_description ?? null;

        if ($message == 'authentication failed') {
            $this->AskQuestion($this->Question, "The two step verification code is invalid. Please try again.", "Question");

            return false;
        }

        return $this->finalRedirect($response->redirect_to ?? null);
        */
    }

    public function Parse()
    {
        $data = '{"operationName":"AccountRewardsBalanceQuery","variables":{},"query":"query AccountRewardsBalanceQuery {\n  account {\n    id\n    pointsBalanceV2\n    rewardsStatusV2\n    __typename\n  }\n}\n"}';
        $this->browser->PostURL("https://api.veyondcard.com/v1/graphql", $data, $this->headers);
        $response = $this->browser->JsonLog();
        // Balance - Points earned
        $this->SetBalance(floor($response->data->account->pointsBalanceV2 / 100));

        // get All Cards
        $this->logger->info("All Cards", ['Header' => 3]);
        $data = '{"operationName":"AllCards","variables":{"last":30,"statusViews":["ACTIVE","EXPIRED","LOCKED","WAITING_ACTIVATION"]},"query":"query AllCards($first: Int, $last: Int, $allCardsCursor: String, $searchQuery: String, $sortBy: CardSortColumn, $departmentIds: [String!], $statusViews: [StatusView!], $isVendor: Boolean, $userId: [ID!]) {\n  cards(first: $first, last: $last, after: $allCardsCursor, statusViews: $statusViews, searchQuery: $searchQuery, sortBy: $sortBy, departmentIds: $departmentIds, isPreapproved: $isVendor, customerUserId: $userId) {\n    totalCount\n    pageInfo {\n      endCursor\n      hasNextPage\n      hasPreviousPage\n      __typename\n    }\n    edges {\n      node {\n        id\n        applicableLimit {\n          frequency\n          amount\n          __typename\n        }\n        displayName\n        holderName\n        instrumentType\n        isAdminLocked\n        isPreapproved\n        isRoleLocked\n        last4\n        network\n        providerCardProductId\n        softExpiration {\n          softExpiresAt\n          isSoftExpired\n          __typename\n        }\n        statusView\n        usage\n        user {\n          id\n          department {\n            id\n            name\n            __typename\n          }\n          monthlyUserLimitInfo {\n            override {\n              endsAt\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        utilization\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->browser->PostURL("https://api.veyondcard.com/v1/graphql", $data, $this->headers);
        $responseCards = $this->browser->JsonLog();
        $totalCount = $responseCards->data->cards->totalCount ?? null;
        $this->logger->notice("Total {$totalCount} cards were found");
        $edges = $responseCards->data->cards->edges ?? [];
        $cards = [];

        foreach ($edges as $edge) {
            $code = $edge->node->id;
            $displayName = $edge->node->displayName;
            $number = $edge->node->last4;
            $statusView = $edge->node->statusView;
            $cardDescription = C_CARD_DESC_DO_NOT_EARN; // WAITING_ACTIVATION

            if ($statusView == 'ACTIVE') {
                $cardDescription = C_CARD_DESC_ACTIVE;
            }

            if (in_array($statusView, ['EXPIRED', 'LOCKED'])) {
                $cardDescription = C_CARD_DESC_CLOSED;
            }

            if (!strstr($displayName, 'Brex Card')) {
                $displayName .= ' Brex Card';
            }

            $card = [
                "Code"            => $code,
                "DisplayName"     => trim("{$displayName} (...{$number})"),
            ];

            $cards[] = $card;

            $this->AddDetectedCard($card + [
                "CardDescription" => $cardDescription,
            ], true);
        }

        $startDates = [];

        foreach ($cards as $card) {
            $startDates['brex' . $card['Code']] = $this->getSubAccountHistoryStartDate('brex' . $card['Code']);
        }

        $this->logger->debug(var_export($startDates, true), ['pre' => true]);

        $statementEntriesPageSize = 200;
        $history = [];
        $page = 0;
        $endOfHistoryReached = [];

        do {
            $this->logger->info("[History page]: {$page}", ['Header' => 3]);
            $statementEntriesCursor = '';

            if ($page > 0 && isset($cursor)) {
                $statementEntriesCursor = ',"statementEntriesCursor":"' . $cursor . '"';
            }

            $page++;

            $data = '{"operationName":"CardTransactionsSearch","variables":{"statementEntriesFilters":"{\"and\":[]}","statementEntriesAggregations":"[{\"operation\":\"sum\",\"from\":\"transaction_operation.amount\",\"name\":\"balance\"}]","transactionsFilters":"{\"and\":[{\"equal\":{\"field\":\"status\",\"value\":\"pending\"}}]}","transactionsAggregations":"[{\"operation\":\"sum\",\"from\":\"transaction_operations.amount\",\"name\":\"balance\"}]","transactionsPageSize":' . $statementEntriesPageSize . $statementEntriesCursor . '},"query":"query CardTransactionsSearch($transactionsFilters: Json!, $transactionsAggregations: Json!, $statementEntriesFilters: Json!, $statementEntriesAggregations: Json!, $transactionsCursor: String, $statementEntriesCursor: String, $transactionsPageSize: Int, $statementEntriesPageSize: Int, $scopeToUser: Boolean) {\n  pending: search(type: \"transaction\", filters: $transactionsFilters, aggregates: $transactionsAggregations, cursor: $transactionsCursor, pageSize: $transactionsPageSize, scopeToUser: $scopeToUser) {\n    cursor\n    totalHits\n    hits {\n      ... on Transaction {\n        id\n        ...V3TransactionFragment\n        __typename\n      }\n      __typename\n    }\n    aggregates {\n      ... on SumAggregationResult {\n        name\n        value\n        __typename\n      }\n      ... on DateHistogramAggregationResult {\n        __typename\n        name\n        series {\n          date\n          count\n          sum\n          __typename\n        }\n      }\n      __typename\n    }\n    __typename\n  }\n  completed: search(type: \"statement_entry\", filters: $statementEntriesFilters, aggregates: $statementEntriesAggregations, cursor: $statementEntriesCursor, pageSize: $statementEntriesPageSize) {\n    cursor\n    totalHits\n    hits {\n      ... on StatementEntry {\n        id\n        ...V3StatementEntrySearchFields\n        __typename\n      }\n      __typename\n    }\n    aggregates {\n      ... on SumAggregationResult {\n        name\n        value\n        __typename\n      }\n      ... on DateHistogramAggregationResult {\n        __typename\n        name\n        series {\n          date\n          count\n          sum\n          __typename\n        }\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment V3TransactionFragment on Transaction {\n  __typename\n  id\n  purchaseTime\n  amount {\n    pending\n    __typename\n  }\n  status\n  memo\n  card {\n    id\n    user {\n      id\n      firstName\n      lastName\n      __typename\n    }\n    __typename\n  }\n  department {\n    id\n    name\n    __typename\n  }\n  cardAcceptor {\n    id\n    captureMethod\n    __typename\n  }\n  merchant {\n    id\n    name\n    merchantCategory {\n      id\n      name\n      __typename\n    }\n    merchantIcon {\n      id\n      asset {\n        id\n        downloadUrl\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  receipts(last: 20) {\n    edges {\n      node {\n        id\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  hasDispute\n}\n\nfragment V3StatementEntrySearchFields on StatementEntry {\n  __typename\n  id\n  postedAt\n  amount\n  activityType\n  operation {\n    id\n    transaction {\n      id\n      ...V3TransactionFragment\n      __typename\n    }\n    type\n    __typename\n  }\n  rewardsRefund {\n    id\n    __typename\n  }\n  originator {\n    __typename\n    ... on RewardsRefund {\n      id\n      pointsCost\n      redeemer {\n        id\n        firstName\n        lastName\n        __typename\n      }\n      refundedStatementEntry {\n        id\n        originator {\n          __typename\n          ... on TransactionOperation {\n            id\n            transaction {\n              id\n              merchant {\n                id\n                name\n                merchantCategory {\n                  id\n                  name\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          ... on RewardsRefund {\n            id\n            __typename\n          }\n        }\n        __typename\n      }\n      __typename\n    }\n    ... on TransactionOperation {\n      id\n      splitItems {\n        id\n        amountCents\n        __typename\n      }\n      __typename\n    }\n  }\n}\n"}';
            $this->browser->PostURL("https://api.veyondcard.com/v1/graphql?operationName=CardTransactionsSearch", $data, $this->headers);
            $response = $this->browser->JsonLog();
            $totalHits = $response->data->completed->totalHits ?? null;
            $cursor = $response->data->completed->cursor ?? null;
            $this->logger->notice("Total {$totalHits} transactions in account were found");

            $hits = $response->data->completed->hits ?? [];

            foreach ($hits as $key => $hit) {
                $this->logger->debug("[{$hit->postedAt}]: $hit->amount");

                if (
                    $hit->operation === null
                    && $hit->activityType == 'COLLECTION'
                ) {
                    $description = 'Payment to Brex';
                    $this->logger->notice("skip -> {$description}");

                    continue;
                }

                $cardCode = $hit->operation->transaction->card->id ?? null;

                if (
                    $cardCode
                    && isset($startDates['brex' . $cardCode])
                    && strtotime($hit->postedAt) < $startDates['brex' . $cardCode]
                ) {
                    $this->logger->notice("[{$cardCode}]: break at date {$hit->postedAt} " . strtotime($hit->postedAt));
                    $endOfHistoryReached[$cardCode] = true;

                    continue;
                }

                $data = '{"operationName":"TransactionDetailsV3","variables":{"id":"' . $hit->id . '"},"query":"query TransactionDetailsV3($id: ID!) {\n  node(id: $id) {\n    id\n    __typename\n    ... on StatementEntry {\n      actualAmount: amount\n      postedAt\n      integrationStatus\n      activityType\n      userCategory {\n        id\n        name\n        description\n        isDeleted\n        isDisabled\n        isInactive\n        __typename\n      }\n      originator {\n        __typename\n        ... on TransactionOperation {\n          id\n          splitItems {\n            id\n            amountCents\n            __typename\n          }\n          __typename\n        }\n        ... on RewardsRefund {\n          id\n          pointsCost\n          refundedStatementEntry {\n            id\n            originator {\n              __typename\n              ... on TransactionOperation {\n                id\n                transaction {\n                  id\n                  merchant {\n                    id\n                    name\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              ... on RewardsRefund {\n                id\n                __typename\n              }\n            }\n            __typename\n          }\n          __typename\n        }\n        ... on CollectionAttempt {\n          id\n          amount\n          collectionDate\n          status\n          __typename\n        }\n      }\n      amount\n      lens {\n        id\n        integrationFieldEntities {\n          id\n          ...ExternalIntegrationEntityFragment\n          __typename\n        }\n        billableStatus\n        userCategory {\n          id\n          name\n          description\n          isDeleted\n          isDisabled\n          isInactive\n          __typename\n        }\n        __typename\n      }\n      rewardsRedemptionOffer {\n        redemptionOfferId\n        pointsCost\n        __typename\n      }\n      rewardsRefund {\n        id\n        pointsCost\n        refundedStatementEntry {\n          id\n          amount\n          __typename\n        }\n        __typename\n      }\n      operation {\n        id\n        rewardsAccrualEntries(first: 10) {\n          edges {\n            node {\n              id\n              amount\n              rewardsTrigger {\n                id\n                name\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        transaction {\n          id\n          ...TransactionDetailsV3\n          __typename\n        }\n        __typename\n      }\n      exportedIntegrationFields {\n        externalIntegrationEntities {\n          id\n          deletedAt\n          externalIntegrationFieldId\n          name\n          __typename\n        }\n        userCategory {\n          id\n          name\n          __typename\n        }\n        billableStatus\n        __typename\n      }\n      fees {\n        __typename\n        ... on StatementEntryFeeBnpl {\n          id\n          amount {\n            quantityCents\n            __typename\n          }\n          rate\n          __typename\n        }\n      }\n      __typename\n    }\n    ... on Transaction {\n      id\n      ...TransactionDetailsV3\n      __typename\n    }\n  }\n}\n\nfragment TransactionDetailsV3 on Transaction {\n  id\n  accrualTime\n  purchaseTime\n  status\n  department {\n    id\n    name\n    deletedAt\n    __typename\n  }\n  location {\n    id\n    name\n    deletedAt\n    __typename\n  }\n  merchant {\n    id\n    name\n    website\n    merchantCategory {\n      id\n      name\n      __typename\n    }\n    merchantIcon {\n      id\n      asset {\n        id\n        downloadUrl\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  userCategory {\n    id\n    name\n    isDeleted\n    isDisabled\n    isInactive\n    __typename\n  }\n  receipts(last: 20) {\n    edges {\n      node {\n        id\n        origin\n        asset {\n          id\n          downloadUrl\n          presignedDownloadUrl\n          data {\n            ... on FileAsset {\n              __typename\n              name\n              contentType\n            }\n            ... on EmailAsset {\n              __typename\n              subject\n              accrualTime\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  lens {\n    id\n    billableStatus\n    __typename\n  }\n  card {\n    id\n    last4\n    user {\n      id\n      firstName\n      lastName\n      __typename\n    }\n    __typename\n  }\n  cardAcceptor {\n    id\n    captureMethod\n    city\n    name\n    __typename\n  }\n  memo\n  transactionAmount: amount {\n    pending\n    __typename\n  }\n  disputeTransaction {\n    id\n    status\n    dispute {\n      id\n      type\n      __typename\n    }\n    __typename\n  }\n  hasDispute\n  __typename\n}\n\nfragment ExternalIntegrationEntityFragment on ExternalIntegrationEntity {\n  id\n  vendorInternalId\n  externalIntegrationFieldId\n  name\n  isInactive\n  deletedAt\n  payload {\n    __typename\n    ... on QuickbooksClass {\n      name\n      __typename\n    }\n    ... on QuickbooksLocation {\n      name\n      __typename\n    }\n    ... on QuickbooksCustomer {\n      name\n      customerType\n      __typename\n    }\n    ... on NetsuiteClass {\n      name\n      isInactive\n      __typename\n    }\n    ... on NetsuiteLocation {\n      name\n      isInactive\n      __typename\n    }\n    ... on NetsuiteDepartment {\n      name\n      isInactive\n      __typename\n    }\n    ... on NetsuiteVendor {\n      name\n      isInactive\n      __typename\n    }\n    ... on XeroTrackingCategory {\n      categoryName\n      categoryOption\n      categoryNameId\n      categoryOptionId\n      isInactive\n      __typename\n    }\n  }\n  __typename\n}\n"}';
                $this->browser->PostURL("https://api.veyondcard.com/v1/graphql", $data, $this->headers);
                $responseDetails = $this->browser->JsonLog();

                $responseDetailsNode = $responseDetails->data->node;

                $date = $responseDetailsNode->postedAt;
                $transactionDate = strtotime($date);

//                if (isset($startDate) && $transactionDate < $startDate) {
//                    $this->logger->notice("break at date {$date} ($transactionDate)");
//
//                    continue;
//                }

                $description = $responseDetailsNode->operation->transaction->merchant->name ?? null;

                if (
                    !$description
                    && $responseDetailsNode->operation === null
                    && $responseDetailsNode->activityType == 'COLLECTION'
                ) {
                    $description = 'Payment to Brex';
                }

                if (
                    isset($responseDetailsNode->operation->transaction->status)
                    && $responseDetailsNode->operation->transaction->status == 'refund'
                ) {
                    $description .= ' (refund)';
                }

                $points = null;

                if (isset($responseDetailsNode->operation->rewardsAccrualEntries->edges[0]->node->amount)) {
                    $points = $responseDetailsNode->operation->rewardsAccrualEntries->edges[0]->node->amount / 100;
                }

                $details = [
                    "id"                  => $responseDetailsNode->id,
                    "Purchased at"        => $responseDetailsNode->operation->transaction->purchaseTime ?? null,
                    "Merchant address"    => $responseDetailsNode->operation->transaction->cardAcceptor->city ?? null,
                    "Merchant descriptor" => $responseDetailsNode->operation->transaction->cardAcceptor->name ?? null,
                    "Merchant website"    => $responseDetailsNode->operation->transaction->merchant->merchantCategory->website ?? null,
                ];

                $cardCode = $responseDetailsNode->operation->transaction->card->id ?? null;

                $history[$cardCode][] = [
                    "Date"        => $transactionDate,
                    "Description" => $description,
                    "Points"      => $points,
                    "Amount"      => $responseDetailsNode->actualAmount / 100,
                    "Currency"    => "USD",
                    "Details"     => json_encode($details),
                    "Category"    => $responseDetailsNode->operation->transaction->merchant->merchantCategory->name ?? null,
                    "Card number" => $responseDetailsNode->operation->transaction->card->last4 ?? null,
                    "Card id"     => $cardCode,
                ];
            }
        } while (
            count($hits) === $statementEntriesPageSize
            && $cursor
            && $page < 10
            && count($cards) != count($endOfHistoryReached)
        );

        foreach ($cards as $card) {
            $card['Balance'] = null;
            $card['IsHidden'] = true;
            $card['HistoryRows'] = $history[$card['Code']] ?? [];
            $this->AddSubAccount($card);
        }

//        $this->logger->debug(var_export($history, true), ['pre' => true]);
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Points"      => "Miles",
            "Amount"      => "Amount",
            "Currency"    => "Currency",
            "Details"     => "Info",
            "Category"    => "Category",
            "Card number" => "Info",
            "Card id"     => "Info",
        ];
    }

    public function GetHiddenHistoryColumns()
    {
        return [
            'Details',
            'Card number',
            'Card id',
        ];
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->RetryCount = 0;
        $this->browser->GetURL($this->http->currentUrl());
        $this->browser->RetryCount = 2;
    }

    private function verificationViaEmail()
    {
        $this->logger->notice(__METHOD__);

        // For extra security, we sent a verification email to ...@....com.
        if ($email = $this->http->FindSingleNode('//span[@id = "mfa-email" and contains(., "@")]')) {
            $this->holdSession();
            $this->AskQuestion("Please copy-paste the “Trust this device” link which was sent to your email {$email} to continue the authentication process.", null, "QuestionLink");

            return true;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 10);
        $this->saveResponse();
        $tokenData = $this->driver->executeScript("return localStorage.getItem('BREX_AUTH_TOKEN_MANAGMENT_TOKEN');");
        $this->logger->info("[Form tokenData]: '" . $tokenData . "'");

        if (!$tokenData) {
            return false;
        }

        $tokenDataResponse = $this->http->JsonLog($tokenData);

        if (!isset($tokenDataResponse->accessToken)) {
            $this->logger->error("authorization token not found");

            return false;
        }

        $this->State['authorization'] = $tokenDataResponse->accessToken;

        $this->parseWithCurl();

        $headers = [
            "authorization" => "Bearer {$this->State['authorization']}",
        ];
        $data = '{"operationName":"UserPropertiesQuery","variables":{},"query":"query UserPropertiesQuery {\n  user: userWithoutDelegator {\n    id\n    isPrimitives\n    role\n    email\n    firstName\n    lastName\n    depositsRole\n    isInitialApplicant\n    isInvitedDepositsAdmin\n    isInvitedDepositsCashAndCardUser\n    helpshiftAuthToken\n    hasTransaction\n    isManager\n    insertedAt\n    status\n    hasOnboarded\n    account {\n      id\n      dbaName\n      legalName\n      signupIntent\n      hasProductApplication\n      financialProductType\n      insertedAt\n      latestApprovedProductApplicationSubmittedAt\n      hasClearedTransaction\n      approvedBlueprintAtOnboarding\n      riskTier\n      initialMarketSegment\n      isInternalSignup\n      status\n      depositsAccounts(first: 1) {\n        edges {\n          node {\n            id\n            status\n            activatedAt\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  integrations(last: 100) {\n    edges {\n      node {\n        id\n        vendor\n        status\n        credential {\n          id\n          status\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  productConfig {\n    ...ProductConfigFeaturesFragment\n    __typename\n  }\n}\n\nfragment ProductConfigFeaturesFragment on ProductConfig {\n  features {\n    budgetManagement {\n      enabled\n      __typename\n    }\n    reimbursements {\n      enabled\n      __typename\n    }\n    multiEntity {\n      enabled\n      __typename\n    }\n    advancedExpenseManagement {\n      enabled\n      __typename\n    }\n    accountingIntegrations {\n      enabled\n      __typename\n    }\n    accountingCustomRules {\n      enabled\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n"}';
        $this->browser->PostURL("https://api.veyondcard.com/v1/graphql?operationName=UserPropertiesQuery", $data, $this->graphqlHeaders + $headers);
        $response = $this->browser->JsonLog(null, 4);
        $email = $response->data->user->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            foreach ($headers as $header => $value) {
                $this->browser->setDefaultHeader($header, $value);
            }
            // Name
            $this->SetProperty('Name', $response->data->user->account->dbaName);

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function authComplete($sessionToken, $response_type = 'code')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("response_type: {$response_type}");

        if (empty($sessionToken)) {
            $this->logger->debug("sessionToken not found");

            return false;
        }

        $headers = $this->headers;
        $headers["Accept"] = "application/json, text/javascript, */*; q=0.01";

        $url = "https://accounts-api.brex.com/oauth2/default/v1/authorize?client_id=0oabfmnzrGNii3ueS5d6&nonce=jBesAibIvsf1nFPj7AyOj1hJ5dbd8RWwNwWgLDHZq1EUcDdRM9YeGW0ie6lOgDvb&prompt=none&redirect_uri=https%3A%2F%2Faccounts.brex.com%2Fokta%2Fpost-auth&response_mode=okta_post_message&response_type={$response_type}&sessionToken={$sessionToken}&state=dzJfZbCUYL3roIrgVI2AcVY9a0XKHdqBB6a6lEDu3XGTVcdPOJLzUXdll6yG7R2o&scope=openid";
        $this->http->GetURL($url);
        $code = $this->http->FindPreg("/data.code = '([^\']+)/");

        if (!isset($code)) {
            $this->logger->error("data.code not found");

            // refs #21417
            $id_token = $this->http->FindPreg("/data.id_token = '([^\']+)/");

            if (!isset($id_token)) {
                $this->logger->error("id_token not found");

                return false;
            }

            $this->http->GetURL("https://accounts-api.brex.com/api/v1/sessions/me");
            $response = $this->http->JsonLog();

            if (!isset($response->id)) {
                $this->logger->error("session_id not found");

                return false;
            }

            $userInfo = null;

            foreach (explode('.', $id_token) as $str) {
                $str = base64_decode($str);
                $this->logger->debug($str);

                if (strstr($str, 'nonce')) {
                    $userInfo = $this->http->JsonLog($str);

                    break;
                }
            }

            if (empty($userInfo)) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->logger->debug("[url] => {$url}");

            $data = [
                "biometrics_id"   => $this->State['biometrics_id'],
                "session_id"      => $response->id,
                "id_token_string" => str_replace('\x2D', '-', $id_token),
                "nonce"           => $userInfo->nonce,
                "login_challenge" => "",
                "redirect_uri"    => "https://accounts-api.brex.com/oauth2/default/v1/authorize?client_id=dashboard&redirect_uri=https%3A%2F%2Fdashboard.brex.com%2Fauth.html&response_type=code&scope=openid%20profile%20offline_access&state=MjAxLDY4LDMzLDE1Miw2NCwyMzMsNDQsMjM2LDkyLDAsOTEsMjA0LDIxNiwxMjksMTIzLDEzMSwxMDEsNjMsMTU4LDE0NCwyMjksMTE4LDE4NCwxNTUsNSwxMzQsODcsMTg3LDc4LDE3LDE2NCwxMjE%3D&prompt=&nonce=MTk0LDM5LDE2MiwxNjAsNDYsMTQ4LDEwOSwyNTMsNzYsMTI1LDE2Myw4MSwxNDgsNTAsMjQyLDIzLDYxLDEzNiwxNjYsMjAyLDIzOCwyNSwyMCwxNzIsMjI1LDg3LDIyOCwyMTMsMyw4MCwxOTEsMjM2&code_challenge=Oa6Wir0B9dFjjye5o1bDuFhBqhbKIHGhkOSbelGu-B8&code_challenge_method=S256", //$url,
            ];
            $this->State['session_id'] = $response->id;
            $this->State['redirect_uri'] = $url;
            $this->http->PostURL("https://accounts.brex.com/okta/post-auth", json_encode($data), $headers);

            return $this->http->JsonLog();
        }

        $tokenHeaders = [
            "Accept"                     => "application/json",
            "Referer"                    => "https://accounts.brex.com/",
            "Origin"                     => "https://accounts.brex.com/",
            "Content-Type"               => "application/x-www-form-urlencoded",
            "X-Okta-User-Agent-Extended" => "okta-signin-widget-5.7.14",
        ];
        $data = [
            "client_id"     => "0oabfmnzrGNii3ueS5d6",
            "redirect_uri"  => "https://accounts.brex.com/okta/post-auth",
            "grant_type"    => "authorization_code",
            "code_verifier" => "92588371c3e5e51602df4234c50adf2e45c30596e12",
            "code"          => $code,
        ];
        $this->http->PostURL("https://accounts-api.brex.com/oauth2/default/v1/token", $data, $tokenHeaders);
        $response = $this->http->JsonLog();

        if (!isset($response->access_token)) {
            $this->logger->error("access_token not found");
            $message = $response->error_description ?? null;

            if ($message == 'The authorization code is invalid or has expired.') {
                throw new CheckRetryNeededException(2, 0);
//                $this->AskQuestion($this->Question, $message, "Question");// no question here

                return false;
            }

            return false;
        }

        $userInfo = null;

        foreach (explode('.', $response->id_token) as $str) {
            $str = base64_decode($str);
            $this->logger->debug($str);

            if (strstr($str, 'brexAccountId')) {
                $userInfo = $this->http->JsonLog($str);

                break;
            }
        }

        if (empty($userInfo)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $data = [
            "access_token"    => $response->access_token,
            "id_token"        => [
                "value"        => $response->id_token,
                "idToken"      => $response->id_token,
                "claims"       => [
                    "sub"           => $userInfo->sub,
                    "email"         => $userInfo->email,
                    "ver"           => $userInfo->ver,
                    "iss"           => $userInfo->iss,
                    "aud"           => $userInfo->aud,
                    "iat"           => $userInfo->iat,
                    "exp"           => $userInfo->exp,
                    "jti"           => $userInfo->jti,
                    "amr"           => ["pwd"],
                    "idp"           => $userInfo->idp,
                    "nonce"         => $userInfo->nonce,
                    "auth_time"     => $userInfo->auth_time,
                    "at_hash"       => $userInfo->at_hash,
                    "brexUserId"    => $userInfo->brexUserId,
                    "brexAccountId" => $userInfo->brexAccountId,
                ],
                "expiresAt"    => $userInfo->exp,
                "scopes"       => ["email", "openid"],
                "authorizeUrl" => "https://accounts-api.brex.com/oauth2/default/v1/authorize",
                "issuer"       => "https://accounts-api.brex.com/oauth2/default",
                "clientId"     => "0oabfmnzrGNii3ueS5d6",
            ],
            "login_challenge" => $this->State['login_challenge'],
        ];
        $this->http->PostURL("https://accounts.brex.com/okta/post-auth", json_encode($data), $headers);

        return $this->http->JsonLog();
    }

    private function finalRedirect($redirect_to)
    {
        $this->logger->notice(__METHOD__);

        if (empty($redirect_to)) {
            $this->logger->debug("redirect not found");

            return false;
        }
        $this->http->GetURL($redirect_to);
        $code = $this->http->FindPreg("/code=(.+?)&/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->error("code not found");

            return false;
        }
        /*
         *     window.sessionStorage.setItem("loginCallbackHash", window.location.search);
         *     window.location.replace('/login/finish');
         */
        $this->http->GetURL("https://dashboard.brex.com/login/finish");

        $data = [
            "clientId"     => "dashboard",
            "code"         => $code,
            "redirectUri"  => "https://dashboard.brex.com/auth.html",
            "codeVerifier" => "49j9SJzV6fwL5XHUQqeHNQ9Fhse2Cse3xJ361MtoZYGDzSlEz04x6jeG296AUNC5iiVP3YpUs3O8l37BE9eRDkAB6FBhTzDZS7cz3GnaTdaobwj1Me6UkcSJbdlhsZrh",
        ];
        $headers = [
            "Accept"            => "*/*",
            "Content-Type"      => "application/json",
            "Origin"            => "https://dashboard.brex.com",
        ];
        $this->http->PostURL("https://web-auth-token-manager.brex.com/auth-token-api/exchange", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, 5);

        if (!isset($response->access_token)) {
            $this->logger->error("authorization token not found");

            if (isset($response->message) && $response->message == 'INTERNAL_SERVER_ERROR') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $this->State['authorization'] = $response->access_token;

        return $this->loginSuccessful();
    }
}
