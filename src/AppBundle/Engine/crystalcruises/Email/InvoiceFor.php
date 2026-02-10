<?php

namespace AwardWallet\Engine\crystalcruises\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InvoiceFor extends \TAccountChecker
{
    public $mailFiles = "crystalcruises/it-645682201.eml, crystalcruises/it-654883489.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $subjects = [
        '/Invoice for Booking\s*\d{1,}\s*\-/u',
        '/Invoice for Booking\s*\d{1,}$/u',
    ];

    public $firstSegment = false;
    public $segConfArray = [];

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@crystalcruises.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "Thank you for choosing Crystal for your exceptional cruise") !== false
                && (strpos($text, 'YOUR VOYAGE DETAIL') !== false)
                && (preg_match("/^\s*DATE\s*ITINERARY\s*{$this->addSpacesWord('ARRIVE')}\s*DEPART/m", $text))
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]crystalcruises.com$/', $from) > 0;
    }

    public function ParseCruisePDF(Email $email, $text)
    {
        $c = $email->add()->cruise();

        $confNumber = $this->re("/BOOKING ID\s*(?:AGENT)?\n\s*(\d{4,})\s/", $text);

        if (in_array($confNumber, $this->segConfArray) === false) {
            $this->segConfArray[] = $confNumber;
        } else {
            $email->removeItinerary($c);

            return $email;
        }

        $c->general()
            ->confirmation($confNumber);

        $guestText = $this->re("/GUESTS\n+(.+)\n\s*VOYAGES DETAIL/su", $text);
        $guestTable = $this->splitCols($guestText, [0, 50]);
        $guestTextFull = $guestTable[0] . "\n" . $guestTable[1];

        if (preg_match_all("/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s+\-\s+CS\s*CODE\:/m", $text, $m)) {
            $c->general()
                ->travellers($m[1]);
        } elseif (preg_match_all("/\s*([\.[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]).*\n\s*[+]/", $guestTextFull, $m)) {
            $c->general()
                ->travellers($m[1]);
        }

        $voyagesDetails = $this->re("/VOYAGE(?:S DETAIL)?\n+(\s.+\n+)COST BREAKDOWN/msu", $text);
        $pos = strlen($this->re("/^(.*)SUITE/", $voyagesDetails));
        $vdTables = $this->splitCols($voyagesDetails, [0, $pos]);
        $suite = $this->re("/SUITE\s*(.+)\s+{$this->addSpacesWord('ARRIVAL PORT')}/su", $vdTables[1]);

        if (!empty($suite)) {
            $c->setDescription(preg_replace("/\n\s*/", " ", $suite));
        }

        $price = $this->re("/GUEST TOTAL\s*(.+)/", $text);

        if (preg_match("/(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/", $price, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $c->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $year = $this->re("/DEPARTURE DATE\s*\w+\s*\d{1,2}\,\s*(\d{4})/", $text);

        $segmentText = $this->re("/\n+^(\s*DATE\s*ITINERARY\s*{$this->addSpacesWord('ARRIVE')}\s*DEPART.+)/msu", $text);
        //Remove part text
        /*07                                      1010 S Federal Hwy
                                        Ste 1500
                                        Hallandale, FL 30009
        DATE     ITINERARY                 ARRIVE    DEPART*/
        $segmentText = preg_replace("/^\d{1,2}\s+.+\n(?:.+\n){1,5}\s+DATE\s*ITINERARY.+\n*/m", "", $segmentText);

        //added indent for all rows where no indent
        $indent = $this->re("/^[ ]+DATE\s*ITINERARY.+\n+(\s+)\d{1,2}\/\s*\d{1,2}\s+/", $segmentText);
        $segmentText = preg_replace("/^([ ]{1,5})(\d{1,2}\/\s*\d{1,2}\s+.+)/m", $indent . "$2", $segmentText);

        $shipTable = $this->splitCols($segmentText, [0, 50]);
        $ship = $this->re("/SHIP\s*\n(\S{2,}.*)/", $shipTable[0]);

        if (!empty($ship)) {
            $c->setShip($ship);
        }

        $segments = array_filter(preg_split("/(\n\n)/", $shipTable[1]));
        $countSegments = count($segments) - 1;

        foreach ($segments as $segment) {
            $segment = preg_replace("/^\n+/", "", $segment);

            if (preg_match("/((?:DATE|DAY AT SEA|SUEZ CANAL TRANSIT))/", $segment)
             || !preg_match("/^\n*[ ]+\d{1,2}\/\s*\d{1,2}\s+/s", $segment)) {
                $countSegments--;

                continue;
            }
            $countSegments--;

            //07/ 03   SEYMOUR NARROWS (BRITISH 09:00          18:00 => 07/ 03   SEYMOUR NARROWS (BRITISH   09:00          18:00
            $segment = preg_replace("/([A-Z])\s(\d+\:\d+\s+\d+\:\d+)/", "$1  $2", $segment);

            $segTable = $this->splitCols($segment);

            $s = $c->addSegment();

            $pointName = str_replace("\n", " ", $segTable[1]);

            if (preg_match("/(00:00)/", $segTable[3])) {
                $segTable[3] = '23:59';
            }

            $aboard = strtotime(str_replace([' ', "\n"], '', $segTable[0]) . '/' . $year . ', ' . $segTable[3]);
            $ashore = strtotime(str_replace([' ', "\n"], '', $segTable[0]) . '/' . $year . ', ' . $segTable[2]);

            if ($this->firstSegment === false) {
                $s->setName($pointName)
                    ->setAboard($aboard);
                $this->firstSegment = true;
            } elseif ($countSegments === 0) {
                $s->setName($pointName)
                    ->setAshore($ashore);
            } else {
                $s->setName($pointName)
                    ->setAshore($ashore)
                    ->setAboard($aboard);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseCruisePDF($email, $text);
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeCurrency($s)
    {
        $sym = [
            '€'          => 'EUR',
            'US dollars' => 'USD',
            '£'          => 'GBP',
            '₹'          => 'INR',
            'C$'         => 'CAD',
            '$'          => '$',
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function addSpacesWord($text): string
    {
        return preg_replace('/(\w)/u', '$1 *', preg_quote($text, '/'));
    }
}
