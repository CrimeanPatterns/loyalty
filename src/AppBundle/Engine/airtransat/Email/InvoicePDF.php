<?php

namespace AwardWallet\Engine\airtransat\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicePDF extends \TAccountChecker
{
    public $mailFiles = "airtransat/it-307490293.eml, airtransat/it-317884891.eml, airtransat/it-695900844.eml";

    public $reFrom = "noreply@transat.com";

    public $pdfNamePattern = ".*pdf";

    public $travellers;
    public $lang = '';
    public static $dictionary = [
        'en' => [
            //            'Booking #' => '',
            //            'Status' => '',
            //            'Booking Date' => '',
            //            'Passenger(s) Information' => '',
            'Flight Itinerary (local time)' => 'Flight Itinerary (local time)',
            //            'Flight' => '',
            //            'Departure' => '',
            //            'Class' => '',
            //            'Confirmation #' => '',
            //            'Operated by' => '',
            'FlightItineraryEnd' => ['Package', 'Other Product'],

            // HOTEL
            // 'Transfers Description' => '',
            // 'Hotel(s) Description' => '',
            // 'Destination' => '',
            // 'Check-In' => '',
            // 'Check-Out' => '',
            // 'Dur.' => '',
            // 'Occupancy' => '',
            // 'Passenger(s)' => '',

            'Invoice' => 'Invoice',
            //            'Total for services' => '',
            'feeNames' => ['Taxes,Fees&Surcharges', 'G.S.T/H.S.T'],
            //            'Total Invoice' => '',
            //            'Total Invoice Amount' => '',
            'discountNames' => ['Commission', 'G.S.T./H.S.T. on Commission'],
        ],
        'fr' => [
            'Booking #'                     => '# Réservation',
            'Status'                        => 'Statut',
            'Booking Date'                  => 'Date de réservation',
            'Passenger(s) Information'      => 'Renseignements sur les passagers',
            'Flight Itinerary (local time)' => 'Itinéraire de vol (heure locale)',
            'Flight'                        => 'Vol',
            'Departure'                     => 'Départ',
            'Class'                         => 'Classe',
            'Confirmation #'                => '# Confirmation',
            'Operated by'                   => 'Opéré par', // to check
            'FlightItineraryEnd'            => ['Emballer', 'Autre produit', 'Forfait'], // all to check

            // HOTEL
            // 'Transfers Description' => '',
            'Hotel(s) Description'          => 'Hôtel(s) Description', // to check
            // 'Destination' => '',
            // 'Check-In' => '',
            // 'Check-Out' => '',
            // 'Dur.' => '',
            // 'Occupancy' => '',
            // 'Passenger(s)' => '',

            'Invoice'                       => 'Facture',
            'Total for services'            => 'Total pour services',
            'feeNames'                      => ['Taxes,frais&surcharges', 'FICAV contribution : $ 0.83', 'T.P.S./T.V.H.', 'T.V.Q.'],
            'Total Invoice'                 => 'Total de la facture',
            'Total Invoice Amount'          => 'Montant total de la facture',
            'discountNames'                 => ['Commission'],
        ],
    ];

