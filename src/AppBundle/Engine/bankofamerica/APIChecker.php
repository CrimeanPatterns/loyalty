<?php

namespace AwardWallet\Engine\bankofamerica;

use AwardWallet\Common\DateTimeUtils;

class APIChecker extends \TAccountChecker
{
    private const PERSISTENT_COOKIES = ["VPID", "JS_AGW", 'x-boa-portal-session-persistent-id'];

    private $sessionId;
    private $curl;

    private $pointBalance = null;
    private $cashBalance = null;
    private $noBalance = 0;

    public function __construct()
    {
        parent::__construct();
        $this->sessionId = bin2hex(random_bytes(5));
        $this->KeepState = true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] != '$') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f " . $properties['Currency']);
        }

        return parent::FormatBalance($fields, $properties);
    }

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

    public function GetHistoryColumns()
    {
        return [
            "Date"                    => "PostingDate",
            "Description"             => "Description",
            "Amount"                  => "Amount",
            "Currency"                => "Currency",
            "Category"                => "Category",
            "Status"                  => "Info",
            "Type"                    => "Info",
            "Points"                  => "Miles",
        ];
    }

    public function Parse()
    {
        if (empty($this->Answers['access_token'])) {
            throw new \CheckException('Authorization required', ACCOUNT_INVALID_PASSWORD);
        }

        // they (BofA developers) asked as to do refresh every time. ok, as you wish.
        $tokenRefreshed = $this->refreshToken();

        if (
            !$tokenRefreshed
            && isset($this->State["refresh_token"])
            && isset($this->Answers["refresh_token"])
            && ($this->Answers["refresh_token"] !== $this->State["refresh_token"])
        ) {
            $this->logger->info("refresh_token from State was declined, try with token from answers");
            $this->refreshToken($this->Answers["refresh_token"]);
        }

        //		$data = $this->apiRequest("/dda/v1/boa/customer");
        //		$this->SetProperty("Name", $data["Customer"]["name"]["first"] . " " . $data["Customer"]["name"]["last"]);

        try {
            $data = $this->apiRequest("/dda/v1/boa/accountlist");
            $this->SetProperty("CombineSubAccounts", false);

            /**
             * {
             * "AccountDescriptorList": {
             * "accountDescriptor": [
             * {
             * "accountId": "c6cba275c79026cdfad16a90af318de6b57897dca60dcbcff2ff35e25c70cc5b",
             * "accountType": "CHECKING",
             * "displayName": "BofA Core Checking - 1091",
             * "status": "OPEN"
             * },
             * {
             * "accountId": "8ff7cdfb0c2929334833abaa12558230572f8e0470776bbac7650142b0c725e2",
             * "accountType": "SAVINGS",
             * "displayName": "Rewards Savings - 1101",
             * "status": "OPEN"
             * },
             * {
             * "accountId": "ed33d71a3784a5fd0bbe465330446fdb030ec497cfa41a2e049ec0a7fd78731f",
             * "accountType": "CREDITCARD",
             * "displayName": "Bank of America Cash Rewards Visa Signature - 4481",
             * "status": "OPEN"
             * }
             * ]
             * }
             * }.
             */
            if (is_array($data) && !empty($data['AccountDescriptorList'])) {
                $accounts = $data['AccountDescriptorList']['accountDescriptor'];
                $checkingAndSavings = 0;

//                $corpCards = array_filter($accounts, function(array $account) {
//                    return $account['accountType'] === 'CREDITCARD'
//                        && stripos($account['displayName'], 'CORP Account ') === 0;
//                });
//                $this->logger->info("we have " . count($corpCards) . " corp cards");
//                $corpCardId = null;
//                if (count($corpCards) === 1) {
//                    $corpCardId = (reset($corpCards))["accountId"];
//                    $this->logger->info("selected corpCardId $corpCardId");
//                }
//

                $displayNames = [];

                foreach ($accounts as $account) {
                    $displayNames[$account['accountId']] = $account['displayName'] ?? null;
                }

                $skipCards = [];
                $addCards = [];

                foreach ($accounts as $account) {
                    if ($account['accountType'] !== 'CREDITCARD') {
                        if (in_array($account['accountType'], ['CHECKING', 'SAVINGS', 'LOAN'])) {
                            $checkingAndSavings++;
                        }

                        continue;
                    }

                    [$details, $hideCardWithId] = $this->ParseAccountReference($account['accountId'], $displayNames);

                    if ($details !== false) {
                        $addCards[] = $details;
                    }

                    if ($hideCardWithId !== null) {
                        $this->logger->info("will skip card $hideCardWithId");
                        $skipCards[] = $hideCardWithId;
                    }
                }

                foreach ($addCards as $details) {
                    if (in_array($details['Code'], $skipCards)) {
                        $this->logger->info("skipping card " . $details['Code']);

                        continue;
                    }

                    $this->AddDetectedCard([
                        "Code"            => $details['Code'],
                        "DisplayName"     => $details['DisplayName'],
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ]);
                    $this->AddSubAccount($details);
                }

                if (empty($this->Properties['SubAccounts'])) {
                    // AccountID: 2046913
                    if ($checkingAndSavings > 0) {
                        $this->logger->info("I see savings or checkings or loan");
                        $this->SetBalanceNA();
                    } else {
                        throw new \CheckException("Empty data 1", ACCOUNT_ENGINE_ERROR);
                    }
                } else {
                    $this->logger->debug("Point Balance: {$this->pointBalance}");
                    $this->logger->debug("Cash Balance: {$this->cashBalance}");

                    if (isset($this->pointBalance)) {
                        $this->SetBalance($this->pointBalance);
                    } elseif (isset($this->cashBalance)) {
                        $this->SetBalance($this->cashBalance);
                        $this->SetProperty('Currency', '$');
                    } else {
                        $this->SetBalanceNA();
                    }

                    // refs #16831, https://redmine.awardwallet.com/issues/16831#note-79
                    if (
                        !empty($this->Properties['SubAccounts'])
                        && (count($this->Properties['SubAccounts']) == 1 || (count($this->Properties['SubAccounts']) - $this->noBalance) == 1)
                    ) {
                        $this->Properties['SubAccounts'][0]['IsHidden'] = true;
                    }
                }
            } else {
                // AccountID: 3808477
                if (
                    is_array($data)
                    && isset($data['Error']['code'], $data['Error']['message'])
                    && $data['Error']['code'] == '306'
                    && $data['Error']['message'] == 'Unable to retrieve details for charged off account'
                ) {
                    throw new \CheckException($data['Error']['message'], ACCOUNT_PROVIDER_ERROR);
                }

                // refs #1522, https://redmine.awardwallet.com/issues/15226#note-13
                //		    if (is_array($data) && isset($data['rewardsAccounts']) && $data['rewardsAccounts'] == [])
//                throw new \CheckException("We don't see any reward accounts under this login", ACCOUNT_PROVIDER_ERROR);/*review*/
                throw new \CheckException("Empty data 2", ACCOUNT_ENGINE_ERROR);
            }
        } finally {
            if ($this->curl !== null) {
                curl_close($this->curl);
            }
        }
    }

    public static function getBaseHost($sandbox)
    {
        return "https://vendorservices-awardwallet.bankofamerica.com/apigateway/awardwallet";
    }

    public static function parseCurlCookies(array $curlCookieList)
    {
        $cookies = [];

        foreach ($curlCookieList as $cookieStr) {
            [$domain, $includeSubDomains, $path, $secure, $expiration, $name, $value] = explode("\t", $cookieStr);
            $cookies[$name] = $value;
        }

        return $cookies;
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

    /**
     * @return array - [false|array $parsedCard, ?string $hideCardWithId]
     */
    private function ParseAccountReference($accountId, array $displayNames)
    {
        $data = $this->apiRequest(
            "/dda/v1/boa/accountsdetails",
               '{"singleAccountDetailsRequestList":{"singleAccountDetailsRequest" : [ { "accountId" : "' . $accountId . '"}]}}',
               true
           );
        $result = false;
        $hideCardWithId = null;
        /**
           {
             "Accounts": {
               "depositAccount": [
                 {
                   "accountId": "c6cba275c79026cdfad16a90af318de6b57897dca60dcbcff2ff35e25c70cc5b",
                   "accountNumber": "138124391091",
                   "currency": {
                     "currencyCode": "USD"
                   },
                   "displayName": "BofA Core Checking - 1091",
                   "nickname": null,
                   "balanceAsOf": "2018-08-22T18:17:31.440+0000",
                   "availableBalance": 1,
                   "accountType": "CHECKING",
                   "routingTransitNumber": "125000024",
                   "lineOfBusiness": "Personal",
                   "interestYtd": 0,
                   "fiAttributes": [
                     {
                       "name": "accountOpenedDate",
                       "value": "2018-05-21"
                     },
                     {
                       "name": "interestPaidLastYear",
                       "value": "0.00"
                     }
                   ],
                   "transactionsIncluded": "false"
                 }
               ]
             }
           }
         */
        if (is_array($data) && !empty($data['Accounts']['depositAccount'])) {
            $data = $data['Accounts']['depositAccount'][0];
            $this->logger->info($data['displayName'], ['Header' => 3]);
            $result = [
                'Code'        => $accountId,
                'DisplayName' => $data['displayName'],
                'Currency'    => $data['currency']['currencyCode'],
                'Balance'     => $data['availableBalance'],
            ];
        }
        /*
        {
          "Accounts": {
            "locAccount": [
              {
                "accountId": "ed33d71a3784a5fd0bbe465330446fdb030ec497cfa41a2e049ec0a7fd78731f",
                "accountNumber": "XXXX-XXXX-XXXX-4481",
                "currency": {
                  "currencyCode": "USD"
                },
                "displayName": "Bank of America Cash Rewards Visa Signature - 4481",
                "nickname": null,
                "balanceAsOf": "2018-08-22T18:44:24.030+0000",
                "currentBalance": 0,
                "lineOfBusiness": "Personal",
                "accountType": "CREDITCARD",
                "creditLine": 100,
                "availableCredit": 100,
                "availableCash": 100,
                "cashAdvanceLimit": 100,
                "minimumPaymentAmount": 0,
                "nextPaymentDate": null,
                "nextPaymentAmount": null,
                "lastPaymentAmount": 94.45,
                "lastPaymentDate": "2018-06-13T00:00:00.000+0000",
                "currentRewardsBalance": 2.32,
                "pointsAccrued": null,
                "fiAttributes": [
                  {
                    "name": "accountOpenedDate",
                    "value": "2018-05-21"
                  },
                  {
                    "name": "amountOverTotalCreditLine",
                    "value": "0.0"
                  },
                  {
                    "name": "statementBalance",
                    "value": "0.0"
                  },
                  {
                    "name": "nextClosingDate",
                    "value": "2018-09-18"
                  },
                  {
                    "name": "RewardsProgramName",
                    "value": "Cash Rewards"
                  }
                ],
                "transactionsIncluded": "false"
              }
         */
        if (is_array($data) && !empty($data['Accounts']['locAccount'])) {
            $data = $data['Accounts']['locAccount'][0];
            $this->logger->info($data['displayName'], ['Header' => 3]);
            [$hideCardWithId, $data['displayName']] = $this->correctBusinessCardDisplayName($data, $displayNames);

            $currency = is_null($data['currentRewardsBalance']) ? '' : '$';
            $result = [
                'Code'        => $accountId,
                'DisplayName' => $data['displayName'],
                'Currency'    => $currency, //$data['currency']['currencyCode'],
                'Balance'     => $data['pointsAccrued'] ?? $data['currentRewardsBalance'],
            ];

            if (empty($currency)) {
                if (is_null($this->pointBalance)) {
                    $this->pointBalance = $data['pointsAccrued'];
                } else {
                    $this->pointBalance += $data['pointsAccrued'];
                }
                $result['BalanceInTotalSum'] = true;
            } else {
                if (is_null($this->cashBalance)) {
                    $this->cashBalance = $data['currentRewardsBalance'];
                } else {
                    $this->cashBalance += $data['currentRewardsBalance'];
                }
            }

            if (is_null($data['pointsAccrued']) && is_null($data['currentRewardsBalance'])) {
                $this->noBalance++;
            }

            $cardDescription = C_CARD_DESC_ACTIVE;

            if (is_null($result['Balance'])) {
                $cardDescription = C_CARD_DESC_DO_NOT_EARN;

                if (strstr($result['DisplayName'], 'Alaska Airlines')) {
                    $cardDescription = C_CARD_DESC_ALASKA_AIR;
                }
                $result['IsHidden'] = true;
            }
            $this->AddDetectedCard([
                "Code"            => $accountId,
                "DisplayName"     => $data['displayName'],
                "CardDescription" => $cardDescription,
            ], true);

            if (isset($data['fiAttributes'])) {
                $attributes = [];

                foreach ($data['fiAttributes'] as $keyPair) {
                    $attributes[$keyPair['name']] = $keyPair['value'];
                }

                if (isset($attributes['RewardsProgramName'])) {
                    $result['RewardsProgramName'] = html_entity_decode($attributes['RewardsProgramName']);

                    // refs #20527
                    if (empty($result['Currency']) && $result['RewardsProgramName'] == 'Cash Rewards') {
                        $result['Currency'] = '$';
                    }
                }
            }

            if ($this->WantHistory) {
                $this->logger->warning("parsing history for account $accountId, {$result['DisplayName']}");

                $historyStartDate = $this->getSubAccountHistoryStartDate($accountId);

                if (!empty($historyStartDate)) {
                    $historyStartDate = min(strtotime("-13 month"), strtotime("-6 month", $historyStartDate));
                } else {
                    $historyStartDate = strtotime("-3 year");
                }
                $historyStartDate = strtotime("00:00", $historyStartDate);

                if ($data['accountType'] === 'CREDITCARD' && $historyStartDate < strtotime("-13 month 00:00")) {
                    // https://redmine.awardwallet.com/issues/19506#note-3
                    $this->logger->info("For credit cards, only the past 11 cycle would be available. Grab only last year");
                    $historyStartDate = strtotime("-13 month 00:00");
                }

                [$historyStartDate, $transactions] = $this->loadTransactions($accountId, $historyStartDate, $data['currency']['currencyCode']);

                // get statements and calc points from them
                // we do not process cash rewards because we could not detect preferred categories.
                // user select this categories himself, every month
                $transactionsWithStatements = $this->parseStatements($transactions, $historyStartDate, $data['parentAccountId'] ?? $accountId, isset($data['parentAccountId']) ? substr($data['accountNumber'], -4) : null);

                if (isset($result['RewardsProgramName']) && $result['RewardsProgramName'] !== 'Cash Rewards') {
                    $transactions = $transactionsWithStatements;
                }

                $result['HistoryRows'] = $transactions;
            }

            $this->logger->debug("Point Balance: {$this->pointBalance}");
            $this->logger->debug("Cash Balance: {$this->cashBalance}");
        }

        return [$result, $hideCardWithId];
    }

    private function getPurchases(array $transactions, int $startDate, int $endDate): array
    {
        $this->logger->info("calculating purchase sum in date range: " . date("Y-m-d", $startDate) . " - " . date("Y-m-d", $endDate));
        $endDate = strtotime("+1 day", $endDate);
        $transactions = array_filter($transactions, function (array $tx) use ($startDate, $endDate) {
            $result = $tx['PostedDate'] >= $startDate
                && $tx['PostedDate'] < $endDate
                && ($tx['Type'] === 'WITHDRAWAL' || $tx['Type'] === 'ADJUSTMENT')
                && stripos($tx['Category'], 'Transfers') === false
            ;

            if ($result) {
                $this->logger->info(date("Y-m-d", $tx['Date']) . " " . $tx['Description'] . " - " . $tx['Category'] . ' - ' . $tx['Status'] . ' - ' . $tx['Amount']);
            }

            return $result;
        });
        $result = round(array_sum(array_map(function (array $tx) { return round($tx['Amount'] * 100); }, $transactions)) / 100, 2);
        $this->logger->info("total: $result");

        return $transactions;
    }

    private function apiRequest($url, $postData = [], $postDataIsJson = false, $accept = null, $cacheable = false)
    {
        if ($accept === null) {
            $accept = 'application/json';
        }

        if ($cacheable) {
            $cacheKey = "bofa_api_" . sha1("url" . json_encode($postData));
            $cached = \Cache::getInstance()->get($cacheKey);

            if ($cached !== false) {
                return $cached;
            }
        }

        $retriesOnResponseCodes = [
            503,
            429,
            0,
        ];
        $sendRequest = function () use ($postData, $url, $postDataIsJson, $accept, $retriesOnResponseCodes) {
            $curlOptions = $this->getCurlOptions();
            $curlOptions[CURLOPT_HTTPHEADER][] = "Accept: $accept";

            if (!empty($postData)) {
                if ($postDataIsJson) {
                    $curlOptions[CURLOPT_POSTFIELDS] = $postData;
                    $curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
                } else {
                    $curlOptions[CURLOPT_POSTFIELDS] = http_build_query($postData);
                    $curlOptions[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
                }
                $curlOptions[CURLOPT_POST] = true;
            }

            $startTime = microtime(true);

            do {
                $body = $this->curlRequest($url, $curlOptions, $responseCode);

                if (in_array($responseCode, $retriesOnResponseCodes)) {
                    usleep(random_int(300000, 4800000));
                }
            } while ((in_array($responseCode, $retriesOnResponseCodes)) && (microtime(true) - $startTime) < 20);

            return [$body, $responseCode];
        };

        [$data, $responseCode] = $sendRequest();

        if ($responseCode == 403) {
            if (stripos(json_encode($data), 'Client certificate is expired or invalid') !== false) {
                throw new \CheckException("Invalid certificate", ACCOUNT_ENGINE_ERROR);
            }

            if ($this->refreshToken()) {
                $this->logger->notice("repeat request");
                [$data, $responseCode] = $sendRequest();
            }
        }

        if ($responseCode !== 200) {
            $this->logger->debug("responseCode: {$responseCode}");

            switch ($responseCode) {
                case 400: // An invalid query parameter was provided or the value for a valid query parameter was malformed.
                    if (isset($data['Error']['message']) && strstr($data['Error']['message'], 'Invalid Date range for this product')) {
                        throw new InvalidDateRangeException($data['Error']['message']);
                    }
                    // The code value in the response body specifies the errant query parameter. See the Errors section for details.
                    // no break
                case 405: // The requested HTTP method isn’t supported;
                    // the code value in the response body specifies the errant method name. See the Errors section for details.
                    throw new \CheckException($this->getErrorFromResponse($data, "Provider error"), ACCOUNT_ENGINE_ERROR);

                    break;

                case 401: // The call did not contain a valid access token and is therefore not authorized.
                case 403: // Access to all available accounts is forbidden because the customer declined to authorize,
                    // the call included an invalid client ID or client secret,
                    // the customer’s online profile cannot be found,
                    // or the accounts were redacted by entitlements.
                    // The code value provided in the response will provide some additional information about the specific problem;
                    // see the Errors section for details.
                    if (stripos(json_encode($data), 'Invalid Source IP') !== false) {
                        throw new \CheckException("Invalid Source IP", ACCOUNT_ENGINE_ERROR);
                    }

                    if (stripos(json_encode($data), 'unauthorized certificate') !== false) {
                        throw new \CheckException("unauthorized certificate", ACCOUNT_ENGINE_ERROR);
                    }

                    if (strstr(json_encode($data), "Requested Account isn't eligible or doesn't exist")) {
                        $this->logger->notice("Requested Account isn't eligible or doesn't exist");

                        break;
                    }
                    $this->InvalidAnswers["refresh_token"] = "none";

                    throw new \CheckException("We don’t have authorization from Bank of America to update your account. Please authenticate yourself again.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case 500: // Internal server error; the server encountered an unexpected condition that prevented it from fulfilling the request.
                    if (is_array($data) && isset($data["code"]) && $data["code"] == 101999) {
                        $this->logger->debug("old auth token format, revoking");
                        $this->InvalidAnswers["refresh_token"] = "none";

                        throw new \CheckException("We don’t have authorization from Bank of America to update your account. Please authenticate yourself again.", ACCOUNT_INVALID_PASSWORD);
                    }
                    $error = $this->getErrorFromResponse($data, "Provider error");

                    if (strstr($error, "Sorry, we didn't find any accounts for that username")) {
                        throw new \CheckException($error, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (strstr($error, "We're unable to display your accounts because we need to verify some of your account info")) {
                        throw new \CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (strstr($error, "Requested Account isn't eligible or doesn't exist")) {
                        $this->logger->notice($error);
                    }

                    // AccountID: 5394674
                    if (isset($data['Error']['message']) && strstr($data['Error']['message'], 'There was a problem processing your request. We are unable to process your request at this time. Please try again later')) {
                        throw new \CheckException($data['Error']['message'], ACCOUNT_PROVIDER_ERROR);
                    }

                    break;

                case 503: // The service is currently unavailable; request should be retried at a later time.
                    $error = $this->getErrorFromResponse($data, "Provider error");

                    if (strstr($error, "Source system is unavailable or unresponsive")) {
                        throw new \CheckException($error, ACCOUNT_PROVIDER_ERROR);
                    }
                    // no break
                default:
                    throw new \CheckException("HTTP Error $responseCode", ACCOUNT_ENGINE_ERROR);
            }
        }

        if ($cacheable) {
            \Cache::getInstance()->set($cacheKey, $data, DateTimeUtils::SECONDS_PER_DAY * 14);
        }

        return $data;
    }

    private function getCurlOptions()
    {
        if (isset($this->State["access_token"])) {
            $accessToken = $this->State['access_token'];
        } else {
            $accessToken = $this->Answers['access_token'];
        }

        return [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $accessToken,
                "User-Agent: awardwallet",
                'X-BOA-Session-ID: ' . $this->sessionId,
                'X-BOA-Trace-ID: ' . bin2hex(random_bytes(5)),
            ],
        ];
    }

    private function curlRequest($url, $options, &$responseCode, $restoreCookies = true)
    {
        \StatLogger::getInstance()->info("bankofamerica api request", ["IsBackground" => $this->isBackgroundCheck()]);
        $url = self::getBaseHost($this->isSandbox()) . $url;

        $options[CURLOPT_URL] = $url;

        if (!isset($options[CURLOPT_POSTFIELDS])) {
            $options[CURLOPT_POSTFIELDS] = null;
            $options[CURLOPT_POST] = false;
        }

        if ($this->curl === null) {
            $this->curl = curl_init();

            if (!$this->curl) {
                throw new \CheckException("failed to init curl", ACCOUNT_ENGINE_ERROR);
            }
            curl_setopt($this->curl, CURLINFO_HEADER_OUT, true);
            curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($this->curl, CURLOPT_TIMEOUT, 60);
            curl_setopt($this->curl, CURLOPT_HEADER, true);
            curl_setopt($this->curl, CURLOPT_FAILONERROR, true);
            curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($this->curl, CURLOPT_FAILONERROR, false);
            curl_setopt($this->curl, CURLOPT_COOKIEFILE, "");

//            if ($this->AccountFields['UserID'] == 7 || $this->AccountFields['UserID'] == 890628) {
            curl_setopt($this->curl, CURLOPT_SSLCERT, BANKOFAMERICA_SSL_CERT_FILE_2024);
//                $this->logger->info("testing new cert");
//            } else {
//                curl_setopt($this->curl, CURLOPT_SSLCERT, BANKOFAMERICA_SSL_CERT_FILE_2023);
//            }

            if (defined('WHITE_PROXY')) {
                // ssh -L localhost:3128:whiteproxy.infra.awardwallet.com:3128 192.168.2.166
                // set white_proxy=host.docker.internal in parameters.yml:parsing_constants
                curl_setopt($this->curl, CURLOPT_PROXY, WHITE_PROXY . ':3128');
            } else {
                curl_setopt($this->curl, CURLOPT_PROXY, 'whiteproxy.infra.awardwallet.com:3128');
            }
        }

        curl_setopt($this->curl, CURLOPT_COOKIESESSION, true);
        curl_setopt($this->curl, CURLOPT_COOKIELIST, "ALL"); // remove all cookies

        if ($restoreCookies) {
            $cookieStr = "";

            foreach (self::PERSISTENT_COOKIES as $cookieName) {
                if (isset($this->State[$cookieName])) {
                    $cookieValue = $this->State[$cookieName];
                } elseif (isset($this->Answers[$cookieName])) {
                    $cookieValue = $this->Answers[$cookieName];
                } else {
                    continue;
                }
                $cookieStr .= "{$cookieName}=" . $cookieValue . ";";
            }

            if (!empty($cookieStr)) {
                $this->logger->info("setting cookieStr: $cookieStr");
                $options[\CURLOPT_COOKIE] = $cookieStr;
            }
        }

        foreach ($options as $key => $value) {
            curl_setopt($this->curl, $key, $value);
        }
        $result = curl_exec($this->curl);
        $curlErrno = curl_errno($this->curl);
        $requestInfo = curl_getinfo($this->curl);

        $cookies = self::parseCurlCookies(curl_getinfo($this->curl, CURLINFO_COOKIELIST));

        foreach (self::PERSISTENT_COOKIES as $cookieName) {
            if (isset($cookies[$cookieName])) {
                $this->State[$cookieName] = $cookies[$cookieName];
                $this->logger->info("set cookie $cookieName to {$cookies[$cookieName]}");
                unset($this->Answers[$cookieName]);
            }
        }

        $responseCode = $requestInfo['http_code'];
        $contentType = ($requestInfo['content_type'] ?? null);
        $contentType = preg_replace('#;.*$#ims', '', $contentType);
        $log = $result;

        // cURL automatically handles Proxy rewrites, remove the "HTTP/1.0 200 Connection established" string
        while (preg_match("#^HTTP/[^\r\n]+\r\n([^\r\n]+\r\n)*\r\nHTTP/#ims", $result)) {
            $result = preg_replace("#^HTTP[^\r\n]+\r\n([^\r\n]+\r\n)*\r\nHTTP#ims", 'HTTP', $result);
        }
        $bodyStart = strpos($result, "\r\n\r\n");
        $responseHeaders = "";

        if ($bodyStart !== false) {
            $responseHeaders = substr($result, 0, $bodyStart + 4);
            $result = substr($result, $bodyStart + 4);
        }

        $data = $result;

        if ($contentType === 'application/json') {
            $data = json_decode($result, true);

            if (is_array($data)) {
                $log = json_encode($data, JSON_PRETTY_PRINT);
            }
        }

        if ($contentType === 'application/pdf') {
            $log = 'pdf, ' . strlen($data);
        }

        $logBlockId = bin2hex(random_bytes(10));
        $this->logger->info("<div style='cursor: pointer;' onclick='$(\"#{$logBlockId}\").toggle();'>$url</div>");
        $this->logger->info("<div style='display: none;' id='{$logBlockId}'>request<pre>" . htmlspecialchars(($requestInfo['request_header'] ?? "") . (isset($options[CURLOPT_POSTFIELDS]) ? "\n" . $options[CURLOPT_POSTFIELDS] : "") /*. var_export($options, true)*/) . "</pre>");
        $this->logger->info("response ({$responseCode}, $contentType) <pre>" . htmlspecialchars($responseHeaders . $log) . "</pre></div>");

        return $data;
    }

    private function isSandbox()
    {
        return false;
    }

    private function refreshToken(?string $refreshToken = null)
    {
        $this->http->Log("trying to refresh token");
        $result = $this->curlRequest(
            "/oauth/v1/boa/exchangeToken",
            [
                CURLOPT_HTTPHEADER => [
                    "User-Agent: awardwallet",
                    'Accept: application/json',
                    'X-BOA-Session-ID: ' . $this->sessionId,
                    'X-BOA-Trace-ID: ' . bin2hex(random_bytes(5)),
                ],
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id'     => BANKOFAMERICA_CLIENT_ID,
                    'client_secret' => BANKOFAMERICA_CLIENT_SECRET,
                    'grant_type'    => 'refresh_token',
                    'refresh_token' => $refreshToken ?? $this->State['refresh_token'] ?? $this->Answers['refresh_token'],
                ]),
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD  => BANKOFAMERICA_CLIENT_ID . ":" . BANKOFAMERICA_CLIENT_SECRET,
            ],
            $responseCode,
            false
        );
        $tokenInfo = self::parseTokenInfo($result, $this->isSandbox());

        if (!empty($tokenInfo)) {
            $this->http->Log("saved new access token");
            $this->State['access_token'] = $tokenInfo['access_token'];

            if (
                !empty($tokenInfo['refresh_token'])
                && ($tokenInfo['refresh_token'] !== $this->Answers['refresh_token'] || $tokenInfo['refresh_token'] !== ($this->State['refresh_token'] ?? ''))
            ) {
                $this->http->Log("saved new refresh token");
                $this->State['refresh_token'] = $tokenInfo['refresh_token'];
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
            269    => 'Requested Account isn\'t eligible or doesn\'t exist',
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
            //			201101 => 'The request contains an unrecognized query parameter. The description response property names the parameter.',
            //			201102 => 'The value of the creditCardLastFour query parameter is invalid.',
            201201 => 'The customer’s online profile couldn’t be found.',
            //			201202 => 'The rewardsAccountReferenceId path parameter doesn’t identify a valid rewards account.',
            //			201301 => 'The server that stores rewards account information couldn’t be reached.',
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
     * @param bool $fromAmount - if false - from Points
     */
    private function getMatchingBonuses(array $transactions, array $categories, bool $includeCategories, float $bonusRatio, bool $fromAmount, float $expectedBonus, callable $filter = null): array
    {
        $this->logger->info("matching bonus $bonusRatio " . ($includeCategories ? "to" : "exclude") . " categories: " . implode(", ", $categories));

        foreach ($transactions as $index => &$transaction) {
            if ($fromAmount) {
                $base = $transaction['Amount'];
            } else {
                $base = $transaction['Points'] ?? 0;
            }
            $bonus = round($base * $bonusRatio, 2);
            $transaction['PossibleBonus'] = $bonus;

            $match = false;
            $transaction['Matched'] = false;

            foreach ($categories as $category) {
                if (stripos($transactions[$index]['Category'], $category) === 0) {
                    $match = true;

                    break;
                }
            }

            if ($includeCategories && !$match) {
                continue;
            }

            if (!$includeCategories && $match) {
                continue;
            }

            if ($filter !== null && call_user_func($filter, $transaction) === false) {
                continue;
            }
            $this->logger->info("matched bonus $bonus ($bonusRatio from $base) to {$transaction['Description']} / {$transaction['Category']}");
            $transaction['Matched'] = true;
        }
        unset($transaction);

        return $this->bonuses($this->matchedTransactions($this->correctWithExpectedBonus($transactions, $expectedBonus)));
    }

    private function correctWithExpectedBonus(array $transactions, float $expectedBonus): array
    {
        $diff = ($expectedBonus - $this->possibleBonus($transactions));

        if ($expectedBonus > 0 && abs($diff) / $expectedBonus > 0.01) {
            $transactions = $this->tryToToggleTransactions($transactions, $expectedBonus, $diff > 0);
        }

        $possibleBonus = $this->possibleBonus($transactions);
        $diff = ($expectedBonus - $possibleBonus);

        if ($expectedBonus > 0 && abs($diff) / $expectedBonus > 0.01) {
            $this->logger->warning("failed to match expected bonus. expected: $expectedBonus, actual: $possibleBonus");

            return [];
        }

        return $transactions;
    }

    private function possibleBonus(array $transactions): float
    {
        return array_sum($this->bonuses($this->matchedTransactions($transactions)));
    }

    private function bonuses(array $transactions): array
    {
        return array_map(function (array $tx) { return $tx['PossibleBonus']; }, $transactions);
    }

    private function matchedTransactions(array $transactions): array
    {
        return array_filter($transactions, function (array $tx): bool { return $tx['Matched']; });
    }

    private function calcMerchantBonuses(array $transactions): array
    {
        $merchantBonuses = [];

        foreach ($transactions as $transaction) {
            if (!isset($merchantBonuses[$transaction['Description']])) {
                $merchantBonuses[$transaction['Description']] = 0;
            }
            $merchantBonuses[$transaction['Description']] += $transaction['PossibleBonus'];
        }

        return $merchantBonuses;
    }

    private function applyBonus(array &$transactions, array $bonuses)
    {
        foreach ($bonuses as $index => $bonus) {
            if (!isset($transactions[$index]['Points'])) {
                $transactions[$index]['Points'] = 0;
            }
            $transactions[$index]['Points'] += $bonus;
        }
        $this->logger->info("applied " . round(array_sum($bonuses), 2) . " bonus to " . count($bonuses) . " transactions");
    }

    private function tryToToggleTransactions(array $transactions, float $expectedBonus, bool $add): array
    {
        $this->logger->info("trying to toggle some transactions");
        $availTransactions = array_filter($transactions, function (array $tx) use ($add): bool { return !$tx['Matched'] === $add; });
        $possibleBonus = $this->possibleBonus($transactions);

        $nMax = 2 ** count($availTransactions);

        if ($nMax > (2 ** 18)) {
            return $transactions;
        }

        for ($n = 1; $n < $nMax; $n++) {
            $mask = 1;
            $matches = [];
            $toggledBonus = 0;

            foreach ($availTransactions as $index => $transaction) {
                if ($mask & $n) {
                    $matches[$index] = $transaction;
                    $toggledBonus += $transaction['PossibleBonus'] * ($add ? 1 : -1);
                }
                $mask *= 2;
            }

            if (abs($possibleBonus + $toggledBonus - $expectedBonus) < 0.50) {
                foreach ($matches as $index => $transaction) {
                    $this->logger->info(($add ? "adding" : "removing") . " {$transaction['Description']}, {$transaction['PossibleBonus']} points");
                    $transactions[$index]['Matched'] = $add;
                }

                return $transactions;
            }
        }

        return $transactions;
    }

    private function processRewardsSummaryLines(array $lines, array $transactions, array $purchases, float $purchasesAndAjustments): array
    {
        if (count($purchases) === 0) {
            return $transactions;
        }

        foreach ($lines as &$line) {
            $line = str_ireplace("Make the most out of", "", $line);
            $line = str_ireplace("your rewards program", "", $line);
            $line = str_ireplace("today!", "", $line);
        }

        $lines = array_map('trim', $lines);
        $lines = array_values(array_filter($lines, function (string $line) { return !empty($line); }));

        if (count($lines) === 0) {
            $this->logger->warning('empty rewards summary');

            return $transactions;
        }

        $this->logger->info("line 0: {$lines[0]}");

        // actually not used anymore, left it there for old cards
        if (in_array($lines[0], [
            'ALASKA AIRLINES CREDIT CARD REWARDS',
            'ALASKA AIRLINES BUSINESS CREDIT CARD REWARDS',
        ])) {
            return $this->parseAlaskaAirlinesSummay($lines, $transactions, $purchases, $purchasesAndAjustments);
        }

        $bonusCategories = [];
        $totalBonus = 0;
        $processedLines = 0;
        $parsedLines = 0;

        foreach ($lines as $line) {
            if (preg_match('#^\s*([\d\.\,]+)\s+(.+)$#is', $line, $match)) {
                $points = round(str_replace(",", "", $match[1]), 2);
                $description = $match[2];
                $this->logger->info("reward line: $description: $points");
                $bonuses = null;
                $parsedLines++;

                if (preg_match('#([\d\.]+) Points/\$1 on (.+)$#ims', $description, $matches)) {
                    $bonusRatio = (float) $matches[1];
                    $category = $matches[2];
                    $this->logger->info("category rewards: ($bonusRatio) on {$category}");

                    if ($category === 'Travel&Dining') {
                        $categories = [
                            'Restaurants & Dining: Restaurants/Dining',
                            'Transportation: Public Transportation',
                        ];
                        $bonuses = $this->getMatchingBonuses($purchases, $categories, true, $bonusRatio, true, $points);
                        $bonusCategories = array_merge($bonusCategories, $categories);
                    } elseif ($category === 'All Other') {
                        $bonuses = $this->getMatchingBonuses($purchases, $bonusCategories, false, $bonusRatio, true, $points);
                    } else {
//                        $this->sendNotification("Unknown rewards category // Vladimir", "all", true, $line);

                        return $transactions;
                    }
                    $totalBonus += $points;
                } elseif ($description === 'BASE EARNED THIS MONTH' || $description === 'Base Cash Back Earned' || strtoupper($description) === 'BASE PURCHASE MILES') {
                    $this->logger->debug("purchasesAndAjustments: '{$purchasesAndAjustments}'");

                    if ($points === 0 || $purchasesAndAjustments == 0) {
                        continue;
                    }
                    $baseRatio = round($points / $purchasesAndAjustments, 3);
                    $this->logger->info("base ratio ($points / $purchasesAndAjustments): {$baseRatio}");
                    $bonuses = $this->getMatchingBonuses($purchases, [], false, $baseRatio, true, $points);
                    $totalBonus += $points;
                } elseif (
                    stripos($description, 'Preferred/Banking Rwds Bonu') !== false
                    || stripos($description, 'Preferred Rewards Bonus') !== false
                ) {
                    if ($points === 0 && $totalBonus === 0) {
                        $this->logger->info("empty Preferred/Banking Rwds Bonus");

                        continue;
                    }

                    if ($totalBonus <= 0.1) {
//                        $this->sendNotification("Could not calc preferred ratio // Vladimir", "all", true, $line);
                        $this->logger->warning("bankofamerica: Could not calc preferred ratio: $line");

                        return $transactions;
                    }
                    $preferredRewardsRatio = round($points / $totalBonus, 4);
                    $this->logger->info("preferred rewards ratio ($points / $totalBonus): $preferredRewardsRatio");
                    $bonuses = $this->getMatchingBonuses($purchases, [], false, $preferredRewardsRatio, false, $points);
                } elseif (stripos($description, "REDEEMED") !== false || stripos($description, "bonus this month") !== false) {
                    continue;
                } elseif (stripos($description, 'Category Bonus Earned') !== false) {
                    $this->logger->info("trying to guess category bonus");
                    $bonuses = $this->getMatchingBonuses($purchases, [], false, 0.02, true, $points);
                } elseif (stripos($description, 'Relationship Bonus Earned') !== false) {
                    $this->logger->info("trying to calc Relationship Bonus");
                    $bonuses = $this->getMatchingBonuses($purchases, [], false, 0.75, false, $points);
                } elseif (stripos($description, 'Alaska Purchase Bonus Miles') !== false) {
                    $this->logger->info("trying to calc Alaska Purchase Bonus Miles");
                    // Earn 3 miles for every $1 spent on eligible Alaska Airlines purchases, such as inflight food and beverages.
                    $bonuses = $this->getMatchingBonuses($purchases, ["Travel: Travel"], true, 2, true, $points);
                } elseif (stripos($description, 'Relationship Bonus Miles') !== false) {
                    $this->logger->info("trying to calc Alaska Relationship Bonus Miles");
                    $bonuses = $this->getMatchingBonuses($purchases, [], false, 0.1, false, $points);
                } elseif (stripos($description, 'Miles to Alaska Airlines') !== false) {
                    $this->logger->info("skip Miles to Alaska Airlines, it's a total of the lines above");

                    continue;
                } else {
//                    $this->sendNotification("Unknown rewards line // Vladimir", "all", true, $line);
                    if ($points == 0) {
                        $this->logger->info("could not match reward line, zero points, ignore");

                        continue;
                    }

                    $this->logger->info("could not match reward line, skip points");

                    return $transactions;
                }

                if (empty($bonuses) && $points > 0) {
                    $this->logger->warning("could not match bonuses");

                    return $transactions;
                }

                $this->applyBonus($purchases, $bonuses);

                $processedLines++;
            }
        }

        if ($parsedLines === 0) {
//            $this->sendNotification("could not parse any bonus lines // Vladimir", "all", true, implode("\n", $lines));
            $this->logger->warning("could not parse any bonus lines: " . implode("\n", $lines));

            return $transactions;
        }

        if ($processedLines == 0) {
            $this->logger->info("no processed lines");
//            $this->sendNotification("can't see Reward details // Vladimir", "all", true, implode("\n", $lines));
            return $transactions;
        }

        $this->logger->info("applying " . count($purchases) . " purchases");

        foreach ($purchases as $index => $purchase) {
            if (isset($purchase['Points'])) {
                $transactions[$index]['Points'] = $purchase['Points'];
            }
        }

        return $transactions;
    }

    /**
     * @return array - rewards summary lines
     */
    private function processStatement(array $statement, array &$transactions, int $historyStartDate, string $accountId, ?string $lastFour): array
    {
        $this->logger->info("exploring monthly statement: {$statement['description']}, user id: " . ($this->UserFields['UserID'] ?? '') . ", accountId: " . $accountId . ", lastFour: $lastFour");
        $endDate = strtotime($statement['statementDate']);
        $startDate = strtotime("-1 month +1 day", $endDate);
        $lines = [];

        if ($startDate < $historyStartDate) {
            $this->logger->info("startDate outside history window (" . date("Y-m-d", $historyStartDate) . "), skip: " . date("Y-m-d", $startDate));

            return [];
        }

        $purchases = $this->getPurchases($transactions, $startDate, $endDate);

        // CORP Account - Alaska Airlines - 1935
//        if ($accountId === 'b0d93d11190680fc2dbad828a3ca52169b58c7d3f8f4da520ff545a78d042ae3') {
//            $this->logger->info("getting statement to debug #22922");
//            $data = $this->apiRequest(
//                '/dda/v1/boa/account/statement',
//                [
//                    'accountId'   => $accountId,
//                    'statementId' => $statement['statementId'],
//                ],
//                false,
//                'application/pdf'
//            );
//            $this->http->LogFile(date("Y-m-d", $endDate) . '.pdf', $data);
//        }

        if (count($purchases) > 0) {
            $this->logger->info("getting statement to calc rewards ratio");

            $cacheKey = "bofa_stmt_" . ($this->UserFields['UserID'] ?? '') . '_' . $accountId . '_' . $statement['statementId'];
            $text = \Cache::getInstance()->get($cacheKey);

            if ('202202142022021606190268190910010050' === $statement['statementId']) {
                $text = false;
            }

            $debugAccountIds = [
            ];

            if (empty($text) || in_array($accountId, $debugAccountIds)) {
                $data = $this->apiRequest(
                    '/dda/v1/boa/account/statement',
                    [
                        'accountId'   => $accountId,
                        'statementId' => $statement['statementId'],
                    ],
                    false,
                    'application/pdf'
                );

                $this->http->LogFile(date("Y-m-d", $endDate) . '.pdf', $data);
                $text = \PDF::convertToText($data);
                \Cache::getInstance()->set($cacheKey, $text, 86400 * 30);
            }

            $this->http->LogFile(date("Y-m-d", $endDate) . '.txt', $text);

            if (stripos($text, date("F j", $startDate)) === false && stripos($text, date("F d", $startDate)) === false) {
                $this->logger->info("can't see startDate: " . date("Y-m-d", $startDate) . ", adding day");
                $startDate = strtotime("+1 day", $startDate);

                if (stripos($text, date("F j", $startDate)) === false && stripos($text, date("F d", $startDate)) === false) {
                    $this->logger->info("can't see startDate: " . date("Y-m-d", $startDate) . ", removing day");
                    $startDate = strtotime("-2 day", $startDate);

                    if (stripos($text, date("F j", $startDate)) === false && stripos($text, date("F d", $startDate)) === false) {
                        $this->logger->warning("can't see startDate: " . date("Y-m-d", $startDate) . ", skip");

                        return [];
                    }
                }
            }

            if (stripos($text, date("F j", $endDate)) === false && stripos($text, date("F d", $endDate)) === false) {
                $this->logger->warning("can't see endDate");

                return [];
            }

            $purchasesAndAdjustments = $this->convertToInt($this->extractTotal($text));
            $this->logger->info("purchasesAndAdjustments: " . $purchasesAndAdjustments);

            if ($purchasesAndAdjustments == 0) {
                return [];
            }

            if ($lastFour && !preg_match("#Account Number: {$lastFour}#ims", $text)) {
                $this->logger->warning("can't find card last four: $lastFour");

                return [];
            }

            $text = $this->extractRewardSummary($text);

            if ($text === null) {
                $this->logger->warning("can't see Your Reward Summary");

                return [];
            }

            $lines = explode("\n", $text);
            $transactions = $this->processRewardsSummaryLines($lines, $transactions, $purchases, $purchasesAndAdjustments);
        }

        return $lines;
    }

    private function extractRewardSummary(string $text): ?string
    {
        if (preg_match(
            "#(Your Reward Summary|Alaska Airlines Mileage Plan Rewards Summary)(.+)\n\s*([\d\.\,]+\s+TOTAL(\s+(points|cash back))? AVAILABLE|REVIEW/REDEEM MILES)#ims",
            $text,
            $matches)
        ) {
            $this->logger->info("rewards found by regexp 1");

            return $matches[2];
        }

        if (preg_match(
            "#(Your Reward Summary)((\n *([\d\.\,]+\s+[\w ]+|Make the most of your|rewards program today!|))+)#ims",
            $text,
            $matches)
        ) {
            $this->logger->info("rewards found by regexp 2");

            return $matches[2];
        }

        return null;
    }

    private function loadTransactions(string $accountId, int $historyStartDate, string $currencyCode): array
    {
        $transactions = [];
        $currentDate = $historyStartDate;

        $sendRequest = function () use (&$currentDate, $accountId, &$page) {
            return $this->apiRequest(
                '/dda/v1/boa/account/transactions',
                [
                    'accountId' => $accountId,
                    'page'      => $page,
                    'startTime' => date("Y-m-d", $currentDate) . 'Z',
                    'endTime'   => date("Y-m-d", strtotime("+30 day", $currentDate)) . 'Z',
                ]
            );
        };

        $lastResponse = null;

        // the date range should span no more than ~30 days, otherwise, the
        // BofA side may prune the interval. In the case where you are looking over multiple cycles
        // you would query for transactions in sets.
        // https://redmine.awardwallet.com/issues/19506#note-3
        while ($currentDate < time()) {
            $page = 1;

            do {
                $reduceDateRange = false;

                try {
                    $data = $sendRequest();
                } catch (\CheckException  $e) {
                    $this->logger->error("[Error]: {$e->getMessage()}");

                    if ($e->getMessage() === 'HTTP Error 500' && $historyStartDate < strtotime("-12 month 00:00") && $currentDate === $historyStartDate) {
                        $reduceDateRange = true;
                    } else {
                        throw $e;
                    }
                } catch (InvalidDateRangeException $e) {
                    $this->logger->error("[Error]: {$e->getMessage()}");
                    $reduceDateRange = true;
                }

                if ($reduceDateRange) {
                    $this->logger->info("trying to reduce history range depth");
                    $historyStartDate = strtotime("-12 month 00:00");
                    $currentDate = $historyStartDate;
                    $data = $sendRequest();
                }

                /*
                {
                  "Transactions": {
                    "total": 3,
                    "totalPages": 1,
                    "page": 1,
                    "locTransaction": [
                      {
                        "postedTimestamp": "2018-06-13T12:00:00.000+0000",
                        "accountId": "ed33d71a3784a5fd0bbe465330446fdb030ec497cfa41a2e049ec0a7fd78731f",
                        "transactionTimestamp": "2018-06-13T12:00:00.000+0000",
                        "amount": -94.45,
                        "description": "Online payment from SAV 1 ",
                        "transactionId": "003000056560020180521201806130001946598",
                        "subCategory": "Finance: Bank of America Credit Card Payment",
                        "status": "POSTED",
                        "transactionType": "PAYMENT",
                        "debitCreditMemo": "Credit",
                        "fiAttributes": [
                          {
                            "name": "Payee",
                            "value": "Online payment from SAV 1"
                          },
                          {
                            "name": "merchantName",
                            "value": "Online payment from SAV 1"
                          },
                          {
                            "name": "merchantInformation",
                            "value": null
                          },
                          {
                            "name": "startTime",
                            "value": "2018-06-01Z"
                          },
                          {
                            "name": "endTime",
                            "value": "2018-06-30Z"
                          }
                        ]
                      },
                    ]
                  }
                }
                */
                if (!is_array($data) || !isset($data['Transactions'])) {
                    $this->http->Log("failed to parse transactions");

                    break;
                }
                $data = $data['Transactions'];

                if (isset($data['locTransaction'])) {
                    $response = json_encode($data['locTransaction']);

                    if ($lastResponse === $response) {
                        $this->logger->info("same response, paging bug, break");

                        break;
                    }
                    $lastResponse = $response;

                    foreach ($data['locTransaction'] as $tr) {
                        $row = [
                            'Date'        => strtotime($tr['transactionTimestamp']),
                            'Amount'      => $tr['amount'],
                            'Currency'    => $currencyCode,
                            'Description' => trim($tr['description']),
                            'Category'    => $tr['subCategory'],
                            'Status'      => $tr['status'],
                            'Type'        => $tr['transactionType'],
                            'PostedDate'  => strtotime($tr['postedTimestamp']),
                        ];
                        // I'm not sure how date filters work
                        $transactions[$tr['transactionId']] = $row;
                    }

                    $this->logger->info("loaded " . count($data['locTransaction']) . " transactions from page");
                }

                $page++;
            } while ($page <= $data['totalPages'] && $page < 10);
            $currentDate = strtotime("+29 day", $currentDate);
        }

        usort($transactions, function (array $a, array $b) {
            return $a['Date'] <=> $b['Date'];
        });

        $this->logger->info("loaded total " . count($transactions) . " transactions for card");

        return [$historyStartDate, $transactions];
    }

    private function parseStatements(array $transactions, int $historyStartDate, string $accountId, ?string $lastFour): array
    {
        $periodEnd = time();
        $this->logger->info("parseStatements from " . date("Y-m-d", $historyStartDate) . " for $accountId");
        $firstStatementProcessed = false;

        while ($periodEnd > $historyStartDate) {
            $periodStart = strtotime("-300 day", $periodEnd);

            if ($periodStart < $historyStartDate) {
                $periodStart = $historyStartDate;
            }
            $page = 1;
            $totalPages = 1;
            $lastResponse = null;

            do {
                $data = $this->apiRequest(
                    '/dda/v1/boa/account/statements',
                    [
                        'accountId' => $accountId,
                        'startTime' => date("Y-m-d", $periodStart) . 'Z',
                        'endTime'   => date("Y-m-d", $periodEnd) . 'Z',
                        'page'      => $page,
                    ]
                );

                if (isset($lastResponse) && json_encode($lastResponse) == json_encode($data)) {
                    $this->logger->info("same response as on last request, paging bug, break");

                    break;
                }
                $lastResponse = $data;

                $message = $data['message'] ?? $data['Message'] ?? null;
                $this->logger->info("[Message]: '$message'");

                if ($message === 'Requested Account isn\'t eligible or doesn\'t exist') {
                    break 2;
                }

                if (!isset($data['Statements'])) {
                    throw new \CheckException("No statements", ACCOUNT_ENGINE_ERROR);
                }

                if (isset($data['Statements']['totalPages'])) {
                    $totalPages = $data['Statements']['totalPages'];
                }
                // provider bug fix
                /*
                    right response
                    {
                        "Statements": {
                            "statement": [],
                            "total": 0,
                            "totalPages": 0
                        }
                    }

                    vs

                    broken response
                    {
                        "Statements": {
                            "total": 0,
                            "totalPages": 0
                        }
                    }
                 */
                if ($data == [
                    "Statements" => [
                        "total"      => 0,
                        "totalPages" => 0,
                    ],
                ]
                ) {
                    $this->logger->info("empty response, paging bug, break");

                    break;
                }

                foreach ($data['Statements']['statement'] as $statement) {
                    if (preg_match('#^\w+\s+Statement$#ims', $statement['description'])) {
                        $rewardsSummaryLines = $this->processStatement($statement, $transactions, $historyStartDate, $accountId, $lastFour);

                        if (!$firstStatementProcessed) {
                            $transactions = $this->guessBonusesByLastStatement($transactions, $statement, $rewardsSummaryLines);
                            $firstStatementProcessed = true;
                        }
                    }
                }
                $page++;
            } while ($page <= $totalPages); // pages not working, returning same page on each request
            $periodEnd = $periodStart;
        }

        return $transactions;
    }

    private function guessBonusesByLastStatement(array $transactions, $statement, $rewardsSummaryLines): array
    {
        if (empty($rewardsSummaryLines)) {
            return $transactions;
        }

        $purchases = $this->getPurchases($transactions, strtotime($statement['statementDate']), time());
        $total = round(array_sum(array_map(function (array $tx) { return round($tx['Amount'] * 100); }, $purchases)) / 100, 2);
        $this->logger->info("guessing current bonuses based on last statement");
        $transactions = $this->processRewardsSummaryLines(
            $rewardsSummaryLines,
            $transactions,
            $purchases,
            $total
        );

        return $transactions;
    }

    private function parseAlaskaAirlinesSummay(
        array $lines,
        array $transactions,
        array $purchases,
        float $purchasesAndAjustments
    ): array {
        $this->logger->info("parsing alaska airlines, purchasesAndAjustments: $purchasesAndAjustments");
        /**
         * Rewards Rules:
         * Get 40,000 bonus miles after you make $2,000 or more in purchases within the first 90 days of your account opening.
         * Earn unlimited 3 miles for every $1 spent on eligible Alaska Airlines purchases and unlimited 1 mile for every $1 spent on all other purchases.
         *
         * statement example:
         *  Your Reward Summary
          ALASKA AIRLINES CREDIT CARD REWARDS

                         292     BASE PURCHASE MILES
                                                                                                          Make the most of your
                         584     BONUS AND PROMOTIONAL MILES
                                                                                                         rewards program today!
                         876     MILES TO ALASKA AIRLINES

         *
         * Calculation:
         * 876(3x) = 292(1x) + 584(2x)
         * alaska bonuses = 584/2*3
         */
        $rewards = $this->extractRewardsFromLines($lines);
        $totalMiles = $rewards['MILES TO ALASKA AIRLINES'] ?? 0;

        // 3x on alaska airlines
        if (isset($rewards['BONUS AND PROMOTIONAL MILES'])) {
            $alaskaBonus = $rewards['BONUS AND PROMOTIONAL MILES'];

            // welcome bonus changes over time
            foreach ([60000, 50000, 40000] as $bonus) {
                if ($alaskaBonus >= $bonus && $purchasesAndAjustments < ($bonus / 3)) {
                    $this->logger->info("excluding $bonus welcome bonus");
                    $alaskaBonus -= $bonus;
                    $totalMiles -= $bonus;

                    break;
                }
            }

            $alaskaBonus = round($alaskaBonus / 2 * 3, 2);

            $bonuses = $this->getMatchingBonuses($purchases, ["Travel: Travel"], true, 3, true, $alaskaBonus, function (array $tx) { return stripos($tx['Description'], 'alaska') !== false; });
            $this->applyBonus($purchases, $bonuses);
            $totalMiles -= $alaskaBonus;
        }

        // exclude Promotional Miles, https://redmine.awardwallet.com/issues/22922#note-12
        if (isset($rewards['PROMOTIONAL MILES'])) {
            $totalMiles -= $rewards['PROMOTIONAL MILES'];
        }

        $relationshipBonusRatio = 0;
        // exclude Promotional Miles, https://redmine.awardwallet.com/issues/22922#note-12
        if (isset($rewards['RELATIONSHIP BONUS MILES']) && $totalMiles > 0.01) {
            $relationshipBonusRatio = round($rewards['RELATIONSHIP BONUS MILES'] / $totalMiles, 1);
            $this->logger->info("relationshipBonusRatio: $relationshipBonusRatio");
        }

        // apply 1x to all other purchases
        if ($totalMiles > 0.01) {
            $bonuses = $this->getMatchingBonuses($purchases, [], false, 1 + $relationshipBonusRatio, true,
                $totalMiles, function (array $tx) {
                    return stripos($tx['Description'], 'alaska') === false;
                });
            $this->applyBonus($purchases, $bonuses);
        }

        foreach ($purchases as $index => $purchase) {
            if (isset($purchase['Points'])) {
                $transactions[$index]['Points'] = $purchase['Points'];
            }
        }

        return $transactions;
    }

    private function extractRewardsFromLines(array $lines): array
    {
        $result = [];

        foreach ($lines as $line) {
            if (preg_match('#^\s*([\d\.\,]+)\s+(.+)$#is', $line, $match)) {
                $result[strtoupper($match[2])] = (float) str_replace(',', '', $match[1]);
            }
        }
        $this->logger->info("extracted rewards: " . json_encode($result, JSON_PRETTY_PRINT));

        return $result;
    }

    private function extractTotal($text)
    {
        if (preg_match('#Purchases and Adjustments\s+\$([\d\.\,]+)#ims', $text, $matches)) {
            return $matches[1];
        }

        if (preg_match('#Purchases and Adjustments[\s\.]+\$?([\d\.\,]+)#ims', $text, $matches)) {
            return $matches[1];
        }

        // business card
        if (preg_match('#TOTAL PURCHASES AND OTHER CHARGES FOR THIS PERIOD[\s\.]+\$?([\d\.\,]+)#ims', $text, $matches)) {
            return $matches[1];
        }

        $this->logger->warning("can't see Purchases and Adjustments in: $text");

        return 0;
    }

    private function convertToInt($sum)
    {
        return str_replace(",", "", $sum);
    }

    /**
     * @return array - [?string $hideCardWithId, string $newDisplayName]
     */
    private function correctBusinessCardDisplayName(array $data, array $displayNames): array
    {
        $result = [null, $data['displayName']];

        if (
            ($data['lineOfBusiness'] ?? '') !== 'Business'
            || !isset($data['parentAccountId'])
            || !isset($displayNames[$data['parentAccountId']])
            || $data['parentAccountId'] === $data['accountId']
        ) {
            return $result;
        }

        if (!preg_match('#\b\d\d\d\d\b#', $data['displayName'], $matches)) {
            $this->logger->info("can't find last four in card name: " . $data['displayName']);

            return $result;
        }

        $cardLastFour = $matches[0];

        if (!preg_match('#\b\d\d\d\d\b#', $displayNames[$data['parentAccountId']], $matches)) {
            $this->logger->info("can't find last four in parent card name: " . $displayNames[$data['parentAccountId']]);

            return $result;
        }

        $parentLastFour = $matches[0];
        $this->logger->info("replacing card name {$data['displayName']} with {$displayNames[$data['parentAccountId']]}, replacing {$parentLastFour} with {$cardLastFour}");

        return [$data['parentAccountId'], str_replace($parentLastFour, $cardLastFour, $displayNames[$data['parentAccountId']])];
    }
}
