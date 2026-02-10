<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightPDF extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-181030022.eml";
    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";
    public $flightOrder = 0;

    public static $dictionary = [
        "en" => [
            'Flight'       => ['Flight', 'Departing flight', 'Departing ﬂight'/*<- not dellete - fl is one special symbol*/],
            'Booking date' => ['Booking date', 'Travel booked/ticket issued on'],
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

            if (strpos($text, "baggage can be found in Air Canada's") !== false
                && (strpos($text, 'Flight 1') !== false || strpos($text, 'Departing flight') !== false || strpos($text, 'Departing ﬂight') !== false)
                && (strpos($text, 'Purchase summary') !== false || strpos($text, 'Baggage allowance') !== false)
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]vacv\.com$/', $from) > 0;
    }

    public function ParseFlightPDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("/Booking reference[\s\:]+([A-Z\d]+)\s/", $text))
            ->date(strtotime(str_replace(',', '', $this->re("#{$this->opt($this->t('Booking date'))}[\s\:]+(\d+\s*\w+\,\s*\d{4})#", $text))));

        if (preg_match_all("/\n[]*\s*([[:alpha:]][-&.\'’[:alpha:] ]*[[:alpha:]])\n+\s*Ticket\s*Number/u", $text, $m)) {
            $f->general()
                ->travellers($m[1], true);
        }

        if (preg_match_all("/Ticket Number\n\s*(\d+)\n/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);
        }

        $priceText = $this->re("/(GRAND TOTAL.+)/u", $text);

        if (preg_match("/GRAND TOTAL[\s\-]+(?<currency>\D+)\s+\D(?<total>[\d\.\,]+)/", $priceText, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);
            $f->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        $airlines = array_filter(explode("\n", str_replace("\n\n", "\n", $this->re("/Seats\n+(.+)(?:[]|Departing flight|Departing ﬂight)/su", $text))));
        $flightText = $this->re("/\n{2,}(\s*{$this->opt($this->t('Flight'))}\s*\d*.+)\n{2,}Purchase summary/s", $text);

        if (empty($flightText)) {
            $flightText = $this->re("/\n{2,}(\s*{$this->opt($this->t('Flight'))}\s*\d*.+)\n{2,}Baggage allowance/s", $text);
        }

        if (!empty($flightText)) {
            $flightParts = array_filter(preg_split("/\n\n\n\n\n/", $flightText));

            foreach ($flightParts as $flightPart) {
                if (stripos($flightPart, 'Non-stop') === false) { //1 Stop - 16hr40m +1
                    $s = $f->addSegment();

                    $seatsText = $this->re("/^[A-Z\d]{2}\d{2,4}\s*(.+)/", $airlines[$this->flightOrder]);

                    if (stripos($seatsText, '-') === false) {
                        $seats = explode(',', $seatsText);
                        $s->extra()
                            ->seats($seats);
                    }

                    $s->airline()
                        ->name($this->re("/^([A-Z\d]{2})/", $airlines[$this->flightOrder]))
                        ->number($this->re("/^[A-Z\d]{2}(\d{2,4})/", $airlines[$this->flightOrder]));

                    $s->departure()
                        ->code($this->re("/^.+\(([A-Z]{3})\).+\([A-Z]{3}\)/u", $flightPart));

                    if (preg_match("/\s*{$this->opt($this->t('Flight'))}\s*\d*\s*(?<date>\w+\,\s*\w+\s*\d+\,\s*\d{4}).+\n+\s*(?<depTime>[\d\:]+)\s*(?<stop>\d+)\s*Stop[\-\s]+(?<duration>\S+)\s*(?<nextDay>[+]\d)?\s*\s(?<arrTime>[\d\:]+)\n\s*(?<cabin>.+)\n/", $flightPart, $m)) {
                        $s->departure()
                            ->date($this->normalizeDate($m['date'] . ', ' . $m['depTime']));

                        $s->extra()
                            ->cabin($m['cabin']);

                        $s->extra()
                            ->stops($m['stop']);
                    }

                    $s->arrival()
                        ->noDate()
                        ->code($this->re("/Adults\n+\s*([A-Z]{3})\s*/", $flightPart));

                    ++$this->flightOrder;

                    //----------------------------------- Next Segment after stop --------------------------------

                    $s = $f->addSegment();

                    $seatsText = $this->re("/^[A-Z\d]{2}\d{2,4}\s*(.+)/", $airlines[$this->flightOrder]);

                    if (stripos($seatsText, '-') === false) {
                        $seats = explode(',', $seatsText);
                        $s->extra()
                            ->seats($seats);
                    }

                    $s->airline()
                        ->name($this->re("/^([A-Z\d]{2})/", $airlines[$this->flightOrder]))
                        ->number($this->re("/^[A-Z\d]{2}(\d{2,4})/", $airlines[$this->flightOrder]));

                    $s->departure()
                        ->code($this->re("/Adults\n+\s*([A-Z]{3})\s*/", $flightPart))
                        ->noDate();

                    $s->arrival()
                        ->code($this->re("/^.+\([A-Z]{3}\).+\(([A-Z]{3})\)/u", $flightPart));

                    if (preg_match("/\s*{$this->opt($this->t('Flight'))}\s*\d*\s*(?<date>\w+\,\s*\w+\s*\d+\,\s*\d{4}).+\n+\s*(?<depTime>[\d\:]+)\s*\d+\s*Stop[\-\s]+(?<duration>\S+)\s*(?<nextDay>[+]\d)?\s*\s(?<arrTime>[\d\:]+)\n\s*(?<cabin>.+)\n/", $flightPart, $m)) {
                        if (isset($m['nextDay'])) { //+1 day
                            $s->arrival()
                                ->date(strtotime('+1 day', $this->normalizeDate($m['date'] . ', ' . $m['arrTime'])));
                        } else {
                            $s->arrival()
                                ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']));
                        }

                        $s->extra()
                            ->cabin($m['cabin']);
                    }

                    $s->extra()
                        ->stops(0);

                    ++$this->flightOrder;
                } else { //Non-stop
                    $s = $f->addSegment();

                    $seatsText = $this->re("/^[A-Z\d]{2}\d{2,4}\s*(.+)/", $airlines[$this->flightOrder]);

                    if (stripos($seatsText, '-') === false) {
                        $seats = explode(',', $seatsText);
                        $s->extra()
                            ->seats($seats);
                    }

                    $s->airline()
                        ->name($this->re("/^([A-Z\d]{2})/", $airlines[$this->flightOrder]))
                        ->number($this->re("/^[A-Z\d]{2}(\d{2,4})/", $airlines[$this->flightOrder]));

                    $s->departure()
                        ->code($this->re("/^.+\(([A-Z]{3})\).+\([A-Z]{3}\)/u", $flightPart));

                    $s->arrival()
                        ->code($this->re("/^.+\([A-Z]{3}\).+\(([A-Z]{3})\)/u", $flightPart));

                    if (preg_match("/\s*{$this->opt($this->t('Flight'))}\s*\d*\s*(?<date>\w+\,\s*\w+\s*\d+\,\s*\d{4}).+\n+\s*(?<depTime>[\d\:]+)\s*Non-stop[\-\s]+(?<duration>\S+).+\s(?<arrTime>[\d\:]+)\n\s*(?<cabin>.+)\n/u", $flightPart, $m)) {
                        $s->departure()
                            ->date($this->normalizeDate($m['date'] . ', ' . $m['depTime']));

                        if (preg_match("/{$this->opt($this->t('Non-stop'))}[\s\-]+\S+\s+[\d\:]+/", $flightPart)) {
                            $s->arrival()
                                ->date($this->normalizeDate($m['date'] . ', ' . $m['arrTime']));
                        } else { //+1 day
                            $s->arrival()
                                ->date(strtotime('+1 day', $this->normalizeDate($m['date'] . ', ' . $m['arrTime'])));
                        }

                        $s->extra()
                            ->stops(0)
                            ->cabin($m['cabin'])
                            ->duration($m['duration']);
                    }

                    ++$this->flightOrder;
                }
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
            "#^\w+\,\s*(\w+)\s(\d+)\,\s*(\d{4})\,\s*([\d\:]+)$#u", //Sunday, October 16, 2022, 13:15
        ];
        $out = [
            "$2 $1 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

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
}
