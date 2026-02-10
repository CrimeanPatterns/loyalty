<?php

namespace AwardWallet\Engine\amextravel\Email;

class PDF3 extends \TAccountCheckerExtended
{
    public $reFrom = "#customerservice@amextravel\.com#i";
    public $reProvider = "#amextravel\.com#i";
    public $rePlain = "";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "amextravel/it-1594044.eml";
    public $rePDF = "Travel Arrangements for";
    public $rePDFRange = "";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');
                    $this->plainText = $this->getDocument('application/pdf', 'text');

                    if (!re("#American\s*Express#i")) {
                        return null;
                    }

                    $this->fullText = $text;

                    $this->travelers = nodes("//text()[contains(., 'Travel Arrangements for:')]/following-sibling::text()");

                    $subj = re('#(?:Carrier\s+Airline\s+Reference|Airline\s+Reference\s+Carrier)\s+(.*)\s+Additional\s+Messages#s');
                    $this->airlineData = [];
                    $regex = '#(?P<RecordLocator>\w+)\s+(?P<AirlineName>.*)#';

                    if (preg_match_all($regex, $subj, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->airlineData[trim($m['AirlineName'])]['RecordLocator'] = trim($m['RecordLocator']);
                        }
                    }

                    $subj = re('#Loyalty\s+Programs\s+\w+\s+\w+\s+\w+\s+(.*)\s+Airline\s+Record\s+Locators#s');
                    $airlineNames = join('|', array_keys($this->airlineData));

                    if (preg_match_all('#(' . $airlineNames . ')\s+([\w\d]+)#', $subj, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->airlineData[$m[1]]['LoyaltyAccount'] = $m[2];
                        }
                    }

                    $xpath = "//text()[contains(., 'FLIGHT INFORMATION')]/ancestor::p[1]";
                    $this->segmentNodes = $this->http->XPath->query($xpath);

                    // Cleaning PDF footers
                    $text = preg_replace('#ONLINE\s+\|\s+OFFLINE\s+\|\s+ALL\s+AROUND\s+THE\s+WORLD\s+\|\s+EXPERIENCE\s+MATTERS\s+TM\s+\d+\s+Page\s+\d+\s+of\s+\d+#s', '', $text);

                    $this->pdfText = $text;
                    $subj = re('#(FLIGHT\s+INFORMATION.*)\s+(?:Loyalty\s+Program.*)?Additional\s+Messages#ius', $text);

