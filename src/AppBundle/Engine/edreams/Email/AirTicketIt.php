<?php
/**
 * Created by PhpStorm.
 * User: Роман.
 */

namespace AwardWallet\Engine\edreams\Email;

class AirTicketIt extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "edreams/it-4178413.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(., 'Grazie per aver prenotato con eDreams')]")->length > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], "@edreams.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@edreams.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return ['it'];
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Numero di prenotazione')]/following::*[normalize-space(.)!=''][1]");
        $it['Passengers'] = $this->http->FindNodes("//*[contains(text(), 'Passeggeri')]/descendant::div[1]/descendant::text()[normalize-space(.)!=''][last()]");
        $xpath = "//*[contains(text(), 'Informazioni sui voli')]/descendant::li";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log("segments not found : {$xpath}", LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];
            $depArrName = $this->http->FindSingleNode("descendant::span[normalize-space(.)!=''][1]", $root);

            if (preg_match("#(.+) a (.+)#", $depArrName, $m)) {
                $seg['DepName'] = $m[1];
                $seg['ArrName'] = $m[2];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $info = $this->getInfoAboutFlight($this->http->FindSingleNode("descendant::div[normalize-space(.)!=''][3]", $root));

            if (count($info) === 5) {
                $seg['DepDate'] = strtotime($info['Date'] . ' ' . $info['DepTime']);
                $seg['AirlineName'] = $info['AirlineName'];
                $seg['FlightNumber'] = $info['FlightNumber'];
                $seg['ArrDate'] = strtotime($info['Date'] . ' ' . $info['ArrTime']);
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getInfoAboutFlight($str)
    {
        if (preg_match("#.*\s+(?<day>\d{2})[\s|de]*(?<month>\w+)[\s|de]*(?<year>\d{4})\s+\S*\s+(?<depTime>\d+:\d+)\s*[-]*\s+(?<arrTime>\d+:\d+)\s*\S*\s+(?<aName>\w{2})\s*(?<fNumber>\d+)#", $str, $m)) {
            return [
                'Date'         => $this->monthNameToEnglish($m['month']) . ' ' . $m['day'] . ' ' . $m['year'],
                'DepTime'      => $m['depTime'],
                'ArrTime'      => $m['arrTime'],
                'AirlineName'  => $m['aName'],
                'FlightNumber' => $m['fNumber'],
            ];
        }

        return $str;
    }
}
