<?php

namespace AwardWallet\Engine\aeromexico\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-3.eml";

    public function parseReservation(&$itineraries)
    {
        $reserv = $this->http->FindSingleNode(".//*[contains(text(),'Código de reservación')]/text()");

        if (preg_match('#:\s*(.*)#', $reserv, $reservationNumber)) {
            $reservationNumber = $reservationNumber[1];
            $itineraries['RecordLocator'] = $reservationNumber;
        }
        $segmentsTemp = $this->http->XPath->query(".//*[contains(text(),'Código de reservación')]/../../*");
        $segments = [];

        if ($segmentsTemp->length > 0) {
            foreach ($segmentsTemp as $segment) {
                $segments[] = $segment->nodeValue;
            }
        }

        foreach ($segments as $key => &$segment) {
            $text = utf8_encode($segment);
            $text = preg_replace('/ {2,}/', ' ', $text);

            $text = preg_replace('/[^a-z0-9\:\-\)\(\n \pL\']+/iu', '', $text);

            $text = trim($text);

            if (empty($text)) {
                continue;
            }
            $segment = $text;
        }
        $pass = [];

        foreach ($segments as $key => &$segment) {
            if (stripos($segment, 'Fecha') !== false && stripos($segment, 'Partida') !== false) {
                if (strlen(arrayVal($segments, $key - 2)) > 0) {
                    $pass[] = substr($segments[$key - 2], 0, strpos($segments[$key - 2], '-') - 1);
                }

                $data = preg_replace('/\s+$/m', ';', $segment);
                $data = trim(substr($data, stripos($data, 'Asiento;') + strlen('Asiento;'), strlen($data)));
                $blocks = explode(';', $data);

                if (count($blocks) < 5) {
                    $blocks = array_merge($blocks, ["", "", "", "", ""]);
                }
                $depDate = $blocks[0];
                preg_match('#(\d*)\:(\d*)#', $blocks[1], $depTime);
                $depTime = $depTime[0];
                $depDateResult = $depDate . ' ' . $depTime;
                preg_match('#(\d*)\:(\d*)#', $blocks[2], $arrTime);
                $arrTime = $arrTime[0];
                $arrDateResult = $depDate . ' ' . $arrTime;
                preg_match('#(.*)\s*\d*\:\d*#', $blocks[1], $depName);

                if (!empty($depName[1])) {
                    $depName = trim($depName[1]);
                } else {
                    $depName = '';
                }
                preg_match('#(.*)\s*\d*\:\d*#', $blocks[2], $arrName);

                if (!empty($arrName[1])) {
                    $arrName = trim($arrName[1]);
                } else {
                    $arrName = '';
                }

                preg_match_all('#([A-z]*)\s*\d*#Si', $blocks[3], $airlineName);

                if (!empty($airlineName[0][1])) {
                    $airlineName = $airlineName[0][1];
                } else {
                    $airlineName = '';
                }
                preg_match('#Confirmado?\s*(.*)#', trim($blocks[4]), $class);

                if (!empty($class[1])) {
                    $class = $class[1];
                } else {
                    $class = '';
                }
                preg_match('#(\d*\S*)#', trim($blocks[5]), $seat);
                $seats = [];

                if (!empty($seat[0])) {
                    $seats[] = $seat[0];
                }
                $itineraries['Passengers'] = $pass;

                $itineraries['TripSegments'][] = [
                    'FlightNumber' => $airlineName,
                    'DepCode'      => TRIP_CODE_UNKNOWN,
                    'ArrCode'      => TRIP_CODE_UNKNOWN,
                    'DepDate'      => strtotime($depDateResult),
                    'ArrDate'      => strtotime($arrDateResult),
                    'ArrName'      => $arrName,
                    'DepName'      => $depName,
                    'Cabin'        => $class,
                    'Seats'        => implode(',', $seats),
                ];
            }
        }
    }

    public function parseDetails(&$itineraries, $subject)
    {
        $record = $this->http->XPath->query(".//*[contains(text(),'Pasajero(s)')]/../../../td[2]");

        $itineraries['Passengers'] = [trim($record->item(0)->nodeValue)];
        preg_match('#PNR\s*(\S*)#', $subject, $reservation);

        if (!empty($reservation[1])) {
            $reservation = $reservation[1];
        }
        $itineraries['RecordLocator'] = $reservation;
        $segmentsTemp = $this->http->XPath->query(".//*[contains(text(),'Vuelo')]/ancestor::*[count(tr) = 2]/tr[2]/td");
        $segments = [];

        if ($segmentsTemp->length > 0) {
            foreach ($segmentsTemp as $segment) {
                $segments[] = $segment->nodeValue;
            }
        }

        foreach ($segments as $key => &$segment) {
            $text = utf8_encode($segment);
            $text = preg_replace('/ {2,}/', ' ', $text);

            $text = preg_replace('/[^a-z0-9\:\-\)\(\n \pL\']+/iu', '', $text);

            $text = trim($text);

            if (empty($text)) {
                continue;
            }
            $segment = $text;
        }
        $fNum = preg_replace('/[^a-z0-9]+/iu', '', $segments[0]);
        preg_match('#\((\S*)\)#', $segments[1], $depCode);

        if (!empty($depCode[1])) {
            $depCode = $depCode[1];
        }
        preg_match('#\((\S*)\)#', $segments[2], $arrCode);

        if (!empty($arrCode[1])) {
            $arrCode = $arrCode[1];
        }

        preg_match('#\S+#', $segments[1], $depName);

        if (!empty($depName[0])) {
            $depName = trim($depName[0]);
        } else {
            $depName = $depCode;
        }
        preg_match('#\S+#', $segments[2], $arrName);

        if (!empty($arrName[0])) {
            $arrName = trim($arrName[0]);
        } else {
            $arrName = $arrCode;
        }
        $date = ucwords($segments[3]);
        $date = trim(preg_replace('/ {2,}/', ' ', $date));
        $date = substr($date, 4, strlen($date));
        $depDate = strtotime($date);
        $arrDate = $depDate;
        $itineraries['TripSegments'][] = [
            'FlightNumber' => $fNum,
            'DepDate'      => $depDate,
            'ArrDate'      => $arrDate,
            'DepCode'      => $depCode,
            'ArrCode'      => $arrCode,
            'DepName'      => $depName,
            'ArrName'      => $arrName,
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // return null; // disable old parser
        $itineraries = [];
        $itineraries['Kind'] = 'T';

        if (stripos($parser->getHTMLBody(), 'Datos de tu vuelo en') === false) {
            $this->parseReservation($itineraries);
        } else {
            $this->parseDetails($itineraries, $parser->getSubject());
        }
        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];

        return $result;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && (stripos($headers['subject'], 'Your flight details') !== false && stripos($headers['subject'], 'Datos de tu vuelo') !== false)
            && isset($headers['from']) && preg_match("/[\.@]aeromexico\.com/", $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Gracias porvolar con Aeroméxico') !== false && stripos($parser->getHTMLBody(), 'Fecha Salida') !== false;
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ["es"];
    }
}
