<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripNotificationPDF extends \TAccountChecker
{
    public $mailFiles = "egencia/it-12539951.eml, egencia/it-171129115.eml";
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

            if (strpos($text, 'Egencia, LLC') === false
                && strpos($text, 'including Ovation Travel Group and Egencia') === false
                && $this->http->XPath->query("//text()[contains(normalize-space(), 'Egencia, LLC')]")->length === 0
            ) {
                continue;
            }

            if ((strpos($text, 'Trip Notification') !== false
                    || strpos($text, 'Booking Confirmation') !== false)
                && strpos($text, 'Rules and regulations') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]mail\.egencia\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        // $this->logger->debug('flight segment : '."\n".print_r( $text,true));

        $confirmaion = $this->re("/CONFIRMATION\s*TICKET\n+\s*([A-Z\d]{5,7})(?: {3,}|\n)/", $text);

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight'
                && in_array($confirmaion, array_column($it->getConfirmationNumbers(), 0))
            ) {
                $f = $it;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $f->general()
                ->confirmation($confirmaion)
                ->date(strtotime($this->re("/\s*(\w+\s*\d+\,\s*\d{4})\n+\s*Booked/", $text)));
        }

        if (preg_match("/CONFIRMATION\s*TICKET\n+\s*[A-Z\d]{5,7} {3,}(\d{3}\W?\d{5,})\n/", $text, $m)
            && !in_array($m[1], array_column($f->getTicketNumbers(), 0))
        ) {
            $f->issued()
                ->ticket($m[1], false);
        }

        if (preg_match("/Operated by.+\s(\d+)\s*DEPARTURE/su", $text, $m)
            && !in_array($m[1], array_column($f->getAccountNumbers(), 0))
        ) {
            $f->program()
                ->account($m[1], false);
        }

        // Segments
        $s = $f->addSegment();

        if (preg_match("/(?<name>[A-Z\d]{2})(?<number>\d{2,4})\n\s*Operated by\s*(?<operator>.+)/u", $text, $m)) {
            $s->airline()
                ->name($m['name'])
                ->number($m['number'])
                ->operator($m['operator']);
        }

        if (preg_match("/\s+(?<depCode>[A-Z]{3})\s*to\s*(?<arrCode>[A-Z]{3})\s*[#](?<itNumber>\d{9,})/", $text, $m)) {
            $s->departure()
                ->code($m['depCode']);

            $s->arrival()
                ->code($m['arrCode']);
        }

        if (preg_match("/DEPARTURE\s*ARRIVAL\n+\s*(?<depDate>\w+\s*\s*\d+\,\s*\d{4}\s*at\s*[\d\:]+\s*a?p?m)\s*(?<arrDate>\w+\s*\s*\d+\,\s*\d{4}\s*at\s*[\d\:]+\s*a?p?m)/us", $text, $m)) {
            $s->departure()
                ->date($this->normalizeDate(trim($m['depDate'])));

            $s->arrival()
                ->date($this->normalizeDate(trim($m['arrDate'])));
        }

        $extraText = $this->re("/(TERMINAL.+)CARBON FOOTPRINT/s", $text);
        $extraTable = $this->splitCols($extraText);

        $dTerminal = $this->re("/TERMINAL\n*\s*(\S.*?)\s*\n *SEAT/su", $extraTable[0]);

        if (!$dTerminal) {
            $s->departure()
                ->terminal($dTerminal);
        }
        $seat = $this->re("/SEAT\s+(\d{1,3}[A-Z])\s*(?:\n|$)/su", $extraTable[0]);

        if (!empty($seat)) {
            $s->extra()
                ->seat($seat);
        }

        $duration = $this->re("/DURATION\s*(.+)/su", $extraTable[1]);

        if (!empty($duration)) {
            $s->extra()
                ->duration($duration);
        }

        $cabin = $this->re("/CLASS\s*(.+)\s*DURATION/su", $extraTable[1]);

        if (!empty($cabin)) {
            $s->extra()
                ->cabin($cabin);
        }

        $partText = $this->re("/(TRAVELLERS.+)MAIN CONTACT/su", $text);

        if (!empty($partText)) {
            $partTable = $this->splitCols($partText);

            $travellers = array_filter(explode("\n", $this->re("/TRAVELLERS(.+)TOTAL DURATION/su", $partTable[0])));
            $f->general()
                ->travellers($travellers);

            if (preg_match("/PAYMENT\s*Base[\s\:]+\D(?<cost>[\d\.]+)\s*Taxes[\s\:]+\D(?<tax>[\d\.\,]+)\s*Total Price[\s\:]+(?<currency>\D)(?<total>[\d\.\,]+)/su", $partTable[1], $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $f->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['total'], $currency))
                    ->cost($m['cost'])
                    ->tax($m['tax']);
            }
        }
    }

    public function ParseHotelPDF(Email $email, $text)
    {
        // $this->logger->debug('hotel segment : '."\n".print_r( $text,true));
        $h = $email->add()->hotel();

        $confirmation = $this->re("/\n *CONFIRMATION\n{1,2}(\w+)\n/", $text);

        if (!empty($confirmation)) {
            $h->general()
                ->confirmation($confirmation);
        } else {
            $h->general()
                ->noConfirmation();
        }

        $h->hotel()
            ->name($this->re("/^(\D+)\s+[#]\d{9,}/", $text))
            ->address($this->re("/ADDRESS\n+(.+)/", $text));

        if (preg_match("/(?<checkIn>\w+\s*\d+\,\s*\d{4})\s*(?<checkOut>\w+\s*\d+\,\s*\d{4})\s*\n+\s*ADDRESS/", $text, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']));
        }

        $roomType = $this->re("/ROOM TYPE\n+((?:.+\n){1,7}?)\n[A-Z][A-Z ]+\n/", $text);
        $roomType = preg_replace('/\s+/', ' ', trim($roomType));

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $h->booked()
            ->rooms($this->re("/\n *ROOMS +PHONE\n+\s*(\d+)/", $text))
            ->guests($this->re("/\n *ADULTS +DURATION\n+\s*(\d+)\s/", $text));

        $partText = $this->re("/(TRAVELLERS.+)BOOKED BY/su", $text);

        if (!empty($partText)) {
            $partTable = $this->splitCols($partText);

            $travellers = array_filter(explode("\n", $this->re("/TRAVELLERS(.+)MAIN CONTACT/su", $partTable[0])));
            $h->general()
                ->travellers($travellers);

            if (preg_match("/Total Price[\s\:]+(?<currency>\D)(?<total>[\d\.\,]+)/su", $partTable[1], $m)) {
                $currency = $this->normalizeCurrency($m['currency']);
                $h->price()
                    ->currency($currency)
                    ->total(PriceHelper::parse($m['total'], $currency));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $segmentArray = $this->splitText($text, "/([ ]{8,}.+[ ]{15,}[#]\d{10,}\n)/", true);

            foreach ($segmentArray as $segment) {
                if (strpos($segment, 'CHECK-IN') !== false) {
                    $this->ParseHotelPDF($email, $segment);
                } elseif (strpos($segment, 'DURATION') !== false) {
                    $this->ParseFlightPDF($email, $segment);
                }
            }
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

    private function splitText(string $textSource, string $pattern, bool $saveDelimiter = false): array
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

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^(\w+\s*)\s*(\d+)\,\s*(\d{4})\s*at\s*([\d\:]+\s*a?p?m)$#u", //Jun 20, 2022 at 12:55 pm
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
