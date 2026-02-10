<?php

namespace AwardWallet\Engine\carlson\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It5 extends \TAccountChecker
{
    public $mailFiles = "carlson/it-5.eml";

    public $reBody = [
        ['Arrival Date', 'Your Confirmation Number is'],
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";

    private $code;
    private $bodies = [
        'carlson' => [
            '@countryinns.com',
        ],
        'milleniumnclc' => [
            '@MILLENNIUMHOTELS.COM',
            'Millennium',
        ],
    ];
    private static $headers = [
        'carlson' => [
            'from' => ['countryinns.com'],
            'subj' => [
                'Confirmation #',
            ],
        ],
        'milleniumnclc' => [
            'from' => ['millenniumhotels.com'],
            'subj' => [
                'Millennium New York Broadway and Premier Confirmation',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $pdfText = '';

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if (!$this->detectText($text)) {
                        $this->logger->debug('another format pdf. skip');

                        continue;
                    }
                    $this->parseEmailPdf($text, $email);
                    $pdfText .= $text;
                }
            }
        }

        if (null !== ($code = $this->getProvider($parser, $pdfText))) {
            $email->setProviderCode($code);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectText($text)) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    private function getProvider(\PlancakeEmailParser $parser, $pdfText)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->code)) {
            return $this->code;
        }
        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && stripos($body, $search) !== false)
                        || (stripos($pdfText, $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmailPdf($textPDF, Email $email)
    {
        $r = $email->add()->hotel();
        $r->general()->confirmation($this->re("/Your Confirmation Number is\s([0-9]+)/", $textPDF));

        if (($node = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'Dear ')][1]", null, true, '/Dear (.+),/'))) {
            $r->addTraveller($node);
        }

        $table = $this->re("/\n\n( *Arrival Date.+?)(?:\n\s*Your Confirmation Number|\n\n)/s", $textPDF);
        $table = $this->splitCols($table, $this->colsPos($this->inOneRow($table), 0));

        if (count($table) !== 4) {
            $this->logger->debug('other format');

            return false;
        }

        $r->booked()
            ->checkIn($this->normalizeDate($this->re("/Arrival Date\s+(.+)/", $table[0])))
            ->checkOut($this->normalizeDate($this->re("/Departure Date\s+(.+)/", $table[1])));

        $timeIn = $this->re("#Check in begins at (\d{1,2}:\d{2}(?: ?[ap]m)?)#i", $textPDF);

        if (!empty($timeIn) && !empty($r->getCheckInDate())) {
            $r->booked()->checkIn(strtotime($timeIn, $r->getCheckInDate()));
        }
        $timeOut = $this->re("#check out is at (\d{1,2}:\d{2}(?: ?[ap]m)?)#i", $textPDF);

        if (!empty($timeOut) && !empty($r->getCheckOutDate())) {
            $r->booked()->checkOut(strtotime($timeOut, $r->getCheckOutDate()));
        }

        $room = $r->addRoom();

        $room
            ->setType($this->re("/Room Type\s+(.+)/", $table[3]))
            ->setRate($this->re("/{$this->opt(['Nightly Rate', 'Rate'])}\s+(.+)/", $table[2]));

        $hotelInfo = $this->re("/{$this->opt(['Reservation Office', 'The Reservations Team', 'Guest Service Team'])}\n\n\n\n\n+(.+)$/s",
            $textPDF);

        if (preg_match("/^\s*(?<name>[^\n]+)\n(?<addr>.+?)\s+(?:Phone|Tel|Telephone): (?<ph>[\d\-\+\(\) ]+?)[\s*]+Fax: (?<f>[\d\-\+\(\) ]+?)\s+(?:\*|Email)/s",
            $hotelInfo, $m)) {
            $r->hotel()
                ->name($m['name'])
                ->address(preg_replace("/\s+/", ' ', $m['addr']))
                ->phone($m['ph'])
                ->fax($m['f']);
        }

        if (preg_match("/check-in time is (\d+:\d+(?:\s*[ap]m)?)/i", $textPDF, $m)) {
            $r->booked()->checkIn(strtotime($m[1], $r->getCheckInDate()));
        }

        if (preg_match("/check-out time is (\d+:\d+(?:\s*[ap]m)?)/i", $textPDF, $m)) {
            $r->booked()->checkOut(strtotime($m[1], $r->getCheckOutDate()));
        }

        $cancelStart = [
            'If you find it necessary to cancel or change plans',
            'If you find it necessary to cancel or change your plans',
            'If you wish to cancel or change your reservation',
        ];

        if (!empty($cancelText = $this->re("#({$this->opt($cancelStart)}.*?)(?:\.\n|\n\n)#s", $textPDF))) {
            $cancelText = preg_replace("/\s+/", ' ', $cancelText);
            $r->general()->cancellation($cancelText);
            $this->detectDeadLine($r, $cancelText);
        }

        return true;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        //Here we describe various variations in the definition of dates deadLine
        if (preg_match("/please contact us by (\d+ *[ap]m|\d+:\d+(?:\s*[ap]m)?) .+? Time the day prior to (\d+\-\d+\-\d+) to avoid a charge of/i",
                $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1], strtotime("-1 day", $this->normalizeDate($m[2]))));
        } elseif (
                preg_match("/please inform us by (\d+ *[ap]m|\d+:\d+(?:\s*[ap]m)?) on the day of arrival to avoid one night's room and tax charge to your credit card/i", $cancellationText, $m)
            || preg_match("/please inform us by (\d+ *[ap]m|\d+:\d+(?:\s*[ap]m)?) [A-Z]{2,4} on the day before your arrival to avoid any fees to be charged/i", $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative("1 day", $m[1]);
        }
    }

    private function normalizeDate($date)
    {
        $in = [
            //11-15-18
            '#^(\d+)\-(\d+)\-(\d{2})$#u',
        ];
        $out = [
            '20$3-$1-$2',
        ];
        $str = strtotime(preg_replace($in, $out, $date));

        return $str;
    }

    private function detectText($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function splitCols($text, $pos = false)
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

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $row) {
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
