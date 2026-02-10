<?php

namespace AwardWallet\Engine\dfds\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Ferry extends \TAccountChecker
{
    public $mailFiles = "dfds/it-149548795.eml, dfds/it-31183982.eml, dfds/it-39129291.eml, dfds/it-39160876.eml, dfds/it-61259473.eml";

    public $lang = '';

    public static $dictionary = [
        'da' => [
            'Hi '                  => 'Hej ',
            'BOOKING NUMBER'       => ['BOOKING NR:', 'BOOKING NUMMER:'],
            'DEPARTURE'            => 'UDREJSE:',
            'RETURN'               => 'HJEMREJSE:',
            'NUMBER OF PASSENGERS' => 'ANTAL PASSAGERER',
        ],
        'nl' => [
            'Hi '                  => 'Beste heer/mevrouw',
            'BOOKING NUMBER'       => ['RESERVERINGSNUMMER', 'RESERVERINGSNUMMER:'],
            'DEPARTURE'            => 'HEENREIS:',
            'RETURN'               => 'TERUGREIS:',
            'NUMBER OF PASSENGERS' => 'AANTAL PASSAGIERS',
            'Date:'                => 'Datum:',
            'Time:'                => 'Tijd:',
        ],
        'en' => [
            'BOOKING NUMBER'       => ['BOOKING NUMBER', 'BOOKING NUMBER:'],
            'DEPARTURE'            => ['DEPARTURE', 'OUTWARD JOURNEY:'],
            'RETURN'               => ['RETURN', 'RETURN JOURNEY:'],
            'NUMBER OF PASSENGERS' => ['NUMBER OF PASSENGERS', 'NO. OF PASSENGERS:'],
        ],
    ];

    private $subjects = [
        'da' => ['Du skal snart sejle med', 'Din booking ændring'],
        'en' => ['Your booking - Ref:', 'Your booking confirmation', 'Your booking alteration'],
    ];

    private $detects = [
        'da' => [
            'Din booking er blevet ændret',
            'Endnu engang tak, fordi du valgte DFDS',
            'Vi glæder os til at byde dig velkommen om bord',
        ],
        'nl' => [
            'DFDS maakt gebruik van e-tickets',
        ],
        'en' => [
            'Thank you for choosing to travel with DFDS',
            'Thanks again for choosing DFDS',
            'Thank you for booking with DFDS',
        ],
    ];

    private $prov = 'DFDS';

