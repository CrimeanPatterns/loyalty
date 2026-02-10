<?php

namespace AwardWallet\Engine\flyadeal\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourTravelItinerary extends \TAccountChecker
{
    public $mailFiles = "flyadeal/it-235175896.eml, flyadeal/it-234169099.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Booking Reference'],
            'statusPhrases'  => ['Your Booking is', 'Your Booking Is'],
            'statusVariants' => ['Confirmed', 'On Hold'],
        ],
    ];

    private $subjects = [
        'en' => ['Your confirmation and travel itinerary'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travel.flyadeal.com') !== false;
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
        if ($this->http->XPath->query('//a[contains(@href,".flyadeal.com/") or contains(@href,"www.flyadeal.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"thank you for choosing flyadeal") or contains(normalize-space(),"flyadeal، all rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('YourTravelItinerary' . ucfirst($this->lang));

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';
        $xpathNoDisplay = 'not(ancestor-or-self::*[contains(translate(@style," ",""),"display:none")])';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Booking Reference'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^([A-Z\d]{5,})(?:\s+-|$)/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Booking Reference'))}]");
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Amount Due:'))}] ]/*[normalize-space()][2][{$xpathNoDisplay}]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)) {
            // 654.0 SAR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }

        $notes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Note:'))}][{$xpathNoDisplay}]", null, true, "/^{$this->opt($this->t('Note:'))}\s*(.{2,})$/");

        if ($notes) {
            $f->general()->notes($notes);
        }

        $travellers = [];

        $segments = $this->http->XPath->query("//*[table[normalize-space()][1][{$xpathTime}] and table[normalize-space()][3][{$xpathTime}] and count(*[normalize-space()])=3]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $xpathLength3 = "descendant::tr[not(.//tr) and normalize-space()][1][string-length(normalize-space())=3]";
            $xpathPrevRow = "preceding::*[ ../self::tr and table[1]/{$xpathLength3} and table[2][normalize-space()=''] and table[3]/{$xpathLength3} ][1]";

            $codeDep = $this->http->FindSingleNode($xpathPrevRow . "/table[1]/descendant::tr[not(.//tr) and normalize-space()][1]", $root, true, '/^[A-Z]{3}$/');
            $cityDep = $this->http->FindSingleNode($xpathPrevRow . "/table[1]/descendant::tr[not(.//tr) and normalize-space()][2]", $root);
            $airportDep = $this->http->FindSingleNode($xpathPrevRow . "/table[1]/descendant::tr[not(.//tr) and normalize-space()][3]", $root);

            if (preg_match("/^(?<name>.{3,}?)\s+Terminal[-\s]+(?<terminal>.+)$/i", $airportDep, $m)) {
                $airportDep = $m['name'];
                $s->departure()->terminal($m['terminal']);
            }

            $s->departure()->code($codeDep)->name($airportDep . ', ' . $cityDep);

            $codeArr = $this->http->FindSingleNode($xpathPrevRow . "/table[3]/descendant::tr[not(.//tr) and normalize-space()][1]", $root, true, '/^[A-Z]{3}$/');
            $cityArr = $this->http->FindSingleNode($xpathPrevRow . "/table[3]/descendant::tr[not(.//tr) and normalize-space()][2]", $root);
            $airportArr = $this->http->FindSingleNode($xpathPrevRow . "/table[3]/descendant::tr[not(.//tr) and normalize-space()][3]", $root);

            if (preg_match("/^(?<name>.{3,}?)\s+Terminal[-\s]+(?<terminal>.+)$/i", $airportArr, $m)) {
                $airportArr = $m['name'];
                $s->arrival()->terminal($m['terminal']);
            }

            $s->arrival()->code($codeArr)->name($airportArr . ', ' . $cityArr);

            $timeDep = $this->http->FindSingleNode("table[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1]", $root, true, "/^{$patterns['time']}$/u");
            $dateDep = strtotime($this->http->FindSingleNode("table[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][2]", $root, true, "/^.*\d.*$/"));

            if ($timeDep && $dateDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            $timeArr = $this->http->FindSingleNode("table[normalize-space()][3]/descendant::tr[not(.//tr) and normalize-space()][1]", $root, true, "/^{$patterns['time']}$/u");
            $dateArr = strtotime($this->http->FindSingleNode("table[normalize-space()][3]/descendant::tr[not(.//tr) and normalize-space()][2]", $root, true, "/^.*\d.*$/"));

            if ($timeArr && $dateArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }

            $flight = $this->http->FindSingleNode("table[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1]", $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $duration = $this->http->FindSingleNode("table[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][2]", $root, true, '/^\d.*hrs\b/i');
            $s->extra()->duration($duration, false, true);

            // travellers
            // seats

            if (!empty($codeDep) && !empty($codeArr) && !empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                $meals = [];

                $xpathPHeader = "descendant::*[{$this->eq([$codeDep . ' > ' . $codeArr, $codeDep . '>' . $codeArr])}]/following-sibling::*[{$this->eq([$s->getAirlineName() . ' ' . $s->getFlightNumber(), $s->getAirlineName() . $s->getFlightNumber()])}]";
                $passengerRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Name'))}] and *[2][{$this->eq($this->t('Seat No'))}] and *[4][{$this->eq($this->t('Meal'))}] and ancestor::table[preceding-sibling::table[normalize-space()]][1]/preceding-sibling::table[normalize-space()][1][{$xpathPHeader}] ]/following-sibling::tr[normalize-space()]");

                foreach ($passengerRows as $pRow) {
                    $passengerName = $this->http->FindSingleNode('*[1]', $pRow, true, "/^{$patterns['travellerName']}$/u");

                    if ($passengerName) {
                        $travellers[] = $passengerName;
                    }
                    $seat = $this->http->FindSingleNode('*[2]', $pRow, true, '/^\d+[A-Z]$/');

                    if ($seat) {
                        $s->extra()->seat($seat);
                    }
                    $meal = $this->http->FindSingleNode('*[4]', $pRow);

                    if (strcasecmp($meal, 'None') !== 0) {
                        $meals[] = $meal;
                    }
                }

                if (count($meals) > 0) {
                    $s->extra()->meals($meals);
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
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
