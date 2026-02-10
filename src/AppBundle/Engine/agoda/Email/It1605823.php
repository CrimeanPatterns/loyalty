<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It1605823 extends \TAccountChecker
{
    public $mailFiles = "agoda/it-1605823.eml, agoda/it-276335367.eml";

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            //            'Dear' => '',
            'your booking ID' => 'to your booking ID',
            //            'Hotel:' => '',
            //            'Room Type:' => '',
            'City/Country:' => ['City/Country:', 'City/Country or Region:'],
            //            'Arrival:' => '',
            //            'Departure:' => '',
            'Cancellation Policy:' => ['Cancellation Policy:', 'Cancellation and Change Policy:'],
        ],
        'zh' => [
            'Dear'                 => '親愛的',
            'your booking ID'      => ['預訂編號', '關於您的訂單（編號'],
            'Hotel:'               => ['飯店：', '住宿名稱：'],
            'Room Type:'           => '房型：',
            'City/Country:'        => ['城市/國家地區：', '國家/城市：'],
            'Arrival:'             => '入住日期：',
            'Departure:'           => '退房日期：',
            'Cancellation Policy:' => '取消政策',
        ],
        'pt' => [
            'Dear'            => 'Estimado',
            'your booking ID' => 'ID da sua reserva',
            'Hotel:'          => 'Hotel:',
            'Room Type:'      => 'Tipo de Quarto:',
            'City/Country:'   => 'Cidade/País:',
            'Arrival:'        => 'Chegada:',
            'Departure:'      => 'Partida:',
            //            'Cancellation Policy:' => '',
        ],
        'ru' => [
            'Dear'            => 'Здравствуйте,',
            'your booking ID' => 'касается вашего бронирования',
            'Hotel:'          => 'Отель:',
            //            'Room Type:' => ':',
            'City/Country:' => 'Город/страна или регион:',
            'Arrival:'      => 'Заезд',
            'Departure:'    => 'Выезд:',
            //            'Cancellation Policy:' => '',
        ],
        'ar' => [
            'Dear'            => ['المحترم،', 'مرحبًا'],
            'your booking ID' => 'رقم حجزك التعريفي',
            'Hotel:'          => 'الفندق:',
            //            'Room Type:' => ':',
            'City/Country:' => 'المدينة أو الدولة أو المنطقة:',
            'Arrival:'      => 'الوصول:',
            'Departure:'    => 'المغادرة:',
            //            'Cancellation Policy:' => '',
        ],
        'ko' => [
            'Dear'            => '님, 안녕하세요',
            'your booking ID' => '예약 번호',
            'Hotel:'          => '숙소명:',
            //            'Room Type:' => ':',
            'City/Country:' => '도시/국가 또는 지역:',
            'Arrival:'      => '체크인:',
            'Departure:'    => '체크아웃:',
            //            'Cancellation Policy:' => '',
        ],
        'th' => [
            'Dear'            => 'เรียน คุณ',
            'your booking ID' => 'รายละเอียดของการจองหมายเลข',
            'Hotel:'          => 'ที่พัก:',
            //            'Room Type:' => ':',
            'City/Country:' => 'เมือง/ประเทศหรือภูมิภาค:',
            'Arrival:'      => 'วันเช็คอิน:',
            'Departure:'    => 'วันเช็คเอาต์:',
            //            'Cancellation Policy:' => '',
        ],
        'ja' => [
            'Dear'            => '様',
            'your booking ID' => ['référence à votre ID de réservation', 'ID：'],
            'Hotel:'          => '宿泊施設名：',
            //            'Room Type:' => ':',
            'City/Country:' => '所在都市/国または地域：',
            'Arrival:'      => 'チェックイン日：',
            'Departure:'    => 'チェックアウト日：',
            //            'Cancellation Policy:' => '',
        ],
        'es' => [
            'Dear'            => ['Estimado/a', 'Estimado'],
            'your booking ID' => 'En relación a su reserva con ID',
            'Hotel:'          => 'Hotel:',
            //            'Room Type:' => ':',
            'City/Country:' => 'Ciudad/País o Región:',
            'Arrival:'      => 'Llegada:',
            'Departure:'    => 'Salida:',
            //            'Cancellation Policy:' => '',
        ],
    ];

    public function ParseHotel(Email $email)
    {
        $xpath = "//text()[{$this->starts($this->t('City/Country:'))}]/ancestor::*[{$this->contains($this->t('Dear'))}][1]";
        $notes = $this->http->XPath->query($xpath);

        foreach ($notes as $root) {
            $conf = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('your booking ID'))}]",
                $root, true, "/{$this->opt($this->t('your booking ID'))}\s*(\d{5,})\D/u");
            $hotelname = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Hotel:'))}]",
                $root, true, "/{$this->opt($this->t('Hotel:'))}\s*(.+)/u");

            foreach ($email->getItineraries() as $it) {
                if ($it->getHotelName() == $hotelname
                    && in_array($conf, array_column($it->getConfirmationNumbers(), 0))) {
                    continue 2;
                }
            }

            $h = $email->add()->hotel();

            // General
            $h->general()
                ->confirmation($conf);
            $traveller = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('City/Country:'))}]/preceding::text()[{$this->starts($this->t('Dear'))}][1]",
                $root, true, "/{$this->opt($this->t('Dear'))}\s*(\D+?)[,:：!،]?\s*$/u");

            if (empty($traveller) && in_array($this->lang, ['ar', 'ko', 'ja'])) {
                $traveller = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('City/Country:'))}]/preceding::text()[{$this->contains($this->t('Dear'))}][1]",
                    $root, true, "/^(\D+?)\s*{$this->opt($this->t('Dear'))}\s*$/u");
            }
            $h->general($traveller)
                ->traveller($traveller)
                ->cancellation($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Cancellation Policy:'))}]",
                    $root, true, "/{$this->opt($this->t('Cancellation Policy:'))}\s*(.+)/u"), true, true);

            // Hotel
            $h->hotel()
                ->name($hotelname)
                ->address($this->http->FindSingleNode(".//text()[{$this->starts($this->t('City/Country:'))}]",
                    $root, true, "/{$this->opt($this->t('City/Country:'))}\s*(.+)/u"));

            // Booked
            $h->booked()
                ->checkIn(strtotime($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Arrival:'))}]",
                    $root, true, "/{$this->opt($this->t('Arrival:'))}\s*(.+)/u")))
                ->checkOut(strtotime($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Departure:'))}]",
                    $root, true, "/{$this->opt($this->t('Departure:'))}\s*(.+)/u")));

            // Rooms
            $type = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Room Type:'))}]",
                $root, true, "/{$this->opt($this->t('Room Type:'))}\s*(.+)/u");

            if (!empty($type)) {
                $h->addRoom()
                    ->setType($type);
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@agoda.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Agoda Booking ID') !== false
            && (stripos($headers['subject'], 'Check-in') !== false || stripos($headers['subject'], 'Check in') !== false || stripos($headers['subject'], '入住日期') !== false);
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Agoda Customer") or contains(.,"www.agoda.com") or contains(.,"@agoda.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.agoda.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['City/Country:']) && $this->http->XPath->query("//*[" . $this->contains($dict['City/Country:']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['City/Country:']) && $this->http->XPath->query("//*[" . $this->contains($dict['City/Country:']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->ParseHotel($email);

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

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
