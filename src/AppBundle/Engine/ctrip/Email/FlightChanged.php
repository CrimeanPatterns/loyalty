<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightChanged extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-114583233.eml";

    public $detectFrom = ["_flight@trip.com"];

    public $detectSubject = [
        // en
        "Flight Changed",
        // nl
        "Vlucht gewijzigd",
    ];

    public $detectBody = [
        'en'  => ['Flight Changed'],
        'nl'  => ['Vlucht gewijzigd'],
    ];

    public $lang = '';
    public static $dictionary = [
        "en" => [
            "Booking No.:" => ["Booking No.:", "Booking No."],
            //            "Original Flight" => "",
        ],
        "nl" => [
            "Booking No.:"    => "Boekingsnummer:",
            "Original Flight" => "Oorspronkelijke vlucht",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".trip.com/")]')->length < 2) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query('//text()[' . $this->contains($detectBody) . ']')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query('//text()[' . $this->contains($detectBody) . ']')->length > 0) {
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

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking No.:")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d{5,})\s*$/"));

        // FLIGHT

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        // Segments
        $xpath = "//text()[contains(translate(normalize-space(), '0123456789', '##########'), '##:##') and following::text()[" . $this->eq($this->t("Original Flight")) . "]]/ancestor::table[count(descendant::text()[contains(translate(normalize-space(), '0123456789', '##########'), '##:##')]) = 2][1]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $dName = [];
            $aName = [];

            $node = implode("\n", $this->http->FindNodes("preceding::tr[normalize-space()][1]//text()[normalize-space()]", $root));

            if (preg_match("/^\s*(?<dname>.+?)\s+-\s+(?<aname>.+)\n[\s\S]*·\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5})\s*\|\s*(?<cabin>[[:alpha:] ]+)/", $node, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;
                $dName[] = $m['dname'];
                $aName[] = $m['aname'];

                $s->extra()
                    ->cabin($m['cabin']);
            }

            $node = implode("\n", $this->http->FindNodes(".//td[normalize-space()]", $root));

            if (preg_match("/^\s*(?<ddate>\d{2}:\d{2}.*\d{4}.*)\n(?<dname>.+)\n\s*(?<adate>\d{2}:\d{2}.*\d{4}.*)\n(?<aname>.+)/", $node, $m)) {
                if (preg_match("/^(.+Airport)(\s+\S.+)\s*$/u", $m['dname'], $mat)) {
                    $dName[] = $mat[1];
                    $s->departure()
                        ->terminal(trim(preg_replace(["/^\s*T(\d[\dA-Z]*)\s*$/", "/\s*Terminal\s*/i"], ['$1', ''], $mat[2])));
                } else {
                    $dName[] = $m['dname'];
                }
                $s->departure()
                    ->noCode()
                    ->name(implode(', ', $dName))
                    ->date($this->normalizeDate($m['ddate']))
                ;

                if (preg_match("/^(.+Airport)(\s+\S.+)\s*$/u", $m['aname'], $mat)) {
                    $aName[] = $mat[1];
                    $terminal = trim(preg_replace(["/^\s*T(\d[\dA-Z]*)\s*$/", "/\s*Terminal\s*/i"], ['$1', ''], $mat[2]));

                    if (!empty($terminal)) {
                        $s->arrival()
                            ->terminal($terminal);
                    }
                } else {
                    $aName[] = $m['aname'];
                }
                $s->arrival()
                    ->noCode()
                    ->name(implode(', ', $aName))
                    ->date($this->normalizeDate($m['adate']))
                ;
            }
        }

        return $email;
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
//        $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
            //            "#^([^\d\s]+)\s+(\d+),\s+(\d{4}),\s+(\d+:\d+)$#", //Sep 8, 2017, 15:00
        ];
        $out = [
            //            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
            'บาท' => 'THB',
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

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
