<?php

namespace AwardWallet\Engine\edreams\Email;

class HotelReservationPlainText extends \TAccountCheckerExtended
{
    public $rePlain = "#EDREAMS\s+CORPORATE#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es";
    public $typesCount = "1";
    public $reFrom = "#CORPORATE-ES@edreams\.com#i";
    public $reProvider = "#edreams\.com#i";
    public $caseReference = "";
    public $isAggregator = "1";
    public $xPath = "";
    public $mailFiles = "edreams/it-2270776.eml";
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
                        return re('#CODIGO\s+DE\s+RES\.:\s+([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $r = '#';
                        $r .= '- - -';
                        $r .= '\s*\n\s*.*\s+(.*)';
                        $r .= '\s*\n\s*((?s).*?)';
                        $r .= '\s*\n\s*REF:.*';
                        $r .= '\s*\n\s*PRECIO-(.*)';
                        $r .= '\s*\n\s*(CONFIRMADO)';
                        $r .= '#i';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Total'     => cost($m[3]),
                                'Currency'  => currency($m[3]),
                                'Status'    => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $year = re('#RESERVA\s+DE\s+HOTEL\s+.*?\s+(\d{4})#i');

                        if (!$year) {
                            return null;
                        }
                        $res = null;

                        foreach (['CheckIn' => 'ENTRADA', 'CheckOut' => 'SALIDA'] as $key => $value) {
                            if (preg_match('#' . $value . ':\s+(\d+)\s+(\w+)#i', $text, $m)) {
                                $res[$key . 'Date'] = strtotime($m[1] . ' ' . en($m[2]) . ' ' . $year);
                            }
                        }

                        return $res;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re('#FECHA:\s+(\d+\s+\w+\s+\d+)#i')));
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
        return ["es"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
