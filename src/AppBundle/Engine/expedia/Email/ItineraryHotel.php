<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ItineraryHotel extends \TAccountChecker
{
    public $mailFiles = "expedia/it-10025514.eml, expedia/it-18734758.eml, expedia/it-54885423.eml";

    public static $dictionary = [
        "es" => [
            //            "Check-in time ends at" => "",// need to translate
            "adultos" => ["adultos", "adulto"],
            //            "child"   => "", // need to translate
        ],
        "en" => [
            // Hotels
            "No. de itinerario"                       => "Itinerary #",
            "Check-in"                                => "Check-in",
            "Hora de entrada"                         => "Check-in time starts at",
            "Check-in time ends at"                   => ["Check-in time ends at", 'Minimum check-in age is 18'],
            "Reservado para"                          => "Reserved for",
            "adultos"                                 => ["adults", "adult"],
            "child"                                   => ["child", "children"],
            "Habitación"                              => "Room",
            "Normas para las cancelaciones y cambios" => "Cancel/Change Rules",
            "Precio neto del viaje"                   => "Trip Net Price",
            "Impuestos y cargos"                      => "Taxes and Fees",
            "Total:"                                  => "Total:",
            "Tu reservación de está confirmada"       => "Your Reservation is Confirmed",
            "confirmada"                              => "Confirmed",
        ],
        "pt" => [
            // Hotels
            "No. de itinerario" => "Nº do itinerário",
            //            "Check-in" => "Check-in",
            "Hora de entrada" => "Horário inicial do check-in",
            //            "Check-in time ends at" => "",
            "Reservado para"                          => "Reservado para",
            "adultos"                                 => ["adultos", "adult", "adulto"],
            "child"                                   => "crianças",
            "Habitación"                              => "Quarto",
            "Normas para las cancelaciones y cambios" => "Regras de cancelamento/alteração",
            "Precio neto del viaje"                   => "Preço líquido da viagem",
            "Impuestos y cargos"                      => "Impostos e taxas",
            "Total:"                                  => "Total:",
            "Tu reservación de está confirmada"       => "Sua reserva foi confirmada!",
            "confirmada"                              => "confirmada",
        ],
        "nl" => [
            // Hotels
            "No. de itinerario" => "Reisplannummer",
            //            "Check-in" => "Check-in",
            "Hora de entrada"                         => "Inchecken vanaf",
            "Check-in time ends at"                   => "Inchecken tot",
            "Reservado para"                          => "Gereserveerd voor:",
            "adultos"                                 => "volwassenen",
            "child"                                   => "kinderen",
            "Habitación"                              => "Kamer",
            "Normas para las cancelaciones y cambios" => "Annulerings/wijzigingsregels:",
            "Precio neto del viaje"                   => "Nettoprijs reis:",
            "Impuestos y cargos"                      => "Belastingen en toeslagen:",
            "Total:"                                  => "Totaal:",
            //"Tu reservación de está confirmada" => "Sua reserva foi confirmada!",
            //"confirmada" => "confirmada",
        ],
    ];

    public $lang;
    private $reFrom = "expediamail.com";
    private $reSubject = [
        "es" => "Itinerario #",
        "en" => "Itinerary #",
        "nl" => "Reisplan #",
    ];
    private $reBody = 'expedia';
    private $reBody2 = [
        "es" => "No. de itinerario",
        "en" => "Itinerary #",
        'pt' => 'Nº do itinerário',
        'nl' => "Reisplannummer",
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHotel($email);

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

    private function parseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("No. de itinerario")) . "])[1]", null, true, "#" . preg_quote($this->t("No. de itinerario"), "#") . "\s*(\d+)#"))
            ->cancellation($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Normas para las cancelaciones y cambios")) . "]/ancestor::tr[1]", null, true, "#" . $this->preg_implode($this->t("Normas para las cancelaciones y cambios")) . "[\s:]*(.+)#"), true, true)
            ->travellers(array_filter(array_unique($this->http->FindNodes("//text()[" . $this->contains($this->t("Reservado para")) . "]/ancestor::td[1]/following-sibling::td[1]"))));

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Tu reservación de está confirmada")) . "])[1]"))) {
            $h->general()
                ->status($this->t("confirmada"));
        }

        //HotelName
        $hotelName = $this->http->FindSingleNode("//img[contains(@src,'ico_star')]/ancestor::td[1]/preceding-sibling::td[1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Check-in"))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/ancestor::table[1]/preceding::text()[normalize-space(.)!=''][1]");
        }

        $h->hotel()
            ->name($hotelName);

        $address = $this->http->FindSingleNode("//img[contains(@src,'ico_place')]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Check-in"))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/ancestor::table[1]/following::text()[normalize-space(.)!=''][1]");
        }

        $h->hotel()
            ->address($address);

        $phone = $this->http->FindSingleNode("//img[contains(@src,'ico_phone')]/ancestor::td[1]/following-sibling::td[1]");

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Check-in"))}]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]/ancestor::table[1]/following::text()[normalize-space(.)!=''][2]",
                null, false, "#^[\d\-\+\(\) ]+$#");
        }

        if (!empty($phone) && strlen($phone) > 3) {
            $h->hotel()
            ->phone($phone);
        }

        $checkInDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check-in")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[2]")));
        $time = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hora de entrada'))}]", null, false,
            "#{$this->preg_implode($this->t('Hora de entrada'))}[ :]+(.+?)({$this->preg_implode($this->t('Check-in time ends at'))}|Minimum check-in|$)#");

        $this->logger->error($time);

        if (!empty($time)) {
            $time = str_replace('midnight', '0:00', $time);
            $time = preg_replace("/^(\d+)(h)$/", "$1:00", $time);
            $time = str_replace('h', ':', $time);
            $time = str_replace('uur', '', $time);
            $checkInDate = strtotime($time, $checkInDate);
        }
        $checkOutDate = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Check-in")) . "]/ancestor::tr[1]/preceding-sibling::tr[1]/td[3]")));
        $h->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        $guests = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("adultos")) . "][1]", null, "#^(\d+)\s*(?:" . $this->preg_implode($this->t("adultos")) . ")\s*(?:,|$)#"));
        $this->logger->warning(var_export($guests, true));

        if (!empty($guests)) {
            $h->booked()
                ->guests(array_sum(array_map(function ($c) { return (int) $c; }, $guests)));
        }

        $kids = array_filter($this->http->FindNodes("//text()[" . $this->contains($this->t("child")) . "][1]", null, "#(?:^|,)\s*(\d+)\s*(?:" . $this->preg_implode($this->t("child")) . ")$#"));

        if (!empty($kids)) {
            $h->booked()
                ->kids(array_sum(array_map(function ($c) { return (int) $c; }, $kids)));
        }

        $h->booked()
            ->rooms(count(array_filter($this->http->FindNodes("//text()[" . $this->starts($this->t("Habitación")) . "][1]", null, "#^" . $this->t("Habitación") . "\s*(\d+):$#"))));

        $roomType = implode(",", array_unique($this->http->FindNodes("//text()[" . $this->starts($this->t("Habitación")) . " and contains(.,':')]/ancestor::*[self::p or self::div][1]/following-sibling::*[self::p or self::div][1]")));

        if (!empty($roomType)) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $currency = $this->currency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total:")) . "]/following::text()[normalize-space()][1]"));

        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total:")) . "]/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)/");

        if (!empty($currency) && !empty($total)) {
            $h->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);
        }

        $cost = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Precio neto del viaje")) . "]/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)/");

        if (!empty($currency) && !empty($cost)) {
            $h->price()
                ->cost(PriceHelper::parse($cost, $currency));
        }

        $taxes = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Impuestos y cargos")) . "]/following::text()[normalize-space()][1]", null, true, "/^([\d\.\,]+)/");

        if (!empty($currency) && !empty($taxes)) {
            $h->price()
                ->tax(PriceHelper::parse($taxes, $currency));
        }

        $this->detectDeadLine($h);
    }

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        //$this->logger->debug('In: '.$str);
        $in = [
            "#^(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#",
            "#^(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})\,\s*([\d\:]+)$#",
            '/^(\d{1,2}) (\w+), (\d{4})$/',
            '/^(\w+) (\d{1,2}), (\d{4})$/',
            '/^(\w+)\s*(\d+)\,\s*(\d{4})\,\s*([\d\:]+a?p?m)$/', //Nov 27, 2022, 11:59pm
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3, $4",
            '$1 $2 $3',
            '$2 $1 $3',
            '$2 $1 $3, $4',
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->debug('Out: '.$str);
        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $amount = (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));

        if ($amount == 0) {
            $amount = null;
        }

        return $amount;
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field));
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+a?p?m)\s*\(\D+\)\s*on\s*(?<date>\w+\s*\d+\,\s*\d{4})\s*or/u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])));
        }

        if (preg_match("/Cancellations or changes made after\s*(?<time>[\d\:]+\s*A?P?M)\s*\(property local time\)\s*on\s*(?<day>\d+)\s*(?<month>\w+)\.\s*(?<year>\d{4})\s*or\s*no\-shows/u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time'])));
        }

        if (preg_match("/Cancelamentos ou alterações feitos após\s*(?<h>\d+)h(?<m>\d+)\s*\(horário local da propriedade\)\,\s*em\s*(?<date>\d+\s*\D+\d{4})\,/u", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date'] . ', ' . $m['h'] . ':' . $m['m'])));
        }
    }
}
