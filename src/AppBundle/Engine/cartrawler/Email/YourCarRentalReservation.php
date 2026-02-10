<?php

namespace AwardWallet\Engine\cartrawler\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourCarRentalReservation extends \TAccountChecker
{
    public $mailFiles = "cartrawler/it-383765306.eml, cartrawler/it-41121435.eml, cartrawler/it-41569499.eml, cartrawler/it-42239289.eml, cartrawler/it-66440205.eml, cartrawler/it-6691907.eml, cartrawler/it-8241078.eml";

    public static $detectProvider = [
        'hautos' => [
            'from'       => 'noreply@holidayautos.com',
            'bodyLink'   => ['.holidayautos.com'],
            'body'       => ['Thank you for choosing Holiday Autos', 'dass Sie Ihren Mietwagen über Holiday Autos gebucht haben'],
            'imgLogoAlt' => 'holidayautos',
        ],
        'dollar' => [
            'from' => 'Dollar Car Rental',
            //            'bodyLink' => [''],
            'body'       => ['Thank you for choosing Dollar Car Rental'],
            'imgLogoAlt' => 'dollar',
        ],
        'ryanair' => [
            'from'       => ['Ryanair Car Hire', '.ryanair@cartrawler.com'],
            'bodyLink'   => ['car-hire.ryanair.com'],
            'body'       => ['dass Sie Ihren Mietwagen über Ryanair gebucht haben', 'Thank you for choosing Ryanair for your car hire'],
            'imgLogoAlt' => 'ryanair',
        ],
        'indigo' => [
            'from' => ['Indigo Car Hire'],
            //            'bodyLink' => [''],
            'body' => ['Thank you for choosing Indigo Car Hire for your car hire'],
            //            'imgLogoAlt' => '',
        ],
        'alaskaair' => [
            'from'       => ['Alaska Airlines Cars'],
            'bodyLink'   => ['.alaskaair.com/car-rental'],
            'body'       => ['Thank you for choosing Alaska for your car rental'],
            'imgLogoAlt' => 'alaska-alt',
        ],
        'norwegian' => [
            'from' => ['Norwegian Air Shuttle'],
            //            'bodyLink' => [''],
            'body' => [
                'Takk for at du valgte CarTrawler & Norwegian Air Shuttle for bilutleie',
                'Gracias por elegir Norwegian Air Shuttle para tu alquiler de coche',
            ],
            'imgLogoAlt' => 'norwegian-alt',
        ],
        'opodo' => [
            'from'     => ['Opodo Cars', '@carrental.opodo.com'],
            'bodyLink' => ['carhire.opodo.com'],
            'body'     => [
                'Thank you for choosing Opodo for your car rental',
                'Vielen Dank, dass Sie Ihren Mietwagen über Opodo gebucht haben',
            ],
            'imgLogoAlt' => 'opodo',
        ],
        'easyjet' => [
            'from' => ['easyJet Car Rental', '.easyjet@cartrawler.com'],
            //            'bodyLink' => [''],
            'body'       => ['Nous vous remercions d’avoir choisi easyJet pour la location de votre voiture'],
            'imgLogoAlt' => 'easyjet-orange-bg',
        ],
        'copaair' => [
            'from' => ['Copa Airlines'],
            //            'bodyLink' => [''],
            'body'       => ['Obrigado por escolher a Copa Airlines para o seu aluguer de automóveis'],
            'imgLogoAlt' => 'copa',
        ],
        'hotels' => [
            'from'       => ['Hotels.com_US_homepage', 'Hotels.com'],
            'bodyLink'   => ['cars.hotels.com/'],
            'body'       => ['Hotels.com을(를) 선택해 주셔서 감사합니다'],
            'imgLogoAlt' => 'hotels-com',
        ],
        'supersaver' => [
            'from' => ['Travelstart'],
            //            'bodyLink' => [''],
            'body'       => ['Thank you for choosing Travelstart for your car rental'],
            'imgLogoAlt' => 'travelstart',
        ],
        'jetstar' => [
            'from' => ['Jetstar'],
            //            'bodyLink' => [''],
            'body'       => ['Thank you for choosing Jetstar for your car rental'],
            'imgLogoAlt' => 'jetstar',
        ],
        'skywards' => [
            'from'       => ['in association with Emirates'],
            'bodyLink'   => ['cars.cartrawler.com/emirates/'],
            'body'       => ['Thank you for choosing Cartrawler, in association with Emirates for', 'نشكرك على اختيارك Cartrawler, in association with Emirates للحصول على'],
            'imgLogoAlt' => 'emirates',
        ],
        'westjet' => [
            'from' => ['WestJet and CarTrawler'],
            //            'bodyLink' => [''],
            'body'       => ['Thank you for choosing WestJet and CarTrawler for'],
            'imgLogoAlt' => 'westjet',
        ],
        'justfly' => [
            'from' => ['Justfly.com'],
            //            'bodyLink' => [''],
            'body'       => ['Thank you for choosing Justfly.com for'],
            'imgLogoAlt' => 'justfly',
        ],
        'velocity' => [
            'from' => ['Virgin Australia'],
            //            'bodyLink' => [''],
            'body'       => ['We hope to welcome you back on the Virgin Australia'],
            'imgLogoAlt' => 'justfly',
        ],
        'expedia' => [
            'from' => ['Expedia'],
            //            'bodyLink' => [''],
            // 'body'       => [''],
            'imgLogoAlt' => 'expedia',
        ],
        'klm' => [
            'from' => ['CarTrawler & KLM'],
            //            'bodyLink' => [''],
            'body'       => ['Obrigado por escolher a KLM para o seu aluguer'],
            'imgLogoAlt' => 'klm',
        ],
        'transavia' => [
            'from'       => ['CarTrawler via Transavia.com'],
            'bodyLink'   => ['cars.cartrawler.com/transavia/'],
            'body'       => ['d’avoir choisi CarTrawler via Transavia.com pour'],
            'imgLogoAlt' => 'klm',
        ],
        'hopper' => [
            'from' => ['Hopper'],
            // 'bodyLink' => ['cars.cartrawler.com/transavia/'],
            // 'body' => ['d’avoir choisi CarTrawler via Transavia.com pour'],
            // 'imgLogoAlt' => 'klm',
        ],
        'pegasus' => [
            'from' => ['Hopper'],
            // 'bodyLink' => ['cars.cartrawler.com/transavia/'],
            'body'       => ['Araç kiralamanız için Pegasus Airlines'],
            'imgLogoAlt' => 'pegasus',
        ],

        'cartrawler' => [ // must be last in providers
            'from'     => '@cartrawler.com',
            'bodyLink' => ['.cartrawler.com'],
            'body'     => ['Bedankt dat u uw auto via CarTrawler'],
            //            'imgLogoAlt' => '',
        ],
        // other companies, not provider
        [
            'from'     => 'cheapcarrental.com',
            'bodyLink' => ['.cheapcarrental.com'],
            'body'     => ['Thank you for choosing cheapcarrental.com'],
            //            'imgLogoAlt' => '',
        ],
        [
            'from'       => 'arguscarhire.com',
            'bodyLink'   => ['.arguscarhire.com'],
            'body'       => ['Thank you for choosing arguscarhire.com for'],
            'imgLogoAlt' => 'arguscarhire',
        ],
        [
            // 'from'       => 'Tipoa',
            'bodyLink'   => ['.tipoa.com/'],
            'body'       => ['Grazie per aver scelto Tipoa per la tua auto a noleggio'],
            // 'imgLogoAlt' => '',
        ],
        [
            'from'       => 'Carmaster.co.il',
            // 'bodyLink'   => ['.tipoa.com/'],
            'body'       => ['תודה שבחרת ב-Carmaster.co.il'],
            // 'imgLogoAlt' => '',
        ],
    ];

