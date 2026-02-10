<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CancelledBooking2 extends \TAccountChecker
{
    public $mailFiles = "agoda/it-186615805.eml, agoda/it-187086294.eml, agoda/it-187122182.eml, agoda/it-187847194.eml, agoda/it-188865804.eml, agoda/it-189580664.eml";

    public $detectSubjects = [
        // en
        'Cancelled Agoda Booking ID',
        // pt
        'ID de Reserva Cancelada - Agoda Booking ID',
        // zh
        '預訂取消通知 - 預訂編號 Agoda Booking ID',
        '預訂已取消 Agoda Booking ID',
        // ja
        '【ホテル予約アゴダ】キャンセルのお知らせ［Agoda Booking ID',
        // es
        'Cancelacion de la Reserva - Agoda Booking ID',
        // it
        'Prenotazione Annullata - Agoda Booking ID',
        // ko
        '예약이 취소되었습니다. Check-in',
        // he
        'בוטלה, צ\'ק-אין תוכנן לתאריך',
        // ar
        ' - الحجز الملغى رقم',
        // ro
        'ID-ul rezervării anulate (Agoda Booking ID',
        // sv
        'har annullerats',
        // tr
        'İptal Edilen Agoda Booking ID',
        // fr
        'Annulation de la réservation - Agoda Booking ID',
        // de
        'Stornierte Buchung - Agoda Booking ID',
        // ru
        'Отмененное бронирование - Agoda Booking ID',
        // pl
        'Identyfikator anulowanej rezerwacji (Agoda Booking ID',
        // nl
        'Geannuleerde Agoda Booking ID',
        // el
        'ακυρώθηκε και check-in στις',
        // id
        'ID Pemesanan yang Dibatalkan Agoda Booking ID',
    ];

    public $lang = '';
    public $emailSubject;

    public static $dictionary = [
        "en" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Your cancelled booking',
            'Booking ID'             => 'Booking ID',
            //            'Check in' => '',
            //            'Check out' => '',
            //            'Lead guest' => '',
            //            'Reservation' => '',
            'CancelledText' => [
                'Your booking has been successfully cancelled',
                'We have confirmed the cancellation of your booking at',
                'Your booking has been cancelled',
                'not be completed and has been cancelled',
            ],
            //            'Keep your booking reference number' => '',
        ],
        "pt" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => ['A sua reserva cancelada', 'Sua reserva cancelada'],
            'Booking ID'             => 'ID de reserva',
            'Check in'               => 'Check-in',
            'Check out'              => 'Check-out',
            'Lead guest'             => 'Hóspede responsável pela reserva',
            'Reservation'            => 'Reserva',
            'CancelledText'          => [
                'A sua reserva foi cancelada com êxito',
                'Confirmámos o cancelamento da sua reserva no',
                'A sua reserva foi cancelada gratuitamente',
            ],
            'Keep your booking reference number' => 'Tenha o número da reserva (',
        ],
        "zh" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => ['已取消的預訂', '已取消的预订'],
            'Booking ID'             => ['訂單編號', '预订编码', '預訂編號'],
            'Check in'               => ['入住', '入住日期'],
            'Check out'              => ['退房', '退房日期'],
            'Lead guest'             => ['主要住客', '客人代表'],
            'Reservation'            => ['預訂資訊', '预订信息', '預訂概要'],
            'CancelledText'          => [
                '您的訂單已成功取消',
                '我們已確認取消您在',
                '您的訂單已免費取消',
                '你的訂單已免費取消',
                '您的订单已成功取消',
                '你的預訂已取消',
            ],
            'Keep your booking reference number' => '請隨時準備好您的訂單編號',
        ],

        "ja" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'キャンセル済みのご予約',
            'Booking ID'             => '予約ID',
            'Check in'               => 'チェックイン日',
            'Check out'              => 'チェックアウト日',
            'Lead guest'             => '宿泊代表者名',
            'Reservation'            => '予約内容',
            'CancelledText'          => [
                'キャンセル済みのご予約',
            ],
            //'Keep your booking reference number' => '',
        ],

        "es" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Tu reserva cancelada',
            'Booking ID'             => 'Número de reserva',
            'Check in'               => 'Llegada',
            'Check out'              => 'Salida',
            'Lead guest'             => 'Huésped principal',
            'Reservation'            => 'Reserva',
            'CancelledText'          => [
                'Tu reserva cancelada',
            ],
            //'Keep your booking reference number' => '',
        ],
        "it" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Prenotazione cancellata',
            'Booking ID'             => 'Numero di prenotazione',
            'Check in'               => 'Check-in',
            'Check out'              => 'Check-out',
            'Lead guest'             => 'Ospite principale',
            'Reservation'            => 'Prenotazione',
            'CancelledText'          => [
                'La prenotazione è stata cancellata con successo.',
                'La tua prenotazione è stata annullata',
                'tua prenotazione e per questo è stata annullata',
            ],
            //'Keep your booking reference number' => '',
        ],
        "ko" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => '취소된 예약',
            'Booking ID'             => '예약 번호',
            'Check in'               => '체크인',
            'Check out'              => '체크아웃',
            'Lead guest'             => '대표 투숙객',
            'Reservation'            => '객실 수 및 숙박 수',
            'CancelledText'          => [
                '고객님의 예약이 성공적으로 취소되었습니다.',
                '고객님의 예약 요청이 확정될 수 없어 예약이 취소되었습니다',
            ],
            //'Keep your booking reference number' => '',
        ],
        "he" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'ההזמנה המבוטלת שלכם',
            'Booking ID'             => 'מספר הזמנה',
            'Check in'               => 'צ\'ק-אין',
            'Check out'              => 'תשלום',
            'Lead guest'             => 'אורח ראשי',
            'Reservation'            => 'הזמנה',
            'CancelledText'          => [
                'אישרנו את ביטול ההזמנה שלכם ב- ',
                'הזמנתכם בוטלה בהצלחה',
                'הזמנתכם בוטלה ללא חיוב',
            ],
            //'Keep your booking reference number' => '',
        ],
        "ar" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'حجزك المُلغى',
            'Booking ID'             => 'رقم الحجز التعريفي',
            'Check in'               => 'تسجيل الوصول',
            'Check out'              => 'تسجيل المغادرة',
            'Lead guest'             => 'النزيل الرئيسي',
            'Reservation'            => 'الحجز',
            'CancelledText'          => [
                'تم إلغاء حجزك بنجاح',
                'لقد أكّدنا على إلغاء حجزك في',
            ],
            //'Keep your booking reference number' => '',
        ],
        "ro" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Rezervarea dvs. anulată',
            'Booking ID'             => 'ID Rezervare',
            'Check in'               => 'Check-in',
            'Check out'              => 'Check-out',
            'Lead guest'             => 'Oaspete principal',
            'Reservation'            => 'Rezervare',
            'CancelledText'          => [
                'Am confirmat anularea rezervării dvs.',
                'Rezervarea dumneavoastră a fost anulată cu succes',
            ],
            //'Keep your booking reference number' => '',
        ],
        "sv" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Din annullerade avbokning',
            'Booking ID'             => 'Boknings-ID',
            'Check in'               => 'Incheckning',
            'Check out'              => 'Utcheckning',
            'Lead guest'             => 'Huvudgäst',
            'Reservation'            => 'Bokning',
            'CancelledText'          => [
                'Din bokning har annullerats',
                'Vi har bekräftat annulleringen av din bokning',
            ],
            //'Keep your booking reference number' => '',
        ],
        "tr" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'İptal edilen rezervasyonunuz',
            'Booking ID'             => 'Rezervasyon ID',
            'Check in'               => 'Giriş',
            'Check out'              => 'Çıkış',
            'Lead guest'             => 'Ana konuk',
            'Reservation'            => 'Rezervasyon',
            'CancelledText'          => [
                'Rezervasyonunuz iptal edildi',
                'rezervasyonunuzun iptalini onayladık.',
            ],
            //'Keep your booking reference number' => '',
        ],
        "fr" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Votre réservation annulée',
            'Booking ID'             => 'N° de réservation',
            'Check in'               => 'Arrivée',
            'Check out'              => 'Départ',
            'Lead guest'             => 'Hôte principal',
            'Reservation'            => 'Réservation',
            'CancelledText'          => [
                'Votre réservation a été annulée avec succès.',
                'Nous avons confirmé l\'annulation de votre réservation',
                'votre réservation a été annulée par l\'établissement',
                'a été annulée par l\'établissement',
            ],
            //'Keep your booking reference number' => '',
        ],
        "de" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Ihre stornierte Buchung',
            'Booking ID'             => 'Buchungsnummer',
            'Check in'               => 'Check-in',
            'Check out'              => 'Check-out',
            'Lead guest'             => 'Hauptgast',
            'Reservation'            => 'Reservierung',
            'CancelledText'          => [
                'Ihre Buchung wurde erfolgreich storniert',
                'Wir haben die Stornierung Ihrer Buchung in der Unterkunft',
            ],
            //'Keep your booking reference number' => '',
        ],
        "ru" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Ваше отмененное бронирование',
            'Booking ID'             => 'Номер бронирования',
            'Check in'               => 'Заезд',
            'Check out'              => 'Отъезд',
            'Lead guest'             => 'Основной гость',
            'Reservation'            => 'Бронирование',
            'CancelledText'          => [
                'Мы подтверждаем отмену вашего бронирования',
                'Ваше бронирование бесплатно отменено.',
            ],
            //'Keep your booking reference number' => '',
        ],
        "pl" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Anulowana rezerwacja',
            'Booking ID'             => 'Identyfikator rezerwacji',
            'Check in'               => 'Zameldowanie',
            'Check out'              => 'Wymeldowanie',
            'Lead guest'             => 'Główny gość',
            'Reservation'            => 'Rezerwacja',
            'CancelledText'          => [
                'Twoja rezerwacja została anulowana',
                'Twoja rezerwacja została bezpłatnie anulowana.',
            ],
            //'Keep your booking reference number' => '',
        ],
        "nl" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Uw geannuleerde boeking',
            'Booking ID'             => 'Boekingsnummer',
            'Check in'               => 'Inchecken',
            'Check out'              => 'Uitchecken',
            'Lead guest'             => 'Hoofdgast',
            'Reservation'            => 'Reservering',
            'CancelledText'          => [
                'Uw boeking is met succes geannuleerd',
                'Uw boeking is gratis geannuleerd.',
            ],
            //'Keep your booking reference number' => '',
        ],
        "el" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Η κράτηση που ακυρώσατε',
            'Booking ID'             => 'Αριθμός κράτησης',
            'Check in'               => 'Check-in',
            'Check out'              => 'Check-out',
            'Lead guest'             => 'Κύριος επισκέπτης',
            'Reservation'            => 'Κράτηση',
            'CancelledText'          => [
                'κράτησή σας ακυρώθηκε δωρεάν.',
                'Η κράτησή σας ακυρώθηκε επιτυχώς.',
            ],
            //'Keep your booking reference number' => '',
        ],
        "id" => [
            'Agoda Booking ID'       => 'Agoda Booking ID', // from subject
            'Your cancelled booking' => 'Pesanan yang Anda batalkan',
            'Booking ID'             => 'ID Pesanan',
            'Check in'               => 'Check-in',
            'Check out'              => 'Check-out',
            'Lead guest'             => 'Tamu utama',
            'Reservation'            => 'Reservasi',
            'CancelledText'          => [
                'Pesanan Anda telah berhasil dibatalkan',
            ],
            //'Keep your booking reference number' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || stripos($headers['from'], '@agoda.com') === false) {
            return false;
        }

        foreach ($this->detectSubjects as $subject) {
            if (strpos($headers['subject'], $subject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.agoda.com')]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (isset($dict['CancelledText']) && $this->http->XPath->query("//*[" . $this->contains($dict['CancelledText']) . "]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@agoda.com') !== false;
    }

    public function ParseHotel(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking ID'))}]/following::text()[normalize-space()][1]",
            null, true, "/^\s*(\d{5,})\s*$/");

        if (empty($conf) && preg_match("/{$this->opt($this->t('Agoda Booking ID'))} ?(\d{5,})\b/", $this->emailSubject, $m)) {
            $conf = $m[1];
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Keep your booking reference number'))}]",
                null, true, "/{$this->opt($this->t('Keep your booking reference number'))}\s*(\b\d{5,})\b/");
        }

        $h->general()
            ->confirmation($conf)
            ->traveller($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Lead guest")) . "]/following::text()[normalize-space()][1]"), true)
        ;

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("CancelledText")) . "])[1]"))) {
            $h->general()
                ->cancelled()
                ->status('Cancelled');
        }

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your cancelled booking")) . "]/following::text()[normalize-space()][1]"), true)
            ->address($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Your cancelled booking")) . "]/following::text()[normalize-space()][2]"), true)
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check in")) . "]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Check out")) . "]/following::text()[normalize-space()][1]")))
            ->rooms($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*(\d+) x /"))
        ;

        // Rooms
        $h->addRoom()
            ->setType($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation")) . "]/following::text()[normalize-space()][1]",
                null, true, "/^\s*\d+ x (.+)/"))
        ;

        return $h;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['CancelledText']) && $this->http->XPath->query("//*[" . $this->contains($dict['CancelledText']) . "]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $this->emailSubject = $parser->getSubject();

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

    private function normalizeDate($date)
    {
        $this->logger->debug($date);
        $in = [
            // Sunday, June 12, 2022
            '/^\s*\w+,\s*(\w+)\s+(\d{1,2})\s*,\s*(\d{4})\s*$/u',
            // Quinta-feira, 5 de Janeiro de 2023
            // mardi 14 février 2023
            '/^\s*[[:alpha:] \.\-]+[,\s]\s*(\d{1,2})\s+(?:de\s+)?(\w+)\s+(?:de\s+)?(\d{4})\s*$/u',
            // 2022年9月8日 星期四; 2023년 2월 7일 화요일
            '/^\s*(\d{4})\s*(?:年|년)\s*(\d{1,2})\s*(?:月|월)\s*(\d{1,2})\s*(?:日|일)\s*[[:alpha:]]*\s*$/u',
            // sábado 10 de septiembre de 2022 (después de las 15:00)
            // Sonntag, 12. Februar 2023 (vor 11:00 Uhr)
            '/^\D+\s*(\d+)[.]?(?:\s+de)?\s+([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*\(\D+([\d\:]+(?:\s*[ap]m)?)\D*\)$/iu',
            //2022年9月4日 （15:00以降）
            '/^(\d{4})\S\s*(\d+)\S\s*(\d+)\S\s*\D*([\d\:]+)\S{1,2}.*$/u',
            // Sunday, September 4, 2022 (after 15:00)
            '/^\w+\,\s+(\w+)\s*(\d+)\,\s*(\d{4})\D+([\d\:]+)\)$/',

            // 12 iunie 2023 (înainte de 11:00)
            // 14 февраля 2023 г. (после 11:00)
            '/^\w*\,?\s*(\d+)\s+([[:alpha:]]+)\s*[,\s]\s*(\d{4})\s*(?:г\.\s*)?\(\D+([\d\:]+)\)$/u',
            // 24 Şubat 2023 Cuma (12:00 öncesi)
            '/^\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\b\D*\s*\(\D*([\d\:]+(?:\s*[ap]m)?)\D+\)$/ui',
            // Tuesday, August 15, 2023 (after 8:00 PM)
            '/^\w*\,?\s*([[:alpha:]]+)\s*[,\s](\d+)\,?\s*(\d{4})\s*(?:г\.\s*)?\(\D+([\d\:]+\s*A?P?M?)\)$/',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$1-$2-$3',
            '$1 $2 $3, $4',
            '$3.$2.$1, $4',
            '$2 $1 $3, $4',
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+(.+?)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
