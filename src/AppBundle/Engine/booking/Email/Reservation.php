<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "booking/it-1.eml, booking/it-10.eml, booking/it-11.eml, booking/it-14.eml, booking/it-15.eml, booking/it-1563551.eml, booking/it-1564990.eml, booking/it-1570707.eml, booking/it-1570818.eml, booking/it-1585291.eml, booking/it-1586654.eml, booking/it-1587486.eml, booking/it-1588634.eml, booking/it-1589806.eml, booking/it-16.eml, booking/it-1636251.eml, booking/it-1636254.eml, booking/it-1636267.eml, booking/it-1636270.eml, booking/it-1648064.eml, booking/it-1652052.eml, booking/it-1652058.eml, booking/it-1659190.eml, booking/it-1660155.eml, booking/it-1663676.eml, booking/it-1663747.eml, booking/it-1664045.eml, booking/it-1666593.eml, booking/it-1666781.eml, booking/it-1673001.eml, booking/it-1675867.eml, booking/it-1675868.eml, booking/it-17.eml, booking/it-1703536.eml, booking/it-1759583.eml, booking/it-1760130.eml, booking/it-1763524.eml, booking/it-1764646.eml, booking/it-1764649.eml, booking/it-1771647.eml, booking/it-1772459.eml, booking/it-1772460.eml, booking/it-18.eml, booking/it-1813491.eml, booking/it-1813493.eml, booking/it-1813494.eml, booking/it-1813496.eml, booking/it-1813497.eml, booking/it-1813499.eml, booking/it-1813507.eml, booking/it-1827753.eml, booking/it-1827754.eml, booking/it-1828183.eml, booking/it-1876495.eml, booking/it-1879420.eml, booking/it-1881103.eml, booking/it-1897290.eml, booking/it-1898780.eml, booking/it-1898869.eml, booking/it-1898870.eml, booking/it-19.eml, booking/it-1901581.eml, booking/it-1903635.eml, booking/it-1903642.eml, booking/it-1903800.eml, booking/it-1908736.eml, booking/it-1908787.eml, booking/it-1918492.eml, booking/it-1939633.eml, booking/it-1939645.eml, booking/it-1965867.eml, booking/it-1966636.eml, booking/it-1971709.eml, booking/it-1983153.eml, booking/it-1998217.eml, booking/it-21.eml, booking/it-2113193.eml, booking/it-2148591.eml, booking/it-2148595.eml, booking/it-2183741.eml, booking/it-22.eml, booking/it-2306033.eml, booking/it-2306510.eml, booking/it-2314864.eml, booking/it-2412566.eml, booking/it-2545955.eml, booking/it-2545959.eml, booking/it-2550691.eml, booking/it-2848588.eml, booking/it-3.eml, booking/it-3329988.eml, booking/it-4.eml, booking/it-5.eml, booking/it-6.eml, booking/it-7.eml, booking/it-8.eml, booking/it-9.eml";

    private $reFrom = '@booking.com';
    private $reSubject = [
        'en' => [
            "Your booking at",
            "your booking at",
        ],
        'ru' => [
            "Ваше бронирование в",
            "ваше бронирование в ",
            "Бронирование в ",
        ],
        'he' => [
            "ההזמנה ב",
        ],
        'da' => [
            "Din reservation på",
        ],
        'de' => [
            "Ihre Reservierung im",
        ],
        'nl' => [
            "Uw reservering in",
        ],
        'fi' => [
            "Varauksesi hotellissa",
        ],
        'fr' => [
            "Votre réservation à l' établissement",
        ],
    ];

    private $reBody = 'booking.com';
    private $reBody2 = [
        'en' => [
            "Your reservation is now",
            "Your modified booking is now confirmed",
            "Please print this confirmation and show it upon check in",
            "Your reservation at",
            "Thank you for your reservation made through",
            "Please note that your reservation has been",
            "We hereby confirm your modified booking",
            "Please be informed that your reservation has been",
            "your booking is ",
            "Please note: additional supplements",
            "Your reservation has just been confirmed",
            "Hotel Cancellation Confirmation",
            "Your booking is now confirmed",
            "Your reservation has just been",
            "The hotel has notified us that you did not arrive",
            "We hope to be your first choice for accommodation reservations",
            "We hope to be your online booking choice in the future",
            "Your cancellation is now confirmed",
            "reservation has just been confirmed",
        ],
        'ru' => [
            "Ваше бронирование подтверждено.",
            "Ваше бронирование успешно изменено",
            "Это ваше измененное бронирование.",
            "Пожалуйста, распечатайте данное подтверждение",
            "Платеж прошел успешно",
        ],
        'zh' => [
            "您在",
            "您的预订现已确认",
        ],
        'he' => [
            "אתם תמיד יכולים לראות או לשנות את הזמנתכם אונליין - ללא צורך בהרשמה",
        ],
        'da' => [
            'din reservation er blevet annulleret',
            'din reservation er nu blevet bekræftet',
            'Vi bekræfter hermed følgende reservation',
            'Din reservation er nu bekræftet',
        ],
        'de' => [
            'Hiermit bestätigen wir die folgende Buchung',
            'Buchung über booking.com',
            'ist jetzt storniert',
            'Ihre Buchung ist nun bestätigt',
        ],
        'nl' => [
            'Uw reservering is nu bevestigd',
            'Bedankt voor uw reservering',
        ],
        'fi' => [
            'Tulosta tämä varausvahvistus',
        ],
        'fr' => [
            'Votre réservation est maintenant confirmée',
        ],
    ];

    private $lang = '';
    private $emailSubject = '';

    private static $dict = [
        'en' => [
            "(?<name>Hotel)SubjectRe" => [
                "(?:modified\s+|)booking\s+at\s+(?<name>\S.*?)(?:\s+has been updated|,|$)",
                "Booking\s+cancelled\s+for\s+(?<name>\S.+)",
                "Booking\s+confirmed\s+at\s+(?<name>\S.+)\s+\(adults only\)",
            ],
            "Room"                               => ["Room", "room"],
            "room"                               => ['Room', 'room', 'Apartment'],
            "Your cancellation is now confirmed" => [
                "Your cancellation is now confirmed",
                "reservation has been canceled",
                "your reservation has been cancelled",
            ],
            //            "Cancellation policy:" => "",
            "Cancellation Fees in local hotel time:" => [
                "Cancellation Fees in local hotel time:",
                "Cancellation Cost in local hotel time:",
            ],
            //            "Cancellation" => '',

            // Type 1 Формат с рядомстоящими таблицами
            //            "Thanks," => "",
            'Booking number:'   => ["Booking number:", "Booking number", "Booking Number", "Reservation number", "Reservation number:"],
            "Booked by:"        => ["Booked by:", "Booked by"],
            "Your reservation:" => ["Your reservation:", "Your reservation"],
            "Check-in:"         => ["Check-in:", "Check in:", "Check-in"],
            "Check-out:"        => ["Check-out:", "Check out:", "Check-out"],
            // tax
            "Total Price" => ["Total Price", "Total price"],

            //            "Hotel info" => "", //hotel name link @title
            "Address:" => ["Address:", "Address :"],
            //            "Phone:" => "",
            //            "Fax:" => "",

            //            "Room Details" => "",
            "Guest name:" => ["Guest name:", "Guest Name:"],

            // Type 2
            //            "Dear " => "",
            //            "Property name" => "",
            "Booking number" => ["Booking number", "booking.com booking number", "Booking.com reservation number", "Reservation number", "booking.com booking number"],
            //            "Address" => "",
            //            "Phone" => "",
            //            "Fax" => "",

            "Check-in"  => ["Check-in", "Arrival", "Check in"],
            "Check-out" => ["Check-out", "Departure", "Check out"],
            "Quantity"  => ["Quantity", "Your reservation"],

            //            "Guest Name" => "",
            "Total Room Price" => ["Total Room Price", "Total price"],
        ],
        'ru' => [
            '(?<name>Hotel)SubjectRe' => [
                "#Ваше\s+бронирование\s+в\s+(?<name>\S.+)$#i",
                "#Повторное подтверждение бронирования \((?<name>\S.+)\)#i",
            ],
            "Room"                                   => ['Номер', 'Апартаменты'],
            'room'                                   => ['номер'],
            "Cancellation policy:"                   => ["Порядок отмены бронирования", "Порядок отмены бронирования:"],
            "Cancellation Fees in local hotel time:" => [
                "Стоимость отмены бронирования:",
            ],
            //            "Your cancellation is now confirmed" => [],
            //            "Cancellation" => '',
            //
            // Type 1 Формат с рядомстоящими таблицами
            "Thanks,"         => "Спасибо,",
            'Booking number:' => "Номер бронирования:",
            //            "Booked by:" => "",
            "Your reservation:" => "Ваше бронирование:",
            "Check-in:"         => ["Регистрация заезда:", "Регистрация заезда"],
            "Check-out:"        => ["Регистрация отъезда:", "Регистрация отъезда"],
            "Total Price"       => ["Общая стоимость", "Общая Стоимость", "Цена"],

            "Hotel info" => "Информация об отеле", //hotel name link @title
            "Address:"   => ["Адрес:", "Адрес :"],
            "Phone:"     => ["Телефон:", "Телефон"],
            "Fax:"       => ["Факс:", "Факс"],

            "Room Details" => ['информация о номере', 'Информация о номере', 'Информация О Номере'],
            "Guest name:"  => ["Имя гостя:"],

            // Type 2
            "Dear "          => "Уважаемый/ая ",
            "Property name"  => "Название объекта размещения",
            "Booking number" => "Номер бронирования",
            "Address"        => "Адрес",
            "Phone"          => "Телефон",
            "Fax"            => "Факс",

            "Check-in"  => "Заезд",
            "Check-out" => "Отъезд",
            "Quantity"  => "Ваше бронирование",

            "Guest Name" => "Имя гостя",
            //            "Total Room Price" => "",
        ],
        'zh' => [
            /*
            'HotelSubjReg' => [
                "#您在\s*(.+)\s*的訂房已確認#",
            ],
            'Hotel info' => '飯店信息',
            'Show directions' => '如何抵達',
//			'confno_keys' => ["Номер бронирования:", "Номер бронирования"],
            'checkin' => ["入住時間:", "入住時間"],
            'checkout' => ["退房時間:", "退房時間"],
//			'address' => ["Адрес:", "Адрес", "Адрес :"],
            'phone' => ["電話:", "電話"],
//			'fax' => ["Факс:", "Факс"],
//			'business' => 'предложение',
            'nameReg' => "#(.+)\s*[,，]\s*(?:謝謝|谢谢)\s*[!！]#",
            'names' => ["住客姓名:", "住客姓名"],
            "Number of guests" => "客人人數",
            'adultReg' => '(?:人入住)',
//			'kidReg' => '(?:ребёнка)',
            'Your reservation' => ["您的預訂:", "您的預訂"],
            'roomReg' => '(?:間客房)',
//			'cancellation' => ["Порядок отмены бронирования", "Порядок отмены бронирования:"],
            'cancellationCost' => "取消費",
//			'room' => ['Номер ', 'Апартаменты '],
            'totalCost' => ["總價"],
            'notTotalCost' => '的增值稅',
//			'for guest' => 'для гостя',
            'Please' => 'Пожалуйста',
            'details' => ['客房細節'],
//			'number' => ' номер',
            'VAT' => ['取消費', '的增值稅'],
            'statusReg' => [
                "#.+[,，]谢谢[!！]您的预订([^\s\.。]+)#u",
                "#您在.+的訂房(.+)#u",
            ],
            */
        ],
        'he' => [
            /*
            'HotelSubjReg' => [
                "#ההזמנה ב-(.+) מאושרת#i",
            ],
            'Hotel info' => 'פרטי המלון',
            'Show directions' => 'הצג הוראות נסיעה',
            'confno_keys' => ["מספר אישור הזמנה"],
            'checkin' => ["צ'ק-אין", "צ'ק-אין:"],
            'checkout' => ["צ'ק-אאוט", "צ'ק-אאוט:"],
            //'address' => ["Адрес:", "Адрес", "Адрес :"],
            'phone' => ["טלפון:", "טלפון"],
            //'fax' => ["Факс:", "Факс"],
            //'business' => 'предложение',
            'nameReg' => "#(?:Спасибо,|Dear)\s+(.*?)(?:,|!)#",
            'names' => ["שם האורח:", "שם האורח"],
            "Number of guests" => ["מספר האורחים", "הקבוצה שלכם"],
            'adultReg' => '(?:אנשים|מבוגרים)',
            'kidReg' => '(?:ילד עד גיל|ילד עד גיל)',
            'Your reservation' => ["ההזמנה שלכם:", "ההזמנה שלכם"],
            'roomReg' => '(?:חדר)',
            'cancellation' => ["מדיניות הביטול", "מדיניות הביטול:"],
            //'cancellationCost' => "Стоимость отмены бронирования",
            'room' => ['החדר'],
            'totalCost' => ["מחיר כולל", "מחיר"],
            'notTotalCost' => "מע''מ בשיעור של",
            //'for guest' => 'для гостя',
            //'Please' => 'Пожалуйста',
            //'details' => ['информация о номере', 'Информация о номере', 'Информация О Номере'],
            'number' => 'חדר ',
            'Meal plan' => 'תוכנית הארוחות',
            'VAT' => '% מהמחיר כלול/ה במחיר',
            'statusReg' => [
                "#\s*הזמנתכם ב.+?\s+(\w+)\s*\.#u",
            ],
            */
        ],
        'da' => [
            '(?<name>Hotel)SubjectRe' => [
                "Din reservation på (?<name>\S.*?)",
            ],
            "Room"                                   => ["Værelse", "Lejlighed"],
            'room'                                   => ['værelser', 'værelse'],
            "Cancellation policy:"                   => "Afbestillingsregler:",
            "Cancellation Fees in local hotel time:" => [
                "Afbestillingsgebyr lokal tid:",
            ],
            "Your cancellation is now confirmed" => [
                //                "Tak for din reservation via booking.com",
                "din reservation er blevet annulleret",
            ],
            "Cancellation" => "Afbestilling",

            // Type 1 Формат с рядомстоящими таблицами
            "Thanks,"           => "Mange tak,",
            'Booking number:'   => ["Reservationsnummer", "Reservationsnummer:"],
            "Booked by:"        => ["Reserveret af"],
            "Your reservation:" => ["Din reservation:"],
            "Check-in:"         => ["Indtjekning:"],
            "Check-out:"        => ["Udtjekning:"],
            "Total Price"       => "Samlet pris",

            "Hotel info" => "Information om hotellet", //hotel name link @title
            "Address:"   => ["Adresse:"],
            "Phone:"     => "Telefon:",
            "Fax:"       => "Fax:",

            "Room Details" => ['Room details', 'Room Details'],
            "Guest name:"  => ["Gæstens navn:"],

            // Type 2
            "Dear "          => "Kære",
            "Property name"  => "Navn på overnatningssted",
            "Booking number" => ["Reservationsnummer", "booking.com reservationsnummer"],
            "Address"        => "Adresse",
            "Phone"          => "Telefon",
            "Fax"            => "Fax",

            "Check-in"  => ["Indtjekning"],
            "Check-out" => ["Udtjekning"],
            "Quantity"  => "Antal",

            "Guest Name"       => "Gæstens navn",
            "Total Room Price" => ["Samlet værelsespris"],
        ],
        'de' => [
            '(?<name>Hotel)SubjectRe' => [
                "Ihre Reservierung im (?<name>\S.+)",
                "Ihre Buchung in der Unterkunft (?<name>\S.+)? wurde aktualisiert",
            ],
            "Room"                                   => ["Zimmer"],
            'room'                                   => ['Zimmer'],
            "Cancellation policy:"                   => "Stornierungsbedingungen:",
            "Cancellation Fees in local hotel time:" => [
                "Stornierungsgebühren in Ortszeit des Hotels: ",
            ],
            //            "Your cancellation is now confirmed" => [],
            "Cancellation" => "Stornierung",

            // Type 1 Формат с рядомстоящими таблицами
            //            "Thanks," => "",
            'Booking number:'   => ["Buchungsnummer"],
            "Booked by:"        => ["Gebucht von"],
            "Your reservation:" => ["Ihre Buchung:"],
            "Check-in:"         => ["Anreise:"],
            "Check-out:"        => ["Abreise:"],
            "Total Price"       => "Gesamtpreis",

            "Hotel info" => "Hotelinformationen", //hotel name link @title
            "Address:"   => ["Adresse:"],
            "Phone:"     => "Telefon:",
            //            "Fax:" => "",

            "Room Details" => "Zimmerdetails",
            "Guest name:"  => ["Name des Gastes:"],

            // Type 2
            "Dear "          => "Hallo ",
            "Property name"  => "Name der Unterkunft",
            "Booking number" => ["Reservierungsnummer"],
            "Address"        => "Adresse",
            "Phone"          => "Telefon",
            //            "Fax" => "",

            "Check-in"  => ["Anreise"],
            "Check-out" => ["Abreise"],
            "Quantity"  => "Anzahl",

            "Guest Name"       => "Name des Gastes",
            "Total Room Price" => ["Gesamter Zimmerpreis"],
        ],
        'nl' => [
            '(?<name>Hotel)SubjectRe' => [
                "Uw reservering in (?<name>\S.+)",
            ],
            "Room"                                   => "Kamer",
            'room'                                   => ['kamer', "room"],
            "Cancellation policy:"                   => "Annuleringsvoorwaarden:",
            "Cancellation Fees in local hotel time:" => "Annuleringskosten in lokale tijd van het hotel:",
            //            "Your cancellation is now confirmed" => "",
            //            "Cancellation" => "",

            // Type 1 Формат с рядомстоящими таблицами
            "Thanks,"           => "Bedankt,",
            'Booking number:'   => ["Reserveringsnummer:", "Reserveringsnummer"],
            "Booked by:"        => ["Gereserveerd door:"],
            "Your reservation:" => ["Uw reservering:", "Uw reservering"],
            "Check-in:"         => ["Inchecken:", "Inchecken"],
            "Check-out:"        => ["Uitchecken:", "Inchecken"],
            "Total Price"       => "Totaalprijs",

            "Hotel info" => "Hotelinformatie", //hotel name link @title
            "Address:"   => ["Adres:", "Adres", "Adres :"],
            "Phone:"     => ["Telefoon:", "Telefoon"],
            "Fax:"       => ["Fax:", "Fax"],

            "Room Details" => "Kamerinformatie",
            "Guest name:"  => ["Naam gast:", "Gastnaam:"],

            // Type 2
            //            "Dear " => "",
            //            "Property name" => "",
            //            "Booking number" => "",
            //            "Address" => "",
            //            "Phone" => "",
            //            "Fax" => "",

            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "Quantity" => "",

            //            "Guest Name" => "",
            //            "Total Room Price" => "",
        ],
        'fi' => [
            '(?<name>Hotel)SubjectRe' => [
                "Varauksesi hotellissa\s+(?<name>\S.+)",
            ],
            "Room"                                   => "Huone",
            'room'                                   => "huone",
            "Cancellation policy:"                   => "Peruutusehdot:",
            "Cancellation Fees in local hotel time:" => "Peruutusmaksu:",
            //            "Your cancellation is now confirmed" => "",
            //            "Cancellation" => "",

            // Type 1 Формат с рядомстоящими таблицами
            //            "Thanks," => "",
            //            'Booking number:' => "",
            //            "Booked by:" => "",
            //            "Your reservation:" => "",
            //            "Check-in:" => "",
            //            "Check-out:" => "",
            //            "Total Price" => "",

            //            "Hotel info" => "", //hotel name link @title
            //            "Address:" => "",
            //            "Phone:" => "",
            //            "Fax:" => "",

            //            "Room Details" => "",
            //            "Guest name:" => "",

            // Type 2
            //            "Dear " => "",
            "Property name"  => "Majoituspaikka",
            "Booking number" => "Varausnumero",
            "Address"        => "Osoite",
            "Phone"          => "Puhelinnumero",
            "Fax"            => "Faksi",

            "Check-in"  => "Sisäänkirjautuminen",
            "Check-out" => "Uloskirjautuminen",
            "Quantity"  => "Huonemäärä",

            "Guest Name"       => "Asiakkaan nimi",
            "Total Room Price" => "Huoneen kokonaishinta",
        ],
        'fr' => [
            '(?<name>Hotel)SubjectRe' => [
                "Votre réservation à l' établissement (?<name>\S.+)",
            ],
            "Room"                                   => "Chambre",
            'room'                                   => "chambre",
            "Cancellation policy:"                   => "Conditions d'annulation:",
            "Cancellation Fees in local hotel time:" => "Frais d'annulation dans l'heure locale de l'établissement :",
            //            "Your cancellation is now confirmed" => "",
            //            "Cancellation" => "",

            // Type 1 Формат с рядомстоящими таблицами
            "Thanks,"           => "Merci,",
            'Booking number:'   => "Numéro de réservation:",
            "Booked by:"        => "Réservation effectuée par:",
            "Your reservation:" => "Votre réservation:",
            "Check-in:"         => "Arrivée :",
            "Check-out:"        => "Départ :",
            "Total Price"       => ["Montant Total", "Montant total"],

            "Hotel info" => "Informations sur l'hôtel", //hotel name link @title
            "Address:"   => "Adresse :",
            "Phone:"     => "Téléphone :",
            //            "Fax:" => "",

            "Room Details" => "Descriptif de la chambre",
            "Guest name:"  => "Nom du client :",

            // Type 2
            //            "Dear " => "",
            //            "Property name" => "",
            //            "Booking number" => "",
            //            "Address" => "",
            //            "Phone" => "",
            //            "Fax" => "",

            //            "Check-in" => "",
            //            "Check-out" => "",
            //            "Quantity" => "",

            //            "Guest Name" => "",
            //            "Total Room Price" => "",
        ],
        /*
        '' => [
            '(?<name>Hotel)SubjectRe' => [
                ".*\s+(?<name>\S.+)\s+.*",
            ],
            // Type 1 Формат с рядомстоящими таблицами
            "Thanks," => "",
            'Booking number:' => "",
            "Booked by:" => "",
            "Your reservation:" => "",
            'room' => "",
            "Check-in:" => "",
            "Check-out:" => "",
            // tax
            "Total Price" => "",

            "Hotel info" => "", //hotel name link @title
            "Address:" => "",
            "Phone:" => "",
            "Fax:" => "",

            "Room Details" => "",
            "Room" => "",
            "Guest name:" => "",
            "Cancellation policy:" => "",
            "Cancellation cost:" => "",

            // Type 2
            "Dear " => "",
            "Property name" => "",
            "Booking number" => "",
            "Address" => "",
            "Phone" => "",
            "Fax" => "",

            "Check-in" => "",
            "Check-out" => "",
            "Quantity" => "",

            "Total Room Price" => "",

            "Room" => "",
            "Guest Name" => "",
            "Cancellation policy:" => "",
            "Cancellation Fees in local hotel time:" => "",
            "Your cancellation is now confirmed" => "",
        ],
        */
    ];

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'booking') === false) {
            $body = $parser->getPlainBody();
        } elseif (stripos($body, 'Г¤') !== false) {
            $body = mb_convert_encoding($body, 'CP1251');
            $this->http->SetBody($body);
        }

        if (strpos($body, $this->reBody) !== false) {
            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) !== false) {
            foreach ($this->reSubject as $lang => $re) {
                foreach ($re as $r) {
                    if (strpos($headers["subject"], $r) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();
        $body = $parser->getHTMLBody();

        if (stripos($body, 'booking') === false) {
        } elseif (stripos($body, 'Г¤') !== false) {
            $this->http->SetBody(mb_convert_encoding($body, 'CP1251'));
        }

        if (!$this->assignLang($body)) {
            $this->logger->debug("can't determinate language");

            return null;
        }

        if (!in_array($this->lang, ['zh'])
            && strpos($this->http->Response['body'], 'Booking confirmation') === false
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Your reservation'))}]/ancestor::tr[1]/following-sibling::tr[1][{$this->starts($this->t('checkin'))}]/following-sibling::tr[1][{$this->starts($this->t('checkout'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->eq($this->t('Your reservation'))}]/ancestor::tr[1]/preceding-sibling::tr[1][{$this->starts($this->t('Booked by'))}]")->length === 0) {
            $this->logger->debug("go to parse by IsBeginForRafactoryProv.php");

            return $email;
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your booking in'))}]/ancestor::tr[{$this->starts($this->t('Thanks'))}]")->length > 0) {
            $this->logger->debug("go to parse by IsBeginForRafactoryProv.php");

            return $email;
        }

        $type = '';

        if ($this->http->XPath->query("//td[" . $this->eq($this->t("Cancellation")) . " and not(./*) and (contains(@style, 'color: rgb(176, 176, 176)') or contains(@style, 'color:#b0b0b0;'))][not(preceding::text()[" . $this->eq($this->t("Address:")) . "])]")->length > 0
        ) {
            $this->parseEmailType3($email);
            $type = '3';
        } elseif (
            $this->http->XPath->query("//text()[" . $this->eq($this->t("Address:")) . "]/ancestor::td/preceding-sibling::td[" . $this->contains($this->t("Check-in:")) . "]")->length > 0
            || $this->http->XPath->query("//*[" . $this->eq($this->t("Cancellation")) . "]/ancestor::td/preceding-sibling::td[" . $this->contains($this->t("Check-in:")) . "]")->length > 0
        ) {
            $this->parseEmailType1($email);
            $type = '1';
        } elseif ($this->http->XPath->query("//*[" . $this->starts($this->t("Quantity")) . "]")->length > 0
        ) {
            $this->parseEmailType2($email);
            $type = '2';
        } else {
            // две рядомстоящие таблицы
            $this->parseEmailType2($email);
            $type = '2';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->emailSubject = $parser->getSubject();
        $body = $parser->getHTMLBody();

        if (stripos($body, 'booking') === false) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        } elseif (stripos($body, 'Г¤') !== false) {
            $this->http->SetBody(mb_convert_encoding($body, 'CP1251'));
        }

        if (!$this->assignLang($body)) {
            return null;
        }

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'Reservation' . ucfirst($this->lang),
        ];
    }

    //# header - string/array - row header ( Booking number: )
    //# many - bool - one node / many nodes
    //# inner - xpath - get node(s) into field
    //# re - regExp
    public function getField($header, $many = false, $inner = false, $re = "#.+#")
    {
        if (is_array($header)) {
            foreach ($header as &$s) {
                $s = 'normalize-space(text())="' . $s . '"';
            }
            $str = implode(' or ', $header);
        } else {
            $str = 'normalize-space(text())="' . $header . '"';
        }
        $xpath = '//*[' . $str . ']/ancestor-or-self::td[1]/following-sibling::td[1]';

        $http = $this->http;

        if (!$many) {
            // One node
            if (!$inner) {
                return $http->FindSingleNode($xpath, null, true, $re);
            } else {
                // Get inner node
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    return null;
                }

                return $http->FindSingleNode($inner, $nodes->item(0), true, $re);
            }
        } else {
            // Many nodes
            if (!$inner) {
                return $http->FindNodes($xpath, null, $re);
            } else {
                // Get inner nodes
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    return null;
                }
                $res = [];

                foreach ($nodes as $node) {
                    $res = array_merge($res, $http->FindNodes($inner, $node, $re));
                }

                return $res;
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) == 'array' || gettype($array[$i]) == 'object') && sizeof($array[$i]) > 0) || $i == $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    // Type 1 Формат с рядомстоящими таблицами
    private function parseEmailType1(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking number:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"))
        ;
        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Guest name:")) . "]/following::text()[normalize-space()][1]", null,
            "/^\s*(\w[\w \-]+)\s*$/");
        $travellers[] = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Booked by:")) . "]/following-sibling::td[normalize-space()][1]", null, true,
            "/^\s*(\w[\w \-]+)\s*(?:\s+\S+@\S+:)?$/");

        $travellers = array_unique(array_filter($travellers));

        if (empty($travellers)) {
            $travellers[] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Thanks,")) . "]", null,
                true, "#" . $this->opt($this->t("Thanks,")) . "\s*(\w[\w \-]+)\s*!#");
        }

        $h->general()
            ->travellers($travellers);

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Your cancellation is now confirmed")) . "])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Hotel
        $name = $this->http->FindSingleNode("(//a[" . $this->contains($this->t('Hotel info'), '@title') . "])[1]");

        if (empty($name) && !empty($this->emailSubject)) {
            if (is_string($this->t("(?<name>Hotel)SubjectRe")) && preg_match("/" . $this->t("(?<name>Hotel)SubjectRe") . "/", $this->emailSubject, $m) && !empty($m['name'])) {
                $name = $m['name'];
            } elseif (is_array($this->t("(?<name>Hotel)SubjectRe"))) {
                foreach ($this->t('(?<name>Hotel)SubjectRe') as $regexp) {
                    if (preg_match("/" . $regexp . "/", $this->emailSubject, $m) && !empty($m['name'])) {
                        $name = $m['name'];

                        break;
                    }
                }
            }
        }

        $address = implode(", ", $this->http->FindNodes("//td[" . $this->eq($this->t("Address:")) . "]/following::td[normalize-space()][1]//text()[normalize-space()]"));
        $phone = implode(", ", $this->http->FindNodes("//td[" . $this->eq($this->t("Phone:")) . "]/following::td[normalize-space()][1]//text()[normalize-space()]"));
        $fax = implode(", ", $this->http->FindNodes("//td[" . $this->eq($this->t("Fax:")) . "]/following::td[normalize-space()][1]//text()[normalize-space()]"));

        $h->hotel()
            ->name($name)
            ->address(preg_replace("#\s*,[\s,]+#", ', ', $address))
            ->phone($phone, true, true)
            ->fax($fax, true, true)
        ;

        // Booked
        $checkIn = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-in:")) . "]/following::td[normalize-space()][1]");

        if (preg_match("/(?<date>.+)\(\D*(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\b.*\)/ui", $checkIn, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['time'], $this->normalizeDate($m['date'])));
        }
        $checkOut = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-out:")) . "]/following::td[normalize-space()][1]");

        if (preg_match("/(?<date>.+)\(.*\b(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\D*\)/ui", $checkOut, $m)) {
            $h->booked()
                ->checkOut(strtotime($m['time'], $this->normalizeDate($m['date'])));
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//td[" . $this->eq($this->t("Your reservation:")) . "]/following::td[normalize-space()][1]", null, true,
                "/\b(\d+) ?" . $this->opt($this->t("room")) . "/"), true, true);

        // Cancellation
        $cancellation = implode(" ", array_unique($this->http->FindNodes("//*[" . $this->eq($this->t("Cancellation policy:")) . "]/following::ul[1]/li")));
        $cancellationCost = implode(". ", $this->http->FindNodes("//*[" . $this->eq($this->t("Cancellation Fees in local hotel time:")) . "]/following::ul[1]/li"));

        $h->general()
            ->cancellation($cancellation
                . ((!empty($cancellationCost)) ? $this->http->FindSingleNode("(//*[" . $this->eq($this->t("Cancellation Fees in local hotel time:")) . "])[1]") . ' ' . $cancellationCost : ''), true, true);

        // Rooms
        if ($h->getRoomsCount() == 1) {
            $roomType = $this->http->FindSingleNode('(//*[' . $this->eq($this->t('Total Price')) . ']/ancestor::tr[1]/preceding-sibling::tr[last()]/td[1])[1]', null, true, "#^(?:\d+\s+|)(.+)#i");

            $roomDesc = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Room Details")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>0][1]//text()[string-length(normalize-space(.))>0])[1]");

            if (!empty($roomType) || !empty($roomDesc)) {
                $r = $h->addRoom();
            }

            if (!empty($roomType)) {
                $r->setType($roomType);
            }

            if (!empty($roomDesc)) {
                $r->setDescription($roomDesc);
            }
        } elseif ($h->getRoomsCount() > 1) {
            $roomTypes = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Room'))}]", null, "/" . $this->opt($this->t("Room")) . "\s*\d+\s*[:,]\s*(.+)/i")));
            $roomDescs = [];

            if (!empty($roomTypes)) {
                $roomDescs = $this->http->FindNodes("//text()[" . $this->starts($this->t("Room")) . " and " . $this->contains($roomTypes) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>0][1]//text()[string-length(normalize-space(.))>0]");
            }

            if (count($roomTypes) !== $h->getRoomsCount()) {
                $roomTypes = [];
            }

            if (count($roomDescs) !== $h->getRoomsCount()) {
                $roomDescs = [];
            }

            for ($i = 0; $i < $h->getRoomsCount(); $i++) {
                $r = $h->addRoom();

                if (!empty($roomTypes[$i])) {
                    $r->setType($roomTypes[$i]);
                }

                if (!empty($roomDescs[$i])) {
                    $r->setDescription($roomDescs[$i]);
                }
            }
        }

        $total = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total Price')) . ']/ancestor::td[1]/following-sibling::td[1]');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function parseEmailType2(Email $email)
    {
        $h = $email->add()->hotel();

        // General

        $h->general()
            ->confirmation($this->http->FindSingleNode("//*[" . $this->eq($this->t("Booking number")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"))
        ;
        $travellers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Guest Name")) . "]/following::text()[normalize-space()][1]", null,
            "/^\s*(\w[\w \-]+)\s*$/");

        $travellers = array_unique(array_filter($travellers));

        if (empty($travellers)) {
            $travellers[] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null,
                true, "#" . $this->opt($this->t("Dear ")) . "\s*(\w[\w\-]+(?: [\w\-]+){1,3})\s*[!,]?$#");
        }

        $h->general()
            ->travellers($travellers);

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Your cancellation is now confirmed")) . "])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Hotel
        $name = $this->http->FindSingleNode("(//td[" . $this->eq($this->t('Property name')) . "]/following-sibling::td[normalize-space()][1])[1]");

        if (empty($name) && !empty($this->emailSubject)) {
            if (is_string($this->t("(?<name>Hotel)SubjectRe")) && preg_match("/" . $this->t("(?<name>Hotel)SubjectRe") . "/", $this->emailSubject, $m) && !empty($m['name'])) {
                $name = $m['name'];
            } elseif (is_array($this->t("(?<name>Hotel)SubjectRe"))) {
                foreach ($this->t('(?<name>Hotel)SubjectRe') as $regexp) {
                    if (preg_match("/" . $regexp . "/", $this->emailSubject, $m) && !empty($m['name'])) {
                        $name = $m['name'];

                        break;
                    }
                }
            }
        }

        $address = implode(", ", $this->http->FindNodes("//td[" . $this->eq($this->t("Address")) . "]/following::td[normalize-space()][1]//text()[normalize-space()]"));
        $phone = implode(", ", $this->http->FindNodes("//td[" . $this->eq($this->t("Phone")) . "]/following::td[normalize-space()][1]//text()[normalize-space()]"));
        $fax = implode(", ", $this->http->FindNodes("//td[" . $this->eq($this->t("Fax")) . "]/following::td[normalize-space()][1]//text()[normalize-space()]"));

        $h->hotel()
            ->name($name)
            ->address(preg_replace("#\s*,[\s,]+#", ', ', $address))
            ->phone($phone, true, true)
            ->fax($fax, true, true)
        ;

        // Booked
        $checkIn = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Quantity")) . "]/preceding::td[" . $this->eq($this->t("Check-in")) . "])[last()]/following::td[normalize-space()][1]");

        if (empty($checkIn)) {
            $checkIn = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Check-in")) . "])[1]/following::td[normalize-space()][1]");
        }

        if (preg_match("/(?<date>.+)\s*,\s*" . $this->opt($this->t("Check-in")) . "\s*\D*(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\b.*/ui", $checkIn, $m)
                || preg_match("/(?<date>.+\d{4}.*?)\s*[,(]\s*(?:\D*\s+)?(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\b.*/ui", $checkIn, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['time'], $this->normalizeDate($m['date'])));
        } else {
            $h->booked()
                ->checkIn($this->normalizeDate($checkIn));
        }
        $checkOut = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Quantity")) . "]/preceding::td[" . $this->eq($this->t("Check-out")) . "])[last()]/following::td[normalize-space()][1]");

        if (empty($checkOut)) {
            $checkOut = $this->http->FindSingleNode("(//td[" . $this->eq($this->t("Check-out")) . "])[1]/following::td[normalize-space()][1]");
        }

        if (preg_match("/(?<date>.+)\s*,\s*" . $this->opt($this->t("Check-out")) . ".*\b(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\D*$/ui", $checkOut, $m)
                || preg_match("/(?<date>.+\d{4}.*?)\s*[,(]\s*(?:\D*\s+)?(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\b.*/ui", $checkOut, $m)) {
            $h->booked()
                ->checkOut(strtotime($m['time'], $this->normalizeDate($m['date'])));
        } else {
            $h->booked()
                ->checkOut($this->normalizeDate($checkOut));
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//td[" . $this->eq($this->t("Quantity")) . "]/following::td[normalize-space()][1]", null, true,
                "/\b(\d+) ?" . $this->opt($this->t("room")) . "/u"), true, true);

        // Cancellation
        $cancellation = implode(" ", array_unique($this->http->FindNodes("//*[" . $this->eq($this->t("Cancellation policy:")) . "]/following::ul[1]/li")));
        $cancellationCost = implode(". ", $this->http->FindNodes("//*[" . $this->eq($this->t("Cancellation Fees in local hotel time:")) . "]/following::ul[1]/li"));

        $h->general()
            ->cancellation($cancellation
                . ((!empty($cancellationCost)) ? $this->http->FindSingleNode("(//*[" . $this->eq($this->t("Cancellation Fees in local hotel time:")) . "])[1]") . ' ' . $cancellationCost : ''), true, true);

        // Rooms
        $roomTypes = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Room'))}]", null, "/" . $this->opt($this->t("Room")) . "\s*\d+\s*,\s*(.+)/i")));
        $roomDescs = [];

        if (!empty($roomTypes)) {
            $roomDescs = $this->http->FindNodes("//text()[" . $this->starts($this->t("Room")) . " and " . $this->contains($roomTypes) . "]/following::text()[string-length(normalize-space(.))>0][1]/ancestor::div[1]");
        }

        if (count($roomTypes) !== $h->getRoomsCount()) {
            $roomTypes = [];
        }

        if (count($roomDescs) !== $h->getRoomsCount()) {
            $roomDescs = [];
        }

        if (!empty($roomTypes) || !empty($roomDescs)) {
            for ($i = 0; $i < $h->getRoomsCount(); $i++) {
                $r = $h->addRoom();

                if (!empty($roomTypes[$i])) {
                    $r->setType($roomTypes[$i]);
                }

                if (!empty($roomDescs[$i])) {
                    $r->setDescription($roomDescs[$i]);
                }
            }
        }

        $total = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total Room Price')) . ']/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space()][1]');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function parseEmailType3(Email $email)
    {
        $h = $email->add()->hotel();

        // General
        $h->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Booking number:")) . "]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"))
        ;
        $travellers[] = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Booked by:")) . "]/following-sibling::td[normalize-space()][1]", null, true,
            "/^\s*(\w[\w ]+)\s*(?:\([^)^(]+@[^)^(]+\)\s*)?\W?$/");

        $travellers = array_unique(array_filter($travellers));

        if (empty($travellers)) {
            $travellers[] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Dear ")) . "]", null,
                true, "#" . $this->opt($this->t("Dear ")) . "\s*(\w[\w ]+)\s*,#");
        }

        $h->general()
            ->travellers($travellers);

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("Your cancellation is now confirmed")) . "])[1]"))) {
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Hotel
        $name = $this->http->FindSingleNode("(//a[" . $this->contains($this->t('Hotel info'), '@title') . "])[1]");

        if (empty($name) && !empty($this->emailSubject)) {
            if (is_string($this->t("(?<name>Hotel)SubjectRe")) && preg_match("/" . $this->t("(?<name>Hotel)SubjectRe") . "/", $this->emailSubject, $m) && !empty($m['name'])) {
                $name = $m['name'];
            } elseif (is_array($this->t("(?<name>Hotel)SubjectRe"))) {
                foreach ($this->t('(?<name>Hotel)SubjectRe') as $regexp) {
                    if (preg_match("/" . $regexp . "/", $this->emailSubject, $m) && !empty($m['name'])) {
                        $name = $m['name'];

                        break;
                    }
                }
            }
        }

        if (!empty($name)) {
            $info = implode("\n", $this->http->FindNodes("//*[normalize-space() = '" . $name . "']/following-sibling::*[normalize-space()][1]//text()[normalize-space()]"));

            if (preg_match("#([\s\S]+)\n\s*" . $this->opt($this->t("Phone:")) . "(.+)#", $info, $m)) {
                $address = preg_replace("/\s+/", ' ', $m[1]);
                $phone = $m[2];
            }
        }

        $h->hotel()
            ->name($name)
            ->address(preg_replace("#\s*,[\s,]+#", ', ', $address ?? null))
            ->phone($phone ?? null, true, true)
        ;

        // Booked
        $checkIn = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-in")) . "]/following::td[normalize-space()][1]");

        if (preg_match("/(?<date>.+)\(\D*(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\b.*\)/ui", $checkIn, $m)) {
            $h->booked()
                ->checkIn(strtotime($m['time'], $this->normalizeDate($m['date'])));
        }
        $checkOut = $this->http->FindSingleNode("//td[" . $this->eq($this->t("Check-out")) . "]/following::td[normalize-space()][1]");

        if (preg_match("/(?<date>.+)\(.*\b(?<time>\d{1,2}:\d{2}( ?[ap]m)?)\D*\)/ui", $checkOut, $m)) {
            $h->booked()
                ->checkOut(strtotime($m['time'], $this->normalizeDate($m['date'])));
        }

        $h->booked()
            ->rooms($this->http->FindSingleNode("//td[" . $this->eq($this->t("Your reservation:")) . "]/following::td[normalize-space()][1]", null, true,
                "/\b(\d+) ?" . $this->opt($this->t("room")) . "/"), true, true);

        // Cancellation
        $cancellation = implode(" ", array_unique($this->http->FindNodes("//*[" . $this->eq($this->t("Cancellation policy:")) . "]/following::ul[1]/li")));
        $cancellationCost = implode(". ", $this->http->FindNodes("//*[" . $this->eq($this->t("Cancellation Fees in local hotel time:")) . "]/following::ul[1]/li"));

        $h->general()
            ->cancellation($cancellation
                . ((!empty($cancellationCost)) ? $this->http->FindSingleNode("(//*[" . $this->eq($this->t("Cancellation Fees in local hotel time:")) . "])[1]") . ' ' . $cancellationCost : ''), true, true);

        // Rooms
        if ($h->getRoomsCount() == 1) {
            $r = $h->addRoom();
            $roomType = $this->http->FindSingleNode('(//*[' . $this->eq($this->t('Total Price')) . ']/ancestor::tr[1]/preceding-sibling::tr[last()]/td[1])[1]', null, true, "#^(?:\d+\s+|)(.+)#i");

            $roomDesc = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Room Details")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>0][1]//text()[string-length(normalize-space(.))>0])[1]"
            //            , null, true, "#(.*?)(?:\s*{$this->t('Please')}|$)#i"
            );

            if (!empty($roomType)) {
                $r->setType($roomType);
            }

            if (!empty($roomDesc)) {
                $r->setDescription($roomDesc);
            }
        } elseif ($h->getRoomsCount() > 1) {
            $roomTypes = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Room'))}]", null, "/" . $this->opt($this->t("Room")) . "\s*\d+\s*:\s*(.+)/i")));
            $roomDescs = [];

            if (!empty($roomTypes)) {
                $roomDescs = $this->http->FindNodes("//text()[" . $this->starts($this->t("Room")) . " and " . $this->contains($roomTypes) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>0][1]//text()[string-length(normalize-space(.))>0]");
            }

            if (count($roomTypes) !== $h->getRoomsCount()) {
                $roomTypes = [];
            }

            if (count($roomDescs) !== $h->getRoomsCount()) {
                $roomDescs = [];
            }

            for ($i = 0; $i < $h->getRoomsCount(); $i++) {
                $r = $h->addRoom();

                if (!empty($roomTypes[$i])) {
                    $r->setType($roomTypes[$i]);
                }

                if (!empty($roomDescs[$i])) {
                    $r->setDescription($roomDescs[$i]);
                }
            }
        }

        $total = $this->http->FindSingleNode('//text()[' . $this->eq($this->t('Total Price')) . ']/ancestor::td[1]/following-sibling::td[1]');

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $h->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        return $email;
    }

    private function normalizeDate($date, $firstTime = true)
    {
        //		$this->logger->info('Date in = '. $date);
        $in = [
            // Wednesday, February 12, 2014
            "#^\s*\D*,\s*([^\d\s]+)\s+(\d{1,2})\s*,\s+(\d{4})\s*$#",
            //zaterdag 3 mei 2014
            "#^\s*\D*\s+(\d{1,2})[\.]?\s+([^\d\s]+)\s+(\d{4})\s*$#",
            //søndag den 30. september 12
            "#^\s*\D*\s+(\d{1,2})[\.]?\s+([^\d\s]+)\s+(\d{2})\s*$#",
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$1 $2 20$3',
        ];

        /*
        if ($firstTime){
            $in = [
                //2017 年 6 月 28 日（星期三） （15:00 起）
                '#^\s*(\d+)\s*年\s*(\d+)\s*月\s*(\d+)\s*日.+?[\(（](\d+:\d+).+$#ius',
                '#^.*?(\w+)\s+(\d+),\s+(\d+).*?(\d+:\d+(?:\s*[ap]m|)).*$#ius',
                '#^.*?(\d+)\s+(\w+)\s+(\d+).*?(\d+:\d+(?:\s*[ap]m|)).*$#ius',
                //2013-06-24 (was 2013-06-26)
                "#^\s*(\d{4})\-(\d{2})\-(\d{2}).*$#",
                //יום שישי, 4 באוגוסט 2017 (החל מהשעה 15:00)
                "#^[^\d\s]+\s+[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s+(\d{4})\s+\([^\d]+(\d+:\d+)\)$#",
                // tirsdag den 23. oktober 12
                "#^[^\d\s]+\s+den\s+(\d+)\.\s+([^\d\s]+)\s+(\d{2})\s*$#",
                // søndag den 30. september 12 (08:30 – 10:30)
                "#^[^\d\s]+\s+den\s+(\d+)\.\s+([^\d\s]+)\s+(\d{2})\s*\(\s*(\d+:\d+).*\s*$#",
            ];
            $out = [
                '$1-$2-$3 $4',
                '$2 $1 $3, $4',
                '$1 $2 $3, $4',
                '$1-$2-$3',
                '$1 $2 $3, $4',
                '$1 $2 20$3',
                '$1 $2 20$3, $4',
            ];
        }else{
            $in = [
                //2017 年 6 月 28 日（星期三） （15:00 起）
                '#^\s*(\d+)\s*年\s*(\d+)\s*月\s*(\d+)\s*日.+?[\(（](\d+:\d+).+$#ius',
                '#^.*?(\w+)\s+(\d+),\s+(\d+).*[\s\-]+.*?(\d+:\d+(?:\s*[ap]m|)).*?$#ius',
                '#^.*?(\d+)\s+(\w+)\s+(\d+).*[\s\-]+.*?(\d+:\d+(?:\s*[ap]m|)).*?$#ius',
                //2013-06-24 (was 2013-06-26)
                "#^\s*(\d{4})\-(\d{2})\-(\d{2}).*$#",
                // tirsdag den 23. oktober 12
                "#^[^\d\s]+\s+den\s+(\d+)\.\s+([^\d\s]+)\s+(\d{2})\s*$#",
                // søndag den 30. september 12 (08:30 – 10:30)
                "#^[^\d\s]+\s+den\s+(\d+)\.\s+([^\d\s]+)\s+(\d{2})\s*\(.+\b(\d+:\d+)\s*\)\s*$#",
            ];
            $out = [
                '$1-$2-$3 $4',
                '$2 $1 $3, $4',
                '$1 $2 $3, $4',
                '$1-$2-$3',
                '$1 $2 20$3',
                '$1 $2 20$3, $4',
            ];
        }*/
        $date = preg_replace($in, $out, $date);
        //		$this->logger->info('Date out = '. $date);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        foreach ($this->reBody2 as $lang => $re) {
            foreach ($re as $r) {
                if (strpos($body, $r) !== false || $this->http->XPath->query("//*[contains(normalize-space(), '" . $r . "')]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", trim($price)));

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $s = trim($s);
        $sym = [
            '€' => 'EUR',
            //            '$' => 'USD',
            '£'   => 'GBP',
            'US$' => 'USD',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function eq($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'starts-with(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
