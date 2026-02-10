<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ElBoardingPass extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-30427963.eml, aeroplan/it-31021158.eml, aeroplan/it-31070611.eml";

    public $reFrom = ["confirmation@aircanada.ca"];
    public $reBody = [
        'en'  => ['Boarding Time:', 'ELECTRONIC Boarding Pass:'],
        'en2' => ['Boarding Time:', 'Electronic boarding pass'],
    ];
    public $reSubject = [
        'Electronic Boarding Pass',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
        ],
    ];
    private $keywordProv = 'Air Canada';
    private $date;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $text = text($body);
        $this->parseEmail($email, $text);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'aircanada.com') !== false) {
            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || strpos($headers["subject"], $this->keywordProv) !== false
                ) {
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

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function parseEmail(Email $email, string $text)
    {
        $text = preg_replace("/^[ >]+/m", '', $text);
        $reservations = $this->splitter("/([^\n]+\n[ ]*{$this->opt($this->t('Boarding Time:'))} \d+:\d+)/", $text);

        foreach ($reservations as $reservation) {
            $r = $email->add()->flight();
            $confNo = $this->re("/{$this->opt($this->t('Ref:'))} ([A-Z\d]{6})/", $reservation);
            $pax = $this->re("/([^\n]+)\s+{$this->opt($this->t('Seat:'))} \w+/", $reservation);
            $r->general()
                ->confirmation($confNo)
                ->traveller($pax);
            $s = $r->addSegment();

            if (preg_match("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+) *\- *(.+) {$this->opt($this->t('to'))} (.+)\s+{$this->opt($this->t('Boarding Time:'))}/",
                $reservation,
                $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->departure()
                    ->noCode()
                    ->noDate()
                    ->name($m[3]);
                $s->arrival()
                    ->noCode()
                    ->noDate()
                    ->name($m[4]);
            }
            $s->extra()->seat($this->re("/{$this->opt($this->t('Seat:'))} (\d+[A-z])/", $reservation), false, true);
            $date = $this->normalizeDate($this->re("/^{$this->opt($this->t('Date:'))} (.+?),/m", $reservation));

            if ($date < strtotime("-2 day", $this->date)) {
                $date = strtotime("+1 year", $date);
            }
            $s->departure()
                ->day($date);
            //Reserve code
//            $url = $this->re("/\*\* ELECTRONIC Boarding Pass:\s+(https:\/\/.+)/", $reservation);
//            if (!empty($url)) {
//                $bp = $email->add()->bpass();
//                $bp->setUrl($url)
//                    ->setFlightNumber($s->getAirlineName() . $s->getFlightNumber())
//                    ->setRecordLocator($confNo)
//                    ->setTraveller($pax)
//                    ->setDepDay($s->getDepDay());//??no method yet
//            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //02DEC
            '#^(\d+)\s*(\w{3})$#u',
        ];
        $out = [
            '$1 $2 ' . $year,
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
