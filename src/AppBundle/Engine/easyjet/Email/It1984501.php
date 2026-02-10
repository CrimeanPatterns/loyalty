<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1984501 extends \TAccountChecker
{
    public $mailFiles = 'easyjet/it-1984501.eml, easyjet/it-5708081.eml';
    public static $dictionary = [
        "en" => [],
    ];

    private $reSubject = 'easyJet booking reference:';

    private $lang = 'en';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@easyjet.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'blackhole@easyJet.com') !== false
            || stripos($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return ($this->http->XPath->query('//a[contains(@href,"//www.easyjet.com/EN")]')->length > 0
            || $this->http->XPath->query('//img[contains(@src,"//www.easyjet.com/common/img/page/logo_main.gif")]')->length > 0)
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"easyJet airline company")]')->length > 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Please print and take this booking confirmation with you to the airport")]')->length > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
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
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking reference:")) . "]/following::text()[normalize-space()][1]"))
            ->traveller($this->http->FindSingleNode("//text()[" . $this->contains($this->t("flying on:")) . "][1]", null, true, "#^\s*(?:Mr |Mrs )?(.+?) (?:is|are) flying on#"), true)
            ->status($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking confirmed")) . "]", null, true, "#Booking (confirmed)#"))
        ;

        // Price
        $total = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total paid")) . "]/following::td[normalize-space(.)][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $mat)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $mat)) {
            $f->price()
                ->total($this->amount($mat['amount']))
                ->currency($this->currency($mat['curr']));
        }

        // Segments
        $xpath = "//tr[(" . $this->contains($this->t("flying on:")) . ") and not(.//tr)]/following-sibling::tr[.//h5]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name('U2')
                ->number($this->http->FindSingleNode("./td[3]", $root, true, "#flight\s*(\d{1,5})\s*;#i"))
            ;

            $info = $this->http->FindSingleNode('(.//h5)[2]', $root);

            if (preg_match('/(.+?)\s*To\s*(.+)/i', $info, $ms)) {
                $s->departure()
                    ->noCode()
                    ->name($ms[1]);
                $s->arrival()
                    ->noCode()
                    ->name($ms[2]);
            }

            $info = $this->http->FindSingleNode('./td[3]', $root);

            if (preg_match('/dep\.(.+?)\s*arr\.\s*(.+)\s*/i', $info, $ms)) {
                $s->departure()
                    ->date($this->normalizeDate($ms[1]));
                $s->arrival()
                    ->date($this->normalizeDate($ms[2]));
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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            //Mon, May 20
            '#^\s*(\w+)\s+(\d+)\s+(\w+)\s*(\d+:\d+)\s*$#u', //Thursday 15 January
        ];
        $out = [
            '$1, $2 $3 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if ($year > 2010 && preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif ($year < 2010 && preg_match("#^(?<week>\w+), (?<date1>\d+ \w+\s*)(?<year>\d{4})(?<date2>.*)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            for ($j = 0; $j <= 3; $j++) {
                $try = strtotime($m['date1'] . ($m['year'] + $j) . $m['date2']);

                if ((int) date('N', $try) === $weeknum) {
                    $date = $try;

                    break;
                }
                $try = strtotime($m['date1'] . ($m['year'] - $j) . $m['date2']);

                if ((int) date('N', $try) === $weeknum) {
                    $date = $try;

                    break;
                }
            }
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
