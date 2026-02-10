<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBcanarias extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.bintercanarias.com/en/bintermas/my-points';

    private $headers = [
        "Accept"        => "application/json, text/plain, */*",
        "Content-Type"  => "application/json",
        "X-AUTH-TOKEN"  => "",
        "X-ENVIRONMENT" => "desktop",
        "X-HASH1"       => "",
        "X-HASH2"       => "",
        "Origin"        => "https://www.bintercanarias.com",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerBcanariasSelenium.php";

        return new TAccountCheckerBcanariasSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->SetProxy($this->proxyAustralia());
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields['Login2']['Options'] = [
            ''              => 'Select your Identification document',
            'userName'      => 'Username',
            'binterMasCard' => 'BinterMás Card',
            'spanishIdNum'  => 'DNI',
            'nie'           => 'NIE',
            'passport'      => 'Passport',
            'nonSpanichId'  => 'Non-Spanish ID',
        ];
    }

    public function IsLoggedIn()
    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
//        $this->http->RetryCount = 2;
//
//        if ($this->loginSuccessful()) {
//            return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.bintercanarias.com/en');

        if ($this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }

        /*
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://services.bintercanarias.com/graphql/main", '{"operationName":"hash_getHash1","variables":{},"query":"query hash_getHash1 {\n  hash_getHash1\n}\n"}', $this->headers);
        $this->http->RetryCount = 2;
        $hashResponse = $this->http->JsonLog();
        $hash1 = $hashResponse->data->hash_getHash1 ?? null;

        if (!$hash1) {
            return $this->checkErrors();
        }
        */

        switch ($this->AccountFields['Login2']) {
            case 'userName':
                $loginType = 'USERNAME';

                break;

            case 'passport':
            case 'nonSpanichId':
            case 'spanishIdNum':
                $loginType = 'DOCUMENT';

                break;

            case 'nie':
                $loginType = '';
                $this->DebugInfo = "need to find loginType";

                return false;

                break;

            case 'binterMasCard':
            default:
                $loginType = 'BINTERMAS';

                break;
        }

        /*
        $this->headers['X-HASH1'] = $hash1;
        $this->http->setCookie("HASH1", $hash1, ".bintercanarias.com");
        */
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://services.bintercanarias.com/graphql/main", '{"variables":{"login_type":"' . $loginType . '","username":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '"},"query":"mutation ($login_type: LoginTypeEnum!, $username: String!, $password: String!) {\n  login(login_type: $login_type, username: $username, password: $password) {\n    auth\n    token\n    hash2\n    __typename\n  }\n}\n"}', $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->data->login->auth, $response->data->login->token) && $response->data->login->auth == 'true') {
            $this->http->setCookie("userToken", $response->data->login->token, ".bintercanarias.com");
            $this->http->setCookie("HASH2", $response->data->login->token, ".bintercanarias.com");
            $this->headers['X-AUTH-TOKEN'] = $response->data->login->token;
            $this->headers['X-HASH2'] = $response->data->login->hash2;
