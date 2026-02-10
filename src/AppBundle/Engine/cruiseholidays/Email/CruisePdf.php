<?php
/**
 * Created by PhpStorm.
 * User: rshakirov.
 */

namespace AwardWallet\Engine\cruiseholidays\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CruisePdf extends \TAccountChecker
{
    private $detects = [
        'Thank you for booking with Cruise Holidays',
    ];

    private $from = '//'; // unknown from

    private $prov = 'Cruise Holidays';

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
            $this->parseEmail($email, $body);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    // unknown from
    public function detectEmailFromProvider($from)
    {
        return false;
    }

    // unknown from
    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));

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

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email, string $text): Email
    {
        $c = $email->add()->cruise();

        $cruise = $this->cutText('Travel End Date', ['Payment History'], $text);

        $passengersInfo = $this->cutText('Client Contact Details', ['Travel End Date'], $text);
        preg_match_all('/(.+?)\s+Loyalty\s*\#\s*:\s*(\w+)/i', $passengersInfo, $m);

        foreach ($m[1] as $i => $p) {
            $c->general()->traveller($p);

            if (!empty($m[2][$i])) {
                $c->program()->account($m[2][$i], false);
            }
        }

        $c->general()->confirmation($this->re('/Reservation\s*\#(\d+)/', $cruise));

        $c->details()->description($this->re('/\s{2,}(.+)\s*\-\s*Reservation\s*\#\d+/', $cruise));
        // Gov\'t Charges and Fees\s+([\S\d\.\,]+)

        if ($t = $this->re('/Port\s+tax\s+\S([\d\.,]+)/i', $cruise)) {
            $tax = str_replace(',', '', $t);
            $c->price()->tax($tax);
        }

        $history = $this->cutText('Payment History', ['Cruise Itinerary'], $text);

        if (preg_match('/\s+([A-Z]{3})\s+\S[\d\.,]+/', $history, $m)) {
            $cur = $m[1];
        }

        if (preg_match('/Booking Total\s*:\s+(\S)\s*([\d\.,]+)/', $cruise, $m)) {
            $cur = str_replace(['$'], [$cur], $m[1]);
            $tot = str_replace([','], [''], $m[2]);
            $c->price()
                ->total($tot)
                ->currency($cur);
        }

        $c->details()->roomClass($this->re('/Stateroom\s*:\s*\#\d+\s+\-\s*(\w+\s+\w+)/', $cruise));

        $c->details()->ship($this->re('/Ship\s*:\s{2,}(.+)\s{2,}Gov\'t/', $cruise));

        $cruiseSegmentText = $this->cutText('Cruise Itinerary', ['Additional Information'], $text);
        $segs = explode("\n", $cruiseSegmentText);

        foreach ($segs as $i => $seg) {
            if (!preg_match('/\d{1,2} \w+ \d{2,4}/', $seg)) {
                unset($segs[$i]);
            }
        }

        foreach ($segs as $seg) {
            $s = $c->addSegment();

            $sTable = $this->splitCols($seg);

            $date = strtotime($sTable[2]);

            if (6 === count($sTable)) {
                $s->setName($sTable[3])
                    ->setAshore(strtotime($sTable[4], $date))
                    ->setAboard(strtotime($sTable[5], $date));
            } elseif (5 === count($sTable)) {
                $s->setName($sTable[3]);

                if (isset($sTable[10])) {
                    $s->setAboard(strtotime($sTable[10], $date))->setAshore($date);
                } else {
                    $s->setAboard(strtotime(end($sTable), $date))->setAshore($date);
                }
            } elseif (4 === count($sTable)) {
                $s->setName($sTable[3])
                    ->setAboard($date)
                    ->setAshore($date);
            }
        }

        return $email;
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
                if (isset($pos[$k - 1]) && $p >= ((int) $pos[$k - 1] * 2)) {
                    $cols[10][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } // dirty hack
                else {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                }
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

    private function re(string $re, $str, int $i = 1): ?string
    {
        if (empty($str)) {
            return null;
        }

        if (preg_match($re, $str, $m) && !empty($m[$i])) {
            return $m[$i];
        }

        return null;
    }
}
