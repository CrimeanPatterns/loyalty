<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Schema\Parser\Email\Email;

class HTMLTicket extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-5475174.eml, cleartrip/it-6507766.eml, cleartrip/it-9950853.eml, cleartrip/it-4297282.eml, cleartrip/it-29218486.eml";

    private $subjects = [
        'en' => ['Ticket for '],
    ];
    private $lang = '';
    private $langDetectors = [
        'en' => ['Airline PNR'],
    ];
    private static $dictionary = [
        'en' => [
            'Trip ID:'        => ['Trip ID:', 'Trip ID :'],
            'Total fare:'     => ['Total fare:', 'Total Fare:'],
            'Base fare:'      => ['Base fare:', 'Base Fare:'],
            'Taxes and fees:' => ['Taxes and fees:', 'Taxes and Fees:'],
        ],
    ];

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Cleartrip Booking') !== false
            || stripos($from, '@cleartrip.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Language
        if (!$this->assignLang()) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }
        $this->parseEmail($email);
        $email->setType('HTMLTicket' . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['cleartrip', 'expedia'];
    }

    private function parseEmail(Email $email)
    {
        $patterns = [
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後
            'travellerName' => '[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $email->ota(); // because Cleartrip is global travel aggregator

        $tripId = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Trip ID:'))}]/following::text()[normalize-space(.)][1]", null, true, '/^(\d{7,})$/');
        $email->ota()->confirmation($tripId);

        $f = $email->add()->flight();

        $segments = $this->http->XPath->query("//tr[ ./*[1]/descendant::text()[normalize-space(.)][1][string-length(normalize-space(.))=3] and ./*[2][./descendant::img] and ./*[3]/descendant::text()[normalize-space(.)][last()][string-length(normalize-space(.))=3] ]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            // depCode
            $depCode = $this->http->FindSingleNode('./*[1]/descendant::text()[normalize-space(.)][1]', $segment, true, '/^([A-Z]{3})$/');
            $s->departure()->code($depCode);

            $timeDep = $this->http->FindSingleNode('./*[1]/descendant::text()[normalize-space(.)][last()]', $segment, true, "/^({$patterns['time']})$/");
            $timeArr = $this->http->FindSingleNode('./*[3]/descendant::text()[normalize-space(.)][1]', $segment, true, "/^({$patterns['time']})$/");

            // arrCode
            $arrCode = $this->http->FindSingleNode('./*[3]/descendant::text()[normalize-space(.)][last()]', $segment, true, '/^([A-Z]{3})$/');
            $s->arrival()->code($arrCode);

            $xpathFragmentRow2 = "./following-sibling::tr[normalize-space(.)][1]";

            // depDate
            $dateDep = $this->http->FindSingleNode($xpathFragmentRow2 . '/*[1]', $segment);

            if ($timeDep && $dateDep) {
                $s->departure()->date2($dateDep . ', ' . $timeDep);
            }

            // duration
            $duration = $this->http->FindSingleNode($xpathFragmentRow2 . '/*[2]', $segment, true, '/^\s*((\s*\d+\s*(?:h|m|min|mins))+)\s*$/');
            $s->extra()->duration($duration);

            // arrDate
            $dateArr = $this->http->FindSingleNode($xpathFragmentRow2 . '/*[3]', $segment);

            if ($timeArr && $dateArr) {
                $s->arrival()->date2($dateArr . ', ' . $timeArr);
            }

            $xpathFragmentRow3 = "./following-sibling::tr[normalize-space(.)][2]";

            $patterns['airportTerminal'] = "(?<airport>.{3,})\s+{$this->opt($this->t('Terminal'))}\s+(?<terminal>[A-z\d]+)\b"; // Mumbai - Chatrapati Shivaji Airport Terminal Terminal 1B

            // depName
            // depTerminal
            $airportDep = $this->http->FindSingleNode($xpathFragmentRow3 . '/*[1]', $segment);

            if (preg_match("/^{$patterns['airportTerminal']}$/", $airportDep, $m)) {
                $s->departure()
                    ->name(preg_replace("/\s*{$this->opt($this->t('Terminal'))}$/", '', $m['airport']))
                    ->terminal($m['terminal'])
                ;
            } else {
                $s->departure()->name($airportDep);
            }

            // cabin
            $class = $this->http->FindSingleNode($xpathFragmentRow3 . '/*[2]', $segment);
            $s->extra()->cabin($class);

            // Seats
            $seats = preg_split('/\s*,\s*/', $this->http->FindSingleNode($xpathFragmentRow3 . "/following::*[normalize-space()][1]//td[{$this->starts($this->t('Seats'))}]",
                $segment, true, "/{$this->opt($this->t('Seats'))}\s*\W\s*(\d{1,3}[A-Z](?:\s*,\s*\d{1,3}[A-Z])*)$/u"));

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
            // Meal
            $meal = $this->http->FindSingleNode($xpathFragmentRow3 . "/following::*[normalize-space()][1]//td[{$this->starts($this->t('Meals booked'))}]",
                $segment, true, "/{$this->opt($this->t('Meals booked'))}\s*\W\s*(.+?)\s*$/u");

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            // arrName
            // arrTerminal
            $airportArr = $this->http->FindSingleNode($xpathFragmentRow3 . '/*[3]', $segment);

            if (preg_match("/^{$patterns['airportTerminal']}$/", $airportArr, $m)) {
                $s->arrival()
                    ->name(preg_replace("/\s*{$this->opt($this->t('Terminal'))}$/", '', $m['airport']))
                    ->terminal($m['terminal'])
                ;
            } else {
                $s->arrival()->name($airportArr);
            }

            $xpathFragmentFlight = "./preceding::tr[not(.//tr) and normalize-space(.)][1]";

            // operatedBy
            $operator = $this->http->FindSingleNode($xpathFragmentFlight . "/preceding-sibling::tr[{$this->contains($this->t('Operated by'))}]", $segment, true, "/{$this->opt($this->t('Operated by'))}\s*(.+)/");
            $s->airline()->operator($operator, false, true);

            // airlineName
            // flightNumber
            $flight = $this->http->FindSingleNode($xpathFragmentFlight, $segment);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<flightNumber>\d+)\s*(?:$|\s*Fare type)/', $flight, $matches)) {
                $s->airline()
                    ->name($matches['airline'])
                    ->number($matches['flightNumber']);
            }
        }

        // travellers
        // confirmation numbers
        // ticketNumbers
        $travellers = [];
        $airlinePNRs = [];
        $ticketNumbers = [];
        $travellerRows = $this->http->XPath->query("//tr[not(.//tr) and ./*[1][{$this->starts($this->t('Travellers'))}] and ./*[2][{$this->starts($this->t('Airline PNR'))}] and ./*[last()][{$this->starts($this->t('Ticket No.'))}]]/following-sibling::tr[count(./*)>2 and string-length(normalize-space(.))>6]");

        foreach ($travellerRows as $travellerRow) {
            $traveller = $this->http->FindSingleNode('./*[2]/descendant::text()[normalize-space(.)][1]', $travellerRow, true, "/^({$patterns['travellerName']})$/");

            if ($traveller) {
                $travellers[] = $traveller;
            }
            $airlinePNR = implode(',', $this->http->FindNodes('./*[3]//text()[normalize-space()]', $travellerRow, '/^([A-Z\d\s,]{5,})$/'));

            if ($airlinePNR) {
                $airlinePNRs = array_merge($airlinePNRs, preg_split('/\s*,\s*/', $airlinePNR));
            }
            $ticketNumber = implode(',', $this->http->FindNodes('./*[last()]//text()[normalize-space()]', $travellerRow, '/^([-\d\s,]{5,})$/'));

            if ($ticketNumber) {
                $ticketNumbers = array_merge($ticketNumbers, preg_split('/\s*,\s*/', $ticketNumber));
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_values(array_unique($travellers)));
        }
        $airlinePNRs = array_filter(array_values(array_unique($airlinePNRs)));

        foreach ($airlinePNRs as $pnr) {
            $f->general()->confirmation($pnr);
        }

        if (count($ticketNumbers) > 0) {
            $ticketNumbers = array_filter(array_values(array_unique($ticketNumbers)));
            $f->setTicketNumbers($ticketNumbers, false);
        }

        $xpathFragmentFare = "//text()[{$this->eq($this->t('FARE BREAKUP'))}]/following::tr[normalize-space(.)][1]/descendant::ul/li";

        if (count($this->http->FindNodes($xpathFragmentFare)) === 0) {
            $xpathFragmentFare = "//text()[{$this->eq($this->t('FARE BREAKUP'))}]/following::tr/td[1]/descendant::p[normalize-space()]";
        }

        if (count($this->http->FindNodes($xpathFragmentFare)) === 0) {
            $xpathFragmentFare = "//text()[{$this->eq($this->t('FARE BREAKUP'))}]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('FARE BREAKUP'))}) and count(tr[normalize-space()]) > 2][1]/tr";
        }

        // currencyCode
        // p.total
        $payment = $this->http->FindSingleNode($xpathFragmentFare . "[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Total fare:'))}] ]", null, true, "/{$this->opt($this->t('Total fare:'))}\s*(.+)/");

        if (empty($payment)) {
            $payment = $this->http->FindSingleNode($xpathFragmentFare . "[{$this->contains($this->t('Total fare:'))}]", null, true, "/{$this->opt($this->t('Total fare:'))}\s*(.+)/");
        }

        if (preg_match('/^(?<currency>[^\d)(]+)\s*(?<amount>\d[,.\'\d]*)/', $payment, $matches)) {
            // Rs. 92,460
            $f->price()
                ->currency($this->normalizeCurrency($matches['currency']))
                ->total($this->normalizeAmount($matches['amount']))
            ;
            $matches['currency'] = trim($matches['currency']);
            // p.cost
            $baseFare = $this->http->FindSingleNode($xpathFragmentFare . "[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Base fare:'))}] ]", null, true, "/{$this->opt($this->t('Base fare:'))}\s*(.+)/");

            if (empty($baseFare)) {
                $baseFare = $this->http->FindSingleNode($xpathFragmentFare . "[{$this->contains($this->t('Base fare:'))}]", null, true, "/{$this->opt($this->t('Base fare:'))}\s*(.+)/");
            }

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $baseFare, $m)) {
                $f->price()->cost($this->normalizeAmount($m['amount']));
            }
            // p.tax
            $taxes = $this->http->FindSingleNode($xpathFragmentFare . "[ ./descendant::text()[normalize-space(.)][1][{$this->eq($this->t('Taxes and fees:'))}] ]", null, true, "/{$this->opt($this->t('Taxes and fees:'))}\s*(.+)/");

            if (empty($taxes)) {
                $taxes = $this->http->FindSingleNode($xpathFragmentFare . "[{$this->contains($this->t('Taxes and fees:'))}]", null, true, "/{$this->opt($this->t('Taxes and fees:'))}\s*(.+)/");
            }

            if (preg_match('/^' . preg_quote($matches['currency'], '/') . '\s*(?<amount>\d[,.\'\d]*)/', $taxes, $m)) {
                $f->price()->tax($this->normalizeAmount($m['amount']));
            }

            //Fee
            $fees = $this->http->FindNodes($xpathFragmentFare . "[not({$this->contains($this->t('Base fare:'))}) and not({$this->contains($this->t('Total fare:'))}) and not({$this->contains($this->t('Taxes and fees:'))})]");

            $discount = 0.0;

            foreach ($fees as $fee) {
                if (preg_match("/^(?<name>\D+)\:\D*\s(?<discount>-)?(?<amount>\d[\d\.\, ]*)\s*$/", $fee, $m)) {
                    if (!empty($m['discount'])) {
                        $discount += $this->normalizeAmount($m['amount']);
                    } else {
                        $f->price()->fee($m['name'], $this->normalizeAmount($m['amount']));
                    }
                } else {
                    $f->price()->fee($fee, null);
                }
            }

            if (!empty($discount)) {
                $f->price()->discount($discount);
            }
        }

        return true;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(?string $s): ?string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);

        if ($code = $this->re("/^([A-Z]{3})\.?$/", $string)) {
            return $code;
        }

        $currences = [
            'INR' => ['Rs.'],
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$', 'US$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignProvider($headers): bool
    {
        $condition1 = strpos($headers['from'], 'Cleartrip Booking') !== false || stripos($headers['from'], '@cleartrip.') !== false;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your tickets with Cleartrip Customer Care") or contains(normalize-space(.),"Cleartrip support") or contains(.,"@cleartrip.")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'cleartrip';

            return true;
        }

        $condition1 = preg_match('/[.@]expedia\.com/i', $headers['from']) > 0;
        $condition2 = $this->http->XPath->query('//node()[contains(normalize-space(.),"your tickets with Expedia Customer Care")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'expedia';

            return true;
        }

        return false;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
