<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class UpgradeRequest extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-30584594.eml, aeroplan/it-30696066.eml";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "aircanada.";
    private $detectSubject = [
        "en" => [
            "Air Canada - Upgrade Request",
            "Air Canada - Upgrade Request Modified",
        ],
    ];
    private $detectCompany = 'Air Canada';
    private $detectBody = [
        "en" => "Summary of your offer",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach($this->detectBody as $lang => $re){
//            if(strpos($this->http->Response["body"], $re) !== false){
//                $this->lang = $lang;
//                break;
//            }
//        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseHtml($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || !isset($headers['subject'])) {
            return false;
        }

        if (self::detectEmailFromProvider($headers['from']) === false
                && stripos($headers['subject'], 'Costco Travel') === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (strpos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if (strpos($body, $dBody) !== false) {
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
        return count(self::$dictionary) * 2; //requested and modificated
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->nextText("Booking Reference:"), "Booking Reference")
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts("Dear ") . "]", null, true, "#Dear (\D+),\s*$#"))
        ;

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains("You have successfully cancelled") . "])[1]"))) {
            $f->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        // Segments
        $xpath = "//text()[normalize-space(.)='Departure:']/ancestor::td[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            $regexp = "#^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s*"
                    . "\n\s*(?<dCode>[A-Z]{3})\s*\n\s*(?<dName>[\s\S]+?)\s*\n\s*Departure:\s*\n\s*(?<dDate>.+)\s*"
                    . "\n\s*(?<aCode>[A-Z]{3})\s*\n\s*(?<aName>[\s\S]+?)\s*(?:\n\s*Arrival:\s*\n\s*(?<aDate>.+))?(?:\s+Operated By|$)#";

            if (preg_match($regexp, $node, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                // Departure
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->noDate()
                    ->day($this->normalizeDate($m['dDate']))
                ;

                // Arrival
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->noDate()
                    ->day($this->normalizeDate((!empty($m['aDate']) ? $m['aDate'] : $m['dDate'])))
                ;
            }
        }

        return $email;
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
        //		$this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*([^\s\d\.\,]+)\s+(\d{1,2})[\s,]+\s+(\d{4})\s*$#ui", //Dec 11, 2018
        ];
        $out = [
            '$2 $1 $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function nextText($field, $root = null, $regex = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regex);
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
}
