<?php

namespace AwardWallet\Engine\carlson\Email;

class IhreReservierungsbestatigung extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?carlsonhotels#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]carlsonhotels#i', 'us', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "08.04.2015, 21:56";
    public $crDate = "08.04.2015, 21:28";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return null;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#BESTÄTIGUNGSNUMMER\s+(\w+)#i');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $arr = [
                            'CheckIn' => [
                                'Ankunftsdatum:',
                                'Check-in-Zeit:',
                            ],
                            'CheckOut' => [
                                'Abreisedatum:',
                                'Check-out-Zeit:',
                            ],
                        ];
                        $res = null;

                        foreach ($arr as $key => [$dateLabel, $timeLabel]) {
                            $d = cell($dateLabel, +1);
                            $t = cell($timeLabel, +1);
                            $res[$key . 'Date'] = strtotime("$d, $t");
                        }

                        return $res;
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        $s = implode("\n", nodes('//img[contains(@src, "http://cache.carlsonhotels.com/chi/images/bcast_235x157")]/ancestor::td[1]/preceding-sibling::td[1]//text()'));
                        $r = '#\n\s*(.*)\s*\n\s*((?s).*)\s*\n\s*([\d\(\)\-][\d\s\(\)\-]+)\s*\n#i';

                        if (preg_match($r, $s, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2]),
                                'Phone'     => nice($m[3]),
                            ];
                        }
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if ($n1 = cell('Vorname:', +1) and $n2 = cell('Nachname:', +1)) {
                            return "$n1 $n2";
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Anzahl Erwachsene:', +1);
                    },

                    "Kids" => function ($text = '', $node = null, $it = null) {
                        return cell('Anzahl Kinder:', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[normalize-space(.) = "Stornierungsfrist"]/following-sibling::tr[1]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node('//text()[contains(., "Preistyp:")]/ancestor::tr[2]/preceding-sibling::tr[1]');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Geschätzter Gesamtbetrag*:', +1), 'Total');
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
