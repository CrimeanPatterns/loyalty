<?php

namespace AwardWallet\Engine\airberlin\Email;

class It2310993 extends \TAccountCheckerExtended
{
    public $mailFiles = "airberlin/it-1886705.eml, airberlin/it-2310992.eml, airberlin/it-2310993.eml, airberlin/it-5084102.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'airberlin') !== false && (
                strpos($parser->getHTMLBody(), 'Anzahl der Reisenden:') !== false
                || strpos($parser->getHTMLBody(), 'Number of passengers:') !== false);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'airberlin.com') !== false;
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
                        return re("#(?:Ihre Buchungsnummer|Your booking number)\s*:\s*([A-Z\d-]{5,6})\b#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re("#\n\s*(?:Guten Tag|Good morning),\s*([^\n]*?)!#")];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("(//*[contains(text(), 'Buchungsnummer:') or contains(text(), 'Your booking number:')])[1]/ancestor::tr[1]/following-sibling::tr[not(contains(.,'Reisenden') or contains(.,'passengers:'))]/td[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*([A-Z\d]{2}\s*\d+)$#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#^\s*([^\n]*?)\s+\-\s+([^\n]+)#"),
                                'ArrName' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(uberDateTime(re("#^[^\n]+\s+([^\n]+)#")));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
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
        return ['de', 'en'];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
