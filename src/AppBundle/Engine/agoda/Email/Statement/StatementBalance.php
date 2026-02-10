<?php

namespace AwardWallet\Engine\agoda\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class StatementBalance extends \TAccountChecker
{
    public $mailFiles = "agoda/statements/it-70626031.eml, agoda/statements/it-70639942.eml, agoda/statements/it-70653062.eml, agoda/statements/it-70654612.eml, agoda/statements/it-70963912.eml, agoda/statements/it-70968306.eml, agoda/statements/it-70983479.eml, agoda/statements/it-71005395.eml, agoda/statements/it-71011828.eml, agoda/statements/it-71026295.eml, agoda/statements/it-71028448.eml";
    public $subjects = [
        '/^Not one, but TWO special discounts for your next trip to/',
    ];

    public $lang = '';

    public $detectLang = [
        'en' => 'Balance',
        'th' => 'มูลค่าคงเหลือ',
        'ms' => 'Baki',
        'zh' => '餘額',
        'vi' => 'Tiền',
        'ko' => '잔액',
        'id' => 'Saldo AgodaTunai',
        'ar' => 'رصيد',
        'fr' => 'Solde',
        'ja' => '残高',
        'no' => 'Din AgodaBonus:',
        'de' => 'Ihr Agoda-Münzen-Guthaben:',
        'it' => 'Saldo di AgodaCash:',
        'pl' => 'Saldo Twoich środków Agoda:',
        'es' => 'Saldo de tus MonedasAgoda:',
        'pt' => 'Saldo',
    ];

    public static $dictionary = [
        "en" => [
            'bookings completed' => ['bookings completed', 'Use coupon'],
            'Get back to your'   => ['Get back to your'],
            'search for'         => ['search for'],
        ],
        "th" => [
            'Your AgodaCash Balance:' => 'ท่านมี AgodaCash มูลค่าคงเหลือ:',
            'bookings completed'      => 'การจองที่เช็คเอาต์แล้ว',
            'Get back to your'        => ['ค้นหาที่พัก'],
            'search for'              => ['อีกครั้ง'],
        ],
        "ms" => [
            'Your AgodaCash Balance:' => 'Baki Ganjaran Agoda:',
            'bookings completed'      => 'tempahan selesai',
            // 'Get back to your' => [''],
            // 'search for' => [''],
        ],
        "zh" => [
            'Your AgodaCash Balance:' => ['你的A金餘額：', 'Agoda住賞錢餘額：'],
            'bookings completed'      => ['已完成', '使用折扣碼', '使用優惠券', '繼續搜尋'],
            'Get back to your'        => ['返回你的'],
            'search for'              => ['搜尋'],
        ],
        "vi" => [
            'Your AgodaCash Balance:' => 'Tiền Agoda của bạn hiện có:',
            'bookings completed'      => ['Để khuyến khích đi lại an toàn', 'Sử dụng phiếu giảm giá'],
            // 'Get back to your' => [''],
            // 'search for' => [''],
        ],
        "ko" => [
            'Your AgodaCash Balance:' => '고객님의 AgodaCash 잔액:',
            'bookings completed'      => ['할인 쿠폰 사용하기', '건 숙박 완료'],
            'Get back to your'        => ['\' 검색 결과 페이지로'],
            'search for'              => ['돌아가기'],
        ],
        "pt" => [
            'Your AgodaCash Balance:' => 'Saldo do seu Vale-Agoda:',
            'bookings completed'      => 'Usar cupão',
            'Get back to your'        => ['Volte à sua'],
            'search for'              => ['pesquisa de'],
        ],
        'ar' => [
            'Your AgodaCash Balance:' => 'رصيد فلوس أجودا:',
            'bookings completed'      => 'استخدم الكوبون',
            'Get back to your'        => ['الرجوع إلى'],
            'search for'              => ['بحثك في'],
        ],
        "fr" => [
            'Your AgodaCash Balance:' => 'Solde de votre Bon Agoda :',
            'bookings completed'      => ['réservation(s) effectuée(s)', 'Utiliser ce bon'],
            'Get back to your'        => ['Retourner à votre'],
            'search for'              => ['recherche pour'],
        ],
        "id" => [
            'Your AgodaCash Balance:' => 'Saldo AgodaTunai Anda:',
            'bookings completed'      => 'Gunakan kupon',
            // 'Get back to your' => [''],
            // 'search for' => [''],
        ],
        "ja" => [
            'Your AgodaCash Balance:' => 'アゴダコイン残高',
            'bookings completed'      => 'クーポンを使う',
            'Get back to your'        => ['検索に戻る'],
            'search for'              => ['の'],
        ],
        "no" => [
            'Your AgodaCash Balance:' => 'Din AgodaBonus:',
            'bookings completed'      => 'Bruk kupong',
            'Get back to your'        => ['Gå tilbake til'],
            'search for'              => ['søket etter'],
        ],
        "de" => [
            'Your AgodaCash Balance:' => 'Ihr Agoda-Münzen-Guthaben:',
            // 'bookings completed'      => 'Bruk kupong',
            'Get back to your' => ['Zurück zur'],
            'search for'       => ['Suche in'],
        ],
        "it" => [
            'Your AgodaCash Balance:' => 'Saldo di AgodaCash:',
            'bookings completed'      => 'Usa il coupon',
            'Get back to your'        => ['Torna alla tua'],
            'search for'              => ['ricerca per'],
        ],
        "pl" => [
            'Your AgodaCash Balance:' => 'Saldo Twoich środków Agoda:',
            // 'bookings completed'      => '',
            'Get back to your' => ['Wróć do'],
            'search for'       => ['wyszukiwania:'],
        ],
        "es" => [
            'Your AgodaCash Balance:' => 'Saldo de tus MonedasAgoda:',
            'bookings completed'      => 'Usar cupón',
            'Get back to your'        => ['Volver a tu'],
            'search for'              => ['búsqueda para'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@sg.newsletter.agoda-emails.com') !== false) {
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
        if ($this->AssignLang() == true) {
            return $this->http->XPath->query("//text()[{$this->contains($this->t('Agoda'))}]")->length > 0
                && ($this->http->XPath->query("//node()[{$this->contains($this->t('bookings completed'))}]")->length > 0
                || $this->http->XPath->query("//td[not(.//td)][.//text()[{$this->eq($this->t('Get back to your'))}] and .//text()[{$this->eq($this->t('search for'))}]]")->length > 0)
                && $this->http->XPath->query("//text()[{$this->contains($this->t('Your AgodaCash Balance:'))}]")->length > 0;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]sg\.newsletter\.agoda-emails\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->AssignLang();

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your AgodaCash Balance:'))}]/preceding::text()[normalize-space()][1]");
        $st->addProperty('Name', trim($name, ','));

        $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your AgodaCash Balance:'))}]/following::text()[normalize-space()][1]", null, true, "/^\D{1,3}\s*([\d\,\.]+)/");

        if ($balance == null) {
            $balance = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your AgodaCash Balance:'))}]/following::text()[normalize-space()][1]", null, true, "/^([\d\,\.]+)\s*\D{1,3}/");
        }
        $st->setBalance(str_replace(['.', ','], '', $balance));

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function AssignLang()
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $word) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$word}')]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }
}
