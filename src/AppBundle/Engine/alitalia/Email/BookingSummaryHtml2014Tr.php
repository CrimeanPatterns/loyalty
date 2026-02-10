<?php

namespace AwardWallet\Engine\alitalia\Email;

class BookingSummaryHtml2014Tr extends \TAccountCheckerExtended
{
    use \DateTimeTools;
    public $mailFiles = "alitalia/it-4290922.eml";

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
                        return cell("REZERVASYON KODU (PNR)", +1, 0);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Bilet numarası')]/ancestor::tr[1]/td[1]");
                    },

                    "TicketNumbers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Bilet numarası')]", null, '/\d+$/');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'terminal ')]/ancestor::tr[1]/preceding-sibling::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[string-length(normalize-space(.))>1][1]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return node("td[3]", $node, true, "#^.*?\s*,\s*([A-Z]{3})#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("td[3]", $node, true, "#^(.*?)\s*,\s*[A-Z]{3}#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = $this->dateStringToEnglish(node("preceding::tr[contains(.,'-')][1]/td[last()]"));

                            return totime($date . ',' . node("td[2]"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return node("td[5]", $node, true, "#^.*?\s*,\s*([A-Z]{3})#");
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("td[5]", $node, true, "#^(.*?)\s*,\s*[A-Z]{3}#");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = $this->dateStringToEnglish(node("preceding::tr[contains(.,'-')][1]/td[last()]"));

                            return totime($date . ',' . node("td[4]"));
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.it') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Rezervasyon özeti') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'REZERVASYON KODU (PNR)') !== false
                && stripos($parser->getHTMLBody(), 'Bilet numarası:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.it') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['tr'];
    }
}
