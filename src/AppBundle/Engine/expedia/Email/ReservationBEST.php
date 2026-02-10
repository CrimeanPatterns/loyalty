<?php

namespace AwardWallet\Engine\expedia\Email;

class ReservationBEST extends \TAccountCheckerExtended
{
    public $reFrom = "#expedia#";
    public $reProvider = "#expedia#";
    public $rePlain = "#expedia|BEST|B\.E\.S\.T#";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "#expedia#";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "expedia/it-63.eml, expedia/it-64.eml, expedia/it-65.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (!preg_match($this->rePlain, $text)) {
                        // Ignore emails of other types
                        return null;
                    }

                    return xPath('.');
                },

                "(.//node())[1]" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return node('.//text()[contains(., "Your Itinerary Number:")]/following::text()[normalize-space()][1]');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumbers" => function ($text = '', $node = null, $it = null) {
                        return re('##');
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return node('.//img[contains(@src, "star-ratings")]/preceding::text()[normalize-space()][1]');
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('.//text()[contains(., "Check-in:")]/following::text()[normalize-space()][1]'));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(node('.//text()[contains(., "Check-out:")]/following::text()[normalize-space()][1]'));
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return implode(', ', nodes('.//img[contains(@src, "star-ratings")]/following::text()[normalize-space()][position() <= 2]'));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        $data = [];
                        $nodes = nodes('.//text()[contains(normalize-space(.), " Guest:")]');

                        foreach ($nodes as $node) {
                            if (preg_match('#(\S+)?\s*Guest:\s*(.+)#ims', $node, $matches)) {
                                $data['GuestNames'][] = $matches[2];

                                if (!empty($matches[1])) {
                                    $data['ConfirmationNumbers'][] = $matches[1];
                                }
                            }
                        }

                        return $data;
                    },

                    "Guests" => function ($text = '', $node = null, $it = null) {
                        $data = [];

                        if (preg_match('#(\d+)\s*Adults?(?:,\s*(\d+)\s*Children)?#ims', node('(.//text()[contains(., "Adult") or contains(., "Child")])[1]'), $matches)) {
                            $data['Guests'] = $matches[1];

                            if (isset($matches[2])) {
                                $data['Kids'] = $matches[2];
                            }
                        }

                        return $data;
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return node('.//text()[contains(., "Cancellation Policy") and normalize-space(.) = "Cancellation Policy"]/following::text()[normalize-space()][1]');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return node('(.//text()[contains(., "Adult") or contains(., "Child")])[1]/following::text()[contains(., "Room")][1]');
                    },

                    "Taxes" => function ($text = '', $node = null, $it = null) {
                        return cost(node('.//td[.//text()[contains(., "Tax Recovery Charges")] and not(.//td)]/following::text()[normalize-space()][1]'));
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(node('(.//td[.//text()[contains(., "Total Charges")] and not(.//td)]/following::text()[normalize-space()])[1]'));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return node('.//td[.//text()[contains(., "Tax Recovery Charges")] and not(.//td)]/following::text()[normalize-space()][2]');
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
}
