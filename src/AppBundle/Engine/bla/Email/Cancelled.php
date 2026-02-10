<?php

namespace AwardWallet\Engine\bla\Email;

use AwardWallet\Schema\Parser\Email\Email;

class Cancelled extends \TAccountChecker
{
    public $mailFiles = "bla/it-265579089.eml, bla/it-267768048.eml";

    public $lang;
    public static $dictionary = [
        'fr' => [
            'Booking number:' => 'Numéro de réservation :',
        ],
        'it' => [
            'Booking number:' => 'Riferimento della prenotazione:',
        ],
    ];

    private $detectFrom = "notification@blablacar.com";
    private $detectSubject = [
        // fr
        ' a été annulée',
        // it
        ' è stata annullato',
    ];
    private $detectBody = [
        // array (count == 1 or count == 2) or string
        'fr' => [
            ['Comme demandé, votre réservation BlaBlaCar', 'a été annulée.'],
            'Désolés, votre réservation a été annulée en raison d\'un problème',
        ],
        'it' => [
            ['Come richiesto, la tua prenotazione BlaBlaCar', 'è stata annullata.'],
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'BlaBlaCar') === false) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.blablacar.'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['BlaBlaCar'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language");

            return $email;
        }
        $this->parseEmailHtml($email);

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
        // TODO check count types
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $b = $email->add()->bus();

        $b->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t("Booking number:"))}]/following::text()[normalize-space()][1]"))
            ->status('Cancelled')
            ->cancelled()
        ;

        return true;
    }

    private function assignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if (is_array($dBody) && count($dBody) === 2) {
                    if ($this->http->XPath->query("//*[{$this->contains($dBody[0])} and {$this->contains($dBody[1])}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                } elseif (is_string($dBody) || (is_array($dBody) && count($dBody) === 1)) {
                    if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0
                    ) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
