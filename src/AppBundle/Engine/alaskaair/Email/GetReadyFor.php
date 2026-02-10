<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class GetReadyFor extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-59721608.eml, alaskaair/it-91037818.eml";

    public static $dictionary = [
        "en" => [
            'Hello,' => ['Hello,', 'Hi,'],
        ],
    ];

    private $detectFrom = "alaskaair.com";

    private $detectSubject = [
        "en" => ", get ready for", // Denis, get ready for Kahului / Maui.
    ];
    private $detectCompany = ".alaskaair.com";
    private $detectBody = [
        "en" => ["Ready, set, jetset"],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseHotel($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->detectFrom) !== false;
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
        $traveller = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Hello,')) . "]", null, true, "#{$this->opt($this->t('Hello,'))}\s*(.+?)\s*(?:\||$)#");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code'))}]/preceding::text()[{$this->starts($this->t('Hello,'))}][1]", null, true, "/{$this->opt($this->t('Hello,'))}\s*(.+?)\s*(?:\||$)/");
        }

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation code:")) . "]/following::text()[normalize-space()][1]"))
            ->traveller($traveller, false);

        $account = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation code'))}]/preceding::text()[{$this->contains($this->t('Member:'))}][1]", null, true, "/{$this->opt($this->t('Member:'))}\s*(.+)/");

        if (!empty($account)) {
            $f->program()
                ->account($account, true);
        }

        // Segments
        $xpath = "//img[contains(@src, 'plane.png')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name('Alaska Airlines')
                ->noNumber()
            ;

            // Departure
            $dep = implode("\n", $this->http->FindNodes("./td[1]//text()[normalize-space()]", $root));

            if (preg_match("#(.+)\n([A-Z]{3})\n(.+)#", $dep, $m)) {
                $s->departure()
                    ->code($m[2])
                    ->noDate()
                    ->day(strtotime($m[1]))
                    ->name($m[3])
                ;
            }

            // Arrival
            $arr = implode("\n", $this->http->FindNodes("./td[3]//text()[normalize-space()]", $root));

            if (preg_match("#(.+)\n([A-Z]{3})\n(.+)#", $arr, $m)) {
                $s->arrival()
                    ->code($m[2])
                    ->noDate()
                    ->day(strtotime($m[1]))
                    ->name($m[3])
                ;
            }
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
//        $this->logger->debug($date);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
