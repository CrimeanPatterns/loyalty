<?php

namespace AwardWallet\Engine\amtrak\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-1586439.eml, amtrak/it-1939381.eml, amtrak/it-1961189.eml, amtrak/it-1968235.eml, amtrak/it-99315207.eml";
    public $reFrom = "tickets@amtrak.com";
    public $reSubject = [
        "en"=> "Amtrak: Reservation Confirmation",
    ];
    public $reBody = 'Amtrak';
    public $reBody2 = [
        "en"=> "ITINERARY",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries): void
    {
        $text = str_replace(["\n> ", "\r"], ["\n", ""], $this->http->Response['body']);
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        if (!$it['RecordLocator'] = $this->re("#Reservation Number:\s+(\w+)#", $text)) {
            $it['RecordLocator'] = $this->re("#Original Reservation number:\s+(\w+)#", $text);
        }

        // Cancelled
        if (strpos($text, 'RESERVATION CANCELED') !== false) {
            $it['Cancelled'] = true;
        }

        // Passengers
        preg_match_all("#Passenger\s+\d+:\s+(.*?)\s+\(#", $text, $Passengers);
        $it['Passengers'] = array_unique($Passengers[1]);

        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->re("#Total\s+\D+([\d\,\.]+)#", $text));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->re("#Rail Fare\s+\D+([\d\,\.]+)#", $text));

        // Currency
        $it['Currency'] = $this->currency($this->re("#Total\s+(.+)#", $text));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        /*
            Service: 53 Auto Train
            Duration: 17 hr, 30 min

            <Departs>
            Lorton - Auto Train Only, VA (LOR)
            26-JUL-14; 4:00 pm

            <Arrives>
            Sanford - Auto Train Only, FL (SFA)
            27-JUL-14; 9:30 am
        */
        $patterns['station'] = "/^"
            . "[> ]*(?<stationName>.{3,}?)(?:[ ]*\([ ]*(?<stationCode>[A-Z]{3})[ ]*\))?(?:[> ]*\n)+"
            . "[> ]*(?<dateTime>[^\n;]{6,};[ ]+\d{1,2}:\d{2}[^\n;]*?)[ ]*$"
            . "/m"
        ;

        $segments = $this->splitText($text, "/\n([> ]*Service:[ ]+.+(?:[> ]*\n)+[> ]*Duration:[ ]+.+[ ]*\n)/", true);

        foreach ($segments as $sText) {
            $itsegment = [];

            if (preg_match("/^[> ]*Service:[ ]+(?<type>.+?)(?:[> ]*\n)+[> ]*Duration:[ ]+(?<duration>.+?)[ ]*\n/", $sText, $m)) {
                $itsegment['FlightNumber'] = $this->re("/(\d+)/", $m['type']);
                $itsegment['Type'] = $m['type'];
                $itsegment['Duration'] = $m['duration'];
            }

            if (preg_match_all($patterns['station'], $sText, $matches) && count($matches[0]) === 2) {
                $itsegment['DepName'] = $matches['stationName'][0];
                $itsegment['ArrName'] = $matches['stationName'][1];

                if (empty($matches['stationCode'][0])) {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                } else {
                    $itsegment['DepCode'] = $matches['stationCode'][0];
                }

                if (empty($matches['stationCode'][1])) {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                } else {
                    $itsegment['ArrCode'] = $matches['stationCode'][1];
                }

                $itsegment['DepDate'] = strtotime($this->normalizeDate($matches['dateTime'][0]));
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($matches['dateTime'][1]));
            }

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
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $plain = $parser->getPlainBody();
        $html = $parser->getHTMLBody();

        if (strpos($plain, $this->reBody) === false && strpos($html, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($plain, $re) !== false || strpos($html, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (strlen($parser->getPlainBody()) > 0) {
            $this->http->setBody($parser->getPlainBody());
        } else {
            $this->http->setBody(implode("\n", $this->http->FindNodes("//text()[normalize-space(.)!='']")));
        }

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

        $result = [
            'emailType'  => 'ReservationConfirmation' . ucfirst($this->lang),
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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-([^\d\s]+)-(\d{2});\s+(\d+:\d+\s+[ap]m)$#", //26-JUL-14; 4:00 pm
        ];
        $out = [
            "$1 $2 20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
