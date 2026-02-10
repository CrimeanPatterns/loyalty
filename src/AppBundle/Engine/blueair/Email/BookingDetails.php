<?php

namespace AwardWallet\Engine\blueair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingDetails extends \TAccountChecker
{
    public $mailFiles = "blueair/it-26989004.eml, blueair/it-27835346.eml , blueair/it-53058474.eml";
    private $subjects = [
        'ro' => ['- Itinerariu'],
        'it' => ['Blue Air Purchase'],
        'en' => ['- Itinerary'],
    ];
    private $langDetectors = [
        'ro' => ['Informatii despre zbor', 'Rezervarea ta'],
        'it' => ['Informazioni di volo'],
        'en' => ['Your flight information', 'Your flight booking'],
    ];
    private $lang = '';
    private static $dict = [
        'ro' => [
            'Booking Code' => 'Cod rezervare',
            'Total cost:'  => 'Total:',
            'FLIGHT:'      => 'ZBOR:',
            'DEP'          => 'PLECARE',
            'ARR'          => 'ATERIZARE',
            'Passenger'    => 'Pasageri',
            'Meal'         => 'Mancare',
            'Seat'         => 'Loc',
        ],
        'it' => [
            'Booking Code' => 'Codice di prenotazione',
            'Total cost:'  => 'Costo totale:',
            'FLIGHT:'      => 'VOLO:',
            'DEP'          => 'PARTENZA',
            'ARR'          => 'ARRIVO',
            'Passenger'    => 'Passeggero',
            'Meal'         => 'Cibo',
            'Seat'         => 'Posto',
        ],
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Blue Air') !== false
            || preg_match('/[.@]blueair\./i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Blue Air') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Blue Air") or contains(.,"www.blueairweb.com") or contains(.,"@blueair.aero")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.blueairweb.com/")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('BookingDetails' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Code'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Code'))}]/following::text()[normalize-space(.)][1]", null, true, '/^([A-Z\d]{5,})$/');
        $f->general()->confirmation($confirmationNumber, $confirmationNumberTitle);

        // p.total
        // p.currencyCode
        $xpathFragmentPrice = "//text()[{$this->eq($this->t('Total cost:'))}]/ancestor::td[1]/following::td[normalize-space(.)][1]/descendant::text()[normalize-space(.)]";
        $amount = $this->http->FindSingleNode($xpathFragmentPrice . '[1]', null, true, '/^\d[,.\'\d ]*$/');
        $currency = $this->http->FindSingleNode($xpathFragmentPrice . '[2]', null, true, '/^[A-Z]{3}$/');

        if ($amount && $currency) {
            $f->price()
                ->total($this->normalizeAmount($amount))
                ->currency($currency);
        }

        // segments
        $segments = $this->http->XPath->query("//tr[ ./*[1]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('DEP'))}] and ./*[3]/descendant::text()[normalize-space(.)][1][{$this->eq($this->t('ARR'))}] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $xpathFragmentFlight = "./preceding::table[normalize-space(.)][1]/descendant::text()[{$this->eq($this->t('FLIGHT:'))}]";

            $date = $this->http->FindSingleNode($xpathFragmentFlight . "/preceding::text()[normalize-space(.)][1]", $segment);

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode($xpathFragmentFlight . "/following::text()[normalize-space(.)][1]", $segment);

            if (!empty($flight)) {
                $flights[] = $flight;

                if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $matches)) {
                    $s->airline()
                        ->name($matches['airline'])
                        ->number($matches['flightNumber']);

                    // meal
                    $meals = $this->http->FindNodes("//tr[ not(.//tr) and ./*[2][{$this->eq($this->t('Meal'))}] ]/following::tr[normalize-space(.) and not(./*[1]/descendant::table)][1]/*[2]/descendant::td[not(.//td) and {$this->starts($flight)}]",
                        null, '/:\s*(.+)$/');
                    $meals = array_filter(array_unique($meals));

                    if (count($meals)) {
                        $s->extra()->meal(implode(' ', $meals));
                    }

                    // seats
                    $seats = $this->http->FindNodes("//text()[{$this->eq($this->t('Seat'))}]/ancestor::tr/following-sibling::tr[{$this->contains($flight)}]/descendant::table[4]",
                        null, '/:\s*(\d{1,5}[A-Z])$/');
                    $seats = array_filter(array_unique($seats));

                    if (count($seats)) {
                        $s->extra()->seats($seats);
                    }
                }
            }
            // depCode
            $s->departure()->code($this->http->FindSingleNode('./*[1]/descendant::text()[normalize-space(.)][3]', $segment, true, '/^[A-Z]{3}$/'));

            $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?'; // 4:19PM    |    2:00 p.m.    |    3pm

            // depDate
            $timeDep = $this->http->FindSingleNode('./*[1]/descendant::text()[normalize-space(.)][2]', $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeDep) {
                $s->departure()->date2($date . ' ' . $timeDep);
            }

            // duration
            $s->extra()->duration($this->http->FindSingleNode('./*[2]', $segment, true, '/^\d[\d hmin]*$/i'));

            // arrCode
            $s->arrival()->code($this->http->FindSingleNode('./*[3]/descendant::text()[normalize-space(.)][3]', $segment, true, '/^[A-Z]{3}$/'));

            // arrDate
            $timeArr = $this->http->FindSingleNode('./*[3]/descendant::text()[normalize-space(.)][2]', $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeArr) {
                $s->arrival()->date2($date . ' ' . $timeArr);
            }
        }

        // travellers
        if (!empty($flights)) {
            $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger'))}]/ancestor::tr/following-sibling::tr[{$this->contains($flights)}]/descendant::td[2]",
                null, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
            $passengers = array_filter(array_unique($passengers));
        }

        if (!empty($passengers)) {
            $f->general()->travellers($passengers, true);
        } else {
            $this->logger->alert("no passengers, need to check!");
            $f->general()->traveller($passengers);
        }
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ")='" . $s . "'"; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . "),'" . $s . "')"; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
