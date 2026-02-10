<?php

class TAccountCheckerEzrentacar extends TAccountChecker
{
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }
        $this->http->setDefaultHeader('X-Auth-Token', $this->State['token']);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://awards.e-zrentacar.com/ez-services/member/header', []);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // User Name - Please enter an email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("User Name - Please enter an email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->FilterHTML = false;

        $this->http->GetURL("https://www.e-zrentacar.com/login");

        if ($this->http->Response['code'] != 200) {//todo: need to check form
            return $this->checkErrors();
        }

        $advlogin = $this->http->FindPreg('/"advlogintNonce":"(\w+?)"/');
        $data = [
            'action'   => 'advLogin',
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
            'advlogin' => $advlogin,
        ];

        if (!$this->http->PostURL('https://www.e-zrentacar.com/wp-admin/admin-ajax.php', $data)) {
            return $this->checkErrors();
        }

        $json = $this->http->JsonLog(null, 1, true);
        // Login failed. Please try again
        if (
            isset($json['error']['errorMessage'])
            && in_array($json['error']['errorMessage'], [
                'Client authentication failed',
                'Invalid Credentials.',
                'Undefined index: d',
            ])
        ) {
            throw new CheckException("Login failed. Please try again", ACCOUNT_INVALID_PASSWORD);
        }

        $data = [
            'access_token' => ArrayVal($json, 'access_token'),
            'membernumber' => ArrayVal($json, 'memberNumber'),
            'id'           => ArrayVal($json, 'userGUID'),
            'hash'         => ArrayVal($json, 'SSO_HASH'),
        ];
        $this->http->PostURL('https://awards.e-zrentacar.com/sso.php', $data);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->info(__METHOD__);

        // Our Apologies.  An error has occurred.
        if ($message = $this->http->FindPreg("/(Our Apologies\.\s*An error has occurred\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The website encountered an unexpected error. Please try again later.
        if ($message = $this->http->FindPreg("/(The website encountered an unexpected error\.\s*Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Error establishing a database connection")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $precondition = $this->http->getCookieByName('persuadeToken');
        $this->http->setDefaultHeader('X-Auth-Token', $precondition);
        $this->http->PostURL('https://awards.e-zrentacar.com/ez-services/member/header', []);

        if ($this->loginSuccessful()) {
            $this->State['token'] = $precondition;

            return true;
        }

        // Invalid Credentials
        if ($message = $this->http->FindPreg('/(Invalid Credentials)/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->PostURL('https://awards.e-zrentacar.com/ez-services/member/header', []);

        // Balance - Total Points
        $this->SetBalance($this->http->FindPreg('/"points":"([\d.]+)"/'));
        // Member ID
        $this->SetProperty("MemberID", $this->http->FindPreg('/"membernumber":"([\d.]+)"/'));
        // Status
        // if ($this->http->FindPreg('/"status":"A"/'))
        //     $this->SetProperty("Status", 'Premier');
        // $status = $this->http->FindPreg('/Money Premier Account/ims');
        // if (isset($status))
        // 	$this->SetProperty("Status", 'Premier');
        // else
        // 	$this->SetProperty("Status", 'Member');
        // We're sorry, the request timed out.

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['MemberID']) || $this->http->FindSingleNode("//span[contains(text(), 'Member ID:')]") == 'Member ID:') {
                $this->SetBalanceNA();
            }

            if ($this->http->currentUrl() == 'http://www.e-zrentacar.com/timeout') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Name
        $firstName = $this->http->FindPreg('/"firstname":"(\w+)"/');
        $lastName = $this->http->FindPreg('/"lastname":"(\w+)"/');
        $name = trim(sprintf('%s %s', $firstName, $lastName));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        $this->http->GetURL('https://awards.e-zrentacar.com/ez-services/member/promotion/list');
        $response = $this->http->JsonLog();

        foreach ($response->promotions as $promotion) {
            if (!isset($promotion->promotioncodes) || $promotion->display !== 'Y') {
                continue;
            }

            if (count($promotion->promotioncodes) > 1) {
                $this->sendNotification('refs #19224: Promotion codes > 1 //KS');
            }
            $expirationDate = $promotion->promotioncodes[0]->expiredate ?? null;
            $displayName = $promotion->description ?? null;
            $redemptionCode = $promotion->promotioncodes[0]->code ?? null;

            if (empty($expirationDate) || empty($displayName) || empty($redemptionCode)) {
                continue;
            }
            $this->AddSubAccount([
                'Code'           => "ezrentacaCoupon{$redemptionCode}",
                'DisplayName'    => $displayName,
                'ExpirationDate' => strtotime($expirationDate),
                'RedemptionCode' => $redemptionCode,
                "Balance"        => null,
            ]);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //		$arg["URL"] = 'https://www.e-zrentacar.com/Rewards/money_landing.asp?action=login';
        //$arg["CookieURL"] = "https://www.e-zrentacar.com/Rewards/money_landing.asp?type=main&loyalty_id=VFFC55B";
        //		$arg["NoCookieURL"] = true;
        return $arg;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg('/"membernumber":"(\d+)"/')) {
            return true;
        }

        return false;
    }
}
