<?php

namespace AwardWallet\Engine\airmilesca\Email;

class It3298750 extends \TAccountCheckerExtended
{
    public $mailFiles = "airmilesca/it-3180177.eml, airmilesca/it-3298750.eml, airmilesca/it-3298751.eml, airmilesca/it-3298752.eml, airmilesca/it-3298754.eml, airmilesca/it-3298755.eml, airmilesca/it-3298756.eml, airmilesca/it-3298757.eml, airmilesca/it-3308058.eml, airmilesca/it-3308059.eml, airmilesca/it-3380861.eml";
    public $reBody = "Travel Club";
    public $reBody2 = "Has reservado el siguiente";
    public $reBody3 = "tu reserva se ha realizado";

    public $reSubject = "TravelClub: Información para el localizador";
    public $reFrom = "noreplay@travelclub.es";

    public $Types = [
        "Hotel"    => "Alojamiento",
        "Car"      => "Coche",
        "Cruise"   => "Crucero",
        "Flight"   => "Vuelo",
        "Event"    => "Servicio",
        "Transfer" => "Traslado",
    ];

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->Types['Hotel'] => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "R";

                // ConfirmationNumber
                $it['ConfirmationNumber'] = orval(
                    re("#Provider reference\s*:\s*([^\n]+)#", $pdf),
                    re("#Localizador\s*:\s*([^\n]+)#", $text)
                );

                // TripNumber
                // ConfirmationNumbers

                // HotelName
                $it['HotelName'] = re("#Has\s+reservado\s+el\s+siguiente\s+(.+)#", $text);

                // 2ChainName

                // CheckInDate
                $it['CheckInDate'] = strtotime(str_replace("/", ".", $this->getField("Entrada")));

                // CheckOutDate
                $it['CheckOutDate'] = strtotime(str_replace("/", ".", $this->getField("Salida")));

                // Address
                $it['Address'] = $this->getField("Dirección");

                // DetailedAddress

                // Phone
                $it['Phone'] = $this->getField("Teléfono");

                // Fax
                // GuestNames
                $it["GuestNames"] = $this->getNames($pdf);

                // Guests
                $it['Guests'] = $this->getField(["Adultos"]);

                // Kids
                $it['Kids'] = $this->getField(["Niños"]);

                // Rooms
                $it['Rooms'] = re("#Habitaciones\s+/\s+Rooms\s*:\s*(\d+)\s+Habitaciones\s+/\s+Rooms#", $pdf);

                // Rate
                // RateType

                // CancellationPolicy
                $it['CancellationPolicy'] = $this->http->FindSingleNode("(//*[normalize-space(text())='Política de cancelación:']/..)[1]", null, true, "#Política\s+de\s+cancelación\s*:\s*(.+)#msi");

                // RoomType
                $it['RoomType'] = $this->getField("Régimen");

                // RoomTypeDescription
                // Cost
                // Taxes
                // Total
                // Currency
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it["Status"] = re("#Booking\s+status\s*:\s*(\w+)#", $pdf);

