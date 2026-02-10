<?php

namespace AwardWallet\Engine\carlson\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ConfirmationLetterPdf extends \TAccountChecker
{
    public $mailFiles = "carlson/it-60270245.eml, carlson/it-60270801.eml";
    public $lang = "en";
    public static $dictionary = [
        "de" => [
            "Dear"     => "Sehr geehrte",
            "Mr / Mrs" => "herr/damen",
            // "CANCELLATION LETTER" => "",
            "Booking details"    => "Reservierungsinformationen",
            "Arrival"            => "Anreise",
            "Departure"          => "Abreise",
            "Reservation number" => "Reservierungsnummer",
            "Guests"             => "Gäste",
            // "Adult" => "",
            // "Child" => "",
            // "Room Type" => "",
            // "Rate" => "",
            "Hotel guarantee and reservation policies" => "Hotel Garantie und Reservierungsrichtlinien",
            // "Radisson Rewards" => "",
            "Hotel information" => "Hotel Informationen",
            // "Hotel Name" => "",
            "Address"   => "Straße",
            "City"      => "Stadt",
            "Zip code"  => "Postleitzahl",
            "Country"   => "Land",
            "Telephone" => "Telefon",
            // "Fax" => "",
        ],
        "en" => [
            "Your reservation number is" => ["Your reservation number is", "the following accommodations for you:"],
            "Dear"                       => ["Dear", "Hello"],
            //            "Mr / Mrs" => "",
            //            "CANCELLATION LETTER" => "CANCELLATION LETTER",
            //            "Booking details" => "",
            //            "Arrival" => "",
            //            "Departure" => "",
            //            "Reservation number" => "",
            //            "Guests" => "",
            //            "Adult" => "",
            //            "Child" => "",
            //            "Room Type" => "",
            //            "Rate" => "",
            //            "Hotel guarantee and reservation policies" => "",
            //            "Radisson Rewards" => "",
            //            "Hotel information" => "",
            //            "Hotel Name" => "",
            //            "Address" => "",
            //            "City" => "",
            //            "Zip code" => "",
            //            "Country" => "",
            //            "Telephone" => "",
            //            "Fax" => "",
        ],
    ];

    private $detectFrom = "RadissonHotels@e.radissonhotels.com";

    private $detectSubject = [
        "CONFIRMATION OF THE RESERVATION",
        "Cancelation letter of the Reservation",
    ];

    private $detectProvider = 'Radisson Rewards';

    private $detectBody = [
        "de" => ['BESTÄTIGUNGS BRIEF'],
        "en" => ['CANCELLATION LETTER', 'CONFIRMATION LETTER'],
    ];

    private $pdfNamePattern = ".*\.pdf";

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
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (stripos($text, $this->detectProvider) === false) {
                    continue;
                }

                if ($this->assignLang($text) === true) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (stripos($text, $this->detectProvider) === false) {
                    continue;
                }

