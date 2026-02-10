<?php

namespace AwardWallet\Engine\easyjet\Email;

// parsers with similar formats: It3363267, Itinerary1

class Booking extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-1.eml, easyjet/it-1701482.eml, easyjet/it-2.eml, easyjet/it-2000463.eml, easyjet/it-2144522.eml, easyjet/it-2147802.eml, easyjet/it-2765895.eml, easyjet/it-3.eml, easyjet/it-3091133.eml, easyjet/it-3363267.eml, easyjet/it-3531765.eml, easyjet/it-3531768.eml, easyjet/it-4.eml, easyjet/it-4009480.eml, easyjet/it-4029220.eml, easyjet/it-4250776.eml, easyjet/it-4372471.eml, easyjet/it-4873272.eml, easyjet/it-5.eml, easyjet/it-5238750.eml, easyjet/it-5239033.eml, easyjet/it-6.eml, easyjet/it-6534787.eml, easyjet/it-6534799.eml, easyjet/it-7618121.eml, easyjet/it-7840674.eml, easyjet/it-7908044.eml, easyjet/it-8.eml";

    public $reSubject = [
        "da" => "easyJet Bookingnummer:",
        "de" => "easyJet Buchungsnummer:",
        "es" => "easyJet referencia de la reserva:",
        "it" => "easyJet numero di prenotazione:",
        "tr" => "easyJet rezervasyon referansı:",
        "pl" => "easyJet numer rezerwacji:",
        "ca" => "easyJet número de localitzador:",
        "fr" => "easyJet référence de réservation:",
        "pt" => "easyJet número de referência da reserva:",
        "en" => "easyJet booking reference:",
    ];

    public $reBody = 'easyJet';
    public $reBody2 = [
        "da" => "Tak for din booking:",
        "de" => "Vielen Dank für Ihre Buchung:",
        "es" => "Gracias por hacer su reserva.:",
        "it" => "Grazie per la prenotazione:",
        "tr" => "Rezervasyonunuz için teşekkür ederiz:",
        "pl" => "Dziękujemy za dokonanie rezerwacji:",
        "ca" => "Gràcies per la seva reserva:",
        "fr" => "Merci de votre ", // réservation - don't parse with...
        "pt" => "Obrigado por reservar:",
        "en" => "Thank you for booking:",
    ];

    public $lang = '';

    public static $dict = [
        'da' => [],
        'de' => [
            'Tak for din booking' => 'Vielen Dank für Ihre Buchung',
            'Passagerer'          => 'Passagiere',
            'Betalinger'          => 'Zahlungen',
            'Flyafgang'           => 'Flug',
            'Afg'                 => 'Abflug',
            'Ank'                 => 'Ankunft',
        ],
        'pt' => [
            'Tak for din booking' => 'Obrigado por reservar',
            'Passagerer'          => 'Passageiros',
            'Betalinger'          => 'Pagamentos',
            'Flyafgang'           => 'Voo',
            'Afg'                 => 'Part',
            'Ank'                 => 'Cheg',
        ],
        'es' => [
            'Tak for din booking' => 'Gracias por hacer su reserva',
            'Passagerer'          => 'Pasajeros',
            'Betalinger'          => 'Pagos',
            'Flyafgang'           => 'Vuelo',
            'Afg'                 => 'Sal',
            'Ank'                 => 'Lleg',
        ],
        'it' => [
            'Tak for din booking' => 'Grazie per la prenotazione',
            'Passagerer'          => 'Passeggeri',
            'Betalinger'          => 'Pagamenti',
            'Flyafgang'           => 'Volo',
            'Afg'                 => 'Par',
            'Ank'                 => 'Arr',
        ],
        'tr' => [
            'Tak for din booking' => 'Rezervasyonunuz için teşekkür ederiz',
            'Passagerer'          => 'Yolcular',
            'Betalinger'          => 'Ödemeler',
            'Flyafgang'           => 'Sefer',
            'Afg'                 => 'Kalk. ',
            'Ank'                 => 'Var.',
        ],
        'pl' => [
            'Tak for din booking' => 'Dziękujemy za dokonanie rezerwacji',
            'Passagerer'          => 'Pasażerowie',
            'Betalinger'          => 'Płatności',
            'Flyafgang'           => 'Lot',
            'Afg'                 => 'Wylot',
            'Ank'                 => 'Przylot',
        ],
        'ca' => [
            'Tak for din booking' => 'Gràcies per la seva reserva',
            'Passagerer'          => 'Passatgers',
            'Betalinger'          => 'Pagaments',
            'Flyafgang'           => 'Vol',
            'Afg'                 => 'Sort',
            'Ank'                 => 'Arr',
        ],
        'fr' => [
            'Tak for din booking' => 'Merci de votre ', // réservation
            'Passagerer'          => 'Passagers',
            'Betalinger'          => 'Paiements',
            'Flyafgang'           => 'Vol',
            'Afg'                 => 'Dép.',
            'Ank'                 => 'Arr.',
        ],
        'en' => [
            'Tak for din booking' => 'Thank you for booking',
            'Passagerer'          => 'Passengers',
            'Betalinger'          => 'Payments',
            'Flyafgang'           => 'Flight',
            'Afg'                 => 'Dep',
            'Ank'                 => 'Arr',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'email.easyJet.com') !== false
            || stripos($from, '@easyJet.com') !== false;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $this->reBody . '")]')->length < 1) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = true;
        $this->http->SetEmailBody(html_entity_decode($this->http->Response['body']));

        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $type = 'Html';
        $its = $this->parseEmail();

        if ((strpos($body, '</td>') === false && strpos($body, '</table>') === false)
                || (empty($its) || empty($its[0]) || empty($its[0]['TripSegments']))) {
            $type = 'Plain';
            $text = $parser->getPlainBody();

            if (empty($text)) {
                $text = $this->http->Response['body'];
            }
            $its = $this->parseEmailPlain($text);
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Booking' . $type . '_' . ucfirst($this->lang),
        ];
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

    protected function monthToEn($date = '')
    {
        $months = [
            'pl' => ['kwietnia', 'czerwca'],
        ];

        foreach ($months as $month) {
            $date = str_ireplace($month, [
                'April', 'June', // en
            ], $date);
        }

        return $date;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'{$this->t('Tak for din booking')}')]/following::text()[normalize-space(.)!=''][1]", null, true, "#[A-Z\d]+#");
        $pax = $this->http->FindNodes("//tr[normalize-space(.)='{$this->t('Passagerer')}']/following-sibling::tr");

        foreach ($pax as $p) {
            $it['Passengers'][] = $this->re("#\S+\s+(.+)#", $p);
        }
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Betalinger')}']/following::text()[normalize-space(.)!=''][1]")));
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[normalize-space(.)='{$this->t('Betalinger')}']/following::*[self::strong or self::b][1]"));

        if (!empty($tot['Total'])) {
            $it['TotalCharge'] = $tot['Total'];
            $it['Currency'] = $tot['Currency'];
        }

        $xpath = "//tr[not(.//tr) and contains(.,'{$this->t('Flyafgang')} ')]/preceding-sibling::tr[.//img or contains(./td[1],'.jpg') or contains(./td[1],'.gif') or contains(./td[1],'.png')][1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $seg = [];
            $flightNumber = $this->http->FindSingleNode("./following::text()[contains(.,'{$this->t('Flyafgang')} ')][1]", $root);

            if (preg_match("#\s+(\d+)#", $flightNumber, $m)) {
                $seg['FlightNumber'] = $m[1];
                $seg['AirlineName'] = 'U2';
            }

            $routes = $this->http->FindSingleNode("./td[normalize-space(.)!=''][last()]", $root);

            if (preg_match("#^\s*(.*?)\s+(?:til|nach|a|ile|do|à|Ã|to)\s+(.*?)\s*$#u", $routes, $m)) {
                $from = $m[1];
                $to = $m[2];

                if (preg_match('/^\s*(.+?)\s+\(([^)(]*Terminal[^)(]*)\)\s*$/iu', $from, $matches)) {
                    $seg['DepName'] = $matches[1];
                    $seg['DepartureTerminal'] = trim(str_ireplace('Terminal', '', $matches[2]));
                } else {
                    $seg['DepName'] = $from;
                }

                if (preg_match('/^\s*(.+?)\s+\(([^)(]*Terminal[^)(]*)\)\s*$/iu', $to, $matches)) {
                    $seg['ArrName'] = $matches[1];
                    $seg['ArrivalTerminal'] = trim(str_ireplace('Terminal', '', $matches[2]));
                } else {
                    $seg['ArrName'] = $to;
                }
            }

            $dateDep = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);
            $seg['DepDate'] = strtotime($this->normalizeDate($this->monthToEn($dateDep)));

            $dateArr = $this->http->FindSingleNode("./following-sibling::tr[2]", $root);
            $seg['ArrDate'] = strtotime($this->normalizeDate($this->monthToEn($dateArr)));

            $seats = $this->http->FindSingleNode("./following-sibling::tr[" . $this->starts($this->t("Seats")) . "][1]//tr[2]", $root);

            if (!empty($seats) && preg_match_all("#(?:^|\s|,)(\d{1,3}[A-Z])(?:\s|,|$)#", $seats, $m)) {
                $seg['Seats'] = $m[1];
            }

            if (!empty($seg['DepName'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (!empty($seg['ArrName'])) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function parseEmailPlain($text)
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];

        if (preg_match("#" . $this->t('Tak for din booking') . ".?:\s*([A-Z\d]{5,6})#", $text, $m)) {
            $it['RecordLocator'] = $m[1];
        }
        $posBegin = strpos($text, strtoupper($this->t('Betalinger')));

        if (empty($posBegin)) {
            $posBegin = strpos($text, '*' . $this->t('Betalinger') . '*');
        }
        $text = substr($text, $posBegin);
        $text = preg_replace(["#^((?:>\s+)+)#m", "#(<http:[^>]+>)#", "#(\[image:[^\]]+\])#", "#\*#"], '', $text);

        if (preg_match_all("#\n\s*([A-Z][^(\n]+)(?:\((TERMINAL\s.+)\))?\s+A\s+([A-Z][^(\n]+)(?:\((TERMINAL\s.+)\))?\n\s*" . $this->t('Afg') . "\s(.+)\n+\s+" . $this->t('Ank') . "\s(.+)\n\s*" . $this->t('Flyafgang') . "\s+(\d+)#u", $text, $m)) {
            if (preg_match("#" . strtoupper($this->t('Passagerer')) . ".*\n\s+((?:.+\n))\s*" . $m[1][0] . "#", $text, $mat)) {
                $it['Passengers'] = array_filter(explode("\n", $mat[1]));
            }

            foreach ($it['Passengers'] as $key => $value) {
                $it['Passengers'][$key] = trim($value);
            }

            foreach ($m[0] as $key => $flight) {
                $seg = [];
                $seg['FlightNumber'] = $m[7][$key];
                $seg['AirlineName'] = 'U2';
                $seg['DepName'] = $m[1][$key];

                if (!empty($m[2][$key])) {
                    $seg['DepartureTerminal'] = $m[2][$key];
                }
                $seg['ArrName'] = $m[3][$key];

                if (!empty($m[4][$key])) {
                    $seg['ArrivalTerminal'] = $m[4][$key];
                }
                $seg['DepDate'] = strtotime($this->normalizeDate($this->monthToEn($m[5][$key])));
                $seg['ArrDate'] = strtotime($this->normalizeDate($this->monthToEn($m[6][$key])));
                $seg['ArrCode'] = $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $it['TripSegments'][] = $seg;
            }
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*(\d+)\.(\d+)\.(\d+)\s*$#',
            '#^\s*.*?\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+)\s*$#u',
            '/^(\d{1,2})\/(\d{2})\/(\d{4})$/',
            '#^\s*(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+)\s*$#u', //16 OCTUBRE 2015 12:50`
        ];
        $out = [
            '$3-$2-$1',
            '$1 $2 $3 $4',
            '$1.$2.$3',
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach ($this->reBody2 as $lang => $reBody) {
            if (stripos($body, $reBody) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace("Dkr", "DKK", $node);
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("£", "GBP", $node);
        $node = preg_replace('#\bzl\b#', "HNL", $node);

        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = str_replace("-", "", $m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
