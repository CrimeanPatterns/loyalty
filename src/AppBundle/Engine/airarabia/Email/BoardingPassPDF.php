<?php

namespace AwardWallet\Engine\airarabia\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPDF extends \TAccountChecker
{
    public $mailFiles = "airarabia/it-706550795.eml, airarabia/it-707525312.eml";
    public $lang = 'en';
    public $pdfNamePattern = "Boarding\s*pass.*pdf";

    public $subjects = [
        'Air Arabia boarding pass confirmation',
    ];

    public $pdfFileName;
    public $currentFlight;
    public $currentSegment;
    public $flightArray = [];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airarabia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Air Arabia')]")
                && strpos($text, "Flight Day Timings") !== false
                && (strpos($text, 'Departure') !== false)
                && (strpos($text, 'Gate closes') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airarabia\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = '';

        $text = preg_replace("/^(\D+Flight Day Timings)/", "", $text);
        $flightText = $this->splitCols($text, [0, 50]);
        $flightInfo = $this->re("/^((?:.*\n){1,10})\n+\s*Date/", $flightText[1]);
        $flightTable = $this->splitCols($flightInfo, [0, 40, 60]);
        $segConf = $this->re("/\-([A-Z\d]{6})\.pdf/", $this->pdfFileName);

        $flight = $this->re("/\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,4})\s+/", $text);

        if (in_array($flight, $this->flightArray) === false) {
            $f = $this->currentFlight = $email->add()->flight();

            $conf = $this->re("/Reservation number\n\s+(\d{5,})\n/", $text);
            $f->general()
                ->confirmation($conf);

            $traveller = $this->re("/Passenger\n\s*(.+)/", $flightText[1]);

            if (!empty($traveller)) {
                $f->general()
                    ->traveller($traveller);
            }

            $ticket = $this->re("/E-ticket\n\s*(\d{10,})\n/", $flightText[1]);

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $traveller);
            }

            $s = $this->currentSegment = $f->addSegment();

            if (preg_match("/Date\s+Departure\s*Seat\n\s*(?<year>\d{4})\-(?<month>\d+)\-(?<day>\d+)\s*(?<time>\d+\:\d+)\s*(?<seat>\d+[A-Z])/", $flightText[1], $m)) {
                $s->departure()
                    ->date(strtotime($m['day'] . '.' . $m['month'] . '.' . $m['year'] . ', ' . $m['time']));

                $s->addSeat($m['seat'], false, false, $traveller);
            }

            if (preg_match("/^\s*(?<depName>.+)\b\s*(?<depCode>[A-Z]{3})/su", $flightTable[0], $m)) {
                $s->departure()
                    ->name(preg_replace("/\s+\n+\s+/", " ", $m['depName']))
                    ->code($m['depCode']);
            }

            if (preg_match("/\s*(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s*/", $flightTable[1], $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                $this->flightArray[] = $m['aName'] . $m['fNumber'];
            }

            if (preg_match("/^\s*(?<arrName>.+)\b\s*(?<arrCode>[A-Z]{3})/su", $flightTable[2], $m)) {
                $s->arrival()
                    ->name(preg_replace("/\s+\n+\s+/", " ", $m['arrName']))
                    ->code($m['arrCode'])
                    ->noDate();
            }
            $s->setConfirmation($segConf);
        } else {
            $f = $this->currentFlight;
            $traveller = $this->re("/Passenger\n\s*(.+)/", $flightText[1]);

            if (!empty($traveller)) {
                $f->general()
                    ->traveller($traveller);
            }

            $ticket = $this->re("/E-ticket\n\s*(\d{10,})\n/", $flightText[1]);

            if (!empty($ticket)) {
                $f->addTicketNumber($ticket, false, $traveller);
            }

            $s = $this->currentSegment;

            if (preg_match("/Date\s+Departure\s*Seat\n\s*\d{4}\-\d+\-\d+\s*\d+\:\d+\s*(?<seat>\d+[A-Z])/", $flightText[1], $m)) {
                $s->addSeat($m['seat'], false, false, $traveller);
            }

            $s->setConfirmation($segConf);
        }

        $b = $email->add()->bpass();
        $b->setFlightNumber($s->getAirlineName() . $s->getFlightNumber());
        $b->setTraveller($traveller);
        $b->setDepDate($s->getDepDate());
        $b->setDepCode($s->getDepCode());
        $b->setRecordLocator($segConf);
        $b->setAttachmentName($this->pdfFileName);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->pdfFileName = $this->getAttachmentName($parser, $pdf);

            $bPassArray = $this->splitText($text, "/^(\D+Flight Day Timings\n)/mu", true);

            foreach ($bPassArray as $bPass) {
                $this->ParseFlightPDF($email, $bPass);
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
        return count(self::$dictionary);
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
                $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
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

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
