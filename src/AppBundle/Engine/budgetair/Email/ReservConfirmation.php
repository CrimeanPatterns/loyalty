<?php

namespace AwardWallet\Engine\budgetair\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservConfirmation extends \TAccountChecker
{
    public $mailFiles = "budgetair/it-10928887.eml, budgetair/it-2485135.eml, budgetair/it-3053456.eml, budgetair/it-4609996.eml";
    //"budgetair/it-4609996.eml";

    public $reBody = [
        'es' => ['Número de reserva de la compañía aérea', 'Número de vuelo'],
        'nl' => ['Reserveringsnummer', 'Vluchtnummer'],
    ];
    public $reSubject = [
        'es' => ['Booking Confirmation'],
        'nl' => ['Boekingsbevestiging '],
    ];
    public $lang = '';
    public $pdf;
    public static $dict = [
        'es' => [
            //'Mr'=>['Mr','Ms'],
        ],
        'nl' => [
            //'Mr'=>['Mw', 'Dhr'],
            'Número de reserva de la compañía aérea' => ['Reserveringsnummer', 'Reserveringsnummer van de luchtvaartmaatschappij'],
            'Passajero(s)'                           => 'Reiziger(s)',
            'Fecha de nacimiento'                    => 'Geboortedatum',
            'Desglose de precio'                     => 'Prijsdetails',
            'Gastos de pago'                         => ['Dossierkosten', 'Toeslag betaling'],
            'Billete de avión'                       => 'Vliegticket',
            'Total'                                  => 'Totaal',
            'Número de vuelo'                        => 'Vluchtnummer',
            'Duración'                               => 'Duur',
        ],
    ];

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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->AssignLang()) {
            $this->http->Log("can't determine the language");

            return null;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "ReservConfirmation" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//img[contains(@src,'https://s1.travix.com/global/assets/airlineLog')] | //a[contains(@href,'budgetair.')]")->length > 0
            && $this->AssignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'BudgetAir.es') !== false
            || isset($headers['from']) && stripos($headers['from'], '@budgetair') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@budgetair") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Número de reserva de la compañía aérea'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})#");

        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t('Passajero(s)'))}]/following::text()[{$this->contains($this->t('Fecha de nacimiento'))}]/preceding::tr[normalize-space(.)!=''][1]");

        $it['AccountNumbers'] = $this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer'))}]/following::text()[normalize-space(.)!=''][1]");

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Desglose de precio'))}]/ancestor::table[2]//tr[{$this->contains($this->t('Total'))} and count(descendant::tr)=0]");
        $tot = $this->getTotalCurrency($node);

        if (!empty($tot['Total'])) {
            $it['Currency'] = $tot['Currency'];
            $it['TotalCharge'] = $tot['Total'];
        }

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Desglose de precio'))}]/ancestor::table[2]//tr[{$this->contains($this->t('Gastos de pago'))} and count(descendant::tr)=0]");
        $tot = $this->getTotalCurrency($node);

        if (!empty($tot['Total'])) {
            $it['Currency'] = $tot['Currency'];
            $it['Tax'] = $tot['Total'];
        }

        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Desglose de precio'))}]/ancestor::table[2]//tr[{$this->contains($this->t('Billete de avión'))} and count(descendant::tr)=0]");
        $tot = $this->getTotalCurrency($node);

        if (!empty($tot['Total'])) {
            $it['Currency'] = $tot['Currency'];
            $it['BaseFare'] = $tot['Total'];
        }

        $xpath = "//img[contains(@src,'BudgetAir/email-flightinfo.jpg')]/following::td[text()[{$this->contains($this->t('Número de vuelo'))}]]/preceding::*[self::tr or self::p][normalize-space(.)][contains(translate(.,'0123456789','dddddddddd'),' dddd')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $res = [];
            $node = $this->http->FindSingleNode("./following::td[text()[{$this->contains($this->t('Número de vuelo'))}]][1]", $root);

            if (preg_match("#{$this->opt($this->t('Número de vuelo'))}\s+(?<AirlineName>[A-Z\d]{2})\s*(?<FlightNumber>\d+)#", $node, $m)) {
                $res['AirlineName'] = $m['AirlineName'];
                $res['FlightNumber'] = $m['FlightNumber'];
            }
            $dateFly = $this->normalizeDate($this->http->FindSingleNode(".", $root));

            $node = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Duración'))} and following::text()[normalize-space(.)!=''][1][{$this->contains($this->t('Número de vuelo'))}]][1]", $root, true, "#{$this->opt($this->t('Duración'))}\s+(\d+:\d+)#");

            if (!empty($node)) {
                $res['Duration'] = $node;
            }

            if ($flightDetails = $this->http->FindSingleNode("./following::tr[count(descendant::text()[contains(.,':')])=2][1]", $root)) {
                if (preg_match('#(?<DepName>.+?)\s+(?<DepTime>\d{1,2}\:\d{2})\s+(?<ArrTime>\d{1,2}\:\d{2})\s+(?<ArrName>.+)#', $flightDetails, $m)) {
                    $res['DepCode'] = $res['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $res['DepName'] = $m['DepName'];
                    $res['DepDate'] = strtotime($m['DepTime'], $dateFly);

                    $res['ArrName'] = $m['ArrName'];
                    $res['ArrDate'] = strtotime($m['ArrTime'], $dateFly);
                }
            }
            $it['TripSegments'][] = $res;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+\s+(\d+\s+\w+\s+\d{4})\s*$#',
        ];
        $out = [
            '$1',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
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
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }
}
