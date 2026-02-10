<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Email\Email;

class It3207108 extends \TAccountCheckerExtended
{
    public $mailFiles = "airbnb/it-140756719.eml, airbnb/it-147943771.eml, airbnb/it-151043568.eml, airbnb/it-15180453.eml, airbnb/it-3207108.eml, airbnb/it-3264578.eml, airbnb/it-3291697.eml, airbnb/it-3297748.eml, airbnb/it-3364738.eml, airbnb/it-3712228.eml, airbnb/it-3712236.eml, airbnb/it-3717906.eml, airbnb/it-50091438.eml, airbnb/it-51333938.eml, airbnb/it-51559304.eml, airbnb/it-51566083.eml, airbnb/it-51773591.eml, airbnb/it-60568143.eml, airbnb/it-6077551.eml, airbnb/it-6474590.eml, airbnb/it-6632001.eml, airbnb/it-66656523.eml, airbnb/it-6666561.eml, airbnb/it-6830442.eml, airbnb/it-80524120.eml, airbnb/it-8205266.eml, airbnb/it-8557490.eml, airbnb/it-9217584.eml, airbnb/it-9217603.eml, airbnb/it-9494208.eml, airbnb/it-95046782.eml, airbnb/it-9658840.eml"; // +3 bcdtravel(html)[en,es]

    public $reBody = "Airbnb";

    // don't add detections if email is for host(not for travelers). This emails goes to JunkComingSoon
    public $langDetectors = [
        "id" => [
            "Reservasi Anda sudah",
        ],
        "da" => [
            "Din reservation er bekræftet",
            'Pak din kuffert!',
        ],
        "de" => [
            "Stelle sicher, dass du dir die Hausregeln und Ausstattung noch einmal anschaust.",
            "Was erwartet dich",
            "Im Voraus bestätigen / Ablehnen",
            "Bestätigen/Ablehnen ",
            "Vollständigen Reiseplan anzeigen",
            "Buchung der Unterkunft",
            'Buchung abschließen',
            'Bereite dich auf die Ankunft von',
            'Mach dich bereit für deine anstehende Reise',
        ],
        "es" => [
            "La reserva se ha confirmado",
            "Infórmate bien",
            "Solicitud enviada",
            "confirmado para las siguientes fechas",
            "Una vez que todas las personas de tu grupo paguen",
            "Aceptar o rechazar",
            "La reserva aún no está confirmada",
            "Recibo de la reservación",
            "Para tu protección y seguridad",
            "Nueva reservación confirmada",
            "Tu reservación está confirmada",
            "¡Haz las maletas!",
        ],
        "zh" => ["您的预订已经确认", "您的預訂已確認", "要收拾行李囉！"],
        "nl" => [
            "Zorg ervoor dat je de huisregels en voorzieningen goed controleert.",
            "Je reservering is bevestigd",
            "Accepteren/Weigeren",
            "Je aanvraag is verstuurd",
            "Aanvraag verzonden",
            "Antwoorden",
            "Naar je reisschema",
            "Bekijk volledig reisschema",
        ],
        "fr" => [
            "vous serez les bienvenus chez nous",
            "Sachez à quoi vous attendre",
            "Demande envoyée",
            "Accepter/Refuser",
            "a trouvé un autre logement",
            "Pré-approuver / Refuser",
            "Votre demande a été envoyée",
            "Reçu de réservation",
            "Faites connaissance avec votre hôte",
        ],
        "pt" => [
            "Saiba o que esperar",
            "Aceitar/Recusar",
            "Pré-aprovar ou Recusar esta Consulta",
            "Seu pedido foi enviado",
            "Para sua proteção e segurança",
            "Vai viajar para o",
            "Concluir reserva",
            "Prepare-se para sua próxima viagem",
        ],
        "hu" => [
            "Tudd, hogy mire számíthatsz",
            "Elfogadás/Elutasítás",
            "Kérésedet elküldtük",
            "Erre az e-mailre válaszolva küldhetsz üzenetet",
        ],
        "sv" => [
            "Din bokning är bekräftad",
            "Förhandsgodkänn / avböj",
            'Det är nästan dags för din resa till Málaga',
        ],
        "ru" => [
            "Пакуйте чемоданы!",
            "Знайте, чего ожидать",
            "Узнайте, чего ожидать",
            "Предварительно подтвердить или отклонить",
            "Ваш запрос был отправлен",
            "Отдельная комната у хозяина",
        ],
        "it" => [
            "La tua prenotazione è confermata",
            "Accetta/Rifiuta",
            "Pre-approva / Rifiuta",
            "Cosa devi aspettarti",
            "prenotazione non è stata confermata",
        ],
        "pl" => [
            "Czas się pakować!",
            "Twoja rezerwacja jest potwierdzona",
            "Twoja prośba została wysłana",
        ],
        "ja" => ["リクエストを送信しました", 'さあ、パッキングしましょう!'],
        "cs" => ["Zobrazit celý itinerář"],
        "no" => ["Forhåndsgodkjenn / Avslå", "Vit hva du kan forvente deg", "Helt hjem / hel leilighet har"],
        "tr" => ["Rezervasyonunuz onaylandı"],
        "ca" => ["Accepta/rebutja"],
        "fi" => ["Peruuta pyyntö"],
        'el' => ['Η κράτησή σας επιβεβαιώθηκε', 'Ετοιμάστε βαλίτσες!'],
        "he" => ["מה מחכה לך", "הנכס כולו אצל המארח/ת"],
        "ko" => [
            "여행 관련 정보입니다.",
            "숙박할 시간이 다가오고 있습니다",
        ],
        "lv" => [
            "Rezervācija ir apstiprināta",
        ],
        "sk" => [
            "Je načase pripraviť sa na cestu",
        ],
        "en" => [
            "Know what to expect",
            "Pay for your trip",
            "Your request was sent",
            "Your reservation is confirmed",
            "Booking confirmed",
            "Request sent",
            "Pre-approve / Decline",
            "Booking receipt",
            'Meet your host',
            'to meet you',
            'For your protection and safety',
            'Your villa is booked',
        ],
    ];

    public $emailForHosterDetectors = [
        'en' => [
            'Send a message to confirm check-in details or welcome',
            'Your cancellation policy for guests is ',
            'A prompt response helps guests finalize their trips.',
            'negatively impact your response rate and your listing’s placement in search.',
            'Why can’t I see my guest’s profile photo?',
        ],
        'de' => [
            'Eine schnelle Antwort hilft Gästen bei der Reiseplanung.',
            'Warum sehe ich bei meinem Gast kein Profilbild?',
        ],
        'es' => [
            '¿Por qué no puedo ver la foto de perfil de mi huésped?',
            'Cuanto antes respondas, más tiempo tendrán los huéspedes para organizar el viaje',
            'ocupa tu anuncio en los resultados de búsqueda pueden verse afectados',
        ],
        'pt' => [
            'Uma resposta rápida ajuda os hóspedes a finalizarem as reservas.',
            'negativamente sua taxa de resposta e a posição do seu anúncio nos resultados de busca',
            'A agilidade na hora de responder pode ajudar hóspedes a finalizarem as reservas',
            'negativamente sua taxa de resposta e a posição do seu anúncio na busca',
            'Por que não posso ver a foto de perfil de um hóspede?',
        ],
        'fr' => [
            'Pourquoi le voyageur n\'a-t-il pas de photo de profil ?',
            'Une réponse rapide permet aux voyageurs de finaliser leurs préparatifs',
            'négatives sur votre taux de réponse et sur le classement de votre annonce dans les résultats de recherche',
        ],
    ];
    public $reFrom = "@airbnb.com";
    public $reSubject = [
        "id"  => "Reservasi terkonfirmasi untuk",
        "el"  => "Η κράτηση για",
        "da"  => "Reservationen for",
        "de"  => "Buchung bestätigt",
        "de2" => "Erinnerung an deine Buchun",
        //		"de3" => "Buchung der Unterkunft",
        "es"  => "Reserva confirmada",
        "es2" => "Solicitud de reserva enviada para",
        "es3" => "Recordatorio de reserva:",
        "es4" => "Solicitud de reserva enviada para",
        "es5" => "Reservación confirmada para",
        "zh"  => "预订已确认",
        "zh2" => "的預訂已確認",
        "預訂小提醒：",
        "nl"  => "Reservering Bevestigd",
        "nl2" => "Lopend: Reserveringsaanvraag voor",
        "nl3" => "Reserveringsaanvraag gestuurd voor",
        "fr"  => "Réservation confirmée",
        "fr2" => "Rappel de la réservation",
        "fr3" => "Demande pour",
        "fr4" => "Reçu de la réservation",
        "pt"  => "está confirmada para",
        "pt2" => "Sua viagem para ",
        "hu"  => "visszaigazolta a foglalásodat",
        "hu2" => "Foglalási kérés elküldve",
        "sv"  => "Bokning bekräftad för",
        "ru"  => "Напоминаем о бронировании на",
        "ru2" => "Вопрос о бронировании",
        "ru3" => "Бронирование в ",
        "it"  => "prenotazione confermata",
        "it2" => "In sospeso: richiesta di prenotazione presso",
        "it3" => "Richiesta per l'annuncio",
        "pl"  => "Przypomnienie o rezerwacji",
        "ja"  => "に予約リクエストを送信しました",
        "予約リマインダ―",
        "cs"  => "Rezervace v",
        "no"  => "Forespørsel om",
        "no2" => "Reservasjon bekreftet for",
        "tr"  => "için rezervasyon onaylandı",
        "ca"  => "Pendent: sol·licitud de reserva",
        "fi"  => "Varauspyyntö lähetetty koskien kohdetta",
        "he"  => "הזמנתך אושרה",
        "ko"  => "예약이 확정되었습니다",
        "en"  => "Reservation Confirmed",
        "en2" => "Booking request sent for",
        "en3" => "/Reservation reminder[ ]*\-[ ]*.+/",
        "lv"  => "/.+ rezervācija ir apstiprināta/",
        // sk
        'Pripomenutie rezervácie – ',
    ];

    private $words = [
        'en'  => 'Send',
        'de'  => 'Antworten',
        'fr'  => 'Pré-approuver / Refuser',
        'en2' => 'Pre-approve / Decline',
        'en3' => 'Book It',
        'pt'  => 'Responder',
    ];
    private $lang = 'en';

