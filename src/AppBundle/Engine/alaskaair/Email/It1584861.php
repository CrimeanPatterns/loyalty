<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1584861 extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-1584861.eml, alaskaair/it-1707141.eml, alaskaair/it-1707147.eml, alaskaair/it-1710749.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "alaskaair.com";

    private $detectSubject = [
        "en" => "Departing on Time at",
    ];
    private $detectCompany = ".alaskaair.com";
    private $detectBody = [
        "en" => ["Terminal / Gate"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["subject"]) || empty($headers["from"])) {
            return false;
        }

        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if (($this->http->XPath->query("//a[contains(@href,'" . $this->detectCompany . "')]")->length === 0)) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
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
        return count(self::$dictionary);
    }

    private function parseHotel(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Confirmation Code:")) . "])[1]", null, true, "#" . $this->preg_implode($this->t("Confirmation Code:")) . "\s*([A-Z\d]{5,})\s*$#"))
            ->travellers($this->http->FindNodes("//td[" . $this->eq('Passengers:') . "]/following-sibling::td[1]/text()"))
        ;

        // Segments
        $xpath = "//td[contains(text(), 'Departure')]/ancestor::tr[1]/following-sibling::tr[normalize-space()]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("./td[2]", $root);

            if (preg_match("#^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d{1,5})\b#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("./td[3]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)))
                ->terminal(trim($this->http->FindSingleNode("./td[8]", $root, true,
                    "#(.+)/#")), true, true)
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("./td[6]", $root))
                ->date($this->normalizeDate($this->http->FindSingleNode("./td[7]", $root)))
            ;

            // Extra
            $s->extra()->stops(trim($this->http->FindSingleNode("./td[5]", $root)), true, true);
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

    private function normalizeDate($date)
    {
//        $this->logger->debug($date);
        $in = [
            "#^\s*(\w+) (\d{1,2}) (\d{2}) (\d{1,2}:\d{2} [ap]m)b?\s*$#i", // Mar 28 14 6:30 PM; Mar 09 14 11:50 PMb
        ];
        $out = [
            "$2 $1 20$3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        $this->logger->debug($date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
