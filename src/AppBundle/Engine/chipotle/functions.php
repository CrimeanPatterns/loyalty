<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerChipotle extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://services.chipotle.com/customer/v2/customer';

    private $responseData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/json;charset=UTF-8*');
        $this->http->setDefaultHeader('ADRUM', 'isAjax:true');
        $this->http->setDefaultHeader('Origin', 'https://www.chipotle.com');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Ocp-Apim-Subscription-Key' => 'b4d9f36380184a3788857063bce25d6a',
            'Authorization'             => $this->State['Authorization'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog($this->responseData);

        if (!isset($response->firstName, $response->subscriptions)) {
            return false;
        }

        foreach ($headers as $name => $value) {
            $this->http->setDefaultHeader($name, $value);
        }

        return true;
    }

    public function LoadLoginForm()
    {
        unset($this->State['Authorization']);
        $this->http->removeCookies();
        //$this->http->GetURL('https://order.chipotle.com/account/login');
        $this->http->GetURL('https://www.chipotle.com/');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        return $this->selenium();

        $xsrf = $this->http->getCookieByName('XSRF-TOKEN', 'order.chipotle.com');

        if (!isset($xsrf)) {
            return $this->checkErrors();
        }
        $this->http->setDefaultHeader('X-XSRF-TOKEN', urldecode($xsrf));

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://order.chipotle.com/api/customer/login', json_encode([
            'username' => $this->AccountFields["Login"],
            'password' => $this->AccountFields["Pass"],
            'persist'  => 'true',
        ]));
        $this->http->RetryCount = 2;

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            /*
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            */

            $selenium->useGoogleChrome();

//            $this->usePacFile(false);
            $selenium->useCache();
