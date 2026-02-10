<?php

namespace AwardWallet\Engine\bookonline\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "bookonline/it-692570300.eml, bookonline/it-693021034.eml, bookonline/it-695053763.eml, bookonline/it-695087104.eml, bookonline/it-698276177.eml, bookonline/it-698510143.eml, bookonline/it-701270623.eml";

    public $emailSubject;
    public $lang;
    public static $dictionary = [
        'en' => [
            'Dear '                 => 'Dear ',
            'Your booking summary:' => 'Your booking summary:',
            'Arrival:'              => 'Arrival:',
            'Departure:'            => 'Departure:',
            'Room #'                => 'Room #',
            //            'Adults:' => 'Adults:',
            //            'Children:' => 'Children:',
            //            'Price:' => 'Price:',
            'Cancellation policy:' => ['Cancellation policy:', 'Special Cancellation - ', 'Cancellation Policy:'],
            //            'Approximate Arrival Time' => 'Approximate Arrival Time',
            'Confirmation Number' => ['Confirmation Number(click on the confirmation number to modify or cancel)', 'Confirmation Number'],
            //            'Date' => 'Date',
            //            'Room' => 'Room',
            //            'Total' => 'Total',
        ],
        'es' => [
            'Dear '                    => 'Estimado ',
            'Your booking summary:'    => 'Resumen de su reserva',
            'Arrival:'                 => 'Llegada:',
            'Departure:'               => 'Salida:',
            'Room #'                   => 'Habitación #',
            'Adults:'                  => 'Adultos:',
            'Children:'                => 'Niños:', // to check
            'Price:'                   => 'Precio:',
            'Cancellation policy:'     => ['Política de cancelación:'],
            'Approximate Arrival Time' => 'Hora aproximada de llegada',
            'Confirmation Number'      => 'Número de confirmación(Pulse en el número de confirmación para modificar o cancelar su reserva)',
            'Date'                     => 'Fecha',
            'Room'                     => 'Habitación',
            'Total'                    => 'cantidad',
        ],
        'pt' => [
            'Dear '                    => ['Prezado Sr (a) ', 'Prezado Sr(a) ', 'Prezado Sr ', 'Prezado '],
            'Your booking summary:'    => 'Resumo da sua reserva',
            'Arrival:'                 => 'Chegada:',
            'Departure:'               => 'Saída:',
            'Room #'                   => 'Quarto #',
            'Adults:'                  => 'Adultos:',
            'Children:'                => 'Crianças:', // to check
            'Price:'                   => 'Preço:',
            'Cancellation policy:'     => ['Politica de Cancelamento:', 'Cancelamento da Reserva Gratuito'],
            'Approximate Arrival Time' => 'Horário aproximado de chegada',
            'Confirmation Number'      => 'Número de confirmação',
            'Date'                     => 'Encontro',
            'Room'                     => 'Quarto',
            'Total'                    => 'total',
        ],
    ];

    private $detectFrom = "@book-onlinenow.net";
    private $detectSubject = [
        // en, es, pt
        'Your Reservation at ',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]book-onlinenow\.net$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.book-onlinenow.net'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['@book-onlinenow.net'])}]")->length === 0
            && stripos($parser->getCleanFrom(), $this->detectFrom) === false
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your booking summary:']) && !empty($dict['Arrival:'])
                && !empty($dict['Room #'])
                && $this->http->XPath->query("//text()[{$this->eq($dict['Your booking summary:'])}]"
                    . "/following::text()[normalize-space()][position()< 6][{$this->eq($dict['Arrival:'])}]"
                    . "/following::text()[normalize-space()][position()< 6][{$this->starts($dict['Room #'])}]"
                )->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->emailSubject = $parser->getSubject();

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

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict["Your booking summary:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Your booking summary:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $confs = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Your booking summary:'))}]/following::text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()][1]",
            null, "/^\s*(RES\d+[\dA-Z]+)\s*$/"));

        foreach ($confs as $conf) {
            $h->general()
                ->confirmation($conf);
        }

        $travellerRe = "(?:[[:alpha:]]{1,3}[\s\.]?\s*)?[[:alpha:]][[:alpha:]\- ]+?";
        $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking summary:'))}]/preceding::text()[{$this->eq(array_map('trim', (array) $this->t('Dear ')))}][1]/following::text()[normalize-space()][1]",
                null, true, "/^\s*({$travellerRe})[\.,]?\s*$/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking summary:'))}]/preceding::text()[{$this->starts($this->t('Dear '))}]",
                null, true, "/^\s*{$this->opt($this->t('Dear '))}\s*({$travellerRe})[\.,]?\s*$/");
        }
        $traveller = preg_replace("/^\s*(Mr|Ms|Mrs|Sr|Sr ?\(a\))[\.\s]\s*/", '', $traveller);
        $h->general()
            ->traveller($traveller, false);

        $cancellation = [];
        $cancelXpath = "//hr/following::text()[normalize-space()][1][{$this->contains($this->t('Cancellation policy:'))}]/ancestor::*[position() < 5][following-sibling::*[normalize-space()]][1]/following-sibling::*";
        $cancelNodes = $this->http->XPath->query($cancelXpath);

        foreach ($cancelNodes as $i => $cRoot) {
            if ($this->http->XPath->query("./ancestor-or-self::hr | ./descendant-or-self::hr", $cRoot)->length > 0) {
                break;
            } elseif ($i > 0 && $this->http->XPath->query("self::node()[not(normalize-space())]//br", $cRoot)->length > 0) {
                break;
            } else {
                $cancellation[] = $this->http->FindSingleNode(".", $cRoot);
            }
        }

        if (!empty($cancellation) && count($cancellation) < 20) {
            $h->general()
                ->cancellation(implode(' ', $cancellation));
        }

        // Hotel
        $h->hotel()
            ->name($this->re("/Your Reservation at (.+)/", $this->emailSubject))
            ->noAddress()
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Arrival:'))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure:'))}]/following::text()[normalize-space()][1]")))
            ->guests(array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Room #'))}]/following::text()[normalize-space()][1][{$this->contains($this->t('Adults:'))}][{$this->contains($this->t('Price:'))}]",
                null, "/{$this->opt($this->t('Adults:'))}\s*(\d+)\b/")))
            ->kids(array_sum($this->http->FindNodes("//text()[{$this->starts($this->t('Room #'))}]/following::text()[normalize-space()][1][{$this->contains($this->t('Children:'))}][{$this->contains($this->t('Price:'))}]",
                null, "/{$this->opt($this->t('Children:'))}\s*(\d+)\b/")))
        ;
        $time = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Approximate Arrival Time'))}])/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{1,2}:\d{2})\s*$/");

        if (!empty($time) && !empty($h->getCheckInDate())) {
            $h->booked()
                ->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        // Rooms
        $types = $this->http->FindNodes("//text()[{$this->starts($this->t('Room #'))}][contains(., '[')]",
            null, "/^\s*{$this->opt($this->t('Room #'))}\s*\d+\s*\[\s*(.+?)\s*\]/");
        $rateTableXpath = "//tr[*[1][{$this->eq($this->t('Date'))}] and *[2][{$this->eq($this->t('Room'))}]]";
        $rateTable = $this->http->XPath->query($rateTableXpath);

        if (count($types) !== $rateTable->length) {
            $rateTable = null;
        }

        $nights = 0;

        if (!empty($h->getCheckInDate()) && !empty($h->getCheckOutDate())) {
            $nights = date_diff(
                date_create('@' . strtotime('00:00', $h->getCheckOutDate())),
                date_create('@' . strtotime('00:00', $h->getCheckInDate()))
            )->format('%a');
        }

        $tax = 0.0;

        foreach ($types as $i => $type) {
            $room = $h->addRoom();

            $room->setType($type);

            if ($rateTable->item($i)) {
                $rates = [];
                $rateRows = $this->http->XPath->query("following-sibling::tr[normalize-space()]", $rateTable->item($i));

                foreach ($rateRows as $rowRoot) {
                    if ($this->http->FindSingleNode("./*[1]", $rowRoot, true, "/^\s*\w+[\.]?\s+\w+[\.]?\s*$/u")) {
                        $rates[] = $this->http->FindSingleNode("./*[2]", $rowRoot);
                    } elseif ($this->http->FindSingleNode("./*[1][{$this->eq($this->t('Total'))}]", $rowRoot)
                        && $t = $this->http->FindSingleNode("./*[3]", $rowRoot, true, "/^\s*\D*(\d[\d., ]*?)\D*\s*$/")
                    ) {
                        $tax += PriceHelper::parse($t);
                    } else {
                        break;
                    }
                }

                if (count($rates) == $nights) {
                    $room->setRates($rates);
                }
            }
        }

        // Price
        if (!empty($tax)) {
            $h->price()
                ->tax($tax);
        }
        $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your booking summary:'))}]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $total, $m)
        ) {
            $m['currency'] = $this->currency($m['currency']);
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency']);
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        $in = [
            // vi. 26 jul. 2024
            '/^\s*[-[:alpha:]]+[\.\s]\s*(\d+)\s*([[:alpha:]]+)[\.]?\s+(\d{4})\s*$/iu',
        ];
        $out = [
            '$1 $2 $3',
        ];

        $date = preg_replace($in, $out, $date);

//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r( $date, true));

        return strtotime($date);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
           preg_match("/If you cancel this reservation up to \d+ days \((?:before|until): (?<date>.+?)\) before date of arrival no fees or penalties will be charged/i", $cancellationText, $m)
       ) {
            $h->booked()
               ->deadline($this->normalizeDate($m['date']));
        }

        if (
           preg_match("/If you cancel, modify, or in case of no-show, 100% of the total price of the reservation will be charged\./i", $cancellationText, $m)
           || preg_match("/This rate is available only on a non-refundable basis\./i", $cancellationText, $m)
       ) {
            $h->booked()
               ->nonRefundable();
        }
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return trim($s);
        }
        $sym = [
            'R$' => 'BRL',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
