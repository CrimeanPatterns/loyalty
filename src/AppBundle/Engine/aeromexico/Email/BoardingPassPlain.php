<?php

namespace AwardWallet\Engine\aeromexico\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;

class BoardingPassPlain extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-10824509.eml, aeromexico/it-10824520.eml, aeromexico/it-10994392.eml, aeromexico/it-11019555.eml";
    public $reFrom = "@aeromexico.com";
    public $reSubject = [
        "en"=> "e-BP AM",
        "es"=> "Pase de Abordar Aeroméxico",
    ];
    public $reBody = 'aeromexico.com';
    public $reBody2 = [
        "en"=> "Please review e-BP information. ",
        "es"=> "Por favor revise la información del pase de abordar.",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "PNR:"                  => "PNR:",
            "Frequent Guest Number:"=> "Número de Viajero Frecuente:",
            "Flight:"               => "Vuelo:",
            "From:"                 => "De:",
            "To:"                   => "a:",
            " at "                  => " en ",
            "Seat:"                 => "Asiento:",
        ],
    ];

    public $lang = "en";
    public $subject = "";

    public function parsePlain(&$itineraries)
    {
        $text = $this->text;
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#" . $this->t("PNR:") . "\s*([A-Z\d]+)\s#", $text);

        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter([$this->re("#\n\s*(\S.+)\n" . $this->t("PNR:") . "#", $text)]);

        // TicketNumbers
        // AccountNumbers
        $it['AccountNumbers'] = array_filter([$this->re("#" . $this->t("Frequent Guest Number:") . "\s+(.+)#", $text)]);

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

        $date = $this->normalizeDate($this->re("#" . $this->t("Flight:") . "\s+\w{2} \d+ (.+)#", $text));

        $itsegment = [];

        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#" . $this->t("Flight:") . "\s+\w{2} (\d+)#", $text);

        if (preg_match("#" . $this->t("From:") . "\s+(?<Name>.*?)" . $this->t(" at ") . "(?<Time>\d+:\d+ [AP]M)#", $text, $m)) {
            // DepName
            $itsegment['DepName'] = $m['Name'];

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($m["Time"], $date);
        }

        if (preg_match("#" . $this->t("To:") . "\s+(?<Name>.*?)" . $this->t(" at ") . "(?<Time>\d+:\d+ [AP]M)#", $text, $m)) {
            // ArrName
            $itsegment['ArrName'] = $m['Name'];

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($m["Time"], $date);
        }

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#" . $this->t("Flight:") . "\s+(\w{2}) \d+#", $text);

        if (preg_match("# ([A-Z]{3})-([A-Z]{3}) #", $this->subject, $m)) {
            // DepCode
            $itsegment['DepCode'] = $m[1];

            // ArrCode
            $itsegment['ArrCode'] = $m[2];
        }

        // Operator
        // Aircraft
        // TraveledMiles
        // AwardMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = [$this->re("#" . $this->t("Seat:") . " (.+)#", $text)];

        // Duration
        // Meal
        // Smoking
        // Stops

        $it['TripSegments'][] = $itsegment;

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        // if(is_array($from)){
        // return strpos($from[0], $this->reFrom)!==false;
        // }
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
        $body = $parser->getPlainBody();

        $rows = explode("\n", $body);

        if (count($rows) > 10) {
            return false;
        }

        $check = false;

        foreach ($rows as $i=>$row) {
            if (strpos($row, "PNR:") !== false) {
                if (isset($rows[$i + 1])) {
                    if (strpos($rows[$i + 1], "From:") !== false || strpos($rows[$i + 1], "De:") !== false) {
                        $check = true;
                    }
                }
            }
        }

        if (!$check) {
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
        $this->date = strtotime($parser->getDate());
        $this->subject = $parser->getSubject();

        $this->http->FilterHTML = true;
        $itineraries = [];

        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        // $this->logger->info($str);
        $in = [
            "#^(\d+)([^\s\d]+)#", //09MAR
        ];
        $out = [
            "$1 $2 %Y%",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->info($str);
        return EmailDateHelper::parseDateRelative("D", $this->date, null, $str, 3);
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
            '₹'=> 'INR',
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
