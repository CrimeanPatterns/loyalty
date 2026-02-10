<?php

namespace AwardWallet\Engine\austrian\Email;

class BookingHtml2016 extends \TAccountChecker
{
    public $mailFiles = "austrian/it-10166356.eml, austrian/it-10202499.eml, austrian/it-10229327.eml, austrian/it-10237035.eml, austrian/it-10345421.eml,austrian/it-5150354.eml, austrian/it-5191775.eml, austrian/it-5660489.eml, austrian/it-6163088.eml, austrian/it-6549140.eml";

    private $result = [];
    private $lang = '';

    private static $detectSubject = [
        'en'  => 'Austrian Service Confirmation', // uk, cs, fr, es
        'en2' => 'Your Austrian rebooking confirmation', // uk
        'ro'  => 'Confirmare Austrian Service',
        'sv'  => 'Austrian Service Confirmation',
        'it'  => 'Conferma di Austrian Service',
        'de'  => 'Ihre Austrian Umbuchungsbestaetigung',
    ];

    private static $detectBody = [//order is important
        'uk' => 'Повернення', //mix with en
        'cs' => 'Cestující', //mix with en
        'fr' => 'Récapitulatif du prix', //mix with en
        'ro' => 'pentru rezervarea Austrian', //mix with en
        'sv' => 'Du kan kontakta oss via telefon',
        'es' => 'Puede ponerse en contacto ',
        'it' => ['Il tuo cambio di prenotazione Austrian', 'Ti ringraziamo per il tuo cambio di prenotazione', 'Grazie per aver prenotato un servizio Austrian'],
        'de' => ['vielen Dank für Ihre', 'Der Buchungscode ist'],
        'en' => ['You can contact us', 'Austrian service booking'], // by phone
    ];
    private $dict = [
        'en' => [
            'Your booking code is:' => ['Your booking code is:', 'Your booking code has not changed:', 'Your booking code has not changed and is:'],
            'Fare:'                 => ['Fare:', 'Tariff:'],
            //			'operated by' => '',
            'Total' => ['Total', 'Total price'],
        ],
        'uk' => [
            //'Your booking code is:' => '',
            'Passenger'  => 'Пасажири',
            'Adult'      => 'Дорослий',
            'Total'      => 'Загалом',
            'From:'      => '3:',
            'To:'        => 'До:',
            'Departure:' => 'Виліт:',
            'Arrival:'   => 'Приліт:',
            'Flight:'    => 'Рейс:',
            'Fare:'      => 'Тариф:',
            //			'operated by' => '',
            //			'Seat preference:' => '',
        ],
        'ro' => [
            'Your booking code is:' => 'Codul de rezervare este',
            'Passenger'             => 'Pasager',
            'Adult'                 => 'Adult',
            'Total'                 => 'Total',
            'From:'                 => 'De la:',
            'To:'                   => 'Catre:',
            'Departure:'            => 'Decolare:',
            'Arrival:'              => 'sosire:',
            'Flight:'               => 'Zbor:',
            'Fare:'                 => 'Tarif:',
            'operated by'           => 'operat de',
            //			'Seat preference:' => '',
        ],
        'it' => [
            'Your booking code is:' => ['non è cambiato ed è:', 'Il suo codice di prenotazione è:'],
            'Passenger'             => 'Passeggeri',
            'Adult'                 => 'Adulto',
            'Total'                 => 'Prezzo totale',
            'From:'                 => 'Da:',
            'To:'                   => 'A:',
            'Departure:'            => 'Partenza:',
            'Arrival:'              => 'Arrivo:',
            'Flight:'               => 'Volo:',
            'Fare:'                 => 'Tariffa:',
            'operated by'           => 'operated by',
            'Seat preference:'      => 'Preferenza posto:',
        ],
        'sv' => [
            'Passenger'  => 'Passagerare',
            'Total'      => 'Totalt',
            'From:'      => 'Från:',
            'To:'        => 'Till:',
            'Departure:' => 'Avgång:',
            'Arrival:'   => 'Ankomst:',
            'Flight:'    => 'Flyg:',
            'Fare:'      => 'Pris:',
            //			'operated by' => '',
            'Seat preference:' => 'Önskad sittplats:',
        ],
        'de' => [
            'Your booking code is:' => ['Der Buchungscode ist:', 'Der Reservierungscode hat sich nicht geändert und lautet:', 'Der Buchungscode ist:'],
            'Passenger'             => 'Passagier',
            'Adult'                 => 'Erwachsener',
            'Total'                 => 'Gesamtbetrag',
            'From:'                 => 'Von:',
            'To:'                   => 'Nach:',
            'Departure:'            => 'Abflug:',
            'Arrival:'              => 'Ankunft:',
            'Flight:'               => 'Flug:',
            'Fare:'                 => 'Tarif:',
            //			'operated by' => '',
            'Seat preference:' => 'Sitzplatzauswahl:',
        ],
        'cs' => [
            'Your booking code is:' => 'Your booking code is:',
            'Passenger'             => 'Cestující',
            'Adult'                 => 'Dospělý',
            'Total'                 => 'Total',
            'From:'                 => 'From:',
            'To:'                   => 'To:',
            'Departure:'            => 'Departure:',
            'Arrival:'              => 'Arrival:',
            'Flight:'               => 'Flight:',
            'Fare:'                 => 'Fare:',
            'operated by'           => 'operated by',
            //			'Seat preference:' => '',
        ],
        'fr' => [
            'Your booking code is:' => 'Your booking code is:',
            //			'Passenger' => '',
            'Adult'       => 'Adulte',
            'Total'       => 'Total',
            'From:'       => 'From:',
            'To:'         => 'To:',
            'Departure:'  => 'Departure:',
            'Arrival:'    => 'Arrival:',
            'Flight:'     => 'Flight:',
            'Fare:'       => 'Fare:',
            'operated by' => 'operated by',
            //			'Seat preference:' => '',
        ],
        'es' => [
            'Your booking code is:' => 'Your booking code is:',
            'Passenger'             => 'Pasajero',
            'Adult'                 => 'Adulto',
            'Total'                 => 'Total',
            'From:'                 => 'De:',
            'To:'                   => 'A:',
            'Departure:'            => 'Salida:',
            'Arrival:'              => 'Llegada:',
            'Flight:'               => 'Vuelo:',
            'Fare:'                 => 'Tarifa:',
            'operated by'           => 'operated by',
            //			'Seat preference:' => '',
        ],
    ];

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

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->lang = $this->detect($parser->getHTMLBody(), self::$detectBody);

