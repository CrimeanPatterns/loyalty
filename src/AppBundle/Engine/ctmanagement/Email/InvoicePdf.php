<?php

namespace AwardWallet\Engine\ctmanagement\Email;

use AwardWallet\Engine\MonthTranslate;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "ctmanagement/it-10026232.eml, ctmanagement/it-10065201.eml, ctmanagement/it-10087588.eml, ctmanagement/it-8748329.eml, ctmanagement/it-8786432.eml, ctmanagement/it-8786515.eml, ctmanagement/it-8822755.eml, ctmanagement/it-8823263.eml, ctmanagement/it-8846957.eml, ctmanagement/it-9959586.eml, ctmanagement/it-9965467.eml";

    public $reFrom = "@eu.travelctm.com";
    public $reSubject = [
        "en"=> "INVOICE",
    ];
    public $reBody = 'Corporate Travel Management';
    public $reBody2 = [
        "en"=> "Invoice No",
    ];
    public $pdfPattern = "[A-Z ]+-\d+[^\s\d]+\d{4}-(INVOICE )?\d+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    public $allTotal;

    public function parsePdf(&$itineraries)
    {
        $text = $this->text;

        if (strpos($text, "European Air") !== false || strpos($text, "Domestic Air") !== false) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->re("#Booking No\.\s+(\w+)#", $text);

            // TripNumber
            // Passengers
            $it['Passengers'][] = str_replace("\n", ' ', trim($this->re("#Incl\.\s+Vat\s+((?:.*\n){0,3})\s*Booker Name#", $text)));

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->re("#(?:Domestic|European)\s*Air\s+.+\s+([\d.,]+)\s*\n#", $text);
            // BaseFare
            // Currency
            $it['Currency'] = $this->re("#(?:Total|Balance Due)\s+([A-Z]{3})#", $text);

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory
            $tripStr = $this->re("#(?:European|Domestic) Air[^\n]+(.*?)(?:Tax/Air|Hotel\s+Referral\s+Booking|Merchant\s+Fee)#ms", $text);
            preg_match_all("#\n(?<Date>\d+/\d+/\d{4})(?:\s{2,}[^/]*?)?\s{2,}(?<DepName>.*?) / (?<ArrName>.*?)(?:\s{2,}[^/]*?)?\s{2,}(?<Cabin>[A-Z]+)\s{2,}(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s{2,}(?<DepTime>\d{4})/(?<ArrTime>\d{4})#", $tripStr, $segments, PREG_SET_ORDER);

            foreach ($segments as $st) {
                $date = strtotime($this->normalizeDate($st['Date']));
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $st['FlightNumber'];

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $st['DepName'];

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($st['DepTime']), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $st['ArrName'];

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($st['ArrTime']), $date);

                // AirlineName
                $itsegment['AirlineName'] = $st['AirlineName'];

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $st['Cabin'];

                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        if (strpos($text, "Hotel Referral Booking") !== false) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#Confirmation No.\s+(\w+)#", $text);

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->re("#\n(.*?)\nCheck-In#", $text);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check-In\s+(.+)#", $text)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check-Out\s+(.+)#", $text)));

            // Address
            $it['Address'] = $it['HotelName'];

            // DetailedAddress

            // Phone
            // Fax
            // GuestNames
            $it['GuestNames'][] = str_replace("\n", ' ', trim($this->re("#Incl\.\s+Vat\s+((?:.*\n){0,3})\s*Booker Name#", $text)));

            // Guests
            // Kids
            // Rooms
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->re("#Roomtype\s+(.+)#", $text);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            if (preg_match("#Room rate total:\s+.+eq\s*(?<curr>[A-Z]{3})(?<total>[\d,.]+)#", $text, $m) || preg_match("#Room rate total:\s+(?<curr>[A-Z]{3})(?<total>[\d,.]+)#", $text, $m)) {
                $it['Total'] = $m['total'];
                $it['Currency'] = $m['curr'];
            }

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        $TotalCharge = $this->re("#(?:Total Incl\. Vat|Invoice Total)\s+([\d.,]+)#", $text);
        $Currency = $this->re("#(?:Total|Balance Due)\s+([A-Z]{3})#", $text);

        if (count($itineraries) == 1 && $itineraries[0]['Kind'] == "T") {
            $itineraries[0]['TotalCharge'] = $TotalCharge;
        }

        if (count($itineraries) == 1 && $itineraries[0]['Kind'] == "R") {
            $itineraries[0]['Total'] += $TotalCharge;
        }

        if (count($itineraries) > 1) {
            $this->allTotal['TotalCharge'] = $TotalCharge;

            foreach ($itineraries as $it) {
                if ($it['Kind'] == "R" && isset($it['Total'])) {
                    $this->allTotal['TotalCharge'] += $it['Total'];
                }
            }
            $this->allTotal['Currency'] = $Currency;
        }
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
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if (isset($this->allTotal)) {
            $result['TotalCharge']['Amount'] = $this->allTotal['TotalCharge'];
            $result['TotalCharge']['Currency'] = $this->allTotal['Currency'];
        }

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
            "#^(\d+)/(\d+)/(\d{4})$#", //03/10/2017
            "#^(\d{2})(\d{2})$#", //2125
            "#^(\d+)/(\d+)/(\d{4}) (\d+:\d+:\d+)$#", //13/10/2017 00:00:00
        ];
        $out = [
            "$1.$2.$3",
            "$1:$2",
            "$1.$2.$3, $4",
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
