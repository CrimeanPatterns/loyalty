<?php

namespace AwardWallet\Engine\dedge\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Cancellation extends \TAccountChecker
{
    public $mailFiles = "dedge/it-186768537.eml, dedge/it-257177992.eml, dedge/it-85447360.eml, dedge/it-260358790-host.eml, dedge/it-261023442-host.eml";

    public $lang = "en";
    public static $dictionary = [
        "en" => [ // it-85447360.eml
            "Booking cancellation - Reference:" => ["Booking cancellation - Reference:", "Booking modification - Reference:", "CANCELLATION OF BOOKING No."],
            "Booking confirmation - Reference:" => ["Booking confirmation - Reference:", "New booking no."],
            // "Booking modification - Reference:" => "",
            "cancelledPhrases"                  => ["Your booking cancellation has been noted", "Date/time of cancellation:", "Date/time of cancelation:"],
            "modifiedPhrases"                   => "Your booking modification has been noted",
            //            "Name:" => "",
            //            "Your hotel" => "",
            //            "Address" => "",
            //            "Town/City" => "",
            //            "Postcode" => "",
            //            "Country" => "",
            //            "Phone" => "",
            //            "Fax" => "",
            //            "Check-in:" => "",
            //            "Check-out:" => "",
            //            "Check-in time from" => "",
            //            "Latest check-out time" => "",
            //            "Adult(s):" => "",
            //            "Children between the ages of 2 and 12:" => "",
            //            "Children under 2 years of age:" => "",
            "Your rooms"                        => ["Your rooms", "Room(s)"],
            //            "Total amount for the stay" => "",
        ],
        "fr" => [ // it-186768537.eml
            "Booking cancellation - Reference:"      => ["Booking cancellation - Reference:", "Booking modification - Reference:"],
            // "Booking confirmation - Reference:" => "",
            "Booking modification - Reference:"      => "Modification de réservation - Référence:",
            // "cancelledPhrases" => "",
            "modifiedPhrases"                        => "Nous prenons bonne note de la modification de votre réservation",
            "Name:"                                  => "Nom :",
            "Booking"                                => "Réservation",
            "Your hotel"                             => "Votre hôtel",
            "Address"                                => "Adresse",
            "Town/City"                              => "Ville",
            "Postcode"                               => "Code postal",
            "Country"                                => "Pays",
            "Phone"                                  => "Téléphone",
            "Fax"                                    => "Fax",
            "Check-in:"                              => "Arrivée :",
            "Check-out:"                             => "Départ :",
            "Check-in time from"                     => "Heure d'arrivée (à partir de)",
            "Latest check-out time"                  => "Heure limite de départ",
            "Adult(s):"                              => "Adulte(s) :",
            "Children between the ages of 2 and 12:" => "Enfant(s) de 2 à 17 ans :",
            "Children under 2 years of age:"         => "Enfant(s) de moins de 2 ans :",
            "Your rooms"                             => "Vos chambres",
            "Total amount for the stay"              => "Montant total du séjour",
            "Traveller"                              => "Voyageur",
            "Services"                               => "Services",
        ],
        "it" => [ // it-257177992.eml
            "Booking cancellation - Reference:"      => ["Cancellazione della prenotazione - Riferimento:"],
            // "Booking confirmation - Reference:" => "",
            // "Booking modification - Reference:" => "Modification de réservation - Référence:",
            "cancelledPhrases"                       => "Prendiamo atto della cancellazione della tua prenotazione",
            // "modifiedPhrases" => "",
            "Name:"                                  => "Cognome:",
            "Booking"                                => "Prenotazione",
            "Your hotel"                             => "Il vostro hotel",
            "Address"                                => "Indirizzo",
            "Town/City"                              => "Città",
            "Postcode"                               => "Codice postale",
            "Country"                                => "Paese",
            "Phone"                                  => "Telefono",
            "Fax"                                    => "Fax",
            "Check-in:"                              => "Arrivo:",
            "Check-out:"                             => "Partenza:",
            "Check-in time from"                     => "Ora d'arrivo da",
            "Latest check-out time"                  => "Ora limite di partenza",
            "Adult(s):"                              => "Adulto(i):",
            "Children between the ages of 2 and 12:" => "Bambino(i) da 2 a 12 anni:",
            "Children under 2 years of age:"         => "Bambino(i) di meno di 2 anni:",
            "Your rooms"                             => "Le vostre camere",
            "Total amount for the stay"              => "Costo totale soggiorno",
            "Traveller"                              => "Viaggiatore(i):",
            "Services"                               => "Servizi",
        ],
    ];

    private $detectFrom = ["@availpro.com", "@d-edge.com"];
    private $detectSubject = [
        // en
        "Booking cancellation - Reference:",
        "Booking modification - Reference:",
        "Booking confirmation - Reference",
        ' - Booking cancellation - ',
        ' - New booking - ',
        // fr
        "Modification de réservation - Référence:",
        // it
        'Cancellazione della prenotazione - Riferimento:',
    ];

    private $detectCompany = [
        'www.book-secure.com',
        'www.secure-hotel-booking.com',
        "@availpro.com",
        "@d-edge.com",
        ".availpro.com",
        ".d-edge.com",
    ];

