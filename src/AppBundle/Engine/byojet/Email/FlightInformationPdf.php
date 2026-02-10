<?php

namespace AwardWallet\Engine\byojet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightInformationPdf extends \TAccountChecker
{
    public $mailFiles = "byojet/it-13145987.eml";

    private $detects = [
        'Thank you for choosing to travel with BYOjet, we are pleased to confirm your booking',
        'BYOjet Tax Invoice and Travel Documents',
        'Tax Invoice and Travel Documents',
        'Receipt and Travel Documents',
    ];

    private $prov = 'BYOjet';

    private $from = '/[@\.]byojet\.com/';

    private $lang = 'en';

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    $this->parseEmail($email, $body);

                    break;
                }
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $body .= \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

            if (false === stripos($body, $this->prov)) {
                return false;
            }

            foreach ($this->detects as $detect) {
                if (false !== stripos($body, $detect)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email, string $text): Email
    {
        $f = $email->add()->flight();

        $travelerText = $this->cutText('TRAVELLER INFORMATION', ['FLIGHT INFORMATION'], $text);
        $travelers = [];
        $accs = [];

        if (preg_match_all('/\d+\.\s*(.+)/', $travelerText, $m) && 0 < count($m[1])) {
            if (array_walk($m[1], function ($el) use (&$travelers, &$accs) { if (preg_match('/(.+)\s+\((\w+)\)/', $el, $math)) {$travelers[] = $math[1]; $accs[] = $math[2]; } }) && 0 < count($travelers) && 0 < count($accs)) {
                $f->general()
                    ->travellers($travelers);
                $f->program()
                    ->accounts($accs, false);
            } else {
                $travelers = array_map(function ($name) { return preg_replace('/\((.+)\)/', '', $name); }, $m[1]);
                $f->general()
                    ->travellers($travelers);
            }
        }

        if (preg_match('/Reservation Number\s+(\w+)/', $text, $m)) {
            $f->ota()
                ->confirmation($m[1]);
        }

        if (preg_match('/Booking Reference\s+(\w+)/', $text, $m)) {
            $f->general()
                ->confirmation($m[1]);
        }

        if (
            (false !== stripos($text, 'You will receive an email within the next 24 hours with your')
            && false !== stripos($text, 'e-ticket number and airline reference'))
            || false !== stripos($text, 'See following email for your airline booking reference number')
        ) {
            $f->general()
                ->noConfirmation();
        }

        if (preg_match('/Reservation Date\s*[:]*\s*(\d{1,2} \w+ \d{2,4})/', $text, $m)) {
            $f->general()->date(strtotime($m[1]));
        }

        $reference = $this->cutText('QUICK REFERENCE', ['TRAVELLER INFORMATION'], $text);
        $confsForSegs = [];

        if (preg_match_all('/.+\s*\[([A-Z\d]{2}\s*\d+)\]\s+(\w+)/', $reference, $m) && 0 < count($m[1])) {
            foreach ($m[1] as $i => $fn) {
                $confsForSegs[$fn] = $m[2][$i];
            }
        }

        if (preg_match('/Total Cost\s*[:]*\s*(\S)\s*([\d\.,]+)/', $text, $m)) {
            $f->price()
                ->total(str_replace([','], [''], $m[2]))
                ->currency(str_replace('$', 'USD', $m[1]));
        }

        $flightText = $this->cutText('FLIGHT INFORMATION', ['SCHEDULE CHANGES', 'FARES & PAYMENTS'], $text);
//        Cathay Pacific Flight CX170                                           Perth [PER]                                  Hong Kong [HKG]
//        Economy Class                                                         Wed 06 Sep 2017 23:55                        Thu 07 Sep 2017 07:45
        $re = '/(.+ [A-Z\d]{2}\s*\d+\s+.+\s\[[A-Z]{3}\]\s+.+\s\[[A-Z]{3}\]\s+.+\s+\w+ \d{1,2} \w+ \d{2,4} \d{1,2}:\d{2}\s+\w+ \d{1,2} \w+ \d{2,4} \d{1,2}:\d{2}[ ]*\n.*)/m';
        $segments = $this->splitText($flightText, $re, true);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $sTable = $this->splitCols($segment);
            $pos = [];

            if (2 === count($sTable)) {
                if (preg_match('/(((.+ Flight [A-Z\d]{2}\s*\d+\s+).+\s{2,}).+\s+)/', $segment, $m)) {
                    unset($m[0]);

                    foreach ($m as $math) {
                        $pos[] = mb_strlen($math);
                    }
                }
                $pos[0] = 0;
                sort($pos);
                $sTable = $this->splitCols($segment, $pos);
            }

            if (preg_match('/([A-Z\d]{2})\s*(\d+)(?:\s+Operated by\s+(.+))?\s+((?:Economy class|business|first class|economy))/i', $sTable[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3])) {
                    $s->airline()->operator($m[3]);
                }
                $s->extra()->cabin($m[4]);

                if (isset($confsForSegs[$m[1] . $m[2]])) {
                    $s->airline()->confirmation($confsForSegs[$m[1] . $m[2]]);
                }
            } elseif (preg_match('/([A-Z\d]{2})\s*(\d+)(?:\s+Operated by\s+(.+))?\s+/', $sTable[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                if (!empty($m[3])) {
                    $s->airline()->operator($m[3]);
                }
            }

            if (preg_match('/(?:Economy class|business|first class|economy)[ ]*\:[ ]*([ A-Z\d\-]+)/i', $sTable[0], $m)) {
                $s->extra()
                    ->aircraft($m[1]);
            }

            if (preg_match('/(.+)\s+\[([A-Z]{3})\]\s+(\w+ \d{1,2} \w+ \d{2,4} \d{1,2}:\d{2})/', $sTable[1], $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($m[3]));
            }

            if (preg_match('/(.+)\s+\[([A-Z]{3})\]\s+(\w+ \d{1,2} \w+ \d{2,4} \d{1,2}:\d{2})/', $sTable[2], $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime($m[3]));
            }
        }

        return $email;
    }

    private function cutText(string $start = '', array $ends = [], string $text = '')
    {
        if (!empty($start) && 0 < count($ends) && !empty($text)) {
            foreach ($ends as $end) {
                if (($cuttedText = stristr(stristr($text, $start), $end, true)) && is_string($cuttedText) && 0 < strlen($cuttedText)) {
                    break;
                }
            }

            return substr($cuttedText, 0);
        }

        return null;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
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

    private function splitCols($text, $pos = false): array
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

    private function rowColsPos($row): array
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
}
