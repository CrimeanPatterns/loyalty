<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingID extends \TAccountChecker
{
    public $mailFiles = "agoda/it-554321572.eml, agoda/it-554668190.eml, agoda/it-555936934.eml, agoda/it-555962794.eml";
    public $subjects = [
        'Agoda Booking ID ',
    ];

    public $lang = '';

    public $detectLang = [
        "pt" => ['Cumprimentos da Agoda'], // before en
        "en" => ['This email was sent by', 'Greetings from Agoda'],
        "zh" => ['此电子邮件发件人', 'Agoda向您問好'],
        "ja" => ['送信元：'],
        "es" => ['Email enviado por:'],
        "id" => ['Email ini dikirimkan oleh'],
        "fr" => ['E-mail envoyé par :', 'Recevez les salutations d\'Agoda'],
    ];

    public static $dictionary = [
        "en" => [
            'Country/Region:'                 => ['Country/Region:'],
            'Agoda Customer Experience Group' => ['Agoda Customer Experience Group', 'Greetings from Agoda!'],
            'With reference booking ID'       => ['With reference booking ID', 'With reference to your booking ID', 'With reference to the booking ID'],
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            //'Hotel:' => '',
            //'Arrival:' => '',
            //'Departure:' => '',
        ],
        "zh" => [
            'Country/Region:'                 => ['城市/国家地区：', '國家/地區：', '國家或地區/城市：'],
            'City:'                           => ['城市：'],
            'Agoda Customer Experience Group' => ['Agoda 客户体验团队', 'Agoda向您問好！', 'Agoda客戶服務團隊　敬上'],
            'With reference booking ID'       => ['订单 (预订编码', '關於您訂單編號', '關於您的訂單（編號'],
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            'Hotel:'     => ['住宿名称：', '住宿名稱：'],
            'Arrival:'   => ['抵达时间：', '入住日期：'],
            'Departure:' => ['退房时间：', '退房日期：'],
        ],
        "ja" => [
            'Country/Region:' => '所在都市/国または地域：',
            //'City:' => [''],
            'Agoda Customer Experience Group' => '今後ともアゴダをよろしくお願いいたします。',
            'With reference booking ID'       => 'ご予約詳細【ID：',
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            'Hotel:'     => '宿泊施設名：',
            'Arrival:'   => 'チェックイン日：',
            'Departure:' => 'チェックアウト日：',
        ],
        "es" => [
            'Country/Region:' => 'Ciudad/País o Región:',
            //'City:' => [''],
            'Agoda Customer Experience Group' => 'Equipo de Atención al Cliente de Agoda',
            'With reference booking ID'       => 'En relación a su reserva con ID',
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            'Hotel:'     => 'Hotel:',
            'Arrival:'   => 'Llegada:',
            'Departure:' => 'Salida: ',
        ],
        "id" => [
            'Country/Region:' => 'Kota/Negara atau Wilayah:',
            //'City:' => [''],
            'Agoda Customer Experience Group' => 'Customer Experience Group Agoda',
            'With reference booking ID'       => 'Mengacu pada ID pesanan Anda',
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            'Hotel:'     => 'Hotel:',
            'Arrival:'   => 'Check-in:',
            'Departure:' => 'Check-out:',
        ],
        "fr" => [
            'Country/Region:' => 'Ville/Pays ou Région :',
            //'City:' => [''],
            'Agoda Customer Experience Group' => ['Le Groupe Satisfaction Clientèle d\'Agoda', 'Recevez les salutations d\'Agoda'],
            'With reference booking ID'       => 'En référence à votre ID de réservation',
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            'Hotel:'     => 'Hotel:',
            'Arrival:'   => 'Arrivée :',
            'Departure:' => 'Départ :',
        ],
        "pt" => [
            'Country/Region:' => 'Cidade/País ou Região:',
            //'City:' => [''],
            'Agoda Customer Experience Group' => ['Cumprimentos da Agoda'],
            'With reference booking ID'       => 'Com referência à sua reserva de n.º',
            //'Customer First Name:' => '',
            //'Customer Last Name:' => '',
            'Hotel:'     => 'Hotel:',
            'Arrival:'   => 'Chegada:',
            'Departure:' => 'Partida:',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@agoda.com') !== false) {
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
        $this->detectLang();

        return $this->http->XPath->query("//text()[{$this->contains($this->t('Agoda Customer Experience Group'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('With reference booking ID'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Country/Region:'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]agoda\.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectLang();
        $this->ParseHotel($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseHotel(Email $email)
    {
        $nodes = $this->http->XPath->query("//text()[{$this->starts($this->t('With reference booking ID'))}]");

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $h->general()
                ->confirmation($this->http->FindSingleNode(".", $root, true, "/{$this->opt($this->t('With reference booking ID'))}\s*(\d{8,})/"));

            $firstName = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Customer First Name:'))}][1]", $root, true, "/\:\s*([[:alpha:]]+(?:[ \-][[:alpha:]]+)*)\s*$/u");
            $lastName = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Customer Last Name:'))}][1]", $root, true, "/\:\s*([[:alpha:]]+(?:[ \-][[:alpha:]]+)*)\s*$/");

            if (!empty($firstName) && !empty($lastName)) {
                $email->setSentToVendor(true);
                $h->general()
                    ->traveller($firstName . ' ' . $lastName, true);
            }

            $h->hotel()
                ->name($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Hotel:'))}][1]", $root, true, "/{$this->opt($this->t('Hotel:'))}\s*(.+)/"));

            $country = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Country/Region:'))}][1]", $root, true, "#{$this->opt($this->t('Country/Region:'))}\s*(.+)#u");
            $city = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('City:'))}][1]", $root, true, "/{$this->opt($this->t('City:'))}\s*(.+)/u");

            $h->hotel()
               ->address(trim($city . ', ' . $country, ','));

            $arrival = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Arrival:'))}][1]", $root, true, "/{$this->opt($this->t('Arrival:'))}\s*(.+)/");

            if (!empty($arrival)) {
                $h->booked()
                    ->checkIn(strtotime($arrival));
            }

            $departure = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Departure:'))}][1]", $root, true, "/{$this->opt($this->t('Departure:'))}\s*(.+)/");

            if (!empty($departure)) {
                $h->booked()
                    ->checkOut(strtotime($departure));
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public function detectLang()
    {
        foreach ($this->detectLang as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//text()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
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
