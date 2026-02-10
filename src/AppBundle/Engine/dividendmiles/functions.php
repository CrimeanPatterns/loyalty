<?php

class TAccountCheckerDividendmiles extends TAccountChecker
{
    protected $collectedHistory = false;

    private $expiration;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->TimeLimit = 600;

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->setExternalProxy();
//            $proxy = $this->http->getLiveProxy("https://membership.usairways.com/Login.aspx");
//            $this->http->SetProxy($proxy);
        }
    }

    public function LoadLoginForm()
    {
        // hacker's gap
        if (strstr($this->AccountFields['Login'], "OnerRoR = prompt(5)>")) {
            throw new CheckException("We couldn’t log you in with the username or password you provided. Please try again.", ACCOUNT_PROVIDER_ERROR);
        }
        // for field 'Last Name'
        if (empty($this->AccountFields['Login2'])) {
            throw new CheckException("To update this US Airways (Dividend Miles) account you need to fill in the 'Last Name' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://membership.usairways.com/Login.aspx");
        //# Tests on the site
        if ($this->http->FindSingleNode("//a[contains(text(), 'Continue to USAirways.com')]/@href")) {
            $this->http->GetURL($this->http->FindSingleNode("//a[contains(text(), 'Continue to USAirways.com')]/@href"));
            $this->http->GetURL("https://membership.usairways.com/Login.aspx");
        }

        // retry
        if (empty($this->http->Response['body'])) {
            $this->http->setExternalProxy();

            throw new CheckRetryNeededException(3, 15, self::PROVIDER_ERROR_MSG);
        }

        //# Check Errors
        $this->checkErrors();
        // PublicKeyToken
        if (!$publicKey = $this->http->FindPreg("/PublicKeyToken=([^\"]+)\"/ims")) {
            $this->http->Log("public key not found");

            return false;
        }
        // set cookies
        $this->http->setCookie("s_sq", urldecode("usaircom%3D%2526pid%253DLogin.aspx%2526pidt%253D1%2526oid%253Djavascript%25253AWebForm_DoPostBackWithOptions%252528new%25252520WebForm_PostBackOptions%252528%252522ctl00%252524phMain%252524loginModule%252524ctl%2526ot%253DA"), ".usairways.com");
        $this->http->setCookie("s_cc", "true", ".usairways.com");

        $this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$UserName'] = $this->AccountFields['Login'];
        $this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$txtLastName'] = $this->AccountFields['Login2'];
        $this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$Password'] = $this->AccountFields['Pass'];
        $this->http->Form['ctl00$phMain$loginSelector$loginPop_updatepanel$loginPanel$LoginType'] = 'rbMember';
        $this->http->Form['ctl00$phMain$dmUpdatePrompt$dmUpdatePopUp_updatepanel$popOverPanelControl$rdoAcctUpdateOptions'] = '1';
        $this->http->Form['__EVENTTARGET'] = 'ctl00$phMain$loginModule$ctl00$loginForm$Login';
        $this->http->Form[urldecode("ctl00_MasterScriptManager_HiddenField")] = urldecode("%3B%3BAjaxControlToolkit%2C+Version%3D3.0.20820.12087%2C+Culture%3Dneutral%2C+PublicKeyToken%3D") . $publicKey;
        $this->http->FormURL = 'https://membership.usairways.com/Login.aspx';

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->FindPreg("/due to system maintenance the user-profile section of our site is unavailable at this time/ims")) {
            throw new CheckException("We're sorry, due to system maintenance the user-profile section of our site is unavailable at this time", ACCOUNT_PROVIDER_ERROR);
        }
        // We're working on our site, and account access is unavailable.
        if ($message = $this->http->FindSingleNode('//span[contains(text(), "We\'re working on our site, and account access is unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Application Unavailable
        if ($this->http->FindSingleNode("//span[contains(text(), 'Server Application Unavailable')]")
            //# HTTP Error 404
            || $this->http->FindSingleNode('//h2[contains(text(),"HTTP Error 404")]')
            //# Server Error in '/' Application
            || $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            // The proxy server did not receive a timely response from the upstream server.
            || $this->http->FindPreg('/The proxy server did not receive a timely response from the upstream server\./ims')
            // We’re sorry. We couldn’t find the page you’re looking for. It may have been removed or changed.
            || (empty($this->http->Response['code']) && empty($this->http->Response['body']))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // retry
        if (empty($this->http->Response['body'])) {
            throw new CheckRetryNeededException(3, 15, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['CookieURL'] = "https://membership.usairways.com/Login.aspx";
        $arg['SuccessURL'] = "https://membership.usairways.com/Manage/AccountSummary.aspx";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        // refs #4034
        //# Forfeiture date
        $this->expiration = $this->http->FindSingleNode("//div[contains(text(), 'Mileage deletion date')]/h2");

        if (!isset($this->expiration)) {
            $this->expiration = $this->http->FindSingleNode("//div[contains(text(), 'Forfeiture date')]/h2");
        }

        if (!isset($this->expiration)) {
            $this->expiration = $this->http->FindPreg("/Mileage\s*deletion\s*date\s*<.+>\s*<.+>\s*([^<]+)/ims");
        }
        $this->http->Log("Forfeiture date $this->expiration - " . var_export(strtotime($this->expiration), true), true);

        if ($this->expiration = strtotime($this->expiration)) {
            $this->SetExpirationDate($this->expiration);
        }
        //# Miles pending forfeiture
        $forfeiture = $this->http->FindSingleNode("//div[a[contains(text(), 'Forfeited miles')]]/h2");

        if (!isset($forfeiture)) {
            $forfeiture = $this->http->FindSingleNode("//div[a[contains(text(), 'Miles pending forfeiture')]]/h2");
        }

        if (!isset($forfeiture)) {
            $forfeiture = $this->http->FindPreg("/Forfeited\s*miles\s*<.+>\s*<.+>\s*([^<]+)/ims");
        }
        $this->SetProperty("Forfeiture", $forfeiture);

        if ($error = $this->http->FindSingleNode('//table[@class="tblsserror"]')) {
            if (preg_match('/your account has been locked/ims', $error)) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($error = $this->http->FindSingleNode('//table[@class="tblsserror"]'))

        if ($message = $this->http->FindSingleNode("//span[@id='ctl00_phMain_bodySection_ctl00_Label2']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // skip TSA
        if ($this->http->FindPreg('/id="ctl00_phMain_dmUpdatePrompt_dmUpdatePopUp_updatepanel_popOverPanelControl_lblLoginAlertText"/ims')) {
            $this->http->Log(">>> skip TSA");

            if (!$this->http->ParseForm("aspnetForm")) {
                return false;
            }
            $publicKey = $this->http->FindPreg("/PublicKeyToken=([^\"]+)\"/ims");

            if (!$publicKey) {
                $this->http->Log("public key2 not found");

                return false;
            }
            $this->http->Form['__EVENTTARGET'] = 'ctl00%24phMain%24dmUpdatePrompt%24dmUpdatePopUp_updatepanel%24popOverPanelControl%24frmbtnContinue';
            $this->http->Form[urldecode("ctl00_MasterScriptManager_HiddenField")] = urldecode("%3B%3BAjaxControlToolkit%2C+Version%3D3.0.20820.12087%2C+Culture%3Dneutral%2C+PublicKeyToken%3D") . $publicKey;
            $this->http->Form[urldecode("ctl00%24MasterScriptManager")] = urldecode("ctl00%24MasterScriptManager%7Cctl00%24phMain%24dmUpdatePrompt%24dmUpdatePopUp_updatepanel%24popOverPanelControl%24frmbtnContinue");
            $this->http->Form['__ASYNCPOST'] = 'true';
            $this->http->Form[urldecode("ctl00%24phMain%24loginSelector%24loginPop_updatepanel%24loginPanel%24LoginType")] = "rbMember";
            unset($this->http->Form['ctl00$siteSearch$imageButtonSearch']);
            unset($this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$RememberMe']);
            unset($this->http->Form['ctl00$dummyAirportHoverLink$btnDummy']);
            unset($this->http->Form['ctl00$phMain$dmUpdatePrompt$buttonDummy']);
            $this->http->Form[urldecode("ctl00%24phMain%24dmUpdatePrompt%24dmUpdatePopUp_updatepanel%24popOverPanelControl%24rdoAcctUpdateOptions")] = "3";
            $this->http->FormURL = "https://membership.usairways.com/Login.aspx";
            $this->http->PostForm();
            $this->http->GetURL("https://membership.usairways.com/Manage/AccountSummary.aspx");
        }// if ($this->http->FindPreg('/name="ctl00_phMain_dmUpdatePrompt_dmUpdatePopUp_updatepanel_popOverPanelControl_lblLoginAlertText"\s+value="3"/ims'))

        if ($this->http->FindPreg('/Miles in your account have been forfeited due to inactivity/ims')) {
            //$this->ErrorMessage = 'Action Required. You have expired miles on your account please login to US Airways and respond to a popup message that you will see after your login.';
            //$this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            return true;
        }

        if ($url = $this->http->FindPreg("/<script>document\.location\.href='([^']+)';<\/script>/ims")) {
            //$this->http->Log("GET location:".$url);
            $this->http->GetURL($url);
        }
        // "Your account"
        if ($this->http->FindSingleNode('//span[@id="ctl00_phMain_yourAccountModule_TitleMessage"]')) {
            return true;
        }

        if ($error = $this->http->FindSingleNode('//table[@class="tblsserror"]')) {
            if (preg_match('/your account has been locked/i', $error)) {
                throw new CheckException($error, ACCOUNT_LOCKOUT);
            } else {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($error = $this->http->FindSingleNode('//table[@class="tblsserror"]'))

        if ($message = $this->http->FindSingleNode("//span[@id='ctl00_phMain_bodySection_ctl00_Label2']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($error = $this->http->FindPreg("/\<span id\=\"ctl00_ErrorDisplay_MessageText\"\>(.+)<\/span>/ims")) {
            $this->ErrorCode = ACCOUNT_PROVIDER_ERROR;
            $this->ErrorMessage = strip_tags($error); // message too long (first,last /span)

            if (strstr($this->ErrorMessage, "Your account is locked") !== false) {
                $this->ErrorCode = ACCOUNT_LOCKOUT;
            }

            return false;
        }

        if ($message = $this->http->FindPreg("/(Enter either your username or Dividend Miles number\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/(We need a little more information\.\s*Please review the information below and fill in any blank fields in order to update your account\.)/ims")) {
            throw new CheckException("US Airways (Dividend Miles) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!strstr($this->http->currentUrl(), 'https://membership.usairways.com/Manage/AccountSummary.aspx')) {
            $this->http->getURL('https://membership.usairways.com/Manage/AccountSummary.aspx');
        }

        // Balance
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'ctl00_phMain_yourAccountModule_ctl00_dmAccountBalance']"));
        // AAdvantage number
        $this->SetProperty("Number", $this->http->FindSingleNode("//td[contains(text(), 'AAdvantage number')]/following-sibling::td[1]"));
        // Status
        $status = $this->http->FindSingleNode("//td[contains(text(), 'Elite status')]/following-sibling::td[1]");
        // Elite qualifying miles
        $this->SetProperty("QualifyingMiles", $this->http->FindSingleNode("//td[contains(text(), 'Elite qualifying miles')]/following-sibling::td[1]", null, true, "/(.*)\s*mile/ims"));
        // Elite qualifying segments
        $this->SetProperty("QualifyingSegments", $this->http->FindSingleNode("//td[contains(text(), 'Elite qualifying miles')]/following-sibling::td[1]", null, true, "/and\s*(.*)\s*segment/ims"));

        $this->http->GetURL("https://membership.usairways.com/Manage/YourMiles.aspx");
        //# Status
        if (!isset($status)) {
            $status = $this->http->FindSingleNode("//td[a[contains(text(), 'Preferred status')]]/following-sibling::td[1]");
        }
        $this->SetProperty("Status", $status);
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@id, 'lblName')]")));

        // Expiration Date  // refs #7501
        if ($this->http->ParseForm("aspnetForm")) {
            $this->http->FormURL = "https://membership.usairways.com/Manage/YourMiles.aspx";
            $this->http->Form['ctl00$MasterScriptManager'] = 'ctl00$phMain$yourMileModule$ctl00$DividendMilesDetailPanel|ctl00$phMain$yourMileModule$ctl00$btnSubmit';
            $this->http->Form["ctl00_MasterScriptManager_HiddenField"] = urldecode('%3B%3BAjaxControlToolkit%2C%20Version%3D3.0.20820.12087%2C%20Culture%3Dneutral%2C%20PublicKeyToken%3D28f01b0e84b6d53e%3Aen-US%3Ae73c8192-d501-4fd7-a3b9-5354885de87b%3A91bd373d%3B');
            $this->http->Form["__EVENTTARGET"] = 'ctl00$phMain$yourMileModule$ctl00$btnSubmit';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$startDate$SelectedDate'] = date("m/d/Y", strtotime("-2 year"));
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$endDate$SelectedDate'] = date("m/d/Y");

            foreach ($this->http->Form as $sKey => $sValue) {
                if (strpos($sKey, '$chk') > 0) {
                    $this->http->Form[$sKey] = "on";
                }
            }
            unset($this->http->Form['ctl00$siteSearch$imageButtonSearch']);
            unset($this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$RememberMe']);
            unset($this->http->Form['ctl00$dummyAirportHoverLink$btnDummy']);
            unset($this->http->Form['ctl00$dummyAirportHoverLink$btnDummy']);
            unset($this->http->Form['ctl00$dummyAirportHoverLink$btnDummy']);
            $this->http->Form['__ASYNCPOST'] = 'true';

            // set cookies
            $this->http->setCookie("s_ria", urldecode("flash%2011%7Csilverlight%20not%20detected"), ".usairways.com");
            $this->http->setCookie("s_sq", urldecode("%5B%5BB%5D%5D"), ".usairways.com");

            $this->http->PostForm();
            $expires = $this->http->XPath->query("//table[@class = 'viewmiles']/tr[not(@class = 'oddrow') and td[6]]");
            $this->http->Log("Total nodes found " . $expires->length);

            for ($i = $expires->length - 1; $i >= 0; $i--) {
                $d = $this->http->FindSingleNode("td[1]", $expires->item($i));
                $description = strtoupper($this->http->FindSingleNode("td[3]", $expires->item($i)));
                $miles = $this->http->FindSingleNode("td[4]", $expires->item($i));
//                $this->http->Log("Date - ".var_export($d, true), true);
                if ((strtotime($d) !== false) && (!isset($this->expiration) || $this->expiration === false)
                    && (!empty($miles) || $description == 'ACCOUNT BALANCE PRESERVED')
                    && (!isset($exp) || (strtotime($exp) < strtotime($d)))) {
                    $this->http->Log("Description: {$description}");
                    $exp = $d;
                    $this->SetProperty("LastActivity", $exp);
                    $d = strtotime("+18 month", strtotime($exp));
                    $this->SetExpirationDate($d);
                }
            }// for ($i = $expires->length-1; $i >= 0; $i--)
        }// if ($this->http->ParseForm("aspnetForm"))
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation or ticket #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "Depart" => [
                "Type"     => "date",
                "Value"    => date(DATE_FORMAT),
                "Required" => true,
            ],
        ];
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://membership.usairways.com/Manage/AccountSummary.aspx");

        if ($this->http->FindSingleNode("//tr[th/h4[contains(text(), 'Travel Dates')] and th/h4[contains(text(), 'Confirmation code')]]/following::tr[1]/td[contains(text(), 'No flights')]")) {
            return $this->noItinerariesArr();
        }
        $xpath = $this->http->XPath;
        $nodes = $xpath->query("//a[contains(@href, 'http://reservations.usairways.com/Default.aspx?')]");
        $links = [];

        foreach ($nodes as $node) {
            $link = $this->http->FindSingleNode("@href", $node);
            $confNo = $this->http->FindSingleNode("parent::td/parent::tr/td[3]", $node);
            $date = $this->http->FindSingleNode("parent::td/parent::tr/td[1]", $node);

            if (!empty($link)) {
                $links[] = [
                    'link'   => $link,
                    'confNo' => $confNo,
                    'date'   => $date,
                ];
            }
        }
        $result = [];
        //$this->http->Log("Found ".$nodes->length." Itineraries");
        foreach ($links as $link) {
            // TODO: refs #10407
            $this->ArchiveLogs = true;
            $this->http->GetURL($link['link']);

            if ($this->http->Response['code'] == 0) {
                $this->http->GetURL($link['link']);
            }
            $itin = $this->CheckItineraryDividendmiles($link['confNo'], $link['date']);

            if (($itin === null) || !is_array($itin) || (count($itin) == 0)) {
                continue;
            } else {
                $result[] = $itin;
            }
        }
        //echo "<pre>".htmlspecialchars(print_r($result, true))."</pre>";
        //die();
        return $result;
    }

    public function CheckItineraryDividendmiles($confNo = null, $datesStr = null)
    {
        if (preg_match("/Your flight schedule for confirmation code \w+ has changed/ims", $this->http->Response["body"])) {
            return "Your flight schedule has changed. Login to US Aiways website to confirm changes";
        }

        if (preg_match("/You have entered an invalid confirmation code or departure date/ims", $this->http->Response["body"])) {
            return "You have entered an invalid confirmation code or departure date/ims";
        }

        $this->http->Log('Parsing reservation ' . $confNo . ' (' . $datesStr . ')');

        if (preg_match('#\d{4}#i', $datesStr, $m)) {
            $year = $m[0];
        } else {
            $this->http->Log('Failed to parser trip year', LOG_LEVEL_ERROR);
            $year = null;
        }

        //		$scheduleChangedPage = (bool)$this->http->FindSingleNode('//span[@id="ctl00_phMain_scheduleChangeModule_TitleMessage"]');
        $result = [];
        $xpath = $this->http->XPath;

        // ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode('//h2[@class="gold"]|//h3[@class="gold"]', null, true, '/Confirmation code:\s*(.*)/');

        if (empty($result["RecordLocator"])) {
            $result["RecordLocator"] = $this->http->FindSingleNode("(//span[contains(@class, 'h2')])[1]");
        }

        if (empty($result["RecordLocator"]) && isset($confNo)) {
            $result["RecordLocator"] = $confNo;
        }
        // ReservationDate
        $nodes = $xpath->query("//div[div[h4[contains(text(),'Original date issued')]]]/div/span");

        if ($nodes->length == 0) {
            $nodes = $xpath->query("//div/div/h3[contains(text(),'Original date issued')]/span");
        }

        if ($nodes->length > 0) {
            $resDate = CleanXMLValue($nodes->item(0)->nodeValue);
        }

        if (isset($resDate) and strtotime($resDate) !== false) {
            $result['ReservationDate'] = strtotime($resDate);
        }
        // TotalCharge
        $result['TotalCharge'] = $this->http->FindSingleNode('//h2[contains(text(), "Total")]/span', null, true, '/(\d+.\d+|\d+)/');
        // Currency
        if (preg_match('/([\$]{1})/', $this->http->FindSingleNode('//h2[contains(text(), "Total")]/span')) && !empty($result['TotalCharge'])) {
            $result['Currency'] = 'USD';
        } else {
            unset($result['TotalCharge']);
        }
        // Status
        $result['Status'] = $this->http->FindSingleNode('//span[@id="ctl00_phMain_TripDetailsModule_ctl00_TripDetailsControl1_SliceViewRepeater_ctl00_DepartReturnSubHeader_lblStatus" or @id="ctl00_phMain_scheduleChangeModule_ctl00_tripDetails_SliceViewRepeater_ctl00_DepartReturnSubHeader_lblStatus"]');

        if (empty($result['Status'])) {
            unset($result['Status']);
        }

        if (isset($result['Status']) and in_array($result['Status'], ['Canceled', 'Refunded'])) {
            $result['Cancelled'] = true;
        }
        // AccountNumber
        $accounts = $this->http->FindNodes('//span[contains(@id, "blFrequentFlyerAirlineCombo")]');

        for ($a = 0; $a < count($accounts); $a++) {
            if (preg_match('/(\d+)/', $accounts[$a], $temp)) {
                $accountsArr[] = $temp[1];
            }
        }

        if (isset($accountsArr[0])) {
            $result['AccountNumbers'] = implode(', ', $accountsArr);
        }

        $nodes = $xpath->query("//span[contains(@id, 'lblPassengerName')]");

        if ($nodes->length > 0) {
            $paxes = [];

            for ($n = 0; $n < $nodes->length; $n++) {
                $paxes[] = CleanXMLValue($nodes->item($n)->nodeValue);
            }
            $result['Passengers'] = implode(", ", $paxes);
        }

        // depart & return 2node
        $rows = $xpath->query("//div[contains(@id, 'DepartReturnSubHeader_tdCityDateHeader')]");
        $this->http->Log("Side count: " . $rows->length);
        $segments = [];
        $flightsChanged = false;

        for ($n = 0; $n < $rows->length; $n++) {
            // segments
            $info = $xpath->query("following-sibling::div[contains(@id, 'jqryZoopTravelDetails')][1]", $rows->item($n));
            $segmentsCount = $xpath->query("following-sibling::div[contains(@id, 'jqryZoopTravelDetails')][1]//div[contains(@class, 'flightNumber')]", $rows->item($n));

            if ($segmentsCount->length == 0) {
                $this->http->Log(">>> Attention: " . $this->http->FindSingleNode("//h4[contains(text(), 'Your flights have changed')]"));
                $segmentsCount = $xpath->query("following::div[contains(@id, 'jqryZoopTravelDetails')][1]//tr[td[contains(@id, 'fltnumColumn')]]", $rows->item($n));
                $flightsChanged = true;
            }
            // base date
            if ($baseDate = $this->http->FindSingleNode('.//span[contains(@id, "DepartReturnSubHeader_departDateValueLabel")]', $rows->item($n))) {
                $baseDate = strtotime($baseDate);
                $this->http->Log("base date: " . date(DATE_FORMAT, $baseDate));
            } else {
                $baseDate = null;
            }
            // segment Status
            $segmentStatus = $this->http->FindSingleNode('.//span[contains(text(), "Status")]/following-sibling::span[1]', $rows->item($n));
            $this->http->Log("Status: " . $segmentStatus);
            $this->http->Log("Segments for this side count: " . $segmentsCount->length);
            $lastDate = null;

            for ($k = 0; $k < $segmentsCount->length; $k++) {
                $segment = [];
                $this->http->Log("Last date: {$lastDate} ");

                if (!empty($segmentStatus)) {
                    $segment['Status'] = $segmentStatus;
                }
                // flights have changed
                if ($flightsChanged) {
                    $row = $segmentsCount->item($k);
                } else {
                    $row = $info->item(0);
                }
                $segment['FlightNumber'] = $this->http->FindSingleNode(".//div[contains(@class, 'flightNumber')]", $row, true, '/\#\s*([^<]+)/ims', $k);

                if (!isset($segment['FlightNumber'])) {
                    $segment['FlightNumber'] = $this->http->FindSingleNode("td[contains(@id, 'fltnumColumn')]//td[contains(@id, 'tdFlightNbr')]", $row, true, null);
                }
                // airline name
                $airlineName = $this->http->FindSingleNode(".//div[contains(@class, 'flightNumber')]/following-sibling::div[2]", $row, true, "/Operated by (.*)$/ims", $k);

                if (!isset($airlineName)) {
                    $airlineName = $this->http->FindSingleNode("td[contains(@id, 'fltnumColumn')]", $row, true, "/Operated by (.*)$/ims");
                }
                $segment["AirlineName"] = preg_replace("/\sdba.*$/ims", "", $airlineName);
                // DepCode
                $segment["DepCode"] = $this->http->FindSingleNode(".//div[contains(@class, 'departCode')]", $row, true, null, $k);

                if (!isset($segment['DepCode'])) {
                    $segment["DepCode"] = $this->http->FindSingleNode("td[contains(@id, 'departColumn')]/span", $row, true, null);
                }
                // DepName
                $segment["DepName"] = $this->http->FindSingleNode(".//div[contains(@class, 'departCode')]//@onmouseover", $row, true, "/new AirportHoverAirport\('([^']+)'/ims", $k);

                if (!isset($segment['DepName'])) {
                    $segment["DepName"] = $this->http->FindSingleNode("td[contains(@id, 'departColumn')]/span/span/@onmouseover", $row, true, "/new AirportHoverAirport\('([^']+)'/ims");
                }
                // ArrCode
                $segment["ArrCode"] = $this->http->FindSingleNode(".//div[contains(@class, 'arriveCode')]/span/span", $row, true, null, $k);

                if (!isset($segment['ArrCode'])) {
                    $segment["ArrCode"] = $this->http->FindSingleNode("td[contains(@id, 'arrivalColumn')]/span", $row, true, null);
                }
                // ArrName
                $segment["ArrName"] = $this->http->FindSingleNode(".//div[contains(@class, 'arriveCode')]//@onmouseover", $row, true, "/new AirportHoverAirport\('([^']+)'/ims", $k);

                if (!isset($segment['ArrName'])) {
                    $segment["ArrName"] = $this->http->FindSingleNode("td[contains(@id, 'arrivalColumn')]/span/span/@onmouseover", $row, true, "/new AirportHoverAirport\('([^']+)'/ims");
                }

                if (isset($baseDate)) {
                    $d = $baseDate;
                    // DepDate
                    $s = $this->http->FindSingleNode(".//div[contains(@class, 'departTime')]", $row, true, null, $k);

                    if (!$s) {
                        $s = $this->http->FindSingleNode("td[contains(@id, 'departColumn')]/text()[1]", $row, true, null);
                    }

                    if ($s) {
                        //$this->http->Log("DepDate: $s ");
                        if (isset($segment['FlightNumber']) && $this->http->FindPreg("/Flight # {$segment['FlightNumber']} : (Departs|Departs\/Arrives) next day/ims")) {
                            $d += SECONDS_PER_DAY;
                        }
                        $segment['DepDate'] = strtotime($s, $d);

                        if ($segment['DepDate'] < $lastDate) {
                            $this->http->Log("Old DepDate: {$segment['DepDate']} ");
                            $segment['DepDate'] += SECONDS_PER_DAY;
                            $this->http->Log("New DepDate: {$segment['DepDate']} ");
                        }
                    }
                    // ArrDate
                    $s = $this->http->FindSingleNode(".//div[contains(@class, 'arriveTime')]", $row, true, null, $k);

                    if (!$s) {
                        $s = $this->http->FindSingleNode("td[contains(@id, 'arrivalColumn')]/text()[1]", $row, true, null);
                    }

                    if ($s) {
                        if (isset($segment['FlightNumber']) && $this->http->FindPreg("/Flight # {$segment['FlightNumber']} : Arrives next day/ims")) {
                            $d += SECONDS_PER_DAY;
                        }
                        $segment['ArrDate'] = strtotime($s, $d);

                        if ($segment['ArrDate'] < $lastDate) {
                            $this->http->Log("Old ArrDate: {$segment['ArrDate']} ");
                            $segment['ArrDate'] += SECONDS_PER_DAY;
                            $this->http->Log("New ArrDate: {$segment['ArrDate']} ");
                        }
                        $lastDate = $segment['ArrDate'];
                    }
                }// if (isset($baseDate))
                $segment['Duration'] = $this->http->FindSingleNode(".//div[@class = 'travelTime']", $row, true, null, $k);

                if (!isset($segment['Duration'])) {
                    $segment['Duration'] = $this->http->FindSingleNode("td[contains(@id, 'durationColumn')]", $row, true, null);
                }
                $segment['Meal'] = $this->http->FindSingleNode(".//div[contains(@class, 'mealType')]", $row, true, null, $k);

                if (!isset($segment['Meal'])) {
                    $segment['Meal'] = $this->http->FindSingleNode("td[contains(@id, 'mealColumn')]", $row, true, null);
                }
                $segment['Aircraft'] = $this->http->FindSingleNode(".//div[contains(@class, 'aircraftType')]/text()[1]", $row, true, null, $k);

                if (!isset($segment['Aircraft'])) {
                    $segment['Aircraft'] = $this->http->FindSingleNode("td[contains(@id, 'aircraftColumn')]", $row, true, null);
                }
                $cabin = $this->http->FindSingleNode(".//div[contains(@class, 'cabinType')]", $row, true, null, $k);

                if (!isset($cabin)) {
                    $cabin = $this->http->FindSingleNode("td[contains(@id, 'cabinColumn')]", $row, true, null);
                }

                if ($cabin) {
                    preg_match("/([^\(]+)\s?\(([A-Z]+)\)/", $cabin, $ma);

                    if ($ma) {
                        $segment['Cabin'] = $ma[1];
                        $segment['BookingClass'] = $ma[2];
                    } else {
                        $segment['Cabin'] = $cabin;
                    }
                }
                $seats = $this->http->FindSingleNode(".//div[contains(@class, 'seatNumbers')]", $row, true, null, $k);

                if (!isset($seats)) {
                    $seats = $this->http->FindSingleNode("td[contains(@id, 'seatsPlainColumn')]", $row, true, null);
                }
                $segment['Seats'] = preg_replace('/\s+/', ', ', $seats);

                // For #10059. US Airways site has strange bug - it shows 2014 for reservations which are actually 2015.
                // So we correct dates for this case.
                foreach (['Dep', 'Arr'] as $key) {
                    if (!isset($segment[$key . 'Date'])) {
                        continue;
                    }

                    if ($year and date('Y', $segment[$key . 'Date']) == $year) {
                        continue;
                    }

                    if (isset($segment[$key . 'Date'])
                            and $segment[$key . 'Date']
                            // Compare with current date minus one month
                            and $segment[$key . 'Date'] < time() - 60 * 60 * 24 * 30) {
                        $segment[$key . 'Date'] = strtotime('+1 year', $segment[$key . 'Date']);
                    }
                }

                $segments[] = $segment;
            }// for ($k = 0; $k < $info->length; $k++)
        }// for ($n = 0; $n < $rows->length; $n++)

        if (count($segments) > 0) {
            $result['TripSegments'] = $segments;
        }
        //echo "<pre>".htmlspecialchars(print_r($result, true))."</pre>";
        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "http://reservations.usairways.com/Default.aspx";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("aspnetForm")) {
            $this->sendNotification("dividendmiles - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Depart: {$arFields['Depart']}");

            return null;
        }
        $this->http->Form['ctl00$phMain$ManageReservationModule$ctl00$rezLookup$ConfirmationCodeOrTicketNoTextBox'] = $arFields["ConfNo"];
        $this->http->Form['ctl00$phMain$ManageReservationModule$ctl00$rezLookup$DepartureDateTextBox$SelectedDate'] = $arFields["Depart"];
        $this->http->Form['ctl00$phMain$ManageReservationModule$ctl00$rezLookup$LookupByDropdown'] = 'ConfirmationCodeOrTicketNumber';
        $this->http->Form['__EVENTTARGET'] = urldecode('ctl00%24phMain%24ManageReservationModule%24ctl00%24rezLookup%24SubmitButton');
        unset($this->http->Form['ctl00$siteSearch$imageButtonSearch']);
        unset($this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$RememberMe']);
        unset($this->http->Form['ctl00$dummyAirportHoverLink$btnDummy']);
        $this->http->FormURL = "http://reservations.usairways.com/Default.aspx";

        if (!$this->http->PostForm()) {
            return null;
        }

        if (preg_match("/<span id=\"ctl00_ErrorDisplay_MessageText\">([^<]+)</ims", $this->http->Response["body"], $arMatch)) {
            return $arMatch[1];
        }
        //		$this->ShowLogs();
        $it = $this->CheckItineraryDividendmiles();

        if (!isset($it['RecordLocator'])) {
            $it['RecordLocator'] = $arFields["ConfNo"];
        }

        if (is_string($it)) {
            return $it;
        }

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "Info",
            "Post date"   => "PostingDate",
            "Description" => "Description",
            "Miles"       => "Miles",
            "Bonus"       => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        $this->http->GetURL("https://membership.usairways.com/Manage/YourMiles.aspx");

        if ($this->http->ParseForm("aspnetForm")) {
            $this->http->Form['__EVENTTARGET'] = 'ctl00$phMain$yourMileModule$ctl00$btnSubmit';
            $this->http->Form['__ASYNCPOST'] = 'true';
            $this->http->Form['ctl00$MasterScriptManager'] = 'ctl00$phMain$yourMileModule$ctl00$DividendMilesDetailPanel|ctl00$phMain$yourMileModule$ctl00$btnSubmit';
            $this->http->Form['ctl00_MasterScriptManager_HiddenField'] = ';;AjaxControlToolkit, Version=3.0.20820.12087, Culture=neutral, PublicKeyToken=28f01b0e84b6d53e:en-US:e73c8192-d501-4fd7-a3b9-5354885de87b:91bd373d';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$startDate$SelectedDate'] = date("m/d/Y", strtotime('-3 year', strtotime(date("m/d/Y"))));
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$endDate$SelectedDate'] = date("m/d/Y");
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$chkPreferred'] = 'on';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$chkCarAndHotel'] = 'on';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$chkOther'] = 'on';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$chkAir'] = 'on';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$chkCreditCard'] = 'on';

            $this->http->PostForm();
        }

        $page = 0;

        do {
            $page++;
            $this->http->Log("[Page: {$page}]");
//            if ($page > 1) {
//                $this->http->PostForm();
//            }
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while (!$this->collectedHistory && $page < 1);

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@class = 'viewmiles']//tr[td[6]]");

        if ($nodes->length > 0) {
            $this->http->Log("Found {$nodes->length} items");

            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $postDate = strtotime($this->http->FindSingleNode("td[2]", $nodes->item($i)));
                $this->http->Log("post date: " . var_export($postDate, true) . ", date: " . date("Y-m-d", $postDate));

                if (isset($startDate) && $postDate < $startDate) {
                    $this->http->Log("break");

                    break;
                }
                $result[$startIndex]['Post date'] = $postDate;
                $result[$startIndex]['Date'] = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                // Bonus Miles
                if ($this->http->FindSingleNode("td[5]/img/@src", $nodes->item($i))) {
                    $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                }
                // Preferred Miles
                elseif ($this->http->FindSingleNode("td[6]/img/@src", $nodes->item($i))) {
                    $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                }

                $startIndex++;
            }
        }

        return $result;
    }
}
