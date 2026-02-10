<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Taxi extends \TAccountChecker
{
    public $mailFiles = "booking/it-551086890.eml, booking/it-551215384.eml, booking/it-574903149.eml, booking/it-574937770.eml, booking/it-574975779.eml, booking/it-575018563.eml, booking/it-575080197.eml, booking/it-606783358.eml, booking/it-662251638.eml, booking/it-97260257.eml, booking/it-97342503.eml, booking/it-97577005.eml, booking/it-97675791.eml, booking/it-97722773.eml, booking/it-97908540.eml, booking/it-98240817.eml, booking/it-98330414.eml";

    private $detectFrom = 'noreply.taxi@booking.com';
    private $detectSubject = [
        "en" => [
            "your taxi from",
            "Enjoy your journey? | ID:",
            "Your Booking.com booking has been cancelled;",
            "updated", // Booking ID #33142338 updated
            "your journey from",
        ],
        "pt" => [
            "Está a gostar da sua viagem? | ID:",
            'o seu motorista está a caminho',
            ', sua corrida saindo de',
            'Aproveitando sua corrida? | ID:',
        ],
        "it" => [
            "il tuo taxi ",
            'la tua corsa da',
            'Abbiamo cancellato la tua prenotazione',
            'La tua prenotazione è stata cancellata | ID:',
            'Hai gradito il viaggio? | ID:',
        ],
        "de" => [
            "Ihr Taxi vom ",
            "Ihre Fahrt ab Flughafen",
            "Hat Ihnen die Fahrt gefallen? | ID:",
            "Ihre Fahrt",
        ],
        "es" => [
            "tu taxi desde el ",
            ", tu trayecto desde",
            "Información importante sobre tu trayecto desde",
        ],
        "da" => [
            "Nyder du din rejse?",
            "Vigtig information om din tur fra",
        ],
        "nl" => [
            "uw taxi vanaf",
            "je vervoer vanaf",
            "Genoten van uw reis? | ID:",
        ],
        "ko" => [
            "예약 정보가 변경되었습니다",
            '에서 승차하는 차량 예약이 확정되었습니다',
        ],
        "ja" => [
            "からの車両に関する重要な情報",
            "旅程は快適でしたでしょうか?| ID:",
        ],
        "fr" => [
            "Tout s'est-il bien passé ? | référence :",
            "Information importante concernant votre course au départ de",
            "votre course est confirmée (départ :",
        ],
        "no" => [
            "Din Booking.com Taxis-bestilling er kansellert",
            "Hatt en god reise",
        ],
        'hr' => ['vaša je vožnja s lokacije'],
        'ru' => ['здесь приведены все детали вашей поездки'],
        'tr' => ['Yolculuğunuz keyifli geçti mi'],
        "lt" => ['pavėžėjimas patvirtintas'],
        "sv" => ['Här är all information om din resa från'],
        "zh" => ['からの車両に関する詳細情報です'],
        "id" => ['hier zijn alle details voor je vervoer vanaf'],
    ];
    private $emailSubject;

    private $detectBody = [
        'tr' => ['Yolculuğumu değerlendir'],
        'lt' => ['Lankstus paėmimo laikas'],
        'sv' => ['Information om föraren'],
        'id' => ['Gegevens chauffeur'],
        'zh' => ['予約番号:'],
        'hr' => ['Broj rezervacije'],
        'ru' => ['Что нужно знать в день поездки'],
        'en' => ['Your taxi from ', 'We hope you’re ready for your journey to', 'We hope your journey on', 'Taxi company:', "Taxi provided by", "Taxi company",
            "We have updated your booking with the following details", "Your driver is en-route", "Journey provided by",
            "Thank you for adding your flight details", "Enjoy a dedicated space with all the information you need for a smooth pick-up",
        ],
        'pt' => ['Táxi fornecido por', 'Viagem fornecida por', 'O seu motorista irá encontrar-se consigo', 'O seu táxi a partir de', 'Empresa de táxi',
            'O motorista estará no ponto de encontro no dia', 'Sobre o meu táxi', 'Provedor da corrida', 'Corrida fornecida por',
            'Falta pouco para a sua viagem de', 'Corrida programada', 'Cada detalhe do dia da sua viagem', ],
        'it' => ['Taxi fornito da', 'Fornitore del servizio', 'Azienda di taxi', "Ci auguriamo che il viaggio del", "Servizio fornito da"],
        'de' => ['Taxi bereitgestellt von', 'Transportanbieter:', 'Fahrt bereitgestellt von', 'wir hoffen, Sie sind bereit für Ihre Reise am Flughafen', 'Ihre Fahrt'],
        'es' => ['Taxi de', 'Gracias por añadir detalles de su vuelo a su reserva', 'Proveedor de transporte', 'Esperamos que tengas todo listo para tu viaje a', 'Tu trayecto'],
        'da' => ['Taxaudbyder', 'Vi håber du er klar til din rejse til', 'Udbyder'],
        'nl' => ['Uw taxi vanaf', 'Vervoersaanbieder', 'Vervoer verzorgd door'],
        'ko' => ['예약 정보를 아래와 같이 변경하였습니다', '서비스 제공업체:'],
        'ja' => ['サービス提供元', 'ご旅行当日に必要な情報はすべてここに'],
        'fr' => ['Course fournie par', 'Il est bientôt l\'heure de quitter', 'Toutes les informations pour préparer votre voyage', 'Votre véhicule'],
        'no' => ['Taxien kjøres av', 'Vi håper din reise den'],
        'pl' => ['Szczegóły rezerwacji'],
    ];

    // when there is more info in plain text than in html
    private $detectBodyPlane = [
        'lt' => ['Užsakymo numeris:'],
        'hr' => ['Susret s vozačem'],
        'sv' => ['Chaufför:'],
        'zh' => ['日時:'],
        'id' => ['Informatie voor de chauffeur:'],
        'fr' => ['*Passager *', "Date d'arrivée", "Nom :"],
        'en' => ['*Passenger*'],
        'de' => ['*Passagier*'],
        'es' => ['*Pasajero*'],
        'da' => ['*Passager*'],
        'pt' => ['*Passageiro*'],
        'it' => ['*Passeggero*'],
        'ja' => ['*乗客*'],
        'ko' => ['*탑승객 정보*'],
        'no' => ['*Passasjer*'],
        'ru' => ['*Пассажир*'],
        'pl' => ['*Pasażer*'],
    ];

    private $lang = '';
    private static $dictionary = [
        "en" => [
            //"Booking reference:" => "",
            "Booking ID" => ["Booking ID", "Ref:", "ID"],
            //"Name" => "",
            "Hi " => ["Hi ", "Thanks "],
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => ["Pick-up", "Pick-up location"],
            "Drop-off" => ["Drop-off", "Drop-off location"],
            "Payment"  => ["Payment", "Price", "Total price"],

            // type HtmlText
            "We hope you’re ready for your journey to" => "Your journey",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            "nearly time for your trip from" => "Il est bientôt l'heure de quitter",
            " to "                           => " à ",
            "Pick-up time:"                  => ["Heure de prise en charge :", "Pick-up time:"],
            "Trip date:"                     => ["Date de la course :", "Trip date:", "Pick-up date:"],

            // type Plain Text
            "Pick-up time" => ["Pick-up time", "Date & time"],
            //"Pick-up location" => "",
            //"Drop-off location" => "",
            //"Distance" => "",
            //'Pick-up date:' => ''

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "pt" => [
            "Booking reference:"         => "Referência da reserva:",
            "Booking ID"                 => ["ID da reserva", "Booking ID", "Ref:", "ID"],
            "Name"                       => "Nome",
            "Hi "                        => ["Olá ", "Olá,"],
            "you have cancelled booking" => "você cancelou a reserva ID Booking.com",

            // type HtmlTable
            "Pick-up"  => "Levantamento",
            "Drop-off" => "Devolução",
            "Payment"  => "Pagamento",

            // type HtmlText
            //"We hope you’re ready for your journey to" => "",
            //"will be at the meeting point on" => "",
            //"is on the way to" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",
            'Pick-up date:' => 'Data da recolha:',

            // type Plain Text
            "Pick-up time:"        => ["Hora de recolha:"],
            "Pick-up location"     => "Local de Recolha",
            "Drop-off location"    => "Destino",
            "Flight arrival date"  => "Data de chegada do voo",
            "Flight Arrival Time"  => "Hora do voo de chegada",
            "Distance"             => "Distância",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "it" => [
            "Booking reference:"         => "Numero di prenotazione:",
            "Booking ID"                 => ["Rif", 'ID della prenotazione:'],
            "Name"                       => "Nome",
            "Hi "                        => "Ciao ",
            "you have cancelled booking" => ["abbiamo dovuto cancellare la prenotazione", "Ti confermiamo la cancellazione della tua prenotazione"],

            // type HtmlTable
            "Pick-up"  => "Da:",
            "Drop-off" => "A:",
            "Payment"  => "Pagamento",

            // type HtmlText
            "We hope you’re ready for your journey to" => "Ci auguriamo che il viaggio del",
            //"will be at the meeting point on" => "",
            //"is on the way to" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time" => [""],
            "Pick-up location"    => "Luogo di prelievo:",
            "Drop-off location"   => "Luogo di destinazione:",
            "Flight arrival date" => "Data di arrivo del volo",
            "Flight Arrival Time" => "Orario di arrivo del volo",
            //"Distance" => "",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "de" => [
            "Booking reference:" => "Buchungsreferenz:",
            "Booking ID"         => ["Ref", 'Buchungs ID'],
            "Name"               => "Name",
            "Hi "                => "Hallo ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => "Abholort",
            "Drop-off" => "Zielort",
            "Payment"  => "Bezahlung",

            // type HtmlText
            "We hope you’re ready for your journey to" => "Wir hoffen, dass die Fahrt am",
            //"will be at the meeting point on" => "",
            //"is on the way to" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time"        => ["Abholzeit"],
            "Pick-up location"    => "Abholort",
            "Drop-off location"   => "Zielort",
            "Flight arrival date" => "Ankunftsdatum des Flugs",
            "Flight Arrival Time" => "Flug-Ankunftszeit",
            //"Distance" => "",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "es" => [
            "Booking reference:" => "Referencia de la reserva:",
            "Booking ID"         => ["Número de referencia"],
            "Name"               => "Nombre",
            "Hi "                => "Hola, ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => "Recogida",
            "Drop-off" => "Devolución",
            "Payment"  => "Pago",

            // type HtmlText
            //"We hope you’re ready for your journey to" => "",
            //"will be at the meeting point on" => "",
            //"is on the way to" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time"        => ["Hora de recogida"],
            "Pick-up location"    => "Lugar de recogida",
            "Drop-off location"   => "Lugar de devolución",
            "Flight arrival date" => "Fecha de recogida",
            "Flight Arrival Time" => "Hora de recogida",
            //"Distance" => "",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "da" => [
            "Booking reference:" => "Bookingreference:",
            "Booking ID"         => ["ID", "Booking-ID"],
            "Name"               => "Navn",
            "Hi "                => "Hej ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => "Fra",
            "Drop-off" => "Til",
            //            "Payment"  => "",

            // type HtmlText
            "We hope you’re ready for your journey to" => "Vi håber du er klar til din rejse til",
            //"will be at the meeting point on" => "",
            "is on the way to" => ", er på vej til ",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time"        => ["Afhentningstidspunkt"],
            "Pick-up location"    => "Afhentningssted",
            "Drop-off location"   => "Destination",
            "Flight arrival date" => "Dato for flyets ankomst",
            "Flight Arrival Time" => "Landingstidspunkt",
            "Distance"            => "Distance",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "nl" => [
            "Booking reference:" => "Referentienummer:",
            //            "Booking ID" => [""],
            "Name" => "Naam",
            "Hi "  => "Hallo ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => ["Ophalen"],
            "Drop-off" => ["Bestemming"],
            "Payment"  => ["Betaling"],

            // type HtmlText
            "We hope you’re ready for your journey to" => "We hopen dat uw reis op",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time" => ["Pick-up time", "Date & time"],
            //"Pick-up location" => "",
            //"Drop-off location" => "",
            //"Distance" => "",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "ko" => [
            "Booking reference:" => "예약번호:",
            "Booking ID"         => ["예약 코드:"],
            "Name"               => "성명",
            "Hi "                => "안녕하세요, ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => ["승차"],
            "Drop-off" => ["하차"],
            "Payment"  => ["결제"],

            // type HtmlText
            //"We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time"      => ["승차 날짜"],
            "Pick-up location"  => "픽업 장소:",
            "Drop-off location" => "하차 장소:",
            //            "Distance" => "",

            // Updated info
            "We have updated your booking with the following details" => '예약 정보를 아래와 같이 변경하였습니다',
            "Updated"                                                 => '업데이트됨',
            "Previous"                                                => '이전',
            //            "Comments" => "",
            "Cellphone" => "Cellphone",
        ],
        "ja" => [
            "Booking reference:"         => "予約番号:",
            "Booking ID"                 => ["Booking ID:"],
            "Name"                       => "氏名",
            "Hi "                        => "さん、こんにちは, ",
            "you have cancelled booking" => "がキャンセルされました。",

            // type HtmlTable
            "Pick-up"  => ["お迎え場所"],
            "Drop-off" => ["目的地"],
            //            "Payment"  => [""],

            // type HtmlText
            "We hope you’re ready for your journey to" => "次での快適なご旅程をお祈り申し上げます",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            "nearly time for your trip from" => "Falta pouco para a sua viagem de",
            " to "                           => " para ",
            "Pick-up time:"                  => "Hora da recolha:",
            "Trip date:"                     => "Data da viagem:",

            // type Plain Text
            "Pick-up time"      => ["ピックアップ時刻", "フライトの到着時刻"],
            "Pick-up location"  => "Pick-up location:",
            "Drop-off location" => "Drop-off location:",
            "Distance"          => "距離:",
            "Pick-up date:"     => "フライトの到着日：",

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "fr" => [
            "Booking reference:" => ["Numéro de réservation:"],
            "Booking ID"         => ["Réference de réservation :", "Référence de réservation"],
            "Name"               => "Nom",
            "Hi "                => "Bonjour ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => ["Prise en charge"],
            "Drop-off" => ["Destination"],
            //            "Payment"  => [""],

            // type HtmlText
            "We hope you’re ready for your journey to" => "Nous espérons que la course en date du",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time:"      => ["Horaire de prise en charge :", "Horaire de prise en charge", "Heure d'arrivée du vol"],
            "Pick-up location"   => "Lieu de prise en charge",
            "Drop-off location"  => "Lieu de dépôt",
            "Distance"           => "Distance",
            'Pick-up date:'      => ["Date d'arrivée du vol:", "Date de prise en charge:"],

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "no" => [
            "Booking reference:" => "Bookingreferanse:",
            "Booking ID"         => ["Bestillings-ID:"],
            "Name"               => "Navn",
            "Hi "                => "Hei ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            "Pick-up"  => ["Henting"],
            "Drop-off" => ["Levering"],
            //            "Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "Nous espérons que la course en date du",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time"      => ["Horaire de prise en charge"],
            "Pick-up location"  => "Hentested",
            "Drop-off location" => "Stoppested",
            //            "Distance" => "Distance",
            "Flight arrival date" => 'Ankomstdato flyvning',
            "Flight Arrival Time" => 'Ankomsttid flyvning',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "hr" => [
            "Booking reference:" => "Broj rezervacije:",
            "Booking ID"         => ["ID rezervacije:"],
            //"Name" => "",
            "Hi "  => "Pozdrav ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //            "Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time" => [""],
            //"Pick-up location" => "",
            //"Drop-off location" => "",
            //            "Distance" => "",
            // "Flight arrival date" => '',
            // "Flight Arrival Time" => '',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "ru" => [
            "Booking reference:" => "Номер бронирования:",
            "Booking ID"         => ["ID бронирования:"],
            "Name"               => "Имя:",
            "Hi "                => "Здравствуйте, ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //            "Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            "Pick-up time:"       => ["Время прибытия рейса"],
            "Pick-up location"    => "Место посадки:",
            "Drop-off location"   => "Место высадки:",
            "Distance"            => "Дистанция:",
            'Pick-up date:'       => 'Дата прибытия рейса:',
            "Flight arrival date" => 'Дата получения:',
            "Flight Arrival Time" => 'Время посадки:',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "tr" => [
            "Booking reference:" => "Rezervasyon referansı:",
            //"Booking ID"         => ["Rezervasyon referansı:"],
            //"Name"               => "",
            "Hi "                => "Merhaba ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //            "Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time:"     => [""],
            "Pick-up location"  => "Pick-up",
            "Drop-off location" => "Drop-off",
            //"Distance"          => "",
            //'Pick-up date:'     => '',
            // "Flight arrival date" => '',
            // "Flight Arrival Time" => '',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "lt" => [
            "Booking reference:" => "Užsakymo numeris:",
            //"Booking ID"         => [""],
            //"Name"               => "",
            "Hi "                => "Dėkojame, ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            "Payment"  => ["Visa kaina"],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time:"     => [""],
            "Pick-up location"  => "Pick-up location:",
            "Drop-off location" => "Drop-off location:",
            //"Distance"          => "",
            //'Pick-up date:'     => '',
            // "Flight arrival date" => '',
            // "Flight Arrival Time" => '',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "sv" => [
            "Booking reference:" => "Bokningsreferens:",
            "Booking ID"         => ["Boknings-ID:"],
            "Name"               => "Namn:",
            "Hi "                => "Hej ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //"Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time:"     => [""],
            "Pick-up location"  => "Upphämtningsplats:",
            "Drop-off location" => "Avlämningsplats:",
            "Distance"          => "Avstånd:",
            //'Pick-up date:'     => '',
            "Flight arrival date" => 'Flygets ankomstdatum:',
            "Flight Arrival Time" => 'Flygets ankomsttid',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "zh" => [
            "Booking reference:" => "予約番号:",
            "Booking ID"         => ["ID:"],
            //"Name"               => "",
            "Hi "                => "様、,",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //"Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time:"     => [""],
            "Pick-up location"  => "Pick-up location:",
            "Drop-off location" => "Drop-off location:",
            //"Distance"          => "",
            //'Pick-up date:'     => '',
            "Flight arrival date" => 'フライトの到着日：',
            "Flight Arrival Time" => 'フライトの到着時刻',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "id" => [
            "Booking reference:" => "Referentienummer:",
            "Booking ID"         => ["Boeking ID:"],
            "Name"               => "Naam:",
            //"Hi "                => "Hallo ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //"Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time:"     => [""],
            "Pick-up location"  => "Ophaallocatie:",
            "Drop-off location" => "Afleverlocatie:",
            //"Distance"          => "",
            //'Pick-up date:'     => '',
            "Flight arrival date" => 'Aankomstdatum vlucht:',
            "Flight Arrival Time" => 'Aankomsttijd vlucht',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
        "pl" => [
            "Booking reference:" => "Numer rezerwacji:",
            "Booking ID"         => ["ID rezerwacji"],
            "Name"               => "Nazwisko",
            //"Hi "                => "Hallo ",
            //"you have cancelled booking" => "",

            // type HtmlTable
            //            "Pick-up"  => [""],
            //            "Drop-off" => [""],
            //"Payment"  => [""],

            // type HtmlText
            //            "We hope you’re ready for your journey to" => "",
            //"is on the way to" => "",
            //"will be at the meeting point on" => "",

            //"nearly time for your trip from" => "",
            //" to " => "",
            //"Pick-up time:" => "",
            //"Trip date:" => "",

            // type Plain Text
            //"Pick-up time:"     => [""],
            "Pick-up location"  => "Miejsce odbioru",
            "Drop-off location" => "Miejsce zwrotu",
            //"Distance"          => "",
            //'Pick-up date:'     => '',
            "Flight arrival date" => 'Data przylotu',
            "Flight Arrival Time" => 'Godzina przylotu',

            // Updated info
            //            "We have updated your booking with the following details" => '',
            //            "Updated" => '',
            //            "Previous" => '',
            //            "Comments" => "",
            //            "Cellphone" => "",
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->detectBody();

        // Travel Agency
        $email->obtainTravelAgency();

        $type = '';

        if ($this->http->XPath->query("//*[{$this->contains($this->t("We have updated your booking with the following details"))}]")->length > 0) {
            $xpathTHead = "*[2][{$this->eq($this->t("Updated"))}] and *[3][{$this->eq($this->t("Previous"))}]";
            $updated = $this->http->FindNodes("//thead[ tr[{$xpathTHead}] ]/following-sibling::tbody/tr/*[1][normalize-space()]"
                . " | //thead[ tr[{$xpathTHead}] ]/following-sibling::tr/*[1][normalize-space()]"
                . " | //table/tr[normalize-space()][1][{$xpathTHead}]/following-sibling::tr/*[1][normalize-space()]"
            );

            if (count($updated) === 1
                || count($updated) > 1 && empty(array_diff($updated, (array) $this->t("Comments"), (array) $this->t("Cellphone")))
            ) {
                // it-97342503.eml
                $email->setIsJunk(true);

                return $email;
            }
        }

        $this->emailSubject = $parser->getSubject();

        if (!empty($this->http->FindSingleNode("(//td[" . $this->eq($this->t("Pick-up")) . "])[1]"))
            && !empty($this->http->FindSingleNode("(//td[" . $this->eq($this->t("Drop-off")) . "])[1]"))) {
            $type = 'HtmlTable';
            $this->parseHtmlTable($email);
        }

        if (count($email->getItineraries()) == 0 && !empty($parser->getPlainBody())) {
            // it-97577005.eml
            $body = $parser->getPlainBody();

            foreach ($this->detectBodyPlane as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false) {
                        $this->lang = $lang;
                        $type = 'Plain';
                        $this->parsePlaneText($email, $body);

                        return $email;
                    }
                }
            }
        }

        if (count($email->getItineraries()) == 0) {
            $type = 'HtmlText';
            $this->parseHtmlText($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang) . $type);

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        if (stripos($headers['from'], $this->detectFrom) === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,"booking.com") or contains(@href, "/booking.rideways.com/")] | //text()[contains(.,"booking.com")]')->length === 0) {
            return false;
        }

        return $this->detectBody();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtmlTable(Email $email): void
    {
        $this->logger->debug(__METHOD__);

        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking reference:")) . "])[1]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"));

        $t = $email->add()->transfer();

        // General
        $conf = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("Booking ID"))}][1]", null, true, "/\b{$this->opt($this->t("Booking ID"))}[\s:#]*(\d{5,})\b/u")
            ?? $this->re("/\b{$this->opt($this->t("Booking ID"))}[\s:#]*(\d{5,})\b/u", $this->emailSubject)
        ;

        if (!empty($conf)) {
            $t->general()
                ->confirmation($conf);
        } else {
            $t->general()
                ->noConfirmation();
        }

        $traveller = $this->nextTd($this->t("Name"), "/^\s*([[:alpha:] \-]+)\s*$/u");

        if (!empty($traveller)) {
            $t->general()
                ->traveller($traveller, true);
        }

        if (empty($traveller)) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi '))}]", null, "/^\s*{$this->opt($this->t("Hi "))}\s*([[:alpha:] ]+?)\s*(?:[,.]|님!)\s*/u"));

            if (in_array($this->lang, ['ja'])) {
                $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Hi '))}]", null, "/^\s*([[:alpha:] ]+?){$this->opt($this->t("Hi "))}\s*/u"));
            }

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $t->general()->traveller($traveller, false);
            }
        }

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("you have cancelled booking")) . "])[1]"))) {
            $t->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        // Segments
        $s = $t->addSegment();

        // Departure
        $address = null;
        $date = $this->nextTd($this->t("Pick-up date"));
        $time = $this->nextTd($this->t("Pick-up time"));

        if (!empty($date) && !empty($time)
            || !empty($time) && strlen($time) > 10
        ) {
            $date = implode(', ', array_filter([$date, $time]));
            $address = $this->nextTd($this->t("Pick-up"));
        } else {
            $date = null;
        }

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Pick-up")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]");
            $address = implode(' ', $this->http->FindNodes("//td[not(.//td) and " . $this->eq($this->t("Pick-up")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]/ancestor::td[1]/descendant::text()[normalize-space()][not(contains(normalize-space(), ':')) and not(contains(normalize-space(), '·'))]"));

            if (!empty($date) && empty($address)) {
                $addressTD = implode("\n", $this->http->FindNodes("//td[not(.//td) and " . $this->eq($this->t("Pick-up")) . "]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][2]/ancestor::td[1]/descendant::text()[normalize-space()]"));

                if (preg_match("/^(?:.+?\b\d{1,2}:\d{2}(?:[apAPmM.\s*]*)?|.*\d{1,2} [[:alpha:]]+ \d{4} · \d{1,2}\.\d{2})\s*\n([\s\S]+)/u", $addressTD, $m)) {
                    $address = $m[1];
                }
            }
        }
        $s->departure()
            ->date($this->normalizeDate($date));

        if (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\),\s*(.+)/", $address, $m)) {
            $s->departure()
                ->code($m[2])
                ->name($m[1])
                ->address($m[3])
            ;
        } elseif (preg_match("/^([^,]+?)\s*,\s*(.+)/", $address, $m)) {
            $s->departure()
                ->name($m[1])
                ->address($m[2])
            ;
        } elseif (preg_match("/^(.+)\s+\-\s+(.+\-.*)$/", $address, $m)) {
            $s->departure()
                ->name($m[1])
                ->address($m[2])
            ;
        }

        // Arrival
        if (!empty($s->getDepDate())) {
            $s->arrival()->noDate();
        }
        $address = $this->http->FindSingleNode("//td[not(.//td) and " . $this->eq($this->t("Drop-off")) . "]/following-sibling::td[normalize-space()][1]");

        if (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\),\s*(.+)/", $address, $m)) {
            $s->arrival()
                ->code($m[2])
                ->name($m[1])
                ->address($m[3])
            ;
        } elseif (preg_match("/^([^,]+?)\s*,\s*(.+)/", $address, $m)) {
            $s->arrival()
                ->name($m[1])
                ->address($m[2])
            ;
        } elseif (preg_match("/^(?<name>.+)?(?<address>Unnamed Road.*)$/", $address, $m)) {
            if (isset($m['name']) && !empty($m['name'])) {
                $s->arrival()
                    ->name($m['name']);
            }
            $s->arrival()
                ->address($m['address'])
            ;
        } elseif (preg_match("/^(.+)\s+\-\s+(.+\-.*)$/", $address, $m)) {
            $s->arrival()
                ->name($m[1])
                ->address($m[2])
            ;
        }

        // Price
        $total = $this->nextTd($this->t("Payment"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $t->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
    }

    private function parseHtmlText(Email $email): void
    {
        $this->logger->debug(__METHOD__);
        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking reference:")) . "])[1]/following::text()[normalize-space()][1]", null, true,
        "/^\s*(\d{5,})\s*$/"));

        $t = $email->add()->transfer();

        $traveller = $this->http->FindSingleNode("//text()[normalize-space()='Contact details']/following::text()[normalize-space()][1]", null, true, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])$/");

        if (empty($traveller)) {
            $traveller = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Hi ")) . "])[1]", null, true,
                "/^\s*" . $this->opt($this->t("Hi ")) . "\s*([[:alpha:] ]+)\s*[,.]\s*/u");
        }

        // General
        $t->general()
            ->noConfirmation()
            ->traveller($traveller, false);

        if (!empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("you have cancelled booking")) . "])[1]"))) {
            $t->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        // Segments
        $s = $t->addSegment();

        // Departure
        $from = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("We hope you’re ready for your journey to")) . "])[1]", null, true,
            "/" . $this->opt($this->t("We hope you’re ready for your journey to")) . "\s*([^\.]+)\./");

        if (empty($from)) {
            $from = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("nearly time for your trip from")) . "])[1]", null, true,
                "/" . $this->opt($this->t("nearly time for your trip from")) . "\s*([^\.]+)" . $this->opt($this->t(" to ")) . "/");
        }

        if (empty($from)) {
            $from = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Pick-up location:")) . "])[1]/ancestor::tr[1]", null, true,
                "/" . $this->opt($this->t("Pick-up location:")) . "\s*([^\.]+)/");
        }

        if (empty($from)) {
            $from = $this->http->FindSingleNode("//text()[normalize-space()='Your journey']/following::text()[normalize-space()][2]");
        }

        if (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\),\s*(.+)/", $from, $m)) {
            $s->departure()
                ->code($m[2])
                ->name($m[1])
                ->address($m[3])
            ;
        } elseif (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\)$/", $from, $m)) {
            $s->departure()
                ->code($m[2])
                ->name($m[1])
            ;
        } elseif (preg_match("/^([^,]+?)\s*,\s*(.{5,})/", $from, $m)) {
            $s->departure()
                ->name($m[1])
                ->address($m[2])
            ;
        } else {
            $s->departure()
                ->name($from);
        }
        $date = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("will be at the meeting point on")) . "])[1]", null, true,
            "/" . $this->opt($this->t("will be at the meeting point on")) . "\s*([^\.]+)\./");

        if (empty($date)) {
            $date = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Trip date:")) . "])[1]", null, true,
                "/" . $this->opt($this->t("Trip date:")) . "\s*(.+)/");

            $time = $this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Pick-up time:")) . "])[1]", null, true,
                "/" . $this->opt($this->t("Pick-up time:")) . "\s*(.+)/");

            if (!empty($date) && !empty($time)) {
                $date .= ', ' . $time;
            } else {
                $date = null;
            }
        }

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[normalize-space()='Flight Arrival Time:']/ancestor::tr[1]/descendant::td[normalize-space()][2]");
        }

        if (empty($date)) {
            $date = $this->http->FindSingleNode("//text()[normalize-space()='Your journey']/following::text()[normalize-space()][1]");
        }

        if (!empty($date)) {
            $s->departure()
                ->date($this->normalizeDate($date))
            ;
            $s->arrival()
                ->noDate();
        }

        // Arrival
        $to = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("is on the way to")) . "])[1]", null, true,
            "/" . $this->opt($this->t("is on the way to")) . "\s*([^\.]+)\./");

        if (empty($to)) {
            $to = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("nearly time for your trip from")) . "])[1]", null, true,
                "/" . $this->opt($this->t("nearly time for your trip from")) . "\s*[^\.]+" . $this->opt($this->t(" to ")) . "([^\.]+)\./");
        }

        if (empty($to)) {
            $to = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Drop-off location:")) . "])[1]/ancestor::tr[1]", null, true,
                "/" . $this->opt($this->t("Drop-off location:")) . "\s*([^\.]+)/");
        }

        if (empty($to)) {
            $to = $this->http->FindSingleNode("//text()[normalize-space()='Change route']/preceding::text()[normalize-space()][1]");
        }

        if (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\),\s*(.+)/", $to, $m)) {
            $s->arrival()
                ->code($m[2])
                ->name($m[1])
                ->address($m[3])
            ;
        } elseif (preg_match("/^([^,]+?)\s*,\s*(.{5,})/", $to, $m)) {
            $s->arrival()
                ->name($m[1])
                ->address($m[2])
            ;
        } else {
            $s->arrival()
                ->name($to);
        }

        if (!empty($s->getDepName()) && !empty($s->getArrName()) && empty($date)) {
            $s->departure()
                ->noDate()
            ;
            $s->arrival()
                ->noDate();
        }

        // Price
        $total = $this->nextTd($this->t("Payment"));

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $t->price()
                ->total(PriceHelper::cost($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }
    }

    private function parsePlaneText(Email $email, string $text): void
    {
        $this->logger->debug(__METHOD__);

        // Travel Agency
        $email->ota()
            ->confirmation($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Booking reference:")) . "])[1]/following::text()[normalize-space()][1]", null, true,
                "/^\s*(\d{5,})\s*$/"));

        $t = $email->add()->transfer();

        $bookingID = $this->re("/\n\s*" . $this->opt($this->t("Booking ID")) . "[ :]+(\d{5,})\s*\n/", $text);

        if (!empty($bookingID)) {
            $t->general()
                ->confirmation($bookingID);
        } else {
            $t->general()
                ->noConfirmation();
        }

        // General
        $traveller = $this->re("/\n\s*" . $this->opt($this->t("Name")) . "[ :]+([[:alpha:] \.\'\-]+)(?: [A-Z]{2}\d{5,})?\s*\n/u", $text);

        if (empty($traveller) && $this->lang === 'zh') {
            $traveller = $this->re("/([[:alpha:] \-]+)(?: [A-Z]{2}\d{5,})?\s+" . $this->opt($this->t("Hi ")) . "/u", $text);
        }

        $t->general()
            ->traveller($traveller, true);

        if (!empty($this->re("/" . $this->opt($this->t("you have cancelled booking")) . "/", $text))
            || !empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("you have cancelled booking")) . "])[1]"))
        ) {
            $t->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        // Segments
        $s = $t->addSegment();

        // Departure
        $date = $this->re("/\n\s*" . $this->opt($this->t("Pick-up time")) . "[ :]+(.*\b\d{4}\b.*|.*[^\d\n]\d{4}[^\d\n].*)\s*\n/u", $text);

        if (empty($date) && preg_match("/" . $this->opt($this->t("Flight arrival date")) . "[ :]+(.+)\s+" . $this->opt($this->t("Flight Arrival Time")) . "[ :]+(.+)/u", $text, $m)) {
            $date = $m[1] . ', ' . $m[2];
        }

        if (empty($date) && preg_match("/{$this->opt($this->t('Pick-up date:'))}\s*(.+)\n{$this->opt($this->t('Pick-up time:'))}\s*([\d\:]+)/u", $text, $m)) {
            $date = $m[1] . ', ' . $m[2];
        }

        if (!empty($date)) {
            $s->departure()
                ->date($this->normalizeDate($date));
        }

        $from = $this->re("/\n\s*" . $this->opt($this->t("Pick-up location")) . "[ :]*(.+)\s*\n/u", $text);

        if (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\),\s*(.+)/u", $from, $m)) {
            $s->departure()
                ->code($m[2])
                ->name($m[1])
                ->address($m[3])
            ;
        } elseif (preg_match("/^([^,]+?)\s*,\s*(.+)/u", $from, $m)) {
            $s->departure()
                ->name($m[1])
                ->address($m[2])
            ;
        } elseif (preg_match("/^(.+)/u", $from, $m)) {
            $s->departure()
                ->address($m[1])
            ;
        }

        // Arrival
        if (!empty($s->getDepDate())) {
            $s->arrival()->noDate();
        }
        $to = $this->re("/\n\s*" . $this->opt($this->t("Drop-off location")) . "[ :]*(.+)\s*\n/u", $text);

        if (preg_match("/^([^,]+?)\s*\(([A-Z]{3})\),\s*(.+)/", $to, $m)) {
            $s->arrival()
                ->code($m[2])
                ->name($m[1])
                ->address($m[3]);
        } elseif (preg_match("/^([^,]+?)\s*,\s*(.+)/", $to, $m)) {
            $s->arrival()
                ->name($m[1])
                ->address($m[2]);
        } elseif (preg_match("/^(.+\-.+)/", $to, $m)) {
            $s->arrival()
                ->address($m[1]);
        }

        $s->extra()
            ->miles($this->re("/\n\s*" . $this->opt($this->t("Distance")) . "[ :]+(.+)\s*\n/u", $text), true, true);
    }

    private function detectBody(): bool
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//node()[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
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

    private function nextTd($field, $regexp = null, $root = null): ?string
    {
        return $this->http->FindSingleNode(".//text()[{$this->eq($field)}][1]/ancestor::td[1]/following-sibling::td[1]", $root, true, $regexp);
    }

    private function normalizeDate($date)
    {
        // 2022년 9월 26일 월요일 · 오후 2:30 -> 2022년 9월 26일 월요일 · 2:30 pm
        $date = preg_replace("/([ ·]+)오후 (\d{1,2}:\d{2})\s*$/", "$1 $2pm", $date);
        //$this->logger->warning('$date = ' . print_r($date, true));

        $in = [
            //Saturday, 19 June 2021 · 09:00; Segunda-feira, 14 de Junho de 2021 0:35; venerdì 16 luglio 2021 · 23.35; Montag, 5. Juli 2021 · 10:30
            // 13. juli 2021 · 18:00
            "/^\s*(?:[[:alpha:] \-]+\s*[,\s])?\s*(\d+)[.]?\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})\W+(\d{1,2})[:.](\d{2}(?:\s*[ap]m)?)\s*$/iu",
            //Wednesday, June 16, 2021 · 9:00 AM
            "/^\s*[[:alpha:] \-]+\s*[,\s]\s*([[:alpha:]]+)\s+(\d+)\s*[\s,]\s*(\d{4})\W+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu",
            // 21:25 יום חמישי 17 יוני 2021
            "/^\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s+[[:alpha:] \-]+\s*[,\s]\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu",
            //Sunday, September 5, 2021 · 8:00 AM
            "/^\w+\,\s*(\w+)\s*(\d+)\,\s*(\d{4})\D+([\d\:]+\s*A?P?M)$/",
            // 2022年9月18日 8:30; 2022年9月18日 · 8:30; 2022년 9월 26일 월요일 · 2:30 pm
            "/^\s*(\d{4})\s*[年년]\s*(\d{1,2})\s*[月월]\s*(\d{1,2})\s*[日일火曜]*(?:\s+[[:alpha:]]+)?[\s·]+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/u",
            // petak, 27. listopada 2023., 23:00
            "/^\w+\,\s*(\d+)\.\s*(\w+)\s*(\d{4})\.\,\s*([\d\:]+)$/u",
            // понедельник, 16 октября 2023 г., 07:25
            "/^\w+\,\s*(\d+)\s*(\w+)\s*(\d{4})\s*\w+\.\,\s*([\d\:]+)$/u",
            // 2 Kasım 2023 Perşembe · 01:50
            "/^(\d+)\s+(\w+)\s+(\d{4})\s+\w+\s*[·]\s+([\d\:]+)$/u",
            //2023 m. lapkričio 14 d., antradienis, 23:10
            "/^(\d{4})\s+m\.\s*(\w+)\s*(\d+)\s*d\.\,\s*\w+\,\s*([\d\:]+)$/u",
            //2023年11月2日木曜日, 14:00
            "/^\s*(\d{4})[年](\d+)[月](\d+)[日]\D+\,\s*([\d\:]+)\s*$/u",
            //السبت، ١٨ نوفمبر ٢٠٢٣  ·  ٩:٢٥ ص
            "/^\w+[،]\s*(\w+)\s*(\w+)\s*(\w+)\s+[·]\s+([\d\:]+)\s*[ص]$/u",
        ];
        $out = [
            "$1 $2 $3, $4:$5",
            "$2 $1 $3, $4",
            "$2 $3 $4, $1",
            "$2 $1 $3, $4",
            "$1-$2-$3, $4",
            "$1.$2.$3, $4",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4",
            "$3 $2 $1, $4",
            "$3.$2.$1, $4",
            "$1 $2 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } else {
                foreach (["pt", "he", "hr", "ar", "sk", "es"] as $lang) {
                    if ($en = MonthTranslate::translate($m[1], $lang)) {
                        $date = str_replace($m[1], $en, $date);

                        break;
                    }
                }
            }
        }

        return strtotime($date);
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
