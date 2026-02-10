<?php

namespace AwardWallet\Engine\btstore\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "btstore/it-10115581.eml, btstore/it-12120355.eml, btstore/it-12120361.eml";

    public $reFrom = "@bt-store.com";
    public $reBody = [
        'en' => [
            ['YOUR FLIGHT ITINERARY', 'Below is a copy of your purchased flight itinerary for your reference'],
            ['YOUR ITINERARY', 'Thank you for booking your trip at Best Travel Store'],
        ],
    ];
    public $reSubject = [
        '#Thank\s+you\s+for\s+your\s+order\s+with\s+BT-Store.com#i',
        '#Your\s+BTS\s+Reservation\s+cannot\s+be\s+Processed[\s\-]+Declined\s+Credit\s+Card\s+Charge#i',
    ];

    public static $dict = [
        'en' => [
        ],
    ];

    private $lang = '';
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('YOUR BOOKING TOTAL IS'))}]", null, true, "#{$this->opt($this->t('YOUR BOOKING TOTAL IS'))}[\s:]+(.+)#"));

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
        if ($this->http->XPath->query("//a[contains(@href,'bt-store.com')]")->length > 0) {
            return $this->AssignLang();
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"])) {
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

        $xpath = "//text()[{$this->eq($this->t('Depart'))}]/ancestor::tr[1][not({$this->contains($this->t('Arive'))})]";
        $nodes = $this->http->XPath->query($xpath);
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("(//node()[{$this->starts($this->t('Airline Record Locator(s)'))}][1])[1]", null, true, "#([A-Z\d]{5,})#");
        $it['TripNumber'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Order ID'))}]", null, true, "#([A-Z\d\-]{5,})#");
        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Passenger'))}]/ancestor::td[1]", null, "#{$this->opt($this->t('Passenger'))}\s*:\s*(.+)#"));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Base fare'))}]/ancestor::tr[1][{$this->contains($this->t('Tax'))}]/following-sibling::tr[1]/td[1]"));

        if (!empty($tot['Total'])) {
            $it['BaseFare'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Base fare'))}]/ancestor::tr[1][{$this->contains($this->t('Tax'))}]/following-sibling::tr[1]/td[2]"));

        if (!empty($tot['Total'])) {
            $it['Tax'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->starts($this->t('Base fare'))}]/ancestor::tr[1][{$this->contains($this->t('Tax'))}]/following-sibling::tr[1]/td[3]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = $this->http->FindSingleNode("./td[1]", $root);

            if (preg_match("#(.+)\s+{$this->opt($this->t('Flight'))}\s+(\d+)#s", $node, $m)) {
                $seg['AirlineName'] = $this->nice($m[1]);
                $seg['FlightNumber'] = $m[2];
            }
            $node = implode("\n", $this->http->FindNodes("./td[3]//text()", $root));

            if (preg_match("#^(\d+:\d+(?:\s*[ap]m)?)\s*(\w+\s+\d+)$#is", $node, $m)) {
                $seg['DepDate'] = strtotime($this->normalizeDate($this->nice($m[2] . ' ' . $m[1])));
            }
            $node = $this->http->FindSingleNode("./td[4]", $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\),\s+(.*?)(?:\s*Terminal[\s:]+(.+)|$)#", $node, $m)) {
                $seg['DepName'] = $m[1] . ' - ' . $m[3];
                $seg['DepCode'] = $m[2];

                if (isset($m[4])) {
                    $seg['DepartureTerminal'] = $m[4];
                }
            }
            $node = implode("\n", $this->http->FindNodes("./td[5]//text()[normalize-space(.)!='']", $root));
            $seg['Duration'] = $this->re("#{$this->opt($this->t('Flight Time'))}[\s:]+(.+)#", $node);
            $seg['TraveledMiles'] = $this->re("#{$this->opt($this->t('Miles Flown'))}[\s:]+(.+)#", $node);
            $seg['Aircraft'] = $this->re("#{$this->opt($this->t('Aircraft'))}[\s:]+(.+)#", $node);
            $seg['Cabin'] = $this->re("#{$this->opt($this->t('Cabin'))}[\s:]+(.+)#", $node);
            $seg['Operator'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root, true, "#{$this->opt($this->t('Operated by'))}\s+(.+)#");
            $node = implode("\n", $this->http->FindNodes("./following-sibling::tr[1]/td[3]//text()", $root));

            if (preg_match("#^(\d+:\d+(?:\s*[ap]m)?)\s*(\w+\s+\d+)$#is", $node, $m)) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->nice($m[2] . ' ' . $m[1])));
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[1]/td[4]", $root);

            if (preg_match("#(.+)\s+\(([A-Z]{3})\),\s+(.*?)(?:\s*Terminal[\s:]+(.+)|$)#", $node, $m)) {
                $seg['ArrName'] = $m[1] . ' - ' . $m[3];
                $seg['ArrCode'] = $m[2];

                if (isset($m[4])) {
                    $seg['ArrivalTerminal'] = $m[4];
                }
            }
            $node = implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(),'Seats')]"));

            if (isset($seg['DepCode'], $seg['ArrCode']) && preg_match_all("#{$seg['DepCode']}[\s\-]+{$seg['ArrCode']}[\s:]+(\d+[A-Za-z])(?:,|$)#m", $node, $m)) {
                $seg['Seats'] = $m[1];
            }

            $it['TripSegments'][] = $seg;
        }
        $its[] = $it;

        return $its;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^(\w+)\s+(\d+)\s+(\d+:\d+(?:\s*[ap]m)?)$#i',
        ];
        $out = [
            '$2 $1 ' . $year . ', $3',
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
                foreach ($reBody as $re) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$re[0]}')]")->length > 0
                        && $this->http->XPath->query("//*[contains(normalize-space(.),'{$re[1]}')]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
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
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
