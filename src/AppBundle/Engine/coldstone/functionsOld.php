<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerColdstone extends TAccountChecker
{
    use ProxyList;
    private $headers = [
        'accept'              => 'application/json, text/plain, */*',
        'content-type'        => 'application/json;charset=UTF-8',
        'accept-language'     => 'en-US,en;q=0.5',
        'accept-encoding'     => 'gzip, deflate, br',
        'Spendgo-Web-Context' => 'coldstone',
    ];

    private $MContext = 'coldstone';
    private $SToken;
    private $SStarted;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://my.spendgo.com/consumer/gen/coldstone/v1/consumerdetails', '{}', $this->headers);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://my.spendgo.com/index.html#/signIn/coldstone');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://my.spendgo.com/coldstone');

        $data = ['email' => $this->AccountFields['Login']];
        $this->http->PostURL('https://my.spendgo.com/consumer/gen/spendgo/v1/lookup', json_encode($data), $this->headers + ['Referer' => 'https://my.spendgo.com/index.html']);
        $status = $this->http->JsonLog(null, 0)->status ?? null;

        if ($status != 'Activated') {
            if ($status === 'NotFound') {
                throw new CheckException('Account not found', ACCOUNT_INVALID_PASSWORD);
            }

            if ($status === 'InvalidEmail') {
                throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
            }

            return false;        $this->http->RetryCount = 0;
            $this->http->PostURL('https://my.spendgo.com/consumer/gen/coldstone/v1/consumerdetails', '{}', $this->headers);
            $this->http->RetryCount = 2;
    
            if ($this->http->Response['code'] != 200) {
                return false;
            }
    
            return $this->loginSuccessful();
    
        }
        /*
        $data = [
            'value'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ];
        */

        $data = [
            'phoneOrEmail' => $this->AccountFields['Login'],
            'password'     => $this->AccountFields['Pass'],
            'passType'     => 'password',
        ];

        $this->updateTokens();

        $headers = [
            'Referer'            => 'https://accounts.spendgo.com/Authenticate/coldstone',
            'Origin'             => 'https://accounts.spendgo.com',
            'X-Spendgo-MContext' => 'coldstone',
            'X-Spendgo-SToken'   => $this->SToken,
            'X-Spendgo-SStarted' => $this->SStarted,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://accounts.spendgo.com/v3/member/signIn?lang=en-US', json_encode($data), $this->headers + $headers);

        return true;
    }

    public function Login()
    {
        if (str_contains($this->http->Response['body'], 'Member ID / password incorrect')) {
            throw new CheckException('This combination does not match. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->updateTokens();
        
        $headers = [
            'X-Spendgo-SToken'   => $this->SToken,
            'X-Spendgo-SStarted' => $this->SStarted,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://my.spendgo.com/consumer/gen/coldstone/v1/consumerdetails', '{}', $this->headers + $headers);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Name
        $first = $response->first_name ?? null;
        $last = $response->last_name ?? null;
        $this->SetProperty('Name', beautifulName($first . ' ' . $last));
        $data = ['spendgo_id' => $response->spendgo_id ?? null];
        $this->http->PostURL('https://my.spendgo.com/consumer/gen/coldstone/v2/rewardsAndOffers', json_encode($data), $this->headers);
        $response = $this->http->JsonLog();
        // Balance - Points
        $balance = $response->point_total ?? null;
        $this->SetBalance($balance);
        // Points to next reward (calculated from threshold and balance)
        $goal = $response->spend_threshold ?? null;

        if ($balance && $goal) {
            $pointsToNextReward = $goal - $balance;
            $this->SetProperty('PointsToNextReward', $pointsToNextReward);
        }
        // Rewards
        $rewards = $response->rewards_list ?? [];

        foreach ($rewards as $reward) {
            if (empty($reward->reward_title)
                || empty($reward->type)
                || $reward->type === 'progress'
            ) {
                continue;
            }
            $this->AddSubAccount([
                'Code'        => preg_filter('/\W/', '', $reward->reward_title),
                'DisplayName' => $reward->reward_title,
                'Balance'     => $reward->quantity ?? null,
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $email = $response->profile->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            $email && strtolower($email) === strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function updateTokens() {
        $SToken = $this->http->getCookieByName('_spendgo_stoken');
        $SStarted = $this->http->getCookieByName('_spendgo_sstart');

        if(isset($SToken, $SStarted)) {
            $this->SToken = $SToken;
            $this->SStarted = $SStarted;
            return;   
        }

        $SToken = $this->http->getCookieByName('_spendgo_stoken');
        $SStarted = $this->http->getCookieByName('_spendgo_sstart');

        if(isset(
            $this->http->Response['headers']['X-Spendgo-SToken'],
            $this->http->Response['headers']['X-Spendgo-SStarted'],
            // $this->http->Response['headers']['X-Spendgo-SUpdated'],
        )) {
            $this->SToken = $this->http->Response['headers']['X-Spendgo-SToken'];
            $this->SStarted = $this->http->Response['headers']['X-Spendgo-SStarted'];
            return;   
        }
    }
}
