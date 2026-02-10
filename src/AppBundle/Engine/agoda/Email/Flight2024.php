<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Flight2024 extends \TAccountChecker
{
    public $mailFiles = "agoda/it-717460999.eml, agoda/it-713929586.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'otaConfNumber'  => ['Booking ID', 'Departure Booking ID', 'Return Booking ID'],
            'statusPhrases'  => ['Your flight booking has been'],
            'statusVariants' => ['confirmed'],
            'cabinValues'    => ['Economy Classic', 'Economy', 'ECO'],
            // 'Airline Reference' => '',
            // 'Passenger details' => '',
            // 'Adult' => '',
            // 'Birthdate' => '',
            // 'Nationality' => '',
            // 'Ticket number' => '',
            // 'Seat selection' => '',
            // 'Total' => '',
            'feeHeaders' => ['Add-ons'],
            // 'FREE' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]agoda\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return array_key_exists('subject', $headers) && stripos($headers['subject'], 'Booking confirmation with Agoda - Booking ID') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".agoda.com/") or contains(@href,"www.agoda.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing Agoda") or contains(normalize-space(),"This email was sent by: Agoda")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findSegments()->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('Flight2024' . ucfirst($this->lang));

        $patterns = [
            'date'          => '.{4,34}\b\d{4}\b', // Sunday, September 22, 2024
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();
        $confNumbers = $confNumbersSecondary = [];

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $otaConfNumbers = $this->http->XPath->query("//text()[{$this->eq($this->t('otaConfNumber'), "translate(.,':','')")}]");

        foreach ($otaConfNumbers as $ocn) {
            $otaConfirmation = $this->http->FindSingleNode("following::text()[normalize-space()][1]", $ocn, true, '/^[-A-Z\d]{5,25}$/');

            if ($otaConfirmation) {
                $otaConfirmationTitle = $this->http->FindSingleNode(".", $ocn, true, '/^(.+?)[\s:：]*$/u');
                $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
            }
        }

        /*
            Manila (MNL)
            Sunday, September 22, 2024 · 8:15 PM
            Ninoy Aquino International Airport
        */
        $pattern = "/^"
        . "(?<point>.*\b(?<code>[A-Z]{3})[\s)]*)\n"
        . "(?<date>{$patterns['date']})[-–·\s]+(?<time>{$patterns['time']})\n"
        . "(?<name>.{2,})"
        . "$/u";

        $segments = $this->findSegments();

        foreach ($segments as $root) {
            $s = $f->addSegment();
            $point1 = $point2 = null;

            $flight = implode("\n", $this->http->FindNodes("preceding::tr[normalize-space() and not(.//tr[normalize-space()])][1]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)$/m", $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            if (preg_match("/^{$this->opt($this->t('cabinValues'))}$/im", $flight, $m)) {
                $s->extra()->cabin($m[0]);
            }

            if (preg_match("/^({$this->opt($this->t('Airline Reference'))})[:\s]+((?:[A-Z\d]{5,8}[,\s]*)+)$/m", $flight, $m)) {
                $referenceList = preg_split('/(?:\s*,\s*)+/', $m[2]);
                $airlineReference = array_shift($referenceList);
                $s->airline()->confirmation($airlineReference);
                $confNumbers[] = $airlineReference;

                foreach ($referenceList as $ref) {
                    if (!in_array($ref, $confNumbers) && !in_array($ref, $confNumbersSecondary)) {
                        $f->general()->confirmation($ref, $m[1]);
                        $confNumbersSecondary[] = $ref;
                    }
                }
            }

            $departure = implode("\n", $this->http->FindNodes("*[1]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $departure, $matches)) {
                $point1 = $matches['point'];
                $dateDep = strtotime($matches['date']);

                if ($dateDep) {
                    $s->departure()->date(strtotime($matches['time'], $dateDep));
                }

                $s->departure()->code($matches['code'])->name($matches['name']);
            }

            $arrival = implode("\n", $this->http->FindNodes("*[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern, $arrival, $matches)) {
                $point2 = $matches['point'];
                $dateArr = strtotime($matches['date']);

                if ($dateArr) {
                    $s->arrival()->date(strtotime($matches['time'], $dateArr));
                }

                $s->arrival()->code($matches['code'])->name($matches['name']);
            }

            $duration = $this->http->FindSingleNode("*[2]", $root, true, '/^(?:\s*\d{1,3}\s*[hm])+$/i');
            $s->extra()->duration($duration, false, true);

            if ($point1 && $point2) {
                $seatNodes = $this->http->XPath->query("//*[{$this->eq($this->t('Seat selection'))}]/following::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][count(descendant::text()[normalize-space()])=2 and descendant::text()[normalize-space()][1][{$this->eq($point1)}] and descendant::text()[normalize-space()][2][{$this->eq($point2)}]] ]/tr[normalize-space()][2]/descendant::text()[normalize-space()]");

                foreach ($seatNodes as $seatRoot) {
                    if (preg_match("/^(?<passenger>{$patterns['travellerName']})\s*[:]+\s*(?<seat>\d+[A-Z])$/u", $this->http->FindSingleNode(".", $seatRoot), $m)) {
                        $s->extra()->seat($m['seat'], false, false, $m['passenger']);
                    }
                }
            }
        }

        if (count($confNumbersSecondary) === 0 && count($confNumbers) > 0) {
            $f->general()->noConfirmation();
        }

        $travellers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passenger details'))}]/following::tr[ normalize-space() and not(.//tr[normalize-space()]) and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Birthdate'))} or {$this->contains($this->t('Nationality'))}] ]", null, "/^({$patterns['travellerName']})(?:\s*·|[·\s]+(?i){$this->opt($this->t('Adult'))}|$)/u"));

        if (count($travellers) > 0) {
            $f->general()->travellers(array_values(array_unique($travellers)), true);
        }

        $ticketNodes = $this->http->XPath->query("//text()[{$this->starts($this->t('Ticket number'))}]");
        $tickets = [];

        foreach ($ticketNodes as $tktRoot) {
            $passengerName = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()][1][{$this->starts($this->t('Birthdate'))} or {$this->contains($this->t('Nationality'))}]/preceding-sibling::*[normalize-space()][1]", $tktRoot, true, "/^({$patterns['travellerName']})(?:\s*·|[·\s]+(?i){$this->opt($this->t('Adult'))}|$)/u");
            $ticket = $this->http->FindSingleNode(".", $tktRoot, true, "/^{$this->opt($this->t('Ticket number'))}[:\s]+({$patterns['eTicket']}|(?:[A-Z\d]{5,8}[\s|]*)+)$/");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        $xpathTotalPrice = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}]";
        $totalPrice = $this->http->FindSingleNode("//tr[{$xpathTotalPrice}]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // PHP 10,168.57
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $discountAmounts = [];
            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and following-sibling::tr[{$xpathTotalPrice}] and not(*[normalize-space()][1][{$this->eq($this->t('feeHeaders'))}]) ]");

            foreach ($feeRows as $feeRow) {
                $feeName = $this->http->FindSingleNode("*[normalize-space()][1]", $feeRow, true, '/^(.+?)[\s:：]*$/u');
                $feeValue = $this->http->FindSingleNode("*[normalize-space()][2]", $feeRow);

                if (preg_match("/^{$this->opt($this->t('FREE'))}$/i", $feeValue)) {
                    $f->price()->fee($feeName, 0);

                    continue;
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*-[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeValue, $m)) {
                    // PHP -319.90
                    $discountAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);

                    continue;
                }

                if (preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $feeValue, $m)) {
                    $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }

            if (count($discountAmounts) > 0) {
                $f->price()->discount(array_sum($discountAmounts));
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

    private function findSegments(): \DOMNodeList
    {
        $xpathTime = 'contains(translate(.,"0123456789： ","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';

        return $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathTime} and not(descendant::img)] and *[2]/descendant::img and *[3][{$xpathTime} and not(descendant::img)] ]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['otaConfNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['otaConfNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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
}
