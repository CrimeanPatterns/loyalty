<?php

namespace AwardWallet\Engine\asiana\Email;

class InssuanceOfTicketWeb extends \TAccountChecker
{
    public $mailFiles = "asiana/it-11604234.eml";

    public $reFrom = "flyasiana.com";
    public $reBody = [
        'en' => ['sent to your confirm flight ticket purchase completion', 'Asiana Airlines'],
    ];
    public $reSubject = [
        'Issuance of international tickets complete',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [],
    ];
    private $passengers;
    private $RecordLocator;
    private $TripNumber;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $its = [];
        $hrefs = $this->http->FindNodes("//img[contains(@src, 'btn_eticket')]/ancestor::a/@href");
        $this->passengers = $this->http->FindNodes("//img[contains(@src, 'btn_eticket')]/ancestor::td[1]/preceding-sibling::*[local-name()='td' or local-name()='th'][last()]/strong");
        $this->TripNumber = str_replace("-", '', $this->http->FindSingleNode("//text()[normalize-space(.)='Reservation Number']/following::text()[normalize-space()][1]", null, true, "#^\s*([\d\-]+)\s*$#"));
        $this->RecordLocator = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), 'Reservation code')]/following::text()[normalize-space()][1])[1]", null, true, "#^\s*([\dA-Z]+)\s*$#");

        foreach ($hrefs as $href) {
            $body = $this->http->GetURL($href);
            $this->parseEmail($its);
        }

        $a = explode('\\', __CLASS__);

        return [
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
            'parsedData' => ['Itineraries' => $its],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'flyasiana.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail(&$its)
    {
        $RecordLocator = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation No.'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, "#^\s*([A-Z\d]+)\b#");

        if (empty($RecordLocator)) {
            $RecordLocator = $this->RecordLocator;
        }
        $TripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation No.'))}]/ancestor::td[1]/following-sibling::td[1]", null, true, "#\(([A-Z\d]+)#");

        if (empty($TripNumber)) {
            $TripNumber = $this->TripNumber;
        }
        $Passenger = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Passenger Name'))}]/ancestor::td[1]/following-sibling::td[1]");
        $check = false;

        foreach ($this->passengers as $passengers) {
            if (stripos($Passenger, $passengers) !== false) {
                $check = true;

                break;
            }
        }

        if ($check == false) {
            return null;
        }
        $TicketNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Ticket Number'))}]/ancestor::td[1]/following-sibling::td[1]");
        $AccountNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Frequent Flyer No.'))}]/ancestor::td[1]/following-sibling::td[1]");
        $total = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'))}]/ancestor::td[1]/following-sibling::td[1]"));
        $currency = $this->currency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total Amount'))}]/ancestor::td[1]/following-sibling::td[1]"));
        $fare = $this->amount($this->http->FindSingleNode("//text()[{$this->eq($this->t('Fare'))}]/ancestor::td[1]/following-sibling::td[1]"));

        foreach ($its as $key => $it) {
            if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                if (empty($its[$key]['TripNumber']) && !empty($TripNumber)) {
                    $its[$key]['TripNumber'] = $TripNumber;
                }

                if (!empty($Passenger)) {
                    $its[$key]['Passengers'][] = $Passenger;
                }

                if (!empty($TicketNumber)) {
                    $its[$key]['TicketNumbers'][] = $TicketNumber;
                }

                if (!empty($AccountNumber)) {
                    $its[$key]['AccountNumbers'][] = $AccountNumber;
                }

                if (!empty($total)) {
                    $its[$key]['TotalCharge'] = (!empty($its[$key]['TotalCharge'])) ? $its[$key]['TotalCharge'] + $total : $total;
                }

                if (!empty($currency) && empty($its[$key]['Currency'])) {
                    $its[$key]['Currency'] = $currency;
                }

                if (!empty($fare)) {
                    $its[$key]['BaseFare'] = (!empty($its[$key]['BaseFare'])) ? $its[$key]['BaseFare'] + $fare : $fare;
                }

                break;
            }
        }

        unset($it);

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Operated by'))}]/ancestor::table[1]");

        foreach ($nodes as $root) {
            $seg = [];

            $node = $this->http->FindSingleNode("./descendant::tr[1]/td[3]", $root);

            if (preg_match("#^([A-Z\d]{2})\s*(\d+)$#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./descendant::tr[1]/td[5]", $root));

            $seg['DepName'] = implode(" ", $this->http->FindNodes("./descendant::tr[1]/td[1]//span[1]//text()", $root));
            $seg['DepartureTerminal'] = $this->http->FindSingleNode("./descendant::tr[1]/td[1]//text()[contains(normalize-space(), 'Terminal')]", $root);

            if (!empty($seg['DepName'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }
            $time = $this->http->FindSingleNode("./descendant::tr[1]/td[6]", $root, true, "#^\s*(\d+:\d+)\s*$#");

            if (!empty($date) && !empty($time)) {
                $seg['DepDate'] = strtotime($date . ' ' . $time);
            }

            $seg['ArrName'] = implode(" ", $this->http->FindNodes("./descendant::tr[1]/td[2]//span[1]//text()", $root));
            $seg['ArrivalTerminal'] = $this->http->FindSingleNode("./descendant::tr[1]/td[2]//text()[contains(normalize-space(), 'Terminal')]", $root);

            if (!empty($seg['ArrName'])) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }
            $time = $this->http->FindSingleNode("./descendant::tr[1]/td[7]", $root, true, "#.*\d+:\d+.*#");

            if (!empty($date) && preg_match("#^\s*(\d+:\d+)\s*(?:\+\s*(\d+))?\s*$#", $time, $m)) {
                $seg['ArrDate'] = strtotime($date . ' ' . $m[1]);

                if (!empty($m[2])) {
                    $seg['ArrDate'] = strtotime(' +' . $m[2] . ' day', $seg['ArrDate']);
                }
            }

            $seg['BookingClass'] = $this->http->FindSingleNode("./descendant::tr[1]/td[4]", $root, true, "#^[A-Z]{1,2}$#");
            $seg['Duration'] = $this->http->FindSingleNode("./descendant::tr[1]/td[8]", $root);
            $seg['Seats'][] = $this->http->FindSingleNode("./descendant::tr[1]/td[10]", $root, true, "#^\s*(\d{1,3}[A-Z])\s*$#");
            $seg['Seats'] = array_filter($seg['Seats']);

            $seg['Operator'] = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Operated by'))}]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^(.+?)\s+[A-Z\d]{2}\s*(\d+)$#");

            if (empty($seg['AirlineName']) && empty($seg['FlightNumber']) && empty($seg['DepDate'])) {
                return null;
            }
            $finded = false;

            foreach ($its as $key => $it) {
                if (isset($RecordLocator) && $it['RecordLocator'] == $RecordLocator) {
                    $finded2 = false;

                    foreach ($it['TripSegments'] as $key2 => $value) {
                        if (isset($seg['AirlineName']) && $seg['AirlineName'] == $value['AirlineName']
                                && isset($seg['FlightNumber']) && $seg['FlightNumber'] == $value['FlightNumber']
                                && isset($seg['DepDate']) && $seg['DepDate'] == $value['DepDate']) {
                            $its[$key]['TripSegments'][$key2]['Seats'] = array_unique(array_filter(array_merge($value['Seats'], $seg['Seats'])));
                            $finded2 = true;
                        }
                    }

                    if ($finded2 == false) {
                        $its[$key]['TripSegments'][] = $seg;
                    }
                    $finded = true;
                }
            }

            unset($it);

            if ($finded == false) {
                $it['Kind'] = 'T';

                if (!empty($RecordLocator)) {
                    $it['RecordLocator'] = $RecordLocator;
                }

                if (!empty($TripNumber)) {
                    $it['TripNumber'] = $TripNumber;
                }

                if (!empty($Passenger)) {
                    $it['Passengers'][] = $Passenger;
                }

                if (!empty($TicketNumber)) {
                    $it['TicketNumbers'][] = $TicketNumber;
                }

                if (!empty($AccountNumber)) {
                    $it['AccountNumbers'][] = $AccountNumber;
                }

                if (!empty($total)) {
                    $it['TotalCharge'] = $total;
                }

                if (!empty($currency)) {
                    $it['Currency'] = $currency;
                }

                if (!empty($fare)) {
                    $it['BaseFare'] = $fare;
                }
                $it['TripSegments'][] = $seg;
                $its[] = $it;
            }
        }

        return true;
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\s*([^\d\s]+)\s*(\d{2})\b\s*.*$#', //21JUN18(THU)
        ];
        $out = [
            '$1 $2 20$3',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return $date;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0
                 && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
