<?php

namespace AwardWallet\Engine\eurobonus\Email;

use AwardWallet\Engine\MonthTranslate;

class CheckNewspaper extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-4126932.eml, eurobonus/it-5420355.eml, eurobonus/it-6805244.eml, eurobonus/it-9654605.eml";

    public $reFrom = 'reply@sas.se';
    public $reSubject = [
        'no' => 'Na kan du sjekke inn og velge sete!',
        'sv' => 'Nu kan du checka in',
        'en' => 'SAS Check-in',
    ];

    public $lang = '';

    public $reBody = 'EuroBonus';
    public $langDetectors = [
        'no' => ['Avreise:'],
        'sv' => ['Avgång:'],
        'en' => ['Departure:'],
    ];

    public static $dictionary = [
        'no' => [
            'Booking reference'       => 'Referansenummer',
            'Departure:'              => 'Avreise:',
            'Arrival:'                => 'Ankomst:',
            'Passenger(s)'            => 'Passasjer(er)',
            'Frequent Flyer Program:' => 'Bonusprogram:',
        ],
        'sv' => [
            'Booking reference'       => 'Bokningsreferens',
            'Departure:'              => 'Avgång:',
            'Arrival:'                => 'Ankomst:',
            'Passenger(s)'            => 'Passagerare',
            'Frequent Flyer Program:' => 'Bonusprogram:',
        ],
        'en' => [],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t('Booking reference'));

        $xpath = '//text()[' . $this->contains($this->t('Departure:')) . ']/ancestor::tr[1]';
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[1]/descendant::text()[normalize-space(.)][2]", $root)));

            $itsegment = [];

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./following::text()[' . $this->contains($this->t('Arrival:')) . '][1]/ancestor::tr[1]/td[string-length(normalize-space(.))>1][4]', $root);

            if (preg_match('/^([A-Z\d]{2})\s+(\d+)$/', $flight, $matches)) {
                $itsegment['AirlineName'] = $matches[1];
                $itsegment['FlightNumber'] = $matches[2];
            }

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][3]", $root);

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./following::text()[" . $this->contains($this->t('Arrival:')) . "][1]/ancestor::tr[1]/td[string-length(normalize-space(.))>1][3]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[string-length(normalize-space(.))>1][2]", $root), $date);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./following::text()[" . $this->contains($this->t('Arrival:')) . "][1]/ancestor::tr[1]/td[string-length(normalize-space(.))>1][2]", $root), $date);

            // DepCode
            // ArrCode
            if ($itsegment['DepName'] && $itsegment['ArrName']) {
                $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $xpathFragment1 = '//text()[' . $this->eq($this->t('Passenger(s)')) . ']/ancestor::tr/following-sibling::tr[' . $this->starts($this->t('E-Ticket:')) . ']';

        // Passengers
        $passengers = $this->http->FindNodes($xpathFragment1 . '/preceding-sibling::tr[normalize-space(.)][1]/td/descendant::text()[string-length(normalize-space(.))>1][1][ ./ancestor::*[name()="b" or name()="strong"] ]');

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_unique($passengers);
        }

        // TicketNumbers - need example!

        // AccountNumbers
        $accountNumbers = $this->http->FindNodes($xpathFragment1 . '/following-sibling::tr[normalize-space(.)][1]/td[' . $this->starts($this->t('Frequent Flyer Program:')) . ']', null, '/:\s*([A-Z\d]{5,})/');
        $accountNumberValues = array_values(array_filter($accountNumbers));

        if (!empty($accountNumberValues[0])) {
            $it['AccountNumbers'] = array_unique($accountNumberValues);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
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

        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;

        if ($this->assignLang() === false) {
            return false;
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $name = explode('\\', __CLASS__);

        $result = [
            'emailType'  => end($name) . ucfirst($this->lang),
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)(\d{2})$#", // 23JUL16
        ];
        $out = [
            "$1 $2 20$3",
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
            '?'=> 'GBP',
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
