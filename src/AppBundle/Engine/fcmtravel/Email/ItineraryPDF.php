<?php

namespace AwardWallet\Engine\fcmtravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryPDF extends \TAccountChecker
{
    public $mailFiles = "fcmtravel/it-251159643.eml";
    public $lang = 'en';
    public $emailProv = ['@in.fcm.travel'];
    public $pdfNamePattern = "(?:[A-Z\d]{8,}|Ticket).*pdf";
    public $pdfNamePattern2 = "Mr.*\s[A-Z]{3}\-[A-Z]{3}.*pdf";

    public static $dictionary = [
        "en" => [
            'Date :' => ['Date :', 'Generation Time'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) == 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern2);
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, '@in.fcm.travel') !== false && strpos($text, 'by Air') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->emailProv as $emailFrom) {
            if (preg_match("/{$this->opt($emailFrom)}/u", $from)) {
                return true;
            }
        }

        return false;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $flightParts = $this->splitText($text, "/\n+(\s*\d+\/\d+\/\d{4}\s*\-.*\-\s*by\s*Air)/u", true, false);

        foreach ($flightParts as $flightPart) {
            if (stripos($flightPart, 'by Air') == false) {
                continue;
            }

            $f = $email->add()->flight();
            $f->general()
                ->confirmation($this->re("/{$this->opt($this->t('Reference Number :'))}\s*([A-Z\d]{8,})/", $text))
                ->date(strtotime($this->re("/{$this->opt($this->t('Date :'))}\s*(\d+\-\w+\-\d{4})/", $text)));

            if (preg_match_all("/{$this->opt($this->t('Freq. Flier:'))}\s*([A-Z\d]{7,})/", $flightPart, $m)) {
                $f->setAccountNumbers(array_filter(array_unique($m[1])), false);
            }

            if (preg_match_all("/\n+^(\s*(?:Mrs|Mr|Ms)\s*.+)\n*\s*\(Adult\)/mu", $flightPart, $m)) {
                $cabin = [];
                $ticket = [];

                foreach ($m[1] as $paxText) {
                    $paxTable = $this->splitCols($paxText);
                    $f->general()
                        ->traveller($paxTable[0]);
                    $cabin[] = $paxTable[2];

                    if (stripos($paxTable[5], 'N/A') === false) {
                        $ticket[] = $paxTable[5];
                    }
                }
            }

            if (preg_match("/\/(?<year>\d{4})\s.*by\s*Air\n*\s*.+\n\s*(?<airlineName>[A-Z\d]{2})[\-\s]+(?<flightNumber>\d{2,4})\n*Departs\s*(?<depDate>.+)[ ]{4,}.*\-\s*(?<depCode>[A-Z]{3})\)\,?\s*(?:Terminal\:\s*(?<depTerminal>.*))?\n+Arrives\s*(?<arrDate>.+)[ ]{4,}.*\-\s*(?<arrCode>[A-Z]{3})\)\,?\s*(?:Terminal\:\s*(?<arrTerminal>.*))?\n/u", $flightPart, $m)) {
                $s = $f->addSegment();

                if (count($cabin) > 0) {
                    $s->setCabin(implode(",", array_unique(array_filter($cabin))));
                }

                if (count($ticket) > 0) {
                    $f->setTicketNumbers(array_filter(array_unique($ticket)), false);
                }

                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['flightNumber']);

                $s->departure()
                    ->code($m['depCode'])
                    ->date($this->normalizeDate($m['depDate'] . ' ' . $m['year']));

                if (isset($m['depTerminal']) && !empty($m['depTerminal'])) {
                    $s->departure()
                        ->terminal($m['depTerminal']);
                }

                $s->arrival()
                    ->code($m['arrCode'])
                    ->date($this->normalizeDate($m['arrDate'] . ' ' . $m['year']));

                if (isset($m['arrTerminal']) && !empty($m['arrTerminal'])) {
                    $s->arrival()
                        ->terminal($m['arrTerminal']);
                }
            }

            if (preg_match_all("/{$this->opt($this->t('Seat:'))}\s*(\d+[A-Z])/", $flightPart, $m)) {
                $s->setSeats($m[1]);
            }

            if (preg_match_all("/{$this->opt($this->t('Meal:'))}\s*(\D+)\,\s*{$this->opt($this->t('Seat:'))}/", $flightPart, $m)) {
                $s->setMeals(array_filter(array_unique($m[1])));
            }

            if (preg_match("/Total Price \*\s*(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\s/", $flightPart, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m['total'], $m['currency']))
                    ->currency($m['currency']);
            }
        }
    }

    /*public function ParseHotelPDF(Email $email, $text)
    {
        $hotelText = $this->re("/{$this->opt($this->t('Hotel Details'))}\n(.+)\n{$this->opt($this->t('Product Details'))}/s", $text);
        $hotelParts = array_filter(preg_split("/Hotel Description\D+Passengers\n/", $hotelText));

        foreach ($hotelParts as $hotelPart) {
            if (preg_match("/^(?<hotelName>.+)\n\s*(?<address>.+)\s+(?<arrDate>[\w\-]+\d{4})\s+(?<depDate>[\w\-]+\d{4})\s+\d+\s+(.+)\s+\d+\-(?<guestCount>\d+)\n/", $hotelPart, $m)) {
                if (trim($m['hotelName'], '*') == trim($this->hotelName, '*') && strtotime($m['arrDate']) == $this->arrDate && strtotime($m['depDate']) == $this->depDate) {
                    continue;
                }

                $h = $email->add()->hotel();

                $h->general()
                    ->confirmation($this->re("/Booking\s*(\d+)\s*PNR/", $text));

                $paxText = $this->re("/{$this->opt($this->t('Invoice Details'))}\n{$this->opt($this->t('Passengers'))}\D+(.+)\n{$this->opt($this->t('Product(s)'))}/su", $text);

                if (preg_match_all("/(?:^|\n)\d\s*([[:alpha:]][-.'’[:alpha:] ]*[[:alpha:]])/", $paxText, $match)) {
                    $match[1] = array_filter(array_unique(($match[1])));

                    $h->general()
                        ->travellers(str_replace(['MSTR', 'MRS', 'MR', 'MS'], '', array_filter($match[1])), true);
                }

                $h->hotel()
                    ->name(trim($m['hotelName'], '*'))
                    ->address($m['address']);

                $h->booked()
                    ->checkIn(strtotime($m['arrDate']))
                    ->checkOut(strtotime($m['depDate']))
                    ->guests($m['guestCount']);

                $this->hotelName = $h->getHotelName();
                $this->arrDate = strtotime($m['arrDate']);
                $this->depDate = strtotime($m['depDate']);
            }
        }
    }*/

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) == 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern2);
        }

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'by Air') !== false) {
                $this->ParseFlightPDF($email, $text);
            }

            /*if (strpos($text, 'Hotel Details') !== false) {
                $this->ParseHotelPDF($email, $text);
            }*/
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

    private function re($re, $str, $c = 1)
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^([\d\:]+\s*a?p?m?)\,\s*(\w+)\s*(\d+)\-(\w+)\s*(\d{4})$#ui", //09:55, Sun 08-Jan 2023
        ];
        $out = [
            "$2, $3 $4 $5, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false, $deleteFirst = true): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);

            if ($deleteFirst === false) {
                $result[] = array_shift($textFragments);
            } else {
                array_shift($textFragments);
            }

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
