<?php

namespace AwardWallet\Engine\asia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Notification extends \TAccountChecker
{
    public $mailFiles = "asia/it-68441419.eml, asia/statements/it-65134759.eml, asia/statements/it-65181030.eml, asia/statements/it-66402972.eml, asia/statements/it-66613763.eml";
    public $lang;

    public $langDetector = [
        'zh' => ['操作系統', '謹啟', '尊敬的'],
        'en' => ['Operating system', 'Yours sincerely', 'notification email'],
    ];

    public static $dictionary = [
        'zh' => [
            'Dear'   => ['尊敬的', '親愛的'],
            'access' => [
                '若您於上述時間並無登入，並懷疑有人嘗試使用您的賬戶',
                '更新您的個人資料',
                '我们注意到您的马可孛罗会账户',
            ],
            'Marco Polo Club account' => ['的馬可孛羅會賬戶', '會員號碼：', '我们注意到您的马可孛罗会账户'],
        ],
        'en' => [
            'access' => [
                'If you did not log in at that time and suspect someone might be trying to access your account',
                'Update your profile',
                'You can ignore this message if the above information looks familiar.',
                'We have received your password reset request for your account',
            ],
            'Marco Polo Club account' => [
                'Marco Polo Club account',
                'Membership No.:',
                'We’ve noticed a new sign-in for your account',
                'Cathay Pacific',
            ],
        ],
    ];

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".cathaypacific.com/") or contains(@href,"e.cathaypacific.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"© Cathay Pacific") or contains(normalize-space(),"@club.cathaypacific.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectLang()
            && $this->http->XPath->query("//text()[{$this->contains($this->t('access'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Marco Polo Club account'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]cathaypacific\.com/i', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();
        $email->setType('Notification' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,:：;!?]|$)/u");

        if (!empty($name)) {
            $st->addProperty('Name', $name);
        }

        $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]/following::text()[{$this->contains($this->t('Marco Polo Club account'))}][1]", null, true, "/{$this->opt($this->t('Marco Polo Club account'))}\s+([X\d ]{5,})\b/u");

        if (!$number) {
            $number = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Marco Polo Club account'))}]", null, true, "/{$this->opt($this->t('Marco Polo Club account'))}[:：\s]*([-Xx\d ]{5,})$/u");
        }
        $number = str_replace(' ', '', $number);

        if (preg_match("/^(\d+)[Xx]+(\d+)$/", $number, $m)) {
            // 172XXXX649
            $numberMasked = $m[1] . '**' . $m[2];
            $st->setNumber($numberMasked)->masked('center')
                ->setLogin($numberMasked)->masked('center');
        } elseif (preg_match("/^\d+$/", $number)) {
            // 1723548649
            $st->setNumber($number)
                ->setLogin($number);
        }

        if ($name || $number) {
            $st->setNoBalance(true);
        }
        $login = $this->http->FindSingleNode("//text()[{$this->contains($this->t('We’ve noticed a new sign-in for your account'))}]", null, true, "/{$this->opt($this->t('We’ve noticed a new sign-in for your account'))}\s\((\S+[@]\S+\.\S+)\)\./u");

        if (!empty($login)) {
            $st->setLogin($login);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public function detectLang(): bool
    {
        foreach ($this->langDetector as $lang => $detect) {
            if ($this->http->XPath->query("//text()[{$this->contains($detect)}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
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
