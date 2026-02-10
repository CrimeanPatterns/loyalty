<?php

use AwardWallet\Common\Parsing\Html;

// TODO: Rewrite from timeouts to waitFor methods
class TAccountCheckerBingSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    // Variable to distinguish cases when security code check is set in account settings and when security code check is forced by provider (e.g. when something unusual happens)
    public $verify = false;

    public $debug = true;

    public $emailQuestion = 'Please enter your full email address '; // email tip will be received from provider
    public $securityCodeQuestionEmail = 'Please input security code which you should receive by email';
    public $securityCodeQuestionPhone = 'Please input security code which you should receive by phone';
    public $phoneQuestion = 'Please enter the last 4 digits of your phone number ';
    public $twoStepAuthQuestion = 'Please input code from your authentication app';
    public $accountBlockPhoneQuestion = 'Enter the security code we sent to your phone';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->InitSeleniumBrowser();
        $this->useGoogleChrome();
        $this->useCache();
        $this->http->saveScreenshots = true;
        $this->keepCookies(false);
    }

    public function LoadLoginForm()
    {
        // <FOR DEBUG ONLY>
        // This page allows to manually select region and don't get error 'Bing Rewards isn't available yet in your country or region.'
        //		$this->http->GetURL('http://www.bing.com/rewards/signin?landingAction=Web');
        //		sleep(15);
        // </FOR DEBUG ONLY>

        $this->logger->notice(__METHOD__);

        if (!$this->http->GetURL($this->startURL())) {
            $this->logger->error('Couldn\'t get login page');

            return false;
        }
        $this->saveResponse();

        $login = $this->waitForElement(WebDriverBy::name('loginfmt'), 20);

        if (!$login) {
            $this->logger->error('Failed to find login input');
            // Verify your identity, refs #16150, https://redmine.awardwallet.com/issues/16150#note-9
            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Verify your identity")] | //a[@id = "ftrLogout" and contains(text(), "Sign out")]'), 0)) {
                return true;
            }

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);

        try {
            $this->handlerClickButton('idSIButton9');
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $this->saveResponse();

        // Use your password instead
        if ($this->waitForElement(WebDriverBy::xpath('//div[
                (@id = "idDiv_SmallSpinnerText" or @id = "idDiv_RemoteNGC_PollingDescription") and contains(., "Tap the number you see below in your Microsoft Authenticator app to sign in with ")
                or contains(., "Tap the number you see below in your Microsoft Authenticator app to sign in.")
                or contains(., "Because you set up the Microsoft Authenticator app")
                or contains(., "In your Microsoft Authenticator app, tap the number here to sign in.")
                or contains(., "Sign in on GitHub")
                or contains(., "In your Authenticator app")
            ]
        '), 4)
            && ($usePassword = $this->waitForElement(WebDriverBy::xpath('//*[
                @id = "idA_PWD_SwitchToPassword"
                or @id = "idA_PWD_SwitchToCredPicker"
            ]'), 0))
//                or @id = "idA_PWD_SwitchToFido"
        ) {
            $this->saveResponse();
            $usePassword->click();
            $this->saveResponse();

            if ($usePassword2 = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Use my password")]'), 0)) {
                $usePassword2->click();
                $this->saveResponse();
            }
        }

        $passwordInput = $this->waitForElement(WebDriverBy::name('passwd'), 16);
        $this->saveResponse();

        if (!$passwordInput) {
            $this->logger->error('Failed to find password input');

            if ($question = $this->waitForElement(WebDriverBy::xpath("//*[self::span or self::div][contains(text(), 'Enter the code we sent to') or contains(text(), 'We emailed a code to')]"), 0)) {
                $this->holdSession();
                $this->AskQuestion($question->getText(), null, 'enteringSecurityCode');

                return false;
            }
            // Confirm your phone number
            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Confirm your phone number')]"), 0)) {
                throw new CheckException("Please use your Email or Skype username to login to Microsoft Rewards", ACCOUNT_PROVIDER_ERROR);
            }/*review*/

            if ($lostAuthenticator = $this->waitForElement(WebDriverBy::xpath('//a[@id = "idRemoteNGC_LostAuthenticator"]'), 0)) {
                $lostAuthenticator->click();
                sleep(3);
                $this->saveResponse();

                return true;
            }

            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Stay signed in?")]'), 0)) {
                $this->driver->executeScript('document.querySelector(\'input[name="DontShowAgain"]\').checked = true;');
                $this->saveResponse();
                $this->waitForElement(WebDriverBy::xpath('//input[@id = "idSIButton9"] | //button[@id = "acceptButton"]'), 0)->click();
                sleep(3);
                $this->saveResponse();

                return true;
            }

            if ($active = $this->waitForElement(WebDriverBy::xpath('//div[@aria-label="Active Directory"]'), 0)) {
                $active->click();
                sleep(3);
                $this->saveResponse();

                if ($passOption = $this->waitForElement(WebDriverBy::id('FormsAuthentication'), 3)) {
                    $passOption->click();
                    sleep(3);
                    $this->saveResponse();

                    $passwordInput = $this->waitForElement(WebDriverBy::name('Password'), 10);
                    $this->saveResponse();

                    if ($passwordInput) {
                        $passwordInput->sendKeys($this->AccountFields['Pass']);
                        $this->handlerClickButton('submitButton');
                    }

                    return true;
                }
            }

            if ($appApproving = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Approve a request on my Microsoft Authenticator app") or contains(text(), "Check your Authenticator app") or contains(text(), "Check your OutlookDev app") or contains(text(), "Check your Outlook app")]'), 0)) {
                $appApproving->click();
                sleep(3);
                $this->saveResponse();

                $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We sent a sign in request to your ") or contains(text(), "We sent a sign-in request to ")]'), 20);
                $this->saveResponse();

                if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We sent a sign-in request to ")]'), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                return true;
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We sent a sign-in request to ")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkUsernameError();
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // Server Error in '/' Application.
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error in \'/\' Application.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function handlerClickButton($id)
    {
        if (isset($id)) {
            try {
                $this->logger->debug('Click ID -> ' . $id);
                $button = $this->waitForElement(WebDriverBy::id($id), 10);

                if ($button) {
                    $button->click();
                    $this->driver->switchTo()->alert()->accept();
                } else {
                    $this->logger->notice("button not found");
                }
                sleep(5);
                $this->saveResponse();
            } catch (UnexpectedAlertOpenException $e) {
                $this->handleSecurityException($e);
            } catch (NoAlertOpenException | Facebook\WebDriver\Exception\NoSuchAlertException $e) {
                $this->logger->debug("no alert, skip");
            }
        } else {
            $this->logger->notice("Element {$id} not found");
        }
    }

    public function checkUsernameError()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        try {
            // Enter a valid email address, phone number, or Skype name.
            // That password is incorrect. Be sure you're using the password for your Microsoft account.
            // That Microsoft account doesn't exist. Enter a different account or ...
            // Your account or password is incorrect
            // Please enter a valid email address, phone number, or Skype name.
            // The account or password is incorrect. Please try again.
            if (
                $error = $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(@id, "Error") and contains(., "Enter a valid email address, phone number, or Skype name")]
                    | //div[contains(@id, "Error") and contains(., "That password is incorrect.")]
                    | //div[contains(@id, "Error") and contains(., "That Microsoft account doesn\'t exist. Enter a different account or")]
                    | //div[contains(@id, "Error") and contains(., "Your account or password is incorrect")]
                    | //div[contains(@id, "Error") and contains(., "Please enter a valid email address, phone number, or Skype name.")]
                    | //div[contains(@id, "Error") and contains(., "This username has been turned-off for sign in.")]
                    | //div[contains(@id, "Error") and contains(., "Signing in to this device requires a password on your account. Visit https:")]
                    | //div[contains(@id, "Error") and contains(., "The account or password is incorrect. Please try again.")]
                    | //span[@id = "errorText" and contains(., "Incorrect user ID or password.")]
                '), 0)
            ) {
                throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
            }
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $this->handlerClickButton('idSIButton9');
        // skip profile update
        if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Is your security info still accurate?')] | //h1[contains(text(), 'Is your security info still accurate?')]"), 3)) {
            $this->handlerClickButton('iRemindMeLater');
            $this->handlerClickButton('iLooksGood');
        }
        // skip app installation
        if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Break free from your passwords')]"), 0)) {
            $this->handlerClickButton('iCancel');
        }

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your data will be processed outside of your country or region")]'))) {
            $this->handlerClickButton('piplConsentContinue');
        }
        $this->saveResponse();

        // AccountID: 4998692
        if (
            $this->http->FindSingleNode('//div[contains(text(), "Stay signed in?")]')
            && ($btnYes = $this->waitForElement(WebDriverBy::xpath('//input[@id = "idSIButton9"]'), 0))
        ) {
            $this->driver->executeScript('document.querySelector(\'input[name="DontShowAgain"]\').checked = true;');
            $this->saveResponse();
            $btnYes->click();
        }

        $this->checkUsernameError();
        // Personal data export consent
        $consent = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Personal data export consent')]"), 0);

        if ($consent) {
            $this->throwAcceptTermsMessageException();
        }
        // Please enter your phone number or your email address in the format someone@example.com.
        // Your account or password is incorrect.
        if (
            $error = $this->waitForElement(WebDriverBy::xpath('
                //div[contains(text(), "Please enter your phone number or your email address in the format someone@example.com.")]
                | //div[@id = "passwordError" and contains(., "Your account or password is incorrect")] | //div[@id = "passwordError" and contains(., "Your account or password is incorrect.")] 
            '), 0)
        ) {
            throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // You've tried to sign in too many times with an incorrect account or password.
        // Sign-in is blocked
        if (
            $error = $this->waitForElement(WebDriverBy::xpath("
                //div[contains(text(), 've tried to sign in too many times with an incorrect account or password.')]
                | //div[contains(text(), 'Sign-in is blocked')]
            "), 0)
        ) {
            throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
        }
        // We're updating the terms and privacy statement for your account
        if ($this->http->FindPreg("/We're updating the terms and privacy statement for your account/ims")) {
            $this->handlerClickButton('iNext');
        }
        // It looks like someone else might be using your account. To help you and only you get back into your account, we need to verify that it's yours.
        if ($this->http->FindPreg('#It\s+looks\s+like\s+someone\s+else\s+might\s+be\s+using\s+your\s+account#i')) {
            $this->handlerClickButton('iNext');
            $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "How do you want to receive your security code?")]'), 5);
            $this->saveResponse();
        }

        if ($this->http->FindPreg('#Someone else has claimed ownership of#i')) {
            $this->handlerClickButton('landingAction');
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "secondaryEvictedAction"]'), 3);
            $this->handlerClickButton('secondaryEvictedAction');
            $this->saveResponse();
        }
        // Your security info change is still pending
        if ($this->http->FindPreg("/Your security info change is still pending/ims")) {
            $this->handlerClickButton('iLandingViewAction');
        }

        // To help you—and only you—get back into ..., we need to verify that it's yours.
        if ($this->http->FindPreg("/To help you—and only you—get back into/ims") && $this->waitForElement(WebDriverBy::id('iLandingViewAction'), 0)) {
            $this->logger->debug("Click 'Next'");
            $this->handlerClickButton('iLandingViewAction');
        }

        // two-step verification, switch to verification code
        if ($this->waitForElement(WebDriverBy::xpath('
                    //div[contains(., "On your mobile device, approve request")]
                    | //div[contains(text(), "Because you\'ve turned on two-step verification,")]
                    | //a[@id = "iHavingTroubleLink"]
                    | //div[contains(text(), "We sent an identity verification request to your mobile device")]
                    | //div[contains(text(), "Approve sign in request")]
                    | //span[contains(text(), "We sent an identity verification request to your mobile device, but you denied it.")]
                    | //div[contains(text(), "We didn\'t hear from you")]
            '), 5)
        ) {
            $this->logger->debug("Click 'Having trouble?'");

            if ($havingTroubleLink = $this->waitForElement(WebDriverBy::xpath('//a[@id = "iHavingTroubleLink"] | //a[@id = "idA_SAOTCAS_Trouble"] | //a[@id = "signInAnotherWay"]'), 0)) {
                $havingTroubleLink->click();
            }
            $this->logger->debug("Click 'get a code a different way.'");
            $differentOptionLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@id, "iDifferentOptionLink")] | //a[contains(@id, "_SendCode")] | //a[contains(@id, "idA_SAOTCC_Toggle")]'), 5);
            $this->saveResponse();

            if ($differentOptionLink) {
                $differentOptionLink->click();
            }

            $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Help us protect your account")]'), 5);
        }
        $this->saveResponse();

        /* NEW layout of security questions - summer 2017 */

        // Verify your identity
        if ($this->waitForElement(WebDriverBy::xpath('
                //div[@id = "idDiv_SAOTCS_Title" and contains(., "Verify your identity")]
                | //div[@id = "iSelectProofTitle" and contains(., "We need to verify your identity")]
            '), 0)
        ) {
            $emailText = $this->waitForElement(WebDriverBy::xpath("(//*[self::div or self::span][contains(text(), 'Email')])[1]"), 0);

            if (!$emailText) {
                $emailText = $this->waitForElement(WebDriverBy::xpath("(//*[self::div or self::span][*[contains(text(), 'Email')]])[1]"), 0); //todo
            }

            if (!$emailText && ($phoneText = $this->waitForElement(WebDriverBy::xpath("(//*[self::div or self::span][contains(text(), 'Text')])[1]"), 0))) {
                if (!$phoneText) {
                    return false;
                }

                $this->chooseMethodHowToReceiveCode();

                return false;

                $phone = $this->http->FindPreg("/Text\s*(.+)/", false, $phoneText->getText());
                $phoneText->click();
                $sendCode = $this->waitForElement(WebDriverBy::xpath('//*[@id = "idSubmit_SAOTCC_Continue" or @id = "iSelectProofAction"]'), 5);
                $this->saveResponse();

                if ($phone && $sendCode) {
                    $this->holdSession();
                    $this->logger->debug("Phone -> {$phone}");
                    $this->AskQuestion($this->securityCodeQuestionPhone, null, 'sendSecurityCode');

                    return false;
                }// if ($email && $sendCode)

                return false;
            }// if (!$emailText)
            elseif (!$emailText && ($app = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Use a verification code from my mobile app')]"), 0))) {
                $app->click();
                $verify = $this->waitForElement(WebDriverBy::id('idSubmit_SAOTCC_Continue'), 5);
                $this->saveResponse();

                if ($verify) {
                    $this->holdSession();

                    $this->AskQuestion($this->twoStepAuthQuestion, null, 'sendSecurityCode');

                    return false;
                }// if ($email && $sendCode)

                return false;
            }// if (!$emailText)

            if (!$emailText) {
                return false;
            }

            $email = $this->http->FindPreg("/Email\s*(.+)/", false, $emailText->getText());
            $emailText->click();

            if ($getCode = $this->waitForElement(WebDriverBy::xpath('//input[@id = "iSelectProofAction"]'), 0)) {
                $getCode->click();
            }

            $res = $this->waitForElement(WebDriverBy::xpath('
                //input[@id = "idSubmit_SAOTCS_SendCode"]
                | //div[@id = "iSelectProofError"]
            '), 7);

            $sendCode = $this->waitForElement(WebDriverBy::xpath('//input[@id = "idSubmit_SAOTCS_SendCode"]'), 0);
            $this->saveResponse();

            if ($email && ($sendCode || $this->waitForElement(WebDriverBy::xpath('//input[@id = "idSubmit_SAOTCC_Continue" or @id = "iVerifyIdentityAction"]'), 0))) {
                $this->holdSession();
                $this->logger->debug("Email -> {$email}");

                $this->AskQuestion($this->securityCodeQuestionEmail, null, 'sendSecurityCode');

                return false;
            }// if ($email && $sendCode)
            elseif ($res && strstr($res->getText(), 'You\'ve requested too many codes today.')) {
                throw new CheckException($res->getText(), ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "idDiv_SAOTCS_Title" and contains(., "Verify your identity")]'), 0))

        if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "idDiv_SAOTCC_Description" and contains(., "Please type in the code displayed on your authenticator app from your device")]'), 0)) {
            $this->holdSession();
            $this->AskQuestion($this->twoStepAuthQuestion, null, 'twoStepAuth');

            return false;
        }// if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "idDiv_SAOTCC_Description" and contains(., "Please type in the code displayed on your authenticator app from your device")]'), 0))

        /* NEW layout of security questions - summer 2017 */

        if ($error = $this->http->FindPreg('#(You\s+need\s+to\s+use\s+a\s+security\s+code\s+to\s+verify\s+your\s+identity)\.\s+How\s+would\s+you\s+like\s+to\s+receive\s+your\s+code\?#i')) {
            $this->verify = false;

            return $this->chooseMethodHowToReceiveCode($error);
        } elseif ($error = $this->http->FindPreg('#(?:We\'ve\s+detected\s+something\s+unusual\s+about\s+this\s+sign-in.\s+For\s+example,\s+you\s+might\s+be\s+signing\s+in\s+from\s+a\s+new\s+location,\s+device,\s+or\s+app\.\s+Before\s+you\s+can\s+continue,\s+we\s+need\s+to\s+verify\s+your\s+identity\s+with\s+a\s+security\s+code\.|We\'ve\s*detected\s*something\s*unusual\s*about\s*this\s*sign-in\.\s*For\s*example,\s*you\s*might\s*be\s*signing\s*in\s*from \s*a\s*new\s*location,\s*device\s*or\s*app\.|How\s*do\s*you\s*want\s*to\s*receive\s*your\s*security\s*code\?)#i')
            || $this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "To verify that this is your email address, complete the hidden part and click")]'), 0)
        ) {
            $this->http->Log('Bing error: ' . $error);
            $this->verify = true;

            return $this->chooseMethodHowToReceiveCode($error);
        } elseif ($this->http->FindPreg('#Someone\s+started\s+a\s+process\s+to\s+replace\s+the\s+security\s+info\s+for#i') || $this->http->FindPreg("/Passwords\s*can\s*be\s*forgotten\s*or\s*stolen\.\s*Just\s*in\s*case\,\s*add\s*security\s*info\s*now\s*to\s*help\s*you\s*get\s*back\s*into\s*your\s*account\s*if\s*something\s*goes\s*wrong\./")
            || $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your Skype account is now a Microsoft account")] | //div[contains(text(), "Verify email") or contains(text(), "Verify your email")]'), 0)
            // For security reasons we are asking you to choose a new password. This might be because you were issued this account with a temporary password.
            || $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Change your password")]'), 0)
            // Help us secure your account
            || $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We have detected unusual activity on your Microsoft account ")]'), 0)
            /*
             * Your Skype account is linked to your Microsoft account
             *
             * You have two Microsoft accounts. Merge ... with ... as one account with a single password.
             */
            || $this->waitForElement(WebDriverBy::xpath('//div[@id="GoodNewsSecureDesc" and contains(text(), "You have two Microsoft accounts. Merge") and contains(., "as one account with a single password.")]'), 0)) {
            $this->throwProfileUpdateMessageException();
        } elseif ($errorPart1 = $this->http->FindPreg('#Call\s*us\s*overprotective#ims')
                        and $errorPart2 = $this->http->FindPreg('#but\s+we\s+need\s+to\s+make\s+sure\s+you\s+can\s+receive\s+a\s+security\s+code\s+if\s+you\s+ever\s+lose\s+access\s+to\s+this\s+account\.#i')) {
            $error = $errorPart1 . ' ' . $errorPart2;
            $this->logger->error('Bing error: ' . $error);
            $this->verify = true;

            return $this->chooseMethodHowToReceiveCode();
        } elseif ($providerMessage = $this->http->FindPreg('/(?:Because you\'ve turned on two-step verification, we need to verify your identity\.\s*Enter the code generated by your authenticator app\.|Before you can access sensitive info, we need to verify your identity\.|Enter the code displayed in the Microsoft app )/')) {
            $this->holdSession();

            return $this->twoStepAuth($providerMessage);
        } elseif ($error = $this->http->FindPreg('#Your\s+account\s+has\s+been\s+temporarily\s+blocked#i')) {
            $this->http->Log($error, LOG_LEVEL_ERROR);

            return $this->handleAccountBlock($error);
        }

        if ($error = $this->http->FindPreg('#To\s+help\s+keep\s+your\s+Microsoft\s+account\s+more\s+secure,\s+you\s+need\s+to\s+change\s+your\s+password\.#i')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        // Your account has been temporarily suspended
        if ($error = $this->http->FindPreg('#Your\s*account\s*has\s*been\s*temporarily\s*suspended#ims')) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * There's a temporary problem
         *
         * There's a temporary problem with the service. Please try again. If you continue to get this message, try again later.
         */
        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[
                contains(text(), "There\'s a temporary problem with the service. Please try again.")
                or contains(text(), "We sent a sign in request to ")
                or contains(text(), "We sent a sign-in request to ")
            ]'), 0)
        ) {
            throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // You're trying to sign in to an account that's going to be closed.
        if ($error = $this->http->FindPreg("#You\'re\s*trying\s*to\s*sign\s*in\s*to\s*an\s*account\s*that\'s\s*going\s*to\s*be\s*closed\.#ims")) {
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
        }

        if ($error = $this->driver->executeScript('return (e = document.getElementById("idTd_Tile_ErrorMsg_Login")) != null ? e.textContent : null')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->driver->executeScript('return (e = document.getElementById("idTd_PWD_ErrorMsg_Password")) != null ? e.textContent : null')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->driver->executeScript('return (e = document.getElementById("idTd_PWD_ErrorMsg_Username")) != null ? e.textContent : null')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->driver->executeScript('return (e = document.getElementById("errorText")) != null ? e.textContent : null')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }
        // Sign-in is blocked
        if ($error = $this->driver->executeScript('return (e = document.getElementById("idTD_LockoutError")) != null ? e.textContent : null')) {
            throw new CheckException($error, ACCOUNT_LOCKOUT);
        }
        // New terms
        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'re updating our terms")]'), 0)) {
            $this->throwAcceptTermsMessageException();
        }

        $this->saveResponse();

        return true;
    }

    public function handleAccountBlock($error = null)
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript('document.getElementById("idContinue").click()');
        sleep(3);
        $this->saveResponse();

        if ($e1 = $this->http->FindPreg('#Enter\s+your\s+phone\s+number,\s+and\s+we\'ll\s+send\s+you\s+a\s+security\s+code#i')
                and $e2 = $this->http->FindPreg('#We\'ll\s+only\s+use\s+this\s+number\s+to\s+help\s+you\s+get\s+back\s+into\s+your\s+account\.#i')) {
            $q = $e1 . ' (phone number should be typed in international format starting with "+" like "+0 000 000 00 00"). ' . $e2;

            if (isset($this->Answers[$q])) {
                if ($this->http->FindPreg('#I\s+have\s+a\s+code#i')
                        and isset($this->Answers[$this->accountBlockPhoneQuestion])) {
                    $this->logger->debug('Clicking "I have a code"');
                    $this->driver->executeScript('document.getElementById("idPLC5").children[0].click()');
                    sleep(3);
                    $this->saveResponse();
                } else {
                    $phoneNumber = '+' . preg_replace('#\D#i', '', $this->Answers[$q]);
                    $this->logger->debug('Got phone number ' . $phoneNumber);

                    if ($arr = $this->countryCodeByPhoneNumber($phoneNumber)) {
                        $countryCodeSymb = $arr[0];
                        $this->logger->debug('Country code (symbolic): ' . $countryCodeSymb);
                        $countryCodeNum = $arr[1];
                        $this->logger->debug('Country code (numeric): ' . $countryCodeNum);
                        $phoneNumberCut = preg_replace('#\+' . $countryCodeNum . '#i', '', $phoneNumber);
                    } else {
                        $this->logger->debug('Couldn\'t find country code for this phone number', LOG_LEVEL_ERROR);

                        throw new CheckException(null, ACCOUNT_ENGINE_ERROR);
                    }
                    $this->logger->debug('Cutted phone number for input to form: ' . $phoneNumberCut);
                    $this->driver->executeScript('document.getElementById("idMNCMobile").value = "' . $phoneNumberCut . '";');
                    $this->logger->debug('Country code for input to form: ' . $countryCodeSymb);
                    $this->driver->executeScript('document.getElementById("iSMSCountry").value="' . $countryCodeSymb . '"');
                    sleep(3);
                    $this->saveResponse();
                    $this->logger->debug('Clicking "Send code"');
                    $this->driver->executeScript('document.getElementById("idMNCSendCode").click()');
                    sleep(3);
                    $this->saveResponse();

                    if ($e = $this->http->FindPreg('#You have exceeded the number of times you may request a verification code. Please try again later.#i')) {
                        throw new CheckException($e, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                if ($this->http->FindPreg('#Enter the security code we sent to#i')) {
                    if (isset($this->Answers[$this->accountBlockPhoneQuestion])) {
                        $code = $this->Answers[$this->accountBlockPhoneQuestion];
                        $this->logger->debug('Got security code "' . $code . '", sending it');
                        $this->driver->executeScript('document.getElementById("idIdent").value = "' . $code . '";');
                        sleep(1);
                        $this->driver->executeScript('document.getElementById("idContinue").click()');
                        sleep(3);
                        $this->saveResponse();

                        if ($errorMsg = $this->http->FindPreg('#That\s+code\s+didn\'t\s+work\.\s+Check\s+the\s+code\s+and\s+try\s+again#i')) {
                            $this->http->Log($errorMsg, LOG_LEVEL_ERROR);
                            $this->logger->debug('Reasking for security code');
                            $this->AskQuestion($this->accountBlockPhoneQuestion, $error, 'handleAccountBlock');

                            return false;
                        }
                        $this->driver->executeScript('document.getElementById("id4").click()');
                        sleep(3);
                        $this->saveResponse();

                        if ($e = $this->http->FindPreg('#To help keep your Microsoft account more secure, you need to change your password#i')) {
                            throw new CheckException($e, ACCOUNT_PROVIDER_ERROR);
                        }

                        if ($e = $this->http->FindPreg('#Because\s+you\'re\s+accessing\s+sensitive\s+info,\s+you\s+need\s+to\s+verify\s+your\s+password#i')) {
                            $this->logger->error($e);
                            $this->logger->debug('Setting password');
                            $this->driver->executeScript('document.getElementById("i0118").value = "' . $this->AccountFields['Pass'] . '"');
                            $this->logger->debug('Clicking "Sign In"');
                            $this->driver->executeScript('document.getElementById("idSIButton9").click()');
                            sleep(3);
                            $this->saveResponse();
                            static $attemptsCount = 1;

                            if ($this->http->FindPreg('#Your account has been temporarily blocked#i') and $attemptsCount <= 3) {
                                $this->logger->notice('Bing redirected back to the beginning of account block page, launching whole process again (attempt #' . $attemptsCount . ')');
                                $attemptsCount++;

                                return $this->handleAccountBlock();
                            }
                            $this->http->Log('Something wrong happening, you should look at it');

                            return false;
                        }

                        return true;
                    } else {
                        $this->logger->debug('Asking for security code');
                        $this->AskQuestion($this->accountBlockPhoneQuestion, null, 'handleAccountBlock');

                        return false;
                    }
                }
            } else {
                $this->logger->debug('Asking for phone number');
                $this->AskQuestion($q, $error, 'handleAccountBlock');
            }
        }

        return false;
    }

    public function chooseMethodHowToReceiveCode($errorMessage = null)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        // It looks like someone else might be using your account (there is only 'Next' button)
        if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'It looks like someone else might be using your account')]"), 0)
            && ($btn = $this->waitForElement(WebDriverBy::id("iLandingViewAction"), 0))) {
            $this->logger->notice("click 'Next' button");
            $btn->click();
            sleep(3);
            $this->saveResponse();
        }

        $s = $this->verify ? 'return document.querySelectorAll("*[class = proofsgap]").length' : 'return document.querySelectorAll("*[name = proofRB]").length';
        $select = false;
        $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;

        if ($receiveCodeVariantsCount == -1) {
            $this->logger->debug($receiveCodeVariantsCount . ' ways to receive security code found');
            $s = 'return document.querySelectorAll("*[name = proof]").length';
            $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;
        }

        if ($receiveCodeVariantsCount == -1) {
            $this->logger->debug($receiveCodeVariantsCount . ' ways to receive security code found');
            $s = 'return document.querySelectorAll("*[id *= proofInput]").length';
            $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;
            $select = false;

            $this->verify = false;
        }

        if ($receiveCodeVariantsCount == -1) {
            $this->logger->debug($receiveCodeVariantsCount . ' ways to receive security code found');
            $s = 'return document.querySelectorAll("select[name = proofs] option").length';
            $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;
            $select = true;
            $this->verify = false;
        }

        // debug
        if (
            $receiveCodeVariantsCount == -1
            && !$this->waitForElement(WebDriverBy::xpath("(//*[self::div or self::span][contains(text(), 'Text')])[1]"), 0)
        ) {
            $this->http->GetURL($this->startURL());
            $this->saveResponse();
            $s = $this->verify ? 'return document.querySelectorAll("*[class = proofsgap]").length' : 'return document.querySelectorAll("*[name = proofRB]").length';
            $select = false;
            $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;

            if ($receiveCodeVariantsCount == -1) {
                $this->logger->debug($receiveCodeVariantsCount . ' ways to receive security code found');
                $s = 'return document.querySelectorAll("*[name = proof]").length';
                $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;
            }

            if ($receiveCodeVariantsCount == -1) {
                $this->logger->debug($receiveCodeVariantsCount . ' ways to receive security code found');
                $s = 'return document.querySelectorAll("select[name = proofs] option").length';
                $receiveCodeVariantsCount = $this->driver->executeScript($s) - 1;
                $select = true;
                $this->verify = false;
            }
        }// if ($receiveCodeVariantsCount == -1) {

        $this->logger->debug("s -> {$s}");
        $this->logger->debug($receiveCodeVariantsCount . ' ways to receive security code found');
        $emailTip = null;
        $phoneTip = null;
        $variantAsIs = false; // for 'Call us overprotective...' auth case, when phones and emails are shown as is, without '*'
        $emailSelectIndex = null;
        $phoneSelectIndex = null;

        if ($emailText = $this->waitForElement(WebDriverBy::xpath("(//*[self::div or self::span][contains(text(), 'Email')])[1]"), 0)) {
            $receiveCodeVariantsCount = 0;
            $select = false;
            $emailTip = $this->http->FindPreg('#Email\s+(.+)#i', false, $emailText->getText());
            $this->logger->debug('Email matched ' . $emailTip);
            $emailTip = $this->clean_string($emailTip);

            if ($emailTip) {
                $emailText->click();
            }
        } elseif ($phoneText = $this->waitForElement(WebDriverBy::xpath("(//*[self::div or self::span][contains(text(), 'Text')])[1]"), 0)) {
            $receiveCodeVariantsCount = 0;
            $select = false;
            $phoneTip = $this->http->FindPreg('#Text\s+(.+\d{2})#i', false, $phoneText->getText());
            $this->logger->debug('Phone matched ' . $phoneTip);
            $phoneTip = $this->clean_string($phoneTip);

            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Use a verification code from my mobile app')]"), 0)) {
                $phoneText->click();
            }
        }

        for ($i = 0; $i < $receiveCodeVariantsCount; $i++) {
            if ($select) {
                $query = 'querySelectorAll("select[name = proofs] option").item(' . $i . ')';
            } else {
                if ($this->waitForElement(WebDriverBy::xpath("//*[@id = 'iProofLbl{$i}']"), 0)) {
                    $query = 'querySelector("*[id = iProofLbl' . $i . ']")';
                } elseif ($this->waitForElement(WebDriverBy::xpath("//*[@for = 'idRB_SAOTCS_Proof{$i}']"), 0)) {
                    $query = 'querySelector("*[for = idRB_SAOTCS_Proof' . $i . ']")';
                } elseif ($this->waitForElement(WebDriverBy::xpath("//*[@id = 'textproofOption{$i}']"), 0)) {
                    $query = 'querySelector("*[id = textproofOption' . $i . ']")';
                } else {
                    $query = $this->verify ? 'querySelector("*[id = iProofLbl' . $i . ']")' : 'querySelector("*[for = idRB_SAOTCS_Proof' . $i . ']")';
                }
            }
            $s = 'return document.' . $query . '.innerHTML';
            $this->logger->debug("query -> [{$s}]");

            $variant = $this->driver->executeScript($s);
            $this->logger->debug('Variant #' . ($i + 1) . ': ' . $variant);

            if (stripos($variant, '@') !== false and preg_match('#\S+@\S+#i', $variant, $m)) {
                $this->logger->debug('Email matched ' . $m[0]);
                $emailTip = $this->clean_string($m[0]);
                $emailSelectIndex = $i;
            } elseif (preg_match('#Text\s+(.+\d{2})#i', $variant, $m) && !$phoneTip) {//find email
                $this->logger->debug('Phone matched ' . $m[0]);
                $phoneTip = $this->clean_string($m[1]);
                $phoneSelectIndex = $i;
            } else {
                $this->logger->debug('None matched');
            }

            if ($emailTip) {
                $this->logger->error('Supported variant found, so we don\'t search for others');

                break;
            }
        }

        if ($emailTip) {
            $this->logger->debug('Email tip: ' . $emailTip);
            $this->logger->debug('Email select index: ' . $emailSelectIndex);
            // Bing shows email as is, without *** mask
            //			if (!$this->http->FindPreg('#\*{3}@#i', false, $emailTip))
            //				$variantAsIs = true;
            if (stripos($this->emailQuestion, '@') === false) {
                $this->emailQuestion .= "(" . $emailTip . ")";
            }
        } elseif ($phoneTip) {
            $this->logger->debug('Phone tip: ' . $phoneTip);
            $this->logger->debug('Phone select index: ' . $phoneSelectIndex);
            // Bing shows phone as is, without *** mask
//            if (!$this->http->FindPreg('#\*+\d+#i', false, $phoneTip))
            //				$variantAsIs = true;
            if (!preg_match('#\d+$#i', $this->phoneQuestion)) {
                $this->phoneQuestion .= $phoneTip;
            }
        } else {
            $this->logger->error('No email/phone found in receive code variants');
            $this->saveResponse();

            throw new CheckRetryNeededException(3, 7);
        }

        $this->logger->debug('Variant: ' . ($variantAsIs ? 'As Is' : 'Not As IS'));

        sleep(5);
        $this->logger->debug('Checking if email/phone was asked already (question "' . $this->emailQuestion . '")');
        $this->saveResponse();

        if (($emailTip and !$variantAsIs and !isset($this->Answers[$this->emailQuestion]) and strstr($emailTip, '*'))
            || ($phoneTip and !$variantAsIs and !isset($this->Answers[$this->phoneQuestion]) and strstr($phoneTip, '*'))) {
            $this->logger->debug('No email/phone was asked, asking');
            $msg = $errorMessage ? $errorMessage : 'Help us protect your account';
            $this->logger->debug("Message -> {$msg}");
            $this->saveResponse();

            if ($emailTip) {
                $this->AskQuestion($this->emailQuestion, $msg, 'chooseMethodHowToReceiveCode');
            } elseif ($phoneTip) {
                $this->AskQuestion($this->phoneQuestion, $msg, 'chooseMethodHowToReceiveCode');
            } else {
                $this->logger->error('This should not happen, something is wrong');

                return false;
            }
            $this->holdSession();

            return false;
        } elseif (
            isset($this->Answers[$this->securityCodeQuestionEmail])
            || isset($this->Answers[$this->securityCodeQuestionPhone])
            || isset($this->Answers[$this->twoStepAuthQuestion])
        ) {
            $this->logger->debug('We already have some security code, try to check it');

            if ($this->getWaitForOtc()) {
                $this->sendNotification("mailbox, 2fa - refs #20661 // RR");
            }

            return $this->sendSecurityCode();
        } else {
            $this->logger->debug("Selecting Method How To Receive Code...");

            if ($emailTip) {
                if (!isset($this->Answers[$this->emailQuestion]) && strstr($emailTip, '*')) {
                    $this->logger->debug(var_export($this->Answers, true), ["pre" => true]);

                    return false;
                }

                if (!strstr($emailTip, '*')) {
                    $email = $emailTip;
                } else {
                    $email = $this->Answers[$this->emailQuestion];
                }
                $this->logger->debug('User has specified email "' . $email . '", prepare it to send to Bing');
                $emailCutted = null;

                if (preg_match('#(.*)@#i', $email, $m)) {
                    $emailCutted = $m[1];
                }

                if (!$emailCutted) {
                    // TODO: Online check
                    $this->logger->error('User specified wrong mail');
                    unset($this->Answers[$this->emailQuestion]);
                    $this->AskQuestion($this->emailQuestion, 'Bad email, no "@" found', 'chooseMethodHowToReceiveCode');

                    return false;
                }
                $this->logger->debug('Cutted email is "' . $emailCutted . '", sending to Bing');
                $id = $this->verify ? 'iProof' . $emailSelectIndex : 'idRB_SAOTCS_Proof' . $emailSelectIndex;
                $variantValue = $emailCutted;
            }// if ($emailTip)
            elseif ($phoneTip) {
                if (!isset($this->Answers[$this->phoneQuestion]) && strstr($phoneTip, '*')) {
                    $this->logger->debug(var_export($this->Answers, true), ["pre" => true]);

                    return false;
                }

                if (!strstr($phoneTip, '*')) {
                    $phone = $phoneTip;
                } else {
                    $phone = $this->Answers[$this->phoneQuestion];
                }

                $this->logger->debug('User has specified last four digits "' . $phone . '" of his/her phone, prepare it to send to Bing');
                $id = $this->verify ? 'iProof' . $phoneSelectIndex : 'idRB_SAOTCS_Proof' . $phoneSelectIndex;
                $variantValue = $phone;
            }// elseif ($phoneTip)
            else {
                $this->logger->error('This should not happen, something is wrong');

                return false;
            }

            $this->logger->notice("Selecting option...");

            if ($select) {
                $this->logger->debug("select:");
                $this->logger->debug("document.querySelector(\"select[name = proofs]\").selectedIndex = {$i}");
                $this->driver->executeScript('document.querySelector("select[name = proofs]").selectedIndex = ' . $i);
            } else {
                if ($emailTip) {
                    $input =
                        $this->waitForElement(WebDriverBy::id("iProofLbl{$emailSelectIndex}"), 0)
                        ?? $this->waitForElement(WebDriverBy::id("iProof{$emailSelectIndex}"), 0)
                    ;
                } elseif ($phoneTip) {
                    $input = $this->waitForElement(WebDriverBy::id("iProofLbl{$phoneSelectIndex}"), 0);
                } else {
                    return false;
                }

                if ($input) {
                    $mover = new MouseMover($this->driver);
                    $mover->logger = $this->logger;
                    $mover->moveToElement($input);
                    $mover->click();
                }
            }

            $this->logger->notice("Populating input...");
            $fullPhoneEmailInput = $this->waitForElement(WebDriverBy::id("iConfirmProof"), 2);

            if (!$fullPhoneEmailInput) {
                $fullPhoneEmailInput = $this->waitForElement(WebDriverBy::id("idTxtBx_SAOTCS_ProofConfirmation"), 0);
            }

            if (!$fullPhoneEmailInput) {
                $fullPhoneEmailInput = $this->waitForElement(WebDriverBy::id("iProofEmail"), 0);
            }

            if (!$fullPhoneEmailInput) {
                $fullPhoneEmailInput = $this->waitForElement(WebDriverBy::id("iProofPhone"), 0);
            }
//            $this->logger->debug("document.querySelector(\"input[id = '{$id}']\").value = \"'{$variantValue}'\";");
//            $this->driver->executeScript('if (document.querySelector("input[id = '.$id.']")) document.querySelector("input[id = '.$id.']").value = "'.$variantValue.'";');
            if ($fullPhoneEmailInput) {
                $fullPhoneEmailInput->sendKeys($variantValue);
            } else {
                $this->logger->error("input not found");
                $this->saveResponse();
            }
            $this->saveResponse();
            $this->logger->notice("Sending code...");
            $button = $this->waitForElement(WebDriverBy::id("iNext"), 2);

            if (!$button) {
                $button = $this->waitForElement(WebDriverBy::id("idSubmit_SAOTCS_SendCode"), 0);
            }

            if (!$button) {
                $button = $this->waitForElement(WebDriverBy::id("iSelectProofAction"), 0);
            }

            if (!$button) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return false;
            }
            // fix for AccountID: 4991559, too many emails
            $x = $button->getLocation()->getX();
            $y = $button->getLocation()->getY() - 200;
            $this->driver->executeScript("window.scrollBy($x, $y)");

            $button->click();
            sleep(2);
            $this->saveResponse();
            $this->logger->debug('Checking for errors');

            $error = $this->driver->executeScript('return (e = document.getElementById("idSpan_SAOTCS_Error_OTC")) != null ? e.innerHTML : null');
            $this->logger->debug('First error type - "' . $error . '"');

            if ($emailTip && isset($this->Answers[$this->emailQuestion])) {
                $question = $this->Answers[$this->emailQuestion];
            } elseif ($phoneTip && isset($this->Answers[$this->phoneQuestion])) {
                $question = $this->Answers[$this->phoneQuestion];
            } elseif (!empty($error)) {
                $this->logger->error('This should not happen, something is wrong');

                return false;
            }

            if ($error == 'We couldn\'t send the code. Please try again.') {
                $this->logger->error($error);

                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            } elseif (!empty($error) && isset($question)) {
                $this->logger->error($error);
                unset($this->Answers[$this->emailQuestion]);
                unset($this->Answers[$this->phoneQuestion]);
                $this->AskQuestion($question, $error, 'chooseMethodHowToReceiveCode');

                return false;
            }

            $error = $this->driver->executeScript('return (e = document.getElementById("id_SAOTCS_Error_ProofConfirmation")) != null ? e.innerHTML : null');
            $this->logger->debug('Second error type - "' . $error . '"');

            if (empty($error)) {
                $error = $this->driver->executeScript('return (e = document.getElementById("iProofInputError")) != null ? e.innerHTML : null');
                $this->logger->debug('Third error type - "' . $error . '"');
            }

            if (!empty($error) && isset($question)) {
                $this->logger->error($error);
                unset($this->Answers[$this->emailQuestion]);
                unset($this->Answers[$this->phoneQuestion]);
                $this->AskQuestion($question, $error, 'chooseMethodHowToReceiveCode');

                return false;
            }

            $this->logger->debug('No errors found, now user should input security code');
            $this->saveResponse();
            $callUsOverprotectiveMsg = 'Call us overprotective but we need to make sure you can receive a security code if you ever lose access to this account';

            if ($emailTip) {
                $this->logger->debug("Email [id = {$emailSelectIndex}]");
                $question = $this->securityCodeQuestionEmail;

                if ($variantAsIs) {
                    $msg = $callUsOverprotectiveMsg;
                    $id = $this->verify ? 'iProof' . $emailSelectIndex : 'idRB_SAOTCS_Proof' . $emailSelectIndex;
                    $this->driver->executeScript('document.querySelector("input[id = ' . $id . ']").click();');
                    sleep(1);
                    $this->driver->executeScript('document.getElementById("iNext").click()');
                    sleep(3);
                }// if ($variantAsIs)
                elseif (isset($this->Answers[$this->emailQuestion])) {
                    $msg = 'If ' . $this->Answers[$this->emailQuestion] . ' matches the email address on your account, Provider site will send you a code';
                } elseif ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'iEnterSubhead']"))) {
                    $msg = $message->getText();
                }
            } elseif ($phoneTip) {
                $this->logger->debug("Phone [index = {$phoneSelectIndex}]");
                $question = $this->securityCodeQuestionPhone;

                if ($variantAsIs) {
                    $msg = $callUsOverprotectiveMsg;
                    $id = $this->verify ? 'iProof' . $phoneSelectIndex : 'idRB_SAOTCS_Proof' . $phoneSelectIndex;
                    $this->logger->debug("Phone [id = {$id}]");
                    $this->driver->executeScript('document.querySelector("input[id = ' . $id . ']").click();');
                    sleep(1);
                    $this->driver->executeScript('document.getElementById("iNext").click()');
                    sleep(3);
                }// if ($variantAsIs)
                elseif (isset($this->Answers[$this->phoneQuestion])) {
                    $msg = 'If ' . $this->Answers[$this->phoneQuestion] . ' the last 4 digits of the phone number on your account, Provider site will send you a code';
                } elseif ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'iEnterSubhead']"))) {
                    $msg = $message->getText();
                }
            } else {
                $this->logger->error('This should not happen, something is wrong');
                $this->saveResponse();

                return false;
            }
            $this->saveResponse();
            $this->holdSession();

            if (isset($msg)) {
                $this->logger->debug("Message -> {$msg}");

                $this->AskQuestion($question, $msg, 'sendSecurityCode');
            }
        }

        return false;
    }

    public function twoStepAuth($providerMessage = null)
    {
        // TODO: Find out why sometimes code is sent twice
        $this->logger->notice(__METHOD__);
        sleep(5);
        $this->saveResponse();

        if (isset($this->Answers[$this->twoStepAuthQuestion])) {
            $code = $this->Answers[$this->twoStepAuthQuestion];
            $this->logger->notice("Entering the code generated by authenticator app...");
            $input = $this->waitForElement(WebDriverBy::id('iOttText'), 0);

            if (!$input) {
                $input = $this->waitForElement(WebDriverBy::id('idTxtBx_SAOTCC_OTC'), 0);
            }

            if (!$input) {
                $this->saveResponse();

                return false;
            }
            $input->sendKeys($code);
            // Don't ask me again on this device
            $this->driver->executeScript('if (checkBox = document.getElementById("idChkBx_SAOTCC_TD")) checkBox.checked = true');
//            $this->driver->executeScript('document.getElementById("idChkBx_SAOTCC_TD").click();');
            try {
                unset($this->Answers[$this->twoStepAuthQuestion]);
                $this->saveResponse();
                $this->logger->debug("Sending code, click 'Next' button...");

                if ($this->waitForElement(WebDriverBy::id('idSubmit_SAOTCC_Continue'), 0)) {
                    $this->driver->executeScript('document.getElementById("idSubmit_SAOTCC_Continue").click();');
                } elseif ($next = $this->waitForElement(WebDriverBy::id('iVerifyCodeAction'), 0)) {
                    $next->click();
                }
                //				    $this->driver->executeScript('document.getElementById("iVerifyCodeAction").click();');
                $error = $this->waitForElement(WebDriverBy::id('idSpan_SAOTCC_Error_OTC'), 5);
                $this->saveResponse();

                if ($error && $error->getText()) {
                    $message = $error->getText();

                    if ($message == 'Enter the code to help us verify your identity.') {
                        return false;
                    } elseif ($message == 'Too many invalid codes have been entered. Please try again later.') {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    } else {
                        $this->logger->error('Code error: ' . $message);
                        $this->holdSession();
                        $this->AskQuestion($this->twoStepAuthQuestion, $message, 'twoStepAuth');

                        return false;
                    }
                }// if ($error && $error->getText())
            } catch (UnexpectedAlertOpenException $e) {
                $this->handleSecurityException($e);
            }

            return true;
        } else {
            $this->holdSession();
            $this->AskQuestion($this->twoStepAuthQuestion, $providerMessage, 'twoStepAuth');

            return false;
        }

        return false;
    }

    public function clean_string($string)
    {
        $this->logger->notice(__METHOD__);
        $s = trim($string);
        // drop all non utf-8 characters
        $s = iconv("UTF-8", "UTF-8//IGNORE", $s);
        // this is some bad utf-8 byte sequence that makes mysql complain - control and formatting i think
        $s = preg_replace('/(?>[\x00-\x1F]|\xC2[\x80-\x9F]|\xE2[\x80-\x8F]{2}|\xE2\x80[\xA4-\xA8]|\xE2\x81[\x9F-\xAF])/', ' ', $s);
        // reduce all multiple whitespace to a single space
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }

    public function enteringSecurityCode()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->saveResponse();
        $input = $this->waitForElement(WebDriverBy::xpath('//input[@id = "idTxtBx_OTC_Password"]'), 3);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Sign in"]'), 0);
        $this->saveResponse();

        if (!$button) {
            $this->logger->error("something went wrong");

            return false;
        }
        // Keep me signed in
        $this->driver->executeScript('if (checkBox = document.getElementById("idChkBx_PWD_KMSI0Pwd")) checkBox.checked = true');
        $input->sendKeys($answer);
        $this->saveResponse();
        $button->click();

        $error = $this->waitForElement(WebDriverBy::xpath('//span[@id = "idSpan_SAOTCC_Error_OTC"]'), 5); //todo: fake selector
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Stay signed in?")]'), 0)) {
            $this->driver->executeScript('document.querySelector(\'input[name="DontShowAgain"]\').checked = true;');
            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "idSIButton9"]'), 0)->click();
            sleep(3);
            $this->saveResponse();

            return true;
        }

        return true;
    }

    public function sendSecurityCode()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('Checking if security code was already asked recently');

        if (
            !isset($this->Answers[$this->securityCodeQuestionEmail])
            && !isset($this->Answers[$this->securityCodeQuestionPhone])
            && !isset($this->Answers[$this->twoStepAuthQuestion])
        ) {
            // Maybe question asking should be placed here instead of chooseMethodHowToReceiveCode
            $this->logger->error('Something went wrong');

            return false;
        }
        $emailVariant = false;
        $phoneVariant = false;
        $appVariant = false;

        if ($this->verify) {
            $this->logger->notice('Clicking "I have a code"...');
            $id = $this->verify ? 'iSelectProofAlternate' : 'idA_SAOTCS_Toggle';

            if ($this->waitForElement(WebDriverBy::id('iShowEnterCode'), 0)) {
                $id = 'iShowEnterCode';
            }
            $this->logger->debug("document.getElementById(\"'{$id}'\").click();");
            $this->saveResponse();
            $this->driver->executeScript('document.getElementById("' . $id . '").click()');
        }

        if (isset($this->Answers[$this->securityCodeQuestionEmail])) {
            $emailVariant = true;
            $answer = $this->Answers[$this->securityCodeQuestionEmail];
            unset($this->Answers[$this->securityCodeQuestionEmail]);
        } elseif (isset($this->Answers[$this->securityCodeQuestionPhone])) {
            $phoneVariant = true;
            $answer = $this->Answers[$this->securityCodeQuestionPhone];
            unset($this->Answers[$this->securityCodeQuestionPhone]);
        } elseif (isset($this->Answers[$this->twoStepAuthQuestion])) {
            $appVariant = true;
            $answer = $this->Answers[$this->twoStepAuthQuestion];
            unset($this->Answers[$this->twoStepAuthQuestion]);
        } else {
            $this->logger->error('Something went wrong, there must be at least one security code');

            return false;
        }
        $this->logger->debug('Security code found - "' . $answer . '", inputting it');
        $this->saveResponse();
        $input = $this->waitForElement(WebDriverBy::xpath('//input[
            @id = "idTxtBx_SAOTCC_OTC"
            or @id = "idTxtBx_SAOTCS_ProofConfirmation"
            or @id = "iOttText"
            or @id = "iVerifyText"
        ]'), 3);

        if (!$input) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $input->sendKeys($answer);
        $this->saveResponse();

        try {
            $this->logger->notice("Sending code, click 'Next' button...");
            // I sign in frequently on this device. Don't ask me for a code.
            // Don't ask me again on this device
            $this->driver->executeScript('if (checkBox = document.getElementById("idChkBx_SAOTCC_TD")) { checkBox.click(); checkBox.checked = true; }');

            $button = $this->waitForElement(WebDriverBy::xpath('//input[
                @id = "iNext"
                or @id = "idSubmit_SAOTCC_Continue"
                or @id = "idSubmit_SAOTCS_SendCode"
                or @id = "iSelectProofAction"
                or @id = "iVerifyCodeAction"
                or @id = "iVerifyIdentityAction"
            ]'), 0);

            if (!$button) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return false;
            }
            $button->click();
            $this->logger->debug('Checking for errors');
            $error = $this->waitForElement(WebDriverBy::xpath('//span[@id = "id_SAOTCS_Error_ProofConfirmation" or @id = "idSpan_SAOTCC_Error_OTC"]'), 5);
            $this->saveResponse();

            $errorIds = [
                'id_SAOTCS_Error_ProofConfirmation',
                'idSpan_SAOTCS_Error_OTC',
                'idSpan_SAOTCC_Error_OTC',
                'iVerificationErr',
                'iVerifyCodeError',
            ];
            $error = null;

            foreach ($errorIds as $ei) {
                $js = 'return (e = document.getElementById("' . $ei . '")) ? e.innerHTML : null';

                if ($e = $this->driver->executeScript($js)) {
                    $error = $e;

                    break;
                }
            }

            if ($error) {
                $this->logger->error('Security code check error: ' . $error);

                if ($emailVariant) {
                    $question = $this->securityCodeQuestionEmail;
                } elseif ($phoneVariant) {
                    $question = $this->securityCodeQuestionPhone;
                } elseif ($appVariant) {
                    $question = $this->twoStepAuthQuestion;
                } else {
                    $this->logger->error('Something went wrong');

                    return false;
                }

                switch ($error) {
                        case 'That code didn\'t work. Check the code and try again.':
                            $this->logger->notice('Clearing security code and email, new ones should be asked');
                            $id = $this->verify ? 'iShowSendCode' : 'idA_SAOTCC_Toggle';
                            $this->driver->executeScript('if (document.getElementById("' . $id . '")) document.getElementById("' . $id . '").click();');
                            unset($this->Answers[$question]);
                            $this->AskQuestion($question, $error, 'chooseMethodHowToReceiveCode');

                            break;

                        case 'The wrong code was entered. Send yourself a new code and try again.':
                        case 'Please enter the 5-digit code. The code only contains numbers.':
                        case strstr($error, "That doesn't match the alternate email associated with your account. "):
                            $this->logger->notice('Clearing security code and email, new ones should be asked');

                            if ($emailVariant) {
                                $question = $this->emailQuestion;
                            } elseif ($phoneVariant) {
                                $question = $this->phoneQuestion;
                            }
                            $this->AskQuestion($question, $error, 'chooseMethodHowToReceiveCode');

                            break;

                        case 'Please enter the 7-digit code. The code only contains numbers.':
                        case 'Enter the code to help us verify your identity.':
                            $this->holdSession();

                            $this->AskQuestion($question, $error, 'sendSecurityCode');

                            break;

                        case 'Too many invalid codes have been entered. Please try again later.':
                            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);

                            break;

                        default:
                            $this->logger->error('Parser should be modified to handle this error, terminating');
                            $this->DebugInfo = $error;

                            break;
                    }

                return false;
            }
        } catch (UnexpectedAlertOpenException $e) {
            $this->handleSecurityException($e);
        }

        return true;
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        // TODO: This should be only for email and phone codes (except exception catch)
        if ($this->driver->findElements(WebDriverBy::xpath('//*[contains(normalize-space(.), "Tired of waiting for security codes?")]'))) {
            $this->logger->notice('Got "Tired of waiting for security codes?" screen, skipping');

            try {
                $this->driver->executeScript('document.getElementById("iDoLater").click()');
                sleep(5);
                $this->saveResponse();
            } catch (UnexpectedAlertOpenException $e) {
                $this->handleSecurityException($e);
            }
        }

        $this->logger->debug('Proceeding to parsing');
        // Don't know why, but it is needed to open page twice to see account info. If it is opened only once -
        // "you are not signed in" error appears
        // switch region to US for Chromium -> ?scope=web&setmkt=en-US&setplang=en-us&setlang=en-us&FORM=W5WA&uid=DF90DA90&sid=02ADDF0E93E068D4375ED792928669AB
        try {
            $this->loadingDashboardPage();

//            if (!$this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "level-label") or @id = "status-level"] | //p[contains(@class, \'level\')]'), 0)) {
//                $this->http->GetURL("https://account.microsoft.com/rewards/dashboard");
//            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $this->loadingDashboardPage();
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
//            $this->http->GetURL("https://account.microsoft.com/rewards/dashboard");
//            $this->driver->executeScript('window.stop();');
        }

        try {
            $signIn = $this->waitForElement(WebDriverBy::xpath('//a[@id = "raf-signin-link-id"]'), 0);

            if ($signIn) {
                $signIn->click();
            }

            $logout = $this->waitForElement(WebDriverBy::xpath('
                //a[contains(@class, "level-label") or @id = "status-level"]
                | //p[contains(@class, "level")]
                | //div[contains(@class, "persona")]//p[contains(@class, "profileDescription")]
                | //div[contains(text(), "Your security info change is still pending")]
                | //div[contains(text(), "Stay signed in?")]
                | //div[@id = "suspendedAccountHeader"]
            '), 5);
            $this->saveResponse();

            if ($logout && strstr($logout->getText(), 'Your Microsoft Rewards account has been suspended.')) {
                throw new CheckException($logout->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 1357678, 1357677
            if (
                $this->http->FindSingleNode('//div[contains(text(), "Stay signed in?")]')
                && ($btnYes = $this->waitForElement(WebDriverBy::xpath('//input[@id = "idSIButton9"]'), 0))
            ) {
                $this->driver->executeScript('document.querySelector(\'input[name="DontShowAgain"]\').checked = true;');
                $this->saveResponse();
                $btnYes->click();

                $logout = $this->waitForElement(WebDriverBy::xpath('
                    //a[contains(@class, "level-label") or @id = "status-level"]
                    | //p[contains(@class, "level")]
                    | //div[contains(@class, "persona")]//p[contains(@class, "profileDescription")]
                '), 5);
                $this->saveResponse();
            }

            // AccountID: 5011642
            if (
                $this->http->FindSingleNode('//div[contains(text(), "Your security info change is still pending")]')
                && ($iLandingViewAction = $this->waitForElement(WebDriverBy::xpath('//input[@id = "iLandingViewAction"]'), 0))
            ) {
                $iLandingViewAction->click();
                $logout = $this->waitForElement(WebDriverBy::xpath('
                    //a[contains(@class, "level-label") or @id = "status-level"]
                    | //p[contains(@class, "level")]
                    | //div[contains(@class, "persona")]//p[contains(@class, "profileDescription")]
                '), 5);
                $this->saveResponse();
            }
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException window.stop: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();

            $logout = $this->waitForElement(WebDriverBy::xpath('
                //a[contains(@class, "level-label") or @id = "status-level"]
                | //p[contains(@class, "level")]
                | //div[contains(@class, "persona")]//p[contains(@class, "profileDescription")]
            '), 0);
        } finally {
            $this->logger->debug("finally");
//            $logout = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "level-label") or @id = "status-level"] | //p[contains(@class, \'level\')]'), 0);
//            $this->saveResponse();
        }

        if (empty($logout)) {
            $this->logger->error('Logout button not found, login failed due to some unknown reason');
            // Add details
            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We need just a little more info to set up your account.")]'), 0)) {
                throw new CheckException("Bing (Rewards) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($error = $this->http->FindSingleNode('//h1[@id = "error-title"]')) {
                // Uh oh, it appears your Microsoft Rewards account has been suspended.
                if (strstr($error, 'Uh oh, it appears your Microsoft Rewards account has been suspended.')) {
                    throw new CheckException('Uh oh, it appears your Microsoft Rewards account has been suspended.', ACCOUNT_PROVIDER_ERROR);
                }

                $this->logger->error("[Error]: {$error}");

                return;
            }
            // You are not signed in to Bing Rewards.
            if ($error = $this->http->FindSingleNode('
                    //div[contains(text(), "You are not signed in to Bing Rewards.")]
                    | //h1[contains(text(), "Uh oh, it appears your Microsoft Rewards account has been suspended.")]
                    | //div[@id = "suspendedAccountHeader" and contains(., "Your Microsoft Rewards account has been suspended")]
                ')
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $this->waitForElement(WebDriverBy::xpath('//a[@id = "start-earning-rewards-link" or @id = "join-for-now-link"]'), 0)
                // hard code for AccountID: 989299
                || $this->AccountFields['Login'] == 'ofdm@outlook.com'
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $this->http->FindSingleNode('//span[contains(text(), "This page isn’t working")]')
            ) {
                throw new CheckRetryNeededException(2, 0);
            }

            return;
        }

        /*if ($error = $this->http->FindPreg('#Bing\s+Rewards\s+isn\'t\s+available\s+yet\s+in\s+your\s+country\s+or\s+region\.#i'))
            throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);

        if ($error = $this->http->FindPreg('#You\s+are\s+not\s+signed\s+in\s+to\s+Bing\s+Rewards\.#i'))
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);*/

        try {
            $this->logger->debug("save response");
            $delay = 5;
            $this->logger->debug("delay: {$delay} sec");
            sleep($delay);
            $this->saveResponse();
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        $available = $this->http->FindPreg("/\"available-points\":\"([^\"]+)\",/");
        $lifetime = $this->http->FindPreg("/\"lifetime-points\":\"([^\"]+)\",/");

        if (!$available || !$lifetime) {
            return;
        }

        // Lifetime credits
        $lifetime = preg_replace('#^(.u[\w\d{}\/]+)+#', '', $lifetime);
        $lifetimeCredits =
            $this->http->FindSingleNode('//span[contains(., "' . Html::cleanXMLValue($lifetime) . '")]/b')
            ?? number_format($this->http->FindPreg("/\"lifetimePoints\":(\d+)/"))
        ;
        $this->logger->debug("lifetimeCredits: '$lifetimeCredits'");

        if (!isset($lifetimeCredits)) {
            return;
        }

        $this->SetProperty("LifetimeCredits", $lifetimeCredits);
        // Balance - Available points
        $this->SetBalance(
            $this->http->FindSingleNode('//p[contains(text(), "' . $available . '")]/../preceding-sibling::p[1]')
            ?? $this->http->FindSingleNode('//p[contains(text(), "' . $available . '")]/../following-sibling::p[1]')
        );
        // Level
        $this->SetProperty("Level", beautifulName($this->http->FindSingleNode('//a[contains(@class, "level-label") or @id = "status-level"] | //p[contains(@class, \'level\')] | //div[contains(@class, "persona")]//p[contains(@class, "profileDescription")]')));

        // AccountID: 4051375
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $balancePopup = $this->waitForElement(WebDriverBy::xpath("//a[contains(., 'Points breakdown')]"), 0);

            if ($balancePopup) {
                $balancePopup->click();
                $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Available points')]"), 5);
                sleep(2);
                $this->saveResponse();
                // Balance - Available points
                $this->SetBalance($this->http->FindSingleNode("//h1[@id = 'title']"));
            }// if ($balancePopup)
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        try {
            $this->http->GetURL("https://account.microsoft.com/profile/");
            $this->logger->debug("save response");
            sleep(3);
            $this->saveResponse();
        } catch (
            ScriptTimeoutException
            | TimeOutException
            | Facebook\WebDriver\Exception\TimeoutException
            | Facebook\WebDriver\Exception\ScriptTimeoutException
            $e
        ) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (\Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("JavascriptErrorException: " . $e->getMessage());
            $this->logger->debug("save response");
            sleep(3);
            $this->saveResponse();
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@id, 'full-name')]") ?? $this->http->FindPreg("/,\"displayName\":\"([^\"]+)/")));
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('Step: ' . $step);
//        if ($this->isNewSession())
        //			return $this->LoadLoginForm() && $this->Login();

        switch ($step) {
            case 'chooseMethodHowToReceiveCode':
                return $this->chooseMethodHowToReceiveCode();

            case 'sendSecurityCode':
                return $this->sendSecurityCode();

            case 'enteringSecurityCode':
                return $this->enteringSecurityCode();

            case 'twoStepAuth':
                return $this->twoStepAuth();

            case 'handleAccountBlock':
                return $this->handleAccountBlock();
        }

        return false;
    }

    public function countryCodeByPhoneNumber($phoneNumber)
    {
        $countryCodes = [
            'AF'   => '93',
            'AL'   => '355',
            'DZ'   => '213',
            'AD'   => '376',
            'AO'   => '244',
            'AQ'   => '672',
            'AG'   => '1',
            'AR'   => '54',
            'AM'   => '374',
            'AW'   => '297',
            'AC'   => '247',
            'AU'   => '61',
            'AT'   => '43',
            'AZ'   => '994',
            'BS'   => '1',
            'BH'   => '973',
            'BD'   => '880',
            'BB'   => '1',
            'BY'   => '375',
            'BE'   => '32',
            'BZ'   => '501',
            'BJ'   => '229',
            'BM'   => '1',
            'BT'   => '975',
            'BO'   => '591',
            'BA'   => '387',
            'BW'   => '267',
            'BV'   => '47',
            'BR'   => '55',
            'IO'   => '44',
            'BN'   => '673',
            'BG'   => '359',
            'BF'   => '226',
            'BI'   => '257',
            'CV'   => '238',
            'KH'   => '855',
            'CM'   => '237',
            'CA'   => '1',
            'KY'   => '1',
            'CF'   => '236',
            'TD'   => '235',
            'CL'   => '56',
            'CN'   => '86',
            'CX'   => '61',
            'CC'   => '61',
            'CO'   => '57',
            'KM'   => '269',
            'CG'   => '242',
            'CD'   => '243',
            'CK'   => '682',
            'CR'   => '506',
            'HR'   => '385',
            'CU'   => '53',
            'CY'   => '357',
            'CZ'   => '420',
            'DK'   => '45',
            'DJ'   => '253',
            'DM'   => '1',
            'DO'   => '1',
            'EC'   => '593',
            'EG'   => '20',
            'SV'   => '503',
            'GQ'   => '240',
            'ER'   => '291',
            'EE'   => '372',
            'ET'   => '251',
            'FK'   => '500',
            'FO'   => '298',
            'FJ'   => '679',
            'FI'   => '358',
            'FR'   => '33',
            'GF'   => '594',
            'PF'   => '689',
            'GA'   => '241',
            'GM'   => '220',
            'GE'   => '995',
            'DE'   => '49',
            'GH'   => '233',
            'GI'   => '350',
            'GR'   => '30',
            'GL'   => '299',
            'GD'   => '1',
            'GP'   => '590',
            'GU'   => '1',
            'GT'   => '502',
            'GG'   => '44',
            'GN'   => '224',
            'GW'   => '245',
            'GY'   => '592',
            'HT'   => '509',
            'VA'   => '379',
            'HN'   => '504',
            'HK'   => '852',
            'HU'   => '36',
            'IS'   => '354',
            'IN'   => '91',
            'ID'   => '62',
            'IR'   => '98',
            'IQ'   => '964',
            'IE'   => '353',
            'IM'   => '44',
            'IL'   => '972',
            'IT'   => '39',
            'JM'   => '1',
            'SJ'   => '47',
            'JP'   => '81',
            'JE'   => '44',
            'JO'   => '962',
            'KZ'   => '7',
            'KE'   => '254',
            'KI'   => '686',
            'KR'   => '82',
            'KW'   => '965',
            'KG'   => '996',
            'LA'   => '856',
            'LV'   => '371',
            'LB'   => '961',
            'LS'   => '266',
            'LR'   => '231',
            'LY'   => '218',
            'LI'   => '423',
            'LT'   => '370',
            'LU'   => '352',
            'MO'   => '853',
            'MK'   => '389',
            'MG'   => '261',
            'MW'   => '265',
            'MY'   => '60',
            'MV'   => '960',
            'ML'   => '223',
            'MT'   => '356',
            'MH'   => '692',
            'MQ'   => '596',
            'MR'   => '222',
            'MU'   => '230',
            'YT'   => '262',
            'MX'   => '52',
            'FM'   => '691',
            'MD'   => '373',
            'MC'   => '377',
            'MN'   => '976',
            'ME'   => '382',
            'MS'   => '1',
            'MA'   => '212',
            'MZ'   => '258',
            'MM'   => '95',
            'NA'   => '264',
            'NR'   => '674',
            'NP'   => '977',
            'NL'   => '31',
            'AN'   => '599',
            'NC'   => '687',
            'NZ'   => '64',
            'NI'   => '505',
            'NE'   => '227',
            'NG'   => '234',
            'NU'   => '683',
            'KP'   => '850',
            'MP'   => '1',
            'NO'   => '47',
            'OM'   => '968',
            'PK'   => '92',
            'PW'   => '680',
            'PS_0' => '970',
            'PS'   => '972',
            'PA'   => '507',
            'PG'   => '675',
            'PY'   => '595',
            'PE'   => '51',
            'PH'   => '63',
            'PL'   => '48',
            'PT'   => '351',
            'QA'   => '974',
            'CI'   => '225',
            'RE'   => '262',
            'RO'   => '40',
            'RU'   => '7',
            'RW'   => '250',
            'SH'   => '290',
            'WS'   => '685',
            'SM'   => '378',
            'ST'   => '239',
            'SA'   => '966',
            'SN'   => '221',
            'RS'   => '381',
            'SC'   => '248',
            'SL'   => '232',
            'SG'   => '65',
            'SK'   => '421',
            'SI'   => '386',
            'SB'   => '677',
            'SO'   => '252',
            'ZA'   => '27',
            'ES'   => '34',
            'LK'   => '94',
            'KN'   => '1',
            'LC'   => '1',
            'PM'   => '508',
            'VC'   => '1',
            'SD'   => '249',
            'SR'   => '597',
            'SZ'   => '268',
            'SE'   => '46',
            'CH'   => '41',
            'SY'   => '963',
            'TW'   => '886',
            'TJ'   => '992',
            'TZ'   => '255',
            'TH'   => '66',
            'TL'   => '670',
            'TG'   => '228',
            'TK'   => '690',
            'TO'   => '676',
            'TT'   => '1',
            'TA'   => '290',
            'TN'   => '216',
            'TR'   => '90',
            'TM'   => '993',
            'TC'   => '1',
            'TV'   => '688',
            'UG'   => '256',
            'UA'   => '380',
            'AE'   => '971',
            'UK'   => '44',
            'US'   => '1',
            'UM'   => '1',
            'UY'   => '598',
            'UZ'   => '998',
            'VU'   => '678',
            'VE'   => '58',
            'VN'   => '84',
            'VG'   => '1',
            'VI'   => '1',
            'WF'   => '681',
            'YE'   => '967',
            'ZM'   => '260',
            'ZW'   => '263',
        ];

        foreach ($countryCodes as $codeSymb => $codeNum) {
            if (preg_match('#^\+' . $codeNum . '#i', $phoneNumber)) {
                return [$codeSymb, $codeNum];
            }
        }

        return null;
    }

    protected function startURL()
    {
        return "https://login.live.com/login.srf?wa=wsignin1.0&rpsnv=11&ct=" . time() . "&rver=6.0.5286.0&wp=MBI&wreply=http:%2F%2Fwww.bing.com%2FPassport.aspx%3Frequrl%3Dhttp%253a%252f%252fwww.bing.com%252f%253fscope%253dweb%2526setmkt%253den-US%2526setlang%253dmatch%2526FORM%253dW5WA%2526uid%253dC3305BD0&lc=1033&id=264960";
    }

    /** @deprecated */
    protected function handleSecurityException($e)
    {
        $this->http->Log('function ' . __METHOD__);
        $this->http->Log('Exception caught, will try to handle it. Exception message:', LOG_LEVEL_ERROR);
        $this->http->Log('--------------- EXCEPTION ---------------------', LOG_LEVEL_ERROR);
        $this->http->Log($e->getMessage(), LOG_LEVEL_ERROR);
        $this->http->Log('-----------------------------------------------', LOG_LEVEL_ERROR);

        if (stripos($e->getMessage(), 'Хотя эта страница и зашифрована, отправленная вами информация будет передана по незашифрованному соединению') !== false
                or stripos($e->getMessage(), 'Although this page is encrypted, the information you have entered is to be sent over an unencrypted connection') !== false
                or stripos($e->getMessage(), 'The information you have entered on this page will be sent over an insecure connection and could be read by a third party') !== false) {
            $this->http->Log('Skipping security warning');
        } else {
            $this->http->Log('Unknown exception, terminating');

            throw $e;
        }
        sleep(5);
    }

    private function loadingDashboardPage()
    {
        $this->logger->notice(__METHOD__);
        sleep(3);
//        $this->http->GetURL("https://account.microsoft.com/rewards/dashboard?scope=web&setmkt=en-US&setplang=en-us&setlang=en-us&FORM=W5WA&uid=DF90DA90&sid=02ADDF0E93E068D4375ED792928669AB");
        $this->http->GetURL("https://rewards.bing.com/");
    }
}
