<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "copaair/it-56646644.eml";
    private $lang = '';
    private $reFrom = [
        'copaair.com',
    ];
    private $reSubject = [
        ' ha sido cancelado',
    ];
    private $reProvider = ['Copa Airlines'];
    private $detectLang = [
        'es' => [
            'sin precedentes fuera de nuestro control, nos vemos obligados a cancelar tu vuelo',
        ],
    ];
    private static $dictionary = [
        'es' => [
            'cancellationNumber' => 'Tu Reservación',
            'cancelled'          => 'cancelar',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $t = $email->add()->flight();
        $t->general()->cancellationNumber(
            $this->http->FindPreg("/{$this->opt($this->t('cancellationNumber'))}\s*([A-Z\d]{5,6}):/", false, $parser->getHeader('subject'))
        );
        // vuelo CM 235 el 03/04/2020 desde PTY a ORD.
        $text = $this->http->FindSingleNode("//p[{$this->contains($this->t('obligados a cancelar tu vuelo'))}]");

        if (preg_match('#\w+ ([A-Z]{2})\s*(\d{2,4}) \w+ (\d+/\d+/\d{4}) \w+ ([A-Z]{3}) \w ([A-Z]{3})#', $text, $m)) {
            $s = $t->addSegment();
            $s->airline()->name($m[1]);
            $s->airline()->number($m[2]);
            $s->departure()->date2($this->ModifyDateFormat($m[3]));
            $s->departure()->code($m[4]);
            $s->arrival()->code($m[5]);
        }
        $t->general()->status($this->t('cancelled'));
        $t->general()->cancelled();
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
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
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $value) {
            if ($this->arrikey($this->http->Response['body'], $value) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'normalize-space(' . $node . ')="' . $s . '"';
                }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ',
                array_map(function ($s) use ($node) {
                    return 'contains(normalize-space(' . $node . '),"' . $s . '")';
                }, $field))
            . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
