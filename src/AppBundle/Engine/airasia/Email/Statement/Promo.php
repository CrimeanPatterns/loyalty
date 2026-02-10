<?php

namespace AwardWallet\Engine\airasia\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Promo extends \TAccountChecker
{
    public $mailFiles = "airasia/it-509817579.eml, airasia/it-509817584.eml, airasia/it-581482855.eml, airasia/statements/it-534028000.eml, airasia/statements/it-536184734.eml, airasia/statements/it-536190194.eml, airasia/statements/it-536263174.eml";
    public $subjects = [
        '/^Plan the perfect/',
    ];

    public $lang = '';

    public $detectLang = [
        "en" => ['Manage your email preferences', 'here'],
        "ko" => ['에어아시아 포인트:', '여기에'],
        "id" => ['Atur preferensi email anda'],
        "th" => ['ตั้งค่าอีเมลของคุณที่นี่'],
        "ja" => ['Eメール配信設定は'],
        "zh" => ['管理您的電子郵件偏好。', '從這裡', '点击', '我的预订'],
        "vi" => ['Quản lý tùy chọn email của bạn'],
    ];

    public static $dictionary = [
        "en" => [
            'airasia Points Balance:'  => ['airasia points:', 'airasia Points Balance:'],
            'BOOK NOW'                 => ['BOOK NOW', 'SHOP NOW', 'Answer survey', 'Join Us Now', 'SUBSCRIBE NOW', 'START SHOPPING', 'Book Now', 'START SHOPPING NOW', 'ENTER NOW', 'SIGN UP NOW', 'SIGN UP', 'PRE-BOOK NOW', 'Manage your email preferences'],
            'Dear'                     => ['Dear', 'Hi'],
        ],

        "ko" => [
            'airasia Points Balance:' => ['에어아시아 포인트:', 'airasia Points Balance:', '에어아시아 포인트'],
            'BOOK NOW'                => ['예약하기'],
            //'Dear' => ['']
        ],
        "id" => [
            'airasia Points Balance:' => ['Saldo airasia Points:', 'airasia points:', 'airasia Points Balance:'],
            'BOOK NOW'                => ['Catat Promonya!', 'PESAN SEKARANG', 'MULAI BELANJA', 'Pesan Sekarang', 'BOOK NOW', 'SHOP NOW'],
            //'Dear' => ['']
        ],
        "th" => [
            'airasia Points Balance:' => ['airasia points คงเหลือ:', 'airasia points:'],
            'BOOK NOW'                => ['ตอบแบบสอบถาม', 'จองเลย', 'ตั้งค่าอีเมลของคุณที่นี่'],
            'Dear'                    => ['สวัสดี'],
        ],
        "ja" => [
            'airasia Points Balance:' => ['エアアジアポイント:', 'エアアジアポイント', 'airasia Points Balance:'],
            'BOOK NOW'                => ['ご予約はこちらから', 'ご予約はこちらから', '今すぐ予約'],
            //'Dear' => ['']
        ],
        "zh" => [
            'airasia Points Balance:' => ['亚航积分:', 'airasia Points Balance:', '亚航积分'],
            'BOOK NOW'                => ['立即訂購', '立即預訂', '立即订购'],
            //'Dear' => ['']
        ],
        "vi" => [
            'airasia Points Balance:' => ['airasia points:', 'airasia Points Balance:'],
            'BOOK NOW'                => ['ĐẶT NGAY'],
            //'Dear' => ['']
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@promo.airasia.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $this->assignLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('airasia Points Balance:'))}]")->length > 0
            && $this->http->XPath->query("//a[contains(@href, '.airasia.com')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('BOOK NOW'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]promo\.airasia\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[{$this->starts($this->t('BOOK NOW'))}]/preceding::text()[{$this->starts($this->t('airasia Points Balance:'))}][1]", null, true, "/{$this->opt($this->t('airasia Points Balance:'))}\s*(\d+)/");

        if ($balance !== null) {
            $st->setBalance($balance);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/^{$this->opt($this->t('Dear'))}\s*(\D+)(?:\s|\,)/");

        if (!empty($name)) {
            $st->addProperty('Name', trim($name, ','));
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

    private function assignLang()
    {
        foreach ($this->detectLang as $lang => $array) {
            foreach ($array as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
