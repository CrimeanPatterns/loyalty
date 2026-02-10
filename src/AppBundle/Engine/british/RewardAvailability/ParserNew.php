<?php

namespace AwardWallet\Engine\british\RewardAvailability;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class ParserNew extends \TAccountCheckerBritish
{
    use \PriceTools;
    use ProxyList;
    use \SeleniumCheckerHelper;

    protected $seleniumURL;

    private $isHot = false;
    private $curl;
    private $link;
    private $depDateIn;
    private $ports = [];
    /**
     * @var \CaptchaRecognizer
     */
    private $recognizer;

    private $inCabin;
    private $inDateDep;

    public static function getRASearchLinks(): array
    {
        return ['https://www.britishairways.com/travel/redeem/execclub/_gf/en_us?eId=106019&tab_selected=redeem&redemption_type=STD_RED'=>'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();

        $this->KeepState = false;

        $this->http->setHttp2(false);

        $this->UseSelenium();
        $this->setProxyNetNut();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->logger->info('chosenResolution:');
        $this->logger->info(var_export($chosenResolution, true));
        $this->setScreenResolution($chosenResolution);

        if ($this->attempt !== 1) {
            $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
            $this->seleniumOptions->addHideSeleniumExtension = false;
        } else {
            $this->useChromePuppeteer();
        }

        $this->seleniumOptions->userAgent = null;
        $this->usePacFile(false);

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->disableImages();
        $this->http->saveScreenshots = true;

        $this->seleniumRequest->setHotSessionPool(
            self::class . 'new',
            $this->AccountFields['ProviderCode'],
            $this->AccountFields['AccountKey']
        );
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0, // 1
            'defaultCurrency'          => 'USD',
        ];
    }