                    return splitter('#((?:FLIGHT|HOTEL|Rental\s+Car)\s+INFORMATION)#ims', $subj);
                },

                "#Flight\s+Information#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $this->currentAirlineName = nice(re('#Airline:?\s+(.*?)\s+(?:Estimated\s+time|Airline\s+Record\s+Locator|TSA\s+SECURED\s+FLIGHT)#s'));

                        if ($subj = re('#OPERATED BY\s+(.*)#')) {
                            $this->currentOriginalAirlineName = $this->currentAirlineName;
                            $this->currentAirlineName = trim($subj);
                        } else {
                            $this->currentOriginalAirlineName = $this->currentAirlineName;
                        }

                        $s = re('#Airline Record Locator\s+([\w\-]+)#');

                        if (!$s) {
                            if (!empty($this->airlineData)) {
                                if (isset($this->airlineData[$this->currentAirlineName])) {
                                    $s = $this->airlineData[$this->currentAirlineName]['RecordLocator'];
                                } elseif (isset($this->airlineData[$this->currentOriginalAirlineName])) {
                                    $s = $this->airlineData[$this->currentOriginalAirlineName]['RecordLocator'];
                                }
                            }
                        }

                        return $s;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        if (isset($this->airlineData[$this->currentAirlineName]['LoyaltyAccount'])) {
                            return $this->airlineData[$this->currentAirlineName]['LoyaltyAccount'];
                        } elseif (isset($this->airlineData[$this->currentOriginalAirlineName]['LoyaltyAccount'])) {
                            return $this->airlineData[$this->currentOriginalAirlineName]['LoyaltyAccount'];
                        } else {
                            return null;
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight:?\s+\w*?(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $res = null;
                            static $currentDateStr = null;
                            $s = re('#Travel\s+Details:\s+\w+\s+(\w+\s+\d+,\s+\d+)\s+.{1,1000}' . re('#Flight:\s+\d+#') . '#s', $this->pdfText);

                            if ($s) {
                                $currentDateStr = $s;
                            }
                            $arr = [
                                'Dep' => [
                                    'NameRegex'      => '#Departure:\s+(\d+:\d+\s+\w+)\s+(.*?)\s+Estimated\s+Time#',
                                    'TerminalPrefix' => 'Departure',
                                ],
                                'Arr' => [
                                    'NameRegex'      => '#Arrival:\s+(\d+:\d+\s+\w+)\s+(.*?)\s+Distance#',
                                    'TerminalPrefix' => 'Arrival',
                                ],
                            ];

                            foreach ($arr as $key => $value) {
                                if (preg_match($value['NameRegex'], $text, $m)) {
                                    $name = $m[2];
                                    $regex = '#' . $value['TerminalPrefix'] . '\s+Terminal:?\s+(.*)#';

                                    if ($name and preg_match($regex, $text, $m2)) {
                                        $name .= ' (' . $m2[1] . ')';
                                    }

                                    if ($name) {
                                        $res["${key}Name"] = $name;
                                    }

                                    $timeStr = $m[1];
                                    $res["${key}Date"] = strtotime($currentDateStr . ', ' . $timeStr);

                                    if ($key == 'Arr' and preg_match('#Arriving\s+on:\s+(.*)#', $text, $m2)) {
                                        $res['ArrDate'] = strtotime($m2[1] . ', ' . $timeStr);
                                    }
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return $this->currentAirlineName;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Equipment:\s+(.*)#');
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            ($s = re('#\n(.*)\s+Miles\n\s+Distance:#')) || ($s = re('#Distance:?\s+(.*)\s+Miles#'));

                            return (float) str_replace(',', '', $s);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class:?\s+(.*)#');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return str_replace(' ', ',', trim(re('#Seat(?:s:)?\s+(.*)#')));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#Estimated\s+Time:\s+(.*)#');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            $foodVariants = '(Dinner|Food\s+(?:to|for)\s+purchase|Lunch|Meals|No\s+Meal\s+Service|Snack|Snack/brunch)?';
                            $regex = '#';
                            $regex .= $foodVariants . '\s+Meal:?\s+(?:Service\s+)?' . $foodVariants;
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                if (isset($m[1]) || isset($m[2])) {
                                    ($s = $m[1]) || ($s = $m[2]);

                                    return $s;
                                }
                            }
                        },
                    ],
                ],

                "#Hotel\s+Information#i" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $this->currentHotelConfNo = re("#Confirmation\s+Number\s+([\w\-]+)#");

                        return $this->currentHotelConfNo;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#\n\s*Hotel\s+(.*)#');
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Hotel\s+Address\s+(.*?)\s+Phone\s+Number.*\n\s*(.*?)\s+Fax\s+Number#';

                        if (preg_match($regex, $text, $m)) {
                            return $m[1] . ', ' . $m[2];
                        } else {
                            $regex = '#Hotel\s+Address\s+(.*?)\s+Confirmation\s+Number#s';

                            if (preg_match($regex, $text, $m)) {
                                return nice(trim($m[1]), ',');
                            }
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Check in Date\s+([\d/]+)#"));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Check out Date\s+([\d/]+)#"));
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Phone\s+Number\s+(.*)#'));
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Fax\s+Number\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#Hotel Rate\s+(.*)\n#");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },

                "#Rental\s+Car\s+Information#i" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation\s+Number\s+([\w\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $subj = nice(re("#Location\s+(.*?)\s+Category#"));

                        if ($subj) {
                            return ['PickupLocation' => $subj, 'DropoffLocation' => $subj];
                        }
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('at', '', re("#Pick\s+Up\s+Date\s+(.*?)\s+Air\s+Conditioning#")));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(str_replace('at', '', re("#Drop\s+Off\s+Date\s+(.*)#")));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Agency\s+(.*?)\s+Car\s+Size#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#Car\s+Size\s+(.*)#') . ' ' . re('#Category\s+(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $s = re('#price\s+including\s+taxes\s+-\s+(.*)#');

                        return ['TotalCharge' => cost($s), 'Currency' => currency($s)];
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }
}
