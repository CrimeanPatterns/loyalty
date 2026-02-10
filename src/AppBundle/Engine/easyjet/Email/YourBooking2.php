<?php

namespace AwardWallet\Engine\easyjet\Email;

class YourBooking2 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?easyjet#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#confirmations@holidays\.easyjet\.com#i";
    public $reProvider = "#easyjet\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->bookingNo = re('#Thank\s+you\s+for\s+your\s+booking\s*:\s+([\w\-]+)#');
                    $this->travellers = array_values(array_filter(explode("\n", re('#Passenger\s+List\s+(.*)#s', cell('Passenger List', 0, 0, '//text()')))));
                    $this->totalStr = total(node('//td[normalize-space(.) = "Total"]/following-sibling::*[1]'));
                    $reservations = null;
                    $airReservations = xpath('//text()[contains(., "Dep:")]/ancestor::td[1]/following-sibling::td[1]');

                    foreach ($airReservations as $r) {
                        $reservations[] = $r;
                    }
                    $hotelReservations = xpath('//img[contains(@src, "starrating.png")]/ancestor::td[1]');

                    foreach ($hotelReservations as $r) {
                        $reservations[] = $r;
                    }

                    return $reservations;
                },

                "#Adults?.*Room#s" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return $this->bookingNo;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= '(?P<Guests>\d+)\s+Adults?,\s+';
                        $r .= '(?P<Rooms>\d+)\s+Room\s+';
                        $r .= '(?P<HotelName>.*)\s+';
                        $r .= '(?P<Address>(?s).*?)\s+';
                        $r .= '(?P<CheckInDate>\d+\w+\s+\w+\s+\d+).*(?P<Nights>\d+)\s+nights\s+';
                        $r .= '\d+\s+x\s+(?P<RoomType>.*)';
                        $r .= '#iu';

                        if (preg_match($r, $node->nodeValue, $m)) {
                            $res = null;
                            $keys = ['Guests', 'Rooms', 'HotelName', 'Address', 'CheckInDate', 'RoomType'];

                            foreach ($keys as $k) {
                                $res[$k] = $m[$k];
                            }
                            $res['CheckInDate'] = strtotime($res['CheckInDate']);

                            if ($res['CheckInDate']) {
                                $res['CheckOutDate'] = strtotime('+' . $m['Nights'] . ' days', $res['CheckInDate']);
                            }
                            $res['Address'] = nice($res['Address'], ',');
                            $res['HotelName'] = nice($res['HotelName'], ',');

                            return $res;
                        }
                    },
                ],

                "#Flight\s+Number#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->bookingNo;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travellers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $r = '#';
                            $r .= '(.*)\s+>\s+(.*)\s+';
                            $r .= '(\d+:.*?)\s+-\s+(.*)\s+';
                            $r .= '(\d+:.*?)\s+-\s+(.*\d{4})\s+';
                            $r .= '(?:(?s).*?)';
                            $r .= 'Flight\s+Number\s+(\d+)';
                            $r .= '#';

                            if (preg_match($r, $node->nodeValue, $m)) {
                                return [
                                    'DepCode'      => TRIP_CODE_UNKNOWN,
                                    'ArrCode'      => TRIP_CODE_UNKNOWN,
                                    'DepName'      => nice($m[1]),
                                    'ArrName'      => nice($m[2]),
                                    'DepDate'      => strtotime($m[4] . ', ' . $m[3]),
                                    'ArrDate'      => strtotime($m[6] . ', ' . $m[5]),
                                    'FlightNumber' => $m[7],
                                    'AirlineName'  => 'U2',
                                ];
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1 and $this->totalStr) {
                        $itNew[0]['Currency'] = currency($t);

                        switch ($itNew[0]['Kind']) {
                        case 'T':
                            $itNew[0]['TotalCharge'] = cost($t);

                            break;

                        case 'R':
                            $itNew[0]['Total'] = cost($t);

                            break;
                        }
                    }

                    return $itNew;
                },
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
