<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;

// parsers with similar formats: bcd/Itinerary1, bcd/TravelPlanPdf, bcd/TravelReceiptPdf

class TravelReceiptPdf2 extends \TAccountChecker
{
    public $mailFiles = "bcd/it-2530502.eml, bcd/it-42312511.eml, bcd/it-5449208.eml";

    public $reFrom = "@bcdtravel";
    public $reSubject = [
        "en"  => "Itinerary Ticket Receipt for",
        "en1" => "Itinerary for",
    ];
    public $reBody = 'BCD Travel';
    public $reBody2 = [
        "en" => "Itinerary Details",
    ];
    public $text;
    public $date;
    public $pdfPattern = "(?:Itinerary Ticket Receipt for|Itinerary for)\s+.*\s+[A-Z\d]+.pdf";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

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

        if (stripos($text, $this->reBody) === false) {
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

        foreach ($this->reBody2 as $lang => $re) {
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
                'TotalCharge' => [
                    "Amount"   => $this->re("#Total:\s+[A-Z]{3}\s+([\d\,\.]+)#", $this->text),
                    "Currency" => $this->re("#Total:\s+([A-Z]{3})\s+[\d\,\.]+#", $this->text),
                ],
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

    private function parsePdf(&$itineraries)
    {
        $text = $this->text;

        $mainTable = $this->re("#\n([^\n]*Itinerary Details.*?)(?:\n\n|\n[^\n]+ [­\-] )#msu", $text);
        $pos = $this->tableHeadPos(explode("\n", $mainTable)[0]);
        $rows = $this->split("#\n(\d+\s+[^\s\d]+)#", $mainTable);
        $mainInfo = [];

        foreach ($rows as $row) {
            $table = $this->splitCols($row, $pos);

            if (count($table) != 6) {
                $this->http->Log("incorrect parse main table");

                return;
            }
            $mainInfo[trim(str_replace("\n", " ", $table[1]))] = $table;
        }

        $segments = $this->split("#\n([^\d\n-]+[­\-]\s+[^\s\d]+,\s+\d+\s+[^\s\d]+\s+\d{4}\n)#u", $text);
        $airs = [];
        $hotels = [];

        foreach ($segments as $stext) {
            $stext = explode("\n\n", $stext)[0];
            $type = $this->re("#^(.*?)\s+[­\-]#u", $stext);

            switch ($type) {
                case 'Air':
                    $airs[] = $stext;

                    break;

                case 'Hotel':
                    $hotels[] = $stext;

                    break;

                default:
                    $this->http->Log("unknown segment type {$type}");

                    return;
            }
        }

        //##################
        //##   FLIGHTS   ###
        //##################

        if (count($airs) > 0) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->re("#Booking Reference\s+(\w+)#", $text);

            // TripNumber
            // Passengers
            $it['Passengers'] = [$this->re("#Traveller[^\n]+\n(.*?)\s{2,}#", $text)];

            // TicketNumbers
            // AccountNumbers
            if (!empty($node = $this->re("#Loyalty Number:[ ]+(.*)#", $text))) {
                $it['AccountNumbers'] = [$node];
            }
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($airs as $stext) {
                $date = strtotime($this->normalizeDate($this->re("#^.*\s+[­\-]\s+(.+)#u", $stext)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\n(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)\*?\s#", $stext);

                // DepName
                $itsegment['DepName'] = $this->re("#Depart:\s+(.*?)(, Terminal|\n)#", $stext);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->re("#Depart:\s+.*?(\d+:\d+[^\n]*)#ms", $stext)), $date);

                // ArrName
                $itsegment['ArrName'] = $this->re("#Arrive:\s+(.*?)(, Terminal|\n)#", $stext);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->re("#Arrive:\s+.*?(\d+:\d+[^\n]*)#ms", $stext)), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\n([A-Z\d][A-Z]|[A-Z][A-Z\d])\d+\*?\s#", $stext);

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Equipment:\s+(.+)#", $stext);

                // TraveledMiles
                // AwardMiles

                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = array_filter([$this->re("#Seat:\s+(\d+\w)#", $stext)]);

                // Duration
                $itsegment['Duration'] = $this->re("#Duration:\s+(.*?)(\s+non[­\-]stop|\n)#u", $stext);

                // Meal
                // Smoking
                // Stops
                $itsegment['Stops'] = $this->re("#Duration:\s+.*(non[­\-]stop)#u", $stext);

                if (isset($mainInfo[$itsegment['AirlineName'] . $itsegment['FlightNumber']])) {
                    $info = $mainInfo[$itsegment['AirlineName'] . $itsegment['FlightNumber']];
                }

                if (isset($mainInfo[$itsegment['AirlineName'] . $itsegment['FlightNumber'] . '*'])) {
                    $info = $mainInfo[$itsegment['AirlineName'] . $itsegment['FlightNumber'] . '*'];
                }

                if (isset($info)) {
                    // DepCode
                    $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $info[2]);

                    // ArrCode
                    $itsegment['ArrCode'] = $this->re("#\([A-Z]{3}\).*\(([A-Z]{3})\)#ms", $info[2]);

                    // Cabin
                    $itsegment['Cabin'] = $this->re("#(.+)\n\w(\n|$)#", $info[4]);

                    // BookingClass
                    $itsegment['BookingClass'] = $this->re("#.+\n(\w)(\n|$)#", $info[4]);
                }

                $it['TripSegments'][] = $itsegment;
            }

            $itineraries[] = $it;
        }

        //#################
        //##   HOTELS   ###
        //#################

        foreach ($hotels as $htext) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->re("#[:\s]+([A-Z\d]{5,})\s+Address:#", $htext);

            // TripNumber
            // AccountNumbers
            // ConfirmationNumbers

            // HotelName
            $it['HotelName'] = $this->re("#\n\s*(.*?)\s{2,}.*\n\s*Address:#", $htext);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->re("#Check In / Check Out:\s+(.*?)\s+[­\-]\s+#u", $htext)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->re("#Check In / Check Out:\s+.*?\s+[­\-]\s+(.+)#u", $htext)));

            // Address
            $it['Address'] = $this->re("#Address:\s+(.+)#", $htext);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->re("#Tel\.:[ ]*(.*?)\n#", $htext);

            // Fax
            $it['Fax'] = $this->re("#Fax:[ ]*(.*?)\n#", $htext);

            // GuestNames
            $it['GuestNames'] = [$this->re("#Traveller[^\n]+\n(.*?)\s{2,}#", $text)];

            // Guests
            // Kids
            // Rooms
            // Rate
            $it['Rate'] = $this->re("#Rate per night:\s+(.+)#", $htext);

            // RateType

            // CancellationPolicy
            // RoomType
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
            $itineraries[] = $it;
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
        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
        ];
        $out = [
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
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
