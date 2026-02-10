<?php

namespace AwardWallet\Engine\avis\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class OffersWithStatement extends \TAccountChecker
{
    public $mailFiles = "avis/statements/it-70963007.eml, avis/statements/it-71024330.eml, avis/statements/it-71038385.eml, avis/statements/it-71038440.eml, avis/statements/it-71429858.eml, avis/statements/it-71990185.eml, avis/statements/it-71260594.eml, avis/statements/it-71239266.eml";

    public $lang = '';

    public static $dictionary = [
        'el' => [ // it-71260594.eml
            'customerNo' => ['αριθμοσ πελατη', 'ΑΡΙΘΜΟΣ ΠΕΛΑΤΗ', 'Αριθμοσ πελατη'],
            'spend'      => ['ποσο που δαπανηθηκε', 'ΠΟΣΟ ΠΟΥ ΔΑΠΑΝΗΘΗΚΕ', 'Ποσο που δαπανηθηκε'],
            'rentals'    => ['ενοικιασεισ', 'ΕΝΟΙΚΙΑΣΕΙΣ', 'Ενοικιασεισ'],
        ],
        'sv' => [ // it-71429858.eml
            'customerNo' => ['kundnummer', 'KUNDNUMMER', 'Kundnummer'],
            'spend'      => ['spenderat', 'SPENDERAT', 'Spenderat'],
            'rentals'    => ['uthyrningar', 'UTHYRNINGAR', 'Uthyrningar'],
        ],
        'no' => [ // it-71239266.eml
            'customerNo' => ['kundenummer', 'KUNDENUMMER', 'Kundenummer'],
            'spend'      => ['forbruk', 'FORBRUK', 'Forbruk'],
            'rentals'    => ['leieforhold', 'LEIEFORHOLD', 'Leieforhold'],
        ],
        'de' => [ // it-71990185.eml
            'customerNo' => ['kundennummer', 'KUNDENNUMMER', 'Kundennummer'],
            'spend'      => ['treueumsatz', 'TREUEUMSATZ', 'Treueumsatz'],
            'rentals'    => [
                'mieten', 'MIETEN', 'Mieten',
                'rentals', 'RENTALS', 'Rentals',
            ],
        ],
        'fr' => [ // it-71038440.eml
            'customerNo' => [
                'numéro client', 'NUMÉRO CLIENT', 'Numéro client',
                'numéro de client', 'NUMÉRO DE CLIENT', 'Numéro de client',
            ],
            'spend'   => ['montant dépensé', 'MONTANT DÉPENSÉ', 'Montant dépensé'],
            'rentals' => ['locations', 'LOCATIONS', 'Locations'],
        ],
        'nl' => [ // it-71038385.eml
            'customerNo' => ['klantnummer', 'KLANTNUMMER', 'Klantnummer'],
            'spend'      => ['uitgaven', 'UITGAVEN', 'Uitgaven'],
            'rentals'    => ['huren', 'HUREN', 'Huren'],
        ],
        'it' => [ // it-71024330.eml
            'customerNo' => ['codice cliente', 'CODICE CLIENTE', 'Codice cliente'],
            'spend'      => ['spesa', 'SPESA', 'Spesa'],
            'rentals'    => ['noleggi', 'NOLEGGI', 'Noleggi'],
        ],
        'en' => [ // it-70963007.eml
            'customerNo' => ['customer no', 'CUSTOMER NO', 'Customer no'],
            'spend'      => ['spend', 'SPEND', 'Spend'],
            'rentals'    => ['rentals', 'RENTALS', 'Rentals'],
        ],
        'da' => [
            'customerNo' => ['Kundenummer', 'kundenummer', 'KUNDENUMMER'],
            'spend'      => ['Brug', 'brug', 'BRUG'],
            'rentals'    => ['Lejemål', 'lejemål', 'LEJEMÅL'],
        ],
        'es' => [
            'customerNo' => ['Número de cliente', 'número de cliente', 'NÚMERO DE CLIENTE'],
            'spend'      => ['Gasto', 'gasto', 'GASTO'],
            'rentals'    => ['Alquileres', 'alquileres', 'ALQUILERES'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@avis-comms.international') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".avis-comms.international/") or contains(@href,"view.avis-comms.international")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"@avis-comms.international")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang() && $this->findRoot()->length === 1;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $email->setType('OffersWithStatement' . ucfirst($this->lang));

        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            return $email;
        }
        $root = $roots->item(0);

        $st = $email->add()->statement();

        $number = null;

        $numberText = implode("\n", $this->http->FindNodes("*[ descendant::text()[{$this->eq($this->t('customerNo'))}] ]/descendant::text()[normalize-space()]", $root));

        if (!preg_match("/^{$this->opt($this->t('customerNo'))}$/iu", $numberText)) {
            $number = preg_match("/^([-A-Za-z\d]{5,})\s+{$this->opt($this->t('customerNo'))}$/", $numberText, $m) ? $m[1] : null;
            $st->setNumber($number);
        }

        $spentText = implode(' ', $this->http->FindNodes("*[ descendant::text()[{$this->eq($this->t('spend'))}] ]/descendant::text()[normalize-space()]", $root));
        $spent = preg_match("/^(.*\d.*)\s+{$this->opt($this->t('spend'))}$/", $spentText, $m) ? $m[1] : null;
        $st->addProperty('Spent', $spent);

        if ($number !== null || $spent !== null) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    private function findRoot(): \DOMNodeList
    {
        return $this->http->XPath->query("//node()[{$this->eq($this->t('customerNo'))}]/ancestor::*[following-sibling::*[normalize-space()]][1]/following-sibling::*[ descendant::text()[{$this->eq($this->t('spend'))}] ]/following-sibling::*[ descendant::text()[{$this->eq($this->t('rentals'))}] ]/..");
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['customerNo']) || empty($phrases['rentals'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->eq($phrases['customerNo'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->eq($phrases['rentals'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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
