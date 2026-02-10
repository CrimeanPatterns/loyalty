<?php

namespace AwardWallet\Engine\delta\Email;

class ImFlyingTo extends \TAccountCheckerExtended
{
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]delta\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]delta\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "27.01.2015, 15:36";
    public $crDate = "27.01.2015, 15:32";
    public $xPath = "";
    public $mailFiles = "delta/it-2411201.eml";
    public $re_catcher = "#.*?#";
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
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter('#\n\s*(\w{2}\d+\s*:\s*\w{3}\s+to\s+\w{3}.*)#i');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(\w{2})(\d+)\s*:\s*(\w{3})\s+to\s+(\w{3})#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'DepCode'      => $m[3],
                                    'ArrCode'      => $m[4],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re('#Departs\s+(.*)#');
                            $date = str_ireplace(' at ', ', ', $date);
                            return strtotime($date);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = re('#Arrives\s+(.*)#');
                                // Mon 5. Dec 2022 at 08:05
                            $date = str_ireplace(' at ', ', ', $date);
                            return strtotime($date);
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (($this->http->XPath->query('//text()[contains(., "Thank you for choosing Delta")]')->length > 0)
            && ($this->http->XPath->query('//text()[contains(., "Sent with the Delta App")]')->length > 0)
            && ($this->http->XPath->query('//text()[contains(., "the information about my flight to")]')->length > 0)) {
            return true;
        }

        return false;
    }
}
