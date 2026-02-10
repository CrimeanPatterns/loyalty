<?php

namespace AwardWallet\Engine\edelweiss\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "edelweiss/it-58288123.eml, edelweiss/it-58288158.eml, edelweiss/it-58288317.eml, edelweiss/it-12326650-de.eml";
    public $lang;
    public static $dictionary = [
        'en' => [
            'confNumber-starts' => ['Reference number:', 'Booking reference:'],
            'confNumber-eq'     => ['Reference number', 'Booking reference'],
            'Your Flight'       => 'Your Flight',
            //            'Flight' => '',
            //            'Seat' => '', // check translate
            //            'Total' => '',
        ],
        'de' => [
            'confNumber-starts' => ['Buchungsreferenz:', 'Referenznummer:'],
            'confNumber-eq'     => ['Buchungsreferenz', 'Referenznummer'],
            'Your Flight'       => 'Ihr Flug',
            'Flight'            => 'Flug',
            'Seat'              => 'Sitzplatz',
            'Total'             => 'Total',
        ],
    ];

    private $detectFrom = [".flyedelweiss.com", "@flyedelweiss.com"];

    private $detectSubjectWithProviderName = [
        // en
        'Your Edelweiss booking to',
        'Confirmation for Edelweiss Options',
        // de
        'Ihre Edelweiss Buchung nach',
        'Sitzplatzbestätigung für Edelweiss Flug',
    ];

    private $detectBody = [
        'en' => [
            'Thank you for your booking,',
            'Your booked Edelweiss Options',
        ],
        'de' => [
            'Geniessen Sie die Vorfreude,',
            'Vielen Dank für Ihre Buchung,',
            'Ihre gebuchten Edelweiss Optionen',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubjectWithProviderName as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains(['.flyedelweiss.com', '//flyedelweiss.com'], '@href')} or normalize-space()='flyedelweiss.com']")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseEmailHtml(Email $email): void
    {
        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//tr[{$this->eq($this->t('confNumber-eq'))}]/following-sibling::tr[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            // it-12326650-de.eml
            $confirmationTitle = $this->http->FindSingleNode("//tr[ {$this->eq($this->t('confNumber-eq'))} and following-sibling::tr[normalize-space()] ]", null, true, '/^(.+?)[\s:：]*$/u');
        } elseif (preg_match("/^({$this->opt($this->t('confNumber-starts'))})[:\s]*([A-Z\d]{5,})$/", $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber-starts'))}]"), $m)) {
            $confirmation = $m[2];
            $confirmationTitle = rtrim($m[1], ': ');
        } else {
            $confirmationTitle = null;
        }
        $f->general()->confirmation($confirmation, $confirmationTitle);

        $travellers = [];

        $segments = $this->http->XPath->query("//tr[ *[1][{$xpathTime}] and *[2][descendant::img and normalize-space()=''] and *[3][{$xpathTime}] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            // Airline
            $flight = $this->http->FindSingleNode("ancestor::table[2]/descendant::tr[normalize-space()][1]/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root, true, $pattern = '/^((?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+))$/')
                ?? $this->http->FindSingleNode("ancestor::table[1]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[ *[normalize-space()][2] ][1]/*[normalize-space()][last()]/descendant::text()[normalize-space()][1]", $root, true, $pattern) // it-12326650-de.eml
            ;

            if (preg_match($pattern, $flight, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::tr[count(*[normalize-space()]) = 1 and *[not(normalize-space())][1][.//img]][1]/descendant::text()[normalize-space()][2]",
                $root));

            // Departure
            $time = $this->http->FindSingleNode("*[normalize-space()][1]", $root);

            if ($date && preg_match("/^\s*(\d{1,2}:\d{2})\s*(?:\(\s*([-+]\d)\s*\))?\s*$/", $time, $m)) {
                $s->departure()->date(strtotime($m[1], $date));

                if (!empty($m[2]) && !empty($s->getDepDate())) {
                    $s->departure()->date(strtotime($m[2] . ' day', $s->getDepDate()));
                }
            }
            $name = $this->http->FindSingleNode("following::text()[normalize-space()][1]/ancestor::tr[count(*[normalize-space()]) = 2][1]/*[normalize-space()][1]", $root);

            if (preg_match("/(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $name, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                ;
            }

            // Arrival
            $time = $this->http->FindSingleNode("*[normalize-space()][2]", $root);

            if ($date && preg_match("/^\s*(\d{1,2}:\d{2})\s*(?:\(\s*([-+]\d)\s*\))?\s*$/", $time, $m)) {
                $s->arrival()->date(strtotime($m[1], $date));

                if (!empty($m[2]) && !empty($s->getArrDate())) {
                    $s->arrival()->date(strtotime($m[2] . ' day', $s->getArrDate()));
                }
            }
            $name = $this->http->FindSingleNode("following::text()[normalize-space()][1]/ancestor::tr[count(*[normalize-space()]) = 2][1]/*[normalize-space()][2]", $root);

            if (preg_match("/(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $name, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                ;
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode("ancestor::table[2]/descendant::tr[1]/*[normalize-space()][3]/descendant::text()[normalize-space()][1]",
                    $root, true, "/^\s*.*\bclass\b.*\s*$/i"), true, true);

            if (!empty($s->getDepCode()) && !empty($s->getArrCode())) {
                $routeVariantsA = [$s->getDepCode() . '-' . $s->getArrCode() . ':', $s->getDepCode() . ' - ' . $s->getArrCode() . ':'];
                $routeVariantsB = [$s->getDepCode() . '-' . $s->getArrCode(), $s->getDepCode() . ' - ' . $s->getArrCode()];
                $seats = array_filter($this->http->FindNodes("//text()[{$this->eq($routeVariantsA)}][ancestor::tr[not({$this->starts($routeVariantsA)})][1][{$this->starts($this->t('Seat'))}]]/following::text()[normalize-space()][1]", null, "/^\s*(\d{1,5}[A-Z])\s*$/"));

                if (count($seats) === 0) {
                    $seats = array_filter($this->http->FindNodes("//text()[{$this->contains($routeVariantsB)}]/following::text()[normalize-space()][position()<3][{$this->starts($this->t('Seat'))}]", null, "/{$this->opt($this->t('Seat'))}\s*(\d{1,5}[A-Z])\s*$/"));
                }

                if (count($seats) > 0) {
                    $s->extra()->seats($seats);
                }
                $travellerNames = array_filter($this->http->FindNodes("//tr[ *[normalize-space()][2][{$this->eq($routeVariantsB)}] ]/*[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));

                if (count($travellerNames) > 0) {
                    // it-12326650-de.eml
                    $travellers = array_merge($travellers, $travellerNames);
                }
            }
        }

        if (count($f->getSegments()) > 0 && $f->getSegments()[0]->getDepCode() && $f->getSegments()[0]->getArrCode()) {
            $routeVariantsB = [
                $f->getSegments()[0]->getDepCode() . '-' . $f->getSegments()[0]->getArrCode(),
                $f->getSegments()[0]->getDepCode() . ' - ' . $f->getSegments()[0]->getArrCode(),
            ];

            if (count($travellers) === 0) {
                $travellers = array_filter($this->http->FindNodes("//tr[not(.//tr) and count(*)=2 and *[1][not(normalize-space())]//img][following::tr[not(.//tr)][1][{$this->contains($routeVariantsB)}]]", null, "/^{$patterns['travellerName']}$/u"));
            }

            // price
            $type = array_values(array_unique($this->http->FindNodes("//tr[not(.//tr) and count(*)=2 and *[1][not(normalize-space())]//img][following::tr[not(.//tr)][1][{$this->contains($routeVariantsB)}]]/following::text()[normalize-space()][1]")));

            if (count($type) === 1 && in_array($type[0], (array) $this->t("Flight"))) {
                $total = $this->http->FindSingleNode("//tr[not(.//tr) and *[1][{$this->eq($this->t("Total"))}]]/*[normalize-space()][last()]");

                if (preg_match("/^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d,. ]*)\s*$/", $total, $matches)) {
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                    $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(preg_replace('/^(?:Herr|Frau|Miss|Mr|Ms)[.\s]+(.{2,})$/i', '$1', array_unique($travellers)), true);
        }
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Your Flight"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Your Flight'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date)
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            //            // Di 18. Februar 2020
            '/^\s*[[:alpha:]\-]+\s+(\d{1,2})[.]?\s+([[:alpha:]]+)\s+(\d{4})\s*$/ui',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
        $date = $this->dateTranslate($date);

//        $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
