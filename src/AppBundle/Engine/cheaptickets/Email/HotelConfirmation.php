<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class HotelConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?cheaptickets#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#cheaptickets#i', 'us', ''],
    ];
    public $reProvider = [
        ['#cheaptickets#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "20.03.2015, 14:48";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-2156734.eml, cheaptickets/it-2296745.eml";
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
                        $r = '#';
                        $r .= '\s*(.*)\s+';
                        $r .= 'Hotel\s+confirmation\s+number\s*:\s+([A-Z\d-]+)\s+';
                        $r .= '(.*)\s+';
                        $r .= 'Phone\s*:\s+([\d\s\-+]+)(?:\s*\|\s+Fax\s*:\s+([\d\-+]+))?';
                        $r .= '#iu';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'HotelName'          => $m[1],
                                'ConfirmationNumber' => $m[2],
                                'Address'            => $m[3],
                                'Phone'              => nice($m[4]),
                                'Fax'                => (isset($m[5]) and $m[5]) ? nice($m[5]) : null,
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-in:\s+(.*)#'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Check-out:\s+(.*)#'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return [re('#Hotel\s+reservations\s+under\s*:\s+(.*)#')];
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Reservation:\s+(\d+)\s+Room#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//*[normalize-space(.) = "Cancellation:"]/following-sibling::ul[1]');
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return re('#Room\s+description:\s+(.*)#');
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node('//td[@id = "costItemHeading1"]/following-sibling::td[1]'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Taxes and fees', +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+due\s+at\s+booking\s+(.*)#i'), 'Total');
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        $sa = cell('CheapCash applied', +1);

                        if ($sa) {
                            $sa = str_replace('-', '', $sa);
                        }

                        return $sa;
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
