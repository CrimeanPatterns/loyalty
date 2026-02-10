<?php

class TAccountCheckerDelta extends TAccountChecker
{
    protected $collectedHistory = false;

    //	public static function GetAccountChecker($accountInfo){
    //		if (ConfigValue(CONFIG_TRAVEL_PLANS)) {
    //			require_once __DIR__."/TAccountCheckerDeltaBeta.php";
    //			return new TAccountCheckerDeltaBeta();
    //		}
    //		else
    //			return new TAccountCheckerDelta();
    //	}

    //	public static function GetCheckStrategy($fields){
    //		if(ConfigValue(CONFIG_TRAVEL_PLANS)){
    //			return CommonCheckAccountFactory::STRATEGY_CHECK_LOCAL;
    //		}
    //		else
    //			return null;
    //	}

    public function getFormMessages()
    {
        //		if ($this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID'])))
        //			return [];
        // -
        return array_merge(
            [$this->getWarning()],
            \AwardWallet\MainBundle\Form\Account\EmailHelper::getMessages(
                $this->AccountFields,
                $this->userFields,
                "https://www.delta.com/profile/basicInfo.action",
                "https://www.delta.com/profile/notificationsAction.action",
                "https://awardwallet.com/blog/track-delta-skymiles-awardwallet/"
            )
        );
    }

    public static function getWarning()
    {
        return new \AwardWallet\MainBundle\Form\Account\Message(
            "
                <ul style='padding-left: 15px'>
                    <li>We have written a <a href='https://awardwallet.com/blog/how-to-track-delta-southwest-united-accounts-awardwallet/' target='_blank'>comprehensive blog post</a> on how to track your Delta accounts; please read it first.</li>
                    <li>Please sign a <a href='http://www.change.org/petitions/delta-airlines-reverse-your-recent-lockout-of-awardwallet-service' target='_blank'>change.org petition</a> letting Delta know you disagree with their decision.</li>
                    <li>In addition to signing change.org, please <a href='https://twitter.com/Delta' target='_blank'>tweet at Delta</a> to let them know your opinion.</li>
                </ul>
            ",
            "alert",
            null,
            "Unfortunately Delta Airlines forced us to stop supporting their loyalty programs."
        );
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->http->setExternalProxy();
        } else {
            $this->http->setDefaultHeader("User-Agent", 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/45.0.2454.85 Safari/537.36');
        }
    }

    public function LoadLoginForm()
    {
        if ($this->AccountFields['Login2'] == "") {
            throw new CheckException("Please fill in you last name in the properties of this reward program", ACCOUNT_INVALID_PASSWORD);
        } /* checked */

        $this->http->removeCookies();
        $this->http->GetURL('https://www.delta.com/smlogin/skymiles_login.action');

        if (!$this->http->ParseForm("smlogin_login")) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "unexpected error occurred")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $this->http->Form['acct'] = $this->AccountFields['Login'];
        $this->http->Form['lastName'] = $this->AccountFields['Login2'];
        $this->http->Form['pin'] = $this->AccountFields['Pass'];
        $this->http->Form['go_button'] = 'Log In';
        $this->http->Form['personalize'] = 'remember';
        $this->http->Form['refreshURL'] = 'http://www.delta.com/smlogin/skymiles_loginNow.action';
        $this->http->Form['redirectFlag'] = 'false';
        $this->http->FormURL = 'https://www.delta.com/smlogin/skymiles_login.action';

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.delta.com/smlogin/skymiles_login.action';

        if (isset($targetURL)) {
            $arg['PostValues']['refreshURL'] = $targetURL;
        } else {
            $arg['PostValues']['refreshURL'] = 'https://www.delta.com/accounthistory/servlet/AccountHist';
        }
        //$arg['SuccessURL'] = 'https://www.delta.com/accounthistory/servlet/AccountHist';
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    public function Login()
    {
        return false;
    }

