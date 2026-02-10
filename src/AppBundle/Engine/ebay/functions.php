<?php

class TAccountCheckerEbay extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $_formUrl = 'https://my.ebay.com/ws/eBayISAPI.dll?MyeBay';
    private $questionIdCode = 'Please enter your confirmation code which was sent to your phone number'; /*review*/

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = $this->_formUrl;

        return $arg;
    }

    public function IsLoggedIn()
    {
//        $this->http->GetURL("http://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&CurrentPage=Rewards&IncentiveType=MyEbayRewards&ssPageName=STRK:ME:LNLK:MERWX");
        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("http://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&currentPage=Rewards&ssPageName=STRK:ME:LNLK:MERWX", [], 20);
        $this->http->RetryCount = 2;
        */
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->http->setHttp2(true);
        $this->http->setUserAgent(\HttpBrowser::PROXY_USER_AGENT);
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL($this->_formUrl);

            $login = $selenium->waitForElement(WebDriverBy::id("userid"), 10);
//            // save page to logs
//            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
//            $this->http->SaveResponse();
//
//            if ($login) {
//                $login->sendKeys($this->AccountFields['Login']);
//                $selenium->waitForElement(WebDriverBy::id("signin-continue-btn"), 0)->click();
//
//                $pass = $selenium->waitForElement(WebDriverBy::id("pass"), 10);
//                // save page to logs
//                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
//                $this->http->SaveResponse();
//                $pass->sendKeys($this->AccountFields['Pass']);
//                sleep(1);
//                $selenium->waitForElement(WebDriverBy::id("sgnBt"), 0)->click();
//
//                $selenium->waitForElement(WebDriverBy::id("logout"), 10);
//            }
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        // distil workaround
        if ($this->attempt == 1) {
            $this->selenium();
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL($this->_formUrl);
        $this->http->RetryCount = 2;

        $this->distil();
        $this->captchaFormChallenge();

        if ($this->http->FindSingleNode('//form[@id = "distilCaptchaForm" and @class="geetest_easy"]/@class')) {
            throw new CheckRetryNeededException(2, 1);
        }

        if ($this->http->ParseForm('SecurityConfirmation')) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue("tokenText", $captcha);
            $this->http->PostForm();
        }

        if (!$this->http->ParseForm('SignInForm')) {
            if (!$this->http->ParseForm('signin-form')) {
                return false;
            }
            $inputs = $this->http->XPath->query('//form[@id = "signin-form"]//input');
            $this->logger->debug("Total {$inputs->length} inputs were found");

            if (!$inputs) {
                return false;
            }

            foreach ($inputs as $input) {
                $name = $this->http->FindSingleNode('@name', $input);
                $this->http->Inputs[$name] = $this->http->FindSingleNode('@name', $input);
                $this->http->SetInputValue($name, $this->http->FindSingleNode('@value', $input));
            }

            $this->http->FormURL = "https://www.ebay.com/signin/s";
            $this->http->SetInputValue("userid", $this->AccountFields['Login']);
            $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
            $this->http->SetInputValue("lastAttemptMethod", "password");
            $this->http->SetInputValue("recgUser", $this->AccountFields['Login']);
//            $this->http->SetInputValue("mid", "AQAAAXAiGIjaAAVjZWRkMmJmNDE3MTBhOWU4MTc4MTg2ZjFmZmYwNGNmYQAA8fTGTZVYAAwSSFRCHZittYDHjYc*");
            $this->http->SetInputValue("isRecgUser", "false");

            return true;
        }
        $this->http->SetInputValue("userid", $this->AccountFields['Login']);
        $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
        $this->http->SetInputValue("mid", $this->http->FindPreg('/"mid":"(.+?)"/'));
        $this->http->SetInputValue("usid", $this->http->FindPreg('/"tmxSessionId":"(.+?)"/'));
        $this->http->SetInputValue("keepMeSignInOption2", 'on');

        if ($login = $this->http->FindSingleNode("//input[@placeholder = 'Email or username' and @name != 'userid']/@name")) {
            $this->http->SetInputValue($login, $this->AccountFields['Login']);
        }

        if ($pass = $this->http->FindSingleNode("//input[@placeholder = 'Password' and @name != 'pass']/@name")) {
            $this->http->SetInputValue($pass, $this->AccountFields['Pass']);
        }

        return true;
    }

    public function Login()
    {
        // cookies
        $this->http->setCookie("js", "1", ".ebay.com");

        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        if ($this->distil()) {
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
        }

        if (strstr($this->http->currentUrl(), 'https://www.ebay.com/splashui/captcha?ap=1&appName=orch&ru=https')) {
            $this->captchaFormChallenge();

            $this->http->FormURL = $formURL;
            $this->http->Form = $form;

            unset($this->http->Form['i1']);
            unset($this->http->Form['fypReset']);
            unset($this->http->Form['ICurl']);
            unset($this->http->Form['src']);
            unset($this->http->Form['AppName']);
            unset($this->http->Form['srcAppId']);
            unset($this->http->Form['errmsg']);
            unset($this->http->Form['recgUser']);

            $this->http->SetInputValue("lastAttemptMethod", "password");

            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;

            $this->captchaFormChallenge();
        }

        if ($this->http->Response['code'] == 405) {
            throw new CheckRetryNeededException();
        }

        if ($redirect = $this->http->FindPreg("/http-equiv=\"Refresh\" content=\"\s*0;\s*url\s*=\s*([^\"]+)/ims")) {
            $this->http->GetURL($redirect);
        }

        //many attempts to log in, there CAPTCHA
        if ($message = $this->http->FindSingleNode('//div[@id="frameBot"]')) {
            $this->logger->debug("try to find captcha...");
            /*
             * it's wrong error, do not use it!
             */
//            throw new CheckException('Account temporary locked out. Please try again later', ACCOUNT_LOCKOUT);;
            if ($this->http->ParseForm('SecurityConfirmation')) {
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return false;
                }

                $this->http->SetInputValue("tokenText", $captcha);
                $this->http->PostForm();
            }

            if ($this->http->ParseForm('SignInForm')) {
                $this->http->SetInputValue("userid", $this->AccountFields['Login']);
                $this->http->SetInputValue("pass", $this->AccountFields['Pass']);

                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return false;
                }

                $this->http->SetInputValue("tokenText", $captcha);
                $this->http->PostForm();
            }// if ($this->http->ParseForm('SignInForm') && $captchaIframe)
        }

        // Invalid credentials
        if (($message = $this->http->FindSingleNode('//span[@class="sd-eym"]')) && $message != '') {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if (($message = $this->http->FindSingleNode('//span[@class="sd-err"]')) && $message != '') {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->checkProviderErrors();

        // Account is locked
        if ($message = $this->http->FindSingleNode('//td[@class="err-msg"]/div[2]/p[1]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        $this->skipUpdateContact();

        // AccountID: 2927426
        if (
            ($message = $this->http->FindSingleNode("//p[contains(text(), 'Call us at 1-866-303-3229 and mention security code')]"))
            && $this->http->FindSingleNode("//h1[contains(text(), 'Contact eBay Customer Service')]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Help us protect your account
        if (!$this->http->FindSingleNode("//p[contains(text(),'For a higher level of protection, make sure your personal info is up to date')]")) {
            // Confirmation code
            if ($this->parseQuestion()) {
                return false;
            }
        }

        // provider bug fix
        if ($this->http->ParseForm('SignInForm')) {
            $this->http->SetInputValue("userid", $this->AccountFields['Login']);
            $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
            $this->http->PostForm();

            $this->checkProviderErrors();

            // Help us protect your account
            if (!$this->http->FindSingleNode("//p[contains(text(),'For a higher level of protection, make sure your personal info is up to date')]")) { // Confirmation code
                if ($this->parseQuestion()) {
                    return false;
                }
            }
        } elseif ($this->http->ParseForm('signin-form')) {
            $inputs = $this->http->XPath->query('//form[@id = "signin-form"]//input');
            $this->logger->debug("Total {$inputs->length} inputs were found");

            if (!$inputs) {
                return false;
            }

            foreach ($inputs as $input) {
                $name = $this->http->FindSingleNode('@name', $input);
                $this->http->Inputs[$name] = $this->http->FindSingleNode('@name', $input);
                $this->http->SetInputValue($name, $this->http->FindSingleNode('@value', $input));
            }

            $this->http->FormURL = "https://www.ebay.com/signin/s";
            $this->http->SetInputValue("userid", $this->AccountFields['Login']);
            $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
            $this->http->PostForm();

            // todo
            if (strstr($this->http->currentUrl(), 'https://www.ebay.com/splashui/captcha?ap=1&appName=orch&ru=https')) {
                $this->captchaFormChallenge();

                $this->http->FormURL = $formURL;
                $this->http->Form = $form;

                unset($this->http->Form['i1']);
                unset($this->http->Form['fypReset']);
                unset($this->http->Form['ICurl']);
                unset($this->http->Form['src']);
                unset($this->http->Form['AppName']);
                unset($this->http->Form['srcAppId']);
                unset($this->http->Form['errmsg']);
                unset($this->http->Form['recgUser']);

                $this->http->SetInputValue("lastAttemptMethod", "password");

                $this->http->RetryCount = 0;
                $this->http->PostForm();
                $this->http->RetryCount = 2;

                $this->captchaFormChallenge();
            }

            $this->checkProviderErrors();

            // Help us protect your account
            if (!$this->http->FindSingleNode("//p[contains(text(),'For a higher level of protection, make sure your personal info is up to date')]")) { // Confirmation code
                if ($this->parseQuestion()) {
                    return false;
                }
                // AccountID: 2506466, loops on auth
                if (
                    in_array($this->AccountFields['Login'], [
                        'websellerctr',
                        'mattrdavis@live.com',
                    ])
                    && $this->http->ParseForm('signin-form')
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }
        }
        // AccountID: 2911490, loops on auth
        elseif (
            $this->AccountFields['Login'] == 'lucrios0@yahoo.com'
            && $this->http->ParseForm('sform')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkProviderErrors();

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        // Temporary measure
        if ($this->http->FindSingleNode("//*[contains(text(), 'Account Summary')]")
            && $this->http->FindPreg("/>Member\s*id\s*<\/b>\s*<span[^>]+>([^<]+)</ims")) {
            $this->SetBalanceNA();
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;

        if (!in_array($this->http->currentUrl(), [
            'https://www.ebay.com/myb/Summary',
            'https://www.ebay.com/sh/ovw',
            'https://www.ebay.com/mys/overview?MyEbay&gbh=1&source=GBH',
        ])) {
            $this->http->GetURL('https://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&CurrentPage=Rewards&IncentiveType=MyEbayRewards&ssPageName=STRK:ME:LNLK:MERWX');
        } elseif (in_array($this->http->currentUrl(), [
            'https://www.ebay.com/mys/overview?MyEbay&gbh=1&source=GBH',
        ])) {
            $this->http->GetURL('http://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&CurrentPage=Rewards');
        }
        $this->http->RetryCount = 2;
        //# Your account has been suspended
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Your account has been suspended')]")) {
            throw new CheckException("Your account has been suspended.", ACCOUNT_PROVIDER_ERROR);
        }
        // Access is allowed
        if ($this->http->FindSingleNode("//span[contains(text(), 'Earned this period')]/following-sibling::span[1]", null, true, "/([\d.]+)/ims")
            || $this->http->FindSingleNode("//b[contains(text(), 'Member id')]/following-sibling::span[1]", null, true, null, 0)
            || $this->http->FindSingleNode("//b[contains(text(), 'Nome de usuário')]/following-sibling::span[1]", null, true, null, 0)
            || $this->http->FindSingleNode("//b[contains(text(), 'Nombre de usuario')]/following-sibling::span[1]", null, true, null, 0)
            || $this->http->FindSingleNode("//b[contains(text(), 'Логин участника')]/following-sibling::span[1]", null, true, null, 0)
            || $this->http->FindSingleNode("//div[@class = 'sh-member-badge']/a/text()[1]")
            || $this->http->FindSingleNode("//a[@class = 'mbg-id']/text()[last()]")
            || $this->http->FindSingleNode("//a[@class = 'm-top-nav__username']", null, true, "/(.+) user ID/")
            || $this->http->FindSingleNode("//div[@id = 'meh-badage']/div/a/text()[1]")
            || $this->http->FindSingleNode("//span[@id = 'useridhidden']")
            || $this->http->FindSingleNode('//div[@id = "me-badge"]')
            || ($this->http->FindSingleNode("//h2[contains(text(), 'My Account')]") && $this->http->FindPreg("/>Member\s*id\s*<\/b>\s*<span[^>]+>([^<]+)</ims"))
            || $this->http->FindSingleNode('//span[contains(text(), "So far you\'ve earned:")]/following-sibling::span')
        ) {
            return true;
        }

        return false;
    }

    public function checkAnswers()
    {
        $this->http->Log("state: " . var_export($this->State, true));

        if (isset($this->LastRequestTime)) {
            $timeFromLastRequest = time() - $this->LastRequestTime;
        } else {
            $timeFromLastRequest = SECONDS_PER_DAY * 30;
        }
        $this->http->Log("time from last code request: " . $timeFromLastRequest);
//        if ($timeFromLastRequest > SECONDS_PER_DAY && count($this->Answers) > 0) {
//            $this->http->log("resetting answers, expired");
//            unset($this->Answers[$this->question]);
//        }
    }

    public function sendCodeToPhone()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("sending confirmation code to phone");

        // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        // Sending Confirmation Code, step 1
        if ($this->http->ParseForm("fullscale")) {
            $this->logger->notice("ebay. Sending Confirmation Code, step 1");
//            $this->http->PostForm();
            // Sending Confirmation Code, step 2
            if ($this->http->FindSingleNode('(//h3[
                    contains(text(), "Choose how you\'d like to receive your confirmation code")
                    or contains(text(), "Escolha de que forma você prefere receber seu código de confirmação")
                    or contains(text(), "Выберите способ получения кода подтверждения.")
                ])[1]')
                && $this->http->ParseForm("fullscale")
            ) {
                $this->logger->notice("ebay. Sending Confirmation Code, step 2");
                // email
                $email = $this->http->FindSingleNode("//form[@id = 'fullscale']//input[@name = 'selectedOption' and @value = 'email']/@value");
                // phone number
                $phoneNumber = $this->http->FindSingleNode("//form[@id = 'fullscale']//input[@name = 'numSelected' and @checked = 'checked']/following-sibling::label");

                if (!isset($phoneNumber)) {
                    $phoneNumber = $this->http->FindSingleNode("//form[@id = 'fullscale']//input[@name = 'numSelected' and @checked = 'checked']/parent::li");
                }
                $this->logger->debug("phone number: $phoneNumber");

                if (isset($email)) {
                    $this->http->SetInputValue('selectedOption', 'email');
                } elseif (isset($phoneNumber)) {
                    $this->http->SetInputValue('contactBy', 'text');
                }

                sleep(3);

                if ($this->http->PostForm(["User-Agent" => HttpBrowser::PROXY_USER_AGENT, 'Upgrade-Insecure-Requests' => '1'])) {
                    $this->logger->notice("code form received");

                    // one more form
                    $phoneNumber = $this->http->FindSingleNode("//form[@id = 'fullscale']//input[@name = 'numSelected' and @checked = 'checked']/parent::li");

                    if ($this->http->FindSingleNode('(//h3[contains(text(), "Choose how you\'d like to receive your confirmation code")])[1]') && $this->http->ParseForm("fullscale") && $phoneNumber) {
                        $this->logger->debug("phone number: $phoneNumber");
                        $this->http->SetInputValue('contactBy', 'text');
                        sleep(3);
                        $this->http->PostForm(["User-Agent" => HttpBrowser::PROXY_USER_AGENT, 'Upgrade-Insecure-Requests' => '1']);
                    // sq after 'Choose how you'd like to receive your confirmation code'
                    } elseif ($this->http->ParseForm("securityQuestionForm")) {
                        return false;
                    }

                    return true;
                }// if ($this->http->PostForm())
            }
        }// if ($this->http->ParseForm("fullscale"))

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->logger->notice(__METHOD__);
        $sendCodeToPhone = false;
        // confirmation code
        if (
            $this->http->FindSingleNode('//p[
                contains(text(), "We\'ll send a message to the option you select.")
                or contains(text(), "Enviaremos uma mensagem pela opção que você escolher.")
                or contains(text(), "Мы отправим вам сообщение.")
            ]') != null
        ) {
            $this->logger->notice("Confirmation code");
            $this->checkAnswers();
            $question = $this->questionIdCode;
            $sendCodeToPhone = $this->sendCodeToPhone();

            if (!$this->http->ParseForm("verifyCodeForm")) {
                $this->logger->error("parse code form failed");
                // We've tried contacting you too many times, so you'll need to wait 24 hours before you try again.
                if ($message = $this->http->FindPreg("/(We&#039;ve tried contacting you too many times, so you&#039;ll need to wait 24 hours before you try again\.)/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // We are having technical difficulties
                if ($message = $this->http->FindPreg('/We are having technical difficulties/')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                /*
                 * Confirm your account
                 *
                 * Answer a security question
                 */
                if (!$this->http->ParseForm("securityQuestionForm")) {
                    return false;
                }
                $questions = $this->http->FindNodes("//label[contains(@for, 'questionId') and not(@id)]");

                if (!$questions) {
                    $questions = $this->http->FindNodes("//h3[
                        contains(text(), 'Answer a security question')
                        or contains(text(), 'Responda a uma pergunta de segurança')
                        or contains(text(), 'Ответьте на секретный вопрос')
                    ]/following-sibling::p");
                }

                foreach ($questions as $question) {
                    $this->logger->debug("Question: {$this->http->FindSingleNode("//label[contains(normalize-space(text()), '{$question}')]")}");
                    $this->logger->debug("Value: {$this->http->FindSingleNode("//label[contains(normalize-space(text()), '{$question}')]/parent::span/input/@value")}");
                    $value = $this->http->FindSingleNode("//label[contains(normalize-space(text()), '{$question}')]/parent::span/input/@value");

                    if (!isset($value)) {
                        $value = $this->http->FindSingleNode("//h3[
                            contains(text(), 'Answer a security question')
                            or contains(text(), 'Responda a uma pergunta de segurança')
                            or contains(text(), 'Ответьте на секретный вопрос')
                        ]/following-sibling::input[@id = 'questionId']/@value");
                    }// if (!isset($value))
                    $this->logger->debug("parse question: {$value}");

                    if (count($questions) == 2 && $value == 0 && $question == 'What other eBay user ID is associated with your current address?') {
                        $this->logger->notice("skip question 'What other eBay user ID is associated with your current address?'");

                        continue;
                    }

                    $this->State[$question] = $value;

                    if (!isset($this->Answers[$question])) {
                        $this->AskQuestion($question, null, "Question");
                        // break; todo: ask only one question now
                        return true;
                    }
                }// foreach ($questions as $question)

                if (!$questions) {
                    return false;
                }
            }// if (!$this->http->ParseForm("verifyCodeForm"))
        }// if ($this->http->FindSingleNode('//p[contains(text(), "We\'ll send a message to the option you select.")]')...
        // 2 step verification
        if (!isset($question) && !$sendCodeToPhone) {
            $this->logger->notice("2 step verification");
            $question = $this->http->FindSingleNode("//b[contains(text(), 'Enter your six-digit security code')] | //div[contains(text(), 'Enter PIN')]");

            if (!isset($question)) {
                // We're texting a security code to
                $question = $this->http->FindSingleNode("//span[contains(text(), 're texting a security code to') or contains(text(), 'We sent a text with a security code to')]");

                if ($question) {
                    $question .= " Please enter security code";

                    throw new CheckException("It seems that you set up two-factor authentication. Please use your mobile eBay app to receive a notification for sign-in approval.", ACCOUNT_PROVIDER_ERROR); //refs #19354
                }
            }

            if (
                (!isset($question) || !$this->http->ParseForm("SignIn2FA"))
                && $this->http->FindSingleNode('//h1[contains(text(), "Verify your contact info")]')
                && ($referenceId = $this->http->FindPreg("/params=([^&]+)/", false, $this->http->currentUrl()))
            ) {
                $email = $this->http->FindPreg("/\"EMAIL_WITH_CODE\",\"value\":\"([^\"]+)/");
                $phone = $this->http->FindPreg("/\"SMS_WITH_CODE\",\"value\":\"([^\"]+)/");

                if (!$email && !$phone) {
                    return false;
                }

                $authMethod = "EMAIL_WITH_CODE";

                if (!$email) {
                    $authMethod = "SMS_WITH_CODE";
                }

                $data = [
                    "operation"   => "startAuth",
                    "referenceId" => $referenceId,
                    "useCase"     => "STEP_UP_AUTH",
                    "authMethod"  => $authMethod,
                    "srt"         => $this->http->FindPreg("/stepUpAuthAjaxCsrf\":\"([^\"]+)/"),
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://accounts.ebay.com/acctxs/stepupauth-verification", $data);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
                $status = $response->status ?? null;

                if ($status == "OK") {
                    $question = "We emailed a security code to {$email}. If you can’t find it, check your spam folder.";

                    if (!$email) {
                        $question = "We sent a security code to your phone {$phone}.";
                    }

                    $this->State['form'] = $data;

                    $this->Question = $question;
                    $this->ErrorCode = ACCOUNT_QUESTION;
                    $this->Step = "Question";

                    return true;
                }

                return false;
            }

            if (!isset($question) || !$this->http->ParseForm("SignIn2FA")) {
                // We sent a notification to your device.
                if ($this->http->FindSingleNode("//span[contains(text(), 'We sent a notification to your device.')]")
                || $this->http->FindSingleNode("//span[contains(text(), 'We sent another notification to your device.')]")) {
                    $this->DebugInfo = 'waitForApprove by device';

                    return $this->waitForApprove();
                }
                $this->logger->error("[2 step verification]: failed to find answer form or question");

                return false;
            }// if (!isset($question) || !$this->http->ParseForm("signInForm"))

            // State
            $this->State["CodeSent"] = true;
            $this->State["CodeSentDate"] = time();
        }// if (!isset($question) || !$sendCodeToPhone)

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        // Confirmation code
        if (
            isset($this->Question)
            && (
                strstr($this->Question, "We emailed a security code to")
                || strstr($this->Question, "We sent a security code to your phone")
            )
        ) {
            $data = $this->State['form'];
            $data["operation"] = "verify";
            $data["secret"] = $this->Answers[$this->Question];

            unset($this->Answers[$this->Question]);
            unset($data["authMethod"]);

            $this->http->PostURL("https://accounts.ebay.com/acctxs/stepupauth-verification", $data);
            $response = $this->http->JsonLog();

            $message = $response->message ?? null;

            // {"message":"You are trying with incorrect code. Please try again or retry the process.","errorId":40025}
            if ($message == "You are trying with incorrect code. Please try again or retry the process.") {
                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }

//            $data = [
//                "srt"         => $this->State['form']['srt'],
//                "referenceId" => $this->State['form']['referenceId'],
//                "userid"      => $this->AccountFields['Login'],
//                "i1"          => "-1",
//                "pageType"    => "-1",
//            ];
//            $this->http->PostURL("https://www.ebay.com/signin/s", $data);

            $this->loginSuccessful();

            if ($this->http->ParseForm('signin-form')) {
                $inputs = $this->http->XPath->query('//form[@id = "signin-form"]//input');
                $this->logger->debug("Total {$inputs->length} inputs were found");

                if (!$inputs) {
                    return false;
                }

                foreach ($inputs as $input) {
                    $name = $this->http->FindSingleNode('@name', $input);
                    $this->http->Inputs[$name] = $this->http->FindSingleNode('@name', $input);
                    $this->http->SetInputValue($name, $this->http->FindSingleNode('@value', $input));
                }

                $this->http->FormURL = "https://www.ebay.com/signin/s";
                $this->http->SetInputValue("userid", $this->AccountFields['Login']);
                $this->http->SetInputValue("pass", $this->AccountFields['Pass']);
                $this->http->SetInputValue("lastAttemptMethod", "password");
                $this->http->SetInputValue("recgUser", $this->AccountFields['Login']);
                $this->http->SetInputValue("isRecgUser", "false");
                $this->http->PostForm();
            }

            return $this->loginSuccessful();
        } elseif (isset($this->Question) && (strstr($this->Question, 'Please enter your confirmation code'))) {
            $this->logger->notice("Entering confirmation code");
//            $this->logger->debug(var_export($this->http->Response['body'], true), ['pre' => true]);
            $this->logger->debug("ok, proceed to code entering");
            $this->http->SetInputValue("code", $this->Answers[$this->Question]);
            sleep(2);

            if ($this->http->PostForm()) {
                unset($this->Answers[$this->Question]);
                $this->logger->debug("the code was sent");
                // Sorry, we couldn't complete your request. Try again later.
                if ($message = $this->http->FindSingleNode('//span[contains(text(), "Sorry, we couldn\'t complete your request. Try again later.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Sorry! We're currently experiencing technical difficulties and are unable to complete the process at this time.
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry! We\'re currently experiencing technical difficulties and are unable")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // The confirmation code you entered is different from the one we sent
                if ($error = $this->http->FindSingleNode("//p[contains(text(), 'The confirmation code you entered is different from the one we sent')]")) {
                    $this->AskQuestion($this->Question, $error, "Question");

                    return false;
                }// if ($this->http->FindSingleNode("//div[contains(text(), 'Your security code is incorrect')]"))

                $this->skipUpdateContact();

                // JS Redirect
                if ($location = $this->http->FindPreg("/window.location=\"([^\"]+)/")) {
                    $this->logger->debug("JS Redirect");
                    $this->http->GetURL($location);
                }// if ($location = $this->http->FindPreg("/window.location=\"([^\"]+)/"))

                return true;
            }// if ($this->http->PostForm())
        }// if (isset($this->Question) && strstr($this->Question, 'Please enter your confirmation code'))
        // Security code    // refs #12757, 19354
        elseif (
            in_array($this->Question, ['Enter your six-digit security code', 'Enter PIN'])
            || strstr($this->Question, 're texting a security code to')
            || strstr($this->Question, 'We sent a text with a security code to')
        ) {
            $this->logger->notice("Entering security code");
            $this->sendNotification("Entering security code - refs #19354 // RR");
            /*
            $this->http->SetInputValue("otp1", $this->Answers[$this->Question]);
            */
            $this->http->FormURL = 'https://www.ebay.com/signin/srv/mfa';

            $this->http->SetInputValue("twoFaCode", $this->Answers[$this->Question]);
            $this->http->SetInputValue("action", "validate");
            $this->http->SetInputValue("referenceId", $this->http->Form['refid']);
            $this->http->SetInputValue("returnUrl", $this->http->Form['returnUrl'] ?? 'https://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&&CurrentPage=Rewards&ssPageName=STRK%3AME%3ALNLK%3AMERWX&guest=1');

            $this->http->unsetInputValue('userid');
            $this->http->unsetInputValue('refid');
            $this->http->unsetInputValue('showWebAuthnOptIn');
            $this->http->unsetInputValue('otp1');
            $this->http->unsetInputValue('i1');
            $this->http->unsetInputValue('uide');
            $this->http->unsetInputValue('pageType');
            $this->http->unsetInputValue('pin');

            unset($this->Answers[$this->Question]);

            $headers = [
                "Accept"           => "*/*",
                "X-Requested-With" => "XMLHttpRequest",
                "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                "User-Agent"       => \HttpBrowser::PROXY_USER_AGENT,
            ];

            $this->http->RetryCount = 0;

            if (!$this->http->PostForm()) {
                return false;
            }
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            $message = $response->message ?? null;

            if (strstr($message, "That's not a match. Please try again.")) {
                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }// if (strstr($message, "That's not a match. Please try again."))
            // Your security code is incorrect
            // That's not a match. Please try again.
            if ($error = $this->http->FindSingleNode("//div[contains(text(), 'Your security code is incorrect')] | //span[@id = 'OTP_ERROR_d' and contains(text(), 's not a match. Please try again.')]")) {
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }// if ($this->http->FindSingleNode("//div[contains(text(), 'Your security code is incorrect')]"))
            // Page redirect
            if ($redirect = $this->http->FindSingleNode("//a[contains(text(), 'Continue')]/@href")) {
                $this->logger->debug("Page redirect -> {$redirect}");
                $this->http->NormalizeURL($redirect);
                $this->http->GetURL($redirect);
            }// if ($redirect = $this->http->FindSingleNode("//a[contains(text(), 'Continue')]/@href"))
            // Open eBay Bucks page
            if ($eBayBucksPage = $this->http->FindPreg('/a href=\"([^\"]+)[^>]+>eBay Bucks/')) {
                $this->logger->debug("Open eBay Bucks page -> {$eBayBucksPage}");
                $this->http->NormalizeURL($eBayBucksPage);
                $this->http->GetURL($eBayBucksPage);
            }// if ($eBayBucksPage = $this->http->FindPreg('/a href=\"([^\"]+)[^>]+>eBay Bucks/'))
            // We ran into a problem. Please try again.
            if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We ran into a problem. Please try again.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Something went wrong on our end. We’ll get things up and running again shortly.
            if ($message = $this->http->FindPreg('/Something went wrong on our end. We’ll get things up and running again shortly\./')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// elseif (in_array($this->Question, ['Enter your six-digit security code', 'Enter PIN']))
        else {
            $this->logger->notice("Just security question");
            $this->logger->debug(var_export($this->State, true), ['pre' => true]);
            $this->http->SetInputValue("answer", $this->Answers[$this->Question]);

            if (isset($this->State[$this->Question])) {
                $this->http->SetInputValue("questionId", $this->State[$this->Question]);
            }

            if (!$this->http->PostForm()) {
                return false;
            }
            // The answer you entered doesn't match our records
            if ($error = $this->http->FindSingleNode('//p[contains(text(), "The answer you entered doesn\'t match our records")]')) {
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }
            // You have answered incorrectly too many times, so you'll need to wait 24 hours before you try again.
            if ($error = $this->http->FindSingleNode('//p[contains(text(), "You have answered incorrectly too many times, so you\'ll need to wait 24 hours before you try again.")]')) {
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }
            // We're sending you a confirmation code
            $email = $this->http->FindSingleNode("//p[contains(text(), 'A confirmation code was sent to')]", null, true, "/was sent to\s*(.+)\.\s*If you don/");

            if ($email && $this->http->ParseForm("verifyCodeForm")) {
                $question = "Please enter your confirmation code which was sent to {$email}";
                $this->AskQuestion($question, null, "Question");

                return false;
            }// if ($question && $this->http->ParseForm("verifyCodeForm"))
        }

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() == 'https://www.ebay.com/myb/Summary') {
            $this->redirectToRewardsPage();
        }

        $balance =
            // Balance - eBay Bucks
            $this->http->FindSingleNode("//span[contains(text(), 'Earned this period')]/following-sibling::span[1]", null, true, "/([\d.]+)/ims")
            // eBay Bucks -> So far you've earned
            ?? $this->http->FindSingleNode("//div[@id = 'Summary_Panel_ct']//span[@class = 'sValue']", null, true, "/([\d.]+)/ims")
            ?? $this->http->FindSingleNode('//span[contains(text(), "So far you\'ve earned:")]/following-sibling::span', null, true, "/([\d.]+)/ims")
        ;

        if (isset($balance)) {
            $this->SetBalance($balance);
        }

        // UserID
        $this->SetProperty("UserID",
            $this->http->FindSingleNode("//b[contains(text(), 'Member id')]/following-sibling::span[1]", null, true, null, 0)
            ?? $this->http->FindSingleNode("//b[contains(text(), 'Логин участника')]/following-sibling::span[1]", null, true, null, 0)
            ?? $this->http->FindSingleNode("//b[contains(text(), 'Nome de usuário')]/following-sibling::span[1]", null, true, null, 0)
            ?? $this->http->FindSingleNode("//b[contains(text(), 'Nombre de usuario')]/following-sibling::span[1]", null, true, null, 0)
            ?? $this->http->FindSingleNode("//div[@class = 'sh-member-badge']/a/text()[1]")
            ?? $this->http->FindSingleNode("//a[@class = 'mbg-id']/text()[last()]")
            ?? $this->http->FindSingleNode("//div[@id = 'meh-badage']/div/a/text()[1]")
            ?? $this->http->FindSingleNode("//a[@class = 'm-top-nav__username']", null, true, "/(.+) user ID/")
            ?? $this->http->FindPreg("/>Member\s*id\s*<\/b>\s*<span[^>]+>([^<]+)</ims")
            ?? $this->http->FindSingleNode("//span[@id = 'useridhidden']")
            ?? $this->http->FindPreg("/,id:(?:'|\")([^\'\"]+)/")
        );

        //# Next payout
        $this->SetProperty("NextPayout", $this->http->FindSingleNode("//span[contains(text(), 'Next payout')]/following-sibling::span[1]"));

        // eBay Bucks certificate    // refs #11749
        // Value
        $certificateValue = $this->http->FindSingleNode("//div[@id = 'CertificatePanel_ct']//span[@class = 'cValue']");
        // Code
        $certificateCode = $this->http->FindSingleNode("//div[@id = 'CertificatePanel_ct']//div[b[contains(text(), 'Code:')]]/text()[last()]");
        // Expires
        $certificateExpires = $this->http->FindSingleNode("//div[@id = 'CertificatePanel_ct']//div[b[contains(text(), 'Expires:')]]/text()[last()]");

        if (isset($certificateCode, $certificateValue, $certificateExpires) && strtotime($certificateExpires)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                "Code"           => "ebayCertificate{$certificateCode}",
                "DisplayName"    => "Certificate: {$certificateCode}",
                "Balance"        => $certificateValue,
                "ExpirationDate" => strtotime($certificateExpires),
            ]);
        }// if (isset($certificateCode, $certificateValue, $certificateExpires) && strtotime($certificateExpires))

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (isset($this->Properties['UserID'])
                && (
                    !$this->http->FindPreg("/(eBay Bucks)/ims")
                    || $this->http->FindPreg("/(You don\'t have any eBay Bucks certificates to spend\.)/ims")
                    || $this->http->FindSingleNode('//span[contains(text(), "So far you\'ve earned:")]/following-sibling::span') === '-'// AccountID: 5198286
                    || $this->http->FindSingleNode('//div[@class = "counter__number"]') === '0'// AccountID: 4795740
                )
            ) {
                $this->logger->notice("eBay Bucks is not found");
                $this->SetBalanceNA();
            }
            //# Some of your information is not available at this time. Please try again later.
            elseif (!isset($balance) && isset($this->Properties['UserID'])
                    && ($message = $this->http->FindSingleNode("//div[contains(text(), 'Some of your information is not available at this time')]"))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Rack up eBay Bucks now and reap the rewards when you receive your next Certificate
            elseif (isset($this->Properties['UserID'])) {
                if (in_array($this->Properties['UserID'], ['festek', 'fsimao14', 'harwhaler'])) {
                    $this->SetBalanceNA();

                    return;
                }

                if ($this->redirectToRewardsPage()) {
                    if ($message = $this->http->FindPreg("/(Rack up eBay Bucks now and reap the rewards when you receive your next Certificate\.)/ims")) {
                        $this->SetBalanceNA();
                    }
                    // The eBay Bucks Rewards Program in Canada has now ended
                    if ($message = $this->http->FindPreg("/\"(The eBay Bucks Rewards Program in Canada has now ended[^<\"]+)/ims")) {
                        $this->SetWarning($message);
                    }
                    //# Your account is suspended
                    if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your account is suspended')]")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    // Balance - eBay Bucks
                    $this->SetBalance(
                        $this->http->FindPreg("/ebay bucks balance<\/div>\s*<div id=.\"ndal.\" style=.\"display:none.\">([^<]+)
/")
                        ?? $this->http->FindSingleNode('//span[contains(text(), "So far you\'ve earned:")]/following-sibling::span', null, true, "/([\d.]+)/ims")
                    );

                    /*
                    // strange provider bug workaround
                    if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['UserID'])) {
                        throw new CheckRetryNeededException(2, 1);
                    }
                    */
                }// if ($this->redirectToRewardsPage())
            }// elseif (isset($this->Properties['UserID']))
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $captchaUrl = $this->http->FindSingleNode("//iframe[@id = 'frameBot_img']/@src");

        if (!$captchaUrl) {
            return false;
        }
        $tokenString = $this->http->FindPreg("/tokenString=([^\&]+)/", false, $captchaUrl);
        $this->logger->debug("tokenString -> {$tokenString}");
        $this->http->SetInputValue("tokenString", $tokenString);
        $file = $this->http->DownloadFile($captchaUrl, "jpg");
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm' and @class = 'recaptcha2']//div[@class = 'g-recaptcha']/@data-sitekey");
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

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    protected function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
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

        if ($this->http->ParseForm(null, "//form[@id = 'distilCaptchaForm' and @class = 'recaptcha2']")) {
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
            $this->http->PostForm(["Referer" => $referer]);
            $this->http->RetryCount = 2;
            $this->http->FilterHTML = true;
        } elseif ($this->http->FindSingleNode('//form[@id = "distilCaptchaForm" and @class="geetest_easy"]/@class')) {
            if (!$this->parseGeetestCaptcha($referer)) {
                return false;
            }
        } else {
            return false;
        }

        if ($this->http->Response['code'] == 204) {
            $this->http->RetryCount = 0;
            $this->http->GetURL($referer);
            $this->http->RetryCount = 2;
        }

        $this->getTime($startTimer);

        return true;
    }

    private function captchaFormChallenge()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->ParseForm("captcha_form")) {
            return false;
        }
        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;
        $currentUrl = $this->http->currentUrl();

        $this->http->PostURL('https://www.ebay.com/captcha/init',
            '{"appName":"orch","provider":"hcaptcha","wisbProvider":""}', [
                'Accept'       => 'application/json, text/plain, */*',
                'Content-Type' => 'application/json',
            ]);
        $response = $this->http->JsonLog();
        //$captcha = $this->parseReCaptcha("6LcPaXEUAAAAAGky5kHGTMxR1UEEqJ-tyBIrmfkV");
        $captcha = $this->parseFunCaptcha($response->siteKey, $currentUrl, true);

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $this->http->SetInputValue("h-captcha-response", $captcha);
//        $this->http->SetInputValue("captchaTokenInput", urlencode('{"guid":"5d3de98e-606a-46a2-bf2c-449441830852","provider":"recaptcha_v2","appName":"orch","token":"' . $captcha . '"}'));
        $this->http->SetInputValue("captchaTokenInput", urlencode('{"guid":"65700642-8f2e-4ce8-90cb-cb459d6e298b","provider":"hcaptcha","appName":"sgninui","token":"' . $captcha . '"}'));

        $headers = [
            "Accept"  => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            'Referer' => $currentUrl,
        ];
        $this->http->PostForm($headers);

        return true;
    }

    private function parseFunCaptcha($key, $currentUrl, $retry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        /*$key = $this->http->FindPreg('/^.+?hcaptcha.+?&sitekey=([\w\-]+)/');

        if (!$key) {
            return false;
        }*/

        $this->increaseTimeLimit(300);

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'hcaptcha',
            "pageurl" => $currentUrl,
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function skipUpdateContact()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode("//p[contains(text(), 'For a higher level of protection, make sure your personal info is up to date')]")) {
            return;
        }
        $this->logger->notice("force redirect");
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://reg.ebay.com/reg#");
        $this->http->RetryCount = 2;

        $this->http->GetURL('https://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&CurrentPage=Rewards&IncentiveType=MyEbayRewards&ssPageName=STRK:ME:LNLK:MERWX');
    }

    private function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);

        $message = $this->http->FindSingleNode('//p[@id = "errf" or @id = "signin-error-msg"]');

        if (!$message) {
            $message = $this->http->FindSingleNode('//p[@id = "errormsg"]');
        }

        if ($message) {
            $this->logger->error($message);

            if (
                strstr($message, "Oops, that's not a match.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "For your security, we've temporarily locked your account. Please reset your password to sign in to your account.")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
        }
    }

    private function parseGeetestCaptcha($referer)
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->logger->error("geetest failed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->logger->debug("verifyUrl => $verifyUrl");
        $this->http->NormalizeURL($verifyUrl);
        $this->logger->debug("verifyUrl => $verifyUrl");
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
            'isAjax'            => 1,
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Referer"    => $referer,
        ];
        $this->http->PostURL($verifyUrl, $payload, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    private function waitForApprove()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("waiting for account access confirmation");

        // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }
        $headers = [
            'Accept'                => '*/*',
            'Content-Type'          => 'application/x-www-form-urlencoded; charset=UTF-8',
            'x-ebay-requested-with' => 'XMLHttpRequest',
            'x-requested-with'      => 'XMLHttpRequest',
        ];
        $data = [
            'action'      => 'status',
            'referenceId' => $this->http->FindPreg('/,"contId":"([\w\-]+)",/'), //fFuOLVjWbWYY19Pg8Kv7A
            'srt'         => $this->http->FindSingleNode("//input[@id='srt3']/@value"),
        ];
        $dataMfa = [
            'action'      => 'resend',
            'referenceId' => $this->http->FindPreg('/,"contId":"([\w\-]+)",/'), //fFuOLVjWbWYY19Pg8Kv7A
            'srt'         => $this->http->FindSingleNode("//input[@id='srt4']/@value"),
        ];

        if (!isset($data['referenceId'], $data['srt'])) {
            return true;
        }
        $referer = $this->http->currentUrl();
        $userId = $this->http->FindPreg('/"userId":"(.+?)",/');
        //$refId = $this->http->FindPreg('/\?id=(.*?)&/', false, $referer);
        $uide = $this->http->FindPreg('/"uide":"(.+?)",/');
        $srt = $this->http->FindPreg('/"csrfToken":"(.+?)",/');

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.ebay.com/signin/srv/mfa', $dataMfa, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->referenceId)) {
            return true;
        }

        for ($i = 0; $i < 12; $i++) {
            $this->http->RetryCount = 0;
            $this->http->PostURL('https://www.ebay.com/signin/srv/contingency', $data, $headers, 10);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;

            if (!$this->http->FindPreg('/\{\}/')) {
                $this->http->PostURL('https://www.ebay.com/signin/s', [
                    'userid'    => $userId,
                    'refid'     => $data['referenceId'],
                    'uide'      => $uide,
                    'i1'        => '-1',
                    'pageType'  => '3984',
                    'returnUrl' => 'https://my.ebay.com/ws/eBayISAPI.dll?MyEbayBeta&MyEbay=&gbh=1&guest=1',
                    'srt'       => $srt,
                ], [
                    'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Referer'      => $referer,
                ]);

                return false;
            } else {
                $delay = 7;
                $this->logger->debug("delay: {$delay}");
                sleep($delay);
            }
        }

        if ($this->http->FindPreg('/\{\}/')) {
            throw new CheckException('We sent a notification to your device, please approve it to sign in to your account.', ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    private function redirectToRewardsPage()
    {
        $this->logger->notice(__METHOD__);
        $link = $this->http->FindSingleNode("//a[contains(text(), 'eBay Buck')]/@href");

        if (!$link) {
            $link = $this->http->FindPreg("/\"url\":\"([^\"]+)/ims");
        }

        $this->logger->debug($link);

        if ($link && !strstr($link, '.jpg')) {
            $link = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($match) {
                return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
            }, $link);
            $this->logger->debug($link);
            $this->http->NormalizeURL($link);
            $this->logger->debug($link);

            $this->http->GetURL($link);

            return true;
        }

        return false;
    }
}
