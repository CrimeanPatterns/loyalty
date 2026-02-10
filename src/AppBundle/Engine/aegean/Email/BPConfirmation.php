<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BPConfirmation extends \TAccountChecker
{
    public $mailFiles = "aegean/it-10051725.eml, aegean/it-10362999.eml, aegean/it-10445594.eml, aegean/it-2050165.eml, aegean/it-4679507.eml, aegean/it-4701722.eml, aegean/it-4713227.eml, aegean/it-4715854.eml, aegean/it-4715931.eml";

    private $langDetectors = [
        'en' => ['BOARDING PASS'],
    ];

    private $lang = '';

    private $pdfText = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $this->pdfText = $textPdf;

                break;
            } else {
                continue;
            }
        }

        if (!$this->pdfText) {
            return false;
        }

        $this->parseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aegeanair.com') !== false
            || stripos($from, '@olympicair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return stripos($headers['subject'], 'Boarding Pass Confirmation') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->logger->debug($textPdf);

            //			if (stripos($textPdf, 'www.aegeanair.com') === false && stripos($textPdf, 'www.olympicair.com') === false && stripos($textPdf, 'mobile.olympicair.com') === false)
            if (strpos($parser->getSubject(), 'flights Aegean') === false
                && stripos($textPdf, 'aegean') === false
                && stripos($textPdf, 'olympicair') === false
                && $this->http->XPath->query("//text()[contains(.,'olympicair.com')]")->length === 0
                && $this->http->XPath->query("//text()[contains(.,'aegeanair.com')]")->length === 0
                && stripos($textPdf, 'If you are affected by a long delay, denied boarding, cancellation or downgrading on a flight departing from or arriving to the') === false
            ) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 2; // 1 row or 2rows for flight
    }

    private function parseEmail(Email $email)
    {
        $text = $this->pdfText;

        $flights = $this->split('/(.+\n+.+ BOOKING REFERENCE .+)/', $text);

        foreach ($flights as $fText) {
            $this->parseFlight($email, $fText);
        }
    }

    private function parseFlight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $posName = strpos($text, 'NAME');
        $posFlight = strpos($text, "\n\n", $posName);
        $posInfo = strpos($text, 'Boarding pass information');

        $pass = substr($text, $posName - 10, $posFlight - $posName);
        $pass = preg_replace("#[\s\S]*\n([^\n\S]*NAME[\s\S]+)$#s", '$1', $pass);
        $passTable = $this->SplitCols($pass);

        if (count($passTable) == 4) {
            if (preg_match("#NAME\s*\n\s*(.+)#", $passTable[0], $m)) {
                $f->general()
                    ->traveller(preg_replace('/\s+(?:Mrs\.|Mr\.|Ms\.)$/i', '', $m[1]));
            }

            if (preg_match("#BOOKING REFERENCE\s+([A-Z\d]{5,7})\b#", $passTable[1], $m)) {
                $f->general()
                    ->confirmation($m[1]);
            }

            if (preg_match("#FREQUENT FLYER\s+([\d \-]+)\b#", $passTable[2], $m) && trim($m[1]) !== 'None') {
                $f->program()
                    ->account(trim($m[1]), false);
            }
        }
        $flight = substr($text, $posFlight, $posInfo - $posFlight);
        $segments = $this->split("#(.+Boarding (?:T|t)ime)#", $flight);

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match('/(?<dep>\b.+?)\s+to\s+(?<arr>\b.+?)[ ]*-[ ]*(?<date>.+?)\s+Boarding/', $stext, $m) || preg_match('/(?<dep>\b.+?)\s*-\s*(?<arr>\b.+?)\s+Boarding/', $stext, $m)) {
                if (preg_match('/(\b.+\b)\s*\((.+),\s+T(.*)\)/', $m['dep'], $mat)) {
                    $airportDep = $mat[1] . ', ' . $mat[2];
                    $s->departure()
                        ->terminal($mat[3]);
                } else {
                    $airportDep = $m['dep'];
                }
                $s->departure()
                    ->name($airportDep);

                if (preg_match('/(\b.+\b)\s*\((.+),\s+T(.*)\)/', $m['arr'], $mat)) {
                    $airportArr = $mat[1] . ', ' . $mat[2];
                    $s->arrival()
                        ->terminal($mat[3]);
                } else {
                    $airportArr = $m['arr'];
                }
                $s->arrival()
                    ->name($airportArr);

                if (!empty($m['date'])) {
                    $date = $m['date'];
                }
            }
            $flightTable = [];

            if (preg_match("#Boarding .+\n+([ ]*DATE.+)#s", $stext, $m)) {
                $flightTable = $this->SplitCols($m[1]);
            } elseif (preg_match("#Boarding .+\n+([ ]*FLIGHT.+)\n+([ ]*DEPARTURE.+)#s", $stext, $m)) {
                $rows = explode("\n", $m[1]);

                if (trim($rows[1]) == 'SEAT') {
                    $pos = strpos($rows[1], 'SEAT');
                    $rows[0] = substr_replace($rows[0], 'SEAT', $pos, 4);
                    unset($rows[1]);
                }
                $m[1] = implode("\n", $rows);
                $flightTable = $this->SplitCols($m[1]);
                $m[2] = explode("\n\n\n", $m[2])[0]; // it-10362999
                $flightTable = array_merge($flightTable, $this->SplitCols($m[2]));
            }

            $flvalue = [];

            foreach ($flightTable as $value) {
                if (preg_match("#^(.+)\n([\s\S]+)#", $value, $m)) {
                    $flvalue[$m[1]] = trim($m[2]);
                }
            }

            if (isset($flvalue['DATE'])) {
                $date = $flvalue['DATE'];
            }

            if (isset($flvalue['FLIGHT']) && preg_match("#([A-Z\d]{2})\s*(\d{1,5})(\s|$)#", $flvalue['FLIGHT'], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (isset($flvalue['SEAT']) && preg_match("#\b(\d{1,3}[A-Z])\b#", $flvalue['SEAT'], $m)) {
                $s->extra()
                    ->seat($m[1]);
            }

            if (isset($date) && isset($flvalue['DEPARTURE']) && preg_match('/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*(\b.+\b)?/s', $flvalue['DEPARTURE'], $m)) {
                $s->departure()
                    ->date(strtotime($this->normalizeDate($date . ' ' . $m[1])));

                if (!empty($m[2])) {
                    if (preg_match('/(\b.+),\s+T(.*\b)/s', $m[2], $mat)) {
                        $airportDep = $mat[1];
                        $s->departure()
                            ->terminal($mat[2]);
                    } else {
                        $airportDep = $m[2];
                    }
                    $airportDep = str_replace(["\n", '  '], ' ', $airportDep);
                    $s->departure()
                        ->name(empty($seg['DepName']) ? $airportDep : $seg['DepName'] . ', ' . $airportDep);
                }
                $s->departure()
                    ->noCode();
            }

            if (isset($date) && isset($flvalue['ARRIVAL']) && preg_match('/(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)\s*(\b.+\b)?/s', $flvalue['ARRIVAL'], $m)) {
                $s->arrival()
                    ->date(strtotime($this->normalizeDate($date . ' ' . $m[1])));

                if (!empty($m[2])) {
                    if (preg_match('/(\b.+),\s+T(.*\b)/s', $m[2], $mat)) {
                        $airportArr = $mat[1];
                        $s->arrival()
                            ->terminal($mat[2]);
                    } else {
                        $airportArr = $m[2];
                    }
                    $airportArr = str_replace(["\n", '  '], ' ', $airportArr);
                    $s->arrival()
                        ->name(empty($seg['ArrName']) ? $airportArr : $seg['ArrName'] . ', ' . $airportArr);
                }
                $s->arrival()
                    ->noCode();
            }

            if (!empty($flvalue['CLASS'])) {
                $s->extra()
                    ->cabin(preg_replace("#\s+#", ' ', $flvalue['CLASS']));
            }

            if (isset($flvalue['TICKET NUMBER'])) {
                $f->issued()
                    ->ticket($flvalue['TICKET NUMBER'], false);
            }
        }

        if (isset($it['TicketNumbers'])) {
            $f->setTicketNumbers(array_unique($it['TicketNumbers']), false);
        }
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function TableHeadPos($row)
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

    private function normalizeDate($str)
    {
        $in = [
            '#^(\d+)/(\d+)/(\d+)\s+(\d+:\d+)$#u', // 28/04/15 08:30
        ];
        $out = [
            '$1.$2.20$3 $4',
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }
}