    public function Parse()
    {
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        $arFields["Login"]["RegExp"] = "/^[^\*]+$/";
        //		if(!$this->securityContext->isGranted('SESSION_CAN_CHECK_FIRST_TIME', getRepository('Provider')->find($this->AccountFields['ProviderID']))) {
        ArrayInsert($arFields, array_key_exists("SavePassword", $arFields) ? "SavePassword" : "Login2", true, [
            "Balance" => [
                "Type"     => "float",
                "Caption"  => "Balance",
                "Required" => false,
                "Value"    => ArrayVal($values, "Balance"),
            ],
            "Status" => getSymfonyContainer()->get('aw.form.account.status_helper')->getField($this->AccountFields, PROPERTY_KIND_STATUS),
        ]);
        // refs #8888
        $arFields["Status"]['Options'] = array_reverse($arFields["Status"]['Options']);
        //		}
    }

    public function SaveForm($values)
    {
        getSymfonyContainer()->get('aw.form.account.status_helper')->saveField(ArrayVal($values, 'Status'), $this->account, PROPERTY_KIND_STATUS);
    }

    public static function GetStatusParams($arFields, &$title, &$img, &$msg)
    {
        $msg = "Unfortunately Delta Airlines forced us to stop supporting their loyalty programs.<br>
				To find out more, please check out our
				<a href='http://awardwallet.com/forum/viewtopic.php?f=16&t=2697' target='_blank'>discussion forum on this subject matter</a>.<br>
				Also, there is a petition going on at change.org:<br>
                <a href='http://www.change.org/petitions/delta-airlines-reverse-your-recent-lockout-of-awardwallet-service' target='_blank'>http://www.change.org/petitions/delta-airlines-reverse-your-recent-lockout-of-awardwallet-service</a><br>
                If you care about this problem, please sign this petition.<br>
				Finally, please voice your opinion by tweeting it to <a href='https://twitter.com/Delta'
				target='_blank'>https://twitter.com/Delta</a>";
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'DeltaCertificates')) {
            if (isset($properties['Currency']) && $properties['Currency'] != '(USD)') {
                return $fields['Balance'] . ' ' . $properties['Currency'];
            } else {
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
            }
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }
    }

    public function CheckItinerary()
    {
        $indexes = 0;

        if (!preg_match_all("/s(\d*)\.SSRs=s\d*/ims", $this->http->Response["body"], $indexes)) {
            return [];
        }
        $result = [];
        $segments = [];
        $body = $this->http->Response['body'];
        $result['RecordLocator'] = $this->http->FindPreg("/recordLocator:\"([^\"]*)\"/ims");

        $totalCharge = $this->http->FindPreg("/\,price\:\"\D*([\d+\.\,]+).*\"/");

        if (isset($totalCharge)) {
            $result['TotalCharge'] = $totalCharge;
            $result['Currency'] = $this->http->FindPreg('/\,price:\".+\(([A-Z]{3})\)\"/');
        }
        // Passengers
        if (preg_match("/passengers:s(\d+)/ims", $body, $match)) {
            $passSeg = $match[1];
        } else {
            $passSeg = 2;
        }
        preg_match_all('/s' . $passSeg . '\[\d+\]=s(\d+)/', $body, $temp);

        if (isset($temp[1][0])) {
            for ($i = 0; $i < count($temp[1]); $i++) {
                if (preg_match('/s' . $temp[1][$i] . '\.nameWithSpaces=\"([^\"]*)\"/', $body, $matches)) {
                    $passengers[] = $matches[1];
                }

                if (preg_match('/s' . $temp[1][$i] . '\.skymilesNumber=\"([^\"]*)\"/', $body, $matches)) {
                    $accounts[] = $matches[1];
                }

                if (preg_match('/s' . $temp[1][$i] . '\.passengerAirSegmentInfo=s(\d+);/', $body, $matches)) {
                    $temp_2[] = $matches[1];
                }
            }

            if (isset($passengers[0])) {
                $result['Passengers'] = implode(', ', $passengers);
            }

            if (isset($accounts[0])) {
                $result['AccountNumbers'] = implode(', ', $accounts);
            }
        }

        if (count($indexes) > 1) {
            $i = 0;

            foreach ($indexes[1] as $index) {
                if (isset($temp_2[0])) {
                    $seats = [];

                    for ($p = 0; $p < count($temp_2); $p++) {
                        if (preg_match('/s' . $temp_2[$p] . '\[' . $i . '\]=s(\d+);/', $body, $temp_3)) {
                            if (preg_match('/s' . $temp_3[1] . '\.seatName=\"([^\"]*)\"/', $body, $matches)) {
                                $seats[] = $matches[1];
                            }
                        }
                    }

                    if (isset($seats[0])) {
                        $segments[$i]["Seats"] = implode(', ', $seats);
                    }
                }

                if (preg_match("/s$index\.arrivalCityName=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["ArrName"] = $matches[1];
                }

                if (preg_match("/s$index\.arrivalAirportCode=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["ArrCode"] = $matches[1];
                }

                if (preg_match("/s$index\.departureCityName=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["DepName"] = $matches[1];
                }

                if (preg_match("/s$index\.departureAirportCode=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["DepCode"] = $matches[1];
                }

                if (preg_match("/s$index\.aircraftType=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["Aircraft"] = $matches[1];
                }

                if (preg_match("/s$index\.flightNumber=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["FlightNumber"] = $matches[1];
                }

                if (preg_match("/s$index\.mealDesc=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["Meal"] = $matches[1];
                }

                if (preg_match("/s$index\.numberOfStops=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["Stops"] = $matches[1];
                }

                if (preg_match("/s$index\.bookedClassCode=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["BookingCode"] = $matches[1];
                }

                if (preg_match("/s$index\.bookedClassDescription=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["Class"] = $matches[1];
                }

                if (preg_match("/s$index\.cabinClassDescription=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["Cabin"] = $matches[1];
                }

                if (preg_match("/s$index\.upgradeStatus=\"([^\"]*)\"/ims", $body, $matches)) {
                    $segments[$i]["Status"] = $matches[1];
                }

                if (preg_match("/s$index\.formattedArrivalDate=\"([^\"]*)\"/ims", $body, $m1) && preg_match("/s$index\.formattedArrivalTime=\"([^\"]*)\"/ims", $body, $m2)) {
                    $segments[$i]["ArrDate"] = strtotime("$m1[1] $m2[1]");
                }

                if (preg_match("/s$index\.formattedDepartureDate=\"([^\"]*)\"/ims", $body, $m1) && preg_match("/s$index\.formattedDepartureTime=\"([^\"]*)\"/ims", $body, $m2)) {
                    $segments[$i]["DepDate"] = strtotime("$m1[1] $m2[1]");
                }

                if (preg_match("/s$index\.duration=\"([^\"]*)\"/ims", $body, $matches)) {
                    $time = explode(".", $matches[1]);

                    if (count($time) > 1) {
                        $segments[$i]["Duration"] = "$time[0]h $time[1]min";
                    }
                }
                $i++;
            }
        }

        if (count($segments) > 0) {
            $result['TripSegments'] = $segments;
        }

        return $result;
    }

    public function ParseItineraries()
    {
        $cookies = $this->http->GetCookies("www.delta.com");
        $request = [
            "callCount"       => "1",
            "page"            => "/myitinerary/itinLanding.action",
            "httpSessionId"   => $cookies["JSESSIONID"],
            "scriptSessionId" => "dontknow", //?
            "c0-scriptName"   => "FutureItinsProcessor",
            "c0-methodName"   => "getItineraries",
            "c0-id"           => "0",
            "c0-param0"       => "boolean:false",
            "batchId"         => "1",
        ];
        $postdata = ImplodeAssoc("=", chr(10), $request);
        $this->http->setDefaultHeader("Content-Type", "text/plain; charset=UTF-8");
        $this->http->PostURL("https://www.delta.com/myitinerary/dwr/call/plaincall/FutureItinsProcessor.getItineraries.dwr", $postdata);

        //$this->http->Log("HTML - ".var_export($this->http->Response['body'], true), true);
        if ($this->http->FindPreg('/dwr\.engine\._remoteHandleCallback\(\'\d\',\'\d\',null\);/ims')) {
            return $this->noItinerariesArr();
        }

        $result = [];

        if (preg_match_all("/recordLocator=\"([^\"]*)\"/ims", $this->http->Response['body'], $arMatches, PREG_SET_ORDER)) {
            foreach ($arMatches as $arMatch) {
                $this->http->PostURL("https://www.delta.com/myitinerary/itinSearch.action", ["searchType" => "confirmNumberRad", "recLocId" => $arMatch[1]]);

                $request = [
                    "callCount"       => "1",
                    "page"            => "/myitinerary/itinSearch.action",
                    "httpSessionId"   => $cookies["JSESSIONID"],
                    "scriptSessionId" => "dontknow",
                    "c0-scriptName"   => "ItineraryDetailsProcessor",
                    "c0-methodName"   => "getItinerary",
                    "c0-id"           => "0",
                    "c0-param0"       => "boolean:false",
                    "c0-param1"       => "boolean:false",
                    "c0-param2"       => "string:" . $arMatch[1],
                    "batchId"         => "0",
                ];
                $postdata = ImplodeAssoc("=", chr(10), $request);
                $this->http->setDefaultHeader("Content-Type", "text/plain; charset=UTF-8");
                $this->http->PostURL("https://www.delta.com/myitinerary/dwr/call/plaincall/FutureItinsProcessor.getItineraries.dwr", $postdata);
                $itin = $this->CheckItinerary();

                if (count($itin) > 0) {
                    $result[] = $itin;
                }
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "http://www.delta.com/index.jsp";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->ParseForms = false;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->FormURL = "https://www.delta.com/myitinerary/itinSearch.action";
        $this->http->Form = [];
        $this->http->Form["searchType"] = "confirmNumberRad";
        $this->http->Form["firstName"] = $arFields["FirstName"];
        $this->http->Form["lastName"] = $arFields["LastName"];
        $this->http->Form["recLocId"] = $arFields["ConfNo"];
        $this->http->Form["goitin"] = "Go";
        $this->http->PostForm();

        if (preg_match("/<P>(We couldn&acute;t find a match[^<]*)</ims", $this->http->Response["body"], $arMatch)) {
            return $arMatch[1];
        }

        if (preg_match("/<span>(We are unable to display your confirmation[^<]*)</ims", $this->http->Response["body"], $arMatch)) {
            return $arMatch[1];
        }
        $this->http->GetURL("https://www.delta.com/myitinerary/popup_printItinerary.action?recLocId=" . urlencode($arFields["RecordLocator"]) . "&firstName=" . urlencode($arFields["FirstName"]) . "&lastName=" . urlencode($arFields["LastName"]));
        $it = $this->CheckItineraryDelta($arFields["ConfNo"]);

        return null;
    }

    public function CheckItineraryDelta($sCode)
    {
        if (preg_match("/Try That Again/ims", $this->http->Response["body"])) {
            return [];
        }

        if (preg_match("/sorry but this service is not available at this time/ims", $this->http->Response["body"])) {
            return [];
        }

        if (preg_match("/t find any reservations with the information you have provided/ims", $this->http->Response["body"])) {
            return [];
        }
        $xpath = $this->http->XPath;
        $nodes = $xpath->query("//table[@id='itineraryTable']/tbody/tr");
        $this->http->Log("Found " . $nodes->length . " itinerary");
        $result = ["RecordLocator" => $sCode];

        for ($n = 0; $n < $nodes->length; $n++) {
            $row = $xpath->query("td", $nodes->item($n));

            if (($row->length == 7) || ($row->length == 8)) {
                if (preg_match("/Schedule Changed/ims", $row->item(6)->nodeValue)) {
                    continue;
                }
                $this->http->Log("itinerary $n");
                $result["TripSegments"][$n] = ["FlightNumber" => preg_replace('/\s+\d+$/ims', '', preg_replace('/^Delta\s+/ims', '', trim(preg_replace("/[\r\n\t\s]+/ims", " ", str_replace("\302\240", ' ', $row->item(0)->nodeValue)))))];

                foreach (["Dep" => 1, "Arr" => 3] as $sPrefix => $nCol) {
                    if (preg_match("/^(.+)\((\w{3})\)/ims", $row->item($nCol)->nodeValue, $arMatch)) {
                        $result["TripSegments"][$n][$sPrefix . "Name"] = trim(preg_replace("/&nbsp$/ims", " ", str_replace("\302\240", ' ', html_entity_decode($arMatch[1]))));
                        $result["TripSegments"][$n][$sPrefix . "Code"] = trim($arMatch[2]);
                        $result["TripSegments"][$n][$sPrefix . "Date"] = strtotime(preg_replace("/[\r\n\t]+/ims", " ", $row->item($nCol + 1)->nodeValue));
                    }
                }

                if (preg_match("/\((\w)\)/ims", $row->item(5)->nodeValue, $arMatch)) {
                    $result["TripSegments"][$n]["Cabin & Class"] = CleanXMLValue($row->item(5)->nodeValue);
                }
                // find seating
                if ($n == 0) {
                    $col = 4;
                } else {
                    $col = 3;
                }
                $seat = $xpath->query("//h3[contains(text(), 'Your Seating')]/following::table[@id='itineraryTable']/tbody/tr[@class = 'tblContent" . ($n + 1) . "']/td[{$col}]");
                $this->http->Log("seats: " . $seat->length);

                if ($seat->length > 0) {
                    $seats = [];

                    for ($i = 0; $i < $seat->length; $i++) {
                        $seats[] = CleanXMLValue($seat->item($i)->nodeValue);
                    }
                    $result["TripSegments"][$n]["Seat"] = implode(", ", $seats);
                }
            }
        }

        if (preg_match_all("/SkyMiles #([^<]+)</ims", $this->http->Response["body"], $matches, PREG_SET_ORDER)) {
            $numbers = [];

            foreach ($matches as $match) {
                $numbers[] = CleanXMLValue($match[1]);
            }
            $result['AccountNumbers'] = implode(", ", $numbers);
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
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

    public function GetHistoryColumns()
    {
        return [
            "Posting Date" => "PostingDate",
            "Description"  => "Description",
            "Miles"        => "Info.Int",
            "Bonus Miles"  => "Bonus",
            "Total Miles"  => "Miles",
            "MQM Earned"   => "Info.Int",
            "MQS Earned"   => "Info.Int",
            "MQD Earned"   => "Info",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);
        $this->http->setDefaultHeader("Content-Type", null);
        $params = [
            'activityType' => 'A',
            'filter'	      => 'Get Activity',
            'fromDate'	    => '01/01/2000',
            'reportRange'  => 'custDateRange',
            'sortBy'	      => 'P',
            'toDate'	      => date("m/d/Y"),
        ];

        $page = 0;
        $this->http->PostURL("https://www.delta.com/accounthistory/servlet/AccountHist", $params);

        do {
            $page++;
            $this->http->Log("[Page: {$page}]");

            if ($page > 1) {
                $this->http->PostForm();
            }
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while ($this->http->ParseForm("navigateForm") && sizeof($this->http->FindNodes("//input[@id='nextPage']")) && !$this->collectedHistory);

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@id='resultsTable']/tbody/tr[@class='even' or @class='odd']");

        if ($nodes->length > 0) {
            $this->http->Log("Found {$nodes->length} items");

            for ($i = 0; $i < $nodes->length; $i++) {
                $postDate = strtotime($this->http->FindSingleNode("td[1]", $nodes->item($i)));

                if (isset($startDate) && $postDate < $startDate) {
                    break;
                }
                $result[$startIndex]['Posting Date'] = $postDate;
                $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[2]/a", $nodes->item($i));
                $result[$startIndex]['Miles'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                $result[$startIndex]['Bonus Miles'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));
                $result[$startIndex]['Total Miles'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                $result[$startIndex]['MQM Earned'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
                $result[$startIndex]['MQS Earned'] = $this->http->FindSingleNode("td[7]", $nodes->item($i));
                $startIndex++;
            }
        }

        return $result;
    }

    private function TestCheckConfirmationNumberInternal()
    {
        $this->CheckConfirmationNumberInternal([
            "FirstName" => "Name", "LastName" => "Last", "RecordLocator" => "#1",
        ], $it);
    }
}
