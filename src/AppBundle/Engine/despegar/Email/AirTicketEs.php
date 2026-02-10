<?php

namespace AwardWallet\Engine\despegar\Email;

class AirTicketEs extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "despegar/it-11082722.eml, despegar/it-13015236.eml, despegar/it-13033528.eml, despegar/it-13033532.eml, despegar/it-13114778.eml, despegar/it-4102576.eml";
    private $xpath = "//text()[contains(., 'Directo') or contains(., 'Escala')]/ancestor::table[2]";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1],
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//a[contains(@href, 'despegar.com')]")->length > 0
            && $this->http->XPath->query($this->xpath)->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@despegar.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@despegar.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = CONFNO_UNKNOWN;
        $it['TripNumber'] = $this->http->FindSingleNode("//*[contains(text(), 'Nro Solicitud de compra')]/following::*[normalize-space(.)!=''][1]");

        foreach ($this->http->XPath->query("//text()[contains(., 'APELLIDO') or contains(text(), 'Nombre')]/ancestor::td[1]") as $root) {
            $it['Passengers'][] = $this->http->FindSingleNode(".//text()[contains(., 'APELLIDO')]/following::text()[normalize-space(.)][1]", $root) . '/' . $this->http->FindSingleNode(".//text()[contains(., 'Nombre')]/following::text()[normalize-space(.)][1]", $root);
        }
        $total = $this->http->FindSingleNode("//*[contains(text(), 'Total')]/following-sibling::*[normalize-space(.)!=''][1]");
        $it['TotalCharge'] = $this->amount($total);
        $it['Currency'] = $this->currency($total);
        $points = $this->http->FindSingleNode('//text()[' . $this->starts('Puntos Superclub:') . ']');

        if (preg_match("#(Puntos Superclub):\s*(\d[\d,. ]+)$#", $points, $m)) {
            $it['SpentAwards'] = trim($m[2]) . ' ' . $m[1];
        }

        $xpath = $this->xpath;
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log("segments not found {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];
            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::td[2]", $root));
            $depInfo = $this->extractCodeTimeName($this->http->FindSingleNode("descendant::tr[2]/td[2]", $root));

            if (is_array($depInfo) && count($depInfo) === 3) {
                $seg['DepCode'] = $depInfo['Code'];
                $seg['DepName'] = $depInfo['Name'];
                $seg['DepDate'] = strtotime($date . ' ' . $depInfo['Time']);
            }
            $arrIndo = $this->extractCodeTimeName($this->http->FindSingleNode("descendant::tr[2]/td[4]", $root));

            if (is_array($arrIndo) && count($arrIndo) === 3) {
                $seg['ArrCode'] = $arrIndo['Code'];
                $seg['ArrName'] = $arrIndo['Name'];
                $seg['ArrDate'] = strtotime($date . ' ' . $arrIndo['Time']);
            }
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            $seg['AirlineName'] = $this->http->FindSingleNode("(.//descendant::img[contains(@src, '/airlines/')])[1]/@src", $root, null, "#/([A-Z\d]{2}).png#");

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function extractCodeTimeName($str)
    {
        if (preg_match("#(\w{3})\s+(\d+:\d+)\s+(.+)#", $str, $m)) {
            return [
                'Code' => $m[1],
                'Time' => $m[2],
                'Name' => $m[3],
            ];
        }

        return $str;
    }

    private function normalizeDate($str)
    {
        // $this->logger->info($str);
        $in = [
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //sábado 27 feb 2016
            "#^[^\s\d]+ (\d+) de ([^\s\d]+) de (\d{4})$#", //Sábado 24 de diciembre de 2016
        ];
        $out = [
            "$1",
            "$1 $2 $3",
        ];
        // $this->logger->info(preg_replace($in, $out, $str));
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '$'   => 'ARS', // it's not error
            'U$S' => 'USD',
            'US$' => 'USD',
            'MXN$'=> 'MXN',
            '€'   => 'EUR',
            '£'   => 'GBP',
            '₹'   => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\. ]+)#", $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
