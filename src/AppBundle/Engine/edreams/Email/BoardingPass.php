<?php

namespace AwardWallet\Engine\edreams\Email;

// parsers similar: engine/fluege/Email/CheckIn.php, engine/appintheair/Email/CheckInStatus.php
class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "edreams/it-4135555.eml, edreams/it-4135558.eml, edreams/it-4227438.eml";

    public $reBody = [
        'en' => 'IMPORTANT REMINDERS - Please read',
        'es' => ['Conserva esta tarjeta hasta tu destino', 'Consérvelo para presentarlo en los aeropuertos de origen y de destino'],
    ];

    /** @var \HttpBrowser */
    public $pdf;

    public $lang = '';

    public static $dict = [
        'en' => [],
        'es' => [
            //            Flight Number
            'REFERENCE' => 'REFERENCIA',
            'DEPT'      => 'PUERTA',
            'SEAT'      => 'ASIENTO',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $this->pdf = clone $this->http;
                    $this->pdf->SetBody($html);
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }
        $body = $this->pdf->Response['body'];

        foreach ($this->reBody as $lang => $reBody) {
            if (is_string($reBody) && stripos($body, $reBody) !== false) {
                $this->lang = $lang;

                break;
            } elseif (is_array($reBody)) {
                foreach ($reBody as $re) {
                    if (stripos($body, $re) !== false) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
        }
        $pass = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $pass,
            ],
            'emailType' => 'BoardingPass' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $text = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        foreach ($this->reBody as $reBody) {
            if (is_string($reBody) && stripos($text, $reBody) !== false) {
                return true;
            } elseif (is_array($reBody)) {
                foreach ($reBody as $re) {
                    if (stripos($text, $re) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@edreams.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@edreams.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $pass = ['Kind' => 'T', 'TripSegments' => []];

//        Record Locator

        $pass['RecordLocator'] = $this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('REFERENCE') . "')]/following-sibling::*[normalize-space(.)!=''][1]");

        if (empty($pass['RecordLocator'])) {
            $pass['RecordLocator'] = $this->pdf->FindSingleNode("//text()[contains(., 'Código de reserva')]/following-sibling::text()[3]");
        }

//        Passengers

        $passenger = $this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('DEPT') . "')]/following-sibling::*[normalize-space(.)!=''][2]/text()");

        if (empty($passenger)) {
            $passenger = $this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('DEPT') . "')]/following-sibling::*[normalize-space(.)!=''][2]/text()[2]");
        }

        if (empty($passenger)) {
            $passenger = $this->pdf->FindSingleNode("//text()[contains(., 'Embarque')]/following-sibling::text()[normalize-space(.)!=''][3]");
        }

        $pass['Passengers'][] = $passenger;
//        Segments
        $seg = [];

//        Flight Number

        $flightNumber = $this->getFlightNumber($this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('REFERENCE') . "')]/following-sibling::*[normalize-space(.)!=''][4]"));

        if (count($flightNumber) >= 2) {
            $seg['FlightNumber'] = $flightNumber['FlightNumber'];
            $seg['AirlineName'] = $flightNumber['AirlineName'];
        } else {
            $flightNumber = $this->getFlightNumber($this->pdf->FindSingleNode("//text()[contains(., 'Origen')]/preceding-sibling::text()[1]"));
        }

        if (!empty($flightNumber)) {
            $seg['FlightNumber'] = $flightNumber['FlightNumber'];
        } else {
            $flightNumber = $this->getFlightNumber($this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('REFERENCE') . "')]/following-sibling::*[normalize-space(.)!=''][3]"));
        }

        if (!empty($flightNumber['FlightNumber']) && !empty($flightNumber['FlightNumber'])) {
            $seg['FlightNumber'] = $flightNumber['FlightNumber'];
            $seg['AirlineName'] = $flightNumber['AirlineName'];
        }

//        Seats

        $seg['Seats'] = $this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('SEAT') . "')]", null, true, "#\D+([A-Z\d]{1,3})#");

        if (empty($seg['Seats'])) {
            $seg['Seats'] = $this->pdf->FindSingleNode("//text()[contains(., 'Código de reserva')]/following-sibling::text()[normalize-space(.)!=''][2]", null, true, '/\b([A-Z\d]{1,3})\b/');
        } else {
            $seg['Seats'] = $this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('SEAT') . "')]/following-sibling::*[normalize-space(.)!=''][1])[1]", null, true, "#\D+([A-Z\d]{1,3})#");
        }

