<?php

namespace AwardWallet\Engine\ebookers\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Member extends \TAccountChecker
{
    public $mailFiles = "ebookers/it-65650741.eml, ebookers/it-65650792.eml, ebookers/it-65650851.eml";

    public static $dictionary = [
        'en' => [
            //            'Get rewarded instantly with ebookers BONUS+!' => '',
            //            'Your balance:' => '',
        ],
        'de' => [
            'Get rewarded instantly with ebookers BONUS+!' => 'Sichern Sie sich mit ebookers Bonus+ sofortige Vorteile!',
            //            'Your balance:' => '',
        ],
    ];

    private $detectFrom = ".ebookers.";

    private $detectBody = [
        "en" => ["Your balance:", "Get rewarded instantly with ebookers BONUS+!"],
        "de" => ["Sichern Sie sich mit ebookers Bonus+ sofortige Vorteile!"],
    ];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
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

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class) . ucfirst($this->lang));

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//td[" . $this->starts($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (!empty($this->http->FindSingleNode("(//td[" . $this->contains($this->t("Get rewarded instantly with ebookers BONUS+!")) . "])[1]"))) {
            $email->setIsJunk(true);

            return $email;
        }

        $st = $email->add()->statement();

        if (!empty($this->http->XPath->query("//text()[" . $this->starts(["Your balance:"]) . "]")->length > 0)) {
            $st->setMembership(true);
        }

        $balance = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your balance:")) . "]/ancestor::td[1]", null, true,
            "/" . $this->preg_implode($this->t("Your balance:")) . "\s*(.+)\s*BONUS\+/");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $balance, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $balance, $m)) {
            $st->addProperty('Currency', $m['curr']);
            $st->setBalance($m['amount']);
        }

        $status = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your balance:")) . "]/preceding::*[normalize-space()][1][" . $this->contains($this->t("Member")) . "]", null, true,
            "/^\s*\w+\s*" . $this->preg_implode($this->t("Member")) . "/u");

        if (!empty($status)) {
            $st->addProperty('Tier', $status);
        }

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
