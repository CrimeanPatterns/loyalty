<?php

namespace AwardWallet\Engine\amextravel\Email;

class ETicketPDF extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = "";
    public $rePDF = [
        ['#American Express Global Business Travel#i', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#donotreplydocprod@welcome.aexp.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#welcome.aexp.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "12.10.2015, 12:48";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "amextravel/it-3139655.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');
                    $this->plainText = $this->getDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*#i" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Booking\s+Reference\s+([\w\-]+)#i');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#TOTAL\s+FARE:\s+(.*)#'));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#FARE:\s+(.*)#'));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Tax:\s+([\d.]+\w+)#i', $this->text(), $m)) {
                            $taxes = null;

                            foreach ($m[1] as $t) {
                                $taxes += $t;
                            }

                            return $taxes;
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#DATE\s+OF\s+ISSUE\s*:\s+(\d+\s+\w+\s+\d+)#i'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('//tr[contains(., "FROM") and contains(., "FLIGHT")]/following-sibling::tr[./following-sibling::tr[contains(., "ENDORSEMENTS")] and not(contains(., "BAGS:"))]');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $result = [];
                            $nodes = array_values(array_filter(nodes('./td')));

                            foreach (['Dep' => 0, 'Arr' => 1] as $key => $index) {
                                if (preg_match('#^(.*)\s+\((\w{3})\)$#i', $nodes[$index], $m)) {
                                    $result[$key . 'Name'] = $m[1];
                                    $result[$key . 'Code'] = $m[2];
                                }
                            }

                            if (preg_match('#^(\w{2})\s+(\d+)$#i', $nodes[2], $m)) {
                                $result['AirlineName'] = $m[1];
                                $result['FlightNumber'] = $m[2];
                            }
                            $result['BookingClass'] = re('#^\w$#', $nodes[3]);

                            if (preg_match('#^(\d+)-(\w+)$#i', $nodes[4], $m1) and preg_match('#^(\d{2})(\d{2})$#', $nodes[5], $m2)) {
                                $s = $m1[1] . ' ' . $m1[2] . ' ' . $this->getEmailYear() . ', ' . $m2[1] . ':' . $m2[2];
                                $result['DepDate'] = strtotime($s);
                                $result['ArrDate'] = MISSING_DATE;
                            }

                            return $result;
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
