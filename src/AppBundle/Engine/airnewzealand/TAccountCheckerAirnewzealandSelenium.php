<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirnewzealandSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const WAIT_TIMEOUT = 10;

    public $regionOptions = [
        ""                => "Select your region",
        "Australia"       => "Australia",
        "Canada"          => "Canada",
        "China"           => "China",
        "HongKong"        => "Hong Kong",
        "Japan"           => "Japan",
        "NewZealand"      => "New Zealand & Continental Europe",
        "PacificIslands"  => "Pacific Islands",
        "UK"              => "United Kingdom & Republic of Ireland",
        "USA"             => "United States",
    ];

    /**
     * @var HttpBrowser
     */
    private $curlDrive;

    private $accountSummary;

    private $seemsNoIts = false;

    private $host = 'www.airnewzealand.co.nz';

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://{$this->host}/airpoints-account/airpoints/member/dashboard", [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setRegionSettings();
        $this->setProxyGoProxies();
        $this->UseSelenium();
        $this->useFirefoxPlaywright();

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
        $this->seleniumOptions->recordRequests = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://{$this->host}/airpoints-account/airpoints/member/dashboard");
        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="signInName"]'), self::WAIT_TIMEOUT);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@id="next"]'), 0);
        $this->saveResponse();

        if ((!$login || !$submit) && !$this->http->FindPreg('/Website Temporarily Unavailable/')) {
            throw new CheckRetryNeededException(3, 0);
        }

        if (!$login || !$submit) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//input[@id="verificationCode"] | //div[contains(@class, "error") and @role="alert" and not(@style="display: none;")] | //a[contains(text(), "Use another authentication method")] | //a[contains(text(), "Your Airpoints")]'), self::WAIT_TIMEOUT * 2);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "error") and @role="alert" and not(@style="display: none;")]')) {
            $this->logger->error($message);

            if (strstr($message, "Airpoints™ number / username doesn't match our records.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return $this->checkErrors();
        }

        if ($useAnotherAuthenticationMethod = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Use another authentication method")]'), 0)) {
            $this->saveResponse();

            if (!$this->http->FindSingleNode('//p[contains(text(), "An authentication code was sent to") and contains(text(), "@")]')) {
                $useAnotherAuthenticationMethod->click();

                $emailOtpOption = $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Email OTP")]'), self::WAIT_TIMEOUT);
                $this->saveResponse();
            }

            if (!empty($emailOtpOption)) {
                $emailOtpOption->click();
            }
        }

        if ($this->processQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        return $this->processQuestion();
    }

    public function Parse()
    {
        $this->http->GetURL("https://{$this->host}/identity/my-bookings");
        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "No bookings, yet.")] | //p[contains(text(), "Booking reference")]'), self::WAIT_TIMEOUT * 3);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//p[contains(text(), "No bookings, yet.")]')) {
            $this->seemsNoIts = true;
        }

        $this->State['token'] = $this->driver->executeScript('return localStorage.getItem("token");');
        $this->State['idpToken'] = $this->driver->executeScript('return localStorage.getItem("idpToken");');
        $this->State['homeAccountToken'] = $this->driver->executeScript('
            const findTokenObject = (items) => {
                for (const key of Object.keys(items)) {
                    if (key.indexOf("accesstoken") !== -1) {
                        return key;
                    }
                }
            }

            const getToken = () => {
                const items = { ...localStorage };
                const tokenObject = findTokenObject(items);
                if (tokenObject) {
                    return items[tokenObject];
                }
            }

            return JSON.parse(getToken()).secret;
        ');

        $this->logger->debug('token: ' . $this->State['token']);
        $this->logger->debug('idpToken: ' . $this->State['idpToken']);
        $this->logger->debug('homeAccountToken: ' . $this->State['homeAccountToken']);

        $this->openCurlDrive();
        $this->copySeleniumCookies($this, $this->curlDrive);

        $headers = [
            'Authorization' => 'Bearer ' . $this->State['token'],
            'idptoken'      => $this->State['idpToken'],
            'Accept'        => 'application/json',
        ];

        $this->curlDrive->RetryCount = 0;

        $this->curlDrive->GetURL('https://www.airnewzealand.co.nz/airpoints-account/api/authservice/social-login/user/me', $headers);

        $me = $this->curlDrive->JsonLog();

        $headers = [
            'Authorization' => 'Bearer ' . $this->State['homeAccountToken'],
            'Accept'        => 'application/json',
        ];

        $this->curlDrive->GetURL('https://api.airnz.io/api/v1/airpoints/my/summary', $headers);

        $pointsSummary = $this->curlDrive->JsonLog();

        $this->getRecordedRequests();

        $accountSummary = $this->http->JsonLog($this->accountSummary);

        if (!isset($accountSummary)) {
            $data = [
                'object' => [
                    'companyCode'         => 'NZ',
                    'programCode'         => 'AP',
                    'membershipNumber'    => $me->userId,
                    'isBonusRequired'     => 'true',
                    'tierOptionsRequired' => true,
                    'creditBalance'       => 'Y',
                ],
            ];

            $headers = [
                'Authorization' => 'Bearer ' . $this->State['token'],
                'idptoken'      => $this->State['idpToken'],
                'Accept'        => 'application/json, text/plain, */*',
                'Content-Type'  => 'application/json',
            ];

            $this->curlDrive->PostURL('https://www.airnewzealand.co.nz/airpoints-account/api/member-service/impl/member/v1/account-summary', json_encode($data), $headers);
            $accountSummary = $this->curlDrive->JsonLog();
        }

        if (!isset($accountSummary)) {
            $this->sendNotification('refs #24353 airnewzeland - need to check accountSummary // IZ');

            throw new CheckRetryNeededException(3, 0);
        }

        // Name
        $this->SetProperty('Name', $me->name);
        // Airpoints no.
        $this->SetProperty('Number', $me->userId);
        // Status
        $this->SetProperty('Status', $accountSummary->object->tierName);

        $this->SetBalance($pointsSummary->membershipBalance[0]->balance);
        // Airpoints Advance
        $this->SetProperty('Advance', $pointsSummary->membershipBalance[0]->advanceAmount);
        // Available balance
        $this->SetProperty('Available', $pointsSummary->membershipBalance[0]->availableBalance);

        $expDateData = $accountSummary->object->expiryDetails ?? [];
        $expDateFiltered = [];

        foreach ($expDateData as $expDateItem) {
            if ($expDateItem->pointType !== 'APDNZ') {
                continue;
            }
            $expDateFiltered[] = $expDateItem;
        }

        uasort($expDateFiltered, function ($a, $b) {
            $dateA = strtotime($a->expiryDate);
            $dateB = strtotime($b->expiryDate);

            if ($dateA == $dateB) {
                return 0;
            }

            return ($dateA < $dateB) ? -1 : 1;
        });

        $rightExpDateItem = current($expDateFiltered);

        if ($rightExpDateItem) {
            // Expiration Date
            $this->SetExpirationDate(strtotime($rightExpDateItem->expiryDate));
            // Airpoints Dollar Expiry
            $this->SetProperty('ExpiringBalance', $rightExpDateItem->points);
        }

        $tierOptions = $accountSummary->object->tierOptions ?? [];

        foreach ($tierOptions as $tierOption) {
            $options = $tierOption->options ?? [];

            if (count($options) != 1) {
                $this->sendNotification('refs #24353 airnewzeland - need to check tierOptions // IZ');

                continue;
            }

            $optionDetails = $options[0]->optionDetails ?? [];

            if (count($optionDetails) != 2) {
                $this->sendNotification('refs #24353 airnewzeland - need to check optionDetails // IZ');

                continue;
            }

            foreach ($optionDetails as $optionDetail) {
                if ($optionDetail->name == "Status Points" && $tierOption->type == 'upgrade') {
                    // Status Points Needed to Upgrade Status
                    $this->SetProperty('UpgradeNeeded', $optionDetail->next);
                    // Status Points Earned to Upgrade Status
                    $this->SetProperty('UpgradeEarned', $optionDetail->current);
                }

                if ($optionDetail->name == "Status Points" && $tierOption->type == 'retain') {
                    // Status Points Needed to Upgrade Status
                    $this->SetProperty('RetainNeeded', $optionDetail->next);
                    // Status Points Earned to Upgrade Status
                    $this->SetProperty('RetainEarned', $optionDetail->current);
                }
            }
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            'Authorization' => 'Bearer ' . $this->State['homeAccountToken'],
            'Accept'        => 'application/json',
        ];

        $this->curlDrive->GetURL('https://www.airnewzealand.co.nz/identity/api/my-bookings-web/v1/customers/my/bookings', $headers);

        $bookingsData = $this->curlDrive->JsonLog();

        $bookings = $bookingsData->bookings ?? [];

        if (count($bookings) > 0) {
            $this->sendNotification('refs #24353 airnewzeland - need to check bookings // IZ');

            $this->http->GetURL("https://{$this->host}/identity/my-bookings");
            $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "No bookings, yet.")] | //p[contains(text(), "Booking reference")]'), self::WAIT_TIMEOUT);
            $this->saveResponse();

            $this->waitForElement(WebDriverBy::xpath("//input[contains(@value, \"{$bookings[0]->bookingReference}\")]/../button[contains(text(), \"Manage booking\")]"), self::WAIT_TIMEOUT)->click();

            sleep(self::WAIT_TIMEOUT);

            $this->saveResponse();
        }

        /*
        foreach($bookings as $booking) {
            $this->http->GetURL("https://{$this->host}/identity/my-bookings");
            $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "No bookings, yet.")] | //p[contains(text(), "Booking reference")]'), self::WAIT_TIMEOUT);
            $this->saveResponse();

            $this->waitForElement(WebDriverBy::xpath("//input[contains(@value, \"{$booking->bookingReference}\")]/../button[contains(text(), \"Manage booking\")]"), self::WAIT_TIMEOUT)->click();
        }
        */
    }

    private function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        $input = $this->waitForElement(WebDriverBy::xpath('//input[@id="verificationCode"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();
        $question = $this->http->FindSingleNode('//p[contains(text(), "An authentication code was sent to")]');

        if (!$question || !$input) {
            $this->logger->debug("question data not found");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $input->clear();
        $input->sendKeys($answer);

        $this->logger->debug("Submit question");
        $this->waitForElement(WebDriverBy::xpath('//p[@id="TechnicalProblemsMsg"] | //span[contains(text(), "Code is invalid or has expired")] | //a[contains(text(), "Your Airpoints")] | //strong[@class="name"]'), self::WAIT_TIMEOUT * 2);
        $this->saveResponse();

        if ($error = $this->http->FindPreg('/Code is invalid or has expired/')) {
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");

            return false;
        }

        if ($this->http->FindSingleNode('//a[contains(text(), "Your Airpoints")] | //strong[@class="name"]')) {
            return true;
        }

        if ($this->IsLoggedIn()) {
            return true;
        }

        return $this->checkErrors();
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg('/Website Temporarily Unavailable/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[@id="TechnicalProblemsMsg"]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function copySeleniumCookies($selenium, $curl)
    {
        $this->logger->notice(__METHOD__);

        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function openCurlDrive()
    {
        $this->logger->notice(__METHOD__);
        $this->curlDrive = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->curlDrive);
    }

    private function getRecordedRequests()
    {
        $this->logger->notice(__METHOD__);

        try {
            $requests = $this->http->driver->browserCommunicator->getRecordedRequests();
        } catch (AwardWallet\Common\Selenium\BrowserCommunicatorException $e) {
            $this->logger->error("BrowserCommunicatorException: " . $e->getMessage(), ['HtmlEncode' => true]);

            $requests = [];
        }

        foreach ($requests as $xhr) {
            if (strstr($xhr->request->getUri(), 'account-summary') && !isset($this->accountSummary)) {
                $this->accountSummary = json_encode($xhr->response->getBody());
                $this->logger->debug('Catched account summary request');
            }
        }// foreach ($requests as $xhr)
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'NewZealand';
        }

        return $region;
    }

    private function setRegionSettings()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2'] ?? null);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        // Identification host
        if (!empty($this->AccountFields['Login2'])) {
            // http://www.airnewzealand.eu/gateway
            switch ($this->AccountFields['Login2']) {
                case 'Australia':
                    $this->host = 'www.airnewzealand.com.au';

                    break;

                case 'Canada':
                    $this->host = 'www.airnewzealand.ca';

                    break;

                case 'China':
                    $this->host = 'www.airnewzealand.com.cn';

                    break;

                case 'HongKong':
                    $this->host = 'www.airnewzealand.com.hk';

                    break;

                case 'Japan':
                    $this->host = 'www.airnewzealand.co.jp';

                    break;

                case 'PacificIslands':
                    $this->host = 'www.pacificislands.airnewzealand.com';

                    break;

                case 'UK':
                    $this->host = 'www.airnewzealand.co.uk';

                    break;

                case 'USA':
                    $this->host = 'www.airnewzealand.com';

                    break;

                default:
                    $this->host = 'www.airnewzealand.co.nz';
            }

            if (isset($this->State['Host'])) {
                $this->logger->notice('Get Region from State (Login2: ' . $this->AccountFields['Login2'] . ') => ' . $this->State['Host']);
                $this->host = $this->State['Host'];
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $nameItemXpath = '//strong[@class="name"]';
        $this->waitForElement(WebDriverBy::xpath($nameItemXpath), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->http->FindSingleNode($nameItemXpath)) {
            return true;
        }

        return false;
    }
}
