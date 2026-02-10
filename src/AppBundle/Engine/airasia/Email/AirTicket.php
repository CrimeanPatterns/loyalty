<?php

namespace AwardWallet\Engine\airasia\Email;

// TODO: fix different airasia/it-10045893.eml with parser `AirTicket`

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "airasia/it-10017333.eml, airasia/it-4535428.eml, airasia/it-4723810.eml, airasia/it-4791987.eml, airasia/it-5263377.eml, airasia/it-9873562.eml, airasia/it-9878063.eml, airasia/it-9969773.eml, airasia/it-9974991.eml";

    private $parser;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'AirAsia Flight Advisory ') !== false
            || stripos($headers['subject'], 'Airasia Zest Flight Advisory ') !== false
            || stripos($headers['subject'], 'Your AirAsia flight') !== false
            || stripos($headers['subject'], 'AirAsia - Flight Reminder') !== false
        ) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'AirAsia') === false) {
            return false;
        }

        return stripos($headers['subject'], 'has been rescheduled to a new time') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airasia.com') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Sincerely, AirAsia") or contains(normalize-space(),"Sincerely,AirAsia") or contains(normalize-space(),"Yours sincerely, AirAsia") or contains(normalize-space(),"Yours sincerely,AirAsia") or contains(.,"www.airasia.com") or contains(.,"@airasia.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,".airasia.com/")]')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query("//node()[{$this->contains([
            'Your NEW Flight Details',
            'YOUR NEW FLIGHT DETAILS',
            'NEW Flight Number',
        ])}]")->length > 0;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[normalize-space()='Airline PNR']/ancestor::td[1]/following-sibling::td[1])[1]", null, true, "#^([A-Z\d]{5,7})$#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[normalize-space()='Your Booking Number:' or normalize-space()='Your booking number:']//following::text()[normalize-space(.)][1])[1]", null, true, "#^([A-Z\d]{5,7})$#");
        }

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }
        $passenger = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Dear')]/ancestor::*[1]/b)[1]", null, true, "#^([\w\- ]+)$#");

        if (!empty($passenger) && stripos($passenger, 'Valued Guest') === false) {
            $it['Passengers'][] = $passenger;
        }

        $xpath = "//*[contains(text(), 'Depart') and not(contains(text(), 'Departure'))]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->http->Log('Segments not found ' . $xpath, LOG_LEVEL_NORMAL);
        }

        foreach ($roots as $root) {
            $seg = [];

            // FlightInfo
            $flightInfo = $this->http->FindSingleNode("(//text()[contains(., 'flight')]/following::b[normalize-space(.)!=''][1])[1]", null, true, '#^\s*((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*[\-]*\d{1,5})\s*$#');

            if ($flightInfo === null) {
                $flightInfo = $this->http->FindSingleNode("(//*[contains(., 'Flight') and (contains(., 'number') or contains(., 'Number')) and ancestor::*[count(*)=3][1]]/following-sibling::*[string-length(self::*)>2][1])[1]", null, true, '#((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*[\-]*\d+)#');
            }

            if ($flightInfo === null) {
                $flightInfo = $this->http->FindSingleNode("(//text()[contains(., 'flight')][1])[1]");
            }

            if (preg_match('#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*[\-]*(\d+)#', $flightInfo, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            // Dates
            $date = date('m/d/Y', strtotime($this->parser->getDate()));
            $dateFromBody = $this->http->FindSingleNode("//*[contains(text(), 'Depart') and not(contains(text(), 'Departure'))]/ancestor::*[name(self::tr) or name(self::div)][1]/preceding-sibling::*[1]//td[contains(., 'Departure date')]/following-sibling::td[1]", null, true, '#[:]*\s+(\d{2} \w+ \d+)#');

            if ($dateFromBody === null) {
                $dateFromBody = $this->http->FindSingleNode("//p[contains(., 'Flight Date')]", null, true, '#Flight\s+date\s+(\d+ \w+ \d+)#');
            }

            if (empty($dateFromBody)) {
                $dateFromBody = $this->normalizeDate($this->http->FindSingleNode("(//*[(local-name()='p' or local-name()='font') and contains(normalize-space(), 'depart from')])[1]"));
                $dateFormat = [
                    "depart\s+from.+this\s+(?<date>\d+-\w+-\d+)",
                    "depart\s+from.+on\s+(?<date>\w+\s*\d+\s*,\s*\d+)",
                    "depart\s+from.+on\s+(?<date>\d+\s*\w+\s*\d+)",
                ];

                if (!empty($dateFromBody) && preg_match("#(?:" . implode('|', $dateFormat) . ")#J", $dateFromBody, $m)) {
                    $dateFromBody = $m['date'];
                } else {
                    $dateFromBody = '';
                }
            }

            if (empty($dateFromBody)) {
                $dateFromBody = $this->normalizeDate($this->http->FindSingleNode("(//*[(local-name()='p' or local-name()='font') and contains(normalize-space(), 'you that your flight on')])[1]", null, true, "#your flight on\s*(\w+ \d+,\s*\d+)#"));
            }

            // DepName and ArrName in text
            $depAndArrName = $this->http->FindSingleNode("//*[name()='p' or name()='font'][contains(normalize-space(.), 'you that flight') or contains(normalize-space(.), 'you that your flight')]", null, true, '#.*(depart from .+ to .+ (?:on|this)).*#i');

            if (preg_match('#depart\s+from\s+(.+)\s+to\s+(.+)\s+(on|this)#i', $depAndArrName, $math)) {
                $depName = $math[1];
                $arrName = $math[2];
            }

            // DepInfo
            $depInfo = $this->getSegmentInfo($this->http->FindSingleNode("ancestor::*[name(self::p) or name(self::div)][1]", $root));

            if ($depInfo === null) {
                $depInfo = $this->getSegmentInfo($this->http->FindSingleNode("ancestor::*[name(self::tr) or name(self::div)][1]", $root));
            }

            if ($depInfo === null) { //4791987.eml
                $depInfo = $this->getSegmentInfo($this->http->FindSingleNode('.', $root));
            }

            if ($depInfo !== null && !empty($dateFromBody)) {
                $seg['DepName'] = ($depInfo['Name']) ? trim($depInfo['Name']) : null;
                $seg['DepCode'] = ($depInfo['Code']) ? $depInfo['Code'] : TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = ($depInfo['Date']) ? strtotime($depInfo['Date'] . ' ' . $depInfo['Time']) : strtotime(($dateFromBody) ? $dateFromBody . ' ' . $depInfo['Time'] : $date . ' ' . $depInfo['Time']);
            }

            if ((!isset($seg['DepName']) || empty($seg['DepName'])) && isset($depName)) {
                $seg['DepName'] = $depName;
            }

            // ArrInfo
            $arrInfo = $this->getSegmentInfo($this->http->FindSingleNode("ancestor::*[name(self::p) or name(self::div) or name(self::tr)][1]/following::*[normalize-space(*)!='' and contains(., 'Arrive')][1]", $root));

            if ($arrInfo === null) {
                $arrInfo = $this->getSegmentInfo($this->http->FindSingleNode("text()[contains(., 'Arrive')]", $root));
            }

            if ($arrInfo === null) {
                $arrInfo = $this->getSegmentInfo($this->http->FindSingleNode('following-sibling::p[1]', $root));
            }

            if ($arrInfo !== null && !empty($dateFromBody)) {
                $seg['ArrName'] = ($arrInfo['Name']) ? trim($arrInfo['Name']) : null;
                $seg['ArrCode'] = ($arrInfo['Code']) ? $arrInfo['Code'] : TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = ($arrInfo['Date']) ? strtotime($arrInfo['Date'] . ' ' . $arrInfo['Time']) : strtotime(($dateFromBody) ? $dateFromBody . ' ' . $arrInfo['Time'] : $date . ' ' . $arrInfo['Time']);
            }

            if ((!isset($seg['ArrName']) || empty($seg['ArrName'])) && isset($arrName)) {
                $seg['ArrName'] = $arrName;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    /**
     * example: Depart Kuala Lumpur (KUL)   :	10.10pm (2210hrs), local time.
     *
     * @param $segments
     *
     * @return array|null
     */
    private function getSegmentInfo($segments)
    {
        $res = [];
        $re = '#(?:Depart from|Arrive in|Depart|Arrive)[;]*(?<Name>[\w|\w\s]*)\s+\((?<Code>\D{3})\)\s?:\s?(?<H>\d+)[\.:]*(?<M>\d+\s*[pm|am]+).*(?:local time|on)\s?(?<Date>\d+ \w+ \d+)?#i';
        $re2 = '#(?:Depart|Arrive)\s+(?<Code>\D{3})?\s?(?<H>\d+):(?<M>\d+\s*(?:pm|am)).*#i';
        $re3 = '#(?:Depart|Arrive)\s+[^(]+\((?<Code>\D{3})?\)\s?(?<H>\d+):(?<M>\d+\s*(?:pm|am)).*#i';

        if (preg_match($re, $segments, $m) || preg_match($re2, $segments, $m) || preg_match($re3, $segments, $m)) {
            $res = [
                'Name' => (isset($m['Name'])) ? $m['Name'] : null,
                'Code' => (isset($m['Code'])) ? $m['Code'] : null,
                'Time' => $m['H'] . ':' . $m['M'],
                'Date' => (!empty($m['Date'])) ? $m['Date'] : null,
            ];
        }

        return ($res) ? $res : null;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d{1,2})-(\w+)-(\d{2})$#", //22-Nov-17
        ];
        $out = [
            "$1 $2 20$3",
        ];
        $str = preg_replace($in, $out, $str);

        //		if(preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
        //			if($en = MonthTranslate::translate($m[1], $this->lang))
        //				$str = str_replace($m[1], $en, $str);
        //		}
        return $str;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
