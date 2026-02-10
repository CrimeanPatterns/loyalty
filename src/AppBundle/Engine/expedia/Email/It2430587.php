<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class It2430587 extends \TAccountCheckerExtended
{
    public $rePlain = '#(?:\s+de\s+itinerario|Itinerary\s+)#i';
    public $reHtml = "#(?:\s+de\s+itinerario|Itinerary\s+)#";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]expedia(?:[.]|mail)#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]expedia(?:[.]|mail)#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "11.02.2015, 08:32";
    public $crDate = "11.02.2015, 07:26";
    public $xPath = "";
    public $mailFiles = "expedia/it-10025607.eml, expedia/it-10025987.eml, expedia/it-2430587.eml, expedia/it-266668977-fr.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public $detectSubject = [
        // en
        'Itinerary #',
        // es
        'Itinerario #',
        // fr
        'Itinéraire #',
    ];

    public $detectBody = [
        'en' => 'Itinerary #',
        'es' => 'de itinerario',
        'fr' => "d'itinéraire",
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if (strpos($headers["from"], '.expedia.') === false && strpos($headers["from"], '.expediamail.') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'expedia') or contains(@href, 'Expedia')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expediamail.com') !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

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
                        return re('/(?:Booking Number|Número de reservación|Numéro de réservation):\s+([A-Z\d]+)/');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('^ (.+?) - (?:Adult|Child|adulto|Adulte)\s*$');

                        if (preg_match_all("/$q/imu", $text, $m)) {
                            return array_unique(nice($m[1]));
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total :  (\d[,.‘\'\d]* [^\-\d)(]+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = rew('(?:Trip Net Price|Prix net du voyage) :  (\d[,.‘\'\d]*)');

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = rew('(?:Taxes and Fees|Taxes et frais) :  (\d[,.‘\'\d]*)');

                        return cost($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Your Reservation is Confirmed!')) {
                            return 'confirmed';
                        }

                        if (rew('Tu reservación de está confirmada')) { // es
                            return 'confirmada';
                        }

                        if (rew('Votre réservation est confirmée!')) { // fr
                            return 'confirmée';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('\( [A-Z]{3} \) .+? \( [A-Z]{3} \)');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								\) .+?
								\) (?: (?:Terminal|Aérogare) \w+)?
								(?P<AirlineName> .+?)
								(?P<FlightNumber> \d+)
							');
                            $res = re2dict($q, $text);

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( ([A-Z]{3}) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("/([[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})/iu");

                            if (!$date) {
                                if (preg_match("/(\d{1,2})\/(\d{2})\/(\d{4})/", $text, $m)) {
                                    $date = $m[1] . '.' . $m[2] . '.' . $m[3];
                                }
                            }

                            $time = preg_match_all('/\n[ ]*(\d{1,2}[ ]*[:：Hh][ ]*\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[ ]*\n/u', $text, $timeMatches) && count($timeMatches[1]) === 2
                                ? $timeMatches[1][0] : null;

                            if ($time) {
                                $time = preg_replace('/(\d)\s*[Hh]\s*(\d)/', '$1:$2', $time);
                            }

                            if (!$date) {
                                $date = (re("/\b(\d{1,2})\s*de\s*([[:alpha:]]+)/u") ?? re("/\b(\d{1,2})\s+([[:alpha:]]+)\./u")) . ' ' . re(2); // es + fr

                                if (preg_match("/\d+\s+([[:alpha:]]+)/u", $date, $m)) {
                                    if (($en = MonthTranslate::translate($m[1], 'es'))
                                        || ($en = MonthTranslate::translate($m[1], 'fr'))
                                    ) {
                                        $date = str_replace($m[1], $en, $date);
                                    }
                                }
                                $date = EmailDateHelper::calculateDateRelative($date, $this, $this->parser, '%D% %Y%');
                                $date = strtotime($time, $date);
                            } else {
                                $date = strtotime($date . ' ' . $time);
                            }

                            return $date;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( ([A-Z]{3}) \)');

                            return ure("/$q/isu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            if (preg_match("/\d{1,2}\/\d{2}\/\d{4}[\s\S]+?\s+(\d{1,2})\/(\d{2})\/(\d{4})/", $text, $m)) {
                                $date = $m[1] . '.' . $m[2] . '.' . $m[3];
                            } else {
                                $date = re("/[[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4}[\s\S]+\s+([[:alpha:]]+\s+\d{1,2}\s*,\s*\d{4})/iu", $text);
                            }

                            $time = preg_match_all('/\n[ ]*(\d{1,2}[ ]*[:：Hh][ ]*\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)[ ]*\n/u', $text, $timeMatches) && count($timeMatches[1]) === 2
                                ? $timeMatches[1][1] : null;

                            if ($time) {
                                $time = preg_replace('/(\d)\s*[Hh]\s*(\d)/', '$1:$2', $time);
                            }

                            if (!$date) {
                                if (preg_match("/\b\d{1,2}\s*de\s*[[:alpha:]]+\s+[\s\S]+\s+(\d{1,2})\s*de\s*([[:alpha:]]+)/u", $text, $m) // es
                                    || preg_match("/\b\d{1,2}[ ]+[[:alpha:]]+\.\s+[\s\S]+\s+(\d{1,2})[ ]+([[:alpha:]]+)\./u", $text, $m) // fr
                                ) {
                                    $date = $m[1] . ' ' . $m[2];

                                    if (preg_match("/\d+\s+([[:alpha:]]+)/u", $date, $m)) {
                                        if (($en = MonthTranslate::translate($m[1], 'es'))
                                            || ($en = MonthTranslate::translate($m[1], 'fr'))
                                        ) {
                                            $date = str_replace($m[1], $en, $date);
                                        }
                                    }
                                    $date = EmailDateHelper::calculateDateRelative($date, $this, $this->parser, '%D% %Y%');
                                    $date = strtotime($time, $date);
                                }
                            } else {
                                $date = strtotime($date . ' ' . $time);
                            }

                            return $date;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w+ \/ \w+)');
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'es', 'fr'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }
}
