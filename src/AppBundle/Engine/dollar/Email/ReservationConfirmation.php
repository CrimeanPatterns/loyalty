<?php

namespace AwardWallet\Engine\dollar\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?dollar#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Dollar\s+Rent\s+A\s+Car\s+Reservation\s+Confirmation#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#dollarrentacar@email\.dollar\.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#@email\.dollar\.com#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "22.05.2015, 14:10";
    public $crDate = "22.05.2015, 14:00";
    public $xPath = "";
    public $mailFiles = "dollar/it-2739139.eml, dollar/it-3020234.eml";
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
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        if (re('#Confirmation\s+\#:\s+([\w\-]+)#')) {
                            return re(1);
                        }

                        if (re('#Cancellation\s+\#:\s+([\w\-]+)#')) {
                            return [
                                "Cancelled"=> true,
                                "Status"   => 'Cancelled',
                                "Number"   => re(1),
                            ];
                        }
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'PICKUP', 'Dropoff' => 'RETURN'] as $key => $value) {
                            $x = '//tr[contains(., "' . $value . ' INFO")]/following-sibling::tr[contains(., "Date/Time:")]//text()';
                            $s = implode("\n", nodes($x));
                            $d = str_replace('@', ',', re('#Date/Time:\s+(.*)#i', $s));
                            $res[$key . 'Datetime'] = strtotime($d);
                            $res[$key . 'Location'] = re('#Location:\s+(.*)#i', $s);
                            $res[$key . 'Phone'] = trim(re('#Phone:\s+([\d\s\(\)\-]+)#msi', $s));
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Vehicle\s+Type:\s+(.*?)\s+-\s+(.*)\s+Date/Time:#i', $this->text(), $m)) {
                            return [
                                'CarType'  => $m[1],
                                'CarModel' => $m[2],
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re('#Name:\s+(.*)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Grand\s+Total:\s+(.*)#'));
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
