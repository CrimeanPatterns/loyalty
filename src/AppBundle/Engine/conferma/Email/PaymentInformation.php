<?php

namespace AwardWallet\Engine\conferma\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class PaymentInformation extends \TAccountChecker
{
    public $mailFiles = "conferma/it-207688893.eml, conferma/it-213123979.eml";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Hotel Reservation Number:', 'Hotel Reservation Number :'],
            'checkIn'        => ['Check-in Date:', 'Check-in Date :'],
            'statusPhrases'  => ['Booking'],
            'statusVariants' => ['confirmed', 'Confirmed'],
        ],
    ];

    public static $travelAgency = [
        'amextravel' => [
            'American Express Global Business Travel',
        ],
        'gant' => [
            'Gant Travel Management',
        ],
        'tleaders' => [
            'Travel Leaders Corporate',
        ],
        'bcd' => [
            'BCD Travel',
        ],
        'amtrav' => [
            ' AmTrav',
        ],
        'ctraveller' => [
            'Corporate Traveler US',
        ],
        'ctmanagement' => [
            'Corporate Travel Management',
        ],
        'fcmtravel' => [
            'FCM',
        ],
        'wagonlit' => [
            'CWT',
        ],
        'travelinc' => [
            'World Travel, Inc.',
        ],
    ];

    private $detectSubject = [
        // en
        ' - Important payment information about your booking to',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'notifications@conferma.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.conferma.com/', '.confermapay.com/'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['generated for Conferma Deployment', 'generated for Conferma Pay Deployment'])}]")->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $providerCode = '';

        foreach (self::$travelAgency as $code => $comp) {
            if ($this->http->XPath->query("//*[{$this->contains($comp)}]")->length > 0
                || preg_match("/\b{$this->opt($comp)}\b/", $parser->getSubject()) > 0
            ) {
                $providerCode = $code;
            }
        }

        $email->ota()->code($providerCode);

        $this->assignLang();
        $this->parseEmailHtml($email);

        $email->setType('PaymentInformation' . ucfirst($this->lang));

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

    private function parseEmailHtml(Email $email): void
    {
        $email
            ->obtainTravelAgency();

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->status($this->http->FindSingleNode("//h3[{$this->starts($this->t('statusPhrases'))}]", null, true, "/^{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"))
            ->confirmation($this->http->FindSingleNode("//td[{$this->eq($this->t('confNumber'))}]/following-sibling::td[normalize-space()][1]"))
            ->travellers(preg_replace("/^\s*(Doctor) /", "", array_filter(preg_split("/\s*,\s*/",
                $this->http->FindSingleNode("//td[{$this->eq($this->t("Guest Name(s):"))}]/following-sibling::td[normalize-space()][1]")))))
        ;

        // Hotel
        $name = $this->http->FindSingleNode("//td[{$this->eq($this->t("Hotel:"))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]");
        $address = trim(preg_replace(['/\s*,\s*/', '/\s+/'], [', ', ' '], implode(" ",
                $this->http->FindNodes("//td[{$this->eq($this->t("Hotel:"))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]"))), ', ');

        if (empty($address) && !empty($name) && strpos($name, ',') === false) {
            $h->hotel()
                ->name($name)
                ->noAddress()
            ;
        } else {
            $h->hotel()
                ->name($this->http->FindSingleNode("//td[{$this->eq($this->t("Hotel:"))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]"))
                ->address(trim(preg_replace(['/\s*,\s*/', '/\s+/'], [', ', ' '], implode(" ",
                    $this->http->FindNodes("//td[{$this->eq($this->t("Hotel:"))}]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]"))),
                    ', '));
        }

        // Booked
        $checkInText = $this->http->FindSingleNode("//td[{$this->eq($this->t('checkIn'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/(.+) for (\d+) /", $checkInText, $m)) {
            $checkIn = strtotime($m[1]);

            if (!empty($checkIn)) {
                $h->booked()
                    ->checkIn($checkIn)
                    ->checkOut(strtotime('+ ' . ($m[2] + 1) . " days", $checkIn));
            }
        }

        // Price
        $total = $this->http->FindSingleNode("//td[{$this->eq($this->t("Booked Amount:"))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
            || preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
        ) {
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } else {
            $h->price()
                ->total(null);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
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

    // additional methods

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

    private function opt($field, $delimiter = '/'): string
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
