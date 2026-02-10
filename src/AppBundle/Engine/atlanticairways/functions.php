<?php

class TAccountCheckerAtlanticairways extends TAccountChecker
{
    use SeleniumCheckerHelper;
    protected const REESE84_CACHE_KEY = 'atlanticairways_reese84';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept', 'application/json, text/plain, */*');
        $this->http->setDefaultHeader('Content-Type', 'application/x-www-form-urlencoded');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['access_token'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://auth.atlantic.fo/api/account/get', [
            'authorization' => "Bearer {$this->State['access_token']}",
        ], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        return isset($response->pointBalance, $response->firstName);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.atlanticairways.com/en/s%C3%BAlubonus");

        if (!$this->http->FindSingleNode("//title[contains(text(),'lubonus - Atlantic Airways')]")) {
            return $this->checkErrors();
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://auth.atlantic.fo/token', [
            'grant_type' => 'password',
            'username'   => $this->AccountFields["Login"],
            'password'   => $this->AccountFields["Pass"],
            'client_id'  => 'www.atlantic.fo',
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->State['access_token'] = $response->access_token;

            return true;
        }

        // An error occurred while logging in. Please try again or contact us if the error persists.
        if ($message = $this->http->FindPreg('/:"The user name or password is incorrect."/')) {
            throw new CheckException('An error occurred while logging in. Please try again or contact us if the error persists.', ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, false);

        if (!isset($response->pointBalance, $response->firstName, $response->bonusNumber) && isset($response->access_token)) {
            $this->http->GetURL('https://auth.atlantic.fo/api/account/get', [
                'authorization' => "Bearer {$response->access_token}",
            ]);
            $response = $this->http->JsonLog();
        }

        if (!isset($response->pointBalance, $response->firstName, $response->bonusNumber)) {
            return;
        }
        // You have 1000 points
        $this->SetBalance($response->pointBalance);
        // Welcome home
        $this->SetProperty('Name', beautifulName("{$response->firstName} {$response->lastName}"));
        // Bonus number
        $this->SetProperty('BonusNumber', $response->bonusNumber);
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking number",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.atlanticairways.com/en/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->selenium($this->ConfirmationNumberURL($arFields), $arFields)) {
            $this->sendNotification("atlanticairways - failed to retrieve itinerary");
        }
        $this->http->GetURL("https://eretail.atlantic.fo/DXATC.aspx?callback=jQuery36003823013922581606_1629787469394&LANGUAGE=GB&CultureCode=en-gb&PNRID={$arFields['ConfNo']}&LastName={$arFields['LastName']}&_=" . round(microtime(true) * 1000));
        $form = urldecode($this->http->FindPreg('/jQuery\d+_\d+\("(.+?)"\);/'));
        $this->http->SetBody($form);
        $this->http->ParseForm('eRetailATCForm');
        $this->http->PostForm();
        $data = $this->http->FindPreg('/config : (.+?)\s+, pageEngine/');

        if (!isset($data)) {
            $iframe = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");
            $this->logger->debug($iframe);

            if ($iframe || empty($this->http->Response['body'])) {
                $this->incapsula($iframe);
            }
            $this->http->GetURL("https://eretail.atlantic.fo/DXATC.aspx?callback=jQuery36003823013922581606_1629787469394&LANGUAGE=GB&CultureCode=en-gb&PNRID={$arFields['ConfNo']}&LastName={$arFields['LastName']}&_=" . round(microtime(true) * 1000));
            $form = urldecode($this->http->FindPreg('/jQuery\d+_\d+\("(.+?)"\);/'));
            $this->http->SetBody($form);
            $this->http->ParseForm('eRetailATCForm');
            $this->http->PostForm();
            $data = $this->http->FindPreg('/config : (.+?)\s+, pageEngine/');
            $data = $this->http->JsonLog($data);
        }

        if (isset($data)) {
            $this->ParseFlight($data);
        }

//        if ($this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")) {
//            $this->incapsula();
//        }
        return null;
    }

    public function ParseFlight($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();

        $f->general()->confirmation($data->pageDefinitionConfig->pageData->business->RESERVATION_INFO->locator, 'Reservation number');

        foreach ($data->pageDefinitionConfig->pageData->business->TRAVELLER_REVIEW->travellerListBean->Travellers as $passenger) {
            $f->general()->traveller("{$passenger->IdentityInformation->IDEN_FirstName} {$passenger->IdentityInformation->IDEN_LastName}");
        }

        $f->general()->date2($data->pageDefinitionConfig->pageData->business->RESERVATION_INFO->listTicket[0]->faFh->issueDate);

        foreach ($data->pageDefinitionConfig->pageData->business->FareReview->tripSummary->listItinerary->listItineraryElem as $segment) {
            foreach ($segment->listSegment as $seg) {
                $s = $f->addSegment();
                $s->airline()->name($seg->airline->code);
                $s->airline()->number($seg->flightNumber);

                $s->departure()->name($seg->beginLocation->locationName);
                $s->departure()->code($seg->beginLocation->locationCode);
                $s->departure()->date2($seg->beginDate);
                $s->departure()->terminal($seg->beginTerminal ?? null, false, true);
                $s->arrival()->name($seg->endLocation->locationName);
                $s->arrival()->code($seg->endLocation->locationCode);
                $s->arrival()->date2($seg->endDate);
                $s->arrival()->terminal($seg->endTerminal ?? null, false, true);
                $s->extra()->aircraft($seg->equipment->name);
                $s->extra()->cabin($seg->listCabin[0]->name);
                $s->extra()->bookingCode($seg->listCabin[0]->code);

                if (count($seg->listCabin) > 1) {
                    $this->sendNotification('listCabin > 1 // MI');
                }
            }
        }
    }

    protected function incapsula($incapsula)
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        //$incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            //$this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL("https://book.atlantic.fo{$incapsula}");
            $this->http->RetryCount = 2;
            $this->logger->debug("parse captcha form");
            $action = $this->http->FindPreg("/xhr2.open\(\"POST\", \"([^\"]+)/");
            $dataUrl = $this->http->FindPreg('#"(/_Incapsula_Resource\?SWCNGEEC=.+?)"#');

            if (!$dataUrl || !$action) {
                return false;
            }
            $this->http->NormalizeURL($dataUrl);
            $this->http->GetURL($dataUrl);
            $data = $this->http->JsonLog();

            if (!isset($data->gt, $data->challenge)) {
                return false;
            }
            $request = $this->parseGeettestRuCaptcha($data->gt, $data->challenge, $referer);

            if ($request === false) {
                $this->logger->error("geetest failed = true");

                return false;
            }
            $this->http->RetryCount = 0;
            $this->http->NormalizeURL($action);
            $data = [
                'geetest_challenge' => $request->geetest_challenge,
                'geetest_validate'  => $request->geetest_validate,
                'geetest_seccode'   => $request->geetest_seccode,
            ];
            $headers = [
                "Referer"    => $referer,
            ];
            $this->http->PostURL($action, $data, $headers);
            $this->http->RetryCount = 2;
            $this->http->FilterHTML = true;
        }// if (isset($distil))

        return true;
    }

    private function parseGeettestRuCaptcha($gt, $challenge, $pageurl)
    {
        $this->logger->notice(__METHOD__);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $pageurl,
            "proxy"      => $this->http->GetProxy(),
            'api_server' => 'api.geetest.com',
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha);
        }

        if (empty($request)) {
            $this->logger->error("geetestFailed = true");

            return false;
        }

        return $request;
    }

    private function selenium($url, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $result = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            //$selenium->useCache();
            $resolutions = [
                //[1366, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL($url);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(., 'My booking')]"), 10);
            $btn->click();
            $lastName = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@ng-show,\"== 'change'\")]//input[contains(@placeholder,'Last name')]"), 0);
            $confNo = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@ng-show,\"== 'change'\")]//input[contains(@placeholder,'Reservation number')]"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@ng-show,\"== 'change'\")]//button[contains(text(),'Find reservation')]"), 0);

            if (!$lastName || !$confNo) {
                return false;
            }
            $lastName->sendKeys($arFields['LastName']);
            $confNo->sendKeys($arFields['ConfNo']);
            $btn->click();
            sleep(3);
            //sleep(1);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'reese84') {
                    $result = true;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
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

        return $result;
    }
}
