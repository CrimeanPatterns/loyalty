<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEddiebauer extends TAccountChecker
{
    use ProxyList;

    private $headers = [
        "Accept"          => "*/*",
        "Accept-Encoding" => "gzip, deflate, br",
        "content-type"    => "application/json",
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "eddiebauerReward"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyGoProxies(null, 'uk');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }
        $this->headers['x-access-token'] = $this->State['token'];

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please use your email registered with Eddie Bauer as login', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.eddiebauer.com/my-account?view=rewards");

        if (!$this->http->ParseForm('login')) {
            return $this->checkErrors();
        }

        $email = $this->AccountFields['Login'];
        $password = $this->AccountFields['Pass'];

        // cart
        $query['query'] = '{  cart {    ...CartFragment    __typename  }}fragment CartItemDetailsFragment on CartItemDetails {  code  title  image  variants {    label    name    value    __typename  }  hemming {    label    name    value    __typename  }  productCode  productGroups  attributes  isNew  __typename}fragment AddressFragment on Address {  id  firstName  lastName  address1  address2  zipCode  city  state  region  country  phone  email  kind  isDefaultShipTo  __typename}fragment ShippingAddressFragment on ShippingAddress {  id  firstName  lastName  address1  address2  zipCode  city  state  region  country  phone  email  kind  isDefaultShipTo  isVerified  additionalPickupPerson {    name    email    __typename  }  __typename}fragment CartFragment on Cart {  id  userId  shippingMethod {    id    minDays    maxDays    name    price {      amount      currency      __typename    }    __typename  }  tax  discounts {    id    type    code    amount    balance    itemDiscounts {      itemId      name      amount      orderDiscountShare      discountType      __typename    }    __typename  }  shippingAddress {    ...ShippingAddressFragment    __typename  }  billingAddress {    ...AddressFragment    __typename  }  subTotalAmount {    currency    amount    __typename  }  items {    id    isGiftBox    giftBox    quantity    availableQuantity    shipsBy    extra    delivery {      mode      storeCode      __typename    }    price {      list {        currency        amount        kind        __typename      }      final {        currency        amount        kind        __typename      }      discount      __typename    }    totalPrice {      currency      amount      kind      __typename    }    discountDetails {      amount      code      itemId      orderDiscountShare      discountType      __typename    }    eligibility {      isGiftboxable      __typename    }    item {      ...CartItemDetailsFragment      __typename    }    __typename  }  donations {    id    extra    quantity    price {      list {        currency        amount        kind        __typename      }      final {        currency        amount        kind        __typename      }      discount      __typename    }    totalPrice {      currency      amount      kind      __typename    }    item {      ...CartItemDetailsFragment      __typename    }    __typename  }  errors {    errorFor    message    __typename  }  extra  hasFreeShipping  shippingDiscount {    amount    displayName    __typename  }  __typename}';
        $this->http->PostURL("https://www.eddiebauer.com/graphql", json_encode($query));
        $shortToken = $this->http->Response['headers']['x-access-token'] ?? null;

        if (!$shortToken) {
            $this->logger->error("x-access-token not found");

            return $this->checkErrors();
        }

        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/json",
            "Referer"         => "https://www.eddiebauer.com/acc-login-submit",
            "x-access-token"  => $shortToken,
        ];

        $data = [
            'operationName' => 'userAuthenticate',
            'query'         => 'mutation userAuthenticate($email: String!, $password: String!, $keepMeLoggedIn: Boolean) {userAuthenticate(email: $email, password: $password, keepMeLoggedIn: $keepMeLoggedIn) {id email name {first middle last __typename } keepUserLoggedIn code message __typename }}',
            'variables'     => [
                'email'          => $email,
                'password'       => $password,
                'keepMeLoggedIn' => false, //todo: broken from 4 Nov 2020 //true,
            ],
        ];

        $this->http->PostURL("https://www.eddiebauer.com/graphql", json_encode($data), $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /*
        if ($this->http->FindSingleNode('//h2[contains(text(), "The request could not be satisfied.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        if ($this->http->FindSingleNode('//img[@src="/s3/site-maintenance.jpg"]/@src')) {
            throw new CheckException("Our site is currentlydown for maintenance. We will back up soon. Thanks for your patience.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $fullToken = $this->http->Response['headers']['x-access-token'] ?? null;

        if (!$fullToken) {
            $this->logger->error("x-access-token not found");

            return $this->checkErrors();
        }
        $this->headers['x-access-token'] = $fullToken;
        $response = $this->http->JsonLog();

        if (!empty($response->data->userAuthenticate->email)) {
            if ($this->loginSuccessful()) {
                $this->State['token'] = $this->headers['x-access-token'];

                return true;
            }
        }

        // errors
        $message = $response->data->userAuthenticate->message ?? null;

        if (!empty($message)) {
            $code = $response->data->userAuthenticate->code ?? null;
            $failedLoginCount = $response->data->userAuthenticate->message->failedLoginCount ?? null;

            if ($code == "AUTH_FAILED" && $failedLoginCount) {
                throw new CheckException("Failed sign in attempt, you have " . (5 - $failedLoginCount) . " more attempts before your account is locked", ACCOUNT_INVALID_PASSWORD);
            }

            $type = $message->type ?? null;

            if ($code == "AUTH_FAILED" && $type == 'user_deactivated') {
                // ???
                return false;
            }

            $this->logger->error($message);

            if ($message == "Force user to change password as password constraint not statified") {
                $this->throwProfileUpdateMessageException();
            }

            if ($message == "User Not Found.") {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "Password needs to be updated to satisfy constraints") {
                throw new CheckException("Due to recent security updates, we will require you to update your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($code == 'USER_LOCKED' && $message == "User is Locked.") {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $firstName = $response->data->user->name->first ?? '';
        $lastName = $response->data->user->name->last ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName));

        // rewardStatus
        $query = '{"operationName":"getDashboardRewardDetails","variables":{},"query":"query getDashboardRewardDetails {\n  getDashboardRewardDetails {\n    loyalty {\n      keepTier\n      description\n      expirationDate\n      joinDate\n      currentTierName\n      customerId\n      balanceInDollars\n      currentTier {\n        tierName\n        tierPoints\n        tierRewards\n        __typename\n      }\n      nextTier {\n        tierAway\n        tierName\n        tierPoints\n        tierRewards\n        tierRewardAmount\n        tierMaxPoints\n        isTopTier\n        __typename\n      }\n      available {\n        qcode\n        bcode\n        discountMsg\n        amount\n        expDate\n        applyToBagUrl\n        tierImageUrl\n        isAvailable\n        isFuture\n        availableFrom\n        __typename\n      }\n      __typename\n    }\n    tierBenefits {\n      tierName\n      benefits\n      channel\n      __typename\n    }\n    __typename\n  }\n}"}';
        $this->http->PostURL("https://www.eddiebauer.com/graphql", $query, $this->headers);
        $response = $this->http->JsonLog();

        // Balance - Current Points Balance
        $this->SetBalance($response->data->getDashboardRewardDetails->loyalty->currentTier->tierPoints ?? null);
        // Member Number
        $this->SetProperty("Number", $response->data->getDashboardRewardDetails->loyalty->customerId ?? null);
        // Status
        $this->SetProperty("Status", $response->data->getDashboardRewardDetails->loyalty->currentTierName ?? null);

        // Points to the next reward - 120 points away from your $5.00 reward!
        $this->SetProperty("PointsToTheNextReward",
            $response->data->getDashboardRewardDetails->loyalty->nextTier->tierPoints ?? null);
        // Spend to the next tier - Away from Guide Benefits
        $this->SetProperty("SpendToTheNextTier",
            '$' . $response->data->getDashboardRewardDetails->loyalty->nextTier->tierAway ?? null);

        foreach ($response->data->getDashboardRewardDetails->loyalty->available ?? [] as $reward) {
            if ($reward->isAvailable === true) {
                $amount = round($reward->amount);
                $this->AddSubAccount([
                    "Code"           => "eddiebauerReward" . $reward->qcode,
                    "DisplayName"    => "\$$amount Reward ($reward->qcode)",
                    "Balance"        => $amount,
                    "ExpirationDate" => strtotime($reward->expDate),
                ]);
            }
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        // UserFragment
        $query = '{"variables":{},"query":"{\n  user {\n    ...UserFragment\n    __typename\n  }\n}\n\nfragment UserFragment on User {\n  id\n  userNumber\n  email\n  birthdate\n  dateOfBirth\n  mobileNumber\n  name {\n    first\n    middle\n    last\n    __typename\n  }\n  companyName\n  addresses {\n    ...AddressFragment\n    __typename\n  }\n  defaultShippingAddressId\n  defaultBillingAddressId\n  isEmailVerified\n  locale\n  gender\n  interests {\n    id\n    type\n    value\n    __typename\n  }\n  preferences {\n    adventureRewardsEmail\n    ebEmail\n    __typename\n  }\n  notifications {\n    birthdaygiftcode\n    signupSmsPromo\n    __typename\n  }\n  birthdayRewards {\n    bcode\n    amount\n    discountMsg\n    expDate\n    __typename\n  }\n  events {\n    type\n    id\n    value\n    __typename\n  }\n  customerId\n  ebCCUser\n  mobileNumber\n  smsOptIn {\n    status\n    optInDate\n    optOutDate\n    __typename\n  }\n  categoryPreferences\n  userInterests\n  myStore {\n    storeCode\n    storeName\n    storeType\n    storeAddress {\n      addressLine1\n      addressLine2\n      city\n      state\n      country\n      zipCode\n      __typename\n    }\n    __typename\n  }\n  adventureUrl\n  __typename\n}\n\nfragment AddressFragment on Address {\n  id\n  firstName\n  lastName\n  address1\n  address2\n  zipCode\n  city\n  state\n  region\n  country\n  phone\n  email\n  kind\n  isDefaultShipTo\n  isDefaultBillTo\n  isVerified\n  __typename\n}"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.eddiebauer.com/graphql", $query, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!empty($response->data->user->email)
            && strtolower($this->AccountFields['Login']) === strtolower($response->data->user->email)) {
            return true;
        }

        return false;
    }
}
