<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

//TODO: need merge with sabre\Email\It5638776.php
class BConfirmation extends \TAccountChecker
{
    public $mailFiles = "bcd/it-11309561.eml, bcd/it-5342892.eml";

    public $date;

    public $reBody2 = [
        "en" => "CONFIRMATION NUMBERS",
        "es" => "NÚMEROS DE CONFIRMACIÓN",
    ];

    public static $dictionary = [
        "en" => [
        ],
        "es" => [
            "CONFIRMATION NUMBERS"       => "NÚMEROS DE CONFIRMACIÓN",
            "SABRE Record Locator"       => "SABRE # de Localizador de Registro",
            "AIR"                        => "AÉREO",
            "Flight"                     => "Flight",
            "NAME(S) OF PEOPLE TRAVELING"=> "NOMBRE DE PASAJERO",
            'Name'                       => 'Nombre',
            "FARE INFORMATION"           => "INFORMACIÓN DE TARIFAS",
            "Total Flight"               => "Total del vuelo",
            "Base Airfare"               => "Tarifa aérea base",
            "Total Taxes"                => "Total de impuestos y cargos aplicables",
            "Depart"                     => "Partida",
            "Arrive"                     => "Arribo",
            "Stops"                      => "Escalas",
            "Class"                      => "Clase",
            "Seats Requested"            => "Asientos Solicitados",
            "Status"                     => "Estado",
        ],
    ];

    public $lang = "en";

    private $code;

    private $headers = [
        'bcd' => [
            'from' => ['@bcdtravel.com', '@bcdtravel.co.uk'],
            'subj' => [
                "en" => "Booking Confirmation",
                "es" => "Confirmación de Reserva",
            ],
        ],
        'hoggrob' => [
            'from' => ['hrgworldwide.com', '@ar.hrgworldwide.com'],
            'subj' => [
                "en" => "Booking Confirmation",
                "es" => "Confirmación de Reserva",
            ],
        ],
    ];

    private $bodies = [
        'bcd' => [
            'BCD',
        ],
        'hoggrob' => [
            'HRG',
        ],
    ];

    public static function getEmailProviders()
    {
        return ['bcd', 'hoggrob'];
    }

