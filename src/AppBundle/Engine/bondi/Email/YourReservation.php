<?php

namespace AwardWallet\Engine\bondi\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "bondi/it-12385801.eml";

    public $lang = "es";

    public $textPDF;
    private $reFrom = "@flybondi.com";
    private $reSubject = [
        "es"=> "Tu código de reserva:",
    ];
    private $reBody = 'Flybondi.com';
    private $reBody2 = [
        "es"=> "Te enviamos los detalles de la reserva que realizaste desde",
    ];

    private static $dictionary = [
        "es" => [],
    ];
    private $date = null;
    private $parser = null;

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->nextText("Reserva:"))
            ->travellers($this->http->FindNodes("//text()[" . $this->eq("Pasajeros:") . "]/ancestor::table[1]/descendant::tr/td[2]"));

        $pdfs = $this->parser->searchAttachmentByName($f->getConfirmationNumbers()[0][0] . ".pdf");

        if (isset($pdfs[0])) {
            $pdf = $pdfs[0];

            if (($pdf = \PDF::convertToText($this->parser->getAttachmentBody($pdf))) !== null) {
                $this->textPDF = $pdf;

                $f->price()
                    ->total($this->amount($this->re("#Total:\s+(.+)#", $pdf)))
                    ->currency($this->currency($this->re("#Total:\s+(.+)#", $pdf)));
            }
        }

        $xpath = "//text()[" . $this->eq("Vuelo:") . "]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $s->airline()
                ->number($this->nextText("Vuelo:", $root));

            $s->airline()
                ->name($this->re("/([A-Z\d]{2})\s*{$s->getFlightNumber()}/", $this->textPDF));

            $s->departure()
                ->code($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/ancestor::tr[1]", $root, true, "#^([A-Z]{3}) - [A-Z]{3}#"))
                ->date($this->normalizeDate($this->nextText("Fecha de partida:", $root) . ', ' . $this->nextText("Hora de partida:", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/ancestor::tr[1]", $root, true, "#^[A-Z]{3} - ([A-Z]{3})#"))
                ->date($this->normalizeDate($this->nextText("Fecha de arribo:", $root) . ', ' . $this->nextText("Hora de arribo:", $root)));
        }
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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

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

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->parser = $parser;
        $this->logger->info('Relative date: ' . date('r', $this->date));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^(\d+)/(\d+)/(\d{4}), (\d+:\d+)$#", //18/4/2018, 06:00
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            'ARS$'=> 'ARS',
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
            '₹'   => 'INR',
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
}
