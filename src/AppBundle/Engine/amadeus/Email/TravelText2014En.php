<?php

namespace AwardWallet\Engine\amadeus\Email;

class TravelText2014En extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-4885670.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->detectEmailByBody($parser)) {
            $this->http->Log('file not recognized, check detectEmailByHeaders or detectEmailByBody method', LOG_LEVEL_ERROR);

            return false;
        }

        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();

        $this->result['Kind'] = 'T';

        if (preg_match('/BOOKING REF\s+([A-Z\d]{5,6})/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/DATE\s+(\d+\w+\d+)/', $text, $matches)) {
            $this->result['ReservationDate'] = strtotime($matches[1]);
        }

        $this->parseSegments($text);

        return [
            'emailType'  => 'TravelText2014En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function increaseDate($dateLetter, $depTime, $arrTime)
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

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'emailserver2@pop3.amadeus.net') !== false
                && strpos($headers['subject'], 'Your travel information') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'SERVICE        FROM                TO') !== false
                && strpos($parser->getHTMLBody(), 'RESERVATION NUMBER(S)') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@pop3.amadeus.net') !== false;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    protected function parseSegments($text)
    {
        $segments = preg_split('/-{10,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $text) {
            if (strpos($text, 'EQUIPMENT:') !== false) {
                $this->result['TripSegments'][] = $this->parseSement($text);
            }
        }
    }

    protected function parseSement($text)
    {
        $segment = [];
        $regular = '.+?\s*-\s*([A-Z\d]{2})\s*(\d+).*?\w+\s+(\d+\w+)';
        $regular .= '\s{2,}(.+?)\s{2,}(.+?)\s{2,}.*?(\d{4})\s+(\d{4}).*?';
        $regular .= 'DURATION\s+(\d+:\d+).*?EQUIPMENT:\s*(.+)';

        if (preg_match("/{$regular}/s", $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
            $segment += $this->increaseDate($this->result['ReservationDate'], $matches[3] . ', ' . $matches[6], $matches[7]);
            $segment['DepName'] = $matches[4];
            $segment['ArrName'] = $matches[5];
            $segment['Duration'] = $matches[8];
            $segment['Aircraft'] = trim($matches[9]);
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        return $segment;
    }
}
