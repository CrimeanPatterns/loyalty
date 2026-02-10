<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class CancelledBooking extends \TAccountChecker
{
    public $mailFiles = "agoda/it-187022965.eml, agoda/it-61576472.eml, agoda/it-66816191.eml, agoda/it-66851282.eml";

    private $subjects = [
        'en' => ['Cancelled Booking', 'Canceled Booking', 'Amendment Confirmation for Booking ID #'],
        'zh' => ['預訂已取消 Agoda Booking ID'],
        'pt' => ['Reserva cancelada'],
        'vi' => ['Mã Đặt Phòng Được Hủy Agoda Booking ID', 'Đã hủy Mã đặt phòng Agoda'],
        'de' => ['Stornierte Buchung - Agoda Booking ID'],
        'id' => ['ID Pemesanan yang Dibatalkan Agoda Booking ID'],
        'ko' => ['Agoda Booking ID'],
        'fr' => ['Annulation de la réservation'],
        'sv' => ['har annullerats'],
        'da' => ['Annulleret Reservations-ID'],
        'ja' => ['【ホテル予約アゴダ】キャンセルのお知らせ［予約ID'],
        'he' => ['Agoda Booking ID'],
        'it' => ['Prenotazione Annullata - Agoda Booking ID'],
        'ru' => ['Отмененное бронирование - Agoda Booking ID'],
        'nl' => ['Geannuleerde Agoda Booking ID'],
        'ar' => ['الحجز الملغى رقم'],
        'uk' => ['Скавовано бронювання ID (Agoda Booking ID'],
    ];

    private $detectors = [
        'es'  => ['Tu reserva cancelada', 'Información de la cancelación'],
        'en'  => ['Your cancelled booking', 'Your canceled booking', 'Your new booking information'],
        'zh'  => ['我們已經確認取消您在', '已取消的訂單資料', '已取消的预订', '已取消的預訂'],
        'pl'  => 'Anulowana rezerwacja',
        'pt'  => ['Cancelou a sua reserva', 'A sua reserva cancelada'],
        'vi'  => 'Đặt phòng đã hủy của bạn',
        'vi2' => 'Đơn đặt phòng bị hủy bỏ của quý khách',
        'de'  => 'Ihre stornierte Buchung',
        'id'  => 'Pemesanan yang dibatalkan',
        'ko'  => '취소된 예약',
        'fr'  => 'Vous avez annulé votre réservation',
        'sv'  => 'Din annullerade bokning',
        'da'  => 'Din afbestilte reservation',
        'ja'  => 'キャンセル済みのご予約',
        'he'  => 'ההזמנה המבוטלת שלכם',
        'it'  => 'Prenotazione cancellata',
        'ru'  => 'Отмененное бронирование',
        'no'  => 'Din kansellerte booking',
        'nl'  => 'Uw geannuleerde boeking',
        'ar'  => 'حجزك المُلغى',
        'uk'  => 'Ваше скасоване бронювання',
    ];

    private $lang = '';

    private static $dictionary = [
        'en' => [
            'confNumber'       => ['Booking ID', 'Booking ID:'],
            'checkIn'          => ['CHECK IN'],
            'checkOut'         => ['CHECK OUT'],
            'statusVariants'   => ['cancelled', 'canceled'],
            'cancelledPhrases' => [
                'We have confirmed the cancellation of your booking',
                'We have confirmed the cancelation of your booking',
                'Your cancelled booking',
                'Your canceled booking',
            ],
        ],

        'zh' => [
            'confNumber'       => ['訂單編號：', '预订编码：', '預訂編號：'],
            'checkIn'          => ['入住日期'],
            'checkOut'         => ['退房日期'],
            'Property name'    => ['住宿名稱：', '酒店名称：', '酒店名稱：'],
            'Address'          => ['住宿地址：', '酒店地址：'],
            'Lead guest'       => ['主要住客：', '客人代表：'],
            'Number of guests' => ['住客人數：', '入住人数：'],
            'Adult'            => ['位大人', '名大人', '位成人'],
            'Children'         => ['位兒童', '名儿童', '位小童'],
            'Room type'        => '房型：',
            'Room'             => ['間房', '间客房'],
            'Reservation'      => ['訂房摘要：', '预订情况：', '預訂詳情：'],
            'statusVariants'   => ['認取消', '已取消'],
            'cancelledPhrases' => [
                '我們已經確認取消您在',
                '已取消的訂單資料',
                '已取消的预订',
                '我们已确认取消您的预订',
                '已取消的預訂',
            ],
        ],
        'pl' => [
            'confNumber'       => ['Numer rezerwacji:'],
            'checkIn'          => ['ZAMELDOWANIE'],
            'checkOut'         => ['WYMELDOWANIE'],
            'Property name'    => 'Nazwa obiektu:',
            'Address'          => 'Adres obiektu:',
            'Lead guest'       => 'Główny gość:',
            'Number of guests' => 'Liczba gości:',
            'Adult'            => 'Osób dorosłych:',
            'Children'         => 'Liczba dzieci:',
            'Room type'        => 'Rodzaj pokoju:',
            'Room'             => 'Liczba pokojów:',
            'Reservation'      => 'Rezerwacja:',
            'statusVariants'   => ['Anulowana'],
            'cancelledPhrases' => [
                'Anulowana rezerwacja',
            ],
        ],
        'es' => [
            'confNumber'       => ['ID Reserva:'],
            'checkIn'          => ['ENTRADA'],
            'checkOut'         => ['SALIDA'],
            'Property name'    => 'Nombre del establecimiento:',
            'Address'          => 'Dirección del establecimiento:',
            'Lead guest'       => ['Huésped principal:', 'Huésped principal'],
            'Number of guests' => ['Número de huéspedes:', 'Capacidad'],
            'Adult'            => ['Adultos', 'adulto'],
            'Children'         => 'Niños',
            'Room type'        => 'Tipo de Habitación:',
            'Room'             => 'Habitación',
            'Reservation'      => ['Reserva:'],
            'statusVariants'   => ['cancelada', 'cancelación'],
            'cancelledPhrases' => [
                'Tu reserva cancelada',
                'Hemos confirmado la cancelación de tu reserva en',
            ],
        ],
        'pt' => [
            'confNumber'       => ['ID de reserva:'],
            'checkIn'          => ['ENTRADA'],
            'checkOut'         => ['SAÍDA'],
            'Property name'    => 'Nome da propriedade:',
            'Address'          => 'Endereço da propriedade:',
            'Lead guest'       => ['Hóspede Principal:', 'Hóspede principal:'],
            'Number of guests' => 'Número de hóspedes:',
            'Adult'            => 'Adultos',
            'Children'         => 'Crianças',
            'Room type'        => ['Tipo de Quarto:', 'Tipo de quarto:'],
            'Room'             => 'Quarto',
            'Reservation'      => 'Reserva:',
            'statusVariants'   => ['cancelar', 'cancelada'],
            'cancelledPhrases' => [
                'para cancelar a sua reserva',
                'Cancelou a sua reserva',
                'A sua reserva cancelada',
            ],
        ],
        'vi' => [
            'confNumber'       => ['Mã số đặt phòng:', 'Mã đặt phòng:'],
            'checkIn'          => ['NHẬN PHÒNG'],
            'checkOut'         => ['TRẢ PHÒNG'],
            'Property name'    => ['Tên khách sạn:', 'Tên chỗ nghỉ:'],
            'Address'          => ['Địa chỉ khách sạn:', 'Địa chỉ:'],
            'Lead guest'       => 'Khách chính:',
            'Number of guests' => ['Số lượng khách:', 'Lượng khách:'],
            'Adult'            => 'người lớn',
            'Children'         => 'trẻ em',
            'Room type'        => 'Loại phòng:',
            'Room'             => 'phòng',
            'Reservation'      => ['Thông tin phòng:', 'Đặt trước:'],
            'statusVariants'   => ['đã hủy'],
            'cancelledPhrases' => [
                'Chúng tôi xác nhận đã hủy đặt phòng của bạn',
                'Đặt phòng đã hủy của bạn',
            ],
        ],
        'de' => [
            'confNumber'       => ['Buchungsnummer:'],
            'checkIn'          => ['CHECK-IN'],
            'checkOut'         => ['CHECK-OUT'],
            'Property name'    => 'Name der Unterkunft:',
            'Address'          => 'Adresse der Unterkunft:',
            'Lead guest'       => 'Hauptgast:',
            'Number of guests' => 'Anzahl der Gäste:',
            'Adult'            => 'Erwachsener',
            'Children'         => 'Kinder',
            'Room type'        => 'Zimmertyp:',
            'Room'             => 'Zimmer',
            'Reservation'      => 'Reservierung:',
            'statusVariants'   => ['stornierte'],
            'cancelledPhrases' => [
                'Wir haben die Stornierung Ihrer Buchung',
                'Ihre stornierte Buchung',
            ],
        ],
        'id' => [
            'confNumber'       => ['ID Pemesanan:'],
            'checkIn'          => ['CHECK-IN'],
            'checkOut'         => ['CHECK-OUT'],
            'Property name'    => 'Nama properti:',
            'Address'          => 'Alamat properti:',
            'Lead guest'       => 'Tamu utama:',
            'Number of guests' => 'Jumlah tamu:',
            'Adult'            => 'Dewasa',
            'Children'         => 'Anak',
            'Room type'        => 'Tipe kamar:',
            'Room'             => 'Kamar',
            'Reservation'      => 'Pesanan:',
            'statusVariants'   => ['dibatalkan'],
            'cancelledPhrases' => [
                'Kami telah mengonfirmasi pembatalan pesanan Anda di',
                'Pemesanan yang dibatalkan',
            ],
        ],
        'ko' => [
            'confNumber'       => ['예약 번호:'],
            'checkIn'          => ['체크인'],
            'checkOut'         => ['체크아웃'],
            'Property name'    => '숙소명:',
            'Address'          => '숙소 주소:',
            'Lead guest'       => '투숙객 명:',
            'Number of guests' => '투숙객 수:',
            'Adult'            => '성인',
            'Children'         => ' 아동',
            'Room type'        => '객실 종류:',
            'Room'             => '객실',
            'Reservation'      => '숙박 수 및 객실 수:',
            'statusVariants'   => ['취소된'],
            'cancelledPhrases' => [
                '예약 취소가 확인되었습니다',
                '취소된 예약',
            ],
        ],
        'fr' => [
            'confNumber'       => ['Numéro de réservation :'],
            'checkIn'          => ['ARRIVÉE'],
            'checkOut'         => ['DÉPART'],
            'Property name'    => 'Nom de l\'établissement :',
            'Address'          => 'Adresse de l\'établissement :',
            'Lead guest'       => 'Hôte principal :',
            'Number of guests' => 'Nombre d\'hôtes :',
            'Adult'            => 'adultes',
            'Children'         => 'enfants',
            'Room type'        => 'Catégorie de chambre :',
            'Room'             => 'chambre',
            'Reservation'      => 'Réservation :',
            'statusVariants'   => ['annulé'],
            'cancelledPhrases' => [
                'clientèle d\'Agoda pour annuler votre réservation.',
                'Vous avez annulé votre réservation',
            ],
        ],
        'sv' => [
            'confNumber'       => ['Boknings-ID:'],
            'checkIn'          => ['ANKOMST'],
            'checkOut'         => ['AVRESA'],
            'Property name'    => 'Boendets namn:',
            'Address'          => 'Boendets adress:',
            'Lead guest'       => 'Huvudgäst:',
            'Number of guests' => 'Antal gäster:',
            'Adult'            => 'Vuxen',
            'Children'         => 'Barn',
            'Room type'        => 'Rumstyp:',
            'Room'             => 'Rum',
            'Reservation'      => 'Bokning:',
            'statusVariants'   => ['annullerade'],
            'cancelledPhrases' => [
                'Vi har bekräftat din avbokning',
                'Din annullerade bokning',
            ],
        ],
        'da' => [
            'confNumber'       => ['Reservations-ID:'],
            'checkIn'          => ['ANKOMST'],
            'checkOut'         => ['AFREJSE'],
            'Property name'    => ['Ejendommens navn:', 'Stedets navn:'],
            'Address'          => ['Ejendommens adresse:', 'Stedets adresse:'],
            'Lead guest'       => 'Hovedgæst:',
            'Number of guests' => 'Antal gæster:',
            'Adult'            => ['Voksen', 'Voksne'],
            'Children'         => 'Børn',
            'Room type'        => 'Værelsestype:',
            'Room'             => 'Værelse',
            'Reservation'      => 'Reservation:',
            'statusVariants'   => ['afbestilte'],
            'cancelledPhrases' => [
                'Agodas Kundeservice til at afbestille din reservation.',
                'Din afbestilte reservation',
            ],
        ],
        'ja' => [
            'confNumber'       => ['ご予約ID：'],
            'checkIn'          => ['チェックイン日'],
            'checkOut'         => ['チェックアウト日'],
            'Property name'    => '宿泊施設名：',
            'Address'          => '宿泊施設住所：',
            'Lead guest'       => ['代表者氏名：'],
            'Number of guests' => '宿泊者人数：',
            'Adult'            => '大人',
            'Children'         => ' 子ども',
            'Room type'        => 'ルームタイプ：',
            'Room'             => '部屋',
            'Reservation'      => 'ご予約内容：',
            //            'statusVariants' => [],
            'cancelledPhrases' => [
                'お客様のご予約キャンセルについてご連絡いたします。',
                'キャンセル済みのご予約',
            ],
        ],
        'it' => [
            'confNumber'       => ['Numero della prenotazione:'],
            'checkIn'          => ['CHECK-IN'],
            'checkOut'         => ['CHECK-OUT'],
            'Property name'    => 'Nome della struttura:',
            'Address'          => 'Indirizzo della struttura:',
            'Lead guest'       => ['Ospite principale:'],
            'Number of guests' => 'Numero di ospiti:',
            'Adult'            => 'adult',
            'Children'         => 'bambino',
            'Room type'        => 'Tipo di camera:',
            'Room'             => 'camera',
            'Reservation'      => 'Prenotazione:',
            'statusVariants'   => ['cancellata'],
            'cancelledPhrases' => [
                'Prenotazione cancellata',
                'Abbiamo confermato la cancellazione della sua prenotazione presso',
            ],
        ],
        'he' => [
            'confNumber'       => ['מספר הזמנה:'],
            'checkIn'          => ['צ\'ק-אאוט'],
            'checkOut'         => ['צ\'ק-אין'],
            'Property name'    => 'שם מקום האירוח:',
            'Address'          => 'כתובת מקום האירוח:',
            'Lead guest'       => 'הזמנה על שם:',
            'Number of guests' => 'מספר האורחים:',
            'Adult'            => 'מבוגרים',
            'Children'         => 'ילדים',
            'Room type'        => 'סוג החדר:',
            'Room'             => 'חדר',
            'Reservation'      => 'הזמנה:',
            //            'statusVariants'   => ['cancellata'],
            //            'cancelledPhrases' => [
            //                'Prenotazione cancellata',
            //                'Abbiamo confermato la cancellazione della sua prenotazione presso',
            //            ],
        ],
        'ru' => [
            'confNumber'       => ['Номер бронирования:'],
            'checkIn'          => ['ЗАЕЗД'],
            'checkOut'         => ['ВЫЕЗД'],
            'Property name'    => 'Объект размещения:',
            'Address'          => 'Адрес:',
            'Lead guest'       => 'Основной гость:',
            'Number of guests' => 'Число гостей:',
            'Adult'            => 'взрослых',
            'Children'         => 'детей',
            'Room type'        => 'Тип номера:',
            'Room'             => 'номер',
            'Reservation'      => 'Бронирование:',
            'statusVariants'   => ['Отмененное'],
            'cancelledPhrases' => [
                'Отмененное бронирование',
            ],
        ],
        'nl' => [
            'confNumber'       => ['Boekings-ID:'],
            'checkIn'          => ['INCHECKEN'],
            'checkOut'         => ['UITCHECKEN'],
            'Property name'    => 'Accommodatienaam:',
            'Address'          => 'Accommodatieadres:',
            'Lead guest'       => 'Hoofdgast:',
            'Number of guests' => 'Aantal gasten:',
            'Adult'            => 'volwassenen',
            'Children'         => 'kinderen',
            'Room type'        => 'Kamertype:',
            'Room'             => 'kamer',
            'Reservation'      => 'Reservering:',
            'statusVariants'   => ['geannuleerde'],
            'cancelledPhrases' => [
                'Uw geannuleerde boeking',
            ],
        ],
        'ar' => [
            'confNumber'       => ['رقم الحجز:'],
            'checkIn'          => ['تسجيل الوصول'],
            'checkOut'         => ['تسجيل المغادرة'],
            'Property name'    => 'اسم مكان الإقامة:',
            'Address'          => 'عنوان مكان الإقامة:',
            'Lead guest'       => 'النزيل الرئيسي:',
            'Number of guests' => 'عدد الأشخاص:',
            'Adult'            => 'بالغين',
            'Children'         => 'أطفال',
            'Room type'        => 'نوع الغرفة:',
            'Room'             => 'عدد الغرف:',
            'Reservation'      => 'الحجز:',
            'statusVariants'   => ['المُلغى'],
            'cancelledPhrases' => [
                'حجزك المُلغى',
            ],
        ],
        'no' => [
            'confNumber'       => ['Booking-ID:'],
            'checkIn'          => ['ANKOMST'],
            'checkOut'         => ['AVREISE'],
            'Property name'    => 'Eiendommens navn:',
            'Address'          => 'Eiendommens adresse:',
            'Lead guest'       => ['Hovedgjest:'],
            'Number of guests' => ['Antall gjester:'],
            'Adult'            => ['voksne'],
            'Children'         => 'barn',
            'Room type'        => 'Romtype:',
            'Room'             => 'rom',
            //'Reservation'      => ['Reserva:'],
            'statusVariants'   => ['kansellerte'],
            'cancelledPhrases' => [
                'Din kansellerte booking',
            ],
        ],
        'uk' => [
            'confNumber'       => ['ID бронювання:'],
            'checkIn'          => ['ЗАЇЗД'],
            'checkOut'         => ['ВИЇЗД'],
            'Property name'    => 'Назва готелю:',
            'Address'          => 'Адреса готелю:',
            'Lead guest'       => ['Головний гість:'],
            'Number of guests' => ['Кількість гостей:'],
            'Adult'            => ['Дорослий'],
            'Children'         => 'Дитина',
            'Room type'        => 'Тип номеру:',
            'Room'             => 'Номер',
            'Reservation'      => ['Бронювання:'],
            'statusVariants'   => ['скасування'],
            'cancelledPhrases' => [
                'Ми підтвердили скасування Вашого бронювання ',
            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@agoda.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers['subject'], 'Cancelled Agoda Booking') !== false
            || stripos($headers['subject'], 'Canceled Agoda Booking') !== false
        ) {
            return true;
        }

        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Agoda') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".agoda.com/") or contains(@href,"www.agoda.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thanks for booking with Agoda") or contains(normalize-space(),"This email was sent by Agoda Company") or contains(.,"www.agoda.com")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('CancelledBooking' . ucfirst($this->lang));

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

    private function parseHotel(Email $email): void
    {
        $h = $email->add()->hotel();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancelledPhrases'))}]")->length > 0) {
            $h->general()->cancelled();
        }

        $cancellation = $this->http->FindSingleNode("//p[{$this->starts($this->t('Cancellation policy for'))}]/following-sibling::p[normalize-space()]");
        $h->general()->cancellation($cancellation, false, true);

        $statuses = array_unique(array_filter($this->http->FindNodes("//h2[{$this->eq($this->t('cancelledPhrases'))}]", null, "/\b{$this->opt($this->t('statusVariants'))}\b/iu")));

        if (count($statuses) === 1) {
            $h->general()->status(array_values($statuses)[0]);
        }

        $xpathDates = "//tr[ *[{$this->eq($this->t('checkIn'))}] and *[{$this->eq($this->t('checkOut'))}] ]/following-sibling::tr[normalize-space()][1]/descendant::*[ tr[normalize-space()][2] ][1]";

        $checkIn = implode(' ', $this->http->FindNodes($xpathDates . "/tr[normalize-space()]/td[1]"));

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkIn'))}]/following::text()[normalize-space()][1]");
        }

        $checkOut = implode(' ', $this->http->FindNodes($xpathDates . "/tr[normalize-space()]/td[3]"));

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("//text()[{$this->eq($this->t('checkOut'))}]/following::text()[normalize-space()][1]");
        }

        $checkInTime = str_replace('.', ':', $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'ArrivalTime:')]", null, true, "/{$this->opt($this->t('ArrivalTime:'))}([\d\.]+)/"));

        if (!empty($checkInTime)) {
            $h->booked()
                ->checkIn2($this->normalizeDate($checkIn) . ', ' . $checkInTime)
                ->checkOut2($this->normalizeDate($checkOut));
        } else {
            $h->booked()
                ->checkIn2($this->normalizeDate($checkIn))
                ->checkOut2($this->normalizeDate($checkOut));
        }

        $confirmation = $this->http->FindSingleNode("//td[{$this->starts($this->t('confNumber'))}]/following-sibling::td[normalize-space()]", null, true, '/^[A-Z\d]{5,}$/');

        if (empty($confirmation)) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Your new booking information'))}]/following::text()[{$this->eq($this->t('confNumber'))}]/ancestor::tr[1]/descendant::td[2]");
        }

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//td[ {$this->starts($this->t('confNumber'))} and following-sibling::td[normalize-space()] ]", null, true, '/^(.+?)[\s:]*$/');
            $h->general()->confirmation($confirmation, $confirmationTitle);
        }

        $hotelName = $this->http->FindSingleNode("//td[{$this->starts($this->t('Property name'))}]/following-sibling::td[normalize-space()]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/preceding::img[1]/following::text()[normalize-space()][1]");
        }

        $address = $this->http->FindSingleNode("//td[{$this->starts($this->t('Address'))}]/following-sibling::td[normalize-space()]");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/preceding::img[1]/following::text()[normalize-space()][2]");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $leadGuest = $this->http->FindSingleNode("//td[{$this->starts($this->t('Lead guest'))}]/following-sibling::td[normalize-space()]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $h->general()->traveller($leadGuest);

        $roomsCount = $this->http->FindSingleNode("//td[{$this->starts($this->t('Reservation'))}]/following-sibling::td[normalize-space()]", null, true, "/\b(\d{1,3})?\s*{$this->opt($this->t('Room'))}/ui");

        if (empty($roomsCount) || !preg_match("/^\d+$/", $roomsCount)) {
            $roomsCount = $this->http->FindSingleNode("//td[{$this->starts($this->t('Reservation'))}]/following-sibling::td[normalize-space()]", null, true, "/{$this->opt($this->t('Room'))}\s*(\d{1,3}|אחד)?\s*/iu");
            $roomsCount = str_replace('אחד', 1, $roomsCount);
        }

        if (!empty($roomsCount)) {
            $h->booked()->rooms($roomsCount);
        }

        $numberOfGuests = $this->http->FindSingleNode("//td[{$this->starts($this->t('Number of guests'))}]/following-sibling::td[normalize-space()]");

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Adult'))}/ui", $numberOfGuests, $m)
            || preg_match("/{$this->opt($this->t('Adult'))}\s*\b(\d{1,3})/ui", $numberOfGuests, $m)
        ) {
            $h->booked()->guests($m[1]);
        }

        if (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('Children'))}/", $numberOfGuests, $m)
            || preg_match("/{$this->opt($this->t('Children'))}\s*\b(\d{1,3})/", $numberOfGuests, $m)
        ) {
            $h->booked()->kids($m[1]);
        }

        $roomType = $this->http->FindSingleNode("//td[{$this->starts($this->t('Room type'))}]/following-sibling::td[string-length(normalize-space())>1]");

        if ($roomType) {
            $room = $h->addRoom();
            $room->setType($roomType);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='Total price of amended booking:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^[A-Z]{3}\s*([\d\.]+)$/u");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='Total price of amended booking:']/ancestor::tr[1]/descendant::td[2]", null, true, "/^([A-Z]{3})\s*[\d\.]+$/u");

        if (!empty($total) && !empty($currency)) {
            $h->price()
                ->total($total)
                ->currency($currency);
        }
    }

    private function normalizeDate(?string $text): string
    {
        //$this->logger->debug($text);

        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // Jul, 2020 20 Monday
            '/^([[:alpha:]]+)\.?\s*,\s*(\d{2,4})\s+(\d{1,2})\D*$/u',
            // 十月, 2020 02 星期五
            '/^([[:alpha:]]+)\,\s+(\d{4})\s+(\d+)\s+\w+$/u',
            // 12月, 2020 23 星期三; 1, 2021 21 목요일
            '/^\s*(\d+)\w?\,\s+(\d{4})\s+(\d+)\s+\w+$/u',
            // Thg12, 2020 21 Thứ Hai
            '/^\s*Thg(\d+),\s+(\d{4})\s+(\d{1,2})\s+\D+$/u',
            // sábado 10 de septiembre de 2022 (después de las 15:00)
            '/^\w+\s*(\d+)(?:\s*de)?\s*(\w+)\D+(\d{4})\s*\(\D+([\d\:]+)\)$/u',
        ];
        $out = [
            '$3 $1 $2',
            '$3 $1 $2',
            '$3.$1.$2',
            '$3.$1.$2',
            '$1 $2 $3, $4',
        ];
        $text = preg_replace($in, $out, $text);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $text, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $text = str_replace($m[1], $en, $text);
            }
        }

        return $text;
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
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
}
