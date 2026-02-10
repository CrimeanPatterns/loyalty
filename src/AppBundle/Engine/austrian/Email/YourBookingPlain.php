<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBookingPlain extends \TAccountChecker
{
    public $mailFiles = "austrian/it-12247292.eml, austrian/it-12382139.eml, austrian/it-5148200.eml";

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Your booking code is:"=> "Su código de reserva es:",
            "Passenger"            => "Pasajero",
            "From:"                => "De:",
            "To:"                  => "A:",
            "Departure:"           => "Salida:",
            "Arrival:"             => "Llegada:",
            "Flight:"              => "Vuelo:",
            "Tariff:"              => "Tarifa:",
        ],
        "sv" => [
            "Your booking code is:"=> "Din bokningskod är:",
            "Passenger"            => "Passagerare",
            "From:"                => "Från:",
            "To:"                  => "Till:",
            "Departure:"           => "Avgång:",
            "Arrival:"             => "Ankomst:",
            "Flight:"              => "Flyg:",
            "Tariff:"              => "Pris:",
        ],
        "fr" => [
            "Your booking code is:"=> "Votre code de réservation est:",
            "Passenger"            => "Passagers",
            "From:"                => "Au départ de:",
            "To:"                  => "Vers:",
            "Departure:"           => "Départ:",
            "Arrival:"             => "Arrivée:",
            "Flight:"              => "Vol:",
            "Tariff:"              => "Tarif:",
        ],
    ];

    public $lang = "en";
    private $reFrom = "@austrian.com";
    private $reSubject = [
        "en"=> "Your booking",
    ];
    private $reBody = 'austrian.com';
    private $reBody2 = [
        "en" => "Departure:",
        "es" => "Salida:",
        "sv" => "Avgång:",
        'fr' => 'Merci pour votre réservation',
    ];

    private $text = '';
    private $date = null;

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
        $this->http->FilterHTML = false;
        $text = $parser->getPlainBody();
        $text = str_replace(['&gt;', '<br>'], ['', ''], $text);
        $this->text = $text;

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($email);

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

    private function parsePlain(Email $email)
    {
        $text = str_replace('> ', '', $this->text);
        $text = str_replace('>', '', $text);

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#" . $this->t("Your booking code is:") . "\s+(.+)#", $text));

        if (preg_match_all("#" . $this->t("Passenger") . " \d+ \(.*?\) (.+)#", $text, $m)) {
            $f->general()
                ->travellers($m[1], true);
        }

        if (preg_match_all("#" . $this->t("Frequent flyer program:") . " (.+?\d+)\n#", $text, $m)) {
            $f->program()
                ->accounts($m[1], true);
        }

        preg_match_all("#" . $this->t("From:") . "[^\n]+\n\s*" . $this->t("To:") . "[^\n]+\n\s*" . $this->t("Departure:") . ".*?" . $this->t("Tariff:") . "[^\n]+#s", $text, $segments);

        foreach ($segments[0] as $stext) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->re("#" . $this->t("Flight:") . "\s*([A-Z\d]{2}) \d+#", $stext))
                ->number($this->re("#" . $this->t("Flight:") . "\s*[A-Z\d]{2} (\d+)#", $stext))
                ->operator($this->re("#" . $this->t("Flight:") . "\s*[A-Z\d]{2} \d+\s*(\D+)\s*\(#", $stext));

            $s->departure()
                ->noCode()
                ->name($this->re("#" . $this->t("From:") . "\s*(.+)#", $stext))
                ->date($this->normalizeDate($this->re("#" . $this->t("Departure:") . "\s*(.+)#", $stext)));

            $s->arrival()
                ->noCode()
                ->name($this->re("#" . $this->t("To:") . "\s*(.+)#", $stext))
                ->date($this->normalizeDate($this->re("#" . $this->t("Arrival:") . "\s*(.+)#", $stext)));

            $s->extra()
                ->cabin($this->re("#" . $this->t("Tariff:") . "\s*(.*?)/[A-Z]\b#", $stext))
                ->bookingCode($this->re("#" . $this->t("Tariff:") . "\s*.*?/([A-Z])\b#", $stext));
        }

        return true;
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
        //		$this->logger->debug($instr);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4}) (\d+:\d+) \(local time\)$#", //Sunday, September 13, 2015 14:15 (local time)
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4}) (\d+:\d+) \((?:hora local|heure locale)\)$#iu", //Viernes, 15 Junio 2018 11:30 (hora local)
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4}) (\d+:\d+) \(lokal tid\)$#", //Måndag, 3 augusti 2015 07:10 (lokal tid)
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1, $2",
            "$1, $2",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function rowColsPos($row, $splitter = "\s{2,}")
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#" . $splitter . "#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
