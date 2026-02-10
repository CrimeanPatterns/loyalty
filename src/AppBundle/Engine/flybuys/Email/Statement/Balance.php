<?php

namespace AwardWallet\Engine\flybuys\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Balance extends \TAccountChecker
{
    public $mailFiles = "flybuys/it-94504444.eml";

    public $lang = 'en';

    public static $dictionary = [
        "en" => [],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], 'flybuys@edm.flybuys.com.au') !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]flybuys\.com\.au$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Membership Number')]/ancestor::*[contains(.,'you have')][1]");
//        $this->logger->debug('$text = '. print_r($text, true));
        if (preg_match("/^\s*(?:Hi +)?(?<name>[[:alpha:] \-]+), you have\s*(?<balance>\d[\d,]*)\s*points as at (?<date>.+?)\s*Membership Number:\s*(?<number>\d+(?:[X ]+|[x ]+|[\d ]+)\d+)\s*$/", $text, $m)) {
            //  Peter, you have 119,808 points as at 24 May 2021 Membership Number: 6008 XXXX XXXX 7591
            // Lorraine, you have 822 points as at 8 Apr 2021 Membership Number: 6008944050137213

            $st = $email->add()->statement();

            $m['number'] = str_replace(' ', '', $m['number']);

            if (preg_match("/^\d+$/", $m['number'])) {
                $st->setNumber($m['number']);
            } else {
                $st->setNumber(preg_replace("/X+/i", '**', $m['number']))->masked('center');
            }
            $st
                ->addProperty("Name", $m['name'])
                ->setBalanceDate(strtotime($m['date']))
                ->setBalance(str_replace(',', '', $m['balance']))
            ;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
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
}
