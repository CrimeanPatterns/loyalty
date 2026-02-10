<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ETicketFlightPDF extends \TAccountChecker
{
    public $reFrom = ["atpi.com"];
    public $reBody = [
        'en' => [
            'The APTI team can be contacted',
        ],
    ];
    public $reBodyPdf = [
        'en' => ['YOUR ITINERARY DETAILS'],
    ];
    public $reSubject = [
        '/.+Flight Itinerary for.+$/',
    ];
    public $lang = '';
    public $pdf;
    public $refFlight;
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'Travelers'       => 'Passenger Details',
            'TableStart'      => 'YOUR ITINERARY DETAILS', //ORDER #
            'TableEnd'        => 'PRIVACY POLICY:', //33-41 Agnes Street
            'endTraveler'     => ['YOUR ITINERARY DETAILS'],
            'DeleteTextBlock' => ['33-41', 'UNISA', 'Created'],
        ],
    ];
    public $ref;
    private $keywordProv = 'ATPI';
    private $cancelledSubject = [
        'CANCELLED - Your order has been cancelled',
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (preg_match("/{$this->opt($this->cancelledSubject)}/", $parser->getSubject())) {
            $cancelledSubject = true;
        }

        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLangText($text) && $this->detectBody($text)) {
                        $this->parseEmailPdf($text, $email);
                        $type = "Pdf";

                        if (isset($cancelledSubject) && count($email->getItineraries()) > 0) {
                            // break the parsing
                            $this->logger->alert('new format: pdf cancelled');
                            $email->add()->flight();

                            break;
                        }
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ((preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $text) > 0)
                && $this->assignLangText($text)
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailPdf(string $textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        $checkType = strstr($textPDF, $this->t('YOUR ITINERARY DETAILS'));
        $rows = explode("\n", $checkType);

        if (!isset($rows[1])) {
            $this->logger->debug('other format');

            return false;
        }
        $this->parseFlightPdf($textPDF, $email);
    }

    private function parseFlightPdf(string $textPDF, Email $email)
    {
        $this->logger->notice(__METHOD__);
        // del garbage
        $textPDF = preg_replace("/33-41.+\n+Created.+\n+.+UNISA.+/", '', $textPDF);
        $mainBlock = strstr($textPDF, 'BOOKING TERMS & CONDITIONS', true);

        $travellers = $this->re('/Passenger\sDetails\n\s+(.+)\s+YOUR/', $mainBlock);
        $segments = $this->splitter("/({$this->opt($this->t('Date'))})/", $mainBlock);
        $flight = [];

        foreach ($segments as $i => $seg) {
            if ($this->ref != $this->re("/Reference\s#:\s(.{6})/", $seg)) {
                $this->ref = $this->re("/Reference\s#:\s(.{6})/", $seg);
            }

            if (preg_match_all("/(\d+:\d+)/", $seg, $m, PREG_SET_ORDER) && count($m) == 2) {
                $resultSegments = $this->splitter("/\n([ ]*.+?Depart:)/", $seg);
            } else {
                $this->logger->debug('other format segment');

                return false;
            }

            foreach ($resultSegments as $j => $segment) {
                $table = $segment;
                $table = $this->splitCols($table, $this->colsPos($this->re("/(.+)/", $table)));

                if (count($table) !== 5) {
                    $tempColPos = $this->colsPos($this->re("/(.+)/", $segment));

                    $lastpos = 0;

                    if (preg_match("/Depart: [A-Z \-]+ ([A-Z][\w ]+?)[ ]+Reference/", $segment, $m)) {
                        $tempColPos[] = mb_strpos($segment, $m[1], $lastpos, 'UTF-8');
                    }

                    sort($tempColPos);

                    $table = $this->splitCols($segment, $tempColPos);

                    if (count($table) !== 5) {
                        $this->logger->debug('other formet (' . $i . '-' . $j . ') segment');

                        return false;
                    }
                }
                $flight[$this->ref][] = $table;
            }
        }

        foreach ($flight as $this->ref=>$segments) {
            $r = $email->add()->flight();
            $r->general()->travellers(explode(',', $travellers), true);
            $r->general()->confirmation($this->ref);

            foreach ($segments as $data) {
                $s = $r->addSegment();
                $s->departure()->noCode();
                $s->arrival()->noCode();

                $dateDep = $this->re('/(\D+\d+,\s\d+)/', $data[0]);
                $timeDep = $this->re('/(\d+:\d+)/', $data[1]);
                $s->departure()->date(strtotime($dateDep . ' ' . $timeDep));

                $dateArr = $this->re('/\D+\d+,\s\d+\s(\D+\d+,\s\d+)/', $data[0]);
                $timeArr = $this->re('/\d+:\d+\s(\d+:\d+)/', $data[1]);

                if (!empty($dateArr)) {
                    $s->arrival()->date(strtotime($dateArr . ' ' . $timeArr));
                } else {
                    $s->arrival()->date(strtotime($dateDep . ' ' . $timeArr));
                }

                $s->departure()->name($this->re('/Depart:\s(\D+)\sArrive:/', $data[2]));
                $s->arrival()->name($this->re('/Arrive:\s(\D+)/', $data[2]));

                $s->airline()->name($this->re('/\D+\s(\D+)/', $data[3]));
                $s->airline()->number($this->re('/\D+\s\D+(\d+)/', $data[3]));

                $this->refFlight[] = $this->re('/Reference\s#:\s(.{6})/', $data[4]);

                $refAirlines = $this->re('/Reference\s#:\s.{6}Reference\s#:\s(.{6})/', $data[4]);

                if (!empty($refAirlines)) {
                    $s->airline()->confirmation($refAirlines);
                }
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBodyPdf)) {
            foreach ($this->reBodyPdf as $lang => $reBody) {
                $reBody = (array) $reBody;

                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLangText($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['TableStart'], $words['TableEnd'])) {
                if (stripos($body, $words['TableStart']) !== false && stripos($body, $words['TableEnd']) !== false) {
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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
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
}
