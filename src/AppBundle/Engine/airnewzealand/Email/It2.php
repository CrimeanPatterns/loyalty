<?php

namespace AwardWallet\Engine\airnewzealand\Email;

class It2 extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-1.eml, airnewzealand/it-10932352.eml, airnewzealand/it-10932360.eml, airnewzealand/it-2.eml, airnewzealand/it-2650560.eml, airnewzealand/it-2921868.eml, airnewzealand/it-3131568.eml, airnewzealand/it-3209497.eml, airnewzealand/it-32858162.eml, airnewzealand/it-32875165.eml, airnewzealand/it-3990925.eml, airnewzealand/it-4002328.eml, airnewzealand/it-5782694.eml";

    private $reFrom = "@airnz.co.nz";
    private $reSubject = ['Electronic Receipt'];
    private $reBody = 'Air New Zealand';
    private $reBody2 = [
        'BOOKING REF.',
    ];

    private $text;
    private $pdfNamePattern = '(E-Ticket booking ref [A-Z\d]+|.*E-Ticket.*)\.pdf';

    private $classTypes = [
        'Economy',
        'Business',
        'First',
        'Premium Economy',
        'Business Class',
        'First Class',
        'Economy Class',
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (!isset($pdfs[0])) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");
        }
        if (!isset($pdfs[0])) {
            return false;
        }

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($body, $this->reBody) === false) {
                continue;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
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

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function parseEmail()
    {
        $stext = $this->text;
        $it = ['Kind' => 'T'];

        // RecordLocator
        $pos = stripos($stext, 'BOOKING REF');

        if ($pos !== false && preg_match("#BOOKING REF\.\s*([A-Z\d]{5,7})\s+#", substr($stext, $pos, 50), $m)) {
            $it['RecordLocator'] = $m[1];
        }

        // TripNumber
        // ConfirmationNumbers
        // Passengers
        // TicketNumbers
        $i = 0;
        $posBegin = 0;

        while (($pos = stripos($stext, 'Tkt No.', $posBegin)) !== false && $i < 50) {
            $posBegin = $pos + 1;

            $pos = ($pos - 60 > 0) ? $pos - 60 : 0;
            $posN = strripos(substr($stext, $pos, 60), "\n");

            if ($pos !== false && preg_match("#^\s+(.+?)(?:\s*\+\s*INFANT)?\s+Tkt No\.\s*([\d\-]{5,})\s+#", substr($stext, $pos + $posN, 90), $m)) {
                $it['Passengers'][] = trim($m[1]);
                $it['TicketNumbers'][] = $m[2];
            }
            $i++;
        }

        if (isset($it['Passengers'])) {
            $it['Passengers'] = array_unique(array_filter($it['Passengers']));
        }

        if (isset($it['TicketNumbers'])) {
            $it['TicketNumbers'] = array_unique(array_filter($it['TicketNumbers']));
        }

        // AccountNumbers
        // Cancelled

        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // Fees
        $receiptPos = stripos($stext, 'PAYMENT' . "\n");
        if (empty($receiptPos)) {
            $receiptPos = stripos($stext, 'PAYMENT' . "   ");
        }

        if (!empty($receiptPos)) {
            $receipt = substr($stext, $receiptPos);
            $addOnPos = stripos($receipt, 'Flight Add-on Payment');

            if (!empty($addOnPos)) {
                $addOnText = substr($receipt, $addOnPos);
                $receipt = substr($receipt, 0, $addOnPos);
            }

            if (preg_match_all("#\n\s*(?:Fare|Additional fare)\s{3,}.+?\s+([A-Z]{3})\s*([\d\.\,]+)\s*\n#", $receipt, $m)) {
                $it['BaseFare'] = 0.0;
                $it['Currency'] = $m[1][0];

                foreach ($m[2] as $key => $value) {
                    $it['BaseFare'] += $this->normalizePrice($value);
                }
            }

            if (preg_match_all("#\n\s*(?:TOTAL|Additional Payment)\s{3,}.+?\s+([A-Z]{3})\s*([\d\.\,]+)\s*\n#", $receipt, $m)) {
                $it['TotalCharge'] = 0.0;
                $it['Currency'] = $m[1][0];

                foreach ($m[2] as $key => $value) {
                    $it['TotalCharge'] += $this->normalizePrice($value);
                }
            }

            if (!empty($addOnText) && isset($it['TotalCharge']) && preg_match_all("#\n\s*TOTAL\s{3,}.+?\\s+([A-Z]{3})\s*([\d\.\,]+)\s*\n#", $addOnText, $m)) {
                $it['Currency'] = $m[1][0];

                foreach ($m[2] as $key => $value) {
                    $it['TotalCharge'] += $this->normalizePrice($value);
                }
            }
        }

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        // TripSegments
        $flightsPos = strpos($stext, 'Check In   ');

        if (empty($flightsPos)) {
            $flightsPos = strpos($stext, 'Check-In   ');
        }

        if (empty($flightsPos)) {
            $flightsPos = strpos($stext, 'Bags   ');
        }

        if (!empty($flightsPos)) {
            $ends = ["Product and Flight Add-ons", "Information"];
            $endsPos = [];

            foreach ($ends as $end) {
                $endsPos[] = strpos($stext, $end . "\n", $flightsPos);
            }

            $endsPos = array_filter($endsPos);

            $endsPos = !empty($endsPos) ? min($endsPos) : 0;

            if (!empty($endsPos)) {
                $flightsText = substr($stext, $flightsPos - 10, $endsPos - $flightsPos + 10);
            } else {
                $flightsText = substr($stext, $flightsPos - 10);
            }

            $flightsText = preg_replace("#\n[ ]*Thanks! Have a.+(.+\n+){1,5}.+Page\s+\d+\s*of\s*\d*[ ]*(\n|$)#", "\n", $flightsText);
            $flightsText = preg_replace("#^[\s\S]*\n([ ]*(?:Check[ \-]In|Bags)\s+Depart.+)#", "", $flightsText);
            $flights = $this->split("#\n(.+[ ]+Depart +.+Arrive\s+)#", $flightsText);

            foreach ($flights as $text) {
                if (preg_match("#\s+((?:[^\w\s:.,\-]+ )*[^\w\s:.,\-]{3,}(?: [^\w\s:.,\-]+)*)\s+#u", $text, $m)) {
                    $text = str_replace($m[1], str_pad('', strlen($m[1])), $text);
                }
                $HeadPos = $this->TableHeadPos(substr($text, 0, stripos($text, "\n")));

                if (isset($HeadPos[0]) && $HeadPos[0] > 15) {
                    array_unshift($HeadPos, '0');
                }

                if (count($HeadPos) == 5) {
                    array_pop($HeadPos);
                }
                $table = $this->SplitCols($text, $HeadPos);

                if (count($table) !== 4) {
                    $this->logger->info("incorrect table parse");

                    return;
                }

                $seg = [];

                // FlightNumber
                // AirlineName
                if (preg_match("#^\s*([A-Z]{2})(\d{1,5})\s+#", $table[3], $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];
                }

                // DepCode
                // DepName
                // DepartureTerminal
                // DepDate
                if (preg_match("#Depart\s+\w+\s+(.+\d{4})\s+(.+)\s+(\d+:\d+)\s+([AP]M)?#s", $table[1], $m)) {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    if (preg_match("#(.+)\s*\n\s*\((.*Terminal.*)\)#s", $m[2], $mat)) {
                        $seg['DepName'] = trim($mat[1]);
                        $seg['DepartureTerminal'] = $mat[2];
                    } else {
                        $seg['DepName'] = trim($m[2]);
                    }
                    $seg['DepDate'] = strtotime($m[1] . ' ' . $m[3] . ' ' . ($m[4] ?? ''));
                } elseif (preg_match("#Depart\s+\W*\s+(\D+)\n\s*(\d+:\d+)\s+([AP]M)?#su", $table[1], $m)) {
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    if (preg_match("#(.+)\s*\n\s*\((.*Terminal.*)\)#s", $m[1], $mat)) {
                        $seg['DepName'] = trim($mat[1]);
                        $seg['DepartureTerminal'] = $mat[2];
                    } else {
                        $seg['DepName'] = trim($m[1]);
                    }
                    $seg['DepDate'] = MISSING_DATE;
                }

                // ArrCode
                // ArrName
                // ArrivalTerminal
                // ArrDate
                if (preg_match("#Arrive\s+\w+\s+(.+\d{4})\s+(.+)\s+(\d+:\d+)\s+([AP]M)?#s", $table[2], $m)) {
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    if (preg_match("#(.+)\s*\n\s*\((.*Terminal.*)\)#s", $m[2], $mat)) {
                        $seg['ArrName'] = trim($mat[1]);
                        $seg['ArrivalTerminal'] = $mat[2];
                    } else {
                        $seg['ArrName'] = trim($m[2]);
                    }
                    $seg['ArrDate'] = strtotime($m[1] . ' ' . $m[3] . ' ' . ($m[4] ?? ''));
                }

                // Aircraft
                // TraveledMiles
                // Cabin
                // BookingClass
                if (preg_match("#(" . implode('|', $this->classTypes) . ")?.*\s+Booking Class:\s*([A-Z]{1,2})\s*#i", $table[3], $m)) {
                    $seg['Cabin'] = !empty($m[1]) ? $m[1] : null;
                    $seg['BookingClass'] = $m[2];
                }
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops
                // Operator
                // Gate
                // ArrivalGate
                // BaggageClaim
                $it['TripSegments'][] = $seg;
            }
        }

        return $it;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        if (empty($pdfs)) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");
        }

        foreach ($pdfs as $pdf) {
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));
            if (stripos($this->text, $this->reBody) === false) {
                continue;
            }

            foreach ($this->reBody2 as $re) {
                if (stripos($this->text, $re) !== false) {
                    $its[] = $this->parseEmail();
                    break;
                }
            }

        }

        $class = explode('\\', __CLASS__);

        return [
            'emailType'  => end($class),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizePrice($cost)
    {
        if (empty($cost)) {
            return 0.0;
        }
        $cost = preg_replace('/\s+/', '', $cost);			// 11 507.00	->	11507.00
        $cost = preg_replace('/[,.](\d{3})/', '$1', $cost);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $cost = preg_replace('/,(\d{2})$/', '.$1', $cost);	// 18800,00		->	18800.00

        return (float) $cost;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $ret = [];

        if (count($r) > 1) {
            if (empty(trim($r[0]))) {
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

        $ds = 5;

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
}
