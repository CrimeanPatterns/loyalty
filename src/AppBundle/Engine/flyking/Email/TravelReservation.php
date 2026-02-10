<?php

namespace AwardWallet\Engine\flyking\Email;

class TravelReservation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Kingfisher\s+Airlines#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#itinerary@flykingfisher\.com#i";
    public $reProvider = "#flykingfisher\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "30.12.2014, 11:20";
    public $crDate = "30.12.2014, 10:17";
    public $xPath = "";
    public $mailFiles = "flyking/it-2315109.eml, flyking/it-2315110.eml, flyking/it-2315111.eml, flyking/it-2315118.eml, flyking/it-2315123.eml, flyking/it-2315125.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Booking\s+Reference\s*\(\w+\)\s*:\s*([\w\-]+)#i'),
                            re('#Confirmation\s+([\w\-]+)\s+Booking\s+Date#i')
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengersInfo = xpath('//tr[contains(., "Guest Details")]/following-sibling::tr[./td[normalize-space(.) = "Name"]]');
                        $passengers = null;
                        $this->seats = null;

                        foreach ($passengersInfo as $pi) {
                            $p = node('./td[normalize-space(.) = "Name"]/following-sibling::td[string-length(normalize-space(.)) > 1][1]', $pi);
                            $passengers[] = $p;

                            if ($s = node('./following-sibling::tr[1]/td[contains(., "Seat No")]/following-sibling::td[string-length(normalize-space(.)) > 1][1]', $pi)) {
                                $this->seats[$p] = explode(',', $s);
                            }
                        }

                        return $passengers;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//tr[contains(., "Guest Details")]/following-sibling::tr/td[normalize-space(.) = "Frequent Flyer No."]/following-sibling::td[string-length(normalize-space(.)) > 1][1]');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#ce\s+(.*)#', node('//tr[contains(., "Total Price")]')));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $segments = xpath('//tr[contains(., "Flight") and contains(., "Departure")]/following-sibling::tr[contains(., "hrs") and following-sibling::tr[contains(., "Guest Details")]]');
                        $this->flightSegmentsCount = $segments->length;

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $nodes = nodes('./td[string-length(normalize-space(.)) > 1]');
                            $nodesPlus = nodes('./following-sibling::tr[2]/td[string-length(normalize-space(.)) > 1]');

                            if (count($nodesPlus) != 2) {
                                $nodesPlus = null;
                            }

                            if (count($nodes) == 7) {
                                if (preg_match('#^([a-z]{2})(\d+)$#i', $nodes[0], $m)) {
                                    $res['AirlineName'] = $m[1];
                                    $res['FlightNumber'] = $m[2];
                                }
                                $res['Aircraft'] = $nodes[1];

                                foreach (['Dep' => 2, 'Arr' => 3] as $key => $value) {
                                    if (preg_match('#(.*)\s+\(([a-z]{3})\)#i', $nodes[$value], $m)) {
                                        $res[$key . 'Name'] = $m[1] . ($nodesPlus ? ' (' . $nodesPlus[$value - 2] . ')' : '');
                                        $res[$key . 'Code'] = $m[2];
                                    }
                                }
                                $res['Duration'] = $nodes[4];
                                $res['Cabin'] = $nodes[5];
                                $res['Status'] = $nodes[6];

                                return $res;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $nodes = nodes('./following-sibling::tr[1]/td[string-length(normalize-space(.)) > 1]');

                            if (count($nodes) == 2) {
                                return [
                                    'DepDate' => strtotime($nodes[0]),
                                    'ArrDate' => strtotime($nodes[1]),
                                ];
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (!$this->seats) {
                                return null;
                            }
                            static $flightSegmentIndex = 0;
                            $seats = null;

                            foreach ($this->seats as $s) {
                                if (count($s) == $this->flightSegmentsCount) {
                                    $seats[] = $s[$flightSegmentIndex];
                                }
                            }
                            $flightSegmentIndex++;

                            return $seats;
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
