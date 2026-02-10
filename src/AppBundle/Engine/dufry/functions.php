<?php

class TAccountCheckerDufry extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://sso.dufry.com/welcome';

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
        $this->logger->notice(__METHOD__);

        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('There is not a user that matches this email/password.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://sso.dufry.com/login');

        if (!$this->http->ParseForm(null, '//form[@action="/api/authentication?"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->http->FindSingleNode('//form[@action="/api/authentication?"]/preceding-sibling::div[contains(@class,"error-text")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message === "Failed to sign in! Please check your credentials or activate your account and try again.") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - 0 points
        $this->SetBalance($this->http->FindSingleNode("//p[@class='feedback-text']/span[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), '- d') and contains(normalize-space(),'points')]", null, true, "/-\s([\d,.]+)\spoints/"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//p[@class='feedback-text']/span[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), '(d') and contains(normalize-space(),'points to ')]/preceding::p[1]")));
        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//li[contains(@id, "number")]'));
        // (2000 points to Gold)
        $this->SetProperty('PointsNextLevel', $this->http->FindSingleNode("//p[@class='feedback-text']/span[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), '(d') and contains(normalize-space(),'points to ')]", null, true, "/\((\d+)\spoints to\s.+?\)/"));
        // Silver - 0 points
        $this->SetProperty('Status', $this->http->FindSingleNode("//p[@class='feedback-text']/span[contains(translate(normalize-space(), '0123456789', 'dddddddddd'), '- d') and contains(normalize-space(),'points')]/preceding-sibling::span[1]"));
        // Your alternative code is
        $this->SetProperty('AlternativeCode', $this->http->FindSingleNode("//p[@class='feedback-text']/span[starts-with(normalize-space(),'Your alternative code is')]/following-sibling::span[1]", null, true, "/^(\d+)$/"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "logout")]/@href')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
