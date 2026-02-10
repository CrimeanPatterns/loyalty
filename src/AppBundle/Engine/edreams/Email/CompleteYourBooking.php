<?php

namespace AwardWallet\Engine\edreams\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CompleteYourBooking extends \TAccountChecker
{
    public $mailFiles = "edreams/it-13324666.eml, edreams/it-13356523.eml, edreams/it-13957674.eml, edreams/it-14083060.eml";

    private $froms = [
        '@e.edreams.com',
    ];

    private $reSubject = [
        "en" => [
            "✈ Your eDreams Flight - complete your booking to",
        ],
        "es" => [
            " - Completa ahora tu reserva", // ✈ Tu vuelo a Kiev (KBP) - Completa ahora tu reserva
            "✈ Última llamada para el vuelo a",
        ],
        "de" => [
            "✈ Ihre Flugsuche mit eDreams - erneut ",
        ],
        "pt" => [
            "✈  O teu voo eDreams - conclui a tua reserva para",
        ],
    ];

    private $reBody = 'eDreams';

    private $reBody2 = [
        "en" => ['COMPLETE YOUR BOOKING NOW'],
        "es" => ['RESERVA AHORA'],
        "de" => ['Flüge vergleichen', 'Abbruch Ihrer Flugbuchung nach'],
        "pt" => ['COMPLETE A SUA RESERVA AGORA'],
    ];

    private static $dictionary = [
        "en" => [
            //			"Total Price" => "",
        ],
        "es" => [
            "Total Price" => "Precio Total",
        ],
        "de" => [
            "Total Price" => "Gesamtpreis",
        ],
        "pt" => [
            "Total Price" => "Preço Total",
        ],
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false || $this->http->XPath->query("//text()[contains(normalize-space(), '" . $re . "')]")->length > 0) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $email->ota();

        $this->flight($email);

        return $email;
    }

    public function flight(Email $email)
    {
        $f = $email->add()->flight();

        //General
        $f->general()->noConfirmation();

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Price")) . "][1]/ancestor::td[1]", null, true, "#" . $this->preg_implode($this->t("Total Price")) . "\s*(.+)#");

        if (!empty($total)) {
            $f->price()
                ->total($this->amount($total))
                ->currency($this->currency($total));
        }

        // Segments
        $xpath = "//img[contains(@src, 'depart.') or contains(@src, 'return.')]/ancestor::*[contains(translate(., '0123456789', 'dddddddddd'),'dd:dd')][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $node = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));

            if (preg_match("#^\s*\w+\s+(?<date>[\d/]{8,10})\s+(?<dName>.+)\((?<dCode>[A-Z]{3})\)\s+(?<dTime>\d+:\d+)\s+(?<aName>.+)\((?<aCode>[A-Z]{3})\)\s+(?<aTime>\d+:\d+)\s*$#u", $node, $m)) {
                // Airline
                $s->airline()
                    ->noName()
                    ->noNumber();
                // Departure
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['dTime']));
                // Arrival
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['aTime']));
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->froms as $value) {
            if (stripos($from, $value) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $re) {
                if (stripos($headers["subject"], $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $re) {
                if (stripos($body, $re) !== false || $this->http->XPath->query("//text()[contains(normalize-space(), '" . $re . "')]")->length > 0) {
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr)
    {
        $in = [
            "#^\s*(\d{1,2})/(\d{1,2})/(\d{4})\s*(\d+:\d+)\s*$#", // 15/08/2017 09:55
        ];
        $out = [
            "$1.$2.$3 $4",
        ];
        $str = preg_replace($in, $out, $instr);
        //		if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
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

    private function amount($s)
    {
        if (empty($s)) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = preg_replace("#[\d\,\. ]+#", '', $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function contains($field, $text = '.')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(normalize-space({$text}), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
