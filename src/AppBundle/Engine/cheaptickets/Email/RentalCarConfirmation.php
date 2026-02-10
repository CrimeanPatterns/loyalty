<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class RentalCarConfirmation extends \TAccountCheckerExtended
{
    public $reFrom = "#cheaptickets#i";
    public $reProvider = "#cheaptickets#i";
    public $rePlain = "#CheapTickets.*?Rental\s+Car\s+Confirmation#is";
    public $rePlainRange = "/1";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-1829199.eml";
    public $rePDF = "";
    public $rePDFRange = "";
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
                        $r = '#(.*)\s+Booking\s+Reference:\s+([\w\-]+)\s+(.*)#';

                        if (preg_match($r, $text, $m)) {
                            return [
                                'RentalCompany' => nice($m[1]),
                                'Number'        => $m[2],
                                'CarModel'      => $m[3],
                            ];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Drop-off'] as $key => $value) {
                            $subj = node('//td[contains(., "' . $value . '") and not(.//td)]');
                            $r = '#';
                            $r .= '\w+,\s+(\w+\s+\d+,\s+\d+)\s+.\s+';
                            $r .= '(\d+:\d+\s*(?:am|pm))\s+\|\s+';
                            $r .= '(.*)\s+';
                            $r .= '(Phone.*|\(Same\s+as.*)';
                            $r .= '#i';

                            if (preg_match($r, $subj, $m)) {
                                $res[$key . 'Datetime'] = strtotime($m[1] . ', ' . $m[2]);
                                $res[$key . 'Location'] = nice($m[3]);

                                if (preg_match('#Phone:\s+(.*)\s+Shuttle#i', $m[4], $m2)) {
                                    $res[$key . 'Phone'] = $m2[1];
                                }
                            }
                        }

                        return $res;
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[contains(., "Rental:") and not(.//tr)]//img[contains(@src, "carImages")]/@src');
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return beautifulName(cell('Car reservation under:', +1));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell('Total car rental', +1));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $r = '#This\s+reservation\s+was\s+made\s+on\s+\w+,\s+(\w+\s+\d+,\s+\d+\s+\d+:\d+\s*(?:am|pm))#i';

                        return strtotime(re($r));
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
