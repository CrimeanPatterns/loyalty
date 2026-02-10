<?php

namespace AwardWallet\Engine\europcar\Email;

class It3620334 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['wird Europcar Sie darüber informieren#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]europcar[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]europcar[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.03.2016, 21:03";
    public $crDate = "23.03.2016, 20:39";
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
                        return reni('Ihre Reservierungsnummer lautet : (\w+)');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[
							normalize-space(text()) = 'Abholung' or
							normalize-space(text()) = 'Zustellstation'	
						]/following::td[text()][1]"));
                        $date = reni('(\d+[.]\d+[.]\d+)', $info);
                        $date = timestamp_from_format($date, '|d . m . y');
                        $time = uberTime($info);

                        if ($time) {
                            $dt = strtotime($time, $date);
                        }

                        $loc = reni('^ (.+?) \n \d+[.]', $info);

                        return [
                            'PickupDatetime' => $dt,
                            'PickupLocation' => $loc,
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[
							normalize-space(text()) = 'Abholstation' or
							normalize-space(text()) = 'Rückgabe'	
						]/following::td[text()][1]"));
                        $date = reni('(\d+[.]\d+[.]\d+)', $info);
                        $date = timestamp_from_format($date, '|d . m . y');
                        $time = uberTime($info);

                        if ($time) {
                            $dt = strtotime($time, $date);
                        }

                        $loc = reni('^ (.+?) \n \d+[.]', $info);

                        return [
                            'DropoffDatetime' => $dt,
                            'DropoffLocation' => $loc,
                        ];
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[normalize-space(text()) = 'Straße und Hausnummer']/following::a[1]"));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[normalize-space(text()) = 'Öffnungszeiten']/following::span[1]"));
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'oder ähnlich')]/ancestor::td[1]/following::td[text()][1]"));
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'oder ähnlich')]/ancestor::td[1]"));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'zu zahlender Preis')]/following::td[1]");

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Ihre Reservierung wurde bestätigt')) {
                            return 'confirmed';
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
