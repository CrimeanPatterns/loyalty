<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFreebirds extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private array $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
        'Referer'      => 'https://www.freebirds.com/user/sign-in',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useFirefox();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        return $this->loginSuccessful();
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->State['token'])) {
            return false;
        }
        $this->http->GetURL("https://www.freebirds.com/api/user/{$this->State['token']}", $this->headers);
        $user = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));

        return isset($user->first_name, $user->last_name, $user->user_id);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.freebirds.com/user/sign-in");

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Email Address"]'), 5);

        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//img[@alt="Freebirds World Burrito"]'), 0));
        }, 10);

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In")]'), 0);
        $this->saveResponse();

        if ($acceptAllBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "ACCEPT ALL")]'), 0)) {
            $acceptAllBtn->click();
            sleep(1);
            $this->saveResponse();
        }

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Hi, ")] | //div[contains(@class, "alert-danger")]'), 10);
        $this->saveResponse();

        $responseData = $this->driver->executeScript("return localStorage.getItem('user');");
//        $this->logger->info("[Form responseData]: " . $responseData);
        $response = $this->http->JsonLog($responseData, 3, false, "token");

        if (isset($response->tokens->tokens->user->token)) {
            $this->State['token'] = $response->tokens->tokens->user->token;

            return $this->loginSuccessful();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Incorrect information submitted. Please retry.') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Parse()
    {
        $user = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'), 0);
        // Name
        $this->SetProperty("Name", beautifulName($user->first_name." ".$user->last_name));
        // Member ID
        $this->SetProperty("Number", $user->user_id);

        $this->http->GetURL("https://www.freebirds.com/api/loyalty/balance?accessToken={$this->State['token']}", $this->headers);
        $balance = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));
        // Points
        $this->SetBalance($balance->redeemable_points ?? null);
        // Lifetime Points
        $this->SetProperty("LifetimePoints", $balance->lifetime_points ?? null);

        // Points to the next tier
        // https://www.freebirds.com/52.d14118c56f5e8420.js
        if ($balance->lifetime_points < 150) {
            $lifetimePoints = 150 - $balance->lifetime_points;
        } elseif ($balance->lifetime_points < 500) {
            $lifetimePoints = 500 - $balance->lifetime_points;
        } elseif ($balance->lifetime_points < 800) {
            $lifetimePoints = 800 - $balance->lifetime_points;
        } else {
            $lifetimePoints = 0;
        }
        $this->SetProperty("PointsToNextTier", $lifetimePoints);

        // t.lifetime_points < 150 ? "headliner" : t.lifetime_points < 500 ? "rockstar" : "legend"
        // Current Level
        $this->SetProperty("Status", $balance->lifetime_points < 150 ? "Headliner" : ($balance->lifetime_points < 500 ? "Rockstar" : "Legend"));
    }

}
