<?php

namespace AwardWallet\Engine\amextravel\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AirportServices extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-705624391.eml, amextravel/it-710587446.eml";

    public $detectFrom = "amex.reservations@diamondairinternational.com";
    public $detectSubject = [
        // en
        // Booking confirmation for DAVID HARTMAN at London Heathrow Airport on 04/05/2024 -Internal ref: CRM:0078475
        // Airport Guide for JOHN GOLDBERG at Leonardo Da Vinci (Fiumicino) International Airport on 08/08/2024 - Internal ref: CRM:0073353
        'Internal ref: CRM:',
    ];

    public $emailSubject;

    public $lang;
    public static $dictionary = [
        'en' => [
            'your Centurion International'                        => 'your Centurion International',
            'Reservation confirmation number'                     => ['Reservation confirmation number', 'Reservation confirmation number:'],
            'Location of service:'                                => 'Location of service:',
            'This email serves as your cancellation confirmation' => [
                'This email serves as your cancellation confirmation',
                'Thank you for cancelling your',
            ],
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'amex.reservations@diamondairinternational.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false) {
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
        if ($this->http->XPath->query("//*[{$this->contains(['Amex.Reservations@diamondairinternational.com', 'amex.reservations@DiamondAirInternational.com'])}]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['your Centurion International'])
                && !empty($dict['Location of service:'])
                && $this->http->XPath->query("//*[{$this->contains($dict['your Centurion International'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dict['Location of service:'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
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
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $event = $email->add()->event();

        $event->type()->event();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Reservation confirmation number'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([A-Z\d]{5,})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Reservation confirmation number'))}]",
                null, true, "/{$this->opt($this->t('Reservation confirmation number'))}\s*[:\s]\s*([A-Z\d]{5,})\s*$/");
        }
        $event->general()
            ->confirmation($conf)
            ->traveller($this->nextTd($this->t("Card Member's Name:")), true)
        ;

        if (preg_match("/\b(Internal ref: CRM):\s*(\d{5,})\s*$/", $this->emailSubject, $m)) {
            $event->general()
                ->confirmation($m[2], $m[1]);
        }
        $notes = [];

        if ($this->nextTd($this->t("Additional service(s) Booked:"), '/[1-9]/')) {
            $notes[] = $this->nextTd($this->t("Additional service(s) Booked:"), null, "/ancestor::tr[1]");
        }
        $notes[] = $this->nextTd($this->t("Airport Guide Name:"), null, "/ancestor::*[1]");
        $notes[] = $this->nextTd($this->t("Guide Telephone Number:"), null, "/ancestor::*[1]");
        $notes = array_filter($notes);

        if (!empty($notes)) {
            $event->general()
                ->notes(implode('. ', $notes));
        }

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('This email serves as your cancellation confirmation'))}]")->length > 0) {
            $event->general()
                ->cancelled()
                ->status('Cancelled');
        }
        // Place
        $event->place()
            ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('your Centurion International'))}]/ancestor-or-self::node()[{$this->contains($this->t('Services'))}][1]",
                null, true, "/{$this->opt($this->t('your Centurion International'))} (.+? Services?) with/"))
            ->address($this->nextTd($this->t("Location of service:")));

        // Booked
        $event->booked()
            ->start($this->normalizeDate($this->nextTd($this->t("Date (dd/mm) and Time of Service (in local time at destination):"))))
            ->noEnd()
            ->guests($this->nextTd($this->t("Total Number of Passengers:"), '/^\s*(\d+)\s*$/'));

        return true;
    }

    private function nextTd($field, $regexp = null, $cond = '')
    {
        return $this->http->FindSingleNode("//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1][{$this->eq($field)}]]/*[normalize-space()][2]" . $cond, null, true, $regexp);
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

    private function normalizeDate(?string $date): ?int
    {
        if (empty($date)) {
            return null;
        }

        $in = [
            //  04/08/2024 13:55 
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1.$2.$3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
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
