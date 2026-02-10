<?php

namespace AwardWallet\Engine\aeroplan\Email;

// use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightInfoPDF extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-259802157.eml, aeroplan/it-274694868.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
            'Booking date' => ['Booking date', 'Travel booked/ticket issued on:'],
            'direction'    => ['Depart', 'Return', 'Flight'],
            'segmentsEnd'  => ['Purchase summary', 'Baggage allowance', 'Canada, U.S.:'],
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, "Air Canada") !== false
                && strpos($text, 'Below are your flight details and other useful information for your trip.') !== false
                && (strpos($text, 'Depart') !== false || strpos($text, 'Return') !== false || strpos($text, 'Flight 1') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]twiltravel\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'airlineLogo'   => '[^\w\s]', //     |    
        ];

        $f = $email->add()->flight();

        if (preg_match("/^[ ]*({$this->opt($this->t('Booking reference'))}).*(?:\n+.{2,})?\n+[ ]*([A-Z\d]{6})(?:[ ]{2}|\n)/m", $text, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $bookingDate = strtotime(str_replace(',', '', $this->re("/{$this->opt($this->t('Booking date'))}[\s:]+(\d{1,2}[,.\s]*[[:alpha:]]+[,.\s]+\d{4})/u", $text)));

        if ($bookingDate) {
            $f->general()->date($bookingDate);
        }

        if (preg_match_all("/^\s+({$patterns['travellerName']})\s\s\s+Seats/mu", $text, $m)) {
            $f->general()->travellers($m[1], true);
        } elseif (preg_match("/\n[ ]*{$this->opt($this->t('Passengers'))}\n+[ ]*({$patterns['travellerName']})\n+[ ]*{$this->opt($this->t('direction'))}/", $text, $m)) {
            $f->general()->traveller($m[1], true);
        }

        if (preg_match_all("/\s*Ticket[#]\:\s*(\d+)/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);
        }

        if (preg_match_all("/\-\s*Aeroplan[#:\s]*([\d\s]*)/", $text, $m)) {
            $f->setAccountNumbers(preg_replace('/\s/', '', $m[1]), false);
        }

        /*$priceText = $this->re("/(GRAND TOTAL.+)/u", $text);

        if (preg_match("/GRAND TOTAL[\s\-]+(?<currency>\D+)\s+\D(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }*/

        $flightText = $this->re("/\n([ ]*{$this->opt($this->t('direction'))}[ \d][\s\S]+?)\n+[ ]*{$this->opt($this->t('segmentsEnd'))}/", $text);

        if (!$flightText) {
            $this->logger->debug('Flight segments not found!');

            return;
        }

        $segments = [];
        $flightParts = $this->splitText($flightText, "/^[ ]*{$this->opt($this->t('direction'))}[ \d]+(.{6,})$/m", true);

        foreach ($flightParts as $fPart) {
            $firstRow = $this->re("/^(.{6,})\n/", $fPart);
            $partSegments = $this->splitText($fPart, "/^(.{8,}\S[ ]{3,}(?:{$patterns['airlineLogo']}[ ]+)?(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+)$/mu", true);

            foreach ($partSegments as $pSegment) {
                $segments[] = $firstRow . "\n\n" . $pSegment;
            }
        }

        foreach ($segments as $key => $sText) {
            $date = $this->re("/^(?<date>[[:alpha:]].+,[ ]*\d{4})(?:[ ]{2}|\n)/u", $sText);

            $s = $f->addSegment();

            $tablePos = [0];
            $tablePos[] = strlen($this->re("/^(.*[ ]{10,})\d+\:\d+/mu", $sText));
            $tablePos[] = strlen($this->re("/^(.+\s\s)\d+\s*(?:h|m)/miu", $sText));

            /*if (preg_match("/^((.+\S [A-Z]{3}[ ]{3,})\S.+? [A-Z]{3}[ ]{3,})(?:{$patterns['airlineLogo']}[ ]+)?(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d+$/mu", $sText, $matches)) {
                $tablePos[] = mb_strlen($matches[2]);
                $tablePos[] = mb_strlen($matches[1]);
            }*/

            $tableText = preg_replace("/^.*\d{4}.*\n/", '', $sText);
            $table = $this->splitCols($tableText, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug("Wrong segment-{$key}!");

                continue;
            }

            if (preg_match("/ (?<depCode>[A-Z]{3})\n+[ ]*(?<depTime>{$patterns['time']})/u", $table[0], $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($date . ', ' . $m["depTime"]));
            }

            if (preg_match("/ (?<arrCode>[A-Z]{3})\n+[ ]*(?<arrTime>{$patterns['time']})\s*(?:[+](?<nextDay>\d)\s*day)?/u", $table[1], $m)) {
                if (isset($m['nextDay']) && !empty($m['nextDay'])) {
                    $s->arrival()
                        ->date(strtotime("{$m['nextDay']}" . ' day', $this->normalizeDate($date . ', ' . $m["arrTime"])));
                } else {
                    $s->arrival()
                        ->date($this->normalizeDate($date . ', ' . $m["arrTime"]));
                }

                $s->arrival()
                    ->code($m['arrCode']);
            }

            if (preg_match("/\W*(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<flightNumber>\d+)\n+[ ]*(?<duration>\d.+)\n+[ ]*(?<cabin>.{2,}?)\s+\((?<bookingCode>[A-Z]{1,2})\)\n+[ ]*{$this->opt($this->t('Operated by'))}/", $table[2], $m)) {
                $s->airline()->name($m['airlineName'])->number($m['flightNumber']);

                $s->extra()
                    ->duration($m['duration'])
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode'])
                ;
            }

            if (preg_match("/\n[ ]*{$this->opt($this->t('Operated by'))}\s*(?<operator>.{2,})\n[ ]*(?<aircraft>.{2,})\n+[ ]*(?:Food|Meal|Breakfast)/", $table[2], $m)) {
                $s->extra()->aircraft($m['aircraft']);
            }

            if ($s->getDepCode() && $s->getArrCode()
                && preg_match_all("/\b{$s->getDepCode()}-{$s->getArrCode()}\s*(\d+[A-Z])\b/", $text, $m)
            ) {
                $s->setSeats($m[1]);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->ParseFlightPDF($email, $text);
        }

        if (preg_match("/Total Invoice Amount\s*([\d\.]+)\s*([A-Z]{3})/u", $text, $m)) {
            $email->price()
                ->total($m[1])
                ->currency($m[2]);
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

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+\s*(\d+)\s*(\w+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Thu 9 Mar, 2023, 16:40
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    /*
    private function normalizeCurrency($s)
    {
        $sym = [
            '€'         => 'EUR',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            '₹'         => 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
    */

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
}
