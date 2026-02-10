<?php

namespace AwardWallet\Engine\avis\Email;

class It1925507 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@avis[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@\.]avis\.com#i";
    public $reProvider = "#[@\.]avis\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "avis/it-1.eml, avis/it-14.eml, avis/it-1729336.eml, avis/it-1830465.eml, avis/it-1925507.eml, avis/it-1946431.eml, avis/it-2002199.eml, avis/it-2004651.eml, avis/it-8.eml, avis/it-9.eml";
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
                        $q = whiten('Your Confirmation Number:');

                        return re("#$q\s*([\w-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							Pick-up Information
							(.+?)
							Return
						');
                        $info = clear('/See\s+Airport\s+Map/i', $info);

                        $pat = white('
							(?P<dt> .+? @ \d+:\d+ (?:AM|PM)? )
							(?P<loc> .+?)
							(?P<phone> [(] \d+ [)] [\d-]+ )
							(?P<hours> .+ )
						');

                        if (!preg_match("/$pat/isu", $info, $ms)) {
                            return;
                        }
                        $dt = nice($ms['dt']);
                        $dt = uberDateTime($dt);

                        return [
                            'PickupDatetime' => strtotime($dt),
                            'PickupLocation' => nice($ms['loc']),
                            'PickupPhone'    => nice($ms['phone']),
                            'PickupHours'    => nice($ms['hours']),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							(?:hrs|PM|AM)
							Return
							(.+?)
							(?:RATE & BENEFIT INFORMATION|YOUR CAR)
						');
                        $info = clear('/See\s+Airport\s+Map/i', $info);

                        $pat = white('
							(?P<dt> .+? @ \d+:\d+ (?:AM|PM)? )
							(?P<loc> .+?)
							(?P<phone> [(] \d+ [)] [\d-]+ )
							(?P<hours> .+ )
						');

                        if (!preg_match("/$pat/isu", $info, $ms)) {
                            return;
                        }
                        $dt = nice($ms['dt']);
                        $dt = uberDateTime($dt);

                        return [
                            'DropoffDatetime' => strtotime($dt),
                            'DropoffLocation' => nice($ms['loc']),
                            'DropoffPhone'    => nice($ms['phone']),
                            'DropoffHours'    => nice($ms['hours']),
                        ];
                    },

                    "PickupPhone" => function ($text = '', $node = null, $it = null) {
                        $info = re('/Pick-up\s+Information(.+?)Return/is');

                        if (preg_match('/([(]\d+[)]\s*[\d-]+)\s+(.+)$/s', $info, $ms)) {
                            return [
                                'PickupPhone' => nice($ms[1]),
                                'PickupHours' => nice($ms[2]),
                            ];
                        }
                    },

                    "CarModel" => function ($text = '', $node = null, $it = null) {
                        $info = node("//*[contains(normalize-space(text()), 'YOUR CAR')]/following::strong[2]");

                        if (preg_match('/(.+?)\s+-\s+(.+)/', $info, $ms)) {
                            return [
                                'CarType'  => nice($ms[1]),
                                'CarModel' => nice($ms[2]),
                            ];
                        }

                        return nice($info);
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        $url = node("(//img[contains(@src, 'vehicle_guide')])[1]/@src");

                        return nice($url);
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return between('Name:', 'email address:');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $pat = "/Estimated\s+Total\s+[(](?P<cur>\w+)[)]\s+(?P<tot>\d+[.]\d+)\s+/i";

                        if (preg_match($pat, $text, $ms)) {
                            return [
                                'TotalCharge' => cost($ms['tot']),
                                'Currency'    => currency($ms['cur']),
                            ];
                        }
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $tax = between('Tax', 'Estimated Total (');

                        return cost($tax);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('Your car is reserved')) {
                            return 'confirmed';
                        } elseif (re_white('YOUR PREPAID RESERVATION IS CANCELLED')) {
                            return 'cancelled';
                        }
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        if (re_white('YOUR PREPAID RESERVATION IS CANCELLED')) {
                            return true;
                        }
                    },

                    "Fees" => function ($text = '', $node = null, $it = null) {
                        $fees = cell('Surcharges & Fees / Taxes', 0, +2);

                        if (preg_match_all('/(.+?)\s+(\d+[.]\d+)/', $fees, $ms)) {
                            $names = $ms[1];
                            $charges = $ms[2];

                            $res = [];

                            foreach ($names as $i => $name) {
                                $res[] = ['Name' => nice($name), 'Charge' => nice($charges[$i])];
                            }

                            return $res;
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
}
