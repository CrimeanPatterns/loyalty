<?php

namespace AwardWallet\Engine\ctraveller\Email;

class ElectronicTicketDetails extends \TAccountChecker
{
    public $mailFiles = "ctraveller/it-294743167.eml"; // +1 bcdtravel(html)[en]

    private $totalReservDate = 0;
    private $totalStatus = '';

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@corporatetraveller.co.in') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/[^@\s]{3,}@corporatetraveller\.co\.in/i', $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(.,"ct.fcmonline.in") or contains(.,"@corporatetraveller.co.in")] | //a[contains(@href,"//ct.fcmonline.in")]')->length === 0) {
            return false;
        }

        $condition1 = $this->http->XPath->query('//tr[normalize-space(.)="Electronic Ticket Details"]')->length > 0;
        $condition2 = $this->http->XPath->query('//tr/td[normalize-space(.)][1][normalize-space(.)="Arrival"]')->length > 0;

        if ($condition1 && $condition2) {
            return true;
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        return $this->parseEmail();
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    // функция возвращает ключ из $array в котором был найден $recordLocator, иначе FALSE
    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }

    protected function parseEmail()
    {
        $generationTime = $this->http->FindSingleNode('//tr/td[normalize-space(.)][1][normalize-space(.)="Generation Time"]/following-sibling::td[string-length(normalize-space(.))>9]');
        $this->totalReservDate = strtotime($generationTime);

        $this->totalStatus = $this->http->FindSingleNode('//tr/td[normalize-space(.)][1][normalize-space(.)="Booking Status"]/following-sibling::td[normalize-space(.)][last()]');

        $its = [];

        $ticketSegments = $this->http->XPath->query('//tr[(contains(.," - by Air")) and not(.//tr)]');

        foreach ($ticketSegments as $ticketSegment) {
            if (preg_match('/\s+-\s+by\s+Air\s*$/i', $ticketSegment->nodeValue)) {
                $itFlight = $this->parseFlight($ticketSegment);

                if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $its)) !== false) {
                    $its[$key]['TripSegments'] = array_merge($its[$key]['TripSegments'], $itFlight['TripSegments']);
                    $its[$key]['Passengers'] = array_merge($its[$key]['Passengers'], $itFlight['Passengers']);
                    $its[$key]['Passengers'] = array_unique($its[$key]['Passengers']);
                    $its[$key]['TicketNumbers'] = array_merge($its[$key]['TicketNumbers'], $itFlight['TicketNumbers']);
                    $its[$key]['TicketNumbers'] = array_unique($its[$key]['TicketNumbers']);
                } else {
                    $its[] = $itFlight;
                }
            }
        }

        $result = [
            'parsedData' => [
                'Itineraries' => $its,
            ],
            'emailType' => 'ElectronicTicketDetails',
        ];

        $payment = $this->http->FindSingleNode('//tr/td[normalize-space(.)][1][normalize-space(.)="Total Price"]/following-sibling::td[normalize-space(.)][last()]');

        if (preg_match('/^([,.\d\s]+)\s+([A-Z]{3})/', $payment, $matches)) {
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[1]);
            $result['parsedData']['TotalCharge']['Currency'] = $matches[2];
        }

        return $result;
    }

    protected function parseFlight($root)
    {
        $it = [];
        $it['Kind'] = 'T';
        $it['ReservationDate'] = $this->totalReservDate;
        $it['Status'] = $this->totalStatus;
        $it['TripSegments'] = [];
        $seg = [];

        $patterns = [
            'route' => '/(\d{1,2}:\d{2})\s*,\s*[^,\d\s]{2,}\s+(\d{1,2}-[^,\d\s]{3,})\s+: (.+?) \(([^)]+)\s+-\s+([A-Z]{3})\)(?:\s+: ([^:]+))?/',
        ];

        $header = $this->http->FindSingleNode('.', $root);

        if (preg_match('/^\d{1,2}\/\d{1,2}\/(\d{4})/', $header, $matches)) {
            $year = $matches[1];
        }

        $flight = $this->http->FindSingleNode('./following-sibling::tr/td[normalize-space(.)][1][normalize-space(.)="Flight"]/following-sibling::td[normalize-space(.)][last()]', $root);

        if (preg_match('/([A-Z\d]{2})[-\s]+(\d+)$/', $flight, $matches)) {
            $seg['AirlineName'] = $matches[1];
            $seg['FlightNumber'] = $matches[2];
        }

        $departure = $this->http->FindSingleNode('./following-sibling::tr/td[normalize-space(.)][1][normalize-space(.)="Departure"]/following-sibling::td[normalize-space(.)][last()]', $root);

        if (preg_match($patterns['route'], $departure, $matches)) {
            if ($year) {
                $seg['DepDate'] = strtotime($matches[2] . '-' . $year . ', ' . $matches[1]);
            }
            $seg['DepName'] = $matches[4] . ' (' . $matches[3] . ')';
            $seg['DepCode'] = $matches[5];

            if (!empty($matches[6])) {
                $seg['DepartureTerminal'] = $matches[6];
            }
        }

        $arrival = $this->http->FindSingleNode('./following-sibling::tr/td[normalize-space(.)][1][normalize-space(.)="Arrival"]/following-sibling::td[normalize-space(.)][last()]', $root);

        if (preg_match($patterns['route'], $arrival, $matches)) {
            if ($year) {
                $seg['ArrDate'] = strtotime($matches[2] . '-' . $year . ', ' . $matches[1]);
            }
            $seg['ArrName'] = $matches[4] . ' (' . $matches[3] . ')';
            $seg['ArrCode'] = $matches[5];

            if (!empty($matches[6])) {
                $seg['ArrivalTerminal'] = $matches[6];
            }
        }

        $seg['Cabin'] = $this->http->FindSingleNode('./following-sibling::tr/td[normalize-space(.)][1][normalize-space(.)="Class"]/following-sibling::td[normalize-space(.)][last()]', $root);

        $xpathFragment = './following-sibling::tr/descendant::td[normalize-space(.)="Airline PNR"]/ancestor::tr[1]';
        $passenger = $this->http->FindSingleNode($xpathFragment . '/following-sibling::tr[1]/td[1]', $root, true, '/^(.+?)(?:\s*\(Adt\))?$/i');
        $airlinePNR = $this->http->FindSingleNode($xpathFragment . '/following-sibling::tr[1]/td[3]', $root, true, '/^([A-Z\d]{5,})$/');
        $ticketNo = $this->http->FindSingleNode($xpathFragment . '/following-sibling::tr[1]/td[5]', $root, true, '/^([-\d\s]+)$/');
        $seg['Meal'] = $this->http->FindSingleNode($xpathFragment . '/following-sibling::tr[2]', $root, true, '/^Meal\s*:\s*(.+?)(?:,|\s+Tour\s+Code)/');

        $it['TripSegments'][] = $seg;

        $it['Passengers'] = [$passenger];
        $it['TicketNumbers'] = [$ticketNo];
        $it['RecordLocator'] = $airlinePNR;

        return $it;
    }
}
