<?php

namespace AwardWallet\Engine\finnair\Email;

class Changed extends \TAccountChecker
{
    public $mailFiles = "finnair/it-12233719.eml, finnair/it-4327143.eml, finnair/it-5068550.eml, finnair/it-5898025.eml";

    public $reBody = [
        'en' => ['FINNAIR INFO', 'The schedule of your flight'],
        'fi' => ['FINNAIR INFO', 'aikataulu on muuttunut'],
    ];

    public $reSubject = [
        'FINNAIR\s+INFO:\s+The\s+schedule\s+of\s+your\s+flight\s+(.+?)\s+has\s+been\s+changed',
        'FINNAIR\s+INFO:\s+Lentosi\s+(.+?)\s+aikataulu\s+on\s+muuttunut',
    ];

    public $lang = 'en';

    public static $dict = [
        'en' => [],
        'fi' => [
            'Reservation code'            => 'Varaustunnus',
            'The schedule of your flight' => 'Lentosi',
            'has been changed'            => 'aikataulu on muuttunut',
            'New departure time'          => 'Uusi lähtöaika',
            'New arrival time'            => 'Uusi saapumisaika',
            'at'                          => 'kello',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->assignLang($body);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Changed_' . $this->lang,
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,"//www.finnair.com/INT/GB/itinerary")]')->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match('/' . $reSubject . '/ui', $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Finnair Customer Service') !== false
            || stripos($from, '@finnair.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $patterns = [
            //20.06.2015 kello 07:10 (fi)     or         20.06.2015 at 07:10 (en)
            'dateTime' => '/(\d{1,2}\.\d{1,2}\.\d{2,4})\s+(?:' . $this->opt($this->t('at')) . ')?\s*(\d{1,2}:\d{2})$/',
        ];

        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Reservation code'))}]",
            null, true, '/\s+([A-Z\d]{5,})$/');

        if (!empty($pax = $this->http->FindSingleNode("//text()[{$this->eq('FINNAIR INFO:')}]/following::text()[normalize-space(.)!=''][1][not({$this->starts($this->t('Reservation code'))})]"))) {
            $it['Passengers'][] = $pax;
        }

        $xpath = "//text()[{$this->starts($this->t('The schedule of your flight'))}]/ancestor-or-self::node()[{$this->contains($this->t('has been changed'))}][1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $seg = [];
            $node = $this->http->FindSingleNode('.', $root);

            if (preg_match('/([A-Z\d]{2})\s*(\d+)\s+(\d{1,2}\.\d{1,2}\.\.?\d{2,4})\s+(.+?)(?:\s*[–-]\s*(.+?))?\s+' . $this->opt($this->t('has been changed')) . '/',
                $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
                $seg['DepName'] = $m[4];

                if (!empty($m[5])) {
                    $seg['ArrName'] = trim(str_replace("\x80\x93", "", $m[5]));
                }
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }
            $node = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<4 and ({$this->contains($this->t('New departure time'))})]",
                $root);

            if (preg_match($patterns['dateTime'], $node, $m)) {
                $seg['DepDate'] = strtotime($m[1] . ', ' . $m[2]);
            }
            $node = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][position()<5 and ({$this->contains($this->t('New arrival time'))})]",
                $root);

            if (preg_match($patterns['dateTime'], $node, $m)) {
                $seg['ArrDate'] = strtotime($m[1] . ', ' . $m[2]);
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        return true;
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
