<?php

namespace AwardWallet\Engine\bradesco\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBalance extends \TAccountChecker
{
    public $mailFiles = "bradesco/statements/it-66830716.eml";
    private $lang = '';
    private $reFrom = ['@vivalivelo.com.br'];
    private $reProvider = ['Livelo'];
    private $reSubject = [
        'Transfira seus pontos para TAP Miles&Go',
    ];
    private $reBody = [
        'pt' => [
            ['Seu saldo em', 'Pontos a expirar nos próximos'],
        ],
    ];
    private static $dictionary = [
        'pt' => [
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $root = $this->http->XPath->query("//a[{$this->contains($this->t('Acesse sua conta »'))}]/ancestor::td[1]");
        $root = $root->item(0);

        if (!empty($root)) {
            $name = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Olá,'))}]/following::strong[1]",
                $root, true, "/^[[:alpha:]\s\-.]+/u");
            $st->addProperty('Name', $name);

            $balanceDate = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Seu saldo em'))}]",
                $root, true, "/{$this->opt($this->t('Seu saldo em'))}\s+(.+?):/u");
            $st->parseBalanceDate($balanceDate);

            $balance = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Seu saldo em'))}]/following::strong[1]",
                $root, true, self::BALANCE_REGEXP);
            $st->setBalance(str_replace('.', '', $balance));
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