    private static $dict = [
        'id' => [
            'Your reservation is' => 'Reservasi Anda sudah',
            'Check In'            => 'Check-in',
            'Check Out'           => 'Check-out',
            //            'reservation_code' => '',
            'Reservation Code' => 'Kode reservasi',
            //            'check_in' => 'check_in',
            //            'check_out' => 'check_out',
            //            'amount' => 'betalt beløb',
            //            'Amount' => 'Betalt beløb',
            //			'Occupancy taxes and fees' => '',
            //            'Apartment' => '',
            //            'guests' => '',
            //            'House' => '',
            'Address' => ['Alamat'],
            //            'is your host' => '',
            //            'Message Host' => '',
            'Guest'  => 'Tamu',
            'Guests' => 'Tamu',
            //            'hosted by' => '',
            //            'Respond to' => '',
            //            '’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.ch/rooms', 'www.airbnb.co.id/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => 'Kebijakan pembatalan',
            'Total'                => ['Jumlah yang dibayarkan'],
            //            'Rent in' => '',
            //            'place is located in' => '',
            //            'View full itinerary' => '',
        ],
        'da' => [
            'Your reservation is' => 'Din reservation er',
            'Check In'            => 'søndag',
            'Check Out'           => 'torsdag',
            //            'reservation_code' => '',
            'Reservation Code' => 'Reservationskode',
            //            'check_in' => 'check_in',
            //            'check_out' => 'check_out',
            'amount' => ['betalt beløb', 'beløb'],
            'Amount' => ['Betalt beløb', 'Beløb'],
            //			'Occupancy taxes and fees' => '',
            //            'Apartment' => '',
            //            'guests' => '',
            //            'House' => '',
            'Address' => ['Adresse'],
            //            'is your host' => '',
            //            'Message Host' => '',
            'Guest'  => 'Gæster',
            'Guests' => 'Gæster',
            //            'hosted by' => '',
            //            'Respond to' => '',
            //            '’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.ch/rooms', 'www.airbnb.dk/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => 'Annulleringspolitik',
            'Total'                => ['Betalt beløb'],
            //            'Rent in' => '',
            //            'place is located in' => '',
            //            'View full itinerary' => '',
        ],
        'de' => [
            'Your reservation is' => 'Deine Buchung wurde',
            'Check In'            => 'Check-In',
            'Check Out'           => 'Check-Out',
            'reservation_code'    => 'buchungscode',
            'Reservation Code'    => ['Buchungscode', 'Bestätigungs-Code'],
            'check_in'            => 'check_in',
            'check_out'           => 'check_out',
            'amount'              => 'betrag',
            'Amount'              => 'Betrag',
            //			'Occupancy taxes and fees' => '',
            'Apartment'    => 'Wohnung',
            'guests'       => 'gäste',
            'House'        => 'NOTTRANSLATED',
            'Address'      => ['Adresse', 'Treffpunkt'],
            'is your host' => 'ist dein Gastgeber',
            'Message Host' => 'Nachricht an den Gastgeber',
            'Guest'        => 'Gäste',
            'Guests'       => 'Gäste',
            'hosted by'    => 'vermietet von',
            'Respond to'   => 'Auf die Anfrage von',
            '’s inquiry'   => 'antworten',
            //			'beforeConfirmStatus' => 'Anfrage',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.ch/rooms', 'www.airbnb.de/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            'Total'   => ['Gesamte Bezahlung', 'Gesamtbetrag'],
            'Rent in' => 'zur Miete in',
            //            'place is located in' => '',
            //            'View full itinerary' => '',
        ],
        'es' => [
            'Your reservation is' => 'La reserva se ha',
            'Check In'            => 'Llegada',
            'Check in'            => 'llegada',
            'Check Out'           => 'Salida',
            'reservation_code'    => 'código_de_reserva',
            'Reservation Code'    => ['Código de reserva', 'Código de confirmación', 'Código de reservación'],
            'check_in'            => 'llegada',
            'check_out'           => 'salida',
            'amount'              => ['importe', 'monto'],
            'Amount'              => ['Importe', 'Monto'],
            'Total'               => ['Importe', 'Pago total', 'Monto pagado'],
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'Apartamento',
            'House'     => 'Departamento',
            'Address'   => ['Dirección', 'Dirección del alojamiento'],
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guests'     => 'Huéspedes',
            'Guest'      => 'huéspede',
            'hosted by'  => '- Anfitrión:',
            'Respond to' => 'Responde a la solicitud de',
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => 'la solicitud',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.es/rooms', 'es.airbnb.com/rooms'],
            'Confirmation Code:'   => ['Código de confirmación:', 'Código de reservación'],
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => ['Política de cancelación', 'Cancellation policy:'],
            'View full itinerary'  => 'Mostrar itinerario completo',
        ],
        'zh' => [
            'Your reservation is' => '您的预订已经',
            'Check In'            => '入住日期',
            'Check Out'           => '退房日期',
            'reservation_code'    => '预订编号',
            'Reservation Code'    => ['预订编号', '預訂編號'],
            'check_in'            => '入住日期',
            'check_out'           => '退房日期',
            'amount'              => '金额',
            'Amount'              => '金额',
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'NOTTRANSLATED',
            'House'     => '独立屋',
            'Address'   => '地址',
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'  => '位房客',
            'Guests' => '房客人數',
            //			'hosted by' => '',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            'Total' => '已付金額',
            //            'View full itinerary' => '',
        ],
        'nl' => [
            //			'Your reservation is' => '',
            'Check In'         => 'Aankomst',
            'Check Out'        => 'Vertrek',
            'reservation_code' => 'reserveringscode',
            'Reservation Code' => 'Reserveringscode',
            'check_in'         => 'aankomst',
            'check_out'        => 'vertrek',
            'amount'           => 'bedrag',
            'Amount'           => 'Bedrag',
            'Total'            => ['Betaald bedrag', 'Total payment'],
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'Appartement',
            'House'     => 'Gehele woning',
            'Address'   => 'Adres',
            //			'is your host' => '',
            //			'Message Host' => '',
            //'Guest' => 'NOTTRANSLATED',
            'Guests'               => ['Gasten', 'Gast'],
            'guests'               => ['gasten', 'gast'],
            'hosted by'            => 'verhuurd door',
            'Respond to'           => 'Reageer op',
            '’s inquiry'           => '’s Aanvraag',
            'beforeConfirmStatus'  => 'Aanvraag',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.nl/rooms', 'www.airbnb.be/rooms'],
            //			'Confirmation Code:' => '',
            'Request sent' => 'Je aanvraag is verstuurd',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => 'Annuleringsvoorwaarden',
            'View full itinerary'  => 'Bekijk volledig reisschema',
        ],
        'fr' => [
            'Your reservation is'      => 'Votre réservation est',
            'Check In'                 => ['Arrivée', 'L\'arrivée', 'Heure d\'arrivée flexible'],
            'Check in'                 => 'entrée dans',
            'Check Out'                => 'Départ',
            'reservation_code'         => 'code_de_réservation',
            'Reservation Code'         => ['Code de réservation', 'Code de confirmation'],
            'check_in'                 => 'arrivée',
            'check_out'                => 'départ',
            'amount'                   => 'montant',
            'Amount'                   => ['Montant', 'Montant payé'],
            'Total'                    => 'Paiement total',
            'Occupancy taxes and fees' => 'Taxes de séjour et frais',
            'Apartment'                => 'Appartement',
            'House'                    => 'Logement entier',
            'Address'                  => ['Adresse', 'Adresse du logement'],
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'      => 'guest',
            'Guests'     => 'Voyageurs',
            'guests'     => 'invités',
            'hosted by'  => ['- hôte :', '- Hôte : '],
            'Respond to' => ['Répondez à la demande de', 'Répondre à la demande de'],
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => ['demande', 'demande'],
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.fr/rooms'],
            'Confirmation Code:'   => ['Code de confirmation:', 'Code de confirmation :'],
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => ['Conditions d\'annulation', 'Cancellation policy:'],
            //            'View full itinerary' => '',
        ],
        'pt' => [
            'Your reservation is' => 'Sua reserva está',
            'Check In'            => ['O horário estabelecido para check-in', 'O check-in é', 'O horário de check-in é flexível'],
            'Check Out'           => 'Checkout',
            'reservation_code'    => 'código_de_reserva',
            'Reservation Code'    => ['Código de Reserva', 'Código de reserva', 'Código de confirmação'],
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => ['valor', 'custo total'],
            'Amount' => ['Valor', 'Custo total'],
            'Total'  => ['Valor pago', "Custo total"],
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'Appartement',
            //			'House' => '',
            'Address' => 'Endereço',
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'  => 'NOTTRANSLATED',
            'Guests' => 'Hóspedes',
            //			'guests' => '',
            'hosted by'  => 'hospedado por',
            'Respond to' => ['Responder à consulta de', 'Responder ao Pedido de', 'Responda à consulta de'],
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => ['consulta', 'Pedido', 'consulta'],
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.com.br/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => 'Política de cancelamento:',
            //            'View full itinerary' => '',
        ],
        'hu' => [
            'Your reservation is' => 'A foglalásod',
            //			'Check In' => '',
            //			'Check Out' => '',
            'reservation_code' => 'foglalás_kódja',
            'Reservation Code' => 'Foglalás kódja',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => 'Összeg',
            'Amount' => 'Összeg',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => 'Appartement',
            //			'House' => 'NOTTRANSLATED',
            'Address' => 'Cím',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => 'NOTTRANSLATED',
            'Guests' => 'Vendégek',
            //			'guests' => '',
            'hosted by'            => 'házigazdája',
            'Respond to'           => 'Válaszolj',
            '’s inquiry'           => 'kérésére',
            'beforeConfirmStatus'  => 'kérésére',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.hu/rooms'],
            //			'Confirmation Code:' => '',
            'Request sent' => 'Kérésedet elküldtük',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            'Total' => 'Teljes fizetés',
            //            'View full itinerary' => '',
        ],
        'sv' => [
            'Your reservation is' => 'Din bokning är',
            //			'Check In' => '',
            //			'Check Out' => '',
            'reservation_code' => 'bokningskod',
            'Reservation Code' => 'Bokningskod',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => 'belopp',
            'Amount' => 'Belopp',
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => 'Appartement',
            //			'House' => 'NOTTRANSLATED',
            'Address' => 'Adress',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => 'NOTTRANSLATED',
            'Guests' => 'Gäster',
            //			'guests' => '',
            'hosted by' => 'hos värden',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'ru' => [
            'Your reservation is' => 'Ваше бронирование',
            'Check In'            => ['Прибытие', 'Время прибытия'],
            'Check Out'           => 'Выезд',
            'reservation_code'    => 'Код_бронирования',
            'Reservation Code'    => 'Код бронирования',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => 'Сумма',
            'Amount' => 'Сумма',
            'Total'  => ['Выплаченная сумма', 'Общий платеж', 'Общий платёж'],
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'Квартира',
            //			'House' => 'NOTTRANSLATED',
            'Address' => 'Адрес',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => 'NOTTRANSLATED',
            'Guests' => 'Гости',
            //			'guests' => '',
            'hosted by'  => 'у хозяина',
            'Respond to' => 'Ответьте пользователю',
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => 'запрос',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.ru/rooms'],
            //			'Confirmation Code:' => '',
            'Request sent' => 'запрос был отправлен',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => 'Правила отмены:',
            //            'View full itinerary' => '',
        ],
        'it' => [
            'Your reservation is' => 'La tua prenotazione è',
            'Check In'            => 'Check-in',
            'Check Out'           => 'check-out',
            'reservation_code'    => 'codice_di_prenotazione',
            'Reservation Code'    => 'Codice di prenotazione',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => ['costo totale', 'totale'],
            'Amount' => ['Costo totale', 'Totale'],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'Appartamento',
            'House'     => 'Intera casa / apt',
            'Address'   => 'Indirizzo',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            'Guests' => 'Ospiti',
            //			'guests' => '',
            'hosted by'  => 'da ',
            'Respond to' => 'Rispondi alla richiesta di',
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => 'richiesta',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.it/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => 'non è stata confermata',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'pl' => [
            'Your reservation is' => 'Twoja rezerwacja jest',
            'Check In'            => 'Zameldowanie między',
            'Check Out'           => 'Wymeldowanie do',
            'reservation_code'    => 'kod_rezerwacji',
            'Reservation Code'    => 'Kod rezerwacji',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => ['kwota'],
            'Amount' => ['Kwota'],
            'Total'  => ['Opłacona kwota'],
            //			'Occupancy taxes and fees' => '',
            'Apartment' => 'apartment',
            //			'House' => '',
            'Address' => ['Adres'],
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            'Guests' => 'Goście',
            //			'guests' => '',
            'hosted by' => 'gospodarza',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms'],
            'Confirmation Code:'   => 'Zasady anulowania',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'ja' => [
            //			'Your reservation is' => '',
            //			'Check In' => '',
            //			'Check Out' => '',
            //			'reservation_code' => '',
            //			'Reservation Code' => '',
            //			'check_in' => '',
            //			'check_out' => '',
            //			'amount' => [''],
            //			'Amount' => [''],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => '',
            //			'House' => '',
            //			'Address' => '',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            //			'Guests' => '',
            //			'guests' => '',
            'hosted by'        => 'さんの',
            'Address'          => '住所',
            'Guests'           => '人数',
            'amount'           => ['金額'],
            'Amount'           => ['金額'],
            'Reservation Code' => ['予約コード'],
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.jp/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'cs' => [
            'Your reservation is' => 'Tvá rezervace je',
            //			'Check In' => '',
            //			'Check Out' => '',
            'reservation_code' => 'rezervační_kód',
            'Reservation Code' => 'Rezervační kód',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => ['Částka'],
            'Amount' => ['Částka'],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => '',
            //			'House' => '',
            'Address' => 'Adresa',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            'Guests' => 'Hosté',
            //			'guests' => '',
            'hosted by' => 'u hostitele',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.cz/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'no' => [
            'Your reservation is' => 'Reservasjonen din er',
            'Check In'            => 'Innsjekkingstid er',
            'Check Out'           => 'Sjekk ut',
            //			'reservation_code' => '',
            'Reservation Code' => 'Reservasjonskode',
            //			'check_in' => '',
            //			'check_out' => '',
            //			'amount' => [''],
            //			'Amount' => [''],
            'Total' => 'Betalt beløp',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => '',
            //			'House' => '',
            'Address' => 'Adresse',
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'  => 'Gjester',
            'Guests' => ['Gjester'],
            //			'guests' => '',
            'hosted by'            => 'som vert',
            'Respond to'           => 'Svar på',
            '’s inquiry'           => 'forespørsel',
            'beforeConfirmStatus'  => 'forespørsel',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.no/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'tr' => [
            'Your reservation is' => 'Rezervasyonunuz',
            //			'Check In' => '',
            //			'Check Out' => '',
            'reservation_code' => 'rezervasyon_kodu',
            'Reservation Code' => 'Rezervasyon Kodu',
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => ['miktar'],
            'Amount' => ['Miktar'],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => '',
            //			'House' => '',
            'Address' => 'Adres',
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'  => 'Misafirler',
            'Guests' => ['Misafirler'],
            //			'guests' => '',
            'hosted by' => 'Ev sahipliğini',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.com.tr/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'ca' => [
            //			'Your reservation is' => '',
            //			'Check In' => '',
            //			'Check Out' => '',
            //			'reservation_code' => '',
            //			'Reservation Code' => '',
            //			'check_in' => '',
            //			'check_out' => '',
            //			'amount' => [''],
            //			'Amount' => [''],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => '',
            //			'House' => '',
            //			'Address' => '',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            'Guests' => ['Hostes'],
            //			'guests' => '',
            //			'hosted by' => '',
            'Respond to' => 'Respon a la sol·licitud de',
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => 'la sol·licitud',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.cat/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'fi' => [
            //			'Your reservation is' => '',
            //			'Check In' => '',
            //			'Check Out' => '',
            //			'reservation_code' => '',
            //			'Reservation Code' => '',
            //			'check_in' => '',
            //			'check_out' => '',
            //			'amount' => [''],
            //			'Amount' => [''],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => '',
            //			'House' => '',
            //			'Address' => '',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            //			'Guests' => [''],
            //			'guests' => '',
            'hosted by' => 'jonka majoittajana toimii',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.fi/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => 'Pyyntösi on lähetetty',
            //			'This is not a confirmed' => '',
            //			'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'el' => [
            'Your reservation is' => 'Η κράτησή σας',
            'Check In'            => 'Η άφιξη',
            'Check Out'           => 'Αναχώρηση έως τις',
            'reservation_code'    => 'Κωδικός_κράτησης',
            'Reservation Code'    => 'Κωδικός κράτησης',
            'check_in'            => 'Ά‌φ‌ι‌ξ‌η‌',
            'check_out'           => 'Α‌π‌ο‌χ‌ώ‌ρ‌η‌σ‌η‌',
            'amount'              => ['Ποσό'],
            'Amount'              => ['Ποσό'],
            //			'Total' => '',
            //			'Occupancy taxes and fees' => '',
            //			'Apartment' => 'Πάτε',
            //			'House' => 'Πάτε',
            'Address' => 'Διεύθυνση',
            //			'is your host' => '',
            //			'Message Host' => '',
            //			'Guest' => '',
            'Guests' => ['Επισκέπτες'],
            //			'guests' => '',
            //            'hosted by' => 'Πάτε',
            //			'Respond to' => '',
            //			'’s inquiry' => '',
            //			'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.fi/rooms', 'www.airbnb.gr/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => 'Pyyntösi on lähetetty',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => 'Πολιτική ακύρωσης',
            //            'View full itinerary' => '',
        ],
        'he' => [
            'Your reservation is' => 'ההזמנה שלך',
            'Check In'            => "שעות הצ'ק-אין",
            'Check Out'           => "צ'ק-אאוט",
            //            'reservation_code' => '',
            'Reservation Code' => 'קוד הזמנה',
            //            'check_in' => '',
            //            'check_out' => '',
            //            'amount' => '',
            //            'Amount' => '',
            'Total' => 'יתרה לתשלום',
            //            'Occupancy taxes and fees' => '',
            //            'Apartment' => '',
            //            'House' => '',
            'Address' => 'כתובת',
            //            'is your host' => '',
            //            'Message Host' => '',
            //            'Guest' => '',
            'Guests' => 'אורחים',
            //            'guests' => '',
            'hosted by' => 'הנכס כולו אצל המארח/ת',
            //            'Respond to' => '',
            //            '’s inquiry' => '',
            //            'beforeConfirmStatus' => '',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'he.airbnb.com/rooms'],
            //            'Confirmation Code:' => '',
            //            'Request sent' => '',
            //            'This is not a confirmed' => '',
            'Cancellation policy:' => 'מדיניות ביטולים:',
            //            'View full itinerary' => '',
        ],
        'ko' => [
            'Your reservation is' => '예약이 확정되었습니다',
            'Check In'            => ['체크인 가능 시간', '체크인 시간'],
            'Check Out'           => '체크아웃 마감 시간',
            //            'reservation_code' => 'código_de_reserva',
            'Reservation Code' => ['예약 코드'],
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => ['지급 금액', '금액'],
            'Amount' => ['지급 금액', '금액'],
            //          'Total' => 'Valor pago',
            //			'Occupancy taxes and fees' => '',
            //          'Apartment' => 'Appartement',
            //			'House' => '',
            'Address' => '주소',
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'  => 'NOTTRANSLATED',
            'Guests' => '인원',
            //			'guests' => '',
            'hosted by' => '님이 호스팅하는 집 전체',
            //            'Respond to' => [''],
            //			'’s inquiry' => '',
            'beforeConfirmStatus'  => ['확정되었습니다'],
            'www.airbnb.com/rooms' => ['www.airbnb.co.kr/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            'Cancellation policy:' => '환불 정책',
            //            'View full itinerary' => '',
        ],
        'lv' => [
            //            'Your reservation is' => '',
            //            'Check In' => [''],
            //            'Check Out' => 'Checkout',
            //            'reservation_code' => 'código_de_reserva',
            'Reservation Code' => ['Rezervācijas kods'],
            //			'check_in' => '',
            //			'check_out' => '',
            //            'amount' => '',
            //            'Amount' => '',
            'Total' => ['Samaksātā summa', 'Apmaksājamā summa'],
            //			'Occupancy taxes and fees' => '',
            //          'Apartment' => 'Appartement',
            'House'   => 'Visa māja/dzīvoklis',
            'Address' => 'Adrese',
            //			'is your host' => '',
            //			'Message Host' => '',
            'Guest'  => 'NOTTRANSLATED',
            'Guests' => 'Viesi',
            //			'guests' => '',
            'hosted by' => ', saimnieks',
            //            'Respond to' => [''],
            //			'’s inquiry' => '',
            //            'beforeConfirmStatus' => [''],
            'www.airbnb.com/rooms' => ['www.airbnb.lv/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //            'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'sk' => [
            //            'Your reservation is' => '',
            //            'Check In' => [''],
            //            'Check Out' => 'Checkout',
            //            'reservation_code' => 'código_de_reserva',
            'Reservation Code' => ['Kód rezervácie'],
            //			'check_in' => '',
            //			'check_out' => '',
            'amount' => 'suma',
            'Amount' => 'Suma',
            //            'Total' => [''],
            //			'Occupancy taxes and fees' => '',
            //          'Apartment' => 'Appartement',
            //            'House'   => 'Visa māja/dzīvoklis',
            'Address' => 'Adresa',
            //			'is your host' => '',
            //			'Message Host' => '',
            //            'Guest'  => 'NOTTRANSLATED',
            'Guests' => 'Hostia',
            //			'guests' => '',
            'hosted by' => 'u hostiteľa',
            //            'Respond to' => [''],
            //			'’s inquiry' => '',
            //            'beforeConfirmStatus' => [''],
            'www.airbnb.com/rooms' => ['.sk.airbnb.com/rooms'],
            //			'Confirmation Code:' => '',
            //			'Request sent' => '',
            //			'This is not a confirmed' => '',
            //            'Cancellation policy:' => '',
            //            'View full itinerary' => '',
        ],
        'en' => [
            'Your reservation is'  => "Your reservation is",
            'Check In'             => ['Check In', 'Check-in'],
            'Check Out'            => ['Check Out', 'Checkout', 'check out'],
            'Total'                => ['Total', 'Amount paid', 'Balance due'],
            'reservation_code'     => 'reservation_code',
            'Reservation Code'     => ['Reservation Code', 'Reservation code', 'Confirmation code'],
            'check_in'             => 'check_in',
            'check_out'            => 'check_out',
            'Address'              => ['Address', 'Accomodation Address', 'Accommodation Address'],
            'Respond to'           => 'Respond to',
            '’s inquiry'           => '’s inquiry',
            'beforeConfirmStatus'  => 'inquiry',
            'www.airbnb.com/rooms' => ['www.airbnb.com/rooms', 'www.airbnb.com%2Frooms', 'www.airbnb.co.uk/rooms'],
            'Confirmation Code:'   => ['Confirmation Code:', 'Confirmation code'],
            'Cancellation policy:' => ['Cancellation policy:', 'Cancellation policy'],
            //            'View full itinerary' => 'View full itinerary',
        ],
    ];

    public function parseHotel(Email $email)
    {
        $html = str_ireplace(['&zwnj;', '&8204;', '‌', '&#8204;'], '',
            $this->http->Response['body']); // Zero-width non-joiner
        $this->http->SetEmailBody($html);

        if ($this->http->XPath->query("//text()[contains(normalize-space(),'Get ready for your upcoming experience')]")->length > 0) {
            $this->logger->alert('go to parse by airbnb\Email\EventReminde');

            return;
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'Get ready for') and contains(.,'!')]")->length > 0
            && $this->http->XPath->query("//text()[normalize-space()='Check In']/ancestor::tr[contains(.,'Check Out')][1][count(./td[normalize-space()!=''])=2]")->length > 0
        ) {
            $this->logger->alert('go to parse by airbnb\Email\It3394982');

            return;
        }
        $body = $this->http->Response['body'];

        foreach ($this->langDetectors as $lang => $rules) {
            foreach ($rules as $rule) {
                if (strpos($body, $rule) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    break 2;
                }
            }
        }

        $tableType = 'getTable';
        $table = $this->getTable("//text()[{$this->contains($this->t('Check In'))} or {$this->contains($this->t('Check-in'))} or {$this->contains($this->t('Check Out'))}]/ancestor::table[1]/..");

        if (empty($table) || count($table) < 2 || (!isset($table[$this->t('check_in')]) && !isset($table[$this->t('checkin')]) && !isset($table[$this->t('check_out')]) && !isset($table[$this->t('checkout')]))) {
            $tableType = 'getTable2';
            $table = $this->getTable2('(//img[contains(@src,"slash")]/ancestor::table[1] | //tr[count(./th)=3 and count(./th[2]/*)=1 and ./th[2]/img]/ancestor::table[1] | //tr[count(./td)=3 and count(./td[2]/*)=1 and ./td[2]//img]/ancestor::table[1] | //tr[count(./th[contains(translate(normalize-space(),\'0123456789\',\'dddddddddd\'),\'dddd\')])=2])[not(descendant::img[contains(@src, "profile")])][not(contains(normalize-space(.), "Product Manager"))]');

            if (empty($table) || (isset($table['check_in']) && (strlen($table['check_in']) < 5 || !preg_match("/\d+/", $table['check_in'])))
                || (!isset($table[$this->t('check_in')]) && !isset($table[$this->t('checkin')]) && !isset($table[$this->t('check_out')]) && !isset($table[$this->t('checkout')]))
            ) {
                $tableType = 'getTable3';
                $table = $this->getTable3("//text()[{$this->contains($this->t('Check In'))} or {$this->contains($this->t('Check-in'))} or {$this->contains($this->t('Check in'))}]/ancestor::table[1]/ancestor::div[1]");
            }

            if (empty($table) || (isset($table['check_in']) && (strlen($table['check_in']) < 5 || !preg_match("/\d+/", $table['check_in']))) || (isset($table[$this->t('check_in')]) && (strlen($table[$this->t('check_in')]) < 5 || !preg_match("/\d+/", $table[$this->t('check_in')])))
                || (!isset($table[$this->t('check_in')]) && !isset($table[$this->t('checkin')]) || !isset($table[$this->t('check_out')]) && !isset($table[$this->t('checkout')]))
            ) {
                $tableType = 'getTable4';
                $table = $this->getTable4("//text()[{$this->eq($this->t('View full itinerary'))}]/ancestor::*/preceding-sibling::*[1][normalize-space()]/descendant::tr[not(.//tr)][count(*[normalize-space()]) = 2]/*[self::td or self::th][normalize-space()]");
            }
        }

        if (empty($table)) {
            $tableType = 'unknown';
        }
        $this->logger->debug('Table parse method: ' . $tableType);
        // $this->logger->debug('$table = '.print_r( $table,true));

        if (empty($table)) {
            return;
        }

        if (!isset($table[$this->t('check_in')]) && !isset($table[$this->t('checkin')])) {
            return;
        }

        if (!isset($table[$this->t('check_out')]) && !isset($table[$this->t('checkout')])) {
            return;
        }

        $h = $email->add()->hotel();

        $status = $this->http->FindSingleNode('(//text()[' . $this->starts($this->t('Your reservation is')) . '])[1]',
            null, true, '/' . $this->opt($this->t('Your reservation is')) . '\s*(\w+)/u');

        if ($status) {
            $h->general()->status($status);
        } elseif ($this->http->FindSingleNode("(//text()[contains(normalize-space(.),'" . $this->t('This is not a confirmed') . "')])[1]")) {
            $h->general()->status('not confirmed');
        } elseif ($this->http->FindSingleNode("(//text()[contains(normalize-space(.),'" . $this->t('Request sent') . "')])[1]")) {
            $h->general()->status($this->t('Request sent'));
        }

        // ConfirmationNumber
        $conf = isset($table[$this->t('reservation_code')]) ? $table[$this->t('reservation_code')] : null;

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode('(//text()[' . $this->starts($this->t('Confirmation Code:')) . '])[1]',
                null, true, '/' . $this->opt($this->t('Confirmation Code:')) . '\s*(\w+)\s*$/u');
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//th[{$this->starts($this->t('Confirmation Code:'))}]/following-sibling::th[normalize-space(.)][1]");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//a[{$this->eq($this->t('View full itinerary'))}][contains(@href, '//www.airbnb.com/reservation/itinerary?code=HMTTFB2F8C')]/@href", null, true, "/(?:\?|\&)code=([A-Z\d]{9,12})(?:\&|$)/");
        }

        if (isset($conf)) {
            $h->general()->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }

        $xpathAvatar = 'img[contains(@src,"jpg?aki_policy=profile_x_medium") or ancestor::a[1][contains(@href,".airbnb.com/users/show/") and count(descendant::img)=1 and count(descendant::text())<2]]';
        $xpathFragment1 = "//tr[not(.//tr) and count(*)=2 and ("
            . "*[1][descendant::*[contains(@class,'headline')]] or *[2][descendant::{$xpathAvatar}]"
            . ")]";

        // Hotel Name
        $hotelName = orval(
            $this->http->FindSingleNode('//img[(contains(@src,"jpg?aki_policy=large") or ( contains(@src,"_original.")) and contains(@src,"size=large_cover") )]/@alt'),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Apartment") . "')])[1]/ancestor::h2[1]/following-sibling::*[1]",
                null, true, "#(.*?)(?:/|$)#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("House") . "')])[1]/ancestor::h2[1]/following-sibling::*[1]",
                null, true, "#(.*?)(?:/|$)#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Townhouse") . "')])[1]/ancestor::h2[1]/following-sibling::*[1]",
                null, true, "#(.*?)(?:/|$)#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Bed & Breakfast") . "')])[1]/ancestor::h2[1]/following-sibling::*[1]",
                null, true, "#(.*?)(?:/|$)#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Cabin") . "')])[1]/ancestor::h2[1]/following-sibling::*[1]",
                null, true, "#(.*?)(?:/|$)#"),
            $this->http->FindSingleNode($xpathFragment1 . '/descendant::text()[' . $this->contains($this->t('hosted by')) . ']/ancestor::*[self::p or self::div or self::span][1]/preceding-sibling::*[self::p or self::div or self::span][1]'),
            $this->http->FindSingleNode('//img[contains(@src,"jpg?aki_policy=large") or ( contains(@src,"_original.") and contains(@src,"size=large_cover") )][not(ancestor::a[' . $this->contains(['/experiences/'],
                    '@href') . ']) and not(ancestor-or-self::*[' . $this->contains([
                        'max-height:267px',
                        'max-height: 267px',
                    ], '@style') . '])]/following::text()[normalize-space()][1]'),
            $this->http->FindSingleNode("//img[contains(@src, '?aki_policy=large')]/ancestor::a[" . $this->contains($this->t('www.airbnb.com/rooms'),
                    '@href') . "][following::text()[normalize-space()][{$this->eq($this->t('Address'))}]]/following::text()[normalize-space()][1]"),
            $this->http->FindSingleNode('(//a[' . $this->contains($this->t('www.airbnb.com/rooms'),
                    '@href') . '][normalize-space(.)][1]//text()[normalize-space(.)])[1]'),
            $this->http->FindSingleNode('(//a[' . $this->contains(str_replace("/", "_",
                    $this->t('www.airbnb.com/rooms')),
                    '@href') . '][normalize-space(.)][1]//text()[normalize-space(.)])[1]'),
            // proofpoint.com/v2/url?u=https-3A__www.airbnb.fr_rooms_27428088
            $this->http->FindSingleNode('//img[contains(@src,"jpg?aki_policy=large") or contains(@src,"jpg?aki_policy=profile_small") or contains(@src,"jpg?aki_policy=profile_x_medium")]/ancestor::*[self::td or self::th][1]/preceding-sibling::*[self::td or self::th][1]/descendant::text()[normalize-space()][1]'),
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/preceding::table[count(./descendant::img)=1 and count(./descendant::text()[string-length(normalize-space())>2])=2]/descendant::text()[string-length(normalize-space())>2][1]"),
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/preceding::table[count(./descendant::img)=1][1]/following::table[1]/descendant::text()[normalize-space()][1]")
        );

        $this->logger->error("//text()[{$this->eq($this->t('Address'))}]/preceding::table[count(./descendant::img)=1 and count(./descendant::text()[string-length(normalize-space())>2])=2]/descendant::text()[string-length(normalize-space())>2][1]");

        if (strlen($hotelName) > 1) {
            $charlist = "›♛★☀♡✈❤☆♥❣️⭐";
            $hotelName = preg_replace("/(?:^\s*[{$charlist}]|[{$charlist}]\s*$)/u", '', $hotelName);
            $hotelName = preg_replace("/\s*\>\s*/", ' ', $hotelName);
            $h->hotel()->name(trim($hotelName));
        }

        // CheckInDate
        $checkin = isset($table[$this->t('check_in')]) ? $table[$this->t('check_in')] : $table[$this->t('checkin')];
        $h->booked()->checkIn(strtotime($this->normalizeDate($checkin)));

        if ($time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check In'))}]/ancestor::*[1]",
            null, true, "#\d+:\d+\s*(?:[AP]M)?#i")) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if ($time = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Check in'))}]/ancestor::*[1]",
            null, true, "#\d+:\d+\s*(?:[AP]M)?#i")) {
            $h->booked()->checkIn(strtotime($time, $h->getCheckInDate()));
        }

        if (isset($table[$this->t('time_in')])) {
            $timeCheckIn = str_ireplace('(noon)', '', $table[$this->t('time_in')]);
            $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
        }

        // CheckOutDate
        $checkout = isset($table[$this->t('check_out')]) ? $table[$this->t('check_out')] : $table[$this->t('checkout')];
        $h->booked()->checkOut(strtotime($this->normalizeDate($checkout)));

        if ($time = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Check Out')) . "]", null, true,
            "#\d+:\d+\s*(?:[AP]M)?#i")) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        if ($time = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Checkout') . "')]/following-sibling::div[last()]",
            null, true, "#(?:\d+[:]+)?\d+\s*(?:[AP]M)#i")) {
            $h->booked()->checkOut(strtotime($time, $h->getCheckOutDate()));
        }

        if (isset($table[$this->t('time_out')])) {
            $timeCheckOut = str_ireplace(['(noon)', '(midnight)'], '', $table[$this->t('time_out')]);
            $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
        }

        if ($h->getCheckInDate() > $h->getCheckOutDate()) {
            $h->booked()->checkOut(strtotime('+1 year', $h->getCheckOutDate()));
        }

        // Address
        $addr = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Address')) . "]/following::text()[string-length(normalize-space(.))>1][1]/ancestor::*[not(local-name() = 'a')][1]");

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'for your trip to')]",
                null, true, "#for\s+your\s+trip\s+to\s+(.+?)\s*(?:\.|$)#");
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//a[starts-with(normalize-space(.),'a place in')]", null,
                false, "#a\s+place\s+in\s+(.+?)(?:\.|$)#");
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//a[starts-with(normalize-space(.),'a place in')]", null,
                false, "#a\s+place\s+in\s+(.+?)(?:\.|$)#");
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//a[starts-with(normalize-space(.),'a home in')]", null,
                false, "#a\s+home\s+in\s+(.+?)(?:\.|$)#");
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'It is located in')]",
                null, false, "#It is located in\s+(.+?)\s*(?:\.|and is|$)#");
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Adres')]/following::text()[normalize-space()][1]/ancestor::p[1]");
        }
