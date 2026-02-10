<?php

namespace AwardWallet\Engine\discar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CarRental extends \TAccountChecker
{
    public $mailFiles = "discar/it-133613968.eml, discar/it-133963749.eml, discar/it-155180398.eml, discar/it-164076077-no.eml, discar/it-167532060.eml";
    public $subjects = [
        // en
        'Your car rental booking DC-',
        // nl
        'Uw boeking van uw huurauto DC-',
        // no
        'Din booking DC-',
        // it
        'La tua prenotazione DC-',
        // pt
        'A sua reserva DC-',
        // fr
        'Votre réservation de voiture DC-',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => ['Driver details'],
        'nl' => ['Details van de bestuurder'],
        'no' => ['Sjåførdetaljer'],
        'it' => ['Dettagli del conducente'],
        'pt' => ['Detalhes do condutor'],
        'fr' => ['Coordonnées du conducteur'],
    ];

    public static $dictionary = [
        "en" => [
            'or similar'     => ['or similar', 'or comparable'],
            'totalPrice'     => ['Total amount', 'Total'],
            'statusPhrases'  => [', your car rental booking in'],
            'statusVariants' => ['confirmed'],
        ],
        "nl" => [
            'Booking number:'      => 'Reserveringsnummer:',
            'Confirmation number:' => 'Bevestigingsnummer:',
            'Supplier:'            => 'Leverancier:',

            'Pick-up'        => 'Ophalen',
            'Address'        => 'Adres',
            'Business hours' => 'Openingstijden',
            'Phone'          => 'Telefoon',

            'Drop-off' => 'Inleveren',

            'Driver details' => 'Details van de bestuurder',
            'Name'           => 'Naam',

            'Car details' => 'Autogegevens',
            'or similar'  => 'of gelijkwaardig',

            'totalPrice'     => 'Totaalsom',
            'statusPhrases'  => [', uw autoverhuur boeking in'],
            'statusVariants' => ['bevestigd'],
            // 'is' => '',
        ],
        "no" => [
            'Booking number:'      => 'Bestillingsnummer:',
            'Confirmation number:' => 'Bekreftelsesnummer:',
            'Supplier:'            => 'Leverandør:',

            'Pick-up'        => 'Henting',
            'Address'        => 'Adresse',
            'Business hours' => 'Arbeidstid',
            'Phone'          => 'Telefon',

            'Drop-off' => 'Levering',

            'Driver details' => 'Sjåførdetaljer',
            'Name'           => 'Navn',

            'Car details' => 'Bildetaljer',
            'or similar'  => 'eller lignende',

            'totalPrice'     => 'Totalsum',
            'statusPhrases'  => [', din bestilling av leiebil i'],
            'statusVariants' => ['bekreftet'],
            'is'             => 'er',
        ],
        "it" => [
            'Booking number:'      => 'Numero di prenotazione:',
            'Confirmation number:' => 'Numero di conferma:',
            'Supplier:'            => 'Fornitore:',

            'Pick-up'        => 'Ritiro',
            'Address'        => 'Indirizzo',
            'Business hours' => 'Orario di apertura',
            'Phone'          => 'Telefono',

            'Drop-off' => 'Riconsegna',

            'Driver details' => 'Dettagli del conducente',
            'Name'           => 'Nome',

            'Car details' => 'Dettagli dell\'auto',
            'or similar'  => 'o simile',

            'totalPrice' => 'Importo totale',
            // 'statusPhrases' => [''],
            // 'statusVariants' => [''],
            // 'is' => '',
        ],
        "pt" => [
            'Booking number:'      => 'Número de reserva:',
            'Confirmation number:' => 'Número de confirmação:',
            'Supplier:'            => 'Fornecedor:',

            'Pick-up'        => 'Levantamento',
            'Address'        => 'Endereço',
            //            'Business hours' => '',
            'Phone'          => 'Telefone',

            'Drop-off' => 'Devolução',

            'Driver details' => 'Detalhes do condutor',
            'Name'           => 'Nome',

            'Car details' => 'Detalhes do carro',
            'or similar'  => 'ou similar',

            'totalPrice'     => 'Total a pagar',
            'statusPhrases'  => [', a sua reserva de aluguer de carro em'],
            'statusVariants' => ['confirmada'],
            'is'             => 'está',
        ],

        "fr" => [
            'Booking number:'             => 'Numéro de réservation:',
            'Confirmation number:'        => 'Numéro de confirmation :',
            'Le fournisseur :'            => 'Fornecedor:',

            'Pick-up'        => 'La prise en charge',
            'Address'        => "L'adresse",
            'Business hours' => 'Les horaires de travail',
            'Phone'          => 'Téléphone',

            'Drop-off' => 'Restitution',

            'Driver details' => 'Coordonnées du conducteur',
            'Name'           => 'Nom',

            'Car details' => 'Information sur le vehicule',
            'or similar'  => 'ou similaire',

            'totalPrice'     => 'Prix total',
            'statusPhrases'  => [', Votre réservation de location à'],
            'statusVariants' => ['confirmée'],
            'is'             => 'est',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@discovercars.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'Discover Cars')]")->length === 0
            && $this->http->XPath->query("//a[contains(@href, 'discovercars.com')]")->length === 0
        ) {
            return false;
        }

        if ($this->assignLang()
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[{$this->eq($this->t('Address'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Driver details'))}]/following::text()[{$this->eq($this->t('Name'))}]")->length > 0
        ) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@discovercars') !== false;
    }

    public function ParseHTML(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';
        $patterns = [
            'phone'         => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]\.?', // Mr. Hao-Li Huang
        ];

        $r = $email->add()->rental();

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('statusPhrases'))}]", null, true, "/{$this->opt($this->t('statusPhrases'))}.*?{$this->opt($this->t('is'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;!?]|$)/i");

        if ($status) {
            $r->general()->status($status);
        }

        $r->general()
            ->confirmation(str_replace(' ', '-', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Confirmation number:'))}\s*([A-Za-z\-\/\d\s]+)\s*$/u")))
            ->traveller(preg_replace("/^(?:[[:alpha:]]{1,3}\.\s+|(?:Mr|Sr|Hr|M)[.\s]+)(.{2,})$/iu", '$1', $this->http->FindSingleNode("//text()[{$this->eq($this->t('Driver details'))}]/following::text()[{$this->eq($this->t('Name'))}]/ancestor::tr[1]/*[2]", null, true, "/^{$patterns['travellerName']}$/u")));

        $company = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Supplier:'))}]/following::text()[normalize-space()][1]");

        if (!empty($company)) {
            $r->setCompany($company);
        }

        $xpathDetails = "//tr[{$this->eq($this->t('Car details'))}]/following::tr[ count(*)=2 and *[1][normalize-space()='' and descendant::img] and *[2][normalize-space()] ][1]";

        $r->car()
            ->image($this->http->FindSingleNode($xpathDetails . "/*[1]/descendant::img[not(contains(@src,'cid:'))]/@src"), false, true)
            ->type($this->http->FindSingleNode($xpathDetails . "/*[2]/descendant::text()[normalize-space()][1]/ancestor::*[{$xpathBold}]"), false, true)
            ->model($this->http->FindSingleNode($xpathDetails . "/*[2]/descendant::text()[{$this->contains($this->t('or similar'))}]"))
        ;

        $phonePickup = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[{$this->eq($this->t('Phone'))}][1]/ancestor::tr[1]/*[2]", null, true, '/^.*\d.*$/');

        if ($phonePickup) {
            $phonesPickup = preg_split('/\s*[,]+\s*/', $phonePickup);

            foreach ($phonesPickup as $pVal) {
                if (preg_match("/^{$patterns['phone']}$/", $pVal)) {
                    $r->pickup()->phone($pVal);

                    break;
                }
            }
        }

        $r->pickup()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[{$this->eq($this->t('Address'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Address'))}\s*(.+)/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][contains(normalize-space(), ':')]")))
        ;

        $pHours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Pick-up'))}]/following::text()[{$this->eq($this->t('Business hours'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Business hours'))}\s*(.+)/");

        if (!empty($pHours)) {
            $r->pickup()->openingHours($pHours);
        }

        $phoneDropoff = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop-off'))}]/following::text()[{$this->eq($this->t('Phone'))}][1]/ancestor::tr[1]/*[2]", null, true, '/^.*\d.*$/');

        if ($phoneDropoff) {
            $phonesDropoff = preg_split('/\s*[,]+\s*/', $phoneDropoff);

            foreach ($phonesDropoff as $pVal) {
                if (preg_match("/^{$patterns['phone']}$/", $pVal)) {
                    $r->dropoff()->phone($pVal);

                    break;
                }
            }
        }

        $r->dropoff()
            ->location($this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop-off'))}]/following::text()[{$this->eq($this->t('Address'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Address'))}\s*(.+)/"))
            ->date($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop-off'))}]/following::text()[normalize-space()][1]/ancestor::tr[1][contains(normalize-space(), ':')]")))
        ;

        $dHours = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Drop-off'))}]/following::text()[{$this->eq($this->t('Business hours'))}][1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Business hours'))}\s*(.+)/");

        if (!empty($dHours)) {
            $r->dropoff()->openingHours($dHours);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('totalPrice'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/', $totalPrice, $matches)) {
            // 470.31 USD
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($matches['currency']);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking number:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Booking number:'))}\s*([A-Z\-\d]+)/"));

        $this->ParseHTML($email);

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

    protected function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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
        foreach ($this->detectLang as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug($date);

        $in = [
            // 8 mei 2022, 20:30    |    27. juni 2022, 11:00
            '/^(\d{1,2})[.\s]*(?:de\s+)?([[:alpha:]]+)[.\s]*(?:de\s+)?(\d{4})[,\s]+(\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/iu',
            // June 26, 2022 AD, 10:00
            '/^([[:alpha:]]+)[.\s]*(\d{1,2})[,\s]+(\d{4})(?:\s*[A-z]+)?[,\s]+(\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)$/iu',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
