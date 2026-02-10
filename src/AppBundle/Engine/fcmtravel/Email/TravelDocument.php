<?php

namespace AwardWallet\Engine\fcmtravel\Email;

class TravelDocument extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-11998879.eml, fcmtravel/it-8614054.eml, fcmtravel/it-8626653.eml";

    public $reFrom = "itinerary@pcsoffice02.de";
    public $reBody = [
        'fr' => ['Reference de dossier', 'Vol'],
    ];
    public $reSubject = [
        'Travel Document - E-Ticket and Itinerary Receipt',
        'RAPPEL CONCERNANT LA RESERVATION',
    ];
    public $lang = 'fr';
    public $pdf;
    public $pdfNamePattern = "\d{6}_.*\.pdf";
    public static $dict = [
        'fr' => [
            //			'Reference de dossier' => '',
            //			'Reference de la réservation aérienne' = > '',
            //			'Voyageur fréquent' => '',
            //			'Ticket number' => '',
            //			'Vol' => '',
            //			'opéré par' => '',
            //			'Classe' => '',
            //			'Durée de vol' => '',
            //			'Avion' => '',
            //			'Siège' => '',
            //			'A bord' => '',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (stripos($this->http->Response['body'], $reBody[0]) !== false && stripos($this->http->Response['body'], $reBody[1]) !== false) {
                $this->lang = $lang;

                break;
            }
        }
        $its = $this->parseEmailHtml();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'TravelDocument',
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, "fcm.travel") !== false) {
            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
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

    protected function parseEmailHtml()
    {
        $its = [];

        $it['Kind'] = 'T';

        $Passengers = $this->http->FindNodes('//text()[contains(., "Travel Document")]/ancestor::tr[1]/following-sibling::tr[string-length()>10][1]//text()');
        $Passengers = $this->http->FindNodes('//text()[contains(., "Travel Document")]/ancestor::tr[1]/following-sibling::tr[string-length()>10][1]//text()');
        $Passengers = implode("\n", $Passengers);
        $Passengers = substr($Passengers, 0, stripos($Passengers, $this->t('Reference de dossier')));

        if (preg_match_all("#\n([A-Z][A-Z\/[:space:]\(\)]+)\n#", $Passengers, $m)) {
            foreach ($m[1] as $value) {
                $it['Passengers'][] = trim($value);
            }
        }

        if (preg_match_all("#\n" . $this->t('Voyageur fréquent') . "\s*:\s*([A-Z\d]+)\n#", $Passengers, $m)) {
            $it['AccountNumbers'] = $m[1];
        }
        $it['TicketNumbers'] = $this->http->FindNodes('//text()[contains(., "' . $this->t('Ticket number') . '")]/ancestor::td[1]/following-sibling::td', null, "#[\d\- ]+#");
        $it['TicketNumbers'] = array_unique($it['TicketNumbers']);
        $RecordLocatorsAr = $this->http->FindNodes('//text()[contains(., "' . $this->t('Reference de dossier') . '")]/ancestor::td[1]/following-sibling::td[string-length()>2][1]//text()[normalize-space(.)]', null, "#((?:[A-Z\d]{2}\/)?[A-Z\d]{5,6})#");
        $RecordLocators[1] = array_shift($RecordLocatorsAr);

        if (preg_match_all("#^([A-Z\d]{2})\/([A-Z\d]{5,6})$#m", implode("\n", $RecordLocatorsAr), $m)) {
            foreach ($m[1] as $key => $value) {
                $RecordLocators[$value] = $m[2][$key];
            }
        }

        foreach ($RecordLocators as $key => $value) {
            $its[$key] = $it;
            $its[$key]['RecordLocator'] = $value;
        }

        $date = strtotime($this->http->FindSingleNode('//text()[contains(., "Date:")]/following::text()[1]'));

        if (!($date)) {
            $date = strtotime(preg_replace("#(\d+)(\D+)(\d+)#", "\\1 \\2 20\\3", $this->http->FindSingleNode('//text()[contains(normalize-space(.), "Date of Issue:")]/following::text()[normalize-space(.)][1]')));
        }
        $year = date("Y", $date);
        $xpath = '//*[contains(text(), "' . $this->t('Vol') . '")]/ancestor::table[1]/ancestor::tr[1]';

        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            return false;
        }

        foreach ($roots as $root) {
            $seg = [];
            $flightInfo = implode("\n", $this->http->FindNodes('.//text()', $root));
            $flightInfo = str_replace(html_entity_decode("&#8204;"), '', $flightInfo);

            if (strpos($flightInfo, "Ã") !== false) {
                $flightInfo = iconv("utf-8", "iso-8859-1//IGNORE", $flightInfo);
            }

            if (preg_match("#" . $this->t('Vol') . "\s*([A-Z\d]{2})\s*(\d+)(?:\s+" . $this->t('opéré par') . ":\s*(.+))?#u", $flightInfo, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $seg['Operator'] = $m[3];
                }
            }

            if (preg_match("#\n{2,}\s*\S+\s+(\d+\.\s+\S+)\s+(\d+:\d+)\s*h\s+([A-Z\d\s-,._]+)\s+(?:\S+\s+(\d+\.\s+\S+)\s+)?(\d+:\d+)\s*h\s+([A-Z\d\s-,._]+)\n{2,}#u", $flightInfo, $m)) {
                $seg['DepDate'] = strtotime($this->monthTranslate($m[1]) . ' ' . $year . ' ' . $m[2]);

                if ($seg['DepDate'] < $date) {
                    $seg['DepDate'] = strtotime("+1 year", $seg['DepDate']);
                }

                if (preg_match("#\s*([\s\S]+)\nTERMINAL\s+(.+)#", $m[3], $mat)) {
                    $seg['DepName'] = str_replace("\n", " ", $mat[1]);
                    $seg['DepartureTerminal'] = $mat[2];
                } else {
                    $seg['DepName'] = trim(str_replace("\n", " ", $m[3]));
                }

                if (!empty($m[4])) {
                    $seg['ArrDate'] = strtotime($this->monthTranslate($m[4]) . ' ' . $year . ' ' . $m[5]);
                } else {
                    $seg['ArrDate'] = strtotime($this->monthTranslate($m[1]) . ' ' . $year . ' ' . $m[5]);
                }

                if ($seg['ArrDate'] < $date) {
                    $seg['ArrDate'] = strtotime("+1 year", $seg['ArrDate']);
                }

                if (preg_match("#\s*([\s\S]+)\nTERMINAL\s+(.+)#", $m[6], $mat)) {
                    $seg['ArrName'] = str_replace("\n", " ", $mat[1]);
                    $seg['ArrivalTerminal'] = $mat[2];
                } else {
                    $seg['ArrName'] = trim(str_replace("\n", " ", $m[6]));
                }
            }

            if (preg_match("#" . $this->t('Classe') . ":\s+([A-Z]{1,2}) - ([^,\n]+)(,|\n)#u", $flightInfo, $m)) {
                $seg['BookingClass'] = $m[1];
                $seg['Cabin'] = $m[2];
            }

            if (preg_match("#" . $this->t('Durée de vol') . ":\s+(\d+:\d+.*)#u", $flightInfo, $m)) {
                $seg['Duration'] = $m[1];
            }

            if (preg_match("#" . $this->t('Avion') . ":\s+\s+(.+)#u", $flightInfo, $m)) {
                $seg['Aircraft'] = $m[1];
            }

            if (preg_match("#" . $this->t('Siège') . ":\s+(.+)#u", $flightInfo, $m)) {
                $seg['Seats'] = explode(",", $m[1]);
            }

            if (preg_match("#" . $this->t('A bord') . ":\s+(.+)#u", $flightInfo, $m)) {
                $seg['Meal'] = $m[1];
            }

            if (isset($seg['DepDate']) && isset($seg['ArrDate'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (empty($seg)) {
                continue;
            }

            if (isset($seg['AirlineName']) && isset($its[$seg['AirlineName']])) {
                $its[$seg['AirlineName']]['TripSegments'][] = $seg;
            } else {
                $its[1]['TripSegments'][] = $seg;
            }
        }
        $its = array_filter($its, function ($v) {
            if (!isset($v['TripSegments']) || count($v['TripSegments']) == 0) {
                return false;
            }

            return true;
        });
        $its = array_values($its);

        return $its;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function monthTranslate($str)
    {
        if (preg_match("#(\d+)\.\s*([^\d\s]+)#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . ' ' . $en;
            }
        }

        return $str;
    }
}
