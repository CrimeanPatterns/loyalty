<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "";

    public $reFrom = "reservations@avis.co.za";
    public $reSubject = [
        "en"=> "Avis reservation/quote confirmation",
    ];
    public $reBody = 'avis';
    public $reBody2 = [
        "en"=> "Pick up Location",
    ];
    public $pdfPattern = "\d+-\d+-[A-Z]+-\d+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;
        $table = $this->splitCols($this->re("#\n(\s*Pick up Location.*?)Liability Amount#ms", $text));

        if (count($table) != 2) {
            $this->http->log("incorrect columns count");

            return;
        }

        $it = [];
        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->re("#Reservation number\s+(.*?)\s{2,}#", $text);

        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->re("#(\d+\s+[^\d\s]+\s+\d{4}\s+\d+:\d+)#", $table[0]));

        // PickupLocation
        $it['PickupLocation'] = str_replace("\n", " ", $this->re("#Time\s+(.*?)\n\n#ms", $table[0]));

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->re("#(\d+\s+[^\d\s]+\s+\d{4}\s+\d+:\d+)#", $table[1]));

        // DropoffLocation
        $it['DropoffLocation'] = str_replace("\n", " ", $this->re("#Time\s+(.*?)\n\n#ms", $table[1]));

        // PickupPhone
        $it['PickupPhone'] = $this->re("#\n(.+)\n\d+\s+[^\d\s]+\s+\d{4}\s+\d+:\d+#", $table[0]);

        // PickupFax
        // PickupHours

        // DropoffPhone
        $it['DropoffPhone'] = $this->re("#\n(.+)\n\d+\s+[^\d\s]+\s+\d{4}\s+\d+:\d+#", $table[1]);

        // DropoffHours
        // DropoffFax
        // RentalCompany
        // CarType
        // CarModel
        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->re("#Customer\s+(.+)#", $text);

        // PromoCode
        // TotalCharge
        $it["TotalCharge"] = $this->re("#Estimated Total\s+[A-Z]{3}\s+([\d\,\.]+)#", $text);

        // Currency
        $it["Currency"] = $this->re("#Estimated Total\s+([A-Z]{3})\s+[\d\,\.]+#", $table[1]);

        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        $it['Status'] = $this->re("#(Confirmed : Yes)#", $text) ? "Confirmed" : null;

        // Cancelled
        // ServiceLevel
        // PricedEquips
        // Discount
        // Discounts
        // Fees
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
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
        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+ [^\d\s]+ \d{4}) (\d+:\d+)$#", //21 AUG 2017 05:00
        ];
        $out = [
            "$1, $2",
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
