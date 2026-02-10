<?php

namespace AwardWallet\Engine\british\Email;

class TimeChangeText2013En extends \TAccountChecker
{
    public $mailFiles = "british/it-1832553.eml, british/it-4631387.eml, british/it-4695119.eml";

    protected $result = [];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();
        $text = substr($text, 0, strpos($text, 'NEXT STEPS') | strpos($text, 'TERMS AND CONDITIONS'));

        $this->result['Kind'] = 'T';

        if (preg_match('/Booking Reference:\s*([A-Z\d]{5,6})/i', $text, $matches)) {
            $this->result['RecordLocator'] = $matches[1];
        }

        if (preg_match('/Passengers:\s*(.*?)Booking/s', $text, $matches)) {
            $this->result['Passengers'] = preg_split('/\n/', $matches[1], -1, PREG_SPLIT_NO_EMPTY);
        } elseif (preg_match('/Dear\s*(.*?),/', $text, $matches)) {
            $this->result['Passengers'][] = $matches[1];
        }

        $this->parseSegment($text);

        return [
            'emailType'  => 'TimeChangeText2013En',
            'parsedData' => ['Itineraries' => [$this->result]],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from'])
            && (stripos($headers['from'], 'BA.CustSvcs2@contact.britishairways.com') !== false
            || stripos($headers['from'], 'BA.CustSvcs@email.ba.com') !== false)
            && (preg_match('/Time Change - [A-Z\d]{5,6}/', $headers['subject']) !== false
            || strpos($headers['subject'], 'Receipt for paid seat selection for British Airways booking:') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();

        return strpos($text, 'We regret to inform you that') !== false
            || strpos($text, 'CONFIRMATION OF PAID SEATS SELECTED') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@contact.britishairways.com') !== false || stripos($from, '@email.ba.com') !== false;
    }

    protected function parseSegment($text)
    {
        $pregx = 'Flight Number:\s*([A-Z\d]{2})?\s*(\d+)\s*';
        $pregx .= 'From:\s*(.+?)\s*To:\s*(.+?)\s*';
        $pregx .= '(?:Original departure time:.+?)?';
        $pregx .= '(?:New departure time|Departs):\s*(.+?)\s*';
        $pregx .= '(?:Original arrival time:.+?)?';
        $pregx .= '(?:New arrival time|Arrives):\s*(.+?)\s*\n\s*';
        $pregx .= '(?:.*?Seat (\w+))?';

        if (preg_match_all("/{$pregx}/s", $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $value) {
                $segment['AirlineName'] = $value[1];
                $segment['FlightNumber'] = $value[2];
                $segment['DepName'] = $value[3];
                $segment['ArrName'] = $value[4];
                $segment['DepDate'] = strtotime($value[5]);
                $segment['ArrDate'] = strtotime($value[6]);

                if (isset($value[7])) {
                    $segment['Seats'] = $value[7];
                }
                $segment['ArrCode'] = $segment['DepCode'] = TRIP_CODE_UNKNOWN;
                $this->result['TripSegments'][] = $segment;
            }
        }
    }
}
