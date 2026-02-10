<?php

namespace AwardWallet\Engine\expedia\Email;

class TravelConfirmation extends \TAccountCheckerExtended
{
    public $mailFiles = "expedia/it-15.eml, expedia/it-1558364.eml, expedia/it-1568288.eml, expedia/it-1568707.eml, expedia/it-1569073.eml, expedia/it-1596614.eml, expedia/it-1623160.eml, expedia/it-2144612.eml, expedia/it-2144653.eml, expedia/it-2144716.eml, expedia/it-2144719.eml, expedia/it-2144811.eml, expedia/it-2144891.eml, expedia/it-2144900.eml, expedia/it-2144951.eml, expedia/it-2145178.eml, expedia/it-2145434.eml, expedia/it-2145477.eml, expedia/it-2145712.eml, expedia/it-2145846.eml, expedia/it-2146564.eml, expedia/it-2146596.eml, expedia/it-2146597.eml, expedia/it-2147198.eml, expedia/it-2147211.eml, expedia/it-2189586.eml, expedia/it-2191172.eml, expedia/it-2193141.eml, expedia/it-2193830.eml, expedia/it-40.eml, expedia/it-42693551.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && (
                preg_match('/Expedia travel confirmation/', $headers['subject'])
                || preg_match('/Confirmación de viaje de Expedia/', $headers['subject']));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//*[contains(normalize-space(text()), "Thank you for booking your trip with Expedia") '
                . 'or contains(normalize-space(text()), "Gracias por reservar tu viaje con Expedia") or contains(normalize-space(.), "Thank you for choosing Expedia.com")]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@expediamail.com') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("
						//*[contains(., 'Flight:') and not(.//td)]/ancestor::tr[position()<=2]/following-sibling::tr[contains(., ':')][1] |
						//*[(contains(., 'Hotel:') or contains(., 'hotel:')) and not(.//td)]/ancestor::tr[position()<=2]/following-sibling::tr[contains(., ':')][1] |
						//*[(contains(., 'Car:') or contains(., 'Auto:')) and not(.//td)]/ancestor::tr[position()<=2]/following-sibling::tr[contains(., ':')][not(contains(., 'Activities'))][1] |
						//*[contains(., 'Activities:') and not(.//td)]/ancestor::tr[position()<=2]/following-sibling::tr[contains(., '/')][contains(.,'ubmarine') or contains(.,'ransfer')][1]
					");
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return $this->correctItinerary($it, true);
                },

                "#(?:\d+/\d+/\d+|\d+-\w+-\d+)\s+\d+:\d+\s*(?:am|pm)?\s+-\s+\d+:\d+\s*(?:am|pm)?#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Airline\s+Confirmation\s+Code:\s+([\w\-]+)#', node('./preceding-sibling::tr[contains(., "Flight:")]')),
                            re('#Itinerary\s+number\s*:\s+([A-Z\d\-]+)#i', $this->text())
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = explode(', ', re('#Traveler\s+names?:\s+(.*?)\s+(?:Airline|Confirmation|Total|Package)#s', $this->text()));

                        if (empty($names)) {
                            $names = explode(', ', $this->http->FindSingleNode("./preceding-sibling::tr[normalize-space()!=''][1][contains(.,'Traveler name')]"));
                        }

                        foreach ($names as &$name) {
                            $name = niceName($name);
                        }

                        return $names;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(orval(
                            re('#Airfare\s+total:\s+(\S+)#', node('./preceding-sibling::tr[contains(., "Flight:")]')),
                            re("#\n\s*Package total\s*:\s*([^\n]+)#ix", $this->text())
                        ));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Total\s+Ticket\s+Cost:\s+(\S+)#i', node('preceding-sibling::tr[contains(., "Flight:")]')));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re('#Taxes\s+&\s+Fees:\s+(\S+)#', node('./preceding-sibling::tr[contains(., "Flight:")]')),
                            re("#\n\s*Taxes & service fees\s*:\s*([^\n]+)#ix", $this->text())
                        ));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return nice(orval(
                            re("#purchase has (not yet been confirmed)#ix", $this->text()),
                            re("#you just ([^\n.;,.]+)#ix", $this->text())
                        ));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $subj = str_replace('/', '.', re('#Date\s+of\s+Booking:\s+(\d+/\d+/\d+)#', $this->text()));

                        return ($d = strtotime($subj)) ? $d : null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.//tr[@valign = "top"] | .//tr[@style="height:4.5pt" or @style="height:2.25pt"]/following-sibling::tr[1][contains(.,":")]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (re('#^(.*)\s+(\d+)#', node('./td[4]'))) {
                                return ['AirlineName' => re(1), 'FlightNumber' => re(2)];
                            } elseif (re('#^(.*?)(?:\s+Operated|$)#', node('./td[4]'))) {
                                return ['AirlineName' => re(1), 'FlightNumber' => FLIGHT_NUMBER_UNKNOWN];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            re("#(?:^|\n)\s*(.*?)\s*\(([^\)]+)\)\s+to\s+(.*?)\s*\(([^\)]+)\)#s");

                            return [
                                'DepName' => str_replace("\n", ' ', preg_match("#^[A-Z]{3}$#", re(2), $m) ? re(1) : re(1) . ', ' . re(2)),
                                'DepCode' => preg_match("#^[A-Z]{3}$#", re(2), $m) ? re(2) : TRIP_CODE_UNKNOWN,
                                'ArrName' => str_replace("\n", ' ', preg_match("#^[A-Z]{3}$#", re(4), $m) ? re(3) : re(3) . ', ' . re(4)),
                                'ArrCode' => preg_match("#^[A-Z]{3}$#", re(4), $m) ? re(4) : TRIP_CODE_UNKNOWN,
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $subj = node('./td[2]') . ' ' . node('./td[3]');
                            $regex = '#';
                            $regex .= '(?P<Day>\d+)[\-/](?P<Month>\w+)[\-/](?P<Year>\d+)\s+';
                            $regex .= '(?P<DepDate>\d+:\d+\s*(?:am|pm)?)\s+-\s+';
                            $regex .= '(?P<ArrDate>\d+:\d+\s*(?:am|pm)?)\s*';
                            $regex .= '(?P<DayShift>[\-+]\d+\s+day)?';
                            $regex .= '#i';

                            if (preg_match($regex, $subj, $m)) {
                                $delim = (is_numeric($m['Month'])) ? '.' : ' ';

                                if (!(preg_match('#Thank\s+you\s+for\s+booking\s+your\s+trip\s+with\s+Expedia\.ca#i', $this->text()) or strlen($m['Year']) == 4)) {
                                    [$m['Day'], $m['Month']] = [$m['Month'], $m['Day']];
                                }

                                $dateStr = $m['Day'] . $delim . $m['Month'] . $delim . ((strlen($m['Year']) == 2) ? '20' . $m['Year'] : $m['Year']);

                                if (!totime($dateStr)) {
                                    $dateStr = $m['Month'] . $delim . $m['Day'] . $delim . ((strlen($m['Year']) == 2) ? '20' . $m['Year'] : $m['Year']);
                                }

                                foreach (['Dep', 'Arr'] as $pref) {
                                    $date = strtotime($dateStr . ', ' . $m[$pref . 'Date']);
                                    $m[$pref . 'Date'] = $date;
                                }

                                if (isset($m['DayShift'])) {
                                    $m['ArrDate'] = strtotime($m['DayShift'], $m['ArrDate']);
                                }
                                copyArrayValues($res, $m, ['DepDate', 'ArrDate']);
                            }

                            return $res;
                        },
                    ],
                ],

                "#Check\s+in#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Hotel confirmation\s*:\s*([A-Z\d-]+)#xi', xpath("ancestor::table[contains(., 'otel:')][1]")),
                            re("#Código de confirmación[:\s]+([A-Z\d-]{4,})#x"),
                            re("#Número de itinerario de Expedia[:\s]+([A-Z\d-]{4,})#x", $this->text()),
                            re("#\n\s*Itinerary number:\s*([\d\w\-]+)#", $this->text())
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $address = re("#(.*)\s+(?:Check\s+in:|Entrada)#ims");
                        re("#^([^\n]+)\s+(.*?)\s+Phone:\s*([\d\(\)\- ]+)#ms", $address);

                        if (!re(1)) { // no address
                            re("#^(.*?)(\s*)(?:Phone:\s*([\d\(\)\- ]+))?\s*$#ms", $address);
                        }

                        return [
                            'HotelName' => re(1),
                            'Address'   => orval(nice(glue(re(2))), re(1)),
                            'Phone'     => re(3),
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        // echo text($text);
                        // TODO: Probably similar format fixes dates for new versions of the letters. While it monitored.
                        // it-2144891.eml, it-2145178.eml,
                        $time = orval(
                            re("#Check in:\s*[[:alpha:]]*\s*(\d+/\d+/\d+)#i"),
                            re("#Check in:\s*(\d+/\d+/\d+|\d+-[^\d\s]+-\d{2}|\d+\s+\w+\s+\d{4})#i")
                        );

                        return totime($this->normalizeDate($time));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $time = orval(
                            re("#Check out:\s*[[:alpha:]]*\s*(\d+/\d+/\d+)#"),
                            re("#Check out:\s*(\d+/\d+/\d+|\d+-[^\d\s]+-\d{2}|\d+\s+\w+\s+\d{4})#i")
                        );

                        return totime($this->normalizeDate($time));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $names = [];

                        re('#\n\s*Room\s+reservation\s*:\s*([^\n]*?)\s*\-\s*#i', function ($m) use (&$names) {
                            $names[trim($m[1])] = 1;
                        }, text(xpath("ancestor::table[contains(., 'otel:')][1]")));

                        return array_keys($names);
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s*adult#i', xpath("ancestor::table[contains(., 'otel:')][1]"));
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#(\d+)\s*room#i', xpath("ancestor::table[contains(., 'otel:')][1]"));
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        // get special request info
                        $hotel = $this->cache('HotelName');
                        $info = re("#Special requests\s+.*?\n\s*(?:\d+|Hotel)\s*:\s*$hotel\s+(.*?)\s+View\s+your#ims", $this->text());

                        return [
                            'RoomType'            => re("#\n\s*Room type:\s*([^\n]+)#i", $info),
                            'RoomTypeDescription' => nice(re("#\s*Room\s*:\s*([^\n]+)#i", $info) . ', ' . re("#\n\s*Non-smoking/Smoking:\s*([^\n]+)#i", $info)),
                        ];
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return node("ancestor::table[contains(.,'total:')]//text()[contains(normalize-space(.), 'Total Room Cost')]/ancestor-or-self::td[1]/following-sibling::td[last()]", $node, true, "#([\d.,]+)#");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            cost(node("ancestor::table[contains(.,'total:')][1]//*[contains(text(), 'Taxes & fees') or contains(text(), 'Taxes & Service Fees')]/ancestor-or-self::td[1]/following-sibling::td[last()]", $node, true, "#([\d.,]+)#")),
                            re("#\n\s*Taxes & service fees\s*:\s*([^\n]+)#ix", $this->text())
                        ));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(orval(
                            node("ancestor::table[contains(.,'total:')][1]//*[contains(text(), 'Lodging total:')]/ancestor-or-self::td[1]/following-sibling::td[last()]", $node, true, "#([\d.,]+)$#"),
                            re("#\n\s*Package total\s*:\s*([^\n]+)#ix", $this->text())
                        ), 'Total');
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $currency = (isset($it['Currency']) && $it['Currency']) ? $it['Currency'] : re("#Lodging total[:\s]+([^\d]+)#ix", xpath("ancestor::table[contains(.,'total:')][1]"));

                        return preg_replace(['/^\$$/', '/^C\$$/i'], ['USD', 'CAD'], $currency);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#purchase has (not yet been confirmed)#ix", $this->text()),
                            re("#you just ([^\n;,.]+)#ix", $this->text())
                        );
                    },
                ],

                "#Pick\s+up|Fecha#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Número de itinerario|Itinerary\s+number):\s+([\w\-]+)#ix', $this->text());
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[
							contains(text(), 'Pick up:') or 
							contains(text(), 'Pick Up:')
						]/ancestor-or-self::td[1]"));

                        $value = nice(re("#\n\s*([^\n]+)$#", $info));
                        $alt = re("#^Car[:\s]+[^,]+,\s*(.+?)\s*(?:Driver|$)#", xpath("preceding-sibling::tr[contains(., 'Car:')]"));

                        return orval(
                            (!$value || re("#\d+:\d+\s*[APM]+#i", $value)) ? $alt : $value,
                            re("#\n\s*Fecha\s+de\s+entrega.*?\n\s*([^\n]+)$#is")
                        );
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(orval(
                            uberDateTime(re("#\n\s*Pick[\s-]+up\s*:\s*([^\n]+\s+[^\n]+)#i")),
                            clear("#/#", uberDateTime(re("#\n\s*Fecha de entrega del auto\s*:\s*([^\n]*?\s+\d+:\d+\s*[APM.]+)#ix")), '.')
                        ));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[
							contains(text(), 'Drop off:') or
							contains(text(), 'Drop Off:')
						]/ancestor-or-self::td[1]"));

                        $value = nice(re("#\n\s*([^\n]+)$#", $info));

                        if (re("#\n\s*Devolución.*?\n\s*([^\n]+)$#is")) {
                            return re(1);
                        }

                        return (!$value || re("#\d+:\d+\s*[APM]+#i", $value)) ? $it['PickupLocation'] : $value;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(orval(
                            // Drop Off: Tue 11/04/14 12:00 PM
                            uberDateTime(re("#Drop[\s-]+off\s*:\s*(.+)#is")),
                            en(uberDateTime(re("#Devolución\s*:\s*([^\n]*?\s+\d+:\d+\s*[APM.]+)#ix")))
                        ));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return node("preceding-sibling::tr[contains(., 'Car:')]", $node, true, "#^Car[:\s]+([^,]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#^([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#Driver[:\s]+([^\n]+)#", xpath("preceding-sibling::tr[contains(., 'Driver:')][1]"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(orval(
                            re("#\n\s*(?:Precio total|Car total)[\s:]+([^\n]+)#ix", xpath("ancestor::table[contains(., 'Car:') or contains(., 'Auto:')][1]")),
                            re("#\n\s*Package total\s*:\s*([^\n]+)#ix", $this->text())
                        ));
                    /*$n = xpath('./preceding-sibling::tr[contains(., "Car:")]');
                    if ($n->length > 0) {
                        $subj = $n->item(0);
                        $res['RentalCompany'] = nice(re('#Car:\s+([^,]+)#i', $subj));
                        $res['RenterName'] = re('#Driver:\s+(.*)#i', $subj);
                        $total = total(re('#Car\s+total:\s+(.*)#i', $subj));
                        $res = array_merge($res, $total);
                        $res['TotalTaxAmount'] = cost(re('#Taxes\s+&\s+Fees:\s+(.*)#i', $subj));
                        return $res;
                    }*/
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#\n\s*Taxes & Fees[\s:]+([^\n]+)#", xpath("ancestor::table[contains(., 'Car:')][1]")),
                            re("#\n\s*Taxes & service fees\s*:\s*([^\n]+)#ix", $this->text())
                        ));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return nice(orval(
                            re("#purchase has (not yet been confirmed)#ix", $this->text()),
                            re("#you just ([^\n.,;]+)#ix", $this->text()),
                            re("#(?:\n|^)([^\s]+) de viaje#ix", $this->text())
                        ));
                    },
                ],

                "#transfer|submarin#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Itinerary\s+number\s*:\s*([A-Z\d\-]+)#i", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Contact name\s*:\s*([^\n]+)#ix", xpath("ancestor::table[contains(., 'Activities:')][1]")),
                            explode(', ', re('#Traveler\s+names?:\s+(.*)#', $this->text()))
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Activities Total\s*:\s*([^\n]+)#ix", xpath("ancestor::table[contains(., 'Activities:')][1]")));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#purchase has (not yet been confirmed)#ix", $this->text()),
                            re("#you just ([^\n.;,.]+)#ix", $this->text())
                        );
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_BUS; // or "water" bus
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//*[contains(normalize-space(text()), 'View Vouchers')][1]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $dep = re("#(.*?)\s+to\s+([^\-:]+)#", node('td[1]'));

                            if (empty($dep)) {
                                $dep = re("#(.*?)\s*\-\s*([^\-:]+)#", node('td[1]'));
                            }

                            return [
                                'DepName' => $dep,
                                'ArrName' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            if (re("#^(.*?)\s+\-+\s+(.+)#", node('td[2]'))) {
                                $dep = totime(re(1));
                                $arr = totime(re(2));
                            } else {
                                $dep = totime(node('td[2]'));
                                $arr = MISSING_DATE;
                            }

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            return re("#Activities:\s*([^\n]*?)\s*(?:Contact name:|\n)#", xpath("ancestor::table[contains(.,'Activities:')][1]//tr[1]"));
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
        return ["en", "es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function normalizeDate($str)
    {
        $str = str_replace("​", "", $str);

        if (preg_match("#(?:^|\D)(\d+[./-]\d+[./-]\d+)(?:$|\D)#", $str, $m)) {
            $str = $this->normalizeDecDate($m[1]);
            $str = preg_replace("#^(\d{1,2})\.(\d{1,2})\.(\d{2})$#", "$1.$2.20$3", $str);
        } else {
            $in = [
                "#^(\d+)-([^\d\s]+)-(\d{2})$#",
            ];
            $out = [
                "$1 $2 20$3",
            ];

            $str = preg_replace($in, $out, $str);
        }

        return $str;
    }

    private function normalizeDecDate($str)
    {
        if (!isset($this->decPattern)) {
            $cur = explode('|', str_replace(['.', '/', '-'], ['|', '|', '|'], $str));

            // check count dates in this text
            preg_match_all("#(?:^|\D)(\d+[./-]\d+[./-]\d+)(?:$|\D)#", $this->http->FindSingleNode("(//text()[contains(., '{$str}')])[1]"), $m);

            if (count($m[1]) == 2) {
                $next = $m[1][1];
            } else {
                // find next decDate text
                $transfrom = '1234567890./-';
                $transto = 'dddddddddd|||';
                $next = $this->http->FindSingleNode("(//text()[contains(., '{$str}')])[1]/following::text()[not(contains(translate(normalize-space(.), './-', '|||'), '{$cur[0]}|{$cur[1]}'))][
					contains(translate(normalize-space(.), '{$transfrom}', '{$transto}'), 'd|d|dd') or
					contains(translate(normalize-space(.), '{$transfrom}', '{$transto}'), 'd|dd|dd')
				][1]", null, true, "#(?:^|\D)(\d+[./-]\d+[./-]\d+)(?:$|\D)#");
            }

            if (empty($next)) {
                return $str;
            }
            $next = explode('|', str_replace(['.', '/', '-'], ['|', '|', '|'], $next));

            // compare dates
            $diff = [];

            for ($i = 0; $i < 3; $i++) {
                // if this number len 4 then year
                if (strlen($cur[$i]) == 4) {
                    $year = $i;

                    continue;
                }
                $diff[str_pad(abs($cur[$i] - $next[$i]), 2, '0', STR_PAD_LEFT) . (3 - $i)] = $i;
            }
            krsort($diff);

            // set pattern by diff
            $day = current($diff);
            $month = next($diff);
            // if exact year not found
            if (!isset($year) && count($diff) == 3) {
                $year = next($diff);
            }

            $this->decPattern = [$day, $month, $year];
        }

        return $this->dd2p($str);
    }

    private function dd2p($str)
    {
        $arr = explode('|', str_replace(['.', '/', '-'], ['|', '|', '|'], $str));
        $date = [];

        foreach ($this->decPattern as $key) {
            $date[] = $arr[$key];
        }

        return implode(".", $date);
    }

    // remove Currency, TotalCharge, Total, Tax, Taxes, TotalTaxAmount, GuestNames, RenterName
    private function correctItinerary($it, $uniteSegments = false, $uniteFlights = false)
    {
        if ($uniteSegments) {
            $it = uniteAirSegments($it);
        }

        // check if mixed
        $mixed = count($it) > 1 ? true : false;

        if ($mixed) {
            // check number of travelers
            $names = [];

            foreach ($it as $i => &$cur) {
                array_walk($cur, function ($value, $key) use (&$names) {
                    if (in_array($key, ['Passengers', 'GuestNames', 'RenterName'])) {
                        if (is_array($value)) {
                            foreach ($value as $name) {
                                $names[niceName(nice($name))] = 1;
                            }
                        } else {
                            $names[niceName(nice($value))] = 1;
                        }
                    }
                });
            }

            $numTravelers = count(array_keys($names));

            foreach ($it as $i => &$cur) {
                array_walk($it, function (&$cur, $key) use ($numTravelers) {
                    if ($numTravelers > 1) {
                        unset($cur['GuestNames']);
                        unset($cur['RenterName']);
                    }

                    unset($cur['Currency']);
                    unset($cur['BaseFare']);
                    unset($cur['TotalCharge']);
                    unset($cur['Total']);
                    unset($cur['TotalTaxAmount']);
                    unset($cur['Taxes']);
                    unset($cur['Tax']);
                }, $cur);
            }
        }

        return $uniteFlights ? uniteFlights($it) : $it;
    }
}
