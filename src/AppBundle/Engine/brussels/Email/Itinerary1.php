<?php

namespace AwardWallet\Engine\brussels\Email;

// it-1.eml, it-4092155.eml, it-1729835.eml

class Itinerary1 extends \TAccountChecker
{
    use \PriceTools;

    public const DATE_FORMAT = 'j F Y H:i';
    public const DATE_FORMAT_1 = '%e %B %Y %H:%M';
    public $mailFiles = "brussels/it-1.eml, brussels/it-1729835.eml, brussels/it-4092155.eml, brussels/it-5021239.eml";

    public $monthNames = [
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
        'fr' => ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'novembre', 'décembre'],
        'nl' => ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december'],
    ];
    public $reBody = [
        'fr' => ['Détails de la réservation'],
        'nl' => ['Details van je reservatie'],
        'en' => ['Your itinerary'],
    ];
    public $reLang = [
        'fr' => ['Numéro de réservation'],
        'en' => ['Booking reference'],
        'nl' => ['Reservatienummer'],
    ];
    public $reSubject = [
        'Booking Confirmation',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Booking Reference' => 'Booking\s+reference',
            'Departure'         => 'Departure:',
            'Arrival'           => 'Arrival:',
        ],
        'fr' => [
            'Booking Reference' => 'Numéro\s*de\s*réservation',
            'E-Ticket Numbers'  => 'Numéros E-ticket',
            'Base fare'         => 'Tarif de base',
            'Airport taxes'     => 'Taxes aéroport',
            'Airline fees'      => 'Frais compagnie aérienne',
            'Departure'         => 'Heure de départ',
            'Flight'            => 'Vol',
            'Arrival'           => 'Heure d\'arrivée',
            'Cabin'             => 'Cabine',
            'Booking class'     => 'Classe de réservation',
        ],
        'nl' => [
            'Booking Reference' => 'Reservatienummer',
            'E-Ticket Numbers'  => 'E-Ticket nummers',
            'TOTAL'             => 'TOTAAL',
            'Base fare'         => 'Basistarief',
            'Airport taxes'     => 'Luchthaventaksen',
            'Airline fees'      => 'Airline toeslagen',
            'Departure'         => 'Vertrektijd',
            'Flight'            => 'Vluchtnummer',
            'Arrival'           => 'Aankomsttijd',
            'Cabin'             => 'Cabine',
            'Booking class'     => 'Reservatieklasse',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = text($parser->getHTMLBody());
        $this->AssignLang($body);

        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindPreg('#' . $this->t('Booking Reference') . '\s*([^\<]*)#');
        $xpath = '//tr[not(.//tr) and starts-with(normalize-space(.),"' . $this->t('E-Ticket Numbers') . '")]/following-sibling::tr';
        $etickets = $this->http->XPath->query($xpath);

        $passengers = [];

        foreach ($etickets as $ticket) {
            $passengers[] = $this->http->FindSingleNode(".//td[1]", $ticket);
        }
        $itineraries['Passengers'] = join(', ', $passengers);

        $total = $this->http->FindSingleNode('//td[not(.//td) and starts-with(normalize-space(.),"' . $this->t('TOTAL') . '")]/following-sibling::td[1]');
        $itineraries['TotalCharge'] = $this->cost($total);
        $itineraries['Currency'] = $this->currency($total);

        $baseFare = preg_split('/ /', $this->http->FindSingleNode('//tr[not(.//tr) and starts-with(normalize-space(.),"' . $this->t('Base fare') . '")]/*[2]'));
        $itineraries['BaseFare'] = $this->floatval($baseFare[0]);

        $xpath = '//tr[not(.//tr) and starts-with(normalize-space(.),"' . $this->t('Airport taxes') . '")]/*[2]';
        $airportTax = $this->cost($this->http->FindSingleNode($xpath));
        $xpath = '(//tr[not(.//tr) and starts-with(normalize-space(.),"' . $this->t('Airline fees') . '")])[1]/*[2]';
        $airportFrais = $this->cost($this->http->FindSingleNode($xpath));
        $itineraries['Tax'] = number_format($airportFrais + $airportTax, 2, '.', '');

        $segments = [];

        $tripRows = $this->http->XPath->query('//text()[contains(.,"' . $this->t('Departure') . '")]/ancestor::table[1]');

        foreach ($tripRows as $row) {
            $tripSegment = [];

            $dateAndCities = $this->http->FindSingleNode('(.//tr)[1]', $row);
            $date = '';
            $matches = [];

            if (preg_match('/(.*)\s+-\s+(.*)\s+-\s+(.*)/', $dateAndCities, $matches)) {
                $tripSegment['DepName'] = $matches[2];
                $tripSegment['ArrName'] = $matches[3];
                $date = $matches[1];

                if (preg_match('/\w+,\s+(\w+\s+\d+,\s+\d+)/', $date, $m)) {
                    // English format
                    $date = $m[1];
                } elseif (preg_match('/\w+\s+(\d+)\s+(\w+)\s+(\d+)/u', $date, $m)) {
                    // French format, Dutch format
                    $date = $m[1] . ' ' . $this->getMonth($m[2]) . ' ' . $m[3];
                }
            }

            $flight = $this->http->FindSingleNode('.//text()[normalize-space(.)="' . $this->t('Flight') . '"]/following::text()[normalize-space(.)!=""][1]', $row);

            if (preg_match('/^([A-Z\d]{2})(\d+)$/', $flight, $matches)) {
                $tripSegment['AirlineName'] = $matches[1];
                $tripSegment['FlightNumber'] = $matches[2];
            }

            $depart = $this->http->FindSingleNode('.//text()[normalize-space(.)="' . $this->t('Departure') . '"]/following::text()[normalize-space(.)!=""][1]', $row);
            $tripSegment['DepDate'] = strtotime($date . ', ' . $depart);

            $arrive = $this->http->FindSingleNode('.//text()[normalize-space(.)="' . $this->t('Arrival') . '"]/following::text()[normalize-space(.)!=""][1]', $row);
            $tripSegment['ArrDate'] = strtotime($date . ', ' . $arrive);

            $tripSegment['Cabin'] = $this->http->FindSingleNode('.//tr[not(.//tr) and starts-with(normalize-space(.),"' . $this->t('Cabin') . ' (")]//a[1]', $row);
            $tripSegment['BookingClass'] = $this->http->FindSingleNode('.//tr[not(.//tr) and starts-with(normalize-space(.),"' . $this->t('Booking class') . '")]', $row, true, '/\s*(\w+)$/');
            $tripSegment['DepCode'] = $tripSegment['ArrCode'] = TRIP_CODE_UNKNOWN;
            $segments[] = $tripSegment;
        }

        $itineraries['TripSegments'] = $segments;

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && in_array($headers['from'], ['webbookings@brusselsairlines.com', 'noreply@brusselsairlines.com'])) {
            if (isset($this->reSubject) && isset($headers["subject"])) {
                foreach ($this->reSubject as $reSubject) {
                    if (stripos($headers["subject"], $reSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
//        return $this->http->FindPreg('/(?:Your\s+itinerary|Détails\s+de\s+la\s+réservation).*?Brussels\s+Airlines/s');
        $body = $parser->getHTMLBody();
        $text = html_entity_decode($body);
        $text = substr($text, stripos($text, "©"));

        if (preg_match('/©\s+Brussels Airlines/ui', $text)) {
            $body = text($body);

            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]brusselsairlines\.com$/ims', $from);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function floatval($float)
    {
        return floatval(preg_replace('/,/', '.', $float));
    }

    protected function buildDate($parsedDate)
    {
        return mktime($parsedDate['hour'], $parsedDate['minute'], 0, $parsedDate['month'], $parsedDate['day'], $parsedDate['year']);
    }

    protected function getMonth($node)
    {
        $month = $this->monthNames['en'];
        $monthLang = $this->monthNames[$this->lang];
        $done = false;
        $res = $node;

        for ($i = 0; $i < 12; $i++) {
            if (mb_strtolower($monthLang[$i]) == mb_strtolower(trim($node))) {
                $res = $month[$i];
                $done = true;

                break;
            }
        }

        if (!$done && isset($this->monthNames[$this->lang . '2'])) {
            $monthLang = $this->monthNames[$this->lang . '2'];

            for ($i = 0; $i < 12; $i++) {
                if (mb_strtolower($monthLang[$i]) == mb_strtolower(trim($node))) {
                    $res = $month[$i];
                    $done = true;

                    break;
                }
            }
        }

        return $res;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        if (isset($this->reLang)) {
            foreach ($this->reLang as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break;
                }
            }
        }

        return true;
    }
}
