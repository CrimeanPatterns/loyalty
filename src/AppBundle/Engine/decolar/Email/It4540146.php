<?php

namespace AwardWallet\Engine\decolar\Email;

class It4540146 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "decolar/it-11377203.eml, decolar/it-12827505.eml, decolar/it-13173087.eml, decolar/it-4540146.eml";

    public static $dictionary = [
        'pt' => [],
    ];

    public $lang = '';

    private $reFrom = "noreply@decolar.com";

    private $reSubject = [
        'pt' => 'Solicitação de compra de passagem aérea',
    ];

    private $reBody = 'Decolar.com';

    private $reBody2 = [
        'pt' => 'Detalhe de seu voo',
    ];

    public function parseHtml(&$itineraries)
    {
        $patterns = [
            'airportCode' => '/^([A-Z]{3})$/',
        ];

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Número de reserva");

        // Passengers
        $it['Passengers'] = array_map(function ($s) {
            return preg_replace(['/SOBRENOME\/S:\s+(.*?)\s+Nome\/s:\s+(.+)\s+Documento\s+\d+/', "/SOBRENOME\/S:\s+(.*?)\s+Nome\/s:\s+(.+)$/"], ["$2 $1", '$2 $1'], $s);
        }, $this->http->FindNodes("//text()[normalize-space(.)='PASSAGEIROS']/following::table[1]//tr"));

        // TotalCharge
        $it['TotalCharge'] = $this->cost(preg_replace("#[.,](\d{3})#", "$1", $this->nextText("Total")));

        // BaseFare
        $it['BaseFare'] = $this->cost(preg_replace("#[.,](\d{3})#", "$1", $this->http->FindSingleNode("//text()[normalize-space(.)='Total']/ancestor::tr[1]/../tr[1]/td[2]")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total"));

        // Tax
        $it['Tax'] = $this->cost(preg_replace("#[.,](\d{3})#", "$1", $this->nextText("Impostos e taxas")));

        $xpath = '//text()[normalize-space(.)="Direto"]/ancestor::tr[2] | //tr[ ./td[1][./descendant::img] and ./td[4] ]';
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info("segments root not found: $xpath");
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[1]/td[2]", $root)));

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, $patterns['airportCode']);

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root), $date);

            $tdPlus = $this->http->XPath->query('./td[3]/descendant::img', $root)->length === 0 ? '0' : '1';

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode('./td[3+' . $tdPlus . ']/descendant::text()[normalize-space(.)][1]', $root, true, $patterns['airportCode']);

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode('./td[3+' . $tdPlus . ']/descendant::text()[normalize-space(.)][3]', $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode('./td[3+' . $tdPlus . ']/descendant::text()[normalize-space(.)][2]', $root), $date);

            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode('./td[4+' . $tdPlus . ']', $root, true, '/([^:]{2,})$/');

            // FlightNumber
            if ($itsegment['DepCode'] && $itsegment['ArrCode'] && $itsegment['DepDate'] && $itsegment['ArrDate']) {
                $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }
            $itsegment['AirlineName'] = $this->http->FindSingleNode("(.//descendant::img[contains(@src, '/airlines/')])[1]/@src", $root, null, "#/([A-Z\d]{2}).png#");

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
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'FlightTicket' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    //	private function nextCol($field, $root=null, $n=1){
    //		return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    //	}

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
            "#^[^\d\s]+\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#",
            '/^\D+\s+(\d{1,2})\s+(\w+)\s+(\d{4})$/',
        ];
        $out = [
            "$1 $2 $3",
            '$1 $2 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    //	private function re($re, $str, $c=1){
//		preg_match($re, $str, $m);
//		if(isset($m[$c])) return $m[$c];
//		return null;
//	}
}
