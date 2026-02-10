<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEtihad extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerEtihadSelenium.php";

            return new TAccountCheckerEtihadSelenium();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.etihadguest.com/en/your-account/transaction-details/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.etihadguest.com/en/login/");
        // Scheduled maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Scheduled maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (!$this->http->ParseForm("eyg-login-form")) {
            return $this->checkForUnavailable();
        }
        $data = [
            "securityContext" => [
                "oAuth"      => null,
                "ipType"     => "MEMBER",
                "macAddress" => null,
                "otp"        => null,
                "ipAddress"  => "46.146.50.29",
            ],
            "requestHeader" => [
                "screenName" => null,
            ],
            "userCtx" => [
                "languageCode" => "en_us",
            ],
            "customerInfo" => [
                "type"            => null,
                "loginCase"       => "etihadguest",
                "userId"          => $this->AccountFields['Login'],
                "password"        => $this->AccountFields['Pass'], // wtf? "GhqNHj57ZCofTC2PBtRKLA=="
                "attemptNumber"   => null,
                "standaloneLogin" => "yes",
            ],
        ];
        $this->http->PostURL("https://www.etihadguest.com/services/dynamic/glc/login/public/v1/authenticate-user", json_encode($data));

        $this->http->SetInputValue('GuestLoginForm_Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('GuestLoginForm_Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('GuestLoginForm_submit', "");
        unset($this->http->Form['dontshow']);
        $this->http->MultiValuedForms = false;

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkForUnavailable()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg('/(Due to high demand, the page you are looking for is taking longer than expected.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/(Etihadairways.com is temporarily unavailable\. We apologise for any inconvenience caused\..*Please try back later.)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//td[contains(@class, 'EYPaddingTop EYPaddingBottom EYPaddingLeftTen')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(),'resolve it, so please try again shortly')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/(Due to the popularity of our promotion, etihad\.com is currently unavailable)/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request
        if ($message = $this->http->FindPreg('/(The server is temporarily unable to service your request)/ims')) {
            throw new CheckException("The server is temporarily unable to service your request.  Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        // The Etihad Airways website is currently unavailable
        if ($message = $this->http->FindSingleNode("//font[contains(text(),'The Etihad Airways website is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are currently upgrading our passenger booking')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are currently upgrading our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Etihad Guest Login is currently unavailable - please try again later
        if ($message = $this->http->FindPreg("/(Etihad Guest Login is currently unavailable\s*-\s*please try again later)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The service is unavailable
        if ($message = $this->http->FindPreg("/(The service is unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable (Server Error)
//        if ($message = $this->http->FindPreg("/(Service (?:Unavailable|Error))/ims"))// bad regexp
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // An error has occured
        if ($this->http->FindSingleNode("//h3[contains(text(), 'An error has occured')]")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkForUnavailable();
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Please check that your username/Etihad Guest number and password are correct
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Please check that your username/Etihad Guest number and password are correct')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Username/Etihad Guest number and password do not match, please check and try again
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Username/Etihad Guest number and password do not match, please check and try again')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Email not yet verified
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Email not yet verified')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Unknown error occured during reCAPTCHA validation, please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unknown error occured during reCAPTCHA validation, please try again.')]")) {
            throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
        }
        // You have exceeded the maximum number of log-in attempts. Your account has been locked.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You have exceeded the maximum number of log-in attempts. Your account has been locked.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        return $this->checkForUnavailable();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.etihadguest.com/en/your-account/transaction-details/");
        // Balance - Total Etihad Guest Miles
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Total Etihad Guest Miles')]/strong"));
        // Etihad Guest number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h3[contains(text(), 'Etihad Guest number:')]/following-sibling::p"));
        // Current Tier level
        $this->SetProperty("TierLevel", $this->http->FindSingleNode("//h3[contains(text(), 'Current tier level:')]/following-sibling::p"));
        // Guest Tier Miles
        $this->SetProperty("TierMiles", $this->http->FindSingleNode("//strong[contains(text(), 'Guest Tier Mile')]", null, true, '/(.+)\s+Guest/ims'));
        // Miles needed to next tier
        $this->SetProperty("TierMilesNeed", $this->http->FindSingleNode("//small[contains(text(), 'tier you need:')]/following-sibling::p[contains(text(), 'Miles')]", null, true, "/([\d\.\,]+)/ims"));
        // Guest Tier Segments
        $this->SetProperty("TierSegments", $this->http->FindSingleNode("//strong[contains(text(), 'Guest Tier Segment')]", null, true, "/([\d\.\,]+)/ims"));
        // Segments needed to next tier
        $this->SetProperty("TierSegmentsNeed", $this->http->FindSingleNode("//small[contains(text(), 'tier you need:')]/following-sibling::p[contains(text(), 'Segments')]", null, true, "/([\d\.\,]+)/ims"));
        // Mileage expiry
        $expMiles = $this->http->XPath->query("//div[@class = 'milesexpirywrapper']//td[@class = 'tableData leftCol']");
        $expDates = $this->http->XPath->query("//div[@class = 'milesexpirywrapper']//td[@class = 'tableData rightCol']");
        $this->logger->debug("Found {$expMiles->length} expiration miles / {$expDates->length} expiration dates");

        if ($expMiles->length == $expDates->length) {
            for ($i = 0; $i < $expMiles->length; $i++) {
                $expireMiles = CleanXMLValue($expMiles->item($i)->nodeValue);
                $expireDate = CleanXMLValue(str_ireplace('After', '', $expDates->item($i)->nodeValue));
                $this->http->Log("Date $expireDate / $expireMiles");

                if (isset($expireDate) && isset($expireMiles) && ($expireMiles != "0")) {
                    $d = strtotime($expireDate);

                    if ($d !== false) {
                        $this->SetExpirationDate($d);
                        $this->SetProperty("MilesToExpire", $expireMiles);

                        break;
                    }// if ($d !== false)
                }// if (isset($expireDate) && isset($expireMiles) && ($expireMiles != "0"))
            }
        }// for ($i = 0; $i < $expirationNodes->length; $i++)

        // Name
        $this->http->GetURL("https://www.etihadguest.com/en/your-account/update-profile/");
        $name = CleanXMLValue(
            $this->http->FindSingleNode("//input[contains(@name, '\$FirstName')]/@value")
            . ' ' . $this->http->FindSingleNode("//input[contains(@name, '\$MiddleName')]/@value")
            . ' ' . $this->http->FindSingleNode("//input[contains(@name, '\$LastName')]/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.etihadguest.com/en";
        $arg["PreloadAsImages"] = true;
        //		$arg["SuccessURL"] = "https://www.etihadguest.com/en/your-account/transaction-details/";
        return $arg;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Activity"    => "Description",
            "Guest Miles" => "Miles",
            "Tier Miles"  => "Info",
            "Bonus Miles" => "Bonus",
        ];
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $data = [
            "securityContext" => [
                "oAuth"      => null,
                "ipType"     => "MEMBER",
                "macAddress" => null,
                "otp"        => null,
                //                "ipAddress" => $this->ip
            ],
            "requestHeader" => [
                "screenName" => null,
            ],
            "userCtx"  => ["languageCode" => "en_us"],
            "transReq" => [
                "offSetValue" => 0,
                "userId"      => "",
                "transType"   => "ALL ACTIVITY",
                "months"      => "",
                "from"        => "2005-01-01",
                "to"          => date('Y-m-d'),
                "noOfTrans"   => "1000",
            ],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
        ];
        $this->http->PostURL("https://www.etihadguest.com/services/dynamic/glc/transaction/private/v1/get-txn-activities", json_encode($data), $headers);

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $response = $this->http->JsonLog(null, true, true);
        $nodes = ArrayVal($response, 'transDetails', []);
        $this->logger->debug("Total " . count($nodes) . " history items were found");

        if (!empty($nodes)) {
            $this->sendNotification("etihad. Need to check history");
        }

        foreach ($nodes as $node) {
            $dateStr = ArrayVal($nodes, 'date');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Activity'] = ArrayVal($nodes, 'dynamicDescription');

            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Activity'])) {
                $result[$startIndex]['Bonus Miles'] = ArrayVal($nodes, 'guestPoints');
            } else {
                $result[$startIndex]['Guest Miles'] = ArrayVal($nodes, 'guestPoints');
            }
            $result[$startIndex]['Tier Miles'] = ArrayVal($nodes, 'tierPoints');
            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking reference or ticket number",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 64,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            /*"Email"  => [
                "Caption"  => "E-mail",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField("Email"),
                "Required" => true,
            ],*/
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.etihad.com/en-us/manage";
    }

    public function notify($provider, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->http->setHttp2(true);
        $this->sendNotification("{$provider} - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        //$this->setProxyBrightData();
        //$this->http->SetProxy($this->proxyDOP());
        $this->setProxyGoProxies();
        $this->selenium($arFields);
//        $this->http->RetryCount = 0;
//        $this->http->GetURL($this->ConfirmationNumberURL($arFields), null, 30);
//        $this->http->RetryCount = 2;

//        if (!$this->http->ParseForm('frmManageMyBooking')) {
//            $this->sendNotification('failed to retrieve itinerary by conf #');
//            return null;
//        }
        $this->http->GetURL("https://book.etihad.com/en/self-service-hub.html?last_name={$arFields['LastName']}&pnr={$arFields['ConfNo']}");
        $paramKey = $this->http->FindPreg('/#Encrypted_Search_Parameters=(.+)/', false, $this->http->currentUrl());
        //$this->sendStaticSensorData();

        $headers = [
            'Accept'  => 'application/json, text/plain, */*',
            'Referer' => 'https://book.etihad.com/en/self-service-hub.html',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://book.etihad.com/services/v1/processEncryptedPNRData?Encrypted_Search_Parameters=$paramKey", $headers, 20);
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] == 403) {
            $this->sendNotification("Failed to retrieve itinerary by conf # // MI");

            throw new CheckRetryNeededException(3, 0);
        }

        $data = $this->http->JsonLog(null, 2);

        if (isset($data->messages->message[0]->text) && $data->messages->message[0]->text == 'e-Ticket/EMD exists or active but itinerary missing from the booking') {
            return "We're sorry, your flight has been cancelled. Please call us for help.";
        }

        if (isset($data->messages->message[0]->text) && $data->messages->message[0]->text == 'Name not matching PNR.') {
            return "We couldn't find your booking. To try again, please enter your ticket number.";
        }

        $this->parseItineraryFlight($data);

        return null;
    }

    public function ArrayVal($array, $indices, $default = null)
    {
        $res = $array;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function xpathQuery($query, $parent = null)
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    public function parseItineraryFlight($item)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();

        if (!isset($item->bookingDetails->bookingReference)) {
            return false;
        }
        $this->logger->info('Parse Itinerary #' . $item->bookingDetails->bookingReference, ['Header' => 3]);
        $f->general()->confirmation($item->bookingDetails->bookingReference);

        foreach ($item->flightDetails as $detail) {
            foreach ($detail->paxDetails as $paxDetail) {
                $f->general()->traveller(beautifulName("$paxDetail->firstName $paxDetail->lastName"));

                if (isset($paxDetail->ticketNumber)) {
                    $f->issued()->ticket($paxDetail->ticketNumber, false);
                }

                if (isset($paxDetail->loyaltyNumber)) {
                    $f->program()->account($paxDetail->loyaltyNumber, false);
                }
            }

            $s = $f->addSegment();
            $s->airline()->name($detail->operatingAirlineCode);
            $s->airline()->number($detail->flightNumber);

            $s->departure()->name($detail->originAirportName);
            $s->departure()->code($detail->originAirportCode);
            $s->departure()->date2("$detail->flightStartDate $detail->flightStartTime");
            $s->departure()->terminal($detail->departureTerminalName, false, true);
            $s->arrival()->name($detail->destinationAirportName);
            $s->arrival()->code($detail->destinationAirportCode);
            $s->arrival()->date2("$detail->flightEndDate $detail->flightEndTime");
            $s->arrival()->terminal($detail->arrivalTerminalName, false, true);
            $s->extra()->aircraft($detail->aircraft);

            if (isset($paxDetail->flightClass)) {
                $s->extra()->cabin($detail->flightClass);
            }
            $hours = floor($detail->flightDuration / 60);
            $minutes = ($detail->flightDuration % 60);
            $s->extra()->duration(sprintf('%01dh %02dm', $hours, $minutes));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'wrapper']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function sendStaticSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#") ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            //return $this->checkErrors();
        }
        $this->http->NormalizeURL($sensorDataUrl);

        $sensorData = [
            //            null,
            "7a74G7m23Vrp0o5c9200201.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36,uaend,12147,20030107,ru,Gecko,5,0,0,0,403748,7217520,1920,1050,1920,1080,1920,352,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.356888901178,820468608760,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,306,-1,-1,-1;-1,2,-94,-109,0,302,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://book.etihad.com/en/self-service-hub.html#Encrypted_Search_Parameters=4GJpHl8%2BzXH9z1d8XAY2gUdNI5wRbF4cqXCwboICe%2FnjW8Bm3oM2gRDO9PkJHZHpMgH%2BhRCZeTaSz0hQZX5BpYYMknSnwxhY6BJvCxrEGKQYa2PkMMz2KV20hR0fwTV6M631fJ%2By23NOM4dzr4dKY2CA%2FbY%3D-1,2,-94,-115,1,32,32,306,302,0,608,579,0,1640937217520,22,17554,0,0,2925,0,0,580,608,0,25725DC6087EF0F50035382207FF4CF6~-1~YAAQZ/1zPp1PLdp9AQAAnd56DwfyaBKl3H1BqZWItdiExMP5AK1u1h5d+yd70WRaWHUeikUsFL6d1FSd1Kgy5tY5nYd3ygFN/UQE3+mJkrWRcOY6IXzh2dcY2UrcF2EkBL2/BqBjOEeKwPSM8NOXC3NCxbLdfpoKIE+1zk1MKAF+JaMhaL4W+52GRu/D1dPwasKjgGnP89iBvgtOIitBg87RbCMESQXIPvxZbEiXp8ObOQq1mo36Rs6ZbrJiDmPvp58IJgfB2OjtJFD0G5avmW0EXLG4PISelrFpEaS+cf/+SzQzvPNa4OHGr4OgZWDIsYla4j3byzBmp5xuTXK9oIb7hUlF04WISo4Gcs3wQY25eAL63i98bS7CU6hrnDv+jPtC8QwHPV6b~-1~||1-NNrSsMYeDA-1-10-1000-2||~-1,37542,156,-1779642376,30261689,PiZtE,76913,68,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,20,60,40,40,60,20,20,20,0,0,340,280,120,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,21652551-1,2,-94,-118,106938-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;15;10;0",
        ];

        $secondSensorData = [
            //            null,
            "7a74G7m23Vrp0o5c9200201.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36,uaend,12147,20030107,ru,Gecko,5,0,0,0,403748,7217520,1920,1050,1920,1080,1920,352,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.706705798353,820468608760,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,0,1,782,608,293;1,1,788,621,303;2,1,789,627,306;3,1,790,632,311;4,1,828,657,338;5,1,830,658,338;6,1,842,660,340;7,1,865,669,350;-1,2,-94,-117,-1,2,-94,-111,0,306,-1,-1,-1;-1,2,-94,-109,0,302,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://book.etihad.com/en/self-service-hub.html#Encrypted_Search_Parameters=4GJpHl8%2BzXH9z1d8XAY2gUdNI5wRbF4cqXCwboICe%2FnjW8Bm3oM2gRDO9PkJHZHpMgH%2BhRCZeTaSz0hQZX5BpYYMknSnwxhY6BJvCxrEGKQYa2PkMMz2KV20hR0fwTV6M631fJ%2By23NOM4dzr4dKY2CA%2FbY%3D-1,2,-94,-115,1,14293,32,306,302,0,14869,1062,0,1640937217520,22,17554,0,8,2925,0,0,1070,7122,0,25725DC6087EF0F50035382207FF4CF6~0~YAAQZ/1zPqRPLdp9AQAAquB6DwecSiNXLqJtCRtTHyDJT/8mU17jq7juz07PODn8hSePsOmTq//y+7rXGOCcAcqdcNaTSqJ8VIsSOK8fFMGJf/vY4p9KzhQvU5Xp700v/eeHzl4Rx7Lf9j65QwznukA2swLG7U8P9osxShURbA+p65dROqdZxuKkABEUwH/pryPKht+pitjakphop6rKlRgQmVc3Kh9DtjeYyexS/N5EXXaZlp2qyJal8EL+LADBCH1d+YfgW1b77C24AJwWvwVndOD0mouy9TdBXa8qJwxDjmYatTouw0XpzjvX+7LEYqCCZPAZ03kD8Nr+Hz7KXiBTilwfr1J7VhCGgBXuTaSXEtbeQGii+6JMQlcPIP8zKeWmYEa3VZjPbwA2F33jpw839BQCGfg=~-1~||1-NNrSsMYeDA-1-10-1000-2||~-1,40280,156,-1779642376,30261689,PiZtE,17631,83,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,20,20,60,40,40,60,20,20,20,0,0,340,280,120,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.bbed9a97f88e6,0.43c910a6d06bf,0.e703a7567ecab,0.8c14807416d17,0.6da9d648d05b7,0.a8cda7c47a1c7,0.4ee7af75ef6ce,0.b8d14e9c00684,0.37ce5232443d8,0.ba21284a24de9;1,0,1,1,0,2,3,1,2,3;0,0,6,4,0,7,22,6,1,18;25725DC6087EF0F50035382207FF4CF6,1640937217520,NNrSsMYeDA,25725DC6087EF0F50035382207FF4CF61640937217520NNrSsMYeDA,1,1,0.bbed9a97f88e6,25725DC6087EF0F50035382207FF4CF61640937217520NNrSsMYeDA10.bbed9a97f88e6,140,86,101,93,55,105,38,126,165,228,198,176,0,99,58,144,71,129,35,61,98,147,210,57,59,46,148,90,203,53,9,255,550,0,1640937218582;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,21652551-1,2,-94,-118,148460-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;12;10;0",
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->RetryCount = 0;
        $referer = $this->http->currentUrl();

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $headers = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->RetryCount = 2;
        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);
        sleep(1);

        return true;
    }

    private function selenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $responseData = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
            //$selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            //$selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.etihad.com/en-us/manage");
            $confNo = $selenium->waitForElement(WebDriverBy::id("mybBookingReference"), 10);
            $lastName = $selenium->waitForElement(WebDriverBy::id("mybLastName"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id("mybFormSubmit"), 0);

            if ($confNo && $lastName && $btn) {
                $confNo->sendKeys($arFields['ConfNo']);
                $lastName->sendKeys($arFields['LastName']);
                /*$this->logger->info("confirm dialog loaded");
                $script = '
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/\/ACWebOnline\/ACRetrieve\/bkgd/g.exec( url )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
                ';
                $this->logger->debug("[run script]");
                $this->logger->debug($script, ['pre' => true]);
                $selenium->driver->executeScript($script);
                $btn->click();*/
            }

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

//            sleep(6);
//            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $responseData;
    }
}
