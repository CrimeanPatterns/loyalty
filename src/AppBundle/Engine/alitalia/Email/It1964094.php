<?php

namespace AwardWallet\Engine\alitalia\Email;

class It1964094 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?@alitalia[.]it#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#@alitalia[.]it#i";
    public $reProvider = "#[@.]alitalia[.]it#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "alitalia/it-1964094.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $subj = $this->parser->getHeader('subject');
                        $q = 'booking reference';

                        return orval(
                            re("/$q\s+([\w-]+)/i", $subj),
                            CONFNO_UNKNOWN
                        );
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        $q1 = whiten('has been cancelled');
                        $q2 = whiten('we booked for you');

                        if (re("/$q1/i") && re("/$q2/i")) {
                            return 'updated';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = between('new flight on', 'on');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $code = between('departing from', 'at');

                            return re('/\s+([A-Z]+)$/', $code);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $pat = whiten('
								on (?P<date>\d+/\d+)
								departing from .+? at (?P<time1>\d+:\d+)
								and arriving in .+? at (?P<time2>\d+:\d+) [.]
							');

                            if (!preg_match("#$pat#is", $text, $ms)) {
                                return;
                            }
                            $date = $ms['date'];
                            $time1 = $ms['time1'];
                            $time2 = $ms['time2'];

                            $date = sprintf('%s/%s', re('#(\d+)/(\d+)#', $date, 2), re(1));

                            $dt1 = "$date $time1";
                            $dt2 = "$date $time2";

                            $dt1 = strtotime($dt1, $this->date);
                            $dt2 = strtotime($dt2, $this->date);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $code = between('arriving in', 'at');

                            return re('/\s+([A-Z]+)$/', $code);
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
}
