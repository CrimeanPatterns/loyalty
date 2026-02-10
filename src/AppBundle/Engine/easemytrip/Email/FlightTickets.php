<?php

namespace AwardWallet\Engine\easemytrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightTickets extends \TAccountChecker
{
    public $mailFiles = "easemytrip/it-217285100.eml, easemytrip/it-261053187.eml, easemytrip/it-711350576.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'direction'    => ['Onward Flight', 'Return Flight'],
            'ticketNumber' => ['Ticket Number', 'TicketNumber', 'Ticket number', 'Ticketnumber'],
            'Seat'         => ['Seat', 'Seat No'],
        ],
    ];

    private $subjects = [
        'en' => ['Your Flight Tickets for'],
    ];

    private $xpath = [
        'time'      => 'contains(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")',
        'noDisplay' => 'not(ancestor-or-self::*[contains(translate(@style," ",""),"display:none")])',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easemytrip.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".easemytrip.com/") or contains(@href,"www.easemytrip.com") or contains(@href,"delivery.easemytrip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"EaseMyTrip Contact Information")]')->length === 0
        ) {
            return false;
        }

        return $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
        $email->setType('FlightTickets' . ucfirst($this->lang));

        $bookingOn = strtotime(
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking on -'))}]/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking on -'))}]", null, true, "/{$this->opt($this->t('Booking on -'))}\s*(.*\d.*)$/")
        );

        // flights

        $flightRoots = $this->http->XPath->query("//*[{$this->eq($this->t('direction'))} and {$this->xpath['noDisplay']}]/ancestor::*[ descendant::tr[{$this->eq($this->t('Traveller Details'))}] ][1]");

        foreach ($flightRoots as $fRoot) {
            $this->parseFlight($email, $fRoot, $bookingOn);
        }

        // price

        $currencyCode = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Fare Details'))}] ]/*[normalize-space()][2]", null, true, "/^{$this->opt($this->t('Amount'))}\s*\(\s*([A-Z]{3})\s*\)$/");

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // RS. 50271.00    |    4131.00
            $currency = empty($matches['currency']) ? null : $this->normalizeCurrency($matches['currency']);

            if (!$currencyCode && $currency) {
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            }

            if (preg_match("/^(\d+\.\d+)\.\d+$/", $matches['amount'], $m)) {
                $matches['amount'] = round($m[1], 2);
            }

            $email->price()
                ->currency($currencyCode ?? $currency)
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Basic Fare'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)) {
                $email->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $discounts = [];

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('Basic Fare'))}]] and following-sibling::tr[*[normalize-space()][1][{$this->eq($this->t('Total'))}]] and *[2][normalize-space()] and {$this->xpath['noDisplay']} ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    // fee
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $email->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                } elseif (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*\([ ]*-[ ]*\)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)) {
                    // discount
                    $discounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                }
            }

            if (count($discounts) > 0) {
                $email->price()->discount(array_sum($discounts));
            }
        }

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

    private function findSegments(\DOMNode $root = null): \DOMNodeList
    {
        return $this->http->XPath->query("descendant::tr[ count(*)=3 and *[1][{$this->xpath['time']}] and *[3][{$this->xpath['time']}] ]", $root);
    }

    private function parseFlight(Email $email, \DOMNode $fRoot, $bookingOn): void
    {
        $patterns = [
            'time' => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        ];

        $f = $email->add()->flight();

        if ($bookingOn) {
            $f->general()->date($bookingOn);
        }

        $travellers = $PNRs = $tickets = $seats = [];

        $travellerRows = $this->http->XPath->query("descendant::tr[ *[1][{$this->eq($this->t('Passenger'))}] and *[3][{$this->eq($this->t('ticketNumber'))}] ]/following-sibling::tr[ normalize-space() and *[3] ]", $fRoot);

        if ($travellerRows->length === 0) {
            $travellerRows = $this->http->XPath->query("descendant::tr[ *[1][{$this->eq($this->t('Passenger'))}] and *[3][{$this->eq($this->t('ticketNumber'))}] ]/following::tbody[1]/descendant::tr[ normalize-space() and *[3] ]", $fRoot);
        }

        foreach ($travellerRows as $tRow) {
            $traveller = $this->http->FindSingleNode('*[1]', $tRow, true, "/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u");

            if ($traveller) {
                $travellers[] = $traveller;
            }

            $pnr = $this->http->FindSingleNode('*[2]', $tRow, true, "/^[A-Z\d]{5,}$/");

            if ($pnr) {
                $PNRs[] = $pnr;
            }

            $ticket = $this->http->FindSingleNode('*[3]', $tRow, true, "/^[-A-Z\d\/]{5,}$/");

            if ($ticket) {
                $tickets[] = $ticket;
            }

            $seatNo = $this->http->FindSingleNode('*[6]', $tRow, true, "/^\d+[-\s]*[A-Z]$/"); // 15C    |    21-F

            if ($seatNo) {
                $seats[] = str_replace([' ', '-'], '', $seatNo);
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(preg_replace("/^(?:Mrs|Mr|Ms)\.?/", "", array_unique($travellers)), true);
        }

        if (count($PNRs) > 0) {
            foreach (array_unique($PNRs) as $pnr) {
                $f->general()
                    ->confirmation($pnr);
            }
        }

        if (count($tickets) > 0) {
            $tickets = array_unique($tickets);

            foreach ($tickets as $ticket) {
                $pax = preg_replace("/^(?:Mrs|Mr|Ms)\s*\.?/u", "", $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking ID'))}]/following::text()[{$this->eq($ticket)}][1]/ancestor::tr[1]/descendant::td[normalize-space()][1]"));

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, $pax);
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $segments = $this->findSegments($fRoot);

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = implode(' ', $this->http->FindNodes("preceding::tr[normalize-space()][1]", $root));

            if (preg_match('/(?:^|\s*)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*-[ ]*(?<number>\d+)(?:\s|\(|$)/', $flight, $m)) {
                // IndiGo 6E-913 (Operated By: 6E)
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                $seatNode = $this->http->XPath->query("//text()[{$this->contains($m['number'])}]/following::text()[{$this->eq($this->t('Traveller Details'))}][1]/following::text()[{$this->eq($this->t('Passenger'))}][1]/ancestor::tr[1]/following-sibling::tr");

                if ($seatNode->length === 0) {
                    $seatNode = $this->http->XPath->query("//text()[{$this->contains($m['number'])}]/following::text()[{$this->eq($this->t('Traveller Details'))}][1]/following::text()[{$this->eq($this->t('Seat'))}][1]/following::tbody[1]/descendant::tr");
                }

                foreach ($seatNode as $seatRoot) {
                    $pax = preg_replace("/^(?:Mrs|Mr|Ms)\.?/", "", $this->http->FindSingleNode("./descendant::td[1]", $seatRoot));
                    $seat = $this->http->FindSingleNode("./descendant::td[5]", $seatRoot, true, "/^(\d+[A-Z\-]+)$/");

                    if (empty($seat)) {
                        $seat = $this->http->FindSingleNode("./descendant::td[6]", $seatRoot, true, "/^(\d+[A-Z\-]+)$/");
                    }

                    if (!empty($pax) && !empty($seat)) {
                        $s->extra()
                            ->seat(str_replace('-', '', $seat), false, false, $pax);
                    }
                }
            }

            $xpathDep = "*[1]/descendant-or-self::*[ *[normalize-space()][2] ][1]";
            $xpathArr = "*[3]/descendant-or-self::*[ *[normalize-space()][2] ][1]";

            $depRow1 = $this->http->FindSingleNode($xpathDep . "/*[normalize-space()][1]", $root);

            if (!preg_match("/\d+\:\d+/", $depRow1)) {
                $depRow1 = $this->http->FindSingleNode($xpathDep . "/*[normalize-space()][2]", $root);
            }

            $arrRow1 = $this->http->FindSingleNode($xpathArr . "/*[normalize-space()][1]", $root);

            if (!preg_match("/\d+\:\d+/", $arrRow1)) {
                $arrRow1 = $this->http->FindSingleNode($xpathArr . "/*[normalize-space()][2]", $root);
            }

            if (preg_match("/^(?<code>[A-Z]{3})\s*(?<time>{$patterns['time']})$/", $depRow1, $m)) {
                $s->departure()->code($m['code']);
                $timeDep = $m['time'];
            } else {
                $timeDep = null;
            }

            $dateDep = $this->http->FindSingleNode($xpathDep . "/*[normalize-space()][2]", $root, true, '/^.*\d.*$/');

            if (preg_match("/\d+\:\d+/", $dateDep)) {
                $dateDep = $this->http->FindSingleNode($xpathDep . "/*[normalize-space()][1]", $root, true, '/^.*\d.*$/');
            }

            $dateDep = strtotime($this->normalizeDate($dateDep));

            if ($timeDep && $dateDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            $terminalDep = $this->http->FindSingleNode($xpathDep . "/*[normalize-space()][3]", $root, false, '/Terminal[-\s]*(.+)$/i');
            $s->departure()->terminal($terminalDep, false, true);

            if (preg_match("/^(?<code>[A-Z]{3})\s*(?<time>{$patterns['time']})$/", $arrRow1, $m)) {
                $s->arrival()->code($m['code']);
                $timeArr = $m['time'];
            } else {
                $timeArr = null;
            }

            $dateArr = $this->http->FindSingleNode($xpathArr . "/*[normalize-space()][2]", $root, true, '/^.*\d.*$/');

            if (preg_match("/\d+\:\d+/", $dateArr)) {
                $dateArr = $this->http->FindSingleNode($xpathArr . "/*[normalize-space()][1]", $root, true, '/^.*\d.*$/');
            }
            $dateArr = strtotime($this->normalizeDate($dateArr));

            if ($timeArr && $dateArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            $terminalArr = $this->http->FindSingleNode($xpathArr . "/*[normalize-space()][3]", $root, false, '/Terminal[-\s]*(.+)$/i');
            $s->arrival()->terminal($terminalArr, false, true);

            $extraText = $this->htmlToText($this->http->FindHTMLByXpath('*[2]', null, $root));

            if (preg_match("/^\s*(?<duration>\d[\d hm]+?)[ ]*(?:\n+[ ]*(?<cabin>.+?)[ ]*)?$/si", $extraText, $m)) {
                $s->extra()->duration($m['duration']);

                if (!empty($m['cabin'])) {
                    $s->extra()->cabin($m['cabin']);
                }
            }

            /*if ($segments->length === 1 && count($seats) > 0) {
                $s->extra()->seats($seats);
            }*/
        }
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'INR' => ['Rs.', 'RS.'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^[-[:alpha:]]+[-,.\s]+(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{4})$/u', $text, $m)) {
            // Tue-18Oct2022
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
