<?php

namespace AwardWallet\Engine\aegean\Email;

class BoardingPassText2014En extends \TAccountChecker
{
    public $mailFiles = "aegean/it-4965535.eml, it-4965535.eml";

    private $dateTimeToolsMonthNames = [
        "el" => ["nottrans", "nottrans", "nottrans", "απρ", "μαϊ", "ιουνιουν", "ιουλ", "αυγ", "nottrans", "οκτ", "nottrans", "nottrans"], // need to correct ryan
        'en' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $this->findСutSection($parser->getHTMLBody(), 'your flight is attached.', 'Please print your boarding pass');
        $result = $this->parseReservations(str_replace([' ', '<br>', '<br/>'], [' ', '', ''], $text));

        return [
            'parsedData' => ['Itineraries' => $result],
            'emailType'  => 'BoardingPassTextForAirTrip',
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'boardingpass@aegeanair.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'AEGEAN AIRLINES S.A. - Web check-in Confirmation') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Please print your boarding pass and present it at the airport.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aegeanair.com') !== false;
    }

    private function parseReservations($text)
    {
        $result['Kind'] = 'T';
        $result['RecordLocator'] = CONFNO_UNKNOWN;

        $regular = '/Flight:\s*([A-Z\d]{2})?\s*(\d+)\s*-\s*(.+?)\s*\(([A-Z]{3})\)\s*-\s*(.+?)\s*\(([A-Z]{3})\)\s*-\s*(.+?)\s*-\s*(\d+:\d+)\s*(.+?):.*?Ticket number:\s*(\d+).*?Flight Arrival:\s*(\d+:\d+)/usi';

        if (preg_match_all($regular, $text, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $result['Passengers'][] = $m[9];
                $result['TicketNumbers'][] = $m[10];

                $result['TripSegments'][] = [
                    'AirlineName'  => $m[1],
                    'FlightNumber' => $m[2],
                    'DepName'      => $m[3],
                    'DepCode'      => $m[4],
                    'ArrName'      => $m[5],
                    'ArrCode'      => $m[6],
                    'DepDate'      => strtotime($this->dateStringToEnglish(mb_strtolower($m[7] . ', ' . $m[8]))),
                    'ArrDate'      => strtotime($this->dateStringToEnglish(mb_strtolower($m[7] . ', ' . $m[11]))),
                ];
            }
        }

        if (isset($result['Passengers'])) {
            $result['Passengers'] = array_unique($result['Passengers']);
            $result['TicketNumbers'] = array_unique($result['TicketNumbers']);
        }

        return [$result];
    }

    private function dateStringToEnglish($dateString, $languageTip = false, $returnSourceStringOnFailure = false)
    {
        if (preg_match('#[[:alpha:]]+#iu', $dateString, $m)) {
            $monthNameOriginal = $m[0];
        } else {
            return $returnSourceStringOnFailure ? $dateString : false;
        }

        if ($translatedMonthName = $this->monthNameToEnglish($dateString, $languageTip)) {
            return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $dateString);
        } else {
            return $returnSourceStringOnFailure ? $dateString : false;
        }
    }

    private function monthNameToEnglish($monthNameOriginal, $languageTip = false)
    {
        $result = false;
        $list = $languageTip ? (is_array($languageTip) ? $languageTip : [$languageTip]) : array_keys($this->dateTimeToolsMonthNames);
        $monthNameOriginal = mb_strtolower($monthNameOriginal, 'UTF-8');

        foreach ($list as $ln) {
            if (isset($this->dateTimeToolsMonthNames[$ln])) {
                $possible = preg_split("#[\s,\-/]+#", $monthNameOriginal);

                foreach ($possible as $item) {
                    if (!trim($item, ",\n\t ")) {
                        continue;
                    }

                    $i = 0;

                    foreach ($this->dateTimeToolsMonthNames[$ln] as $mn) {
                        if (preg_match("#^$item#i", $mn)) {
                            $result = $this->dateTimeToolsMonthNames['en'][$i];

                            break 2;
                        }
                        $i++;
                    }
                }
            }
        }

        return $result;
    }

    private function findСutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_stristr(mb_stristr($input, $searchStart), $searchFinish, true);

        return mb_substr($input, mb_strlen($searchStart));
    }
}
