<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class DateChangeApproved extends \TAccountChecker
{
    public $mailFiles = "booking/it-200693914.eml";

    private $detectFrom = 'customer.service@booking.com';
    private $detectSubject = [
        // en
        'Date change approved!',
        // de
        'Datumsänderung bestätigt!',
        // pt
        'Alteração de datas aprovada!',
        // it
        'Modifica delle date approvata!'
    ];

    private $detectBody = [
        'en' => ['Date change approved!'],
        'de' => ['Datumsänderung bestätigt!'],
        'pt' => ['Alteração de datas aprovada!'],
        'it' => ['Modifica delle date approvata!'],
    ];
    private $lang = 'en';

    private static $dictionary = [
        "en" => [
//            'Dear ' => '',
            'hotelNameRe' => 'You recently sent a request to (?<hotel>.+)to change the dates of your upcoming stay',
//            'Check-in' => '',
//            'Check-out' => '',
//            'Your reservation' => '',
//            'rooms' => '',
//            'Total Price' => '',
        ],
        "de" => [
            'Dear ' => 'Hallo ',
            'hotelNameRe' => 'Sie haben der Unterkunft (?<hotel>.+) kürzlich eine Anfrage zur Änderung der Reisedaten für Ihren bevorstehenden Aufenthalt gesendet',
            'Check-in' => 'Anreise',
            'Check-out' => 'Abreise',
            'Your reservation' => 'Ihre Buchung',
            'rooms' => 'Zimmer',
            'Total Price' => 'Gesamtpreis',
        ],
        "pt" => [
            'Dear ' => 'Olá,',
            'hotelNameRe' => 'Você pediu recentemente que (?<hotel>.+) alterasse as datas da sua futura hospedagem',
            'Check-in' => 'Check-in',
            'Check-out' => 'Check-out',
            'Your reservation' => 'Sua reserva',
            'rooms' => 'quarto',
            'Total Price' => 'Preço total',
        ],
        "it" => [
            'Dear ' => 'Gentile ',
            'hotelNameRe' => 'Di recente hai chiesto a (?<hotel>.+) di cambiare le date del soggiorno',
            'Check-in' => 'Check-in',
            'Check-out' => 'Check-out',
            'Your reservation' => 'La tua prenotazione',
            'rooms' => 'camera',
            'Total Price' => 'Importo totale',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains('.booking.com', '@href')}]")->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null, true,
        "/^\s*" . $this->opt($this->t("Dear ")) . " ?(\w+(?: \w+)*)[,\.!]\s*$/u"))
        ;

        // Hotel
        $reservation = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]/following::text()[normalize-space()][1]");
        if (preg_match("/" . $this->t("hotelNameRe") . "/u", $reservation, $m)
            || !empty($m['hotel'])
        ) {
            $h->hotel()
                ->name($m['hotel'])
                ->noAddress()
            ;
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in")) . "]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-out")) . "]/following::text()[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your reservation")) . "]/following::text()[normalize-space()][1]",
                null, true, "/,\s*(\d+)\s*" . $this->opt($this->t("rooms")) . "/"))
        ;


        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total Price")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function detectBody()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            //Tuesday, 1 September 2020; segunda-feira, 24 de agosto de 2020; mercoledì 26 agosto 2020; Freitag, 30. Juli 2021
            "/^\s*[^\d\s]+,?\s*(\d+)[.]?\s+(?:de\s+)?([^\d\s]+)\s+(?:de\s+)?(\d{4})\s*(?:г\.\s*)?$/",
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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

    private function getField($text, $regexp = null)
    {
        $result = $this->http->FindSingleNode("//text()[" . $this->eq($text) . "]/ancestor::*[self::td or self::th][1]/following-sibling::*[1]", null, true, $regexp);

        if (empty($result)) {
            $result = $this->http->FindSingleNode("//text()[" . $this->eq($text) . "]/following::text()[normalize-space()][1]", null, true, $regexp);
        }

        return $result;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        if (in_array($this->lang, ['pt', 'es'])) {
            $price = str_replace('.', '', $price);
            $price = str_replace(',', '.', $price);
        }
        $price = str_replace([',', ' '], '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            'R$' => 'BRL',
            'US$' => 'USD',
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            'Rp' => 'IDR',
            'zł'  => 'PLN',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
