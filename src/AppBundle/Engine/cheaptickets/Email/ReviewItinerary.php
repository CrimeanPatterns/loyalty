<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class ReviewItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#Please\s+review\s+your\s+itinerary\s+below.*?cheaptickets#i";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#cheaptickets#i";
    public $reProvider = "#cheaptickets#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $upDate = "25.12.2014, 18:49";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "cheaptickets/it-1680342.eml, cheaptickets/it-2295472.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        if (preg_match_all('#Hotel\s+Confirmation\s+Number\s+(.*)#', $text, $m)) {
                            if (count($m[1]) > 1) {
                                $res['ConfirmationNumbers'] = $m[1];
                            }
                            $res['ConfirmationNumber'] = $m[1][0];
                        }

                        return $res;
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $regex = '#Cheap\s+Tickets;?\s+Record\s+Locator\s+[\w\-]+\s+(.*)\s+((?s).*)Phone\s+number:\s+(.*)\s+Map#';

                        if (preg_match($regex, $text, $m)) {
                            return [
                                'HotelName' => nice($m[1]),
                                'Address'   => nice($m[2], ', '),
                                'Phone'     => nice($m[3]),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check-In', 'CheckOut' => 'Check-Out'] as $key => $value) {
                            $subj = cell("${value} Date", +1);

                            if (preg_match('#(\w+\s+\d+\s+\d+)\s+(\d{1,2})\s*(\d{2})\s*(?:am|pm)?#', $subj, $m)) {
                                $subj = $m[1] . ', ' . $m[2] . ':' . $m[3];

                                if (isset($m[4])) {
                                    $subj .= ' ' . $m[4];
                                }
                                $res["${key}Date"] = strtotime($subj);
                            }
                        }

                        return $res;
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Room\s+Reservation\s+Name:\s+(.*)#', $text, $m)) {
                            return $m[1];
                        }
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return cell('Total Number of Guests', +1);
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return cell('Total Number of Rooms', +1);
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        $cp = nodes('//td[contains(., "Cancellation Policy for") and not(.//td)]/*[not(contains(., "Cancellation Policy for"))]');

                        if ($cp) {
                            $cp = implode('', $cp);
                        }

                        return $cp;
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#Room\s+Description\s+(.*)#', $text, $m)) {
                            $subj = array_count_values(nice($m[1]));
                            $res = [];

                            foreach ($subj as $value => $count) {
                                if ($count == 1) {
                                    $res[] = $value;
                                } else {
                                    $res[] = $count . ' * ' . $value;
                                }
                            }

                            return implode('; ', $res);
                        }
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        return cost(node('//tr[contains(., "Room for") and not(.//tr) and ./following-sibling::tr[contains(., "Nights") and contains(., "Guests")]]/td[last()]'));
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Taxes and Fees', +1));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('Amount Charged to Your Card', +1);

                        return ['Total' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        $sa = cell('CheapCash applied', +1);

                        if ($sa) {
                            $sa = preg_replace('#\s*–\s*#', '', $sa);
                        }

                        return $sa;
                    },
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
