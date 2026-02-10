<?php

namespace AwardWallet\Engine\europcar\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Important extends \TAccountChecker
{
    public $mailFiles = "europcar/statements/it-65527235.eml, europcar/statements/it-65535444.eml, europcar/statements/it-65580927.eml, europcar/statements/it-65581779.eml, europcar/statements/it-65582703.eml";
    private $lang = '';
    private $reFrom = ['europcar-loyaltymembers.com'];
    private $reProvider = ['Europcar Privilege', 'Carta Privilege', 'Privilege Club', 'Privilege Executive', 'Privilege Elite'];
    private $reSubject = [
        // en
        'Important information about your Privilege membership',
        'You are one rental away from your next Privilege reward',
        // it
        'Informazioni importanti sulla tua iscrizione Privilege',
        // fr
        'Information importante concernant votre adhésion au programme',
        // es
        'Información importante sobre el Programa Privilege',
        // pt
        'Informação importante relativa ao seu Programa',
        // de
        'Privilege Angebot! Vergünstigte Konditionen nur noch bis Montag gültig',
    ];
    private $reBody = [
        'en' => [
            ['You are receiving this message as a Europcar Privilege member', 'Europcar ID:'],
            ['You receive this email because you wish to receive news about our products and services', 'Europcar ID:'],
            ['You receive this email because you wish to receive news about our products and services', 'Europcar ID:'],
            ['You received this email because you are a member of the Privilege Loyalty Program', 'Europcar ID:'],
            ['You receive this email because you\'ve proceed to a vehicle rental.', 'Europcar ID:'],
        ],
        'it' => [
            ['Ricevi questo messaggio in quanto titolare di una Carta Privilege', 'Codice identificativo Europcar:'],
        ],
        'fr' => [
            ['Vous recevez ce message en tant que membre du programme de fidélité Europcar', 'Identifiant Europcar'],
        ],
        'es' => [
            ['Estás recibiendo este mensaje como titular de la tarjeta Europcar', 'Identificación Europcar:'],
        ],
        'pt' => [
            ['Esta mensagem é relativa ao seu Programa Privilege Europcar', 'ID Europcar:'],
        ],
        'de' => [
            ['soeben wurden die neuen Privilege-Angebote', 'Driver ID:'],
            ['Sie erhalten diese E-Mail, weil Sie ein Fahrzeug reserviert oder angemietet haben.', 'Driver ID:'],
            ['Sie erhalten diese E-Mail, weil Sie Mitglied des Privilege-Treueprogramms sind.', 'Driver ID:'],
        ],
        'nl' => [
            ['U ontvangt deze e-mail omdat u een voertuig heeft gehuurd.', 'Europcar-ID:'],
            ['U heeft deze e-mail ontvangen omdat u lid bent van het Privilege Getrouwheidsprogramma .', 'Europcar ID:'],
            ['U ontvangt deze e-mail omdat je nieuws over onze producten en diensten wilt ontvangen.', 'Europcar-ID:'],
        ],
        'no' => [
            ['Du mottok denne e-posten fordi den inneholder vikitg informasjon om ditt Europcar Privilege-lojalitetsprogram.', 'Europcar ID:'],
        ],
    ];
    private static $dictionary = [
        'en' => [
        ],
        'it' => [
            'Europcar ID:' => 'Codice identificativo Europcar:',
        ],
        'fr' => [
            'Europcar ID:' => ['Identifiant Europcar :', 'Identifiant Europcar'],
        ],
        'es' => [
            'Europcar ID:' => 'Identificación Europcar:',
        ],
        'pt' => [
            'Europcar ID:' => 'ID Europcar:',
        ],
        'de' => [
            'Europcar ID:' => 'Driver ID:',
        ],
        'nl' => [
            'Europcar ID:' => ['Europcar-ID:', 'Europcar ID:'],
        ],
        'no' => [
            'Europcar ID:' => 'Europcar ID:',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (self::detectEmailFromProvider($parser->getCleanFrom()) !== true) {
            return false;
        }
        $this->assignLang();
        $this->logger->debug("Lang: {$this->lang}");
        $st = $email->add()->statement();

        $text = join("\n",
            array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Europcar ID:'))}]/ancestor::table[1]//text()")));
        $text = str_replace(['', ''], '', $text);

        if (preg_match("/^([[:alpha:]\-\s]{3,})\s+(Privilege [[:alpha:]\s]{4,})" .
            "\s+{$this->opt($this->t('Europcar ID:'))}\s([\w\-]{4,})/u", $text, $m)) {
            $st->addProperty('Name', $m[1]);
            $st->addProperty('Status', $m[2]);
            $st->setLogin($m[3]);
            $st->setNumber($m[3]);

            $st->setMembership(true);
            $st->setNoBalance(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains($this->reProvider)}]")->length === 0) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $values) {
            foreach ($values as $value) {
                if ($this->http->XPath->query("//text()[{$this->contains($value[0])}]")->length > 0
                && $this->http->XPath->query("//text()[{$this->contains($value[1])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ")='" . $s . "'";
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }
}
