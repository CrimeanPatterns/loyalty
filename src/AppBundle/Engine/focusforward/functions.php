<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerFocusforward extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.fffocusgroup.com/login-now/");

        if ($loginFormUrl = $this->http->FindSingleNode('//iframe[contains(@src, "/login")]/@src')) {
            $this->http->GetURL($loginFormUrl);
        }

        $account_uuid = $this->http->FindPreg("/panelist\/([^\/]+)\/login/", null, $loginFormUrl);

        if (!$account_uuid) {
            return $this->checkErrors();
        }

        $data = [
            "email"    => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "Referer"      => "https://panelfox.io/",
            "Origin"       => "https://panelfox.io",
        ];
        $this->http->PostURL("https://api.panelfox.io/api/people-public-login?account_uuid={$account_uuid}", json_encode($data), $headers);
        $this->http->RetryCount = 1;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $message = $response->error ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                in_array($message, [
                    'Panelist profile does not exist. Please sign up.',
                    'Wrong email or password.',
                ])
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($rewardsPage = $this->http->FindSingleNode("//a[contains(text(), 'Claim a Reward')]/@href")) {
            $this->http->NormalizeURL($rewardsPage);
            $this->http->GetURL($rewardsPage);
        }// if ($rewardsPage = $this->http->FindSingleNode("//a[contains(text(), 'Claim a Reward')]/@href"))

        // Balance - You have a total of 0 points in your account
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'You have a total of')]", null, true, '/of\s*([\d\.\,]+)\s*point/ims'));
        // Total Points Available
        $this->SetProperty("TotalPointsAvailable", $this->http->FindSingleNode("//span[@class = 'TotalPoints']"));
        // Points Currently Being Redeemed
        $this->SetProperty("PointsCurrentlyBeingRedeemed", $this->http->FindSingleNode("//span[@class = 'BasketPoints']"));
        // Remaining Balance
        $this->SetProperty("RemainingBalance", $this->http->FindSingleNode("//span[@class = 'AvailablePoints']"));

        $this->http->GetURL("http://panel.focusfwdonline.com/Members2/MyAccount.aspx");
        //# Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[contains(@name, 'FirstName')]/@value")
            . ' ' . $this->http->FindSingleNode("//input[contains(@name, 'LastName')]/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }
}
