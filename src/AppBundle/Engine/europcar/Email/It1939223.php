<?php

namespace AwardWallet\Engine\europcar\Email;

class It1939223 extends \TAccountCheckerExtended
{
    public $rePlain = "#la\s+invitiamo\s+a\s+visitare\s+il\s+nostro\s+sito\s+web\s+all'indirizzo\s+www[.]europcar[.]it#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "it";
    public $typesCount = "1";
    public $reFrom = "#EuropcarRes#i";
    public $reProvider = "#[@.]europcar.com#i";
    public $xPath = "";
    public $mailFiles = "europcar/it-1939223.eml";
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
                        $q = 'Numero ID Europcar:';

                        return re("/$q\s*([\w-]+)/i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $country = re('/Return\s*Country:\s*(.+?)\s*Località/i');
                        $loc = re('/Località\s*di\s*ritiro:\s*(.+?)\s*Località/i');

                        return nice("$country, $loc");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Data\s*del\s*ritiro:\s*(.+?)\s*Data#i");
                        // $date = \DateTime::createFromFormat('d/m/Y', $date);
                        // return $date ? $date->getTimestamp() : null;
                        $date = re("#(\d+)/(\d+)/(\d+)#", $date, 2) . '/' . re(1) . '/' . re(3);

                        return strtotime($date);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $country = re('/Return\s*Country:\s*(.+?)\s*Località/i');
                        $loc = re('/Località\s*di\s*ritorno:\s*(.+?)\s*Casa/i');

                        return nice("$country, $loc");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $date = re("#Data\s*di\s*ritorno:\s*(.+?)\s*Paese#i");
                        $date = re("#(\d+)/(\d+)/(\d+)#", $date, 2) . '/' . re(1) . '/' . re(3);

                        return strtotime($date);
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $model = re('/Casa\s*automobilistica:\s*(.+?)\s*Messaggio/i');

                        return nice($model);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name1 = re('/Nome:\s*(.+?)\s*Cognome/i');
                        $name2 = re('/Cognome:\s*(.+?)\s*Data/i');

                        return nice("$name1 $name2");
                    },

                    "ServiceLevel" => function ($text = '', $node = null, $it = null) {
                        $level = re('/Carta\s*Europcar\s*:\s*(.+?)\s*Numero/i');

                        return nice($level);
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
        return ["it"];
    }
}
