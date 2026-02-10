<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationHotelPdf extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-109332766.eml";
    public $detectSubjects = [
        'Confirmation - ',
        'Cancellation - ',
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
            'Booking made by' => 'Booking made by',
            'Provider:' => 'Provider:',
//            'Reservation Cancelled' => '',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || stripos($headers['from'], '@amadeus.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $subject) {
            if (strpos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach (self::$dictionary as $lang => $dict) {
                if (isset($dict['Booking made by'], $dict['Provider:'])) {
                    $pos = $this->striposAll($text, $dict['Booking made by']);
                    if ($pos !== false) {
                        $str = substr($text, $pos-20, 200);
                        if (preg_match("/\n\s*{$this->opt($this->t($dict['Booking made by']))} .+\b\d{1,2}:\d{2}\b.* {3,}{$this->opt($this->t($dict['Provider:']))}.+\n/", $str)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    public function ParsePDF(Email $email, $text)
    {
//        $this->logger->debug($text);

        // Travel Agency
        $otaConf = $this->re("/{$this->opt($this->t('PNR Number:'))} *([A-Z\d]{5,})\s*\n/", $text);
        $email->ota()
            ->confirmation($otaConf, 'PNR Number');

        // HOTEL

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation Number:'))} *([A-Z\d]{5,})\s*\n/", $text))
            ->travellers($this->res("/ {3,}{$this->opt($this->t('Name'))} \d+ *: *(?:MR |MISS |MS |MRS )?(.+)/ui", $text), true)
            ->cancellation($this->re("/\n *\W? *{$this->opt($this->t('Cancellation Policy'))}\n(.+)\n/u", $text));

        if (preg_match("/".$this->opt($this->t("Reservation Cancelled"))."/", $text)) {
            $h->general()
                ->cancelled();
        }
        $hotel = $this->re("/{$this->opt($this->t('Provider:'))}.+\n\s*(.+?)(?: {3,}|\n)/", $text);
        $address = $this->re("/{$this->opt($this->t('Provider:'))}.+\n\s*(?:.+?)(?: {3,}.*)?\n((?:.*\n)+)\s*{$this->opt($this->t('Stay details'))}/", $text);

        $address = preg_replace("/^(.{30,}?) {5,}.*/m", '$1', $address);
        $h->hotel()
            ->name($hotel)
            ->address(preg_replace('/\s+/', ' ', trim($address)))
            ->phone($this->re("/{$this->opt($this->t('Tel:'))} *([\d\(\) \-+]{5,})\s*\n/", $text), true, true)
            ->fax($this->re("/{$this->opt($this->t('Fax:'))} *([\d\(\) \-+]{5,})\s*\n/", $text), true, true)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('Check-in:'))} *(.+?)(?: {3,}|\n)/", $text)))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('Check-out:'))} *(.+?)(?: {3,}|\n)/", $text)))
            ->rooms($this->re("/{$this->opt($this->t('Stay details'))}(?: .*)?\n\s*(\d+) *{$this->opt($this->t('Room'))}/", $text))
        ;

        // Rooms
        $roomDescriptions = $this->res("/\n\s*{$this->opt($this->t('Room'))} \d+\s*\n *\W? *{$this->opt($this->t('Room and rates description'))}\n *(.+)/u", $text);
        foreach ($roomDescriptions as $description) {
            $room = $h->addRoom();
            $room->setDescription($description);
        }

        // Price
        $priceText = $this->re("/{$this->opt($this->t('Total Price:'))} *(.+)/", $text);
        if (preg_match("/^(?<total>[ \d\.\,]+)\s*(?<currency>[A-Z]{3})$/s", $priceText, $m)) {
            $h->price()
                ->total(PriceHelper::cost($m['total']))
                ->currency($m['currency']);
        }

        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach (self::$dictionary as $lang => $dict) {
                if (isset($dict['Booking made by'], $dict['Provider:'])) {
                    $pos = $this->striposAll($text, $dict['Booking made by']);
                    if ($pos !== false) {
                        $str = substr($text, $pos-20, 200);
                        if (preg_match("/\n\s*{$this->opt($this->t($dict['Booking made by']))} .+\b\d{1,2}:\d{2}\b.* {3,}{$this->opt($this->t($dict['Provider:']))}.+\n/", $str)) {
                            $this->lang = $lang;
                            $this->ParsePDF($email, $text);
                            continue 2;
                        }
                    }
                }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }
        // Cancel before Friday, August 27, 2021 19:00 local hotel time to avoid a charge of 74.87 EUR
        if (
            preg_match('/^\s*Cancel before \w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+)\s*[\w ]+? to avoid a /u', $cancellationText, $m)
        ) {
            $h->booked()->deadline($this->normalizeDate($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]));
        }
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('IN-' . $str);
        $in = [
            "#^(\d+\s*\w+\s*\d{4}\s*[\d\:]+)$#s", //02 ago 2021
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function striposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = stripos($text, $n);
                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return stripos($text, $needle);
        }

        return false;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

}
