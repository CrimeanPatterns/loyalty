<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ViaBookingPDF extends \TAccountChecker
{
    public $mailFiles = "booking/it-62293311.eml";
    public $from = '@property.booking.com';
    public $header = 'Thank you for choosing';
    public $bodyHTML = ['sent via Booking.com', 'Booking.com will receive and process replies to this email'];
    public $bodyPDF = ['Guest Details', 'Stay Details', 'Additional Comments'];
    public $pdfPattern = '.+\.pdf';
    public $textPDF;

    public function detectEmailFromProvider($from)
    {
        if (stripos($this->from, $from) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach ($this->bodyHTML as $bodyHTML) {
            if ($this->http->XPath->query("//text()[contains(normalize-space(), '" . $bodyHTML . "')]")->count() == 0) {
                return false;
            } else {
                $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

                foreach ($pdfs as $pdf) {
                    $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                    foreach ($this->bodyPDF as $bodyPDF) {
                        if (empty($this->re("/({$bodyPDF})/", $textPdf))) {
                            return false;
                        }
                    }

                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            /*$body = str_replace("&#160;", " ", \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX));
            if ($body === null)
                return null;*/
            //$this->pdf = clone $this->http;
            //$this->pdf->SetEmailBody($body);
            $this->textPDF = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->parsePdf($email);
        }

        return $email;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);            // 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);    // 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);    // 18800,00		->	18800.00

        return $string;
    }

    private function parsePdf(Email $email)
    {
//        $this->logger->warning($this->textPDF);
        $text = $this->cutText('Guest Details', 'Booking Items', $this->textPDF);
//        $this->logger->warning($text);
        $table = $this->splitCols($text);
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/([A-Z\d]{15,})\s+Guest\s+Details/", $this->textPDF), 'Booking Number')
            ->traveller($this->re("/Guest\s*Name\:\s*(\D+)\s+Guest\s*Email/", $table[0]), true)
            ->date(strtotime($this->re("/Booking\s*Date\:\s*(\w+\,\s*\w+\s*\d+\,\s+\d{4})\s+/", $table[0])))
            ->cancellation(str_replace("\n", " ", $this->re("/\.\s*cancellation\s*policy\:\s*(.+)\s+additional\s*comments/is", $this->textPDF)));

        $h->hotel()
            ->name($this->re("/Accommodation\:\s*(\D+)\n/", $table[1]))
            ->phone($this->re("/([+]\d+\s*[\d\-]+)/", $table[1]))
            ->address(str_replace("\n", " ", $this->re("/Booking\s*Date\:\s*\w+\,\s*\w+\s*\d+\,\s+\d{4}\s+(\D+)/", $table[0])));

        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/Check\s*In\:\s*(.+)\n/", $table[1])))
            ->checkOut($this->normalizeDate($this->re("/Check\s*Out\:\s*(.+)\n/", $table[1])));

        $room = $h->addRoom();
        $room->setType($this->re("/booking\s*items\s+(.+)\s+[A-Z]{3}\s+/i", $this->textPDF));
        $room->setDescription(str_replace("\n", " ", $this->re("/general\:\s*(.+)\s*deposit\s*policy\:/is", $this->textPDF)));

        $h->price()
            ->total($this->normalizePrice($this->re("/Booking\s*value\s+[A-Z]{3}\s+([\d\,\.]+)/", $this->textPDF)))
            ->currency($this->re("/Booking\s*value\s+([A-Z]{3})\s+/", $this->textPDF));
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = strstr(strstr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\w+\,\s*(\w+)\s*(\d+)\,\s+(\d{4})\s+(?:from|until)\s+([\d\:]+)$#u',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        return strtotime($date);
    }
}
