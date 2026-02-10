<?php

namespace AwardWallet\Engine\carlson\Email;

class It2634950 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]carlsonhotels[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]carlsonhotels[.]#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]carlsonhotels[.]#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.09.2015, 13:53";
    public $crDate = "15.04.2015, 11:38";
    public $xPath = "";
    public $mailFiles = "carlson/it-2596685.eml, carlson/it-2634950.eml, carlson/it-3048645.eml";
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
                        return reni('Ihre Bestätigungsnummer lautet (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(node("//*[contains(text(), 'Bestätigungsnummer lautet')]/following::a[1]"));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Ankunftsdatum:', +1);
                        $date = en($date);
                        $time = cell('Check-in-Zeit:', +1);

                        $dt = strtotime($date);
                        $dt = date_carry($time, $dt);

                        return $dt;
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        $date = cell('Abreisedatum', +1);
                        $date = en($date);
                        $time = cell('Check-out-Zeit:', +1);

                        $dt = strtotime($date);
                        $dt = date_carry($time, $dt);

                        return $dt;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(text(), 'Bestätigungsnummer lautet')]/following::td[1]");
                        $name = arrayVal($it, 'HotelName');

                        $q = white("
							$name
							(?P<Address> .+?)
							(?P<Phone> [+\s\d\(\)-]{7,})\s*
							\S+@
						");
                        $res = re2dict($q, $info);

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = sprintf('%s %s',
                            cell('Vorname:', +1),
                            cell('Nachname:', +1)
                        );

                        return [nice($name)];
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return reni('Anzahl Erwachsene:  (\d+)');
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return reni('Anzahl Kinder:  (\d+)');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $s = node("//*[
							contains(text(), 'Stornierungsrichtlinie') or
							contains(text(), 'Stornierungsfrist')
						]/following::td[1]");

                        return nice($s);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $s = node("//*[contains(text(), 'Gesamtbetrag:')]/following::tr[2]");
                        $q = white('
							(?P<RoomType> .+?) ,
							(?P<RoomTypeDescription> .+)
						');
                        $res = re2dict($q, $s);

                        return $res;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Gesamtbetrag: ([\d,]+ \w+)');

                        return cost($x);
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Geschätzte Steuern\*?: ([\d,]+ \w+)');

                        return cost($x);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Geschätzter Gesamtbetrag\*: ([\d,]+ \w+)');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('bestätigen zu können')) {
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
