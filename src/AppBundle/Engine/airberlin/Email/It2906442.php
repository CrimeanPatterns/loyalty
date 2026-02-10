<?php

namespace AwardWallet\Engine\airberlin\Email;

class It2906442 extends \TAccountCheckerExtended
{
    public $mailFiles = "airberlin/it-2906442.eml";
    public $reBody = 'airberlin';
    public $reBody2 = "Elektronische Tickets";
    public $reSubject = "Virgin Atlantic Airways e-Ticket";
    public $reFrom = "airberlinholidays.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);

                // FLIGHT

                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = re("#Ihre\s+Buchungs-Nr\.\s+lautet\s*:\s*(\d+)#", $this->http->Response['body']);
                // TripNumber
                // Passengers
                $it['Passengers'] = [reni("Name des Fluggastes / Name of passenger
				Veranstalter / Touroperator
				\w+
				[^\n]+
				[^\n]+
				(\w+\s+\w+)", $pdf)];
                // AccountNumbers
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

                preg_match_all("#Elektronische Tickets.*?Flug.*?Flight.*?(?:E-Tix|Es gelten die ABB)#msi", $pdf, $m, PREG_PATTERN_ORDER);
                $roots = $m[0];

                foreach ($roots as $root) {
                    $root = str_replace("Flug /\nKlasse /\nDatum /\nAbflugzeit /\nAnkunftszeit /\nStatus /\n", "", $root);

                    $table = self::getTable($root, 7, 1, "von / from");

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = re("#\w+\s+(\d+)#", $table['Flight'][0]);

                    // DepCode
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                    // DepName
                    $itsegment['DepName'] = $table['von / from'][0];

                    // DepDate
                    $itsegment['DepDate'] = strtotime($table['Date'][0] . ' ' . $table['Departure Time'][0]);

                    // ArrCode
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                    // ArrName
                    $itsegment['ArrName'] = trim(re("#nach / to\n[^\n]+\n([^\n]+)#msi", $root));

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($table['Date'][0] . ' ' . $table['Arrival Time'][0]);

                    // AirlineName
                    $itsegment['AirlineName'] = re("#(\w+)\s+\d+#", $table['Flight'][0]);

                    // Aircraft
                    // TraveledMiles
                    // Cabin
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

                // HOTEL

                preg_match_all("#\n\s*Hotelgutschein / Accommodation Voucher.*?\n\n#msi", $pdf, $m, PREG_PATTERN_ORDER);
                $roots = $m[0];

                foreach ($roots as $root) {
                    $it = [];
                    $table = self::getTable($root, 2, 1, "Adresse / Address");
                    $table2 = self::getTable($root, 3, 1, "Zimmerart / Roomtype");

                    $it['Kind'] = "R";

                    // ConfirmationNumber
                    $it['ConfirmationNumber'] = re("#Ihre\s+Buchungs-Nr\.\s+lautet\s*:\s*(\d+)#", $this->http->Response['body']);

                    // TripNumber
                    // ConfirmationNumbers

                    // Hotel Name
                    $it['HotelName'] = $table["Adresse / Address"][0];

                    // 2ChainName

                    // CheckInDate
                    $it['CheckInDate'] = strtotime(re("#(\d+\.\d+\.\d+)\s+-\s+\d+\.\d+.\d+#", $root));

                    // CheckOutDate
                    $it['CheckOutDate'] = strtotime(re("#\d+\.\d+\.\d+\s+-\s+(\d+\.\d+.\d+)#", $root));

                    // Address
                    $it['Address'] = $table["Ansprechpartner / Contact"][0];

                    // DetailedAddress

                    // Phone
                    $it['Phone'] = trim(re("#Tel\s*:\s*([\d\s\+\-]+)#", $root));

                    // Fax
                    // GuestNames
                    $GuestNames = re("#nicht übertragbar / not endorsable -(.+)#msi", $root);
                    $GuestNames = explode("\n", trim($GuestNames));
                    $GuestNames = array_map(function ($a) { return trim(preg_replace("#[^a-zA-Z\s]+#", "", $a)); }, $GuestNames);

                    if (count($GuestNames) > 0) {
                        $it['GuestNames'] = array_unique($GuestNames);
                    }

                    // Guests
                    $it['Guests'] = $table2['Personen / No. of Pax'][0];

                    // Kids
                    // Rooms
                    // Rate
                    // RateType
                    // CancellationPolicy
                    // RoomType
                    $it['RoomType'] = $table2['Zimmerart / Roomtype'][0];

                    // RoomTypeDescription
                    $it['RoomTypeDescription'] = $table2['Verpflegung / Board'][0];

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
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('bestaetigung_\d+\.pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $pdf = $pdfs[0];

        if (($body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return false;
        }

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('bestaetigung_\d+\.pdf');

        if (!isset($pdfs[0])) {
            return null;
        }

        $pdf = $pdfs[0];

        if (($body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return null;
        }

        $this->pdf = clone $this->http;
        $this->pdf->SetBody($body);

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'Flight',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getTable($text, $cols, $rows, $start)
    {
        $re = str_repeat("[^\n]*?\n", $cols + $cols * $rows);
        $table = re("#{$start}{$re}#msi", $text);
        $re = str_repeat("[^\n]*?\n", $cols);
        $header = re("#{$re}#msi", $table);
        $body = re("#{$re}(.+)#msi", $table);

        $table = [];
        $th = array_map("trim", explode("\n", $header));

        preg_match_all("#{$re}#msi", $body, $m, PREG_PATTERN_ORDER);

        foreach ($m[0] as $k => $row) {
            $cols = array_map("trim", explode("\n", $row));

            foreach ($cols as $i => $col) {
                $table[$th[$i]][$k] = $col;
            }
        }

        return $table;
    }
}
