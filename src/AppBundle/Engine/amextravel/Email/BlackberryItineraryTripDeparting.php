<?php

namespace AwardWallet\Engine\amextravel\Email;

class BlackberryItineraryTripDeparting extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?AMEXBUSINESSTRAVEL\.NOREPLY@AEXP\.COM#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#BLACKBERRY\s+ITINERARY\s+TRIP\s+DEPARTING#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#AMEXBUSINESSTRAVEL\.NOREPLY@AEXP\.COM#i";
    public $reProvider = "#AEXP\.COM#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2205423.eml, amextravel/it-2205427.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = preg_replace('#\n\s*[>\s]+\s*#i', "\n", $text);
                    $passInfo = "\n" . re('#Passengers\s+Reference.*\s+((?s).*?)\s+IMPORTANT#i', $text);
                    $this->travellers = [];

                    if (preg_match_all('#\n\s*(.*?/.*?)\s{2,}#i', $passInfo, $m)) {
                        $this->travellers = $m[1];
                    }
                    $this->tripLocator = re('#Information\s+for\s+Trip\s+Locator:\s+([\w\-]+)#i', $text);

                    if (preg_match_all('#HOTEL\s+(?:(?s).*?)Status:\s+.*|TRAIN\s+-(?:(?s).*?)Status:#', $text, $m)) {
                        return $m[0];
                    }
                },

                "#HOTEL#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation:\s+([\w\-]+)#i');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re('#HOTEL\s+.*\s*\n\s*(.*)\s*\n\s*Address#');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                        if (!$year) {
                            return null;
                        }
                        $res = null;

                        foreach (['CheckIn' => 'HOTEL -', 'CheckOut' => 'Check out:'] as $key => $value) {
                            $d = re('#' . $value . '\s+(\w+,\s+\w+\s+\d+)#i');

                            if (!$d) {
                                continue;
                            }
                            $res[$key . 'Date'] = strtotime($d . ', ' . $year);
                        }

                        return $res;
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return nice(re('#Address:\s+(.*?)\s+Telephone#s'), ',');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return re('#Telephone:\s+(.*)#');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return re('#Fax:\s+(.*)#');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return $this->travellers;
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re('#Rate:\s+(.*)#');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellation\s+Policy:\s+(.*)#');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)#');
                    },
                ],

                "#TRAIN#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travellers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)#');
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $year = re('#\d{4}#i', $this->parser->getHeader('date'));

                            if (!$year) {
                                return null;
                            }
                            $res = null;

                            foreach (['Dep' => 'From', 'Arr' => 'To'] as $key => $value) {
                                if (preg_match('#' . $value . ':\s+(.*)\s+(\d+:\d+\s+[ap]m),\s+(.*)#', $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Date'] = strtotime($m[3] . ', ' . $year . ', ' . $m[2]);
                                }
                            }

                            return $res;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],
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
