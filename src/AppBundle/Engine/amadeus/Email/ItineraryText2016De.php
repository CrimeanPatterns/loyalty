<?php

namespace AwardWallet\Engine\amadeus\Email;

class ItineraryText2016De extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-4924749.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method', LOG_LEVEL_ERROR);

            return;
        }
        $text = $this->text(empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody());

        $this->parseReservations(join($this->findСutSectionAll($text, 'PASSENGER ITINERARY/RECEIPT', ['VON /NACH'])));

        $this->parsePayment(join($this->findСutSectionAll($text, 'BITTE WEISEN SIE', ['DER MITTELWERT'])));

        $this->parseSegments(join($this->findСutSectionAll($text, 'VOR   NACH', ['BITTE WEISEN SIE SICH'])));

        return [
            'emailType'  => '"YOUR ITINERARY:" format TEXT from 2013 in "en"',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'eticket@amadeus.com') !== false
                && preg_match('/\d+\s*\w+ [A-Z]{3} [A-Z]{3}/', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'PASSENGER ITINERARY/RECEIPT') !== false
                && strpos($parser->getHTMLBody(), 'ANKUNFTSZEIT:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['de'];
    }

    protected function parseReservations($text)
    {
        $this->result['Kind'] = 'T';

        if (preg_match('/BUCHUNGSCODE\s*:.*?([A-Z\d]{5,6})\n/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/DATUM:\s*(\d+ \w+ \d+)/', $text, $matches)) {
            $this->result['ReservationDate'] = strtotime($matches[1]);
        }

        if (preg_match('/REISENDER:\s*(.+?)\n/', $text, $matches)) {
            $this->result['Passengers'][] = $matches[1];
        }

        if (preg_match('/E-TICKETNUMMER\s*:\s*(.+?)\n/', $text, $matches)) {
            $this->result['TicketNumbers'][] = $matches[1];
        }
    }

    protected function parsePayment($text)
    {
        if (preg_match('/GESAMTSUMME\s*:\s*([A-Z]{3})\s*([\d.]+)/', $text, $matches)) {
            $this->result['Currency'] = $matches[1];
            $this->result['TotalCharge'] = (float) $matches[2];
        }
    }

    protected function parseSegments($text)
    {
        $segments = $this->splitter('/(.+?\s+[A-Z\d]{2}\s*\d+\s+[A-Z]\s+)/', trim($text));

        foreach ($segments as $text) {
            $segment = [];
            $regularDep = '\s*(.+?)\s+([A-Z\d]{2})\s*(\d+)\s+([A-Z])\s+(\d+\s*\w+)\s+(\d{4})';
            $regularArr = '\s*(.+?)(?:SITZPLATZ.*?)?\s+ANKUNFTSZEIT:\s*(\d{4})\s+ANKUNFTSDATUM:\s*(\d+\s*\w+)';

            if (preg_match("/{$regularDep}/", $text, $matchesDep) && preg_match("/{$regularArr}/", $text, $matchesArr)) {
                $segment['DepName'] = trim($matchesDep[1]);
                $segment['AirlineName'] = $matchesDep[2];
                $segment['FlightNumber'] = $matchesDep[3];
                $segment['BookingClass'] = $matchesDep[4];

                $segment['ArrName'] = trim($matchesArr[1]);
                $segment += $this->increaseDate($this->result['ReservationDate'], $matchesDep[5] . ', ' . $matchesDep[6], $matchesArr[3] . ', ' . $matchesArr[2]);

                $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (!empty($segment)) {
                $this->result['TripSegments'][] = $segment;
            }
        }
    }

    protected function splitter($regular, $text = false)
    {
        $result = [];
        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function text($string)
    {
        return preg_replace('/<[^>]+>/', "\n", str_replace([' ', '<br>', '<br/>', '<br />', '&nbsp;'], ' ', $string));
    }

    protected function increaseDate($dateLetter, $depTime, $arrTime)
    {
        $depDate = strtotime($depTime, $dateLetter);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }
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
     * while similar <i>preg_match('/LEFT(.*)RIGHT')</i> 0.300 sec.
     * <p>
     * Example:
     * <b>LEFT</b> <i>cut text</i> <b>RIGHT</b>
     * <b>LEFT</b> <i>cut text2</i> <b>RIGHT2</b>.
     */
    protected function findСutSectionAll($input, $searchStart, $searchFinish = null)
    {
        $array = [];

        while (empty($input) !== true) {
            $right = mb_strstr($input, $searchStart);

            foreach ($searchFinish as $value) {
                $left = mb_strstr($right, $value, true);

                if (!empty($left)) {
                    $input = mb_strstr($right, $value);
                    $array[] = mb_substr($left, mb_strlen($searchStart));

                    break;
                }
            }

            if (empty($left)) {
                $input = false;
            }
        }

        return $array;
    }
}
