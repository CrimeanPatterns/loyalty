<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReadyToTrip extends \TAccountChecker
{
    public $mailFiles = "asia/it-30637563.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = [
        "cathaypacific.com",
    ];
    private $detectSubject = [
        "en" => "days away",
    ];
    private $detectCompany = [
        "Cathay Pacific Airways Limited",
    ];
    private $detectBody = [
        "en" => "Your current booking details are detailed below",
    ];

    private $lang = "en";

    public function flight(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()->confirmation($this->nextText($this->t("Booking reference:")), "Booking reference");

        // Segments
        $xpath = "//text()[normalize-space()='Departs']/ancestor::tr[contains(., 'Arrives')][2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $sText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            $date = trim($this->re("#^(.+?)\|#s", $sText));

            // Airline
            if (preg_match("#\|\s*([A-Z\d]{2})[ ]?(\d{1,5})\s+#", $sText, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            if (preg_match("#Operated by\s+(.+)#", $sText, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            if (preg_match("#\|\s*[A-Z\d]{2}[ ]?\d{1,5}\s*\n(?<dep>.+?)\s+to\s+(?<arr>.+)\s+Departs#", $sText, $m)) {
                // Departure
                $s->departure()
                    ->noCode()
                    ->name($m['dep'])
                ;
                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($m['arr'])
                ;
            }

            if ($date && preg_match("#Departs\s+Arrives\s+(?<dep>\d+:\d+)\s*\n\s*(?<arr>\d+:\d+)\s*(?<nextday>\+[ ]*\d+)?\s+#", $sText, $m)) {
                // Departure
                $s->departure()
                    ->date($this->normalizeDate($date . ' ' . $m['dep']));
                // Arrival
                $s->arrival()
                    ->date($this->normalizeDate($date . ' ' . $m['arr']));

                if (!empty($m['nextday']) && !empty($s->getArrDate())) {
                    $s->arrival()->date(strtotime($m['nextday'] . 'day', $s->getArrDate()));
                }
            }
        }

        return $email;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        //		$body = html_entity_decode($this->http->Response["body"]);
        //		foreach($this->detectBody as $lang => $dBody){
        //			if (stripos($body, $dBody) !== false) {
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->flight($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (strpos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $finded = false;

        foreach ($this->detectFrom as $dFrom) {
            if (strpos($headers["from"], $dFrom) !== false) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
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
        $body = $parser->getHTMLBody();
        $finded = false;

        foreach ($this->detectCompany as $dCompany) {
            if (strpos($body, $dCompany) !== false || $this->http->XPath->query("//a[contains(@href, '" . $dCompany . "')]")->length > 0) {
                $finded = true;

                break;
            }
        }

        if (!$finded) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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
            //			"#^$#",//Fri, 18 Jan 2019 09:45
        ];
        $out = [
            //			"",
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
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
