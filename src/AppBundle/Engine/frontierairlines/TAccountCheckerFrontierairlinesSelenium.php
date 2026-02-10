<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFrontierairlinesSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const JSON_REREXP = '/<(?:div id="json"|pre.*?)>(.+?)<\/(?:div|pre)>/';

    private const XPATH_LOGIN_LINK = "//div[contains(@class,'user-not-logged-in')]/ancestor::div[1]";

    public $selenium = true;
    private $resp;
    private $error = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        /*
        $this->useFirefoxPlaywright();
        $this->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->setProxyGoProxies();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        $request = AwardWallet\Common\Selenium\FingerprintRequest::firefox();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

        $this->setProxyMount();

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }
        */

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $loginSuccessful = false;

        try {
            $loginSuccessful = $this->loginSuccessful(false);
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: {$e->getMessage()}");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: {$e->getMessage()}");

            throw new CheckRetryNeededException(3, 0);
        }
        $this->http->RetryCount = 2;

        if ($loginSuccessful === true) {
            return true;
        }

        return false;
    }

    public function delay($max = 10)
    {
        $delay = rand(1, $max);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    public function LoadLoginForm()
    {
        try {
            try {
                $this->http->GetURL("https://www.flyfrontier.com/about-us/", [], 20);
            } catch (
                Facebook\WebDriver\Exception\UnrecognizedExceptionException
                | Facebook\WebDriver\Exception\WebDriverException $e
            ) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }

            $this->saveResponse();

            if (strlen($this->http->Response['body']) < 13) {
                $this->http->GetURL("https://www.flyfrontier.com/about-us/");
            }

            if ($this->http->FindPreg('/id="distilIdentificationBlock"/')) {
                $this->http->GetURL("https://www.flyfrontier.com/about-us/");
            }

            // for catching errors
            if ($this->AccountFields['Login'] == '40001161104') {
                $this->http->GetURL("https://booking.flyfrontier.com/flight/internal?_ga=1.83295251.483811173.1425974788");
            }

            $this->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
//            $acceptCookies = $this->waitForElement(WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler']"), 10);
//            if ($acceptCookies) {
//                $acceptCookies->click();
//            }

            $resultXpath = self::XPATH_LOGIN_LINK . "
                | //p[contains(text(), 'To protect this website, we cannot process your request right now.')]
                | //p[contains(text(), 're not a robot, please complete the CAPTCHA to continue your journey.')]
                | //h2[contains(text(), '403 Forbidden')]
            ";
            $this->waitForElement(WebDriverBy::xpath($resultXpath), 10);
            $this->saveResponse();

            // request has been blocked
            if ($this->http->FindSingleNode("
                    //p[contains(text(), 'To protect this website, we cannot process your request right now.')]
                    | //h2[contains(text(), '403 Forbidden')]
            ")) {
                $this->markProxyAsInvalid();
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = "ip blocked";

                throw new CheckRetryNeededException(3, 0);
            }

            $this->captchaRecognizing();

            $popup = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_LINK), 0);
            $this->saveResponse();

            if (!$popup) {
                try {
                    $this->http->GetURL("https://www.flyfrontier.com/groups/");
                } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
                $this->waitForElement(WebDriverBy::xpath($resultXpath), 10);
                $this->saveResponse();

                $this->captchaRecognizing();

                $popup = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_LINK), 0);

                if (!$popup) {
                    if (
                        $this->http->FindPreg("/^\s*<pre><\/pre>\s*$/")
                        || (!$this->http->FindPreg("/log in \|/") && $this->http->FindPreg("/<h1 class=\"text--uppercase color--primary\">GROUP TRAVEL<\/h1>/"))
                        || $this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]")
                        || $this->http->FindSingleNode("//div[@class = 'main-content']/h2[contains(text(), '403 Forbidden')]")
                        || $this->http->FindSingleNode("
                                //p[contains(text(), 'To protect this website, we cannot process your request right now.')]
                                | //h2[contains(text(), '403 Forbidden')]
                            ")
                    ) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(3);
                    }
                    // it helps
                    if (
                        $this->http->FindSingleNode('//div[@id="f9SubHeader" and @style="display:none"]')
                    ) {
                        throw new CheckRetryNeededException(3);
                    }

                    return $this->checkErrors();
                }
            }

            $this->driver->executeScript('var overlay = document.getElementById(\'onetrust-consent-sdk\'); if (overlay) overlay.style.display = "none";');
            $this->driver->executeScript('var overlayPopup = document.querySelector(\'div#wisepops-root\'); if (overlayPopup) overlayPopup.remove();');

            $this->saveResponse();
            $popup->click();

            $loginInput = $this->waitForElement(WebDriverBy::xpath("//div[@class='slider-container slider-visible']//input[@name='email']"), 5);
            $passwordInput = $this->waitForElement(WebDriverBy::xpath("//div[@class='slider-container slider-visible']//input[@name='password']"), 0);
            $button = $this->waitForElement(WebDriverBy::xpath("//div[@class='slider-container slider-visible']//div[@name='submit']"), 0);
            $this->http->saveResponse();

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'LOG OUT')]"), 0)) {
                    return true;
                }

                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $button->click();
        } catch (WebDriverCurlException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());

            if (strstr($e->getMessage(), 'Timeout')) {
                throw new CheckRetryNeededException(3);
            }
        } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());

            if (strstr($e->getMessage(), 'Reached error page: about:neterror')) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're currently experiencing server issues. Please try again later.
        if ($this->waitForElement(WebDriverBy::xpath("//strong[contains(text(),'re currently experiencing server issues.')]"), 7)) {
            throw new CheckRetryNeededException(3);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "System Maintenance")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $sleep = 70; //todo: slowly site, temporarily measure
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            // success
            if (
                $this->waitForElement(WebDriverBy::xpath("//span[@class = 'first-name' and normalize-space(text()) != '']"), 0)
                // AccountID: 4079259, 4935918
                || $this->waitForElement(WebDriverBy::xpath("//span[@class = 'first-name']/following-sibling::span[@class = 'hide-sm' and not(contains(text(), '|  mi.'))]"), 0)
            ) {
                return $this->loginSuccessful();
            }
            $this->saveResponse();

            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class,'error-message') and contains(text(),'Error: Your login information is incorrect. Please check your details and try again or')]"), 0)) {
                $error = $message->getText();
                $this->logger->notice("[Attention! error on login form]: {$error}");

                // provider bug fix
                try {
                    $this->http->GetURL("https://www.flyfrontier.com/about-us/");
                } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                    $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(2, 5);
                } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                    $this->logger->error("UnknownErrorException: " . $e->getMessage());
                }
                $popup = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_LINK), 10);
                $this->saveResponse();

                if ($popup) {
                    $popup->click();

                    if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'LOG OUT')]"), 0)) {
                        return $this->loginSuccessful();
                    }
                }

