<?php

namespace AwardWallet\Engine\aireuropa\Email;

class It4009667 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aireuropa/it-4117668.eml, aireuropa/it-4158115.eml, aireuropa/it-4184419.eml, aireuropa/it-4196789.eml";

    public $reFrom = "flyingblue@airfrance-klm.com";
    public $reSubject = [
        "es"=> "Flying Blue: Acuse de recibo de su pedido",
    ];
    public $reBody = [
        'Flying Blue',
        'Air Europa',
    ];
    public $reBody2 = [
        "es" => ['Tarjeta de embarque', 'INFORMACIÓN DEL VIAJE'],
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parsePDF(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->pdf->FindSingleNode("//text()[normalize-space(.)='Otra información']/preceding::text()[normalize-space(.)][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Tarjeta de embarque")];

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
        $pdf = implode("\n", $this->pdf->FIndNodes("//text()"));
        // $segments = $this->splitter("#(\w{2}\s+\d+\s+Ok\s+to\s+fly)#msi", $pdf);

        // foreach($segments as $segment){

        $itsegment = [];

        if (preg_match("#PUERTA\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s#", $pdf, $flight)) {
            // FlightNumber
            $itsegment['FlightNumber'] = $flight['FlightNumber'];

            // AirlineName
            $itsegment['AirlineName'] = $flight['AirlineName'];
        }

        if (preg_match("#(?<DepTime>\d+:\d+)\s+(?<ArrTime>\d+:\d+)\s+(?<DepDate>\d+\s+\w+\s+\d{4})\s+(?<DepCode>[A-Z]{3})\s+(?<ArrCode>[A-Z]{3})\s+(?<ArrDate>\d+\s+\w+\s+\d{4})#ms", $pdf, $deparr)) {
            // DepCode
            $itsegment['DepCode'] = $deparr['DepCode'];
            // ArrCode
            $itsegment['ArrCode'] = $deparr['ArrCode'];
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($deparr['DepDate'] . ', ' . $deparr['DepTime']));
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($deparr['ArrDate'] . ', ' . $deparr['ArrTime']));
        }

        // DepName
        // ArrName
        // Operator
        // Aircraft
        // TraveledMiles
        // Cabin
        $itsegment['Cabin'] = $this->re("#(CLASE TURISTA)#", $pdf);

        // BookingClass
        // PendingUpgradeTo
        // Seats
        if (preg_match("#PUERTA\s+\w{2}\d+\s+(?<Seat>\d{2}[A-Z])#", $pdf, $seat)) {
            $itsegment['Seats'] = $seat['Seat'];
        }

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;
        // }
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $bodyPdf = $parser->searchAttachmentByName('.*pdf');

        if (empty($bodyPdf)) {
            return false;
        }
        $bodyPdf = \PDF::convertToText($parser->getAttachmentBody(array_shift($bodyPdf)));

        $provider = false;

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false) {
                $provider = true;
            }
        }

        if (!$provider) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (is_array($re) && (stripos($bodyPdf, $re[0]) !== false || stripos($bodyPdf, $re[1]) !== false)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return null;
        }

        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) === null) {
            return null;
        }

        $this->pdf = clone $this->http;
        $this->pdf->SetBody(str_replace("&#160;", " ", $html));

        foreach ($this->reBody2 as $lang=> $re) {
            if (is_array($re) && (stripos($this->http->Response["body"], $re[0]) !== false || stripos($this->http->Response["body"], $re[1]) !== false)) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePDF($itineraries);

        $result = [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function nextText($field)
    {
        return $this->pdf->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][1]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\w+)/(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