    public $lang = 'en';

    public static $dictionary = [
        'nl' => [
            'cancelSubjectRe'           => 'Uw annuleringsverzoek is gelukt – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => ['Beste', 'Geachte'],
            'Booking Reference Number:' => ['Boekingsnummer:', 'Boekingsreferentienummer:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'Bevestigingsnummer',
            'Desk telephone no'         => 'Telefoonnummer balie',
            'or similar'                => 'of vergelijkbaar',
            'equipment'                 => ['deuren', 'Handgeschakeld', 'Airconditioning'],
            'Supplier:'                 => 'Leverancier:',
            // Block about Transaction
            'Transaction Information'   => 'Transactiegegevens',
            'Transaction Currency'      => 'Transactievaluta',
            'Transaction Amount'        => 'Transactiebedrag',
            'Rental details'            => 'Verhuurgegevens',
            'Pick-up date'              => 'Ophaaldatum',
            'Drop-off date'             => 'Inleverdatum',
        ],
        'da' => [
            // 'cancelSubjectRe' => 'Uw annuleringsverzoek is gelukt – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Kære',
            'Booking Reference Number:' => ['Reservationsreferencenummer:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            //            'Confirmation no.' => '',
            //            'Desk telephone no' => '',
            //            'or similar' => '',
            'equipment'                 => ['NOT_TRANSLATE', 'NOT_TRANSLATE', 'NOT_TRANSLATE'],
            //            'Supplier:' => '',
            // Block about Transaction
            //            'Transaction Information' => '',
            //            'Transaction Currency' => '',
            //            'Transaction Amount' => '',
            //            'Rental details' => '',
            //            'Pick-up date' => '',
            //            'Drop-off date' => '',
        ],
        'de' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Sehr geehrte(r)',
            'Booking Reference Number:' => ['Buchungsreferenznummer:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'Bestätigungs-Nr.',
            'Desk telephone no'         => 'Telefonnummer Schalter',
            'or similar'                => 'oder vergleichbar',
            'equipment'                 => ['Türen', 'Handschaltgetriebe', 'Klimaanlage'],
            'Supplier:'                 => 'Anbieter:',
            // Block about Transaction
            'Transaction Information'   => 'Transaktionsdaten',
            'Transaction Currency'      => 'Transaktionswährung',
            'Transaction Amount'        => 'Transaktionsbetrag',
            'Rental details'            => 'Mietangaben',
            'Pick-up date'              => 'Abholdatum',
            'Drop-off date'             => 'Abgabedatum',
        ],
        'es' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Estimado/a',
            'Booking Reference Number:' => ['Número de referencia de tu reserva:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            //            'Confirmation no.' => '',
            //            'Desk telephone no' => '',
            //            'or similar' => '',
            'equipment'                 => ['Puertas', 'Cambio manual', 'Aire acondicionado'],
            'Supplier:'                 => 'Proveedor:',
            // Block about Transaction
            //            'Transaction Information' => '',
            //            'Transaction Currency' => '',
            //            'Transaction Amount' => '',
            //            'Rental details' => '',
            //            'Pick-up date' => '',
            //            'Drop-off date' => '',
        ],
        'fr' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Cher/Chère',
            'Booking Reference Number:' => ['Numéro de la réservation:', 'Numéro de la réservation :'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'Confirmation N°',
            'Desk telephone no'         => 'N° de téléphone d\'agence',
            'or similar'                => 'ou équivalent',
            'equipment'                 => ['Portes', 'Boîte de vitesses manuelle', 'NOT_TRANSLATE'],
            'Supplier:'                 => ['Fournisseur:', 'Fournisseur :'],
            // Block about Transaction
            'Transaction Information'   => 'Informations sur le paiement',
            'Transaction Currency'      => 'Devise du paiement',
            'Transaction Amount'        => 'Montant du paiement',
            'Rental details'            => 'Informations de location',
            'Pick-up date'              => ['Date de retrait', 'Date de prise en charge'],
            'Drop-off date'             => ['Date de retour', 'Date de restitution'],
        ],
        'no' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Kjære',
            'Booking Reference Number:' => ['Referansenummer for bestilling:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'Bekreftelsesnr.',
            'Desk telephone no'         => 'Filialens telefonnummer',
            'or similar'                => 'eller lignende',
            'equipment'                 => ['Dører', 'Manuelt gir', 'Air conditioning'],
            'Supplier:'                 => 'Leverandør:',
            // Block about Transaction
            'Transaction Information'   => 'Transaksjonsopplysninger',
            'Transaction Currency'      => 'Transaksjonsvaluta',
            'Transaction Amount'        => 'Transaksjonsbeløp',
            'Rental details'            => 'Leiebildetaljer',
            'Pick-up date'              => 'Hentedato',
            'Drop-off date'             => ['Leveringsdato', 'Avleveringsdato'],
        ],
        'es' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Estimado/a',
            'Booking Reference Number:' => ['Número de referencia de tu reserva:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            //            'Confirmation no.' => '',
            //            'Desk telephone no' => '',
            'or similar'                => 'o similar',
            'equipment'                 => ['Puertas', 'Cambio manual', 'Aire acondicionado'],
            'Supplier:'                 => 'Proveedor:',
            // Block about Transaction
            //            'Transaction Information' => '',
            //            'Transaction Currency' => '',
            //            'Transaction Amount' => '',
            //            'Rental details' => '',
            //            'Pick-up date' => '',
            //            'Drop-off date' => '',
        ],
        'pt' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                                           => 'Caro(a)',
            'Booking Reference Number:'                      => ['Número de referência da reserva:'],
            'Your booking reference number has changed from' => 'O número de referência da sua reserva foi alterado de', // instead of Booking Reference Number
            'to '                                            => 'para', // instead of Booking Reference Number
            'Confirmation no.'                               => 'Confirmação n.º:',
            'Desk telephone no'                              => 'Número de telefone do balcão',
            'or similar'                                     => 'ou semelhante',
            'equipment'                                      => ['Portas', 'Caixa de velocidades automática', 'Ar condicionado'],
            'Supplier:'                                      => 'Fornecedor:',
            // Block about Transaction
            // 'Transaction Information' => '',
            // 'Transaction Currency' => '',
            // 'Transaction Amount' => '',
            // 'Rental details' => '',
            // 'Pick-up date' => '',
            // 'Drop-off date' => '',
        ],
        'sv' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Bästa',
            'Booking Reference Number:' => ['Referensnummer för bokningen:'],
            'equipment'                 => ['Dörrar', 'Automatväxellåda', 'Luftkonditionering'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'Bekräftelsenummer',
            'Desk telephone no'         => 'Telefonnummer till disken',
            'or similar'                => 'eller motsvarande',
            'Supplier:'                 => 'Leverantör:',
            // Block about Transaction
            'Transaction Information' => 'Transaktionsuppgifter',
            'Transaction Currency'    => 'Transkationsvaluta',
            'Transaction Amount'      => 'Transaktionsbelopp',
            'Rental details'          => 'Hyresuppgifter',
            'Pick-up date'            => 'Upphämtningsdatum',
            'Drop-off date'           => 'Avlämningsdatum',
        ],
        'it' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Gentile',
            'Booking Reference Number:' => ['Numero di riferimento della prenotazione:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'N. di conferma',
            'Desk telephone no'         => 'N. di telefono del banco dell\'autonoleggio',
            'or similar'                => 'o simile',
            'equipment'                 => ['Porte', 'Cambio automatico', 'Aria condizionata'],
            'Supplier:'                 => 'Fornitore:',
            // Block about Transaction
            'Transaction Information' => 'Informazioni sulla transazione',
            'Transaction Currency'    => 'Valuta della transazione',
            'Transaction Amount'      => 'Importo della transazione',
            'Rental details'          => 'Dettagli del noleggio',
            'Pick-up date'            => 'Data di ritiro',
            'Drop-off date'           => 'Data di riconsegna',
        ],
        'ko' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => '님,',
            'Booking Reference Number:' => ['예약 참조 번호:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => '확인 번호',
            'Desk telephone no'         => '업체 전화번호',
            'or similar'                => '또는 유사한 차량',
            'equipment'                 => ['도어', '자동 변속', '냉난방'],
            'Supplier:'                 => '공급업체:',

            // Block about Transaction
            'Transaction Information' => '거래 정보',
            'Transaction Currency'    => '거래 통화',
            'Transaction Amount'      => '거래 금액',
            'Rental details'          => '대여 세부 정보',
            'Pick-up date'            => '픽업 날짜',
            'Drop-off date'           => '반납 날짜',
        ],
        'ja' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => '様',
            'Booking Reference Number:' => ['予約番号：'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'  => '確認番号',
            'Desk telephone no' => '電話番号',
            'or similar'        => 'または同程度',
            'equipment'         => ['ドア', 'オートマチック車', 'エアコン'],
            'Supplier:'         => 'サプライヤ：',

            // Block about Transaction
            // 'Transaction Information' => '',
            // 'Transaction Currency' => '',
            // 'Transaction Amount' => '',
            // 'Rental details' => '',
            // 'Pick-up date' => '',
            // 'Drop-off date' => '',
        ],
        'ar' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'عميلنا العزيز',
            'Booking Reference Number:' => ['الرقم المرجعي للحجز:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'  => 'أميال سكاي واردز المستحقة:',
            'Desk telephone no' => 'رقم هاتف المكتب',
            'or similar'        => 'أو ما شابه',
            'equipment'         => ['أبواب', 'ناقل حركة أوتوماتيكي', 'エアコン'],
            'Supplier:'         => 'مكيفة الهواء',

            // Block about Transaction
            'Transaction Information' => 'معلومات حول المعاملة',
            'Transaction Currency'    => 'عملة المعاملة',
            'Transaction Amount'      => 'قيمة المعاملة',
            'Rental details'          => 'تفاصيل التأجير',
            'Pick-up date'            => 'تاريخ الاستلام',
            'Drop-off date'           => 'تاريخ التسليم',
        ],
        'tr' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'Sayın',
            'Booking Reference Number:' => ['Rezervasyon Referans Numarası:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'Onay No:',
            'Desk telephone no'         => 'Ofis telefon no.',
            'or similar'                => 'ya da benzer araçlar',
            'equipment'                 => ['Kapılar', 'Otomatik şanzıman', 'Klima'],
            'Supplier:'                 => 'Tedarikçi:',
            // Block about Transaction
            'Transaction Information' => 'İşlem Bilgisi',
            'Transaction Currency'    => 'İşlem Para Birimi',
            'Transaction Amount'      => 'İşlem Miktarı',
            'Rental details'          => 'Kiralama ayrıntıları',
            'Pick-up date'            => 'Teslim alma tarihi',
            'Drop-off date'           => 'Bırakma tarihi',
        ],
        'he' => [
            // 'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            'Dear'                      => 'שלום',
            'Booking Reference Number:' => ['Booking Reference Number:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            'Confirmation no.'          => 'מס\' אישור',
            'Desk telephone no'         => 'מספר הטלפון של הדלפק',
            'or similar'                => 'או דומה',
            'equipment'                 => ['דלתות', 'תיבת הילוכים אוטומטית', 'מיזוג אוויר'],
            'Supplier:'                 => 'ספק:',
            // Block about Transaction
            'Transaction Information' => 'מידע על העסקה',
            'Transaction Currency'    => 'מטבע העסקה',
            'Transaction Amount'      => 'סכום העסקה',
            'Rental details'          => 'פרטי השכרה',
            'Pick-up date'            => 'תאריך איסוף',
            'Drop-off date'           => 'תאריך החזרה',
        ],
        'en' => [
            'cancelSubjectRe' => 'Your cancellation request was successful – (?<conf>[-A-Z\d]{5,})',
            // 'Dear' => '',
            'Booking Reference Number:' => ['Booking Reference Number:'],
            // 'Your booking reference number has changed from' => '', // instead of Booking Reference Number
            // 'to ' => '', // instead of Booking Reference Number
            // 'Confirmation no.' => '',
            // 'Desk telephone no' => '',
            // 'or similar' => '',
            'equipment' => ['Doors', 'Automatic Transmission', 'Air conditioning'],
            // 'Supplier:' => '',

            // Block about Transaction
            // 'Transaction Information' => '',
            // 'Transaction Currency' => '',
            // 'Transaction Amount' => '',
            // 'Rental details' => '',
            // 'Pick-up date' => '',
            // 'Drop-off date' => '',
        ],
    ];

    private $rentalCompanies = [
        'dollar'       => ['DOLLAR'],
        'perfectdrive' => ['BUDGET'],
        'avis'         => ['AVIS'],
        'alamo'        => ['ALAMO'],
        'sixt'         => ['SIXT'],
        'europcar'     => ['EUROPCAR'],
        'rentacar'     => ['ENTERPRISE'],
        'foxrewards'   => ['FOX'],
        'hertz'        => ['HERTZ'],
        'thrifty'      => ['THRIFTY'],
        //        '' => ['ABBYCAR'],
        //        '' => ['CIRCULAR'],
        //        '' => ['DELPASO'],
        //        '' => ['ILHA VERDE'],
    ];

    private $detectSubjects = [
        'nl' => [
            'Uw boekingsbevestiging voor autohuur',
            'Uw annuleringsverzoek is gelukt – ',
        ],
        'da' => ['Bekræftelse af din billejebestilling'],
        'de' => ['Ihre Mietwagen-Buchungsbestätigung'],
        'en' => [
            'Your car rental reservation confirmation',
            'Your car rental booking confirmation',
            'Your car hire booking confirmation',
            'Your travel reminder',
            'Your booking reference number has changed from',
        ],
        'no' => [
            'Din bestillingsbekreftelse for billeie',
            'Din reisepåminnelse',
            'Din reservasjonsbekreftelse for leiebil',
        ],
        'es' => ['Confirmación de su reserva de alquiler de coche'],
        'fr' => ['Votre confirmation de réservation de location de voiture',
            'Rappel de votre voyage - Réf :',
        ],
        'pt' => [
            'O seu lembrete da viagem',
            'A confirmação da sua reserva de aluguer de automóveis',
        ],
        'sv' => [
            'Din bokningsbekräftelse för hyrbil – Ref:',
        ],
        'it' => [
            'La conferma della prenotazione del tuo noleggio auto – Rif:',
            'Il tuo promemoria di viaggio - Rif:',
        ],
        'ko' => [
            '렌터카 예약 확인 – 예약 번호:',
        ],
        'ja' => [
            'トラベルリマインダ – 予約参照番号:',
        ],
        'ar' => [
            'تأكيد حجز سيارتك المستأجرة – مرجع:',
            'رسالة تذكير برحلتك – الرقم المرجعي',
        ],
        'tr' => [
            'Araç kiralama rezervasyon onayınız – Ref:',
        ],
        'he' => [
            'אישור הזמנת השכרת הרכב שלך – מס\' סימוכין',
        ],
    ];

    private $dateRelative = 0;
    private $emailSubject = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@cartrawler.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['from']) && $this->striposAll($headers['from'], $params['from']) === false) {
                continue;
            }

