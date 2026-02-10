<?php

namespace AwardWallet\Engine\eurostar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BalanceMembership extends \TAccountChecker
{
    public $mailFiles = "eurostar/statements/it-65912920.eml, eurostar/statements/it-633560373.eml, eurostar/statements/it-632834978.eml";

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Membership Tier' => 'Membership Tier',
            'Points'          => 'Points',
            'Membership No'   => 'Membership No.',
        ],
        "fr" => [
            'Membership Tier' => 'Niveau d’adhésion',
            'Points'          => 'Points',
            'Membership No'   => "Numéro d'adhérent(e)",
        ],
        "nl" => [
            'Membership Tier' => 'Clubniveau',
            'Points'          => ['Points', 'Punten'],
            'Membership No'   => 'Loyaltynummer',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Welcome to Club Eurostar!') !== false
            || stripos($headers['subject'], 'Club Eurostar – welcome on board') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".eurostar.com/") or contains(@href,"e.eurostar.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"this email by Eurostar")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]e\.eurostar\.com$/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $st = $email->add()->statement();

        $name = null;
        $nameTexts = array_filter($this->http->FindNodes("//text()[{$this->starts(['Dear', 'Bonjour'])}]", null, "/^{$this->opt(['Dear', 'Bonjour'])}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

        if (count(array_unique($nameTexts)) === 1) {
            $name = array_shift($nameTexts);
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $tier = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership Tier'))}]", null, true, "/^{$this->opt($this->t('Membership Tier'))}[.:\s]+(\S.+)$/")
        ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership Tier'))}]/following::text()[normalize-space()][1][not({$this->contains($this->t('Points'))} or {$this->contains($this->t('Membership No'))})]");

        if (!empty($tier)) {
            $st->addProperty('Tier', $tier);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Membership No'))}]", null, true, "/^{$this->opt($this->t('Membership No'))}[.:\s]+(\d{5,})$/")
        ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Membership No'))}]/following::text()[normalize-space()][1]", null, true, '/^\d{5,}$/');

        if ($number !== null) {
            $st->setNumber($number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points'))}]", null, true, "/^{$this->opt($this->t('Points'))}[.:\s]+(\d[,\d]*)$/")
        ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Points'))}]/following::text()[normalize-space()][1]", null, true, '/^\d[,\d]*$/');

        if ($balance !== null) {
            // it-65912920.eml, it-632834978.eml
            $st->setBalance(str_replace(',', '', $balance));
        } elseif (!empty($tier) && $number !== null && $this->http->XPath->query("//text()[{$this->starts($this->t('Points'))}]")->length === 0) {
            // it-633560373.eml
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
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
            if (!is_string($lang) || empty($phrases['Membership Tier']) || empty($phrases['Membership No'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['Membership Tier'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Membership No'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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
