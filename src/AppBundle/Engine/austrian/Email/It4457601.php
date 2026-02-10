<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It4457601 extends \TAccountChecker
{
    public $mailFiles = "austrian/it-12639048.eml, austrian/it-4457601.eml, austrian/it-8684340.eml";

    public $reFrom = "traveldetails@austrian.com";
    public $reSubject = [
        "en"=> "Passenger Receipt",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en"=> "Zahlungsinformation",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $text = implode("\n", $this->pdf->FindNodes("//text()"));
        // echo $text ."\n\n-------------------\n\n";
        // echo $text;
        if (stripos($text, 'DEPARTURE') !== false && stripos($text, 'ARRIVAL') !== false) {//go to parse by ReceiptPdf.php
            return null;
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->nextText("Booking code"))
            ->traveller(preg_replace(["/ (\/\s*)?(DR )?(MR|MRS|MS|DR)$/", "/^\s*(.+?)\s*\/\s*(.+?)\s*$/"], ["", "$2 $1"], $this->nextText("Name")))
        ;

        // Price
        $f->price()
            ->total($this->re("#^Total / Gesamt.+?[A-Z]{3}\s+([\d,]+)#sm", $text))
            ->currency($this->re("#^Total / Gesamt.+?([A-Z]{3})#sm", $text))
        ;

        // Segments
        $flights = $this->re("#Flight\s+\/\s+(.*?)\s+Payment Details / Zahlungsinformation#ms", $text);
        preg_match_all("#(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s+" .
        "(?<Date>\d+\s+\w+\s+\d{2})\n" .
        "(?<DepName>.*?)\n" .
        "(?<ArrName>.*?)(?:(?=\s*Economy.*)|\n)" .
        "(?:(?<DepTime>\d+:\d+)\n" .
        "(?<ArrTime>\d+:\d+)\n)?" .
        "(?:(?<BookingClass>\w)\s+\(OK\)|(?<Cabin>[^\n]+))#ms", $flights, $segments, PREG_SET_ORDER);

        foreach ($segments as $segment) {

            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($segment['AirlineName'])
                ->number($segment['FlightNumber'])
            ;

            // Departure
            $s->departure()
                ->noCode()
                ->name($segment['DepName'])
            ;
            if (!empty($segment["DepTime"])) {
                $s->departure()
                    ->date(strtotime($segment["Date"] . "," . $segment["DepTime"]));
            } else {
                $s->departure()
                    ->noDate()
                    ->day(strtotime($segment["Date"]));
            }

            // Arrival
            $s->arrival()
                ->noCode()
                ->name($segment['ArrName'])
            ;
            if (!empty($segment["ArrTime"])) {
                $s->arrival()
                    ->date(strtotime($segment["Date"] . "," . $segment["ArrTime"]));
            } else {
                $s->arrival()
                    ->noDate()
                    ->day(strtotime($segment["Date"]))
                ;
            }

            // Extra
            $s->extra()
                ->cabin($segment['Cabin'] ?? null, true, true)
                ->bookingCode($segment['BookingClass'] ?? null, true, true)
            ;
        }
        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return false;
        }

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }


    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {

            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
                return false;
            }

            $this->pdf = clone $this->http;
            $this->pdf->setBody($html);

            foreach ($this->reBody2 as $lang => $re) {
                if (strpos($this->pdf->Response["body"], $re) !== false) {
                    $this->lang = $lang;
                    $this->parseHtml($email);
                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function nextText($field, $n = 1)
    {
        $segments = explode(" ", $field);
        $rule = implode(" and ", array_map(function ($s) { return "contains(., '{$s}')"; }, $segments));

        return $this->pdf->FindSingleNode("(//text()[{$rule}]/following::text()[normalize-space(.)][{$n}])[1]");
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
        $in = [
            "#^(\d+\.\d+\.\d{4})$#",
        ];
        $out = [
            "$$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
}
