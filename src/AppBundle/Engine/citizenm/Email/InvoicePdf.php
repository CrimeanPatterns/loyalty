<?php

namespace AwardWallet\Engine\citizenm\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class InvoicePdf extends \TAccountChecker
{
    public $mailFiles = "citizenm/it-352748385.eml, citizenm/it-356714242.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            'Invoice' => 'Invoice',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]citizenm.com/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, ['citizenM says', 'www.citizenM.com']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Invoice'])
                && $this->containsText($text, $dict['Invoice']) === true
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $this->parseEmailPdf($email, $text);
            }
        }

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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->re('/Our reference *([A-Z\d]{5,})(-\d+)?\n/', $textPdf))
        ;

        if (preg_match("/\n *Guest: *(?<guest>.+?) *Arrival: *(?<checkIn>.+?) *Departure: *(?<checkOut>.+?) *Room:/", $textPdf, $m)) {
            $h->general()
                ->traveller($m['guest']);

            $h->booked()
                ->checkIn(strtotime($m['checkIn']))
                ->checkOut(strtotime($m['checkOut']))
            ;
        }

        if (preg_match("/\n *citizenM says:.+\n+(?<name>.+?)\n(?<address>(.+\n)+) *www\.citizenM\.com/", $textPdf, $m)) {
            $h->hotel()
                ->name(trim($m['name']))
                ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
            ;
        }

        $currency = $this->currency($this->re('/ {2,}Price {2,}Total *(.+)\n/', $textPdf));
        $priceTable = $this->re('/\n *Guest:.+\n+( *\d(.+\n+)+) *Total invoice/', $textPdf);
        $priceRows = array_filter(explode("\n", $priceTable));
        $cost = 0.00;
        $taxes = [];

        foreach ($priceRows as $row) {
            $amount = PriceHelper::parse($this->re("/^ *[\d\\/]{5,} {2,}.+ {2,}(\d[\d,. ]*)$/", $row), $currency);
            $name = trim($this->re("/^ *[\d\\/]{5,} {2,}(.+?) {2,}/", $row));

            if (preg_match("/ +Room Charge/i", $row)) {
                $cost += $amount;

                continue;
            }

            if (isset($taxes[$name])) {
                $taxes[$name] += $amount;
            } else {
                $taxes[$name] = $amount;
            }
        }

        $h->price()
            ->cost($cost);

        $total = PriceHelper::parse($this->re("/\n +Total invoice *(\d[\d,. ]*)\n/", $textPdf), $currency);
        $h->price()
            ->total($total)
            ->currency($currency)
        ;

        foreach ($taxes as $name => $tax) {
            $h->price()
                ->fee($name, $tax);
        }

        return $email;
    }

    private function currency($s)
    {
        $s = trim($s);
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if (preg_match("/^[A-Z]{3}$/", $s)) {
            return $s;
        }

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
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
