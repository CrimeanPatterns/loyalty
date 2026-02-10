<?php

namespace AwardWallet\Engine\azul\Email;

class It1617331 extends \TAccountCheckerExtended
{
    public $mailFiles = "azul/it-1617331.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], '@voeazul.') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Itinerario Azul') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'Azul') !== false
                && strpos($parser->getHTMLBody(), 'Código localizador') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@voeazul.') !== false;
    }

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return xPath("//img[contains(@src, 'ida.gif') or contains(@src, 'volta.gif')]/ancestor::table[2]");
                },

                "//*" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Código localizador\s*([^\s]+)#is", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xPath("descendant::b[contains(text(), 'Voo:')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return node('following::b[1]');
                        },
                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return AIRLINE_UNKNOWN;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return node('following::b[2]');
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('following::b[3]');
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return node('following::b[6]');
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('following::b[7]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(str_replace('/', ' ', en(node('preceding::img[1]/following::b[1]') . ' ' . node('following::b[5]'))));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(str_replace('/', ' ', en(node('preceding::img[1]/following::b[1]') . ' ' . node('following::b[9]'))));
                        },
                    ],

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//*[contains(text(), "Passageiros")][ancestor::td[1]/preceding-sibling::td[1]/descendant::font[contains(text(), "IDA")]]/ancestor::table[3]/ancestor::tr[1]/following-sibling::tr[1]/descendant::table[1]/descendant::tr/td[2]/descendant::font[1]');
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return node("//font[contains(text(), 'Taxas:')]/ancestor::td[1]/following::td[1]", null, false, "/([\d+.,]+)/ims");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return node("//*[contains(text(), 'Total da Compra')]/ancestor::tr[1]/following-sibling::tr[1]/td[5]", null, false, "#[\d,.]+#ims");
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return str_replace('R$', 'BRL', node("//*[contains(text(), 'Total da Compra')]/ancestor::tr[1]/following-sibling::tr[1]/td[5]", null, false, "#([^\s]*)\s#ims"));
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
        return ["pt"];
    }
}
