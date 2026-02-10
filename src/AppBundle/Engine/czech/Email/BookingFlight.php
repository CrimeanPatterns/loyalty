<?php

namespace AwardWallet\Engine\czech\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingFlight extends \TAccountChecker
{
    public $mailFiles = "czech/it-29537194.eml"; // +1 bcdtravel(html)[it]
    private $subjects = [
        'en' => ['CSA on-line booking'],
    ];
    private $langDetectors = [
        'it' => ['Codice di prenotazione:'],
        'en' => ['Booking reference:'],
    ];
    private $lang = '';
    private static $dict = [
        'it' => [
            'Booking reference:' => 'Codice di prenotazione:',
            'Date of creation:'  => 'Generato il:',
            'PASSENGER'          => 'PASSEGGERO',
            'PAYMENT'            => 'PAGAMENTO',
            'Amount:'            => 'Ammontare:',
            'ITINERARY'          => 'ITINERARIO DEL SUO VIAGGIO',
            'Operating carrier:' => 'Vettore operativo:',
        ],
        'en' => [],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@czechairlines.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Czech Airlines") or contains(normalize-space(.),"La ringraziamo di aver scelto la Czech Airlines") or contains(.,"@czechairlines.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//booking.csa.cz") or contains(@href,"//www.csa.cz")]')->length === 0;

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
        $email->setType('BookingFlight' . ucfirst($this->lang));

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
        $patterns = [
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?', // 4:19PM    |    2:00 p.m.
        ];

        $xpathFragmentBold = '(self::b or self::strong)';

        $f = $email->add()->flight();

        // confirmation number
        $confirmationNumberTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]");
        $confirmationNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference:'))}]/following::text()[normalize-space(.)][1]", null, true, '/^([A-Z\d]{5,})$/');
        $f->general()->confirmation($confirmationNumber, preg_replace('/\s*:\s*$/', '', $confirmationNumberTitle));

        // reservationDate
        $creationDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Date of creation:'))}]/following::text()[normalize-space(.)][1]");
        $f->general()->date2($creationDate);

        // tarvellers
        $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('PASSENGER'))}]/ancestor::tr[1]/following-sibling::tr[ ./descendant::text()[normalize-space(.) and ./ancestor::*[{$xpathFragmentBold}] and ./ancestor::*[contains(@style,'#064379')]] ]", null, '/^[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]$/u');
        $passengers = array_filter($passengers);
        $f->general()->travellers($passengers);

        // p.total
        // p.currencyCode
        $payment = $this->http->FindSingleNode("//text()[{$this->eq($this->t('PAYMENT'))}]/ancestor::tr[1]/following-sibling::tr/descendant::text()[{$this->eq($this->t('Amount:'))}]/following::text()[normalize-space(.)][1]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)\s*(?<currency>[A-Z]{3})\b/', $payment, $matches)) {
            // 35,730 RUB
            $f->price()
                ->total($this->normalizeAmount($matches['amount']))
                ->currency($matches['currency'])
            ;
        }

        // segments
        $xpathFragment0 = "( ./descendant::text()[normalize-space(.)][./ancestor::*[{$xpathFragmentBold}]] and not(./descendant::text()[normalize-space(.)][not(./ancestor::*[{$xpathFragmentBold}])]) )";
        $xpathFragmentTr = "( ./*[1][{$xpathFragment0}] and ./*[2][{$xpathFragment0}] )";
        $xpathSegments = "//text()[{$this->eq($this->t('ITINERARY'))}]/ancestor::tr[1]/following::tr[ count(./*[normalize-space(.)])=2 and ./preceding-sibling::tr[normalize-space(.)][1][{$xpathFragmentTr}] and ./following-sibling::tr[normalize-space(.)][1][{$xpathFragmentTr}] ]";
        $segments = $this->http->XPath->query($xpathSegments); // target: row with two cell with airport names

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $date = $this->http->FindSingleNode("./preceding-sibling::*[normalize-space(.)][2]/descendant::text()[normalize-space(.)][1]", $segment);

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode("./preceding-sibling::*[normalize-space(.)][2]/descendant::text()[normalize-space(.)][2]", $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            $patterns['airport'] = '/^.+\(\s*([A-Z]{3})\s*\)(?:\s*,.+|$)/';

            // depName
            // depCode
            $airportDep = $this->http->FindSingleNode("./*[1]", $segment);

            if (preg_match($patterns['airport'], $airportDep, $m)) {
                $s->departure()->code($m[1]);
            } elseif ($airportDep) {
                $s->departure()
                    ->name($airportDep)
                    ->noCode()
                ;
            }

            // arrName
            // arrCode
            $airportArr = $this->http->FindSingleNode("./*[2]", $segment);

            if (preg_match($patterns['airport'], $airportArr, $m)) {
                $s->arrival()->code($m[1]);
            } elseif ($airportArr) {
                $s->arrival()
                    ->name($airportArr)
                    ->noCode()
                ;
            }

            // depDate
            $timeDep = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][1]/*[1]", $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeDep) {
                $s->departure()->date2($date . ' ' . $timeDep);
            }

            // arrDate
            $timeArr = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][1]/*[2]", $segment, true, "/^{$patterns['time']}$/");

            if ($date && $timeArr) {
                $s->arrival()->date2($date . ' ' . $timeArr);
            }

            // operatedBy
            $operator = $this->http->FindSingleNode("./following-sibling::*[normalize-space(.)][2]/descendant::text()[{$this->eq($this->t('Operating carrier:'))}]/following::text()[normalize-space(.)][1]", $segment);
            $s->airline()->operator($operator);
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
