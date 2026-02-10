<?php

namespace AwardWallet\Engine\aircaraibes\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BoardingPassPdf2 extends \TAccountChecker
{
    public $mailFiles = "aircaraibes/it-76020404.eml";
    public static $dict = [
        'fr' => [],
    ];

    private $detectCompany = [
        'www.aircaraibes.com',
    ];
    private $detectBody = [
        'fr' => ['BOARDING PASS'],
    ];
    private $pdfPattern = '.*\.pdf';
    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }
            $foundCompany = false;

            foreach ($this->detectCompany as $detectCompany) {
                if (stripos($text, $detectCompany) !== false) {
                    $foundCompany = true;

                    break;
                }
            }

            if ($foundCompany === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        $this->parseEmail($email, $text);

                        break 2;
                    }
                }
            }

//            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));
//
//            foreach ($this->detectBody as $lang => $detectBody) {
//                foreach ($detectBody as $dBody) {
//                    if (stripos($text, $dBody) !== false) {
//                        $this->lang = $lang;
//                        break 2;
//                    }
//                }
//            }

//            $this->parseEmail($email, $text);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($text)) {
                continue;
            }
            $foundCompany = false;

            foreach ($this->detectCompany as $detectCompany) {
                if (stripos($text, $detectCompany) !== false) {
                    $foundCompany = true;

                    break;
                }
            }

            if ($foundCompany === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($text, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
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

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function parseEmail(Email $email, string $text)
    {
        foreach ($email->getItineraries() as $value) {
            if ($value->getType() == 'flight') {
                /** @var Flight $flight */
                $f = $value;

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();
            $f->general()->noConfirmation();
        }

        $regexp = "/\n(BOARDING PASS\s*\n)/";
        $segments = $this->splitText($regexp, $text);

        foreach ($segments as $segment) {
//            $this->logger->debug('$segment = '.print_r( $segment,true));

            $s = $f->addSegment();

            // Airline
            if (preg_match("/(?:[ ]{3,}|\/ *)BOOKING(?:[ ]{3,}.*|\n)\n.*[ ]{3,}([A-Z\d]{5,7})\s+/", $segment, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            $flight = $this->re("/\n((?: *|.*\/ *)FLIGHT[ ]{2,}.*\bFROM\b.*[\s\S]+?)\n\s*" . $this->preg_implode($this->t("BAGAGE(S) EN SOUTE")) . "/u", $segment);
            $table = $this->SplitCols($flight, $this->TableHeadPos($this->inOneRow($flight)));
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) !== 4) {
                $this->logger->debug('error parse table');
            }

            // Airline
            if (preg_match("/\FLIGHT\n\s*([A-Z\d]{2}) ?(\d{1,5})\n/", $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            // Departure
            if (preg_match("/\bFROM\n\s*(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\n(?<date>.*\d{1,2}.*\n\s*\d{1,2}:\d{1,2}.*)(?<terminal>\n[\s\S]*)?$/", $table[1], $m)) {
                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->date($this->normalizeDate($m['date']))
                ;

                if (isset($m['terminal']) && !empty(trim($m['terminal']))) {
                    $s->departure()->terminal(preg_replace("/\s+/", ' ', trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', trim($m['terminal'])))));
                }
            }

            // Arrival
            if (preg_match("/\bTO\n\s*(?<code>[A-Z]{3})\s+(?<name>[\s\S]+?)\n(?<date>.*\d{1,2}.*\n\s*\d{1,2}:\d{1,2}.*)(?<terminal>\n[\s\S]*)?$/", $table[3], $m)) {
                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->date($this->normalizeDate($m['date']))
                ;

                if (isset($m['terminal']) && !empty(trim($m['terminal']))) {
                    $s->arrival()->terminal(preg_replace("/\s+/", ' ', trim(preg_replace("/\s*\bterminal\b\s*/i", ' ', trim($m['terminal'])))));
                }
            }

            // Extra
            if (preg_match("/\bCABIN\n\s*([A-Z]{1,2})(?:\n|$)/u", $table[2], $m)) {
                $s->extra()->bookingCode($m[1]);
            }

            if (preg_match("/\bSEAT\n\s*(\d{1,3}[A-Z])\s*\n/u", $table[2], $m)) {
                $s->extra()->seat($m[1]);
            }

            if (preg_match("/^\s*BOARDING PASS.*\n(?:(?:[ ]{15,}.*|\s*)\n)*[ ]{0,15}([A-Z][A-Za-z\. \/]+?)(?:[ ]{2,}.*)?\n/", $segment, $m)) {
                if (!in_array($m[1], array_column($f->getTravellers(), 0))) {
                    $f->general()->traveller($m[1]);
                }
            }

            if (preg_match("/\n\s*ETKT(?: {3,}.*|\n)\n[ ]*(\d{10,})/", $segment, $m)) {
                if (!in_array($m[1], array_column($f->getTicketNumbers(), 0))) {
                    $f->issued()->ticket($m[1], false);
                }
            }

            foreach ($f->getSegments() as $key => $seg) {
                if ($seg === $s) {
                    continue;
                }

                if ($s->getAirlineName() == $seg->getAirlineName()
                    && $s->getFlightNumber() == $seg->getFlightNumber()
                    && $s->getDepCode() == $seg->getDepCode()
                    && $s->getDepDate() == $seg->getDepDate()) {
                    if (!empty($s->getSeats())) {
                        $seg->extra()->seats(array_unique(array_merge($seg->getSeats(), $s->getSeats())));
                    }
                    $f->removeSegment($s);

                    break;
                }
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
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

    private function TableHeadPos($row)
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($str)
    {
//        $this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*(\d{1,2})\s*([[:alpha:]]{3,})\s*(\d{4})\s+(\d{1,2}:\d{2})\s*$#u", // lun mag 21, 2018 19:15
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }
}
