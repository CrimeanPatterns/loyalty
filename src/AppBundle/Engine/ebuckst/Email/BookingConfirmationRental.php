<?php

namespace AwardWallet\Engine\ebuckst\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationRental extends \TAccountChecker
{
    // the same as in BookingConfirmationPDF, only parse html body
    public $mailFiles = "ebuckst/it-238594767.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
        ],
    ];

    private $detectSubject = [
        // en
        'Booking Confirmation For Booking Ref:',
    ];
    private $detectBody = [
        'en' => [
            'Car rental summary :',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ebucks.com') !== false;
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
        if (
            $this->http->XPath->query("//img[{$this->contains(['.ebucks.com'], '@src')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['The eBucks Travel Team'])}]")->length === 0
        ) {
            return false;
        }

        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        if (count($pdfs) > 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.+\.pdf');

        if (count($pdfs) > 0) {
            $this->logger->debug('Contains pdf. Go to BookingConfirmationPDF');

            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Pick-up"]) && $this->http->XPath->query("//*[{$this->eq($dict['Pick-up'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

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

    private function parseHtml(Email $email)
    {
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t("eBucks reference:"))}]",
                null, true, "/:\s*(\d+[\-]?\d{5,})\s*$/"));

        // Rental
        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t("Booking reference:"))}]",
                null, true, "/:\s*\|\s*.*?\s*\|\s*([\dA-Z]{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->starts($this->t('Hi,'))}]",
                null, true, "/^Hi,\s*(.+)$/"));

        // Pick Up
        $node = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Pick-up'))}]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("/^\s*{$this->opt($this->t('Pick-up'))}\s*\n(?<date>.+)\n(?<name>[\s\S]+)/u", $node, $m)) {
            $r->pickup()
                ->location(preg_replace("/\s+/", ' ', trim($m['name'])))
                ->date($this->normalizeDate($m['date']));
        }
        // Drop Off
        $node = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Drop-off'))}]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("/^\s*{$this->opt($this->t('Drop-off'))}\s*\n(?<date>.+)\n(?<name>[\s\S]+)/u", $node, $m)) {
            $r->dropoff()
                ->location(preg_replace("/\s+/", ' ', trim($m['name'])))
                ->date($this->normalizeDate($m['date']));
        }

        $r->car()
            ->model($this->http->FindSingleNode("//text()[{$this->contains($this->t("or similar"))}]"));

        return true;
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
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // Fri, 13 Jan '23 | 09:15
            '/^\s*[[:alpha:]\-]+\s*,\s+(\d+)\s+([[:alpha:]]+)\s+\'\s*(\d{2})\s*\|\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 20$3, $4',
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
