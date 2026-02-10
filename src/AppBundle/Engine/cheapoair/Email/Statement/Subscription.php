<?php

namespace AwardWallet\Engine\cheapoair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Subscription extends \TAccountChecker
{
    public $mailFiles = "cheapoair/statements/it-64706929.eml, cheapoair/statements/it-75013464.eml, cheapoair/statements/it-74479861.eml, cheapoair/statements/it-74929860.eml";

    private $format = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@myCheapOair.com') !== false
            || stripos($from, '@mycheapoair.ca') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'CheapOair Rewards Balance:') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".travelweeklyupdate.com/") or contains(@href,"www.travelweeklyupdate.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"@CheapOair.com") or contains(.,"@myCheapOair.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership() || $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            if ($this->isMembership()) {
                $st->setMembership(true);
            }

            return $email;
        }
        $this->logger->debug('Statement format: ' . $this->format);
        $root = $roots->item(0);
        $rootText = $this->http->FindSingleNode('.', $root); // it-64706929.eml, it-74929860.eml

        if (preg_match("/^Hi\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[ ]*[,|]/iu", $rootText, $m)
            && !preg_match("/^Customer$/i", $m[1])
        ) {
            $st->addProperty('Name', $m[1]);
        }

        $points = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()='POINTS']/ancestor::td[1]", $root, true, "/^(\d[,.\'\d ]*)\s*POINTS/i");

        if ($points === null && preg_match("/\|[ ]*(\d[,.\'\d ]*)\s*points/i", $rootText, $m)) {
            // Hi Charles | 21,321 points | Status: Platinum
            $points = $m[1];
        }
        $st->setBalance($this->normalizeAmount($points));

        if (preg_match("/below is your points balance for\s+(.{4,})\./i", $rootText, $m)) {
            $st->parseBalanceDate($m[1]);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1;
        $nodes = $this->http->XPath->query("//tr[ contains(normalize-space(),'below is your points balance for') and following-sibling::tr[normalize-space()][1][contains(normalize-space(),'POINTS')] ]");

        if ($nodes->length !== 1) {
            // it-74929860.eml
            $this->format = 2;
            $nodes = $this->http->XPath->query("//tr[not(.//tr) and starts-with(normalize-space(),'Hi') and contains(.,'|')]");
        }

        return $nodes;
    }

    private function isMembership(): bool
    {
        return $this->http->XPath->query("//node()[normalize-space()='Email Settings']/following::text()[normalize-space()][1][normalize-space()='To manage any of the emails you receive from us, edit your']/following::a[normalize-space()][1][starts-with(normalize-space(),'email settings') and contains(@href,'.travelweeklyupdate.com/')]")->length > 0;
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
