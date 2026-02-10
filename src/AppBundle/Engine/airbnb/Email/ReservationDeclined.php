<?php

namespace AwardWallet\Engine\airbnb\Email;

class ReservationDeclined extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#We’re sorry to say that the host at .* declined(?:(?s).*?)Regards,\s+The\s+Airbnb\s+Team#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#express@airbnb\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#airbnb\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "22.05.2015, 12:55";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "airbnb/it-1965357.eml, airbnb/it-1973085.eml, airbnb/it-1974040.eml, airbnb/it-1975634.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $regex = '#(?:We.re sorry to say that|Unfortunately) the host at\s+(.*?)\s+(declined) your#i';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'ConfirmationNumber' => CONFNO_UNKNOWN,
                                'HotelName'          => $m[1],
                                'Status'             => $m[2],
                                'Cancelled'          => true,
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Here.s a reminder of your trip details#i', $text, $m)) {
                            $res = null;

                            if (preg_match('#\s+(.*)\s+(\d+)\s+Guests?#i', $text, $m)) {
                                $res['RoomType'] = $m[1];
                                $res['Guests'] = $m[2];
                            }

                            foreach (['CheckIn' => 'CHECK IN', 'CheckOut' => 'CHECK OUT'] as $key => $value) {
                                $dateStr = nice(re('#' . $value . '\s+\w+,\s+(\w+\s+\d+)#i'));

                                if ($dateStr) {
                                    $dateStr .= ', ' . re('#\d{4}#', $this->parser->getHeader('Date'));
                                    $res[$key . 'Date'] = strtotime($dateStr);
                                }
                            }

                            return $res;
                        } elseif (preg_match('#Here are some similar listings we found that are available (\d+ \w+, \d+) - (\d+ \w+, \d+):#i', $text, $m)) {
                            return [
                                'RoomType'     => null,
                                'Guests'       => null,
                                'CheckInDate'  => timestamp_from_format($m[1] . ', 00:00', 'd M, Y, H:i'),
                                'CheckOutDate' => timestamp_from_format($m[2] . ', 00:00', 'd M, Y, H:i'),
                            ];
                        } elseif (preg_match('#Here are some similar listings we found that are available (\w+) (\d+) - (\d+), (\d+):#i', $text, $m)) {
                            return [
                                'RoomType'     => null,
                                'Guests'       => null,
                                'CheckInDate'  => strtotime($m[2] . ' ' . $m[1] . ' ' . $m[4]),
                                'CheckOutDate' => strtotime($m[3] . ' ' . $m[1] . ' ' . $m[4]),
                            ];
                        } else {
                            return [
                                'RoomType'     => null,
                                'Guests'       => null,
                                'CheckInDate'  => MISSING_DATE,
                                'CheckOutDate' => MISSING_DATE,
                            ];
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

    public function IsEmailAggregator()
    {
        return false;
    }
}
