<?php

namespace AwardWallet\Engine\airfrance\Email;

class It1569294 extends \TAccountCheckerExtended
{
    public $reFrom = "#airfrance#";
    public $reProvider = "#airfrance#";
    public $rePlain = "";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "#.*?#";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "airfrance/it-1569294.eml, airfrance/it-1569397.eml, airfrance/it-1571507.eml";
    public $rePDF = "#call\s+your\s+Air\s+FrancE#i";
    public $rePDFRange = "/1";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("application/pdf", "text");
                    $this->date = strtotime($this->parser->getHeader('date'));

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation number\s*:\s*([^\n]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if ($passanger = re("#\n\s*Loyalty Program Number\s+([A-Z\s\d]+)\s+\d+#")) {
                            return [$passanger];
                        }
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Fare amount\s*:\s*(\d[^\n]+)#");
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax/fee/surcharges\s*:\s*([^\n]+)#"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#\s*issue date\s*:\s*(\d+\w{3}\d*)#"), $this->date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $flights = re("#\n\s*Itinerary\s+information\s+(.*?)\s+At check\-in#ims");

                        $boarding = re("#DATE\s+FROM\s+TO\sDEPARTURE\s+GATE\s+BOARDING\s+CLASS\s+SEAT(.*?)\s+(?:Check\s+Terminal\s+and|RETIREZ\s+VOTRE)#ims");

                        preg_match_all("#(?<DepName>[^\n]+\s+-\s+[^\n]+)\s*\n\s*" .
                        "(?<ArrName>[^\n]+\s+-\s+[^\n]+)\s*\n\s*" .
                        "(?<Date>\d+\w+)\s*\n\s*" .
                        "(?<FlightNumber>\d+)\s*\n\s*" .
                        "(?<DepTime>\d+:\d+)\s*\n\s*" .
                        "OK\s*\n\s*" .
                        "\d+K\s*\n\s*" .
                        "(?<DepCode>[A-Z]{3})\s*\n\s*" .
                        "(?<ArrCode>[A-Z]{3})#", $flights, $segments, PREG_SET_ORDER);

                        if (preg_match("#(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*\n\s*" .
                        "\d+\w+\s*\n\s*" .
                        "[^\n]+\s+[A-Z]{3}[^\n]*\s*\n\s*" .
                        "[^\n]+\s*\n\s*" .
                        "(?<DepTime>\d+:\d+)\s*\n\s*" .
                        "\d+:\d+\s*\n\s*" .
                        "(?<BookingClass>\w)\s*\n\s*" .
                        "(?<Seats>\d{1,2}\w)\s*\n\s*" .
                        "OPERATED BY\s+(?<Operator>[^\n]+)#", $boarding, $more)) {
                            foreach ($segments as &$segment) {
                                if ($segment['FlightNumber'] == $more['FlightNumber']) {
                                    $segment = array_merge($segment, $more);
                                }
                            }
                        }

                        return $segments;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data = [];

                            if (stripos($this->text(), 'please call your Air France local contact') !== false) {
                                $data['AirlineName'] = 'AF';
                            }
                            $sets = [
                                "FlightNumber", "AirlineName", "Operator",
                                "DepCode", "DepName",
                                "ArrCode", "ArrName",
                                "Seats", "BookingClass", ];

                            foreach ($sets as $set) {
                                if (isset($text[$set])) {
                                    $data[$set] = $text[$set];
                                }
                            }
                            $date = strtotime(preg_replace("#^(\d+)(\w+)$#", "$1 $2", $text["Date"]), $this->date);
                            $data["DepDate"] = strtotime($text["DepTime"], $date);
                            $data["ArrDate"] = MISSING_DATE;

                            return $data;
                        },
                    ],
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
}
