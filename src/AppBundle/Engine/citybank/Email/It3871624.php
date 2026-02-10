<?php

namespace AwardWallet\Engine\citybank\Email;

class It3871624 extends \TAccountCheckerExtended
{
    public $mailFiles = "citybank/it-6422413.eml, orbitz/it-2302251.eml, orbitz/it-2352422.eml, orbitz/it-2657260.eml, orbitz/it-2660162.eml, orbitz/it-2660316.eml, orbitz/it-2941935.eml, orbitz/it-5216091.eml, orbitz/it-5333737.eml, orbitz/it-5340366.eml, orbitz/it-5340393.eml, orbitz/it-5440270.eml, orbitz/it-5535618.eml, orbitz/it-5656067.eml, orbitz/it-5661363.eml, orbitz/it-6144763.eml, orbitz/it-6223213.eml";
    public $reBody = '.ml.com';
    public $reBody2 = [
        "en"=> "Your Flight",
        "no"=> "Din Flyreise",
        "fr"=> "Votre vol",
        "nl"=> "boekingsnummer:",
        "fi"=> "bookers varausnumero:",
        "de"=> "Buchungsnummer",
    ];

    public static $dictionary = [
        "en" => [
            "PassangerBlock" => "normalize-space(text())='Traveler information' or normalize-space(text())='Traveller information' or normalize-space(text())='Customer information'",
            "PassangerField" => "contains(., 'Customer') or contains(., 'Traveller') or contains(., 'Traveler')",
            "FlightsBlock"   => "normalize-space(.)='Outbound' or normalize-space(.)='Flight 1'",
        ],
        "no" => [
            "booking number:" => "reservasjonsnummer:",
            "Record locator:" => "Bestillingsnummer:",
            "FlightsBlock"    => "normalize-space(.)='Utgående'",
            "Depart"          => "Avgang",
            "PassangerBlock"  => "normalize-space(text())='Informasjon om den reisende'",
            "PassangerField"  => "contains(., 'Reisende ')",
            "Stop"            => "Stopp",
            "Arrive"          => "Ankomst",
        ],
        "fr" => [
            "booking number:"    => ["Numéro de réservation", 'booking number'],
            "Record locator:"    => "Numéro de dossier:",
            "FlightsBlock"       => "normalize-space(.)='Vol aller' or contains(., 'Vol ')",
            "Depart"             => "Départ",
            "PassangerBlock"     => "contains(normalize-space(text()), 'Informations voyageur')",
            "PassangerField"     => "contains(., 'Voyageur ') or contains(., 'Passager ')",
            "Stop"               => "Escale",
            "Arrive"             => "Arrivée",
            "Total trip cost"    => "Tarif total du voyage",
            "Taxes and fees"     => "Taxes et frais",
            'Total booking cost' => 'Coût total du voyage',
        ],
        "nl" => [
            "booking number:" => "boekingsnummer:",
            "Record locator:" => "Reserveringsnummer:",
            "FlightsBlock"    => "normalize-space(.)='Heenreis' or contains(normalize-space(.), 'Vlucht ')",
            "Depart"          => "Vertrek",
            "PassangerBlock"  => "contains(normalize-space(text()), 'Reizigersinformatie')",
            "PassangerField"  => "contains(., 'Reiziger ')",
            "Stop"            => "Stop",
            "Arrive"          => "Aankomst",
            "Total trip cost" => "Totale reissom",
            "Taxes and fees"  => "Belastingen en toeslagen",
        ],
        "fi" => [
            "booking number:" => "bookers varausnumero:",
            "Record locator:" => "Varaustunnus:",
            "FlightsBlock"    => "normalize-space(.)='Menolento' or contains(normalize-space(.), 'Paluu') or contains(normalize-space(.), 'Lento ') ",
            "Depart"          => "Lähtö",
            "PassangerBlock"  => "contains(normalize-space(text()), 'Matkustajatiedot')",
            "PassangerField"  => "contains(., 'Matkustaja ')",
            "Stop"            => "Välilasku",
            "Arrive"          => "Saapuminen",
            "Total trip cost" => "Totale reissom",
            "Taxes and fees"  => "Belastingen en toeslagen",
            // Hotel
            "Hotel"              => "Hotelli",
            "ConfirmationNumber" => "Hotellin vahvistusnumero",
            "Phone"              => "Puhelinnumero",
            "Fax"                => "Fax",
            "Reservation"        => "Varaus",
            "SignIn"             => "on sisäänkirjautujana",
            "CheckInDate"        => "Sisäänkirjautuminen",
            "CheckOutDate"       => "Uloskirjautuminen",
            "Rooms"              => "Varaus",
            "Description"        => "Huoneen kuvaus",
        ],
        "de" => [
            "booking number:" => "Buchungsnummer:",
            "Record locator:" => "Auftragsnummer:",
            "FlightsBlock"    => "contains(normalize-space(.), 'Abgehender Flug') or contains(normalize-space(.), 'Flug') ",
            "Depart"          => ["Abreise", "Hinreise"],
            "PassangerBlock"  => "contains(normalize-space(text()), 'Reisedetails') or contains(normalize-space(text()), 'Passagierangaben')",
            "PassangerField"  => "contains(., 'Reisender')",
            "Stop"            => "Stop",
            "Arrive"          => "Ankunft",
            "Total trip cost" => "Gesamtreisepreis",
            "Taxes and fees"  => "Steuern & Gebühren",
        ],
    ];

