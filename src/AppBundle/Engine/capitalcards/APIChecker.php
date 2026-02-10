<?php

namespace AwardWallet\Engine\capitalcards;

use CheckException;
use CheckRetryNeededException;
use StatLogger;
use TAccountChecker;

class OldApiNewTokenException extends CheckException
{
}

class APIChecker extends TAccountChecker
{
    public const MESSAGE_REWARDS_AUTH_LOST = "The connection to your Capital One account was lost; please go to the \"Edit\" screen of this account and press the \"Connect with Capital One\" button to reconnect your account.";
    public const MESSAGE_TX_AUTH_LOST = "The connection to your Capital One credit card transactions was lost; please go to the \"Edit\" screen of this account and press the \"Connect with Capital One\" button to reconnect your account.";

    private $cardsWithMiles = 0;
    private $cardsWithPints = 0;
    private $cardsWithCash = 0;

    private $milesBalance = null;
    private $pointsBalance = null;
    private $cashBalance = null;

    private $newApi = false;

    public function IsLoggedIn()
    {
        return true;
    }

    public function LoadLoginForm()
    {
        $this->ArchiveLogs = true;

        return true;
    }

    public function Login()
    {
        return true;
    }

    public function Parse()
    {
        $this->logger->info("this is updated APIChecker");
        $this->loadApiTokens();
        $this->SetProperty("CombineSubAccounts", false);

        if (empty($this->Answers['access_token'])) {
            throw new CheckException(self::MESSAGE_REWARDS_AUTH_LOST, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->newParse();

        /**
         * {"rewardsAccounts":[
         *      {
         *          "rewardsAccountReferenceId":"920cd61a1e28b40045f8e",
         *          "accountDisplayName":"Capital One Mastercard Worldcard Cash *4734",
         *          "rewardsCurrency":"Cash",
         *          "productAccountType":"Credit Card",
         *          "creditCard Account": {
         *              "issuer":"Capital One",
         *              "product":"Mastercard Worldcard",
         *              "network":"MasterCard",
         *              "lastFour":"4734",
         *              "isBusinessAccount":false
         *          }
         *      }
         * ]}.
         */
        if (is_array($data) && !empty($data['rewardsAccounts'])) {
            $accounts = $data['rewardsAccounts'];
            $SubAccounts = [];

            foreach ($accounts as $account) {
                $details = $this->ParseAccountReference($account['rewardsAccountReferenceId']);

                if ($details !== false) {
                    $SubAccounts[] = $details;
                    $this->AddDetectedCard([
                        "Code"            => $details['Code'],
                        "DisplayName"     => $details['DisplayName'],
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ]);
                }
            }

            if (empty($SubAccounts)) {
                $this->InvalidAnswers["rewards"] = "none";
                unset($this->State[self::getTokenName(true)]);

                throw new CheckException("Access to all customer accounts is denied. Please go to the \"Edit\" screen of this account and press the \"Connect with Capital One\" button to reconnect your account.", ACCOUNT_INVALID_PASSWORD);
            }

            if (count($SubAccounts) === 1) {
                $this->logger->info("one subaccount, setting as main balance and hiding");
                $this->SetBalance($SubAccounts[0]['Balance']);
                $this->SetProperty('Currency', $SubAccounts[0]['Currency']);
                $SubAccounts[0]['IsHidden'] = true;
            } else {
                $this->SetBalanceNA();
            }

            $this->SetProperty("SubAccounts", $SubAccounts);
        } else {
            // refs #1522, https://redmine.awardwallet.com/issues/15226#note-13
            if (is_array($data) && isset($data['rewardsAccounts']) && $data['rewardsAccounts'] == []) {
                throw new CheckException("We don't see any reward accounts under this login", ACCOUNT_PROVIDER_ERROR);
            }/*review*/

            throw new CheckException("Empty data", ACCOUNT_ENGINE_ERROR);
        }

        // refs #21126
        $this->logger->debug("Miles Balance: {$this->milesBalance} / cards: {$this->cardsWithMiles}");
        $this->logger->debug("Points Balance: {$this->pointsBalance} / cards: {$this->cardsWithPints}");
        $this->logger->debug("Cash Balance: {$this->cashBalance} / cards: {$this->cardsWithCash}");

        if (isset($this->Properties['SubAccounts'])) {
            $countSubAccounts = count($this->Properties['SubAccounts']);
            $this->logger->debug("count subAccounts: $countSubAccounts");

            if (/*$this->cardsWithMiles == $countSubAccounts && */ $this->milesBalance !== null) {
                $this->SetBalance($this->milesBalance);
                /*
                } elseif ($this->cardsWithPints == $countSubAccounts && $this->cashBalance !== null) {
                    $this->SetBalance($this->cardsWithPints);
                } elseif ($this->cardsWithCash == $countSubAccounts && $this->cashBalance !== null) {
                    $this->SetBalance($this->cashBalance);
                }

                if (!is_null($this->Balance)) {
                */
                for ($i = 0; $i < $countSubAccounts; $i++) {
                    if ($this->Properties['SubAccounts'][$i]['Currency'] == 'miles') {
                        $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
                    }
                }
            }
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                   => "PostingDate",
            "Description"            => "Description",
            "Merchant"               => "Info",
            "Status"                 => "Info",
            "Type"                   => "Info",
            "Category"               => "Category",
            "Amount"                 => "Amount",
            "Currency"               => "Currency",
            "Miles"                  => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $response = null;

        if ($this->newApi) {
            $this->logger->info("trying to get history with new token");
            $response = $this->apiRequest("/customers/~/accounts-summary", true);

            if (isset($response['code']) && $response['code'] == 101305) {
                $this->logger->info("101305, failed to get history from new api");
                $response = null;
            }
        }

        if ($response === null) {
            if (!isset($this->Answers['tx_access_token'])) {
                $this->markTxAccessInvalid();

                return [];
            }

            $response = $this->apiRequest("/customers/~/accounts-summary", true, false);
        }

        if (!isset($response['customerAccounts'])) {
            if (isset($response['code']) && $response['code'] <= 101217) {
                $this->markTxAccessInvalid();
            }

            return [];
        }
        /**
         * {
         * "customerAccounts": [
         * {
         * "accountPayments": {
         * "minimumDueAmount": 0,
         * "minimumAmountDueDate": "2020-11-19"
         * },
         * "accountTypeDescription": "Credit-Card",
         * "accountStatements": {
         * "mostRecentEndingBalance": 0,
         * "mostRecentDate": "2020-11-17"
         * },
         * "accountNumberLastFour": "0343",
         * "accountName": "QuicksilverOne",
         * "accountUseDescription": "Personal",
         * "transactionUrl": "https:\/\/apiit.capitalone.com:443\/customers\/~\/credit-card\/accounts\/t.66b7a12174274f3ab9948d3ddb0d3054\/transactions",
         * "accountReferenceId": "t.66b7a12174274f3ab9948d3ddb0d3054",
         * "accountBalance": {
         * "date": "2020-12-08",
         * "postedBalance": 0
         * }
         * }
         * ],
         * "total": 1
         * }.
         */
        foreach ($response['customerAccounts'] as $account) {
            if ($account['accountTypeDescription'] !== 'Credit-Card' && $account['accountTypeDescription'] !== 'Charge-Card') {
                continue;
            }

            $cardName = $account['accountName'] ?? null;
            $rewardsRatios = $this->getRewardsRatiosByCardName($cardName);
            $unknownCard = $rewardsRatios['IsHidden'] ?? false;

            if ($unknownCard) {
                $account['IsHidden'] = true;
            }

            $transactions = $this->parseTransactions($account['accountReferenceId'], $rewardsRatios, $startDate);

            if (count($transactions) > 0) {
                $this->addSubAccountHistory($account['accountReferenceId'], $account, $transactions, $cardName, $rewardsRatios, $unknownCard);
            }
        }

        // actual history was saved into SubAccounts.[x].HistoryRows.
        return null;
    }

    public function parseTransactions(string $accountReferenceId, array $rewardsRatios, ?int $startDate): array
    {
        /** @var int $minFromDate */
        $minFromDate = strtotime("-88 days");

        if ($startDate === null) {
            $startDate = $minFromDate;
        }

        if ($startDate < $minFromDate) {
            $startDate = $minFromDate;
        }

        $params = [
            'fromDate' => date("Y-m-d", $startDate),
            'toDate'   => date("Y-m-d"),
        ];

        $result = [];
        $pagingKey = null;

        do {
            /**
             * {.
             }
             */
            $response = $this->apiRequest(
                "/customers/~/credit-card/accounts/{$accountReferenceId}/transactions?" . http_build_query(array_merge($params,
                    ($pagingKey !== null ? ["pagingKey" => $pagingKey] : []))),
                false,
                false
            );

            $result = array_merge($result, array_map(function (array $tx) use ($rewardsRatios) {
                $tx = $this->mapTransaction($tx);

                if ($tx['Type'] !== 'Payment') {
                    $ratio = $rewardsRatios["D:" . $tx["Description"]] ?? $rewardsRatios[$tx['Category']] ?? $rewardsRatios['All'] ?? null;

                    if ($ratio !== null) {
                        $tx['Miles'] = round($tx['Amount'] * $ratio);
                    }
                }

                return $tx;
            }, $response['creditCardTransactions']));

            $pagingKey = $response['pagingKey'] ?? null;
        } while ($pagingKey !== null);

        $this->logger->info("got " . count($result) . " transaction for {$accountReferenceId}");

        return $result;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] != '$') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
        }

        return parent::FormatBalance($fields, $properties);
    }

