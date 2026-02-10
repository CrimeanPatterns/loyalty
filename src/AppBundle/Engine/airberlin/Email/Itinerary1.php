<?php

namespace AwardWallet\Engine\airberlin\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "airberlin/it-1.eml";

    public function ParseItinerary()
    {
        $result = ['Kind' => 'T'];
        $http = $this->http;
        // RecordLocator
        $result['RecordLocator'] = $http->FindPreg("/Passengers in your booking:\s*<b>([^<]+)<\/b>/ims");

        // FlightNumber
        $segment['FlightNumber'] = $http->FindSingleNode("//td[contains(text(), 'Flight:')]/following-sibling::td[1]");
        $result['TripSegments'] = [];
        // DepCode
        $segment["DepCode"] = TRIP_CODE_UNKNOWN;
        // DepName
        $segment["DepName"] = $http->FindSingleNode("//td[contains(text(), 'Departure:')]/following-sibling::td[1]", null, true, '/\,\s*([^<]+)/ims');
        // DepDate
        $date = $http->FindSingleNode("//td[contains(text(), 'Date:')]/following-sibling::td[1]");
        $depTime = CleanXMLValue($http->FindSingleNode("//td[contains(text(), 'Departure:')]/following-sibling::td[1]", null, true, '/\s*([^\,]+)/ims'));
        $segment["DepDate"] = strtotime($date . ' ' . $depTime);
        // ArrCode
        $segment["ArrCode"] = TRIP_CODE_UNKNOWN;
        // ArrName
        $segment["ArrName"] = $http->FindSingleNode("//td[contains(text(), 'Arrival:')]/following-sibling::td[1]", null, true, '/\,\s*([^<]+)/ims');
        // ArrDate
        $arrTime = CleanXMLValue($http->FindSingleNode("//td[contains(text(), 'Arrival:')]/following-sibling::td[1]", null, true, '/\s*([^\,]+)/ims'));
        $segment["ArrDate"] = strtotime($date . ' ' . $arrTime);
        $result['TripSegments'][] = &$segment;
        // Passengers
        if (isset($result['RecordLocator'])) {
            $result['Passengers'] = array_map('beautifulName', $http->FindNodes("//b[contains(text(), '" . $result['RecordLocator'] . "')]/ancestor::tr[1]/following-sibling::tr/td[@colspan = 2]"));
        }

        return [
            'Itineraries' => [$result],
            'Properties'  => [],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $emailType = $this->getEmailType($parser);

        switch ($emailType) {
            case "ParseItinerary":
                $result = $this->ParseItinerary();

                break;

            default:
                $result = "Undefined email type";

                break;
        }

        return [
            'parsedData' => $result,
            'emailType'  => $emailType,
        ];
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindPreg("/Passengers in your booking:\s*<b>([^<]+)<\/b>/ims")) {
            return "ParseItinerary";
        }

        return 'Undefined';
    }

    public function detectEmailByHeaders(array $headers)
    {
        return /*isset($headers['subject']) && false !== stripos($headers['subject'], "Air Berlin"))
            ||*/ isset($headers['from']) && false !== stripos($headers['from'], '@airberlin.com')
            /*|| (isset($headers['to']) && false !== stripos($headers['to'], '@airberlin.com')*/;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return false !== stripos($this->http->Response['body'], 'Air Berlin')
                || false !== stripos($this->http->Response['body'], 'www.airberlin.com');
    }

    public function detectEmailFromProvider($from)
    {
        return false !== stripos($from, '@airberlin.com');
    }
}
