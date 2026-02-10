<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Schema\Parser\Email\Email;

// similar parser aeroplan:RevisedItinerary (not the same, just similar)
class It4104615 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aeroplan/it-113091860.eml, aeroplan/it-4104615.eml, aeroplan/it-4133798.eml, aeroplan/it-54456690.eml, aeroplan/it-5765339.eml";

    public $reFrom = "notification@aircanada.ca";
    public $reSubject = [
        "en" => "Air Canada: FLIGHT DISRUPTION",
    ];
    public $reBody = 'Air Canada';
    public $reBody2 = [
        "en" => "Booking Reference:",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $f->general()->confirmation($this->re("#Booking Reference:\s+(\w+)#", $text), 'Booking Reference');

        if ($travellers = $this->re("#Booking Reference:\s+\w+\s*\n\s*([A-z\s.,()@:]+)\n#", $text)) {
            if ($str = $this->re("#Passenger\(s\):(.+)#", $travellers)) {
                $f->general()->travellers(explode(', ', $str));
            } else {
                $f->general()->travellers(explode(', ', $travellers));
            }
        }

        $type = '';

        if (($pos = strpos($text, 'REVISED ITINERARY:')) !== false || ($pos = strpos($text, 'Revised Itinerary:')) !== false) {
            $text = substr($text, $pos);
        } elseif (($pos = strpos($text, 'Your updated itinerary:')) !== false) {
            $text = substr($text, $pos);
        } elseif (($pos = strpos($text, 'Cancelled flight(s):')) !== false) {
            $text = substr($text, $pos);
            $type = 'Cancelled';
        }
        // SN2064
        // Edinburgh  to Brussels
        // Departing: Thu Jun-23, 2016 at 16:00
        // Arriving: Thu Jun-23, 2016 at 18:35
        $regexp = "#\s*(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*\n\s*" .
            "(?<DepName>.+?)\s+to\s+(?<ArrName>.+?)\s*\n\s*" .
            "Departing:\s+(?<DepDate>.+?)\s+at\s+(?<DepTime>\d+:\d+(?:\s+[AP]M)?)\s*\n\s*" .
            "Arriving:\s+(?<ArrDate>.+?)\s+at\s+(?<ArrTime>\d+:\d+(?:\s+[AP]M)?)\s*\n\s*" .
            "#si";

        $regexp2 = "#\s*(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*{$this->opt($this->t('operated by '))}?(?<Operator>.+)?\n\s*Departing\s*(?<DepName>.+)\s*\((?<DepCode>[A-Z]{3})\)\s*on\s*(?<DepDate>.+?)\s+(?:@|at)\s+(?<DepTime>\d+:\d+(?:\s+[AP]M)?)\s*\n\s*Arriving in\s*(?<ArrName>.+)\s*\((?<ArrCode>[A-Z]{3})\)\s*on\s*(?<ArrDate>.+?)\s+(?:@|at)\s+(?<ArrTime>\d+:\d+(?:\s+[AP]M)?)#";

        if (preg_match_all($regexp, $text, $flights, PREG_SET_ORDER) || preg_match_all($regexp2, $text, $flights, PREG_SET_ORDER)) {
            foreach ($flights as $flight) {
                $s = $f->addSegment();
                $s->airline()->name($flight['AirlineName']);
                $s->airline()->number($flight['FlightNumber']);

                if (!empty($flight['DepCode'])) {
                    $s->departure()->code($flight['DepCode']);
                } else {
                    $s->departure()->noCode();
                }

                $s->departure()->name($flight['DepName']);
                $s->departure()->date(strtotime($this->normalizeDate($flight['DepDate'] . ', ' . $flight['DepTime'])));

                if (!empty($flight['ArrCode'])) {
                    $s->arrival()->code($flight['ArrCode']);
                } else {
                    $s->arrival()->noCode();
                }

                $s->arrival()->name($flight['ArrName']);
                $s->arrival()->date(strtotime($this->normalizeDate($flight['ArrDate'] . ', ' . $flight['ArrTime'])));

                if (isset($flight['Operator']) && !empty($flight['Operator'])) {
                    $s->airline()
                        ->operator($flight['Operator']);
                }

                if ($type === 'Cancelled') {
                    $s->extra()
                        ->status('Cancelled')
                        ->cancelled();
                }
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getBody();

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
        $this->date = strtotime($parser->getHeader('date'));
        $text = $parser->getBody();
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($email, $text);
        $email->setType('reservations');

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]",
            $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]",
            $root);
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
            "#^\w+\s+(\w+)-(\d+),\s+(\d{4}),\s+(\d+:\d+)(?:\s+[AP]M)?$#",
        ];
        $out = [
            "$2 $1 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
