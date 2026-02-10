<?php
/**
 * Created by PhpStorm.
 * User: roman.
 */

namespace AwardWallet\Engine\edreams\Email;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "edreams/it-5589472.eml";

    private static $detectBody = [
        'it' => 'Grazie per aver prenotato con eDreams',
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();
        $text = str_replace('&nbsp;', '', $text);
        $its = $this->parseEmail($text);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'AirTicketPlainText',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach (self::$detectBody as $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'edreams.com') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, 'edreams.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    private function parseEmail($text)
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T'];
        $info = $this->cutText('Codice prenotazione', 'Servizio Post', $text);
        /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
        $seg = [];
        $re = '/';
        $re .= 'Codice prenotazione\s+(?<Rec>[A-Z0-9]+)\s+(?<Date>\d+ \w+ \d{4})\s+(?<AName>[A-Z]{2})\s?(?<FNum>\d+)\s+';
        $re .= '(?<DepName>.+)\s+-\s+(?<ArrName>.+)\s+(?<DepTime>\d{2}:\d{2})\s+-\s+(?<ArrTime>\d{2}:\d{2})';
        $re .= '\s+Passeggero\s*:\s*(?<Psng>.+)';
        $re .= '/';

        if (!empty($info) && preg_match($re, $info, $m)) {
            $it['RecordLocator'] = $m['Rec'];
            $seg['AirlineName'] = $m['AName'];
            $seg['FlightNumber'] = $m['FNum'];
            $seg['DepName'] = $m['DepName'];
            $seg['ArrName'] = $m['ArrName'];
            $seg['DepDate'] = strtotime($m['Date'] . ', ' . $m['DepTime']);
            $seg['ArrDate'] = strtotime($m['Date'] . ', ' . $m['ArrTime']);
            $it['Passengers'][] = $m['Psng'];
        }

        if (isset($seg['DepDate']) && isset($seg['ArrDate']) && isset($seg['FlightNumber'])) {
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
        }
        $it['TripSegments'][] = $seg;

        return [$it];
    }

    private function cutText($start, $end, $text)
    {
        if (!empty($start) && !empty($end) && !empty($text)) {
            $cuttedText = stristr(stristr($text, $start), $end, true);

            return substr($cuttedText, 0);
        }

        return null;
    }
}