    public static function parseTokenInfo($tokenInfo, $sandbox)
    {
        if (is_array($tokenInfo) && !empty($tokenInfo['access_token']) && !empty($tokenInfo['refresh_token']) && !empty($tokenInfo['expires_in'])) {
            $tokenInfo['creation_date'] = time();
            $tokenInfo['sandbox'] = $sandbox;
            unset($tokenInfo['id_token']); // too long (750 chars) for transferring to wsdl as answer (250 chars)

            return $tokenInfo;
        }

        return null;
    }

    public static function getBaseHost(bool $rewards, bool $sandbox)
    {
        if ($rewards && $sandbox) {
            return "https://api-sandbox.capitalone.com";
        }

        if (!$rewards && $sandbox) {
            return "https://apiit.capitalone.com";
        }

        return "https://api.capitalone.com";
    }

    private function ParseAccountReference($rewardsAccountReferenceId)
    {
        $data = $this->apiRequest("/rewards/accounts/" . urlencode($rewardsAccountReferenceId), true);
        /**
         * {
         *      "accountDisplayName":"Capital One Visa Platinum Points *3582",
         *      "rewardsBalance":"693",
         *      "balanceTimestamp":"2016-03-03T22:14:52-05:00",
         *      "rewardsCurrency":"Points",
         *      "canRedeem":true,
         *      "redemptionLockReasonDescription":"We're sorry. You can't redeem your rewards right now due to the status of your account ending in 2883. To view your account details, please log in at www.capitalone.com.",
         *      "canTransferOut":true,
         *      "canTransferIn":true,
         *      "redemptionOpportunities":[],
         *      "productAccountType":"Credit Card",
         *      "creditCardAccount":{"issuer":"Capital One","product":"Visa Platinum","network":"Visa","lastFour":"3582","isBusinessAccount":false},
         *      "primaryAccountHolder":{
         *          "firstName":"TATYANA",
         *          "lastName":"SCHMIDT"
         *      }
         * }.
         */
        if (is_array($data) && !empty($data['accountDisplayName'])) {
            // AccountID: 7233460, 7321887, 6913888
            if (!isset($data['rewardsBalance']) && !isset($data['rewardsCurrency'])) {
                return false;
            }

            switch ($data['rewardsCurrency']) {
                case 'Miles':
                    $this->cardsWithMiles++;

                    if (is_null($this->milesBalance)) {
                        $this->milesBalance = $data['rewardsBalance'];
                    } else {
                        $this->milesBalance += $data['rewardsBalance'];
                    }

                    break;

                case 'Points':
                    $this->cardsWithPints++;

                    if (is_null($this->pointsBalance)) {
                        $this->pointsBalance = $data['rewardsBalance'];
                    } else {
                        $this->pointsBalance += $data['rewardsBalance'];
                    }

                    break;

                case 'Cash':
                    $this->cardsWithCash++;

                    if (is_null($this->cashBalance)) {
                        $this->cashBalance = $data['rewardsBalance'];
                    } else {
                        $this->cashBalance += $data['rewardsBalance'];
                    }

                    break;
            }

            return [
                'Code'        => $this->getSubAccountCode($rewardsAccountReferenceId),
                'DisplayName' => $data['accountDisplayName'],
                'Currency'    => $this->convertCurrency($data['rewardsCurrency']),
                'Balance'     => $data['rewardsBalance'],
            ];
        }

        return false;
    }

