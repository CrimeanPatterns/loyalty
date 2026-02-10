<?php

namespace AwardWallet\Engine\ceskedrahy\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class InformationAbout extends \TAccountChecker
{
    public $mailFiles = "ceskedrahy/it-344863076.eml, ceskedrahy/it-344990159.eml, ceskedrahy/it-347335542.eml";
    public $subjects = [
        'www.cd.cz - information about order No.',
        'www.cd.cz - informace o objednávce č.',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@atlas.cz') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (strpos($text, 'České dráhy. Lepší cesta každý den.') !== false
                && strpos($text, 'Daňový doklad číslo') !== false
                && strpos($text, 'Jízdní řád') !== false
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]atlas\.cz$/', $from) > 0;
    }

    public function parseTrain(Email $email, $text)
    {
        $t = $email->add()->train();

        $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Order No.') or starts-with(normalize-space(), 'Objednávka č.')]/following::text()[normalize-space()='Date of issue:' or normalize-space()='Datum vystavení:'][1]/following::text()[normalize-space()][1]", null, true, "/\.(\d{4})/");

        $t->general()
            ->traveller($this->re("/(?:TICKET|JÍZDENKA)\s*(.+)\n/", $text));

        if (preg_match_all("/Ref\:\s*(\d{11,})/", $text, $m)) {
            foreach ($m[1] as $conf) {
                $t->general()
                    ->confirmation($conf);
            }
        }

        if (empty($t->getConfirmationNumbers())) {
            $t->general()
                ->noConfirmation();
        }

        if (preg_match("/Cena\s*(?<total>[\d\,\.]+)\s*(?<currency>[\D]{1,3})\n/", $text, $m)) {
            $currency = $this->normalizeCurrency($m['currency']);

            $t->price()
                ->total(PriceHelper::parse($m['total'], $currency))
                ->currency($currency);
        }

        if (preg_match_all("/\s*[*](\d+\-\d+\-\d+(?:\/\d+)?)/u", $text, $m)) {
            $t->setTicketNumbers($m[1], false);
        }

        $segments = [];

        if (preg_match_all("/^(.+[ ]{10,}\d+\.\d+\.\s+\d+\:\d+\s*[A-z\d]{2}\s+\d{2,4}\s*(?:\d+)?\s*(?:[\d\,\s]+)?.*\n.+[ ]{10,}\d+\.\d+\.\s+\d+\:\d+)(?:\s+\D+)?\n/mu", $text, $m)) {
            $segments = $m[1];
        }

        foreach ($segments as $segText) {
            $s = $t->addSegment();

            if (preg_match("/^(?<depName>.+)[ ]{10,}(?<date>\d+\.\d+\.)\s+(?<time>\d+\:\d+)\s*(?<service>[A-z\d]{2})\s+(?<number>\d{2,4})\s*(?<car>\d+)?\s*(?<seat>[\d\,\s]+)?.*\n/mu", $segText, $depM)) {
                $s->departure()
                    ->date(strtotime($depM['date'] . $year . ', ' . $depM['time']))
                    ->name($depM['depName']);

                $s->setNumber($depM['number']);

                $s->setServiceName($depM['service']);

                if (isset($depM['car']) && !empty($depM['car'])) {
                    $s->setCarNumber($depM['car']);
                }

                if (isset($depM['seat']) && !empty($depM['seat'])) {
                    $s->extra()
                        ->seats(explode(', ', $depM['seat']));
                }
            }

            if (preg_match("/^(?<arrName>.+)[ ]{10,}(?<date>\d+\.\d+\.)\s+(?<time>\d+\:\d+)$/mu", $segText, $arrM)) {
                $s->arrival()
                    ->date(strtotime($arrM['date'] . $year . ', ' . $arrM['time']))
                    ->name($arrM['arrName']);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $texts = $this->splitText($text, "/^(\s*(?:TICKET|JÍZDENKA))/m", true);

            foreach ($texts as $text) {
                $this->parseTrain($email, $text);
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'CZK' => ['Kč'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
            'USD' => ['US$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }
}
