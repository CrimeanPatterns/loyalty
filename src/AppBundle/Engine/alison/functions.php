<?php

class TAccountCheckerAlison extends TAccountChecker
{
    public const REWARDS_PAGE_URL = 'https://alison.com/dashboard';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->removeCookies();
        $this->http->GetURL("https://alison.com/login");

        if (!$this->http->ParseForm('login-form')) {
            return false;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("remember", "1");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /*
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'There is no such email address in our database.')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        if ($this->http->Response['code'] == 404) // if email wrong, we get 404
            throw new CheckException('Wrong email address', ACCOUNT_INVALID_PASSWORD); /*checked*/

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//span[@class = "error-message"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Invalid log in, please try again')
                || strstr($message, 'These credentials do not match our records')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // check, if email confirmed or not
        if ($this->http->FindSingleNode("//h2[contains(@class, 'main')]")) {
            throw new CheckException('You need to confirm your account. An email has been sent to the address which
			you supplied.', ACCOUNT_PROVIDER_ERROR); /*checked*/
        }

        // hard code
        if (
            (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false && strstr($this->AccountFields['Login'], '@gmail.com'))
            || $this->http->FindSingleNode('//p[contains(text(), "Please login with your social network account instead.")]')
        ) {
            throw new CheckException('Unfortunately, we are currently do not support Login with Social Media', ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(@class, "sidebar__user-data-inner")]/h4')));
        // Alison ID
        $this->SetProperty("ID", $this->http->FindSingleNode('//h6[contains(text(), "Alison ID:")]', null, true, "/\:\s*([^<]+)/"));
        /*
        // Balance - Score
        $this->SetBalance($this->http->FindSingleNode("//div[@class='course-info--points']", null, false, '/([\d,.]+)\s*Points/'));
        */

        // Courses in Progress
        $headers = [
            "x-header-host" => "wdtkYMfcdKhElGQZ/wDejrY/PaY=",
            "x-csrf-token"  => $this->http->FindSingleNode("//meta[@name = \"csrf-token\"]/@content"),
            "Authorization" => "Cookie " . $this->http->FindPreg("/var sessionId = \"([^\"]+)/"),
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.alison.com/v0.1/user/learning-stats", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Courses completed
        $this->SetProperty("Courses", $response->result->completed);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.alison.com/v0.1/user/courses-in-progress/5/1", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $this->SetProperty("CourseCoursesInProgress", $response->total);

        if (!isset($this->Properties['Courses'])) {
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://api.alison.com/v0.1/user/courses-completed", $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();
            // Courses completed
            if ($this->http->Response['body'] == '{"status":200,"total":0}') {
                $this->SetProperty("Courses", 0);
            } else {
                $this->SetProperty("Courses", count($response->result));
            }
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['ID'])
            && isset($this->Properties['Courses'])
        ) {
            $this->SetBalanceNA();
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://alison.com/login/';
        $arg['SuccessURL'] = self::REWARDS_PAGE_URL;

        return $arg;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }

        return false;
    }
}
