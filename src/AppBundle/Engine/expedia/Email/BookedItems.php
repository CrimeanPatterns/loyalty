<?php

namespace AwardWallet\Engine\expedia\Email;

class BookedItems extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+choosing\s+Expedia\.co\.uk\s+-\s+below\s+are\s+the\s+details\s+of\s+your\s+booking|Thank you for booking your trip with AARP Travel Center powered by Expedia|This e-mail contains a copy of an AARP Travel Center powered by Expedia|Expedia itinerary number|Cet e-mail contient une copie d\'un voyage Expedia.be|This e-mail contains a copy of an Expedia.co.uk#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Your\s+Expedia\s+Booking#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#travel@support\.expedia\.co\.uk#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#support\.expedia\.co\.uk#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, fr";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "13.07.2015, 11:00";
    public $crDate = "10.07.2015, 09:17";
    public $xPath = "";
    public $mailFiles = "expedia/it-1780570.eml, expedia/it-1898081.eml, expedia/it-1899073.eml, expedia/it-1914493.eml, expedia/it-2075687.eml, expedia/it-2075688.eml, expedia/it-2075690.eml, expedia/it-2075713.eml, expedia/it-2889291.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!preg_match($this->rePlain[0][0], $text)) {
                        // Ignore emails of other types
                        return null;
                    }

                    return [$text];
                },

                "#Pick\s+up#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(.*)\s+confirmation\s+number\s*:\s+([\w\-]+)#', $text, $m)) {
                            return [
                                'RentalCompany' => nice($m[1]),
                                'Number'        => $m[2],
                            ];
                        }
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $subj = nice(re('#Location:\s+(.*?)\s+Hours\s+of\s+operation#si'));
                        $subj = preg_replace('#shuttle\s+to\s+counter\s+and\s+car;\s+may\s+need\s+to\s+call\s+for\s+shuttle,\s+#i', '', $subj);

                        return [
                            'PickupLocation'  => $subj,
                            'DropoffLocation' => $subj,
                        ];
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $hours = re('#Hours\s+of\s+operation:\s+(.*)#');

                        foreach (['Pickup' => 'Pick up', 'Dropoff' => 'Drop off'] as $key => $value) {
                            $subj = cell($value . ':');

                            if (preg_match('#:\s+\w+\s+(.*)\s+(\d+:.*)#i', $subj, $m)) {
                                $res[$key . 'Datetime'] = strtotime(str_replace('/', '.', $m[1] . ', ' . $m[2]));

                                if (preg_match('#' . date('n/j/Y', strtotime($m[1])) . ':\s+(\d+:\d+\s+-\s+\d+:\d+|Open\s+24\s+Hours)#', $hours, $m)) {
                                    $res[$key . 'Hours'] = $m[1];
                                }
                            }
                        }

                        return $res;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Telephone:\s+([\d\s]+)#'));
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re('#(.*)\s+car:#', cell('Driver:', +1));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#:\s+(.*)#', cell('Driver:'));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Car rental total', +1);

                        if ($subj) {
                            return total($subj);
                        }
                    },
                ],

                "#Check\s+in:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        //$conf = node("//*[contains(text(), 'Expedia booking ID:')]");
                        return orval(
                        //	re('/:\s*([\w-]+)\s*/', $conf),
                            re('#Expedia\s+booking\s+ID\s*:\s*([\w-]+)#'),
                            re('#powered\s+by\s+Expedia\s+itinerary\s+number\s*:\s*([\w\-]+)#i')
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(normalize-space(text()), 'Hotel summary')]/following::b[1]");

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        //$date = node("//*[contains(normalize-space(text()), 'Check in:')]/following::span[1]");
                        //$date = nice($date);
                        $res = null;

                        foreach (['CheckIn' => 'Check in', 'CheckOut' => 'Check out'] as $key => $value) {
                            $dateStr = re('#' . $value . '\s*:\s+\w+\s+(\w+-\d+-\d+)#');
                            $res[$key . 'Date'] = \DateTime::createFromFormat('M-j-Y', $dateStr)->setTime(0, 0)->getTimestamp();
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = node("//*[contains(normalize-space(text()), 'Check in:')]/preceding::td[2]");

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#\s*Tel\s*:\s+(.*)\s+Fax#');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re('#\s*Fax\s*:\s+(.*)#');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[contains(normalize-space(text()), 'Reserved for:')]/b[1]");

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(normalize-space(text()), 'Reserved for:')]/following::td[1]");

                        return re('/(\d+)\s*adults/i', $info);
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $rate = node("//*[contains(normalize-space(text()), 'Reserved for:')]/following-sibling::td[3]");

                        return re('/\S+\s*per\s*night/', $rate);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cancel = node("//*[contains(normalize-space(text()), 'Cancellation or Change Policy')]/following::tr[1]");

                        return nice($cancel);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $type = node("//*[contains(normalize-space(text()), 'Reserved for:')]/following-sibling::td[2]/text()[1]");

                        return nice($type);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $desc = node("//*[contains(normalize-space(text()), 'Reserved for:')]/following-sibling::td[2]/*[1]");

                        return preg_replace('#Includes\s*:\s*#i', '', nice($desc));
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Amount charged for hotel reservation', +1), 'Total');
                    },
                ],

                "#Duration|Départ#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#confirmation\s*code:\s*([\w-]+)#i'),
                            re('#Code\s*de\s*confirmation\s*(?:.+?)\s*:\s*([\w-]+)#is'),
                            re('#Expedia\s+booking\s+ID:\s*([\w-]+)#i')
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengers = orval(
                            nodes("//tr[not(.//tr) and .//td[2 and (contains(., 'Adult') or contains(., 'Child'))]]/ancestor-or-self::tr[1]/td[1]"),
                            [node("//*[contains(text(), 'Contact principal :')]/parent::*[1]/text()[1]")]
                        );

                        return nice($passengers);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $tot = orval(
                            cell('Print a receipt', +1),
                            node("//*[contains(text(), 'Taxes et frais')]/following::tr[2]/td[last()]")
                        );

                        return total($tot);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $tax = orval(
                            cell('Taxes & Fees', +1),
                            cell('Taxes et frais', +1)
                        );

                        return cost($tax);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('/Your\s*booking\s*is\s*now\s*confirmed/i') || re('/E\-Ticket\s+purchase\s+has\s+been\s+confirmed\b/')) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//span[contains(text(), 'Duration:')]/ancestor-or-self::tr[2] | //*[contains(normalize-space(text()), 'Distance totale')]/ancestor::tr[2]/preceding-sibling::tr[6]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('/(\w+)\s*Vol\s*:\s*(\d+)/is', node('.'), $ms)) {
                                return [
                                    'FlightNumber' => $ms[2],
                                    'AirlineName'  => $ms[1],
                                ];
                            } else {
                                return re("#Flight:\s*(\d+)#i");
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $date = node('./preceding-sibling::tr[string-length(normalize-space(.)) > 1][1]');
                            $date = re('#\d+-\w+-\d+#iu', $date);
                            $date = en($date);

                            $arr = ['Dep' => ['Depart', 'Départ'], 'Arr' => ['Arrive', 'Arrivée']];

                            foreach ($arr as $key => $value) {
                                if (re('#\((\w{3})\)\s*(?:' . implode('|', $value) . ')\s*(\d+:\d+)\s*(am|pm)?#i')) {
                                    $res[$key . 'Code'] = re(1);
                                    $timeNoon = [
                                        'format' => re(3) ? " a" : "",
                                        'val'    => re(3) ? " " . re(3) : "",
                                    ];
                                    $dt = \DateTime::createFromFormat('d-M-y, H:i' . $timeNoon['format'], $date . ', ' . re(2) . $timeNoon['val']);

                                    if ($dt) {
                                        $res[$key . 'Date'] = $dt->getTimestamp();
                                    }
                                }
                            }

                            if (isset($res['DepDate']) and isset($res['ArrDate'])) {
                                correctDates($res['DepDate'], $res['ArrDate']);
                            }

                            return $res;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $info = node('td[5]');

                            return re('/(\w+)\s+/', $info);
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            $info = node('td[4]');

                            if ($val = re('/([\d,.]+)\s*mi[^n]/i', $info)) {
                                return floatval(preg_replace("#[,.](\d{3})\b#", "\\1", $val));
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node("./following-sibling::tr[2]");

                            $regex1 = '/\d*\s*(?P<class>.+?)\s*[(]\s*(?P<seat>.+?)\s*[)]\s*,\s*(?P<meal>.+?)\s*,\s*(?P<air>.+)\s*/';
                            $regex2 = '/\d*\s*(?P<class>.+?)\s*[(].+?[)]\s*,\s*(?P<meal>.+?)\s*,\s*(?P<air>.+)/i';
                            $regex3 = '/\d*\s*(?<class>.+?)\s*[(]\s*(?<seat>.+?)\s*[)]\s*,\s*(?<air>.+?)\s*,\s*\d+%\s+on\s+time/';

                            if (preg_match($regex3, $info, $m)) {
                                return [
                                    'Cabin'    => nice($m['class']),
                                    'Seats'    => !re('/enregistrement|assignment/i', $m['seat']) ? nice($m['seat']) : null,
                                    'Aircraft' => nice($m['air']),
                                ];
                            } elseif (preg_match($regex1, $info, $ms)) {
                                return [
                                    'Cabin'    => nice($ms['class']),
                                    'Seats'    => !re('/enregistrement|assignment/i', $ms['seat']) ? nice($ms['seat']) : null,
                                    'Meal'     => nice($ms['meal']),
                                    'Aircraft' => nice($ms['air']),
                                ];
                            } elseif (preg_match($regex2, $info, $ms)) {
                                return [
                                    'Cabin'    => nice($ms['class']),
                                    'Meal'     => nice($ms['meal']),
                                    'Aircraft' => nice($ms['air']),
                                ];
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return nice(orval(re("#Durée\s*:\s*(.+?min)#is"), re('/Duration:\s*(.+)/i')));
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
        return ["en", "fr"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
