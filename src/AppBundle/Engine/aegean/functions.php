<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAegean extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const XPATH_PAGE_HISTORY = "(//div[@class = 'activitylist-container']/table)[1]//tr[td and not(th)]";
    private const REWARDS_PAGE_URL = "https://en.aegeanair.com/milesandbonus/my-account/";

    public $browser;

    public $seleniumURL = null;

    /**
     * Try to reformat date from %d/%m/%Y, to %m/%d/%Y
     * if failed returns $dateStr.
     *
     * @param $dateStr String
     *
     * @return string
     */
    public function reformatDate($dateStr)
    {
        //if date in format DD/MM/YYYY, then need to exchange DD and MM
        $dateAssoc = strptime($dateStr, '%d/%m/%Y');

        if ($dateAssoc !== false) {
            return ($dateAssoc['tm_mon'] + 1) . '/' . $dateAssoc['tm_mday'] . '/' . ($dateAssoc['tm_year'] + 1900);
        }

        return $dateStr;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // xss
        if (strstr($this->AccountFields['Login'], '<')) {
            throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://en.aegeanair.com/member/login/');

        if (!$this->http->ParseForm("loginPageFormId")) {
            return $this->checkErrors();
        }

        // incapsula workaround
        $this->selenium();

        return true;

        $this->http->SetInputValue("Username", $this->AccountFields['Login']);
        $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("X-Requested-With", "XMLHttpRequest");
        $this->http->SetInputValue("RedirectUrl", $this->http->FindSingleNode("//select[@name = 'RedirectUrl']/option[contains(text(), 'My Miles')]/@value"));

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
//        /** @var TAccountChecker $selenium */
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();

            $selenium->http->SetProxy($this->proxyDOP());

            $request = AwardWallet\Common\Selenium\FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://en.aegeanair.com/member/login/");
            } catch (ScriptTimeoutException | TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->debug("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "Username"]'), 10, false);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "Password"]'), 0, false);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0, false);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                // This site can’t be reached
                if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")) {
                    $this->DebugInfo = "This site can’t be reached";
                    $retry = true;
                }// if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]"))

                if ($this->http->FindSingleNode("//p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]")) {
                    $this->DebugInfo = "No internet";
                    $retry = true;
                }

                return $this->checkErrors();
            }
//            $loginInput->sendKeys($this->AccountFields['Login']);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
//            // Sign In
//            $button->click();

            $this->logger->notice("js injection");
            $selenium->driver->executeScript("
                var form = $('form[id = \"loginPageFormId\"]')
                form.find('input[name = Username]').val('{$this->AccountFields['Login']}');
                form.find('input[name = Password]').val('" . str_replace(["\\", "'"], ["\\\\", "\'"], $this->AccountFields['Pass']) . "');
                form.find('button[type = \"submit\"]').click();
            ");

            $result = $selenium->waitForElement(WebDriverBy::xpath('
                //h1[contains(text(), "My Miles+Bonus account")]
                | //div[contains(@class, "default")]//div[@class = "modal-body"]/div
                | //div[contains(text(), "Login verification with one-time password")]
            '), 60);

            // provider bug fix
            if (
                !$result
                && $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "loginPageFormId"]//button[contains(@class, "btn btn-lg btn-progress")]/preceding-sibling::div[@class = "spinner"]'), 0)
            ) {
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");

            if ($this->seleniumURL == 'https://en.aegeanair.com/member/force-change-password/' && $this->http->FindSingleNode("//h1[contains(text(), 'Change Password')]")) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->seleniumURL == 'https://en.aegeanair.com/milesandbonus/member/upgrade-or-link-account/' && $this->http->FindSingleNode("//h1[contains(text(), 'You are currently not a Miles+Bonus member')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // This site can’t be reached
            if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")) {
                $this->DebugInfo = "This site can’t be reached";

                throw new CheckRetryNeededException(3, 10);
            }// if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]"))

            if ($result && $result->getText()) {
                $message = $result->getText();
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, "t validate your credentials. Please enter your valid credentials to login.")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, "Login to “My Aegean” as well as registration to aegeanair.com & Miles+Bonus are temporarily unavailable due to scheduled maintenance.")
                    || strstr($message, "Your account login verification could not be completed as you have not filled in your email or mobile phone in your account.")
                    || strstr($message, "Login is temporarily unavailable due to maintenance activity.")
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->debug("WebDriverException: " . $e->getMessage());
            $retry = true;
        } catch (TimeOutException $e) {
            $this->logger->debug("TimeoutException: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $xKeys;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'At the moment the website is facing some technical difficulties.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(?:<body>\s*|^)(The service is unavailable.)(?:\s*<\/body>|)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//h2[contains(text(), "Server Error in \'/\' Application.")]')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            // retries
            if ($this->http->Response['code'] == 0) {
                $this->http->TimeLimit = 500;
                throw new CheckRetryNeededException(3, 10);
            }

            return $this->checkErrors();
        }
        */
        // Access is allowed
        if ($this->http->FindPreg("/window.location.replace\(\"https:\/\/\w+\.aegeanair\.com\/milesandbonus\/my-account\//")
            || $this->http->FindPreg("/window.location.replace\(\"https:\/\/\w+\.aegeanair\.com\/member\/what-is-new-miles\//")
            // We would like to inform you that we are changing our Password Policy. You can proceed to changing your password now or postpone it for a later time
            || $this->http->FindPreg('/\["We would like to inform you that we are changing our Password Policy. You can proceed to changing your password now or postpone it for a later time."\],\s*\[\{"popUpButton":null,"action":null,"link":"https:\/\/\w+\.aegeanair\.com\/milesandbonus\/my-account\/","primary":false,"text":"POSTPONE"\}/')
            || in_array($this->seleniumURL, [
                'https://en.aegeanair.com/member/what-is-new-miles/',
                'https://en.aegeanair.com/member/my-profile/?note=incomplete',
            ]
            )
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            // retry
            if ($this->http->currentUrl() == 'https://en.aegeanair.com/member/login/?goto=8BA4888C237D48A99E9C9F36E3C91949') {
                throw new CheckRetryNeededException(2, 10);
            }

            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg("/\[\"((?:Please use your Miles\+Bonus account credentials to login\.|This account is not allowed for web account|A Miles\+Bonus account exists with this email. Please use your Miles\+Bonus credentials to login or change the email address\.|We can.u0027t validate your credentials\. If you are a Miles\+Bonus member, please enter your valid Miles\+Bonus credentials to login\..u0026nbsp;|We can.u0027t validate your credentials\. Please enter your valid credentials to login\.\s*))\"\]/")) {
            throw new CheckException(str_replace(['\u0027', '\u0026nbsp;'], ["'", ''], $message), ACCOUNT_INVALID_PASSWORD);
        }
        // Your Miles\+Bonus account has been closed\.
        if ($message = $this->http->FindPreg("/\[\"(Your Miles\+Bonus account has been closed\.)/")) {
            throw new CheckException(str_replace(['\u0027', '\u0026nbsp;'], ["'", ''], $message), ACCOUNT_PROVIDER_ERROR);
        }
        // Your account has been locked due to 5 failed login attempts.
        if ($message = $this->http->FindPreg("/\[\"(Your account has been locked due to 5 failed login attempts\.[^\"]*)\"\]/")) {
            throw new CheckException("Your account has been locked due to 5 failed login attempts.", ACCOUNT_LOCKOUT);
        }
        // Sorry, your request cannot be completed at this time. Please try again later.
        if ($message = $this->http->FindPreg("/\[\"(zSorry, your request cannot be completed at this time\. Please try again later\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Login to “My Aegean” service as well as registration to aegeanair.com \u0026 Miles+Bonus are temporarily unavailable due to scheduled maintenance. Please try again later.
        if ($message = $this->http->FindPreg("/\[\"(Login to \“My Aegean\” service as well as registration to aegeanair\.com[^\"]+Miles\+Bonus are temporarily unavailable due to scheduled maintenance\. Please try again later\.)\"/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Pursuant to the European Union's General Data Protection Regulation (GDPR), we require your consent so as to be able to continue communicating with you and for you to receive updates and promotional material that match with your interests and preferences.
        if ($message = $this->http->FindPreg('/\["Pursuant to the European Union.+?General Data Protection Regulation \(GDPR\), we require your consent/')) {
            $this->throwAcceptTermsMessageException();
        }
        // Change Password
        if ($this->http->FindSingleNode("//input[@name='RedirectUrl' and @value='https://en.aegeanair.com/member/force-change-password/']/@value")
            && $this->http->FindPreg('#window.location.replace\("https://en.aegeanair.com/member/force-change-password/"\);#')) {
            $this->throwProfileUpdateMessageException();
        }

        // not a member
        if ($this->http->FindPreg("/window.location.replace\(\"(https:\/\/en\.aegeanair\.com\/member\/my-profile\/\?note=profile)\"/")
            || $this->http->FindPreg('/\["We would like to inform you that we are changing our Password Policy. You can proceed to changing your password now or postpone it for a later time."\],\s*\[\{"popUpButton":null,"action":null,"link":"https:\/\/\w+\.aegeanair\.com\/member\/my-profile\/","primary":false,"text":"POSTPONE"\}/')) {
            $this->http->GetURL("https://en.aegeanair.com/member/my-profile/");

            if ($this->http->FindPreg("/(?:Since you are not a Miles\+Bonus member, we have redirected you to your profile page\.|Upgrade your account and start earning your rewards today!)/")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Sorry, your request cannot be completed at this time. Please try again later")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $rId = $this->http->FindSingleNode('//input[@name = "rId"]/@value');
        $email = $this->http->FindSingleNode('//label[@for = "EmailMethod"]', null, false, "/Email\s*(.+)/");

        if (
            !$this->http->FindSingleNode('//div[contains(text(), "Login verification with one-time password")]')
            || !$this->http->ParseForm("__AjaxAntiForgeryForm")
            || !$rId
            || !$email
        ) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $data = [
            "identifier"                 => $rId,
            "channel"                    => "Email",
            "__RequestVerificationToken" => $this->http->Form['__RequestVerificationToken'],
        ];
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://en.aegeanair.com/sys/Member/SendOTP", $data, $headers);
        $response = $this->http->JsonLog();

        if (
            !isset($response->Result)
            || $response->Result != true
        ) {
            if (isset($response->ErrorMessages[0]) && $response->ErrorMessages[0] == "You have exceeded the maximum number of one-time password attempts. Please try again later.") {
                throw new CheckException($response->ErrorMessages[0], ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $this->State['identifier'] = $rId;
        $this->Question = "Please enter the one-time password you have received in your registered email {$email}";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $data = [
            "identifier" => $this->State['identifier'],
            "otp"        => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://en.aegeanair.com/sys/Member/ValidateOTP", $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (
            isset($response->ErrorMessages)
            && in_array($response->ErrorMessages, [
                'The one-time password that you entered has expired. Please try again.',
                'The one-time password that you entered is invalid. Please try again.',
            ])
        ) {
            $this->AskQuestion($this->Question, $response->ErrorMessages, 'Question');

            return false;
        }

        if (isset($response->Redirect)) {
            $this->http->GetURL($response->Redirect);

            if (in_array($this->http->currentUrl(), [
                'https://en.aegeanair.com/member/what-is-new-miles/',
                'https://en.aegeanair.com/member/my-profile/?note=incomplete',
            ]
            )) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }

            if (
                in_array($this->http->currentUrl(), [
                    'https://en.aegeanair.com/member/force-change-password/',
                    'https://en.aegeanair.com/member/force-change-password/?note=incomplete',
                ])
                && $this->http->FindSingleNode("//h1[contains(text(), 'Change Password')]")
            ) {
                $this->throwProfileUpdateMessageException();
            }
        }

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@id = 'basicInfo']//h2[@class = 'mnbBlock__title']")));
        // Member ID
        $this->SetProperty('Number', $this->http->FindPreg("/\"LoyaltyUserID\":\"([^\"]+)/"));
        // Balance - Award Miles
        $this->SetBalance($this->http->FindSingleNode("//p[@id = 'user_awarded_miles']", null, true, self::BALANCE_REGEXP_EXTENDED));
        // Tier
        $this->SetProperty('CardLevel', $this->http->FindPreg("/\"LoyaltyLevel\":\"([^\"]+)/"));
        // Exp. Date
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode("//div[contains(text(), 'Exp. Date')]/following-sibling::div[1]"));
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//p[contains(text(), 'Member since:')]", null, true, "/:\s*([^<]+)/"));

        // To upgrade your tier - Required number of Tier Miles
        $this->SetProperty('TierMilesToNextLevel',
            $this->http->FindSingleNode("(//div[h2[contains(text(), 'How to upgrade my tier.')]]/following-sibling::div[1]//div[@class = 'tierDetails__options']//p[@class = 'tierDetails__optionMiles'])[1]", null, true, "/([\d\-\.\,]+)/ims")
            // refs #16723
            ?? $this->http->FindSingleNode("//div[h2[contains(text(), 'How to upgrade my tier')]]/following-sibling::div[1]", null, true, "/earn ([\d,.]+) Tier Miles within 12 months/ims")
        );
        // To upgrade your tier - including ... flights with Aegean and Olympic Air
        $this->SetProperty('FlightsToNextLevel', $this->http->FindSingleNode("//div[h2[contains(text(), 'How to upgrade my tier.')]]/following-sibling::div[1]//div[@class = 'tierDetails__options']//p[contains(text(), 'including')]/b"));
        // To upgrade your tier - Required number of Tier Miles in total
        $this->SetProperty('TierMilesToNextLevelInTotal',
            $this->http->FindSingleNode("(//div[h2[contains(text(), 'How to upgrade my tier.')]]/following-sibling::div[1]//div[@class = 'tierDetails__options']//p[@class = 'tierDetails__optionMiles'])[2]", null, true, "/([\d\-\.\,]+)/ims")
            // refs #16723
            ?? $this->http->FindSingleNode("//div[h2[contains(text(), 'How to upgrade my tier')]]/following-sibling::div[1]", null, true, "/total ([\d,.]+) Tier Miles,/ims")
        );
        // To maintain your tier - Required number of Tier Miles
        $this->SetProperty('TierMilesToRetainLevel', $this->http->FindSingleNode("(//div[h3[contains(text(), 'To maintain my tier')]]/following-sibling::div[1]//div[@class = 'tierDetails__options']//p[@class = 'tierDetails__optionMiles'])[1]", null, true, "/([\d\-\.\,]+)/ims"));
        // To maintain your tier - Required number of Tier Miles
        $this->SetProperty('TierMilesToRetainLevelInTotal', $this->http->FindSingleNode("(//div[h3[contains(text(), 'To maintain my tier')]]/following-sibling::div[1]//div[@class = 'tierDetails__options']//p[@class = 'tierDetails__optionMiles'])[2]", null, true, "/([\d\-\.\,]+)/ims"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg["CookieURL"] = "http://milesandbonus.aegeanair.com/WEBSITE/Login.jsp?activeLanguage=EN";
        $arg["SuccessURL"] = 'https://en.aegeanair.com/milesandbonus/member/my-account/';

        return $arg;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Company"     => "Info",
            "Description" => "Description",
            "Award Miles" => "Miles",
            "Tier Miles"  => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 1;
        $this->http->GetURL("https://en.aegeanair.com/milesandbonus/my-account/my-transactions/");

        do {
            $this->logger->debug("[Page: {$page}]");

            if ($page > 1 && isset($nextPage)) {
                $this->http->NormalizeURL($nextPage);
                $this->http->GetURL($nextPage);
            }
            $page++;
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while ($page < 30 && ($nextPage = $this->http->FindSingleNode("(//a[@title = 'Page {$page}']/@href)[1]")));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query(self::XPATH_PAGE_HISTORY);
        $this->logger->debug("Total {$nodes->length} history items were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $dateStr = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $postDate = $this->ModifyDateFormat($dateStr);
            $postDate = strtotime($postDate);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Company'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
            $result[$startIndex]['Award Miles'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
            $result[$startIndex]['Tier Miles'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
            $startIndex++;
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://en.aegeanair.com/plan/my-booking/';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetProxy($this->proxyDOP());
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm(null, '//form[@action = "/MyBooking.axd"]', true, 1)) {
            $this->notifyFailedRetrieve($arFields);

            return null;
        }
        $this->http->SetInputValue('PNR', $arFields['ConfNo']);
        $this->http->SetInputValue('LastName', $arFields['LastName']);

        if (!$this->postItinerary($arFields)) {
            $this->sendNotification("failed to retrieve itinerary by conf // MI");

            return null;
        }

        if ($msg = $this->http->FindPreg('/"(We are unable to find this confirmation number\..+?)"/')) {
            return $msg;
        }
        $data = $this->http->JsonLog(null, 0, true);
        $it = $this->parseItinerary($data, $arFields);

        return null;
    }

    public function ParseItineraries()
    {
        $res = [];
        $this->http->SetProxy($this->proxyDOP());
        $this->http->GetURL('https://en.aegeanair.com/member/my-bookings/');

        if ($this->http->FindSingleNode('//input[@id = "pnrlist-info-message" and contains(@value, "No bookings were found for this member.")]/@value')) {
            return $this->noItinerariesArr();
        }

        $forms = $this->http->FindNodes('//form[@name = "manageMyBooking"]');

        for ($i = 0; $i < count($forms); $i++) {
            $this->loadItineraryWrapper($i);
            $data = $this->http->JsonLog(null, 0, true);

            if ($data) {
                $it = $this->parseItinerary($data);
            }

            if ($it) {
                $res[] = $it;
            }
        }

        return $res;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@href, "logout")]')) {
            return true;
        }

        return false;
    }

    private function isCaptchaSuccess()
    {
        $this->logger->notice(__METHOD__);

        return $this->http->Response['code'] === 204;
    }

    private function postItinerary($arFields = null)
    {
        $this->logger->notice(__METHOD__);
        $this->http->PostForm();

        if (!$this->http->ParseForm('form_MasterPage')) {
            $this->notifyFailedRetrieve($arFields);

            return null;
        }
        $memForm = $this->http->Form;
        $memFormURL = $this->http->FormURL;

        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            return null;
        }
        $this->http->RetryCount = 2;
        $this->distil();

        if ($this->isCaptchaSuccess()) {
            if (empty($this->http->Response['body'])) {
                $this->http->FormURL = $memFormURL;
                $this->http->Form = $memForm;
                $this->http->RetryCount = 0;
                $this->http->PostForm();
                $this->http->RetryCount = 2;
            }

            if ($this->http->Response['code'] !== 200) {
                return false;
            }
        }
        $distilAjax = $this->getDistilAjax();

        $jsessionid = $this->http->FindPreg('/var jsessionid\s*=\s*"(.+?)";/');
        $data = $this->http->FindPreg('/var entryRequestParams\s*=\s*(\{.+?\});/');

        if (!$jsessionid || !$data) {
            $this->logger->error('aegean - post itinerary failed');

            return false;
        }
        $params = [
            'data'            => $data,
            'FORCE_OVERRIDE'  => true,
            'LANGUAGE'        => 'GB',
            'SITE'            => 'E00KE00K',
            'SKIN'            => 'skin_aegean',
            'WDS_STRESS_TEST' => '',
        ];
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'X-Distil-Ajax' => $distilAjax,
        ];
        $url = sprintf('https://e-ticket.aegeanair.com/A3Responsive/dyn/air/servicing/myBooking.json;jsessionid=%s', $jsessionid);
        $this->http->RetryCount = 0;
        $this->http->PostURL($url, $params, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    private function getDistilAjax()
    {
        $this->logger->notice(__METHOD__);
        $src = $this->http->FindSingleNode('//script[
            @type = "text/javascript" and
            @src and
            not(contains(@src, "distil"))
        ]/@src');
        $this->http->NormalizeURL($src);
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $http2->RetryCount = 0;
        $http2->GetURL($src);
        $http2->RetryCount = 2;
        $res = $http2->FindPreg('/ajax_header:"(\w+)"/');
        $this->logger->info("ajax_header = {$res}");

        return $res;
    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        if ($this->http->FindPreg("/Validating JavaScript Engine/")) {
            $this->http->GetURL($this->http->currentUrl());
        }
        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm(null, "//form[@id = 'distilCaptchaForm' and @class = 'recaptcha2']", true, 1)) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('isAjax', "1");

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        sleep(5);
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function loadItineraryWrapper($i)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->loadItinerary($i)) {
            if (
                $this->isCaptchaSuccess()
                || $this->isConnectionProblem()
            ) {
                $this->logger->info('itinerary load retry');

                if (!$this->loadItinerary($i)) {
                    $this->logger->error('itinerary not loaded');
                }
            }
        }
    }

    private function loadItinerary($i)
    {
        $this->logger->notice(__METHOD__);
        $formFilter = sprintf('(//form[@name = "manageMyBooking"])[%s]', $i + 1);

        if ($i > 0) {
            $this->http->GetURL('https://en.aegeanair.com/member/my-bookings/');

            // it helps
            if (!$this->http->ParseForm(null, $formFilter, true, 1)) {
                $this->http->GetURL('https://en.aegeanair.com/member/my-bookings/');
            }
        }

        $this->http->ParseForm(null, $formFilter, true, 1);
        $conf = ArrayVal($this->http->Form, 'REC_LOC');
        $this->logger->info('Load Itinerary #' . $conf, ['Header' => 3]);

        if (!$this->postItinerary()) {
            return false;
        }

        return true;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm' and @class = 'recaptcha2']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function notifyFailedRetrieve($arFields)
    {
        $this->logger->notice(__METHOD__);

        if (!$arFields) {
            $this->sendNotification('check itineraries');

            return null;
        }
        $this->sendNotification("failed to retrieve itinerary by conf #");
    }

    private function isErrorItinerary()
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindPreg('/(This trip cannot be found\. It may have been cancelled\.)/')) {
            return $msg;
        } elseif ($msg = $this->http->FindPreg('/(We are unable to find this confirmation number\. Please validate your entry and try again or contact us for further information\.)/')) {
            return $msg;
        } elseif ($msg = $this->http->FindPreg('/(No AIR element is present in this PNR)/')) {
            return $msg;
        } elseif ($msg = $this->http->FindPreg('/(The itinerary you have searched is not available anymore\.)/')) {
            return $msg;
        }

        return false;
    }

    private function isConnectionProblem()
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindPreg('/(?:Operation timed out after|transfer closed with outstanding read data remaining)/', false, $this->http->Response['errorMessage'])) {
            return $msg;
        } elseif (in_array($this->http->Response['code'], [503, 405, 204])) {
            return sprintf('http %s', $this->http->Response['code']);
        }

        return false;
    }

    private function parseItinerary($data, $arFields = null)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];

        if (
            ($msg = $this->isErrorItinerary())
            || ($msg = $this->isConnectionProblem())
        ) {
            $this->logger->error($msg);

            return [];
        }
        $pnrRecap = $this->ArrayVal($data, ['bom', 'modelObject', 'pnrRecap']);

        if (!$pnrRecap) {
            // $this->notifyFailedRetrieve($arFields);
            return [];
        }

        // RecordLocator
        $conf = $this->ArrayVal($pnrRecap, ['pnrInfo', 'mainRecordLocator', 'code']);

        if (!$conf) {
            $this->notifyFailedRetrieve($arFields);

            return [];
        }
        $res['RecordLocator'] = $conf;
        $this->logger->info('Parse Itinerary #' . $conf, ['Header' => 3]);
        // Passengers
        $travellers = $this->ArrayVal($pnrRecap, ['travellersInformation', 'travellers'], []);
        $res['Passengers'] = [];

        foreach ($travellers as $trav) {
            $identity = $this->ArrayVal($trav, 'identityInformation', '');
            $name = beautifulName(trim(sprintf('%s %s %s',
                $this->ArrayVal($identity, 'title'),
                $this->ArrayVal($identity, 'firstName'),
                $this->ArrayVal($identity, 'lastName')
            )));
            $res['Passengers'][] = $name;
        }
        // TotalCharge
        $priceRecap = $this->ArrayVal($pnrRecap, 'priceRecap');
        $res['TotalCharge'] = $this->ArrayVal($priceRecap, ['priceForAllTravellers', 0, 'totalPrice', 'cashAmount', 'amount']);
        // Currency
        $res['Currency'] = $this->ArrayVal($priceRecap, ['priceForAllTravellers', 0, 'totalPrice', 'cashAmount', 'currency']);
        // BaseFare
        $res['BaseFare'] = $this->ArrayVal($priceRecap, ['priceForAllTravellers', 0, 'totalPriceWithoutTax', 'cashAmount', 'amount']);
        // Tax
        $res['Tax'] = $this->ArrayVal($priceRecap, ['priceForAllTravellers', 0, 'totalTaxes', 'cashAmount', 'amount']);
        // Segments
        $res['TripSegments'] = [];
        $flightBounds = $this->ArrayVal($pnrRecap, ['airRecap', 'flightBounds'], []);

        foreach ($flightBounds as $bound) {
            $flightSegments = $this->ArrayVal($bound, 'flightSegments', []);

            foreach ($flightSegments as $segment) {
                $ts = [];
                // FlightNumber
                $ts['FlightNumber'] = $this->ArrayVal($segment, ['flightIdentifier', 'flightNumber']);
                // AirlineName
                $ts['AirlineName'] = $this->ArrayVal($segment, ['flightIdentifier', 'marketingAirline']);
                // Operator
                $ts['Operator'] = $this->ArrayVal($segment, 'effectiveOperatingAirline');
                // DepCode
                $loc1 = $this->ArrayVal($segment, 'originLocation');

                if ($loc1) {
                    $ts['DepCode'] = $this->http->FindPreg('/_(\w{3})$/', false, $loc1);
                }
                // ArrCode
                $loc2 = $this->ArrayVal($segment, 'destinationLocation');

                if ($loc2) {
                    $ts['ArrCode'] = $this->http->FindPreg('/_(\w{3})$/', false, $loc2);
                }
                // DepartureTerminal
                if ($loc1) {
                    $ts['DepartureTerminal'] = $this->http->FindPreg('/^T(\w+)_/', false, $loc1);
                }
                // ArrivalTerminal
                if ($loc2) {
                    $ts['ArrivalTerminal'] = $this->http->FindPreg('/^T(\w+)_/', false, $loc2);
                }
                // DepDate
                $date1 = $this->ArrayVal($segment, ['flightIdentifier', 'originDate']);

                if ($date1) {
                    $ts['DepDate'] = $date1 / 1000;
                }
                // ArrDate
                $date2 = $this->ArrayVal($segment, 'destinationDate');

                if ($date2) {
                    $ts['ArrDate'] = $date2 / 1000;
                }
                // Duration
                $dur = ArrayVal($segment, 'duration', 0);

                if ($dur) {
                    $ts['Duration'] = date('G\h i\m', $dur / 1000);
                }
                // Aircraft
                $ts['Aircraft'] = $this->ArrayVal($segment, 'equipment');
                // Stops
                $ts['Stops'] = $this->ArrayVal($segment, 'numberOfStops');
                // BookingClass
                $ts['BookingClass'] = $this->ArrayVal($segment, ['cabins', 0, 'rbds', 0, 'code']);
                $res['TripSegments'][] = $ts;
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function ArrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        if (!is_array($indices)) {
            $indices = [$indices];
        }

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
}
