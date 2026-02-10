<?php

namespace AwardWallet\Engine\amextravel\Email;

class HotelConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank\s+you\s+for\s+using\s+Platinum\s+Travel\s+Services#i', 'us', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#Platinum@americanexpress\.com\.bh#i', 'us', ''],
    ];
    public $reProvider = [
        ['#americanexpress\.com\.bh#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "03.02.2015, 17:42";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2120475.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->emailDate = strtotime($this->parser->getHeader('date'));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Hotel:\s+(.*)\s+((?s).*?)\s+Check\s+In#i', $text, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        if (!$this->emailDate) {
                            return null;
                        }

                        foreach (['CheckIn' => 'Check In', 'CheckOut' => 'Check Out'] as $key => $value) {
                            $dateStr = re('#' . $value . '\s+Date:\s+(.*)#i');
                            $res[$key . 'Date'] = strtotime($dateStr . ' ' . date('Y', $this->emailDate));

                            if ($res[$key . 'Date'] < strtotime('-3 month', $this->emailDate)) {
                                $res[$key . 'Date'] = strtotime('+1 year', $res[$key . 'Date']);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $guestNames = re('#Itinerary\s+for\s*:\s+(.*)\s+Hotel\s+Information#i');

                        if ($guestNames) {
                            return [$guestNames];
                        }
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Number\s+of\s+Rooms:\s+((\d+).*)\s+Hotel\s+Rate#i', $text, $m)) {
                            return [
                                'RoomType' => $m[1],
                                'Rooms'    => $m[2],
                            ];
                        }
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Hotel\s+Rate:\s+(.*)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#CANCELL?ATION\s*-\s*(.*)#i');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(re('#TOTAL\s+PRICE\s+(.*)#i'), 'Total');
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
