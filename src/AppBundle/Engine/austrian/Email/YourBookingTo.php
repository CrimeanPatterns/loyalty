<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class YourBookingTo extends \TAccountChecker
{
    public $mailFiles = "austrian/it-105555992.eml, austrian/it-105709946.eml, austrian/it-105721246.eml, austrian/it-105794824.eml, austrian/it-174464468.eml, austrian/it-318036397.eml, austrian/it-324031359.eml";
    public $subjects = [
        // en
        'Get ready for your upcoming trip to',
        'Your booking to',
        'Your upgrade for your booking to',
        // de
        'Your ancillary service for your booking to',
        'Alles Wichtige zu Ihrem bevorstehenden Flug nach',
        'hre Buchung nach',
        // fr
        'Votre réservation à destination de',
    ];

    public $lang = 'en';

    public $detectLang = [
        'de' => ['Hinflug', 'Buchungscode'],
        'ru' => ['Рейс'],
        'fr' => ['Vol aller', 'Vol retour'],
        'en' => ['Outbound flight'], //last
    ];

    public static $dictionary = [
        "en" => [
            'Your journey starts soon!' => [
                'Your journey starts soon!',
                'We are looking forward to welcoming you on board soon!',
                'Thank you for booking your ancillary service!',
                'Your flight has been rebooked',
                'We hereby confirm your upgrade.',
            ],
            //            'Outbound flight'           => ['Outbound flight', 'Outbound Flight'],
            'Inbound flight'            => ['Inbound flight', 'Inbound Flight'],
            'Booking code:'             => 'Booking code:',
            //            'Seat'           => '',
            //            'Fare' => '',
            //            'Taxes and surcharges' => '',
        ],
        "de" => [
            'Your journey starts soon!' => ['Bald geht es los!', 'Wir freuen uns Sie bald an Bord begrüßen zu dürfen!'],
            'MANAGE BOOKING'            => 'BUCHUNG VERWALTEN',
            'Dear '                     => 'Lieber ',
            'Passengers and services'   => 'Passagiere und Services',
            'Outbound flight'           => 'Hinflug',
            'Inbound flight'            => 'Rückflug',
            'Booking code:'             => 'Buchungscode:',
            'Booking Class:'            => 'Buchungsklasse:',
            'operated by'               => 'durchgeführt von',
            'Flight to '                => 'Flug nach ',
            'Grand total'               => 'Gesamtbetrag',
            'Ticket number:'            => 'Ticketnummer:',
            'Seat'                      => 'Sitzplatz',
            'Fare'                      => 'Flugpreis',
            'Taxes and surcharges'      => 'Steuern und Gebühren',
        ],
        "ru" => [
            'Your journey starts soon!' => ['Your journey starts soon!'],
            'MANAGE BOOKING'            => 'УПРАВЛЕНИЕ БРОНИРОВАНИЕМ',
            'Dear '                     => 'Уважаемый ',
            //'Passengers and services' => '',
            'Outbound flight' => 'Рейс "туда"',
            'Inbound flight'  => 'Рейс "обратно"',
            'Booking code:'   => 'Номер бронирования:',
            //'Booking Class:' => '',
            'operated by' => 'выполняется',
            'Flight to '  => 'Рейс Куда ',
            //'Grand total' => '',
            //'Ticket number:' => '',
            //            'Seat'           => '',
            //            'Fare' => '',
            //            'Taxes and surcharges' => '',
        ],
        "fr" => [
            'Your journey starts soon!' => ['Merci beaucoup pour votre réservation!'],
            'MANAGE BOOKING'            => 'GÉRER VOTRE RÉSERVATION',
            //            'Dear '                     => 'Уважаемый ',
            'Passengers and services' => 'Passagers et services',
            'Outbound flight'         => 'Vol aller',
            'Inbound flight'          => 'Vol retour',
            'Booking code:'           => 'Code de réservation:',
            'Booking Class:'          => 'Classe de réservation:',
            'operated by'             => 'opéré par',
            'Flight to '              => 'Vol à ',
            'Grand total'             => 'Prix total',
            'Ticket number:'          => 'Numéro de billet:',
            'Seat'                    => ['Siège offrant plus d’espace pour les jambes', 'Siège en zone préférentielle'],
            'Fare'                    => 'Tarif',
            'Taxes and surcharges'    => 'Taxes et frais',
        ],
        "es" => [
            'Your journey starts soon!' => ['¡Gracias por su reserva!'],
            'MANAGE BOOKING'            => 'GESTIONAR SU RESERVA',
            //'Dear '                     => '',
            'Passengers and services'   => 'Pasajeros y servicios',
            'Outbound flight'           => 'Vuelo de ida',
            //'Inbound flight'            => '',
            'Booking code:'             => 'Código de reserva:',
            'Booking Class:'            => 'Clase de reserva:',
            'operated by'               => 'operado por',
            'Flight to '                => 'Vuelo a ',
            'Grand total'               => 'Precio total',
            'Ticket number:'            => 'Número de billete:',
            'Seat'                      => 'Asiento',
            'Fare'                      => 'Impuestos y tasas',
            'Taxes and surcharges'      => 'Tasa por emisión del billete',
        ],
        "pl" => [
            'Your journey starts soon!' => ['Dziękujemy za rezerwację!'],
            'MANAGE BOOKING'            => 'ZARZĄDZANIE REZERWACJĄ',
            //'Dear '                     => '',
            'Passengers and services'   => 'Pasażerowie i usługi',
            'Outbound flight'           => 'Wylot',
            'Inbound flight'            => 'Lot powrotny',
            'Booking code:'             => 'Kod Rezerwacji:',
            'Booking Class:'            => 'Klasa rezerwacyjna:',
            'operated by'               => 'obsługiwany przez',
            'Flight to '                => 'Lot do ',
            'Grand total'               => 'Cena całkowita',
            'Ticket number:'            => 'Numer biletu:',
            //'Seat'                      => '',
            'Fare'                            => 'Taryfa',
            'Taxes and surcharges'            => 'Podatki i opłaty',
            'on behalf of'                    => 'w imieniu',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@notifications.austrian.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (strpos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('(//a[contains(@href,".austrian.com/") or contains(@href,"www.austrian.com")]) | (//img[contains(@src, "AustrianAirlines")])')->length === 0
            || $this->http->XPath->query('//*[contains(.,"www.austrian.com") or contains(., "no-reply@notifications.austrian.com") or contains(., "Austrian Airlines AG")]')->length === 0
        ) {
            return false;
        }

        if ($this->assignLang()) {
            return
                $this->http->XPath->query("//node()[{$this->contains($this->t('MANAGE BOOKING'))}] | //img[{$this->eq($this->t('MANAGE BOOKING'), 'normalize-space(@alt)')}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your journey starts soon!'))}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($this->t('operated by'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]notifications\.austrian\.com$/', $from) > 0;
    }

    public function ParseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking code:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking code:'))}\s*([A-Z\d]+)/"));

        $travellers = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers and services'))}]/following::img[contains(@src, 'people')]/following::text()[normalize-space()][1]");

        if (empty($travellers)) {
            $travellers = array_filter([$this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear '))}]", null, true, "/{$this->opt($this->t('Dear '))}\s*\b(\D+)\!/u")]);
        }

        if (!empty($travellers)) {
            $f->general()
                ->travellers(preg_replace("/^\s*(?:Herr|Mr\.|Mrs\.|Mrsdr\.)?\s+/", '', $travellers));
        }

        // Issued
        $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('Ticket number:'))}]", null, "/{$this->opt($this->t('Ticket number:'))}\s*(\d+)/");

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique(array_filter($tickets)), false);
        }

        // Segments
        $xpath = "//text()[{$this->starts($this->t('Flight to '))} or {$this->starts($this->t('Leg by train to '))}]/ancestor::tr[1]/following::tr[normalize-space()][1]/descendant::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));

        foreach ($nodes as $root) {
            $type = 'flight';

            if (!empty($this->http->FindSingleNode("preceding::text()[{$this->starts($this->t('Flight to '))} or {$this->starts($this->t('Leg by train to '))}][1]",
                $root, null, "/{$this->opt($this->t('Leg by train to '))}/"))
            ) {
                if (!isset($t)) {
                    $t = $email->add()->train();

                    $confs = array_column($f->getConfirmationNumbers(), 0);

                    if (!empty($confs)) {
                        foreach ($confs as $conf) {
                            $t->general()
                                ->confirmation($conf);
                        }
                    }
                    $travellers = array_column($f->getTravellers(), 0);

                    if (!empty($travellers)) {
                        $t->general()
                            ->travellers($travellers);
                    }
                }
                $type = 'train';
                $s = $t->addSegment();
            } else {
                $s = $f->addSegment();
            }

            $airlineInfo = $this->http->FindSingleNode("./following::tr[6]", $root);

            if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<number>\d+)\s*(?<depCode>[A-Z]{3})[\s\-]+(?<arrCode>[A-Z]{3})\s*{$this->opt($this->t('operated by'))}\s*(?<operated>.+?)(?:\s+im Auftrag von .*)?$/", $airlineInfo, $m)) {
                if ($type === 'train') {
                    $s->extra()
                        ->number($m['name'] . $m['number']);
                } else {
                    $s->airline()
                        ->name($m['name'])
                        ->number($m['number']);

                    if (!empty($m['operated'])) {
                        if (stripos($m['operated'], $this->t('on behalf of')) !== false) {
                            $m['operated'] = $this->re("/^.+\s*{$this->t('on behalf of')}\s+(.+)/", $m['operated']);
                        }

                        $s->airline()
                            ->operator($m['operated']);
                    }
                }

                $s->departure()
                    ->code($m['depCode']);

                $s->arrival()
                    ->code($m['arrCode']);
            }

            $s->departure()
                ->name(implode(', ', array_filter([
                    $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2][count(*[normalize-space()])=2]/*[normalize-space()][1]", $root),
                    $this->http->FindSingleNode("following-sibling::tr[normalize-space()][3][count(*[normalize-space()])=2]/*[normalize-space()][1]", $root),
                ])));

            $s->arrival()
                ->name(implode(', ', array_filter([
                    $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2][count(*[normalize-space()])=2]/*[normalize-space()][2]", $root),
                    $this->http->FindSingleNode("following-sibling::tr[normalize-space()][3][count(*[normalize-space()])=2]/*[normalize-space()][2]", $root),
                ])));

            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root, true, "/(\d+\.\d+\.\d{4})/");
            $depTime = $this->http->FindSingleNode("./descendant::td[1]", $root);

            if (!empty($depTime) && !empty($date)) {
                $s->departure()
                    ->date(strtotime($date . ', ' . $depTime));
            }

            $arrTime = $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/^\s*([\d\:]+)/");

            if (!empty($arrTime) && !empty($date)) {
                $s->arrival()
                    ->date(strtotime($date . ', ' . $arrTime));
                $overnight = $this->http->FindSingleNode("./descendant::td[3]", $root, true, "/^\s*\d+:\d+\s*([-+] ?\d+)\D*/");

                if (!empty($overnight) && !empty($s->getArrDate())) {
                    $s->arrival()
                        ->date(strtotime($overnight . ' days', $s->getArrDate()));
                }
            }

            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::td[4]", $root));

            $cityDep = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][2][count(*[normalize-space()])=2]/*[normalize-space()][1]", $root);
            $route = array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers and services'))}]/following::tr[ *[2][{$this->starts($cityDep)}] ]",
                null, "/^\s*{$this->opt($cityDep)}\s*\({$s->getDepCode()}\)\s*−\s*.*?\s*\({$s->getArrCode()}\)\s*/")));

            if (count($route) == 1) {
                $route = array_shift($route);
                $blockXpath = "//text()[normalize-space() = '{$route}']/following::text()[normalize-space()][1]/ancestor::*[not(contains(normalize-space(), '{$route}'))][last()]";
                $bookingCodes = array_unique($this->http->FindNodes($blockXpath . "//text()[{$this->starts($this->t('Booking Class:'))}][1]",
                    null, "/{$this->opt($this->t('Booking Class:'))}\s*([A-Z]{1,2})$/"));

                if (count($bookingCodes) == 1) {
                    $s->extra()->bookingCode($bookingCodes[0]);
                }

                $cabin = array_unique($this->http->FindNodes($blockXpath . "//text()[{$this->starts($this->t('Booking Class:'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]"));

                if (count($cabin) == 1) {
                    $s->extra()
                        ->cabin($cabin[0]);
                }

                $seats = array_filter($this->http->FindNodes($blockXpath . "//text()[{$this->starts($this->t('Seat'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]",
                    null, "/{$this->opt($this->t("Seat"))}\s+(\d{1,3}[A-Z])\s*(?:,|$)/"));

                if (empty($seats)) {
                    $depName = $this->re("/^(.+\))\s+\−/u", $route);
                    $arrName = $this->re("/\−\s+(.+\))$/u", $route);

                    $seats = $this->http->FindNodes("//text()[{$this->starts($depName)} and {$this->contains($arrName)}]/ancestor::tr[2]/descendant::text()[starts-with(normalize-space(), 'Booked services:')]/ancestor::tr[1]", null, "/seat\s*(\d{1,2}[A-Z])/");
                }

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats);
                }
            }
        }

        // Price
        $total = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Grand total'))}])[1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Grand total'))}\s*[A-Z]{3}\s*([\d\.\,]+)/");
        $currency = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Grand total'))}])[1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Grand total'))}\s*([A-Z]{3})\s*/");

        if (!empty($total) && !empty($currency)) {
            $email->price()
                ->total(PriceHelper::parse($total, $currency))
                ->currency($currency);

            if ($this->http->XPath->query("//text()[{$this->eq($this->t('Grand total'))}]")->length > 1) {
                // checking that the letter is not truncated
                $fares = $this->http->FindNodes("//text()[{$this->eq($this->t("Fare"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
                $fare = 0.0;

                foreach ($fares as $text) {
                    if (preg_match("/^\s*{$currency}\s*(\d[\d., ]*)\s*$/", $text, $m)) {
                        $fare += PriceHelper::parse($m[1], $currency);
                    }
                }

                if (!empty($fare)) {
                    $email->price()
                        ->cost($fare);
                }
                $taxes = $this->http->FindNodes("//text()[{$this->eq($this->t("Taxes and surcharges"))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
                $tax = 0.0;

                foreach ($taxes as $text) {
                    if (preg_match("/^\s*{$currency}\s*(\d[\d., ]*)\s*$/", $text, $m)) {
                        $tax += PriceHelper::parse($m[1], $currency);
                    }
                }

                if (!empty($tax)) {
                    $email->price()
                        ->tax($tax);
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $words) {
            if (!empty($words['Booking code:']) && $this->http->XPath->query("//text()[{$this->contains($words['Booking code:'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) use ($text) {
            return $text . "='{$s}'";
        }, $field)) . ')';
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
