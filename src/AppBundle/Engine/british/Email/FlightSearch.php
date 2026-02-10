<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightSearch extends \TAccountChecker
{
    public $mailFiles = "british/it-10320465.eml, british/it-10378448.eml, british/it-10468046.eml, british/it-115546805.eml, british/it-9904212.eml";
    public $reFrom = ["@contact.britishairways.com", "ba.custsvcs@email.ba.com"];
    public $reSubject = [
        "en"=> "Recent flight search to",
        "Your BA fare quote to ",
        "it"=> "Ricerca voli recenti per",
        "de"=> "Kürzliche Suche nach",
        "pl"=> "Wyszukiwanie ostatnich lotów do",
    ];
    public $reBody = 'British Airways';
    public $reBody2 = [
        "en"=> ["Your flight search", "I found these flights on ba.com. They may not be available for long at this price."],
        "it"=> "La sua ricerca voli",
        "de"=> "Ihre Suche nach Flügen",
        "pl"=> "Wyszukiwanie lotów",
    ];

    public static $dictionary = [
        "en" => [
            "Outbound"=> ["Outbound", "Inbound"],
        ],
        "it" => [
            "Your flight search"        => "La sua ricerca voli",
            "Total price quote"         => "Prezzo totale",
            "we've saved your itinerary"=> "abbiamo salvato il suo",
            "Outbound"                  => ["Andata", "Ritorno"],
        ],
        "de" => [
            "Your flight search"        => "Ihre Suche nach Flügen",
            "Total price quote"         => "Preisangebot (gesamt):",
            "we've saved your itinerary"=> "haben wir Ihren nachstehenden",
            "Outbound"                  => ["Hinflug", "Rückflug"],
        ],
        "pl" => [
            "Your flight search"        => "Wyszukiwanie lotów",
            "Total price quote"         => "Wycena całkowita",
            "we've saved your itinerary"=> "zapisaliśmy Twój plan podróży niżej",
            "Outbound"                  => ["Lot tam", "Powrót"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        if ($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your flight search")) . "]")) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // TripNumber
        // Passengers
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t("Total price quote")));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total price quote")));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("we've saved your itinerary")) . "]")) {
            $it['Status'] = 'saved';
        }

        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[" . $this->starts($this->t("Outbound")) . "]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[count(./td[normalize-space(.)])=6]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if ($date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[" . $this->starts($this->t("Outbound")) . "][1]", $root, true, "# - (.+)#")))) {
                $this->date = $date;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space(.)][5]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)][3]", $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)][4]", $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)][2]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[normalize-space(.)][5]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\d+$#");

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

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->containsText($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query("//text()[".$this->contains($re)."]") !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if ($this->detectEmailByHeaders($parser->getHeaders()) == true
            && $this->detectEmailByBody($parser) == true) {
            $email->setIsJunk(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
    }

    public function ParsePlanEmail(PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

//        $this->parseHtml($itineraries);

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
        return 0;
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
            "#^[^\s\d]+ (\d+ [^\s\d]+ \d{4})$#", //Thursday 10 November 2016
            "#^(\d+:\d+) (\d+) ([^\s\d]+)$#", //07:05 10 Nov
        ];
        $out = [
            "$1",
            "$2 $3 $year, $1",
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

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }
        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }
        return false;
    }
}
