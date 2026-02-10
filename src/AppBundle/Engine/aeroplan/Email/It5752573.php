<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It5752573 extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-12586872.eml, aeroplan/it-5752573.eml";
    public $reFrom = "notification@aircanada.ca";
    public $reSubject = [
        "en"=> "Air Canada: FLIGHT CANCELLED",
    ];
    public $reBody = 'Air Canada';
    public $reBody2 = [
        "en"=> "Booking Reference:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(Email $email)
    {
        $text = strip_tags(str_replace("&nbsp;", " ", $this->http->Response["body"]));
        $text = preg_replace("#\s*\n\s*>+\s*#", "\n", preg_replace("#\s*\n\s*>+\s*#", "\n", $text));

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#Booking Reference:\s*(\w+)#", $text))
            ->travellers(array_map('trim', explode(",", str_replace(['null', '@'], ' ', $this->re("#Passenger\(s\):\s*(.+)#", $text)))));

        $tickets = array_filter(array_map('trim', explode(",", str_replace(['null', '@'], ' ', $this->re("#eTicket Number\(s\):\s*(.+)#", $text)))));

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        if (!$flights = $this->re("#REVISED ITINERARY:(.+)#ms", $text)) {
            $flights = $text;
        }

        preg_match_all("#(?<=\n)(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*(?:\s+\*+\s+Upgraded to Business Class\s+\*+)?\n" .
            "(?<DepName>.*?)\s+to\s+(?<ArrName>.*?)\n+" .
            "Departing:\s+(?<DepDate>.+)\n+" .
            "Arriving:\s+(?<ArrDate>.+)\n?" .
            "(?:\nSeats:\s+(?<Seats>.+))?\s*\n#u", $flights, $segments, PREG_SET_ORDER);

        if (count($segments) == 0) {
            $this->logger->error('1111111');
            preg_match_all("/\s*(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*\n\s*(?<DepName>.+?)\s+to\s+(?<ArrName>.+?)\s*\n\s*Departing:\s+(?<DepDate>.+?\s+at\s+\d+:\d+(?:\s+[AP]M)?)\s*\n\s*Arriving:\s+(?<ArrDate>.+?\s+at\s+\d+:\d+(?:\s+[AP]M)?)\s*\n\s*Seats\:\s*(?<Seats>.+)\n/u", $flights, $segments, PREG_SET_ORDER);
        }

        $this->logger->error($flights);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $s->airline()
                ->name($segment["AirlineName"])
                ->number($segment["FlightNumber"]);

            $s->departure()
                ->name($segment["DepName"])
                ->date(strtotime($this->normalizeDate($segment["DepDate"])))
                ->noCode();

            $s->arrival()
                ->name($segment["ArrName"])
                ->date(strtotime($this->normalizeDate($segment["ArrDate"])))
                ->noCode();

            if (!empty($segment["Seats"])) {
                $s->extra()
                    ->seats(explode(',', $segment["Seats"]));
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $this->http->setBody($parser->getPlainBody());

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

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
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
        $this->logger->error('IN-' . $str);

        $year = date("Y", $this->date);
        $in = [
            "#^\s*[^\d\s]+\s+([^\d\s]+)-(\d+),\s+(\d{4})\s+at\s+(\d+:\d+)\s*$#",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