//                $this->DebugInfo = $message;
//
//                return false;

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            // AccountID: 5205397
            if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Instructions on how to reset your password')]"), 0)) {
                throw new CheckException("Your login information is incorrect. Please reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->saveResponse();
        }// while ((time() - $startTime) < $sleep)

        if (in_array($this->AccountFields['Login'], [
            '90091632769',
            'United777guy@gmail.com',
            '90093671549',
            'deborah031664@yahoo.com',
            '90094734186',
            'blueiguy@gmail.com',
            '40001161104',
            '90091381524',
            '90091113204',
            '90095016692',
            'henash@gmail.com',
            'vivekramachandrann@gmail.com',
            'sherlockfem@hotmail.com',
            'daniel@browns.life',
            'samshik88@yahoo.com',
            'kmengelbach@ucdavis.edu',
            '90097151054',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        throw new CheckRetryNeededException(2, 10);

        return false;
    }

    public function Parse()
    {
        // Balance - MY MILES:
        if (isset($this->resp->totalMiles)) {
            $this->SetBalance($this->resp->totalMiles);
        }
        // Name
        if (isset($this->resp->name)) {
            $this->SetProperty('Name', beautifulName($this->resp->name));
        }
        // Level - MY STATUS:
        if (isset($this->resp->statusName)) {
            $this->SetProperty('Level', $this->resp->statusName);
        }

        // Number - Member #
        $this->SetProperty('Number', $this->resp->number);
        $this->delay(3);

        try {
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Customer');
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (WebDriverCurlException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }
        $this->delay(5);
        $response = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));

        if (empty($response)) {
            $this->delay(3);
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Customer');
            $response = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));
            /*if (empty($response)) {
                throw new CheckRetryNeededException(2);
            }*/
        }

        // NextLevel - Miles to Next Level
        if (isset($response->myMiles->milesToNextStatus)) {
            $this->SetProperty('MilesNextLevel', $response->myMiles->milesToNextStatus);
        }
        // StatusMiles - total status miles:
        if (isset($response->statusQualifyingMiles)) {
            $this->SetProperty('StatusMiles', $response->statusQualifyingMiles);
        }
        // StatusExpiration - expiration:
        if (isset($response->statusExpiration)) {
            $this->SetProperty('StatusExpiration', $response->statusExpiration);
        }

        /*
        // refs#17281 Expiration Date
        $this->http->GetURL("https://booking.flyfrontier.com/F9Sessionless/AccountTransactions");
        $transactions = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));
        if (!empty($transactions)) {
            $this->logger->debug("Total " . count($transactions) . " nodes were found");
            foreach ($transactions as $item) {
                if (stripos($item->description, 'Standard Accrual') !== false && $item->redeemableMiles > 0 && strtotime($item->date, false)) {
                    // Last Activity
                    $this->SetProperty("LastActivity", $item->date);
                    // Exp date
                    $this->SetExpirationDate(strtotime("+ 6 month", strtotime($item->date, false)));
                    break;
                }
            }
        }
        */

        try {
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Aggregate');
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (
            UnknownServerException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
        }
        $this->delay(5);
        $this->waitForElement(WebDriverBy::xpath("//div[@id='json'] | //pre[not(@id)]"), 3);
        $response = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));
        // Expiration Date   // refs #17281
        if (isset($response->milesExpiration)) {
            if ($response->milesExpiration == 'N/A') {
                $this->ClearExpirationDate();
            } elseif ($exp = strtotime($response->milesExpiration)) {
                $this->SetExpirationDate($exp);
            }
        }

        // Family Pooling
        if (isset($response->loyaltyPool->isHead, $response->loyaltyPool->poolMiles) && $response->loyaltyPool->isHead === true) {
            $this->SetProperty('TotalPoolMiles', $response->loyaltyPool->poolMiles);
        }

        // Discount Den
        if (isset($response->discountDenData->isDiscountDen) && $response->discountDenData->isDiscountDen === true) {
            if (isset($response->discountDenData->memberSince)) {
                $this->SetProperty('MemberSince', $response->discountDenData->memberSince);
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (in_array($this->AccountFields['Login'], ['melissalaycock@gmail.com', '90091632769', '90092523549', '90092934910', '90092768092'])
                && isset($this->Properties['Number']) && $this->Properties['Number'] === 0) {
                $this->SetBalance(0);
            }
        }
    }

    public function ParseItineraries()
    {
        $result = [];

        try {
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Trips');
        } catch (
            UnknownServerException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());

            if (
                strstr($e->getMessage(), 'Connection refused (Connection refused)')
                || $this->http->FindPreg('/(?:Tried to run command without establishing a connection Build info|Curl error thrown for http GET to \/session\/)/', false, $e->getMessage())
                || strstr($e->getMessage(), 'Failed to decode response from marionette Build info: version')
                || strstr($e->getMessage(), 'session timed out or not found')
                || strstr($e->getMessage(), 'JSON decoding of remote response failed. Error code')
            ) {
                return [];
            }

            $this->saveResponse();
        }
        sleep(2);

        $this->logger->debug("page loaded");

        try {
            if (strpos($this->http->currentUrl(), '.perfdrive.com/captcha?') !== false) {
                if ($key = $this->http->FindSingleNode('//div[@class="captcha-mid"]/form//div[@class = "g-recaptcha"]/@data-sitekey')) {
                    $this->sendNotification("check captcha // ZM");
                    $this->captchaWorkaround($key);
                    $this->delay(5);
                    $this->saveResponse();
                }
            }
        } catch (UnknownServerException | NoSuchDriverException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        //$this->waitForElement(WebDriverBy::xpath("//div[@id='json'] | //pre[not(@id)]"), 5);
        $response = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));
        $noItineraries = false;

        if ($this->http->FindPreg('/"upcomingTrips":null,"isSuccess":true,/')) {
            $noItineraries = true;
        }

        // The past don't work until you get out
        try {
            $this->http->GetURL('https://www.flyfrontier.com/travel/my-trips/manage-trip/');
        } catch (
            UnknownServerException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }

        try {
            if ($popup = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_LINK), 5)) {
                $popup->click();

                if ($logout = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'LOG OUT')]"), 1)) {
                    $logout->click();
                    sleep(5);
                }
            }
        } catch (WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        if (!$noItineraries && isset($response->upcomingTrips)) {
            $this->logger->debug("Found: " . count($response->upcomingTrips) . " itineraries");

            foreach ($response->upcomingTrips as $item) {
                // https://booking.flyfrontier.com/Booking/Retrieve?&ln=Romano&rl=Z4LY9V
                if (isset($item->recordLocator)) {
                    $this->logger->info('Parse itinerary #' . $item->recordLocator, ['Header' => 3]);

                    try {
                        $this->http->GetURL("https://booking.flyfrontier.com/Booking/Retrieve?ln={$this->resp->lastName}&rl={$item->recordLocator}");
                    } catch (UnknownServerException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                    } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                        $this->driver->executeScript('window.stop();');
                    }
                    $error = $this->http->FindSingleNode('//h1[contains(text(), "504 Gateway Time-out")]');
                    $error1 = $this->http->FindSingleNode('//h2[contains(text(), "Not Found")]');

                    if ($error || $error1) {
                        $this->logger->error("[Retry]: {$error}");
                        sleep(5);
                        $this->http->GetURL("https://booking.flyfrontier.com/Booking/Retrieve?ln={$this->resp->lastName}&rl={$item->recordLocator}");
                    }

                    if ($msg = $this->http->FindSingleNode('//p[contains(text(), "Credit card payment is invalid.")]')) {
                        $this->logger->error('Skipping itinerary: ' . $msg);

                        continue;
                    }

                    if ($it = $this->ParseItinerary()) {
                        $result[] = $it;
                    }
                }
                $this->delay();
            }
        }

        // Past itineraries
        if ($this->ParsePastIts && isset($response->pastTrips)) {
            $this->logger->debug("Found: " . count($response->pastTrips) . " itineraries");

            foreach ($response->pastTrips as $item) {
                // https://booking.flyfrontier.com/Booking/Retrieve?&ln=Romano&rl=Z4LY9V
                if (isset($item->recordLocator)) {
                    $this->logger->info('Parse itinerary #' . $item->recordLocator, ['Header' => 3]);
                    $this->http->GetURL("https://booking.flyfrontier.com/Booking/Retrieve?&ln={$this->resp->lastName}&rl={$item->recordLocator}");

                    if ($msg = $this->http->FindSingleNode('//p[contains(text(), "Credit card payment is invalid.")]')) {
                        $this->logger->error('Skipping itinerary: ' . $msg);

                        continue;
                    }

                    if ($it = $this->ParseItinerary()) {
                        $result[] = $it;
                    }

                    $this->delay();
                }
            }
        } elseif ($noItineraries) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Redeemable"  => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 0;
        $this->delay();

        try {
            $this->http->GetURL("https://booking.flyfrontier.com/F9Sessionless/AccountTransactions");
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());

            return [];
        } catch (UnknownServerException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());

            if (strstr($e->getMessage(), 'Connection refused')) {
                return [];
            }

            $this->saveResponse();
        }
        $page++;
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog($this->http->FindSingleNode("//div[@id = 'json']"), false);
        $this->logger->debug("Total " . ((is_array($response) || ($response instanceof Countable)) ? count($response) : 0) . " history items were found");

        if (!$response) {
            return $result;
        }

        foreach ($response as $row) {
            $dateStr = $row->date;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $row->description;
            $result[$startIndex]['Redeemable'] = $row->redeemableMiles;
            $startIndex++;
        }

        return $result;
    }

    protected function captchaWorkaround($key)
    {
        $this->logger->notice(__METHOD__);
        $this->DebugInfo = 'reCAPTCHA checkbox';
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true);

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }
        $this->driver->executeScript('document.querySelector("#g-recaptcha-response").value="' . json_encode($captcha) . '";');
        $this->driver->executeScript('document.querySelector("body > div.container > div:nth-child(2) > div.captcha-mid > form > center > input").click()');

        return true;
    }

    protected function parseHCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[contains(@class, "h-captcha")]/@data-sitekey');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => "hcaptcha",
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($retry = true)
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Member', [], 20);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        $this->resp = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));

        if ($retry === true && empty($this->resp)) {
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Member', [], 20);
            $this->resp = $this->http->JsonLog($this->http->FindPreg(self::JSON_REREXP));
        }

        if (isset($this->resp->number, $this->resp->isSuccess)) {
            return true;
        }

        return false;
    }

    private function ParseItinerary(): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['RecordLocator'] = $this->http->FindSingleNode("//span[contains(text(),'Trip Confirmation Number:')]/following-sibling::span[@class='pnr']");

        $flights = $this->http->XPath->query("//div[@class='itin-flights']//div[contains(@class,'itin-flight itin-body')]");

        foreach ($flights as $flight) {
            // Departure: Friday June 29, 2018
            $date = $this->http->FindSingleNode(".//span[contains(text(),'Departure: ') or contains(text(),'Return: ')]", $flight, false, '/:\s*(\w+ \w+.+)/');
            $this->logger->debug('Departure:' . $date);
            $date = strtotime($date, false);

            $its = $this->http->XPath->query(".//div[@class='table-row' and @scope='row']", $flight);
            $this->logger->debug("Found {$its->length} itineraries");

            foreach ($its as $node) {
                $seg = [];
                // AirlineName
                $seg['AirlineName'] = $this->http->FindSingleNode(".//div[@headers='Flight']", $node, false, '/([A-Z\d]+)\s+\d+/');
                // FlightNumber
                $seg['FlightNumber'] = $this->http->FindSingleNode(".//div[@headers='Flight']", $node, false, '/[A-Z\d]+\s+(\d+)/');

                // Depart
                $seg['DepDate'] = strtotime($this->http->FindSingleNode(".//div[@headers='Depart']/span[1]", $node), $date);
                $depart = $this->http->FindSingleNode(".//div[@headers='Depart']/span[2]", $node);
                $seg['DepName'] = $this->http->FindPreg('/^(.+?)\s+\([A-Z]{3}\)/', false, $depart);
                $seg['DepCode'] = $this->http->FindPreg('/\(([A-Z]{3})\)/', false, $depart);
                // Arrive
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode(".//div[@headers='Arrive']/span[1]", $node), $date);
                $arrival = $this->http->FindSingleNode(".//div[@headers='Arrive']/span[2]", $node);
                $seg['ArrName'] = $this->http->FindPreg('/^(.+?)\s+\([A-Z]{3}\)/', false, $arrival);
                $seg['ArrCode'] = $this->http->FindPreg('/\(([A-Z]{3})\)/', false, $arrival);

                // Duration - 3hr 20min
                $seg['Duration'] = $this->http->FindSingleNode(".//div[@headers='Duration']/span[1]", $node);

                if ($this->http->FindPreg('/Non\s*Stop/i', false, $this->http->FindSingleNode(".//div[@headers='Duration']/span[2]", $node))) {
                    $seg['Stops'] = 0;
                }

                $result['TripSegments'][] = $seg;
            }
        }

        $passengers = $this->http->XPath->query("//div[contains(@class,'passengers-details-column')]");

        foreach ($passengers as $node) {
            $result['Passengers'][] = beautifulName($this->http->FindSingleNode(".//span[@class='passenger-name']", $node));
            $result['AccountNumbers'][] = $this->http->FindSingleNode(".//span/i[contains(text(),'Frontier Miles Number:')]/following-sibling::text()", $node, false, '/^\s*(\w+)\s*$/');
        }

        if (!empty($result['AccountNumbers'])) {
            $result['AccountNumbers'] = array_unique($result['AccountNumbers']);
        }

        $result['TotalCharge'] = round(array_sum($this->http->FindNodes("//span/b[contains(text(),'Total:')]/following-sibling::text()[not(contains(., 'MI'))]", null, '/\$([\d+.,]+)/')), 2);
        $result['Currency'] = str_replace('$', 'USD', $this->http->FindSingleNode("(//span/b[contains(text(),'Total:')]/following-sibling::text()[not(contains(., 'MI'))])[1]", null, false, '/(\$)[\d+.,]+/'));
        $result['SpentAwards'] = $this->http->FindSingleNode("//span/b[contains(text(),'Total:')]/following-sibling::text()[contains(., 'MI')]", null, '/MI\s*([\d+.,]+)/');
        $result['Tax'] = $this->http->FindPreg('/"originTaxesAndFees": ([\d\.]+)00\,/');

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function captchaRecognizing()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode("//p[contains(text(), 're not a robot, please complete the CAPTCHA to continue your journey.')]")) {
            return;
        }

        $submit = $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Submit']"), 0);

        if (!$submit) {
            return;
        }

        $captcha = $this->parseHCaptcha();

        if ($captcha === false) {
            return;
        }

        $this->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');
        $this->driver->executeScript('document.getElementsByName("h-captcha-response")[0].value = "' . $captcha . '";');

        $submit->click();

        $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_LINK), 10);
        $this->saveResponse();
    }
}
