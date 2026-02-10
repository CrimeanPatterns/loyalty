<?php

namespace AwardWallet\Engine\airmiles\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelDocuments extends \TAccountChecker
{
    public $mailFiles = "airmiles/it-266545617.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $date;
    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'Confirmation' => 'Confirmation',
        ],
    ];

    private $detectFrom = "aviosteam@avios.com";
    private $detectSubject = [
        // en
        'Your travel documentation for Avios booking ref:',
    ];
    private $detectBody = [
        'en' => [
            'Your Avios e-ticket',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'Avios') === false
        ) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

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
        // TODO check count types
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['aerlingus'];
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, ['@avios.com']) === false) {
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
        $this->date = null;

        $parts = $this->split("/\n *((?:Your booking summary|Your Avios e-ticket|Before you go)\n)/", $textPdf, false);

        foreach ($parts as $i => $pText) {
            if ($i == 0) {
                $this->date = $this->normalizeDate($this->re("/\n *(\S.+)\s*\n *Dear /", $pText));
            }

            if (strpos($pText, 'Your Avios e-ticket') === 0) {
                $f = $this->parseItinerary($email, $pText);
            }

            if (strpos($pText, 'Your booking summary') === 0) {
                if (preg_match("/\n *Product +Details +Avios +(.{1,5})\n/", $pText, $mc)
                    && preg_match("/\n *Received {3,}((?:\S ?)+) +((?:\S ?)+)\n/", $pText, $mt)
                ) {
                    $currency = $mc[1];
                    $total = PriceHelper::parse($mt[2], $mc[1]);
                    $awards = $mt[1];
                }
            }
        }

        if (isset($currency) && isset($total) || isset($awards)) {
            if (count($email->getItineraries()) === 1 && isset($f)) {
                $price = $f->price();
            } else {
                $price = $email->price();
            }

            if (isset($currency)) {
                $price->currency($currency);
            }

            if (isset($total)) {
                $price->total($total);
            }

            if (isset($awards)) {
                $price->spentAwards($awards);
            }
        }

        return $email;
    }

    private function parseItinerary(Email $email, ?string $text = null)
    {
        // $email->obtainTravelAgency();

        $f = $email->add()->flight();

        $providerAirline = null;
        $headers = $this->re("/\n( *Airline +Flight no +From.*)/", $text);
        $pos = $this->rowColumnPositions($headers);
        $headerTable = $this->createTable($headers, $pos);
        $flightsText = $this->re("/\n *Airline +Flight no +From.*\s*\n([\s\S]+?)\n *Passengers\n/", $text);
        $flightsText = preg_replace("/\n *Return\n/", "\n", $flightsText);
        $flights = $this->split("/\n(.* \d{2}:\d{2} .*\n.+)/", "\n" . $flightsText);

        foreach ($flights as $ftext) {
            $table = $this->createTable($ftext, $pos);
//            $this->logger->debug('$table = ' . print_r($table, true));

            $s = $f->addSegment();

            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5})\s*$/", $table[1] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if ($providerAirline === null || $providerAirline === $m['al']) {
                    $providerAirline = $m['al'];
                } else {
                    $providerAirline = '';
                }
            } else {
                $providerAirline = '';
            }

            if (preg_match("/^\s*From\s*$/", $headerTable[2] ?? '')) {
                $s->departure()
                    ->noCode()
                    ->name(preg_replace("/\s*\n\s*/", '', trim($table[2] ?? '')))
                    ->terminal(preg_replace("/\s*\n\s*/", '', trim($table[3] ?? '')), true, true);
            }

            if (preg_match("/^\s*To\s*$/", $headerTable[4] ?? '')) {
                $s->arrival()
                    ->noCode()
                    ->name(preg_replace("/\s*\n\s*/", '', trim($table[4] ?? '')));
            }

            if (preg_match("/^\s*(?<dt>\d{2}:\d{2}(?: *[ap]m)?) *(?<dd>.+)\n\s*(?<at>\d{2}:\d{2}(?: *[ap]m)?) *(?<ad>.+)\s*$/i",
                $table[5] ?? '', $m)) {
                $date = $this->normalizeDate($m['dd']);

                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($m['dt'], $date));
                }
                $date = $this->normalizeDate($m['ad']);

                if (!empty($date)) {
                    $s->arrival()
                        ->date(strtotime($m['at'], $date));
                }
            }

            if (preg_match("/^\s*Class\s*$/", $headerTable[6] ?? '')) {
                $s->extra()
                    ->cabin(preg_replace("/\s*\n\s*/", '', trim($table[6] ?? '')));
            }
        }

        if (preg_match("/\n *Airline Check-In {3,}Airline Booking {3,}Avios Booking\s*\n *Reference: ?([A-Z\d]{5,7}) {3,}Reference: ?([A-Z\d]{5,7}) {3,}Reference: ?([A-Z\d ]{5,})\n/",
            $text, $m)) {
            if ($providerAirline === 'EI') {
                $email->setProviderCode('aerlingus');
            }

            $f->ota()
                ->code('airmiles')
                ->confirmation(str_replace(' ', '', $m[3]));

            $f->general()
                ->confirmation($m[1], 'Airline Check-In Reference')
                ->confirmation($m[2], 'Airline Booking Reference')
            ;
        }

        $passengerText = $this->re("/\n *Passengers(\n[\s\S]+?)\n *CANCELLATION AND REFUNDS/", $text);

        if (preg_match_all("/\n *([A-Z][A-Z\W]+?) {3,}(\d{3}-?\d{10}) {3}/", $passengerText, $m)) {
            $f->general()
                ->travellers(preg_replace("/^\s*(?:MRS|MS|MR|MSTR|MISS)\s+/", '', $m[1]), true);

            $f->issued()
                ->tickets($m[2], false);
        }

        return $f;
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
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

    private function split($re, $text, $deleteFirst = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if ($deleteFirst) {
                array_shift($r);
            } else {
                $ret[] = array_shift($r);
            }

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
        $year = $this->date ? date("Y", $this->date) : '';
        $in = [
            // Sun Mar 26
            '/^\s*([[:alpha:]]+)\s+([[:alpha:]]+)\s+(\d{1,2})\s*$/u',
            // Sun 26 Feb
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})\s+([[:alpha:]]+)\s*$/u',
        ];
        $out = [
            '$1, $3 $2 ' . $year,
            '$1, $2 $3 ' . $year,
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
