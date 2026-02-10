<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDominos extends TAccountChecker
{
    use ProxyList;

    public $regionOptions = [
        ""       => "Select your region",
        "Canada" => "Canada",
        "USA"    => "USA",
    ];

    protected $_profile;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $authUrl = "https://authproxy.dominos.com/auth-proxy-service/token.oauth2";
    private $domain = "com";
    private $headers = [
        "DPZ-Language"  => "en",
        "DPZ-Market"    => "UNITED_STATES",
        "DPZ-Pattern"   => "Full",
        "Market"        => "UNITED_STATES",
        "Referer"       => "https://authproxy.dominos.com/assets/build/xdomain/proxy.html",
        "X-DPZ-D"       => "3e8421ff528954a2a6cdbd653b517caaf296b9e6bec3c05a0551eecf0ae1ccbb",
        'Content-Type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
        "Authorization" => "bm9sby1ybTo=",
        "Accept"        => "application/json, text/javascript, */*; q=0.01",
    ];
    private $headersUs = [
        'Accept'       => '*/*',
        'Content-Type' => 'application/json',
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if (isset($this->AccountFields['Login2']) && $this->AccountFields['Login2'] == 'Canada') {
            $this->headers['Market'] = "CANADA";
            $this->headers['DPZ-Market'] = "CANADA";
            $this->headers['Referer'] = "https://order.dominos.ca/";
            $this->authUrl = "https://authproxy.dominos.ca/auth-proxy-service/token.oauth2";
            $this->domain = "ca";

            if ($this->attempt == 1) {
//                $this->http->SetProxy($this->proxyReCaptchaIt7());
                $this->setProxyBrightData();
            } else {
                $this->http->SetProxy($this->proxyReCaptchaVultr());
            }

            $this->http->setRandomUserAgent(5, false, false);
        }
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }
        $this->http->setDefaultHeader("Authorization", $this->State['Authorization']);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

//        if ($this->AccountFields['Login2'] == 'Canada') {
//            $this->http->setUserAgent("AwardWallet Service. Contact us at awardwallet.com/contact"); // "Network error 28 - Operation timed out after 30001 milliseconds with 0 out of 0 bytes received" workaroud
//        }

        if ($this->AccountFields['Login2'] != 'Canada') {
            $this->http->GetURL("https://www.dominos.{$this->domain}/en/index");
            $captcha = $this->parseReCaptcha('6LdRZqoUAAAAAJ2SsP_3UXYCtAHSSonZOW5KAGHb');

            if (!$captcha) {
                return false;
            }

            $data = '{"query":"\n  \n  fragment LoyaltyFields on CustomerLoyalty {\n    AccountStatus\n    BasePointExpirationDate\n    EnrollDate\n    LastActivityDate\n    LoyaltyCoupons {\n      BaseCoupon\n      CouponCode\n      LimitPerOrder\n      PointValue\n    }\n    PendingPointBalance\n    VestedPointBalance\n  }\n\n  \n  \n  fragment CardFields on HistoricalCard {\n    id\n    nickName\n  }\n\n  \n  fragment OrderFields on HistoricalOrder {\n    Address {\n      City\n      DeliveryInstructions\n      IsDefault\n      OrganizationName\n      PostalCode\n      Region\n      Street\n      StreetName\n      StreetNumber\n      Type\n      UnitNumber\n      UnitType\n    }\n    BusinessDate\n    Coupons {\n      Code\n      ID\n      Qty\n      Tags {\n        Hash\n      }\n    }\n    DeliveryHotspot {\n      Description\n      Id\n      Name\n    }\n    OrderID\n    OrderMessages {\n      Message\n      MessageCategory\n    }\n    OrderMethod\n    Payments {\n      CardID\n      CardType\n      Type\n    }\n    Phone\n    Products {\n      CategoryCode\n      Code\n      ID\n      Options\n      Qty\n      Tags {\n        couponHash\n      }\n      descriptions {\n        portionCode\n        value\n      }\n      name\n    }\n    PlaceOrderTime\n    ServiceMethod\n    StoreID\n  }\n\n  \n  fragment StoreFields on HistoricalStore {\n    address {\n      City\n      PostalCode\n      Region\n      Street\n    }\n    storeName\n  }\n\n\n  fragment OrderHistoryFields on OrderHistory {\n    customerOrders {\n      addressNickName\n      cards {\n        ...CardFields\n      }\n      deliveryInstructions\n      id\n      order {\n        ...OrderFields\n      }\n      store {\n        ...StoreFields\n      }\n    }\n    easyOrder {\n      addressNickName\n      cards {\n        ...CardFields\n      }\n      deliveryInstructions\n      order {\n        ...OrderFields\n      }\n      easyOrder\n      easyOrderNickName\n      id\n      store {\n        ...StoreFields\n      }\n    }\n    products\n    productsByCategory {\n      category\n      productKeys\n    }\n    productsByFrequencyRecency {\n      frequency\n      productKey\n    }\n  }\n\n\n  query detailedLogin($input: LoginInput) {\n    detailedLogin(input: $input) {\n      Customer {\n        Addresses {\n          AddressLine1\n          AddressLine2\n          AddressLine3\n          AddressLine4\n          BuildingID\n          CampusID\n          City\n          Coordinates {\n            Latitude\n            Longitude\n          }\n          DeliveryInstructions\n          IsDefault\n          LocationName\n          Name\n          Neighborhood\n          PlaceType\n          PostalCode\n          PropertyNumber\n          PropertyType\n          Region\n          SectorName\n          Street\n          StreetAddress2\n          StreetField1\n          StreetField2\n          StreetName\n          StreetNumber\n          StreetRange\n          SubNeighborhood\n          Type\n          UnitNumber\n          UnitType\n          UpdateTime\n        }\n        Age13OrOlder\n        AgreeToTermsOfUse\n        AlternateExtension\n        AlternatePhone\n        AsOfTime\n        BirthDate\n        CustomerID\n        CustomerIdentifiers\n        Details\n        Email\n        EmailOptIn\n        EmailOptInTime\n        Extension\n        FirstName\n        Gender\n        IPAddress\n        LastLogin\n        LastName\n        Loyalty {\n          ...LoyaltyFields\n        }\n        Market\n        Nonce\n        Phone\n        PhonePrefix\n        Status\n        SmsOptIn\n        SmsOptInTime\n        SmsPhone\n        StJudeRoundUp\n        TaxInformation\n        Type\n        UpdateTime\n        URL\n        Vehicles {\n          Color\n          IsDefault\n          Make\n          Model\n          PreferredLocation\n        }\n      }\n      OrderHistory {\n        ...OrderHistoryFields\n      }\n      accessToken\n      errors\n      refreshToken\n    }\n  }\n","variables":{"input":{"password":"' . str_replace(['\\', '"'], ['\\\\', '\"'], $this->AccountFields['Pass']) . '","rememberMe":true,"username":"' . $this->AccountFields['Login'] . '","v2":"' . $captcha . '","v3":{"action":"","token":""},"auth":{"usesAuthProxy":true}}}}';
            $this->http->PostURL("https://www.dominos.{$this->domain}/graphql", $data, $this->headersUs);

            return true;
        }
        // page with login form
//        $this->http->GetURL("https://www.dominos.com/en/pages/customer/#/customer/rewards/");

        $data = [
            "client_id"    => "nolo-rm",
            "grant_type"   => "password",
            "password"     => $this->AccountFields['Pass'],
            "scope"        => "customer:card:read customer:profile:read:extended customer:orderHistory:read customer:card:update customer:profile:read:basic customer:loyalty:read customer:orderHistory:update customer:card:create customer:loyaltyHistory:read order:place:cardOnFile customer:card:delete customer:orderHistory:create customer:profile:update easyOrder:optInOut easyOrder:read",
            "username"     => $this->AccountFields['Login'],
            "validator_id" => "VoldemortCredValidator",
        ];

        $captcha = $this->parseReCaptcha($this->AccountFields['Login2'] == 'Canada'
            ? '6LdF-pQmAAAAAI4LvMaMjHWXjAj5UJ1NsKbYZT1G'
            : '6LcHcqoUAAAAAALG9ayDxyq5yuWSZ3c3pKWQnVwJ'
        );

        if (!$captcha) {
            return false;
        }

        $this->headers['X-DPZ-CAPTCHA'] = 'google-recaptcha-v3-enterprise-gnolo;token=' . $captcha . ';action=authproxyservice/tokenoauth2';

        $this->http->RetryCount = 0;
        $this->http->PostURL($this->authUrl, $data, $this->headers);
        $response = $this->http->JsonLog();

        if (empty($response->access_token)) {
            $this->checkCredentials($response);
            $this->DebugInfo = $this->http->Error;

            if (
                strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
                || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
                || strpos($this->http->Error, 'Network error 56 - Received HTTP code') !== false
            ) {
                $this->markProxyAsInvalid();

                throw new \CheckRetryNeededException(3, 5 * $this->attempt);
            }

            return $this->checkErrors();
        }// if (empty($response->access_token))

        $this->captchaReporting($this->recognizer);
        $this->http->setDefaultHeader("Authorization", "Bearer {$response->access_token}");
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The The server encountered an unknown error and was unable to complete your request.
        if (
            $this->http->FindSingleNode("//p[contains(text(), 'The The server encountered an unknown error and was unable to complete your request.')]") && in_array($this->http->Response['code'], [503, 502])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->AccountFields['Login2'] != 'Canada') {
            $response = $this->http->JsonLog(null, 2);

            if (isset($response->errors[0]->extensions->response->body->error)) {
                if ($response->errors[0]->extensions->response->body->error == 'invalid_grant') {
                    throw new CheckException("We could not locate a Pizza Profile with that e-mail and password combination. Please make sure you are using the e-mail address associated with your Domino's Pizza Profile.", ACCOUNT_INVALID_PASSWORD);
                }
            }

            if (isset($response->data->detailedLogin->accessToken)) {
                $this->http->setDefaultHeader("Authorization", "Bearer {$response->data->detailedLogin->accessToken}");

                return true;
            }

            return false;
        }
        $formData = [
            'loyaltyIsActive' => true,
            'rememberMe'      => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://order.dominos.' . $this->domain . '/power/login', $formData, ['X-Requested-With' => 'XMLHttpRequest']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $this->checkCredentials($response);

        if ($this->loginSuccessful()) {
            $this->State["Authorization"] = $this->http->getDefaultHeader("Authorization");

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //if ($this->AccountFields['Login2'] != "Canada") {
        $auth = $this->http->JsonLog(null, 0);
        $customerID = $auth->data->detailedLogin->Customer->CustomerID // USA
            ?? $auth->CustomerID; // CA

            $this->http->GetURL("https://order.dominos.{$this->domain}/power/customer/{$customerID}/loyalty?_=" . date('UB'));
        $response = $this->http->JsonLog(null, 1);

        if (empty($this->http->Response['body']) && 404 === $this->http->Response['code']) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        // Download from .com
        $this->http->GetURL("https://cache.dominos.com/nolo/ca/en/6_121_13/assets/build/js/site/boot.js");

        $redemptionPoints = $this->http->FindPreg('/\{\s*Potential:\s*\{\s*Burn:\s*\{\s*RedemptionPoints:\s*(\d+)\s*\}/us');

        if ($redemptionPoints === null) {
            return;
        }

        $basePoints = null;

        if (empty($response->LoyaltyCoupons)) {
            return;
        }

        foreach ($response->LoyaltyCoupons as $coupons) {
            if ($coupons->BaseCoupon === true) {
                $basePoints = $coupons->PointValue;

                break;
            }
        }
        // https://cache.dominos.com/olo/6_103_3/assets/build/js/modules/dpz.loyalty.js
        $this->SetBalance($response->VestedPointBalance - $redemptionPoints);
        $nextFreePizza = ($basePoints - $this->Balance);

        if ($nextFreePizza > 0) {
            $this->SetProperty('NextFreePizza', $basePoints - $this->Balance);
        } else {
            $freeValue = (int) floor($this->Balance / $basePoints);

            if ($freeValue > 0) {
                $this->AddSubAccount([
                    'Code'        => "FreePizzaAvailable",
                    'DisplayName' => "Free Pizza Available",
                    'Balance'     => $freeValue,
                ]);
            }
        }

        $firstName = $auth->data->detailedLogin->Customer->FirstName ?? $auth->FirstName;
        $lastName = $auth->data->detailedLogin->Customer->LastName ?? $auth->LastName;
        $this->SetProperty('Name', "$firstName $lastName");

        // Expiration Date
        if (!empty($response->BasePointExpirationDate)) {
            $this->logger->info("Expiration Date {$response->BasePointExpirationDate} - " . var_export(strtotime($response->BasePointExpirationDate),
                        true));

            if ($expiration = strtotime($response->BasePointExpirationDate, false)) {
                $this->SetExpirationDate($expiration);
            }
        }

        return;
//        }

        if (empty($this->_profile)) {
            return;
        }
        $this->http->GetURL('https://order.dominos.' . $this->domain . '/power/customer/' . $this->_profile->CustomerID . '/loyalty?_=' . time() . date('B'));
        $response = $this->http->JsonLog();

        if (empty($this->http->Response['body']) && 404 === $this->http->Response['code']) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        if (!isset($response->VestedPointBalance)) {
            // Unable to retrieve loyalty information.
            if ($this->http->FindSingleNode("//p[contains(text(), 'The The server encountered an unknown error and was unable to complete your request.')]") && $this->http->Response['code'] == 500) {
                throw new CheckException('Unable to retrieve loyalty information.', ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        $this->SetProperty('Name', beautifulName(trim($this->_profile->FirstName . ' ' . $this->_profile->LastName)));

        // VestedPointBalance - a.Potential.Burn.RedemptionPoints
        // Откуда приходит RedemptionPoints не очень понятно, дефолт == 0, следуя логике в JS будет так
        $balancePoint = $response->VestedPointBalance;

        if (isset($response->RedemptionPointBalance)) {
            $balancePoint -= $response->RedemptionPointBalance;
            $balancePoint > 0 ?: $balancePoint = 0;
        }
        // Total Available Points
        $this->SetBalance($balancePoint);

        if (empty($response->LoyaltyCoupons)) {
            return;
        }
        $couponCode = $response->CouponCode ?? null;
        $pointValue = 0;
        $pointValueBase = 0;

        for ($i = -1, $iCount = count($response->LoyaltyCoupons); ++$i < $iCount;) {
            if ($response->LoyaltyCoupons[$i]->CouponCode == $couponCode) {
                $pointValue = $response->LoyaltyCoupons[$i]->PointValue;
            }

            if (1 == $response->LoyaltyCoupons[$i]->BaseCoupon) {
                $pointValueBase = $response->LoyaltyCoupons[$i]->PointValue;
            }
        }// for ($i = -1, $iCount = count($response->LoyaltyCoupons); ++$i < $iCount;)
        empty($pointValue) && !empty($pointValueBase) ? $pointValue = $pointValueBase : null;

        // Next Free Pizza
        $pendingPoint = empty($response->PendingPointBalance) && !empty($balancePoint) ? $balancePoint : 0;
        $pendingPoint == $pointValue ? $pendingPoint = 0 : ($pendingPoint > $pointValue ? $pendingPoint -= $pointValue : 0);

        if ($pendingPoint > $pointValue) {
            $pendingPoint -= $pointValue;
        }

        $this->SetProperty('NextFreePizza', $pointValue - $pendingPoint);

        // Free Pizzas Available
        $free = floor($balancePoint / $pointValue);
        $free > 0 ?: $free = 0;
        $this->AddSubAccount([
            'Code'           => "FreePizzaAvailable",
            'DisplayName'    => "Free Pizza Available",
            'Balance'        => $free,
        ]);

        // Expiration Date
        if (!empty($response->BasePointExpirationDate)) {
            $this->logger->info("Expiration Date {$response->BasePointExpirationDate} - " . var_export(strtotime($response->BasePointExpirationDate), true));

            if ($expiration = strtotime($response->BasePointExpirationDate, false)) {
                $this->SetExpirationDate($expiration);
            }
        }
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    protected function checkCredentials($response)
    {
        $this->logger->notice(__METHOD__);

        if ((isset($response->Status) && '-403' == $response->Status && isset($response->StatusItems[0]->Code) && 'NotAuthorized' == $response->StatusItems[0]->Code)
            || (isset($response->error_description)
                && (in_array($response->error_description, [
                    'System error: fail to process username & password combination.',
                    'Invalid username & password combination.',
                    "We didn't recognize the username or password you entered. Please try again.",
                ])
                    || strstr($response->error_description, '{"code":"authn.srvr.code.reset.password","firstName":"')
                    || strstr($response->error_description, '{code:authn.srvr.code.reset.password,firstName:')
                )
            )
        ) {
//            $this->captchaReporting($this->recognizer); -> "Invalid username & password combination." may be wrong error if captcha answer incorrect
            throw new CheckException('We could not locate a Pizza Profile with that e-mail and password combination. Please make sure you are using the e-mail address associated with your Domino\'s Pizza Profile.', ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->error_description) && in_array($response->error_description, [
            'Your account is locked. Please reset your password.',
        ])
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('This account has been locked for exceeding the number of invalid login attempts.', ACCOUNT_LOCKOUT);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $formData = [
            'loyaltyIsActive' => true,
            'rememberMe'      => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://order.dominos.' . $this->domain . '/power/login', $formData, ['X-Requested-With' => 'XMLHttpRequest']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);
        $email = $response->Email ?? null;

        if (strtolower($email) === strtolower($this->AccountFields['Login'])) {
            $this->_profile = $response;

            return true;
        }

        return false;
    }

    private function parseReCaptcha($key)
    {
        $this->http->RetryCount = 0;

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"         => "RecaptchaV3TaskProxyless",
//            "websiteURL"   => "https://www.dominos.com/",
//            "websiteKey"   => $key,
//            "minScore"     => 0.9,
//            "pageAction"   => "login",
//            "isEnterprise" => true,
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->AccountFields['Login2'] == 'Canada' ? 'https://order.dominos.ca/' : "https://www.dominos.com/",
            "proxy"     => $this->http->GetProxy(),
            //"version"   => "v3",
            //"action"    => "login",
            //"min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
