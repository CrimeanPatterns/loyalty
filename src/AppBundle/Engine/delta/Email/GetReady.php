<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\ItineraryArrays\AirTrip;
use AwardWallet\ItineraryArrays\AirTripSegment;

class GetReady extends \TAccountChecker
{
    public $mailFiles = "delta/it-2694527.eml, delta/it-32836058.eml, delta/it-8309418.eml";

    public $lang = "en";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        if (($roots = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Depart:') and contains(normalize-space(.), 'Flight') and not(.//tr)]")) && 0 < $roots->length) {
            $its = $this->parseAir($roots);
        } else {
            $its = $this->ParseEmail();
        }

        return [
            'parsedData' => ["Itineraries" => $its],
            'emailType'  => "GetReady",
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'deltaairlines@e.delta.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href, "delta") and ( contains(., "It\'s coming up. Here\'s some useful info.") or contains(normalize-space(.), "experience delta") )]')->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@\.]delta\.com/", $from);
    }

    //2694527

    protected function ParseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $rows = $this->http->XPath->query("//text()[normalize-space(.)='DEPART:']/ancestor::table[contains(., 'seat:')][1]/..");

        foreach ($rows as $row) {
            $segment = [];
            $text = $row->nodeValue;
            $codes = $this->http->FindNodes('./table[1]/descendant::text()[normalize-space(.)]', $row);
            $segment['DepCode'] = array_shift($codes);
            $segment['ArrCode'] = array_shift($codes);

            $segment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='DEPART:']/following::text()[normalize-space(.)][1]", $row)));
            $segment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='ARRIVE:']/following::text()[normalize-space(.)][1]", $row)));

            $segment['AirlineName'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='flight #:']/following::text()[normalize-space(.)][1]", $row, true, "#(\w{2})\s+\d+#");
            $segment['FlightNumber'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='flight #:']/following::text()[normalize-space(.)][1]", $row, true, "#\w{2}\s+(\d+)#");

            $segment['Seats'] = $this->http->FindSingleNode('.//td[contains(., "seat:") and not(.//td)]', $row, true, '/\d+[A-Z]$/');
            $it['TripSegments'][] = $segment;
        }

        if (count($it['TripSegments']) > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        return [$it];
    }

    private function parseAir(\DOMNodeList $roots)
    {
        /** @var AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = CONFNO_UNKNOWN;

        foreach ($roots as $root) {
            /** @var AirTripSegment $seg */
            $seg = [];

            $seg['DepCode'] = $this->http->FindSingleNode("td[1]", $root);

            $seg['ArrCode'] = $this->http->FindSingleNode('td[3]', $root);

            $seg['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("(td[last()]/descendant::node()[starts-with(normalize-space(.), 'Depart:')]/following-sibling::node()[normalize-space(.)][1])[1]", $root)));

            $seg['ArrDate'] = MISSING_DATE;

            if (preg_match('/([A-Z\d]{2})[ ]*(\d+)/', $this->http->FindSingleNode("(td[last()]/descendant::node()[starts-with(normalize-space(.), 'Flight')]/following-sibling::node()[normalize-space(.)][1])[1]", $root), $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+\s+[AP]M),\s+[^\s\d]+\s+(\d+)\s+([^\s\d]+)$#", // 10:58 PM, Wed 06 Sep
            '/^(\d{1,2}:\d{2} [AP]M) (\w+) (\d{1,2}), (\d{2,4})$/', // 01:15 PM March 01, 2019
        ];
        $out = [
            "$2 $3 $year, $1",
            '$3 $2 $4, $1',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
