<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ShowBoardingInfo extends \TAccountChecker
{
    public $mailFiles = "ana/it-22408860.eml, ana/it-22786032.eml";
    public static $dictionary = [
        'ja' => [],
    ];

    private $detectFrom = 'ana.co.jp';
    private $detectSubject = [
        'ana国内線予約からのお知らせ',
    ];

    private $detectCompany = [
        'ana.co.jp',
    ];

    private $detectBody = [
        'ja' => [
            '搭乗口と搭乗方法をご',
        ],
    ];

    private $lang = 'en';
    private $date;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->date = strtotime($parser->getHeader('date'));
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $body = $parser->getPlainBody();
        $this->flight($email, $body);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        $findedCompany = false;

        foreach ($this->detectCompany as $detectBody) {
            if (stripos($body, $detectBody) !== false) {
                $findedCompany = true;

                break;
            }
        }

        if ($findedCompany == false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $detect) {
                if (stripos($body, $detect) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
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

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function flight(Email $email, string $body)
    {
        $body = str_replace("　", ' ', $body); //&#12288;

        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $segments = $this->split("#(\n\s*(?:\d+\.)?\d+/\d+\s+[A-Z\d]{2}|[A-Z\d]{2}[A-Z]\d{1,5}\s+.+?[ ]*-[ ]*.+)#", $body);

        foreach ($segments as $sbody) {
            $s = $f->addSegment();

            if (preg_match("#\n\s*(?:\d+\.)?(?<date>\d+/\d+)\s+(?<al>[A-Z\d]{2}|[A-Z\d]{2}[A-Z])(?<fn>\d{1,5})\s+"
                    . "(?<dep>.+?)[ ]*-[ ]*(?<arr>.+)\s+(?<depTime>\d+:\d+)[ ]*-[ ]*(?<arrTime>\d+:\d+)#", $sbody, $m)) {
                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
                $date = $this->normalizeDate($m['date']);

                if (empty($date)) {
                    return null;
                }

                // Departure
                $s->departure()
                    ->noCode()
                    ->name($m['dep'])
                    ->date(strtotime($m['depTime'], $date));

                // Arrival
                $s->arrival()
                    ->noCode()
                    ->name($m['arr'])
                    ->date(strtotime($m['arrTime'], $date));

                if (preg_match('/座席番号\s*(.+)\s*\n/', $sbody, $m) && preg_match_all('/\b\D*(\d{1,3}[A-Z])\b/', $m[1], $mat)) {
                    $s->extra()->seats($mat[1]);
                }
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\s*(\d+)/(\d+)\s*$#", // 6/26
        ];
        $out = [
            "$2.$1." . $year,
        ];
        $str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        $str = strtotime($str);

        if ($str < strtotime("-7days", $this->date)) {
            $str = strtotime("+1year", $str);
        }

        return $str;
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
