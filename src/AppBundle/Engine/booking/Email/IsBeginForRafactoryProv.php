<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class IsBeginForRafactoryProv extends \TAccountChecker
{
    public $mailFiles = "booking/it-10280825.eml, booking/it-10849337.eml, booking/it-10982657.eml, booking/it-11210836.eml, booking/it-112600173.eml, booking/it-12.eml, booking/it-12070454.eml, booking/it-12243783.eml, booking/it-13.eml, booking/it-13172379.eml, booking/it-141744376.eml, booking/it-1550996.eml, booking/it-1587038.eml, booking/it-1587039.eml, booking/it-1587041.eml, booking/it-1592867.eml, booking/it-1642145.eml, booking/it-1672440.eml, booking/it-1676913.eml, booking/it-1680524.eml, booking/it-1690371.eml, booking/it-1705286.eml, booking/it-1737785.eml, booking/it-180430522.eml, booking/it-1821177.eml, booking/it-1826247.eml, booking/it-1827706.eml, booking/it-1843108.eml, booking/it-1855263.eml, booking/it-1858299.eml, booking/it-1861175.eml, booking/it-1898732.eml, booking/it-1917857.eml, booking/it-1918138.eml, booking/it-1918144.eml, booking/it-1980287.eml, booking/it-1984434.eml, booking/it-1988347.eml, booking/it-20.eml, booking/it-20391082.eml, booking/it-2114042.eml, booking/it-2213030.eml, booking/it-2249756.eml, booking/it-2249763.eml, booking/it-2250020.eml, booking/it-2251587.eml, booking/it-2251616.eml, booking/it-2339537.eml, booking/it-2342852.eml, booking/it-2399505.eml, booking/it-2411528.eml, booking/it-2411529.eml, booking/it-2411530.eml, booking/it-2411531.eml, booking/it-2411532.eml, booking/it-2411533.eml, booking/it-2419184.eml, booking/it-2478718.eml, booking/it-2566578.eml, booking/it-25705005.eml, booking/it-2666961.eml, booking/it-2706071.eml, booking/it-27643682.eml, booking/it-27643720.eml, booking/it-27947687.eml, booking/it-27947689.eml, booking/it-28041220.eml, booking/it-2847644.eml, booking/it-28529218.eml, booking/it-2863989.eml, booking/it-2938543.eml, booking/it-2949421.eml, booking/it-2949456.eml, booking/it-2949579.eml, booking/it-3000901.eml, booking/it-3015339.eml, booking/it-3042264.eml, booking/it-311641156.eml, booking/it-3129238.eml, booking/it-3129241.eml, booking/it-3129244.eml, booking/it-3129402.eml, booking/it-3138248.eml, booking/it-3151906.eml, booking/it-3161700.eml, booking/it-323625724.eml, booking/it-33226650.eml, booking/it-3330047.eml, booking/it-3330052.eml, booking/it-3330059.eml, booking/it-33566361.eml, booking/it-3378831.eml, booking/it-33924875.eml, booking/it-33999463.eml, booking/it-34823207.eml, booking/it-34862197.eml, booking/it-34908360.eml, booking/it-35512499.eml, booking/it-4008095.eml, booking/it-4418038.eml, booking/it-4557166.eml, booking/it-46219020.eml, booking/it-49644924.eml, booking/it-52066593.eml, booking/it-54320526.eml, booking/it-5461422.eml, booking/it-5461424.eml, booking/it-5525304.eml, booking/it-58488222.eml, booking/it-5859011.eml, booking/it-59543847.eml, booking/it-60173533.eml, booking/it-60237449.eml, booking/it-61758031.eml, booking/it-6642518.eml, booking/it-6765937.eml, booking/it-67861399.eml, booking/it-7750117.eml, booking/it-78148002.eml, booking/it-90477397.eml, booking/it-93259934.eml, booking/it-9752173.eml"; // +1 bcdtravel(html)[nl]

    public $reSubject = [
        "en"  => "Your booking is confirmed at", "Your ^_booking^_ is confirmed at", "Booking cancelled for",
        "et"  => "broneering kinnitatud",
        "Aitäh! Peatumine (",
        "pl"  => "Potwierdzono rezerwację w obiekcie",
        "pl2" => "Twoja rezerwacja w obiekcie",
        "zh"  => "的预订已确认",
        "zh2" => "感謝您！您在",
        "bs"  => "Rezervacija je potvrđena",
        "sv"  => "Tack! Din bokning på",
        "sv2" => "Bokning avbokad för ",
        "hu"  => "Köszönjük!",
        "hu2" => "beli foglalása visszaigazolva",
        "de"  => "Danke! Ihre Buchung ist bestätigt:",
        "ro"  => "Vă mulţumim! Rezervarea dumneavoastră la",
        "Mulţumim! Rezervarea ta la ",
        "fi"  => "Kiitos! Varauksesi on vahvistettu – ",
        "cs"  => "Děkujeme! Vaše rezervace je potvrzena",
        "it"  => "Grazie! La tua prenotazione per",
        "it2" => "Prenotazione cancellata per",
        "el"  => "Ευχαριστούμε! Η κράτηση",
        "nl"  => "Bedankt! Uw boeking bij", "Bedankt! Je boeking bij", "Bedankt! Uw reservering bij",
        "es3" => "Confirmación de la reserva de eDreams Prime",
        "es2" => "Se canceló la reserva en",
        "is"  => "Takk! Bókun þín á ",
        "th"  => "ขอบคุณ การจองที่",
        "pt"  => "Sua reserva",
        "pt2" => "Reserva cancelada em",
        "ca"  => "Gràcies! La reserva està confirmada",
        "da"  => "Tak! Din booking hos ",
        "ja"  => "の予約が確定しました！",
        "ar"  => "شكراً، تم تأكيد حجزك في",
        "fr"  => "Réservation à l’établissement",
        "fr2" => "Merci ! Votre réservation à l&#039;établissement",
        "fr3" => "Merci ! Votre réservation à l", // not the same
        "fr4" => "Merci ! Votre réservation à l'établissement", // not the same
        "fr5" => "Votreréservation à l'établissement",
        "tr"  => "rezervasyonunuz onaylandı",
        "lt"  => "patvirtino jūsų užsakymą",
        "lv"  => "Rezervējums naktsmītnē",
        "sk"  => "Ďakujeme! Rezervácia v ubytovaní",
        "Ďakujeme! Vaša rezervácia v ubytovaní",
        "sl"  => "Potrjena rezervacija v nastanitvi",
        "hr"  => "Rezervacija u objektu",
        "ko"  => "예약이 확정되었습니다 -",
        "ko2" => "감사합니다! 아트스테이 ",
        "ko3" => "🛄 감사합니다! ",
        "ru"  => "Спасибо! Ваше бронирование в ",
        "uk"  => "Дякуємо! Ваше бронювання в ",
        "no"  => "🛄 Takk! Bookingen på",
        "Bookingen din på", "bookingen din på",
        "es" => "¡Gracias! Tu reserva en el",
        "vi" => "Cảm ơn! Đặt phòng của bạn ở",
        "Đặt phòng đã hủy tại ",
        "he" => "תודה! ההזמנה שלכם ב",
        "bg" => "Вашата резервация в ",
        // id
        "Terima kasih! Pemesanan Anda dikonfirmasi di",
        // ms
        'Terima kasih! Tempahan anda telah disahkan di',
    ];

    public $reBody2 = [
        "et"       => "Teie broneering",
        "et2"      => "Broneeringu üksikasjad",
        "et3"      => "Sisseregistreerimine",
        "pl"       => "Twoja rezerwacja",
        "zh"       => "您的预订",
        "zh2"      => "您的預訂已免費取消",
        "zh3"      => "感谢您的预订",
        "zh4"      => "入住时间",
        "zh5"      => "您在成功的訂房已確認",
        "zh6"      => "您已成功取消訂單",
        "zh7"      => "已確認您在",
        "zh8"      => "訂房已完成",
        "zh9"      => "訂單內容",
        "zh11"     => "您預訂的入住人數",
        "zh12"     => "入住時間",
        "zh13"     => "您的订单已免费取消",
        "bs"       => "Prijavljivanje",
        "sv"       => "Din bokning",
        "sv2"      => "Utcheckning",
        "hu"       => "Az Ön foglalása",
        "hu2"      => "foglalását ingyen töröltük",
        "hu3"      => "Foglalás részletei",
        "de"       => "Ihre Buchung",
        "de2"      => "Buchungsinformationen",
        "ro"       => "Rezervarea dvs.",
        "ro2"      => "Numărul confirmării",
        'fi'       => 'Varauksesi',
        'fi2'      => 'Lähtöpäivä',
        'cs'       => 'Vaše rezervace',
        'it'       => 'La tua prenotazione',
        'it2'      => 'prenotazione è stata cancellata',
        'el'       => 'Η κράτησή σας',
        'ru'       => 'Ваше бронирование',
        'ru2'      => 'Это бронирование',
        'uk'       => 'Ваше бронювання',
        'nl'       => 'Uw reservering',
        'nl2'      => 'Je boeking is',
        'nl3'      => 'Je reservering',
        'nl4'      => 'Over je boeking',
        'nl5'      => 'Je hebt geboekt voor',
        'es'       => 'Tu reserva',
        'es2'      => 'Número de confirmación',
        'es3'      => 'Tu reserva está garantizada',
        'is'       => 'Pöntunin þín',
        'is2'      => 'Bókunarnúmer',
        'th'       => 'การสำรองห้องพักของท่าน',
        'th2'      => 'ยืนยันการจองของท่านใ',
        'th3'      => 'จำนวนผู้เข้าพักสำหรับการจองนี้',
        'pt'       => 'Sua reserva',
        'pt2'      => 'sua reserva',
        'ca'       => 'La teva reserva',
        'da'       => 'Din reservation',
        'da2'      => 'Indtjekning',
        //'ja'       => 'ご予約',
        'ja'       => 'キャンセル料',
        'ja2'      => '宿泊施設にメールする',
        'he'       => 'ההזמנה שלכם',
        'he2'      => 'ביטלתם את הזמנתכם',
        'ar'       => 'حجزكم',
        'ar2'      => 'تسجيل الوصول',
        // fr
        'fr'     => 'votre réservation',
        'fr2'    => 'Votre réservation a bien été annulée',
        'fr3'    => 'Votre réservation',
        'fr4'    => 'votre réservation est désormais confirmée',
        'fr5'    => 'Détails de la réservation',

        'tr'      => 'Rezervasyonunuz',
        'tr2'     => 'İPTAL EDİLDİ',
        'lt'      => 'Jūsų užsakymas',
        'lv'      => 'Jūsu rezervējums',
        "sk"      => "Vaša rezervácia",
        'sl'      => 'Pravila o odpovedi rezervacije',
        'sl2'     => 'Strošek odpovedi rezervacije',
        "hr"      => "Prijava", //"Vaša rezervacija",the same in croatia and bosnia
        "ko"      => "예약이 확정되었습니다",
        "ko2"     => "우현님",
        //"ko3"    => "체크인", not use
        "ko1"    => "고객님의 예약이 무료로",
        //        "no"    => "Din booking", // not use, the same as 'da'
        "no2"    => "Bookingopplysninger",
        "no3"    => "Innsjekking",
        "no4"    => "Du har booket for",
        "vi"     => "Đặt phòng của bạn",
        "bg"     => "Вашата резервация",
        "ms"     => "Daftar masuk",
        "id"     => "Pemesanan Anda",

        // TODO: en should be last in list
        "en"  => "Your reservation",
        "en2" => "your reservation",
        "en3" => "This reservation was",
        "en4" => "Your booking ",
        "en5" => "About your booking",
    ];

    public static $dictionary = [
        "et" => [
            "(?<name>hotel)FromSubject" => "Aitäh! Peatumine \((?<name>.+)\) on kinnitatud",

            "Confirmation Number:"  => ["Broneeringu number:", "Kinnitus:"],
            "Check-in"              => "Sisseregistreerimine",
            "Check-out"             => "Väljaregistreerimine",
            "Show directions"       => "Näita teejuhiseid",
            "Address:"              => "Asukoht",
            "Phone:"                => ["Telefon:", "Telefon"],
            "guestNameTD"           => "Külastaja nimi",
            //            "guestsGeneral" => "",
            //            "guestsRoom" => "",
            "maxGuest"                               => ["Maksimaalne inimeste arv"],
            "realGuestsInMaxGuestRe"                 => "Koguhind põhineb inimeste hulgal, kellele broneering tehti \(([^)]+)\)\.",
            "person"                                 => ["täiskasvanut", "täiskasvanu"],
            "child"                                  => ["last", "laps"],
            "Your reservation"                       => "Teie broneering",
            "Room"                                   => "Tuba",
            "room"                                   => "tuba",
            "Cancellation policy"                    => "Tühistamistingimused",
            "Total Price"                            => "Hind kokku",
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => "",
            //            "Details" => "",
            //            "welcome" => "",
            //            "taxVAT" => "",
            //            "taxCity" => "",
            //            "isConfirmed" => "",
            //            "confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => "TÜHISTATUD",
            //            "cancelledText" => "",
            "Cancellation cost" => "Tühistamistasu",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Broneeringu üksikasjad', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "zh" => [
            "(?<name>hotel)FromSubject" => "(?:感謝您|谢谢)！您在(?<name>.+)(?:的訂房已確認|的预订已确认)",

            "Confirmation Number:"    => ["预订编号", "確認函編號：", "确认订单号：", "订单确认号：", '订单编号'],
            "Check-in"                => ["入住时间", "入住時間", "入住日"],
            "Check-out"               => ["退房时间", "退房時間", "退房日"],
            "Show directions"         => ["如何抵达", "如何抵達"],
            "Address:"                => ["地址:", "地點"],
            "Phone:"                  => ["电话:", "电话", "電話:", "電話", '酒店电话'],
            "guestNameTD"             => ["预订者", "住客姓名"],
            "guestsGeneral"           => "客人人数",
            "guestsRoom"              => "最多可入住人數",
            "maxGuest"                => ["最多可入住人數", "最多入住人数"],
            "realGuestsInMaxGuestRe"  => "订单总价根据预订时选择的入住人数（([^）]+)）得出。",
            "person"                  => ["人", "位成人"],
            "child"                   => ["位孩童", "名儿童"],
            "Your reservation"        => ["您的预订", "您的預訂", "訂單內容"],
            //            "Room" => "Bed",
            "room"                => ["间客房", "間客房"],
            "Cancellation policy" => ["取消政策"],
            "Total Price"         => ["总价", "總價", "价格"],
            //            "Total by Discount" => "",
            //            "has made a reservation for you through"=>["has made a reservation for you through"],
            "Details" => ["客房细节", "特殊要求", "客房細節"],
            "welcome" => ["，感谢您的预订！", "親愛的"],
            "taxVAT"  => ["% 的增值税 。", " % 的增值稅 。", '% 的税费 。'],
            //            "taxCity" => "City tax per night is included",
            "isConfirmed" => ["已确认。", "您在成功的訂房已確認。"],
            "confirmed"   => "confirmed",
            //            "isCanceled" => "",
            "CANCELED"          => ["已取消"],
            "cancelledText"     => "您的订单已免费取消",
            "Cancellation cost" => ["预订取消费用", "取消費"],

            "Booked by" => "預訂者",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => ["預訂者", "预订对象"],
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "pl" => [
            "(?<name>hotel)FromSubject" => "Twoja rezerwacja w obiekcie (?<name>.+) jest potwierdzona",

            "Confirmation Number:"  => "Potwierdzenie rezerwacji nr:",
            "Check-in"              => "Zameldowanie",
            "Check-out"             => "Wymeldowanie",
            "Show directions"       => "Wyświetl wskazówki dojazdu",
            "Address:"              => "Lokalizacja",
            "Phone:"                => ["Telefon:", "Telefon"],
            "guestNameTD"           => "Nazwisko Gościa",
            "guestsGeneral"         => ["Liczba Gości", "Twoja grupa"],
            //            "guestsRoom" => "",
            "maxGuest"               => ["Maksymalna liczba gości"],
            "realGuestsInMaxGuestRe" => "Całkowita cena została wyliczona na podstawie ceny za zarezerwowaną liczbę gości \(([^\)]+)\)\.",
            "person"                 => ["osobę", "osoby", "dorosłych"],
            "child"                  => "dziec", //dziecko, dzieci
            "Your reservation"       => "Twoja rezerwacja",
            "Room"                   => ["Pokój", "Apartament"],
            "room"                   => ["pokój", "pokoje", "apartament"],
            "Cancellation policy"    => "Zasady odwołania rezerwacji",
            "Total Price"            => ["Cena", "Całkowity koszt"],
            "Total by Discount"      => "Kwota płatności",
            //            "has made a reservation for you through" => "",
            "Details" => "Informacje dotyczące obiektu",
            "welcome" => "Dziękujemy",
            "taxVAT"  => ["% VAT jest wliczony", "% VAT jest wliczono"],
            //			"taxCity" => "",
            "isConfirmed" => "została potwierdzona",
            "confirmed"   => "potwierdzona",
            //            "isCanceled" => "",
            "CANCELED" => ["ODWOŁANO"],
            //            "cancelledText" => "",
            //            "Cancellation cost" => "",

            "Booked by" => "Zarezerwowane przez",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            "Your loyalty information" => "Nr konta WizzAir",
            "Loyalty reward"           => "Nagrody lojalnościowe",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "bs" => [
            "(?<name>hotel)FromSubject" => "Hvala! Rezervacija je potvrđena: (?<name>.+)",

            "Confirmation Number:" => "Broj potvrde:",
            "Check-in"             => "Prijavljivanje",
            "Check-out"            => "Odjavljivanje",
            //			"Show directions" => "",
            "Address:"       => "Lokacija",
            "Phone:"         => ["Telefon:", "Telefon"],
            "guestNameTD"    => "Ime gosta",
            "guestsGeneral"  => "Vaša grupa",
            //            "guestsRoom" => "",
            "maxGuest" => ["Maksimalni kapacitet"],
            //            "realGuestsInMaxGuestRe" => "Ukupna cena se zasniva na ceni za izabrani broj gostiju \((.+?)\)\. ",
            "person"              => "odrasla",
            "child"               => "dete",
            "Your reservation"    => "Vaša rezervacija",
            "Room"                => "Soba",
            "room"                => "soba",
            "Cancellation policy" => "Pravila otkazivanja",
            "Total Price"         => "Ukupna cena",
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => "",
            "Details" => "Informacije o sobi",
            "welcome" => "Hvala",
            "taxVAT"  => "% PDV je obuhvaćen",
            //			"taxCity" => "",
            "isConfirmed" => "je potvrđen",
            "confirmed"   => "potvrđen",
            //            "isCanceled" => "",
            //            "CANCELED" => "",
            //            "cancelledText" => "",
            //            "Cancellation cost" => "",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Rezervisali ste za: ",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Podaci o rezervaciji', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "sv" => [
            "(?<name>hotel)FromSubject" => "Tack! Din bokning på (?<name>.+) är bekräftad",

            "Confirmation Number:"  => ["Bokningsnummer", "Bekräftelsenummer:"],
            "Check-in"              => "Incheckning",
            "Check-out"             => "Utcheckning",
            "Show directions"       => "Visa vägbeskrivning",
            "Address:"              => "Läge",
            "Phone:"                => ["Telefon:", "Telefon"],
            "guestNameTD"           => "Gästens namn",
            "guestsGeneral"         => "Antal gäster",
            //            "guestsRoom" => "",
            "maxGuest" => ["Maxkapacitet"],
            //            "realGuestsInMaxGuestRe" => "",
            "person"              => ["personer", "vuxna", "vuxen"],
            "child"               => "barn",
            "Your reservation"    => "Din bokning",
            "Room"                => "Rum",
            "room"                => "rum",
            "Cancellation policy" => "Avbokningsregler",
            "Total Price"         => "Totalkostnad",
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => "",
            //            "Details" => "",
            //			"welcome" => "",
            //			"taxVAT" => "",
            //			"taxCity" => "",
            //			"isConfirmed" => "",
            //			"confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => ["AVBOKAT"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Avbokningskostnad",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Du har bokat för",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "hu" => [
            "(?<name>hotel)FromSubject" => "Köszönjük![ ]*(?<name>.+?)[ ]*-[ ]*beli foglalása visszaigazolva",

            "Confirmation Number:"    => "Visszaigazolás száma:",
            "Check-in"                => "Bejelentkezés",
            "Check-out"               => "Kijelentkezés",
            "Show directions"         => "Útvonal megjelenítése",
            "Address:"                => "Elhelyezkedés",
            "Phone:"                  => ["Telefon:", "Telefon"],
            "guestNameTD"             => "Vendég neve",
            "guestsGeneral"           => "Vendégek száma",
            "guestsRoom"              => "Foglalási létszám",
            "maxGuest"                => "Maximális férőhelyek",
            "realGuestsInMaxGuestRe"  => "Ennyi a teljes összeg az Ön foglalásában szereplő létszámra \((.+)\)\.",
            "person"                  => ["felnőtt", "fő részére"],
            "child"                   => ["gyermek", "gyerek"],
            "Your reservation"        => "Az Ön foglalása",
            "Room"                    => "Szoba",
            "room"                    => ["szoba", "apartman", "ház"],
            "Cancellation policy"     => ["Előzetes fizetés", "Lemondási szabályzat"],
            "Total Price"             => ["Ár", 'Teljes ár'],
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => "",
            //            "Details" => "",
            "welcome"     => "Köszönjük",
            "taxVAT"      => "% idegenforgalmi adó - benne van az árban.",
            "taxCity"     => "idegenforgalmi adó tartózkodásonként",
            "isConfirmed" => "visszaigazolták",
            "confirmed"   => "visszaigazolták",
            //            "isCanceled" => "",
            "CANCELED"          => "LEMONDVA",
            //            "cancelledText" => "",
            "Cancellation cost" => "Lemondási díj",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "de" => [
            "(?<name>hotel)FromSubject" => "Danke! Ihre Buchung ist bestätigt: (?<name>.+)",

            "Confirmation Number:"    => ["Bestätigungsnummer", "Buchungsnummer:", "Bestätigungsnummer:"],
            "Check-in"                => ["Anreise", 'Ankunft', 'einchecken', 'Check-in'],
            "Check-out"               => ["Abreise", 'Checkout', 'Check-out'],
            "Show directions"         => "Wegbeschreibung anzeigen",
            "Address:"                => "Lage",
            "Phone:"                  => ["Telefon:", "Telefon"],
            "guestNameTD"             => "Name des Gastes",
            "guestsGeneral"           => "Ihre Gruppe",
            "guestsRoom"              => ["Anzahl der Gäste"],
            "maxGuest"                => ["Maximale Belegung"],
            "realGuestsInMaxGuestRe"  => "Ihr Gesamtpreis gilt für die von Ihnen gebuchte Anzahl an Gästen \(([^\)]+)\)\.",
            "person"                  => ["Person", "Erwachsene"],
            "child"                   => "Kind",
            "Your reservation"        => ["Ihre Buchung", 'Ihre Reservierung'],
            "Room"                    => "Zimmer",
            "room"                    => ["Zimmer", 'Apartment', 'Haus'],
            "Cancellation policy"     => "Stornierungsbedingungen",
            "Total Price"             => ["Preis", "Gesamtpreis", "Gesamtpreis für dieses Zimmer:", "Gezahlter Betrag"],
            "Total by Discount"       => "Zahlungsbetrag",
            //            "has made a reservation for you through" => "",
            "Details" => ["Zimmerdetails:", "Zimmerdetails"],
            "welcome" => ["Vielen Dank", "Hallo", 'Sehr geehrte(r)'],
            "taxVAT"  => "% Mehrwertsteuer ist inbegriffen",
            //			"taxCity" => "",
            "isConfirmed" => "ist bestätigt",
            "confirmed"   => "bestätigt",
            //            "isCanceled" => "",
            "CANCELED"          => "STORNIERT",
            //            "cancelledText" => "",
            "Cancellation cost" => "Stornierungsgebühren",

            "Booked by"      => "Gebucht von",
            'Check-in time'  => 'Check-in-Zeit',
            'Check-out time' => 'Check-out-Zeit',
            //            "Getting into the property:" => "",
            "You booked for" => "Sie haben gebucht für",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Buchungsinformationen', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ro" => [
            "(?<name>hotel)FromSubject" => "(?:Vă mulţumim! Rezervarea dumneavoastră|Mulţumim! Rezervarea ta) la (?<name>.+) este confirmată",

            "Confirmation Number:"  => ["Numărul confirmării:", "Confirmare:"],
            "Check-in"              => "Check-in",
            "Check-out"             => "Check-out",
            "Show directions"       => "Arată instrucţiuni de călătorie",
            "Address:"              => "Amplasare",
            "Phone:"                => ["Telefon:", "Telefon"],
            "guestNameTD"           => "Numele clientului",
            "guestsGeneral"         => "Numărul oaspeţilor",
            //            "guestsRoom" => "",
            "maxGuest" => ["Capacitate maximă"],
            //            "realGuestsInMaxGuestRe" => "",
            "person"                                 => ["persoană", "adulți", "adult"],
            "child"                                  => ["copii", "copil"],
            "Your reservation"                       => "Rezervarea dvs.",
            "Room"                                   => "Cameră",
            "room"                                   => ["cameră", "chalet", "apartament", "camere"],
            "Cancellation policy"                    => "Politica de anulare",
            "Total Price"                            => ["Preț", "Costuri totale", "Costuri totale"],
            //            "Total by Discount" => "",
            "has made a reservation for you through" => ["NOTTRANSLATED"],
            "Details"                                => ["Detaliile camerei", "Detaliile unităţii de cazare"],
            "welcome"                                => "Vă mulțumim",
            "taxVAT"                                 => " %TVA",
            "taxCity"                                => "NOTTRANSLATED",
            //			"isConfirmed" => "",
            //			"confirmed" => "",
            "isCanceled"        => "Rezervarea dumneavoastră a fost anulată gratuit",
            "CANCELED"          => "ANULATĂ",
            //            "cancelledText" => "",
            "Cancellation cost" => "Taxă de anulare",

            "Booked by" => "Rezervat de",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Ați rezervat pentru",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "fi" => [
            "(?<name>hotel)FromSubject" => "Kiitos! Varauksesi on vahvistettu – (?<name>.+)",

            "Confirmation Number:"  => ["Varausnumero:", "Vahvistusnumero:"],
            "Check-in"              => "Tulopäivä",
            "Check-out"             => "Lähtöpäivä",
            "Show directions"       => "Näytä reittiohjeet",
            "Address:"              => "Sijainti",
            "Phone:"                => ["Puhelin:", "Puhelin"],
            "guestNameTD"           => "Asiakas",
            "guestsGeneral"         => ["Ryhmänne", "Henkilömäärä"],
            "guestsRoom"            => "Kokonaishinta on hinta varauksesi henkilömäärältä \(([^\)]+)\)\.",
            "maxGuest"              => ["Henkilöiden maksimimäärä", "Majoittujien enimmäismäärä"],
            //            "realGuestsInMaxGuestRe" => "",
            "person"           => ["aikuista", "henkilöä", "aikuinen"],
            "child"            => ["lapsi", "lasta"],
            "Your reservation" => "Varauksesi",
            //			"Room" => "Cameră",
            "room"                                   => ["huone"],
            "Cancellation policy"                    => "Peruutusehdot",
            "Total Price"                            => ["Hinta", "Kokonaishinta"],
            //            "Total by Discount" => "",
            "has made a reservation for you through" => ["NOTTRANSLATED"],
            "Details"                                => ["Huoneen tiedot"],
            "welcome"                                => "Kiitos",
            //			"taxVAT" => "",
            //			"taxCity" => "",
            "isConfirmed" => "on vahvistettu",
            "confirmed"   => "vahvistettu",
            //            "isCanceled" => "",
            "CANCELED"          => ["PERUTTU"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Peruutusmaksu",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "cs" => [
            "(?<name>hotel)FromSubject" => "🛄 (?<name>.+) – Děkujeme! Vaše rezervace je potvrzena",

            "Confirmation Number:"  => ["Číslo rezervace:", "Potvrzení rezervace:"],
            "Check-in"              => "Příjezd",
            "Check-out"             => "Odjezd",
            "Show directions"       => "Ukázat popis cesty",
            "Address:"              => "Místo",
            "Phone:"                => ["Telefon:", "Telefon"],
            "guestNameTD"           => "Jméno hosta",
            "guestsGeneral"         => "Počet hostů",
            //            "guestsRoom" => "",
            "maxGuest"                               => ["Maximální kapacita"],
            "realGuestsInMaxGuestRe"                 => "Vaše celková cena vychází z ceny pokoje pro uvedený počet hostů \(([^\)]+)\)\.",
            "person"                                 => ["osoba", "dospělý", "dospělí"],
            "child"                                  => "dítě",
            "Your reservation"                       => "Vaše rezervace",
            "Room"                                   => "Pokoj",
            "room"                                   => ["pokoj"],
            "Cancellation policy"                    => "Podmínky zrušení rezervace",
            "Total Price"                            => ["Celková cena", "Cena"],
            //            "Total by Discount" => "",
            "has made a reservation for you through" => ["NOTTRANSLATED"],
            "Details"                                => ["Pokoj"],
            //			"welcome" => "",
            //			"taxVAT" => "",
            //			"taxCity" => "",
            //			"isConfirmed" => "",
            //			"confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => ["ZRUŠENO"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Poplatek za zrušení rezervace",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "it" => [
            "(?<name>hotel)FromSubject" => "Grazie! La tua prenotazione per (?<name>.+) è confermata",

            "Confirmation Number:"  => ["Numero di conferma:", "Conferma:"],
            "Check-in"              => "Arrivo",
            "Check-out"             => "Partenza",
            "Show directions"       => "Mostra percorso",
            "Address:"              => ["Posizione", "Indirizzo:"],
            "Phone:"                => ["Telefono:", "Telefono"],
            "guestNameTD"           => "Nome dell'ospite",
            //            "guestsGeneral" => "",
            "guestsRoom"                             => ["Numero ospiti"],
            "maxGuest"                               => ["Capienza massima"],
            "realGuestsInMaxGuestRe"                 => "Il prezzo totale che paghi si basa sulla tariffa per il numero di ospiti per cui hai prenotato \(([^\)]+)\)\.",
            "person"                                 => ["persona", "adult", "ospiti"],
            "child"                                  => "bambin",
            "Your reservation"                       => "La tua prenotazione",
            "Room"                                   => "Camera",
            "room"                                   => ["camera", "camere", "appartamento", "casa"],
            "Cancellation policy"                    => "Condizioni di cancellazione",
            "Total Price"                            => ["Prezzo", "Importo totale"],
            "Total by Discount"                      => "Importo del pagamento",
            "has made a reservation for you through" => ["NOTTRANSLATED"],
            "Details"                                => ["Dettagli", "Dettagli sulle camere"], //Dettagli sulle camere; Dettagli sulla struttura
            "welcome"                                => ["Grazie", "Ciao"],
            "taxVAT"                                 => "% IVA è incluso",
            //			"taxCity" => "",
            "isConfirmed" => "è confermata",
            "confirmed"   => "confermata",
            //            "isCanceled" => "",
            "CANCELED"          => ["CANCELLATA"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Costi di cancellazione",

            "Booked by" => "Prenotazione effettuata da",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Hai prenotato per",
            //            "is confirmed." => "",
            "Your loyalty information" => "I tuoi dati",
            "Loyalty reward"           => "Premio fedeltà",
            "Reservation details"      => 'Dati della prenotazione', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "el" => [ // it-11210836.eml
            "(?<name>hotel)FromSubject" => "Ευχαριστούμε! Η κράτηση στο (?<name>.+) επιβεβαιώθηκε",

            "Confirmation Number:"  => ["Αριθμός επιβεβαίωσης:", "Επιβεβαίωση:"],
            "Check-in"              => "Check-in",
            "Check-out"             => "Check-out",
            "Show directions"       => "Προβολή οδηγιών",
            "Address:"              => "Τοποθεσία",
            "Phone:"                => ["Τηλέφωνο:", "Τηλέφωνο"],
            "guestNameTD"           => "Όνομα επισκέπτη",
            "guestsGeneral"         => ["Αριθμός επισκεπτών", "Κάνατε κράτηση για"],
            //            "guestsRoom" => "",
            "maxGuest"               => "Μέγιστη χωρητικότητα",
            "realGuestsInMaxGuestRe" => "Η συνολική τιμή σας βασίζεται στην τιμή για τον αριθμό των επισκεπτών που έχουν κάνει κράτηση \((.+)\)\.",
            "person"                 => ["άτομα", "ενήλικες"], //,"adult"
            "child"                  => "παιδί",
            "Your reservation"       => "Η κράτησή σας",
            "Room"                   => ["ιδιωτικό μπάνιο"],
            "room"                   => ["δωμάτιο", "κατοικία"],
            "Cancellation policy"    => "Πολιτική ακύρωσης",
            "Total Price"            => ["Τιμή", "Συνολική τιμή"],
            //            "Total by Discount" => "",
            //"has made a reservation for you through"=>["has made a reservation for you through"],
            "Details"     => ["Στοιχεία δωματίου", "Στοιχεία Καταλύματος"],
            "welcome"     => "Ευχαριστούμε",
            "taxVAT"      => "% ΦΠΑ περιλαμβάνεται",
            "taxCity"     => ["City tax per night is included", "Δημοτικός φόρος περιλαμβάνεται"],
            "isConfirmed" => ["επιβεβαιώθηκε"],
            "confirmed"   => "confirmed",
            //            "isCanceled" => "",
            //            "CANCELED" => "",
            //            "cancelledText" => "",
            "Cancellation cost" => "Ακυρωτικά",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ru" => [
            "(?<name>hotel)FromSubject" => "Спасибо! Ваше бронирование в (?<name>.+) подтверждено",

            "Confirmation Number:"          => ["Номер бронирования:", "Номер бронирования", "Номер подтверждения:"],
            "Check-in"                      => ["Регистрация заезда", "Заезд"],
            "Check-out"                     => ["Регистрация отъезда", "Отъезд"],
            "Show directions"               => "Показать маршрут проезда",
            "Address:"                      => ["Адрес:", "Месторасположение"],
            "Phone:"                        => ["Телефон:", "Телефон"],
            "guestNameTD"                   => "Имя гостя",
            "guestsGeneral"                 => "Забронировано для",
            "guestsRoom"                    => "Число гостей",
            "maxGuest"                      => "Максимальная вместимость",
            "realGuestsInMaxGuestRe"        => "Итоговая цена указана для (.+?)(?: и выбранного тарифа)?\.",
            "person"                        => ["человек", "взрослых", "взрослый"], //,"adult"
            "child"                         => ["ребенок", "ребенка", "детей"],
            "Your reservation"              => ["Ваше бронирование", "Сведения о бронировании"],
            "Room"                          => ["Номер", "Апартаменты", "номер"],
            "room"                          => ["номер", "апартаменты", "дом", "кровать в общем номере", "апартаментов", "вилла"],
            "Cancellation policy"           => "Порядок отмены бронирования",
            "Total Price"                   => ["Общая стоимость", "Оплачено", 'Цена'],
            "Total by Discount"             => ["Сумма платежа"],
            "Booking details"               => ["Детали бронирования"],
            //"has made a reservation for you through"=>["has made a reservation for you through"],
            "Details"     => ["Информация о номере"],
            "welcome"     => ["Спасибо", "Отличные новости"],
            "taxVAT"      => "% входит в стоимость",
            "taxCity"     => "NOTTRANSLATED",
            "isConfirmed" => ["подтверждено"],
            "confirmed"   => "подтверждено",
            //            "isCanceled" => "",
            "CANCELED"          => ["ОТМЕНЕНО"],
            //            "cancelledText" => "",
            "Cancellation cost" => ["Стоимость отмены бронирования", "Стоимость отмены"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            "Getting into the property:" => "Как попасть в объект размещения:",
            "You booked for"             => "Забронировано для",
            //            "is confirmed." => "",
            "Your loyalty information" => "Ваши данные",
            "Loyalty reward"           => "Вознаграждение по программе лояльности",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "uk" => [
            "(?<name>hotel)FromSubject" => "Дякуємо! Ваше бронювання в (?<name>.+) підтверджено",

            "Confirmation Number:"    => ["Номер підтвердження:", "Номер бронювання", "Підтвердження бронювання:"],
            "Check-in"                => "Заїзд",
            "Check-out"               => "Виїзд",
            "Show directions"         => "Показати маршрут",
            "Address:"                => ["Адрес:", "Розташування"],
            "Phone:"                  => ["Телефон:", "Телефон"],
            "guestNameTD"             => "Ім'я гостя",
            "guestsGeneral"           => "Ви забронювали для",
            "guestsRoom"              => ["Кількість гостей"],
            "maxGuest"                => "Максимальна місткість",
            "realGuestsInMaxGuestRe"  => "Підсумкова ціна вказана для заброньованої кількості гостей \(([^\)]+)\)\.",
            "person"                  => ["дорослих", "дорослий", "людини"], //,"adult"
            "child"                   => ["дитина", "дітей"],
            "Your reservation"        => "Ваше бронювання",
            "Room"                    => ["Номер", "Апартаменты"],
            "room"                    => ["номер"],
            "Cancellation policy"     => "Порядок ануляції бронювання",
            "Total Price"             => ["Загальна сума", "Загальна ціна", "Ціна"],
            //            "Total by Discount" => "",
            //"has made a reservation for you through"=>["has made a reservation for you through"],
            "Details"     => ["Дані готельного об'єкта", "Інформація про номер"],
            "welcome"     => "Дякуємо",
            "taxVAT"      => ["ПДВ 7 % входить у ціну.", "ПДВ 20 % входить у ціну."],
            "taxCity"     => "NOTTRANSLATED",
            "isConfirmed" => ["підтверджено"],
            "confirmed"   => "підтверджено",
            //            "isCanceled" => "",
            //            "CANCELED" => ["ОТМЕНЕНО"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Вартість скасування",

            "Booked by" => "Заброньовано",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Ви забронювали для",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Special wishes" => "Особливі побажання",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "nl" => [ // it-2249763.eml
            "(?<name>hotel)FromSubject" => "Bedankt! Je boeking bij (?<name>.+) is bevestigd.",

            "Confirmation Number:"  => ["Bevestigingsnummer:", "Boekingsnummer"],
            "Check-in"              => ["Aankomst", "Inchecken"],
            "Check-out"             => ["Vertrek", "Uitchecken"],
            "Show directions"       => "Toon routebeschrijving",
            "Address:"              => "Locatie",
            "Phone:"                => ["Telefoon:", "Telefoon"],
            "guestNameTD"           => ["Naam reiziger", "Naam gast"],
            "guestsGeneral"         => ["Je hebt geboekt voor", "Aantal gasten"],
            //            "guestsRoom" => "",
            "maxGuest"               => "Maximumcapaciteit",
            "realGuestsInMaxGuestRe" => "De totaalprijs is gebaseerd op de prijs voor het aantal geboekte gasten \(([^\)]+)\)\.",
            "person"                 => ["personen", "volwassene"],
            "child"                  => ["kind", "child"],
            "Your reservation"       => ["Uw reservering", "Je reservering", "Boekingsgegevens"],
            "Room"                   => "Kamer",
            "room"                   => ["villa's", "kamer", "appartement"],
            "Cancellation policy"    => ["Annuleringsvoorwaarden", "Annuleringskosten"],
            "Total Price"            => ["Totaalprijs", "Totaalprijs voor deze kamer:", "Je hebt betaald"],
            "Total by Discount"      => "Te betalen bedrag",
            "Check-in time"          => "Inchecktijd",
            "Check-out time"         => "Uitchecktijd",
            //            "has made a reservation for you through"=>["has made a reservation for you through"],
            "Details"     => ["Gegevens van de accommodatie", "Kamerinformatie"],
            "welcome"     => ["Bedankt", "Beste"],
            "taxVAT"      => ["% Belasting is inbegrepen", "% BTW is inbegrepen."],
            "taxCity"     => "NOTTRANSLATED",
            "isConfirmed" => ["is bevestigd"],
            "confirmed"   => "confirmed",
            //            "isCanceled" => "",
            "CANCELED"          => ["CANCELED", "CANCELLED", "GEANNULEERD"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Annuleringskosten",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Je hebt geboekt voor",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            "You paid"                   => "Je hebt betaald", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            "At the property you'll pay" => "Bij de accommodatie betaal je",
        ],
        "es" => [
            "(?<name>hotel)FromSubject" => "¡Gracias! Tu reserva en el (?<name>.+) está confirmada",

            "Confirmation Number:" => [
                "Número de confirmación:",
                "Número de reserva",
                'Número de reserva:',
                'Confirmación:',
                'Número de confirmación de eDreams Prime',
            ],
            "Check-in"         => ["Entrada", "Llegada"],
            "Check-out"        => "Salida",
            "Show directions"  => "Mostrar itinerario",
            "Address:"         => ["Dirección:", "Ubicación"],
            "Phone:"           => ["Teléfono:", "Teléfono"],
            "guestNameTD"      => ["Nombre del huésped", "Nombre del cliente", "Huéspedes"],
            "guestsGeneral"    => ["Número de huéspedes", "Número de personas", "Reservaste para"],
            //            "guestsRoom" => "",
            "maxGuest"                               => ["Capacidad máxima", "Huéspedes"],
            "realGuestsInMaxGuestRe"                 => "El precio total se basa en la tarifa para (?:la cantidad de huéspedes que figuran en la reserva|el número de personas que has reservado) \(([^\)]+)\)\.",
            "person"                                 => ["adulto", "persona"],
            "child"                                  => "niño",
            "Your reservation"                       => ["Tu reserva", "Datos de la reserva", "Preferencias", "Tu reserva"],
            "Room"                                   => "Habitación",
            "room"                                   => ["habitación", "apartamento", "habitaciones", "cama en dormitorio", "casa", "unidad"],
            "Cancellation policy"                    => ["Condiciones de cancelación", "Cancelación gratuita"],
            "Total Price"                            => ["Precio total", "Precio", "Has pagado"],
            'Discount'                               => 'Cupón aplicado (EPRIME% de descuento)',
            //"Total by Discount" => "",
            "has made a reservation for you through" => ["has made a reservation for you through"],
            "Details"                                => ["Información de la habitación", "Datos del establecimiento", "Detalles de la unidad"],
            "welcome"                                => ["¡Gracias", "Gracias", "Hola"],
            "taxVAT"                                 => ["%) incluido", "% IVA incluido", 'Impuestos y tasas'],
            //            "taxCity" => "City tax per night is included",
            "isConfirmed" => ["reserva está confirmada", "está confirmada"],
            "confirmed"   => "confirmada",
            //            "isCanceled" => "",
            "CANCELED"          => ["CANCELADA"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Cargos de cancelación",

            "Booked by" => "Reserva realizada por",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "Cómo acceder al alojamiento:",
            "You booked for" => ["Reservaste para"],
            //            "is confirmed." => "",
            "Your loyalty information" => "Tus datos",
            "Loyalty reward"           => "Programa de puntos",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "is" => [
            "(?<name>hotel)FromSubject" => "Takk! Bókun þín á (?<name>.+) er staðfest",

            "Confirmation Number:" => ["Staðfestingarnúmer:", "Bókunarnúmer"],
            "Check-in"             => "Innritun",
            "Check-out"            => "Útritun",
            //            "Show directions" => "",
            "Address:"       => ["Staðsetning"],
            "Phone:"         => "Sími:",
            "guestNameTD"    => "Nafn gests",
            "guestsGeneral"  => ["Fjöldi gesta", "Gestir"],
            //            "guestsRoom" => "",
            //            "maxGuest" => "",
            //            "realGuestsInMaxGuestRe" => "",
            "person"              => ["gestir", 'fullorðnir'],
            "child"               => "börn",
            "Your reservation"    => "Pöntunin þín",
            "Room"                => "Herbergi",
            "room"                => ["bústaður", "herbergi"],
            "Cancellation policy" => "Afpöntunarskilmálar",
            "Total Price"         => ["Heildarverð", "Upphæð til greiðslu"],
            //            "Total by Discount" => "",
            //            "has made a reservation for you through"=>[""],
            "Details" => ["Upplýsingar um gistirýmið"],
            "welcome" => ["Takk"],
            "taxVAT"  => "% VSK er innifalinn",
            //            "taxCity" => "City tax per night is included",
            "isConfirmed" => ["er staðfest"],
            "confirmed"   => "staðfest",
            //            "isCanceled" => "",
            "CANCELED"          => ["AFPANTAÐ"],
            "cancelledText"     => "Bókunin var afpöntuð því trygging var ekki greidd",
            "Cancellation cost" => "Kostnaður vegna afpöntunar",

            //                        "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //                        "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Upplýsingar um bókun', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "th" => [
            // ขอบคุณ การจองที่ Chiang Dao Reset ได้รับการยืนยันแล้ว -
            "(?<name>hotel)FromSubject" => "ขอบคุณ การจองที่ (?<name>.+?) ได้รับการยืนยันแล้ว",

            "Confirmation Number:"  => ["หมายเลขยืนยันการจอง:"],
            "Check-in"              => "เช็คอิน",
            "Check-out"             => "เช็คเอาท์",
            "Show directions"       => "แสดงเส้นทาง",
            "Address:"              => ["ที่ตั้ง"],
            "Phone:"                => "โทรศัพท์:",
            "guestNameTD"           => "ชื่อผู้เข้าพัก",
            "guestsGeneral"         => "จำนวนผู้เข้าพัก",
            //            "guestsRoom" => "",
            //            "maxGuest" => "",
            //            "realGuestsInMaxGuestRe" => "",
            "person" => ["ท่าน"],
            //            "child" => "",
            "Your reservation"    => "การสำรองห้องพักของท่าน",
            "Room"                => "ห้อง",
            "room"                => ["ห้อง"],
            "Cancellation policy" => "นโยบายการยกเลิก",
            "Total Price"         => ["ราคารวม", "ราคา", 'ราคารวม'],
            //            "Total by Discount" => "",
            //            "has made a reservation for you through"=>[""],
            "Details" => ["รายละเอียดห้องพัก"],
            "welcome" => ["ขอบคุณคุณ"],
            "taxVAT"  => "รวมภาษีมูลค่าเพิ่ม (VAT)",
            //            "taxCity" => "City tax per night is included",
            //            "isConfirmed" => [""],
            "confirmed" => "confirmed",
            //            "isCanceled" => "",
            "CANCELED"          => ["ยกเลิกแล้ว"],
            "cancelledText"     => "ยกเลิกการจองของท่านเรียบร้อยแล้วโดยไม่มีค่าธรรมเนียม",
            "Cancellation cost" => ["ค่าธรรมเนียมการยกเลิก", "ค่าธรรมเนียมยกเลิก"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "pt" => [
            "(?<name>hotel)FromSubject" => "(?:S|A s)ua reserva em\s+(?<name>.{3,}?)\s+está confirmada",

            "Confirmation Number:" => [
                "Número de confirmação:",
                "Número da reserva",
                "Número da reserva:",
                "Número da confirmação:",
                "Confirmação:",
            ],
            "Check-in"         => ["Entrada", "Check-in"],
            "Check-out"        => ["Saída", "Check-out"],
            "Show directions"  => ["Mostrar direcções", "Ver rota para a propriedade"],
            "Address:"         => ["Endereço:", "Localização"],
            "Phone:"           => ["Telefone:", "Telefone"],
            "guestNameTD"      => ["Nome do hóspede", "Nome do hóspede:"],
            "guestsGeneral"    => ["Número de hóspedes"],
            //            "guestsRoom" => "",
            "maxGuest"                               => "Capacidade máxima",
            "realGuestsInMaxGuestRe"                 => "(?:O(?: seu) preço total baseado na tarifa que reservou para o seu número de hóspedes|O preço total corresponde ao número de hóspedes na reserva|O preço total corresponde à tarifa cobrada para o número reservado de hóspedes) \(([^\)]+)\)\.",
            //                                                                                                                                                                                                        O preço total corresponde à tarifa cobrada para o número reservado de hóspedes (2 adultos, 2 crianças). Pode haver cobranças adicionais para hóspedes extras - até a capacidade máxima.
            "person"                                 => ["hóspedes", "pessoas", "adulto"], //ызрослые
            "child"                                  => "criança",
            "Your reservation"                       => ["Sua reserva", "A sua reserva"],
            "Room"                                   => ["Quarto", "Apartamento"],
            "room"                                   => ["quarto", "Quarto", "apartamento", "chalé", "bangalô", "casa", "villa"],
            "Cancellation policy"                    => "Condições de cancelamento",
            "Total Price"                            => ["Preço", "Preço total"],
            "Total by Discount"                      => "Valor do pagamento",
            "has made a reservation for you through" => ["has made a reservation for you through"],
            "Details"                                => ["Informação sobre o quarto", "Informação do quarto", "Detalhes deste meio de hospedagem"],
            "welcome"                                => ["Obrigado", "Olá"],
            "taxVAT"                                 => "% incluído",
            "taxCity"                                => "imposto municipal por pessoa, por noite incluído(a)",
            "isConfirmed"                            => ["Sua confirmação de reserva", "sua reserva está agora confirmada"],
            "confirmed"                              => "confirmada",
            "isCanceled"                             => "Sua reserva está cancelada",
            "CANCELED"                               => ["CANCELADA", "cancelada"],
            //            "cancelledText" => "",
            "Cancellation cost"                      => ["Custos de cancelamento", "Custos de Cancelamento"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            "Getting into the property:" => "Como entrar na acomodação:",
            "You booked for"             => ["Sua é reserva é para", 'Reservou para'],
            //            "is confirmed." => "",
            "Your loyalty information" => "Seus dados",
            "Loyalty reward"           => "Bônus de fidelidade",
            "Reservation details"      => 'Detalhes da reserva', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ca" => [ // it-20391082.eml
            "(?<name>hotel)FromSubject" => "Gràcies! La reserva està confirmada: (?<name>.+)",

            "Confirmation Number:"  => ["Número de confirmació:", "Confirmació:"],
            "Check-in"              => "Entrada",
            "Check-out"             => "Sortida",
            "Show directions"       => "Mostra l'itinerari",
            "Address:"              => "Situació",
            "Phone:"                => ["Telèfon:", "Telèfon"],
            "guestNameTD"           => "Client",
            //            "guestsGeneral" => "",
            //            "guestsRoom" => ,
            "maxGuest"               => ["Capacitat màxima", "Capacitat màx."],
            "realGuestsInMaxGuestRe" => "El preu total es basa en la tarifa per al nombre de persones que has reservat \(([^\)]+)\)\.",
            "person"                 => ["person", "adult"], // adult
            //			"child" => "", // child
            "Your reservation" => "La teva reserva",
            //			"Room" => "",
            "room"                => ["habitacion", "habitació"],
            "Cancellation policy" => "Condicions de cancel·lació",
            "Total Price"         => ["Preu total"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            "Details" => ["Informació de l'habitació"],
            "welcome" => "Gràcies",
            "taxVAT"  => "% IVA inclòs.",
            //			"taxCity" => "",
            "isConfirmed" => ["està confirmada"],
            "confirmed"   => "confirmada",
            //            "isCanceled" => "",
            "CANCELED"          => "S'HA CANCEL·LAT",
            //            "cancelledText" => "",
            "Cancellation cost" => "Càrrec de cancel·lació",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Reserva per a",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "da" => [
            "(?<name>hotel)FromSubject" => "Tak! Din booking hos (?<name>.+) er bekræftet",

            "Confirmation Number:" => [
                "Reservationsnummer:",
                "Reservationsnummer",
                "booking.com reservationsnummer",
                "Bekræftelsesnummer:",
            ],
            "Check-in"         => "Indtjekning",
            "Check-out"        => "Udtjekning",
            "Show directions"  => ["Skriv til overnatningsstedet", "Vis rutebeskrivelse"],
            "Address:"         => ["Adresse:", "Adresse", "Adresse :", "Beliggenhed"],
            "Phone:"           => ["Telefon:", "Telefon"],
            "guestNameTD"      => "Gæster",
            "guestsGeneral"    => "Antal gæster",
            //            "guestsRoom" => "",
            "maxGuest"               => "Maks. antal gæster",
            "realGuestsInMaxGuestRe" => "Den samlede pris er baseret på det bookede antal gæster \(([^\)]+)\)\.",
            "person"                 => ["personer", "voksne", 'voksen'], // adult
            "child"                  => "børn", // child
            "Your reservation"       => ["Din reservation", "Din booking"],
            "Room"                   => ["Værelse", "Villa"],
            "room"                   => ["værels"],
            "Cancellation policy"    => "Afbestillingsregler",
            "Total Price"            => ["Pris", "Samlet pris"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            //			"Details" => [""],
            "welcome" => "Tak",
            "taxVAT"  => "% er medregnet.",
            //			"taxCity" => "",
            "isConfirmed" => ["er bekræftet"],
            "confirmed"   => "bekræftet",
            //            "isCanceled" => "",
            "CANCELED"          => "AFBESTILT",
            "cancelledText"     => "Din booking er nu blevet afbestilt gratis",
            "Cancellation cost" => "Pris for afbestilling",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => ["Du har booket til"],
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Bookingoplysninger', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ja" => [
            "(?<name>hotel)FromSubject" => "🛄 (?<name>.+)の予約が確定しました！",
            //                        🛄 エアロテル T3 ロンドン ヒースロー の予約が確定しました！

            "Confirmation Number:"    => ["予約番号："],
            "Check-in"                => "チェックイン",
            "Check-out"               => "チェックアウト",
            "Show directions"         => "道順を表示する",
            "Address:"                => ["ロケーション"],
            "Phone:"                  => "電話",
            "guestNameTD"             => "宿泊者氏名",
            "guestsGeneral"           => ["宿泊者数", "宿泊者"],
            "guestsRoom"              => "人数",
            "maxGuest"                => "最大宿泊人数",
            "realGuestsInMaxGuestRe"  => "合計宿泊料金は、予約された宿泊人数（([^）]+)）",
            "person"                  => ["名", "大人"],
            //			"child" => "child",
            "Your reservation"    => "ご予約",
            "Room"                => "客室",
            "room"                => ["部屋", "部屋"],
            "Cancellation policy" => "キャンセルポリシー",
            "Total Price"         => ["合計料金", "料金"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            "Details" => ["客室の詳細"],
            "welcome" => ["さん、 ありがとうございます！"],
            "taxVAT"  => "/VAT",
            //			"taxCity" => "",
            "isConfirmed" => ["宿泊予約が完了しました。"],
            "confirmed"   => "confirmed",
            //            "isCanceled" => "",
            "CANCELED"          => ["キャンセル済み"],
            //            "cancelledText" => "",
            "Cancellation cost" => "キャンセル料",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "宿泊者の内訳",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => '予約内容', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "he" => [
            "(?<name>hotel)FromSubject" => "דה! ההזמנה שלכם ב-(?<name>.+) מאושרת",

            "Confirmation Number:" => ["מספר אישור הזמנה", "מספר אישור הזמנה:"],
            "Check-in"             => "צ'ק-אין",
            "Check-out"            => "צ'ק-אאוט",
            //			"Show directions" => "Show directions",
            "Address:"    => ["מיקום"],
            "Phone:"      => ["טלפון", "טלפון:"],
            "guestNameTD" => "שם האורח",
            //            "guestsGeneral" => "",
            //            "guestsRoom" => "",
            //            "maxGuest" => "",
            //            "realGuestsInMaxGuestRe" => "",
            //			"person" => [""],
            //			"child" => "child",
            "Your reservation"    => "ההזמנה שלם",
            "Room"                => "חדר",
            "room"                => ["חדרים", "חדר"],
            "Cancellation policy" => "מדיניות הביטול",
            "Total Price"         => ["מחיר", "מחיר כולל"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            //			"Details" => ["Details", "Room details"],
            "welcome" => ["תודה "],
            //			"taxVAT" => "",
            //			"taxCity" => "",
            "isConfirmed" => ["הזמנתכם בלקו מאושרת"],
            "confirmed"   => "confirmed",
            //            "isCanceled" => "",
            "CANCELED"          => ["בוטלה"],
            //            "cancelledText" => "",
            "Cancellation cost" => "עלות הביטול",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ar" => [
            "(?<name>hotel)FromSubject" => "شكراً، تم تأكيد حجزك في (?<name>.+)",

            "Confirmation Number:" => ["رقم تأكيد الحجز:"],
            "Check-in"             => "تسجيل الوصول",
            "Check-out"            => "تسجيل المغادرة",
            //			"Show directions" => "",
            "Address:"       => "الموقع",
            "Phone:"         => ["الهاتف:", "الهاتف"],
            "guestNameTD"    => "الضيوف",
            "guestsGeneral"  => "عدد النزلاء",
            //            "guestsRoom" => "",
            "maxGuest"               => "العدد الأقصى",
            "realGuestsInMaxGuestRe" => "مبني على السعر لعدد الضيوف الذين حجزت لهم \((.+)\)\.",
            "person"                 => ["شخص", "بالغين", "أشخاص بالغين"],
            "child"                  => "أطفال",
            "Your reservation"       => "حجزكم",
            //			"Room" => "",
            "room"                => ["غرفة"],
            "Cancellation policy" => "سياسة الإلغاء",
            "Total Price"         => ["السعر"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            "Details" => ["تفاصيل الغرف", "معلومات عن مكان الإقامة"],
            "welcome" => ["عزيزنا السيد", "شكراً"],
            "taxVAT"  => "قيمة ضريبة القيمة المضافة ستكون مشمولة",
            //			"taxCity" => "",
            "isConfirmed" => ["تأكيد"],
            //			"confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => "تم الإلغاء",
            "cancelledText"     => "تم إلغاء حجزك مجانًا بنجاح",
            "Cancellation cost" => ["رسوم  إلغاء الحجز", "رسوم إلغاء الحجز"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "حجزت لعدد ضيوف يبلغ",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "fr" => [ // it-27947689.eml, it-27947687.eml
            "(?<name>hotel)FromSubject" => "Merci\s+!\s+Votre\s*réservation\s+à\s+l(?:'|\&\#039;)établissement\s+(?<name>.+)\s+est\s+confirmée",

            "Confirmation Number:"  => ["Numéro de réservation:", "Numéro de réservation :", "Confirmation :"],
            "Check-in"              => ["Arrivée", "Date d’arrivée"],
            "Check-out"             => ["Départ", "Date de départ"],
            "Show directions"       => "Voir l'itinéraire",
            "Address:"              => ["Adresse :", "Situation géographique"],
            "Phone:"                => ["Téléphone:", "Téléphone :", "Téléphone", "Numéro de téléphone :"],
            "guestNameTD"           => ["Clients", "Nom du client"],
            "guestsGeneral"         => ["Nombre de clients"],
            //            "guestsRoom" => "",
            "maxGuest"               => "Capacité maximum",
            "realGuestsInMaxGuestRe" => "(?:Le montant total correspond au nombre de personnes indiqué lors de votre réservation|Le tarif total correspond au nombre de personnes figurant sur la réservation) \(([^\)]+)\)\.",
            "person"                 => ["personne", "adulte"],
            "child"                  => ["child", "enfant"],
            "Your reservation"       => ["Votre réservation"],
            "Room"                   => "Chambre",
            "room"                   => ["chambre", "appartement", "villa"],
            "Cancellation policy"    => "Conditions d'annulation",
            "Total Price"            => ["Montant total", "Tarif", "Vous avez payé"],
            "Total by Discount"      => "Montant du paiement",
            //			"has made a reservation for you through"=>[""],
            "Details" => ["Descriptif de la chambre", "Informations sur l'hébergement", "Détail du tarif"],
            "welcome" => ["Bonjour", "Merci"],
            "taxVAT"  => "% de TVA",
            //			"taxCity" => "",
            "isConfirmed" => ["est désormais confirmée", "est confirmée"],
            "confirmed"   => "confirmée",
            //            "isCanceled" => "",
            "CANCELED"               => ["ANNULÉE"],
            "cancelledText"          => ["votre réservation a été annulée"],
            "Cancellation cost"      => "Frais d'annulation",

            "Booked by" => "Réservation effectuée par",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Vous avez réservé pour",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details"        => 'Détails de la réservation', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            "You paid"                   => "Vous avez payé", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            "At the property you'll pay" => "Sur place, vous paierez",

            // provider 'cdsgroupe'
            "CDS reference"   => 'Référence CDS',
            "HOTEL reference" => ['Référence HÔTEL', 'Référence Hôtel'],
        ],
        "tr" => [ // it-28041220.eml
            "(?<name>hotel)FromSubject" => "Teşekkürler! (?<name>.+) rezervasyonunuz onaylandı",

            "Confirmation Number:"    => ["Rezervasyon numarası"],
            "Check-in"                => "Check-in",
            "Check-out"               => "Check-out",
            "Show directions"         => "Ulaşım talimatlarını göster",
            "Address:"                => "Konum",
            "Phone:"                  => "Telefon:",
            "guestNameTD"             => "Konuk adı",
            "guestsGeneral"           => "Konuklar",
            "guestsRoom"              => "Konuk sayısı",
            "maxGuest"                => "Maksimum kapasite",
            "realGuestsInMaxGuestRe"  => "Toplam fiyatınız rezervasyon yaptığınız konuk sayısının (.+?) fiyatına dayanır\.",
            "person"                  => ["kişi", "yetişkin"],
            "child"                   => "çocuk",
            "Your reservation"        => ["Konuk adı", "Rezervasyonunuz"],
            //			"Room" => "",
            "room"                => ["Oda", "oda"],
            "Cancellation policy" => "İptal koşulları",
            "Total Price"         => ["Toplam ücret", "Fiyat"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            "Details" => ["Oda bilgileri"],
            "welcome" => "Teşekkürler",
            "taxVAT"  => "KDV dahildir",
            //			"taxCity" => "",
            "isConfirmed" => ["onaylandı"],
            //			"confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => "İPTAL EDİLDİ",
            "cancelledText"     => ["olarak iptal edilmiştir", "kredi kartı nedeniyle iptal edildi"],
            "Cancellation cost" => "İptal ücreti",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Rezervasyon bilgileri', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "lt" => [
            "(?<name>hotel)FromSubject" => "Dėkojame\!\s*(?<name>.+)\s*patvirtino\s*jūsų\s*užsakymą\.",

            "Confirmation Number:"  => ["Užsakymo numeris", "Užsakymo numeris:"],
            "Check-in"              => "Įregistravimas",
            "Check-out"             => "Išregistravimas",
            "Show directions"       => "Rodyti maršrutą",
            "Address:"              => "Vieta",
            "Phone:"                => "Telefonas:",
            "guestNameTD"           => "Svečio vardas ir pavardė",
            "guestsGeneral"         => "Svečių skaičius",
            //            "guestsRoom" => "",
            //            "maxGuest" => "",
            //            "realGuestsInMaxGuestRe" => "",
            "person"           => ["asmenim", "suaugusieji"],
            "child"            => "vaikai",
            "Your reservation" => "Jūsų užsakymas",
            //			"Room" => "",
            "room"                => ["numeris"],
            "Cancellation policy" => "Atšaukimo nuostatai",
            "Total Price"         => ["Kaina", "Visa kaina"],
            //            "Total by Discount" => "",
            //			"has made a reservation for you through"=>[""],
            "Details" => ["Informacija apie numerį"],
            "welcome" => "Dėkojame",
            "taxVAT"  => "% dydžio PVM",
            //			"taxCity" => "",
            "isConfirmed" => "patvirtintas",
            //			"confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => ["ATŠAUKTA"],
            //            "cancelledText" => "",
            "Cancellation cost" => "Atšaukimo kaina",

            //            "Booked by" => "Užsakėte nurodytam skaičiui svečių:",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Užsakėte nurodytam skaičiui svečių:",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "lv" => [ // it-90477397.eml
            "(?<name>hotel)FromSubject" => "Paldies! Rezervējums naktsmītnē (?<name>.+) ir apstiprināts",

            "Confirmation Number:"  => "Apstiprinājuma numurs:",
            "Check-in"              => "Reģistrēšanās",
            "Check-out"             => "Izrakstīšanās",
            "Show directions"       => "Rādīt ceļa norādījumus",
            "Address:"              => "Atrašanās vieta",
            "Phone:"                => ["Tālrunis:", "Tālrunis"],
            "guestNameTD"           => "Viesa vārds",
            "guestsGeneral"         => "Viesi",
            // "guestsRoom" => "",
            "maxGuest"               => "Maksimālais viesu skaits",
            "realGuestsInMaxGuestRe" => "Jūsu kopējā cena ir par jūsu rezervējuma cenā iekļauto viesu skaitu \(([^\)]+)\)\.",
            "person"                 => "pieaugušie",
            "child"                  => "bērni",
            "Your reservation"       => "Jūsu rezervējums",
            "Room"                   => "Dzīvoklis",
            "room"                   => ["dzīvoklis", "dzīvokļi"],
            "Cancellation policy"    => "Atcelšanas noteikumi",
            "Total Price"            => ["Kopējās izmaksas", "Cena"],
            "Total by Discount"      => "Maksājuma summa",
            // "has made a reservation for you through" => "",
            "Details" => "Informācija par naktsmītni",
            "welcome" => "Paldies",
            "taxVAT"  => "% ir iekļauts/-a cenā",
            // "taxCity" => "",
            "isConfirmed" => "ir apstiprināts",
            // "confirmed" => "",
            // "isCanceled" => "",
            "CANCELED"          => "ATCELTS",
            //            "cancelledText" => "",
            "Cancellation cost" => "Atcelšanas izmaksas",

            // "Booked by" => "",
            // "Check-in time" => "",
            // "Check-out time" => "",
            "Getting into the property:" => "Iekļūšana naktsmītnē:",
            // "You booked for" => "",
            // "is confirmed." => "",
            // "Your loyalty information" => "",
            // "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "sk" => [
            "(?<name>hotel)FromSubject" => "Ďakujeme! (?:Vaša r|R)ezervácia v ubytovaní (?<name>.+) je potvrdená",

            "Confirmation Number:"  => ["Číslo rezervácie:", "Potvrdenie rezervácie:"],
            "Check-in"              => ["Registrácia", "Príchod"],
            "Check-out"             => ["Odhlásenie", "Odchod"],
            "Show directions"       => "Zobraziť popis cesty",
            "Address:"              => ["Miesto"],
            "Phone:"                => ["Telefón:", "Telefón"],
            "guestNameTD"           => "Meno hosťa",
            "guestsGeneral"         => "Kapacita",
            //            "guestsRoom" => "",
            "maxGuest"               => "Max. počet hostí",
            "realGuestsInMaxGuestRe" => "Celková cena zodpovedá počtu osôb uvednému počas rezervácie \((.+)\)\.",
            "person"                 => ["osoby", "osobu", "dospelí", "dospelý"],
            "child"                  => ["deti", "dieťa"],
            "Your reservation"       => "Vaša rezervácia",
            "Room"                   => ["Izba"],
            "room"                   => ["izby", "izba", "apartmán"],
            "Cancellation policy"    => ["Storno podmienky"],
            "Total Price"            => ["Celková cena"],
            //            "Total by Discount" => "",
            "Details"                => ["Informácie o izbe", "Údaje o ubytovacom zariadení"],
            "welcome"                => ["Ďakujeme"],
            "taxVAT"                 => "% DPH je v cene",
            //			"taxCity" => "City tax per night is included",
            "isConfirmed" => ["je potvrdená"],
            "confirmed"   => "potvrdená",
            //            "isCanceled" => "",
            //			"CANCELED" => [""],
            //            "cancelledText" => "",
            "Cancellation cost" => ["Storno poplatky", "Poplatok za zrušenie rezervácie"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Rezervácia pre",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "sl" => [ // it-93259934.eml
            "(?<name>hotel)FromSubject" => "Hvala! Potrjena rezervacija v nastanitvi (?<name>.+)",

            "Confirmation Number:" => "Potrditev:",
            "Check-in"             => "Prijava",
            "Check-out"            => "Odjava",
            // "Show directions" => "",
            "Address:"       => "Lokacija",
            "Phone:"         => "Telefon",
            "guestNameTD"    => "Ime gosta",
            "guestsGeneral"  => "Rezervirali ste za",
            // "guestsRoom" => "",
            "maxGuest" => "Največja kapaciteta",
            // "realGuestsInMaxGuestRe" => "",
            "person"           => "odrasl",
            "child"            => "otrok",
            "Your reservation" => "Vaša rezervacija",
            // "Room" => "",
            "room"                => "soba",
            "Cancellation policy" => "Pravila o odpovedi rezervacije",
            "Total Price"         => "Skupna cena",
            //            "Total by Discount" => "",
            // "has made a reservation for you through" => "",
            // "Details" => "",
            "welcome" => "Hvala",
            // "taxVAT" => "",
            // "taxCity" => "",
            "isConfirmed" => "je potrjena",
            "confirmed"   => "potrjena",
            // "isCanceled" => "",
            // "CANCELED" => "",
            //            "cancelledText" => "",
            "Cancellation cost" => "Strošek odpovedi rezervacije",

            // "Booked by" => "",
            // "Check-in time" => "",
            // "Check-out time" => "",
            // "Getting into the property:" => "",
            // "You booked for" => "",
            // "is confirmed." => "",
            // "Your loyalty information" => "",
            // "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "hr" => [
            "(?<name>hotel)FromSubject" => "Vaša je rezervacija u objektu (?<name>.+) potvrđena",

            "Confirmation Number:"  => ["Broj potvrde:", "Broj rezervacije:", "Broj rezervacije"],
            "Check-in"              => "Prijava",
            "Check-out"             => "Odjava",
            "Show directions"       => "Prikaži upute",
            "Address:"              => "Položaj",
            "Phone:"                => ["Telefon:", "Telefon"],
            "guestNameTD"           => "Ime gosta",
            "guestsGeneral"         => "Broj gostiju",
            //            "guestsRoom" => "",
            "maxGuest"               => ["Maksimalni kapacitet"],
            "realGuestsInMaxGuestRe" => "Vaša se ukupna cijena temelji na cijeni za odabrani broj gostiju \((.+)\)\.",
            "person"                 => ["osoba", "odraslih"],
            "child"                  => "dijete",
            "Your reservation"       => "Vaša rezervacija",
            "Room"                   => "Soba",
            "room"                   => ["soba", "apartman"],
            "Cancellation policy"    => "Pravila otkazivanja",
            "Total Price"            => "Ukupna cijena",
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => "",
            //            "Details" => "",
            "welcome" => "Hvala",
            //            "taxVAT" => "",
            //            "taxCity" => "",
            //            "isConfirmed" => "",
            //            "confirmed" => "",
            //            "isCanceled" => "",
            "CANCELED"          => "OTKAZANO",
            "cancelledText"     => "rezervacija uspješno besplatno otkazana",
            "Cancellation cost" => "Trošak otkazivanja rezervacije",

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Rezervirali ste za",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ko" => [
            "(?<name>hotel)FromSubject" => "감사합니다!(?<name>.+)예약이 확정되었습니다",

            "Confirmation Number:"  => ["예약 확인 번호:", "예약번호:"],
            "Check-in"              => "체크인",
            "Check-out"             => "체크아웃",
            "Show directions"       => "경로 표시",
            "Address:"              => ["위치"],
            "Phone:"                => ["전화:", "전화"],
            "guestNameTD"           => "투숙객",
            "guestsGeneral"         => "정원",
            //            "guestsRoom" => "",
            //            "maxGuest" => "",
            //            "realGuestsInMaxGuestRe" => "",
            "person"           => ["명"],
            "child"            => "아동 ",
            "Your reservation" => ["내 예약"],
            //"Room" => ["Bed", "Room", "Apartment"],
            //"room" => ["dorm bed", "room", "apartment"],
            "Cancellation policy" => ["취소 정책"],
            "Total Price"         => ["합계", '요금'],
            "Total by Discount"   => "지급 금액",
            //"has made a reservation for you through" => ["has made a reservation for you through"],
            "Details" => ["객실 상세 정보"],
            "welcome" => ["감사해요", "우현님"],
            "taxVAT"  => "%의 세금(이)가 포함",
            //"taxCity" => "City tax per night is included",
            "isConfirmed" => ["확정되었습니다"],
            "confirmed"   => "확정되었습니다",
            //            "isCanceled" => "",
            "CANCELED"          => ["CANCELED", "CANCELLED", "취소됨"],
            //            "cancelledText" => "",
            "Cancellation cost" => [
                "취소 수수료",
            ],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            //            "You booked for" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => '예약 상세 정보', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "no" => [ // it-7750117.eml, it-10982657.eml
            "(?<name>hotel)FromSubject" => "Takk! Bookingen på (?<name>.+) er bekreftet",

            "Confirmation Number:" => [
                "Bekreftelsesnummer:", "Bookingnummer", "Bookingnummer:",
            ],
            "Check-in"         => "Innsjekking",
            "Check-out"        => "Utsjekking",
            "Show directions"  => "Se veibeskrivelse",
            "Address:"         => ["Beliggenhet"],
            "Phone:"           => ["Telefon:", "Telefon"],
            "guestNameTD"      => "Navn på gjest",
            //            "guestsGeneral" => "",
            "guestsRoom"             => ["Antall gjester"],
            "maxGuest"               => "Maks. kapasitet",
            "realGuestsInMaxGuestRe" => "Totalprisen er for antallet gjester i bookingen din \(([^\)]+)\)\.",
            "person"                 => ["voksne", "voksen", "gjester"],
            "child"                  => "barn",
            "Your reservation"       => ["Din booking"],
            "Room"                   => ["Hus", "Rom"],
            "room"                   => ["hus", "rom", 'leilighet'],
            "Cancellation policy"    => ["Avbestillingsregler"],
            "Total Price"            => ["Total pris", "Totalpris"],
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => [""],
            "Details" => ["Kontaktopplysninger", "Romopplysninger"],
            "welcome" => ["Takk"],
            "taxVAT"  => "% er inkludert.",
            //            "taxCity" => "",
            "isConfirmed" => ["er bekreftet"],
            "confirmed"   => "bekreftet",
            //            "isCanceled" => "",
            "CANCELED" => ["AVBESTILT"],
            //            "cancelledText" => "",
            "Cancellation cost" => [
                "Avbestillingsgebyr",
            ],

            "Booked by" => "Booket av",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Du har booket for",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            "Reservation details" => 'Bookingopplysninger', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "vi" => [
            "(?<name>hotel)FromSubject" => "Cảm ơn! Đặt phòng của bạn ở (?<name>.+) đã được xác nhận.",

            "Confirmation Number:" => [
                "Mã xác nhận:", "Mã số đặt phòng",
            ],
            "Check-in"         => "Nhận phòng",
            "Check-out"        => "Trả phòng",
            "Show directions"  => "Hiển thị đường đi",
            "Address:"         => ["Địa điểm"],
            "Phone:"           => ["Điện thoại:", "Điện thoại"],
            "guestNameTD"      => "Navn på gjest",
            //            "guestsGeneral" => "",
            "guestsRoom"             => ["Tên khách"],
            "maxGuest"               => "Sức chứa tối đa",
            "realGuestsInMaxGuestRe" => "Tổng giá được tính trên giá cho số lượng khách bạn đã đặt \(([^\)]+)\)\.",
            "person"                 => ["người lớn"],
            "child"                  => "trẻ em",
            "Your reservation"       => ["Đặt phòng của bạn"],
            "Room"                   => ["Phòng"],
            "room"                   => ["Phòng"],
            "Cancellation policy"    => ["Chính sách Hủy đặt phòng"],
            "Total Price"            => ["Tổng giá phòng"],
            //            "Total by Discount" => "",
            //            "has made a reservation for you through" => [""],
            "Details" => ["Chi tiết phòng"],
            "welcome" => ["Cảm ơn"],
            "taxVAT"  => "% Thuế GTGT",
            //            "taxCity" => "",
            "isConfirmed" => ["đã được xác nhận"],
            "confirmed"   => "đã được xác nhận",
            //            "isCanceled" => "",
            "CANCELED"          => ["ĐÃ HỦY"],
            //            "cancelledText" => "",
            "Cancellation cost" => [
                "Phí huỷ phòng",
            ],

            "Booked by" => "Đặt bởi",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Bạn đã đặt cho",
            //            "is confirmed." => "",
            //            "Your loyalty information" => "",
            //            "Loyalty reward" => "",
            //            "Reservation details" => '', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "bg" => [
            "(?<name>hotel)FromSubject" => "Вашата резервация в (?<name>.+) е потвърдена",

            "Confirmation Number:" => [
                "Потвърждение:",
            ],
            "Check-in"                               => "Настаняване",
            "Check-out"                              => "Напускане",
            //            "Show directions"                        => "",
            "Address:"                                => ["Разположение"],
            "Phone:"                                  => ["Телефон"],
            "guestNameTD"                             => "Име на госта",
            //            "guestsGeneral"                          => "",
            //            "guestsRoom"                             => [""],
            "maxGuest"                               => ["Максимален капацитет"],
            "realGuestsInMaxGuestRe"                 => "Общата ви цена е базирана на резервиралия брой гости \(([^\)]+)\)\.",
            "person"                                 => ["възрастни", "гости"],
            "child"                                  => ["child", "children"],
            "Your reservation"                       => ["Вашата резервация"],
            "Room"                                   => ["Стая"],
            "room"                                   => ["Стая", "стая"],
            "Cancellation policy"                    => ["Правила и условия за анулиране"],
            "Total Price"                            => ["Обща цена"],
            //            "Total by Discount"                      => [""],
            //            "Discount"                               => [""],
            //            "has made a reservation for you through" => [""],
            //            "Details"                                => [""],
            "welcome"                                => ["Благодарим ви"],
            "taxVAT"                                 => ["% ДДС"],
            //            "taxCity"                                => "",
            "isConfirmed"                            => ["е потвърдена"],
            "confirmed"                              => "потвърдена",
            //            "isCanceled" => "",
            //            "CANCELED"          => [""],
            //            "cancelledText" => "",
            "Cancellation cost" => ["Разноски по анулиране"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Вие резервирахте за",
            //            "Your booking in" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => ["Your loyalty information", "Your details"],
            //            "Loyalty reward"           => ["Loyalty reward", "Loyalty Reward"],
            "Reservation details" => 'Данни за резервацията', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "id" => [
            "(?<name>hotel)FromSubject" => "Pemesanan Anda dikonfirmasi di (?<name>.+)",

            "Confirmation Number:" => [
                "Nomor konfirmasi:",
            ],
            "Check-in"                                => "Check-in",
            "Check-out"                               => "Check-out",
            "Show directions"                         => "Tampilkan arah jalan",
            "Address:"                                => ["Lokasi"],
            "Phone:"                                  => ["Telepon:", "Telepon"],
            "guestNameTD"                             => "Nama tamu",
            //            "guestsGeneral"                          => "",
            //            "guestsRoom"                             => [""],
            "maxGuest"                               => ["Kapasitas maksimum"],
            "realGuestsInMaxGuestRe"                 => "Harga total Anda dihitung berdasarkan harga untuk jumlah tamu yang Anda pesan \(([^\)]+)\)\.",
            "person"                                 => ["dewasa"],
            //            "child"                                  => [""],
            "Your reservation"                       => ["Pemesanan Anda"],
            //            "Room"                                   => ["Стая"],
            "room"                                   => ["apartemen", "kamar"],
            "Cancellation policy"                    => ["Kebijakan pembatalan"],
            "Total Price"                            => ["Harga total"],
            //            "Total by Discount"                      => [""],
            //            "Discount"                               => [""],
            //            "has made a reservation for you through" => [""],
            //            "Details"                                => [""],
            //            "welcome"                                => [""],
            //            "taxVAT"                                 => ["% ДДС"],
            //            "taxCity"                                => "",
            //            "isConfirmed"                            => ["е потвърдена"],
            //            "confirmed"                              => "потвърдена",
            //            "isCanceled" => "",
            //            "CANCELED"          => [""],
            //            "cancelledText" => "",
            "Cancellation cost" => ["Biaya pembatalan"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Anda memesan untuk",
            //            "Your booking in" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => ["Your loyalty information", "Your details"],
            //            "Loyalty reward"           => ["Loyalty reward", "Loyalty Reward"],
            "Reservation details" => 'Detail reservasi', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],
        "ms" => [
            "(?<name>hotel)FromSubject" => "Terima kasih! Tempahan anda telah disahkan di (?<name>.+)",

            "Confirmation Number:" => [
                "Pengesahan:",
            ],
            "Check-in"                                => "Daftar masuk",
            "Check-out"                               => "Daftar keluar",
            //            "Show directions"                         => "Tampilkan arah jalan",
            "Address:"                                => ["Lokasi"],
            "Phone:"                                  => ["Telefon"],
            //            "guestNameTD"                             => "Nama tamu",
            //            "guestsGeneral"                          => "",
            //            "guestsRoom"                             => [""],
            "maxGuest"                               => ["Kapasiti maksimum"],
            //            "realGuestsInMaxGuestRe"                 => "Harga total Anda dihitung berdasarkan harga untuk jumlah tamu yang Anda pesan \(([^\)]+)\)\.",
            "person"                                 => ["dewasa"],
            //            "child"                                  => [""],
            "Your reservation"                       => ["Tempahan anda"],
            "Room"                                   => ["Bilik"],
            "room"                                   => ["Bilik"],
            "Cancellation policy"                    => ["Polisi Pembatalan"],
            "Total Price"                            => ["Jumlah kos"],
            //            "Total by Discount"                      => [""],
            //            "Discount"                               => [""],
            //            "has made a reservation for you through" => [""],
            //            "Details"                                => [""],
            //            "welcome"                                => [""],
            //            "taxVAT"                                 => ["% ДДС"],
            //            "taxCity"                                => "",
            //            "isConfirmed"                            => ["е потвърдена"],
            //            "confirmed"                              => "потвърдена",
            //            "isCanceled" => "",
            //            "CANCELED"          => [""],
            //            "cancelledText" => "",
            "Cancellation cost" => ["Kos pembatalan"],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            //            "Getting into the property:" => "",
            "You booked for" => "Anda menempah untuk",
            //            "Your booking in" => "",
            //            "is confirmed." => "",
            //            "Your loyalty information" => ["Your loyalty information", "Your details"],
            //            "Loyalty reward"           => ["Loyalty reward", "Loyalty Reward"],
            "Reservation details" => 'Maklumat tempahan', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",
        ],

        // Last
        "en" => [ // it-7750117.eml, it-10982657.eml
            "(?<name>hotel)FromSubject" => "(?:Your (?:\^_)?)?booking(?:\^_)? is confirmed at (?<name>.+)",

            "Confirmation Number:" => [
                "Confirmation Number:",
                "Confirmation number:",
                "Booking number",
                "Booking number:",
                "Booking Number",
                "Confirmation:",
            ],
            "Check-in"                                => ["Check-in", "Arrival", "Move-in date"],
            "Check-out"                               => ["Check-out", "Departure"],
            "Show directions"                         => "Show directions",
            "Address:"                                => ["Address:", "Address", "Location"],
            "Phone:"                                  => ["Phone:", "Phone"],
            "guestNameTD"                             => ["Guest names:", "Guest name", "Clients", "Traveler(s)"],
            "guestsGeneral"                           => "Your group",
            "guestsRoom"                              => ["Number of guests", 'Number of clients'],
            "maxGuest"                                => ["Maximum capacity", "Max capacity"],
            "realGuestsInMaxGuestRe"                  => "(?:Your total price is based on the rate for (?:your booked number of|the number of booked) guests|The total price is for the number of booked guests) \(([^\)]+)\)\.",
            "person"                                  => ["person", "adult", "adults", "people"],
            "child"                                   => ["child", "children"],
            "Your reservation"                        => ["Your reservation", "Booking details", 'Your booking'],
            "Room"                                    => ["Bed", "Room", "Apartment", "Dorm bed"],
            "room"                                    => ["dorm bed", "room", "apartment", "house"],
            "Cancellation policy"                     => ["Cancellation policy", "Cancellation policy:"],
            "Total Price"                             => ["Total Price", "Total price", "Price", "You paid", "Total", "Price", "Total EUR"],
            "Total by Discount"                       => ["Payment amount", "Payment Amount"],
            "Discount"                                => ["Early payment benefit"],
            "has made a reservation for you through"  => ["has made a reservation for you through"],
            "Details"                                 => ["Details", "Room details", "Accommodation details", 'Description of the room'],
            "welcome"                                 => ["Thanks", "Dear"],
            "taxVAT"                                  => ["% VAT is included", "% TAX is included"],
            "taxCity"                                 => "City tax per night is included",
            "isConfirmed"                             => ["is now confirmed", "is confirmed"],
            "confirmed"                               => "confirmed",
            //            "isCanceled" => "",
            "CANCELED"          => ["CANCELED", "CANCELLED"],
            //            "cancelledText" => "",
            "Cancellation cost" => [
                "Cancellation cost",
                "Cancellation Fees in local hotel time",
                "Cancellation costs in local hotel time:",
            ],

            //            "Booked by" => "",
            //            "Check-in time" => "",
            //            "Check-out time" => "",
            "Getting into the property:" => ["Getting into the property:", "Getting Into the Property:"],
            //            "You booked for" => "",
            //            "Your booking in" => "",
            //            "is confirmed." => "",
            "Your loyalty information" => ["Your loyalty information", "Your details"],
            "Loyalty reward"           => ["Loyalty reward", "Loyalty Reward"],
            "Reservation details"      => 'Reservation details', // фраза после названия отеля и перед таблицей с данными(адрес отеля указан отдельно в поле Location)
            //            "You paid" => "", // эта же фраза должна быть в "Total Price", и используется когда стоимость указана двумя строками "You paid" и "At the property you'll pay"
            //            "At the property you'll pay" => "",

            // provider 'cdsgroupe'
            //            "CDS reference" => '',
            //            "HOTEL reference" => '',
        ],
    ];

    public $lang = "en";

    private $bgColorBlue = [ // without spaces
        'background:#003580',       'background-color:#003580',
        'background:rgb(0,53,128)', 'background-color:rgb(0,53,128)',
    ];

    private $borderTopBlue = [ // without spaces
        'border-top:solid#003580', 'border-top:solidrgb(0,53,128)',
    ];

    private $emailSubject;

    private $loyaltyProgram = [
        'aa' => [
            'number' => 'AAdvantage® number',
            //            'points' => '',
        ],
        'alitalia' => [
            'number' => 'Codice MilleMiglia',
            'points' => 'MilleMiglia',
        ],
        'british' => [
            //            'number' => '',
            'points' => 'Avios',
        ],
        'lanpass' => [
            'number' => ['Número LATAM Pass'],
            'points' => ['Pontos LATAM Pass', 'Millas LATAM Pass'],
        ],
        'rapidrewards' => [
            'number' => 'Rapid Rewards® Number',
            'points' => 'Rapid Rewards® points',
        ],
        'skywards' => [
            //            'number' => '',
            'points' => 'Skywards Miles',
        ],
        'wizz' => [
            'number' => 'Numer karty członkowskiej Wizz Air',
            'points' => 'Środki Wizz Air',
        ],
        // Pontos Livelo
        // https://www.pins.co/booking-com/
    ];

    private $providerCode = '';

    public function parseHtml(Email $email): void
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold") or contains(@style, "font-size:15.0pt")])';

        $h = $email->add()->hotel();

        // it-1861175.eml
        $hotelRoots = $this->http->XPath->query("descendant::*[self::div[contains(translate(@style,' ',''),'width:580px')] or self::table][count(descendant::tr[{$this->starts($this->t("Check-in"))}]/following-sibling::tr[normalize-space()][1][{$this->starts($this->t("Check-out"))}]/preceding-sibling::tr[{$this->starts($this->t("Your reservation"))}])=1]");
        $rootMain = $hotelRoots->length > 0 ? $hotelRoots->item(0) : null;

        $confNo = $this->nextText($this->t("Confirmation Number:"));

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number:'))}]/ancestor::tr[1]", null, true, "/^{$this->opt($this->t('Confirmation Number:'))}\s*(\d+)\s*(?:PIN|$)/");
        }

        if ($this->providerCode === 'cdsgroupe') {
            $conf = $this->nextText($this->t("CDS reference"));

            if (empty($conf)) {
                $conf = $this->nextText($this->t("CDS Reference"));
            }
            $h->general()
                ->confirmation($conf, null, true);
            $confNo = $this->nextText($this->t("HOTEL reference"), null, 'eq', "/^\s*([\d\-]{5,})(?: - .+)?\s*$/");

            if (!empty($confNo)) {
                $h->general()
                    ->confirmation($confNo, $this->http->FindSingleNode("//text()[" . $this->eq($this->t("HOTEL reference")) . ']'));
            }
        } elseif (!empty($confNo)) {
            $h->general()
                ->confirmation($confNo);
        } else {
            if ($this->http->FindSingleNode("//text()[" . $this->contains($this->t("has made a reservation for you through")) . "]")) {
                $h->general()
                    ->noConfirmation();
            } elseif (empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Confirmation Number:")) . "][1]"))) {
                $h->general()
                    ->noConfirmation();
            }
        }

        $hotelInfoVariants = ['Hotel info', 'Hotelinformationen', 'Hotel information', 'Informacje o hotelu', 'Información del hotel', 'Informações do hotel', "Informations sur l'hôtel", 'פרטי המלון', 'Информация об отеле', 'Information om hotellet', '酒店信息', 'Informação do hotel', '飯店信息', 'Informasjon om hotellet', 'ข้อมูลโรงแรม'];
        $urls = ['secure.booking.com/myreservations', 'secure.booking.com/mybooking', 'www.booking.com/hotel/', 'www.booking.com_hotel_', 'secure.booking.com_myreservations', 'www.booking.com%2Fhotel%2F', 'secure.booking.com%2Fmyreservations', '//travelroom.cdsgroupe.com/hotel/'];

        $hotelName = $this->http->FindSingleNode("(//a[{$this->eq($hotelInfoVariants, '@title')}])[1]");

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode($q = "(//a[{$this->contains($urls, '@originalsrc')} and not(.//img)]/descendant::node()[{$xpathBold}])[1]/ancestor::a[1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode($q = "(//a[{$this->contains($urls, '@href')} and not(.//img)]/descendant-or-self::node()[{$xpathBold}][ancestor::td[1]/preceding-sibling::td[1]/descendant::img][string-length(normalize-space())>2])[1]/ancestor-or-self::a[1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation details")) . "][following::text()[normalize-space()][1][" . $this->starts($this->t("Check-in")) . "]]/preceding::text()[normalize-space()][not(ancestor::*[contains(@style, '#FEFBF0')])][1][ancestor::a[{$this->contains($urls, '@href')}] and ancestor::node()[{$xpathBold}] and following::img[following::text()[" . $this->eq($this->t("Reservation details")) . "]]]");
        }

        if (empty($hotelName)) {
            $hotelNameTemp = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation details")) . "][following::text()[normalize-space()][1][" . $this->starts($this->t("Check-in")) . "]]/preceding::text()[normalize-space()][not(ancestor::*[contains(@style, '#FEFBF0')])][2][ancestor::a[{$this->contains($urls, '@href')}] and ancestor::node()[{$xpathBold}] and following::img[following::text()[" . $this->eq($this->t("Reservation details")) . "]]]");

            if (!empty($hotelNameTemp) && !empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Reservation details")) . "]/preceding::text()[normalize-space()][1][" . $this->eq($hotelNameTemp) . "]/preceding::text()[normalize-space()][1][" . $this->eq($hotelNameTemp) . "]"))) {
                $hotelName = $hotelNameTemp;
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->re("#{$this->opt($this->t('Your booking is confirmed at'))}\s*(.+)#u", $this->emailSubject);
        }

        if (empty($hotelName)) {
            $hotelName_subject = $this->re("#{$this->t("(?<name>hotel)FromSubject")}#u", $this->emailSubject);

            if (!$hotelName_subject) {
                $subjectVariants = ["Subject:", "Assunto:"];
                $hotelNames_subject = array_filter($this->http->FindNodes("//text()[{$this->eq($subjectVariants)}]/following::node()[string-length(normalize-space())>18][1]", null, "#{$this->t("(?<name>hotel)FromSubject")}#u"));

                if (count($hotelNames_subject) === 0) {
                    $hotelNames_subject = array_filter($this->http->FindNodes("//text()[{$this->starts($subjectVariants)}]", null, "#{$this->t("(?<name>hotel)FromSubject")}#u"));
                }

                if (count(array_unique($hotelNames_subject)) === 1) {
                    $hotelName_subject = array_shift($hotelNames_subject);
                }
            }

            if ($hotelName_subject) {
                $hotelName_subjectVariant = [$hotelName_subject];
                //  Melrost Airport Bed &amp; Breakfast    ->    Melrost Airport Bed & Breakfast
                $hotelName_subjectVariant[] = trim(str_ireplace(['&amp;', '(R)'], ['&', '®'], $hotelName_subject));

                // WyndhamKöln    ->    Wyndham Köln
                $hotelName_subjectVariant[] = trim(preg_replace("/([[:lower:]])([[:upper:]])/u", '$1 $2', $hotelName_subject));
                // Kimpton Shorebreak Huntington Beach Resort, an IHG Hotel [booking.com]
                $hotelName_subjectVariant[] = $hotelName_subject . ' [booking.com]';

                if (($hotelName_body = $this->http->FindSingleNode("descendant::a[{$this->eq($hotelName_subjectVariant)}][1]"))
                    || ($hotelName_body = $this->http->FindSingleNode("descendant::text()[{$this->eq($hotelName_subjectVariant)}][ preceding::text()[normalize-space()][1]/ancestor::a[1][contains(@href,'www.booking.com/hotel/')] ]"))
                ) {
                    $hotelName = $hotelName_body;
                }
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//img[contains(@src, '-etoiles')]/preceding::text()[normalize-space()][1]");
        }

        $address = null;

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("(//a[{$this->contains($urls, '@href')}])/ancestor::*[self::div or self::tr][1][count(descendant::text()[normalize-space()]) = 1 and following-sibling::*[normalize-space()][2][" . $this->starts($this->t('Phone:')) . "]]");

            if (!empty($hotelName)) {
                $address = $this->http->FindSingleNode("(//a[{$this->contains($urls, '@href')}])/ancestor::*[self::div or self::tr][1][count(descendant::text()[normalize-space()]) = 1 and following-sibling::*[normalize-space()][2][" . $this->starts($this->t('Phone:')) . "]]/following-sibling::*[normalize-space()][1][count(descendant::text()[normalize-space()]) = 1]",
                    null, true, "/.*\d.*\d.*/");
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking details'))}]/preceding::text()[normalize-space()][1]");
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You\'ll pay when you stay at'))}]", null, true, "/{$this->opt($this->t('You\'ll pay when you stay at'))}\s*(.+)/");
        }

        $InDate = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Your reservation"))}]/following::td[{$this->starts($this->t("Check-in"))}])[1]/following-sibling::td[normalize-space()][1]");

        if (empty($InDate)) {
            $InDate = $this->http->FindSingleNode("descendant::td[not(.//td) and {$this->starts($this->t("Check-in"))}][1]/following-sibling::td[normalize-space()][1]");
        }

        if (empty($InDate)) {
            $InDate = $this->nextCol($this->t("Check-in"));
        }

        if (empty($InDate)) {
            $InDate = $this->nextTable($this->t("Check-in"));
        }

        if (empty($InDate)) {
            $InDate = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Check-in')) . "]/following::td[1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ' dddd ')]");
        }

        if (empty($InDate)) {
            $InDate = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Check-in')) . "]/following::td[1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'd:dd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ' dddd ')]");
        }

        $InDate = preg_replace("/Tell your host what time you'll arrive .*/", '', $InDate);

        $checkInTime = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Special wishes'))}]/following::text()[starts-with(normalize-space(), 'Approximate time of arrival')]", null, true, "/between\s*([\d\:]+)/");

//        $this->logger->debug('DATE (Check-In): ' . $InDate);

        if (preg_match("#[а-я]+,\s(\d\s[А-я]+\s\d+)\s.+?(\d{1,2}:\d{1,2})#iu", $InDate, $m)
            || preg_match("#(.*)\(.*?\b(\d+:\d+(?:\s*[ap]m)?).*\)#iu", $InDate, $m)
            || preg_match("#,(.*)\(.*\b(\d+:\d+(?:\s*[ap]m)?)\)#iu", $InDate, $m)
            || preg_match("#(.*)\(.*\b(\d+:\d+(?:\s*[ap]m)?).*\b(\d+:\d+(?:\s*[ap]m)?)\)#ui", $InDate,
                $m) // пятница, 27 апреля 2018 (12:00 - 14:00)
            || preg_match("#(.*?) *(?:\w.?)? *\(.*\b(\d+:\d+(?:\s*[ap]m)?)\)#ui", $InDate,
                $m) // пятница, 27 апреля 2018 (с 12:00)
        ) {
            if (!empty($checkInTime)) {
                $InDate = trim($m[1]) . ' ' . trim($checkInTime);
            } else {
                $InDate = trim($m[1]) . ' ' . trim($m[2]);
            }
        }

        $checkInDate = strtotime($this->normalizeDate($InDate));

        if ($checkInTime = $this->nextCol($this->t('Check-in time'), null, '/(\d{1,2}:\d{2})\s*[\-]*/')) {
            $checkInDate = strtotime($checkInTime, $checkInDate);
        }

        $OutDate = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Your reservation"))}]/following::td[{$this->starts($this->t("Check-out"))}])[1]/following-sibling::td[normalize-space(.)!=''][1]");

        if (empty($OutDate)) {
            $OutDate = ($this->nextCol($this->t("Check-out")));
        }

        if (empty($OutDate)) {
            $OutDate = ($this->nextTable($this->t("Check-out")));
        }

        if (empty($OutDate)) {
            $OutDate = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Check-out')) . "]/following::td[1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dd:dd') and contains(translate(normalize-space(), '0123456789', 'dddddddddd'), ' dddd ')]");
        }

        if (empty($OutDate)) {
            $OutDate = $this->http->FindSingleNode("//text()[" . $this->starts($this->t('Check-out')) . "]/following::td[1][contains(translate(normalize-space(), '0123456789', 'dddddddddd'), 'dddd')]");
        }

//        $this->logger->debug('DATE (Check-Out): ' . $OutDate);

        if (preg_match("#(.*)\((?:.*\b(\d+:\d+))?.*\b(\d+:\d+(?:\s*[ap]m)?)\)#iu", $OutDate, $m)
            || preg_match("#,(.*)\((?:.*\b(\d+:\d+))?.*\b(\d+:\d+(?:\s*[ap]m)?)\)#iu", $OutDate, $m)
        ) {
            $OutDate = trim($m[1]) . ' ' . trim($m[3]);
        }
        // WTF?
//        if (preg_match("#,\s(\d\s[А-я]+\s\d+)\s.+?(\d{1,2}:\d{1,2})#iu", $OutDate, $m)) {
//            $OutDate = $m[1] . "." . $m[2];
//        }

        $checkOutDate = strtotime($this->normalizeDate($OutDate, true));

        if (empty($OutDate) && !empty($checkInDate)) {
            $lenghtStay = $this->http->FindSingleNode("//text()[normalize-space()='Length of stay']/following::text()[normalize-space()][1]");
            $checkOutDate = strtotime($lenghtStay, $checkInDate);
        }

        if ($checkInTime = $this->nextCol($this->t('Check-out time'), null, '/\s*[\-]*\s*(\d{1,2}:\d{2})\s*$/')) {
            $checkOutDate = strtotime($checkInTime, $checkOutDate);
        }
        $h->booked()
            ->checkIn($checkInDate)
            ->checkOut($checkOutDate);

        // Address
        $subAddressStyle = ['color:#F', 'color:#f', 'rgb(255', '#6B6B6B', '#6b6b6b', '#777777'];

        if (empty($address)) {
            if (!$address = implode(", ", array_filter(array_merge([
                implode(" ",
                    array_filter($this->http->FindNodes("//td[" . $this->eq($this->t("Address:")) . "]/following-sibling::td[1]/*[1]/descendant::text()[normalize-space(.)]"))),
            ],
                $this->http->FindNodes("//td[" . $this->eq($this->t("Address:")) . "]/following-sibling::td[1]/*[position()>1 and position()<6]"))))
            ) {
                if (!$address = $this->http->FindSingleNode($q1 = "//text()[" . $this->eq($this->t("Show directions")) . "]//ancestor::td[1]",
                    null, true, "/(.+?)[\s\-]+{$this->opt($this->t('Show directions'))}/u") // it-3151906.eml (Mostrar itinerario)
                ) {
                    // it-33226650.eml
                    $address = $this->http->FindSingleNode("descendant::a[{$this->eq($hotelInfoVariants, '@title')}][1]/following::text()[normalize-space() and not(normalize-space()=':') and normalize-space()!=\"{$hotelName}\" and not(normalize-space()='Business trip')][1][contains(.,',')]");

                    if (empty($address)) { // except red status "Cancelled"
                        $nodes = $this->http->FindNodes($q3 = "(//text()[{$this->starts($this->t("Phone:"))}])[1]/preceding::tr[normalize-space()][not(" . $this->contains($this->t("Getting into the property:")) . ")][1]/descendant::text()[string-length(normalize-space())>1][not(ancestor::*[position()<3][{$this->contains($subAddressStyle, 'translate(@style," ","")')}]) and not({$this->eq($this->t('CANCELED'))}) and not(contains(normalize-space(),\"Please check\")) and not(contains(.,\"Ankunft\")) and not(contains(.,\"Indirizzo\"))]");
                        $address = implode(' ', $nodes);
                    }
                }
            }
        }

        foreach (array_merge((array) $this->t('Address:'), (array) $this->t("Getting into the property:")) as $phrase) {
            $addressRoots = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($phrase)}] ]/*[normalize-space()][2]");

            if ($addressRoots->length !== 1) {
                $addressRoots = $this->http->XPath->query("//tr/*[ count(table[normalize-space()])=2 and table[normalize-space()][1][{$this->eq($phrase)}] ]/table[normalize-space()][2]");
            }

            if ($addressRoots->length !== 1) {
                continue;
            }
            $rootAddress = $addressRoots->item(0);

            // remove sub-address
            $nodesToStip = $this->http->XPath->query("descendant::span[normalize-space() and {$this->contains($subAddressStyle, 'translate(@style," ","")')}]", $rootAddress);

            foreach ($nodesToStip as $nodeToStip) {
                $nodeToStip->parentNode->removeChild($nodeToStip);
            }

            $addressText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $rootAddress));

            if ($addressText) {
                $address = preg_replace(['/([^\-,.;!?\s])[ ]*\n+[ ]*/', '/\s+/'], ['$1, ', ' '], $addressText);

                break;
            }
        }

        $address = preg_replace("/(.+?)[\s\-]+{$this->opt($this->t('Show directions'))}/u", '$1', $address);

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Hotel')]/following::text()[normalize-space()][1][contains(normalize-space(), '酒店')]/following::text()[normalize-space()][1]");
        }
        $addressClean = trim(preg_replace("#,[, ]+#", ', ', $address), ' -');

        if (empty($hotelName) && !empty($addressClean)) {
            $addresStartsWith = strstr($addressClean, ',', true);
            $hotelName = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(),\"{$addresStartsWith}\")]/preceding::a[1][contains(@href,'booking.com')]/descendant::node()[{$xpathBold}])[1]");

            if (empty($hotelName) && $this->lang === 'he') {
                $hotelName = $this->http->FindSingleNode("(//text()[contains(normalize-space(),\"{$addresStartsWith}\")]/ancestor::tr[1]//a[contains(@href,'booking.com/hotel')])[1]");
            }

            if (empty($hotelName) && 'de' === $this->lang) {
                $nodes = $this->http->FindNodes("//p[normalize-space(.)='Hotelinformationen']/following-sibling::table[1]/descendant::td[1]/descendant::text()[normalize-space(.)]");
                $hotelName = array_shift($nodes);

                if (!empty($nodes)) {
                    $addressClean = implode(', ', $nodes);
                }
            }

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("(//text()[starts-with(normalize-space(),\"{$addresStartsWith}\")]/preceding::tr[normalize-space()][1]/descendant::*[{$xpathBold}][normalize-space()])[1]");
            }

            if (empty($hotelName)) {
                $hotelName = $this->http->FindSingleNode("//text()[{$this->contains($address)}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1]//a")
                    ?? $this->http->FindSingleNode("//*[{$this->contains($address)}]/ancestor-or-self::tr[1]/preceding-sibling::tr[normalize-space()][1]//a");
            }
        }

        if (!empty($hotelName) && empty($addressClean)) {
            $addressClean = $this->http->FindSingleNode("//text()[{$this->eq($hotelName)}]/following::text()[normalize-space()][1]/ancestor::table[1]", null, true, "/{$hotelName}(.+){$this->opt($this->t('Phone:'))}/s");
        }

        $h->hotel()
            ->name($hotelName)
            ->address($addressClean);

        $patterns['phone'] = '[+(\d][-+. \d)(]{5,}[\d)]';

        $phone = null;

        foreach ((array) $this->t('Phone:') as $phrase) {
            $re = "/^({$patterns['phone']})(?:\s*[,;]|$)/";
            $phone = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($phrase)}] ]/*[normalize-space()][2]", null, true, $re)
                ?? $this->http->FindSingleNode("//tr/*[ count(table[normalize-space()])=2 and table[normalize-space()][1][{$this->eq($phrase)}] ]/table[normalize-space()][2]", null, true, $re);

            if ($phone) {
                break;
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Phone:'))}]", null, true, "/{$this->opt($this->t('Phone:'))}\s*({$patterns['phone']})(?:\s*[,;]|$)/")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your reservation'))}]/preceding::text()[{$this->starts($this->t('Phone:'))}][1]", null, true, "/{$this->opt($this->t('Phone:'))}\s*({$patterns['phone']})(?:\s*[,;]|$)/");
        }

        if (empty($phone)) {
            $root = $this->http->XPath->query('//text()[(' . $this->contains($this->t('Phone:')) . ')]/ancestor::table[1]');
            $phone = $this->nextText($this->t("Phone:"), $root->length > 0 ? $root->item(0) : null, 'starts', "/(?:\[[^\[\]]*\])?([\d\W]{5,})/");
            $phone = str_replace(["ｰ", "–", '`', html_entity_decode("&#8236;")], "", $phone);

            if (stripos($phone, '://') !== false) {
                $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Phone:'))}]/following::text()[string-length()>5][not(contains(normalize-space(), '//'))][1]", null, true, "/({$patterns['phone']})/");
            }
        }

        if (!empty($phone) && strlen($phone) > 5) {
            // $phone = str_ireplace(['&zwnj;', '&8203;', '&#x202D;',  '&#8237;',  '&8237;', '​', '*'], '', $phone);
            $phone = preg_replace('/[^\w\-+ ()\.,]+/u', '', $phone);
            $h->hotel()->phone($phone);
        }

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        // Travellers
        $guestNames = [];
        $guestNameNodes = $this->http->XPath->query("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("guestNameTD"))}] ]/*[normalize-space()][2] | //tr/*[ count(table[normalize-space()])=2 and table[normalize-space()][1][{$this->eq($this->t("guestNameTD"))}] ]/table[normalize-space()][2]");

        foreach ($guestNameNodes as $gnNode) {
            // remove context links
            $nodesToStip = $this->http->XPath->query('descendant::a[normalize-space()]', $gnNode);

            foreach ($nodesToStip as $nodeToStip) {
                $nodeToStip->parentNode->removeChild($nodeToStip);
            }

            $guestNameText = $this->htmlToText($this->http->FindHTMLByXpath('.', null, $gnNode));
            $guestNames_temp = preg_split('/[ ]*\n+[ ]*/', $guestNameText);

            if (count($guestNames_temp) === 1) {
                // it-33566361.eml
                $guestNames_temp = preg_split('/[ ]*[,]+[ ]*/', $guestNameText);
            }

            foreach ($guestNames_temp as $gName) {
                if (preg_match("/^({$patterns['travellerName']})(?:\s*[(]|$)/u", $gName, $m)) {
                    $guestNames[] = $m[1];
                } elseif (!preg_match("/(?:{$this->opt($this->t("person"))}|{$this->opt($this->t("child"))})/u", $gName)) {
                    $guestNames = [];
                }
            }
        }

        if (count($guestNames) === 0) {
            $guestNames_temp = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t("welcome"))}]/ancestor::*[1]", null, "/^{$this->opt($this->t("welcome"))}[،,\s]*({$patterns['travellerName']})(?:\s*[،,:;!?]|$)/u"));

            if (count(array_unique($guestNames_temp)) === 1) {
                $guestName = array_shift($guestNames_temp);
                $guestNames = [$guestName];
            }
        }

        if (count($guestNames) > 0) {
            $h->general()->travellers(array_values(array_unique($guestNames)));
        }

        // Guests
        // Kids
        $guests = [];
        $kids = [];
        $guestsGeneral = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t("guestsGeneral"))}]/following-sibling::td[normalize-space() and normalize-space()!=':']", $rootMain);

        if (empty($guestsGeneral)) {
            $guestsGeneral = $this->http->FindSingleNode("descendant::*[{$this->eq($this->t("guestsGeneral"))}]/following-sibling::*[normalize-space() and normalize-space()!=':'][not(following-sibling::*)]", $rootMain);
        }

        if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("person"))}/iu", $guestsGeneral, $m)) {
            $guests[] = $m[1];
        }

        if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("child"))}/iu", $guestsGeneral, $m)) {
            $kids[] = $m[1];
        }

        if (count($guests) === 0 && count($kids) === 0) {
            $guestsRoomAll = $this->nextTds($this->t("guestsRoom"));

            foreach ($guestsRoomAll as $guestsRoom) {
                if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("person"))}/iu", $guestsRoom, $m)) {
                    $guests[] = $m[1];
                }

                if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("child"))}/iu", $guestsRoom, $m)) {
                    $kids[] = $m[1];
                }
            }
        }

        if (count($guests) === 0 && count($kids) === 0) {
            $guestsGeneral = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("You booked for"))}]/ancestor::td[1]/following::td[1]",
                $rootMain);

            if (empty($guestsGeneral)) {
                $guestsGeneral = $this->http->FindSingleNode("//text()[{$this->starts($this->t("You booked for"))}]/ancestor::td[1]/following::td[1]");
            }

            if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("person"))}/iu", $guestsGeneral, $m)) {
                $guests[] = $m[1];
            }

            if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("child"))}/iu", $guestsGeneral, $m)) {
                $kids[] = $m[1];
            }
        }

        if (count($guests) === 0 && count($kids) === 0) {
            $maxGuestGeneralAll = $this->http->FindNodes("descendant::td[not(.//td)][{$this->eq($this->t("maxGuest"))}]/following-sibling::td[not(.//td)][normalize-space() and normalize-space()!=':']", $rootMain, "/.*{$this->opt($this->t("person"))}.*/ui");

            if (empty($maxGuestGeneralAll)) {
                $maxGuestGeneralAll = $this->http->FindNodes("descendant::*[{$this->eq($this->t("maxGuest"))}]/following-sibling::*[normalize-space() and normalize-space()!=':'][not(following-sibling::*)]/descendant::text()[normalize-space() and not(ancestor::div[1][contains(@style,'margin-top:')])]",
                    $rootMain);
            }

            foreach ($maxGuestGeneralAll as $maxGuestGeneral) {
                if (preg_match("/" . $this->t("realGuestsInMaxGuestRe") . "/u", $maxGuestGeneral, $m) && isset($m[1])) {
                    $maxGuestGeneral = $m[1];
                }

                if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("person"))}/iu", $maxGuestGeneral, $m)) {
                    $guests[] = $m[1];
                }

                if (preg_match("/(?:^|\D)(\d{1,3})\s*{$this->opt($this->t("child"))}/iu", $maxGuestGeneral, $m)) {
                    $kids[] = $m[1];
                }
            }
        }

        $rooms = '';

        if (count($guests) === 0 && count($kids) === 0) {
            $info = implode("\n", $this->http->FindNodes("//text()[{$this->contains($h->getHotelName())}]/following::tr[3]/descendant::text()[string-length()>3]"));
            $guestCount = $this->re("/(\d+)\s*{$this->opt($this->t('person'))}/", $info);

            if (!empty($guestCount)) {
                $guests = [$guestCount];
            }
            $rooms = $this->re("/(\d+)\s*{$this->opt($this->t('room'))}/", $info);
        }

        if (count($guests)) {
            $h->booked()->guests(array_sum($guests));
        }

        if (count($kids)) {
            $h->booked()->kids(array_sum($kids));
        }

        if (empty($rooms)) {
            $rooms = $this->re("/\b(\d{1,3})\s*{$this->opt($this->t("room"))}/u",
                $this->nextTd($this->t("Your reservation")));
        }

        $h->booked()->rooms($rooms, false, true);

        $bgColorGreen = ['background:#008009', 'background-color:#008009', 'background:rgb(0,128,9)', 'background-color:rgb(0,128,9)'];

        if ($h->getRoomsCount() > 1) {
            $roots = $this->http->XPath->query($xpath = "//text()[{$this->eq($this->t('Cancellation policy'))}]/ancestor::table[{$this->contains($this->t('Cancellation cost'))}][1]");
            // it-20391082.eml

            $cancelFull = [];
            $roomRate = '';

            foreach ($roots as $root) {
                $cancellText = $this->nextCol($this->t("Cancellation policy"), $root);

                if (empty($cancellText)) {
                    $cancellText = $this->nextText($this->t("Cancellation policy"), $root);
                }

                if (!empty($cancellText)) {
                    $cancelFull[] = $cancellText;
                }

                $rootUp = $this->http->XPath->query($xpath = "preceding::tr[({$this->starts($this->t("Room"))}) and contains(.,':')][1]", $root);

                if ($rootUp->length == 1) {
                    $rootUp = $rootUp->item(0);

                    if (empty($roomType = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::tr[1]",
                        $rootUp, false, "#\d:\s*(.+)#u"))
                    ) {
                        $roomType = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Total Price")) . "]/ancestor::tr[1]/../tr[1]/td[1][not(" . $this->contains($this->t("Total Price")) . ")]");

                        if (empty($roomType)) {
                            $roomType = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Total Price")) . "]/ancestor::table[1]/preceding-sibling::table[1]//tr[1]/td[1]");
                        }

                        if (empty($roomType)) {
                            $roomType = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]",
                                $rootUp, true, "/\d\s*\:(.+)/");

                            $roomRate = $this->http->FindSingleNode(".",
                                $rootUp, true, "/{$this->opt($this->t('Room rate:'))}\s*(.+\d)\s*/");
                        }
                    }

                    $rTypeDescription =
                        $this->http->FindSingleNode("./descendant::tr[normalize-space()!=''][1]/following-sibling::tr[normalize-space()!=''][last()]",
                            $rootUp);

                    if (empty($rTypeDescription)) {
                        $RoomTypeDescription = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("guestsRoom")) . " or " . $this->eq($this->t("guestNameTD")) . "]/preceding::text()[" . $this->contains($this->t("Details")) . "]/ancestor::tr[1]/following-sibling::tr[1]",
                            $root);

                        if (stripos($RoomTypeDescription, $this->t("Room"))) {
                            $rTypeDescription = $RoomTypeDescription;
                        }
                    }
