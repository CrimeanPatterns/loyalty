<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAcmoore extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.michaels.com/buyertools/rewards/my-rewards';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->setProxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA);

//        $this->useFirefox();
//        $this->setKeepProfile(true);

        $this->useGoogleChrome();

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if ($fingerprint !== null) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->disableImages();
        $this->useCache();
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $this->waitForElement(WebDriverBy::xpath('//header//div[starts-with(@id, "popover-trigger")]//p[starts-with(@class, "chakra-text")]'), 10);
        $this->saveResponse();

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('The email address is invalid.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.michaels.com');
        $this->http->GetURL('https://www.michaels.com/signin?returnUrl=/buyertools/rewards/my-rewards');

        $loginInput = $this->waitForElement(WebDriverBy::id('email'), 37);
        $pwdInput = $this->waitForElement(WebDriverBy::id('password'), 0);
        $this->driver->executeScript('var c = document.getElementById("onetrust-accept-btn-handler"); if (c) c.click();');
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@type="submit" and @form="sign-in-form"]'), 0);
        $this->saveResponse();

        if (!isset($loginInput, $pwdInput, $btn)) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $pwdInput->sendKeys($this->AccountFields['Pass']);
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // System maintenance
        if ($message = $this->http->FindSingleNode("
                //span[contains(text(),'we are currently performing system maintenance')]
                | //p[contains(text(),'We are making some important system upgrades to better serve our customers.')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        sleep(3);

        if ($this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
            $this->waitFor(function () {
                return !$this->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
            }, 120);

            $this->saveResponse();
            $this->logger->debug("click Log-in one more time");
            $this->waitForElement(WebDriverBy::xpath('//button[@type="submit" and @form="sign-in-form"]'), 0)->click();
        }

        $this->waitForElement(WebDriverBy::xpath('
            //header//div[starts-with(@id, "popover-trigger")]//p[starts-with(@class, "chakra-text")]
            | //form[@id = "sign-in-form"]/p[1]
            | //div[starts-with(@class, "chakra-input__group")][1]/following-sibling::div[1]/p
            | //div[starts-with(@class, "chakra-input__group")][1]/following-sibling::p[1]
        '), 20);
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        $message = $this->http->FindSingleNode('//form[@id = "sign-in-form"]/p[1]')
            ?? $this->http->FindSingleNode('//div[starts-with(@class, "chakra-input__group")][1]/following-sibling::div[1]/p')
            ?? $this->http->FindSingleNode('//div[starts-with(@class, "chakra-input__group")][1]/following-sibling::p[1]');

        if (isset($message)) {
            $this->logger->error("[Error]: $message");

            if (
                str_contains($message, 'The username or password entered do not match our records')
                || str_contains($message, 'Please specify a valid email address')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == '6-20 characters') {
                throw new CheckException("Password must be $message", ACCOUNT_INVALID_PASSWORD);
            }

            if (stristr($message, 'Something went wrong. Please try again')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        sleep(2);
        $this->http->FilterHTML = false;
        $this->saveResponse();
        // Rewards Number
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//img[@alt = "michaels-rewards"]/following-sibling::div[1]/h4'));
        // You are $X.XX away from your next voucher
        $this->SetProperty('DollarsToNextReward', $this->http->FindSingleNode('//p[starts-with(text(), "You are $")]', null, true, self::BALANCE_REGEXP));
        // You’ve earned $X.XX in Rewards
        $this->SetBalance($this->http->FindSingleNode('//div[@data-test-id = "CircularProgressbarWithChildren__children"]/p[2]', null, true, self::BALANCE_REGEXP));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode('//p[contains(text(), "Enroll in Michaels Rewards and start earning today!")]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Available Vouchers
        $this->parseVouchers();

        $this->http->GetURL('https://www.michaels.com/buyertools/profile');
        $pwdInput = $this->waitForElement(WebDriverBy::id('password'), 5);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Sign In"]'), 0);

        if (!isset($pwdInput, $btn)) {
            return;
        }
        $pwdInput->sendKeys($this->AccountFields['Pass']);
        $btn->click();
        sleep(2);
        $this->saveResponse();
        $firstName = $this->http->FindSingleNode('//p[text() = "First Name"]/following-sibling::p[1]');
        $lastName = $this->http->FindSingleNode('//p[text() = "Last Name"]/following-sibling::p[1]');
        $this->SetProperty('Name', beautifulName("$firstName $lastName"));

        /*
        // AccountID: 5454708
        if ($this->http->FindSingleNode('//h3[contains(text(), "Join our Rewards Program!")]')) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        $this->http->GetURL("https://www.michaels.com/coupons");
        $noBalance = false;
        $coupons = $this->http->XPath->query("//section[@class = 'sub-header-coupons']/ul/li");
        $this->logger->debug("Total {$coupons->length} coupons were found");

        if ($this->http->FindSingleNode('//li[contains(text(), "No Rewards coupons offered currently. Check back later for more Rewards coupons!")]')) {
            $noBalance = true;
        } elseif (
            $coupons->length > 0
            && $this->http->FindSingleNode('//h1[contains(text(), "Michaels Coupons and Promo Codes")]')
        ) {
            $noBalance = true;
        }

        foreach ($coupons as $coupon) {
            $title = $this->http->FindSingleNode('.//h4[@class = "short-title"]', $coupon);
            $exp = $this->http->FindSingleNode('.//h4[@class = "short-title"]/following-sibling::h5/span', $coupon, true, "/Valid through\s*([^<]+)/")
                // Valid: Sunday, December 13, 2020 through Wednesday, December 16, 2020
                ?? $this->http->FindSingleNode('.//h4[@class = "short-title"]/following-sibling::h5/span', $coupon, true, "/Valid:\s*([^<]+)through/")
            ;
            $barCode = $this->http->FindSingleNode('.//span[@class = "barcode"]/img/@src', $coupon, true, "/code=([^&]+)/");
            // Online Promo Code
            $promoCode = $this->http->FindSingleNode('.//p[contains(text(), "Online Promo Code:")]', $coupon, true, "/:\s*([^<]+)/");
            $this->AddSubAccount([
                'Code'           => 'acmooreCoupons' . $promoCode,
                'DisplayName'    => $title,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
                'PromoCode'      => $promoCode,
                'BarCode'        => $barCode ?? '',
                "BarCodeType"    => BAR_CODE_UPC_A,
            ]);
        }

        if ($noBalance === true) {
            $this->SetBalanceNA();
        } elseif ($message = $this->http->FindSingleNode('//li[@class = "coupon-loading-issue" and contains(text(), "We are facing technical issues with coupons, please check back later for coupons.")]')) {
            $this->SetWarning($message);
        }

        $this->http->GetURL("https://www.michaels.com/on/demandware.store/Sites-MichaelsUS-Site/default/Tealium-DPCInclude?&pt=Coupons");
        $response = $this->http->JsonLog();
//        // Balance - Point Balance
//        $this->SetBalance($response->crm_data->rewards_points ?? null);
//
//        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
//            if (!empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber']) && $this->http->FindPreg('/"rewards_points":null,/')) {
//                $this->SetBalance(0);
//            }
//            elseif (!empty($response->user->lastname) && $this->http->FindPreg("/customer_no\":null\},\"crm_data\":null\}/")) {
//                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
//            }
//        }
        */
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9112891.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395119,6695948,1536,872,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.455546053227,802933347973.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,113,113,0;0,-1,0,0,2135,2135,0;1,-1,0,0,2154,2154,0;0,-1,0,0,3433,3433,0;0,-1,0,0,3317,3317,0;0,-1,0,0,2984,2984,0;0,-1,0,0,3002,3002,0;1,-1,0,0,3002,3002,0;0,-1,0,0,1629,1629,0;0,-1,0,0,2091,2091,0;0,-1,0,0,2109,2109,0;0,-1,0,0,1266,1266,0;0,-1,0,0,5276,5276,0;0,-1,0,0,1526,1526,0;0,-1,0,0,725,520,0;-1,2,-94,-102,0,-1,0,0,113,113,0;0,-1,0,0,2135,2135,0;1,-1,0,0,2154,2154,0;0,-1,0,0,3433,3433,0;0,-1,0,0,3317,3317,0;0,-1,0,0,2984,2984,0;0,-1,0,0,3002,3002,0;1,-1,0,0,3002,3002,0;0,-1,0,0,1629,1629,0;0,-1,0,0,2091,2091,0;0,-1,0,0,2109,2109,0;0,-1,0,0,1266,1266,0;0,-1,0,0,5276,5276,0;0,-1,0,0,1526,1526,0;0,-1,0,0,725,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.michaels.com/Account-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1605866695947,-999999,17179,0,0,2863,0,0,7,0,0,3905176D7A13575AACADEBBA784FD6C3~-1~YAAQfJTcF5s0PaB1AQAAJCkd5QRyq0cc9H/TlizLIyIjrKA2kmDii1n2rje9iwtI32qu9grU6UylOd13jcVwhmMvE0E3PiqgdrDVKIeGzCytkofmYUjzfphqtQkLJxEe6nhuv7kNuVLUihAy6bvjgiLK+iyeu57xLfrvqsd3DHq5r3R06R5nzWBurPrdPgFuhNHfBa84G3jqVoGfB+AMfc58LYqNSp4DEru112qA25jQf3k9LOGkRNJ+yBixTpTcq63TcLNzEQ3uF2AgJ3gQrxDt1x8T6rWMe5wakkD6IkhnspSm3ZWqlwDBWgc=~-1~-1~-1,30440,-1,-1,30261693,PiZtE,39109,47-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,903953058-1,2,-94,-118,107461-1,2,-94,-129,-1,2,-94,-121,;10;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9112891.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395119,6695948,1536,872,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.486632997243,802933347973.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,113,113,0;0,-1,0,0,2135,2135,0;1,-1,0,0,2154,2154,0;0,-1,0,0,3433,3433,0;0,-1,0,0,3317,3317,0;0,-1,0,0,2984,2984,0;0,-1,0,0,3002,3002,0;1,-1,0,0,3002,3002,0;0,-1,0,0,1629,1629,0;0,-1,0,0,2091,2091,0;0,-1,0,0,2109,2109,0;0,-1,0,0,1266,1266,0;0,-1,0,0,5276,5276,0;0,-1,0,0,1526,1526,0;0,-1,0,0,725,520,0;-1,2,-94,-102,0,0,0,0,113,113,0;0,-1,0,0,2135,2135,0;1,-1,0,0,2154,2154,0;0,-1,0,0,3433,3433,0;0,-1,0,0,3317,3317,0;0,-1,0,0,2984,2984,0;0,-1,0,0,3002,3002,0;1,-1,0,0,3002,3002,0;0,-1,0,0,1629,1629,0;0,-1,0,0,2091,2091,0;0,-1,0,0,2109,2109,0;0,-1,0,0,1266,1266,0;0,-1,0,0,5276,5276,0;0,-1,0,0,1526,1526,0;0,-1,1,0,725,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.michaels.com/Account-1,2,-94,-115,1,32,32,0,0,0,0,647,0,1605866695947,46,17179,0,0,2863,0,0,647,0,0,3905176D7A13575AACADEBBA784FD6C3~-1~YAAQfJTcF6U0PaB1AQAAPy4d5QRmyzuR/G0k7FNT/i0J4NRqx3YOTNIXrQ1l1EZ07w3W2ZwU0TGxquK8Cabj6VDZRx8EZbuTgqdhXYyeCvd1O48wm+LdcqNnW/rIVUsRHIGxj/cnDizM67S4oAHd+ApvU5H1zCnc9PHaPvjVuIUPYBBe4VurwQsXrwoGnV14Zu0YlfmDJMesYV10wzbsn2+tG1rPF1/4r/PGLhRU8botJCbWRYslRAElAL8/R+HpBHEKUUGEa5LOxJD9ML+QzM6Q8cq++ZMgYymQGC7+Rxj4jDkwq+7Yi05v9H+Zs9RK+p82aFzwmDA7mQ==~-1~||1-jCJJRijqif-1-10-1000-2||~-1,32687,595,-1533856669,30261693,PiZtE,21802,39-1,2,-94,-106,9,1-1,2,-94,-119,32,35,35,35,54,56,38,31,34,27,6,6,10,393,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,903953058-1,2,-94,-118,113058-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,,,,0-1,2,-94,-121,;30;13;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        return $this->http->FindSingleNode('//header//div[starts-with(@id, "popover-trigger")]//p[starts-with(@class, "chakra-text")]') != null;
    }

    private function parseVouchers()
    {
        $this->logger->notice(__METHOD__);
        $vouchersRoot = $this->http->XPath->query('//h3[contains(text(), "Available Vouchers")]/parent::div/following-sibling::div[1]');

        if ($vouchersRoot->length != 1) {
            $this->logger->error('Vouchers root not found');

            return;
        }
        $vouchers = $this->http->XPath->query('div/div/div/div[starts-with(@class, "slick-slide ")]', $vouchersRoot->item(0));
        $this->logger->debug("Found $vouchers->length vouchers");

        for ($i = 0; $i < $vouchers->length; $i++) {
            $voucher = $vouchers->item($i);
            $number = $this->http->FindSingleNode('div/div/div/div/div/div[2]/p[2]', $voucher);
            $balance = $this->http->FindSingleNode('div/div/div/div/div/div[2]/h2', $voucher);
            $exp = $this->http->FindSingleNode('div/div/div/div/div/div[1]/p[2]', $voucher, true, '#(\d{1,2}/\d{1,2}/\d{4})#');

            if (!isset($number, $balance)) {
                continue;
            }
            $subAcc = [
                'Code'        => 'Coupons' . $number,
                'DisplayName' => 'Rewards Voucher #' . $number,
                'Balance'     => $balance,
            ];

            if (strtotime($exp)) {
                $subAcc['ExpirationDate'] = strtotime($exp);
            }
            $this->AddSubAccount($subAcc);
        }
    }
}
