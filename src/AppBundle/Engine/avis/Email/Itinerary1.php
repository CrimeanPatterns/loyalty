<?php

namespace AwardWallet\Engine\avis\Email;

class Itinerary1 extends \TAccountChecker
{
    public $processors = [];
    public $mailFiles = "avis/it-10.eml, avis/it-11.eml, avis/it-12.eml, avis/it-15.eml, avis/it-2.eml, avis/it-3.eml, avis/it-4.eml, avis/it-7.eml, avis/it-1799888.eml";
    public $xInstance = null;
    private $providerCode = '';

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            /**
             * @example avis/it-5.eml
             * @example avis/it-6.eml
             * @example avis/it-12.eml
             * @example avis/it-13.eml
             */
            "#(^$)|emailHeader\.gif#" => function (&$itineraries, $parser) {
                $this->xBase($this->http);
                $body = $parser->getPlainBody();
                $body = preg_replace("#\n>\s*#ms", "\n", $body);
                $it = [];

                if (preg_match("#Your Avis Booking Confirmation\s+([\d\w\-]+)\s+#", $body, $m)) {
                    $it['Number'] = $m[1];
                }

                if (preg_match("#Cancellation of Booking Number\s+([\d\w\-]+)\s+#", $body, $m)) {
                    $it['Number'] = $m[1];
                    //$it['Cancelled'] = true;
                    $it['Status'] = "Cancelled";
                }

                if (preg_match("#Date:\s*([^\n]*?)\s+At:?\s+(\d+:\d+)\t([^\n]*?)\s+At:?\s+(\d+:\d+)#ims", $body, $m)) {
                    $it['PickupDatetime'] = strtotime($m[1] . ' ' . $this->year . ', ' . $m[2]);
                    $it['DropoffDatetime'] = strtotime($m[3] . ' ' . $this->year . ', ' . $m[4]);
                }

                if (preg_match("#Location:\s*(.*?)(?:\s+Click here to view map\s+|\t)(.*?)(?:Car Type|Click here to view map)#ms", $body, $m)) {
                    $m = array_map('trim', $m);

                    if (preg_match("#^(.*?)\s+([\d\-+]+)\s*$#ms", $m[1], $in)) {
                        $it['PickupLocation'] = preg_replace("#\n#", ', ', $in[1]);
                        $it['PickupPhone'] = $in[2];
                    } else {
                        $it['PickupLocation'] = $m[1];
                    }

                    if (preg_match("#^(.*?)\s+([\d\-+]+)\s*$#ms", $m[2], $in)) {
                        $it['DropoffLocation'] = preg_replace("#\n#", ', ', $in[1]);
                        $it['DropoffPhone'] = $in[2];
                    } else {
                        $it['DropoffLocation'] = $m[2];
                    }

                    if (preg_match("#Opening Hours:\s*(.*?)\t(.*?)\n#", $body, $m)) {
                        $it['PickupHours'] = $m[1];
                        $it['DropoffHours'] = $m[2];
                    }

                    if (preg_match("#Car Type:\s*(.*?):(.*?)\n#", $body, $m)) {
                        $it['CarModel'] = trim($m[2]);
                        $it['CarType'] = trim($m[1]);
                    }

                    if (preg_match("#Total Price:\s*(?:(\w{3})\s+)?([\d\.]+)(?:\s+([A-Z]{3}))?#", $body, $m)) {
                        if (!empty($m[1])) {
                            $it['Currency'] = $m[1];
                        } elseif (!empty($m[2])) {
                            $it['Currency'] = $m[3];
                        }
                        $it['TotalCharge'] = $m[2];
                    }
                }

                if (count($it) > 0) {
                    $it['Kind'] = 'L';
                    $itineraries[] = $it;
                }
            },
            /**
             * @example avis/it-1.eml
             * @example avis/it-4.eml
             * @example avis/it-7.eml
             * @example avis/it-8.eml
             * @example avis/it-9.eml
             * @example avis/it-10.eml
             * @example avis/it-11.eml
             * @example avis/it-14.eml
             */
            "#(for renting with us\!|Thank you\s+[^,]+,) Your car is reserved|you have a car waiting|your reservation has been modified and your car is reserved#i" => function (&$itineraries, $parser) {
                $this->xBase($this->http); // helper
                $this->http->XPath->registerNamespace('php', 'http://php.net/xpath');
                $this->http->XPath->registerPhpFunctions('CleanXMLValue');
                $body = $this->http->Response['body']; // full html
                    $text = $this->mkText($body); // text representation
                    //$tabbed = $this->mkText($body, true); // text with tabs

                    // @Handlers

                ////////////////////////////
                $result = ["Kind" => "L"];
                $confNo = $this->http->FindSingleNode("//text()[contains(., 'Your Confirmation Number')]/ancestor::table[1]", null, true, "#Your\s+Confirmation\s+Number\s*:\s*([A-Z0-9]+)#i");

                if ($confNo) {
                    $result["Number"] = $confNo;
                } else {
                    $result["Number"] = $this->http->FindSingleNode("//*[contains(text(), 'Confirmation Number') and contains(text(), 'Your')]/ancestor::td[1]/following-sibling::td[1]");
                }

                foreach ([
                    "Pickup" => "Pick-up Information",
                    "Dropoff" => "Return",
                ] as $key => $search) {
                    $node = $this->findFirstNode("(//strong | //b)[descendant::text()[contains(normalize-space(.), '" . $search . "')]]");

                    if ($node) {
                        $node->nodeValue = "";
                        $text = $this->http->FindNodes("parent::*//text()", $node);

                        if (count($text) < 2) {
                            $text = $this->http->FindNodes("parent::*/parent::*/text()", $node);
                        }
                        $address = '';
                        $lines = 0;

                        foreach ($text as $line) {
                            $line = CleanXMLValue($line);

                            if (isset($result["{$key}Datetime"]) && !empty($line)) {
                                if (empty($address) && preg_match("/^(.+) ([A-Z]{3})$/", $line, $m)) {
                                    $line = trim($m[1], ", ") . " (" . $m[2] . ")";
                                }
                                $address .= ', ' . $line;
                                $lines++;

                                if ($lines >= 2) {
                                    $result["{$key}Location"] = trim($address, ' ,');

                                    break;
                                }
                            }

                            if (preg_match("/\w+\, \w+ \d{1,2}\, \d{4} @ \d\d?:\d\d [AP]M/", $line, $m)) {
                                $result["{$key}Datetime"] = strtotime(str_replace("@", "", $m[0]));
                            } elseif (preg_match("/(\d+\s*\w+\,?\s*\d+\s*)\@\s*(\d{2})(\d{2})\s*/", $line, $m)) {
                                $m[1] = str_replace(",", "", $m[1]);
                                $result["{$key}Datetime"] = strtotime($m[1] . " " . $m[2] . ":" . $m[3]);
                            }
                        }
                    }
                }

                $totals = $this->http->FindNodes("//td[.//text()[contains(normalize-space(.), 'Estimated Total')] and not(.//td)]/ancestor::table[1]");

                foreach ($totals as $text) {
                    if (preg_match("/Estimated Total \(([A-Z]+)\) ([\d\.\,]+)/", $text, $m)) {
                        $result["Currency"] = $m[1];
                        $result["TotalCharge"] = $m[2];

                        break;
                    } elseif (preg_match("/Estimated Total ([\d\.\,]+) ([A-Z]+)/", $text, $m)) {
                        $result["Currency"] = $m[2];
                        $result["TotalCharge"] = $m[1];

                        break;
                    }
                }
                // taxes
                $taxesNode = $this->http->XPath->query('//tr[
                    contains(., "Taxes") and
                    php:functionString("CleanXMLValue", .) = "Taxes" and
                    preceding-sibling::tr[
                        contains(., "Surcharges") and
                        php:functionString("CleanXMLValue", .) = "Surcharges"
                    ]
                ]')->item(0);

                if ($taxesNode) {
                    $result['TotalTaxAmount'] = $this->http->FindSingleNode('(./following-sibling::tr[contains(., "Tax")]//td[last()])[1]', $taxesNode, true, '/(\d+.\d+|\d+)/ims');
                    $surchargesNodes = $this->http->XPath->query('./preceding-sibling::tr[
                        (count(td) > 1 or
                        count(table//td) > 1) and
                        count(preceding-sibling::tr[
                            contains(., "Surcharges") and
                            php:functionString("CleanXMLValue", .) = "Surcharges"
                        ]) = 1
                    ]', $taxesNode);
                    $surchargesNodesDetailed = $this->http->XPath->query('./preceding-sibling::tr[
                        td/table//table and
                        count(preceding-sibling::tr[
                            contains(., "Surcharges") and
                            php:functionString("CleanXMLValue", .) = "Surcharges"
                        ]) = 1
                    ]/td/table//table//tr', $taxesNode);

                    if ($surchargesNodesDetailed->length > $surchargesNodes->length) {
                        $surchargesNodes = $surchargesNodesDetailed;
                    }
                    $fees = [];

                    foreach ($surchargesNodes as $surchargeNode) {
                        $name = $this->http->FindSingleNode('(./descendant-or-self::*[count(td) > 1]/td[1])[1]', $surchargeNode);
                        $charge = $this->http->FindSingleNode('(./descendant-or-self::*[count(td) > 1]/td[last()])[1]', $surchargeNode, true, '/(\d+.\d+|\d+)/ims');

                        if ($name && $charge) {
                            $fees[] = [
                                'Name'   => $name,
                                'Charge' => $charge,
                            ];
                        }
                    }

                    if (!empty($fees)) {
                        $result['Fees'] = $fees;
                    }
                }

                $result["RenterName"] = beautifulName($this->http->FindSingleNode("//text()[contains(., 'Name:')]/ancestor::td[1]/following-sibling::td"));

                if (!$result["RenterName"]) {
                    $result["RenterName"] = $this->http->FindSingleNode("//*[contains(text(), 'Name:')]/following-sibling::font[1]");
                }

                $car = $this->http->FindSingleNode("//text()[contains(., 'YOUR CAR')]/ancestor::table[1]/ancestor::tr[1]/following-sibling::tr//tr[descendant::text()[contains(., 'Notes:')]]/following-sibling::tr[2]");

                if (!$car) {
                    $node = $this->findFirstNode("//text()[contains(., 'YOUR CAR')]");

                    if ($node) {
                        $node->nodeValue = "";
                        $table = $this->findFirstNode("ancestor::table[1]", $node);

                        if (stripos(CleanXMLValue($table->nodeValue), 'Notes:') === false) {
                            $table = $this->findFirstNode("ancestor::table[1]", $table);
                        }
                        $text = $this->http->FindNodes("descendant::text()", $table);

                        foreach ($text as $line) {
                            if ($car === true && !empty($line)) {
                                $car = $line;

                                break;
                            }

                            if (stripos($line, 'Notes:') !== false) {
                                $car = true;
                            }
                        }
                    }
                }

                if ($model = $this->http->FindSingleNode('//*[contains(text(), "similar") and contains(normalize-space(text()), "or similar")]')) {
                    $result["CarModel"] = $model;
                } elseif (is_string($car)) {
                    $result["CarType"] = $car;
                }

                if (isset($result['CarType']) and preg_match('#(.*)\s+-\s+(.*or\s+similar)#i', $result['CarType'], $m)) {
                    $result['CarType'] = $m[1];
                    $result['CarModel'] = $m[2];
                }

                $result['CarImageUrl'] = $this->http->FindSingleNode('//td[contains(., "YOUR CAR") and not(.//td)]/ancestor::tr/following-sibling::tr[1]//img[contains(@src, "vehicle_guide")]/@src');

                if (count(array_filter($result)) > 0) {
                    $result['Kind'] = 'L';
                    $itineraries[] = $result;
                }
            },

            // Parsed file "avis/it-2.eml"
            "#Your reservation is confirmed|Your reservation has been modified#" => function (&$itineraries, $parser) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                    $text = $this->mkText($body); // text representation
                    //$tabbed = $this->mkText($body, true); // text with tabs

                    // @Handlers

                ////////////////////////////
                $result = ["Kind" => "L"];
                $confNo = $this->http->FindSingleNode("//*[@class='confirmationNumber']");

                if (!$confNo) {
                    $confNo = $this->http->FindSingleNode("//text()[contains(., 'Reservation number')]/parent::*/parent::*", null, true, "/Reservation number (\S+)/");
                }

                if ($confNo) {
                    $result["Number"] = $confNo;
                }

                $total = $this->http->FindSingleNode("//*[@class='total-info']");

                if (!$total) {
                    $total = $this->http->FindSingleNode("//text()[contains(., 'Your estimated total')]/parent::*/parent::*", null, true, "/estimated total (\S+)/");
                }

                if ($total) {
                    if (preg_match("/([\d\.]+) ([A-Z]+)/", $total, $m)) {
                        $result["TotalCharge"] = $m[1];
                        $result["Currency"] = $m[2];
                    } else {
                        $result["TotalCharge"] = preg_replace("/[^\d\.]/", "", $total);
                    }
                }

                $node = $this->findFirstNode("//*[text()[contains(., 'Pick-Up / Drop-Off')]]");

                if ($node) {
                    $node->nodeValue = "";
                    $i = 5;
                    $root = $node;

                    do {
                        $root = $this->findFirstNode("parent::*", $root);
                        $text = $this->http->FindNodes("descendant::text()", $root);
                        $i--;
                    } while (count($text) < 4 && $i > 0);

                    foreach ($text as $line) {
                        $line = CleanXMLValue($line);

                        if (isset($result["DropoffDatetime"]) && !empty($line)) {
                            $result["PickupLocation"] = $result["DropoffLocation"] = $line;

                            break;
                        }

                        if (preg_match("/\w+\, \w+ \d{1,2}\, \d{4} \d\d:\d\d [AP]M/", $line, $m)) {
                            $date = strtotime($m[0]);

                            if (isset($result["PickupDatetime"])) {
                                $result["DropoffDatetime"] = $date;
                            } else {
                                $result["PickupDatetime"] = $date;
                            }
                        }
                    }
                } else {
                    foreach ([
                        "Pickup" => "Pick-Up",
                        "Dropoff" => "Drop-Off",
                    ] as $key => $search) {
                        $node = $this->findFirstNode("//*[text()[contains(., '" . $search . " -')]]");

                        if ($node) {
                            $node->nodeValue = "";
                            $text = $this->http->FindNodes("parent::*//text()", $node);

                            if (count($text) < 3) {
                                $text = $this->http->FindNodes("parent::*/parent::*//text()", $node);
                            }

                            foreach ($text as $line) {
                                $line = CleanXMLValue($line);

                                if (isset($result["{$key}Datetime"]) && !empty($line)) {
                                    $result["{$key}Location"] = $line;

                                    break;
                                }

                                if (preg_match("/\w+\, \w+ \d{1,2}\, \d{4} \d\d:\d\d [AP]M/", $line, $m)) {
                                    $result["{$key}Datetime"] = strtotime($m[0]);
                                }
                            }
                        }
                    }
                }
                $car = $this->http->FindSingleNode("//div[@class='car-make-info']/h3");

                if ($car && $this->http->FindSingleNode("//div[@class='car-make-info']/span[@class='or-similar']")) {
                    $car .= " or similar";
                }

                if (!$car) {
                    $car = $this->http->FindSingleNode("//img[contains(@src, 'car-rental/images')]/parent::*/h3");
                }

                if (!$car) {
                    $cars = $this->http->FindNodes("//h3");

                    foreach ($cars as $i => $car) {
                        if (stripos(CleanXMLValue($car), 'driver preferences') !== false) {
                            $car = $cars[$i + 1] ?? null;

                            break;
                        }
                    }
                }

                if ($car) {
                    $result["CarType"] = $car;
                }
                $node = $this->findFirstNode("//*[text()[contains(., 'Personal Information')]]");

                if ($node) {
                    $node->nodeValue = "";
                    $text = $this->http->FindNodes("parent::*//text()", $node);

                    if (count($text) < 3) {
                        $text = $this->http->FindNodes("parent::*/parent::*//text()", $node);
                    }

                    foreach ($text as $line) {
                        $line = CleanXMLValue($line);

                        if (!empty($line)) {
                            $result["RenterName"] = beautifulName($line);

                            break;
                        }
                    }
                }
                //		$result["PickupHuman"] = isset($result["PickupDatetime"]) ? date("r", $result["PickupDatetime"]) : null;
                //		$result["DropoffHuman"] = isset($result["DropoffDatetime"]) ? date("r", $result["DropoffDatetime"]) : null;
                if (count(array_filter($result)) > 0) {
                    $result['Kind'] = 'L';
                    $itineraries[] = $result;
                }
            },
            /**
             * @example avis/it-15.eml
             * @example avis/it-1799888.eml
             */
            '/Attached\s+is\s+your\s+receipt/' => function (&$itineraries, $parser) {
                $pdfs = $parser->searchAttachmentByName('.*pdf');

                foreach ($pdfs as $pdf) {
                    $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

                    if (!$textPdf) {
                        continue;
                    }

                    if (empty($this->providerCode)) {
                        $this->assignProviderPdf($textPdf);
                    }

                    if (strpos($textPdf, 'RENTAL AGREEMENT NUMBER:') !== false) {
                        $htmlPdf = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX);
                        $this->http->SetEmailBody($htmlPdf, true);
                        \PDF::sortNodes($this->http);
                        $this->parsePdf($itineraries);
                    }
                }
            },
        ];
    }

    public static function getEmailTypesCount()
    {
        return 4;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.](?:avis|avis-europe)\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match('/(?:Receipt for Avis|Avis.+Reservation reminder|Your Avis Booking Confirmation|Avis Reservation Confirmation|Avis Rent A Car:\s*Reservation Confirmation)/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $reText = '/(?:Avis\s+Rent\s+A\s+Car.*www.avis.co.uk|www.avis.co.uk.*Avis\s+Rent\s+A\s+Car)/is';

        if (preg_match($reText, $parser->getPlainBody()) > 0 || preg_match($reText, $parser->getHTMLBody()) > 0) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if ($this->assignProviderPdf($textPdf) !== true) {
                continue;
            }

            if (strpos($textPdf, 'RENTAL AGREEMENT NUMBER:') !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $dateEmail = strtotime($parser->getHeader('date'));
        $this->year = $dateEmail ? getdate($dateEmail)['year'] : null;

        $this->assignProvider($parser->getHeaders());

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $parser->getHTMLBody())) {
                $processor($itineraries, $parser);

                break;
            }
        }

        // if body content is empty
        if (count($itineraries) === 0 && count($parser->searchAttachmentByName('.*pdf')) > 0) {
            $this->processors['/Attached\s+is\s+your\s+receipt/']($itineraries, $parser);
        }

        return [
            'emailType'    => 'Itinerary1',
            'providerCode' => $this->providerCode,
            'parsedData'   => [
                'Itineraries' => $itineraries,
            ],
        ];
    }

    public static function getEmailProviders()
    {
        return ['avis', 'perfectdrive'];
    }

    public function findFirstNode($xpath, $root = null)
    {
        $nodes = $this->http->XPath->query($xpath, $root);

        return $nodes->length > 0 ? $nodes->item(0) : null;
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#&" . "nbsp;#uims", " ", $html);
        $html = preg_replace("#&" . "amp;#uims", "&", $html);
        $html = preg_replace("#&" . "quot;#uims", '"', $html);
        $html = preg_replace("#&" . "lt;#uims", '<', $html);
        $html = preg_replace("#&" . "gt;#uims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace_callback("#(</t(d|h)>)\s+#uims", function ($m) {
                return $m[1];
            }, $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#uims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ums", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#uims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#uims", "\n", $html);
        $html = preg_replace("#</(p|tr|pre)>#uims", "\n", $html);

        $html = preg_replace("#\r\n#uims", "\n", $html);
        $html = preg_replace("#<br(/|)>#uims", "\n", $html);
        $html = preg_replace("#<[^>]+>#uims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#uims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#uims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#uims", "\n", $html);
        $html = preg_replace("#\s+\n#uims", "\n", $html);
        $html = preg_replace("#\n+#uims", "\n", $html);

        return trim($html);
    }

    public function xBase($newInstance)
    {
        $this->xInstance = $newInstance;
    }

    private function parsePdf(&$itineraries): void
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $it = [];

        $it['Number'] = $http->FindSingleNode('//p[contains(normalize-space(),"RENTAL AGREEMENT NUMBER:")]/following-sibling::*[1]', null, true, '/^[-A-Z\d]{5,}$/');
        $it['RenterName'] = $http->FindSingleNode('//*[normalize-space()="Customer Name:"]/following-sibling::*[1]', null, true, '/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u');

        $it['PickupDatetime'] = strtotime(str_replace(['@'], ' ', $http->FindSingleNode('//*[contains(text(), "Pickup Date/Time")]/following::*[1]')));
        $it['DropoffDatetime'] = strtotime(str_replace(['@'], ' ', $http->FindSingleNode('//*[contains(text(), "Return Date/Time")]/following::*[1]')));

        $pickupLocationText = $this->htmlToText($this->http->FindHTMLByXpath('//p[starts-with(normalize-space(),"Pickup Location")]/following-sibling::*[1]'));

        if (preg_match("/^(?<address>[\s\S]{2,}?)[ ]*\n+[ ]*(?<phone>[+(\d][-+. \d)(]{5,}[\d)])[ ]*$/", $pickupLocationText, $m)) {
            $it['PickupLocation'] = preg_replace('/\s+/', ' ', $m['address']);
            $it['PickupPhone'] = $m['phone'];
        } elseif ($pickupLocationText) {
            $it['PickupLocation'] = preg_replace('/\s+/', ' ', $pickupLocationText);
        }

        $dropoffLocationText = $this->htmlToText($this->http->FindHTMLByXpath('//p[starts-with(normalize-space(),"Return Location")]/following-sibling::*[1]'));

        if (preg_match("/^(?<address>[\s\S]{2,}?)[ ]*\n+[ ]*(?<phone>[+(\d][-+. \d)(]{5,}[\d)])[ ]*$/", $dropoffLocationText, $m)) {
            $it['DropoffLocation'] = preg_replace('/\s+/', ' ', $m['address']);
            $it['DropoffPhone'] = $m['phone'];
        } elseif ($dropoffLocationText) {
            $it['DropoffLocation'] = preg_replace('/\s+/', ' ', $dropoffLocationText);
        }

        $it['CarModel'] = $http->FindSingleNode('//*[normalize-space()="Vehicle Description:"]/following-sibling::*[1]');
        $it['CarType'] = $http->FindSingleNode('//*[normalize-space()="Vehicle Group Rented:"]/following-sibling::*[1]');
        $it['TotalCharge'] = $http->FindSingleNode('//p[contains(normalize-space(),"Your Total Charges paid:") or contains(normalize-space(),"Your Total Charges:")]/following-sibling::*[1]', null, true, '(\d+.\d+|\d+)');

        if (preg_match('/([a-z]{3})\s*(\d+.\d+|\d+)/ims', $http->FindSingleNode('//p[contains(., "Net Charges:")]/following-sibling::*[1]'), $matches)) {
            $it['Currency'] = $matches[1];
        }
        $feesNodes = $xpath->query('//line[contains(., "Your Taxable Fees")]
                /following-sibling::line[
                    count(./following-sibling::line[contains(., "Sub-total-Charges:")]) = 1 and
                    not(contains(., "_________________"))
                ]');

        foreach ($feesNodes as $feeNode) {
            $it['Fees'][] = [
                'Name'   => $http->FindSingleNode('./p[1]', $feeNode),
                'Charge' => preg_replace('/^\.(\d+)$/', '0.$1', $http->FindSingleNode('p[last()]', $feeNode)),
            ];
        }

        if (count(array_filter($it)) > 0) {
            $it['Kind'] = 'L';
            $itineraries[] = $it;
        }
    }

    private function assignProvider(array $headers): bool
    {
        if (stripos($headers['from'], '@budgetgroup.com') !== false
            || stripos($headers['subject'], ' Budget ') !== false
            || $this->http->XPath->query('//*[contains(normalize-space(),"Budget Rent A Car System, Inc") or contains(.,"@budgetgroup.com")]')->length > 0
        ) {
            $this->providerCode = 'perfectdrive';

            return true;
        }

        if (preg_match('/[@.](?:avis|avis-europe)\.com/i', $headers['from']) > 0
            || stripos($headers['subject'], ' Avis ') !== false
            || $this->http->XPath->query('//a[contains(@href,".avis.com") or contains(@href,"www.avis.com") or contains(@href,"link.avis.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Reservations & Avis.com Assistance") or contains(.,"www.avis.co.uk") or contains(.,"@avis.com")]')->length > 0
        ) {
            $this->providerCode = 'avis';

            return true;
        }

        return false;
    }

    private function assignProviderPdf(string $text): bool
    {
        if (stripos($text, 'www.budget.com') !== false
            || strpos($text, 'Thank you for renting with Budget') !== false
            || strpos($text, 'Budget Customer Discount:') !== false
        ) {
            $this->providerCode = 'perfectdrive';

            return true;
        }

        if (stripos($text, 'www.avis.com') !== false
            || strpos($text, 'Thank you for renting with Avis') !== false
            || strpos($text, 'Avis Worldwide Discount:') !== false
        ) {
            $this->providerCode = 'avis';

            return true;
        }

        return false;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
