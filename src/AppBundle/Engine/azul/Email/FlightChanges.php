<?php

namespace AwardWallet\Engine\azul\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightChanges extends \TAccountChecker
{
    public $mailFiles = "azul/it-16612523.eml, azul/it-7798707.eml, azul/it-7876622.eml";

    public static $dictionary = [
        "pt" => [],
        'es' => [],
    ];

    public $lang = "pt";

    private $reFrom = "no-reply@voeazul.com.br";
    private $reSubject = [
        "pt"=> "Azul Linhas Aéreas - Aviso de Alteração de Voo",
    ];
    private $reBody = 'Azul';
    private $reBody2 = [
        "pt" => "Novo voo",
        'es' => 'Caro agente, favor atentar para o novo horário do voo de',
    ];

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
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

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $this->http->setBody($parser->getHTMLBody());
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        if (false !== stripos($this->http->Response['body'], 'Caro agente, favor atentar para o novo horário do voo de')) {
            $this->parseEmail($itineraries);
        } else {
            $this->parseHtml($itineraries);
        }
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
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

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'][] = $this->nextText("Olá");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->contains("foi modificado") . "]")) {
            $it['Status'] = 'changed';
        }

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//a[contains(@href, '/AtualizaStatusEmail.aspx')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            preg_match_all("#(\d+)#", $this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][1]", $root), $m);

            foreach ($m[1] as $i=> $v) {
                $it['TripSegments'][$i]['FlightNumber'] = $v;
                $it['TripSegments'][$i]['AirlineName'] = AIRLINE_UNKNOWN;
            }

            // DepCode
            preg_match_all("#de:\s+([A-Z]{3})#", $this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][2]", $root), $m);

            foreach ($m[1] as $i=> $v) {
                $it['TripSegments'][$i]['DepCode'] = $v;
            }

            // DepName
            // DepartureTerminal
            // DepDate
            preg_match_all("#saída:\s+([\d\s:/]+)#", $this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][3]", $root), $m);

            foreach ($m[1] as $i=> $v) {
                $it['TripSegments'][$i]['DepDate'] = strtotime($this->normalizeDate(trim($v)));
            }

            // ArrCode
            preg_match_all("#para:\s+([A-Z]{3})#", $this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][2]", $root), $m);

            foreach ($m[1] as $i=> $v) {
                $it['TripSegments'][$i]['ArrCode'] = $v;
            }

            // ArrName
            // ArrivalTerminal
            // ArrDate
            preg_match_all("#chegada:\s+([\d\s:/]+)#", $this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][3]", $root), $m);

            foreach ($m[1] as $i=> $v) {
                $it['TripSegments'][$i]['ArrDate'] = strtotime($this->normalizeDate(trim($v)));
            }

            // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                // Meal
                // Smoking
                // Stops
        }

        $itineraries[] = $it;
    }

    private function parseEmail(&$itineraries)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'localizador')]/following-sibling::node()[1]");

        $it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(normalize-space(.), 'cliente(s)')]/following-sibling::node()[1]");

        $re = '/(?<fNum>\d+)\s*\S\s*(?<dCode>[A-Z]{3})\s*\S\s*(?<dDate>\d{1,2}\/\d{2}\/\d{2,4}\s+\d{1,2}:\d{2})\s*\S\s*(?<aCode>[A-Z]{3})\s*\S\s*(?<aDate>\d{1,2}\/\d{2}\/\d{2,4}\s+\d{1,2}:\d{2})/';
        $body = $this->http->Response['body'];

        if (($lines = $this->http->FindNodes("//text()[normalize-space(.)='Itinerário atual:']/following-sibling::node()[normalize-space(.)]")) && 0 < count($lines)) {
            $body = implode('\n', $lines);
        }
        preg_match_all($re, $body, $m);

        foreach ($m['fNum'] as $i => $fNum) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $seg['FlightNumber'] = $fNum;

            $seg['AirlineName'] = AIRLINE_UNKNOWN;

            if (!empty($m['dCode'][$i])) {
                $seg['DepCode'] = $m['dCode'][$i];
            }

            if (!empty($m['dDate'][$i])) {
                $seg['DepDate'] = strtotime($m['dDate'][$i]);
            }

            if (!empty($m['aCode'][$i])) {
                $seg['ArrCode'] = $m['aCode'][$i];
            }

            if (!empty($m['aDate'][$i])) {
                $seg['ArrDate'] = strtotime($m['aDate'][$i]);
            }

            $it['TripSegments'][] = $seg;
        }
        $itineraries[] = $it;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{2})\s+(\d+:\d+)$#", //21/05/17 16:45
        ];
        $out = [
            "$1.$2.20$3, $4",
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
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
