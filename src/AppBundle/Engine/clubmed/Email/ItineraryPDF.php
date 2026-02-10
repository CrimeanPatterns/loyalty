<?php

namespace AwardWallet\Engine\clubmed\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "clubmed/it-191784960.eml, clubmed/it-194083238.eml, clubmed/it-457730208.eml, clubmed/it-461592459.eml";
    public $subjects = [
        'Your Club Med',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $lastHotelName = '';
    public $lastCheckIn = '';
    public $guests;
    public $kids;
    public $travellers = [];

    public static $dictionary = [
        "en" => [
            'Total price'  => ['Total price', 'Total Cost'],
            'Booking file' => ['Booking file', 'Booking file number', 'File number'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@infos.clubmed.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
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

            if (strpos($text, 'CLUB MED') !== false && strpos($text, 'PARTICIPANT') !== false && strpos($text, 'RESORT') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]infos\.clubmed\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Booking file[\s#]+(\d{5,})/", $text), 'Booking number')
            ->travellers($this->travellers, true);

        if (preg_match_all("/E-Ticket numbers\:\s*([\d\-\,\s]{14,})/", $text, $match)) {
            foreach ($match[1] as $ticketsText) {
                $f->setTicketNumbers(array_filter(explode(' ,', trim($ticketsText))), false);
            }
        }

        if (preg_match_all("/\n*(\s*\d+\/\d+\/\d{4}\s*[A-Z\d]{2}\s*\d{2,4}\s*[A-Z]{3}\s*[\d\:]+[ ]{5,}.+[ ]{20,}.+[ ]{15,}\d+\/\d+\/\d{4}\s*[\d\:]+\s*[A-Z]{1,}\n*)/", $text, $m)) {
            foreach ($m[1] as $flight) {
                if (preg_match("/\s*(?<depDate>\d+\/\d+\/\d{4})\s*(?<airName>[A-Z\d]{2})\s*(?<flightNumber>\d{2,4})\s*(?<class>[A-Z]{3})\s*(?<depTime>[\d\:]+)[ ]{5,}(?<depName>[A-Z].+)[ ]{10,}(?<arrName>[A-Z].+)[ ]{10,}(?<arrDateTime>\d+\/\d+\/\d{4}\s*[\d\:]+)\s*[A-Z]{1,}\n*/", $flight, $match)) {
                    $s = $f->addSegment();

                    $s->airline()
                        ->name($match['airName'])
                        ->number($match['flightNumber']);

                    $s->departure()
                        ->name($match['depName'])
                        ->date(strtotime($match['depDate'] . ' ' . $match['depTime']))
                        ->noCode();

                    $s->arrival()
                        ->name($match['arrName'])
                        ->date(strtotime($match['arrDateTime']))
                        ->noCode();

                    $s->extra()
                        ->cabin($match['class']);
                }
            }
        }
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        $hotelText = $this->re("/\n\s*RESORT\n+(.+)\n[-]+\n\s*(?:SERVICES|TRANSPORT|EXTERNAL ACCESSORIES|Travel services paid but not received may be reimbursed by the Compensation)\s*\n/su", $text);

        if (empty($hotelText)) {
            $hotelText = $this->re("/\n\s*RESORT\n+(.+)\n[-]+\n\s*{$this->opt($this->t('Total price'))}/su", $text);
        }

        $hotesArray = array_filter(explode("\n", $hotelText));

        foreach ($hotesArray as $hotel) {
            if (preg_match("/^\s*(?<hotelName>.+)\s+(?<checkIn>\d+.*\d{4})\s*(?<checkOut>\d+.*\d{4})[ ]{10,}(\d+)\s*(?<roomDesc>.+)\s*(?:Occupancy\s*\:|Occupation)\s*(?<guests>\d+)\s*(?:people|person)\s+[A-Z]+$/u", $hotel, $m)
                || preg_match("/^\s*(?<hotelName>.+)\s+(?<checkIn>\d+\/\d+\/\d{4})\s*(?<checkOut>\d+\/\d+\/\d{4})[ ]{10,}(\d+)\s*(?<roomDesc>.+)\s+[A-Z]+$/u", $hotel, $m)) {
                if ($this->lastHotelName !== trim($m['hotelName']) && $this->lastCheckIn !== $m['checkIn']) {
                    $h = $email->add()->hotel();

                    $h->general()
                        ->confirmation($this->re("/{$this->opt($this->t('Booking file'))}[\s\#\:]*(\d{5,})/", $text), 'Booking number')
                        ->travellers($this->travellers, true);

                    $h->hotel()
                        ->name('CLUB MED ' . trim($m['hotelName']))
                            ->noAddress();

                    $inTime = $this->re("/CHECK IN \/ CHECK OUT TIMES You are welcomed to check in between\s*([\d\:]+\s*a?p?m)/", $text);

                    if (empty($inTime)) {
                        $inTime = $this->re("/You are welcome to check-in at the resort after\s*([\d\:h]+\s*a?p?m)/u", $text);
                        $inTime = str_replace('h', ':', $inTime);
                    }
                    $outTime = $this->re("/you will have to check out of your room by\s*([\d\:]+\s*a?p?m)/", $text);

                    if (empty($outTime)) {
                        $outTime = '12:00';
                    }

                    $dateArray = $this->DateFormat($m['checkIn'], $m['checkOut']);

                    if (!empty($inTime) && !empty($outTime)) {
                        $h->booked()
                            ->checkIn(strtotime($dateArray[0] . ' ' . $inTime))
                            ->checkOut(strtotime($dateArray[1] . ' ' . $outTime));
                    } else {
                        $h->booked()
                            ->checkIn(strtotime($dateArray[0]))
                            ->checkOut(strtotime($dateArray[1]));
                    }

                    $h->booked()
                        ->guests($this->guests);

                    if (!empty($this->kids)) {
                        $h->booked()
                            ->kids($this->kids);
                    }

                    $account = $this->re("/Membership number[\s\:]*(\d{5,})/", $text);

                    if (!empty($account)) {
                        $h->addAccountNumber($account, false);
                    }

                    $this->lastCheckIn = trim($m['checkIn']);
                    $this->lastHotelName = trim($m['hotelName']);
                }

                if (isset($h) && trim($m['roomDesc']) !== 'CLUB MED') {
                    $room = $h->addRoom();

                    $room->setDescription($m['roomDesc']);
                }
            }
        }
    }

    public function ParseFerryPDF(Email $email, $text)
    {
        if (preg_match_all("/^ *(\d+\/\d+\/\d{4} +[A-Z\d]+ ?[A-Z\d]+ +\D+ +[\d\:]+[ ]{5,}.+[ ]{5,}.+[ ]{5,}\d+\/\d+\/\d{4} +[\d\:]+ *[A-Z]+)$/m", $text, $m)) {
            $f = $email->add()->ferry();

            $f->general()
                ->confirmation($this->re("/{$this->opt($this->t('Booking file'))}[\s\#\:]+(\d{5,})/", $text), 'Booking number')
                ->travellers($this->travellers, true);

            $f->setAllowTzCross(true);

            foreach ($m[1] as $ferry) {
                if (preg_match("/\s*(?<depDate>\d+\/\d+\/\d{4})\s*(?<carrier>[A-Z\d]+)\s+(?<vessel>[A-Z\d]+)\s*(?<cabin>\D+)\s+(?<depTime>[\d\:]+)[ ]{5,}(?<depName>\S.+)[ ]{15,}(?<arrName>\S.+)[ ]{10,}(?<arrDate>\d+\/\d+\/\d{4})\s*(?<arrTime>[\d\:]+)\s*[A-Z]+/u", $ferry, $match)) {
                    $s = $f->addSegment();

                    $s->setCarrier($match['carrier']);

                    $s->setVessel($match['vessel']);

                    $dateArray = $this->DateFormat($match['depDate'], $match['arrDate']);

                    $s->departure()
                        ->name($match['depName'])
                        ->date(strtotime($dateArray[0] . ' ' . $match['depTime']));

                    $s->arrival()
                        ->name($match['arrName'])
                        ->date(strtotime($dateArray[1] . ' ' . $match['arrTime']));
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $fileName = $this->getAttachmentName($parser, $pdf);

            if (stripos($fileName, 'agent')) {
                continue;
            }
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $guestsText = $this->re("/PARTICIPANT\n(\s*\D+FULL NAME.+[-]{10,})\n+\s*STAY/su", $text);
            $guestTable = $this->SplitCols($guestsText, [0, 50]);

            if (preg_match_all("/\n[A-Z][ ]{5,}([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])/", $guestTable[0], $m)) {
                $this->travellers = $m[1];
            }

            if (preg_match_all("/\n[A-Z][ ]{5,}([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])\s*\d+\/\d+\/\d{4}/", $guestTable[0], $m)) {
                $this->kids = count($m[1]);
            }

            if (!empty($this->kids)) {
                $this->guests = count($this->travellers) - $this->kids;
            } else {
                $this->guests = count($this->travellers);
            }

            if (preg_match("/{$this->opt($this->t('Total price'))}\s*(?<currency>\D) ?(?<total>\d[\d\.\,]*)/u", $text, $m)
            || preg_match("/{$this->opt($this->t('Total price'))}\s*(?<currency>[A-Z]{3})\s*(?<total>\d[\d\.\,]*)/u", $text, $m)) {
                $this->logger->debug($m['currency']);

                if (trim($m['currency']) == '$') {
                    $currency = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Amount of ')]/ancestor::tr[1]", null, true, "/\s([A-Z]{3})$/");

                    if (!empty($currency)) {
                        $m['currency'] = $currency;
                    }
                }

                $email->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            } else {
                $totalText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total price')]/ancestor::tr[1]/descendant::td[normalize-space()][2]");

                if (empty($totalText)) {
                    $totalText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Total price')]/ancestor::tr[1]",
                        null, true, "/:\s*(.+)/");
                }

                if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})$/", $totalText, $match)) {
                    $email->price()
                        ->total(PriceHelper::parse($match['total'], $match['currency']))
                        ->currency($match['currency']);
                }
            }

            if (stripos($text, 'RESORT') !== false) {
                $this->ParseHotelPDF($email, $text);
            }

            if (strpos($text, 'Ferry terminal') !== false) {
                // !! case is important
                $this->ParseFerryPDF($email, $text);
            }

            if (stripos($text, 'flight Nr:') !== false) {
                $this->ParseFlightPDF($email, $text);
            }
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
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

    private function normalizeDate($str)
    {
        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
            "#^(\d+)\/(\d+)\/(\d{4})$#", //18/12/2022
            "#^(\d+)\/(\d+)\/(\d{4})\,?\s*([\d\:]+\s*a?p?m?)$#i", //18/12/2022 14:00
        ];
        $out = [
            "$1 $2 $3",
            "$1.$2.$3",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }

    private function DateFormat($dateIN, $dateOut)
    {
        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateIN, $m)) {
            $dateIN = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateIN));
        }

        if (preg_match("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", $dateOut, $m)) {
            $dateOut = str_replace(" ", "", preg_replace("#^(\d{1,2}[\/\.\-]\d{1,2}[\.\/\-])(\d{2})$#", "$1 " . "20$2", $dateOut));
        }

        if ($this->identifyDateFormat($dateIN, $dateOut) === 1) {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$2.$1.$3", $dateOut);
        } else {
            $dateIN = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateIN);
            $dateOut = preg_replace("#(\d+)[\/\.](\d+)[\/\.](\d+)$#", "$1.$2.$3", $dateOut);
        }

        return [$dateIN, $dateOut];
    }

    private function identifyDateFormat($date1, $date2)
    {
//    define("DATE_MONTH_FIRST", "1");
//    define("DATE_DAY_FIRST", "0");
//    define("DATE_UNKNOWN_FORMAT", "-1");
        if (preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date1, $m) && preg_match("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $date2, $m2)) {
            if (intval($m[1]) > 12 || intval($m2[1]) > 12) {
                return 0;
            } elseif (intval($m[2]) > 12 || intval($m2[2]) > 12) {
                return 1;
            } else {
                //try to guess format
                $diff = [];

                foreach (['$3-$2-$1', '$3-$1-$2'] as $i => $format) {
                    $tempdate1 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date1);
                    $tempdate2 = preg_replace("#(\d{1,2})[\/\.\-](\d{1,2})[\.\/\-](\d{4})#", $format, $date2);

                    if (($tstd1 = strtotime($tempdate1)) !== false && ($tstd2 = strtotime($tempdate2)) !== false && ($tstd2 - $tstd1 > 0)) {
                        $diff[$i] = $tstd2 - $tstd1;
                    }
                }
                $min = min($diff);

                return array_flip($diff)[$min];
            }
        }

        return -1;
    }
}
