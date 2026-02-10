<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class YourReceipt extends \TAccountChecker
{
    public $mailFiles = "dividendmiles/it-2473470.eml, dividendmiles/it-7507322.eml, dividendmiles/it-7559840.eml";
    public $reSubject = [
        "Your Receipt",
    ];
    public $reBody = [
        "Please print this receipt or save the email for your records",
    ];

    protected $reFrom = '#reservations@[\w]{0,5}usairways\.com#i';
    protected $reProvider = '#\busairways\.com#i';

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match($this->reFrom, $headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $subject) {
            if (strpos($headers["subject"], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody as $phrase) {
            if (stripos($body, $phrase) !== false && preg_match($this->reProvider, $body) == 1) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
        $result = $this->ParseEmail();

        if ($this->RefreshData && !empty($result['RecordLocator']) && !empty($result['TripSegments'][0]['DepDate'])) {
        }

        return [
            'emailType'  => "YourReceipt",
            'parsedData' => $result,
        ];
    }

    public function ParseEmail()
    {
        $result = ['Kind' => 'T'];

        // Confirmation number
        $result['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation code:')]", null, true, '#Confirmation code:\s*(.*)#');

        if (empty($result['RecordLocator'])) {
            $ConfirmationNumbers = $this->http->FindNodes('//div[contains(text(), "Confirmation code:")]/following-sibling::node()[1 and self::div]//tr[count(td) > 1]/td[1]');
            //*[contains(text(), "Confirmation code:")]/ancestor::div[1]/following-sibling::node()[1 and self::div]//table[1]//tr[count(td) > 1]/td[1]
            if (empty($ConfirmationNumbers)) {
                $ConfirmationNumbers = $this->http->FindNodes('//*[contains(text(), "Confirmation code:")]/ancestor::div[1]/following-sibling::node()[1 and self::div]//table[1]//tr[count(td) > 1]/td[1]');
            }

            if (empty($ConfirmationNumbers)) {
                return null;
            }

            $result['RecordLocator'] = $ConfirmationNumbers[0];

            for ($i = 0; $i < (count($ConfirmationNumbers) / 2); $i++) {
                if (isset($ConfirmationNumbers[$i * 2 + 1])) {
                    $result['ConfirmationNumbers'][] = $ConfirmationNumbers[$i * 2] . ' - ' . $ConfirmationNumbers[$i * 2 + 1];
                } else {
                    $result['ConfirmationNumbers'][] = $ConfirmationNumbers[$i * 2];
                }
            }
            $result['ConfirmationNumbers'] = implode(', ', $result['ConfirmationNumbers']);
        }

        $result['Passengers'] = $this->http->FindNodes("//*[normalize-space(text()) = 'Choice Seats']/ancestor-or-self::tr[1]/following-sibling::tr[not(contains(., 'total'))]/td[string-length(normalize-space(.))>3][1]");

        if (empty($result['Passengers'])) {
            $result['Passengers'] = $this->http->FindNodes("//*[normalize-space(text()) = 'PreferredAccess']/ancestor-or-self::tr[1]/following-sibling::tr[not(contains(., 'total'))]/td[string-length(normalize-space(.))>3][1]");
        }

        $segments = [];
        $baseDataRowNodes = $this->http->XPath->query('//*[contains(text(), "Return:") or contains(text(), "Depart:")]/ancestor::tr[1]');

        foreach ($baseDataRowNodes as $baseDataRowNode) {
            $baseData['Date'] = $this->http->FindSingleNode('.//*[contains(text(), "Date:")]/ancestor-or-self::td[1]', $baseDataRowNode, true, '/Date:\s+(.*)/ims');

            if (empty($baseData['Date'])) {
                $baseData['Date'] = $this->http->FindSingleNode('./following-sibling::tr[1]//*[contains(text(), "Date:")]/ancestor-or-self::td[1]', $baseDataRowNode, true, '/Date:\s+(.*)/ims');
            }
            $tripSegments = [];
            $segment = [];
            $dataRowNodes = $this->http->XPath->query('./following-sibling::tr[not(.//*[contains(text(), "Travel time")])]', $baseDataRowNode);

            foreach ($dataRowNodes as $dataRowNode) {
                if (empty(trim($dataRowNode->nodeValue))) {
                    continue;
                }

                if (preg_match('#Operated by\s(?:(?:(.+) dba (.+))|(.+))#', $dataRowNode->nodeValue, $m)) {
                    if (isset($m[3])) {
                        $tripSegments[count($tripSegments) - 1]['Operator'] = trim($m[3]);
                    } elseif (isset($m[1]) && isset($m[2])) {
                        $tripSegments[count($tripSegments) - 1]['AirlineName'] = trim($m[2]);
                        $tripSegments[count($tripSegments) - 1]['Operator'] = trim($m[1]);
                    }

                    continue;
                }
                // set data from baseRowData at last segment in series
                if ($this->http->XPath->query('.//*[contains(text(), "Depart:") or contains(text(), "Return:")]', $dataRowNode)->length > 0) {
                    break;
                }

                // handle stops
                if ($stopData = $this->http->FindSingleNode('.//*[contains(text(), "Stop:")]', $dataRowNode)) {
                    continue;
                }
                // handle time shifts
                if ($shifts = $this->http->FindNodes('.//*[contains(text(), "next day")]', $dataRowNode)) {
                    continue;
                }
                // handle segment data
                if ($this->http->XPath->query('./td', $dataRowNode)->length > 6) {
                    $segment['FlightNumber'] = $this->http->FindSingleNode('./td[1]', $dataRowNode);

                    foreach ([['Dep', 3], ['Arr', 4]] as $vars) {
                        [$Dep, $index] = $vars;
                        $time = $this->http->FindSingleNode("./td[{$index}]", $dataRowNode);
                        preg_match('/([\d:]+\s*[apm]+)\s+([A-Z]{3})$/ims', $time, $matches);
                        $segment["{$Dep}Date"] = strtotime($baseData['Date'] . ' ' . ArrayVal($matches, 1), $this->date);
                        $segment["{$Dep}Code"] = ArrayVal($matches, 2);
                    }

                    $segment['Duration'] = $this->http->FindSingleNode("./td[5]", $dataRowNode);
                    $segment['Meal'] = $this->http->FindSingleNode("./td[6]", $dataRowNode);
                    $segment['Aircraft'] = $this->http->FindSingleNode("./td[7]", $dataRowNode);
                    $Cabin = $this->http->FindSingleNode("./td[8]", $dataRowNode);

                    if (preg_match("#(.+)\s*(?:\(([A-Z]{1,2})\))#", $Cabin, $m)) {
                        $segment['Cabin'] = trim($m[1]);

                        if (isset($m[2])) {
                            $segment['BookingClass'] = $m[2];
                        }
                    }
                    $Seats = $this->http->FindSingleNode("./td[9]", $dataRowNode, true, "#\d+[A-Z]#");

                    if (!empty($Seats)) {
                        $segment['Seats'][] = $this->http->FindSingleNode("./td[9]", $dataRowNode, true, "#\d+[A-Z]#");
                    }

                    $tripSegments[] = $segment;
                    $segment = [];
                }
            }
            unset($segment);

            $segments = array_merge($segments, $tripSegments);
        }

        if (count($segments) > 0) {
            $result['TripSegments'] = $segments;
        }

        return ["Itineraries" => [$result]];
    }
}
