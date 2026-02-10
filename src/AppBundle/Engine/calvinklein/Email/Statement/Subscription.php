<?php

namespace AwardWallet\Engine\calvinklein\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "calvinklein/statements/it-84686118.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@em.calvinklein.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".calvinklein.com/") or contains(@href,"em.calvinklein.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"@em.calvinklein.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1
            && $this->http->XPath->query("//a[normalize-space()='Unsubscribe' or normalize-space()='UNSUBSCRIBE' or normalize-space()='unsubscribe']")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $number = $balance = null;

        $rootText = $this->http->FindSingleNode('.', $root);

        /*
            Member ID: C0041529681 | Point Balance : 100
        */

        if (preg_match("/Member ID[ ]*[:]+[ ]*(?<number>[-A-Z\d]{5,})(?:[ ]*\||$)/", $rootText, $m)) {
            $number = $m['number'];
        }

        if (preg_match("/Point Balance[ ]*[:]+[ ]*(?<balance>\d[,.\'\d ]*)(?:[ ]*\||$)/i", $rootText, $m)) {
            $balance = $m['balance'];
        }

        if ($number) {
            $st->setNumber($number);
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($number) {
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
        return $this->http->XPath->query("//tr[not(.//tr[normalize-space()])]/descendant::text()[starts-with(normalize-space(),'Member ID') and contains(normalize-space(),'Point Balance')]");
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
