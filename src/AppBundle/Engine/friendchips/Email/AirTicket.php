<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\friendchips\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-5859576.eml, friendchips/it-6095621.eml, friendchips/it-7328853.eml";

    private static $detectBody = [
        'de' => 'Buchungsbestätigung',
        'en' => 'Booking Confirmation',
        'es' => 'Confirmación de la reserva',
    ];

    private $provider = 'TUIfly.com';

    private $dict = [
        'en' => [
            'Buchungsnummer' => 'Confirmation Number',
            'Rechnungsdatum' => 'Invoice Date',
            'Rechungsbetrag' => 'Invoice amount',
            'Passagiere'     => 'Passengers',
            'Hinflug'        => 'Outbound flight',
            'Rückflug'       => 'Return flight',
            'Handgepäck'     => ['Carry-on baggage', 'Hand luggage'],
        ],
        'es' => [
            'Buchungsnummer' => 'Numero de reserva',
            'Rechnungsdatum' => 'Fecha de la Factura',
            'Rechungsbetrag' => 'Importe de la factura',
            'Passagiere'     => 'Los pasajeros',
            'Hinflug'        => 'Ida',
            'Rückflug'       => 'Vuelta',
            'Handgepäck'     => 'Equipaje de mano',
        ],
    ];

    private $lang = '';

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->detectBody($parser);
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketHtml',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->provider) !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, $this->provider) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t('Buchungsnummer') . "')]/ancestor::td[1]/following-sibling::td[1]");
        $reservationDate = $this->http->FindSingleNode('//td[contains(., "' . $this->t('Rechnungsdatum') . '")]/following-sibling::td[1]');
        $it['ReservationDate'] = strtotime($this->normalizeDate($reservationDate));
        $total = $this->http->FindSingleNode("//td[contains(text(), '" . $this->t('Rechungsbetrag') . "')]/following-sibling::td[1]");

        if (preg_match('/([\d,\.]+)\s+(\D)/u', $total, $m)) {
            $it['TotalCharge'] = str_replace([','], ['.'], $m[1]);
            $it['Currency'] = str_replace(['€'], ['EUR'], $m[2]);
        }
        $psng = $this->http->FindNodes("//h2[contains(., '" . $this->t('Passagiere') . "')]/following-sibling::table[1]/descendant::tr/descendant::td[1]", null, '/\d+\.\s*(.+)/');
        $it['Passengers'] = $psng;

        $seats = $this->getSeats(count($psng));

        $xpath = "//text()[normalize-space(.) = '" . $this->t('Hinflug') . "' or normalize-space(.) = '" . $this->t('Rückflug') . "']/ancestor::h2[1]/following-sibling::table[count(descendant::tr) > 2 and (" . $this->getXPath($this->t('Handgepäck')) . ")][1]";

        $segments = $this->http->FindNodes($xpath);

        if ($this->http->XPath->query($xpath)->length !== 0) {
            $this->logger->info('Segments found by: ' . $xpath);
        }

        $flightsNumber = [];
        $flightInfo = [];

        foreach ($segments as $value) {
            $seg = preg_split('/((?:[A-Z]\d|\d[A-Z]|[A-Z]{2})\s+\b\d+\b)/u', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

            if (count($seg) % 2 !== 0) {
                array_pop($seg); // delete last value in array
            }

            foreach ($seg as $i => $s) {
                if ($i % 2 === 0) {
                    $flightInfo[] = $s;
                }

                if ($i % 2 !== 0) {
                    $flightsNumber[] = $s;
                }
            }
        }

        foreach ($flightInfo as $i => $info) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];
            $re = '/(?<DepName>(?:[\w\s\-\.]+|\w+\/\w+))\s+\((?<DepCode>[A-Z]{3})\)\s*\D\s*(?<ArrName>.+)\s+\((?<ArrCode>[A-Z]{3})\)\s*';
            $re .= '(?<Date>\d{1,2}\.\d{2}\.\d{4})\s+(?<DepTime>\d{2}:\d{2})\s+-\s+(?<ArrTime>\d{2}:\d{2})/u';

            if (preg_match($re, $info, $m)) {
                $date = $this->normalizeDate($m['Date']);

                if (preg_match("#[^-](- ){5,}\s*([\w-\s]+)#", $m['DepName'], $match)) {
                    $seg['DepName'] = $match[2];
                } else {
                    $seg['DepName'] = $m['DepName'];
                }
                $seg['DepCode'] = $m['DepCode'];
                $seg['DepDate'] = strtotime($date . ', ' . $m['DepTime']);
                $seg['ArrName'] = $m['ArrName'];
                $seg['ArrCode'] = $m['ArrCode'];
                $seg['ArrDate'] = strtotime($date . ', ' . $m['ArrTime']);
            }

            if (preg_match('/(\w+)\s+(\d+)/', $flightsNumber[$i], $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (!empty($seats)) {
                $seg['Seats'] = array_shift($seats);
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getXPath($str, $not = true, $delimiter = '.')
    {
        if (empty($str)) {
            return false;
        }

        if (is_array($str)) {
            if ($not === true) {
                $r = array_map(function ($s) use ($delimiter) {
                    return "not(contains(" . $delimiter . ", '" . $s . "'))";
                }, $str);
            } else {
                $r = array_map(function ($s) use ($delimiter) {
                    return "contains(" . $delimiter . ", '" . $s . "')";
                }, $str);
            }

            return ($not === true) ? implode(' and ', $r) : implode(' or ', $r);
        } elseif (is_string($str)) {
            if ($not === true) {
                return "not(contains(" . $delimiter . ", '" . $str . "'))";
            } else {
                return "contains(" . $delimiter . ", '" . $str . "')";
            }
        }

        return $str;
    }

    /**
     * get seats
     * Sitzplatz Hinflug: 10F+10F	 Sitzplatz Rückflug: 10F
     * Sitzplatz Hinflug: 10E+10E	 Sitzplatz Rückflug: 10E.
     *
     * @return array
     */
    private function getSeats($countsOfPassengers)
    {
        $seats = [];
        $arrs = [];
        $reArr = [];
        $xpath = "//h2[contains(text(), '" . $this->t('Passagiere') . "')]/following-sibling::table[1]/descendant::tr/";
        $nodesOut = $this->http->FindNodes($xpath . 'descendant::td[2]', null, '/:\s*(.+)/');
        $nodesRet = $this->http->FindNodes($xpath . 'descendant::td[3]', null, '/:\s*(.+)/');
        $nodes = array_merge($nodesOut, $nodesRet);

        foreach ($nodes as $i => $node) {
            if (stripos($node, '+') !== false) {
                $se = explode('+', $node);

                foreach ($se as $j => $s) { // 0 => [10F, 10E], 1 => [10F, 10E]
                    $arrs[$j][] = $s;
                }
            } elseif (preg_match('/([\dA-Z]{1,3})$/', $node, $m)) {
                $reArr[] = $m[1]; // 0 => [10F, 10E]
            }
        }

        foreach ($arrs as $arr) {
            foreach ($arr as $item) {
                $seats[] = $item;
            }
        }
        $seats = array_merge($seats, $reArr);

        if (is_int($countsOfPassengers) && $countsOfPassengers > 0) {
            return array_chunk($seats, $countsOfPassengers);
        }

        return null;
    }

    /**
     * 18.03.2014 - return 03/18/2014.
     *
     * @param $str
     *
     * @return bool|mixed
     */
    private function normalizeDate($str)
    {
        $patternReplace = [
            '/(\d{1,2})\.(\d{2})\.(\d{4})/' => '$2/$1/$3',
        ];

        foreach ($patternReplace as $pattern => $replace) {
            return preg_replace($pattern, $replace, $str);
        }

        return false;
    }

    private function t($str)
    {
        if (!isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$str])) {
            return $str;
        }

        return $this->dict[$this->lang][$str];
    }

    private function detectBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detectBody as $lang => $detect) {
            if (stripos($body, $detect) !== false && stripos($body, $this->provider) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }
}
