<?php

namespace AwardWallet\Engine\budgetair\Email;

class ConfirmationBooking extends \TAccountChecker
{
    public $mailFiles = "budgetair/it-4393979.eml, budgetair/it-4477569.eml, budgetair/it-4600495.eml, budgetair/it-4618859.eml, budgetair/it-4688367.eml, budgetair/it-5121767.eml, budgetair/it-5125982.eml, budgetair/it-5661547.eml, budgetair/it-5669815.eml";
    public $reFrom = "Budgetair.";
    public $reSubject = [
        "Confirmation booking",
        "order confirmation",
        "booking verification",
        'Conferma Prenotazione',
    ];
    public $reBody = 'budgetair';
    public $reBody2 = [
        "en" => "This document is your",
        "it" => "per aver prenotato con BudgetAir",
        "pl" => "za dokonanie rezerwacji z BudgetAir",
        "pt" => "Obrigado por reservar com Budgetair",
    ];
    public $lang = "en";
    public static $dictionary = [
        "en" => [],
        "it" => [
            "Booking Code"           => "numero di prenotazione",
            "Confirmation / Invoice" => "conferma / fattura",
            "Booking date"           => "data della prenotazione",
            "Cost details"           => "dati personali",
            "Total"                  => "totale",
            "Flight ticket"          => "biglietto volo",
            "Passengers"             => "passeggeri",
            "Flight Details"         => "dettagli volo",
        ],
        "pl" => [
            "Booking Code"           => "Kod rezerwacji",
            "Confirmation / Invoice" => "Potwierdzenie/faktura",
            "Booking date"           => "Data rezerwacji",
            "Cost details"           => "Dane osobowe",
            "Total"                  => "Suma",
            "Flight ticket"          => "Bilet lotniczy",
            "Passengers"             => "Pasażerowie",
            "Flight Details"         => "Szczegóły Lotu",
        ],
        "pt" => [
            "Booking Code"           => "Código de Reserva",
            "Confirmation / Invoice" => "Confirmação / Fatura",
            "Booking date"           => "Data da reserva",
            "Cost details"           => "Detalhes do preço",
            //"Total" => "Total",
            "Flight ticket"  => "bilhete xvoo",
            "Passengers"     => "Passageiros",
            "Flight Details" => "Detalhes de Voo",
        ],
    ];
    protected $date = 0;

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            if (mb_strpos(html_entity_decode($this->http->Response["body"]), $re, 0, 'UTF-8') !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
            'emailType' => 'Lang detect: ' . $this->lang,
        ];

        return $result;
    }

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        $it['RecordLocator'] = $this->http->FindSingleNode("(.//text()[normalize-space(.)='" . $this->t("Booking Code") . "'])[1]/following::text()[normalize-space(.)][1]", null, true, "#[A-Z\d]+$#");
        $it['ConfirmationNumbers'] = $this->nextText($this->t("Confirmation / Invoice"));

        $it['ReservationDate'] = $this->http->FindSingleNode("//span[.='" . $this->t('Booking date') . "']/ancestor-or-self::td[1]/following-sibling::td[2]", null, true, "#(\d{1,2}\s+[a-z]+\s+\d{2,4}|\d{1,2}-\d{1,2}-\d{2,4})#i");
        $it['ReservationDate'] = $this->normalizeDate($it['ReservationDate'], $this->lang);

        if (($tc = $this->http->FindSingleNode("//tr[contains (.,'" . $this->t('Cost details') . "') and count(descendant::tr)=0]/following::table[1]//tbody/tr[contains(.,'" . $this->t('Flight ticket') . "')]"))) {
            if (preg_match('#([A-Z]{3})\s+([\d\.]+)#', $tc, $m)) {
                $it['BaseFare'] = cost($m[2]);
                $it['Currency'] = $m[1];
            }
        }

        if (($tc = $this->http->FindSingleNode("//tr[contains (.,'" . $this->t('Cost details') . "') and count(descendant::tr)=0]/following::table[1]//tbody/tr[contains(.,'" . $this->t('Total') . "')]"))) {
            if (preg_match('#([A-Z]{3})\s+([\d\.]+)#', $tc, $m)) {
                $it['TotalCharge'] = cost($m[2]);
                $it['Currency'] = $m[1];
            }
        }
        $it['Passengers'] = [];

        foreach ($this->http->XPath->query("//tr[contains (.,'" . $this->t('Passengers') . "') and count(descendant::tr)=0]/following::table[1]//tbody/tr[count(td)>3 and td[position()<=3]]") as $p) {
            $it['Passengers'][] = implode(' ', $this->http->FindNodes("./td[position()<=3]", $p));
        }
        $it['Passengers'] = array_filter(array_unique($it['Passengers']));

        $xpath = "//tr[contains (.,'" . $this->t('Flight Details') . "') and count(descendant::tr)=0]/following::table[1]//tbody/tr[count(td)>=5]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $seg = [];

        foreach ($nodes as $i => $root) {
            //if odd then to segment
            $toSegment = $i % 2;
            $dataNodes = $this->http->FindNodes("./td", $root);
            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            if (!$toSegment && count($dataNodes) == 7) {
                if (preg_match('#([A-Z\d]{2})(\d+)#', $dataNodes[4], $m)) {
                    $seg['FlightNumber'] = $m[2];
                    $seg['AirlineName'] = $m[1];
                }
                $seg['DepDate'] = $this->normalizeDate($dataNodes[0] . ' ' . $dataNodes[2], $this->lang);
                $seg['DepName'] = trim($dataNodes[1]);
                $seg['Duration'] = trim($dataNodes[5]);
                $seg['Stops'] = (trim($dataNodes[6]) == 'Non-stop') ? 0 : trim($dataNodes[6]);
                $seg['Operator'] = trim($dataNodes[3]);
            }

            if ($toSegment && count($dataNodes) == 5) {
                $seg['Aircraft'] = trim($dataNodes[4]);
                $seg['ArrDate'] = $this->normalizeDate($dataNodes[0] . ' ' . $dataNodes[2], $this->lang);
                $seg['ArrName'] = trim($dataNodes[1]);
                $seg['Cabin'] = trim($dataNodes[3]);
                $it['TripSegments'][] = $seg;
                $seg = [];
            }
        }
        $it = array_filter($it);
        $itineraries[] = $it;
    }

    protected function normalizeDate($subject, $lang)
    {
        if ($date = strtotime($subject, false)) {
            return $date;
        }

        return strtotime($this->translateDate('/\d+ (\w+) \d+.+/', $subject, $lang), false);
    }

    protected function translateDate($pattern, $string, $lang)
    {
        if (preg_match($pattern, $string, $matches)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($matches[1], $lang)) {
                return str_replace($matches[1], $en, $matches[0]);
            } else {
                return $matches[0];
            }
        }
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