                // Cancelled
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },

            $this->Types['Transfer'] => function (&$itineraries) {
                /*
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                $it = array();

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->getField("Localizador proveedor");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = MISSING_DATE;//strtotime(str_replace("/", ".", $this->getField("Inicio")));

                // PickupLocation
                $it['PickupLocation'] = $this->getFieldPdf("Origin", false);

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(str_replace("/", ".", $this->getField("Final")).", ".re("#\d+:\d+#", $this->getFieldPdf("DropOff time")));

                // DropoffLocation
                $it['DropoffLocation'] = $this->getFieldPdf("Destination");

                */
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                // echo $pdf;

                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->getField("Localizador proveedor");
                // TripNumber
                // Passengers
                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                $it["Status"] = re("#Booking\s+status\s*:\s*(\w+)#", $pdf);

                // ReservationDate
                $it["ReservationDate"] = strtotime(str_replace("/", ".", re("#Booking\s+date\s*:\s*(\d+/\d+/\d+)#", $pdf)));

                // NoItineraries
                // TripCategory
                $it["TripCategory"] = TRIP_CATEGORY_TRANSFER;

                // preg_match_all("#FLIGHT\s+DATA\s*:\s*(.*?)(?:DATOS\s+VUELO|\n\s*\n)#msi", $pdf, $segments);

                // $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                // foreach($segments[1] as $segment){
                $itsegment = [];
                // FlightNumber
                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->getFieldPdf("Origin", false);

                // DepDate
                $itsegment['DepDate'] = MISSING_DATE;

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->getFieldPdf("Destination", false);

                // ArrDate
                $itsegment['ArrDate'] = strtotime(str_replace("/", ".", $this->getField("Final")) . ", " . re("#\d+:\d+#", $this->getFieldPdf("DropOff time")));

                // AirlineName
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
                // }
                $itineraries[] = $it;
            },

            $this->Types['Car'] => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "L";

                // Number
                $it['Number'] = $this->getField("Localizador del proveedor");
                // TripNumber
                // PickupDatetime
                $it['PickupDatetime'] = strtotime(re("#(.+)h\.$#", str_replace("/", ".", $this->getField("Fecha de recogida:"))));

                // PickupLocation
                $it['PickupLocation'] = $this->getField("Dirección de recogida:");

                // DropoffDatetime
                $it['DropoffDatetime'] = strtotime(re("#(.+)h\.$#", str_replace("/", ".", $this->getField("Fecha de devolución:"))));

                // DropoffLocation
                $it['DropoffLocation'] = $this->getField("Dirección de devolución:");

                // PickupPhone
                $it['PickupPhone'] = $this->getField("Telefono de recogida:");

                // PickupFax
                // PickupHours
                // DropoffPhone
                $it['DropoffPhone'] = $this->getField("Telefono de devolución:");

                // DropoffHours
                // DropoffFax
                // RentalCompany
                // CarType
                $it['CarType'] = re("#^(.*?)\s+-#", $this->getField("Coche:"));

                // CarModel
                $it['CarModel'] = re("#-\s+(.+)#", $this->getField("Coche:"));

                // CarImageUrl
                // RenterName
                $it['RenterName'] = $this->getField("Conductor:");

                // PromoCode
                // TotalCharge
                // Currency
                // TotalTaxAmount
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it["Status"] = re("#Booking\s+status\s*:\s*(\w+)#", $pdf);

                // ServiceLevel
                // Cancelled
                // PricedEquips
                // Discount
                // Discounts
                // Fees
                // ReservationDate
                // NoItineraries
                $itineraries[] = $it;
            },

            $this->Types['Cruise'] => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "T";
                // TripCategory
                $it['TripCategory'] = TRIP_CATEGORY_CRUISE;
                // RecordLocator
                $it["RecordLocator"] = orval(
                    re("#Localizador\s*:\s*(\w+)#", $text),
                    re("#Localizador\s*:\s*(\w+)#", $this->text)
                );
                // TripNumber
                // Passengers
                $it["Passengers"] = $this->getNames($pdf);

                // AccountNumbers
                // Cancelled
                // ShipName
                $it["ShipName"] = $this->getField("Barco");
                // ShipCode
                // CruiseName
                $it["CruiseName"] = $this->getField("Crucero:");
                // Deck
                // RoomNumber
                $it["RoomNumber"] = $this->getField("Número de camarote");
                // RoomClass
                $it["RoomClass"] = $this->getField("Categoría Barco");
                // Status
                $it["Status"] = re("#Booking\s+status\s*:\s*(\w+)#", $pdf);
                // TripSegments

                $week = [
                    "Monday"   => 0,
                    "Tuesday"  => 1,
                    "Wednesday"=> 2,
                    "Thursday" => 3,
                    "Friday"   => 4,
                    "Saturday" => 5,
                    "Sunday"   => 6,
                ];
                $date = strtotime(str_replace("/", ".", re("#Fecha\s+de\s+salida\s+/\s+Departure\s+Date\s+(\d+/\d+/\d+)#msi", $pdf)));
                $weekday = false;

                $segmentsText = re("#Llegada\s+/\s+Arrival\s+-\s+Salida\s+/\s+Departure\s*(.*?)\s*Política\s+de\s+cancelación\s+/\s+Cancellation policy#msi", $pdf);
                preg_match_all("#(?<weekday>\w+)\s+-\s+(?<Port>[^\n]+)\s+(?<Arr>\d+:\d+hs|no disp)\s+-\s+(?<Dep>\d+:\d+hs|no disp)#", $segmentsText, $segments, PREG_SET_ORDER);

                foreach ($segments as $segment) {
                    if ($weekday) {
                        $last = $week[$weekday];
                        $current = $week[$segment['weekday']];

                        if ($current < $last) {
                            $current = $current + 7;
                        }
                        $diff = $current - $last;

                        if ($diff > 0) {
                            $date = strtotime("+{$diff} day", $date);
                        }
                    }

                    $weekday = $segment['weekday'];

                    $itsegment = [];
                    // Port
                    $itsegment["Port"] = $segment['Port'];
                    // DepDate
                    if ($segment['Dep'] != 'no disp') {
                        $itsegment["DepDate"] = strtotime(re("#\d+:\d+#", $segment['Dep']), $date);
                    }
                    // ArrDate
                    if ($segment['Arr'] != 'no disp') {
                        $itsegment["ArrDate"] = strtotime(re("#\d+:\d+#", $segment['Arr']), $date);
                    }

                    $cruise[] = $itsegment;
                }

                $this->converter = new \CruiseSegmentsConverter();
                $it['TripSegments'] = $this->converter->Convert($cruise);

                $itineraries[] = $it;
            },

            $this->Types['Flight'] => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                // echo $pdf;

                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = re("#Flight\s+ref.\s*:\s*(\w{6})#msi", $pdf);
                // TripNumber
                // Passengers
                $it["Passengers"] = $this->getNames($pdf);

                // AccountNumbers
                // Cancelled
                // TotalCharge
                // BaseFare
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                $it["Status"] = re("#Booking\s+status\s*:\s*(\w+)#", $pdf);

                // ReservationDate
                $it["ReservationDate"] = strtotime(str_replace("/", ".", re("#Booking\s+date\s*:\s*(\d+/\d+/\d+)#", $pdf)));

                // NoItineraries
                // TripCategory

                preg_match_all("#FLIGHT\s+DATA\s*:\s*(.*?)(?:DATOS\s+VUELO|\n\s*\n)#msi", $pdf, $segments);

                // $year = $this->http->FindSingleNode("//*[contains(text(),'BOOKING') and contains(text(),'DETAILS')]/ancestor::table[1]/following-sibling::table[3]//td[2]", null, true, "#(\d+)$#");

                foreach ($segments[1] as $segment) {
                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = re("#Flight\s+Number\s*:\s*\w{2}\s+(\d+)#", $segment);

                    // DepCode
                    $itsegment['DepCode'] = re("#Departure\s*:\s*([A-Z]{3})\s+(\d+/\d+/\d+)\s*-\s*(\d+:\d+)#ms", $segment);

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime(str_replace("/", ".", re(2) . ", " . re(3)));

                    // ArrCode
                    $itsegment['ArrCode'] = re("#Arrival\s*:\s*([A-Z]{3})\s+(\d+/\d+/\d+)\s*-\s*(\d+:\d+)#ms", $segment);

                    // ArrName
                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(str_replace("/", ".", re(2) . ", " . re(3)));

                    // AirlineName
                    $itsegment['AirlineName'] = re("#Flight\s+Number\s*:\s*(\w{2})\s+\d+#", $segment);

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
            },

            $this->Types['Event'] => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);
                $text = text($this->http->Response['body']);

                $it = [];

                $it['Kind'] = "E";
                // ConfNo
                $it["ConfNo"] = re("#Provider reference\s*:\s*([^\n]+)#", $pdf);
                // TripNumber
                // Name
                $it["Name"] = $this->getField("Servicio:");
                // StartDate
                $it["StartDate"] = strtotime(str_replace("/", ".", $this->getField("Inicio")));
                // EndDate
                $it["EndDate"] = strtotime(str_replace("/", ".", $this->getField("Inicio")));
                // Address
                $it["Address"] = re("#Zona\s+/\s+Zone\s+([^\n]+)#ms", $pdf);
                // Phone
                // DinerName
                $guests = $this->getNames($pdf);

                if (isset($guests[0])) {
                    $it["DinerName"] = $guests[0];
                }

                // Guests
                $it["Guests"] = $this->getField("Número Pasajeros");
                // TotalCharge
                // Currency
                // Tax
                // SpentAwards
                // EarnedAwards
                // AccountNumbers
                // Status
                $it["Status"] = re("#Booking\s+status\s*:\s*(\w+)#", $pdf);
                // Cancelled
                // ReservationDate
                $it["ReservationDate"] = strtotime(str_replace("/", ".", re("#Booking\s+date\s*:\s*(\d+/\d+/\d+)#", $pdf)));
                // NoItineraries
                $itineraries[] = $it;
            },

            "Other" => function (&$itineraries) {
                $pdf = text($this->pdf->Response['body']);

                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = $this->getFieldPdf("Flight ref");
                // TripNumber
                // Passengers
                $it['Passengers'] = $this->getFieldPdf("Persona", true);
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

                $flights = $this->getFieldPdf("Vuelo", true);

                foreach ($flights as $num=> $flight) {
                    $itsegment = [];
                    // FlightNumber
                    if (isset($this->getFieldPdf("Flight Number", true)[$num])) {
                        $itsegment['FlightNumber'] = re("#(\w{2})\s*(\w+)#", $this->getFieldPdf("Flight Number", true)[$num], 2);
                    }

                    // DepCode
                    $itsegment['DepCode'] = re("#^([A-Z]{3})\s*\(#", $flight);

                    // DepName
                    // DepDate
                    if (isset($this->getFieldPdf("Salida", true)[$num])) {
                        $itsegment['DepDate'] = strtotime(str_replace("/", ".", re("#(\d+/\d+/\d+)\s*-\s*(\d+:\d+)#", $this->getFieldPdf("Salida", true)[$num])) . ', ' . re(2));
                    }

                    // ArrCode
                    $itsegment['ArrCode'] = re("#\s+([A-Z]{3})\s*\(#", $flight);

                    // ArrName
                    // ArrDate
                    if (isset($this->getFieldPdf("Llegada", true)[$num])) {
                        $itsegment['ArrDate'] = strtotime(str_replace("/", ".", re("#(\d+/\d+/\d+)\s*-\s*(\d+:\d+)#", $this->getFieldPdf("Llegada", true)[$num])) . ', ' . re(2));
                    }

                    // AirlineName
                    if (isset($this->getFieldPdf("Flight Number", true)[$num])) {
                        $itsegment['AirlineName'] = re("#(\w{2})\s*(\w+)#", $this->getFieldPdf("Flight Number", true)[$num], 1);
                    }

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
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && (strpos($body, $this->reBody2) !== false || strpos($body, $this->reBody3) !== false);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->text = text($this->http->Response['body']);
        $this->parser = $parser;
        $itineraries = [];
        $this->pdf = clone $this->http;

        // PDF
        $pdfs = $this->parser->searchAttachmentByName('.*pdf');

        if (isset($pdfs[0])) {
            $pdf = $pdfs[0];

            if (($pdfHtml = \PDF::convertToHtml($this->parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
                return null;
            }
        } else {
            return null;
        }
        $pdfHtml = explode("<hr/>", $pdfHtml);

        // HTML
        $rules = array_map(function ($s) { return "normalize-space(text())='{$s}'"; }, $this->Types);
        sort($rules);
        $rule = implode(" or ", $rules);

        $xpath = "//*[{$rule}]/ancestor::table[2]";
        $nodes = $this->http->XPath->query($xpath);

        // Parse
        foreach ($nodes as $node) {
            $body = $node->ownerDocument->saveXML($node);
            $this->http->setBody($body);

            foreach ($this->processors as $re => $processor) {
                $this->pdf->setBody("");

                foreach ($pdfHtml as $part) {
                    if (stripos($part, $re)) {
                        $this->pdf->setBody($part);

                        break;
                    }
                }

                if (stripos($body, $re)) {
                    $processor($itineraries);

                    break;
                }
            }
        }

        if ($nodes->length == 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');

            if (isset($pdfs[0])) {
                $pdf = $pdfs[0];

                if (($body = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) !== null) {
                    $this->pdf->SetBody($body);
                } else {
                    return null;
                }
            } else {
                return null;
            }
            $processor = $this->processors["Other"];
            $processor($itineraries);
        }

        $result = [
            'emailType'  => 'Reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ["es"];
    }

    public static function getEmailTypesCount()
    {
        return 6;
    }

    private function getField($str)
    {
        if (!is_array($str)) {
            $str = [$str];
        }
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(text())=\"{$s}\""; }, $str));

        return $this->http->FindSingleNode("(//*[{$rule}]/following-sibling::*[1])[1]");
    }

    private function getFieldPdf($str, $many = false)
    {
        if (!is_array($str)) {
            $str = [$str];
        }
        $str = implode(" or ", array_map(function ($s) { return "contains(normalize-space(text()), '{$s}')"; }, $str));

        if ($many) {
            return $this->pdf->FindNodes("//*[{$str}]/following::text()[string-length(normalize-space(.))>1][1]"); //return $this->pdf->FindNodes("//*[{$str}]/ancestor::p[1]/following-sibling::p[1]");
        } else {
            return $this->pdf->FindSingleNode("//*[{$str}]/following::text()[string-length(normalize-space(.))>1][1]"); //return $this->pdf->FindSingleNode("//*[{$str}]/ancestor::p[1]/following-sibling::p[1]");
        }
    }

    private function getNames($str)
    {
        if (preg_match_all("#(?:Pasajero|Persona)\s+/\s+Passenger\s+\d+\s*:?\s*([^\n\(,]+)#msi", $str, $Names)) {
            return array_map("beautifulName", array_map(function ($s) { return trim($s, " *"); }, $Names[1]));
        }
        $names = explode("\n", trim(re("#Client\s+Name\s*:\s*\n(.*?)\n[^\n]+\s+/#msi", $str)));

        if (!empty($names)) {
            return array_map("beautifulName", $names);
        }

        return null;
    }
}
