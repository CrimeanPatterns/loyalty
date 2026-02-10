<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class PrePackagedVacation extends \TAccountCheckerExtended
{
    public $mailFiles = "expedia/it-12081503.eml, expedia/it-12087789.eml";

    public static $dictionary = [
        'en' => [
            //			"Traveller Information" => "",
            //			"Confirmation#" => "",
            //			"booking is confirmed" => "",
            "All prices" => "All prices quoted in",
            "CurrencyRe" => "#quoted in ([^\.]+).#",
            //			"You earned" => "",
            //Flight
            //			"Flight Information" => "",
            //Hotels
            //			"Hotel Information" => "",
            //			"Check-in:" => "",
            //			"Check-out:" => "",
            //			"Room Type:" => "",
            //			"# of Rooms:" => "",
        ],
    ];

    protected $lang = 'en';

    private static $headers = [
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                'Your Pre-packaged Vacation Confirmation',
            ],
        ],
        'travelocity' => [
            'from' => ['@e.travelocity.com'],
            'subj' => [
                'Your Pre-packaged Vacation Confirmation',
            ],
        ],
    ];
    private $code = '';

    private $bodies = [
        'travelocity' => [
            '//img[contains(@src,"travelocity.com")]',
            'travelocity.com',
        ],
    ];

    private $reBody = [
        'Expedia',
        'Travelocity',
    ];

    private $reBody2 = [
        'en' => ['directly with the Tour Operator'],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom) {
                $this->code = $code;
            }

            if ($byFrom && $bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->detectLang($parser);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $this->detectLang($parser);

        $itineraries = $this->parseHtml();

        $result = [
            'emailType'  => 'PrePackagedVacation' . ucfirst($this->lang),
            'parsedData' => [
            ],
        ];

        $payment = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total:")) . "])[1]/following::text()[normalize-space()][1]");

        if (!empty($payment)) {
            $total = $this->amount($payment);

            $currency = $this->currency($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("All prices")) . "])[1]", null, true, $this->t("CurrencyRe")));

            if (empty($currency)) {
                $currency = $this->currency($payment);
            }

            if ($total !== null && $currency !== null) {
                if (count($itineraries) === 1 && $itineraries[0]['Kind'] === 'T') {
                    $itineraries[0]['TotalCharge'] = $total;
                    $itineraries[0]['Currency'] = $currency;
                } elseif (count($itineraries) === 1 && $itineraries[0]['Kind'] === 'R') {
                    $itineraries[0]['Total'] = $total;
                    $itineraries[0]['Currency'] = $currency;
                }

                $result['parsedData']['TotalCharge']['Amount'] = $total;
                $result['parsedData']['TotalCharge']['Currency'] = $currency;
            }

            $EarnedAwards = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("You earned")) . "])[1]/following::text()[normalize-space()][1][contains(.,'Expedia')]", null, true, "#.*\d+.*#");

            if (!empty($EarnedAwards)) {
                $result['parsedData']['TotalCharge']['EarnedAwards'] = $EarnedAwards;
            }
        }

        $result['parsedData']['Itineraries'] = $itineraries;

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

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

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    protected function parseHtml()
    {
        $passengers = $this->http->FindNodes("(//text()[" . $this->eq($this->t("Traveller Information")) . "])[1]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]//tr[not(.//tr)]/descendant::text()[normalize-space()][1]");
        $tripNumber = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Confirmation#")) . "])[1]", null, true, "/" . $this->preg_implode($this->t("Confirmation#")) . "\s*(\d+)/");

        $this->year = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Confirmation#")) . "])[1]/ancestor::tr[1]", null, true, "#/(20\d{2}).*\|#");

        //######################################
        //#             FLIGHTS               ##
        //######################################

        $xpath = "(.//text()[" . $this->eq($this->t("Flight Information")) . "])[1]/ancestor::tr[1]/following-sibling::tr[.//table]";
        $nodes = $this->http->XPath->query($xpath);
        //		$this->logger->info('Segments for flights: '.$xpath);
        if ($nodes->length > 0) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $tripNumber;

            // Passengers
            $it['Passengers'] = $passengers;

            if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("booking is confirmed")) . "]"))) {
                $it['Status'] = 'confirmed';
            }

            foreach ($nodes as $root) {
                $seg = [];

                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[not(.//table)][1]", $root, true, "#(.+)-#"));

                // AirlineName
                // FlightNumber
                $flight = $this->http->FindSingleNode('.//table/descendant::tr[3]/td[normalize-space()][1]', $root);

                if (preg_match('/^(?<airlineFull>.+?)\s+(?<flightNumber>\d+)(?:\s*[+]\s*(?<plusDays>\d{1,3})\s*day)?$/i', $flight, $matches)) { // WestJet 2153 +1 day
                    $seg['AirlineName'] = $matches['airlineFull'];
                    $seg['FlightNumber'] = $matches['flightNumber'];

                    if (!empty($matches['plusDays'])) {
                        $plusDays = $matches['plusDays'];
                    }
                }

                // DepCode
                $seg['DepCode'] = $this->http->FindSingleNode(".//table/descendant::tr[2]/td[normalize-space()][1]", $root, true, "#^\s*([A-Z]{3})\b#");

                // DepName
                $seg['DepName'] = $this->http->FindSingleNode(".//table/descendant::tr[1]/td[normalize-space()][1]", $root);

                // DepDate
                $time = $this->http->FindSingleNode('.//table/descendant::tr[2]/td[normalize-space()][1]', $root, true, '#[A-Z]{3}\s+(.+)#');

                if ($time && $date) {
                    $seg['DepDate'] = strtotime($date . ' ' . $time);
                }

                // ArrCode
                $seg['ArrCode'] = $this->http->FindSingleNode(".//table/descendant::tr[2]/td[normalize-space()][2]", $root, true, "#^\s*([A-Z]{3})\b#");

                // ArrName
                $seg['ArrName'] = $this->http->FindSingleNode(".//table/descendant::tr[1]/td[normalize-space()][2]", $root);

                // ArrDate
                $time = $this->http->FindSingleNode('.//table/descendant::tr[2]/td[normalize-space()][2]', $root, true, '#[A-Z]{3}\s+(.+)#');

                if ($time && $date) {
                    $seg['ArrDate'] = strtotime($date . ' ' . $time);

                    if (isset($plusDays)) {
                        $seg['ArrDate'] = strtotime("+$plusDays days", $seg['ArrDate']);
                    }
                }

                // Cabin
                // BookingClass
                $node = $this->http->FindSingleNode('.//table/descendant::tr[4]', $root);

                if (!empty($node) && preg_match("#^([^(\|]+)\(([A-Z]{1,2})\)\s*|#", $node, $m)) {
                    $seg['Cabin'] = trim($m[1]);
                    $seg['BookingClass'] = $m[2];
                }

                // Duration
                $seg['Duration'] = $this->http->FindSingleNode(".//table/descendant::tr[1]/td[normalize-space()][3]", $root);

                $it['TripSegments'][] = $seg;
            }
            $itineraries[] = $it;
        }

        //######################################
        //#               Hotel               ##
        //######################################
        $xpath = "(.//text()[" . $this->eq($this->t("Hotel Information")) . "])[1]/ancestor::tr[1]/ancestor::*[1]";
        $nodes = $this->http->XPath->query($xpath);
        //		$this->logger->info('Segments for hotel: '.$xpath);

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

            // TripNumber
            $it['TripNumber'] = $tripNumber;

            // HotelName
            $it['HotelName'] = trim($this->http->FindSingleNode("./tr[2]", $root, true, "#(.+?)(?:\(\s*[\d\.]+\s*star.*\))?$#"));

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check-in:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root)));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check-out:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root)));

            // Address
            $it['Address'] = $it['HotelName'];

            // GuestNames
            $it['GuestNames'] = $passengers;

            // Guests
            if (!empty($it['GuestNames'])) {
                $it['Guests'] = count($it['GuestNames']);
            }

            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("# of Rooms:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("Room Type:")) . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // Status
            if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("booking is confirmed")) . "]"))) {
                $it['Status'] = 'confirmed';
            }

            $itineraries[] = $it;
        }

        return $itineraries;
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function detectLang(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $finded = false;

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false) {
                $finded = true;

                break;
            }
        }

        if ($finded == false) {
            return false;
        }

        foreach ($this->reBody2 as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($body, $line) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'expedia') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map('preg_quote', $field));
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*([^\d\s\.\,]+)[.]*\s*,\s+([^\d\s\.]+)[.]*\s+(\d+)\s*$#u", //Sun, Jun 25
        ];
        $out = [
            "$1, $3 $2 {$this->year}",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d{1,2}\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^(\w+),\s+(\w+\s+\w+\s+\d{4})#u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m[1], $this->lang));
            $str = date("d F Y", EmailDateHelper::parseDateUsingWeekDay($m[2], $weeknum));
        }

        return $str;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\. ]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            'Canadian dollars' => 'CAD',
            'US dollars'       => 'USD',
            '€'                => 'EUR',
            'R$'               => 'BRL',
            'C$'               => 'CAD',
            'SG$'              => 'SGD',
            'HK$'              => 'HKD',
            'AU$'              => 'AUD',
            '$'                => 'USD',
            '£'                => 'GBP',
            'kr'               => 'NOK',
            'RM'               => 'MYR',
            '฿'                => 'THB',
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
}
