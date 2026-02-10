<?php

namespace AwardWallet\Engine\edreams\Email;

class PDF2 extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4043115.eml, edreams/it-4120367.eml, edreams/it-4247990.eml";
    public $reBody = [
        'en' => ['Invoice', 'Invoice Date'],
        'es' => ['Pasajero', 'Fecha de Factura'],
        'fr' => ['Passager', 'Date de facturation'],
    ];
    public $pdf;
    public $lang = '';
    public static $dict = [
        'es' => [
            'Record locator'  => 'Número de Factura',
            'Passenger'       => 'Pasajero',
            'Date of booking' => 'Fecha de Factura',
            'Ticket'          => 'Tax. Amount',
            'Total cost'      => 'Total servicio',
            'Dep Name'        => 'Ruta',
            'Dep Date'        => 'Fecha de emisión',
            'From'            => 'dos',
            'Flight'          => 'Flight',
            'Class'           => 'Classe',
            'Depart'          => 'Depart',
            'Return'          => 'Arrive',
        ],
        'en' => [
            'Record locator'  => 'Invoice No',
            'Passenger'       => 'Passenger',
            'Date of booking' => 'Invoice Date',
            'Ticket'          => 'Tax. Amount',
            'Total cost'      => 'Total for Services',
            'Dep Name'        => 'Route',
            'Dep Date'        => 'Issue Date',
            'Depart'          => 'Depart',
            'Return'          => 'Arrive',
        ],
        'fr' => [
            'Record locator'  => 'Facture N°',
            'Passenger'       => 'Passager',
            'Date of booking' => 'Date de facturation',
            'Ticket'          => 'Tax. Amount',
            'Total cost'      => 'Total des prestations',
            'Dep Name'        => 'Itinéraire',
            'Dep Date'        => "Date d'émission",
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName(".*pdf");

        if (isset($pdfs) && count($pdfs) > 0) {
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

        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('.*pdf');

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($text, $reBody[0]) !== false && stripos($text, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "noreply@edreams.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "noreply@edreams.com") !== false;
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
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Record locator') . "')]/following-sibling::*[normalize-space(.)!=''][2]", null, true, "#(\d+)#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Record locator') . "')]", null, true, "#" . $this->t('Record locator') . ":\s+(\d+)#");
        }
        $it['Passengers'] = $this->pdf->FindNodes("//*[contains(text(), '" . $this->t('Passenger') . "')]/following-sibling::text()[normalize-space(.)!=''][1]");
        $it['TotalCharge'] = cost($this->pdf->FindSingleNode("(//*[contains(normalize-space(.), '" . $this->t('Total cost') . "')]/following-sibling::text()[normalize-space(.)!=''][1])[1]"));
        $it['Currency'] = $this->pdf->FindSingleNode("(//*[starts-with(text(), 'Total') and not(contains(., '" . $this->t('From') . "'))]/following-sibling::text()[1])[1]", null, true, "#\((\w{3})\)#");
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Date of booking') . "')][normalize-space(.)!=''][1]")));

        $seg = [];
//        it-4043115.eml - Ruta: Barcelona - Miami International Apt., it-4120367.eml - Route: Melbourne International Apt - Perth.
        $infoAir = $this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[contains(., '" . $this->t('Dep Name') . "')][1])[1]", null, true, "#" . $this->t('Dep Name') . ":\s+(.+)#");

        if (preg_match("#([\w\s]*) - (\w+)#", $infoAir, $m)) {
            $seg['DepName'] = $m[1];
            $seg['ArrName'] = $m[2];
        }
        $depName = [];
        $arrName = [];

        if (empty($seg['DepName']) && empty($seg['ArrName'])) {
            $depName = $this->getField($this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[contains(., '" . $this->t('Depart') . "')][1]"));
            $arrName = $this->getField($this->pdf->FindSingleNode("//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[contains(., '" . $this->t('Return') . "')][1]"));
        }

        if (count($depName) >= 2 && count($arrName) >= 2) {
            $seg['DepName'] = $depName['Name'];
            $seg['DepartureTerminal'] = $depName['Term'];
            $seg['ArrName'] = $arrName['Name'];
            $seg['ArrivalTerminal'] = $arrName['Term'];
        }
        $code = $this->getNode([
            $this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[4])[2]"),
            $this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[normalize-space(.)!=''][5])[2]"),
            $this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[normalize-space(.)!=''][5])[last()]"),
        ]);

        if (count($code) === 2) {
            $seg['DepCode'] = $code['DepCode'];
            $seg['ArrCode'] = $code['ArrCode'];
        }
