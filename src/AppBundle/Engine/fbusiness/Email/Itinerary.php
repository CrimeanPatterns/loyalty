<?php

namespace AwardWallet\Engine\fbusiness\Email;

use AwardWallet\Engine\MonthTranslate;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = ""; // +1 bcdtravel(html)[de]

    public $reSubject = [
        'de' => ['Reiseplan für'],
    ];

    public $providerCode = '';
    public $lang = '';

    public $langDetectors = [
        'de' => ['Reiseplan'],
    ];

    public static $dictionary = [
        'de' => [],
    ];

    public function parseHtml(&$itineraries)
    {
        $traveller = $this->nextText('Reiseplan', null, '/^(\w[^:]+)$/u');

        if (!$traveller) {
            $traveller = $this->nextText('Reisender', null, '/^(\w[^:]+)$/u');
        }

        $ffNumber = $this->http->FindSingleNode("//text()[" . $this->starts("Vielflieger:") . "]", null, true, "#:\s+(.+)#");

        $ticketNumbers = [];
        $ticketNumber = $this->http->FindSingleNode("//text()[" . $this->starts("E-Ticketnummer:") . "]", null, true, "#:\s+(.+)#");

        if ($ticketNumber) {
            $ticketNumbers = explode(',', $ticketNumber);
        }

        if (empty($ticketNumbers[0])) {
            $ticketNumberTexts = $this->http->FindNodes('//text()[normalize-space(.)="Ticketnummer:"]/following::text()[normalize-space(.)][1]', null, '/^(\d{2}[-\d\s]+\d{2})$/');
            $ticketNumberValues = array_values(array_filter($ticketNumberTexts));

            if (!empty($ticketNumberValues[0])) {
                $ticketNumbers = array_unique($ticketNumberValues);
            }
        }

        //#################
        //##   HOTELS   ###
        //#################
        $nodes = $this->http->XPath->query("//img[contains(@src, '/accommodation_dunkel.png')]/ancestor::td[1]/following-sibling::td[1]");

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = 'R';

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->nextText($this->t("Bestätigungsnr.:"), $root);

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src, '/phone-pictogram.gif')]/preceding::td[1]/descendant::text()[normalize-space(.)][1]")));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src, '/phone-pictogram.gif')]/preceding::td[1]/descendant::text()[normalize-space(.)][2]")));

            // Address
            $it['Address'] = implode(" ", $this->http->FindNodes(".//img[contains(@src, '/phone-pictogram.gif')]/ancestor::td[1]//text()[normalize-space(.) and ./following::img[contains(@src, '/phone-pictogram.gif')]]", $root));

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode(".//img[contains(@src, '/phone-pictogram.gif')]/following::text()[normalize-space(.)][1]", $root);

            // Fax
            $it['Fax'] = $this->http->FindSingleNode(".//img[contains(@src, '/fax-pictogram.gif')]/following::text()[normalize-space(.)][1]", $root);

            // GuestNames
            if ($traveller) {
                $it['GuestNames'] = [$traveller];
            }

            // Guests
            // Kids
            // Rooms
            // Rate
            // RateType
            $it['RateType'] = $this->nextText("Rate:", $root);

            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Stornierung:") . "]/following::td[1]", $root);

            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode(".//text()[" . $this->eq("Zimmer-/Raten Informationen:") . "]/following::td[1]/descendant::text()[normalize-space(.)][2]", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            $it['Total'] = $this->amount($this->nextText("Preis:", $root));

            // Currency
            $it['Currency'] = $this->currency($this->nextText("Preis:", $root));

            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->nextText("Buchungsstatus:", $root);

            // Cancelled
            // ReservationDate
            // NoItineraries
            $itineraries[] = $it;
        }

        //##################
        //##   FLIGHTS   ###
        //##################
        $roots = $this->http->XPath->query("//img[contains(@src, '/fligh_dunkel.png')]/ancestor::td[1]/following-sibling::td[1]");

        if ($roots->length === 0) {
            $roots = $this->http->XPath->query("//img[contains(@alt, 'Flight')]/ancestor::td[1]/following-sibling::td[1][ contains(.,'Klasse:') or contains(.,'Flugdauer:')]");
        }

        if ($roots->length > 0) {
            $it = [];
            $it['Kind'] = 'T';

            // RecordLocator
            $it['RecordLocator'] = $this->nextText("Buchungsnummer:", null, "#^\s*([A-Z\d]+)\s*$#");

            if (empty($it['RecordLocator'])) {
                $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='Buchungsnummer:'])[1]/following::text()[normalize-space(.) and not(contains(.,'Airline-'))][1]", null, true, "#^\s*([A-Z\d]+)\s*$#");
            }

            if (empty($it['RecordLocator'])) {
                $it['RecordLocator'] = $this->http->FindSingleNode('//text()[' . $this->contains(['Buchungsnummer:', 'Buchungsnummer :']) . ']', null, true, '/^[^:]+:\s*([A-Z\d]{5,})$/');
            }

            // Passengers
            if ($traveller) {
                $it['Passengers'] = [$traveller];
            }

            // AccountNumbers
            if ($ffNumber) {
                $it['AccountNumbers'] = [$ffNumber];
            }

            // TicketNumbers
            if (!empty($ticketNumbers[0])) {
                $it['TicketNumbers'] = $ticketNumbers;
            }

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
            $totalCharge = null;
            $currency = null;

            foreach ($roots as $root) {
                $itsegment = [];

                // AirlineName
                // FlightNumber
                $flight = $this->http->FindSingleNode('./table[1]/descendant::text()[normalize-space(.)][1]', $root);

                if (preg_match('/\b([A-Z\d]{2})\s+(\d+)$/', $flight, $matches)) {
                    $itsegment['AirlineName'] = $matches[1];
                    $itsegment['FlightNumber'] = $matches[2];
                }

                $root2 = $this->http->XPath->query("./table[2]/descendant::tr[normalize-space(.)][1]/..", $root)->item(0);

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][2]", $root2, true, "#(.*?)(?:\s+TERMINAL|$)#");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][2]", $root2, true, "#TERMINAL\s+(.+)#");

                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[normalize-space(.)][1]/td[normalize-space(.)][1]", $root2)));

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[normalize-space(.)][2]", $root2, true, "#(.*?)(?:\s+TERMINAL|$)#");

                // ArrivalTerminal
                $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[normalize-space(.)][2]", $root2, true, "#TERMINAL\s+(.+)#");

                // ArrDate
                $dateArr = $this->http->FindSingleNode("./tr[normalize-space(.)][2]/td[normalize-space(.)][1]", $root2);
                $overnight = null;

                if (preg_match('/^(.{3,})[+](\d{1,4})$/s', $dateArr, $matches)) { // 12:30 Uhr+1
                    $dateArr = $matches[1];
                    $overnight = $matches[2];
                }

                if (!empty($itsegment['DepDate']) && $dateArr) {
                    $itsegment['ArrDate'] = strtotime($this->normalizeDate($dateArr), $itsegment['DepDate']);
                }

                if (!empty($itsegment['ArrDate']) && $overnight) {
                    $itsegment['ArrDate'] = strtotime("+$overnight days", $itsegment['ArrDate']);
                }

                // Operator
                $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->starts("durchgeführt von:") . "]", $root, true, "#:\s+(.+)#");

                // Aircraft
                $itsegment['Aircraft'] = $this->nextText("Flugzeug:", $root);

                // BookingClass
                // Cabin
                $class = $this->nextText('Klasse:', $root);

                if (preg_match('/^([A-Z]{1,2})\s+-\s+(\w[^,]+\w)\b/u', $class, $matches)) {
                    $itsegment['BookingClass'] = $matches[1];
                    $itsegment['Cabin'] = $matches[2];
                }

                // Seats
                if ($seat = $this->nextText("Sitzplatz:", $root)) {
                    $itsegment['Seats'] = [$seat];
                }

                // Duration
                $itsegment['Duration'] = $this->nextText("Flugdauer:", $root);

                // Meal
                $itsegment['Meal'] = $this->nextText('An Bord:', $root);

                // DepCode
                // ArrCode
                if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName'])) {
                    $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                $payment = $this->nextText('Preis:', $root);
                // 1489.38 EUR
                if (preg_match('/^(\d[,.\d\s]*)([^\d]+)/', $payment, $matches)) {
                    $matches[1] = (float) $this->normalizePrice($matches[1]);
                    $matches[2] = trim($matches[2]);

                    if (!$currency) {
                        $totalCharge = $matches[1];
                        $currency = $matches[2];
                    } elseif ($currency === $matches[2]) {
                        $totalCharge += $matches[1];
                    }
                }

                $it['TripSegments'][] = $itsegment;
            }

            // TotalCharge
            // Currency
            if ($currency) {
                $it['TotalCharge'] = $totalCharge;
                $it['Currency'] = $currency;
            }

            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'first-business-travel.de') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider() === false) {
            return false;
        }

        // Detecting Language
        if ($this->providerCode) {
            return $this->assignLang();
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = true;

        $this->assignProvider();

        if ($this->assignLang() === false) {
            return null;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'Itinerary' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'providerCode' => $this->providerCode,
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

    private function assignProvider()
    {
        if ($this->http->XPath->query('//text()[contains(normalize-space(.),"FIRST Business Travel")]')->length > 0) {
            $this->providerCode = 'fbusiness';

            return true;
        } elseif ($this->http->XPath->query('//text()[contains(normalize-space(.),"Carlson Wagonlit Travel") or contains(normalize-space(.),"CARLSON WAGONLIT TRAVEL") or contains(.,"@contactcwt.com")]')->length > 0) {
            $this->providerCode = 'wagonlit';

            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['fbusiness', 'wagonlit'];
    }

    private function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        $str = str_replace("‌", "", $str);
        // $this->http->log($str);
        $str = str_replace("‌", "", $str); //hidden char
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\.\s+([^\d\s]+)$#", //Di, 1‌3‌. Jun
            "#^[^\s\d]+\s+(\d+)\.\s+([^\s\d]+)\s+(\d+:\d+)\s+Uhr$#", //Mo 25. Sep 08:30 Uhr
            "#^(\d+:\d+)\s+Uhr$#", //08:30 Uhr
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year, $3",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->http->log($str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
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

    private function nextText($field, $root = null, $regexp = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root, true, $regexp);
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
