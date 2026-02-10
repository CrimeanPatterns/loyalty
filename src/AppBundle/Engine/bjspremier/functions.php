<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBjspremier extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www1.bjsrestaurants.com/account/dashboard';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $headers;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->http->SetProxy($this->proxyReCaptchaVultr());
        $this->seleniumOptions->recordRequests = true;
        $this->http->saveScreenshots = true;

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
        //$this->useChromePuppeteer();
        $this->usePacFile(false);

        /*$this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
        */

        $resolutions = [
            [1280, 720],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://www.bjsrestaurants.com/account/dashboard');
        $number = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, " loyalty-id ")]'), 10);
        $logOut = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Log Out")]'), 0);
        $this->saveResponse();

        if ($logOut && $number) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        //$this->driver->manage()->window()->maximize();
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        //if (!strstr($this->http->currentUrl(), '/account/dashboard')) {
        $this->http->GetURL('https://www.bjsrestaurants.com/account/dashboard');
        //}

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@data-testid = "text-input-*email"]'), 7);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@data-testid = "text-input-*password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "login-button")]'), 0);

        if (!isset($login, $pwd, $btn)) {
            $this->saveResponse();

            return false;
        }
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 1000);
        $mover->steps = rand(1, 5);

        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login'], 3);

        $mover->moveToElement($pwd);
        $mover->click();
        $mover->sendKeys($pwd, $this->AccountFields['Pass'], 3);

        $this->saveResponse();
        $fieldMessage = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Please provide a valid email address')]"), 0);

        if ($fieldMessage) {
            throw new CheckException($fieldMessage->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        $captcha = $this->parseReCaptcha('6Le8Mu4kAAAAAHO4kIdZid7Jze40h2VUg8eP2eiF',
            'https://www.bjsrestaurants.com/account/login');

        if ($captcha === false) {
            return false;
        }
        $this->logger->info("Execute recaptcha");
        $this->driver->executeScript('document.getElementById("g-recaptcha-response").value = "' . $captcha . '";');
//        $this->waitFor(function () {
//            return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0))
//                /*|| !is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Could not connect to proxy related to the task, connection refused")]'), 0))*/;
//        }, 60);

        $this->saveResponse();

        if ($this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]')) {
            $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")]'), 20);
            $this->saveResponse();
        }
        sleep(2);
        $btn->click();
        sleep(2);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry for the inconvenience. We")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->saveResponse();

        $this->waitForElement(WebDriverBy::xpath("
                    //a[contains(@href, '/account/dashboard')] 
                    | //span[contains(text(), 'Credentials combination not valid')]
                    | //span[contains(text(), 'Oops. Something went wrong. Please try again.')]
                    | //span[contains(text(), 'reCAPTCHA validation failed')]
                    "), 13);
        $isLogin = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/account/dashboard")]'), 0);
        $message = $this->waitForElement(WebDriverBy::xpath("
                    //span[contains(text(), 'Credentials combination not valid')]
                    | //span[contains(text(), 'reCAPTCHA validation failed')]
        "), 0);
        $this->saveResponse();

        if ($isLogin) {
            $this->http->GetURL('https://www.bjsrestaurants.com/account/dashboard');

            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Log Out")]'), 7)) {
                return true;
            }
        }
        $this->saveResponse();

        if ($message) {
            $message = $message->getText();
            $this->logger->error("[Error]: {$message}");

            if ($message == 'reCAPTCHA validation failed') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0);
            }

            if (
                $message == 'Credentials combination not valid'
                || $message == 'Please provide a valid email address'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Oops. Something went wrong. Please try again.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $balance = $this->waitForElement(WebDriverBy::xpath('//h1[contains(@class, "pointsAreaHeading")]/following-sibling::div[div]/div/p[1]'), 5);
        $number =
            $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, " loyalty-id ")]'), 5)
            ?? $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, " loyalty-id ")]'), 0, false)
        ;
        $this->saveResponse();

        if (!$balance || !$number) {
            return;
        }

        // Balance - points
        $this->SetBalance($this->http->FindPreg('/^(\d+) points$/', null, $balance->getText()));
        // 16 points until next $10 reward
        $pointsToNextReward = $this->waitForElement(WebDriverBy::xpath('//h1[contains(@class, "pointsAreaHeading")]/following-sibling::div[div]/div/p[2]'), 0);
        $this->SetProperty('PointsToNextReward', $this->http->FindPreg('/(\d+) points until next /', null, $pointsToNextReward->getText()));
        // Number
        $this->SetProperty('Number', $this->http->FindPreg('/#(\w+)/', null, $number->getText()));

        $this->http->GetURL('https://www.bjsrestaurants.com/account/profile');
        $name = $this->waitForElement(WebDriverBy::xpath('//p[text() = "Name"]/following-sibling::p'), 5);
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', $name ? beautifulName($name->getText()) : null);

        $this->http->GetURL('https://www.bjsrestaurants.com/account/dashboard/rewards');
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "chakra-tabs__tab-panels")]'), 5);
        $this->saveResponse();

        // Available Rewards & Offers
        $coupons = $this->http->XPath->query("//div[contains(@class, 'chakra-tabs__tab-panels')]//img/following-sibling::div[1]");
        $this->logger->debug("total {$coupons->length} rewards and offers found");

        foreach ($coupons as $coupon) {
            $namePart1 = $this->http->FindSingleNode('div/p[1]', $coupon);
            $namePart2 = $this->http->FindSingleNode('div/p[2]', $coupon);
            $exp = strtotime($this->http->FindSingleNode('p', $coupon, true, '#Expires (\d{2}/\d{2}/\d{4})#') ?? '');

            if ($namePart1 && $namePart2 && $exp) {
                $this->AddSubAccount([
                    'Code'           => 'bjspremierCoupon' . preg_replace('/\W/', '', $namePart1 . $namePart2) . $exp,
                    'DisplayName'    => "$namePart1 - $namePart2",
                    'Balance'        => null,
                    'ExpirationDate' => $exp,
                ]);
            }
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Description"      => "Description",
            "Transaction type" => "Info",
            "Earned points"    => "Miles",
            //"Total" => "Amount",
            //"Currency" => "Currency",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->info('History json loaded');
        $this->driver->executeScript("
            XMLHttpRequest = new Proxy(XMLHttpRequest, {
                construct: function (target, args) {
                    const xhr = new target(...args);
                    xhr.onreadystatechange = function () {
                        if (xhr.responseType == 'blob' && xhr.responseURL == 'https://api.prod.bjsrestaurants.com/graphql') {
                            //console.log(xhr); 
                            xhr.response.text().then((value) => {
                                //console.log(value); 
                                if (/getOrderHistory/g.exec(value)) {
                                    localStorage.setItem('response_data', value);
                                }
                            })
                        }  
                    };
                    return xhr;
                },
            });
        ");
        $historyLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/account/order-history")]'), 3);

        if ($historyLink) {
            $historyLink->click();
        }
        //$this->http->GetURL("https://www.bjsrestaurants.com/account/order-history");

        sleep(4);
        $this->saveResponse();
        $sensor_data = $this->driver->executeScript("return localStorage.getItem('response_data');");
        $this->logger->debug('Response data' . $sensor_data);

        if (!empty($sensor_data)) {
            $this->http->SetBody($sensor_data);
        }

        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s',
                $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        /*$data = '{"query":"\n  query GetOrderHistory($input: GetOrderHistoryInput!) {\n    getOrderHistory(input: $input) {\n      Data {\n        id\n        total\n        tax\n        paymentTotal\n        paymentMethod\n        paymentMessage\n        type\n        pointsEarned\n        dateCreated\n        dateOrder\n        isPending\n        orderStatus\n        orderCancelAvailable\n        itemList {\n          id\n          name\n          imageURL\n          altText\n          price\n          prodId\n          attributes {\n            id\n            name\n            type\n            subAttributes {\n              id\n              name\n              change\n              plu\n            }\n          }\n          preSelectedAttributes {\n            name\n          }\n          addedAttributes {\n            name\n          }\n          removedAttributes {\n            name\n          }\n        }\n        site {\n          name\n        }\n      }\n      Error {\n        ErrorCode\n        ErrorDesc\n      }\n      Status\n    }\n  }\n","variables":{"input":{}},"operationName":"GetOrderHistory"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.prod.bjsrestaurants.com/graphql', $data, $this->headers);
        $this->http->RetryCount = 2;*/

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $history = $this->http->JsonLog();

        if (empty($history->data->getOrderHistory->Data)) {
            $this->logger->debug("History empty");

            return [];
        }
        $nodes = $history->data->getOrderHistory->Data;
        $this->logger->debug("Total " . count($nodes) . " transactions were found");

        foreach ($nodes as $node) {
            $dateStr = $node->dateOrder;
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $node->site->name;
            $result[$startIndex]['Transaction type'] = beautifulName($node->type);
            $result[$startIndex]['Earned points'] = $node->pointsEarned;
            //$result[$startIndex]['Total'] = $node->paymentTotal;
            //$result[$startIndex]['Currency'] = 'USD';

            $startIndex++;
        }

        return $result;
    }

    protected function parseReCaptchaV3($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $parameters = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.3,
            "pageAction"   => "",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $parameters);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => self::REWARDS_PAGE_URL,
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "LOGIN_USER_SEARCH",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseReCaptcha($key, $currentUrl)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        /*$postData = [
            "type"         => "RecaptchaV2EnterpriseTaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "isEnterprise" => true,
            "minScore"     => $this->attempt == 0 ? 0.3 : ($this->attempt == 1 ? 0.7 : 0.9),
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);*/

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"      => $currentUrl,
            "proxy"        => $this->http->GetProxy(),
            //"enterprise"   => 1,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
