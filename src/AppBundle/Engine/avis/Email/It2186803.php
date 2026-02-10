<?php

namespace AwardWallet\Engine\avis\Email;

class It2186803 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]avis[.]com#i";
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
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "avis/it-2186803.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = $this->parser->getHeader('date');
                    $date = strtotime($date);
                    $this->sent = $date;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re_white('Your Reservation Confirmation Number: (\w+)');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Pick-up Date Time and Location
							.+? \s+ at \s+
							(?P<PickupLocation> .+?)
							(?P<PickupPhone> [(\d) ]+)
							Return Date Time and Location
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = between('Pick-up Date Time and Location', 'Hrs');
                        $date = uberDate($info);
                        $time = uberTime($info);

                        $year = date('Y', $this->sent);
                        $dt = "$date $year, $time";
                        $dt = totime($dt);

                        if ($dt < $this->sent) {
                            $dt = strtotime('+1 year', $dt);
                        }

                        return $dt;
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Return Date Time and Location
							.+? \s+ at \s+
							(?P<DropoffLocation> .+?)
							(?P<DropoffPhone> [(\d) ]+)
							Your Vehicle
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $info = between('Return Date Time and Location', 'Hrs');
                        $date = uberDate($info);
                        $time = uberTime($info);

                        $year = date('Y', $this->sent);
                        $dt = "$date $year, $time";
                        $dt = totime($dt);

                        if ($dt < $this->sent) {
                            $dt = strtotime('+1 year', $dt);
                        }

                        return $dt;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							Your Vehicle
							(?P<CarType> .+?) - (?P<CarModel> .+? similar)
						');
                        $res = re2dict($q, $text);

                        return $res;
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Your Vehicle')]/following::img[1]/@src");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return between('Name:', 'E-mail:');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Estimated Total:	(.?\d+[.]\d+)');

                        return cost($x);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Base Rate:	(.?\d+[.]\d+)');

                        return currency($x);
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Taxes and Surcharges:	(.?\d+[.]\d+)');

                        return cost($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re_white('thank you for choosing Avis')) {
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
