<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReceiptPlain extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-29959246.eml, aeroplan/it-30024613.eml, aeroplan/it-30545310.eml, aeroplan/it-8580142.eml";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "confirmation@aircanada.ca";
    private $detectSubject = [
        "en" => [
            "Air Canada - Receipt - Seat Change Charge",
            "Air Canada - Receipt - Baggage Fee",
            "Air Canada - Receipt - Upgrade Fee",
        ],
    ];
    private $detectCompany = 'aircanada';
    private $detectBody = [
        "en" => "Departure Date:",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();

//        foreach($this->detectBody as $lang => $re){
        //			if(strpos($text, $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email, $text);

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
        return count(self::$dictionary) * 3;
    }

    private function parseEmail(Email $email, string $text)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
        ;
        $travellers = $this->res("#Passenger:\s+(.+)#", $text);

        if (!empty($travellers)) {
            $f->general()
                ->travellers($travellers)
            ;
        }

        // Segments
        $s = $f->addSegment();

        // Airline
        if (preg_match("#(?:Seat Change|Paid Upgrade to (?<class>.+?))\s+(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(?<fn>\d{1,5})\s+#", $text, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            if (!empty($m['class'])) {
                $s->extra()->cabin($m['class']);
            }
        } else {
            $s->airline()
                ->noName()
                ->noNumber()
            ;
        }

        // Departure
        if (preg_match("#Departure City:\s+(?<Name>.*?)(?:-(?<Terminal>.*?))?\s+(?<Code>[A-Z]{3})\s*[\n\r]#ui", $text, $m)) {
            $s->departure()
                ->code($m['Code'])
                ->name($m['Name'])
                ->noDate()
                ->day($this->normalizeDate($this->re("#Departure Date:\s+(.+)#", $text)))
                ->terminal($m['Terminal'] ?? null, true, true)
            ;
        }

        // Arrival
        if (preg_match("#Destination City:\s+(?<Name>.*?)(?:-(?<Terminal>.*?))?\s+(?<Code>[A-Z]{3})\s*[\n\r]#ui", $text, $m)) {
            $s->arrival()
                ->code($m['Code'])
                ->name($m['Name'])
                ->noDate()
                ->day($this->normalizeDate($this->re("#Departure Date:\s+(.+)#", $text)))
                ->terminal($m['Terminal'] ?? null, true, true)
            ;
        }

        return $email;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        //		$this->http->log($str);
        $in = [
            "#^(\d{4})-(\d+)-(\d+)$#", //2017-06-30
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (!empty($m[$c])) {
            return $m[$c];
        }

        return [];
    }
}
