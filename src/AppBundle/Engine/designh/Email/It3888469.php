<?php

namespace AwardWallet\Engine\designh\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class It3888469 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "designh/it-3888469.eml, designh/it-99735731.eml";

    public $reSubject = [
        "en"=> "Reservation Confirmation",
    ];
    public $reBody = 'Design Hotels';
    public $reBody2 = [
        "en"  => "RESERVATION CONFIRMATION",
        "en2" => "GUEST NAME:",
    ];

    public static $dictionary = [
        "en" => [
            'PHONE:'            => ['PHONE:', 'Phone:'],
            'FAX:'              => ['FAX:', 'Fax:'],
            'NUMBER OF ROOMS:'  => ['NUMBER OF ROOMS:', 'NUMBER OF ROOM:'],
            'NUMBER OF GUESTS:' => ['NUMBER OF GUESTS:', 'NUMBER OF GUEST:'],
            'dailyRateEnd'      => ['TOTAL RATE (EXCL. TAX):', 'TOTAL RATE (INCL. TAX):', 'TOTAL RATE(INCL.TAX）:'],
            'totalPrice'        => ['TOTAL RATE (INCL. TAX):', 'TOTAL RATE(INCL.TAX）:'],
        ],
    ];

    public $lang = '';

    public function parseHotel(Email $email): void
    {
        $patterns['time'] = '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        $h = $email->add()->hotel();

        $h->general()
            ->travellers(array_unique($this->getFields("GUEST NAME:")))
        ;

        $date = $this->normalizeDate($this->getField("BOOKING DATE:"));

        if (!empty($date)) {
            $h->general()
                ->date2($date);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('ITINERARY NUMBER:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (!empty($confirmation)) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('ITINERARY NUMBER:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $confirmation = $this->http->FindNodes("//text()[{$this->eq($this->t('CONFIRMATION NUMBER:'))}]/following::text()[normalize-space()][1]", null, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('CONFIRMATION NUMBER:'))}])[1]", null, true, '/^(.+?)[\s:]*$/');

            foreach ($confirmation as $conf) {
                $h->general()->confirmation($conf, $confirmationTitle);
            }
        }

        if (!empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("RESERVATION CANCELLATION")) . "]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled()
                ->cancellationNumber($this->http->FindSingleNode("//text()[{$this->starts($this->t('CANCELLATION NUMBER:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/'))
            ;
        }

        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('PHONE:'))}]/ancestor::tr[1]/../descendant::text()[normalize-space()][1]"))
            ->address(implode(', ', $this->http->FindNodes("//text()[{$this->contains($this->t('PHONE:'))}]/ancestor::tr[1]/../descendant::text()[normalize-space()][position()=2 or position()=3 or position()=4]")))
            ->phone($this->http->FindSingleNode("//text()[{$this->starts($this->t('PHONE:'))}]", null, true, "/{$this->opt($this->t('PHONE:'))}\s*([\d\s\(\)\+\-]+)$/"))
            ->fax($this->http->FindSingleNode("//text()[{$this->starts($this->t('FAX:'))}]", null, true, "/{$this->opt($this->t('FAX:'))}\s*([\d\s\(\)\+\-]+)$/"), false, true);

        $checkIns = array_unique($this->getFields("ARRIVAL DATE:"));

        if (count($checkIns) === 1) {
            $h->booked()
                ->checkIn2($this->normalizeDate(array_shift($checkIns)));
        }

        $checkOuts = array_unique($this->getFields("DEPARTURE DATE:"));

        if (count($checkOuts) === 1) {
            $h->booked()
                ->checkOut2($this->normalizeDate(array_shift($checkOuts)));
        }

        $rooms = $this->http->FindNodes("//text()[{$this->eq($this->t('NUMBER OF ROOMS:'))}]/following::text()[normalize-space()][1]", null, '/^(\d{1,3})\b/');
        $guestCountXpath = "//text()[{$this->eq($this->t('NUMBER OF GUESTS:'))}]//following::text()[normalize-space()][position() < 6][{$this->contains($this->t('Adult'))}][1]";
        $guests = $this->http->FindNodes($guestCountXpath, null, "/Adults?\s*=\s*(\d{1,3})\b/i");
        $kids = $this->http->FindNodes($guestCountXpath, null, "/Child\s*=\s*(\d{1,3})\b/i");

        $h->booked()
            ->rooms((count($rooms) === count(array_filter(array_map('is_numeric', $rooms)))) ? array_sum($rooms) : null)
            ->guests((count($guests) === count(array_filter(array_map('is_numeric', $guests)))) ? array_sum($guests) : null)
            ->kids((count($kids) === count(array_filter(array_map('is_numeric', $kids)))) ? array_sum($kids) : null)
        ;

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t("ROOM:"))}]/following::text()[normalize-space()][1]");

        foreach ($nodes as $root) {
            $room = $h->addRoom();

            $room->setType($this->http->FindSingleNode(".", $root));

            $rateText = '';
            $startRate = false;

            for ($i = 1; $i < 20; $i++) {
                $value = $this->http->FindSingleNode("following::text()[normalize-space()][$i]", $root);

                if ($startRate == false && preg_match("/^\s*" . $this->opt($this->t("DAILY RATE:")) . "\W*$/ui", $value)) {
                    $startRate = true;

                    continue;
                }

                if (preg_match("/" . $this->opt($this->t("dailyRateEnd")) . "/ui", $value)) {
                    break;
                }

                if ($startRate === true) {
                    $rateText .= "\n" . $value;
                }
            }
            $rateRange = $this->parseRateRange($rateText);

            if ($rateRange !== null) {
                $room->setRate($rateRange);
            }

            $room->setRateType($this->http->FindSingleNode("following::text()[normalize-space()][position() < 5][{$this->eq($this->t("RATE:"))}]/following::text()[normalize-space()][1]", $root));
        }

        $totalTexts = $this->getFields($this->t('totalPrice'));

        foreach ($totalTexts as $text) {
            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $text, $m)) {
                $v = PriceHelper::cost($m['amount']);
                $currency = $this->currency($m['curr']);

                if (is_numeric($v)) {
                    $total = isset($total) ? $total + $v : $v;
                } else {
                    $total = null;

                    break;
                }
            } else {
                break;
            }
        }

        if ($total !== null) {
            $h->price()
                ->currency($currency)
                ->total($total);
        }

        $costTexts = $this->getFields($this->t('TOTAL RATE (EXCL. TAX):'));

        foreach ($costTexts as $text) {
            if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $text, $m)) {
                $v = PriceHelper::cost($m['amount']);

                if (is_numeric($v)) {
                    $cost = isset($cost) ? $cost + $v : $v;
                } else {
                    $cost = null;

                    break;
                }
            } else {
                break;
            }
        }

        if ($cost !== null) {
            $h->price()->cost($this->cost($cost));
        }

        $cancellation = $this->http->FindSingleNode("//*[normalize-space(text())='GUARANTEE AND CANCELLATION POLICY']/ancestor::tr[1]/following-sibling::tr[1]");

        if ($cancellation) {
            $h->general()->cancellation($cancellation);

            if (preg_match("/Reservations (?i)must be cancell?ed or modified (?<prior>\d{1,3} days?) prior arrival(?: day)? latest at (?<hour>{$patterns['time']})(?: ?\([^)(]+\))? to avoid a fee/", $cancellation, $m)
                || preg_match("/Reservations (?i)must be cancell?ed or modified (?<prior>\d{1,3} days?|\d{1,2} hours?) prior to arrival day to avoid a fee of (?:50%|the full)/", $cancellation, $m)
                || preg_match("/Reservations must be cancelled or modified by (?<hour>{$patterns['time']}) local time, (?<prior>\d{1,3} days?|\d{1,2} hours?) prior to the check-in date to avoid /", $cancellation, $m)
            ) {
                $h->booked()->deadlineRelative($m['prior'], empty($m['hour']) ? null : $m['hour']);
            } elseif (preg_match("/No (?i)change or cancell?ation is possible\./", $cancellation)
            ) {
                $h->booked()->nonRefundable();
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@25hours-hotels.com') !== false
            || stripos($from, '@hotelsezz.com') !== false
            || stripos($from, '@properhotel.com') !== false
            || stripos($from, '@lepigalle.paris') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], '25hours Hotel') === false
            && strpos($headers['subject'], 'Hôtel Sezz') === false
            && strpos($headers['subject'], 'Le Pigalle') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (0 !== $this->http->XPath->query("//node()[contains(normalize-space(.), '{$re}')]")->length) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('Reservation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function getField($field, $n = 1)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/following::text()[normalize-space()][{$n}]");
    }

    private function getFields($field, $n = 1)
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/following::text()[normalize-space()][{$n}]");
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
            "#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
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

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
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

    /**
     * Dependencies `$this->normalizeAmount()`.
     */
    private function parseRateRange(?string $string): ?string
    {
        // Tuesday, August 13, 2019 EUR 990.00
        if (preg_match_all('/\b\d{4}[ ]+(?<currency>[A-Z]{3}) ?(?<amount>\d[,.\'\d ]*)$/m', $string, $rateMatches)
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->normalizeAmount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / day';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / day';
                }
            }
        }

        return null;
    }
}