    private $passengerCount = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], ' DFDS') === false) {
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

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === strpos($body, $this->prov)) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@dfds.info') !== false
            || preg_match('/[@.]dfdsseaways\./i', $from) > 0;
    }

    private function parseEmail(Email $email)
    {
        $xpathP = "(self::tr or self::p or self::strong)";

        $ferry = $email->add()->ferry();

        $h = $this->http;

        $passengerName = $h->FindSingleNode("//*[$xpathP and {$this->eq($this->t('BOOKING NUMBER'))}][1]/preceding::text()[{$this->starts($this->t('Hi '))}]", null, true, "/^{$this->opt($this->t('Hi '))}[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[?!;,. ]*$/u");

        if ($passengerName) {
            $ferry->general()->traveller($passengerName);
        }

        $confirmation = $h->FindSingleNode("//*[$xpathP and {$this->eq($this->t('BOOKING NUMBER'))}][1]/following-sibling::*[$xpathP and normalize-space()][1]", null, true, '/^\d{5,}$/');

        if (!$confirmation) {
            $confirmation = $h->FindSingleNode("//*[$xpathP and {$this->eq($this->t('BOOKING NUMBER'))}][1]/following-sibling::text()[normalize-space(.)][1]", null, true, '/^\d{5,}$/');
        }

        if (!$confirmation) {
            $confirmation = $h->FindSingleNode("//*[$xpathP and {$this->eq($this->t('BOOKING NUMBER'))}][1]/following::text()[normalize-space(.)][1]", null, true, '/^\d{5,}$/');
        }

        if ($confirmation) {
            $ferry->general()->confirmation($confirmation);
        }

        $this->passengerCount = $h->FindSingleNode("//*[$xpathP and {$this->starts($this->t('NUMBER OF PASSENGERS'))}][1]/following-sibling::*[$xpathP and normalize-space()][1]", null, true, '/^\d{1,3}$/');

        if (!$this->passengerCount) {
            $this->passengerCount = $h->FindSingleNode("//*[$xpathP and {$this->starts($this->t('NUMBER OF PASSENGERS'))}][1]/following-sibling::text()[normalize-space(.)][1]", null, true, '/^\d{1,3}$/');
        }

        if (!$this->passengerCount) {
            $this->passengerCount = $h->FindSingleNode("//*[$xpathP and {$this->starts($this->t('NUMBER OF PASSENGERS'))}][1]/following::text()[normalize-space(.)][1]", null, true, '/^\d{1,3}$/');
        }

        if ($total = $h->FindSingleNode("//tr[starts-with(normalize-space(.), 'TOTAL PRICE')][1]/following-sibling::tr[normalize-space(.)][1]", null, true, '/([\S\d]+)/')) {
            $ferry->price()
                ->total($this->total($total), false, true)
                ->currency($this->currency($total));
        }

        $xpath = "//td[{$this->eq($this->t('DEPARTURE'))} or {$this->eq($this->t('RETURN'))}]/ancestor::table[2]";
        $segments = $h->XPath->query($xpath);

        if ($segments->length > 0) {
            // it-31183982.eml
            $this->parseSegments1($ferry, $segments);

            return;
        }

        $xpath = "//p[ {$this->starts($this->t('Route:'))} and following-sibling::p[{$this->starts($this->t('Date:'))}] ]";
        $segments = $h->XPath->query($xpath);

        if ($segments->length == 0) {
            $xpath = "//text()[ {$this->starts($this->t('Route:'))} and following::text()[{$this->starts($this->t('Date:'))}] ]";
            $segments = $h->XPath->query($xpath);
        }

        if ($segments->length > 0) {
            // it-39129291.eml, it-39160876.eml
            $this->parseSegments2($ferry, $segments);

            return;
        }

        if (0 === $segments->length) {
            $this->logger->debug("Segments did not found: {$xpath}");
        }
    }

    private function parseSegments1($ferry, $segments)
    {
        $h = $this->http;

        foreach ($segments as $root) {
            $s = $ferry->addSegment();

            if ($this->passengerCount) {
                $s->booked()->adults($this->passengerCount);
            }

            if (preg_match('/(.+) - (.+)[ ]+(\d{1,2}-\d{1,2}-\d{2,4})/', $h->FindSingleNode('descendant::*[count(tr)=5]/tr[1]', $root), $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
                $dDate = strtotime($m[3]);
            } elseif (preg_match('/(.+) [-–]+? (.+)/', $this->getNode($root, 'Route'), $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            if ($dDate && ($dTime = $this->getNode($root, 'Departure time'))) {
                $s->departure()
                    ->date(strtotime($dTime, $dDate));
            } elseif (($dDate = $this->getNode($root, 'Date')) && ($dTime = $this->getNode($root, 'Time'))) {
                $s->departure()
                    ->date(strtotime($dDate . ', ' . $dTime));
            }

            if (($aDate = $this->getNode($root, 'Arrival date')) && ($aTime = $this->getNode($root, 'Arrival time'))) {
                $s->arrival()
                    ->date(strtotime($aDate . ', ' . $aTime));
            }
        }
    }

    private function parseSegments2($ferry, $segments)
    {
        foreach ($segments as $root) {
            $s = $ferry->addSegment();

            if ($this->passengerCount) {
                $s->booked()->adults($this->passengerCount);
            }

            $route = $this->http->FindSingleNode('.', $root, true, "/{$this->opt($this->t('Route:'))}\s*(.{7,})/");

            if (preg_match("/^(.{3,}?)\s*[\-\–]+\s*(.{3,})$/u", $route, $m)) {
                // Oslo - København
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            $date = $this->dateStringToEnglish($this->http->FindSingleNode("following-sibling::p[{$this->starts($this->t('Date:'))}]", $root, true, "/{$this->opt($this->t('Date:'))}\s*(.{6,})/"));

            if (empty($date)) {
                $date = $this->dateStringToEnglish($this->http->FindSingleNode("following::text()[{$this->starts($this->t('Date:'))}][1]", $root, true, "/{$this->opt($this->t('Date:'))}\s*(.{6,})/"));
            }

            $time = $this->http->FindSingleNode("following-sibling::p[{$this->starts($this->t('Time:'))}]", $root, true, "/{$this->opt($this->t('Time:'))}\s*(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)(?: uur)?$/");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("following::text()[{$this->starts($this->t('Time:'))}][1]", $root, true, "/{$this->opt($this->t('Time:'))}\s*(\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)(?: uur)?$/");
            }

            if ($date && $time) {
                $s->departure()->date2($date . ' ' . $time);
                $s->arrival()->noDate();
            }
        }
    }

    private function dateStringToEnglish($date)
    {
        if ('en' !== $this->lang && preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function total(string $total): ?string
    {
        if (preg_match('/([\d\.]+)/', $total, $m)) {
            return $m[1];
        }

        return null;
    }

    private function currency(string $currency): ?string
    {
        $datas = [
            '£' => 'GBP',
            '€' => 'EUR',
        ];

        foreach ($datas as $symbol => $code) {
            if (false !== stripos($currency, $symbol)) {
                return $code;
            }
        }

        return null;
    }

    private function getNode(\DOMNode $root, string $s, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("descendant::*[count(tr)=5]/tr[starts-with(normalize-space(.), '{$s}')][1]/td[normalize-space(.)][2]", $root, true, $re);
    }

    private function assignLang(): bool
    {
        foreach ($this->detects as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
