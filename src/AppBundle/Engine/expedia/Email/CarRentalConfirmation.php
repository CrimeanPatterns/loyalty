<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRentalConfirmation extends \TAccountChecker
{
    public $mailFiles = "expedia/it-21714944.eml, expedia/it-47045536.eml, expedia/it-65795511.eml";

    public $reSubject = [
        'car rental confirmation',
        'Car rental in',
        // it
        'Conferma noleggio auto Expedia',
        //pt
        'Confirmação do aluguel de carro na Expedia',
        //es
        'Confirmación de renta de auto en Expedia',
        // de
        'Mietwagenbestätigung von Expedia',
        // fr
        'Confirmation de la location d’une voiture sur Expedia',
        'Confirmation de location de voiture Expedia',
        // nl
        'Bevestiging Expedia-autoverhuur:',
    ];
    public $detectBody = [
        'en'   => 'VIEW FULL RESERVATION',
        'it'   => 'VAI ALLA PRENOTAZIONE COMPLETA',
        'pt'   => 'VER RESERVA COMPLETA',
        'es'   => 'VER MÁS',
        'es2'  => 'VER TODA LA RESERVA',
        'de'   => 'VOLLSTÄNDIGE RESERVIERUNG ANZEIGEN',
        'fr'   => 'CONSULTER TOUTE LA RÉSERVATION',
        'fr2'  => 'CONSULTER LA RÉSERVATION COMPLÈTE',
        'nl'   => 'VOLLEDIGE RESERVERING BEKIJKEN',
    ];
    public $date;
    public $subject;
    public $lang = '';
    public static $dictionary = [
        'en' => [
            'Pick-up'  => ['Pick-up'],
            'Drop-off' => ['Drop-off'],
            'welcome'  => ['your car reservation', 'car rental itinerary with you'],
            'Total'    => ['Total', 'Estimated Total'],
            ''         => ['Hours of operation:', ''],
            //            'You earned'                => '',
            //            'Expedia Rewards points'    => '',
        ],
        'it' => [
            'Itinerary'                 => 'N. di itinerario:',
            'Car details'               => 'Dettagli dell\'auto',
            'Reserved for'              => 'Prenotata per:',
            'Hours'                     => 'Orari',
            'Due at car rental counter' => 'Riscosso da Expedia al momento della prenotazione',
            'Confirmation'              => 'N. di conferma:',
            'Total'                     => 'Totale',
            'Pick-up'                   => ['Ritiro'],
            'Drop-off'                  => ['Riconsegna'],
            'welcome'                   => ['la prenotazione del tuo noleggio auto è'],
            //            'You earned'                => '',
            //            'Expedia Rewards points'    => '',
        ],
        'pt' => [
            'Itinerary'                 => 'Nº do itinerário:',
            'Car details'               => 'Detalhes do carro',
            'Reserved for'              => 'Reservado para',
            'Hours'                     => ['horas', 'Horário'],
            'Due at car rental counter' => 'Devido no balcão de aluguel de carro',
            'Confirmation'              => 'Nº de confirmação:',
            'Total'                     => 'Total',
            'Pick-up'                   => ['Retirada'],
            'Drop-off'                  => ['Entrega', 'Devolução'],
            'welcome'                   => ['sua reserva de carro está'],
            //            'You earned'                => '',
            //            'Expedia Rewards points'    => '',
        ],
        'es' => [
            'Itinerary'                 => ['Itinerario', 'Itinerario no.'],
            'Car details'               => 'Detalles del auto',
            'Reserved for'              => ['Reservación para', 'Reserva para'],
            'Hours'                     => 'Horario',
            'Due at car rental counter' => 'A pagar en el mostrador de la arrendadora',
            'Confirmation'              => 'Confirmación #',
            'Total'                     => 'Total',
            'Pick-up'                   => ['Entrega'],
            'Drop-off'                  => ['Devolución'],
            'welcome'                   => ['Tu renta de auto está', 'Información sobre el coche', 'Tu reserva de auto'],
            'You earned'                => 'Obtuviste',
            'Expedia Rewards points'    => 'puntos de Expedia Rewards',
        ],
        'de' => [
            'Itinerary'                 => ['Reiseplannr.'],
            'Car details'               => 'Mietwagendetails',
            'Reserved for'              => 'Reserviert für',
            'Hours'                     => 'Öffnungszeiten',
            'Due at car rental counter' => 'Bei Buchungsabschluss von Expedia berechnet',
            'Confirmation'              => ['Bestätigungsnr', 'Bestätigungsnummer:'],
            'Total'                     => 'Gesamtpreis',
            'Pick-up'                   => ['Abholung'],
            'Drop-off'                  => ['Rückgabe'],
            'welcome'                   => ['Ihre Mietwagenbuchung ist'],
            'You earned'                => 'Sie sammeln',
            'Expedia Rewards points'    => 'Expedia Rewards-Punkte',
        ],
        'fr' => [
            'Itinerary'                 => ['Numéro d’itinéraire:'],
            'Car details'               => 'Détails de la voiture',
            'Reserved for'              => ['Réservation pour', 'Réservation au nom de'],
            'Hours'                     => 'Heures d’ouverture',
            'Due at car rental counter' => 'À payer au comptoir de location de voitures',
            'Confirmation'              => ['Numéro de confirmation:'],
            'Total'                     => 'Total',
            'Pick-up'                   => ['Prise en charge', 'Lieu de prise en charge'],
            'Drop-off'                  => ['Remise', 'Lieu de restitution'],
            'welcome'                   => ['votre réservation de voiture'],
            'You earned'                => ['Vous avez accumulé', 'Vous avez gagné'],
            'Expedia Rewards points'    => ['points Récompenses  Expedia', 'points Expedia Rewards'],
        ],
        'nl' => [
            'Itinerary'                 => ['Reisplannummer:'],
            'Car details'               => 'Autodetails',
            'Reserved for'              => 'Geboekt voor',
            'Hours'                     => ['Openingsuren', 'Openingstijden'],
            'Due at car rental counter' => 'Aangerekend tijdens het afrekenen op Expedia',
            'Confirmation'              => ['Bevestigingsnummer:'],
            'Total'                     => 'Totaal',
            'Pick-up'                   => ['Ophalen'],
            'Drop-off'                  => ['Inleveren'],
            'welcome'                   => ['je autoreservatie'],
            'You earned'                => 'Je hebt',
            'Expedia Rewards points'    => 'Expedia Rewards-punten verzameld',
        ],
    ];
    private $keywords = [
        'foxrewards' => [
            'Fox Rent-A-Car',
        ],
        'sixt' => [
            'Sixt',
        ],
        'payless' => [
            'Payless',
        ],
        'advantagecar' => [//advrent??
            'Advantage',
        ],
        'avis' => [
            'Avis',
        ],
        'ezrentacar' => [
            'EZ Rent A Car',
            'E-Z',
        ],
        'thrifty' => [
            'Thrifty Car Rental',
            'Thrifty',
        ],
        'alamo' => [
            'Alamo',
        ],
        'dollar' => [
            'Dollar',
        ],
        'hertz' => [
            'Hertz',
        ],
        'perfectdrive' => [
            'Budget',
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $date = $this->http->FindSingleNode("(//text()[" . $this->contains('EMLDTL=DATE') . "])[last()]", null, true, "#EMLDTL=DATE(\d{8})-#");

        if (preg_match("#^\s*(\d{4})(\d{2})(\d{2})\s*$#", $date, $m)) {
            $this->date = strtotime($m[3] . '.' . $m[2] . '.' . $m[1]);
        }

        if (empty($this->date)) {
            $this->date = strtotime($parser->getDate());
        }
        $this->subject = $parser->getSubject();

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Expedia') or contains(@alt,'expedia')] | //a[contains(@href,'expedia')]")->length == 0) {
            return false;
        }

        if ($this->http->XPath->query('//text()[' . $this->eq($this->detectBody) . ']')->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Expedia') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expediamail.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email)
    {
        if (($node = $this->http->XPath->query("//text()[{$this->eq($this->t('Packages'))}]/ancestor::tr[1][{$this->contains([$this->t('Rewards'), 'Cruises'])}]"))->length !== 1) {
            if (($node = $this->http->XPath->query("(//text()[{$this->contains($this->t('welcome'))}]/ancestor::tr)[1]"))->length !== 1) {
//                $this->logger->error("(//text()[{$this->contains($this->t('welcome'))}]/ancestor::tr)[1]");
                $this->logger->debug('other format');

                return false;
            }
        }
        $root = $node->item(0);
        $r = $email->add()->rental();

        $confNo = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Itinerary'))}][1]/ancestor::td[1][contains(.,'#') or contains(.,':')])[1]",
            null, false,
            "/{$this->opt($this->t('Itinerary'))}[# ]+([A-Z]{0,2}\d+)/");

        if (!$confNo) {
            $confNo = $this->http->FindSingleNode("(//text()[{$this->contains($this->t("Itinerary"))}][1]/following-sibling::b[contains(.,'#')])[1]",
                null, false, "/[# ]+(\d+)/");
        }

        if (!$confNo) {
            $confNo = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Itinerary"))}][1]/following::text()[normalize-space()][1])[1]",
                null, false, "/^\s*(\d{6,})\s*$/");
        }

        if (empty($confNo) && preg_match("/{$this->opt($this->t("Itinerary"))}\W{1,5}(\d{7,})\b/", $this->subject, $m)) {
            $confNo = $m[1];
        }

        if (!empty($confNo)) {
            $email->ota()
                ->confirmation($confNo);
        }

        // Ota Phone
        $node = trim($this->http->FindSingleNode("//text()[{$this->starts($this->t('Expedia Customer Support Phone Number'))}]/ancestor::tr[normalize-space()!=''][1]",
            null, false, "#{$this->opt($this->t('Expedia Customer Support Phone Number'))}: *([\d\+\-\(\) \.]+)#"));

        if ($node) {
            $email->ota()->phone($node, $this->t('Expedia Customer Support Phone Number'));
        }

        $confNo = $this->http->FindSingleNode("(descendant::text()[{$this->starts($this->t('Confirmation'))}]/ancestor::*[self::tr or self::div][1][contains(.,'#') or contains(.,':')])[1]");

        if (preg_match("/({$this->opt($this->t('Confirmation'))}[ ]*#?)[ ]*(?:EXP\(B\)-)?([A-Za-z\d\-\/_]+)(?: PEXP)?\s*$/", $confNo, $m)) {
            $r->general()->confirmation($m[2], $m[1]);
        } elseif ($this->http->XPath->query("descendant::text()[{$this->starts($this->t('Confirmation'))}]/ancestor::*[self::tr or self::div][1][contains(.,'#') or contains(.,':')]")->length === 0) {
            $r->general()->noConfirmation();
        }

        $r->general()
            ->traveller($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Pick-up'))}][1]/following::text()[normalize-space(.)!=''][position() = 2 or position() = 3][{$this->starts($this->t('Reserved for'))}]",
                $root, false, "#{$this->opt($this->t('Reserved for'))} *(.+)#"));

        if ($status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('welcome'))}]/following-sibling::b")) {
            $r->general()
            ->status($status);
        }

        $r->extra()->company($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Pick-up'))}][1]/following::text()[normalize-space(.)!=''][3]", $root));

        if (!empty($keyword = $r->getCompany())) {
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
//            } else {
//                // error if company not found
//                $r->program()->keyword($keyword);
            }
        }

        if (!empty($keyword)) {
            // 333/122 69 79
            $node = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Contact'))}) and {$this->contains($keyword)}]/following::tr[normalize-space()][position()<3][{$this->contains($this->t('Phone Number'))}]", null, false, "#{$this->opt($this->t('Phone Number'))}: *([+(\d][-. \/\d)(]{5,}[\d)])#");

            if (!empty($node)) {
                $r->program()->phone($node);
            }
        }
        $datePU = $this->normalizeDate($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Pick-up'))}][1]/following::text()[normalize-space(.)!=''][1]",
            $root));
        $dateDO = $this->normalizeDate($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Drop-off'))}][1]/following::text()[normalize-space(.)!=''][1]",
            $root));

        if (($dateDO - $datePU) < 0) {
            $dateDO = strtotime('+1 YEAR', $dateDO);
        }

        $r->pickup()
            ->date($datePU)
            ->location($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Pick-up'))}][1]/following::text()[normalize-space(.)!=''][4]",
                $root, false, "/^(?:{$this->opt($this->t('Car Pickup'))})?\s*(.+)/"))
            ->openingHours($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Pick-up'))}][1]/following::text()[normalize-space(.)!=''][{$this->contains($this->t('Hours'))}][1]/ancestor::tr[1]",
                $root, false, "#: +(.+)#"));
        $r->dropoff()
            ->date($dateDO)
            ->location($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Drop-off'))}][1]/following::text()[normalize-space(.)!=''][3]",
                $root, false, "/^(?:{$this->opt($this->t('Car Pickup'))})?\s*(.+)/"))
            ->openingHours($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Drop-off'))}][1]/following::text()[normalize-space(.)!=''][{$this->contains($this->t('Hours'))}][1]/ancestor::tr[1]",
                $root, false, "#: +(.+)#"));

        $r->car()
            // may be "Your Ford Escape, Nissan Rogue rental will have air conditioning and fit 5 people."
            ->type(implode('; ', $this->http->FindNodes(".//following::text()[{$this->eq($this->t('Car details'))}][1]/following::table[1]/descendant::text()[normalize-space()!=''][position()>1]",
                $root)))
            ->model($this->http->FindSingleNode(".//following::text()[{$this->eq($this->t('Car details'))}][1]/following::table[1]/descendant::text()[normalize-space()!=''][1]",
                $root));

        $cost = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Due at car rental counter'))}][ following::text()[normalize-space()][1][{$this->contains($this->t('Base price'))}] ]/ancestor::table[1]/following::text()[normalize-space()][2]");

        if ($cost === null) {
            $cost = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Due at car rental counter'))}]/following-sibling::tr/descendant::td[{$this->eq($this->t('Base price'))}]/following-sibling::td[normalize-space()]");
        }

        if ($cost !== null) {
            $tot = $this->getTotalCurrency($cost);

            if ($tot['Total'] !== '') {
                $r->price()
                    ->cost($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()!=''][1]"));

        if ($tot['Total'] !== '') {
            $r->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tax = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Due at car rental counter'))}][ following::text()[normalize-space()][1][{$this->contains($this->t('Base price'))}] ]/ancestor::table[1]/following::text()[normalize-space()][3]");

        if ($tax === null) {
            $tax = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Due at car rental counter'))}]/following-sibling::tr/descendant::td[{$this->eq($this->t('Taxes & fees'))}]/following-sibling::td[normalize-space()]");
        }

        if ($tax !== null) {
            $tot = $this->getTotalCurrency($tax);

            if ($tot['Total'] !== '') {
                $r->price()
                    ->tax($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
        $feeRows = $this->http->XPath->query("//tr[ normalize-space() and preceding-sibling::tr[{$this->starts($this->t('Collected at Expedia checkout'))}] and following-sibling::tr[{$this->starts($this->t('Due at car rental counter'))}] ]/descendant-or-self::tr[count(*[normalize-space()])=2]");

        foreach ($feeRows as $feeRow) {
            $fee = $this->getTotalCurrency($this->http->FindSingleNode('*[normalize-space()][2]', $feeRow));

            if ($fee['Total'] !== '') {
                $r->price()->fee($this->http->FindSingleNode('*[normalize-space()][1]', $feeRow), $fee['Total']);
            }
        }
        $node = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Expedia Rewards points used')][1]", null, true, "#^\s*(\d[\d, ]*Expedia Rewards points) used#");

        if (!empty($node)) {
            $r->price()->spentAwards($node);
        }

        $node = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("You earned")) . "][1]");

        if (preg_match("#{$this->opt($this->t('You earned'))} (\d+ {$this->opt($this->t('Expedia Rewards points'))})#", $node, $m)) {
            $email->ota()->earnedAwards($m[1]);
        }

        return true;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Pick-up']) || empty($phrases['Drop-off'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Pick-up'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Drop-off'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#^(?<c>[A-Z]{3}|[^\d)(]+)\s*(?<t>\d[,.\'\d\s]*\d*)#", $node, $m) // C$234.77
            || preg_match("#^(?<t>\d[,.\'\d\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^\D*(?<c>[-]*?)(?<t>\d[,.\'\d\s]*\d*)\s*$#", $node, $m)
            || preg_match("#^(?<t>\d[,.\'\d\s]*\d*)\s*(?<c>\D{1,5})$#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $this->normalizeCurrency($m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currences = [
            // do not add unused currency!
            'USD' => ['US$'],
            'AUD' => ['AU$'],
            'CAD' => ['C$', 'CA $', '$ CA'],
            'EUR' => ['€'],
            'GBP' => ['£'],
            'BRL' => ['R$'],
            'MXN' => ['MXN$'],
            'ARS' => ['AR$'],
        ];

        foreach ($currences as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
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
//        $this->logger->debug('IN - ' . $date);
        $year = date('Y', $this->date);
        $in = [
            //Aug 5, 2018 at 1:00pm
            '#^(\w+) +(\d+),\s+(\d{4})\s*at\s*(\d+:\d+(?:\s*[ap]m)?)$#u',
            //Sun., 5 Jan. at 3:30pm; Mi., 27. Jan. um 11:45 Uhr
            '/^([-[:alpha:]]{2,})[,.\s]+(\d{1,2})[.]?\s+([[:alpha:]]{3,})[,.\s]+(?:at|um)\s*(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)(?:\s*Uhr)?$/u',
            //Sun, Dec 9 at 3:30pm
            '#^(\w+),\s*(\w+)\s+(\d+)\s*at\s*(\d+:\d+(?:\s*[ap]m)?)$#iu',
            //Il giorno dom 16 ago alle ore 10:30
            '/^\s*(?:Il giorno )?([-[:alpha:]]{2,})\s+(\d{1,2})\s+([[:alpha:]]{3,})[,.\s]+alle ore\s*(\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?)$/u',
            //sáb, 26 de dez às 14h00; dom, 7 de fev – 10h00
            '#^\w+,\s*(\d+)\s*\w+\s*(\w+)\s*(?:\w+|\W)\s*(\d+)h(\d+)$#u',

            //lun. 4 de ene a esta hora: 10:00; sáb. 18 de dic a esta hora: 11:00a. m.; dom. 16 de oct. a las 17:00
            '#^\s*([\w\-]+)\.\s*(\d+)\s+de\s+(\w+)\.?\s+.+\b(\d{1,2}:\d{2}(?: *[ap][ .]*m[ .]*)?)\s*$#iu',
            //Tue, 11 Aug at 2:00p
            '#^(\w+),\s*(\d+)\s+(\w+)\s*at\s*(\d+:\d+)\s*([ap])$#',
            // Le ven. 7 oct. à 12 h 30
            '#^(?:Le )?(\w+)\.\s*(\d+)\s+(\w+)\.\s*à\s*(\d+)\s*h\s*(\d+)\s*$#u',
            // vr 31 dec. om 13.00 uur
            '#^(\w+)\s+(\d+)\s+(\w+)\.\s*om\s*(\d+)\s*\.\s*(\d+)\s*uur\s*$#',
        ];
        $out = [
            '$2 $1 $3, $4',
            '$1, $2 $3 ' . $year . ', $4',
            '$1, $3 $2 ' . $year . ', $4',
            '$1, $2 $3 ' . $year . ', $4',
            '$1 $2 ' . $year . ', $3:$4',

            '$1, $2 $3 ' . $year . ', $4',
            '$1, $2 $3 ' . $year . ', $4 $5m',
            '$1, $2 $3 ' . $year . ', $4:$5',
            '$1, $2 $3 ' . $year . ', $4:$5',
        ];
        $str = preg_replace($in, $out, $date);
        $str = preg_replace("/(\d{1,2}:\d{2}) *([ap])[ .]*m[ .]*$/", '$1$2m', $str); // 11:00a. m. -> 11:00am

        if (preg_match('#\d+ ([[:alpha:]]+) \d+#iu', $str, $m)) {
            $monthNameOriginal = $m[1];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                $str = preg_replace("#$monthNameOriginal#i", $translatedMonthName, $str);
            }
        }
//        $this->logger->debug('OUT - ' . $str);

        if (preg_match('/^(?<week>[-[:alpha:]]{2,}), (?<date>\d{1,2} [[:alpha:]]{3,} .+)/u', $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } else {
            $str = strtotime($str);
        }

        return $str;
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                }
            }
        }

        return null;
    }
}