//        it-4043115.eml - Fecha de emisión: 31.01.14., it-4120367.eml - Issue Date: 18-Oct-13.
        $date = $this->normalizeDate($this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[contains(., \"" . $this->t('Dep Date') . "\")][1])[1]"));
//        $seg['date'] = $this->pdf->FindSingleNode("(//*[contains(text(), '" . $this->t('Ticket') . "')]/preceding::text()[contains(., 'émission')])[1]");
        if (!empty($date) && preg_match("#(?:\d+|\w+)[\.\/ ]*\d+[\.\/ ]*\d+#", $date) !== false) {
            $seg['DepDate'] = strtotime($date);
            $seg['ArrDate'] = strtotime($date);
        } else {
            $date = $this->normalizeDate($this->pdf->FindSingleNode("//text()[contains(., '" . $this->t('Flight') . "')]/preceding::*[normalize-space()!=''][1]"));
            $seg['DepDate'] = strtotime($date . ' ' . $depName['Time']);
            $seg['ArrDate'] = strtotime($this->normalizeDate($arrName['Date']) . ' ' . $arrName['Time']);
        }
        $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
        $seg['AirlineName'] = AIRLINE_UNKNOWN;
        $it['TripSegments'][] = $seg;

        return [$it];
    }

    /**
     * @return array|null
     */
    private function getNode(array $nodeRes)
    {
        $res = null;

        foreach ($nodeRes as $nodeRe) {
            $res = $this->processingCodes($nodeRe);

            if ($res !== null) {
                return $res;
            }
        }

        return null;
    }

    /**
     * example: GEO - SH Fees - REG.
     *
     * @param $str
     *
     * @return array|null
     */
    private function processingCodes($str)
    {
        $seg = [];

        if (preg_match("#(\w{3})\s*-\s*.+\s*-\s*(\w{3})\s*.*#", $str, $m)) {
            return [
                'DepCode' => $m[1],
                'ArrCode' => $m[2],
            ];
        }

        return null;
    }

    /**
     * example: Arrive: Madrid Barajas Apt 30-Sep-13 at 12:30. Terminal: 2.
     *          Depart: Paris Orly Apt at 10:25. Terminal: W.
     *
     * @param $str
     *
     * @return array
     */
    private function getField($str)
    {
        if (preg_match("#:\s+(?<Name>[\w\s]+[^\d-\w-\d])\s*(?<Date>\d{2}-\w{3}-\d+)*\s*?at\s+(?<Time>\d+:\d+)\.*\s*[Terminal:\s*]+(?<Term>[\w\d]+)#i", $str, $m)) {
            return [
                'Name' => $m['Name'],
                'Date' => $m['Date'],
                'Time' => $m['Time'],
                'Term' => $m['Term'],
            ];
        }

        return [];
    }

    /**
     * example: 27-Sep-13, 27.09.13.
     *
     * @param $date
     *
     * @return mixed
     */
    private function normalizeDate($date)
    {
        $in = [
            '#[\S\s]*(\d{2})[\.\/]*(\d{2})[\.\/]*(\d{2})#',
            '#[\S\s]*(\d{2})-(\D{3,})-(\d{2})[.]*#',
        ];
        $out = [
            '$2/$1/$3',
            '$2 $1 $3',
        ];

        return preg_replace($in, $out, $date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }
}