//        if ($this->loginSuccessful()) {
            return true;
        }

        /*
        // Incorrect login or pass
        $message = $this->http->FindSingleNode('//div[contains(@class, "clear-warning")]/div[contains(@id, "flashMessage")]/text()');

        if (!is_null($message)) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Login details are not correct") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

//        $serverApp = $this->http->FindSingleNode('//script[@id = "serverApp-state"]');
        $serverApp = $this->http->FindPreg('/<script id="serverApp-state" type="application\/json">(.*)<\/script/ims');
        $serverApp = str_replace('&l;', '<', $serverApp);
        $serverApp = str_replace('&g;', '>', $serverApp);
        $serverApp = str_replace('&q;', '"', $serverApp);
        $this->logger->debug(var_export($serverApp, true), ['pre' => true]);
        $serverAppJson = $this->http->JsonLog($serverApp, 3, false, 'documentNumber');
        $userData = null;

        foreach ($serverAppJson as $data) {
            if (!isset($data->data->user_getBasic)) {
                continue;
            }

            $userData = $data->data->user_getBasic;
        }

        if (empty($userData)) {
            return;
        }

        // Name
        $this->SetProperty('Name', beautifulName($userData->name . " " . $userData->surname1));
        // Status
        $this->SetProperty('Status', $userData->cardLevel);
        // Balance - BinterMás points
        $this->SetBalance($userData->points);
        // BinterMas Card Number
        $this->SetProperty('Number', $userData->frequentFlyer);

        // todo: not working on the site
        $this->logger->info("User account", ['Header' => 3]);
        $this->http->PostURL("https://services.bintercanarias.com/graphql/main", '{"operationName":"user_get","variables":{},"query":"query user_get {\n  user_get {\n    clientData {\n      token\n      genre\n      name\n      points\n      surname1\n      surname2\n      documentType\n      documentNumber\n      birthDay\n      username\n      acceptCommercialNotifications\n      acceptProfiling\n      flightNotifications\n      frequentFlyer\n      cardLevel\n      cicarCode\n      points\n      levelPoints12M\n      expiredPoints\n      country {\n        ...country\n        __typename\n      }\n      language {\n        code2Chars\n        name\n        __typename\n      }\n      resident\n      residentDocumentType\n      residentDocumentNumber\n      residentTown {\n        code\n        name\n        island {\n          code\n          name\n          province {\n            code\n            name\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      personalContactData {\n        name\n        document\n        main\n        country {\n          code2Chars\n          name\n          __typename\n        }\n        province\n        town\n        address\n        postalCode\n        mainPhone\n        secondaryPhone\n        email\n        __typename\n      }\n      companyContactData {\n        name\n        document\n        main\n        country {\n          code2Chars\n          code3Chars\n          name\n          europeanComunitary\n          phonePrefix\n          __typename\n        }\n        province\n        town\n        address\n        postalCode\n        mainPhone\n        secondaryPhone\n        email\n        __typename\n      }\n      personalContactData {\n        ...bmasContactData\n        __typename\n      }\n      companyContactData {\n        ...bmasContactData\n        __typename\n      }\n      emergencyContact {\n        name\n        country {\n          ...country\n          __typename\n        }\n        phone\n        __typename\n      }\n      preferedAirport {\n        ...destination\n        __typename\n      }\n      educationLevel\n      occupation\n      occupationSector\n      cardState\n      __typename\n    }\n    beneficiaries {\n      name\n      surname1\n      surname2\n      documentType\n      documentNumber\n      birthDay\n      relation\n      __typename\n    }\n    companions {\n      genre\n      name\n      surname1\n      surname2\n      documentType\n      documentNumber\n      resident {\n        documentType\n        documentNumber\n        town {\n          ...town\n          __typename\n        }\n        __typename\n      }\n      birthDay\n      frequentFlyer\n      emergencyContact {\n        name\n        country {\n          ...country\n          __typename\n        }\n        phone\n        __typename\n      }\n      __typename\n    }\n    preferences {\n      code\n      category\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment country on Country {\n  code2Chars\n  code3Chars\n  name\n  europeanComunitary\n  phonePrefix\n  __typename\n}\n\nfragment town on Town {\n  code\n  name\n  island {\n    ...island\n    __typename\n  }\n  __typename\n}\n\nfragment island on Island {\n  code\n  name\n  province {\n    ...province\n    __typename\n  }\n  __typename\n}\n\nfragment province on Province {\n  code\n  name\n  __typename\n}\n\nfragment bmasContactData on BmasContactData {\n  name\n  document\n  main\n  country {\n    ...country\n    __typename\n  }\n  province\n  town\n  address\n  postalCode\n  mainPhone\n  secondaryPhone\n  email\n  __typename\n}\n\nfragment destination on Destination {\n  iata\n  name\n  slug\n  description\n  group\n  groupTitle\n  country {\n    ...country\n    __typename\n  }\n  __typename\n}\n"}', $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'levelPoints12M');
        // Level points
        $this->SetProperty('LevelPoints', $response->data->user_get->clientData->levelPoints12M ?? null);

        // Check reservations
        $this->logger->info("My bookings", ['Header' => 3]);
        $this->http->PostURL("https://services.bintercanarias.com/graphql/main", '{"variables":{"type":"ALIVE","ticket":null,"origin":null,"destination":null,"flightDateMin":"","flightDateMax":"","pageNumber":1,"pageSize":3},"query":"query ($type: MyReservationTypeEnum!, $ticket: String, $origin: String, $destination: String, $flightDateMin: DateTime, $flightDateMax: DateTime, $pageNumber: Int, $pageSize: Int) {\n  reservation_getMyReservations(\n    type: $type\n    ticket: $ticket\n    origin: $origin\n    destination: $destination\n    flightDateMin: $flightDateMin\n    flightDateMax: $flightDateMax\n    paginator: {pageNumber: $pageNumber, pageSize: $pageSize, orderType: \"DESC\", orderField: \"firstFlightDate\"}\n  ) {\n    pnr\n    surname\n    reservationDate\n    firstFlightDate\n    route\n    tickets {\n      ticket\n      name\n      surnames\n      __typename\n    }\n    __typename\n  }\n}\n"}', $this->headers);
        $response = $this->http->JsonLog();
        /* Send notification if reservations exists
         * If is logged in successful - return html, where string: No results for search - no reservations.
         */
        if ($response->data->reservation_getMyReservations != []) {
            $this->sendNotification('refs #14001: Find reservations');
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//div[contains(@class,"login-block")]/span[contains(@class, "user-name")]')) {
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
