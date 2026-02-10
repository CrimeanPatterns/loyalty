<?php

namespace AwardWallet\Engine\flyerbonus\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "flyerbonus/it-1.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && $this->checkMails($headers["from"])
            && (stripos($headers['subject'], "Electronic Flight Award Ticket"));
    }

    public function detectEmailFromProvider($from)
    {
        return in_array($from, ["webhelpdesk@bangkokair.com"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->checkMails($parser->getHTMLBody());
    }

    public function checkMails($input = '')
    {
        preg_match('/([\.@]bangkokair\.com)/ims', $input, $match);

        return (isset($match[0])) ? true : false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = null;
        $tripSegment = null;
        $itineraries['Kind'] = 'T';
        $itineraries['RecordLocator'] = $this->http->FindSingleNode("//span[contains(text(), 'BOOKING REFERENCE')]/following-sibling::span[1]");
        $infoPass = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Frequent Flyer Number')  and count(th) = 5]/following-sibling::tr");

        foreach ($infoPass as $row) {
            $passengers[] = $this->http->FindSingleNode(".//td[2]", $row);
            $accountNumbers[] = $this->http->FindSingleNode(".//td[6]", $row);
        }

        if (isset($passengers)) {
            $itineraries['Passengers'] = $passengers;
        }

        if (isset($accountNumbers)) {
            $itineraries['AccountNumbers'] = implode(',', $accountNumbers);
        }
        $currency = $this->http->FindSingleNode("//td/*[contains(text(), 'Total')]/../following-sibling::td");

        if (!empty($currency)) {
            preg_match('/[\.,\d]+/', $currency, $resultMoney);

            if (isset($resultMoney[0])) {
                $itineraries['TotalCharge'] = str_replace(',', '', $resultMoney[0]);
            }
            preg_match('/[A-Z]{3}/', $currency, $resultCurency);

            if (isset($resultCurency[0])) {
                $itineraries['Currency'] = str_replace(',', '', $resultCurency[0]);
            }
        }
        $segments = $this->http->XPath->query("//tr[contains(normalize-space(.), 'Departure')  and count(th) = 7]/following-sibling::tr[count(td) = 7]");
        $tripSegments = [];

        foreach ($segments as $segment) {
            $date = $this->http->FindSingleNode(".//td[2]", $segment);
            $depInfo = $this->http->FindSingleNode(".//td[3]", $segment);
            $arrInfo = $this->http->FindSingleNode(".//td[4]", $segment);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $this->http->FindSingleNode(".//td[5]", $segment), $m)) {
                $tripSegment['AirlineName'] = $m[1];
                $tripSegment['FlightNumber'] = $m[2];
            }

            if (!empty($date)) {
                preg_match('/[:0-9]+/', $depInfo, $depResult);

                if (isset($depResult[0])) {
                    $tripSegment['DepDate'] = strtotime(trim($date . ' ' . $depResult[0]));
                }
                preg_match('/[:0-9]+/', $arrInfo, $arrResult);

                if (isset($arrResult[0])) {
                    $tripSegment['ArrDate'] = strtotime(trim($date . ' ' . $arrResult[0]));
                }
            }
            preg_match('/\(([A-Z]{3})\)/', $depInfo, $depCode);

            if (isset($depCode[1])) {
                $tripSegment['DepCode'] = $depCode[1];
            }
            preg_match('/\(([A-Z]{3})\)/', $arrInfo, $arrCode);

            if (isset($arrCode[1])) {
                $tripSegment['ArrCode'] = $arrCode[1];
            }
            $tripSegment['DepName'] = trim(preg_replace('/\(([A-Z]{3})\)|[:0-9]+/', '', $depInfo));
            $tripSegment['ArrName'] = trim(preg_replace('/\(([A-Z]{3})\)|[:0-9]+/', '', $arrInfo));
            $tripSegments[] = $tripSegment;
        }
        $itineraries['TripSegments'] = $tripSegments;

        return [
            "emailType"  => "reservation",
            "parsedData" => [
                "Itineraries" => [$itineraries],
            ],
        ];
    }
}
