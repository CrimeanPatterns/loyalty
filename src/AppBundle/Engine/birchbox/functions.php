<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBirchbox extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        unset($this->State['x-session-id']);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.birchbox.com/profile');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->logger->notice("Sending credentials...");
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.birchbox.com/api/auth/csrf');
        $response = $this->http->JsonLog();

        if (!isset($response->csrfToken)) {
            return false;
        }

        $headers = [
            'Accept'       => '*/*',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin'       => 'https://www.birchbox.com',
            'Referer'      => 'https://www.birchbox.com/profile',
        ];
        $data = [
            'redirect'    => "false",
            'email'       => $this->AccountFields['Login'],
            'password'    => $this->AccountFields['Pass'],
            'csrfToken'   => $response->csrfToken,
            'callbackUrl' => "https://www.birchbox.com/profile",
            'json'        => "true",
        ];
        $this->http->PostURL('https://www.birchbox.com/api/auth/callback/credentials?', $data, $headers);
        $this->http->RetryCount = 2;

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

        if (isset($response->url)) {
            if ($this->http->Response['code'] == 403 && $response->url == 'https://www.birchbox.com/api/auth/error?error=AccessDenied') {
                throw new CheckException("Unable to sign in. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            return $this->loginSuccessful();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $customer = $response->user->customer;
        // AccountId
//        $this->SetProperty('AccountId', $customer->id);
        // Name
        $this->SetProperty('Name', beautifulName(trim($customer->firstName . ' ' . $customer->lastName)));

        if (time() < strtotime("10 Jul 2022")) {
            $this->SetWarning("We've upgraded birchbox.com. We'll be bringing your loyalty status and points back online in a couple of days!");
        }

        $this->http->GetURL('https://www.birchbox.com/profile/points');
        // Current Points
        $this->SetBalance($this->http->FindPreg('/"pointsBalance":(\d+),/'));

        return;

//        // Point Balance
//        $this->SetBalance($response->point_balance);
//        // Member since
//        $this->SetProperty('MemberSince', date('j F, Y', strtotime($response->created_at)));

        $this->http->setDefaultHeader('x-requested-with', 'XMLHttpRequest');
//        $this->http->GetURL('https://www.birchbox.com/user/vip_status/');
//        $response = $this->http->JsonLog();
//        // Total Points earned year to date
//        $this->SetProperty('PointsEarnedYear', $response->vip_status->eligible_points);
//        // Points until ACES
//        $this->SetProperty('PointsUntilAces', $response->vip_status->vip_gap);
        // Total lifetime savings in the Shop
        $this->http->GetURL('https://api.birchbox.com/user/reward_history/');

        $response = $this->http->JsonLog();
        $saving = 0;

        if (false !== $response && !empty($response->reward_history)) {
            //$pointUsed = array_column($response->reward_history, 'points_used');
            $pointUsed = 0;

            foreach ($response->reward_history as $i => $obj) {
                $pointUsed += $obj->points_used;
            }
            $saving = floor($pointUsed / 10);
            $saving >= 0 ?: $saving = 0;
        }// if (false !== $response && !empty($response->reward_history))
        $this->SetProperty('TotalSaving', '$' . $saving);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.birchbox.com/api/auth/session");
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->user->customer->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            $email
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }
}
