<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TravelConfirmationHotel extends \TAccountChecker
{
    public $mailFiles = "expedia/it-143020644.eml, expedia/it-19759953.eml, expedia/it-342777850.eml, expedia/it-35979422.eml, expedia/it-365186364-multi.eml, expedia/it-40295606.eml, expedia/it-42754846.eml, expedia/it-42756140.eml, expedia/it-44732090.eml, expedia/it-46271529.eml, expedia/it-46373567.eml, expedia/it-46751793.eml, expedia/it-49821060.eml, expedia/it-50924922.eml, expedia/it-50924933.eml, expedia/it-52906107.eml, expedia/it-55539526.eml, expedia/it-56808851.eml, expedia/it-58940789.eml, expedia/it-641846931.eml"; // +1 bcdtravel(html)[en]

    private $detectBody = [
        'es'       => ['u reserva de hotel está confirmada', 'Resumen de precios'],
        'es2'      => ['Se confirmó tu reserva', 'Detalles de la habitación'],
        'es3'      => ['VER ITINERARIO COMPLETO', 'Detalles de la habitación'],
        'es4'      => ['VER EL ITINERARIO', 'Detalles del alojamiento'],
        'es5'      => ['VER ITINERARIO COMPLETO', 'Detalles del hospedaje'],
        'es6'      => ['Detalles del viajero', 'Detalles del alojamiento'],
        'es7'      => ['Información del viajero', 'Detalles del hospedaje'],
        'es8'      => ['Datos del viajero', 'Detalles del alojamiento'],
        'fr'       => ['VOIR L’ITINÉRAIRE COMPLET', 'Départ'],
        'fr2'      => ['Votre réservation a été mise à jour', 'Merci'],
        'fr3'      => ['Détails sur l’hébergement', 'Merci'],
        'fr4'      => ['Renseignements sur le(s) voyageur(s)', 'Arrivée'],
        'it'       => ['VISUALIZZA ITINERARIO', 'Dettagli camera'],
        'it2'      => ['Dettagli della sistemazione', 'Dettagli dei viaggiatori'],
        'it3'      => ['Dettagli viaggiatore', 'Dettagli della sistemazione'],
        'ja'       => ['旅程の詳細を表示', 'チェックイン'],
        'ja2'      => ['お客様の詳細情報', '宿泊施設の詳細'],
        'de'       => ['hre Hotelbuchung ist bestätigt', 'Preisübersicht'],
        'de2'      => ['Ihre Buchung wurde aktualisiert', 'Preisübersicht'],
        'de3'      => ['Details zur Unterkunft', 'Zimmer'],
        'nl'       => ['e hotelboeking is bevestigd', 'Kamerdetails'],
        'nl2'      => ['reisplan met je gedeeld', 'Kamerdetails'],
        'nl3'      => ['Accommodatiedetails', 'Inchecken'],
        'en'       => ['our hotel booking is confirmed', 'Check-in'],
        'en1'      => ['VIEW FULL ITINERARY', 'Pricing Summary'],
        'en2'      => ['VIEW FULL ITINERARY', 'Check-in'],
        'en3'      => ['View full itinerary', 'Check-in'],
        'en4'      => ['Accommodation details', 'Check-in'],
        'pt'       => ['VER O ITINERÁRIO COMPLETO', 'Check-in'],
        'pt2'      => ['Sua reserva está', 'Check-in'],
        'pt3'      => ['A sua reserva está', 'Check-in'],
        'pt4'      => ['Detalhes do viajante', 'Detalhes da acomodação'],
        'no'       => ['VIS HELE REISERUTEN', 'Innsjekking'],
        'no2'      => ['Informasjon om overnattingen', 'Innsjekking'],
        'zh'       => ['查看完整行程', '入住時間'],
        'zh2'      => ['查看完整行程', '住宿详情'],
        'zh3'      => ['住宿詳情', '入住'],
        'ko'       => ['전체 일정 보기', '객실 세부 정보'],
        'ko2'      => ['숙소 세부 정보', '체크인'],
        'tr'       => ['Misafir Bilgileri', 'Konaklama yeri detayları'],
        'da'       => ['Oplysninger om overnatningsstedet', 'Oplysninger om rejsende'],
        'sv'       => ['Resenärsuppgifter', 'Boendeuppgifter'],
    ];
    private $detectLang = [
        'es' => ['VER EL ITINERARIO', 'tu itinerario completo', 'VER ITINERARIO COMPLETO', 'Detalles del alojamiento'],
        'fr' => ['VOIR L’ITINÉRAIRE COMPLET', 'Départ'],
        'it' => ['VISUALIZZA ITINERARIO', 'Per i regolamenti e le'],
        'ja' => ['旅程の詳細を表示', 'ホテルをご予約いただきありがとうございます', '宿泊施設の詳細'],
        'de' => ['VOLLSTÄNDIGEN REISEPLAN ANZEIGEN', 'hrem vollständigen Reiseplan',
            'Details zur Unterkunft', ],
        'nl'       => ['VOLLEDIG REISPLAN BEKIJKEN', 'je volledige reisplan'],
        'en'       => ['VIEW FULL ITINERARY', 'full itinerary', 'Accommodation details'],
        'pt'       => ['VER O ITINERÁRIO COMPLETO', 'Detalhes do quarto', 'Sua reserva está confirmada', 'A sua reserva está confirmada'],
        'no'       => ['VIS HELE REISERUTEN', 'Innsjekking'],
        'zh'       => ['查看完整行程', '入住時間'],
        'ko'       => ['체크인', '객실 세부 정보'],
        'tr'       => ['Misafir Bilgileri', 'Konaklama yeri detayları'],
        'da'       => ['Oplysninger om overnatningsstedet', 'Oplysninger om rejsende'],
        'sv'       => ['Resenärsuppgifter', 'Boendeuppgifter'],
    ];
    private $subjects = [
        'es' => ['Confirmación de viaje de Expedia', 'Confirmación de viaje con Expedia', 'Confirmación de viaje de Hoteles.com'],
        'fr' => ['Confirmation de voyage Expedia', 'Itinéraire mis à jour', 'Confirmation de voyage Hotels.com –'],
        'de' => ['Expedia-Reisebestätigung', 'Expedia Reisebestätigung −'],
        'nl' => ['Reisbevestiging van Expedia'],
        'en' => ['Expedia travel confirmation'],
        'it' => ['Conferma di viaggio Expedia:'],
        'pt' => ['Confirmação de viagem da Expedia', 'Confirmação de viagem da Hoteis.com - '],
        'no' => ['Reisebekreftelse fra Expedia'],
        'zh' => ['Expedia 智遊網行程確認', 'Expedia 行程確認 -'],
        'ja' => ['【エクスペディア】ご旅行予約の確認通知 - チェックイン', 'Hotels.com 旅行予約の確認メール -'],
        'ko' => ['익스피디아 여행 확인 -', 'Hotels.com 여행 확인 -'],
        'tr' => ['Expedia seyahat onayı -'],
        'da' => [' Rejsebekræftelse fra Hotels.com –'],
        'sv' => ['Hotels.coms resebekräftelse – '],
    ];
    private $lang = '';
    private $date;
    private $provCode;
    private $currencyCode; // !! use only for getTotalCurrency

    private static $dict = [
        'es' => [
            'headerLinks'     => ['Inicio', 'Hoteles', 'Vuelos', 'Viajes', 'Alquiler de coches'],
            'headerLastLink'  => 'Actividades',
            'Room'            => ['Habitación', 'habitación'],
            'Price'           => ['Precio', 'precio'],
            'confNoInBody'    => ['n.º de itinerario:', 'N.° de itinerario', 'No. de itinerario:', 'Itinerario no.', 'Número de itinerario:'],
            'confNoInSubject' => ['n.º de itinerario', 'n.° de itinerario', 'Itinerario', 'itinerario #', 'número de itinerario:'], // first two values is not the same
            //            'otaPh' => '',
            'You earned'                  => ['Has conseguido', 'Obtuviste'],
            'Expedia Rewards points'      => ['puntos de Expedia Rewards'],
            'Expedia Rewards points used' => ['puntos de Expedia Rewards aplicados', 'puntos de Expedia aplicados'],
            'Contact'                     => ['Contacto', 'contacto', 'comunicate', 'Contacta'],
            'adult'                       => ['adulto', 'adultos', 'adulto(s)'],
            'children'                    => ['Niños', 'Niño', 'Menores'],
            'Room Details'                => ['Detalles de la habitación', 'Detalles del hospedaje', 'Detalles del alojamiento'],
            'Room %'                      => 'Habitación %',
            'Room % Price'                => 'Precio de la habitación %',
            'roomPriceRegExp'             => 'Precio de la habitación \d{1,3}\b',
            'Reserved for'                => ['Reserva a nombre de', 'Reserva para', 'Reservación para'],
            'Special requests'            => ['Solicitudes especiales', 'Ver solicitudes especiales en el itinerario', 'Consultar solicitudes especiales en el itinerario'],
            'Taxes & fees'                => ['Impuestos', 'Impuestos y cargos', 'Tasas e impuestos'],
            'Thank you'                   => ['¡Gracias', 'Gracias,', 'Gracias ,'],
            'Traveler details'            => ['Datos del huésped', 'Información del viajero', 'Detalles del viajero', 'Datos del viajero'],
            'status'                      => ['Tu reserva de hotel está', 'Tu reservación de hotel con está', 'Tu reservación está'],
            'statusVariants'              => 'confirmada',
            'Free cancellation until'     => 'Cancelación gratuita hasta',
            'Cancellations and changes'   => ['Cambios y cancelaciones', 'Cancelaciones y cambios'],
            'Check-in'                    => ['Entrada', 'Check-in', 'Fecha de entrada'],
            'Check-in Time'               => ['Hora de inicio de check-in:', 'Horário inicial do check-in:', 'El registro de entrada comienza a las'],
            'Check-out'                   => ['Salida', 'Check-out', 'Fecha de salida'],
            //            'Minimum check-in age is' => '',
            'Total'      => 'Total',
            'Tel'        => ['Tel', 'Teléfono'],
            'Fax'        => 'Fax',
            'You booked' => ['Reservaste', 'Has reservado'],
            'room'       => ['habitación'],
            '% room'     => ['% habitación'],
        ],
        'fr' => [
            //            'headerLinks' => ['Inicio', 'Hoteles', 'Vuelos', 'Viajes', 'Alquiler de coches'],
            //            'headerLastLink' => 'Actividades',
            'Room' => ['Chambre', 'chambre', 'Hébergement', 'hébergement'],
            //'Price' => ['Precio', 'precio'],
            'confNoInBody'    => ['n° de voyage', 'Itinéraire n°', 'Voyage nᵒ', 'Numéro d’itinéraire :'],
            'confNoInSubject' => ['itinéraire n°', 'Itinéraire n°', 'Itinéraire nº'],
            //            'otaPh' => '',
            'You earned'             => 'Vous accumulerez',
            'Expedia Rewards points' => 'points Récompenses Expedia',
            //            'Expedia Rewards points used' => '',
            'Contact'                   => 'contacter',
            'adult'                     => 'adulte',
            'children'                  => 'enfants',
            'Room Details'              => ['Détails sur la chambre', 'Détails de l’hébergement', 'Détails sur l’hébergement'],
            'Room %'                    => ['Chambre %', 'Hébergement %'],
            'Room % Price'              => 'Prix de la chambre %',
            'roomPriceRegExp'           => 'Prix de la chambre \d{1,3}\b',
            'Reserved for'              => ['Réservation pour', 'Nombre de personnes:'],
            'Special requests'          => ['Demandes spéciales', 'Consulter les demandes spéciales de votre voyage', 'Consulter les demandes spéciales dans votre itinéraire',
                'Afficher les demandes spéciales de mon itinéraire', ],
            'Taxes & fees'              => ['Taxes', 'Frais de propriété', 'Taxes et frais'],
            'Thank you'                 => ['Merci', 'Merci,'],
            'Traveler details'          => ['Détails sur le ou les voyageurs', 'Renseignements sur le(s) voyageur(s)', 'Détails sur le voyageur'],
            'status'                    => "Votre réservation d'hôtel est",
            'statusVariants'            => 'confirmée',
            'Free cancellation until'   => 'Annulation gratuite jusqu’au',
            'Cancellations and changes' => 'Annulations et modifications',
            'Check-in'                  => 'Arrivée',
            'Check-in Time'             => 'Arrivées à partir de',
            'Check-out'                 => 'Départ',
            //            'Minimum check-in age is' => '',
            'Total'      => 'Total',
            'Tel'        => 'Tél',
            'Fax'        => 'Fax',
            'You booked' => 'Vous avez réservé',
            'room'       => ['chambre'],
            '% room'     => ['% chambre'],
        ],
        'it' => [
            'headerLinks'     => ['Home', 'Hotel', 'Voli', 'Volo+hotel', 'Noleggio auto'],
            'headerLastLink'  => 'Cose da fare',
            'Room'            => ['Camera', 'camera'],
            'Price'           => ['Prezzo', 'prezzo'],
            'confNoInBody'    => 'N° di itinerario:',
            'confNoInSubject' => ['N° di itinerario', 'n. itinerario'],
            //            'otaPh' => '',
            //'You earned' => 'Has conseguido',
            //            'Expedia Rewards points' => 'punti Expedia Rewards utilizzati',
            'Expedia Rewards points used' => 'punti Expedia Rewards utilizzati',
            'Contact'                     => ['Contatta', 'contatta'],
            'adult'                       => 'adult',
            'children'                    => 'bambin',
            'Room Details'                => ['Dettagli camera', 'Dettagli della sistemazione', 'Dettagli viaggiatore'],
            'Room %'                      => 'Camera %',
            'Room % Price'                => 'Prezzo della camera %',
            'roomPriceRegExp'             => 'Prezzo della camera\s*\d{1,3}\b',
            'Reserved for'                => 'Prenotazione per',
            'Special requests'            => ['Richieste speciali', 'Vai al tuo itinerario per le richieste speciali'],
            'Taxes & fees'                => ['Tasse', 'Tasse e oneri'],
            'Thank you'                   => ['Grazie', 'Grazie,'],
            'Traveler details'            => 'Dettagli dei viaggiatori',
            'status'                      => 'La tua prenotazione hotel è',
            'statusVariants'              => 'confermata',
            'Free cancellation until'     => 'Cancellazione gratuita entro la data',
            'Cancellations and changes'   => 'Cancellazioni e modifiche',
            'Check-in'                    => 'Check-in',
            //            'Check-in Time' => '',
            'Check-out' => 'Check-out',
            //            'Minimum check-in age is' => '',
            'Total'      => 'Totale',
            'Tel'        => 'Tel',
            'Fax'        => ['Numero di fax', 'Fax'],
            'You booked' => 'Hai prenotato',
            'room'       => ['camera'],
            '% room'     => ['% camera'],
        ],
        'ja' => [
            'headerLinks'     => ['ホーム', '国内旅行', '海外ホテル', 'お客様のアカウント'],
            'headerLastLink'  => '会員プログラム',
            'Room'            => ['部屋'],
            'Price'           => ['合計'],
            'confNoInBody'    => '旅程番号 :',
            'confNoInSubject' => '旅程番号',
            //            'otaPh' => '',
            'You earned'                  => 'エクペディア会員プログラムで',
            'Expedia Rewards points'      => 'ポイントを獲得しました',
            'Expedia Rewards points used' => 'エクスペディア会員プログラムポイント使用分',
            'Contact'                     => ['ご予約のお'],
            'adult'                       => ['名', '大人'],
            'children'                    => '乳幼児',
            'Room Details'                => ['部屋の詳細', '宿泊施設の詳細'],
            'Room %'                      => '部屋 %',
            'Room % Price'                => '部屋 % の料金',
            'roomPriceRegExp'             => '部屋 \d{1,2} の料金\b',
            'Reserved for'                => 'ご予約者名',
            'Special requests'            => ['旅程で宿泊施設への要望を確認', '旅程を開いて宿泊施設への要望を表示'],
            'Taxes & fees'                => '税',
            'Thank you'                   => '様、ホテルをご予約いただきありがとうございます',
            'Traveler details'            => ['お客様の詳細情報', 'お客様の詳細'],
            //            'status' => '',
            //            'statusVariants' => '',
            'Free cancellation until'   => 'までキャンセル手数料無料',
            'Cancellations and changes' => 'キャンセルおよび変更',
            'Check-in'                  => 'チェックイン',
            'Check-in Time'             => 'チェックイン開始時刻',
            'Check-out'                 => 'チェックアウト',
            //            'Minimum check-in age is' => '',
            'Total'      => '合計',
            'Tel'        => '電話',
            'Fax'        => 'FAX',
            'You booked' => 'ご予約されました。',
            'room'       => ['部屋'],
            '% room'     => ['% 部屋'],
        ],
        'de' => [
            'headerLinks'     => ['Flug', 'Mietwagen', 'Pauschalreisen'],
            'headerLastLink'  => 'Top Reisedeals',
            'Room'            => ['Zimmer'],
            'Price'           => ['Preis'],
            'confNoInBody'    => 'Reiseplannummer',
            'confNoInSubject' => 'Reiseplannr',
            //            'otaPh' => '',
            //            'You earned' => '',
            //            'Expedia Rewards points' => '',
            //            'Expedia Rewards points used' => '',
            'Contact' => ['Setzen', 'setzen'],
            'adult'   => 'Erwachsen',
            //            'children' => '',
            'Room Details'              => ['Zimmerdetails', 'Details zur Unterkunft'],
            'Room %'                    => 'Zimmer %',
            'Room % Price'              => 'Preis von Zimmer %',
            'roomPriceRegExp'           => 'Preis von Zimmer \d{1,3}\b',
            'Reserved for'              => 'Reserviert für',
            'Special requests'          => ['Sonderwünsche', 'Sonderwünsche in deinem Reiseplan anzeigen'],
            'Taxes & fees'              => ['Steuern', 'Steuern und Gebühren'],
            'Thank you'                 => 'Vielen Dank',
            'Traveler details'          => 'Angaben zu den Reisenden',
            'status'                    => 'Ihre Hotelbuchung ist',
            'statusVariants'            => 'bestätigt',
            'Free cancellation until'   => 'Kostenlose Stornierung bis',
            'Cancellations and changes' => 'Stornierungen und Änderungen',
            'Check-in'                  => 'Check-in',
            'Check-in Time'             => 'Check-in ab',
            //            'Check-out' => 'Check-out',
            //            'Minimum check-in age is' => '',
            'Total' => ['Gesamtpreis', 'Gesamtbetrag'],
            //            'Tel' => '',
            //            'Fax' => '',
            'You booked' => 'Du hast',
            'room'       => ['Zimmer'],
            '% room'     => ['% Zimmer'],
        ],
        'nl' => [
            //            'headerLinks' => [''],
            //            'headerLastLink' => '',
            'Room'            => ['Kamer', 'kamer'],
            'Price'           => ['Prijs', 'prijs'],
            'confNoInBody'    => ['Reisplannr.', 'Reisplannummer'],
            'confNoInSubject' => 'Reisplannr.',
            //            'otaPh' => '',
            //            'You earned' => '',
            //            'Expedia Rewards points' => '',
            //            'Expedia Rewards points used' => '',
            'Contact' => ['Contacteer', 'contacteer'],
            'adult'   => ['volwassenen'],
            //            'children' => '',
            'Room Details'              => ['Kamerdetails', 'Accommodatiedetails'],
            'Room %'                    => 'Kamer %',
            'Room % Price'              => 'Prijs kamer %',
            'roomPriceRegExp'           => 'Prijs kamer \d{1,3}\b',
            'Reserved for'              => 'Geboekt voor',
            'Special requests'          => ['Speciale verzoeken', 'Bekijk speciale verzoeken in je reisplan'],
            'Taxes & fees'              => 'Belastingen',
            'Thank you'                 => ['Hallo', 'Bedankt,'],
            'Traveler details'          => 'Reizigersinformatie',
            //            'status' => '',
            //            'statusVariants' => '',
            'Free cancellation until'   => 'Gratis annulering tot',
            'Cancellations and changes' => ['Annuleringen en wijzigingen', 'Wijzigingen en annuleringen'],
            'Check-in'                  => ['Check-in', 'Inchecken'],
            //            'Check-in Time' => '',
            'Check-out' => ['Check-out', 'Uitchecken'],
            //            'Minimum check-in age is' => '',
            'Total' => 'Totaal',
            //            'Tel' => '',
            //            'Fax' => '',
            'You booked' => 'Je hebt',
            'room'       => ['kamer'],
            '% room'     => ['% kamer'],
        ],
        'en' => [
            'headerLinks'     => ['Packages', 'Hotels', 'Cars', 'Flights', 'Cruises', 'My Account'],
            'headerLastLink'  => ['Rewards', "Today's Deal"],
            'Room'            => ['Room', 'room'],
            'Price'           => ['Price', 'price'],
            'confNoInBody'    => ['Itinerary #', 'Expedia itinerary:'],
            'confNoInSubject' => ['Itinerary', 'Itinerary no'],
            'otaPh'           => ['Domestic Phone Number', 'International Phone Number'],
            'You earned'      => ['You earned', 'You will earn', "You'll earn"],
            //'Expedia Rewards points' => '',
            'Expedia Rewards points used' => ['Expedia Rewards points used', 'OneKeyCash used'],
            'Contact'                     => ['Contact', 'contact'],
            'adult'                       => ['adult', 'Adults'],
            'children'                    => ['children', 'child', 'infant'],
            'Room Details'                => ['Room Details', 'Accommodation Details', 'Accommodation details'],
            'Room %'                      => ['Room %', 'Rooms %', 'Accommodation %', '% room', '% rooms'],
            //            'Room % Price' => '',
            'roomPriceRegExp'  => ['Room \d{1,3} Price', 'Room Price'],
            'Reserved for'     => ['Reserved for', 'Reserved for [First / Last name]'],
            'Special requests' => ['Special requests', 'View special requests in your itinerary'],
            'Taxes & fees'     => ['Taxes & fees', 'Taxes & Fees', 'Taxes'],
            'Thank you'        => ['Thank you,', 'Thank you ,'],
            'Traveler details' => ['Traveler details', 'Traveler Details', 'Traveller details'],
            'status'           => ['Your hotel booking is', 'Your booking is'],
            'statusVariants'   => 'confirmed',
            //            'Free cancellation until' => '',
            //            'Cancellations and changes' => '',
            //            'Check-in' => '',
            'Check-in Time' => ['Check-in time', 'Check-in Time'],
            //            'Check-out' => '',
            //            'Minimum check-in age is' => '',
            //            'Total' => '',
            'Tel' => ['Tel', 'Phone'],
            //            'Fax' => '',
            //'You booked' => '',
            'room' => ['room', 'accommodation'],
        ],
        'pt' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            'Room'            => ['Quarto', 'quarto'],
            'Price'           => ['Preço', 'preço'],
            'confNoInBody'    => ['Nº do itinerário:', 'Itinerário n.º'],
            'confNoInSubject' => 'itinerário nº',
            //            'otaPh' => '',
            'You earned'             => 'Você ganhou',
            'Expedia Rewards points' => 'pontos do Expedia Rewards',
            //            'Expedia Rewards points used' => '',
            'Contact'                   => ['contato'],
            'adult'                     => ['adulto', 'Adultos'],
            'children'                  => ['criança', 'Crianças', 'Bebês'],
            'Room Details'              => ['Detalhes do quarto', 'Detalhes da acomodação', 'Detalhes do alojamento'],
            'Room %'                    => 'Quarto %',
            '% room'                    => ['% quarto', '% quartos'],
            'Room % Price'              => 'Preço do quarto %',
            'roomPriceRegExp'           => 'Preço do quarto \d{1,3}',
            'Reserved for'              => ['Reservado para'],
            'Special requests'          => ['Solicitações especiais', 'Consulte as solicitações especiais no seu itinerário', 'Consulte os pedidos especiais no seu itinerário'],
            'Taxes & fees'              => ['Taxa para hóspede extra', 'Impostos', 'Impostos e taxas'],
            'Thank you'                 => ['Obrigado,', 'Olá,'],
            'Traveler details'          => ['Detalhes do viajante', 'Informações sobre o viajante'],
            'status'                    => ['Sua reserva de hotel está', 'Sua reserva está'],
            'statusVariants'            => 'confirmada',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => 'Cancelamentos e alterações',
            'Check-in'                  => 'Check-in',
            'Check-in Time'             => 'Horário inicial do check-in:',
            'Check-out'                 => 'Check-out',
            //            'Minimum check-in age is' => '',
            'Total' => 'Total',
            'Tel'   => ['Tel'],
            //            'Fax' => '',
            'You booked' => ['Você reservou', 'Reservou'],
            'room'       => ['quarto', 'quartos'],
        ],
        'no' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            'Room' => ['Boenhet'],
            //            'Price' => ['Price', 'price'],
            'confNoInBody'    => 'Reiserutenummer:',
            'confNoInSubject' => 'reiserutenr.',
            //            'otaPh' => ['Domestic Phone Number', 'International Phone Number'],
            'You earned'                  => 'Du vil tjene',
            'Expedia Rewards points'      => 'Expedia Rewards-poeng',
            'Expedia Rewards points used' => 'Expedia Rewards-poeng brukt',
            //            'Contact' => ['Contact', 'contact'],
            'adult'        => ['voksne', 'voksen'],
            'children'     => ['barn'],
            'Room Details' => ['Informasjon om overnattingsstedet', 'Informasjon om overnattingen'],
            'Room %'       => 'Boenhet %',
            //            'Room % Price' => '',
            'roomPriceRegExp' => ['Prissammendrag'],
            //            'Reserved for' => ['Reserved for', 'Reserved for [First / Last name]'],
            'Special requests'          => 'Se spesielle forespørsler i reiseruten din',
            'Taxes & fees'              => ['Skatter', 'Skatter og avgifter'],
            'Thank you'                 => 'Takk',
            'Traveler details'          => 'Informasjon om reisende',
            //            'status' => '',
            //            'statusVariants' => '',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => 'Avbestillinger og endringer',
            'Check-in'                  => 'Innsjekking',
            //            'Check-in Time' => ['Check-in time', 'Check-in Time'],
            'Check-out' => 'Utsjekking',
            //            'Minimum check-in age is' => '',
            'Total'      => 'Totalt',
            'Tel'        => ['Telefon:', 'Tlf:'],
            'Fax'        => 'Faks:',
            'You booked' => 'Du bestilte',
            'room'       => ['rom'],
            '% room'     => ['% rom'],
        ],
        'zh' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            'Room' => ['客房 ', '总计'],
            //            'Price' => ['Price', 'price'],
            'confNoInBody'    => '行程編號',
            'confNoInSubject' => '行程編號',
            //            'otaPh' => ['Domestic Phone Number', 'International Phone Number'],
            'You earned'                  => ['您將可獲得', '您将累积'],
            'Expedia Rewards points'      => ['點 Expedia Rewards', '个 Expedia Rewards 积分'],
            'Expedia Rewards points used' => '已使用 OneKeyCash 奖励金',
            //            'Contact' => ['Contact', 'contact'],
            'adult'        => ['位成人', '成人'],
            'children'     => ['位兒童', '兒童'],
            'Room Details' => ['客房詳情', '住宿详情', '住宿詳情'],
            'Room %'       => '客房 %',
            //            'Room % Price' => '',
            //            'roomPriceRegExp' => ['Prissammendrag'],
            // 'Reserved for'              => ['旅客'],
            'Special requests'          => ['特殊要求', '查看行程特別要求', '查看您行程中的特殊要求'],
            'Taxes & fees'              => ['稅金', '稅項及其他費用'],
            'Thank you'                 => '感謝您！',
            'Traveler details'          => ['旅客详细信息', '旅客詳情', '旅客資料', '详细旅客信息'],
            'status'                    => '您的預訂已經確認。',
            //            'statusVariants' => '',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => ['取消和變更', '取消及更改', '取消和变更'],
            'Check-in'                  => ['入住時間', '入住'],
            'Check-in Time'             => ['入住登記開始時間：', '开始办理入住'],
            'Check-out'                 => ['退房時間', '退房'],
            //            'Minimum check-in age is' => '',
            'Total' => ['總計', '总计', '合計', '总价'],
            //            'Tel' => ['Telefon:', 'Tlf:'],
            //            'Fax' => 'Faks:',
            'You booked' => ['您已預訂', '你預訂了', '您预订了'],
            'room'       => ['間客房', '间客房'],
            '% room'     => ['% 間客房', '% 间客房'],
        ],
        'ko' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            'Room' => ['객실'],
            //            'Price' => ['Price', 'price'],
            'confNoInBody'    => '일정 번호:',
            'confNoInSubject' => '일정 번호:',
            //            'otaPh' => ['Domestic Phone Number', 'International Phone Number'],
            //            'You earned'             => [''],
            //            'Expedia Rewards points' => ['點 Expedia Rewards', '个 Expedia Rewards 积分'],
            'Expedia Rewards points used' => '포인트 사용됨',
            //            'Contact' => ['Contact', 'contact'],
            'adult'        => ['성인'],
            'children'     => ['아동'],
            'Room Details' => ['객실 세부 정보', '숙소 세부 정보'],
            'Room %'       => '객실 %',
            //            'Room % Price' => '',
            //            'roomPriceRegExp' => ['Prissammendrag'],
            'Reserved for'              => ['예약자'],
            'Special requests'          => '일정에 포함된 특별 요청 보기',
            'Taxes & fees'              => ['세금 및 수수료', '세금'],
            'Thank you'                 => '감사합니다,',
            'Traveler details'          => '여행객 정보',
            //            'status' => '',
            //            'statusVariants' => '',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => '취소 및 변경',
            'Check-in'                  => ['체크인'],
            'Check-in Time'             => ['체크인 시작 시간:'],
            'Check-out'                 => ['체크아웃'],
            //            'Minimum check-in age is' => '',
            'Total' => ['합계'],
            //            'Tel' => ['Telefon:', 'Tlf:'],
            //            'Fax' => 'Faks:',
            'You booked' => '예약된 객실:',
            'room'       => ['개'],
            '% room'     => ['%개'],
        ],
        'tr' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            // 'Room' => ['객실'],
            //            'Price' => ['Price', 'price'],
            'confNoInBody'    => 'Seyahat program numarası:',
            'confNoInSubject' => 'seyahat programı no.',
            //            'otaPh' => ['Domestic Phone Number', 'International Phone Number'],
            //            'You earned'             => [''],
            //            'Expedia Rewards points' => ['點 Expedia Rewards', '个 Expedia Rewards 积分'],
            // 'Expedia Rewards points used' => '포인트 사용됨',
            //            'Contact' => ['Contact', 'contact'],
            'adult'        => ['Yetişkin'],
            'children'     => ['Çocuk'],
            'Room Details' => ['Konaklama yeri detayları'],
            // 'Room %'       => '객실 %',
            //            'Room % Price' => '',
            //            'roomPriceRegExp' => ['Prissammendrag'],
            // 'Reserved for'     => ['예약자'],
            'Special requests'          => 'Seyahat programınızdaki özel istekleri görüntüleyin',
            'Taxes & fees'              => ['Vergiler'],
            'Thank you'                 => 'Teşekkür ederiz,',
            'Traveler details'          => ['Misafir Bilgileri', 'Misafir bilgileri'],
            //            'status' => '',
            //            'statusVariants' => '',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => 'İptaller ve değişiklikler',
            'Check-in'                  => ['Giriş'],
            'Check-in Time'             => ['Giriş başlangıç saati:'],
            'Check-out'                 => ['Çıkış'],
            //            'Minimum check-in age is' => '',
            'Total' => ['Toplam'],
            //            'Tel' => ['Telefon:', 'Tlf:'],
            //            'Fax' => 'Faks:',
            'You booked' => 'rezervasyonu yaptınız',
            'room'       => ['oda'],
            '% room'     => ['% oda'],
        ],
        'da' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            // 'Room' => ['객실'],
            //            'Price' => ['Price', 'price'],
            'confNoInBody'    => 'Rejseplansnummer:',
            'confNoInSubject' => 'rejseplansnummer:',
            //            'otaPh' => ['Domestic Phone Number', 'International Phone Number'],
            //            'You earned'             => [''],
            //            'Expedia Rewards points' => ['點 Expedia Rewards', '个 Expedia Rewards 积分'],
            // 'Expedia Rewards points used' => '포인트 사용됨',
            //            'Contact' => ['Contact', 'contact'],
            'adult'        => ['Voksne'],
            // 'children'     => ['Çocuk'],
            'Room Details' => ['Oplysninger om overnatningsstedet'],
            // 'Room %'       => '객실 %',
            //            'Room % Price' => '',
            //            'roomPriceRegExp' => ['Prissammendrag'],
            // 'Reserved for'     => ['예약자'],
            'Special requests'          => 'Se særlige anmodninger i din rejseplan',
            'Taxes & fees'              => ['Skatter og gebyrer'],
            'Thank you'                 => 'Tak,',
            'Traveler details'          => 'Oplysninger om rejsende',
            //            'status' => '',
            //            'statusVariants' => '',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => 'Afbestillinger og ændringer',
            'Check-in'                  => ['Indtjekning'],
            'Check-in Time'             => ['Indtjekning starter kl.'],
            'Check-out'                 => ['Udtjekning'],
            //            'Minimum check-in age is' => '',
            'Total' => ['I alt'],
            //            'Tel' => ['Telefon:', 'Tlf:'],
            //            'Fax' => 'Faks:',
            'You booked' => 'Du har booket',
            'room'       => ['værelse'],
            '% room'     => ['% værelse', '% værelser'],
        ],
        'sv' => [
            //            'headerLinks' => [],
            //            'headerLastLink' => [],
            // 'Room' => ['객실'],
            //            'Price' => ['Price', 'price'],
            'confNoInBody'    => 'Resplansnummer',
            'confNoInSubject' => 'resplansnummer',
            //            'otaPh' => ['Domestic Phone Number', 'International Phone Number'],
            //            'You earned'             => [''],
            //            'Expedia Rewards points' => ['點 Expedia Rewards', '个 Expedia Rewards 积分'],
            // 'Expedia Rewards points used' => '포인트 사용됨',
            //            'Contact' => ['Contact', 'contact'],
            'adult'        => ['Vuxna'],
            // 'children'     => ['Çocuk'],
            'Room Details' => ['Boendeuppgifter'],
            // 'Room %'       => '객실 %',
            //            'Room % Price' => '',
            //            'roomPriceRegExp' => ['Prissammendrag'],
            // 'Reserved for'     => ['예약자'],
            'Special requests'          => 'Se särskilda önskemål i din resplan',
            'Taxes & fees'              => ['Skatter', 'Skatter och avgifter'],
            'Thank you'                 => 'Tack,',
            'Traveler details'          => 'Resenärsuppgifter',
            //            'status' => '',
            //            'statusVariants' => '',
            //            'Free cancellation until' => '',
            'Cancellations and changes' => 'Avbokningar och ändringar',
            'Check-in'                  => ['Incheckning'],
            'Check-in Time'             => ['Incheckningstiden börjar'],
            'Check-out'                 => ['Utcheckning'],
            //            'Minimum check-in age is' => '',
            'Total' => ['Totalt'],
            //            'Tel' => ['Telefon:', 'Tlf:'],
            //            'Fax' => 'Faks:',
            'You booked' => 'Du bokade',
            'room'       => ['rum'],
            '% room'     => ['% rum'],
        ],
    ];

    private $patterns = [
        'time'  => '\d{1,2}[.:]+\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?',
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    (+351) 21 342 09 07    |    713.680.2992
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        foreach ($this->detectLang as $lang => $dtext) {
            if ($this->http->XPath->query("//*[{$this->contains($dtext)}]")->length > 0) {
                $this->lang = $lang;
            }
        }

        if (!empty($this->provCode)) {
            $email->setProviderCode($this->provCode);
        }

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        // remove garbage (it-365186364-multi.eml)
        $nodesToStip = $this->http->XPath->query("//tr[not(.//tr) and {$this->eq("Learn about this property's cleaning and safety practices before your trip begins.")}]");

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $hotelRoots = $this->http->XPath->query("//*[{$this->eq($this->t('Check-in'))}]/ancestor::*[ descendant::*[{$this->contains($this->t('Contact'))}] ][1]");

        if ($hotelRoots->length > 1) {
            // for multi-reservation emails (it-365186364-multi.eml)
            foreach ($hotelRoots as $hRoot) {
                $this->parseEmail($email, $hRoot, $parser->getHeader('subject'));
            }
        } else {
            $this->parseEmail($email, null, $parser->getHeader('subject'));
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->detectProv() === true) {
            foreach ($this->detectBody as $dBody) {
                if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody[0]}')]")->length > 0
                    && $this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody[1]}')]")->length > 0
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectProv()
    {
        if ($this->http->XPath->query("//img[contains(@alt,'Expedia') or contains(@alt,'expedia')] | //a[contains(@href,'expedia')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(),'Expedia, Inc. All rights reserved') or contains(.,'@expediamail.com')]")->length > 0) {
            $this->provCode = 'expedia';

            return true;
        } elseif ($this->http->XPath->query("//*[(contains(normalize-space(),'Hotels.com') or contains(normalize-space(),'Hoteis.com') or contains(normalize-space(),'Hoteles.com')) and contains(normalize-space(), 'app')]")->length > 0) {
            $this->provCode = 'hotels';

            return true;
        } elseif ($this->http->XPath->query("//img[contains(@alt,'Orbitz.com')] | //a[contains(@href,'orbitz.com')]")->length > 0) {
            $this->provCode = 'orbitz';

            return true;
        } elseif ($this->http->XPath->query("//img[contains(@alt,'CheapTickets')] | //a[contains(@href,'cheaptickets.com')]")->length > 0) {
            $this->provCode = 'cheaptickets';

            return true;
        } elseif ($this->http->XPath->query("//img[contains(@alt,'Travelocity')] | //a[contains(@href,'travelocity.com')]")->length > 0) {
            $this->provCode = 'travelocity';

            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        return stripos($from, 'expediamail.com') !== false;
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
        return ['expedia', 'orbitz', 'hotels', 'cheaptickets', 'travelocity'];
    }

    private function parseEmail(Email $email, ?\DOMNode $hRoot = null, $subject): void
    {
        $xpathNoEmpty = '(normalize-space() and normalize-space()!=" " and normalize-space()!=" ")';
        $xpathNoDisplay = 'ancestor-or-self::*[contains(translate(@style," ",""),"display:none")]';
        $xpathExpediamail = "(contains(@href,'expediamail') or contains(@href,'eg.expedia.com') or contains(@href,'eg.hotels.com'))";
        $xpathImgExpedia = "(contains(@alt,'Expedia') and not(contains(@alt,'Reward')) or contains(@src,'Expedia_Logo') or contains(@src,'expedia_logo'))";

        $headers = $this->http->XPath->query("descendant::text()[{$this->eq($this->t('headerLinks'))}]/ancestor::tr[1][{$this->contains($this->t('headerLastLink'))}]", $hRoot);

        if ($headers->length !== 1) {
            $headers = $this->http->XPath->query("descendant::img[contains(@src,'wrapper') and contains(@src,'logo')]/ancestor::a[{$xpathExpediamail} or not(@href)]/ancestor::tr[1]", $hRoot);
        }

        if ($headers->length !== 1) {
            $headers = $this->http->XPath->query("descendant::img[contains(@src,'image') and contains(@src,'logo')]/ancestor::a[{$xpathExpediamail} or not(@href)]/ancestor::tr[1]", $hRoot);
        }

        if ($headers->length !== 1) {
            $headers = $this->http->XPath->query("descendant::img[{$xpathImgExpedia}]/ancestor::a[{$xpathExpediamail}]/ancestor::tr[1]", $hRoot);

            if ($headers->length === 0) {
                $headers = $this->http->XPath->query("descendant::img[{$xpathImgExpedia}]/ancestor::a[{$xpathExpediamail}]/ancestor::div[1]", $hRoot);
            }
        }

        if ($headers->length !== 1) {
            $headers = $this->http->XPath->query("descendant::img[contains(@alt,'Orbitz.com')]/ancestor::a[contains(@href,'orbitz.com')]/ancestor::tr[1]", $hRoot);

            if ($headers->length) {
                $email->setProviderCode('orbitz');
            }
        }

        if ($headers->length !== 1) {
            $headers = $this->http->XPath->query("descendant::tr[not(.//tr) and {$this->starts($this->t('Thank you'))} and not(descendant::a) and not({$xpathNoDisplay})]/preceding::tr[ not(.//tr) and ({$xpathNoEmpty} or descendant::img[{$xpathImgExpedia}]) ][1]", $hRoot);

            if ($headers->length === 0) {
                $headers = $this->http->XPath->query("descendant::div[not(.//div or .//tr) and {$this->starts($this->t('Thank you'))} and not(descendant::a) and not({$xpathNoDisplay})]/preceding::div[ not(.//div or .//tr) and ({$xpathNoEmpty} or descendant::img[{$xpathImgExpedia}]) ][1]", $hRoot);
            }
        }

        if ($headers->length !== 1) {
            $this->logger->debug('other format');

            return;
        }
        $root = $headers->item(0);

        // HOTEL
        $r = $email->add()->hotel();

        // Travel Agency
        $confNo = null;
        $confNoTitle = 'Itinerary #';

        if (preg_match("/({$this->opt($this->t('confNoInBody'))})[ ]*(\d+)$/",
            $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}][1]", $hRoot), $m)
        ) {
            $confNo = $m[2];
            $confNoTitle = $m[1];
        }

        if (empty($confNo)) {
            $confNo = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}][1]/following::text()[normalize-space()][1]", $hRoot, false, '/^\d{5,}$/');
            $confNoTitle = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('confNoInBody'))}][1]", $hRoot, true, '/^(.+?)[\s:]*$/');
        }

        $this->logger->debug($confNo);

        if (empty($confNo)
            && preg_match("/({$this->opt($this->t('confNoInSubject'))}\s*[#.:]*)[-:\s]*([-A-Z\d]{5,})\b/", $subject, $m)
        ) {
            $confNo = $m[2];
            $confNoTitle = trim($m[1], ':');
        }

        $otaPhones = (array) $this->t('otaPh');
        $addedPhones = [];

        foreach ($otaPhones as $descr) {
            $node = trim($this->http->FindSingleNode("descendant::text()[{$this->starts($descr)}]/ancestor::tr[normalize-space()][1]", $hRoot, false, "#{$this->opt($descr)}: *({$this->patterns['phone']})#"));

            if (!empty($node) && !in_array($node, $addedPhones)) {
                $addedPhones[] = $node;
                $r->ota()->phone($node, $descr);
            }
        }

        $nodes = array_filter($this->http->FindNodes("descendant::text()[{$this->starts($this->t('You earned'))}]", $hRoot));
        $earned = [];

        foreach ($nodes as $node) {
            if (preg_match("#{$this->opt($this->t('You earned'))} (\d+ {$this->opt($this->t('Expedia Rewards points'))})#", $node, $m)) {
                $earned[] = $m[1];
            } elseif (preg_match("/{$this->opt($this->t('You earned'))}\s*(\D[\d\.\,]+)/", $node, $m)) {
                $earned[] = $m[1];
            }
        }

        if (count($earned) == 1) {
            $r->ota()->earnedAwards($earned[0]);
        }

        // General
        if (!empty($confNo)) {
            $r->general()->confirmation($confNo, $confNoTitle);
        } else {
            $r->general()
                ->noConfirmation();
        }

        $reservedFor = [];
        $nodes = $this->http->XPath->query("descendant::tr[{$this->starts($this->t('Reserved for'))} and not(.//tr)]/ancestor::table[1][{$this->starts($this->t('Reserved for'))}]", $hRoot);

        foreach ($nodes as $rroot) {
            $reservedFor[] = implode("\n", $this->http->FindNodes("./descendant::tr[not({$this->contains($this->t('Reserved for'))})]/descendant::text()[normalize-space()]", $rroot));
        }
        $reservedFor = array_filter($reservedFor);

        if (empty($reservedFor)) {
            $reservedFor = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Reserved for'))}]/following::text()[normalize-space()][1]", $hRoot);
            $reservedFor = array_filter($reservedFor);
        }

        if (empty($reservedFor)) {
            $reservedFor = $this->http->FindNodes("descendant::text()[{$this->starts($this->t('Reserved for'))}]", $hRoot);
            $reservedFor = array_filter($reservedFor);
        }

        $travellers = array_map(function ($s) { return preg_match('/^([[:alpha:]][-.\'[:alpha:]\s]*[[:alpha:]])(?:\s*,|$|\n)/u', $s, $m) ? $m[1] : ''; }, $reservedFor);
        $travellers = array_map(function ($el) { return trim(str_replace('大人', '', $el)); }, $travellers);

        if (count(array_unique(array_filter($travellers))) > 0) {
            $r->general()->travellers(array_unique(array_filter($travellers)));
        } else {
            $travellerNames = array_filter($this->http->FindNodes("following::text()[normalize-space()][position()<20][{$this->starts($this->t('Thank you'))}][not(ancestor::a)][not({$this->contains('reservation')})]", $root, "/^{$this->opt($this->t('Thank you'))}[,\s]+([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*[.]\s*{$this->opt($this->t('status'))}|\s*[,;:!?]|$)/u"));

            if (in_array($this->lang, ['zh'])) {
                $travellerNames = array_filter($this->http->FindNodes("following::text()[normalize-space()][position()<20][{$this->contains($this->t('Thank you'))}][not(ancestor::a)][not({$this->contains('reservation')})]", $root, "/^\s*([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])\s*，\s*{$this->opt($this->t('Thank you'))}(?:\s*{$this->opt($this->t('status'))}|\s*[,;:!?]|$)/u"));
            }
            $travellerNames = preg_replace("/^\s*travell?er\s*$/i", '', $travellerNames);

            if (count(array_unique(array_filter($travellerNames))) === 1) {
                $traveller = array_shift($travellerNames);
                $r->general()->traveller($traveller);
            }
        }

        $status = $this->http->FindSingleNode("following::text()[normalize-space()][position()<20][{$this->contains($this->t('status'))}][1]", $root, true, "/{$this->opt($this->t('status'))}\s+({$this->opt($this->t('statusVariants'))})(?:\s*[,.:;!?]|$)/u");

        if ($status) {
            $r->general()->status($status);
        }

        $cancellation = '';

        $cancellationParts = [];
        $cancellationRows = $this->http->XPath->query("descendant::tr[not(.//tr) and {$this->eq($this->t('Cancellations and changes'))}]/following::tr[not(.//tr) and normalize-space()][normalize-space()][position()<10]", $hRoot);

        if ($cancellationRows->length === 0) {
            $cancellationRows = $this->http->XPath->query("descendant::div[not(.//div) and {$this->eq($this->t('Cancellations and changes'))}]/following::div[not(.//div) and normalize-space()][normalize-space()][position()<10]", $hRoot);
        }

        foreach ($cancellationRows as $cRow) {
            $cRowText = $this->http->FindSingleNode(".", $cRow, true, "/^[*\s]*([\s\S]*(?:cancel|annul|キャンセ|avbestill|afbestill|Stornier|取消)[\s\S]*)/ui");

            if ($cRowText) {
                $cancellationParts[] = $cRowText;
            } else {
                break;
            }
        }

        if (count($cancellationParts)) {
            $cancellation = implode(' ', $cancellationParts);
        }

        if (empty($cancellation)) {
            $cancellation = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t('Free cancellation until'))}]", $hRoot)
                ?? $this->http->FindSingleNode("following::div[not(.//div) and normalize-space()][position()<25][{$this->starts($this->t('Free cancellation until'))}]", $root);
        } else {
            $cancellation2 = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t('Free cancellation until'))}][following::tr[not(.//tr) and normalize-space()][position()<50][{$this->eq($this->t('Cancellations and changes'))}]]", $hRoot)
                ?? $this->http->FindSingleNode("descendant::div[not(.//div) and {$this->starts($this->t('Free cancellation until'))}][following::div[not(.//div) and normalize-space()][position()<50][{$this->eq($this->t('Cancellations and changes'))}]]", $hRoot)
            ;

            if (!empty($cancellation2)) {
                $cancellation = $cancellation2 . '. ' . $cancellation;
            }
        }

        $cancellation = str_replace(['{', '}', '0,,hotelName'], '', $cancellation);

        if (!empty($cancellation)) {
            $r->general()->cancellation(ltrim($cancellation, '* '));
        }

        if (preg_match("/\b\d{1,2}[ ]+[[:alpha:]]+[ ]+\d{4}\b/u", $cancellation, $m)) {
            // 17 October 2022
            $dateFromCancellation = strtotime($m[0]);

            if ($dateFromCancellation) {
                $this->date = $dateFromCancellation;
            }
        } elseif (preg_match("/^©\s*(\d{4})\s*Expedia/imu", implode("\n", $this->http->FindNodes("//text()[starts-with(normalize-space(),'©') and contains(normalize-space(),'Expedia')]")), $m)) {
            $dateFromFooter = strtotime('01.01.' . $m[1]);

            if ($dateFromFooter) {
                $this->date = $dateFromFooter;
            }
        }

        // Hotel
        $xpathHotelName = "[ count(*)=2 and *[1][descendant::img and normalize-space()=''] and preceding::*[{$this->eq($this->t('Traveler details'))}] and following::text()[{$this->eq($this->t('Check-in'))}] ][1]/*[2][normalize-space()][not(descendant::tr[not(.//tr) and normalize-space()][2])]";

        $name = $this->http->FindSingleNode("following::*" . $xpathHotelName . "[following::text()[normalize-space()][1][not({$this->eq($this->t('Check-in'))})]]", $root);
        $address = $this->http->FindSingleNode("following::*[ normalize-space()"
            . " and preceding-sibling::*[normalize-space()][1]/descendant-or-self::*{$xpathHotelName}[following::text()[normalize-space()][1][not({$this->eq($this->t('Check-in'))})]] and following-sibling::*[descendant::text()[normalize-space()][1][{$this->starts($this->t('Check-in'))}]] ][1]", $root);

        if (empty($name) || empty($address)) {
            $name = $this->http->FindSingleNode("following::text()[normalize-space()][position()<50][{$this->eq($this->t('Check-in'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]/preceding::td[normalize-space()][1]", $root);
            $address = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Check-in'))}][1]/preceding::text()[normalize-space()][1]/ancestor::td[1]", $root);
        }

        if (empty($name) && empty($address)) {
            $name = $this->http->FindSingleNode("following::img[not(contains(@href,'/Icon') or contains(@href,'/icon') or contains(@width,'24'))][1][following::text()[normalize-space()][position()<6][{$this->eq($this->t('Check-in'))}]]/following::text()[normalize-space()][1][not(following::text()[normalize-space()][1][{$this->contains($this->t('Check-in'))}])]", $root);
            $address = $this->http->FindSingleNode("following::img[not(contains(@href,'/Icon') or contains(@href,'/icon') or contains(@width,'24'))][1][following::text()[normalize-space()][position()<6][{$this->eq($this->t('Check-in'))}]]/following::text()[normalize-space()][2][not({$this->contains($this->t('Check-in'))})]", $root);
        }

        if (empty($name) && empty($address)) {
            $info = $this->http->FindNodes("(following::img/ancestor::tr[1]/following-sibling::tr[normalize-space()][1][count(descendant::text()[normalize-space()])=2])[1]/descendant::text()[normalize-space()]", $root);

            if (count($info) === 2) {
                $name = $info[0];
                $address = $info[1];
            }
        }

        if ((empty($name) || empty($address)) && $this->http->XPath->query("//text()[{$this->eq($this->t('Traveler details'))}]/following::div[not(.//div)][normalize-space()][2][{$this->eq($this->t('Check-in'))}]")->length > 0) {
            $name = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Traveler details'))}]/preceding::text()[normalize-space()][1][{$this->starts($this->t('confNoInBody'))}]/preceding::text()[normalize-space()][1]", $root);
            $address = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Check-in'))}][1]/preceding::text()[normalize-space()][1]/ancestor::div[1]", $root);
        }

        if ($name == $address
            || preg_match("/^\s*{$this->opt($this->t('Check-in'))}/", $name)
            || preg_match("/{$this->opt($this->t('Check-in'))}.*{$this->opt($this->t('Check-out'))}/", $address)
        ) {
            $name = $address = null;
        }

        if ((empty($name) || strlen($name) > 70)
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Expedia itinerary:'))}]")) {
            $name = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Expedia itinerary:')]/preceding::text()[normalize-space()][1]");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("following::img[contains(@src, 'lob_hotels')][1]/following::text()[normalize-space()][1]", $root);
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("following::img[contains(@alt, 'hotel')][1]/following::text()[normalize-space()][1]", $root);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Check-in'))}][1]/preceding::text()[normalize-space()][1]/ancestor::a[1]", $root);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("following::text()[{$this->eq($name)}][1]/following::text()[normalize-space()][1]", $root);
        }

        $r->hotel()
            ->name($name)
            ->address($address);

        if (!empty($name)) {
            $phones = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Contact'))} and {$this->contains($name)}]/following::tr[normalize-space()][1][{$this->contains($this->t('Tel'))} or {$this->contains($this->t('Fax'))}]", $hRoot)
                ?? $this->http->FindSingleNode("descendant::text()[({$this->contains($this->t('Contact'))}) and {$this->contains($name)}]/following::text()[normalize-space()][1][{$this->contains($this->t('Tel'))} or {$this->contains($this->t('Fax'))}]", $hRoot)
            ;

            if (preg_match("#{$this->opt($this->t('Tel'))}[. ]*: *({$this->patterns['phone']})#", $phones, $m)) {
                $r->hotel()->phone($m[1]);
            }

            if (preg_match("#{$this->opt($this->t('Fax'))}[. ]*: *({$this->patterns['phone']})#", $phones, $m)) {
                $r->hotel()->fax($m[1]);
            }
        }

        // Booked

        // checkInDate
        $dateInText = $this->http->FindSingleNode("(following::tr[{$this->eq($this->t('Check-in'))}]/following-sibling::tr[normalize-space()][1])[1]", $root, true, "/^.*(?:\d.*|noon)$/")
            ?? $this->http->FindSingleNode("following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check-in'))}] ][1]/following::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][1]", $root, true, "/^.*(?:\d.*|noon)$/")
            ?? $this->http->FindSingleNode("following::*[ count(div[normalize-space()])=2 and div[normalize-space()][1][{$this->eq($this->t('Check-in'))}] ][1]/following::*[count(div[normalize-space()])=2][1]/div[normalize-space()][1]", $root, true, "/^.*(?:\d.*|noon)$/")
            ?? $this->http->FindSingleNode("following::*[ count(p[normalize-space()])=2 and p[normalize-space()][1][{$this->eq($this->t('Check-in'))}] ][1]/following::*[count(p[normalize-space()])=2][1]/p[normalize-space()][1]", $root, true, "/^.*(?:\d.*|noon)$/")
        ;

        $dateIn = $this->normalizeDate($dateInText);
        $timeIn = $this->http->FindSingleNode("(following::tr[{$this->eq($this->t('Check-in'))}]/following-sibling::tr[normalize-space()][2][not({$this->eq($this->t('Check-out'))})])[1]", $root, true, "/^.*(?:\d.*|noon)$/")
            ?? $this->http->FindSingleNode("following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Check-in'))}] ][1]/following::tr[count(*[normalize-space()])=2][2]/*[normalize-space()][1]", $root, true, "/^.*(?:\d.*|noon)$/")
            ?? $this->http->FindSingleNode("following::*[ count(div[normalize-space()])=2 and div[normalize-space()][1][{$this->eq($this->t('Check-in'))}] ][1]/following::*[count(div[normalize-space()])=2][2]/div[normalize-space()][1]", $root, true, "/^.*(?:\d.*|noon)$/")
            ?? $this->http->FindSingleNode("following::*[ count(p[normalize-space()])=2 and p[normalize-space()][1][{$this->eq($this->t('Check-in'))}] ][1]/following::*[count(p[normalize-space()])=2][2]/p[normalize-space()][1]", $root, true, "/^.*(?:\d.*|noon)$/")
        ;

        if (stripos($timeIn, 'point') !== false) {
            $timeIn = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Check-in time starts at'))}][1]", $root, true, "/{$this->opt($this->t('Check-in time starts at'))}\s*([\d\:]+\s*a?p?m?)$/iu");
        }

        if (!empty($dateIn) && !empty($timeIn)) {
            if (preg_match("/^{$this->opt($this->t('Minimum check-in age is'))}[:\s-]*\d+$/", $timeIn)) {
                $timeIn = '00:00';
            }
            $r->booked()->checkIn(strtotime($this->normalizeTime($timeIn), $dateIn));
        } elseif (!empty($dateIn)) {
            $r->booked()->checkIn($dateIn);
        }

        // checkOutDate
        $dateOutText = $this->http->FindSingleNode("(following::tr[{$this->eq($this->t('Check-out'))}]/following-sibling::tr[normalize-space()][1])[1]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
            ?? $this->http->FindSingleNode("following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('Check-out'))}] ][1]/following::tr[count(*[normalize-space()])=2][1]/*[normalize-space()][2]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
            ?? $this->http->FindSingleNode("following::*[ count(div[normalize-space()])=2 and div[normalize-space()][2][{$this->eq($this->t('Check-out'))}] ][1]/following::*[count(div[normalize-space()])=2][1]/div[normalize-space()][2]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
            ?? $this->http->FindSingleNode("following::*[ count(p[normalize-space()])=2 and p[normalize-space()][2][{$this->eq($this->t('Check-out'))}] ][1]/following::*[count(p[normalize-space()])=2][1]/p[normalize-space()][2]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
        ;
        $dateOutText = preg_replace('/(\d{1,2})[ ]+(\d{1,2})/', '$1$2', $dateOutText);
        $dateOut = $this->normalizeDate($dateOutText);
        $timeOut = $this->http->FindSingleNode("(following::tr[{$this->eq($this->t('Check-out'))}]/following-sibling::tr[normalize-space()][2][not({$this->eq($this->t('Room Details'))})])[1]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
            ?? $this->http->FindSingleNode("following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][2][{$this->eq($this->t('Check-out'))}] ][1]/following::tr[count(*[normalize-space()])=2][2]/*[normalize-space()][2]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
            ?? $this->http->FindSingleNode("following::*[ count(div[normalize-space()])=2 and div[normalize-space()][2][{$this->eq($this->t('Check-out'))}] ][1]/following::*[count(div[normalize-space()])=2][2]/div[normalize-space()][2]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
            ?? $this->http->FindSingleNode("following::*[ count(p[normalize-space()])=2 and p[normalize-space()][2][{$this->eq($this->t('Check-out'))}] ][1]/following::*[count(p[normalize-space()])=2][2]/p[normalize-space()][2]", $root, true, "/^.*(?:\d.*|noon|meio-dia|midi|正午)$/u")
        ;

        if ($dateOut && ($timeOut = $this->normalizeTime($timeOut)) && false !== strtotime($timeOut)) {
            $r->booked()->checkOut(strtotime($timeOut, $dateOut));
        } elseif ($dateOut) {
            $r->booked()->checkOut($dateOut);
        }

        if (!$r->getCheckInDate() || !$r->getCheckOutDate()) {
            // find rows with rate by day
            $rows = explode("\n", $this->re("#^(.+?)\nTaxes\n[^\n\d]*[\d\.,]+\n#s", implode("\n",
                $this->http->FindNodes("following::text()[starts-with(normalize-space(),'Room') and contains(.,'Price')][1]/ancestor::tr[following-sibling::tr][1]/following-sibling::tr[normalize-space()]/descendant::text()[normalize-space()]", $root))));
            // check format
            $rows = array_filter($rows, function ($k) {
                return !($k & 1);
            }, ARRAY_FILTER_USE_KEY);
            $rowsFiltered = array_filter($rows, function ($v) {
                return preg_match("#^\w+, \w+ \d+$#u", $v) > 0;
            });

            if ($rowsFiltered === $rows) {
                // check order days. if day by day => booked dates
                foreach ($rowsFiltered as $row) {
                    if (!isset($day)) {
                        $day = $this->normalizeDate($row);
                    } elseif (isset($day)) {
                        $day = strtotime("+ 1 day", $day);

                        if ($day !== $this->normalizeDate($row)) {
                            $day = null;

                            break;
                        }
                    }
                }

                if (isset($day)) {
                    $dtIn = $rowsFiltered[0]; // first day stay
                    $dtOut = end($rowsFiltered); // last day stay -> +1 day - departing

                    if (!$r->getCheckInDate()) {
                        $r->booked()->checkIn($this->normalizeDate($dtIn));
                    }

                    if (!$r->getCheckOutDate()) {
                        $r->booked()->checkOut(strtotime("+1 day", $this->normalizeDate($dtOut)));
                    }
                }
            }
        }

        $travelerDetails = $this->http->FindSingleNode("descendant::*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Traveler details'))}] ]/tr[normalize-space()][2]", $hRoot) // it-143020644.eml
            ?? $this->http->FindSingleNode("descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Traveler details'))}] ]/*[normalize-space()][2]", $hRoot)
            ?? $this->http->FindSingleNode("descendant::tr[{$this->starts($this->t('Thank you'))} and not(descendant::a)]/preceding::tr[normalize-space()][1]/following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Traveler details'))}] ][1]/*[normalize-space()][2]", $hRoot)
        ;

        $roomDetails = implode("\n", $this->http->FindNodes("descendant::table[ descendant::text()[normalize-space()][1][{$this->eq($this->t('Room Details'))}] ][1]/descendant::text()[normalize-space() and not({$this->eq($this->t('Room Details'))})]", $hRoot));

        if (empty($roomDetails)) {
            $roomDetails = implode("\n", $this->http->FindNodes("descendant::div[ {$this->eq($this->t('Room Details'))} and preceding-sibling::div[normalize-space()] ]/following-sibling::div[normalize-space()][position()<3]/descendant::text()[normalize-space()]", $hRoot));
        }

        // roomsCount
        if (preg_match("/{$this->opt($this->t('You booked'))}\s*(\d{1,3})\s*{$this->opt($this->t('room'))}/i", $roomDetails, $m)) {
            $r->booked()->rooms($m[1]);
        } elseif (in_array($this->lang, ['tr', 'ja']) && preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('room'))}\s*{$this->opt($this->t('You booked'))}/i", $roomDetails, $m)) {
            $r->booked()->rooms($m[1]);
        }

        // guestCount
        if (preg_match_all("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/i", implode("\n", $reservedFor), $adultMatches)) {
            $r->booked()->guests(array_sum($adultMatches[1]));
        } elseif (preg_match_all("/:\s+{$this->opt($this->t('adult'))}[[:alpha:]\(\)]{0,4},\s*(\d{1,3})\b/i", $roomDetails, $adultMatches)
            || preg_match_all("/[:：]\s*(\d{1,3}) \s*{$this->opt($this->t('adult'))}/i", $roomDetails, $adultMatches)
        ) {
            // Room 1 no. of guests: Adults, 2
            $r->booked()->guests(array_sum($adultMatches[1]));
        } elseif (preg_match("/^{$this->opt($this->t('adult'))}[[:alpha:]\(\)]{0,4}[,:：\s]+(\d{1,3})(?:\b|명|名)/iu", $travelerDetails, $m)) {
            // Adults, 2
            $r->booked()->guests($m[1]);
        } elseif (preg_match("/^\s*(\d{1,3})\s*{$this->opt($this->t('adult'))}[[:alpha:]\(\)]{0,4}/iu", $travelerDetails, $m)) {
            // Adults, 2
            $r->booked()->guests($m[1]);
        }

        // kidsCount
        if (preg_match_all("/\b(\d{1,3})\s*{$this->opt($this->t('children'))}/i", implode("\n", $reservedFor), $kidMatches)) {
            $r->booked()->kids(array_sum($kidMatches[1]));
        } elseif (preg_match("/(?:^|;\s*|\.\s*){$this->opt($this->t('children'))}[:,\s]+(\d{1,3})\b/i", $travelerDetails, $m)) {
            // Children, 2
            $r->booked()->kids($m[1]);
        } elseif (preg_match("/^\s*{$this->opt($this->t('adult'))}[[:alpha:]\(\)]{0,4}\s*[:：\s]\s*\d+\s*명?\s*,\s*{$this->opt($this->t('children'))}[[:alpha:]\(\)]{0,4}\s*[:：\s]\s*(\d{1,2})\b/iu", $travelerDetails, $m)) {
            // Voksne: 2, barn: 3
            $r->booked()->kids($m[1]);
        }
        $this->detectDeadLine($r);

        $xpathRoomsCount = "descendant::text()[{$this->eq($this->t('Room %'), 'translate(normalize-space(),"123456789","%%%%%%%%%")')}]";
        $xpathRoomsCount2 = "descendant::text()[{$this->eq($this->t('% room'), 'translate(normalize-space(),"123456789","%%%%%%%%%")')}]";
        $xpathSpecialRequests = "{$this->starts($this->t('Reserved for'))} or {$this->starts($this->t('Special requests'))}";

        // Rooms
        $roomType = $this->http->FindNodes("descendant::tr[{$this->eq($this->t('Room Details'))}]/following::tr[normalize-space()][position()<40][{$xpathRoomsCount}]/following-sibling::tr[normalize-space()][1][ following-sibling::tr[{$xpathSpecialRequests}] ]"
            . " | descendant::div[{$this->eq($this->t('Room Details'))}]/following::div[following-sibling::div[normalize-space()] and normalize-space()][position()<40][{$xpathRoomsCount}]/following-sibling::div[normalize-space()][1][ following-sibling::div[{$xpathSpecialRequests}] ]", $hRoot);

        if (count($roomType) === 0) {
            $roomType = $this->http->FindNodes("descendant::tr[{$this->eq($this->t('Room Details'))}]/following::tr[normalize-space()][position()<40][{$xpathRoomsCount2}]/following-sibling::tr[normalize-space()][1][ following-sibling::tr[{$xpathSpecialRequests}] ]"
                . " | descendant::div[{$this->eq($this->t('Room Details'))}]/following::div[following-sibling::div[normalize-space()] and normalize-space()][position()<40][{$xpathRoomsCount2}]/following-sibling::div[normalize-space()][1][ following-sibling::div[{$xpathSpecialRequests}] ]", $hRoot);
        }

        if (count($roomType) > 0 && $r->getRoomsCount() > 0 && $r->getRoomsCount() !== count($roomType)) {
            $roomType = array_unique($roomType);
        }

        if (count($roomType) === 1 && $r->getRoomsCount() > 1) {
            $roomType = array_fill(0, $r->getRoomsCount(), $roomType[0]);
        }

        if (count($roomType) === 0 && (empty($r->getRoomsCount()) || $r->getRoomsCount() == 1)) {
            $roomTypeText = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Room Details'))}][not(following::text()[{$this->starts($this->t('Reserved for'))}])]/following::td[normalize-space()][not(.//td)][position() < 3][ancestor::tr/following-sibling::tr[not(.//tr)][normalize-space()][1][" . $this->contains($this->t('adult')) . "]]", $hRoot);

            if (!empty($roomTypeText) && !preg_match("/^[[:alpha:]]+\s*\d+$/u", $roomTypeText)) {
                $roomType[] = $roomTypeText;
            }
        }
        $rxpath = "descendant::text()[{$this->eq($this->t('Room % Price'), "translate(normalize-space(),'123456789','%%%%%%%%%')")}]/ancestor-or-self::tr[position()<4][following-sibling::tr]/ancestor::*[1]/tr";
        $nodes = $this->http->XPath->query($rxpath, $hRoot);
        $i = -1;
        $tax = 0.0;
        $ratesStr = false;
        $rates = [];

        foreach ($nodes as $rroot) {
            if (preg_match('/^\s*(?:' . implode('|', (array) $this->t('roomPriceRegExp')) . ')/u', $rroot->nodeValue)) {
                $i++;
                $ratesStr = true;
                $rates[$i] = [];

                continue;
            } elseif (!empty($this->http->FindSingleNode("(.//td[{$this->contains($this->t('Taxes & fees'))}])[1]", $rroot))) {
                $ratesStr = false;

                continue;
            } elseif (!preg_match("#(\w+[., ]* \d{1,2}[., ]* \w+|\w+[., ]* \w+[., ]* \d{1,2}[., ]*\s*$|\d{1,2}/\d{1,2}\s*\(\w+\))#u", $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space()])[1]", $rroot))) {
                $ratesStr = false;

                continue;
            } elseif ($ratesStr === true) {
                $value = $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space()])[2]", $rroot);

                if (stripos($value, 'night') !== false) {
                    $rates[$i] = $value;
                } else {
                    $rates[$i][] = $this->getTotalCurrency($value)['Total'];
                    $currencyText = trim(preg_replace("#\(.+$#", '', str_replace($this->re("#^[^\d]*(\d[\d ,.]+)#", $value), '', $value)), '-');
                }
            }
        }

        $ruleRoomPrice = "({$this->starts($this->t('Room'))}) and ({$this->contains($this->t('Price'))}"
            . " or {$this->starts($this->t('Price'))}) and ({$this->contains($this->t('Room'))})";

        if (count($rates) && count($roomType) === 0
            || count($rates) && count($roomType) && count($rates) === count($roomType)
        ) {
            if (count($roomType) === 0) {
                $roomType[] = null;
            }

            foreach ($roomType as $key => $value) {
                $s = $r->addRoom();
                $s->setType($value, false, true);

                if (is_string($rates[$key])) {
                    $s->setRate($rates[$key]);
                } else {
                    $rate = array_unique(array_filter($rates[$key]));

                    if (count($rate) == 1) {
                        $s->setRate(array_shift($rates[$key]) . ' ' . $currencyText);
                    } else {
                        $s->setRate(min($rates[$key]) . ' - ' . max($rates[$key]) . ' ' . $currencyText);
                    }
                }
            }
        } elseif (!empty($roomType)) {
            foreach ($roomType as $key => $value) {
                $s = $r->addRoom();
                $s->setType($value);
            }
        } else {
            // it-19759953.eml
            $roomRate = $this->http->FindSingleNode("descendant::text()[{$ruleRoomPrice}][ following::text()[normalize-space()][1][contains(.,'night')] ]/ancestor::table[1]/following::text()[normalize-space()][2]", $hRoot);

            if (!empty($roomRate)) {
                $r->addRoom()->setRate($roomRate);
            }
        }

        // Program
        $specialRequests = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Special requests'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()][1]", $hRoot);

        if (preg_match("/{$this->opt($this->t('Marriott Rewards Number'))}\s*(\d{7,})\b/", $specialRequests, $m)) {
            $r->program()->account($m[1], false);
        }

        // Price
        $taxes = array_filter($this->http->FindNodes("descendant::text()[{$ruleRoomPrice}][ following::text()[normalize-space()][2][{$this->contains($this->t('Taxes & fees'))}] ]/ancestor::table[1]/following::text()[normalize-space()][3]", $hRoot));

        if (empty($taxes)) {
            $taxes = array_filter($this->http->FindNodes("descendant::text()[{$this->eq($this->t('Taxes & fees'))}]/following::text()[normalize-space()][1]", $hRoot));
        }

        if (count($taxes)) {
            $taxAmounts = [];
            $taxCurrencies = [];

            foreach ($taxes as $value) {
                $tax = $this->getTotalCurrency($value);

                if ($tax['Total'] !== null) {
                    $taxAmounts[] = $tax['Total'];
                    $taxCurrencies[] = $tax['Currency'];
                }
            }

            if (count($taxAmounts) && count(array_unique($taxCurrencies)) === 1) {
                $r->price()
                    ->tax(array_sum($taxAmounts));
                $cur = array_shift($taxCurrencies);

                if (!empty($cur)) {
                    $r->price()
                        ->currency($cur);
                }
            }
        } else {
            $taxes = $this->http->FindSingleNode("descendant::text()[{$ruleRoomPrice}][ following::text()[normalize-space()][2][{$this->contains($this->t('Taxes & fees'))}] ]/ancestor::table[1]/following::text()[normalize-space()][3]", $hRoot);

            if ($taxes !== null) {
                $tax = $this->getTotalCurrency($taxes);

                if ($tax['Total'] !== null) {
                    $r->price()
                        ->tax($tax['Total'])
                        ->currency($tax['Currency']);
                }
            }
        }
        $roomPrice = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Room Price'))}]/ancestor::td[ following-sibling::td[normalize-space()] ][1]/following-sibling::td[normalize-space()][1]/descendant::text()[normalize-space()][1]", $hRoot);

        if ($roomPrice !== null) {
            $cost = $this->getTotalCurrency($roomPrice);
            $r->price()
                ->cost($cost['Total']);

            if (!empty($cost['Currency'])) {
                $r->price()
                    ->currency($cost['Currency']);
            }
        }

        $feeRows = $this->http->XPath->query("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts('Locally collected mandatory fees/taxes')}] ]", $hRoot);

        foreach ($feeRows as $feeRow) {
            $feeCharge = $this->http->FindSingleNode('*[normalize-space()][2]', $feeRow);
            $fee = $this->getTotalCurrency($feeCharge);

            if ($fee['Total'] !== null
                && (empty($r->getPrice()) || $r->getPrice()->getCurrencyCode() === $fee['Currency'])
            ) {
                $feeName = $this->http->FindSingleNode('*[normalize-space()][1]', $feeRow, true, '/^(.+?)\s*[*]*$/');
                $r->price()->fee($feeName, $fee['Total']);
            }
        }

        $total = $this->http->FindSingleNode("(descendant::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2])[1]", $hRoot, true, "/^(.*\d.*?)(?:\s*\(|$)/");

        if ($total !== null) {
            $tot = $this->getTotalCurrency($total);

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }

        $node = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Expedia Rewards points used'))}][1]", $hRoot, false, "/\b(\d[\d., ]+)\s+\w+/u");

        if (empty($node) && $this->lang === 'zh') {
            $node = $this->http->FindSingleNode("descendant::text()[{$this->contains('使用了')} and {$this->contains('个 Expedia Rewards 积分')}][1]", $hRoot, false, "/使用了 (\d[\d., ]+) 个 Expedia Rewards 积分/iu");
        }

        if (!empty(trim($node))) {
            $r->price()->spentAwards(trim($node) . ' points');
        }

        //For Hotels
        if (empty($node)) {
            $node = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Expedia Rewards points used'))}][1]/ancestor::tr[1][count(*[normalize-space()]) = 2]/descendant::td[normalize-space()][2]", $hRoot, true, "/^\s*\-(\D[\d\.\,]+)$/");

            if (empty($node)) {
                $node = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Expedia Rewards points used'))}][1]/following::text()[normalize-space()][1]", $hRoot, true, "/^\s*\-(\D[\d\.\,]+)$/");
            }

            if (!empty(trim($node))) {
                $r->price()->spentAwards(trim($node));
            }
        }
    }

    private function normalizeDate(&$date, bool $returnTimestamp = true)
    {
        //$this->logger->debug('$date = ' . print_r($date, true));

        if (empty($date)) {
            return null;
        }
        $year = date('Y', $this->date);
        $in = [
            //quinta, 11/04
            '#^(?<week>\w+)\,\s*(?<day>\d+)\/(?<month>\d+)$#u', //pt
            //29 abr 2024, 16:00
            '#^(\w+\s*\w+\s*\d{4}\,\s*[\d\:]+)$#u',
            // 10/23 (金)
            '#^(\d+)/(\d+)\s*\((.+?)\)$#u',
            // Wednesday, 11 September 2019    |    Sonntag, 22. Dezember 2019
            '/^[-[:alpha:]]{2,}\.?,\s*(\d{1,2})\.?\s+([[:alpha:]]{3,})\.?\s+(\d{4})$/u',
            // Sep 07, 2018
            '#^(\w+)\s+(\d+),\s+(\d{4})$#u',
            // 07 Sep 2019    |    27. Dezember 2019    |    7 de octubre de 2019
            '/^(\d{1,2})\.?(?:\s+de)?\s+([[:alpha:]]{3,})\.?(?:\s+de)?\s+(\d{4})$/u',
            // September 17
            '#^([[:alpha:]]{3,})\s+(\d{1,2})$#u',

            // Fri., Dec 27    |    Tue., Aug. 17    |    Mon, Sep2
            '/^([-[:alpha:]]+)\.?,\s*([[:alpha:]]{3,})[.\s]*(\d{1,2})$/u',
            // Fri., 27 Dec    |    So., 5. Jan.    |    dom., 13 de oct.    |    wo 18 mrt. |  lør. d. 16. mar.
            '/^([-[:alpha:]]+)\.?,?(?: d\.)?\s*(\d{1,2})\.?(?:\s+de)?\s+([[:alpha:]]{3,})\.?$/u',
            // gio 12 mar
            '#^([[:alpha:]]+)\s+(\d+)\s+([[:alpha:]]+)$#u', // it
            //mié. 11 de mar.
            '#^(\w+)\.\s*(\d{1,2})\s*\w+\s*(\w+)\.$#u', //es
            //4月29日(星期四); 9 月 22 日星期四; 11월 19일(토)
            '#^\s*(\d+)\s*[月월]\s*(\d+)\s*[日일]\s*[\(（]?([[:alpha:]]+?)[\)）]?\s*$#u', // zh
            // 16 Haziran Paz
            '#^\s*(\d{1,2})\s*([[:alpha:]]+)\s+([[:alpha:]]+)\s*$#u', // tr
        ];
        $out = [
            '$2.$3.' . $year,
            "$1",
            "{$year}-$1-$2",
            '$1 $2 $3',
            '$2 $1 $3',
            '$1 $2 $3',
            '$2 $1',

            '$3 $2 ' . $year,
            '$2 $3 ' . $year,
            '$2 $3 ' . $year,
            '$2 $3 ' . $year,
            $year . '-$1-$2',
            '$1 $2 ' . $year,
        ];
        $outWeek = [
            '$1',
            '',
            '$3',
            '',
            '',
            '',
            '',

            '$1',
            '$1',
            '$1',
            '$1',
            '$3',
            '$3',
        ];
        $date = str_replace(['Ã¡', 'Ã³'], 'á', $date);
        $week = preg_replace($in, $outWeek, $date);
        // $this->logger->debug('$week = ' . print_r($week, true));

        $result = null;

        if ($this->date && !empty($week)) {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $result = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } elseif ($this->date) {
            $dateEn = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $result = $returnTimestamp ? strtotime($dateEn) : $dateEn;
        }

        return $result;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @return string
     */
    private function dateStringToEnglish(?string $date): ?string
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeTime(?string $str)
    {
        // $this->logger->debug('time = ' . print_r($str, true));

        if (!$str) {
            return null;
        }

        $in = [
            // Check-in time starts at 2:00 PM    |    Check-in ab 14:00 Uhr
            '/^\D*\b(\d{1,2}:+\d{2}(?:\s*[ap]m)?)\D*$/i',
            // Check-in time starts at 2 PM    |    Check-in time starts at 2
            '/^\D*\b(\d{1,2})(\s*[ap]m)?\D*$/i',
            //Inchecken vanaf 15.00 uur    |    Horário inicial do check-in: 14h00
            '/^\D*\b(\d{1,2})[.h](\d{2}(?:\s*[ap]m)?)\D*$/i',
            //Check-in time starts at noon  |  noon
            '#^\s*(?:.* at )?noon\s*$#i',
            // 10 AM
            '#^\s*(\d{1,2})\s*([ap]m)$#i',
            //Arrivées à partir de 15 h 00
            '#^.+ (?:de|à)\s+(\d+)\sh\s(\d+)$#u',
            //midi
            '#^\D*\b(midi|meio-dia|正午|정오|öğlen)$#u',
            '#Check-in time (?:ends at anytime|starts at midnight)#',
            //11 h 00
            '#^(\d+)[\s\D]+(\d+)#',
        ];
        $out = [
            '$1',
            '$1:00$2',
            '$1:$2',
            '12:00',
            '$1:00 $2',
            '$1:$2',
            '12:00',
            '00:00',
            '$1:$2',
        ];
        $str = preg_replace($in, $out, $str);

        return $str;
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        $cancellationText = $h->getCancellation();

        if (empty($cancellationText)) {
            return;
        }

        if (preg_match("/Cancelamentos ou alterações feitos após\s*(?<hour>\d+)h(?<min>\d+)\s*\(horário local da propriedade\)\,\s*em\s*(?<day>\d+)\s*(?<month>\w+)\,\s*(?<year>\d{4})/u", $cancellationText, $m)
         || preg_match("/Os cancelamentos ou alterações realizados (?:após as|antes das)\s*(?<hour>\d+)\:(?<min>\d+)\s*\(hora local do alojamento\)\s*do\s*dia\s*(?<day>\d+)\s*de\s*(?<month>\w+)\s*de\s*(?<year>\d{4})/us", $cancellationText, $m)) {
            $date = $m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['hour'] . ':' . $m['min'];
            // $this->logger->error($date);
            $h->booked()->deadline($this->normalizeDate($date));
        }

        // Free cancellation until October 15 at 2:00 PM (Romance Standard Time)
        if (preg_match("/Free (?i)cancell?ation until (?<date>.{3,}?) at (?<time>{$this->patterns['time']})(?: \(|$)/", $cancellationText, $m) // en
            || preg_match("/Cancelación gratuita hasta el (?<date>.{3,}?) a las (?<time>{$this->patterns['time']})(?: \(|$)/i", $cancellationText, $m) // es
            || preg_match("/Kostenlose Stornierung bis (?<date>.{3,}?) um (?<time>{$this->patterns['time']})(?: \(|$)/i", $cancellationText, $m) // de
            || preg_match("/Gratis annulering tot (?<date>.{3,}?) om (?<time>{$this->patterns['time']})(?: \(|$)/i", $cancellationText, $m) // nl
            || preg_match("/Annulation gratuite jusqu’au (?<date>.{3,}?) à (?<time>{$this->patterns['time']}|\d{1,2} h \d{2})(?: \(|$)/i", $cancellationText, $m) // fr
            || preg_match("/Aucuns frais d’annulation ne sont facturés avant (?<time>{$this->patterns['time']}) \(heure locale de l’hébergement\) le (?<date>\w+\W?\s*\b\w+\W?\s*\b\w+\W?)\./i", $cancellationText, $m) // fr
            || preg_match("/Cancellazione gratuita entro la data (?<date>.{3,}?) alle ore (?<time>{$this->patterns['time']})(?: \(|$)/i", $cancellationText, $m) // it
            || preg_match("/Cancelación gratuita hasta el (?<date>.{3,}?), (?<time>{$this->patterns['time']})(?: \(|$)/i", $cancellationText, $m) // it
            || preg_match("/modifications effectuées après (?<time>{$this->patterns['time']}) \(Heure de l'Atlantique\) le (?<date>.{3,}?) ou les/i", $cancellationText, $m) // it
        ) {
            $dlDate = $this->normalizeDate($m['date'], false);

            $m['time'] = preg_replace("/^(\d{1,2}) h (\d{2})$/", '$1:$2', $m['time']);

            if ($dlDate && is_string($dlDate) && !preg_match('/\b\d{4}\s*$/', $dlDate) && !empty($h->getCheckInDate())) {
                $dlDate = EmailDateHelper::parseDateRelative($dlDate, $h->getCheckInDate(), false);
                $h->booked()->deadline(strtotime($m['time'], $dlDate));
            } elseif ($dlDate && is_string($dlDate)) {
                $h->booked()->deadline2($dlDate . ' ' . $m['time']);
            }
        } elseif (preg_match('/(\d{4})[^\d]+(\d{1,2})[^\d]+(\d{1,2})\w+ (\d{1,2}\:\d{2})/u', $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m[1] . '-' . $m[2] . '-' . $m[3] . ', ' . $m[4]));
        }

        if (
               preg_match("#The room/unit type and rate selected are non-refundable\.#", $cancellationText)// en
            || preg_match("#Le type de chambre/hébergement et le tarif sélectionnés ne sont pas remboursables\.#", $cancellationText)// fr
            || preg_match("#Los detalles y la tarifa de la habitación o unidad seleccionados no son reembolsables\.#", $cancellationText)// es
            || preg_match("#El tipo de habitación o unidad y la tarifa seleccionados no son reembolsables\.#", $cancellationText)// es
            || preg_match("#O tipo de quarto/unidade e tarifa selecionados não são reembolsáveis\.#", $cancellationText)// pt
            || preg_match("#La tipologia di camera/unità e la tariffa selezionate non sono rimborsabili\.#", $cancellationText)// it
            || preg_match("#Den valgte typen rom/enhet og prisen kan ikke refunderes\.#", $cancellationText)// no
            || preg_match("#Het geselecteerde kamer/unittype en -tarief zijn niet restitueerbaar\.#", $cancellationText)// no
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node): array
    {
        $sym = [
            'SG$'  => 'SGD',
            'JP¥'  => 'JPY',
            '€'    => 'EUR',
            'NZ$'  => 'NZD',
            'R$'   => 'BRL',
            'CN¥'  => 'CNY',
            'AU$'  => 'AUD',
            'A$'   => 'AUD',
            'CA$'  => 'CAD',
            'C$'   => 'CAD',
            '$C'   => 'CAD',
            'US$'  => 'USD',
            '₫'    => 'VND',
            'MX$'  => 'MXN',
            '₹'    => 'INR',
            '円'    => 'JPY', // 28,624 円
            '₪'    => 'ILS',
            'ג‚×'  => 'ILS',
            'MXN$' => 'MXN',
            'CA $' => 'CAD',
            '$ CA' => 'CAD',
            '£'    => 'GBP',
            '₩'    => 'KRW',
            'NT$'  => 'TWD',
        ];

        foreach ($sym as $key => $value) {
            $node = preg_replace("#(?<=^|\s|\d)" . preg_quote($key) . "(?=\d|\s|$)#", $value, $node);
        }
        $tot = null;
        $cur = null;

        if (preg_match('/^(?<t>\d[,.\'\d\s]*)\s*(?<c>[A-Z]{3}\b|[^\d)(]+)/', $node, $m) // 135,00 £
            || preg_match('/^(?<c>\b[A-Z]{3}|[^\d)(]+)\s*(?<t>\d[,.\'\d\s]*\d*)\b/', $node, $m)
            || preg_match('/(?<c>[-]*?)(?<t>\d[,.\'\d\s]*\d*)\b/', $node, $m)
        ) {
            $cur = trim($m['c']);

            if (preg_match("/^[A-Z]{3}$/", $cur)) {
                // 1,589.620
                $m['t'] = PriceHelper::parse($m['t'], $cur);
            } else {
                $m['t'] = preg_replace('/\s+/', '', $m['t']);               // 11 507.00  ->  11507.00
                $m['t'] = preg_replace('/[,.\'](\d{3})/', '$1', $m['t']);   // 2,790      ->  2790    or    4.100,00  ->  4100,00
                $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);         // 18800,     ->  18800
                $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00   ->  18800.00
            }
            $tot = (float) $m['t'];
            $cur = trim($m['c']);

            if (!empty($this->currencyCode) && in_array($cur, ['kr', 'kr.', '$'])) {
                $cur = $this->currencyCode;
            } else {
                if ($cur == 'kr' && $this->http->FindSingleNode('//text()[contains(., "Priser oppgitt i NOK, er basert")]')) {
                    $cur = 'NOK';
                    $this->currencyCode = 'NOK';
                } elseif ($cur == 'kr.' && $this->http->FindSingleNode('//text()[contains(., "Priser angivet i DKK er baseret")]')) {
                    $cur = 'DKK';
                    $this->currencyCode = 'DKK';
                } elseif ($cur == 'kr' && $this->http->FindSingleNode('//text()[contains(., "Det angivna priset i SEK baseras")]')) {
                    $cur = 'SEK';
                    $this->currencyCode = 'SEK';
                } elseif ($cur == '$' && $this->http->FindSingleNode('//text()[contains(., "强制性税费基于当前汇率，以 USD 报出")]')) {
                    $cur = 'USD';
                    $this->currencyCode = 'USD';
                } elseif ($cur == '$' && $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Total'))}]/following::text()[normalize-space()][3][{$this->contains(['US dollars', '\bUSD\b'])}]")
                ) {
                    $cur = 'USD';
                    $this->currencyCode = 'USD';
                }
            }
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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
}
