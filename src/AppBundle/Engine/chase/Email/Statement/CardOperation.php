<?php

namespace AwardWallet\Engine\chase\Email\Statement;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CardOperation extends \TAccountChecker
{
    public $mailFiles = "chase/statements/it-76679781.eml, chase/statements/it-77083998.eml, chase/statements/it-137048647.eml";

    private $detectFrom = ['no.reply.alerts@chase.com', 'Chase@notify.chase.com'];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [
            'maskedAccountRe' => [
                'your (?:Chase )?credit card account ending in (?<maskedAccount>\d{4}) ?\.',
                'Your credit card payment is due in \d+ days for your account ending in (?<maskedAccount>\d{4}) ?\.',
                '\n\s*Account ending in: (?<maskedAccount>\d{4})\s*\n',
            ],
            "An account is active again" => [
                "An account is active again",
                "You have new positive activity on your credit report.",
                "The limit on one of your credit accounts went up.",
            ],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();
//        $this->logger->debug('$text = '.print_r( $text,true));
        $st = $email->add()->statement();

        $regexps = (array) $this->t("maskedAccountRe");

        $number = $name = $balance = null;

        foreach ($regexps as $reg) {
            if (preg_match("/" . $reg . "/iu", $text, $m)) {
                if (!empty($m['maskedAccount'])) {
                    $number = $m['maskedAccount'];
                }
            }
        }

        if (empty($number)) {
            $number = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Account'))}] ]/*[normalize-space()][2]", null, true, "/\(\s*[.]{3}(\d{4})\s*\)/");
        }
        /*
        if (!empty($number)) {
            $st->setLogin($number)->masked();
            $st->setNumber($number)->masked();
        }
        */

        if (!preg_match("/\b(\d{4})\b[\s\S]+\(C\) 20\d{2} JPMorgan Chase & Co\./iu", $text, $m)) {
            $st->setMembership(true);
        }

        if (preg_match("/^\s*(?:Hello|Hi) ([[:alpha:] \-]+),/ium", $text, $m)) {
            $name = $m[1];
        }

        if (preg_match("/member/iu", $name ?? '', $m)) {
            $name = null;
        }

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        // this is not balance
        // $balance = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Rewards balance reached'))}] ]/*[normalize-space()][2]", null, true, "/^(\d[,.\'\d ]*?)\s+points/i");
        // if ($balance !== null) {
        //     $st->setBalance(PriceHelper::parse($balance));
        // } else
        if (!empty($number) || !empty($name) || $st->getMembership() === true) {
            $st->setNoBalance(true);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']) === true;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return false;
    }

    public static function getEmailLanguages()
    {
        return [];
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
