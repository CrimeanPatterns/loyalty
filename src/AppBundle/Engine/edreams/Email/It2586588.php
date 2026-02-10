<?php

namespace AwardWallet\Engine\edreams\Email;

class It2586588 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?edreams#i', 'us', ''],
    ];
    public $reHtml = [
        ['#eDreams#', 'blank', '-100'],
    ];
    public $rePDF = "";
    public $reSubject = [
        ['#Booking confirmation from eDreams#i', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]edreams#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]edreams#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "21.04.2015, 11:37";
    public $crDate = "29.03.2015, 02:20";
    public $xPath = "";
    public $mailFiles = "edreams/it-2586576.eml, edreams/it-2586582.eml, edreams/it-2586588.eml, edreams/it-2586590.eml, edreams/it-2631694.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xpath("
						//text()[contains(normalize-space(.), 'Pick-up')]/ancestor::div[1] |
						//text()[
							contains(normalize-space(.), 'DESTINATION') or
							contains(normalize-space(.), 'OUTBOUND') or
							contains(normalize-space(.), 'HOMEBOUND')
						]/ancestor::div[2] |
						//text()[contains(normalize-space(.), 'Check-in:')]/ancestor::div[1]
					");
                },

                "#Airline#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Airline booking reference\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveller\s*:\s*([^\n]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Payment')]/following::div[1][contains(.,'FLIGHT')]"));

                        return total(re("#\n\s*Total\s+price[:\s]+([^\n]+)#", $info));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//*[contains(text(), 'Payment')]/following::div[1][contains(.,'FLIGHT')]"));

                        return cost(re("#\n\s*Price[*:\s]+([^\n]+)#", $info));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#^Status\s*:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $rows = xpath("following-sibling::div[1][
								contains(., ':') and
								not(contains(normalize-space(.), 'DESTINATION')) and
								not(contains(normalize-space(.), 'OUTBOUND')) and
								not(contains(normalize-space(.), 'HOMEBOUND'))
						]");

                        if ($rows->length) {
                            return [$node, $rows->item(0)];
                        }

                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                "AirlineName"  => re("#^([A-Z\d]{2})\s*(\d+),.*?\n\s*(\w+)$#s", xpath(".//img[contains(@src, '/airlines/')]/ancestor::*[1]")),
                                "FlightNumber" => re(2),
                                "Cabin"        => re(3),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*(?:HOMEBOUND|OUTBOUND|DESTINATION[\s\d]+)[\s:]+([^\n]+?)\s*\d+:\d+#");

                            if (!$date) {
                                $date = @$this->lastDate;
                            }
                            $this->lastDate = $date;

                            return totime($date . ', ' . uberTime(1));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*(?:HOMEBOUND|OUTBOUND|DESTINATION[\s\d]+)[\s:]+([^\n]+?)\s*\d+:\d+#");

                            if (!$date) {
                                $date = @$this->lastDate;
                            }
                            $this->lastDate = $date;

                            return totime($date . ',' . uberTime(2));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#(\d+h\s+\d+min)#");
                        },
                    ],
                ],

                "#Pick-up#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re("#booking reference\s*:\s*([A-Z\d-]+)#");
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            "PickupDatetime" => totime(uberDateTime(re("#\n\s*Pick-up\s*:\s*([^\n]*?)\s*\-\s*([^\n]+)#"))),
                            "PickupLocation" => re(2),
                        ];
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return [
                            "DropoffDatetime" => totime(uberDateTime(re("#\n\s*Drop-off\s*:\s*([^\n]*?)\s*\-\s*([^\n]+)#"))),
                            "DropoffLocation" => re(2),
                        ];
                    },

                    "RentalCompany" => function ($text = '', $node = null, $it = null) {
                        return re("#Car hire company\s*:\s*([^\n]+)#");
                    },

                    "CarType" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Drop-off\s*:\s*[^\n]+\s+([^\n]+)#");
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Traveller\s*:\s*([^\n]+)#", $this->text());
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#\n\s*Total price\s*:\s*([^\n]+)#"));
                    },

                    "TotalTaxAmount" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Tax/insurance[\s:]+([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#Status\s*:\s*([^\n]+)#");
                    },
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return uniteAirSegments($it);
                },

                "#Check-in:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return reni('Hotel booking reference: (\w+)');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return nice(node('(.//b)[1]'));
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDate(1));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDate(2));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return reni('Check-out: .*? \d{4}
						(.+?) Phone:');
                    },

                    "Phone" => function ($text = '', $node = null, $it = null) {
                        return reni('Phone: ([\d-()\s]+)');
                    },

                    "Fax" => function ($text = '', $node = null, $it = null) {
                        return reni('Fax: ([\d-()\s]+)');
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $name = reni('Traveller: (.+?) \n');

                        return [$name];
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return reni('Cancellation information :? (.+?) \n');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return reni('Room: (.+?) [.]');
                    },

                    "RoomTypeDescription" => function ($text = '', $node = null, $it = null) {
                        return reni('Room: .+? [.] (.+?) [.]');
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total price: (.+?) \n');

                        return total($x, 'Total');
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Status: Confirmed')) {
                            return 'confirmed';
                        }
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
