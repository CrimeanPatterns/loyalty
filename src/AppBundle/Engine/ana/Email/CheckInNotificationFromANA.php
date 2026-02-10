<?php

namespace AwardWallet\Engine\ana\Email;

class CheckInNotificationFromANA extends \TAccountCheckerExtended
{
    public $reSubject = "#Check-in Notification from ANA#i";
    public $reFrom = "#(?:anaintrsv@121\.ana\.co\.jp|noreply@amadeus.com)#i";
    public $reProvider = "#(?:ana\.co\.jp|noreply@amadeus.com)#i";
    public $xPath = "";
    public $mailFiles = "ana/it-1928849.eml, ana/it-38916272.eml";
    public $pdfRequired = "0";

    private $date = 0;

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $subj = $this->parser->getHtmlBody();
                    $subj = preg_replace('#[&lt;<](Reservation\s+number|Passenger\s+Name)[&gt;>]#i', '${1}', $subj);
                    $text = text($subj);

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Reservation\s+number|Buchungsnummer|予約番号)\s+(?:\([^\)]*\)\s+)?([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#(?:Passenger\s+Name|Passagiername|搭乗者名)\s+(.*)#i')];
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return [re('#ETKT\s+No\.:[ ]+(\d{10,})\b#')];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Flight:\s+(\w{2})(\d+)\s+(\w{3})\s+-\s+(\w{3})#i', $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'DepCode'      => $m[3],
                                    'ArrCode'      => $m[4],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = strtotime(preg_replace('#(\d+)(\w+)#i', '$1 $2', re('#Date:\s+(\d+\w+)#i')));
                            $time = re("#Schedule Time:\s*(\d+:\d+([ ]*[ap]m)?)#i");

                            if (!empty($date) && !empty($time)) {
                                return strtotime($time, $date);
                            }

                            return MISSING_DATE;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#Seat\s+No\.:\s+\((\w)\)\s+(\d+\w)#i', $text, $m)) {
                                return [
                                    'BookingClass' => $m[1],
                                    'Seats'        => $m[2],
                                ];
                            }
                        },
                    ],
                ],
            ],
        ];
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('Date'));
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (strpos($parser->getHTMLBody(), 'Boarding Pass') !== false
            && (mb_strpos($parser->getHTMLBody(), 'Thank you for your continued support of ANA') !== false
                || mb_strpos($parser->getHTMLBody(), 'いつもANAをご利用いただきありがとうございます') !== false)) {
            return true;
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    public static function getEmailLanguages()
    {
        return ["en", "ja", "de"];
    }
}
