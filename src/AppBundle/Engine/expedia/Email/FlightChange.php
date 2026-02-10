<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightChange extends \TAccountChecker
{
    public $mailFiles = "expedia/it-1566366.eml, expedia/it-1675256.eml, expedia/it-1920880.eml, expedia/it-2012456.eml, expedia/it-3080879.eml, expedia/it-3105848.eml, expedia/it-4.eml, expedia/it-4198720.eml, expedia/it-4226662.eml, expedia/it-4248306.eml, expedia/it-4370976.eml, expedia/it-4578317.eml, expedia/it-4920484.eml, expedia/it-5152829.eml, expedia/it-5680149.eml, expedia/it-6103039.eml, expedia/it-6106962.eml, expedia/it-6316157.eml, expedia/it-6332692.eml, expedia/it-72854211.eml, expedia/it-8586975.eml";

    public static $dictionary = [
        "en" => [
            "Flight Details"             => ["Flight Details", "Flight Change Details", "Cancelled Flight"],
            "Cancelled Flights"          => ["Cancelled Flight"],
            "Equipment:"                 => ["Equipment:", "Aircraft:"],
            "Seat:"                      => ["Seat:", "Seats:"],
            "Itinerary Number"           => ["Itinerary Number", "Confirmation Number(s):", 'Trip ID:'],
            " confirmation code"         => [" confirmation code", "Booking Locator", 'Confirmation Code'],
            "(.*?)\s+confirmation code:" => "(.*?)\s+(?:confirmation code|Confirmation Code|Booking Locator):",
        ],
        "pt" => [
            " confirmation code"         => "Código de confirmação da",
            "Flight Details"             => "Detalhes do voo",
            "(.*?)\s+confirmation code:" => "Código de confirmação da (.*?):",
            "Itinerary Number"           => "Número de itinerário",
            "Passenger(s):"              => "Passageiro(s):",
            "Flight Number:"             => "Número do voo:",
            "From:"                      => "De:",
            "Depart:"                    => "Partida:",
            "To:"                        => "Para:",
            "Arrive:"                    => "Chegada:",
            "Equipment:"                 => "Aeronave:",
            "Class:"                     => "Classe:",
            "Seat:"                      => ["Assentos:", "Assento:"],
            "change"                     => "mudança",
            "day"                        => "dia",
        ],
        "de" => [
            " confirmation code"         => "Bestätigungscode ",
            "Flight Details"             => ["Flugdetails", "Flug ändern Details"],
            "(.*?)\s+confirmation code:" => "Bestätigungscode (.*?):",
            "Itinerary Number"           => "Reiseplannummer",
            "Passenger(s):"              => "Passagier(e):",
            "Flight Number:"             => "Flugnummer:",
            "From:"                      => "Von:",
            "Depart:"                    => "Abflug:",
            "To:"                        => "Nach:",
            "Arrive:"                    => "Ankunft:",
            "Equipment:"                 => "Transport:",
            "Class:"                     => "Klasse:",
            "Seat:"                      => ["Sitze:", "Sitz:"],
            "change"                     => "änderung",
            "day"                        => "tag",
        ],
        "es" => [
            " confirmation code"         => "Código de confirmación de ",
            "Flight Details"             => "Detalles del vuelo",
            "Status:"                    => "Estado:",
            "(.*?)\s+confirmation code:" => "Código de confirmación de (.*?):",
            "Itinerary Number"           => "Número de Itinerario",
            "Passenger(s):"              => "Pasajero(s):",
            "Flight Number:"             => "Número de vuelo:",
            "From:"                      => "Origen:",
            "Depart:"                    => "Salida:",
            "To:"                        => "Destino:",
            "Arrive:"                    => "Llegada:",
            "Equipment:"                 => "Tipo de avión:",
            "Class:"                     => "Clase:",
            "Seat:"                      => ["Seat:", "Seats:"],
            //			"change" => "",
            //			"day" => "",
        ],
        "nl" => [
            " confirmation code"         => "Bevestigingscode van ",
            "Flight Details"             => ["Vluchtgegevens", "Vluchtwijzigingsgegevens"],
            "Status:"                    => "Status:",
            "(.*?)\s+confirmation code:" => "Bevestigingscode van (.*?):",
            "Itinerary Number"           => "Reisplannummer",
            "Passenger(s):"              => "Passagier(s):",
            "Flight Number:"             => "Vluchtnummer:",
            "From:"                      => "Van:",
            "Depart:"                    => "Vertrek:",
            "To:"                        => "Naar:",
            "Arrive:"                    => "Aankomst:",
            "Equipment:"                 => "Vliegtuig:",
            "Class:"                     => "Klasse:",
            "Seat:"                      => ["Stoel:", "Stoelen:"],
            "change"                     => "wijziging",
            "day"                        => "dag",
        ],
    ];
    public $lang = "";
    private $reBody2 = [
        //        "en" => "Your Flight Details Have Changed",
        //        "en2" => "Your Flight Has Been Upgraded",
        //        "en3" => "Please select a new seat for your upcoming flight",
        //        "en4" => "Your Flight Details",
        //        "pt" => "Os detalhes do seu voo foram alterados",
        //        "de" => "Ihre Flugdetails haben sich geändert",
        //        "es" => "Selección de un nuevo asiento para su próximo vuelo",
        "en" => "Flight Number",
        "pt" => "Detalhes do voo",
        "de" => "Flugnummer",
        "es" => "Detalles del vuelo",
        "nl" => "Vluchtgegevens",
    ];
    private $code;
    private $bodies = [
        'orbitz' => [
            '//a[contains(@href,"orbitz.")]',
            '//img[contains(@src,"orbitz")]',
            'Orbitz',
        ],
        'ebookers' => [
            '//a[contains(@href,"ebookers.")]',
            '//img[contains(@src,"ebookers")]',
            'Ebookers',
        ],
        'hotwire' => [
            '//a[contains(@href,"hotwire.")]',
            '//img[contains(@src,"hotwire")]',
            'Hotwire',
        ],
        'lastminute' => [
            '//a[contains(@href,"lastminute.")]',
            '//img[contains(@src,"lastminute")]',
            'Lastminute',
        ],
        'celebritycruises' => [
            '//a[contains(@href,".celebritycruises.com")]',
            'Flights By Celebrity',
        ],
        'amextravel' => [
            '//a[contains(@href,"americanexpress.com")]',
            'American Express.',
        ],
        'ctmanagement' => [
            'The Corporate Travel Management Team.',
        ],
        'wellsfargo' => [
            '//a[contains(@href,".wellsfargorewards.com")]',
            'Wells Fargo Rewards',
            'Wells Fargo Business Rewards',
        ],
        'egencia' => [
            '//a[contains(@href,".egencia.com")]',
            'Thank you for choosing Egencia',
            'Egencia Itinerary Number',
        ],
        // the last
        'expedia' => [
            '//a[contains(@href,"expedia.")]',
            '//img[contains(@src,"expedia")]',
            'Expedia',
        ],
    ];
    private static $headers = [
        'orbitz' => [
            'from' => ['@orbitz.com', '@email.orbitz.com'],
            'subj' => [
                'Changes have been made to your',
                'Flight upgrade',
                'Itinerary update:',
            ],
        ],
        'ebookers' => [
            'from' => ['@ebookers.ie', '@ebookers.com'],
            'subj' => [
                'Itinerary update: Confirmation of change',
                'Action Needed - Notification of Change to Your',
                'Notification of changes to your flight',
                'Notification of minor changes to your flight',
            ],
        ],
        'hotwire' => [
            'from' => ['@hotwire.com'],
            'subj' => [
                'Changes have been made to your',
            ],
        ],
        'expedia' => [
            'from' => ['@expedia.com', '@CUSTOMERCARE.EXPEDIA.COM'],
            'subj' => [
                "Flight change for",
                "Flight upgrade for",
                "Flight update regarding your seat assignments",
                "Seu voo de",
                "Flugänderungen für",
                "Selección de un nuevo asiento para su próximo vuelo",
            ],
        ],
        'lastminute' => [
            'from' => ['@Lastminute.com.au'],
            'subj' => [
                "Flight change for",
            ],
        ],
        'celebritycruises' => [
            'from' => ['@rccl.com'],
            'subj' => [
                "Notification of Air Itinerary Changes for Reservation:",
            ],
        ],
        'amextravel' => [
            'from' => ['noreply@myamextravel.americanexpress.com'],
            'subj' => [
                "Notification of Changes to Your",
            ],
        ],
        'ctmanagement' => [
            'from' => ['noreply@travelctm.com'],
            'subj' => [
                "Notification of Changes to Your",
            ],
        ],
        'wellsfargo' => [
            'from' => ['@wellsfargorewards.com'],
            'subj' => [
                "Notification of Changes to Your",
            ],
        ],
        'egencia' => [
            'from' => ['@egencia.com'],
            'subj' => [
                "Flight change for",
            ],
        ],
    ];
    private $date;

    public function parseEmail(Email $email)
    {
        $rls = [];
        $nodes = $this->http->XPath->query("//text()[" . $this->contains($this->t(" confirmation code")) . "]");

        foreach ($nodes as $root) {
            $rl = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]",
                $root, false, "#^\s*([A-Z\d]{5,})\s*$#");

            if (!empty($rl)) {
                $airline = $this->http->FindSingleNode(".", $root, true,
                    "#^{$this->t($this->t("(.*?)\s+confirmation code:"))}$#");
                $rls[$airline] = $rl;
            }
        }

        $email->ota()
            ->code($this->code);
        $confNo = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Itinerary Number"))}])[1]/following::text()[normalize-space()!=''][1]",
                null, true, "#^([\d\-]{5,})$#");

        if (!empty($confNo)) {
            $email->ota()
                ->confirmation($confNo,
                    trim($this->http->FindSingleNode("(//text()[{$this->contains($this->t("Itinerary Number"))}])[1]"), ":"), true);
        }
        $confNo = $this->http->FindSingleNode("//text()[{$this->contains($this->t("Record Locator:"))}]/following::text()[normalize-space()!=''][1]",
            null, true, "#^([A-Z\d]{5,})$#");

        if (!empty($confNo)) {
            $email->ota()
                ->confirmation($confNo,
                    trim($this->http->FindSingleNode("(//text()[{$this->contains($this->t("Record Locator:"))}])[1]"), ":"));
        }

        if (empty($email->getTravelAgency()->getConfirmationNumbers())) {
            $email->ota()
                ->confirmation(null);
        }

        $f = $email->add()->flight();
        $f->general()
            ->noConfirmation()
            ->travellers(array_map("trim", explode(",",
                $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Passenger(s):")) . "]/following::text()[normalize-space(.)][1]"))));

        $xpath = "//text()[" . $this->eq($this->t("Flight Details")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();
            $airline = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root);
            $status = ucfirst(mb_strtolower($this->http->FindSingleNode("./following-sibling::tr[4]/td[2]", $root, null,
                "#" . $this->t("Status:") . "\s+(.+)#")));
            $s->airline()
                ->name($airline);

            $operator = $this->http->FindSingleNode("./following-sibling::tr[6]/td[normalize-space(.)!=''][1]", $root,
                true, "#.*" . $this->t('Operated By') . "[ :]+\/?(.+?)\s*(?:\||$)#i");

            if (!empty($operator)) {
                $s->airline()->operator($operator);
            }

            if (isset($rls[$airline])) {
                $s->airline()
                    ->confirmation($rls[$airline]);
            }
            $s->extra()->status($status);

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[2]", $root));

            if (!($flightNumber = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root, true,
                "#^" . $this->t("Flight Number:") . "\s+\w{2}(\d+)$#"))
            ) {
                $flightNumber = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root, true,
                    "#^" . $this->t("Flight Number:") . "\s+\w{2}\s+\d+\s+(\d+)\s+\(#");
            }

            $s->airline()
                ->number($flightNumber)
                ->name($this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root, true,
                    "#^" . $this->t("Flight Number:") . "\s+(\w{2})#"));

            $depTime = $this->http->FindSingleNode("./following-sibling::tr[2]/td[3]", $root, true,
                "#" . $this->t("Depart:") . "\s+(.+)#");
            $depDate = strtotime($date . ' ' . $this->normalizeTime($depTime));
            $depCode = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true,
                "#\(([A-Z]{3})[\)-]#");

            if (!empty($depCode)) {
                $s->departure()->code($depCode);
            } else {
                $s->departure()->noCode();
            }
            $s->departure()
                ->name(trim(preg_replace("#\(.+\)#", "",
                    $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true,
                        "#" . $this->t("From:") . "\s+(.+)#"))))
                ->date($depDate);

            $arrTime = $this->http->FindSingleNode("./following-sibling::tr[3]/td[3]", $root, true,
                "#" . $this->t("Arrive:") . "\s+(.+)#");
            $arrDate = strtotime($date . ' ' . $this->normalizeTime($arrTime));

            if (preg_match("#\+\s*(\d+)\s*" . $this->t("day") . "\s*$#", $arrTime, $m)) {
                $arrDate = strtotime("+" . $m[1] . " day", $arrDate);
            }
            $arrCode = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true,
                "#\(([A-Z]{3})[\)-]#");

            if (!empty($arrCode)) {
                $s->arrival()->code($arrCode);
            } else {
                $s->arrival()->noCode();
            }
            $s->arrival()
                ->name(trim(preg_replace("#\(.+\)#", "",
                    $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true,
                        "#" . $this->t("To:") . "\s+(.+)#"))))
                ->date($arrDate);

            $s->extra()
                ->seats(array_filter(str_replace('00', '', array_filter(explode(", ",
                    $this->http->FindSingleNode("./following-sibling::tr[5]/td[3]", $root, null,
                        "#(?:" . $this->opt($this->t("Seat:")) . ")\s+(.*\d{1,3}[A-Z])#"))))));

            $aircraft = implode(" ",
                $this->http->FindNodes("./following-sibling::tr[5]/td[2]//text()[not(ancestor::del)]", $root));

            if (preg_match("#" . $this->opt($this->t("Equipment:")) . "\s+([^(]+)#", $aircraft, $m)) {
                $s->extra()->aircraft(trim($m[1]));
            }

            $cabin = implode(" ",
                $this->http->FindNodes("./following-sibling::tr[4]/td[3]//text()[not(ancestor::del)]", $root));

            if (preg_match("#" . $this->opt($this->t("Class:")) . "\s+([^(]+)#", $cabin, $m)) {
                $s->extra()->cabin(trim($m[1]));
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        } else {
            $this->logger->debug('[LANG]: ' . $this->lang);
        }

        if (null !== ($this->code = $this->getProvider($parser))) {
            $this->logger->debug('[providerCode]: ' . $this->code);
            $email->ota()->code($this->code);
            $email->setProviderCode($this->code);
        } else {
            $this->logger->debug('can\'t determine providerCode');

            return $email;
        }

        $this->date = strtotime($parser->getDate());
        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (null !== ($code = $this->getProviderByBody())) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public static function getEmailTypesCount()
    {
        $types = 1; // flight
        $provs = count(self::$headers);
        $langs = count(self::$dictionary);

        return $types * $provs * $langs;
    }

    private function normalizeDate($str)
    {
        $in = [
            // Wednesday, Feb 06, 2013 1:15 PM
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+(at\s+)?\d+:\d+(\s+[AP]M)?$#",
            // Thursday, 21 December, 2017 at 02:05
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})\s+(at\s+)?\d+:\d+(\s+[AP]M)?$#",
            //Sonntag, 20 Dezember, 2015
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})$#",
            //Mar 18, 2015 at 8:15 AM Thursday, Mar 19, 2015 at 8:15 AM (change)
            "#^[^\d\s]+\s+\d+,\s+\d{4}\s+at\s+\d+:\d+\s+[AP]M\s+[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)\s+\(" . $this->t("change") . "\)$#",
            //Saturday, Nov 19, 2016 at 5:40 AM Nov 17, 2016 at 5:40 AM (change)
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)\s+[^\d\s]+\s+\d+,\s+\d{4}\s+at\s+\d+:\d+\s+[AP]M\s+\(" . $this->t("change") . "\)$#",
            //24 November, 2017 Saturday, 25 November, 2017 (change)
            "#^.*[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})\s+\(" . $this->t("change") . "\)$#",
            // December 3, 2022 Thursday, December 01, 2022 at 6:30 PM (change)
            "#^[^\d\s]+\s+\d+,\s+\d{4}\s+[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)\s+\(" . $this->t("change") . "\)$#",
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$2 $1 $3",
            "$1 $2 $3",
            "$1 $2 $3",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        $in = [
            // 1:15 PM +1 day; 11:30 +1 dia; 06:15 +1 tag;
            "#^(\d+:\d+(\s+[AP]M)?)(\s+\+\d+\s+" . $this->t("day") . ")?$#",
            //5:25 AM 5:05 AM (change); 05:25 05:05 (change); 12:35 12:40 (änderung)
            "#^\d+:\d+(?:\s+[AP]M)?\s+(\d+:\d+(\s+[AP]M)?)\s+\(" . $this->t("change") . "\)(\s+\+\d+\s+" . $this->t("day") . ")?$#",
        ];
        $out = [
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return "(?:" . implode("|", array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ")";
    }

    private function getProviderByBody()
    {
        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }
}