            foreach ($this->detectSubjects as $dSubjects) {
                if ($this->striposAll($headers['subject'], $dSubjects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->emailSubject = $parser->getSubject();

        if ($this->http->XPath->query('//a[contains(@href,".cartrawler.com")]')->length > 0
            || $this->http->XPath->query('//node()[contains(.,"@cartrawler.com")]')->length > 0
        ) {
            return $this->assignLang();
        }

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['bodyLink']) && $this->http->XPath->query('//*[' . $this->contains($params['bodyLink'],
                        '@href') . ']')->length === 0) {
                return $this->assignLang();
            }

            if (!empty($params['body']) && $this->http->XPath->query('//*[' . $this->contains($params['body']) . ']')->length === 0) {
                return $this->assignLang();
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->emailSubject = $parser->getSubject();

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $email->setType('YourCarRentalReservation' . ucfirst($this->lang));
        $providerCode = null;

        foreach (self::$detectProvider as $code => $params) {
            if (!empty($params['body']) && $this->http->XPath->query('//*[' . $this->contains($params['body']) . ']')->length > 0) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['bodyLink']) && $this->http->XPath->query('//*[' . $this->contains($params['bodyLink'], '@href') . ']')->length > 0) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['from']) && $this->striposAll(implode(' ', $parser->getFrom()), $params['from']) !== false) {
                $providerCode = $code;

                break;
            }

            if (!empty($params['imgLogoAlt']) && $this->http->XPath->query('//img[' . $this->eq($params['imgLogoAlt'], '@alt') . ']')->length > 0) {
                $providerCode = $code;

                break;
            }
        }

        if (is_numeric($providerCode)) {
            $providerCode = null;
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
        }

        $this->dateRelative = strtotime($parser->getDate());

        if (!empty($this->dateRelative)) {
            $this->dateRelative = strtotime("-2day", $this->dateRelative);
        }
