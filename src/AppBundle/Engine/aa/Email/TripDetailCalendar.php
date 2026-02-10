<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TripDetailCalendar extends \TAccountChecker
{
    public $mailFiles = "aa/it-96277786.eml, aa/it-97332172.eml";

    private $detectSubjects = [
        // en
        ' trip details', // Matthew Hersh 13/02/2021 trip details
    ];

    private $lang = '';

    private static $dictionary = [
        'en' => [],
    ];

    public function detectEmailFromProvider($from)
    {
        $emails = ['american.airlines@info.email.aa.com', 'americanairlines@aa.com'];

        foreach ($emails as $email) {
            if (stripos($from, $email) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $detectSubjects) {
            if (stripos($headers['subject'], $detectSubjects) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ((
                preg_match("/(^|\n)\s*[[:alpha:] \-]+ \d{1,2}\/\d{1,2}\/\d{4} trip details\s*$/", $parser->getBody())
                || empty(trim($parser->getBody()))
            )
            && stripos($parser->getBodyStr(), 'filename="calendar.ics"') !== false
        ) {
            return true;
        }

        return false;
    }

    public function parseFlight(Email $email, string $text)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("/Record Locator: ([A-Z\d]{5,7})\s*\n/", $text))
            ->travellers(array_unique($this->res("/Traveler Information: *(.+?) - /", $text)), true)
        ;

        // Segments
        $segments = $this->split("/\n *((?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(?: .+)?\s*\n\s*Flight \d{1,5}\s*\n)/", $text);
//        $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $stext) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($this->re("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+/", $stext))
                ->number($this->re("/\n\s*Flight (\d{1,5})\s*\n/", $stext))
                ->operator($this->re("/OPERATED BY (.+)/", $stext), true, true)
            ;

            // Departure
            $s->departure()
                ->code($this->re("/\n *Depart: ([A-Z]{3}) -/", $stext))
                ->name($this->re("/\n *Depart: [A-Z]{3} - (.+?) on /", $stext))
                ->date($this->normalizeDate($this->re("/\n *Depart: [A-Z]{3} - .+? on (.+)/", $stext)))
            ;

            // Arrival
            $s->arrival()
                ->code($this->re("/\n *Arrive: ([A-Z]{3}) -/", $stext))
                ->name($this->re("/\n *Arrive: [A-Z]{3} - (.+?) on /", $stext))
                ->date($this->normalizeDate($this->re("/\n *Arrive: [A-Z]{3} - .+? on (.+)/", $stext)))
            ;

            // Booked
            $s->extra()
                ->bookingCode($this->re("/\n *Booking Code: ([A-Z]{1,2})\s*\n/", $stext));
            $cabin = array_unique($this->res("/Traveler Information: *.+? - (.+) -/", $stext));

            if (count($cabin) == 1) {
                $s->extra()->cabin(array_shift($cabin));
            }
            $seats = array_unique($this->res("/Traveler Information: *.+? - .+ - (\d{1,3}[A-Z])\s*(?:\n|$)/", $stext));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
            $meals = $this->res("/Meals Offered: *(.+)/", $stext);

            if (!empty($meals)) {
                $s->extra()->meals($meals);
            }
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $calendar = '';
        $text = $parser->getBodyStr();
//        $this->logger->debug('$text = '.print_r( $parser->getBodyStr(),true));
        $text = str_replace("\r", '', $text);
        $text = preg_replace("#^(--\w{25,32}(--)?)$#m", "\n", $text);
        $posBegin1 = stripos($text, "Content-Type: text/calendar");
        $text = substr($text, $posBegin1);
        $text = substr($text, stripos($text, "\n\n") + 2);
        $posEnd = stripos($text, "\n\n");

        if (!empty($posEnd)) {
            $text = substr($text, 0, $posEnd);
        }
        $calendar = $text;

        if (stripos($calendar, 'Record Locator:') === false) {
            $calendar = base64_decode($calendar);
        }

        if (!empty($calendar)) {
            $calendar = str_replace('\n', '', $calendar);
            $this->parseFlight($email, $calendar);
        }

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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));

        $in = [
            // Wed 1 Sep 2021 at 06:20AM
            "/^\s*\w+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4}) at (\d+:\d+(?: ?[AP]M)?)\s*$/iu",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
