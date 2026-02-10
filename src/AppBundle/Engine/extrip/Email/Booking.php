<?php

namespace AwardWallet\Engine\extrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking extends \TAccountChecker
{
    public $mailFiles = "extrip/it-53892400.eml, extrip/it-53934246.eml, extrip/it-61299640.eml, extrip/it-64051403.eml";
    public $reFrom = "support@exploretrip.com";
    public $reProvider = "@exploretrip.com";
    public $reSubject = [
        'en' => ['Booking Information for PNR', 'From ExploreTrip'],
    ];

    public $reBody2 = [
        'en'  => "Please use the below Exploretrip Inc Ref/PNR no for any communication",
        'en2' => "Please use the below ExploreTrip Ref/PNR no for any communication",
        'en3' => "Please find your E-ticket confirmation below:",
    ];
    public $lang = "en";

    public static $dictionary = [
        'en' => [
            'PNR:'       => ['PNR :', 'PNR:'],
            'Travellers' => ['Travellers', 'Last Name'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        $flight = $email->add()->flight();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'ExploreTrip #')]/ancestor::*[1]", null, true, "/[:]\s*([A-Z\d]{8,})/"), 'ExploreTrip #')
            ->phone($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Exploretrip Inc Customer Support 24/7') or starts-with(normalize-space(), 'ExploreTrip Customer Support 24/7')]/following::text()[normalize-space()][1]", null, true, "/([\d+\-]{8,})/"));

        //Travellers
        $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Last Name')]/ancestor::table[normalize-space()][1]/descendant::tr[not(contains(normalize-space(), 'Last Name'))][normalize-space()]", null, "/\s*(\w+\s*\w+\s*\w+?)\s*\D{3}[-]\d+[-]\d{4}/s");

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passenger')]", null, "/\d+[.](\D+)[(]/");
        }

        if (count($travellers) > 0) {
            $flight->general()->travellers($travellers, true);
        } elseif (count($travellers) === 0) {
            $travellers = $this->http->XPath->query("//text()[{$this->starts($this->t('Travellers'))}]/ancestor::table[normalize-space()][1]/descendant::tr[not({$this->contains($this->t('Travellers'))}) and not({$this->contains($this->t('TIP:'))})][normalize-space()]");

            foreach ($travellers as $traveller) {
                $flight->general()->traveller($this->http->FindSingleNode("./td[1]", $traveller));
                $flight->program()->account($this->http->FindSingleNode("./td[3]", $traveller), false);
            }
        }

        $flight->general()
            ->confirmation($this->http->FindSingleNode("(//text()[{$this->starts($this->t('PNR:'))}]/ancestor::*[1])[1]", null, true, "/[:]\s*([A-Z\d]{6})/"), 'PNR')
            ;

        //Segments
        $xpath = "//table[starts-with(normalize-space(), 'Itinerary Details')]/following::td[starts-with(.,' Depart |')]/ancestor::table[1]/following-sibling::table";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            // it-53892400.eml
            foreach ($nodes as $root) {
                $s = $flight->addSegment();

                $s->airline()
                    ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[2]", $root, true, "/(.+)\s*[-]/"))
                    ->number($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[2]", $root, true, "/[-]\s*(\d+)/"));

                $s->departure()
                    ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][3]", $root)))
                    ->noCode();

                $s->arrival()
                    ->name($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root))
                    ->date($this->normalizeDate($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][4]", $root)))
                    ->noCode();

                $s->extra()
                    ->duration($this->http->FindSingleNode("./descendant::tr[normalize-space()][1]/descendant::td[3]", $root))
                    ->cabin($this->http->FindSingleNode("./descendant::tr[normalize-space()][2]/descendant::text()[normalize-space()][5]", $root));
            }
        } else {
            // it-53934246.eml
            $segments = $this->http->XPath->query("//tr[ count(*)=3 and *[1][{$xpathTime}] and *[3][{$xpathTime}] ]");

            foreach ($segments as $root) {
                $s = $flight->addSegment();

                $flightInfo = implode("\n", $this->http->FindNodes("ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

                if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<number>\d+)$/m', $flightInfo, $m)) {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);
                }

                $s->departure()
                    ->code($this->http->FindSingleNode("*[1]", $root, true, '/^([A-Z]{3})\b/'))
                    ->date($this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[1]", $root)));

                $s->arrival()
                    ->code($this->http->FindSingleNode("*[3]", $root, true, '/\b([A-Z]{3})$/'))
                    ->date($this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/*[2]", $root)));

                $s->extra()
                    ->duration($this->http->FindSingleNode("*[2]/descendant::tr[not(.//tr)][2]", $root, true, '/^\d[\d hm]+$/i'))
                    ->cabin($this->http->FindSingleNode("*[2]/descendant::tr[not(.//tr)][3]", $root, true, '/^(.*?Economy.*?)$/i'));

                if ($this->http->XPath->query("following-sibling::tr[starts-with(normalize-space(),'Layover')]", $root)->length === 1) {
                    $s->extra()->stops(1);
                }
            }
        }

        // Price
        $totalPrice = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Payment Details')]/following::td[starts-with(normalize-space(),'Total Amount')]/following-sibling::td[1]"); // it-53934246.eml

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // CAD 419.59
            $flight->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);

            $baseFare = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Payment Details')]/following::td[starts-with(normalize-space(),'Base Fare')]/following-sibling::td[1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $matches)) {
                $flight->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $taxes = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Payment Details')]/following::td[starts-with(normalize-space(),'Taxes & Fees')]/following-sibling::td[1]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $taxes, $matches)) {
                $flight->price()->tax($this->normalizeAmount($matches['amount']));
            }
        } else {
            // it-53892400.eml
            $total = $this->http->FindSingleNode("//tr[starts-with(normalize-space(),'Payment Details')]/ancestor::table[1]/descendant::tr[starts-with(normalize-space(),'Total')][1]/descendant::td[1]", null, true, '/^\d[\d., ]*$/');
            $currency = $this->http->FindSingleNode("//tr[starts-with(normalize-space(),'Payment Details')]/descendant::td[starts-with(normalize-space(),'Currency')]", null, true, '/:\s*(\D+)$/');
            $flight->price()
                ->total($this->normalizeAmount($total ?? ''))
                ->currency($currency);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject[0]) !== false && stripos($headers["subject"], $reSubject[1]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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

    private function normalizeDate($str)
    {
        $in = [
            "/(\w+)[-](\d+)-(\d+)\s*([\d:]+\s*(?:AM|PM))/", //Feb-10-2020 06:30 PM
            "/\w+[,]\s*(\d+)\s*(\w+)\s*(\d{4})\s*([\d\:]+)/", //Sun, 05 Jan 2020 15:15
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
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
}
