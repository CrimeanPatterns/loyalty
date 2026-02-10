<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourTripCanceled extends \TAccountChecker
{
    public $mailFiles = "delta/it-58168760.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'       => ['Your Trip Confirmation #', 'Your Trip Confirmation#'],
            'cancelledPhrases' => '- has been canceled.',
        ],
    ];

    private $subjects = [
        'en' => ['Your Trip Has Been Canceled'],
    ];

    private $detectors = [
        'en' => ['- has been canceled.'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@n.delta.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".delta.com/") or contains(@href,"www.delta.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for choosing Delta") or contains(.,"Delta Air Lines, Inc. All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $statusText = $this->http->FindSingleNode("//text()[{$this->contains($this->t('cancelledPhrases'))}]", null, true, "/- has been (canceled)\./i");

        if ($statusText) {
            $f->general()->status($statusText);
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $f->general()->cancelled();
        }

        $travellers = [];
        $ticketNumbers = [];

        $ticketsRow = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Ticket #'))}]", null, true, "/{$this->opt($this->t('Ticket #'))}[:\s]+(.{2,})$/");
        $tickets = preg_split('/\s*\|\s*/', $ticketsRow);

        foreach ($tickets as $ticket) {
            if (preg_match("/^([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s+(\d{3}[- ]*\d{5,}[- ]*\d{1,2})$/u", $ticket, $m)) {
                // D Ivie 0062420034143
                $travellers[] = $m[1];
                $ticketNumbers[] = $m[2];
            }
        }

        if (count($travellers)) {
            $f->general()->travellers(array_unique($travellers));
        }

        if (count($ticketNumbers)) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }

        $email->setType('YourTripCanceled' . ucfirst($this->lang));

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

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0) {
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
