<?php

namespace AwardWallet\Engine\avis\Email;

class It3952667 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?bookings@avis-europe.com#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#bookings@avis-europe.com#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]avis#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "nl";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "21.06.2016, 09:34";
    public $crDate = "21.06.2016, 09:02";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#Uw unieke reserveringsreferentie:\s+([\w\-]+)#i');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#OPHAALLOCATIE\s*((?s).*?)\n\n#'), ',');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re('#OPHAALDATUM\s+\w+,\s+(.*)#i')));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#INLEVERLOCATIE\s*((?s).*?)\n\n#'), ',');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(en(re('#INLEVERDATUM\s+\w+,\s+(.*)#i')));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $s = node('//text()[contains(., "OPHAALDATUM")]/ancestor::tr[1]');
                        $hours = re('#OPENINGSTIJDEN\s+(\d+:\d+-\d+:\d+)#', $s);
                        $phone = re('#TELEFOONNUMMER\s+(\d+)#', $s);

                        return [
                            'PickupPhone' => $phone,
                            'PickupHours' => $hours,
                        ];
                    },

                    "DropoffPhone" => function ($text = '', $node = null, $it = null) {
                        $s = node('//text()[contains(., "INLEVERDATUM")]/ancestor::tr[1]');
                        $hours = re('#OPENINGSTIJDEN\s+(\d+:\d+-\d+:\d+)#', $s);
                        $phone = re('#TELEFOONNUMMER\s+(\d+)#', $s);

                        return [
                            'DropoffPhone' => $phone,
                            'DropoffHours' => $hours,
                        ];
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Uw reserveringsgegevens:\s*(.*)\s*(.*)#i', $text, $m)) {
                            return [
                                'CarType'  => $m[1],
                                'CarModel' => nice($m[2]),
                            ];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('GESCHAT TOTAALBEDRAG', +1));
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
        return ["nl"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
