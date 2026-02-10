<?php

namespace AwardWallet\Engine\aeroplan\Email;

class RewardConfirmation extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-2859588.eml, aeroplan/it-2862755.eml, aeroplan/it-58439576.eml, aeroplan/it-7432047.eml";

    public $reFrom = "aeroplan.com";
    public $reBody = [
        'en' => ['Your Transaction Summary', 'This email contains important information about the flight reward'],
        'fr' => ['Résumé de la transaction', 'Ce courriel contient d’importants renseignements sur'],
    ];
    public $reSubject = [
        'your Aeroplan Flight Reward confirmation',
        'la confirmation de votre prime aérienne Aéroplan',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'DepOrArr' => ['DEPARTURE', 'RETURN'],
        ],
        'fr' => [
            'Your flight details may change' => 'Ces renseignements sont sujets à changement.',
            'Aeroplan Number'                => 'Numéro Aéroplan',
            'Transaction Date'               => 'Date de la transaction',
            'Departs'                        => 'Départ',
            'Arrives'                        => 'Arrivée',
            'booking reference'              => 'numéro de réservation',
            'Booking Reference Number'       => 'Numéro de réservation Aeroplan',
            'DepOrArr'                       => ['DÉPART', 'RETURN'],
            'Flight Duration'                => 'Durée du vol',
            'Class'                          => 'Classe',

            'Amount'           => 'Montant',
            'Redemption Total' => 'Sous-total',
        ],
    ];
    private $date;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $this->AssignLang();

        $its = $this->parseEmail();

        //get SUMS
        $sum = $this->http->FindSingleNode("//text()[normalize-space(.)='Grand Total']/following::text()[normalize-space(.)][3]", null, true, "#[\d\.\,]+#");

        if (empty($sum)) {
            $sum = $this->http->FindSingleNode("//*[contains(text(), 'Redemption Total')]/following::td[2]", null, true, "#[\d\.\,]+#");
        }

        if (empty($sum)) {
            $sum = $this->http->FindSingleNode("//*[contains(text(), 'Montant payé par carte de crédit')]/following::td[2]", null, true, "#[\d\.\,]+#");
        }

        $cur = $this->http->FindSingleNode("(//*[{$this->starts($this->t('Amount'))}])[1]", null, true, "#([A-Z]{3})#");

        if (empty($cur)) {
            $cur = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Grand Total')]", null, true, "#\(([A-Z]{3})\)#");
        }
        $spentAW = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Grand Total'))}]/following::text()[normalize-space(.)][1]");

        if (empty($spentAW)) {
            $spentAW = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Redemption Total'), 'text()')}]/following::td[1])[1]", null, false, '/^[\d\s.,]+$/');
        }
        $this->logger->notice("//*[{$this->eq($this->t('Redemption Total'), 'text()')}]/following::td[1]");

        $name = explode('\\', __CLASS__);

        if (count($its) === 1) {
            $its[0]['TotalCharge'] = $sum;
            $its[0]['Currency'] = $cur;
            $its[0]['SpentAwards'] = $spentAW . ' Miles';
        } else {
            return [
                'parsedData' => ['Itineraries' => $its, 'TotalCharge' => ['Amount' => $sum, 'Currency' => $cur, 'SpentAwards' => $spentAW . ' Miles']],
                'emailType'  => end($name) . ucfirst($this->lang),
            ];
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => end($name) . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href,'aeroplan.com')]")->length > 0) {
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

    private function parseEmail()
    {
        $this->logger->notice("[LANG]: " . $this->lang);
        $its = [];
        $pax = $this->http->FindNodes("//text()[{$this->starts($this->t('Your flight details may change'))}]/following::table[normalize-space(.)][1]//text()[normalize-space(.)]");
        $accNum = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Aeroplan Number'))}]/following::text()[normalize-space(.)][1]");
        $resDate = strtotime($this->http->FindSingleNode("//text()[{$this->starts($this->t('Transaction Date'))}]/following::text()[normalize-space(.)][1]", null, true, '/(\d{2,4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{1,2})/'));

        if ($resDate) {
            $this->date = $resDate;
        }
        $airs = [];
        $xpath = "//text()[{$this->starts($this->t('Departs'))}]/ancestor::tr[3]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $node) {
            $rl = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Departs'))}]/ancestor::tr[3]/descendant::text()[{$this->contains($this->t('booking reference'))}]/following::text()[normalize-space(.)][1]", $node, true, "#[A-Z\d]{5,}#");

            if (!$rl) {
                $rl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference Number'))}]/following::td[1]", null, true, "#[A-Z\d]{5,}#");
            }

            if (!$rl) {
                $rl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking Reference:'))}]/following::td[1]", null, true, "#[A-Z\d]{5,}#");
            }

            if (!$rl && (
                $this->http->XPath->query("//*[contains(normalize-space(),'See flight details below for booking references')]")->length > 0
                )) {
                $rl = CONFNO_UNKNOWN;
            }
            $airs[$rl][] = $node;
        }

        foreach ($airs as $rl => $nodes) {
            $it = ['Kind' => 'T', 'TripSegments' => []];
            $it['RecordLocator'] = $rl;
            $it['AccountNumbers'][] = $accNum;
            $it['ReservationDate'] = $resDate;
            $it['Passengers'] = $pax;

            foreach ($nodes as $root) {
                $date = $this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('DepOrArr'))}][1]/ancestor::td[1]/following-sibling::td[normalize-space(.)][last()]", $root);
                $date = strtotime($this->normalizeDate($date));

                if ($date) {
                    $this->date = $date;
                }
                $seg = [];
                $node = $this->http->FindSingleNode(".//descendant::text()[{$this->starts($this->t('Departs'))}]/preceding::text()[normalize-space(.)][1]", $root);

                if (preg_match("#([A-Z\d]{2})\s*(\d+)\s*(Operated\s+by\s+(.+?))?(?:\s+DBA\s+|$)#", $node, $m)) {
                    $seg['AirlineName'] = $m[1];
                    $seg['FlightNumber'] = $m[2];

                    if (isset($m[3]) && !empty($m[3])) {
                        $seg['Operator'] = $m[3];
                    }
                }

                $dateDep = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departs'))}]/following::text()[normalize-space(.)][1]", $root)));
                $seg['DepDate'] = strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departs'))}]/following::text()[normalize-space(.)][2]", $root), $dateDep);
                $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrives'))}]/following::text()[normalize-space(.)][1]", $root)));
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrives'))}]/following::text()[normalize-space(.)][2]", $root), $dateArr);

                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Departs'))}]/following::text()[normalize-space(.)][3]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                }
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Arrives'))}]/following::text()[normalize-space(.)][3]", $root);

                if (preg_match("#(.+)\s+\(([A-Z]{3})\)#", $node, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                }
                $seg['Duration'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight Duration'))}]/following::text()[normalize-space(.)][1]", $root);
                $seg['Aircraft'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight Duration'))}]/following::text()[normalize-space(.)][2]", $root);
                $seg['Meal'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Flight Duration'))}]/following::text()[normalize-space(.)][3]", $root);
                $seg['Cabin'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Class'))}]/preceding::text()[normalize-space(.)][1]", $root);
                $seg['BookingClass'] = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t('Class'))}][1]", $root, true, "#Class.?\s+([A-Z]{1,2})#");
                $it['TripSegments'][] = $seg;
            }
            $its[] = $it;
        }

        return $its;
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

    private function normalizeDate($date)
    {
        //$this->logger->error($date);
        $year = date('Y', $this->date);

        $in = [
            // Dim. 7 mars 2021
            '#^\s*\w+\.\s+(\d+)\s+(\w+)\s+(\d{4})\s*$#',
            // Thu July 16
            '#^\s*\w+\s+(\w+)\s+(\d+)\s*$#',
            // Dim. 7 mars
            '#^\s*\w+\.\s+(\d+)\s+(\w+)\s*$#',
        ];
        $out = [
            '$2 $1 $3',
            '$2 $1 ' . $year,
            '$2 $1 ' . $year,
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return $text . '="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
