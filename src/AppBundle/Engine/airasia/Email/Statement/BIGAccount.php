<?php

namespace AwardWallet\Engine\airasia\Email\Statement;

// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BIGAccount extends \TAccountChecker
{
    public $mailFiles = "airasia/statements/it-62935242.eml, airasia/statements/it-62996650.eml, airasia/statements/it-63099845.eml, airasia/statements/it-77088571.eml";

    public static $dictionary = [
        'en' => [
            "BIG Member ID" => ["BIG Member ID", "BIG Member ID:"],
            //            "Points Balance as of" => "",
            //            "Dear " => "",
        ],
        'zh' => [
            "BIG Member ID"        => ["BIG會員ID", "BIG會員ID:", "BIG会员ID"],
            "Points Balance as of" => "BIG积分截至",
            "Dear "                => "親愛的 ",
        ],
        'th' => [
            "BIG Member ID" => ["หมายเลข BIG ID"],
            //            "Points Balance as of" => "",
            //            "Dear " => "",
        ],
        'id' => [
            "BIG Member ID" => ["ID Anggota BIG"],
            //            "Points Balance as of" => "",
            //            "Dear " => "",
        ],
    ];

    private $detectFrom = ["donotreply@airasiabigmails2.com", "no-reply@airasiabig.com"];

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
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
            if (!empty($t["BIG Member ID"])
                && $this->http->XPath->query("//text()[{$this->contains($t["BIG Member ID"])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }
        $email->setType('StatementBIGAccount' . ucfirst($this->lang));

        $st = $email->add()->statement();

        // BIGShotID
        $number = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("BIG Member ID")) . "]", null, true,
            "#\s*" . $this->preg_implode($this->t("BIG Member ID")) . ":?\s*(\d{5,})\s*$#u");

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("BIG Member ID")) . "]/following::text()[normalize-space()][1]", null, true,
                "#^\s*(\d{5,})\s*$#u");
        }
        $st->addProperty('BIGShotID', $number);

        // Balance
        $balance = implode(" ", $this->http->FindNodes("//td[" . $this->starts($this->t("Points Balance as of")) . "]//text()[normalize-space()]"));

        if (empty($balance)) {
            $balance = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Points Balance as of")) . "]/ancestor::*[self::div or self::td][1]//text()[normalize-space()]"));
        }

        if (preg_match("#" . $this->preg_implode($this->t("Points Balance as of")) . "\s*:?\s*([\d/\-]{6,}) (\d+)\s*$#", $balance, $m)) {
            if (strpos($m[1], '/') !== false) {
                $date1 = strtotime(preg_replace("#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*$#", "$1.$2.$3", $m[1]));
                $date2 = strtotime(preg_replace("#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*$#", "$2.$1.$3", $m[1]));
                $date = null;

                if (count(array_filter([$date1, $date2])) == 1) {
                    $date = array_shift(array_filter([$date1, $date2]));
                } elseif (!empty($date1) && !empty($date2)) {
                    $dateRel = strtotime($parser->getDate());

                    if (empty($dateRel)) {
                        $date = null;
                    }

                    if (abs($dateRel - $date1) < abs($dateRel - $date2)) {
                        $date = $date1;
                    } else {
                        $date = $date2;
                    }
                }
            } else {
                $date = $this->normalizeDate($m[1]);
            }
            $st->setBalance((int) ($m[2]));
            $st->setBalanceDate($date);
        }

        // Name
        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
            "#^\s*" . $this->preg_implode($this->t("Dear ")) . "\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*,\s*$#u");

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//span[" . $this->starts($this->t("BIG Member ID")) . "]/preceding-sibling::span[1]", null, true,
                "#^\s*\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*$#u");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//tr[" . $this->starts($this->t("BIG Member ID")) . "][count(preceding-sibling::tr[normalize-space()]) = 1]/preceding-sibling::tr[normalize-space()][1]", null, true,
                "#^\s*\s*([^\d\W]+(?: [^\d\W]+){0,4})\s*$#u");
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        if (!empty($number) && empty($balance) && empty($this->http->FindSingleNode("(//*[" . $this->starts($this->t("Points Balance")) . "])[1]"))) {
            $st->setNoBalance(true);
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{4})-(\d{2})-(\d{2})\s*$#iu", // 2019-09-23
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
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
