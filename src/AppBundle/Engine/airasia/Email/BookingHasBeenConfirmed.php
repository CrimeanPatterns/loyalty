<?php

namespace AwardWallet\Engine\airasia\Email;

class BookingHasBeenConfirmed extends \TAccountCheckerExtended
{
    public $rePlain = "#Your\s+booking\s+has\s+been\s+confirmed,\s+thank\s+you\s+for\s+choosing\s+(?:AirAsia|Thai\s+AirAsia\s+X)#i";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your\s+AirAsia\s+booking\s+has\s+been\s+confirmed#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#itinerary@myses01\.airasia\.com|itineraryns@airasia\.com#i";
    public $reProvider = "#airasia\.com#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "airasia/it-1.eml, airasia/it-1413849.eml, airasia/it-1750326.eml, airasia/it-1770815.eml, airasia/it-1996755.eml, airasia/it-5071267.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $subj = $this->parser->getHtmlBody();
                    $subj = preg_replace('#<META[^>]+>#', '', $subj);
                    $this->http->setBody($subj);

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+booking\s+number\s+is:\s*([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [nice(re('#Dear\s+(.*)\s*,#i'))];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+booking\s+has\s+been\s+(.*?),#');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Your\s+booking\s+date\s+is\s+\w+\s+(.*)#'));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[contains(., "Depart") and contains(., "Arrive") and not(.//tr)]/following-sibling::tr[not(contains(., "Transfer service"))]';
                        $segments = $this->http->XPath->query($xpath);

                        if ($segments->length > 0) {
                            $this->segmentContentType = 'xpath';

                            return $segments;
                        }

                        $subj = re('#Date\s+Flight\s+Depart\s+Arrive\s+(.*)#is');
                        $regex = '#\w+\s+\d+\s+\w+\s+\d{4}\s+\w+\s+\d+\s+\w+\s+\d+:\d+\s+\w+\s+\d+:\d+#';

                        if (preg_match_all($regex, $subj, $m)) {
                            $this->segmentContentType = 'text';

                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if ($this->segmentContentType == 'xpath') {
                                $subj = node('./td[3]');
                            } elseif ($this->segmentContentType == 'text') {
                                $subj = re('#\d{4}\s+(\w{2}\s+\d+)\s+\w{3}\s+\d+:#i');
                            }

                            if (preg_match('#(\w{2})\s*(\d+)#', $subj, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            if ($this->segmentContentType == 'xpath') {
                                $dateStr = re('#\w+\s+(\d+.*)#', node('./td[2]'));

                                foreach (['Dep' => 4, 'Arr' => 6] as $key => $value) {
                                    $subj = node('./td[' . $value . ']');
                                    $res[$key . 'Code'] = $subj;

                                    $subj = node('./td[' . ($value + 1) . ']');
                                    $res[$key . 'Date'] = strtotime($dateStr . ', ' . $subj);
                                }
                            } elseif ($this->segmentContentType == 'text') {
                                $dateStr = re('#\w+\s+(\d+\s+\w+\s+\d{4})\s+#i');

                                if (preg_match('#(\w{3})\s+(\d+:\d+)\s+(\w{3})\s+(\d+:\d+)#', $text, $m)) {
                                    foreach (['Dep' => 1, 'Arr' => 3] as $key => $value) {
                                        $subj = $m[$value];
                                        $res[$key . 'Code'] = $subj;

                                        $subj = $m[$value + 1];
                                        $res[$key . 'Date'] = strtotime($dateStr . ', ' . $subj);
                                    }
                                }
                            }

                            return $res;
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
