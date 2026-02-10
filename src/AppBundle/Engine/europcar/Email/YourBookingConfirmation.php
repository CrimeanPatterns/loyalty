<?php

namespace AwardWallet\Engine\europcar\Email;

class YourBookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+booking\s+with\s+Europcar#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#europcar@mail\.europcar\.com#i";
    public $reProvider = "#mail\.europcar\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "europcar/it-2218095.eml";
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
                        return re('#Reservation\s+number:\s++([\w\-]+)#i');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $infos = xpath('//tr[count(./td) = 2 and contains(., "Pick up") and contains(., "and return") and not(.//tr)]');

                        if ($infos->length > 0) {
                            $n = $infos->item(0);
                            $res = null;

                            foreach (['Pickup' => 1, 'Dropoff' => 2] as $key => $value) {
                                $r = '#(?:Pick\s+up|and\s+return)\s+(.*)\s+((?s).*)\s+Tel\s+(.*)\s+Fax\s+(.*)\s+Opening\s+hours\s+((?s).*)#i';
                                $s = implode("\n", nodes('./td[' . $value . ']//text()', $n));

                                if (preg_match($r, $s, $m)) {
                                    $res[$key . 'Datetime'] = strtotime($m[1]);
                                    $res[$key . 'Location'] = nice($m[2], ',');
                                    $res[$key . 'Phone'] = nice($m[3]);
                                    $res[$key . 'Fax'] = nice($m[4]);
                                    $res[$key . 'Hours'] = nice($m[5], ',');
                                }
                            }

                            return $res;
                        }
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#and\s+return\s+(\d.*)#'));
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re('#Thank\s+you\s+for\s+booking\s+with\s+(.*?)\.#');
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $s = cell('Vehicle information', 0, 0, '//text()');
                        $res['CarType'] = re('#Code\s*:\s+(.*)#i', $s);
                        $res['CarModel'] = re('#e\.g\.\s*:\s+(.*)#i', $s);

                        return $res;
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $fn = re('#First\s+Name\s*:\s+(.*)#i');
                        $ln = re('#Last\s+Name\s*:\s+(.*)#i');

                        if ($fn and $ln) {
                            return $fn . ' ' . $ln;
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+price\s*:\s+(.*)#'));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Reservation\s+date\s+\(.*\)\s*:\s+(.*)#'));
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
