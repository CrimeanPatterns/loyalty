<?php

namespace AwardWallet\Engine\frosch\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class TravelInineraryPdf extends \TAccountChecker
{
    public $mailFiles = "frosch/it-142194783.eml"; // +frosch/it-84766803.eml

    public $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'Agency Record Locator:' => '',
            'itineraryEnd' => 'IMPORTANT INFORMATION',
        ],
    ];

    private $detectFrom = '@frosch.com';

    private $detectProvider = [
        '@frosch.com',
        'FROSCH Travel Waiver',
    ];
    private $detectBody = [
        'en' => [
            'Please review your itinerary',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
//        if ($this->detectEmailFromProvider($headers['from']) !== true) {
//            return false;
//        }
//
//        foreach ($this->detectSubject as $dSubject) {
//            if (stripos($headers["subject"], $dSubject) !== false) {
//                return true;
//            }
//        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
//            $this->logger->debug('Pdf text = ' . print_r($text, true));
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

    public function parseFlight(Flight $f, $text)
    {
//        $this->logger->debug('$text = ' . print_r($text, true));

        $travellers = array_filter(explode("\n", $this->re("/\n {0,15}" . $this->opt($this->t("Traveler(s)")) . ".*\n(( {0,15}[A-Z][A-Z \-]+ {2,}.*\n+)+)/", $text)));
        $travellers = preg_replace("/^ *(\S.+?) {2,}.*/", '$1', $travellers);

        foreach ($travellers as $traveller) {
            if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
                $f->general()
                    ->traveller($traveller, true);
            }
        }
        $account = $this->re("/\n {0,15}" . $this->opt($this->t("Traveler(s)")) . ".* " . $this->opt($this->t("Frequent Flyer #")) . ".*\n* {0,15}[A-Z \-]+ +.* {2,}([A-Z\d\-\-]{5,})\n/", $text);

        if (!empty($account) && !in_array($account, array_column($f->getAccountNumbers(), 0))) {
            $f->program()
                ->account($account, true);
        }

        // Segment
        $s = $f->addSegment();

        $date = strtotime($this->re("/^\s*(.+)/", $text));

        // Airline
        if (preg_match("/" . $this->opt($this->t("FLIGHT#")) . " *(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5}) {2,}/", $text, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn']);
        }

        if (preg_match("/" . $this->opt($this->t("OPERATED BY:")) . " *\/?(?<operator>.+?)(?: AS .*| DBA .*)?(?: {2,}|\n)/", $text, $m)) {
            $s->airline()
                ->operator(trim($m['operator']));
        }

        if (preg_match("/" . $this->opt($this->t("Confirmation")) . ": *([A-Z\d]{5,7})(?: {2,}|\n)/", $text, $m)) {
            $s->airline()
                ->confirmation(trim($m[1]));
        }

        // Departure
        if (preg_match("/\n *" . $this->opt($this->t("From:")) . " *(?<code>[A-Z]{3}) *- *(?<name>.+) +" . $this->opt($this->t("Depart:")) . " *(?<time>\d{1,2}:\d{2}(?: *[ap]m)?)(?<terminal> {2,}.*\b(?:terminal|term|domestic|AEROGARE)\b.*)?\n/i", $text, $m)) {
            $s->departure()
                ->code($m['code'])
                ->name($m['name'])
                ->date($date ? strtotime($m['time'], $date) : null)
                ->terminal(trim(preg_replace("/\s*(?:terminal|AEROGARE)\s*/i", ' ', $m['terminal'] ?? null)), true, true);
        }

        // Arrival
        if (preg_match("/\n *" . $this->opt($this->t("To:")) . " *(?<code>[A-Z]{3}) *- *(?<name>.+) +" . $this->opt($this->t("Arrive:")) . " *(?<time>\d{1,2}:\d{2}(?: *[ap]m)?)(?<date> +\d{1,2} *\w+)(?<terminal> +.*\b(?:terminal|term|AEROGARE)\b.*)?\n/i", $text, $m)) {
            $date = $date ? strtotime($m['date'], $date) : null;
            $s->arrival()
                ->code($m['code'])
                ->name($m['name'])
                ->date($date ? strtotime($m['time'], $date) : null)
                ->terminal(trim(preg_replace("/\s*(?:terminal|AEROGARE)\s*/i", ' ', $m['terminal'] ?? null)), true, true);
        }

        // Extra
        if (preg_match("/" . $this->opt($this->t("Duration:")) . " *([\w ]+?) {2,}/", $text, $m)) {
            $s->extra()
                ->duration($m[1]);
        }

        if (preg_match("/" . $this->opt($this->t("Stops:")) . " *(\d+)\b/", $text, $m)) {
            $s->extra()
                ->stops($m[1]);
        }

        if (preg_match("/" . $this->opt($this->t("Stops:")) . " *Non[- ]?stop/i", $text, $m)) {
            $s->extra()
                ->stops(0);
        }

        if (preg_match("/" . $this->opt($this->t("Class:")) . " *([A-Z]{1,2}) - (.+)/i", $text, $m)) {
            $s->extra()
                ->cabin($m[2])
                ->bookingCode($m[1])
            ;
        }

        if (preg_match("/" . $this->opt($this->t("MILES:")) . " *(\d+) *\//", $text, $m)) {
            $s->extra()
                ->miles($m[1]);
        }

        if (preg_match("/" . $this->opt($this->t("EQUIPMENT:")) . " *(.+)/", $text, $m)) {
            $s->extra()
                ->aircraft($m[1]);
        }

        if (preg_match("/" . $this->opt($this->t("MEAL:")) . " *(NO *MEAL *SERVICE|(?<meal>.+))/", $text, $m) && !empty($m['meal'])) {
            $s->extra()
                ->meal($m['meal']);
        }

        if (preg_match("/  " . $this->opt($this->t("Status:")) . " *(.+?) - /", $text, $m)) {
            $s->extra()
                ->status($m[1]);
        }

        if (preg_match_all("/\n+\s+[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\s+(\d{1,3}[A-Z])\s+/", $text, $m)) {
            $s->extra()
                ->seats($m[1]);
        }
    }

    public function parseHotel(Email $email, $text)
    {
//        $this->logger->debug('$text = ' . print_r($text, true));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/" . $this->opt($this->t("Confirmation")) . ": *([A-Z\d]{5,})(?: {2,}|\n)/", $text))
            ->status($this->re("/  " . $this->opt($this->t("Status:")) . " *(.+?) - /", $text))
        ;

        // Hotel
        $h->hotel()
            ->name($this->re("/\n *(.+?) {2,}" . $this->opt($this->t("Status:")) . "/", $text))
            ->address($this->re("/\n *" . $this->opt($this->t("Address:")) . " *(.+?) {2,}/", $text))
            ->phone($this->re("/\s+" . $this->opt($this->t("Phone:")) . " *(.+)/", $text))
            ->fax($this->re("/\s+" . $this->opt($this->t("Fax:")) . " *(.+)/", $text))
        ;

        // Booked
        $h->booked()
            ->checkIn(strtotime($this->re("/\s+" . $this->opt($this->t("Check-In:")) . " *(.+?) {2,}/", $text)))
            ->checkOut(strtotime($this->re("/\s+" . $this->opt($this->t("Check-Out:")) . " *(.+?) {2,}/", $text)))
            ->rooms($this->re("/\s+" . $this->opt($this->t("NUMBER OF ROOMS:")) . " *(\d+)\b/", $text))
        ;

        // Program
        $account = $this->re("/\n *(.+?) {2,}" . $this->opt($this->t("HOTEL MEMBERSHIP:")) . " ([A-Z\d]{5,})\n/", $text);

        if (!empty($account)) {
            $h->program()
                ->account($account, false);
        }
    }

    public function parseRental(Email $email, $text)
    {
//        $this->logger->debug('$text = ' . print_r($text, true));

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->re("/" . $this->opt($this->t("Confirmation")) . ": *([A-Z\d]{5,})(?: {2,}|\n)/", $text))
            ->status($this->re("/  " . $this->opt($this->t("Status:")) . " *(.+?) - /", $text))
        ;

        // Pick Up
        $r->pickup()
            ->location($this->re("/\n *" . $this->opt($this->t("Pick-up:")) . " *(.+?) {2,}/", $text)
                . '' . $this->re("/\n *" . $this->opt($this->t("Address:")) . " *(.+?)(?: {2,}|\n)/", $text))
            ->phone($this->re("/\n *" . $this->opt($this->t("Pick-up:")) . ".* " . $this->opt($this->t("Phone:")) . " *(.+)/", $text))
            ->date(strtotime($this->re("/\n *" . $this->opt($this->t("Pick-up:")) . " *\S.+? {2,}(.+?) +" . $this->opt($this->t("Phone:")) . "/", $text)))
        ;

        // Drop Off
        $r->dropoff()
            ->location($this->re("/\n *" . $this->opt($this->t("Drop-off:")) . " *(.+?) {2,}/", $text)
                . '' . $this->re("/\n *" . $this->opt($this->t("Address:")) . " *(.+?)(?: {2,}|\n)/", $text))
            ->phone($this->re("/\n *" . $this->opt($this->t("Drop-off:")) . ".* " . $this->opt($this->t("Phone:")) . " *(.+)/", $text))
            ->date(strtotime($this->re("/\n *" . $this->opt($this->t("Drop-off:")) . " *\S.+? {2,}(.+?) +" . $this->opt($this->t("Phone:")) . "/", $text)))
        ;

        // Car
        $r->car()
            ->type($this->re("/\n *" . $this->opt($this->t("TYPE:")) . " *(.+)/", $text));
        // Program
        $account = $this->re("/\n *(.+?) {2,}" . $this->opt($this->t("CAR MEMBERSHIP NBR:")) . " ([A-Z\d]{5,})\n/", $text);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, $this->detectProvider) === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) !== false) {
                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
//        $this->logger->debug('Pdf text = ' . print_r($textPdf, true));

        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/\n\s*" . $this->opt($this->t("Agency Record Locator:")) . " *([A-Z\d]{5,7})\s+/", $textPdf));

        $travellers = array_filter(explode("\n",
            $this->re("/\n\s*" . $this->opt($this->t("Agency Record Locator:")) . ".+\s*\n([A-Z]+(?:[- ][A-Z]+){1,3}\n+)+/", $textPdf)));

        $itineraryStart = $this->strposAll($textPdf, $this->t('Agency Record Locator:'), 'min');

        if (empty($itineraryStart)) {
            $itineraryStart = 0;
        }
        $itineraryEnd = $this->strposAll($textPdf, $this->t('itineraryEnd'), 'min');
        $itineraryText = $textPdf;

        if (!empty($itineraryStart)) {
            $itineraryText = substr($itineraryText, $itineraryStart);
        }

        if (!empty($itineraryEnd)) {
            $itineraryText = substr($itineraryText, 0, $itineraryEnd - $itineraryStart);
        }

        $segments = $this->split("/\n {0,5}([[:alpha:]]+ \d{2} [[:alpha:]]+ 20\d{2}\n)/", $itineraryText);
//        $this->logger->debug('$segments = ' . print_r($segments, true));
        foreach ($segments as $sText) {
            // flight
            if ($this->containsText($sText, $this->t("Airline Confirmation")) === true) {
                if (!isset($flight)) {
                    $flight = $email->add()->flight();

                    $flight->general()->noConfirmation();
                }
                $this->parseFlight($flight, $sText);

                continue;
            }
            // hotel
            if ($this->containsText($sText, $this->t("Hotel Confirmation")) === true) {
                $this->parseHotel($email, $sText);

                continue;
            }
            // rental
            if ($this->containsText($sText, $this->t("Car Confirmation")) === true) {
                $this->parseRental($email, $sText);

                continue;
            }
            $email->add()->flight();
            $this->logger->debug("unknown type" . $sText);
        }

        foreach ($email->getItineraries() as $it) {
            if (!empty($it->getType() !== 'flight') && empty($it->getTravellers())) {
                $it->general()->travellers($travellers);
            }
        }

        if (isset($flight)) {
            if (preg_match_all("/\n\s*" . $this->opt($this->t("Ticket Number:")) . " *[A-Z\d]{2}-(\d{13})/", $textPdf, $m)) {
                $flight->issued()
                    ->tickets($m[1], false);
            }

            if (preg_match("/\n\s*" . $this->opt($this->t("Total Amount:")) . " *(\d[\d,. ]*)\n/", $textPdf, $m)) {
                // no use without currency, only for Kira's peace of mind
                $flight->price()
                    ->total(PriceHelper::parse($m[1]));
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    // additional methods

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
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

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $text
     * @param $needle
     * @param string $function 'first' - first finded, 'min' - min value of all needles, 'max' - max value of all needles
     *
     * @return bool
     */
    private function strposAll($text, $needle, $function = 'first')
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            $result = [];

            foreach ($needle as $n) {
                $v = strpos($text, $n);

                if ($function == 'first' && $v !== false) {
                    return $v;
                }

                if ($v !== false) {
                    $result[] = $v;
                }
            }
            $result = array_filter($result);

            if (empty($result)) {
                return false;
            }

            if ($function == 'min') {
                return min($result);
            }

            if ($function == 'max') {
                return max($result);
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
        }

        return false;
    }
}