//        $this->logger->debug('$this->dateRelative = '.print_r( $this->dateRelative,true));

//        $urls = $this->http->XPath->query("//a[contains(normalize-space(),'Manage booking')]/@href");
//        if ($urls->length === 1) {
//            $this->parseDataFromSite();
//            return $email;
//        }

        $this->parseCar($email);

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
        return array_filter(array_keys(self::$detectProvider), function ($v) {
            return (is_numeric($v)) ? false : true;
        });
    }

    private function parseCar(Email $email)
    {
        if (!empty(self::$dictionary[$this->lang]['cancelSubjectRe']) && preg_match("/" . self::$dictionary[$this->lang]['cancelSubjectRe'] . "/", $this->emailSubject, $m)
            && !empty($m['conf'])) {
            $email->ota()->confirmation($m['conf']);

            $car = $email->add()->rental();

            $renterName = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Dear'))}])[1]/ancestor::p[1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[!,.\s:،]*$/mu");

            if (in_array($this->lang, ['ko', 'ja', 'he'])) {
                $renterName = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Dear'))}][1]/ancestor::p[1]", null, true, "/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Dear'))}[,]?\s*$/mu");
            }
            $car->general()->traveller($renterName);

            $car->general()
                ->status('Cancelled')
                ->cancelled();

            return $email;
        }

        $xpathNoEmpty = 'string-length(normalize-space())>1';
        $xpathRenDetails = "//text()[{$this->eq($this->t('Rental details'))}]";

        $email->obtainTravelAgency();

        $car = $email->add()->rental();

        $renterName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number:'))} or {$this->starts($this->t('Your booking reference number has changed from'))}]/preceding::text()[{$this->starts($this->t('Dear'))}][1]/ancestor::p[1]", null, true, "/^{$this->opt($this->t('Dear'))}\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])[!,.\s:،]*$/mu");

        if (in_array($this->lang, ['ko', 'ja', 'he'])) {
            $renterName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number:'))} or {$this->starts($this->t('Your booking reference number has changed from'))}]/preceding::text()[{$this->contains($this->t('Dear'))}][1]/ancestor::p[1]", null, true, "/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Dear'))}[,]?\s*$/mu");

            if (empty($renterName)) {
                $renterName = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number:'))} or {$this->starts($this->t('Your booking reference number has changed from'))}]/preceding::text()[{$this->contains($this->t('Dear'))}][position() < 5][2]/ancestor::p[1]", null, true, "/^\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*{$this->opt($this->t('Dear'))}[,]?\s*$/mu");
            }
        }
        $car->general()->traveller($renterName);

        $bookingReference = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number:'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if (!empty($bookingReference)) {
            $bookingReferenceTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking Reference Number:'))}]", null, true, '/^(.+?)[\s:]*$/');
            $email->ota()->confirmation($bookingReference, $bookingReferenceTitle);
        } elseif ($bookingReference = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking reference number has changed from'))}]", null, true,
            '/^\s*' . $this->opt($this->t('Your booking reference number has changed from')) . '\s+[-A-Z\d]{5,}\s+' . $this->opt($this->t('to')) . '\s+([-A-Z\d]{5,})\s*$/')) {
            $email->ota()->confirmation($bookingReference);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation no.'))}]/following::text()[normalize-space()][1]", null, true, '/^[-A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Confirmation no.'))}]", null, true, '/^(.+?)[\s:]*$/');
            $car->general()->confirmation($confirmation, $confirmationTitle);
        } else {
            $car->general()->noConfirmation();
        }

        $xpathTime = "normalize-space(translate(.,'0123456789','dddddddddd'))='dd:dd'";
        $xpathTable = "//tr[ count(*[{$xpathNoEmpty}])=2 and *[{$xpathNoEmpty}][1]/descendant::text()[{$xpathTime}] and *[{$xpathNoEmpty}][2]/descendant::text()[{$xpathTime}] ]";

        // pickup

        $pickupRoots = $this->http->XPath->query($xpathTable . "/*[{$xpathNoEmpty}][1]/descendant::text()[{$xpathTime}]/ancestor::tr[1]");

        if ($pickupRoots->length === 1) {
            $pickupRoot = $pickupRoots->item(0);

            $pickupLocation = $this->http->FindSingleNode('preceding-sibling::tr[normalize-space()][1]', $pickupRoot);
            $car->pickup()->location($pickupLocation);

            $pickupPhones = $this->http->FindSingleNode("ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty}][1][{$this->starts($this->t('Desk telephone no'))}]", $pickupRoot, true, "/{$this->opt($this->t('Desk telephone no'))}[.:\s]+(.{5,}?)\s*(?:\(ext \d+\))?$/");

            foreach (preg_split('/\s*[,\/]\s*/', $pickupPhones) as $phone) {
                // 866-434-2226, 215-365-4499    |    +385996349333 / +3851 6260 800
                if (preg_match("/^[+(\d]{1,2}[-. \d)(]{5,}[\d)]$/", $phone)) {
                    $car->pickup()->phone($phone);

                    break;
                }
            }

            $fullDatePickup = $this->http->FindSingleNode($xpathRenDetails . "/following::tr[{$this->starts($this->t('Pick-up date'))}]", null, true, '/:\s*(.{6,})$/');

            if ($fullDatePickup) {
                $car->pickup()->date2($fullDatePickup);
            } else {
                $pickupDate = $this->translateDate($this->http->FindSingleNode('ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]', $pickupRoot));
                $pickupTime = $this->http->FindSingleNode('.', $pickupRoot, true, '/^\d{1,2}:\d{2}$/');

                if ($this->dateRelative && $pickupDate && $pickupTime) {
                    $pickupDate = EmailDateHelper::parseDateRelative($pickupDate, $this->dateRelative);
                    $car->pickup()->date(strtotime($pickupTime, $pickupDate));
                }
            }
        }

        // dropoff

        $dropoffRoots = $this->http->XPath->query($xpathTable . "/*[{$xpathNoEmpty}][2]/descendant::text()[{$xpathTime}]/ancestor::tr[1]");

        if ($dropoffRoots->length === 1) {
            $dropoffRoot = $dropoffRoots->item(0);

            $dropoffLocation = $this->http->FindSingleNode('preceding-sibling::tr[normalize-space()][1]', $dropoffRoot);
            $car->dropoff()->location($dropoffLocation);

            $dropoffPhones = $this->http->FindSingleNode("ancestor::tr[1]/following-sibling::tr[{$xpathNoEmpty}][1][{$this->starts($this->t('Desk telephone no'))}]", $dropoffRoot, true, "/{$this->opt($this->t('Desk telephone no'))}[.:\s]+(.{5,}?)\s*(?:\(ext \d+\))?$/");

            foreach (preg_split('/\s*[,\/]\s*/', $dropoffPhones) as $phone) {
                if (preg_match("/^[+(\d]{1,2}[-. \d)(]{5,}[\d)]$/", $phone)) {
                    $car->dropoff()->phone($phone);

                    break;
                }
            }

            $fullDateDropoff = $this->http->FindSingleNode($xpathRenDetails . "/following::tr[{$this->starts($this->t('Drop-off date'))}]", null, true, '/:\s*(.{6,})$/');

            if ($fullDateDropoff) {
                $car->dropoff()->date2($fullDateDropoff);
            } else {
                $dropoffDate = $this->translateDate($this->http->FindSingleNode('ancestor::td[ preceding-sibling::td[normalize-space()] ][1]/preceding-sibling::td[normalize-space()][1]', $dropoffRoot));
                $dropoffTime = $this->http->FindSingleNode('.', $dropoffRoot, true, '/^\d{1,2}:\d{2}$/');

                if ($this->dateRelative && $dropoffDate && $dropoffTime) {
                    $dropoffDate = EmailDateHelper::parseDateRelative($dropoffDate, $this->dateRelative);
                    $car->dropoff()->date(strtotime($dropoffTime, $dropoffDate));
                }
            }
        }

        // carModel
        // carType
        // Tesla Model Y without similar
        $carModel = $this->http->FindSingleNode("//tr[not(.//tr)][{$this->contains($this->t('or similar'))} or contains(., 'Tesla Model Y')]");
        $carType = $this->http->FindSingleNode("//tr[not(.//tr)][{$this->contains($this->t('or similar'))} or contains(., 'Tesla Model Y')]/following-sibling::tr[1][normalize-space()]");
        $car->car()
            ->model($carModel)
            ->type($carType, false, true);

        // carImage

        $carImageUrl = $this->http->FindSingleNode("descendant::img[contains(@src,'/otaimages/')][1]/@src");

        if (!$carImageUrl) {
            $carImageUrl = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Supplier:'))}]/ancestor::tr[ preceding-sibling::tr[{$xpathNoEmpty} or descendant::img] ][1]/preceding-sibling::tr[{$xpathNoEmpty} or descendant::img][1]/descendant::img/@src");
        }

        if (!$carImageUrl) {
            $carImageUrl = $this->http->FindSingleNode("//text()[{$this->contains($this->t('or similar'))}]/ancestor::tr[ following-sibling::tr[{$xpathNoEmpty} or descendant::img] ][1]/following-sibling::tr[count(*)=2]/*[1]/descendant::tr[following-sibling::tr][1]/descendant::img[1]/@src");
        }

        if (preg_match("/^https?:\/\/.*/u", $carImageUrl)) {
            $car->car()->image($carImageUrl);
        }

        // Company

        $patterns['companyUrl'] = '/[^\/]\/([^ \/.]{2,})\.(?:png|jpg|jpeg|gif|bmp|pdf)/i'; // ../car/delpaso_car_hire.pdf?w=90
        $xpathRenCompImg1 = "//text()[{$this->eq($this->t('Supplier:'))}]/ancestor::tr[preceding-sibling::tr or following-sibling::tr][1]/following-sibling::tr[1]/descendant::img[1]";
        $xpathRenCompImg2 = "//text()[{$this->contains($this->t('or similar'))}]/ancestor::tr[ following-sibling::tr[{$xpathNoEmpty} or descendant::img] ][1]/following-sibling::tr[count(*)=2]/*[1]/descendant::tr[following-sibling::tr][1]/../tr[preceding-sibling::tr][last()]/descendant::img[1]";
        $rentalCompany = $this->http->FindSingleNode($xpathRenCompImg1 . "/@alt", null, true, '/^[^_]{2,}$/');

        if (!$rentalCompany) {
            $rentalCompany = $this->http->FindSingleNode($xpathRenCompImg1 . "/@src", null, true, $patterns['companyUrl']);
        }

        if (!$rentalCompany) {
            $rentalCompany = $this->http->FindSingleNode($xpathRenCompImg2 . "/@alt", null, true, '/^[^_]{2,}$/');
        }

        if (!$rentalCompany) {
            $rentalCompany = $this->http->FindSingleNode($xpathRenCompImg2 . "/@src", null, true, $patterns['companyUrl']);
        }

        if (!empty($rentalCompany)) {
            $rentalCompany = str_replace('_', ' ', $rentalCompany);
        }

        $foundRentalProvider = false;

        if (!empty($rentalCompany)) {
            foreach ($this->rentalCompanies as $code => $companyNames) {
                foreach ($companyNames as $name) {
                    if ($name === $rentalCompany) {
                        $car->program()->code($code);
                        $foundRentalProvider = true;

                        break 2;
                    }
                }
            }
        }

        if ($foundRentalProvider == false) {
            $car->extra()->company($rentalCompany);
        }

        // Price

        $xpathTransaction = "//text()[{$this->eq($this->t('Transaction Information'))}]";
        $transactionAmount = $this->http->FindSingleNode($xpathTransaction . "/following::tr[{$this->starts($this->t('Transaction Amount'))}]", null, true, '/:\s*(\d[,.\'\d]*)$/');

        if ($transactionAmount !== null) {
            $transactionCurrency = $this->http->FindSingleNode($xpathTransaction . "/following::tr[{$this->starts($this->t('Transaction Currency'))}]", null, true, '/:\s*([A-Z]{3})$/');
            $car->price()
                ->currency($transactionCurrency)
                ->total($this->normalizeAmount($transactionAmount));
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['cancelSubjectRe']) && preg_match("/" . $phrases['cancelSubjectRe'] . "/", $this->emailSubject, $m)
                && !empty($m['conf'])
            ) {
                $this->lang = $lang;

                return true;
            }

            if (empty($phrases['Booking Reference Number:']) || empty($phrases['equipment'])) {
                continue;
            }
            $conf = $this->contains($phrases['Booking Reference Number:']);

            if (isset($phrases['Your booking reference number has changed from'])) {
                $conf .= ' or ' . $this->contains($phrases['Your booking reference number has changed from']);
            }

            if ($this->http->XPath->query("//node()[$conf]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['equipment'])}]")->length > 0
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
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

    private function translateDate($date)
    {
        if (preg_match("#^\s*\d+\s+([[:alpha:]]+)[.]?\s*$#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                return str_replace($m[1], $en, $date);
            }
        } elseif (preg_match("#^\s*(\d+)\s+(\d+)\s*(월|月)\s*$#u", $date, $m)) {
            // 18 5月
            return $m[1] . ' ' . date("F", strtotime("2020-$m[2]-1"));
        }

        return $date;
    }

//    private function parseDataFromSite()
//    {
//        $browser = new \HttpBrowser('none', new \CurlDriver());
//        $browser->GetURL('https://car-hire.ryanair.com/support?email=SCHWARZ-TOBIAS@OUTLOOK.DE&uniqueid=CY786186240&lang=EN-EN&_$ja=tsid:70349%7Ccid:1470980%7Ccgid:147098028993241%7Ccrid:803377041%7Cccgn:manage-booking&utm_source=CRM&utm_medium=Email&utm_campaign=Confirmation+Email&utm_content=manage+booking');
//        if ($iframe = $browser->FindSingleNode("//iframe[@id='helpcenter_iframe']/@src")) {
//            $browser->NormalizeURL($iframe);
//            $browser->GetURL($iframe);
//        }
//        if (!$browser->ParseForm('j_id0:customLogin'))
//        {
//            return false;
//        }
//
//        $inputs = [
//            'AJAXREQUEST' => 'j_id0:customLogin:j_id13',
//            'j_id0:customLogin' => 'j_id0:customLogin',
//            'j_id0:customLogin:emailAddress' => '',
//            'j_id0:customLogin:bookingId' => '',
//            'j_id0:customLogin:pickUpDate' => '',
//            'com.salesforce.visualforce.ViewState' => $browser->FindSingleNode("//input[@id='com.salesforce.visualforce.ViewState']/@value"),
//            'com.salesforce.visualforce.ViewStateVersion' => $browser->FindSingleNode("//input[@id='com.salesforce.visualforce.ViewStateVersion']/@value"),
//            'com.salesforce.visualforce.ViewStateMAC' => $browser->FindSingleNode("//input[@id='com.salesforce.visualforce.ViewStateMAC']/@value"),
//            'com.salesforce.visualforce.ViewStateCSRF' => $browser->FindSingleNode("//input[@id='com.salesforce.visualforce.ViewStateCSRF']/@value"),
//            'GoTo' => '',
//            'emailFromReferrer' => 'schwarz-tobias@outlook.de',
//            'j_id0:customLogin:j_id14' => 'j_id0:customLogin:j_id14',
//            'language' => '',
//            'uniqueId' => 'cy786186240',
//        ];
//        foreach ($inputs as $input => $value) {
//            $browser->SetInputValue($input, $value);
//        }
//        $browser->PostForm();
//        return $browser->Response;
//    }
}