//                    if (empty($rTypeDescription)) {
//                        $rTypeDescription = $this->http->FindSingleNode("./preceding::text()[{$this->eq($this->t('Details'))}]/following::*[normalize-space(.)!=''][1][ not(" . $this->starts($this->t("guestsRoom")) . ") and not(" . $this->starts($this->t("guestNameTD")) . ")]");
//                    }
                    if (empty($rTypeDescription)) {
                        $rTypeDescription = $this->http->FindSingleNode("./following::*[normalize-space(.)!=''][1][ not(" . $this->starts($this->t("guestsRoom")) . ") and not(" . $this->starts($this->t("guestNameTD")) . ")]", $rootUp);
                    }

//                    if (!empty($rTypeDescription) || !empty($roomType)) {
                    if (!empty($roomType)) {
                        $roomType = preg_replace('/Probleme beim Anzeigen dieser E-Mail.*/', '', $roomType);
                    }

                    $r = $h->addRoom();
                    $r->setType($roomType, true, true);
                    $node = $this->re("#(.*?)\s*(?:Approximate time of arrival|$)#", $rTypeDescription);

                    if (!empty($node)) {
                        $r->setDescription($node, false, true);
                    }

                    if (!empty($roomRate)) {
                        $r->setRate($roomRate . ' / night');
                    }
                }
            }

            if (!empty($cancelFull)) {
                $cancellText = implode("|", array_filter(array_unique($cancelFull)));

                $h->general()
                    ->cancellation($cancellText, false, true);
            }

            $root = $this->http->XPath->query($xpath = "preceding::text()[{$this->contains($this->t("Total Price"))}]/ancestor::table[1][{$this->contains($h->getRoomsCount())}]",
                $roots->item(0));

            if ($root->length == 1) {
                $root = $root->item(0);
                $currency = $this->Currency($this->nextText($this->t("Total Price"), $root, 'eq', '/.*\d.*/'));
                $taxesText = $this->nextTd($this->t("taxVAT"), $root, 'contains');

                if ($taxesText !== null) {
                    $tax = $this->amount(trim($this->re("#^\s*\D{0,4}(\d[\d\s\,\.]*)\D{0,4}\s*$#u", $taxesText)), $currency);
                    $taxesText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("taxCity")) . "]",
                        $root);

                    if ($taxesText !== null) {
                        $tax += $this->amount($this->re("#\s*\D{0,4}(\d[\d\s\,\.]*)\D{0,4}\s*[^%]*$#u", $taxesText), $currency);
                    }
                }

                $total = $this->nextText($this->t("Total Price"), $root, 'eq', '/.*\d.*/');
            }

            if (empty($total)) {
                $total = $this->http->FindSingleNode("//td[{$this->eq($this->t("Total Price"))}]/following::td[normalize-space()][1][{$this->contains($bgColorGreen, 'translate(@style," ","")')}]/following::td[normalize-space()][1]");
                $currency = $this->Currency($total);
            }

            if (empty($total)) {
                $total = $this->http->FindSingleNode(".//text()[{$this->eq($this->t("Total Price"))}]/following::text()[normalize-space(.)!=''][1]");
                $currency = $this->Currency($total);
            }

            if (!empty($total)) {
                $payAtHotel = $this->http->FindSingleNode("//td[{$this->eq($this->t("At the property you'll pay"))}]/following::td[normalize-space()][1][{$this->contains($bgColorGreen, 'translate(@style," ","")')}]/following::td[normalize-space()][1]");

                if (preg_match("/^(\D{1,5}\d[\d., ]*) \D+/", $payAtHotel, $m)
                || preg_match("/^\s*(\d[\d., ]* ?\D{1,5}) \D+/", $payAtHotel, $m)) {
                    $payAtHotel = $m[1];
                }

                if (!empty($payAtHotel) && !empty($total) && !empty($this->http->FindSingleNode("//*[" . $this->eq($total) . "]/preceding::text()[normalize-space()][position() < 5][{$this->eq($this->t("You paid"))}]"))) {
                    $tax = $tax ?? 0;
                    $tax += $this->amount(trim($this->re("#(\d[\d\s\,\.]*)#", $payAtHotel)), $currency);
                    $cost = $total;
                    $total = null;
                }

                if (isset($tax)) {
                    $h->price()
                        ->tax($tax);
                }

                $h->price()
                    ->currency($currency, false, true);

                if (!empty($total)) {
                    $h->price()
                        ->total($this->amount(trim($this->re("#(\d[\d\s\,\.]*)#", $total)), $currency), false, true);
                }

                if (!empty($cost)) {
                    $h->price()
                        ->cost($this->amount(trim($this->re("#(\d[\d\s\,\.]*)#", $cost)), $currency), false, true);
                }
            }
        } else {
            $cancellText = $this->nextCol($this->t("Cancellation policy"));

            if (empty($cancellText)) {
                $cancellText = $this->nextText($this->t("Cancellation policy"));
            }

            if (mb_strlen($cancellText) <= 2) {
                $cancellText = $this->nextTd($this->t("Cancellation policy"));
            }

            if (empty($cancellText)) {
                $cancellText = $this->http->FindSingleNode("//text()[normalize-space()='Cancellation cost']/ancestor::tr[1]/descendant::tr[1]");
            }

            $h->general()
                ->cancellation($cancellText, false, true);

            if (is_array($this->t("Room"))) {
                foreach ($this->t("Room") as $tuba) {
                    $TubaTitle[] = $tuba . ' d:';
                }
            } else {
                $TubaTitle[] = $this->t("Room") . ' d:';
            }

            $roomType = implode("; ",
                array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("guestNameTD"))}]/preceding::tr[{$this->contains($TubaTitle, "translate(normalize-space(),'0123456789','dddddddddd')")}][1]", null, '/\d:\s*(.+)/')))
            );

            if (empty($roomType)) {
                $roomType = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Total Price")) . "]/ancestor::tr[1]/../tr[1]/td[1][not(" . $this->contains($this->t("Total Price")) . ")]");

                if (empty($roomType)) {
                    $roomType = $this->http->FindSingleNode("//*[" . $this->eq($this->t("Total Price")) . "]/ancestor::table[1]/preceding-sibling::table[1]//tr[1]/td[1]");
                }
            }

            $rTypeDescription = implode("\n",
                array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("guestNameTD")) . "]/preceding::tr[" . $this->contains($TubaTitle,
                        "translate( normalize-space(.), '0123456789', 'dddddddddd')") . "][1]/following-sibling::tr[position()<3][string-length(normalize-space())>5][1]"))));

            if (empty($rTypeDescription)) {
                $RoomTypeDescription = implode("\n",
                    array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("guestsRoom")) . " or " . $this->eq($this->t("guestNameTD")) . "]/preceding::text()[" . $this->contains($this->t("Details")) . "]/ancestor::tr[1]/following-sibling::tr[1]"))));

                if (is_array($this->t("Room"))) {
                    foreach ($this->t("Room") as $room) {
                        if (stripos($RoomTypeDescription, $room)) {
                            $rTypeDescription = $RoomTypeDescription;
                        }
                    }
                } else {
                    if (stripos($RoomTypeDescription, $this->t("Room"))) {
                        $rTypeDescription = $RoomTypeDescription;
                    }
                }
            }

            if (empty($rTypeDescription)) {
                $rTypeDescription = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Details'))}]/following::*[normalize-space(.)!=''][1][not(" . $this->starts($this->t("guestsRoom")) . ") and not(" . $this->starts($this->t("guestNameTD")) . ")]");
            }

            if (!empty($rTypeDescription) || !empty($roomType)) {
                $roomType = preg_replace('/Probleme beim Anzeigen dieser E-Mail.*/', '', $roomType);
            }

            if (empty($roomType)) {
                $roomTypes = $this->http->FindNodes("//b[starts-with(normalize-space(.), 'Zimmer ') and not(.//td)]/following::b[normalize-space(.)][1]");
            }

            if (empty($roomType) && 0 < count($roomTypes)) {
                foreach ($roomTypes as $roomType) {
                    $r = $h->addRoom();
                    $r->setType($roomType, true, true);
                }
            } else {
                $roomDesc = $this->re("#(.*?)\s*(?:Approximate time of arrival|$)#", $rTypeDescription);

                if ($roomDesc || $roomType) {
                    $r = $h->addRoom();

                    if ($roomDesc) {
                        $r->setDescription($roomDesc);
                    }

                    if ($roomType) {
                        $r->setType($roomType);
                    }
                }
            }

            if (count($h->getRooms()) == 0) {
                $xpathRoomTypeRoot = "//text()[" . $this->eq($this->t("guestNameTD")) . "]/ancestor::table[1][" . $this->eq($this->t("guestNameTD")) . "]/ancestor::tr[" . $this->starts($this->t("guestNameTD")) . "]";
                $xpathRoomTypeStyle = "ancestor::*[{$xpathBold}] and ancestor::*[{$this->contains(['font-size:16px', 'font-size:12.0pt'], 'translate(@style," ","")')}] and ancestor::*[{$this->contains(['line-height:24px', 'line-height:18.0pt'], 'translate(@style," ","")')}]";

                $roomTypes = $this->http->FindNodes($xpathRoomTypeRoot . "/preceding-sibling::tr[normalize-space()][1][not(preceding-sibling::tr)][ descendant::text()[normalize-space()][1][{$xpathRoomTypeStyle}] ]");
                $roomTypesDesc = [];

                if (empty($roomTypes)) {
                    $roomTypes = $this->http->FindNodes($xpathRoomTypeRoot . "/preceding-sibling::tr[normalize-space()][2][not(preceding-sibling::tr)][ descendant::text()[normalize-space()][1][{$xpathRoomTypeStyle}] ]");

                    if (!empty($roomTypes)) {
                        $roomTypesDesc = $this->http->FindNodes($xpathRoomTypeRoot . "/preceding-sibling::tr[normalize-space()][1][ preceding-sibling::tr[normalize-space()][1][descendant::text()[normalize-space()][1][{$xpathRoomTypeStyle}]] ]");

                        if (count($roomTypes) !== count($roomTypesDesc)) {
                            $roomTypesDesc = [];
                        }
                    }
                }

                foreach ($roomTypes as $i => $type) {
                    $type = str_replace(['>', '<'], '', $type);
                    $h->addRoom()
                        ->setType($type)
                        ->setDescription($roomTypesDesc[$i] ?? null, true, true)
                    ;
                }
            }

            $discount = $this->re("#([\d\s\,\.]*\d[\d\s\,\.]*)#", $this->nextText($this->t('Discount')));

            if (!preg_match("/^\s*\d+\,\d+\s/", $discount)) {
                $discount = str_replace(',', '', $discount);
            } else {
                $discount = str_replace(',', '.', $discount);
            }

            if (!empty($discount)) {
                $h->price()
                    ->discount($discount);
            }

            $total = $this->nextText($this->t('Total by Discount'), null, 'eq', '/.*\d.*/');

            if (empty($total)) {
                $total = $this->nextText($this->t("Total Price"), null, 'eq', '/.*\d.*/');
            }

            if (empty($total)) {
                $total = $this->http->FindSingleNode("//td[{$this->eq($this->t("Total Price"))}]/following::td[normalize-space()][1][{$this->contains($bgColorGreen, 'translate(@style," ","")')}]/following::td[normalize-space()][1]");

                if (empty($total)) {
                    $total = $this->http->FindSingleNode("//*[{$this->contains($this->t("Check-in"))}]/following::text()[{$this->eq($this->t('Total Price'))}]/following::text()[normalize-space()][not(contains(normalize-space(), 'currency'))][1]");
                }

                if (empty($total)) {
                    $total = $this->http->FindSingleNode("//*[{$this->contains($this->t("Total Price"))}]/ancestor-or-self::td[1]/following-sibling::td[normalize-space()!=''][1]");
                }

                if (empty($total)) {
                    $total = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Total Price"))}]/following::td[1]");
                }
            }
            $currency = $this->Currency($total);

            $taxesText = $this->nextTd($this->t("taxVAT"), null, 'eq');

            if (empty($taxesText)) {
                $taxesText = $this->nextTd($this->t("taxVAT"), null, 'contains');
            }

            if ($taxesText !== null) {
                $tax = $this->amount($this->re("#^\s*\D{0,4} ?(\d[\d\s\,\.]*) ?\D{0,4}\s*$#u", $taxesText), $currency);
                $taxesText = $this->http->FindSingleNode("//text()[" . $this->contains($this->t("taxCity")) . "]");

                if ($taxesText !== null) {
                    $tax += $this->amount($this->re("#\s*\D{0,4}(\d[\d\s\,\.]*)\D{0,4}\s*[^%]*$#u", $taxesText), $currency);
                }
            }

            $payAtHotel = $this->http->FindSingleNode("//td[{$this->eq($this->t("At the property you'll pay"))}]/following::td[normalize-space()][1]");

            if (preg_match("/^(\D{1,5}\d[\d., ]*) \D+/", $payAtHotel, $m)
                || preg_match("/^\s*(\d[\d., ]* ?\D{1,5}) \D+/", $payAtHotel, $m)) {
                $payAtHotel = $m[1];
            }

            if (!empty($payAtHotel) && !empty($total) && !empty($this->http->FindSingleNode("//*[" . $this->eq($total) . "]/preceding::text()[normalize-space()][position() < 5][{$this->eq($this->t("You paid"))}]"))) {
                $tax = $tax ?? 0;
                $tax += $this->amount($this->re("#(\d[\d\s\,\.]*)#", $payAtHotel), $currency);
                $cost = $total;
                $total = null;
            }

            if (isset($tax)) {
                $h->price()
                    ->tax($tax);
            }

            if (!empty($currency)) {
                $h->price()
                    ->currency($currency, false, true);
            }

            if (!empty($total)) {
                $h->price()
                    ->total($this->amount(trim($this->re("#(\d[\d\s\,\.]*)#", $total)), $currency), false, true);
            }

            if (!empty($cost)) {
                $h->price()
                    ->cost($this->amount(trim($this->re("#(\d[\d\s\,\.]*)#", $cost)), $currency), false, true);
            }
        }

        // Status
        $status = $this->http->FindSingleNode("//h2[{$this->contains($this->t("isConfirmed"))}]", null, true, "/\b({$this->opt($this->t("confirmed"))})\b/u");

        if (empty($status)) {
            $status = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t("isConfirmed"))}][1]", null, true, "/\b({$this->opt($this->t("confirmed"))})\b/u");
        }

        if (empty($status)) {
            // for canceled
            $status = $this->http->FindSingleNode("//h2[{$this->contains($this->t("isCanceled"))}]", null, true, "/\b({$this->opt($this->t("CANCELED"))})\b/");
        }

        if ($status) {
            $h->general()->status($status);
        }

        // Cancelled
        if (array_search($status, (array) $this->t("CANCELED")) !== false) {
            $h->general()->cancelled();
        } elseif (
            !empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t("Phone:"))}])[1]/preceding::text()[string-length(normalize-space())>1][1][{$this->contains(['color:#F', 'color:#f', 'rgb(255', 'color:#ee3b28'], 'translate(ancestor::*[position()<3]/@style," ","")')} or {$this->eq($this->t('CANCELED'))}]"))
            || !empty($this->http->FindSingleNode("(//text()[{$this->contains($this->t("cancelledText"))}])[1]"))
        ) {
            // it-10982657.eml, it-27947687.eml, it-3378831.eml, it-33999463.eml
            $h->general()
                ->status('Cancelled')
                ->cancelled();
        }

        // Travel Agency
        $accountsText = array_unique(array_filter($this->http->FindNodes("//td[" . $this->eq($this->t("Your loyalty information")) . "]/following-sibling::td[normalize-space()][1]")));

        if (!empty($accountsText)) {
            $accounts = [];

            foreach ($accountsText as $atext) {
                if (preg_match("#^\s*(?<name>\S.+\s+)?(?<value>[\da-z]{5,})\s*$#", $atext, $m)) {
                    $accounts[trim($m['name'])][] = $m['value'];
                    $aName = trim($m['name']);

                    continue;
                } else {
                    $accounts = [];
                }
            }

            if (count($accounts) == 1) {
                if (!empty($aName)) {
                    foreach ($this->loyaltyProgram as $code => $pnames) {
                        if (preg_match("#^\s*" . $this->opt($pnames['number']) . "\s*$#ui", $aName, $m)) {
                            $aCode = $code;

                            break;
                        }
                    }
                }

                $pointTexts = array_filter($this->http->FindNodes("//td[" . $this->eq($this->t("Loyalty reward")) . "]/following-sibling::td[normalize-space()][1]", null,
                    "#^\s*\d+.*#"));

                $points = [];

                foreach ($pointTexts as $ptext) {
                    if (preg_match("#^\s*(?<value>\d[\d,]*)\s+(?<name>.+)#", $ptext, $m)) {
                        $points[$m['name']][] = str_replace(',', '', $m['value']);
                        $pName = $m['name'];
                    } else {
                        $points = [];

                        break;
                    }
                }

                $pCode = '';

                if (count($points) == 1) {
                    foreach ($this->loyaltyProgram as $code => $pnames) {
                        if (preg_match("#^\s*" . $this->opt($pnames['points']) . "\s*$#ui", $pName)) {
                            $pCode = $code;

                            break;
                        }
                    }
                }

                if (!empty($aCode) && empty($pCode)) {
                    $h->obtainTravelAgency()
                        ->setProviderCode($aCode)
                        ->setAccountNumbers(array_shift($accounts), false)
                    ;
                } elseif (!empty($pCode) && (empty($aCode) || (!empty($aCode) && ($aCode === $pCode)))) {
                    $h->obtainTravelAgency()
                        ->setProviderCode($pCode)
                        ->setAccountNumbers(array_shift($accounts), false)
                        ->setEarnedAwards((string) (array_sum(array_shift($points))) . ' ' . $pName);
                }
            }
        }

        $this->detectDeadLine($h);
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@booking.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (mb_stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        /*
        if ($this->http->XPath->query('//a[contains(@href,"secure-hotel-booking.com")]')->length > 0) {
            // go to provider dedge
//            return false;
        }
        */

        // Detecting Provider
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        // Detecting Format
        $body = $parser->getHTMLBody();
        // TODO Helps separate from booking/Reservation
        if (
            (empty($this->providerCode) || $this->providerCode == 'booking')
            && stripos($body, '580px') === false
            && stripos($body, '"580"') === false
            && stripos($body, '=580') === false
            && stripos($body, '560px') === false
            && stripos($body, '468.0pt') === false
            && $this->striposAll($parser->getSubject(), $this->reSubject) === false
            && $this->http->XPath->query("//node()[{$this->contains(['Manage your booking', 'Endre i bookingen din', 'Altere sua reserva', 'Ändern Sie Ihre Buchung', 'Modifier la réservation', 'Ваше бронирование', 'Manage my booking »'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->bgColorBlue, 'translate(@style," ","")')}][count(descendant::text[normalize-space()])<7 and descendant::text()[normalize-space()='PIN:']]")->length === 0
        ) {
            return false;
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->borderTopBlue, 'translate(@style," ","")')}]")->length > 0) {
            return false;
        }

        // Detecting Language
        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($body, $re) !== false
                || $this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response["body"];

        // Detecting Provider
        $this->assignProvider($parser->getHeaders());

        // Detecting Format
        if (
            (empty($this->providerCode) || $this->providerCode == 'booking')
            && stripos($body, '580px') === false
            && stripos($body, '"580"') === false
            && stripos($body, '=580') === false
            && stripos($body, '560px') === false
            && stripos($body, '468.0pt') === false
            && $this->striposAll($parser->getSubject(), $this->reSubject) === false
            && $this->http->XPath->query("//node()[{$this->contains(['Manage your booking', 'Endre i bookingen din', 'Altere sua reserva', 'Ändern Sie Ihre Buchung', 'Manage my booking »'])}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains($this->bgColorBlue, 'translate(@style," ","")')}][count(.//text[normalize-space()])<7 and descendant::text()[normalize-space()='PIN:']]")->length === 0
            && $this->http->XPath->query("//*[self::td or self::tr or self::table][{$this->contains($this->bgColorBlue, 'translate(@style," ","")')}][descendant::img and count(descendant::text[normalize-space()])<7 and descendant::text()[contains(normalize-space(),':')]]")->length === 0
        ) {
            $this->logger->debug("go to booking/Reservation 1");

            return $email;
        }

        $this->http->FilterHTML = true;

        // Detecting Language
        foreach ($this->reBody2 as $lang => $re) {
            if ((strpos($body, $re) !== false
                || $this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0
            ) && (
                (!empty(self::$dictionary[substr($lang, 0, 2)]['Your reservation']) && $this->http->XPath->query("//*[{$this->contains(self::$dictionary[substr($lang, 0, 2)]['Your reservation'])}]")->length > 0)
                || (!empty(self::$dictionary[substr($lang, 0, 2)]['Reservation details']) && $this->http->XPath->query("//*[{$this->contains(self::$dictionary[substr($lang, 0, 2)]['Reservation details'])}]")->length > 0)
                || (!empty(self::$dictionary[substr($lang, 0, 2)]['CANCELED']) && $this->http->XPath->query("//*[{$this->contains(self::$dictionary[substr($lang, 0, 2)]['CANCELED'])}]")->length > 0)
                || (!empty(self::$dictionary[substr($lang, 0, 2)]['Cancellation policy']) && $this->http->XPath->query("//*[{$this->contains(self::$dictionary[substr($lang, 0, 2)]['Cancellation policy'])}]")->length > 0)
            )) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        if ($this->http->XPath->query("//*[{$this->contains($this->borderTopBlue, 'translate(@style," ","")')}]")->length > 0) {
            $this->logger->debug("go to booking/Reservation 3");

            return $email;
        }

        if ($this->lang == 'pl' && strpos($parser->getHTMLBody(), 'Ń') !== false) {
            $body = $parser->getHTMLBody();
            $body = iconv('UTF-8', 'ISO-8859-2//IGNORE', $body);
            $symbols = [
                "к" => "ę",
                "і" => "ł",
                "у" => "ó",
                "¶" => "ś",
                "ж" => "ć",
                "ї" => "ż",
                "±" => "ą",
                "ј" => "ź",
                "Ј" => "Ł",
                "с" => "ń",
            ];

            foreach ($symbols as $key => $sym) {
                $body = str_replace($key, $sym, $body);
            }
            $this->http->SetEmailBody($body);
        }

        //hardcode
        // FE: it-60237449.eml
        if (strpos($this->http->Response['body'], "class=\"gmail_quote\"") !== false) {
            $this->logger->info("bad email. look at source");
            $body = $this->http->Response['body'];
            $body = preg_replace("/(?:<div[^>]*class=\"gmail_attr\"[^>]*>(?:<br>)*<\/div>)/", '', $body);
            $divs = $this->http->FindPreg("/(<div[^>]*class=\"gmail_quote\"[^>]*>(?:<br>)*(?:<div[^>]*>(?:<br>)*)*)/",
                false, $body);

            if (!empty($divs)) {
                $cntStart = preg_match_all("/(<div[^>]*>)/", $divs);

                if (preg_match("/((?:<\/div>\s*){{$cntStart}})$/", $body, $m)) {
                    $this->logger->info("fixed");
                    $body = str_replace($divs, '', $body);
                    $body = str_replace($m[1], '', $body);
                    $this->http->SetEmailBody($body);
                }
            }
        }

        $this->emailSubject = $parser->getSubject();

        $this->parseHtml($email);

        if ($this->providerCode !== 'booking') {
            $email->setProviderCode($this->providerCode);
        }
        $email->setType('IsBeginForRafactoryProv' . ucfirst($this->lang));

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

    public static function getEmailProviders()
    {
        return ['cdsgroupe', 'booking', 'edreams', 'tripbiz'];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@cdsgroupe.com') !== false
            || $this->http->XPath->query('//a[contains(@href,".cdsgroupe.com/") or contains(@href,"travelroom.cdsgroupe.com") or contains(@href,"bookings.cdsgroupe.com")]')->length > 0
        ) {
            // it-12243783.eml
            $this->providerCode = 'cdsgroupe';

            return true;
        }

        if (stripos($headers['from'], '@edreams') !== false
            || $this->http->XPath->query('//a[contains(@href,".edreams.es/") or contains(@href,".edreams.com")]')->length > 0
        ) {
            // it-12243783.eml
            $this->providerCode = 'edreams';

            return true;
        }

        if (stripos($headers['from'], '@trip.com') !== false
            || $this->http->XPath->query('//a[contains(@href,"c-ctrip.com/") or contains(@href,".c-ctrip.com")]')->length > 0
        ) {
            $this->providerCode = 'tripbiz';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,".booking.com/") or contains(@href,"www.booking.com") or contains(@href,"secure.booking.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(.,"Booking.com") or contains(.,"booking.com") or contains(.,"BOOKING.COM")]')->length > 0
        ) {
            $this->providerCode = 'booking';

            return true;
        }

        return false;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        $node = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[string-length(normalize-space())>2][1]");

        if (($zero = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]/following::text()[normalize-space()!=''][1]",
                null, false, "#^.*\b0$#u"))
            || ($zero = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]",
                null, false, "#^.*\b0$#u"))
            || ($zero = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]/following::text()[normalize-space()!=''][1]",
                null, false, "#^0\s*\w+$#u"))
            || ($zero = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]",
                null, false, "#^0\s*\w+$#u"))
