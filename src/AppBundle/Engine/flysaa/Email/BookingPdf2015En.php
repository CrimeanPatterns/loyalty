<?php

namespace AwardWallet\Engine\flysaa\Email;

class BookingPdf2015En extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-5598095.eml, flysaa/it-5604598.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $pdf = $parser->searchAttachmentByName('Your EMD Receipt\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $text = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $this->parseReservation(str_replace(' ', ' ', $this->findCutSection($text, null, ['Notice /', 'Where this document'])));

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'EMAILSERVER@POP3.AMADEUS.NET') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Votre recu electronique de l\'EMD / Your EMD receipt'));
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Please find attached your Electronic Miscellaneous Document receipt') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@POP3.AMADEUS.NET') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    //========================================
    // Auxiliary methods
    //========================================

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
    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;

        if (!empty($searchStart)) {
            $input = mb_strstr($input, $searchStart);
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($input, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($input, $searchFinish, true);
        } else {
            $inputResult = $input;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    protected function parseReservation($text)
    {
        $this->result['Kind'] = 'T';

        if (preg_match('/Booking Reference.*?:?([A-Z\d]{5,6})/', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        // Fare / Taryfa       GBP 22.70        Form of Payment
        if (preg_match('/Fare.*?([A-Z]{3})\s*([\d.,]+)\s+/', $text, $matches)) {
            $this->result['Currency'] = $matches[1];
            $this->result['TotalCharge'] = (float) $matches[2];
        }

        // Pardjak Piotr Boguslaw Mr            080 8200793855
        if (preg_match_all('/\s*(.+?)\s{3,}([\d]+[\s-]*[\d]+)/', $this->findCutSection($text, 'Document Number', ['Services']), $matches)) {
            $this->result['Passengers'] = $matches[1];
            $this->result['TicketNumbers'] = $matches[2];
        }

        $this->parseSegments($this->findCutSection($text, '   Baggage', ['Payment Details']));
    }

    protected function parseSegments($text)
    {
        foreach ($this->splitter('/(\b[A-Z]{3}\s+[A-Z]{3}\s+[A-Z\d]{2}\s*\d+)/', $text) as $text) {
            $segment = [];

            // JFK                  WAW          LO 027           L    29Nov ,         30Nov ,          OK
            // Assignment                                              22:30           12:45
            $pattern = '([A-Z]{3})\s+([A-Z]{3})\s+([A-Z\d]{2})\s*(\d+)\s+([A-Z])\s+';
            $pattern .= '(\d+\w+)\s*,(?:\s+(\d+\w+)\s*,)?.+?(\d+:\d+)\s+(\d+:\d+)?';

            if (preg_match("/$pattern/s", $text, $matches)) {
                $segment['DepCode'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
                $segment['AirlineName'] = $matches[3];
                $segment['FlightNumber'] = $matches[4];
                $segment['BookingClass'] = $matches[5];
                $segment['DepDate'] = $this->dateYear($this->date, $matches[6] . ', ' . $matches[8]);

                if (!empty($matches[7]) && !empty($matches[9])) {
                    $segment['ArrDate'] = strtotime($matches[7] . ', ' . $matches[9], $segment['DepDate']);
                } else {
                    $segment['ArrDate'] = MISSING_DATE;
                }
            }

            $this->result['TripSegments'][] = $segment;
        }
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function dateYear($dateLetter, $depTime)
    {
        $depDate = strtotime($depTime, $dateLetter);

        if ($dateLetter > $depDate) {
            $depDate = strtotime('+1 year', $depDate);
        }

        return $depDate;
    }
}
