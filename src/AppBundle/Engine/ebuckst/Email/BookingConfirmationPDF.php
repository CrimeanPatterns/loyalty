<?php

namespace AwardWallet\Engine\ebuckst\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "ebuckst/it-232496567.eml, ebuckst/it-236500629.eml, ebuckst/it-236907178.eml, ebuckst/it-238594767.eml, ebuckst/it-240351687.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            'eBucks Ref:' => ['eBucks Ref:', 'eBucks reference:'],
        ],
    ];

    private $detectSubject = [
        // en
        'Booking Confirmation For Booking Ref:',
    ];

    private $detectCompany = [
        'The eBucks Travel Team',
    ];
    private $detectBody = [
        'en' => [
            'flight' => ['Flight summary:'],
            'bus'    => ['Trip summary:'],
            'rental' => ['Car rental summary :'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'no-reply@ebucks.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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

            if ($this->detectPdf($text)) {
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
            $type = $this->detectPdf($text);

            if (empty($type)) {
                continue;
            }

            switch ($type) {
                case 'flight':
                    $this->parseFlight($email, $text);

                    break;

                case 'bus':
                    $this->parseBus($email, $text);

                    break;

                case 'rental':
                    $this->parseREntal($email, $text);

                    break;
            }
//            $this->logger->debug('Pdf text = ' . print_r($text, true));
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
        // TODO check count types
        return count(self::$dictionary);
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, $this->detectCompany) !== true) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $type => $dBody) {
                if ($this->containsText($text, $dBody) === true) {
                    return $type;
                }
            }
        }

        return false;
    }

    private function parseFlight(Email $email, ?string $textPdf = null)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/\b{$this->opt($this->t("eBucks Ref:"))} *(\d+[\-]?\d{5,})\n/", $textPdf));

        // Flight
        $f = $email->add()->flight();

        // General
        $f->general()
            ->noConfirmation();

        $travellersText = $this->re("/\n *{$this->opt($this->t("Traveller details:"))}[^\n]+\n(.+?)\n *{$this->opt($this->t("Price breakdown:"))}/s", $textPdf);

        if (preg_match_all("/^ {0,10}([[:alpha:]](?: ?[^\d\s])+)(?: {3,}|$)/m", $travellersText, $m)) {
            $m[1] = preg_replace("/^(?:Mrs|Mr|Miss|Mstr|Ms|Dr)\s+/", '', $m[1]);
            $f->general()
                ->travellers($m[1]);
        }
        // Issued
        if (preg_match_all("/(?: {3,}|\n)[A-Z\d]{2} *- *(\d{10,}?)(?: {3,}|\n|$)/", $travellersText, $m)) {
            $f->issued()
                ->tickets($m[1], false);
        }

        // Price
        $priceText = $this->re("/\n *{$this->opt($this->t("Price breakdown:"))}(.+)/s", $textPdf);

        $spent = $this->re("/\n *{$this->opt($this->t("eBucks paid:"))} +eB *(.+)\n/", $priceText);

        if (!empty((int) $spent)) {
            $f->price()
                ->spentAwards('eB ' . $spent);
        }

        $currency = null;
        $total = $this->re("/\n *{$this->opt($this->t("ZAR Paid:"))} +(.+?)(?: {3}|\n|$)/", $priceText);

        if (preg_match("/^\s*R\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)) {
            $currency = 'ZAR';
            $f->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        } else {
            $f->price()
                ->total(null);
        }

        $fareText = $this->re("/^\s*.+\bp ?\\/ ?p\b.*(\n[\s\S]+?)\n {0,20}[[:alpha:]](?: ?[[:alpha:]])+: +/", $priceText);

        if (preg_match_all("/^ +[[:alpha:]]+ {3,}(\d+) {3,}R +(\d[\d .,]+?) {3,}R +(\d[\d .,]+?) *$/m", $fareText, $m)
            && count($f->getTravellers()) == array_sum($m[1])
        ) {
            $currency = 'ZAR';
            $fare = 0.0;

            foreach ($m[2] as $i => $ft) {
                $fare += $m[1][$i] * PriceHelper::parse($ft, $currency);
            }
            $f->price()
                ->cost($fare);

            $taxes = 0.0;

            foreach ($m[3] as $i => $ft) {
                $taxes += $m[1][$i] * PriceHelper::parse($ft, $currency);
            }
            $f->price()
                ->tax($taxes);

            $fee = $this->re("/\n *{$this->opt($this->t("Booking Fee:"))} +R +(.+?)(?: {3}|\n|$)/", $priceText);

            if (!empty($fee)) {
                $f->price()
                    ->fee('Booking Fee', $fee);
            }
        }

        // Segments
        $segments = $this->split("/\n *({$this->opt($this->t("Airline reference:"))})/", $textPdf);
//        $this->logger->debug('$segments = '.print_r( $segments,true));
        foreach ($segments as $sText) {
            $s = $f->addSegment();

            // Airline
            if (preg_match("/{$this->opt($this->t('Airline reference:'))} *(?<conf>[A-Z\d]{5,})\n\s*.* (?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\n/", $sText, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                    ->confirmation($m['conf'])
                ;
            }

            $tableText = $this->re("/{$this->opt($this->t('TAKE-OFF'))}.+\n([\s\S]+?\|.+[\s\S]+?)\n(?:\n|.*baggage)/", $sText);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            if (preg_match("/^\s*(?<name>[\s\S]+?)\n\s*(?<code>[A-Z]{3})\s*\n(?<date>[\s\S]+)/u", $table[0] ?? '', $m)) {
                $s->departure()
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            if (preg_match("/^\s*(?<name>[\s\S]+?)\n\s*(?<code>[A-Z]{3})\s*\n(?<date>[\s\S]+)/u", $table[2] ?? '', $m)) {
                $s->arrival()
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->code($m['code'])
                    ->date($this->normalizeDate($m['date']));
            }

            if (preg_match("/^\s*(?<cabin>[[:alpha:] ]+)\s*\|\s*(?<duration>(?: *\d+ *[hm]+)+)\s*$/ui", $table[1] ?? '', $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->duration($m['duration'])
                ;
            }
        }

        return $email;
    }

    private function parseBus(Email $email, ?string $textPdf = null)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->re("/\b{$this->opt($this->t("eBucks Ref:"))} *(\d+[\-]?\d{5,})\n/", $textPdf), 'eBucks Ref')
            ->confirmation($this->re("/\b{$this->opt($this->t("QuickBus Ref:"))} *([\dA-Z]{5,})\n/", $textPdf), 'QuickBus Ref')
        ;

        // Bus
        $b = $email->add()->bus();

        // General

        $travellersText = $this->re("/\n *{$this->opt($this->t("Traveller details:"))}[^\n]+\n(.+?)\n *{$this->opt($this->t("Price breakdown:"))}/s", $textPdf);

        if (preg_match_all("/^ {0,10}([[:alpha:]](?: ?[^\d\s])+)(?: {3,}|$)/m", $travellersText, $m)) {
            $m[1] = preg_replace("/^(?:Mrs|Mr|Miss|Mstr|Ms|Dr)\s+/", '', $m[1]);
            $b->general()
                ->travellers($m[1]);
        }
        // Issued
        if (preg_match_all("/^\s*.{20,} {3,}([A-Z\d]{5,})(?:\n|$)/m", $travellersText, $m)) {
            $m[1] = array_unique($m[1]);
            $conf = implode(' ', array_column($email->getTravelAgency()->getConfirmationNumbers(), 0));

            foreach ($m[1] as $t) {
                if (strpos($conf, $t) === false) {
                    $b->addTicketNumber($t, false);
                }
            }
        }

        // Price
        $priceText = $this->re("/\n *{$this->opt($this->t("Price breakdown:"))}(.+)/s", $textPdf);

        $spent = $this->re("/\n *{$this->opt($this->t("eBucks paid:"))} +eB *(.+)\n/", $priceText);

        if (!empty((int) $spent)) {
            $b->price()
                ->spentAwards('eB ' . $spent);
        }

        $currency = null;
        $total = $this->re("/\n *{$this->opt($this->t("ZAR Paid:"))} +(.+?)(?: {3}|\n|$)/", $priceText);

        if (preg_match("/^\s*R\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)) {
            $currency = 'ZAR';
            $b->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        } else {
            $b->price()
                ->total(null);
        }

        $fareText = $this->re("/^\s*.+\bp ?\\/ ?p\b.*(\n[\s\S]+?)\n {0,20}[[:alpha:]](?: ?[[:alpha:]])+: +/", $priceText);

        if (preg_match_all("/^ +[[:alpha:]]+ {3,}(\d+) {3,}R +(\d[\d .,]+?) {3,}R +(\d[\d .,]+?) *$/m", $fareText, $m)
            && count($b->getTravellers()) == array_sum($m[1])
        ) {
            $currency = 'ZAR';
            $fare = 0.0;

            foreach ($m[2] as $i => $ft) {
                $fare += $m[1][$i] * PriceHelper::parse($ft, $currency);
            }
            $b->price()
                ->cost($fare);

            $taxes = 0.0;

            foreach ($m[3] as $i => $ft) {
                $taxes += $m[1][$i] * PriceHelper::parse($ft, $currency);
            }
            $b->price()
                ->tax($taxes);

            $fee = $this->re("/\n *{$this->opt($this->t("Booking Fee:"))} +R +(.+?)(?: {3}|\n|$)/", $priceText);

            if (!empty($fee)) {
                $b->price()
                    ->fee('Booking Fee', $fee);
            }
        }

        // Segments
        $segments = $this->split("/\n( {0,10}\S.+\n+(?: {10,}.*\n+){1,2} *{$this->opt($this->t("FROM"))} +{$this->opt($this->t("TO"))})/", $textPdf);

        foreach ($segments as $sText) {
            $conf = $this->re("/^.+?-(.+)/", $sText);

            if (!in_array($conf, array_column($b->getConfirmationNumbers(), 0))) {
                $b->general()
                    ->confirmation($conf);
            }

            $s = $b->addSegment();

            $tableText = $this->re("/\n *{$this->opt($this->t('FROM'))}.+\n([\s\S]+?)\n\n\s*Arrive/", $sText);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

            if (preg_match("/^\s*(?<name>[\s\S]+?),\n(?<name2>[\s\S]+?)\n\s*(?<code>[A-Z]{3})\s*\n(?<date>[\s\S]+)/u", $table[0] ?? '', $m)) {
                $s->departure()
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->address(preg_replace("/\s+/", ' ', trim($m['name2'])))
//                    ->code($m['code'])
                    ->geoTip("Africa")
                    ->date($this->normalizeDate($m['date']));
            }

            if (preg_match("/^\s*(?<name>[\s\S]+?),\n(?<name2>[\s\S]+?)\n\s*(?<code>[A-Z]{3})\s*\n(?<date>[\s\S]+)/u", $table[2] ?? '', $m)) {
                $s->arrival()
                    ->name(preg_replace("/\s+/", ' ', trim($m['name'])))
                    ->address(preg_replace("/\s+/", ' ', trim($m['name2'])))
//                    ->code($m['code'])
                    ->geoTip("Africa")
                    ->date($this->normalizeDate($m['date']));
            }

            $s->extra()
                ->noNumber();

            if (preg_match("/^\s*(?<duration>(?: *\d+ *[hm]+)+)\s*$/ui", $table[1] ?? '', $m)) {
                $s->extra()
                    ->duration($m['duration'])
                ;
            }
        }

        return $email;
    }

    private function parseRental(Email $email, ?string $textPdf = null)
    {
        // Travel Agency
        $conf = $this->re("/\b{$this->opt($this->t("eBucks Ref:"))} *(\d+[\-]?\d{5,})\n/", $textPdf);

        if ($email->getTravelAgency() && in_array($conf, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'rental') {
                    return null;
                }
            }
        } else {
            $email->ota()
                ->confirmation($conf, 'eBucks Ref')
            ;
        }

        // Rental
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->re("/\| *{$this->opt($this->t("Supplier Reference:"))} *([\dA-Z]{5,})(?: {3}|\n)/", $textPdf))
            ->traveller($this->re("/\n\s*{$this->opt($this->t("Driver's Details"))}(?:\n+.*){0,2}\n *{$this->opt($this->t("Name:"))} *(.+)\n/", $textPdf));

        // Price
        $priceText = $this->re("/\n *{$this->opt($this->t("Car price details"))}(.+)/s", $textPdf);

        $spent = $this->re("/\n *{$this->opt($this->t("eB paid:"))} +eB *(.+)\n/", $priceText);

        if (!empty((int) $spent)) {
            $r->price()
                ->spentAwards('eB ' . $spent);
        }

        $currency = null;
        $total = $this->re("/\n *{$this->opt($this->t("ZAR Paid:"))} +(.+?)(?: {3}|\n|$)/", $priceText);

        if (preg_match("/^\s*R\s*(?<amount>\d[\d\., ]*)\s*$/", $total, $m)) {
            $currency = 'ZAR';
            $r->price()
                ->total(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        } else {
            $r->price()
                ->total(null);
        }

        $fare = $this->re("/\n *{$this->opt($this->t("Car rental base Price:"))} +(.+)\n/", $priceText);

        if (preg_match("/^\s*R\s*(?<amount>\d[\d\., ]*)\s*$/", $fare, $m)) {
            $currency = 'ZAR';
            $r->price()
                ->cost(PriceHelper::parse($m['amount'], $currency))
                ->currency($currency)
            ;
        }
        $discount = $this->re("/\n *{$this->opt($this->t("Discount amount:"))} +(.+)\n/", $priceText);

        if (preg_match("/^\s*R\s*(?<amount>\d[\d\., ]*)\s*$/", $discount, $m)) {
            $currency = 'ZAR';
            $r->price()
                ->discount(PriceHelper::parse($m['amount'], $currency))
            ;
        }
        $fee = $this->re("/\n *{$this->opt($this->t("Booking fee:"))} +(.+)\n/", $priceText);

        if (preg_match("/^\s*R\s*(?<amount>\d[\d\., ]*)\s*$/", $fee, $m)) {
            $currency = 'ZAR';
            $r->price()
                ->fee('Booking fee', PriceHelper::parse($m['amount'], $currency))
            ;
        }

        // Segments
        $tableText = $this->re("/(\n .* {3}{$this->opt($this->t('Pick-up'))}(.*\n+){5}[\s\S]+?)\n(?: {0,10}\S|.*emergency number:)/", $textPdf);
        $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));

        if (preg_match("/^\s*{$this->opt($this->t('Pick-up'))}\s*\n(?<date>.+)\n(?<name>[\s\S]+?)\n\s*{$this->opt($this->t('Drop-off'))}/u", $table[1] ?? '', $m)) {
            $r->pickup()
                ->location(preg_replace("/\s+/", ' ', trim($m['name'])))
                ->date($this->normalizeDate($m['date']));
        }

        if (preg_match("/\n\s*{$this->opt($this->t('Drop-off'))}\s*\n(?<date>.+)\n(?<name>[\s\S]+?)(?:\n{3}|$)/u", $table[1] ?? '', $m)) {
            $r->dropoff()
                ->location(preg_replace("/\s+/", ' ', trim($m['name'])))
                ->date($this->normalizeDate($m['date']));
        }

        if (preg_match("/^\s*(?:.+\n){1,2}\n(?<model>[\s\S]+?\s+or\s+similar)\n/u", $table[0] ?? '', $m)) {
            $r->car()
                ->model(preg_replace("/\s+/", '', $m['model']));
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

    // additional methods

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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // 16:35 Fri, 21 Oct '22
            '/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+[[:alpha:]\-]+\s*,\s+(\d+)\s+([[:alpha:]]+)\s+\'\s*(\d{2})\s*$/ui',
            // Fri, 13 Jan '23 | 09:15
            '/^\s*[[:alpha:]\-]+\s*,\s+(\d+)\s+([[:alpha:]]+)\s+\'\s*(\d{2})\s*\|\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$2 $3 20$4, $1',
            '$1 $2 20$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

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

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
