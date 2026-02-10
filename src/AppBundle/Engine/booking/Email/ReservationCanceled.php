<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationCanceled extends \TAccountChecker
{
    public $mailFiles = "booking/it-56915532.eml";

    public $lang = '';

    public static $dictionary = [
        'es' => [],
    ];

    private $subjects = [
        'es' => ['¡Reserva cancelada!'],
    ];

    private $detectors = [
        'es' => ['Acabas de recibir la cancelación de una reserva de un cliente de Booking.com'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Booking.com') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('ReservationCanceled' . ucfirst($this->lang));

        $bodyHtml = $this->http->Response['body'];

        if (strpos($bodyHtml, '.booking.com/hotel/') === false) {
            $this->logger->debug("Hotel not found!");

            return $email;
        }

        $h = $email->add()->hotel();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Cancelación'))}]");

        if (preg_match('/^(Cancelación)[-—\s]+([A-Z\d]{5,})$/', $confirmation, $m)) {
            $h->general()
                ->confirmation($m[2], $m[1])
                ->cancelled();
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

    private function assignLang(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $lang => $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }
}
