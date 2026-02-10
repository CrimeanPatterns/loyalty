<?php

namespace AwardWallet\Engine\egencia\Email;

class TravelBooking extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Egencia\s+are\s+pleased\s+to\s+confirm\s+the\s+attached\s+reservation#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['#(?:&\#160;|\s+)Egencia(?:&\#160;|\s+)#i', 'blank', '-500'],
        ['#Egencia,\s+an\s+Expedia\s+Inc\.\s+Company#iu', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#customer_service@egencia\.com\.au|vip@egencia\.com\.au#i', 'us', ''],
    ];
    public $reProvider = [
        ['#egencia\.com\.au#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "26.05.2015, 07:43";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "egencia/it-11.eml, egencia/it-12.eml, egencia/it-1801375.eml, egencia/it-1801396.eml, egencia/it-1801399.eml, egencia/it-1803087.eml, egencia/it-1843571.eml, egencia/it-1896445.eml, egencia/it-1907076.eml, egencia/it-1946147.eml, egencia/it-1999192.eml, egencia/it-2034406.eml, egencia/it-2042064.eml, egencia/it-2215270.eml, egencia/it-2223524.eml, egencia/it-2232048.eml, egencia/it-2232187.eml, egencia/it-2240276.eml, egencia/it-2271740.eml, egencia/it-2283914.eml, egencia/it-2393372.eml, egencia/it-2503702.eml, egencia/it-2504242.eml, egencia/it-2523966.eml, egencia/it-2747411.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    // get user email from subjects
                    $emailHeaders = $this->parser->getHeaders();
                    $headers = [
                        'subject'        => $this->parser->getSubject(),
                        'anotherSubject' => $emailHeaders['subject'] ?? null,
                    ];

                    $emails = [];

                    foreach ($headers as $item) {
                        if (is_array($item)) {
                            continue;
                        }

                        if ($email = re("#[\w\d\._\-]+@[\w\d\._\-]+\.(?:[\w\d\._\-]+)+#ims", $item)) {
                            $emails[$email] = 1;
                        }
                    }

                    $clean = $this->parser->getCleanTo();

                    if (!re("#plans@traxo#i", $clean)) {
                        $emails[$clean] = 1;
                    }

                    $emails = array_keys($emails);

                    if (count($emails)) {
                        $this->parsedValue('userEmail', reset($emails));
                    }

                    $this->setDocument("application/pdf", "simpletable");

                    if ($s = re("#\n\s*Estimated Price\s*:\s*([^\n]+)#", $this->text())) {
                        $total = total($s, 'Amount');
                        $this->parsedValue('TotalCharge', $total);
                    }

                    // clear Page info
                    $rows = xpath("//tr");
                    $html = [];

                    for ($i = 0; $i < $rows->length; $i++) {
                        $row = $rows->item($i);
                        $info = text($row);

                        if (re("#^\s*(?:View over)#i", $info)) {
                            ++$i;
                        }

                        if (re("#^\s*(?:www.egencia.com.au)#", $info)) {
                            $i += 2;
                        } elseif (re("#^\s*(?:Hotel\s+rules|Fare\s+rules)#", $info)) {
                            $i++;
                        } elseif (re("#^\s*(?:Page\s+\d+|phone)#", $info)) {
                            // skip
                        } else {
                            //print $info."\n";
                            $html[] = clear("#(&nbsp;|\s)#i", html($row), " ");
                        }
                    }
                    //print "<table>".implode('', $html)."</table>";
                    $this->setDocument("source", "<table>" . implode('', $html) . "</table>");

                    $x = "
						//tr[contains(., 'Pick') and contains(., 'Drop') and contains(., 'Car')] | 
						//tr[contains(., 'Departing') and contains(., 'Arriving')] | 
						//tr[contains(., 'Transfer') and contains(., 'Start') and not(contains(., 'Fare'))] | 
						//tr[contains(., 'Hotel') and contains(., 'Out')]
					";

                    return xpath($x);
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },

                "#^Hotel#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $ref = node("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>=1][last()]", null, true, "#^([A-Z\d\-]{5,})#");

                        if (!re("#\d#", $ref)) {
                            $ref = node("following-sibling::tr[position()<15][contains(.,'Ref') and contains(.,'Booking')][1]", null, true, "#Booking\s*Ref[\s:\-]+([A-Z\d\-]+)#i");
                        }

                        return $ref;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $hotel = node("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>2][1]");
                        $this->dateGone = detach("#\s*\w{3},\s*\d+\s+\w{3}\s*$#", $hotel);

                        if (re("#offline\s*hotel#i", $hotel)) {
                            $info = text(xpath("following-sibling::tr[position()<10]"));
                            $hotel = re("#\n\s*Remarks[:\s]+([^\-\n]+)#i", $info);
                            $phone = detach("#\s+[\d \-\(\)+]{5,}$#", $hotel);
                        }

                        return $hotel;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(clear("#,#", node("preceding-sibling::tr[contains(.,',')][string-length(normalize-space(.))>5][1]", null, true, "#(\d+\s+\w+,\s*\d+)$#")));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        // if date gone to hotel cell
                        if ($this->dateGone) {
                            $date = node("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>2][2]", null, true, "#(\d+\s+\w+)#");
                        } else {
                            // if in own cell
                            $date = node("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>2][3]", null, true, "#(\d+\s+\w+)#");
                        }

                        $anchor = clear("#,#", node("preceding-sibling::tr[contains(.,',')][string-length(normalize-space(.))>5][1]", null, true, "#(\d+\s+\w+,\s*\d+)$#"));

                        return correctDate($date, $anchor);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            node("following-sibling::tr[contains(., 'Address:')][1]/td[contains(., 'Address:')]/following-sibling::td[string-length(normalize-space(.))>5][1]"),
                            $it['HotelName']
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return node('following-sibling::tr[contains(., "Phone:")][1]/td[string-length(normalize-space(.)) > 1][2]');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $s = re('#Room\s+Confirmations?:\s+(.*)#is', implode("\n", nodes('following-sibling::tr[contains(., "Address:")][1]/td[string-length(normalize-space(.)) > 1][3]//text()')));

                        if (preg_match_all('#(.*)\s*:\s+.*#i', $s, $m)) {
                            return [
                                'GuestNames' => $m[1],
                            ];
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $index = $this->dateGone ? 3 : 4;

                        return orval(
                            re("#Room/([\d.,]+\s*[A-Za-z]{3})\s+#", xpath("following-sibling::tr[position()<5]")),
                            trim(clear("#\^#", node("following-sibling::tr[contains(., ',')][string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>2][$index]")))
                        );
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('following-sibling::tr[contains(., "Cancellation") and not(contains(., "Amenities:"))][1]/td[string-length(normalize-space(.)) > 1][1]', null, true, "#^Cancel+ation\s+(.+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("following-sibling::tr[contains(., 'Address') and contains(., 'Remarks:')][1]"));

                        if (!$info) {
                            $info = text(xpath("following-sibling::tr[position()<5]"));

                            return re("#\-\s+(.*?\s+Room)/[\d.,]+\s+[A-Za-z]{3}\s+#", $info);
                        }

                        return re("#\n\s*Remarks\s*:\s*([^\n-]+)#", $info);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $index = $this->dateGone ? 3 : 4;

                        return currency(node("following-sibling::tr[string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>2][$index]"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#This trip is (\w+)#ix", $this->text());
                    },
                ],

                "#^Car#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return node("following-sibling::tr[string-length(normalize-space(.))>30][1]/td[string-length(normalize-space(.))>1][last()]", null, true, "#^([A-Z\d\-]+)$#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $moreInfo = node("following-sibling::tr[position() < 10]/td[contains(., 'up:')][1]", null, true, "#:\s*([^\n]+)#");

                        $date = clear("#,#", node("preceding-sibling::tr[string-length(normalize-space(.))>5][1]", null, true, "#(\d+\s+\w+,\s*\d+)$#"));
                        $info = text(xpath("following-sibling::tr[string-length(normalize-space(.))>30][1]/td[string-length(normalize-space(.))>1][2]"));

                        return [
                            "PickupLocation" => orval($moreInfo, re("#^(.*?)(?:^|\n)\s*\w+,\s*(\d+\s+\w+)\s+\d+,\s*(\d+:\d+\s*[APM]*)#i", $info)),
                            "PickupDatetime" => correctDate(re(2) . ',' . re(3), $date),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = clear("#,#", node("preceding-sibling::tr[string-length(normalize-space(.))>5][1]", null, true, "#(\d+\s+\w+,\s*\d+)$#"));
                        $info = text(xpath("following-sibling::tr[string-length(normalize-space(.))>30][1]/td[string-length(normalize-space(.))>1][3]"));

                        return [
                            "DropoffLocation" => orval(
                                re("#^(.*?)(?:^|\n)\s*\w+,\s*(\d+\s+\w+)\s+\d+,\s*(\d+:\d+\s*[APM]*)#i", $info),
                                $it["PickupLocation"]
                            ),
                            "DropoffDatetime" => correctDate(re(2) . ',' . re(3), $date),
                        ];
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return node("following-sibling::tr[position() < 10]/td[contains(., 'Phone:')]/following-sibling::td[string-length(normalize-space(.))>1][1]");
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return node("following-sibling::tr[string-length(normalize-space(.))>30][1]/td[string-length(normalize-space(.))>1][1]");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("following-sibling::tr[position() < 10]/td[contains(., 'Remarks:')][1]"));

                        return [
                            'CarType' => re([
                                "#\n\s*(?:Confirmed|Whitelisted|Cancel+ed)\s+([A-Z]{3,})\s+(.*?/similar)#i",
                                "#\n\s*(.{0})([^\n]*?/similar)#i",
                            ], $info),
                            'CarModel' => re(2),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//tr[contains(., 'Name') and contains(., 'Information')]/following::tr[position()<10]/td[string-length(normalize-space(.))>2][1]");
                        $names = [];

                        foreach ($nodes as $node) {
                            if (re("#Cost|Department|This|Company|Trip|[A-Z]{3}|^\d#", $node)) {
                                break;
                            }
                            $names[] = $node;
                        }

                        return implode(',', $names);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(node("following-sibling::tr[position() < 10]/td[contains(., 'Remarks:')][1]", null, true, "#Estimated\s*Total[:\s]+([A-Z]{3}\s*[\d.,]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $info = $info = text(xpath("following-sibling::tr[position() < 10]/td[contains(., 'Remarks:')][1]"));

                        return orval(
                            re("#\n\s*(Confirmed|Whitelisted|Cancel+ed)\s+#", $info),
                            re("#This trip is (\w+)#ix", $this->text())
                        );
                    },
                ],

                "#^Flight#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//td[contains(., 'Reference:')]/following::td[string-length(normalize-space(.))>1][1]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//tr[contains(., 'Name') and contains(., 'Information')]/following::tr[position()<10]/td[string-length(normalize-space(.))>2][1]");
                        $names = [];

                        foreach ($nodes as $node) {
                            if (re("#Cost|Department|This|[A-Z]{3}|,|Price|\d|Trip#", $node)) {
                                break;
                            }

                            if ($node) {
                                $names[] = $node;
                            }
                        }

                        return $names;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//tr[contains(., 'Name') and contains(., 'Information')]/following::tr[position()<10]/td[contains(., 'Frequent')][1]/following-sibling::td[string-length(normalize-space(.))>2]");
                        $names = [];

                        foreach ($nodes as $node) {
                            if (re("#Cost|Department|This|Estimated#", $node)) {
                                break;
                            }
                            $names[] = $node;
                        }

                        return implode(",", $names);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Airfare\s+([^\n]+)#", $this->text()));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes\s+([^\n]+)#", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#\n\s*(Confirmed|Cancel+ed|Waitlisted)\s+#", xpath("following-sibling::tr[position()<10]")),
                            //re("#\n\s*(Confirmed|Cancel+ed|Waitlisted)#", xpath("following::tr[string-length(normalize-space(.))>10][1]")),
                            re("#This trip is (\w+)#ix", $this->text())
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>1][1]/td[string-length(normalize-space(.))>1][1]", null, true, "#\s+([A-Z\d]{2}\s*\d+)$#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $depInfo = text(xpath("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>11][1]/td[string-length(normalize-space(.))>1][2]"));
                            $arrInfo = text(xpath("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>11][1]/td[string-length(normalize-space(.))>1][3]"));

                            $date = clear("#,#", node("preceding-sibling::tr[contains(.,',')][string-length(normalize-space(.))>5][1]", null, true, "#(\d+\s+\w+,\s*\d+)$#"));
                            $dep = totime($date . ',' . uberTime($depInfo));

                            if (uberDate($arrInfo)) {
                                $date = date('Y-m-d', correctDate(uberDate($arrInfo), $date));
                            }
                            $arr = totime($date . ',' . uberTime($arrInfo));

                            //correctDates($dep, $arr);

                            return [
                                "DepName" => re("#^([^\n\(]+)#", $depInfo),
                                "ArrName" => re("#^([^\n\(]+)#", $arrInfo),
                                "DepCode" => re("#\(([A-Z]{3})\)#", $depInfo) ? re(1) : TRIP_CODE_UNKNOWN,
                                "ArrCode" => re("#\(([A-Z]{3})\)#", $arrInfo) ? re(1) : TRIP_CODE_UNKNOWN,
                                "DepDate" => $dep,
                                "ArrDate" => $arr,
                            ];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('(./following-sibling::tr[contains(., "Aircraft")][1]/td[contains(.,"Aircraft")][1]/following-sibling::td[string-length(normalize-space(.))>1])[1]');
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("following-sibling::tr[string-length(normalize-space(.))>1]"));

                            if (isset($it['Aircraft']) && !empty($it['Aircraft'])) {
                                return [
                                    "Duration" => re("#\n\s*Total\s+Time:\s*([^\n]+)#i", $info),
                                ];
                            }

                            return [
                                "Duration" => re("#\n\s*Total\s+Time:\s*([^\n]+)#i", $info),
                                "Aircraft" => re("#\n\s*Aircraft\s*:\s*([^\n]+?)(?:Seat|\n)#i", $info),
                            ];
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>11][1]/td[string-length(normalize-space(.))>1][4]"));

                            return [
                                "BookingClass" => detach("#\(([A-Z])\)#", $info),
                                "Cabin"        => nice(clear("#\s*Class$#i", detach("#^([^\n]+)#", $info))),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $info = text(xpath("following-sibling::tr[position()<10][contains(.,'Seat')][1]"));

                            return re("#Seat Request\s*:\s*(\d+[A-Z]+)#ix", $info);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re('#(\d+\s+hrs\s+\d+\s+min)#', node('following-sibling::tr[contains(., "hrs") and contains(., "min")][1]'));
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return node("following-sibling::tr[string-length(normalize-space(.))>10][1]/td[string-length(normalize-space(.))>=1][last()]", null, true, "#^([A-Z\d\-]+)$#");
                        },
                    ],
                ],

                "#^\w+\s+Transfer#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return node("//td[contains(., 'Reference:')]/following::td[string-length(normalize-space(.))>1][1]");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//tr[contains(., 'Name') and contains(., 'Information')]/following::tr[position()<10]/td[string-length(normalize-space(.))>2][1]");
                        $names = [];

                        foreach ($nodes as $node) {
                            if (re("#Cost|Department|This|[A-Z]{3}|,|Price|\d|Trip#", $node)) {
                                break;
                            }

                            if ($node) {
                                $names[] = $node;
                            }
                        }

                        return $names;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//tr[contains(., 'Name') and contains(., 'Information')]/following::tr[position()<10]/td[contains(., 'Frequent')][1]/following-sibling::td[string-length(normalize-space(.))>2]");
                        $names = [];

                        foreach ($nodes as $node) {
                            if (re("#Cost|Department|This|Estimated#", $node)) {
                                break;
                            }
                            $names[] = $node;
                        }

                        return implode(",", $names);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#This trip is (\w+)#ix", $this->text())
                        );
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_BUS;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $depInfo = text(xpath("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>11][1]/td[string-length(normalize-space(.))>1][2]"));
                            $arrInfo = text(xpath("following-sibling::tr[contains(.,',')][string-length(normalize-space(.))>11][1]/td[string-length(normalize-space(.))>1][3]"));

                            $date = clear("#,#", node("preceding-sibling::tr[contains(.,',')][string-length(normalize-space(.))>5][1]", null, true, "#(\d+\s+\w+,\s*\d+)$#"));
                            $dep = totime($date . ',' . uberTime($depInfo));

                            if (uberDate($arrInfo)) {
                                $date = date('Y-m-d', correctDate(uberDate($arrInfo), $date));
                            }
                            $time = uberTime($arrInfo);
                            if (!empty($time)) {
                                $arr = totime($date . ',' . $time);
                            } else {
                                $arr = MISSING_DATE;
                            }

                            // transfer info
                            $info = text(xpath("following-sibling::tr[position()<10]"));

                            if (re("#^\s*\w{3},\s*\d+\s+\w{3}\s*$#", $arrInfo)) { // clear if only date leaved
                                $arrInfo = "";
                            }

                            return [
                                "DepName"    => re("#^([^\n\(]+)#", $depInfo),
                                "DepAddress" => re("#\n\s*Pick[-\s]+up[\s-]+([^\n]+)#", $info),
                                //"ArrName" => re("#^([^\n\(]+)#", $arrInfo),
                                "ArrName" => re("#\n\s*Drop[-\s]+off[\s-]+([^\n]+)#", $info),
                                "DepCode" => re("#\(([A-Z]{3})\)#", $depInfo) ? re(1) : TRIP_CODE_UNKNOWN,
                                "ArrCode" => re("#\(([A-Z]{3})\)#", $arrInfo) ? re(1) : TRIP_CODE_UNKNOWN,
                                "DepDate" => $dep,
                                "ArrDate" => $arr,
                            ];
                        },
                    ],
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
