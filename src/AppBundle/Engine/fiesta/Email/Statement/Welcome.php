<?php

namespace AwardWallet\Engine\fiesta\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Welcome extends \TAccountChecker
{
    public $mailFiles = "fiesta/statements/it-89066683.eml, fiesta/statements/it-89066730.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [
            'hi'               => 'Hola',
            'membershipNumber' => 'Número de Socio:',
        ],
        'en' => [
            'hi'               => 'Hello',
            'membershipNumber' => 'Your membership number is',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@posadas.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".fiestarewards.com/") or contains(@href,".posadas.com/") or contains(@href,"www.fiestarewards.com") or contains(@href,"www.posadas.com") or contains(@href,"cms.posadas.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"more on fiestarewards.com") or contains(.,"@posadas.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Welcome to Fiesta Rewards') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('Welcome' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $st = $email->add()->statement();

        $name = $number = null;

        $rootText = $this->http->FindSingleNode('.', $root);

        $names = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('hi'))}]/following::text()[normalize-space()][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($names) === 0) {
            $names = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hi'))}]", null, "/^{$this->opt($this->t('hi'))}\s+({$patterns['travellerName']})(?:[ ]*[,:;!?]|$)/u"));
        }

        if (count($names)) {
            $name = array_shift($names);
        }

        if (preg_match("/{$this->opt($this->t('membershipNumber'))}[: ]*([-A-Z\d]{5,})$/", $rootText, $m)) {
            $number = $m[1];
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        if ($number) {
            $st->setNumber($number);
        }

        if ($name || $number) {
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
        return $this->http->XPath->query("//text()[{$this->starts($this->t('membershipNumber'))}]");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['membershipNumber']) && $this->http->XPath->query("//*[{$this->contains($phrases['membershipNumber'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
