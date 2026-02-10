<?php

namespace AwardWallet\Engine\asiana\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class MilesStatement2021 extends \TAccountChecker
{
    public $mailFiles = "asiana/statements/it-127034015.eml";
    private $lang = '';
    private $detectFrom = 'iclub@flyasiana.com';
    private $detectSubject = [
        '\'s mileage expiration status.', // [Asiana Airlines] Information on ASHLEY KIM 's mileage expiration status.
    ];


    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();

        if (preg_match("/Information on ([[:alpha:]\- ]+) 's mileage expiration status/", $parser->getSubject(), $m)) {
            $st->addProperty('Name', $m[1]);
        }

        // As of Aug. 19, 2021, your remaining mileage is
        $date = $this->http->FindSingleNode("//img[{$this->contains(', your remaining mileage is', '@alt')}]/@alt");
        if ($date = $this->http->FindPreg("/As of (.+), your remaining mileage is/", false, $date)) {
            $st->setBalanceDate($this->normalizeDate($date));

            $balance = $this->http->FindSingleNode("//img[{$this->contains(', your remaining mileage is', '@alt')}]/ancestor::td[1]/following-sibling::td[1]", null, false, self::BALANCE_REGEXP);
            $st->setBalance(str_replace(',', '', $balance));
            $st->setMembership(true);
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->detectSubject) !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[{$this->contains('emailsend.flyasiana.com', '@href')}]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//img[{$this->contains(', your remaining mileage is', '@alt')}]/@alt")->length > 0) {
            return true;
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return ['en'];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }


    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // Aug. 19, 2021
            '/^\s*(\w+)\. (\d+), (\d{4})\s*$/',
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . "),'" . $s . "')";
        }, $field)) . ')';
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
