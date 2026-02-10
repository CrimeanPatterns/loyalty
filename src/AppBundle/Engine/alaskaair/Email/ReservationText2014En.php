<?php

namespace AwardWallet\Engine\alaskaair\Email;

class ReservationText2014En extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-1732469.eml, alaskaair/it-1973521.eml, alaskaair/it-1988455.eml, alaskaair/it-1988464.eml, alaskaair/it-4365608.eml, alaskaair/it-6231753.eml";

    protected $result = [];

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
     *
     * @param type $input
     * @param type $searchStart
     * @param type $searchFinish array
     *
     * @return type
     */
    public function findСutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if (empty($searchFinish)) {
            $inputResult = $left;
        } elseif (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            $inputResult = mb_strstr($left, $searchFinish, true);
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        //$body = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();
        $body = text($this->http->Response['body']);

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        $this->result['Kind'] = 'T';
        $this->parseStatus($parser->getSubject());
        $this->parseRecordLocator($this->findСutSection($body, 'Confirmation Code:'));
        $this->parsePriceList($this->findСutSection($body, 'FARE SUMMARY (USD)'));
        $this->parsePassengers($this->findСutSection($body, 'TRAVELERS', ['You can exchange tickets', 'ITINERARY']));
        $this->parseSegments($this->findСutSection($body, 'ITINERARY', ['FARE SUMMARY', 'Thanks again!']));

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'Alaska.Web@AlaskaAir.com') !== false
            && isset($headers['subject']) && (
                stripos($headers['subject'], 'Canceled Reservation: Your') !== false
                || stripos($headers['subject'], 'AlaskaAir.com - Reservation Confirmation') !== false
            );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (strpos($parser->getHTMLBody(), 'Thank you for using alaskaair.com. ') && strpos($parser->getHTMLBody(), 'FARE SUMMARY'))
            || strpos($parser->getHTMLBody(), 'Thank you for holding a reservation at easybiz.alaskaair.com.');
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@AlaskaAir.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    protected function parseStatus($subject)
    {
        if (!empty($subject)) {
            if (stripos($subject, 'Canceled') !== false) {
                $this->result['Status'] = 'Canceled';
                $this->result['Cancelled'] = true;
            } elseif (stripos($subject, 'Confirmation') !== false) {
                $this->result['Status'] = 'Confirmation';
            }
        }
    }

    protected function parseRecordLocator($recordLocator)
    {
        if (preg_match('/[A-Z\d]{5,6}/', $recordLocator, $matches)) {
            $this->result['RecordLocator'] = $matches[0];
        }
    }

    protected function parsePriceList($plainText)
    {
        // Base: $174.88 Taxes: $58.32 Total: $233.20
        if (preg_match('/Base:\s*(.+)\s+Taxes:\s*(.+)\s+Total:\s*(.+)/', $plainText, $matches)) {
            $this->result['BaseFare'] = cost($matches[1]);
            $this->result['Tax'] = cost($matches[2]);
            $this->result['TotalCharge'] = cost($matches[3]);
            $this->result['Currency'] = currency($matches[3]);
        }
    }

    protected function parsePassengers($plainText)
    {
        // Michael Piecuch - 0272114428385
        foreach (preg_split('/\n/', $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            if (preg_match('/([\w\s]+)/', $value, $matches) && stripos($value, 'Confirmation Code') === false) {
                $this->result['Passengers'][] = trim($matches[1]);
            }

            if (preg_match('/\d+/', $value, $matches)) {
                $this->result['AccountNumbers'][] = $matches[0];
            }
        }

        if (isset($this->result['Passengers'])) {
            $this->result['Passengers'] = array_filter($this->result['Passengers']);
        }
    }

    protected function parseSegments($plainText)
    {
        foreach (preg_split('#\n\s+\n#', $plainText, -1, PREG_SPLIT_NO_EMPTY) as $value) {
            $this->result['TripSegments'][] = $this->segment($value);
        }
    }

    private function segment($text)
    {
        $segment = [];

        // Thursday, June 23, 2016
        if (preg_match('#,\s+(\w+\s+\d+,\s+\d{4})#', $text, $matches)) {
            $date = $matches[1];
        }

        //Alaska #471
        if (preg_match('/(\w+)\s+#(\d+)/', $text, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        if (preg_match('/Operated By\s+(.*)/', $text, $matches)) {
            // print_r($matches[1]);
        }

        //Depart: Los Angeles, CA (LAX) at 11:35 PM
        if (preg_match('/Depart:\s+(.+?)\(([A-Z]{3})\)(\s+at\s+(\d+:\d+(\s*[AP]M)?))?/', $text, $matches)) {
            $segment['DepName'] = trim($matches[1]);
            $segment['DepCode'] = $matches[2];

            if (isset($matches[4])) {
                $segment['DepDate'] = strtotime($matches[4], strtotime($date));
            } else {
                $segment['ArrDate'] = MISSING_DATE;
            }
        }

        //Depart: Los Angeles, CA (LAX) at 11:35 PM
        if (preg_match('/Arrive:\s+(.+?)\(([A-Z]{3})\)(\s+at\s+(\d+:\d+(\s*[AP]M)?))?/', $text, $matches)) {
            $segment['ArrName'] = trim($matches[1]);
            $segment['ArrCode'] = $matches[2];

            if (isset($matches[4])) {
                $segment['ArrDate'] = strtotime($matches[4], strtotime($date));
            } else {
                $segment['ArrDate'] = MISSING_DATE;
            }
        }

        return $segment;
    }
}
