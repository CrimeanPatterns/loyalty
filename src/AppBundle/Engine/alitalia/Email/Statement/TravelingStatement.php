<?php

namespace AwardWallet\Engine\alitalia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TravelingStatement extends \TAccountChecker
{
    public $mailFiles = "alitalia/statements/it-63834124.eml, alitalia/statements/it-63960529.eml, alitalia/statements/it-63712411.eml, alitalia/statements/it-63712486.eml, alitalia/statements/it-63712536.eml, alitalia/statements/it-64247409.eml, alitalia/statements/it-64289866.eml, alitalia/statements/it-64306196.eml, alitalia/statements/it-64346403.eml";
    private $lang = '';
    private $reFrom = ['@mailing.alitalia.it'];
    private $reProvider = ['MilleMiglia'];
    private $reSubject = [
        'Traveling safely during COVID-19',
        'Estate in Sardegna con il',
    ];
    private $reBody = [
        'en' => [
            ['Dear', 'MilleMiglia code'],
            ['Dear', 'MilleMiglia Code'],
            ['Miles Balance', 'MilleMiglia Code'],
            ['Company Code:', 'MilleMiglia BusinessConnect'],
            ['Congratulations', 'MilleMiglia Code:'],
        ],
        'es' => [
            ['Saldo de millas', 'Código de MilleMiglia'],
            ['Estimado/a', 'Código de MilleMiglia:'],
        ],
        'it' => [
            ['Gentile', 'Codice MilleMiglia:'],
            ['Il tuo saldo miglia', 'Il tuo Codice MilleMiglia'],
            ['Il tuo saldo miglia', 'Il tuo codice MilleMiglia'],
            ['Il tuo saldo MilleMiglia', 'Il tuo codice MilleMiglia'],
        ],
        'ja' => [
            ['様', 'MilleMiglia Code:'],
        ],
        'pt' => [
            ['Prezado', 'Seu Código MilleMiglia'],
            ['Prezado', 'Código MilleMiglia:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'Hello '  => ['Dear ', 'Hello '],
            'Number'  => ['MilleMiglia Code:', 'Your MilleMiglia code', 'Your MilleMiglia Code', 'MilleMiglia BusinessConnect Code:', 'MilleMiglia code:'],
            'Balance' => ['Your Miles Balance', 'Miles Balance', 'Miles Balance:'],
        ],
        'es' => [
            'Hello '  => ['Dear ', 'Hello '],
            'Number'  => ['Su código de MilleMiglia', 'Código de MilleMiglia:'],
            'Balance' => ['Saldo de millas'],
        ],
        'it' => [
            'Hello '  => ['Gentile '],
            'Number'  => ['Codice MilleMiglia:', 'Il tuo codice MilleMiglia', 'Il tuo codice MilleMiglia', 'Il tuo saldo miglia'],
            'Balance' => ['Il tuo saldo miglia', 'Il tuo saldo MilleMiglia'],
        ],
        'ja' => [
            'Hello '  => ['Gentile '],
            'Number'  => ['MilleMiglia Code:'],
            'Balance' => ['no translated'],
        ],
        'pt' => [
            'Hello '  => ['Prezado(a) Sr.(a) ', 'Prezado(a) '],
            'Number'  => ['Seu código de MilleMiglia', 'Seu Código MilleMiglia'],
            'Balance' => ['Saldo de millas'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();
        $name = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Hello '))}])[1]", null,
            false, "/^{$this->opt($this->t('Hello '))}\s*([[:alpha:]\s.\-]{1,}),/u");

        if (isset($name)) {
            $st->addProperty('Name', $name);
        } else {
            $name = $this->re('/^([[:alpha:]\s]{2,})\,/u', $parser->getSubject());

            if (!empty($name)) {
                $st->addProperty('Name', $name);
            }
        }

        $text = join("\n",
            $this->http->FindNodes("//text()[{$this->contains($this->t('Number'))}]/ancestor::div[1]//text()"));
        $this->logger->debug($text);

        $number = $this->http->FindPreg("/{$this->opt($this->t('Number'))}[\s>]+([\d.,\s]+)/", false, $text);

        if (isset($number)) {
            $st->setLogin($number);
            $st->setNumber($number);
            $st->setMembership(true);
        }

        $balance = $this->http->FindPreg("/{$this->opt($this->t('Balance'))}\s+([\d.,\s]+)/i", false, $text);

        if (isset($balance) && $balance != '') {
            $st->setBalance($balance);
        } elseif (isset($number) && empty($balance)) {
            $st->setNoBalance(true);
        }

        /*if (empty($st->getNumber()) || empty($st->getBalance())) {
            $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Number'))}]", null, false,
                '/:\s*(\d+)$/');
            if ($number) {
                $st->setLogin($number);
                $st->setNumber($number);
            }
        }*/

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
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
