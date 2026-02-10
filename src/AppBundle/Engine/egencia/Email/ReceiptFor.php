<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;

class ReceiptFor extends \TAccountChecker
{
    public $mailFiles = "egencia/it-8447619.eml, egencia/it-8972043.eml";

    public $reSubject = [
        'en' => ['Receipt for'],
    ];

    public $reBody2 = [
        'en' => ['Itinerary Receipt'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = '';

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Egencia') !== false
            || stripos($from, '@egencia.com') !== false;
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
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Thank you for choosing Egencia") or contains(normalize-space(.),"through Egencia") or contains(normalize-space(.),"Egencia fee charge:")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.egencia.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;

        if ($this->assignLang() === false) {
            return false;
        }

        return $this->parseEmail();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail()
    {
        $result['emailType'] = $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang);

        $its = [];

        $countFlights = $this->http->XPath->query('//div[' . $this->eq('Flights') . ' and not(.//div)]')->length;
        $countHotels = $this->http->XPath->query('//div[' . $this->eq('Hotels') . ' and not(.//div)]')->length;

        if ($countFlights === 1 && $countHotels === 0) {
            $itFlights = $this->parseFlights();

            if ($itFlights) {
                $its = array_merge($its, $itFlights);
            }
        } elseif ($countHotels === 1 && $countFlights === 0) {
            $itHotels = $this->parseHotels();

            if ($itHotels) {
                $its = array_merge($its, $itHotels);
            }

            $payment = $this->http->FindSingleNode('//td[not(.//td) and ' . $this->starts('Total hotel charges') . ']/following-sibling::td[last()]');
            // $521.30
            if (preg_match('/^([^\d]+)\s*([,.\d]+)/', $payment, $matches)) {
                $result['parsedData']['TotalCharge']['Currency'] = trim($matches[1]);
                $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[2]);
            }
        }

        if (empty($its[0])) {
            return false;
        }

        $result['parsedData']['Itineraries'] = $its;

        return $result;
    }

    private function parseFlights()
    {
        $its = [];

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts("Booking ID:") . "]", null, true, "#:\s*(.+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->contains("Ticket number:") . "]", null, "#(.*?)\s+-#");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->contains("Ticket number:") . "]", null, "#Ticket number:\s+(.+)#");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Total flight charges"));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->nextText("Base fare"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total flight charges"));

        // Tax
        $it['Tax'] = $this->amount($this->nextText("Taxes & airline fees"));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq("Base fare") . "]/preceding::div[normalize-space(.)][1]//p";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];
            // Southwest Airlines 1583 (Wed Sep 6, 2017) - HOU-BNA, Economy/Coach Class (N)
            if (preg_match("#^(?<AirlineName>.*?)\s+(?<FlightNumber>\d+)\s+\((?<Date>.*?)\)\s+-\s+(?<DepCode>[A-Z]{3})-(?<ArrCode>[A-Z]{3}),\s+(?<Cabin>.*?)\s+\((?<BookingClass>\w)\)$#", $this->http->FindSingleNode(".", $root), $m)) {
                $keys = ['AirlineName', 'FlightNumber', 'DepCode', 'ArrCode', 'Cabin', 'BookingClass'];

                foreach ($keys as $key) {
                    $itsegment[$key] = $m[$key];
                }

                $itsegment['DepDate'] = strtotime($this->normalizeDate($m['Date']));
                $itsegment['ArrDate'] = MISSING_DATE;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $its[] = $it;

        return $its;
    }

    private function parseHotels()
    {
        $its = [];

        $itineraryNumber = $this->http->FindSingleNode('//text()[' . $this->contains('Itinerary number:') . ']', null, true, '/^[^:]+:\s*([A-Z\d]{5,})$/');

        $hotelSegments = $this->http->XPath->query('//text()[' . $this->contains('Check in:') . ' and ' . $this->contains('Check out:') . ']');

        foreach ($hotelSegments as $hotelSegment) {
            $itHotel = $this->parseHotel($hotelSegment, $itineraryNumber);

            if ($itHotel) {
                $its[] = $itHotel;
            }
        }

        return $its;
    }

    private function parseHotel($root, $confirmationNumber)
    {
        $patterns = [
            'date' => '[^,.\d\s]{2,}\s+([^,.\d\s]{3,}\s+\d{1,2},\s+\d{2,4})', // Sat Oct 14, 2017
        ];

        $it = [];
        $it['Kind'] = 'R';

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $confirmationNumber;

        // CheckInDate
        $checkInDate = $this->http->FindSingleNode('.', $root, null, '/Check in:\s*' . $patterns['date'] . '/');

        if ($checkInDate) {
            $it['CheckInDate'] = strtotime($this->normalizeDate($checkInDate));
        }

        // CheckOutDate
        $checkOutDate = $this->http->FindSingleNode('.', $root, null, '/Check out:\s*' . $patterns['date'] . '/');

        if ($checkOutDate) {
            $it['CheckOutDate'] = strtotime($this->normalizeDate($checkOutDate));
        }

        $xpathFragment1 = './preceding::text()[normalize-space(.)][position()<5][ ./ancestor::*[name()="b" or name()="strong"] ]';

        // HotelName
        $it['HotelName'] = $this->http->FindSingleNode($xpathFragment1, $root);

        // Address
        $it['Address'] = $this->http->FindSingleNode($xpathFragment1 . '/following::text()[normalize-space(.)][1][not(' . $this->contains('Check in:') . ') and not(' . $this->contains('Check out:') . ')]', $root);

        // GuestNames
        $guest = $this->http->FindSingleNode('./preceding::text()[normalize-space(.)][ ./ancestor::*[name()="b" or name()="strong"] ][position()<4][' . $this->starts('Hotel purchase') . ']/ancestor::p[1]/following-sibling::p[normalize-space(.)][1]', $root);

        if ($guest) {
            $it['GuestNames'] = [$guest];
        }

        // Currency
        // Total
        $payment = $this->http->FindSingleNode('./following::text()[normalize-space(.)][1]/ancestor::table[1]/descendant::td[contains(.,"[") and contains(.,"]")][last()]/following-sibling::td[last()][ ./descendant::*[name()="b" or name()="strong"] ]', $root);
        // $260.65
        if (preg_match('/^([^\d]+)\s*([,.\d]+)/', $payment, $matches)) {
            $it['Currency'] = trim($matches[1]);
            $it['Total'] = $this->normalizePrice($matches[2]);
        }

        return $it;
    }

    private function assignLang()
    {
        foreach ($this->reBody2 as $lang => $phrases) {
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
        $year = date('Y', $this->date);
        $in = [
            '/^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$/', // 09:25| Tue,30-Dec-14
            '/^([^,.\d\s]{3,})\s+(\d{1,2}),\s+(\d{2,4})$/', // Oct 14, 2017
        ];
        $out = [
            '$2 $3 $4, $1',
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match('/\d{1,2}\s+([^\d\s]+)\s+\d{4}/', $str, $m)) {
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

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
}
