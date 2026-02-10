<?php

namespace AwardWallet\Engine\aplus\Email;

class ConfirmationOfReservation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#From\s*:\s*ACCOR\s*HOTEL#i', 'blank', ''],
    ];
    public $reHtml = [
        ['images/aclub/', 'blank', '-4000'],
    ];
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#accor\.reservation\.transmission@accor\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#accor\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, es";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "18.03.2015, 17:12";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "aplus/it-1663333.eml, aplus/it-1663931.eml, aplus/it-1663970.eml, aplus/it-1694563.eml, aplus/it-1694566.eml, aplus/it-2018104.eml, aplus/it-2031699.eml, aplus/it-2537296.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = clear("#\n[>\s]*#", $text, "\n");

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#\n\s*(?:Reservation\s+number\s*:|New\s+reservation\s+n.?|Número\s+de\s+archivo\s*:)\s*([\w\-]+)#');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*([^\n]*?)\s+\((?:For\s+more|Si\s+desea\s+obtener)\s+[^\)]+\)\s+(.*?)\s+(?:Phone|Teléfono)\s*:\s*([\s\(\)\d/-]+)\s+Fax\s*:\s*([\s\(\)\d/-]+)#s")) {
                            return [
                                'HotelName' => re(1),
                                'Address'   => nice(glue(re(2))),
                                'Phone'     => nice(re(3)),
                                'Fax'       => nice(re(4)),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (re("#(?:stay|estancia)(?:\s*are\s+now)?\s*:\s*(?:from|desde\s+el)\s+([^\n]*?)\s+(?:to|hasta\s+el)\s+([^\n]+)#")) {
                            $res['CheckInDate'] = re(1);
                            $res['CheckOutDate'] = re(2);

                            foreach (['CheckIn' => 1, 'CheckOut' => 2] as $key => $value) {
                                if (preg_match('#(\d+)\s+(\w+)\s+(\d+)#i', $res[$key . 'Date'], $m)) {
                                    $res[$key . 'Date'] = $m[1] . ' ' . en($m[2]) . ' ' . $m[3];
                                }
                                $res[$key . 'Date'] = strtotime($res[$key . 'Date']);
                            }

                            return $res;
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:In favor of|Para)\s*:\s*([^\n]*?)(?:\n|\s{2,})#");
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#:\s*(\d+)\s+adult#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+(?:Number of rooms|Número de habitaciones)\s*:\s*(\d+)#");
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Rates|Tarifas)\s*:\s*([^\n]+)#");
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*(?:Cancellation policy|Tiempo limite de cancelación)\s*([^\n]+)#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Room type|Tipo de habitación)\s*:\s*([^\n]*?)(?:\s{2,}|\n)#");
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            cost(re("#National taxes on accommodation\s*:\s*([\d.,]+\s*[A-Z]{3})#")),
                            cost(re("#Other taxes\s*:\s*([^\n]+)#"))
                        );
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*(?:Total amount of the reservation|Importe total de la reserva)\s*:\s*([^\n]+)#"), 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:New\s+reservation\s+[^,]+,|This\s+reservation\s+is|Su\s+reserva\s+ha\s+sido)\s+(confirmed|modified|confirmada)#');
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
        return ["en", "es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
