<?php

namespace AwardWallet\Engine\aplus\Email;

class It2141194 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*De\s*:[^\n]*?@agenciaseta[.]com[.]br#i";
    public $rePlainRange = "1500";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $reFrom = "#@agenciaseta[.]com[.]br#i";
    public $reProvider = "#[@.]agenciaseta[.]com[.]br#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "aplus/it-2141194.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('text/rtf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re_white('de confirmar sua reserva nú mero      (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('de confirmar sua reserva nú mero  \d+  no  (.+?)[.]');

                        return nice($name);
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('- \d+ (\d+ - \d+ - \d+)');
                        $date = timestamp_from_format($date, 'd-m-y|');

                        return $date;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('(\d+ - \d+ - \d+) \d+ -');
                        $date = timestamp_from_format($date, 'd-m-y|');

                        return $date;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $addr = re_white('
							Email: .+? \n
							(.+?)
							Tel:
						');

                        return nice($addr);
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Tel:  ([\(+\d\)/]+)');

                        return nice($x);
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Fax:  ([\(+\d\)/]+)');

                        return nice($x);
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('
							novotel[.]com \n
							(.+?) \n
						');

                        return [nice($name)];
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('
							\s+ \d+[.]\d+ \s+ \w+ \n
							(.+?) \n
							\d+ - \d+ - \d+
						');
                        $x = clear('/and\s*$/i', $x);

                        return nice($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Agradecemos por escolher .+?[.]  .+? (\d+[.]\d+ \w+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('de confirmar sua reserva nú mero')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('
							(\d+ de \w+ de \d+)
							Forma de pagamento:
						');
                        $date = clear('/de\s*/', $date);
                        $date = en($date);

                        return strtotime($date);
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
