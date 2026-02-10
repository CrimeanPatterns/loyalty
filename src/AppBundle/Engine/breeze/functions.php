<?php

class TAccountCheckerBreeze extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.flybreeze.com/account';

    private $client_id = 'iheO83aDJGfnTD14lCZVEV3MXmDWblo9';
    private $auth0Client = 'eyJuYW1lIjoiYXV0aDAtc3BhLWpzIiwidmVyc2lvbiI6IjEuMTguMCJ9';

    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        "Accept-Language" => "en-US,en;q=0.5",
        "Platform"        => "web 1.65.0",
        "Content-Type"    => "application/json",
        "Origin"          => "https://www.flybreeze.com",
        "Referer"         => "https://www.flybreeze.com/",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        // cloudflare workaround
        require_once __DIR__ . "/TAccountCheckerBreezeSelenium.php";

        return new TAccountCheckerBreezeSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

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
        $this->http->GetURL("https://auth.flybreeze.com/authorize?audience=https%3A%2F%2Fapi.flybreeze.com&client_id={$this->client_id}&redirect_uri=https%3A%2F%2Fwww.flybreeze.com&mode=login&scope=openid%20profile%20email%20offline_access&response_type=code&response_mode=query&state=aVk1UWpnLWRQRkwzckVhdG5TdjVCT3MwLXNKUmNqWFVsSTc2ZWcwVmVvLg%3D%3D&nonce=SGs5Vmc2UlpOb1RTWGVuYllqTVhZLWRhOXRELXNWa0JMfnJ6aGpPaEpJaA%3D%3D&code_challenge=l2CD1eIiUWQz0J8Q486VnslUoEcC6z2aovzIdkkDpRI&code_challenge_method=S256&auth0Client={$this->auth0Client}");

        if (!$this->http->ParseForm(null, '//form[@data-form-primary and //input[@name = "username"]]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('action', "default");

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $data = [
                "grant_type"    => "authorization_code",
                "client_id"     => $this->client_id,
                "code_verifier" => "odq~sPpHJmx0hD4Aw0dKpX.LiA_GPgSmaoOX76QHnpB",
                "code"          => $code,
                "redirect_uri"  => "https://www.flybreeze.com",
            ];
            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/json',
                'Auth0-Client' => $this->auth0Client,
            ];

            $this->http->GetURL("https://auth.flybreeze.com/authorize?audience=https%3A%2F%2Fapi.flybreeze.com&client_id={$this->client_id}&redirect_uri=https%3A%2F%2Fwww.flybreeze.com&scope=openid%20profile%20email%20offline_access&response_type=code&response_mode=web_message&state=dTNFX1J%2BLXl2NnkuTkVWWGx5Mlg0REZwRVBzU1ZwZTRadGZ5RnduTl9KXw%3D%3D&nonce=VHJ4OFI0cVhxfmo3SDFHcXhqMEJ4S2tub0Q0aEFHQWlWcnc4RTBpV08xMA%3D%3D&code_challenge=W9p9kPdi6UQ5XYcc2xb0jkAVN4NTjXMfzrtTCcuHhJ0&code_challenge_method=S256&prompt=none&auth0Client={$this->auth0Client}");

            $this->http->PostURL("https://auth.flybreeze.com/oauth/token", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (isset($response->access_token)) {
                $this->State['Authorization'] = "Bearer {$response->access_token}";

                return $this->loginSuccessful();
            }

            return false;
        }

        $message = $this->http->FindSingleNode('//span[@class = "ulp-input-error-message"]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Wrong email or password")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $user = $response->data->login->user ?? null;
        $this->SetProperty('Name', beautifulName($user->profile->firstName . " " . $user->profile->lastName));
        // Number
        $this->SetProperty('Number', $user->customerProgramNumber ?? null);
        // Valued Breeze Guest since
        $this->SetProperty('Since', date('m/d/Y', strtotime($user->createdAt)));

        // Balance - BreezePoints Available
        $this->http->PostURL("https://api.flybreeze.com/production/loyalty/redeemable", '{"operationName":"redeemable","variables":{},"query":"query redeemable {\n  redeemable: redeemable {\n    points\n    errorMessage\n    errorCode\n    __typename\n  }\n}\n"}', $this->headers);
        $response = $this->http->JsonLog();
        $this->SetBalance($response->data->redeemable->points);
        // Balance worth
        $this->SetProperty('BalanceWorth', "$" . ($response->data->redeemable->points / 100));

        // Expiration Date
        /*
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $startDate = date("c", strtotime("-1 year"));
        $endDate = date("c");
        $this->http->PostURL("https://api.flybreeze.com/production/loyalty/ledger", '{"operationName":"ledgerTransactions","variables":{"startDate":"' . $startDate . '","endDate":"' . $endDate . '"},"query":"query ledgerTransactions($startDate: ISO8601DateTime, $endDate: ISO8601DateTime) {\n  ledger(startDate: $startDate, endDate: $endDate) {\n    transactions {\n      comments\n      agentName\n      reasonCode\n      createdUTC\n      transactionID\n      transactionType\n      bankedPointsTotal\n      referenceNumber\n      creditSummary\n      __typename\n    }\n    errorMessage\n    errorCode\n    __typename\n  }\n}\n"}', $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'bankedPointsTotal');
        $transactions = $response->data->ledger->transactions ?? [];

        foreach ($transactions as $transaction) {
            if ($transaction->bankedPointsTotal != 0) {
                $this->sendNotification("Need to check exp date - refs #21239 // RR");
                // TODO
        //        $exp = $this->http->FindSingleNode("//p[contains(text(), 'Expiration Date')]", null, true, "/expiring on ([^<]+)/ims");
        //        $expiringBalance = $this->http->FindSingleNode("//p[contains(., 'CashPoints expiring on')]", null, true, "/([\d\.\,]+) CashPoints? expiring/ims");
        //        // Expiring Balance
        //        $this->SetProperty("ExpiringBalance", $expiringBalance);
        //
        //        if ($expiringBalance > 0 && strtotime($exp)) {
        //            $this->SetExpirationDate(strtotime($exp));
        //        }
            }
        }
        */
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json",
            "Origin"       => "https://www.flybreeze.com",
            "Referer"      => "https://www.flybreeze.com/",
        ];
        $this->http->PostURL("https://api.flybreeze.com/production/nav/api/nsk/v1/token", "{}", $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data->token)) {
            return false;
        }

        $this->http->PostURL("https://api.flybreeze.com/production/user/login", '{"operationName":"login","variables":{"input":{"navToken":"' . $response->data->token . '"}},"query":"mutation login($input: LoginInput!) {\n  login(input: $input) {\n    user {\n      id\n      customerProgramNumber\n      createdAt\n      profile {\n        ...profile\n        __typename\n      }\n      navUsername\n      pssId\n      personalInfoVerified\n      __typename\n    }\n    errorCode\n    errorMessage\n    __typename\n  }\n}\n\nfragment profile on Profile {\n  id\n  userId\n  active\n  addresses {\n    ...address\n    __typename\n  }\n  ageRange\n  avatarUrl\n  birthdate\n  conversionId\n  emails {\n    ...email\n    __typename\n  }\n  favoriteDestinations\n  firstName\n  gender\n  lastName\n  loyaltyNumber\n  middleName\n  oftenNeed\n  phoneNumbers {\n    ...phoneNumber\n    __typename\n  }\n  preferredFirstName\n  preferredOrigin\n  reasonForTravel\n  seat\n  socialMedia {\n    ...socialMedia\n    __typename\n  }\n  transportation\n  travelDocuments {\n    ...travelDocument\n    __typename\n  }\n  travelFor\n  travelTo\n  __typename\n}\n\nfragment address on Address {\n  address\n  line1\n  line2\n  addressType\n  city\n  id\n  state\n  zip\n  __typename\n}\n\nfragment email on Email {\n  address\n  emailType\n  id\n  __typename\n}\n\nfragment phoneNumber on PhoneNumber {\n  id\n  number\n  phoneType\n  countryCode\n  __typename\n}\n\nfragment socialMedia on SocialMedia {\n  id\n  username\n  socialMediaPlatform {\n    ...socialMediaPlatform\n    __typename\n  }\n  __typename\n}\n\nfragment socialMediaPlatform on SocialMediaPlatform {\n  id\n  name\n  __typename\n}\n\nfragment travelDocument on TravelDocument {\n  id\n  number\n  documentTypeCode\n  __typename\n}\n"}', $this->headers + ["Authorization" => $this->State['Authorization']]);
        $response = $this->http->JsonLog(null, 3, false, 'firstName');

        $email = $response->data->login->user->profile->emails[0]->address ?? null;
        $this->logger->debug("[email]: {$email}");

        if (
            $email
            && (
                strtolower($email) == strtolower($this->AccountFields['Login'])
                || in_array($this->AccountFields['Login'], [
                    'pam_and_devin@yahoo.com',
                    'robku20@aol.com',
                    'arnold112@yahoo.com',
                    'booldaak@gmail.com',
                    'n.hong@osjl.com',
                ])
            )
        ) {
            $this->headers += ["Authorization" => $this->State['Authorization']];

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