                if ($this->assignLang($text) === true) {
                    $this->parsePdf($email, $text);
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

    private function parsePdf(Email $email, string $text)
    {
        $h = $email->add()->hotel();

        $bookedInfo = $this->re("#\n{2,}\s*{$this->opt($this->t("Booking details"))}\n\s+([\s\S]+?)\n\s*{$this->opt($this->t("Hotel guarantee and reservation policies"))}\n#", $text);
        // General
        if (preg_match("/({$this->opt($this->t("Reservation number"))})[: ]+([-A-Z\d]{5,})\n/i", $bookedInfo, $m)) {
            $h->general()->confirmation($m[2], $m[1]);
        }
        $h->general()->traveller($this->re("/^[ ]*{$this->opt($this->t("Dear"))}(?:[ ]+{$this->opt($this->t("Mr / Mrs"))})?[ ]+([[:alpha:]][-,.\'’[:alpha:] ]*?[[:alpha:]])[ ,:;!?]*$/mu", $text));

        if (preg_match("#" . $this->opt($this->t("CANCELLATION LETTER")) . "#", $text)) {
            $h->general()->cancelled();
        }

        // Hotel
        $hotelInfo = $this->re("#\n{2,}\s*{$this->opt($this->t("Hotel information"))}\s+([\s\S]+)#", $text);
        $h->hotel()
            ->name($this->re("#\n\s*{$this->opt($this->t("Hotel Name"))} *(.+)#", $hotelInfo))
            ->phone($this->re("#\n\s*{$this->opt($this->t("Telephone"))} *(.+)#", $hotelInfo), true, true)
            ->fax($this->re("#\n\s*{$this->opt($this->t("Fax"))} *(.+)#", $hotelInfo), true, true)
//            ->detailed()
//                ->address($this->re("#\n\s*{$this->opt($this->t("Address"))} *(.+)#", $hotelInfo))
//                ->city($this->re("#\n\s*{$this->opt($this->t("City"))} *(.+)#", $hotelInfo))
//                ->zip($this->re("#\n\s*{$this->opt($this->t("Zip code"))} *(.+)#", $hotelInfo))
//                ->country($this->re("#\n\s*{$this->opt($this->t("Country"))} *(.+)#", $hotelInfo))
        ;
        $addrs = [
            $this->re("#\n\s*{$this->opt($this->t("Address"))} *(.+)#", $hotelInfo),
            $this->re("#\n\s*{$this->opt($this->t("City"))} *(.+)#", $hotelInfo),
            $this->re("#\n\s*{$this->opt($this->t("Country"))} *(.+)#", $hotelInfo),
            $this->re("#\n\s*{$this->opt($this->t("Zip code"))} *(.+)#", $hotelInfo),
        ];
        $h->hotel()->address(implode(', ', $addrs));

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->re("#{$this->opt($this->t("Arrival"))} *(.+)#i", $bookedInfo)))
            ->checkOut($this->normalizeDate($this->re("#{$this->opt($this->t("Departure"))} *(.+)#i", $bookedInfo)))
            ->guests($this->re("/^[ ]*{$this->opt($this->t("Guests"))}[: ]+(\d{1,3})[ ]*{$this->opt($this->t("Adult"))}/im", $bookedInfo))
            ->kids($this->re("/^[ ]*{$this->opt($this->t("Room Type"))}[: ]+(\d{1,3})[ ]*{$this->opt($this->t("Child"))}/im", $bookedInfo), true, true)
        ;

        $roomType = $roomRate = null;

        $roomType = $this->re("/^[ ]*{$this->opt($this->t("Room Type"))}[: ]+(.+)/im", $bookedInfo);

        if (preg_match_all("/^[ ]*{$this->opt($this->t("Rate"))}[ ]+[\d.]{6,}[: ]+(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\d)(]+)?$/m", $bookedInfo, $rateMatches)) {
            $rateMatches['currency'] = array_values(array_filter($rateMatches['currency']));

            if (count(array_unique($rateMatches['currency'])) < 2) {
                $rates = array_values(array_unique(array_map([$this, 'amount'], $rateMatches['amount'])));
                $rateCurrency = count($rateMatches['currency']) ? ' ' . $rateMatches['currency'][0] : '';

                if (count($rates) === 1) {
                    $roomRate = $rates[0] . $rateCurrency;
                } else {
                    $roomRate = min($rates) . ' - ' . max($rates) . $rateCurrency;
                }
            }
        }

        if ($roomType || $roomRate !== null) {
            $r = $h->addRoom();

            if ($roomType) {
                $r->setType($roomType);
            }

            if ($roomRate !== null) {
                $r->setRate($roomRate);
            }
        }

        $radissonRewards = $this->re("/\n\n{$this->opt($this->t("Radisson Rewards"))}\n+([\s\S]+?)\n+{$this->opt($this->t("Hotel information"))}\n/m", $text);

        if (preg_match_all("/^[ ]*RR[ ]*(\d[\d ]{4,})$/im", $radissonRewards, $rrMatches)) {
            // RR 6015995303762101
            $h->program()->accounts($rrMatches[1], false);
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function assignLang($text): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (strpos($text, $dBody) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\.(\d+)\.(\d{2})$#", //04.09.17
        ];
        $out = [
            "$1.$2.20$3",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
//            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
//                $str = str_replace($m[1], $en, $str);
//            }
//        }
        return strtotime($str);
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", trim($s)));
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
