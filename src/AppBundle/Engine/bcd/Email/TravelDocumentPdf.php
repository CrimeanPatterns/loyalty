<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelDocumentPdf extends \TAccountChecker
{
    public $mailFiles = "bcd/it-12374560.eml, bcd/it-12374642.eml, bcd/it-10146507.eml, bcd/it-10180414.eml";

    private $reFrom = "first-business-travel.de";
    private $reSubject = [
        // de
        "Reiseplan für",
        "Reisebestätigung für",
    ];

    private static $detectProvider = [
        'bcd' => [
            //            'from' => '',
            'bodyPdf' => ['BCD Travel Germany'],
        ],
        'lufthansa' => [
            //            'from' => '',
            'bodyPdf' => ['Lufthansa City Center'],
        ],
        'amextravel' => [
            //            'from' => '',
            'bodyPdf' => ['American Express Global Business Travel'],
        ],
        'fcmtravel' => [
            'from'    => 'fcm.travel',
            'bodyPdf' => ['FCM Travel Solutions'],
        ],
        // last
        'fbusiness' => [
            'from'    => 'first-business-travel.de',
            'bodyPdf' => ['Business Travel'], // ?? no examples
        ],
    ];

    private $providerCode;
    private $pdfPattern = ".*\.pdf";

    private $date;
    private $lang = "de";
    private static $dictionary = [
        "de" => [
            'Flug' => 'Flug',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function parsePdf(Email $email)
    {
        $text = $this->text;

        $email->obtainTravelAgency();

        $otaConf = $this->re("#Reservierungsnummer:\s+(.+)#", $text);
        $email->ota()
            ->confirmation($otaConf);

        $f = $email->add()->flight();

        $f->general()
            ->noConfirmation()
            ->traveller(preg_replace(
                ['/(^\s*(MRS|MR|MS|MISS|MSTR|DR) | (MRS|MR|MS|MISS|MSTR|DR)\s*$)/', '/^\s*([^\\/]+?)\s*\\/\s*([^\\/]+?)\s*$/'], ['', '$2 $1'],
                $this->re("#Reisedaten für:\s+(.*?)\s{2,}#", $text)), true)
        ;
        $date = $this->re("#Datum:\s+(.*?)\s{2,}#", $text);

        if (empty($date)) {
            $date = $this->re("#(?: {2,}|\n *)(\d{2}\.\d{2}\.20\d{2})(?: {2,}|\s*\n)#", $this->re("/^((?:.*\n+){10})/", $text));
        }
        $f->general()
            ->date($this->normalizeDate($date))
        ;
        $this->date = $f->getReservationDate();

        $ticket = $this->re("#E-Ticketnummer:\s+(.+)#", $text);

        if (empty($ticket)) {
            $ticket = $this->re("#Ticket number: {3,}.*\n *(\d{3}[-?]\d{8,}.*?)(?: {3,}|\n)#", $text);
        }

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        preg_match_all("#\b([A-Z\d]{2})/([A-Z\d]+)#", $this->re("#(?:Airline-Buchungsnr\.|Airlinebuchungscode):\s+(.+)#", $text), $ms, PREG_SET_ORDER);
        $rls = [];

        foreach ($ms as $m) {
            $rls[$m[1]] = $m[2];
        }

        $segments = $this->split("/([^\n\S]*Flug \s+ Datum\s+Von.*)/", $text);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            $stext = $this->re("#^(.+Flugzeug.+?)(?:\n\n|$)#s", $stext);

            $pos = [0, min(mb_strlen($this->re("#^(.*?)Datum#ms", $stext), 'UTF-8'), mb_strlen($this->re("#\n(.*?)Buchung {2,}#m", $stext), 'UTF-8'))];
            $mainCols = $this->SplitCols($stext, $pos, false);

            if (count($mainCols) != 2) {
                $this->logger->info("parse segment failed");

                return;
            }
            $flightTable = $this->re("#(.+)\n *Buchung +#s", $mainCols[1]);
            $flightTable = $this->SplitCols($flightTable);

            if (count($flightTable) != 5) {
                $this->logger->info("incorrect parse table");

                return;
            }

            $date = $this->normalizeDate(trim($this->re("#Datum\n(.+)#s", $flightTable[0])));

            // Airline
            $s->airline()
                ->name($this->re("#\n\s*(\w{2}) \d+\s*\n#", $mainCols[0]))
                ->number($this->re("#\n\s*\w{2} (\d+)\s*\n#", $mainCols[0]))
            ;

            if (preg_match("/durchgeführt von\s+([\s\S]+?)\s+([A-Z\d]{2}) ?(\d{1,5})\s*$/", $mainCols[0], $m)) {
                $s->airline()
                    ->operator(preg_replace("/\s+/", ' ', trim($m[1])))
                    ->carrierName($m[2])
                    ->carrierNumber($m[3]);
            } elseif (preg_match("/durchgeführt von\s+([\s\S]+?)\s*$/", $mainCols[0], $m)) {
                $s->airline()
                    ->operator(preg_replace("/\s+/", ' ', trim($m[1])))
                ;
            }

            if (!empty($s->getAirlineName()) && !empty($rls[$s->getAirlineName()]) && $rls[$s->getAirlineName()] !== $otaConf) {
                $s->airline()
                    ->confirmation($rls[$s->getAirlineName()]);
            }

            // Departure
            $time = preg_replace("/\s*Uhr$/", '', $this->re("#Abflug\s+(.+)#", $flightTable[3]));
            $s->departure()
                ->noCode()
                ->name(trim(str_replace("\n", " ", $this->re("#Von\n(.*?)(?:\nTERMINAL|$)#s", $flightTable[1]))))
                ->terminal($this->re("#TERMINAL (\w+)#s", $flightTable[1]), true, true)
                ->date(($date && $time) ? strtotime($time, $date) : null)
            ;

            // Arrival
            $time = preg_replace("/\s*Uhr$/", '', $this->re("#Ankunft\s+(.+)#", $flightTable[4]));
            $s->arrival()
                ->noCode()
                ->name(trim(str_replace("\n", " ", $this->re("#Nach\n(.*?)(?:\nTERMINAL|$)#s", $flightTable[2]))))
                ->terminal($this->re("#TERMINAL (\w+)#s", $flightTable[2]), true, true)
                ->date(($date && $time) ? strtotime($time, $date) : null)
            ;

            // Extra
            $s->extra()
                ->aircraft($this->re("#Flugzeug:\s+(.*?) \(#", $mainCols[1]))
                ->cabin($this->re("#Klasse:\s+[A-Z] - (.*?),#", $mainCols[1]))
                ->bookingCode($this->re("#Klasse:\s+([A-Z]{1,2}) +- #", $mainCols[1]))
                ->duration($this->re("#Flugdauer:\n(.+)#", $flightTable[4]))
            ;

            $seat = $this->re("#Sitzplatz: +(\d{1,3}[A-Z])\n#", $mainCols[1]);

            if (!empty($seat)) {
                $s->extra()
                    ->seat($seat);
            }
            $meal = $this->re("#An Bord:\s*(.+)#", $mainCols[1]);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if ($this->detectPdf($text) === true) {
            return true;
        }

        return false;
    }

    public function detectPdf($text)
    {
        $detectedProvider = false;

        foreach (self::$detectProvider as $code => $detectProvider) {
            if (!empty($detectProvider['bodyPdf'])) {
                foreach ($detectProvider['bodyPdf'] as $dbody) {
                    if (strpos($text, $dbody) === false) {
                        $detectedProvider = true;
                        $this->providerCode = $code;

                        break;
                    }
                }
            }
        }

        if ($detectedProvider === false) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Flug'])) {
                $pos = 0;

                foreach ((array) $dict['Flug'] as $flight) {
                    $pos = strpos($text, $flight . '  ');

                    if (!empty($pos)) {
                        break;
                    }
                }

                if (preg_match("/\n *Flug {3,}Datum {3,}Von {3,}Nach {3,}Abflug {3,}Ankunft\n/", substr($text, $pos - 30, 200))) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }

        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        if ($this->detectPdf($this->text) === true) {
            $this->parsePdf($email);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }

        $year = date('Y', $relDate);

        $in = [
            "#^(?<week>[^\s\d]+), (\d+)\. ([^\s\d]+)$#", //Mo, 29. Feb
            "#^\s*(\d{2}\.\d{2}\.\d{4})\s+(\d+:\d+) Uhr$#", //07.02.2023 14:56 Uhr
        ];
        $out = [
            "$2 $3 {$year}",
            "$1 $2",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $dayOfWeekInt = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function ColsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i=> $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }

        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                $str = mb_substr($row, $p, null, 'UTF-8');

                if ($trim) {
                    $str = trim($str);
                }
                $cols[$k][] = $str;
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