    private $detectBody = [
        "en" => [
            "Your booking cancellation has been noted",
            "Your booking modification has been noted",
            "Many thanks for your booking. We are happy to advise that it is now confirmed.",
        ],
        "fr" => ["Nous prenons bonne note de la modification de votre réservation"],
        "it" => ["Prendiamo atto della cancellazione della tua prenotazione"],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->striposAll($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (mb_stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains($this->detectCompany, '@href') . "]")->length === 0
            && $this->http->XPath->query("//*[" . $this->contains($this->detectCompany) . "]")->length === 0
            && $this->http->XPath->query("//img[" . $this->contains($this->detectCompany, '@src') . "]")->length === 0
            && $this->striposAll($parser->getCleanFrom(), $this->detectFrom) === false
        ) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                return true;
            }
        }

        return $this->isHost();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function isHost(): bool
    {
        return $this->http->XPath->query("//text()[{$this->eq($this->t('For the attention of:'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Any guest queries should be sent to'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t("Guest's payment card:"))}]")->length > 0;
    }

    private function parseHotel(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or contains(translate(@style," ",""),"font-weight:bold"))';

        $isHost = $this->isHost();

        // HOTEL

        $h = $email->add()->hotel();

        if ($isHost) {
            $h->booked()->host();
        }

        // General
        $travellers = [$this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Name:")) . "]", null, true, "/^\s*" . $this->preg_implode($this->t("Name:")) . "\s*(.+)/")];
        $tXpath = "//text()[" . $this->eq($this->t("Traveller(s):")) . "]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($tXpath);

        foreach ($nodes as $root) {
            $travellersText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $root));
            $travellers = array_merge($travellers,
                preg_replace("/^\s*\w{1,3}\. /u", '', explode("\n", $this->re("/" . $this->preg_implode($this->t("Traveller(s):")) . "\s*(.+)/s", $travellersText))));
        }

        $h->general()
            ->noConfirmation()
            ->travellers(array_unique(array_filter($travellers)), true);

        if ($this->http->XPath->query("//*[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            // it-85447360.eml, it-257177992.eml, it-261023442-host.eml
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->t('modifiedPhrases'))}]")->length > 0) {
            // it-186768537.eml
            $h->general()
                ->status('Modified');
        }

        // Hotel
        $h->hotel()->name($this->http->FindSingleNode("//text()[{$this->starts($this->t('Check-in:'))}]/preceding::text()[{$this->starts($this->t('Booking'))}][1]/preceding::text()[normalize-space()][2][ancestor::*[{$xpathBold}]]", null, true, "/^(.{2,}?)[*\s]*$/")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('For the attention of:'))}]/following::text()[normalize-space()][1]", null, true, "/^(.{2,}?)(?:\s*\(\s*[A-z\d]+\s*\)|$)$/")
        );

        $hotelInfo = implode(' ', $this->http->FindNodes("//text()[" . $this->eq($this->t("Your hotel")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]"));

        if (preg_match("/\s+" . $this->preg_implode($this->t("Address")) . "\s+(.+)\s+" . $this->preg_implode($this->t("Town/City")) . "\s+(.+)" .
            $this->preg_implode($this->t("Postcode")) . "\s+(.*)\s*\b" .
            $this->preg_implode($this->t("Country")) . "\s+(.+?)\s+" . $this->preg_implode($this->t("Phone")) . "\s+/s", $hotelInfo, $m)) {
            unset($m[0]);
            $h->hotel()
                ->address(implode(', ', $m));
        } elseif ($isHost) {
            $h->hotel()->address($this->http->FindSingleNode("//text()[{$this->eq($this->t('For the attention of:'))}]/following::text()[normalize-space()][2][not(ancestor::*[{$xpathBold}])]", null, true, "/^(.{2,}?)(?:\s*\(\s*[A-z\d]+\s*\)|$)$/"));
        }

        $h->hotel()
            ->phone($this->re("/" . $this->preg_implode($this->t("Phone")) . "\s*([\d \-\+\(\)\.]*\d+[\d \-\+\(\)\.]*)\s+/",
                $hotelInfo), true, true)
            ->fax($this->re("/" . $this->preg_implode($this->t("Fax")) . "\s*([\d \-\+\(\)\.]*\d+[\d \-\+\(\)\.]*)\s+/",
                $hotelInfo), true, true)
        ;

        // Booked
        $checkin = $this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Check-in:")) . "]", null, true,
            "/" . $this->preg_implode($this->t("Check-in:")) . "\s*(.+)/");
        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check-in time from")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d+:\d+)\s*$/");

        if (!empty($checkin)) {
            $h->booked()->checkIn($this->normalizeDate(implode(', ', array_filter([$checkin, $time]))));
        }

        $checkout = $this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Check-out:")) . "]", null, true,
            "/" . $this->preg_implode($this->t("Check-out:")) . "\s*(.+)/");
        $time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Latest check-out time")) . "]/following::text()[normalize-space()][1]", null, true,
            "/^\s*(\d+:\d+)\s*$/");

        if (!empty($checkout)) {
            $h->booked()->checkOut($this->normalizeDate(implode(', ', array_filter([$checkout, $time]))));
        }

        $h->booked()
            ->guests($this->http->FindSingleNode("//td[not(.//td) and " . $this->starts($this->t("Adult(s):")) . "]",
                null, true, "/" . $this->preg_implode($this->t("Adult(s):")) . "\s*(\d+)\s*$/"))
            ->kids($this->http->FindSingleNode("//td[" . $this->eq($this->t("Children between the ages of 2 and 12:")) . "]",
                null, true, "/" . $this->preg_implode($this->t("Children between the ages of 2 and 12:")) . "\s*(\d+)\s*$/")
                + $this->http->FindSingleNode("//td[" . $this->eq($this->t("Children under 2 years of age:")) . "]",
                    null, true, "/" . $this->preg_implode($this->t("Children under 2 years of age:")) . "\s*(\d+)\s*$/"), true, true)
        ;

        // Rooms
        $rXpath = "//text()[" . $this->eq($this->t("Your rooms")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]//b[following::text()[normalize-space()][1][contains(., ' x ')]]";
        $nodes = $this->http->XPath->query($rXpath);

        if ($nodes->length == 0) {
            $rXpath = "//text()[{$this->eq($this->t('Your rooms'))}]/following::text()[normalize-space()][1]/ancestor::td[1]//b[following::text()[normalize-space()][1]][not({$this->contains($this->t('Traveller'))}) and not(contains(normalize-space(), 'Services'))]";
            $nodes = $this->http->XPath->query($rXpath);

            foreach ($nodes as $root) {
                $h->addRoom()
                    ->setType($this->http->FindSingleNode(".", $root));
            }
        } else {
            foreach ($nodes as $root) {
                $h->addRoom()
                    ->setType($root->nodeValue);
            }
        }

        // Price
        $totalStr = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Total amount for the stay")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^(?<curr>[^\d)(]{1,5}?)\s*(?<amount>\d[,.\'\d ]*)$/", $totalStr, $m)
            || preg_match("/^(?<amount>\d[,.\'\d ]*)\s*(?<curr>[^\d)(]{1,5})$/", $totalStr, $m)
        ) {
            // EUR283.00    |    2 250,00 €
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $this->currency($m['curr'])))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Travel Agency
        $email->obtainTravelAgency();
        $otaConfirmation = $otaConfirmationTitle = null;

        $confirmation = $this->http->FindSingleNode("//tr/*[normalize-space()][2][{$this->starts($this->t("D-EDGE ref.:"))}]");

        if (preg_match("/^({$this->preg_implode($this->t("D-EDGE ref.:"))})[:\s]*([A-z\d]{5,})$/", $confirmation, $m)) {
            $otaConfirmation = $m[2];
            $otaConfirmationTitle = rtrim($m[1], ': ');
        }

        $hotelName = $h->getHotelName() ?? 'NOT HOTEL NAME';

        if (empty($otaConfirmation)) {
            $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Booking cancellation - Reference:"), 'translate(.,"*","")')}][1]", null, true,
                "/{$this->preg_implode($this->t("Booking cancellation - Reference:"))}[:\s]*(?i)([A-z\d]{5,})\s*(?:[-,.*;!]|{$this->preg_implode($hotelName)}|$)/u");
        }

        if (empty($otaConfirmation)) {
            $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Booking confirmation - Reference:"), 'translate(.,"*","")')}][1]", null, true,
                "/{$this->preg_implode($this->t("Booking confirmation - Reference:"))}[:\s]*(?i)([A-z\d]{5,})\s*(?:[-,.*;!]|{$this->preg_implode($hotelName)}|$)/u");
        }

        if (empty($otaConfirmation)) {
            $otaConfirmation = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Booking modification - Reference:"), 'translate(.,"*","")')}][1]", null, true,
                "/{$this->preg_implode($this->t("Booking modification - Reference:"))}[:\s]*(?i)([A-z\d]{5,})\s*(?:[-,.*;!]|{$this->preg_implode($hotelName)}|$)/u");
        }
        $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);

        $handlerConfirmation = $this->http->FindSingleNode("//tr[ *[normalize-space()][2][{$this->starts($this->t("D-EDGE ref.:"))}] ]/preceding-sibling::tr/*[normalize-space()][2]");

        if (preg_match("/^([^:]{2,}?)\s*[:]+\s*([-A-z\d]{5,})$/", $handlerConfirmation, $m)) {
            $email->ota()->confirmation($m[2], rtrim($m[1], ': '));
        }
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
        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            // 18 de fevereiro de 2020, 12:00
            "#^\s*(\d{1,2}) de ([^\s\d]+) de (\d{4})\s*, \s*(\d+:\d+)\s*$#iu",
            // samedi 10 juillet 2021, 14:00
            "#^\s*[[:alpha:]]+\s+(\d{1,2}) ([^\s\d]+) (\d{4})\s*, \s*(\d+:\d+)\s*$#iu",
        ];
        $out = [
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return (!empty($str)) ? strtotime($str) : null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
