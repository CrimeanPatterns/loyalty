<?php

namespace AwardWallet\Engine\avis\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "avis/statements/it-62580251.eml, avis/statements/it-62518171.eml, avis/statements/it-62214361.eml, avis/statements/it-62430222.eml";

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'avis@e.avis.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Welcome to Avis') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".avis.com/") or contains(@href,"click.e.avis.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"This message is sent by Avis")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//td[normalize-space()="WIZARD NUMBER:" or normalize-space()="YOUR WIZARD NUMBER:"]')->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        $wizardNumber = $this->http->FindSingleNode('//td[normalize-space()="WIZARD NUMBER:" or normalize-space()="YOUR WIZARD NUMBER:"]/following-sibling::td[normalize-space()]', null, true, '/^[*\dA-Z]{5,}$/');

        if (preg_match('/^[*]+([A-Z\d]+)$/', $wizardNumber, $m)) {
            // ***52C
            $st->setNumber($m[1])->masked()
                ->setLogin($m[1])->masked();
        } else {
            $st->setNumber($wizardNumber)
                ->setLogin($wizardNumber);
        }

        $totalPoints = $this->http->FindSingleNode('//td[normalize-space()="TOTAL POINTS:"]/following-sibling::td[normalize-space()]/descendant::tr[not(.//tr) and normalize-space()][1]');

        if (preg_match('/^\d[,.\'\d ]*$/', $totalPoints)) {
            $st->setBalance($this->normalizeAmount($totalPoints));
        } elseif (preg_match('/^Start earning points/i', $totalPoints)
            || $this->http->XPath->query('//node()[contains(normalize-space(),"TOTAL POINTS:")]')->length === 0
        ) {
            $st->setNoBalance(true);
        }

        $name = $this->http->FindSingleNode('//tr[not(.//tr) and starts-with(normalize-space(),"Hello")]', null, true, '/^Hello\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,!?]|$)/u');

        if ($name) {
            $st->addProperty('Name', $name);
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
