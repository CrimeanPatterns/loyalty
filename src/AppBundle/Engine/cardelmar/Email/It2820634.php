<?php

namespace AwardWallet\Engine\cardelmar\Email;

class It2820634 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#anbei\s+erhalten\s+Sie\s+die\s+Bestätigung\/Rechnung\s+zu\s+Ihrer\s+Stornierung.+?CarDelMar#is', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Storno-Bestätigung/Storno-Kundenrechnung zu Ihrer CarDelMar-Buchun', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]cardelmar#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]cardelmar#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.06.2015, 21:30";
    public $crDate = "26.06.2015, 20:41";
    public $xPath = "";
    public $mailFiles = "cardelmar/it-2820634.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Buchungsnummer:\s+(.+?)\s*\n#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $puDateStr = re("#Abholstation:\s+([^\n]+?)\s*\n#i");
                        $puDateStr .= ", " . re("#Adresse:\s+(.+?)\s*\n\s*[^\n]+\s+([^\n]+)\s*\n#i");
                        $puDateStr .= ", " . re(2);

                        return $puDateStr;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $puDateStr = re("#Anmietzeitraum:\s+(\d+\.\d+\.\d+)[^\d]+(\d+\.\d+\.\d+)\s#i");
                        $doDateStr = re(2);
                        $puDateStr .= " " . re("#Abholzeit:\s+(\d+:\d+)\s+Abgabezeit:\s+(\d+:\d+)\s#i");
                        $doDateStr .= " " . re(2);

                        return [
                            'PickupDatetime'  => strtotime($puDateStr),
                            'DropoffDatetime' => strtotime($doDateStr),
                        ];
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $puDateStr = re("#Abgabestation:\s+([^\n]+?)\s*\n#i");
                        $puDateStr .= ", " . re("#Adresse:\s+[^\n]+\s+(.+?)\s*\n\s*[^\n]+\s+([^\n]+)\s*\n#i");
                        $puDateStr .= ", " . re(2);

                        return $puDateStr;
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $puPhone = re("#Abgabestation:\s.+?Telefon:\s+([^\n]+)\s+([^\n]+)\s#is");
                        $doPhone = re(2);

                        return [
                            'PickupPhone'  => $puPhone,
                            'DropoffPhone' => $doPhone,
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Lokaler\s+Partner:\s+(.+?)\s*\n#");
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return re("#Fahrzeuggruppe:\s+(.+?)\s*\n#i");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#^.+?GmbH\s+(.+?)\s*\n#i");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Currency'    => re("#Gutschriftsbetrag\s+(.+)\s+(.+)\s#i"),
                            'TotalCharge' => floatval(re(2)),
                        ];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match("#Storno-Kundenrechnung\s+zu\s+Ihrer\s+CarDelMar-Buchun#", $this->parser->getHeader('Subject'))) {
                            return [
                                'Status'    => "cancelled",
                                'Cancelled' => true,
                            ];
                        }
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        return re("#Tarif:\s+(.+?)\s*\n#i");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Rechnungsdatum:\s+([\d\-]+)\s#i") . "T00:00");
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
