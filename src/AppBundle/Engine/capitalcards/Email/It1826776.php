<?php

namespace AwardWallet\Engine\capitalcards\Email;

use PlancakeEmailParser;

class It1826776 extends \TAccountCheckerExtended
{
    public $mailFiles = "capitalcards/it-1826776.eml, capitalcards/it-2355244.eml, capitalcards/it-2355314.eml";

    private $subjects = [
        'Capital One Travel Reservation',
    ];

    private $detects = [
        'Thank you for booking your travel with Capital One',
    ];

    private $from = '/[@\.]capitalone\.com/i';

    private $prov = 'capitalone';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("//strong[normalize-space(.) =  'Flights' or normalize-space(.) = 'Car']/ancestor::table[1]");
                },

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },

                "#^Flights#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#use reference code ([A-Z\d\-]+)#ix"),
                            clear("#\s#", re("#\n\s*Your Trip ID is\s*:\s*([A-Z\d- ]+)#", $this->text()))
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[1]"),
                            nodes("//*[contains(text(), 'Passengers')]/ancestor::tr[1]/following-sibling::tr[1]/td[1]")
                        );
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            nodes("//*[contains(text(), 'Passenger Name')]/ancestor::tr[1]/following-sibling::tr/td[2]"),
                            nodes("//*[contains(text(), 'Passengers')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]")
                        );
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(cell("Total:", +1, 0, '', null));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Adult:", +1, 0, '', null));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(cell("Taxes + Airline & Agency Fees:", +1, 0, '', null));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#airline has (\w+) the flight#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath(".//*[contains(text(), 'Depart:')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return $this->http->FindSingleNode('descendant::tr[count(td)>=3]/td[last()]/descendant::text()[normalize-space(.)][1]', $node, true, '/.+,\s+Flight\s+(\d+)/');
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return $this->http->FindSingleNode('descendant::tr[count(td)>=3]/td[2]/descendant::text()[normalize-space(.)][1]', $node, true, '/\(([A-Z]{3})\)/');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = orval(
                                uberDate(node("preceding::tr[contains(., 'check-in') or contains(., ' to ')][1]")),
                                uberDate(node("preceding::*[contains(., ' to ')][1]"))
                            );

                            $dep = $date . ',' . $this->http->FindSingleNode('descendant::node()[normalize-space(.)="Depart:"]/following-sibling::text()[normalize-space(.)][1]', $node);
                            $arr = $date . ',' . $this->http->FindSingleNode('descendant::node()[normalize-space(.)="Arrive:"]/following-sibling::text()[normalize-space(.)][1]', $node);

                            return [
                                'DepDate' => strtotime($dep),
                                'ArrDate' => strtotime($arr),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return $this->http->FindSingleNode('descendant::tr[count(td)>=3]/td[2]/descendant::text()[normalize-space(.)][2]', $node, true, '/\(([A-Z]{3})\)/');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                $this->http->FindSingleNode('descendant::tr[count(td)>=3]/td[last()]/descendant::text()[normalize-space(.)][1]', $node, true, '/(.+),\s+Flight/'),
                                node('tbody/tr[1]/td[3]')
                            );
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight\s+\d+\s+\(on\s+([^\)]+)\)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return $this->http->FindSingleNode('descendant::tr[count(td)>=3]/td[last()]/descendant::text()[normalize-space(.)][2]', $node, true, '/(.+)\s+Class/');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#Seats\s*:\s*(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Total Travel Time\s*:\s*([^\n]+)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non\-stop#") ? 0 : null;
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return $this->http->FindSingleNode("preceding::tr[contains(., 'check-in') or contains(., ' to ')][1]", $node, true, "#check\-in\s+code:\s*([A-Z\d\-]+)#i");
                        },
                    ],
                ],

                "#^Car#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation number\s*:\s*([A-Z\d-]+)#");
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Pick[\s-]*up\s+Location\s*:\s*([^\n]+)#i");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Pick[\s\-]*up\s+date\s*:\s*([^\n]+)#i")));
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Drop[\s-]*off\s+Location\s*:\s*([^\n]+)#i");
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Drop[\s\-]*off\s+date\s*:\s*([^\n]+)#i")));
                    },

                    "PickupHours" => function ($text = '', $node = null, $it = null) {
                        return implode(';', nodes(".//*[contains(text(), 'Hours of operation')]/following-sibling::text()"));
                    },

                    "DropoffHours" => function ($text = '', $node = null, $it = null) {
                        return $it['PickupHours'];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return cell("Car type", -1, 0);
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Car\s+type\s*:\s*([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Driver:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Taxes and Fees\s*:\s*([^\n]+)#", $this->text()));
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['subject'], $headers['from'])) {
            if (!preg_match($this->from, $headers['from'])) {
                return false;
            }

            foreach ($this->subjects as $subject) {
                if (false !== stripos($headers['subject'], $subject)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }
}
