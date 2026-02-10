<?php

namespace AwardWallet\Engine\advrent\Email;

class YourAdvantageReservation extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your Advantage Reservation#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#advantagereservations@advantage\.com#i";
    public $reProvider = "#advantage\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "advrent/it-1941185.eml, advrent/it-1941194.eml";
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
                        return re('#CONFIRMATION\s+NUMBER\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $regex = '#';
                        $regex .= 'Pick Up/Return Location\s+';
                        $regex .= '((?s).*)\s+';
                        $regex .= 'Pick Up Phone Number[\s:]+(.*)\s+';
                        $regex .= 'Pick Up Fax[\s:]+(.*)\s+';
                        $regex .= 'Pick Up Hours[\s:]+((?s).*)\s+';
                        $regex .= 'Pick Up Date\s+(.*)\s+';
                        $regex .= 'Drop Off Date\s+(.*)';
                        $regex .= '#';

                        if (preg_match($regex, $text, $m)) {
                            $location = nice($m[1], ',');

                            return [
                                'PickupLocation'  => $location,
                                'DropoffLocation' => $location,
                                'PickupPhone'     => $m[2],
                                'PickupFax'       => $m[3],
                                'PickupHours'     => nice($m[4], ','),
                                'PickupDatetime'  => strtotime(str_replace('at', ',', $m[5])),
                                'DropoffDatetime' => strtotime(str_replace('at', ',', $m[6])),
                            ];
                        }
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#Selected Vehicle\s+(.*)\s+\((.*)\)\s+(or\s+similar)#i', $text, $m)) {
                            return [
                                'CarModel' => $m[1] . ' ' . $m[3],
                                'CarType'  => $m[2],
                            ];
                        }
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node('//tr[contains(., "Selected Vehicle")]/following-sibling::tr[1]//img[contains(@src, "vehicles")]/@src');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total Approximate Charge:\s+(.*)#'));
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
