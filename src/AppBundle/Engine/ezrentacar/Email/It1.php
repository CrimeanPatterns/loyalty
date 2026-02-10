<?php

namespace AwardWallet\Engine\ezrentacar\Email;

class It1 extends \TAccountCheckerExtended
{
    public $reFrom = "";
    public $reProvider = "";
    public $rePlain = "#reservation\s+with\s+E-Z\s+Rent\s+a\s+Car#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#E-Z\s+Rent-A-Car\s+Reservation#is";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "ezrentacar/it-1.eml, ezrentacar/it-1731347.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#Your Confirmation Number is:\s+([a-z0-9]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pickup', 'Dropoff' => 'Return'] as $key => $value) {
                            $regex = '#';
                            $regex .= $value . ':?\s+';
                            $regex .= '(.*)\s+';
                            $regex .= '(\d+/\d+/\d+)\s+@?\s*(\d+:\d+(?::\d+)?\s+(?:am|pm))\s+';
                            $regex .= '(.*)';
                            $regex .= '#i';

                            if (preg_match($regex, $text, $m)) {
                                $res[$key . 'Location'] = $m[1];
                                $res[$key . 'Datetime'] = strtotime($m[2] . ', ' . $m[3]);
                                $res[$key . 'Phone'] = $m[4];
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Car\s+Class:\s+(.*?)\s*(?:Model)?:\s+(.*)#', $text, $m)) {
                            if (preg_match('#' . $m[2] . '\s+or\s+similar#i', $text, $m2)) {
                                $m[2] = nice($m2[0]);
                            }

                            return [
                                'CarType'  => $m[1],
                                'CarModel' => $m[2],
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return node("//table[tbody[tr[td[contains(text(), 'Your Itinerary')]]]]/following-sibling::table[1]//td/b[contains(text(), 'Name')]/following-sibling::node()[1]");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Est(?:\.|imated)\s+Total:\s+(.*)#');

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
                            ];
                        }
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\((.*)\)#", node("//table[tbody[tr[td[contains(text(), 'Extras / Discounts')]]]]/following-sibling::table[1]//text()[contains(., 'Discounts:')]/ancestor::td[1]/following-sibling::td[1]")));
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
