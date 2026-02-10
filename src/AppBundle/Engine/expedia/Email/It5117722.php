<?php

namespace AwardWallet\Engine\expedia\Email;

class It5117722 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "expedia/it-5117722.eml";

    public $reFrom = "expedia@br.expediamail.com";
    public $reSubject = [
        "pt"=> "Confirmação de Compra",
    ];
    public $reBody = 'Expedia';
    public $reBody2 = [
        "pt"=> "Obrigado por reservar sua viagem",
    ];

    public static $dictionary = [
        "pt" => [],
    ];

    public $lang = "pt";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->nextText("Nome do passageiro:")]);

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->amount($this->nextText("Passagem aérea Total:")));

        // BaseFare
        $it['BaseFare'] = $this->cost($this->amount($this->nextText("Custo total das passagens:")));

        // Currency
        $it['Currency'] = $this->currency($this->amount($this->nextText("Passagem aérea Total:")));

        // Tax
        $it['Tax'] = $this->cost($this->amount($this->nextText("Tributos e tarifas:")));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='a']/ancestor::tr[1][contains(., '(') and contains(., ')')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#.*?\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^.*?\s+\(([A-Z]{3})\) a .*?\s+\([A-Z]{3}\)$#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^(.*?)\s+\([A-Z]{3}\) a .*?\s+\([A-Z]{3}\)$#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#(\d+:\d+)\s+-\s+\d+:\d+#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^.*?\s+\([A-Z]{3}\) a .*?\s+\(([A-Z]{3})\)$#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^.*?\s+\([A-Z]{3}\) a (.*?)\s+\([A-Z]{3}\)$#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#\d+:\d+\s+-\s+(\d+:\d+)#"), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\d+$#");

            // Operator
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
    }

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
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

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

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
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
            "#^(\d+)-([^\d\s]+)-(\d{2})$#", //4-set-15
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
        return str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
