<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAlgerie extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public $code = "ahplus";

    /* see all frequentflyer.aero parsers */

    private $uniqueHashCode = null;
    private $headers = [
        "Content-Type" => "application/json;charset=utf-8",
        "Accept"       => "application/json, text/plain, */*",
    ];

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (in_array($this->code, [
            'ahplus',
            'caribbeanairlines',
            'umbiumbiclub',
            'dreammiles',
            'oasisclub',
            'kuwait',
        ])) {
//            $this->setProxyGoProxies();

            return $this->selenium();
        }
        $this->http->GetURL("https://{$this->code}.frequentflyer.aero/pub/#/main/not-authenticated/");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->code}.frequentflyer.aero/webapp/authenticate", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // login successful
        if (isset($response->response->id) && $response->response->uniqueHashCode != 'null') {
            $this->uniqueHashCode = $response->response->uniqueHashCode;

            return true;
        }// if (isset($response->response->id) && $response->response->id != 'null')

        if (isset($response->processCode)) {
            switch ($response->processCode) {
                case '0008':
                case '0755':
                case '0004':
                case '0757':
                    throw new CheckException("Your user and/or password are incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);

                    break;

                case '0240':
                    throw new CheckException("Too many attempts... Your account is locked!", ACCOUNT_LOCKOUT);

                    break;

                default:
                    $this->logger->notice("Unknown processCode: {$response->processCode}");
            }
        }// switch ($response->processCode)

        if (isset($this->http->Response['code']) && $this->http->Response['code'] == 204) {
            throw new CheckException("Your user and/or password are incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[@class = "alert-text"]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Your user and/or password are incorrect. Please try again.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function getStatus($tier)
    {
        $this->logger->debug("Tier: {$tier}");

        switch ($tier) {
            case 'BLU':
                $status = 'Djurdjura';

                break;

            case 'SLV':
                $status = 'Chelia';

                break;

            case 'GLD':
                $status = 'Tahat';

                break;
//            case 'EXEC':
//                $status = 'EXECUTIVE GOLD';
//                break;
            default:
                $status = '';
                $this->sendNotification("{$this->AccountFields['ProviderCode']}, New status was found: {$tier}");
        }

        return $status;
    }

    public function getName($response)
    {
        // Name
        return beautifulName(ArrayVal($response, 'nameOnCard', ArrayVal($response, 'name')));
    }

    public function Parse()
    {
        $data = [
            "uniqueHashCode"         => $this->uniqueHashCode,
            "retrieveMemberProfiles" => [
                ["retrieveMemberProfileType" => "ALL"],
            ],
        ];
        $this->http->PostURL("https://{$this->code}.frequentflyer.aero/webapp/retrieveProfile", json_encode($data), $this->headers);
        $response = $this->http->JsonLog(null, 1, true);
        $response = ArrayVal($response, 'response');
        // Member since
        $startDate = preg_replace('/000$/', '', ArrayVal($response, 'startDate'));

        if ($startDate) {
            $this->SetProperty("MemberSince", date('d.m.Y', $startDate));
        }
        // Account Number
        $this->SetProperty("CardNumber", ArrayVal($response, 'idWithSuffix'));
        // Name
        $this->SetProperty("Name", $this->getName($response));

        $memberCard = ArrayVal($response, 'memberCard');
        // Balance - Award Miles
        $this->SetBalance(ArrayVal($memberCard, 'awardMiles'));
        // Tier Miles
        $this->SetProperty("TierMiles", ArrayVal($memberCard, 'qualifyingMiles'));
        // Tier Sector
        $this->SetProperty("Sectors", ArrayVal($memberCard, 'qualifyingSectors'));
        // Status expiration - Expiring
        $validUntil = preg_replace('/000$/', '', ArrayVal($memberCard, 'validUntil'));

        if ($validUntil && $validUntil > time() && $validUntil < 4110932061) {
            $this->SetProperty("StatusExpiration", date('d.m.Y', $validUntil));
        }
        $tier = ArrayVal($memberCard, 'tier');
        $this->SetProperty("CurrentTier", $this->getStatus($tier));
        // You are only ... tier points left from NEXT Level!
        $this->SetProperty("MilesToNextLevel", $this->http->FindPreg("/\"value\":\"(\d+)\",\"key\":\"req_points_for_next_tier\"/"));
        // Since enrollment
        $this->SetProperty("SinceEnrollment", ArrayVal($memberCard, 'totalAwardMilesSinceEnrollment'));

        // Expiring Miles
        $data = [
            "uniqueHashCode"    => $this->uniqueHashCode,
        ];
        $this->http->PostURL("https://{$this->code}.frequentflyer.aero/webapp/retrieveExpiringTierPoints", json_encode($data), $this->headers);
        $response = $this->http->JsonLog();

        if (isset($response->response->tierPoints)) {
            foreach ($response->response->tierPoints as $row) {
                $expirePoints = $row->expirePoints;
                $expireDate = intval(preg_replace('/000$/', '', $row->expireYear));

                if (
                    (!isset($exp) || $expireDate < $exp)
                    // refs #24111
                    && $expirePoints > 0
                ) {
                    // Expiration Date
                    $exp = $expireDate;
                    $this->SetExpirationDate($exp);
                    // Miles Expiring
                    $this->SetProperty("ExpiringBalance", $expirePoints);
                }// if (!isset($exp) || $expireDate < $exp)
            }
        }// foreach ($response->response->tierPoints as $row)

        // Vouchers
        $data = [
            "language"          => "en",
            "uniqueHashCode"    => $this->uniqueHashCode,
            "voucherType"       => "",
        ];
        $this->http->PostURL("https://{$this->code}.frequentflyer.aero/webapp/voucherListByMember", json_encode($data), $this->headers);

        if (!$this->http->FindPreg("/\"vouchers\":null/")) {
            $response = $this->http->JsonLog(null, true, 3);
            $vouchers = ArrayVal(ArrayVal($response, 'response'), 'vouchers', []);

            foreach ($vouchers as $voucher) {
                $displayName = ArrayVal($voucher, 'definition', null);
                $expireDate = ArrayVal($voucher, 'expireDate', null);
                $barcode = ArrayVal($voucher, 'barcode', null);
                $status = ArrayVal($voucher, 'status');

                if (strtolower($status) != 'used' && $displayName && $barcode && $expireDate) {
                    $this->AddSubAccount([
                        "Code"           => "{$this->AccountFields['ProviderCode']}Voucher{$barcode}",
                        "DisplayName"    => $displayName,
                        "Balance"        => null,
                        'VoucherNumber'  => $barcode,
                        'ExpirationDate' => intval(preg_replace("/000$/", "", $expireDate)),
                    ]);
                }
            }
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

//            $selenium->useChromePuppeteer();// TODO: now roking now
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
//            $selenium->seleniumOptions->userAgent = null;

            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL("https://{$this->code}.frequentflyer.aero/pub/#/main/not-authenticated/");
            $loginInput = $selenium->waitForElement(WebDriverBy::id("username"));
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginButton"]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                if ($this->http->FindSingleNode('//*[self::h1 or self::span][contains(text(), "This site can’t be reached")]')) {
                    $retry = true;
                }

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/uniqueHashCode/g.exec( this.responseText )) {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');
            $button->click();
            sleep(4);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);

                return true;
            }
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return true;
    }
}