    private $patterns = [
        'date' => '\b\d{1,2}-[[:alpha:]]{3,20}-\d{4}\b', // 05-MAR-2024
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (!$this->detectPdf($text)) {
                    $this->logger->debug('can\'t determine a language');

                    continue;
                }
                $this->parseEmailPdf($text, $email);
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectPdf($body): bool
    {
        if (strpos($body, 'TRANSAT TOURS') === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Flight Itinerary (local time)']) && !empty($dict['Invoice'])
                && strpos($body, $dict['Flight Itinerary (local time)']) !== false
                && strpos($body, $dict['Invoice']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf($textPDF, Email $email): void
    {
        $textPDF = preg_replace("/\n.* {10,}Page \d+\n/", "\n", $textPDF);

        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/\n *{$this->opt($this->t('Booking #'))} +(\d{5,})(?: {5,}|\n)/u", $textPDF));

        $travellersText = $this->re("/\n *{$this->opt($this->t('Passenger(s) Information'))}\n([\s\S]+?)\n *{$this->opt($this->t('Flight Itinerary (local time)'))}/u", $textPDF);

        if (preg_match_all("/^ *(\d+) {3,}[A-Z]{2,6} {3,} ([A-Z].+?)( {5,}|\n)/mu", $travellersText, $m)) {
            $this->travellers = array_combine($m[1], $m[2]);
        }

        $flightText = $this->re("/\n *{$this->opt($this->t('Flight Itinerary (local time)'))}(\n[\s\S]+?)\n *(?:{$this->opt($this->t('FlightItineraryEnd'))}|{$this->opt($this->t('Invoice'))})/u", $textPDF);

        $this->parseFlight($flightText, $email);

        $hotelRe = "/\n([ ]*{$this->opt($this->t('Hotel(s) Description'))} .+\n[\s\S]+?)\n+(?: *{$this->opt($this->t('Hotel(s) Description'))}|[ ]*{$this->opt($this->t('Transfers Description'))}|[ ]*{$this->opt($this->t('FlightItineraryEnd'))})/u";

        if (preg_match_all($hotelRe, $textPDF, $hotelMatches)) {
            foreach ($hotelMatches[1] as $v) {
                $this->parseHotel($v, $email);
            }
        }

        $status = $this->re("/\n *{$this->opt($this->t('Status'))} +(\S.+?)(?: {5,}|\n)/u", $textPDF);
        $bookingDateVal = $this->re("/\n *{$this->opt($this->t('Booking Date'))} +(\S.+?)(?: {5,}|\n)/u", $textPDF);

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->status($status)
                ->date2($this->normalizeDate($bookingDateVal))
            ;

            if (empty($it->getTravellers())) {
                $it->general()
                    ->travellers($this->travellers, true)
                ;
            }
        }

        // Price

        $currency = $this->re("/ {3,}{$this->opt($this->t('Total Invoice Amount'))}[: ]+.*?[\d ]([A-Z]{3})[\d \n]/", $textPDF);
        $email->price()->currency($currency);

        $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;

        $cost = $this->re("/\n {0,10}{$this->opt($this->t('Total for services'))}[: ]+(\d[\d., ]*?)(?: {3,}|\n)/", $textPDF);
        $email->price()->cost(PriceHelper::parse($cost, $currencyCode));

        if (preg_match_all("/^ {0,10}(?<name>{$this->opt($this->t('feeNames'))})[: ]+(?<charge>\d[\d., ]*?)(?: {3,}|$)/m", $textPDF, $feeMatches, PREG_SET_ORDER)) {
            foreach ($feeMatches as $m) {
                $email->price()->fee($m['name'], PriceHelper::parse($m['charge'], $currencyCode));
            }
        }

        $discountAmounts = [];

        if (preg_match_all("/^ {1,30}{$this->opt($this->t('discountNames'))}[: ]+[-–]+[ ]*(\d[\d., ]*?)(?: {3,}|$)/m", $textPDF, $discountMatches)) {
            foreach ($discountMatches[1] as $discountVal) {
                $discountAmounts[] = PriceHelper::parse($discountVal, $currencyCode);
            }
        }

        if (count($discountAmounts) > 0) {
            $email->price()->discount(array_sum($discountAmounts));
        }

        $total = $this->re("/\n {0,10}{$this->opt($this->t('Total Invoice'))}[: ]+(\d[\d., ]*?)(?: {3,}|\n)/", $textPDF);
        $email->price()->total(PriceHelper::parse($total, $currencyCode));

//        $this->logger->debug('$textPDF = '.print_r( $textPDF,true));
    }

    private function parseFlight($text, Email $email): void
    {
        $segments = $this->splitter("/\n *((?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?\d{1,5} +.*[ ]{3,}\d[\d\-, ]*\n)/", $text);
        // $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $stext) {
            $tableText = preg_replace("/{$this->opt($this->t('Operated by'))}[\s\S]+/", '', $stext);
            $tableText = preg_replace("/^.*{$this->opt($this->t('Flight'))}.* {2,}{$this->opt($this->t('Departure'))} {2,}.* {2,}{$this->opt($this->t('Class'))} {2,}.*/m", '', $tableText);
            $tableSeg = $this->splitCols($tableText, $this->colsPos($this->inOneRow($tableText)));

            if (count($tableSeg) < 4) {
                $email->add()->flight();
                $this->logger->debug("table error");
                $this->logger->debug('$tableSeg = ' . print_r($tableSeg, true));

                return;
            }

            $conf = null;

            if (preg_match("/\n\s*{$this->opt($this->t('Confirmation #'))}\s*\n([A-Z\d]{5,7})(\n|$)/", $tableSeg[0], $m)) {
                $conf = $m[1];
            }

            unset($f);

            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'flight') {
                    if (empty($conf) && $it->getNoConfirmationNumber() === true) {
                        $f = $it;

                        break;
                    }

                    if (!empty($conf) && in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                        $f = $it;

                        break;
                    }
                }
            }

            if (!isset($f)) {
                if (empty($conf)) {
                    $f = $email->add()->flight();
                    $f->general()
                        ->noConfirmation();
                } else {
                    $f = $email->add()->flight();
                    $f->general()
                        ->confirmation($conf);
                }
            }

            $this->addTravellers($f, $tableSeg[count($tableSeg) - 1]);

            $s = $f->addSegment();

            // Airline
            if (preg_match("/^\s*(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d]) ?(?<fn>\d{1,5})\n/", $tableSeg[0], $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);
            }

            if (preg_match("/\n\s*{$this->opt($this->t('Operated by'))} +(\S.+?)( {3,}|\s*\n|\s*$)/", $stext, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            $re = "/^(?<name>.+?)\((?<code>[A-Z]{3})\)\s*\n(?<date>.+)\n\s*[^\d\n]* (?<time>\d{1,2}:\d{2}(?: [apAP][mM])?)\s*$/su";
            // Departure
            if (preg_match($re, $tableSeg[1], $m)) {
                $dateDep = strtotime($this->normalizeDate($m['date']));
                $s->departure()
                    ->code($m['code'])
                    ->name($this->nice($m['name']))
                    ->date(strtotime($m['time'], $dateDep));
            }
            // Arrival
            if (preg_match($re, $tableSeg[2], $m)) {
                $dateArr = strtotime($this->normalizeDate($m['date']));
                $s->arrival()
                    ->code($m['code'])
                    ->name($this->nice($m['name']))
                    ->date(strtotime($m['time'], $dateArr));
            }

            $s->extra()
                ->cabin($this->nice($tableSeg[count($tableSeg) - 2]));
        }
    }

    private function parseHotel($text, Email $email): void
    {
        $h = $email->add()->hotel();

        $h->general()
            ->noConfirmation();

        $tablePos = [0];

        if (preg_match("/^((((((.+ ){$this->opt($this->t('Destination'))}[ ]+){$this->opt($this->t('Check-In'))}[ ]+){$this->opt($this->t('Check-Out'))}[ ]+){$this->opt($this->t('Dur.'))}[ ]+){$this->opt($this->t('Occupancy'))}[ ]+){$this->opt($this->t('Passenger(s)'))}\n/", $text, $matches)) {
            $tablePos[1] = mb_strlen($matches[6]);
            $tablePos[2] = mb_strlen($matches[5]);
            $tablePos[3] = mb_strlen($matches[4]);
            $tablePos[4] = mb_strlen($matches[3]);
            $tablePos[5] = mb_strlen($matches[2]);
            $tablePos[6] = mb_strlen($matches[1]);
        }

        if (preg_match("/^(((.{30,}? ){$this->patterns['date']}[ ]+){$this->patterns['date']}[ ]*)/mu", $text, $matches)) {
            $tablePos[2] = mb_strlen($matches[3]);
            $tablePos[3] = mb_strlen($matches[2]);
            $tablePos[4] = mb_strlen($matches[1]);
        }

        $tableSeg = $this->splitCols($text, $tablePos);

        if (count($tableSeg) !== 6 && count($tableSeg) !== 7) {
            $this->logger->debug("table error");
            $this->logger->debug('$tableSeg = ' . print_r($tableSeg, true));

            return;
        }

        // remove table headers
        $tableSeg = array_map(function ($item) { return preg_replace('/^.*\S\n+/', '', $item); }, $tableSeg);

        if (count($tableSeg) === 6 && preg_match("/^\s*.* (\d{1,2})(?:\n|$)/", $tableSeg[3], $m)) {
            $tableSeg[3] = preg_replace("/^\s*(.* )\d{1,2}(?:\n|$)/", "$1\n", $tableSeg[3]);
            array_splice($tableSeg, 4, 0, $m[1]);
        }

        $this->addTravellers($h, $tableSeg[count($tableSeg) - 1]);
        $col0Rows = array_values(array_filter(explode("\n", $tableSeg[0])));

        if (count($col0Rows) === 2) {
            $hotelNameParts = preg_split('/[ ]{2,}/', $col0Rows[0]);

            if (count($hotelNameParts) === 2) {
                $col0Rows[0] = $hotelNameParts[0];
            }

            $h->hotel()
                ->name($col0Rows[0]);

            $h->addRoom()
                ->setType($col0Rows[1]);
        }

        $h->hotel()
            ->address($this->nice($tableSeg[1]));

        $h->booked()
            ->checkIn2($this->normalizeDate($tableSeg[2]))
            ->checkOut2($this->normalizeDate($tableSeg[3]))
        ;

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'hotel' && $it->getId() !== $h->getId()
                && serialize(array_diff_key($it->toArray(), ['rooms' => [], 'travellers' => []])) === serialize(array_diff_key($h->toArray(), ['rooms' => [], 'travellers' => []]))
            ) {
                $it->addRoom()->setType($h->getRooms()[0]->getType());

                foreach ($h->getTravellers() as $traveller) {
                    if (!in_array($traveller[0], array_column($it->getTravellers(), 0))) {
                        $it->general()
                            ->traveller($traveller[0], true);
                    }
                }
                $email->removeItinerary($h);
            }
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^\s*(?:[-[:alpha:]]{3,}\s+)?(\d{1,2})-([[:alpha:]]{3,})-(\d{4})\s*$/u', $text, $m)) {
            // 24-APR-2023    |    LUN 24-APR-2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text): array
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
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
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
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

    private function colsPos($table, $correct = 5)
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
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function nice($str): string
    {
        return trim(preg_replace("/\s+/", ' ', $str));
    }

    private function addTravellers($it, $s)
    {
        $pax = $this->travellerNumbers($s);

        if (!empty($pax)) {
            $travellers = array_intersect_key($this->travellers, array_flip($pax));

            foreach ($travellers as $traveller) {
                if (!in_array($traveller, array_column($it->getTravellers(), 0))) {
                    $it->general()
                        ->traveller($traveller, true);
                }
            }
        }
    }

    private function travellerNumbers($s)
    {
        $result = [];
        $array = preg_split("/\s*,\s*/", trim($s));

        foreach ($array as $value) {
            if (preg_match("/^\s*(\d+)\s*-\s*(\d+)\s*$/", $value, $m) && (int) $m[2] >= (int) $m[1]) {
                for ($i = $m[1]; $i <= $m[2]; $i++) {
                    $result[] = $i;
                }
            } elseif (preg_match("/^\s*(\d+)\s*$/", $value, $m)) {
                $result[] = $m[1];
            } else {
                return null;
            }
        }

        return $result;
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
}
