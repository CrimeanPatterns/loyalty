<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ReservationConfirmation2 extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-107224110.eml, airbnb/it-205383801.eml, airbnb/it-701957328.eml, airbnb/it-71081218.eml, airbnb/it-71081232.eml, airbnb/it-83926205.eml, airbnb/it-86379461.eml, airbnb/it-92806022.eml";
    public $subjects = [
        // en
        'Reservation reminder - ',
        'Reservation confirmed for ',
        // es
        'Reserva confirmada para ', // + pt
        'Recordatorio de reservación:',
        'Recordatorio de reserva:',
        'Reservación confirmada para',
        // uk
        '/\w+: бронювання підтверджено$/u',
        // pt
        'Lembrete de reserva - ',
        // el
        'Υπενθύμιση κράτησης - ',
        // nl
        'Reserveringsherinnering - ',
        'Reservering bevestigd voor',
        // de
        '/Buchung in .+ bestätigt/',
        // fr
        'Réservation confirmée pour ',
        // da
        'Reservationspåmindelse - ',
        '/Reservationen for .+ bekræftet/',
        // it
        ': prenotazione confermata',
        // ko
        '예약이 확정되었습니다',
        // tr
        'Rezervasyon anımsatıcısı - ',
        'için rezervasyon onaylandı',
        // no
        'Reservasjonspåminnelse - ',
        // ro
        'Memento despre rezervare - ',
        // sk
        'Pripomenutie rezervácie – ',
        // cs
        'Připomenutí rezervace - ',
        // pl
        'Przypomnienie o rezerwacji - ',
        // zh
        '之旅的预订已确认',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'statusVariants'             => ['confirmed'],
            'Reservation code'           => ['Reservation code', 'reservation code'],
            'Cancellation policy'        => ['Cancellation policy', 'Refund Policy'],
            'Check-in is any time after' => ['Check-in is any time after', 'Check-in is', 'Check-in is anytime after', 'Check-in time:', 'Check-in by'],
            //            'flexible'                   => '',
            //            'Check-in' => '', // Check-in and Checkout in two column
            //            'Checkout' => '',
            'Show full itinerary'          => ['Show full itinerary', 'View full itinerary'], // after dates
            'Show all reservation details' => ['Show all reservation details'], // after main info, before price
            'from Airbnb'                  => ['from Airbnb', 'Contact Airbnb'],
            'Address'                      => ['Address', 'address'],
            //'badAddress' => [''],
            'send you the exact address'   => ['send you the exact address', 'we’ll share the address'],
            'Guests'                       => ['Guests', 'personnel'],
            'child'                        => ['infant', 'child'],
            'Amount paid'                  => ['Amount paid', 'Amount', 'Payment amount'],
        ],
        "it" => [
            //detects
            'from Airbnb'                  => 'da Airbnb',
            'Your reservation is'          => 'La tua prenotazione è',
            'Show full itinerary'          => ['Ricevi istruzioni', 'Vedi l\'itinerario completo'], // after dates
            'Show all reservation details' => ['Mostra tutti i dettagli della prenotazione'], // after main info, before price
            //            'Change reservation' => '',

            'statusVariants'             => ['confermata'],
            'Reservation code'           => 'Codice di prenotazione',
            'Cancellation policy'        => 'Termini di cancellazione',
            'hosted by'                  => ['Intera casa / apt da', 'Intera casa/apt da', 'Stanza privata da ', ', host:'],
            'Address'                    => 'Indirizzo',
            //'badAddress' => [''],
            'Check-in is any time after' => ['Il check-in si effettua dalle', 'Check-in a qualsiasi ora dopo le'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Check-in', // Check-in and Checkout in two column
            'Checkout'                   => 'Partenza',
            'Checkout by'                => 'Check-out entro le ore',
            'Guests'                     => 'Ospiti',
            'adult'                      => 'adult',
            'child'                      => 'bambin',
            'Amount paid'                => ['Importo pagato', 'Totale'],
            'Balance due'                => 'Saldo dovuto',
        ],
        "de" => [
            //detects
            'from Airbnb'                  => 'von Airbnb',
            'Your reservation is'          => 'Deine Buchung wurde',
            'Show full itinerary'          => ['Vollständigen Reiseplan ansehen', 'Vollständigen Reiseplan anzeigen'], // after dates
            'Show all reservation details' => ['Alle Buchungsdetails anzeigen'], // after main info, before price
            //            'Change reservation' => '',

            'statusVariants'             => ['bestätigt'],
            'Reservation code'           => 'Buchungscode',
            'Cancellation policy'        => 'Stornierungsbedingungen',
            'hosted by'                  => ['vermietet von', 'Gastgeber:in ist'],
            'Address'                    => 'Adresse',
            //'badAddress' => [''],
            'Check-in is any time after' => ['D‌e‌r‌ ‌C‌h‌e‌c‌k‌-‌i‌n‌ ‌i‌s‌t‌ ‌j‌e‌d‌e‌r‌z‌e‌i‌t‌ ‌n‌a‌c‌h‌', 'Check-in von', 'Der Check-in ist jederzeit nach', 'Check-in-Zeit ist'],
            'flexible'                   => 'flexibel',
            'Check-in'                   => 'Check-in', // Check-in and Checkout in two column
            'Checkout'                   => 'Check-out',
            'Checkout by'                => ['C‌h‌e‌c‌k‌-‌o‌u‌t‌ ‌b‌i‌s‌', 'Check-out bis'], // not the same
            'Guests'                     => 'Gäste',
            'adult'                      => 'Erwachsene',
            'child'                      => 'Kleinkind',
            'Amount paid'                => ['Bezahlung', 'Betrag', 'Gezahlter Betrag'],
            'Balance due'                => ['Restbetrag fällig'],
        ],
        "es" => [ // it-107224110.eml
            // detects
            'from Airbnb'                  => ['desde Airbnb', ' de Airbnb'],
            'Your reservation is'          => 'La reserva se ha',
            'Show full itinerary'          => ['Ver itinerario completo', 'Ver el itinerario completo', 'Mostrar itinerario completo'], // after dates
            'Show all reservation details' => ['Mostrar los datos de la reserva', 'Mostrar toda la información de la reservación'], // after main info, before price
            'Change reservation'           => 'Modificar la reserva',

            'statusVariants'               => ['confirmado'],
            'Reservation code'             => ['Código de reserva', 'Código de la reserva'],
            'Cancellation policy'          => 'Política de cancelación',
            'hosted by'                    => ['Anfitrión:', 'cuyo anfitrión es', ', anfitrión:', '(anfitrión:'],
            'Address'                      => 'Dirección',
            //'badAddress' => [''],
            'send you the exact address'   => ['Te enviaremos la dirección exacta dentro de'],
            'Check-in is any time after'   => ['Horario de llegada: de', 'La hora de llegada es a partir de las', 'La hora de llegada es', 'La llegada es',
                'El horario de llegada es de las', ],
            'flexible'                   => 'flexible',
            'Check-in'                   => 'Llegada', // Check-in and Checkout in two column
            'Checkout'                   => 'Salida',
            'Checkout by'                => ['S‌a‌l‌i‌d‌a‌ ‌a‌n‌t‌e‌s‌ ‌d‌e‌ ‌l‌a‌s‌', 'Salida a las', 'Salida antes de las'],
            'Guests'                     => ['Huéspedes', 'Viajeros'],
            'adult'                      => 'adulto',
            'child'                      => ['niño', 'bebé'],
            'Amount paid'                => ['Importe pagado', 'Monto', 'Importe'],
            'Balance due'                => ['Saldo pendiente'],
        ],
        "uk" => [ // it-92806022.eml
            // detects
            'from Airbnb'                  => 'від Airbnb',
            'Your reservation is'          => 'Ваше бронювання',
            'Show full itinerary'          => 'Переглянути весь план подорожі', // after dates
            'Show all reservation details' => ['Показати всі подробиці бронювання'], // after main info, before price
            // 'Change reservation' => '',

            'statusVariants'             => ['підтверджено'],
            'Reservation code'           => 'Код бронювання',
            'Cancellation policy'        => 'Правила скасування бронювання',
            'hosted by'                  => 'господар',
            'Address'                    => 'Адреса',
            //'badAddress' => [''],
            'Check-in is any time after' => ['Приїзд в будь-який час після', 'Час прибуття'],
            'flexible'                   => 'гнучкий',
            'Check-in'                   => 'Прибуття', // Check-in and Checkout in two column
            'Checkout'                   => 'Виїзд',
            'Checkout by'                => 'Виїзд до',
            'Guests'                     => 'Гості',
            'adult'                      => 'доросл',
            //             'child' => '',
            'Amount paid'                => ['Сплачена сума', 'Сума'],
        ],
        "pt" => [
            // detects
            'from Airbnb'                  => 'pelo Airbnb',
            'Your reservation is'          => 'Sua reserva está',
            'Show full itinerary'          => 'Visualizar itinerário completo', // after dates
            'Show all reservation details' => ['Mostrar todas as informações da reserva', 'Mostrar todos os detalhes da reserva'], // after main info, before price
            'Change reservation'           => 'Alterar reserva',

            'statusVariants'             => ['confirmada'],
            'Reservation code'           => ['Código de reserva', 'Código da reserva'],
            'Cancellation policy'        => 'Política de cancelamento',
            'hosted by'                  => ['hospedado por', 'hospedada por '],
            'Address'                    => 'Endereço',
            //'badAddress' => [''],
            'send you the exact address' => ['nós compartilharemos o endereço em seu ', 'Iremos enviar-lhe o endereço exato daqui a'],
            'Check-in is any time after' => ['O check-in é a qualquer momento depois das', 'O check-in é entre', 'O horário de check-in é',
                'O check-in é a qualquer hora depois das', ],
            'flexible'                   => 'flexível',
            'Check-in'                   => 'Check-in', // Check-in and Checkout in two column
            'Checkout'                   => ['Checkout', 'Check-out'],
            'Checkout by'                => ['Checkout até', 'Check-out até às'],
            'Guests'                     => 'Hóspedes',
            'adult'                      => 'adult',
            'child'                      => ['crianças', 'criança', 'bebê'],
            'Amount paid'                => ['Valor pago', 'Valor'],
        ],
        "el" => [
            // detects
            'from Airbnb'         => 'Κατεβάστε την εφαρμογή της Airbnb',
            //            'Your reservation is' => '',
            'Show full itinerary'          => 'Δείτε το πλήρες δρομολόγιο', // after dates
            'Show all reservation details' => ['Δείτε το πλήρες δρομολόγιο'], // after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Κωδικός κράτησης',
            //            'Cancellation policy'        => 'Política de cancelamento',
            'hosted by'                  => 'με οικοδεσπότη τον/την',
            'Address'                    => 'Διεύθυνση',
            //'badAddress' => [''],
            'Check-in is any time after' => ['Η άφιξη πραγματοποιείται μεταξύ', 'Η άφιξη πραγματοποιείται μετά τις'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Άφιξη', // Check-in and Checkout in two column
            'Checkout'                   => 'Αναχώρηση',
            'Checkout by'                => 'Αναχώρηση έως τις',
            'Guests'                     => ['Επισκέπτες', 'ενήλικας'],
            'adult'                      => 'ενήλικες',
            'child'                      => 'παιδί',
            'Amount paid'                => ['Ποσό'],
        ],
        "nl" => [
            // detects
            'from Airbnb'         => 'Download de Airbnb-app',
            //            'Your reservation is' => '',
            'Show full itinerary'          => 'Bekijk volledig reisschema', // after dates
            'Show all reservation details' => ['Volledig reisschema'], // after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => ['Reserveringscode', 'Boekingscode'],
            //            'Cancellation policy'        => 'Política de cancelamento',
            'hosted by'                  => 'verhuurd door',
            'Address'                    => 'Adres',
            //'badAddress' => [''],
            'Check-in is any time after' => ['Inchecken tussen', 'Inchecken kan op elk moment na'],
            //            'flexible'                   => '',
            'Check-in'                   => ['Inchecken', 'Aankomst'], // Check-in and Checkout in two column
            'Checkout'                   => 'Vertrek',
            'Checkout by'                => 'Uitchecken voor',
            'Guests'                     => 'Gasten',
            'adult'                      => 'volwassene',
            'child'                      => 'huisdier',
            'Amount paid'                => ['Bedrag'],
        ],
        "fr" => [
            // detects
            'from Airbnb'                  => ['Obtenir l\'application Airbnb', 'Obtenir l\'appli Airbnb'],
            'Your reservation is'          => 'Votre réservation est',
            'Show full itinerary'          => ['Voir le récapitulatif complet', 'Voir l’itinéraire au complet', 'Afficher tous les détails de la réservation'], // after dates
            'Show all reservation details' => ['Afficher tous les détails de la réservation'], // after main info, before price
            // 'Change reservation' => '',
            'statusVariants'             => ['confirmée'],
            'Reservation code'           => 'Code de réservation',
            'Cancellation policy'        => 'Conditions d\'annulation',
            'hosted by'                  => ['- Hôte :', ' hébergé par ', ', hôte :', '. Hôte'],
            'Address'                    => 'Adresse',
            'badAddress'                 => ['Nous vous enverrons l\'adresse exacte dans', 'On vous envoie l\'adresse exacte'],
            'send you the exact address' => 'nous vous communiquerons l\'adresse dans',
            'Check-in is any time after' => ['L\'arrivée est', 'L\'arrivée se fait entre', 'L\'entrée dans les lieux se fait à partir de', 'Arrivée à partir de', 'L\'arrivée a lieu entre', 'Heure d\'arrivée'],
            'flexible'                   => 'flexible',
            'Check-in'                   => 'Arrivée', // Check-in and Checkout in two column
            'Checkout'                   => 'Départ',
            'Checkout by'                => ['Départ avant :', 'Départ avant'],
            'Guests'                     => 'Voyageurs',
            'adult'                      => 'adult',
            'child'                      => ['enfant', 'bébé'],
            'Amount paid'                => ['Montant payé', 'Montant'],
            'Balance due'                => ['Solde dû'],
        ],
        "da" => [
            // detects
            'from Airbnb'         => 'Hent Airbnb-appen',
            //            'Your reservation is' => '',
            'Show full itinerary'          => 'Se komplet rejseplan', // after dates
            'Show all reservation details' => ['Se komplet rejseplan', 'Vis alle reservationsoplysninger'], // after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Reservationskode',
            'Cancellation policy'        => 'Annulleringspolitik',
            'hosted by'                  => [' med ', ', vært: '],
            'Address'                    => 'Adresse',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['‌I‌n‌d‌t‌j‌e‌k‌n‌i‌n‌g‌ ‌k‌a‌n‌ ‌s‌k‌e‌ ‌n‌å‌r‌ ‌s‌o‌m‌ ‌h‌e‌l‌s‌t‌ ‌e‌f‌t‌e‌r‌', 'Indtjekning kan ske når som helst efter', 'Indtjekning fra'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Indtjekning', // Check-in and Checkout in two column
            'Checkout'                   => 'Udtjekning',
            'Checkout by'                => ['‌U‌d‌t‌j‌e‌k‌n‌i‌n‌g‌ ‌s‌e‌n‌e‌s‌t‌', 'Udtjekning senest'],
            'Guests'                     => 'Gæster',
            'adult'                      => ['voksne', 'voksen'],
            'child'                      => ['barn', 'baby'],
            'Amount paid'                => ['Beløb', 'Betalt beløb', 'I alt'],
            'Balance due'                => ['Forfalden saldo'],
        ],
        "ko" => [
            // detects
            'from Airbnb'         => '에어비앤비 앱 설치',
            //            'Your reservation is' => '',
            'Show full itinerary'          => '전체 여행 일정표 보기', // after dates
            'Show all reservation details' => ['예약 세부정보 모두 표시하기'], // after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => '예약 코드',
            'Cancellation policy'        => '환불 정책',
            'hosted by'                  => '님이 호스팅하는 ',
            'Address'                    => '주소',
            //            'send you the exact address' => '',
            //'badAddress' => [''],
            'Check-in is any time after' => ['체크인 시간:', '체크인 가능 시간:'],
            //            'flexible'                   => '',
            'Check-in'                   => '체크인', // Check-in and Checkout in two column
            'Checkout'                   => '체크아웃',
            'Checkout by'                => ['체크아웃 마감 시간:'],
            'Guests'                     => '인원',
            'adult'                      => '성인',
            'child'                      => '유아',
            'Amount paid'                => ['지급 금액', '금액', '결제 금액'],
            'Balance due'                => ['잔액'],
        ],
        "tr" => [
            // detects
            //            'from Airbnb'         => '',
            //            'Your reservation is' => '',
            'Show full itinerary'          => 'Tüm seyahat planını görüntüle', // after dates
            'Show all reservation details' => ['Tüm rezervasyon bilgilerini göster'], // after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Rezervasyon kodu',
            'Cancellation policy'        => 'İptal politikası',
            'hosted by'                  => [' adlı kişinin yaptığı ', '· Ev sahibi:'],
            'Address'                    => 'Adres',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['Giriş zamanı:', 'Giriş saati, şu saatten sonra herhangi bir zamandadır:'],
            'flexible'                   => 'esnektir',
            'Check-in'                   => 'Giriş', // Check-in and Checkout in two column
            'Checkout'                   => 'Çıkış',
            'Checkout by'                => ['Çıkış saati:'],
            'Guests'                     => 'Misafirler',
            'adult'                      => 'yetişkin',
            'child'                      => 'çocuk',
            'Amount paid'                => ['Miktar', 'Ödenen tutar'],
            //            'Balance due'                => [''],
        ],
        "no" => [
            // detects
            //            'from Airbnb'         => '',
            //            'Your reservation is' => '',
            'Show full itinerary' => 'Se hele reiseruten', // after dates
            // 'Show all reservation details' => [''],// after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Reservasjonskode',
            //            'Cancellation policy'        => '',
            'hosted by'                  => [' som vert', ' er vertskap'], // Helt hjem/leilighet har Thea som vert
            'Address'                    => 'Adresse',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['Innsjekkingstid er når som helst etter'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Innsjekking', // Check-in and Checkout in two column
            'Checkout'                   => 'Utsjekking',
            'Checkout by'                => ['Utsjekking innen '],
            'Guests'                     => 'Gjester',
            'adult'                      => ['voksne', 'voksen'],
            'child'                      => 'barn',
            'Amount paid'                => ['Beløp'],
            //            'Balance due'                => [''],
        ],
        "ro" => [
            // detects
            'from Airbnb'         => 'Descarcă aplicația Airbnb',
            //            'Your reservation is' => '',
            'Show full itinerary' => 'Afișează itinerarul complet', // after dates
            // 'Show all reservation details' => [''],// after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Codul rezervării',
            //            'Cancellation policy'        => '',
            'hosted by'                  => [' cu găzduire oferită de ', ' apartament a cărei gazdă este '],
            'Address'                    => 'Adresă',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['Check-in disponibil oricând după ora', 'Check-in-ul este între'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Check-in', // Check-in and Checkout in two column
            'Checkout'                   => 'Check-out',
            'Checkout by'                => ['Check-out la '],
            'Guests'                     => 'Oaspeți',
            'adult'                      => ['adulți', 'adult'],
            // 'child' => '',
            'Amount paid'                => ['Suma'],
            //            'Balance due'                => [''],
        ],
        "sv" => [
            // detects
            'from Airbnb'         => 'Skaffa Airbnb-appen',
            //            'Your reservation is' => '',
            'Show full itinerary' => 'Visa fullständig resplan', // after dates
            // 'Show all reservation details' => [''],// after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Bokningskod',
            //            'Cancellation policy'        => '',
            'hosted by'                  => [' hos värden ', ' med '],
            'Address'                    => 'Adress',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['Incheckning är', 'Incheckning när som helst efter'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Incheckning', // Check-in and Checkout in two column
            'Checkout'                   => 'Utcheckning',
            'Checkout by'                => ['Utcheckning senast'],
            'Guests'                     => 'Gäster',
            // 'adult' => '',
            //             'child' => '',
            'Amount paid'                => ['Betalt belopp'],
            'Balance due'                => ['Återstående'],
        ],
        "sk" => [
            // detects
            //            'from Airbnb'         => '',
            //            'Your reservation is' => '',
            'Show full itinerary' => 'Zobraziť celý itinerár', // after dates
            // 'Show all reservation details' => [''],// after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Kód rezervácie',
            //            'Cancellation policy'        => '',
            'hosted by'                  => [' u hostiteľa ', ' od hostiteľa '],
            'Address'                    => 'Adresa',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['Ubytovať sa môžete kedykoľvek po', 'Ubytovať sa môžete od'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Príchod', // Check-in and Checkout in two column
            'Checkout'                   => 'Odchod',
            'Checkout by'                => ['Odchod do'],
            'Guests'                     => 'Hostia',
            'adult'                      => ['dospelí', 'dospelý'],
            //             'child' => '',
            'Amount paid'                => ['Suma'],
            //            'Balance due'                => [''],
        ],
        "cs" => [
            // detects
            'from Airbnb'         => 'aplikaci Airbnb',
            //            'Your reservation is' => '',
            'Show full itinerary' => 'Zobrazit celý itinerář', // after dates
            // 'Show all reservation details' => [''],// after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Rezervační kód',
            //            'Cancellation policy'        => '',
            'hosted by'                  => ' s hostitelem ',
            'Address'                    => 'Adresa',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            //            'Check-in is any time after' => ['Ubytovať sa môžete kedykoľvek po', 'Ubytovať sa môžete od'],
            //            'flexible'                   => '',
            'Check-in' => 'Příjezd', // Check-in and Checkout in two column
            'Checkout' => 'Odjezd',
            //            'Checkout by'                => ['Odchod do'],
            'Guests'                     => 'Hosté',
            'adult'                      => 'dospěl',
            'child'                      => ['dítě', 'děti'],
            'Amount paid'                => ['Částka'],
            //            'Balance due'                => [''],
        ],
        "pl" => [
            // detects
            'from Airbnb'         => 'aplikację Airbnb',
            //            'Your reservation is' => '',
            'Show full itinerary' => 'Pokaż pełny plan podróży', // after dates
            // 'Show all reservation details' => [''],// after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => 'Kod rezerwacji',
            //            'Cancellation policy'        => '',
            'hosted by'                  => [', gospodarzem jest', ' gospodarza '],
            'Address'                    => 'Adres cz. 1',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            'Check-in is any time after' => ['Zameldowanie o dowolnej godzinie po'],
            //            'flexible'                   => '',
            'Check-in'                   => 'Przyjazd', // Check-in and Checkout in two column
            'Checkout'                   => 'Wyjazd',
            'Checkout by'                => ['Wymeldowanie do'],
            'Guests'                     => 'Goście',
            'adult'                      => ['dorosł', 'dorośli'],
            'child'                      => 'dzieci',
            'Amount paid'                => ['Kwota'],
            //            'Balance due'                => [''],
        ],
        "zh" => [
            // detects
            'from Airbnb'         => '获取爱彼迎App',
            //            'Your reservation is' => '',
            // 'Show full itinerary' => '', // after dates
            'Show all reservation details' => ['显示所有订单详情'], // after main info, before price
            // 'Change reservation' => '',

            //            'statusVariants'             => [''],
            'Reservation code'           => '预订码',
            //            'Cancellation policy'        => '',
            'hosted by'                  => ['出租的'],
            'Address'                    => '地址',
            //'badAddress' => [''],
            //            'send you the exact address' => '',
            // 'Check-in is any time after' => ['Zameldowanie o dowolnej godzinie po'],
            //            'flexible'                   => '',
            'Check-in'                   => '入住', // Check-in and Checkout in two column
            'Checkout'                   => '退房',
            // 'Checkout by'                => ['Wymeldowanie do'],
            'Guests'                     => '人数',
            'adult'                      => ['名成人'],
            // 'child'                      => 'dzieci',
            'Amount paid'                => ['已支付金额'],
            //            'Balance due'                => [''],
        ],
    ];

    private $patterns = [
        'time' => '\d{1,2}(?:[:：\.]\d{2})?(?:\s*Uhr|\s*[AaPp]\.?[Mm]\.?)?',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@airbnb.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (
                    (stripos($subject, '/') === 0 && preg_match($subject, $headers['subject']))
                || stripos($headers['subject'], $subject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                $this->http->XPath->query("//a[contains(@href, '.airbnb.')]")->length > 0
                || (!empty($dict['from Airbnb'])
                    && $this->http->XPath->query("//text()[{$this->contains($dict['from Airbnb'])}]")->length > 0)
            ) {
                $this->logger->debug('YES');

                if (!empty($dict['Address']) && (!empty($dict['Show full itinerary']) || !empty($dict['Show all reservation details']))
                    && $this->http->XPath->query("//text()[{$this->contains($dict['Show full itinerary'] ?? [])} or {$this->contains($dict['Show all reservation details'] ?? '')}]")->length > 0
                    && $this->http->XPath->query("//text()[{$this->eq($dict['Address'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]airbnb\.com$/', $from) > 0;
    }

    public function parseHTML(Email $email): void
    {
        $xpath = "//text()[{$this->eq($this->t('Reservation code'))}]/ancestor::*[count(.//text()[{$this->eq($this->t('Reservation code'))}]) < 2][last()]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $nodes = [null];
        }

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $status = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Your reservation is'))}]", $root,
                true, "/{$this->opt($this->t('Your reservation is'))}\s+({$this->opt($this->t('statusVariants'))})/");

            if (!empty($status)) {
                $h->general()
                    ->status($status);
            }
            $h->general()
                ->cancellation($this->http->FindSingleNode(".//text()[{$this->starts($this->t('Cancellation policy'))}]/following::text()[normalize-space()][1]", $root),
                    false, true);

            $confirmation = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Reservation code'))}]/following::text()[normalize-space()][1]",
                $root, true, '/^[A-Z\d]{5,}$/');

            if (empty($confirmation)) {
                // https://www.airbnb.com/reservation/itinerary?c=.pi80.-fe7b-3703-c5ff487c9acf&code=HM8MSHNMZC
                $confirmation = $this->http->FindSingleNode("(.//a[contains(@href, '&code=')])[1]/@href",
                    $root, true, '/&code=([A-Z\d]{5,})$/');
            }

            if (empty($confirmation)) {
                $confirmation = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Reservation code'))}]",
                    $root, true, "/^\s*{$this->opt($this->t('Reservation code'))}\s*[:：]\s*([A-Z\d]{5,})\s*$/");
            }

            if (empty($confirmation)) {
                // https://na01.safelinks.protection.outlook.com/?url=https%3A%2F%2Fwww.airbnb.com.br%2F ... 62-a1b9efeb8d27%26code%3DHMK4MCFEPA&data=05%...
                $confirmation = $this->http->FindSingleNode("(.//a[contains(@href, '%26code%3D')])[1]/@href",
                    $root, true, '/%26code%3D([A-Z\d]{5,})(?:$|&)/');
            }

            if ($confirmation) {
                $confirmationTitle = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Reservation code'))}]",
                    $root, true, '/^(.+?)(?:\s*[:：]\s*\w+)?\s*$/u');
                $h->general()->confirmation($confirmation, $confirmationTitle);
            }

            $name = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Address'))}]/preceding::text()[{$this->contains($this->t('hosted by'))}][1]/preceding::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('hosted by'))})][last()]", $root);
            $address = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Address'))}][preceding::text()[{$this->contains($this->t('hosted by'))}]]/following::text()[normalize-space()][1][not({$this->contains($this->t('send you the exact address'))})]", $root);

            $imgXpath = ".//a[contains(@href, 'airbnb') and contains(@href, '/rooms/')][not(normalize-space()) and //*[contains(@background, 'https')]]";

            if (empty($name) && empty($address)
                && empty($this->http->FindSingleNode(".//text()[{$this->contains($this->t('hosted by'))}]", $root))
                && !empty($this->http->FindSingleNode($imgXpath . "/following::text()[normalize-space()][1]/ancestor::h2/following::text()[normalize-space()][2]/ancestor::a[contains(@href, 'maps.google')]", $root))
            ) {
                // it-205383801.eml
                $name = $this->http->FindSingleNode($imgXpath . "/following::text()[normalize-space()][1]/ancestor::h2", $root);
                $address = $this->http->FindSingleNode($imgXpath . "/following::text()[normalize-space()][1]/ancestor::h2/following::text()[normalize-space()][1]", $root);
            }

            if (preg_match("/{$this->opt($this->t('badAddress'))}/", $address)) {
                $address = '';
                $email->removeItinerary($h);
                $email->setIsJunk(true);
            }

            if (empty($address)
                && !empty($this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Address'))}])[1]", $root))
                && !empty($this->http->FindSingleNode(".//text()[{$this->contains($this->t('send you the exact address'))}]", $root))
                && empty($this->http->FindSingleNode(".//a[contains(@href, 'maps.google.')]"))
                && empty($this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Address'))}]/following::text()[normalize-space()][position() < 4]/ancestor::a)[1]", $root))
            ) {
                $h->hotel()->noAddress();
            } else {
                $h->hotel()
                    ->address($address);
            }
            $h->hotel()
                ->name(str_replace(['{', '}'], ['[', ']'], htmlspecialchars($name)))
                ->house();

            $checkInText = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Check-in is any time after'))}]/ancestor::*[self::th or self::td][1]", $root);

            if (empty($checkInText)) {
                $checkInText = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Check-in is any time after'))}][following::text()[{$this->eq($this->t('Show full itinerary'))}]]/ancestor::*[self::th or self::td][1]", $root);
            }

            if (empty($checkInText)) {
                $checkInText = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Show full itinerary'))}]/preceding::td[2]", $root);
            }

            if (preg_match("/^\s*[[:alpha:]\-]*,\s*(?<date>(?:\d{1,2}|[[:alpha:]]+)\s*[.,]?(?:\bde\b)?\s*([[:alpha:]]+|\d{1,2})\s*[.,]?(?:\bde\b)?\s*\d{4}|\d+년\s*\d+월\s*\d+일)\D+(?<time>{$this->patterns['time']}|{$this->opt($this->t('flexible'))}|flexible)/u",
                    $checkInText, $m)
                || preg_match("/{$this->opt($this->t('Check-in is any time after'))}\s*(?<time>{$this->patterns['time']}|{$this->opt($this->t('flexible'))}|flexible)\s*[-[:alpha:]]+\s*,\s*(?<date>[[:alpha:]]+\s*\d+,\s+\d{4})/u",
                    $checkInText, $m)
                || preg_match("/^\s*{$this->opt($this->t('Check-in is any time after'))}\s*(?<time>{$this->patterns['time']}|{$this->opt($this->t('flexible'))}|flexible).*\s*[-[:alpha:]]+\s*,\s*(?<date>.{6,})/u",
                    $checkInText, $m)
                || preg_match("/^\s*[[:alpha:]\-]+\.?\s*[,\s]\s*(?<date>.{6,}?)\s*(?<time>{$this->patterns['time']}|{$this->opt($this->t('flexible'))}|flexible)$/u",
                    $checkInText, $m)
            ) {
                if (preg_match("/^{$this->opt($this->t('flexible'))}$/i", $m['time'])) {
                    // C‌h‌e‌c‌k‌-‌i‌n‌ ‌i‌s‌ ‌f‌l‌e‌x‌i‌b‌l‌e
                    $m['time'] = '00:00';
                }
                $h->booked()
                    ->checkIn($this->normalizeDate($m['date'] . ', ' . $m['time']));

                if (!empty($h->getCheckInDate())) {
                    $this->date = $h->getCheckInDate();
                }
            }

            if (empty($checkInText)) {
                $checkInText = implode(' ',
                    $this->http->FindNodes(".//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}]][*[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Checkout'))}]]/*[normalize-space()][1]/descendant::text()[normalize-space()][position() > 1]", $root));

                if (!empty($checkInText)) {
                    // Sun, Jun 18  3:00 PM - 5:00 PM   ->  Sun, Jun 18   3:00 PM
                    // Fr., 6. Okt. 15:00 Uhr–17:00 Uhr
                    $checkInText = preg_replace("/^(.+\s+\d+:\d+ *[^\d\-]{0,6})\s*[^\s\w]\s*\d+:\d+ *[^\d\-]{0,6}\s*$/u",
                        '$1', $checkInText);
                    // ES: vie, 6 oct. De 8:00 a 10:00
                    // ES: vie, 8 sept. 20:00 a 22:00
                    $checkInText = preg_replace("/^(.+?)\s+(?:De\s+)?(\d+:\d+ *[^\d\-]{0,6}) \D \d+:\d+ *[^\d\-]{0,6}\s*$/u",
                        '$1 $2', $checkInText);
                    $h->booked()
                        ->checkIn($this->normalizeDate($checkInText));
                }
            }

            $checkOutText = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('Checkout by'))}]/ancestor::*[self::th or self::td][1]", $root);

            if (empty($checkOutText)) {
                $checkOutText = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Show full itinerary'))}]/preceding::td[1]", $root);
            }

            if (preg_match("/^\s*[[:alpha:]\-]*,\s*(?<date>(?:\d{1,2}|[[:alpha:]]+)\s*[.,]?(?:\bde\b)?\s*([[:alpha:]]+|\d{1,2})\s*[.,]?(?:\bde\b)?\s*\d{4})\D*(?<time>{$this->patterns['time']}|{$this->opt($this->t('flexible'))})/u",
                    $checkOutText, $m)
                || preg_match("/^\w+\,\s(?<date>.+?)\s*{$this->opt($this->t('Checkout by'))}\s*(?<time>.+)/u",
                    $checkOutText, $m)
                || preg_match("/{$this->opt($this->t('Checkout by'))}\s*(?<time>{$this->patterns['time']})\s*\w+\,\s*(?<date>\w+\s*\d+\,\s+\d{4})/u",
                    $checkOutText, $m)
                || preg_match("/{$this->opt($this->t('Checkout by'))}\s*(?<time>{$this->patterns['time']})\s*[-[:alpha:]]+\,\s(?<date>.+)/u",
                    $checkOutText, $m)
                || preg_match("/^\s*[[:alpha:]\-]+\.?\s*[,\s]\s*(?<date>.{6,}?)\s*(?<time>{$this->patterns['time']}|{$this->opt($this->t('flexible'))}|flexible)$/u",
                    $checkOutText, $m)
            ) {
                $m['time'] = str_replace(", ", ":", $m['time']);
                $h->booked()
                    ->checkOut($this->normalizeDate($m['date'] . ', ' . $m['time']));
            }

            if (empty($checkOutText)) {
                $checkOutText = implode(' ',
                    $this->http->FindNodes(".//tr[count(*[normalize-space()]) = 2][*[normalize-space()][1]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}]][*[normalize-space()][2]/descendant::text()[normalize-space()][1][{$this->eq($this->t('Checkout'))}]]/*[normalize-space()][2]/descendant::text()[normalize-space()][position() > 1]", $root));

                if (!empty($checkOutText)) {
                    // Sun, Jun 18  3:00 PM - 5:00 PM   ->  Sun, Jun 18   5:00 PM
                    $checkOutText = preg_replace("/^(.+)\s+\d+:\d+ *[^\d\-]{0,6}\s*-\s*(\d+:\d+ *[^\d\-]{0,6})\s*$/",
                        '$1 $2', $checkOutText);
                    $h->booked()
                        ->checkOut($this->normalizeDate($checkOutText));
                }
            }

            $guestsValue = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Guests'))}]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^\d+$/", $guestsValue)) {
                $h->booked()->guests($guestsValue);
            } elseif (preg_match("/\b(\d{1,3})\s*{$this->opt($this->t('adult'))}/", $guestsValue, $m)) {
                $h->booked()->guests($m[1]);
            } elseif (preg_match("/\b{$this->opt($this->t('adult'))}\s*(\d{1,3})\s*명/u", $guestsValue, $m)) {
                $h->booked()->guests($m[1]);
            }

            if (preg_match_all("/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/", $guestsValue, $m)) {
                $h->booked()->kids(array_sum($m[1]));
            } elseif (preg_match_all("/\b{$this->opt($this->t('child'))}\s*(\d{1,3})\s*명/u", $guestsValue, $m)) {
                $h->booked()->kids(array_sum($m[1]));
            }

            $totalPricePaid = $this->http->FindSingleNode(".//tr/*[not(.//tr) and {$this->starts($this->t('Amount paid'))}][last()]/following-sibling::td[normalize-space()][last()]", $root);

            if (empty($totalPricePaid)) {
                $totalPricePaid = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Amount paid'))}]/following::text()[normalize-space()][1]", $root);
            }

            if (empty($totalPricePaid)) {
                $totalPricePaid = $this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Amount paid'))}])[last()]/following::text()[normalize-space()][1]", $root);
            }
            $totalPriceScheduled = $this->http->FindSingleNode(".//tr/*[not(.//tr) and {$this->starts($this->t('Balance due'))}]/following-sibling::td[normalize-space()][last()]", $root);

            if (empty($totalPriceScheduled)) {
                $totalPriceScheduled = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Balance due'))}]/following::text()[normalize-space()][1]", $root);
            }

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPricePaid, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)(?<currency>[^\d)(]+?)[ ]*$/', $totalPricePaid, $m)
                || preg_match('/^\s*[^\s\d]{1,2}\s*(?<amount>\d[,.\'\d ]*)(?<currency>[A-Z]{3})[ ]*$/u',
                    $totalPricePaid, $m)
            ) {
                $totalPaidAmount = $this->normalizeAmount($m['amount']);
                $totalPaidCurrency = $this->http->FindSingleNode(".//tr/*[not(.//tr) and {$this->starts($this->t('Amount paid'))}]",
                        $root, true, "/.+\(\s*([A-Z]{3})\s*\)$/")
                    ?? $this->http->FindSingleNode("(.//text()[{$this->starts($this->t('Amount paid'))}])[last()]", $root,
                        true, "/.+\(\s*([A-Z]{3})\s*\)$/")
                    ?? $m['currency'];
            }

            if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPriceScheduled, $m)
                || preg_match('/^(?<amount>\d[,.\'\d ]*)(?<currency>[^\d)(]+?)[ ]*$/', $totalPriceScheduled, $m)
                || preg_match('/^\s*[^\s\d]{1,2}\s*(?<amount>\d[,.\'\d ]*)(?<currency>[A-Z]{3})[ ]*$/u',
                    $totalPriceScheduled, $m)
            ) {
                // $41.51 AUD
                $totalScheduledAmount = $this->normalizeAmount($m['amount']);
                $totalScheduledCurrency = $this->http->FindSingleNode(".//tr/*[not(.//tr) and {$this->starts($this->t('Balance due'))}]",
                        $root, true, "/.+\(\s*([A-Z]{3})\s*\)$/") ?? $m['currency'];
            }

            if (!empty($totalPaidAmount) && empty($totalPriceScheduled)) {
                $h->price()
                    ->currency($this->normalizeCurrency($totalPaidCurrency))
                    ->total($totalPaidAmount);
            } elseif (!empty($totalPaidAmount) && !empty($totalPriceScheduled) && $totalScheduledAmount !== null && $totalPaidCurrency === $totalScheduledCurrency) {
                $h->price()
                    ->currency($this->normalizeCurrency($totalPaidCurrency))
                    ->total($totalPaidAmount + $totalScheduledAmount);
            }

            $this->detectDeadLine($h);
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->date = strtotime($parser->getDate());
        $this->parseHTML($email);
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

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
            'BRL' => ['R$'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        if (preg_match("/^[^\d\s]{1,5} ([A-Z]{3})$/", $string, $m)) {
            return $m[1];
        }

        return $string;
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (
            preg_match("/Cancel before (?<time>{$this->patterns['time']}) on (?<date>\d+\s*[[:alpha:]]+) and get a full refund/u", $cancellationText, $m)
            || preg_match("/Cancel before (?<time>{$this->patterns['time']}) on (?<date>[[:alpha:]]+\s*\d+) and get a full refund/u", $cancellationText, $m)
            || preg_match("/Free cancellation before (?<time>{$this->patterns['time']}) on (?<date>[[:alpha:]]+\s*\d+)\./u", $cancellationText, $m)
            || preg_match("/Storniere bis (?<time>{$this->patterns['time']}) am (?<date>\d+\.\s*[[:alpha:]]+)., um eine volle Rückerstattung zu erhalten/u", $cancellationText, $m)
            || preg_match("/Free cancellation until (?<time>{$this->patterns['time']}) on (?<date>[[:alpha:]]+\s+\d{1,2})\./u", $cancellationText, $m)
            || preg_match("/Cancella la prenotazione prima delle ore (?<time>{$this->patterns['time']}) del (?<date>\d+\s*[[:alpha:]]+) e riceverai un rimborso completo./u", $cancellationText, $m)
            || preg_match("/Cancela (?i)antes del (?<date>\d{1,2}\s*[[:alpha:]]+)\. a las (?<time>{$this->patterns['time']}) y consigue un reembolso completo\./u", $cancellationText, $m) // es
            || preg_match("/Якщо (?i)скасувати бронювання раніше\s+(?<time>{$this->patterns['time']})\s+(?<date>\d{1,2}\s*[[:alpha:]]+)\., вам будуть повернені всі кошти\./u", $cancellationText, $m) // uk
            || preg_match("/Kostenlose Stornierung vor\s+(?<time>{$this->patterns['time']})\s+(am\s+)?(?<date>\d{1,2}\.?\s*[[:alpha:]]+)\.\. Wenn du/u", $cancellationText, $m) // de
            // Ücretsiz iptal için son tarih 23 Şub, saat 13.00.
            || preg_match("/Ücretsiz iptal için son tarih\s+(?<date>\d{1,2}\s+[[:alpha:]]+)\s*, saat\s*(?<time>{$this->patterns['time']})\s*\./u", $cancellationText, $m) // tr
            || preg_match("/Cancelación gratuita antes del\s+(?<date>\d{1,2}\s+[[:alpha:]]+)\.? a las\s*(?<time>{$this->patterns['time']})\s*\./u", $cancellationText, $m) // tr
            || preg_match("/Annulation gratuite jusqu'à (?<time>{$this->patterns['time']}) le (?<date>\d{1,2}\s+[[:alpha:]]+)\./u", $cancellationText, $m) // tr
            || preg_match("/Annulez avant l'arrivée à (?<time>{$this->patterns['time']}) le (?<date>\d{1,2}\s+[[:alpha:]]+)\. pour recevoir un remboursement partiel/u", $cancellationText, $m) // tr
            || preg_match("/Annulation gratuite avant le (?<date>\d{1,2}\s+[[:alpha:]]+)\. à (?<time>{$this->patterns['time']})\./u", $cancellationText, $m) // tr
        ) {
            $date = $this->normalizeDate($m['date'] . ', ' . $m['time']);

            if (!empty($date) && !empty($h->getCheckInDate())) {
                if ($date > $h->getCheckInDate() && $h->getCheckInDate() - strtotime("- 1 year", $date) < 60 * 60 * 24 * 30 * 6) {
                    $date = strtotime("- 1 year", $date);
                }
            }
            $h->booked()->deadline($date);

            return;
        }

        if (
            preg_match("/^Bu rezervasyon için para iadesi yapılamaz\./", $cancellationText) // tr
          || preg_match("/^Cette réservation n\'est pas remboursable\./", $cancellationText) // fr
        ) {
            $h->booked()->nonRefundable();
        }

        if (preg_match("/Annulation gratuite pendant (?<hours>\d+) heures\./", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['hours'] . ' hours');
        }
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('$str 1 = ' . print_r($str, true));
        $year = date("Y", $this->date);
        $this->logger->debug("/^\s*(?:[-[:alpha:]]+\.?\s*[,\s]\s*)?(\d{1,2})\.?[ ]*(?:de\s+)?([[:alpha:]]+)\.?[ ]*[,\s][ ]*({$this->patterns['time']})\s*$/u");
        $str = preg_replace('/ π\.μ\.\s*$/', 'am', $str); // lang el
        $str = preg_replace('/ μ\.μ\.\s*$/', 'pm', $str); // lang el
        // $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?'
        $in = [
            // 27 Dec, 3:00 PM
            // 21 de abr., 15:00
            // 30. Apr., 16:00 Uhr
            // jeu. 27 avr. 16:00
            "/^\s*(?:[-[:alpha:]]+\.?\s*[,\s]\s*)?(\d{1,2})\.?[ ]*(?:de\s+)?([[:alpha:]]+)\.?[ ]*[,\s][ ]*({$this->patterns['time']})\s*$/u",
            // 8 червня 2021 p., 11:00    |    15 de octubre de 2021, 11:00
            // 13. Juli 2022, 10:00
            // jue, 4 ene. 2024 14:00
            "/^\s*(?:[-[:alpha:]]+\.?\s*[,\s]\s*)?(\d{1,2})[.,]?[ ]*(?:de\s+)?([[:alpha:]]+)[.]?(?:\s+de)?[ ]*(\d{4})(?:[ ]*[Pp]\.)?[ ]*[,\s+][ ]*({$this->patterns['time']})\s*$/u",
            // Dec 29, 4:00 PM
            "/^\s*([[:alpha:]]+)\s*(\d{1,2})[ ]*,[ ]*({$this->patterns['time']})$/u",
            // 2022년 5월 12일, 11:00
            "/^\s*(\d{4})년\s*(\d{1,2})월\s*(\d{1,2})일[ ]*,[ ]*(\d{1,2}:\d{2})\s*$/u",
            // June 7, 2022, 11:00 AM
            "/^\s*([[:alpha:]]+)\s+(\d{1,2})[.,]?[ ]*(\d{4})[ ]*,[ ]*(\d{1,2}:\d{2}(?: *[ap]m)?)\s*$/ui",
            //9. Apr, 14:00 Uhr am
            "/^(\d+)\.\s*(\w+)\,\s*([\d\:]+)\s*\w+\s*(a?p?m)\s*$/",
            // 4월 27일 (목) 오후 4:00
            // 10月17日周二 下午4:00
            "/^\s*(\d{1,2})(?:월|月)\s*(\d{1,2})(?:일|日)[ ]*\D+[ ]*(\d{1,2}:\d{2})\s*$/u",
            // 31 Mayıs Çar 09.00
            "/^(\d{1,2})[ ]+([[:alpha:]]+)[ ]+[[:alpha:]]+[ ]+(\d{1,2})\.(\d{2})\s*$/u",
            // ne 15. 10. 11:00
            "/^\s*[[:alpha:]]+\s+(\d{1,2})\.[ ]+(\d{1,2})\.[ ]+(\d{1,2}:\d{2})\s*$/u",
            // quinta, 1/02/2024 11:00
            "/^\s*[[:alpha:]]+[\s,]+\s*(\d{1,2})\\/(\d{2})\\/(\d{4})[ ]+(\d{1,2}:\d{2})\s*$/u",
            // quinta, 1/02 11:00
            "/^\s*[[:alpha:]]+[\s,]+\s*(\d{1,2})\\/(\d{2})[ ]+(\d{1,2}:\d{2})\s*$/u",
            // Tue, Aug 22 4:00 PM
            "/^(\w+)\.?\,\s*(\w+)\.?\s*(\d+)\s*([\d\:]+\s*A?\.?P?\.?M\.?)$/iu",
        ];
        $out = [
            "$1 $2 $year $3",
            "$1 $2 $3, $4",
            "$2 $1 $year, $3",
            "$1-$2-$3, $4",
            "$2 $1 $3, $4",
            "$1 $2 $year, $3 $4",
            "$year-$1-$2, $3",
            "$1 $2 $year, $3:$4",
            "$1.$2.$year, $3",
            "$1.$2.$3, $4",
            "$1.$2.$year, $3",
            "$1, $3 $2 $year, $4",
        ];
        $str = preg_replace($in, $out, $str);
        $str = preg_replace('/ Uhr\s*$/', '', $str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/(.+, )((?:1[3-9]|2\d):\d{2}) *(?:a|p)m\s*$/ui", $str, $m)) {
            // June 5, 2022, 14:00 PM -> June 5, 2022, 14:00
            $str = $m[1] . $m[2];
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            $count = 0;
            $words = ['Address', 'Reservation code', 'Show full itinerary', 'Show all reservation details'];

            foreach ($words as $word) {
                if (!empty($dict[$word])
                    && $this->http->XPath->query("//text()[{$this->eq($dict[$word])}]")->length > 0
                ) {
                    $count++;
                }
            }

            if ($count >= 2) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field)) . ')';
    }
}