    private function mapTransaction(array $tx): array
    {
        return [
            'Date'        => strtotime($tx["postedDate"]),
            'Description' => $tx['transactionDescription'],
            'Merchant'    => $tx['transactionMerchant']['name'] ?? null,
            'Status'      => $tx['transactionState'],
            'Type'        => $tx['transactionType'],
            'Category'    => $tx['transactionMerchant']['merchantType'] ?? null,
            // debitCreditType - Indicates whether the transaction is
            // - a debit (which decreases the account balance) or
            // - a credit (which increases the account balance).
            'Amount'      => $tx['debitCreditType'] === 'Credit' ? -$tx['transactionAmount'] : $tx['transactionAmount'],
            'Currency'    => 'USD',
        ];
    }

    private function addSubAccountHistory(string $accountReferenceId, array $account, array $transactions, ?string $cardCode, ?array $rewardsRatios, bool $unknownCard): void
    {
        $this->logger->info("addSubAccountHistory, cardCode: $cardCode, account['accountName']: " . ($account['accountName'] ?? null));
        $subAccountCode = $this->getSubAccountCode($accountReferenceId);

        if (!isset($this->Properties['SubAccounts'])) {
            $this->Properties['SubAccounts'] = [];
        }
        $index = $this->findCreditCardIndex($subAccountCode, $account['accountNumberLastFour'] ?? null);

        if ($index === null) {
            $this->logger->info("creating new subaccount for history saving");
            $this->Properties['SubAccounts'][] = [
                'Code'        => $subAccountCode,
                'DisplayName' => $account['accountName'] . ' ' . ($account['accountNumberLastFour'] ?? ''),
                'Balance'     => null,
            ];
            $index = count($this->Properties['SubAccounts']) - 1;
        }

        StatLogger::getInstance()->info("capitalcards mapping", [
            "known"    => !$unknownCard,
            "cardName" => $this->Properties['SubAccounts'][$index]['DisplayName'],
            "cardCode" => $cardCode,
            "ratios"   => count($rewardsRatios) === 0 ? null : $rewardsRatios,
            "UserID"   => (int) $this->AccountFields['UserID'],
        ]);

        $this->Properties['SubAccounts'][$index]['HistoryRows'] = $transactions;

        $this->AddDetectedCard([
            "Code"            => $this->Properties['SubAccounts'][$index]['Code'],
            "DisplayName"     => $this->Properties['SubAccounts'][$index]['DisplayName'],
            "CardDescription" => C_CARD_DESC_ACTIVE,
        ]);

        if ($account['IsHidden'] ?? false) {
            $this->Properties['SubAccounts'][$index]['IsHidden'] = true;
        }
    }

    private function findCreditCardIndex(string $subAccountCode, ?string $lastFour): ?int
    {
        foreach ($this->Properties['SubAccounts'] as $index => $subAccount) {
            if ($subAccount['Code'] === $subAccountCode) {
                $this->logger->info("mapped to subAccount {$subAccount['DisplayName']} by code");

                return $index;
            }

            if ($lastFour !== null && stripos($subAccount['DisplayName'], $lastFour) !== false) {
                $this->logger->info("mapped to subAccount {$subAccount['DisplayName']} by last four $lastFour");

                return $index;
            }
        }

        return null;
    }

