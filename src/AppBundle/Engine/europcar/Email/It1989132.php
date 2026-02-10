<?php

namespace AwardWallet\Engine\europcar\Email;

class It1989132 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*Expéditeur\s*:[^\n]*?europcar#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "fr";
    public $typesCount = "1";
    public $reFrom = "#europcar#i";
    public $reProvider = "#europcar#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "europcar/it-1989132.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Res\.\-no\.\s+([\dA-Z\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*LIEU\s*([^\n]+)#") . ',' .
                        re("#\n\s*ADRESSE\s*([^\n]+)#") . ',' .
                        re("#\n\s*ADRESSE\s*[^\n]+\s+[^\n]+\n\s+([^\n]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*DATE\s+(\d+\.\d+\.\d+)\s+#") . ',' . re("#\n\s*HEURE\s+(\d+:\d+)\s+#"));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*LIEU\s*[^\n]+\s+([^\n]+)#") . ',' .
                        re("#\n\s*ADRESSE\s*[^\n]+\s+([^\n]+)#") . ',' .
                        nice(re("#\n\s*ADRESSE\s*([^\n]+\s+){3}(.*?)\s+TELEPHONE#ims"));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*DATE\s+\d+\.\d+\.\d+\s+(\d+\.\d+\.\d+)\s+#") . ',' . re("#\n\s*HEURE\s+\d+:\d+\s+(\d+:\d+)\s+#"));
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return [
                            'PickupPhone'  => re("#(\(.*?)\s+(\(.+)#", nice(re("#\n\s*TELEPHONE\s+([\d\(\)\s-+]+)#"))),
                            'DropoffPhone' => re(2),
                        ];
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*HEURES OUVERT[.\s]+(\d+:\d+\s*\-\s*\d+:\d+)#"));
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*HEURES OUVERT[.\s]+\s+\d+:\d+\s*\-\s*\d+:\d+\s+(\d+:\d+\s*\-\s*\d+:\d+)#"));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Europcar#i");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return [
                            'CarType'  => nice(re("#\n\s*CATEG[.\s]+VEHICULE\s*(.*?\))\s+(.*?)(?:TARIF|\s{3,})#ims")),
                            'CarModel' => nice(re(2)),
                        ];
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return nice(re("#\n\s*CONDUCTEUR\s+(.*?)(?:CONDUCTEUR|\s{3,})#ims"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*MONTANT ESTIME\s+([\d.]+\s+[A-Z]{3})#ims"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re(1));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*DATE DE RESERVATION\s*([^\n]+)#"));
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
        return ["fr"];
    }
}
