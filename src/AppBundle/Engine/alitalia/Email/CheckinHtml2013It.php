<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * it-4202242.eml.
 */
class CheckinHtml2013It extends \TAccountCheckerExtended
{
    public $mailFiles = "alitalia/it-4202242.eml";

    private $i = -1;
    private $arrDate = [];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $pdfText = text($this->getDocument('application/pdf', 'text'));

                    if (preg_match_all('/\s+(\d{1,2}.\d{1,2})\s+/', $pdfText, $matches)) {
                        $this->arrDate = array_values(array_filter($matches[1], function ($val, $key) {
                            return $key & 1;
                        }, ARRAY_FILTER_USE_BOTH));
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        if (preg_match('/Codice prenotazione\s+([A-Z\d]{5,6})/', $text, $matches)) {
                            return $matches[1];
                        }
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return preg_split('/\n+/', cell("Codice prenotazione", -1, 0), -1, PREG_SPLIT_NO_EMPTY);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Volo')]/ancestor::tr[contains(., 'Partenza')][1]/following-sibling::tr[contains(., '/')]");
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
                            // TODO: tell me the best solution?
                            return totime(clear('#/#', node("td[2]") . ',' . $this->arrDate[++$this->i], '-'));
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Riepilogo check-in') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Se non hai la carta d\'imbarco, ritirala in aeroporto alle self') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }
}
