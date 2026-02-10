<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingCancelled extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-30558699.eml, aeroplan/it-471540733.eml, aeroplan/it-489632332.eml, aeroplan/it-56437948.eml";
    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = "@aircanada.";
    private $detectSubject = [
        "en" => ["Booking cancelled"],
    ];
    private $detectCompany = 'Air Canada';
    private $detectBody = [
        "en" => ["You have successfully cancelled your flight", "Your booking has been cancelled", 'You have successfully cancelled booking'],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
//        foreach ($this->detectBody as $lang => $re){
        //			if (strpos($this->http->Response["body"], $re) !== false){
        //				$this->lang = $lang;
        //				break;
        //			}
        //		}

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
            foreach ($dBody as $detectByBody) {
                if (strpos($body, $detectByBody) !== false) {
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
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $travellers = $this->http->FindNodes("//td[" . $this->eq("Passengers") . "]/following::td[1]");

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[starts-with(normalize-space(), 'Ticket number')]/preceding::text()[normalize-space()][1]");
        }

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("(.//text()[{$this->eq('Name')}])[1]/following::text()[normalize-space(.)][1]");
        }

        $date = $this->normalizeDate($this->nextText("Booking Date:"));

        if (empty($date)) {
            $date = $this->normalizeDate($this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date of issue:')]", null, true, '/[:]\s*(.+)/'));
        }

        $f->general()
            ->confirmation($this->nextText(["Booking Reference:", "You have successfully cancelled booking"], null, "/^\s*([A-Z\d]{5,7})\.?\s*$/"), "Booking Reference")
            ->travellers($travellers);

        if (!empty($date)) {
            $f->general()
                ->date($date);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains(["You have successfully cancelled", "Your booking has been cancelled"]) . "])[1]"))) {
            $f->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $tickets = array_filter($this->http->FindNodes("//td[" . $this->eq("Ticket Number") . "]/following::td[1]", null, "#^\s*(\d{7,})\s*$#"));

        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
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
            "#^\s*(\d{1,2})\s+([^\s\d\.\,]+)[\s,]+(\d{4})\s*$#ui", //10 Dec, 2019
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
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
