<?php

namespace AwardWallet\Engine\carlson\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourEStatement extends \TAccountChecker
{
    public $mailFiles = "carlson/statements/it-61727401.eml, carlson/statements/it-61827928.eml";

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]radissonrewards\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Radisson Rewards e-statement') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".radissonrewards.com")] | //*[contains(normalize-space(), "Radisson Rewards Americas | ") or contains(normalize-space(), "Radisson Rewards | ")]')->length == 0) {
            // check for intersection with carlson/It5775645
            return false;
        }

        return $this->http->XPath->query("//a[normalize-space()='ACCOUNT' or normalize-space()='Account']")->length > 0
            && $this->http->XPath->query("//text()[starts-with(normalize-space(),'MEMBER NO') or starts-with(normalize-space(),'Member No') or starts-with(normalize-space(),'Member no')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(),'ve registered with us as')]")->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),'HELLO') or starts-with(normalize-space(),'Hello') or starts-with(normalize-space(),'DEAR') or starts-with(normalize-space(),'Dear')]", null, true, "/^(?:HELLO|DEAR)\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[, ]*$/i");

        if ($name) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),'MEMBER NO') or starts-with(normalize-space(),'Member No') or starts-with(normalize-space(),'Member no')]", null, true, "/^MEMBER NO[* ]*:\s*(.*\d.*)$/i");

        if ($number === null) {
            $number = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),'MEMBER NO') or starts-with(normalize-space(),'Member No') or starts-with(normalize-space(),'Member no')]/following::tr[normalize-space()][1]", null, true, "/^.*\d.*$/");
        }

        if (preg_match("/^(?:ENDING WITH|[x]{4,})\s*(?<number>\d+)$/i", $number, $m)) {
            // ENDING WITH 8101    |    XXXXXXXXXX7262
            $st->setNumber($m['number'])->masked();
        } elseif ($number !== null) {
            $st->setNumber($number);
        }

        $patterns['tier'] = 'CLUB|SILVER|GOLD|PLATINUM';

        $tier = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),'TIER') or starts-with(normalize-space(),'Tier')]", null, true, "/^TIER[* ]*:\s*({$patterns['tier']})$/i");

        if (!$tier) {
            $tier = $this->http->FindSingleNode("//img[contains(@alt,'Tier progress:')]/@alt", null, true, "/Tier progress:\s*({$patterns['tier']})$/i");
        }

        if ($tier) {
            $st->addProperty('Status', $tier);
        }

        $login = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),\"You've registered with us as\") or starts-with(normalize-space(),\"You’ve registered with us as\")]", null, true, "/^(?:You've registered with us as|You’ve registered with us as)[* ]*:\s*(\S{8,})$/");
        $st->setLogin($login);

        $points = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),'POINTS') or starts-with(normalize-space(),'Points')]", null, true, "/^POINTS[* ]*:\s*(\d[,.\'\d ]*)$/i");

        if ($points === null) {
            $points = $this->http->FindSingleNode("//tr[not(.//tr)][starts-with(normalize-space(),'POINTS') or starts-with(normalize-space(),'Points')]/following::tr[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");
        }

        if ($points !== null) {
            $st->setBalance($this->normalizeAmount($points));
        } elseif ($number || $tier || $login) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
