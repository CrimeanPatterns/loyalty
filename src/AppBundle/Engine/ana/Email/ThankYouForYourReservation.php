<?php

namespace AwardWallet\Engine\ana\Email;

class ThankYouForYourReservation extends \TAccountCheckerExtended
{
    public $reFrom = "#anaintrsv@121\.ana\.co\.jp#i";
    public $reProvider = "#ana\.co\.jp#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?ana\b#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#From\s+ANA\s+[Thank\s+you\s+for\s+your\s+reservation]#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "ana/it-13416322.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    private $date = 0;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell('Reservation Number', +1);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//text()[contains(., "Passenger Name")]/following::table[string-length(normalize-space(.)) > 1][1]/descendant::text()[normalize-space(.)]';

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(str_replace(',', '', cell('Total Price', +1)));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = strtotime(re('#(\w+\s+\d+,)\s+(\d+:\d+)#', cell('Purchase by', +1)) .
                            date('Y', $this->date));

                        if ($date > $this->date) {
                            $date = strtotime("-1 year", $date);
                        }

                        return strtotime(re(2), $date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Flight") and contains(., "Route") and not(.//tr)]/following-sibling::tr';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w+?)(\d+)#', node('./td[2]'), $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Seats'        => array_filter(nodes("//img[contains(@src, '/icon_seat.gif')]/ancestor::table[1]//text()[normalize-space(.)='" . $m[1] . $m[2] . "']/ancestor::tr[1]/td[4]", null, "#^\d+\w$#")),
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep'=>1, 'Arr'=>3] as $k=>$i) {
                                if (preg_match("#^(?<Name>.*?)\s*(?:\((?<Code>[A-Z]{3})\))?$#", node("(./td[3]//tr[1])[1]/td[{$i}]"), $m)) {
                                    $res[$k . 'Code'] = $m['Code'] ?? TRIP_CODE_UNKNOWN;
                                    $res[$k . 'Name'] = $m['Name'];
                                }
                            }

                            if ($operator = node(".//text()[starts-with(normalize-space(.), 'Operated by')]", $node, true, "#Operated by (.+)#")) {
                                $res['Operator'] = $operator;
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            if (preg_match('#^(?P<Month>\d+)/(?P<Day>\d+)#', node('./td[1]'), $m)) {
                                $dateStr = $m['Day'] . '.' . $m['Month'] . '.' . date('Y', $this->date);

                                foreach (['Dep' => 4, 'Arr' => 5] as $key => $value) {
                                    $datetimeStr = $dateStr . ', ' . re('#\d+:\d+#', node('./td[' . $value . ']'));
                                    $res[$key . 'Date'] = strtotime($datetimeStr, $this->date);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(.*):(\w)#', node('./td[6]'), $m)) {
                                return ['Cabin' => $m[1], 'BookingClass' => $m[2]];
                            } else {
                                return node('./td[6]');
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return node('./td[8]');
                        },
                    ],
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));
        $result = parent::ParsePlanEmail($parser);

        return $result;
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