        if (empty($this->lang)) {
            return null;
        }

        $text = $parser->getHTMLBody();
        $this->parseReservations($this->htmlToText($text, true));

        return [
            'parsedData' => ['Itineraries' => [$this->result]],
            'emailType'  => 'BookingHtml2016' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectSubject as $key => $value) {
            if (stripos($headers['subject'], '$value') !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return (stripos($parser->getHTMLBody(), 'austrian') !== false || $this->http->XPath->query("//img[@alt='Austrian']")->length > 0) && $this->detect($parser->getHTMLBody(), self::$detectBody);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@austrian.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectBody);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectBody);
    }

    //====================================
    // Auxiliary methods
    //====================================

    protected function detect($haystack, $arrayNeedle)
    {
        foreach ($arrayNeedle as $lang => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $lang;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $lang;
            }
        }

        return null;
    }

    private function t($s)
    {
        return !isset($this->dict[$this->lang]) || !isset($this->dict[$this->lang][$s]) ? $s : $this->dict[$this->lang][$s];
    }

    private function parseReservations($text)
    {
        $this->result['Kind'] = 'T';

        if (preg_match("/{$this->opt($this->t('Your booking code is:'))}\s*([A-Z\d]{5,6})/", $text, $m)) {
            $this->result['RecordLocator'] = $m[1];
        } else {
            $this->result['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking code is:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,6})#");
        }

        if (!empty($this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking code is:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "/#\s*PNR\s*#/"))) {
            $this->result['RecordLocator'] = CONFNO_UNKNOWN;
        }

        $this->result['Passengers'] = $this->http->FindNodes("//td[{$this->starts($this->t('Passenger'))}]/following-sibling::td[1]", null, "#(.+?)(?:\s*\(|$)#");

        if (count($this->result['Passengers']) === 0) {
            $this->result['Passengers'] = $this->http->FindNodes("//td[{$this->starts($this->t('Passenger'))}]/ancestor::tr[1]/following-sibling::tr[{$this->contains($this->t('Adult'))}]/td[2][string-length(translate(.,'0123456789 ',''))>5]");
        }

        if (preg_match("/{$this->opt($this->t('Total'))}\s*:\s*(?<Cur>[^\s\d]{1,5})\s*(?<Total>\d[\d., ]*)/us", $text, $m) || preg_match("/{$this->opt($this->t('Total'))}\s*:\s*(?<Total>\d[\d\.\, ]*)\s*(?<Cur>[^\s\d]{1,5})(\s|$)/us", $text, $m)) {
            $this->result['Currency'] = $this->currency($m['Cur']);
            $m['Total'] = str_replace([',', ' '], ['.', ''], trim($m['Total']));

            if (is_numeric($m['Total'])) {
                $this->result['TotalCharge'] = str_replace(',', '.', trim($m['Total']));
            }
        }

        // Segments
        foreach ($this->splitter("/^\s*({$this->t('From:')}[^\@]+?{$this->t('To:')}[^\@]+?{$this->t('Departure:')})/ums", $text) as $value) {
            $this->result['TripSegments'][] = $this->parseSegment($value);
        }
    }

    private function parseSegment($text)
    {
        $segment = [];
        $re = '/';
        $re .= "^\s*{$this->t('From:')}\s*(?<DepName>.+?)\s*{$this->t('To:')}\s*(?<ArrName>.+?)\s*";
        $re .= "{$this->t('Departure:')}\s*[\w']+,(?<DepDate>.+?\d+:\d+(?:\s*[ap]m)?).*?\s*{$this->t('Arrival:')}\s*[\w']+,(?<ArrDate>.+?\d+:\d+(?:\s*[ap]m)?)(?<nextDay>\s*\+\d+)?.*?";
        $re .= "{$this->t('Flight:')}\s*(?<AName>[A-Z\d]{2})\s*(?<FNum>\d+)\s*(?<Operator>.+?)\s*{$this->opt($this->t('Fare:'))}\s*.+?\/\s*(?<BClass>\w+)";
        $re .= '/uis';

        if (preg_match($re, $text, $m)) {
            $segment['DepName'] = $this->normalizeText($m['DepName']);
            $segment['ArrName'] = $this->normalizeText($m['ArrName']);
            $segment['DepDate'] = strtotime($this->normalizeDate($m['DepDate']), false);
            $segment['ArrDate'] = strtotime($this->normalizeDate($m['ArrDate']), false);

            if (!empty($m['nextDay'])) {
                $segment['ArrDate'] = strtotime(trim($m['nextDay']) . ' day', $segment['ArrDate']);
            }

            $segment['AirlineName'] = $this->normalizeText($m['AName']);
            $segment['FlightNumber'] = $m['FNum'];

            if (trim($m['Operator']) !== false) {
                if (preg_match("#\(([A-Z\d]{2})\)#s", $m['Operator'], $mat)) {
                    if ($mat[1] !== $segment['AirlineName']) {
                        $segment['Operator'] = $mat[1];
                    }
                } elseif (preg_match("#" . $this->opt($this->t('operated by')) . "\s+(.+)\b#s", $m['Operator'], $mat)) {
                    $segment['Operator'] = $this->normalizeText($mat[1]);
                } else {
                    $segment['Operator'] = $this->normalizeText($m['Operator']);
                }
            }
            $segment['BookingClass'] = $m['BClass'];
            $segment['DepCode'] = $segment['ArrCode'] = TRIP_CODE_UNKNOWN;

            $seats = array_filter($this->http->FindNodes("//td[" . $this->eq($this->t('Seat preference:')) . "]/following::td[1]//text()[normalize-space()]", null, "#" . $segment['AirlineName'] . '\s*' . $segment['FlightNumber'] . ":\s*(\d{1,3}[A-Z])(?:$|\W)#"));

            if (!empty($seats)) {
                $segment['Seats'] = $seats;
            }
        }

        return $segment;
    }

    private function normalizeDate($str)
    {
        //		$this->logger->info($str);
        $in = [
            "#^\s*([^\s\d]+?)\. (\d+), (\d{4})\s*$#", //Dec. 16, 2015
            "#^\s*([^\s\d]+?)\. (\d+), (\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#u", //Dec. 16, 2015 12:30
            "#^\s*(\d+)\. (\w+)\. (\d{4})\s*$#u", //9. Aug. 2014
            "#^\s*(\d+)\. (\w+)\.? (\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#u", //9. Aug. 2014 12:30
            "#^\s*(\d+\.\d+\.\d{4})\s*$#", //14.08.2013
            "#^\s*(\d+\.\d+\.\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#", //14.08.2013 12:30
            "#^\s*(\d+)-\w (\w+)\.? (\d{4})\s*$#u", //7-е Трав. 2015
            "#^\s*(\d+)-\w (\w+)\.? (\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#u", //7-е Трав. 2015 12:30
            "#^\s*(\d+) (\w+)\.? (\d{4})\s*$#u", //02 Mar. 2016
            "#^\s*(\d+) (\w+)\.? (\d{4})\s+(\d+:\d+(?:\s*[ap]m)?)\s*$#u", //02 Mar. 2016 12:30
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $3 $4",
            "$1 $2 $3",
            "$1 $2 $3 $4",
            "$1",
            "$1, $2",
            "$1 $2 $3",
            "$1 $2 $3 $4",
            "$1 $2 $3",
            "$1 $2 $3 $4",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

        return $str;
    }

    private function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function htmlToText($string, $view = false)
    {
        $text = str_replace(' ', ' ', preg_replace('/<[^>]+>/', "\n", html_entity_decode($string)));

        if ($view) {
            return preg_replace('/\n{2,}/', "\n", $text);
        }

        return $text;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
            'Lv'=> 'BGN',
            'Kc'=> 'CZK',
            'Ft'=> 'HUF',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (trim($s) == $f) {
                return $r;
            }
        }

        return null;
    }
}
