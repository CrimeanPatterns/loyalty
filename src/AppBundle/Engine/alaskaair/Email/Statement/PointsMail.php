<?php

namespace AwardWallet\Engine\alaskaair\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class PointsMail extends \TAccountChecker
{
    public $mailFiles = "alaskaair/statements/it-68718699.eml, alaskaair/statements/it-68792975.eml";

    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = "mileageplan@points-mail.com";

    private $detectSubject = [
        "en" => "Alaska Airlines Purchase Miles",
    ];
    private $detectBody = [
        "en" => ["Thank you for buying Alaska Airlines Mileage Plan"],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//td[" . $this->starts($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//td[" . $this->starts($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        $number = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Mileage Plan™ Account Number:")) . "]/following::td[normalize-space()][1]", null, true,
            "/^\s*([\d]{5,})\s*$/");

        if (!empty($number)) {
            $st->setNumber($number);
            $st->setNoBalance(true);
        }

        $name = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Your Name:")) . "]/following::td[normalize-space()][1]", null, true,
            "/^\s*([[:alpha:] \-]{5,})\s*$/u");
        $st->addProperty('Name', $name);

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
