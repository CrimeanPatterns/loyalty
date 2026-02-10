<?php

namespace AwardWallet\Engine\amadeus\Email;

class CorsairHtml2016Fr extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-4617743.eml";

    private $result = [];

    public function increaseDate($dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, $dateSegment);
        $arrDate = strtotime($arrTime, $dateSegment);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->result['Kind'] = 'T';
        $this->result['RecordLocator'] = $this->http->FindSingleNode('//*[contains(text(), "Booking Reference")]/ancestor::td[1]/following-sibling::td[1]', null, false, '/[A-Z\d]{5,6}/');
        $this->result['Passengers'] = $this->http->FindSingleNode('//*[contains(text(), "Passenger name")]/ancestor::td[1]/following-sibling::td[1]');
        $this->result['TicketNumbers'] = $this->http->FindSingleNode('//*[contains(text(), "Ticket number")]/ancestor::td[1]/following-sibling::td[1]');
        $this->result['Tax'] = cost($this->http->FindSingleNode('//*[contains(text(), "Surcharges and taxes")]/ancestor::td[1]/following-sibling::td[1]'));
        $this->result += total($this->http->FindSingleNode('//*[contains(text(), "Grand Total")]/ancestor::td[1]/following-sibling::td[1]'));
        $this->parseSegments();

        return [
            'emailType'  => 'Сorsair Booking format HTML from 2016 in "fr"',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
        isset($headers['from']) && stripos($headers['from'], 'eticket@amadeus.com') !== false
                // GOMEZ SANZBERRO/AITOR ADT 20NOV PAR PTP
                && preg_match('/\w+.*?\s+\d+\w+\s+[A-Z]{3}\s+[A-Z]{3}/', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'votre réservation a bien été prise en compte') !== false
            || strpos($parser->getHTMLBody(), 'Numéro de Réservation') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['fr'];
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function parseSegments()
    {
        foreach ($this->http->XPath->query('//*[contains(text(), "Opéré par")]/ancestor::tr[1]') as $current) {
            $this->result['TripSegments'][] = $this->parseSegment(
                    join("\n", $this->http->FindNodes('preceding-sibling::tr[position() <= 3]', $current)),
                    $current->nodeValue, join("\n", $this->http->FindNodes('following-sibling::tr[position() < count(following-sibling::tr//hr[contains(@color, "#C0C0C0")])-2]', $current))
            );
        }
    }

    private function parseSegment($header, $center, $bottom)
    {
        $segment = [];

        if (preg_match('/(.*?)\s+-\s+(.*)\s+(\d+\s*\w+\s*\d+)/', $header, $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['ArrName'] = $matches[2];
            $date = strtotime($matches[3]);
        }

        if (preg_match('/Vol Flight\s*:\s*([A-Z\d]{2})\s*(\d+).*?Operated by\s*:\s*(.*)/s', $center, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
            $segment['Operator'] = trim($matches[3]);
        }

        if (isset($date) && preg_match('/Departure\s+(\d+:\d+).*?Arrival\s+(\d+:\d+).*?Booking class.*?([A-Z])/s', $bottom, $matches)) {
            $segment += $this->increaseDate($date, $matches[1], $matches[2]);
            $segment['BookingClass'] = $matches[3];
        }

        $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        return $segment;
    }
}
