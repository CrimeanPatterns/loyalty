<?php

namespace AwardWallet\Engine\directbook\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Booking2 extends \TAccountChecker
{
    public $mailFiles = "";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Reservation Cancellation' => ['Reservation Cancellation', 'We have confirmed your cancellation'],
            // 'Address' => '',
            // 'Show on Map' => '',
            // 'Phone' => '',
            'Location Instructions' => 'Location Instructions',
            'Reference Number'      => 'Reference Number',
            // 'Cancel your Booking' => '',
            'Check In Date'  => 'Check In Date',
            'Check Out Date' => 'Check Out Date',
            // 'Booked by' => '',
            'Guest ETA' => 'Guest ETA',
            // 'Guest' => '',
            // 'Occupancy' => '',
            'Cost Breakdown' => 'Cost Breakdown',
            // 'Rate' => '',
            // 'Grand Total' => '',
            // 'Prices are in' => '',
            // 'Cancellation Policy' => '',
        ],
        'es' => [
            // 'Reservation Cancellation' => [''],
            'Address'               => 'Dirección',
            'Show on Map'           => 'Mostrar en el mapa',
            'Phone'                 => 'Teléfono',
            'Location Instructions' => 'Cómo llegar aquí',
            'Reference Number'      => 'Número de referencia',
            'Cancel your Booking'   => 'Cancelar su reserva',
            'Check In Date'         => 'Fecha de llegada',
            'Check Out Date'        => 'Fecha de salida',
            'Booked by'             => 'Reservada por',
            'Guest ETA'             => 'Hora estimada de llegada del huésped',
            'Guest'                 => 'Huésped',
            'Occupancy'             => 'Ocupación',
            'Cost Breakdown'        => 'Desglose de coste',
            'Rate'                  => 'Tarifa',
            'Grand Total'           => 'Total general',
            'Prices are in'         => 'Los precios están en',
            'Cancellation Policy'   => 'Política de Cancelación',
        ],
    ];

    private $detectFrom = ["donotreply@book-directonline.com", 'donotreply@app.thebookingbutton.com',
        'donotreply@reservation.easybooking-asia.com', 'donotreply@bookings.skytouchhos.com', ];

    private $detectSubject = [
        // en
        'Booking cancellation at',
        'Online Booking For',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.](?:book-directonline|thebookingbutton|easybooking-asia|direct-book|skytouchhos)\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers["from"], $this->detectFrom) === false) {
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
        foreach (self::$dictionary as $dict) {
            if (
                !empty($dict['Check In Date']) && !empty($dict['Check Out Date'])
                && !empty($dict['Location Instructions']) && !empty($dict['Guest ETA'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Location Instructions'])}]/following::text()[{$this->eq($dict['Check In Date'])}]/following::text()[{$this->eq($dict['Guest ETA'])}]")->length > 0
            ) {
                return true;
            }

            if (
                !empty($dict['Check In Date']) && !empty($dict['Check Out Date'])
                && !empty($dict['Location Instructions']) && !empty($dict['Cost Breakdown'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Location Instructions'])}]/following::text()[{$this->eq($dict['Check In Date'])}]/following::text()[{$this->eq($dict['Cost Breakdown'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Location Instructions"]) && !empty($dict["Reference Number"])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Location Instructions'])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->eq($dict['Reference Number'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->parseEmailHtml($email);

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

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->nextTd($this->t('Reference Number'), "/^\s*([A-Z\d]{5,})\s*(?:{$this->opt($this->t('Cancel your Booking'))}|$)/"))
        ;
        $cancellation = $this->nextTd($this->t('Cancellation Policy'));

        if (!empty($cancellation) && mb_strlen($cancellation) < 2000) {
            $h->general()
                ->cancellation($cancellation, true, true);
        }
        $travellers = $this->nextTds($this->t('Guest'));

        if (!empty($travellers)) {
            $h->general()
                ->travellers($travellers, true);
        } else {
            $h->general()
                ->traveller($this->nextTd($this->t('Booked by'), "/^\s*(.+?) - \S+@/"));
        }

        if (!empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Reservation Cancellation'))}])[1]"))) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]"))
            ->address($this->nextTd($this->t('Address'), "/^\s*(.+?)\s*(?:{$this->opt($this->t('Show on Map'))}|$)/"))
            ->phone($this->nextTd($this->t('Phone'), "/^\s*([\d \-\+\(\)\.]+?)\s*(?:\(\D*\).*)?$/"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->nextTd($this->t('Check In Date'))))
            ->checkOut($this->normalizeDate($this->nextTd($this->t('Check Out Date'), "/^\s*(.+?)\([^()]+\)$/")))
        ;

        $time = $this->nextTd($this->t('Guest ETA'));

        if (!empty($h->getCheckInDate()) && !empty($time)) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if ($h->getCancelled()) {
            return true;
        }

        $h->booked()
            ->guests(array_sum($this->nextTds($this->t('Occupancy'), "/^\s*(\d+) [[:alpha:]]+/u")))
            ->kids(array_sum($this->nextTds($this->t('Occupancy'), "/^\s*\d+ [[:alpha:]]+[^,]*,\s*(\d+) [[:alpha:]]+/u")))
        ;

        $nights = 0;

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');
        }

        $roomsXpath = "//text()[{$this->eq($this->t('Guest'))}]";
        $roomsNodes = $this->http->XPath->query($roomsXpath);

        foreach ($roomsNodes as $rRoot) {
            $room = $h->addRoom();

            $room
                ->setType($this->http->FindSingleNode("preceding::text()[normalize-space()][1]/ancestor::tr[1]",
                    $rRoot, true, "/^\s*(.+?)\s*\\//"))
                ->setRateType($this->http->FindSingleNode("preceding::text()[normalize-space()][1]/ancestor::tr[1]",
                    $rRoot, true, "/^\s*.+?\s*\\/\s*(.+?)\s*$/"));

            $rates = array_filter($this->http->FindNodes("following::*[{$this->eq($this->t('Cost Breakdown'))}][1]/following-sibling::*[normalize-space()][1]//tr[count(*[normalize-space()]) = 2]/*[normalize-space()][2][not({$this->eq($this->t('Rate'))})]", $rRoot));

            if ($nights == count($rates)) {
                $room->setRates($rates);
            }
        }
        // Price
        $currency = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Prices are in'))}]",
            null, true, "/{$this->opt($this->t('Prices are in'))} ([A-Z]{3})\.?\s*$/");
        $h->price()
            ->total($this->amount($this->nextTd($this->t('Grand Total'), "/^\s*\D{0,5}\s*(\d[\d,. ]*)\s*\D{0,5}\s*/u"), $currency))
            ->currency($currency);

        return true;
    }

    private function nextTd($field, $regexp = null)
    {
        return $this->http->FindSingleNode("//text()[{$this->eq($field)}]/ancestor::*[{$this->eq($field)}]/following-sibling::*[normalize-space()][1]",
            null, true, $regexp);
    }

    private function nextTds($field, $regexp = null)
    {
        return $this->http->FindNodes("//text()[{$this->eq($field)}]/ancestor::*[{$this->eq($field)}]/following-sibling::*[normalize-space()][1]",
            null, $regexp);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
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
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
        ];
        $out = [
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
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

    private function amount($value, $currency)
    {
        $value = PriceHelper::parse($value, $currency);

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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
}
