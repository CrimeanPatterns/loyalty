<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationOfChangesJunk extends \TAccountChecker
{
    public $mailFiles = "british/it-115428413.eml";
    public $detectFrom = ["BA.CustSvcs@email.ba.com"];
    public $detectSubject = [
        // en
        "Confirmation of changes to Booking Reference:",
        // de
        "Bestätigung der Änderungen zur Buchungsreferenz:",
        // es
        "Confirmación de cambios a la Referencia de la reserva:",
    ];
    public $detectBody = [
        "en"=> [
            ["This is confirmation of changes made to your British Airways booking.", 'Summary of changes']
        ],
        "de"=> [
            ["Hiermit bestätigen wir die Änderungen, die an Ihrer British Airways Buchung vorgenommen wurden.", 'Zusammenfassung der Änderungen']
        ],
        "es"=> [
            ["Confirmamos los cambios realizados a su reserva con British Airways.", 'Resumen de cambios']
        ],
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, 'www.britishairways.com')]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (is_array($dBody) && count($dBody)
                    && $this->http->XPath->query("//text()[".$this->contains($dBody[0])."]")->length > 0
                    && $this->http->XPath->query("//text()[".$this->contains($dBody[1])."]")->length > 0
                ) {
                    $this->lang = $lang;
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) == true
            && $this->detectEmailByBody($parser) == true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Thursday 10 November 2016
            "#^(\d+:\d+) (\d+) ([^\s\d]+)$#", //07:05 10 Nov
        ];
        $out = [
            "$1",
            "$2 $3 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function containsText($text, $needle): bool
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
