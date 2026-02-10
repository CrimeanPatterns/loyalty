<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It1831519 extends \TAccountCheckerExtended
{
    public $mailFiles = "british/it-10464410.eml, british/it-10477367.eml, british/it-1831519.eml, british/it-1847964.eml, british/it-60585272.eml, british/it-8440603.eml, british/it-8626761.eml";

    private $detects = [
        'We have checked your travel documents which are all in order',
        'Your checked baggage allowance',
        'Please go straight to departures',
        'Consulte ba.com',
        'Please see ba.com',
        're ready to fly',
    ];

    private $from = ['britishairways.com', '@ba.com'];
    private $detectProvider = ['british', 'ba.com', ' BA'];

    private $lang = 'en';
    private $date;
    private $pdfFileName;

    public function flight(Email $email, $pdfText): void
    {
        $traveller = $this->re("/\s+^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+you\'re\s*ready\s*to\s*fly/m", $pdfText);

        if (!empty($traveller)) {
            $flight = $email->add()->flight();

            $flight->general()
                ->traveller($traveller, true);

            $segment = $this->re("/^(\s+Flight.+\d+\s+)\-/sm", $pdfText);

            if (empty($segment)) {
                $segment = $this->re("/^(Flight.+[A-Z\d]{6}\s+)ENTREGA/sm", $pdfText);
            }

            if (empty($segment)) {
                $segment = $this->re("/^(\s+Flight.+\d+\s+)We\shave/sm", $pdfText);
            }

            if (empty($segment)) {
                $segment = $this->re("/^(\s+Flight.+[A-Z\d]{6}\s+)\-/sm", $pdfText);
            }

            if (empty($segment)) {
                $segment = $this->re("/^(\s*Flight.+From.+)\n[-]{6,}\n\s*(?:Your passport|We have checked)/sm", $pdfText);
            }

            if (!empty($segment)) {
                $seg = $flight->addSegment();

                $headPos = $this->TableHeadPos($this->re("/^\s+Flight.+/", $segment));

                $table = [];
                $table = $this->SplitCols($segment, $headPos);

                if (preg_match("/SOLD\s*AS\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/", $table[0], $m)
                    || preg_match("/Flight\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/", $table[0], $m)
                ) {
                    $seg->airline()
                        ->name($m[1])
                        ->number($m[2]);
                }

                $dateDep = $this->re("/Date\s+(\d+\s+\w+)\s*Operating/s", $table[0]);
                $operator = $this->re("/operating\s*airline\s*(.+)$/si", $table[0]);

                if (strlen($operator) > 20) {
                    $operator = $this->re("/operating\s*airline\s*(\D+)\s+\-/si", $table[0]);
                }

                if (!empty($operator)) {
                    $seg->airline()
                        ->operator($operator);
                }

                $patterns['nameTerminal'] = '/^\s*(?<n>[\s\S]{3,}?)\n+[ ]*Terminal\s+(?<t>[\s\S]+)$/i';

                $depName = $this->re("/^\s*From\n+[ ]*([\s\S]{3,}?)\n[ ]*(?:Clear security by|Class)/", $table[1]);

                if (preg_match($patterns['nameTerminal'], $depName, $m)) {
                    $seg->departure()
                        ->name(preg_replace('/\s+/', ' ', $m['n']))
                        ->terminal(preg_replace('/\s+/', ' ', $m['t']))
                        ->noCode();
                } elseif ($depName) {
                    $seg->departure()
                        ->name(preg_replace('/\s+/', ' ', $depName))
                        ->noCode();
                }

                $flightClass = $this->re("/Class\s*(\D+)/s", $table[1]);

                if (strlen($flightClass) > 20 && stripos($flightClass, '-') !== false) {
                    $flightClass = $this->re("/Class\s*(\D+)\s+\-/u", $table[1]);
                }

                if (strlen(trim($flightClass)) == 1) {
                    $seg->extra()
                        ->bookingCode($flightClass);
                } else {
                    $seg->extra()
                        ->cabin(str_replace("\n", ' ', $flightClass));
                }

                $arrName = $this->re("/^\s*To\n+[ ]*([\s\S]{3,}?)\n[ ]*(?:Boarding|Booking|Gate)/", $table[2]);

                if (preg_match($patterns['nameTerminal'], $arrName, $m)) {
                    $seg->arrival()
                        ->name(preg_replace('/\s+/', ' ', $m['n']))
                        ->terminal(preg_replace('/\s+/', ' ', $m['t']))
                        ->noCode()
                        ->noDate();
                } elseif ($arrName) {
                    $seg->arrival()
                        ->name(preg_replace('/\s+/', ' ', $arrName))
                        ->noCode()
                        ->noDate();
                }

                if (preg_match("/^[ ]*(Booking reference)[:\n]+[ ]*([-A-Z\d]{5,})$/m", $table[2], $m)) {
                    $flight->general()->confirmation($m[2], $m[1]);
                }

                $depTime = $this->re("/Departure\stime\s*([\d\:]+)\s/s", $table[3]);
                $seg->departure()
                    ->date($this->normalizeDate($dateDep . ', ' . $depTime));

                $account = $this->re("/Frequent\s*flyer\s*[A-Z\s\/]{2,}(\d+)/s", $table[3]);

                if (!empty($account)) {
                    $flight->ota()
                        ->account($account, false);
                }

                if (preg_match("/Seat\s*(\d+[A-Z])\b/", (empty($table[4]) ? '' : $table[4] . "\n") . $table[3], $m)) {
                    $seg->extra()->seat($m[1]);
                }

                if (!empty($this->pdfFileName)) {
                    $bp = $email->add()->bpass();
                    $bp->setFlightNumber($seg->getFlightNumber())
                        ->setDepDate($seg->getDepDate())
                        ->setRecordLocator($flight->getConfirmationNumbers()[0][0])
                        ->setAttachmentName($this->pdfFileName)
                        ->setTraveller($traveller);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $pdfText = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $this->pdfFileName = $this->getAttachmentName($parser, $pdf);
            $this->flight($email, $pdfText);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->from as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdf[0])) {
            return false;
        }
        $pdfBody = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

        foreach ($this->detects as $detect) {
            if (stripos($pdfBody, $detect) !== false) {
                foreach ($this->detectProvider as $dProvider) {
                    if (stripos($pdfBody, $dProvider) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->from as $reFrom) {
            if (stripos($headers['from'], $reFrom) !== false) {
                return true;
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\w+)\s*(\d+)\,\s*([\d\:]+\s*A?P?M)$#", //Mar 14, 10:41 AM
            "#^(\d+)\s*(\w+)\,\s*([\d\:]+)$#", //28 December, 16:05
        ];
        $out = [
            "$2 $1 $year, $3",
            "$1 $2 $year, $3",
        ];
        $str = (preg_replace($in, $out, $str));

        return strtotime($str);
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
