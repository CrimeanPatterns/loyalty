<?php

namespace AwardWallet\Engine\asia\Email;

class MobileBoardingPassHtml extends \TAccountChecker
{
    public $mailFiles = "asia/it-7061855.eml, asia/it-7103972.eml, asia/it-7116023.eml, asia/it-7154051.eml, asia/it-7193003.eml, asia/it-7213931.eml, asia/it-7230499.eml, asia/it-7239377.eml, asia/it-7270688.eml, asia/it-8411830.eml, asia/it-8473983.eml, asia/it-8497307.eml";

    public $reFrom = 'boardingpass@cathaypacific.com';

    private $lang = 'en';

    private $detects = [
        'en' => ['Please check your flight status online'],
        'it' => ['Ti consigliamo di verificare online'],
        'zh' => ['請於出發前往機場前查核航班狀況'],
        'nl' => ['Controleer voor vertrek naar de luchthaven uw vluchtstatus online'],
        'es' => ['Aspectos que conviene recordar'],
    ];

    private static $dict = [
        'en' => [],
        'it' => [
            'PASSENGER'     => 'PASSEGGERO',
            'FLIGHT'        => 'VOLO',
            'DEPARTURE'     => 'PARTENZA',
            'Boarding Pass' => 'Carta d\'imbarco',
            //			'We are pleased to invite' => '',
            'SEAT' => 'POSTO',
            //			'comfort of'
        ],
        'zh' => [
            'PASSENGER'     => '旅客',
            'FLIGHT'        => '航班',
            'DEPARTURE'     => '出發',
            'Boarding Pass' => '登機證',
            //			'We are pleased to invite' => '',
            'SEAT' => '座位',
            //			'comfort of'
        ],
        'nl' => [
            'PASSENGER'     => 'PASSAGIER',
            'FLIGHT'        => 'VLUCHT',
            'DEPARTURE'     => 'VERTREK',
            'Boarding Pass' => 'Instapkaart',
            //			'We are pleased to invite' => '',
            'SEAT' => 'STOEL',
            //			'comfort of'
        ],
        'es' => [
            'PASSENGER'     => 'PASAJERO',
            'FLIGHT'        => 'VUELO',
            'DEPARTURE'     => 'SALIDA',
            'Boarding Pass' => 'Tarjeta de embarque',
            //			'We are pleased to invite' => '',
            'SEAT' => 'ASIENTO',
            //			'comfort of'
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->detects as $lang => $detect) {
            foreach ($detect as $dt) {
                if (stripos($body, $dt) !== false) {
                    $this->lang = $lang;

                    break 2;
                }
            }
        }
        $itineraries = [];
        $this->parseHtml($itineraries);

        return [
            'emailType'  => 'MobileBoardingPassHtml' . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $itineraries],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        if (preg_match("#[A-Z\d]{2}\/\d{2}[A-Z]{3}\d{2}#", $headers['subject'])) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'Boarding Pass') === false || stripos($body, 'cathaypacific.com') === false) {
            return false;
        }

        foreach ($this->detects as $detect) {
            foreach ($detect as $dt) {
                if (stripos($body, $dt) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'][] = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('PASSENGER') . "']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");

        // AccountNumbers
        $it['AccountNumbers'][] = $this->http->FindSingleNode("//tr[contains(., '" . $this->t('FREQUENT FLYER') . "') and not(.//tr)]/following-sibling::tr[1]/td[1]");
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

        // TripSegments
        $segment = [];

        $flight = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('FLIGHT') . "']/ancestor::tr[1]/following-sibling::tr[1]/td[1]");
        //FlightNumber
        //AirlineName
        if (preg_match('#([\dA-Z]{2})(\d{1,5})#', $flight, $m)) {
            $segment['AirlineName'] = $m[1];
            $segment['FlightNumber'] = $m[2];
        }
        //DepCode
        $segment['DepCode'] = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('FLIGHT') . "']/ancestor::tr[1]/following-sibling::tr[3]/td[1]", null, true, "#([A-Z]{3})#");

        //DepName
        $segment['DepName'] = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('FLIGHT') . "']/ancestor::tr[1]/following-sibling::tr[4]/td[1]");

        //DepartureTerminal
        //DepDate
        $segment['DepDate'] = strtotime($this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('DEPARTURE') . "']/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, true, "#(\d{2}[A-Z]{3}\d{2}\s+\d{2}:\d{2})#i"));

        //ArrCode
        $segment['ArrCode'] = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('FLIGHT') . "']/ancestor::tr[1]/following-sibling::tr[3]/td[3]", null, true, "#([A-Z]{3})#");

        //ArrName
        $segment['ArrName'] = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('FLIGHT') . "']/ancestor::tr[1]/following-sibling::tr[4]/td[2]");

        //ArrivalTerminal
        //ArrDate
        $segment['ArrDate'] = MISSING_DATE;

        //Aircraft
        //TraveledMiles
        //Cabin
        //BookingClass
        $class = $this->http->FindSingleNode('(.//text()[normalize-space(.)="' . $this->t('Boarding Pass') . '"])[1]/following::text()[normalize-space(.)][1]');

        if (stripos($class, 'Connecting from ') === false) {
            $segment['BookingClass'] = $class;
        }
        //PendingUpgradeTo
        $invit = $this->http->FindSingleNode("//*[contains(normalize-space(text()), '" . $this->t('We are pleased to invite') . "')]", null, true, "#" . $this->t('comfort of') . ":\s*(.*)#");

        if (!empty($invit)) {
            $segment['PendingUpgradeTo'] = $invit;
        }
        //Seats
        $seat = $this->http->FindSingleNode("//*[normalize-space(.)='" . $this->t('SEAT') . "']/ancestor::tr[1]/following-sibling::tr[1]/td[2]", null, true, "#([\dA-Z]+)#");

        if (isset($seat)) {
            $segment['Seats'] = [$seat];
        }

        //Duration
        //Meal
        //Smoking
        //Stops
        //Operator

        $it['TripSegments'][] = $segment;

        $itineraries[] = $it;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
