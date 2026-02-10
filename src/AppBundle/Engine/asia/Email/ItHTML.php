<?php

namespace AwardWallet\Engine\asia\Email;

/**
 * it-1751552.eml, it-2569042.eml, it-4975461.eml.
 */
class ItHTML extends \TAccountCheckerExtended
{
    use \DateTimeTools;
    public $mailFiles = "asia/it-2569042.eml, asia/it-4975461.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'onlinebooking-NO-REPLY@cathaypacific.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Cathay Pacific electronic ticket (eTicket)') !== false
                );
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (strpos($parser->getHTMLBody(), 'Booking Reference') !== false && strpos($parser->getHTMLBody(), 'Itinerary') !== false)
                || preg_match('/The\s+following\s+booking\s+is\s+/', $parser->getHTMLBody())
                || preg_match('/La réservation ci-dessous est/', $parser->getHTMLBody());
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cathaypacific.com') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:Booking Reference Number|de dossier de réservation)\s*:\s*([A-Z\d-]+)#ix");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $passengerInfoNodes = xpath('//tr[(contains(., "Frequent Flyer Programme") or contains(., "Programme de fidélisation")) and not(.//tr)]/ancestor::table[1]//tr[contains(., "/")]');

                        $passengers = null;
                        $this->passengersSeats = null;

                        foreach ($passengerInfoNodes as $n) {
                            $currentPassenger = node('td[1]', $n);
                            $passengers[] = $currentPassenger;

                            foreach (splitter('#([A-Z]+[0-9]+/.*)#U', node('td[last()]', $n)) as $seat) {
                                if (preg_match('#([0-9]+)/(.*)#', $seat, $m)) {
                                    $seat = trim($m[2]);
                                    $seat = ($seat != '-') ? re('#\d+\w+#', $seat) : null;
                                    $this->passengersSeats[$m[1]][$currentPassenger] = $seat;
                                }
                            }
                        }

                        return $passengers;
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $xpath = '//tr[(contains(., "Taxes/Fees/Charges") or contains(., "Taxes/Frais/Charges")) and not(.//tr)]/following-sibling::tr[not(contains(., "Total")) and not(contains(., "Montant"))]';
                        $fareDetailsNodes = $this->http->XPath->query($xpath);

                        if ($fareDetailsNodes->length == 1) {
                            $n = $fareDetailsNodes->item(0);
                            $res['BaseFare'] = cost(node('./td[2]', $n));
                            $res['Tax'] = cost(node('./td[3]', $n)) + cost(node('./td[4]', $n));

                            if (preg_match('/([A-Z]{3})\s*([\d,.\s]+)/', node('td[5]', $n), $matches)) {
                                $res['TotalCharge'] = cost($matches[2]);
                                $res['Currency'] = currency($matches[1]);
                            }
                        }

                        return $res;
                    },

                    "EarnedAwards" => function ($text = '', $node = null, $it = null) {
                        $xpath = '(//tr[contains(normalize-space(.), "Miles") and contains(., "Total") and not(.//tr)]/ancestor::table[1]//tr[1])[2]/td[1]';

                        return node($xpath);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#(?:ci-dessous est|following\s+booking is)\s+(confirmée|confirmed)#u");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//tr[(contains(normalize-space(.), "Operated By") or contains(., "Opéré par")) and not(.//tr)]/ancestor::table[1]//tr[contains(., ":")]';
                        $segments = $this->http->XPath->query($xpath);

                        // In some email examples duration is common for two rows and we can't definitely
                        // say what duration which flight have. So in such cases we ignore durations and
                        // set offset for fields following after duration.
                        $tdCount = null;
                        $this->tdCountDiffers = false;

                        foreach ($segments as $s) {
                            $currentTdCount = count(nodes('./td', $s));

                            if ($tdCount === null) {
                                $tdCount = $currentTdCount;
                            } elseif ($currentTdCount != $tdCount) {
                                $this->tdCountDiffers = true;

                                break;
                            }
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $res['AirlineName'] = re('#([A-Z\d]{2})\s*(\d+)#', node('./td[2]'));
                            $res['FlightNumber'] = re(2);

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $date = $this->dateStringToEnglish(str_replace('.', '', node('./td[1]')));
                            $info = node('./td[4]');
                            $code = re('#[A-Z]+#', $info);
                            $time = re('#[0-9]{1,2}:[0-9]{2}#', $info);

                            return ['DepCode' => $code, 'DepDate' => strtotime("$date $time")];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $date = $this->dateStringToEnglish(str_replace('.', '', node('./td[1]')));
                            $info = node('./td[5]');
                            $code = re('#[A-Z]+#', $info);
                            $time = re('#[0-9]{1,2}:[0-9]{2}#', $info);
                            $dayShift = re('#\+([1-9])#', $info);
                            $dateTime = (int) strtotime("$date $time");

                            if ($dayShift) {
                                $dateTime += $dayShift * 24 * 60 * 60;
                            }

                            return ['ArrCode' => $code, 'ArrDate' => $dateTime];
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return node('./td[last() - 1]');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[last()]');

                            if (preg_match('#^(.*)?\s+\((\w)\)#', $subj, $m)) {
                                return [
                                    'Cabin'        => $m[1],
                                    'BookingClass' => $m[2],
                                ];
                            } else {
                                return $subj;
                            }
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            if (!$this->tdCountDiffers) {
                                return node('./td[7]');
                            }
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return (int) node('./td[6]');
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 2;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'fr'];
    }
}
