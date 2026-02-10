<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class RentalCarVoucher extends \TAccountChecker
{
    public $mailFiles = "booking/it-74625795.eml, booking/it-78188022.eml, booking/it-78524575.eml, booking/it-99737133.eml, booking/it-146767966.eml";

    public static $dictionary = [
        'sv' => [ // it-99737133.eml
            // 'Hi' => '',
            'Confirmation:'  => 'Bekräftelse:',
            'or similar'     => 'eller liknande',
            'Pick-up'        => 'Upphämtning',
            'Drop-off'       => 'Avlämning',
            'Driver details' => 'Information om föraren',
            'Rental company' => 'Hyrbilsföretag',
            'Total'          => 'Totalpris',
        ],
        'it' => [
            // 'Hi' => '',
            'Confirmation:'  => 'Conferma:',
            'or similar'     => 'o simile',
            'Pick-up'        => 'Ritiro',
            'Drop-off'       => 'Riconsegna',
            'Driver details' => 'Dati del conducente',
            'Rental company' => 'Compagnia di noleggio',
            'Total'          => 'Totale',
        ],
        'pt' => [ // it-78524575.eml
            'Hi'             => 'Olá',
            'Confirmation:'  => ['Confirmação:', 'Confirmation:'],
            'or similar'     => ['ou similar', 'or similar'],
            'Pick-up'        => ['Retirada:', 'Levantamento'],
            'Drop-off'       => ['Devolução:', 'Devolução'],
            'Driver details' => 'Informações do condutor',
            'Rental company' => ['Locadora', 'Empresa de aluguer'],
            'Total'          => ['Total Price', 'Total', 'Preço total'],
        ],
        'es' => [
            'Hi'             => 'Hola,',
            'Confirmation:'  => 'Confirmación:',
            'or similar'     => 'o similar',
            'Pick-up'        => 'Recogida',
            'Drop-off'       => 'Devolución',
            'Driver details' => 'Datos del conductor',
            'Rental company' => 'Empresa de alquiler',
            'Total'          => ['Precio Total', 'Total'],
        ],
        'da' => [
            'Hi'             => 'Hej',
            'Confirmation:'  => 'Bekræftelse:',
            'or similar'     => 'eller tilsvarende',
            'Pick-up'        => 'Afhentning',
            'Drop-off'       => 'Aflevering',
            'Driver details' => 'Oplysninger om fører',
            'Rental company' => 'Biludlejningsfirma',
            'Total'          => ['Samlet pris', 'I alt'],
        ],
        'en' => [ // it-74625795.eml
            // 'Hi' => '',
            //            'Confirmation:' => '',
            //            'or similar' => '',
            //            'Pick-up' => '',
            //            'Drop-off' => '',
            //            'Driver details' => '',
            //            'Rental company' => '',
            'Total' => ['Total', 'Total Price'],
        ],
        'fr' => [
//            'Hi'             => '',
            'Confirmation:'  => 'Confirmation :',
            'or similar'     => 'ou similaire',
            'Pick-up'        => 'Prise en charge',
            'Drop-off'       => 'Restitution',
            'Driver details' => 'Coordonnées du conducteur',
            'Rental company' => 'Société de location',
            'Total'          => ['Total'],
        ],
    ];

    private $detectFrom = "@cars.booking.com";

    private $detectSubject = [
        'sv' => 'Din hyrbil från ',
        'it' => 'Grazie! Il tuo noleggio con ',
        'en' => 'Thanks! Your car rental with ',
        'Your Booking.com Quotation - Ref:',
        'es' => 'Su presupuesto con Booking.com – Ref:',
        'Gracias! Tu coche de alquiler de',
        'da' => 'Tak! Din billeje hos',
        'pt' => 'Obrigado! Seu aluguel de carro com ',
        'Obrigado! O seu aluguer de carro com ',
    ];

    private $detectBody = [
        "sv" => [
            "Personalen i uthyrningsdisken",
        ],
        "it" => [
            "Dovrai mostrare il tuo voucher al personale al desk",
        ],
        "pt" => [
            "o seu orçamento da sua viagem para:",
            "Confira seu orçamento abaixo",
            'o aluguer do seu carro para',
            'seu aluguel de carro para',
        ],
        "es" => [
            "Tendrás que enseñar el vale de confirmación al personal",
            "Aquí tienes el presupuesto que has pedido.",
        ],
        "da" => [
            "Du skal vise din voucher til personalet",
        ],
        "fr" => [
            "Vous devrez présenter votre bon de réservation au personnel",
        ],
        "en" => [
            "The counter staff will need to see your voucher",
            "Didn’t finish your booking?",
            "you wish to make a confirmed booking",
            "You can find your requested quote below",
            ", here’s your requested quote.",
        ],
    ];

    private $isDetectedJunk = false;
    private $detectBodyJunk = [
        "it" => [
            //            "Dovrai mostrare il tuo voucher al personale al desk",
        ],
        "pt" => [
            "Não concluiu a sua reserva?", "Não concluiu sua reserva?",
            "preço do seu aluguer não é garantido até que o aluguer seja confirmado.",
        ],
        "en" => [ // it-78188022.eml
            //            "The counter staff will need to see your voucher",
            "Didn’t finish your booking?",
            //            "you wish to make a confirmed booking",
            "You can find your requested quote below",
        ],
        "es" => [
            "Puedes encontrar tu presupuesto aquí abajo",
        ],
        "da" => [
            "Du kan finde tilbuddet nedenfor.",
        ],
    ];

    private $providerCode = '';
    private $lang = "en";

    private $rentalProviders = [
        'alamo' => ['Alamo'],
        'avis'  => ['Avis'],
        //        'dollar' => ['Dollar', 'Dollar RTA'],
        //        'europcar' => ['Europcar'],
        //        'hertz' => ['Hertz'],
        //        'localiza' => ['Localiza'],
        'perfectdrive' => ['Budget'],
        'sixt'         => ['Sixt'],
        'thrifty'      => ['Thrifty'],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getHeaders());

        foreach ($this->detectBodyJunk as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;
                $this->isDetectedJunk = true;

                break;
            }
        }

        foreach ($this->detectBody as $lang => $body) {
            if ($this->http->XPath->query('//*[' . $this->contains($body) . ']')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseCar($email, $parser->getSubject());

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($dBody) . ']')->length > 0) {
                return true;
            }
        }

        foreach ($this->detectBodyJunk as $dBody) {
            if ($this->http->XPath->query('//*[' . $this->contains($dBody) . ']')->length > 0) {
                $this->isDetectedJunk = true;

                return true;
            }
        }

        return false;
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return ['rentalcars', 'booking'];
    }

    private function parseCar(Email $email, string $subject): void
    {
        $patterns = [
            'travellerName'   => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
            'travellerNameV2' => '[[:alpha:]][-\'’[:alpha:] ]*[[:alpha:]]',
        ];

        $namePrefixes = ['Miss', 'Sig', 'Sra', 'Herr', 'Mr', 'Ms', 'Hr'];

        $r = $email->add()->rental();

        // General

        $traveller = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Driver details"))}] ]/*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1]", null, true, "/^{$patterns['travellerName']}/u");

        if (!$traveller) {
            $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Hi"))}]", null, true, "/^{$this->preg_implode($this->t("Hi"))}\s+(?:{$this->preg_implode($namePrefixes)}[.\s]+)?({$patterns['travellerNameV2']})(?:[ ]*[,.:;!?]|$)/u");
        }

        if ($traveller) {
            $r->general()->traveller(preg_replace("/^{$this->preg_implode($namePrefixes)}[.\s]+/i", '', $traveller));
        }

        $otaConfirmation = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Confirmation:")) . "]/following::text()[normalize-space()][1]");

        if (!$otaConfirmation && preg_match("/[–\s]+{$this->preg_implode($this->t('Ref:'))}\s*([-A-Z\d]{5,})/u", $subject, $m)) {
            $otaConfirmation = $m[1];
        }

        if ($otaConfirmation) {
            $r->ota()->confirmation($otaConfirmation);
        }

        $companyConfirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Rental company'))}]/following::text()[normalize-space()][1]/ancestor::table[1]", null, true, "/\s(\d+)/");

        if (!empty($companyConfirmation)) {
            $r->general()->confirmation($companyConfirmation);
        } elseif ($otaConfirmation) {
            $r->general()->noConfirmation();
        }

        // Pick up
        $xpathPickUp = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Pick-up"))}] ]/*[normalize-space()][2]";
        $r->pickup()
            ->date($this->normalizeDate($this->http->FindSingleNode($xpathPickUp . "/descendant::tr[not(.//tr) and normalize-space()][1]", null, true, "/^.*\d.*$/")))
            ->location(implode(', ', $this->http->FindNodes($xpathPickUp . "/descendant::tr[not(.//tr) and normalize-space()][position()=2 or position()=3]")))
        ;

        // Drop Off
        $xpathDropOff = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Drop-off"))}] ]/*[normalize-space()][2]";
        $r->dropoff()
            ->date($this->normalizeDate($this->http->FindSingleNode($xpathDropOff . "/descendant::tr[not(.//tr) and normalize-space()][1]", null, true, "/^.*\d.*$/")))
            ->location(implode(', ', $this->http->FindNodes($xpathDropOff . "/descendant::tr[not(.//tr) and normalize-space()][2]")))
        ;

        // Car
        $xpathCar = "//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Pick-up"))}] ]/ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][position()<3]/descendant::*[ count(*)=2 and *[1][descendant::img and normalize-space()=''] and *[2][normalize-space()] ][1]";
        $r->car()
            ->image($this->http->FindSingleNode($xpathCar . "/*[1]/descendant::img[not(contains(@alt,'removed'))]/@src"), false, true)
            ->model($this->http->FindSingleNode($xpathCar . "/*[2]/descendant::tr[not(.//tr) and normalize-space()][1]", null, true, "#.+{$this->preg_implode($this->t("or similar"))}#"))
        ;

        // Company
        $company = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Rental company"))}] ]/*[normalize-space()][2]/descendant::tr[not(.//tr) and normalize-space()][1]");
        $this->logger->debug($company);

        if (!empty($company)) {
            $foundCode = false;

            foreach ($this->rentalProviders as $code => $names) {
                foreach ($names as $name) {
                    if (stripos($name, $company) === 0) {
                        $r->program()->code($code);
                        $foundCode = true;

                        break 2;
                    }
                }
            }

            if ($foundCode === false) {
                $r->extra()->company($company);
            }
        }

        $total = $this->http->FindSingleNode("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Total"))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match("/^(?<currency>[^\d\s]{1,5}?)\s*(?<amount>\d[\d\., ]*?)[\s*]*$/", $total, $matches)
            || preg_match("/^(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})[\s*]*$/", $total, $matches)
        ) {
            // US$142.95
            $currency = $this->currency(trim($matches['currency'], ' *'));
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $r->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        if ($this->isDetectedJunk === true && count(array_filter($r->toArray())) > 6) {
            $email->removeItinerary($r);
            $email->setIsJunk(true);
        }
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@reservations.rentalcars.com') !== false
            || strpos($headers['subject'], 'Rentalcars.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".rentalcars.com/") or contains(@href,"reservations.rentalcars.com")]')->length > 0
        ) {
            $this->providerCode = 'rentalcars';

            return true;
        }

        if ($this->http->XPath->query("//a[contains(@href,'cars.booking.com')]")->length > 0) {
            $this->providerCode = 'booking';

            return true;
        }

        if ($this->http->XPath->query("//a[contains(@href,'.carhire.ryanair.com')]")->length > 0
            || strpos($headers['from'], '.carhire.ryanair.com') !== false
        ) {
            $this->providerCode = 'ryanair';

            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug("Date: {$date}");
        $in = [
            // miércoles, 18 August 2021 18:00
            "#^\s*[\w\-ʼ]+,\s*(\d{1,2})\s+([^\d\s\.\,]+)\s+(\d{4})\s+(\d{1,2}:\d{2})\s*$#ui",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($this->lang === 'pt' && $en = MonthTranslate::translate($m[1], 'br')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function re($re, $str, $c = 1)
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
            'US$'=> 'USD',
            'R$' => 'BRL',
            '€'  => 'EUR',
            '£'  => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
