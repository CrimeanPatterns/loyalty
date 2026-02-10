<?php

namespace AwardWallet\Engine\airbnb\Email;

class ReservationReminder extends \TAccountCheckerExtended
{
    public $rePlain = "#This\s+is\s+a\s+reminder\s+that\s+you\s+have\s+an\s+upcoming\s+reservation.*?The\s+Airbnb\s+Team|Dette er en påmindelse om, at du har en kommende reservation.*?Airbnb teamet#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en, da";
    public $typesCount = "2";
    public $reFrom = "#automated@airbnb\.com#i";
    public $reProvider = "#airbnb\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "airbnb/it-1939196.eml, airbnb/it-1939444.eml, airbnb/it-1944888.eml, airbnb/it-1953730.eml, airbnb/it-1955210.eml, airbnb/it-1955475.eml, airbnb/it-1956745.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (re('#phone\s+number\s+with\s+the\s+host\s+directly#i')) {
                            $lang = 'en';
                        } elseif (re('#og telefonnummeret, direkte med værten#i')) {
                            $lang = 'da';
                        } else {
                            $lang = null;
                        }
                        $res = null;
                        $regex = '#';
                        $regex .= '(phone\s+number\s+with\s+the\s+host\s+directly\.|og telefonnummeret, direkte med værten\.)\s*';

                        if ($lang == 'en') {
                            $regex .= '\n\s*(?P<CheckInMonth>\w+)\s+(?P<CheckInDay>\d+)(?:,\s+(?P<CheckInYear>\d+))?\s+-\s*';
                            $regex .= '(?P<CheckOutMonth>\w+)?\s+(?P<CheckOutDay>\d+),\s+(?P<CheckOutYear>\d+)\s*';
                        } elseif ($lang == 'da') {
                            $regex .= '\n\s*(?P<CheckInDay>\d+)\.\s+(?P<CheckInMonth>\w+)(?:\s+(?P<CheckInYear>\d+))?\s+-\s*';
                            $regex .= '(?P<CheckOutDay>\d+)?\.?\s+(?P<CheckOutMonth>\w+)\s+(?P<CheckOutYear>\d+)\s*';
                        }
                        $regex .= '\n\s*(?P<HotelName>.*)\s*';
                        $regex .= '\n\s*(?P<Address>.*)\s*';
                        $regex .= '\n\s*.*\s*';
                        $regex .= '\n\s*.*\s*';
                        $regex .= '\n\s*(?:(?P<Phone>[\-+\d\s]+?)|\(phone\s+number\s+unknown\))\s+';
                        $regex .= '(Before you depart|Før du rejser)';
                        $regex .= '#i';

                        if (preg_match($regex, $text, $m)) {
                            if (!$m['CheckOutMonth']) {
                                $m['CheckOutMonth'] = $m['CheckInMonth'];
                            }

                            if (!$m['CheckInYear']) {
                                $m['CheckInYear'] = $m['CheckOutYear'];
                            }

                            foreach (['CheckIn', 'CheckOut'] as $key) {
                                $res[$key . 'Date'] = strtotime($m[$key . 'Day'] . ' ' . en($m[$key . 'Month']) . ' ' . $m[$key . 'Year']);
                            }
                            copyArrayValues($res, $m, ['HotelName', 'Address', 'Phone']);

                            if (!$res['Phone']) {
                                $res['Phone'] = null;
                            }
                        }

                        return nice($res);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#(?:Dear|Kære)\s+(.*?),#i')];
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["en", "da"];
    }
}
