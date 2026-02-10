<?php

namespace AwardWallet\Engine\british\Email;

class ETicketPlain extends \TAccountCheckerExtended
{
    public $mailFiles = "british/it-1647690.eml, british/it-1664392.eml, british/it-1666831.eml, british/it-1675222.eml, british/it-1698385.eml, british/it-8668103.eml";

    private $detectBody = [
        'Thank you for booking with British Airways. Your booking',
        'Thank you for booking your forthcoming trip with British Airways',
    ];

    private $travelers = [];
    private $bookingReference = '';
    private $total = '';
    private $fullText = '';

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (empty($text)) {
                        $text = $this->setDocument('plain');
                    }

                    $this->travelers = explode("\n", re('#Passenger\(s\):\s+(.*)\n\n#sU'));
                    $this->bookingReference = re('/booking\s+reference:\s+([-A-Z\d]{5,})/');
                    $this->total = re('#Payment:\s+(.*)\n#');
                    $this->fullText = $text;

                    foreach ($this->travelers as &$t) {
                        $t = nice($t);
                    }

                    if (preg_match_all('/(?:Hotel|Flight\s+number|Car|Transfer):.*\n\n/sU', $text, $m)) {
                        return $m[0];
                    }
                },

                "#Hotel:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'R';
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $result = [];

                        if (preg_match('/^[>\s]*Hotel:\s*([^,]+),\s*(.+)Room:/ims', $text, $matches)) {
                            $result['HotelName'] = $matches[1];
                            $result['Address'] = preg_replace('/\s+/', ' ', $matches[2]);
                        }

                        if (preg_match('/^[>\s]*Room:\s*(.+)$/im', $text, $matches)) {
                            $result['RoomType'] = $matches[1];
                        }

                        if (preg_match('/^[>\s]*Check-in:\s*(\d{1,2}\s*\w{3,}\s*\d{4})/im', $text, $matches)) {
                            $result['CheckInDate'] = strtotime($matches[1]);
                        }

                        if (preg_match('/^[>\s]*Check-out:\s*(\d{1,2}\s*\w{3,}\s*\d{4})/im', $text, $matches)) {
                            $result['CheckOutDate'] = strtotime($matches[1]);
                        }

                        if (preg_match('/^[>\s]*Price:\s*([A-Z]{3})\s+([,.\d]+)/im', $text, $matches)) {
                            $result['Currency'] = $matches[1];
                            $result['Total'] = $this->normalizePrice($matches[2]);
                        }

                        return $result;
                    },
                ],

                "#Car:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'L';
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $res = [];
                        $arr = ['Pickup' => 'Pick-up', 'Dropoff' => 'Drop-off'];

                        foreach ($arr as $key => $value) {
                            $regex = '#';
                            $regex .= $value . ':\s+';
                            $regex .= '(?P<' . $key . 'Datetime>.*?),\s+';
                            $regex .= '(?P<RentalCompany>.*?),\s+';
                            $regex .= '(?P<' . $key . 'Location>.*)';

                            if ($key == 'Pickup') {
                                $regex .= '\s+' . $arr['Dropoff'];
                            }
                            $regex .= '#s';

                            if (preg_match($regex, $text, $matches)) {
                                $res[$key . 'Datetime'] = strtotime($matches[$key . 'Datetime']);
                                $res[$key . 'Location'] = nice($matches[$key . 'Location']);

                                if (!isset($res['RentalCompany'])) {
                                    $res['RentalCompany'] = $matches['RentalCompany'];
                                } elseif ($res['RentalCompany'] !== $matches['RentalCompany']) {
                                    $res['RentalCompany'] .= ', ' . $matches['RentalCompany'];
                                }
                            }
                        }

                        return $res;
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re('#Car:\s+(.*)#');
                    },
                ],

                "#Flight\s+number:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return $this->bookingReference;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Bonus\s+Avios\s+\(awarded\s+after\s+travel\):\s+(.*)#', $this->fullText);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return ['AirlineName' => re('#Flight\s+number:\s+(\w+?)(\d+)#'), 'FlightNumber' => re(2)];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $from = re('/From:\s+(.*)/');

                            if (preg_match('/^(.+)[,\s]+Terminal\s+(\w+)\s*$/i', $from, $matches)) {
                                return [
                                    'DepName'           => $matches[1],
                                    'DepartureTerminal' => $matches[2],
                                ];
                            } else {
                                return $from;
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re('#Depart:\s+(.*)#'));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $to = re('/To:\s+(.*)/');

                            if (preg_match('/^(.+)[,\s]+Terminal\s+(\w+)\s*$/i', $to, $matches)) {
                                return [
                                    'ArrName'         => $matches[1],
                                    'ArrivalTerminal' => $matches[2],
                                ];
                            } else {
                                return $to;
                            }
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re('#Arrive:\s+(.*)#'));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Cabin:\s+(.*)#');
                        },

                        "Operator" => function ($text = '', $node = null, $it = null) {
                            return re('/^[>\s]*Operated\s+by:\s*(.+)$/im');
                        },
                    ],

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/^[>\s]*Flight\s+Total:\s*([A-Z]{3})\s+([,.\d]+)/im', $this->fullText, $matches)) {
                            return [
                                'Currency'    => $matches[1],
                                'TotalCharge' => $this->normalizePrice($matches[2]),
                            ];
                        }

                        return null;
                    },
                ],

                "#Transfer:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'B';
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travelers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            if (isset($this->transferIndex)) {
                                $this->transferIndex++;
                            } else {
                                $this->transferIndex = 0;
                            }
                            $regex = '/Transfer:\s+.+\s+FROM\s+(.*)\s+TO\s+(.*)\s+OR\s+/';

                            if ($this->transferIndex === 0) {
                                $from = re($regex);
                                $to = re(2);
                            } elseif ($this->transferIndex === 1) {
                                $from = re($regex);
                                $to = re(2);
                            }

                            return ['DepName' => $from, 'ArrName' => $to];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = strtotime(re('#Date:\s+(.*)#'));

                            return ['DepDate' => $date, 'ArrDate' => $date];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) === 1) {
                        $total = cost($this->total);
                        $currency = currency($this->total);

                        switch ($itNew['Kind']) {
                            case 'T':
                            case 'L':
                                $itNew['TotalCharge'] = $total;
                                $itNew['Currency'] = $currency;

                                break;

                            case 'R':
                                $itNew['Total'] = $total;
                                $itNew['Currency'] = $currency;

                                break;
                        }
                    }

                    return $itNew;
                },
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = $parser->getHTMLBody();
        }

        foreach ($this->detectBody as $phrase) {
            if (stripos($textBody, $phrase) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'British Airways') !== false
            || stripos($from, '@email.ba.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/^BA (e-ticket receipt|balance reminder) [A-Z\d]{5,}/', $headers['subject']);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        if (preg_match('/^[>\s]*Booking\s+total:\s*([^\d]+)\s+([,.\d]+)/im', $this->fullText, $matches)) {
            $result['parsedData']['TotalCharge']['Currency'] = $matches[1];
            $result['parsedData']['TotalCharge']['Amount'] = $this->normalizePrice($matches[2]);
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);			// 11 507.00	->	11507.00
        $string = preg_replace('/[,.](\d{3})/', '$1', $string);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $string = preg_replace('/,(\d{2})$/', '.$1', $string);	// 18800,00		->	18800.00

        return $string;
    }
}
