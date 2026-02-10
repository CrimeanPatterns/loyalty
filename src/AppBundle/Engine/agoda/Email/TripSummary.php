<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class TripSummary extends \TAccountChecker
{
    public $mailFiles = "agoda/it-135448812.eml";
    public $subjects = [
        // en
        'Confirmation for Agoda Booking ID',
        // de
        'Bestätigung für Ihre Agoda-Buchung mit der Nummer',
        // pt
        'Confirmação para o ID de reserva Agoda',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            "Agoda Booking summary for" => "Agoda Booking summary for",
            "Booking ID:"               => "Booking ID:",
            //            "Total" => "",
            //            "Trip summary" => "",
            //            "Cancellation and Change Policy" => "",
            //            "Dear" => "",
            //            "Check in:" => "",
            //            "Check out:" => "",
            //            "Occupancy:" => "",
            //            "Adult" => "",
        ],
        "de" => [
            "Agoda Booking summary for"      => "Agoda-Buchungsübersicht für",
            "Booking ID:"                    => "Buchungsnummer:",
            "Total"                          => "Total",
            "Trip summary"                   => "Übersicht über Ihre Reise",
            "Cancellation and Change Policy" => "Stornierungs- und Änderungsbedingungen",
            "Dear"                           => "Hallo,",
            "Check in:"                      => "Check-in:",
            "Check out:"                     => "Check-out:",
            "Occupancy:"                     => "Belegung:",
            "Adult"                          => "Erwachsene",
        ],
        "fr" => [
            "Agoda Booking summary for"      => "Récapitulatif de la réservation Agoda pour",
            "Booking ID:"                    => "Numéro de réservation :",
            "Total"                          => "Total",
            "Trip summary"                   => "Récapitulatif de voyage",
            "Cancellation and Change Policy" => "Conditions d'annulation et de modification",
            "Dear"                           => "Cher(e)",
            "Check in:"                      => "Arrivée :",
            "Check out:"                     => "Départ :",
            "Occupancy:"                     => "Occupation :",
            "Adult"                          => "adult",
        ],
        "pt" => [
            "Agoda Booking summary for"      => "Agoda Booking summary for",
            "Booking ID:"                    => "ID da reserva:",
            "Total"                          => "Total:",
            "Trip summary"                   => "Trip summary",
            "Cancellation and Change Policy" => "Conditions d'annulation et de modification",
            "Dear"                           => "Olá,",
            "Check in:"                      => "Entrada:",
            "Check out:"                     => "Saída:",
            "Occupancy:"                     => "Ocupação:",
            "Adult"                          => "Adult",
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@agoda.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".agoda.")]')->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Agoda Booking summary for']) && !empty($dict['Booking ID:'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Agoda Booking summary for'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Booking ID:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]agoda.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Agoda Booking summary for']) && !empty($dict['Booking ID:'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Agoda Booking summary for'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($dict['Booking ID:'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        // Total Price
        $price = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Total"))}]/following::text()[normalize-space()][1]");

        if (preg_match("/([A-Z]{3})\s*([\d\.\,]+)/", $price, $m)) {
            $email->price()
                ->currency($m[1])
                ->total(PriceHelper::parse($m[2], $m[1]));
        }

        // HOTELS
        $xpath = "//text()[{$this->eq($this->t("Agoda Booking summary for"))}]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $hotelName = trim($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('Agoda Booking summary for'))}\s*(.+)/"), ': ');

            if (strpos($hotelName, "'") !== false || strpos($hotelName, '"') !== false) {
                $parts = preg_split("/(['\"])/", $hotelName, -1, PREG_SPLIT_DELIM_CAPTURE);

                foreach ($parts as $i => $p) {
                    $parts[$i] = (strpos($p, "'") !== false) ? '"' . $p . '"' : "'" . $p . "'";
                }
                $hotelNameXpath = "concat(" . implode(', ', $parts) . ")";
            } elseif (empty($hotelName)) {
                $hotelNameXpath = "'false()'";
            } else {
                $hotelNameXpath = "'{$hotelName}'";
            }
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Trip summary"))}]/following::text()[normalize-space()={$hotelNameXpath}]/following::text()[{$this->eq($this->t("Cancellation and Change Policy"))}][1]/following::text()[normalize-space()][1]");

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }

            $h->general()
                ->confirmation($this->http->FindSingleNode("./following::text()[{$this->starts($this->t("Booking ID:"))}][1]/ancestor::tr[1]", $root, true, "/{$this->opt($this->t('Booking ID:'))}\s*(\d+)/"))
                ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t("Dear"))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+(.+?)\.?$/"));

            $h->hotel()
                ->name($hotelName)
                ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip summary'))}]/following::text()[normalize-space()={$hotelNameXpath}][1]/following::img[contains(@alt, 'star') or contains(@altx, 'star')][1]/following::text()[normalize-space()][1]"));

            $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip summary'))}]/following::text()[normalize-space()={$hotelNameXpath}][1]/following::text()[{$this->eq($this->t('Check in:'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check in:'))}\s*(.+)/");
            $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip summary'))}]/following::text()[normalize-space()={$hotelNameXpath}][1]/following::text()[{$this->eq($this->t('Check out:'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Check out:'))}\s*(.+)/");

            if (!empty($checkIn) && !empty($checkOut)) {
                $h->booked()
                    ->checkIn($this->normalizeDate($checkIn))
                    ->checkOut($this->normalizeDate($checkOut))
                    ->guests($this->http->FindSingleNode("//text()[{$this->eq($this->t('Trip summary'))}]/following::text()[normalize-space()={$hotelNameXpath}][1]/following::text()[{$this->eq($this->t('Occupancy:'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Occupancy:'))}\s*(\d+)\s*{$this->opt($this->t('Adult'))}/"));
            }

            $roomType = $this->http->FindSingleNode("./following::b[1]", $root);

            if (!empty($roomType)) {
                $room = $h->addRoom();
                $room->setType($roomType);
            }

            if (!empty($hotelName) && !empty($h->getAddress()) && empty($checkIn) && empty($checkOut)) {
                $email->removeItinerary($h);
            }

            $this->detectDeadLine($h);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/\bThis booking is non-Refundable\b/", $cancellationText)
        || preg_match("/This booking is Non-Refundable and cannot be amended or modified/", $cancellationText)) {
            $h->booked()
                ->nonRefundable();
        }

        if (preg_match("/Any cancellation received within (\d+) day\/s prior to arrival/u", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['1'] . ' days');
        }
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
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

    private function normalizeDate($date)
    {
        //$this->logger->debug('$date = ' . print_r($date, true));

        $in = [
            // January 31, 2022 (after 2:00 PM)
            // Fevereiro, 10, 2023 (antes das 12:00)
            "/^\s*(\w+),?\s*(\d+)\,\s*(\d{4})\s*\(\D+\s*([\d\:]+(?:\s*[AP]M)?)\)$/iu",
            // 25. Juli 2022 (nach 15:00 Uhr)
            // 10 Juillet 2022 Avant 10:00
            "/^\s*(\d{1,2})[.]?\s*([[:alpha:]]+)\s*(\d{4})\s*\(?[^\d\(\):]*\s*(\b\d{1,2}:\d{2}(?:\s*[AP]M)?)(?:\s*Uhr)?\)?\s*$/iu",
        ];
        $out = [
            "$2 $1 $3, $4",
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = ' . print_r($date, true));

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
