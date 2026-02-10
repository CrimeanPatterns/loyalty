<?php

namespace AwardWallet\Engine\ctrip\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;
use function GuzzleHttp\Psr7\str;

class Account extends \TAccountChecker
{
    public $mailFiles = "ctrip/statements/it-105773112.eml, ctrip/statements/it-105811090.eml, ctrip/statements/it-106218511.eml, ctrip/statements/it-106315100.eml, ctrip/statements/it-106397427.eml";

    private $detectFrom = ['@newsletter.trip.com'];

    private $detectBody = [
        'en' => ['to receive this type of promotional email from Trip.com', 'to receive this type of promotional emails from Trip.com'],
        'es' => ['Si no deseas recibir este tipo de correos promocionales de Trip.com'],
        'pt' => ['Se preferir não receber e-mails promocionais do Trip.com, ajuste as preferências'],
        'ko' => ['트립닷컴 프로모션 이메일의 수신을 원하지 않는 경우, 알림 설정을 변경하거나 이메일 수신을'],
        'zh' => ['如您不希望接收 Trip.com 此類推廣郵件', '如您不希望接收 Trip.com 此類的電子郵件'],
        'ru' => ['Если вы не хотите больше получать уведомления об акциях от Trip.com, вы'],
        'ja' => ['Trip.comのキャンペーンメールの受信を希望しない場合は、設定を変更するか、'],
        'th' => ['หากคุณไม่ต้องการรับอีเมลโปรโมชั่นจาก Trip.com คุณปิดการแจ้งเตือนหรือยกเลิกการรับอีเมลโปร'],
        'it' => ['servizio per motivi che riguardano il tuo account Trip.com o una prenotazione effettuata'],
        'de' => ['Wir haben Ihnen diese Service-E-Mail wegen Ihrem Trip.com-Konto oder einer Buchung bei uns geschickt'],
    ];

    private $lang = 'en';
    private static $dictionary = [
        'en' => [
//            'Hi ' => '',
            'Trip.com Member' => 'Trip.com Member',
            'Tier:' => 'Tier:',
//            'Trip Coins:' => '',
        ],
        'es' => [
            'Hi ' => '¡Hola,',
            'Trip.com Member' => 'Miembro de Trip.com',
            'Tier:' => 'Categoría:',
            'Trip Coins:' => 'Trip Coins:',
        ],
        'ko' => [
            'Hi ' => '안녕하세요',
            'Trip.com Member' => '트립닷컴 회원님',
            'Tier:' => '등급:',
            'Trip Coins:' => '트립코인:',
        ],
        'pt' => [
            'Hi ' => 'Hi ',
            'Trip.com Member' => 'Membro do Trip.com',
            'Tier:' => 'Nível:',
            'Trip Coins:' => 'Trip Coins:',
        ],
        'zh' => [
            'Hi ' => 'Hi ',
            'Trip.com Member' => 'Trip.com 會員',
            'Tier:' => ['會員等級：', '等級：'],
            'Trip Coins:' => 'Trip Coins：',
        ],
        'ru' => [
            'Hi ' => 'Приветствуем,',
            'Trip.com Member' => 'Пользователь Trip.com',
            'Tier:' => 'Уровень:',
            'Trip Coins:' => 'Trip Coins:',
        ],
        'ja' => [
            'Hi ' => 'こんにちは、',
            'Trip.com Member' => 'Trip.com会員 さん',
            'Tier:' => 'ステイタス：',
            'Trip Coins:' => 'Trip Coins：',
        ],
        'th' => [
            'Hi ' => 'สวัสดี ',
//            'Trip.com Member' => '',
            'Tier:' => 'สถานภาพสมาชิก:',
//            'Trip Coins:' => '',
        ],
        'it' => [
            'Hi ' => 'Ciao ',
            'Trip.com Member' => 'Membro Trip.com',
            'Tier:' => 'Livello:',
//            'Trip Coins:' => '',
        ],
        'de' => [
            'Hi ' => 'Hallo,',
//            'Trip.com Member' => '',
            'Tier:' => 'Ebene:',
            'Trip Coins:' => 'Trip Coins:',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $dFrom) {
            if (stripos($from, $dFrom) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) === true) {
            return true;
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[" . $this->contains(['www.trip.com'], '@href') . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($dBody) . "]")->length > 0
                && !empty(self::$dictionary[$lang]) && !empty(self::$dictionary[$lang]['Tier:'])
                && $this->http->XPath->query("//text()[" . $this->contains(self::$dictionary[$lang]['Tier:']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query("//text()[" . $this->contains(self::$dictionary[$lang]['Tier:']) . "]")->length > 0) {
                $this->lang = $lang;
                break;
            }
        }

        $st = $email->add()->statement();

        $st
            ->setMembership(true)
        ;

        $name = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi ")) . "]",
            null, true, "/^\s*" . $this->preg_implode($this->t("Hi ")) . "\s*([[:alpha:]][[:alpha:] \-]+)(?:!|さん|)\s*$/u");
        if (!empty($name) && !preg_match("/(?:".$this->opt($this->t("Trip.com Member"))."|trip\.com)/iu", $name)) {
            $st
                ->addProperty("Name", $name);
        }

        $st
            ->addProperty("Level", $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Tier:")) . "])[1]/following::text()[normalize-space()][1]",
            null, true, "/^\s*([[:alpha:]\p{Thai} ]+)\s*$/u"))
        ;

        $coins = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Trip Coins:")) . "])[1]/following::text()[normalize-space()][1]");
        if (empty($coins)) {
            $coins = $this->http->FindSingleNode("(//tr[descendant::text()[normalize-space()][1][" . $this->eq($this->t("Tier:")) . "] and contains(., '|')])[1]",
                null, true, "/" . $this->opt($this->t("Tier:")) . "\s*[[:alpha:] ]+\s*\|\s*[\w\s]+[:：]\s*(.+)/u");
        }
        if (preg_match("/^\s*(\d[\d,]*)\s*\((\D{0,5}\d[\d,. ]*\D{0,5})\)/u", $coins, $m)) {
            $st
                ->setBalance(str_replace([" ", ","], '', $m[1]))
                ->addProperty("TripCoins", $m[2]);
        } elseif (empty($coins)) {
            $st
                ->setNoBalance(true);
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }

}
