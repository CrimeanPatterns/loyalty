<?php
/**
 * Created by PhpStorm.
 * User: Roman.
 */

namespace AwardWallet\Engine\airasia\Email;

class AirPlane extends \TAccountChecker
{
    public $mailFiles = "airasia/it-4803170.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = $this->parseEmail();

        return [
            'parsedData' => [
                'Itineraries' => $its,
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//strong[contains(., 'Optiontown in partnership with AirAsia')]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return isset($from) && stripos($from, '@airasia.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@airasia.com') !== false;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(., 'Booking Reference')]", null, true, '#.*:\s+([\w-]+)#');
        $it['Passengers'] = $this->http->FindNodes("//td[contains(., 'Passenger')]/following-sibling::td[1]");

        $xpath = "//tr[contains(., 'Flight') and count(td)=4]/following-sibling::tr";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log('Segments not found: ' . $xpath, LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];
            $fligthInfo = $this->getNode('[1]/descendant::td[2]', $root);

            if (preg_match('#(\D+)\s*(\d+)#', $fligthInfo, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $depInfo = $this->getNode(2, $root);

            if (preg_match('#(.+)\s?\((\D{3})\)#', $depInfo, $math)) {
                $seg['DepName'] = $math[1];
                $seg['DepCode'] = $math[2];
            }
            $arrInfo = $this->getNode(3, $root);

            if (preg_match('#(.+)\s?\((\D{3})\)#', $arrInfo, $mathec)) {
                $seg['ArrName'] = $mathec[1];
                $seg['ArrCode'] = $mathec[2];
            }
            $date = $this->getNode(4, $root);

            if (!empty($date)) {
                $seg['DepDate'] = strtotime($date);
                $seg['ArrDate'] = strtotime($date);
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function getNode($node, $root)
    {
        if (is_int($node)) {
            return $this->http->FindSingleNode("td[{$node}]", $root);
        } else {
            return $this->http->FindSingleNode('td' . $node, $root);
        }
    }
}
