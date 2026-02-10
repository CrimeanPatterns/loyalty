<?php

namespace AwardWallet\Engine\ana\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketReceiptPdf extends \TAccountChecker
{
    public $mailFiles = "ana/it-50120016.eml";

    private $from = "@121.ana.co.jp";

    private $subject = ["eチケットお客様控"];

    private $body = 'ANA';

    private $lang;

    private $pdfNamePattern = ".*pdf";

    private static $detectors = [
        'en' => ["TICKET INFORMATION", "ITINERARY"],
        'ja' => ["航空券情報", "旅程"],
    ];
    private static $dictionary = [
        'en' => [
            "RESERVATION NO" => ["RESERVATION NO"],
            "PRINT DATE"     => ["PRINT DATE"],
        ],
        'ja' => [
            "RESERVATION NO" => ["予約番号"],
            "PRINT DATE"     => ["発行日"],
        ],
    ];

    private $year;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subject as $sub) {
            if (stripos($headers["subject"], $sub) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->from) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, $this->body) === false) {
                return false;
            }
        }

        if ($this->detectBody($parser)) {
            return $this->assignLang($parser);
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang($parser)) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!empty($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), 2)) !== null) {
                    $this->parseEmailPdf($email, $html, $text);
                }
            }
        }
        $email->setType('ETicketReceiptPdf');

        return $email;
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach (self::$detectors as $lang => $phrases) {
                foreach ($phrases as $phrase) {
                    if (!empty(stripos($text, $phrase)) && !empty(stripos($text,
                            $phrase))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $html = \PDF::convertToHtml($parser->getAttachmentBody($pdf[0]), 2);
            $http1 = clone $this->http;
            $http1->SetBody($html);

            foreach (self::$dictionary as $lang => $words) {
                if ($http1->XPath->query("//*[{$this->contains($words["RESERVATION NO"])}]")->length > 0
                    && $http1->XPath->query("//*[{$this->contains($words["PRINT DATE"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function parseEmailPdf(Email $email, $html, $text)
    {
        $httpComplex = clone $this->http;
        $httpComplex->SetBody($html);

        $r = $email->add()->flight();

        $confsNo = explode(' ',
            $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('RESERVATION NO')) . "]/following-sibling::p[1]"));

        foreach ($confsNo as $k => $conf) {
            if (!empty($conf)) {
                if (preg_match('/([A-Z\d]{2})\/([A-Z\d]{5,6})/', $conf, $m)) {
                    if ($k == 0) {
                        $r->general()->confirmation($m[2], $m[1], true);
                    } else {
                        $r->general()->confirmation($m[2], $m[1]);
                    }
                }
            }
        }

        $resDate = $httpComplex->FindSingleNode("//text()[" . $this->contains($this->t('PRINT DATE')) . "]", null, true,
            '/' . $this->opt($this->t('PRINT DATE')) . ':(\d{1,2}[A-Z]{3}\d{2,4})/'); //11DEC19

        if (!empty($resDate)) {
            $r->general()->date(strtotime($resDate));

            if (preg_match('/\d{1,2}[A-Z]{3}(\d{2})/', $resDate, $m)) {
                $this->year = $m[1];
            }
        }

        $pax = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('NAME')) . "]/following-sibling::p[1]");

        if (!empty($pax)) {
            $r->general()->traveller($pax, true);
        }

        $ticket = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('TICKET NUMBER')) . "]/following-sibling::p[1]");

        if (!empty($ticket)) {
            $r->issued()->ticket($ticket, false);
        }

        $total = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('TOTAL FARE')) . "]/following-sibling::p[1]",
            null, true, "/[A-Z]{3}(\d+[\d,.]+)/");

        if (!empty($total)) {
            $r->price()->total($total);
        }
        $cur = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('TOTAL FARE')) . "]/following-sibling::p[1]",
            null, true, "/([A-Z]{3})\d+[\d,.]+/");

        if (!empty($cur)) {
            $r->price()->currency($cur);
        }

        $fees = $httpComplex->FindSingleNode("//*[" . $this->starts($this->t('TAX/FEE/CHARGE')) . "]/following-sibling::p[1]");

        if (!empty($fees)) {
            if (preg_match_all("/(\d+[A-Z]+)/", $fees, $m)) {
                foreach ($m[1] as $k => $v) {
                    if (preg_match("/(\d+)([A-Z]+)/", $v, $m)) {
                    }
                    $r->price()->fee($m[2], $m[1]);
                }
            }
        }
        //Segment
        if (preg_match_all('/TERMINAL((?:\n.*)+)\s■運賃\/航空券情報\/FARE\/TICKET INFORMATION/', $text, $blocks)) {
            foreach ($blocks[1] as $block) {
                $segments = array_filter(array_map('trim', preg_split("/\s\n/", $block)));

                foreach ($segments as $segment) {
                    $tSeg = $this->getTableSegment($segment);

                    if (preg_match_all('/\d{1,2}[A-Z]{3}\([A-Z]{3}\)\s\d{4}/', $tSeg[0], $m)) {
                        if ((count($m[0]) % 2) == 0) {
                            $segment = array_chunk($m[0], count($m[0]) / 2);

                            foreach ($segment as $seg) {
                                if (preg_match('/(\d{1,2})([A-Z]{3})\(([A-Z]{3})\)\s(\d{2})(\d{2})/', $seg[0], $m)) {
                                    $it['depDate'][] = $this->normalizeDate($m[1] . " " . $m[2] . " " . $m[4] . ":" . $m[5] . " " . $m[3]);
                                }

                                if (preg_match('/(\d{1,2})([A-Z]{3})\(([A-Z]{3})\)\s(\d{2})(\d{2})/', $seg[1], $m)) {
                                    $it['arrDate'][] = $this->normalizeDate($m[1] . " " . $m[2] . " " . $m[4] . ":" . $m[5] . " " . $m[3]);
                                }
                            }
                        }
                    }

                    if (preg_match_all('/([A-Z ]+)\n/', $tSeg[1], $m)) {
                        if ((count($m[0]) % 2) == 0) {
                            $segment = array_chunk($m[0], count($m[0]) / 2);

                            foreach ($segment as $seg) {
                                $it['depName'][] = $seg[0];
                                $it['arrName'][] = $seg[1];
                            }
                        }
                    }

                    if (preg_match_all('/([A-Z]{2}\d{4,5}\s\/\s[A-Z]{1}\s\/\s.+)/', $tSeg[2], $m)) {
                        foreach ($m[1] as $seg) {
                            if (preg_match('/([A-Z]{2})(\d{4,5})\s\/\s([A-Z]{1})\s\/\s(.+)/', $seg, $m)) {
                                $it['AirlineName'][] = $m[1];
                                $it['FlightNumber'][] = $m[2];
                                $it['Class'][] = $m[3];
                                $it['Seat'][] = $m[4];
                            }
                        }
                    }

                    if (preg_match_all('/TERMINAL\s(.+?)[\s]?/', $tSeg[4], $m)) {
                        if ((count($m[1]) % 2) == 0) {
                            $segment = array_chunk($m[1], count($m[1]) / 2);

                            foreach ($segment as $seg) {
                                $it['debTerm'][] = $seg[0];
                                $it['arrTerm'][] = $seg[1];
                            }
                        }
                    }

                    foreach ($it['AirlineName'] as $k => $v) {
                        $s = $r->addSegment();
                        $s->airline()
                            ->name($v)
                            ->number($it['FlightNumber'][$k]);
                        $s->departure()
                            ->name($it['depName'][$k])
                            ->date($it['depDate'][$k])
                            ->terminal($it['debTerm'][$k])
                            ->noCode();
                        $s->arrival()
                            ->name($it['arrName'][$k])
                            ->date($it['arrDate'][$k])
                            ->terminal($it['arrTerm'][$k])
                            ->noCode();
                        $s->extra()
                            ->bookingCode($it['Class'][$k])
                            ->seat($it['Seat'][$k]);
                    }
                }
            }
        }

        return $email;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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

    private function getTableSegment($segment)
    {
        $table = $this->splitCols($segment);
        array_splice($table, 5);

        return $table;
    }

    private function normalizeDate($date)
    {
        $in = [
            // 12 DEC 11:15 THU
            '/(\d{1,2})\s([A-Z]{3})\s(\d{1,2}:\d{1,2})\s([A-Z]{3})/u',
        ];
        $out = [
            '$1 $2 ' . $this->year . ' $3',
        ];
        $outWeek = [
            '$4',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
