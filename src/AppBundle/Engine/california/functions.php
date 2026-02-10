<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCalifornia extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla, palm837
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $loginURL = "https://cpkrewards.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://cpkrewards.myguestaccount.com/guest/account-balance";
    public $code = "california";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $selenium = false;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = $this->loginURL;
        $arg["SuccessURL"] = $this->balanceURL;

        return $arg;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && (strstr($properties['SubAccountCode'], "Dollars"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->seleniumAuth()) {
            $this->selenium = true;

            return true;
        }

        $this->http->GetURL($this->balanceURL);
//        $this->challengeCloudflareTurnstile();
        $formXpath = "//form[contains(@class, 'loginForm')]";

        if (!$this->http->ParseForm(null, $formXpath)) {
            if ($this->seleniumAuth()) {
                $this->selenium = true;

                return true;
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue($this->http->FindSingleNode($formXpath . "//input[@id = 'inputUsername']/@name"), $this->AccountFields['Login']);
        $this->http->SetInputValue($this->http->FindSingleNode($formXpath . "//input[@id = 'inputPassword']/@name"), $this->AccountFields['Pass']);
        $this->http->SetInputValue($this->http->FindSingleNode($formXpath . "//button[@id = 'loginFormSubmitButton']/@name"), "");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // gordonb - You are seeing this message because you requested a page that either does not exist or is currently not available.
        if (
            $this->http->FindSingleNode("
                //font[contains(text(), 'Not Available')]
                | //h2[contains(text(), 'Web server is down')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            strstr($this->http->currentUrl(), 'guest/error?')
            && ($message = $this->http->FindSingleNode('//span[contains(@class, "error") and contains(normalize-space(), "There was an error processing your subscription. Please try again.")]'))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Example Account: 2372537
        // You have reached this page because there was an error processing your request.
        // Card template has invalid favorite store group
        if ($this->code == 'cosi') {
            if ($this->http->FindSingleNode("//h2[contains(text(), 'Error Processing Request')]")
                || $this->http->FindPreg('#myguestaccount\.com/guest/error\?#', false, $this->http->currentUrl())) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        if ($this->selenium === false && !$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if (
            strstr($this->http->currentUrl(), 'guest/error?')
            && ($message = $this->http->FindSingleNode('//span[contains(@class, "error") and contains(normalize-space(), "Failed to get the configuration for your card. Please try again.")]'))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        // The username could not be found or the password you entered was incorrect. Please try again.
        if ($message = $this->http->FindSingleNode("//div[contains(@id, '___error')]/ul/li")) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'The username could not be found or the password you entered was incorrect. Please try again.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'There was an error logging in. Please try to login again.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // TODO: oberweis - Migrating Your Account
        if (strstr($this->http->currentUrl(), '.myguestaccount.com/guest/migrate-username')
            && $this->http->FindSingleNode("//h1[contains(text(),'Migrating Your Account')]")
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode("//h2[contains(text(), 'Update Your Login Information')]")) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Your points balance is
        $balance = $this->http->FindSingleNode("//div[div/strong[
                    contains(text(), 'Your points balance is')
                    or contains(text(), 'Currently on your account:')
                    or contains(text(), 'Your rewards balance is:')
                ]
            ]/following-sibling::div//div[
                (
                    contains(text(), 'Points')
                    or contains(text(), 'Total')
                )
                and not(contains(text(), 'Beverage'))
                and not(contains(text(), 'LifeTime Points'))
                and not(contains(text(), 'Lifetime Points'))
                and not(contains(text(), 'Stein Points'))
                and not(contains(text(), 'Yearly'))
                and not(contains(text(), 'Catering Points'))
                and not(contains(text(), 'Double Points'))
                and (not(
                    contains(text(), 'Rewards Points')
                    and not(contains(text(), 'CPK Rewards Points Total'))
                    and not(contains(text(), 's Rewards Points'))
                ))
                and not(contains(text(), 'Yearly Points Earned'))
                and not(contains(text(), 'This Year'))
                and not(contains(text(), 'Points Next Visit'))
            ]", null, true, self::BALANCE_REGEXP_EXTENDED);

        if ($this->code == 'canes') {
            $balance = $this->http->FindSingleNode("//div[div/strong[normalize-space(text()) = 'Visits']]/following-sibling::div//div[contains(text(), ' Visits')]", null, true, self::BALANCE_REGEXP_EXTENDED);
        }

        if ($balance === 'None') {
            $this->SetBalanceNA();
        } else {
            $this->SetBalance($balance);
        }
        $exp = $this->http->FindSingleNode("//div[div/strong[
                    contains(text(), 'Your points balance is')
                    or contains(text(), 'Currently on your account:')
                    or contains(text(), 'Your rewards balance is:')
                ]
            ]/following-sibling::div//div[
                (
                    contains(text(), 'Points')
                    or contains(text(), 'Total')
                )
                and not(contains(text(), 'Beverage'))
                and not(contains(text(), 'LifeTime Points'))
                and not(contains(text(), 'Lifetime Points'))
                and not(contains(text(), 'Stein Points'))
                and not(contains(text(), 'Yearly'))
                and not(contains(text(), 'Catering Points'))
                and not(contains(text(), 'Double Points'))
                and (not(
                    contains(text(), 'Rewards Points')
                    and not(contains(text(), 'CPK Rewards Points Total'))
                    and not(contains(text(), 's Rewards Points'))
                ))
                and not(contains(text(), 'Yearly Points Earned'))
                and not(contains(text(), 'This Year'))
                and not(contains(text(), 'Points Next Visit'))
            ]/parent::div/following-sibling::div[contains(@class, 'pointExpirations')]");

        if ($this->code == 'canes') {
            $exp = $this->http->FindSingleNode("//div[div/strong[normalize-space(text()) = 'Visits']]/following-sibling::div//div[contains(text(), ' Visits')]/parent::div/following-sibling::div[contains(@class, 'pointExpirations')]");
        }

        if (!empty($exp)) {
            $this->sendNotification("{$this->code}. Exp was found");
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@code = 'customerName']")));

        if ($this->code == 'lettuce') {
            // Balance - Total Points
            $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Total Points')]", null, true, "/(.+)Total Points/"));
            // Total Spend YTD
            $this->SetProperty("TotalSpendYTD", $this->http->FindSingleNode("(//div[contains(text(), 'Total Spend YTD')])[1]", null, true, "/\s*(.+)\s+Total Spend YTD/"));
        }

        if ($this->code == 'smashburger') {
            // Balance - Total Points
            $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Total Points')]", null, true, "/(.+)Total Points/"));
            // This Year's Points
            $this->SetProperty("YTDPoints", $this->http->FindSingleNode("(//div[contains(text(), 'This Year')])[1]", null, true, "/\s*(.+)\s+This Year's Point/"));
        }

        if ($this->code == 'papaginos') {
            // Card
            $this->SetProperty("Card", $this->http->FindSingleNode("(//span[@code = 'cardNumberAppend'])[last()]"));
            // Type
            $this->SetProperty("Type", $this->http->FindSingleNode("(//span[@code = 'cardTemplateLabelAppend'])[last()]"));
            // Tier
            $this->SetProperty("Tier", $this->http->FindSingleNode("(//span[@code = 'tierLabelAppend'])[last()]"));
        } else {
            // Card
            $this->SetProperty("Card", $this->http->FindSingleNode("(//span[@code = 'cardNumberAppend'])[1]", null, true, "/\s*:\s*([^<]+)/"));
            // Type
            $this->SetProperty("Type", $this->http->FindSingleNode("//span[@code = 'cardTemplateLabelAppend']", null, true, "/\s*:\s*([^<]+)/"));
            // Tier
            $this->SetProperty("Tier", $this->http->FindSingleNode("//span[@code = 'tierLabelAppend']", null, true, "/\s*:\s*([^<]+)/"));
        }
        // Stored Value
        $this->SetProperty("StoredValue", $this->http->FindSingleNode("//div[strong[contains(text(), 'Stored Value')]]/following-sibling::div"));
        // Charge Dollars
        $this->SetProperty("ChargeDollars", $this->http->FindSingleNode("//div[strong[contains(text(), 'Charge Dollars')]]/following-sibling::div"));
        // LifeTime Points
        $this->SetProperty("LifeTimePoints", $this->http->FindSingleNode("//div[div/strong[contains(text(), 'Your points balance is')]]/following-sibling::div//div[
                contains(text(), 'LifeTime Points')
                or contains(text(), 'Lifetime Points')
            ]", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Status expiration
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode("//div[div[contains(text(), 'Gold Tier Expiration')]]/following-sibling::div[1]", null, true, "/expire\s*on\s*([^<]+)/"));

        if ($this->code == 'burgerville' && !empty($this->Properties['Name']) && !empty($this->Properties['Card']) && !empty($this->Properties['Type']) && isset($this->Properties['StoredValue']) && !empty($this->Properties['Tier'])) {
            $this->SetBalance($this->Properties['StoredValue']);
        }

        // SubAccounts - rewards

        $nodes = $this->http->XPath->query("//div[@class = 'rewardBalance']/div[contains(@class, 'rewardRepeater') and not(contains(., 'Gold Tier Expiration'))]");
        $this->logger->debug("Total {$nodes->length} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $displayName = $this->http->FindSingleNode('div/div[@class = "row"]', $node, true, "/[\d\.\,]+\s*(.+)/");
            $code = str_replace([' ', "'", ',', '/', '$', '"', '%', ':', '+'], '', $displayName);
            $code = str_replace(['é'], ['e'], $code);
            $balance = $this->http->FindSingleNode('div/div[@class = "row"]', $node, true, "/([\d\.\,]+)/");

            $subAccount = [
                'Code'        => $this->code . $code,
                'DisplayName' => $displayName,
                'Balance'     => $balance,
            ];

            $expNodes = $this->http->XPath->query('div/div[contains(@class, "rewardExpirations")]/div', $node);
            $this->logger->debug("[Node #{$i}]: total {$expNodes->length} exp nodes were found");
            unset($exp);

            foreach ($expNodes as $expNode) {
                $date = $this->http->FindPreg("/expire\s*on\s*(.+)/", false, $expNode->nodeValue);
                $value = $this->http->FindPreg("/([\d\.\,]+)\s*expire\s*on/", false, $expNode->nodeValue);
                $this->logger->debug("[Node #{$i}]: $date / $value");

                if (!isset($exp) || strtotime($date) < $exp) {
                    $exp = strtotime($date);
                    $this->logger->debug("[Node #{$i}]: set $date -> $value");
                    $subAccount['ExpirationDate'] = $exp;
                    $subAccount['ExpiringBalance'] = $value;
                }//if (!isset($exp) || strtotime($date) < $exp)
            }// foreach ($expNodes as $expNode)

            $this->AddSubAccount($subAccount);
        }// for ($i = 0; $i < $nodes->length; $i++)

        // Credit / Annual Visits / Visits / Beverage Points / LifeTime Points / Stein Points / Prior Six Month Spend
        $rewardsXpath = "//div[div/strong[
                contains(text(), 'Your points balance is')
                or contains(text(), 'Your rewards balance is:')
                or contains(text(), 'Currently on your account:')
            ]]/following-sibling::div//div[
                contains(text(), 'Credit')
                or contains(text(), 'Visits')
                or contains(text(), 'Beverage')
                or contains(text(), 'Stein Points')
                or contains(text(), 'Prior Six Month Spend')
                or contains(text(), 'Catering Points')
                or contains(text(), 'Double Points')
                or (
                    contains(text(), 'Rewards Points')
                    and not(contains(text(), 'CPK Rewards Points Total'))
                    and not(contains(text(), 's Rewards Points'))
                )
                or contains(text(), 'Yearly Points Earned')
                or contains(text(), 'Points Next Visit')
            ]
        ";
        $otherRewards = $this->http->XPath->query($rewardsXpath);
        $this->logger->debug("Total {$otherRewards->length} other rewards were found");

        foreach ($otherRewards as $i => $otherReward) {
            $balance = $this->http->FindPreg("/([\d\.\,]+)/", false, $otherReward->nodeValue);
            $displayName = $this->http->FindPreg("/[\d\.\,]+\s*(.+)/", false, $otherReward->nodeValue);

            if ($this->code == 'silverdiner' && $displayName == 'Lifetime Visits') {
                $this->SetBalance($balance);

                continue;
            }
            // refs #23829
            if ($this->code == 'qdoba' && $displayName == 'Annual Visits') {
                $this->SetProperty("AnnualVisits", $balance);

                continue;
            }

            if (!empty($balance)) {
                $subAccount = [
                    'Code'        => $this->code . str_replace([' ', "'", ',', '/', '$', '"', '+'], '', $displayName),
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                ];

                $expNodes = $this->http->XPath->query("./parent::div/following-sibling::div[contains(@class, 'pointExpirations')]/div", $otherReward);
                $this->logger->debug("[Node #{$i}]: total {$expNodes->length} exp nodes were found");
                unset($exp);

                foreach ($expNodes as $expNode) {
                    $date = $this->http->FindPreg("/expire\s*on\s*(.+)/", false, $expNode->nodeValue);
                    $value = $this->http->FindPreg("/([\d\.\,]+)\s*expire\s*on/", false, $expNode->nodeValue);
                    $this->logger->debug("[Node #{$i}]: $date / $value");

                    if (!isset($exp) || strtotime($date) < $exp) {
                        $exp = strtotime($date);
                        $this->logger->debug("[Node #{$i}]: set $date -> $value");
                        $subAccount['ExpirationDate'] = $exp;
                        $subAccount['ExpiringBalance'] = $value;
                    }//if (!isset($exp) || strtotime($date) < $exp)
                }// foreach ($expNodes as $expNode)

                $this->AddSubAccount($subAccount);
            }
        }

        // refs #20693
        if ($this->code == 'california') {
            $this->http->GetURL("https://cpkrewards.myguestaccount.com/guest/transaction-history");
            $transactions = $this->http->XPath->query("//div[@class = 'transactions']/div");
            $this->logger->debug("Total {$transactions->length} transactions were found");

            foreach ($transactions as $transaction) {
                $transactionInfo = $this->http->FindSingleNode(".//div[contains(@class, 'transaction-info')]", $transaction);
                $transactionDate = $this->http->FindSingleNode(".//div[contains(@class, 'transaction-date')]", $transaction);

                if (
                    !strstr($transactionInfo, "Campaign Adjustment")
                    && !strstr($transactionInfo, "Accrual / Redemption")
                ) {
                    $this->logger->debug("Skip transaction: {$transactionInfo}");

                    continue;
                }

                $this->SetProperty("LastActivity", $transactionDate);

                $this->SetExpirationDate(strtotime("+12 month", strtotime($transactionDate)));

                break;
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->checkErrors();
        }
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

            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            */
            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL($this->balanceURL);

            $formXpath = "//form[contains(@class, 'loginForm')]";

            $selenium->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //div[contains(@class, \"cf-turnstile-wrapper\")] | " . $formXpath . "//input[@id = 'inputUsername']" . ' | //div[@class="px-captcha-error-message")]'), 20);
            $this->savePageToLogs($selenium);

            $delay = 0;

            if ($this->clickCloudFlareCheckboxByMouse($selenium)) {
                $delay = 10;
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//input[@id = 'inputUsername']"), $delay);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//input[@id = 'inputPassword']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath($formXpath . "//button[@id = 'loginFormSubmitButton']"), 0);

            if ($agreeBtn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'I Agree') or contains(text(), 'OK, I understand')]"), 0)) {
                $agreeBtn->click();
            }

            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);

            // canes
            if (empty($this->AccountFields['Pass'])) {
                throw new CheckException("The username could not be found or the password you entered was incorrect. Please try again.", ACCOUNT_PROVIDER_ERROR);
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //span[@code = 'cardNumberAppend'] | //h1[contains(text(),'Migrating Your Account')] | //span[contains(@class, 'error')] | //div[contains(@id, '___error')]/ul/li | //span[contains(text(), 'Your connection was interrupted')] | //h2[contains(text(), 'Update Your Login Information')]"), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($this->http->FindSingleNode("//span[contains(text(), 'Your connection was interrupted')]")) {
                $retry = true;
            }
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | WebDriverCurlException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
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

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $key = "0x4AAAAAAAAjq6WYeRDKmebM"; //todo

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'turnstile',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            || ($this->code == 'tortilla' && $this->http->FindSingleNode("(//span[@code = 'cardNumberAppend'])[1]"))
            || (in_array($this->code, ['huhot', 'canes']) && $this->http->FindNodes("
                //div[div[strong[contains(text(), 'Your points balance is')]]]
                | //strong[contains(text(), 'Congratulations!')
            ]"))
        ) {
            return true;
        }

        return false;
    }

    private function challengeCloudflareTurnstile()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("challenge-form")) {
            return false;
        }

//        $captcha = $this->parseCaptcha();
//
//        if ($captcha == false) {
//            return false;
//        }
        // TODO

        $this->http->SetInputValue("sh", "32215c567af18ffa6e89009a087a0bc5");
        $this->http->SetInputValue("aw", "FKRaIPimhAuJ-12-79adacbb28157fd5");
        $this->http->SetInputValue("cf_ch_cp_return", '6227eb6b55a278c147b36da2ee7d5fb1|{"managed_clearance":"i"}');
        $this->http->PostForm();

        return true;
    }
}