//            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->GetURL("https://www.chipotle.com/");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
            }

            $headerGreeting = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "sign-in")]'), 10);

            if (!$headerGreeting) {
                $this->savePageToLogs($selenium);

                return false;
            }
            $headerGreeting->click();

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//div[div[contains(text(), "Email")]]/following-sibling::div/input'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//div[div[contains(text(), "Password")]]/following-sibling::div/input'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//div[div[contains(text(), "Sign In")]]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/subscriptionId/g.exec( this.responseText )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');

            // Sign In
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'customer-greeting ')] | //div[contains(@class, 'errors')] | //div[contains(@class, 'verify-mobile-number-text')]//div[contains(. ,'Enter the code sent to your phone number')]"), 10);
            // save page to logs
            try {
                $this->savePageToLogs($selenium);
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            if ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'customer-greeting ')]"), 0)) {
                $this->responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $this->responseData);

                $cmgVuex = $selenium->driver->executeScript("return localStorage.getItem('cmg-vuex');");
                $response = $this->http->JsonLog($cmgVuex, 0);

                if (isset($response->customer->jwt)) {
                    $this->State['Authorization'] = "Bearer {$response->customer->jwt}";
                }
                /*
                $selenium->http->GetURL("https://www.chipotle.com/order/rewards");
                sleep(5);
                $this->savePageToLogs($selenium);
                // Balance
                if (!$this->SetBalance($this->http->FindSingleNode('//div[@class = "points"]', null, true, "/^(\d+)\s*\//"))) {
                    $cmgVuex = $selenium->driver->executeScript("return localStorage.getItem('cmg-vuex');");
                    $response = $this->http->JsonLog($cmgVuex, 0);
                    if (isset($response->customer->jwt)) {
                        $this->http->GetURL('https://services.chipotle.com/loyalty/v2/points', [
                            'Accept' => 'application/json, text/plain, *
                /*',
                            'ADRUM' => null,
                            //'Chipotle-CorrelationId' => 'OrderWeb-d9b54572-354e-4074-b3a2-d853456b0a73',
                            'Ocp-Apim-Subscription-Key' => '48d9abab8af54916b9a94c10ec6f9d3d',
                            'Authorization' => "Bearer {$response->customer->jwt}"
                        ]);
                        $response = $this->http->JsonLog();
                        if (isset($response->currentPointsBalance)) {
                            // Balance
                            $this->SetBalance($response->currentPointsBalance);
                            //  Points Until Next Reward
                            if ($response->rewardThreshold > 0) {
                                $this->SetProperty('PointsUntilNextReward', $response->rewardThreshold - $response->currentPointsBalance);
                            }
                        }
                    }
                } else {
                    // Points Until Next Reward
                    $this->SetProperty('PointsUntilNextReward', $this->http->FindSingleNode('//div[contains(@class, "message-bottom") and contains(text(), "until your next reward")]', null, true, "/(\d+)\s*point/"));
                }

                // Bonuses
                $this->SetProperty("CombineSubAccounts", false);

                $bonuses = $this->http->XPath->query('//div[contains(@class, "bonus-item")]');
                $this->logger->debug("Total {$bonuses->length} bonuses were found");
                foreach ($bonuses as $key => $bonus) {
                    $displayName = $this->http->FindSingleNode('.//div[@class = "title"]', $bonus);
                    $exp = $this->http->FindSingleNode('.//div[contains(text(), "Offer Expires")]', $bonus, true, "/Expires\s*([^<]+)/");
                    $this->AddSubAccount([
                        'Code'           => 'chipotleBonuses'.$key.md5($displayName.$exp),
                        'DisplayName'    => $displayName,
                        'Balance'        => null,
                        'ExpirationDate' => strtotime($exp, false),
                    ]);
                }// foreach ($bonuses as $bonus)

                $rewards = $this->http->XPath->query('//div[contains(@class, "reward-item")]');
                $this->logger->debug("Total {$rewards->length} rewards were found");
                foreach ($rewards as $i => $reward) {
                    $displayName = $this->http->FindSingleNode('.//div[@class = "title"]', $reward);
                    $exp = $this->http->FindSingleNode('.//div[contains(@class, "expiration")]', $reward, true, "/Expires\s*([^<]+)/");
                    $this->AddSubAccount([
                        'Code'           => 'chipotleReward'.$i.md5($displayName.$exp),
                        'DisplayName'    => $displayName,
                        'Balance'        => null,
                        'ExpirationDate' => strtotime($exp, false),
                    ]);
                }// foreach ($rewards as $reward)

                $selenium->http->GetURL("https://www.chipotle.com/order/edit-account");
                $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Member #')]"), 5);
                $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'been a member since')]"), 5);
                $this->savePageToLogs($selenium);
                // Name
                $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(@class, "my-information-desktop")]//div[contains(@class, "full-name")]')));
                // Member
                $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//div[contains(text(), "Member #")]', null, true, "/Member #(.+)/"));
                // Member since
                $this->SetProperty('MemberSince', $this->http->FindSingleNode('//div[contains(text(), "member since")]', null, true, "/member\s*since\s*\'(.+)/"));

                $result = false;

                return $result;
                */
            }
            $question = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'verify-mobile-number-text')]//div[contains(. ,'Enter the code sent to your phone number')] | //div[contains(text(),'To verify your identity, we’ll text a code via SMS to your mobile number')]"), 0);

            if ($question) {
                $this->savePageToLogs($selenium);

                if ($this->parseQuestion($selenium)) {
                    return false;
                }
            }

            try {
                $cookies = $selenium->driver->manage()->getCookies();
            } catch (InvalidArgumentException $e) {
                $this->logger->error("InvalidArgumentException: " . $e->getMessage(), ['HtmlEncode' => true]);
                // "InvalidArgumentException: Cookie name should be non-empty trace" workaround
                $cookies = $selenium->http->driver->browserCommunicator->getCookies();
            }

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $result = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (NoSuchWindowException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            try {
                $selenium->http->cleanup();
            } catch (InvalidArgumentException $e) {
                $this->logger->error("InvalidArgumentException on cleanup: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//img[@alt = "WELL, THIS IS JUST THE PITS. Our website is having some trouble. Sometimes it\'s a quick fix, sometimes it isn\'t. Come back later and hopefully we\'ll be back up."]')) {
            throw new CheckException("Our website is having some trouble. Sometimes it's a quick fix, sometimes it isn't. Come back later and hopefully we'll be back up.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion($selenium)
    {
        $this->logger->notice(__METHOD__);
        $question = $question ?? $this->http->FindSingleNode("//div[contains(@class, 'verify-mobile-number-text')]//div[contains(. ,'Enter the code sent to your phone number')]");

        $btn = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(),'SEND CODE')]"), 0);

        if ($btn) {
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $btn->click();
            $q = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'verify-mobile-number-text')]//div[contains(. ,'Enter the code sent to your phone number')] | //div[contains(text(),'To verify your identity, we’ll text a code via SMS to your mobile number')]"), 5);
            $this->savePageToLogs($selenium);

            if ($q) {
                $question = $q->getText();
            }
        }

        if (!$question) {
            return false;
        }

        $this->Step = 'Question';
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;

        return true;
    }

    public function ProcessStep($step)
    {
        $headers = [
            'Accept'                    => 'application/json, text/plain, */*',
            'Content-Type'              => 'application/json',
            'Ocp-Apim-Subscription-Key' => '48d9abab8af54916b9a94c10ec6f9d3d',
            'Chipotle-CorrelationId'    => 'OrderWeb-53668f48-f4e4-41e9-baf5-74a6a3a9cfe0',
        ];
        $data = [
            'Email' => $this->AccountFields['Login'],
            'Code'  => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://services.chipotle.com/auth/v2/verify/finalize", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        // Invalid answer
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Code has expired.')
                || strstr($message, 'Invalid Parameter.')
            ) {
                $this->AskQuestion($this->Question, 'Please enter a valid verification code', "Question");

                return false;
            }

            return false;
        }

        if (isset($response->jwt)) {
            $this->State['Authorization'] = $response->jwt;
        }

        return $this->loginSuccessful();
    }

    public function Login()
    {
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and not(contains(@class, 'hide')) and not(contains(@class, 'sign-in')) and not(contains(text(), 'We were unable to retrieve your points.'))]")) {
            $this->logger->error($message);

            if (strstr($message, 'Bad username or password')) {
                throw new CheckException('Bad username or password. Please try again.', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'The email or password you entered isn\'t quite right.')
                || strstr($message, 'Looks like an invalid email. Try again?')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Oops! Looks like something went wrong. Please try again later.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        /*
        // Bad username or password. Please try again.
        if ($message = $this->http->FindPreg('/"Message":"Hmm. We’ve run into an issue processing your request. Please try again."/')) {
            throw new CheckException('Bad username or password. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }
        */

        if ($this->http->FindSingleNode("//div[contains(@class, 'customer-greeting ')]")) {
//            return true;
            if ($this->loginSuccessful()) {
                return true;
            }

            $email = str_replace('+', '\+', addslashes($this->AccountFields['Login']));

            $response = $this->http->JsonLog($this->responseData, 0);

            if (
                $this->http->FindPreg('/^\{"country":"[^\"]+",(?:"dateOfBirth":"[^\"]+",|)(?:"customerIdHash":"[^\"]+",|)"email":"' . $email . '","firstName":"[^\"]+"(?:,"gender":"u"|)(?:,"isGuacMode":false|),"isGuest":false,"lastName":"[^\"]+",(?:"lastUsedWalletTokenId":"[^\"]+",|)(?:"locationPreferences":\d,|)"phoneNumber":"\d*"(?:,"zip":"[^\"]+"|)(?:,"isGuacMode":false|)\}$/')
                || $this->http->FindPreg('/^\{"dateOfBirth":"[^\"]+",(?:"customerIdHash":"[^\"]+",|)"email":"' . $email . '","firstName":"[^\"]+"(?:,"isGuacMode":false|),"isGuest":false,"lastName":"[^\"]+","phoneNumber":"\d*"(?:,"zip":"[^\"]+"|)(?:,"isGuacMode":false|)\}$/')
                || $this->http->FindPreg('/^\{"email":"' . str_replace('+', '\+', $email) . '"(?:,"favoriteRestaurants":\[\{"name":"[^\"]+","restaurantNumber":\d+\}\]|),"firstName":"[^\"]+"(?:,"gender":"[^\"]+"|)(?:,"isGuacMode":false|),"isGuest":false,"lastName":"[^\"]+"(?:,"lastUsedWalletTokenId":"[^\"]+",|),"phoneNumber":"[^\"]+"(?:,"preferredFavoriteRestaurantNumber":\d+,"restaurantSelectionPreference":\d+|)(?:,"isGuacMode":false|)\}$/')
                || $this->http->FindPreg('/^\{"email":"' . str_replace('+', '\+', $email) . '"(?:,"favoriteRestaurants":\[\{"name":"[^\"]+","restaurantNumber":\d+\}\]|),"firstName":"[^\"]+"(?:,"gender":"[^\"]+"|)(?:,"isGuacMode":false|),"isGuest":false,"lastName":"[^\"]+"(?:,"lastUsedWalletTokenId":"[^\"]+",|),"phoneNumber":"[^\"]+"(?:,"preferredFavoriteRestaurantNumber":\d+,"restaurantSelectionPreference":\d+|)(?:,"isGuacMode":false|)\}$/')
                || $this->http->FindPreg('/^\{"country":"[^\"]+",(?:"dateOfBirth":"[^\"]+",|)(?:"customerIdHash":"[^\"]+",|)"email":"' . $email . '"(?:,"favoriteRestaurants":\[\{"name":"[^\"]+","restaurantNumber":\d+\}\]|),"firstName":"[^\"]+"(?:,"gender":"[^\"]+"|)(?:,"isGuacMode":false|),"isGuest":false,"lastName":"[^\"]+"(?:,"lastUsedWalletTokenId":"[^\"]+",|)(?:"locationPreferences":\d|),"phoneNumber":"[^\"]+"(?:,"preferredFavoriteRestaurantNumber":\d+,"restaurantSelectionPreference":\d+|)(?:,"preferredFavoriteRestaurantNumber":\d+|)(?:,"zip":"[^\"]+"|)(?:,"isGuacMode":false|)\}$/')
                || $this->http->FindPreg('/^\{(?:"country":"[^\"]+",|)(?:"customerIdHash":"[^\"]+",|)"email":"[^"]+"(?:,"favoriteRestaurants":\[\{"restaurantNumber":\d+,"name":"[^\"]+"\}\]|),"firstName":"[^\"]+"(?:,"gender":"[^\"]+"|)(?:,"isGuacMode":false|),"isGuest":false,"lastName":"[^\"]+"(?:,"lastUsedWalletTokenId":"[^\"]+",|)(?:"locationPreferences":\d|),"phoneNumber":"[^\"]+"(?:,"preferredFavoriteRestaurantNumber":\d+)(?:"restaurantSelectionPreference":\d+,|)(?:,"isGuacMode":false|)\}$/')
                || (
                    isset($response->customerIdHash, $response->email, $response->firstName, $response->lastName, $response->phoneNumber)
                    && !isset($response->subscriptions)
                )
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        /*
        if ($this->parseQuestion($this)) {
            return false;
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog($this->responseData, 0);
        // Name
        $this->SetProperty('Name', beautifulName("{$response->firstName} {$response->lastName}"));

        foreach ($response->subscriptions as $item) {
            if ($item->context == 'loyalty') {
                // Member
                $this->SetProperty('AccountNumber', $item->subscriptionId);
                // member since
                $this->SetProperty('MemberSince', date('Y', strtotime($item->created)));

                break;
            }
        }

        $this->http->GetURL('https://services.chipotle.com/loyalty/v2/points');
        $response = $this->http->JsonLog();

        if (isset($response->currentPointsBalance)) {
            // Balance
            $this->SetBalance($response->currentPointsBalance);
            //  Points Until Next Reward
            if ($response->rewardThreshold > 0) {
                $this->SetProperty('PointsUntilNextReward', $response->rewardThreshold - $response->currentPointsBalance);
            }
        }

        // Bonuses
        $this->SetProperty("CombineSubAccounts", false);

        /*
        $this->http->GetURL('https://services.chipotle.com/loyalty/v2/challenges');
        $response = $this->http->JsonLog();

        if ($response && !is_string($response)) {
            foreach ($response as $item) {
                if (isset($item->challengeId, $item->challengeTitle, $item->endDate)) {
                    $this->AddSubAccount([
                        'Code'           => 'chipotleBonuses' . $item->challengeId,
                        'DisplayName'    => $item->challengeTitle,
                        'Balance'        => null,
                        'ExpirationDate' => strtotime($item->endDate, false),
                    ]);
                }
            }
        }
        */

        $this->http->GetURL('https://services.chipotle.com/promo/v2/customers/promotions');
        $response = $this->http->JsonLog();

        if ($response && !is_string($response)) {
            foreach ($response as $item) {
                if (isset($item->PromotionCode, $item->PromotionName, $item->ExpirationDate, $item->IsValid) && $item->IsValid === true) {
                    $this->AddSubAccount([
                        'Code'           => 'chipotleReward' . $item->PromotionCode,
                        'DisplayName'    => $item->PromotionName,
                        'Balance'        => null,
                        'ExpirationDate' => strtotime($item->ExpirationDate, false),
                    ]);
                }
            }
        }
    }
}
