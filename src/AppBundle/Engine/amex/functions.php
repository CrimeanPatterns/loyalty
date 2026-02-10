<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerAmex extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public const QUESTION_TSC = 'Please enter a temporary security code which was sent to you. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';
    public const QUESTION_ID_CODE = 'Please enter your four-digit identification code (optional memorable date)';
    public const QUESTION_DATE_OF_BIRTH = "Basic Cardmember's Date of Birth (Enter date in DD/MM/YYYY format)"; /*review*/
    public const QUESTION_YEAR_OF_BIRTH = "Basic Cardmember's year of birth (Enter year in YYYY format)";
    public const MESSAGE_NOT_FOUND_BALANCE = "We can't find balance on your card(s). Please contact us, if you know how to find it.";
    public const BRAZIL_SUCCESSFUL = '//select[@id = "cartaoSelecionado"]';

    private $seleniumURL = null;
    private $detectedCards = [];

    // refs #21294
    private $mapReferences = [];

    // refs #9131
    private $northAfrica = [
        "Bahrain",
        "Egypt",
        "Lebanon",
        "Jordan",
        "Kuwait",
        "Oman",
        //        "Qatar",
        "UAE",
        "United Arab Emirates", // refs #24160
    ];
    private $parseNonUS = false;
    private $proxy = false;
    private $proxyFamily = "direct";

    private $lang = null;

    private $headersSouthAfrica = [
        "Accept"        => "application/json",
        "Content-Type"  => "application/json",
        "Origin"        => "https://secured.nedbank.co.za",
    ];

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        if (isset($properties['SubAccountCode'], $properties['SubAccBalance']) && strstr($properties['SubAccountCode'], 'amex')) {
            return $properties['SubAccBalance'];
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        if ($this->attempt > 0) {
            $this->useLastHostAsProxy = false;
        }
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if (in_array($this->AccountFields['Login2'], ['Saudi Arabia'])) {
            $this->UseSelenium();
            $this->useFirefoxPlaywright();
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
            $this->setKeepProfile(true);
            $this->http->saveScreenshots = true;
        }

        if (in_array($this->AccountFields['Login2'], $this->northAfrica)) {
            $this->UseSelenium();
            $this->useFirefox();
            $this->setKeepProfile(true);
            $this->http->saveScreenshots = true;
        }
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $result = Cache::getInstance()->get('amex_countries');

        if (($result !== false) && (count($result) > 0)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select your country/location",
            ];
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL("https://www.americanexpress.com/change-country/");
            $nodes = $browser->XPath->query('//ul[contains(@class, "countryList")]/li/a[not(contains(@class, "bubble"))]');

            if ($nodes->length > 0) {
                for ($n = 0; $n < $nodes->length; $n++) {
                    $s = Html::cleanXMLValue($nodes->item($n)->nodeValue);

                    if ($s != "") {
                        $arFields['Login2']['Options'][$s] = $s;
                    }
                }
            } else {
                $state = $browser->FindPreg("/window.__INITIAL_STATE__ = \"([^<]+)\";/");
                $state = $browser->JsonLog(stripcslashes($state));
                $countries = $state[1][17][1][3][1][1][1][3][1][1][1][29][1][7][1] ?? [];
//                $this->logger->debug(var_export($countries, true), ['pre' => true]);
                foreach ($countries as $country) {
                    $s = Html::cleanXMLValue($country[1][1]);

                    if ($s == "") {
                        continue;
                    }

                    if ($s == 'Schweiz') {
                        $s = 'Switzerland';
                    }
                    $s = str_replace('u0026', '&', $s);
                    $arFields['Login2']['Options'][$s] = $s;

                    if ($s == 'Greater China Region' && isset($country[1][7][1])) {
                        foreach ($country[1][7][1] as $regionKey => $region) {
                            $s = Html::cleanXMLValue($region[1][1]);

                            if ($s == "") {
                                continue;
                            }
                            $s = str_replace('u0026', '&', $s);
                            $arFields['Login2']['Options'][$s] = $s;
                        }
                    }// if ($s == 'Greater China Region' && isset($country[8]))
                }// foreach ($countries as $country)
            }

            if (count($arFields['Login2']['Options']) > 1) {
                // refs #6190
                $arFields["Login2"]["Options"]['Japan'] = 'Japan';
                $arFields["Login2"]["Options"]['Taiwan'] = 'Taiwan';
                Cache::getInstance()->set('amex_countries', $arFields['Login2']['Options'], 3600);
            }// if (count($arFields['Login2']['Options']) > 1)
            else {
                // ignored for at least 2 weeks, commenting out as meaningless
                // $this->sendNotification("Regions aren't found - {$browser->currentUrl()}", 'all', true, $browser->Response['body']);
                $arFields['Login2']['Options'] = array_merge($arFields['Login2']['Options'], TAccountCheckerAmex::amexRegions());
            }
        }
        $arFields["Login2"]["Value"] = (isset($values['Login2']) && $values['Login2']) ? $values['Login2'] : "United States";

        //		$arFields["Login2"]["InputAttributes"] = "onchange=\"this.form.DisableFormScriptChecks.value = '1'; this.form.submit();\"";
        //		if (isset($values['Login2']) && $values['Login2'] == 'Saudi Arabia')
        ArrayInsert($arFields, "Pass", true, ["Login3" => [
            "Type"     => "string",
            "Required" => false,
            "Caption"  => "Last 4 digits on your card",
        ]]);
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'South Africa') {
            return false;
        }
        // refs #11593
        if ($this->AccountFields['Login2'] == 'Saudi Arabia') {
            return false;
        }
        // refs #13456
        if ($this->AccountFields['Login2'] == 'ישראל') {
            return false;
        }

        if ($this->AccountFields['Login2'] == 'Schweiz') {
            $this->AccountFields['Login2'] = 'Switzerland';
        }

        if ($this->AccountFields['Login2'] == 'Switzerland') {
            return false;
        }
        // refs #9131
        if (in_array($this->AccountFields['Login2'], $this->northAfrica)) {
            return false;
        }

        // For Your Account Security, Please Confirm Your Identity, workaround
        if ($this->proxy === true && $this->attempt > 1) {
            $this->http->SetProxy($this->proxyStaticIpDOP(), false);
        }// if ($this->proxy === true && $this->attempt > 0)
        else {
            unset($this->State['proxy']);
        }

        $this->http->RetryCount = 0;
        $this->http->setHttp2(true);
        $this->http->GetURL("https://global.americanexpress.com/dashboard", [], 20);
        $this->http->RetryCount = 2;

        if (!strstr($this->http->currentUrl(), '/account/login?') && $this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
//        $this->checkLoginOptions();
//        return false;//TODO

        unset($this->State['assessmentToken']);
        unset($this->State['encryptedValue']);
        unset($this->State['accountToken']);
        unset($this->State['authenticationActionId']);

        $this->http->removeCookies();
        $this->http->setMaxRedirects(15);
        $this->http->FilterHTML = true;

        if ($this->AccountFields['Login2'] == 'South Africa') {
            return $this->LoadLoginFormSouthAfrica();
        }
        // refs #11593
        if ($this->AccountFields['Login2'] == 'Saudi Arabia') {
            return $this->LoadLoginFormSaudiArabia();
        }
        // refs #13456
        if ($this->AccountFields['Login2'] == 'ישראל') {
            return $this->LoadLoginFormIsrael();
        }

        if ($this->AccountFields['Login2'] == 'Schweiz') {
            $this->AccountFields['Login2'] = 'Switzerland';
        }

        if ($this->AccountFields['Login2'] == 'Switzerland') {
            return $this->LoadLoginFormSwitzerland();
        }
        // refs #9131
        if (in_array($this->AccountFields['Login2'], $this->northAfrica)) {
            return $this->LoadLoginFormNorthAfrica();
        }

        $new2fa_v3 = [
            'veresch80',
            "anniehanjra786",
            "jredman100",
            "edmondchou",
            "ryanslloyd88",
            "carpediemwt123",
            "minyoungpark511",
        ];

        if ($this->attempt == 0 && !in_array($this->AccountFields['Login'], $new2fa_v3) ) {
            switch (random_int(1, 2)) {
                case 1:
                    $this->http->SetProxy($this->proxyReCaptcha());
                    $this->proxyFamily = "recapctha";

                    break;

                case 2:
                    $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_NORTH_AMERICA));
                    $this->proxyFamily = "do";

                    break;
            }
        }
        /*
        $this->http->setRandomUserAgent(10, true, false, true, false);
        */
        $this->http->setRandomUserAgent(10);

        $this->http->GetURL("https://global.americanexpress.com/login?DestPage=%2Fdashboard%3Fomnlogin%3Dus_homepage_myca");

        if (!in_array($this->AccountFields['Login'], $new2fa_v3)) {
            return $this->selenium();
        }

        $data = [
            "request_type"                  => "login",
            "Face"                          => "en_US",
            "UserID"                        => $this->AccountFields['Login'],
            "Password"                      => $this->AccountFields['Pass'],
            "REMEMBERME"                    => "on",
            "Logon"                         => "Logon",
            "channel"                       => "Web",
            "version"                       => "4",
            "DestPage"                      => "https://global.americanexpress.com/dashboard",
            "inauth_profile_transaction_id" => 'LOGIN-' . $this->getUuid(),
        ];
        // we add every header needed on website
        // to try to exactly match what's done, or we could get a LGON011 error
        // source: https://github.com/laurentb/weboob/blob/master/modules/americanexpress/browser.py#LL96C16-L96C16
        $headers = [
            "Accept"          => "*/*",
            'Accept-Encoding' => 'gzip, deflate, br, zstd',
            'Accept-Language' => 'en-US,en;q=0.5',
            'Content-Type'    => 'application/x-www-form-urlencoded; charset=utf-8',
            "challengeable"   => "ON",
        ];
        $this->http->PostURL("https://global.americanexpress.com/myca/logon/us/action/login", $data, $headers);

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;

            $selenium->useGoogleChrome();

            try {
                $wrappedProxy = $this->services->get(WrappedProxyClient::class);
                $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
                $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
                $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            } catch (Exception $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            $request = AwardWallet\Common\Selenium\FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.americanexpress.com/en-us/account/login?inav=iNavLnkLog");
            // login
            $loginInput = $selenium->waitForElement(WebDriverBy::id('eliloUserID'), 10);
            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('eliloPassword'), 0);
            // Sign In
            $button = $selenium->waitForElement(WebDriverBy::id('loginSubmit'), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->savePageToLogs($selenium);
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $button->click();

            // wait for page loaded
            if (in_array($this->AccountFields['Login2'], ['Brazil', 'Brasil'])) {
                $result = $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We are unable to access your Account.")] | //a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL . '| //input[@name = "answer"]'), 3);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$result && !$this->http->FindPreg("/<body onload=\"document\.forms\[0\]\.submit\(\)\">/")) {
                    $result = $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We are unable to access your Account.")] | //a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL . '| //input[@name = "answer"]'), 1);
                    $this->savePageToLogs($selenium);
                }

                if (!$result && !$this->http->FindPreg("/<body onload=\"document\.forms\[0\]\.submit\(\)\">/")) {
                    $result = $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We are unable to access your Account.")] | //a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL . '| //input[@name = "answer"]'), 1);
                    $this->savePageToLogs($selenium);
                }

                if ($this->http->FindPreg("/<body onload=\"document\.forms\[0\]\.submit\(\)\">/")) {
                    $this->logger->notice("force SAML form");
                    $selenium->driver->executeScript('document.forms[0].submit();');
                }
            }// if ($this->AccountFields['Login2'] == 'Brazil')
            $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We are unable to access your Account.")] | //a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL . '| //input[@name = "answer"]'), 10);
            $logout = $selenium->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "We are unable to access your Account.")] | //a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL), 0);
            $this->savePageToLogs($selenium);

            // todo: debug
            if ($this->http->FindSingleNode('//h1[contains(text(), "We are unable to access your Account.")]')) {
                $this->http->removeCookies();
                $selenium->http->removeCookies();
                $selenium->http->GetURL("https://www.americanexpress.com");

                // login
                $loginInput = $selenium->waitForElement(WebDriverBy::id('login-user'), 10);
                // password
                $passwordInput = $selenium->waitForElement(WebDriverBy::id('login-password'), 0);
                // Sign In
                $button = $selenium->waitForElement(WebDriverBy::id('login-submit'), 0);

                if (!$loginInput || !$passwordInput || !$button) {
                    $this->savePageToLogs($selenium);

                    return false;
                }

                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $button->click();

                $logout = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL), 10);
                $this->savePageToLogs($selenium);
            }

            //div[@data-testid="one-identity-login"]
            if (!$logout) {
                $selenium->waitFor(function () use ($selenium) {
                    $this->logger->warning("Solving is in process...");
                    sleep(3);
                    $this->savePageToLogs($selenium);

                    return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                }, 180);

                $logout = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL), 10);
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");

            if ($logout) {
                $this->http->GetURL($this->seleniumURL);
            }

            $result = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        if (
            $this->seleniumURL == "https://global.americanexpress.com/dashboard/error"
        ) {
            throw new CheckException("Sorry, we are unable to display this account right now. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $result;
    }

    public function LoadLoginFormSouthAfrica()
    {
        $this->logger->notice(__METHOD__);
        // get csrf
        $this->http->GetURL("https://id.nedbank.co.za/libs/granite/csrf/token.json");

        $nedbankidjwtvalue = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJhdWQiOiIxOGYyMjA4OS05OWY2LTQ4MmUtOTMzMC02N2IwM2RlOTNjZjEiLCJ0b2tlbl90eXBlIjoiQmVhcmVyIiwibmJmIjoxNzA0MTk5MTMxLCJpc3MiOiJpZHAubmVkYmFuay5jby56YSIsImlhdCI6MTcwNDE5OTE5MSwiZXhwIjoxNzM1NzM1MTkxLCJncmFudF90eXBlIjoiYW5vbnltb3VzIiwiY2lkIjoiMyIsImp0aSI6ImQzZjdlOTE2MDE1ODQ3NTE4ZGZlMDBhZTQ2ZDU2YmI1Iiwic2NvcGVzIjpbXX0.oWiDggiHQKr-tM6XiweAa687Rl3N-xjCmrdqfCLJsrB-_xbiA_LtGZ2aky5isQWMQlmNBpos_PCImt0q292ztKzlC1rW2rakve30INq7O2zQGpVSRGmM5qIUPlWCNAek6FKmxQThjvYBkDav6k74rjdpz82bCQQZg1IeDUCXKC8naJ-hbTbx8D5H8wBmwvRxKr43y_K30OO0owlCjKDaJVj_fWwV1z_UgJ6o6H1NmslWP5vAxwCXjbLasvK0GQUPxxMnR1w5iC3kbTHGjrVVKW2w4_9DOse-cX6o1w5zCRxLfBpH-zmK4feo2NMPLb9VzDJjxyqRNssyLZoVJoWqhA'; // https://secured.nedbank.co.za/main.js

        if (!$nedbankidjwtvalue) {
            $this->logger->error("token not found");

            return false;
        }
        $http2 = clone $this;

        $http2->http->GetURL("https://api.nedsecure.co.za/nedbank/informationsecuritymanagement/nedbankidtokens/v1/users/sessiontokens", [
            "Authorization"    => "Bearer {$nedbankidjwtvalue}",
            "Accept"           => "application/json",
            "Content-Type"     => "application/json",
        ]);

//        $http2->http->GetURL("https://secured-id.nedbank.co.za/mga/sps/apiauthsvc?PolicyId=urn:ibm:security:authentication:asf:retrieve_unauth_jwt", [
        ////            "Authorization"    => "Bearer {$nedbankidjwtvalue}",
//            "Accept"           => "application/json",
//            "Content-Type"           => "application/json",
//        ]);
        $response = $http2->http->JsonLog();

        if (!isset($response->data->tokenValue)) {
            $this->logger->error("access_token token not found");

            return false;
        }
        $this->http->setDefaultHeader("Authorization", "Bearer {$response->data->tokenValue}");
        // open login form)
        $this->http->GetURL("https://secured.nedbank.co.za/#/login");
//        $nedbankidjwtvalue = $this->http->FindSingleNode('//div[@data-nedbankidjwtvalue]/@data-nedbankidjwtvalue');
//        if (!$nedbankidjwtvalue) {
//            return false;
//        }
        if ($this->http->Response['code'] != 200) {
//        if (!$this->http->ParseForm(null, "//form[contains(@action, '/NedbankIDEAI/EAIServlet')]")) {
            return false;
        }
//        $this->http->SetInputValue('username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('SP', "8");

        $data = [
            "username"   => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "appliesTo"  => "https://newmoneyweb/",
            "secretType" => "PWD",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://secured-id.nedbank.co.za/mga/sps/apiauthsvc?PolicyId=urn:ibm:security:authentication:asf:nidlogin", json_encode($data), $this->headersSouthAfrica);
        $this->http->RetryCount = 2;

        return true;
    }

    public function LoadLoginFormSaudiArabia()
    {
        $this->logger->notice(__METHOD__);

        $this->Answers = [];

        // Please enter the last 4 digits of your card or account number.
        if (strlen($this->AccountFields['Login3']) != 4 || !is_numeric($this->AccountFields['Login3'])) {
            throw new CheckException("Please enter the last 4 digits of your card or account number.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://online.americanexpress.com.sa/");
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter your User ID"]'), 7);
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter your Password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Login")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passInput || !$button) {
            // maintenance
            if ($message = $this->http->FindSingleNode('//h1[contains(., "Site is currently not available.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    public function LoadLoginFormIsrael()
    {
        $this->logger->notice(__METHOD__);
        // Please enter the last 6 digits of your card or account number.
        if (strlen($this->AccountFields['Login3']) != 6 || !is_numeric($this->AccountFields['Login3'])) {
            throw new CheckException("Please enter the last 6 digits of your card.", ACCOUNT_INVALID_PASSWORD);
        }
        // יש להזין ת.ז. תקינה בעלת 9 ספרות
        if (strlen($this->AccountFields['Login']) < 9 || !is_numeric($this->AccountFields['Login'])) {
            throw new CheckException("Please enter a 9-digit Social Security Number", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://he.americanexpress.co.il/personalarea/login/#/logonPage");
        $token = $this->http->FindSingleNode("//input[@name = '__RequestVerificationToken']/@value");

        if (!$this->http->ParseForm("otpLobbyFormPassword") || !$token) {
            return false;
        }
        $data = [
            "checkLevel"        => "1",
            "idType"            => "1",
            "cardSuffix"        => $this->AccountFields['Login3'],
            "id"                => $this->AccountFields['Login'],
            "companyCode"       => "77",
            "countryCode"       => "212",
            "applicationSource" => "0",
        ];
        $this->http->setDefaultHeader("__RequestVerificationToken", $token);
        $this->http->PostURL("https://he.americanexpress.co.il/services/ProxyRequestHandler.ashx?reqName=ValidateIdData", json_encode($data));
//        $this->http->SetInputValue('otpLoginID', $this->AccountFields['Login']);
//        $this->http->SetInputValue('otpLoginPwd', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('otpLoginLastDigits', $this->AccountFields['Login3']);

        return true;
    }

    public function LoadLoginFormSwitzerland()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://rewardshop.americanexpress.ch/home");

        if (!$this->http->ParseForm("loginFormRex")) {
            if ($this->http->Response['code'] == 404) {
                $this->http->GetURL("https://rewardshop.americanexpress.ch/");

                if ($message = $this->http->FindSingleNode("//h4[
                        contains(text(), 'The Membership Reward Shop is currently unavailable while we are migrating the service to a new hosting platform.')
                        or contains(text(), 'The Membership Reward Shop is currently unavailable while we are upgrading the platform.')
                    ]")
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }

            return false;
        }
        $this->http->SetInputValue('currentPage', $this->http->currentUrl());
        $this->http->SetInputValue('memberLoggedIn', "false");
        $this->http->SetInputValue('rpd', "rewardshop.americanexpress.ch");
        $this->http->PostForm();

        if ($this->http->ParseForm(null, "//form[contains(@action, 'metaAlias')]")) {
            $this->http->SetInputValue('lang', 'en');
            $this->http->PostForm();
        }
        $csrf = $this->http->getCookieByName("42-S", null, "/", true);

        if (!$csrf) {
            $this->logger->error("csrf not found");
            /**
             * Unerwarteter Fehler
             * Wir bitten Sie um Entschuldigung, ein unerwarteter Fehler ist aufgetreten.
             */
            if ($message = $this->http->FindSingleNode('//span[contains(text(), "Wir bitten Sie um Entschuldigung, ein unerwarteter Fehler ist aufgetreten.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

//            return false;// todo: provider workflow now
        }
        /*
        if (!$this->http->ParseForm("LOGINFORM")) {
            return false;
        }
        $this->http->SetInputValue('USERNAME', $this->AccountFields['Login']);
        $this->http->SetInputValue('PASSWORD', $this->AccountFields['Pass']);
        */
        $headers = [
            "Accept-Language" => "en",
            "Accept"          => "application/json",
            "Content-Type"    => "application/json",
            "X-42"            => $csrf,
            "X-Same-Domain"   => "1",
            "X-Continue-Flow" => "true",
            "User-Agent"      => HttpBrowser::PROXY_USER_AGENT,
        ];
        $this->http->RetryCount = 0;

        parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
        $returnUrl = $output['returnUrl'] ?? $this->http->currentUrl();

        if (!isset($output['returnUrl'])) {
            $this->logger->error("returnUrl not found");

//            return false;
        }

        $reqID = $this->http->FindPreg("/ReqID(?:\%3D|=)([^&]+)/", false, $returnUrl);

        if (!isset($reqID)) {
            return false;
        }

        $data = [
            "requestId" => $reqID,
        ];
        $this->http->PostURL("https://sso.swisscard.ch/saml-auth/rest/public/authentication/saml2/idp/sso/init", json_encode($data), $headers);
        $this->http->JsonLog();

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL("https://sso.swisscard.ch/saml-auth/rest/public/authentication/password/check", json_encode($data), $headers);
        $this->http->RetryCount = 2;

//        $this->logger->debug(var_export($this->http->Response['headers'], true), ['pre' => true]);
//        $saml = $this->http->FindPreg("/SamlSso\s*(.+)/", false, $this->http->Response['headers']['authorization'] ?? null);
        $saml = $this->http->Response['headers']['x-forward-url'] ?? null;

        if ($saml) {
//            $this->http->GetURL("https://entry.swisscard.ch/loyshop-saml-auth/check-login?SamlSso={$saml}&goto=https://entry.swisscard.ch/auth/SSOPOST/metaAlias/idp?ReqID={$reqID}");
            $this->http->GetURL("https://sso.swisscard.ch{$saml}");
        }

        return true;
    }

    public function LoadLoginFormNorthAfrica()
    {
        $this->logger->notice(__METHOD__);
        /*
        $this->http->SetProxy($this->proxyUK(), false);
        */
        $this->http->saveScreenshots = true;
        $this->http->GetURL("https://secure.americanexpress.com.bh/online/login");
        sleep(3);
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "loginUserId"]'), 7);
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "loginPassword"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "loginSubmitButton"]'), 0);
        $this->saveResponse();

        $this->waitFor(function () {
            return !is_null($this->waitForElement(WebDriverBy::xpath('//div[@class = "progress-circle"]'), 0));
        }, 10);

        if (!$loginInput || !$passInput || !$button) {
            // maintenance
            if ($message =
                    $this->http->FindPreg("/(Due to system maintenance, our Online Services will be unavailable on[^<]+)/")
                    ?? $this->http->FindSingleNode('//div[@id = "outage-notification" and contains(., "Please note that due to system maintenance, certain functionalities on Online Services will be unavailable.")]')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        /*
        $formURL = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_0onlsportletsLogin_Default1')]/@href");
        $linkEnterPassword = $this->http->FindSingleNode("//a[@name = 'linkEnterPassword']/@href");

        if (!$this->http->ParseForm("onlsLoginForm") || !isset($formURL, $linkEnterPassword)) {
            // maintenance
            if (
                $message =
                    $this->http->FindPreg("/(Due to system maintenance, our Online Services will be unavailable on[^<]+)/")
                    ?? $this->http->FindSingleNode('//div[@id = "outage-notification" and contains(., "Please note that due to system maintenance, certain functionalities on Online Services will be unavailable.")]')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }// if (!$this->http->ParseForm("onlsLoginForm") || !isset($formURL, $linkEnterPassword))
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('UserID', $this->AccountFields['Login']);
        $this->http->PostForm();
        // Invalid User ID. Try again.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid User ID')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Entering password
        $this->http->NormalizeURL($linkEnterPassword);
        $this->http->GetURL($linkEnterPassword);
        $formURL = $this->http->FindSingleNode("//a[contains(@id, 'wpf_action_ref_1onlsportletsLogin_PasswordEntryPage')]/@href");

        if (!$this->http->ParseForm("enterPasswordForm") || !isset($formURL)) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('btnLogin', 'Login');
        */

        return true;
    }

    public function Login()
    {
        if ($this->http->Response['code'] == 403) {
            $this->DebugInfo = "Access Denied";

            return false;
        }

        $response = $this->http->JsonLog();
        $statusCode = $response->statusCode ?? null;

        if (!empty($response->redirectUrl) && $statusCode == 0) {
            $this->http->GetURL($response->redirectUrl);
        } elseif (!empty($response->errorCode) && $statusCode) {
            $errorCode = $response->errorCode;
            // https://www.aexp-static.com/cdaas/axp-app/modules/axp-login/5.11.1/en-us/axp-login.json
            switch ($errorCode) {
                // Change Password
                case $errorCode == 'LGON015' && $statusCode == 1:
                    $this->throwProfileUpdateMessageException();

                    break;

                case $errorCode == 'LGON001' && $statusCode == 1:
                    throw new CheckException("The User ID or Password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
                // need to check it
                case $errorCode == 'LGON003' && $statusCode == 1:
                    throw new CheckException("The User ID or Password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);

                case $errorCode == 'LGON004' && $statusCode == 1:
                    throw new CheckException("Your account has been locked due to too many unsuccessful attempts. To log in, please reset your password or retrieve your User ID.", ACCOUNT_LOCKOUT);

                case $errorCode == 'LGON005' && $statusCode == 1:
                    throw new CheckException("For your protection, we have locked your American Express account due to more than three incorrect login attempts.", ACCOUNT_LOCKOUT);

                // We're sorry. Our system is temporarily unavailable.
                case $errorCode == 'LGON018' && $statusCode == 1:
                    $this->DebugInfo = "blocked by provider";
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    return false;

                case $errorCode == 'LGON010' && $statusCode == 1:
                    throw new CheckException("We're sorry. Our system is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);

                case $errorCode == 'LGON013' && $statusCode == 1:
                    $mfaId = $response->reauth->mfaId;

                    if (isset($response->reauth->assessmentToken)) {
                        $this->State['assessmentToken'] = $response->reauth->assessmentToken;
                    }

                    $this->http->GetURL("https://www.americanexpress.com/en-us/account/two-step-verification/verify?mfaId={$mfaId}");

                    break;

                case $errorCode == 'LGON009' && $statusCode == 1:
                    $mfaId = $response->reauth->mfaId;

                    if (isset($response->reauth->assessmentToken)) {
                        $this->State['assessmentToken'] = $response->reauth->assessmentToken;
                    }

                    $this->http->GetURL("https://www.americanexpress.com/en-us/account/reauth/verify?mfaId={$mfaId}");

                    break;

                default:
                    $this->logger->notice("[Status code]: {$statusCode}");
                    $this->logger->notice("[Error code]: {$errorCode}");
            }
        }// elseif (!empty($response->errorCode) && $statusCode)

        if ($this->AccountFields['Login2'] == 'South Africa') {
            return $this->LoginSouthAfrica();
        }
        // refs #9131
        if (in_array($this->AccountFields['Login2'], $this->northAfrica)) {
            return $this->LoginNorthAfrica();
        }
        // refs #11593
        if ($this->AccountFields['Login2'] == 'Saudi Arabia') {
            return $this->LoginSaudiArabia();
        }
        // refs #13456
        if ($this->AccountFields['Login2'] == 'ישראל') {
            return $this->LoginIsrael();
        }

        if ($this->AccountFields['Login2'] == 'Switzerland') {
            return $this->LoginSwitzerland();
        }

        $success = false;

        try {
            if ($this->attempt == 2) {
                $this->selenium();

                if ($this->http->FindSingleNode(self::BRAZIL_SUCCESSFUL)) {
                    $this->parseNonUS = true;
                }
            } else {
                $this->logger->notice("checking bad login");
                // system timeout
                if ($this->http->FindPreg("/<b>Our System is (Not Responding)<\/b>/ims")) {
                    throw new CheckException("Our System is Not Responding. Try your request again later today.", ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    !strstr($this->http->FormURL, 'content/search/')
                    && !strstr($this->http->currentUrl(), 'https://global.americanexpress.com/dashboard')
                    && !$this->http->PostForm()
                ) {
                    // retries
                    if ($this->http->currentUrl() == "https://online.americanexpress.com/myca/logon/us/action?request_type=LogLogonHandler&location=us_pre1_cards" && $this->http->Response['code'] == 0) {
                        throw new CheckRetryNeededException(3, 15);
                    }
                }
            }// if (!$this->http->PostForm())

            // meta redirects
            $this->http->Response['body'] = $this->http->removeTag($this->http->Response['body'], "noscript");
            $url = $this->http->FindPreg("/<meta http\-equiv=\"Refresh\" content=\"[12]?\d;\s*url=([^\"]+)\"/ims");

            if (isset($url)) {
                $this->logger->notice("processing meta-redirect");
                $this->http->GetURL(str_replace(" ", "%20", $url));
            }
            // Maintenance
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'As we update systems, online/phone services will be down')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Error 404: javax.servlet.UnavailableException: CWSRV0200E: Servlet [com.americanexpress.util.frontservlet.FrontServlet]
            if (($this->http->FindPreg("/Error 404: javax\.servlet\.UnavailableException: CWSRV0200E: Servlet \[com\.americanexpress.util\.frontservlet\.FrontServlet\]/")
                    /*
                     * Not Found
                     *
                     * The requested URL /myca/usermgt/ipcfwd/action was not found on this server.
                     *
                     * IBM_HTTP_Server at global.americanexpress.com Port 443
                     */
                    || $this->http->FindSingleNode("//p[contains(text(), 'The requested URL /myca/usermgt/ipcfwd/action was not found on this server')]"))
                && $this->http->Response['code'] == 404) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // provider error, debug
            if ($this->http->FindPreg("/IBM_HTTP_Server at global.americanexpress.com Port 443/") && $this->http->Response['code'] == 404
                && strstr($this->http->currentUrl(), 'Face=pt_BR&sorted_index=')) {
                throw new CheckRetryNeededException(3, 10);
            }

            if (
                $this->http->Response['code'] == 403
                && $this->http->FindPreg("/Error 403: Request Forbidden. Transaction ID:/")
            ) {
                throw new CheckRetryNeededException(2, 0);
            }

            // provider error (AccountID: 2833961)
            if ($this->http->currentUrl() == 'https://aedc.extra.aexp.com/sf/docman/do/listDocuments/projects.myca-moneymovement/docman.root.global_payments.web_applications.ukcreditcenter.applicationurls' && strstr($this->http->Response['errorMessage'],
                    'Could not resolve host: aedc.extra.aexp.com')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // for Brazil
            if ($this->http->ParseForm("login")) {
                $this->logger->notice(">>> Brazil. Step 1");
                sleep(1);
                $this->http->PostForm();

                if ($this->http->ParseForm(null, 1)) {
                    if ($this->http->FindSingleNode("//div[contains(text(), 'Select Your Security Validation Question and Answer')]")) {
                        throw new CheckException("Amex (Membership Rewards) website is asking you to select your security validation question and answer, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
                    }/*checked*/

                    $this->logger->notice(">>> Brazil. Step 2");
                    sleep(1);
                    $this->http->PostForm();

                    if ($this->http->ParseForm(null, 1)) {
                        $this->logger->notice(">>> Brazil. Step 3");
                        sleep(1);

                        if (!$this->http->PostForm() || $this->http->FindSingleNode("//h2[contains(text(), 'SAML 2.0 authentication failed')]")) {
                            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                        }
                        $this->parseNonUS = true;
                    }
                }// if ($this->http->ParseForm(null, 1))
            }// if ($this->http->ParseForm("login"))
            // refs #18232
            elseif ($this->attempt == 1 && $this->http->FindSingleNode("//title[contains(text(), 'SAML 2.0 Auto-POST form')]")) {
                if ($this->http->ParseForm(null, 1)) {
                    $this->logger->notice(">>> Brazil. Step 3");
                    sleep(1);

                    if (!$this->http->PostForm() || $this->http->FindSingleNode("//h2[contains(text(), 'SAML 2.0 authentication failed')]")) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                    $this->parseNonUS = true;
                }
            }// elseif ($this->attempt == 1 && $this->http->FindSingleNode("//title[contains(text(), 'SAML 2.0 Auto-POST form')]"))

            $this->logger->notice("checking bad login");
            // system timeout
            if ($message = $this->http->FindPreg("/<b>Our System is (Not Responding)<\/b>/ims")) {
                throw new CheckException("Our System is Not Responding. Try your request again later today.", ACCOUNT_PROVIDER_ERROR);
            }
            // We're sorry, the usual website service is unavailable due to scheduled maintenance.
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, the usual website service is unavailable due to scheduled maintenance.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We're sorry, our website is currently undergoing scheduled maintenance.
            if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, our website is currently undergoing scheduled maintenance.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Momenteel vindt er een gepland onderhoud plaatst op onze website, onze excuses voor het ongemak.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindPreg('/(?:wir aktuell Wartungsarbeiten auf dieser Webseite durch|no se encuentra disponible en estos momentos debido a que estamos realizando labores de mantenimiento en la web|Notre site est actuellement en maintenance)/')) {
                throw new CheckException("We're sorry, the usual website service is unavailable due to scheduled maintenance.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Your Card Account is locked. Please call the number on the back of your Card.")]')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            /*
            $this->CheckError($this->http->FindPreg("/Unfortunately\, we are unable to log you in at this time\./ims"), ACCOUNT_PROVIDER_ERROR);
            */
            // check bad login
            if ($this->http->FindSingleNode("//input[@id = 'errMsgValueInPage']/@value") == 'true') {
                $error = $this->http->FindSingleNode("//input[@id = 'errMsgValue']/@value", null, false);
            }

            if (!isset($error)) {
                $error = $this->http->FindPreg("/<div class =\"floatLeft Error errorStyle\">([^<]+)</ims");
            }

            if (!isset($error)) {
                $error = $this->http->FindSingleNode("//div[@id = 'errMsg']", null, false);
            }

            if (isset($error)) {
                if ($error == 'You\'ve left a field blank.') {
                    if (
                        // AccountID: 3392568
                        substr_count($this->AccountFields['Pass'], '❶') >= 1
                        || filter_var($this->AccountFields['Pass'], FILTER_VALIDATE_URL)// AccountID: 5342346
                        || in_array($this->AccountFields['Login'], [
                            'kamsler@directgovernmentsales.com', // AccountID: 4624601
                            'thekmumm', // AccountID: 3549555
                        ])
                    ) {
                        throw new CheckException("Your User ID or Password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
                    }

                    return false;
                }

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            // check lockout
            if ($message = $this->http->FindPreg("/Your\s+User\s+ID\s+is\s+(locked)/ims")) {
                throw new CheckException("Your User ID is locked. Please <a href=\"https://www99.americanexpress.com/myca/usermgt/us/action?request_type=NewPassword&Face=en_US&AccountRevoked=1\" target=\"_blank\">retrieve your User ID</a> and reset your Password.", ACCOUNT_LOCKOUT);
            }
            // check card registration
            if ($message = $this->http->FindPreg("/Enter your Card information to begin the registration process/ims")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * You have been unsuccessful logging in to your account.
             * Please enter your Card information to retrieve your User ID and reset your password.
             */
            if ($this->http->FindSingleNode("//*[contains(text(), 'You have been unsuccessful logging in to your account. Please enter your Card information to retrieve your User ID and reset your password.')]")
                || $this->http->currentUrl() == 'https://www.americanexpress.com/en-us/account/password/recover?DestPage=https%3A%2F%2Fglobal.americanexpress.com%2Fdashboard%3Fomnlogin%3Dus_homepage_myca'
            ) {
                throw new CheckException("Amex (Membership Rewards) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // Change Password
            if ($this->http->ParseForm("wgtpasswordForm") && $this->http->FindSingleNode("//h1[contains(text(), 'Change Password')]")) {
                $this->throwProfileUpdateMessageException();
            }
            //# Set up your Online Account to customize your membership
            if ((strstr($this->http->currentUrl(), 'authunreg_register')
                    && $this->http->FindPreg("/(Set up your Online Account)/ims"))
                /* Select Cards, enter payment information and click 'Continue' */
                || (strstr($this->http->currentUrl(), 'authreg_PayBill')
                    && $this->http->FindSingleNode("(//h1[@class = 'PayBillHeader1']/img/@alt)[1]") == 'PAYBILL')
                // Already have a Card? Add it to your Online Account
                || $this->http->currentUrl() == 'https://www.americanexpress.com/us/content/no-card/'
                || $this->http->currentUrl() == 'https://www.americanexpress.com/us/no-card/'
                || $this->http->currentUrl() == 'https://content.americanexpress.com/us/content/no-card/'
                || $this->seleniumURL == 'https://www.americanexpress.com/us/content/no-card/'
                || $this->seleniumURL == 'https://www.americanexpress.com/en-us/change-country/'
                || $this->seleniumURL == 'https://www.americanexpress.com/us/no-card/'
                || $this->seleniumURL == 'https://content.americanexpress.com/us/content/no-card/'
                || $this->seleniumURL == 'https://www.americanexpress.com/en-us/change-country/'
            ) {
                throw new CheckException("Amex (Membership Rewards) website is asking you to enter your card information, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } /*checked*/

            // no auth, no error (528416, 4654424, 3818559, 4700987, 903272, 2610192 etc.)
            if (
                $this->http->currentUrl() == 'https://www.americanexpress.com/en-us/account/login?DestPage=https%3A%2F%2Fglobal.americanexpress.com%2Fdashboard%3Fomnlogin%3Dus_homepage_myca'
                // AccountID: 4262993, Our system is not responding at this time. Please try again by clicking the back or refresh button.
                || $this->http->currentUrl() == 'https://online.americanexpress.com/myca/tasdsgn/us/action?request_type=authreg_tasDelegateCRRequest&Face=en_US_sbs&spaRedirect=false'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                in_array($this->AccountFields['Login'], [
                    'jtymeck', // AccountID: 481114
                    'daviesallen21', // AccountID: 2484470
                    'jweiss9401', // AccountID: 3890019
                    'jlocke0827', // AccountID: 2188816
                ])
                && (
                    $this->http->currentUrl() == 'https://www.americanexpress.com/account/login?DestPage=https%3A%2F%2Fonline.americanexpress.com%2Fmyca%2Freauth%2Fus%2FreauthOptionsController.do%3Frequest_type%3Dauthreg_reauthHandler%26Face%3Den_US%26sorted_index%3D0%26Details%3Dtrue%26DestPage%3Dhttps%253A%252F%252Fglobal.americanexpress.com%252Fdashboard%253Fomnlogin%253Dus_homepage_myca&Face=en_US'
                    || $this->http->FindPreg("#Found. Redirecting to <a href=\"https://www.americanexpress.com/en-us/account/travel/login\?DestPage=https://www.americanexpress.com/en-us/travel/my-bookings/summary\">#")
                )
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                in_array($this->AccountFields['Login'], [
                    'prgphoto2018', // AccountID: 5006572
                ])
                && (
                    $this->http->currentUrl() == 'https://account.kabbage.com/api/bounce/login-with-amex'
                    || strpos($this->http->currentUrl(), 'https://www.americanexpress.com/account/oauth/connect?client_id=') === 0
                )
            ) {
                throw new CheckException("You are not enrolled into Membership rewards", ACCOUNT_PROVIDER_ERROR);
            }

            /*
             * We need to speak with you about the information you entered.
             * Please call American Express Customer Service at 1-800-AXP-1234
             * from 8:00 a.m. to midnight ET, 7 days a week.
             */
            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'We need to speak with you about the information you entered')]")) {
                throw new CheckException(Html::cleanXMLValue($message . ' ' . $this->http->FindSingleNode("//b[contains(text(), 'Please call American Express Customer Service')]")), ACCOUNT_PROVIDER_ERROR);
            }
            // 500 Internal Server Error
            if ($this->http->FindSingleNode("//title[contains(text(), '500 Internal Server Error')]")
                && $this->http->Response['code'] == 500) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // We're unable to load this page at this time.
            if ($message = $this->http->FindSingleNode('//span[contains(text(), "We\'re unable to load this page at this time.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Remind me later
            if ($remindMeLater = $this->http->FindSingleNode('//a[contains(@title, "We\'ll remind you again later")]/@href')) {
                $this->logger->notice("Skip switching to online statements");
                $this->http->NormalizeURL($remindMeLater);
                $this->http->GetURL($remindMeLater);
            }// if ($remindMeLater = $this->http->FindSingleNode('//a[contains(@title, "We\'ll remind you again later")]/@href'))

            if (
                $this->http->currentUrl() == 'https://global.americanexpress.com/login?noRedirect=true&DestPage=%2Fdashboard%3Fomnlogin%3Dus_homepage_myca'
                || $this->seleniumURL == 'https://global.americanexpress.com/login?noRedirect=true&DestPage=%2Fdashboard%3Fomnlogin%3Dus_homepage_myca'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // invalid credentials, hard code
            if (
                substr_count($this->AccountFields['Pass'], '❹') >= 1
                || substr_count($this->AccountFields['Pass'], '❶') >= 1
                || substr_count($this->AccountFields['Pass'], '❷') >= 1
            ) {
                throw new CheckException("Your User ID or Password is incorrect. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            // check question
            if ($this->ParseQuestion()) {
                return false;
            }

            // redirect bug fix, it's works
            if ($this->seleniumURL == 'https://www.americanexpress.com/' && $this->http->getCookieByName("gatekeeper")) {
                $this->http->GetURL("https://global.americanexpress.com/dashboard");
            }

            if (stripos($this->http->currentUrl(), 'https://global.americanexpress.com/login') === 0) {
                $this->logger->warning("still at login page");

                return false;
            }

            $success = true;

            return true;
        } finally {
            StatLogger::getInstance()->info("amex login attempt", [
                "attempt"      => $this->attempt,
                "success"      => $success,
                "proxy"        => $this->proxyFamily,
                "userAgentStr" => $this->http->userAgent,
                "resolution"   => ($this->seleniumOptions->resolution[0] ?? 0) . "x" . ($this->seleniumOptions->resolution[1] ?? 0),
                "hasQuestion"  => isset($this->Question),
            ]);
        }
    }

    public function LoginSouthAfrica()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        $tokenValue =
            $response->data->tokenValue
            ?? $this->http->Response['headers']['jwt']
            ?? null
        ;

        if ($tokenValue) {
            foreach (explode('.', $tokenValue) as $str) {
                $str = base64_decode($str);
                $this->logger->debug($str);

                if ($sub = $this->http->FindPreg('/"sub":"(.+?)"/', false, $str)) {
                    break;
                }
            }

            if (!isset($sub)) {
                $this->logger->error("userId not found");

                return false;
            }
            $data = [
                "profileIdentification" => [
                    "profileType"   => "",
                    "profileNumber" => "",
                ],
                "verificationInfo"      => [
                    "verificationMethod" => "",
                    "verificationID"     => 0,
                    "otp"                => 0,
                ],
            ];
            $this->http->setDefaultHeader("Authorization", "Bearer {$tokenValue}");
            $this->http->RetryCount = 0;
            $this->http->PutURL("https://api.nedsecure.co.za/nedbank/nedbankid/v4/users/profiles/contexts/retail", json_encode($data), $this->headersSouthAfrica);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->data->token)) {
                $this->logger->error("token not found");

                $message = $response->metaData->description ?? null;
                $code = $response->metaData->code ?? null;
                // For your security we need to confirm your banking info
                if ($code == "R152" && $message == "No Banking Credentials Federated") {
                    $this->throwProfileUpdateMessageException();
                }

                return false;
            }
            $this->http->setDefaultHeader("Authorization", "Bearer {$response->data->token}");

            return true;
        }

        $message = $response->metaData->frontendMessage ?? null;
        $code =
            $response->metaData->code
            ?? $response->metaData->resultCode
            ?? null
        ;
        // Incorrect details. Your Nedbank ID will be locked after 6 failed attempts.
        if (in_array($code, [
            "R05",
            "R06",
            "R12",
        ])
            && (
                $message == "Couldn’t log you in. Please check and try again or call 0860 555 111 for help."
                || $message == "You have entered the incorrect details, Your Nedbank ID will be locked."
            )
        ) {
            throw new CheckException("Incorrect details. Your Nedbank ID will be locked after 6 failed attempts.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function ParseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        /**
         * Unable to process your request.
         *
         * We're sorry. Our system is temporarily unavailable. Please try again later.
         */
        // AccountID: 2188816
        if ($this->http->Response['code'] == 404 && $this->http->FindPreg('/https:\/\/www\.americanexpress\.com\/([^\/]+)\/account\/two-step-verification\/verify\?mfaId=[a-z0-9]+$/', false, $this->http->currentUrl())) {
            throw new CheckException("Unable to process your request. We're sorry. Our system is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        // Security Verification: One-Time Verification Code
        // {"statusCode":1,"errorCode":"LGON013","errorMessage":"","debugInfo":"","redirectUrl":"https://global.americanexpress.com/dashboard","reauth":{"applicationId":"LOGON01","actionId":"MFAOI01","mfaId":"10a09fb"}}
        if (
            $this->http->FindPreg('/https:\/\/www\.americanexpress\.com\/([^\/]+)\/account\/two-step-verification\/verify/', false, $this->seleniumURL ?? $this->http->currentUrl())
            || $this->http->FindPreg('/https:\/\/www\.americanexpress\.com\/([^\/]+)\/account\/reauth\/verify\?mfaId=/', false, $this->seleniumURL ?? $this->http->currentUrl())
        ) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->State['authenticationActionId'] = "MFAOI01";

            if ($this->http->FindPreg('/https:\/\/www\.americanexpress\.com\/([^\/]+)\/account\/reauth\/verify\?mfaId=/', false, $this->seleniumURL ?? $this->http->currentUrl())) {
                $this->State['authenticationActionId'] = "REAUT01";
            }
            $this->State['mfaId'] = $this->http->FindPreg("/mfaId=([^\&]+)/", false, $this->seleniumURL ?? $this->http->currentUrl());
            $this->State['locale'] = $this->http->getCookieByName("axplocale", ".americanexpress.com", "/", true) ?? $this->http->FindPreg('/"locale.",."([^\"\\\]+)."/');

            $headers = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json; charset=UTF-8",
            ];
            $this->http->RetryCount = 0;
            $this->logger->notice("ReadAuthenticationChallenges.v1");
            $data = [
                [
                    "authenticationActionId" => $this->State['authenticationActionId'],
                    "applicationId"          => "LOGON01",
                    "locale"                 => $this->State['locale'],
                ],
            ];
            $this->http->PostURL("https://functions.americanexpress.com/ReadAuthenticationChallenges.v1", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            $hasEmail = false;

            if (isset($response->tenuredChannels)) {
                foreach ($response->tenuredChannels as $tenuredChannel) {
                    if ($tenuredChannel->deliveryMethod == 'EMAIL') {
                        $hasEmail = true;
                    }// if ($tenuredChannel->deliveryMethod == 'EMAIL')
                }// foreach ($response->tenuredChannels as $tenuredChannel)
            }// if (isset($response->tenuredChannels))

            if (
                (
                    isset($response->description)
                    && in_array($response->description, [
                        "Card is already locked out",
                        "Invalid Security Token: No response from Card Service",
                    ])
                    // AccountID: 6888307
                    || $this->AccountFields['Login'] == 'alexnorth61'
                    || (
                        !in_array($this->State['locale'], [
                            'en-GB',
                            'en-IN',
                            'en-AU',
                            'de-DE',
                            'nl-NL',
                        ])
                        && $hasEmail === false
                        && (
                            !isset($response->description)
                            || !in_array($response->description, [
                                "No active card account belongs to user",
                            ])
                        )
                    )
                )
                && isset($this->State['assessmentToken'])
                && !in_array($this->AccountFields['Login'], [
                    'miney622',
                    'bernardox1',
                    'davidmburggraf',
                    'mylinh04',
                    'Ekoihc547',
                    'davekean01',
                    'dschabac3',
                    'salon1965',
                    'erbyrd22',
                    'byont1k',
                    'scbutler',
                    'salon1965',
                    'rcgarner48',
                    'DHarper16000',
                ])
            ) {
                $this->logger->notice("ReadAuthenticationChallenges.v3");
                $data = [
                    "assessmentToken"       => $this->State['assessmentToken'],
                    "userJourneyIdentifier" => "aexp.global:create:session",
                ];
                $this->http->PostURL("https://functions.americanexpress.com/ReadAuthenticationChallenges.v3", json_encode($data), $headers);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();

                if (!isset($response->challengeQuestions)) {
                    // AccountID: 4696988
                    if (isset($response->message) && $response->message == "Secondary Authentication not possible. Check detail for more information.") {
                        throw new CheckException("Unable to process your request. We're sorry. Our system is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }

                $deliveryMethod = 'EMAIL';

                foreach ($response->challengeQuestions as $challengeQuestion) {
                    if ($challengeQuestion->category == 'OTP_EMAIL') {
                        $device = $challengeQuestion->challengeOptions[0]->maskedValue;
                        $this->logger->notice("email was found: {$device}");
                        $question = str_replace("was sent to you.", "was sent to {$device}.", self::QUESTION_TSC);
                        $encryptedValue = $challengeQuestion->challengeOptions[0]->encryptedValue;

                        break;
                    }// if ($challengeQuestion->category == 'OTP_EMAIL')
                }// foreach ($response->challengeQuestions as $challengeQuestion)

                if (!isset($encryptedValue) || !isset($question)) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $this->State['encryptedValue'] = $encryptedValue;

                $data = [
                    "userJourneyIdentifier" => "aexp.global:create:session",
                    "otpDeliveryRequest"    => [
                        "deliveryMethod" => $deliveryMethod,
                        "encryptedValue" => $this->State['encryptedValue'],
                    ],
                    "locale"                => $this->State['locale'],
                ];
                $this->http->PostURL("https://functions.americanexpress.com/CreateOneTimePasscodeDelivery.v3", json_encode($data), $headers);
                $response = $this->http->JsonLog();

                if (!isset($response->encryptedChannelValue)) {
                    $this->logger->error("something went wrong");

                    return false;
                }
                // {"errorCode":"UE_DATA_MISMATCH","message":"Data provided by user is not matching our records.","description":"Data provided is invalid"}

//                $error = $response->error ?? null;
//
//                if ($error == 'error: "(RECIPIENT_FAILURE,401) User not Authenticated, ErrorCode: invalid_or_expired_session, ErrorMessage: ErrorCode = IC_REQUEST_INPUT_VALIDATION_ERROR Message = Mandatory session cookies or user jwt are empty/invalid "') {
//                    throw new CheckException("Unable to process your request. We're sorry. Our system is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
//                }
//
//                // {} - // success
//                if (Html::cleanXMLValue($this->http->Response['body']) != '{}' || $this->http->Response['code'] != 200 || !isset($question)) {
//                    $this->logger->error("something went wrong");
//
//                    return false;
//                }

                if ($this->getWaitForOtc() && $this->isBackgroundCheck()) {
                    $this->sendNotification("mailbox, waiting code - refs #20425 // RR");
                }

                $this->Question = $question;
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "VerificationCode.v3";
            } else {
                $this->http->RetryCount = 2;

                if (!isset($response->tenuredChannels)) {
                    $message = $response->description ?? null;
                    $this->logger->error("[description]: {$message}");

                    // AccountID: 7039071
                    if ($message == "Verification Not Possible") {
                        throw new CheckException("Unable to process your request. We are unable to complete this request. Please call the number on the back of your Card.", ACCOUNT_PROVIDER_ERROR);
                    }
                    if ($message == "Invalid Security Token: No response from Card Service") {
                        throw new CheckException("We are experiencing temporary difficulties. Please try again later", ACCOUNT_PROVIDER_ERROR);
                    }
                    // AccountID: 4311242
                    if ($message == "No active card account belongs to user") {
                        throw new CheckException("Unable to process your request. We're sorry. Our system is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                $deliveryMethod = 'EMAIL';
                $channelType = 'EMAIL';

                foreach ($response->tenuredChannels as $tenuredChannel) {
                    if (
                        $tenuredChannel->deliveryMethod == 'EMAIL'
                        && !in_array($this->AccountFields['Login'], [
                            'erbyrd22', // AccountID: 7728145, refs #22826
                        ])
                    ) {
                        $device = $tenuredChannel->channelDisplayValue;
                        $this->logger->notice("email was found: {$device}");
                        $question = str_replace("was sent to you.", "was sent to {$device}.", self::QUESTION_TSC);

                        $deliveryMethod = $tenuredChannel->deliveryMethod;
                        $channelType = $tenuredChannel->channelType;
                        $channelEncryptedValue = $tenuredChannel->channelEncryptedValue;

                        break;
                    }

                    // sms
                    if (
                        (!isset($question) || (isset($question) && !strstr($question, "@")))
                        && $tenuredChannel->deliveryMethod == 'SMS'
                    ) {
                        $device = $tenuredChannel->channelDisplayValue;
                        $question = str_replace("was sent to you.", "was sent to {$device}.", self::QUESTION_TSC);
                        $deliveryMethod = $tenuredChannel->deliveryMethod;
                        $channelType = $tenuredChannel->channelType;
                        $channelEncryptedValue = $tenuredChannel->channelEncryptedValue;
                    } elseif (
                        (!isset($device) || (isset($question) && !strstr($question, "@")))
                        && $tenuredChannel->deliveryMethod == 'VOICE' && $tenuredChannel->channelType == 'HOME_PHONE_NUMBER' && $channelType == 'EMAIL'
                    ) {// AccountID: 1017136
                        $device = $tenuredChannel->channelDisplayValue;
                        $question = str_replace("was sent to you.", "was sent to {$device}.", self::QUESTION_TSC);
                        $deliveryMethod = $tenuredChannel->deliveryMethod;
                        $channelType = $tenuredChannel->channelType;
                        $channelEncryptedValue = $tenuredChannel->channelEncryptedValue;
                    } elseif (!isset($device) && $tenuredChannel->deliveryMethod == 'VOICE' && $tenuredChannel->channelType == 'WORK_PHONE_NUMBER') {// AccountID: 6408102
                        $device = $tenuredChannel->channelDisplayValue;
                        $question = str_replace("was sent to you.", "was sent to {$device}.", self::QUESTION_TSC);
                        $deliveryMethod = $tenuredChannel->deliveryMethod;
                        $channelType = $tenuredChannel->channelType;
                        $channelEncryptedValue = $tenuredChannel->channelEncryptedValue;
                    }
                }

                $this->State['accountToken'] = $response->identityData[0]->identityValue ?? null;

                if (!isset($channelEncryptedValue) || empty($this->State['accountToken']) || !isset($question)) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $data = [
                    [
                        "authenticationActionId" => $this->State['authenticationActionId'],
                        "applicationId"          => "LOGON01",
                        "accountToken"           => $this->State['accountToken'],
                        "locale"                 => $this->State['locale'],
                        "deliveryMethod"         => $deliveryMethod,
                        "channelType"            => $channelType,
                        "channelEncryptedValue"  => $channelEncryptedValue,
                    ],
                ];
                $this->http->PostURL("https://functions.americanexpress.com/CreateOneTimePasscodeDelivery.v1", json_encode($data), $headers);
                $response = $this->http->JsonLog();

                $error = $response->error ?? null;

                if ($error == 'error: "(RECIPIENT_FAILURE,401) User not Authenticated, ErrorCode: invalid_or_expired_session, ErrorMessage: ErrorCode = IC_REQUEST_INPUT_VALIDATION_ERROR Message = Mandatory session cookies or user jwt are empty/invalid "') {
                    throw new CheckException("Unable to process your request. We're sorry. Our system is temporarily unavailable. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                }

                // {} - // success
                if (Html::cleanXMLValue($this->http->Response['body']) != '{}' || $this->http->Response['code'] != 200 || !isset($question)) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                if ($this->getWaitForOtc() && $this->isBackgroundCheck()) {
                    $this->sendNotification("mailbox, waiting code - refs #20425 // RR");
                }

                $this->Question = $question;
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "VerificationCode";
            }

            return true;
        }

        $securityCode = false;

        if ($this->http->FindPreg("/we need to ask you for some additional security information/ims")
            || $this->http->FindPreg("/we need you to re-authenticate your account with a temporary key/ims")
            || $this->http->FindPreg("/ikainen turvakoodi ei muuta My Account Online -palvelun salasanaasi/ims")
            || $this->http->FindSingleNode("//img[@alt = 'Authenticate Your Account']/@alt")
            || $this->http->FindSingleNode("//h1[contains(text(), 'AUTHENTICATE YOUR ACCOUNT')]")
            || $this->http->FindSingleNode("//img[@alt = 'AUTHENTIFIZIERUNG IHRES KONTOS']/@alt")
            || $this->http->FindSingleNode("//img[@alt = 'ACCOUNT VERIFICATIE']/@alt")
            || $this->http->FindSingleNode("//img[contains(@alt, 'Authenticate Your Account')]/@alt")
            || $this->http->FindSingleNode("//img[@alt = 'VERIFICA IL TUO CONTO CARTA']/@alt")
            || $this->http->FindSingleNode("//img[@alt = 'VERIFICATION DE VOTRE IDENTITE']/@alt")
            || $this->http->FindSingleNode("//img[@alt = 'VERIFIQUE SU CUENTA']/@alt")
            || $this->http->FindSingleNode("//img[@alt = 'Tu Cuenta']/@alt")
            || $this->http->FindSingleNode("//img[@alt = 'カード情報の認証']/@alt")
            || $this->http->FindSingleNode("//h1[contains(text(), 'VALIDA EL ACCESO A SERVICIOS EN L')]")
            || $this->http->FindPreg("/Provide authentication using a security question online/ims")
            || $this->http->FindPreg("/Obtener un código de autenticación temporal vía correo electrónico/ims")) {
            $securityCode = true;
        }

        if ($securityCode && $this->http->ParseForm("passwordOptions")) {
            $this->http->MultiValuedForms = true;
            $this->logger->notice("selecting auth method: online");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            // header
            $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded");

            $device =
                $this->http->FindSingleNode('//input[@value = "REAUTH_KEY"]/following-sibling::span[1]', null, true, "/send\s*to\s*([^<]+)/ims")
                ?? $this->http->FindSingleNode('//input[@value = "OTP_ONSMS_KEY"]/following-sibling::span[1]', null, true, "/send\s*to\s*([^<]+)/ims")
                ?? $this->http->FindSingleNode('//input[@value = "OTP_OVER_VOICE_CELL"]/following-sibling::span[1]')
                ?? $this->http->FindSingleNode('//input[@value = "OTP_OVER_VOICE_HOME"]/following-sibling::span[1]');
            $this->logger->debug("[Device]: {$device}");
            $question = empty($device) ? self::QUESTION_TSC : str_replace("was sent to you.", "was sent to {$device}.", self::QUESTION_TSC);

            if (isset($this->Answers[$question])) {
                $this->logger->notice("try to entering security code. step 1");
                $this->http->SetInputValue("PswdOption", "EXIST_REAUTH_KEY");
            }// if (isset($this->Answers[$question]))
            elseif ($this->http->FindSingleNode("//input[@value = 'REAUTH_KEY']/@value")) {
                $this->http->SetInputValue("PswdOption", "REAUTH_KEY");
            } elseif ($this->http->FindSingleNode("//input[@value = 'OTP_ONSMS_KEY']/@value")) {
                $this->http->SetInputValue("PswdOption", "OTP_ONSMS_KEY");
            } elseif ($this->http->FindSingleNode("//input[@value = 'OTP_OVER_VOICE_CELL']/@value")) {
                $this->http->SetInputValue("PswdOption", "OTP_OVER_VOICE_CELL");
            } elseif ($this->http->FindSingleNode("//input[@value = 'OTP_OVER_VOICE_HOME']/@value")) {
                $this->http->SetInputValue("PswdOption", "OTP_OVER_VOICE_HOME");
            } else {
                $this->logger->notice(">>> PswdOption was not found !!!");
            }

            if ($this->http->FindPreg("/Provide authentication using a security question online/ims")
                || $this->http->FindPreg("/pondre aux questions d'identification en ligne\./ims")
                || ($this->http->FindPreg("/Authenticate online/ims")
                    && $this->http->FindPreg("/input id=\"ONL\" value=\"ONL\"/ims"))
                || $this->http->FindPreg("/&#12458;&#12531;&#12521;&#12452;&#12531;&#35469;&#35388;&#12377;&#12427;&#12383;&#12417;&#12395;&#12371;&#12371;&#12434;&#12463;&#12522;&#12483;&#12463;&#12375;&#12390;&#12367;&#12384;&#12373;&#12356;&#12290;/ims")) {
                $this->http->SetInputValue("PswdOption", "ONL");
            }

            $this->http->PostForm();

            // Parse Re-authentication form
            if ($this->http->ParseForm("reAuth") || $this->http->ParseForm("reAuthOptions")) {
                if ($this->http->FindSingleNode("//input[@name = 'DATEMM' or @name = 'CPW_SECRET_PASSWORD']/@name")) {
                    $question = self::QUESTION_ID_CODE;
                }

                $this->Question = $question;
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";

                $this->logger->notice("Re-authentication form was parsed");

                return true;
            }// if ($this->http->ParseForm("reAuth") || $this->http->ParseForm("reAuthOptions"))
        }// if ($securityCode && $this->http->ParseForm("passwordOptions"))
        $question = $this->http->FindSingleNode("//input[@class = 'mycaInputEntryBox']/@title");

        if (!isset($question) && $this->http->FindPreg("/(Basic Cardmember\'s Date of Birth)/ims")) {
            $question = self::QUESTION_DATE_OF_BIRTH;
        }
        $input = $this->http->FindSingleNode("//input[@class = 'mycaInputEntryBox']/@name");

        if (!isset($question) && $this->http->FindPreg("/(Basic Cardmember\'s year of birth)/ims")) {
            $question = self::QUESTION_YEAR_OF_BIRTH;
        }

        if (!isset($question)) {
            $question = $this->http->FindSingleNode("//input[@title = 'Enter the security information']/preceding-sibling::span[1]") ?? $this->http->FindSingleNode('//div[@id = "requestContentMessageOnl"]', null, true, "/Socios Titulares y Adicionales deben ingresar el aÃ±o de nacimiento del Socio Titular de la cuenta\./");

            // Argentina (AccountID: 4750488)
            if ($question == 'Socios Titulares y Adicionales deben ingresar el aÃ±o de nacimiento del Socio Titular de la cuenta.') {
                $question = self::QUESTION_YEAR_OF_BIRTH;
            }

            $input = $this->http->FindSingleNode("//input[@name = 'DATEMM' or @name = 'DATEYYYY']/@name");

            if (!isset($input)) {
                $this->http->MultiValuedForms = true;
                $input = $this->http->FindSingleNode("//input[@name = 'CPW_SECRET_PASSWORD']/@name");
            }
        }

        if ($this->http->ParseForm("reAuthOptions", null, false) && isset($question)
            && (in_array($question, [self::QUESTION_DATE_OF_BIRTH, self::QUESTION_YEAR_OF_BIRTH]) || isset($input))) {
            $this->http->MultiValuedForms = true;

            $this->logger->debug("[Input]: {$input}");

            if ($question != self::QUESTION_DATE_OF_BIRTH) {
                $this->State["InputName"] = $input;
            }

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            $this->logger->debug("[Question]: {$question}");
            $this->logger->debug("question was parsed");

            return true;
        }
        // For Your Account Security, Please Confirm Your Identity
        elseif (
            $this->http->ParseForm("onl-login")
            && ($question = $this->http->FindSingleNode('//p[input[@name = "answer"]]/preceding-sibling::p/label | //p[input[@name = "answer"]]/preceding-sibling::label | //label[contains(text(), "Please enter your Birth Date in MM/DD/YYYY format.") or contains(text(), "Please enter Mother\'s Birth Date in MM/DD format.")]'))
        ) {
            $this->logger->notice("For Your Account Security, Please Confirm Your Identity");

            $this->State["InputName"] = 'answer';

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        } else {
            if ($message = $this->http->FindSingleNode('//div[@data-testid="login-message-container"]/span[not(@data-testid="warning-icon")]')) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'The User ID or Password is incorrect. Please try again.')) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'We\'re sorry. Our system is temporarily unavailable.')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            // provider error
            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Lo sentimos pero no podemos acceder a sus datos en estos momentos.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Leider ist ein Login zum jetzigen Zeitpunkt nicht')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Italy
            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Siamo spiacenti ma non puoi accedere alla pagina richiesta.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->unableToAccess();

            // AccountID: 5028043
            if (
                $this->AccountFields["Login"] == 'SallyMain445'
                && ($message = $this->http->FindSingleNode("//p[@id = 'sorryPageHdrMsg' and contains(text(), 'Unfortunately, we are unable to log you in at this time.') and following-sibling::p[normalize-space(text()) = 'Please call the Customer Care number on the back of your Card.']]"))
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                in_array($this->AccountFields["Login"], [
                    'mordyb', // AccountID: 903272
                    'hangerone', // AccountID: 3406630
                ])
                && ($message = $this->http->FindPreg('/>(We\'re sorry. Our system is temporarily unavailable\. Please try again later\.)<\/p>/'))
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function unableToAccess()
    {
        $this->logger->notice(__METHOD__);

        if ($this->attempt > 1) {
            return true;
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'We are unable to access your Account.')]")) {
            $this->DebugInfo = 'Need to enable proxy';
            $this->logger->info("unable to access, will try with proxy");
            $this->proxyRetries();
        }

        if ($this->http->currentUrl() == 'https://www.americanexpress.com/account/login?Face=en_US&DestPage=https%3A%2F%2Fonline.americanexpress.com%2Fmyca%2Freauth%2Fus%2FreauthOptionsController.do%3Frequest_type%3Dauthreg_reauthHandler%26Face%3Den_US%26sorted_index%3D0%26Details%3Dtrue%26DestPage%3Dhttps%253A%252F%252Fglobal.americanexpress.com%252Fdashboard%253Fomnlogin%253Dus_homepage_myca') {
            $this->DebugInfo = '400_heading';
            $this->logger->info("400_heading");
            $this->proxyRetries();
        }

        if ($this->attempt > 0) {
            return true;
        }

        if (
            $this->http->FindSingleNode("//b[contains(text(), 'Our System is Not Responding')]")
            || ($this->http->FindPreg("/Cannot POST \/search/") && $this->http->Response['code'] == 404)
            || $this->http->FindSingleNode("//input[@id = 'errMsgValue' and @value = 'Your User ID or Password is incorrect. Please try again.']/@value")
        ) {
            $this->logger->notice("retry, provider bug fix");
//            $this->selenium();
            $this->proxyRetries();

            return true;
        }

        if (
            $this->http->ParseForm("onl-login")
            && ($question = $this->http->FindSingleNode('//p[input[@name = "answer"]]/preceding-sibling::p/label | //p[input[@name = "answer"]]/preceding-sibling::label | //label[contains(text(), "Please enter your Birth Date in MM/DD/YYYY format.") or contains(text(), "Please enter Mother\'s Birth Date in MM/DD format.")]'))
        ) {
            $this->logger->notice("retry, provider bug fix");
            $this->proxyRetries();
        }

        return false;
    }

    public function LoginNorthAfrica()
    {
        $this->logger->notice(__METHOD__);
        $resultXpath = '
            //a[contains(text(), "Log out")]
            | //input[@id = "inputOTP"]
            | //p[contains(text(), "Trust This Device?")]
            | //*[self::button or self::p][contains(., "Email OTP")]
        ';
        $resultXpathExtended = '| //h4[contains(text(), "Security Alert")]';
        $this->waitForElement(WebDriverBy::xpath($resultXpath . $resultXpathExtended), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//h4[contains(text(), "Security Alert")]')) {
            $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Next")]'), 0)->click();

            if ($opt = $this->waitForElement(WebDriverBy::xpath('//*[self::button or self::p][contains(., "Email OTP")]'), 5)) {
                $opt->click();

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Confirm")]'), 5)->click();
            }

            $this->waitForElement(WebDriverBy::xpath($resultXpath), 5);
        }

        /*
        if (!$this->http->PostForm()) {
            return false;
        }

        // INTRODUCING E-STATEMENTS FROM AMERICAN EXPRESS
        $formURL = $this->http->FindSingleNode("//a[contains(@href, 'remindLaterAL')]/@href");

        if ($formURL && $this->http->ParseForm("pref_form")) {
            $this->logger->notice("skip update profile");
            $this->http->NormalizeURL($formURL);
            $this->http->FormURL = $formURL;
            $this->http->PostForm();
            // js redirect
            $this->http->GetURL("https://secure.americanexpress.com.bh/wps/portal/lebanon/AccountSummary");
        }// if ($formURL && $this->http->ParseForm("pref_form"))
        */

        if ($yesBtn = $this->waitForElement(WebDriverBy::xpath('
                //form[@name="trustedDeviceForm"]//input[@name = "btnYes"]
                | //h4[contains(text(), "Trust This Device?")]/following-sibling::div//button[contains(text(), "Yes")]
        '), 0)) {
            $yesBtn->click();

            if ($okBtn = $this->waitForElement(WebDriverBy::xpath('//input[@name = "btnOk"]'), 5)) {
                $okBtn->click();
            }

            $this->waitForElement(WebDriverBy::xpath($resultXpath), 5);
            $this->saveResponse();
        }

        // Please select your preferred statement delivery method
        if ($this->http->FindPreg("/(Please select your preferred statement delivery method)/")
            && $this->http->FindPreg("/INTRODUCING E-STATEMENTS FROM AMERICAN EXPRESS<\/h1>/")) {
            throw new CheckException("Amex (Membership Rewards) website is asking you to select your preferred statement delivery method, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        if ($this->parseQuestionNorthAfrica()) {
            return false;
        }

        $message = $this->http->FindSingleNode('//div[contains(@class, "dls-color-warning")]//span[not(contains(@class, "icon"))]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Sorry, we are unable to process your request at the moment. Please try again.')
                || $message == 'Invalid Username/Password.'
                || $message == 'Invalid User ID/Password. You have one more attempt before your Online account is locked.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Locked Account. Please try again later or call Customer Services on the phone number specified on the back of your Card.'
                || $message == 'Account is locked. Please try again after sometime or contact Customer Services on the phone number at the back of your Card.'
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode("//a[contains(@class, 'olsr_logoutbtn')]/@class")
            || $this->http->FindSingleNode("//a[contains(text(), 'Log out')]")) {
            return true;
        }
        // Incorrect password entered.
        if ($this->http->FindPreg("/(Incorrect passs?word entered\.)/ims")) {
            throw new CheckException("Incorrect password entered.", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked. Please contact customer service.
        if ($message = $this->http->FindPreg("/(Your account has been locked\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Incorrect online login/password. Please try again
        if ($message = $this->http->FindPreg("/(Incorrect online login\/password\. [^<]+)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function parseQuestionSaudiArabia()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question (Authentication code)', ['Header' => 3]);

        $otp = $this->waitForElement(WebDriverBy::xpath('//input[contains(@class, "otp-field-box--")]'), 3);
        $this->saveResponse();

        if (!$otp) {
            return false;
        }

        if (!isset($this->Answers[$this->Question])) {
            $this->holdSession();
            $this->AskQuestion($this->Question, null, "QuestionSaudiArabia");

            return false;
        }// if (!isset($this->Answers[$this->Question]))

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->logger->debug("entering answer");
        $elements = $this->driver->findElements(WebDriverBy::xpath('//input[contains(@class, "otp-field-box--")]'));

        if (!empty($elements)) {
            $this->logger->debug("entering answer...");

            for ($i = 0; $i < strlen($answer); $i++) {
                $this->logger->debug("[{$i}]: {$answer[$i]}");
                $this->driver->findElement(WebDriverBy::xpath("//input[contains(@class, 'otp-field-box--" . ($i) . "')]"))->sendKeys($answer[$i]);
            }// for ($i = 0; $i < strlen($answer); $i++)
        }

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Submit") and not(@disabled)]'), 3);
        $this->saveResponse();

        if (!$btn) {
            return false;
        }

        $btn->click();

        sleep(5);

        $error = $this->waitForElement(WebDriverBy::xpath('
            //div[@class="alert"]//div[contains(@class, "v-alert__content")]/span
            | //div[@role = "alert"]//div[contains(@class, "v-messages__message")]
        '), 5);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();

            if ($message == 'You have entered an invalid Activation Code. Please try again') {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error->getText(), "QuestionSaudiArabia");

                return false;
            }

            $this->DebugInfo = $message;

            return false;
        }

        // TODO: debug, refs #23631
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Submit") and not(@disabled)]'), 3);
        $this->saveResponse();

        if ($btn) {
            $btn->click();
            sleep(5);
            $this->saveResponse();

            $this->waitForElement(WebDriverBy::xpath('
                //div[@class="alert"]//div[contains(@class, "v-alert__content")]/span
                | //div[@role = "alert"]//div[contains(@class, "v-messages__message")]
            '), 5);
            $this->saveResponse();

            if ($error = $this->waitForElement(WebDriverBy::xpath('//div[@class="alert"]//div[contains(@class, "v-alert__content")]/span | //div[@role = "alert"]//div[contains(@class, "v-messages__message")]'), 0)) {
                $message = $error->getText();

                if ($message == 'You have entered an invalid Activation Code. Please try again') {
                    $this->holdSession();
                    $this->AskQuestion($this->Question, $error->getText(), "QuestionSaudiArabia");

                    return false;
                }

                $this->DebugInfo = $message;

                return false;
            }
        }

        /*
        if ($btnYes = $this->waitForElement(WebDriverBy::xpath('//input[@name = "btnYes"] | //button[contains(text(), "Yes")]'), 0)) {
            $btnYes->click();

            $okBtn = $this->waitForElement(WebDriverBy::xpath('//input[@name = "btnOk"] | //button[contains(text(), "Ok")]'), 5);

            if ($okBtn) {
                $okBtn->click();
            }

            $this->waitForElement(WebDriverBy::xpath('
                //span[@name = "userName"]
                | //p[@class = "card-name"]
            '), 10);
            $this->saveResponse();
        }
        */

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return true;
    }

    public function parseQuestionNorthAfrica()
    {
        $this->logger->notice(__METHOD__);
        /*
        $question = $this->http->FindPreg('/To proceed further please enter the OTP sent to your email/');

        if (!$this->http->ParseForm("2FADeviceMgmtForm") || !$question) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "QuestionNorthAfrica";
        */

        $this->logger->info('Security Question (One-Time Pin)', ['Header' => 3]);
        $this->saveResponse();

        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "inputOTP"] | //input[@aria-label="number" and @id = "otp_1"]'), 3);
        $this->saveResponse();

        $question =
            $this->http->FindPreg('/To proceed further please enter the OTP sent to your email/')
            ?? $this->http->FindSingleNode('//p[contains(text(), "Please input the One Time Password sent to your email")]')
            ?? $this->http->FindSingleNode('//p[contains(text(), "Input OTP sent to your ")]')
        ;

        if (!$otp || !$question) {
            return false;
        }

        if ($question == 'To proceed further please enter the OTP sent to your email') {
            $question = 'Please enter a temporary security code which was sent to your email';
        } else {
            $question = str_replace(['Please input the One Time Password sent to your email', 'Input OTP sent to your email', 'Input OTP sent to your'], 'Please enter a temporary security code which was sent to your email', $question);
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "QuestionNorthAfrica");

            return false;
        }// if (!isset($this->Answers[$question]))

        $this->logger->debug("entering answer");

        $elements = $this->driver->findElements(WebDriverBy::xpath('//input[@aria-label="number" and contains(@id, "otp_")]'));

        if (!empty($elements)) {
            $this->logger->debug("entering answer...");
            $answer = $this->Answers[$question];

            for ($i = 0; $i < strlen($answer); $i++) {
                $this->logger->debug("[{$i}]: {$answer[$i]}");
                $this->driver->findElement(WebDriverBy::xpath("//input[@aria-label = 'number' and contains(@id, 'otp_" . ($i + 1) . "')]"))->sendKeys($answer[$i]);
            }// for ($i = 0; $i < strlen($answer); $i++)

            $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "btn-confirm-email-otp"]'), 3);
            $this->saveResponse();
        } else {
            $otp->sendKeys($this->Answers[$question]);
            $btn = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Proceed"]'), 0);
            $this->saveResponse();
        }
        unset($this->Answers[$question]);

        if (!$btn) {
            return false;
        }

        $btn->click();

        sleep(5);

        $this->waitForElement(WebDriverBy::xpath('
            //span[@name = "userName"]
            | //p[@class = "card-name"]
            | //input[@name = "btnYes"]            
            | //p[@id = "OTPValidationError"]            
            | //p[contains(text(), "The OTP that you entered is incorrect.")]     
        '), 5);
        $this->saveResponse();

        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[@id = "OTPValidationError"] | //p[contains(text(), "The OTP that you entered is incorrect.")]'), 0)) {
            $this->holdSession();
            $this->AskQuestion($question, $error->getText(), "QuestionNorthAfrica");

            return false;
        }

        if ($btnYes = $this->waitForElement(WebDriverBy::xpath('//input[@name = "btnYes"] | //button[contains(text(), "Yes")]'), 0)) {
            $btnYes->click();

            $okBtn = $this->waitForElement(WebDriverBy::xpath('//input[@name = "btnOk"] | //button[contains(text(), "Ok")]'), 5);

            if ($okBtn) {
                $okBtn->click();
            }

            $this->waitForElement(WebDriverBy::xpath('
                //span[@name = "userName"]            
                | //p[@class = "card-name"]            
            '), 10);
            $this->saveResponse();
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return true;
    }

    public function LoginSaudiArabia()
    {
        $this->logger->notice(__METHOD__);

        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')] | //div[@role = 'alert']//div[contains(@class, 'v-alert__content')]/span | //div[@role = \"alert\"]//div[contains(@class, \"v-messages__message\")]"), 10);
        $this->saveResponse();
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        if ($question = $this->http->FindSingleNode('//p[contains(text(), "Please enter authentication code sent to you over SMS.")]')) {
            $this->holdSession();
            $this->AskQuestion($question, null, "QuestionSaudiArabia");

            return false;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'The username or password is incorrect')] | //div[@role = \"alert\"]//div[contains(@class, \"v-messages__message\")]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The username or password is incorrect')
                || strstr($message, 'Your password has expired')
                || strstr($message, 'Password must be 6 characters containing letters, numbers and symbols.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        // AccountID: 3488910
        if ($message = $this->http->FindSingleNode('//div[@id = "bodyContent"]/h1[contains(., "Page cannot be found.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        /*
         * if session not closed
         */
        // You are already logged in. Error Code CI026
        if ($message = $this->http->FindPreg("/alert\(\'(You are already logged in. Error Code CI026)\'\)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoginIsrael()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();
        // Access is allowed
        if (isset($response->ValidateIdDataBean->returnCode, $response->ValidateIdDataBean->userName)
            && $response->ValidateIdDataBean->returnCode == 1) {
            $data = [
                "Sisma"         => str_replace('#', '', $this->AccountFields['Pass']),
                "KodMishtamesh" => $response->ValidateIdDataBean->userName,
                "MisparZihuy"   => $this->AccountFields['Login'],
                "countryCode"   => "212",
                "idType"        => "1",
                "cardSuffix"    => $this->AccountFields['Login3'],
            ];
            $this->http->PostURL("https://he.americanexpress.co.il/services/ProxyRequestHandler.ashx?reqName=performLogonA", json_encode($data));
            $response = $this->http->JsonLog();

            if (isset($response->status) && $response->status == 1) {
                return true;
            }
            // catch some errors
            if (isset($response->message)) {
                if (
                    // One of the ID details is invalid
                    strstr($response->message, "אחד מפרטי הזיהוי אינו תקין")
                    // Note that you can try again before temporarily blocking. If you make a mistake again, you will be deleted from the system and need to re-register.
                    || strstr($response->message, " שים לב, באפשרותך לבצע ניסיון נוסף בטרם תחסם זמנית. במידה ותטעה שוב, תימחק מהמערכת ותצטרך לבצע הרשמה מחדש.")
                    // incorrect data
                    || strstr($response->message, "נתונים שגויים")
                    // At least one of the details does not fit, check and try again
                    || strstr($response->message, "לפחות אחד מהפרטים לא מתאים, יש לבדוק ולנסות שוב")
                    // Enter 8-20 numbers and letters in English
                    || strstr($response->message, "יש להזין 8-20 ספרות ואותיות באנגלית")
                ) {
                    throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    /*
                     * Due to failed attempts, you are temporarily blocked for 5 minutes.
                     * If you make a mistake again, you will be deleted from the system and need to re-register
                     */
                    strstr($response->message, "עקב מס' נסיונות כושלים, נחסמת באופן זמני ל5- דקות. במידה ותטעה שוב, תימחק מהמערכת ותצטרך לבצע הרשמה מחדש.")
                    // You have made a mistake 6 times and therefore the user has been revoked.
                    || strstr($response->message, "טעית 6 פעמים ולכן המשתמש בוטל, כדי להיכנס לאתר תצטרך להירשם מחדש.")
                    // The account has been locked for 30 minutes due to multiple incorrect login attempts. You can try again later
                    || strstr($response->message, "החשבון ננעל ל-30 דקות בגלל מספר ניסיונות כניסה שגויים. ניתן לנסות שוב לאחר מכן")
                ) {
                    throw new CheckException($response->message, ACCOUNT_LOCKOUT);
                }

                $this->logger->error("[Error]: {$response->message}");
            }// if (isset($response->message))

            return false;
        }
        // Invalid credentials
        if (isset($response->ValidateIdDataBean->message)) {
            // Please enter only numbers (CardSuffix)
            if (strstr($response->ValidateIdDataBean->message, 'אנא הזן ספרות בלבד')) {
                throw new CheckException($response->ValidateIdDataBean->message, ACCOUNT_INVALID_PASSWORD);
            }
            // Password expired validity
            if (strstr($response->ValidateIdDataBean->message, 'סיסמא פגה תוקף')) {
                throw new CheckException($response->ValidateIdDataBean->message, ACCOUNT_INVALID_PASSWORD);
            }
            // An unregistered customer
            if (strstr($response->ValidateIdDataBean->message, "לקוח לא רשום לאתר")) {
                throw new CheckException($response->ValidateIdDataBean->message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->logger->error("[Error]: {$response->ValidateIdDataBean->message}");
        }// if (isset($response->ValidateIdDataBean->message))
        // Security questions
        if (isset($response->ValidateIdDataBean)) {
            $this->State["Response"] = $response;

            foreach ($response->ValidateIdDataBean as $key => $value) {
                if (strstr($key, 'question') && strstr($key, 'Content') && $value && !isset($this->Answers[$value])) {
                    $this->AskQuestion($value);

                    return false;
                }
            }// if (strstr($key, 'question') && strstr($key, 'Content') && $value && !isset($this->Answers[$value]))
        }// if (isset($response->ValidateIdDataBean))

        return false;
    }

    public function LoginSwitzerland()
    {
        $this->logger->notice(__METHOD__);

        $response = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if ($response) {
            $errorCode = $response->errors[0]->code ?? null;

            if ($errorCode && $errorCode == 'AUTHENTICATION_FAILED') {
                throw new CheckException("Wrong email / password", ACCOUNT_INVALID_PASSWORD);
            }
            $nextAuthStep = $response->data->attributes->nextAuthStep ?? null;
            // Terms and Conditions of Use of Swisscard Digital Services
            if ($nextAuthStep && $nextAuthStep == 'TERMS_OF_SERVICES_RETRIEVAL_REQUIRED') {
                $this->throwAcceptTermsMessageException();
            }
        }

        // AccountID: 4797273
        if (strstr($this->http->currentUrl(), 'https://sso.swisscard.ch/error_path/400.html?al_req_id=')) {
            throw new CheckException("An unexpected error just occurred. We apologize for any inconvenience.", ACCOUNT_INVALID_PASSWORD);
        }

        // Strong registration
        if (
            $this->http->FindSingleNode('//form[@name = "STRONG_REG_FORM"]//label[contains(@class, "c-checkbox")]', null, true, "/By checking this box, I confirm that I have read, understood, and accepted the/")
            && ($skip = $this->http->FindSingleNode('//a[@id = "noInput"]/@href'))
        ) {
            $this->logger->notice("Skip Strong registration");
            $this->http->NormalizeURL($skip);
            $this->http->GetURL($skip);
        }

        if ($this->http->ParseForm("SAMLResponse")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm(null, "//form[contains(@action, 'genericSSOReceiver')]")) {
            $this->http->PostForm();
        }

        if ($this->http->ParseForm(null, "//form[contains(@action, 'commonSSOReceiver')]")) {
            $this->http->PostForm();
        }

        // refs #23495 multiple accounts
        $forms = $this->http->FindNodes("//div[@class = 'holder-switch-account']//form[contains(@action, 'rexSSOReceive')]");
        $allFormsCount = count($forms);
        $allForms = [];

        for ($i = 0; $i < $allFormsCount; $i++) {
            if ($this->http->ParseForm(null, "(//div[@class = 'holder-switch-account']//form[contains(@action, 'rexSSOReceive')])[" . ($i + 1) . "]")) {
                $allForms[] = [
                    "FormURL" => $this->http->FormURL,
                    "Form"    => $this->http->Form,
                ];
            }
        }

        if ($allFormsCount > 1) {
            $balance = null;
            $this->logger->info(var_export($allForms, true), ['pre' => true]);

            foreach ($allForms as $allForm) {
                $this->logger->info("Membership Rewards: #{$allForm['Form']['selectedAccountID']}", ['Header' => 3]);

                $this->http->FormURL = $allForm['FormURL'];
                $this->http->Form = $allForm['Form'];
                $this->http->PostForm();

                if ($this->http->ParseForm("loginForm")) {
                    $this->LoadLoginFormSwitzerland();

                    if ($this->http->ParseForm("SAMLResponse")) {
                        $this->http->PostForm();
                    }

                    if ($this->http->ParseForm(null, "//form[contains(@action, 'genericSSOReceiver')]")) {
                        $this->http->PostForm();
                    }

                    if ($this->http->ParseForm(null, "//form[contains(@action, 'commonSSOReceiver')]")) {
                        $this->http->PostForm();
                    }

                    $this->http->FormURL = $allForm['FormURL'];
                    $this->http->Form = $allForm['Form'];
                    $this->http->PostForm();
                }// if ($this->http->ParseForm("loginForm"))

                if ($this->http->FindSingleNode("//a[@id = 'logoutHandler']")) {
                    // Balance - points
                    $bal = $this->getBalanceForParseSwitzerland();
                    $balance += $bal;
                    $this->AddSubAccount([
                        'Code'              => 'amexSwitzerland' . $allForm['Form']['selectedAccountID'],
                        'DisplayName'       => "Membership Rewards: #{$allForm['Form']['selectedAccountID']}",
                        'Balance'           => $bal,
                        'Name'              => $this->getNameForParseSwitzerland(),
                        "BalanceInTotalSum" => true,
                    ]);

                    if (!empty($this->Properties['SubAccounts'])) {
                        $this->SetBalance($balance);
                    }// if (!empty($this->Properties['SubAccounts']))
                }// if ($this->http->FindSingleNode("//a[@id = 'logoutHandler']"))
            }// foreach ($allForms as $allForm)

            return false;
        } else {
            if ($this->http->ParseForm(null, "//div[@class = 'holder-switch-account']//form[contains(@action, 'rexSSOReceive')]")) {
                $this->http->PostForm();
            }
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[@id = 'logoutHandler']")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@id= "error-message-id"]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Leider ist es Ihnen nicht möglich sich in diesem Shop einzuloggen.")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;
        }

        /*
         * Your e-mail address or password is incorrect. Please note that your cardservice password cannot be used here.
         * Please try again.The registration for business clients with a Corporate or Business Card will follow later.
         */
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'Your e-mail address or password is incorrect.')
                or contains(text(), 'Leider ist es Ihnen nicht möglich sich in diesem Shop einzuloggen.')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Apologies, your login request was unsuccessful. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[
            contains(text(), 'Apologies, your login request was unsuccessful. Please try again later.')
            or contains(text(), 'Unfortunately you cannot use this service. Please contact Swisscard customer support: +41 44 659 25 25.')
        ]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The user has been blocked for security reasons
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The user has been blocked for security reasons. Please contact Swisscard customer service. You can find the phone number on the back of your credit card.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Unerwarteter Fehler
        // Wir bitten Sie um Entschuldigung, ein unerwarteter Fehler ist aufgetreten.
        if ($message = $this->http->FindSingleNode("
                //span[contains(text(), 'Wir bitten Sie um Entschuldigung, ein unerwarteter Fehler ist aufgetreten.')]
                | //p[contains(text(), 'Es tut uns leid, Ihr Login-Versuch ist fehlgeschlagen. Bitte versuchen Sie es später erneut.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//h1[contains(text(), "Internal Server Error")]')
            && strstr($this->http->currentUrl(), 'https://rewardshop.americanexpress.ch/error_path/500.html?al_req_id=')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice("[ProcessStep]: {$this->Question}");

        if ($step === 'QuestionSaudiArabia') {
            return $this->parseQuestionSaudiArabia();
        }

        if ($step === 'QuestionNorthAfrica') {
            /*
            $this->http->SetInputValue("inputOTP", $this->Answers[$this->Question]);
            unset($this->Answers[$this->Question]);
            $this->http->PostForm();

            // js redirect
            $this->http->GetURL("https://secure.americanexpress.com.bh/wps/portal/lebanon/AccountSummary"); //todo
            */

            return $this->parseQuestionNorthAfrica();
        }

        if ($this->http->FormURL === 'https://global.americanexpress.com/search') {
            $this->logger->notice("correcting form url from {$this->http->FormURL}");
            $this->http->FormURL = 'https://online.americanexpress.com/myca/reauth/us/reauthVerificationController.do?request_type=authreg_reauthHandler&Face=en_US';
        }

        if ($step == 'VerificationCode.v3') {
            $headers = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json; charset=UTF-8",
            ];
            $data = [
                "userJourneyIdentifier" => "aexp.global:create:session",
                "assessmentToken"       => $this->State['assessmentToken'],
                "challengeAnswers"      => [
                    [
                        "type"           => "OTP",
                        "value"          => $this->Answers[$this->Question],
                        "encryptedValue" => $this->State['encryptedValue'],
                    ],
                ],
            ];
            unset($this->Answers[$this->Question]);

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://functions.americanexpress.com/UpdateAuthenticationTokenWithChallenge.v3", json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->description) && $response->description == "Data provided is invalid") {
                // {"errorCode":"UE_DATA_MISMATCH","message":"Data provided by user is not matching our records.","description":"Data provided is invalid"}
                $this->AskQuestion($this->Question, "The information you have entered is incorrect. Please try again.", "VerificationCode.v3");

                return false;
            }
//            // expired session
//            if (
//                isset($response->error)
//                && (
//                    strstr($response->error, "User not Authenticated, ErrorCode: invalid_or_expired_session")
//                    || strstr($response->error, "User Authentication Token expired and cannot be refreshed due to inactivity")
//                )
//            ) {
//                return $this->LoadLoginForm() && $this->Login();
//            }

            // remember device
            $data = [
                [
                    "locale"     => $this->State['locale'],
                    "trust"      => true,
                    "deviceName" => "AwardWallet",
                ],
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://functions.americanexpress.com/UpdateAuthenticationTokenWithChallenge.v1", json_encode($data), $headers); // todo: not working
            $this->http->JsonLog();
            $this->http->RetryCount = 2;

            // refs #19332
//            $this->sendNotification("refs #19332 2fa");
            // get gatekeeper cookie
            $data = [
                "request_type" => "login",
                "Face"         => "en_US",
                "Logon"        => "Logon",
                "version"      => "4",
                "mfaId"        => $this->State['mfaId'],
                "b_hour"       => intval(date('h')),
                "b_minute"     => intval(date('i')),
                "b_second"     => intval(date('s')),
                "b_dayNumber"  => intval(date('j')),
                "b_month"      => date('n'),
                "b_year"       => date('Y'),
                "b_timeZone"   => 5,
                //                "devicePrint"  => "version%3D3%2E4%2E0%2E0%5F1%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%5F15%5F5%29%20applewebkit%2F537%2E36%20%28khtml%2C%20like%20gecko%29%20chrome%2F83%2E0%2E4103%2E61%20safari%2F537%2E36%7C5%2E0%20%28Macintosh%29%7CMacIntel%26pm%5Ffpsc%3D24%7C1536%7C960%7C880%26pm%5Ffpsw%3D%26pm%5Ffptz%3D5%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D0%26pm%5Ffpco%3D1%26pm%5Ffpasw%3D%26pm%5Ffpan%3DNetscape%26pm%5Ffpacn%3DMozilla%26pm%5Ffpol%3Dtrue%26pm%5Ffposp%3D%26pm%5Ffpup%3D%26pm%5Ffpsaw%3D1536%26pm%5Ffpspd%3D24%26pm%5Ffpsbd%3D%26pm%5Ffpsdx%3D%26pm%5Ffpsdy%3D%26pm%5Ffpslx%3D%26pm%5Ffpsly%3D%26pm%5Ffpsfse%3D%26pm%5Ffpsui%3D%26pm%5Fos%3DMac%26pm%5Fbrmjv%3D83%26pm%5Fbr%3DChrome%26pm%5Finpt%3D%26pm%5Fexpt%3D",
            ];
            $this->http->PostURL("https://global.americanexpress.com/myca/logon/us/action/login", $data);
            $this->http->JsonLog();
            $this->http->GetURL("https://global.americanexpress.com/dashboard");

            return true;
        }

        if ($step == 'VerificationCode') {
            $data = [
                [
                    "authenticationActionId" => $this->State['authenticationActionId'],
                    "applicationId"          => "LOGON01",
                    "accountToken"           => $this->State['accountToken'],
                    "locale"                 => $this->State['locale'],
                    "fieldName"              => "OTP",
                    "fieldValue"             => $this->Answers[$this->Question],
                ],
            ];
            $headers = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json; charset=UTF-8",
            ];
            unset($this->Answers[$this->Question]);
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://functions.americanexpress.com/UpdateAuthenticationTokenWithChallenge.v1", json_encode($data), $headers); // todo: not working
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->description) && $response->description == "Invalid Claim: Data does not match SOR") {
                // {"description":"Invalid Claim: Data does not match SOR","errorCode":"UEVE008"}
                $this->AskQuestion($this->Question, "The value you entered is incorrect. Please try again.", "VerificationCode");

                return false;
            }
            // expired session
            if (
                isset($response->error)
                && (
                    strstr($response->error, "User not Authenticated, ErrorCode: invalid_or_expired_session")
                    || strstr($response->error, "User Authentication Token expired and cannot be refreshed due to inactivity")
                )
            ) {
                return $this->LoadLoginForm() && $this->Login();
            }

            // remember device
            $data = [
                [
                    "locale"     => $this->State['locale'],
                    "trust"      => true,
                    "deviceName" => "AwardWallet",
                ],
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://functions.americanexpress.com/CreateTwoFactorAuthenticationForUser.v1", json_encode($data), $headers);
            $this->http->JsonLog();
            $this->http->RetryCount = 2;

            // refs #19332
//            $this->sendNotification("refs #19332 2fa");
            // get gatekeeper cookie
            $data = [
                "request_type" => "login",
                "Face"         => "en_US",
                "Logon"        => "Logon",
                "version"      => "4",
                "mfaId"        => $this->State['mfaId'],
                "b_hour"       => intval(date('h')),
                "b_minute"     => intval(date('i')),
                "b_second"     => intval(date('s')),
                "b_dayNumber"  => intval(date('j')),
                "b_month"      => date('n'),
                "b_year"       => date('Y'),
                "b_timeZone"   => 5,
                //                "devicePrint"  => "version%3D3%2E4%2E0%2E0%5F1%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%5F15%5F5%29%20applewebkit%2F537%2E36%20%28khtml%2C%20like%20gecko%29%20chrome%2F83%2E0%2E4103%2E61%20safari%2F537%2E36%7C5%2E0%20%28Macintosh%29%7CMacIntel%26pm%5Ffpsc%3D24%7C1536%7C960%7C880%26pm%5Ffpsw%3D%26pm%5Ffptz%3D5%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D0%26pm%5Ffpco%3D1%26pm%5Ffpasw%3D%26pm%5Ffpan%3DNetscape%26pm%5Ffpacn%3DMozilla%26pm%5Ffpol%3Dtrue%26pm%5Ffposp%3D%26pm%5Ffpup%3D%26pm%5Ffpsaw%3D1536%26pm%5Ffpspd%3D24%26pm%5Ffpsbd%3D%26pm%5Ffpsdx%3D%26pm%5Ffpsdy%3D%26pm%5Ffpslx%3D%26pm%5Ffpsly%3D%26pm%5Ffpsfse%3D%26pm%5Ffpsui%3D%26pm%5Fos%3DMac%26pm%5Fbrmjv%3D83%26pm%5Fbr%3DChrome%26pm%5Finpt%3D%26pm%5Fexpt%3D",
            ];
            $this->http->PostURL("https://global.americanexpress.com/myca/logon/us/action/login", $data);
            $this->http->JsonLog();
            $this->http->GetURL("https://global.americanexpress.com/dashboard");

            return true;
        }

        // refs #13456
        if ($this->AccountFields['Login2'] == 'ישראל') {
            $this->logger->notice("[ProcessStep]: Israel");

            if (isset($this->State["Response"])) {
                $response = $this->State["Response"];
                $this->logger->debug("[Response]: " . var_export($response, true), ['pre' => true]);

                foreach ($response->ValidateIdDataBean as $key => $value) {
                    if (strstr($key, 'question') && strstr($key, 'Content') && !isset($this->Answers[$value])) {
                        $this->AskQuestion($value);

                        return false;
                    }
                }// if (strstr($key, 'question') && strstr($key, 'Content') && !isset($this->Answers[$value]))
            }// if (isset($this->State["Response"]))

//            $this->http->SetInputValue("smsVerificationCode", $this->Answers[$this->Question]);
//            $this->http->PostForm();
//            // You have entered the incorrect verification code. Please try again
//            if ($error = $this->http->FindSingleNode("//li[contains(text(), 'You have entered the incorrect verification code.')]")) {
//                $this->AskQuestion($this->Question, $error);
//                return false;
//            }

            return true;
        }// if ($this->AccountFields['Login2'] == 'ישראל')

        if ($this->Question != self::QUESTION_DATE_OF_BIRTH && isset($this->State["InputName"])) {
            $this->http->SetInputValue("InputName", $this->State["InputName"]);
        }

        if (in_array($this->Question, [self::QUESTION_ID_CODE, '4-Digit Password']) || strstr($this->Question, 'Please enter a temporary security code which was sent')) {
            $this->logger->notice("ProcessStep. Entering a temporary security code");

            if (in_array($this->Question, [self::QUESTION_ID_CODE, '4-Digit Password'])) {
                $this->logger->notice("ProcessStep. ONL");
                // notifications
//                $this->sendNotification("Identification code was entered");
                if ($this->http->FindSingleNode("//b[contains(text(), 'Memorable Date (MMDD)') or contains(text(), 'morable : (JJMM)')]") || isset($this->http->Form['DATEMM'])) {
                    $this->logger->notice("ProcessStep. ONL - DATEMM");
                    $this->http->Form['DATEMM'] = $this->Answers[$this->Question];
                } elseif ($this->http->FindSingleNode("//b[contains(text(), '4-Digit Password')]") || isset($this->http->Form['CPW_SECRET_PASSWORD'])) {
                    $this->logger->notice("ProcessStep. ONL - CPW_SECRET_PASSWORD");
                    $this->http->SetInputValue('CPW_SECRET_PASSWORD', $this->Answers[$this->Question]);
                    $this->http->SetInputValue('csm_sbt', "Next");
                } elseif ($this->Question == self::QUESTION_DATE_OF_BIRTH) {
                    $this->logger->notice("ProcessStep. ONL - Basic Cardmember's Date of Birth");
                    $date = explode('/', $this->Answers[$this->Question]);
                    $this->http->SetInputValue('DATEDD', $date[0]);
                    $this->http->SetInputValue('DATEMM', $date[1]);
                    $this->http->SetInputValue('DATEYYYY', $date[2]);
                } elseif ($this->Question == self::QUESTION_YEAR_OF_BIRTH) {
                    $this->logger->notice("ProcessStep. ONL - Basic Cardmember's Year of Birth");
                    $this->http->SetInputValue('DATEYYYY', $this->Answers[$this->Question]);
                } else {
                    $this->logger->notice("ProcessStep. ONL - unknown fields");
                    $this->http->SetInputValue('DATEMM', $this->Answers[$this->Question]);
                }
                $this->http->SetInputValue('PswdOption', "ONL");
            }// if ($this->Question == self::QUESTION_ID_CODE)
            else {// else ($this->Question == self::QUESTION_TSC)
                $this->logger->notice("ProcessStep. TSC");
                $this->http->SetInputValue('authPwd', $this->Answers[$this->Question]);
                $this->http->SetInputValue('PswdOption', "EXIST_REAUTH_KEY");
                unset($this->Answers[$this->Question]);
            }// else ($this->Question == self::QUESTION_TSC)
        }// if (in_array($this->Question, array(self::QUESTION_TSC, self::QUESTION_ID_CODE)))
        elseif (isset($this->State["InputName"])) {
            $this->logger->notice(">>> Unknown question");
            $answer = $this->Answers[$this->Question];

            if ($this->Question == 'Please enter your Birth Date in MM/DD/YYYY format.') {
                $answerParts = explode('/', $answer);

                if (count($answerParts) != 3 || strlen($answerParts[2]) != 4) {
                    $this->AskQuestion($this->Question, "The information you entered does not match our records");

                    return false;
                }// if (count($answerParts) != 3 || strlen($answerParts[2]) != 4)
                $answer = $answerParts[2] . $answerParts[0] . $answerParts[1];
            }// if ($this->Question == 'Please enter your Birth Date in MM/DD/YYYY format.') {

            $this->http->SetInputValue($this->State["InputName"], $answer);
            $this->http->Form[$this->State["InputName"]] = $answer; //todo
            $this->http->unsetInputValue("InputName");
        } else {
            $this->logger->error("InputName not found");
        }
        $this->http->PostForm();
        $error = $this->http->FindSingleNode("//font[@color = '#cc3300']");

        if (!isset($error)) {
            $error = $this->http->FindPreg("/The information you entered does not match our records/ims");
        }
        // France
        if (!isset($error)) {
            $error = $this->http->FindPreg("/Les informations indiqu.+es ne correspondent pas aux donn.+es que vous nous avez transmises lors de votre inscription\./ims");
        }
        // temporary security code
        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//b[contains(text(), 'The security information provided by you is not valid')]");
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//b[contains(text(), 'The Reauthentication information provided by you is not valid')]");
        }
        // Nederland
        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//b[contains(text(), 'De door u ingevoerde tijdelijke verificatiecode is niet correct.')]");
        }
        // United States
        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//div[contains(text(), 'The temporary security code you entered is not valid.')]");
        }

        if (!isset($error)) {
            $error = $this->http->FindPreg("/(The Authentication Key you entered is not valid\.)/");
        }
        // Argentina
        if (!isset($error)) {
            $error = $this->http->FindPreg("/La\s*informaci.n\s*de\s*seguridad\s*ingresada\s*no\s*es\s*v.lida\./");
        }
        // Italia
        if (!isset($error)) {
            $error = $this->http->FindPreg("/La\s*risposta\s*fornita\s*non\s*.\s*valida\.\s*Riprova\./");
        }
        // Canada
        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//div[contains(text(), 'The security information provided by you is not valid.')]");
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//p[contains(text(), 'The answer you entered is incorrect.')]");
        }

        if (!isset($error)) {
            // 認証情報に誤りがあります。入力内容をご確認のうえ、再度ご入力ください。
            $error = $this->http->FindPreg("/\&\#35469;\&\#35388;\&\#24773;\&\#22577;\&\#12395;\&\#35492;\&\#12426;\&\#12364;\&\#12354;\&\#12426;\&\#12414;\&\#12377;\&\#12290;\&\#20837;\&\#21147;\&\#20869;\&\#23481;\&\#12434;\&\#12372;\&\#30906;\&\#35469;\&\#12398;\&\#12358;\&\#12360;\&\#12289;\&\#20877;\&\#24230;\&\#12372;\&\#20837;\&\#21147;\&\#12367;\&\#12384;\&\#12373;\&\#12356;\&\#12290;/");
        }
        // temporary security code
        if (in_array($this->Question, [self::QUESTION_TSC, self::QUESTION_ID_CODE])
            || strstr($this->Question, 'Please enter a temporary security code which was sent ')
        ) {
            unset($this->Answers[$this->Question]);

            if (isset($error)) {
                $this->AskQuestion($this->Question, $error);

                return false;
            }
        }

        if (isset($error)) {
            $this->ParseQuestion();
            $this->ErrorMessage = $error;

            return false;
        }

        if ($this->http->ParseForm("passwordOptions") && $this->ParseQuestion()) {
            $this->logger->notice("Ask security question one more time after entering last four digits of Social Security Number...");

            return false;
        }// if ($this->http->ParseForm("passwordOptions") && $this->ParseQuestion()

        /*
         * You have been unsuccessful logging in to your account.
         * Please enter your Card information to retrieve your User ID and reset your password.
         */
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'You have been unsuccessful logging in to your account. Please enter your Card information to retrieve your User ID and reset your password.')]")) {
            throw new CheckException("Amex (Membership Rewards) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//div[@id = "requestContentMessageOnl"]', null, true, "/Socios Titulares y Adicionales deben ingresar el aÃ±o de nacimiento del Socio Titular de la cuenta\./")
            && $this->ParseQuestion()
        ) {
            return false;
        }

        $this->unableToAccess();

        if (
            $this->attempt == 0
            && $this->http->FindSingleNode("//h1[contains(text(), 'We are unable to access your Account.')]")
        ) {
            $this->sendNotification("refs #18232. see logs after sq, v.2 // RR");
            $this->http->removeCookies();
            $this->selenium();

            return $this->Login();
        }
        // sometimes it's works
        if (
            $this->attempt == 0
            && $this->http->FindSingleNode("
                //p[@id = 'sorryPageHdrMsg' and contains(text(), 'Unfortunately, we are unable to log you in at this time.')]/following-sibling::p[normalize-space(text()) = 'Please call the Customer Care number on the back of your Card.']
            ")
        ) {
            $this->http->removeCookies();
            $this->selenium();

            return $this->Login();
        }

        return true;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                   => "PostingDate",
            "Description"            => "Description",
            "Amount"                 => "Amount",
            "Currency"               => "Currency",
            "Category"               => "Category",
            "Points"                 => "Miles",
            "Reference"              => "Info",
            "Phone"                  => "Info",
            "Address"                => "Info",
            "Additional Information" => "Info",
            "Rewards"                => "Info",
        ];
    }

    public function GetHiddenHistoryColumns()
    {
        return [
            'Reference',
            'Address',
            'Phone',
            'Rewards',
            'Additional Information',
        ];
    }

    public function Parse()
    {
        $loggedIn = ($this->http->FindSingleNode("//div[@class='AMCenterLanding-ManagingCardTextInner']/strong", null, false, '/^Hi\s+/ims') !== null);

        if ($this->AccountFields['Login2'] == 'South Africa') {
            $this->ParseSouthAfrica();

            return;
        }
        // refs #9131
        if (in_array($this->AccountFields['Login2'], $this->northAfrica)) {
            $this->ParseNorthAfrica();

            return;
        }
        // refs #11593
        if ($this->AccountFields['Login2'] == 'Saudi Arabia') {
            $this->ParseSaudiArabia();

            return;
        }
        // refs #13456
        if ($this->AccountFields['Login2'] == 'ישראל') {
            $this->ParseIsrael();

            return;
        }

        if ($this->AccountFields['Login2'] == 'Switzerland') {
            $this->ParseSwitzerland();

            return;
        }

        $this->skipOffers();
        $currentUrl = $this->http->currentUrl();

        // AccountID: 2860405
        // amex - new design (2017)
        if (strstr($currentUrl, 'https://global.americanexpress.com/dashboard')
            // AccountID: 2737365
            || stristr($currentUrl, 'https://online.americanexpress.com/myca/creditcenter/us/creditCenter.do?request_type=authreg_CreditCenter&Face=en_US&intlink=AcctMgmt-AutoRedir-CreditCenter')
            // AccountID: 2370875
            || stristr($currentUrl, 'https://online.americanexpress.com/myca/onlinepayment/us/v3/payment/inquiry.do?Face=en_US&intlink=AcctMgmt-AutoRedir-PBC&Face=en_US')
            // AccountID: 2068623
            || stristr($currentUrl, 'https://online.americanexpress.com/myca/wct/us/list.do?request_type=authreg_Statement')
            // AccountID: 1137530
            || stristr($currentUrl, 'https://global.americanexpress.com/myca/creditcenter/emea/UK/payments.do?request_type=authreg_CreditCenter&Face=en_GB')
            || stristr($currentUrl, 'https://online.americanexpress.com/myca/creditcenter/us/creditCenter.do?intlink=AcctMgmt-AutoRedir-CreditCenter&request_type=authreg_CreditCenter&Face=en_US')
            // AccountID: 2154710
            || stristr($currentUrl, 'https://global.americanexpress.com/myca/intl/myca/intl/acctsumm/canlac/accountSummary.do?request_type=&Face=es_AR')
            // AccountID: 2351855
            || ($currentUrl == 'https://www.americanexpress.com/change-country/')
            // AccountID: 165127, 1278482
            || stristr($currentUrl, 'https://global.americanexpress.com/payments/pay')
            // AccountID: 4220118
            || stristr($currentUrl, 'https://global.americanexpress.com/payment-options')
            // AccountID: 4965759
            || stristr($currentUrl, 'https://global.americanexpress.com/overview')
            // AccountID: 4076916
            || stristr($currentUrl, 'https://online.americanexpress.com/myca/usermgt/ipcfwd/action?request_type=auth_aauIPCRedirect&DestPage=https%3A%2F%2Fglobal.americanexpress.com%2Fmyca%2Fintl%2Facctsumm%2Fcanlac%2FaccountSummary.do%3Frequest_type%3D%26Face%3Des_AR&Face=es_AR')
            // AccountID: 4013972, 1536857
            || stristr($currentUrl, 'https://www.americanexpress.com/en-us/loans/businesslending/dashboard')
            // AccountID: 6984239
            || stristr($currentUrl, 'https://www.americanexpress.com/en-us/banking/personal/savings/dashboard')
        ) {
            $this->ParseUSA();

            return;
        }

        $this->Properties["SubAccounts"] = [];

        if ($this->ErrorCode == ACCOUNT_CHECKED) {
            return;
        }
        $this->ParseBlueSky();

        if (!$this->parsed()) {
            if ($this->ParseNonUS() && !$this->parsed()) {
                $this->logger->notice("Parse ended");
                // Mexico without balance
                $this->logger->notice("[Current url]: {$this->http->currentUrl()}");

                if ($this->http->currentUrl() == 'https://global.americanexpress.com/myca/logon/canlac/action' && $this->http->FindPreg("/request_type=&Face=es_MX&Face=es_MX/ims")) {
                    throw new CheckException(self::MESSAGE_NOT_FOUND_BALANCE, ACCOUNT_PROVIDER_ERROR);
                }
                // amex - new design (2017)
                if (strstr($this->http->currentUrl(), 'https://global.americanexpress.com/dashboard')) {
                    $this->ParseUSA();

                    return;
                }
                // retries
                if ($this->AccountFields['Login2'] == 'Brazil' && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    throw new CheckRetryNeededException(3, 5);
                }

                return;
            }
        }

        if ($this->ErrorCode == ACCOUNT_PROVIDER_ERROR) {
            return;
        }

        if ($this->parsed() && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetBalanceNA();
        }
        // if balance was not found
        if (
            (
                $loggedIn
                || strstr($this->http->currentUrl(), 'https://www.americanexpress.com/account/oauth/connect?client_id=')// AccountID: 6680829
            )
            && !$this->parsed()
        ) {
            throw new CheckException(self::MESSAGE_NOT_FOUND_BALANCE, ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function ParseSouthAfrica()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://api.nedsecure.co.za/nedbank/clients/clientdetails", $this->headersSouthAfrica);
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($response->FullNames ?? null));

        $this->http->GetURL("https://api.nedsecure.co.za/nedbank/accounts/v4/accounts", $this->headersSouthAfrica);
        $containers = $this->http->JsonLog();
        $hasRewards = false;

        foreach ($containers as $container) {
            switch ($container->ContainerName) {
                case 'Card':
                    foreach ($container->Accounts as $account) {
                        $code = $account->AccountNumber;
                        $this->AddDetectedCard([
                            "Code"            => 'amexSouthAfrica' . $code,
                            "DisplayName"     => $account->AccountName . " ({$code})",
                            "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                        ]);
                    }

                    break;

                case 'Rewards':
                    $hasRewards = true;

                    foreach ($container->Accounts as $account) {
                        $code = $account->AccountNumber ?? null;

                        if ($account->AccountName == 'Amex Membership') {
                            // Balance - Amex Membership
                            $this->SetBalance($account->AvailableBalance ?? null);
                            // Number
                            $this->SetProperty("Number", $code);

                            continue;
                        }
                        $this->AddSubAccount([
                            "Code"        => 'amexSouthAfrica' . $code,
                            "DisplayName" => $account->AccountName . " ({$code})",
                            "Balance"     => $account->AvailableBalance,
                            'Number'      => $code,
                        ]);
                    }

                    break;
            }// switch ($container->ContainerName)
        }// foreach ($containers as $container)

        // not a member, AccountID: 780156
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $hasRewards === false
            && !empty($this->Properties['DetectedCards'])
            && count($this->Properties['DetectedCards']) > 2
        ) {
            $this->SetBalanceNA();
        }
    }

    public function ParseNorthAfrica()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@name = 'userName'] | //p[@class = 'card-name']")));

        // open balance page
        if ($rewardURL = $this->http->FindSingleNode("(//a[contains(@id, 'wpf_action_ref_0onlsportletsAccountSummary')]/@href)[1]")) {
            $this->logger->notice("open balance page");
            $this->http->NormalizeURL($rewardURL);

            if (!$this->http->GetURL($rewardURL) && $this->http->Response['code'] == 0) {
                throw new CheckException("Sorry! We have encountered an unexpected error. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        } elseif ($rewardURL = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'membership']"), 0)) {
            $this->logger->notice("open balance page");

            if ($this->http->currentUrl() == 'https://secure.americanexpress.com.bh/online/home-page') {
                $this->http->GetURL("https://secure.americanexpress.com.bh/online/membership/rewards");
            }

//            $rewardURL->click();

            $this->waitForElement(WebDriverBy::xpath("//div[@class = 'card-mr-points']//h1 | //p[contains(@class, 'reward-points-label')]"), 10);
            $this->saveResponse();

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        }
        // Parse cards
        $balanceMR = 0;
        $cardNodes = $this->http->XPath->query("//div[@class = 'olsr_cnt_db_wide2']"); // | //div[contains(@class, 'dashboard-swipeCard')]
        $this->logger->debug("Total {$cardNodes->length} cards were found");

        for ($i = 0; $i < $cardNodes->length; $i++) {
            $card = $cardNodes->item($i);
            $displayName = $this->http->FindSingleNode(".//span[@id = 'CardType']", $card);
            $cardNum = $this->http->FindSingleNode(".//span[@name = 'CardNum'] | .//span[@class = 'number']", $card);
            $balance =
                $this->http->FindSingleNode(".//span[@name = 'MRPoints']", $card, true, "/Points\s*Balance\s*\:\s*([^<]+)/ims")
                ?? $this->http->FindSingleNode(".//div[@class = 'card-mr-points']//h1", $card)
            ;

            if (isset($balance)) {
                $balanceMR += $this->getFloat($balance);
                $this->AddSubAccount([
                    "Code"              => 'amex' . $cardNum,
                    "DisplayName"       => $displayName . ' ' . $cardNum,
                    "Balance"           => $balance,
                    "BalanceInTotalSum" => true,
                ]);
                // detected cards
                $this->detectedCards[] = [
                    "Code"            => 'amex' . $cardNum,
                    "DisplayName"     => $displayName . ' ' . $cardNum,
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ];
            } else {
                $this->logger->notice(">>> Skip card without balance");
                // detected cards
                $this->detectedCards[] = [
                    "Code"            => 'amex' . $cardNum,
                    "DisplayName"     => $displayName . ' ' . $cardNum,
                    "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                ];
            }
        }// for ($i = 0; $i < $cards->length; $i++)
        // detected cards
        if (!empty($this->detectedCards)) {
            $this->SetBalanceNA();
            $this->SetProperty("DetectedCards", $this->detectedCards);
        } elseif ($cardNodes->length == 0 && $this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name'])) {
            // open Card Summary page
            if ($rewardURL = $this->http->FindSingleNode("(//a[contains(text(), 'Card Summary')]/@href)[1]")) {
                $this->logger->notice("open card summary page");
                $this->http->NormalizeURL($rewardURL);
                $this->http->GetURL($rewardURL);
                $cards = $this->http->FindNodes("//select[@id = 'olsr_cnt_db_selcrd_sel']/option");
                $this->logger->debug(var_export($cards, true), ["pre" => 1]);

                if (count($cards) == 1 && isset($cards[0]) && $cards[0] == 'Select A Card') {
                    $this->SetBalanceNA();
                }
            }// if ($rewardURL = $this->http->FindSingleNode("(//a[contains(text(), 'Card Summary')]/@href)[1]"))
            else {
                // Kuwait
                $this->SetBalance($this->http->FindSingleNode("//div[@class = 'card-mr-points']//h1 | //p[contains(@class, 'reward-points-label')]"));
            }
        }// elseif ($cardNodes->length == 0 && $this->ErrorCode == ACCOUNT_ENGINE_ERROR && !empty($this->Properties['Name']))

        // refs #16147
        $this->logger->info('Summary of MR subAccounts', ['Header' => 3]);

        if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) != 1) {
            $this->logger->debug("Summary of MR subAccounts: {$balanceMR}");
            $this->SetProperty("CombineSubAccounts", false);

            if (!empty($balanceMR)) {
                $this->SetBalance($balanceMR);
            }// if (!empty($balanceMR))
        }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) != 1)
    }

    public function ParseSaudiArabia()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[not(contains(@style,"visibility: hidden;"))]/div[contains(text(), "Welcome")]', null, true, "/Welcome\s*([^<]+)/ims")));
        // parse all cards
        $cards = $this->http->XPath->query("//div[contains(@class, 'card-balance')]");
        $this->logger->debug("Total {$cards->length} cards were found");
        $subAccounts = [];
//        $balanceMR = 0;
        $balanceMR = false;

        for ($i = 0; $i < $cards->length; $i++) {
            $this->logger->debug("card: " . $i);
            $card = $cards->item($i);
            // Card name
            $title = $this->http->FindSingleNode('.//div[contains(@class, "text-1xl font-weight-semibold") and span]/preceding-sibling::p/span', $card);
            // Card number
            $code = $this->http->FindSingleNode('.//div[contains(@class, "text-1xl font-weight-semibold")]/span', $card);
            // Balance - Membership Rewards Balance
            $balance = $this->http->FindSingleNode('.//div[contains(@class, "reward-point")]/span', $card, false, "/([\d\,\.\-]+)/ims");
            // for future improvements
            $closed = false;

            if (isset($title) && isset($balance) && isset($code) && !$closed) {
                $this->logger->debug("code: $code, title: $title, balance: $balance");

                if (!$balanceMR) {
                    $balanceMR = true;
                    $subAccounts[] = [
                        "Code"        => 'amexSaudiArabia' . $code,
                        "DisplayName" => $title,
                        "Balance"     => $balance,
                    ];
                }
//                $balanceMR += $this->getFloat($balance);
//                $subAccounts[] = array(
//                    "Code"        => 'amexSaudiArabia'.$code,
//                    "DisplayName" => $title,
//                    "Balance"     => $balance,
//                );
                $this->AddDetectedCard([
                    "Code"            => 'amexSaudiArabia' . $code,
                    "DisplayName"     => $title,
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ]);
            }// if (isset($title) && isset($balance) && isset($code) && !$closed)
            else {
                // account is currently closed
                if (isset($title, $code)) {
                    if ($closed) {
                        $this->AddDetectedCard([
                            "Code"            => 'amexSaudiArabia' . $code,
                            "DisplayName"     => $title,
                            "CardDescription" => C_CARD_DESC_CLOSED,
                        ]);
                    } else {
                        $this->AddDetectedCard([
                            "Code"            => 'amexSaudiArabia' . $code,
                            "DisplayName"     => $title,
                            "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                        ]);
                    }
                }// if (isset($title, $code))
            }
        }// for ($n = 0; $n < $rows->length; $n++)

        if (!empty($subAccounts)
            || (isset($this->Properties['DetectedCards']) && count($this->Properties['DetectedCards']) > 0)) {
            $this->SetBalanceNA();
            $this->SetProperty("SubAccounts", $subAccounts);
        }
        // logout
        $this->logger->debug("Logout");
        sleep(3);
        $logout = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class, "logout")]'), 0);

        if ($logout) {
            $logout->click();
            $logoutPopupBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Logout")]'), 3);
            $this->saveResponse();

            if ($logoutPopupBtn) {
                $logoutPopupBtn->click();
            }
        }

        // refs #16147
//        $this->logger->info('Summary of MR subAccounts', ['Header' => 3]);
//        if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) != 1) {
//            $this->logger->debug("Summary of MR subAccounts: {$balanceMR}");
//            $this->SetProperty("CombineSubAccounts", false);
//            if (!empty($balanceMR)) {
//                $this->SetBalance($balanceMR);
//                if (isset($this->Properties['SubAccounts'])) {
//                    $countSubAccounts = count($this->Properties['SubAccounts']);
//                    $this->logger->debug("count subAccounts: $countSubAccounts");
//                    for ($i = 0; $i < $countSubAccounts; $i++)
//                        $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
//                }// if (isset($this->Properties['SubAccounts']))
//            }// if (!empty($balanceMR))
//        }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) != 1)
    }

    public function ParseIsrael()
    {
        $this->logger->notice(__METHOD__);
        $this->http->setDefaultHeader("Accept", "application/json, text/plain, */*");
        $this->http->GetURL("https://he.americanexpress.co.il/services/ProxyRequestHandler.ashx?reqName=ShowCustomerData_101&isCustomerTypeCorporate=false");
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'ShowCustomerData_101Bean');
        // Name
        $this->SetProperty("Name", beautifulName(ArrayVal($data, 'firstNameLatin') . " " . ArrayVal($data, 'lastNameLatin')));

//        $this->http->GetURL("https://he.americanexpress.co.il/services/ProxyRequestHandler.ashx?reqName=CardsList_102Digital&userGuid=0123b8ad-5304-429e-9de8-63c81f2f3d96");
        $this->http->GetURL("https://he.americanexpress.co.il/services/ProxyRequestHandler.ashx?reqName=CardsList_102Digital&userGuid=NOSESSION_" . time() . date("B"));
        $response = $this->http->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'CardsList_102DigitalBean');
        $accounts = ArrayVal($data, 'Table1', []);
        $this->logger->debug("Total " . count($accounts) . " cards were found");

        foreach ($accounts as $account) {
            $displayName = ArrayVal($account, 'cardName', null);
            $code = ArrayVal($account, 'cardNumber', null);
            $balance = ArrayVal($account, 'bikoretSodi', null);

            $subAccount = $detectedCard = [
                'Code'        => 'amex' . $code,
                'DisplayName' => Html::cleanXMLValue($displayName) . " ({$code})",
                'Number'      => $code,
            ];
            $this->logger->debug(var_export($subAccount, true), ['pre' => true]);
//            if (isset($balance)) {
//                $detectedCard['CardDescription'] = C_CARD_DESC_ACTIVE;
//                $subAccount['Balance'] = $balance;
//                $this->AddSubAccount($subAccount);
//            }
//            else
            $detectedCard['CardDescription'] = C_CARD_DESC_DO_NOT_EARN;
            $this->detectedCards[] = $detectedCard;
        }// foreach ($accounts as $account)

        // detected cards
        if (!empty($this->detectedCards)) {
            $this->SetBalanceNA();
            $this->SetProperty("DetectedCards", $this->detectedCards);
        }// if (!empty($this->detectedCards))
    }

    public function ParseSwitzerland()
    {
        $this->logger->notice(__METHOD__);
        // Name
        $this->SetProperty("Name", $this->getNameForParseSwitzerland());
        // Balance - points
        $this->SetBalance($this->getBalanceForParseSwitzerland());
    }

    public function ParseUSA()
    {
        $this->logger->notice(__METHOD__);
        /*
         * Loading Error
         * Sorry, we are unable to load this page at this time. Please try again later.
         *
         * If you need immediate assistance, please call the number on the back of your Card.
        */
        if (
            $this->http->currentUrl() == "https://global.americanexpress.com/dashboard/error"
        ) {
            throw new CheckException("Sorry, we are unable to display this account right now. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        // provider bug fix
        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            && stristr($this->http->currentUrl(), 'https://global.americanexpress.com/payments/pay?account_key=')
        ) {
            sleep(3);
            $this->sendNotification("try to fix provider bug");
            $this->logger->notice("try to fix provider bug");
            $this->http->GetURL($this->http->currentUrl());
        }

        $axp_loyalty_summary =
            $this->http->FindPreg("/axp-loyalty-summary.\",\s*.\"([\d\.]+).\"/")
            ?? $this->http->FindPreg("/axp-loyalty-summary\/\s*([\d\.]+)\/\"/")
        ;
        $this->lang =
            $this->http->FindSingleNode("//html/@lang")
            ?? $this->http->FindPreg("/locale_preference.\",\s*.\"([^\"]+).\"/")
        ;
        $app =
            $this->http->FindPreg("/cdaas\/(\w+-app)\/modules/")
            ?? "axp-app"
        ;
        $this->logger->notice("axp-loyalty-summary: {$axp_loyalty_summary}");
        $this->logger->notice("lang: {$this->lang}");

        if ($axp_loyalty_summary && $this->lang) {
            $this->http->GetURL("https://www.aexp-static.com/cdaas/{$app}/modules/axp-loyalty-summary/{$axp_loyalty_summary}/" . strtolower($this->lang) . "/axp-loyalty-summary.json");
        }
        $loyaltySummary = $this->http->JsonLog(null, 0, true);
        $tiers = ArrayVal($loyaltySummary, 'tiers', []);
//        $this->logger->debug(var_export($tiers, true), ['pre' => true]);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/member");
        $this->http->RetryCount = 2;
//        $this->http->GetURL("https://global.americanexpress.com/account-data/v1/member");
        $response = $this->http->JsonLog(null, 3, true);
        $accounts = ArrayVal($response, 'accounts', []);

        // do not duplicate amex cards with MR balance
        $linked_card = [];
        $primary_card = [];
        // refs #20382, do not duplicate amex cards with MR balance for Australia
        $collectedCards = [];

        $benefitSubAccounts = [];
//        $amexOffers = [];
        $balanceMR = 0;
        $history = [];

        $countOfAccounts = count($accounts);
        $this->logger->debug("Total {$countOfAccounts} cards were found");

        if ($countOfAccounts === 0) {
            // AccountID: 2669174, 585977, 509092, 4853981, 4100291, 3819747, 3807321
            if ($this->http->FindPreg("/^\{\"accounts\":\[\],\"last_login\":\{\"timestamp\":\d+\}\}$/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/errors\":\[\{\"error_code\":\"component_error\",\"message\":\"ErrorCode = IC_SYSTEM_SSI_ERROR Message = Failure during logon: Unable to connect to SSI : ssiprod.web.ipc.us.aexp.com\"/")) {
                throw new CheckException("Sorry, your rewards information is unavailable at this time. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        if ($countOfAccounts > 30) {
            $this->increaseMaxRequests();
        }

        foreach ($accounts as $account) {
            $account_token = ArrayVal($account, 'account_token', null);
            $account_key = ArrayVal($account, 'account_key', null);

            $displayName = ArrayVal($account['product'], 'description', null);
            $code = ArrayVal($account['account'], 'display_account_number', null);
            // Name
            if (isset($account['profile'])) {
                $name = ArrayVal($account['profile'], 'embossed_name', null);

                if (!empty($name)) {
                    $this->SetProperty("Name", beautifulName($name));
                }
            }// if (isset($account['profile']))
            else {
                $this->logger->notice("Name not found");
            }

            $subAccount = $detectedCard = [
                'Code'        => 'amex' . $account_token . $code,
                'DisplayName' => $displayName . " (-{$code})",
                'Number'      => $code,
            ];
            $this->logger->debug(var_export($subAccount, true), ['pre' => true]);
            $this->logger->info($subAccount['DisplayName'], ['Header' => 3]);

            if ($this->http->ResponseNumber > 350) {
                $this->increaseMaxRequests();
            }

            if ((isset($account['status']['card_status'][0]) && (in_array($account['status']['card_status'][0], ['Cancelled', 'Canceled'])))
                || (isset($account['status']['account_status'][0]) && (in_array($account['status']['account_status'][0], ['Cancelled', 'Canceled'])))) {
                // detected cards
                $detectedCard['CardDescription'] = C_CARD_DESC_CANCELLED;
                $this->detectedCards[] = $detectedCard;

                $this->logger->notice(">>> Skip cancelled card");
                $this->SetBalanceNA();

                continue;
            }

            $product = ArrayVal($account, 'product', null);
            $line_of_business_type = ArrayVal($product, 'line_of_business_type', null);

            // get Balance info
            if ($account_token) {
                $headers = [
                    "Accept"          => "*/*",
                    "Referer"         => "https://global.americanexpress.com/dashboard",
                    "Accept-Encoding" => "gzip, deflate, br",
                ];
                $this->http->RetryCount = 0;

                if (count($accounts) == 1) {
                    $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/arbitration", array_merge(["account_token" => $account_token], $headers));

                    if ($this->http->Response['code'] == 404) {
                        $postHeaders = [
                            "Accept"          => "application/json",
                            "Accept-Language" => "en-US,en;q=0.5",
                            "Accept-Encoding" => "gzip, deflate, br",
                            "Content-Type"    => "application/json",
                            "ce-source"       => "WEB",
                            "Origin"          => "https://global.americanexpress.com",
                        ];
                        $this->http->PostURL("https://functions.americanexpress.com/ReadArbitrationForAccounts.v1", "{\"accountTokens\":[\"{$account_token}\"],\"productClass\":\"\"}", $postHeaders);
                    } else {
                        $this->http->GetURL("https://global.americanexpress.com/api/servicing/v2/layout?view=%2Fdashboard", array_merge(["account_token" => $account_token], $headers));
                    }
                }// if (count($accounts) == 1)

                $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/loyalty/details", array_merge(["account_tokens" => $account_token], $headers));
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog(null, 3, true);

                /*
                if (!$response) {
                    if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re sorry. We are momentarily experiencing technical difficulties. We are working on bringing our services back online")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }
                }
                */

                // migration issue on some accounts
                if ($this->http->Response['code'] == 401) {
                    $errorCode = ArrayVal(ArrayVal($response, 'errors', [])[0], 'error_code', null);
                    $this->logger->debug("error_code: {$errorCode}");

                    if (
                        $errorCode == 'invalid_gk_cookie'
                    ) {
                        $this->http->setCookie("PreGatekeeperCookie", null);
                        $this->http->GetURL("https://global.americanexpress.com/myca/scwdg/canlac/widgethandler?widgetName=SessionTimeout&request_type=SessionTimeout&json=%7Btype:%27SessionTimeout%27,signal:1%7D&cache=no-cache&Face={$this->lang}}");
                        $this->http->GetURL("https://global.americanexpress.com/myca/reauth/intl/riskScoringController.do?request_type=authunreg_riskScoring&Details=true&Face={$this->lang}&destPage=https%3A%2F%2Fglobal.americanexpress.com%2Fdashboard", ['Referer' => "https://global.americanexpress.com/dashboard"]);
                        $this->http->RetryCount = 0;
                        $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/loyalty/details", array_merge(["account_tokens" => $account_token], $headers));
                        $this->http->RetryCount = 2;
                        $response = $this->http->JsonLog(null, 3, true);
                    }
                }// if ($this->http->Response['code'] == 401)

                unset($balance);
                unset($account_number);
                unset($program_code);
                unset($program_tier);
                unset($loyalty_linked_cards_list);
                unset($linked_account);
                unset($primary_account);
                unset($balance_account_key);

                if (isset($response[0])) {
                    $balance = ArrayVal($response[0], 'balance', null);
                    $account_number = ArrayVal($response[0], 'account_number', null);
                    $program_code = ArrayVal($response[0], 'program_code', null);
                    $program_tier = ArrayVal($response[0], 'program_tier', null);
                    $cash_indicator = ArrayVal($response[0], 'cash_indicator', null);

                    // fixes for linked cards

                    // new
                    $loyalty_linked_cards_list = ArrayVal($response[0], 'loyalty_linked_cards_list', []);
                    // deprecated
                    $linked_account = ArrayVal($response[0], 'linked_account', null);
                    $primary_account = ArrayVal($linked_account, 'primary_account', null);

                    // refs #20382, do not duplicate amex cards with MR balance for Australia
                    $balance_account_key = ArrayVal($response[0], 'account_key', null);
                }
                // https://redmine.awardwallet.com/issues/16147#note-15
                if (!empty($account_number) && !in_array($account_number, ['N/A'])) {
                    $subAccount['Number'] = $account_number;
                }

                $originalCard = $subAccount;
                $originalCard['IsHidden'] = true;
                $originalCard['Balance'] = null;

                if (isset($balance)) {
                    $this->logger->notice("balance was found");

                    $this->State[$account_token] = [
                        "HasBalance" => true,
                        "Balance"    => $balance,
                        "Time"       => time(),
                    ];

                    if (isset($program_code, $account_number) && $program_code != 'cobrand' /*AccountID: 4501448 */) {
                        $this->logger->notice("Detect card type by program_code");

                        if (isset($cash_indicator) && $cash_indicator) {
                            $subAccount['Currency'] = "$";

                            // refs #21877
                            if ($this->lang == 'en-GB') {
                                $subAccount['Currency'] = "&pound;";
                            }
                        }

                        switch ($program_code) {
                            case 'HLTN':
                                $this->logger->notice(">>> Skip Hilton card");
                                $detectedCard['CardDescription'] = C_CARD_DESC_HHONORS;

                                // refs #21039, A progress towards a free Hilton night
                                $benefitSubAccounts = array_merge($benefitSubAccounts, $this->progressRewards($displayName, $code, $account_token));

                                // refs #20852
                                $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
                                $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
                                $this->AddSubAccount($originalCard);

                                break;

                            case 'DLTA':
                                $subAccount['ProviderAccountNumber'] = $account_number;
                                $subAccount['ProviderCode'] = 'delta';
                                $subAccount['Balance'] = $balance;
                                $detectedCard['CardDescription'] = C_CARD_DESC_DELTA;
                                $this->AddSubAccount($subAccount);

                                // refs #22821, tracker Miles Headstart
                                $benefitSubAccounts = array_merge($benefitSubAccounts, $this->progressRewards($displayName, $code, $account_token));

                                // refs #20852
                                $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
                                $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
                                $this->AddSubAccount($originalCard);

                                break;

                            case 'STRW':// Starpoints® - YTD Starpoints® Earned
                                $this->logger->notice(">>> Skip Starwood card");
                                $detectedCard['CardDescription'] = C_CARD_DESC_MARRIOTT;

                                // refs #20852
                                $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
                                $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
                                $this->AddSubAccount($originalCard);

                                break;

                            case 'RRSB':// refs #14496
                            case 'RRSC':
                            case 'RRSO':
                            case 'RRSE':// YTD Cash Rebate Earned
                                $this->logger->notice(">>> Skip SimplyCash® card");
                                $detectedCard['CardDescription'] = C_CARD_DESC_DO_NOT_EARN;

                                // refs #20852
                                $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
                                $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
                                $this->AddSubAccount($originalCard);

                                break;

                            case 'AMZN':// Amazon Business Prime Card / Amazon Rewards Points
                            case 'BSKY':// Blue Sky
                            case 'RRBB':// Blue Business Cash(TM)
                            case 'CCRB':// Reward Dollars
                            case 'RROS':// Cash Rebate
                            case 'RROP':// Cash Rebate
                            case 'REBATE':// Platinum Cashback Credit Card
                            case 'LOWE':// Lowe's Business Rewards
                            case 'PLEN':// Plenti Rewards Program
                            case 'CLEA':// CLEAR Rewards
                            case 'CSIC':// Cash Back Rewards
                            case 'FRPS':// FreedomPass(SM) Points / FreedomPass Program
                            case 'USMR':// Membership Rewards
                            case 'USCM':// Membership Rewards Corporate
                            case 'rewards':// American Express® Platinum Charge Card // AccountID: 4635822
                            case 'REWARDS':// American Express® Platinum Charge Card // AccountID: 4635822
                            case 'MSBC':// Morgan Stanley Blue Cash® // AccountID: 3298683
                                $detectedCard['CardDescription'] = C_CARD_DESC_ACTIVE;

                                // fixes for linked cards
                                if (!empty($loyalty_linked_cards_list)) {
                                    foreach ($loyalty_linked_cards_list as $loyalty_linked_card) {
                                        if ($account_token == $loyalty_linked_card['account_token']
                                        && $loyalty_linked_card['primary_card'] == false) {
                                            $this->logger->notice("Skip linked card");
                                            $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $subAccount['Number']);

                                            $subAccount['Balance'] = $balance;

                                            // refs #16167
                                            if (in_array($program_code, ['USMR', 'USCM'])) {
                                                $historyCode = "amex" . $account_token . $subAccount['Number'];
                                                $originalCard['HistoryRows'] = $this->parseSubAccHistory($historyCode, array_merge(["account_token" => $account_token], $headers), $originalCard['Code'], $originalCard['DisplayName']);
                                            }

                                            $linked_card[$account_number] = $subAccount;
                                            $this->AddSubAccount($originalCard);

                                            break;
                                        }// if ($account_token == $loyalty_linked_card['account_token'] ...
                                        elseif ($account_token == $loyalty_linked_card['account_token']
                                        && $loyalty_linked_card['primary_card'] == true) {
                                            $primary_card[$account_number] = $subAccount;
                                        }
                                    }
                                }// foreach ($loyalty_linked_cards_list as $loyalty_linked_card)

                                // refs #20382, do not duplicate amex cards with MR balance for Australia
                                if (isset($balance_account_key)) {
                                    $this->logger->debug(var_export($collectedCards, true), ['pre' => true]);

                                    if (
                                        in_array($this->AccountFields['Login2'], [
                                            'Australia',
                                            'United Kingdom',
                                            'India',
                                            'Deutschland', //refs #22379
                                        ])
                                        && (
                                            in_array($balance_account_key, $collectedCards)
                                            // https://redmine.awardwallet.com/issues/21658#note-18
                                            || in_array($program_tier . '|' . $balance . "|" . $program_code, $collectedCards)
                                        )
                                    ) {
                                        $this->logger->notice("Skip linked card, do not collect balance for {$subAccount["DisplayName"]}");

                                        $detectedCard['CardDescription'] = C_CARD_DESC_LINKED;
                                        $this->detectedCards[] = $detectedCard;

                                        continue 2;
                                    }// if ($this->AccountFields['Login'] == 'Australia' && in_array($account_key, $collectedCards))

                                    $collectedCards[] = $balance_account_key;
                                    // https://redmine.awardwallet.com/issues/21658#note-18
                                    $collectedCards[] = $program_tier . '|' . $balance . "|" . $program_code;
                                }// if (isset($balance_account_key))

                                if ($detectedCard['CardDescription'] == C_CARD_DESC_ACTIVE) {
                                    $subAccount['Balance'] = $balance;

                                    // refs #16167
                                    if (in_array($program_code, ['USMR', 'USCM'])) {
                                        $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $subAccount['Number']);
                                        $subAccount["Code"] = "amex" . $account_token . $subAccount['Number'];
                                        $subAccount["DisplayName"] = "Membership Rewards ({$subAccount['Number']})";
                                        $originalCard['HistoryRows'] = $this->parseSubAccHistory($subAccount['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'], $originalCard['DisplayName']);
                                    } else {
                                        // refs #20852
                                        $subAccount['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'], $originalCard['DisplayName']);
                                    }

                                    // refs #19104 double balance
                                    $skipBalance = false;

                                    if (
                                        isset($loyalty_linked_cards_list)
                                        && in_array($program_code, ['USMR'])
                                        && (
                                            $loyalty_linked_cards_list === []
                                            && isset($primary_card[$subAccount['Number']])
                                            // refs #21658
//                                            || ($this->AccountFields['Login'] == 'surfman49' && $subAccount['Code'] == 'amexCZG19YSNJ3CXKYX1M19849285')
                                        )
                                    ) {
                                        $this->logger->notice("[Fixed double balance]: MR account #{$subAccount['Number']}, do not collect balance");
                                        $skipBalance = true;
                                    } else {
                                        if (in_array($program_code, ['USMR', 'USCM'])) {
                                            // refs #20851 - Pending Points
                                            $data = [
                                                "accountToken"      => $account_token,
                                                "period"            => "P6M",
                                                "transactionStatus" => ["PENDING", "QUALIFIED"],
                                                "category"          => ["REWARD"],
                                                "summariesBy"       => ["category", "transactionStatus"],
                                                "periodType"        => "CALENDAR_PERIOD",
                                                "summariesFor"      => "LOYALTY_ACCOUNT",
                                            ];
                                            $this->http->RetryCount = 0;
                                            $this->http->PostURL("https://functions.americanexpress.com/ReadLoyaltyTransactionSummaries.v1", json_encode($data));
                                            $this->http->RetryCount = 2;
                                            $responseTransactionSummaries = $this->http->JsonLog();

                                            if (isset($responseTransactionSummaries->summary)) {
                                                foreach ($responseTransactionSummaries->summary as $item) {
                                                    if ($item->summariesBy->transactionStatus == 'QUALIFIED') {
                                                        $subAccount['PendingPoints'] = number_format($item->totalAmount->amount->value);

                                                        break;
                                                    }// if ($item->summariesBy->transactionStatus == 'QUALIFIED')
                                                }// foreach ($responseTransactionSummaries->summary as $item)
                                            }// if (isset($responseTransactionSummaries->summary))
                                        }

                                        $this->AddSubAccount($subAccount);
                                    }
                                    $this->AddSubAccount($originalCard);

                                    if (in_array($program_code, [
                                        'USMR',
                                        'USCM',
                                        'REWARDS', // MR in UK, AccountID: 4161101
                                    ])
                                        && $skipBalance === false
                                    ) {
                                        $balanceMR += floatval($balance);
                                    } else {
                                        if (
                                            $skipBalance === false
                                            && !in_array($program_code, [
                                                'CCRB',
                                                'BSKY',
                                                'CSIC',
                                                'CLEA',
                                                'LOWE',
                                                'RROS',
                                                'FRPS',
                                                'RROP',
                                                'AMZN',
                                                'rewards',
                                                'REBATE',
                                                'RRBB',
                                                'MSBC',
                                            ])
                                        ) {
                                            $this->sendNotification("refs #16147. Need to check summary (program_code: {$program_code})", "awardwallet");
                                        }
                                    }
                                }// if ($detectedCard['CardDescription'] == C_CARD_DESC_ACTIVE)

                                break;

                            default:
                                $this->sendNotification("unknown program code: {$program_code}");
                                $detectedCard['CardDescription'] = C_CARD_DESC_ACTIVE;
                                $subAccount['Balance'] = $balance;
                                $this->AddSubAccount($subAccount);

                                break;
                        }// switch ($program_code)
                    }// if (isset($program_code, $account_number))
                    else {
                        $this->logger->notice("Detect card type by program_tier");
                        $detectedCard['CardDescription'] = C_CARD_DESC_ACTIVE;
                        $subAccount['Balance'] = $balance;

                        if (isset($program_tier, $tiers[$program_tier]['title']['data']['text'])) {
                            switch ($tiers[$program_tier]['title']['data']['text']) {
                                case 'Asia Miles Account':
                                case 'Total Miles Transferred':
                                case '「亞洲萬里通」里數':
                                case '已轉移之「亞洲萬里通」里數':
                                case '待轉換里數':
                                    $this->logger->notice(">>> Skip Asia Miles card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Cathay Pacific (Asia Miles)', 35], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'ポイント数':// ANA American Express · Super Flyers Gold Card
                                case 'ポイント数（移行コース未登録)':// ANA AMERICAN EXPRESS CARD
                                    $this->logger->notice(">>> Skip ANA card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['All Nippon Airways (ANA Mileage Club)', 92], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'デルタ スカイマイル':
                                    $subAccount['ProviderAccountNumber'] = $account_number;
                                    $subAccount['ProviderCode'] = 'delta';
                                    $subAccount['Balance'] = $balance;
                                    $detectedCard['CardDescription'] = C_CARD_DESC_DELTA;
                                    $this->AddSubAccount($subAccount);

                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'Total Airpoints Dollars<sup>™</sup> Earned to Date':
                                    $this->logger->notice(">>> Skip Airpoints card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Air New Zealand (Airpoints)', 258], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'EuroBonus':
                                case "Total SAS EuroBonus saldo":
                                    $this->logger->notice(">>> Skip EuroBonus card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['SAS (EuroBonus)', 85], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'Flying Blue Award Miles':
                                case 'Vos Miles du mois avec votre carte American Express':
                                case 'Flying Blue Miles':
                                case 'Votre compteur de Miles Flying Blue':
                                    $this->logger->notice(">>> Skip Flying Blue card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Air France (Flying Blue)', 44], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history (Flying Blue) // RR");

                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'JPMiles':
                                    $this->logger->notice(">>> Skip JPMiles card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Jet Airways (JetPrivilege)', 51], C_CARD_DESC_UNIVERSAL);

                                    break;

                                case 'Harrods Reward Points to be transferred':
                                    $this->logger->notice(">>> Skip Harrods card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Harrods (Rewards)', 370], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'KrisFlyer Miles':
                                    $this->logger->notice(">>> Skip KrisFlyer Miles card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Singapore Airlines (KrisFlyer)', 71], C_CARD_DESC_UNIVERSAL);

//                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'Punktestand':
                                case 'Puntos PAYBACK<sup>®</sup>':
                                    $this->logger->notice(">>> Skip PAYBACK card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['PAYBACK points', 263], C_CARD_DESC_UNIVERSAL);
//                                $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'Nectar Points to be transferred':
                                    $this->logger->notice(">>> Skip Nectar card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Nectar', 151], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history (Nectar) // RR");

                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'Marriott Bonvoy Points':
                                    $this->logger->notice(">>> Skip Marriott Bonvoy Points");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Marriott Bonvoy', 17], C_CARD_DESC_UNIVERSAL);

                                    break;

                                case 'Puntos Premier':
                                case 'Puntos Aeroméxico Rewards':
                                    $this->logger->notice(">>> Skip Premier card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Aeromexico (Club Premier)', 96], C_CARD_DESC_UNIVERSAL);
                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'Starpoints collected':
                                case 'スターポイント':
                                case 'ポイント':
                                case 'Points collected':// Starwood Preferred Guest Credit Card from American Express
                                    $this->logger->notice(">>> Skip Starwood card");
                                    $detectedCard['CardDescription'] = C_CARD_DESC_MARRIOTT;

                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'Total Altitude Points Earned to Date':
                                case 'Points Aéroplan':
                                case 'Points Aéroplan<sup>MD*</sup>':
                                case 'Aeroplan Points':
                                    $this->logger->notice(">>> Skip Altitude card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Air Canada (Aeroplan)', 2], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'Total Qantas Points Earned to Date':
                                case 'Qantas Frequent Flyer Points Earned this Statement Period':
                                case 'Qantas Points Earned this Statement Period':
                                    $this->logger->notice(">>> Skip Qantas card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Qantas (Frequent Flyer)', 33], C_CARD_DESC_UNIVERSAL);

                                    // refs #20852
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'] . 'Analysis', $originalCard['DisplayName'], true);
//                                    $originalCard['Code'] = $originalCard['Code'] . 'Analysis';
//                                    $this->AddSubAccount($originalCard);

                                    break;

                                case 'Total Velocity Points Earned to Date':
                                case 'Velocity Frequent Flyer Points Earned this Statement Period':
                                    $this->logger->notice(">>> Skip Velocity card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Virgin Australia (Velocity Frequent Flyer)', 93], C_CARD_DESC_UNIVERSAL);
//                                $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'Miles de prime':
                                    $this->logger->notice(">>> Skip Brussels Airlines card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Brussels Airlines (LOOPs)', 768], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'Use PAYBACK Points':
                                    $this->logger->notice(">>> Skip MakeMyTrip card");
                                    $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['MakeMyTrip', 1115], C_CARD_DESC_UNIVERSAL);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'Membership Rewards':
                                case 'Membership Rewards®':
                                case 'Membership Rewards<sup>&reg;</sup>':
                                case 'Membership Rewards<sup>®</sup>':
                                case 'Membership Rewards<sup>®</sup> Points':
                                case 'Membership Rewards Points':
                                case 'Membership Rewards<sup>®</sup> Punkte':
                                case 'Membership Rewards<sup>®</sup> balance':
                                case 'Membership Rewards with PAYBACK<sup>®</sup>':
                                case 'Punti Membership Rewards®':
                                case 'Saldo de puntos':
                                case 'David Jones Membership Rewards<sup>®</sup>':
                                case 'メンバーシップ・リワード<sup>®</sup>':
                                case 'メンバーシップ・リワード<sup>®</sup>・プラス登録済':
                                case 'Points-privilègesᴹᴰ':
                                case '美國運通積分計劃':
                                case 'Membership Rewards<sup>®</sup> punten saldo':
                                case 'Awardmijlen':
                                case 'Corporate Membership Rewards<sup>®</sup> balance':
                                case 'Solde de points Membership Rewards<sup>®</sup>':
                                case 'Royal Orchid Plus Miles Earned':
                                case 'ไมล์สะสมรอยัล ออร์คิด พลัส':
                                case 'ยอดคะแนนสะสมเม็มเบอร์ชิป รีวอร์ด<sup>®</sup>':
                                    $balanceMR += floatval($balance);

                                    // refs #16167
                                    $subAccount["Code"] = "amex" . $account_token . $subAccount['Number'];
                                    $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $subAccount['Number']);
                                    $subAccount["DisplayName"] = "Membership Rewards ({$subAccount['Number']})";
//                                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($subAccount['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'], $originalCard['DisplayName']);
//                                    $this->AddSubAccount($originalCard);

                                    $this->AddSubAccount($subAccount);

                                    break;

                                case 'Cashback earned':// Cashback
                                case 'Blue Cashback earned':// Blue Credit Card
                                    $subAccount['Currency'] = "&pound;";
                                    $this->AddSubAccount($subAccount);
//                                    $this->sendNotification("refs #20852 need to check history // RR");

                                    break;

                                case 'TrueEarnings Cashback balance':// Costco TrueEarnings
                                case 'Cash Back Balance':// SimplyCash
                                case 'Blue Sky<sup>®</sup> Points':// Blue Sky
                                case 'Avios to be transferred':// British Airways
                                case 'STAR$<sup>®</sup>':// CapitaCard
                                case 'REDMoney balance':// American Express® RED
                                case 'Cashback':// Cashback
                                case 'American Express® Blue Cashback Card':// Cashback
                                case '消費回贈':// Blue Cash Credit Card from American Express
                                case 'Solde de remise en argent':// Blue Cash Credit Card from American Express
                                case 'Total HighFlyer Points Earned to Date':// American Express® Singapore Airlines Business Credit Card   // AccountID: 4725286
                                    // refs #20852
//                                    $subAccount['HistoryRows'] = $this->parseSubAccHistory($subAccount['Code'], array_merge(["account_token" => $account_token], $headers), $subAccount['Code'], $subAccount['DisplayName'], true);
//
                                    $this->AddSubAccount($subAccount);

                                    break;

                                default:
                                    $this->logger->notice(">>> Unknown card: {$tiers[$program_tier]['title']['data']['text']}");
                                    $this->logger->info("Unknown card: {$tiers[$program_tier]['title']['data']['text']}", ['Header' => 3]);
                                    $this->sendNotification("refs #16147. Unknown card: {$tiers[$program_tier]['title']['data']['text']}");

                                    $this->AddSubAccount($subAccount);

                                    break;
                            }
                        }// if (isset($program_tier, $tiers[$program_tier]['title']['data']['text']))
                        /*
                        else {
                            $this->sendNotification("refs #16147. Please check this account, something went wrong");
                            $this->AddSubAccount($subAccount);
                        }
                        */
                    }
                }// if (isset($balance))
                else {
                    $this->logger->notice("balance not found");

                    // refs #16593 Amex - returns different results
                    if (isset($this->State[$account_token])) {
                        $this->logger->debug("[Attempt]: {$this->attempt}");
                        $this->logger->debug(var_export($this->State[$account_token], true), ['pre' => true]);

                        /*
                        if ($this->State[$account_token]['Time'] > strtotime("-3 month") && $this->attempt < 3) {
                            throw new CheckRetryNeededException(4);
                        }
                        */
                    }// if (isset($this->State[$account_token]))

                    // (AccountID: 1204368, 3232569)
                    if ((isset($line_of_business_type) && in_array($line_of_business_type, ['COMPANY_CARD', 'CORPORATE']) && count($accounts) == 1)
                        || $this->http->FindPreg("/\"enrollment_indicator\":false/")
                        // AccountID: 2532057, 3623307, 273407, 4903979, 3985014, 3999944
                        || ($this->http->FindPreg("/\"program_tier\":\"(?:B|CB1|M10|M20|SB|Z|SC|CS|APC|APL|ACC)\"/") && isset($account_number))
                        // AccountID: 4775699, 6261542
                        || ($this->http->FindPreg("/\"program_tier\":\"(?:Z02|Z03)\"/") && $this->http->FindPreg("/\"enrollment_indicator\":true/"))
                        // United Kingdom (AccountID: 2091651, 3726567, 3213418)
                        || $this->http->FindPreg("/\{\"code\":502,\"message\":\"Invalid response from downstream system.\"\}/")
                        || $this->http->FindPreg("/\{\"errors\":\[\{\"error_code\":\"access_denied_tas\",\"message\":\"ErrorCode = IC_UE_POLICY_EXECUTION_FAILED Message = TP User not allowed to access this URI GET, \[execution status denied\]\",\"domain\":\"security_gateway\"\}\]\}/")
                        // AccountID: 4556474
                        || $this->http->FindPreg("/\{\"errors\":\[\{\"error_code\":\"access_denied_tas\",\"message\":\"ErrorCode = IC_UE_POLICY_EXECUTION_FAILED Message = TP User not allowed to access this URI, \[execution status denied\]\",\"domain\":\"security_gateway\"\}\]\}/")
                        // AccountID: 6126383
                        || $this->http->FindPreg("/\{\"errors\":\[\{\"error_code\":\"access_denied\",\"message\":\"ErrorCode = IC_UE_POLICY_EXECUTION_FAILED Message = Provided tokens not authorized to access this resource, \[execution status denied\]\",\"domain\":\"security_gateway\"\}\]\}/")
                    ) {
                        $detectedCard['CardDescription'] = C_CARD_DESC_DO_NOT_EARN;
                        $this->SetBalanceNA();

                        // AccountID: 3967922
                        if (
                            strstr($displayName, 'Hilton Honors Aspire Card')
                        ) {
                            $detectedCard['CardDescription'] = C_CARD_DESC_HHONORS;
                        } elseif (
                            strstr($displayName, 'Bonvoy Business Amex Card')
                            || strstr($displayName, 'Bonvoy Amex Card')
                        ) {
                            $detectedCard['CardDescription'] = C_CARD_DESC_MARRIOTT;
                        } elseif (strstr($displayName, 'Aeroplan')) {
                            $detectedCard['CardDescription'] = str_replace(['[Program]', '[Program_ID]'], ['Air Canada Aeroplan (Altitude)', 2], C_CARD_DESC_UNIVERSAL);
                        }
                    } elseif (isset($program_code)
                        && (!isset($account_number) || $program_code == 'HLTN' || $program_code == 'DLTA')) {
                        if ($program_code == 'HLTN') {
                            $this->logger->notice(">>> Skip Hilton card");
                            $detectedCard['CardDescription'] = C_CARD_DESC_HHONORS;
                        }// if ($program_code == 'HLTN')
                        elseif ($program_code == 'DLTA') {
                            $this->logger->notice(">>> Skip Delta card");
                            $detectedCard['CardDescription'] = C_CARD_DESC_DELTA;
                        }// if ($program_code == 'DLTA')
                        elseif ($program_code == 'STRW') {
                            $this->logger->notice(">>> Skip Starwood card");
                            $detectedCard['CardDescription'] = C_CARD_DESC_MARRIOTT;
                        } else {
                            $this->sendNotification("Unknown CardDescription for {$program_code}");
                        }
                    }// elseif (isset($program_code) && (!isset($account_number) || $program_code == 'HLTN'))
                    else {
                        // something strange, provider bug (AccountID: 2860405)
                        if (!isset($primary_account)) {
                            // refs #16167
//                            $historyCode = "amex".$account_token.$subAccount['Number'];
//                            $originalCard['HistoryRows'] = $this->parseSubAccHistory($historyCode , array_merge(["account_token" => $account_token], $headers), $originalCard['Code'], $originalCard['DisplayName']);
//                            $this->AddSubAccount($originalCard);
                            continue;
                        }
//                            return false;
                        if ($primary_account == $code) {
                            $this->SetBalanceNA();
                        }
                        $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $subAccount['Number']);
                    }

                    // refs #20852
                    $originalCard['HistoryRows'] = $this->parseSubAccHistory($originalCard['Code'], array_merge(["account_token" => $account_token], $headers), $originalCard['Code'], $originalCard['DisplayName'], true);
                    $this->AddSubAccount($originalCard);
                }// $this->logger->notice("balance not found");

                // Airline Fee Credit   // refs #15551
                if ((
                        strstr($displayName, 'Platinum')
                        || strstr($displayName, 'Premier Rewards Gold Card')
                        // Hilton Honors Aspire Card    // refs #17111
                        || strstr($displayName, 'Honors Aspire')
                        // refs #17111
                        || strstr($displayName, 'Honors American Express Aspire')
                        // Dining Credit    // refs #17046
                        || strstr($displayName, 'American Express Gold Card')
                        // refs #22654
                        || strstr($displayName, 'Business Gold Card')
                        // refs #22821
                        || strstr($displayName, 'Reserve Card')
                        // refs #23180
                        || strstr($displayName, 'Hilton Honors Card')
                        // refs #23493
                        || strstr($displayName, 'Hilton Honors Surpass')
                        // refs #24220
                        || strstr($displayName, 'Hilton Honors Business')
                        // refs #23575
                        || strstr($displayName, 'Delta Reserve Business Card')
                    )
                    && !empty($account_key)) {
//                    $this->logger->info("Airline Fee Credit: {$displayName} (-{$code})", ['Header' => 3]);
                    $headers = [
                        "Referer" => "https://global.americanexpress.com/dashboard",
                    ];
                    $this->http->GetURL("https://global.americanexpress.com/card-benefits/view-all?account_key={$account_key}", $headers);
                    /*
                    $headers = [
                        "Content-Type" => "application/x-www-form-urlencoded",
                        "Accept" => "application/json, text/plain, *
                    /*",
                    ];
                    $sorted_index = $this->http->FindPreg('/sorted_index=(\d+)/', false, $this->http->currentUrl());
                    $this->http->PostURL("https://online.americanexpress.com/us/credit-cards/benefits/api/inav/{$sorted_index}", [], $headers);
                    $this->http->RetryCount = 1;
                    $this->http->PostURL("https://online.americanexpress.com/us/credit-cards/benefits/api/viewall", [], $headers);
                    $this->http->RetryCount = 2;
                    $response = $this->http->JsonLog(null, 0);

                    $airlineFeeCredit = false;
                    if (empty($response))
                        $airlineFeeCredit = true;

                    if (isset($response->data->benefits) && isset($sorted_index))
                        foreach ($response->data->benefits as $key => $benefit) {
                            if (isset($benefit->primfilter, $benefit->header)
                                && in_array($benefit->primfilter, ['Enroll', 'Travel'])
                                && (strstr($benefit->header, '00 Airline Fee Credit'))) {
                                $this->http->PostURL("https://online.americanexpress.com/us/credit-cards/benefits/api/enrollstatus/loggedin/{$benefit->id}/{$sorted_index}", [], $headers);
                                $benefitResponse = $this->http->JsonLog(null, 0);
                                if (isset($benefitResponse->data, $benefitResponse->data->enrollment_status, $benefitResponse->data->remaining_balance) && $benefitResponse->data->enrollment_status == 'enrolled') {
                                    $benefitBalance = $benefitResponse->data->remaining_balance.".".$benefitResponse->data->remaining_cents;
                                    $brand = $benefitResponse->data->brand;
                                    if (strstr($benefit->header, '100 Airline Fee Credit'))
                                        $feeCreditValue = "100";
                                    elseif (strstr($benefit->header, '250 Airline Fee Credit'))
                                        $feeCreditValue = "250";
                                    else {
                                        if (!strstr($benefit->header, '200 Airline Fee Credit'))
                                            $this->sendNotification("refs #15551: {$benefit->header}");
                                        $feeCreditValue = "200";
                                    }
                                    $this->logger->debug("Remaining $".$feeCreditValue." Airline Fee Credit ({$brand}): {$benefitBalance}");
                                    if ($benefitBalance > 0) {
                                        $benefitDisplayName = isset($brand) ? "Remaining $".$feeCreditValue." Airline Fee Credit ({$brand})" : "Remaining $".$feeCreditValue." Airline Fee Credit";
                                        $benefitSubAccounts[] = [
                                            'Code'           => 'amexAirlineFeeCredit'.$code,
                                            'DisplayName'    => $benefitDisplayName,
                                            'Balance'        => $benefitBalance,
                                            'Currency'       => "$",
                                            'ExpirationDate' => strtotime("31 Dec")
                                        ];
                                        $this->logger->debug("Adding subAccount...");
                                        $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);
                                    }// if ($benefitBalance > 0)
                                }// if (isset($benefitResponse->data) && $benefitResponse->data->enrollment_status == 'enrolled')
                                break;
                            }// if ($benefit->primfilter == 'Enroll' && $benefit->header == '$200 Airline Fee Credit')
                        }// foreach ($response->data->benefits as $benefit)
                    */
                    $airlineFeeCredit = true;
                    $benefitSubAccounts = array_merge($benefitSubAccounts, $this->getDiningCredits($displayName, $code, $account_token, $airlineFeeCredit));
                }// if (strstr($displayName, 'Platinum') && !empty($account_key))

                $this->logger->debug(var_export($detectedCard, true), ['pre' => true]);
                $this->detectedCards[] = $detectedCard;
            }// if ($account_token)
            else {
                $this->logger->error("account_token not found");
            }

            // AccountID: 3652567
            if ($countOfAccounts > 20) {
                $this->increaseTimeLimit(120);
            }
        }// foreach ($accounts as $account)

        // detected cards
        if (!empty($this->detectedCards)) {
            $this->SetBalanceNA();
            $this->SetProperty("DetectedCards", $this->detectedCards);

            // fixes for linked cards
            // AccountID: 3746543 - several cards with different MR balance
            // AccountID: 2316042 - several cards with same MR balance
            foreach ($primary_card as $key => $value) {
                if (isset($linked_card[$key])) {
                    unset($linked_card[$key]);
                }// if (isset($linked_card[$key]))
            }// foreach ($primary_card as $key => $value)

            foreach ($linked_card as $key => $value) {
                $detectedCard = $linked_card[$key];
                $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $linked_card[$key]['Number']);
                $linked_card[$key]["Code"] = "amex" . $linked_card[$key]['Number'];
                $linked_card[$key]["DisplayName"] = "Membership Rewards ({$linked_card[$key]['Number']})";

                // refs #21658
                if (array_search($linked_card[$key]["DisplayName"], array_column($this->Properties['SubAccounts'], 'DisplayName'))) {
                    $this->logger->notice(">>> [$key]: such DisplayName already found, skip");
                    $this->logger->debug(">>> [$key]:" . var_export($linked_card[$key], true), ['pre' => true]);
                } else {
                    $this->AddSubAccount($linked_card[$key]);
                    $balanceMR += floatval($linked_card[$key]['Balance']);
                }

                $this->AddDetectedCard($detectedCard, true, true);
            }// foreach ($linked_card as $key => $value)
        }// if (!empty($this->detectedCards))

        // refs #16147
        $this->logger->info('Summary of MR subAccounts', ['Header' => 3]);
        $this->SetProperty("CombineSubAccounts", false);
        $this->logger->debug("Summary of MR subAccounts: {$balanceMR}");

        if (!empty($balanceMR)) {
            $this->SetBalance($balanceMR);

            if (isset($this->Properties['SubAccounts'])) {
                $countSubAccounts = count($this->Properties['SubAccounts']);
                $this->logger->debug("count subAccounts: $countSubAccounts");

                for ($i = 0; $i < $countSubAccounts; $i++) {
                    // refs #21681
                    if (isset($this->Properties['SubAccounts'][$i]['Currency']) && $this->Properties['SubAccounts'][$i]['Currency'] == '$') {
                        continue;
                    }

                    $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
                    // refs #16167
//                    if (isset($history[$this->Properties['SubAccounts'][$i]['Code']])) {
//                        // Sort by date
//                        usort($history[$this->Properties['SubAccounts'][$i]['Code']], function($a, $b) {
//                            $key = 'Date';
//                            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
//                        });
//                        $this->Properties['SubAccounts'][$i]['HistoryRows'] = $history[$this->Properties['SubAccounts'][$i]['Code']];
//                    }// if (isset($history[$this->Properties['SubAccounts'][$i]['Code']]))
                }// for ($i = 0; $i < $countSubAccounts; $i++)
            }// if (isset($this->Properties['SubAccounts']))
        }// if (!empty($balanceMR))

        // refs #14492
        $this->logger->info('FICO® Score', ['Header' => 3]);
        $this->http->GetURL("https://online.americanexpress.com/myca/creditscore/us/viewScore?request_type=authreg_acctCreditScore&csdeeplink=true&linknav=US-Ser-axpUsefulLinks-cbViewFICOScore&Face=en_US");
        $codedJson = $this->http->FindPreg("/var\s*ficoJSON\s*=\s*CS\.utilities\.decodeJSONString\('([^']+)'/ims");
        $json = urldecode($codedJson);
        $json = $this->http->JsonLog($json, 3, true);

        if (isset($json['creditScorePageBean'][0])) {
            $creditScorePageBean = $json['creditScorePageBean'][0];
            $ficoScores = ArrayVal($creditScorePageBean, 'ficoScores', []);
            $ficoScoreData = [];

            foreach ($ficoScores as $ficoScore) {
                $date = str_split($ficoScore['scoreCalculatedDate']);
//                $this->logger->debug(var_export($date, true), ['pre' => true]);
                if (count($date) != 8) {
                    continue;
                }
                $date = $date[0] . $date[1] . "/" . $date[2] . $date[3] . "/" . $date[4] . $date[5] . $date[6] . $date[7];
                $this->logger->debug($date);
                $date = strtotime($date);
                $this->logger->debug($date);

                if (!isset($ficoScoreData['FICOScoreUpdatedOn']) || $date > $ficoScoreData['FICOScoreUpdatedOn']) {
                    $ficoScoreData = [
                        'Balance'            => $ficoScore['score'],
                        'FICOScoreUpdatedOn' => $date,
                    ];
                }
            }// foreach ($ficoScores as $ficoScore)

            if (!empty($ficoScoreData)) {
                // FICO® SCORE
                $fcioScore = $ficoScoreData['Balance'];
                // FICO Score updated on
                $fcioUpdatedOn = $ficoScoreData['FICOScoreUpdatedOn'];

                if ($fcioScore && $fcioUpdatedOn) {
                    $this->AddSubAccount([
                        "Code"               => "amexFICO",
                        "DisplayName"        => "FICO® Score 8 (Experian)",
                        "Balance"            => $fcioScore,
                        "FICOScoreUpdatedOn" => date('m/d/Y', $fcioUpdatedOn),
                    ]);
                }// if ($fcioScore && $fcioUpdatedOn)
            }// if (!empty($ficoScoreData))
        }// if (isset($json['creditScorePageBean'][0]))

        // Airline Fee Credit   // refs #15551
        foreach ($benefitSubAccounts as $benefitSubAccount) {
            $this->AddSubAccount($benefitSubAccount);
        }
        // Amex offers      // refs #15633
//        foreach ($amexOffers as $amexOffer)
//            $this->AddSubAccount($amexOffer);

        $this->parseTravelCredit();
    }

    // refs #21039
    public function progressRewards($displayName, $code, $account_token)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Progress Reward: {$displayName} (-{$code})", ['Header' => 3]);
        $benefitSubAccounts = [];
        $headers = [
            "content-type"  => "application/json",
            "Accept"        => "*/*",
            "origin"        => "https://global.americanexpress.com",
            "account_token" => $account_token,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://global.americanexpress.com/api/servicing/v2/trackers?type=TARGET_SPEND_REWARD,EVENT_BASED_REWARD&period=ALL", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 2);

        // it helps
        if (isset($response->message) && $response->message == "Internal Server Error") {
            sleep(3);
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://global.americanexpress.com/api/servicing/v2/trackers?type=TARGET_SPEND_REWARD,EVENT_BASED_REWARD&period=ALL", $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog(null, 2);
        }

        if (!empty($response) && $this->http->Response['code'] != 400) {
            foreach ($response as $nightInfo) {
                if (
                !empty($nightInfo)
                // refs #21914
                && in_array($nightInfo->name, [
                    'Earn a Free Weekend Night Reward',
                    'Earn a Free Night Reward',
                    'Earn a $100 Delta Flight Credit',
                ])
                && $nightInfo->status == 'TRACKING'
                && $nightInfo->type == 'TARGET_SPEND_REWARD'
                && in_array($nightInfo->sub_type, [
                    'HOTEL_FREE_NIGHT',
                    // refs #22821
                    'SPEND_BASED_TRAVEL_VOUCHER',
                ])
                && isset($nightInfo->spent)
                && isset($nightInfo->remaining)
            ) {
                    $displayName = "Spent until a Free Hilton Night Reward - card ending in {$code}";

                    if (in_array($nightInfo->name, [
                        'Earn a $100 Delta Flight Credit',
                    ])) {
                        $displayName = "Spent until a $100 Delta Flight Credit - card ending in {$code}";
                    }

                    $benefitBalance = $nightInfo->remaining->amount;
                    $this->logger->debug("Balance = {$benefitBalance}");
                    $this->logger->debug("Remaining {$displayName}: {$benefitBalance}");

                    if ($benefitBalance > 0) {
                        $benefitSubAccounts[] = [
                            'Code'           => 'amexTrackerReward' . md5($displayName) . $code,
                            'DisplayName'    => $displayName,
                            // ... spent
                            'Balance'        => $nightInfo->spent->amount,
                            // ...  to go - Spend until a Free Hilton Night Reward
                            'AmountToSpend'  => "$" . number_format($benefitBalance, 2),
                            'Currency'       => "$",
                            'ExpirationDate' => strtotime($nightInfo->period->end_date),
                        ];
                        $this->logger->debug("Adding subAccount...");
                        $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);
                    }// if ($benefitBalance > 0)

                    break;
                }
            }
        }// foreach ($response as $nightInfo)

        return $benefitSubAccounts;
    }

    // refs #17046
    public function getDiningCredits($displayName, $code, $account_token, $airlineFeeCredit)
    {
        $this->logger->notice(__METHOD__);

        if (
            strstr($displayName, 'Additional ')
        ) {
            return [];
        }

        $this->logger->info("Dining Credit: {$displayName} (-{$code})", ['Header' => 3]);
        $benefitSubAccounts = [];

        $headers = [
            "content-type"  => "application/json",
            "Accept"        => "*/*",
            "Origin"        => "https://global.americanexpress.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://functions.americanexpress.com/ReadLoyaltyBenefits.v2", "{\"accountToken\":\"$account_token\",\"locale\":\"en-US\"}", $headers);
        $this->http->RetryCount = 2;
        $responseReadLoyaltyBenefits = $this->http->JsonLog(null, 2);

        $this->logger->info("Digital Entertainment Credit: {$displayName} (-{$code})", ['Header' => 3]);
        $data = [
            [
                "accountToken" => $account_token,
                "locale"       => "en-US",
                "limit"        => "ALL",
            ],
        ];
        $this->http->PostURL("https://functions.americanexpress.com/ReadBestLoyaltyBenefitsTrackers.v1", json_encode($data));
        $responseEntertainments = $this->http->JsonLog(null, 5);

        if ($responseEntertainments) {
            foreach ($responseEntertainments as $responseEntertainment) {
                if (!isset($responseEntertainment->trackers)) {
                    continue;
                }

                foreach ($responseEntertainment->trackers as $tracker) {
                    if (
                        (
                            !in_array($tracker->benefitId, [
                                "digital-entertainment",
                                "clear-platinum-tracker",
                                "equinox-platinum-tracker",
                                "saks-platinum-tracker",
                                "120-dining-credit-gold",
                                "hotel-credit-platinum-tracker",
                                "250-afc-hilton-tracker",
                                "200-afc-tracker",
                                "200-afc-business-platinum-tracker",
                                "delta-reserve-miles-boost",
                                "adobe-statment-credit-business-platinum",
                                "indeed-statment-credit-business-platinum",
                                "wireless-statment-credit-business-platinum",
                                "dell-credit-business-platinum",
                                "miles-headstart", // refs #22821
                                "bus-gold-240-statement-credit", // refs #23485
                                "400-hilton-aspire-resort-credit", // refs #23493
                                "200-hilton-surpass-credit", // refs #23493
                                "hilton-honors-statement-credit", // refs #24220
                                //                                "hilton-honors-free-night-tracker",// Spent until a Free Hilton Night Reward - card ending in ...
                                //                                "walmart-platinum-tracker",
                                "200-airline-statement-credit", // refs #23783
                                "hilton-aspire-189-clear-credit", // refs #23783
                                "20-resy-statement-credit-delta-reserve-sbs", // refs #23575
                                "10-rideshare-sc-delta-reserve-sbs", // refs #23575
                                "250-sc-delta-reserve-sbs", // refs #23575
                                "200-delta-statement-credit", // refs #23575
                            ])
                            && !strstr($tracker->benefitName, "Centurion Lounge Complimentary Guest Access")
                        )
                        || in_array($tracker->status, ['NOTENROLLED', 'NOTELIGIBLE', 'ACHIEVED'])
                    ) {
                        $this->logger->notice("[skip benefit]: {$tracker->benefitName}");

                        continue;
                    }

                    $benefitDisplayName = str_replace('New: $', 'Remaining $', $tracker->progress->title ?? $tracker->benefitName);
                    $benefitDisplayName = trim(str_replace('Enjoy a Status Boost', 'Status Boost', $benefitDisplayName));
                    $benefitDisplayName = str_replace('$400-hilton-aspire-resort-credit', '$400 Hilton Resort Credit', $benefitDisplayName);
                    $benefitDisplayName = str_replace("$200-hilton-surpass-credit", '$200 Hilton Credit', $benefitDisplayName);
                    $benefitDisplayName = str_replace("$200-airline-statement-credit", '$200 Flight Credit', $benefitDisplayName);
                    $benefitTargetBalance =
                        $this->http->FindPreg("/([\d\.\,]+) (?:Digital Entertainment Credit|CLEAR Credit|Equinox Credit|Saks Credit)/", false, $benefitDisplayName)
                        ?? $tracker->tracker->targetAmount
                    ;
                    $totalSavingsYearToDate =
                        $tracker->progress->totalSavingsYearToDate
                        ?? $this->http->FindPreg("/received this year: .([\d\.]+)/", false, $tracker->progress->message ?? null)
                        ?? $tracker->tracker->spentAmount
                    ;
                    $this->logger->debug("Balance = {$benefitTargetBalance} - {$totalSavingsYearToDate}");

                    if (
                        strstr($tracker->benefitName, "$120 Dining Credit")
                        || $tracker->benefitName == "$200-airline-statement-credit" // refs #23783
                    ) {
                        $benefitBalance = $tracker->tracker->remainingAmount;
                    } elseif (strstr($tracker->benefitName, "Centurion Lounge Complimentary Guest Access")) {
                        $benefitBalance = $tracker->tracker->spentAmount;
                    } else {
                        $benefitBalance = $benefitTargetBalance - $totalSavingsYearToDate;
                    }

                    $this->logger->debug("Remaining {$benefitDisplayName}: {$benefitBalance}");

                    if ($benefitBalance > 0) {
                        // refs #22815
                        if (strstr($benefitDisplayName, 'Airline Fee Credit')) {
                            foreach ($responseReadLoyaltyBenefits->benefits ?? [] as $key => $loyaltyBenefit) {
                                if (
                                    $key == 'airline-fee-credit'
                                    && $loyaltyBenefit->layoutType == 'ENROLLED'
                                    && isset($loyaltyBenefit->enrollmentApplication->selectionName)
                                ) {
                                    $brand = $loyaltyBenefit->enrollmentApplication->selectionName;
                                    $benefitDisplayName = str_replace('Airline Fee Credit', "Airline Fee Credit ({$brand})", $benefitDisplayName);
                                }
                            }// foreach ($responseReadLoyaltyBenefits->benefits as $loyaltyBenefit)
                        }// if (strstr($benefitDisplayName, 'Airline Fee Credit'))

                        $propertyKey = 'TotalSavingsYearToDate';

                        if (strstr($benefitDisplayName, 'Status Boost')) {
                            $propertyKey = 'AmountToSpend';
                        }

                        $benefitSubAccounts[] = [
                            'Code'                   => 'amex' . ucfirst(str_replace(' ', '', $tracker->benefitName)) . md5($displayName) . $code,
                            'DisplayName'            => $benefitDisplayName . " - card ending in {$code}",
                            'Balance'                => $benefitBalance,
                            $propertyKey             => "$" . $totalSavingsYearToDate,
                            'Currency'               => "$",
                            'ExpirationDate'         => strtotime("31 Dec"),
                            'IsSpentSum'             => true,
                        ];
                        $this->logger->debug("Adding subAccount...");
                        $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);

                        $this->State[ucfirst(str_replace(' ', '', $tracker->benefitName)) . $account_token] = [
                            "HasBalance" => true,
                            "Balance"    => $benefitBalance,
                            "Time"       => time(),
                        ];
                    }// if ($benefitBalance > 0)
                }// foreach ($responseEntertainment as $entertainment)
            }// foreach ($responseEntertainments as $responseEntertainment)
        }// if ($responseEntertainments)

        return $benefitSubAccounts;

        $headers = [
            "content-type"  => "application/json",
            "Accept"        => "*/*",
            "origin"        => "https://global.americanexpress.com",
            "account_token" => $account_token,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://rewards.americanexpress.com/orchestration/", '{"query":"query {\n  benefitsContent {\n    benefitsContentCardData {\n      cardname\n      redirectUrl\n    }\n  }\n}"}', $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);
        $cardname = $response->data->benefitsContent->benefitsContentCardData->cardname ?? null;

        if (!$cardname) {
            return $benefitSubAccounts;
        }

        $data = [
            "variables" => "{\"environment\":\"global\",\"locale\":\"en_us\",\"cardname\":\"{$cardname}\",\"loggedin\":true}",
            "query"     => "query (\$locale: String!, \$environment: String!, \$cardname: String, \$loggedin: Boolean) {\n    benefitsContent {\n      filterExcludingBlacklist(locale: \$locale, environment: \$environment, cardname: \$cardname, loggedin: \$loggedin) {\n        category\n        ids\n      }\n    }\n  }",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://rewards.americanexpress.com/orchestration/", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);

        if (!isset($response->data->benefitsContent->filterExcludingBlacklist)) {
            return $benefitSubAccounts;
        }

        $benefitNames = [
            "dining-credit",
            //            "walmart-platinum",
            "equinox-credit",
            "clear-credit",
            "shop-saks",
        ];

        if ($airlineFeeCredit) {
            $benefitNames[] = "airline-fee-credit";
        }

        foreach ($response->data->benefitsContent->filterExcludingBlacklist as $item) {
//            if ($item->category != 'Dining')
            if ($item->category != 'Enroll') {// AccountID: 2705491
                continue;
            }

            foreach ($item->ids as $id) {
                $data = [
                    "variables" => [
                        "id"       => $id,
                        "locale"   => "en_us",
                        "cardname" => $cardname,
                        "afc"      => false,
                    ],
                    "query"     => "query(\$id: Int!, \$locale: String!, \$cardname: String!, \$afc: Boolean!) {\n  benefitsContent {\n    benefitById(id: \$id) {\n      id\n      title\n      alternate_title\n      images {\n        medium_url\n      }\n      call_to_action {\n        url\n        title\n      }\n    short_description\n    disclaimer @include(if: \$afc)\n    }\n  }\n  layout {\n    benefitLayout(locale: \$locale, id: \$id, cardname: \$cardname) {\n      benefitName\n      enrollStatus {\n        notEnrolled\n        enrolled\n        pending\n      }\n      ctaText\n      renewText @include(if: \$afc)\n      altText\n      learnMoreText\n      layout {\n        header\n        enrollStatus\n        description\n        cta\n        learnMore\n      }\n    }\n  }\n}",
                ];
                $this->increaseTimeLimit();
                $this->http->PostURL("https://rewards.americanexpress.com/orchestration/", json_encode($data), $headers);
                $response = $this->http->JsonLog(null, 0);

                if (
                    !isset($response->data->layout->benefitLayout->benefitName)
                    || !isset($response->data->layout->benefitLayout->ctaText)
                    || !isset($response->data->layout->benefitLayout->layout->header)
                    || !in_array($response->data->layout->benefitLayout->benefitName, $benefitNames)
                    && !in_array($response->data->layout->benefitLayout->ctaText, ["Enroll", "Select An Airline", "static"/*, "Get Started"*/])
                ) {
                    // refs #20851
                    if (
                        isset($response->data->layout->benefitLayout->benefitName)
                        && in_array($response->data->layout->benefitLayout->benefitName, $benefitNames)
                    ) {
                        $this->logger->info("Digital Entertainment Credit: {$displayName} (-{$code})", ['Header' => 3]);
                        $data = [
                            [
                                "accountToken" => $account_token,
                                "locale"       => "en-us",
                                "limit"        => "ALL",
                            ],
                        ];
                        $this->http->PostURL("https://functions.americanexpress.com/ReadBestLoyaltyBenefitsTrackers.v1", json_encode($data));
                        $responseEntertainments = $this->http->JsonLog(null, 5);

                        if ($responseEntertainments) {
                            foreach ($responseEntertainments as $responseEntertainment) {
                                if (!isset($responseEntertainment->trackers)) {
                                    continue;
                                }

                                foreach ($responseEntertainment->trackers as $tracker) {
                                    if (
                                        (
                                            !in_array($tracker->benefitId, [
                                                "digital-entertainment",
                                                "clear-platinum-tracker",
                                                "equinox-platinum-tracker",
                                                "saks-platinum-tracker",
                                                //                                                "walmart-platinum-tracker",
                                            ])
                                            && !strstr($tracker->benefitName, "Centurion Lounge Complimentary Guest Access")
                                        )
                                        || in_array($tracker->status, ['NOTENROLLED', 'NOTELIGIBLE'])
                                    ) {
                                        $this->logger->notice("[skip benefit]: {$tracker->benefitName}");

                                        continue;
                                    }

                                    $benefitDisplayName = str_replace('New: $', 'Remaining $', $tracker->progress->title ?? $tracker->benefitName);
                                    $benefitTargetBalance =
                                        $this->http->FindPreg("/([\d\.\,]+) (?:Digital Entertainment Credit|CLEAR Credit|Equinox Credit|Saks Credit)/", false, $benefitDisplayName)
                                        ?? $tracker->tracker->targetAmount
                                    ;
                                    $totalSavingsYearToDate =
                                        $tracker->progress->totalSavingsYearToDate
                                        ?? $this->http->FindPreg("/received this year: .([\d\.]+)/", false, $tracker->progress->message ?? null)
                                        ?? $tracker->tracker->remainingAmount
                                    ;
                                    $this->logger->debug("Balance = {$benefitTargetBalance} - {$totalSavingsYearToDate}");

                                    if (strstr($tracker->benefitName, "Centurion Lounge Complimentary Guest Access")) {
                                        $benefitBalance = $tracker->tracker->spentAmount;
                                    } else {
                                        $benefitBalance = $benefitTargetBalance - $totalSavingsYearToDate;
                                    }

                                    $this->logger->debug("Remaining {$benefitDisplayName}: {$benefitBalance}");

                                    if ($benefitBalance > 0) {
                                        $benefitSubAccounts[] = [
                                            'Code'                   => 'amex' . ucfirst(str_replace(' ', '', $tracker->benefitName)) . md5($displayName) . $code,
                                            'DisplayName'            => $benefitDisplayName . " - card ending in {$code}",
                                            'Balance'                => $benefitBalance,
                                            'TotalSavingsYearToDate' => "$" . $totalSavingsYearToDate,
                                            'Currency'               => "$",
                                            'ExpirationDate'         => strtotime("31 Dec"),
                                            'IsSpentSum'             => true,
                                        ];
                                        $this->logger->debug("Adding subAccount...");
                                        $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);

                                        $this->State[ucfirst(str_replace(' ', '', $tracker->benefitName)) . $account_token] = [
                                            "HasBalance" => true,
                                            "Balance"    => $benefitBalance,
                                            "Time"       => time(),
                                        ];
                                    }// if ($benefitBalance > 0)
                                }// foreach ($responseEntertainment as $entertainment)
                            }// foreach ($responseEntertainments as $responseEntertainment)
                        }// if ($responseEntertainments)
                    }// if ($response->data->layout->benefitLayout->benefitName == "digital-entertainment")

                    if (isset($response->data->layout->benefitLayout->benefitName)) {
                        // AccountID: 1996386
                        if ($this->http->ResponseNumber > 490) {
                            $this->increaseMaxRequests();
                        }

                        $this->logger->notice("Skip benefitname -> {$response->data->layout->benefitLayout->benefitName}");
                    } else {
                        $this->logger->notice("benefitname not found");
                    }

                    continue;
                }

                $benefitName = $response->data->layout->benefitLayout->benefitName;
                $data = [
                    "variables" => [
                        "code"        => $response->data->layout->benefitLayout->layout->header,
                        "queryCode"   => null,
                        "locale"      => "en_us",
                        "cardname"    => $cardname,
                        "benefitname" => $benefitName,
                    ],
                    "query"     => "\n  query(\$code: String!, \$queryCode: String, \$locale: String!, \$cardname: String!, \$benefitname: String!) {\n    benefitsContent {\n        tracker(code: \$code, queryCode: \$queryCode) {\n          code\n          name\n          description\n          used_amount\n          remaining_amount\n          total_amount\n          accrued_used_amount\n        }\n    }\n    enrollmentContent {\n      enrollmentPage (locale: \$locale, cardname: \$cardname, benefitname: \$benefitname) {\n        exhausted_amount\n        total_saving\n        used_month\n        unused_month\n        credit_year\n        renewal_year\n        balance_disclaimer\n      }\n    }\n  }\n",
                ];
                $this->http->PostURL("https://rewards.americanexpress.com/orchestration/", json_encode($data), $headers);
                $response = $this->http->JsonLog(null, 0);

                if (!isset($response->data->benefitsContent->tracker->name)
                    || !isset($response->data->benefitsContent->tracker->remaining_amount)) {
                    continue;
                }
                $benefitBalance = $response->data->benefitsContent->tracker->remaining_amount;

                if ($benefitName == "dining-credit") {
                    $this->http->JsonLog();
                    $benefitDisplayName = str_replace(' Credit Benefit', ' Credit', $response->data->benefitsContent->tracker->name);
                    $this->logger->debug("Remaining {$benefitDisplayName}: {$benefitBalance}");

                    if ($benefitBalance > 0) {
                        $benefitSubAccounts[] = [
                            'Code'           => 'amexDiningCredit' . $code,
                            'DisplayName'    => "Remaining Dining Credit (card ending in {$code})",
                            'Balance'        => $benefitBalance,
                            'Currency'       => "$",
                            'ExpirationDate' => strtotime('last day of this month', time()),
                        ];
                        $this->logger->debug("Adding subAccount...");
                        $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);
                    }// if ($benefitBalance > 0)

                    if (!$airlineFeeCredit) {
                        break;
                    }
                }// if ($benefitName == "dining-credit")
                else {
                    $this->logger->info("Airline Fee Credit: {$displayName} (-{$code})", ['Header' => 3]);
                    // AccountID: 2705491
                    $data = [
                        "variables" => [
                            "id"       => $id,
                            "locale"   => "en_us",
                            "cardname" => $cardname,
                            "afc"      => true,
                        ],
                        "query"     => "query(\$id: Int!, \$cardname: String!, \$afc: Boolean!, \$locale: String) {\n  enrollmentContent {\n    enrollmentStatusById(id: \$id, cardname: \$cardname, locale: \$locale) {\n      id\n      name\n      provider\n      code\n      outcome\n      membershipNumber\n      membershipText\n      airline_code @include(if: \$afc)\n      airline_name @include(if: \$afc)\n      airline_selection_date @include(if: \$afc)\n    }\n  }\n}",
                    ];
                    $this->http->PostURL("https://rewards.americanexpress.com/orchestration/", json_encode($data), $headers);
                    $airlineNameInfo = $this->http->JsonLog(null, 3);
                    $brand = $airlineNameInfo->data->enrollmentContent->enrollmentStatusById->airline_name ?? null;
                    // NOTENROLLED
                    if (isset($airlineNameInfo->data->enrollmentContent->enrollmentStatusById->code)
                        && in_array($airlineNameInfo->data->enrollmentContent->enrollmentStatusById->code, ['NOTENROLLED', 'NOTELIGIBLE'])
                        || (
                            isset($airlineNameInfo->data->enrollmentContent, $airlineNameInfo->errors[0]->message)
                            && $airlineNameInfo->data->enrollmentContent->enrollmentStatusById === null
                            && $airlineNameInfo->errors[0]->message == "Error: socket hang up"
                        )
                    ) {
                        $this->logger->notice("Skip NOTENROLLED benefitname -> {$response->data->benefitsContent->tracker->name}");

                        // refs #17111
                        $this->http->RetryCount = 0;
                        $this->http->PostURL("https://functions.americanexpress.com/ReadBestLoyaltyBenefitsTrackers.v1", '[{"accountToken":"' . $account_token . '","locale":"en-us","limit":5}]');
                        $this->http->RetryCount = 2;
                        $airlineNameInfo = $this->http->JsonLog();

                        if (
                            (isset($airlineNameInfo->error) && $airlineNameInfo->error == '(RECIPIENT_FAILURE,502) LoyaltyBenefitsTrackers.v2 Failure.')
                            || (isset($airlineNameInfo->error) && $airlineNameInfo->error == 'RECIPIENT_FAILURE,0) Timed out after waiting')
                            || !isset($airlineNameInfo[0]->trackers[0]->status)
                            || $airlineNameInfo[0]->trackers[0]->status != 'IN_PROGRESS'
                        ) {
                            continue;
                        }
                    }

                    if (strstr($response->data->benefitsContent->tracker->name, '100 Airline Fee Credit')) {
                        $feeCreditValue = "100";
                    } elseif (strstr($response->data->benefitsContent->tracker->name, '250 Airline Fee Credit')) {
                        $feeCreditValue = "250";
                    } elseif (
                        $response->data->benefitsContent->tracker->name == 'Airline Fee Credit – New Program Setup - All Cards but Centurion'
                        && $response->data->benefitsContent->tracker->total_amount == '200'
                    ) {
                        $feeCreditValue = "200";
                    } else {
                        if (!strstr($response->data->benefitsContent->tracker->name, '200 Airline Fee Credit')) {
                            $this->sendNotification("refs #15551: {$response->data->benefitsContent->tracker->name}");
                        }
                        $feeCreditValue = "200";
                    }
                    $this->logger->debug("Remaining $" . $feeCreditValue . " Airline Fee Credit ({$brand}): {$benefitBalance}");

                    if ($benefitBalance > 0) {
                        $benefitDisplayName = isset($brand) ? "Remaining $" . $feeCreditValue . " Airline Fee Credit ({$brand})" : "Remaining $" . $feeCreditValue . " Airline Fee Credit";
                        $benefitSubAccounts[] = [
                            'Code'           => 'amexAirlineFeeCredit' . md5($displayName) . $code,
                            'DisplayName'    => $benefitDisplayName . " - card ending in {$code}",
                            'Balance'        => $benefitBalance,
                            'Currency'       => "$",
                            'ExpirationDate' => strtotime("31 Dec"),
                        ];
                        $this->logger->debug("Adding subAccount...");
                        $this->logger->debug(var_export($benefitSubAccounts, true), ['pre' => true]);
                    }// if ($benefitBalance > 0)
                }
            }// foreach ($item->ids as $id)
        }// foreach ($response->data->benefitsContent->filterExcludingBlacklist as $item)

        return $benefitSubAccounts;
    }

    // refs #17504
    public function parseTravelCredit()
    {
        $this->logger->notice(__METHOD__);

        if (!in_array($this->AccountFields['Login2'], [
            'Australia',
            'Canada',
        ])
        ) {
            return;
        }

        $face = 'en_AU';
        $lang = 'en-au';
        $loginURL = "https://global.americanexpress.com/myca/logon/japa/action/login";

        if ($this->AccountFields['Login2'] == 'Canada') {
            $face = "en_CA";
            $lang = 'en-ca';
            $loginURL = "https://global.americanexpress.com/myca/logon/canlac/action/login";
        }

        $this->logger->info('Travel Credit', ['Header' => 3]);

        $this->http->GetURL("https://www.americanexpress.com/{$lang}/account/travel/login?DestPage=https%3A%2F%2Fglobal.americanexpress.com%2Fmyca%2Fintl%2Fmrpartner%2Femea%2FauthMrPartner.do%3Frequest_type%3Dauthreg_MrPartner%26Face%3D{$face}%26searchType%3DTravel_1");
        /*
        $this->http->GetURL("https://global.americanexpress.com/myca/intl/mrpartner/japa/unauthMrPartner.do?request_type=un_MrPartner&Face=en_AU&searchType=Travel_1&intlink=mtsi-AU-book-travel-online-iNav-prod-int&inav=au_menu_travel_pt_booktrv");
        $this->http->GetURL("https://global.americanexpress.com/myca/logon/japa/action?request_type=LogonHandler&Face=en_AU&DestPage=https%3A%2F%2Fglobal.americanexpress.com%2Fmyca%2Fintl%2Fmrpartner%2Fjapa%2FauthMrPartner.do%3Frequest_type%3Dauthreg_MrPartner%26Face%3Den_AU%26searchType%3DTravel_1");
        $this->globalLogin();
        */

        $data = [
            "request_type" => "login",
            "Face"         => $face,
            "UserID"       => $this->AccountFields['Login'],
            "Password"     => $this->AccountFields['Pass'],
            "REMEMBERME"   => "on",
            "Logon"        => "Logon",
            "DestPage"     => "https://global.americanexpress.com/myca/intl/mrpartner/emea/authMrPartner.do?request_type=authreg_MrPartner&Face={$face}&searchType=Travel_1",
        ];
        $headers = [
            "Accept"     => "*/*",
            "User-Agent" => HttpBrowser::PROXY_USER_AGENT,
        ];
        $this->http->PostURL($loginURL, $data, $headers);
        $response = $this->http->JsonLog();
        $statusCode = $response->statusCode ?? null;

        if (!empty($response->redirectUrl)) {
            $this->http->GetURL($response->redirectUrl);
        } elseif (!empty($response->errorCode) && $statusCode) {
            $errorCode = $response->errorCode;
            $this->logger->error("[Error code]: {$errorCode}");

            return;
        }

        $headers = [
            "User-Agent" => HttpBrowser::PROXY_USER_AGENT,
        ];

        if ($this->http->ParseForm("interstitial")) {
            $UserData = $this->http->Form["UserData"];
            $this->http->PostForm($headers);
        }

        $onload = $this->http->FindPreg("/<BODY\s+Onload=\"([^\"]+)\"/ims");
        $this->logger->debug($onload);

        if ($onload == "document.FedSSOPost.submit();") {
            $FedSSOPost = "/ssofedi/Saml2/FedSSOService.jsp";

            if ($this->AccountFields['Login2'] == 'Canada') {
                $FedSSOPost = "/ssofedi/Saml2/FedSSOService.jsp?TPID=ezrez&gotoOnFail=https%3A%2F%2Fglobal.americanexpress.com%2Fmyca%2Fintl%2Fmrpartner%2Fcanlac%2FunauthMrPartnerError.do%3Frequest_type%3Dun_MrPartner%26Face%3Den_CA&IntegrationName=Intltravel2&UserData={$UserData}";
            }

            $this->http->NormalizeURL($FedSSOPost);
            sleep(2);
            $this->http->PostURL($FedSSOPost, [], $headers);
        }

        $count = 0;

        do {
            $posted = false;
            $onload = $this->http->FindPreg("/<BODY\s+Onload=\"([^\"]+)\"/ims");
            $this->logger->debug($onload);

            if ($onload == "document.login.submit();") {
                $this->logger->notice("onload-submit found. searching form");

                if ($this->http->ParseForm("login")) {
                    $this->logger->debug("submitting redirect form");
                    sleep(2);
                    $posted = true;
                    $this->http->PostForm($headers);
                    $count++;
                }
            }
        } while ($posted && $count < 5);

        $this->handleRedirectForm();

        if ($this->http->FindPreg("/redirect_form/")) {
            $this->http->PostForm();
        }

        $credits = $this->http->XPath->query('//tr[td[a[contains(text(), "Travel Credit")]]]');
        $this->logger->debug("Total {$credits->length} travel credits nodes were found");

        $balance = $this->http->FindSingleNode('//a[contains(text(), "Travel Credit") and not(contains(text(), "How to Use Your Annual"))]', null, true, "/(.+)Travel Credit/") ?? $this->http->FindPreg("/,\"expiration\":\d+000,\"name\":\"\\$(\d+)[A-Za-z\s]* Travel Credit\"/");

        if ($credits->length == 0 && $balance) {
            $exp = $this->ModifyDateFormat($this->http->FindSingleNode('//a[contains(text(), "Travel Credit")]', null, true, "/Expires\s*([^<]+)/"));
            $this->AddSubAccount([
                "Code"           => "amexAustraliaTravelCredit",
                "DisplayName"    => "Travel Credit",
                "Balance"        => $balance,
                'ExpirationDate' => isset($exp) ? strtotime($exp, false) : intval($this->http->FindPreg("/,\"expiration\":(\d+)000,\"name\":\"\\$\d+[A-Za-z\s]* Travel Credit\"/")),
                'Currency'       => "$",
            ]);
        }

        $url = "/profiles/amex_mtsi_process.cfm";
        $this->http->NormalizeURL($url);
        $subAccounts = [];

        foreach ($credits as $i => $credit) {
            $balance = $this->http->FindSingleNode('td[1]/a[contains(text(), "Travel Credit")]', $credit, true, "/(.+)[A-Za-z\s]* Travel Credit/");
            $displayName = $this->http->FindSingleNode('td[1]/a[contains(text(), "Travel Credit")]', $credit);
            $exp = $this->ModifyDateFormat($this->http->FindSingleNode('td[2]', $credit, true, "/Exp\s*([^<]+)/"));
            $subAccounts[] = [
                "Code"           => "amex{$this->AccountFields['Login2']}TravelCredit" . $i . md5($displayName),
                "DisplayName"    => $displayName,
                "Balance"        => $balance,
                'ExpirationDate' => strtotime($exp, false),
                'Currency'       => "$",
                'CreditCard'     => $this->http->FindSingleNode('td[1]/a[contains(text(), "Travel Credit")]/ancestor::tr[1]/preceding-sibling::tr[1]//input[@name="creditCard"]/@value', $credit),
            ];
        }// foreach ($credits as $i => $credit)

        if (!empty($subAccounts)) {
            foreach ($subAccounts as &$subAccount) {
                $browser = $this->http;
                $this->http->brotherBrowser($browser);

                $this->logger->debug(var_export($subAccount, true), ['pre' => true]);

                if (!$subAccount['ExpirationDate'] && $subAccount['CreditCard']) {
                    $data = [
                        "previousPage" => "/apps/shopping/#/search/room",
                        "creditCard"   => $subAccount['CreditCard'],
                    ];
                    $browser->PostURL($url, $data);

                    $exp = $this->ModifyDateFormat($browser->FindSingleNode('//a[contains(text(), "Travel Credit")]', null, true, "/Expires\s*([^<]+)/"));
                    $exp = isset($exp) ? strtotime($exp, false) : intval($browser->FindPreg("/,\"expiration\":(\d+)000,\"name\":\"\\$\d+[A-Za-z\s]* Travel Credit\"/"));
                    $subAccount['ExpirationDate'] = strtotime($exp, false);
                    unset($subAccount['CreditCard']);
                }
            }/// foreach ($subAccounts as $subAccount)
        }

        // refs #5816
        if ($evouchers = $this->http->FindPreg("/\"evouchers\":(\[[^\]]+\])/")) {
            $evouchers = $this->http->JsonLog($evouchers);

            foreach ($evouchers as $i => $evoucher) {
                $balance = $this->http->FindPreg("/\\$(\d+)[A-Za-z\s]* Travel Credit/", false, $evoucher->name);
                $displayName = html_entity_decode($evoucher->name);
                $exp = intval($this->http->FindPreg("/(\d+)000/", false, $evoucher->expiration));
                $this->AddSubAccount([
                    "Code"           => "amex{$this->AccountFields['Login2']}TravelCredit" . $i . md5($displayName),
                    "DisplayName"    => $displayName,
                    "Balance"        => $balance,
                    'ExpirationDate' => $exp,
                    'Currency'       => "$",
                ]);
            }// foreach ($evouchers as $i => $evoucher)
        }// if ($evouchers = $this->http->FindPreg("/\"evouchers\":(\[[^\]]+\])/"))

        foreach ($subAccounts as $subAccount) {
            $this->AddSubAccount([
                "Code"           => $subAccount['Code'],
                "DisplayName"    => $subAccount['DisplayName'],
                "Balance"        => $subAccount['Balance'],
                'ExpirationDate' => $subAccount['ExpirationDate'],
                'Currency'       => $subAccount['Currency'],
            ], true);
        }/// foreach ($subAccounts as $subAccount)
    }

    /**
     * valid on 23 May 2017.
     *
     * @throws CheckException
     */
    public function ParseBlueSky()
    {
        $this->logger->notice(__METHOD__);
        // json v.1
        $codedJson = $this->http->FindPreg("/var\s*decodedJSONStr\s*=\s*decodeJSONString\('([^']+)'/ims");

        if (isset($codedJson)) {
            $this->logger->notice("ParseBlueSky. json v.1");
        }
        // new json
        if (!isset($codedJson)) {
            $this->logger->notice("ParseBlueSky. json v.2");
            $codedJson = $this->http->FindPreg("/var\s*decodedJSONStr\s*=\s*AH\.utilities\.decodeJSONString\('([^']+)'/ims");
        }

        if (isset($codedJson)) {
            $json = urldecode($codedJson);
            $json = json_decode($json, true);

            // refs #12211
            $subAccountBalance = 0;
            $membershipRewardsBalance = 0;
            $subAccountCodes = [];
            $ficoData = [];

            // Collect non US card links    // refs #14876
            $nonUSCardURLs = [];

            $this->logger->debug(var_export($json, true), ['pre' => true]);

            if (isset($json['AccountSummaryBeanList'])) {
                $this->logger->notice("Number of accounts: " . count($json['AccountSummaryBeanList']));

                foreach ($json['AccountSummaryBeanList'] as $summary) {
                    if (isset($summary['membershipRewardsBean'])) {
                        $rewards = $summary['membershipRewardsBean'];
                        $cardInfo = $summary['cardBean'];
                        // for FICO
                        $acctHubLinksPageBean = $summary['acctHubLinksPageBean'];
                        // Name
                        if (!empty($cardInfo['cmName']) && empty($this->Properties['Name'])) {
                            $this->SetProperty("Name", beautifulName($cardInfo['cmName']));
                        }

                        // Collect non US card links    // refs #14876
                        if (!empty($cardInfo['nonUSCardURL']) && isset($cardInfo['nonUSCard']) && $cardInfo['nonUSCard']) {
                            $this->logger->notice("skip Non US Card");
                            $nonUSCardURLs[str_replace('-', '', $cardInfo['accountNumber'])] = $cardInfo['nonUSCardURL'];

                            continue;
                        }// if (!empty($cardInfo['nonUSCardURL']) && isset($cardInfo['nonUSCard']) && $cardInfo['nonUSCard'])

                        if (isset($rewards['loyaltyAccountNumber']) && isset($rewards['pointBalance']) && isset($rewards['programName'])) {
                            $displayName = $this->displayNameForSubAccounts($rewards['programName'], $cardInfo);

                            // refs #12211
                            if (!empty($rewards['programName']) && $rewards['programName'] == 'Membership Rewards®') {
                                $membershipRewards = true;
                            } else {
                                $membershipRewards = false;
                            }

                            // detected cards
                            $detectedCard = [
                                'Code'            => 'amex' . str_replace(' ', '', $displayName),
                                'DisplayName'     => $displayName,
                            ];

                            if (isset($cardInfo['cardAccountStatus']) && ($cardInfo['cardAccountStatus'] == 'Cancelled')) {
                                // detected cards
                                $detectedCard['CardDescription'] = C_CARD_DESC_CANCELLED;
                                $this->detectedCards[] = $detectedCard;

                                $this->logger->notice(">>> Skip cancelled card");
                                $this->SetBalanceNA();

                                continue;
                            }

                            if (($rewards['loyaltyAccountNumber'] == "" || stristr($rewards['loyaltyAccountNumber'], 'Not Available'))
                                && isset($cardInfo["accountNumber"])) {
                                $rewards['loyaltyAccountNumber'] = $summary["cardBean"]["accountNumber"];
                            }

                            if (isset($displayName, $rewards['loyaltyAccountNumber'])) {
                                $detectedCard['Code'] = 'amex' . $rewards['loyaltyAccountNumber'];
                                $this->SetBalanceNA();
                            }

                            // decimal
                            $pointBalanceDecimal = (isset($rewards['pointBalanceDecimal'])) ? '.' . $rewards['pointBalanceDecimal'] : '';
                            $pointBalanceDecimal = str_replace('..', '.', $pointBalanceDecimal);
                            $subAccount = [
                                'Code'        => 'amex' . $rewards['loyaltyAccountNumber'],
                                'DisplayName' => strstr($displayName, 'Membership Rewards') ? "Membership Rewards ({$rewards['loyaltyAccountNumber']})" : $displayName,
                                'Balance'     => $rewards['pointBalance'] . $pointBalanceDecimal,
                                'Number'      => $rewards['loyaltyAccountNumber'],
                                'Currency'    => (strstr($rewards['pointBalance'], '$')) ? "$" : null,
                            ];
                            // detected cards
                            $detectedCard['CardDescription'] = C_CARD_DESC_DO_NOT_EARN;

                            // Delta
                            if ((isset($rewards['loyaltyOfferLink']) && $rewards['loyaltyOfferLink'] == 'http://www.delta.com')
                                // new
                                || (isset($rewards['loyaltyFamily']) && $rewards['loyaltyFamily'] == 'Delta')) {
                                $detectedCard['CardDescription'] = C_CARD_DESC_DELTA;
                                $subAccount['ProviderCode'] = 'delta';

                                if ($rewards['loyaltyAccountNumber'] != ' Not Available') {
                                    $subAccount['ProviderAccountNumber'] = $rewards['loyaltyAccountNumber'];
                                } else {
                                    $this->ArchiveLogs = true;
                                    $this->sendNotification("delta. Number ->  Not Available");
                                }
                            }
                            // refs #6491
                            if (((isset($rewards['loyaltyOfferLink']) && $rewards['loyaltyOfferLink'] == 'https://www.hhonors.com') || strstr($displayName, 'Hilton HHonors Card'))
                                // new
                                || (isset($rewards['loyaltyFamily']) && $rewards['loyaltyFamily'] == 'Hilton')) {
                                // detected cards
                                $detectedCard['CardDescription'] = C_CARD_DESC_HHONORS;
                                $this->detectedCards[] = $detectedCard;
                                $this->logger->notice(">>> Skip Hilton HHonors card");

                                continue;
                            }

                            if ((isset($rewards['loyaltyOfferLink']) && $rewards['loyaltyOfferLink'] == 'http://www.spg.com')
                                // new
                                || (isset($rewards['loyaltyFamily']) && $rewards['loyaltyFamily'] == 'Starwood')) {
                                // detected cards
                                $detectedCard['CardDescription'] = C_CARD_DESC_MARRIOTT;
                                $this->detectedCards[] = $detectedCard;
                                $this->logger->notice(">>> Skip Starwood card");

                                continue;
                            }
                            // refs #14496
                            if (strstr($displayName, 'SimplyCash®')) {
                                $this->detectedCards[] = $detectedCard;
                                $this->logger->notice(">>> Skip SimplyCash® card");

                                continue;
                            }// if (strstr($displayName, 'SimplyCash®'))

                            if (isset($subAccount['Balance']) && $subAccount['Balance'] != '') {
                                // refs #14492
                                if (isset($acctHubLinksPageBean['showViewCreditScoreLink']) && $acctHubLinksPageBean['showViewCreditScoreLink']) {
                                    $ficoData[] = [
                                        'parameter' => $cardInfo['encryptedAccountNumber'],
                                        'link'      => "https://online.americanexpress.com/myca/creditscore/us/viewScore?request_type=authreg_acctCreditScore&Face=en_US&sorted_index={$cardInfo['cardSortedIndex']}",
                                    ];
                                }// if (isset($acctHubLinksPageBean['showViewCreditScoreLink']) && $acctHubLinksPageBean['showViewCreditScoreLink'])

                                $this->AddSubAccount($subAccount);
                                // detected cards
                                if (!in_array($detectedCard['CardDescription'], [C_CARD_DESC_DELTA, C_CARD_DESC_HHONORS, C_CARD_DESC_MARRIOTT])) {
                                    if (strstr($subAccount['DisplayName'], 'Membership Rewards')) {
                                        $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $rewards['loyaltyAccountNumber']);
                                    } else {
                                        $detectedCard['CardDescription'] = C_CARD_DESC_ACTIVE;
                                    }
                                    // refs #12211
//                                    $this->http->Log("<pre>".var_export($subAccount, true)."</pre>", false);
                                    if (!in_array($subAccount['Code'], $subAccountCodes)) {
                                        $this->logger->debug("subAccountBalance: {$subAccountBalance}");
                                        $subAccountCodes[] = $subAccount['Code'];

                                        if (is_null($subAccount['Currency'])) {
                                            $subAccount['Balance'] = str_replace([',', '.'], ['', ','], $subAccount['Balance']);
                                        } else {
                                            $subAccount['Balance'] = str_replace([',', '$'], '', $subAccount['Balance']);
                                        }
                                        $subAccountBalance += $subAccount['Balance'];
                                        $this->logger->debug("subAcc Balance: {$subAccount['Balance']}");
                                        $this->logger->debug("subAccountBalance: {$subAccountBalance}");

                                        if ($membershipRewards && is_null($subAccount['Currency'])) {
                                            $membershipRewardsBalance += str_replace([',', '.'], ['', ','], $subAccount['Balance']);
                                        }
                                    }// if (!in_array($subAccount['Code'], $subAccountCodes))
                                }// if (!in_array($detectedCard['CardDescription'], array(C_CARD_DESC_DELTA, C_CARD_DESC_HHONORS, C_CARD_DESC_MARRIOTT)))

                                foreach ($this->detectedCards as $dCard) {
                                    if (isset($dCard['Code']) && $dCard['Code'] == $detectedCard['Code']) {
                                        $detectedCard['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $rewards['loyaltyAccountNumber']);
                                    }
                                }
                                $this->detectedCards[] = $detectedCard;
                            } else {
                                $this->logger->debug(var_export($detectedCard, true), ['pre' => true]);
                                // detected cards
                                $this->detectedCards[] = $detectedCard;
                            }
                        }// if (isset($rewards['loyaltyAccountNumber']) && isset($rewards['pointBalance']) && isset($rewards['programName']))
                    }
                }// if (isset($summary['membershipRewardsBean']))

                // Collect non US card links    // refs #14876
                if (!empty($nonUSCardURLs)) {
                    $this->logger->info('Non US cards', ['Header' => 3]);
                    $nonUSCardChecker = clone $this;
                    $nonUSCardChecker->Properties['SubAccounts'] = [];

                    foreach ($nonUSCardURLs as $key => $nonUSCardURL) {
                        $nonUSCardChecker->http->GetURL($nonUSCardURL);
                        $nonUSCardChecker->parseNonUS();
                    }// foreach ($nonUSCardURLs as $nonUSCardURL)
                    $this->logger->debug(var_export($nonUSCardChecker->Properties['SubAccounts'], true), ['pre']);

                    foreach ($nonUSCardChecker->Properties['SubAccounts'] as $sAcc) {
                        $detectedAcc = $sAcc;

                        if (!isset($sAcc['Currency'])) {
                            $sAcc['Balance'] = str_replace([',', '.'], ['', ','], $sAcc['Balance']);
                        } else {
                            $sAcc['Balance'] = str_replace([',', '$'], '', $sAcc['Balance']);
                        }
                        $subAccountBalance += $sAcc['Balance'];
                        unset($detectedAcc['Balance']);

                        if (!strstr($sAcc['DisplayName'], 'Membership Rewards')) {
                            $detectedAcc['CardDescription'] = C_CARD_DESC_ACTIVE;
                        }
                        $this->detectedCards[] = $detectedAcc;
                    }// foreach ($nonUSCardChecker->Properties['SubAccounts'] as $sAcc)
                    $this->Properties['SubAccounts'] = array_merge($nonUSCardChecker->Properties['SubAccounts'], $this->Properties['SubAccounts']);
                }// if (!empty($nonUSCardURLs))

                // detected cards
                if (!empty($this->detectedCards)) {
                    $this->SetProperty("DetectedCards", $this->detectedCards);
                }

                // refs #12211
                if (!empty($this->Properties['SubAccounts']) && !empty($this->detectedCards)) {
//                    $subAccountBalance = number_format($subAccountBalance, 0, '.', ',');
                    $this->logger->debug("Summary of subAccounts: " . $subAccountBalance);
//                    $membershipRewardsBalance = number_format($membershipRewardsBalance, 0, '.', ',');
                    $this->logger->debug("Membership Rewards Balance: " . $membershipRewardsBalance);

                    if ($membershipRewardsBalance === $subAccountBalance) {
                        $this->SetBalance($membershipRewardsBalance);
                        $countSubAccounts = 0;

                        foreach ($this->Properties['SubAccounts'] as $subAccount) {
                            if (!isset($subAccount['ProviderCode'])) {
                                $countSubAccounts++;
                            }
                        }
                        $this->logger->debug("count subAccounts: $countSubAccounts");

                        for ($i = 0; $i < $countSubAccounts; $i++) {
                            $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
                        }
                    }// if ($membershipRewardsBalance === $subAccountBalance)
                }// if (isset($this->Properties['SubAccounts']) && !empty($this->detectedCards))

                $this->logger->debug(var_export($ficoData, true), ['pre' => true]);
                // refs #14492
                if (!empty($ficoData)) {
                    $this->logger->info('FICO® Score', ['Header' => 3]);

                    foreach ($ficoData as $data) {
                        $this->http->PostURL($data['link'], ['parameter' => $data['parameter']]);

                        $codedJson = $this->http->FindPreg("/var\s*ficoJSON\s*=\s*CS\.utilities\.decodeJSONString\('([^']+)'/ims");
                        $json = urldecode($codedJson);
                        $json = $this->http->JsonLog($json, 3, true);

                        if (isset($json['creditScorePageBean'][0])) {
                            $creditScorePageBean = $json['creditScorePageBean'][0];
                            $ficoScores = ArrayVal($creditScorePageBean, 'ficoScores', []);
                            $ficoScoreData = [];

                            foreach ($ficoScores as $ficoScore) {
                                $date = str_split($ficoScore['scoreCalculatedDate']);
//                                $this->logger->debug(var_export($date, true), ['pre' => true]);
                                if (count($date) != 8) {
                                    continue;
                                }
                                $date = $date[0] . $date[1] . "/" . $date[2] . $date[3] . "/" . $date[4] . $date[5] . $date[6] . $date[7];
                                $this->logger->debug($date);
                                $date = strtotime($date);
                                $this->logger->debug($date);

                                if (!isset($ficoScoreData['FICOScoreUpdatedOn']) || $date > $ficoScoreData['FICOScoreUpdatedOn']) {
                                    $ficoScoreData = [
                                        'Balance'            => $ficoScore['score'],
                                        'FICOScoreUpdatedOn' => $date,
                                    ];
                                }
                            }// foreach ($ficoScores as $ficoScore)

                            if (!empty($ficoScoreData)) {
                                // FICO® SCORE
                                $fcioScore = $ficoScoreData['Balance'];
                                // FICO Score updated on
                                $fcioUpdatedOn = $ficoScoreData['FICOScoreUpdatedOn'];

                                if ($fcioScore && $fcioUpdatedOn) {
                                    $this->SetProperty("CombineSubAccounts", false);
                                    $this->AddSubAccount([
                                        "Code"               => "amexFICO",
                                        "DisplayName"        => "FICO® Score 8 (Experian)",
                                        "Balance"            => $fcioScore,
                                        "FICOScoreUpdatedOn" => date('m/d/Y', $fcioUpdatedOn),
                                    ]);
                                }// if ($fcioScore && $fcioUpdatedOn)
                            }// if (!empty($ficoScoreData))
                        }// if (isset($json['creditScorePageBean'][0]))

                        break;
                    }// foreach ($ficoData as $data)
                }// if (!empty($ficoData))
            }// if (isset($json['AccountSummaryBeanList']))
        }// if (isset($codedJson))
        // Pay My Bill
        if ($this->http->FindNodes("//span[contains(text(), 'Pay My Bill')]")
            && ($this->http->FindPreg("/id=\"(?:paybill-cancel|return-to-account-home)\"[^>]*>Go to Account Home<\/a>/")
                || $this->http->FindSingleNode("//a[contains(text(), 'Go to Account Home') or contains(text(), 'No thanks, not now')]/@href"))) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "GrayText2"]/strong[contains(text(), "Unfortunately, we are unable to log you in at this time.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function displayNameForSubAccounts($displayName, $cardInfo)
    {
        $this->logger->notice(__METHOD__);
//        $this->http->Log("cardInfo: <pre>".var_export($cardInfo, true)."</pre>", false);
        $this->logger->debug("DisplayName: " . $displayName);

        if (!empty($displayName)) {
            $displayName = ' (' . $displayName . ')';
        }

        if (isset($cardInfo['cardProductName'])) {
            $displayName = $cardInfo['cardProductName'] . $displayName;
        }
        $this->logger->debug("Step 1.DisplayName: " . $displayName);

        if (!empty($cardInfo['businessName'])) {
            $displayName = 'Business (' . $cardInfo['businessName'] . ') ' . $displayName;
        }
        $this->logger->debug("Step 2.DisplayName: " . $displayName);

        return $displayName;
    }

    public function ParseNonUS()
    {
        $this->logger->notice("parsing non-us");

        if (($this->http->FindPreg("/Non\-U\.S\. Card/ims") && ($url = $this->http->FindPreg("/<a\s+class=\"INTLLINK\"\s+href=\"([^\"]+)\"/ims")))// old ?
            || count($this->http->FindNodes("//a[@class = 'summaryLink']/parent::div[span[@class = 'makeBold']]")) > 0
            || count($this->http->FindNodes("//a[@class = 'summaryLink']/parent::span[@class = 'MrLink']", null, '/(?:Rewards balance|JPMiles)\s*:/ims')) > 0
            || ($this->AccountFields['Login2'] == 'Taiwan'
                && (count($this->http->FindNodes("//span[@class = 'MrLink']")) > 0 || $this->http->FindSingleNode("//table[@id = 'NewSummaryTable']")))
            || $this->http->FindSingleNode("//div[@id = 'card-selector']/a/@href", null, true, "/\.?([^<]+)/ims", 0)
            || $this->http->FindSingleNode("//span[text() = 'Close page guide']")
            || $this->http->FindSingleNode("//span[text() = 'SEE HOW THIS PAGE WORKS']")
            || $this->http->FindNodes("//a[contains(@href, '/myca/intl/rewards/emea/action?request_type=authreg_MrMultipleAccounts')]/@href")
            || $this->http->FindNodes("//a[contains(@href, '/myca/intl/rewards/canlac/action?request_type=authreg_MrMultipleAccounts')]/@href")
            || $this->http->FindNodes("//a[contains(@href, '/myca/intl/acctmaintain/japa/dataExplain.do?request_type=&')]/@href")
            || $this->http->FindNodes("//a[contains(@href, '/myca/intl/estatement/emea/statement.do?')]/@href")
            || $this->http->FindNodes("//a[contains(@href, '/myca/intl/estatement/japa/statement.do?request_type=')]/@href")
            || $this->http->FindNodes("//a[contains(@href, '/myca/intl/estatement/canlac/statement.do?request_type=')]/@href")
            // provider bug workaround
            || ($this->http->FindPreg("/\/myca\/intl\/estatement\/japa\/statement\.do\?request_type=/") && strstr($this->http->currentUrl(), 'japa'))

            || $this->http->ParseForm("ssoform")
            || $this->http->ParseForm("lilo_formLogon")) {
            $this->parseNonUS = true;
        }

        if ($this->parseNonUS) {
            $this->logger->notice("loading international");
            // Old ?
            if (isset($url)) {
                $this->http->FilterHTML = true;
                $this->http->GetURL($url);
                $this->handleRedirectForm();
            }
            // ---
            $this->globalLogin();

            if (
                $this->http->ParseForm("onl-login")
                && $this->attempt <= 1
//                && ($question = $this->http->FindSingleNode('//p[input[@name = "answer"]]/preceding-sibling::p/label'))
//                && isset($this->Answers[$question])
            ) {
                $this->Question = null;
                $this->Step = null;

                throw new CheckRetryNeededException(3, 0);

                if (!isset($this->Answers[$question])) {
                    $this->ParseQuestion();

                    return false;
                }
                $this->http->SetInputValue("answer", $this->Answers[$question]);
                $this->http->PostForm();
            }

            $nodes = $this->http->XPath->query("//title[contains(text(), 'American Express - Go Paperless')]");

            if ($nodes->length > 0) {
                $this->logger->notice("skip paperless");
                $nodes = $this->http->XPath->query("//a[span[contains(text(), 'Remind me later')]]/@href");

                if ($nodes->length == 0) {
                    $nodes = $this->http->XPath->query("//a[contains(text(), 'Remind me later')]/@href");
                }

                if ($nodes->length == 0) {
                    $nodes = $this->http->XPath->query("//a[contains(@title, 'Close this message')]/@href");
                }

                if ($nodes->length > 0) {
                    $u = Html::cleanXMLValue($nodes->item(0)->nodeValue);

                    if (strncmp($u, "http", 4) != 0) {
                        $u = 'https://global.americanexpress.com' . $u;
                    }
                    $this->logger->notice("skipping: $u");
                    $this->http->GetURL($u);
                }
            }
            $skipLink = $this->http->FindSingleNode("//area[contains(@href, '/myca/intl/acctsumm/canlac/interstitialPaperless.do?request_type=&Face=es_AR&CV=CI&PIF=1') and contains(@href, 'CV=CI&PIF=1')]/@href");

            if (isset($skipLink)) {
                $this->logger->notice("skip int paperless");
                $this->http->NormalizeURL($skipLink);
                $this->http->GetURL($skipLink);
            }// if (isset($skipLink))
            // Pay My Bill
            if ($this->http->FindNodes("//span[contains(text(), 'Pay My Bill')]") && ($skipLink = $this->http->FindSingleNode("//a[contains(text(), 'Go to Account Home')]/@href"))) {
                $this->throwProfileUpdateMessageException();
            }

            if ($mm = $this->http->FindPreg('/Everything you need to manage your Card account is right here at your fingertips./')) {
                $this->ErrorCode = ACCOUNT_INVALID_PASSWORD;
                $this->ErrorMessage = $mm;
            }// if ($mm = $this->http->FindPreg('/Everything you need to manage your Card account is right here at your fingertips./'))
            // Maintenance
            if ($message = $this->http->FindSingleNode("
                    //h2[contains(text(), 'Our website will be undergoing scheduled maintenance')]
                    | //div[@id = 'errormsg' and contains(text(), 'Lamentamos que por el momento Servicios Online American Express se encuentra en proceso de mantenimiento. Por favor intente nuevamente.')]
                ")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // As you are logged in as a Supplementary Cardmember you cannot view the Card balance information.
            if ($message = $this->http->FindSingleNode("//div[contains(text(), 'As you are logged in as a Supplementary Cardmember you cannot view the Card balance information')]")) {
                $this->SetBalanceNA();
            }

            //# Name
            $this->ParseName();
            // try multiple rewards
            $rows = $this->http->XPath->query("//table[@class = 'summaryTable' or @id = 'summaryTable' or @class = 'newSummaryTable']");
            $this->logger->debug("rows found: " . $rows->length);

            if ($rows->length == 0 && (in_array($this->AccountFields['Login2'], ['Hong Kong'])
                || $this->http->FindPreg("/Face=en_HK&/ims", false, $this->http->currentUrl()))) {
                $this->http->FilterHTML = false;
                $this->http->GetURL($this->http->currentUrl());
                $rows = $this->http->XPath->query("//table[@class = 'summaryTable' or @id = 'summaryTable' or @class = 'newSummaryTable']");
                $this->logger->debug("rows found: " . $rows->length);
                $this->http->FilterHTML = true;
            }

            $balanceLinks = [];
            $cancelledCount = 0;
            $referredCount = 0;
            $subAccountCount = $rows->length;

            for ($n = 0; $n < $rows->length; $n++) {
                $row = $rows->item($n);
                $this->logger->debug("row " . $n);

                // code
                $code = $this->http->FindSingleNode(".//div[@class = 'summaryTitles']/a[@class = 'crPointer']", $row);

                if (!isset($code)) {
                    $code = $this->http->FindSingleNode(".//div[@class = 'summaryTitles'][contains(text(),'XXX')]", $row);
                }

                if (!isset($code)) {
                    $code = $this->http->FindSingleNode(".//a[contains(text(),'XXX')]", $row);
                }

                if (!isset($code)) {
                    $code = $this->http->FindSingleNode("ancestor::div[contains(@id, 'summaryBoxAvailableCard')]//a[contains(text(),'XXX')]", $row);
                }
                $code = preg_replace("/XXX\s*-\s*/", '', $code);
                $this->logger->debug("code: " . $code);

                // title
                $title = $this->http->FindSingleNode(".//span[@class = 'cardTitle' and not(contains(@title, 'This link'))]/a", $row);

                if (!isset($title)) {
                    $title = $this->http->FindSingleNode(".//img[contains(@id, 'cardImage') and not(contains(@title, 'This link'))]/@title", $row);
                }

                if (!isset($title)) {
                    $title = $this->http->FindSingleNode("ancestor::div[contains(@id, 'summaryBoxAvailableCard')]//div[@class = 'cardDescription bold']/h2/a", $row);
                }

                if (!isset($title)) {
                    $title = $this->http->FindSingleNode("preceding::div[@class = 'cardTitleWrap'][1]//span[@class = 'cardTitle']", $row);
                }

                if (!isset($title)) {
                    $title = $this->http->FindSingleNode("ancestor::div[contains(@id, 'summaryBoxAvailableCard')]//div[@class = 'cardAccountNumber']", $row);
                }
                $this->logger->debug("title: " . $title);
                $num = null;
                $balanceLink = $this->http->FindSingleNode("ancestor::div[contains(@id, 'summaryBoxAvailableCard')]//a[@class = 'summaryLink' and contains(@href, 'rewards')]/@href", $row);
                $this->logger->debug("[balanceLink]: {$balanceLink}");

                // detected cards
                $detectedCards = [
                    "Code"        => "amex" . $code,
                    "DisplayName" => ($this->AccountFields['Login2'] == 'Taiwan') ? $title . " (ending in {$code})" : $title . " " . $code,
                ];
                // skip cancelled
                if ($this->http->FindSingleNode("parent::div//div[@id = 'CanceledCollection_Basic' or @id = 'Canceled_Basic_WithBal']", $row) !== null) {
                    // detected cards
                    $detectedCards['CardDescription'] = C_CARD_DESC_CANCELLED;
                    $this->detectedCards[] = $detectedCards;
                    $this->logger->notice("this card is cancelled");
                    $cancelledCount++;

                    continue;
                }
                // skip linked
                if ($this->http->FindSingleNode(".//a[contains(text(), 'balance is:')]/following-sibling::span[contains(text(), 'Refer to Primary Card')]", $row) !== null
                    || $this->http->FindSingleNode(".//span[contains(text(), 'Please view your points balance under Card')]", $row) !== null
                    || $this->http->FindSingleNode(".//span[contains(text(), 'Ihren Punktestand finden Sie unter folgender Karte')]", $row) !== null
                    || $this->http->FindSingleNode("ancestor::div[contains(@id, 'summaryBoxAvailableCard')]", $row, false, '/(?:Please view your points balance under Card|Ihren Punktestand finden Sie unter folgender Karte|請查閱您的卡片)/ims') !== null) {
                    // get MR Account #
                    if (!empty($balanceLink)) {
                        $browser = clone $this->http;
                        $this->http->brotherBrowser($browser);
                        $this->http->NormalizeURL($balanceLink);
                        $browser->GetURL($balanceLink);
                        // Taiwan
                        $this->taiwanMRform($browser);
                        $num = $browser->FindPreg('/\:(\d+)<\/font>\s*<\/td>\s*<\/tr><\/table>/ims');
                    }

                    if (empty($num)) {
                        $this->sendNotification("refs #16167. Need to change const");
                    }
                    $detectedCards['CardDescription'] = sprintf(C_CARD_DESC_AMEX_LINKED, $num);
                    $this->detectedCards[] = $detectedCards;
                    $this->logger->notice("this card is linked");
                    $referredCount++;

                    continue;
                }

                $balance = $this->http->FindSingleNode('.//a[@class="summaryLink"]/following::span[1]', $row, false);

                if (!isset($balance)) {
                    $balance = $this->http->FindSingleNode('.//a[@class="summaryLink"]/following::span[1][@class="makeBold"]', $row, false);
                }

                if (!isset($balance)) {
                    $balance = $this->http->FindSingleNode('following::div[@id = "cashBack"][1]//a[@class="summaryLink"]/following::span[1][@class="makeBold"]', $row, false, null, 0);
                }

                if (!isset($balance)) {
                    $balance = $this->http->FindSingleNode("ancestor::div[contains(@id, 'summaryBoxAvailableCard')]", $row, false, '/Membership Rewards (?:balance|puntensaldo|\-palkinto\-ohjelman\s*pistesaldosi)\s*:\s*([\d\.\,\-]+)/ims');
                }

                if (!isset($balance)) {
                    $balance = $this->http->FindSingleNode(".//div[@class = 'summaryTitles' and contains(text(), 'Rewards points balance is')]/span[@class = 'makeBold']", $row, false);
                }

                if (!isset($balance) || $balance == 'Umsätze ansehen') {
                    $balance = $this->http->FindSingleNode("//a[contains(text(), 'Membership Rewards Punktestand')]/parent::span", null, true, "/:\s*([^<]+)/ims");
                }

                if (($this->AccountFields['Login2'] == 'Taiwan' || $this->AccountFields['Login2'] == 'Chinese' || $this->AccountFields['Login2'] == 'India') && is_numeric($code)) {
                    $balance = $this->http->FindSingleNode("//a[contains(text(), 'XXX - " . $code . "')]/ancestor::div[@id = 'cardDetails']/following-sibling::div[1]//span[@class = 'MrLink']", null, false, "/:\s*([^<]+)/ims");
                }

                if (!isset($balance)) {
                    $balance = $this->http->FindSingleNode('.//div[@class="summaryTitles"]/span[@class="makeBold"]', $row, false, null, 0);
                }

                $this->logger->debug("balance:[" . $balance . "]");

                if ($this->http->FindPreg('/XXX/', false, $balance)) {
                    unset($balance);
                } // must be balanceLink

                if (!isset($title) || !isset($code)) {
                    continue;
                }
                $title = str_ireplace("view latest transactions for the ", "", $title);
                $title = str_ireplace("view latest transactions for", "", $title);

                if ($this->AccountFields['Login2'] != 'Taiwan' && $this->AccountFields['Login2'] != 'Chinese') {
                    $title = trim(preg_replace("/[^a-z\d\,\.\-]/ims", " ", $title));
                }
                $b = new stdClass();
                $b->title = $title;
                $b->code = $code;
                $this->logger->debug("title:[" . $b->title . "]");
                $this->logger->debug("code:[" . $b->code . "]");

                if (isset($balance)) {
                    $b->balance = $balance;
                }

                if (empty($balanceLink)) {
                    $balanceLink = $this->http->FindSingleNode('.//a[contains(@href, "/myca/intl/estatement/japa/statement.do")]/@href', $row, false, null, 0);
                }

                if (empty($balanceLink)) {
                    $balanceLink = $this->http->FindSingleNode('.//a[contains(@class, "summaryLink")]/following::span[1][@class="makeBold"]/preceding::a[1]/@href', $row, false);
                }

                if (empty($balanceLink)) {
                    $balanceLink = $this->http->FindSingleNode('.//a[contains(@class, "summaryLink") and contains(@href, "rewards")]/@href', $row, false);
                }

                if (empty($balanceLink)) {
                    $balanceLink = $this->http->FindSingleNode('.//a[contains(@class, "summaryLink") and contains(@href, "statement")]/@href', $row, false);
                }

                if (!empty($balanceLink) && !$this->http->FindPreg('/^http/ims', false, $balanceLink)) {
                    $this->http->NormalizeURL($balanceLink);
                }
                $this->logger->debug("balanceLink:[" . $balanceLink . "]");

                if (!empty($balanceLink)) {
                    $this->logger->notice("trying to follow rewards link");
                    $browser = clone $this->http;
                    $this->http->brotherBrowser($browser);
                    $this->http->NormalizeURL($balanceLink);
                    $browser->GetURL($balanceLink);

                    if ($browser->ParseForm("interstitial")) {
                        $browser->PostForm();
                    }
                    // Taiwan
                    $this->taiwanMRform($browser);

                    if (!isset($b->balance)) {
                        $b->balance = $browser->FindSingleNode("//span[contains(text(), 'Your current points balance is')]", null, false, '/Your current points balance is ([\d\.\,]+)$/ims');
                    }

                    if (!isset($b->balance)) {
                        $b->balance = $browser->FindSingleNode('//span[contains(text(), "Total miles transferred to your Asia Miles account")]/following::span[1]');
                    }

                    if (!isset($b->balance)) {
                        $b->balance = $browser->FindSingleNode("(//td[@class = 'ptsSummaryTextBold'])[last()]");
                    }

                    if (!isset($b->balance)) {
                        $b->balance = $browser->FindSingleNode("//span[contains(text(), 'Su balance de puntos es')]/following-sibling::span[1]", null, true, '/([\d\.\,]+)/ims');
                    }

                    if (!isset($b->balance)) {
                        $b->balance = $browser->FindSingleNode("//span[contains(text(), 'Membership Rewards points balance is')]/following-sibling::span[1]", null, true, '/([\d\.\,]+)/ims');
                    }

                    if (!isset($b->balance) && $this->AccountFields['Login2'] == 'Taiwan') {
                        $b->balance = $browser->FindSingleNode("//span[@class = 'MrLink']", null, false, "/:\s*([^<]+)/ims");
                    }

                    $num = $browser->FindPreg('/\:(\d+)<\/font>\s*<\/td>\s*<\/tr><\/table>/ims');
                    $this->logger->debug("[Membership Rewards #]: {$num}");

                    $this->logger->debug("linkBalance:[" . $b->balance . "]");
                }

                if ($this->AccountFields['Login2'] == 'Taiwan') {
                    $displayName = $b->title . " (ending in {$b->code})";
                } else {
                    $displayName = Html::cleanXMLValue($b->title . " " . $b->code);
                }

                if (!isset($b->balance)) {
                    $this->logger->notice(">> Skip card without balance");

                    if (strstr($b->title, 'AeroplanPlus')) {
                        $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Air Canada Aeroplan (Altitude)', 2], C_CARD_DESC_UNIVERSAL);
                    } else {
                        $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                    }
                    // detected cards
                    $this->detectedCards[] = [
                        "Code"            => 'amex' . $b->code,
                        "DisplayName"     => $displayName,
                        "CardDescription" => $cardDescription,
                    ];

                    continue;
                }
                $b->balance = str_ireplace(",", "", $b->balance);
                $b->balance = str_ireplace(".", "", $b->balance);
                $b->balance = str_ireplace(" ", "", $b->balance);

                if (!$this->http->FindPreg("/^\-?[0-9]+$/", false, $b->balance)) {
                    $b->balance = null;
                } // this card under other card, so what is total balance?

                if ($this->AccountFields['Login2'] == 'Taiwan') {
                    if (empty($b->balance) && $b->balance != 0) {
                        $this->detectedCards[] = [
                            "Code"            => 'amex' . $b->code,
                            "DisplayName"     => $displayName,
                            "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                        ];

                        continue;
                    }
                }

                if (isset($b->balance)) {
                    $this->AddSubAccount([
                        "Code"        => 'amex' . $b->code,
                        "DisplayName" => (empty($num) || $this->AccountFields['Login2'] == 'Taiwan') ? $displayName : "Membership Rewards ({$num})",
                        "Balance"     => $b->balance,
                    ], true);

                    if (empty($num)) {
//                        $this->sendNotification("refs #16167. Need to change const");
                        $CardDescription = C_CARD_DESC_ACTIVE;
                    } else {
                        $CardDescription = sprintf(C_CARD_DESC_AMEX_LINKED, $num);
                    }

                    // detected cards
                    $this->detectedCards[] = [
                        "Code"            => 'amex' . $b->code,
                        "DisplayName"     => $displayName,
                        "CardDescription" => $CardDescription,
                    ];
                } else {
                    $this->logger->notice(">>> Skip card without balance");
                    // detected cards
                    $this->detectedCards[] = [
                        "Code"            => 'amex' . $b->code,
                        "DisplayName"     => $displayName,
                        "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                    ];
                    $this->logger->debug(var_export($b, true), ['pre' => true]);
                }
            }

            // detected cards
            if (!empty($this->detectedCards)) {
                $this->SetProperty("DetectedCards", $this->detectedCards);
            }

            if (!$this->parsed()) {
                $this->logger->notice("try to load rewards");
                $url = $this->http->FindSingleNode("//a[contains(text(), 'Membership Rewards Online')]/@href");

                if (!isset($url)) {
                    $this->logger->notice("try to load rewards, v2");
                    $url = $this->http->FindSingleNode("//a[contains(text(), 'Click here to view your Membership Rewards points balance')]/@href");
                }

                if (!isset($url)) {
                    $this->logger->notice("try to load rewards, v3");
                    $url = $this->http->FindSingleNode("//a[@class = 'summaryLink' and contains(@href, '.americanexpress.com/myca/rewards/emea/action?request_type=authreg_MrMultipleAccounts&Face=')]/@href");
                }

                if (!isset($url)
                    && ($this->AccountFields['Login2'] != 'Australia') && !strstr($this->http->currentUrl(), 'Face=en_AU')
                    && ($this->AccountFields['Login2'] != 'Japan') && !strstr($this->http->currentUrl(), 'Face=ja_JP')) {
                    $this->logger->notice("try to load rewards, v5");
                    $url = $this->http->FindSingleNode("(//a[contains(@href, 'https://global.americanexpress.com/myca/intl/rewards/japa/action?request_type=authreg_MrMultipleAccounts&Face=')]/@href)[1]");
                }

                if (!isset($url)) {
                    $this->logger->notice("try to load rewards, v6");
                    $url = $this->http->FindSingleNode("//a[@class = 'summaryLink' and contains(@href, '/myca/intl/rewards/canlac/action?request_type=authreg_MrMultipleAccounts&Face=')]/@href");
                }

                if (!isset($url)) {
                    //# Rewards is currently unavailable
                    $this->logger->notice("Your Rewards");

                    if ($message = $this->http->FindSingleNode("//div[@id = 'rewards-warning-message']")) {
                        $this->ErrorCode = ACCOUNT_WARNING;
                        $this->ErrorMessage = $message;
                    }
                }

                if (isset($url)) {
                    $this->http->NormalizeURL($url);
                    $this->http->GetURL($url);

                    if ($this->http->ParseForm("interstitial")) {
                        $this->http->PostForm();
                    }

                    $balance = $this->http->FindPreg("/Your current points balance is ([^<]+)/ims");

                    if (!isset($balance)) {
                        $balance = $this->http->FindSingleNode("//table[@title = 'Select your MR Number']//td[@class = ' txtSmaller paddedLeft']");

                        if (isset($balance)) {
                            $this->addMembershipRewards(preg_replace("/[^\d\-]/ims", "", $balance));
                        }
                    }
                    // india
                    if (!$this->parsed()) {
                        $balance = $this->http->FindPreg("/You currently have <B>([^<]+)<\/B> points to redeem or transfer/ims");

                        if (isset($balance)) {
                            $this->addMembershipRewards(preg_replace("/[^\d\-]/ims", "", $balance));
                        }
                    }
                    // india
                    if (!$this->parsed()) {
                        $balance = $this->http->FindPreg("/You currently have\s*<b>([^<]+)<\/b>\s*king miles/ims");

                        if (isset($balance)) {
                            $this->addMembershipRewards(preg_replace("/[^\d\-]/ims", "", $balance));
                        }
                    }

                    if (!$this->parsed()) {
                        $balance = $this->http->FindPreg("/Your current points balance is ([^<]+)</ims");

                        if (isset($balance)) {
                            $this->addMembershipRewards(preg_replace("/[^\d\-]/ims", "", $balance));
                        }
                    }

                    if (!$this->parsed() && $this->AccountFields['Login2'] == 'Taiwan') {
                        $balance = $this->http->FindPreg("/<tr bgcolor=\"#FFFFCC\"><td><font face=\"arial, helvetica, sans serif\" size=\"-1\">\s*<b>([^<]+)/");

                        if (isset($balance)) {
                            $this->addMembershipRewards(preg_replace("/[^\d\-]/ims", "", $balance));
                        }
                    }// if (!$this->parsed() && $this->AccountFields['Login2'] == 'Taiwan')
                }
            }

            if (!$this->parsed()) {
                $this->logger->notice("try to parse rewards like Membership Rewards Punktestand : 30.665");
                $this->addMembershipRewards($this->http->FindPreg("/<a[^>]*>\s*Membership\s*rewards[^<]*<\/a>\s*:\s*([\d\.\,]+)\s*</ims"));
                // 'Membership Rewards' Points Balance - puntos
                if (!$this->parsed() && !isset($balance) && $this->AccountFields['Login2'] == 'Argentina') {
                    $this->addMembershipRewards($this->http->FindSingleNode("//span[@class = 'points']/strong[contains(text(), 'punt')]"));
                }
            }
            // brazil
            if (($btn = $this->http->FindPreg("/delegaTopMenu\('(hidMnuBtnSobreProgramaMR)'\)/ims"))
                || ($btn = $this->http->FindPreg("/delegaTopMenu\('(hidMnuBtnSaibaInscricaoMR)'\)/ims"))) {
                $this->logger->notice("selecting brazil rewards top menu");

                if ($this->http->ParseForm("linkDummyForm")) {
                    $this->http->Form['linkDummyForm:_link_hidden_'] = $btn;
                    $this->http->Form['autoScroll'] = "0,0";
                    $this->http->Form['rstSrc'] = "S";
                    $this->http->PostForm();
                    $this->ParseName();
                    $balance = $this->http->FindSingleNode("//td[span[contains(text(), 'Saldo de pontos')]]/following::td[2]");

                    if (isset($balance)) {
                        $this->addMembershipRewards(str_replace(".", "", $balance));
                    } elseif ($this->http->FindSingleNode("//img[contains(@alt, 'INSCREVA-SE AGORA')]/@alt")) {
                        throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    return true;
                }// if ($this->http->ParseForm("linkDummyForm"))
            }
            // other one
            if (!$this->parsed()) {
                $nodes = $this->http->XPath->query("//form/input[@name='Face']/@value");
                $buttons = $this->http->XPath->query("//form/input[@name='bt_action_Rewards_Rewards']/@value");

                if (($nodes->length > 0) && ($buttons->length > 0)) {
                    $this->logger->notice("loading international, step 2");
                    $this->http->FilterHTML = false;
                    $this->http->PostURL("https://global.americanexpress.com/myca/estatement/emea/action?", [
                        "Face"                      => Html::cleanXMLValue($nodes->item(0)->nodeValue),
                        "request_type"              => $this->http->FindSingleNode("//input[@name = 'request_type']/@value", null, false, null, 0),
                        "INPUTTYPE"                 => "BUTTON",
                        "bt_action_Rewards_Rewards" => Html::cleanXMLValue($buttons->item(0)->nodeValue),
                    ]);
                    $nodes = $this->http->XPath->query("//table[contains(@summary, 'this section shows the summary of MR Statement during this period')]/tbody/tr[3]/td");
                    $this->logger->notice("cells found: " . $nodes->length);

                    if ($nodes->length == 0) {
                        $this->logger->notice("try to not cleanup");
                        $nodes = $this->http->XPath->query("//table[tbody/tr/th[@id = 'FinalTotal']]//tr[3]/td");
                        $this->logger->notice("cells found: " . $nodes->length);

                        for ($n = 0; $n < $nodes->length; $n++) {
                            $this->logger->notice("cell $n: " . $nodes->item($n)->nodeValue);
                        }
                    }

                    if ($nodes->length == 6) {
                        $this->logger->notice("international matched");
                        // Valid on 18 May 2018
//                        $this->sendNotification("refs #16167. Called deprecated method: international");
                        $this->Properties["SubAccounts"][] = [
                            "Code"        => "amexinternational",
                            "DisplayName" => "Membership Rewards",
                            "Balance"     => str_replace(".", "", $this->http->FindSingleNode("//tr[th[contains(text(), 'TOTAL DE POINTS')]]/following-sibling::tr/td[3]")),
                            //							"Balance" => str_replace(".", "", Html::cleanXMLValue($nodes->item(5)->nodeValue)),
                            //							'PointsUsed' => Html::cleanXMLValue($nodes->item(1)->nodeValue),
                            //							'Reinstated' => Html::cleanXMLValue($nodes->item(2)->nodeValue),
                            'Adjusted'  => Html::cleanXMLValue($nodes->item(4)->nodeValue),
                            'Forfeited' => Html::cleanXMLValue($nodes->item(3)->nodeValue),
                        ];
                    }
                    // internationals
                    if (!$this->parsed()) {
                        $this->ParseEurope("swiss", "Verfügbare Punkte insgesamt");
                    }
                }

                // error on site (AccountID: 2554890)
                if ($this->AccountFields['Login'] == 'jonckhep4') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            // try to switch card
            $url = $this->http->FindSingleNode("//a[contains(@href, 'SwitchCard')]/@href");
            $face = $this->http->FindPreg("/Face=([^&]+)/ims", false, $this->http->currentUrl());
            $folder = $this->http->FindPreg("/isummary\/([^\/]+)\/summary\.do/ims", false, $this->http->currentUrl());
            $multipleCards = $this->http->FindNodes("//div[@id = 'card-list']/div/div[contains(@class, 'card')]/@rel");
            $errorsMultipleCards = $this->http->FindNodes("//div[@id = 'message-present' and contains(text(), 'Your Card has been cancelled')]");
            $countCard = count($multipleCards);
            $this->logger->notice("Total cards -> " . $countCard);
            $this->logger->debug("face -> " . $face);
            $this->logger->debug("folder -> " . $folder);

            // stupid user bug fix
            if ($face == 'ja_JP' && $folder == 'japa' && $this->AccountFields['Login2'] != 'Japan') {
                $this->logger->notice("stupid user bug fix, set Region Japan");
                $this->AccountFields['Login2'] = 'Japan';
            }

            $this->parseCardBalance();

            if ($countCard == 0 && $this->AccountFields['Login2'] == 'Japan') {
                $multipleCards = $this->http->FindPregAll("/div class=\"card[^\"]*\" rel=\"([^\"]+)/ims", null, PREG_PATTERN_ORDER, true);
                $this->logger->notice(">>> multipleCards:");
                $this->logger->debug(var_export($multipleCards, true), ["pre" => true]);
                $countCard = count($multipleCards);
                $this->logger->notice("Total cards -> " . $countCard);
                $fields = $this->http->FindPregAll("/input type='hidden' name='Hidden' value='([^\']+)/ims");
            }// if ($countCard == 0 && $this->AccountFields['Login2'] == 'Japan')

            if ((isset($url) || ($countCard > 1 && count($errorsMultipleCards) != $countCard)) && isset($face)) {
                $this->logger->notice("loading card switcher");
                $url = "https://global.americanexpress.com/myca/intl/isummary/emea/" . substr($url, 2);

                if ($this->http->ParseForm("j-session-form") || !empty($fields)) {
                    $this->http->FormURL = $url . "&Face=en_GB&sorted_index=0";
                    $this->logger->debug("posting to card switcher");
                    $this->http->FilterHTML = false;
                    $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
                    // Australia
                    if ($this->AccountFields['Login2'] == 'Australia') {
                        $this->logger->debug("loading card switcher (Australia)");

                        foreach ($multipleCards as $index) {
                            $this->logger->debug("loading card $index");

                            if ($this->http->ParseForm("j-session-form")) {
                                $this->http->FormURL = "https://global.americanexpress.com/myca/intl/isummary/{$folder}/summary.do?request_type=&Face={$face}&method=displaySummary&sorted_index=" . $index;
                                $this->logger->debug("clicking select card $index");

                                if ($this->http->PostForm()) {
                                    $this->parseCardBalance();
                                }// if ($this->http->PostForm())
                            }// if ($this->http->ParseForm("j-session-form"))
                        }// foreach($indexes as $index)
                    }// if ($this->AccountFields['Login2'] == 'Australia')
                    // Other regions
                    else {
                        $this->logger->debug("loading card switcher ({$this->AccountFields['Login2']})");

                        foreach ($multipleCards as $index) {
                            $this->logger->debug("loading card $index");

                            if ($this->http->ParseForm("j-session-form") || !empty($fields)) {
                                $this->http->FormURL = "https://global.americanexpress.com/myca/intl/isummary/{$folder}/summary.do?method=reloadCardSummary&Face={$face}&sorted_index=" . $index;
                                $this->logger->debug("clicking select card $index");

                                // Japan
                                if (!empty($fields)) {
                                    foreach ($fields as $mValue) {
                                        $data[] = urlencode("Hidden") . "=" . urlencode($mValue);
                                    }
                                    $this->http->PostURL($this->http->FormURL, implode("&", $data));
                                    $this->parseCardBalance();
                                }

                                if ($this->http->PostForm()) {
                                    $this->parseCardBalance();
                                }// if ($this->http->PostForm())
                            }// if ($this->http->ParseForm("j-session-form"))
                        }// foreach($indexes as $index)
                    }
                }// if($this->http->ParseForm("j-session-form"))
            }// if (isset($url) && isset($face))

            // not enrolled?
            if (!$this->parsed()) {
                $this->logger->notice("check not enrolled");

                if ($this->http->FindPreg("/<title>MR not enrolled/ims") !== null) {
                    $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
                    $this->ErrorMessage = "You are not enrolled into Membership rewards"; /*checked*/ /* the original was spanish, translated to: 'Check Points should be For Cardholder 1 registered in Membership Rewards' */
                }
                //# Your Card account has been cancelled
                if (($message = $this->http->FindPreg("/>\s*(Your Card account has been cancelled\.)/ims"))
                    || ($message = $this->http->FindPreg("/>\s*(Your Card has been cancelled[^\.]*\.?)/ims"))
                    || ($message = $this->http->FindPreg("/(Il conto Carta risulta cancellato)/ims"))
                    // Ihre Kreditkarte ist gekündigt!
                    || ($message = $this->http->FindPreg("/(Ihre Kreditkarte ist gek\&\#252\;ndigt!)/ims"))
                    // Votre carte est annulée
                    || ($message = $this->http->FindPreg("/(Votre carte est annul\&\#233\;e)/ims"))
                    || ($message = $this->http->FindPreg("/>\s*(Esta tarjeta ha sido cancelada\.)/ims"))) {
//                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    $this->SetBalanceNA();
                }
                // Your Card account has been cancelled and there is an outstanding balance.
                if ($message = $this->http->FindPreg("/>\s*(Your Card account has been cancelled and there is an outstanding balance\.\s*Please make a payment by calling the number on the back of your Card\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Esta tarjeta ha sido cancelada. Por favor, realiza el pago de tu saldo llamando al número que aparece al reverso de tu tarjeta.
                if ($message = $this->http->FindPreg("/>(Esta tarjeta ha sido cancelada. Por favor, realiza el pago de tu saldo llamando al número que aparece al reverso de tu tarjeta\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# Maintenance
                if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Our Rewards programme is currently unavailable due to scheduled maintenance')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                /**
                 * U kunt alleen punten overschrijven, Rewards bestellen of uw puntentotaal bekijken
                 * als u de basis Kaarthouder1 bent en als u ingeschreven bent bij Membership Rewards.
                 */
                if ($message = $this->http->FindSingleNode('//b[contains(text(), "U kunt alleen punten overschrijven, Rewards bestellen of uw puntentotaal bekijken als u de basis Kaarthouder")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                /**
                 * Onze excuses...
                 *
                 * Het American Express Online Services systeem heeft niet correct gereageerd op uw verzoek.
                 * U kunt uw browser's back knop gebruiken om uw verzoek nogmaals te verkrijgen.
                 * In veel gevallen zal deze tweede poging genoeg zijn. U kunt het ook over enkele minuten nog eens proberen.
                 */
                if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Het American Express Online Services systeem heeft niet correct gereageerd op uw verzoek.')]")) {
                    throw new CheckException("Onze excuses... Het American Express Online Services systeem heeft niet correct gereageerd op uw verzoek.", ACCOUNT_PROVIDER_ERROR);
                }
                // Your Reward information is currently unavailable
                if ($message = $this->http->FindSingleNode("//h3[@class = 'rewards-error' and contains(text(), 'Your Reward information is currently unavailable')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
            }
            // all cancelled
            if (($subAccountCount > 0) && ($cancelledCount > 0) && ($subAccountCount == ($cancelledCount + $referredCount))) {
                $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
                $this->ErrorMessage = "We did not find any cards for which loyalty balances can be tracked via americanexpress.com"; /*checked*/
            }

            if (!$this->parsed()) {
                // total link
                $link = $this->http->FindSingleNode("//a[contains(@href, 'inav=nl_myca_pc_view_points')]/@href");

                if (isset($link)) {
                    $this->logger->notice("loading total");
                    $this->http->GetURL($link);
                    $this->addMembershipRewards($this->http->FindSingleNode("//td[select[@id = 'mrAccount']]/following::td[contains(@style, 'width:50%')]", null, false, null, 0));
                }

                if (!isset($link)) {
                    $this->logger->notice("Membership Rewards -> Available Points");
                    $link = $this->http->FindSingleNode("//h2[contains(text(), 'Membership Rewards')]/following-sibling::p/a[contains(@title, 'Available Points')]/@href");
                    $this->http->NormalizeURL($link);
                    $this->http->GetURL($link);
                    $balance = $this->http->FindPreg("/Your current points balance is([^<]+)</ims");

                    if (isset($balance)) {
                        $this->addMembershipRewards(preg_replace("/[^\d\-]/ims", "", $balance));
                    }
                }// if (!isset($link))

                if (((count($balanceLinks) > 0)
                    || $this->http->FindSingleNode("//input[@id = 'btnTopSair' and @title = 'Sair']/@title"))) {
                    throw new CheckException(self::MESSAGE_NOT_FOUND_BALANCE, ACCOUNT_PROVIDER_ERROR);
                }

                //# The access was not possible. Please try again later or contact the Service to Members.
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Tente novamente mais tarde ou entre em contato com o')]/parent::p")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# Your Card account has been suspended
                if ($message = $this->http->FindPreg("/(Your Card account has been suspended\.)/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# The American Express Online Services system has not properly responded to your request
                if ($message = $this->http->FindPreg("/(The American Express Online Services system has not properly responded to your request\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# You are not a member of this loyalty program
                if ($this->http->FindPreg("/(En effet,\s*pour consulter votre solde de points,\s*vous devez [^<]+ inscrit au programme Membership Rewards et [^<]+ Titulaire d\'une Carte de Base)/ims")
                    // Switzerland
                    || $this->http->FindPreg("/Zum Anzeigen der Punkte m.+ssen Sie Hauptkarteninhaber\&sup1; und im\s*<i>Membership Rewards<\/i>\s*Programm registriert sein\./ims")) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                /*
                 * Sorry ...
                 * Your request was not successful.
                 * If you have tried to access the details of your statement,
                 * the system may not be able to provide all account transactions.
                 */
                if ($message = $this->http->FindSingleNode('//font[contains(text(), "Votre demande n\'a pas abouti. Si vous avez essayé d\'accéder au détail de votre relevé, le système n\'est peut être pas en mesure de fournir la totalité des opérations du compte") or contains(text(), "Votre demande n\'a pas abouti. Si vous avez essay? d\'acc?der au d?tail de votre relev?, le syst?me n\'est peut ?tre pas en mesure de fournir la totalit? des op?rations du compte.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                //# Error message ... The American Express online system could not process the data.
                if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Das American Express Online System konnte die Daten nicht verarbeiten')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                /*
                 * Your Rewards information is currently unavailable.
                 * Please refresh the page or try again later.
                 * If urgent, please call the number on the back of your Card.
                 */
                if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Your Rewards information is currently unavailable.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // Our System is Not Responding
                // You may experience intermittent delays. We apologize for this inconvenience.
                if ($this->http->FindSingleNode("//b[contains(text(), 'Our System is Not Responding')]")) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                // AccountID: 481349
                if ($this->http->FindSingleNode("//div[contains(text(), 'Your payment is overdue')]")) {
                    $this->SetBalanceNA();
                }
                // AccountID: 1285694
                if (isset($this->Properties['DetectedCards']) && count($this->Properties['DetectedCards']) > 0
                    && !empty($this->Properties['Name'])) {
                    $this->SetBalanceNA();
                }
                // AccountID: 1270558
                if (isset($this->Properties['DetectedCards'])
                    && ($rows->length == count($this->Properties['DetectedCards'])
                        || $this->http->FindSingleNode("//span[contains(text(), 'As you are logged in as a Supplementary Cardmember you cannot view the Card balance information.')]")
                        || $this->http->FindSingleNode("//h3[contains(text(), 'Please view your points balance under Card')]")
                        || $this->http->FindSingleNode("//p[contains(text(), 'Check your Aeroplan account for your miles balance')]")
                        || $this->http->FindSingleNode("//h3[contains(text(), 'Consulta tu saldo en puntos en La Tarjeta')]")
                        || in_array($this->AccountFields["Login2"], ['Canada', 'México']))) {
                    $this->SetBalanceNA();
                }
            }

            return true;
        }// if ($this->parseNonUS)
        else {
            return false;
        }
    }

    public function handleRedirectForm()
    {
        $this->logger->notice(__METHOD__);
        $count = 0;

        do {
            $posted = false;
            $onload = $this->http->FindPreg("/<BODY\s+Onload=\"([^\"]+)\"/ims");

            if ($onload == "document.forms[0].submit()" || $onload == "document.login.submit();") {
                $this->logger->debug("onload-submit found. searching form");

                if ($this->http->ParseForm(null, '//form[contains(@action, "/ssofedi/public/saml2sso?") or contains(@action, "https://americanexpress.switchfly.com")]')) {
                    $this->logger->debug("submitting redirect form");
                    $this->http->PostForm();
                    $posted = true;
                    $count++;
                }
            }
        } while ($posted && $count < 5);
    }

    public function globalLogin()
    {
        $this->http->FilterHTML = false;

        if ($this->http->ParseForm("ssoform") || $this->http->ParseForm("lilo_formLogon")) {
            $this->logger->debug("global logon. step 1");
            $this->http->SetInputValue("UserID", $this->AccountFields['Login']);
            $this->http->SetInputValue("USERID", $this->AccountFields['Login']);
            $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("PWD", $this->AccountFields['Pass']);
            $this->http->SetInputValue("hiddenUserID", str_repeat('*', strlen($this->AccountFields['Login'])));
            $this->http->PostForm();
            $this->http->MultiValuedForms = true;

            if ($this->http->ParseForm("LogonForm") || $this->http->ParseForm("lilo_formLogon")) {
                $this->logger->debug("global logon. step 2");
                $this->http->SetInputValue("Password", $this->AccountFields['Pass']);
                $this->http->SetInputValue("PWD", $this->AccountFields['Pass']);
                $this->http->PostForm();

                // need to retry after 10 minute
                if ($message = $this->http->FindPreg("/(?:Te informamos que la Contraseña que estás utilizando para ingresar a Servicios Online se encuentra firmada en otra computadora o bien, la sesión segura no se cerró correctamente\. Por favor espera 10 minutos para volver a intentarlo\.|Te informamos que la Contrase\&ntilde;a que est&aacute;s utilizando para ingresar a Servicios Online se encuentra firmada en otra computadora o bien, la sesi\&oacute;n segura no se cerr&oacute; correctamente\. Por favor espera 10 minutos para volver a intentarlo\.)/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // check question
                if ($this->ParseQuestion()) {
                    return false;
                }
            }// if ($this->http->ParseForm("LogonForm") || $this->http->ParseForm("lilo_formLogon"))
        }// if ($this->http->ParseForm("ssoform") || $this->http->ParseForm("lilo_formLogon"))

        return true;
    }

    public function ParseName()
    {
        $this->logger->notice(__METHOD__);
        //# Name
        if (empty($this->Properties['Name'])) {
            $name = $this->http->FindSingleNode("//div[contains(text(), 'Hello')]", null, true, "/Hello\s*([^<]+)/ims");

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[@class = 'cardmember']/h3");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//span[contains(text(), 'Ihr aktueller Punktestand:')]/parent::span", null, true, '/Willkommen\s*([^:<]+)/ims');
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), 'Guten Tag')]", null, true, "/Guten\s*Tag\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), 'Hallo')]", null, true, "/Hallo\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), 'Bonjour')]", null, true, "/Bonjour\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), 'Hej')]", null, true, "/Hej\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), 'Hola')]", null, true, "/Hola\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), 'Welkom,')]", null, true, "/Welkom\,\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(text(), '您好')]", null, true, "/您好\s*([^<]+)/ims");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(@class, 'hello_text') and not(contains(text(), '您好'))]");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//span[contains(@class, 'hello_text')]", null, true, '/Hello\s*([^<]+)/ims');
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//td[span[contains(text(), 'Nome do Associado')]]/following::td[2]/span");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//div[contains(@class, 'green Loyal_Multi_Acts_CustomerName2')]");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//h1[@id = 'summary-header-nameoncard']");
            }

            if (!isset($name)) {
                $name = $this->http->FindPreg("/id=\"summary-header-nameoncard\">\s*([^\<]+)/");
            }

            if (!isset($name)) {
                $name = $this->http->FindSingleNode("//strong[contains(text(), 'Herzlich willkommen')]", null, true, '/Herzlich\s*willkommen\,\s*([^<]+)/ims');
            }

            //# Set Name
            if (isset($name)) {
                $this->SetProperty("Name", beautifulName($name));
            }
        }
    }

    public function parsed()
    {
        return count($this->Properties['SubAccounts']) > 0;
    }

    /**
     * valid on 23 May 2018.
     *
     * @param $balance
     */
    public function addMembershipRewards($balance)
    {
        $this->logger->notice(__METHOD__);

        if (isset($balance)) {
//            $this->sendNotification("refs #16167. Called deprecated method: addMembershipRewards");
            $this->AddSubAccount([
                "Code"        => "amexMembershiprewards",
                "DisplayName" => "Membership Rewards",
                "Balance"     => $balance,
            ], true);
        }
    }

    /**
     * only for Swiss (and mybe Taiwan).
     *
     * @param $country
     * @param $label
     */
    public function ParseEurope($country, $label)
    {
        $this->logger->notice("checking {$country}");
        $balance = $this->http->FindSingleNode("//tr[td[font[b[contains(text(), '{$label}')]]]]/following::tr[1]/td/font/b");

        if (isset($balance)) {
            $balance = str_replace("'", "", $balance);
            $this->addMembershipRewards($balance);
        }
    }

    public function parseCardBalance()
    {
        $this->logger->notice(__METHOD__);
        // one more
        $this->logger->notice("parseCardBalance");
        $code = $this->http->FindSingleNode("//span[@class = 'acc-num']");
        $displayName = $this->http->FindSingleNode("//h2[@class = 'card-name']");
        // Australia fix
        if (!isset($code, $displayName)) {
            $code = $this->http->FindSingleNode("//div[@id = 'card-details']/h2[@class = 'card-name']/span[@class = 'acc-num']");
            $displayName = $this->http->FindSingleNode("//div[@id = 'card-details']/h2[@class = 'card-name']");
        }
        $balance = $this->http->FindSingleNode("//h3[contains(text(), 'Membership Rewards Points')]/span[@class = 'financial-detail']");

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//div[@class = 'reward-options clearfix']/h3/span[@class = 'financial-detail' or @class = 'balance-data']");
        }
        // American Express 'Membership Rewards' Points Balance - 美國運通「會員酬賓」計劃積分結餘
        if (!isset($balance) && $this->AccountFields['Login2'] == 'Taiwan') {
            $balance = $this->http->FindSingleNode("//b[contains(text(), '給自己獎賞')]/ancestor::tr[1]/following-sibling::tr[3]/td[2]/table//tr[2]/td/font/b");
        }
        // Brazil fix
        if (!isset($balance) && $this->AccountFields['Login2'] == 'Brazil') {
            $balance = $this->http->FindSingleNode("//td[span[contains(text(), 'Saldo de pontos')]]/following-sibling::td[2]/span");
        }
        // refs #11962 duplicating the balance for Velocity Frequent Flyer
        if ($this->http->FindSingleNode("//a[contains(text(), 'Velocity Points Transferred')]") && strstr($displayName, 'American Express Velocity')) {
            $balance = null;
        }

        if ($this->http->FindSingleNode("//a[contains(text(), 'Qantas Points Transferred')]") && strstr($displayName, 'Qantas American Express')) {
            $balance = null;
        }
        // Japan fix
        if (!isset($code, $displayName, $balance) && $this->AccountFields['Login2'] == 'Japan') {
            $displayName = $this->http->FindPregAll("/summary-overview.+card-name\">\s*<span class=\"card-desc\">([^<]+<\/span>\s*<br \/>\s*<span class=\"acc-num\">[^<]+)<\/span>/ims");
            $displayName = array_merge($displayName, $this->http->FindPregAll("/summary-overview.+card-name\">\s*<span class=\"card-desc\">\s*<span[^>]+>([^<]+<\/span>[^<]+<\/span>\s*<br \/>\s*<span class=\"acc-num\">[^<]+)<\/span>/ims"));

            if (isset($displayName[0]) && count($displayName) == 1) {
                $displayName = strip_tags($displayName[0]);
            } else {
                $displayName = null;
            }
            $code = $this->http->FindPreg("/(XXX\-[^\<]+)/", false, $displayName);
            $balance = $this->http->FindPreg("/<span class=\"(?:balance-data|financial-detail)\">\s*([^<]+)<\/span>\s*<\/h3>\s*<\/div>\s*<div id=\"rewards-button-container\"/");
        }// if (!isset($code, $displayName, $balance) && $this->AccountFields['Login2'] == 'Japan')

        $displayName = Html::cleanXMLValue(str_replace('XXX-', ' XXX-', $displayName));
        $displayName = Html::cleanXMLValue(str_replace('ÃÂ', '', $displayName));
        $this->logger->debug("displayName -> {$displayName}");
        $this->addSubAcc($balance, "amex" . md5($displayName) . $code, $displayName);
    }

    public function addSubAcc($balance, $code, $displayName)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("addSubAcc: $balance,  $code,  $displayName");

        if (isset($code) && !empty($displayName) && isset($balance)) {
//            $this->sendNotification("refs #16167. Called deprecated method: addSubAcc");
            $this->AddSubAccount(["Code" => $code, "DisplayName" => $displayName, "Balance" => $balance]);
            // detected cards
            $this->AddDetectedCard(["Code" => $code, "DisplayName" => $displayName, "CardDescription" => C_CARD_DESC_ACTIVE], false, false);
        } elseif (isset($balance)) {
            $this->SetBalance($balance);
        }
        // Corporate Card without balance
        elseif (empty($balance) && !empty($displayName)) {
            if (strstr($displayName, 'Corporate Card')) {
                //# Name
                $this->ParseName();
                $this->SetBalanceNA();
            }

            if ($this->http->FindSingleNode("//span[contains(text(), '" . $code . "')]/parent::h2[@class = 'card-name']/following-sibling::div[@class = 'messages']", null, true, '/cancelled/ims')
                || $this->http->FindSingleNode("//div[@id = 'account-messages']/h2[contains(., 'Your Card account has been cancelled')
                    or contains(., 'Your Card has been cancelled')
                    or contains(., 'Il conto Carta risulta cancellato')
                    or contains(., 'Ihre Kreditkarte ist gek')
                    or contains(., 'Votre carte est annul')
                    or contains(., 'Esta tarjeta ha sido cancelada')]")) {
                $cardDescription = C_CARD_DESC_CANCELLED;
            } elseif ($this->http->FindSingleNode("//h3[contains(text(), 'Please view your points balance under Card') or contains(text(), 'Consulta tu saldo en puntos en La Tarjeta')]")) {
                $cardDescription = C_CARD_DESC_AMEX_LINKED;
                $this->sendNotification("refs #16167. Need to change const");
            }
            // American Express® AeroplanPlus®* Gold Card
            elseif ($this->http->FindSingleNode("//p[contains(text(), 'Check your Aeroplan account for your miles balance')]")) {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Air Canada Aeroplan (Altitude)', 2], C_CARD_DESC_UNIVERSAL);
            }
            // refs #11962 duplicating the balance for Velocity Frequent Flyer
            // American Express Velocity Platinum Card
            elseif ($this->http->FindSingleNode("//a[contains(text(), 'Velocity Points Transferred')]")) {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Virgin Australia (Velocity Frequent Flyer)', 93], C_CARD_DESC_UNIVERSAL);
            } elseif ($this->http->FindSingleNode("//a[contains(text(), 'Qantas Points Transferred')]")) {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Qantas (Business Rewards)', 1067], C_CARD_DESC_UNIVERSAL);
            } else {
                $cardDescription = C_CARD_DESC_DO_NOT_EARN;
            }
            // detected cards
            $this->AddDetectedCard(["Code" => $code, "DisplayName" => $displayName, "CardDescription" => $cardDescription], false, false);
        }// elseif (empty($balance) && !empty($displayName))

        if (!empty($this->detectedCards)) {
            $this->SetProperty("DetectedCards", $this->detectedCards);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //		$arg['CookieURL'] = 'https://home.americanexpress.com/home/mt_personal.shtml';
        //		$arg["URL"] = "https://www99.americanexpress.com/myca/logon/us/action?request_type=LogLogonHandler&location=us_logon2";
        //		$arg["PostValues"]["DestPage"] = urldecode("https%3A%2F%2Fwww99.americanexpress.com%2Fmyca%2Fmr%2Fus%2Faction%3Frequest_type%3Dauthreg_ssologin%26target%3Dhttps%253a%252f%252fwww.membershiprewards.com%252fmyca%252fProcess.aspx");
        return $arg;
    }

    public function ParseItineraries()
    {
        $result = [];

        if ($this->doNotCollectInfo()) {
            return $result;
        }

        $this->http->GetURL("https://travel.americanexpress.com/my-trips?inav=travel_mytrips_gem");

        if ($this->http->ParseForm("digitalItinForm")) {
            $this->http->PostForm();

            $this->seleniumItinerary();
//            $this->logger->debug("start");
//            $this->http->GetURL("https://s-usc1c-nss-238.firebaseio.com/.lp?start=t&ser=33146226&cb=1&v=5&ns=fb-di2-live");
//            $s = $this->http->FindPreg("/,\"s\":\"([^\"]+)/");
//            $id = $this->http->FindPreg("/start','([^\']+)/");
//            $pw = $this->http->FindPreg("/start','[^\']+\'\,\'([^\']+)/");
//
//            $this->logger->debug("onunload");
//            $this->http->GetURL("https://s-usc1c-nss-238.firebaseio.com/.lp?dframe=t&id={$id}&pw={$pw}&ns=fb-di2-live");
//
//            $this->logger->debug("pRTLPCB(2,[]);");
//            $this->http->GetURL("https://s-usc1c-nss-238.firebaseio.com/.lp?id={$id}&pw={$pw}&ser=21481191&ns=fb-di2-live", ['Accept' => '*/*']);
//
//            $this->logger->debug("Switching protocols");
//            $headers = [
//                "Sec-WebSocket-Extensions" => "permessage-deflate",
//                "Sec-WebSocket-Key" => "vdV/8BFRD5MrG0NJlRRs5g==",
//                "Sec-WebSocket-Version" => "13",
//                "Upgrade" => "websocket"
//            ];
//            $this->http->GetURL("https://s-usc1c-nss-238.firebaseio.com/.ws?v=5&s={$s}&ns=fb-di2-live", $headers);
//
//            $this->http->GetURL("https://s-usc1c-nss-238.firebaseio.com/.lp?id=3769686&pw=dfH3yuVEpc&ser=21481193&ns=fb-di2-live&seg0=1&ts0=1&d0=eyJ0IjoiZCIsImQiOnsiciI6MiwiYSI6ImF1dGgiLCJiIjp7ImNyZWQiOiJleUpoYkdjaU9pSlNVekkxTmlJc0ltdHBaQ0k2SW1NM01XTTRZekZtTXpNM01qSTFPVEEwTVRBME5ESTNPRGhrTURRMFlqTTVOakE1TkdNME0yWWlmUS5leUpwYzNNaU9pSm9kSFJ3Y3pvdkwzTmxZM1Z5WlhSdmEyVnVMbWR2YjJkc1pTNWpiMjB2Wm1JdFpHa3lMV3hwZG1VaUxDSjFjMlZ5SWpwMGNuVmxMQ0poZFdRaU9pSm1ZaTFrYVRJdGJHbDJaU0lzSW1GMWRHaGZkR2x0WlNJNk1UVXhNekEzTURFMk1Td2lkWE5sY2w5cFpDSTZJalUwT1Rrek56SXlOVEE0TlRjME56SWlMQ0p6ZFdJaU9pSTFORGs1TXpjeU1qVXdPRFUzTkRjeUlpd2lhV0YwSWpveE5URXpNRGN3TVRZeExDSmxlSEFpT2pFMU1UTXdOek0zTmpFc0ltWnBjbVZpWVhObElqcDdJbWxrWlc1MGFYUnBaWE1pT250OUxDSnphV2R1WDJsdVgzQnliM1pwWkdWeUlqb2lZM1Z6ZEc5dEluMTkuVVZYSzdaSTgzbllJMkJfazFYc1d1eHk0NGFsT20ydFdUU1JwNFJDRy1RVTJ1VUg3b1c3b2pTaV9XUm5udGE0UWlLM2JlclhUVld5WW92TWZmTkVOTE12V3lJUUFyYUN5ZGZlSVYwcVNKU0VUYVR0cXFoVWR1SlpVUHM3Z3RBekR1c0FQaDlrLWVMSTJlWXg1SzFwa25GcG5nMDNsZk40YmlCLS15bGlIQ1YwYUpnbC12MzdkbE5lckhtSU1jWWhpX2xGNDhIS3J4N1hidHhhejZLdlhPNC04b2lOVVpzc0pFSllXY0ZZMWszMTVlTTdGbTJTUDFJT3VsdXVkekI1b01vTm9HVHh3ampYX2lUekJha2swRFRHVGdERWNPN2JLTlF5RHZnRHJIVVgwYmlHS2dRTk9pSnF5ZVc3VEYxVFFqbmRLNUxvaXVaV3lseTN2NEY1RVBnIn19fQ..&seg1=2&ts1=1&d1=eyJ0IjoiZCIsImQiOnsiciI6MywiYSI6InEiLCJiIjp7InAiOiIvdXNlcnMvdW5kZWZpbmVkL3RyaXAvZGV0YWlsIiwiaCI6IiJ9fX0.&seg2=3&ts2=1&d2=eyJ0IjoiZCIsImQiOnsiciI6NCwiYSI6InEiLCJiIjp7InAiOiIvdXNlcnMvdW5kZWZpbmVkL2FyY2hpdmVkL3RyaXAvZGV0YWlsIiwiaCI6IiJ9fX0.&seg3=4&ts3=1&d3=eyJ0IjoiZCIsImQiOnsiciI6NSwiYSI6InEiLCJiIjp7InAiOiIvdXNlcnMvNTQ5OTM3MjI1MDg1NzQ3Mi90cmlwL2RldGFpbCIsImgiOiIifX19&seg4=5&ts4=1&d4=eyJ0IjoiZCIsImQiOnsiciI6NiwiYSI6InEiLCJiIjp7InAiOiIvdXNlcnMvNTQ5OTM3MjI1MDg1NzQ3Mi9hcmNoaXZlZC90cmlwL2RldGFpbCIsImgiOiIifX19");
//
//            $this->http->GetURL("https://s-usc1c-nss-238.firebaseio.com/.lp?id={$id}&pw={$pw}&ser=21481194&ns=fb-di2-live");
        }
//        $emailAddresses = $this->http->FindPreg("/emailAddresses\":\{\"0\":\"([^\"]+)/");
//        $confNumbers = $this->http->FindPregAll('/rloc":"(?<TripID>[^\"]+)",/', $this->http->Response['body'], PREG_SET_ORDER, true);

        $pastIts = $this->http->XPath->query("//div[@data-radium and div[@data-radium and contains(., 'Past Trips')]]/following-sibling::div//form");
        $this->logger->debug("Total {$pastIts->length} past reservations found");

        if ($this->http->FindSingleNode("//div[contains(text(), 'Plan Your Next Getaway')]/following-sibling::div[contains(., 'You currently have no upcoming trips.')]") && (!$this->ParsePastIts || ($this->ParsePastIts && $pastIts->length == 0))) {
            return $this->noItinerariesArr();
        }

        $xpath = "//div[@data-radium and div[@data-radium and contains(., 'Upcoming Trips')]]/following-sibling::div//form";

        if ($this->ParsePastIts) {
            $xpath = "//div[@data-radium and div[@data-radium and contains(., 'Upcoming Trips') or contains(., 'Past Trips')]]/following-sibling::div//form";
        }
        $reservations = $this->http->XPath->query($xpath);
        $confNumbers = [];
        $this->logger->debug("Total {$reservations->length} itineraries form were found");

        for ($i = 0; $i < $reservations->length; $i++) {
            $reservation = $reservations->item($i);
            $confNumbers[] = [
                'trip_lookup[confirmation_number]' => $this->http->FindSingleNode(".//input[@name = 'trip_lookup[confirmation_number]']/@value", $reservation),
                'trip_lookup[email]'               => $this->http->FindSingleNode(".//input[@name = 'trip_lookup[email]']/@value", $reservation),
            ];
        }
        $this->http->MultiValuedForms = false;
        $formData = [
            "trip_lookup[confirmation_number]" => "",
            "trip_lookup[email]"               => "",
        ];

        foreach ($confNumbers as $confNumber) {
            $this->increaseTimeLimit();
            $this->http->FormURL = 'https://www.amextravel.com/trip_lookups';
            $this->http->Form = $formData;
            $this->http->SetInputValue('trip_lookup[confirmation_number]', $confNumber['trip_lookup[confirmation_number]']);
            $this->http->SetInputValue('trip_lookup[email]', $confNumber['trip_lookup[email]']);
            $this->http->PostForm();

            if ($this->http->ParseForm("formForFHR")) {
                $this->http->PostForm();
            }

            $tripContains = $this->http->FindSingleNode("//h2[contains(text(), 'Details')] | //div[@class = 'title' and contains(text(), 'Details')] | //h3[contains(@class, 'BrandHeader') and contains(text(), 'Details')] | //h3[contains(@class, 'BrandHeader') and contains(text(), 'DETAILS')]", null, true, "/(.+)\s+Details/ims");
            $this->logger->debug("{$tripContains}");

            if ($error = $this->http->FindSingleNode('
                    //h2[contains(text(), "Invalid E-mail/Trip ID")]
                    | //div[contains(text(), "Your plans may be changing, and we are here to help. For our latest information and resources related to COVID-19,")]
                    | //p[contains(text(), "We apologize for the inconvenience, but we are currently experiencing an issue. Please click the back button to return to the summary page and try again.")]
                ')
            ) {
                $this->logger->error($error);

                continue;
            }

            switch (strtolower($tripContains)) {
                case 'car':
                case 'your car':
                case 'rental':
                    $it = $this->ParseItineraryCar();

                    break;

                case 'hotel':
                    $it = $this->ParseItineraryHotel();

                    break;

                default:
                    $it = $this->ParseItinerary();

                    $hotel = $this->ParseItineraryHotelInAirTrip();

                    if (!empty($hotel["HotelName"])) {
                        $result[] = $hotel;
                    }
                    $car = $this->ParseItineraryCar();

                    if (!empty($car["Number"])) {
                        $result[] = $car;
                    }
            }
            $result[] = $it;
        }

        return $result;
    }

    public function ParseItineraryCar()
    {
        $this->logger->notice(__METHOD__);
        $it = ["Kind" => "L"];
        // Number
        $it["Number"] = Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'CONFIRMATION NUMBER')]/following-sibling::p"));
        $this->logger->info('Parse itinerary #' . $it["Number"], ['Header' => 3]);

        // reservation has been cancelled
        if (stristr($it["Number"], 'THIS RESERVATION HAS BEEN CANCELLED')) {
            $it["Number"] = preg_replace("/\s*THIS RESERVATION HAS BEEN CANCELLED\.?/ims", "", $it["Number"]);
            $it["Cancelled"] = true;
        }// if (stristr($it["Number"], 'THIS RESERVATION HAS BEEN CANCELLED'))

        // TripNumber
        $it['TripNumber'] = Html::cleanXMLValue(preg_replace("/\s*THIS RESERVATION HAS BEEN CANCELLED\.?/ims", "", $this->http->FindSingleNode("//p[contains(., 'Trip ID:')]", null, true, '/Trip\s*ID\:\s*([^<]+)/ims') ?? $this->http->FindSingleNode("//span[@id = 'trip_id']")));
        // SpentAwards
        $it["SpentAwards"] = $this->http->FindSingleNode("//td[span[contains(text(), 'Points Used')]]/following-sibling::td/span");
        // PickupDatetime
        $it["PickupDatetime"] = strtotime(str_replace('@', '',
                $this->http->FindSingleNode("//p[strong[contains(text(), 'PICK-UP')]]/following-sibling::p[2]/strong/span")
                ?? $this->http->FindSingleNode("//p[strong[contains(text(), 'PICK-UP')]]", null, true, "/PICK-UP\s*\:(.+)/")
        ));
        // PickupLocation
        $it["PickupLocation"] = $this->http->FindSingleNode("//p[strong[contains(text(), 'PICK-UP')]]/following-sibling::p[1]");
        // PickupHours
        $it["PickupHours"] = implode('; ',
            $this->http->FindNodes('//div[p[strong[contains(text(), "PICK-UP")]]]/following-sibling::div/p[contains(@class, "CarPickUpDropOff-hours")]')
            ?? $this->http->FindNodes('//div[p[strong[contains(text(), "PICK-UP")]]]/div//span[contains(text(), "Business Hours:")]', null, "/Business Hours:\s*(.+)/")
        );
        // DropoffDatetime
        $it["DropoffDatetime"] = strtotime(str_replace('@', '',
                $this->http->FindSingleNode("//p[strong[contains(text(), 'DROP-OFF')]]/following-sibling::p[2]/strong/span")
                ?? $this->http->FindSingleNode("//p[strong[contains(text(), 'DROP-OFF')]]", null, true, "/DROP-OFF\s*\:(.+)/")
        ));
        // DropoffLocation
        $it["DropoffLocation"] = $this->http->FindSingleNode("//p[strong[contains(text(), 'DROP-OFF')]]/following-sibling::p[1]");
        // DropoffHours
        $it["DropoffHours"] = implode('; ',
            $this->http->FindNodes('//div[p[strong[contains(text(), "DROP-OFF")]]]/following-sibling::div/p[contains(@class, "CarPickUpDropOff-hours")]')
            ?? $this->http->FindNodes('//div[p[strong[contains(text(), "DROP-OFF")]]]/div//span[contains(text(), "Business Hours:")]', null, "/Business Hours:\s*(.+)/")
        );
        // CarType
        $it["CarType"] = $this->http->FindSingleNode('//h4[contains(@class, "CarCardPostBooking-rentalClass")]');
        // CarModel
        $it["CarModel"] = $this->http->FindSingleNode('//h4[contains(@class, "CarCardPostBooking-rentalClass")]/following-sibling::h6');
        // CarImageUrl
        $it["CarImageUrl"] = $this->http->FindSingleNode("//img[contains(@class, 'CarCardPostBooking-carImage')]/@src");
        // RenterName
        $it["RenterName"] = beautifulName($this->http->FindSingleNode("//div[contains(@class, 'CarDriverInformation-container')]/div/div/h4"));
        // AccountNumbers
        $it["AccountNumbers"] = $this->http->FindSingleNode("//p[strong[contains(text(), 'Loyalty Number')]]/following-sibling::p");
        /*
        if (strstr($it["AccountNumbers"], '@')) {
            $this->logger->debug("Remove wrong AccountNumbers {$it["AccountNumbers"]}");
            unset($it["AccountNumbers"]);
        }
        */
        // TotalCharge
        $it["TotalCharge"] = $this->http->FindSingleNode("//div[p[contains(text(), 'Due at Pick-up')]]/following-sibling::div/h1", null, true, "/([\s\d\.\,]+)/");
        // BaseFare
        $it["BaseFare"] = $this->http->FindSingleNode('//div[p[contains(text(), "Base Rate")]]/following-sibling::div/h3', null, true, "/([\s\d\.\,]+)/");
        // Currency
        $currency = Html::cleanXMLValue($this->http->FindSingleNode("//div[p[contains(text(), 'Due at Pick-up')]]/following-sibling::div/h1", null, true, "/([^\d\.\,]+)/"));

        if ($currency == '$') {
            $it["Currency"] = 'USD';
        } elseif ($currency == '£') {
            $it["Currency"] = 'GBP';
        } else {
            $it["Currency"] = $currency;
        }
        // TotalTaxAmount
        $it["TotalTaxAmount"] = $this->http->FindSingleNode('//div[p[contains(., "Taxes &amp; Fees") or contains(., "Taxes & Fees")]]/following-sibling::div/h3');

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($it, true), ['pre' => true]);

        return $it;
    }

    public function ParseItineraryHotel()
    {
        $this->logger->notice(__METHOD__);
        $it = ["Kind" => "R"];
        // Number
        $it["ConfirmationNumber"] = Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(., 'Trip ID:')]", null, true, '/Trip\s*ID\:\s*([^<]+)/ims'));

        if (empty($it["ConfirmationNumber"])) {
            $it["ConfirmationNumber"] = $this->http->FindSingleNode("//span[@id = 'trip_id']");
        }
        $this->logger->info('Parse itinerary #' . $it["ConfirmationNumber"], ['Header' => 3]);

        // reservation has been cancelled
        if (stristr($it["ConfirmationNumber"], 'THIS RESERVATION HAS BEEN CANCELLED')) {
            $it["ConfirmationNumber"] = preg_replace("/\s*THIS RESERVATION HAS BEEN CANCELLED\.?/ims", "", $it["ConfirmationNumber"]);
            $it["Cancelled"] = true;
        }// if (stristr($it["ConfirmationNumber"], 'THIS RESERVATION HAS BEEN CANCELLED'))

        $appData = $this->http->JsonLog($this->http->FindSingleNode('//script[contains(text(), "window.appData = ")]', null, true, "/^\/\/<!\[CDATA\[\s*window\.appData\s*=\s*(.+});\s*window\./"));
        $hotel = $appData->tripLookup->itinerary->hotel_itinerary_responses[0] ?? null;
//        $this->logger->debug(var_export($hotel, true), ['pre' => true]);

        // TripNumber
        $it['TripNumber'] = $it["ConfirmationNumber"];
        // GuestNames
        $it["GuestNames"] = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->http->FindNodes("//h3[@class = 'TravelerRoomInformation-name']"));
        // SpentAwards
        $it["SpentAwards"] = $this->http->FindSingleNode("//div[p[contains(text(), 'Points Used')]]/following-sibling::div/h3");
        // HotelName
        $it["HotelName"] = $this->http->FindSingleNode("//h3[@class = 'HotelInformation-name']") ?? $hotel->hotel->name ?? null;
        // Address
        if (isset($hotel->hotel->address)) {
            $state_province_code = isset($hotel->hotel->address->state_province_code) ? ", " . $hotel->hotel->address->state_province_code : '';
            $address =
                $hotel->hotel->address->street1
                . ", " . $hotel->hotel->address->city_name
                . $state_province_code
                . ", " . $hotel->hotel->address->country_code
            ;
        }
        $it["Address"] = $this->http->FindSingleNode("//div[h3[@class = 'HotelInformation-name']]/following-sibling::div/p") ?? $address ?? null;
        // Phone
        $it["Phone"] = $hotel->hotel->phone_number ?? null;
        // CheckInDate
        $checkIn = $hotel->hotel->check_in ?? null;

        if ($checkIn == 'anytime') {
            $checkIn = '00:00';
        }
        $it["CheckInDate"] = strtotime($this->http->FindSingleNode("//div[@class = 'BookingInformation']/div[1]/p") ?? $this->http->FindPreg('/([^\-]+)/', false, $checkIn) . ' ' . $hotel->hotel_stay_details->check_in_date);
        // CheckOutDate
        $checkOut = $hotel->hotel->check_out ?? null;

        if ($checkOut == 'noon') {
            $checkOut = '12 PM';
        }
        $it["CheckOutDate"] = strtotime($this->http->FindSingleNode("//div[@class = 'BookingInformation']/div[2]/p") ?? $checkOut . ' ' . $hotel->hotel_stay_details->check_out_date);
        // Rooms
        $it["Rooms"] = $this->http->FindSingleNode("//div[@class = 'BookingInformation']/div[3]/p", null, true, '/(\d+)\sRoom/ims') ?? $hotel->hotel_stay_details->total_number_of_rooms ?? null;
        // RoomTypeDescription
        $it["RoomTypeDescription"] = $this->http->FindSingleNode("//div[@class = 'RoomInformation-description']/div[@class = 'TruncateText']/p") ?? $hotel->hotel_room[0]->rate->description ?? null;
        // CancellationPolicy
        $it["CancellationPolicy"] = $this->http->FindSingleNode("//div[@class = 'RoomInformation-description']/div[@class = 'TruncateText']/p") ?? $hotel->hotel_room[0]->rate->cancel_policy_text ?? null;
        // Cost
        $it["Cost"] = $this->cost($this->http->FindSingleNode("//div[p[contains(text(), 'Room')]]/following-sibling::div/p"));
        // Total
        $total = $this->http->FindSingleNode("//div[p[contains(text(), 'Cost')]]/following-sibling::div/h3");
        $it["Total"] = $this->cost($total);
        // Currency
        $it["Currency"] = $this->currency($total);
        // Taxes
        $it["Taxes"] = $this->cost($this->http->FindSingleNode("//div[a[contains(text(), 'Taxes')]]/following-sibling::div/p"));

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($it, true), ['pre' => true]);

        return $it;
    }

    public function ParseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $it = ["Kind" => "T"];

        if (!$this->http->FindNodes("//div[div[contains(@class, 'FlightLeg--reviewYourTrip')]]") && $this->http->FindNodes("//div[contains(@class, 'flight-details')]/div[contains(@class, 'slice')]")) {
            $this->logger->notice("Old design");
            // TripNumber
            $it['TripNumber'] = Html::cleanXMLValue($this->http->FindSingleNode("//div[contains(text(), 'Trip ID:')]", null, true, '/Trip\s*ID\:\s*([^<]+)/ims'));
            // RecordLocator
            $it["RecordLocator"] = $this->http->FindSingleNode("(//span[@class = 'airline-code'])[1]");

            if (!isset($it["RecordLocator"])) {
                $it["RecordLocator"] = $it['TripNumber'];
            }// if (!isset($it["RecordLocator"]))
            // reservation has been cancelled
            if (stristr($it["TripNumber"], 'THIS RESERVATION HAS BEEN CANCELLED') || $this->http->FindSingleNode("//h1[contains(text(), 'YOUR TRIP HAS BEEN CANCELLED')]")) {
                $it["TripNumber"] = preg_replace("/\s*THIS RESERVATION HAS BEEN CANCELLED\.?/ims", "", $it["TripNumber"]);
                $it["Cancelled"] = true;
            }// if (stristr($it["TripNumber"], 'THIS RESERVATION HAS BEEN CANCELLED'))
            $this->logger->info('Parse itinerary #' . $it["RecordLocator"], ['Header' => 3]);
            // Passengers
            $it["Passengers"] = array_map(function ($elem) {
                return beautifulName($elem);
            }, $this->http->FindNodes("//span[@class = 'passenger-name']"));
            // AccountNumbers
            $it["AccountNumbers"] = implode(', ', $this->http->FindNodes("//span[@class = 'item-loyalty' and normalize-space(text()) != '--']"));
            // TicketNumbers
            $it["TicketNumbers"] = $this->http->FindNodes("//div[@class = 'ticket-info']/div[@class = 'items']/span[normalize-space(text()) != '--' and normalize-space(text()) != 'Pending']");
            // TotalCharge
            $it["TotalCharge"] = $this->http->FindSingleNode("//div[@class = 'total']/span[contains(@class, 'number')]", null, true, '/[\d\.\,]+/ims');

            if (!isset($it["TotalCharge"])) {
                $it["TotalCharge"] = $this->http->FindSingleNode("//div[@class = 'total-cost-data']/span[contains(@class, 'number')]", null, true, '/[\d\.\,]+/ims');
            }
            // Currency
            $currency = Html::cleanXMLValue($this->http->FindSingleNode("//div[@class = 'total']/span[contains(@class, 'super')]", null, true));

            if (!$currency) {
                $currency = $this->http->FindSingleNode("//div[@class = 'total-cost-data']/span[contains(@class, 'number')]");
            }
            $it["Currency"] = $this->currency($currency);
            // BaseFare
            $it["BaseFare"] = $this->http->FindSingleNode("//span[contains(text(), 'Adult')]/following-sibling::span[contains(@class, 'number')]", null, true, '/[\d\.\,]+/ims');
            // Tax
            $it["Tax"] = $this->http->FindSingleNode("//span[contains(@class, 'total-taxes-fees') and not(contains(@class, 'imposed'))]", null, true, '/[\d\.\,]+/ims');

            if (!isset($it["Tax"])) {
                $it["Tax"] = $this->http->FindSingleNode("//a[contains(text(), 'Taxes & Fees')]/following-sibling::span[contains(@class, 'number')]", null, true, '/[\d\.\,]+/ims');
            }
            // SpentAwards
            $it["SpentAwards"] = $this->http->FindSingleNode("//td[span[contains(text(), 'Points Used')]]/following-sibling::td/span");

            $nodes = $this->http->XPath->query("//div[contains(@class, 'flight-details')]/div[contains(@class, 'slice')]");
            $this->logger->debug('Total ' . $nodes->length . ' segments were found');
            $segments = [];
            $n = 0;

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $subSegments = $this->http->XPath->query(".//div[contains(@class, 'segment ')]", $node);
                $this->logger->debug('Total ' . $subSegments->length . ' sub-segments were found');
                $version = 1;

                if ($subSegments->length == 0) {
                    $version = 2;
                    $subSegments = $this->http->XPath->query(".//div[@class = 'segments']/div[contains(@class, 'segment-')]", $node);
                    $this->logger->debug('Total ' . $subSegments->length . ' sub-segments v.2 were found');
                }// if ($subSegments->length == 0)

                for ($k = 0; $k < $subSegments->length; $k++) {
                    $segment = [];
                    $subSegment = $subSegments->item($k);
                    // FlightNumber
                    $segment["FlightNumber"] = $this->http->FindSingleNode(".//div[@class = 'flight-num']", $subSegment, true, null, 0);
                    // AirlineName
                    $segment["AirlineName"] = $this->http->FindSingleNode(".//div[@class = 'codeshare']/div", $subSegment);

                    if (!isset($segment["AirlineName"])) {
                        $segment["AirlineName"] = $this->http->FindSingleNode(".//div[@class = 'airline' or @class = 'airline-leg']/div[@class = 'logo']/div[@class = 'name']", $subSegment);
                    }
                    // Duration
                    $segment["Duration"] = $this->http->FindSingleNode(".//div[@class = 'travel-duration']", $subSegment);
                    // TraveledMiles
                    $segment["TraveledMiles"] = $this->http->FindSingleNode(".//div[contains(@class, 'detail')]//div[contains(text(), 'Miles')]", $subSegment, true, '/([\d\.\,]+)/ims');
                    // Aircraft
                    $segment["Aircraft"] = $this->http->FindSingleNode(".//div[contains(@class, 'detail')]//div[contains(text(), 'Miles')]/preceding-sibling::div[1]", $subSegment);

                    // fixes for sub-segments v.2
                    if ($version == 2) {
                        $this->logger->notice("sub-segments v.2");
                        // FlightNumber
                        $segment["FlightNumber"] = $this->http->FindSingleNode("//div[@class = 'airline' or @class = 'airline-leg']/div[@class = 'logo']//div[@class = 'flight-num']", null, true, null, $n);
                        // AirlineName
                        $segment["AirlineName"] = $this->http->FindSingleNode("//div[@class = 'airline' or @class = 'airline-leg']/div[@class = 'logo']/div[@class = 'codeshare']/div", null, true, null, $n);

                        if (!isset($segment["AirlineName"])) {
                            $segment["AirlineName"] = $this->http->FindSingleNode("//div[@class = 'airline' or @class = 'airline-leg']/div[@class = 'logo']/div[@class = 'name']", null, true, null, $n);

                            if (
                                $segment["AirlineName"] === ''
                                && $this->http->FindSingleNode("//div[@class = 'airline' or @class = 'airline-leg']/div[@class = 'logo']/img/@alt", null, true, null, $n) == 'Kl_sq'
                            ) {
                                $this->logger->notice('set AirlineName by alt from img');
                                $segment["AirlineName"] = 'KL';
                            }

                            if (
                                $segment["AirlineName"] === ''
                                && $this->http->FindSingleNode("//div[@class = 'airline' or @class = 'airline-leg']/div[@class = 'logo']/img/@alt", null, true, null, $n) == 'Aa_sq'
                            ) {
                                $this->logger->notice('set AirlineName by alt from img');
                                $segment["AirlineName"] = 'AA';
                            }
                        }
                        // Duration
                        $segment["Duration"] = $this->http->FindSingleNode("//div[@class = 'travel-duration']", null, true, null, $n);
                        // TraveledMiles
                        $segment["TraveledMiles"] = $this->http->FindSingleNode("//div[contains(@class, 'detail')]//div[contains(text(), 'Miles')]", null, true, '/([\d\.\,]+)/ims', $n);
                        // Aircraft
                        $segment["Aircraft"] = $this->http->FindSingleNode("//div[contains(@class, 'detail')]//div[contains(text(), 'Miles')]/preceding-sibling::div[1]", null, true, null, $n);

                        $this->logger->debug(var_export($segment, true), ["pre" => true]);
                    }// if ($version == 2)

                    // Seats
                    $seats = $this->http->FindSingleNode(".//div[@class = 'info-seats']", $subSegment, true, '/Your\s*Seats\s*:\s*([^<]+)/ims');

                    if (!strstr($seats, 'The Airline did not pre-assign your seat')) {
                        $segment["Seats"] = $seats;
                    }
                    // Meal
                    $meals = $this->http->FindNodes("//span[contains(text(), 'Meal')]", null, '/Meal\s*\-\s*([^<]+)/ims');
                    $meal = implode(', ', array_values(array_filter($meals)));

                    if ($meal) {
                        $segment["Meal"] = $meal;
                    }
                    // Stops
                    $stops = Html::cleanXMLValue($this->http->FindSingleNode(".//div[@class = 'endpoints_legs']/ul/li[1]", $subSegment));

                    if (strtolower($stops) == 'non-stop') {
                        $segment["Stops"] = 0;
                    } elseif (stristr($stops, 'stop')) {
                        $segment["Stops"] = preg_replace("/[^\d]+/", '', $stops);
                    }
                    // Cabin
                    $segment["Cabin"] = $this->http->FindSingleNode(".//div[@class = 'endpoints_legs']/ul/li[2]", $subSegment);

                    if (!isset($segment["Cabin"]) && !strstr($stops, 'stop')) {
                        $segment["Cabin"] = $this->http->FindSingleNode(".//div[@class = 'endpoints_legs']/ul/li[1]", $subSegment);
                    }
                    // DepCode
                    $segment["DepCode"] = $this->http->FindSingleNode(".//div[@class='endpoints_legs'][1]//div[contains(@class, 'departure')]/span[@class = 'airport']", $subSegment, true, "/\(([A-Z]{3})/");
                    // DepName
                    $segment["DepName"] = $this->http->FindSingleNode(".//div[@class='endpoints_legs'][1]//div[contains(@class, 'departure')]/span[@class = 'airport']", $subSegment, true, "/([^\(]+)/ims");
                    // DepDate
                    $depDate = $this->http->FindSingleNode(".//div[@class='endpoints_legs'][1]//div[contains(@class, 'departure')]/span[@class = 'date']", $subSegment);
                    $segment["DepDate"] = strtotime(
                        $depDate . ' ' .
                        $this->http->FindSingleNode(".//div[@class='endpoints_legs'][1]//div[contains(@class, 'departure')]/span[@class = 'time']", $subSegment));
                    // ArrCode
                    $segment["ArrCode"] = $this->http->FindSingleNode(".//div[@class='endpoints_legs'][last()]//div[contains(@class, 'arrival')]/span[@class = 'airport']", $subSegment, true, "/\(([A-Z]{3})/");
                    // ArrName
                    $segment["ArrName"] = $this->http->FindSingleNode(".//div[@class='endpoints_legs'][last()]//div[contains(@class, 'arrival')]/span[@class = 'airport']", $subSegment, true, "/([^\(]+)/ims");
                    // ArrDate
                    $arrDate = $this->http->FindSingleNode(".//div[@class='endpoints_legs'][last()]//div[contains(@class, 'arrival')]/span[@class = 'date']", $subSegment);
                    $segment["ArrDate"] = strtotime(
                        (isset($arrDate)) ? $arrDate : $depDate . ' ' .
                            $this->http->FindSingleNode(".//div[@class='endpoints_legs'][last()]//div[contains(@class, 'arrival')]/span[@class = 'time']", $subSegment));
                    $nextDay = $this->http->FindSingleNode(".//div[@class = 'endpoints_legs']/ul/li[contains(text(), 'Day')]", $subSegment, true, "/\+\s*(\d)\s*Day/");

                    if ($nextDay) {
                        $this->logger->notice("Arrival in next day [+ {$nextDay} Day(s)]");
                        $segment["ArrDate"] = strtotime("+{$nextDay} day", $segment["ArrDate"]);
                    }
                    $segments[] = $segment;
                    $n++;
                }// for ($k = 0; $k < $subSegments->length; $k++)
            }// for ($i = 0; $i < $nodes->length; $i++)
        } else {
            $this->logger->notice("New design");

//            $appData = $this->http->JsonLog($this->http->FindSingleNode('//script[contains(text(), "window.appData = ")]', null, true, "/^\/\/<!\[CDATA\[\s*window\.appData\s*=\s*(.+});\s*window\./"));

            // TripNumber
            $it['TripNumber'] = Html::cleanXMLValue($this->http->FindSingleNode("//span[@id = 'trip_id']"));
            // RecordLocator
            $it["RecordLocator"] = $this->http->FindSingleNode("(//div[@class = 'FlightLeg-recordLocator'])[1]", null, true, "/LOCATOR:\s*([^<]+)/");

            if (!isset($it["RecordLocator"])) {
                $it["RecordLocator"] = $it['TripNumber'];
            }// if (!isset($it["RecordLocator"]))
            // reservation has been cancelled
            if (stristr($it["TripNumber"], 'THIS RESERVATION HAS BEEN CANCELLED') || $this->http->FindSingleNode("//h1[contains(text(), 'YOUR TRIP HAS BEEN CANCELLED')]")) {
                $it["TripNumber"] = preg_replace("/\s*THIS RESERVATION HAS BEEN CANCELLED\.?/ims", "", $it["TripNumber"]);
                $it["Cancelled"] = true;
            }// if (stristr($it["TripNumber"], 'THIS RESERVATION HAS BEEN CANCELLED'))
            $this->logger->info('Parse itinerary #' . $it["RecordLocator"], ['Header' => 3]);
            // Passengers
            $it["Passengers"] = array_map(function ($elem) {
                return beautifulName($elem);
            }, $this->http->FindNodes("//h4[contains(@id, '.complete_name')]"));
            // AccountNumbers
            $it["AccountNumbers"] = array_unique($this->http->FindNodes("//div[contains(@id, '.loyalty_program') and normalize-space(text()) != '--']/div"));
            $accountNumbers = [];

            foreach ($it["AccountNumbers"] as $number) {
                if (strstr($number, ' ... ')) {
                    $accountNumbers[] = explode(' ... ', $number);
                } else {
                    $accountNumbers[] = $number;
                }
            }
            $it["AccountNumbers"] = array_unique($accountNumbers);
            // TicketNumbers
            $it["TicketNumbers"] = $this->http->FindNodes("//div[@data-attribute = 'ticketNumber' and not(contains(text(), 'Unassigned')) and not(contains(text(), 'Pending'))]");
            // TotalCharge
            $it["TotalCharge"] = $this->http->FindSingleNode("//h1[@class = 'FlightTotalCost-total' and not(following-sibling::p[contains(text(), 'Membership Rewards') and contains(., 'point')])]/text()[last()]", null, true, '/[\d\.\,]+/ims');
            // Currency
            $currency = $this->http->FindSingleNode("//h1[@class = 'FlightTotalCost-total']/sup");

            if (!$currency) {
                $currency = $this->http->FindSingleNode("//div[a[contains(text(), 'Taxes')]]/following-sibling::div/h4/sup");
            }
            $it["Currency"] = $this->currency($currency);
            // BaseFare
            $it["BaseFare"] = $this->http->FindSingleNode("//div[p[contains(text(), 'Adult')]]/following-sibling::div/h4/text()[last()]", null, true, '/[\d\.\,]+/ims');
            // Tax
            $it["Tax"] = $this->http->FindSingleNode("//div[a[contains(text(), 'Taxes')]]/following-sibling::div/h4/text()[last()]", null, true, '/[\d\.\,]+/ims');
            // SpentAwards
            $it["SpentAwards"] = $this->http->FindSingleNode("//div[p[contains(text(), 'Points Used')]]/following-sibling::div/h1");

            $nodes = $this->http->XPath->query("//div[div[contains(@class, 'FlightLeg--reviewYourTrip')]]");
            $this->logger->debug('Total ' . $nodes->length . ' segments were found');
            $segments = [];

            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $subSegments = $this->http->XPath->query("div[contains(@class, 'FlightLeg--reviewYourTrip')]", $node);
                $this->logger->debug('Total ' . $subSegments->length . ' sub-segments were found');
                $startDate = $this->http->FindSingleNode("./div[contains(@class, 'AddToCalendar')]//span[@class = 'start']", $node, true, "/^(\d{4}-\d{2}-\d{2})/");
                $endDate = $this->http->FindSingleNode("./div[contains(@class, 'AddToCalendar')]//span[@class = 'end']", $node, true, "/^(\d{4}-\d{2}-\d{2})/");

                for ($k = 0; $k < $subSegments->length; $k++) {
                    $subSegment = $subSegments->item($k);
                    $segment = [];
                    $this->logger->debug("Start Date: $startDate / " . strtotime($startDate) . " ");
                    $this->logger->debug("End Date: $endDate / " . strtotime($endDate) . " ");
                    // FlightNumber
                    $segment["FlightNumber"] = $this->http->FindSingleNode(".//p[contains(@class, 'LegAirlineInfo-airplaneName')]/preceding-sibling::p[contains(@class, 'LegAirlineInfo-airlineName')]", $subSegment, true, "/(\d+)$/");
                    // AirlineName
                    $segment["AirlineName"] = $this->http->FindSingleNode(".//p[contains(@class, 'LegAirlineInfo-airplaneName')]/preceding-sibling::p[contains(@class, 'LegAirlineInfo-airlineName')]", $subSegment, true, "/(.+)\s+\d+$/");
                    // Operator
                    $segment["Operator"] = $this->http->FindSingleNode(".//p[contains(@class, 'LegAirlineInfo-airplaneName')]/preceding-sibling::p[contains(@class, 'LegAirlineInfo-airlineName')]/parent::div//div[contains(@class, 'OperatorList')]", $subSegment, true, "/Operated by\s*(.+)/ims");
                    // TraveledMiles
                    $segment["TraveledMiles"] = $this->http->FindSingleNode(".//p[contains(@class, 'LegAirlineInfo-airplaneName')]/span", $subSegment, true, "/,?\s*([\d\.\,]+)\s*Mile/ims");
                    // Aircraft
                    $segment["Aircraft"] = beautifulName($this->http->FindSingleNode(".//p[contains(@class, 'LegAirlineInfo-airplaneName')]/text()[1]", $subSegment));
                    // DepCode
                    $segment["DepCode"] = $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]/div[1]/div", $subSegment, true, "/\(([A-Z]{3})/");
                    // DepName
                    $segment["DepName"] = $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]/div[1]/div", $subSegment, true, "/([^\(]+)/ims");
                    // DepDate
                    if ($k == 0) {
                        $depDateTime = $startDate . ' ' . $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]/div[1]/*[@class = 'FlightLegSchedule-time']", $subSegment);
                    } else {
                        $depDateTime = ($this->http->FindSingleNode(".//div[contains(@class, 'FlightLeg-date')]", $subSegment) ?? $endDate) . ' ' . $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]/div[1]/*[@class = 'FlightLegSchedule-time']", $subSegment);
                    }
                    $this->logger->debug("DepDate: $depDateTime");
                    $segment["DepDate"] = strtotime($depDateTime);
                    // ArrCode
                    $segment["ArrCode"] = $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]//div[3]/div", $subSegment, true, "/\(([A-Z]{3})/");
                    // ArrName
                    $segment["ArrName"] = $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]/div[3]/div", $subSegment, true, "/([^\(]+)/ims");
                    // ArrDate
                    $arrDateTime = ($this->http->FindSingleNode(".//div[contains(@class, 'FlightLeg-date')]", $subSegment) ?? $endDate) . ' ' . $this->http->FindSingleNode(".//div[contains(@class, 'FlightLegSchedule')]/div[3]/*[@class = 'FlightLegSchedule-time']", $subSegment);
                    $this->logger->debug("ArrDate: $arrDateTime");
                    $segment["ArrDate"] = strtotime($arrDateTime);

                    // Cabin
                    $segment["Cabin"] = $this->http->FindSingleNode(".//span[contains(@class, 'FlightLegExtras-cabinType')]", $subSegment);
                    // Seats
                    $segment["Seats"] = array_filter(explode(' - ', $this->http->FindSingleNode(".//span[contains(@class, 'FlightLegExtras-seats') and not(contains(text(), 'Seats Unassigned'))]", $subSegment, true, "/Seats\s*([^\,]+)/")), function ($seat) {
                        return $seat != 'BAG';
                    });

                    $segments[] = $segment;
                }
            }// for ($i = 0; $i < $nodes->length; $i++)
        }

        $it["TripSegments"] = $segments;

        if (empty($segments) && !$this->http->FindSingleNode("//h2[contains(text(), 'Invalid E-mail/Trip ID')]")
            && !$this->http->FindSingleNode("//h1[contains(text(), 'INTERNAL SERVER ERROR')]")
            && !$this->http->FindSingleNode('//p[contains(text(), "We\'re currently undergoing scheduled maintenance.")]')
            && !$this->http->FindSingleNode('//p[contains(text(), "We are experiencing technical difficulties.")]')
            && !$this->http->FindSingleNode('//p[contains(text(), "We\'re sorry, it appears an error has occurred.")]')
            && !isset($it["Cancelled"])
            && !$this->http->FindPreg("/Bad Gateway/")
            && !in_array($this->http->Response['code'], [0, 500])) {
            $this->sendNotification("Need to check Air itinerary");
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($it, true), ['pre' => true]);

        return $it;
    }

    public function ParseItineraryHotelInAirTrip()
    {
        $this->logger->notice(__METHOD__);
        $it = ["Kind" => "R"];
        // Number
        $it["ConfirmationNumber"] = Html::cleanXMLValue($this->http->FindSingleNode("//div[contains(text(), 'Trip ID:')]", null, true, '/Trip\s*ID\:\s*([^<]+)/ims'));

        // reservation has been cancelled
        if (stristr($it["ConfirmationNumber"], 'THIS RESERVATION HAS BEEN CANCELLED')) {
            $it["ConfirmationNumber"] = preg_replace("/\s*THIS RESERVATION HAS BEEN CANCELLED\.?/ims", "", $it["ConfirmationNumber"]);
            $it["Cancelled"] = true;
        }// if (stristr($it["ConfirmationNumber"], 'THIS RESERVATION HAS BEEN CANCELLED'))

        // TripNumber
        $it['TripNumber'] = $it["ConfirmationNumber"];
        // HotelName
        $xpath = "//div[contains(text(), 'Your selected hotel')]/following-sibling::div[@class = '%s']";
        $it["HotelName"] = $this->http->FindSingleNode(sprintf($xpath, "name"));

        if (!isset($it["HotelName"])) {
            $this->logger->notice("Hotel not found");
            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($it, true), ['pre' => true]);

            return [];
        }

        $this->logger->info('Parse itinerary #' . $it["ConfirmationNumber"], ['Header' => 3]);
        // Address
        $it["Address"] = $this->http->FindSingleNode(sprintf($xpath, "address"));
        // CheckInDate
        $it["CheckInDate"] = strtotime($this->http->FindSingleNode(sprintf($xpath, "in-date")));
        // CheckOutDate
        $it["CheckOutDate"] = strtotime($this->http->FindSingleNode(sprintf($xpath, "out-date")));
        // Rooms
        $it["Rooms"] = $this->http->FindSingleNode("//div[contains(text(), 'Your selected hotel')]/following-sibling::div[@class = 'total_night' and contains(text(), 'Room')]", null, true, '/(\d+)\sRoom/ims');
        // RoomTypeDescription
        $it["RoomTypeDescription"] = $this->http->FindSingleNode(sprintf($xpath, "detail"));

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($it, true), ['pre' => true]);

        return $it;
    }

    private function increaseMaxRequests()
    {
        $this->logger->notice(__METHOD__);
        $this->http->maxRequests = 2900;
        $this->http->TimeLimit = 500;
        $this->increaseTimeLimit(120);
    }

    private function getNameForParseSwitzerland()
    {
        $this->logger->notice(__METHOD__);

        return beautifulName($this->http->FindSingleNode("//span[@class = 'label-user']"));
    }

    private function getBalanceForParseSwitzerland()
    {
        $this->logger->notice(__METHOD__);

        return str_replace('\'', '', $this->http->FindSingleNode("//span[@class = 'label-balance']", null, true, "/([\d\']+)/"));
    }

    private function checkLoginOptions()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.americanexpress.com/change-country/");
        $nodes = $this->http->XPath->query('//div[@data-value="mymenu0_tabs_new"]//a[contains(@class, "linkout") and string-length(@title) > 2]');

        if ($nodes->length > 0) {
            for ($n = 0; $n < $nodes->length; $n++) {
                $s = Html::cleanXMLValue($nodes->item($n)->nodeValue);

                if ($s != "") {
                    $arFields['Login2']['Options'][$s] = $s;
                }
            }
        } else {
            $state = $this->http->FindPreg("/window.__INITIAL_STATE__ = \"([^<]+)\";/");
            $state = $this->http->JsonLog(stripcslashes($state));
            $countries = $state[1][17][1][3][1][1][1][3][1][1][1][29][1][7][1] ?? [];
//            $this->logger->debug(var_export($countries, true), ['pre' => true]);
            foreach ($countries as $country) {
                $s = Html::cleanXMLValue($country[1][1]);

                if ($s == "") {
                    continue;
                }

                $this->logger->debug(var_export($s, true), ['pre' => true]);
                $this->logger->debug(var_export($s, true), ['pre' => true]);

                if ($s == 'Schweiz') {
                    $s = 'Switzerland';
                }
                $s = str_replace('u0026', '&', $s);
                $arFields['Login2']['Options'][$s] = $s;

                if ($s == 'Greater China Region' && isset($country[1][7][1])) {
                    foreach ($country[1][7][1] as $regionKey => $region) {
                        $s = Html::cleanXMLValue($region[1][1]);

                        if ($s == "") {
                            continue;
                        }
                        $s = str_replace('u0026', '&', $s);
                        $arFields['Login2']['Options'][$s] = $s;
                    }
                }// if ($s == 'Greater China Region' && isset($country[8]))
            }// foreach ($countries as $country)
        }

        $this->logger->debug(var_export($arFields['Login2']['Options'], true), ['pre' => true]);
    }

    private function getUuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),

            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,

            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function proxyRetries()
    {
        $timeout = 0;

        if ($this->attempt == 1) {
            $timeout = 20;
        }

        throw new CheckRetryNeededException(3, $timeout);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes('//a[contains(@class, "__closedLogout___")] | ' . self::BRAZIL_SUCCESSFUL)) {
            if ($this->http->FindSingleNode(self::BRAZIL_SUCCESSFUL)) {
                $this->parseNonUS = true;
            }

            return true;
        }

        return false;
    }

    private function getFloat($str)
    {
        if (strstr($str, ",")) {
            $str = str_replace(",", "", $str); // replace ',' with '.'
            $str = str_replace(".", ",", $str); // replace dots (thousand seps) with blancs
        }

        return floatval($str);
    }

    private function skipOffers()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // skip Offer
        if (in_array($this->AccountFields['Login2'], ['France', 'français', 'United States'])
            && (($link = $this->http->FindPreg("/href=\"([^\"]+)\"\s*[^>]+title=\"Me le rappeler plus tard\">/ims"))
                || ($link = $this->http->FindSingleNode("//a[contains(text(), 'Me le rappeler plus tard')]/@href")))) {
            $this->logger->notice(">>> skip Offer");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }

        if ($this->AccountFields['Login2'] == 'Argentina'
            && ($link = $this->http->FindSingleNode("//area[contains(@onclick, 'Button>Remind me later')]/@href"))) {
            $this->logger->notice(">>> skip Offer");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }
        // go to 'Summary of Accounts'
        if (strstr($this->http->currentUrl(), 'https://global.americanexpress.com/myca/onlinepayments/canlac/CA/payments.do?request_type=')) {
            $this->logger->notice("go to 'Summary of Accounts'");

            if ($link = $this->http->FindSingleNode("//a[@id = 'ca_myca_view_sumaccnt']/@href")) {
                $this->http->GetURL($link);
            } elseif ($link = $this->http->FindSingleNode("//a[@id = 'ca_myca_accthome']/@href")) {
                $this->http->GetURL($link);
            }
        }

        if (strstr($this->http->currentUrl(), 'https://global.americanexpress.com/myca/intl/paybill/emea/payBillPaymentAlt.do?request_type=&Face=')
            || strstr($this->http->currentUrl(), 'https://online.americanexpress.com/myca/onlinepayment/us/v3/payment/inquiry.do?')) {
            $this->logger->notice("go to 'Summary of Accounts'");
            $this->http->setCookie("payBillVisited", "true", "global.americanexpress.com");

            if ($link = $this->http->FindSingleNode("//a[@id = 'gb_menu_myacct_acctsum']/@href")) {
                $this->http->GetURL($link);
            } elseif ($link = $this->http->FindSingleNode("//a[@id = 'return-to-account-home']/@href")) {
                $this->http->GetURL($link);
            } elseif ($link = $this->http->FindSingleNode("//a[@id = 'MYCA_PC_Account_Summary2']/@href")) {
                $this->http->GetURL($link);
            }
        }

        if ($this->http->currentUrl() == 'https://global.americanexpress.com/myca/onlinepayments/canlac/CA/payments.do?request_type=authreg_CreditCenter&Face=en_CA'
            || strstr($this->http->currentUrl(), 'https://online.americanexpress.com/myca/onlinepayment/us/v3/payment/inquiry.do?')) {
            $this->throwProfileUpdateMessageException();
        }
    }

    private function parseSubAccHistory($code, $headers, $cardCode, $displayName, $coBrand = false)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->mapReferences = [];

        if (!$this->WantHistory) {
            return $result;
        }

        // todo:
//        if (
//              strstr($displayName, '')
//              || strstr($displayName, '')
//        ) {
//            return $result;
//        }

        $this->logger->info("History for card ...{$displayName} (MR #{$code})", ['Header' => 3]);
        $startTimer = $this->getTime();
        $startDate = $this->getSubAccountHistoryStartDate($cardCode);
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');

        // refs #19361, note-78
        if (!$this->strictHistoryStartDate && $startDate !== null) {
            $startDate = strtotime("-5 day", $startDate);
            $this->logger->debug('[Set history start date -3 days for ' . $code . ': ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        }

        // refs #18365 -> https://redmine.awardwallet.com/issues/18365#note-12
        if ($this->http->ResponseNumber > 900) {
            return $result;
        }

        // Redemption transactions  // refs #18325
        $redemptionInfo = [];

        if ($coBrand === false) {
            $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/loyalty/statement/transactions?category=REDEMPTION&period=P1Y06D&offset=7&limit=7", $headers);
            $response = $this->http->JsonLog(null, 0, true);
            $periods = ArrayVal($response, 'periods');

            if (!is_array($periods)) {
                return $result;
            }
            $periods = array_reverse($periods);

            foreach ($periods as $key => $period) {
                $this->logger->debug("[Page: {$key}]");

                $end_date = ArrayVal($period, 'end_date');
                $bp_index = ArrayVal($period, 'bp_index');

                if (isset($startDate) && $startDate > strtotime($end_date)) {
                    $this->logger->notice("skip old history: {$end_date}");

                    continue;
                }// if (isset($startDate) && $startDate > strtotime($end_date))
                $startIndex = sizeof($redemptionInfo);
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://global.americanexpress.com/api/servicing/v2/loyalty/statement/transactions?bp_index={$bp_index}&offset=0&limit=1000", $headers);
                $this->http->RetryCount = 2;
                $redemptionInfo = array_merge($redemptionInfo, $this->parsePageSubAccActivity($startDate, $startIndex));
            }
        }// if ($coBrand === false)
        unset($periods);
        unset($period);

        // get statement_periods
        $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/financials/statement_periods", $headers);
        $periods = $this->http->JsonLog(null, 0, true);

        if (!is_array($periods)) {
            return $result;
        }
        $page = 0;
        $periods = array_reverse($periods);
        // exclude latest month (no points posted in transactions yet)
        // #note-20
        $businessCard = false;

        if (!strstr($displayName, 'Business')
            && !strstr($displayName, 'Platinum Card® (')
            && !strstr($displayName, 'Platinum Card (')
            && !strstr($displayName, 'Platinum Cashback Credit Card (')
            && !strstr($displayName, 'Centurion® Card (')
            && !strstr($displayName, 'Centurion Card® (')
            && !strstr($displayName, 'Premier Rewards Gold Card (')
            && !strstr($displayName, 'American Express Gold Card (')
            && !strstr($displayName, 'Corporate Card (')
            && !strstr($displayName, 'Morgan Stanley Platinum Card (')
            && !strstr($displayName, 'Schwab Platinum Card (')
            && !strstr($displayName, 'Amex EveryDay® Preferred')
            && !strstr($displayName, 'Classic Gold Card (')
            && !strstr($displayName, 'Green Card (')
            && !strstr($displayName, 'American Express® Platinum Edge Credit Card (')
            && !strstr($displayName, 'American Express® Platinum Card (')
            && !strstr($displayName, 'The American Express® Explorer Credit Card (')
            && !strstr($displayName, 'ＡＮＡアメリカン・エキスプレス・ゴールド・カード (')
            && !strstr($displayName, 'American Express® Green Corporate Card (')
            && !strstr($displayName, 'American Express® Essential Credit Card (')
            && !strstr($displayName, 'American Express® Platinum Charge Card (')
            && !strstr($displayName, 'Centurion from American Express (')
            && !strstr($displayName, 'American Express® Platinum Reserve Credit Card (')
            && !strstr($displayName, 'CPA Gold Credit Card (')
            && !strstr($displayName, 'American Express® Elevate Premium Credit Card (')
            && !strstr($displayName, 'American Express ® Platinum Rewards Credit Card (')
            && !strstr($displayName, 'ANA AMERICAN EXPRESS SUPER FLYERS GOLD CARD (')
            && !strstr($displayName, 'プラチナ・カード (')
            && !strstr($displayName, 'American Express® Gold Credit Card (')
            && !strstr($displayName, 'American Express® Preferred Rewards Gold Credit Card (')
            && !strstr($displayName, 'ANA AMERICAN EXPRESS CARD (')
            && !strstr($displayName, 'Additional Gold Card on Platinum (')
            && !strstr($displayName, 'The Preferred Rewards Gold Card® (')
            && !strstr($displayName, 'ＡＮＡアメリカン・エキスプレス・カード')
            && !strstr($displayName, 'American Express® Gold Card')
            && !strstr($displayName, 'AMA Platinum Credit Card from American Express')
            && !strstr($displayName, 'The American Express® Rewards Credit Card (')
            && !strstr($displayName, 'American Express® Rewards Advantage Card (')
            && !strstr($displayName, 'アメリカン・エキスプレス・ビジネス・ゴールド・カード')
            && $coBrand === false
        ) {
            $this->logger->notice("exclude latest month (no points posted in transactions yet)");
            array_pop($periods);
        }// if (!strstr($displayName, 'Business'))
        else {
            $this->logger->notice("parse all history for Business card");
            $businessCard = true;
        }
        $last_statement_end_date = null;
        $transactionsByStatements = [];

        // refs #22325
        if (strstr($displayName, 'Business Gold Card (')
            && $coBrand === false
        ) {
            $this->logger->notice("exclude latest month (no points posted in transactions yet)");
            array_pop($periods);
        }// if (strstr($displayName, 'Business Gold Card (')

        foreach ($periods as $period) {
            $this->logger->debug("[Page: {$page}]");

            $statement_end_date = ArrayVal($period, 'statement_end_date');

            if (isset($startDate) && $startDate > strtotime($statement_end_date)) {
                $this->logger->notice("skip old history: {$statement_end_date}");

                continue;
            }// if ($startDate < strtotime($statement_end_date))

            // AccountID: 3066571, 1114511, 5759003
            if (
                in_array($this->AccountFields['Login'], [
                    'yenAmex2016',
                    'petermcgowan545',
                    'paulbarry1',
                    'jostergaard2',
                    'dgerhardt13', // 3511118
                    'mujamil01', // 5612150
                    'amexmadcap', // 2554890
                ])
                && (
                    strstr($displayName, 'Amex EveryDay® Preferred (-71002)')
                    || strstr($displayName, 'Business Platinum Card® (-93004)')
                    || strstr($displayName, 'Platinum Card® (-79000)')
                    || strstr($displayName, 'Business Platinum Card® (-62001)')
                    || strstr($displayName, 'Business Platinum Card® (-71006)')
                    || strstr($displayName, 'Platinum Card® (-01000)')
                    // 2554890
                    || strstr($displayName, 'Business Platinum Card® (-62009)')
                    || strstr($displayName, 'Centurion® Card (-89008)')
                    || strstr($displayName, 'Additional Platinum Card® on Centurion® (-85055)')
                    || strstr($displayName, 'Additional Platinum Card® on Centurion® (-85048)')
                    || strstr($displayName, 'Additional Platinum Card® on Centurion® (-84066)')
                    || strstr($displayName, 'Additional Business Platinum Card (-61910)')
                    || strstr($displayName, 'Companion Centurion® Card (-83118)')
                    || strstr($displayName, 'Companion Centurion® Card (-83092)')
                    || strstr($displayName, 'Companion Centurion® Card (-83100)')
                    || strstr($displayName, 'Companion Centurion® Card (-83076)')
                    // 5759003
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31890)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31965)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31940)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31932)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31924)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31908)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31858)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31841)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31833)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31916)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31882)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31874)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31866)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31825)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31817)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31809)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31791)')
                    || strstr($displayName, 'Additional Business Green Card on Business Platinum (-31783)')
                    // 3511118
                    || strstr($displayName, 'Bonvoy Business Amex Card (-22008)')
                    || strstr($displayName, 'Amazon Business Prime Card (-53001)')
                    || strstr($displayName, 'Hilton Honors Surpass® Card (-91001)')
                    || strstr($displayName, 'Business Gold Card (-61007)')
                    // 5612150
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02803)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02795)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02787)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02787)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02779)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02761)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02753)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02746)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02738)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02720)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02712)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02530)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02506)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02498)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02480)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-02126)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01896)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01888)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01870)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01862)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01821)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01839)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01847)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01854)')
                    || strstr($displayName, 'Additional Gold Card on Platinum (-01813)')
                )
                && strtotime($statement_end_date) < strtotime("-1 year")
            ) {
                $this->logger->notice("skip old history (AccountID: 3066571 / 5759003): {$statement_end_date}");

                continue;
            }

            if (isset($startDate) && $startDate > strtotime($statement_end_date)) {
                $this->logger->notice("skip old history: {$statement_end_date}");

                continue;
            }// if ($startDate < strtotime($statement_end_date))

            $this->logger->info("statement_end_date: {$statement_end_date} / result: " . sizeof($result), ['Header' => 4]);

            if ($businessCard && (($page > 4 && sizeof($result) > 1000) || $this->http->ResponseNumber > 900)) {
                $this->logger->info("End of history reached for card ...{$displayName} (MR #{$code})", ['Header' => 3]);
                $this->logger->notice("Partial history parsing for business accounts");

                break;
            }

            $this->increaseTimeLimit();
            $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/financials/transactions?limit=1000&status=posted&extended_details=merchant%2Ccategory%2Cadditional_attributes%2Cpurchase_details%2Cadditional_description_lines%2Crewards&statement_end_date=" . $statement_end_date, $headers);

            // Partial history parsing for business accounts
            $response = $this->http->JsonLog(null, 0, true);
            $totalRows = ArrayVal($response, 'total_count', "no transactions");
            $this->logger->debug("Total {$totalRows} activity rows were found");

            $startIndex = sizeof($result);
            $result = array_merge($result, $this->parsePageSubAccHistory($startDate, $startIndex, $statement_end_date, $headers, $businessCard, $last_statement_end_date));
            $page++;

            // refs #18947, only earning transactions for last half year
            if (
                empty($last_statement_end_date)
                || strtotime($statement_end_date) > strtotime($last_statement_end_date)
            ) {
                $transactionsByStatements[$statement_end_date] = $totalRows;
            }
        }// foreach ($periods as $period)

        usort($result, function ($a, $b) { return $b['Date'] - $a['Date']; });
//        $this->logger->debug(var_export($result, true), ["pre" => true]);

        // refs #18947, only earning transactions for last half year
        $this->logger->debug("transactionsByStatements");
//        $this->logger->debug(var_export($transactionsByStatements, true), ['pre' => true]);
        array_pop($transactionsByStatements);
        $this->logger->debug(var_export($transactionsByStatements, true), ['pre' => true]);

        foreach ($transactionsByStatements as $date => $transactionsByStatement) {
            if ($transactionsByStatement !== 0) {
                $this->logger->notice("break at last_statement_end_date: {$last_statement_end_date}");

                break;
            }
            $this->logger->notice("Set last_statement_end_date as {$date}");
            $last_statement_end_date = $date;
        }

        if (
            empty($transactionsByStatements)
            || empty($statement_end_date)
            || $last_statement_end_date == $statement_end_date
        ) {
            $this->logger->notice("last_statement_end_date: {$last_statement_end_date}");
            $this->logger->notice("statement_end_date: " . ($statement_end_date ?? null));
            $this->logger->notice("Drop last_statement_end_date");
            $last_statement_end_date = null;
        }

        $this->increaseTimeLimit();

        // refs #18325, merge data in one array
        $result = $this->combineHistoryTransactions($result, $redemptionInfo, $displayName, $startDate, $businessCard, $last_statement_end_date);

        $this->getTime($startTimer);

        return $result;
    }

    private function combineHistoryTransactions($result, $redemptionInfo, $displayName, $startDate, $businessCard, $last_statement_end_date)
    {
        $this->logger->notice(__METHOD__);
        $filterByCode = $this->http->FindPreg("/\((\-\d+)\)/", false, $displayName);
        $redemptions = [];

        if (!isset($result[0])) {
            $this->logger->notice("empty earning history, do not combine results");

            return $result;
        }
        $this->logger->info("spending by card ...{$displayName}", ['Header' => 3]);

        $this->logger->debug('startDate: ' . $startDate);
        $firstDateOfResult = $result[0]['Date'];
        $this->logger->debug('first date of result: ' . $firstDateOfResult);
        $lastDateOfResult = $result[array_key_last($result)]['Date'];
        $this->logger->debug('last date of result: ' . $lastDateOfResult);
        $this->logger->debug('date of last parsed statement: ' . $last_statement_end_date);

        foreach ($redemptionInfo as $item => $value) {
            if (
                $businessCard === true
                    && (
                        $lastDateOfResult > $value['Date']
                        || $firstDateOfResult < $value['Date']
                    )
            ) {
                $this->logger->debug("partialParsing, skip: {$value['Date']}");

                if (
                    $last_statement_end_date
                    && $lastDateOfResult < strtotime($last_statement_end_date)
                    && $value['Date'] < strtotime($last_statement_end_date)
                    && $firstDateOfResult < $value['Date']
                ) {
                    $this->logger->notice("partialParsing, transaction should be parsed: {$value['Date']}");
                } else {
                    continue;
                }
            }

            if ($value['Card Number'] != "XXXX-XXXXXX{$filterByCode}") {
                $this->logger->debug("skip non eligible transaction ({$value['Card Number']}) :{$value['Date']}");

                continue;
            }

            if (
                ($firstDateOfResult < $value['Date'] && $value['Date'] < $startDate)
                || $value['Date'] > strtotime("-7 days")
            ) {
                $this->logger->debug("skip {$value['Date']}");

                continue;
            }

            if (isset($value['Amount']) && $value['Amount'] > 0) {
                $this->logger->debug("skip non eligible transaction:");
                $this->logger->debug(var_export($value, true), ["pre" => true]);

                continue;
            }

            // refs #21294
            if (isset($value['Reference']) && in_array($value['Reference'], $this->mapReferences)) {
                $this->logger->debug("skip duplicate transaction:");
                $this->logger->debug(var_export($value, true), ["pre" => true]);

                continue;
            }

            $redemptions[] = $value;
        }
//        $this->logger->debug(var_export($redemptionInfo, true), ["pre" => true]);
        $this->logger->debug("spending by card #{$filterByCode}");
        $this->logger->debug(var_export($redemptions, true), ["pre" => true]);
        $result = array_merge($result, $redemptions);

        usort($result, function ($a, $b) { return $b['Date'] - $a['Date']; });
//        $this->logger->debug(var_export($result, true), ["pre" => true]);

        return $result;
    }

    private function parsePageSubAccActivity($startDate, $startIndex)
    {
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $this->logger->debug("Total " . ArrayVal($response['metadata'] ?? null, 'total', "no transactions") . " activity rows were found");
        $transactions = ArrayVal($response, 'transactions', []);
        $nextIndex = ArrayVal($response, 'next_index', null);

        if ($nextIndex > 0) {
            $this->sendNotification("refs #18325 need to check history // RR");
        }

        // Your Points Summary is currently unavailable.
        // We’re sorry but our system is not responding. Please try again later.
        if ($this->http->Response['code'] == 502 && ArrayVal($response, 'message', null) == 'Exception calling LOYALTY_TRANSACTION_REST API') {
            $this->logger->error("Your Points Summary is currently unavailable. We’re sorry but our system is not responding. Please try again later.");
            $this->increaseTimeLimit(300);
        }

        foreach ($transactions as $transaction) {
            $dateStr = ArrayVal($transaction, 'posted_date');

            if (!empty($dateStr)) {
                $postDate = strtotime($dateStr);
            } else {
                $postDate = null;
            }
            /*
            $status = ArrayVal($transaction, 'status');
            */
            // Transaction
            $result[$startIndex]['Date'] = $postDate;
            // Description
            $result[$startIndex]['Description'] = ArrayVal($transaction, 'descriptions');
            $result[$startIndex]['Card Number'] = ArrayVal($transaction, 'card_number');
            // Reference
            $result[$startIndex]['Reference'] = ArrayVal($transaction, 'id');
            // Amount
            $cash = ArrayVal($transaction, 'cash_value', []);
            $result[$startIndex]['Amount'] = ArrayVal($cash, 'value');
            // Currency
            $result[$startIndex]['Currency'] = ArrayVal($cash, 'currency_code');
            // Category
            $result[$startIndex]['Category'] = ArrayVal($transaction, 'category');
            // Additional Information
            $result[$startIndex]['Additional Information'] = json_encode($transaction);
            // Points
            $rewards = ArrayVal($transaction, 'reward_amount', []);
            $result[$startIndex]['Points'] = ArrayVal($rewards, 'value');

            if ($result[$startIndex]['Points'] > 0) {
//                $this->logger->notice("{$dateStr} ($postDate), skip non redemption transaction: '{$result[$startIndex]['Points']}'");
                continue;
            }
            // Rewards
            $result[$startIndex]['Rewards'] = json_encode($rewards);

            $startIndex++;
        }// foreach ($response->transactions as $transaction)

        return $result;
    }

    private function parsePageSubAccHistory($startDate, $startIndex, $statement_end_date, $headers, $businessCard, &$last_statement_end_date)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $this->logger->debug("Total " . ArrayVal($response, 'total_count', "no transactions") . " activity rows were found");
        $transactions = ArrayVal($response, 'transactions', []);

        foreach ($transactions as $k => $transaction) {
            $dateStr = ArrayVal($transaction, 'charge_date');

            if (empty($dateStr)) {
                $dateStr = ArrayVal($transaction, 'post_date');
            }

            if (!empty($dateStr)) {
                $postDate = strtotime($dateStr);
            } else {
                $postDate = null;
            }

            if (
                isset($startDate)
                && ($postDate < $startDate || $postDate > strtotime("-7 days"))// You'll be able to see Rewards information for an eligible charge within 5 days of the charge posting to your account. Please check back later.
            ) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->logger->notice("End of history reached");
                $last_statement_end_date = $statement_end_date;

                continue;
            }

            if (
                isset($postDate)
                && $businessCard
                && $this->http->ResponseNumber > 700
            ) {
                $this->logger->notice("too big history, break at date {$dateStr} ($postDate)");
                $this->logger->notice("End of history reached");

                continue;
            }
            // Transaction
            $result[$startIndex]['Date'] = $postDate;
            // Description
            $details = ArrayVal($transaction, 'extended_details');
            $merchant = ArrayVal($details, 'merchant');
            $result[$startIndex]['Description'] = ArrayVal($transaction, 'description');
            // credit card transaction
            if (empty($result[$startIndex]['Description'])) {
                $result[$startIndex]['Description'] = ArrayVal($merchant, 'name');
            }
            // Phone Number
            $result[$startIndex]['Phone'] = ArrayVal($merchant, 'phone_number');
            // Address
            $address = ArrayVal($merchant, 'address');
            $fullAddress = [
                implode(' ', array_filter(ArrayVal($address, 'address_lines', []), function ($elem) {
                    return $elem != '-';
                })),
                ArrayVal($address, 'city'),
                ArrayVal($address, 'state'),
                ArrayVal($address, 'postal_code'),
                ArrayVal($address, 'country_name'),
            ];
            $result[$startIndex]['Address'] = implode(', ', array_filter($fullAddress, function ($elem) {
                return !empty($elem);
            }));
            // Reference
            $result[$startIndex]['Reference'] = ArrayVal($transaction, 'reference_id');

            // refs #21294
            $this->mapReferences[] = $result[$startIndex]['Reference'];

            // Amount
            $result[$startIndex]['Amount'] = ArrayVal($transaction, 'amount');

            if ($result[$startIndex]['Amount']) {
                $result[$startIndex]['Currency'] = 'USD';

                // refs #21877
                if ($this->lang == 'en-GB') {
                    $result[$startIndex]['Currency'] = "GBP";
                }
            }
            // Category
            $rewards = ArrayVal($details, 'rewards', []);
            $category = ArrayVal($details, 'category');

            if (empty($category)) {
                $result[$startIndex]['Category'] = null;
            } else {
                $result[$startIndex]['Category'] = ArrayVal($category, 'category_name');
                $subcategory_name = ArrayVal($category, 'subcategory_name', null);

                $spend_category = ArrayVal($rewards, 'spend_category');

                if ($spend_category && !strstr($spend_category, 'Blue Business Plus')) {
                    $result[$startIndex]['Category'] .= " - " . $spend_category;
                } elseif ($subcategory_name) {
                    $result[$startIndex]['Category'] .= " - " . $subcategory_name;
                }
            }
            // Additional Information
            $result[$startIndex]['Additional Information'] = json_encode(ArrayVal($details, 'purchase_details', []));
            // Points
            $identifier = ArrayVal($transaction, 'identifier');

            if (
                (
                    empty($rewards)
                    || $rewards == ["display_indicator" => "NONE"]// https://redmine.awardwallet.com/issues/21294#note-7
                )
                && !empty($identifier)
            ) {
                $this->logger->debug("transaction #{$k}");
                $this->increaseMaxRequests();
                $this->http->GetURL("https://global.americanexpress.com/api/servicing/v1/financials/transactions/{$identifier}?extended_details=merchant%2Ccategory%2Cadditional_attributes%2Cpurchase_details%2Cadditional_description_lines%2Crewards&statement_end_date={$statement_end_date}&status=posted", $headers);
                $rewardsResponse = $this->http->JsonLog(null, 0, true);
                $rewards = ArrayVal(ArrayVal($rewardsResponse, 'extended_details'), 'rewards', []);

                // refs #23664
                if (
                    empty($rewards)
                    || $rewards == ["display_indicator" => "NONE"]// https://redmine.awardwallet.com/issues/21294#note-7
                ) {
                    $this->logger->debug("transaction #{$k}, alternative request");
                    $data = [
                        "accountToken"         => $headers['account_token'],
                        "productType"          => "AEXP_CARD_ACCOUNT",
                        "offset"               => 0,
                        "limit"                => 1000,
                        "transactionsFor"      => "LOYALTY_ACCOUNT",
                        "startDate"            => date("Y-m-d", strtotime("-2 days", $postDate)),
                        "endDate"              => date("Y-m-d", strtotime("+2 days", $postDate)),
                        "includeSuppCards"     => true,
                    ];
                    $this->http->RetryCount = 1;
                    $this->http->PostURL("https://functions.americanexpress.com/ReadLoyaltyTransactions.v1", json_encode($data), $headers);
                    $this->http->RetryCount = 2;
                    $rewardsResponse = $this->http->JsonLog(null, 5, true, 'currencyDescription');
                    $transactions = ArrayVal($rewardsResponse, 'transactions', []);

                    foreach ($transactions as $readLoyaltyTransaction) {
                        if (isset($readLoyaltyTransaction['id']) && $readLoyaltyTransaction['id'] == $identifier) {
                            $rewards = $readLoyaltyTransaction;

                            break;
                        }// if ($readLoyaltyTransaction['id'] == $identifier)
                    }// foreach ($transactions as $readLoyaltyTransaction)
                }
            }
            $result[$startIndex]['Points'] = ArrayVal(ArrayVal($rewards, 'total_rewards'), 'value', ArrayVal(ArrayVal($rewards, 'rewardAmount'), 'value'));
            // Rewards
            $result[$startIndex]['Rewards'] = json_encode($rewards);

            $startIndex++;
        }// foreach ($response->transactions as $transaction)

        return $result;
    }

    private function taiwanMRform($browser)
    {
        $this->logger->notice(__METHOD__);
        $fields = $browser->FindPregAll("/input type='hidden' name='Hidden' value='([^\']+)/ims");

        if ($browser->ParseForm(null, "//form[@action = '/myca/intl/rewards/japa/action']") && !empty($fields)) {
            $browser->MultiValuedForms = true;

            foreach ($fields as $mValue) {
                $data[] = urlencode("Hidden") . "=" . urlencode($mValue);
            }
            $data[] = urlencode("MRCARDSELECTION") . "=" . urlencode("0");
            $data[] = urlencode("PointBalanceButton") . "=" . urlencode("¬d ¾\ ¿n ¤À");
            $data[] = urlencode("request_type") . "=" . urlencode("authreg_MrMultipleAccountsDest");
            $browser->PostURL($browser->FormURL, implode("&", $data));
        }
    }

    private function doNotCollectInfo()
    {
        $this->logger->notice(__METHOD__);

        if (in_array($this->AccountFields['Login2'], ['South Africa', 'Saudi Arabia', 'ישראל', 'Switzerland'])) {
            return true;
        }
        // refs #9131
        if (in_array($this->AccountFields['Login2'], $this->northAfrica)) {
            return true;
        }

        return false;
    }

    private function seleniumItinerary()
    {
        $this->logger->notice(__METHOD__);

        try {
            // get cookies from curl
            $allCookies = array_merge($this->http->GetCookies("digital2.myamextravel.com"), $this->http->GetCookies("digital2.myamextravel.com", "/", true));
            $allCookies = array_merge($allCookies, $this->http->GetCookies(".myamextravel.com"), $this->http->GetCookies(".myamextravel.com", "/", true));

            $selenium = clone $this;
            $this->http->brotherBrowser($selenium->http);
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://digital2.myamextravel.com/client/www/www");

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".myamextravel.com"]);
            }

            $selenium->http->GetURL($this->http->currentUrl());
            $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), \'Upcoming Trips\')] | //div[contains(text(), \'You currently have no upcoming trips.\')]'), 10);
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (SessionNotCreatedException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (NoSuchDriverException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (WebDriverCurlException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (NoSuchWindowException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (UnknownServerException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } catch (NoSuchCollectionException $e) {
            $this->logger->error('SeleniumDriver::Exception - ' . $e->getMessage());
        } finally {
            // close Selenium browser
            if (isset($selenium)) {
                $selenium->http->cleanup();
            }// if (isset($selenium))
        }
    }

    /*function ParseFiles($filesStartDate) {
        $result = [];
        if ($this->doNotCollectInfo())
            return $result;
        $this->http->GetURL("https://online.americanexpress.com/myca/statementimage/us/welcome.do?Face=en_US&request_type=authreg_StatementCycles&intlink=SOAFins-ViewBillingStatement");
        ## Name
        $this->ParseName();
        // Parse cards
        $cardList = $this->http->FindPreg("/var _cardsList = (\[[^\]]+\])/ims");
        $this->http->Log("card list: ".$cardList);
        if (isset($cardList)) {
            $cardList = json_decode($cardList, true);
            $this->http->Log("card list decoded: ".var_export($cardList, true));
            foreach($cardList as $card){
                $this->http->Log("loading card: ".var_export($card, true));
                if(ArrayVal($card, 'template') == 'basic' && isset($card['index'])){
                    $this->http->GetURL("https://online.americanexpress.com/myca/statementimage/us/welcome.do?sorted_index={$card['index']}&request_type=authreg_StatementCycles&Face=en_US");
                    $hidden = $this->http->FindSingleNode("(//input[@name = 'Hidden'])[1]");
                    $cycleResponse = $this->http->FindPreg("#var cycleResponse = (.*)<\/script>\s*<\/div>\s*<!\-\- END HIDDEN INPUTS#ims");
                    $this->http->Log("cycled response: ".$cycleResponse);
                    $cycleResponse = json_decode($cycleResponse, true);
                    $this->http->Log("cycled response decoded: <pre>".json_encode($cycleResponse, JSON_PRETTY_PRINT)."</pre>", LOG_LEVEL_NORMAL, false);
                    // Pdf.Download('10122012',0,'olImg_0_0','2')
                    if(isset($cycleResponse['statementInfoListMap'])){
                        foreach($cycleResponse['statementInfoListMap'] as $infoList) foreach($infoList['onlineDataMap'] as $year) foreach($year as $file){
                            $this->http->Log("loading file: <pre>".json_encode($file, JSON_PRETTY_PRINT)."</pre>", LOG_LEVEL_NORMAL, false);
                            $date = mktime(0, 0, 0, substr($file['endDate'], 0, 2), substr($file['endDate'], 2, 2), substr($file['endDate'], 4, 4));
                            if(isset($filesStartDate) && $date < $filesStartDate)
                                continue;
                            $params = array(
                                "sorted_index" => $card['index'],
                                "Face" => "en_US",
                                "request_type" => "authreg_StatementImage",
                                "statement_Date" => $file['endDate'],
                                "formatType" => "1",
                                "cardSelected" => "Y",
                                "cardIndex" => $card['index'],
                                "Reauth" => "",
                                "isNationalIdAvailable" => "",
                                "isCustomerPasswordAvailable" => "",
                                "iszipAvailable" => "",
                                "iscardmembirthAvailable" => "",
                                "isphoneAvailable" => "",
                                "Birthplace" => "",
                                "phone" => "",
                                "NATIONALID" => "",
                                "MaidenName" => "",
                                "Password" => "",
                                "School" => "",
                                "SecurePin" => "",
                                "motherbirthday" => "",
                                "zip" => "",
                                "DATEYYYY" => "",
                                "DATEMM" => "",
                                "DATEDD" => "",
                                "cardindex" => $card['index'],
                                "Layer" => "",
                                "logoutURL" => "https://online.americanexpress.com/myca/logon/us/action?request_type=LogLogoffHandler&Face=en_US&inav=Logout",
                                "pdfURL" => "https://online.americanexpress.com/myca/statementimage/us/welcome.do?request_type=authreg_StatementCycles",
                                "Hidden" => $hidden,
                            );
                            $this->http->ParseEncoding = false;
                            $this->http->ParseDOM = false;
                            $this->http->ParseForms = false;
                            $this->http->LogResponses = false;
                            $this->http->PostURL("https://online.americanexpress.com/myca/statementimage/us/download.do?Download=true", $params);
                            if(strpos($this->http->Response['body'], '%PDF') === 0){
                                $this->http->Log("added file ".$file['viewEndDate']);
                                $result[] = array(
                                    'AccountNumber' => ArrayVal($card, 'account'),
                                    'AccountName' => ArrayVal($card, 'description'),
                                    "AccountType" => 'credit',
                                    'FileDate' => $date,
                                    'Name' => $file['viewEndDate'],
                                    'Extension' => 'pdf',
                                    'Contents' => $this->http->LastResponseFile(),
                                );
                            }
                            else
                                $this->http->Log("not pdf");
                        }
                    }
                }
            }
        }
        return $result;
    }*/

    private static function amexRegions()
    {
        return [
            'Argentina'              => 'Argentina',
            'Australia'              => 'Australia',
            'Bahrain'                => 'Bahrain',
            'Bangladesh'             => 'Bangladesh',
            'België'                 => 'België',
            'Brasil'                 => 'Brasil',
            'Bolivia'                => 'Bolivia',
            'Bosna I Hercegovina'    => 'Bosna I Hercegovina',
            'България'               => 'България',
            'Canada'                 => 'Canada',
            'Caribbean'              => 'Caribbean',
            'Česko'                  => 'Česko',
            'Chile'                  => 'Chile',
            'Crna Gora'              => 'Crna Gora',
            'Colombia'               => 'Colombia',
            'Cyprus'                 => 'Cyprus',
            'Danmark'                => 'Danmark',
            'Deutschland'            => 'Deutschland',
            'Dominican Republic'     => 'Dominican Republic',
            'Ecuador'                => 'Ecuador',
            'Eesti'                  => 'Eesti',
            'Egypt'                  => 'Egypt',
            'España'                 => 'España',
            'Ethiopia'               => 'Ethiopia',
            'Finland'                => 'Finland',
            'France'                 => 'France',
            'საქართველო'             => 'საქართველო',
            'Greater China Region'   => 'Greater China Region',
            'Greece'                 => 'Greece',
            'Guyana'                 => 'Guyana',
            'Հայաստան'               => 'Հայաստան',
            'Hrvatska'               => 'Hrvatska',
            'India'                  => 'India',
            'Indonesia'              => 'Indonesia',
            'Ísland'                 => 'Ísland',
            'ישראל'                  => 'ישראל',
            'Italia'                 => 'Italia',
            '日本'                     => '日本',
            'Jordan'                 => 'Jordan',
            'Kazakhstan'             => 'Kazakhstan',
            'Kenya'                  => 'Kenya',
            '대한민국'                   => '대한민국',
            'Kuwait'                 => 'Kuwait',
            'Latin America'          => 'Latin America',
            'Latvija'                => 'Latvija',
            'Lebanon'                => 'Lebanon',
            'Lietuva'                => 'Lietuva',
            'Luxembourg'             => 'Luxembourg',
            'Magyarország'           => 'Magyarország',
            'Malaysia'               => 'Malaysia',
            'Maldives'               => 'Maldives',
            'Mauritius'              => 'Mauritius',
            'México'                 => 'México',
            'Moldova'                => 'Moldova',
            'Mongolia'               => 'Mongolia',
            'Mozambique'             => 'Mozambique',
            'Nederland'              => 'Nederland',
            'New Zealand'            => 'New Zealand',
            'Nigeria'                => 'Nigeria',
            'Norge'                  => 'Norge',
            'North Macedonia'        => 'North Macedonia',
            'Oman'                   => 'Oman',
            'Österreich'             => 'Österreich',
            'Panamá'                 => 'Panamá',
            'Paraguay'               => 'Paraguay',
            'Perú'                   => 'Perú',
            'Philippines'            => 'Philippines',
            'Polska'                 => 'Polska',
            'Portugal'               => 'Portugal',
            'Puerto Rico u0026 USVI' => 'Puerto Rico u0026 USVI',
            'Qatar'                  => 'Qatar',
            'Romania'                => 'Romania',
            'Russia'                 => 'Russia',
            'Rwanda'                 => 'Rwanda',
            'Saudi Arabia'           => 'Saudi Arabia',
            'Shqipëria'              => 'Shqipëria',
            'Singapore'              => 'Singapore',
            'Slovenija'              => 'Slovenija',
            'Slovensko'              => 'Slovensko',
            'South Africa'           => 'South Africa',
            'Sri Lanka'              => 'Sri Lanka',
            'Srbija'                 => 'Srbija',
            'Sverige'                => 'Sverige',
            'Switzerland'            => 'Switzerland',
            'ไทย'                    => 'ไทย',
            'Türkiye'                => 'Türkiye',
            'United Arab Emirates'   => 'United Arab Emirates',
            'United Kingdom'         => 'United Kingdom',
            'United States'          => 'United States',
            'Uruguay'                => 'Uruguay',
            'Japan'                  => 'Japan',
            'Taiwan'                 => 'Taiwan',
        ];
    }
}
