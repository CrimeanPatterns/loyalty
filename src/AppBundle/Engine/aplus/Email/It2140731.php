<?php

namespace AwardWallet\Engine\aplus\Email;

class It2140731 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@accor[.]net#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#ACCOR\s+HOTELS#i";
    public $reProvider = "#[@.]accor[.]net#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "aplus/it-2140731.eml, aplus/it-2148599.eml, aplus/it-2148608.eml";
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
                        return re_white('
							(?:Confirmation|Reservation) number :? :?
							([\w-]+)
						');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $name = node("//*[normalize-space(text()) = 'Access']/preceding::a[1]");
                        $name = nice($name);
                        $addr = re_white("
							$name
							(?:Tel :? .+?)? \|? \s+
							(.+?)
							\/? Access
						");
                        $addr = nice($addr);
                        // important, leaving old parsers alone.
                        if (re_white('GPS :?', $addr)) {
                            return null;
                        }

                        return [
                            'HotelName' => $name,
                            'Address'   => $addr,
                        ];
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('from (\d+ \/ \d+ \/ \d+) to');

                        return timestamp_from_format($date, 'd/m/Y|');
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('to (\d+ \/ \d+ \/ \d+) ,');

                        return timestamp_from_format($date, 'd/m/Y|');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re_white('Tel : (.+?) \s+');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('
							Reservation made in the name of :? (.+?)
							(?:Reservation number | Confirmation number | Dates of stay) 
						');

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\d+) adult');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return between('Cancellation delay :', 'Check in Policy :');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $x = node("(//*[contains(text(), 'adult(s)')]) [1] /following::h4[1]");

                        return nice($x);
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        $x = node("(//*[contains(text(), 'adult(s)')]) [1] /following::h4[1]/following::div[1]");

                        return nice($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Reservation total  (\d+[.]\d+ \w+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Reservation confirmation')) {
                            return 'confirmed';
                        } elseif (re_white('reservation was cancelled')) {
                            return 'cancelled';
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
