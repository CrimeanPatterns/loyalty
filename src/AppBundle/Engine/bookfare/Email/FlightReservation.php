<?php

namespace AwardWallet\Engine\bookfare\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class FlightReservation extends \TAccountChecker
{
    public $mailFiles = "bookfare/it-12232044.eml";
    public $reBody = [
        'en' => ['Your Flight reservation (PNR) number is', 'Arrive'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Airfare'              => ['Airfare', 'AirBaseFare'],
            'Taxes & Airline Fees' => ['Taxes & Airline Fees', 'AirTax'],
        ],
    ];

    private $headers = [
        'bookfare' => [
            'from' => [
                'bookflightsfare.com',
            ],
            'subj' => [
                'Confirmed - Your Flight Reservation',
            ],
        ],
    ];
    private $bodies = [
        'bookfare' => [
            '//a[contains(@href,\'bookflightsfare.com\')] | //img[contains(@src,\'bookflightsfare.com\')]',
        ],
    ];
    private $code;
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $body = $this->http->Response['body'];
        $this->AssignLang($body);

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);
        $result = [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];

        if ($this->getProvider($parser) && $this->code !== 'bookfare') {
            $result['providerCode'] = $this->code;
        }

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->getProvider($parser)) {
            $body = $parser->getHTMLBody();

            return $this->AssignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
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

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your Flight reservation (PNR) number is'))}]/following::text()[normalize-space(.)!=''][1]");
        $it['Status'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Status'))}]/following::text()[normalize-space(.)!=''][1]");
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->starts($this->t('Title:'))}]/ancestor::tr[1][{$this->contains($this->t('Name'))}]/following-sibling::tr/td[normalize-space(.)!=''][2]");

        $xpath = "//text()[{$this->eq($this->t('Item Name'))}]/ancestor::tr[1]/*[self::td or self::th][normalize-space(.)][last()][{$this->eq($this->t('Total'))}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Airfare'))}]/ancestor::tr[1]/*[self::td or self::th][normalize-space(.)!=''][last()]",
                $root));

            if (!empty($tot['Total'])) {
                $it['BaseFare'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Taxes & Airline Fees'))}]/ancestor::tr[1]/*[self::td or self::th][normalize-space(.)!=''][last()]",
                $root));

            if (!empty($tot['Total'])) {
                $it['Tax'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
            $tot = $this->getTotalCurrency($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('TOTAL DUE'))}]/ancestor::tr[1]/*[self::td or self::th][normalize-space(.)!=''][last()]",
                $root));

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }
        }

        $xpath = "//text()[{$this->starts($this->t('Depart:'))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $node = $this->http->FindSingleNode("./preceding-sibling::tr[1]", $root);

            if (preg_match("#{$this->opt($this->t('Airline'))}:\s+(.+),?\s+{$this->opt($this->t('Flight'))}[\s\#]+(\d+)[\*\s]*(?:{$this->opt($this->t('Flight operated by'))}:(.*))?#",
                $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (isset($m[3]) && !empty(trim($m[3]))) {
                    $seg['Operator'] = $m[3];
                }
            }

            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root);
            //Thu, Feb 23 08:39 PM [ Houston-intercontinental, TX, US (IAH) ] - Terminal C
            if (preg_match("#(.+)\s+\[\s*(.+?)\s+\(([A-Z]{3})\)\s*\][\s\-]*(?:{$this->opt($this->t('Terminal'))}\s+(.+))?#",
                $node, $m)) {
                $seg['DepDate'] = $this->normalizeDate($m[1]);
                $seg['DepName'] = $m[2];
                $seg['DepCode'] = $m[3];

                if (isset($m[4]) && !empty(trim($m[4]))) {
                    $seg['DepartureTerminal'] = $m[4];
                }
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[1][contains(.,'Arrive')]/td[normalize-space(.)!=''][2]",
                $root);

            if (preg_match("#(.+)\s+\[\s*(.+?)\s+\(([A-Z]{3})\)\s*\][\s\-]*(?:{$this->opt($this->t('Terminal'))}\s+(.+))?#",
                $node, $m)) {
                $seg['ArrDate'] = $this->normalizeDate($m[1]);
                $seg['ArrName'] = $m[2];
                $seg['ArrCode'] = $m[3];

                if (isset($m[4]) && !empty(trim($m[4]))) {
                    $seg['ArrivalTerminal'] = $m[4];
                }
            }

            $node = $this->http->FindSingleNode("./following-sibling::tr[2][{$this->contains($this->t('Class'))}]/td[normalize-space(.)!=''][2]",
                $root);

            if (preg_match("#^(.+?)\s*(?:\(([A-Z]{1,2})\)|$)#", $node, $m)) {
                $seg['Cabin'] = $m[1];

                if (isset($m[2]) && !empty(trim($m[2]))) {
                    $seg['BookingClass'] = $m[2];
                }
            }

            $seg['Aircraft'] = $this->http->FindSingleNode("./following-sibling::tr[3][{$this->contains($this->t('Airplane Name'))}]/td[normalize-space(.)!=''][2]",
                $root);

            if (isset($seg['DepName'], $seg['ArrName'])) {
                $node = $this->http->FindSingleNode("./preceding-sibling::tr[2][({$this->starts($this->t('From:'))}) and ({$this->contains($this->t('to:'))}) and contains(.,'{$seg['DepName']}') and contains(.,'{$seg['ArrName']}')]",
                    $root);

                if (preg_match("#{$this->opt($this->t('Duration'))}:\s+(d+.*?)\s+{$this->opt($this->t('Stops'))}:\s+(\d+)#",
                    $node, $m)) {
                    $seg['Duration'] = $m[1];
                    $seg['Stops'] = $m[2];
                }
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //Thu, Feb 23 05:44 PM
            '#^(\w+),\s+(\w+)\s+(\d+)\s+(\d+:\d+(?:\s*[ap]m)?)$#iu',
        ];
        $out = [
            '$3 $2 ' . $year . ' $4',
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return true;
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        $this->code = $code;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
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
        $node = str_replace("C$", "CAD", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
