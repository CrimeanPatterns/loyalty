<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "edreams/it-60279869.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'     => ['YOUR RESERVATION NUMBER IS', 'Your reservation number is'],
            'Arrival'        => ['Arrival'],
            'statusVariants' => ['confirmed'],
        ],
    ];

    private $subjects = [
        'en' => ['Booking confirmation'],
    ];

    private $detectors = [
        'en' => ['Flight details', 'FLIGHT DETAILS'],
    ];

    private $parsedYear = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@edreams.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".edreams.com/") or contains(@href,"www.edreams.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@edreams.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        if (($date = strtotime($parser->getDate()))) {
            $this->parsedYear = date('Y', $date);
        }

        $this->parseFlight($email);
        $email->setType('FlightConfirmation' . ucfirst($this->lang));

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

    private function parseFlight(Email $email): void
    {
        $email->ota(); // because eDreams is not airline

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})[:\s]+([A-Z\d]{5,})$/", $confirmation, $m)) {
            $m[1] = preg_replace("/^{$this->opt($this->t('YOUR'))}\s+/", '', $m[1]);
            $m[1] = preg_replace("/\s+{$this->opt($this->t('IS'))}$/", '', $m[1]);
            $f->general()->confirmation($m[2], $m[1]);
        }

        $statusText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your reservation has been'))}]");

        if (preg_match("/{$this->opt($this->t('Your reservation has been'))}\s+({$this->opt($this->t('statusVariants'))})\s*(?:[.;!?]|$)/", $statusText, $m)) {
            $f->general()->status($m[1]);
        }

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        $segments = $this->http->XPath->query("//tr[ {$this->starts($this->t('Departure'))} and {$xpathTime} and following-sibling::tr[normalize-space()][1][{$this->starts($this->t('Arrival'))} and {$xpathTime}] ]");

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $xpathArr = 'following-sibling::tr[normalize-space()][1]';

            // 22:15 Thu, 5-Feb
            $patterns['timeDate'] = '/^(?<time>\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?)\s+(?<date>.{6,})$/';

            // Thu, 5-Feb
            $patterns['wdayDate'] = '/^(?<wday>[[:alpha:]]{2,})\s*,\s*(?<date>.{3,})$/u';

            $dateDepValue = $this->http->FindSingleNode('*[2]', $root);

            if (preg_match($patterns['timeDate'], $dateDepValue, $matches)
                && preg_match($patterns['wdayDate'], $matches['date'], $m)
            ) {
                $dateDep = $this->normalizeDate($m['date']);
                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($dateDep && $this->parsedYear && $weekDayNumber) {
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($dateDep . ' ' . $this->parsedYear, $weekDayNumber);
                    $s->departure()->date(strtotime($matches['time'], $dateDep));
                }
            }

            $dateArrValue = $this->http->FindSingleNode($xpathArr . '/*[2]', $root);

            if (preg_match($patterns['timeDate'], $dateArrValue, $matches)
                && preg_match($patterns['wdayDate'], $matches['date'], $m)
            ) {
                $dateArr = $this->normalizeDate($m['date']);
                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($dateArr && $this->parsedYear && $weekDayNumber) {
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($dateArr . ' ' . $this->parsedYear, $weekDayNumber);
                    $s->arrival()->date(strtotime($matches['time'], $dateArr));
                }
            }

            $patterns['airport'] = '/^(?<city>.+?)\s+-\s+(?<code>[A-Z]{3})\s*\(\s*(?<name>.+)\s*\)$/'; // Kiev - KBP (Borispol)

            $airportDep = $this->http->FindSingleNode('*[3]', $root);

            if (preg_match($patterns['airport'], $airportDep, $m)) {
                $s->departure()
                    ->name(implode(', ', array_unique([$m['name'], $m['city']])))
                    ->code($m['code']);
            }

            $airportArr = $this->http->FindSingleNode($xpathArr . '/*[3]', $root);

            if (preg_match($patterns['airport'], $airportArr, $m)) {
                $s->arrival()
                    ->name(implode(', ', array_unique([$m['name'], $m['city']])))
                    ->code($m['code']);
            }

            $terminalDep = $this->http->FindSingleNode('*[4]', $root, true, "/{$this->opt($this->t('Terminal'))}[:\s]+([^:]+)$/");
            $s->departure()->terminal($terminalDep, false, true);

            $terminalArr = $this->http->FindSingleNode($xpathArr . '/*[4]', $root, true, "/{$this->opt($this->t('Terminal'))}[:\s]+([^:]+)$/");
            $s->arrival()->terminal($terminalArr, false, true);

            $flight = $this->http->FindSingleNode($xpathArr . '/*[5]', $root);

            if (preg_match('/^(?<name>.{2,}?)\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $class = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][2]/*[1]', $root, true, "/^{$this->opt($this->t('Class'))}[:\s]+([^)(]+)$/");
            $s->extra()->cabin($class);
        }

        $passengers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passengers'))}]/following-sibling::table[normalize-space()][1]/descendant::tr[not(.//tr)]", null, '/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*(?:\(|$)/u'));
        $f->general()->travellers($passengers);

        $payment = $this->http->FindSingleNode("//text()[{$this->starts($this->t('The total cost of your reservation is'))}]", null, true, "/{$this->opt($this->t('The total cost of your reservation is'))}\s+(.+)$/");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $payment, $m)
            || preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[^\d)(]+)$/', $payment, $m)
        ) {
            // € 161.46    |    365.11 €
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-\s]+([[:alpha:]]{3,})$/u', $text, $m)) {
            // 5-Feb
            $day = $m[1];
            $month = $m[2];
            $year = '';
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Arrival'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Arrival'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
