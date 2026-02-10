<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class FlightItineraryBlue extends \TAccountChecker
{
    public $mailFiles = "bcd/it-10623377.eml, bcd/it-10623383.eml, bcd/it-156832081-en.eml";
    public $reFrom = "@bcdtravel.";
    public $reSubject = [
        "de"=> "Reiseangebot für",
        "en"=> "Hotel not booked for",
    ];
    public $reBody = 'BCD Travel';
    public $reBody2 = [
        "de"=> "Leistungsträger",
        "en"=> "Vendor",
    ];

    public static $dictionary = [
        'de' => [
            'Vendor' => 'Leistungsträger',
            // 'Flygtaxi' => '',
            'Confirmed'    => 'Bestätigt',
            'Traveller(s)' => 'Reisende(r)',
            'Total:'       => 'Gesamt:',
            'Fare:'        => 'Tarif:',
            'Tax:'         => 'Taxes:',
            // 'operated by' => '',
            'timeValues' => ['Stunde', 'Minute'],
        ],
        'en' => [
            'timeValues' => ['hour', 'minute'],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries): void
    {
        $xpath = "//td[2][{$this->eq($this->t('Vendor'))}]/ancestor::tr[1]/following-sibling::tr[td[6] and not({$this->contains($this->t('Flygtaxi'))})]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            if (!$rl = $this->nextText($this->t('Confirmed'), $root)) {
                $this->logger->debug("RL not matched");

                return;
            }
            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // Passengers
            $travellers = [];
            $travellerRows = $this->http->XPath->query("//text()[{$this->eq($this->t('Traveller(s)'))}]/ancestor::tr[1]/following-sibling::tr[ following-sibling::tr[{$this->contains($this->t('Vendor'))}] ]/td[1]");

            foreach ($travellerRows as $tRow) {
                $travellerText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $tRow));

                if (preg_match_all("/^[ ]*((?:[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]](?:[ \/]+|$))+)/mu", $travellerText, $travellerMatches)) {
                    $travellers = array_merge($travellers, $travellerMatches[1]);
                }
            }
            $travellers = array_map(function ($item) {
                $item = rtrim($item);

                return preg_replace('/^(.{2,}?)(?:[ ]+(?:MISS|MRS|DR|MR|MS))+$/i', '$1', $item);
            }, $travellers);
            $it['Passengers'] = $travellers;

            // TicketNumbers
            $tickets = [];

            // TotalCharge
            // BaseFare
            // Tax
            $currency = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total:'))}]/following-sibling::td[1]", null, true, '/^[^\-\d)(]+$/');
            $totalPrice = $this->http->FindSingleNode("//td[{$this->eq($this->t('Total:'))}]/following-sibling::td[2]", null, true, '/^\d[,.\'\d ]*$/');

            if ($currency && $totalPrice !== null) {
                // SEK 40884.00
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $it['Currency'] = $currency;
                $it['TotalCharge'] = PriceHelper::parse($totalPrice, $currencyCode);

                $baseFare = $this->http->FindSingleNode("//td[{$this->eq($this->t('Fare:'))}]/following-sibling::td[1][{$this->eq($currency)}]/following-sibling::td[1]", null, true, '/^\d[,.\'\d ]*$/');

                if ($baseFare !== null) {
                    $it['BaseFare'] = $baseFare;
                }

                $tax = $this->http->FindSingleNode("//td[{$this->eq($this->t('Tax:'))}]/following-sibling::td[1][{$this->eq($currency)}]/following-sibling::td[1]", null, true, '/^\d[,.\'\d ]*$/');

                if ($tax !== null) {
                    $it['Tax'] = $tax;
                }
            }

            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

                if (($col = $this->http->XPath->query("./td[3]", $root)->item(0)) === null) {
                    $this->logger->info("column not found");

                    return;
                }

                $detailsText = preg_replace('/[ ]+/', ' ', $this->htmlToText($this->http->FindHTMLByXpath('.', null, $col)));
                $col = preg_split('/[ ]*\n+[ ]*/', $detailsText);

                if (count($col) < 5) {
                    $this->logger->info("incorrect rows count");

                    return;
                }

                $itsegment = [];

                // AirlineName
                // FlightNumber
                $flight = $this->http->FindSingleNode("td[2]/descendant::text()[normalize-space()][2]", $root);

                if (preg_match('/^((?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+))\*?$/', $flight, $m)) {
                    $itsegment['AirlineName'] = $m['name'];
                    $itsegment['FlightNumber'] = $m['number'];

                    $fTickets = array_filter($this->http->FindNodes("//tr[ *[5][{$this->eq($this->t('Ticket Information'))}] ]/following-sibling::tr[ *[1][{$this->contains($m[1])}] and *[8] ]", null, "/{$this->opt($this->t('Ticket Number'))}\s*[:]+\s*(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})(?:\D|$)/"));

                    if (count($fTickets) > 0) {
                        $tickets = array_merge($tickets, $fTickets);
                    }
                }

                // DepName
                $itsegment['DepName'] = $this->re("#(.*?)(?:, Terminal:|$)#", $col[0]);

                // DepartureTerminal
                $terminalDep = $this->re("/Terminal:\s*(.+)/i", $col[0]);

                if ($terminalDep) {
                    $itsegment['DepartureTerminal'] = preg_replace("/^Terminal[-:\s]+([A-Z\d]+)$/i", '$1', $terminalDep);
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root), $date);

                // ArrName
                $itsegment['ArrName'] = $this->re("#(.*?)(?:, Terminal:|$)#", $col[1]);

                // ArrivalTerminal
                $terminalArr = $this->re("/Terminal:\s*(.+)/i", $col[1]);

                if ($terminalArr) {
                    $itsegment['ArrivalTerminal'] = preg_replace("/^Terminal[-:\s]+([A-Z\d]+)$/i", '$1', $terminalArr);
                }

                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][2]", $root), $date);

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('operated by'))}]", $root, true, "/{$this->opt($this->t('operated by'))}\s+(.+)/");

                // Aircraft
                if (preg_match("/^[ ]*(.*(?:Airbus|Embraer|Boeing).*?)[ ]*$/im", $detailsText, $m)) {
                    $itsegment['Aircraft'] = $m[1];
                }

                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][1]", $root);

                // BookingClass
                $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][2]", $root);

                // Seats
                if (preg_match("/((?:\n+[ ]*\d+[A-Z]\b.*)+)/", $detailsText, $m)
                    && preg_match_all("/^[ ]*(\d+[A-Z]\b).*$/m", $m[1], $seatMatches)
                ) {
                    $itsegment['Seats'] = $seatMatches[1];
                }

                // Duration
                if (preg_match("/^[ ]*(\d{1,3}\s*{$this->opt($this->t('timeValues'))}.*?)[ ]*$/im", $detailsText, $m)) {
                    // 2 hour(s) and 35 minute(s)
                    $itsegment['Duration'] = $m[1];
                }

                // Meal
                // Smoking
                // Stops
                if (preg_match("/^[ ]*non[-\s]*stops?[ ]*$/im", $detailsText)) {
                    $itsegment['Stops'] = 0;
                }

                if (!empty($itsegment['DepDate']) && !empty($itsegment['ArrDate'])) {
                    $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                $it['TripSegments'][] = $itsegment;
            }

            if (count($tickets) > 0) {
                $it['TicketNumbers'] = array_unique($tickets);
            }

            $itineraries[] = $it;
        }
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
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true; // fixing damaged flight segments
        $this->http->SetEmailBody($this->http->Response['body']);

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'FlightItineraryBlue' . ucfirst($this->lang),
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
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+ (\d+) ([^\s\d]+)$#", //Sonntag 07 Jan
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (strtotime($str) < $this->date && strpos($str, $year) !== false) {
            $str = str_replace($year, $year + 1, $str);
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

    private function nextText($field, $root = null): ?string
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
