<?php

namespace AwardWallet\Engine\finnair\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $reFrom = '#@finnair.com#i';
    public $reProvider = '#[@.]finnair\.com#i';
    public $reSubject = null;
    public $reText = null;
    public $reHtml = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "finnair/it-1.eml"
            "#SERVICE\s+FROM\s+TO\s+DEPART\s+ARRIVE#" => function (&$it) {
                $it = ['Kind' => 'T'];

                $text = $this->http->Response['body'];

                if (preg_match("#BOOKING REF\s+([^\s]+)#", $text, $m)) {
                    $it['RecordLocator'] = $m[1];
                }

                if (preg_match("#DATE\s+([^\s]+)#", $text, $m)) {
                    $it['ReservationDate'] = strtotime($m[1]);
                }

                if (preg_match("#\s+(\w+\s*/\s*\w+\s*\w+)\s+TELEPHONE#ms", $text, $m)) {
                    $it['Passengers'] = $m[1];
                }

                $year = date('Y', $it['ReservationDate']);

                $meter = [];
                $offset = 0;

                if (preg_match("#\n(((\-{2,})\s+)+)#ms", $text, $m)) {
                    $meterStr = $m[1];

                    foreach (preg_split("#(\-+\s)#", $meterStr, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $line) {
                        $meter[] = $offset;
                        $offset += strlen($line);
                    }
                }

                $flights = null;

                if (preg_match("#SERVICE\s+FROM\s+TO\s+DEPART\s+ARRIVE\s+(((\-{2,})\s+)+)(.*?)\s+RESERVATION NUMBER#ms", $text, $m)) {
                    $flights = preg_split("#(\n\s*\n)#ms", $m[4]);
                }

                $segCell = function ($str, $col, $row, $full = false) use ($meter) {
                    $rows = explode("\n", $str);

                    if ($full) {
                        return $rows[$col];
                    }
                    $from = $meter[$col];

                    if (!isset($meter[$col + 1])) {
                        $to = strlen($rows[$row]);
                    } else {
                        $to = $meter[$col + 1];
                    }

                    return trim(substr($rows[$row], $from, $to - $from), " \n,.");
                };

                foreach ($flights as $segment) {
                    $seg = [];

                    $seg['FlightNumber'] = $segCell($segment, 0, 0, true);

                    $seg['DepName'] = trim($segCell($segment, 1, 1) . ', ' . $segCell($segment, 1, 2), ",.\n\r ");
                    $seg['ArrName'] = trim($segCell($segment, 2, 1) . ', ' . $segCell($segment, 2, 2), ",.\n\r ");

                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    $depDate = $segCell($segment, 0, 1);
                    $arrDate = $segCell($segment, 0, 1);

                    $seg['DepDate'] = strtotime($depDate . "$year, " . $segCell($segment, 3, 1));
                    $seg['ArrDate'] = strtotime($arrDate . "$year, " . $segCell($segment, 4, 1));

                    if (preg_match("#SEAT\s+([^\s]+)\s+CONFIRMED#", $segment, $m)) {
                        $seg['Seats'] = $m[1];
                    }

                    if (preg_match("#RESERVATION CONFIRMED\s*-\s*([^\n]+)#", $segment, $m)) {
                        $seg['Cabin'] = $m[1];
                    }

                    if (preg_match("#FLIGHT OPERATED BY \s*([^\n]+)#", $segment, $m)) {
                        $seg['AirlineName'] = $m[1];
                    }

                    if (preg_match("#EQUIPMENT:\s*([^\n]+)#", $segment, $m)) {
                        $seg['Aircraft'] = $m[1];
                    }

                    $seg['Stops'] = $segCell($segment, 0, 2) == 'NON STOP' ? 0 : null;

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return ((isset($this->reFrom) && isset($headers['from'])) ? preg_match($this->reFrom, $headers["from"]) : false)
                || ((isset($this->reSubject) && isset($headers['subject'])) ? preg_match($this->reSubject, $headers["subject"]) : false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $parser->getPlainBody()) : false)
                || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $parser->getHTMLBody())) {
                $processor($itineraries, $parser);

                break;
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];
    }
}
