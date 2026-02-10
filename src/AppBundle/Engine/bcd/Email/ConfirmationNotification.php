<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class ConfirmationNotification extends \TAccountChecker
{
    public $mailFiles = "bcd/it-11575666.eml, bcd/it-11575767.eml";

    public $reSubject = [
        'es' => 'Itinerario', 'Notificación de Confirmación:',
    ];

    public $reBody2 = [
        'es'  => 'Notificación de Confirmación',
        'es2' => 'Notificación de confirmación',
        'es3' => 'Notificación de Reserva',
        'es4' => 'Notificación de reserva',
    ];

    public static $dictionary = [
        'es' => [
            'status'     => ['Situación de reserva:', 'Situación de la reserva:'],
            'totalPrice' => ['TOTAL ESTIMADO DEL VIAJE', 'TOTAL ESTIMADO DEL VIAJE*', 'TOTAL ESTIMADO DEL VIAJE *'],
            // FLIGHT / BUS
            'departure' => ['SALIDA:', 'Salida:'],
            'arrival'   => ['LLEGADA:', 'Llegada:'],
            'aircraft'  => ['Tipo de avión', 'Tipo de avión:'],
            // HOTEL
            'checkIn'  => ['ENTRADA', 'Entrada'],
            'checkOut' => ['SALIDA:', 'Salida:'],
            'phone'    => ['Telefono:', 'Teléfono:'],
        ],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries): void
    {
        $hotels = [];
        $flights = [];
        $cars = [];
        $cruises = [];
        $buses = [];

        foreach ($this->http->XPath->query("//img[contains(@src, 'img/confirmacion') or contains(@src, '/img/reserva/')]") as $img) {
            $type = $this->http->FindSingleNode("./@src", $img, true, "#(?:/img/confirmacion/|/img/reserva/)(.*?)[A-Z]{2}.png#");

            switch ($type) {
                case "visado":
                case "seguro":
                    break;

                break;

                case "vuelos":
                    $flights[] = $this->http->XPath->query("./ancestor::tr[1]/..", $img)->item(0);

                break;

                case "hotel":
                    $hotels[] = $this->http->XPath->query("./ancestor::tr[1]/..", $img)->item(0);

                break;

                case "coche":
                    $cars[] = $this->http->XPath->query("./ancestor::tr[1]/..", $img)->item(0);

                break;

                case "barco":
                    $cruises[] = $this->http->XPath->query("./ancestor::tr[1]/..", $img)->item(0);

                break;

                case "bus":
                    $buses[] = $this->http->XPath->query("./ancestor::tr[1]/..", $img)->item(0);

                break;

                default:
                    $this->logger->info("unknown type " . $type);

                    return;
            }
        }

        //#################
        //##   FLIGHT   ###
        //#################
        $airs = [];

        foreach ($flights as $head) {
            if (($body = $this->http->XPath->query("./following::table[1]", $head)->item(0)) == null) {
                $this->logger->info("flight body root is null");

                return;
            }

            if (!$rl = $this->http->FindSingleNode(".//td[" . $this->eq("Localizador compañía aérea:") . "]/following-sibling::td[1]", $body, true, "#^(?:\w{2}/)?(\w+)$#")) {
                $this->logger->info("RL not matched");

                return;
            }
            $airs[$rl][] = [$head, $body];
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//td[" . $this->eq("Localizador:") . "]/following-sibling::td[1]");

            // Passengers
            $it['Passengers'] = [$this->http->FindSingleNode("//td[" . $this->eq("Viajero:") . "]/following-sibling::td[1]")];

            // AccountNumbers
            // Cancelled
            if (count($segments) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $segments[0][1]));

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $segments[0][1]));
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('status')) . "]/following-sibling::td[1]", $segments[0][1]);

            $tickets = [];

            foreach ($segments as $data) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//td[" . $this->eq("Número de vuelo:") . "]/following-sibling::td[1]", $data[1], true, "# (?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) (\d+)$#");

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $data[0], true, "#(.*?)(?: Terminal.+|$)#i");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $data[0], true, "#Terminal (.+)#i");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/following-sibling::td[1]", $data[0])));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $data[0], true, "#(.*?)(?: Terminal.+|$)#i");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $data[0], true, "#Terminal (.+)#i");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/following-sibling::td[1]", $data[0])));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode(".//td[" . $this->eq("Número de vuelo:") . "]/following-sibling::td[1]", $data[1], true, "# ([A-Z][A-Z\d]|[A-Z\d][A-Z]) \d+$#");

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('aircraft')) . "]/following-sibling::td[1]", $data[1]);

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode(".//td[" . $this->eq("Clase:") . "]/following-sibling::td[1]", $data[1], true, "#^(.*?) [A-Z]$#");

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode(".//td[" . $this->eq("Clase:") . "]/following-sibling::td[1]", $data[1], true, "# ([A-Z])$#");

                // Seats
                $seat = $this->http->FindSingleNode(".//td[" . $this->eq("Asiento:") . "]/following-sibling::td[normalize-space()]", $data[1], true, "/^(\d+[A-Z])[ *]*$/");

                if ($seat) {
                    $itsegment['Seats'] = [$seat];
                }

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode(".//td[" . $this->eq("Duración:") . "]/following-sibling::td[1]", $data[1]);

                // Meal
                $itsegment['Meal'] = $this->http->FindSingleNode(".//td[" . $this->eq("Comida a bordo:") . "]/following-sibling::td[1]", $data[1]);

                $ticket = $this->http->FindSingleNode(".//td[" . $this->eq("Número de billete:") . "]/following-sibling::td[normalize-space()]", $data[1], true, "/^(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})[ *]*$/");

                if ($ticket) {
                    $tickets[] = $ticket;
                }

                $it['TripSegments'][] = $itsegment;
            }

            if (count($tickets) > 0) {
                $it['TicketNumbers'] = array_unique($tickets);
            }

            $itineraries[] = $it;
        }

        //################
        //##   HOTEL   ###
        //################
        foreach ($hotels as $head) {
            if (($body = $this->http->XPath->query("./following::table[1]", $head)->item(0)) == null) {
                $this->logger->info("hotel body root is null");

                return;
            }

            $it = [];
            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->http->FindSingleNode(".//td[" . $this->eq("Número de confirmación:") . "]/following-sibling::td[1]", $body);

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//td[" . $this->eq("Localizador:") . "]/following-sibling::td[1]");

            // HotelName
            $it['HotelName'] = $this->http->FindSingleNode("./tr[1]/td[2]", $head);

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('checkIn')) . "]/following-sibling::td[1]", $head)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('checkOut')) . "]/following-sibling::td[1]", $head)));

            // Address
            $it['Address'] = $this->http->FindSingleNode(".//td[" . $this->eq("Dirección:") . "]/following-sibling::td[1]", $body);

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('phone')) . "]/following-sibling::td[1]", $body);

            // Fax
            // GuestNames
            $it['GuestNames'] = [$this->http->FindSingleNode("//td[" . $this->eq("Viajero:") . "]/following-sibling::td[1]")];

            // Guests
            // Kids
            // Rooms
            // Rate
            // RateType
            // CancellationPolicy
            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//td[" . $this->eq("Tipo de habitación:") . "]/following-sibling::td[1]", $body);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->amount($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('status')) . "]/following-sibling::td[1]", $body);

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //###############
        //##   CARS   ###
        //###############
        foreach ($cars as $head) {
            if (($body = $this->http->XPath->query("./following::table[1]", $head)->item(0)) == null) {
                $this->logger->info("car body root is null");

                return;
            }
            $it = [];

            $it['Kind'] = "L";

            // Number
            $it['Number'] = $this->http->FindSingleNode(".//td[" . $this->eq("Número de confirmación:") . "]/following-sibling::td[1]", $body);
            // TripNumber
            // PickupDatetime
            $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq("RECOGIDA:") . "]/following-sibling::td[1]", $head)));

            // PickupLocation
            $it['PickupLocation'] = $this->http->FindSingleNode(".//td[" . $this->eq("RECOGIDA:") . "]/ancestor::tr[1]/following-sibling::tr[1]", $head);

            // DropoffDatetime
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq("DEVOLUCIÓN:") . "]/following-sibling::td[1]", $head)));

            // DropoffLocation
            $it['DropoffLocation'] = $this->http->FindSingleNode(".//td[" . $this->eq("DEVOLUCIÓN:") . "]/ancestor::tr[1]/following-sibling::tr[1]", $head);

            // PickupPhone
            // PickupFax
            // PickupHours
            // DropoffPhone
            // DropoffHours
            // DropoffFax
            // RentalCompany
            // CarType
            $it['CarType'] = $this->http->FindSingleNode(".//td[" . $this->eq("Tipo de coche:") . "]/following-sibling::td[1]", $body);

            // CarModel
            // CarImageUrl
            // RenterName
            $it['RenterName'] = $this->http->FindSingleNode("//td[" . $this->eq("Viajero:") . "]/following-sibling::td[1]");

            // PromoCode
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // TotalTaxAmount
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('status')) . "]/following-sibling::td[1]", $body);

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

        //##############
        //##   BUS   ###
        //##############
        foreach ($buses as $head) {
            if (($body = $this->http->XPath->query("./following::table[1]", $head)->item(0)) == null) {
                $this->logger->info("bus body root is null");

                return;
            }
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->http->FindSingleNode(".//td[" . $this->eq("Localizador de billete:") . "]/following-sibling::td[1]", $body);

            // TripNumber
            // Passengers
            $it['Passengers'] = [$this->http->FindSingleNode("//td[" . $this->eq("Viajero:") . "]/following-sibling::td[1]")];

            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // BaseFare
            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('status')) . "]/following-sibling::td[1]", $body);

            // ReservationDate
            // NoItineraries
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_BUS;

            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $head);

            // DepAddress
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/following-sibling::td[1]", $head)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $head);

            // ArrAddress
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/following-sibling::td[1]", $head)));

            // Type
            $itsegment['Type'] = "Bus";

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

            $itineraries[] = $it;
        }

        //#################
        //##   CRUISE   ###
        //#################
        foreach ($cruises as $head) {
            if (($body = $this->http->XPath->query("./following::table[1]", $head)->item(0)) == null) {
                $this->logger->info("cruise body root is null");

                return;
            }
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $this->http->FindSingleNode(".//td[" . $this->eq("Localizador de billete:") . "]/following-sibling::td[1]", $body);

            // TripNumber
            // Passengers
            $it['Passengers'] = [$this->http->FindSingleNode("//td[" . $this->eq("Viajero:") . "]/following-sibling::td[1]")];

            // AccountNumbers
            // Cancelled
            // TotalCharge
            $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // BaseFare
            // Currency
            $it['Currency'] = $this->currency($this->http->FindSingleNode(".//td[" . $this->eq("Tarifa:") . "]/following-sibling::td[1]", $body));

            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('status')) . "]/following-sibling::td[1]", $body);

            // ReservationDate
            // NoItineraries
            // TripCategory
            $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

            $itsegment = [];

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $head);
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('departure')) . "]/following-sibling::td[1]", $head)));
            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/ancestor::tr[1]/following-sibling::tr[1]", $head);
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//td[" . $this->eq($this->t('arrival')) . "]/following-sibling::td[1]", $head)));

            $it['TripSegments'][] = $itsegment;

            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'bcd-itinerary@es.amadeus.com') !== false
            || stripos($from, '@bcdtravel.es') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        $body = $parser->getHTMLBody();

        if (strpos($body, 'BCD TRAVEL') === false
            && $this->http->XPath->query('//a[contains(@href,".bcdtravel.es/") or contains(@href,"@bcdtravel.es") or contains(@href,"www.bcdtravel.es")]')->length === 0
        ) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ConfirmationNotification' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        $totalPrice = $this->http->FindSingleNode("//tr/*[normalize-space()][1][{$this->eq($this->t('totalPrice'))}]/following-sibling::*[normalize-space()]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/', $totalPrice, $matches)) {
            // 1221.09EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $result['parsedData']['TotalCharge']['Amount'] = PriceHelper::parse($matches['amount'], $currencyCode);
            $result['parsedData']['TotalCharge']['Currency'] = $matches['currency'];
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\s\d]+, (\d+) ([^\s\d]+) (\d{4}) (\d+:\d+)$#", //Jueves, 21 abril 2016 15:00
        ];
        $out = [
            "$1 $2 $3, $4",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }
}
