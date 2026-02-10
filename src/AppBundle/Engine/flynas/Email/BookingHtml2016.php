<?php

namespace AwardWallet\Engine\flynas\Email;

class BookingHtml2016 extends \TAccountChecker
{
    public $mailFiles = "flynas/it-10408376.eml, flynas/it-29722620.eml, flynas/it-53914609-ar.eml, flynas/it-6144771.eml, flynas/it-6144783.eml, flynas/it-6606746.eml, flynas/it-619476696.eml, flynas/it-615345903-ar.eml";

    public static $dict = [
        'en' => [
            'Departs'           => ['Departs', 'DEPARTS', 'DEPARTURE'],
            'Arrives'           => ['Arrives', 'ARRIVES', 'ARRIVAL'],
            'Booking Reference' => ['Booking Reference', 'Booking Number'],
        ],
        'ar' => [
            'Departs'                 => 'المغادرة',
            'Arrives'                 => 'الوصول',
            'Booking Reference'       => 'رقم الحجز',
            'PASSENGER NAME'          => 'إسم المسافر',
            'SEAT'                    => 'المقعد',
            'Airport'                 => 'مطار',
            'Terminal'                => 'صالة',
            'Your booking earned you' => 'جمعت',
            'SMILE Points'            => 'نقطة سمايلز',
            'Fare Price'              => 'أسعار التذاكر',
            'Total Fare'              => 'المجموع',
        ],
    ];
    protected $lang = '';
    private $keywordProv = "flynas";
    private $subject = [
        'en' => ['flynas Booking Confirmation ('],
    ];
    private $body = [
        'en' => [
            'Thank you for booking with flynas.',
            'Thank you for booking your flynas.',
        ],
        'ar' => [
            'شكرا لحجزكم على طيران ناس.',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    private $seats = [];
    private $meals = [];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && isset($headers['from'])
            && (stripos($headers['from'], 'no-reply@flynas.com') !== false
                || strpos($headers['subject'], $this->keywordProv) !== false)
            && $this->detect($headers['subject'], $this->subject);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignLang() && $this->detect($parser->getHTMLBody(), $this->body);
    }

    public function detectEmailFromProvider($from)
    {
        return (bool) preg_match("/[@\.]flynas\./", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];

        if ($this->assignLang()) {
            $its = $this->parseEmail();
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'BookingHtml2016' . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    /**
     * Are case sensitive. Example:
     * <pre>
     * var $reSubject = [
     * 'en' => ['Reservation Modify'],
     * ];
     * </pre>.
     *
     * @param string $haystack
     */
    private function detect($haystack, array $arrayNeedle): ?string
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }

        return null;
    }

    private function parseEmail(): array
    {
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Reference'))}]/ancestor::tr[1]/following-sibling::tr[1]/descendant::text()[normalize-space(.)!=''][1]", null, true, '/^[A-Z\d]{5,6}$/');
        $xpathSeats = "//tr[{$this->contains($this->t('PASSENGER NAME'), 'normalize-space(.)')} and {$this->contains($this->t('SEAT'), 'normalize-space(.)')}]/following-sibling::tr[1]/descendant::tr[count(td)=2]/td[2]/descendant::tr/td[1]";
        $fns = $this->http->FindNodes($xpathSeats, null, '/([A-Z\d]{2}\d+)/');

        foreach ($fns as $fn) {
            $this->seats[$fn] = array_filter($this->http->FindNodes("//td[normalize-space(.)='{$fn}' and not(.//td)]/following-sibling::td[1]", null, '/([A-Z\d]{1,4})/'));
            $this->meals[$fn] = implode("|", array_unique(array_filter(array_map(function ($s) {return trim($s, ' -'); }, $this->http->FindNodes("//td[normalize-space(.)='{$fn}' and not(.//td)]/following-sibling::td[last()]")))));
        }
        $result += $this->parseAdditionally();
        $result += $this->parseSegments();

        return [$result];
    }

    private function parseSegments(): array
    {
        $result = [];

        foreach ($this->http->XPath->query("//text()[{$this->contains($this->t('Departs'), 'normalize-space(.)')}]/ancestor::tr[2]") as $root) {
            $i = ['DepCode' => TRIP_CODE_UNKNOWN, 'ArrCode' => TRIP_CODE_UNKNOWN];

            $nodeCheckDep = $this->http->FindNodes("td[position() = 1 or position()=3][{$this->contains($this->t('Departs'), 'normalize-space(.)')}]//text()", $root);
            $nodeCheckArr = $this->http->FindNodes("td[position()=1 or position()=3][{$this->contains($this->t('Arrives'), 'normalize-space(.)')}]//text()", $root);
            $node1 = $this->http->FindNodes("td[1]//text()", $root);
            $node3 = $this->http->FindNodes("td[3]//text()", $root);

            if ($nodeCheckDep === $node3 && $nodeCheckArr === $node1) {
                $leftName = 'ArrName';
                $leftTerminal = 'ArrivalTerminal';
                $leftDate = 'ArrDate';
                $rightName = 'DepName';
                $rightTerminal = 'DepartureTerminal';
                $rightDate = 'DepDate';
            } else {
                $leftName = 'DepName';
                $leftTerminal = 'DepartureTerminal';
                $leftDate = 'DepDate';
                $rightName = 'ArrName';
                $rightTerminal = 'ArrivalTerminal';
                $rightDate = 'ArrDate';
            }

            // King Abdulaziz International Airport - Main Terminal – New Airport
            $pattern0_A = "/^(?<airport>.+\s{$this->opt($this->t('Airport'))})[-–\s]+(?<terminal>\S.*)$/iu";

            // مطار سفنكس الدولى - مبنى الركاب 1
            // مطار الملك خالد الدولي - صالة 3
            $pattern0_B = "/^(?<airport>{$this->opt($this->t('Airport'))}.+?)\s+[-–]+\s+(?<terminal>\S[^-–]*|{$this->opt($this->t('Terminal'))}.*)$/iu";

            /*
                Departs
                JEDDAH 19:30
                King Abdulaziz International Airport Main Terminal – New Airport
                Wednesday 01 February 2017
            */
            $pattern1 = "/\n(?<city>\w.+?)\s+(?<time>{$this->patterns['time']})\n+"
            . "(?:(?<terminal>.+)\n+)?"
            . ".*(?<date>\b\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}\b)"
            . "/u";

            if (preg_match($pattern1, join("\n", $node1), $matches)) {
                $airportDep = $matches['city'];

                if (!empty($matches['terminal'])
                    && (preg_match($pattern0_A, $matches['terminal'], $m) || preg_match($pattern0_B, $matches['terminal'], $m))
                ) {
                    $airportDep = $m['airport'];
                    $matches['terminal'] = $m['terminal'];
                }

                $i[$leftName] = $airportDep;

                if (!empty($matches['terminal']) && preg_match("/^.*{$this->opt($this->t('Terminal'))}.*$/iu", $matches['terminal'])) {
                    $i[$leftTerminal] = ReminderHtml2022::normalizeTerminal($matches['terminal'], $this->t('Terminal'));
                }
                $i[$leftDate] = strtotime($matches['time'], strtotime($this->dateStringToEnglish($matches['date'])));
            }

            /*
                XY412
                2h 5m
                Economy
            */
            $pattern2 = "/"
            . "\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)"
            . "(?:\s+(?<duration>\d[hm \d]+))?"
            . "\s+(?<cabin>\w+)"
            . "/u";

            if (preg_match($pattern2, join("\n", $this->http->FindNodes('td[2]//text()', $root)), $matches)) {
                $i['AirlineName'] = $matches['name'];
                $i['FlightNumber'] = $matches['number'];

                if (!empty($matches['duration'])) {
                    $i['Duration'] = $matches['duration'];
                }
                $i['BookingClass'] = $matches['cabin'];
            }

            /*
                Arrives
                21:35 DAMMAM
                King Abdulaziz International Airport Main Terminal – New Airport
                Wednesday 01 February 2017
            */
            $pattern3 = "/\n(?<time>{$this->patterns['time']})\s+(?<city>\w.+)\n+"
            . "(?:(?<terminal>.+)\n+)?"
            . ".*(?<date>\b\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}\b)"
            . "/u";

            if (preg_match($pattern3, join("\n", $node3), $matches)) {
                $airportArr = $matches['city'];

                if (!empty($matches['terminal'])
                    && (preg_match($pattern0_A, $matches['terminal'], $m) || preg_match($pattern0_B, $matches['terminal'], $m))
                ) {
                    $airportArr = $m['airport'];
                    $matches['terminal'] = $m['terminal'];
                }

                $i[$rightName] = $airportArr;

                if (!empty($matches['terminal']) && preg_match("/^.*{$this->opt($this->t('Terminal'))}.*$/iu", $matches['terminal'])) {
                    $i[$rightTerminal] = ReminderHtml2022::normalizeTerminal($matches['terminal'], $this->t('Terminal'));
                }
                $i[$rightDate] = strtotime($matches['time'], strtotime($this->dateStringToEnglish($matches['date'])));
            }

            if (!empty($this->seats[$i['AirlineName'] . $i['FlightNumber']])) {
                $i['Seats'] = $this->seats[$i['AirlineName'] . $i['FlightNumber']];
            }

            if (!empty($this->meals[$i['AirlineName'] . $i['FlightNumber']])) {
                $i['Meal'] = $this->meals[$i['AirlineName'] . $i['FlightNumber']];
            }
            $result['TripSegments'][] = $i;
        }

        return $result;
    }

    private function parseAdditionally(): array
    {
        $result = [];

        $travellers = $infants = [];

        foreach ($this->http->XPath->query("//text()[{$this->eq($this->t('PASSENGER NAME'))}]/ancestor::tr[2]/following-sibling::tr[not(.//*[contains(., '{$this->t('Total Fare')}')])]") as $root) {
            $nameVal = implode("\n", $this->http->FindNodes("descendant-or-self::tr[ normalize-space() and *[2] ][1]/*[1]/descendant::text()[normalize-space()]", $root));
            $nameVal = preg_replace("/\n+Wheelchair for Carry$/im", '', $nameVal);

            if (preg_match("/^({$this->patterns['travellerName']})\s*{$this->opt($this->t('Infant'))}\s*[:]+\s*({$this->patterns['travellerName']})$/u", $nameVal, $m)) {
                $travellers[] = $m[1];
                $infants[] = $m[2];
            } elseif (preg_match("/^{$this->patterns['travellerName']}(?:\s*{$this->opt($this->t('Infant'))}\s*:|$)/u", $nameVal)) {
                $travellers[] = $nameVal;
            } else {
                $travellers = $infants = [];
                $this->logger->debug('Wrong passenger name!');

                break;
            }
        }

        if (count($infants) > 0) {
            $travellers = array_merge($travellers, $infants);
        }

        if (count($travellers) > 0) {
            $result['Passengers'] = $travellers;
        }

        $currency = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Price'))}]/ancestor::td[1]/following-sibling::td[1]");
        $currency = str_replace('ريال', 'SAR', $currency);
        $result['Currency'] = $currency;
        $result['BaseFare'] = preg_replace('/[^\d.]+/', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare Price'))}]/ancestor::td[1]/following-sibling::td[2]", null, true, "#^\s*\d.+#"));
        $result['TotalCharge'] = preg_replace('/[^\d.]+/', '', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Fare'))}]/ancestor::td[1]/following-sibling::td[2]", null, true, "#^\s*\d.+#"));
        $earned = implode(' ', $this->http->FindNodes("//text()[{$this->contains($this->t('Your booking earned you'), 'normalize-space()')}]/ancestor::tr[1]/descendant::text()[normalize-space()]"));

        if (preg_match("/\d[,.‘\'\d ]*\s+{$this->opt($this->t('SMILE Points'))}/u", $earned, $m)
            || preg_match("/{$this->opt($this->t('SMILE Points'))}\s+\d[,.‘\'\d ]*\b/u", $earned, $m) // ar
        ) {
            $result['EarnedAwards'] = $m[0];
        }

        return $result;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Departs"], $words["Booking Reference"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Departs'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Booking Reference'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field, $str1 = 'normalize-space(text())', $operator = 'or'): string
    {
        $array = (array) $field;
        $arr = [];

        foreach ($array as $str2) {
            $arr[] = "contains({$str1}, '{$str2}')";
        }

        return "(" . join(" {$operator} ", $arr) . ")";
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return "(" . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ")";
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
