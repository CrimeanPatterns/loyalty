<?php

namespace AwardWallet\Engine\airtransat\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class EDocumentsPDF extends \TAccountChecker
{
    public $mailFiles = "airtransat/it-30533197.eml";

    public $reFrom = ["airtransat.com"];
    public $reBody = [
        'en' => ['AGENCY', 'You have booked'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [],
    ];
    private $keywordProv = 'airtransat.com';
    private $otaConfNos = [];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    }
                    $this->parseEmailPdf($text, $email);
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((stripos($text, $this->keywordProv) !== false)
                && $this->assignLang($text)
            ) {
                return true;
            }
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
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 2; // flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        if (strpos($textPDF, $this->t('Issue Date')) === false || strpos($textPDF, $this->t('Booking')) === false) {
            $this->logger->debug("other format attachment");

            return false;
        }
        $reservations = $this->splitter("/^([ ]*{$this->t('Issue Date')}[ :].+? {$this->t('Booking')}[ :]+\d+)/m",
            "controlStr\n" . $textPDF);

        foreach ($reservations as $reservation) {
            if (preg_match("/{$this->opt($this->t('CARRIER'))}[ ]+{$this->opt($this->t('FLIGHT'))}/",
                    $reservation) > 0
            ) {
                if (!$this->parseFlight($reservation, $email)) {
                    return false;
                }
            } elseif (preg_match("/{$this->opt($this->t('HOTEL'))}[ ]+{$this->opt($this->t('CATEGORY'))}/",
                    $reservation) > 0
            ) {
                if (!$this->parseHotel($reservation, $email)) {
                    return false;
                }
            } else {
                $this->logger->debug("other format reservation");

                return false;
            }
        }

        return true;
    }

    private function parseFlight($textPDF, Email $email)
    {
        $r = $email->add()->flight();

        if (!empty($str = strstr($textPDF, $this->t('INFORMATION'), true))) {
            $textPDF = $str;
        }
        $textInfo = strstr($textPDF, $this->t('ITINERARY'), true);
        $textInfo = strstr($textInfo, $this->t('GUEST'));

        if (empty($textInfo)) {
            $this->logger->debug("other format Flight");

            return false;
        }

        $tableInfo = $this->splitCols($textInfo, $this->colsPos($this->re("/(.+)/", $textInfo)));

        if (count($tableInfo) !== 2) {
            $this->logger->debug("other format Flight: info block");

            return false;
        }
        $pax = array_filter(array_map("trim",
            explode("\n", $this->re("/{$this->opt($this->t('GUEST'))}\s+(.+)/s", $tableInfo[0]))));
        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation'))}[\# ]+([A-Z\d]{5,6})/", $textPDF))
            ->travellers($pax)
            ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Issue Date'))}[ :]+(.+?)[ ]+{$this->opt($this->t('Booking'))}/",
                $textPDF)));

        $tripNum = $this->re("/{$this->opt($this->t('Issue Date'))}[ :]+.+?[ ]+{$this->opt($this->t('Booking'))}[ :]+(\d+)/",
            $textPDF);

        if (!in_array($tripNum, $this->otaConfNos)) {
            $email->ota()->confirmation($tripNum, $this->t('Booking'));
            $this->otaConfNos[] = $tripNum;
        }

        $segments = $this->splitter("/^([ ]*{$this->opt($this->t('FROM'))}[ ]+{$this->opt($this->t('TERMINAL'))})/m",
            $textPDF);

        foreach ($segments as $segment) {
            $s = $r->addSegment();
            $tableSeg = $this->splitCols($segment, $this->colsPos($segment));

            if (count($tableSeg) !== 8) {
                $this->logger->debug("other format Flight: segment");
                $this->logger->debug($segment);

                return false;
            }

            if (preg_match("/{$this->opt($this->t('FROM'))}\s+(.+)\s+{$this->opt($this->t('TO'))}\s+(.+?)\n\n/s",
                $tableSeg[0], $m)) {
                $s->departure()
                    ->noCode()
                    ->name($this->nice($m[1]));
                $s->arrival()
                    ->noCode()
                    ->name($this->nice($m[2]));
            }

            if (preg_match("/{$this->opt($this->t('TERMINAL'))}\s+(.*?)\s*{$this->opt($this->t('SEAT'))}\s+(.*?)\n\n/s",
                $tableSeg[1], $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $s->departure()
                        ->terminal($m[1]);
                }

                if (isset($m[2]) && !empty($m[2]) && preg_match("/^\d+[A-Z]$/", trim($m[2]))) {
                    $s->extra()->seat($m[2]);
                }
            }

            if (preg_match("/{$this->opt($this->t('VIA'))}\s+(.*?)\s*{$this->opt($this->t('CLASS'))}\s+(.*?)\n\n/s",
                $tableSeg[2], $m)) {
                if (isset($m[1]) && !empty($m[1])) {
                    $s->airline()
                        ->operator($m[1]);
                }

                if (isset($m[2]) && !empty($m[2]) && preg_match("/^[A-Z]{1,2}$/", trim($m[2]))) {
                    $s->extra()->bookingCode($m[2]);
                }
            }

            if (preg_match("/{$this->opt($this->t('FLIGHT'))}\s+([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)\s*{$this->opt($this->t('BAGGAGE'))}/",
                $tableSeg[4], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }
            $date = $this->normalizeDate($this->re("/{$this->opt($this->t('DATE'))}\s+(.+)/", $tableSeg[5]));
            $depTime = $this->re("/{$this->opt($this->t('DEP'))}\s+(.+)/", $tableSeg[6]);
            $arrTime = $this->re("/{$this->opt($this->t('ARR'))}\s+(.+)/", $tableSeg[7]);
            $s->departure()->date(strtotime($depTime, $date));
            $s->arrival()->date(strtotime($arrTime, $date));
        }

        return true;
    }

    private function parseHotel($textPDF, Email $email)
    {
        $r = $email->add()->hotel();

        if (!empty($str = strstr($textPDF, $this->t('INFORMATION'), true))) {
            $textPDF = $str;
        }
        $textInfo = strstr($textPDF, $this->t('ACCOMMODATION'), true);
        $textInfo = strstr($textInfo, $this->t('NAME'));

        if (empty($textInfo)) {
            $this->logger->debug("other format Hotel (1)");

            return false;
        }
        $tableInfo = $this->splitCols($textInfo, $this->colsPos($this->re("/(.+)/", $textInfo)));

        if (count($tableInfo) !== 2) {
            $this->logger->debug("other format Hotel: info block");

            return false;
        }

        $textHotel = strstr($textPDF, $this->t('CHECK IN'), true);
        $textHotel = $this->re("/^([ ]*{$this->opt($this->t('DESTINATION'))}.+)/ms", $textHotel);

        if (empty($textHotel)) {
            $this->logger->debug("other format Hotel (2)");

            return false;
        }
        $tableHotel = $this->splitCols($textHotel, $this->colsPos($this->re("/(.+)/", $textHotel)));

        if (count($tableHotel) !== 4) {
            $this->logger->debug("other format Hotel: hotel block");

            return false;
        }

        $textBooking = strstr($textPDF, $this->t('ADDRESS'), true);
        $textBooking = $this->re("/^([ ]*{$this->opt($this->t('CHECK IN'))}.+)/ms", $textBooking);

        if (empty($textBooking)) {
            $this->logger->debug("other format Hotel (3)");

            return false;
        }
        $tableBooking = $this->splitCols($textBooking, $this->colsPos($this->re("/(.+)/", $textBooking)));

        if (count($tableBooking) !== 4) {
            $this->logger->debug("other format Hotel: booking block");

            return false;
        }

        $textAddress = $this->re("/^([ ]*{$this->opt($this->t('ADDRESS'))}.+)/ms", $textPDF);

        if (empty($textAddress)) {
            $this->logger->debug("other format Hotel (4)");

            return false;
        }
        $tableAddress = $this->splitCols($textAddress, $this->colsPos($this->re("/(.+)/", $textAddress)));

        if (count($tableAddress) !== 2) {
            $this->logger->debug("other format Hotel: address block");

            return false;
        }

        $r->hotel()
            ->name($this->nice($this->re("/^\s*{$this->opt($this->t('HOTEL'))}\s+(.+)/s", $tableHotel[1])));

        if (preg_match("/{$this->opt($this->t('ADDRESS'))}\s+(.+?)\s*(?:{$this->opt($this->t('Telephone'))}[ :]+([^\n]+))?$/su",
            trim($tableAddress[0]), $m)) {
            $r->hotel()
                ->address($this->nice($m[1]));

            if (isset($m[2]) && !empty($m[2])) {
                $r->hotel()->phone($m[2]);
            }
        }
        $r->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('CHECK IN'))}\s+(.+)/", $tableBooking[0])))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('CHECK OUT'))}\s+(.+)/",
                $tableBooking[1])));

        $room = $r->addRoom();
        $room
            ->setType($this->nice($this->re("/^\s*{$this->opt($this->t('CATEGORY'))}\s+(.+)/s", $tableHotel[2])))
            ->setDescription($this->nice($this->re("/^\s*{$this->opt($this->t('OCCUPANCY'))}\s+(.+)/s",
                $tableBooking[3])));

        $confNo = $this->nice($this->re("/^\s*{$this->opt($this->t('Confirmation'))}[ \#]*\s*(.*)/s", $tableHotel[3]));

        if (empty($confNo)) {
            $r->general()
                ->noConfirmation();
        } else {
            $r->general()->confirmation($confNo);
        }

        $pax = array_filter(array_map("trim",
            explode("\n", $this->re("/{$this->opt($this->t('GUEST'))}\s+(.+)/s", $tableInfo[0]))));
        $r->general()
            ->travellers($pax)
            ->date($this->normalizeDate($this->re("/{$this->opt($this->t('Issue Date'))}[ :]+(.+?)[ ]+{$this->opt($this->t('Booking'))}/",
                $textPDF)));

        $tripNum = $this->re("/{$this->opt($this->t('Issue Date'))}[ :]+.+?[ ]+{$this->opt($this->t('Booking'))}[ :]+(\d+)/",
            $textPDF);

        if (!in_array($tripNum, $this->otaConfNos)) {
            $email->ota()->confirmation($tripNum, $this->t('Booking'));
            $this->otaConfNos[] = $tripNum;
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            //Mon Dec 10 9:1:27 2018
            '#^[\-\w]+\s+(\w+)\s+(\d+)\s+.+?\s+(\d{4})$#u',
            //15-JAN-2019
            '#^(\d+)\-(\w+)\-(\d{4})$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
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
                if (strpos($body, $reBody[0]) !== false && strpos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

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

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice($str)
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }
}
