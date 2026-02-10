<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Engine\MonthTranslate;
use PlancakeEmailParser;

class TripItinerary extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-11575640.eml, amadeus/it-11575642.eml, amadeus/it-11575767.eml";

    private $detects = [
        'es'  => 'Notificación de Reserva',
        'es1' => 'Notificación de Confirmación',
    ];

    private $lang = 'es';

    private $prov = 'BCD Travel';

    private static $dict = [
        'es' => [],
    ];

    private $from = '/[@.]amadeus\.com/';

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $class = explode('\\', __CLASS__);
        $total = $this->http->FindSingleNode("//td[contains(normalize-space(.), 'TOTAL ESTIMADO DEL VIAJE') and not(.//td)]/following-sibling::td[1]");
        $amount = '';
        $currency = '';

        if (preg_match('/([\d\.]+)\s*([A-Z]{3})/', $total, $m)) {
            $amount = $m[1];
            $currency = $m[2];
        }

        return [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseEmail(),
                'TotalCharge' => [
                    'Amount'   => $amount,
                    'Currency' => $currency,
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(): array
    {
        $its = [];

//        AIR
        $xpathAir = "//tr[descendant::img[contains(@src, 'vuelos')] and descendant::td[contains(., 'SALIDA')] and not(.//tr)]";
        $airs = $this->http->XPath->query($xpathAir);

        if (0 === $airs->length) {
            $this->logger->info("Segments did not found by xpath for AIR: {$xpathAir}");
        }

        foreach ($airs as $air) {
            if (($it = $this->parseAir($air)) && ($key = $this->issetAir($it, $its)) !== false && isset($it['TripSegments'])) {
                $its[$key]['TripSegments'] = array_merge($it['TripSegments'], $its[$key]['TripSegments']);
                unset($it);
            } else {
                $its[] = $this->parseAir($air);
            }
        }

//        HOTEL
        $xpathHotel = "//tr[descendant::img[contains(@src, 'hotel')] and following-sibling::tr[1]/descendant::td[contains(., 'ENTRADA')] and not(.//tr)]";
        $hotels = $this->http->XPath->query($xpathHotel);

        if (0 === $hotels->length) {
            $this->logger->info("Segments did not found by xpath for HOTEL: {$xpathHotel}");
        }

        foreach ($hotels as $hotel) {
            $its[] = $this->parseHotel($hotel);
        }

//        CRUISE
        $xpathCruise = "//tr[descendant::img[contains(@src, 'barco')] and following-sibling::tr[1]/descendant::td[contains(., 'SALIDA')] and not(.//tr)]";
        $cruises = $this->http->XPath->query($xpathCruise);

        if (0 === $cruises->length) {
            $this->logger->info("Segments did not found by xpath for CRUISE: {$xpathCruise}");
        }

        foreach ($cruises as $cruise) {
            $its[] = $this->parseCruise($cruise);
        }

//        BUS
        $xpathCruise = "//tr[descendant::img[contains(@src, 'bus')] and following-sibling::tr[1]/descendant::td[contains(., 'SALIDA')] and not(.//tr)]";
        $cruises = $this->http->XPath->query($xpathCruise);

        if (0 === $cruises->length) {
            $this->logger->info("Segments did not found by xpath for BUS: {$xpathCruise}");
        }

        foreach ($cruises as $cruise) {
            $its[] = $this->parseBus($cruise);
        }

//        TRAIN
        $xpathTrain = "//tr[descendant::img[contains(@src, 'tren')] and following-sibling::tr[1]/descendant::td[contains(., 'SALIDA')] and not(.//tr)]";
        $trains = $this->http->XPath->query($xpathTrain);

        if (0 === $trains->length) {
            $this->logger->info("Segments did not found by xpath for TRAIN: {$xpathTrain}");
        }

        foreach ($trains as $train) {
            if (($it = $this->parseTrain($train)) && ($key = $this->issetAir($it, $its)) !== false && isset($it['TripSegments'])) {
                $its[$key]['TripSegments'] = array_merge($it['TripSegments'], $its[$key]['TripSegments']);
                unset($it);
            } else {
                $its[] = $this->parseTrain($train);
            }
        }

        return $its;
    }

    private function issetAir($it, $airsInfo)
    {
        foreach ($airsInfo as $key => $air) {
            if ($it['RecordLocator'] === $air['RecordLocator']) {
                return $key;
            }
        }

        return false;
    }

    private function getPassenger()
    {
        return $this->http->FindSingleNode("//td[contains(normalize-space(.), 'Viajero') and not(.//td)]/following-sibling::td[1]");
    }

    private function parseBus(\DOMNode $node)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['TripCategory'] = TRIP_CATEGORY_BUS;

        $it['RecordLocator'] = $this->getNode($node, 'Localizador de billete');

        $it['Passengers'][] = $this->getPassenger();

        $it['Status'] = $this->getNode($node, 'Situación de reserva');

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA') and not(.//tr)]/td[last()]", $node));

        $seg['DepName'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA') and not(.//tr)]/following-sibling::tr[1]/td[last()]", $node));

        $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA') and not(.//tr)]/td[last()]", $node));

        $seg['ArrName'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA') and not(.//tr)]/following-sibling::tr[1]/td[last()]", $node));

        if (!empty($seg['DepDate']) && !empty($seg['ArrDate'])) {
            $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseCruise(\DOMNode $node)
    {
        /** @var \AwardWallet\ItineraryArrays\CruiseTrip $it */
        $it = ['Kind' => 'T'];

        $it['TripCategory'] = TRIP_CATEGORY_CRUISE;

        $it['RecordLocator'] = $this->getNode($node, 'Localizador de billete');

        $it['Passengers'][] = $this->getPassenger();

        $it['Status'] = $this->getNode($node, 'Situación de reserva');

        /** @var \AwardWallet\ItineraryArrays\CruiseTripSegment $seg */
        $seg = [];

        $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA') and not(.//tr)]/td[last()]", $node));

        $seg['DepName'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA') and not(.//tr)]/following-sibling::tr[1]/td[last()]", $node));

        $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA') and not(.//tr)]/td[last()]", $node));

        $seg['ArrName'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA') and not(.//tr)]/following-sibling::tr[1]/td[last()]", $node));

        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseHotel(\DOMNode $node)
    {
        /** @var \AwardWallet\ItineraryArrays\Hotel $it */
        $it = ['Kind' => 'R'];

        $it['CheckInDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'ENTRADA')]/descendant::td[last()]", $node));

        $it['CheckOutDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA')]/descendant::td[last()]", $node));

        $it['Status'] = $this->getNode($node, 'Situación de reserva:');

        $it['Address'] = $this->getNode($node, 'Dirección');

        $it['Phone'] = $this->getNode($node, 'Teléfono');

        $it['RoomType'] = $this->getNode($node, 'Tipo de habitación');

        $it['ConfirmationNumber'] = $this->getNode($node, 'Número de confirmación:');

        $it['CancellationPolicy'] = $this->getNode($node, 'Política de cancelación:');

        if (empty($it['CancellationPolicy'])) {
            $it['CancellationPolicy'] = $this->http->FindSingleNode("following::td[contains(normalize-space(.), 'POLITICA CANCELACION') and not(.//td)]", $node, true, '/POLITICA CANCELACION\s*:\s*(.+)/');
        }

        $it['Rate'] = $this->getNode($node, 'Tarifa');

        $it['HotelName'] = $this->http->FindSingleNode('.', $node);

        $it['GuestNames'][] = $this->getPassenger();

        return $it;
    }

    private function parseAir(\DOMNode $node)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode($node, 'Localizador compañía aérea', '/[\/]?([A-Z\d]{5,9})/');

        $it['Passengers'][] = $this->getPassenger();

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode('descendant::td[last()]', $node));

        $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA')]/td[last()]", $node));

        foreach ([
            'Departure' => $this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[last()]', $node),
            'Arrival'   => $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA')]/following-sibling::tr[1]/descendant::td[last()]", $node),
        ] as $key => $value) {
            if (false !== stripos($value, 'Terminal') && ($info = explode('TERMINAL', $value))) {
                $seg[substr($key, 0, 3) . 'Name'] = trim($info[0]);
                $seg[$key . 'Terminal'] = trim($info[1]);
            } else {
                $seg[substr($key, 0, 3) . 'Name'] = $value;
            }
        }

        $flight = $this->getNode($node, 'Número de vuelo');

        if (preg_match('/([A-Z\d]{2})\s*(\d+)/', $flight, $m)) {
            $seg['AirlineName'] = $m[1];
            $seg['FlightNumber'] = $m[2];
        }

        if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
            $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        $seg['Duration'] = $this->getNode($node, 'Duración');

        $seg['Aircraft'] = $this->getNode($node, 'Tipo de avión');

        $seg['Meal'] = $this->getNode($node, 'Comida a bordo');

        if (preg_match('/(\w+)\s+([A-Z])/', $this->getNode($node, 'Clase'), $m)) {
            $seg['Cabin'] = $m[1];
            $seg['BookingClass'] = $m[2];
        }

        $it['Status'] = $this->getNode($node, 'Situación de reserva');

        $it['TripSegments'][] = $seg;

        return $it;
    }

    private function parseTrain(\DOMNode $node)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->getNode($node, 'Localizador de billete', '/[\/]?([A-Z\d]{5,9})/');

        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        $it['TicketNumbers'][] = $this->getNode($node, 'Número de billete');

        $it['Passengers'][] = $this->getPassenger();

        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];

        $seg['DepDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA')]/descendant::td[last()]", $node));

        $seg['ArrDate'] = $this->normalizeDate($this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA')]/descendant::td[last()]", $node));

        foreach ([
            'Departure' => $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'SALIDA')]/following-sibling::tr[1]/descendant::td[last()]", $node),
            'Arrival'   => $this->http->FindSingleNode("following-sibling::tr[contains(normalize-space(.), 'LLEGADA')]/following-sibling::tr[1]/descendant::td[last()]", $node),
        ] as $key => $value) {
            if (false !== stripos($value, 'Terminal') && ($info = explode('TERMINAL', $value))) {
                $seg[substr($key, 0, 3) . 'Name'] = trim($info[0]);
                $seg[$key . 'Terminal'] = trim($info[1]);
            } else {
                $seg[substr($key, 0, 3) . 'Name'] = $value;
            }
        }

        $flight = $this->getNode($node, 'Número de tren');

        if (preg_match('/(\d+)\s+(\w+)/', $flight, $m)) {
            $seg['FlightNumber'] = $m[1];
            $seg['AirlineName'] = $m[2];
        }

        if (!empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
            $seg['DepCode'] = $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }

        $seg['Cabin'] = $this->getNode($node, 'Clase');

        $seg['Seats'][] = $this->getNode($node, 'Asiento');

        $it['TripSegments'][] = $seg;

        $it['Status'] = $this->getNode($node, 'Situación de reserva');

        return $it;
    }

    private function getNode(\DOMNode $node, string $str, $re = null)
    {
        return $this->http->FindSingleNode("ancestor::table[1]/following-sibling::table[1]/descendant::tr[contains(normalize-space(.), '{$str}') and not(.//tr)]/descendant::td[last()]", $node, true, $re);
    }

    private function normalizeDate($str)
    {
//        $this->logger->info($str);
        $regExps = [
            '/^\w+,\s+(?<day>\d{1,2})\s+(?<month>\w+)\s+(?<year>\d{2,4})\s+(?<time>\d{1,2}:\d{2})$/u',
        ];

        foreach ($regExps as $regExp) {
            if (preg_match($regExp, $str, $m)) {
                return strtotime($m['day'] . ' ' . MonthTranslate::translate($m['month'], $this->lang) . ' ' . $m['year'] . ', ' . $m['time']);
            }
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
