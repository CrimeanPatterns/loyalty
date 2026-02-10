<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class OverviewActivity extends \TAccountChecker
{
    public $mailFiles = "expedia/it-11145451.eml, expedia/it-31772382.eml, expedia/it-35579516.eml, expedia/it-9874229.eml, expedia/it-266442247-fr.eml";
    public $reBody2 = [
        "en" => "Activity overview",
        "pt" => "Visão geral da atividade",
        "es" => "Resumen de la actividad",
        "fr" => ["Aperçu de l’activité", "Aperçu de l'activité"],
    ];

    public static $dictionary = [
        "en" => [
            //            "Activity overview" => "",
            //            "Reservation dates" => "",
            //            "Itinerary #" => "",
            //            "Reserved for" => "",
            //            "travell?ers?" => "",
            //            "Location" => "",
            //            "Total:" => "",
            //            "All prices are quoted in" => "",
        ],
        "pt" => [
            "Activity overview"        => "Visão geral da atividade",
            "Reservation dates"        => "Datas da reserva",
            "Itinerary #"              => "Nº do itinerário",
            "Reserved for"             => "Reservado para",
            "travell?ers?"             => "viajantes?",
            "Location"                 => "Localização",
            "Total:"                   => "Total:",
            "All prices are quoted in" => "Todos os preços foram cotados em",
        ],
        "es" => [
            "Activity overview"        => "Resumen de la actividad",
            "Reservation dates"        => "Fechas de la reservación",
            "Itinerary #"              => "No. de itinerario",
            "Reserved for"             => "Reservado para",
            "travell?ers?"             => "personas?",
            "Location"                 => "Ubicación",
            "Total:"                   => "Total:",
            "All prices are quoted in" => "Todos los precios se muestran en",
        ],
        "fr" => [
            "Activity overview"        => ["Aperçu de l’activité", "Aperçu de l'activité"],
            "Reservation dates"        => "Dates de réservation",
            "Itinerary #"              => "Itinéraire nº",
            "Reserved for"             => "Réservé pour",
            "travell?ers?"             => "voyageurs?",
            "Location"                 => "Emplacement",
            "Total:"                   => "Total :",
            "All prices are quoted in" => "Tous les prix sont en",
        ],
    ];

    public $lang = "en";

    public static $headers = [
        'cheaptickets' => [
            'from' => ['cheaptickets.com'],
            'subj' => [
                'CheapTickets travel confirmation',
            ],
        ],
        'ebookers' => [
            'from' => ['ebookers.com'],
            'subj' => [
                'ebookers travel confirmation',
                'ebookers-Reisebestätigung',
                'Votre confirmation de voyage ebookers',
            ],
        ],
        'hotels' => [
            'from' => ['@support-hotels.com'],
            'subj' => [
                'en' => 'Hotels.com travel confirmation',
            ],
        ],
        'hotwire' => [
            'from' => ['noreply@Hotwire.com'],
            'subj' => [
                'en' => 'Hotwire travel confirmation',
            ],
        ],
        'mrjet' => [
            'from' => ['mrjet.se'],
            'subj' => [
                'sv' => 'Resebekräftelse från MrJet',
            ],
        ],
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'Orbitz travel confirmation',
            ],
        ],
        'rbcbank' => [
            'from' => ['rbcrewardstravel@rbcrewards.com'],
            'subj' => [
                'RBC Travel travel confirmation',
            ],
        ],
        'travelocity' => [
            'from' => ['email@e.travelocity.com'],
            'subj' => [
                'Travelocity travel confirmation',
            ],
        ],
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                "Expedia travel confirmation",
                'Confirmation de voyage Expedia',
            ],
        ],
    ];

    private $bodies = [
        'chase' => [
            '//img[contains(@src,"chase.com")]',
            'Chase Travel',
        ],
        'cheaptickets' => [
            '//img[contains(@src,"cheaptickets.com")]',
            'cheaptickets.com',
            'Call CheapTickets customer',
        ],
        'ebookers' => [
            '//img[contains(@alt,"ebookers.com")]',
            'Collected by ebookers',
        ],
        'hotels' => [
            '//img[contains(@src,"Hotels.com")]',
            "Hotels.com",
        ],
        'hotwire' => [
            '//img[contains(@alt,"Hotwire.com")]',
            'Or call Hotwire at',
        ],
        'mrjet' => [
            '//img[contains(@src,"MrJet.se")]',
            'MrJet.se',
        ],
        'orbitz' => [
            '//img[contains(@alt,"Orbitz.com")]',
            'Call Orbitz customer care',
            'This Orbitz Itinerary was sent from',
        ],
        'rbcbank' => [
            '//img[contains(@src,"rbcrewards.com")]',
            'rbcrewards.com',
        ],
        'travelocity' => [
            '//img[contains(@src,"travelocity.com")]',
            'travelocity.com',
            'Collected by Travelocity',
        ],
        'expedia' => [
            '//img[contains(@alt,"expedia.com")]',
            '//img[contains(@src,"expedia.com")]',
            'expedia.com',
        ],
    ];

    private $reBody = [
        'CheapTickets',
        'ebookers',
        'Hotels.com',
        'Hotwire',
        'MrJet',
        'Orbitz',
        'RBC Travel',
        'Travelocity',
        'Expedia',
        'chase.com',
    ];

    private $code;

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
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

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function parseHtml(Email $email): void
    {
        $event = $email->add()->event();
        $event->setEventType(Event::TYPE_EVENT);

        // ConfNo
        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary #'))}]/ancestor::td[1]/descendant::text()[normalize-space()][2]");
        $confDescription = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Itinerary #'))}]/ancestor::td[1]/descendant::text()[normalize-space()][1]");

        if (!empty($confirmation)) {
            $event->general()
                ->confirmation($confirmation, $confDescription);
        } else {
            $event->general()
                ->noConfirmation();
        }

        // Name
        $event->place()->name($this->nextText($this->t("Activity overview")));

        // StartDate
        $startDate = strtotime($this->normalizeDate($this->re("#(.*?) -#", $this->nextText($this->t("Reservation dates")))));

        if (!empty($startDate)) {
            $event->booked()
                ->start($startDate);
        }

        if ($time = $this->re("#(\d+:\d+ [AP]M)#", $this->nextText($this->t("Activity overview")))) {
            $event->booked()
                ->start(strtotime($time, $startDate))
                ->noEnd()
            ;
        } else {
            $endDate = strtotime($this->normalizeDate($this->re("#.*? -\s+(.+)#", $this->nextText($this->t("Reservation dates")))));
            $endDate = strtotime('23:59', $endDate);
            $event->booked()
                ->end($endDate);
        }

        // Address
        $address = $this->nextText($this->t("Location"));

        if (empty($address)) {
            $address = $event->getName();
        }

        if (!empty($address)) {
            $event->place()
                ->address($address);
        }

        // Phone
        // DinerName
        $event->general()
            ->traveller($this->nextText($this->t("Reserved for")));

        // Guests
        $guests = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Reserved for")) . "]/ancestor::td[1]//text()"));

        if (preg_match("#\s+(\d+) " . $this->t("travell?ers?") . "\b#", $guests, $m) && $m[1]) {
            $event->booked()->guests($m[1]);
        }

        // TotalCharge
        // Currency
        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Total:"))}]", null, true, "/^{$this->opt($this->t("Total:"))}\s*(.*\d.*)$/");
        $currencyCode = $this->http->FindSingleNode("//text()[{$this->starts($this->t("All prices are quoted in"))}]", null, true, "/{$this->opt($this->t("All prices are quoted in"))}\s+([A-Z]{3})[,.;!\s]*$/");

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
        ) {
            // 272,68 $ CA    |    R$ 815,58
            $event->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currencyCode ?? $matches['currency']);
        }

        // SpentAwards
        $spentAwards = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total:'))}]/ancestor::td[1]", null, true, "/{$this->opt($this->t('Total:'))}\s+([\d\,\.]+\s+PTS)\s+/");

        if (!empty($spentAwards)) {
            $event->price()
                ->spentAwards($spentAwards);
        }

        // EarnedAwards
        // AccountNumbers
        // Status
        if ($this->http->XPath->query("//*[contains(normalize-space(),'reservation is booked and confirmed')]")->length > 0) {
            $event->general()
                ->status('confirmed');
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();
        $flag = false;

        foreach ($this->reBody as $reBody) {
            if (strpos($body, $reBody) !== false) {
                $flag = true;
            }
        }

        if ($flag) {
            foreach ($this->reBody2 as $re) {
                if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        // $this->date = strtotime($parser->getHeader('date'));
        $this->http->FilterHTML = true;

        foreach ($this->reBody2 as $lang=>$re) {
            if ($this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

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

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'expedia') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)) {
                        continue 2;
                    } else {
                        return $code;
                    }
                }
            }
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

    private function normalizeDate($str)
    {
//        $this->logger->debug($str);
        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
            "/^\s*(\d{1,2})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{4})\s*$/u", //28 jan, 2019    |    27 déc. 2022
            "#^\s*([^\d\s]+)\s+(\d{1,2}),\s+(\d{4})\s*$#u", //abr 17, 2019
            "#^\s*(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})\s*$#", //3 de sep de 2021
        ];
        $out = [
            "$2 $3 $4, $1",
            "$1 $2 $3",
            "$2 $1 $3",
            "$1 $2 $3",
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

    private function nextText($field, $root = null): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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
}
