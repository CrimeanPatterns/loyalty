<?php

namespace AwardWallet\Engine\ebookers\Email;

class It2438062 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*Von\s*:[^\n]*?[@.]ebookers[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]ebookers[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]ebookers[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "06.02.2015, 08:24";
    public $crDate = "06.02.2015, 08:06";
    public $xPath = "";
    public $mailFiles = "ebookers/it-2438062.eml";
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
                        return reni('Galileo  PNR: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return reni('Anmietstation: .+? \| (.+?) \n');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = rew('Anmietstation: \w+ , (.+?) \|');
                        $dt = en($dt);

                        return totime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return reni('Rückgabe: .+? \| (.+?) \n');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $dt = rew('Rückgabe: \w+ , (.+?) \|');
                        $dt = en($dt);

                        return totime($dt);
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return reni('Anmietstation: .+?
						Telefon: (.+?) \n');
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return reni('IHR FAHRZEUG
						(.+?) Autovermietung');
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Gepäck')]/preceding::strong[1]"));
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return reni('Mietwagenreservierung unter:  (.+?) \n');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Gesamtreisepreis  (.+?) \n');

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Vielen Dank, dass Sie Ihre Reise')) {
                            return 'confirmed';
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $dt = rew('Diese Reservierung erfolgte am (.+?) \n');
                        $dt = uberDateTime($dt);

                        return totime($dt);
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