    public function parseHtml(&$itineraries)
    {
        $tripNumber = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBERS'))}]/ancestor::strong[1]/ancestor::div[1]/following::table[1]/descendant::text()[{$this->contains($this->t('SABRE Record Locator'))}]/following::text()[normalize-space(.)!=''][1]");

        //##################
        //##   FLIGHTS   ###
        //##################
        $xpath = "//strong[text()='" . $this->t('AIR') . "']/ancestor::div[1]/following::table[1]";
        $airs = [];
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Flight'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^\s*(.+?)\s*\d+#");

            if ($rl = $this->http->FindSingleNode("//text()[{$this->eq($this->t('CONFIRMATION NUMBERS'))}]/ancestor::strong[1]/ancestor::div[1]/following::table[1]/descendant::text()[contains(.,'{$airline}')]", null, true, "#(\w+)\s*\(#")) {
                $airs[$rl][] = $root;
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";
            $it['RecordLocator'] = $rl;
            $it['TripNumber'] = $tripNumber;
            $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t('NAME(S) OF PEOPLE TRAVELING'))}]/ancestor::strong[1]/ancestor::div[1]/following::table[1]/descendant::text()[{$this->contains($this->t('Name'))}]/following::text()[normalize-space(.)!=''][1]");

            //perhaps there is an error in the case of several airlines
            if (count($it['Passengers']) == 1) {//per person
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('FARE INFORMATION'))}]/ancestor::strong[1]/ancestor::div[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Total Flight'))}]/following::text()[normalize-space(.)!=''][1]"));

                if (!empty($tot['Total'])) {
                    $it['TotalCharge'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('FARE INFORMATION'))}]/ancestor::strong[1]/ancestor::div[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Base Airfare'))}]/following::text()[normalize-space(.)!=''][1]"));

                if (!empty($tot['Total'])) {
                    $it['BaseFare'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
                $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('FARE INFORMATION'))}]/ancestor::strong[1]/ancestor::div[1]/following::table[1]/descendant::text()[{$this->starts($this->t('Total Taxes'))}]/following::text()[normalize-space(.)!=''][1]"));

                if (!empty($tot['Total'])) {
                    $it['Tax'] = $tot['Total'];
                    $it['Currency'] = $tot['Currency'];
                }
            }

            foreach ($roots as $root) {
                $itsegment = [];

                $node = $this->getField($this->t('Flight'), $root);
                // United Airlines 682 Boeing 737-800
                if (preg_match("#^\s*(.+?)\s*(\d+)\s*(.+)$#", $node, $m)) {
                    $itsegment['FlightNumber'] = $m[2];
                    $itsegment['AirlineName'] = $m[1];
                    $itsegment['Aircraft'] = $m[3];
                }
                $node = $this->getField($this->t('Depart'), $root);
                // Monday, Feb 8 12:53 Chicago (ORD)
                if (preg_match("#(\w+\s*,\s*\S+\s*\d+\s+\d+:\d+)\s*(.+)\s*\(([A-Z]{3})\)#u", $node, $m)) {
                    $itsegment['DepDate'] = $this->normalizeDate($m[1]);
                    $itsegment['DepName'] = $m[2];
                    $itsegment['DepCode'] = $m[3];
                }
                $node = $this->getField($this->t('Arrive'), $root);

                if (preg_match("#(\w+\s*,\s*\w+\s*\d+\s+\d+:\d+)\s*(.+)\s*\(([A-Z]{3})\)#u", $node, $m)) {
                    $itsegment['ArrDate'] = $this->normalizeDate($m[1]);
                    $itsegment['ArrName'] = $m[2];
                    $itsegment['ArrCode'] = $m[3];
                }
                $node = $this->getField($this->t('Stops'), $root);
                $itsegment['Stops'] = ($node == $this->t('non-stop')) ? 0 : $node;
                $itsegment['TraveledMiles'] = $this->getField($this->t('Miles'), $root);
                $itsegment['Cabin'] = $this->getField($this->t('Class'), $root);
                $itsegment['Seats'] = $this->getField($this->t('Seats Requested'), $root);
                $it['Status'] = $this->getField($this->t('Status'), $root);

                $itsegment = array_filter($itsegment);
                $it['TripSegments'][] = $itsegment;
            }

            $it = array_filter($it);
            $itineraries[] = $it;
        }

        //#################
        //##    Cars    ###
        //#################
        $xpath = "//strong[text()='" . $this->t('CAR') . "']/ancestor::div[1]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];
            $it['Kind'] = "L";
            $it['Number'] = $this->getField($this->t('Confirmation'), $root);
            $it['TripNumber'] = $tripNumber;
            $it['RentalCompany'] = $this->getField($this->t("Vendor"), $root);
            $addr = "";
            $node = $this->getField($this->t('Pick-up'), $root);
            // Monday, Feb 8 17:00 New York Laguardia Airport
            if (preg_match("#(\w+\s*,\s*\S+\s*\d+\s+\d+:\d+)s*(.+)#u", $node, $m)) {
                $it['PickupDatetime'] = $this->normalizeDate($m[1]);
                $addr = $m[2] . " ";
            }
            $it['PickupLocation'] = trim($addr . $this->http->FindPreg("/^(.+?)(?:Virtual Kiosk Location|$)/", false, $this->getField($this->t('Address'), $root)));
            $it['PickupPhone'] = $this->getField("Tel", $root);
            $addr = "";
            $node = $this->getField($this->t('Drop-Off'), $root);
            // Monday, Feb 8 17:00 New York Laguardia Airport
            if (preg_match("#(\w+\s*,\s*\S+\s*\d+\s+\d+:\d+)\s*(.+)#u", $node, $m)) {
                $it['DropoffDatetime'] = $this->normalizeDate($m[1]);
                $addr = $m[2] . " ";
            }
            $it['DropoffLocation'] = trim($addr . $this->http->FindPreg("/^(.+?)(?:Virtual Kiosk Location|$)/", false, $this->getField($this->t('Address'), $root, 2)));
            $it['DropoffPhone'] = $this->getField($this->t("Tel"), $root, 2);
            $it['CarType'] = $this->getField($this->t("Car size"), $root);
            $it['PricedEquips'] = ["Name" => $this->getField($this->t('Special Requests'), $root)];
            $node = $this->getField($this->t('Total Car Cost'), $root);
            $tot = $this->getTotalCurrency($node);

            if (!empty($tot['Total'])) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $it = array_filter($it);
            $itineraries[] = $it;
        }
        //###################
        //##    HOTELS    ###
        //###################
        $xpath = "//strong[text()='" . $this->t('HOTEL') . "']/ancestor::div[1]/following::table[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $it = [];

            $it['Kind'] = "R";

            $it['ConfirmationNumber'] = $this->getField($this->t('Hotel Confirmation'), $root);
            $it['TripNumber'] = $tripNumber;
            $it['HotelName'] = $this->getField($this->t('Name'), $root);
            $it['Address'] = $this->getField($this->t("Address"), $root);

            $it['CheckInDate'] = $this->normalizeDate($this->getField($this->t('Check-in'), $root));
            $it['CheckOutDate'] = $this->normalizeDate($this->getField($this->t('Check-out'), $root));

            $it['Phone'] = $this->getField($this->t('Phone'), $root);
            $it['Fax'] = $this->getField($this->t('Fax'), $root);
            $it['Rooms'] = $this->getField($this->t('Number of Rooms'), $root);
            $it['Rate'] = $this->getField($this->t('Average Rate'), $root);
            $it['RoomTypeDescription'] = $this->getField($this->t('Special Requests'), $root);

            $it = array_filter($it);
            $itineraries[] = $it;
        }
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $flag = false;

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        $flag = true;
                    }
                }
            }
        }

        if ($flag) {
            foreach ($this->reBody2 as $re) {
                if (stripos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BConfirmation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        if ($code = $this->getProvider($parser)) {
            $result['providerCode'] = $code;
        }

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        $typesRes = 3;
        $cntProvs = 2;
        $cnt = count(self::$dictionary) * $typesRes * $cntProvs;

        return $cnt;
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'bcd') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getField($field, $root, $num = 1)
    {
        $node = $this->http->FindSingleNode("./descendant::td[starts-with(.,'{$field}')][{$num}]/following-sibling::td[1]", $root);

        return $node;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //domingo, mar 13 15:28
            "#^\s*(\w+)\s*,\s+(\w+)\s+(\d+)\s+(\d+:\d+(\s*[AP]M)?)$#iu",
        ];
        $out = [
            '$3 $2 ' . $year . ' $4',
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
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

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);			// 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);	// 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);	// 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);	// 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
