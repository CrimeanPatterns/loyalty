<?php

namespace AwardWallet\Engine\amadeus\Email;

class ForText2015En extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-4675311.eml";

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

        if (preg_match('/FOR:(.+)/', $text, $matches)) {
            $this->result['Passengers'] = trim($matches[1]);
        }

        $this->parseSegments($text);

        return [
            'emailType'  => 'Format TEXT from 2015 in "en"',
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
                // WINTERSTRID/MARIA 09AUG ARN
                && preg_match('/.+?\d+\w{3}\s+[A-Z]{3}/', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'INVOICE') !== false
                && strpos($parser->getHTMLBody(), 'FOR:') !== false;
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
        $segments = preg_split('/(\s*\n){2,}/', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($segments as $text) {
            if (strpos($text, 'AIRCRAFT:') !== false) {
                $this->result['TripSegments'][] = $this->parseSement($text);
            }
        }
    }

    protected function parseSement($text)
    {
        $segment = [];
        $regular = '([A-Z\s]+)\s+(\d+\w{3}).*?(\d+)\s+(\d+)\s+';
        $regular .= '([A-Z\d]{2})\s*(\d+)\s+SUNDAY\s+([A-Z\s]{1,15})\s+([A-Z\s]+).*?\b([A-Z])\s+(\w+).*?';
        $regular .= '(\d+:\d+)\s+DURATION\s+AIRCRAFT:\s+(.+?)SEAT';

        if (preg_match("/{$regular}/s", $text, $matches)) {
            $segment['Operator'] = trim($matches[1]);
            $segment += $this->increaseDate($this->result['ReservationDate'], $matches[2] . ', ' . $matches[3], $matches[4]);
            $segment['AirlineName'] = $matches[5];
            $segment['FlightNumber'] = $matches[6];
            $segment['DepName'] = trim($matches[7]);
            $segment['ArrName'] = trim($matches[8]);
            $segment['BookingClass'] = $matches[9];
            $segment['Cabin'] = $matches[10];
            $segment['Duration'] = $matches[11];
            $segment['Aircraft'] = trim($matches[12]);
            $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
        }

        return $segment;
    }
}
