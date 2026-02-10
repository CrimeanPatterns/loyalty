<?php

namespace AwardWallet\Engine\finnair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StatementData extends \TAccountChecker
{
    public $mailFiles = "finnair/statements/it-64863476.eml, finnair/statements/it-70655562.eml, finnair/statements/it-70658457.eml, finnair/statements/it-70658601.eml, finnair/statements/it-70658960.eml, finnair/statements/it-70659747.eml, finnair/statements/it-70786979.eml, finnair/statements/it-70878437.eml, finnair/statements/it-70668158.eml";
    public $lang = '';

    public static $dictionary = [
        'it' => [
            'Membership number' => 'Numero di iscrizione',
            'Tier points'       => 'Punti di livello',
            'Tier'              => 'Livello',
            'Award points'      => 'Punti premio',
        ],
        'pl' => [
            'Membership number' => 'Numer członkowski',
            'Tier points'       => 'Punkty poziomów',
            'Tier'              => 'Poziom',
            'Award points'      => 'Punkty premiowe',
        ],
        'et' => [
            'Membership number' => 'Kliendikaardi number',
            'Tier points'       => 'Tasemepunkte',
            'Tier'              => 'Tase',
            'Award points'      => 'Preemiapunkte',
        ],
        'fi' => [
            'Membership number' => 'Jäsennumero',
            'Tier points'       => 'Tasopisteet',
            'Tier'              => 'Taso',
            'Award points'      => 'Palkintopisteet',
        ],
        'de' => [
            'Membership number' => 'Mitgliedsnummer',
            'Tier points'       => 'Stufenpunkte',
            'Tier'              => 'Stufe',
            'Award points'      => 'Prämienpunkte',
        ],
        'da' => [
            'Membership number' => 'Medlemsnummer',
            'Tier points'       => 'Niveaupoint',
            'Tier'              => 'Medlemsniveau',
            'Award points'      => 'Bonuspoint',
        ],
        'no' => [
            'Membership number' => 'Medlemsnummer',
            'Tier points'       => 'Nivåpoeng',
            'Tier'              => 'Nivå',
            'Award points'      => 'Bonuspoeng',
        ],
        'sv' => [
            'Membership number' => 'Medlemsnummer',
            'Tier points'       => 'Nivåpoäng',
            'Tier'              => 'Nivå',
            'Award points'      => 'Bonuspoäng',
        ],
        'en' => [
            'Membership number' => 'Membership number',
            'Tier points'       => 'Tier points',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href,'.finnair.com/') or contains(@href,'email.finnair.com')]")->count() > 0
            && $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]email\.finnair\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");
        }
        $email->setType('StatementData' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $numberText = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Membership number'))}]");

        if (preg_match("/^{$this->opt($this->t('Membership number'))}\s*(\d+)\s*\|\s*{$this->opt($this->t('Tier'))}\s*(\w+)$/", $numberText, $m)) {
            $name = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Membership number'))}]/preceding::text()[normalize-space()][1]", null, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");
            $st->addProperty('Name', $name);

            $st->setNumber($m[1])
                ->setLogin($m[1]);

            $st->addProperty('Status', $m[2]);
        }

        $balanceText = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Award points'))}]");

        if (preg_match("/^{$this->opt($this->t('Award points'))}\s*(\d[,.\'\d]*)\s*\|\s*{$this->opt($this->t('Tier points'))}\s*(\d[,.\'\d]*)$/", $balanceText, $m)) {
            $st->setBalance($this->normalizeAmount($m[1]));

            $st->addProperty('Collected', $this->normalizeAmount($m[2]));
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Membership number']) || empty($phrases['Tier points'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Membership number'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Tier points'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }
}