    public $lang = "en";

    public function html_own(&$itineraries)
    {
        // record locators
        $xpath = "//text()[" . $this->getXpath($this->t("booking number:")) . "]/ancestor::tr[./following-sibling::tr][1]/../tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[" . $this->getXpath($this->t("booking number:")) . "]/ancestor::tr[./descendant::text()[normalize-space(.)][2]][1]";
            $nodes = $this->http->XPath->query($xpath);
        }
        //		$this->logger->info($xpath);
        $rls = [];

        if ($nodes->length > 0) {
            foreach ($nodes as $root) {
                $airline = strtolower($this->http->FindSingleNode("(.//text()[normalize-space(.)])[1]", $root, true, "#(.*?)\s+" . $this->t("Record locator:") . "#"));
                $rl = $this->http->FindSingleNode("(.//*[name()='strong' or name()='b'])[last()]", $root);

                if (empty($airline)) {
                    $airline = 'main';
                }
                $rls[$airline] = $rl;
            }
        } else {
            $rls['main'] = $this->http->FindSingleNode('//text()[contains(., "record locator")]', null, false, '# record locator\s+([\w\-/]+)#');
        }

        $w = $this->t("Depart");

        if (!is_array($w)) {
            $w = [$w];
        }
        $ruleDep = implode(" or ", array_map(function ($s) {return "contains(., '{$s}')"; }, $w));
        // airs
        $xpath = "//*[" . $this->t("FlightsBlock") . "]/ancestor::tr[1]/following-sibling::tr[$ruleDep]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->info($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $root2 = $this->http->XPath->query("./following-sibling::tr[(contains(., '" . $this->t("Stop") . "') or contains(., '" . $this->t("Arrive") . "')) and string-length(normalize-space(.))>10][1]", $root)->item(0);
            $airline = strtolower($this->http->FindSingleNode("./td[2]//img/ancestor::tr[1]/descendant::text()[normalize-space(.)][1]", $root2, true, "#(.*?)\s+\d+\s+#"));

            if (stripos($root2->nodeValue, 'train') !== false) {
                $rl = CONFNO_UNKNOWN;
                $type = 'train';
            } elseif (isset($rls[$airline])) {
                $rl = $rls[$airline];
            } elseif (isset($rls['main'])) {
                $rl = $rls['main'];
            } else {
                $rl = null;
            }

