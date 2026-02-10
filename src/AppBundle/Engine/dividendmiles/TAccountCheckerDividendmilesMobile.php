<?php

class TAccountCheckerDividendmilesMobile extends TAccountCheckerDividendmiles
{
    private $expiration;

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
//        $this->http->GetURL("http://mobile.usairways.com/mt/www.usairways.com/default.aspx?un_jtt_redirect");
//        if (!$this->http->ParseForm(null, 1, "//form[contains(@action, 'https://mobile.usairways.com/mt/membership.usairways.com/Login.aspx')]"))
//            return false;
//        $this->http->FormURL = 'https://mobile.usairways.com/mt/membership.usairways.com/Login.aspx?ReturnUrl=http%3a%2f%2fwww.usairways.com%2fdefault.aspx';
//        $this->http->PostForm();

        $this->http->GetURL("https://mobile.usairways.com/mt/membership.usairways.com/Manage/YourMiles.aspx");
        $this->http->setDefaultHeader('Content-Type', "application/x-www-form-urlencoded");
//        $this->http->setDefaultHeader('Accept-Language', "ru,en-us;q=0.7,en;q=0.3");
//        $this->http->setDefaultHeader('User-Agent', "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:17.0) Gecko/20100101 Firefox/17.0");
//        $this->http->setCookie(
//            'name'    => 's_cc+.usairways.com+%252F',
//            'domain'  => 'mobile.usairways.com',
//            'path'    => '/',
//            'value'   => 'true',
//            'expires' => ''
//        );
//        $this->http->setCookie(
//            'name'    => 's_sq+.usairways.com+%252F',
//            'value'   => '%255B%255BB%255D%255D',
//            'path'    => '/',
//            'domain'  => 'mobile.usairways.com',
//            'expires' => ''
//        );

        if (!$this->http->ParseForm(null, 1, "//form[contains(@action, '/mt/membership.usairways.com/Login.aspx?ReturnUrl=http%3a%2f%2fwww.usairways.com%2fdefault.aspx')]")) {
            return false;
        }
        $this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$UserName'] = $this->AccountFields['Login'];
        $this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$Password'] = $this->AccountFields['Pass'];
//        $this->http->Form['__EVENTTARGET'] = 'ctl00$phMain$loginModule$ctl00$loginForm$Login';
//        $this->http->Form['un_jtt_login_submit'] = 'Log in';
//        $this->http->FormURL = 'https://mobile.usairways.com/mt/membership.usairways.com/Login.aspx?ReturnUrl=http%3a%2f%2fmembership.usairways.com%2fManage%2fYourMiles.aspx';
//        unset($this->http->Form['ctl00$phMain$loginModule$ctl00$loginForm$RememberMe']);

