<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCarPdf extends \TAccountChecker
{
    public $mailFiles = "avis/it-45323517.eml";

    private $lang = '';

    private $reBody = [
        'en' => ['Car Pick Up Details', 'Car Return Details'],
    ];

    private $from = '/[\.@]avis\.(?:com|de)/i';

    private $prov = 'Avis';

    private $pdfNamePattern = '.*pdf';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->assignLang($text)) {
                        $this->logger->debug('can\'t determine a language');

                        continue;
                    } else {
                        if (!$this->parseEmail($email, $text)) {
                            $this->logger->alert('method: ' . __METHOD__ . 'exited with false result');

                            return null;
                        }
                    }
                }
            }
        }
        $email->setType('RentalCarPdf' . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (false === stripos($text, $this->prov)) {
                return false;
            }

            if ($this->assignLang($text)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    private function parseEmail(Email $email, string $text): bool
    {
        $r = $email->add()->rental();

        $mainContent = $this->findCutSection($text, 'Avis Travel Voucher', 'Remarks:');

        if ($ota = $this->re('/Avis Travel Voucher No\.[ ]+(\d+)/', $mainContent)) {
            $r->ota()
                ->confirmation($ota);
        }

        if ($conf = $this->re('/Reservation No\.[ ]+([A-Z\d]+)/', $mainContent)) {
            $r->general()
                ->confirmation($conf);
        }

        if ($renter = $this->re('/Customer Name\:[ ]+(.+)/', $mainContent)) {
            $r->addTraveller($renter);
        }

        if ($acc = $this->re('/Avis Account No\.[ ]+(\w+)/', $mainContent)) {
            $r->addAccountNumber($acc, false);
        }

        if (preg_match('/Car Type\:[ ]+(Group [A-Z]), (.+) \*or similar/', $mainContent, $m)) {
            $r->car()
                ->model($m[2]);
        }

        $details = $this->findCutSection($mainContent, 'Car Pick Up Details', 'Remarks');
        $str = $this->re('/(Date\:.+)/', $details);
        $pos = $this->rowColsPos($str);

        foreach ($pos as $k => $p) {
            if (($k % 2) === 0) {
                $pos[$k] = $p;
            } else {
                unset($pos[$k]);
            }
        }

        $table = array_values($this->splitCols($details, $pos));

        if (($date = $this->re('/Date\:[ ]+(\d{1,2}\-\w+\-\d{2,4})/', $table[0])) && ($time = $this->re('/Time\:[ ]+(\d{1,2}\:\d{2})/', $table[0]))) {
            $r->pickup()
                ->date(strtotime(str_replace('\-', ' ', $date) . ', ' . $time));
        }

        if (($ddate = $this->re('/Date\:[ ]+(\d{1,2}\-\w+\-\d{2,4})/', $table[1])) && ($dtime = $this->re('/Time\:[ ]+(\d{1,2}\:\d{2})/', $table[1]))) {
            $r->dropoff()
                ->date(strtotime(str_replace('\-', ' ', $ddate) . ', ' . $dtime));
        }

        if ($pickUp = $this->re('/Pick Up station\:[ ]+(.+)/', $table[0])) {
            $r->pickup()
                ->location($pickUp);
        }

        if ($phone = $this->re('/phone\:[ ]+(\d+)/', $table[0])) {
            $r->pickup()
                ->phone($phone);
        }

        if ($dropOff = $this->re('/Drop Off station\:[ ]+(.+)/', $table[1])) {
            $r->dropoff()
                ->location($dropOff);
        }

        if ($dphone = $this->re('/phone\:[ ]+(\d+)/', $table[1])) {
            $r->dropoff()
                ->phone($dphone);
        }

        return true;
    }

    private function assignLang($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function findCutSection($input, $searchStart, $searchFinish = null): string
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return '';
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, 0);
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
}
