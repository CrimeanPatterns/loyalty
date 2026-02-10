<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "ana/it-20562747.eml";
    public static $dictionary = [
        'en' => [],
    ];

    private $detectFrom = 'ana.co.jp';
    private $detectSubject = [
        '[ANA] Departure time change Notice:',
    ];

    private $detectCompany = [
        'ANA/ALL NIPPON AIRWAYS',
    ];

    private $detectBody = [
        'en' => [
            'will be changed for the following flight',
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

        if (!$findedCompany) {
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
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation()
            ->traveller(trim($this->re("#Dear (.+?),#", $body)));

        $s = $f->addSegment();

        // Airline
        if (preg_match("#\n\s*([A-Z\d]{2}|[A-Z\d]{2}[A-Z])(\d{1,5}) on (.+?)\s+\[Initial schedule#", $body, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
            $date = $this->normalizeDate($m[3]);
        }

        if (isset($date) && preg_match("#\[Changed to\]\s+.*?from (?<dep>.+) at (?<depTime>.+?) and .*? at (?<arr>.+) at (?<arrTime>.+)#", $body, $m)) {
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
        $year = date("Y", $this->date);
        $in = [
            "#^\s*(\d+)[ \-]+([^\s\d\,\.]+)\s*$#", // 10-Aug
        ];
        $out = [
            "$1 $2 " . $year,
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $str = strtotime($str);
        $compareDate = date("d M Y", $this->date);

        if ($str < strtotime($compareDate)) {
            $str = strtotime("+1year", $str);
        }

        return $str;
    }
}
