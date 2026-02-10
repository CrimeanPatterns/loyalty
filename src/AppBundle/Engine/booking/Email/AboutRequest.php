<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AboutRequest extends \TAccountChecker
{
    public $mailFiles = "booking/it-11619418.eml, booking/it-16059626.eml, booking/it-19909037.eml, booking/it-28414114.eml, booking/it-28468538.eml, booking/it-33924872.eml, booking/it-33967322.eml, booking/it-36018378.eml, booking/it-43154348.eml, booking/it-74741056.eml"; // +1 bcdtravel(html)[nl]

    public static $detectProvider = [
        'booking' => [
            'from'         => ['@property.booking.com', 'noreply@booking.com', '@mchat.booking.com'],
            'providerText' => ['Booking.com'],
            'providerLink' => ['//www.booking.com', '//secure.booking.com'],
            'subject'      => [
                'ru'  => 'Особый запрос для Вашего бронирования',
                'es'  => 'Tu reserva en el',
                'es2' => 'tenés mensajes nuevos del Asistente de Booking',
                'fr'  => "Votre réservation à l'établissement",
                'nl'  => 'Uw boeking bij',
                'en'  => 'Your booking at',
                'Special Request for your Reservation',
                'sv'  => 'Din bokning på',
                'it'  => 'La tua prenotazione presso',
                'tr'  => 'tesisinden bir mesajınız var',
                'da'  => 'Din reservation på',
                'ja'  => '様からメッセージが届きました',
                '）の特別リクエスト',
                'zh'  => '您有来自Booking小助手的新消息',
                'ca'  => 'Tens un missatge de',
                'Petició especial de la reserva',
                'Tens un nou missatge de',
                //                'hr' => '',
                'de' => 'Besondere Anfrage für Ihre Buchung',
                'pl' => 'Masz nową wiadomość od obiektu',
                'pt' => 'Sua reserva',
                'Você recebeu uma mensagem de',
                'et' => 'seoses teie broneeringuga',
                'hu' => 'Az Ön különleges kérése',
                'ko' => '예약',
                'cs' => 'Zvláštní požadavek',
                'no' => 'Du har en ny melding fra',
                'sl' => 'Prek Booking.com ste prejeli novo sporočilo od nastanitve',
                'ro' => 'Ați primit un mesaj nou de la ',
                'uk' => 'Особливий запит для вашого бронювання',
                'he' => 'יש לכם הודעה חדשה מ-',
                'lt' => 'atsiuntė jums naują žinutę per Booking.com',
                'fi' => 'Sinulle on uusi viesti majoituspaikalta',
                'el' => 'Ειδικό Αίτημα για την Κράτησή σας',
                'Έχετε ένα νέο μήνυμα από το',
                'sk' => 'Máte novú správu od ubytovania',
                'lv' => 'Jūs esat saņēmis jaunu ziņu no naktsmītnes',
                // th
                'คำขอพิเศษสำหรับการจอง',
                // bg
                'Специално изискване с резервационен номер',
                // bs
                'Poseban zahtev uz vašu rezervaciju pod brojem',
            ],
        ],
        'agoda' => [
            'from'         => 'notification@agodabiz.com',
            'providerText' => ['Agoda.com'],
            'providerLink' => ['www.agoda.com'],
            'subject'      => [
                'en' => 'You have a message from',
            ],
        ],
    ];

    public $langDetectors = [
        'ru' => ['Имя гостя:', 'Имя гостя :', 'Имя Гостя:', 'Имя Гостя :'],
        'es' => ['Nombre del cliente:', 'Nombre del cliente :', 'Nombre del huésped:'],
        'fr' => ['Nom du client:', 'Nom du client :', 'Nom Du Client:', 'Nom Du Client :'],
        'nl' => ['Naam gast:', 'Naam gast :', 'Naam Gast:', 'Naam Gast :'],
        'it' => ["Nome dell'ospite:", "Nome dell'ospite :"],
        'lt' => ['Svečio vardas ir pavardė:'],
        'sv' => ['Gästens namn:', 'Gästens namn :'],
        'da' => ["Gæstens navn:", "Gæstens navn :"],
        'ja' => ["宿泊者氏名："],
        'sl' => ['Ime nastanitve:'],
        'bs' => ['Prijavljivanje:'],
        'hr' => ["Ime gosta:"],
        'de' => ["Name des Gastes:"],
        'pl' => ["Imię i nazwisko gościa:"],
        'pt' => ['Nome do hóspede'],
        'zh' => ['住客姓名：'],
        'ca' => ['Nom del client:'],
        'tr' => ['Konuk adı:'],
        'et' => ['Külastaja nimi:'],
        'hu' => ['Vendég neve:'],
        'ko' => ['투숙객 성함:'],
        'cs' => ['Jméno hosta'],
        'no' => ['Gjestens navn:'],
        'ro' => ['Nume oaspete:'],
        'uk' => ['Ім\'я гостя:'],
        'ar' => ['اسم مكان الإقامة'],
        'he' => ['שם האורח/ת:', 'שם האורח'],
        'fi' => ['Asiakkaan nimi'],
        'el' => ['Όνομα πελάτη:'],
        'sk' => ['Meno hosťa:'],
        'lv' => ['Viesa vārds un uzvārds:'],
        'th' => ['ชื่อผู้เข้าพัก'],
        'bg' => ['Име на обекта:'],
        'en' => ['Guest name:', 'Guest name :', 'Guest Name:', 'Guest Name :'],
    ];

    public $lang = '';
    public $prefix = '';
    public static $dict = [
        'ru' => [],
        'es' => [
            'Номер бронирования'             => 'Número de reserva',
            'Название объекта размещения'    => ['Nombre del establecimiento', 'Nombre de la propiedad'],
            'Всего гостей'                   => ['Total de personas', 'Total de huéspedes'],
            'Всего номеров'                  => 'Total de habitaciones',
            'Имя гостя'                      => ['Nombre del cliente', 'Nombre del huésped'],
            'Заезд'                          => ['Check-in', 'Entrada'],
            'Отъезд'                         => ['Check-out', 'Salida'],
            'У вас новое сообщение от гостя' => 'Tienes un mensaje nuevo de un cliente',

            // Url
            'Забронировано для'             => 'Reservaste para',
            'Сведения о бронировании'       => 'Datos de la reserva',
            'взрослый'                      => 'adulto',
            'Цена'                          => 'Precio', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Precio final', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Dirección',
            'Телефон'                       => 'Teléfono',
            'Правила отмены'                => 'Condiciones de cancelación',
            'Стоимость отмены бронирования' => 'Cargos de cancelación',
            'Бронирование было отменено'    => 'Reserva cancelada',
        ],
        'zh' => [
            'Номер бронирования'          => ['订单编号：', '確認函編號：', '訂單編號：'],
            'Название объекта размещения' => ['住宿名称：', '住宿名稱：'],
            'Всего гостей'                => ['入住总人数：', '人數：'],
            'Всего номеров'               => ['客房总数：', '客房數：'],
            'Имя гостя'                   => ['住客姓名：', '客人姓名'],
            'Заезд'                       => ['入住日期：', '入住：', '入住时间'],
            'Отъезд'                      => ['退房日期：', '退房：', '退房时间'],
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => '预订对象',
            'Сведения о бронировании'       => '预订资料',
            'взрослый'                      => '位成人',
            'Цена'                          => '价格', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com支付',
            'Итоговая цена'                 => '总价', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => '地址',
            'Телефон'                       => '电话',
            'Правила отмены'                => '取消政策',
            'Стоимость отмены бронирования' => '预订取消费用',
            'Бронирование было отменено'    => '预订已被取消',
        ],
        'ca' => [
            'Номер бронирования'          => 'Número de la reserva',
            'Название объекта размещения' => ['Nom de l\'establiment', 'Nom de l\'allotjament'],
            'Всего гостей'                => 'Total de persones',
            'Всего номеров'               => 'Total d\'habitacions',
            'Имя гостя'                   => 'Nom del client',
            'Заезд'                       => 'Entrada',
            'Отъезд'                      => 'Sortida',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Reserva per a',
            'Сведения о бронировании'       => 'Dades de la reserva',
            'взрослый'                      => 'adult',
            'Цена'                          => 'Preu', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Preu final', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adreça',
            'Телефон'                       => 'Telèfon',
            'Правила отмены'                => 'Condicions de cancel·lació',
            'Стоимость отмены бронирования' => 'Càrrec de cancel·lació',
            'Бронирование было отменено'    => 'S\'ha cancel·lat la reserva',
        ],
        'tr' => [
            'Номер бронирования'             => 'Rezervasyon numarası',
            'Название объекта размещения'    => 'Tesis adı',
            'Всего гостей'                   => 'Toplam konuk',
            'Всего номеров'                  => 'Toplam oda',
            'Имя гостя'                      => 'Konuk adı',
            'Заезд'                          => 'Check-in',
            'Отъезд'                         => 'Check-out',
            'У вас новое сообщение от гостя' => 'Bir konuktan yeni bir mesajınız var',

            // Url
            //            'Забронировано для'             => '',
            'Сведения о бронировании'       => 'Rezervasyon detayları',
            'взрослый'                      => 'yetişkin',
            'Цена'                          => 'Fiyat', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            //            'Итоговая цена'                 => '', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adres',
            //            'Телефон'                       => '',
            'Правила отмены'                => 'Ön ödeme',
            'Стоимость отмены бронирования' => 'İptal ücreti',
            'Бронирование было отменено'    => 'Rezervasyon iptal edilmiştir',
        ],
        'fr' => [
            'Номер бронирования'             => 'Numéro de réservation',
            'Название объекта размещения'    => "Nom de l'établissement",
            'Всего гостей'                   => 'Nombre total de personnes',
            'Всего номеров'                  => "Nombre total d'hébergements",
            'Имя гостя'                      => 'Nom du client', // + url
            'Заезд'                          => 'Arrivée', // + url
            'Отъезд'                         => 'Départ', // + url
            'У вас новое сообщение от гостя' => "Vous avez reçu un nouveau message d'un client",

            // Url
            'Забронировано для'                      => ['Vous avez réservé pour'],
            'Сведения о бронировании'                => 'Détails de la réservation',
            'взрослый'                               => ['adulte'],
            'Цена'                                   => ['Tarif'],
            'Booking.com платит'                     => ['Montant pris en charge par Booking.com'],
            'Итоговая цена'                          => ['Montant final'],
            'Адрес'                                  => 'Adresse',
            'Телефон'                                => 'Téléphone',
            'Правила отмены'                         => ['Conditions d\'annulation'],
            'Стоимость отмены бронирования'          => ['Frais d\'annulation'],
            'Бронирование было отменено'             => 'Cette réservation a été annulée',
        ],
        'nl' => [
            'Номер бронирования'          => ['Bevestigingsnummer:', 'Boekingsnummer:'],
            'Название объекта размещения' => 'Naam accommodatie',
            'Всего гостей'                => 'Totaal aantal gasten',
            'Всего номеров'               => 'Totaal aantal kamers',
            'Имя гостя'                   => 'Naam gast',
            'Заезд'                       => 'Inchecken',
            'Отъезд'                      => 'Uitchecken',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Je hebt geboekt voor',
            'Сведения о бронировании'       => 'Boekingsgegevens',
            'взрослый'                      => 'volwasse',
            'Цена'                          => 'Prijs', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Eindprijs', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adres',
            'Телефон'                       => 'Telefoon',
            'Правила отмены'                => 'Annuleringsvoorwaarden',
            'Стоимость отмены бронирования' => 'Annuleringskosten',
            'Бронирование было отменено'    => 'Let op: deze reservering is geannuleerd',
        ],
        'en' => [
            'Номер бронирования'             => ['Booking number', 'Booking Number'],
            'Название объекта размещения'    => ['Property name', 'Property Name'],
            'Всего гостей'                   => 'Total guests',
            'Всего номеров'                  => 'Total rooms',
            'Имя гостя'                      => 'Guest name', // + url
            'Заезд'                          => 'Check-in', // + url
            'Отъезд'                         => 'Check-out', // + url
            'У вас новое сообщение от гостя' => 'You have a new message from a guest',

            // Url
            'Забронировано для'             => ['You booked for'],
            'Сведения о бронировании'       => 'Booking details',
            'взрослый'                      => ['adult'],
            'Цена'                          => ['Price'], // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => ['Booking.com pays'],
            'Итоговая цена'                 => ['Final price'], // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => ['Address:', 'Address'],
            'Телефон'                       => ['Phone:', 'Phone'],
            'Правила отмены'                => ['Cancellation policy'],
            'Стоимость отмены бронирования' => ['Cancellation cost'],
            'Бронирование было отменено'    => 'The booking has been cancelled',
        ],
        'sv' => [
            'Номер бронирования'          => ['Bokningsnummer'],
            'Название объекта размещения' => ['Boendets namn'],
            'Всего гостей'                => 'Totalt antal gäster',
            'Всего номеров'               => 'Totalt antal rum',
            'Имя гостя'                   => 'Gästens namn',
            'Заезд'                       => 'Incheckning',
            'Отъезд'                      => 'Utcheckning',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Du har bokat för',
            'Сведения о бронировании'       => 'Bokningsuppgifter',
            'взрослый'                      => 'vux',
            'Цена'                          => 'Pris', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Slutpris', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adress',
            'Телефон'                       => 'Telefon',
            'Правила отмены'                => 'Avbokningsvillkor',
            'Стоимость отмены бронирования' => 'Avbokningskostnad',
            'Бронирование было отменено'    => 'Bokningen har avbokats',
        ],
        'it' => [
            'Номер бронирования'             => ['Numero di prenotazione'],
            'Название объекта размещения'    => ['Nome struttura'],
            'Всего гостей'                   => 'Ospiti totali',
            'Всего номеров'                  => 'Totale delle camere',
            'Имя гостя'                      => "Nome dell'ospite",
            'Заезд'                          => ['Check-in', 'Arrivo'],
            'Отъезд'                         => ['Check-out', 'Partenza'],
            'У вас новое сообщение от гостя' => 'Nuovo messaggio da un ospite',

            // Url
            'Забронировано для'             => 'Hai prenotato per',
            'Сведения о бронировании'       => 'Informazioni sulla prenotazione',
            'взрослый'                      => 'adult',
            'Цена'                          => 'Prezzo', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Prezzo finale', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Indirizzo',
            'Телефон'                       => 'Telefono',
            'Правила отмены'                => 'Condizioni di cancellazione',
            'Стоимость отмены бронирования' => 'Costi di cancellazione',
            'Бронирование было отменено'    => 'La prenotazione è stata cancellata',
        ],
        'da' => [
            'Номер бронирования'          => ['Bookingnummer'],
            'Название объекта размещения' => ['Overnatningsstedets navn'],
            'Всего гостей'                => 'Antal gæster',
            'Всего номеров'               => 'Samlet antal værelser',
            'Имя гостя'                   => "Gæstens navn",
            'Заезд'                       => 'Indtjekning',
            'Отъезд'                      => 'Udtjekning',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Du har booket til',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'voks',
            'Цена'                          => 'Pris', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Din pris', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresse',
            'Телефон'                       => 'Telefonnummer',
            'Правила отмены'                => 'Afbestillingsregler',
            'Стоимость отмены бронирования' => 'Pris for afbestilling',
            'Бронирование было отменено'    => 'Reservationen er nu annulleret',
        ],
        'ja' => [
            'Номер бронирования'          => ['予約番号：'],
            'Название объекта размещения' => ['宿泊施設名：'],
            'Всего гостей'                => '合計人数：',
            'Всего номеров'               => '部屋数：',
            'Имя гостя'                   => "宿泊者氏名：",
            'Заезд'                       => 'チェックイン',
            'Отъезд'                      => 'チェックアウト',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => '宿泊者の内訳',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => '大人',
            'Цена'                          => '料金', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.comによる負担額',
            'Итоговая цена'                 => '最終料金', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => '住所',
            'Телефон'                       => '電話番号',
            'Правила отмены'                => 'キャンセルポリシー',
            'Стоимость отмены бронирования' => 'キャンセル料',
            'Бронирование было отменено'    => 'この予約はキャンセルされました。',
        ],
        'hr' => [
            'Номер бронирования'             => 'Broj rezervacije',
            'Название объекта размещения'    => 'Ime objekta',
            'Всего гостей'                   => 'Ukupan br. gostiju',
            'Всего номеров'                  => 'Ukupan br. jedinica',
            'Имя гостя'                      => 'Ime gosta',
            'Заезд'                          => 'Prijava',
            'Отъезд'                         => 'Odjava',
            'У вас новое сообщение от гостя' => 'Imate novu poruku od gosta',

            // Url
            'Забронировано для'             => 'Rezervirali ste za',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'odrasle osobe',
            'Цена'                          => 'Cijena', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com plaća',
            'Итоговая цена'                 => 'Završna cijena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresa',
            'Телефон'                       => 'Telefon',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Trošak otkazivanja rezervacije',
            'Бронирование было отменено'    => 'Rezervacija je otkazana',
        ],
        'de' => [
            'Номер бронирования'             => 'Buchungsnummer',
            'Название объекта размещения'    => 'Unterkunftsname',
            'Всего гостей'                   => 'Gesamtzahl der Gäste',
            'Всего номеров'                  => 'Gesamtzahl der Zimmer',
            'Имя гостя'                      => 'Name des Gastes',
            'Заезд'                          => ['Check-in', 'Anreise'],
            'Отъезд'                         => ['Check-out', 'Abreise'],
            'У вас новое сообщение от гостя' => 'Sie haben eine neue Nachricht von einem Gast',

            // Url
            'Забронировано для'             => 'Sie haben gebucht für',
            'Сведения о бронировании'       => 'Buchungsdetails',
            'взрослый'                      => 'Erwachs',
            'Цена'                          => 'Preis', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Endpreis', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresse',
            'Телефон'                       => 'Telefon',
            'Правила отмены'                => 'Stornierungsbedingungen',
            'Стоимость отмены бронирования' => 'Stornierungsgebühren',
            'Бронирование было отменено'    => 'Die Buchung wurde storniert',
        ],
        'pl' => [
            'Номер бронирования'             => 'Numer rezerwacji',
            'Название объекта размещения'    => 'Nazwa obiektu',
            'Всего гостей'                   => 'Liczba gości',
            'Всего номеров'                  => 'Łączna liczba pokoi',
            'Имя гостя'                      => 'Imię i nazwisko gościa',
            'Заезд'                          => 'Zameldowanie',
            'Отъезд'                         => 'Wymeldowanie',
            'У вас новое сообщение от гостя' => 'Masz nową wiadomość od gościa',

            // Url
            'Забронировано для'             => 'Zarezerwowałeś dla',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'dorosł',
            'Цена'                          => 'Cena', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com płaci',
            'Итоговая цена'                 => 'Cena końcowa', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adres',
            'Телефон'                       => 'Telefon',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Koszt anulowania rezerwacji',
            'Бронирование было отменено'    => 'Rezerwacja została anulowana',
        ],
        'pt' => [
            'Номер бронирования'             => 'Número da reserva',
            'Название объекта размещения'    => 'Nome da propriedade',
            'Всего гостей'                   => 'Total de hóspedes',
            'Всего номеров'                  => 'Total de quartos',
            'Имя гостя'                      => 'Nome do hóspede',
            'Заезд'                          => 'Check-in',
            'Отъезд'                         => 'Check-out',
            'У вас новое сообщение от гостя' => 'Tem uma nova mensagem de um cliente',

            // Url
            'Забронировано для'             => ['Sua é reserva é para', 'Reservou para'],
            'Сведения о бронировании'       => 'Dados da reserva',
            'взрослый'                      => 'adulto',
            'Цена'                          => 'Preço', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Preço final', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => ['Endereço', 'Morada'],
            'Телефон'                       => 'Telefone',
            'Правила отмены'                => ['Condições de cancelamento', 'Política de cancelamento'],
            'Стоимость отмены бронирования' => 'Custos de cancelamento',
            'Бронирование было отменено'    => 'A reserva foi cancelada',
        ],
        'et' => [
            'Номер бронирования'             => 'Broneeringu number',
            'Название объекта размещения'    => 'Majutusasutuse nimi',
            'Всего гостей'                   => 'Külastajaid kokku',
            'Всего номеров'                  => 'Tube kokku',
            'Имя гостя'                      => 'Külastaja nimi',
            'Заезд'                          => 'Sisseregistreerimine',
            'Отъезд'                         => 'Väljaregistreerimine',
            'У вас новое сообщение от гостя' => 'Teil on külastajalt uus sõnum',

            // Url
            'Забронировано для'             => 'Külastajad',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'täiskasvanut',
            'Цена'                          => 'Hind', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com maksab',
            'Итоговая цена'                 => 'Lõpphind', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Aadress',
            'Телефон'                       => 'Telefoninumber',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Tühistamistasu',
            'Бронирование было отменено'    => 'Teie broneering on tühistatud',
        ],
        'hu' => [
            'Номер бронирования'          => ['Foglalási szám:'],
            'Название объекта размещения' => ['Szálláshely neve:'],
            'Всего гостей'                => 'Vendégek száma',
            'Всего номеров'               => 'Szobák száma:',
            'Имя гостя'                   => 'Vendég neve:',
            'Заезд'                       => 'Bejelentkezés:',
            'Отъезд'                      => 'Kijelentkezés:',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Foglalási létszám',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'felnőttnek',
            'Цена'                          => 'Ár', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'A Booking.com ennyit áll',
            'Итоговая цена'                 => 'Fizetendő ár', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Cím',
            'Телефон'                       => 'Telefonszám',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Lemondási díj',
            'Бронирование было отменено'    => 'Foglalása lemondásra került',
        ],
        'ko' => [
            'Номер бронирования'          => ['예약 번호:'],
            'Название объекта размещения' => ['숙소 명칭:'],
            'Всего гостей'                => '총 투숙객 수',
            'Всего номеров'               => '총 객실 수:',
            'Имя гостя'                   => '투숙객 성함:',
            'Заезд'                       => '체크인:',
            'Отъезд'                      => '체크아웃:',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => '예약 인원',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => '명',
            'Цена'                          => '요금', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com에서 부담',
            'Итоговая цена'                 => '최종 결제 금액', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => '주소',
            'Телефон'                       => '전화번호',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => '취소 수수료',
            'Бронирование было отменено'    => '예약이 취소되었습니다',
        ],
        'cs' => [
            'Номер бронирования'          => ['Číslo rezervace:'],
            'Название объекта размещения' => ['Název ubytování:'],
            'Всего гостей'                => 'Hostů celkem',
            'Всего номеров'               => 'Pokojů celkem:',
            'Имя гостя'                   => ['Jméno hosta:', 'Jméno hosta'],
            'Заезд'                       => ['Příjezd:', 'Příjezd'],
            'Отъезд'                      => ['Odjezd:', 'Odjezd'],
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Rezervace pro:',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'dospěl',
            'Цена'                          => 'Cena', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Konečná cena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresa',
            'Телефон'                       => 'Telefon',
            'Правила отмены'                => 'Podmínky zrušení rezervace',
            'Стоимость отмены бронирования' => 'Poplatek za zrušení rezervace',
            'Бронирование было отменено'    => 'Rezervace byla zrušena',
        ],
        'no' => [
            'Номер бронирования'             => ['Bookingnummer:'],
            'Название объекта размещения'    => ['Navn på overnattingsstedet:'],
            'Всего гостей'                   => 'Totalt antall gjester:',
            'Всего номеров'                  => 'Totalt antall rom:',
            'Имя гостя'                      => ['Gjestens navn:', 'Navn på gjest'],
            'Заезд'                          => ['Innsjekking:', 'Innsjekking'],
            'Отъезд'                         => ['Utsjekking:', 'Utsjekking'],
            'У вас новое сообщение от гостя' => 'Du har en ny melding fra en gjest',

            // Url
            'Забронировано для'             => 'Du har booket for',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'voks',
            'Цена'                          => 'Pris', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Sluttpris', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresse',
            'Телефон'                       => 'Telefon',
            'Правила отмены'                => 'Avbestillingsvilkår',
            'Стоимость отмены бронирования' => 'Avbestillingsgebyr',
            'Бронирование было отменено'    => 'The booking has been cancelled',
        ],
        'sl' => [
            'Номер бронирования'          => ['Številka rezervacije:'],
            'Название объекта размещения' => ['Ime nastanitve:'],
            'Всего гостей'                => 'Skupno št. gostov:',
            'Всего номеров'               => 'Skupno št. sob:',
            'Имя гостя'                   => 'Ime gosta:',
            'Заезд'                       => 'Prijava:',
            'Отъезд'                      => 'Odjava:',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Rezervirali ste za',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'odrasla',
            'Цена'                          => 'Cena', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com plača',
            'Итоговая цена'                 => 'Skupna cena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Naslov',
            'Телефон'                       => 'Telefon',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Strošek odpovedi rezervacije',
            'Бронирование было отменено'    => 'Rezervacija je preklicana',
        ],
        'ro' => [
            'Номер бронирования'             => ['Număr rezervare:'],
            'Название объекта размещения'    => ['Nume proprietate:'],
            'Всего гостей'                   => 'Număr total oaspeți:',
            'Всего номеров'                  => 'Număr total camere:',
            'Имя гостя'                      => ['Nume oaspete:', 'Numele clientului'],
            'Заезд'                          => ['Check-in:', 'Check-in'],
            'Отъезд'                         => ['Check-out'],
            'У вас новое сообщение от гостя' => 'Aveți un mesaj nou de la un client',

            // Url
            'Забронировано для'             => 'Ați rezervat pentru',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'adulț',
            'Цена'                          => 'Preț', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Preţ final', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresă',
            'Телефон'                       => 'Telefon',
            'Правила отмены'                => 'Politica de anulare',
            'Стоимость отмены бронирования' => 'Taxă de anulare',
            'Бронирование было отменено'    => 'Rezervarea a fost anulată',
        ],
        'uk' => [
            'Номер бронирования'          => ['Номер бронювання:'],
            'Название объекта размещения' => ['Назва помешкання:'],
            'Всего гостей'                => 'Усього гостей:',
            'Всего номеров'               => 'Усього номерів:',
            'Имя гостя'                   => 'Ім\'я гостя:',
            'Заезд'                       => 'Заїзд:',
            'Отъезд'                      => 'Виїзд:',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Ви забронювали для',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'доросл',
            'Цена'                          => 'Ціна', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com сплачує',
            'Итоговая цена'                 => 'Остаточна ціна', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Адреса',
            'Телефон'                       => 'Телефон',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Вартість скасування',
            'Бронирование было отменено'    => 'Бронювання було анульоване',
        ],
        'ar' => [
            'Номер бронирования'             => ['رقم الحجز:'],
            'Название объекта размещения'    => ['اسم مكان الإقامة:'],
            'Всего гостей'                   => 'العدد الإجمالي للضيوف:',
            'Всего номеров'                  => 'العدد الإجمالي للغرف:',
            'Имя гостя'                      => 'اسم الضيف:',
            'Заезд'                          => ['تسجيل الوصول:', 'تسجيل الوصول'],
            'Отъезд'                         => ['تسجيل المغادرة:', 'تسجيل المغادرة'],
            'У вас новое сообщение от гостя' => 'لديك رسالة جديدة من ضيفك',

            // Url
            'Забронировано для'             => 'حجزت لعدد ضيوف يبلغ',
            // 'Сведения о бронировании'       => '',
            //            'взрослый'                      => '',
            'Цена'                          => 'السعر', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'المبلغ الذي تدفعه Booking.com',
            'Итоговая цена'                 => 'السعر النهائي', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'العنوان',
            'Телефон'                       => 'رقم الهاتف',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'رسوم إلغاء الحجز',
            'Бронирование было отменено'    => 'تم إلغاء الحجز',
        ],
        'he' => [
            'Номер бронирования'             => ['מספר הזמנה:'],
            'Название объекта размещения'    => ['שם מקום האירוח:'],
            'Всего гостей'                   => 'מספר האורחים הכולל:',
            'Всего номеров'                  => 'סך כל החדרים:',
            'Имя гостя'                      => ['שם האורח/ת:', 'שם האורח:'],
            'Заезд'                          => 'צ׳ק-אין:',
            'Отъезд'                         => 'צ\'ק-אאוט:',
            'У вас новое сообщение от гостя' => 'קיבלתם הודעה חדשה מאורח',

            // Url
            'Забронировано для'             => 'הזמנתם ל-',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'מבוגרים',
            'Цена'                          => 'מחיר', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com משלמת',
            'Итоговая цена'                 => 'מחיר סופי', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'כתובת',
            'Телефон'                       => 'מספר טלפון',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'עלות הביטול',
            'Бронирование было отменено'    => 'ההזמנה בוטלה',
        ],
        'lt' => [
            'Номер бронирования'             => ['Užsakymo numeris:'],
            'Название объекта размещения'    => ['Apgyvendinimo įstaigos pavadinimas:'],
            'Всего гостей'                   => 'Svečių skaičius:',
            'Всего номеров'                  => 'Numerių skaičius:',
            'Имя гостя'                      => 'Svečio vardas ir pavardė:',
            'Заезд'                          => 'Įsiregistravimas:',
            'Отъезд'                         => 'Išsiregistravimas:',
            'У вас новое сообщение от гостя' => 'Gavote naują žinutę iš svečio',

            // Url
            'Забронировано для'             => 'Užsakėte nurodytam skaičiui svečių:',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'suaugusiesiem',
            'Цена'                          => 'Kaina', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com sumoka',
            'Итоговая цена'                 => 'Galutinė kaina', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresas',
            'Телефон'                       => 'Telefono numeris',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Atšaukimo kaina',
            'Бронирование было отменено'    => 'Užsakymas buvo atšauktas',
        ],
        'fi' => [
            'Номер бронирования'          => ['Varausnumero:'],
            'Название объекта размещения' => ['Majoituspaikan nimi:'],
            'Всего гостей'                => 'Asiakasmäärä yhteensä:',
            'Всего номеров'               => 'Huoneita yhteensä:',
            'Имя гостя'                   => ['Asiakkaan nimi:', 'Asiakkaan nimi'],
            'Заезд'                       => 'Sisäänkirjautuminen:',
            'Отъезд'                      => 'Uloskirjautuminen:',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Henkilömäärä',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'aikuiselle',
            'Цена'                          => 'Hinta', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            'Итоговая цена'                 => 'Lopullinen hinta', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Osoite',
            'Телефон'                       => 'Puhelin',
            'Правила отмены'                => 'Peruutusehdot',
            'Стоимость отмены бронирования' => 'Peruutusmaksu',
            'Бронирование было отменено'    => 'Varaus on peruutettu',
        ],
        'el' => [
            'Номер бронирования'             => ['Αριθμός κράτησης:'],
            'Название объекта размещения'    => ['Όνομα καταλύματος:'],
            'Всего гостей'                   => 'Σύνολο επισκεπτών:',
            'Всего номеров'                  => 'Σύνολο δωματίων:',
            'Имя гостя'                      => 'Όνομα πελάτη:',
            'Заезд'                          => 'Check-in:',
            'Отъезд'                         => 'Check-out:',
            'У вас новое сообщение от гостя' => 'Έχετε ένα νέο μήνυμα από επισκέπτη',

            // Url
            'Забронировано для'             => 'Κάνατε κράτηση για',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'ενήλικες',
            'Цена'                          => 'Τιμή', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Η Booking.com πληρώνει',
            'Итоговая цена'                 => 'Τελική τιμή', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Διεύθυνση',
            'Телефон'                       => 'Τηλέφωνο',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Ακυρωτικά',
            'Бронирование было отменено'    => 'Η κράτηση έχει ακυρωθεί',
        ],
        'sk' => [
            'Номер бронирования'             => ['Číslo rezervácie:'],
            'Название объекта размещения'    => ['Názov ubytovania:'],
            'Всего гостей'                   => 'Celkový počet hostí:',
            'Всего номеров'                  => 'Celkový počet izieb:',
            'Имя гостя'                      => 'Meno hosťa:',
            'Заезд'                          => ['Check-in:', 'Registrácia'],
            'Отъезд'                         => ['Check-out:', 'Odchod'],
            'У вас новое сообщение от гостя' => 'Dostali ste novú správu od hosťa',

            // Url
            'Забронировано для'             => 'Rezervácia pre',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'dospel',
            'Цена'                          => 'Cena', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com platí',
            'Итоговая цена'                 => 'Konečná cena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adresa',
            'Телефон'                       => 'Telefón',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Storno poplatky',
            'Бронирование было отменено'    => 'Rezervácia bola zrušená',
        ],
        'lv' => [
            'Номер бронирования'          => ['Rezervējuma numurs:'],
            'Название объекта размещения' => ['Naktsmītnes nosaukums:'],
            'Всего гостей'                => 'Kopējais viesu skaits:',
            'Всего номеров'               => 'Kopējais numuru skaits:',
            'Имя гостя'                   => 'Viesa vārds un uzvārds:',
            'Заезд'                       => 'Reģistrēšanās:',
            'Отъезд'                      => 'Izrakstīšanās:',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Viesi',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'pieaugušie',
            'Цена'                          => 'Cena', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            'Booking.com платит'            => 'Booking.com apmaksātā summa',
            'Итоговая цена'                 => 'Kopējā cena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Adrese',
            'Телефон'                       => 'Tālrunis',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Atcelšanas izmaksas',
            'Бронирование было отменено'    => 'Rezervējums ir atcelts',
        ],
        'th' => [
            'Номер бронирования'             => ['หมายเลขการจอง:'],
            'Название объекта размещения'    => ['ชื่อที่พัก:'],
            'Всего гостей'                   => 'จำนวนผู้เข้าพักทั้งหมด:',
            'Всего номеров'                  => 'จำนวนห้องทั้งหมดที่จอง:',
            'Имя гостя'                      => 'ชื่อผู้เข้าพัก:',
            'Заезд'                          => 'เช็คอิน',
            'Отъезд'                         => 'เช็คเอาท์',
            'У вас новое сообщение от гостя' => 'ท่านได้รับข้อความใหม่จากลูกค้า!',

            // Url
            'Забронировано для'             => 'จำนวนผู้เข้าพักสำหรับการจองนี้',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'ผู้ใหญ่',
            'Цена'                          => 'ราคา', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => '',
            //            'Итоговая цена'                 => '', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'ที่อยู่',
            'Телефон'                       => 'โทรศัพท์',
            'Правила отмены'                => 'นโยบายยกเลิกการจอง',
            'Стоимость отмены бронирования' => 'ค่าธรรมเนียมยกเลิก',
            //            'Бронирование было отменено' => '',
        ],
        'bg' => [
            'Номер бронирования'          => ['Номер на резервацията:'],
            'Название объекта размещения' => ['Име на обекта:'],
            'Всего гостей'                => 'Total guests:',
            'Всего номеров'               => 'Total rooms:',
            'Имя гостя'                   => 'Guest name:',
            'Заезд'                       => 'Настаняване',
            'Отъезд'                      => 'Напускане',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            'Забронировано для'             => 'Вие резервирахте за',
            // 'Сведения о бронировании'       => '',
            'взрослый'                      => 'възрастни',
            'Цена'                          => 'Цена', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => 'Booking.com apmaksātā summa',
            //            'Итоговая цена'                 => 'Kopējā cena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            'Адрес'                         => 'Адрес',
            'Телефон'                       => 'Телефон',
            //            'Правила отмены'                => '',
            'Стоимость отмены бронирования' => 'Разноски по анулиране',
            //            'Бронирование было отменено' => '',
        ],
        'bs' => [
            'Номер бронирования'          => ['Broj rezervacije:'],
            'Название объекта размещения' => ['Ime objekta:'],
            'Всего гостей'                => 'Ukupan broj gostiju:',
            'Всего номеров'               => 'Ukupno jedinica:',
            'Имя гостя'                   => 'Ime gosta:',
            'Заезд'                       => 'Prijavljivanje',
            'Отъезд'                      => 'Odjavljivanje',
            // 'У вас новое сообщение от гостя' => '',

            // Url
            //            'Забронировано для'             => 'Вие резервирахте за',
            // 'Сведения о бронировании'       => '',
            //            'взрослый'                      => 'възрастни',
            //            'Цена'                          => 'Цена', // "Цена (за 2 гостей)", цена без учета 'Booking.com платит', расположена сразу после цены за комнату и основных налогов
            //            'Booking.com платит'            => 'Booking.com apmaksātā summa',
            //            'Итоговая цена'                 => 'Kopējā cena', // "Итоговая цена (включая налоги)", цена c учетом 'Booking.com платит', расположена после все цен и доп сборов
            //            'Адрес'                         => 'Адрес',
            //            'Телефон'                       => 'Телефон',
            //            'Правила отмены'                => '',
            //            'Стоимость отмены бронирования' => 'Разноски по анулиране',
            //            'Бронирование было отменено' => '',
        ],
    ];

    /**
     * @return array|Email
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("can't determine a language!");

            return $email;
        }

        $providerCode = '';

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['from']) && $this->striposAll($parser->getCleanFrom(), $params['from']) !== false) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['providerText']) && $this->http->XPath->query('//node()[' . $this->contains($params['providerText']) . ']')->length > 0) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['providerLink']) && $this->http->XPath->query('//a[' . $this->contains($params['providerLink']) . ']')->length > 0) {
                $providerCode = $code;

                break;
            }
        }

        $email->setProviderCode($providerCode);

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$detectProvider as $params) {
            if (empty($params['providerText']) || empty($params['providerLink'])) {
                continue;
            }

            if ($this->http->XPath->query('//a[' . $this->contains($params['providerLink']) . ']')->length === 0
                && $this->http->XPath->query('//*[' . $this->contains($params['providerText']) . ']')->length === 0
            ) {
                continue;
            }

            if ($this->assignLang() === true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $params) {
            if (empty($params['from']) || empty($params['subject'])) {
                continue;
            }

            if ($this->striposAll($headers['from'], $params['from']) === false) {
                continue;
            }

            foreach ($params['subject'] as $pSubject) {
                if (stripos($headers['subject'], $pSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]booking\.com/i', $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function parseEmail(Email $email): void
    {
        if (!empty($this->http->FindSingleNode("(//node()[{$this->contains($this->t('У вас новое сообщение от гостя'))}])[1]"))) {
            $email->setSentToVendor(true);
        }

        $patterns = [
            'time' => '\d{1,2}(?:[.:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?|\s*noon|\s*午[前後])?', // 4:19PM    |    8.00    |    2:00 p.m.    |    3pm    |    12 noon    |    3:10 午後
        ];

        $begin = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Имя гостя")) . "])[last()]/preceding::text()[normalize-space()][1]");

        if (!empty($begin)) {
            // при этом условии текст будет искаться только в блоке о бронировании, что исключит случаи, когда условиям может соотвествовать текст в сообщении отеля (используется в $this->nextText )
            $this->prefix = "text()[normalize-space() = '" . $begin . "']/following::";
        }

        $h = $email->add()->hotel();

        if (!$confNo = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Номер бронирования'))}])[1]",
            null, false, "#{$this->opt($this->t('Номер бронирования'))}[:\s]*(\d{5,})#u")
        ) {
            $confNo = $this->nextText($this->t('Номер бронирования'));
        }
        $h->general()
            ->confirmation($confNo)
            ->travellers(preg_split("/\s*,\s*/", $this->nextText($this->t('Имя гостя'))));

        $h->booked()
            ->checkIn($this->normalizeDate($this->nextText($this->t('Заезд'))))
            ->checkOut($this->normalizeDate($this->nextText($this->t('Отъезд'))))
            ->guests($this->nextText($this->t('Всего гостей')), true, true)
            ->rooms($this->nextText($this->t('Всего номеров')), true, true);

        $hotelName = $this->nextText($this->t('Название объекта размещения'));

        $h->hotel()
            ->name($hotelName);

        if (!empty($hotelName)) {
            if (stripos($hotelName, '"') !== false) {
                $hotelName = strpos($hotelName, '"') === false ? '"' . $hotelName . '"' : 'concat("' . str_replace('"', '",\'"\',"', $hotelName) . '")';
            }
            $url = $this->http->FindSingleNode('//a[normalize-space()= "' . $hotelName . '"]/@href');
//            $this->logger->debug('$url = '.print_r( $url,true));

            if (stripos($url, '.booking.com') === false) {
                $h->hotel()->noAddress();

                return;
            }

            $http2 = clone $this->http;
            $http2->GetURL($url);
            // $this->logger->debug('$data = '.print_r( $http2->Response['body'],true));

            $notHidden = "not(ancestor-or-self::*[contains(@aria-hidden, 'true')])";
            $address = implode(", ", $http2->FindNodes("(//text()[" . $this->eq($this->t("Адрес")) . "])[1]/ancestor::*[" . $this->eq($this->t("Адрес")) . "][{$notHidden}]/following-sibling::*[normalize-space()][1]//tr[not(.//tr)]/td[1]"));

            if (empty($address)) {
                $address = implode(", ", $http2->FindNodes("(//text()[" . $this->eq($this->t("Адрес")) . "])[1]/ancestor::*[" . $this->eq($this->t("Адрес")) . "][{$notHidden}]/following-sibling::*[normalize-space()][1]//text()[normalize-space()]"));
            }

            if (!empty($address)) {
                $address = preg_replace("/\s*,\s*" . $this->opt($this->t("Телефон")) . ":.*/u", '', $address);
                $h->hotel()
                    ->address($address)
                ;
            }
            $phone = implode(", ", array_filter($http2->FindNodes("//text()[" . $this->eq($this->t("Телефон")) . "]/ancestor::*[" . $this->eq($this->t("Телефон")) . "]/following-sibling::*[normalize-space()][1]//tr[not(.//tr)]/td[1]")));

            if (empty($phone)) {
                $phone = implode(", ", array_filter($http2->FindNodes("//text()[" . $this->eq($this->t("Телефон")) . "]/ancestor::*[" . $this->eq($this->t("Телефон")) . "]/following-sibling::*[normalize-space()][1]//text()[normalize-space()]")));
            }

            if (!empty($phone) && preg_match('/^([\d\+\-\(\) \.]{5,})$/', $phone)) {
                $h->hotel()
                    ->phone($phone, true, true);
            }

            $roomType = $http2->FindSingleNode("//text()[" . $this->eq($this->t('Забронировано для')) . "]/preceding::text()[normalize-space()][2]/ancestor::h3");
            $roomDesc = $http2->FindSingleNode("//text()[" . $this->eq($this->t('Забронировано для')) . "]/preceding::text()[normalize-space()][1]");

            if (!empty($roomType)) {
                $h->addRoom()
                    ->setType($roomType)
                    ->setDescription($roomDesc);
            } else {
                $types = preg_replace("/^\s*[[:alpha:]] \d+:\s*/", '', $http2->FindNodes("//*[contains(@class, 'room-info-card__content-title')]"));

                foreach ($types as $type) {
                    $h->addRoom()
                        ->setType($type);
                }
            }

            $guests = $http2->FindSingleNode("//*[self::td or self::th][" . $this->eq($this->t('Забронировано для')) . "]/following-sibling::*[normalize-space()][1]");

            if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('взрослый'))}\D{0,10}(?:\s*\W\s*(\d+)\s*[[:alpha:]]+)\s*$/u", $guests, $m)) {
                $h->booked()
                    ->guests($m[1])
                    ->kids($m[2] ?? null, true, true);
            } else {
                $guests = $http2->FindSingleNode("(//*[" . $this->eq($this->t('Сведения о бронировании')) . "][{$notHidden}])[1]/following::text()[normalize-space()][1]",
                    null, true, "/^(.+?) - /");

                if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('взрослый'))}[[:alpha:]]*(?:\s*\W\s*(\d+)\s*[[:alpha:]]+\D{1,5}?)?\s*(\(|$)/u", $guests, $m)) {
                    $h->booked()
                        ->guests($m[1])
                        ->kids($m[2] ?? null, true, true);
                }
            }

            $checkIn = $http2->FindSingleNode("//*[self::td or self::th][" . $this->eq($this->t('Заезд')) . "]/following-sibling::*[normalize-space()][1]");

            if (empty($checkIn)) {
                $checkIn = $http2->FindSingleNode("//*[descendant::text()[normalize-space()][1][" . $this->eq($this->t('Заезд')) . "]][following-sibling::*/descendant::text()[normalize-space()][1][" . $this->eq($this->t('Отъезд')) . "]][{$notHidden}]",
                    null, true, "/^\s*{$this->opt($this->t('Заезд'))}\s*(.+)/");
            }
            $checkIn = $this->normalizeDateUrl($checkIn, true);

            if (!empty($checkIn)) {
                $h->booked()
                    ->checkIn($checkIn);
            }

            $checkOut = $this->normalizeDateUrl($http2->FindSingleNode("//*[self::td or self::th][" . $this->eq($this->t('Отъезд')) . "]/following-sibling::*[normalize-space()][1]"), false);

            if (empty($checkOut)) {
                $checkOut = $http2->FindSingleNode("//*[descendant::text()[normalize-space()][1][" . $this->eq($this->t('Заезд')) . "]]/following-sibling::*[descendant::text()[normalize-space()][1][" . $this->eq($this->t('Отъезд')) . "]][{$notHidden}]",
                    null, true, "/^\s*{$this->opt($this->t('Отъезд'))}\s*(.+)/");
            }
            $checkOut = $this->normalizeDateUrl($checkOut, false);

            if (!empty($checkOut)) {
                $h->booked()
                    ->checkOut($checkOut);
            }

            if ($http2->XPath->query("//text()[{$this->contains($this->t('Бронирование было отменено'))}][not(ancestor::*[self::script or self::style])]")->length) {
                $h->general()
                    ->cancelled()
                    ->status('Cancelled');
            }

            $cancellation = $http2->FindSingleNode("//*[self::td or self::th][" . $this->eq($this->t('Правила отмены')) . "]/following-sibling::*[normalize-space()][1]");
            $cancellationCosts = $http2->FindNodes("//*[self::td or self::th][" . $this->eq($this->t('Стоимость отмены бронирования')) . "]/following-sibling::*[normalize-space()][1]//li");

            if (!empty($cancellationCosts[0]) && preg_match("/(.+)\[[A-Z]+\]\s*:\s*(0\D*|\D*0)$/u", $cancellationCosts[0], $m)) {
                $h->booked()
                    ->deadline($this->normalizeDateDeadline(trim($m[1], ', ')));
            }

            $allCanc = trim(preg_replace("/(\S)\W*$/u", '$1.', $cancellation)
                . ' ' . implode('; ', $cancellationCosts));

            if (!empty($allCanc)) {
                $h->general()
                    ->cancellation($allCanc);
            }

            // Price
            $totalText = "\n" . implode("\n", $http2->FindNodes("(//*[self::td or self::th][descendant::text()[normalize-space()][1][" . $this->eq($this->t('Итоговая цена')) . "]]/following-sibling::*[normalize-space()][1])[1]/div[2]//text()[normalize-space()]")) . "\n";

            if (preg_match("/\n\s*(?<amount>\d[\d\., ]*)\s*\n/u", $totalText, $ma)
                && preg_match("/\n\s*(?<currency>[A-Z]{3})\s*\n/u", $totalText, $mc)
            ) {
                $currency = $this->currency($mc['currency']);
                $h->price()
                    ->total($this->amount($ma['amount'], $currency))
                    ->currency($currency);
            }

            if (empty(trim($totalText))) {
                $discountText = $http2->FindSingleNode("(//*[self::td or self::th][descendant::text()[normalize-space()][1][" . $this->eq($this->t('Booking.com платит')) . "]]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][1])[1]");

                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $discountText, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $discountText, $m)) {
                    $discountCurrency = $this->currency($m['currency']);
                    $discount = $this->amount($m['amount'], $discountCurrency);
                }
                $totalText = $http2->FindSingleNode("(//*[self::td or self::th][descendant::text()[normalize-space()][1][" . $this->eq($this->t('Цена')) . "]]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][last()])[1]");

                if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $totalText, $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $totalText, $m)
                ) {
                    $currency = $this->currency($m['currency']);
                    $total = $this->amount($m['amount'], $currency);

                    if (is_numeric($discount) && $discountCurrency === $currency) {
                        $h->price()
                            ->total($total + $discount)
                            ->currency($currency);
                    } else {
                        $h->price()
                            ->total($total)
                            ->currency($currency);
                    }
                }
            }
        }

        if (empty($h->getAddress())) {
            $h->hotel()
                ->noAddress();
        }
    }

    private function nextText($field, $num = 1, $without = 'blabla'): ?string
    {
        return $this->http->FindSingleNode("(//" . $this->prefix . "text()[({$this->starts($field)}) and not({$this->contains($without)})]/following::text()[normalize-space(.)!=''][1])[{$num}]");
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = ' . print_r($date, true));
        $in = [
            //Fr., 7. Sept. 2018.; L., 18. mai 2019; четверг, 20 октября 2022 г.
            '#^\s*[^\d\W]{1,}[.\s]*,?\s+(\d{1,2})[.\s]+([^\d\W]{3,})\.?\s+(\d{4})\s*[.г]*\s*$#u',
            //tor. d. 23. aug. 2018
            '#^\s*[\w. ]+?\s+(\d+)[.]?\s+(\w+)\.?\s+(\d{4})\s*$#u',
            //2018年11月8日(木)
            '#^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*\(\s*\w+\s*\)\s*$#u',
            //seg, 7 de jan. de 2019
            '#^\s*[^\d\W]{2,}[.\s]*,?\s+(\d{1,2})\s+de[.\s]+([^\d\W]{3,})\.?\s+de\s+(\d{4})[.\s]*$#',
            //2019年3月7日星期四; 2018년 9월 22일 (토)
            '#^\s*(\d{4})\s*(?:年|년)\s*(\d{1,2})\s*(?:月|월)\s*(\d{1,2})\s*(?:日|일)\s*[\(\（]?\s*\w+\s*[\)）]?\s*$#u',

            //dim, 27 de ag. de 2019
            '#^\D+\s+(\d+)\s+de\s+(\w+)\.?\s+del?\s+(\d{4})\s*$#u',
            //7 Nis 2019, Paz
            '#^(\d+)\s+(\w+)\s+(\d{4}),\s*\w+$#u',
            // 2019. okt. 26. (Szo)
            '#^(\d{4})\.\s+(\w+)\.\s+(\d+)\..+?$#u', // hu
            //    السبت 11-يناير-2020
            '#^\s*[-[:alpha:]]+[، ]+(\d{1,2})[- ]+([[:alpha:]]+)[- ]+(\d{4})\s*$#u', // ar
            // יום ג', 22 ביוני 2021
            '#^\s*\D*[.\s,]+\s+(\d{1,2})[.\s]+([\w]{3,})\.?\s+(\d{4})[.\s]*$#u', // he

            // 2021 m. lie 20 d., an
            '#^\s*(\d{4})\s*m\.\s+(\w+)\s+(\d+)\s*d\.[, ]*\D*$#u', // lt
            //ca: dt., 17 d'ag. de 2021
            '#^\D+\s+(\d+)\s+d[\'’](\w+)\.?\s+del?\s+(\d{4})\s*$#u',
            // lv: piektd., 2021. gada 29. okt.
            '#^\s*\w+[.,\s]+(\d{4})\.\s*gada\s*(\d+)\.\s*(\w+)\.\s*$#u',
            // อาทิตย์ 2 ต.ค. 2022
            '#^\s*[[:alpha:]\p{Thai}]+\s+(\d{1,2})\s+(\w\.\w\.)\s+(\d{4})\s*$#u',
            //วันอาทิตย์ที่ 2 ตุลาคม ค.ศ. 2022
            '#^\s*[[:alpha:]\p{Thai}]+\s+(\d{1,2})\s+([[:alpha:]\p{Thai}]+)?\s+\w\.\w\.\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3',
            '$3-$2-$1',
            '$1 $2 $3',
            '$3-$2-$1',

            '$1 $2 $3',
            '$1 $2 $3',
            '$3 $2 $1',
            '$1 $2 $3',
            '$1 $2 $3',

            '$3 $2 $1',
            '$1 $2 $3',
            '$2 $3 $1',
            '$1 $2 $3',
            '$1 $2 $3',
        ];

        foreach ($in as $i => $re) {
            if (preg_match($re, $date)) {
                $date = preg_replace($re, $out[$i], $date);

                break;
            }
        }
        // $this->logger->debug('$date 2 = ' . print_r($date, true));

        return strtotime($this->dateStringToEnglish($date));
    }

    private function normalizeDateDeadline($date)
    {
        // $this->logger->debug('normalizeDateDeadline = ' . print_r($date, true));
        $in = [
            // до 29 мая 2022 г. 23:59
            // jusqu'au 29 mai 2022 23:59
            // até às 17 de junho de 2022 23:59
            // до 30 май 2023 г. 18:00 ч.
            // Fins al 9 d’agost de 2022 23:59
            '/^\s*[[:alpha:]\']+(?: [[:alpha:]\']+){0,3}\s+(\d{1,2})[.]?(?:\s+de)?\s+(?:d’)?([[:alpha:]]{3,})(?:\s+de)?\s+(\d{4})(?:\s+г\.)?\s+(\d{1,2}):(\d{2}(?:\s*[AP]M)?)(?:\s*ч\.)?\s*$/ui',
            // until May 29, 2022 11:59 PM
            '/^\s*[[:alpha:]]+(?: [[:alpha:]]+){0,3}\s+([[:alpha:]]{3,})\s+(\d{1,2})[,]?\s+(\d{4})\s+(\d{1,2}):(\d{2}(?:\s*[AP]M)?)\s*$/ui',
            // lv: līdz 2022. gada 18. jūnijs 23:59
            '/^\s*\w+[.,\s]+(\d{4})\.\s*gada\s*(\d+)\.\s*(\w+)\s+(\d{1,2}:\d{2}(?:\s*[AP]M)?)\s*$/u',
            // 2022年12月18日 23:59 まで
            "/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*(\d{1,2}:\d{2})\s*\D*$/u",
            // ถึง 30 กันยายน ค.ศ. 2022 23:59
            '#^\s*[[:alpha:]\p{Thai}]+\s+(\d{1,2})\s+([[:alpha:]\p{Thai}]+)?\s+\w\.\w\.\s+(\d{4})\s+(\d{1,2}:\d{2})\s*$#u',
        ];
        $out = [
            '$1 $2 $3, $4:$5',
            '$2 $1 $3, $4:$5',
            '$2 $3 $1, $4',
            '$1-$2-$3, $4',
            '$1 $2 $3, $4',
        ];

        foreach ($in as $i => $re) {
            if (preg_match($re, $date)) {
                $date = preg_replace($re, $out[$i], $date);

                break;
            }
        }
        // $this->logger->debug('normalizeDateDeadline 2 = ' . print_r($date, true));

        return strtotime($this->dateStringToEnglish($date));
    }

    private function normalizeDateUrl($dateStr, $isCheckIn)
    {
        if (preg_match("/(.+?)\s*\(\s*([^)(]+?)\s*\).*$/", $dateStr, $m)
            // Thursday, June 2, 2022 (until 11:00 AM) Outlook/iCal Google calendar
            || preg_match("/(.+?) –\s*Outlook\\/iCal/u", $dateStr, $m)
            // Wed 21 Aug 2024from 14:00
            || preg_match("/^(.+?)\D*(\d{1,2}:\d{2}.*)/u", $dateStr, $m)
        ) {
            $date = $m[1];
            $time = $m[2] ?? null;

            if (preg_match("#^\s*\D*\b(\d+\D{0,2}\d+\D*)\s*(?:至|～|\s+-\s+)\s*(\d+\D{0,2}\d+\D*)\s*\D*$#", $time, $mat)) {
                //（14:00至23:30）;（14:00～00:00）; (14:00 - 14:00); (17h00 - 20h00)
                if ($isCheckIn) {
                    $time = $mat[1];
                } else {
                    $time = $mat[2];
                }
            }
            $time = $this->normalizeTime($time);
        } else {
            $date = $dateStr;
        }

        $date = $this->normalizeDate($date);

        if (!empty($time)) {
            $date = strtotime($time, $date);
        }

        return $date;
    }

    private function normalizeTime($str): string
    {
        $in = [
            // 10h20
            "#^\D*(\d{1,2})h(\d{2})\D*$#",
            // from 3:00 PM
            "#^\D*\b(\d{1,2}:\d{2}(?:\s*[AP]M)?)\b\D*$#",
        ];
        $out = [
            "$1:$2",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (
            preg_match('#\d+ ([[:alpha:]]+) \d{4}#iu', $date, $m)
            || ($this->lang == 'th' && preg_match('#\d+ ([[:alpha:]\p{Thai}\.]+) \d{4}#iu', $date, $m))
        ) {
            $monthNameOriginal = $m[1];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function amount($amount, $currency)
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        $amount = PriceHelper::parse($amount, $currency);

        if (is_numeric($amount)) {
            return $amount;
        }

        return null;
    }

    private function currency($s)
    {
        $s = trim($s);
        $sym = [
            '€'     => 'EUR',
            'US$'   => 'USD',
            '₩'     => 'WON',
            'lei'   => 'RON',
            'S$'    => 'SGD',
            'HK$'   => 'HKD',
            '£'     => 'GBP',
            'Rp '   => 'IDR',
            'zł'    => 'PLN',
            '¥'     => '¥',
            '￥'     => '¥',
            'руб'   => 'RUB',
            'R$'    => 'BRL',
            '₹'     => 'INR',
            '元'     => 'CNY',
            '$'     => '$',
            'Rs.'   => 'INR',
            '₪'     => 'ILS',
            'Rp'    => 'IDR',
            'CL$'   => 'CLP',
            '₱'     => 'PHP',
        ];

        if (preg_match("/^([A-Z]{3})$/", $s)) {
            return $s;
        }

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
