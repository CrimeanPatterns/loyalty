<?php

namespace AwardWallet\Engine\despegar\Email;

class It1842700 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?despegar.com|\n[>\s*]*De\s*:[^\n]*?despegar.com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "es";
    public $typesCount = "2";
    public $reFrom = "#despegar#i";
    public $reProvider = "#despegar#i";
    public $caseReference = "9078";
    public $xPath = "";
    public $mailFiles = "despegar/it-1703691.eml, despegar/it-1704923.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'simpletable');

                    if (empty(node("(//*[starts-with(normalize-space(), 'Reserva de')])[1]"))) {
                        return [''];
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Código de ingreso al hotel:')]/ancestor-or-self::tr/following-sibling::tr[2]/td[last()]");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Codigo para ingresar al hotel:')]/ancestor-or-self::tr/td[last()]");
                        }

                        return $node;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Pago')]/ancestor-or-self::tr/td[11]");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Pago')]/ancestor-or-self::tr/preceding-sibling::tr[2]/td[11]");
                        }

                        return $node;
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Entrada:\s*([^\n]+)\s*-#");
                        $node = trim($node);
                        $node = en(uberDatetime($node));

                        return totime($node);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Salida:\s*([^\n]+)\s*#");
                        $node = trim($node);
                        $node = en(uberDatetime($node));

                        return totime($node);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $node = node("//*[contains(text(), 'Pago')]/ancestor-or-self::tr/following-sibling::tr[3]/td[5]");

                        if ($node == null) {
                            $node = node("//*[contains(text(), 'Pago')]/ancestor-or-self::tr/preceding-sibling::tr[1]/td[2]");
                            $node = str_replace("Dirección:", "", $node);
                        }

                        return $node;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Reserva de\s*([^\n]+)#");

                        if ($node == null) {
                            $node = re("#Habitación [0-9]\n\s*[^\n]+;\s*([^\n]+),#");
                        }

                        return $node;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#([0-9]) adultos#");
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Habitación ([0-9])#");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Tipo:\s*([^\n]+\n\s*[^\n]+) -#");

                        if ($node != null) {
                            $node = explode(", ", $node);

                            return [
                                'RoomType'            => $node[0],
                                'RoomTypeDescription' => nice($node[1]),
                            ];
                        }

                        $node = re("#Tipo:\s*([^\n]+)#");

                        if ($node != null) {
                            return $node;
                        }

                        $node = re("#Habitación [0-9]\n\s*([^\n]+);#");

                        return $node;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $node = re("#Servicios confirmados#");

                        if ($node != null) {
                            return "confirmed";
                        }
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
        return ["es"];
    }
}
