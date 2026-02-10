<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1631092 extends \TAccountCheckerExtended
{
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $rePlain = "#\n[>\s*]*De[:\s]*[^\n]*?amadeus#i";
    public $rePlainRange = "2000";
    public $typesCount = "1";
    public $langSupported = "fr";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1631092.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*(\w+\s+\d+\s+\w+\s+\d{4}\s+VOL\s*\-)#");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*REFERENCE DE LA RESERVATION AERIENNE\s*:\s*([\dA-Z\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*PASSAGER\(S\)\s*:\s*([^\n]+)#", $this->text());
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*ETAT DE LA RESERVATION\s*:\s*([^\n]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(en(re("#\n\s*DATE D'ENVOI DE L'ITINERAIRE\s*:\s*([^\n]+)#", $this->text())));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*VOL\s*:\s*([A-Z\d]{2})\s*(\d+)#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(uberDate());

                            re("#\n\s*DEPART\s*:\s*([^\n]*?)\s*\-\s*([^\n]+)#");
                            $depDate = en(uberDateTime(re(1)), 'fr');
                            $depName = re(2);

                            re("#\n\s*ARRIVEE\s*:\s*([^\n]*?)\s*\-\s*([^\n]+)#");
                            $arrDate = en(uberDateTime(re(1)), 'fr');
                            $arrName = re(2);

                            correctDates($depDate, $arrDate, $date);

                            return [
                                'ArrName' => $arrName,
                                'DepName' => $depName,
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*EQUIPEMENT\s*:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\n\s*CLASSE\s*:\s*([^\n]*?)\s+\(([A-Z])\)#"),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*DUREE\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*REPAS\s*:\s*([^\n]+)#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*NON FUMEUR#") ? false : null;
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
        return ["fr"];
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
