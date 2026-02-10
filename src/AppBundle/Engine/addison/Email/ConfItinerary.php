<?php

namespace AwardWallet\Engine\addison\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfItinerary extends \TAccountChecker
{
    public $mailFiles = "addison/it-326584068.eml, addison/it-64754933.eml, addison/it-65045046.eml, addison/it-67241780.eml, addison/it-454433918-junk.eml";
    private $lang = '';
    private $reFrom = ['@addisonlee.co.uk'];
    private $reProvider = ['Addison Lee'];
    private $reSubject = [
        'Booking Confirmation. No:',
    ];

    private static $dictionary = [
        'en' => [
            'For UK journeys call us on' => ['For UK journeys call us on', 'Call our dedicated courier team on', 'For journeys outside of the UK call us on', 'For all reservations please call:'],
            'bookingConfirmation'        => ['BOOKING CONFIRMATION', 'Booking Confirmation', 'BOOKING REFERENCE', 'Booking Reference'],
            'pickUp'                     => ['PICK UP', 'Pick up'],
            'dropOff'                    => ['DROP OFF', 'Drop off'],
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.    |    3pm
        'travellerName' => '[[:alpha:]][-,.\'’[:alpha:] ]*[[:alpha:]]', // PIPER, MR. WILLIAMJASON
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Booking:'))}]")->length > 0) {
            $this->parseTaxi($email);
        } elseif ($this->http->XPath->query("//text()[normalize-space()='PASSENGER DETAILS']/following::text()[normalize-space()][1][starts-with(normalize-space(), 'Name:')]")->length > 0) {
            $this->parseTaxi2($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function parseTaxi(Email $email): void
    {
        // examples: it-64754933.eml, it-65045046.eml, it-67241780.eml, it-454433918-junk.eml

        $dateVal = $this->http->FindSingleNode("//h4[{$this->eq($this->t('Date & time'))}]/following::text()[normalize-space()][1]");

        if (preg_match("/^Within and up to \d+ minutes?$/i", $dateVal)) {
            $email->setIsJunk(true);

            return;
        }

        $t = $email->add()->transfer();

        $confNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking:'))}]/following::text()[normalize-space()][1]", null, false, '/#\s*(\d+)$/');
        $confDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking:'))}]", null, true, "/^(.+)\s*(?:\:|\#)/");
        $t->general()->confirmation($confNumber, $confDescription);

        if ($this->http->XPath->query("//text()[{$this->contains('Booking Cancelled')}]")->length > 0) {
            $t->general()
                ->cancelled()
                ->status('cancelled');
        }

        $travellers = $accounts = [];
        $travellerRows = $this->http->FindNodes("//h4[{$this->contains($this->t('Passenger'))}]/following-sibling::*[normalize-space()]");

        foreach ($travellerRows as $tRow) {
            // Teddy Chadd - 07891646818    |    J138 - 07723063428
            if (preg_match("/^(?<name>{$this->patterns['travellerName']}|[A-Z\d]{3,})\s+-\s+(?<account>[-A-Z\d]{5,})$/u", $tRow, $m)) {
                $travellers[] = $m['name'];
                $accounts[] = $m['account'];
            } elseif (preg_match("/^(?:{$this->patterns['travellerName']}|[A-Z\d]{3,})$/u", $tRow)) {
                $travellers[] = $tRow;
            }
        }

        $t->general()->travellers($this->normalizePassengers($travellers));

        if (count($accounts) > 0) {
            $t->program()->accounts(array_unique($accounts), false);
        }

        foreach ((array) $this->t('For UK journeys call us on') as $title) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($title)}]/following-sibling::a[1]");

            if (!empty($phone)) {
                $t->program()->phone(
                    $phone,
                    $this->http->FindSingleNode("//text()[{$this->contains($title)}]")
                );
            }
        }

        $price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Total'))}]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("/^\s*(?<currency>.+?)\s*(?<amount>\d[,.'\d]*)/m", $price, $matches)) {
            // 8,953.00 ILS
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $t->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
        }

        $s = $t->addSegment();
        /*
        HEATHROW, TERM 5 (International)
        Meeting point: Meeting point South *
        Arrival from: Geneva Genève-cointrin, Main Term
        Flight number: BA735
        ETA: 24/08/2020 19:25
         */
        $pickUpVal = $this->htmlToText($this->http->FindHTMLByXpath("//h4[{$this->eq($this->t('pickUp'))}]/following-sibling::p[normalize-space()][1]"));
        $name = preg_match("/^.{2,}?[ ]*(?:\n|$)/", $pickUpVal, $m) ? $m[0] : null;

        if (preg_match('/^\s*(.+?), (?:[A-z\d]+ TERM|TERM [A-Z\d])/', $name, $m)) {
            $s->departure()->name($this->normalizeLocation($m[1]));
            $s->departure()->address($this->normalizeLocation($name));
        } else {
            $s->departure()->address($this->normalizeLocation($name));
        }

        $dropOffVal = $this->htmlToText($this->http->FindHTMLByXpath("//h4[{$this->eq($this->t('dropOff'))}]/following-sibling::p[normalize-space()][1]"));
        $name = preg_match("/^.{2,}?[ ]*(?:\n|$)/", $dropOffVal, $m) ? $m[0] : null;

        if (preg_match('/Flight number:/i', $name, $m)) {
            $s->arrival()->name($this->normalizeLocation($name));
        } elseif (!empty($name)) {
            $s->arrival()->address($this->normalizeLocation($name));
        }

        $s->departure()->date($this->normalizeDate($dateVal));
        $s->arrival()->noDate();
    }

    public function parseTaxi2(Email $email): void
    {
        // examples: it-326584068.eml

        $t = $email->add()->transfer();

        foreach ((array) $this->t('For UK journeys call us on') as $title) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($title)}]", null, true, "/\:\s*(.+)/");

            if (!empty($phone)) {
                $t->program()->phone(
                    $phone,
                    $this->http->FindSingleNode("//text()[{$this->contains($title)}]", null, true, "/^(.+)\:/")
                );
            }
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('pickUp'))}]/preceding::text()[{$this->starts($this->t('bookingConfirmation'))}][1]");

        if (preg_match("/^({$this->opt($this->t('bookingConfirmation'))})\s*#\s*([-A-Z\d]{5,})$/i", $confirmation, $m)) {
            $t->general()->confirmation($m[2], $m[1]);
        }

        $travellers = $this->http->FindNodes("//text()[normalize-space()='PASSENGER DETAILS']/following::text()[normalize-space()][1][starts-with(normalize-space(),'Name:')]/following::text()[normalize-space()][1]");
        $t->general()->travellers($this->normalizePassengers($travellers), true);

        $s = $t->addSegment();

        $depText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('pickUp'))}]/ancestor::td[1]"));

        if (preg_match("/^Pick up[- ]*(?<depDate>\d[\d\/]{4,}\d[ ]*{$this->patterns['time']}).*\n+[ ]*Address:[ ]*(?<address>.{3,}?)[ ]*(?:\n|$)/iu", $depText, $m)) {
            $s->departure()
                ->date(strtotime(str_replace('/', '.', $m['depDate'])))
                ->address($this->normalizeLocation($m['address']));
        }

        $arrText = $this->htmlToText($this->http->FindHTMLByXpath("//text()[{$this->starts($this->t('dropOff'))}]/ancestor::td[1]"));

        if (preg_match("/^Drop off[ ]*\n+[ ]*Address:[ ]*(?<address>.{3,}?)[ ]*(?:\n|$)/iu", $arrText, $m)) {
            $s->arrival()->address($this->normalizeLocation($m['address']))->noDate();
        }
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            $this->logger->debug('ProvDETECT');

            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
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
            if (!is_string($lang) || empty($phrases['pickUp']) || empty($phrases['dropOff'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->starts($phrases['pickUp'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->starts($phrases['dropOff'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $in = [
            // 28/08/2020 18:50
            '#^(\d+)/(\d+)/(\d{4}) (\d+:\d+)$#',
            //12/05/2019 10:54 (ASAP)
            '#^(\d+)/(\d+)/(\d{4}) (\d+:\d+)\D+$#',
        ];
        $out = [
            "$2/$1/$3, $4",
            "$2/$1/$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
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

    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['US$'],
            'INR' => ['Rs.'],
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

    private function normalizeLocation(?string $s): ?string
    {
        if (empty($s)) {
            return $s;
        }

        if (!preg_match("/(?:\bLondon\b)/i", $s)) {
            $s = preg_replace([
                '/^(CITY AIRPORT\b.*?)[-.,;\s]*$/i',
            ], [
                '$1, London, United Kingdom',
            ], $s);
        }

        return $s;
    }

    private function normalizePassengers(array $a): array
    {
        return preg_replace([
            '/(\s*,\s*)+/',
            '/, (?:MR|MS|DR|MISS)[. ]+(\S)/i',
        ], [
            ', ',
            ', $1',
        ], $a);
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
}
