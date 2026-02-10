<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightCompensation extends \TAccountChecker
{
    public $mailFiles = "egencia/it-6016804.eml, egencia/it-9361763.eml, egencia/it-9296482.eml, egencia/it-9671760.eml, egencia/it-9301274.eml, egencia/it-9286943.eml, egencia/it-9312925.eml, egencia/it-9349797.eml";

    protected $providerCode = '';

    protected $lang = '';

    protected $langDetectors = [
        'nl' => ['Reserveringsnummer:', 'Reserveringsnummer :'],
        'fr' => ['Numéro de réservation:', 'Numéro de réservation :'],
        'da' => ['Bookingnummer:', 'Bookingnummer :'],
        'sv' => ['Bokningsreferens:', 'Bokningsreferens :'],
        'no' => ['Bookingreferanse:', 'Bookingreferanse :'],
        'en' => ['Booking Reference:', 'Booking Reference :'],
    ];

    protected static $dict = [
        'nl' => [
            'Dear' => 'Hallo',
            //			'Cancelled flight' => '',
            'Flight duration'    => 'Gemiste aansluitende vlucht',
            'operated by'        => 'verzorgd door',
            'Booking Reference:' => ['Reserveringsnummer:', 'Reserveringsnummer :'],
        ],
        'fr' => [
            'Dear'               => 'Bonjour',
            'Cancelled flight'   => 'Vol annulé',
            'Flight duration'    => 'Vol retardé',
            'operated by'        => 'opéré par',
            'Booking Reference:' => ['Numéro de réservation:', 'Numéro de réservation :'],
        ],
        'da' => [
            'Dear' => 'Kære',
            //			'Cancelled flight' => '',
            'Flight duration'    => 'Forsinket fly',
            'operated by'        => 'Betjent af',
            'Booking Reference:' => ['Bookingnummer:', 'Bookingnummer :'],
        ],
        'sv' => [
            'Dear'               => 'Hej',
            'Cancelled flight'   => 'Inställt flyg',
            'Flight duration'    => 'Försenat flyg',
            'operated by'        => 'drivs av',
            'Booking Reference:' => ['Bokningsreferens:', 'Bokningsreferens :'],
        ],
        'no' => [
            'Dear'             => 'Hei',
            'Cancelled flight' => 'Innstilt flyvning',
            //			'Flight duration' => '',
            'operated by'        => 'drevet av',
            'Booking Reference:' => ['Bookingreferanse:', 'Bookingreferanse :'],
        ],
        'en' => [
            'Booking Reference:' => ['Booking Reference:', 'Booking Reference :'],
        ],
    ];

    // Standard Methods
    public static function getEmailProviders()
    {
        return ['egencia', 'cheapnl'];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@egencia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['from'], 'compensation@egencia.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider() === false) {
            return false;
        }

        // Detecting Language
        return $this->assignLang();
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider() === false) {
            return false;
        }

        // Detecting Language
        if ($this->assignLang() === false) {
            return false;
        }

        $it = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => [$it],
            ],
            'emailType'    => 'FlightCompensation_' . $this->lang,
            'providerCode' => $this->providerCode,
        ];
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function parseEmail()
    {
        $patterns = [
            'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?',
            'code' => '[A-Z]{3}',
        ];

        $it = [];
        $it['Kind'] = 'T';

        // Passengers
        $passenger = $this->http->FindSingleNode('//*[(name()="h1" or name()="h2" or name()="h3") and starts-with(normalize-space(.),"' . $this->t('Dear') . '")]', null, true, '/^' . $this->t('Dear') . '\s+(\w{2,}[\w\s]*)/u');

        if ($passenger) {
            $it['Passengers'] = [$passenger];
        }

        // TripSegments
        $it['TripSegments'] = [];
        $seg = [];

        $segments = $this->http->XPath->query('//tr[ ./td[1]/descendant::text()[string-length(normalize-space(.))=3] and ./td[2]/descendant::img and ./td[3]/descendant::text()[string-length(normalize-space(.))=3] ]');

        if ($segments->length === 0) {
            return false;
        }

        $root = $segments->item(0);

        $xpathFragment1 = './ancestor::tr/preceding-sibling::tr/descendant::td[contains(normalize-space(.),"' . $this->t('operated by') . '") and not(.//td)]';

        // Cancelled
        // Duration
        $flightInfo = $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[normalize-space(.)][1]', $root);

        if (strpos($flightInfo, $this->t('Cancelled flight')) !== false) {
            $it['Cancelled'] = true;
        } elseif (preg_match('/' . $this->t('Flight duration') . '\s*([\d hm]{2,7})$/', $flightInfo, $matches)) { // Vol retardé 4h32m
            $seg['Duration'] = $matches[1];
        }

        $date = $dateStr = $this->http->FindSingleNode($xpathFragment1, $root, true, '/(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s+\d{4})/');

        if ($dateNew = $this->normalizeDate($date)) {
            $date = $dateNew;
        }

        $seg['AirlineName'] = $this->http->FindSingleNode($xpathFragment1 . "//text()[contains(.,'$dateStr')]", $root, true, '/' . $dateStr . '\s*(.+)/');

        // Operator
        $operator = $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[contains(normalize-space(.),"' . $this->t('operated by') . '")][1]', $root, true, '/' . $this->t('operated by') . '\s*(.+)/');

        if ($operator) {
            $seg['Operator'] = $operator;
        }

        $timeDep = $this->http->FindSingleNode('./td[1]/descendant::text()[contains(.,":")][1]', $root, true, '/^(' . $patterns['time'] . ')$/');

        // DepDate
        if ($date && $timeDep) {
            $seg['DepDate'] = strtotime($date . ', ' . $timeDep);
        }

        // DepCode
        $seg['DepCode'] = $this->http->FindSingleNode('./td[1]/descendant::text()[string-length(normalize-space(.))=3][1]', $root, true, '/^(' . $patterns['code'] . ')$/');

        $timeArr = $this->http->FindSingleNode('./td[3]/descendant::text()[contains(.,":")][1]', $root, true, '/^(' . $patterns['time'] . ')$/');

        // ArrDate
        if ($date && $timeArr) {
            $seg['ArrDate'] = strtotime($date . ', ' . $timeArr);
        }

        // ArrCode
        $seg['ArrCode'] = $this->http->FindSingleNode('./td[3]/descendant::text()[string-length(normalize-space(.))=3][1]', $root, true, '/^(' . $patterns['code'] . ')$/');

        if ($seg['DepDate'] && $seg['DepCode'] && $seg['ArrDate'] && $seg['ArrCode']) {
            $seg['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
        }

        $it['TripSegments'][] = $seg;

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('//td[' . $this->starts($this->t('Booking Reference:')) . ']', null, true, '/:\s*([A-Z\d]{5,})$/');

        return $it;
    }

    protected function normalizeDate($string = '')
    {
        if (preg_match('/^(\d{1,2})\s+([^,.\d\s]{3,})\s*,\s+(\d{4})$/', $string, $matches)) { // 3 April, 2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if ($day && $month && $year) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return false;
    }

    protected function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    protected function assignProvider()
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"that Egencia") or contains(normalize-space(.),"Egencia LLC. All rights reserved") or contains(.,"compensation@egencia.com")]')->length > 0;
        $condition2 = $this->http->XPath->query('//img[contains(@src,"/partners/logos/ect_") and contains(@src,"/Egencia-logo.")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'egencia';

            return true;
        }

        $condition1 = $this->http->XPath->query('//node()[contains(.,"CheapTickets.") or contains(.,"compensation@cheaptickets.")]')->length > 0;
        $condition2 = $this->http->XPath->query('//img[contains(@src,"/partners/logos/cheaptickets") and contains(@src,"/cheaptickets_nl-logo.")]')->length > 0;

        if ($condition1 || $condition2) {
            $this->providerCode = 'cheapnl';

            return true;
        }

        return false;
    }

    protected function assignLang()
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
}
