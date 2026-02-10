<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Volunteering extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-143754186.eml";

    public static $dictionary = [
        "en" => [
            'Hello,' => ['Hello,', 'Hi,'],
        ],
    ];

    private $detectFrom = "notification@v.alaskaair.com";

    private $detectSubject = [
        // en
        "Volunteers needed to take a later flight.",
        "Thank you for volunteering",
    ];
    private $detectCompany = ".alaskaair.com";
    private $detectBody = [
        "en" => [
            "We may need volunteers to take a later flight",
            "Thanks for your interest in volunteering for a later flight",
        ],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseFlight($email);

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

    private function parseFlight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation Code")) . "]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t('Dear ')) . "]", null, true, "/{$this->opt($this->t('Dear '))}\s*([[:alpha:] \-]+?)\s*\W?\s*$/"));

        // Segments
        $xpath = "//*[*[normalize-space()][1][".$this->starts($this->t("Flight"))."] and *[normalize-space()][2][".$this->starts($this->t("Duration"))."]]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name('Alaska Airlines')
                ->noNumber()
            ;

            $dates = implode("\n", $this->http->FindNodes("*[normalize-space()][1]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*.+\n\s*(\d+:\d+.*?)\s*-\s*(\d+:\d+.*)\n(.+)/", $dates, $m)) {
                $s->departure()
                    ->date(strtotime($m[3].','.$m[1]));
                ;
                $s->arrival()
                    ->date(strtotime($m[3].','.$m[2]));
                ;
            }

            $routes = implode("\n", $this->http->FindNodes("*[normalize-space()][2]//text()[normalize-space()]", $root));
            if (preg_match("/^\s*.+\n\s*(\d.+)\n\s*([A-Z]{3})\s*-\s*([A-Z]{3})\b/", $routes, $m)) {
                $s->departure()
                    ->code($m[2])
                ;
                $s->arrival()
                    ->code($m[3])
                ;
                $s->extra()
                    ->duration($m[1]);
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
