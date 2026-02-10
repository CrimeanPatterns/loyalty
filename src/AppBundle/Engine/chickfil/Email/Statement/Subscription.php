<?php

namespace AwardWallet\Engine\chickfil\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "chickfil/statements/it-101065199.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@local.chick-fil-a.com') !== false
            || stripos($from, '@email.chick-fil-a.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".chick-fil-a.com/") or contains(@href,"trk.chick-fil-a.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"@email.chick‑fil‑a.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $balance = null;

        $roots = $this->findRoot();

        if ($roots->length === 1) {
            $root = $roots->item(0);
            $balance = $this->http->FindSingleNode(".", $root, true, '/^(\d[,.\'\d ]*)\s*pts$/i');
        }

        $names = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),\"Hi\") and not(contains(.,\"it's\"))]", null, "/^Hi\s+({$patterns['travellerName']})(?:\s*[,:;!?]+|$)/u"));

        if (count(array_unique($names)) === 1) {
            $name = array_shift($names);
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//tr[ count(*[normalize-space()])=3 and *[normalize-space()][2][normalize-space()='Account'] ]/*[normalize-space()][1]");
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
}
