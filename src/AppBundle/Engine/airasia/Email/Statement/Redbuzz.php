<?php

namespace AwardWallet\Engine\airasia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class Redbuzz extends \TAccountChecker
{
    public $mailFiles = "airasia/it-62827236.eml, airasia/it-62867616.eml, airasia/it-62941226.eml";

    public static $dictionary = [
        'en' => [
            "BIG Member ID:" => "BIG Member ID:",
            //            "Hello," => "",
        ],
        'ko' => [
            "BIG Member ID:" => "BIG 회원 ID:",
            "Hello,"         => "안녕하세요",
        ],
        'zh' => [
            "BIG Member ID:" => "BIG會員ID:",
            "Hello,"         => "你好,",
        ],
        'id' => [
            "BIG Member ID:" => "ID BIG Member:",
            "Hello,"         => "Halo,",
        ],
        'th' => [
            "BIG Member ID:" => "หมายเลขสมาชิก BIG:",
            "Hello,"         => "Halo,",
        ],
    ];

    private $detectFrom = "no-reply@redbuzz.airasia.com";

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $t) {
            if (!empty($t['BIG Member ID:']) && !empty($this->http->FindSingleNode("//text()[" . $this->contains($t["BIG Member ID:"]) . "]"))) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        // BIGShotID
        $number = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("BIG Member ID:")) . "]", null, true,
            "#\s*" . $this->preg_implode($this->t("BIG Member ID:")) . "\s*(\d{5,})\s*$#u");
        $st->addProperty('BIGShotID', $number);

        // Balance
        if (!empty($number)) {
            $st->setNoBalance(true);
        }

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("BIG Member ID:")) . "]/preceding::text()[normalize-space()][1]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Hello,")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*$#u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("BIG Member ID:")) . "]", null, true,
                "#^\s*" . $this->preg_implode($this->t("Hello,")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})?\s*님?(?:" . $this->preg_implode($this->t("BIG Member ID:")) . "|$)#u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', rtrim($name, '님'));
        }

        $class = explode('\\', __CLASS__);
        $email->setType('Statement' . end($class));

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
}
