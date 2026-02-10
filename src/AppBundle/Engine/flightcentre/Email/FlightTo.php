<?php

namespace AwardWallet\Engine\flightcentre\Email;

use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class FlightTo extends \TAccountChecker
{
    public $mailFiles = "flightcentre/it-59982847.eml";
    public $reFrom = [
        "flightcentre.com.au",
    ];
    public $reSubject = [
        "Your Flight Centre Itinerary - ",
    ];
    public $reBody = [
        "en" => [
            'Flight Centre',
            'FLIGHT TO:',
            'BOOKING DETAILS',
        ],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dictionary = [
        'en' => [
            'StartFlight' => 'AIRLINE CODE & FLIGHT #',
            'EndFlight'   => 'PASSENGER',

            'StartPassenger' => 'PASSENGER',
            'EndPassenger'   => 'BOOKING DETAILS',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $i => $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug("can't determine a language in {$i}-attach");

                        continue;
                    }

                    if (!$this->parseEmailPdf($text, $email)) {
                        return null;
                    }
                }
            }
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->reBody as $lang => $reBody) {
                if ((stripos($text, $reBody[0]) !== false)
                    && (stripos($text, $reBody[1]) !== false)
                    && (stripos($text, $reBody[2]) !== false)
                    && $this->assignLang($text)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $subject) {
            if (stripos($headers['subject'], $subject) !== false) {
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

    private function parseEmailPdf($textPDF, Email $email)
    {
        $segmentBlocks = preg_split('/FLIGHT TO:/', $textPDF);

        foreach ($segmentBlocks as $segmentBlock) {
            if (empty($this->re("/(Baggage Policy)/", $segmentBlock))) {
                continue;
            }

            $f = $email->add()->flight();

            $seg = $f->addSegment();

            //Block Flight - view email 59982847
            $flightText = $this->re("/^.+\n(?:.+\n)?(.+{$this->t('StartFlight')}.+){$this->t('EndFlight')}/sm", $segmentBlock);
            $flightTable = $this->splitCols($flightText);

            if (preg_match("/(?<name>[A-Z]{2})\s(?<number>\d{2,4})/", $flightTable[0], $m)) {
                $seg->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            } elseif (preg_match("/{$this->opt($this->t('StartFlight'))}\s*(?<name>[\s\S]+?)\\n(?<number>\d{1,5})\n/", $flightTable[0], $m)) {
                $seg->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            $confNumber = $this->re("/BOOKING\s+#\s+([A-Z\d]{6})/s", $flightTable[1]);

            if (empty($confNumber)) {
                $confNumber = $this->re("/PNR Reference:\s+([A-Z\d]{6})/s", $segmentBlock);
            }

            if (!empty($confNumber)) {
                $f->general()
                    ->confirmation($confNumber, 'BOOKING #');
            }

            $distance = $this->re("/Distance:\s+(\d+)/", $segmentBlock);

            if ($distance) {
                $seg->extra()
                    ->miles($distance);
            }

            $tablePart = '';

            if (stripos($flightTable[1], $this->t('DEPARTURE')) !== false) {
                $tablePart = $flightTable[1];
            } elseif (stripos($flightTable[2], $this->t('DEPARTURE')) !== false) {
                $tablePart = $flightTable[2];
            }

            if (preg_match("/DEPARTURE\s+([\d\:]+\s*A?P?M\s+\w+\s\w+\s+\d+\,\s\d{4})\s+([A-Z]{3})\:/s", $tablePart, $m)) {
                $seg->departure()
                    ->date($this->normalizeDate(str_replace("\n", " ", $m[1])))
                    ->code($m[2]);

                $depTerminal = $this->re("/Terminal\sTERMINAL\s+([A-Z\d\s\-]+)/s", $tablePart);

                if (empty($depTerminal)) {
                    $depTerminal = $this->re("/Terminal\s*([A-Z\d\s\-]+)/s", $tablePart);
                }

                if (!empty($depTerminal)) {
                    $depTerminal = preg_replace("/(?:TERMINAL|TERM)/", "", $depTerminal);
                    $seg->departure()
                        ->terminal(str_replace("\n", " ", $depTerminal));
                }
            }

            if (stripos($flightTable[2], $this->t('ARRIVAL')) !== false) {
                $tablePart = $flightTable[2];
            } elseif (stripos($flightTable[3], $this->t('ARRIVAL')) !== false) {
                $tablePart = $flightTable[3];
            }

            if (preg_match("/ARRIVAL\s+([\d\:]+\s*A?P?M\s+\w+\s\w+\s+\d+\,\s\d{4})\s+([A-Z]{3})\:/su", $tablePart, $m)) {
                $seg->arrival()
                    ->date($this->normalizeDate(str_replace("\n", " ", $m[1])))
                    ->code($m[2]);

                $arrTerminal = $this->re("/Terminal\sTERMINAL\s+([A-Z\d\s\-]+)/s", $tablePart);

                if (empty($arrTerminal)) {
                    $arrTerminal = $this->re("/Terminal\s*([A-Z\d\s\-]+)/s", $tablePart);
                }

                if (!empty($arrTerminal)) {
                    $arrTerminal = preg_replace("/(?:TERMINAL|TERM)/", "", $arrTerminal);
                    $seg->arrival()
                        ->terminal(str_replace("\n", " ", $arrTerminal));
                }
            }

            //Block Passenger - view email 59982847
            $passText = $this->re("/\n\s+\n(\s+{$this->t('StartPassenger')}.+){$this->t('EndPassenger')}/ums", $segmentBlock);

            if (empty($passText)) {
                $passText = $this->re("/\n(\s+{$this->t('StartPassenger')}.+){$this->t('EndPassenger')}/ums", $segmentBlock);
            }
            $passTable = $this->splitCols($passText);

            $paxText = $this->re("/PASSENGER\s+(.+)\n\n\n/s", $passTable[0]);
            $travellers = array_filter(explode("\n", $paxText));

            if (!empty($travellers)) {
                $f->general()
                    ->travellers($travellers, true);
            }

            if (preg_match_all("/(\d{5,})/", $passTable[3], $m)) {
                $f->setTicketNumbers($m[1], false);
            }

            $seat = $this->re("/SEAT\s+(\d{1,2}[A-Z])/", $passTable[1]);

            if (!empty($seat)) {
                $seg->extra()
                    ->seat(trim($seat));
            }

            $cabin = $this->re("/CLASS\s+([A-Z]+)/s", $passTable[2]);

            if (!empty($cabin)) {
                $seg->extra()
                    ->cabin($cabin);
            }

            $meal = $this->re("/MEAL\s+(\D+)\s{2,}(?:page.+|$)/s", $passTable[5]);
            $meals = array_unique(array_filter(explode("\n", $meal)));

            if (!empty($meals)) {
                $seg->extra()
                    ->meal(implode(' ', $meals));
            }

            // remaining data after blocks (flight, passenger) - view email 59982847
            $operator = $this->re("/Operated\sBy[:]\s\/?(.+)/", $segmentBlock);

            if (!empty($operator)) {
                if (strlen($operator) > 50) {
                    $operator = $this->re("/^(.+)(?:AS|\s\-\s)/", $operator);
                }

                $seg->airline()
                    ->operator($operator);
            }

            $aircraft = $this->re("/Aircraft[:]\s*(.+)/", $segmentBlock);

            if (!empty($aircraft)) {
                $seg->extra()
                    ->aircraft($aircraft);
            }

            $duration = $this->re("/Duration[:]\s*(.+)/", $segmentBlock);

            if (!empty($duration)) {
                $seg->extra()
                    ->duration($duration);
            }
        }

        return true;
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

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function normalizeDate($str)
    {
        $in = [
            "#^([\d\:]+\s*A?P?M)\s+\w+\s(\w+)\s+(\d+)\,\s(\d{4})$#", //5:25 AM Sat May 09, 2020
        ];
        $out = [
            "$3 $2 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }
}
