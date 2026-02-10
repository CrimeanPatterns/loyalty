<?php

namespace AwardWallet\Engine\avis\Email;

class ReservationConfirmation2013 extends \TAccountCheckerExtended
{
    public $mailFiles = "avis/it-1747098.eml";

    public $reFrom = "#noreply@avis\.com#i";
    public $reProvider = "#[@\.]avis\.com#i";
    public $rePlain = "#thank\s+you\s+for\s+choosing\s+Avis\.\s+Confirmation\s+Number#i";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#Avis\s+Reservation\s+Confirmation#i";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
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
                        return re('#Confirmation\s+Number\s*:\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Pickup' => 'Pick-up', 'Dropoff' => 'Return'] as $key => $value) {
                            $xpath = '//td[contains(., "' . $value . ' Date Time and Location") and not(.//td)]//text()';
                            $subj = implode("\n", nodes($xpath));
                            $regex = '#(\w+\s+\d+,\s+\d+)\s+at\s+(\d+:\d+\s+(?:am|pm))\s+((?s).*?)\s*\n\s*(.*)\s*$#i';

                            if (preg_match($regex, $subj, $m)) {
                                $res[$key . 'Datetime'] = strtotime($m[1] . ', ' . $m[2]);
                                $res[$key . 'Location'] = nice($m[3]);
                                $res[$key . 'Phone'] = nice($m[4]);
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Your\s+Vehicle\s+(.*)\s+-\s+(.*)#i', $text, $m)) {
                            return [
                                'CarModel' => $m[1],
                                'CarType'  => $m[2],
                            ];
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return cell('Name:', +1);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Estimated Total:', +1);

                        if ($subj) {
                            return [
                                'TotalCharge' => cost($subj),
                                'Currency'    => currency($subj),
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
}
