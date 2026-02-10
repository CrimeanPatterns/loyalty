<?php

class TAccountCheckerCapitalcards extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $seleniumURL;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] == '$') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public static function GetAccountChecker($accountInfo)
    {
        if ($accountInfo['Login'] == 'oauth' && $accountInfo['Pass'] == 'access_token') {
            return new \AwardWallet\Engine\capitalcards\APIChecker();
        } else {
//            return new static();
            require_once __DIR__ . "/TAccountCheckerCapitalcardsSelenium.php";

            return new TAccountCheckerCapitalcardsSelenium();
        }
//        if ($accountInfo['Login2'] == 'CA')
//            return new static();
//        else
//            return new \AwardWallet\Engine\capitalcards\APIChecker();
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

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->LogHeaders = true;
        $this->http->UseSSLv3();
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = [
            ""   => "Select region",
            "US" => "United States",
            "CA" => "Canada",
        ];

        $region = $fields["Login2"];
        unset($fields["Login2"]);
        $fields = array_merge(
            [
                "Login2"   => $region,
                "AuthInfo" => ["Type" => "oauth"],
            ],
            $fields
        );
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] != 'CA') {
            if (ArrayVal($this->AccountFields, 'Partner', 'awardwallet') == 'awardwallet') {
                throw new CheckException('Please edit this account to authenticate yourself via the "Connect with Capital One" button.', ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException('Unsupported provider');
            }
        }
//        $this->http->setCookie("ISSO_PAGE_IDT", "LPI", ".capitalone.com");
        $this->http->setCookie("ISSO_CNTRY_CODE", "CA", ".capitalone.com");
        $this->http->setCookie("locale_pref", "en_CA", ".capitalone.com");
        $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/Summary.aspx?LinkId=EOS_Z_Z_Z_EOSNAV_H1_01_G_NACT");

        return $this->http->FindPreg("/Accounts Summary/ims") !== null;
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login2'] != 'CA') {
            if (ArrayVal($this->AccountFields, 'Partner', 'awardwallet') == 'awardwallet') {
                throw new CheckException('Please edit this account to authenticate yourself via the "Connect with Capital One" button.', ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException('Unsupported provider');
            }
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://servicing.capitalone.com/c1/login.aspx?CountryCode=CA");

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        $this->http->GetURL("https://verified.capitalone.com/sic-ui/html/signin/partials/loginCanada.html");
//        $this->http->setCookie("ISSO_PAGE_IDT", "LPI", ".capitalone.com");
        $this->http->setCookie("ISSO_CNTRY_CODE", "CA", ".capitalone.com");
        $this->http->setCookie("locale_pref", "en_CA", ".capitalone.com");

        if (!$this->http->ParseForm("userLogin")) {
            return $this->checkErrors();
        }

        $this->selenium();

        //		$this->http->SetInputValue("user", $this->AccountFields['Login']);
        //		$this->http->SetInputValue("username", $this->AccountFields['Login']);
        //		$this->http->SetInputValue("password", $this->AccountFields['Pass']);
//        unset($this->http->Form["remember"]);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/The System is unavailable or has returned an unrecognized response.\s*Please try again later.\s*We apologize for the inconvenience./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error 404--Not Found
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our card servicing site is currently unavailable
        if ($redirect = $this->http->FindPreg("/var targetURL = \"([^\"]+)/ims")) {
            $this->http->GetURL($redirect);

            if ($message = $this->http->FindPreg("/(Our card servicing site is currently unavailable\.\s*We apologize for the inconvenience and suggest that you check back later today to view your account information\.)/ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function Login()
    {
        //		if (!$this->http->PostForm())
//            return false;

        if ($message = $this->http->FindSingleNode("//div[@id='errormsg']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->currentUrl() == 'https://login.capitalone.com/loginweb/login/invalidPassword.do'
            || strstr($this->http->currentUrl(), '.capitalone.com/loginweb/login/invalidCredential.do')) {
            throw new CheckException("The information you entered doesn't match what we have on file. Please check the information you entered.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->handleRedirect();

        if ($message = $this->http->FindSingleNode("//div[@id='SERVERSIDEERROR']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[@class='instructionsContainer']")) {
            if (preg_match("/Please try again later/", $message)) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
        //# Needed to create Security Answers
        if ($this->http->FindSingleNode("//div[contains(text(), 'our system experienced an error while processing your request')]")
            // New card
            || $this->http->FindSingleNode("//font[contains(text(), 'Verify you have received your credit card ending in')]")
            // I don't want to set alerts
            || $this->http->FindSingleNode('//a[contains(text(), "I don\'t want to set alerts")]')
            // Don't Set Up AutoPay
            || $this->http->FindSingleNode('//a[contains(text(), "Don\'t Set Up AutoPay")]')
            // For your security, let's create a new password
            || $this->http->FindSingleNode('//td[contains(text(), "Enter your new password here and click Change Password to access your account online.")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "For your security, let\'s create a new password")]')) {
            $this->throwProfileUpdateMessageException();
        }
        /*
         * You need to have your credit card in-hand before logging in for the first time.
         * You should receive your card within 7-10 days of approval.
         * If it has been more than 10 days and you haven't received your card,
         * please call 1-800-955-7070. (Ref. No. 10132.8084)
         */
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'You need to have your credit card in-hand before logging in for the first time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Manage Your Accounts Together Online!
        if ($this->http->FindPreg("/To complete this transition,<\/strong>\s*please accept the\s*terms and conditions below\.\s*The final step allows you to set up\s*paperless preferences for your recently moved account\(s\)\./ims")) {
            $this->throwAcceptTermsMessageException();
        }

        $this->logger->debug("[Selenium URL]: {$this->seleniumURL}");

        if ($this->seleniumURL != $this->http->currentUrl()) {
            $this->http->GetURL($this->seleniumURL);
        }

        if ($this->ParseQuestion()) {
            return false;
        }

        if ($this->http->FindPreg("/your account has been locked for your security/ims") !== null) {
            throw new CheckException("We're sorry but due to multiple failed verification attempts, your account has been locked for your security. To unlock your account or for additional support, please call 1-866-750-0873", ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindPreg("/Your Account Is Locked/ims") !== null) {
            throw new CheckException("Your Account Is Locked. Please go to our Forgot Password or Forgot User Name screens to reset your login information; then log in to access your account(s)", ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindPreg("/We have a temporary issue with our system/ims") !== null) {
            throw new CheckException("We have a temporary issue with our system. Close this browser window and try logging in from a new window. We're sorry for the inconvenience. (Ref. No. 10048.8009)", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/We have a temporary issue with our system/ims") !== null) {
            throw new CheckException("We have a temporary issue with our system. Close this browser window and try logging in from a new window. We're sorry for the inconvenience. (Ref. No. 10048.8009)", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//input[@name = 'LoginIntercept:btnRemindMeLater']") !== null
        && $this->http->ParseForm("MAINFORM")) {
            $this->http->Form["LoginIntercept:btnRemindMeLater"] = 'Remind Me Later';
            $this->http->PostForm();
        }

        $this->CheckError($this->http->FindSingleNode("//font[contains(text(), 'Verify you have received your credit card')]"), ACCOUNT_PROVIDER_ERROR);
        $this->CheckError($this->http->FindSingleNode("//div[contains(text(), 'Your access to this site is currently restricted')]"), ACCOUNT_PROVIDER_ERROR);

        // Setup security questions
        if ($this->http->FindSingleNode("//td[contains(text(), 'For your protection, we may ask you these security questions to verify your identity')]")) {
            $this->throwProfileUpdateMessageException();
        }
        /*
         * Our site is currently unavailable.
         * We apologize for the inconvenience and suggest that you check back later today to view
         * your account information.
         */
        if ($message = $this->http->FindPreg("/(Our site is currently unavailable\.\s*We apologize for the inconvenience and suggest that you check back later today to view your account information\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Application page requested was not found
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Application page requested was not found')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Server Under Maintance Pls try after an hour
        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Server Under Maintance Pls try after an hour')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We are sorry but the application that you are trying to access is unavailable at this time.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are sorry but the application that you are trying to access is unavailable at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // force switch to API (Challenge App One Time Pin Authentication)
        if ($this->http->FindSingleNode("//title[contains(text(), 'Challenge App One Time Pin Authentication')]")
            && $this->http->currentUrl() == 'https://verified.capitalone.com/challenge.html#/') {
            throw new CheckException('Authorization required', ACCOUNT_INVALID_PASSWORD);
        }

        // provider error
        if (strstr($this->http->currentUrl(), 'capitalone.com/ui/#/cvv')) {
            throw new CheckException("We're unable to service your account at this time.", ACCOUNT_PROVIDER_ERROR);
        }
        // Enjoy the convenience of electronic statements.
        if (strstr($this->http->currentUrl(), 'campaigns/viewloginoffer')) {
            $this->logger->notice(">>> Offer");

            if ($this->http->FindPreg("/Remind Me Later/")) {
                $this->logger->notice("Skip offer");
                $url = "/accounts";
                $this->http->NormalizeURL($url);
                $this->http->GetURL($url);
            } elseif (strstr($this->http->currentUrl(), 'capitalone.com/campaigns/viewloginoffer?targetUrl=')) {
                $this->throwProfileUpdateMessageException();
            }
        }// if (strstr($this->http->currentUrl(), 'campaigns/viewloginoffer'))

        return true;
    }

    public function ParseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        if ($questionLink = $this->http->FindSingleNode('//a[contains(@href, "getSecurityQuestionPA.do")]/@href')) {
            $this->logger->notice("new question view");
            $this->http->NormalizeURL($questionLink);
            $this->http->GetURL($questionLink);
        }
        $question = $this->http->FindSingleNode("//label[@for = 'MFAanswer' or @for = 'sec-question']");

        if ($this->http->ParseForm("loginSecurityQuestions") && $question) {
            $this->logger->debug("Found question: " . $question);
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Question = $question;
            $this->Step = "Question";

            return true;
        }// if ($this->http->ParseForm("loginSecurityQuestions") && $question)

        if ($this->http->FindPreg("/please provide the information below so that we can verify your identity/ims") !== null) {
//            $question = $this->http->FindSingleNode("//td[contains(text(), 'What is your date of birth?')]");
            $question = $this->http->FindSingleNode("//td[@class = 'literalalternateChallenge']");

            if (!isset($question)) {
                $this->logger->debug("no question");

                return false;
            }
            // refs #6838
            if (CleanXMLValue($question) == 'What is your date of birth?') {
                $question = 'What is your date of birth? (mm/dd/yyyy)';
            }
            $this->logger->debug("Found question: " . $question);
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Question = $question;
            $this->Step = "Question";

            return true;
        }// if ($this->http->FindPreg("/please provide the information below so that we can verify your identity/ims") !== null)

        if ($message = $this->http->FindPreg("/The system is unavailable or returned an unrecognized response[^<]+/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // One-time code
        if ($this->http->FindPreg("/(?:For your security, choose a way to receive a one-time code|ll send you a temporary code to your chosen contact point\.|<title>Capital One – Multi-factor Authentication Request<\/title>)/")
            && strstr($this->http->currentUrl(), 'https://verified.capitalone.com/challenge.html#/')) {
            $this->logger->notice("Choose a way to receive a one-time code...");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->http->setDefaultHeader("statusCheckMethod", "EVENT");
            $this->http->GetURL("https://verified.capitalone.com/challengeapp-server-web/challenge/method/getChallengeMethod?client=SICAPP&channelType=WEB");
            $response = $this->http->JsonLog();
            // security questions
            if (isset($response->sqResponse->question)) {
                $this->logger->notice("Just question");

                $this->State['QuestionId'] = $response->sqResponse->questionId;

                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Question = $response->sqResponse->question;
                $this->Step = "Question";

                return true;
            }// if (isset($response->sqResponse->question))
            // One Time COde
            elseif (isset($response->methodResponse->emailContactEntries[0])) {
                $email = $response->methodResponse->emailContactEntries[0]->emailId;
                $question = "Please enter Identification Code which was sent to the following email address: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

                $data = [
                    "contactPointType" => "EMAIL",
                    "id"               => $response->methodResponse->emailContactEntries[0]->id,
                ];
                $headers = [
                    "Accept"       => "application/json",
                    "Content-Type" => "application/json;charset=utf-8",
                ];
                $this->http->PostURL("https://verified.capitalone.com/challengeapp-server-web/challenge/otp/sendPin/",
                    json_encode($data), $headers);
                $this->http->JsonLog();

                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Question = $question;
                $this->Step = "Question";

                return true;
            }// if (isset($response->methodResponse->emailContactEntries[0]))
            elseif (isset($response->methodResponse->smsContactEntries[0])) {
                $email = $response->methodResponse->smsContactEntries[0]->smsNumber;
                $question = "Please enter Identification Code which was sent to the following phone number: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

                $data = [
                    "contactPointType" => "sms",
                    "id"               => $response->methodResponse->smsContactEntries[0]->id,
                ];
                $headers = [
                    "Accept"       => "application/json",
                    "Content-Type" => "application/json;charset=utf-8",
                ];
                $this->http->PostURL("https://verified.capitalone.com/challengeapp-server-web/challenge/otp/sendPin/",
                    json_encode($data), $headers);
                $this->http->JsonLog();

                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Question = $question;
                $this->Step = "Question";

                return true;
            }// if (isset($response->methodResponse->emailContactEntries[0]))
            /*
             * We noticed something different about this sign in
             *
             * To provide you with the best protection, choose a 2-Step Verification method to verifty your identity.
             *
             * AccountID: 1767302
             */
            elseif (
                isset($response->methodResponse->emailContactEntries, $response->methodResponse->voiceContactEntries, $response->methodResponse->smsContactEntries, $response->methodResponse->challengeMethods)
                && $response->methodResponse->challengeMethods == 'OTP'
                && empty($response->methodResponse->smsContactEntries)
                && empty($response->methodResponse->emailContactEntries)
                && empty($response->methodResponse->voiceContactEntries)
            ) {
                throw new CheckException("Unfortunately, you have no Verification methods in your profile to verify your identity", ACCOUNT_PROVIDER_ERROR); /*review*/
            }
        }// if ($this->http->FindPreg("/For your security, choose a way to receive a one-time code/")...

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];

        if (strstr($this->Question, 'Please enter Identification Code which was sent to the following ')) {
            $this->logger->notice("Submit My Code...");
            $data = [
                "pin" => $answer,
            ];
            $headers = [
                "Accept"       => "application/json",
                "Content-Type" => "application/json;charset=utf-8",
            ];
            $this->http->PostURL("https://verified.capitalone.com/challengeapp-server-web/challenge/otp/validatePin",
                json_encode($data), $headers);
            $response = $this->http->JsonLog();
            // https://verified.capitalone.com/challenge/js/app-479f7b4d54.min.js
            /*
             * PinService.validatePin(pin).then(function(data, status) {
                if (data.pinAuthentication.profileStatus == 'UNLOCKED') {
                    if (data.pinAuthentication.acceptanceStatus == 'ACCEPTED') {
                        $scope.displayAuthPinPage = false;
                        $state.go('success');
                    } else if (data.pinAuthentication.acceptanceStatus == 'EXPIRED') {
                        $scope.isDisabled = false;
                        $scope.pinForm.securityPin.$error.sessionExpired = true;
                        $scope.pinForm.securityPin.$error.required = false;
                        $window.document.getElementById("pin").focus();
                        $scope.errorId = "authE3";
                        SiteCatalystService.trackInlineError('expired code');
                    } else if (data.pinAuthentication.acceptanceStatus == 'REJECTED') {
                        $scope.isDisabled = false;
                        $scope.errorId = "authE4";
                        $scope.pinForm.securityPin.$error.required = true;
                        $scope.pinForm.securityPin.$error.sessionExpired = false;
                        $window.document.getElementById("pin").focus();
                        SiteCatalystService.trackInlineError('invalid code');
                    }
                } else if (data.pinAuthentication.profileStatus == 'LOCKED') {
                    $sessionStorage.$reset();
                    $window.location.href = '/error.html#/lockOut';
                } else {
                    $scope.displayAuthPinPage = false;
                    $sessionStorage.$reset();
                    $window.location.href = '/error.html#/error';
                }
            }, function(error) {
                $scope.displayAuthPinPage = false;
                $sessionStorage.$reset();
                $window.location.href = '/error.html#/error';
            });
             */
            if (isset($response->pinAuthentication->acceptanceStatus) && $response->pinAuthentication->acceptanceStatus == 'REJECTED') {
                // Looks like the code you entered is invalid, Please try again.
                $this->AskQuestion($this->Question, "Looks like the code you entered is invalid, Please try again.");

                return false;
            }// if (isset($response->acceptanceStatus) && $response->acceptanceStatus == 'REJECTED')
            else {
                $this->logger->notice("success");
            }
            unset($this->Answers[$this->Question]);
//            $this->sendNotification("capitalcards (Canada). code was entered");

//            $this->http->GetURL("https://verified.capitalone.com/challenge/otp/view/success.html");
            $this->http->GetURL("https://verified.capitalone.com/challengeapp-server-web/challenge/pathfinder");
            $response = $this->http->JsonLog();

            if (isset($response->landingPageUrl)) {
                $this->http->GetURL($response->landingPageUrl);
            }

            $this->http->GetURL("https://services.capitalone.com/accounts/?initial_login=true");
        }// if (strstr($answer, 'Please enter Identification Code which was sent to the following '))
        elseif (isset($this->State['QuestionId'])) {
            $this->logger->notice("Submit Answer...");

            $data = [
                "answer"     => $answer,
                "questionId" => $answer,
            ];
            $headers = [
                "Accept"       => "application/json",
                "Content-Type" => "application/json;charset=utf-8",
            ];
            $this->http->PostURL("https://verified.capitalone.com/challengeapp-server-web/challenge/sq/validateAnswer",
                json_encode($data), $headers);
            $response = $this->http->JsonLog();
            // The information you entered doesn't match what we have on file. Please try again.
            if (isset($response->validationStatus) && $response->validationStatus == 'FAILURE') {
                $this->AskQuestion($this->Question, "The information you entered doesn't match what we have on file. Please try again.");

                return false;
            }// if (isset($response->validationStatus) && $response->validationStatus == 'FAILURE')
            else {
                $this->logger->notice("success");
            }

            $this->http->GetURL("https://verified.capitalone.com/challengeapp-server-web/challenge/pathfinder");
            $response = $this->http->JsonLog();

            if (isset($response->landingPageUrl)) {
                $this->http->GetURL($response->landingPageUrl);
            }

            $this->http->GetURL("https://services.capitalone.com/accounts/?initial_login=true");
        }// elseif (isset($this->State['Question']) && $this->State['Question'] == 'New')
        else {
            $this->logger->notice("Just question");
            $this->http->Form["txtAnswer1_TLNPI"] = '**************************************************';

            if ($this->Question == 'What is your date of birth? (mm/dd/yyyy)') {
                $date = explode('/', $answer);
                // refs #6838
                if (!isset($date[2])) {
                    $this->AskQuestion('What is your date of birth? (mm/dd/yyyy)');
                }
                $this->http->Form['ctlDateOfBirth$ddlMonth'] = $date[0];
                $this->http->Form['ctlDateOfBirth$ddlDay'] = $date[1];
                $this->http->Form['ctlDateOfBirth$ddlYear'] = $date[2];
            } else {
                $this->http->Form["mfaAnswer_TLNPI"] = $answer;
            }
            $this->http->PostForm();

            if ($this->ParseQuestion()) {
                $error = $this->http->FindSingleNode("//div[@id='serverrdiv' and contains(text(), 'The information you entered doesn')]");

                if (isset($error)) {
                    $this->ErrorMessage = $error;
                }

                return false;
            }// if ($this->ParseQuestion())

            if ($this->http->FindPreg("/your account has been locked for your security/ims") !== null) {
                throw new CheckException("We're sorry but due to multiple failed verification attempts, your account has been locked for your security. To unlock your account or for additional support, please call 1-866-750-0873", ACCOUNT_LOCKOUT);
            }
        }
        $this->handleRedirect();

        return true;
    }

    public function Parse()
    {
        $this->exportToEditThisCookies();
        $this->sendNotification("capitalcards - refs #16903. Account was checked");

        $this->logger->notice(__METHOD__);
        $this->logger->debug(">> Emulating redirect...");

        if (strstr($this->http->currentUrl(), 'capitalone.com/ui/#/accounts/rewards')) {
//            $this->http->GetURL("https://services.capitalone.com/accounts?initial_Login=true");
//            if (strstr($this->http->currentUrl(), 'viewLoginoffer')) {
//                $this->http->Log(">> Skip offer");
            $goToAccount = "/accounts";
            $this->http->NormalizeURL($goToAccount);
            $this->http->GetURL($goToAccount);
//            }// if (strstr($this->http->currentUrl(), 'viewLoginoffer'))
        }// if (strstr($this->http->currentUrl(), 'capitalone.com/ui/#/accounts/rewards'))
        elseif ($this->http->FindSingleNode("//h3[contains(text(), 'Which account are you trying to access today?')]")
            && $capitalOneBank = $this->http->FindSingleNode("//a[contains(text(), 'CAPITAL ONE BANK')]/@href")) {
            $this->logger->notice(">> Redirect to CAPITAL ONE BANK...");
            $this->http->GetURL($capitalOneBank);
            $rows = $this->http->XPath->query("//table[@class = 'loc_accts']//tr[td]");
            $this->logger->debug("rows: " . $rows->length);

            for ($n = 0; $n < $rows->length; $n++) {
                $this->logger->debug("row: " . $n);
                $row = $rows->item($n);
                $title = $this->http->FindSingleNode("td[1]", $row);
                $code = $this->http->FindSingleNode("td[1]", $row, false, "/([\d]+)/ims");

                if (isset($title) && isset($code)) {
                    $this->logger->debug("code: $code, title: $title");
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                    $this->AddDetectedCard([
                        "Code"            => 'capitalcards' . $code,
                        "DisplayName"     => $title,
                        "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                    ]);
                }// if (isset($title) && isset($balance) && isset($code))
            }// for ($n = 0; $n < $rows->length; $n++)
        }
        // old
        elseif ($this->http->currentUrl() != 'https://servicing.capitalone.com/C1/Accounts/RewardsPoints.aspx'
                && !strstr($this->http->currentUrl(), 'capitalone.com/accounts')
                && !strstr($this->http->currentUrl(), '#/error')) {
            $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/Summary.aspx?LinkId=EOS_Z_Z_Z_EOSNAV_H1_01_G_NACT");
        }

        // Let's validate your online account.
        if (strstr($this->http->currentUrl(), '.capitalone.com/ui/#/cvv')) {
            $this->throwProfileUpdateMessageException();
        }

        $subAccounts = [];
        $rows = $this->http->XPath->query("//div[contains(@id, 'AccountDashboard')]");
        $this->logger->debug("rows 1: " . $rows->length);
        // if already Logged in
        for ($n = 0; $n < $rows->length; $n++) {
            $this->logger->debug("row: " . $n);
            $row = $rows->item($n);
            $title = $this->http->FindSingleNode(".//div[contains(@id, 'DisplayArea')]", $row);
            $balance = $this->http->FindSingleNode(".//div[contains(@id, 'Reward')]", $row, false);
            $currency = null;

            if (isset($balance)) {
                if (preg_match('/Rewards:\s*(\$?)([-\$?\d\,\.]+)/ims', $balance, $matches)) {
                    $balance = str_replace('$', '', $matches[2]);
                    $currency = $matches[1];

                    if (empty($currency) && strstr($matches[2], '$')) {
                        $currency = '$';
                    }
                } else {
                    $balance = null;
                }
            }
            $code = $this->http->FindSingleNode(".//span[contains(@id, 'AccountNumber')]", $row);

            if (isset($title) && !isset($balance) && isset($code)) {
                // if account is currently closed
                if ($this->http->FindSingleNode(".//span[contains(@id, 'AccountStatusMessage')]", $row, false, '/This account is currently closed/ims')) {
                    $cardDescription = C_CARD_DESC_CLOSED;
                } else {
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                }
                $this->AddDetectedCard([
                    "Code"            => 'capitalcards' . $code,
                    "DisplayName"     => $title,
                    "CardDescription" => $cardDescription,
                ]);
            }

            if (isset($title) && isset($balance) && isset($code)) {
                $this->logger->debug("code: $code, title: $title, balance: $balance");
                // if account is currently closed
                if ($this->http->FindSingleNode(".//span[contains(@id, 'AccountStatusMessage')]", $row, false, '/This account is currently closed/ims')) {
                    $cardDescription = C_CARD_DESC_CLOSED;
                } else {
                    $cardDescription = C_CARD_DESC_ACTIVE;
                    $subAccounts[$code] = [
                        "Code"        => 'capitalcards' . $code,
                        "DisplayName" => $title,
                        "Balance"     => $balance,
                    ];

                    if (isset($currency)) {
                        $subAccounts[$code]['Currency'] = $currency;
                    }
                }
                $this->AddDetectedCard([
                    "Code"            => 'capitalcards' . $code,
                    "DisplayName"     => $title,
                    "CardDescription" => $cardDescription,
                ]);
            }
        }

        // https://servicing.capitalone.com/C1/Accounts/RewardsPoints.aspx
        if (count($subAccounts) == 0) {
            $balanceLinks = [];
            $rows = $this->http->XPath->query("//table[contains(@class, 'dataTable')]/tr[td[contains(text(), '....')]]");
            $this->logger->debug("rows 2: " . $rows->length);

            for ($n = 0; $n < $rows->length; $n++) {
                $this->logger->debug("row: " . $n);
                $row = $rows->item($n);
                $title = $this->http->FindSingleNode("td[1]", $row);
                $code = $this->http->FindSingleNode("td[1]", $row, false, "/([\d]+)/ims");
                $balance = $this->http->FindSingleNode("td[2]", $row, false, "/([\-\d\,\.]+)/ims");
                // Rewards balance is temporarily unavailable. Please try again later.
                if (!isset($balance) && $this->http->FindSingleNode("td[2]", $row) == 'Temporarily Unavailable') {
                    throw new CheckException("Rewards balance is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                }/*review*/

                if (isset($title) && isset($balance) && isset($code)) {
                    $balance = preg_replace("/[^-\d\.]/ims", "", $balance);
                    $this->logger->debug("code: $code, title: $title, balance: $balance");
                    $currency = $this->http->FindSingleNode("td[2]", $row);
                    $subAccounts[] = [
                        "Code"        => 'capitalcards' . $code,
                        "DisplayName" => $title,
                        "Balance"     => $balance,
                        'Currency'    => (strstr($currency, '$')) ? '$' : '',
                    ];
                    $this->AddDetectedCard([
                        "Code"            => 'capitalcards' . $code,
                        "DisplayName"     => $title,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ]);
                    $link = $this->http->FindSingleNode(".//a[contains(@onclick, 'RewardsHandler.ashx')]/@onclick", $row, false, "/PopupMaritz\('([^']+)'/ims");

                    if (isset($link)) {
                        $this->logger->debug("link found: $link");
                        $this->http->NormalizeURL($link);
                        $balanceLinks[$code] = $link;
                    }
                }// if (isset($title) && isset($balance) && isset($code))
                else {
                    $this->sendNotification("capitalcards. Page 2");
                }
            }// for ($n = 0; $n < $rows->length; $n++)
        }// if (count($subAccounts) == 0)

        if (count($subAccounts) == 0) {
            $balanceLinks = [];
            $rows = $this->http->XPath->query("//div[contains(@class, 'card_sum')]");
            $this->logger->debug("rows 3: " . $rows->length);

            for ($n = 0; $n < $rows->length; $n++) {
                $this->logger->debug("row: " . $n);
                $row = $rows->item($n);
                $title = $this->http->FindSingleNode(".//div[@class = 'nickname']", $row);
                $code = $this->http->FindSingleNode(".//span[contains(@id, '_number')]", $row, false, "/([\d]+)/ims");
                $balance = $this->http->FindSingleNode("div[@class = 'left_sum']/div[@class = 'rewards' and not(contains(@id, 'urchaseLevelFinancing'))]", $row, false, "/([\-\d\,\.]+)/ims");
                $closed = $this->http->FindSingleNode("div[@class = 'left_sum']", $row, false, "/(This account is currently closed.)/ims");

                if (isset($title) && isset($balance) && isset($code) && !$closed) {
                    $balance = preg_replace("/[^-\d\.]/ims", "", $balance);
                    $this->logger->debug("code: $code, title: $title, balance: $balance");
                    $currency = $this->http->FindSingleNode("div[@class = 'left_sum']/div[@class = 'rewards' and not(contains(@id, 'urchaseLevelFinancing'))]", $row);
                    $subAccounts[] = [
                        "Code"        => 'capitalcards' . $code,
                        "DisplayName" => $title,
                        "Balance"     => $balance,
                        'Currency'    => (strstr($currency, '$')) ? '$' : '',
                    ];
                    $this->AddDetectedCard([
                        "Code"            => 'capitalcards' . $code,
                        "DisplayName"     => $title,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ]);
                }// if (isset($title) && isset($balance) && isset($code))
                else {
                    // account is currently closed
                    if (isset($title, $code)) {
                        if ($closed) {
                            $this->AddDetectedCard([
                                "Code"            => 'capitalcards' . $code,
                                "DisplayName"     => $title,
                                "CardDescription" => C_CARD_DESC_CLOSED,
                            ]);
                        } else {
                            $this->AddDetectedCard([
                                "Code"            => 'capitalcards' . $code,
                                "DisplayName"     => $title,
                                "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                            ]);
                        }
                    }// if (isset($title, $code))
                }
            }// for ($n = 0; $n < $rows->length; $n++)
        }// if (count($subAccounts) == 0)

        if (count($subAccounts) == 0) {
            $balanceLinks = [];
            $this->http->FilterHTML = false;
            $rows = $this->http->XPath->query("//table[@id = 'per_accts']//tr[td and not(contains(@class, 'last'))]");
            $this->logger->debug("version 4: " . $rows->length);

            for ($n = 0; $n < $rows->length; $n++) {
                $this->logger->debug("row: " . $n);
                $row = $rows->item($n);
                $title = $this->http->FindSingleNode('td[1]//a[contains(@class, "link_spinner") and contains(@id, "accountNameLink")]', $row);
                $title2 = $this->http->FindSingleNode('td[1]//span[contains(@class, "act_num_display_inline")]', $row);
                $title .= $title2;
                $code = $this->http->FindSingleNode('td[1]//span[contains(@class, "act_num_display_inline")]', $row, false, "/([\d]+)/ims");

                $closed = $this->http->FindSingleNode('.//span[contains(text(), "This account is closed")]', $row, false, "/(This account is closed)/ims");
                $this->logger->debug("code: $code, title: $title");

                unset($balance);

                if ($code && !$closed && $this->http->ParseForm(null, 1, true, "//span[contains(@class, 'act_num_display_inline') and contains(text(), '{$title2}')]/following-sibling::form[@name = 'redeemRewardsForm']")) {
                    $http2 = clone $this->http;
                    $this->http->brotherBrowser($http2);
                    $http2->Form = $this->http->Form;
                    $http2->FormURL = $this->http->FormURL;
                    $http2->PostForm();

                    $balance = $http2->FindSingleNode("//span[contains(@class, 'legend')]", null, false, "/([\-\d\,\.]+)/ims");
                }// if (!$closed && isset($forms[$n]))
                else {
                    $this->logger->notice("Form not found, see next node");
                }

                if (isset($title) && isset($balance) && isset($code) && !$closed) {
                    $balance = preg_replace("/[^-\d\.]/ims", "", $balance);
                    $this->logger->debug("code: $code, title: $title, balance: $balance");
                    $currency = $this->http->FindSingleNode("div[@class = 'left_sum']/div[@class = 'rewards']", $row);
                    $subAccounts[] = [
                        "Code"        => 'capitalcards' . $code,
                        "DisplayName" => $title,
                        "Balance"     => $balance,
                        'Currency'    => (strstr($currency, '$')) ? '$' : '',
                    ];
                    $this->AddDetectedCard([
                        "Code"            => 'capitalcards' . $code,
                        "DisplayName"     => $title,
                        "CardDescription" => C_CARD_DESC_ACTIVE,
                    ]);
                }// if (isset($title) && isset($balance) && isset($code))
                else {
                    // account is currently closed
                    if (isset($title, $code)) {
                        if ($closed) {
                            $this->AddDetectedCard([
                                "Code"            => 'capitalcards' . $code,
                                "DisplayName"     => $title,
                                "CardDescription" => C_CARD_DESC_CLOSED,
                            ]);
                        } else {
                            $this->AddDetectedCard([
                                "Code"            => 'capitalcards' . $code,
                                "DisplayName"     => $title,
                                "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                            ]);
                        }
                    }// if (isset($title, $code))
                }
            }// for ($n = 0; $n < $rows->length; $n++)
        }// if (count($subAccounts) == 0)

        if (!empty($subAccounts)
            || (isset($this->Properties['DetectedCards']) && count($this->Properties['DetectedCards']) > 0)) {
            $this->SetBalanceNA();
            $this->Properties["SubAccounts"] = $subAccounts;
        }
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//a[@id = 'ancUserName']/text()[1]")));

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//a[@id = 'account-preferences-link']/text()[1]")));
        }

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@id = 'section_header_customer_name']", null, true, "/Welcome\s*([^<]+)/ims")));
        }
        // check account status on the page "Summary" (Menu: Accounts -> Summary)
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->Log("Try catch errors");
            // This account is closed
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'This account is closed')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($this->http->currentUrl(), '#/error')) {
                $this->logger->notice("New design");

                if (preg_match("/result\/(\d+)/ims", $this->http->currentUrl(), $code)) {
                    // Global error
                    preg_match("/error\/(\d+)/ims", $this->http->currentUrl(), $errorCode);
                    $this->http->GetURL("https://services1.capitalone.com/ui/error/error.json");

                    if ($this->http->Response['code'] == 404) {
                        $this->http->GetURL("https://services1.capitalone.com/ui/sharedError/error.json");
                    }

                    $message = $this->http->FindPreg("/{$code[1]}\"\:\s*\{\s*\"id\"\:\s*\"[^\"]+\"\,\s*\"title\"\:\s*[^\"]+\"[^\"]+\"\,\s*\"message\"\:\s*\{\s*\"text\"\:\s*\"([^\"]+)/ims");

                    if (empty($message) && isset($errorCode[1])) {
                        $message = $this->http->FindPreg("/{\s*\"id\"\:\s*\"{$errorCode[1]}\"\,\s*\"title\"\:\s*[^\"]+\"[^\"]+\"\,\s*\"message\"\:\s*\{\s*\"text\"\:\s*\"([^\"]+)/ims");
                    }
                    $this->logger->error("find -> $message");

                    if ($message) {
                        $this->http->GetURL("https://services1.capitalone.com/api/messages");

                        if ($message = $this->http->FindPreg("/{$message}\":\"([^\"\.]+\.?)/")) {
                            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                        }
                    }// if ($message)
                }// if (preg_match("/result/(\d+)", $this->http->currentUrl(), $code))
            }// if (strstr($this->http->currentUrl(), 'accounts#/error'))
            else {
                $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/AccountSummary.aspx?LinkId=EOS_Z_Z_Z_EOSNAV2_H2_01_G_SSUM");

                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'This account is currently closed.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // retries
                if (strstr($this->http->currentUrl(), 'https://servicing.capitalone.com/c1/Login.aspx?returnurl=')) {
                    throw new CheckRetryNeededException(2, 15);
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function selenium()
    {
        $checker2 = clone $this;
        $this->http->brotherBrowser($checker2->http);

        try {
            $this->logger->notice("Running Selenium...");
            $checker2->UseSelenium();
            $checker2->useChromium();
            $checker2->disableImages();
            $checker2->http->start();
//            $checker2->http->saveScreenshots = true;//todo: debug
            $checker2->Start();
            $checker2->http->GetURL("https://servicing.capitalone.com/c1/login.aspx?CountryCode=CA");

            $loginInput = $checker2->waitForElement(WebDriverBy::id('usernameForCA'), 10);

            if (!$loginInput && $checker2->waitForElement(WebDriverBy::id('username'), 0)) {
                $this->logger->notice("Switch country to Canada / English");
                $this->http->SetBody($checker2->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                $checker2->driver->manage()->addCookie(['name' => 'locale_pref', 'value' => 'en_CA', 'domain' => ".capitalone.com"]);
                $checker2->driver->manage()->addCookie(['name' => 'ISSO_CNTRY_CODE', 'value' => 'CA', 'domain' => ".capitalone.com"]);
                $checker2->http->GetURL("https://servicing.capitalone.com/c1/login.aspx?CountryCode=CA");
                $loginInput = $checker2->waitForElement(WebDriverBy::id('usernameForCA'), 10);
            }
            $passwordInput = $checker2->waitForElement(WebDriverBy::id('passwordForCA'), 0);

            $this->http->SetBody($checker2->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
//            $checker2->saveResponse();

            $button = $checker2->waitForElement(WebDriverBy::xpath("//button[@id = 'id-signin-submit-ca']"), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");
                // We are sorry but the application that you are trying to access is unavailable at this time.
                if ($checker2->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'We are sorry but the application that you are trying to access is unavailable at this time.')]"), 0)) {
                    throw new CheckException('We are sorry but the application that you are trying to access is unavailable at this time. Please try again later.', ACCOUNT_PROVIDER_ERROR);
                }

                if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    throw new CheckRetryNeededException(3);
                }

                return false;
            }// if (!$loginInput || !$passwordInput || !$button)
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $button->click();

            $checker2->waitForElement(WebDriverBy::xpath("//button[@id = 'id-signout-icon-text']"), 7);
            sleep(2);
            $this->http->SetBody($checker2->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
//            $checker2->saveResponse();
            // It looks like you might need a reminder for your user name and password.
            if ($error = $this->waitForElement(WebDriverBy::xpath("//p[span[contains(text(), 'It looks like you might need a reminder for your')]]"), 0)) {
                if ($message = $this->http->FindPreg('/It looks like you might need a reminder for your user name and password./', false, $error->getText())) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
            }
            // You have limited sign-in attempts. Your user name and/or password doesn't match what we have on file.
            if ($message = $checker2->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'You have limited sign-in attempts.')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            /*
             * You're running low on sign-in attempts.
             *
             * Choose Forgot User name or Password or try to sign in again.
             */
            if ($message = $checker2->waitForElement(WebDriverBy::xpath('//span[contains(text(), "You\'re running low on sign-in attempts.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            /*
             * We hit a snag
             *
             * Looks like we don't have the necessary contact info to complete a security step.
             * Give us a call and we'll get you back on track.
             */
            if ($message = $checker2->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Looks like we don\'t have the necessary contact info to complete a security step.")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Oop!
             * It looks like something went wrong, but we're working on it!
             */
            if ($message = $checker2->waitForElement(WebDriverBy::xpath('//span[contains(text(), "It looks like something went wrong, but we\'re working on it!")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * We hit a snag
             *
             * Looks like something went wrong, but we're working on it.
             * Give it another try in a bit
             */
            if ($message = $checker2->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Looks like something went wrong, but we\'re working on it. ")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            $this->http->SetBody($checker2->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
//            $checker2->saveResponse();

            $cookies = $checker2->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->seleniumURL = $checker2->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");

            return true;
        } finally {
            // close Selenium browser
            $checker2->http->cleanup();
        }
    }

    protected function exportToEditThisCookies()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("exportToEditThisCookies", ['Header' => 3]);
        $cookiesArr = [];
        $cookiesArrGeneral = [];
        $domains = [
            ".capitalone.com",
            "verified.capitalone.com",
            "myaccounts.capitalone.com",
        ];
        $cookies = [];

        foreach ($domains as $domain) {
            $cookies = array_merge($cookies, $this->http->GetCookies($domain), $this->http->GetCookies($domain, "/", true));
        }
        $i = 1;

        foreach ($cookies as $cookie => $val) {
            $c = [
                "domain"   => ".capitalone.com",
                //                "expirationDate" => 1494400127,
                "hostOnly" => false,
                "httpOnly" => false,
                "name"     => $cookie,
                "path"     => "/",
                "secure"   => false,
                "session"  => false,
                "storeId"  => "0",
                "value"    => $val,
                //                "id" => $i
            ];
            $cookiesArr[] = $c;
            $cg = "document.cookie=\"{$cookie}={$val}; path=/; domain=.capitalone.com\";";
            $cookiesArrGeneral[] = $cg;
            $i++;
        }// foreach ($cookies as $cookie)
        $this->logger->debug("==============================");
        $this->logger->debug(str_replace("\/", "/", json_encode($cookiesArr)));
        $this->logger->debug("==============================");
        $this->logger->debug("===============2==============");
        $this->logger->debug(var_export(implode(' ', $cookiesArrGeneral), true));
        $this->logger->debug("==============================");
    }

    private function handleRedirect()
    {
        $this->logger->notice(__METHOD__);
        $count = 0;

        while (($url = $this->http->FindPreg("/redirIfCookiePresent\(\"([^\"]+)\"/ims")) && $count < 3) {
            sleep(1);
            $this->logger->debug("redirIfCookiePresent:[$url]");
            $this->http->GetURL($url);
            $count++;
        }
    }

    /*
        function ParseFiles($filesStartDate){
            $this->http->TimeLimit = 500;
            $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/StatementsList.aspx");
            $accounts = [];
            foreach($this->http->XPath->query("//select[@name = 'cboAccountList']/option") as $option){
                /** @var \DOMNode $option * /
                $title = CleanXMLValue($option->nodeValue);
                if(preg_match('#\d{4}$#ims', $title, $matches))
                    $number = $matches[0];
                else
                    $number = null;
                $accounts[] = [
                    'index' => $option->attributes->getNamedItem('value')->nodeValue,
                    'title' => $title,
                    'number' => $number
                ];
            }
            $this->logger->debug("accounts: ".var_export($accounts, true));
            $result = [];
            foreach($accounts as $account){
                $this->logger->debug("loading account: ".var_export($account, true));
                $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/StatementsList.aspx?Type=Monthly&index=".$account['index']);
                if(empty($this->http->FindSingleNode("//select[@name = 'cboAccountList']/option[@value = '{$account['index']}']"))){
                    $this->http->Log("failed to select account");
                    continue;
                }

                $tabs = [];
                if($this->http->FindSingleNode("//a[contains(@id, 'lnkStmntsQuarterly')]"))
                    $tabs[] = 'Quarterly';
                if($this->http->FindSingleNode("//a[contains(@id, 'lnkStmntsYearly')]"))
                    $tabs[] = 'Yearly';

                $dateRanges = $this->http->FindNodes("//select[@name = 'ddldateRange']/option/@value");

                $this->parseFilesPage($filesStartDate, $account['number'], $account['title'], $result);

                if(count($dateRanges) > 1){
                    array_shift($dateRanges);
                    foreach($dateRanges as $range){
                        $this->logger->debug("loading date range $range");
                        $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/StatementsList.aspx?Type=Monthly&index=".$account['index']);
                        if($this->http->ParseForm("MAINFORM")){
                            $this->http->Form["__EVENTTARGET"] = "ddldateRange";
                            $this->http->Form["ddldateRange"] = $range;
                        }
                        $this->http->PostForm();
                        if(!empty($this->http->FindNodes("//select[@name = 'ddldateRange']/option[@value = '$range' and @selected = 'selected']"))){
                            $this->parseFilesPage($filesStartDate, $account['number'], $account['title'], $result);
                        }
                        else
                            $this->logger->debug("failed to load range");
                    }
                }

                foreach($tabs as $period){
                    $this->http->GetURL("https://servicing.capitalone.com/C1/Accounts/StatementsList.aspx?Type={$period}&index=".$account['index']);
                    $this->parseFilesPage($filesStartDate, $account['number'], $account['title'], $result);
                }
            }

            return $result;
        }

        function parseFilesPage($filesStartDate, $accountNumber, $accountName, array &$result){
            $rows = $this->http->XPath->query("//table[@id = 'ctlStatementList']//tr[contains(@class, 'tem')]");
            foreach($rows as $row){
                $date = strtotime($this->http->FindSingleNode("td[2]/span", $row));
                $title = $this->http->FindSingleNode("td[1]/span/b", $row);
                $link = $this->http->FindSingleNode("td[1]/span/span/a[contains(@href, 'javascript:PopupStatementsPDF')]/@href", $row, false, "#javascript:PopupStatementsPDF\('([^']+)'\)#ims");
                if(!empty($date) && !empty($title) && !empty($link) && $date >= $filesStartDate && !isset($result[$link])){
                    $this->logger->debug("found file: $date, $title, $link");
                    $fileName = $this->http->DownloadFile("https://servicing.capitalone.com/C1/Accounts/".$link);
                    if(strpos($this->http->Response['body'], '%PDF') === 0){
                        $result[$link] = [
                            'FileDate' => $date,
                            'Name' => $title,
                            'Extension' => 'pdf',
                            'AccountNumber' => $accountNumber,
                            'AccountName' => $accountName,
                            'AccountType' => 'Credit Card',
                            'Contents' => $fileName
                        ];
                    }
                }
            }
        }
        */
}
