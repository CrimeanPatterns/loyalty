<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It19 extends \TAccountChecker
{
    public $mailFiles = "bcd/it-1670840.eml, bcd/it-19.eml";

    public $detectBody = [
        "en" => ["Prepared for"],
    ];
    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private static $detectHeaders = [
        'bcd' => [
            'from' => ['@bcdtravel'],
            'subj' => [
                'Client Statement for',
            ],
        ],
        'amextravel' => [
            'from' => ['amexgbt@tandemtravel.co.nz'],
            'subj' => [
                'Client Statement for',
            ],
        ],
    ];
    private $pdfNamePattern = ".*\.pdf";

    private $code;
    private $detectCompany = [
        'bcd' => [
            'BCD Travel',
        ],
        'amextravel' => [
            'amexgbt@tandemtravel',
        ],
    ];
    private $text;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->detectBody as $lang => $reBody2) {
                foreach ($reBody2 as $re) {
                    if (strpos($this->text, $re) !== false) {
                        $this->lang = substr($lang, 0, 2);

                        if (!$this->parsePdf($email)) {
                            $this->logger->info("parsePdf is failed'");

                            return null;
                        }
                        $email->setProviderCode($this->getProvider($parser, $this->text));

                        break;
                    }
                }
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$detectHeaders as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectHeaders as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $text = '';

        foreach ($pdfs as $pdf) {
            if (($text .= \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }
        }

        $code = $this->getProvider($parser, $text);

        if (empty($code)) {
            return false;
        }

        foreach ($this->detectBody as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($text, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectHeaders);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePdf(Email $email)
    {
        $segmentsText = $this->text;
        $posTicket = stripos($segmentsText, 'Ticket Numbers:');

        if (!empty($posTicket)) {
            $segmentsText = substr($segmentsText, 0, $posTicket);
            $ticketsText = substr($this->text, $posTicket);
        }

        $chargesText = $this->re("#\n\s*Other Charges\s*\n\s*Service[ ]+Currency.*([\s\S]+?)\n\n\n\n\n#", $this->text);
        $charges = $this->split("#(?:^|\n)([ ]*.+[ ]{2,}[A-Z]{3}[ ]{2,}.*\d)#", $chargesText);

        foreach ($charges as $value) {
            $table = $this->SplitCols($value);
            $prices[preg_replace('#\s+#', ' ', trim(array_shift($table)))] = implode(' ', $table);
        }

        $travellers = [];
        $tickets = [];

        if (!empty($ticketsText)) {
            $ticketsText = $this->re("#Ticket Numbers:\s+([\s\S]+?)\n[ ]*Costs\s*\n#", $ticketsText);
            $ticketsRow = array_filter(explode("\n", $ticketsText));

            foreach ($ticketsRow as $key => $row) {
                if (preg_match("#^[ ]*(?<traveller>\S[A-Z/. \-]+)[ ]{2,}(?<ticket>\d{3}[ ]?\d{5,})[ ]{2,}(?<al>.+?) (?<route>[A-Z]{3}(?:/[A-Z]{3})+)#", $row, $m)) {
                    $travellers[] = $m['traveller'];
                    $tickets[strtolower($m['al'])]['ticketNum'][] = $m['ticket'];
                    $tickets[strtolower($m['al'])]['route'][] = $m['route'];
                }
            }
        }
        $travellers = array_unique($travellers);

        if (empty($travellers)) {
            $travellers = array_filter([$this->re("#\n\s*ITINERARY PREPARED FOR:[ ]*([A-Z/ \-\.]+?)(?:[ ]{2,}|\n)#", $this->text)]);
        }

        // Travel Agency
        $email->ota()
            ->confirmation(re("#PNR\s+Ref:[ ]*([A-Z\d]+)#", $this->text), 'PNR Ref')
            ->confirmation(re("#Booking \\#:[ ]*([A-Z\d]+)#", $this->text), 'Booking #');

        // AIR Segments
        $segments = $this->split("#(\n[ ]*\w+[ ]+\d+[ ]+\w+[ ]+\d{4}[ ]*\-[ ]*(?:ACCOMMODATION|FLIGHT|RENTAL CAR))#", $segmentsText);

        $airs = [];
        $hotels = [];
        $rentals = [];

        foreach ($segments as $value) {
            if (preg_match("#\s*.*-[ ]*FLIGHT#", $value)) {
                $al = strtolower($this->re("#Depart .+? on (.+?) flight [A-Z\d]{2}#", $value));
                $airs[$al][] = $value;
            }

            if (preg_match("#\s*.*-[ ]*ACCOMMODATION#", $value)) {
                $hotels[] = $value;
            }

            if (preg_match("#\s*.*-[ ]*RENTAL CAR#", $value)) {
                $rentals[] = $value;
            }
        }

        foreach ($airs as $al => $aSegments) {
            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation()
                ->travellers($travellers)
            ;

            // Issued
            if (!empty($tickets[$al]['ticketNum'])) {
                $f->issued()->tickets($tickets[$al]['ticketNum'], false);
            } elseif (count($tickets) == 1 && count($airs) == 1) {
                $f->issued()->tickets(current($tickets)['ticketNum'], false);
            }

            // Segments
            foreach ($aSegments as $sText) {
                $s = $f->addSegment();

                $date = $this->re("#^\s*\w+\s+(\d+\s+\w+\s+\d{4})\s*\-\s*#", $sText);

                // Airline
                $s->airline()
                    ->name($this->re("#flight\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d{1,5}\s+#", $sText))
                    ->number($this->re("#flight\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d{1,5})\s+#", $sText))
                    ->operator($this->re("#Service operated by (.+)#", $sText), true, true)
                ;
                $conf = $this->re("#Airline Reference[ ]*:[ ]*([A-Z\d]{5,7})#", $sText);

                if (!empty($conf)) {
                    $s->airline()
                        ->confirmation($conf)
                    ;
                }

                // Departure
                $s->departure()
                    ->name($this->re("#\s+Depart[: ]+([ A-Z,.\-]+?)\s*on\s*#", $sText))
                    ->date($this->normalizeDate($date . ' ' . $this->re("#\n\s*(\d+:\d+)[ ]*Depart#", $sText)))
                    ->terminal(trim(preg_replace("#\s*terminal\s*#i", ' ', $this->re("#\s+Depart[ :]+(?:.*\n+){1,3}\s*Terminal:[ ]*(.+)#", $sText))), true, true)
                ;

                // Arrival
                $s->arrival()
                    ->name($this->re("#\s+Arrive[: ]+([ A-Z,.\-]+)\s+#", $sText))
                    ->date($this->normalizeDate($date . ' ' . $this->re("#\n\s*(\d+:\d+)[ ]*Arrive#", $sText)))
                    ->terminal(trim(preg_replace("#\s*terminal\s*#i", ' ', $this->re("#\s+Arrive[ :]+(?:.*\n+){1,3}\s*Terminal:[ ]*(.+)#", $sText))), true, true)
                ;

                if (count($aSegments) == 1 && !empty($tickets[$al]['route'])
                        && preg_match("#^\s*([A-Z]{3})/([A-Z]{3})\s*$#", $tickets[$al]['route'])) {
                    $s->departure()->code($m[1]);
                    $s->arrival()->code($m[2]);
                } else {
                    $s->departure()->noCode();
                    $s->arrival()->noCode();
                }

                // Extra
                $s->extra()
                    ->aircraft($this->re("#\n\s*Aircraft[ ]*:[ ]*(.+)#", $sText))
                    ->miles($this->re("#\n\s*Distance[ ]*:[ ]*(.+)#", $sText))
                    ->cabin($this->re("#flight\s+[A-Z]{2}[ ]*\d{1,5}\s+(.+?)(\s*-\s*[A-Z]{1,2})?\s+Confirmed#", $sText), true, true)
                    ->bookingCode($this->re("#flight\s+[A-Z]{2}[ ]*\d{1,5}\s+.+?\s*-\s*([A-Z]{1,2})\s+Confirmed#", $sText), true, true)
                    ->duration($this->re("#\n\s*Flying[ ]+time[ ]*:[ ]*(.+)#", $sText))
                ;

                $seatsText = $this->re("#\n\s*Seat\(s\)[ ]*:((?:\s*\d{1,3}[A-Z]\b[^:]*)+)#", $sText);

                if (preg_match_all("#^\s*(\d{1,3}[A-Z])\b#m", $seatsText, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }
        }

        // HOTELS Segments
        foreach ($hotels as $sText) {
            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($this->re("#\n\s*Confirmation[\#: ]+([\d\w\-]+)#", $sText))
                ->travellers($travellers)
                ->status($this->re("#\n\s*Status[ ]*:[ ]*(.+)#", $sText))
                ->cancellation($this->re("#\n\s*Cancellation Policy[ ]*:[ ]*(.+)#", $sText))
            ;
            // Hotel
            if (preg_match("#ACCOMMODATION\s*\n\s*(.+)\n+([\s\S]*?)\n\s*+Telephone#", $sText, $m)) {
                $h->hotel()
                    ->name($m[1])
                    ->address(preg_replace("#\s*\n\s*#", ', ', trim($m[2])))
                ;
            }
            $h->hotel()
                ->phone($this->re("#\n\s*Telephone[ ]*\(.*?\):[ ]*(.+)#", $sText));

            // Booked
            $h->booked()
                ->checkIn($this->normalizeDate($this->re("#\n\s*Check\-in:[ ]*(.+)#", $sText)))
                ->checkOut($this->normalizeDate($this->re("#\n\s*Check\-out:[ ]*(.+)#", $sText)))
                ->rooms($this->re("#\n\s*Number of rooms[ ]*:[ ]*(\d+)\D#", $sText))
            ;

            // Rooms
            $h->addRoom()
                ->setRate($this->re("#\n\s*Rate[ ]*:[ ]*([^\n]+)#", $sText))
                ->setRateType(preg_replace("#\s*\n\s*#", ', ', trim($this->re("#\n\s*Service[ ]*:[ ]*(.+(?:\n[ ]{20,}[^:]+))\n#", $sText))))
            ;

            if (!empty($prices) && !empty($h->getHotelName())) {
                foreach ($prices as $key => $value) {
                    if (preg_match("#^\s*[A-Z]{3}\s*" . $h->getHotelName() . "\s*$#i", $key)) {
                        if (preg_match("#^\s*([A-Z]{3})\s+\S*\d\S*\s+\S*\d\S*\s+(\S*\d\S*)\s+#", $value, $m)) {
                            $h->price()
                                ->total($this->amount($m[2]))
                                ->currency($m[1])
                            ;
                        }
                        unset($prices[$key]);

                        break;
                    }
                }
            }
        }

        // RENTALS Segments
        foreach ($rentals as $sText) {
            $r = $email->add()->rental();

            // General
            $r->general()
                ->confirmation($this->re("#\n\s*Confirmation[\#: ]+([\d\w\-]+)#", $sText))
                ->travellers($travellers)
                ->status($this->re("#\n\s*Status[ ]*:[ ]*(.+)#", $sText))
            ;

            // Pick Up
            $r->pickup()
                ->location($this->re("#\s+Pickup:[ ]*(.+)#", $sText))
                ->date($this->normalizeDate($this->re("#(.+\n.+)Pickup:[ ]*#", $sText)))
            ;

            // Drop Off
            $r->dropoff()
                ->location($this->re("#\s+Dropoff:[ ]*(.+)#", $sText))
                ->date($this->normalizeDate($this->re("#(.+\n.+)Dropoff:[ ]*#", $sText)))
            ;

            // Car
            $r->car()
                ->type($this->re("#\n\s*Service:[ ]*(.+)#", $sText))
            ;

            // Extra
            $r->extra()
                ->company($this->re("# - RENTAL CAR\s+(.+)#", $sText))
            ;

            if (!empty($prices) && !empty($r->getCompany())) {
                foreach ($prices as $key => $value) {
                    if (preg_match("#^\s*[A-Z]{3}\s*" . $r->getCompany() . "\s*$#i", $key)) {
                        if (preg_match("#^\s*([A-Z]{3})\s+\S*\d\S*\s+\S*\d\S*\s+(\S*\d\S*)\s+#", $value, $m)) {
                            $r->price()
                                ->total($this->amount($m[2]))
                                ->currency($m[1])
                            ;
                        }
                        unset($prices[$key]);

                        break;
                    }
                }
            }
        }

        return $email;
    }

    private function getProvider(PlancakeEmailParser $parser, $text)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach ($this->detectCompany as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (strpos($text, $search) !== false) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->http->log('$str = '.print_r( $str,true));
        $in = [
            "#^\s*(\d+:\d+)\s+[^\d\s]+\s+(\d{1,2})\s+([^\s\d]+)\s+(\d{4})\s*(\(.+\))?\s*$#", //10:00 Wednesday 21 May 2014 (3 night/s)
        ];
        $out = [
            "$2 $3 $4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return strtotime($str);
    }

    private function split($re, $text, $shiftFirst = true)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            if ($shiftFirst == true || ($shiftFirst == false && empty($r[0]))) {
                array_shift($r);
            }

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        $s = trim(str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s|\d)#", $s)) {
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
