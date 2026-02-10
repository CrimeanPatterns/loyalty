<?php

namespace AwardWallet\Engine\agoda\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "agoda/it-1.eml, agoda/it-2947986.eml, agoda/it-3047707.eml, agoda/it-3088645.eml, agoda/it-3121110.eml, agoda/it-3121116.eml, agoda/it-3121118.eml, agoda/it-3174405.eml, agoda/it-3332149.eml, agoda/it-3950275.eml, agoda/it-3985756.eml, agoda/it-3988892.eml, agoda/it-45627770.eml, agoda/it-48301859.eml, agoda/it-48381597.eml, agoda/it-9181422.eml, agoda/it-9203508.eml, agoda/it-9207448.eml, agoda/it-9388091.eml, agoda/it-9409377.eml, agoda/it-9427799.eml, agoda/it-9576952.eml, agoda/it-9633155.eml, agoda/it-9653543.eml, agoda/it-9654549.eml, agoda/it-9722522.eml, agoda/it-9722683.eml, agoda/it-9727721.eml, agoda/it-9727747.eml";

    private $subjects = [
        'zh' => ['確認訂單編號', '预订确认'],
        'ja' => ['確認メール、予約'],
        'it' => ['Conferma della Prenotazione'],
        'ar' => ['تأكيد  لحجز رقم'],
        'sv' => ['Bekräftelse för Boknings-ID'],
    ];

    private $detects = [
        'en'  => 'Please present either an electronic or paper copy of your',
        'en2' => 'Hotel Contact Number',
        'en3' => 'is confirmed and complete with Agoda price guarantee',
        'en4' => '‫‪Booking Reference No :‬‬',
        'en5' => 'Ваше бронювання підтверджене та завершене!', // TODO: Writing in ua and en languages
        'en6' => 'Agoda price guarantee',
        'en7' => 'Booked And Payable By',
        'zh'  => '預訂編號：',
        'zh2' => 'Agoda價格保證',
        'zh3' => '的预订已成功确认，并享有Agoda价格保证。',
        'de'  => ['wurde bestätigt und mit der Agoda Preisgarantie abgeschlossen', 'Bitte legen Sie beim Check-in eine elektronische oder ausgedruckte Kopie dieses Buchungsbelegs vor'],
        "da"  => 'er bekræftet og afsluttet med Agodas Prisgaranti',
        "pl"  => 'gwarancją ceny Agoda, została potwierdzona i zakończona',
        "pt"  => 'confirmada e completa com garantia de preço Agoda',
        "id"  => 'dibuat dengan jaminan harga Agoda',
        "es"  => 'confirmada con la garantía de precio Agoda',
        "no"  => 'bekreftet og dekkes av Agodas prisgaranti',
        "ru"  => 'завершено с гарантией лучшей цены Agoda',
        'ru2' => 'Пожалуйста, предъявите электронную или распечатанную копию данного подтверждения при регистрации заезда.',
        "ko"  => '최저가 보장이 적용됩니다',
        "fi"  => 'Esitä joko elektroninen tai paperinen varausvoucher',
        //'fr' => 'Utilisez le Self Service Agoda pour gérer votre réservation',
        'nl'  => 'U kunt uw boeking gemakkelijk beheren met onze zelfservice',
        'ja'  => '空港から宿泊施設までの交通手段を手配できます',
        'ja2' => 'アゴダ®ベスト料金保証',
        'ja3' => 'アゴダのセルフサービスページより予約を管理できます',
        'fr'  => 'votre réservation est confirmée (Garantie de prix Agoda',
        'it'  => 'completa e confermata e con la garanzia sul prezzo di Agoda',
        'ar'  => 'مؤكد و مكتمل مع ضمان أفضل سعر من أجودا',
        'sv'  => 'är bekräftad och klar, med Agoda prisgaranti',
        'th'  => 'หากมีขอ้ สงสัยหรือต้องการสอบถามเพิมเติม กรุณาไปที www.agoda.com/support',
        'sv2' => 'Skaffa en billig hyrbil och spara pengar när du bokar bilen online idag',
    ];

    private static $dict = [
        'en' => [
            'ConfirmationNumber'  => 'Booking ID:',
            'HotelName'           => "#Your booking at (.*?) is confirmed#i",
            'CheckInDate'         => 'Check in:', 'CheckOutDate' => 'Check out:',
            'GuestNames'          => 'Lead Guest:', 'Guests' => 'Occupancy:', 'Adults' => ['Adults', 'Adult'],
            'Rooms'               => 'Reservations:', 'CancellationPolicy' => "contains(text(), 'Cancellation') and contains(text(), 'Policy')",
            'Cancellation Policy' => ['Cancellation and Change Policy', 'Cancellation Policy', 'Cancellation policy'],
            'Total'               => ['Total price', 'Total Price:', 'Total Charge to Card:', 'Total charge to card:', 'Total Charge to Credit Card', 'Total Due / Charge to ', 'Total amount pre-authorized'],
            'RoomTypeDescription' => 'Special requests:',
            'Status'              => 'is confirmed',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'zh' => [
            'ConfirmationNumber'  => ['预订编码：', '订单号', '編號：', '預訂編號：'],
            'HotelName'           => "#(?:您在|已獲得)(.*?)的预订已成功确认#ui",
            'CheckInDate'         => ['入住日期：'], 'CheckOutDate' => '退房日期：',
            'GuestNames'          => ['顾客姓名:', '顧客姓名:', '住客姓名：'], 'Guests' => ['入住人数：', '入住人數：'], 'Adults' => '位成人',
            'Rooms'               => ['预订信息：', '預訂細節：', '訂房摘要：'], 'CancellationPolicy' => "contains(text(), '取消') and contains(text(), '修改政策')",
            'Cancellation Policy' => '取消預訂條款', 'Hotel policy' => ['飯店政策', '酒店政策'],
            'Total'               => ['总价：', '刷卡總金額：', '從信用卡扣除總金額：', '總金額：', '總價格：'], 'RoomTypeDescription' => '特殊要求：',
            'RoomTypeDescription' => '特殊需求：',
            'Status'              => ['您的预订已成功确认', '您的預訂已經完成並確認', '預訂成功，訂單已經獲得確認'],
            'Client'              => '住客姓名',
            'Room Type'           => '房型',
            'Hotel'               => '住宿名稱',
            'Address'             => '地址',
            //'after' => '後', 'before' => '前',
        ],
        'de' => [
            'ConfirmationNumber'  => 'Buchungs-ID:',
            'HotelName'           => "#Ihre Buchung im (.*?) wurde bestätigt#i",
            'CheckInDate'         => ['Check-in:', 'Check-In:'], 'CheckOutDate' => ['Check-out:', 'Check-Out:'],
            'GuestNames'          => 'Hauptgast:', 'Guests' => 'Belegung:',
            'Rooms'               => 'Reservierung:', 'CancellationPolicy' => "contains(text(), 'Stornierungs') and contains(text(), 'Änderungsbedingungen')",
            'Cancellation Policy' => 'Stornierungsbedingungen',
            'Total'               => ['Gesamter Abbuchungsbetrag', 'Gesamtpreis'],
            'RoomTypeDescription' => 'Sonderwünsche:',
            'Status'              => 'Ihre Buchung wurde bestätigt',
            'after'               => 'nach', 'before' => 'vor',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'nl' => [
            'ConfirmationNumber'  => 'Boekings-ID:',
            'HotelName'           => "#Uw boeking bij (.*?) is bevestigd#i",
            'CheckInDate'         => 'Inchecken:', 'CheckOutDate' => 'Uitchecken:',
            'GuestNames'          => 'Aanhef Gast:', 'Guests' => 'Bezetting:',
            'Rooms'               => 'Reservering:', 'CancellationPolicy' => "contains(text(), 'Annulerings') and contains(text(), 'Aanpassingsbeleid')",
            'Cancellation Policy' => 'Annuleringsbeleid', 'Hotel policy' => 'Hotelbeleid',
            'Total'               => ['Totaalprijs:', 'Totaal belast op kaart:'],
            'RoomTypeDescription' => 'Speciale verzoeken:',
            'Status'              => 'Uw boeking is bevestigd',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'da' => [
            'ConfirmationNumber'  => 'Dit reservations-ID:',
            'HotelName'           => "#Din reservation på (.*?) er bekræftet#i",
            'CheckInDate'         => 'Indtjekning:', 'CheckOutDate' => 'Udtjekning:',
            'GuestNames'          => 'Hovedgæst:', 'Guests' => 'Belægning:',
            'Rooms'               => 'Reservationer:', 'CancellationPolicy' => "contains(text(), 'Afbestillings') and contains(text(), 'ændringspolitik')",
            'Total'               => ['Total Price:', 'Samlet opkrævning fra kort:'],
            'RoomTypeDescription' => 'Specielle forespørgsler:',
            'Status'              => 'er bekræftet',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'pl' => [
            'ConfirmationNumber'  => 'Numer rezerwacji:',
            'HotelName'           => "#Twoja rezerwacja w: (.*?), wraz z gwarancją#i",
            'CheckInDate'         => 'Zameldowanie:', 'CheckOutDate' => 'Wymeldowanie:',
            'GuestNames'          => 'Główny gość:', 'Guests' => 'Ilość osób:',
            'Rooms'               => 'Rezerwacje:', 'CancellationPolicy' => "contains(text(), 'anulowania') and contains(text(), 'Polityka')",
            'Cancellation Policy' => 'Regulamin anulowania', 'Hotel policy' => 'zgodnie z zasadami hotelu',
            'Total'               => ['Kartę obciążono łączną kwotą:'],
            'RoomTypeDescription' => 'Specjalne życzenia:',
            'Status'              => 'została potwierdzona',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'pt' => [
            'ConfirmationNumber'  => 'O seu ID de reserva:',
            'HotelName'           => "#A sua reserva em (.*?) está confirmada#i",
            'CheckInDate'         => 'Entrada:', 'CheckOutDate' => 'Saída:',
            'GuestNames'          => 'Hóspede Principal:', 'Guests' => 'Ocupação:',
            'Rooms'               => 'Reservas:', 'CancellationPolicy' => "contains(text(), 'Política') and contains(text(), 'Cancelamento')",
            'Cancellation Policy' => 'Política de cancelamentos', 'Hotel policy' => 'Política do hotel',
            'Total'               => ['Custo total para cartão'],
            'RoomTypeDescription' => 'Pedidos Especiais:',
            'Status'              => 'está confirmada',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'id' => [
            'ConfirmationNumber'  => 'ID Pesanan Anda:',
            'HotelName'           => "#Pesanan Anda di (.*?) telah dikonfirmasi#i",
            'CheckInDate'         => 'Check-in:', 'CheckOutDate' => 'Check-out:',
            'GuestNames'          => 'Tamu Utama:', 'Guests' => 'Okupansi:',
            'Rooms'               => 'Pesanan:', 'CancellationPolicy' => "contains(text(), 'Kebijakan') and contains(text(), 'Pembatalan')",
            'Cancellation Policy' => 'Kebijakan Pembatalan', 'Hotel policy' => 'Kebijakan hotel',
            'Total'               => ['Total yang dibebankan ke kartu'],
            'RoomTypeDescription' => 'Permintaan khusus:',
            'Status'              => 'telah dikonfirmasi',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'es' => [
            'ConfirmationNumber'  => ['Tu ID de reserva:', 'ID Reserva:'],
            'HotelName'           => "#Tu reserva en (.*?) ha sido completada #i",
            'CheckInDate'         => 'Entrada:', 'CheckOutDate' => 'Salida:',
            'GuestNames'          => 'Huésped Principal', 'Guests' => 'Capacidad:',
            'Rooms'               => 'Reservas:', 'CancellationPolicy' => "contains(text(), 'Política') and contains(text(), 'Cancelación')",
            'Cancellation Policy' => 'Política de Cancelación', 'Hotel policy' => 'Política del Hotel',
            'Total'               => ['A cobrar en la tarjeta', "Precio Final:", 'Cargo Total en Tarjeta:'],
            'RoomTypeDescription' => 'Solicitud especial:',
            'Status'              => 'está completa',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'no' => [
            'ConfirmationNumber'  => 'Din booking-ID:',
            'HotelName'           => "#Din booking på (.*?) er bekreftet#i",
            'CheckInDate'         => 'Innsjekking:', 'CheckOutDate' => 'Utsjekking:',
            'GuestNames'          => 'Hovedgjest', 'Guests' => 'Kapasitet:',
            'Rooms'               => 'Bestilling:', 'CancellationPolicy' => "contains(text(), 'Avbestilling') and contains(text(), 'endringsregler')",
            'Cancellation Policy' => "Regler for avbestilling", 'Hotel policy' => 'hotellregel',
            'Total'               => ['Endelig belastning av kortet'],
            'RoomTypeDescription' => 'Forespørsler',
            'Status'              => 'er bekreftet',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'ru' => [
            'ConfirmationNumber'  => 'ID номер вашего бронирования:',
            'HotelName'           => "#бронирование в (.*?) подтверждено и#i",
            'CheckInDate'         => 'Дата заезда:', 'CheckOutDate' => 'Дата выезда:',
            'GuestNames'          => 'Имя гостя', 'Guests' => 'Размещение:',
            'Rooms'               => 'Бронирование:', 'CancellationPolicy' => "contains(text(), 'Политика') and contains(text(), 'отмены')",
            'Cancellation Policy' => 'Правила отмены', 'Hotel policy' => 'Политика отеля',
            'Total'               => ['Общая сумма:', 'Полная сумма к списанию с карты:'],
            'RoomTypeDescription' => 'Специальные запросы:',
            'Status'              => 'бронирование подтверждено',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'ko' => [
            'ConfirmationNumber'  => '예약 번호:',
            'HotelName'           => "#(.*?)의 예약이 확정#i",
            'CheckInDate'         => ['의 예약이 확정:', '체크인 날짜:'], 'CheckOutDate' => '체크아웃 날짜:',
            'GuestNames'          => '투숙객 이름:', 'Guests' => '총 숙박 인원:',
            'Rooms'               => '숙박일수 및 객실수:', 'CancellationPolicy' => "contains(text(), '취소 및 변경 정책')",
            'Cancellation Policy' => '[취소 정책]', 'Hotel policy' => '호텔 정책',
            'Total'               => ['총 카드 결제액:'],
            'RoomTypeDescription' => '특별요청사항:',
            'Status'              => '예약이 확정',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'fi' => [
            'ConfirmationNumber'  => 'Varauksesi ID:',
            'HotelName'           => "#Varauksesi kohteessa (.*?) on vahvistettu#i",
            'CheckInDate'         => 'Check-in:', 'CheckOutDate' => 'Check-out:',
            'GuestNames'          => 'Varauksesta vastaava:', 'Guests' => 'Saatavuus:',
            'Rooms'               => 'Varaukset:', 'CancellationPolicy' => "contains(text(), 'Peruutus-') and contains(text(), 'ja vaihtokäytäntö')",
            'Total'               => ['Kokonaisveloitus kortilta:'],
            'RoomTypeDescription' => 'Erityispyynnöt:',
            'Status'              => 'бронирование подтверждено',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'ja' => [
            'ConfirmationNumber'  => 'ご予約',
            'HotelName'           => "#ご予約手続きが完了しました。(.*?) のご予約確定です#i",
            'CheckInDate'         => 'チェックイン日：', 'CheckOutDate' => 'チェックアウト日：',
            'after'               => '以降', 'before' => 'まで',
            'GuestNames'          => '代表者名：', 'Guests' => '定員：',
            'Rooms'               => '部屋', 'CancellationPolicy' => "contains(text(), 'キャンセルポリシー') and contains(text(), '変更ポリシー')",
            'Total'               => ['合計金額：', 'カード課金額：'],
            'RoomTypeDescription' => '特別なリクエスト：',
            'Status'              => 'ご予約が確定しました!',
            'Client'              => '宿泊者名',
            'Room Type'           => 'ルームタイプ',
            'Hotel'               => '宿泊施設',
            'Address'             => '住所',
        ],
        'fr' => [
            'ConfirmationNumber'  => 'Numéro de réservation :',
            'HotelName'           => "#^\s*(.+?) : votre réservation est confirmée#i",
            'CheckInDate'         => 'Arrivée :', 'CheckOutDate' => 'Départ :',
            'after'               => 'Après', 'before' => 'Avant',
            'GuestNames'          => 'Hôte principal:', 'Guests' => 'Occupation :', 'Adults' => ['adulte', 'adultes'],
            'Rooms'               => 'Séjour :', 'CancellationPolicy' => "contains(text(), 'Conditions') and contains(text(), \"d'annulation\") and not(ancestor::a)",
            'Cancellation Policy' => ["Conditions d'annulation et de modification", "Conditions d annulation"],
            'Total'               => ['Montant total débité de votre carte :'],
            'RoomTypeDescription' => 'Demandes spéciales :',
            'Status'              => 'réservation est confirmée',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'it' => [
            'ConfirmationNumber'  => 'Numero Prenotazione (booking ID):',
            'HotelName'           => "#La tua prenotazione presso (.*?) è completa#i",
            'CheckInDate'         => 'Check-in:', 'CheckOutDate' => 'Check-out:',
            'after'               => 'dopo le', 'before' => 'entro le',
            'GuestNames'          => 'Ospite principale:', 'Guests' => 'Ospiti:',
            'Rooms'               => 'Prenotazione:', 'CancellationPolicy' => "contains(text(), 'Cancellazione') and contains(text(), 'Termini')",
            'Cancellation Policy' => 'Politica di cancellazione', 'Hotel policy' => "Politica dell'Hotel",
            'Total'               => ['Prezzo totale:'],
            'RoomTypeDescription' => 'Richieste speciali:',
            'Status'              => 'è confermata e completa',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'ar' => [
            'ConfirmationNumber'  => 'رقم حجزك:',
            'HotelName'           => "#حجزك في (.*?) مؤكد و مكتمل مع ضمان أفضل سعر من أجودا.#i",
            'CheckInDate'         => 'تسجيل الدخول:',
            'CheckOutDate'        => 'تسجيل الخروج:',
            'after'               => 'بعد', 'before' => 'قبل',
            'GuestNames'          => 'النزيل الرئيسي:',
            'Guests'              => 'الإشغال:',
            'Rooms'               => 'الحجوزات:',
            'CancellationPolicy'  => "contains(text(), 'إلغاء والتغيير') and contains(text(), 'سياسة ال')",
            'Cancellation Policy' => ['سياسة الإلغاء والتغيير'],
            'Total'               => ['السعر الكلي:', 'السعر الكلي'],
            'RoomTypeDescription' => 'طلبات خاصة:',
            'Status'              => ['حجزك مؤكد و مك'],
            'titleIMG'            => 'Agoda',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'sv'=> [
            'ConfirmationNumber'  => 'Boknings-ID:',
            'HotelName'           => "#Din bokning på (.*?) är bekräftad och klar, med Agoda prisgaranti.#",
            'CheckInDate'         => 'Incheckning:',
            'CheckOutDate'        => 'Utcheckning:',
            'GuestNames'          => 'Huvudgäst:',
            'Guests'              => 'Gäster:',
            'Adults'              => ['vuxna'],
            'Rooms'               => 'Bokningar:',
            'CancellationPolicy'  => "contains(text(), 'Avboknings') and contains(text(), '- och ändringsvillkor')",
            'Cancellation Policy' => ['Avboknings- och ändringsvillkor'],
            'Total'               => ['Totalbelopp debiterat på kortet:'],
            'RoomTypeDescription' => 'Särskilda önskemål:',
            'Status'              => 'är bekräftad',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
        'th' => [
            'ConfirmationNumber'  => 'หมายเลขการจอง:',
            'HotelName'           => "#การจองห้องพักของท่านที่ (.*?) ได้รับการยืนยันเรียบร้อยแล้ว#i",
            'CheckInDate'         => 'เช็คอิน:',
            'CheckOutDate'        => 'เช็คเอาต์:',
            'GuestNames'          => 'ผู้เข้าพัก:',
            'Guests'              => 'ผู้เข้าพัก:',
            'Adults'              => ['ผู้ใหญ่'],
            'Rooms'               => 'การจองห้องพัก:',
            'CancellationPolicy'  => "contains(text(), 'นโยบายการยกเลิกและ') and contains(text(), 'การเปลี่ยนแปลงการจองห้องพัก')",
            'Cancellation Policy' => ['นโยบายการยกเล กการจอง'],
            'Total'               => ['จำนวนเงินที่เรียกเก็บจากบัตร:'],
            'Meal option:'        => 'อาหารเช้า:',
            'after'               => 'หลัง', 'before' => 'ก่อน',
            'Status'              => 'ได้รับการยืนยันเรียบร้อยแล้ว',
            //            'Client' => '',
            //            'Room Type' => '',
            //            'Hotel' => '',
            //            'Address' => '',
        ],
    ];

    private $lang = 'en';
    private $htmlOrPlain;
    private $patterns = [
        'time' => '\d{1,2}:\d{2}(?:\s*[AaPp][Mm])?', // 4:19PM
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Agoda ') !== false
            || preg_match("/[@.]agoda\.com/i", $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//span[@id='lbl_Confirmation']")->length > 0
            && $this->http->XPath->query("//span[@id='lbl_NotAmendVoucher' or @id='lbl_AmendVoucher']")->length > 0
        ) {
            return false;
        }
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $body = '';

        if (isset($pdfs[0]) && 0 < count($pdfs)) {
            $body = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdfs)));
            $body = str_replace(chr(194) . chr(160), ' ', $body);

            if (!empty($body) && mb_stripos($body, 'agoda') === false) {
                return false;
            }
        }
        $body .= $parser->getHTMLBody() ?? $parser->getPlainBody();

        foreach ($this->detects as $detect) {
            if (!is_array($detect)) {
                $detect = [$detect];
            }

            foreach ($detect as $line) {
                if (stripos($body, $line) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) + 2; // pdf + html(en,zh)
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->htmlOrPlain = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach (self::$dict as $lang => $dict) {
            if (is_string($dict['ConfirmationNumber']) && stripos($parser->getHTMLBody(),
                    $dict['ConfirmationNumber']) !== false
            ) {
                $this->lang = $lang;

                break;
            } elseif (is_array($dict['ConfirmationNumber'])) {
                foreach ($dict['ConfirmationNumber'] as $confNo) {
                    if (stripos($parser->getHTMLBody(), $confNo) !== false) {
                        $this->lang = $lang;

                        break 2;
                    }
                }
            }
        }

        $pdf = $this->extractPDF($parser);
        $pdf = str_replace('­', '-', $pdf); // hidden symbols

        if (!empty($pdf) && $this->parsePdf($email, $pdf)) { // PDF
            $type = 'Pdf';
        } else { // HTML
            $type = 'Html';
            $this->parseHtml($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    private function parsePdf(Email $email, $pdf): bool
    {
        if (!preg_match("/^[ ]*Booking ID[: ]*:[:\s]*(\d{7,})(?:[ ]{2}|$)/m", $pdf, $m)) {
            $this->logger->alert('maybe other format...');

            return false;
        }
        $h = $email->add()->hotel();
        $h->general()->confirmation($m[1]);

        $email->removeItinerary($h);

        $columns1 = $this->re("/^([ ]*Booking ID[: ]*:[: ]*.+?)^[^\n]*(?i)(?:\bCancel|取消|취소하실|لاغٍ|\bRefunderbar\b|\bAvbokningar\b|\bRestitutie\b|\bBezzwrotna\b|\bKansellering\b|\bPembatalan\b|\bОтмены\b|\bВозвращается\b)/msu", $pdf);
        $table1Pos = [0];

        if (preg_match("/(.+[ ]{2})Number of Rooms[: ]*:/i", $columns1, $matches)) {
            $table1Pos[] = mb_strlen($matches[1]) - 1;
        }
        $table1 = $this->splitCols($columns1, $table1Pos);

        if (count($table1) !== 2) {
            $this->logger->debug('Wrong table1!');

            return false;
        }
        $table1[0] = $this->removeDoubleFields($table1[0]);
        $table1[1] = $this->removeDoubleFields($table1[1]);

        if (preg_match("/^[ ]*Number of Rooms[: ]*:[:\s]*(\d{1,3})[ ]*$/m", $table1[1], $m)) {
            $h->booked()->rooms($m[1]);
        }

        if (preg_match("/^[ ]*Member ID[: ]*:[: ]*(\d{7,})[ ]*$/m", $table1[0], $m)) {
            $h->program()->account($m[1], false);
        }

        // travellers
        if (preg_match("/^[ ]*Client[: ]*:[: ]*([[:alpha:]][-,.\'[:alpha:]\s]*[[:alpha:]])[ ]*$\s+^[ ]*Member ID/mu", $table1[0], $m)) {
            $h->general()->travellers(preg_split('/\s*,\s*/', preg_replace('/\s+/', ' ', $m[1])));
        }

        $guests1 = $this->re("/^[ ]*(?:Number of Adults|Max Occupancy)[: ]*:[:\s]*(\d{1,3})[ ]*$/m", $table1[1]);

        if (($guests2 = $this->re("/(\d+) /", $this->http->FindSingleNode("//*[{$this->getRule('Guests')}]/ancestor-or-self::td[1]/following-sibling::td[1]")))
            && (empty($guests1) || (int) $guests1 < (int) $guests2)
        ) {
            $h->booked()->guests($guests2);
        } else {
            $h->booked()->guests($guests1);
        }

        $h->booked()->kids($this->re("/^[ ]*Number of Children[: ]*:[:\s]*(\d{1,3})[ ]*$/m", $table1[1]), false, true);

        $room = $h->addRoom();

        $roomType = $this->re("/^[ ]*Room Type[: ]*:[:\s]*([^:]+)[ ]*$\s+^[ ]*Promotion[: ]*:/m", $table1[1]);
        $room->setType(preg_replace('/\s+/', ' ', $roomType));

        // hotelName
        $hotel = $this->re("/^[ ]*(?:Hotel|Property)[: ]*:[:\s]*([^:\n]{3,}?)[ ]*$/m", $table1[0]);
        $mas = explode("\n", $hotel);

        if (count($mas) == 2 && mb_stripos($mas[1], $mas[0]) === false) {
            $h->hotel()->name(preg_replace('/\s+/', ' ', $mas[0]));
        } else {
            $h->hotel()->name(preg_replace('/\s+/', ' ', $hotel));
        }

        // address
        $address = $this->re("/^[ ]*Address[: ]*:[:\s]*([^:]{3,}?)[ ]*$\s+^[ ]*(?:Hotel Contact Number|Property Contact Number)[: ]*:/m", $table1[0])
            ?? $this->re("/\n[ ]*Address[: ]*:[:\s]*([^:]{3,}?)\s*$/", $table1[0]) // it-3988892.eml
        ;

        if (!empty($address)) {
            $h->hotel()->address(preg_replace('/\s+/', ' ', $address));
        }

        $addressParts = explode(',', $h->getAddress());

        if (count($addressParts) === 5 && $this->re('/(\d+)/', $addressParts[4]) !== null) {
            $da = $h->hotel()->detailed();
            $da
                ->address(trim($addressParts[0]))
                ->city(trim($addressParts[2]))
                ->country(trim($addressParts[3]))
                ->zip(trim($addressParts[4]));
        }

        // phone
        $phone = $this->re("/^[ ]*(?:Hotel Contact Number|Property Contact Number)[: ]*:[: ]*([+(\d][-. \d)(]{5,}[\d)])[ ]*$/m", $table1[0]);
        $h->hotel()->phone($phone, false, true);

        $columns2 = $this->re("/^([ ]*Arrival[: ]*:[: ]*.+?)^[ ]*Payment Details/ms", $pdf);
        $table2Pos = [0];

        if (preg_match("/(.+[ ]{2})Departure[: ]*:/i", $columns2, $matches)) {
            $table2Pos[] = mb_strlen($matches[1]);
        }
        $table2 = $this->splitCols($columns2, $table2Pos);

        if (count($table2) !== 2) {
            $this->logger->debug('Wrong PDF dates!');

            return false;
        }
        $table2[0] = $this->removeDoubleFields($table2[0]);
        $table2[1] = $this->removeDoubleFields($table2[1]);

        $checkInDate = strtotime($this->re("/^\s*Arrival\b.*:\s*([-[:alpha:]]+[ ]+\d{1,2}[, ]*\d{2,4})\s*$/su", $table2[0]));
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t("CheckInDate"))}]/ancestor::tr[1]/descendant::td[contains(normalize-space(.),'{$this->t("after")}')][last()]",
            null, true, "/(?:\D|\b)({$this->patterns['time']})(?:\D|\b|$)/");

        if ($time) {
            $checkInDate = strtotime($this->normalizeTime($time), $checkInDate);
        }

        $checkOutDate = strtotime($this->re("/^\s*Departure\b.*:\s*([-[:alpha:]]+[ ]+\d{1,2}[, ]*\d{2,4})\s*$/su", $table2[1]));
        $time = $this->http->FindSingleNode("//text()[{$this->eq($this->t("CheckOutDate"))}]/ancestor::tr[1]/td[contains(normalize-space(.),'{$this->t("before")}')][last()]",
            null, true, "/(?:\D|\b)({$this->patterns['time']})(?:\D|\b|$)/");

        if (!$time) {
            $time = $this->http->FindSingleNode("//td[contains(normalize-space(),'前') and not(.//td)]", null, true, '/\b(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*前/iu');
        }

        if ($time) {
            $checkOutDate = strtotime($this->normalizeTime($time), $checkOutDate);
        }

        $h->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        $pdf = $this->removeDoubleFields($pdf);

        $before = [//it could be mix in languages en + other
            'di fare riferimento all e mail di conferma',
            'see confirmation email',
            'bevestigingsmail',
            'aby pozna szczeg y i warunki Promocji',
            'for kampanjen',
            'silahkan lihat email konfirmasi',
            '예약 확정 이메일을 참고하시기 바랍니다',
            'электронном письме с подтверждением',
            '优惠活动条件及其详情 敬请查看确认邮件',
            'correo de confirmación',
            'นโยบายการยกเล กการจอง:', //th
        ];
        $after = [
            'Arrival',
            'Remarks',
            'Benefits Included',
            'Voordelen inbegrepen',
            'Wliczono korzy ci',
            'Inkluderte fordeler',
            'Termasuk keuntungan',
            '[포함된 서비스 옵션]',
            '包含以下優惠內容',
            'Включены преимущества',
            'Servicios incluidos', //es
            'ส ทธ ประโยชน ท ได ร บ:', //th
        ];

        $cancellationPolicy = $this->re("/^[ ]*{$this->preg_implode($this->t('Cancellation Policy'))}[: ]*:[: ]*(.+?)$(?:\n\n|\s+^[ ]*{$this->getRule($after, 'reg', true)})/ms", $pdf)
            ?? $this->re("/^[ ]*(?:Hotel Contact Number|Property Contact Number)[: ]*:[^\n]*$\s+^[ ]*([^\n]*(?i)Cancel.*?)$\s+^[ ]*Arrival[: ]*:/ms", $pdf);

        if (empty($cancellationPolicy)) {
            $cancellationPolicy = preg_replace("#\s+#", ' ',
                $this->re("#{$this->getRule($before, 'reg', true)}\s+(.+?)\n *{$this->getRule($after, 'reg', true)}#s",
                    $pdf));
        }

        if (empty($cancellationPolicy)
            && $cancel = implode(" ",
                $this->http->FindNodes("//*[" . $this->t('CancellationPolicy') . "]/ancestor::tr[1]/following-sibling::tr"))
        ) {
            $cancellationPolicy = trim(str_replace(['Cancellation and Change Policy', 'Cancellation Policy:'], ['', ''],
                $cancel));
        }

        $cP = preg_replace("#\s+#", ' ', $cancellationPolicy);

        if (!empty($cP)) {
            $h->general()->cancellation($cP);
        }

        if ($roomDesc = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t('RoomTypeDescription') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]")) {
            $room->setDescription($roomDesc);
        }

        // p.currencyCode
        // p.total
        $total = $this->http->FindSingleNode("//*[" . $this->getRule('Total') . "]/ancestor-or-self::td[1]/following-sibling::td[1]");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//*[" . $this->getRule('Total') . "]/ancestor-or-self::td[1]/following::td[1]");
        }

        if (preg_match('/([A-Z]{3})\s+([,.\'\d ]+)/', $total, $m)) {
            $h->price()
                ->currency($m[1])
                ->total($this->normalizeAmount($m[2]));
        } elseif (stripos($this->htmlOrPlain,
                'Total Charge to Credit Card') !== false && ($total = $this->re('/Total Charge to Credit Card\s+([A-Z]{3})\s+([\d\.\, ]+)/',
                $this->htmlOrPlain, true))
        ) {
            $h->price()
                ->currency($total[1])
                ->total($this->normalizeAmount($total[2]));
        }

        // status
        if ($this->http->XPath->query("//node()[{$this->getRule('Status')}]")->length > 0) {
            $h->general()->status('Confirmed');
        }

        // deadline
        // nonRefundable
        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }

        return true;
    }

    private function parseHtml(Email $email): void
    {
        $h = $email->add()->hotel();

        $text = $this->htmlOrPlain;

        if (empty($confirmationNumber = $this->http->FindSingleNode("//*[{$this->getRule('ConfirmationNumber')}]/ancestor-or-self::td[1]/following-sibling::td[1]"))) {
            if (empty($confirmationNumber = $this->re("#\n\s*{$this->getRule('ConfirmationNumber', 'reg')}\s*([\w-]+)#msi",
                $text))
            ) {
                $confirmationNumber = $this->http->FindSingleNode("(//*[{$this->getRule('ConfirmationNumber')}]/ancestor-or-self::td[1]/following-sibling::td[1])[2]");
            }
        }

        if (empty($confirmationNumber)) {
            $confirmationNumber = $this->http->FindSingleNode("//title[{$this->starts($this->t('titleIMG'))}]/following::tr[1]/td[1]", null, true, "/{$this->preg_implode($this->t('ConfirmationNumber'))}\s+(\d+)/m");
        }

        if (!empty($confirmationNumber)) {
            $h->general()->confirmation($confirmationNumber);
        }

        $hotelName = $this->re($this->t('HotelName'), $text);

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("(//h2/ancestor::tr[1]/following-sibling::tr[2]//td[3])[1][.//img[contains(@src,'star') or contains(@id,'Star') or contains(@alt,'star')]]/preceding::tr[string-length(normalize-space())>3][1]/descendant::text()[normalize-space()!=''][1]");
        }

        if (!empty($hotelName)) {
            $h->hotel()->name($hotelName);
        }

        if (empty($checkInDateText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('CheckInDate')) . "]/ancestor-or-self::td[1]/following-sibling::td[1]"))) {
            $checkInDateText = $this->re("/\n\s*{$this->preg_implode($this->t('CheckInDate'))}\s*(.+)/i", $text);
        }

        if (preg_match('/^(?<date>.+)\s+\([[:alpha:]\s]*(?<time>' . $this->patterns['time'] . ')\)/u', $checkInDateText,
                $matches)
            || preg_match('/^(?<date>.+)\s+(?<time>' . $this->patterns['time'] . ')/', $checkInDateText, $matches)
        ) {
            $checkInDateNormal = $this->normalizeDate($matches['date']);
            $checkInTimeNormal = $this->normalizeTime($matches['time']);

            if ($checkInDateNormal) {
                $h->booked()->checkIn(strtotime($checkInDateNormal . ', ' . $checkInTimeNormal));
            }
        } elseif ($checkInDateText) {
            $checkInDateNormal = $this->normalizeDate($checkInDateText);

            if ($checkInDateNormal) {
                $h->booked()->checkIn(strtotime($checkInDateNormal));
            }
        }

        if (empty($checkOutDateText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('CheckOutDate')) . "]/ancestor-or-self::td[1]/following-sibling::td[1]"))) {
            $checkOutDateText = $this->re("/\n\s*{$this->preg_implode($this->t('CheckOutDate'))}\s*(.+)/i", $text);
        }

        if (preg_match('/^(?<date>.+)\s+\([[:alpha:]\s]*(?<time>' . $this->patterns['time'] . ')\)/u', $checkOutDateText,
                $matches)
            || preg_match('/^(?<date>.+)\s+(?<time>' . $this->patterns['time'] . ')/', $checkOutDateText, $matches)
        ) {
            $checkOutDateNormal = $this->normalizeDate($matches['date']);
            $checkOutTimeNormal = $this->normalizeTime($matches['time']);

            if ($checkOutDateNormal) {
                $h->booked()->checkOut(strtotime($checkOutDateNormal . ', ' . $checkOutTimeNormal));
            }
        } elseif ($checkOutDateText) {
            $checkOutDateNormal = $this->normalizeDate($checkOutDateText);

            if ($checkOutDateNormal) {
                $h->booked()->checkOut(strtotime($checkOutDateNormal));
            }
        }

        /** @var \DOMNodeList $nodes */
        $nodes = $this->http->XPath->query("(//h2/ancestor::tr[1]/following-sibling::tr[2]//td[3])[1][.//img[contains(@src,'star') or contains(@id,'Star') or contains(@alt,'star')]]/descendant::text()[normalize-space()!=''][1]");

        if ($nodes->length === 0) {
            $nodes = $this->http->XPath->query("(//h2[contains(normalize-space(), \"" . html_entity_decode($hotelName) . "\")]/ancestor::tr[1]/following-sibling::tr[2]//td[3])[1]/descendant::text()[normalize-space()][1]");
        }
        $address = '';

        if ($nodes->length > 0) {
            $text1 = trim($nodes->item(0)->nodeValue);

            if ($this->re("#\b(\d+)\b.+?\b\g{1}\b#s", $text1)) {
                $arr = explode("\n", $text1);
                $cnt = (count($arr) > 1) + (count($arr) % 2);
                $address = nice(implode(" ", array_slice($arr, 0, $cnt)));
            } else {
                $address = nice($text1);
            }
        }
        $h->hotel()->address($address);

        $addressParts = explode(',', $address);

        if (count($addressParts) === 4) {
            $da = $h->hotel()->detailed();
            $da->address(trim($addressParts[0]))
                ->city(trim($addressParts[2]))
                ->country(trim($this->re('/(\w+)/', end($addressParts))))
            ;

            if (($zip = $this->re('/\w+\s+(\d{4,})/', end($addressParts)))) {
                $da->zip($zip);
            }
        }

        if (empty($guestName = $this->http->FindSingleNode("//*[{$this->getRule('GuestNames')}]/ancestor-or-self::td[1]/following-sibling::td[1]"))) {
            if (empty($guestName = $this->re("#\n\s*{$this->getRule('GuestNames', 'reg')}\s*([^\n]+)#", $text))) {
                $guestName = $this->http->FindSingleNode("//node()[starts-with(normalize-space(.), 'Uw boeking is bevestigd en voltooid!')]/preceding-sibling::node()[normalize-space(.)!=''][1]");
            }
        }

        if (empty($guestName) and $this->lang == 'th') {
            $guestName = $this->http->FindSingleNode("//*[{$this->starts($this->t('ConfirmationNumber'))}]/following::tr[normalize-space()][1][{$this->starts($this->t('GuestNames'))}]/td[2]");
        }

        $cancellation = $this->http->FindSingleNode("//*[" . $this->t('CancellationPolicy') . "]/ancestor::tr[1]/following-sibling::tr[1]");

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Guests'))}]/following::text()[{$this->eq($this->t('Conditions d\'annulation et de modification'))}]/ancestor::tr[1]/following-sibling::tr[1]");
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking ID')]/following::text()[normalize-space()='Cancellation policy'][1]/following::text()[normalize-space()][1]");
        }

        $h->general()
            ->traveller($guestName)
            ->cancellation($cancellation);

        $guests = $this->re("#(\d+) {$this->getRule('Adults', 'reg')}#i",
            $this->http->FindSingleNode("//*[{$this->getRule('Guests')}]/ancestor-or-self::td[1]/following-sibling::td[1]"));

        if (empty($guests) and $this->lang == 'th') {
            $guests = $this->re("#{$this->getRule('Adults', 'reg')}\s(\d+)#i",
                $this->http->FindSingleNode("//*[{$this->starts($this->t('Meal option:'))}]/preceding-sibling::tr[normalize-space()][1][{$this->starts($this->t('Guests'))}]/td[2]"));
        }

        if ($this->lang != 'ar' && !empty($guests)) {
            $h->booked()->guests($guests);
        } elseif (!empty($guests) and $this->lang == 'ar') {
            $h->booked()->guests($guests);
        }

        if (empty($rooms = $this->re("#(\d+)\s?(?:Room|kamer|間房|ห้อง|rum)#i",
            $this->http->FindSingleNode("//*[{$this->getRule('Rooms')}]/ancestor-or-self::td[1]/following-sibling::td[1]")))) {
            //ar
            $rooms = $this->re("/.*?غرفة\s+(\w+)/mu",
                    $this->http->FindSingleNode("//*[{$this->getRule('Rooms')}]/ancestor-or-self::td[1]/following-sibling::td[1]"));

            switch ($rooms) {
                case 'واحدة':
                case 'واحد':
                    $rooms = 1;

                    break;
            }
        }

        if (!empty($rooms)) {
            $h->booked()->rooms($rooms);
        }

        $this->logger->debug("//*[contains(text(), '" . $this->t('RoomTypeDescription') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]");
        $room = $h->addRoom();

        $room
            ->setType(trim($this->http->FindSingleNode("//*[" . $this->getRule('Total') . "]/ancestor::tr[1]/preceding-sibling::tr[string-length(normalize-space(./td[2]))>2][last()]/td[1]"),
                ': '));

        $description = $this->http->FindSingleNode("//*[contains(text(), '" . $this->t('RoomTypeDescription') . "')]/ancestor-or-self::td[1]/following-sibling::td[1]");

        if (!empty($description)) {
            $room->setDescription($description, true, true);
        }

        $total = $this->http->FindSingleNode("//*[" . $this->getRule('Total') . "]/ancestor-or-self::td[1]/following-sibling::td[1]");

        if (preg_match('/([A-Z]{3})\s+([\d\.\, ]+)/', $total, $m)) {
            $h->price()
                ->currency($m[1])
                ->total($this->normalizeAmount($m[2]));
        }

        $discount = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Discount')]", null, true, "/\s\D([\d\,\.]+)\s*$/");

        if (!empty($discount)) {
            $h->price()
                ->discount($discount);
        }

        if ($this->http->XPath->query("//node()[{$this->getRule('Status')}]")->length > 0) {
            $h->general()->status('Confirmed');
        }

        if (!empty($node = $h->getCancellation())) {
            $this->detectDeadLine($h, $node);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h, string $cancellationText)
    {
        if (
            preg_match("#Any cancellation received within (\d+) day\/?s? prior to arrival (?:date )?will incur the (?:full period|first night|first \d+ nights) charge.#i", // en
                $cancellationText, $m)
            || preg_match("#Bei Stornierung innerhalb von (\d+) Tagen vor Anreisedatum wird eine Geb#iu", // de
                $cancellationText, $m)
            || preg_match("#If cancelled or modified up to (\d+) days before date of arrival, no fee will be charged.#i", // en
                $cancellationText, $m)
            || preg_match("#Enhver kansellering mottatt innen (\d+) dager før ankomst vil medføre en avgift på#iu", // no
                $cancellationText, $m)
            || preg_match("#Semua pembatalan yang diterima dalam (\d+) hari sebelum kedatangan akan dikenakan biaya untuk malam pertama.#i", // id
                $cancellationText, $m)
            || preg_match("#체크인 날짜 전 날을 기준으로 (\d+)일 이내에 예약을 취소하실 경우 예약 요금의#iu", // ko
                $cancellationText, $m)
            || preg_match("#В случае отмены или изменения бронирования в срок до (\d+) суток до даты заезда штраф не взимается.#iu", // ru
                $cancellationText, $m)
            || preg_match("#Las cancelaciones recibidas con antelación inferior a (\d+) día a la fecha de llegada serán penalizadas con el importe de la primera noche.#iu", // es
                $cancellationText, $m)
            || preg_match("#Las cancelaciones recibidas con (\d{1,3}) o menos días de antelación a la fecha de llegada serán penalizadas con el importe completo de la reserva\.#iu", // es
                $cancellationText, $m)
            || preg_match("#如果在入住前(\d+)天内取消预订，将被收取第1晚的房费作为取消费。#iu", // zh
                $cancellationText, $m)
            || preg_match("#如果在入住前(\d+)天内取消预订 将被收取第\d+晚的房费作为取消费#iu", // zh
                $cancellationText, $m)
            /*|| preg_match("#如果在入住日期前(\d+)天內提交預訂取消申請，將被收取訂房總額的首晚作為取消費用#iu", // zh
                $cancellationText, $m)*/
            || preg_match("#若於入住日期前(\d+)天內取消預訂，需支付全額訂房費用#iu", // zh
                $cancellationText, $m)
            || preg_match("#Qualsiasi cancellazione ricevuta entro (\d+) giorno/i prima dell arrivo sarà soggetta all addebito#iu", // it
                $cancellationText, $m)
            || preg_match("#Qualsiasi cancellazione pervenuta (\d{1,3}) giorno prima della data di arrivo incorrerà nell'addebito della prima notte\.#iu", // it
                $cancellationText, $m)
            || preg_match("#ご到着日の(\d+)日前以降のキャンセルには、ご予約料金の全額がキャンセル料として発生します。#iu", // ja
                $cancellationText, $m)
            || preg_match("#หากท านยกเล กการจองห องพ ก (\d+) ว นก อนว นเช คอ น ท านจะถ กเร ยกเก#iu", // th
                $cancellationText, $m)
            || preg_match("#หากท่านยกเลิกการจองห้องพัก (\d+) วันก่อนวันเช็คอิน ท่านจะถูกเรียกเก็บเงินเต็มจำนวน#iu", // th
                $cancellationText, $m)
            || preg_match("/في حال استلام طلب إلغاء الحجز خلال (\d+) أيام السابقة /mu", // ar
                $cancellationText, $m)
            || preg_match("/Avbokningar mottagna inom (\d+) dag innan ankomstdatumet/mu", // sv
                $cancellationText, $m)
        ) {
            $days = $m[1]; //+1;
            $h->booked()->deadlineRelative($days . ' days', '00:00');
        }
        $h->booked()
            ->parseNonRefundable('Please note, if cancelled, modified or in case of no-show, the total price of the reservation will be charged')
            ->parseNonRefundable('Please note, if cancelled or modified, the total price of the reservation will be charged')
            ->parseNonRefundable('Denne reservation er ikke-refunderbar og kan ikke ændres eller rettes')
            ->parseNonRefundable('Ta rezerwacja jest bezzwrotna i nie może zostać poprawiona lub zmodyfikowana.')
            ->parseNonRefundable('Esta reserva não é reembolsável e não pode ser alterada ou modificada.')
            ->parseNonRefundable('Esta reserva no admite reembolso y no se puede modificar ni cancelar.')
            ->parseNonRefundable('Стоимость данного бронирования не возвращается, бронирование не может быть дополнено или изменено.')
            ->parseNonRefundable('本預訂經確認後即不退費，且不可被修正或更改')
            ->parseNonRefundable('此為不可退訂之房型，預訂完成後將不能修改或修正')
            ->parseNonRefundable('Cette réservation est non-remboursable et')
            ->parseNonRefundable('Deze boeking kan niet worden verschoven of aangepast. Er wordt geen restitutie verleend.')
            ->parseNonRefundable('このご予約をキャンセルされた場合は返金されません。');
    }

    private function re($re, $text, $multiple = false)
    {
        if (!$multiple) {
            if (is_string($text) && preg_match($re, $text, $m)) {
                return $m[1];
            } else {
                return null;
            }
        } else {
            if (is_string($text) && preg_match($re, $text, $m)) {
                return $m;
            } else {
                return null;
            }
        }
    }

    private function getRule($name, $type = 'xpath', $nameIsArray = false)
    {
        if ($nameIsArray) {
            $rule = $name;
        } else {
            $rule = (array) $this->t($name);
        }

        if ($type == 'xpath') {
            $rule = implode(" or ", array_map(function ($s) { return "contains(text(), '" . $s . "')"; }, $rule));
        } else {
            $rule = "(?:" . implode("|", array_map(function ($s) { return preg_quote($s); }, $rule)) . ")";
        }

        return $rule;
    }

    private function t($s)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function removeDoubleFields(?string $s): ?string
    {
        $whiteList = array_merge([
            'Booking ID', 'Booking Reference No', 'Client', 'Member ID', 'Country of Residence',
            'Country of Passport', 'Property', 'Hotel', 'Address', 'Property Contact Number',
            'Hotel Contact Number', 'Number of Rooms', 'Number of Extra Beds', 'Number of Adults',
            'Max Occupancy', 'Number of Children', 'Breakfast', 'Room Type', 'Promotion',
            'Benefits Included', 'Arrival', 'Departure', 'Payment Details', 'Payment Method',
            'Booked And Payable By', 'Remarks', 'Included',
            'behandeld als No-Show', // it-9181422.eml
        ], (array) $this->t('Cancellation Policy'));
        $whitePattern = $this->preg_implode($whiteList);
        $rows = explode("\n", $s);

        foreach ($rows as $key => $r) {
            if ((preg_match("/^[ (]*([[:alpha:]][-.[:alpha:] ]*?)[ )]*[:]+/mu", $r, $m) || preg_match("/^[ ]*(ﺍﻟﺮﻗﻢ ﺍﻟﻤﺮﺟﻌﻲ ﻟﻠﺤﺠﺰ|住客姓名)(?:[ ]|$)/mu", $r, $m))
                && !preg_match("/^{$whitePattern}$/", $m[1])
            ) {
                $rows[$key] = rtrim(preg_replace("/^{$this->preg_implode($m[0])}/", str_repeat(' ', mb_strlen($m[0])), $r));
            }
        }

        if (count($rows)) {
            $s = implode("\n", $rows);
        }

        return $s;
    }

    private function extractPDF(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs)) {
            $i = array_shift($pdfs);
            $pdfText = \PDF::convertToText($parser->getAttachmentBody($i));
        } else {
            return null;
        }

        return $pdfText;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
    }

    private function normalizeDate(string $string)
    {
        $this->logger->debug('IN-' . $string);

        if (preg_match('/([^\d\W]{3,})\s+(\d{1,2})\s*,\s*(\d{4})/u', $string, $matches)) { // December 9, 2015
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/(\d{1,2})[.\s]+([^\d\W]{3,})[.\s]+(\d{4})/u', $string, $matches)) { // 08 July 2018
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{4})年(\d+)月(\d+)日(?:.+)?$/u', $string, $matches)) { // 2018年11月28日
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        } elseif (($this->lang == 'th') and (preg_match('/(\d+)\s+(\S+)\s+(\d{4})/u', $string, $matches))) { // 23 ธันวาคม 2562 (หลัง THAI
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];

            if (($year - date('Y')) > 400) {
                $year = $year - 543;
            }
        } elseif (($this->lang == 'ar') and (preg_match('/^(\d+)\s+(\S+)[,]\s+(\d+)/', $string, $matches))) { // 10 ديسمبر, 2019 (بعد
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }
            $this->logger->debug('OUR-' . $day . ' ' . $month . ($year ? ' ' . $year : ''));

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return false;
    }

    private function normalizeTime(string $s): string
    {
        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1];
        } // 21:51 PM    ->    21:51

        return $s;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }
}
