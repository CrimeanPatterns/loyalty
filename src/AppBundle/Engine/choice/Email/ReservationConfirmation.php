<?php

namespace AwardWallet\Engine\choice\Email;

class ReservationConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#(We\'re\s+pleased\s+to\s+confirm\s+your(\s+upcoming)?\s+stay|We\s+look\s+forward\s+to\s+your\s+arrival\.\s+It\'s\s+just\s+right\s+around\s+the\s+corner).*?Choice\s+Hotels\s+International#is', 'blank', '/1'],
        ['#Join\s+Choice\s+Privileges#i', 'blank', '/1'],
        ['#Inscríbase\s+a\s+Choice\s+Privileges#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#Your\s+upcoming\s+Comfort\s+Suites\s+stay#i', 'us', ''],
    ];
    public $reFrom = [
        ['#yourstay@choicehotels\.com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#choicehotels\.com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en, fr, es";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "30.04.2015, 12:29";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "choice/it-1794426.eml, choice/it-1804388.eml, choice/it-1804389.eml, choice/it-1834896.eml, choice/it-1846483.eml, choice/it-1849894.eml, choice/it-1884797.eml, choice/it-1889178.eml, choice/it-2021270.eml, choice/it-2120785.eml, choice/it-2396339.eml, choice/it-2455573.eml, choice/it-2494711.eml, choice/it-2510090.eml, choice/it-2525963.eml, choice/it-2525964.eml, choice/it-2653959.eml, choice/it-2673482.eml, choice/it-2858539.eml, choice/it-3963819.eml, choice/it-3971356.eml, choice/it-3978260.eml, choice/it-3980605.eml, choice/it-9015427.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Confirmation\s+Number:\s*([\w\-]+)#'),
                            re('#Número\s+de\s+confirmación:\s+([\w\-]+)#i'),
                            CONFNO_UNKNOWN
                        );
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $addr = implode("\n", nodes("//img[contains(@class, 'hotel-photo')][ancestor::table[1][not(contains(., 'Description'))]]/following::tr[string-length(normalize-space(.))>1][1]//text()"));

                        if (!$addr) {
                            $addr = implode("\n", nodes("//*[contains(text(), 'Property Info & Directions')]/ancestor::td[contains(., '+')][1]//text()"));
                            $addr = clear("#Property\s+Info.+$#is", $addr);
                        }

                        if (!$addr) {
                            $addr = implode("\n", nodes("//*[contains(text(), 'Property Info & Directions')]/ancestor::td[1]//text()"));
                            $addr = clear("#Property\s+Info.+$#is", $addr);
                        }

                        if (!$addr) {
                            $addr = implode("\n", nodes("//img[contains(@src, 'eBrochure') and not(./preceding::text()[contains(normalize-space(),'Room Description')])][ancestor::table[1][not(contains(., 'Description'))]]/following::tr[string-length(normalize-space(.))>1][1]//text()"));
                        }

                        if (!$addr) {
                            $addr = implode("\n", nodes('//td[contains(., "Name:") and not(.//td)]/following-sibling::td[1]//text()'));
                        }

                        return [
                            "HotelName" => detach("#^\s*([^\n]+)#", $addr),
                            "Phone"     => nice(trim(detach("#\n\s*([+\d\s\(\)\-]{10,}).*#s", $addr))),
                            "Address"   => nice($addr),
                        ];

                    /*return orval(
                        re('#We\'re\s+pleased\s+to\s+confirm\s+your\s+upcoming\s+stay\s+at\s+the\s+(.*?)\.\s+Below\s+is\s+information#i'),
                        re('#Your\s+stay\s+at\s+(.*?),#i'),
                        node("//*[contains(@class, 'hotel-info')]//strong[1]/strong[1]"),
                        node("(//*[contains(@class, 'hotel-info')]//strong)[1]"),
                        node("(//*[contains(@class, 'hotel-info')]//b//text())[1]")
                    );

                    if (preg_match('#'.$res['HotelName'].'\n\s*((?s).*?)\n\s*(\+[\d\-\s\(\)]+)\n#iums', $text, $m)) {
                        $res['Address'] = nice($m[1]);
                        $res['Phone'] = nice($m[2]);
                    } else {
                        $subj = implode("\n", nodes('//td[contains(., "Property Info & Directions") and not(.//td)]/ancestor::td[2]//text()'));
                        if (preg_match('#\s*(.*)\s+((?s).*)\s*\n\s*(\+\d.*)#i', $subj, $m)) {
                            $res['HotelName'] = $m[1];
                            $res['Address'] = nice($m[2], ',');
                            $res['Phone'] = $m[3];
                        }
                    }
                    return $res;*/
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(nice(str_replace('/', '.', re("#\s+(?:Check[\s-]*In|Fecha\s+de\s+llegada)\s*:\s*\w+,\s+(\w+\s+\d+,\s+\d{4}|\d+-\w+-\d{4}|\d+/\d+/\d{4})\s+\((\d+:\d+(\s+[AP]M)?)\)#iu")) . ', ' . re(2)));
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(nice(str_replace('/', '.', re([
                            "#\s+Check[\s-]*Out\s*:\s*(\w+,\s*\w+\s*\d+,\s*\d+\s*\((\d+:\d+[\sAPM]+)\))#i",
                            "#\s+Check[\s-]*Out\s*:\s*\w+,\s*(\d+/\d+/\d{4}\s*\((\d+:\d+[\sAPM]+)\))#i",
                            "#\s+Check[\s-]*Out\s*:\s*([^\n]+)#i",
                            "#\s+Fecha\s+de\s+salida\s*:\s*([^\n]+)#i",
                        ])))));
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return clear("#\s+\d+#", re('#(?:Name|Nombre):\s+(.*?)\s+(?:Reservation\s+Status|Confirmation\s+Number|Número\s+de\s+confirmación)#i'));
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Number\s+of\s+Rooms|Número\s+de\s+habitaciones):\s+(\d+)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Cancellation\s+Deadline|Fecha\s+límite\s+para\s+cancelar):\s+(.*)#');
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        $res = null;
                        $xpath = '//tr[(contains(., "Room Description") or contains(., "Descripción de la habitación")) and (contains(., "Max Room") or contains(., "Ocupación máxima")) and not(.//tr)]/ancestor::table[1]/tbody/tr[1]';
                        $nodes = $this->http->XPath->query($xpath);

                        if ($nodes->length == 1) {
                            $tr = $nodes->item(0);
                            $subj = implode("\n", nodes('./td[1]//text()', $tr));

                            if (preg_match('#\s*(.*)\s*\n\s*(.*)#i', $subj, $m)) {
                                $res['RoomType'] = $m[1];
                                $res['RoomTypeDescription'] = $m[2];
                            }

                            $res['Guests'] = re('#\d+#', node('./td[3]', $tr));
                            $res['Kids'] = re('#\d+#', node('./td[4]', $tr));
                        }

                        return $res;
                    },

                    "Cost" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//td[contains(., "Sub Total") and not(.//td)]/following-sibling::td[1]//text()';
                        $subj = nodes($xpath);

                        if (count($subj) == 4) {
                            return [
                                'Cost'  => cost($subj[0]),
                                'Taxes' => cost($subj[1]),
                            ];
                        }
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $c = cost(
                            orval(
                                re("#([\d,.]+)\s*\([^\)]+\)\s*$#s", cell("Estimated Total:", +1)),
                                re("#Estimated\s+Total:\s+([^\n\d]{1,2}[\d.,]+)#i"),
                                re('#Total\s+estimado:\s+(.*)\s+Número#i')
                            )
                        );

                        return $c;
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $cur = orval(
                            currency(re('#Total\s+estimado:\s+\$(.*)\s+Número#i')),
                            currency(re("#Estimated\s+Total:[^\n]*?\s+([A-Z]{3})#i")),
                            re("#[\d,.]+\s*\(([^\)]+)\)#", cell("Estimated Total:", +1))
                        );

                        if (stripos($cur, 'US Dollar') !== false) {
                            return 'USD';
                        }

                        if (stripos($cur, 'Peso mexicano') !== false) {
                            return 'MXN';
                        }

                        if (stripos($cur, 'New Zealand Dollar') !== false) {
                            return 'NZD';
                        }

                        if (stripos($cur, 'Canadian Dollar') !== false) {
                            return 'CAD';
                        }

                        if (stripos($cur, 'Australian Dollar') !== false) {
                            return 'AUD';
                        }

                        if (strlen($cur) > 5) {
                            $this->logger->info($cur);

                            return null;
                        }

                        return $cur;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $an = re('#Member\s*\#:\s*([A-Z\d]+)#');

                        if ($an) {
                            return [$an];
                        }
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Points\s+Redeemed:\s+(.*)#i');

                        if ($subj) {
                            return $subj . ' points';
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re('#(?:Reservation\s+Status|Estatus\s+de\s+la\s+reservación):\s+(.*?)\s+(?:Check\s+In|Fecha)#i');
                    },
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        // Old detect
        $textBody = $parser->getPlainBody();

        foreach ($this->rePlain as $re) {
            if (preg_match($re[0], $textBody)) {
                return true;
            }
        }

        // Detect Provider
        if (
            $this->http->XPath->query('//a[contains(@href,".choicehotels.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(.),"Choice Hotels International, Inc. All rights reserved") or contains(.,"@choicehotels.com")]')->length === 0
        ) {
            return false;
        }

        // Detect Format/Language
        return $this->http->XPath->query('//node()[contains(normalize-space(.),"Check Out:")]')->length > 0;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $result = parent::ParsePlanEmail($parser);

        return $result;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en", "fr", "es"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