        return true;
    }

    public function CheckErrors()
    {
        //# HTTP Error 404
        if ($message = $this->http->FindSingleNode('//h2[contains(text(),"HTTP Error 404")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Application Unavailable
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Server Application Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')) {
            throw new CheckException("The website is experiencing technical difficulties, please try to check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

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
            return false;
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]/@href")) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(@class, 'un_dividend_txt')]/a")));
        //# Balance
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'un_dividend_txt')]/text()[last()]"));

        $this->http->GetURL("https://mobile.usairways.com/mt/membership.usairways.com/Manage/YourMiles.aspx");
        //# Dividend Miles number
        $this->SetProperty("Number", $this->http->FindPreg("/Dividend\s*Miles\s*number\s*([\d+]+)\s*</ims"));
        //# Preferred status
        $this->SetProperty("Status", CleanXMLValue($this->http->FindPreg("/Preferred\s*status\s*<\/a>\s*([^<]+)/ims")));
        //# Preferred qualifying miles
        $this->SetProperty("Status", CleanXMLValue($this->http->FindPreg("/Preferred\s*qualifying\s*miles\s*<\/a>\s*([^<]+)/ims")));

        if (preg_match_all("/<td class=\"(odd|even)row\">(\d+\/\d+\/\d+)<\/td>/ims", $this->http->Response["body"], $arMatches, PREG_SET_ORDER)) {
            $d = $arMatches[count($arMatches) - 1][2];
            $this->SetProperty("LastActivity", $d);
            $d = strtotime($d);

            if (($d !== false) && (!isset($this->expiration) || $this->expiration === false)) {
                $d = strtotime("+18 month", $d);
                $this->SetExpirationDate($d);
                $this->http->Log("Expiration Date - " . var_export($d, true), true);
            }
        } elseif ($message = $this->http->FindSingleNode("//div[contains(text(), 'No account activity')]")) {
            $this->http->Log(">>> " . $message);
        }
    }

    /*   function GetConfirmationFields() {
           return array(
               "ConfNo" => array(
                   "Caption" => "Confirmation or ticket #",
                   "Type" => "string",
                   "Size" => 20,
                   "Required" => true,
               ),
               "Depart" => array(
                   "Type" => "date",
                   "Value" => date(DATE_FORMAT),
                   "Required" => true,
               ),
           );
       }

       function ParseItineraries() {
           $this->http->GetURL("http://mobile.usairways.com/mt/reservations.usairways.com/");
           if($this->http->FindSingleNode("//tr[th/h4[contains(text(), 'Travel Dates')] and th/h4[contains(text(), 'Confirmation code')]]/following::tr[1]/td[contains(text(), 'No flights')]"))
               return $this->noItinerariesArr();
           $xpath = $this->http->XPath;
           $nodes = $xpath->query("//a[contains(@href, 'http://reservations.usairways.com/Default.aspx')]/@href");
           $result = array();
           //$this->http->Log("Found ".$nodes->length." Itineraries");
           for ($i = 0; $i < $nodes->length; $i++) {
               $this->http->GetURL(CleanXMLValue($nodes->item($i)->nodeValue));
               $itin = $this->CheckItineraryDividendmiles();
               if (($itin === NULL) || !is_array($itin) || (count($itin) == 0))
                   continue;
               else
                   $result[$i] = $itin;
           }
           //echo "<pre>".htmlspecialchars(print_r($result, true))."</pre>";
           //die();
           return $result;
       }

       private function CheckItineraryDividendmiles() {
           if (preg_match("/Your flight schedule for confirmation code \w+ has changed/ims", $this->http->Response["body"]))
               return "Your flight schedule has changed. Login to US Aiways website to confirm changes";
           if (preg_match("/You have entered an invalid confirmation code or departure date/ims", $this->http->Response["body"]))
               return "You have entered an invalid confirmation code or departure date/ims";
           $result = array();
           $segments = array();
           $xpath = $this->http->XPath;

           // ConfirmationNumber
           $result['RecordLocator'] = $this->http->FindSingleNode('//h2[@class="gold"]|//h3[@class="gold"]', null, true,'/Confirmation code:\s*(.*)/');
           // ReservationDate
           $nodes = $xpath->query("//div[div[h4[contains(text(),'Original date issued')]]]/div/span");
           if ($nodes->length > 0)
               $resDate = CleanXMLValue($nodes->item(0)->nodeValue);
           if(isset($resDate) and strtotime($resDate) !== false)
               $result['ReservationDate'] = strtotime($resDate);
           // TotalCharge
           $result['TotalCharge'] = $this->http->FindSingleNode('//h2[contains(text(), "Total")]/span', null, true, '/(\d+.\d+|\d+)/');
           // Currency
           if(preg_match( '/([\$]{1})/', $this->http->FindSingleNode('//h2[contains(text(), "Total")]/span') ) && !empty($result['TotalCharge']))
               $result['Currency'] = 'USD';
           else
               unset($result['TotalCharge']);
           // Status
           $result['Status'] = $this->http->FindSingleNode('//span[@id="ctl00_phMain_TripDetailsModule_ctl00_TripDetailsControl1_SliceViewRepeater_ctl00_DepartReturnSubHeader_lblStatus" or @id="ctl00_phMain_scheduleChangeModule_ctl00_tripDetails_SliceViewRepeater_ctl00_DepartReturnSubHeader_lblStatus"]');
           if(empty($result['Status']))
               unset($result['Status']);
           if(isset($result['Status']) and $result['Status'] == 'Canceled')
               $result['Cancelled'] = 1;
           // AccountNumber
           $accounts = $this->http->FindNodes('//span[contains(@id, "blFrequentFlyerAirlineCombo")]');
           for($a=0; $a<count($accounts); $a++){
               if(preg_match('/(\d+)/', $accounts[$a], $temp))
                   $accountsArr[] = $temp[1];
           }
           if(isset($accountsArr[0]))
               $result['AccountNumbers'] = implode(', ', $accountsArr);

           $nodes = $xpath->query("//span[contains(@id, 'lblPassengerName')]");
           if ($nodes->length > 0) {
               $paxes = array();
               for ($n = 0; $n < $nodes->length; $n++)
                   $paxes[] = CleanXMLValue($nodes->item($n)->nodeValue);
               $result['Passengers'] = implode(", ", $paxes);
           }

           // depart & return 2node
           $rows = $xpath->query('//div[@class="spaceleftsm citypair"]');

           $this->http->Log("Side count: ".$rows->length);
           $segments = array();
           for ($n = 0; $n < $rows->length; $n++) {

               $status = $xpath->query('//span[contains(text(), "Status")]/../following::span[1]', $rows->item($n));
               if(trim($status->item($n)->nodeValue) == 'Canceled' || trim($status->item($n)->nodeValue) == 'Used')
                   continue;

               if(empty($status->item(0)->nodeValue)){
                   $status = $xpath->query('//span[contains(text(), "Status")]/../following::a[1]', $rows->item($n));
                   if(trim($status->item(0)->nodeValue) == 'Refunded')
                       continue;
               }

               // segments
               $info = $xpath->query('//div[@class="padtopxsm"]['.($n+1).']/div/table/tr[count(td)=8 and td[@id]]');
               $this->http->Log("Segments for this side count: ".$info->length);
               for($k = 0; $k < $info->length; $k++) {
                   $segment = array();
                   $row = $info->item($k);
                   $cells = $xpath->query("td[contains(@id, 'fltnumColumn')]/div", $row);
                   if ($cells->length > 0) {
    */ //                   if(preg_match('/\s*(\S*)\s*.*/ims', CleanXMLValue($cells->item(0)->nodeValue), $m ))
 /*                       $segment['FlightNumber'] = $m[1];
                }
                $cells = $xpath->query("td[contains(@id, '_departColumn')]/span/span", $row);
                if ($cells->length > 0) {
                    $segment["DepCode"] = CleanXMLValue($cells->item(0)->nodeValue);
                    $mouseOver = $cells->item(0)->getAttribute("onmouseover");
                    $id = $cells->item(0)->getAttribute("id");
                    if (preg_match("/new AirportHoverAirport\('([^']+)'/ims", $mouseOver, $matches))
                        $segment["DepName"] = $matches[1];
                }
                $cells = $xpath->query("td[contains(@id, '_arrivalColumn')]/span/span", $row);
                if ($cells->length > 0) {
                    $segment["ArrCode"] = CleanXMLValue($cells->item(0)->nodeValue);
                    $mouseOver = $cells->item(0)->getAttribute("onmouseover");
                    if (preg_match("/new AirportHoverAirport\('([^']+)'/ims", $mouseOver, $matches))
                        $segment["ArrName"] = $matches[1];
                }
                $nodes = $xpath->query("preceding::span[contains(@id, 'DepartReturnSubHeader_departDateValueLabel')]", $row);
                if ($nodes->length > 0) {
                    $baseDate = strtotime(CleanXMLValue($nodes->item($nodes->length - 1)->nodeValue));
                    //$this->http->Log("base date: ".date(DATE_FORMAT, $baseDate));
                } else {
                    //$this->http->Log("base date not found");
                    $baseDate = null;
                }
                if (isset($baseDate)) {
                    $cells = $xpath->query("td[contains(@id, '_departColumn')]/text()", $row);
                    $d = $baseDate;
                    if ($cells->length > 0) {
                        $s = CleanXMLValue($cells->item(0)->nodeValue);
                        //$this->http->Log("DepDate: ".$s);
                        if (isset($segment['FlightNumber']) && preg_match("/>Flight # {$segment['FlightNumber']} : Departs next day/ims", $this->http->Response["body"]))
                            $d += SECONDS_PER_DAY;
                        $segment['DepDate'] = strtotime($s, $d);
                    }
                    $cells = $xpath->query("td[contains(@id, '_arrivalColumn')]/text()", $row);
                    if ($cells->length > 0) {
                        $s = CleanXMLValue($cells->item(0)->nodeValue);
                        //$this->http->Log("ArrDate: ".$s);
                        if (isset($segment['FlightNumber']) && preg_match("/>Flight # {$segment['FlightNumber']} : Arrives next day/ims", $this->http->Response["body"]))
                            $d += SECONDS_PER_DAY;
                        $segment['ArrDate'] = strtotime($s, $d);
                    }
                }
                $cells = $xpath->query("td[contains(@id, '_durationColumn')]", $row);
                if ($cells->length > 0)
                    $segment['Duration'] = CleanXMLValue($cells->item(0)->nodeValue);
                $cells = $xpath->query("td[contains(@id, '_mealColumn')]", $row);
                if ($cells->length > 0)
                    $segment['Meal'] = CleanXMLValue($cells->item(0)->nodeValue);
                $cells = $xpath->query("td[contains(@id, '_aircraftColumn')]", $row);
                if ($cells->length > 0)
                    $segment['Aircraft'] = CleanXMLValue($cells->item(0)->nodeValue);
                $cells = $xpath->query("td[contains(@id, '_cabinColumn')]", $row);
                if ($cells->length > 0) {
                    //$segment['Cabin'] = CleanXMLValue($cells->item(0)->nodeValue);
                    preg_match("/([^\(]+)\s?\(([A-Z]+)\)/", CleanXMLValue($cells->item(0)->nodeValue), $ma);
                    if ($ma) {
                        $segment['Cabin'] = $ma[1];
                        $segment['BookingClass'] = $ma[2];
                    }
                    else
                        $segment['Cabin'] = CleanXMLValue($cells->item(0)->nodeValue);
                }
                $cells = $xpath->query("td[contains(@id, '_seatsLinkColumn')]/a", $row);
                if ($cells->length > 0)
                    $segment['Seats'] = preg_replace('/\s+/', ', ', CleanXMLValue($cells->item(0)->nodeValue));
                $segments[] = $segment;
            }
        }
        if (count($segments) > 0)
            $result['TripSegments'] = $segments;
        else //if 0 segments then all segments cancelled => reservation cancelled
            return array('RecordLocator' => $result['RecordLocator'],'ConfirmationNumber' => $result['ConfirmationNumber'], 'Cancelled' => true, 'Hidden' => true);
        //echo "<pre>".htmlspecialchars(print_r($result, true))."</pre>";
        $result['Hidden'] = $result['Cancelled'] = false;
        return $result;
    }

    function CheckConfirmationNumberInternal($arFields, &$it) {
        $this->http->GetURL("http://reservations.usairways.com/Default.aspx");
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
        //unset($form['ctl00$CustomerHeader$btnSearch']);
        //unset($form['ctl00$CustomerHeader$ChangeBtn']);
        $this->http->FormURL = "http://reservations.usairways.com/Default.aspx";
        if(!$this->http->PostForm())
            return "Engine error";
        if (preg_match("/<span id=\"ctl00_ErrorDisplay_MessageText\">([^<]+)</ims", $this->http->Response["body"], $arMatch))
            return $arMatch[1];
        //		$this->ShowLogs();
        $it = $this->CheckItineraryDividendmiles();
        if(!isset($it['RecordLocator'])) {
            $it['RecordLocator'] = $arFields["ConfNo"];//$this->http->FindSingleNode('//h3[@class="gold"]/span');
        }
        //echo "<pre>".htmlspecialchars(print_r($it, true))."</pre>";
        //echo "<pre>".htmlspecialchars(print_r($this->http->Response, true))."</pre>";
        //die();

        if (is_string($it))
            return $it;
        return null;
    }


    function GetHistoryColumns() {
        return array(
            "Date" => "Info",
            "Post date" => "PostingDate",
            "Description" => "Description",
            "Miles" => "Miles",
            "Bonus" => "Info",
            "Preferred" => "Info",
        );
    }

    protected $collectedHistory = false;

    function ParseHistory($startDate = null) {
        $this->http->Log('[History start date: '.((isset($startDate))?date('Y/m/d H:i:s', $startDate):'all').']');
        $result = array();
        $startTimer = microtime(true);

        $this->http->GetURL("https://membership.usairways.com/Manage/YourMiles.aspx");
        if ($this->http->ParseForm("aspnetForm")){
            $this->http->Form['__EVENTTARGET'] = 'ctl00$phMain$yourMileModule$ctl00$btnSubmit';
            $this->http->Form['__ASYNCPOST'] = 'true';
            $this->http->Form['ctl00$MasterScriptManager'] = 'ctl00$phMain$yourMileModule$ctl00$DividendMilesDetailPanel|ctl00$phMain$yourMileModule$ctl00$btnSubmit';
            $this->http->Form['ctl00_MasterScriptManager_HiddenField'] = ';;AjaxControlToolkit, Version=3.0.20820.12087, Culture=neutral, PublicKeyToken=28f01b0e84b6d53e:en-US:e73c8192-d501-4fd7-a3b9-5354885de87b:91bd373d';
            $this->http->Form['ctl00$phMain$yourMileModule$ctl00$startDate$SelectedDate'] = date("m/d/Y", strtotime ('-3 year', strtotime(date("m/d/Y"))));
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

        $this->http->Log("[Time parsing: ".(microtime(true) - $startTimer)."]");

        return $result;
    }

    function ParsePageHistory($startIndex, $startDate) {
        $result = array();
        $nodes = $this->http->XPath->query("//table[@class = 'viewmiles']//tr[td[6]]");
        if ($nodes->length > 0) {
            $this->http->Log("Found {$nodes->length} items");
            for ($i = 0; $i < $nodes->length; $i++) {
                $postDate = strtotime($this->http->FindSingleNode("td[2]", $nodes->item($i)));
                if (isset($startDate) && $postDate < $startDate)
                    break;
                $result[$startIndex]['Post date'] = $postDate;
                $result[$startIndex]['Date'] = $this->http->FindSingleNode("td[1]", $nodes->item($i));
                $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                $result[$startIndex]['Preferred'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
                $startIndex++;
            }
        }
        return $result;
    }
*/
}
