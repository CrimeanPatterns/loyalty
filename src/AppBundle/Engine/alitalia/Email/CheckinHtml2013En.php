<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * it-1891310.eml, it-1891313.eml, it-1891315.eml.
 */
class CheckinHtml2013En extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-1891310.eml, alitalia/it-1891313.eml, alitalia/it-1891315.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return cell(["Reservation code", "Record locator"], +1, 0);
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [cell(["Reservation code", "Record locator"], -1, 0)];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departure')]/ancestor::tr[contains(., 'From')][1]/following-sibling::tr[contains(., '/')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("td[3]");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(clear('#/#', node("td[2]") . ',' . node('td[5]'), '-'));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("td[4]");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Web Check-in - Boarding Pass') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'you have successfully completed the web check-in process.') !== false
                || stripos($parser->getHTMLBody(), 'you have successfully completed the web check-in.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
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
