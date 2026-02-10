<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class FlightPurchaseRequest extends \TAccountChecker
{
    public $mailFiles = "despegar/it-11944143.eml, despegar/it-12105488.eml, despegar/it-12125366.eml";

    public static $dictionary = [
        "es" => [
            'Verifica el estado de tu compra en Mi Cuenta' => ['Verifica el estado de tu compra en Mi Cuenta', 'Conocé todo lo que podés hacer en Mi Cuenta', 'Conoce todo lo que puedes hacer en Mi Cuenta'],
        ],
    ];

    public $lang = "es";
    private $reFrom = "noreply@despegar.com";
    private $reSubject = [
        "es"=> "Solicitud de compra de vuelo",
    ];
    private $reBody = 'Despegar.com';
    private $reBody2 = [
        "es"=> "Detalle de tu vuelo",
    ];
    private $header;

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];
        $it['Kind'] = "T";

        // TripNumber
        $it['TripNumber'] = $this->nextText("Nro Solicitud de compra");

        if (empty($it['TripNumber']) && preg_match('/Número\s*\:\s*(\d+)/', $this->header, $m)) {
            $it['TripNumber'] = $m[1];
        }

        // RecordLocator
        if (!empty($it['TripNumber']) || $this->http->XPath->query('//node()[' . $this->contains($this->t('Verifica el estado de tu compra en Mi Cuenta')) . ']')->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN; // because Despegar is not airline
        }

        // Passengers
        foreach ($this->http->XPath->query("//text()[" . $this->eq("APELLIDO/S:") . "]/ancestor::td[1]") as $root) {
            $it['Passengers'][] = $this->nextText("APELLIDO/S:", $root) . '/' . $this->nextText("Nombre/s:", $root);
        }

        // Currency
        // TotalCharge
        $total = $this->nextText("Total");

        if ($total !== null) {
            $it['Currency'] = $this->currency($this->nextText("Total"));
            $it['TotalCharge'] = $this->amount($total);
        }
        $points = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Puntos Superclub:')) . ']');

        if (preg_match("#(" . $this->t('Puntos Superclub:') . ")\s*(\d[\d,. ]+)$#", $points, $m)) {
            $it['SpentAwards'] = trim($m[2]) . ' ' . trim($m[1], ':');
        }

        // BaseFare
        $baseFare = $this->http->FindSingleNode("//text()[" . $this->eq("Total") . "]/ancestor::tr[1]/../tr[1]/td[2]");

        if ($baseFare !== 0) {
            $it['BaseFare'] = $this->amount($baseFare);
        }

        // Tax
        $tax = $this->nextText("Impuestos y Tasas");

        if ($tax !== null) {
            $it['Tax'] = $this->amount($tax);
        }

        // Status
        if (
            strpos($this->http->Response['body'], 'Tu solicitud de compra está siendo procesada') !== false
            || strpos($this->http->Response['body'], 'Recibirás un email con el paso a paso para realizar el pago.') !== false
        ) {
            $it['Status'] = "siendo procesada";
        }

        // TripSegments
        $xpathFragment1 = '/descendant::text()[string-length(normalize-space(.))=3]';
        $xpath = "//text()[" . $this->eq("VUELO") . "]/following::table[1]//tr[ ./td[2]{$xpathFragment1} and ./td[3]{$xpathFragment1} and ./td[4][string-length(normalize-space(.))>3] ]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $itsegment = [];

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode('./td[1]/descendant::img/@src', $root, true, '/\/([A-Z][A-Z\d]|[A-Z\d][A-Z])\.(?:png|jpg|jpeg|gif|bmp)/');

            // FlightNumber
            if (
                strpos($this->http->Response['body'], 'Tu solicitud de compra está siendo procesada') !== false
                || strpos($this->http->Response['body'], 'Recibirás un email con el paso a paso para realizar el pago.') !== false
            ) {
                $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
            }

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]//tr[1]/td[1]", $root);

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]//tr[2]/td[1]", $root);

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::tr[1]/td[2]", $root));

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]//tr[1]/td[2]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]//tr[1]/td[1]", $root);

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]//tr[2]/td[1]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]//tr[1]/td[2]", $root), $date);

            // Duration
            $itsegment['Duration'] = $this->nextText("Duración:", $root);

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
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
            if (stripos($headers["subject"], $re) !== false) {
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
        $this->date = EmailDateHelper::calculateOriginalDate($this, $parser);
        $this->logger->info('Relative date: ' . date('r', $this->date));
        $this->header = $parser->getHeader('subject');

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr)
    {
        // $this->http->log($instr);
        $in = [
            "#^[^\s\d]+ (\d+) de ([^\s\d]+) de (\d{4})$#", //Martes 06 de marzo de 2018
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $this->date, true, $str);
        }

        return strtotime($str);
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'starts-with(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }
}
