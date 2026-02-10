<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketReceipt extends \TAccountChecker
{
    public $mailFiles = "british/it-1.eml, british/it-1561575.eml, british/it-1594594.eml, british/it-1628586.eml, british/it-1725092.eml, british/it-1725093.eml, british/it-1966930.eml, british/it-1966931.eml, british/it-1966933.eml, british/it-1966934.eml, british/it-1970173.eml, british/it-2.eml, british/it-2159228.eml, british/it-2342693.eml, british/it-3.eml, british/it-4.eml, british/it-4862565.eml, british/it-5.eml, british/it-5088037.eml, british/it-5270866.eml, british/it-5470920.eml, british/it-5536657.eml, british/it-6.eml, british/it-6063014.eml, british/it-6063021.eml, british/it-7.eml, british/it-8.eml, british/it-9.eml";

    public $reSubject = [
        'BA e-ticket receipt',
        'BA changed e-ticket',
        'British Airways E-Ticket-Bestätigung',
        'Recibo do bilhete electrónico da BA',
        'Ricevuta del biglietto elettronico BA',
        'Reçu de billet électronique British Airways',
        'E-Ticket-Änderungsbestätigung',
        'Vielen Dank für Ihre Buchung bei British Airways',
        'kvitto på e-ticket från',
        'BA?e????????',
        'Recibo de billete electrónico de BA',
    ];

    public $typesCount = '1';

    public $lang = 'en';

    public static $dict = [
        'en' => [
            'BODY'              => 'booking reference',
            'Payment Total'     => ['Payment Total', 'Total new payment', 'Additional payment'],
            'Cabin'             => ['Cabin', 'Class'],
            'SpentAwards'       => ['Avios points debited', 'Avios debited'],
            'booking reference' => ['Booking reference', 'booking reference'],
        ],
        'de' => [
            'BODY'              => 'Buchungsreferenz',
            'booking reference' => 'Buchungsreferenz',
            'Passenger(s)'      => 'Fluggäste',
            'Ticket Number(s)'  => 'Ticketnummer(n)',
            //			'Membership No' => '',
            'Date'          => 'Datum',
            'Payment Total' => 'Gesamtbetrag',
            'Flight number' => 'Flugnummer',
            'From'          => 'Von',
            'To'            => 'Nach',
            'Depart'        => 'Abflug',
            'Arrive'        => 'Ankunft',
            'Cabin'         => 'Kabine',
            'Operated by'   => 'Durchgeführt von',
            //			'Seat selection' => '',
        ],
        'ru' => [
            'BODY'              => 'Номер бронирования',
            'booking reference' => 'Номер бронирования',
            'Passenger(s)'      => 'Пассажир(ы)',
            'Ticket Number(s)'  => 'Номера билетов',
            'Membership No'     => 'Номер участника',
            'Date'              => 'Дата',
            'Payment Total'     => 'Общая сумма платежа',
            'Flight number'     => 'Номер рейса',
            'Terminal'          => 'Терминал',
            'From'              => 'Из',
            'To'                => 'В',
            'Depart'            => 'Вылет',
            'Arrive'            => 'Прибытие',
            'Cabin'             => 'Салон',
            'Operated by'       => 'Авиакомпания',
            'Seat selection'    => 'Выбор места',
            'SpentAwards'       => 'Списанные Баллы Avios',
        ],
        'es' => [
            'BODY'              => 'Referencia de la reserva',
            'booking reference' => 'Referencia de la reserva',
            'Passenger(s)'      => 'Pasajero(s)',
            'Ticket Number(s)'  => 'Número(s) del/de los billete(s)',
            //			'Membership No' => '',
            'Date'          => 'Fecha',
            'Payment Total' => 'Pago total',
            'Flight number' => 'Número de vuelo',
            'Terminal'      => 'Terminal',
            'From'          => 'Desde',
            'To'            => 'A',
            'Depart'        => 'Salida',
            'Arrive'        => 'Llegada',
            'Cabin'         => 'Cabina',
            'Operated by'   => 'Operado por',
            //			'Seat selection' => '',
            //			'SpentAwards' => '',
        ],
        'pt' => [
            'BODY'              => 'Referência de reserva',
            'booking reference' => 'Referência de reserva',
            'Passenger(s)'      => 'Passageiro(s)',
            'Ticket Number(s)'  => 'Número do(s) bilhete(s)',
            'Membership No'     => 'N.º de membro',
            'Payment Total'     => 'Total de pagamento',
            'Date'              => 'Data',
            'Flight number'     => 'Número do voo',
            'From'              => 'De',
            'To'                => 'Para',
            'Depart'            => 'Partida',
            'Arrive'            => 'Chegada',
            'Cabin'             => 'Cabine',
            'Operated by'       => 'Operado por',
            //			'Seat selection' => '',
            'SpentAwards' => 'Pontos Avios debitados',
        ],
        'it' => [
            'BODY'              => 'Codice di prenotazione',
            'booking reference' => 'Codice di prenotazione',
            'Passenger(s)'      => 'Passeggero/i',
            'Ticket Number(s)'  => 'Numero biglietto/i',
            'Membership No'     => 'N.º de membro',
            'Payment Total'     => 'Totale pagamento',
            'Date'              => 'Data',
            'Flight number'     => 'Numero di volo',
            'From'              => 'Da',
            'To'                => 'A',
            'Depart'            => 'Partenza',
            'Arrive'            => 'Arrivo',
            'Cabin'             => 'Classe di viaggio',
            'Operated by'       => 'Operato da',
            //			'Seat selection' => '',
        ],
        'fr' => [
            'BODY'              => 'Référence de réservation',
            'booking reference' => 'Référence de réservation',
            'Passenger(s)'      => 'Passager(s)',
            'Ticket Number(s)'  => 'Nombre de billet(s)',
            //			'Membership No' => '',
            'Payment Total'  => 'Total du règlement',
            'Date'           => 'Date',
            'Flight number'  => 'Numéro de vol',
            'From'           => 'De',
            'To'             => 'A',
            'Depart'         => 'Départ',
            'Arrive'         => 'Arrivée',
            'Cabin'          => 'Classe de voyage',
            'Operated by'    => 'Exploité par',
            'Seat selection' => 'Choix des sièges',
        ],
        'sv' => [
            'BODY'              => 'Bokningsnummer',
            'booking reference' => 'Bokningsnummer',
            'Passenger(s)'      => 'Passagerare',
            'Ticket Number(s)'  => 'Biljettnummer',
            //			'Membership No' => '',
            'Payment Total' => 'Total betalning',
            'Date'          => 'Datum',
            'Flight number' => 'Flygnummer',
            'From'          => 'Från',
            'To'            => 'Till',
            'Depart'        => 'Avgång',
            'Arrive'        => 'Ankomst',
            'Cabin'         => 'Kabin',
            'Operated by'   => 'Trafikeras av',
            //			'Seat selection' => '',
        ],
        'ja' => [
            'BODY'              => '予約番号',
            'booking reference' => '予約番号',
            'Passenger(s)'      => 'ご搭乗者',
            'Ticket Number(s)'  => '航空券番号',
            //			'Membership No' => '',
            'Payment Total' => 'お支払い合計金額',
            'Date'          => '日付',
            'Flight number' => '便名',
            'Terminal'      => 'ターミナル',
            'From'          => '出発地',
            'To'            => '目的地',
            'Depart'        => '出発',
            'Arrive'        => '到着',
            'Cabin'         => 'キャビン',
            'Operated by'   => '運航航空会社',
            //			'Seat selection' => '',
        ],
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $parser->getHTMLBody();

        if ($this->http->XPath->query("//a[contains(@href,'ba.com')]")->length > 0) {
            foreach (self::$dict as $lang => $reBody) {
                if (stripos($text, $reBody['BODY']) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@ba.com") !== false || stripos($from, ".ba.com") !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->Subj = $parser->getSubject();
        $this->http->FilterHTML = false;
        $itineraries = [];
        $NBSP = chr(194) . chr(160);
        $body = str_replace($NBSP, ' ', html_entity_decode($this->http->Response['body']));
        $this->AssignLang($body);

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ETicketReceipt_' . $this->lang,
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function IsEmailAggregator()
    {
        return false;
    }

    protected function normalizeDate($string)
    {
        if (preg_match('/^(\d{1,2})\s+([^\d\s]{3,})\s+(\d{4})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $time = '00:00';
        } elseif (preg_match('/^(\d{1,2})\s+([^\d\s]{3,})\s+(\d{4})\s+(\d{1,2}:\d{2})$/', $string, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            $time = $matches[4];
        }

        if ($day && $month && $year && $time) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . '.' . $year . ', ' . $time;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year . ', ' . $time;
        }

        return false;
    }

    private function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[" . $this->getXPath($this->t('booking reference'), 'normalize-space(.)') . "]/following::*[1])[1]");

        if ($reservationDate = $this->http->FindSingleNode("//*[contains(normalize-space(text()),'" . $this->t('Date') . "')]/ancestor::tr[1]/td[2]", null, true, '/(\d{1,2}\s*\S+\s*\d{4})/')) {
            $it['ReservationDate'] = strtotime($this->normalizeDate($reservationDate));
        }

        $passengers = $this->http->FindNodes("//*[contains(normalize-space(text()),'" . $this->t('Passenger(s)') . "')]/ancestor::table[1]//td[2]");
        $passengerValues = array_values(array_filter($passengers));

        if (!empty($passengerValues[0])) {
            $it['Passengers'] = $passengerValues;
        }

        $ticketNumbers = $this->http->FindNodes("//*[contains(normalize-space(text()),'" . $this->t('Ticket Number(s)') . "')]/ancestor::table[1]//td[2]", null, "#([\d\-]{10,})\s*\(?#");
        $ticketNumberValues = array_values(array_filter($ticketNumbers));

        if (!empty($ticketNumberValues[0])) {
            $it['TicketNumbers'] = $ticketNumberValues;
        }

        $membershipNo = $this->http->FindSingleNode("//*[contains(normalize-space(text()),'" . $this->t('Membership No') . "')]/ancestor::tr[1]//td[2]");

        if ($membershipNo) {
            $it['AccountNumbers'][] = $membershipNo;
        }

        $it['TotalCharge'] = $this->http->FindSingleNode("(//*[" . $this->getXPath($this->t('Payment Total')) . "]/ancestor::tr[1]//td[2])[1]", null, true, "#[\d\.\,]+#");

        $it['Currency'] = $this->http->FindSingleNode("(//*[" . $this->getXPath($this->t('Payment Total')) . "]/ancestor::tr[1]//td[2])[1]", null, true, "#[A-Z]{3}#");

        $it['SpentAwards'] = $this->http->FindSingleNode("//*[" . $this->getXPath($this->t('SpentAwards')) . "]/ancestor::tr[1]//td[2]", null, true, "#[\d\.\,]+#");

        $xpath = "//*[contains(normalize-space(text()),'" . $this->t('Flight number') . "')]/ancestor::table[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $this->logger->info('Segments found by: ' . $xpath);

        foreach ($segments as $i => $root) {
            $seg = [];
            $node = $this->http->FindSingleNode(".//tr[contains(normalize-space(.),'" . $this->t('Flight number') . "')]/td[2]", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            $airportDep = $this->http->FindSingleNode(".//tr[contains(normalize-space(.),'" . $this->t('From') . "')]/td[2]", $root);

            if (preg_match('/(.+?)\s*' . $this->t('Terminal') . '(.+?)?$/', $airportDep, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepartureTerminal'] = $m[2];
            } else {
                $seg['DepName'] = $airportDep;
            }
            $airportArr = $this->http->FindSingleNode(".//tr[td[position()=1 and contains(normalize-space(.),'" . $this->t('To') . "') and not(contains(normalize-space(.),'" . $this->t('Depart') . "')) and not(contains(normalize-space(.),'" . $this->t('Arrive') . "')) and not(contains(normalize-space(.),'" . $this->t('Seat selection') . "'))]]/td[2]", $root); //for russian|italian|etc language not(contains...)

            if (preg_match('/(.+?)\s*' . $this->t('Terminal') . '(.+?)?$/', $airportArr, $m)) {
                $seg['ArrName'] = $m[1];
                $seg['ArrivalTerminal'] = $m[2];
            } else {
                $seg['ArrName'] = $airportArr;
            }

            if ($dateDep = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t('Depart') . "']/ancestor::tr[1]/td[2]", $root, null, '/(\d{1,2}\s*\S+\s*\d{4}\s*\d{1,2}:\d{2})/')) {
                $seg['DepDate'] = strtotime($this->normalizeDate($dateDep));
            }

            if ($dateArr = $this->http->FindSingleNode(".//tr[contains(normalize-space(.),'" . $this->t('Arrive') . "')]/td[2]", $root, null, '/(\d{1,2}\s*\S+\s*\d{4}\s*\d{1,2}:\d{2})/')) {
                $seg['ArrDate'] = strtotime($this->normalizeDate($dateArr));
            }
            $seg['Cabin'] = $this->http->FindSingleNode(".//tr[" . $this->getXPath($this->t('Cabin'), '.') . "]/td[2]", $root);
            $seg['Operator'] = $this->http->FindSingleNode(".//tr[contains(normalize-space(.),'" . $this->t('Operated by') . "')]/td[2]", $root);

            $seats = $this->http->FindNodes(".//tr[contains(normalize-space(.),'" . $this->t('Seat selection') . "')]/preceding::tr[1]/following-sibling::tr/td[2]", $root, '#\s(\d[\d\w]{1,2})\s#');
            $seatValues = array_values(array_filter($seats));

            if (!empty($seatValues[0])) {
                $seg['Seats'] = implode(', ', $seatValues);
            }
            $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;

            if (isset($seg['DepName']) && isset($seg['ArrName'])) {//british/it-6063021.eml
                $it['TripSegments'][] = array_filter($seg);
            }
        }
        $it = array_filter($it);
        $itineraries[] = $it;
    }

    private function getXPath($s, $node = 'text()')
    {
        $res = '';

        if (is_array($s)) {
            $r = array_map(function ($s) use ($node) {
                return "contains(" . $node . ", '" . $s . "')";
            }, $s);
            $res = implode(' or ', $r);
        } else {
            $res = "contains(" . $node . ", '" . $s . "')";
        }

        return $res;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        foreach (self::$dict as $lang => $reBody) {
            if (stripos($body, $reBody['BODY']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }
}
