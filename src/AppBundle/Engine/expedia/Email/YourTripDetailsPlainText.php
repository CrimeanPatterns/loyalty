<?php

namespace AwardWallet\Engine\expedia\Email;

class YourTripDetailsPlainText extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+choosing\s+Expedia!\s+We're\s+pleased\s+to\s+provide\s+details\s+of\s+your\s+upcoming\s+trip#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $xPath = "";
    public $mailFiles = "expedia/it-1564859.eml";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('plain', 'text');

                    if (!preg_match($this->rePlain, $text)) {
                        // Ignore emails of other types
                        return null;
                    }

                    return splitter("#(Check\-in with|Hotel[^\d\w\-]{2,})#");
                },

                "#Hotel#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Confirmation.Code:\s*(\d+)#i") .
                            re("#Expedia itinerary number\(s\):[\s\-]+([\d\w\-]+)#", $this->text())
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#^Hotel[^\d\w]{2,}(.*?)Add.a#"),
                            re("#([^\n]+)\s+([\d \(\)\-]+)\s+(?:Rooms|Hotel confirmation code)#s")
                        );
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        if (re("#Check In:#")) {
                            return [
                                'CheckInDate'  => strtotime(implode("/", (array_reverse(explode("/", re("#Check In:\s*([^\n]+)#")))))),
                                'CheckOutDate' => strtotime(implode("/", (array_reverse(explode("/", re("#Check Out:\s*([^\n]+)#")))))),
                            ];
                        } else {
                            return [
                                'CheckInDate'  => strtotime(re("#Check\-in\s*(.*?)Check\-out(.*?)Confirmation#")),
                                'CheckOutDate' => strtotime(re(2)),
                            ];
                        }
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Address:\s*(.*?)Telephone#"),
                            nice(re("#Check.Out:[^\n]+(.*?)\s+\-{5,}\s+Helpful#ms"))
                        );
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re("#Telephone:\s*([\s\d\(\)\-]+)#"),
                            re("#\n\s*([\d \(\)\-]+)\s+(?:Room|Hotel confirmation code)#s")
                        );
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return re("#Main contact:\s*([^\n]+)#", $this->text());
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        return re("#Traveller\(s\):\s*(\d+)#", $this->text());
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re("#Rooms*\(s\).Booked:\s*(\d+)#i");
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return re("#Room type:(.*?)Address:#");
                    },
                ],

                "#Flight Number#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Confirmation Code:\s*([^\s]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $people = re("#Traveler's name\(s\):(.*?)\s+(?:\={3}|Flight)#ms");
                        $names = [];

                        re("#\n\s*\d+\.\s*([^\n]+)#ms", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, $people);

                        return orval(
                            $names,
                            re("#Main contact:\s*([^\n]+)#", $this->text())
                        );
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*Depart\s+[^\n]*?\s*\([A-Z]{3}\))#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight.Number\s*:\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(uberDateTime());
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(uberDateTime(2));
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return orval(
                                re("#\={5,}\s+([^\n]+)\s+Operated By#", $this->text()),
                                trim(re("#Check\-in\s*with(.*?)Confirmation#"))
                            );
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Seats'    => re("#[^\d\w]{2,}(\d+\w|Seat\s+[^\n,]+),\s*([^\n,<]*)\s+Class,\s*([^\n]*?)[^\d\w]{3,}#"),
                                'Cabin'    => re(2),
                                'Aircraft' => re(3),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#[^\w\d]+Duration\s*((?:\d|Hr|Min| )+)#"));
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },
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
