<?php

namespace AwardWallet\Engine\expedia\Email;

class It4605304 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "expedia/it-12400479.eml, expedia/it-29087633.eml, expedia/it-4605304.eml, expedia/it-5054592.eml, expedia/it-6065665.eml";
    public $reBody2 = [
        "en"=> "You can find your full",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    protected $code = null;

    protected static $headers = [
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                "Your upcoming trip:",
            ],
        ],
        'ebookers' => [
            'from' => ['mailer.ebookers.'],
            'subj' => [
                'Your upcoming trip:',
            ],
        ],
        'lastminute' => [
            'from' => ['email.lastminute.com.au'],
            'subj' => [
                'Your upcoming trip:',
            ],
        ],
    ];
    protected $bodies = [
        'orbitz' => [
            '//img[contains(@alt,"Orbitz.com")]',
            'Collected by Orbitz',
        ],
        'lastminute' => [
            '//img[contains(@alt,"lastminute.com")]',
            '//a[contains(.,"lastminute.com")]/parent::*[contains(.,"Collected by")]',
        ],
        'travelocity' => [
            '//img[contains(@alt,"Travelocity.com")]',
            'Collected by Travelocity',
        ],
        'ebookers' => [
            '//img[contains(@alt,"ebookers.com") or contains(@alt,"ebookers.fi")]',
            'Collected by ebookers',
            'Maksun veloittaa ebookers',
        ],
    ];

    protected $reBody = [
        'Expedia',
        'lastminute',
        'ebookers',
    ];

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[normalize-space(.)='➜']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\d+$#");

            if ($rl = $this->nextText($airline . " Booking Reference")) {
                $airs[$rl][] = $root;
            } elseif ($rl = $this->nextText("Expedia Booking Reference")) {
                $airs[$rl][] = $root;
            }
        }
        // print_r($airs);
        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->nextText("Itinerary #");

            // Passengers
            $it['Passengers'] = array_filter([
                $this->http->FindSingleNode("//text()[normalize-space(.)='View Rewards Activity']/ancestor::tr[1]/../tr[1]"),
            ]);

            if (empty($it['Passengers'])) {
                $it['Passengers'] = array_filter([
                    $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Itinerary #')]/preceding::table//img/ancestor::td[1]/ancestor::*[1][count(./td) = 2]/td[2]", null, true, "#^\s*((?:\b[A-Za-z\-]+\b\s*)+)\s*$#"),
                ]);
            }

            // AccountNumbers
            // Cancelled
            // TotalCharge
            // BaseFare
            // Currency
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            if (!($it['Status'] = $this->nextText("Flight Summary at Time of Booking"))) {
                $it['Status'] = $this->nextText("Flight Summary");
            }

            // ReservationDate
            // NoItineraries
            // TripCategory
            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./ancestor::tr[1]/preceding-sibling::tr[contains(., 'Departure') or contains(., 'Return')][1]/descendant::text()[normalize-space(.)][last()]", $root)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^.*?\s+(\d+)(?:$|\soperated by)#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[2]/descendant::text()[contains(normalize-space(.), 'Terminal')][1]", $root, true, "#Terminal\s*(.+)#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[4]/descendant::text()[contains(normalize-space(.), 'Terminal')][1]", $root, true, "#Terminal\s*(.+)#");

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]/descendant::text()[contains(translate(normalize-space(.), '0123456789', 'dddddddddd'), 'd:dd')][1]", $root), $date);

                if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                    $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                }

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(.*?)\s+\d+(?:$|\soperated by)#");

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode("./td[1]/descendant::text()[contains(normalize-space(.), 'operated by')][1]", $root, true, "#.*operated by\s+(.+)\)#i");

                // Aircraft
                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[1]/descendant::text()[contains(normalize-space(.), 'duration')]/following::text()[normalize-space()][1]", $root, null, "#(\w+)(?:\s+/\s+Coach|$)#");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][3]", $root, null, "#Seat\s+(\d+\w)\s+#");

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./td[1]/descendant::text()[contains(normalize-space(.), 'duration')]", $root, null, "#(.*?)\s+duration#");

                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        if (!self::detectEmailByBody($parser)) {
            $this->logger->debug('can\'t determine a format');

            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'It4605304',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

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
        if ($this->unitDetectByBody($parser->getHTMLBody())) {
            return true;
        }
        // many letters with information in html-attachments
        $htmls = $this->getHtmlAttachments($parser);

        foreach ($htmls as $html) {
            if ($this->unitDetectByBody($html)) {
                $this->http->SetEmailBody($html);

                return true;
            }
        }

        return false;
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

    protected function getProvider(\PlancakeEmailParser $parser)
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
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function unitDetectByBody($body)
    {
        foreach ($this->reBody as $s) {
            if (stripos($body, $s) === false) {
                $first = true;
            }
        }

        if (empty($first)) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
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
            "#^(\w+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
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

    private function getHtmlAttachments(\PlancakeEmailParser $parser, $length = 20000)
    {
        $result = [];
        $altCount = $parser->countAlternatives();

        for ($i = 0; $i < $parser->countAttachments() + $altCount; $i++) {
            $html = $parser->getAttachmentBody($i);
            $info = $parser->getAttachmentHeader($i, 'content-type');

            if (preg_match("#^text/html;#", $info) && is_string($html) && strlen($html) > $length) {
                $result[] = $html;
            }
        }

        return $result;
    }
}
