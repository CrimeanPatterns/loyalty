<?php

namespace AwardWallet\Engine\akasa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FlightBookingPdf extends \TAccountChecker
{
    public $mailFiles = "akasa/it-321529752.eml, akasa/it-690979699.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'bookingStart'    => ['BOOKING DETAILS'],
            'bookingEnd'      => ['FLIGHT DETAILS'],
            'flightStart'     => ['FLIGHT DETAILS'],
            'flightEnd'       => ['PASSENGER DETAILS'],
            'badRows'         => ['Check-in'],
            'passengersStart' => ['PASSENGER DETAILS'],
            'passengersEnd'   => ['FARE SUMMARY', 'PAYMENT SUMMARY'],
            'fareStart'       => ['FARE SUMMARY'],
            'statusVariants'  => ['Confirmed'],
        ],
    ];

    private $pdfPattern = '.*pdf';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@travel-akasaair.in') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Akasa Air Itinerary') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($textPdf, '@akasaair.com') === false) {
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
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->assignLang($textPdf)) {
                $this->parsePdf($email, $textPdf);
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FlightBookingPdf' . ucfirst($this->lang));

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

    private function parsePdf(Email $email, string $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $bookingText = $this->re("/\n[ ]*{$this->opt($this->t('bookingStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('bookingEnd'))}/", $text);
        $flightText = $this->re("/\n[ ]*{$this->opt($this->t('flightStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('flightEnd'))}/", $text);
        $flightText = preg_replace("/^[ ]*{$this->opt($this->t('badRows'))}.+/m", '', $flightText);
        $passengersText = $this->re("/\n[ ]*{$this->opt($this->t('passengersStart'))}\n+([\s\S]+?)\n+[ ]*{$this->opt($this->t('passengersEnd'))}/", $text);
        $paymentText = $this->re("/\n[ ]*{$this->opt($this->t('fareStart'))}(?: .+)?\n+([\s\S]+?)(?:\n{3}|$)/", $text);

        $bookingRows = preg_split("/\n+/", $bookingText);

        if (count($bookingRows) === 2
            && preg_match("/^[ ]{0,40}({$this->opt($this->t('Booking Reference/PNR'))})[ ]{2,}{$this->opt($this->t('Booking Status'))}[ ]{2}/", $bookingRows[0], $m1)
            && preg_match("/^[ ]{0,40}(?<pnr>[A-Z\d]{5,})(?:[ ]{2,}|$)(?:(?<status>{$this->opt($this->t('statusVariants'))})(?:[ ]{2}|$))?/", $bookingRows[1], $m2)
        ) {
            $f->general()->confirmation($m2['pnr'], $m1[1]);

            if (!empty($m2['status'])) {
                $f->general()->status($m2['status']);
            }
        }

        $codesByFlight = $travellers = $seatsByFlight = [];
        $pdParts = $this->splitText($passengersText, "/^([ ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+(?:[ ]*\(|$))/m", true);

        foreach ($pdParts as $pdPart) {
            /*
                QP 1396 (BOM-GOX), 17 Mar   Flexi
                Vishwajit Dahanukar
                Seat 2A, Mixed Nuts
            */

            if (preg_match("/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:[ ]*\(|$)/", $pdPart, $m)) {
                $id = $m['name'] . $m['number'];
            } else {
                $codesByFlight = $travellers = $seatsByFlight = [];
                $this->logger->debug('Flight ID not found!');

                break;
            }

            if (preg_match("/^.+\( ?([A-Z]{3}) ?- ?([A-Z]{3}) ?\)/", $pdPart, $m)) {
                if (array_key_exists($id, $codesByFlight)) {
                    $codesByFlight = $travellers = $seatsByFlight = [];
                    $this->logger->debug('Found duplicate airport codes!');

                    break;
                } else {
                    $codesByFlight[$id] = ['dep' => $m[1], 'arr' => $m[2]];
                }
            }

            // TODO: need examples with multi-passengers!

            $table = $this->splitCols(preg_replace("/^.+\s*\n/", '', $pdPart));

            if ((count($table) === 1 or count($table) === 2)
                && preg_match_all("/^ *(?<traveller>{$patterns['travellerName']})$\s*[ ]*{$this->opt($this->t('Seat'))}[: ]+(?<seat>\d+[A-Z])(?:[ ]*,.*)?$/m", "\n" . implode("\n\n", $table), $m)) {
                foreach ($m[0] as $i => $v) {
                    if (strpos($m['traveller'][$i], '  ') === false) {
                        $travellers[] = $m['traveller'][$i];
                    } else {
                        $codesByFlight = $travellers = $seatsByFlight = [];
                        $this->logger->debug('Wrong passenger name!');

                        break;
                    }

                    if ($i === 0 && isset($seatsByFlight[$id])) {
                        $codesByFlight = $travellers = $seatsByFlight = [];
                        $this->logger->debug('Found duplicate seat!');

                        break;
                    } else {
                        $seatsByFlight[$id][] = [$m['seat'][$i], $m['traveller'][$i]];
                    }
                }
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $segments = $this->splitText($flightText, "/\n(.+ (?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+ .+ {$patterns['time']})/", true);

        foreach ($segments as $sText) {
            $s = $f->addSegment();

            /*
                17 Mar 2023    QP 1396    Mumbai (T1)    North Goa    0    17:35    18:50    1pc - 15 kgs
            */

            $tablePos = [0];

            if (preg_match("/^(((((((.{6,}[ ]{2})(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+[ ]+)\S.+\S[ ]{2,})\S.+\S[ ]{2,})\d{1,3}[ ]{2,}){$patterns['time']}[ ]+){$patterns['time']})(?: |$)/", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[7]);
                $tablePos[] = mb_strlen($matches[6]);
                $tablePos[] = mb_strlen($matches[5]);
                $tablePos[] = mb_strlen($matches[4]);
                $tablePos[] = mb_strlen($matches[3]);
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($sText, $tablePos);

            if (count($table) !== 8) {
                $this->logger->debug('Wrong flight segment!');

                continue;
            }

            $date = strtotime($table[0]);
            $s->departure()->date(strtotime($table[5], $date));
            $s->arrival()->date(strtotime($table[6], $date));

            if (preg_match("/^[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:[ ]+\(|\n|$)/", $table[1], $m)) {
                $s->airline()->name($m['name'])->number($m['number']);

                $id = $m['name'] . $m['number'];

                if (array_key_exists($id, $codesByFlight)) {
                    $s->departure()->code($codesByFlight[$id]['dep']);
                    $s->arrival()->code($codesByFlight[$id]['arr']);
                } else {
                    $s->departure()->noCode();
                    $s->arrival()->noCode();
                }

                if (!empty($seatsByFlight[$id])) {
                    foreach ($seatsByFlight[$id] as $v) {
                        $s->extra()->seat($v[0], false, false, $v[1]);
                    }
                }
            }

            $airportDep = preg_replace('/\s+/', ' ', trim($table[2]));
            $airportArr = preg_replace('/\s+/', ' ', trim($table[3]));

            if (preg_match($pattern = "/^(?<name>.{2,}?)\s*\(\s*(?<terminal>T[^)(]+)\s*\)$/", $airportDep, $m)) {
                $airportDep = $m['name'];
                $terminalDep = $m['terminal'];
            } else {
                $terminalDep = null;
            }

            $s->departure()->name($airportDep)->terminal($terminalDep, false, true);

            if (preg_match($pattern, $airportArr, $m)) {
                $airportArr = $m['name'];
                $terminalArr = $m['terminal'];
            } else {
                $terminalArr = null;
            }

            $s->arrival()->name($airportArr)->terminal($terminalArr, false, true);

            if (preg_match("/^\s*(\d{1,3})\s*$/", $table[4], $m)) {
                $s->extra()->stops($m[1]);
            }
        }

        $totalPrice = $this->re("/^[ ]*{$this->opt($this->t('Total'))}[ ]{4,80}(.*?\d.*?)(?:[ ]{2}|$)/m", $paymentText);

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)) {
            // ₹20,024
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function assignLang(?string $text): bool
    {
        if (empty($text) || !isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['flightStart']) || empty($phrases['flightEnd'])) {
                continue;
            }

            if ($this->strposArray($text, $phrases['flightStart']) !== false
                && $this->strposArray($text, $phrases['flightEnd']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
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
}
