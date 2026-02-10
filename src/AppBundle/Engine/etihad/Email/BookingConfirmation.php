<?php

namespace AwardWallet\Engine\etihad\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#bookings@etihadairways\.com#i";
    public $reProvider = "#etihadairways\.com#i";
    public $rePlain = "#Thank\s+you\s+for\s+flying\s+with\s+Etihad\s+Airways.*Your\s+booking\s+is\s+confirmed#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "etihad/it-1732855.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Booking\s+reference:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "FREQUENT FLYER") and not(.//tr)]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.)) > 1]//tr[string-length(normalize-space(.)) > 1]/td[1]';

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Total price', +1);

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Flights', +1));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Tax/Fee/Charge', +1));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "DEPART") and contains(., "ARRIVE") and not(.//tr) and not(contains(., ":"))]/ancestor::tr[1]/following-sibling::tr[contains(., ":")]//tr[string-length(normalize-space(.)) > 1 and count(.//td) > 3]';

                        return $this->http->XPath->query($xpath);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[2]');

                            if (preg_match('#(\w+?)(\d+)#', $subj, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            static $dateStr = null;
                            static $prevDateStr = null;
                            static $dayShift = null;

                            if ($dateStr) {
                                $prevDateStr = $dateStr;
                            }

                            $res = null;

                            $xpath = './ancestor::tr/preceding-sibling::tr//tr[(contains(., "Outbound flight") or contains(., "Return flight")) and not(.//tr)]/ancestor::tr[1]/td[2]';
                            $subj = end(nodes($xpath));

                            if (preg_match('#(\d+\s+\w+\s+\d+)\s+travelling\s+(.*)#', $subj, $m)) {
                                $dateStr = $m[1];
                                $res['Cabin'] = $m[2];
                            }

                            if ($dateStr != $prevDateStr) {
                                $dayShift = null;
                            }

                            foreach (['Dep' => 3, 'Arr' => 4] as $key => $value) {
                                $subj = node('./td[' . $value . ']');

                                if (preg_match('#(\d+:\d+)\s+(.*)\s+\((\w+)\)#', $subj, $m)) {
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $m[1]);
                                    $res[$key . 'Name'] = $m[2];
                                    $res[$key . 'Code'] = $m[3];
                                }

                                if ($key == 'Arr' and preg_match('#([\+\-]\d+\s+day)#', node('./td[5]'), $m)) {
                                    $dayShift = $m[1];
                                }

                                if ($dayShift) {
                                    $res[$key . 'Date'] = strtotime($dayShift, $res[$key . 'Date']);
                                }
                            }

                            return $res;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re('#Subclass:\s+(\w)#', node('./td[2]'));
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
}
