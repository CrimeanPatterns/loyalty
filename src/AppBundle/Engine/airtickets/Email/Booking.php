<?php

namespace AwardWallet\Engine\airtickets\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "airtickets/it-10100734.eml, airtickets/it-10116444.eml, airtickets/it-10132126.eml, airtickets/it-11676570.eml, airtickets/it-12232101.eml, airtickets/it-12232105.eml";

    public $reFrom = "@airtickets.gr";
    public $reBody = [
        'en' => ['My booking', 'Thank you for choosing us'],
        'el' => ['Η κράτησή μου', 'ευχαριστούμε για την προτίμησή σας'],
    ];
    public $reSubject = [
        'My tickets to',
        'Η κράτησή μου για',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
        'el' => [
            'Reservation Number'=> 'Αρ. Κράτησης',
            'Passengers'        => 'Επιβάτες',
            'Duration:'         => 'Διάρκεια:',
            // 'code is'=>'',
            // 'Ticket Number'=>'',
            'Price details' => 'Ανάλυση Τιμής',
            'Fare'          => 'Ναύλος',
            'Taxes and fees'=> 'Φόροι και τέλη',
            'Total:'        => 'Τελικό ποσό:',
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
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Price details'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('Total:'))}]/ancestor::td[1]/following-sibling::td[1]"));

        if (!empty($tot['Total'])) {
            $result['parsedData']['TotalCharge'] = [
                'Amount'   => $tot['Total'],
                'Currency' => $tot['Currency'],
            ];
        }

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
        $tripNum = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Reservation Number'))}]/ancestor::td[1])[1]", null, true, "#([A-Z\d]{5,})\s*$#");
        $pax = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers'))}]/following::table[1]/descendant::tr[count(descendant::table)=1]/descendant::text()[normalize-space(.)!=''][2]");
        $xpath = "//text()[{$this->starts($this->t('Duration:'))}]/ancestor::tr[contains(.,'→')][1]";
        $nodes = $this->http->XPath->query($xpath);

        $airs = [];
        $tickets = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./descendant::tr[1]/descendant::text()[normalize-space(.)!=''][1]", $root);
            $node = $this->http->FindNodes("//text()[contains(normalize-space(.),'{$airline}') and {$this->contains($this->t('code is'))}]/following::text()[normalize-space(.)!=''][1]", null, "#([A-Z\d]{5,})#");
            $tn = $this->http->FindNodes("//text()[{$this->eq($this->t('Ticket Number'))}]/following::text()[contains(normalize-space(.),'{$airline}')][1]", null, "#{$airline}[\s:]+(.+)#");

            if (count($node) > 0) {
                $rl = array_shift($node);
            } else {
                $rl = $tripNum;
            }
            $airs[$rl][] = $root;

            if (isset($tickets[$rl])) {
                $tickets[$rl] = array_merge($tickets[$rl], $tn);
            } else {
                $tickets[$rl] = $tn;
            }
        }

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];

            $it['RecordLocator'] = $rl;

            $it['TripNumber'] = $tripNum;

            $it['Passengers'] = $pax;

            if (isset($tickets[$rl])) {
                $tn = array_unique(array_filter(array_unique($tickets[$rl])));

                if (count($tn) > 0) {
                    $it['TicketNumbers'] = $tn;
                }
            }

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::tr[contains(.,'|')][1]", $root, true, "#\|\s*(.+)#")));

                $node = $this->http->FindSingleNode("./descendant::tr[1]/td[1]", $root);

                if (preg_match("#\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)(?:\s+(?<Cabin>.*?))?\s*(?:\(\s*(?<BookingClass>[A-Z]{1,2})\s*\))?\s+(?:(?i)Terminal[\s:]+(?<DepartureTerminal>\w+))?\s*(?<Aircraft>(?!(?i)Terminal).*)#s", $node, $m)) {
                    foreach ($m as $key => $value) {
                        if (!is_numeric($key) && !empty(trim($value))) {
                            $seg[$key] = $this->nice($value);
                        }
                    }
                } elseif (preg_match("#\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#", $node, $m)) {
                    foreach ($m as $key => $value) {
                        if (!is_numeric($key) && !empty(trim($value))) {
                            $seg[$key] = $this->nice($value);
                        }
                    }
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::tr[1]/td[2]/descendant::text()[normalize-space(.)!='']", $root));

                if (preg_match("#([A-Z]{3})[,\s]+(.+?)\n\s*(.+?)(?:\n|$)#s", $node, $m)) {
                    $seg['DepCode'] = $m[1];
                    $seg['DepName'] = $this->nice($m[3] . ' - ' . $m[2]);
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::tr[1]/td[4]/descendant::text()[normalize-space(.)!='']", $root));

                if (preg_match("#([A-Z]{3})[,\s]+(.+?)\n\s*(.+?)(?:\n|$)#s", $node, $m)) {
                    $seg['ArrCode'] = $m[1];
                    $seg['ArrName'] = $this->nice($m[3] . ' ' . $m[2]);
                }

                $time = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[1]/td[1]", $root, true, "#(\d+:\d+(?:\s*[ap]m)?)#i");
                $seg['DepDate'] = strtotime($time, $date);

                $time = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[1]/td[3]", $root, true, "#(\d+:\d+(?:\s*[ap]m)?)#i");
                $seg['ArrDate'] = strtotime($time, $date);

                $seg['Duration'] = $time = $this->http->FindSingleNode("./descendant::tr[1]/following-sibling::tr[1]/td[2][{$this->contains($this->t('Duration:'))}]", $root, true, "#{$this->opt($this->t('Duration:'))}\s*(.+)#");

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        if (count($its) == 1) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Price details'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('Fare'))}]/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['BaseFare'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Price details'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('Taxes and fees'))}]/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['Tax'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->contains($this->t('Price details'))}]/following::table[1]/descendant::text()[{$this->starts($this->t('Total:'))}]/ancestor::td[1]/following-sibling::td[1]"));

            if (!empty($tot['Total'])) {
                $its[0]['TotalCharge'] = $tot['Total'];
                $its[0]['Currency'] = $tot['Currency'];
            }
        }

        return $its;
    }

    private function normalizeDate($date)
    {
        $this->logger->info($date);
        $in = [
            '#^\s*[^\s\d]+\s+(\d{2})\/(\d{2})\/(\d{4})\s*$#',
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function nice($str)
    {
        return preg_replace("#\s+#", ' ', $str);
    }
}
