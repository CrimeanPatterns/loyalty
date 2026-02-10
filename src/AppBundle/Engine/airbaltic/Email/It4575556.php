<?php

namespace AwardWallet\Engine\airbaltic\Email;

class It4575556 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "airbaltic/it-4575556.eml, airbaltic/it-4598579.eml, airbaltic/it-4975637.eml";

    public static $dictionary = [
        "en" => [
            'Booking reference:' => ['Booking reference:', 'Booking confirmation:'],
        ],
        'de' => [
            'Booking reference:' => 'Buchungsnummer:',
            'Dear'               => 'Liebe(r)',
            'Flight'             => 'Flug',
            'Departure'          => 'Abflug',
            'Arrival'            => 'Ankunft',
        ],
    ];

    public $lang = "en";

    private $reFrom = "flights@info.airbaltic.com";
    private $reSubject = [
        "en" => "7 days till your flight. Enhance your trip!",
        "en2"=> "Important Information! airBaltic online check-in extended to 5 days before departure",
        "en3"=> 'Your flight is now available for check-in',
        'de' => 'Ihr Flug ist jetzt zum Check-in bereit',
    ];
    private $reBody = 'Air Baltic';
    private $reBody2 = [
        "en" => "Departure",
        'de' => 'Abflug',
    ];

    private $date = null;

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
        $this->subject = $parser->getSubject();

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
            'emailType'  => 'reservations' . ucfirst($this->lang),
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
        $it['RecordLocator'] = $this->http->FindSIngleNode("//text()[{$this->starts($this->t('Booking reference:'))}]", null, true, "#(?:{$this->preg_implode($this->t('Booking reference:'))})\s+(\w+)#");

        // TripNumber
        // Passengers
        $dear = str_replace(['(', ')'], ['\(', '\)'], $this->t('Dear'));
        $it['Passengers'] = array_filter([$this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "#{$dear} (.+),#")]);

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

        // $xpath = "//text()[normalize-space(.)='FLIGHT NUMBER']/ancestor::tr[2]";
        // $nodes = $this->http->XPath->query($xpath);
        // if($nodes->length == 0){
        // $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        // }

        // foreach($nodes as $root){
        $date = strtotime($this->normalizeDate($this->http->FindSIngleNode("(//text()[{$this->contains($this->t('Flight'))}][preceding::text()[{$this->starts($this->t('Booking reference:'))}][1]])[1]", null, true, "#{$this->t('Flight')}\s+\w{2}\d+,\s+(\d+/\d+/\d{4})#")));
        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->http->FindSIngleNode("(//text()[{$this->contains($this->t('Flight'))}][preceding::text()[{$this->starts($this->t('Booking reference:'))}][1]])[1]", null, true, "#{$this->t('Flight')}\s+\w{2}(\d+),\s+\d+/\d+/\d{4}#");

        // DepCode
        $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Departure")));

        // DepName
        $itsegment['DepName'] = $this->re("#\d+:\d+\s+(.*?)\s+\([A-Z]{3}\)#", $this->nextText($this->t("Departure")));

        // DepDate
        $itsegment['DepDate'] = strtotime($this->re("#(\d+:\d+)#", $this->nextText($this->t("Departure"))), $date);

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})\)#", $this->nextText($this->t("Arrival")));

        // ArrName
        $itsegment['ArrName'] = $this->re("#\d+:\d+\s+(.*?)\s+\([A-Z]{3}\)#", $this->nextText($this->t("Arrival")));

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->re("#(\d+:\d+)#", $this->nextText($this->t("Arrival"))), $date);

        // AirlineName
        $itsegment['AirlineName'] = $this->http->FindSIngleNode("(//text()[{$this->contains($this->t('Flight'))}][preceding::text()[{$this->starts($this->t('Booking reference:'))}][1]])[1]", null, true, "#{$this->t('Flight')}\s+(\w{2})\d+,\s+\d+/\d+/\d{4}#");

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
        // }
        $itineraries[] = $it;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
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
            "#^(\d+)/(\d+)/(\d{4})$#",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        if (strtotime($str) === false) {
            $in = [
                "#^(\d+)\.(\d+)\.(\d{4})$#",
            ];
            $out = [
                "$2.$1.$3",
            ];
            $str = preg_replace($in, $out, $str);

            if (preg_match("#[^\d\W]#", $str)) {
                $str = $this->dateStringToEnglish($str);
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
}