            $airs[$rl][] = $root;
        }

        foreach ($airs as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";

            $it['RecordLocator'] = $rl;

            $it['Passengers'] = $this->http->FindNodes("//*[" . $this->t("PassangerBlock") . "]/ancestor::tr[1]/following-sibling::tr//text()[" . $this->t("PassangerField") . "]/following::text()[normalize-space(.)][1]");

            if (count($airs) == 1) {
                $it['TotalCharge'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Total trip cost") . "' or normalize-space(text())='" . $this->t("Total booking cost") . "']/ancestor-or-self::td[1]/following-sibling::td[1]"));

                $it['Currency'] = currency($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Total trip cost") . "' or normalize-space(text())='" . $this->t("Total booking cost") . "']/ancestor-or-self::td[1]/following-sibling::td[1]"));

                $it['Tax'] = cost($this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Taxes and fees") . "']/ancestor-or-self::td[1]/following-sibling::td[1]"));

                $it['SpentAwards'] = $this->http->FindSingleNode("//*[normalize-space(text())='" . $this->t("Points redeemed for this reservation") . "']/ancestor-or-self::td[1]/following-sibling::td[1]");
            }

            foreach ($roots as $root) {
                $this->logger->info($root->nodeValue);

                $root2 = $this->http->XPath->query("./following-sibling::tr[(contains(., '" . $this->t("Stop") . "') or contains(., '" . $this->t("Arrive") . "')) and string-length(normalize-space(.))>10][1]", $root)->item(0);

                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root)));
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]//img/ancestor::tr[1]/descendant::text()[normalize-space(.)][1]", $root2, true, "#.+?\s+(\d{1,4})#");
                //				$this->logger->info('TODO - '.$root2->nodeValue);
                // DepCode
                if (empty($type)) {
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\(([A-Z]{3})\)#");
                } else {
                    $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                }

                if (!empty($type) && preg_match('/\s+\d{1,2}\s+\w+\s+(.+\s+\([A-Z]{3}\))/iu', $root->nodeValue, $m)) {
                    $itsegment['DepName'] = $m[1];
                }

                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[1]", $root, true, "#\d+[:.]\d+(?:\s+[AP]M)?#"), $date);

                if (!empty($type) && preg_match('/\d{1,2}:\d{2}\s+(.+\s+\([A-Z]{3}\))/iu', $root2->nodeValue, $m)) {
                    $itsegment['ArrName'] = $m[1];
                }

                // ArrCode
                if (empty($type)) {
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]", $root2, true, "#\(([A-Z]{3})\)#");
                } else {
                    $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;
                }

                // ArrDate
                if ($time = $this->http->FindSingleNode("./td[1]", $root2, true, "#\d+[:.]\d+(?:\s+[AP]M)?#")) {
                    $itsegment['ArrDate'] = strtotime($time, $date);

                    if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                        $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                    }
                } else {
                    $itsegment['ArrDate'] = MISSING_DATE;
                }

                //$itsegment['DepDate_'] = date('Y-m-d H:i:s', $itsegment['DepDate']);
                //$itsegment['ArrDate_'] = date('Y-m-d H:i:s', $itsegment['ArrDate']);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]//img/ancestor::tr[1]/descendant::text()[normalize-space(.)][1]", $root2, true, "#(.*?)\s+\d+\s+#");

                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[2]//img/ancestor::tr[1]/descendant::text()[normalize-space(.)][1]", $root2, true, "#.*?\s+\d+\s+(.+)#");

                // TraveledMiles
                $itsegment['TraveledMiles'] = $this->http->FindSingleNode("./td[2]", $root2, true, "#(([\d\,\.]+\s+(?:mi|km)))\s+#");

                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[2]/table[2]/descendant::text()[normalize-space(.)][2]", $root2);

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./td[2]", $root2, true, "#\s+(\d+(?:h|hr)\s+\d+(?:min|m))#");

                $it['TripSegments'][] = $itsegment;
            }
            //filter dublicates //it-5661363.eml
            $it['TripSegments'] = array_map('unserialize', array_unique(
                                    array_map('serialize', $it['TripSegments'])
                                    ));
            $itineraries[] = $it;
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->html_own($itineraries);

        foreach ($this->http->XPath->query('//*[text() = "Hotelli"]/ancestor::table[2]') as $value) {
            $itineraries[] = $this->parseHotel($value->nodeValue);
        }

        $result = [
            'emailType'  => 'FlightItinarary' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) + 1; // +1 hotel
    }

    protected function parseHotel($text)
    {
        $it = [];
        $it['Kind'] = 'R';

        foreach ($this->http->XPath->query("//td[normalize-space(.)='{$this->t("Hotel")}']/ancestor::tr[2]") as $root) {
            $tr1 = $this->http->FindNodes('following-sibling::tr[1]//tr', $root);

            if (!empty($tr1[0])) {
                $it['HotelName'] = trim($tr1[0]);
            }

            if (!empty($tr1[1]) && preg_match("/{$this->t("ConfirmationNumber")}:\s*([A-Z\d]+)/", $tr1[1], $matches)) {
                $it['ConfirmationNumber'] = $matches[1];
            } else {
                $it['ConfirmationNumber'] = $this->http->FindSingleNode("following-sibling::tr//text()[contains(., '{$this->t("ConfirmationNumber")}')]/following-sibling::strong[1]", $root);
            }

            $tr2 = $this->http->FindSingleNode("following-sibling::tr[contains(., '{$this->t("Phone")}')]//tr", $root);

            // 3900 Las Vegas Blvd. South, Las Vegas, NV 89119 USPuhelinnumero: 1-702-262-4000 | Fax: 1-702-262-4478
            // H-1138 Budapest, Margitsziget, Budapest, H-1138 HUPuhelinnumero: 361-889-4778
            if (preg_match("/^(.+?){$this->t("Phone")}:\s*([\d-\s]+)(?:\s*\|\s*{$this->t("Fax")}:\s*([\d-\s]+))?/s", $tr2, $matches)) {
                $it['Address'] = trim($matches[1]);
                $it['Phone'] = trim($matches[2]);

                if (!empty($matches[3])) {
                    $it['Fax'] = trim($matches[3]);
                }
            }

            $checkInDate = $this->http->FindSingleNode("following-sibling::tr/td//text()[contains(., '{$this->t("CheckInDate")}')]/ancestor::td[1]", $root, false, '/:([\w\s.]+)/');
            $it['CheckInDate'] = strtotime($this->normalizeDate(trim($checkInDate)));

            $checkOutDate = $this->http->FindSingleNode("following-sibling::tr/td//text()[contains(., '{$this->t("CheckOutDate")}')]/ancestor::td[1]", $root, false, '/:([\w\s.]+)/');
            $it['CheckOutDate'] = strtotime($this->normalizeDate(trim($checkOutDate)));

            // Varaus: 1 Huone/huoneet:
            // Varaus: Huone/huoneet: 1
            $it['Rooms'] = $this->http->FindSingleNode("following-sibling::tr/td//text()[contains(., '{$this->t("Rooms")}')]/ancestor::td[1]", $root, false, '/:?.*?:\s*(\d+)/');

            //RoomTypeDescription
            $it['RoomTypeDescription'] = $this->http->FindSingleNode("following-sibling::tr/td//text()[contains(., '{$this->t("Description")}')]/ancestor::tr[1]", $root, false, "/{$this->t('Description')}\s+([\w\s.-]+)/");
        }

        return $it;
    }

    protected function parseHotel2($text)
    {
        $it = [];
        $it['Kind'] = 'R';

        // Example:
        // Hotelli    Luxor Hotel and Casino   Hotellin vahvistusnumero: H11V97
        // 3900 Las Vegas Blvd. South, Las Vegas, NV 89119 USPuhelinnumero: 1-702-262-4000 | Fax: 1-702-262-4478
        // ----
        // Hotelli    Danubius Health Spa Resort Margitsziget Budapest
        // H-1138 Budapest, Margitsziget, Budapest, H-1138 HUPuhelinnumero: 361-889-4778

        $reg = "{$this->t("Hotel")}\s+(.+?)(?:\s+{$this->t("ConfirmationNumber")}:\s*([A-Z\d]{5,6}))?";
        $reg .= "\s{2,}(.+?){$this->t("Phone")}:\s*([\d-\s]+)(?:\s*\|\s*{$this->t("Fax")}:\s*([\d-\s]+))?";

        if (preg_match("/{$reg}/s", $text, $matches)) {
            $it['HotelName'] = trim($matches[1]);
            $it['ConfirmationNumber'] = !empty($matches[2]) ? trim($matches[2]) : CONFNO_UNKNOWN;
            $it['Address'] = trim($matches[3]);
            $it['Phone'] = trim($matches[4]);

            if (!empty($matches[5])) {
                $it['Fax'] = trim($matches[5]);
            }
        }

        if (preg_match_all("/{$this->t("Reservation")}:\s*(\d+)/", $text, $matches)) {
            $it['Rooms'] = array_sum($matches[1]);
        }

        if (preg_match_all("/\s*\n(.+?){$this->t("SignIn")}/", $text, $matches)) {
            $it['GuestNames'] = array_map('trim', $matches[1]);
        }

        if (preg_match("/{$this->t("CheckInDate")}:(.+?){$this->t("CheckOutDate")}:(.+?){$this->t("Description")}/us", $text, $matches)) {
            $it['CheckInDate'] = strtotime($this->normalizeDate(trim($matches[1])));
            $it['CheckOutDate'] = strtotime($this->normalizeDate(trim($matches[2])));
        }

        return $it;
    }

    private function getXpath($str, $anchor = '.')
    {
        if (is_array($str)) {
            $r = array_map(function ($str) use ($anchor) {
                return "contains(" . $anchor . ", '" . $str . "')";
            }, $str);

            return implode(' or ', $r);
        } else {
            return "contains(" . $anchor . ", '" . $str . "')";
        }
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^\w+,\s+(\d+)\s+(\w+)$#",
            "#^\w+\s+(\d+)\s+(\w+)$#",
            "#^[^\d\s]+\s+(\d+)\.\s+(\w+)$#u",
            "#^\w+[.,]*\s+(\d+)\s+(\w+)\.?$#u", //ven. 17 juin.
            "#^\w+,\s+(\w+)\s+(\d+)$#",
            "#^\w+\s+(\d+)\.?\s+(\w+)\s+(\d+)#u", // ma 22. kesä 2015, 1400
        ];
        $out = [
            "$1 $2 {$year}",
            "$1 $2 {$year}",
            "$1 $2 {$year}",
            "$1 $2 {$year}",
            "$2 $1 {$year}",
            "$1 $2 $3",
        ];
        $en = en(preg_replace($in, $out, $str));

        if (strtotime($en) < $this->date) {
            $en = preg_replace("#\d{4}#", $year + 1, $en);
        }

        return $en;
    }

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[1]");
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
