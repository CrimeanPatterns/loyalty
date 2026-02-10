<?php

namespace AwardWallet\Engine\aerolineas\Email;

class It1712483 extends \TAccountCheckerExtended
{
    public $reBody = '/Gracias\s*por\s*elegir\s*Aerolíneas\s*Argentinas/i';
    public $reFrom = 'aerolineas';
    public $reProvider = '@aerolineas.com';
    public $typesCount = '1';
    public $langSupported = 'es';
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "aerolineas/it-1712483.eml, aerolineas/it-4020141.eml, aerolineas/it-4408820.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Código de reservación\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return 'T';
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Pasajero(s):')]/ancestor-or-self::tr[1]/following-sibling::tr[not(contains(.,'DOCUMENT'))]/td[1]");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Llegada')]/ancestor::tr[3]/following-sibling::tr//tr[contains(.,':')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node('td[4]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return clear("#\s*\d+:\d+\s*$#", node('td[2]'));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = en(node('td[1]', $node, true, "#(.*?)(?:\s+-|$)#"));

                            $dep = $date . ', ' . uberTime(node('td[2]'));
                            $arr = $date . ', ' . uberTime(node('td[3]'));

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return clear("#\s*\d+:\d+\s*$#", node('td[3]'));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $a = nodes("td[5]//text()");

                            return end($a);
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return preg_match($this->reBody, $body);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reProvider) !== false;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }
}
