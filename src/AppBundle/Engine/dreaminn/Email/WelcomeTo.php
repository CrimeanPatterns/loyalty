<?php

namespace AwardWallet\Engine\dreaminn\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class WelcomeTo extends \TAccountChecker
{
    public $mailFiles = "dreaminn/it-36823434.eml";

    public static $dictionary = [
        "en" => [],
    ];

    private $detectFrom = ["dreaminnsantacruz.com", "dreaminnsc.com"];

    private $detectSubject = [
        "en" => "You are Confirmed at Dream Inn Santa Cruz",
    ];
    private $detectCompany = ["Dream Inn Santa Cruz", "dreaminnsc.com"];
    private $detectBody = [
        "en" => "WELCOME TO THE DREAM INN SANTA CRUZ",
    ];

    private $lang = "en";

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = html_entity_decode($this->http->Response["body"]);

        foreach ($this->detectBody as $lang => $dBody) {
            if (stripos($body, $dBody) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers["from"]) || empty($headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers["from"], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if ($this->striposAll($body, $this->detectCompany) === false) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if (strpos($body, $detectBody) !== false) {
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

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t("Reservation Number:")), "Reservation Number")
            ->traveller($this->nextTd($this->t("Guest Name:")), true)
            ->cancellation($this->http->FindSingleNode("//text()[normalize-space()='Cancellation and No-Show Policy']/following::text()[normalize-space()][1]"))
        ;

        // Hotel
        $h->hotel()
            ->name("Dream Inn Santa Cruz")
            ->address($this->http->FindSingleNode("//text()[normalize-space() = 'Address:']/following::text()[normalize-space()][1][ancestor::a[1]]"))
            ->phone($this->http->FindSingleNode("//text()[normalize-space() = 'Tel:']/following::text()[normalize-space()][1][ancestor::a[1]]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd("Arrival Date:")))
            ->checkOut($this->normalizeDate($this->nextTd("Departure Date:")))
            ->guests($this->nextTd("Number of Adults:"))
            ->kids($this->nextTd("Number of Kids:"))
        ;
        $time = $this->nextTd("Check-In Time:");

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }
        $time = $this->nextTd("Check-Out Time:");

        if (!empty($time) && !empty($h->getCheckOutDate())) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        // Rooms
        $rateText = implode("\n", $this->http->FindNodes("//text()[normalize-space()='Rate:']/ancestor::td[1]/following-sibling::td[normalize-space()][1]//text()[normalize-space()]"));
        $rate = $this->parseRateRange($rateText);
        $type = $this->nextTd("Room Type:");

        if (!empty($type)) {
            $h->addRoom()
                ->setRate($rate, true, true)
                ->setType($type);
        } else {
            $h->addRoom()
                ->setRate($rate, true, true);
        }

        $this->detectDeadLine($h);

        return $email;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return false;
        }

        if (preg_match("#please notify us at least (\d+) hours in advance of the arrival date to avoid a cancellation fee of first night room rate plus tax\.#i", $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative('+' . $m[1] . 'hours', '00:00');

            return true;
        }

        return false;
    }

    private function striposAll($haystack, $needles)
    {
        if (is_string($needles)) {
            return stripos($haystack, $needles);
        }

        if (is_array($needles)) {
            foreach ($needles as $needle) {
                if (stripos($haystack, $needle) !== false) {
                    return true;
                }
            }
        }

        return false;
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
        //		$this->http->log($str);
        $in = [
            //			"#^\s*(\d{1,2}\.\d{1,2}\.\d{4})\s*\(.* (\d+:\d+) Uhr\)\s*$#",//25.11.2018 (Check-in ab 14:00 Uhr)
        ];
        $out = [
            //			"$1 $2",
        ];
        //		$str = preg_replace($in, $out, $str);
        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
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
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextTd($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("//text()[{$rule}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", $root, true, $regexp);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function parseRateRange($string = '')
    {
        if (preg_match_all('/(?:^\s*|\s+)[\d\/]{6,} - (?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d\s]{0,5})/', $string, $rateMatches) // 128.00 GBP from 29/11/2018
            || preg_match_all('/(?:^\s*|\s+)[\d\/]{6,} - (?<currency>[^\d\s]{0,5})[ ]*(?<amount>\d[,.\'\d ]*)/', $string, $rateMatches) // 128.00 GBP from 29/11/2018
            || preg_match_all('/(?:^\s*|\s+)[\d\/]{6,}: (?<currency>[^\d\s]{0,5})[ ]*(?<amount>\d[,.\'\d ]*)/', $string, $rateMatches) // 128.00 GBP from 29/11/2018
        ) {
            if (count(array_unique($rateMatches['currency'])) === 1) {
                $rateMatches['amount'] = array_map(function ($item) {
                    return (float) $this->amount($item);
                }, $rateMatches['amount']);

                $rateMin = min($rateMatches['amount']);
                $rateMax = max($rateMatches['amount']);

                if ($rateMin === $rateMax) {
                    return number_format($rateMatches['amount'][0], 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                } else {
                    return number_format($rateMin, 2, '.', '') . '-' . number_format($rateMax, 2, '.', '') . ' ' . $rateMatches['currency'][0] . ' / night';
                }
            }
        }

        return null;
    }
}
