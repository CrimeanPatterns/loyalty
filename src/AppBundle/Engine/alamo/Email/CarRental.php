<?php

namespace AwardWallet\Engine\alamo\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "alamo/it-3105985.eml, alamo/it-3692692.eml, alamo/it-3904342.eml, alamo/it-45756017.eml, alamo/it-46215509.eml, alamo/it-48947271.eml, alamo/it-677345311.eml";

    public static $dictionary = [
        "en" => [
            "Pickup Location"              => ["Pickup Location", "Pickup Location:"],
            "Return Location"              => ["Return Location", "Return Location:"],
            "Total Charges"                => ["Total Charges", "Total Price", "Estimated Total Price"],
            "Your confirmation number is:" => ["Your confirmation number is:", 'Your reservation number is:'],
            "Alamo Insiders #"             => ["Alamo Insiders #", 'Emerald Club #', 'Enterprise Plus/Emerald Club #'],
        ],
        "de" => [
            "Your confirmation number is:" => ["Ihre Bestätigungsnummer lautet:", 'Ihre Reservierungsnummer lautet:'],
            "Pickup Date & Time"           => "Datum & Zeit der Abholung",
            "Pickup Location"              => "Abholort",
            "Return Date & Time"           => "Datum & Zeit der Rückgabe",
            "Return Location"              => "Rückgabeort",
            //			"Directions to pickup location" => "",
            "Hours of Operation"  => "Öffnungszeiten",
            "Phone"               => "NOTTRANSLATED",
            "Vehicle Information" => "Fahrzeug Informationen",
            "Driver's Name"       => "Name des Fahrers",
            "Total Charges"       => "Gesamtkosten",
            //			"To Pay on Arrival" => "",
        ],
        "sv" => [
            "Your confirmation number is:"  => "Ditt reservationsnummer är:",
            "Pickup Date & Time"            => "Datum och tid för upphämtning",
            "Pickup Location"               => "Upphämtningsplats",
            "Return Date & Time"            => "Datum och tid för återlämning",
            "Return Location"               => "Återlämningsplats",
            "Directions to pickup location" => "Vägbeskrivning till upphämtningsplats",
            "Hours of Operation"            => "Öppettider",
            //			"Phone" => "",
            "Vehicle Information" => "Fordonsinformation",
            "Driver's Name"       => "Förarens namn",
            "Total Charges"       => "Uppskattad totalkostnad",
            "To Pay on Arrival"   => "Att betala vid upphämtning",
        ],
        "ja" => [
            "Your confirmation number is:"  => "予約番号は、",
            "Pickup Date & Time"            => "貸出日時",
            "Pickup Location"               => "貸出場所",
            "Return Date & Time"            => "返却日時",
            "Return Location"               => "返却場所",
            "Directions to pickup location" => "貸出場所の案内",
            "Hours of Operation"            => "営業時間",
            //			"Phone" => "",
            "Vehicle Information" => "車両に関する情報",
            "Driver's Name"       => "運転者の氏名",
            "Total Charges"       => "見積合計金額",
            "To Pay on Arrival"   => "現地払い",
        ],

        "it" => [
            "Your confirmation number is:"  => "Your reservation number is:",
            "Pickup Date & Time"            => "Orario e data di ritiro",
            "Pickup Location"               => "Luogo di ritiro",
            "Return Date & Time"            => "Data e orario di consegna",
            "Return Location"               => "Luogo di restituzione",
            "Directions to pickup location" => "Vägbeskrivning till upphämtningsplats",
            "Hours of Operation"            => "Orario lavorativo",
            //			"Phone" => "",
            "Vehicle Information" => "Informazioni del veicolo",
            "Driver's Name"       => "Nome del conducente",
            "Total Charges"       => "Prezzo totale",
            // "To Pay on Arrival"   => "",
        ],
    ];

    public $lang = "en";
    private static $providers = [
        'rentacar' => [
            'from' => ['@partnerbookingkit.com', 'no-reply@enterpriseholdings.com'],
            'body' => [
                "en" => ['Enterprise is not responsible for any overdraft fees incurred', 'Enterprise Plus/Emerald Club'],
            ],
            'subject' => [
                "en" => 'Enterprise Car Rental Confirmation',
            ],
            'companyNameInSubject' => ['Enterprise '],
        ],
        'national' => [
            'from' => ['@partnerbookingkit.com', 'no-reply@enterpriseholdings.com'],
            'body' => [
                "en" => 'National is committed to providing a range',
            ],
            'subject' => [
                "en" => 'National Car Rental Confirmation',
            ],
            'companyNameInSubject' => ['National Car'],
        ],
        'alamo' => [
            'from' => ['@partnerbookingkit.com', 'no-reply@enterpriseholdings.com'],
            'body' => [
                "en"  => "Alamo Rent-A-Car che opera presso",
            ],
            'subject' => [
                "en" => "Alamo Car Rental Confirmation",
                "de" => "Alamo Autovermietung Bestätigung",
                "sv" => "Alamo Biluthyrning bekräftelse",
                "ja" => "Alamo レンタカー 確認",
                "it" => "Alamo Noleggio auto Conferma",
            ],
            'companyNameInSubject' => ['Alamo '],
        ],
    ];

    private $detectBody = [
        "en"  => ["Reservation Details", "Thank you for your reservation!"],
        "de"  => "Reservierungsdetails",
        "sv"  => "Reservationsdetaljer",
        "ja"  => "予約の詳細",
        "it"  => "Dettagli della prenotazione",
    ];

    public function parseHtml(Email $email)
    {
        $car = $email->add()->rental();

        // Number
        if (!$confNumber = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Your confirmation number is:")) . "][1])[1]", null, true, "/{$this->opt($this->t("Your confirmation number is:"))}\s*([-A-Z\d]+)/")) {
            $confNumber = $this->nextText($this->t("Your confirmation number is:"));
        }

        $car->general()->confirmation($confNumber);

        $reservationDetails = '';
        $rDetailsNodes = $this->http->XPath->query("//text()[{$this->eq($this->t("Pickup Location"))}]/ancestor::*[{$this->contains($this->t("Return Location"))}][1]/*[self::h4 or self::p or self::dd or self::br or self::dl]");

        foreach ($rDetailsNodes as $rdNode) {
            $rdNodeHtml = $this->http->FindHTMLByXpath('.', null, $rdNode);
            $rdNodeText = $this->htmlToText($rdNodeHtml);
            $reservationDetails .= empty($rdNodeText) ? "\n\n" : "\n" . $rdNodeText;
        }

        // PickupDatetime
        if (preg_match("/{$this->opt($this->t("Pickup Date & Time"))}\s+(.{6,})/", $reservationDetails, $m)
            && ($datePickup = $this->normalizeDate($m[1]))
        ) {
            $pickupDatetime = strtotime($datePickup);
        }

        if (empty($pickupDatetime)) {
            $pickupDatetime = strtotime($this->normalizeDate($this->nextText($this->t("Pickup Date & Time"))));
        }
        $car->pickup()
            ->date($pickupDatetime);

        // DropoffDatetime
        if (preg_match("/{$this->opt($this->t("Return Date & Time"))}\s+(.{6,})/", $reservationDetails, $m)
            && ($datePickup = $this->normalizeDate($m[1]))
        ) {
            $dropoffDatetime = strtotime($datePickup);
        }

        if (empty($dropoffDatetime)) {
            $dropoffDatetime = strtotime($this->normalizeDate($this->nextText($this->t("Return Date & Time"))));
        }
        $car->dropoff()
            ->date($dropoffDatetime);

        $noAddressPhrases = ['Shuttle provided', 'Off site location, shuttle provided'];

        // PickupLocation
        // PickupPhone

        if (preg_match("/{$this->opt($this->t("Pickup Location"))}\s+([\s\S]{3,}?)\s+{$this->opt($this->t("Return Location"))}/u", $reservationDetails, $m)) {
            $m[1] = preg_replace('/\s+/', ' ', preg_replace("/\s+{$this->opt($noAddressPhrases)}/", '', $m[1]));

            if (preg_match("/^\s*(?<location>.+?)\s*{$this->opt($this->t("Phone"))}[^:]*:+\s*(?<phone>[+(\d][-. \d)(]{5,}[\d)])/", $m[1], $matches)) {
                $car->pickup()
                    ->location($matches['location'])
                    ->phone($matches['phone']);
            } else {
                $car->pickup()
                    ->location($m[1]);
            }
        }

        // DropoffLocation
        // DropoffPhone
        if (preg_match("/{$this->opt($this->t("Return Location"))}\s+([\s\S]{3,}?)(?:\n[ ]*\n|\s+{$this->opt($this->t("Directions to pickup location"))}|$)/u", $reservationDetails, $m)) {
            $m[1] = preg_replace('/\s+/', ' ', preg_replace("/\s+{$this->opt($noAddressPhrases)}/", '', $m[1]));

            if (preg_match("/^\s*(?<location>.+?)\s*{$this->opt($this->t("Phone"))}[^:]*:+\s*(?<phone>[+(\d][-. \d)(]{5,}[\d)])/", $m[1], $matches)) {
                $car->dropoff()
                    ->location($matches['location'])
                    ->phone($matches['phone']);
            } else {
                $car->dropoff()
                    ->location($m[1]);
            }
        }

        // PickupHours
        // DropoffHours
        $hoursOperation = null;
        $hoursOperationRows = $this->http->XPath->query("descendant::text()[{$this->eq($this->t("Hours of Operation"))}]/following::table[1]//tr[normalize-space()]");

        foreach ($hoursOperationRows as $hoRow) {
            $hoursOperation .= implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $hoRow)) . '; ';

            $car->pickup()
                ->openingHours(implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $hoRow)));
            $car->dropoff()
                ->openingHours(implode(' ', $this->http->FindNodes('descendant::text()[normalize-space()]', $hoRow)));
        }

        // CarType
        $car->car()
            ->type($this->http->FindSingleNode("//h3[" . $this->eq($this->t("Vehicle Information")) . "]/following::text()[normalize-space(.)][1]"));

        // CarImageUrl
        $carImageUrl = $this->http->FindSingleNode("//h3[{$this->eq($this->t("Vehicle Information"))}]/following::node()[ self::text()[normalize-space()] or self::*[img] ][2]/descendant::img/@src", null, true, '/.+(?:carpic|fareoffice|\/content\/).+/i');

        if ($carImageUrl) {
            $car->car()
                ->image($carImageUrl);
        }

        // CarModel
        $car->car()
            ->model($this->http->FindSingleNode("//h3[" . $this->eq($this->t("Vehicle Information")) . "]/following::text()[normalize-space(.)][2]"));

        // RenterName
        $car->general()
            ->traveller($this->nextText($this->t("Driver's Name")));

        // TotalCharge
        // Currency
        // Fees
        $totalPrice = $this->http->FindSingleNode("//div[{$this->starts($this->t("To Pay on Arrival"))}]");

        if ($totalPrice === null) {
            $totalPrice = $this->http->FindSingleNode("//td[{$this->starts($this->t("Total Charges"))}]/following-sibling::*[normalize-space()]");
        }

        if (preg_match("/(?:^|{$this->opt($this->t("To Pay on Arrival"))})[\D\s]*(?<amount>\d[,.\'\d]*) ?(?<currency>[A-Z]{3})(?:[^A-Z]|$)/", preg_replace('/^.+\(\s*(\d[,.\'\d]* ?[A-Z]{3})[^A-Z]+$/', '$1', $totalPrice), $m)) {
            // 257.55 GBP    |    $74.30 USD
            $car->price()
                ->currency($m['currency'])
                ->total($this->amount($m['amount']));

            $fNodes = $this->http->XPath->query("//text()[{$this->eq($this->t("Total Charges"))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()]");

            foreach ($fNodes as $i => $root) {
                $name = $this->http->FindSIngleNode('td[1]', $root);
                $charge = $this->http->FindSIngleNode('td[2]', $root, true, '/^[\D\s]*(?<amount>\d[,.\'\d]*) ?' . preg_quote($m['currency'], '/') . '(?:[^A-Z]|$)/');

                if ($i === 0 || ($i === 1 && preg_match("/^\s*\d+ [[:alpha:]]+ @/u", $name))) {
                    $cost = ($car->getPrice() && $car->getPrice()->getCost()) ? $car->getPrice()->getCost() : 0.0;
                    $car->price()
                        ->cost($cost + $this->amount($charge));

                    continue;
                }

                if ($name && $charge !== null) {
                    $car->price()
                        ->fee($name, $this->amount($charge));
                }
            }
        }

        // AccountNumbers
        $accountNumber = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Alamo Insiders #'))}][1]/following::text()[normalize-space()][1]", null, true, '/^\s*([\dA-Z]{5,})\s*$/');

        if (!empty($accountNumber)) {
            $car->addAccountNumber($accountNumber, false);
        }

        return true;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $key => $option) {
            foreach ($option['from'] as $reFrom) {
                return strpos($from, $reFrom) !== false;
            }
        }
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['subject'] as $lang=>$subject) {
                if (strpos($headers["subject"], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['body'] as $lang=>$body) {
                if ($this->http->XPath->query("//*[{$this->contains($body)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = true;
        $this->assignLang();
        $email->setProviderCode($this->getProvider($parser->getSubject()));

        $this->parseHtml($email);
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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
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
        $in = [
            "#^[^\s\d]+, (\d+) ([^\s\d]+) (\d{4}) (\d+:\d+)$#", // Sa, 13 Jan 2018 9:00
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4}) (\d+:\d+ [AP]M)$#", // Thu, Apr 7 2016 4:30 PM
            "#^[^\s\d]+, (\d{1,2}) (\d{1,2})(?: 月)? (\d{4}) (\d+:\d+)$#", // 水, 9 10 月 2019 16:00
        ];
        $out = [
            "$1 $2 $3, $4",
            "$2 $1 $3, $4",
            "$3-$2-$1, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = str_replace("\n", '', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $bDetect) {
            if ($this->http->XPath->query("(//*[" . $this->contains($bDetect) . "])[1]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function getProvider($subject = null)
    {
        foreach (self::$providers as $prov => $option) {
            foreach ($option['body'] as $lang=>$body) {
                if ($this->http->XPath->query("(//*[" . $this->contains($body) . "])[1]")->length > 0) {
                    return $prov;
                }
            }

            foreach ((array) $option['companyNameInSubject'] as $companyName) {
                if (stripos($subject, $companyName) !== false) {
                    return $prov;
                }
            }
        }
    }
}
