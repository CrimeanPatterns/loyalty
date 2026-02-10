<?php

namespace AwardWallet\Engine\airchina\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationEmailPdf extends \TAccountChecker
{
    public $mailFiles = "airchina/it-299318648.eml, airchina/it-301852762.eml, airchina/it-307961543.eml, airchina/it-644136953.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'it' => [ // before it
            'Travel Itinerary/Invoice'       => ['Itinerario di viaggio/Fattura'],
            'Booking reference:'             => 'Riferimento prenotazione:',
            'Air China confirmation number:' => 'Numero di conferma Air China:',
            'Itinerary Details'              => 'Itinerary Details / Dettagli itinerario',
            'Operated By:'                   => 'Operato da:',
            'Passenger Details'              => 'Passenger Details / Dettagli Passeggero',
            'Ticket success!'                => 'Il successo del biglietto',
            'Air e-ticket numbers:'          => 'Numero E-ticket',
            'Seat No'                        => 'Seat No / Posto n°',
            'Price Details'                  => 'Price Details/ Dettagli sui prezzi',
            'Base Fare'                      => 'Base Fare/ Tariffa base',
            'Taxes and Fees'                 => 'Taxes and Fees/ Tasse e supplementi',
            // 'Taxes and Fees SecondRow' => '',
            'Fare Rules'                     => 'Fare Rules',
        ],
        'es' => [
            'Travel Itinerary/Invoice'       => ['Itinerario de viaje/recibo', 'Itinerario de viaje'],
            'Booking reference:'             => 'Referencia de reserva:',
            'Air China confirmation number:' => 'Número de confirmación de Air China:',
            'Itinerary Details'              => 'Itinerary Details / Datos del vuelo',
            'Operated By:'                   => 'Operado por:',
            'Passenger Details'              => 'Passenger Details / Datos del Pasajero',
            //            'Ticket success!' => '',
            'Air e-ticket numbers:'    => 'Número del e-ticket:',
            'Seat No'                  => 'Seat No / Número de asiento',
            'Price Details'            => 'Price Details/ Detalles del precio',
            'Base Fare'                => 'Base Fare/ Tarifa básica',
            'Taxes and Fees'           => ['Tax, Fees and Charges/ Impuestos y'],
            'Taxes and Fees SecondRow' => ['tarifas'],
            'Fare Rules'               => 'Fare Rules',
        ],
        'de' => [
            'Travel Itinerary/Invoice'       => ['Reiseplan/Beleg'],
            'Booking reference:'             => 'Buchungsreferenz:',
            'Air China confirmation number:' => 'Air China-Bestätigungsnummer:',
            'Itinerary Details'              => 'Itinerary Details / Details zur Reiseroute',
            'Operated By:'                   => 'Flug durchgeführt von:',
            'Passenger Details'              => 'Passenger Details / Fluggastinformationen',
            //            'Ticket success!' => '',
            'Air e-ticket numbers:'    => 'e-Flugticket-Nummern:',
            'Seat No'                  => 'Seat No / Sitz-Nr.',
            'Price Details'            => 'Price Details/ Preisdetails',
            'Base Fare'                => 'Base Fare/ Grundpreis',
            'Taxes and Fees'           => ['Tax, Fees and Charges/ Steuern und'],
            'Taxes and Fees SecondRow' => ['Gebühren'],
            'Fare Rules'               => 'Fare Rules',
        ],
        'ru' => [
            'Travel Itinerary/Invoice'       => ['Travel Itinerary / Маршрут путешествия'],
            'Booking reference:'             => 'Номер бронирования:',
            'Air China confirmation number:' => 'Номер подтверждения Air China:',
            'Itinerary Details'              => 'Itinerary Details / Детали программы визита',
            'Operated By:'                   => 'Кем выполняется:',
            'Passenger Details'              => 'Passenger Details / Данные пассажира',
            //            'Ticket success!' => '',
            'Air e-ticket numbers:'    => 'Номера электронных авиабилетов:',
            'Seat No'                  => 'Seat No / Номер места',
            'Price Details'            => 'Price Details/ Сведения о цене',
            'Base Fare'                => 'Base Fare/ Базовый тариф',
            'Taxes and Fees'           => ['Tax, Fees and Charges/ Налоги'],
            'Taxes and Fees SecondRow' => ['и сборы'],
            'Fare Rules'               => 'Fare Rules',
        ],
        'pt' => [
            'Travel Itinerary/Invoice'       => ['Itinerário de viagem/recibo'],
            'Booking reference:'             => 'Referência da reserva:',
            'Air China confirmation number:' => 'Número de confirmação da Air China:',
            'Itinerary Details'              => 'Itinerary Details / Detalhes do itinerário',
            'Operated By:'                   => 'Operado por:',
            'Passenger Details'              => 'Passenger Details / Detalhes do passageiro',
            //            'Ticket success!' => '',
            'Air e-ticket numbers:'    => 'Números dos bilhetes aéreos eletrônicos:',
            'Seat No'                  => 'Seat No / Lugar n.º',
            'Price Details'            => 'Price Details/ Detalhes do preço',
            'Base Fare'                => 'Base Fare/ Tarifa-base',
            'Taxes and Fees'           => ['Tax, Fees and Charges/ Impostos e taxas'],
            // 'Taxes and Fees SecondRow' => [''],
            'Fare Rules'               => 'Fare Rules',
        ],
        'en' => [
            'Travel Itinerary/Invoice' => ['Travel Itinerary/Invoice', 'Travel Itinerary/Receipt'],
            //            'Booking reference:' => '',
            //            'Air China confirmation number:' => '',
            'Itinerary Details' => 'Itinerary Details',
            //            'Operated By:' => '',
            //            'Passenger Details' => '',
            //            'Ticket success!' => '',
            //            'Air e-ticket numbers:' => '',
            //            'Seat No' => '',
            //            'Price Details' => '',
            //            'Base Fare' => '',
            'Taxes and Fees' => ['Taxes and Fees', 'Tax, Fees and Charges'],
            // 'Taxes and Fees SecondRow' => '',
            //            'Fare Rules' => '',
        ],
    ];

    private $detectFrom = 'no-reply@amadeus.com';
    private $detectProvider = [
        'Air China Ltd',
        'https://www.airchina.co.uk',
        // en
        'Air China confirmation',
        // it
        'conferma Air China:',
        // es
        'confirmación de Air China',
        // de
        'Air China-Bestätigungsnummer',
        // pt
        'Número de confirmação da Air China:',
    ];
    private $detectSubject = [
        // en
        'Confirmation Email from Air China',
        // it
        'E-mail di conferma da Air China',
        // es
        'Correo electrónico de confirmación de Air China',
        // de
        'Bestätigungs-E-Mail von Air China',
        // ru
        'Подтверждение по электронной почте от Air China',
        // pt
        'E-mail de confirmação da Air China',
    ];

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) !== false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->containsText($text, $this->detectProvider) === false) {
            return false;
        }

        // detect Format
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Travel Itinerary/Invoice'])
                && $this->containsText($text, $dict['Travel Itinerary/Invoice']) === true
                && !empty($dict['Itinerary Details'])
                && $this->containsText($text, $dict['Itinerary Details']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $parts = preg_split("/\n *({$this->opt($this->t("Itinerary Details"))}|{$this->opt($this->t("Passenger Details"))}|{$this->opt($this->t("Price Details"))}|{$this->opt($this->t("Fare Rules"))})\n/", $textPdf);
//        $this->logger->debug('$parts = ' . print_r($parts, true));

        $partHeader = $parts[0] ?? '';
        $partItinerary = $parts[1] ?? '';
        $partTraveller = $parts[2] ?? '';
        $partPrice = $parts[3] ?? '';

        $f = $email->add()->flight();

        if (preg_match("/\n *({$this->opt($this->t('Booking reference:'))}) {2,}({$this->opt($this->t('Air China confirmation number:'))})\n+ *([A-Z\d]{5,7}) {3,}([A-Z\d]{5,7})\n/", $partHeader, $m)) {
            $f->general()
                ->confirmation($m[3], trim($m[1], ':'))
                ->confirmation($m[4], trim($m[2], ':'))
            ;
        }

        if (preg_match_all("/(?:^|\n)(.+?) *: *- *.+\n+\s*{$this->opt($this->t('Ticket success!'))}/", $partTraveller, $m)
            || preg_match_all("/(?:^|\n)(.+?) *: *- *.+\n+\s*.*{$this->opt($this->t('Air e-ticket numbers:'))}/", $partTraveller, $m)
        ) {
            $f->general()
                ->travellers($m[1], true);
        } else {
            $f->general()
                ->travellers([]);
        }

        if (preg_match_all("/{$this->opt($this->t('Air e-ticket numbers:'))} *([\d\-]{10,})\s+/", $partTraveller, $m)) {
            $f->issued()
                ->tickets($m[1], false);
        } else {
            $f->issued()
                ->tickets([], false);
        }

        $segments = $this->split("/\n( *.*\b\d{4}\b.*\n+(?:.*\n+)? *\d{1,2}:\d{2} +)/", $partItinerary);
//        $this->logger->debug('$segments = ' . print_r($segments, true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->re("/^\s*(.+)/", $sText));

            $tableText = $this->re("/^\s*.+\n+((?:.*\n+){3,}?)(?:\n\n\n|\s*$)/", $sText);
            $tableText = preg_replace("/\n+(?:.*Duration| {25,}Connection).*\s*[\s\S]+$/", '', $tableText);

            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            // Airline
            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fl>\d{1,5})\n/", $table[1] ?? '', $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fl']);
            }

            if (preg_match("/{$this->opt($this->t('Operated By:'))}([\s\S]+?)\n *\S.+\s*$/", $table[1] ?? '', $m)) {
                $s->airline()
                    ->operator(preg_replace("/\s+/", ' ', trim($m[1])));
            }

            $re = "/^\s*(?:(?<overnight>[-+]\d+) *\w+\n)?\s*(?<time>\d{1,2}:\d{2})\n\s*(?<code>[A-Z]{3})\n\s*(?<name>[\s\S]+?)(?<terminal>\n.*Terminal.*)?\s*$/u";

            // Departure
            if (preg_match($re, $table[0] ?? '', $m)) {
                if (!empty($date) && !empty($m['overnight'])) {
                    $date = strtotime($m['overnight'] . ' days', $date);
                }

                if (!empty($date) && !empty($m['time'])) {
                    $s->departure()
                        ->date(strtotime($m['time'], $date));
                }

                $s->departure()
                    ->code($m['code'])
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->terminal(preg_replace(["/\\/.+/", "/\bTerminal\b/i"], '', trim($m['terminal'] ?? '')), true, true)
                ;
            }
            // Arrival
            if (preg_match($re, $table[2] ?? '', $m)) {
                if (!empty($date) && !empty($m['overnight'])) {
                    $date = strtotime($m['overnight'] . ' days', $date);
                }

                if (!empty($date) && !empty($m['time'])) {
                    $s->arrival()
                        ->date(strtotime($m['time'], $date));
                }

                $s->arrival()
                    ->code($m['code'])
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->terminal(preg_replace(["/\\/.+/", "/\bTerminal\b/i"], '', trim($m['terminal'] ?? '')), true, true)
                ;
            }

            // Extra
            if (preg_match("/\n *([\dhm]{2,})\s*$/", $table[1] ?? '', $m)) {
                $s->extra()
                    ->duration($m[1]);
            }

            if (preg_match("/^\s*(?<aircraft>.+(?:\n.+)?)\n\n(?<cabin>.+(?:\n.*)?)\((?<code>[A-Z]{1,2})\)\n/", $table[3] ?? '', $m)) {
                $s->extra()
                    ->aircraft(preg_replace("/\s+/", ' ', trim($m['aircraft'])))
                    ->cabin(preg_replace("/\s+/", ' ', trim($m['cabin'])))
                    ->bookingCode($m['code'])
                ;
            } elseif (preg_match("/^\s*(?<aircraft>.+)\n(?<cabin>\S.+) *\n? *\((?<code>[A-Z]{1,2})\)\n/", $table[3] ?? '', $m)) {
                $s->extra()
                    ->aircraft(preg_replace("/\s+/", ' ', trim($m['aircraft'])))
                    ->cabin(preg_replace("/\s+/", ' ', trim($m['cabin'])))
                    ->bookingCode($m['code'])
                ;
            } elseif (preg_match("/^\s*(?<aircraft>\S.+(?:\n *.*\d.*)?)\n\n *\((?<code>[A-Z]{1,2})\)\n *(?<cabin>\S.+ Class) *\n\n/", $table[3] ?? '', $m)) {
                //
                //  Boeing 777-
                //   300/300ER
                //
                //  (V)
                //  Economy Class
                //
                //
                //  Economy Flex
                //
                //  Erwachsener 1
                $s->extra()
                    ->aircraft(preg_replace("/\s+/", ' ', trim($m['aircraft'])))
                    ->cabin(preg_replace("/\s+/", ' ', trim($m['cabin'])))
                    ->bookingCode($m['code'])
                ;
            }

            if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())
                && preg_match_all("/\n *{$this->opt($this->t('Seat No'))} {3,}.*(?:\n {20,}.*)?\n *{$s->getAirlineName()}{$s->getFlightNumber()} - (?<seat>\d{1,3}[A-Z])(?: {3,}|\n|$)/", $partTraveller, $m)
            ) {
                $s->extra()->seats($m[1]);
            }
        }

        // Price
        $currency = null;

        if (
            preg_match("/\n.* {5,}(?<amount>\d[\d., ]*) ?(?<currency>[A-Z]{3})\s*$/", $partPrice, $m)
            || preg_match("/\n.{20,} {5,}(?<amount>\d[\d., ]*) ?(?<currency>[A-Z]{3})\s*\n {0,5}\S.{0,25}\s*$/", $partPrice, $m)
        ) {
            $currency = $m['currency'];
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency']);
        } else {
            $f->price()
                ->total(null);
        }

        if (preg_match_all("/\n *{$this->opt($this->t('Base Fare'))} {5,}(?<amount>\d[\d., ]*) ?(?<currency>[A-Z]{3})\n/", $partPrice, $m)
            && (empty($currency) || $currency === $m['currency'][0])
        ) {
            $fare = 0.0;

            foreach ($m['amount'] as $amount) {
                $fare += PriceHelper::parse($amount, $m['currency'][0]);
            }
            $f->price()
                ->cost($fare);

            if (empty($currency)) {
                $currency = $m['currency'][0];
            }
        }

        $rePrice = "/\n *{$this->opt($this->t('Taxes and Fees'))}((?: {5,}.+|)\n+(?:(?: {30,}| {0,5}{$this->opt($this->t('Taxes and Fees SecondRow'))}(?: {10,}\S|)).*\n+)+)/u";

        if (preg_match_all($rePrice, $partPrice, $m)) {
            foreach ($m[1] as $i => $part) {
                if (preg_match("/^( {0,5}{$this->opt($this->t('Taxes and Fees SecondRow'))})( {10,}.+)/", $m[1][$i], $mat)) {
                    $m[1][$i] = preg_replace("/^.+/", str_pad($mat[2], mb_strlen($mat[1]), '0', STR_PAD_LEFT), $m[1][$i]);
                }
            }
            $m[1] = preg_replace("/\n {0,5}{$this->opt($this->t('Taxes and Fees SecondRow'))}\n/", "\n", $m[1]);
            $fees = implode("\n", $m[1]);
            $fees = $this->split("/(?:^|\n)( {0,}[[:alpha:]]+.+\n? {3,}\d.+)/u", $fees);
            sort($fees);

            foreach ($fees as $feeRow) {
                $tf = $this->createTable($feeRow, $this->rowColumnPositions($this->inOneRow($feeRow)));

                if (count($tf) == 2 && preg_match("/^\s*(?<amount>\d[\d., ]*) ?(?<currency>[A-Z]{3})\s*$/", $tf[1], $m)
                    && (empty($currency) || $currency === $m['currency'])
                ) {
                    $f->price()
                        ->fee(preg_replace('/\s+/', ' ', trim($tf[0])), PriceHelper::parse($m['amount'], $m['currency']));
                } else {
                    $f->price()
                        ->fee(null, null);
                }
            }
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function normalizeDate(?string $date)
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            //            // mer 08 mar 2023
            '/^\s*[[:alpha:]]+\s+(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
