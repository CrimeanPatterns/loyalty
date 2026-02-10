<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightCancelled extends \TAccountChecker
{
    public $mailFiles = "asia/it-56603092.eml, asia/it-56644377.eml, asia/it-56719792.eml, asia/it-56731052.eml, asia/it-56735982.eml";

    private $detectFrom = "@notification.cathaypacific.com";
    private $detectSubject = [
        "en" => "Important information about your flight",
        "zh" => "有關您的航班的重要信息",
    ];

    private $detectProvider = "cathaypacific.com";

    private $detectBody = [
        "en" => ["Flight cancellation", "your flight has been cancelled", "your flight will be cancelled"],
        "zh" => ["你的航班將會被取消。", "你的航班将会被取消。"],
    ];

    private $lang = "en";
    private static $dictionary = [
        'en' => [
            //            "Booking reference:" => "",
            //            "Dear" => "",
            //            "Passenger" => "",
            //            " to " => "",
            //            "Departing on" => "",
            //            "Scheduled departure:" => "",
            //            "Scheduled arrival:" => "",
            //            "" => "",
        ],
        'zh' => [
            "Booking reference:" => ["預訂參考編號：", "预订参考编号："],
            "Dear"               => ["親愛的", "尊敬的"],
            //            "Passenger" => "",
            //            " to " => "",
            "Departing on" => "出發日期：",
            //            "Scheduled departure:" => "",
            //            "Scheduled arrival:" => "",
            //            "" => "",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers["subject"])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $this->http->Response['body'];

        if (stripos($text, $this->detectProvider) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($text, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]",
            null, true, "/" . $this->opt($this->t("Booking reference:")) . "\s*([A-Z\d]{5,7})\s*$/");
        $confName = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]",
            null, true, "/(" . $this->opt($this->t("Booking reference:")) . ")\s*/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "]/following::text()[1]",
                null, true, "/^([A-Z\d]{5,7})$/");
            $confName = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Booking reference:')) . "][1]");
        }

        $f->general()
            ->confirmation($conf, trim($confName, ':：'))
            ->cancelled()
            ->status('Cancelled')
        ;

        $passenger = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/ancestor::*[1][not({$this->contains($this->t('Passenger'))})]", null, true, "#{$this->opt($this->t('Dear'))}\s+(.+)#");

        if (!empty($passenger)) {
            $f->general()
                ->traveller($passenger)
            ;
        }

        $xpath = "//text()[" . $this->contains($this->t(" to "), '.') . "]/ancestor::tr[1][.//img]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $text = implode(" ", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("#(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<fn>\d{1,5})\s+(?<dName>.+)\s*\((?<dCode>[A-Z]{3})\)\s*" . $this->opt($this->t(" to "), '.') . "\s*(?<aName>.+)\s*\((?<aCode>[A-Z]{3})\)\s+(?<date>.+)\s+(?<dTime>\d{1,2}:\d{2})\s*-\s*(?<aTime>\d{1,2}:\d{2})#",
                $text, $m)) {
                $s = $f->addSegment();

                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['dTime']))
                ;
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['aTime']))
                ;
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }
        }

        if ($nodes->length === 0) {
            $xpath = "//text()[" . $this->starts($this->t("Departing on"), '.') . "]/ancestor::td[1]";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $text = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

                if (preg_match("#" . $this->opt($this->t("Departing on"), '.') . "\s+(?<date>.+)\s+" . $this->opt($this->t("Scheduled departure:"), '.') . "\s*(?<dTime>\d{1,2}:\d{2})\s+" . $this->opt($this->t("Scheduled arrival:"), '.') . "\s*(?<aTime>\d{1,2}:\d{2})#",
                    $text, $m)) {
                    $s->departure()
                        ->noCode()
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['dTime']))
                    ;
                    $s->arrival()
                        ->noCode()
                        ->date($this->normalizeDate($m['date'] . ' ' . $m['aTime']))
                    ;
                } elseif (preg_match("#" . $this->opt($this->t("Departing on"), '.') . "\s*(?<date>.+)$#u", $text, $m)) {
                    $f->removeSegment($s);
                }

                $airline = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);

                if (preg_match("#^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<fn>\d{1,5})\s*$#", $airline, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                }
            }
        }

        return $email;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field));
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*\w,\s*(\d{1,2}) (\d{1,2}) (\d{4})\s+(\d{1,2}:\d{1,2})\s*$#u", // 木, 16 4 2020 16:15,
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }
}
