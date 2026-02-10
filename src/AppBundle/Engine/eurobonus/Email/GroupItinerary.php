<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class GroupItinerary extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-27813495.eml";

    private $detectFrom = '@sas.';

    private $detectSubject = [
        'da' => 'Bookingreference: ',
    ];

    private $detectCompany = 'www.sas.';

    private $detectBody = [
        'da' => ['Antal personer:'],
        'no' => ['Antall reisende:'],
    ];
    private $date;
    private $lang = 'da';
    private static $dict = [
        'da' => [
            //			"Dato:" => "",
            //			"Antal personer:" => "",
            //			"Reference:" => "",
            //			"Pris per person" => "",
            //			"Total pris per person" => "",
        ],
        'no' => [
            "Dato:"                 => "Dato:",
            "Antal personer:"       => "Antall reisende:",
            "Reference:"            => "Bestillingsreferanse:",
            "Pris per person"       => "Pris per person",
            "Total pris per person" => "Total pris per person",
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->date = strtotime($parser->getHeader('date'));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (strpos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($body, $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $conf = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Reference:")) . "]/ancestor::td[1]/following-sibling::td[1])[1]", null, true, "#^\s*([A-Z\d]{5,})\s*$#");

        if (!empty($conf)) {
            $f->general()->confirmation($conf);
        }

        $date = $this->normalizeDate($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Dato:")) . "])[1]", null, true, "#:\s*(.+)#"));

        if (!empty($date)) {
            $this->date = $date;
            $f->general()->date($date);
        }

        // Segments
        $posBegin = count($this->http->FindNodes("(//text()[" . $this->starts($this->t("Reference:")) . "]/ancestor::*[following-sibling::*[" . $this->starts($this->t("Pris per person")) . "]][1])[1]/preceding-sibling::*"));
        $posEnd = count($this->http->FindNodes("(//text()[" . $this->starts($this->t("Reference:")) . "]/ancestor::*[following-sibling::*[" . $this->starts($this->t("Pris per person")) . "]][1]/following-sibling::*[" . $this->starts($this->t("Pris per person")) . "])[1]/preceding-sibling::*"));
        $roots = $this->http->XPath->query("(//text()[" . $this->starts($this->t("Reference:")) . "]/ancestor::*[following-sibling::*[" . $this->starts($this->t("Pris per person")) . "]][1])[1]/following-sibling::*[position() < " . ($posEnd - $posBegin) . "][normalize-space()]");
        $segmentText = '';

        foreach ($roots as $root) {
            $segmentText .= implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
        }
        $segments = $this->split("#(?:^|\n)\s*((?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?:\d{1,5})\s*\S.+?-.+? [\d:]{3,5}-[\d:]{3,5})#", $segmentText);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("#^(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\s+(?<date>\d+ ?[^\d\s]+(?<year> ?\d{2,4})?)\s+(?<dname>.+)-(?<aname>.+)\s+(?<dtime>\d{1,2}[:]?\d{2})[\s\-]+(?<atime>\d{1,2}[:]?\d{2})#", $stext, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                if (empty(trim($m['year'])) && empty($this->date)) {
                    return $email;
                }

                // Departure
                $s->departure()
                    ->noCode()
                    ->name(trim($m['dname']))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['dtime']))
                ;

                if (empty(trim($m['year'])) && !empty($s->getDepDate()) && $this->date > $s->getDepDate()) {
                    $s->departure()->date(strtotime("+1 year", $s->getDepDate()));
                }

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name(trim($m['aname']))
                    ->date($this->normalizeDate($m['date'] . ', ' . $m['atime']))
                ;

                if (empty(trim($m['year'])) && !empty($s->getArrDate()) && $this->date > $s->getArrDate()) {
                    $s->arrival()->date(strtotime("+1 year", $s->getArrDate()));
                }
            }
        }

        // Price
        $total = $this->http->FindSingleNode("(//*[" . $this->starts($this->t("Total pris per person")) . "])[1]", null, true, "#:(.+)#");
        $countTravellers = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Antal personer:")) . "]/ancestor::td[1]/following-sibling::td[1])[1]", null, true, "#^\s*(\d+)\s*$#");

        if (!empty($countTravellers) && $total && preg_match('/\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\d \-]*)\s*$/', $total, $m)
                || preg_match('/\s*(?<amount>\d[,.\d\s\-]*)\s*(?<currency>[A-Z]{3})\s*$/', $total, $m)) {
            $f->price()
                ->total($countTravellers * $this->normalizeAmount($m['amount']))
                ->currency($m['currency'])
            ;
        }

        return $email;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function normalizeAmount(string $string): string
    {
        switch ($this->lang) {
            case 'da':
                $string = str_replace('.', '', $string);

break;

            case 'no':
                $string = str_replace(',-', '', $string);

break;
        }

        if (is_numeric($string)) {
            return (float) $string;
        }

        return null;
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'USD' => ['$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function normalizeDate($instr)
    {
        $year = date('Y', $this->date);
        $in = [
            "#^\s*(\d{1,2})\s*([^\d\s]+),\s+(\d{1,2})[:]?(\d{2})\s*$#i", // 12SEP 1430
            "#^\s*(\d{1,2})\s*([^\d\s]+)\s*(\d{4}),\s+(\d{1,2})[:]?(\d{2})\s*$#i", // 13 januari 2019 21:55
        ];
        $out = [
            "$1 $2 {$year} $3:$4",
            "$1 $2 $3 $4:$5",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#^\s*\d+\s+([^\d\s]+)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'sv')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
