<?php

namespace AwardWallet\Engine\asiana\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Membership extends \TAccountChecker
{
    public $mailFiles = "asiana/statements/it-127148046.eml, asiana/statements/it-63616260.eml, asiana/statements/it-70475662.eml";
    private $detectFrom = '@flyasiana.com';
    private $detectsEmail = [
        [
            'subject' => ['(Asiana Airlines) Notice for Statement of Consent for Marketing information.',],
            'body' => ['This e-mail has been sent to Asiana Club members who have agreed and subscribed to Asiana Airlines e-mails'],
        ],
        [
            'subject' => ['(Asiana Airlines) Your Asiana Club account will be in dormant status soon.',],
            'body' => ['According to the above Act and it’s regulations, we would like to let you know that your account will'],
        ],
        [
            'subject' => ['(광고)',],
            'body' => ['아시아나항공 이메일 수신을 동의하신 회원님께만 발송되었습니다.', '아시아나항공 이메일수신을 동의하신 회원님께만 발송되었습니다.'],
        ],
        [
            'subject' => ['[아시아나항공] 개인정보 이용 내역 통지 안내',],
            'body' => ['본 메일은 개인정보보호법 제39조의8에 의거, 아시아나항공 가입회원에게 이용자가 입력한 사항을 기준으로'],
        ],
        [
            'subject' => ['(韩亚航空) 温馨提醒您，您的韩亚会员账号将会变更为休眠账号。',],
            'body' => ['根据如上规定，您的账号将于'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $st = $email->add()->statement();
        $st->setMembership(true);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (stripos($parser->getHeader('from'), $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectsEmail as $detect) {
            if (empty($detect['subject']) || empty($detect['body'])) {
                continue;
            }
            if ($this->arrikey($parser->getSubject(), $detect['subject']) === false) {
                continue;
            }
            if ($this->http->XPath->query("//*[{$this->contains($detect['body'])}] | //img[{$this->contains($detect['body'], '@alt')}]/@alt")->length > 0) {
                return true;
            }
        }

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
