<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDollar extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.dollar.com/Express/MainMember.aspx';

    private $lastName = '';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        if ($this->attempt == 2) {
            $this->setProxyGoProxies();
        }

        $this->UseSelenium();

        if ($this->attempt == 1) {
            $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $this->setKeepProfile(true);
        } else {
            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        }

        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->disableImages();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        }
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

//        $this->selenium();

        try {
            $this->http->GetURL("https://www.dollar.com/Express/Login.aspx");
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());

//            throw new CheckRetryNeededException(3, 0);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$ExpressIDTextBox"]'), 15);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$PasswordTextBox"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@name = "contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$LoginButton"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$btn) {
            if ($this->http->FindSingleNode('
                    //h1[contains(text(), "The page isn’t redirecting properly")]
                    | //h1[contains(text(), "Unable to connect")]
                    | //span[contains(text(), "This page isn’t working")]
                ')
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 3);
            }

            if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]")) {
                throw new CheckRetryNeededException(2, 5);
            }

            return $this->checkErrors();
        }

        $this->driver->executeScript("var remember = document.getElementById('contentplaceholder_0_ColumnLayout2_rightpanelregion_0_ExpressLoginColumnLayout_RememberExpressIDSitecoreCheckbox'); if (remember) remember.checked = true;");
        $this->saveResponse();

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->click();
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        if ($error = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "ValidatorMessage")]'), 0)) {
            $message = $error->getText();

            if (strstr($message, 'The Dollar EXPRESS ID you entered is invalid. ')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $btn->click();

        sleep(10);
        $this->saveResponse();

        $this->waitFor(function () {
            return !$this->waitForElement(WebDriverBy::xpath('//div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")]'), 0);
        });

        $this->saveResponse();

        return true;

        if (!$this->http->ParseForm("MainForm")) {
            return $this->checkErrors();
        }

        $this->http->Form['contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$ExpressIDTextBox'] = $this->AccountFields['Login'];
        $this->http->Form['contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$PasswordTextBox'] = $this->AccountFields['Pass'];
        $this->http->Form['contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$LoginButton.x'] = '42';
        $this->http->Form['contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$LoginButton.y'] = '13';
        $this->http->SetInputValue('contentplaceholder_0$ColumnLayout2$rightpanelregion_0$ExpressLoginColumnLayout$RememberExpressIDSitecoreCheckbox', 'on');

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    public function Login()
    {
        /*
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm([], 80)) {
            if (
                $this->http->Response['code'] == 302
                && $this->http->currentUrl() == 'https://www.dollar.com/Express/Login.aspx'
            ) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;
        */
        //# We are sorry, but we cannot process your request at this time
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We are sorry, but we cannot process your request at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Message: 'Join Renter Rewards'
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Join Renter Rewards')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[div[@class="Validator"] and not(@style)]//span[contains(@class, "ValidatorMessage") and not(contains(@class, "ValidatorMessageID"))]')) {
            $this->logger->error($message);
            // The password does not match the password on record. (Comments Express1506)
            if (strstr($message, 'The password does not match the password on record')
                // Please check the Loyalty Number you have entered. (Comments Express1536)
                || strstr($message, 'Please check the Loyalty Number you have entered')
                // The Dollar Express ID you entered is not found in our records. Please try again. (Comments Profile815)
                || strstr($message, 'The Dollar Express ID you entered is not found in our records. Please try again.')
                // The Dollar EXPRESS ID you entered is not found in our records. Please try again. (Comments Profile815)
                || strstr($message, 'The Dollar EXPRESS ID you entered is not found in our records')
                // Please enter your Dollar Express ID. (Comments Profile814)
                || strstr($message, 'Please enter your Dollar Express ID. (Comments Profile')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            /*
             * Your account has been locked.
             * Information has been entered incorrectly multiple times against your account.
             * For security reasons, please verify your account details and Click here to reset your password. (Comments Express1535)
             */
            if (strstr($message, 'Your account has been locked')) {
                throw new CheckException('Your account has been locked. Information has been entered incorrectly multiple times against your account.', ACCOUNT_LOCKOUT);
            }
            /*
             * We are experiencing technical difficulties.
             * Please try again later or call Member Support Services 1-866-776-6667 for assistance.
             */
            if (strstr($message, 'We are experiencing technical difficulties')
                /*
                 * We show that your account is inactive.
                 * Please contact Member Support, dollarexpress@dollar.com or 1-866-776-6667, ext 4. (Comments Profile822)
                 */
                || strstr($message, 'We show that your account is inactive.')
                || strstr($message, 'Please try again later or call Member Support Services 1-866-776-6667 for assistance. (Comments Express1511)')
                || strstr($message, 'No Credit Number provided (Comments Profile871)')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message = $this->http->FindSingleNode('//div[div[@class="Validator"] and not(@style)]//span[contains(@class, "ValidatorMessage")]'))

        if ($this->http->FindSingleNode("//h1/span[text() = 'Reset Password']")) {
            throw new CheckException('The password does not match the password on record.', ACCOUNT_INVALID_PASSWORD);
        }

        // provider bug fix
        if ($this->http->FindSingleNode('
                //h1[contains(text(), "The page isn’t redirecting properly")]
                | //h1[contains(text(), "Unable to connect")]
                | //span[contains(text(), "This page isn’t working")]
        ')) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Total Point Balance
        if (!$this->SetBalance($this->http->FindSingleNode("//span[@id = 'contentplaceholder_0_ColumnLayout2_rightcolumn_1_PointBalanceText']"))
            && $this->http->FindSingleNode("//span[@id = 'contentplaceholder_0_ColumnLayout2_rightcolumn_1_PointBalanceText']") == "") {
            $this->SetBalanceNA();
        }
        // Name
        try {
            $this->http->GetURL("https://www.dollar.com/Express/ModifyProfile.aspx");
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(2);
            $this->saveResponse();
        } catch (NoSuchWindowException $e) {
            $this->logger->error("NoSuchWindowException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(2, 0);
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (Exception $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);

            sleep(2);
            $this->saveResponse();
        }
        $lastName = $this->http->FindSingleNode('//input[@name="contentplaceholder_0$ColumnLayout2$rightcolumn_1$EnrollmentProfile$AboutYourSelfColumn$LastNameTextBox"]/@value');
        $firstName = $this->http->FindSingleNode('//input[@name="contentplaceholder_0$ColumnLayout2$rightcolumn_1$EnrollmentProfile$AboutYourSelfColumn$FirstNameTextBox"]/@value');

        try {
            $this->http->GetURL("https://www.dollar.com/Express/MainMember.aspx");
        } catch (UnknownServerException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        if (!empty($firstName) && !empty($lastName)) {
            $this->SetProperty("Name", beautifulName($firstName . ' ' . $lastName));
            $this->lastName = $lastName;
        } else {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//label[@id="FirstName"]', null, true, '/Welcome back,(.*)/')));
        }
        // Member #
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//span[@id = 'contentplaceholder_0_ColumnLayout1_DxMemberShipNumber']", null, true, "/\#(\d+)/"));
    }

    public function ParseItineraries()
    {
        $result = [];

        if ($this->http->FindSingleNode("//div/div/span[contains(text(), 'No pending reservations found.')]")) {
            return $this->noItinerariesArr();
        }

        $nodes = $this->http->XPath->query("//div[contains(@class, 'RewardsCatalogue')]/descendant::table[descendant::td[normalize-space(.)!=''][2][normalize-space(.)='Rental']]");
//        if ($nodes->length > 0 || (!$this->http->FindNodes('//text()[contains(.,"Driver\'s License Expired") or contains(.,"Primary Credit Card Expired")]')
//        && !$this->http->FindNodes('//a[contains(.,"Update your Profile")]')))
//            $this->sendNotification("find itinerary // MI");
        foreach ($nodes as $root) {
            $confNo = $this->http->FindSingleNode("./descendant::td[normalize-space(.)!=''][4]", $root, true, "#^\s*([A-Z\d]+)\s*$#");
            $date = strtotime($this->http->FindSingleNode("./descendant::td[normalize-space(.)!=''][1]", $root, true, "#^\s*(\d+\/\d+\/\d+)\s*$#"));

            if (($date !== false) && $date > time() && !empty($confNo)) {
                $this->sendNotification("dollar: gotcha future reservation confNo - $confNo; lastName - $this->lastName");
            }
        }
        $links = $this->http->FindNodes('//table[contains(@id, "PendingReservationsGridView")]/tr/td/a[contains(@href, "Confirmation.aspx")]/@href');

        for ($i = 0; $i < count($links); $i++) {
            $this->http->GetURL('https://www.dollar.com/' . $links[$i]);
            $result[] = $this->ParseItinerary();
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
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
        return "https://www.dollar.com/Reservations/Search.aspx";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->sendNotification('check confNo // MI');
        // return $this->CheckConfirmationNumberInternalCurl($arFields, $it);
        return $this->CheckConfirmationNumberInternalSelenium($arFields, $it);
    }

    public function CheckConfirmationNumberInternalCurl($arFields, &$it): ?string
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("MainForm")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $this->http->FormURL = 'https://www.dollar.com/Reservations/Search.aspx';
        $this->http->Form['contentplaceholder_0$column2placeholder_0$ResSearchColumnLayout$ConfirmationNumberTextBox'] = $arFields["ConfNo"];
        $this->http->Form['contentplaceholder_0$column2placeholder_0$ResSearchColumnLayout$LastNameTextBox'] = $arFields["LastName"];
        $this->http->Form['__EVENTTARGET'] = 'contentplaceholder_0$column2placeholder_0$ResSearchColumnLayout$SearchButton';
        $this->http->Form['ctl06$ctl03$ResSearchColumnLayout$SearchButton.x'] = '38';
        $this->http->Form['ctl06$ctl03$ResSearchColumnLayout$SearchButton.y'] = '7';

        if (!$this->http->PostForm()) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        if ($message = $this->http->FindSingleNode("//*[contains(@id, 'Validator') and not(contains(@style, 'display'))]")) {
            if ($this->http->FindPreg("/Reservation has already been cancelled/ims", false, $message)) {
                $it = [['Number' => $arFields["ConfNo"], 'Cancelled' => true]];

                return null;
            }

            return $message;
        }

        if ($this->http->FindPreg("/Keep my Express Number but don't login/")) {
            if (!$this->http->ParseForm("MainForm")) {
                $this->sendNotification('failed to retrieve itinerary by conf #');

                return null;
            }
            $this->http->Form['__EVENTTARGET'] = 'contentplaceholder_0$column2placeholder_0$DollarExpressLogin$SitecoreImageButton1';
            $this->http->Form['contentplaceholder_0$column2placeholder_0$DollarExpressLogin$SitecoreImageButton1.x'] = '22';
            $this->http->Form['contentplaceholder_0$column2placeholder_0$DollarExpressLogin$SitecoreImageButton1.y'] = '10';
            $this->http->PostForm();
        }

        if (!$this->http->FindPreg("/Welcome back/")) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $it = $this->ParseItinerary();
        $it = [$it];

        return null;
    }

    public function CheckConfirmationNumberInternalSelenium($arFields, &$it): ?string
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useChromium();
            // $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));

            $form = $selenium->waitForElement(WebDriverBy::id("MainForm"), 5);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$form) {
                $this->logger->error("Form not found");

                return null;
            }
            $confInput = $selenium->waitForElement(WebDriverBy::name('contentplaceholder_0$column2placeholder_0$ResSearchColumnLayout$ConfirmationNumberTextBox'), 5);

            if (!$confInput) {
                $this->logger->error("Conf input not found");

                return null;
            }
            $lastNameInput = $selenium->waitForElement(WebDriverBy::name('contentplaceholder_0$column2placeholder_0$ResSearchColumnLayout$LastNameTextBox'), 5);

            if (!$lastNameInput) {
                $this->logger->error("Last name input not found");

                return null;
            }
            $confInput->sendKeys($arFields['ConfNo']);
            $lastNameInput->sendKeys($arFields['LastName']);
            $searchButton = $selenium->waitForElement(WebDriverBy::name('contentplaceholder_0$column2placeholder_0$ResSearchColumnLayout$SearchButton'), 5);

            if (!$searchButton) {
                $this->logger->error("Search button not found");

                return null;
            }
            $searchButton->click();
            $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@id,"ConfirmationNumberLabel")] | //h2/span[normalize-space()="Verification"]'), 5);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if ($this->http->FindSingleNode("//h2/span[normalize-space()='Verification']")) {
                $goButton = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(.,\"Keep my Express Number but don't login\")]/following-sibling::div//input[@title='Go']"),
                    0);

                if (!$goButton) {
                    $this->logger->error("Go button not found");

                    return null;
                }
                $goButton->click();
                $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@id,"ConfirmationNumberLabel")]'), 5);
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
            }
            $message = $this->http->FindSingleNode("//*[contains(@id, 'Validator') and not(contains(@style, 'display'))]");

            if ($message) {
                if ($this->http->FindPreg("/Reservation has already been cancelled/ims", false, $message)) {
                    $it = [
                        'Number'    => $arFields['ConfNo'],
                        'Cancelled' => true,
                    ];

                    return null;
                }

                if ($this->http->FindPreg("/We're sorry; our system encountered a temporary problem in processing your request\./ims", false, $message)) {
                    return "We're sorry; our system encountered a temporary problem in processing your request.";
                }

                if ($this->http->FindPreg("/Reservation not on file\./ims", false, $message)) {
                    return "Reservation not on file.";
                }

                if ($this->http->FindPreg("/Confirmation number not found in system\./ims", false, $message)) {
                    return "Confirmation number not found in system.";
                }

                return $message;
            }

            $it = $this->ParseItinerary();
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(text(), "Log out")]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // "Internal Server Error - Read"
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Server Error') or contains(text(), '504 Gateway Time-out')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The service is unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'An unexpected error has occurred.')]")) {
            throw new CheckException($message . 'Please try again later', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $result['Kind'] = 'L';
        $result['Number'] = $this->http->FindSingleNode('//span[contains(@id,"ConfirmationNumberLabel")]');
        // Pickup
        $pickup = $this->http->FindNodes('//span[contains(@id, "PickupLocationLabel")]/node()');

        if (count($pickup) > 1) {
            if (strtotime(str_replace('@', '', $pickup[count($pickup) - 2])) !== false) {
                $result['PickupDatetime'] = strtotime(str_replace('@', '', $pickup[count($pickup) - 2]));
            }

            for ($i = 0; $i < count($pickup) - 2; $i++) {
                if (preg_match('/[A-Za-z]/', $pickup[$i])) {
                    $pickupLocation[] = $pickup[$i];
                } elseif (!empty($pickup[$i]) && !preg_match('/[A-Za-z\s*]/', $pickup[$i])) {
                    $pickupPhone[] = $pickup[$i];
                }
            }

            if (isset($pickupLocation[0])) {
                $result['PickupLocation'] = implode(', ', $pickupLocation);
            }

            if (isset($pickupPhone[0])) {
                $result['PickupPhone'] = implode(', ', $pickupPhone);
            }
        }
        // Dropoff
        $dropoff = $this->http->FindNodes('//span[contains(@id, "ReturnLocationLabel")]/node()');

        if (count($dropoff) > 1) {
            if (strtotime(str_replace('@', '', $dropoff[count($dropoff) - 2])) !== false) {
                $result['DropoffDatetime'] = strtotime(str_replace('@', '', $dropoff[count($dropoff) - 2]));
            }

            for ($i = 0; $i < count($dropoff) - 2; $i++) {
                if (preg_match('/[A-Za-z]/', $dropoff[$i])) {
                    if (preg_match('/Same as Pick-up/', $dropoff[$i])) {
                        $dropoffLocation = $pickupLocation;

                        break;
                    } else {
                        $dropoffLocation[] = $dropoff[$i];
                    }
                } elseif (!empty($dropoff[$i]) && !preg_match('/[A-Za-z\s*]/', $dropoff[$i])) {
                    $dropoffPhone[] = $dropoff[$i];
                }
            }

            if (isset($dropoffLocation[0])) {
                $result['DropoffLocation'] = implode(', ', $dropoffLocation);
            }

            if (isset($dropoffPhone[0])) {
                $result['PickupPhone'] = implode(', ', $dropoffPhone);
            }
        }
        // CarType
        $result['CarType'] = $this->http->FindSingleNode('//span[contains(@id, "CarTypeLabel")]');
        // CarModel
        $result['CarModel'] = $this->http->FindSingleNode('//span[contains(@id, "VehicleDescriptionLabel")]');
        // CarImageUrl
        $carImgUrl = $this->http->FindSingleNode('//img[contains(@id, "VehicleImage")]/@src');

        if (isset($carImgUrl)) {
            $result['CarImageUrl'] = 'https://www.dollar.com' . $carImgUrl;
        }
        // PromoCode
        $result['PromoCode'] = $this->http->FindSingleNode('//span[contains(@id, "PromotionNumberLabel")]');
        // TotalCharge
        $result['TotalCharge'] = $this->http->FindSingleNode('//span[contains(@id, "EstGrandTotalLabel")]', null, true, '/(\d+[\.\,]?\d*[\.\,]?\d*)/');
        // Currency
        $result['Currency'] = $this->http->FindSingleNode('//span[contains(@id, "TotalBaseRateLabel")]', null, true, '/\b([A-Z]{3})\b/');

        $nodes = $this->http->XPath->query('//div[contains(@id, "MandatoryCharges")]/div/div[contains(@class, "Small")]');
        $taxes = $fees = [];

        for ($i = 0; $i < $nodes->length; $i++) {
            $name = $this->http->FindSingleNode('div[1]', $nodes->item($i));
            $charge = $this->http->FindSingleNode('div[2]', $nodes->item($i), true, '/(\d+[\.\,]?\d*[\.\,]?\d*)/');

            if (isset($charge) && !empty($charge) && floatval($charge) != 0) {
                if (preg_match('/Tax/', $name)) {
                    $taxes[] = str_replace(',', '', $charge);
                } else {
                    $fees[] = ['Name' => $name, 'Charge' => str_replace(',', '', $charge)];
                }
            }
        }
        // TotalTaxAmount
        if (isset($taxes[0])) {
            $result['TotalTaxAmount'] = array_sum($taxes);
        }
        // Fees
        $result['Fees'] = $fees;
        // AccountNumbers
        $result['AccountNumbers'] = $this->http->FindSingleNode('//span[contains(@id, "LoyaltyProgramLabel")]', null, true, '/(\d+)/');

        $result['RenterName'] = $this->http->FindSingleNode('//span[contains(@id, "ThankYouSitecoreLabel")]', null, true, '/Welcome back, (.+)\.\s+/ims');

        return $result;
    }
}
