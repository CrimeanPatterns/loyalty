<?php

namespace AwardWallet\Engine\amextravel\Email;

class It2768058 extends \TAccountCheckerExtended
{
    public $mailFiles = "amextravel/it-2768058.eml, amextravel/it-1860505.eml, amextravel/it-2503558.eml, amextravel/it-1414092.eml, amextravel/it-18.eml, amextravel/it-6683579.eml";

    public $processors = [];

    private $year = '';

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            'AIR' => function (&$itineraries) {
                // FLIGHTS

                $it = ['Kind' => 'T'];
                $it['RecordLocator'] = $this->http->FindSingleNode('//text()[contains(.,"American Express Global Business Travel Locator") or contains(.,"AE Global Business Travel Locator") or contains(.,"American Express Locator")]/following::text()[normalize-space(.)][1]');
                $segments = [];
                $segmentsNodes = $this->http->XPath->query('//tr[td[contains(.,"AIR -")]]
					/following-sibling::tr[1][.//a[contains(@href,"Forecast")]]
					/following-sibling::tr[position()=1 or position()=2][td[
						contains(.,"From:") and
						contains(.,"To:")
				]]');

                foreach ($segmentsNodes as $segmentNode) {
                    $segment = [];

                    if (preg_match('/(.+)\s+Flight\s+(\d+)(?:\s+(.+))?/ims', $this->http->FindSingleNode('./preceding-sibling::tr[not(.//td[contains(.,"Operated by")])][1]', $segmentNode), $matches)) {
                        if (!empty($matches[3])) {
                            $segment['Cabin'] = $matches[3];
                        }
                        $segment['FlightNumber'] = $matches[2];
                        $segment['AirlineName'] = $matches[1];
                    }

                    if ($operatedBy = $this->http->FindSingleNode('./preceding-sibling::tr[contains(.,"Operated by") and position()<5]', $segmentNode, true, '/Operated\s+by\s+(.+)/ims')) {
                        $segment['Operator'] = $operatedBy;
                    }
                    $segment['Status'] = $this->http->FindSingleNode('.//td[contains(.,"Status:") and not(.//td)]/following-sibling::td[1]', $segmentNode);
                    $segment['Duration'] = $this->http->FindSingleNode('.//td[contains(.,"Duration:") and not(.//td)]/following-sibling::td[1]', $segmentNode);
                    $segment['Seats'] = implode(', ', array_filter($this->http->FindNodes('.//td[contains(.,"Seats:") and not(.//td)]/following-sibling::td[1]/descendant::text()', $segmentNode, '/(\d+\s*\S)\s+/ims'), 'strlen'));
                    $segment['Aircraft'] = $this->http->FindSingleNode('.//td[contains(.,"Equipment:") and not(.//td)]/following-sibling::td[1]', $segmentNode);

                    foreach ([['From', 'Departure', 'Dep'], ['To', 'Arrival', 'Arr']] as $keys) {
                        [$From, $Departure, $Dep] = $keys;
                        $segment["{$Dep}Name"] = $this->http->FindSingleNode(".//td[contains(.,'{$From}:') and not(.//td)]/following-sibling::td[1]", $segmentNode);
                        $s = implode(' ', array_reverse(explode(',', $this->http->FindSingleNode("(.//td[contains(.,'{$From}:')]/following::td)[3]/descendant::text()[1]", $segmentNode))));

                        if (preg_match('/(\w+\s+\d+)\s+\w+\s+(\d+:\d+\s*(?:[ap]m)?)/i', $s, $m)) {
                            $s = $m[1] . ', ' . $this->year . ', ' . $m[2];
                        }
                        $segment["{$Dep}Date"] = strtotime($s);
                        $terminal = $this->http->FindSingleNode("(.//td[contains(.,'{$From}:')]/following::td)[3]/descendant::text()[position()>1 and normalize-space(.)][last()]", $segmentNode);

                        if (preg_match('/^\s*([\w\s]*Terminal[\w\s]*)\s*$/i', $terminal, $terminalMatches)) {
                            $segment["{$Departure}Terminal"] = $terminalMatches[1];
                        }
                        $segment["{$Dep}Code"] = TRIP_CODE_UNKNOWN;
                    }
                    $segments[] = $segment;
                }

                if (!empty($segments)) {
                    $it['TripSegments'] = $segments;
                }
                $accountNumbers = [];
                $passengerNodes = $this->http->XPath->query('//td[contains(.,"Passengers")]
					/following-sibling::td[1][contains(.,"Reference #")]
					/following-sibling::td[1][contains(.,"Frequent Flyer #")]
					/ancestor::tr[1]
					/following-sibling::tr');

                foreach ($passengerNodes as $passengerNode) {
                    if ($passenger = $this->http->FindSingleNode('./td[1]', $passengerNode)) {
                        $it['Passengers'][] = $passenger;
                    }

                    if ($accountNumber = $this->http->FindSingleNode('./td[3]', $passengerNode)) {
                        $accountNumbers[] = $accountNumber;
                    }
                }

                if ($accountNumbers) {
                    $it['AccountNumbers'] = array_values(array_unique(array_filter(array_map(function ($s) {return trim($s, ' @'); }, explode(',', implode(',', $accountNumbers))))));
                }
                $itineraries[] = $it;
            },

            'HOTEL' => function (&$itineraries) {
                // HOTELS

                $segmentsNodes = $this->http->XPath->query("//tr[contains(.,'HOTEL -')]/following-sibling::tr[2][contains(.,'Address:')]");

                foreach ($segmentsNodes as $root) {
                    $it = [];
                    $it['Kind'] = 'R';

                    $it['CheckInDate'] = strtotime($this->http->FindSingleNode("./preceding-sibling::tr[contains(.,'HOTEL -')][1]", $root, true, "#^[^-]+-\s+(.+)#") . ' ' . $this->year);

                    $it['HotelName'] = $this->http->FindSingleNode('./preceding-sibling::tr[./preceding-sibling::tr[contains(.,"HOTEL -")] and position()<5 and string-length()>1][1]', $root);

                    $addressTexts = $this->http->FindNodes(".//*[contains(text(),'Address:')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]", $root);

                    if (!empty($addressTexts[0])) {
                        $it['Address'] = implode(', ', $addressTexts);
                    }

                    $it['Phone'] = $this->http->FindSingleNode(".//*[contains(text(),'Telephone:')]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^[-.\d\s]+$/');

                    $it['Fax'] = $this->http->FindSingleNode(".//*[contains(text(),'Fax:')]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^[-.\d\s]+$/');

                    $it['CancellationPolicy'] = $this->http->FindSingleNode(".//*[contains(text(),'Cancellation Policy:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $it['2ChainName'] = $this->http->FindSingleNode(".//*[contains(text(),'Chain:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $it['CheckOutDate'] = strtotime($this->http->FindSingleNode(".//td[contains(.,'Check out:')]/following-sibling::td[1]", $root) . ' ' . $this->year);

                    $it['Rate'] = $this->http->FindSingleNode(".//*[contains(text(),'Rate:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $it['ConfirmationNumber'] = str_replace(' ', '', $this->http->FindSingleNode(".//*[contains(text(),'Confirmation:')]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^[-A-Z\d\s]+$/'));

                    $it['Status'] = $this->http->FindSingleNode(".//*[contains(text(),'Status:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $it['RoomTypeDescription'] = $this->http->FindSingleNode("./following-sibling::tr[1]//td[contains(.,'Supplemental Information:')]/following-sibling::td[1]", $root);

                    $name = $this->http->FindSingleNode("./following-sibling::tr[1]//td[contains(.,'Name:')]/following-sibling::td[1]", $root);

                    if ($name) {
                        $it['GuestNames'] = [$name];
                    }

                    $itineraries[] = $it;
                }
            },

            'CAR' => function (&$itineraries) {
                // CARS

                $patterns = [
                    'locationDate' => '/(.+)\s+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*,\s*[^,\d\s]{2,}\s*,\s*([^,\d\s]{3,}\s+\d{1,2})$/i',
                ];

                $segmentsNodes = $this->http->XPath->query("//tr[contains(.,'CAR -')]/following-sibling::tr[2][contains(.,'Pick up:')]");

                foreach ($segmentsNodes as $root) {
                    $it = [];
                    $it['Kind'] = 'L';

                    $it['RentalCompany'] = $this->http->FindSingleNode('./preceding-sibling::tr[./preceding-sibling::tr[contains(.,"CAR -")] and position()<5 and string-length()>1][1]', $root);

                    $pickup = implode(' ', $this->http->FindNodes('.//*[contains(text(),"Pick up:")]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]', $root));

                    if (preg_match($patterns['locationDate'], $pickup, $matches)) {
                        $it['PickupLocation'] = $matches[1];
                        $it['PickupDatetime'] = strtotime($matches[3] . ' ' . $this->year . ', ' . $matches[2]);
                    }

                    $dropoff = implode(' ', $this->http->FindNodes('.//*[contains(text(),"Drop Off:")]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]', $root));

                    if (preg_match($patterns['locationDate'], $dropoff, $matches)) {
                        $it['DropoffLocation'] = $matches[1];
                        $it['DropoffDatetime'] = strtotime($matches[3] . ' ' . $this->year . ', ' . $matches[2]);
                    }

                    $it['PickupPhone'] = $this->http->FindSingleNode(".//*[contains(text(),'Telephone:')]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^[-.\d\s]+$/');

                    $it['CarType'] = $this->http->FindSingleNode(".//*[contains(text(),'Type:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $payment = $this->http->FindSingleNode(".//*[contains(text(),'Dropoff Rate:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    if (preg_match('/^([A-Z]{3})\s+([,.\d\s]+)/', $payment, $matches)) {
                        $it['Currency'] = $matches[1];
                        $it['TotalCharge'] = $this->normalizePrice($matches[2]);
                    }

                    $it['Number'] = str_replace(' ', '', $this->http->FindSingleNode(".//*[contains(text(),'Confirmation:')]/ancestor::td[1]/following-sibling::td[1]", $root, true, '/^[-A-Z\d\s]+$/'));

                    $clientID = $this->http->FindSingleNode(".//*[contains(text(),'Client ID:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    if ($clientID) {
                        $it['AccountNumbers'] = [trim($clientID, " @")];
                    }

                    $it['Status'] = $this->http->FindSingleNode(".//*[contains(text(),'Status:')]/ancestor::td[1]/following-sibling::td[1]", $root);

                    $it['RenterName'] = $this->http->FindSingleNode("./following-sibling::tr[1]//td[contains(.,'Name:')]/following-sibling::td[1]", $root);

                    $itineraries[] = $it;
                }
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@AEXP.COM') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], 'AMERICANEXPRESSGLOBALBUSINESSTRAVEL.NOREPLY@AEXP.COM') !== false) {
            return true;
        }

        if (stripos($headers['subject'], 'American Express Global Business Travel') === false) {
            return false;
        }

        if (stripos($headers['subject'], 'schedule') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//node()[contains(normalize-space(.),"American Express Global Business Travel Locator") or contains(normalize-space(.),"AE Global Business Travel Locator") or contains(normalize-space(.),"American Express Locator")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = re('/\d{4}/i', $parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re) !== false) {
                $processor($itineraries);
            }
        }

        $result = [
            'emailType'  => 'ItinerarySchedule',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    protected function normalizePrice($string)
    {
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }
}
