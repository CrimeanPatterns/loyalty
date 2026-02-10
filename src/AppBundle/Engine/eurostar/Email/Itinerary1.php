<?php

namespace AwardWallet\Engine\eurostar\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "eurostar/it-1.eml, eurostar/it-2.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';
        $recordL = $this->http->XPath->query(".//*[contains(text(),'Eurostar:')]/*");
        $recordResult = '';

        if ($recordL->length > 0) {
            foreach ($recordL as $record) {
                $value = preg_replace('/[^a-z0-9\:\-\)\(\n\pL\']+/iu', '', $record->nodeValue);

                if (!empty($value)) {
                    $recordResult = $value;

                    break;
                }
            }
        }
        $passResult = [];

        $pass = $this->http->FindNodes(".//*[contains(text(),'View or amend your booking')]/../../../../../*[count(tr) = 2]/tr[1]/td");

        if (!empty($pass)) {
            foreach ($pass as $key => $value) {
                $name = preg_replace('/[^a-z0-9\:\-\)\(\n \pL\']+/iu', '', $value);

                if (!empty($name)) {
                    $name = trim(substr($name, 0, strpos($name, '-')));
                    $passResult[] = $name;
                }
            }
        }
        $paid = $this->http->FindSingleNode(".//*[contains(text(),'TOTAL AMOUNT PAID')]/following-sibling::*/text()");
        $paid = preg_replace('/[^a-z0-9\:\-\)\(\n\. \pL\']+/iu', '', $paid);
        $paidResult = (float) preg_replace("/([^0-9\\.])/i", "", $paid);
        $segmentsTemp = $this->http->XPath->query(".//*[contains(text(),'Departure:')]/../*");

        $segments = [];

        if ($segmentsTemp->length > 0) {
            foreach ($segmentsTemp as $segment) {
                $value = preg_replace('/[^a-z0-9\:\-\)\( \pL\']+/iu', '', $segment->nodeValue);
                $value = preg_replace('/ {2,}/', ' ', $value);
                $value = trim($value);

                if (!empty($value)) {
                    $segments[] = $value;
                }
            }
        }
        $depNameResult = '';
        $arrNameResult = '';
        $depTimeResult = '';
        $arrTimeResult = '';
        $trainNoResult = '';
        $durationResult = '';
        $airlineResult = '';

        if (!empty($segments)) {
            foreach ($segments as $segment) {
                if (stripos($segment, 'From') !== false) {
                    preg_match('#.*\:\s*(.*)#', $segment, $depName);

                    if (!empty($depName[1])) {
                        $depNameResult = $depName[1];
                    }
                }

                if (stripos($segment, 'to:') !== false) {
                    preg_match('#.*\:\s*(.*)#', $segment, $arrName);

                    if (!empty($arrName[1])) {
                        $arrNameResult = $arrName[1];
                    }
                }

                if (stripos($segment, 'Departure') !== false) {
                    preg_match('#Departure:\s*(\d*\:\d*)#', $segment, $depTime);

                    if (!empty($depTime[1])) {
                        $depTimeResult = $depTime[1];
                    }
                }

                if (stripos($segment, 'Arrival') !== false) {
                    preg_match('#Arrival:\s*(\d*\:\d*)#', $segment, $arrTime);

                    if (!empty($arrTime[1])) {
                        $arrTimeResult = $arrTime[1];
                    }
                }

                if (stripos($segment, 'Train no') !== false) {
                    preg_match('#.*\:\s*(.*)#', $segment, $trainNo);

                    if (!empty($trainNo[1])) {
                        $trainNoResult = $trainNo[1];
                    }
                }

                if (stripos($segment, 'Duration') !== false) {
                    preg_match('#.*\:\s*(.*)#', $segment, $duration);

                    if (!empty($duration[1])) {
                        $durationResult = $duration[1];
                    }
                }

                if (stripos($segment, 'Train operator') !== false) {
                    preg_match('#.*\:\s*(.*)#', $segment, $airline);

                    if (!empty($airline[1])) {
                        $airlineResult = $airline[1];
                    }
                }
            }
        }
        $dateResult = "";
        $dateData = $this->http->FindNodes(".//*[contains(text(),'Departure:')]/../../tr[3]");

        if (!empty($dateData)) {
            preg_match('#.*\:\s*(.*)#', $dateData[0], $date);

            if (!empty($date[1])) {
                $date = $date[1];
                $dateResult = date('Y-m-d', strtotime($date));
            }
        }

        $itineraries['RecordLocator'] = $recordResult;
        $itineraries['Passengers'] = $passResult;
        $itineraries['TotalCharge'] = $paidResult;
        $itineraries['TripCategory'] = TRIP_CATEGORY_TRAIN;
        $itineraries['TripSegments'][] = [
            //'FlightNumber' => $trainNoResult,
            'DepDate' => strtotime($dateResult . ' ' . $depTimeResult),
            'DepName' => $depNameResult,
            'ArrDate' => strtotime($dateResult . ' ' . $arrTimeResult),
            'ArrName' => $arrNameResult,
            //'AirlineName' => $airlineResult,
            'Seats'    => $this->http->FindNodes("//text()[contains(.,'Seat:')]/ancestor::td[1]/following-sibling::td[normalize-space(.)]"),
            'Duration' => $durationResult,
            'Cabin'    => $this->http->FindSingleNode("//text()[contains(.,'Coach:')]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]"),
            'DepCode'  => TRIP_CODE_UNKNOWN,
            'ArrCode'  => TRIP_CODE_UNKNOWN,
        ];

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$itineraries],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]eurostar\.co\.uk$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return !empty($headers['subject']) && stripos($headers['subject'], 'Your Eurostar booking confirmation') !== false;
    }
}
