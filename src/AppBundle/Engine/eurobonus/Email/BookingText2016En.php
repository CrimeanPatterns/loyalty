<?php

namespace AwardWallet\Engine\eurobonus\Email;

class BookingText2016En extends \TAccountChecker
{
    public $mailFiles = "eurobonus/it-4827427.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method', LOG_LEVEL_ERROR);

            return;
        }

        $this->result['Kind'] = 'T';

        $this->parseLocator($this->findСutSection($parser->getHTMLBody(), 'find your travel details', 'Save the booking reference'));
        $this->parsePassengers($this->findСutSection($parser->getHTMLBody(), 'control and gate', 'Scandinavian Airlines'));
        $this->parseSegments($this->findСutSection($parser->getHTMLBody(), 'Service machine at the airport.', 'Passengers'));

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function increaseDate($dateSegment, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, strtotime($dateSegment));
        $arrDate = strtotime($arrTime, $depDate);

        while ($depDate > $arrDate) {
            $arrDate = strtotime('+1 day', $arrDate);
        }

        return [
            'DepDate' => $depDate,
            'ArrDate' => $arrDate,
        ];
    }

    /**
     * Quick change preg_match. 1000 iterations takes 0.0008 seconds,
     * while similar <i>preg_match('/LEFT\n(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * </p>
     * <pre>
     * findСutSection($plainText, 'LEFT', 'RIGHT')
     * findСutSection($plainText, 'LEFT', ['RIGHT', PHP_EOL])
     * </pre>.
     */
    public function findСutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && (
                stripos($headers['from'], 'no-reply@flysas.com') !== false
                )
                && isset($headers['subject']) && (
                preg_match('/Your SAS flight.+?, booking:\s*\[/', $headers['subject'])
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Save the booking reference.') !== false
            && stripos($parser->getHTMLBody(), 'This booking was created:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@flysas.com') !== false;
    }

    protected function parseSegments($text)
    {
        foreach (preg_split('/\n{3,}/', $text, -1, PREG_SPLIT_NO_EMPTY) as $text) {
            $segment = $this->parseSegment($text);

            if (!empty($segment)) {
                $this->result['TripSegments'][] = $segment;
            }
        }
    }

    protected function parseSegment($text)
    {
        $segment = [];

        $regular = '(\d+ \w+ \d+)\s+(\d+:\d+)\s+-\s+(\d+:\d+)\s+';
        $regular .= '(.+?)\s+(?:\(Terminal\s+(\w+)\))?\s+-\s+(.+?)\s+(?:\(Terminal\s+(\w+)\))?\s+';
        $regular .= '([A-Z\d]{2})?\s*(\d+)\s+Operated by:\s+(.+?)\s+\|\s+Aircraft:\s+(.+?)\s+Baggage';

        if (preg_match("/{$regular}/u", $text, $matches)) {
            $segment += $this->increaseDate($matches[1], $matches[2], $matches[3]);
            $segment['DepName'] = $matches[4];
            $segment['DepartureTerminal'] = $matches[5];
            $segment['ArrName'] = $matches[6];
            $segment['ArrivalTerminal'] = $matches[7];
            $segment['AirlineName'] = $matches[8];
            $segment['FlightNumber'] = $matches[9];
            $segment['Operator'] = $matches[10];
            $segment['Aircraft'] = $matches[10];
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        return $segment;
    }

    protected function parsePassengers($text)
    {
        if (preg_match_all('/\s+(.+?)\s+Adult/', $text, $matches, PREG_SET_ORDER)) {
            array_shift($matches[0]);
            $this->result['Passengers'] = $matches[0];
        }

        if (preg_match_all('/Ticket number\s+(.+)/', $text, $matches, PREG_SET_ORDER)) {
            array_shift($matches[0]);
            $this->result['TicketNumbers'] = $matches[0];
        }

        if (preg_match('/This booking was created:\s+(.+)\s+Ticket/', $text, $matches)) {
            $this->result['ReservationDate'] = strtotime($matches[1]);
        }
    }

    protected function parseLocator($text)
    {
        if (preg_match('/Booking reference:\s+(\w+)/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }
    }
}
