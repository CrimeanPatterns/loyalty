<?php

namespace AwardWallet\Engine\alitalia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class EStatement extends \TAccountChecker
{
    public $mailFiles = "alitalia/statements/it-61583348.eml, alitalia/statements/it-63712544.eml";
    private $lang = '';
    private $reFrom = ['@mailing.alitalia.it'];
    private $reProvider = ['MilleMiglia'];
    private $reSubject = [
        ', your e-Statement is ready',
        ', ecco il tuo Estratto Conto',
    ];
    private $reBody = [
        'en' => [
            ['TOTAL MILES', 'QUALIFYING MILES'],
        ],
        'it' => [
            ['SALDO MIGLIA', 'MIGLIA QUALIFICANTI'],
        ],
    ];
    private static $dictionary = [
        'en' => [
            'MilleMiglia Code:' => ['MilleMiglia Code:', 'MilleMiglia code:'],
        ],
        'it' => [
            'MilleMiglia Code:'  => ['Codice MilleMiglia:'],
            'TOTAL MILES'        => ['SALDO MIGLIA'],
            'QUALIFYING MILES'   => ['MIGLIA QUALIFICANTI'],
            'QUALIFYING FLIGHTS' => ['VOLI QUALIFICANTI'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->logger->notice("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $name = $this->re('/^([[:alpha:]\s]{2,})\,/u', $parser->getSubject());

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $this->logger->debug($parser->getSubject());

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('MilleMiglia Code:'))}]/following::text()[normalize-space()][1]",
            null, true, "/^(\d{7,})$/");

        if (!empty($number)) {
            $st->addProperty('Number', $number);
        }

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('TOTAL MILES'))}]/ancestor::div[1]/following::div[1]/descendant::text()[normalize-space()]");

        if (isset($balance)) {
            $balance = str_replace('.', '', $balance);
            $st->setBalance($balance);
        }

        $qMiles = $this->http->FindSingleNode("//text()[{$this->starts($this->t('QUALIFYING MILES'))}]/ancestor::div[1]/following::div[1]/descendant::text()[normalize-space()]");

        if (isset($qMiles)) {
            $qMiles = str_replace('.', '', $qMiles);
            $st->addProperty('QualifyingMiles', $qMiles);
        }

        $qFlights = $this->http->FindSingleNode("//text()[{$this->starts($this->t('QUALIFYING FLIGHTS'))}]/ancestor::div[1]/following::div[1]/descendant::text()[normalize-space()]");

        if (isset($qMiles)) {
            $st->addProperty('TotalQualifyingFlights', $qFlights);
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
                $this->logger->debug($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length);

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
