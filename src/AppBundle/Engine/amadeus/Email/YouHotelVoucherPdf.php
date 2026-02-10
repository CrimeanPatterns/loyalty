<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YouHotelVoucherPdf extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-109332774.eml";
    public $detectSubjects = [
        'Booking Confirmation - ',
    ];


    public $detectLang = [
        'en' => ['Your Hotel Voucher'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [],
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

            foreach ($this->detectLang as $lang => $detect) {
                if ($this->striposAll($text, $detect) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePDF(Email $email, $text)
    {
//        $this->logger->debug($text);

        // Travel Agency
        $otaConf = $this->re("/{$this->opt($this->t('Agency Reference Number:'))}\s*([A-Z\d]{5,})\s*\n/", $text);
        $email->ota()
            ->confirmation($otaConf);

        // HOTEL

        $h = $email->add()->hotel();

        $conf = $this->re("/{$this->opt($this->t('Confirmation Number'))}[ :]+(?:\n| {3,}.*\n) {0,10}([\d\-]{5,})(?:\n| {3,}.*\n)/u", $text);
        if (empty($conf)) {
            $conf = $this->re("/{$this->opt($this->t('Confirmation Number'))}[ :]{0,5}([\d\-]{5,})(?:\n| {3,}.*\n)/u", $text);
        }
        // General
        $h->general()
            ->confirmation($conf)
            ->traveller($this->re("/\n\s*{$this->opt($this->t('Guest Information'))}\s*\n\s*.+\n\s*([[:alpha:] \-]+)\n/u", $text), true)
        ;

        // Hotel
        $h->hotel()
            ->name($this->re("/{$this->opt($this->t('Check-out:'))}.+\n(?: {20,}.+\n){1,3}\s*\n{2,} {0,15}(.+)\n/", $text))
            ->address(preg_replace('/\s+/', ' ', trim($this->re("/{$this->opt($this->t('Check-out:'))}.+\n(?: {20,}.+\n){1,3}\s*\n{2,} {0,15}.+\n((.+\n)+?)\s*(?:{$this->opt($this->t('Phone:'))}|{$this->opt($this->t('Fax'))}|\n{2})/", $text))))
            ->phone($this->re("/{$this->opt($this->t('Check-out:'))}.+\n(?:.*\n){1,15}{$this->opt($this->t('Phone:'))} *([\d\(\)\-\+ ]{5,})\n/", $text), true, true)
            ->fax($this->re("/{$this->opt($this->t('Check-out:'))}.+\n(?:.*\n){1,15}{$this->opt($this->t('Fax:'))} *([\d\(\)\-\+ ]{5,})\n/", $text), true, true)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("/{$this->opt($this->t('Check-in:'))} *(.+)/", $text)))
            ->checkOut($this->normalizeDate($this->re("/{$this->opt($this->t('Check-out:'))} *(.+)/", $text)))
            ->guests($this->re("/\s*{$this->opt($this->t('Guest Information'))}\s*\n\s*(\d+) *{$this->opt($this->t('adult'))}/u", $text))
            ->kids($this->re("/\s*{$this->opt($this->t('Guest Information'))}\s*\n\s*.*(\d+) *{$this->opt($this->t('children'))}/u", $text), true, true)
        ;

        // Rooms
        $roomDescriptions = $this->res("/{$this->opt($this->t('Room'))} \d+\n\s*(.+)/u", $text);
        foreach ($roomDescriptions as $description) {
            $room = $h->addRoom();
            $room->setDescription($description);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectLang as $lang => $detect) {
                if ($this->striposAll($text, $detect) === true) {
                    $this->lang = $lang;
                    $this->ParsePDF($email, $text);
                    continue 2;
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

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
//        $this->logger->debug('IN-' . $str);
        $in = [
            "#^(\d+\s*\w+\s*\d{4}\s*[\d\:]+)$#s", //02 ago 2021
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function striposAll($text, $needle): bool
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

}