    private function curlRequest($url, $options, &$responseCode, bool $rewardsApi)
    {
        $requestInfo = [CURLINFO_HTTP_CODE, CURLINFO_HEADER_OUT];
        $url = self::getBaseHost($rewardsApi, $this->isSandbox($rewardsApi)) . $url;
        $options[CURLOPT_FAILONERROR] = false;
        $headers = "";
        $options[CURLOPT_HEADERFUNCTION] = function ($curl, $header) use (&$headers) {
            $headers .= $header;

            return strlen($header);
        };
        $result = curlRequest($url, 60, $options, $requestInfo);
        $responseCode = $requestInfo[CURLINFO_HTTP_CODE];
        $data = json_decode($result, true);

        if (is_array($data)) {
            $log = json_encode($data, JSON_PRETTY_PRINT);
        } else {
            $log = $result;
        }
        $this->http->Log("GET {$url}", LOG_LEVEL_NORMAL);
        $this->http->Log("request: <pre>" . htmlspecialchars(var_export($options, true)) . "</pre>", LOG_LEVEL_NORMAL, false);
        $this->http->Log("response ({$responseCode}): <pre>" . htmlspecialchars($headers . $log) . "</pre>", LOG_LEVEL_NORMAL, false);

        return $data;
    }

    private function apiRequest($url, $allow403 = false, $rewardsApi = true)
    {
        $data = $this->curlRequest($url, $this->getCurlOptions($rewardsApi), $responseCode, $rewardsApi);
        $errorCode = null;

        if (is_array($data) && isset($data["code"])) {
            $errorCode = $data["code"];
        }

        if ($responseCode == 500 && is_array($data) && isset($data["code"]) && $data["code"] == 101999) {
            $this->logger->debug("old auth token format, refreshing");
            $responseCode = 401;
        }

        // No data returned - Downstream system denied all data for the customer
        if ($responseCode == 403 && is_array($data) && isset($data["code"]) && $data["code"] == 205010) {
            sleep(5);
            $this->logger->notice("repeat request");
            $data = $this->curlRequest($url, $this->getCurlOptions($rewardsApi), $responseCode, $rewardsApi);
        }

        if ($responseCode == 401) {
            if ($this->refreshToken($rewardsApi)) {
                $this->logger->notice("repeat request");
                $data = $this->curlRequest($url, $this->getCurlOptions($rewardsApi), $responseCode, $rewardsApi);
            }
        }

        if ($responseCode < 200 || $responseCode > 299) {
            $this->logger->debug("responseCode: {$responseCode}");

            switch ($responseCode) {
                case 0:
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                case 400: // An invalid query parameter was provided or the value for a valid query parameter was malformed.
                    if (isset($data['text'])) {
                        if ($data['text'] == 'The server encountered an unexpected condition that prevented it from completing the request.') {
                            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                        }

                        if ($data['text'] == 'The given account is not rewards eligible.') {
                            throw new CheckException($data['text'], ACCOUNT_PROVIDER_ERROR);
                        }
                    }// if (isset($data['text']))
                // The code value in the response body specifies the errant query parameter. See the Errors section for details.
                // no break
                case 405: // The requested HTTP method isn’t supported;
                // the code value in the response body specifies the errant method name. See the Errors section for details.
                    throw new CheckException($this->getErrorFromResponse($data, "Provider error"), ACCOUNT_ENGINE_ERROR);

                    break;

                case 401: // The call did not contain a valid access token and is therefore not authorized.
                case 403: // Access to all available accounts is forbidden because the customer declined to authorize,
                    if ($allow403) {
                        return $data;
                    }

                    if ($errorCode == 102006) {
                        $this->logger->info("102006, new token detected");

                        throw new OldApiNewTokenException("Please wait while we are upgrading our system.", ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($errorCode >= 102000 && $errorCode < 103000) {
                        throw new CheckException("Access to all customer accounts is denied due to customer or account standing such as bankruptcy or suspected fraud.", ACCOUNT_PROVIDER_ERROR);
                    }
                    // no break
                case 404: // token too short (old)
                // the call included an invalid client ID or client secret,
                // the customer’s online profile cannot be found,
                // or the accounts were redacted by entitlements.
                // The code value provided in the response will provide some additional information about the specific problem;
                // see the Errors section for details.
                    $this->InvalidAnswers[$rewardsApi ? "rewards" : "tx"] = "none";
                    unset($this->State[self::getTokenName($rewardsApi)]);

                    if ($rewardsApi) {
                        throw new CheckException(self::MESSAGE_REWARDS_AUTH_LOST, ACCOUNT_INVALID_PASSWORD);
                    }

                    break;

                case 429:
                    $this->logger->debug("The service is unavailable due to rate limiting");
                    // "code": 101303,
                    // "description": "The service is unavailable due to rate limiting. Too Many Requests"
                    if (isset($data['description']) && $data['description'] == 'The service is unavailable due to rate limiting. Too Many Requests') {
                        throw new CheckRetryNeededException(3, $this->attempt * 5);
                    }

                    // no break
                case 500: // Internal server error; the server encountered an unexpected condition that prevented it from fulfilling the request.
                    if (is_array($data) && isset($data["code"]) && $data["code"] == 101999) {
                        $this->logger->debug("old auth token format, revoking");
                        $this->InvalidAnswers[$rewardsApi ? "rewards" : "tx"] = "none";
                        unset($this->State[self::getTokenName($rewardsApi)]);

                        throw new CheckException("We don’t have authorization from Capital One to update your account. Please authenticate yourself again.", ACCOUNT_INVALID_PASSWORD);
                    }
                    $error = $this->getErrorFromResponse($data, "Provider error");

                    if (strstr($error, "Sorry, we didn't find any accounts for that username")) {
                        throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        strstr($error, "We're unable to display your accounts because we need to verify some of your account info")
                        || strstr($error, "Our system experienced an error. Please try again later.")
                    ) {
                        throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($error == 'The server that stores rewards account information couldn’t be reached.') {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (isset($data['description']) && $data['description'] == 'General System Failure') {
                        throw new CheckRetryNeededException();
                    }

                    // no break
                case 503: // The service is currently unavailable; request should be retried at a later time.
                    $error = $this->getErrorFromResponse($data, "Provider error");

                    if (strstr($error, "Source system is unavailable or unresponsive")) {
                        throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }
                    // no break
                default:
                    throw new CheckException("Provider error", ACCOUNT_ENGINE_ERROR);
            }
        }

        return $data;
    }

    private function getCurlOptions(bool $rewardsApi)
    {
        if ($rewardsApi) {
            $accessToken = $this->State['access_token'] ?? $this->Answers['access_token'];
        } else {
            $accessToken = $this->State['tx_access_token'] ?? $this->Answers['tx_access_token'];
        }

        return [
            CURLOPT_HTTPHEADER => [
                "Accept:application/json;v=1",
                "Authorization:Bearer " . $accessToken,
                "User-Agent: awardwallet",
            ],
        ];
    }

    private function isSandbox(bool $rewardsApi)
    {
        return !empty($this->Answers[$rewardsApi ? 'sandbox' : 'tx_sandbox']);
    }

    private static function getTokenName(bool $rewardsApi): string
    {
        return $rewardsApi ? 'refresh_token' : 'tx_refresh_token';
    }

    private function refreshToken(bool $rewardsApi)
    {
        $this->http->Log("401, trying to refresh token");
        $constSuffix = '';

        if (!$rewardsApi) {
            $constSuffix .= '_TX';
        }

        if ($this->isSandbox($rewardsApi)) {
            $constSuffix .= '_SANDBOX';
        }
        $tokenName = self::getTokenName($rewardsApi);
        $result = $this->curlRequest(
            "/oauth2/token",
            [
                CURLOPT_HTTPHEADER => [
                    "User-Agent: awardwallet",
                ],
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    // constants defined parameters.yml, parsing_constants
                    'client_id'     => constant("CAPITALCARDS{$constSuffix}_CLIENT_ID"),
                    'client_secret' => constant("CAPITALCARDS{$constSuffix}_CLIENT_SECRET"),
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $this->State[$tokenName] ?? $this->Answers[$tokenName],
                ]),
            ],
            $responseCode,
            $rewardsApi
        );
        $tokenInfo = self::parseTokenInfo($result, $this->isSandbox($rewardsApi));

        if (!empty($tokenInfo)) {
            $this->http->Log("saved new token");
            $this->State[$rewardsApi ? 'access_token' : 'tx_access_token'] = $tokenInfo['access_token'];

            if (!empty($tokenInfo['refresh_token'])) {
                $this->State[$tokenName] = $tokenInfo['refresh_token'];
            }

            return true;
        }

        return false;
    }

    private function getErrorFromResponse($response, $default)
    {
        // @see https://developer.capitalone.com/platform-documentation/errors/
        // !!! description: A plaintext, human-readable description of the error. The audience for this string is you, the app developer;
        // you mustn’t display the message to the end-user.
        if (is_array($response) && !empty($response['code'])) {
            return $this->getErrorDescription($response['code']) ?: $default;
        }

        return $default;
    }

    private function getErrorDescription($code)
    {
        static $errors = [
            101117 => 'The Authorization header token is invalid.',
            101118 => 'The Authorization header token has expired.',
            101119 => 'You’re not authorized to access this API.',
            101120 => 'You’re not authorized to access this API.',
            101121 => 'You’re not authorized to access this API.',
            101216 => 'The Authorization header token is invalid.',
            101217 => 'The Authorization header token has expired.',
            //			101218 => 'The service is unavailable due to heavy traffic.',
            //			101301 => 'The URL couldn’t be reached.',
            //			101302 => 'The URL isn’t recognized.',
            102900 => "We're unable to display your accounts because we need to verify some of your account info. Give us a call at 1-866-750-0873 and we'll sort it out.",
            //			101999 => 'General system error.',
            102934 => 'Sorry, we didn\'t find any accounts for that username',
            103004 => 'Our system experienced an error. Please try again later.',
            //			201101 => 'The request contains an unrecognized query parameter. The description response property names the parameter.',
            //			201102 => 'The value of the creditCardLastFour query parameter is invalid.',
            201201 => 'The customer’s online profile couldn’t be found.',
            //			201202 => 'The rewardsAccountReferenceId path parameter doesn’t identify a valid rewards account.',
            201301 => 'The server that stores rewards account information couldn’t be reached.',
            201302 => 'Source system is unavailable or unresponsive.  Retry request at a later time.',
            202001 => 'The taxId query parameter is missing or its value is invalid.',
            202002 => 'The firstName query parameter is missing or its value is invalid.',
            202003 => 'The lastName query parameter is missing or its value is invalid.',
            202004 => 'The value of the middleName query parameter is invalid.',
            202005 => 'The value of the nameSuffix query parameter is invalid.',
            202006 => 'The addressLine1 query parameter is missing or its value is invalid.',
            202007 => 'The value of the addressLine2 query parameter is invalid.',
            202008 => 'The city query parameter is missing or its value is invalid.',
            202009 => 'The stateCode query parameter is missing or its value is invalid.',
            202010 => 'The postalCode query parameter is missing or its value is invalid.',
            202011 => 'The value of the dateOfBirth query parameter is invalid.',
            202012 => 'The value of the primaryBenefit query parameter is invalid.',
            202013 => 'The value of the bankAccountSummary query parameter is invalid.',
            202014 => 'The value of the selfAssessedCreditRating query parameter is invalid.',
        ];

        if (array_key_exists($code, $errors)) {
            return $errors[$code];
        }

        return false;
    }

    /**
     * @param $rewardsCurrency
     *
     * @return string
     * An enum that identifies the currency type associated with the rewards account. Valid values are:
     * - Cash
     * - Miles
     * - Points
     */
    private function convertCurrency($rewardsCurrency)
    {
        $rewardsCurrency = strtolower($rewardsCurrency);
        $rewardsCurrency = $rewardsCurrency == 'cash' ? '$' : $rewardsCurrency;

        return $rewardsCurrency;
    }

    private function loadApiTokens()
    {
        if (!empty($this->Answers['tx'])) {
            $this->logger->info("tx access tokens set");
            $tokens = json_decode($this->Answers['tx'], true);
            $this->Answers["tx_access_token"] = $tokens['access_token'];
            $this->Answers["tx_refresh_token"] = $tokens['refresh_token'];
            $this->Answers["tx_sandbox"] = $tokens['sandbox'];
        }

        if (!empty($this->Answers['rewards'])) {
            $this->logger->info("rewards access tokens set");
            $tokens = json_decode($this->Answers['rewards'], true);
            $this->Answers["access_token"] = $tokens['access_token'];
            $this->Answers["refresh_token"] = $tokens['refresh_token'];
            $this->Answers["sandbox"] = $tokens['sandbox'];
        }
    }

    private function getSubAccountCode(string $rewardsAccountReferenceId): string
    {
        return 'capitalcards' . md5($rewardsAccountReferenceId);
    }

    /**
     * @return array [
     *      "Dining" => 4,
     *      "All" => 2,
     *      "D:SomeDesc" => 10, // match by description
     * ]
     */
    private function getRewardsRatiosByCardName(?string $cardName): array
    {
        if ($cardName === null) {
            return [];
        }

        /*
         Code	Type

         1 Dining
         2 Gas/Automotive
         3 Merchandise
         4 Entertainment
         5 Airfare
         6 Car Rental
         7 Lodging
         8 Other Travel
         9 Phone/Cable
         10 Internet
         11 Utilities
         13 Professional Services
         14 Healthcare
         15 Insurance
         16 Other Services
         17 Other
         18 Grocery
         */

        // https://www.capitalone.com/credit-cards/compare/
        switch ($cardName) {
            case "Capital One Miles":
                // 5X miles on hotels and rental cars booked through Capital One Travel
                // 2X miles on every purchase
                return ["All" => 2];

            case "Journey":
                // 5% cash back on hotels and rental cars booked through Capital One Travel
                // 1% cash back on every purchase
                return ["All" => 1];

            case "JourneyStudent":
                // Earn 1% cash back on all your purchases. Pay on time to boost your cash back to a total of 1.25% for that month.
                // @TODO: how to calc 1.25 ?
                return ["All" => 1];

            case "Quicksilver":
                // Earn unlimited 1.5% cash back on every purchase, every day
                // Earn a one-time $200 cash bonus once you spend $500 on purchases within 3 months from account opening 2
                return ["All" => 1.5];

            case "QuicksilverOne":
                // Earn unlimited 1.5% cash back on every purchase, every day
                return ["All" => 1.5];

            case "SaksFirst Credit Card":
                // 2X points on your first $2,499 in purchases for the year
                // 4X points on your purchases between $2,500 and $9,999 for the year
                // 6X points on purchases totaling over $10,000 for the year
                return ["All" => 2];

            case "Savor":
                // Earn unlimited 4% cash back on dining and entertainment, 2% at grocery stores and 1% on all other purchases. 1
                // Earn a one-time $300 cash bonus once you spend $3,000 on purchases within 3 months from account opening 2
                return ["Dining" => 4, "Entertainment" => 4, "Grocery" => 2, "All" => 1];

            case "SavorOne":
                // Earn unlimited 3% cash back on dining and entertainment, 2% at grocery stores (excluding superstores like Walmart® and Target®) and 1% on all other purchases. 1
                // @TODO: exclude walmart
                // Earn a one-time $200 cash bonus once you spend $500 on purchases within the first 3 months from account opening 2
                return ["Dining" => 3, "Entertainment" => 3, "Grocery" => 2, "All" => 1];

            case "SparkCash":
            case "Spark Cash":
                // Unlimited 2% cash back on every purchase, every day
                // Earn a one-time $500 cash bonus once you spend $4,500 on purchases within the first 3 months from account opening 2
                return ["All" => 2];

            case "SparkMiles":
            case "Spark Miles":
                // Unlimited 2X miles per dollar on every purchase, every day - plus 5X miles on hotel & rental car bookings through Capital One Travel
                // Earn a one-time bonus of 50,000 miles – equal to $500 in travel – once you spend $4,500 on purchases within the first 3 months from account opening 2
                // @TODO: 5x for Capital One Travel
                return ["All" => 2];

            case "SparkCashSelect":
            case "Spark Cash Select":
                // Unlimited 1.5% cash back on every purchase, every day
                // Earn a one-time $200 cash bonus once you spend $3,000 on purchases within the first 3 months from account opening 2
                return ["All" => 1.5];

            case "SparkMilesSelect":
            case "Spark Miles Select":
                // Unlimited 1.5X miles per dollar on every purchase, every day - plus 5X miles on hotel & rental car bookings through Capital One Travel
                // Earn a one-time bonus of 20,000 miles – equal to $200 in travel – once you spend $3,000 on purchases within the first 3 months from account opening 2
                // @TODO: 5x for Capital One Travel
                return ["All" => 1.5];

            case "SparkClassic":
            case "Spark Classic":
                // Get the credit you want for your business, and unlimited 1% cash back on every purchase, every day
                return ["All" => 1];

            case "Spark Classic Miles":
                // Capital One Visa Business Miles
                // 5X miles on hotels and rental cars booked through Capital One Travel
                // 2X miles on every purchase
                return ["All" => 2];

            case "BuyPowerBusiness":
                // Get 5% Earnings at GM Dealerships, get 3% Earnings on dining, gas and office supply stores, and 1% on everything else.
                // @TODO: detect office supply, GM
                return ["Dining" => 3, "Gas/Automotive" => 3, "All" => 1];

            case "BuyPower":
                // Get 5% Earnings on your first $5,000 in purchases every year. Then get unlimited 2% Earnings on purchases after that.
                // @TODO: first $5,000
                return ["All" => 2];

            case "Venture":
                // Earn unlimited 2X miles per dollar on every purchase, every day
                // Earn 60,000 bonus miles once you spend $3,000 on purchases within the first 3 months from account opening 2
                return ["All" => 2];

            case "VentureOne":
                // Earn unlimited 1.25 miles per dollar on every purchase, every day
                // Earn 20,000 bonus miles once you spend $500 on purchases within the first 3 months from account opening 2
                return ["All" => 1.25];

            case "Venture X":
            case "Venture X Business":
                // - 10X miles on hotels and rental cars booked via Capital One Travel
                // - 5X miles on flights booked via Capital One Travel
                // - 2X miles on all other purchases
                return ["All" => 2, "D:COTHTL" => 10, "D:COTFLT" => 5];

            case "Walmart":
            case "Walmart Rewards Card":
            case "Capital One Walmart Rewards Card":
                // Earn 5% back on purchases made at Walmart.com and the Walmart app, 2% back on purchases in Walmart stores and Murphy USA gas stations, 2% back on restaurant and travel purchases, 1% back on all other purchases
                // Earn 5% back on in-store purchases at Walmart using Walmart Pay for the first 12 months after approval.
                // @TODO: 2x for walmart etc
                return ["Dining" => 2, "Other Travel" => 2, "Airfare" => 2, "Car Rental" => 2, "All" => 1];

            // @TODO: Cabela, BassPro
//            default:
                //$this->sendNotification("Unknown capital one card: {$cardName}");
        }

        return ["IsHidden" => true];
    }

    private function markTxAccessInvalid(): void
    {
        $this->SetWarning(self::MESSAGE_TX_AUTH_LOST);
        $this->InvalidAnswers["tx"] = "none";
        unset($this->State[self::getTokenName(false)]);
    }

    private function newParse()
    {
        $this->logger->info("newParse");
        $data = $this->apiRequest('/loyalty/accounts/~/associated-accounts/partners/awardwallet');
        // {
        //  "associatedAccounts": [
        //    {
        //      "associatedAccountReferenceId": "t.62886c533fb5429f97fc326274a22521",
        //      "accountDetails": {
        //        "lastFourCardNumber": "1234",
        //        "cardIssuer": "Capital One",
        //        "cardProductBrandName": "Venture",
        //        "processingNetwork": "Visa",
        //        "isBusinessAccount": false
        //      },
        //      "rewardsBalance": {
        //        "rewardsCurrency": "MILES",
        //        "rewardsCurrencyDescription": "REWARDS MILES",
        //        "rewardsBalance": 245424,
        //        "balanceTimestamp": "2024-05-01T20:35:50.167Z"
        //      },
        //      "rewardsDetails": {
        //        "accountDisplayName": "Capital One Venture Visa *5678",
        //        "canRedeemRewards": true,
        //        "canTransferRewardsIntoAccount": true,
        //        "canTransferRewardsFromAccount": true,
        //        "productAccountType": "Credit Card"
        //      }
        //    },
        //    {
        //      "associatedAccountReferenceId": "t.2abd8834bfc3478ab4b1f0583601fd91",
        //      "accountDetails": {
        //        "lastFourCardNumber": "1234",
        //        "cardIssuer": "Capital One",
        //        "cardProductBrandName": "Venture X Business",
        //        "processingNetwork": "MasterCard",
        //        "isBusinessAccount": false
        //      },
        //      "rewardsBalance": {
        //        "rewardsCurrency": "MILES",
        //        "rewardsCurrencyDescription": "REWARDS MILES",
        //        "rewardsBalance": 606994,
        //        "balanceTimestamp": "2024-04-30T22:08:04.606Z"
        //      },
        //      "rewardsDetails": {
        //        "accountDisplayName": "Capital One Venture X Business MasterCard *5678",
        //        "canRedeemRewards": true,
        //        "canTransferRewardsIntoAccount": true,
        //        "canTransferRewardsFromAccount": true,
        //        "productAccountType": "Credit Card"
        //      }
        //    }
        //  ],
        //  "customerDetails": {
        //    "firstName": "Alexi",
        //    "lastName": "Vereschaga"
        //  },
        //  "profileReferenceId": "t.aff75ee2f31c48a2be983d8c7f2f75f3"
        //}
        if (is_array($data) && !empty($data['associatedAccounts'])) {
            $accounts = $data['associatedAccounts'];
            $SubAccounts = [];

            foreach ($accounts as $account) {
                $details = $this->newParseAccountReference($account);

                if ($details !== false) {
                    $SubAccounts[] = $details;
                    $this->AddDetectedCard([
                        "Code"            => $details['Code'],
                        "DisplayName"     => $details['DisplayName'],
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ]);
                }
            }

            if (empty($SubAccounts)) {
                $this->InvalidAnswers["rewards"] = "none";
                unset($this->State[self::getTokenName(true)]);

                throw new CheckException("Access to all customer accounts is denied. Please go to the \"Edit\" screen of this account and press the \"Connect with Capital One\" button to reconnect your account.", ACCOUNT_INVALID_PASSWORD);
            }

            if (count($SubAccounts) === 1) {
                $this->logger->info("one subaccount, setting as main balance and hiding");
                $this->SetBalance($SubAccounts[0]['Balance']);
                $this->SetProperty('Currency', $SubAccounts[0]['Currency']);
                $SubAccounts[0]['IsHidden'] = true;
            } else {
                $this->SetBalanceNA();
            }

            $this->SetProperty("SubAccounts", $SubAccounts);
        } else {
            // refs #1522, https://redmine.awardwallet.com/issues/15226#note-13
            if (is_array($data) && isset($data['associatedAccounts']) && $data['associatedAccounts'] == []) {
                throw new CheckException("We don't see any reward accounts under this login", ACCOUNT_PROVIDER_ERROR);
            }/*review*/

            throw new CheckException("Empty data", ACCOUNT_ENGINE_ERROR);
        }

        // refs #21126
        $this->logger->debug("Miles Balance: {$this->milesBalance} / cards: {$this->cardsWithMiles}");
        $this->logger->debug("Points Balance: {$this->pointsBalance} / cards: {$this->cardsWithPints}");
        $this->logger->debug("Cash Balance: {$this->cashBalance} / cards: {$this->cardsWithCash}");

        if (isset($this->Properties['SubAccounts'])) {
            $countSubAccounts = count($this->Properties['SubAccounts']);
            $this->logger->debug("count subAccounts: $countSubAccounts");

            if (/*$this->cardsWithMiles == $countSubAccounts && */ $this->milesBalance !== null) {
                $this->SetBalance($this->milesBalance);
                /*
                } elseif ($this->cardsWithPints == $countSubAccounts && $this->cashBalance !== null) {
                    $this->SetBalance($this->cardsWithPints);
                } elseif ($this->cardsWithCash == $countSubAccounts && $this->cashBalance !== null) {
                    $this->SetBalance($this->cashBalance);
                }

                if (!is_null($this->Balance)) {
                */
                for ($i = 0; $i < $countSubAccounts; $i++) {
                    if ($this->Properties['SubAccounts'][$i]['Currency'] == 'miles') {
                        $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
                    }
                }
            }
        }

        $this->newApi = true;
    }

    private function newParseAccountReference(array $data)
    {
        if (is_array($data) && !empty($data['rewardsDetails']['accountDisplayName'])) {
            if (!isset($data['rewardsBalance']) && !isset($data['rewardsBalance']['rewardsCurrency'])) {
                return false;
            }

            // provider bug fix
            if (!isset($data['rewardsBalance']['rewardsCurrency'])) {
                switch ($data['rewardsBalance']['rewardsCurrencyDescription']) {
                    case 'REWARDS MILES':
                        $data['rewardsBalance']['rewardsCurrency'] = 'MILES';

                        break;

                    case 'REWARDS POINTS':
                        $data['rewardsBalance']['rewardsCurrency'] = 'POINTS';

                        break;

                    case 'REWARDS CASH':
                        $data['rewardsBalance']['rewardsCurrency'] = 'CASH';

                        break;
                }// switch ($data['rewardsBalance']['rewardsCurrencyDescription'])
            }// if (!isset($data['rewardsBalance']['rewardsCurrency']))

            switch ($data['rewardsBalance']['rewardsCurrency']) {
                case 'MILES':
                    $this->cardsWithMiles++;

                    if (is_null($this->milesBalance)) {
                        $this->milesBalance = $data['rewardsBalance']['rewardsBalance'] ?? 0;
                    } else {
                        $this->milesBalance += $data['rewardsBalance']['rewardsBalance'] ?? 0;
                    }

                    break;

                case 'POINTS':
                    $this->cardsWithPints++;

                    if (is_null($this->pointsBalance)) {
                        $this->pointsBalance = $data['rewardsBalance']['rewardsBalance'] ?? 0;
                    } else {
                        $this->pointsBalance += $data['rewardsBalance']['rewardsBalance'] ?? 0;
                    }

                    break;

                case 'CASH':
                    $this->cardsWithCash++;

                    if (is_null($this->cashBalance)) {
                        $this->cashBalance = $data['rewardsBalance']['rewardsBalance'] ?? 0;
                    } else {
                        $this->cashBalance += $data['rewardsBalance']['rewardsBalance'] ?? 0;
                    }

                    break;
            }

            return [
                'Code'        => $data['associatedAccountReferenceId'],
                'DisplayName' => $data['rewardsDetails']['accountDisplayName'],
                'Currency'    => $this->convertCurrency($data['rewardsBalance']['rewardsCurrency']),
                'Balance'     => $data['rewardsBalance']['rewardsBalance'] ?? null,
            ];
        }

        return false;
    }
}
