<?php

class TAccountCheckerFhb extends TAccountChecker
{
    private $csrf = null;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://fhbrewards.com");

        $csrf = $this->http->FindSingleNode("//input[@id = 'csrf_token_keeper']/@value");

        if (!$csrf) {
            return $this->checkErrors();
        }
        // prevent error [SESSION_TIMEOUT]
        $postData = [
            "postaction"     => "NavForm",
            "site_page_name" => "SPN_LOGIN",
            "next_page_name" => "",
            "parent_id"      => "",
            "data"           => "",
            "selected_index" => "",
            "csrf_token"     => $csrf,
        ];
        $this->http->PostURL("https://fhbrewards.com/rewards/phoenix/priorityrewards/sign-in?csrf_token={$csrf}", $postData);

        // open login form
        $this->csrf = $this->http->FindSingleNode("//input[@id = 'csrf_token_keeper']/@value");

        if (!$this->csrf) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha($this->http->FindPreg("/site_key\s*=\s*'([^\']+)/"));

        if ($captcha == false) {
            return false;
        }

        $postData = [
            "ajaxEvent"  => "initialLoginSurpreenda",
            "csrf_token" => $this->csrf,
        ];
        $this->http->PostURL("https://fhbrewards.com/rewards/AjaxDataServlet/", $postData);

        if (!$this->http->ParseForm("form_login")) {
            return $this->checkErrors();
        }

        $this->http->FormURL = "https://fhbrewards.com/rewards/AjaxDataServlet/";
        $this->http->SetInputValue('user_id', $this->AccountFields['Login']);
        $this->http->SetInputValue('user_pwd', $this->AccountFields['Pass']);
        $this->http->SetInputValue('csrf_token', $this->csrf);
        $this->http->SetInputValue('ajaxEvent', 'loginPostSurpreenda');
        $this->http->SetInputValue('ajaxData', '');
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('recaptchaToken', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // Server Error in '/' Application
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            // HTTP Error 503. The service is unavailable.
            || $this->http->FindSingleNode('//h2[contains(text(), "Service Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.
        if ($message = $this->http->FindPreg("/(The page you are looking for might have been removed, had its name changed, or is temporarily unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // Please enter your user ID and password.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please enter your user ID and password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // An unexpected error occurred during the processing of your request. Please try again or contact Customer Service if the problem persists  (1-800-868-2856).
        // Password: ❸❺❽❼❹❹❼❾❾❹❹❻ // AccountID: 4585558
        if ($message = $this->http->FindSingleNode("//p[@class = 'error' and contains(text(), 'An unexpected error occurred during the processing of your request. Please try again or contact Customer Service if the problem persists')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Update Password
        if ($this->http->FindPreg("/\[EXE_JS_BEGIN\]goToSecurityProfile\(\);\[EXE_JS_END\]/")) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg("/\[EXE_JS_BEGIN\]goToAuthentication\(\);\[EXE_JS_END\]/")) {
            $this->http->FormURL = "https://fhbrewards.com/rewards/AjaxDataServlet/";
            $this->http->SetInputValue('ajaxEvent', 'initAuthentication');
            $this->http->SetInputValue('csrf_token', $this->csrf);

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }// if ($this->http->FindPreg("/\[EXE_JS_BEGIN\]goToAuthentication\(\);\[EXE_JS_END\]/"))

        if ($this->parseQuestion()) {
            return false;
        }

        // The login information you entered does not match our records, please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The login information you entered does not match our records')]")) {
            throw new CheckException($message . " (Due to site enhancements made on 7/13, to log in to the site for the first time, you must re-register.)", ACCOUNT_INVALID_PASSWORD);
        }

        $this->finalForm();

        if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
            return true;
        }
        // Your account has been locked out for incorrect login information.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Locked Profile')]/following-sibling::p[contains(text(), 'Your account has been locked out for incorrect login information.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkErrors();
    }

    public function finalForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Upgrade-Insecure-Requests", "1");
        $this->http->FormURL = "https://fhbrewards.com/rewards/phoenix/priorityrewards/sign-in?csrf_token={$this->csrf}";
        $this->http->SetInputValue('postaction', "NavForm");
        $this->http->SetInputValue('site_page_name', "SPN_LOGIN");
        $this->http->SetInputValue('next_page_name', "SPN_HOME");
        $this->http->SetInputValue('parent_id', "");
        $this->http->SetInputValue('data', "");
        $this->http->SetInputValue('selected_index', "");
        $this->http->SetInputValue('csrf_token', $this->csrf);

        // try to catch errors
        if ($this->http->FindPreg("/EXT_PAGE:SPN_ERROR##FORM_ACTION:\/rewards\/phoenix\/priorityrewards\/sign-in/")) {
            $this->http->FormURL = "https://fhbrewards.com/rewards/phoenix/priorityrewards/sign-in";
            $this->http->SetInputValue('next_page_name', "SPN_ERROR");
        }// if ($this->http->FindPreg("/EXT_PAGE:SPN_ERROR##FORM_ACTION:\/rewards\/phoenix\/priorityrewards\/sign-in/"))

        $this->http->PostForm();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $questions = $this->http->XPath->query("//label[contains(@for, 'answer')]");
        $this->logger->debug("Total {$questions->length} questions were found");

        foreach ($questions as $q) {
            $question = $this->http->FindSingleNode("span", $q);
            $questionInput = $this->http->FindSingleNode("input/@name", $q);

            if (!isset($this->Answers[$question])) {
                $this->AskQuestion($question);

                break;
            }// if (!isset($this->Answers[$question]))
            else {
                $this->http->SetInputValue($questionInput, $this->Answers[$question]);
            }
        }// foreach ($questions as $q)

        if (!$this->http->ParseForm("AuthForm")) {
            return false;
        }

        $needAnswer = false;

        for ($n = 0; $n < 2; $n++) {
            $question = $this->http->FindSingleNode("//label[contains(@for, 'answer')]/span", null, false, null, $n);

            if (isset($question)) {
                $this->http->SetInputValue("Question" . ($n + 1), $question);
                $this->http->SetInputValue("InputQuestion" . ($n + 1), $this->http->FindSingleNode("//label[contains(@for, 'answer')]/input/@name", null, false, null, $n));

                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question);
                    $needAnswer = true;
                }// if (!isset($this->Answers[$question]))
            }// if (isset($question))
        }// for ($n = 0; $n < 2; $n++)

