<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ETicketConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "atpi/it-11980112.eml, atpi/it-11980417.eml, atpi/it-11980591.eml, atpi/it-507097793.eml, atpi/it-516603120.eml, atpi/it-516970255.eml, atpi/it-8788899.eml, atpi/it-8926544.eml";

    private $reFrom = "@atpi.com";
    private $reSubject = [
        "en"=> "E Ticket Confirmation for:",
    ];
    private $reBody = 'ATPI';
    private $reBody2 = [
        "en"  => "Your travel itinerary",
        'en2' => 'Paid and reserved without extras by ATPI',
        'en3' => 'YOUR TRAVEL ITINERARY',
    ];
    //	private $pdfPattern = "(ATPI_Itinerary_[A-Z\d]+_lead_[A-Z\s]+_\d+-[A-Z]+-\d+.pdf|(?:Vsl|VSL) [A-Z\d- ]+.pdf)";
    private $pdfPattern = ".+\.pdf";

    private static $dictionary = [
        "en" => [],
    ];

    private $lang = "en";

    private $text;

    private $date;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }

        foreach ($pdfs as $pdf) {
            if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            foreach ($this->reBody2 as $lang=>$re) {
                if (strpos($this->text, $re) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
            $this->parsePdf($email);
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

    private function parsePdf(Email $email)
    {
        $text = $this->text;

        // Travel Agency
        if (preg_match_all("#ATPI Booking Reference\s+(.+)#", $text, $m)) {
            foreach (array_unique(array_filter($m[1])) as $conf) {
                $email->ota()
                    ->confirmation($conf);
            }
        }

        $travellersTable = $this->splitCols($this->re("#([ ]*Travellers.*?)\n\n\w+\,\s*\d+\s*\w*\s*\d{4}#s", $text));

        if (count($travellersTable) != 2) {
            $this->logger->debug("incorrect parse travellersTable");
        }

        $travellers = array_filter(explode("\n", $this->re("#Travellers\n(.+)#ms", $travellersTable[0] ?? '')));
        $travellers = preg_replace("/^\s*(MR|MRS|DR|PROF) /", "", $travellers);

        $segments = $this->split("#(?:\n\s*|^)([^\s\d]+, \d+ [^\s\d]+ \d{4})#", $this->re("#(?:YOUR TRAVEL ITINERARY|Your travel itinerary|Accommodation Voucher).*?\n\n(.*?)(?:IMPORTANT|REMARKS)#ms", $text));

        $airs = [];
        $hotels = [];
        $cars = [];

        foreach ($segments as $stext) {
            $this->logger->debug($stext);
            $this->logger->debug('-------------------------------------');

            if (stripos($stext, "Flight") !== false) {
                $airs[] = $stext;
            } elseif (strpos($stext, "Hotel") !== false) {
                $hotels[] = $stext;
            } elseif (strpos($stext, "Car type") !== false) {
                $cars[] = $stext;
            } elseif (strpos($stext, "Transfer") !== false) {
                continue;
            } else {
                $this->http->log("Unknown type ");

                return;
            }
        }

        if (count($airs) > 0) {
            $f = $email->add()->flight();
            $f->general()
                ->noConfirmation()
                ->travellers($travellers);

            $accounts = array_unique(array_map(function ($s) {
                if (preg_match("#- (\w+)#", $s, $m)) {
                    return $m[1];
                }
            }, array_filter(explode("\n", $this->re("#Frequent flyer numbers\n(.+)#ms", $travellersTable[1])))));

            if (count($accounts) > 0) {
                $f->program()
                ->accounts($accounts, false);
            }
        }

        $ticketNumbers = [];

        foreach ($airs as $stext) {
            $subj = $this->re("#\n([ ]*Booked For.+?)(?:\n *(?:\S ?)+:.+)?$#s", $stext);
            $bookedTable = $this->SplitCols($subj, $this->ColsPos($this->inOneRow($subj)));

            if (count($bookedTable) != 5 && !(count($bookedTable) == 5) && strpos($bookedTable[1], "Seat")) {
                $f->addSegment();
                $this->logger->debug("incorrect parse bookedTable");

                return;
            }
            $ticketNumbers = array_merge($ticketNumbers, array_filter(explode("\n", $this->re("#E-Ticket number\n(.+)#ms", $bookedTable[1]))));

            $this->logger->debug($this->re("#(.*?)\n#", $stext));
            $date = strtotime($this->normalizeDate($this->re("#(.*?)\n#", $stext)));

            $s = $f->addSegment();

            $s->airline()
                ->number($this->re("#Flight\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#", $stext))
                ->name($this->re("#Flight\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+#", $stext))
            ;
            $conf = $this->re("#Airline reference +([A-Z\d]{5,7})\n#", $stext);

            if (!empty($conf)) {
                $s->airline()
                    ->confirmation($conf);
            }

            $operator = $this->re("#OPERATED BY((?: \S+){1,7})(?:\s{2,}|\n)#", $stext);

            if (!empty($operator) && !preg_match("/^\s*SUBSIDIARY\s*\\/\s*FRANCHISE\s*$/", $operator)) {
                $s->airline()
                    ->operator($operator);
            }

            if (preg_match("#Departs\s+(?<Time>.*?)\s{2,}(?<Name>.*?)\s{2,}(?<Code>[A-Z]{3})\s{2,}(?<Terminal>(?:\w+ ){0,3}Terminal(?: \w+){0,3})?#", $stext, $m)) {
                $s->departure()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date(strtotime($this->normalizeDate($m['Time']), $date));

                if (!empty($m['Terminal'])) {
                    $s->departure()
                        ->terminal(trim(preg_replace("/\s*Terminal\s*/i", ' ', $m['Terminal'])));
                }
            }

            if (preg_match("#Arrives\s+(?<Time>\d+:\d+ (?:HRS|[ap]m))\s{2,}(?<Name>.*?)\s{2,}(?<Code>[A-Z]{3})\s{2,}(?<Terminal>(?:\w+ ){0,3}Terminal(?: \w+){0,3})?#", $stext, $m)) {
                $s->arrival()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date(strtotime($this->normalizeDate($m['Time']), $date));

                if (!empty($m['Terminal'])) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*Terminal\s*/i", ' ', $m['Terminal'])));
                }
            } elseif (preg_match("#\n\s*(?<Time>\d+:\d+ (?:HRS|[ap]m))\s*\n\s*Arrives\s+(?<Name>.*?)\s{2,}(?<Code>[A-Z]{3})\s{2,}(?<Terminal>(?:\w+ ){0,3}Terminal(?: \w+){0,3})?#", $stext, $m)
                || preg_match("#(?<Time>\d+:\d+ (?:HRS|[ap]m))\s*Flight\s*time\s*\/\s*miles\s*\d+:\d+ (?:HRS|[ap]m)\s*Arrives\s+\s{2,}(?<Name>.*?)\s{2,}(?<Code>[A-Z]{3})\s{2,}(?<Terminal>(?:\w+ ){0,3}Terminal(?: \w+){0,3})?#", $stext, $m)) {
                $s->arrival()
                    ->code($m['Code'])
                    ->name($m['Name'])
                    ->date(strtotime($this->normalizeDate($m['Time']), $date));

                if (!empty($m['Terminal'])) {
                    $s->arrival()
                        ->terminal(trim(preg_replace("/\s*Terminal\s*/i", ' ', $m['Terminal'])));
                }
            }

            $nextDay = $this->re("/Arrives.+\n\s*(next day)/", $stext);

            if (!empty($nextDay)) {
                $s->arrival()
                    ->date(strtotime('+1 day', $s->getArrDate()));
            }

            $pos = [0, strlen($this->re("#\n(.*?)Airline reference#", $stext))];
            $table = $this->splitCols($stext, $pos);
            $s->extra()
                ->aircraft(preg_replace("#\s+#", " ", trim(str_replace("Equipment", "", $this->re("#Stopover[^\n]+\n(.*?)Flight time#ms", $table[1])))), true)
                ->duration($this->re("#(?:Flight time \/ miles\s*|Flight time[ ]+)(.+)#", $stext), true, true)
                ->miles($this->re("#(?:Flight time \/ miles\s*).+(?:\n.*)?\n.* {5,}(\d+ miles)\n#", $stext), true, true);

            if (preg_match("/\n +(?:\S ?)+ {2,}((?:\S ?)+) {3,}Stopover/", $stext, $m)
                    && preg_match('#(Economy|Business)#i', $m[1])
            ) {
                if (preg_match("/^\s*([A-Z]{1,2})\s*\\/\s*(.+?)\s*$/", $m[1], $mt)) {
                    $s->extra()
                        ->cabin($mt[2])
                        ->bookingCode($mt[1])
                    ;
                } else {
                    $s->extra()
                        ->cabin($m[1])
                    ;
                }
            }

            $meal = $this->re("#Offered meal[ ]+(.+)#", $stext);

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $func = function ($s) {
                return preg_match("#^(\d+[A-Z])$#", $s);
            };

            if (count($bookedTable) == 5) {
                $seats = array_filter(explode("\n", $this->re("#Seat\n(.+)#ms", $bookedTable[2])),
                    $func);
            } else {
                $seats = array_filter(explode("\n", $this->re("#Seat\n(.+)#ms", $bookedTable[1])),
                    $func);
            }

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }

            // Stops
            if (!empty($this->re("#Stopover[ ]*(Non[ ]*-[ ]*stop)#i", $stext))) {
                $stops = 0;
            } else {
                $stops = $this->re("#Stopover[ ]+(\d+)\b#", $stext);
            }

            if ($stops !== null) {
                $s->extra()
                    ->stops($stops);
            }
        }

        if (!empty($ticketNumbers) && isset($f)) {
            $f->issued()
                ->tickets(array_unique($ticketNumbers), false);
        }

        if (!empty($airs)) {
            $priceText = $this->re("/\nTICKET INFORMATION\n([\s\S]+)\nREMARKS/", $text);

            if (!empty($priceText)) {
                $priceSegments = $this->split("/( {2,}Charges \([A-Z]{3})/", $priceText);
                $total = $cost = 0.0;
                $tax = 0.0;
                $fee = 0.0;

                foreach ($priceSegments as $pText) {
                    $t = preg_replace("/^(\d{3})-/", '$1', $this->re("/E-ticket Number *(\d[\d\-]{8,})\s+/", $pText));

                    if (in_array($t, $ticketNumbers)) {
                        $currency = $this->re("/\s+Charges \(([A-Z]{3})\)/", $pText);
                        $total += PriceHelper::parse($this->re("/ {3,}Total {3,}(.+)/", $pText), $currency);
                        $cost += PriceHelper::parse($this->re("/ {3,}Base Fare {3,}(.+)/", $pText), $currency);
                        $tax += PriceHelper::parse($this->re("/ {3,}Tax {3,}(.+)/", $pText), $currency);
                        $fee += PriceHelper::parse($this->re("/ {3,}Fee {3,}(.+)/", $pText), $currency);
                    }
                }

                if (!empty($total) && !empty($currency)) {
                    $f->price()
                        ->total($total)
                        ->currency($currency);
                }

                if (!empty($cost)) {
                    $f->price()
                        ->cost($cost);
                }

                if (!empty($tax)) {
                    $f->price()
                        ->tax($tax);
                }

                if (!empty($fee)) {
                    $f->price()
                        ->fee('Fee', $fee);
                }
            }
        }

        //#################
        //##   HOTELS   ###
        //#################
        foreach ($hotels as $htext) {
            $bookedTableText = $this->re("#([ ]*Booked for.+)#s", $htext);
            $bookedTableText = preg_replace("/^([\s\S]+?)\s*\n[ ]*Rate Booked\b.*/", '$1', $bookedTableText);
            $bookedTable = $this->SplitCols($bookedTableText, $this->ColsPos($bookedTableText));

            if (count($bookedTable) != 4) {
                $this->logger->debug("incorrect parse bookedTable");

                return;
            }

            $h = $email->add()->hotel();

            if (count($travellers) == 0) {
                $travellers[] = preg_replace('/\s+/', '', $this->re("#Booked for\n+(.+?)\s*$#s", $bookedTable[0]));
            }

            $h->general()
                ->confirmation($this->re("#Confirmation\n+(\w+)\b#", $bookedTable[1]))
                ->travellers($travellers, false);

            $h->hotel()
                ->name($this->re("#Hotel\s+(.*?)\s{2,}#", $htext))
                ->address($this->re("#Location\s+(.+)#", $htext))
                ->phone($this->re("#Phone[ ]+(.*?)\s{2,}#", $htext))
                ->fax($this->re("#Fax[ ]+(.+)#", $htext), true, true);

            $h->booked()
                ->checkIn(strtotime($this->normalizeDate($this->re("#Check in date\s+(.*?)\s{2,}#", $htext))))
                ->checkOut(strtotime($this->normalizeDate($this->re("#Check out date\s+(.*?)\s{2,}#", $htext))));

            if ($time = $this->re("#Check-in/out times:\s+IN-(\d+:\d+)\s*-\s*OUT-\d+:\d+#", $htext)) {
                $h->booked()
                    ->checkIn(strtotime($time, $h->getCheckInDate()));
            }

            if ($time = $this->re("#Check-in/out times:\s+IN-\d+:\d+\s*-\s*OUT-(\d+:\d+)#", $htext)) {
                $h->booked()
                    ->checkOut(strtotime($time, $h->getCheckOutDate()));
            }

            $h->booked()
                ->rooms($this->re("#Room\(s\)[ ]+(.+)#", $htext));

            $rate = $this->re("#Daily rates:[ ]+(.+)#i", $htext);

            if (empty($rate)) {
                $rate = $this->re("/Rate Booked\s+(.+) per room \\/ per night - Total Rate:/", $htext);
            }
            $rateType = $this->re("#Rate and Room Type[ ]+(.*?)\s+-\s+#", $htext);
            $roomType = $this->re("#Rate and Room Type[ ]+.*?-\s+(.+)#", $htext);

            if (!empty($rate) || !empty($rateType) || !empty($roomType)) {
                $room = $h->addRoom();

                if (!empty($rate)) {
                    $room->setRate($rate);
                }

                if (!empty($rateType)) {
                    $room->setRateType($rateType);
                }

                if (!empty($roomType)) {
                    $room->setType($roomType);
                }
            }

            if (preg_match("/Rate Booked\s+(?:daily rates:.+\n *)?.* - Total Rate: ([A-Z]{3}) ?(\d.+?)(?:\s+tax|\n|$)/", $htext, $m)
                || preg_match("/Rate Booked\s+(?:daily rates:.+\n *)?([A-Z]{3}) \d.* - Total Rate: (\d.+?)(?:\s+tax|\n|$)/", $htext, $m)
            ) {
                $h->price()
                    ->total(PriceHelper::parse($m[2], $m[1]))
                    ->currency($m[1])
                ;

                if (preg_match("/\n *Taxes: *[A-Z]{3} ?(\d.+)\n/", $htext, $mt)
                    || preg_match("/\n *Taxes: ?(\d.+)\n/", $htext, $mt)
                ) {
                    $h->price()
                        ->tax(PriceHelper::parse($mt[1], $m[1]))
                    ;
                }
            }
        }

        foreach ($cars as $car) {
            $c = $email->add()->rental();

            $travellerText = $this->re("/(Booked For\s+Confirmation.+Membership information\n(?:.+\n){1,3})Rate booked/", $car);

            $travellerTable = $this->SplitCols($travellerText);

            $c->general()
                ->traveller($this->re("/Booked For\n(.+)/", $travellerTable[0]))
                ->confirmation($this->re("/Confirmation\n([A-Z\d]+)\n*\s*$/", $travellerTable[1]));

            $account = $this->re("/Membership information\n([A-Z\d]+)\n*\s*$/", $travellerTable[3]);

            if (!empty($account)) {
                $c->addAccountNumber($account, false);
            }

            if (preg_match("/Pick up\s*Date\s*(?<depDate>.+\d{4})\s+Time\s+(?<depTime>[\d\:]+)\s+HRS/", $text, $m)) {
                $c->pickup()
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
            }

            if (preg_match("/Drop\s*Off\s*Date\s*(?<depDate>.+\d{4})\s+Time\s+(?<depTime>[\d\:]+)\s+HRS/", $text, $m)) {
                $c->dropoff()
                    ->date(strtotime($m['depDate'] . ', ' . $m['depTime']));
            }

            $company = $this->re("/Car Vendor\s*(.+\S)\s+Car/", $text);

            if (!empty($company)) {
                $c->setCompany($company);
            }

            if (preg_match("/Pick up.+\n+\s*City(?:.+\n){1,2}\s*Address\n*\s*(?<address>.+)\s+coordinates\:.+\n*\s*Phone\s*(?<phone>[\d\s]+)\n/", $text, $m)
            || preg_match("/Pick up.+\n+\s*City(?:.+\n){1,2}\s*Address\n*\s*(?<address>.+)\s+coordinates\:/", $text, $m)) {
                $c->pickup()
                    ->location($m['address']);

                if (isset($m['phone']) && !empty($m['phone'])) {
                    $c->pickup()
                        ->phone($m['phone']);
                }
            }

            if (preg_match("/Drop Off.+\n+\s*City(?:.+\n){1,2}\s*Address\n*\s*(?<address>.+)\s+coordinates\:.+\n*\s*Phone\s*(?<phone>[\d\s]+)\n/", $text, $m)
            || preg_match("/Drop Off.+\n+\s*City(?:.+\n){1,2}\s*Address\n*\s*(?<address>.+)\s+coordinates\:/", $text, $m)) {
                $c->dropoff()
                    ->location($m['address']);

                if (isset($m['phone']) && !empty($m['phone'])) {
                    $c->dropoff()
                        ->phone($m['phone']);
                }
            }

            if (preg_match("/Total\s*(?<currency>[A-Z]{3})\s+(?<total>[\d\.\,']+)/", $text, $m)) {
                $c->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
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
        //	    $this->logger->info($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", // Sunday, 08 October 2017
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})\s*\-\s*\w+\s+(\d+\s*[AP]M) hrs$#", // Tuesday, 14 August 2018 - after 2PM hrs
            "#^(\d+:\d+) HRS$#", //18:00 HRS
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4}) - (?:after|before) (\d{2})(\d{2}) hrs$#", //Sunday, 08 October 2017 - after 1500 hrs
            '/^[^\s\d]+, (\d+ [^\s\d]+ \d{4})\s*\-\s*\w+\s+(\d+)\s*N hrs$/',
        ];
        $out = [
            "$1",
            "$1, $2",
            "$1",
            "$1, $2:$3",
            '$1, $2:00',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

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
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
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