//        if (empty($addr)) {
//            // event
//            $addr = $this->http->FindSingleNode("//text()[{$this->contains($this->t("I'll be at the"))}]", null, true,
//                "#{$this->preg_implode($this->t("I'll be at the"))}\s*(.{3,}?)\s*{$this->preg_implode($this->t('to meet you'))}#");
//        }
        if (empty($addr) && !empty($h->getHotelName())) {
            $browser = new \HttpBrowser("none", new \CurlDriver());
            $url = $this->http->FindSingleNode("(//a[contains(@href,'https://www.airbnb.') 
                    and contains(@href,'/rooms/') or contains(@href, 'https://abnb.me') 
                    or (contains(@href, 'urldefense.proofpoint.com/v2/url') and contains(@href,'_rooms_'))])[1]/@href");

            if ($url) {
                $this->logger->debug('Http request: ' . $url);
                $browser->GetURL($url);
            }

            if ($address = $browser->FindSingleNode("//meta[@property='og:title' and contains(@content, '{$this->t('Rent in')}')]/@content",
                null, false, "/{$this->t('Rent in')}\s+(.+)/")
            ) {
                $addr = $address;
            } elseif (($address = $browser->FindSingleNode("//div[{$this->contains($this->t('map/GoogleMap'), '@data-veloute')}]/preceding::text()[normalize-space()][1]",
                null, true, "/^(?:.*{$this->opt($this->t('place is located in'))}\s+)?(.+)/"))
            ) {
                $addr = $address;
            } elseif (($address = $browser->FindSingleNode("//div[{$this->contains($this->t('map/GoogleMap'), '@data-veloute')}]/preceding::h2[normalize-space()=\"De buurt\"]/following::div[not(.//div) and normalize-space()][1]",
                null, true, "/De woning van.* ligt in\s+(.{3,}?)[.]*$/")) // nl
            ) {
                $addr = $address;
            }
        }

        if (empty($addr)) {
            $addr = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Bald ist es Zeit für deine Reise nach'))}]",
                null, false, "/{$this->opt($this->t('Bald ist es Zeit für deine Reise nach'))}\s*(.+?)\./");
        }

        if (empty($addr) && !empty($h->getHotelName())) {
            $addr = $this->http->FindPreg('/Cosy room in (.+)/', false, $h->getHotelName());
        }

        if (!empty($addr)) {
            $h->hotel()->address($addr);
        } else {
            $h->hotel()->noAddress();
        }

        // Phone
        $phone = $this->http->FindSingleNode("//text()[{$this->contains($this->t('is your host'))}]/following::text()[{$this->eq($this->t('Message Host'))}]/following::text()[string-length(normalize-space())>3][1]",
            null, true, '/^[+(\d][-. \d)(]{5,}[\d)]$/');

        if ($phone) {
            $h->hotel()->phone($phone);
        }

        // Guests
        $guests = orval(
            $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[string-length(normalize-space(.))>0][1]",
                null, true, "#^\s*(\d+)\s*#"),
            $this->http->FindSingleNode("//*[{$this->contains($this->t("Apartment"), 'text()')}]/ancestor::h2[1]", null,
                true, "#(\d+)\s*" . $this->opt($this->t("Guests")) . "#i"),
            $this->http->FindSingleNode("//*[{$this->contains($this->t("House"), 'text()')}]/ancestor::h2[1]", null, true,
                "#(\d+)\s*" . $this->opt($this->t("Guests")) . "#i"),
            $this->http->FindSingleNode("//text()[{$this->contains($this->t("Guests"))}]/ancestor::*[1]", null,
                true, "#(\d+)\s*" . $this->opt($this->t("Guests")) . "#i"),
            $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[string-length(normalize-space(.))>0][1]",
                null, true, "#^\D*(\d+)(?:\D+\d+)?\D*$#")
        );

        if (!empty($guests) && !is_array($this->t('guests')) && isset($table[$this->t('guests')])) {
            $guests = re("#\d+#", $table[$this->t('guests')]);
        }

        if (!empty($guests)) {
            $h->booked()->guests($guests);
        }

        if (empty($h->getGuestCount())) {
            $guests = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[string-length(normalize-space(.))>0][1]");

            if (preg_match("#^([^\d]+)$#", $guests, $m)) {
                $h->general()->travellers(array_map('trim', array_filter(explode(',', $m[1]))));
                $h->booked()->guests(count($h->getTravellers()));
            } elseif (preg_match("#^([^\d]+),[^,]+ (\d+) .*$#", $guests, $m)) {
                $h->general()->travellers(array_map('trim', explode(',', $m[1])));
                $h->booked()->guests(count($h->getTravellers()) + (int) $m[2]);
            }
        }

        // Kids
        $kids = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[string-length(normalize-space(.))>0][1]",
            null, true, "#^\s*\d+\s*\D+\s+(\d+)#");

        if (empty($kids)) {
            $kids = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Guests")) . "]/following::text()[string-length(normalize-space(.))>0][1]",
                null, true, "#^\D*\d+\D+(\d+)\D*$#");
        }

        if ($kids === null && !is_array($this->t('guests')) && isset($table[$this->t('guests')])) {
            $kids = re("#^\D*\d+\D+(\d+)\D*$#u", $table[$this->t('guests')]);
        }

        if ($kids !== null) {
            $h->booked()->kids($kids, false, true);
        }

        // RoomType
        $roomType = orval(
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Apartment") . "')])[1]/ancestor::h2[1]",
                null, true, "#" . $this->t("Apartment") . "\s+•\s+(.*?)\s*(?:•|$)#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("House") . "')])[1]/ancestor::*[1]", null,
                true, "#" . $this->t("House") . "\s*•\s*(.*?)\s*(?:•|$)#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Townhouse") . "')])[1]/ancestor::h2[1]",
                null, true, "#" . $this->t("Townhouse") . "\s*•\s*(.*?)\s*(?:•|$)#"),
            $this->http->FindSingleNode($xpathFragment1 . '/descendant::*[self::p or self::div][normalize-space()!=""][2][' . $this->contains($this->t("hosted by")) . ']'),
            //, null, true, "#(.+?)\s+hosted by#")
            $this->http->FindSingleNode("//img[{$this->contains(['jpg?aki_policy=large', 'jpg?aki_policy=profile_small', 'jpg?aki_policy=profile_x_medium'], '@src')}]/ancestor::*[self::td or self::th][1]/preceding-sibling::*[self::td or self::th][1]/descendant::text()[normalize-space()][2]"),
            $this->http->FindSingleNode("//img[{$this->contains(['jpg?aki_policy=large', 'jpg?aki_policy=profile_small', 'jpg?aki_policy=profile_x_medium'], '@src')}]/ancestor::*[self::td or self::th][1]/following::*[self::td or self::th][1]/descendant::text()[normalize-space()][1]", null, true, empty($h->getHotelName()) ? null : '/^(?!' . preg_quote($h->getHotelName(), '/') . ').*$/u'),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("Apartment") . "')])[1]/ancestor::*[1]", null, true, "#" . $this->t("Apartment") . "\s*" . $this->opt($this->t("hosted by")) . "#"),
            $this->http->FindSingleNode("(//*[contains(text(),'" . $this->t("House") . "')])[1]/ancestor::*[1]", null, true, "#(" . $this->opt($this->t("House")) . ")\s*" . $this->opt($this->t("hosted by")) . ".+#"),
            $this->http->FindSingleNode("//text()[{$this->eq($this->t('Address'))}]/preceding::table[count(./descendant::img)=1 and count(./descendant::text()[string-length(normalize-space())>2])=2]/descendant::text()[string-length(normalize-space())>2][2]"),
            // it-9217584.eml
            $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Guests'))}])[1]/ancestor::*[1]",
                null, true, "#(^.+?)\s*•\s*\d+\s*" . $this->opt($this->t("Guests")) . "#")
        );

        if (!empty($h->getHotelName()) && empty($roomType)) {
            $roomType = $this->http->FindSingleNode("//text()[" . $this->contains(explode("'",
                    $h->getHotelName())) . "][1]/ancestor::*[1][contains(normalize-space(.),'›')]/following::text()[normalize-space(.)][1]");
        }

        if (!empty($roomType)) {
            $r = $h->addRoom();
            $r->setType($roomType);
        }

        if (is_string($this->t('Respond to'))) {
            $guestName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Respond to'))} and {$this->contains($this->t('’s inquiry'))}]",
                null, true,
                "#{$this->opt($this->t('Respond to'))}\s+(.+?)\s+{$this->opt($this->t('’s inquiry'))}#");

            if (!empty($guestName) && !empty(self::$dict[$this->lang]['beforeConfirmStatus'])) {
                $h->general()->traveller($guestName);
                $h->general()->status($this->t('beforeConfirmStatus'));
            }
        } else {
            foreach ($this->t('Respond to') as $key => $respond) {
                if (empty(self::$dict[$this->lang]['’s inquiry'])) {
                    continue;
                }
                $guestName = $this->http->FindSingleNode("//text()[{$this->contains($respond)} and {$this->contains($this->t('’s inquiry')[$key])}]",
                    null, true, "#{$respond}\s+(.+?)\s+{$this->opt($this->t('’s inquiry')[$key])}#");

                if (!empty($guestName) && !empty(self::$dict[$this->lang]['beforeConfirmStatus'])) {
                    $h->general()->traveller($guestName);
                    $h->general()->status($this->t('beforeConfirmStatus')[$key]);

                    break;
                }
            }
        }

        // Currency
        // Total
        // can be $table['Amount'] or $table['Amount(USD)']
        if (is_array($this->t('amount'))) {
            $found = false;

            foreach ($this->t('amount') as $key => $value) {
                if (isset($table[$value])) {
                    $total = $table[$value];
                    $found = true;

                    break;
                }
            }

            if ($found !== true) {
                foreach ($this->t('amount') as $value) {
                    foreach ($table as $key => $v) {
                        if (strpos($key, $value) === 0) {
                            $total = $table[$key];

                            break;
                        }
                    }
                }
            }
        } else {
            if (isset($table[$this->t('amount')])) {
                $total = $table[$this->t('amount')];
            } else {
                foreach ($table as $key => $v) {
                    if (stripos($key, $this->t('amount')) === 0) {
                        $total = $v;

                        break;
                    }
                }
            }
        }
        $totalFull = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("Total"))} or starts-with(normalize-space(),'Total')]/ancestor::tr[1]")));

        if (!empty($totalFull)) {
            if (preg_match("/(?:{$this->opt($this->t("Total"))}|Total)\s*\([ ]*USD[ ]*\)\s*\\$\d[\d., ]* (AUD)\s*$/u", $totalFull[0])) {
                // Total (USD) $2075.92 AUD
                $currency = 'AUD';
            } else {
                $currency =
                    preg_match("/(?:{$this->opt($this->t("Total"))}|Total)\s*[(（]+[ ]*([A-Z]{3})[ ]*[)）]/", $totalFull[0], $m)
                        ? $m[1] : $this->currency($totalFull[0]);
            }
            $tot = array_values(array_filter($this->http->FindNodes("//text()[{$this->starts($this->t("Total"))} or starts-with(normalize-space(),'Total')]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::*[normalize-space()][1]")));

            if (!empty($tot)) {
                $sum = 0.0;

                foreach ($tot as $tt) {//it-51773591.eml
                    $sum += $this->amount($tt);
                }

                if (!empty($sum)) {
                    $h->price()->total($sum);
                }
            }
        }

        if (empty($sum) && isset($total) && is_string($total)) {
            $currency = $this->currency($total);
            $h->price()->total($this->amount($total));
        }

        if (!empty($currency)) {
            $h->price()->currency($currency);
            // Taxes
            $taxesFull = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Occupancy taxes and fees")) . "]/ancestor::tr[1]");
            $taxCurrency = $this->currency($taxesFull);
            $taxes = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Occupancy taxes and fees")) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::*[normalize-space()][1]");

            if ($taxes && $taxCurrency === $currency) {
                $h->price()->tax($this->amount($taxes));
            }
        } else {
            // it-9217584.eml - nl
            $total = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Guests'))}])[1]/ancestor::*[1]");

            if (preg_match('/Je zou (\D+)([\d.,\s]+) betalen/', $total, $m)) {
                $h->price()->currency($this->currency($m[1]));
                $h->price()->total($this->amount($m[2]));
            }
        }

        $xpathCancellation = "//tr[ *[normalize-space()][1][{$this->starts($this->t('Cancellation policy:'))}] and *[normalize-space()][2][ descendant::a[normalize-space() and @href] ] ]/*[normalize-space()][1]";

        // CancellationPolicy
        $cancellationTexts = [];
        $cancellationTexts[] = $this->http->FindSingleNode($xpathCancellation . "/descendant::p[normalize-space() and {$this->starts($this->t('Cancellation policy:'))}]",
                null, true, "/{$this->opt($this->t('Cancellation policy:'))}\s*(.{3,})/")
            ?? $this->http->FindSingleNode($xpathCancellation . "/descendant::div[normalize-space() and {$this->starts($this->t('Cancellation policy:'))}]",
                null, true, "/{$this->opt($this->t('Cancellation policy:'))}\s*(.{3,})/");
        $cancellationSubTexts = $this->http->FindNodes($xpathCancellation . "/descendant::*[self::p or self::div][normalize-space() and not({$this->starts($this->t('Cancellation policy:'))})]");

        if (empty(array_filter($cancellationTexts)) && empty($cancellationSubTexts)) {
            $xpathCancellation = "//th[ descendant::*[normalize-space()][1][{$this->starts($this->t('Cancellation policy:'))}] and descendant::*[normalize-space()][2] and following-sibling::th[1][ count(descendant::text()[normalize-space()]) = 1 and .//a] ]";
            $cancellationSubTexts = $this->http->FindNodes($xpathCancellation . "/descendant::*[normalize-space()][position()>1]");
        }
        $cancellationTexts = array_merge($cancellationTexts, $cancellationSubTexts);
        $cancellationTexts = array_map(function ($item) {
            return !preg_match('/[-,.;:?!]$/', $item) ? $item . ';' : $item;
        }, array_filter($cancellationTexts));

        if (count($cancellationTexts)) {
            $h->setCancellation(implode(' ', array_unique($cancellationTexts)));
        }
        $this->detectDeadLine($h);

        //kostyl for bcd
        /*if (!empty($h->getCheckInDate())
            && date('Y-m-d', $h->getCheckInDate()) === date('Y-m-d', $h->getCheckOutDate())
        ) {
            //not a Hotel, Event
            $e = $email->add()->event();
            if (!empty($h->getCheckInDate())) {
                $e->booked()->start($h->getCheckInDate());
            }
            if (!empty($h->getConfirmationNumbers())) {
                $e->general()->confirmation($it['ConfirmationNumber']);
            }
            if (!empty($h->getCheckInDate())) {
                $e->booked()->end($h->getCheckOutDate());
            }
            if (!empty($it['GuestNames'])) {
                $it['DinnerName'] = array_shift($it['GuestNames']);
                unset($it['GuestNames']);
            }
            if (isset($it['RoomType'])) {
                $it['Name'] = $it['RoomType'];
            }
            if (isset($it['HotelName'])) {
                $it['Name'] = $it['HotelName'] . ' ' . $it['Name'];
            }
            $it['Name'] = trim($it['Name']);
            unset($it['Kids']);
            unset($it['RoomType']);
            unset($it['HotelName']);
        }
        $itineraries[] = $it;*/
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        if (0 < $this->http->XPath->query("//a[{$this->contains($this->words)} and contains(@href,\"airbnb\") and not({$this->contains(['invites', 'invitasjoner', 'verten', 'vert', 'besked', 'invitationer'])} or {$this->contains(['thread'], '@href')})]")->length) {
            // WTF?
            return false;
        }

        foreach ($this->langDetectors as $rules) {
            foreach ($rules as $rule) {
                if (stripos($body, $rule) !== false) {
                    return true;
                }
            }
        }

        foreach ($this->emailForHosterDetectors as $texts) {
            if ($this->http->XPath->query("//text()[{$this->contains($texts)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $rule) {
            if (false === strpos($rule, '/') && stripos($headers['subject'], $rule) !== false) {
                return true;
            } elseif (false !== strpos($rule, '/') && preg_match($rule, $headers['subject']) > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->year = date('Y', strtotime($parser->getHeader('date')));

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        if ($this->isEmailForHoster() === true) {
            $this->logger->debug('this email for hoster -> should not be parsed');
            $email->setType('Reservations' . ucfirst($this->lang));
            $email->setIsJunk(true);

            return $email;
        }

        $this->parseHotel($email);
        $email->setType('Reservations' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $types = 3 * count(self::$dict);

        return $types;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancel = $h->getCancellation())) {
            return;
        }

        if (
            // 7월 12일 4:00 PM 전에 예약을 취소하면 요금 전액이 환불됩니다. - ko
               preg_match('/(\d+월 \d+일 \d+:\d+ [AP]M) 전에 예약을 취소하면 요금 전액이 환불됩니다\./iu', $cancel, $m)
            // Отмените до 2:00PM 27 дек. и получите полный возврат.
            || preg_match('/Отмените до (\d+:\d+[AP]M \d+ \w+). и получите полный возврат\./iu', $cancel, $m)
            // Atceļot līdz 12:00PM 5. aug., saņemiet atmaksu pilnā mērā.
            || preg_match('/Atceļot līdz (\d+:\d+[AP]M \d+[.] \w+)\., saņemiet atmaksu pilnā mērā\./iu', $cancel, $m)
               || preg_match('/Annulez avant ([\d\:]+\s*A?P?M\s*le\s*\d+\s*\w+) et obtenez/iu', $cancel, $m)
        ) {
            $h->booked()->deadline($this->normalizeDateDeadLine($m[1], $h));
        }

        if (
            // Free cancellation for 48 hours after booking.
            preg_match('/Free cancellation for (\d+) hours after booking\./iu', $cancel, $m)
            || preg_match('/Gratis annullering i (\d+) timer efter booking/iu', $cancel, $m)
            || preg_match('/Δωρεάν ακύρωση για (\d+) ώρες μετά την κράτηση/iu', $cancel, $m)
        ) {
            $h->booked()->deadlineRelative($m[1] . ' hours');
        }
    }

    private function normalizeDateDeadLine(string $str, Hotel $h)
    {
        $year = date('Y', $h->getCheckInDate());

        $in = [
            // (7)월 (12)일 (4:00 [AP]M)
            '/^(\d+)월 (\d+)일 (\d+:\d+ [AP]M)$/iu',
            // 2:00PM 27 дек; 12:00PM 5. aug
            '/^(\d+:\d+\s*[AP]M)\s+(\d+)\.? (\w+)$/iu',
            // 3:00 PM le 13 juin
            '/^([\d\:]+\s*A?P?M)\s*le\s*(\d+\s*\w+)$/iu',
        ];
        $out = [
            "{$year}-$1-$2, $3",
            "$2 $3 {$year}, $1",
            "$2 {$year}, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str, false);
    }

    private function isEmailForHoster()
    {
        foreach ($this->emailForHosterDetectors as $texts) {
            if ($this->http->XPath->query("//text()[{$this->contains($texts)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    private function getTable($xpath)
    {
        $this->logger->debug(__FUNCTION__);

        // examples: it-3207108.eml, it-3264578.eml, it-3297748.eml, it-3364738.eml, it-3712228.eml, it-3712236.eml, it-3717906.eml, it-6077551.eml
        $this->logger->debug($xpath);
        $table = $this->http->XPath->query($xpath);

        if ($table->length === 0) {
            return [];
        }
        $table = $table->item(0);

        if ($this->http->XPath->query("./descendant::text()[" . $this->eq($this->t('Address')) . "]", $table)->length === 0
            && $this->http->XPath->query("./descendant::td[{$this->starts($this->t('Check In'))}]/following-sibling::td[1][{$this->starts($this->t('Check Out'))}]", $table)->length === 0) {
            return [];
        }
        $res = [];

        foreach ($this->http->XPath->query("./table", $table) as $table) {
            if ($this->http->XPath->query('tbody', $table)->length > 0) {
                $table = $this->http->XPath->query('tbody', $table)->item(0);
            }

            if (count($this->http->FindNodes("./tr[2]", $table)) > 0) {
                $keys = array_map(
                    function ($s) {
                        return preg_replace("#[\s-]+#", "_", trim(re("#[^\d:]+#", strtolower($s))));
                    }, $this->http->FindNodes("./tr[position()=1 or position()=3]/td", $table)
                );
                $values = $this->http->FindNodes("./tr[position()=2 or position()=4]/td", $table);
            } else {
                $keys = array_map(function ($s) {
                    return preg_replace("#[\s-]+#", "_", trim(re("#[^\d:]+#", strtolower($s))));
                }, $this->http->FindNodes("./tr[1]/td/*[1]", $table));
                $values = $this->http->FindNodes("./tr[1]/td/*[2]", $table);
            }

            if (count($keys) === count($values)) {
                $res = array_merge($res, array_combine($keys, $values));
            }
        }

        return $res;
    }

    private function getTable2($xpath)
    {
        $this->logger->debug(__FUNCTION__);

        // examples: it-15180453.eml, it-43253851.eml, it-6632001.eml, it-6666561.eml, it-6830442.eml, it-8205266.eml, it-8557490.eml
        $this->logger->debug($xpath);
        $table = $this->http->XPath->query($xpath);

        if ($table->length === 0) {
            return [];
        }
        $table = $table->item(0);
        $res = [];
        $keys = [];
        $values = [];

        $arrValues = array_values(array_filter(array_map('trim', $this->http->FindNodes("./descendant::text()", $table))));

        if (!(count($arrValues) === 4 || count($arrValues) === 5 || count($arrValues) === 6) || preg_match("/\d/", $arrValues[1]) === 0) {
            return [];
        }
        $keys[] = $this->t('check_in');
        $values[] = $arrValues[1];

        switch (count($arrValues)) {
            case 4:
                $keys[] = $this->t('check_out');
                $values[] = $arrValues[3];

                break;

            case 5:
            case 6:
                $keys[] = $this->t('check_out');

                if (preg_match("/{$this->opt($this->t('Check Out'))}/i", $arrValues[4])) {
                    $values[] = $arrValues[3];
                } else {
                    $values[] = $arrValues[4];
                }
                // in bcd file after date string "C‌h‌e‌c‌k‌ ‌i‌n‌ ‌A‌n‌y‌t‌i‌m‌e‌ ‌a‌f‌t‌e‌r‌ ‌3‌P‌M‌"
                if (preg_match("/{$this->t('Anytime after')}\s+(.+)/i", $arrValues[2], $m) or preg_match("/\b(\d{1,2}:\d{2}(\s*[AP]M)?)/i", $arrValues[2], $m)
                        or preg_match("/\b(\d{1,2}\s*[AP]M)\b(\s*-\s*\d{1,2}\s*[AP]M)?/i", $arrValues[2], $m) //or preg_match("/\b(\d{1,2}:\d{2}(\s*[AP]M)?)/i", $arrValues[2], $m)
                        ) {
                    $keys[] = $this->t('time_in');
                    $in = [
                        '#^\s*(\d+)\s*([AP]M)#i',
                    ];
                    $out = [
                        '$1:00 $2',
                    ];
                    $str = preg_replace($in, $out, $m[1]);
                    $values[] = $str;
                }

                if (//preg_match("/^\s*{$this->t('Check Out')}\s+(.+)/i", $arrValues[5], $m) or
                    isset($arrValues[5])) {
                    $timeOut = $arrValues[5];
                } else {
                    $timeOut = $arrValues[4];
                }

                    if (preg_match("/\b(\d{1,2}:\d{2}(\s*[AP]M)?)/i", $timeOut, $m) or preg_match("/\b(\d{1,2}\s*[AP]M)\b(\s*-\s*\d{1,2}\s*[AP]M)?/i", $timeOut, $m)) {
                        $keys[] = $this->t('time_out');
                        $values[] = $m[1];
                    }
//				else {
//					$keys[] = $this->t('time_out');
//					$values[] = "23:59";
//				}
                break;
        }

        if ($this->http->XPath->query("./following::table[" . $this->contains($this->t('Amount')) . "]", $table)->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./following::table[" . $this->contains($this->t('Amount')) . "]/descendant::text()", $table))));

            if (count($arrValues) > 1) {
                $keys[] = strtolower($arrValues[0]);
                $values[] = $arrValues[1];
            }
        }

        if ($this->http->XPath->query("./following::table[" . $this->contains($this->t('Reservation Code')) . "]", $table)->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./following::table[" . $this->contains($this->t('Reservation Code')) . "]/descendant::text()", $table))));

            if (count($arrValues) > 1) {
                $keys[] = $this->t('reservation_code');
                $values[] = $arrValues[1];
            }
        }

        if (count($keys) === count($values)) {
            $res = array_merge($res, array_combine($keys, $values));
        }

        return $res;
    }

    private function getTable3($xpath)
    {
        $this->logger->debug(__FUNCTION__);

        // examples: it-50091438.eml, it-51566083.eml
        $this->logger->debug($xpath);
        $table = $this->http->XPath->query($xpath);

        if ($table->length === 0) {
            $table = $this->http->XPath->query("//text()[{$this->contains($this->t('Get instructions'))}]/ancestor::table[1]/ancestor::div[1]");

            if ($table->length !== 1) {
                return [];
            }
        }
        $table = $table->item(0);
        $res = [];
        $keys = [];
        $values = [];

        $arrValues = array_values(array_filter(array_map('trim', $this->http->FindNodes("./descendant::text()", $table))));

        if (isset($arrValues[0]) && $arrValues[0] === 'Check-in time is') {
            array_shift($arrValues);
            $arrValues = array_values($arrValues);
        }

        if (count($arrValues) > 1) {
            $keys[] = $this->t('check_in');
            $values[] = $arrValues[1];

            if (preg_match("/(?:^|\s+)(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $arrValues[0], $m)
                || preg_match("/\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\D+\d{1,2}:\d/i", $arrValues[0], $m)
                || preg_match("/^\D*\s+(\d{1,2}(?:[:：]\d{2})?\s*[ap]m(?:\s*\([^)(\d]+\))?)$/i", $arrValues[0], $m) // Check-in is anytime after 12PM (noon)
                || preg_match("/^\D*\s+(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $arrValues[2], $m)) {
                $keys[] = $this->t('time_in');
                $in = [
                    '#^\s*(\d+)\s*([ap]m)#i',
                ];
                $out = [
                    '$1:00 $2',
                ];
                $str = preg_replace($in, $out, $m[1]);
                $values[] = $str;
            }
        }
        $nodes = $this->http->XPath->query("following::table[{$this->contains($this->t('Check Out'))} or {$this->contains($this->t('Check-out'))} or {$this->contains($this->t('Check out'))}]", $table);

        if ($nodes->length == 0) {
            $nodes = $this->http->XPath->query("descendant::text()[{$this->contains($this->t('Check Out'))} or {$this->contains($this->t('Check-out'))} or {$this->contains($this->t('Check out'))}]/ancestor::*[self::td or self::th][1]",
                $table);
        }

        if ($nodes->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./descendant::text()", $nodes->item(0)))));

            if (isset($arrValues[0]) && $arrValues[0] === 'Check out') {
                array_shift($arrValues);
                $arrValues = array_values($arrValues);
            }

            if (count($arrValues) >= 2 && preg_match('/\b[ap]m\b/i', $arrValues[1]) === 0) {
                $keys[] = $this->t('check_out');
                $values[] = $arrValues[1];

                if (preg_match("/\s+((?:\d{1,2}[:]+)?\d{1,2}\s*[ap]m)/i", $arrValues[0], $m)
                    || preg_match("/^((?:\d{1,2}[:]+)?\d{1,2}\s*[ap]m)/i", $arrValues[0], $m)
                    || !empty($arrValues[2]) && preg_match("/\s+((?:\d{1,2}[:]+)?\d{1,2}\s*[ap]m)/i", $arrValues[2], $m)
                ) {
                    $keys[] = $this->t('time_out');
                    $in = [
                        '/^(\d{1,2})\s*([ap]m)/i',
                    ];
                    $out = [
                        '$1:00 $2',
                    ];
                    $str = preg_replace($in, $out, $m[1]);
                    $values[] = $str;
                }
            }
        }

        $nodes = $this->http->XPath->query("./following::table[" . $this->contains($this->t('Guests')) . "]", $table);

        if ($nodes->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./descendant::text()", $nodes->item(0)))));

            if (count($arrValues) > 1) {
                $keys[] = $this->t('guests');
                $values[] = $arrValues[1];
            }
        }

        if ($this->http->XPath->query("./following::table[" . $this->contains($this->t('Amount')) . "]", $table)->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./following::table[" . $this->contains($this->t('Amount')) . "]/descendant::text()", $table))));

            if (count($arrValues) > 1) {
                $keys[] = strtolower($arrValues[0]);
                $values[] = $arrValues[1];
            }
        }

        if ($this->http->XPath->query("./following::table[" . $this->contains($this->t('Reservation Code')) . "]", $table)->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./following::table[" . $this->contains($this->t('Reservation Code')) . "]/descendant::text()", $table))));
            $keys[] = $this->t('reservation_code');
            $values[] = $arrValues[1] ?? null;
        }

        if (count($keys) === count($values)) {
            $res = array_merge($res, array_combine($keys, $values));
        }

        return $res;
    }

    private function getTable4($xpath)
    {
        $this->logger->notice(__FUNCTION__);

        // examples: it-66656523.eml
        $this->logger->debug($xpath);
        $table = $this->http->XPath->query($xpath);

        if ($table->length !== 2) {
            return [];
        }

        $checkInArr = $this->http->FindNodes("./*", $table->item(0));
        $checkOutArr = $this->http->FindNodes("./*", $table->item(1));

        $table = $table->item(0);
        $res = [];
        $keys = [];
        $values = [];

        if (count($checkInArr) == 3) {
            if (preg_match("/\s+(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $checkInArr[2], $m)
                || preg_match("/^(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $checkInArr[2], $m)
                || preg_match("/\s+(\d+:\d+\s*[ap]m)$/i", $checkInArr[2], $m)
                || preg_match("/\s+(\d+:\d+(?:\s*[ap]m)?)\D+\d+:\d+(?:\s*[ap]m)?/i", $checkInArr[2], $m)
                || preg_match("/\s+(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $checkInArr[2], $m)
                || preg_match("/\s+(\d+\s*[ap]m)/i", $checkInArr[2], $m)
                || preg_match("/^\D*\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\D*$/i", $checkOutArr[2], $m)
            ) {
                $keys[] = $this->t('check_in');
                $values[] = $checkInArr[0] . ' ' . $checkInArr[1];

                $keys[] = $this->t('time_in');
                $in = [
                    '#(\d+)\s*([ap]m)#i',
                ];
                $out = [
                    '$1:00 $2',
                ];
                $str = preg_replace($in, $out, $m[1]);
                $values[] = $str;
            }
        }

        if (count($checkOutArr) == 3) {
            if (preg_match("/\s+(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $checkOutArr[2], $m)
                || preg_match("/^(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $checkOutArr[2], $m)
                || preg_match("/\s+(\d+:\d+\s*[ap]m)$/i", $checkOutArr[2], $m)
                || preg_match("/\s+(\d+:\d+(?:\s*[ap]m)?)\D+\d+:\d+(?:\s*[ap]m)?/i", $checkOutArr[2], $m)
                || preg_match("/\s+(\d+\s*[ap]m)[\s\-]+\d+\s*[ap]m/i", $checkOutArr[2], $m)
                || preg_match("/\s+(\d+\s*[ap]m)/i", $checkOutArr[2], $m)
                || preg_match("/^\D*\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)\D*$/i", $checkOutArr[2], $m)
            ) {
                $keys[] = $this->t('check_out');
                $values[] = $checkOutArr[0] . ' ' . $checkOutArr[1];

                $keys[] = $this->t('time_out');
                $in = [
                    '#(\d+)\s*([ap]m)#i',
                ];
                $out = [
                    '$1:00 $2',
                ];
                $str = preg_replace($in, $out, $m[1]);
                $values[] = $str;
            }
        }

        if ($this->http->XPath->query("./following::table[" . $this->contains($this->t('Amount')) . "]", $table)->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./following::table[" . $this->contains($this->t('Amount')) . "]/descendant::text()", $table))));

            if (count($arrValues) > 1) {
                $keys[] = strtolower($arrValues[0]);
                $values[] = $arrValues[1];
            }
        }

        if ($this->http->XPath->query("./following::table[" . $this->contains($this->t('Reservation Code')) . "]", $table)->length > 0) {
            $arrValues = array_values(array_filter(array_map("trim", $this->http->FindNodes("./following::table[" . $this->contains($this->t('Reservation Code')) . "]/descendant::text()", $table))));
            $keys[] = $this->t('reservation_code');
            $values[] = $arrValues[1] ?? null;
        }

        if (count($keys) === count($values)) {
            $res = array_merge($res, array_combine($keys, $values));
        }

        return $res;
    }

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $str = preg_replace("#[^\w\s,]#u", "", $str); // delete like &zwnj;
        $str = preg_replace('/(\d{2})\s*г\.?$/i', '$1', $str);

        if (preg_match('/^\s*\S( \S)+\s*$/', $str)) {
            //  F e b r u a r y 1 2 , 2 0 2 2
            $str = preg_replace('/\s+/', '', $str);
            $str = preg_replace(['/([[:alpha:]])(\d)/', '/(\d)([[:alpha:]])/'], '$1 $2', $str);
        }
        $strOrig = $str;
        $in = [
            "/^[[:alpha:]]+\s*,$/", // Saturday,
            "/^(?:\D*?,)?\s*(\d{4})년.+?(\d{1,2})월.+?(\d{1,2})일$/iu", // ‌2‌0‌2‌0‌년‌ ‌7‌월‌ ‌1‌3‌일‌; 목요일, 2021년 7월 15일
            "#^(\d{4})\s*年(\d+)\s*月(\d+).+$#i",

            "#^\w+,\s+(\d+)\s+(\w+)$#",
            "#^\w+,\s+(\d+)\.\s+(\w+)$#",
            "/^(\d{4})[,.\s]+([[:alpha:]]+)[,.\s]+(\d{1,2})$/u", // 2017 augusztus 20

            "/^(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)[,.\s]+(?:de\s+)?(\d{4})$/u", // 13 December, 2018
            "/^(?:[-[:alpha:]]+[,.\s]+)?(\d{1,2})(?:\s+de)?\s+([[:alpha:]]+)$/u", // jeu, 28 juil
            "/^(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]+)(?:\s+de)?\s+(\d{1,2})[,.\s]+(?:de\s+)?(\d{4})$/u", // December 13, 2018
            "/^(?:[-[:alpha:]]+[,.\s]+)?([[:alpha:]]+)(?:\s+de)?\s+(\d{1,2})$/u", // jeu, juil 28
        ];
        $out = [
            "MISSING",
            "$3.$2.$1",
            "$3.$2.$1",

            "$2 $1 {$this->year}",
            "$1 $2 {$this->year}",
            "$3 $2 $1",

            "$1 $2 $3",
            "$1 $2 {$this->year}",
            "$2 $1 $3",
            "$2 $1 {$this->year}",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (preg_match("#^\w+,\s+\w+\s+\w+$#u", $strOrig)) {
            $inWeek = [
                "#^(\w+),\s+\w+\.?\s+\w+$#u",
            ];
            $outWeek = [
                '$1',
            ];
            $weeknum = WeekTranslate::number1(WeekTranslate::translate(preg_replace($inWeek, $outWeek, $strOrig), $this->lang));

            $str = date("Y-m-d", EmailDateHelper::parseDateUsingWeekDay($str, $weeknum));
        }

        return $str;
    }

    private function amount($s)
    {
        $amount = str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", str_replace(" ", "", $this->re("#(\d[\d\,\. ]*)#", $s))));

        if (!preg_match('/^\d[,.\'\d]*$/', $amount)) {
            $amount = null;
        }

        return (float) $amount;
    }

    private function currency($s)
    {
        if ($code = $this->re("#\(([A-Z]{3})\)#", $s)) {
            return $code;
        }
        // $744 AUDView Receipt
        if ($code = $this->re("#[\d.,]+\s*([A-Z]{3})(?:\w+|\s+|$)#", $s)) {
            return $code;
        }
        $sym = [
            '€'   => 'EUR',
            'R$'  => 'BRL',
            '£'   => 'GBP',
            '₽'   => 'RUB',
            'S/.' => 'PEN',
            'Ft'  => 'HUF',
            'Kč'  => 'CZK',
            '₺'   => 'TRY',
            '₩'   => 'KRW',
            '$'   => '$', // TODO
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        if (mb_strpos($s, '￥') !== false) {
            if ($this->lang === 'zh') {
                return 'CNY';
            } elseif ($this->lang === 'ja') {
                return 'JPY';
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function opt($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