//            || ($zero = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]",
//                null, false, "#^(.+\[.+)$#"))
            || ($zero = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]/following::text()[normalize-space()!=''][1]",
                null, true, "#^(?:FREE|БЕСПЛАТНО|GRATIS|ZDARMA|GRATUIT)#"))
        ) {
            $this->logger->debug($zero);
        }

        if ($zero && (
                   preg_match("#^(?<date>.+? \d+\.\d+) \[.+#", $node, $m)//13. helmikuuta 2018 23.59
                || preg_match("#Έως (?<date>.+? \d+:\d+ μ.μ.) \[.+#u", $node, $m) //Έως 9 Απριλίου 2018 5:59 μ.μ.
                || preg_match("#עד (?<date>\d+ [^\d\s]+ \d{4} \d+:\d+)#", $node, $m) //עד 27 בספטמבר 2016 23:59
                || preg_match("#^(?:Until|Tot|Till)\s+(?<date>.+? \d+:\d+(?:\s*[AaPp][Mm])?) \[.+#", $node,
                    $m)//Until February 9, 2014 11:59 PM [    |    Tot 9 juni 2020 23:59 [CEST]: NOK 0
                || preg_match("#^До (?<date>.+? \d{4})(?: г\.)? (?<time>\d+:\d+(?: *[AaPp][Mm])?) \[.+#u", $node,
                    $m)//До 25 апреля 2018 г. 23:59 [
                   || preg_match("#^С\s*(?<time>[\d\:]+)\s*(?<date>\d+\s*\w+\s*\d{4})\s*г\.#u", $node,
                       $m)//До 25 апреля 2018 г. 23:59 [
                || preg_match("#^Bis (?<date>.+? \d+:\d+) \[.+#", $node, $m)//Bis 19. August 2014 23:59 [
                || preg_match("#^(?:Fins al|À partir du|Jusqu'au) (?<date>.+? \d+:\d+) \[.+#", $node, $m)//Fins al 11 d’agost de 2018 23:59 [; À partir du 10 septembre 2020 23:47 [EAT]:
                || preg_match("#^(?<date>\d{4}年\d+月\d+日 \d+:\d+) \[.+#u", $node, $m)//2018年9月10日 11:59 [
                || preg_match("#^Hasta (?<date>.+ \d+:\d+) \[.+#", $node, $m)//Hasta 22 de septiembre de 2015 15:59 [
                || preg_match("#^Até (?<date>.+ \d+:\d+) \[.+#", $node, $m)//Até 8 de maio de 2019 23:59 [
                || preg_match("#^(?<date>\d{4}年\d+月\d+日 下午\d+:\d+) \[.+#u", $node, $m)//2019年3月5日 下午11:59 [
                || preg_match("#^Þangað til (?<date>\d+.+? \d+:\d+) \[.+#u", $node,
                    $m)//Þangað til 14. mars 2019 23:59 [
                || preg_match("#^จนถึง (?<date>\d+.+? \d+:\d+) \[.+#u", $node, $m)//จนถึง 15 พฤศจิกายน ค.ศ. 2019 23:59 [
                || preg_match("#^(?<date>\d+.+? \d+:\d+) \[.+#u", $node, $m)//2020. július 11. 23:59 [
                || preg_match("#^\w+\s(?<time>\d{1,2}:\d{1,2})\s\w+\s(?<date>[A-z]+\s\d+,\s\d{2,4})$#u", $node, $m)//From 16:40 on January 23, 2020 [
                || preg_match("#^\w+\s(?<date>[A-z]+\s\d+,\s\d{2,4})\s(?<time>\d{1,2}:\d{1,2}(?:\s*[ap]m))\s\[.+#ui", $node, $m)//Til July 27, 2020 11:59 PM [CEST] : NOK 0
                || preg_match("#Fino al (?<date>.+? \d+:\d+) \[.+#u", $node, $m) //it //Fino al 21 luglio 2021 23:59 [CEST]: € 0
                || preg_match("#Până la (?<date>.+? \d+:\d+) \[.+#u", $node, $m) //ro //Până la 17 decembrie 2020 23:59 [CET]: € 0
                || preg_match("#^Iki\s*(?<date>\d{4}.+? \d+:\d+) \[.+#u", $node, $m)// lt //Iki 2020 m. rugpjūčio 22 d. 16:00 [BST] : £0
                || preg_match("#^Đến\s*(?<date>\d+ .+ \d{4}.*? \d+:\d+) \[.+#u", $node, $m)// vi //Đến 28 tháng 12, 2020 23:59 [+07]: VND 0
                || preg_match("#^Do\s*(?<date>\d+\. .+ \d{4}\.? \d+:\d+) \[.+#u", $node, $m)// hr //Do 24. lipnja 2021. 23:59 [CEST] : € 0
                || preg_match("#^Til\s*(?<date>\d+\. .+ \d{4} \d+:\d+) \[.+#u", $node, $m)// no //Til 23. august 2021 14:00 [CEST] : NOK 0
                || preg_match("#^До\s*(?<date>\d+ .+ \d{4} г\. \d+:\d+) ч\. \[.+#u", $node, $m)// bg //До 10 септември 2021 г. 23:59 ч. [EEST]: 0 lei
                || preg_match("#^Od\s*(?<date>\d+\. .+ \d{4})\s*\((?<time>\d{1,2}:\d{2})\)\s*$#u", $node, $m)// sk //Od 12. októbra 2021 (17:55)
                || preg_match("#^À partir du\s*(?<date>\d+ .+ \d{4}) à (?<time>\d{1,2}:\d{2})\s*$#u", $node, $m)// fr //À partir du 12 octobre 2021 à 22:30
                || preg_match("#^Sehingga\s*(?<date>\d+ .+ \d{4}) (?<time>\d{1,2}:\d{2}) [A-Z]{2,4} \[.+#u", $node, $m)// ms //Sehingga 20 Ogos 2022 11:59 PTG [+08] : MYR 0
                || preg_match("#^Until\s*(?<time>[\d\:]+)\s*on\s*(?<date>\d+\s*\w+\s*\d{4})$#", $node, $m)// ms //Sehingga 20 Ogos 2022 11:59 PTG [+08] : MYR 0
            )
        ) {
            $dateStr = $m['date'];

            if (isset($m['time']) && !empty($m['time'])) {
                $dateStr .= ' ' . $m['time'];
            }
            $date = strtotime($this->normalizeDate($dateStr));

            if ($date !== false) {
                $h->booked()
                    ->deadline($date);

                return;
            }
        }

        $fromRe = [
            // en // From 22:35 on 24 May 2021
            "/^\s*From (?<time>\d{1,2}:\d{2}) on (?<date>\d+ \w+ \d{4})\s*$/",
            // nl // Vanaf 00:00 op 13 september 2021
            "/^\s*Vanaf (?<time>\d{1,2}:\d{2}) op (?<date>\d+ \w+ \d{4})\s*$/",
        ];

        if ($zero) {
            $next = $this->http->FindSingleNode("(//*[{$this->eq($this->t('Cancellation cost'))}])[1]/following::text()[{$this->eq($node)}][1]/following::text()[normalize-space()!=''][2]");

            foreach ($fromRe as $re) {
                if (preg_match($re, $node)
                    && preg_match($re, $next, $m) && !empty($m['date']) && !empty($m['time'])
                ) {
                    $date = strtotime($this->normalizeDate($m['date'] . ', ' . $m['time']));

                    if ($date !== false) {
                        $h->booked()
                            ->deadline($date);
                    }
                }
            }
        }

        if (empty($cancellationText = $h->getCancellation())) {
            $this->checkActualNonRefund($h);

            return;
        }

        if (preg_match("#You (?:can|may) cancel free of charge until (\d+) days? before arrival#i", $cancellationText, $m)
            || preg_match("#If cancell?ed or modified up to (\d+) days? before date of arrival, no fee will be charged#i", $cancellationText, $m)
            || preg_match("#If cancell?ed or modified up to (\d+) days? before the date of arrival, no fee will be charged#i", $cancellationText, $m)
            || preg_match("#If cancell?ed up to (\d+) days? before the date of arrival, no fee will be charged#i", $cancellationText, $m)
            || preg_match("#If cancell?ed up to (\d+) days? before date of arrival, no fee will be charged#i", $cancellationText, $m)
            || preg_match("#Rezervaci můžete zrušit zdarma do (\d+) dne před příjezdem\.#i", $cancellationText, $m)
            || preg_match("#В случае отмены или изменения бронирования в срок до (\d+) суток до даты заезда штраф не взимается#i", $cancellationText, $m)
            || preg_match("#Вы можете бесплатно отменить бронирование в срок вплоть до (\d+) дней до заезда#i", $cancellationText, $m)
            || preg_match("#Вы можете бесплатно отменить бронирование в срок вплоть до (\d+) дня до заезда#i", $cancellationText, $m)
            || preg_match("#Voit peruuttaa ilmaiseksi viimeistään (\d+) päivää ennen saapumista#iu", $cancellationText, $m) // fi
            || preg_match("#Bis zu (\d+) Tage vor der Anreise können Sie kostenfrei stornieren#iu", $cancellationText, $m) // de
            || preg_match("#Pots cancel·lar gratis fins a (\d+) dies abans de l'arribada#iu", $cancellationText, $m) // ca
            || preg_match("#Você pode efetuar o cancelamento gratuitamente até (\d+) dias? antes da chegada#iu", $cancellationText, $m) // pt
            || preg_match("#Se você cancelar em até (\d+) dias antes da chegada nenhuma taxa será cobrada#iu", $cancellationText, $m) // pt
            || preg_match("#Se cancelado ou alterado até (\d+) dias antes da data de chegada não será cobrada qualquer penalidade#iu", $cancellationText, $m) // pt
            || preg_match("#Se você cancelar ou alterar em até (\d+) dias antes da chegada nenhuma taxa será cobrada#iu", $cancellationText, $m) // pt
            || preg_match("#Se cancelado até (\d+) dia antes da data de chegada: não será cobrada qualquer penalidade#iu", $cancellationText, $m) // pt
            || preg_match("/^Je kunt gratis annuleren tot (\d{1,3}) dagen voor aankomst\./iu", $cancellationText, $m) // zh
            || preg_match("#免费取消期限：截至入住日前(\d+)天。#iu", $cancellationText, $m) // zh
            || preg_match("#到着日の(\d+)日前の前日までは、無料でキャンセルできます。#iu", $cancellationText, $m) // ja
            || preg_match("#Þú getur afpantað þér að kostnaðarlausu þar til (\d+) dögum fyrir komu.#iu", $cancellationText, $m) // is
            || preg_match("#ท่านสามารถยกเลิกการจองได้ฟรีก่อนถึงวันเข้าพัก (\d+) วัน#iu", $cancellationText, $m) // th
            || preg_match("#^En cas d'annulation ou de modification jusqu'à (\d+) jours avant la date d'arrivée, l'établissement ne prélève pas de frais.#iu",
                $cancellationText, $m) // fr
            || preg_match("#^Rezerváciu môžete zrušiť bezplatne do (\d+) dní pred príchodom.#iu", $cancellationText, $m) // sk
            || preg_match("#^Ви можете безкоштовно скасувати бронювання безпосередньо за (\d+) днів до заїзду. #iu", $cancellationText, $m) // sk
            || preg_match("/^Brezplačno (?i)lahko odpoveste do\s+(\d{1,3})\s+dan pred prihodom\./u", $cancellationText, $m) // sl
            || preg_match("/^Jūs (?i)varat bez maksas atcelt rezervējumu līdz\s+(\d{1,3})\s+dienām pirms ierašanās\./u", $cancellationText, $m) // lv
        ) {
            $h->booked()
                ->deadlineRelative($m[1] . ' days', '00:00');

            return;
        } elseif (preg_match("#If cancell?ed or modified up to (\d+:\d+|\d\s*[ap]m) on the date of arrival, no fee will be charged#",
                $cancellationText, $m)
            || preg_match("#If cancell?ed up to (\d+:\d+|\d+\s*[ap]m) on the date of arrival, no fee will be charged#",
                $cancellationText, $m)
            || preg_match("#^Si cancelas o modificas la reserva antes de las (\d+:\d+|\d+\s*[ap]m) del día de llegada, el establecimiento no efectuará cargos#",
                $cancellationText, $m)
            || preg_match("#Μπορείτε να ακυρώσετε χωρίς χρέωση μέχρι τις (\d+:\d+|\d+\s*[ap]m) την ημέρα της άφιξης#u",
                $cancellationText, $m)
            || preg_match("#You may cancel free of charge until ([\d\:]+) on the day of arrival#u",
                $cancellationText, $m)
        ) {
            $h->booked()
                ->deadlineRelative('0 days', $m[1]);

            return;
        } elseif (preg_match('/' . ($cPatternKo = '체크인 날짜가 1일보다 더 많이 남았을 때까지는 무료 취소가 가능합니다.체크인 날짜까지 1일 남은') . '/', $cancellationText, $m) // ko
            || preg_match("/Vous (?i)pourrez annuler gratuitement votre réservation jusqu'à \d.+ le jour de l'arrivée\./", $cancellationText, $m) // fr
        ) {
            $dateDeadlineText = $this->http->FindSingleNode("//text()[contains(normalize-space(),'까지 무료로 취소 가능합니다')]", null, true, "/^(\d{4}\D{1,3}\d{1,2}\D{1,3}\d{1,2}\D{1,3}\d{1,2}:\d{2})(?::\d{2})?\s*\[\D+$/")
                ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),\"Annulable sans frais jusqu'au\")]", null, true, "/Annulable sans frais jusqu'au\s+(\d{4}\D{1,3}\d{1,2}\D{1,3}\d{1,2}\D{1,3}\d{1,2}:\d{2})(?::\d{2})?\s*\(\D+/")
            ;

            if (!empty($dateDeadlineText)) {
                $h->booked()->deadline(strtotime($this->normalizeDate($dateDeadlineText)));
            } elseif (preg_match('/' . $cPatternKo . '/', $cancellationText, $m)) {
                $h->booked()
                    ->deadlineRelative('2 days');
            }
        } elseif (!empty($h->getCancellation()) && empty($h->getDeadline())) {
            if (preg_match("/^hasta la fecha\:\s*(?<time>[\d\:]+)\,\s*(?<date>[\d\/]+)\s*(\(.+\))$/", $h->getCancellation(), $m)) {
                $h->booked()
                    ->deadline(strtotime($this->normalizeDate($m['date'] . ', ' . $m['time'])));
            }
        }

        if (!$this->checkActualNonRefund($h)) {
            $h->booked()
                ->parseNonRefundable("#^En cas d'annulation ou de modification jusqu'à (\d+) jours avant la date d'arrivée, l'établissement exige#iu")
                ->parseNonRefundable("#^Veuillez noter que le montant total de la réservation sera débité en cas d'annulation, de modification ou de non-présentation.#")
                ->parseNonRefundable("#en cas d'annulation, de modification ou de non-présentation, l'établissement prélève le montant total de la réservation.#")
                ->parseNonRefundable("#при отмене или изменении бронирования, а также в случае незаезда стоимость бронирования не возвращается#")
                ->parseNonRefundable("#Примите к сведению, что в случае отмены бронирования или незаезда взимается полная стоимость заказа#")
                ->parseNonRefundable("#if cancell?ed, modified or in case of no-show, no fee will be charged#")
                ->parseNonRefundable("#if cancell?ed, modified or in case of no-show, the total price of the reservation will be charged#")
                ->parseNonRefundable("#In caso di cancellazione o modifiche viene addebitato l\'intero importo del soggiorno#")
                ->parseNonRefundable("#^Please note, if canceled, modified or in case of no-show, \d+ percent of the total price of the reservation will be charged#i")
                ->parseNonRefundable("#^Rezervasyonun iptali, değiştirilmesi veya kullanılmaması durumunda toplam tutar sizden alınacaktır#i")
                ->parseNonRefundable("#^Turėsite sumokėti visą kainą, jei užsakymą atšauksite#i")
                ->parseNonRefundable("#^Por favor, observe que se você cancelar ou alterar: será cobrado o valor total da reserva#i")
                ->parseNonRefundable("#^Por favor, observe que se você cancelar, alterar ou em caso de não comparecimento: será cobrada uma porcentagem de#i")
                ->parseNonRefundable("#^Por favor, observe que se você cancelar, alterar ou não comparecer, será cobrado o valor total da reserva#i")
                ->parseNonRefundable("#^Turėsite sumokėti pirmosios nakties kainą, jei užsakymą atšauksite#i")
                ->parseNonRefundable("#请注意，如取消、修改订单或未如期入住，住宿提供方仍将收取全额费用。#i")
                ->parseNonRefundable("#Atención: si cancelas, modificas o no te presentas, el establecimiento cargará la estancia completa\.#")
                ->parseNonRefundable("/Bemærk (?i)venligst at hvis bookingen afbestilles eller ændres, eller i tilfælde af udeblivelse, opkræves den samlede pris for bookingen\./") // da
                ->parseNonRefundable("/^Bei Stornierung, Buchungsänderung oder Nichtanreise zahlen Sie als Gebühr einen Betrag in Höhe des Gesamtpreises/i") // de
                ->parseNonRefundable("/^If you cancel, modify the booking, or don't show up, the fee will be the total price of the reservation./i") // en
            ;
        }
    }

    private function checkActualNonRefund(\AwardWallet\Schema\Parser\Common\Hotel $h): bool
    {
        $node = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('Cancellation cost'))}] ]/*[2]/descendant::text()[string-length(normalize-space())>2][last()]")
            ?? $this->http->FindSingleNode("//*[ count(table)=2 and table[1][{$this->eq($this->t('Cancellation cost'))}] ]/table[2]/descendant::text()[string-length(normalize-space())>2][last()]")
        ;

        if (preg_match("#^该订单不可退款，#", $node)
            || preg_match("#^不可退款，也无法修改入住日期。#", $node)
            || preg_match("#^This reservation can't be canceled free of charge#", $node)
            || preg_match("#^O cancelamento desta reserva não é gratuito#", $node)
            || preg_match("#^Tai negrąžinamo apmokėjimo užsakymas. Negalima keisti viešnagės datų#", $node)
            || preg_match("#^Esta reserva não pode ser cancelada gratuitamente.#", $node)
            || preg_match("#^هذا الحجز غير قابل للاسترداد، وتغيير تواريخ إقامتك غير ممكن.#", $node)
            || preg_match("#^This booking is non-refundable\.#i", $node)
            || preg_match("/^Diese Buchung ist nicht kostenfrei stornierbar\./i", $node) // de
        ) {
            $h->booked()->nonRefundable();

            return true;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($strDate, $lastTime = false)
    {
        $this->logger->debug('normalizeDate (in) : ' . $strDate);

        $strDate = str_replace(['（', '）'], ['(', ')'], $strDate);

        if (preg_match("#\(\s*(\d+:\d+\D*)\s*(?:至|～|-)\s*(\d+:\d+\D*)\s*\)#", $strDate, $m)) {
            //（14:00至23:30）;（14:00～00:00）; (14:00 - 14:00)
            if ($lastTime) {
                $strDate = str_replace($m[0], '(' . $m[2] . ')', $strDate);
            } else {
                $strDate = str_replace($m[0], '(' . $m[1] . ')', $strDate);
            }
        }

        $strDate = preg_replace('#\b(下午\b|오후\b|μ\.μ\.)#u', 'PM', $strDate);

        $this->logger->debug('normalizeDate (time) : ' . $strDate);
        //		$year = date("Y", $this->date);

        $in = [
            /* 1
            25 Ekim 2018, Perşembe (11:00 saatine kadar)
            24 Ekim 2018, Çarşamba  14:00
            */
            "#^\s*(\d+)[.]?\s+([^\d\s]+)\s+(\d{4}),\s*[^\d\s]*(?:\s+|\s*\(\D*)(\d{1,2}:\d{2}(?:\s*[AP][M])?)\D*$#ui",

            // 2
            // 29 Mayıs 2020, Cuma
            // ponedjeljak, 25. srpnja 2022.
            "#^\s*(?:[[:alpha:]\x{0E00}-\x{0E7F}]+[,.]?\s+)?(\d+)[.]?\s+([^\d\s]+?)[،]?\s+(\d{4})\b\s*\D*\s*$#ui",

            // 3
            //2020. július 11. 23:59
            //2018. január 5. (péntek)  14:00
            //2018. augusztus 15. (szerda) (12:00 óráig)
            //2018 m. lapkričio 3 d., šeštadienis  12:00
            //2020. július 17. (P) (14:00 órától)
            "#^\s*(\d{4})(?:\s*m)?[.]?\s+([^\d\s\.\,]+)[.]?\s+(\d{1,2})[.]?(?:\s+\D*)?(\d{1,2}:\d{2}(?:\s*[AP][M])?)\D*$#ui",

            // 4
            //13. helmikuuta 2018 23.59
            //9 Απριλίου 2018 5:59 pm
            //20. Mai 2019 23:59
            //dilluns, 21 d'oct. de 2019 (a partir de les 14:00)
            //onsdag d. 15. august 2018 (13:30)
            //пятница, 8 августа 2014 г.  11:00
            //reede, 7. aprill 2017 (alates kella 15:00)
            //czwartek 19 października 2017 (od 14:30)
            //subota, 11. avgust 2018. (od 14:00)
            //среда, 6 мая 2015 (до 11:00)
            //reede, 7. aprill 2017  15:00
            //الاثنين 24 سبتمبر، 2018  15:00
            //piątek 6 października 2017 (14:00) Zmień
            //utorak, 24. listopada 2017. 2:00 PM
            //sábado, 21 de julio de 2018  (hasta las 12:00)
            //sábado, 21 de julio de 2018  12:00
            //22 de septiembre de 2015 15:59
            //11 d’agost de 2018 23:59
            //15 พฤศจิกายน ค.ศ. 2019 23:59
            "#^\s*(?:\D+\s+|)(\d{1,2})[.]?\s+(?:de\s+|d\')?([^\d\s\.\,،]+)[.،]?\s+(?:de\s+|ค\.ศ\.\s+)?(\d{4})[.,]?(?:\s*г\.)?\s*(?:\s+|\s*\(\D*)(\d{1,2})[.:](\d{2}(?:\s*[AP][M])?)\D*$#ui",

            // 5
            //reede, 7. aprill 2017
            //الاثنين 24 سبتمبر، 2018
            // יום חמישי, 21 במרץ 2019
            //fredag den 16. november 2012
            //terça-feira, 23 de abril de 2019
            //MARDI 04-JANV.-2022
            // onsdag d. 26. oktober 2022
            "/^\s*[-[:alpha:]]+(?: [-[:alpha:]]+)?[\.,\s]+(\d{1,2})[-.\s]+(?:de\s+|d\')?([[:alpha:]]+)[.]?(?:\s+de|\s*،)?[-.\s]+(\d{4})(?:\s*г\.)?\s*$/iu",

            //13
            //2021년 6월 10일 PM 11:59
            "/^(\d{4})\D+(\d+)\D+(\d+)\D+\s(A?P?M)\s*([\d:]+)$/u",

            // 6
            //2018年9月10日 11:59
            //2018年9月14日(金) （11:00まで）
            //2019年3月5日 下午11:59
            //2019年3月7日星期四 （从14:00起）
            // 2020년 4월 25일 (토) 15:00
            // 2020년 4월 26일 (일) (11:00까지)
            "#^\s*(\d{4})\s*(?:年|년)\s*(\d{1,2})\s*(?:月|월)\s*(\d{1,2})\s*(?:日|일)\s*\D*(\d{1,2}:\d{2})\D*$#ui",

            // 7
            //2019 年 8 月 17 日（星期六）
            "#^\s*(\d{4})\s*(?:年|년)\s*(\d{1,2})\s*(?:月|월)\s*(\d{1,2})\D*$#ui",

            // 8
            //Thursday, November 23, 2017 3:00 PM
            //Thursday, November 23, 2017 (from 15:00)
            //Thursday, November 23, 2017 (15:00)
            "#^\s*[^\d\W]+,\s*([^\d\s\.\,]+)\s+(\d{1,2}),\s*(\d{4})\s+(?:\D*)?(\d{1,2}:\d{2}(?:\s*[AP][M])?)\D*$#ui",

            // 9
            // Thứ 7 Ngày 2 Tháng 1 Năm 2021 12:00
            //Chủ Nhật Ngày 7 Tháng 2 Năm 2021 12:00
            "#^\s*\S+\s+\S+\s+Ngày\s+(\d{1,2})\s+Tháng\s+(\d{1,2})\s+Năm\s+(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[AP][M])?)\D*$#ui",

            // 10
            //Chủ Nhật Ngày 7 Tháng 2 Năm 2021
            "#^\s*\S+\s+\S+\s+Ngày\s+(\d{1,2})\s+Tháng\s+(\d{1,2})\s+Năm\s+(\d{4})\s*$#ui",

            // 11
            // 28 tháng 12, 2020 23:59
            "#^\s*(\d{1,2})\s+tháng\s+(\d{1,2})\s*,\s+(\d{4})\s+(\d{1,2}:\d{2}(?:\s*[AP][M])?)\D*$#ui",

            // 12
            // sestdiena, 2021. gada 10. jūlijs 14:00
            "/^[-[:alpha:]]+\s*,\s*(\d{4})[.\s]+[[:alpha:]]+\s+(\d{1,2})[.\s]+([[:alpha:]]{3,})\s*(\d{1,2}:\d{2}(?:\s*[AaPp][.\s]*[Mm][.\s]*)?)\D*$/u",

            // 13
            // 2021. június 27. (V)
            "/^\s*(\d{4})[.]?\s+([^\d\s\.\,]+)[.]?\s+(\d{1,2})[.]?\D*\s*$/ui",

            // 14
            // sestdiena, 2021. gada 10. jūlijs
            "/^[-[:alpha:]]+\s*,\s*(\d{4})[.\s]+[[:alpha:]]+\s+(\d{1,2})[.\s]+([[:alpha:]]{3,})\s*$/u",

            // 15
            //2018 m. lapkričio 3 d., šeštadienis
            "#^\s*(\d{4})(?:\s*m)?[.]?\s+([^\d\s\.\,]+)[.]?\s+(\d{1,2})[.]?(?:\s+\D*)?\s*$#ui",
        ];
        $out = [
            "$1 $2 $3, $4", //1
            "$1 $2 $3", //2
            "$3 $2 $1, $4", // 3
            "$1 $2 $3, $4:$5", // 4
            "$1 $2 $3", // 5
            "$3.$2.$1, $5$4", //13
            "$3.$2.$1, $4", // 6

            "$3.$2.$1", // 7
            "$2 $1 $3, $4", // 8
            "$3-$2-$1, $4", // 9
            "$3-$2-$1", // 10
            "$3-$2-$1, $4", // 11
            "$2 $3 $1, $4", // 12

            "$3 $2 $1", // 13
            "$2 $3 $1", // 14
            "$3 $2 $1", // 15
        ];

        $str = preg_replace($in, $out, $strDate);
        $this->logger->debug('normalizeDate (out): ' . $str);

        if (empty($str)) {
            if ($lastTime) {
                $str = preg_replace("#^\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日.*?\s*（(\d+:\d+)\s*(?:至|～)\s*(\d+:\d+)）$#u", "$3-$2-$1, $5", $strDate);
            } else {
                $str = preg_replace("#^\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日.*?\s*（(\d+:\d+)\s*(?:至|～)\s*(\d+:\d+)）$#u", "$3-$2-$1, $4", $strDate);
            }
        }

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } else {
                foreach (self::$dictionary as $lang => $dict) {
                    if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $lang)) {
                        $str = str_replace($m[1], $en, $str);

                        break;
                    }
                }
            }
        }

        return str_replace("&nbsp;", ' ', htmlentities($str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s, $currency)
    {
        if (empty($s)) {
            return null;
        }

        return PriceHelper::parse(trim($s), $currency);
    }

    private function currency($s)
    {
        $sym = [
            '₩'   => 'WON',
            'lei' => 'RON',
            'US$' => 'USD',
            'S$'  => 'SGD',
            'HK$' => 'HKD',
            '€'   => 'EUR',
            '£'   => 'GBP',
            'Rp ' => 'IDR',
            'zł'  => 'PLN',
            '¥'   => '¥',
            '￥'   => '¥',
            'руб' => 'RUB',
            'R$'  => 'BRL',
            '₹'   => 'INR',
            '元'   => 'CNY',
            '$'   => '$',
            'Rs.' => 'INR',
            '₪'   => 'ILS',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null, $type = 'eq', $regexp = null)
    {
        if ($type == 'contains') {
            $rule = $this->contains($field);
        } elseif ('starts' === $type) {
            $rule = $this->starts($field);
        } else {
            $rule = $this->eq($field);
        }

        if ($this->lang == 'he' || 'ja' === $this->lang || 'zh' === $this->lang) {
            return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!='' and normalize-space(.)!=':'][1]",
                $root, true, $regexp);
        }

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]",
            $root, true, $regexp);
    }

    private function nextTd($field, $root = null, $type = 'eq')
    {
        if ($type == 'contains') {
            $rule = $this->contains($field);
        } elseif ('starts' === $type) {
            $rule = $this->starts($field);
        } else {
            $rule = $this->eq($field);
        }

        if ($this->lang == 'he' || 'ja' === $this->lang) {
            return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::td[normalize-space(.)!='' and normalize-space(.)!=':'][1]",
                $root);
        }

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::td[normalize-space(.)!=''][1]", $root);
    }

    private function nextTds($field, $root = null, $type = 'eq')
    {
        if ($type == 'contains') {
            $rule = $this->contains($field);
        } elseif ('starts' === $type) {
            $rule = $this->starts($field);
        } else {
            $rule = $this->eq($field);
        }

        if ($this->lang == 'he' || 'ja' === $this->lang) {
            return $this->http->FindNodes(".//text()[{$rule}]/following::td[normalize-space(.)!='' and normalize-space(.)!=':'][1]",
                $root);
        }

        return $this->http->FindNodes(".//text()[{$rule}]/following::td[normalize-space(.)!=''][1]", $root);
    }

    private function nextCol($field, $root = null, $re = null)
    {
        $rule = $this->starts($field);

        return $this->http->FindSingleNode("(.//td[{$rule}])[1]/following-sibling::td[normalize-space(.)!=''][1]",
            $root, true, $re);
    }

    private function nextTable($field, $root = null, $re = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//table[{$rule}])[1]/following-sibling::table[normalize-space(.)!=''][1]",
            $root, true, $re);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
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

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (mb_stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && mb_stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
