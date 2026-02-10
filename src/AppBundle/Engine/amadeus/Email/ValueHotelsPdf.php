<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ValueHotelsPdf extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-109332207.eml, amadeus/it-93239814.eml";
    public $detectSubjects = [
        'Amadeus Value Hotels, Confirmation of Booking',
        'Amadeus Value Hotels, Travel Voucher for Booking',
        'Amadeus Value Hotels, Cancellation of Booking',
        'Amadeus Value Hotels, Traveller cancellation notification for Booking',
    ];


    public $detectLang = [
        'en' => ['confirmed by Amadeus Value Hotels'],
        'es' => ['confirmada por Amadeus Value Hotels'],
    ];

    public $lang = 'en';
    public static $dictionary = [
        "en" => [
//            'your reservation is now' => '',
//            'cancelled' => '',
//            'Reservation Number :' => '',
//            'Confirmation Number' => '',
//            'Check-in' => '',
//            'Check-out' => '',
//            'Guests' => '',
//            'Adults' => '',
//            'Children' => '',
//            'GUEST DETAILS' => 'GUEST DETAILS',
            'ROOM DETAILS' => 'ROOM DETAILS',
//            'Room Confirmation Number' => '',
            'roomDescriptionEnd' => ['Meal','ROH'],
//            'CANCELLATION POLICY' => '',
//            'BOOKING TOTALS' => '',
//            'Selling price' => '',
//            'Buying price' => '',
//            'Agency Fee' => '',
        ],
        "es" => [
            'your reservation is now' => 'su reserva está ahora',
            'cancelled' => 'cancelada',
            'Reservation Number :' => 'Número De Reserva :',
            'Confirmation Number' => 'Número De Confirmación',
            'Check-in' => 'Llegada',
            'Check-out' => 'Salida',
            'Guests' => 'Huéspedes',
            'Adults' => 'Adultos',
//            'Children' => '',
            'GUEST DETAILS' => 'DETALLES DEL HUÉSPED',
            'ROOM DETAILS' => 'DETALLES DE LA HABITACIÓN',
            'Room Confirmation Number' => 'Número De Confirmación De La Habitación',
            'roomDescriptionEnd' => ['Régimen de estancia','ROH'],
            'CANCELLATION POLICY' => 'POLÍTICA DE CANCELACIÓN',
            'BOOKING TOTALS' => 'TOTALES DE LA RESERVA',
            'Selling price' => 'Precio de venta',
            'Buying price' => 'Precio de compra',
            'Agency Fee' => 'Comisión de agencia',
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

            foreach ($this->detectLang as $lang => $detect) {
                if ($this->striposAll($text, $detect) === true) {
                    if (isset(self::$dictionary[$lang], self::$dictionary[$lang]['ROOM DETAILS'])
                        && $this->striposAll($text, self::$dictionary[$lang]['ROOM DETAILS']) === true) {
                        return true;
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
        $otaConf = $this->re("/{$this->opt($this->t('Reservation Number :'))}\s*([A-Z\d]{5,})\s*\n/", $text);
        $email->ota()
            ->confirmation($otaConf);

        // HOTEL

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->traveller($this->re("/\n\s*{$this->opt($this->t('GUEST DETAILS'))}\s*\n\s*(.+)\n[\s\S]*?\n{$this->opt($this->t('ROOM DETAILS'))}/u", $text), true)
            ->cancellation($this->re("/{$this->opt($this->t('CANCELLATION POLICY'))}\n(.+)\n/u", $text));


        $status = $this->re("/{$this->opt($this->t('your reservation is now'))} +(\w+)\n/u", $text);
        if (!empty($status)) {
            $h->general()
                ->status($status);
        }
        if (preg_match("/{$this->opt($this->t('your reservation is now'))} +{$this->opt($this->t('cancelled'))}\n/u", $text)) {
            $h->general()
                ->cancelled();
        }

        $hotel = $this->re("/{$this->opt($this->t('Reservation Number :'))}.+\n(?:.+\n)*\s*\n{2,}\s+(.+\n(?:.*\n+){2,15}?) {0,15}\S/", $text);
        if (preg_match("/^(.+\n(?: *\S.{0,10}\n)?)\s*([\s\S]+)/", $hotel, $m)) {
            $h->hotel()
                ->name((preg_replace('/\s+/', ' ', trim($m[1]))))
                ->address(preg_replace('/\s+/', ' ', trim($m[2])));
        }

        $info = $this->re("/({$this->opt($this->t('Confirmation Number'))}\s*{$this->opt($this->t('Check-in'))}\s*.*\n.+)\n{$this->opt($this->t('GUEST DETAILS'))}/us", $text);
        $infoTable = $this->SplitCols($info);

        $h->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation Number'))}\s*([A-Z\d\-]{5,})(?:\s+|$)/su", $infoTable[0]));

        $h->booked()
            ->checkIn($this->normalizeDate(preg_replace('/\-/u', ' ', $this->re("/{$this->opt($this->t('Check-in'))}\s+(.+)/s", $infoTable[1]))))
            ->guests($this->re("/\s*{$this->opt($this->t('Guests'))}\s*(\d+)\s*{$this->opt($this->t('Adults'))}/su", $infoTable[3]));

        if ($this->striposAll($infoTable[2], $this->t('Children'))) {
            if (preg_match("/(\d+)\-(\w+)\-(\d{4})\s*(\d)\s*([\d\:]+)\s*{$this->opt($this->t('Children'))}/us", $infoTable[2], $m)) {
                $h->booked()
                    ->checkOut($this->normalizeDate($m[1] . ' ' . $m[2] . ' ' . $m[3] . ', ' . $m[5]))
                    ->kids($m[4]);
            }
        } else {
            $h->booked()
                ->checkOut($this->normalizeDate(preg_replace('/\-/u', ' ', $this->re("/{$this->opt($this->t('Check-out'))}\s+(.+)/s", $infoTable[2]))));
        }

        $roomDescription = $this->re("/{$this->opt($this->t('ROOM DETAILS'))}\n\s*{$this->opt($this->t('Room Confirmation Number'))}[\s\:]+[A-Za-z\-\d]+\n(.+)\n{$this->opt($this->t('roomDescriptionEnd'))}/su", $text);
        $room = $h->addRoom();
        $room->setDescription(str_replace("\n", " ", $roomDescription));
        $room->setConfirmation($this->re("/{$this->opt($this->t('ROOM DETAILS'))}\n\s*{$this->opt($this->t('Room Confirmation Number'))}[\s\:]+([A-Za-z\-\d]+)\n/u", $text));

        $priceText = $this->re("/{$this->opt($this->t('BOOKING TOTALS'))}\n{$this->opt($this->t('Selling price'))}\s*{$this->opt($this->t('Buying price'))}\s*{$this->opt($this->t('Agency Fee'))}\n(.+)/", $text);

        if (preg_match("/^(?<total>[\d\.\,]+)\s*(?<currency>[A-Z]{3})\D+(?<cost>[\d\.\,]+)\D+(?<tax>[\d\.\,]+)\D+$/s", $priceText, $m)) {
            $h->price()
                ->cost($m['cost'])
                ->tax(str_replace(',', '.', $m['tax']))
                ->total($m['total'])
                ->currency($m['currency']);
        }


        $this->detectDeadLine($h);
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            foreach ($this->detectLang as $lang => $detect) {
                if ($this->striposAll($text, $detect) === true) {
                    if (isset(self::$dictionary[$lang], self::$dictionary[$lang]['ROOM DETAILS'])
                        && $this->striposAll($text, self::$dictionary[$lang]['ROOM DETAILS']) === true
                    ) {
                        $this->lang = $lang;
                        $this->ParsePDF($email, $text);
                        continue 2;
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
        // Cancel before viernes, mayo 21, 2021 15:59 GMT to avoid a charge of 112.18 EUR
        // Cancelar antes sábado, septiembre 4, 2021 18:30 GMT Para evitar un cargo de 93.01 EU
        if (
            preg_match('/^\s*Cancel before \w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+)\s*GMT to avoid a charge/u', $cancellationText, $m)
            || preg_match('/^\s*Cancelar antes \w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\s*([\d\:]+)\s*GMT Para evitar un cargo/u', $cancellationText, $m)
        ) {
            $h->booked()->deadline($this->normalizeDate($m[2] . ' ' . $m[1] . ' ' . $m[3] . ', ' . $m[4]));
        }

        if (preg_match("/(?:Non-Refundable rate|Tarifa no reembolsable\.)/ui", $cancellationText)) {
            $h->booked()->nonRefundable();
        }
    }

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
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

    private function TableHeadPos($row)
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

    private function normalizeDate($str)
    {
        $this->logger->debug('IN-' . $str);
        $in = [
            "#^(\d+\s*\w+\s*\d{4},?\s*[\d\:]+)$#s", //02 ago 2021
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], 'es')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
