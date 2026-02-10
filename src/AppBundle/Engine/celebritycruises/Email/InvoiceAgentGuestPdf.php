<?php

namespace AwardWallet\Engine\celebritycruises\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: royalcaribbean/It2, royalcaribbean/AgentGuestBooking, princess/Itinerary, mta/POCruisesPdf

class InvoiceAgentGuestPdf extends \TAccountChecker
{
    public $mailFiles = "celebritycruises/it-27821557.eml, celebritycruises/it-450034353.eml, celebritycruises/it-528608525.eml, celebritycruises/it-61659154.eml";
    private $subjects = [
        'en' => ['Agent & Guest Invoice for Reservation ID'],
    ];
    private $langDetectors = [
        'en' => ['Cruise Itinerary:', 'Booking Itinerary'],
        'es' => ['Itinerario Crucero:'],
    ];
    private $status = '';
    private $lang = '';
    private static $dict = [
        'en' => [
            'firstRow' => ['Confirmation Invoice - Business Partner Copy', 'Confirmation Invoice - Guest Copy'],
        ],

        'es' => [
            'firstRow'                       => ['Confirmación Reserva para el Pasajero'],
            'Reservation ID'                 => 'Clave de reservación',
            'Ship'                           => 'Barco',
            'Itinerary'                      => 'Itinerario',
            'Stateroom'                      => 'Camarote',
            'Sailing Date'                   => 'Inicio de servicios',
            'Departure Date'                 => 'Fecha Salida',
            'Issue Date'                     => 'Fecha',
            'Guest Information'              => 'Información del Pasajero',
            'Guest'                          => 'Pasajero',
            'Cruise Itinerary'               => 'Itinerario Crucero',
            "Captain's Club Numbe"           => "Captain's Club Number",
            'Post Cruise Arrangements'       => 'Servicios Post-Crucero',
            'Charges'                        => 'Importes',
            'Total Charge'                   => 'Total a pagar',
            'Taxes, fees, and port expenses' => 'Impuestos, tasas y gastos portuarios',
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@celebritycruises.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('agent_copy\.pdf');

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('guest_copy\.pdf');
        }

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($textPdf, 'www.celebritycruises.com') === false
                && strpos($textPdf, 'This holiday is provided by Celebrity Cruises') === false
                && strpos($textPdf, 'www.cruisingpower.com') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('agent_copy\.pdf');

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('guest_copy\.pdf');
        }

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName('.*pdf');
        }

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= $textPdf;

                break;
            }
        }

        if (!$textPdfFull) {
            $this->logger->notice("Can't determine a language!");

            return $email;
        }

        $this->parsePdf($email, $textPdfFull);
        $email->setType('InvoiceAgentGuestPdf' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parsePdf(Email $email, $text)
    {
        $patterns = [
            'time'          => '\d{1,2}(?::\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?', // 4:19PM    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後
            'travellerName' => '[[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        if (preg_match("/({$this->opt($this->t('firstRow'))}.+)/s", $text, $matches)) {
            $text = $matches[1];
        }

        $c = $email->add()->cruise();

        // confirmation number
        if (preg_match("/ {2}({$this->opt($this->t('Reservation ID'))})[: ]{2,}(\d{5,})\b/", $text, $m)) {
            $c->general()->confirmation($m[2], $m[1]);
        }

        // Ship
        if (preg_match("/ {2}{$this->opt($this->t('Ship'))}[: ]{2,}(.+?)(?: {2}|$)/m", $text, $m)) {
            $c->details()->ship($m[1]);
        }

        // description
        if (preg_match("/ {2}{$this->opt($this->t('Itinerary'))}[: ]{2,}(.+?)(?: {2}|$)/m", $text, $m)) {
            $c->details()->description($m[1]);
        }

        // room
        if (preg_match("/ {2}{$this->opt($this->t('Stateroom'))}[: ]{2,}(.+?)(?: {2}|$)/m", $text, $m)) {
            if (preg_match("/^\s*([A-Z\d]{1,5}-)(\d+) ([A-Z].+)$/", $m[1], $mat)) {
                $c->details()
                    ->room($mat[2])
                    ->roomClass($mat[1] . $mat[3])
                ;
            } else {
                $c->details()->room($m[1]);
            }
        }

        $dateRelative = 0;

        if (preg_match("/ {2}{$this->opt($this->t('Sailing Date'))}[: ]{2,}(.+?)(?: {2}|$)/m", $text, $m)) {
            $dateRelative = strtotime($m[1]);
        } elseif (preg_match("/ {2}{$this->opt($this->t('Departure Date'))}[: ]{2,}(.+?)(?: {2}|$)/m", $text, $m)) {
            $dateRelative = strtotime($m[1]);
        } elseif (preg_match("/ {2}{$this->opt($this->t('Issue Date'))}[: ]{2,}(.+?)(?: {2}|$)/m", $text, $m)) {
            $dateRelative = strtotime($m[1]);
        }

        // travellers
        $guestInformation = preg_match("/^[ ]*({$this->opt($this->t('Guest Information'))}.+?{$this->opt($this->t('Guest'))}\b.+?)\s*{$this->opt($this->t("Captain's Club Numbe"))}/ms", $text, $m) ? $m[1] : '';

        if ($guestInformation) {
            $travellers = [];
            $tableTraveller = $this->splitCols($guestInformation, $this->colsPos($guestInformation));
            unset($tableTraveller[0]);

            foreach ($tableTraveller as $travellerCell) {
                if (preg_match("/{$this->opt($this->t('Guest'))} #?\d{1,3}\n+({$patterns['travellerName']})$/i", $travellerCell,
                    $m)) {
                    $travellers[] = preg_replace('/\s+/', ' ', $m[1]);
                }
            }
            $c->general()->travellers($travellers);
        }

        $patterns['chargesHeaders'] = "/^ *{$this->opt($this->t('Charges'))}$\s+^.+\b{$this->opt($this->t('Guest'))}\b.+\b{$this->opt($this->t('Guest'))}\b/m";
        $chargesTableType = preg_match($patterns['chargesHeaders'], $text) ? 'G' : 'A';

        // p.tax
        $patterns['tax'] = "^ *{$this->opt($this->t('Charges'))}$.+?^ *{$this->opt($this->t('Taxes, fees, and port expenses'))}";

        if (
            $chargesTableType === 'A'
            && preg_match("/{$patterns['tax']} {2,}(\d[,.\'\d]*)(?: {2}|$)/ms", $text, $m)
        ) {
            $c->price()->tax($this->normalizeAmount($m[1]));
        } elseif (
            $chargesTableType === 'G'
            && preg_match("/{$patterns['tax']} {2}[-,.\'\d ]+ {2}(\d[,.\'\d]*)$/ms", $text, $m)
        ) {
            $c->price()->tax($this->normalizeAmount($m[1]));
        }

        // p.total
        $patterns['total'] = "^ *{$this->opt($this->t('Charges'))}$.+?^ *{$this->opt($this->t('Total Charge'))}";

        if (
            $chargesTableType === 'A'
            && preg_match("/{$patterns['total']} {2,}(\d[,.\'\d]*)(?: {2}|$)/ms", $text, $m)
        ) {
            $c->price()->total($this->normalizeAmount($m[1]));
        } elseif (
            $chargesTableType === 'G'
            && preg_match("/{$patterns['total']} {2}[-,.\'\d ]+ {2}(\d[,.\'\d]*)$/ms", $text, $m)
        ) {
            $c->price()->total($this->normalizeAmount($m[1]));
        }

        // p.currencyCode
        if (preg_match("/^ *{$this->opt($this->t('Currency'))} {2,}{$this->opt($this->t('Guest'))}.*\n+^ *([A-Z]{3})(?: {2}|$)/m", $text, $m)) {
            $c->price()->currency($m[1]);
        }

        // segments
        $cruiseItinerary = preg_match("/^[ ]*{$this->opt($this->t('Cruise Itinerary'))}[^\n]*\s*(.+?)\s*{$this->opt($this->t('Post Cruise Arrangements'))}/ms", $text, $m) ? $m[1] : '';

        if (empty($cruiseItinerary)) {
            $cruiseItinerary = preg_match("/^[ ]*{$this->opt($this->t('Pre Cruise Arrangements'))}[^\n]*\s*(.+?)\s*{$this->opt($this->t('Post Cruise Arrangements'))}/ms", $text, $m) ? $m[1] : '';
        }

        if (stripos($cruiseItinerary, 'Date') !== false) {
            $cruiseItinerary = preg_match("/^\s*(Date\s+Port\s+Location\s+Arrive\s+Depart.+)/msu", $cruiseItinerary, $m) ? $m[1] : '';
        }

        if (!$cruiseItinerary) {
            $this->logger->alert('Cruise segments not found!');

            return false;
        }

        $cruiseItinerary = preg_replace("/\n {15,}\S.+ {15,}\d{1,2} of \d{1,2}\n.+ 20\d{2}.*\n+(.*\n+){4,12}.* +{$this->opt($this->t('Stateroom'))}(.+\n+){1,4}?\n{3,}/u", "\n---Part---\n", $cruiseItinerary);
        $cruiseItineraryParts = explode("\n---Part---\n", $cruiseItinerary);

        $cruiseSegmentsArray = [];

        foreach ($cruiseItineraryParts as $i => $part) {
            if (preg_match('/(?:^|\n)( *Date\s+Port\s+Location\s+Arrive\s+Depart\s+)/', $part, $m)) {
                $array = $this->splitCols($part, [0, mb_strlen($m[1])]);
                $cruiseSegmentsArray[(int) ('00' . str_pad($i, 3, '0', STR_PAD_LEFT))] = $array[0];
                $cruiseSegmentsArray[(int) ('01' . str_pad($i, 3, '0', STR_PAD_LEFT))] = $array[1];
            } elseif (preg_match("/^(?:\s*\n)?( *\S+.+? {2,})\d{1,2} {0,10}[[:alpha:]]{3} {2,}\S+/u", $part, $m)) {
                $colsAll = $this->colsPos($this->inOneRow($part));
                $headers = [];

                foreach ($colsAll as $ca) {
                    if (($ca > (mb_strlen($m[1]) - 5)) && ($ca < (mb_strlen($m[1]) + 5))) {
                        $headers = [0, $ca];
                    }
                }

                if (!empty($headers)) {
                    $array = $this->splitCols($part, $headers);
                    $cruiseSegmentsArray[(int) ('00' . str_pad($i, 3, '0', STR_PAD_LEFT))] = $array[0];
                    $cruiseSegmentsArray[(int) ('01' . str_pad($i, 3, '0', STR_PAD_LEFT))] = $array[1];
                } else {
                    $cruiseSegmentsArray[] = $part;
                }
            } else {
                $cruiseSegmentsArray[] = $part;
            }
        }

        ksort($cruiseSegmentsArray);
        $cruiseSegmentText = implode("\n", $cruiseSegmentsArray);

        $segments = array_filter(explode("\n", $cruiseSegmentText), function ($v, $k) {
            $v = trim($v);

            if (empty($v)
                || preg_match("/ {2}{$this->opt($this->t('AT SEA'))}$/i", $v)
                || preg_match('/^\s*Date\s+/i', $v)) {
                return false;
            }

            return true;
        }, ARRAY_FILTER_USE_BOTH);
        $segments = array_values($segments);

        // 24 DEC    SAN JUAN, PUERTO RICO    3:30 PM    11:00 PM
        $patterns['segment'] = "/^ *(?<date>\d{1,2} +[[:alpha:]]{3}) {2,}(?<port>.+?) {2,}(?<time1>{$patterns['time']})(?:\s+(?<time2>{$patterns['time']}))?$/u";

        foreach ($segments as $key => $segment) {
            $s = $c->addSegment();

            if (preg_match($patterns['segment'], $segment, $m)) {
                $s->setName($m['port']);

                $date = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);

                if (empty($date)) {
                    continue;
                }

                if (empty($m['time2'])) {
                    if ($key === 0) {
                        $s->setAboard(strtotime($m['time1'], $date));
                        $this->status = 'Abord';
                    } elseif ($this->status == 'Abord') {
                        $s->setAshore(strtotime($m['time1'], $date));
                        $this->status = 'Ashore';
                    } elseif ($this->status == 'Ashore') {
                        $s->setAboard(strtotime($m['time1'], $date));
                        $this->status = 'Abord';
                    }
                } else {
                    $s->setAshore(strtotime($m['time1'], $date));
                    $s->setAboard(strtotime($m['time2'], $date));
                }
            } elseif (preg_match("/ \d{1,2}:\d{2}\b/", $segment)) {
                $this->logger->debug('check segment = ' . print_r($segment, true));
            } else {
                $c->removeSegment($s);
            }
        }

        return true;
    }

    /**
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);             // 11 507.00  ->  11507.00
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s); // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $s = preg_replace('/,(\d{2})$/', '.$1', $s);    // 18800,00   ->  18800.00

        return $s;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function rowColsPos($row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols($text, $pos = false): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function colsPos($table, $correct = 5): array
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            for ($j = $i - 1; $j >= 0; $j = $j - 1) {
                if (isset($pos[$j])) {
                    if (isset($pos[$i])) {
                        if ($pos[$i] - $pos[$j] < $correct) {
                            unset($pos[$i]);
                        }
                    }

                    break;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
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
}
