<?php

namespace AwardWallet\Engine\aeroflot\Email;

class ItineraryETicketReceipt extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-10047256.eml";

    public $reFrom = "@aeroflot.ru";
    public $reBody = [
        'en' => ['Itinerary e-ticket receipt', 'Prepared for'],
    ];
    public $reSubject = [
        'Itinerary e-ticket receipt',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Aeroflot']")->length > 0) {
            $body = $parser->getHTMLBody();

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
        $airs = [];
        $its = [];

        $xpath = "//text()[starts-with(normalize-space(.),'Ticket status')]/following::text()[normalize-space(.)!=''][1][not(contains(.,'Exchanged'))]/ancestor::table[contains(.,'Ticket not valid before')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./preceding::text()[starts-with(normalize-space(.),'Booking code')][1]/following::text()[normalize-space(.)!=''][1]", $root, true, "#([A-Z\d]{5,})#");
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;

            if (!empty($rl)) {
                $dates = $this->http->FindNodes("//text()[normalize-space()='{$rl}']/preceding::text()[{$this->starts($this->t('Ticket issue date'))}]/ancestor::td[1]/following-sibling::td//text()[normalize-space(.)!='']");

                if (count($dates) > 0) {
                    $it['ReservationDate'] = strtotime(array_shift($dates));
                }
                $it['TicketNumbers'] = $this->http->FindNodes("//text()[normalize-space()='{$rl}']/preceding::text()[{$this->starts($this->t('Ticket(s) number(s)'))}]/ancestor::td[1]/following-sibling::td//text()[normalize-space(.)!='']");
                $it['Passengers'] = array_values(array_filter(array_unique($this->http->FindNodes("//text()[normalize-space()='{$rl}']/preceding::text()[{$this->starts($this->t('Prepared for'))}]/ancestor::td[1]//text()[normalize-space(.)!=''][not({$this->contains($this->t('Prepared for'))})]"))));
                $subroot = $this->http->XPath->query("//text()[normalize-space()='{$rl}']/following::text()[{$this->eq($this->t('Fare'))}]/ancestor::table[1][{$this->contains($this->t('Ticket total'))}]");

                if ($subroot->length == 1) {
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Ticket total'))}]/ancestor::td[1]/following-sibling::td[1]", $subroot->item(0)));

                    if (!empty($tot['Total'])) {
                        $it['TotalCharge'] = $tot['Total'];
                        $it['Currency'] = $tot['Currency'];
                    }
                    $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Fare'))}]/ancestor::td[1]/following-sibling::td[1]", $subroot->item(0)));

                    if (!empty($tot['Total'])) {
                        $it['BaseFare'] = $tot['Total'];
                        $it['Currency'] = $tot['Currency'];
                    }
                    $node = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->eq($this->t('Taxes/Fees/Charges'))}]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)!='']", $subroot->item(0)));

                    if (preg_match_all("#^([A-Z]{3}\s+\d[\d\.]+)\s+(.+)#m", $node, $m, PREG_SET_ORDER)) {
                        foreach ($m as $item) {
                            $it['Fees'][] = ['Name' => $item[2], 'Charge' => $item[1]];
                        }
                    }
                }
            }

            foreach ($roots as $root) {
                /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
                $seg = [];
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Class'))}]/following::text()[normalize-space(.)!=''][1]", $root);

                if (preg_match("#(.+?)(?:[\s\/]+([A-Z]{1,2})|$)#", $node, $m)) {
                    $seg['Cabin'] = $m[1];

                    if (isset($m[2]) && !empty($m[2])) {
                        $seg['BookingClass'] = $m[2];
                    }
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Operating airline'))}]/ancestor::td[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#([A-Z\d]{2})\s*(\d+)\s+(.+?)\s+{$this->opt($this->t('Operating airline'))}[\s:]+(.*?)(?:\s+{$this->opt($this->t('Partner airline'))}[\s:]+(.*)|$)#s", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                    $seg['Aircraft'] = $this->nice($m[3]);

                    if (isset($m[4]) && !empty($m[4])) {
                        $seg['Operator'] = $this->nice($m[4]);
                    }
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Operating airline'))}]/ancestor::td[1]/following-sibling::td[1]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#(\d+:\d+)\s+(\d+[\sh\.]+\d+[\smin\.]+)(\d+\s+\w+\s+\d+)\s+(.+?)\s+([A-Z]{3})\s+Terminal[\s:]+(.+)#s", $node, $m)) {
                    $seg['Duration'] = $this->nice($m[2]);
                    $seg['DepDate'] = strtotime($m[3] . ' ' . $m[1]);
                    $seg['DepName'] = $this->nice($m[4]);
                    $seg['DepCode'] = $m[5];
                    $seg['DepartureTerminal'] = $m[6];
                }

                $node = implode("\n", $this->http->FindNodes("./descendant::text()[{$this->starts($this->t('Operating airline'))}]/ancestor::td[1]/following-sibling::td[2]//text()[normalize-space(.)!='']", $root));

                if (preg_match("#(\d+:\d+)\s+(\d+\s+\w+\s+\d+)\s+(.+?)\s+([A-Z]{3})\s+Terminal[\s:]+(.+)#s", $node, $m)) {
                    $seg['ArrDate'] = strtotime($m[2] . ' ' . $m[1]);
                    $seg['ArrName'] = $this->nice($m[3]);
                    $seg['ArrCode'] = $m[4];
                    $seg['ArrivalTerminal'] = $m[5];
                }

                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", " ", $str));
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
