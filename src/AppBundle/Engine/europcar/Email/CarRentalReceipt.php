<?php

namespace AwardWallet\Engine\europcar\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CarRentalReceipt extends \TAccountChecker
{
    public $mailFiles = "europcar/it-467711207.eml";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Pick Up Location'     => ['Pick Up Location', 'Pick UpLocation'],
            'Drop-Off Information' => ['Drop-Off Information', 'Drop-OffInformation'],
        ],
    ];

    private $detectFrom = "info@europcar-us.com";
    private $detectSubject = [
        // en
        'Europcar Car Rental Receipt',
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]europcar-us\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && stripos($headers["subject"], 'Europcar ') === false
        ) {
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
        if ($this->http->XPath->query("//*[{$this->contains(['www.europcar-us.com', 'EUROPCAR Reservation Number'])}]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
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
        return count(self::$dictionary);
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict['Pick Up Location']) && $this->http->XPath->query("//*[{$this->contains($dict['Pick Up Location'])}]")->length > 0
                && !empty($dict['Drop-Off Information']) && $this->http->XPath->query("//*[{$this->contains($dict['Drop-Off Information'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email)
    {
        $email->setSentToVendor(true);

        $r = $email->add()->rental();

        // General
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('EUROPCAR Reservation Number:'))}]",
                null, true, "/{$this->opt($this->t('EUROPCAR Reservation Number:'))}\s*(\d{5,})\s*$/"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t('Driver Information'))}]/following::text()[normalize-space()][1]"))
        ;

        // Pick Up
        $node = implode("\n", $this->http->FindNodes("//node()[{$this->eq($this->t('Pick Up Location'))}]/ancestor::table[1]//text()[normalize-space()]"));
        $re = "/\n(?<date>.*\b20\d{2}\b.*\d{1,2}:\d{2}:\d{2}.*)\n(?<name>[\s\S]+?)\n{$this->opt($this->t('Tel:'))} *(?<tel>.+)\n{$this->opt($this->t('Fax:'))} *(?<fax>.+)\n(?<hours>[\s\S]+)/";

        if (preg_match($re, $node, $m)) {
            if (strlen($m['fax']) < 5) {
                unset($m['fax']);
            }
            $r->pickup()
                ->location(preg_replace("/\s*\n\s*/", ', ', trim($m['name'])))
                ->date($this->normalizeDate($m['date']))
                ->phone($m['tel'])
                ->fax($m['fax'] ?? null, true, true)
                ->openingHours(preg_replace("/\s+/", ' ', trim($m['hours'])));
        }

        // Drop Off
        $node = implode("\n", $this->http->FindNodes("//node()[{$this->eq($this->t('Drop-Off Information'))}]/ancestor::table[1]//text()[normalize-space()]"));
        $re = "/\n(?<date>.*\b20\d{2}\b.*\d{1,2}:\d{2}:\d{2}.*)\n(?<name>[\s\S]+?)\n{$this->opt($this->t('Tel:'))} *(?<tel>.+)\n{$this->opt($this->t('Fax:'))} *(?<fax>.+)\n(?<hours>[\s\S]+)/";

        if (preg_match($re, $node, $m)) {
            if (strlen($m['fax']) < 5) {
                unset($m['fax']);
            }
            $r->dropoff()
                ->location(preg_replace("/\s*\n\s*/", ', ', trim($m['name'])))
                ->date($this->normalizeDate($m['date']))
                ->phone($m['tel'])
                ->fax($m['fax'] ?? null, true, true)
                ->openingHours(preg_replace("/\s+/", ' ', trim($m['hours'])));
        }

        // Car
        $carText = implode("\n", $this->http->FindNodes("//text()[{$this->contains($this->t('or similar'))}]/ancestor::td[1]//text()[normalize-space()]"));

        if (preg_match("/\n(?<model>.+?) *{$this->opt($this->t('or similar'))}\n\s*(?<type>[\s\S]+)/", $carText, $m)) {
            $r->car()
                ->model($m['model'])
                ->type(preg_replace('/\s*\n\s*/', ', ', $m['type']))
            ;
        }

        // Price
        $currency = $this->http->FindSingleNode("//*[{$this->eq($this->t('Currency'))}]/following-sibling::*[normalize-space()][1]");
        $r->price()
            ->currency($currency)
            ->total(PriceHelper::parse($this->http->FindSingleNode("//*[{$this->eq($this->t('Total'))}]/following-sibling::*[normalize-space()][1]"), $currency))
            ->cost(PriceHelper::parse($this->http->FindSingleNode("//*[{$this->eq($this->t('Rate'))}]/following-sibling::*[normalize-space()][1]"), $currency))
            ->fee($this->http->FindSingleNode("(//*[{$this->eq($this->t('Addt\'l Fees and Charges'))}])[1]"),
                PriceHelper::parse($this->http->FindSingleNode("//*[{$this->eq($this->t('Addt\'l Fees and Charges'))}]/following-sibling::*[normalize-space()][1]"), $currency))
            ->fee($this->http->FindSingleNode("(//*[{$this->eq($this->t('Equipment Charges'))}])[1]"),
                PriceHelper::parse($this->http->FindSingleNode("//*[{$this->eq($this->t('Equipment Charges'))}]/following-sibling::*[normalize-space()][1]"), $currency))
        ;

        return true;
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

    private function dateTranslate($date)
    {
        if (preg_match('/[[:alpha:]]+/iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("/$monthNameOriginal/i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $in = [
            // Sep 14, 2023 10:00:00 am
            '/^\s*([[:alpha:]]+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}):\d{2}\s*([ap]m|)\s*$/ui',
        ];
        $out = [
            '$2 $1 $3, $4 $5',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

        // $this->logger->debug('date end = ' . print_r( $date, true));

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
