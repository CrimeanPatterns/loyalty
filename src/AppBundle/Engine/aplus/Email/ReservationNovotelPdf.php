<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationNovotelPdf extends \TAccountChecker
{
    public $mailFiles = "aplus/it-60836858.eml";

    public $reFrom = "@eu.exide.com";
    public $reSubject = [
        "en" => ["Reservation Novotel Poznan Centrum", "Booking confirmation"],
        "de" => "Zimmerreservierung",
    ];
    public $reBody = ['Novotel', 'NOVOTEL'];
    public $reBody2 = [
        "en"=> "Arrival",
        "de"=> "Anreise",
    ];
    public $pdfPattern = ".*\.pdf";

    public static $dictionary = [
        "en" => [
            'Reservation no.' => ['Reservation no.', 'Confirmation No.'],
            'Name'            => ['Name', 'Guest name'],
            'Arrival'         => ['Arrival Date', 'Arrival'],
            'Departure'       => ['Departure Date ', 'Departure'],
        ],

        "de" => [
            "Reservation no." => "Reservierungsbestätigung",
            "Arrival"         => "Anreise",
            "Departure"       => "Abreise",
            "Name"            => "Gastname",
            "Number of rooms" => "Zimmer",
            "Room rate"       => "Preis",
        ],
    ];

    public $lang = "en";

    public function parsePdf(Email $email)
    {
        $text = $this->text;
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation(trim($this->re("#{$this->opt($this->t('Reservation no.'))}\s*:?\s*(.+)#", $text), '#'))
            ->travellers(array_filter([$this->re("#{$this->opt($this->t('Name'))}\s*:?\s*(.+)#", $text)]));

        $address = $this->re("#(?:WELCOME|NOVOTEL)[\s-]+[^\n]+\n\s*([\s\S]+)Tel#ms", $text);

        if (empty($address)) {
            $address = $this->re("#(?:WELCOME|NOVOTEL)[\s-]+[^\n]+\n\s*([^\n]+)#ms", $text);
        }

        $phone = $this->re("#\s(?:TEL|Tel).\s*(.+)\s*[-]#", $text);

        if (empty($phone)) {
            $phone = $this->re("#\s+(?:TEL|Tel)\s?([\)\(\s\d\-]+)#", $text);
        }

        $h->hotel()
            ->name($this->re("#(?:WELCOME|NOVOTEL)[\s-]+([^\n]+)#ms", $text))
            ->address(str_replace("\n", " ", $address))
            ->phone($phone);

        $fax = $this->re("#(?:FAX|Fax)\s*:\s*(.+)#", $text);

        if (empty($fax)) {
            $fax = $this->re("#(?:FAX|Fax)\s?([\)\(\s\d\-]+)#", $text);
        }

        if (!empty($fax)) {
            $h->hotel()
                ->fax($fax);
        }

        $h->booked()
            ->checkIn(strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Arrival'))}\s*:?\s*(.+)#", $text) . ', ' . $this->re("#Check in time starts at\s+(\d+:\d+\s*[AP]M)#", $text))))
            ->checkOut(strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Departure'))}\s*:?\s*(.+)#", $text))));

        $rooms = $this->re("#{$this->opt($this->t('Number of rooms'))}\s*:?\s*(\d+)\s+#", $text);

        if (!empty($rooms)) {
            $h->booked()
                ->rooms($rooms);
        }

        $cancellationPolicy = $this->re("/Cancellation and non-arrival policy\:([\s\S]+)\s+Additional airport transfers:/ms", $text);

        if (!empty($cancellationPolicy)) {
            $h->general()
                ->cancellation(str_replace("\n", " ", $cancellationPolicy));
        }

        $roomTypeDescription = $this->re("#{$this->opt($this->t('Number of rooms'))}\s*:?\s*\d+\s+/\s+(.+)#", $text);

        if (empty($roomTypeDescription)) {
            $roomTypeDescription = $this->re("#{$this->opt($this->t('Number of rooms'))}\s*:?\s*\d+\s+(.+)#", $text);
        }

        if (empty($roomTypeDescription)) {
            $roomTypeDescription = $this->re("#{$this->opt($this->t('Room Type'))}\s*(.+)#", $text);
        }

        if (!empty($roomTypeDescription)) {
            $room = $h->addRoom();
            $room->setDescription($roomTypeDescription);

            $rate = $this->re("#{$this->opt($this->t('Room rate'))}\s*:?\s*(.+)#", $text);

            if (!empty($rate)) {
                $room->setRate($rate);
            }
        }

        $total = $this->re("#Total cost of stay\s*:\s*([\d\,\.]+)#", $text);

        if (!empty($total)) {
            $h->price()
                ->total($total);
        }

        $currency = $this->re("#Total cost of stay\s*:\s*[\d\,\.]+\s+([A-Z]{3})#", $text);

        if (!empty($currency)) {
            $h->price()
                ->total($currency);
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody[0]) === false && strpos($text, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $this->parsePdf($email);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-(\d+)-(\d{2})$#", //21-08-17
            "#^(\d+)-(\d+)-(\d{2}), (\d+:\d+\s*[AP]M)$#", //21-08-17, 2:00 PM
            "#^(\d+)-(\d+)-(\d{2})\s+\(.*?\)$#", //24-08-17 (3 nights)
            "#^(\d{1,2})[.]\s+(\w+)\s+(\d{4})#", //11. Mai 2020
        ];
        $out = [
            "$1.$2.20$3",
            "$1.$2.20$3, $4",
            "$1.$2.20$3",
            "$1 $2 $3",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,\s](\d{3})#", "$1", $s));
    }
}
