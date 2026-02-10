<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "egencia/it-445402765.eml, egencia/it-458653800.eml, egencia/it-623948275.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (stripos($text, 'Contact the Egencia') !== false
                && stripos($text, 'Egencia reference #') !== false
                && stripos($text, 'Price details') !== false) {
                return true;
            }

            return false;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function ParseHotel(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Hotel confirmation:'))}\s*(\d+)/", $text), 'Hotel confirmation')
            ->traveller($this->re("/{$this->opt($this->t('Traveler'))}\s+{$this->opt($this->t('Phone number'))}.*\n+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/", $text), true)
            ->cancellation($this->re("/{$this->opt($this->t('Cancellation and Changes'))}\n+\s*(.+)\./", $text));

        $h->hotel()
            ->name($this->re("/Price details\s*\n\s*\s*(.+)/", $text))
            ->address($this->re("/\n {0,10}Address\b.*\n(?: {30,}.*\n)? {0,10}(\S.+)\n/", $text));

        if (preg_match("/{$this->opt($this->t('Check-in'))}\s+.+\n+\s*(?<checkIn>\w+\,\s*\w+\s*\d+\,\s*\d{4})\s*(?<checkOut>\w+\,\s*\w+\s*\d+\,\s*\d{4})/", $text, $m)) {
            $h->booked()
                ->checkIn($this->normalizeDate($m['checkIn']))
                ->checkOut($this->normalizeDate($m['checkOut']));
        }

        $info = $this->re("/^(\s+Room\s*Phone\/Fax\s*.+)\n\s*Address/ms", $text);
        $infoTable = $this->splitCols($info);

        if (preg_match("/\nP: ?((?:\S ?)+)/", $infoTable[1], $m)) {
            $h->hotel()
                ->phone($m[1]);
        }

        if (preg_match("/\nF: ?((?:\S ?)+)/", $infoTable[1], $m)) {
            $h->hotel()
                ->fax($m[1]);
        }

        if (preg_match("/Room\n+(?<rooms>\d+)\s*rooms?\s*\,\s*(?<guests>\d+)\s*adults?\s*(?<roomType>.+)/", $infoTable[0], $m)) {
            $h->booked()
                ->rooms($m['rooms'])
                ->guests($m['guests']);

            $room = $h->addRoom();
            $room->setType($m['roomType']);

            if (preg_match_all("/(\w+\,\s*\d{1,2}\/\d{1,2}\s*\D{1,2}\s*[\d\.\,]+)/", $text, $match)) {
                $room->setRates(preg_replace("/\s+/", " ", $match[1]));
            }
        }

        $total = $this->re("/{$this->opt($this->t('Total'))}\s*(\D{1,3}\s*[\d\.\,]+)/", $text);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/u", $total, $m)) {
            $h->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $tax = $this->re("/{$this->opt($this->t('Taxes & Fees'))}\s*\D{1,3}\s*([\d\.\,]+)/", $text);
            $h->price()
                ->tax(PriceHelper::parse($tax, $m['currency']));

            $fee = $this->re("/{$this->opt($this->t('Savings Finder for Hotel fee'))}\s*\D{1,3}\s*([\d\.\,]+)/", $text);

            if (!empty($fee)) {
                $h->price()
                    ->fee('Savings Finder for Hotel fee', PriceHelper::parse($fee, $m['currency']));
            }
        }

        if (stripos($text, 'Loyalty Program') !== false) {
            $account = $this->re("/Loyalty Program\n.+\s\:\s+([A-Z\dx]+)/", $text);

            if (!empty($account)) {
                $h->program()
                    ->account($account, true);
            }
        }

        $this->detectDeadLine($h);
    }

    public function ParseFlight(Email $email, string $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/{$this->opt($this->t('Flight confirmation:'))}\s*([\dA-Z]+)/", $text), 'Flight confirmation')
            ->traveller($this->re("/{$this->opt($this->t('Traveler'))}\s+{$this->opt($this->t('Phone number'))}.*\n+\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])/", $text), true);

        $ticket = $this->re("/Ticket\s*Total distance\n+\s*\d{10,}\s*(\d{10,})\s*/", $text);
        $f->addTicketNumber($ticket, false);

        $segments = $this->splitText($text, "/^(\s*.+CO[₂]\s+.+)\n/mu", true);
        //Friday, August 11, 2023 -
        $year = $this->re("/^\s*\w+\,\s*\w+\s*\d{1,2}\,\s*(\d{4})\s*\-/m", $text);

        if (stripos($text, 'Loyalty Program') !== false) {
            $account = $this->re("/Loyalty Program\n.+\s\:\s+([A-Z\dx]+)/", $text);

            if (!empty($account)) {
                $f->program()
                    ->account($account, true);
            }
        }

        foreach ($segments as $segment) {
            if (stripos($segment, 'Departure') !== false) {
                $s = $f->addSegment();

                if (preg_match("/\((?<airline>[A-Z\d]{2})\)\s*(?<number>\d{1,4})\s*.*[ ]{10,}(?<duration>\d+[hm\d\s]+)/", $segment, $m)) {
                    $s->airline()
                        ->name($m['airline'])
                        ->number($m['number']);

                    $s->extra()
                        ->duration($m['duration']);
                }

                if (preg_match("/\s*Departure\s*Arrival\s*(?:[+]\d)?\n\s*(?<depDate>\w+\,\s+\w+\s*\d+)\s*at\s*(?<depTime>[\d\:]+\s*a?p?m)\s*(?<arrDate>\w+\,\s*\w+\s*\d+)\s*at\s*(?<arrTime>[\d\:]+\s*a?p?m?)\s*.*\n.*\((?<depCode>[A-Z]{3})\-.+\((?<arrCode>[A-Z]{3})\-/su", $segment, $m)) {
                    $depDate = $m['depDate'] . ', ' . $m['depTime'];
                    $arrDate = $m['arrDate'] . ', ' . $m['arrTime'];

                    $s->departure()
                        ->code($m['depCode'])
                        ->date($this->normalizeDate($depDate, $year));

                    $s->arrival()
                        ->code($m['arrCode'])
                        ->date($this->normalizeDate($arrDate, $year));
                }

                if (preg_match("/^\s*(Terminal)\s*.*\n\s*(?<depTerminal>(?:\d+|\-|\D))[ ]{10,}(?<seat>\S+)[ ]{10,}(?<cabin>\w+)\s*\((?<bookingCode>[A-Z])\)/m", $segment, $m)) {
                    if (!preg_match("/\-/", $m['depTerminal'])) {
                        $s->departure()
                            ->terminal($m['depTerminal']);
                    }

                    if (!preg_match("/\-/", $m['seat'])) {
                        $s->extra()
                            ->seat($m['seat']);
                    }

                    $s->extra()
                        ->cabin($m['cabin'])
                        ->bookingCode($m['bookingCode']);
                }
            }
        }

        $total = $this->re("/{$this->opt($this->t('Total'))}\s*(\D{1,3}\s*[\d\.\,]+)/", $text);

        if (preg_match("/^(?<currency>\D{1,3})\s*(?<total>[\d\.\,]+)$/u", $total, $m)) {
            $f->price()
                ->total(PriceHelper::parse($m['total'], $m['currency']))
                ->currency($m['currency']);

            $cost = $this->re("/Base\s*\D{1,3}([\d\.\,]+)\n/", $text);

            if (!empty($cost)) {
                $f->price()
                    ->cost(PriceHelper::parse($cost, $m['currency']));
            }

            $tax = $this->re("/Taxes\s*\D{1,3}([\d\.\,]+)\n/", $text);

            if (!empty($tax)) {
                $f->price()
                    ->tax(PriceHelper::parse($tax, $m['currency']));
            }

            $priceText = $this->re("/(\s{1,3}\w+\s*\w+\s*Flight confirmation\:\s*[A-Z\d]+\s+Base\s*.*Total\s*\D{1,3}[\d\.\,]+\n)/s", $text);
            $pos = strpos($priceText, 'Base') - 2;

            if (!empty($pos = [0, $pos])) {
                $priceTable = $this->splitCols($priceText, $pos);

                if (preg_match_all("/((?:CC Fees|Air booking fee|Air booking fee \(negotiated\n*\s*rate\)|Air booking fee)\s*\D{1,3}[\d\.\,]+)\n/s", $priceTable[1], $match)) {
                    foreach ($match[1] as $fee) {
                        if (preg_match("/(?<feeName>(?:CC Fees|Air booking fee|Air booking fee \(negotiated\n*\s*rate\)|Air booking fee))\s*\D{1,3}(?<feeSum>[\d\.\,]+)/", $fee, $match)) {
                            $f->price()
                                ->fee($match['feeName'], $match['feeSum']);
                        }
                    }
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $email->ota()->confirmation($this->re("/{$this->opt($this->t('Egencia reference #'))}\s*(\d+)/", $text), 'Egencia reference #');

            if (stripos($text, 'Hotel confirmation:') !== false) {
                $this->ParseHotel($email, $text);
            }

            if (stripos($text, 'Flight confirmation:') !== false) {
                $this->ParseFlight($email, $text);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function normalizeDate($date, $year = null)
    {
        $in = [
            // Sun, Jan 28, 6:15 pm
            '/^([-[:alpha:]]+)\s*,\s*([[:alpha:]]+)[.]?\s+(\d{1,2})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/ui',
        ];
        $out = [
            '$1, $3 $2 ' . $year . ', $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b20\d{2}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function detectDeadLine($h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/There is no Hotel penalty for cancellations made before\s*(?<time>\d+\:\d+\s*A?P?M?)\s*local hotel time on\s*(?<date>[\d\/]+\d{4})/", $cancellationText, $m)
        || preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+\s*A?P?M)\s*local hotel time on\s*(?<date>[\d\/]+)\s+/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['date'] . ', ' . $m['time']));
        }
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
        $r = array_values(array_filter($rows));

        if (!$pos) {
            $pos = $this->rowColsPos($r[0]);
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