    public function IsLoggedIn()
    {
        if (strpos($this->http->currentUrl(), 'www.britishairways.com') !== false) {
            $this->logger->debug('is hot');

            if ($btn = $this->waitForElement(\WebDriverBy::xpath("//ba-button[contains(,'Stay logged in')]"), 0)) {
                $btn->click();
                sleep(2);
            }
            $this->http->GetURL("https://www.britishairways.com/travel/redeem/execclub/public/en_us?eId=106019&tab_selected=redeem&redemption_type=STD_RED", [], 20);
            $this->saveResponse();

            if ($this->isBadProxy()) {
                throw new \CheckRetryNeededException(5, 0);
            }

            return true;
        }
        $this->http->GetURL("https://www.britishairways.com/travel/viewaccount/execclub/_gf/en_us", [], 20);

        if ($this->http->FindSingleNode("//div[@class='member-name']")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if ($this->attempt > 0) {
            if (strpos($this->http->currentUrl(), 'www.britishairways.com') !== false) {
                $this->logger->debug('is hot');

                if ($btn = $this->waitForElement(\WebDriverBy::xpath("//ba-button[contains(,'Stay logged in')]"), 0)) {
                    $btn->click();
                    sleep(2);
                }
                $this->http->GetURL("https://www.britishairways.com/travel/redeem/execclub/public/en_us?eId=106019&tab_selected=redeem&redemption_type=STD_RED",
                    [], 20);
                $this->saveResponse();

                if ($this->isBadProxy()) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                $this->isHot = true;

                return true;
            }
        }

        return parent::LoadLoginForm();
    }

    public function Login()
    {
        if ($this->attempt > 0 && $this->isHot) {
            return true;
        }

        return parent::Login();
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->notice('logged in, Save session');
        $this->keepSession(true);

        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        $this->logger->notice(
            'parsing started at: ' . date("H:i:s", $this->requestDateTime)
        );

        $this->inDateDep = $fields['DepDate'];

        if ($fields['DepDate'] > strtotime('+355 day')) {
            $this->SetWarning('too late');

            return [];
        }

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }
        $this->inCabin = $fields['Cabin'];
        $fields['Cabin'] = $this->getCabinFields(false)[$fields['Cabin']];
        $countryCode = $this->getCountryCode();

        $this->depDateIn = $fields['DepDate'];

        if ($countryCode === 'us') {
            $fields['DepDate'] = date("m/d/y", $fields['DepDate']);
        } else {
            $fields['DepDate'] = date("d/m/y", $fields['DepDate']);
        }

        try {
            $result = $this->ParseReward($fields);
        } catch (\WebDriverException | \WebDriverCurlException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        return ['routes' => $result];
    }

    public function checkErrors($http = null)
    {
        if (!isset($http)) {
            $http = $this->http;
        }
        $this->logger->notice(__METHOD__);
        // Sorry, our website is unavailable while we make a quick update to our systems.
        if ($message = $http->FindSingleNode("
                //p[contains(text(), 'Sorry, our website is unavailable while we make a quick update to our systems.')]
                | //p[contains(text(), 'Both ba.com and our apps are temporarily unavailable while we make some planned improvements to our systems.')]
            ")
        ) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // System Upgrade
        if ($message = $http->FindSingleNode("//p[contains(text(), 'Due to the Executive Club System Upgrade you will experience limited access to your account')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We regret to advise that this section of the site is temporarily unavailable.
        if ($message = $http->FindPreg("/(We regret to advise that this section of the site is temporarily unavailable\.)/ims")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Unfortunately our systems are not responding
        if ($message = $http->FindSingleNode("//p[contains(text(),'Unfortunately our systems are not responding')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently carrying out site maintenance between ...
        if ($message = $http->FindSingleNode("//p[contains(text(), 'We are currently carrying out site maintenance')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There is currently no access to your account while we upgrade our system
        if ($message = $http->FindSingleNode("//li[contains(text(),'There is currently no access to your account while we upgrade our system')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a technical problem. Please try again in a few minutes.
        if ($message = $http->FindSingleNode("//p[contains(text(), 'Sorry, there seems to be a technical problem. Please try again in a few minutes.')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing technical issues today with our website.
        if ($message = $http->FindSingleNode("//p[contains(text(), 'We are experiencing technical issues today with our website.')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Sorry, there seems to be a technical problem. Please try again in a few minutes, and please contact us if it still doesn't work.
         * We apologise for the inconvenience.
         */
        if ($message = $http->FindSingleNode("//p[contains(text(), 'Sorry, there seems to be a technical problem. Please try again in a few minutes')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Major IT system failure . latest information at 23.30 Saturday May 27
         *
         * Following the major IT system failure experienced throughout Saturday,
         * we are continuing to work hard to fully restore all of our global IT systems.
         *
         * Flights on Saturday May 27
         */
        if ($message = $http->FindSingleNode("//p[contains(text(), 'Following the major IT system failure experienced throughout')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Internal Server Error - Read
            $http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $http->FindPreg("/An error occurred while processing your request\./")
        ) {
            throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[URL]: " . $http->currentUrl());
        $this->logger->debug("[CODE]: " . $http->Response['code']);
        // retries
        if (in_array($http->Response['code'], [0, 301, 302, 403])
            || ($http->Response['code'] == 200 && empty($http->Response['body']))) {
            if ($http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckRetryNeededException(3, 5);
        // error in selenium
        } elseif (
            $http->Response['code'] == 200
            && $http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further") or contains(text(), "We are experiencing high demand on ba.com at the moment.")]')
        ) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(3, 0);
        }

        $this->logger->debug('[checkErrors. date: ' . date('Y/m/d H:i:s') . ']');

        return false;
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => 'Economy',
            'premiumEconomy' => 'Premium economy',
            'firstClass'     => 'First',
            'business'       => 'Business Class',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function initCurl()
    {
        $this->logger->notice(__METHOD__);

        if (isset($this->curl)) {
            $this->logger->debug('curl already exists');

            return;
        }

        $this->curl = new \HttpBrowser("none", new \CurlDriver());
        $this->curl->setProxyParams($this->http->getProxyParams());

        $this->http->brotherBrowser($this->curl);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                $cookie['expiry'] ?? null);
        }
        $this->curl->setUserAgent($this->http->getDefaultHeader('User-Agent'));
        $this->curl->GetURL("https://ipinfo.io/ip");
    }

    private function ParseReward($fields = [], ?bool $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);
        $countryCode = $this->getCountryCode();

        $mainUrl = "https://www.britishairways.com/travel/redeem/execclub/execclub/_gf/en_{$countryCode}?eId=106019&tab_selected=redeem&redemption_type=STD_RED";

        $this->http->GetURL($mainUrl);

        if (!$this->validRoute($fields)) {
            return [];
        }

        $this->initCurl();

        if (strcasecmp($fields['Cabin'], 'Business/Club') === 0
            || strcasecmp($fields['Cabin'], 'Business Class') === 0
        ) {
            $cabin = 'Business Class';
            $fields['Cabin'] = 'Business/Club';
        } else {
            $cabin = ucwords(strtolower($fields['Cabin']));
            $fields['Cabin'] = ucfirst(strtolower($fields['Cabin']));
        }
//        $this->curl->GetURL("https://www.britishairways.com/travel/redeem/execclub?eId=106019&tab_selected=redeem&redemption_type=STD_RED");
        $this->curl->RetryCount = 0;
//        $this->curl->GetURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}?eId=106019&tab_selected=redeem&redemption_type=STD_RED", [], 30);
        $this->curl->GetURL($mainUrl, [], 30);

        if ($this->isBadProxy($this->curl)) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$this->curl->ParseForm("plan_redeem_trip")) {
            $this->logger->debug('try again');
//            $this->curl->GetURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}?eId=106019&tab_selected=redeem&redemption_type=STD_RED", [], 30);
            $this->curl->GetURL("https://www.britishairways.com/travel/redeem/execclub/execclub/_gf/en_{$countryCode}?eId=106019&tab_selected=redeem&redemption_type=STD_RED", [], 30);

            if ($this->isBadProxy($this->curl)) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $this->curl->RetryCount = 2;

        if (!$this->curl->ParseForm("plan_redeem_trip")) {
            $this->logger->error('check parse');

            if ($this->curl->Response['code'] == 403) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException('not load plan_redeem_trip', ACCOUNT_ENGINE_ERROR);
        }

        if (!$this->fillRedeemTrip($fields, $cabin)) {
            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->postRedeemTrip($isRetry, $fields, $cabin);

        if ($this->curl->FindPreg("/There was a problem with your request, please try again later./")
            && ($link = $this->curl->FindSingleNode("//a[normalize-space()='Start again']/@href"))
        ) {
            $this->curl->NormalizeURL($link);
            $this->curl->GetURL($link);

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($msg = $this->curl->FindSingleNode("//text()[contains(normalize-space(),'a problem with our systems. Please try again, and if it still doesn')]")) {
            $this->logger->error($msg);

            if ($this->attempt === 0) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->error($msg);

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        if ($msg = $this->curl->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
            $this->logger->error($msg);
            // TODO retry|restart doesn't help - надо проверять запросы

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        if ($msg = $this->curl->FindSingleNode("//h3[contains(.,'There is no availability on British Airways for the displayed date')]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($this->curl->FindSingleNode("//h2[contains(.,'Choose your travel dates')]")) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($msg = $this->curl->FindSingleNode("//h3[contains(.,'If there’s no availability on your chosen dates you can') or contains(.,\"If there's no availability on your chosen dates you can\")]")) {
            $this->SetWarning('There’s no availability on your chosen dates');

            return [];
        }

        if ($msg = $this->curl->FindSingleNode("//div[@id='blsErrors']//li[contains(.,'There was a problem with your request, please try again later')]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($msg = $this->curl->FindSingleNode('//div[@id="blsErrors"]//li[contains(.,"It looks like you\'re using multiple tabs") or contains(.,"Resource Not Found Error Received from ASSOCIATE API from AGL Group Loyalty Platform")]')) {
            $this->logger->error($msg);

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            if ($this->attempt === 0) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
        }

        if ($msg = $this->curl->FindSingleNode("//div[@id='blsErrors']//li[contains(.,'British Airways and its partners do not fly this route. Please consider alternative destinations')]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($msg = $this->curl->FindSingleNode("(//div[@id='blsErrors']//li[normalize-space()!=''])[1]")) {
            $this->logger->error($msg);

            if (strpos($msg, 'Sorry, it is only possible to book flights up to 355 days in advance') !== false
                || strpos($msg, 'point has not been recognised. Please ensure you select a ') !== false
            ) {
                $this->SetWarning($msg);

                return [];
            }

            if ($this->curl->FindPreg("/Not able to connect to AGL Group Loyalty Platform and IO Error Recieved/",
                false, $msg)) {
                $this->SetWarning('No availability on your chosen dates');

                return [];
            }

            if ($this->curl->FindPreg("/We're sorry, but ba.com is very busy at the moment, and couldn't deal with your request. Please do try again/",
                false, $msg)) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->curl->FindPreg("/Internal Server Error Received from IAGL API ASSOCIATE/", false, $msg)) {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
            }

            if ($this->curl->FindPreg("/Sorry, something went wrong. Please try again or contact us/", false, $msg)) {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
            }

            if ($this->curl->FindPreg("/(Web Service Query Error|Web Service Connection Error|problem with your request. Please try again, and if you)/",
                false, $msg)) {
                if ($this->curl->FindPreg("/(problem with your request. Please try again, and if you)/", false, $msg)) {
                    $this->sendNotification("check retry after problem with your request // ZM");
                }

                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->curl->FindPreg("/(?:We are encountering a temporary fault, please try again. If the problem persists, please contact your|Sorry, something went wrong. Please try again or contact us)/",
                false, $msg)) {
                if (time() - $this->requestDateTime < $this->AccountFields['Timeout'] - 5) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->curl->FindPreg("/There is currently no access to your account while we upgrade our system./",
                false, $msg)) {
                if ($this->attempt !== 4 && (time() - $this->requestDateTime < $this->AccountFields['Timeout'] - 5)) {
                    throw new \CheckRetryNeededException(5, 10);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
            $this->sendNotification("check msg // ZM");
        }

        try {
            $routes = $this->parseRewardFlights($fields, $cabin);
        } catch (\CheckException $e) {
            if ($e->getMessage() === 'wrong format: departInputDate') {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException('Something went wrong', ACCOUNT_ENGINE_ERROR);
            }

            throw $e;
        }

        return $routes;
    }

    private function validRoute(array $fields): bool
    {
        if (!$this->hasAirport($fields['DepCode'])) {
            $this->SetWarning('no flights from ' . $fields['DepCode']);

            return false;
        }

        if (!$this->hasAirport($fields['ArrCode'])) {
            $this->SetWarning('no flights to ' . $fields['ArrCode']);

            return false;
        }

        if (count($this->ports) !== 2) {
            $this->logger->error("has no airport details");
            $this->logger->notice(var_export($this->ports, true), ['pre' => true]);

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->logger->notice(var_export($this->ports, true), ['pre' => true]);

        return true;
    }

    private function hasAirport($airport): bool
    {
        $data = \Cache::getInstance()->get('ra_british_locations' . $airport);

        if ($data === false || !is_string($data)) {
            $headers = [
                "Accept"           => "*/*",
                "Accept-Language"  => "en-US,en;q=0.5",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            // not award
            // "https://www.britishairways.com/api/sc4/csdm-plm/rs/v1/productlocations;searchText=LAX?locale=en_US"
            $url = "https://www.britishairways.com/dwr/exec/locationHelper.getMatchedLocations.dwr?callCount=1&c0-scriptName=locationHelper&c0-methodName=getMatchedLocations&xml=true&c0-param0=string:" . $airport;
            $referer = "https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$this->getCountryCode()}?eId=106019&tab_selected=redeem&redemption_type=STD_RED";
            $data = $this->getFetch('airport' . $airport, $url, 'GET', $headers, [], $referer);

            if (strpos($data, 'var s0={}') === false) {
//                $this->sendNotification("check airport " . $airport . " // ZM");

                throw new \CheckRetryNeededException(5, 0);
            }

            \Cache::getInstance()->set('ra_british_locations' . $airport, $data, 60 * 60 * 24 * 7);
        }

        $stringNumber = $this->http->FindPreg("/s0\['" . $airport . "']=(s\d+)/", false, $data);

        if (null === $stringNumber) {
            return false;
        }
        $this->ports[$airport] = $this->http->FindPreg('/' . $stringNumber . '="([^"]+)"/', false, $data);

        if (empty($this->ports[$airport])) {
            $this->sendNotification("check airport " . $airport . " (2) // ZM");

            throw new \CheckRetryNeededException(5, 0);
//            throw new \CheckException("check airport", ACCOUNT_ENGINE_ERROR);
        }

        return true;
    }

    private function getXHR($url, $method, array $headers)
    {
        $headersString = "";

        foreach ($headers as $key=>$value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                xhttp.open("' . $method . '", "' . $url . '", false);
                ' . $headersString . '
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202)) {
                        responseText = this.responseText;
                    }
                };
                xhttp.send();
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre'=>true]);

        return $this->driver->executeScript($script);
    }

    private function getFetch($id, $url, $method, array $headers, $payload, $referer)
    {
        $this->logger->notice(__METHOD__);

//        if (!isset($headers['Referer'])) {
//            $headers['Referer'] = $referer;
//        }
//
//        return $this->getXHR("POST", $url, $headers);

        $headers = json_encode($headers);
//        $payload = base64_encode($payload);
//                   "body": atob("' . $payload . '"),
        $script = '
                fetch("' . $url . '", {
                  "headers": ' . $headers . ',
                  "referrer": "' . $referer . '",
                  "method": "' . $method . '",
                  "mode": "cors",
                  "credentials": "include"
                }).then( response => response.text())
                  .then( result => {
                    let script = document.createElement("script");
                    let id = "' . $id . '";
                    script.id = id;
                    script.setAttribute(id, result);
                    document.querySelector("body").append(script);
                })
                .catch(error => {                    
                    let newDiv = document.createElement("div");
                    let id = "' . $id . '";
                    newDiv.id = id;
                    let newContent = document.createTextNode(error);
                    newDiv.appendChild(newContent);
                    document.querySelector("body").append(newDiv);
                });
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre'=>true]);
        $this->driver->executeScript($script);

        $this->waitForElement(\WebDriverBy::xpath('//*[@id="' . $id . '"]'), 20, false);
        $ext = $this->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '"]'), 0, false);
        $this->saveResponse();

        if (!$ext) {
            $this->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '"]'), 0, false);

            return null;
        }

        return $ext->getAttribute($id);
    }

    private function fillRedeemTrip($fields, $cabin): bool
    {
        $this->logger->notice(__METHOD__);
        $this->curl->SetInputValue("departurePoint", $this->ports[$fields['DepCode']]);
        $this->curl->SetInputValue("destinationPoint", $this->ports[$fields['ArrCode']]);
        $this->curl->SetInputValue("departInputDate", $fields['DepDate']);
        $this->curl->SetInputValue("oneWay", "true");

        $cabinNames = $this->curl->FindPreg('/data-cabinNames="([^"]+)" data-cabinCodes="[\w:]+"/');
        $cabinCodes = $this->curl->FindPreg('/data-cabinCodes="([^"]+)"/');

        if (empty($cabinNames) || empty($cabinNames)) {
            $this->sendNotification('check cabin codes');

            throw new \CheckException('other cabin codes', ACCOUNT_ENGINE_ERROR);
        }
        $cabinNames = explode(":", $cabinNames);
        $cabinCodes = explode(":", $cabinCodes);
        $cabins = array_combine($cabinNames, $cabinCodes);
        $this->logger->notice(var_export($cabin, true));
        $this->logger->notice(var_export($cabins, true));

        if (!isset($cabins[$fields['Cabin']]) && !isset($cabins[$cabin])) {
            $this->logger->notice('change cabin');

            if ($fields['Cabin'] === 'Premium economy') {
                $fields['Cabin'] = 'Economy';
            }
        }

        if (!isset($cabins[$fields['Cabin']]) && !isset($cabins[$cabin])) {
            return false;
        }
        $this->curl->SetInputValue("CabinCode", $cabins[$fields['Cabin']] ?? $cabins[$cabin]);
        $this->curl->SetInputValue("NumberOfAdults", $fields['Adults']);
        $this->curl->SetInputValue("NumberOfYoungAdults", 0);
        $this->curl->SetInputValue("NumberOfChildren", 0);
        $this->curl->SetInputValue("NumberOfInfants", 0);
        $this->curl->Form['DEVICE_TYPE'] = 'DESKTOP';

        if (isset($this->curl->Form['returnInputDate'])) {
            unset($this->curl->Form['returnInputDate']);
        }

        if (isset($this->curl->Form['upgradeInbound'])) {
            unset($this->curl->Form['upgradeInbound']);
        }

        if (isset($this->curl->Form['upgrade_redemption_type'])) {
            unset($this->curl->Form['upgrade_redemption_type']);
        }

        return true;
    }

    private function postRedeemTrip($isRetry, $fields, $cabin): void
    {
        $this->logger->notice(__METHOD__ . ' with isRetry=' . var_export($isRetry, true));

        if (!$this->curl->PostForm()) {
            $this->checkErrors();

            throw new \CheckException('error post plan_redeem_trip', ACCOUNT_ENGINE_ERROR);
        }

        if ($this->curl->Response['code'] == 403) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->curl->FindPreg("/Validation question/") && $this->curl->ParseForm("captcha_form")) {
            $this->sendNotification('captcha on search (1) // ZM');

            throw new \CheckException('captcha', ACCOUNT_ENGINE_ERROR);
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            $this->curl->SetInputValue('g-recaptcha-response', $captcha);

            if (!$this->curl->PostForm()) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->curl->FindPreg("/Validation question/")
                && $this->curl->ParseForm("captcha_form")
                && $this->curl->FindSingleNode("//p[normalize-space(text())='You did not validate successfully. Please try again.']")
            ) {
                $this->captchaReporting($this->recognizer, false);
                $this->sendNotification('captcha on search (2) // ZM');

                throw new \CheckException('captcha', ACCOUNT_ENGINE_ERROR);
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                $this->curl->SetInputValue('g-recaptcha-response', $captcha);

                if (!$this->curl->PostForm()) {
                    throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }
        }

        if (!$this->curl->ParseForm("SubmitFromInterstitial")) {
            if ($this->curl->FindSingleNode("//li[contains(.,\"It looks like you're using multiple tabs in the same browser. Please use a single tab to continue.\")]")) {
                if (!$this->curl->ParseForm("plan_redeem_trip")) {
                    $this->logger->error('check parse');

                    if ($msg = $this->curl->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
                        $this->logger->error($msg);

                        throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                    }

                    throw new \CheckException('not load plan_redeem_trip', ACCOUNT_ENGINE_ERROR);
                }

                if (!$isRetry && $this->fillRedeemTrip($fields, $cabin)) {
                    $this->postRedeemTrip(true, $fields, $cabin);

                    return;
                }
                $this->logger->notice('failed retry, try restart');

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->curl->FindSingleNode("//p[contains(normalize-space(),'You did not validate successfully. Please try again')]")) {
                // TODO m/b it's better retry
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->error('check parse');

            if ($msg = $this->curl->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
                $this->logger->error($msg);

                if ($this->attempt == 0) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckException('no form SubmitFromInterstitial', ACCOUNT_ENGINE_ERROR);
        }
        $eventId = $this->curl->FindPreg("/var eventId = '(\d+)';/");

        if (empty($eventId)) {
            $this->logger->error('can\'t find eventId');

            throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
        }
        $this->curl->SetInputValue("eId", $eventId);

        if (!$this->curl->PostForm()) {
            $this->checkErrors();
            $this->logger->error('error post SubmitFromInterstitial');

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->curl->FindPreg("/Validation question/") && $this->curl->ParseForm("captcha_form")) {
            $this->sendNotification('captcha on search (3) // ZM');

            throw new \CheckException('captcha', ACCOUNT_ENGINE_ERROR);
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            $this->curl->SetInputValue('g-recaptcha-response', $captcha);

            if (!$this->curl->PostForm()) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            $eventId = $this->curl->FindPreg("/var eventId = '(\d+)';/");

            if ($this->curl->ParseForm("SubmitFromInterstitial")) {
                if (empty($eventId)) {
                    $this->logger->error('can\'t find eventId (2)');

                    throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
                }

                if (!$this->curl->PostForm()) {
                    $this->checkErrors();
                    $this->logger->error('error post SubmitFromInterstitial');

                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            if ($this->curl->FindPreg("/Validation question/") && $this->curl->ParseForm("captcha_form")) {
                throw new \CheckException('walks in a circle', ACCOUNT_ENGINE_ERROR);
            }
        }

        if ($this->curl->ParseForm("plan_trip")) {
            if ($this->curl->Form['departurePoint'] !== $this->ports[$fields['DepCode']]
                || $this->curl->Form['destinationPoint'] !== $this->ports[$fields['ArrCode']]
                || $this->curl->Form['departInputDate'] !== $fields['DepDate']
            ) {
                // sometimes captcha broke request, fix it
                $this->curl->Form['departurePoint'] = $this->ports[$fields['DepCode']];
                $this->curl->Form['destinationPoint'] = $this->ports[$fields['ArrCode']];
                $this->curl->Form['departInputDate'] = $fields['DepDate'];
            }
            // no stopovers
            if (!$this->curl->PostForm()) {
                $this->checkErrors();

                throw new \CheckException('error post plan_trip', ACCOUNT_ENGINE_ERROR);
            }

            if ($this->curl->ParseForm("SubmitFromInterstitial")) {
                $eventId = $this->curl->FindPreg("/var eventId = '(\d+)';/");

                if (empty($eventId)) {
                    $this->logger->error('can\'t find eventId');

                    throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
                }
                $this->curl->SetInputValue("eId", $eventId);

                if (!$this->curl->PostForm()) {
                    $this->checkErrors();
                    $this->logger->error('error post SubmitFromInterstitial');

                    throw new \CheckRetryNeededException(5, 0);
                }
            }
        }

        if ($this->curl->ParseForm("stopOverForm")) {
            // no stopovers
            if (!$this->curl->PostForm()) {
                $this->checkErrors();

                throw new \CheckException('error post stopOverForm', ACCOUNT_ENGINE_ERROR);
            }

            if ($this->curl->ParseForm("SubmitFromInterstitial")) {
                $this->logger->error('check parse');
                $eventId = $this->curl->FindPreg("/var eventId = '(\d+)';/");

                if (empty($eventId)) {
                    $this->logger->error('can\'t find eventId');

                    throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
                }
                $this->curl->SetInputValue("eId", $eventId);

                if (!$this->curl->PostForm()) {
                    $this->checkErrors();
                    $this->logger->error('error post SubmitFromInterstitial');

                    throw new \CheckRetryNeededException(5, 0);
                }
            }
        }

        if ($this->curl->ParseForm("plan_redeem_trip") && $this->curl->FindSingleNode("//li[normalize-space()='Book with Avios']")
            && !$this->curl->FindSingleNode("//div[@id='blsErrors']")
        ) {
            if (!$isRetry && $this->fillRedeemTrip($fields, $cabin)) {
                $this->postRedeemTrip(true, $fields, $cabin);

                return;
            }

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function parseRewardFlights($fields, $cabinIn): array
    {
        $countryCode = $this->getCountryCode();

        // fix XPath
        $this->curl->FilterHTML = false;
        $this->curl->SetBody($this->curl->Response['body'] = preg_replace("#\b(ns|xmlns)\b=\"[^\"]+\"#i", '', $this->curl->Response['body']));

        $routes = [];
        // if no filter by cabin
        $xpathAllCabins = "//div[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')]/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";

        // if filter by cabin
//        $xpath = "//div[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')][normalize-space()='{$cabinIn}']/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";
        $xpath = "//button[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')][normalize-space()='{$cabinIn}']/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";
        // if filter by exclude selected cabin
//        $xpathExclude = "//div[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')][normalize-space()!='{$cabinIn}']/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";
        $xpathExclude = "//button[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')][normalize-space()!='{$cabinIn}']/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";

        $Roots = $this->curl->XPath->query($rootStr = "//div[@class='direct-flight-details' or @class='conn-flight-details'][.{$xpath}]");
        $this->logger->debug("Found {$Roots->length} routes");
        $this->logger->warning($rootStr);

        if ($Roots->length === 0 && $this->curl->FindSingleNode("//li[@data-date-value='{$this->inDateDep}000']/a/span[normalize-space()='No availability']")) {
            $this->SetWarning('No availability');

            return [];
        }
        $formToken = null;
        $flightDetails = [];

        // check date of result
        $segmentsRoot_0 = $this->curl->XPath->query(".//div[contains(@class,'travel-time-detail')]", $Roots->item(0));

        if ($segmentsRoot_0->length > 0) {
            $r = $segmentsRoot_0->item(0);
            $date = $this->curl->FindSingleNode("./div[1]/descendant::p[contains(@class,'date')][1]", $r);
            $date1 = $this->curl->FindSingleNode("//li[contains(@class,'active-tab')]//span[contains(@class,'datemonth')]");
            $depDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));
            $depDate1 = EmailDateHelper::parseDateRelative($date1, strtotime('-1 day'));

            if ($depDate !== $this->depDateIn && $depDate1 !== $this->depDateIn) {
                $this->logger->debug('segment Date' . var_export($depDate, true));
                $this->logger->debug('checked Date' . var_export($depDate1, true));
                $this->logger->debug('input Date' . var_export($this->depDateIn, true));

                throw new \CheckException('wrong format: departInputDate', ACCOUNT_ENGINE_ERROR);
            }
        }
        $skippedPrice = null;
        $routesProblemWithBooking = $routesTechnicalProblem = [];

        $wasBreak = $this->collectRoutes($Roots, $xpath, $fields, $countryCode, false, $routes, $formToken, $skippedPrice, $flightDetails, $routesProblemWithBooking, $routesTechnicalProblem);

        if ($wasBreak) {
            return $routes;
        }

        if ((time() - $this->requestDateTime) < $this->AccountFields['Timeout'] || empty($routes)) {
            $this->logger->warning('collect flights with other cabins');
            $Roots = $this->curl->XPath->query($rootStr = "//div[@class='direct-flight-details' or @class='conn-flight-details'][.{$xpathExclude}]");
            $this->logger->debug("Found {$Roots->length} routes");
            $this->logger->warning($rootStr);

            if (empty($routes) && $Roots->length > 0) {
                $segmentsRoot_0 = $this->curl->XPath->query(".//div[contains(@class,'travel-time-detail')]",
                    $Roots->item(0));
                $r = $segmentsRoot_0->item(0);
                $date = $this->curl->FindSingleNode("./div[1]/descendant::p[contains(@class,'date')][1]", $r);
                $depDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));

                if ($depDate !== $this->depDateIn) {
                    $this->logger->debug(var_export($depDate, true));
                    $this->logger->debug(var_export($this->depDateIn, true));

                    throw new \CheckException('wrong format: departInputDate', ACCOUNT_ENGINE_ERROR);
                }
            }
            $wasBreak = $this->collectRoutes($Roots, $xpathExclude, $fields, $countryCode, true, $routes, $formToken, $skippedPrice, $flightDetails, $routesProblemWithBooking, $routesTechnicalProblem);

            if ($wasBreak) {
                return $routes;
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        if (empty($routes)) {
            $this->SetWarning('No results');
        }

        $Roots = $this->curl->XPath->query("//div[@class='direct-flight-details' or @class='conn-flight-details'][.{$xpathAllCabins}]");

        if (isset($skippedPrice) && empty($routes) && $Roots->length) {
            $routesProblemWithBooking = array_unique($routesProblemWithBooking);

            if ($Roots->length === count($routesProblemWithBooking)) {
                throw new \CheckException('Sorry, there’s a problem with booking this journey online. You can try selecting other flights or contact us so an agent can help you book the flights you have selected', ACCOUNT_PROVIDER_ERROR);
            }

            $routesTechnicalProblem = array_unique($routesTechnicalProblem);

            if ($Roots->length === count($routesTechnicalProblem)) {
                throw new \CheckException('Sorry, there seems to be a technical problem. Please try again in a few minutes', ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckException("can't get prices", ACCOUNT_ENGINE_ERROR);
        }

        return $routes;
    }

    private function collectRoutes(
        $Roots,
        $xpath,
        $fields,
        $countryCode,
        $checkLimit,
        &$routes,
        &$formToken,
        &$skippedPrfice,
        &$flightDetails,
        &$routesProblemWithBooking,
        &$routesTechnicalProblem
    ): bool {
        $this->logger->notice(__METHOD__);

        foreach ($Roots as $numRoot => $root) {
            if ($checkLimit && !empty($routes) && (time() - $this->requestDateTime) > $this->AccountFields['Timeout']) {
                return false;
            }
            $result = ['connections' => []];

            $segmentsRoot = $this->curl->XPath->query(".//div[contains(@class,'travel-time-detail')]", $root);

            if ($segmentsRoot->length === 0) {
                $this->sendNotification("check segments");

                continue;
            }
            $offerList = $this->curl->XPath->query(".{$xpath}", $root);
            $offers = [];

            foreach ($offerList as $offer) {
                $cabinStr = $this->curl->FindSingleNode("./span[contains(@class,'travel-class')]", $offer);

                $cabinKey = $this->curl->FindSingleNode("./span[contains(@class,'travel-class')]/following-sibling::div[1]//div[contains(@id,'DtlOuterDivRadio')]/@id", $offer, false, '/DtlOuterDivRadio(\d-\d-\w)\s*$/');

                $cabinSeg = $this->curl->FindNodes(".//div[contains(@class,'travel-time-detail')]//p[contains(@class,'cabinName')]/span[contains(@id,'cbnName{$cabinKey}')]", $root);

                if (empty($cabinSeg)) {
                    $this->sendNotification('check cabins // ZM');
                }

                $cabin = array_search($cabinStr, $this->getCabinFields(false));

                if (empty($cabin)) {
                    $cabin = array_search(ucfirst(strtolower($cabinStr)), $this->getCabinFields(false));
                }
                $tickets = $this->curl->FindSingleNode(".//span[@class='message-number-of-seats']", $offer, false,
                    "/(\d+) left/i");

                $paramForOutbound = $this->curl->FindSingleNode(".//input[@name='0']/@value", $offer);
                $paramForInbound = '';
                $eId = $this->curl->FindSingleNode("//*[@id='hdnEIdSB']/@value");
                $stopover = json_encode($this->curl->FindSingleNode("//*[@id='Stopover']/@value") === 'true');
                $hostAirlineCode = $this->curl->FindSingleNode("//*[@id='HostAirlineCode']/@value");
                $BAOnlyRoute = $this->curl->FindSingleNode("//*[@id='BAOnlyRoute']/@value");
                $BANotOperateFullOrPartial = $this->curl->FindSingleNode("//*[@id='BANotOperateFullOrPartial']/@value");
                $hostAirline = $this->curl->FindSingleNode("//*[@id='HostAirline']/@value");

                if (!isset($formToken)) {
                    $formToken = $this->curl->FindSingleNode("//*[@id='formToken']/@value");
                }

                $http2 = clone $this->curl;
                $http2->FilterHTML = false;
                $this->curl->brotherBrowser($http2);

                $headers = [
                    'Accept'           => '*/*',
                    'Origin'           => 'https://www.britishairways.com',
                    'Referer'          => 'https://www.britishairways.com/travel/redeem/execclub/_gf/en_' . $countryCode,
                    'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With' => 'XMLHttpRequest',
                ];
                $x_dtpc = $http2->getCookieByName('dtPC');

                if (!empty($x_dtpc)) {
                    $headers['x-dtpc'] = $x_dtpc;
                }
                $zeroParam = $paramForOutbound . $paramForInbound;
                $payload = "0={$zeroParam}&&eId={$eId}&&Stopover={$stopover}&&HostAirlineCode={$hostAirlineCode}&&BAOnlyRoute={$BAOnlyRoute}&&BANotOperateFullOrPartial={$BANotOperateFullOrPartial}&&HostAirline=$hostAirline&&FormToken={$formToken}";
                $http2->PostURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}/device-all",
                    $payload, $headers);

                if ($msg = $http2->FindSingleNode("//li[contains(.,'Sorry, there’s a problem with booking this journey online')]")) {
                    $this->logger->error($msg);
                    $routesProblemWithBooking[] = $numRoot;
                    $noPrice = true;
                } elseif (empty($http2->FindSingleNode("//span[@class='totalPriceAviosTxt']"))
                    && $http2->FindSingleNode("//div[@name='cacheKeyList']/following-sibling::span[1]") === 'false') {
                    $formToken = $http2->FindSingleNode("//div[@name='totalPriceAvios']/@data-formToken");

                    if (empty($formToken)) {
                        $formToken = $http2->FindPreg("/name=\"totalPriceAvios\" data-formToken=\"(\d+)\">/");
                    }
                    $this->logger->debug('formToken for retry: ' . $formToken);

                    if (!empty($formToken)) {
                        $payload = "0={$zeroParam}&&eId={$eId}&&Stopover={$stopover}&&HostAirlineCode={$hostAirlineCode}&&BAOnlyRoute={$BAOnlyRoute}&&BANotOperateFullOrPartial={$BANotOperateFullOrPartial}&&HostAirline=$hostAirline&&FormToken={$formToken}";
                        sleep(2); //sometimes works
                        $http2->PostURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}/device-all",
                            $payload, $headers);
                    }

                    if ($http2->FindSingleNode("//div[@name='cacheKeyList']/following-sibling::span[1]") === 'false') {
                        $this->logger->error('empty price');
                        $noPrice = true;
                    }
                }

                $price = $http2->FindSingleNode("//span[@class='totalPriceAviosTxt']");

                if (empty($price) && ($msg = $http2->FindSingleNode("//p[starts-with(normalize-space(),'Sorry, there seems to be a technical problem. Please try again in a few minutes, and')]"))) {
                    sleep(2);
                    $payload = "0={$zeroParam}&&eId={$eId}&&Stopover={$stopover}&&HostAirlineCode={$hostAirlineCode}&&BAOnlyRoute={$BAOnlyRoute}&&BANotOperateFullOrPartial={$BANotOperateFullOrPartial}&&HostAirline=$hostAirline&&FormToken={$formToken}";
                    $http2->PostURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}/device-all",
                        $payload, $headers);
                    $price = $http2->FindSingleNode("//span[@class='totalPriceAviosTxt']");

                    if (empty($price)) {
                        $formToken = $http2->FindSingleNode("//div[@name='totalPriceAvios']/@data-formToken");

                        if (empty($formToken)) {
                            $formToken = $http2->FindPreg("/name=\"totalPriceAvios\" data-formToken=\"(\d+)\">/");
                        }

                        if (empty($formToken)) {
                            $this->logger->error($msg);
                            $this->logger->error('empty price');

                            if (!isset($prevToken)) {
                                if (count($routes) > 0) {
                                    return true;
                                }

                                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                            }
                            $formToken = $prevToken;
                            $this->logger->debug("skip journey online");
                            $skippedPrice = true;
                            $routesTechnicalProblem[] = $numRoot;

                            continue;
                        }

                        $msg = $http2->FindSingleNode("//p[starts-with(normalize-space(),'Sorry, there seems to be a technical problem. Please try again in a few minutes, and')]");

                        if ($msg) {
                            $this->logger->error($msg);
                            $this->logger->debug("skip journey online");
                            $skippedPrice = true;
                            $routesTechnicalProblem[] = $numRoot;

                            continue;
                        }

                        if ($http2->FindSingleNode("//div[@name='cacheKeyList']/following-sibling::span[1]") !== 'false') {
                            $this->sendNotification('retry after technical problem on getPrice - helped // ZM');
                        }
                    }
                }
                $price = $http2->FindSingleNode("//span[@class='totalPriceAviosTxt']");

                $prevToken = $formToken;
                $formToken = $http2->FindSingleNode("//div[@name='totalPriceAvios']/@data-formToken");

                if (empty($formToken)) {
                    $formToken = $http2->FindPreg("/name=\"totalPriceAvios\" data-formToken=\"(\d+)\">/");
                }

                if (empty($formToken)) {
                    $this->logger->error('check formToken');

                    if (!empty($prevToken) && (!isset($tryWithPrevToken) || $tryWithPrevToken <= 2)) {
                        $tryWithPrevToken = isset($tryWithPrevToken) ? $tryWithPrevToken + 1 : 0;
                        $formToken = $prevToken;
                        $skippedPrice = true;
                    } elseif (count($routes) > 0 && isset($tryWithPrevToken) && $tryWithPrevToken > 2) {
                        return true;
                    } else {
                        throw new \CheckException('problem with formToken', ACCOUNT_ENGINE_ERROR);
                    }
                }

                if (isset($noPrice)) {
                    $skippedPrice = true;
                    $noPrice = null;
                    $this->logger->debug("skip journey online");

                    continue;
                }

                if (empty($price)) {
                    $this->logger->error('skip offer. price not found');

                    continue;
                }
                $offers[$cabin] = ['price' => $price, 'tickets' => $tickets, 'cabinSeg' => $cabinSeg];
            }
            $stop = -1;
            $price = null;
            $layover = null;
            $totalFlight = null;

            foreach ($segmentsRoot as $i => $r) {
                $stop++;

                $depTime = $this->curl->FindSingleNode("./div[1]/descendant::p[contains(@class,'time')][1]", $r);
                $date = $this->curl->FindSingleNode("./div[1]/descendant::p[contains(@class,'date')][1]", $r);
                $depDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));

                $arrTime = $this->curl->FindSingleNode("./div[2]/descendant::p[contains(@class,'time')][1]", $r);
                $date = $this->curl->FindSingleNode("./div[2]/descendant::p[contains(@class,'date')][1]", $r);
                $arrDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));

                $seg = [
                    'num_stops' => $this->curl->FindSingleNode("./div[3]//p[contains(.,'Stops')]", $r, false, "/Stops:\s*(\d+)/") ?? 0,
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime($depTime, $depDate)),
                        'dateTime' => strtotime($depTime, $depDate),
                        'airport'  => $this->curl->FindSingleNode("./div[1]/div[@class='airport-box']/p[1]", $r),
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime($arrTime, $arrDate)),
                        'dateTime' => strtotime($arrTime, $arrDate),
                        'airport'  => $this->curl->FindSingleNode("./div[2]/div[@class='airport-box']/p[1]", $r),
                    ],
                    'cabin'   => null,
                    'flight'  => [$this->curl->FindSingleNode("./div[3]//a/span[2]", $r)],
                    'airline' => $this->curl->FindSingleNode("./div[3]//a/span[2]", $r, false,
                        '/^([A-Z\d]{2})\s*\d+$/'),
                    'distance' => null,
                ];

                $stop += $seg['num_stops'];
                $result['connections'][] = $seg;
            }

            foreach ($offers as $cabin => $data) {
                $fees = $this->curl->FindPreg("/\d+\s+Avios\s+\+\s+\D+?(\d[\d.,]+)$/", false, $data['price']);
                $currency = $this->curl->FindPreg("/\d+\s+Avios\s+\+\s+(\D+?)\d[\d.,]+$/", false, $data['price']);
                $this->logger->debug("parsed fees: " . $fees);
                $this->logger->debug("parsed currency: " . $currency);

                if ($currency === '¥') {
                    $currency = 'JPY';
                }
                $totalTravel = null;
                $headData = [
                    'distance'  => null,
                    'num_stops' => $stop,
                    'times'     => [
                        'flight'  => $totalTravel,
                        'layover' => $layover,
                    ],
                    'redemptions' => [
                        'miles' => intdiv($this->curl->FindPreg("/(\d+)\s+Avios/", false, $data['price']),
                            $fields['Adults']),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->currency($currency),
                        'taxes'    => null,
                        'fees'     => round(\AwardWallet\Common\Parser\Util\PriceHelper::cost($fees) / $fields['Adults'],
                            2),
                    ],
                ];

                if (empty($headData['payments']['currency'])) {
                    // TODO может стоит ретрай с обновленным formToken
                    $this->logger->error('can\'t determine currency');
                    $this->DebugInfo = 'can\'t determine currency';

                    if (count($routes) > 0) {
                        return true;
                    }

                    throw new \CheckException('Something went wrong', ACCOUNT_ENGINE_ERROR);
                }

                $offerResult = $result;

                foreach ($result['connections'] as $i => $seg) {
                    if (isset($data['cabinSeg'][$i])) {
                        $offerResult['connections'][$i]['cabin'] = array_search($data['cabinSeg'][$i],
                            $this->getCabinFields(false));

                        if (empty($offerResult['connections'][$i]['cabin'])) {
                            $offerResult['connections'][$i]['cabin'] = $cabin;
                        }
                        $offerResult['connections'][$i]['classOfService'] = $this->clearCOS($data['cabinSeg'][$i]);
                    } else {
                        $offerResult['connections'][$i]['cabin'] = $cabin;
                        $offerResult['connections'][$i]['classOfService'] = $this->clearCOS($this->getCabinFields(false)[$cabin]);
                    }
                }
                $res = array_merge($headData, $offerResult);
                $res['tickets'] = $data['tickets'];
                $res['classOfService'] = $this->clearCOS($this->getCabinFields(false)[$cabin]);
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        return false;
    }

    private function clearCOS(string $cos): string
    {
        if (preg_match("/^(.+\w+) (?:class)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }

    private function isBadProxy($http = null): bool
    {
        if (!isset($http)) {
            $http = $this->http;
        }

        return strpos($http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($http->Error, 'Network error 0 -') !== false
            || strpos($http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($http->Error, 'Operation timed out after') !== false
            || $http->FindSingleNode("(
            //h1[contains(., 'This site can’t be reached')]
            | //h1[normalize-space()='Access Denied']
            | //span[contains(text(), 'This site can’t be reached')]
            | //span[contains(text(), 'This page isn’t working')]
            | //text()[contains(.,'An error occurred while processing your request')]/following-sibling::p[starts-with(normalize-space(),'Reference #')]
            | //p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]
            )[1]")
            ;
    }
}
