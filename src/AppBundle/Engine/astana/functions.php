<?php

class TAccountCheckerAstana extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const WAIT_TIMEOUT = 10;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (!is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Incorrect membership number or password", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://airastana.com/global-en/account-overview');

        $this->waitForElement(WebDriverBy::xpath('//div[@test-id="login-preference-selection"]//div[@role="button"]'), self::WAIT_TIMEOUT);
        sleep(5); // prevent form errors
        $selectLoginTypeButton = $this->waitForElement(WebDriverBy::xpath('//div[@test-id="login-preference-selection"]//div[@role="button"]'), self::WAIT_TIMEOUT);

        $this->saveResponse();
        $selectLoginTypeButton->click();

        if (strstr($this->AccountFields['Login'], "@")) { // set login type
            $this->waitForElement(WebDriverBy::xpath('//li[@data-value="email"]'), self::WAIT_TIMEOUT)->click();
            $loginXpath = '//div[@test-id="login-email-input"]//input';
        } else {
            $this->waitForElement(WebDriverBy::xpath('//li[@data-value="membershipNumber"]'), self::WAIT_TIMEOUT)->click();
            $loginXpath = '//div[@test-id="login-membership-number-input"]//input';
        }

        $login = $this->waitForElement(WebDriverBy::xpath($loginXpath), self::WAIT_TIMEOUT);

        $this->saveResponse();

        $password = $this->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//label[@test-id="login-remember-me-checkbox"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@test-id="login-button"]'), 0);

        if (!$login || !$password || !$rememberMe || !$submit) {
            $this->logger->error("Failed to find form fields");

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $rememberMe->click();
        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "MuiToolbar-root")]//button[contains(@class, "MuiButtonBase-root")]//*[@d="M4.463 8.476a.75.75 0 0 1 1.061-.013l5.606 5.477a1.236 1.236 0 0 0 1.74 0l.006-.007 5.6-5.47a.75.75 0 0 1 1.048 1.074l-5.597 5.467a2.736 2.736 0 0 1-3.854 0L4.476 9.537a.75.75 0 0 1-.013-1.061z"]
            | //*[@color="#FFFFFF"]/../span[contains(@class, "MuiTypography-root")] | //input[@name="securityCode"]
        '), self::WAIT_TIMEOUT);

        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//input[@name="securityCode"]'), 0)) {
            return $this->processQuestion();
        }

        if ($error = $this->http->FindSingleNode('//*[@color="#FFFFFF"]/../span[contains(@class, "MuiTypography-root")]/text()')) {
            $this->logger->error($error);

            if (
                strstr($error, "Invalid credentials. If you're already a member, you can set a new password.")
                || strstr($error, "Email or phone number is not found. Please contact call center.")
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $error;

            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        $dataRaw = $this->http->FindSingleNode('//pre[not(@id)]/text()');
        $memberData = $this->http->JsonLog($dataRaw);

        // Balance - Points
        $this->SetBalance($memberData->data->memberInformation->totalPoints);

        $firstName = $memberData->data->personalInformation->firstName ?? null;
        $surname = $memberData->data->personalInformation->surname ?? null;

        if (isset($firstName, $surname)) {
            // Name
            $this->SetProperty("Name", beautifulName("$firstName $surname"));
        }

        if (isset($memberData->data->memberInformation->joinDate)) {
            // Member since
            $this->SetProperty('MemberSince', strtotime($memberData->data->memberInformation->joinDate));
        }

        if (isset($memberData->data->memberInformation->membershipNumber)) {
            // Membership No
            $this->SetProperty("MembershipNo", $memberData->data->memberInformation->membershipNumber);
        }

        if (isset($memberData->data->memberInformation->tierLevel)) {
            // Tier Level
            $this->SetProperty("TierLevel", $memberData->data->memberInformation->tierLevel);
        }

        $pointList = $memberData->data->pointList ?? null;

        if (isset($pointList) && count($pointList) > 0) {
            foreach ($pointList as $pointsObject) {
                if ($pointsObject->pointType == "Miles") {
                    // level points Required for Next Tier
                    $this->SetProperty("PointsRequiredForNextTier", $pointsObject->nextTierPoints);

                    // Level points
                    $this->SetProperty("FlightPointsThisYear", $pointsObject->currentTierPoints);
                }

                if ($pointsObject->pointType == "Segments") {
                    // Segments Required for Next Tier
                    $this->SetProperty("SegmentsRequiredForNextTier", $pointsObject->nextTierPoints);

                    // Flights
                    $this->SetProperty("FlightSegmentsThisYear", $pointsObject->currentTierPoints);
                }
            }
        }
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($step == "otp") {
            return $this->processQuestion();
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://airastana.com/api/profile/member-details', [], 20);
        $this->http->RetryCount = 2;

        $dataRaw = $this->http->FindSingleNode('//pre[not(@id)]/text()');
        $memberData = $this->http->JsonLog($dataRaw);

        $email = $memberData->data->contactInformation->email ?? null;
        $membershipNumber = $memberData->data->memberInformation->membershipNumber ?? null;

        if (strtolower($this->AccountFields['Login']) == strtolower($membershipNumber) || strtolower($this->AccountFields['Login']) == strtolower($email)) {
            $this->logger->debug('membership number: ' . $membershipNumber);
            $this->logger->debug('email: ' . $email);
            $this->logger->debug('login: ' . $this->AccountFields['Login']);

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function processQuestion()
    {
        $this->logger->notice(__METHOD__);

        $questionField = $this->waitForElement(WebDriverBy::xpath('//div[contains(@test-id, "otp-information-text")]'), 0);

        $otpInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="securityCode"]'), 0);

        $otpSubmit = $this->waitForElement(WebDriverBy::xpath('//button[contains(@test-id, "otp-button") and not(@disabled)]'), 0);

        $this->saveResponse();

        if (!$questionField || !$otpInput || !$otpSubmit) {
            $this->logger->debug('failed to find otp form fields');

            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification("refs #23950 astana - account with mailbox // IZ");
        }

        $question = $questionField->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "otp");

            return false;
        }

        $otpInput->clear();
        $otpInput->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $this->logger->debug("Submit question");
        $otpSubmit->click();

        sleep(5);

        $this->saveResponse();

        $errorField = $this->waitForElement(WebDriverBy::xpath('//input[@name="securityCode"]/../../..//p/span'), 0);

        if ($errorField) {
            $error = $errorField->getText();
            $this->logger->error("[Error]: {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "otp");
            $this->DebugInfo = $error;

            return false;
        }

        return $this->loginSuccessful();
    }
}
