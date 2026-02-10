<?php

namespace AwardWallet\Engine\airtickets\Email;

class ETicket extends \TAccountChecker
{
    public $mailFiles = "airtickets/it-10184641.eml";

    public $reFrom = "airtickets.";
    public $reBody = [
        'en' => ['This is your e-ticket receipt', 'Thank you for choosing airtickets'],
    ];
    public $reSubject = [
        'E-Ticket',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Passengers information' => ['Passengers', 'Passengers information'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'airtickets.com/')]/@href")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        $its = [];
        $tripNum = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'airtickets') and {$this->contains($this->t('Reservation number'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})\s*$#");
        $pax = [];
        $accNum = [];
        $tickets = [];
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passengers information'))}]/following::table[1]/descendant::tr[position()>1]");

        foreach ($nodes as $node) {
            $pax[] = $this->http->FindSingleNode("./td[1]", $node) . ' ' . $this->http->FindSingleNode("./td[2]", $node);
            $acc = $this->http->FindSingleNode("./td[3]", $node, true, "#([\w\-]{5,})#");

            if (!empty($acc)) {
                $accNum[] = $acc;
            }
            $tickets[] = $this->http->FindSingleNode("./td[4]", $node);
        }
        $xpath = "//text()[{$this->eq($this->t('Flight duration:'))}]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        if (0 === $nodes->length) {
            $this->logger->info("Segments did not found by xpath: {$xpath}");

            return [];
        }
//        $this->logger->info($xpath);

        $airs = [];

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Airline reservation No'))}][1]/following::text()[normalize-space(.)!=''][1]", $root, true, "#([A-Z\d]{5,})\s*$#");
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];

            $it['RecordLocator'] = $rl;

            $it['TripNumber'] = $tripNum;

            $it['Passengers'] = $pax;

            $it['TicketNumbers'] = $tickets;

            if (count($accNum) > 0) {
                $it['AccountNumbers'] = $accNum;
            }

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::text()[{$this->starts($this->t('Airline reservation No'))}][1]/preceding::text()[normalize-space(.)!=''][1]", $root)));

                $node = implode("\n", $this->http->FindNodes("descendant-or-self::tr[count(td)=3]/td[1]/descendant::text()[normalize-space(.)!='']", $root));
                $this->logger->info($node);
//                ^([a-z\s\,\.]+)\s+\(([A-Z]{3})\)\s*(\d+:\d+(?:\s*[ap]m)?)\s*(\d{1,2}\/\d{1,2}\/\d{2,4})\s*(?:Terminal\s+(.+))?
                $re = "#([a-z\,\s\.]+)\s+\(([A-Z]{3})\)\s+((?i)\d+:\d+(?:\s*[ap]m)?)\s*(?:(\d{1,2}\/\d{1,2}\/\d{2,4}))?\s*(?:(?i)Terminal\s+(.+))?#i";

                if (preg_match($re, $node, $m)) {
                    $seg['DepCode'] = $m[2];
                    $seg['DepName'] = preg_replace('/\s+/', ' ', $m[1]);
                    $seg['DepDate'] = strtotime($m[3], $date);

                    if (!empty($m[4])) {
                        $seg['DepDate'] = strtotime($m[3], strtotime($m[4]));
                    }

                    if (!empty($m[5])) {
                        $seg['DepartureTerminal'] = $m[5];
                    }
                }
                $node = implode("\n", $this->http->FindNodes("descendant-or-self::tr[count(td)=3]/td[2]/descendant::text()[normalize-space(.)!='']", $root));
                $this->logger->info($node);

                if (preg_match($re, $node, $m)) {
                    $seg['ArrCode'] = $m[2];
                    $seg['ArrName'] = preg_replace('/\s+/', ' ', $m[1]);
                    $seg['ArrDate'] = strtotime($m[3], $date);

                    if (!empty($m[4])) {
                        $seg['ArrDate'] = strtotime($m[3], strtotime($m[4]));
                    }

                    if (!empty($m[5])) {
                        $seg['ArrivalTerminal'] = $m[5];
                    }
                }

                $node = $this->http->FindSingleNode("descendant::text()[normalize-space(.)!=''][contains(., 'Equipment')]/ancestor::table[1]/descendant::text()[normalize-space(.)][last()]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                $seg['Duration'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight duration'))}]/following::text()[normalize-space(.)!=''][1]", $root);
                $seg['Cabin'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Class'))}]/following::text()[normalize-space(.)!=''][1]", $root);
                $seg['Aircraft'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Equipment'))}]/following::text()[normalize-space(.)!=''][1]", $root);

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+\s+(\d{2})\/(\d{2})\/(\d{4})\s*$#',
        ];
        $out = [
            '$3-$2-$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
