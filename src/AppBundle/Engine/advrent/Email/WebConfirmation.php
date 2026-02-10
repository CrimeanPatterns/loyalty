<?php

namespace AwardWallet\Engine\advrent\Email;

class WebConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+making\s+your\s+on-line\s+reservation\s+with\s+Advantage#i";
    public $rePlainRange = "1000";
    public $reHtml = "";
    public $reHtmlRange = "1000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#advrent#i";
    public $reProvider = "#advrent#i";
    public $caseReference = "";
    public $xPath = "";
    public $mailFiles = "advrent/it-1.eml, advrent/it-1424330.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Your\s+confirmation\s+number\s+is\s+([\w\-]+)#');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Location:')]/ancestor::td[1]//text()";
                        $subj = implode("\n", nodes($xpath));

                        if (preg_match('#Location:\s+(.*?)\n(\(\d+.*)\s+#s', $subj, $m)) {
                            $location = nice($m[1], ', ');
                            $phone = nice($m[2]);
                            $res = [];

                            foreach (['Pickup', 'Dropoff'] as $key) {
                                $res["${key}Location"] = $location;
                                $res["${key}Phone"] = $phone;
                            }

                            return $res;
                        }
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Arrival\s+Date:\s+(.*)#'));
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re('#Return\s+Date:\s+(.*)#'));
                    },

                    "CarImageUrl" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(normalize-space(.), 'View/Cancel this reservation')]/ancestor::td[1]/preceding-sibling::td[1]";
                        $nodes = $this->http->XPath->query($xpath);

                        if ($nodes->length > 0) {
                            $parent = $nodes->item(0);
                            $res['CarImageUrl'] = node('.//img/@src', $parent);
                            $xpath = "./ancestor::tr[2]/following-sibling::tr[1]//text()[string-length(normalize-space(.)) > 0]";
                            $nodes = nodes($xpath, $parent);

                            if (count($nodes) == 2) {
                                $res['CarType'] = $nodes[0];
                                $res['CarModel'] = trim($nodes[1], '()');
                            }

                            return $res;
                        }
                    },

                    "RenterName" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Arrival Date')]/ancestor::td[1]/preceding-sibling::td[1]//text()[string-length(normalize-space(.))>0]";

                        return nodes($xpath)[0];
                    },

                    "PromoCode" => function ($text = '', $node = null, $it = null) {
                        return re('#USED\s+PROMO\s+CODE:\s+([\w\-]+)#');
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $subj = cell('TOTAL', +1);

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "Discount" => function ($text = '', $node = null, $it = null) {
                        return cost(cell('Discount', +2));
                    },

                    "Fees" => function ($text = '', $node = null, $it = null) {
                        $feesNodes = xpath('//tr[preceding-sibling::tr[contains(., "Taxes/Fees")] and following-sibling::tr[contains(., "TOTAL")] and string-length(normalize-space(.)) > 1]');
                        $res = null;

                        foreach ($feesNodes as $f) {
                            $res[] = [
                                'Name'   => node('./td[1]', $f),
                                'Charge' => cost(node('./td[3]', $f)),
                            ];
                        }

                        return $res;
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
