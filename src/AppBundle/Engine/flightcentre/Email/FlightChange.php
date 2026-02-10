<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;

// TODO: merge with parser velocity/It2818504 (in favor of velocity/It2818504)

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-60413839.eml, flightcentre/it-6771256.eml";

    public $reFrom = ["flightcentre.com.au", '@reservations.virginaustralia.com'];
    public $reBody = [
        'en' => ['Flight Change Notification', 'New Itinerary', 'We are writing to advise of a change to your upcoming flight'],
    ];
    public $reSubject = [
        'Flight Change Notification',
    ];
    public $lang = '';
    public $date;
    public static $dict = [
        'en' => [
            'BOOKING NUMBER' => ['BOOKING NUMBER', 'Booking Reference'],
        ],
    ];

    private $reProvider = [
        'flightcentre' => [
            'Flight Centre Travel',
            'flightcentre.com.au',
        ],
        'velocity' => [
            'Virgin Australia',
        ],
    ];

    public static function getEmailProviders()
    {
        return ['flightcentre', 'velocity'];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->assignLang();

        $its = $this->parseEmail();

        return [
            'parsedData'   => ['Itineraries' => $its],
            'emailType'    => 'FlightChange' . ucfirst($this->lang),
            'providerCode' => $this->getProvider($parser),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->getProvider($parser)) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && $this->arrikey($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
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
        return $this->arrikey($from, $this->reFrom) !== false;
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

    private function getProvider(\PlancakeEmailParser $parser)
    {
        foreach ($this->reProvider as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    } else {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING NUMBER'))}]/following::text()[normalize-space(.)!=''][1]");

        $nodes = $this->http->XPath->query($xpath = "//span[contains(.,'YOUR NEW ITINERARY')]/following-sibling::table//text()[starts-with(normalize-space(.),'FLIGHT')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]");

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query($xpath = "//text()[starts-with(normalize-space(.),'FLIGHT')]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]");
        }

        foreach ($nodes as $root) {
            $seg = [];
            $node = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Operated by\s*(.+)#i", $node, $m)) {
                $seg['Operator'] = $m[1];
            }
            $node = implode("\n", $this->http->FindNodes("./td[2]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s+(.+)#s", $node, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = $m[2];
                $seg['DepDate'] = $this->normalizeDate($m[3]);
            }
            $node = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space(.)!='']", $root));

            if (preg_match("#(.+?)\s+\(([A-Z]{3})\)\s+(.+)#s", $node, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrCode'] = $m[2];
                $seg['ArrDate'] = $this->normalizeDate($m[3]);
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            '#^\s*(\d+:\d+)\s+\S+?,\s+(\w+)\s+(\d+)\s*$#',
            '#^(\d{1,2}:\d{1,2}[A-z]{2})\s([A-z]{3}),\s([A-z]{3})\s(\d{1,2})$#',
        ];
        $out = [
            '$3 $2 ' . $year . ' $1',
            '$3 $4 ' . $year . ' $1',
        ];
        $outWeek = [
            '',
            '$2',
        ];

        if (preg_match('#^[A-z]{3}$#', $week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            $this->logger->error($this->http->XPath->query("//text()[{$this->contains($value)}]")->length);

            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
