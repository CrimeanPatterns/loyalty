<?php

namespace AwardWallet\Engine\bsi\Email;

class Itinerary1 extends \TAccountCheckerExtended
{
    public $reFrom = "#Hotel_Confirmation_1@bsi\.co\.uk#i";
    public $reProvider = "#bsi\.co\.uk#i";
    public $rePlain = "# Your\s+BSI\s+Reference#i";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#BSI\s+#";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $xPath = "";
    public $mailFiles = "bsi/it-1683570.eml, bsi/it-1683571.eml, bsi/it-1683572.eml, bsi/it-1683574.eml, bsi/it-1683575.eml, bsi/it-1683578.eml, bsi/it-1683580.eml, bsi/it-1683582.eml, bsi/it-1683583.eml, bsi/it-1683584.eml";
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
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Hotel\s+Ref:\s+([\w\-]+)#', $text, $m)) {
                            $res['ConfirmationNumber'] = $m[1][0];

                            if (count($m[1]) > 1) {
                                $res['ConfirmationNumbers'] = $m[1];
                            }

                            return $res;
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $subj = implode("\n", nodes("//text()[contains(., 'HOTEL DETAILS')]/ancestor::div[1]//text()"));
                        $regex = '#';
                        $regex .= 'Hotel\s+details\s+';
                        $regex .= '(?P<HotelName>.*)\s+';
                        $regex .= '(?P<Address>(?s).*)\s+';
                        $regex .= 'Tel:\s+(?P<Phone>.*)\s+';
                        $regex .= 'Fax:\s+(?P<Fax>.*)';
                        $regex .= '#i';

                        if (preg_match($regex, $subj, $m)) {
                            copyArrayValues($res, $m, ['HotelName', 'Address', 'Phone', 'Fax']);
                            $res = nice($res, ',');
                            $res['Address'] = trim($res['Address'], ',- ');

                            return $res;
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res['CheckInDate'] = strtotime(re('#Date\s+of\s+arrival:\s+\w+\s+(.*)#'));

                        if (preg_match('#Nights:\s+(\d+)#', $text, $m)) {
                            $res['CheckOutDate'] = strtotime('+' . $m[1] . ' day', $res['CheckInDate']);
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Guest:\s+(.*)#', $text, $m)) {
                            $guestNames = nice($m[1]);

                            return $guestNames;
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Room\s+\d+:#', $text, $m)) {
                            return count($m[0]);
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#.*\s+Per\s+Night#');

                        if (!$subj) {
                            if (preg_match_all('#Daily\s+Rate:\s+(.*)#', $text, $m)) {
                                $subj = implode(', ', $m[1]);
                            }
                        }

                        return $subj;
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+\(local\s+time\):\s+(.*)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $roomTypeRegexps = [
                            'Double\s+Room\s+Ensuite\s+for\s+Single\s+Occupancy(?:\s+-\s+Non-smoking)?',
                            'Deluxe\s+king-bedded\s+room\s+for\s+sole\s+use(?:\s+-\s+Non-smoking)?',
                            'Single\s+Room\s+Ensuite(?:\s+-\s+Non-smoking)?',
                            'Executive\s+Double\s+Room\s+for\s+Single\s+Occupancy(?:\s+-\s+Non-smoking)',
                        ];
                        $roomTypeSearchRegex = implode('|', $roomTypeRegexps);

                        if (preg_match_all("#Rate\s+Breakdown:\s+(${roomTypeSearchRegex})#", $text, $m)) {
                            return implode(' | ', $m[1]);
                        }
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $roomDescriptionRegexps = [
                            'Broadband\s+Wifi',
                            'Either\s+Double\s+Twin\s+Bedding',
                            'Bed\s+Bay View Or City View',
                            'Deluxe\s+Guest\s+Room\s+\d+\s+Or\s+\d+\s+Double',
                            'Urban\s+Swiss\s+Standard\s+Single\s+Bed\s+Min.\s+\d+X\d+',
                            '(?:Deluxe\s+|Guest\s+)?Room\s+\d+\s+(?:Queen|King)(?:\s+Or\s+\d+\s+(?:Twin\s+Single\s+Bed-S\s+\d+Sqm\s+\d+Sqft\s+Living\s+Sitting\s+Area\s+Wireless|Double\s+Mini-\s+\d+Sqm\s+\d+Sqft\s+))?',
                            'Wireless\s+Internet\s+For\s+A\s+Fee',
                        ];
                        $roomsDesc = [];

                        if (preg_match_all("#Description:\s+(.*?)\s+Meal\s+Plan#s", $text, $m)) {
                            foreach ($m[1] as $roomDescSrc) {
                                $roomDesc = [];

                                foreach ($roomDescriptionRegexps as $r) {
                                    if (preg_match("#${r}#", $roomDescSrc, $m2)) {
                                        $roomDesc[] = nice($m2[0]);
                                    }
                                }
                                $roomsDesc[] = implode(', ', $roomDesc);
                            }

                            return implode(' | ', $roomsDesc);
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#(.*)\s+Total\s+Amount\s+Of\s+Stay#');

                        if ($subj) {
                            return ['Total' => cost($subj), 'Currency' => currency($subj)];
                        }
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
