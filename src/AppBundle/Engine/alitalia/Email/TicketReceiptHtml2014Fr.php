<?php

namespace AwardWallet\Engine\alitalia\Email;

class TicketReceiptHtml2014Fr extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "alitalia/it-4151689.eml";

    protected $result = [];
    protected $dateYear = null;
    protected $pattern = [
        'recordLocator' => 'numéro de dossier:',
        // Passangers
        'firstName'     => 'Prénom:',
        'lastName'      => 'Nom',
        'ticketNumbers' => 'Numéro de billet:',
        // Price list
        'totalCharge' => 'Prix total',
        'tax'         => 'Taxes et suppléments',
        // Segments
        'code'          => '(De|A):',
        'traveledMiles' => 'Distance:',
        'duration'      => 'Durée de vol:\s*(\d+\s*h\s*\d+\s*min)',
        'aircraft'      => 'Avions:(.*?)Aéroport',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->dateYear = date('Y', strtotime($parser->getDate()));

        $this->result['Kind'] = 'T';

        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(normalize-space(text()), "' . $this->pattern['recordLocator'] . '")]', null, false, '/[A-Z\d]{5,6}$/');

        $this->parsePassangers();
        $this->parsePriceList();
        $this->parseSegments('//*[contains(@src, "millemiglia-white.png")]/ancestor::tr[1]/following-sibling::tr[./td/table or count(./td)>7]');

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'confirmation@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Reçu de billet électronique') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Nous vous remercions de votre achat. Veuillez noter votre numéro de dossier:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    protected function parsePassangers()
    {
        $firstName = $this->http->FindNodes('//*[contains(text(), "' . $this->pattern['firstName'] . '")]/following-sibling::td[1]');
        $lastName = $this->http->FindNodes('//*[contains(text(), "' . $this->pattern['lastName'] . '")]/following-sibling::td[1]');

        foreach ($firstName as $key => &$value) {
            if (isset($lastName[$key])) {
                $value .= ' ' . $lastName[$key];
            }
        }

        $this->result['Passengers'] = $firstName;
        $this->result['TicketNumbers'] = $this->http->FindNodes(
                '//*[contains(text(), "' . $this->pattern['ticketNumbers'] . '")]/following-sibling::td[1]');
    }

    protected function parsePriceList()
    {
        $this->result['Tax'] = cost($this->http->FindSingleNode('//*[contains(text(), "' . $this->pattern['tax'] . '")]/following-sibling::td[2]'));
        $this->result += total(join(' ', $this->http->FindNodes('//*[contains(text(), "' . $this->pattern['totalCharge'] . '")]/../following-sibling::td')));
    }

    protected function parseSegments($query)
    {
        $i = 0;
        $segments = [];

        foreach ($this->http->XPath->query($query) as $value) {
            if ($value->childNodes->length > 10) {
                $segments[$i][] = $this->innerArray($this->http->XPath->query('./td/text()', $value));
            }

            if ($value->childNodes->length > 3 && $value->childNodes->length < 10) {
                $segments[$i][] = $value->nodeValue;
                $i++;
            }
        }

        foreach ($segments as $value) {
            $this->result['TripSegments'][] = $this->segments($value);
        }
    }

    protected function innerArray($element)
    {
        $array = [];

        foreach ($element as $value) {
            // Link https://en.wikipedia.org/wiki/Non-breaking_space
            $value->nodeValue = str_replace(PHP_EOL, ' ', trim(trim($value->nodeValue, chr(0xC2) . chr(0xA0))));

            if (empty($value->nodeValue) !== true) {
                $array[] = $value->nodeValue;
            }
        }

        return join('|', $array);
    }

    protected function parseDate($date)
    {
        return strtotime($this->dateStringToEnglish($date . ' ' . $this->dateYear));
    }

    /**
     * @param type $array
     *
     * @return type
     */
    protected function segments($array = [])
    {
        $segment = [];

        // vendredi, 15 août
        if (preg_match_all('/, (\d+ \w+)/u', $array[0], $match)) {
            $depDate = $this->parseDate($match[1][0]);
            $arrDate = $this->parseDate($match[1][1]);
        }

        // AZ679 / Economy
        if (preg_match('/([A-Z]{2})\s*(\d{3,4})\s*\/\s*(\w+)/', $array[0], $match)) {
            $segment['FlightNumber'] = $match[2];
            $segment['AirlineName'] = $match[1];
            $segment['Cabin'] = $match[3];
        }

        // Sao Paulo, Guarulhos, Brésil - 19.20
        if (preg_match_all('/\|([\w\s,]+)\s*-\s*(\d+[.:]\d+)/ui', $array[0], $match)) {
            $segment['DepDate'] = strtotime($match[2][0], $depDate);
            $segment['ArrDate'] = strtotime($match[2][1], $arrDate);
            //$segment['DepDate_'] = date("Y-m-d H:i:s", strtotime($match[2][0], $depDate));
            //$segment['ArrDate_'] = date("Y-m-d H:i:s", strtotime($match[2][1], $arrDate));
            $segment['DepName'] = trim($match[1][0]);
            $segment['ArrName'] = trim($match[1][1]);
        }

        // De: Rome (FCO)
        // A: Sao Paulo (GRU)
        if (preg_match_all('/' . $this->pattern['code'] . '.*?\(([A-Z]{3})\)/', $array[1], $match)) {
            $segment['DepCode'] = trim($match[2][0]);

            if (isset($match[2][1])) {
                $segment['ArrCode'] = trim($match[2][1]);
            }
        }

        // Distance: 5858 mile
        if (preg_match('/' . $this->pattern['traveledMiles'] . '\s*(\d+)/', $array[1], $match)) {
            $segment['TraveledMiles'] = (int) $match[1];
        }

        // Horaire de départ: 22.05 Terminal 3
        if (preg_match_all('/\s+Terminal\s*(\d+)/', $array[1], $match)) {
            if (isset($match[1][0])) {
                $segment['DepartureTerminal'] = (int) $match[1][0];
            }

            if (isset($match[1][1])) {
                $segment['ArrivalTerminal'] = (int) $match[1][1];
            }
        }

        // Durée de vol: 12 h 0 min
        if (preg_match('/' . $this->pattern['duration'] . '/', $array[1], $match)) {
            $segment['Duration'] = trim($match[1]);
        }

        // Avions: 772
        if (preg_match('/' . $this->pattern['aircraft'] . '/s', $array[1], $match)) {
            $segment['Aircraft'] = trim($match[1]);
        }

        return $segment;
    }

    protected function startsWith($haystack, $needle)
    {
        return $needle === "" || strrpos($haystack, $needle, -strlen($haystack)) !== false;
    }
}
