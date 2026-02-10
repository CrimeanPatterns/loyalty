<?php

namespace AwardWallet\Engine\aplus\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = ["sofitel.com", "accor.com"];
    public $reSubject = [
        "en" => "Sofitel",
        "pt" => "Reservas Mercure",
    ];
    public $reBody = 'sofitel.com';
    public $reBody2 = [
        "en" => "Check-in Date",
        "pt" => "Data de Chegada",
    ];
    public $pdfPattern = ".+\.pdf";
    public static $dictionary = [
        "en" => [],
        "pt" => [
            "Confirmation number"                  => "Número de confirmação",
            "Thank you very much for choosing the" => "Agradecemos por escolher o hotel",
            "Check-in Date"                        => "Data de Chegada",
            "Check-out Date"                       => "Data de Saida",
            "Room Type"                            => "Tipo Apto",
            "Rate"                                 => "Tarifa",
        ],
    ];

    public $lang = "en";
    private $text;

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->re("#{$this->opt($this->t('Confirmation number'))}\s*:\s*(\d+)#", $text);

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->re("#{$this->opt($this->t('Thank you very much for choosing the'))}\s+(.*?)\.#", $text);

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check-in Date'))}\s*:\s*(.+)#", $text)));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check-out Date'))}\s*:\s*(.+)#", $text)));

        // Address
        $it['Address'] = $this->re("#\n\s*{$it['HotelName']}\s+\|\s+(.+)#", $text);

        if (empty($it['Address'])) {
            $it['Address'] = trim(str_replace([" - ", " – "], ", ", $this->re("#\n\s*(.+?\s(?:-|- CEP:\s+\d+))\s*Tel.+\s*$#m", $text)), " -");
        }
        // DetailedAddress

        // Phone
        $it['Phone'] = trim($this->re("#Tel\s*:\s*(.+?)(?:\||\n)#", $text));

        // Fax
        $it['Fax'] = $this->re("#Fax\s*:\s*(.+)#", $text);

        // GuestNames
        $it['GuestNames'] = array_filter([$this->re("#Guest Name\s*:\s*(.+)#", $text)]);

        // Guests
        $it['Guests'] = $this->re("#No of Persons per room\s*:\s*(\d+)(?:\s+Adult|\n)#", $text);

        // Kids
        // Rooms
        $it['Rooms'] = $this->re("#No of Rooms\s*:\s*(.+)#", $text);

        // Rate
        $it['Rate'] = $this->re("#Daily Rate.*:\s*(.+)#", $text);

        // RateType

        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->re("#{$this->opt($this->t('Room Type'))}\s*:?\s*(.+)#", $text);

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        // Currency
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ReservationDate
        // NoItineraries
        if (empty($it['CheckInDate'])) {//format 2:
            $table = $this->re("#^( *{$this->opt($this->t('Check-in Date'))}.+?)\n *{$this->opt($this->t('Confirmation number'))}#sm",
                $text);
            $table = $this->SplitCols($table, $this->colsPos($table, 10));

            if (count($table) < 4) {
                $this->http->Log("other format");

                return null;
            }

            if (preg_match("#^ *(\d+.+?\d{4}) *\n\s+([^\n]+)#", $text, $m)) {
                $it['GuestNames'][] = $m[2];
                $it['ReservationDate'] = strtotime($this->normalizeDate($m[1]));
            }
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check-in Date'))}\s+(.+)#",
                $table[0])));
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#{$this->opt($this->t('Check-out Date'))}\s+(.+)#",
                $table[1])));

            if (isset($table[4])) {
                $table[3] = explode("\n", $table[3]);
                $table[4] = explode("\n", $table[4]);
                $node = [];

                foreach ($table[3] as $i => $str) {
                    if (isset($table[4][$i])) {
                        $node[$i] = $str . $table[4][$i];
                    } else {
                        $node[$i] = $str;
                    }
                }

                if (count($table[4]) < count($table[3])) {
                    $n = count($table[3]);
                    array_push($node, array_slice($table[4], $n));
                }
                $node = implode("\n", $node);
            } else {
                $node = $table[3];
            }
            $it['RoomType'] = $this->re("#{$this->opt($this->t('Room Type'))}\s*:?\s*(.+)#", $node);
            $it['Rate'] = $this->re("#{$this->opt($this->t('Rate'))}\s*(.+)#", $table[2]);
        }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (strpos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
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

        if (strpos($text, $this->reBody) === false && strpos($text, 'accor.com') === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

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

        $this->parsePdf($itineraries);
        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($a) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $types = 2;
        $cnt = $types * count(self::$dictionary);

        return $cnt;
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+)\-(\d+)\-(\d{2})$#", //16-05-18
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
            "#^(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})$#u", //12 de Abril de 2018
        ];
        $out = [
            "$2.$1.20$3",
            "$20$3-$2-$1",
            "$3.$2.20$4, $1",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function SplitCols($text, $pos = false)
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
