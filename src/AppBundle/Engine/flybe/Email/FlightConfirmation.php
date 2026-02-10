<?php

namespace AwardWallet\Engine\flybe\Email;

class FlightConfirmation extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "flybe/it-1.eml, flybe/it-10391167.eml, flybe/it-1964905.eml, flybe/it-2788338.eml, flybe/it-2890741.eml, flybe/it-4552297.eml, flybe/it-4628920.eml, flybe/it-4735888.eml, flybe/it-5872335.eml, flybe/it-5951224.eml, flybe/it-8309788.eml, flybe/it-8380871.eml, flybe/it-8562243.eml";

    public static $dictionary = [
        "en"=> [],
        "es"=> [
            "ooking reference:"  => ["de su reserva:", "ooking reference:"],
            "to"                 => "a",
            "Operated by"        => "Operado por",
            "Flight From To Seat"=> "Vuelo Desde Con",
            "TAXES AND CHARGES"  => "LOS IMPUESTOS Y TASAS",
        ],
        "fr"=> [
            "ooking reference:"  => ["de réservation:", "ooking reference:"],
            "to"                 => "à destination de",
            "Operated by"        => "Exploité par",
            "Flight From To Seat"=> "Vol Au départ de A destination de",
            "TAXES AND CHARGES"  => "TAXES ET FRAIS INCLUS",
        ],
        "fi"=> [
            "ooking reference:"  => "entosi varausnumero:",
            "to"                 => "kohteeseen",
            "Operated by"        => ["Lentoyhtiö", "Lentoyhti�"],
            "Flight From To Seat"=> ["Lento Lähtö Kohde", "Lento L�ht� Kohde Istuinpaikka"],
            "TAXES AND CHARGES"  => "VEROT JA MAKSUT MUKAAN LUKIEN",
        ],
        "de"=> [
            "ooking reference:"  => ["ooking reference:", "Buchungsbest�tigung:"],
            "to"                 => "nach",
            "Operated by"        => "Bedient durch",
            "Flight From To Seat"=> "Flug Von Nach Sitzplatz",
            "TAXES AND CHARGES"  => ["STEUERN UND GEBÜHREN", "STEUERN UND GEB�HREN:"],
        ],
    ];

    protected $lang = null;

    protected $langDetectors = [
        'en' => [
            'Arrive',
        ],
        'es' => [
            'Llegada',
        ],
        'fr' => [
            'Arrivée',
        ],
        'fi' => [
            'Saapuu',
        ],
        'de' => [
            'Ankunft',
        ],
    ];

    // Standard Methods

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'DO_NOT_REPLY@bookings.flybe.com') !== false
            || preg_match('/(Confirmation.+Flybe|Confirmación.+Flybe|Bestätigung.+Flybe|Flybe -lentosi vahvistus)/iu', $headers['subject']);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bookings.flybe.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"flybe.com")]')->length === 0 && $this->http->XPath->query('//a[contains(@href,".flybe.com/")]')->length === 0) {
            return false;
        }

        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if ($this->http->XPath->query('//node()[contains(.,"' . $line . '")]')->length > 0) {
                    $this->lang = $lang;
                }
            }
        }
        $it = $this->ParseEmail();
        $class = explode('\\', __CLASS__);

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType' => end($class) . ucfirst($this->lang),
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    protected function ParseEmail()
    {
        $it = [];
        $it['Kind'] = 'T';

        if (!$it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $this->contains($this->t('ooking reference:')) . '])[1]', null, true, "#:\s*(.+)#")) {
            $it['RecordLocator'] = $this->http->FindSingleNode('(//text()[' . $this->contains($this->t('ooking reference:')) . '])[1]/following::text()[normalize-space(.)][1]');
        }
        $it['TripSegments'] = [];
        $segments = $this->http->XPath->query('//tr[(' . $this->contains($this->t('Operated by')) . ') and not(.//tr)]/preceding-sibling::tr[contains(.,":") and not(.//tr)][1]');

        foreach ($segments as $segment) {
            $seg = [];
            $date = $this->http->FindSingleNode('./td[1]', $segment, true, '/(\d{1,2}\s+[^\d]+\s+\d{2,4})\s*$/');
            $flight = $this->http->FindSingleNode('./td[2]', $segment);

            if (preg_match('/([A-Z\d]{2})(\d+)/', $flight, $matches)) {
                $seg['AirlineName'] = $matches[1];
                $seg['FlightNumber'] = $matches[2];
            }
            $route = $this->http->FindSingleNode('./td[3]', $segment);

            if (preg_match('/(.+)\s+' . $this->t('to') . '\s+(.+)/u', $route, $matches)) {
                $seg['DepName'] = $matches[1];
                $seg['ArrName'] = $matches[2];
            }
            $timeDep = $this->http->FindSingleNode('./td[4]', $segment, true, '/(\d{2}:\d{2})/');
            $timeArr = $this->http->FindSingleNode('./td[5]', $segment, true, '/(\d{2}:\d{2})/');

            if ($date && $timeDep && $timeArr) {
                $date = strtotime($this->dateStringToEnglish($date));
                $seg['DepDate'] = strtotime($timeDep, $date);
                $seg['ArrDate'] = strtotime($timeArr, $date);
            }
            $codeRows = $this->http->XPath->query('//tr[(' . $this->contains($this->t('Flight From To Seat')) . ') and not(.//tr)]/following::tr[./td[contains(.,"' . $flight . '")] and count(./td)>2 and not(.//tr)]');

            if ($codeRows->length > 0) {
                $codeRow = $codeRows->item(0);
                $seg['DepCode'] = $this->http->FindSingleNode('./td[2]', $codeRow, true, '/([A-Z]{3})/');
                $seg['ArrCode'] = $this->http->FindSingleNode('./td[3]', $codeRow, true, '/([A-Z]{3})/');
            }
            $seg['Seats'] = [];

            foreach ($codeRows as $codeRow) {
                $seg['Seats'][] = $this->http->FindSingleNode('./td[4]/descendant::text()[normalize-space(.)!=""][1]', $codeRow, true, '/^\d+\w$/');
            }
            $seg['Seats'] = array_values(array_filter($seg['Seats']));

            if (empty($seg['Seats'])) {
                unset($seg['Seats']);
            }

            $seg['Operator'] = $this->http->FindSingleNode('(./following-sibling::tr//text()[' . $this->contains($this->t('Operated by')) . '])[1]', $segment, true, '/(?:' . $this->preg_implode($this->t('Operated by')) . ')\s+(.+)/i');
            $it['TripSegments'][] = $seg;
        }
        $it['Passengers'] = $this->http->FindNodes('//tr[(' . $this->contains($this->t('Flight From To Seat')) . ') and not(.//tr)]/preceding::tr[normalize-space(.)!="" and not(.//tr)][1]//*[(name(.)="th" or name(.)="td") and normalize-space(.)!=""][1]');
        $payments = $this->http->FindSingleNode('//td[(' . $this->contains($this->t('TAXES AND CHARGES')) . ') and not(.//td)]//*[(name(.)="strong" or name(.)="b") and normalize-space(.)!=""][last()]');

        if (preg_match('/(.+\b)\s+([.\d\s]+)\s*$/', $payments, $matches)) {
            $it['Currency'] = $matches[1];
            $totalCharge = preg_replace('/(\d{1,3})\s+/', '$1', $matches[2]);
            $it['TotalCharge'] = $totalCharge;
        }

        return $it;
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field));
    }
}
