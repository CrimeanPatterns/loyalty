<?php

namespace AwardWallet\Engine\fuelrewards\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class MiscNotifications extends \TAccountChecker
{
    public $mailFiles = "fuelrewards/statements/it-65844524.eml, fuelrewards/statements/it-65846207.eml, fuelrewards/statements/it-71878695.eml";

    private $format = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@email.rewardsnetwork.com') !== false
            || stripos($from, '@email.fuelrewards.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".fuelrewards.com/") or contains(@href,".rewardsnetwork.com/") or contains(@href,"email.fuelrewards.com") or contains(@href,"email.rewardsnetwork.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"You are subscribed to receive emails from Fuel Rewards") or contains(normalize-space(),"Thanks for being a part of the Fuel Rewards program") or contains(.,"@fuelrewards.com")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $email->setType('MiscNotifications' . $this->format);
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $name = $status = $balance = null;

        $headerHtml = $this->http->FindHTMLByXpath('.', null, $root);
        $headerText = $this->htmlToText($headerHtml);
        /*
            Hi, Jason,
            Manage Cards

            or

            Hi, T
            You have Gold Status.
            Alt ID: 310-478-XXXX
        */

        if (preg_match("/^\s*Hi,\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]|[[:alpha:]])(?:\s*[,:;!?]|$)/imu", $headerText, $m)) {
            $name = $m[1];
        }
        $st->addProperty('Name', $name);

        if (preg_match("/^[ ]*You have (\w+) Status/im", $headerText, $m)) {
            $status = $m[1];
            $st->addProperty('Status', $status);
        }

        $balanceText = implode(' ', $this->http->FindNodes("//*[ tr[1][starts-with(normalize-space(),'You have a total of')] ]/tr[2]/descendant::tr/*[normalize-space()][1]"));

        if (preg_match("/^(?<amount>\d[,.\'\d ]*)(?:¢)/u", $balanceText, $m)) {
            // 15 ¢ /gal*
            $balance = $this->normalizeAmount($m['amount']);
            $st->setBalance($balance);
        } elseif ($name || $status) {
            $st->setNoBalance(true);
        }

        $totalAmountSaved = implode(' ', $this->http->FindNodes("//*[ tr[1][starts-with(normalize-space(),'YOUR FUEL REWARDS') and contains(normalize-space(),'LIFETIME SAVINGS')] ]/tr[2]/descendant::tr/*[normalize-space()][1]"));

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalAmountSaved)
            || preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)$/', $totalAmountSaved)
        ) {
            // $37.44    |    37.44$
            $st->addProperty('TotalAmountSaved', $totalAmountSaved);
        }

        if ($balance !== null
            && ($lastUpdated = $this->http->FindSingleNode("following::text()[starts-with(normalize-space(),'Account information last updated')]", $root, true, "/Account information last updated\s*(.{6,}?)(?:\s*[,.;!?]|$)/"))
        ) {
            $st->parseBalanceDate($lastUpdated);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function findRoot(): \DOMNodeList
    {
        $this->format = 1; // it-65844524.eml, it-65846207.eml
        $nodes = $this->http->XPath->query("//tr[*[1]/descendant::img]/*[2][descendant::text()[normalize-space()='go to your account' or normalize-space()='Manage Cards'] and count(following-sibling::*[normalize-space()])=0]");

        if ($nodes->length === 0) {
            $this->format = 2; // it-71878695.eml
            $nodes = $this->http->XPath->query("//tr[*[1]/descendant::img]/*[2][ descendant::text()[starts-with(normalize-space(),'You have')] and descendant::text()[starts-with(normalize-space(),'Alt ID')] ]");
        }

        return $nodes;
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
