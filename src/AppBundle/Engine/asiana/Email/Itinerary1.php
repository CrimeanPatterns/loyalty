<?php

namespace AwardWallet\Engine\asiana\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "asiana/it-4.eml, asiana/it-5.eml, asiana/it-6.eml, asiana/it-7.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && preg_match("#Asiana Airlines Reservation Itinerary Information#", $headers['subject'])
            || isset($headers['from']) && preg_match('/@flyasiana\.com/i', $headers['from']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'departure of Asiana flights') !== false) {
            return true;
        }

        return stripos($body, 'Asiana Airlines(Only for Sending)') !== false;
    }

    public function extractPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function toText($html)
    {
        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        return $html;
    }

    public function re($re, $text)
    {
        return preg_match($re, $text, $m) ? $m[1] : null;
    }

    public function parsePdfMail(\PlancakeEmailParser $parser)
    {
        $it = [];
        $it['Kind'] = 'T';

        $text = $this->toText($this->extractPDF($parser));

        $it['Passengers'] = $this->re("#\nPassenger Name\s*([^\n]+)#", $text);
        $it['RecordLocator'] = $this->re("#\nReservation No\.\s*([^\n]+)#", $text);

        $route = $this->re("#Itinerary\s+City\(Airport\)\s+Date\(Day\)\s+Local.Time\s+Terminal\s+Flying.Time\s+Status\s+(.*?)All conditions may vary#uims", $text);
        $trips = preg_split("#\n\d+\.\s+([^\n]+)#ims", "\n" . $route, -1, PREG_SPLIT_DELIM_CAPTURE);

        $it['TotalCharge'] = $this->re("#\nTotal\s+([^\n]+)#", $text);
        $it['Currency'] = preg_replace("#[^A-Z]+#", '', $it['TotalCharge']);
        $it['TotalCharge'] = preg_replace("#[^\d\.,]+#", '', $it['TotalCharge']);
        $it['BaseFare'] = preg_replace("#[^\d\.,]+#", '', $this->re("#\nFare\n([^\n]+)#", $text));

        $it['TripSegments'] = [];

        for ($i = 1; $i < count($trips) - 1; $i += 2) {
            $seg = [];

            $seg['FlightNumber'] = trim($trips[$i]);
            $details = trim($trips[$i + 1]);

            if (preg_match("#^Departure\s+(.*?)\s+(\d{1,2}\w{3}\d{4})[^\n]*\s+(\d{1,2}:\d{1,2})\s+[^\n]+\s+(\d+H\d+M)\s+[^\n]+\s+Arrival\s+(.*?)\s+(\d{1,2}\w{3}\d{4})[^\n]*\s+(\d{1,2}:\d{1,2})#", $details, $m)
                // ArrName before "Arrive"
               || preg_match("#^Departure\s+(.*?)\s+(\d{1,2}\w{3}\d{4})[^\n]*\s+(\d{1,2}:\d{1,2})\s+[^\n]+\s+(\d+H\d+M)\s+[^\n]+\s+(.*?)\s+Arrival\s+(\d{1,2}\w{3}\d{4})[^\n]*\s+(\d{1,2}:\d{1,2})#", $details, $m)
               // DepName before "Departure"
               || preg_match("#^(.*?)\s+Departure\s+(\d{1,2}\w{3}\d{4})[^\n]*\s+(\d{1,2}:\d{1,2})\s+[^\n]+\s+(\d+H\d+M)\s+[^\n]+\s+.*?\s+.*?Arrival\s+(.*?)\s+(\d{1,2}\w{3}\d{4})[^\n]*\s+(\d{1,2}:\d{1,2})#", $details, $m)
               ) {
                $seg['DepName'] = $m[1];
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepDate'] = strtotime($m[2] . ', ' . $m[3]);

                $seg['Duration'] = $m[4];

                $seg['ArrName'] = $m[5];
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrDate'] = strtotime($m[6] . ', ' . $m[7]);

                $seg['AirlineName'] = $this->re("#is operated by\s+([^\n]+)#", $details);
                $seg['BookingClass'] = $this->re("#\nBooking Class\s+([^\n]+)#", $details);
            }

            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    public function parseHtmlMail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';

        // Reservation No 	4000515 (CEDFWN)
        if ($data = $this->http->FindSingleNode("//td/span[contains(text(),'Reservation No')]/parent::td/following-sibling::td[1]")) {
            if (preg_match("/\(([A-Z\d]{6})\)/", $data, $m)) {
                $data = $m[1];
            } else {
                $data = preg_replace('/[^\d]/', '', $data);
            }
            $itineraries['RecordLocator'] = $data;
        }

        // Passenger Name 	ANACHETA/STATSENBERGMR
        if ($data = $this->http->FindSingleNode("//td/span[contains(text(),'Passenger Name')]/parent::td/following-sibling::td[1]")) {
            $itineraries['Passengers'] = explode(',', beautifulName(str_replace('/', ' ', $data)));

            foreach ($itineraries['Passengers'] as $k=> $name) {
                if (substr($name, -2) == 'mr') {
                    $itineraries['Passengers'][$k] = 'Mr. ' . substr($name, 0, -2); // todo ?

                    continue;
                }

                if (substr($name, -3) == 'mrs') {
                    $itineraries['Passengers'][$k] = 'Mrs. ' . substr($name, 0, -3); // todo ?

                    continue;
                }
            }
        }

        $segments = [];
        $Status = 'Confirmed';

        foreach ($this->http->XPath->query('//td[@colspan="7"]/table') as $row) {
            $segment = [];

            // 1. OZ0368
            $FlightNumber = $this->http->FindSingleNode(".//tr[1]/td[1]/span", $row);

            if (preg_match('/\d+\.\s*?(?P<number>.*)/', $FlightNumber, $matches)) {
                $FlightNumber = $matches['number'];
            }

            // OZ0368 is operated by ASIANA AIRLINES
            $AirlineName = $this->http->FindSingleNode(".//tr[position()=last()]/td", $row);

            if (preg_match('/is operated by\s*(?P<name>.*)/', $AirlineName, $matches)) {
                $AirlineName = $matches['name'];
            }

            // Departure 	SHANGHAI PU DONG(PVG) 	25JUN2012(MON) 	08:35 	TRAVEL 	Confirmed
            $DepName = $this->http->FindSingleNode(".//td[contains(text(),'Departure')]/following-sibling::td[1]", $row);

            if (preg_match('/(?P<name>.+)\((?P<code>.+)\)/', $DepName, $matches)) {
                $DepName = $matches['name'];
                $DepCode = $matches['code'];
            } else {
                $DepCode = TRIP_CODE_UNKNOWN;
            }

            $DepDate = $this->http->FindSingleNode(".//td[contains(text(),'Departure')]/following-sibling::td[2]", $row);

            if (preg_match('/(?P<date>.+)\((?P<day>.+)\)/', $DepDate, $matches)) {
                $DepDate = $matches['date'];
            }

            $DepDateTime = $this->http->FindSingleNode(".//td[contains(text(),'Departure')]/following-sibling::td[3]", $row);

            $Class = $this->http->FindSingleNode(".//td[contains(text(),'Departure')]/following-sibling::td[4]", $row);

            $data = $this->http->FindSingleNode(".//td[contains(text(),'Departure')]/following-sibling::td[5]", $row);

            if ($Status == 'Confirmed' && $data != $Status) {
                $Status = $data;
            }

            // Arrival 	INCHEON(ICN) 	25JUN2012(MON) 	11:30 	TRAVEL 	Confirmed
            $ArrName = $this->http->FindSingleNode(".//td[contains(text(),'Arrival')]/following-sibling::td[1]", $row);

            if (preg_match('/(?P<name>.+)\((?P<code>.+)\)/', $ArrName, $matches)) {
                $ArrName = $matches['name'];
                $ArrCode = $matches['code'];
            } else {
                $ArrCode = TRIP_CODE_UNKNOWN;
            }

            $ArrDate = $this->http->FindSingleNode(".//td[contains(text(),'Arrival')]/following-sibling::td[2]", $row);

            if (preg_match('/(?P<date>.+)\((?P<day>.+)\)/', $ArrDate, $matches)) {
                $ArrDate = $matches['date'];
            }

            $ArrDateTime = $this->http->FindSingleNode(".//td[contains(text(),'Arrival')]/following-sibling::td[3]", $row);

            $segment['FlightNumber'] = trim($FlightNumber);
            $segment['AirlineName'] = trim($AirlineName);
            $segment['Cabin'] = trim($Class);
            $segment['DepCode'] = trim($DepCode);
            $segment['DepName'] = trim($DepName);
            $segment['DepDate'] = strtotime($DepDate . ' ' . $DepDateTime);
            $segment['ArrCode'] = trim($ArrCode);
            $segment['ArrName'] = trim($ArrName);
            $segment['ArrDate'] = strtotime($ArrDate . ' ' . $ArrDateTime);

            // others:  Aircraft TraveledMiles Cabin BookingClass Seats Duration Meal Smoking Stops

            $segments[] = $segment;
        }

        $itineraries['TripSegments'] = $segments;

        $itineraries['Status'] = $Status;

        return $itineraries;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindSingleNode("//*[contains(text(),'receipt document attached')]")) {
            $it = $this->parsePdfMail($parser);
        } else {
            $it = $this->parseHtmlMail($parser);
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => [$it],
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[\.@]flyasiana\.com/i', $from);
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }
}
