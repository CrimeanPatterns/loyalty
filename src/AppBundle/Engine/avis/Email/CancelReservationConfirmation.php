<?php

namespace AwardWallet\Engine\avis\Email;

class CancelReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#your\s+reservation\s+has\s+been\s+cancelled.\s+Please\s+consider\s+Avis#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Avis\s+Rent\s+A\s+Car:\s+Cancel\s+Reservation\s+Confirmation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@\.]avis\.#i";
    public $reProvider = "#[@\.]avis\.#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "avis/it-1855740.eml, avis/it-1855742.eml, avis/it-1881587.eml, avis/it-1946464.eml, avis/it-2186811.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!preg_match($this->rePlain, $text)) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re_white('
							Your (?: Confirmation | Cancellation ) Number:+ (\w+)
						');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Pick-up Information
							.+? @ \d+:?\d+ (?: PM | AM | hours )?
							(?P<PickupLocation> .+?)
							(?P<PickupPhone> \([(\d) -]{5,})
							(?P<PickupHours> .+? (?: PM | AM | hrs) \n )?
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Pick-up Information
							\w+,\s+(?P<d>.+?), (?P<y>\d{4}) @?
							(?P<h>\d{1,2}) :? (?P<i>\d{2}) (?P<a> PM | AM)?
						');

                        if (!preg_match("/$q/isu", $text, $m)) {
                            return;
                        }
                        $m['a'] = get_or($m, 'a', '');
                        $dt = "{$m['d']} {$m['y']}, {$m['h']}:{$m['i']} {$m['a']}";

                        return strtotime($dt);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Return (?:Information)?
							.+? @ \d+:?\d+ (?: PM | AM | hours )?
							(?P<DropoffLocation> .+?)
							(?P<DropoffPhone> \([(\d) -]{5,})
							(?P<DropoffHours> .+? (?: PM | AM | hrs) \n )?
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Return (?:Information)?
							\w+,\s+(?P<d>.+?),\s+(?P<y>\d{4}) @?
							(?P<h>\d{1,2}) :? (?P<i>\d{2}) (?P<a> PM | AM)?
						');

                        if (!preg_match("/$q/isu", $text, $m)) {
                            return;
                        }
                        $m['a'] = get_or($m, 'a', '');

                        $dt = "{$m['d']} {$m['y']}, {$m['h']}:{$m['i']} {$m['a']}";

                        return strtotime($dt);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#(Group\s+\w)\s+-\s+(.*\s+or\s+similar)#i', $text, $m)) {
                            return [
                                'CarType'  => $m[1],
                                'CarModel' => $m[2],
                            ];
                        }
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return orval(
                                node('//tr[contains(., "YOUR CAR") and not(.//tr)]/following-sibling::tr[1]//img[contains(@src, "vehicle_guide")]/@src'),
                                node('//img[contains(@src, "yourcar")]/ancestor::tr[1]/following-sibling::tr[1]//img[contains(@src, "vehicle_guide")]/@src')
                            );
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $name = re_white('CANCELLED (.+?), your reservation');

                        return nice($name);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#your\s+reservation\s+has\s+been\s+(cancelled)#i', $text, $m)) {
                            return [
                                'Status'    => $m[1],
                                'Cancelled' => true,
                            ];
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