        if (!$needAnswer && isset($question)) {
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }// if (!$needAnswer && isset($question))

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("sending answers");
        $questions = [];

        for ($n = 0; $n < 2; $n++) {
            $question = ArrayVal($this->http->Form, "Question" . ($n + 1));

            if ($question != '') {
                $questions[] = $question;

                if (!isset($this->Answers[$question])) {
                    $this->AskQuestion($question);

                    return false;
                }// if (!isset($this->Answers[$question]))
                $this->http->SetInputValue($this->http->Form["InputQuestion" . ($n + 1)], $this->Answers[$question]);
                $this->http->FormURL = 'https://fhbrewards.com/rewards/AjaxDataServlet/';

                unset($this->http->Form["Question" . ($n + 1)]);
                unset($this->http->Form["InputQuestion" . ($n + 1)]);
            }// if ($question != '')
        }// for ($n = 0; $n < 2; $n++)
        // user_page:homepageV2 ?
        $this->logger->notice("questions: " . var_export($questions, true));

        if (count($questions) != 2) {
            return false;
        }

        $this->http->SetInputValue("ajaxData", "");
        $this->http->SetInputValue("ajaxEvent", "submitAuthentication");
        $this->http->SetInputValue("csrf_token", $this->csrf);
        $this->http->SetInputValue("remember_dev_sw", "Y");
        $this->http->PostForm();

        // The security verification information you provided does not match our records. You may have entered the information incorrectly, please try again.
        if ($error = $this->http->FindSingleNode("//p[@class = 'error']/span[@id = 'status_msg']", null, true, "/The security verification information you provided does not match our records. You may have entered the information incorrectly, please try again./ims")) {
            foreach ($questions as $question) {
                unset($this->Answers[$question]);
            }
            $this->parseQuestion();

            return false;
        }

        $this->finalForm();

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'customer_name')]")));
        // Balance - Available Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'my_points_span']"));
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