//        Date

        $date = $this->normalizeDate($this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('SEAT') . "')]/following-sibling::*[normalize-space(.)!=''][1]"));
        $depTime = $this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('DEPT') . "')]/following-sibling::*[normalize-space(.)!=''][1]");

        if (empty($date) && empty($depTime)) {
            $date = $this->normalizeDate($this->pdf->FindSingleNode("//text()[contains(., 'Embarque')]/following-sibling::text()[normalize-space(.)!=''][1]"));
            $depTime = $this->pdf->FindSingleNode("//text()[contains(., 'Hora de salida')]/following-sibling::text()[normalize-space(.)!=''][3]");
        }

        if (empty($date)) {
            $date = $this->normalizeDate($this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('SEAT') . "')]/following-sibling::*[normalize-space(.)!=''][2])[1]"));
        }
        $seg['DepDate'] = strtotime($date . ' ' . $depTime);

//        DepName and ArrName

        $depArrName = $this->getDepArrName($this->pdf->FindSingleNode("(//text()[contains(., '" . $this->t('DEPT') . "')]/preceding-sibling::*[normalize-space(.)!=''][1]/text()[1])[1]"));

        if (count($depArrName) >= 2) {
            $seg['DepName'] = $depArrName['DepName'];
            $seg['ArrName'] = $depArrName['ArrName'];
            $seg['DepartureTerminal'] = $depArrName['DepTerm'];
            $seg['ArrivalTerminal'] = $depArrName['ArrTerm'];
        }

        if (empty($seg['DepName']) && empty($seg['ArrName'])) {
            $seg['DepName'] = $this->pdf->FindSingleNode("//text()[contains(., 'Origen')]/following-sibling::text()[3]");
            $seg['ArrName'] = $this->pdf->FindSingleNode("//text()[contains(., 'Destino')]/following-sibling::text()[3]");
        }

        if (empty($seg['DepName']) && empty($seg['ArrName'])) {
            $depArrName = $this->getDepArrName($this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('DEPT') . "')]/following-sibling::*[normalize-space(.)!=''][2]/text()[1]"));
        }

        if (count($depArrName) >= 2) {
            $seg['DepName'] = $depArrName['DepName'];
            $seg['ArrName'] = $depArrName['ArrName'];
            $seg['DepartureTerminal'] = $depArrName['DepTerm'];
        }
        $seg['ArrDate'] = MISSING_DATE;

//        DepCode and ArrCode

        $seg['DepCode'] = $this->pdf->FindSingleNode("//text()[contains(., 'Destino')]", null, true, "#:\s*(\w{3})#");
        $seg['ArrCode'] = $this->pdf->FindSingleNode("//text()[contains(., 'Origen')]", null, true, "#:\s*(\w{3})#");

        if (empty($seg['DepCode']) && empty($seg['ArrCode']) && !empty($seg['DepDate']) && !empty($seg['ArrDate']) && !empty($seg['FlightNumber'])) {
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        $pass['TripSegments'][] = $seg;

        return [$pass];
    }

    private function getDepArrName($str)
    {
        if (preg_match("#([\w\s]*)\s+([-|T\d]+)*\s*\S*\s+([\w|\w\s]+[^T\d])([T\d]*)#i", $str, $m)) {
            return [
                'DepName' => $m[1],
                'DepTerm' => (stripos($m[2], 'T') !== false) ? $m[2] : null,
                'ArrName' => $m[3],
                'ArrTerm' => (stripos($m[4], 'T') !== false) ? $m[4] : null,
            ];
        }

        return [];
    }

    private function getFlightNumber($str)
    {
        if (preg_match("#(\D{2})\s*(\d+)#", $str, $m)) {
            return [
                'FlightNumber' => $m[2],
                'AirlineName'  => $m[1],
            ];
        }

        return [];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#(\d{1,2})\s*(\w+)\s*(\d{4})#',
            '#(\d{2})[\/](\d{2})[\/](\d{4})#',
        ];
        $out = [
            '$2 $1 $3',
            '$2/$1/$3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || empty(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
