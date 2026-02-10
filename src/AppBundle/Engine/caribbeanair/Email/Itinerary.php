<?php

namespace AwardWallet\Engine\caribbeanair\Email;

use AwardWallet\Engine\MonthTranslate;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "caribbeanair/it-7363368.eml, caribbeanair/it-7534076.eml, caribbeanair/it-63897340.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";
    private $reFrom = "no-reply@Liatairline.com";
    private $reSubject = [
        "en" => "LIAT Itinerary", "Firefly Travel Itinerary",
    ];

    private $reBody2 = [
        "en"=> "Flight Information:",
    ];
    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = true;
        $this->http->setEmailBody($parser->getHTMLBody());

        $this->assignProvider($parser->getHeaders());

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'providerCode' => $this->providerCode,
            'emailType'    => 'Itinerary' . ucfirst($this->lang),
            'parsedData'   => [
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

    public static function getEmailProviders()
    {
        return ['jsx', 'caribbeanair'];
    }

    private function parseHtml(&$itineraries): void
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//tr/*[{$this->eq("Confirmation Number:")}]/following-sibling::*[{$xpathNoEmpty}][1]", null, true, "/^[-A-Z\d]{5,}$/")
                ?? $this->http->FindSingleNode("//h3[{$this->starts("Confirmation:")}]", null, true, "/{$this->opt("Confirmation:")}\s*([-A-Z\d]{5,})$/");

        $xpathPaxRows = "//tr[ *[1][{$this->eq("Name")}] and *[position()=2 or position()=3][{$this->contains("Seat")}] ]/following-sibling::tr[normalize-space()]";

        // Passengers
        $passengers = $this->http->FindNodes($xpathPaxRows . "/descendant-or-self::tr[not(.//tr)]/*[1]", null, '/^(?:[^:]+[:]+)?\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/u');
        $it['Passengers'] = array_unique(array_filter($passengers));

        // 5C    |    NA
        $patterns['seat'] = "(?:[Nn][Aa]|\d+[A-Z])";

        $seatsByFlight = [];
        $seatsText = $this->htmlToText($this->http->FindHTMLByXpath("//tr[ *[1][{$this->eq("Name")}] and *[3][{$this->contains("Seat")}] ]/following-sibling::tr[normalize-space()]/*[3]    |    //tr[ *[1][{$this->eq("Name")}] and *[2][{$this->contains("Seat")}] ]/following-sibling::tr[normalize-space()]/*[2]"));
        // FY1146/NA  FY1151/NA    |    170 - 4C  171 - 5C
        preg_match_all("/(\d+)(?:\/|[ ]+-[ ]+)({$patterns['seat']})[ ]*$/m", $seatsText, $seatMatches, PREG_SET_ORDER);

        foreach ($seatMatches as $m) {
            $seatsByFlight[$m[1]][] = $m[2];
        }

        // TotalCharge
        // Currency
        // BaseFare
        $totalPrice = $this->nextText("Total Fare Price:")
                ?? $this->nextText("Total Including Service Charges:")
                ?? $this->nextText("Total:");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+)$/', $totalPrice, $matches)
                || preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)
            ) {
            // 447.42 MYR    |    $796.00
            $it['TotalCharge'] = $this->normalizeAmount($matches['amount']);
            $it['Currency'] = $matches['currency'];

            $baseFare = $this->nextText("Base Fare:");

            if (preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $baseFare, $m)
                    || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $baseFare, $m)
                ) {
                $it['BaseFare'] = $this->normalizeAmount($m['amount']);
            }

            $fees = [];
            $feeRows = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and preceding-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq("Base Fare:")}]] and following-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq(["Total Fare Price:", "Total:"])}]] ]    |    //tr[ count(*[normalize-space()])=2 and preceding-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq("Total:")}]] and following-sibling::tr[count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq("Total Including Service Charges:")}]] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow);

                if (preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/', $feeCharge, $m)
                        || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $feeCharge, $m)
                    ) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[: ]*$/');
                    $fees[] = ['Name' => $feeName, 'Charge' => $this->normalizeAmount($m['amount'])];
                }
            }

            if (count($fees)) {
                $it['Fees'] = $fees;
            }
        }

        // Status
        $status = $this->nextText("Booking Status:");

        if ($status) {
            $it['Status'] = $status;
        }

        // ReservationDate
        $bookingDate = $this->nextText("Booking Date:");

        if ($bookingDate) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($bookingDate));
        }

        $xpath = "//tr[ *[3][{$this->eq("Depart")}] ]/following-sibling::tr[ *[normalize-space()][4] ]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            // AirlineName
            // FlightNumber
            $flightNumber = $this->http->FindSingleNode("td[2]", $root);

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])?(\d+)$/", $flightNumber, $m)) {
                // FY2442    |    2442
                if (!empty($m[1])) {
                    $itsegment['AirlineName'] = $m[1];
                } elseif ($this->providerCode == 'jsx') {
                    $itsegment['AirlineName'] = 'XE';
                } else {
                    $itsegment['AirlineName'] = AIRLINE_UNKNOWN;
                }
                $itsegment['FlightNumber'] = $m[2];
            }

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[5]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[5]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[6]", $root)), $date);

            // Seats
            if (!empty($itsegment['FlightNumber']) && !empty($seatsByFlight[$itsegment['FlightNumber']])) {
                $seats = array_filter($seatsByFlight[$itsegment['FlightNumber']], function ($item) {
                    return preg_match('/^\d+[A-Z]$/', $item) > 0;
                });

                if (count($seats)) {
                    $itsegment['Seats'] = $seats;
                }
            }

            // Stops
            $stops = $this->http->FindSingleNode("td[7]", $root, true, "/^\d{1,3}$/");

            if ($stops !== null) {
                $itsegment['Stops'] = $stops;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['subject'], 'Your JSX Flight') !== false
            || $this->http->XPath->query('//a[contains(@href,".jsx.com/")]')->length > 0
            || $this->http->XPath->query('//node()[contains(normalize-space(),"JSX. All Rights Reserved") or contains(.,"@jsx.com")]')->length > 0
        ) {
            $this->providerCode = 'jsx';

            return true;
        }

        if (stripos($headers['from'], '@Liatairline.com') !== false
            || stripos($headers['from'], '@firefly.com') !== false
            || stripos($headers['subject'], 'LIAT Itinerary') !== false
            || stripos($headers['subject'], 'Firefly Travel Itinerary') !== false
            || $this->http->XPath->query('//a[contains(@href,".liat.com/") or contains(@href,"www.liat.com") or contains(@href,".fireflyz.com.my/") or contains(@href,"www.fireflyz.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"www.liat.com") or contains(.,"www.fireflyz.com")]')->length > 0
        ) {
            $this->providerCode = 'caribbeanair';

            return true;
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^[^\d\s]+\s+(\d+\s+[^\d\s]+\s+\d{4})$#", //Sun 24 Jan 2016
            "#^(\d+:\d+):([AP]M)$#", //06:30:PM
        ];
        $out = [
            "$1",
            "$1 $2",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
