<?php

namespace AwardWallet\Engine\expedia\Email;

class ETicketConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?expedia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "expedia/it-2096838.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = orval(
                        re('#Date:.*\s+(\d{4})\s+#', $this->getDocument('plain')),
                        re('#\s+(\d{4})\s+#i', $this->parser->getHeader('date'))
                    );

                    if (!$this->year) {
                        return null;
                    }

                    if (preg_match('#Airline\s+reference:\s+(.*?)\s*=\s*(\S+)#', $text, $m)) {
                        $this->recordLocators[$m[1]] = $m[2];
                    } else {
                        $this->recordLocators = null;
                    }

                    $this->passengers = nodes('//tr[contains(., "Passengers") and contains(., "Reference")]/following-sibling::tr/td[2]');

                    return xpath('//text()[contains(., "Departs:")]/ancestor::table[1]');
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('#\s*(.*)\s+Flight\s+\d+#i', text($text), $m)) {
                            if (isset($this->recordLocators[$m[1]])) {
                                return $this->recordLocators[$m[1]];
                            }
                        }
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->passengers;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#Status:\s+(.*)#');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return trim(re('#\n *(.+?) *Flight\s+\d+#'));
                        },

                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re('#Flight\s+(\d+)#');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 'Departs', 'Arr' => 'Arrives'] as $key => $value) {
                                $subj = cell($value . ':', +1);

                                if (preg_match('#(.*?)\s*(\d{1,2})(\d{2})\s+Hrs(?:,\s+\w+,\s+(?P<Date>\d+\s+\w*?))?(?:\s*(?P<Terminal>Terminal\s+.*))?$#i', $subj, $m)) {
                                    $res[$key . 'Code'] = TRIP_CODE_UNKNOWN;
                                    $res[$key . 'Name'] = $m[1]; //.(isset($m['Terminal']) ? ' ('.$m['Terminal'].')' : '');

                                    if (isset($m['Terminal'])) {
                                        $res[['Dep' => 'Departure', 'Arr' => 'Arrival'][$key] . 'Terminal'] = $m['Terminal'];
                                    }

                                    if (isset($m['Date'])) {
                                        $dateStr = $m['Date'];
                                    } elseif (isset($res['DepDate']) and $res['DepDate']) {
                                        $dateStr = date('j M', $res['DepDate']);
                                    } else {
                                        return null;
                                    }
                                    $res[$key . 'Date'] = strtotime($dateStr . ' ' . $this->year . ', ' . $m[2] . ':' . $m[3]);
                                }
                            }

                            return $res;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re('#Equipment:\s+(.*)#');
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
