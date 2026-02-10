<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OverviewRide extends \TAccountChecker
{
    public $mailFiles = "expedia/it-224264176.eml, expedia/it-28525378.eml, expedia/it-30503162.eml, expedia/it-33801228.eml, expedia/it-6562568.eml";

    public static $headers = [
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'Orbitz travel confirmation',
            ],
        ],
        'lastminute' => [
            'from' => ['email.lastminute.com.au'],
            'subj' => [
                'lastminute.com.au travel confirmation',
            ],
        ],
        'travelocity' => [
            'from' => ['e.travelocity.com'],
            'subj' => [
                'Travelocity travel confirmation',
            ],
        ],
        'ebookers' => [
            'from' => [
                'mailer.ebookers.com',
                'mailer.ebookers.fi',
            ],
            'subj' => [
                'ebookers travel confirmation',
                'ebookers-Reisebestätigung -',
                'ebookersn matkavahvistus -',
                'Votre confirmation de voyage ebookers -',
            ],
        ],
        'cheaptickets' => [
            'from' => ['cheaptickets.com'],
            'subj' => [
                'CheapTickets travel confirmation',
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
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                "Expedia travel confirmation",
                "Conferma di viaggio Expedia",
                "Confirmación de viaje de Expedia - ",
                'Expedia-Reisebestätigung',
                "nl" => 'Reisbevestiging van Expedia',
                "pt" => "Confirmação de viagem da Expedia -",
                "fr" => "Votre confirmation de voyage Expedia",
                "sv" => "Resebekräftelse från Expedia",
                "zh" => "Expedia 智遊網 行程確認",
            ],
        ],
    ];

    public $lang = "en";
    public $lastArrivePoint = '';
    public static $dictionary = [
        "en" => [],
        "nl" => [
            "Reservation dates" => "Boekingsdatums",
            //            "Itinerary #" => "",
            "Supplier reference #" => "Leveranciersreferentie",
            "Reserved for"         => "Geboekt voor",
            //            "adult" => "",
            //            "child" => "",
            "Total:"                   => "Totaal:",
            "All prices are quoted in" => "Alle prijzen worden vermeld in",
            "Pick-up"                  => "Ophalen",
            "Drop-off"                 => "Inleveren",
            "Flight"                   => "Vlucht",
            "arrival"                  => "uur aankomst",
            "departure"                => "uur vertrek",
        ],
        "pt" => [
            "Reservation dates" => "Datas da reserva",
            //            "Itinerary #" => "",
            "Supplier reference #" => "Nº de referência do fornecedor",
            "Reserved for"         => "Reservado para",
            //            "adult" => "",
            //            "child" => "",
            "Total:"                   => "Total:",
            "All prices are quoted in" => "Todos os preços foram cotados em",
            "Pick-up"                  => "Retirada",
            "Drop-off"                 => "Entrega",
            "Flight"                   => "Voo",
            "arrival"                  => "chegada",
            "departure"                => "partida",
        ],
    ];

    protected $code = null;

    protected $bodies = [
        'lastminute' => [
            '//img[contains(@alt,"lastminute.com")]',
            '//a[contains(.,"lastminute.com")]/parent::*[contains(.,"Collected by")]',
        ],
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
            'Maksun veloittaa ebookers',
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
            'This Orbitz Itinerary was sent from',
            'Call Orbitz customer care',
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
        'lastminute',
    ];

    private $detectBody = [
        "en" => ["Ride overview"],
        "nl" => ["Vervoersoverzicht"],
        "pt" => ["Visão geral do traslado"],
    ];

    private $emailCurrency = '';

    private $year;

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

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $first = false;

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false) {
                $first = true;
            }
        }

        if (!$first) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($this->http->Response['body'], $dBody) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (stripos($this->http->Response['body'], $dBody) !== false) {
                    $this->lang = trim($lang, '1234567890');

                    break;
                }
            }
        }
        $this->date = strtotime($parser->getHeader('date'));

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }

        $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Total:")) . " or contains(normalize-space(.),'Total:')][1]/ancestor::table[1]//text()[normalize-space(.)]"));

        if (empty($totalText)) {
            $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Total:")) . " or normalize-space(.)='Total'][1]/ancestor::table[1]//text()[normalize-space(.)]"));
        }
        $reCurrency = (array) $this->t("#reCurrency#");

        foreach ($reCurrency as $re) {
            if (preg_match($re, $totalText, $m) && !empty($m[1])) {
                $this->emailCurrency = $this->normalizeCurrency($m[1]);

                break;
            }
        }

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
        return array_keys(self::$headers);
    }

    private function parseHtml(Email $email): void
    {
        // Travel Agency
        $email->obtainTravelAgency();
        $TAconf = trim($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Itinerary #")) . "]/following::text()[normalize-space(.)][1])[1]"), ')( ');

        if (!empty($TAconf)) {
            $email->ota()->confirmation($TAconf);
        }

        if (!$this->year = $this->re("#\D(\d{4})(?:\D|$)#", $this->nextText($this->t("Reservation dates")))) {
            $this->logger->debug('year not matched!');

            return;
        }

        // TRANSFER
        $t = $email->add()->transfer();

        // General
        $conf = $this->nextText($this->t("Supplier reference #"));

        if ($conf !== $TAconf) {
            $t->general()->confirmation($conf);
        } else {
            $t->general()->noConfirmation();
        }
        $t->general()->traveller($this->re("#(.*?)\s*,#", $this->nextText($this->t("Reserved for"))));

        // Price
        $total = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Total:")) . "]", null, true, "#:\s*(.+)#");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//p[" . $this->starts($this->t("Total:")) . "]", null, true, "#:\s*(.+)#");
        }

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//span[" . $this->starts($this->t("Trip Total:")) . "]", null, true, "#:\s*(.+)#");
        }

        if (preg_match('/(?<points>\d[,.\'\d ]*) PTS and (?<currency>[^\-\d)(]+?)[ ]*(?<total>\d[,.\'\d ]*?) Trip Total: [^\-\d)(]+?[ ]*(?<cost>\d[,.\'\d ]*)/', $total, $matches)) {
            // ??
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $t->price()
                ->cost(PriceHelper::parse($matches['cost'], $currencyCode))
                ->total(PriceHelper::parse($matches['total'], $currencyCode))
                ->spentAwards($matches['points'])
                ->currency($currency);
        } elseif (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $total, $matches)) {
            // R$ 163,34    |    CA $109.61
            $currency = !empty($this->emailCurrency) ? $this->emailCurrency : $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $t->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
        }

        // Segments
        $xpath = "//text()[" . $this->eq($this->t("Pick-up")) . "]/ancestor::tr[./td[3]][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            // Departure
            $name = $this->nextText($this->t("Pick-up"), $root, 2);
            $country = '';

            if ($name == 'Airport') {
                $name = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t('Pick-up'))}])[1]/following::text()[normalize-space(.)][3][{$this->eq($this->t('map'))}]/ancestor::a[1]/@href",
                    $root, true, "/google\.com\/maps\/place\/([A-Z]{3})\s*$/");
                $s->departure()
                    ->code($name);
            }

            if (stripos($name, '(') !== false) {
                $country = trim($this->re("/\,([A-z\s]+)\(/", $name));
            }

            if (!empty($this->lastArrivePoint) && stripos($this->lastArrivePoint, $name) !== false) {
                $s->departure()
                    ->name($this->lastArrivePoint);
            } else {
                $s->departure()
                    ->name($name);
            }

            if (preg_match("/^.+ \(([A-Z]{3})-.+\)\s*$/", $name, $m)) {
                $s->departure()
                    ->code($m[1]);
            }

            $node = $this->nextText($this->t("Pick-up"), $root);
            $depDate = null;

            if (preg_match("#\d+:\d+#", $node)) {
//                $s->departure()->date($this->normalizeDate($node));
                $depDate = $this->normalizeDate($node);
            } else {
                $date = $this->normalizeDate($node); //it-6562568.eml
                $node = $this->http->FindSingleNode("./td[{$this->contains($this->t("Pick-up"))}][1]/descendant::text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space()!=''][1]", $root);

                if (preg_match("#\- (.+?) {$this->t('arrival')}$#", $node, $m)) {
                    $flightTime = $this->normalizeDate($m[1]);
                    $s->departure()->date(strtotime("+ 30 minutes", $flightTime));
                } elseif ($date && preg_match("#, (\d+:\d+(?:\s*[ap]m)) {$this->t('arrival')}$#i", $node, $m)) {
                    $flightTime = strtotime($m[1], $date);
                    $s->departure()->date(strtotime("+ 30 minutes", $flightTime));
                } else {
                    $s->departure()->noDate();
                }
            }

            // Arrival
            $name = $this->nextText($this->t("Drop-off"), $root);

            if ($name == 'Airport') {
                $name = $this->http->FindSingleNode("(.//text()[{$this->eq($this->t('Drop-off'))}])[1]/following::text()[normalize-space(.)][2][{$this->eq($this->t('map'))}]/ancestor::a[1]/@href",
                    $root, true, "/google\.com\/maps\/place\/([A-Z]{3})\s*$/");
                $s->arrival()
                    ->code($name);
            }

            if (!empty($country)) {
                $s->arrival()
                    ->name($country . ', ' . $name);
                $this->lastArrivePoint = $country . ', ' . $name;
            } else {
                $s->arrival()
                    ->name($name);
                $this->lastArrivePoint = '';
            }

            if (preg_match("/^.+ \(([A-Z]{3})-.+\)\s*$/", $name, $m)) {
                $s->arrival()
                    ->code($m[1]);
            }

            $node = $this->http->FindSingleNode("./td[{$this->contains($this->t("Drop-off"))}][1]/descendant::text()[{$this->eq($this->t('Flight'))}]/following::text()[normalize-space()!=''][1]", $root);

            if (preg_match("#\- (.+?) {$this->t('departure')}$#", $node, $m)) {
                $flightTime = $this->normalizeDate($m[1]);
                $s->arrival()->date(strtotime("-2 hours", $flightTime));
            } elseif ($date && preg_match("#, (\d+:\d+(?:\s*[ap]m)) {$this->t('departure')}$#i", $node, $m)) {
                $flightTime = strtotime($m[1], $date);
                $s->arrival()->date(strtotime("-2 hours", $flightTime));
            } else {
                $s->arrival()->noDate();
            }

            if (!empty($s->getArrDate()) && empty($s->getDepDate()) && !empty($depDate)) {
                if (($s->getArrDate() - $depDate) < 60 * 60 * 24 && $s->getArrDate() - $depDate > 0) {
                    $s->departure()->date($depDate);
                } else {
                    $s->departure()->noDate();
                }
            } elseif (!empty($depDate)) {
                $s->departure()->date($depDate);
            }

            // Extra
            $s->extra()
                ->adults($this->re("#\b(\d{1,2})[ ]*" . $this->preg_implode($this->t("adult")) . "#i", $this->nextText($this->t("Reserved for"))), true, true)
                ->kids($this->re("#\b(\d{1,2})[ ]*" . $this->preg_implode($this->t("child")) . "#i", $this->nextText($this->t("Reserved for"))), true, true);
        }
    }

    private function getProvider(PlancakeEmailParser $parser)
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
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
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
        $this->logger->debug('$str = ' . $str);
        $in = [
            "/^([[:alpha:]]+)\s+(\d{1,2})$/u", // Jul 25
            "/^([-[:alpha:]]+),?\s+([[:alpha:]]+)\s+(\d{1,2})\.?$/u", // Thu, Jul 25
            "/^([-[:alpha:]]+)\.?,?\s+(\d{1,2})\s+([[:alpha:]]+)\.?$/u", // Thu, 7 Mar    |    Mon., 1 Jul.

            "/^([-[:alpha:]]+)\.?,?\s+(\d{1,2})\s+([[:alpha:]]+)\.?,\s*(\d{1,2})[:.h](\d{2}(?:\s*[ap]m)?)$/iu", // Thu, 7 Mar, 4:00 pm    |    Tue., 25 Jun., 2:00pm    |    wo 31 okt., 10.00    |    qui, 10 jan, 17h00
            "/^([-[:alpha:]]+),?\s+([[:alpha:]]+)\s+(\d{1,2})\.?,[ ]*(\d{1,2}:\d{2}\s*[ap]m)$/iu", // Thu, Jul 25, 2:00pm
            "/^([-[:alpha:]]+)\.?,?\s+(\d{1,2})\s+de\s*([[:alpha:]]+)\.?,\s*(\d{1,2})[:.h](\d{2}(?:\s*[ap]m)?)$/iu", // dom, 13 de set, 18h00
            "/^(\w+)\,\s*(\w+)\s*(\d+)\,\s*([\d\:]+)\s*([ap])\.(m)\.$/", //Wed, Jan 24, 3:30 p.m.
        ];
        $out = [
            "$2 $1 {$this->year}",
            "$3 $2 {$this->year}",
            "$2 $3 {$this->year}",

            "$2 $3 {$this->year}, $4:$5",
            "$3 $2 {$this->year}, $4",
            "$2 $3 {$this->year}, $4:$5",
            "$1, $3 $2 {$this->year}, $4 $5$6",
        ];
        $outWeek = [
            '',
            '$1',
            '$1',

            '$1',
            '$1',
            '$1',

            '$1',
        ];
        //$this->logger->debug($this->lang);
        if (!empty($week = preg_replace($in, $outWeek, $str))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = preg_replace($in, $out, $str);

            if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
                if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                    $str = str_replace($m[1], $en, $str);
                }
            }
//            $this->logger->warning($str);
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = preg_replace($in, $out, $str);

            if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
                if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                    $str = str_replace($m[1], $en, $str);
                }
            }
//            $this->logger->warning($str);
            $str = strtotime($str);
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

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'CAD' => ['$C', 'C$', '$CA', 'CA $'],
            'BRL' => ['R$'],
            'SGD' => ['SG$'],
            'HKD' => ['HK$'],
            'AUD' => ['AU$'],
            'MYR' => ['RM'],
            'THB' => ['฿'],
            'MXN' => ['MXN$'],
            'JPY' => ['円'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[{$this->eq($field)}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
