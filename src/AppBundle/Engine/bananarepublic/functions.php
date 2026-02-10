<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerBananarepublic extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const XPATH_CLOSED = "//div[contains(text(), 'This account has been closed. No further purchases will be allowed.')]";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $data = null;

    private $newDesign = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;

        // captcha workaround
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $this->UseSelenium();
        $this->useChromium();
        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""               => "Select your brand", /*review*/
            "GapCard"        => "GapCard",
            "BananaRepublic" => "Banana Republic card",
            "OldNavy"        => "Old Navy card",
            "Athleta"        => "Athleta card",
        ];
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $url = $this->getRightURL(false);
        $arg['RedirectURL'] = $url;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && (strstr($properties['SubAccountCode'], "bananarepublicPoints"))) {
            return parent::FormatBalance($fields, $properties);
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function IsLoggedIn()
    {
        //$this->http->GetURL("https://chaseonline.chase.com/MyAccounts.aspx");
        //return $this->http->FindSingleNode("//td[contains(text(), 'My Accounts')]") !== null;
        return false;
    }

    public function getRightURL($setHeaders = true)
    {
        // refs #8303
        $subDomain = $this->getSubDomain();

        if ($setHeaders) {
            $this->http->setDefaultHeader("Origin", "https://{$subDomain}.barclaysus.com");
        }

        return "https://{$subDomain}.barclaysus.com/servicing/authenticate";
    }

    public function getSubDomain()
    {
        switch ($this->AccountFields["Login2"]) {
            case 'GapCard':
                $subDomain = 'gap';

                break;

            case 'OldNavy':
                $subDomain = 'oldnavy';

                break;

            case 'Athleta':
                $subDomain = 'athleta';

                break;

            default:
                $subDomain = 'bananarepublic';

                break;
        }

        return $subDomain;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $url = $this->getRightURL();
        $this->http->GetURL($url);

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "loginButton"]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $this->logger->notice('remember Me');
        $this->driver->executeScript("document.querySelector('input[id = \"rememberUserNameCheckbox\"]').checked = true;");

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("
                //h2[contains(text(), 'Our site is down for maintenance')]
                | //p[contains(text(), 'Our System is down for maintenance')]
                | //font[contains(text(), 'The Synchrony Bank Account Servicing System is offline for regular maintenance')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//img[contains(@src, '_decomm.png')]/@src")) {
            throw new CheckException("Our site is down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        // HTTP Status 404
        if ($this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //div[@id = "b-secondary-nav"]//a[contains(text(), "Log out")]
            | //div[@id = "alertBody"]/h1
            | //div[not(contains(@class, "hidden"))]/span[contains(@class, "error")]
            | //div[not(contains(@class, "hidden"))]/p[contains(@class, "error")]
            | //h3[contains(text(), "Answer your security questions")]
            | //div[contains(text(), "re sorry, but for security reasons online access to your account is unavailable.")]
        '), 15);
        $this->saveResponse();

        if ($this->parseQuestionSelenium(true)) {
            return false;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        // Access is allowed
        if ($this->http->FindSingleNode('//div[@id = "b-secondary-nav"]//a[contains(text(), "Log out")]')) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('
                //div[@id = "alertBody"]/h1
                | //div[not(contains(@class, "hidden"))]/span[contains(@class, "error")]
                | //div[not(contains(@class, "hidden"))]/p[contains(@class, "error")]
            ')
        ) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Your username or password is incorrect. Please try again.')
                || strstr($message, 'Your username must be 6-30 characters')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // For security reasons, we have locked online access to your account.
            if ($message == 'For security reasons, we have locked online access to your account.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            // Retrieve your username and reset your password.
            if ($message == "For security reasons, please validate your identity.") {
                throw new CheckException("GAP Brands (Banana Republic, Old Navy, Gap etc.) website is asking you to retrieve your username and reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "re sorry, but for security reasons online access to your account is unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Retrieve your username and reset your password.
        if ($this->http->FindSingleNode('//h1[contains(text(), "For security reasons, please validate your identity.")]')) {
            throw new CheckException("GAP Brands (Banana Republic, Old Navy, Gap etc.) website is asking you to retrieve your username and reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function parseQuestionSelenium($sendAnswers)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode('//h3[contains(text(), "Answer your security questions")]')) {
            return false;
        }

        $contBtn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "rsaChallengeFormSubmitButton"]'), 0);
        $this->saveResponse();

        if (!$contBtn) {
            return false;
        }

        $needAnswer = false;

        for ($n = 0; $n < 2; $n++) {
            $xpatQuestionText = "//input[@name = 'rsaForm.rsaQ" . ($n + 1) . "Text']";
            $question = $this->waitForElement(WebDriverBy::xpath($xpatQuestionText), 0, false)->getAttribute('value');
            $this->State["Question" . ($n + 1)] = $question;
            $this->State["InputQuestion" . ($n + 1)] = '//input[@name = "rsaForm.rsaAns' . ($n + 1) . '"]';

            if (!isset($this->Answers[$question])) {
                $this->AskQuestion($question, null, "Question");
                $needAnswer = true;
            }// if (!isset($this->Answers[$question]))
            else {
                $this->waitForElement(WebDriverBy::xpath($this->State["InputQuestion" . ($n + 1)]), 0)->sendKeys($this->Answers[$question]);
            }
        }// for ($n = 0; $n < 2; $n++)

        if (!$needAnswer && $sendAnswers) {
            $this->logger->debug("return to ProcessStep");
            $this->ProcessStep('question');
            $this->Parse();

            return true;
        }

        $this->logger->debug("return true");

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode("//h3[contains(text(), 'Select a delivery method')]")) {
            return false;
        }

        $emailMe = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Email me at")]/preceding-sibling::label'), 0);
        $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otpDecision.btnContinue"]'), 0);
        $this->saveResponse();
        $email = $this->http->FindSingleNode('//p[contains(text(), "Email me at")]', null, true, "/me at\s*(.+)/");

        if (!$email) {
            $emailMe = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Email")]/parent::label'), 0);
        }// if (!$email)

        if (!$contBtn || !$emailMe) {
            return false;
        }

        $emailMe->click();

        if ($emailOption = $this->waitForElement(WebDriverBy::xpath('//select[@id = "emailOption"]'), 0)) {
            $emailOption->click();
//            $this->driver->executeScript("
//                try {
//                    document.querySelector('#emailOption').selectedIndex = document.querySelector('#emailOption option[value *= \"-\"]').index;
//                } catch (e) {}
//            ");
            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath('(//select[@id = "emailOption"]//option[contains(@value, "-")])[1]'), 0)->click();
            $this->saveResponse();

            $email = $this->http->FindSingleNode('(//select[@id = "emailOption"]//option[contains(@value, "-")])[1]');
        }

        if (!$email) {
            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification("2fa - refs #21485 // RR");
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->saveResponse();
            $this->Cancel();
        }

        $contBtn->click();

        $question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'for your temporary SecurPass™ code')]"), 5);
        $this->saveResponse();

        if (!$question) {
            return false;
        }

        $this->holdSession();
        $this->Question = "Please enter SecurPass™ code which was sent to the following email address: $email. Please note: This SecurPass™ code can only be used once and it expires within 10 minutes.";
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "SecurPass";

        return true;
    }

    public function ProcessStep($step)
    {
        $questions = [];

        if ($step == 'SecurPass') {
            $answerFiled = $this->waitForElement(WebDriverBy::xpath('//input[@id = "otpPasscode"]'), 0);
            $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otpEntryForm.btnContinue"]'), 0);
            $this->saveResponse();
            $answer = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            if (!$contBtn || !$answerFiled) {
                return false;
            }

            $answerFiled->clear();
            $answerFiled->sendKeys($answer);
            $contBtn->click();

            sleep(5);
            $error = $this->waitForElement(WebDriverBy::xpath("
                //div[@id = 'alertBody']/p
                | //div[not(contains(@class, 'hidden'))]/span[contains(@class, 'error')]
                | //div[not(contains(@class, 'hidden'))]/p[contains(@class, 'error')]
            "), 0);
            $this->saveResponse();

            // wrong answer
            if (
                isset($error)
                && strstr($error->getText(), 'Please enter your SecurPass™ code. To have another SecurPass™ code sent, please')
            ) {
                $this->logger->error("wrong SecurPass code was entered");
                $this->holdSession();
                $this->AskQuestion($this->Question, 'Please enter the SecurPass™ code that we sent to you.', 'SecurPass');

                return false;
            }

            return true;
        }

        $btnNext = $this->waitForElement(WebDriverBy::xpath('//input[@id = "rsaChallengeFormSubmitButton"]'), 0);
        $this->saveResponse();

        if (!$btnNext) {
            return false;
        }

        for ($n = 0; $n < 2; $n++) {
            $question = ArrayVal($this->State, "Question" . ($n + 1));

            if ($question != '') {
                $questions[] = $question;

                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question, null, "Question");

                    return false;
                }// if (!isset($this->Answers[$question]))
                $this->waitForElement(WebDriverBy::xpath($this->State["InputQuestion" . ($n + 1)]), 0)->sendKeys($this->Answers[$question]);

                unset($this->State["Question" . ($n + 1)]);
                unset($this->State["InputQuestion" . ($n + 1)]);
            }// if ($question != '')
        }// for ($n = 0; $n < 2; $n++)
        // user_page:homepageV2 ?
        $this->logger->debug("questions: " . var_export($questions, true));

        if (count($questions) != 2) {
            return false;
        }

        $btnNext->click();

        sleep(5);
        $this->saveResponse();

        $error = $this->waitForElement(WebDriverBy::xpath("
            //div[@id = 'alertBody']/p
            | //div[not(contains(@class, 'hidden'))]/span[contains(@class, 'error')]
            | //div[not(contains(@class, 'hidden'))]/p[contains(@class, 'error')]
        "), 0);
        $this->saveResponse();

        // For security reasons, we have locked online access to your account.
        if ($message = $this->http->FindPreg("/<h1>(For security reasons, we have locked online access to your account\.)<\/h1>/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // wrong answer
        if (
            isset($error)
            && (
                strstr($error->getText(), 'The answer(s) you entered did not match our records')
                || strstr($error->getText(), 'The answer you entered did not match our records')
                || strstr($error->getText(), 'Your answer must contain only letters, numbers, periods and spaces. No other characters are allowed.')
            )
        ) {
            foreach ($questions as $question) {
                unset($this->Answers[$question]);
            }
            $this->parseQuestionSelenium(true); //false

            return false;
        }

        /*
        $this->sendNotification("2fa // RR");

        $answerInputs = $this->driver->findElements(WebDriverBy::xpath("//div[@id = 'otp']//input[@type='tel']"));
        $verifyBtn = $this->waitForElement(WebDriverBy::xpath('//button[@data-type="otp" and @data-reason="verify code"]'), 0);
        $saveComputer =
            $this->waitForElement(WebDriverBy::xpath('//input[@data-reason="save computer:yes"]'), 0)
            ?? $this->waitForElement(WebDriverBy::xpath('//input[@data-reason="save computer:yes"]/following-sibling::label[1]'), 0)
        ;
        $this->saveResponse();

        if (!$answerInputs || !$verifyBtn || !$saveComputer) {
            return false;
        }

        $answer = $this->Answers[$this->Question];

        for ($i = 0; $i < strlen($answer); $i++) {
            $answerInputs[$i]->clear();
            $answerInputs[$i]->sendKeys($answer[$i]);
        }

        $this->saveResponse();
        $saveComputer->click();
        $this->saveResponse();

        unset($this->Answers[$this->Question]);
        $this->logger->debug("click 'Submit'...");
        $verifyBtn->click();

        $this->logger->debug("find errors...");
        sleep(5);
        $error = $this->waitForElement(WebDriverBy::xpath('//span[@data-test="error-message"]'), 0);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();

            if (strstr($message, 'The one time passcode you provided is incorrect.')) {
                $this->logger->error($message);
                $this->holdSession();
                $this->AskQuestion($this->Question, $message, "Question");

                return false;
            }

            return false;
        }
        */

        return true;
    }

    public function Parse()
    {
        /*
        $allCardNumbers = [];
        $allCardsLinks = [];
        $this->skipReminders();
        */
        $subDomain = $this->getSubDomain();

        $this->logger->debug("[CurrentURL]: {$this->http->currentUrl()}");
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(text(), 'Welcome,') or contains(text(), 'Aloha,')] | //p[@class = 'b-greeting']", null, true, "/,\s*([^\!\.<]+)/ims"));

        $this->parseTab($subDomain);
        $otherTabs = $this->http->FindNodes("//div[@id = 'tabs']//div[@class = 'tabunselected']//a/@href");

        foreach ($otherTabs as $link) {
            if ($this->http->GetURL($link)) {
                $this->parseTab($subDomain);
            }
        }
        $links = $this->http->FindNodes("
            //a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=') and h3[contains(text(), 'ewards')]]/@href
            | //a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=') and h3[contains(text(), 'Extra points')]]/@href
            | //a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=') and h3[contains(text(), 'Carnival')]]/@href
            | //a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=')]/@href
        ");

        if (count($links) == 0) {
            $links = $this->http->FindNodes("//a[contains(@href, 'SwitchAccount.action?accountId=')]/@href");

            foreach ($links as &$link) {
                $this->http->NormalizeURL($link);
            }
        }

        $this->logger->debug(var_export($links, true), ["pre" => true]);
        $links = array_unique($links);
        $this->logger->debug(var_export($links, true), ["pre" => true]);
        // filter links
        foreach ($links as &$link) {
            $pos = strpos($link, '&');

            if ($pos) {
                $link = substr($link, 0, strpos($link, '&'));
            }
        }// foreach ($links as &$link)
        $this->logger->debug(var_export($links, true), ["pre" => true]);
        $links = array_unique($links);
        $this->logger->debug(var_export($links, true), ["pre" => true]);

        $closed = false;
        unset($link);

        foreach ($links as $link) {
            // refs #6178
            // This account is closed
            if ($link == "https://{$subDomain}.barclaysus.com/servicing/legacy" && $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($message = $this->http->FindSingleNode("//h1[contains(text(), 'This account is closed')]"))) {
                $closed = true;
            }
            // skip less link
            if (strstr($link, '&redirectAction=/messageCenter')) {
                $this->logger->notice("skip less link -> " . $link);

                continue;
            }

            if (strstr($link, '&rnd=') && !$this->newDesign) {
                $this->logger->notice("skip less link -> " . $link);

                continue;
            }// if (strstr($link, '&rnd=') && !$this->newDesign)
            $this->parseCard($link, $subDomain);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//span[@id = 'lastlogin']") !== null
                || $this->http->FindPreg('/Email Address/ims') !== null) {
                $this->SetBalanceNA();
            }
            //# This account is closed   // refs #6178
            elseif ($closed && isset($message)) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We apologize for the inconvenience, but we could not complete your request. Please try again.
            elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for the inconvenience, but we could not complete your request. Please try again.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // refs #11309, 14505
        $this->logger->info('FICO® Score', ['Header' => 3]);
        $this->http->GetURL("https://{$subDomain}.barclaysus.com/servicing/ficoScore?start");
        // FICO® SCORE
        $fcioScore = $this->http->FindPreg("/var\s*num\s*=\s*([^\;]+)/ims");
        // FICO Score updated on
        $fcioUpdatedOn = $this->http->FindSingleNode("//div[@id = 'lastUpdated']", null, true, "/last \s*updated\s*on\s*([^<\.]+)/ims");

        if ($fcioScore && $fcioUpdatedOn) {
            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                    if (in_array($key, ['Code', 'DisplayName'])) {
                        continue;
                    } elseif ($key == 'Balance') {
                        $this->SetBalance($value);
                    } elseif ($key == 'ExpirationDate') {
                        $this->SetExpirationDate($value);
                    } else {
                        $this->SetProperty($key, $value);
                    }
                }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                unset($this->Properties['SubAccounts']);
            }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                "Code"               => "bananarepublic{$this->AccountFields['Login2']}FICO",
                "DisplayName"        => "FICO® Score (TransUnion)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)

        // refs #14720
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->State['Success'] = true;
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function parseCard($link, $subDomain)
    {
        $this->logger->debug("loading card $link");
        $this->http->GetURL($link);
        $this->parseTab($subDomain);
    }

    public function parseTab($subDomain)
    {
        $tabName = $this->http->FindSingleNode("//span[@class = 'b-card-name']");
        $this->newDesign = true;
        $this->logger->debug("tabName: " . $tabName);

        if (isset($tabName)) {
            $this->logger->info($tabName, ['Header' => 3]);
        }

        $rewardLink = $this->http->FindPreg("/href=\"([^\"]+)\" id=\"rewards\"/");
        $rewardText = Html::cleanXMLValue($this->http->FindPreg("/<div class=\"tabAction[^\"]+\">\s*([^<]+)<\/div>/ims"));
        $this->logger->debug("rewardText: " . $rewardText);
        // Card ending in ...
        $ending = $this->http->FindSingleNode("//div[@class = 'cardNum']");
        $this->logger->debug("ending: " . $ending);

        $notActive = $this->http->FindPreg("/Activate your account/");

        if (!$notActive && !strstr($tabName, 'AAdvantage')) {
            $http2 = clone $this;
            $http2->http->GetURL("https://{$subDomain}.barclaysus.com/servicing/jserv/rewardsTile/?_=" . time() . date("B"));

            // We're sorry, but we're currently upgrading our Website.
            if ($this->http->Response['code'] == 503 && ($message = $this->http->FindSingleNode('//b[contains(text(), "We\'re sorry, but we\'re currently upgrading our Website.")]'))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif ($this->http->Response['code'] == 500 && $this->http->FindPreg("/Exception occurred while processing the request'/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $response = $http2->http->JsonLog(null, 3, true);
            $balance = ArrayVal($response, 'rewardsBalance');
            $this->logger->debug("Balance 1 (from main page) -> " . $balance);
        }// if (!$this->http->FindPreg("/Activate your account/"))

        if (
            (
                (
                    !empty($rewardText)
                    && $rewardText != 'VisitUpromise.com View rewards'
                    && $rewardLink != 'Rewards.action?boostRewards'
                )
                || (
                    $this->newDesign
                    && !strstr($tabName, 'AAdvantage')
                )
            )
            && !$notActive
        ) {
            $this->http->GetURL("https://{$subDomain}.barclaysus.com/servicing/Rewards.action?rnd=" . time() . date("B"));
            $this->http->SetBody($this->http->Response['body'], true);
            $balance2 = $this->GetBalance();
            $this->logger->debug("Balance 1 (from main page) -> " . $balance);
            $this->logger->debug("Balance 2 (from details) -> " . $balance2);

            if (!isset($balance)) {
                $balance = $balance2;
            }

            $this->http->GetURL("https://{$subDomain}.com/myrewards");
        }
        // improve DisplayName card
        if (isset($ending)) {
            $tabName = $tabName . " ({$ending})";
        }
        $this->logger->debug("tabName: " . $tabName);

        $closed = ($this->http->FindPreg("/This account is closed./ims")) ? true : false;

        if (isset($tabName) && isset($balance) && $balance != '' && !$closed) {
            if (isset($balance) && strpos($balance, '$') !== false) {
                $currency = '$';
            } else {
                $currency = null;
            }
            // adding SubAccount
            $lastStatement = $this->http->FindSingleNode("
                //dt[contains(text(), 'Miles earned last statement')]/following::dd[1]
                | //p[contains(text(), 'Earned since last statement')]/preceding-sibling::p[1]
                | //p[contains(text(), 'Earned last statement')]/preceding-sibling::p[1]
            ");

            $this->AddSubAccount([
                "Code"          => 'bananarepublic' . md5($tabName),
                "DisplayName"   => $tabName,
                "Balance"       => $balance,
                // for US Airways cards
                "LastStatement" => $lastStatement,
                'Currency'      => $currency,
            ]);
            // Detected cards
            $this->AddDetectedCard([
                "Code"            => 'bananarepublic' . md5($tabName),
                "DisplayName"     => $tabName,
                "CardDescription" => C_CARD_DESC_ACTIVE,
            ]);

            if ($this->ErrorCode != ACCOUNT_CHECKED) {
                $this->SetBalanceNA();
            }
        }// if (isset($tabName) && isset($balance) && $balance != '' && !$closed && !strstr($rewardText, 'AAdvantage'))
        // cards without balance
        elseif (isset($tabName) && (!isset($balance) || $closed)) {
            $this->logger->notice("Balance not found");
            // This account is closed
            if ($closed) {
                $cardDescription = C_CARD_DESC_CLOSED;
            }
            // if needed to visit Upromise.com
            elseif ($rewardText == 'VisitUpromise.com View rewards') {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Sallie Mae', 117], C_CARD_DESC_UNIVERSAL);
            } elseif (strstr($tabName, 'AAdvantage')) {
                $cardDescription = C_CARD_DESC_AA;
            } else {
                $cardDescription = C_CARD_DESC_DO_NOT_EARN;
            }
            // Detected cards
            $this->AddDetectedCard([
                "Code"            => 'bananarepublic' . md5($tabName),
                "DisplayName"     => $tabName,
                "CardDescription" => $cardDescription,
            ], true);
            $this->SetBalanceNA();
        } else {
            $this->logger->notice("Balance not found");
        }

        //# Activate your account
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->http->FindSingleNode("//span[contains(text(), 'To start using your card, please activate your account right away.')]"))) {
            $this->SetWarning("Your account is not activated");
        }
    }

    public function GetBalance()
    {
        $balance = $this->http->FindSingleNode("//td[contains(text(), 'My Rewards')]/following::td[1]");

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[contains(text(), 'My Rewards')]]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[contains(text(), 'Reward Points')]]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'Reward Points')]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'My FunPoints')]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[contains(text(), 'My Princess Rewards')]]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'My Travelocity Points')]/following::td[1]");
        }

        // US Airways cards
        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Miles earned so far this billing cycle')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Total Points available')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Total Travelocity Points available')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Current Coupon Dollars')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Current') and contains(text(), 'iTunes points')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Rewards Points') and contains(text(), 'ready to spend')]", null, false, "/You\s+have\s+([\d\,\.\$\-]+)\s+Rewards\s+Points\s+ready\s+to\s+spend/ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Points') and contains(text(), 'earned so far this billing cycle')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//div[contains(@class, 'rewardsTxt floatLeft')]/span");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'My Miles &amp More Miles')]/following-sibling::td[1]");
        }
        // new design
        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Current miles')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Current points')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Earned so far this billing cycle')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Points Earned')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Coupon Dollars Earned')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/You have\s*([\d\.\,]+)\s*points\s*ready\s*to\s*spend/ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/You have\s*([\d\.\,]+)\s*miles\s*ready\s*to\s*spend/ims");
        }

        return $balance;
    }
}
