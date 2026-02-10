<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OverviewHotel extends \TAccountChecker
{
    use \PriceTools;

    public $mailFiles = "expedia/it-10054199.eml, expedia/it-16086869.eml, expedia/it-184827459.eml, expedia/it-221138585.eml, expedia/it-264749780.eml, expedia/it-268456739.eml, expedia/it-270327458.eml, expedia/it-2840031.eml, expedia/it-2854482.eml, expedia/it-2931632.eml, expedia/it-3135425.eml, expedia/it-3268488.eml, expedia/it-3294885.eml, expedia/it-3410173.eml, expedia/it-34265523.eml, expedia/it-3466363.eml, expedia/it-35525312.eml, expedia/it-36213516.eml, expedia/it-42722884.eml, expedia/it-4921452.eml, expedia/it-51109268.eml, expedia/it-5625480.eml, expedia/it-5774706.eml, expedia/it-5785046.eml, expedia/it-6147469.eml, expedia/it-999.eml"; // +1 bcdtravel(html)[fi]

    public static $headers = [
        'orbitz' => [
            'from' => ['orbitz.com'],
            'subj' => [
                'Orbitz travel confirmation',
            ],
        ],
        'lastminute' => [
            'from' => ['email.lastminute.com.au'],
            'subj' => [
                'lastminute.com.au travel confirmation',
            ],
        ],
        'travelocity' => [
            'from' => ['e.travelocity.com'],
            'subj' => [
                'Travelocity travel confirmation',
            ],
        ],
        'ebookers' => [
            'from' => [
                'mailer.ebookers.com',
                'mailer.ebookers.fi',
            ],
            'subj' => [
                'ebookers travel confirmation',
                'ebookers-Reisebestätigung -',
                'ebookersn matkavahvistus -',
                'Votre confirmation de voyage ebookers -',
            ],
        ],
        'cheaptickets' => [
            'from' => ['cheaptickets.com'],
            'subj' => [
                'CheapTickets travel confirmation',
            ],
        ],
        'hotels' => [
            'from' => ['@support-hotels.com', 'hotels@eg.hotels.com'],
            'subj' => [
                'en' => 'Hotels.com travel confirmation',
                'pt' => 'Confirmação da viagem com a Hoteis.com em',
                'tr' => 'Hotels.com seyahat onayı',
                'th' => 'ฝ่ายบริการลูกค้าของ Hotels.com',
                'hu' => 'Hotels.com ügyfélszolgálata',
                'el' => 'Υποστήριξη πελατών Hotels.com',
                'fr' => 'Confirmation de voyage Hotels.com',
                'zh' => 'Hotels.com 行程確認',
            ],
        ],
        'hotwire' => [
            'from' => ['noreply@Hotwire.com'],
            'subj' => [
                'en' => 'Hotwire travel confirmation',
            ],
        ],
        'expedia' => [
            'from' => ['expediamail.com'],
            'subj' => [
                "Expedia travel confirmation",
                "Conferma di viaggio Expedia",
                "Confirmación de viaje de Expedia - ",
                'Expedia-Reisebestätigung',
                "nl" => 'Reisbevestiging van Expedia',
                "pt" => "Confirmação de viagem da Expedia -",
                "fr" => "Votre confirmation de voyage Expedia",
                "Confirmation de voyage Expedia",
                "sv" => "Resebekräftelse från Expedia",
                "zh" => "Expedia 智遊網 行程確認",
            ],
        ],
    ];

    public static $dictionary = [
        'tr' => [
            "Itinerary #"       => "Seyahat Programı Numarası",
            "Hotel overview"    => "Otele genel bakış",
            "Reservation dates" => "Rezervasyon tarihleri",
            "Check-in time"     => "Giriş saati",
            "Check-out time"    => "Çıkış saati",
            "Guests"            => "Misafirler",
            //"Reserved for"      => "",
            "adult"             => "yetişkin",
            //			"child"=>"NOTTRANSLATED",
            "Room"                      => "Oda",
            "Room requests"             => "Oda istekleri",
            "Cancellations and changes" => "İptaller ve değişiklikler",
            "Price summary"             => "Fiyat özeti",
            //"Room price"                => "",
            "night"                     => "gece",
            "Taxes & fees"              => "Vergiler:",
            //			"Taxes"=>"NOTTRANSLATED",
            // "discount" => "",
            "Total"             => ["Toplam:"],
            "#defaultCurrency#" => "#Aksi belirtilmediği sürece teklifteki fiyatlar (.+) cinsindendir#",
        ],
        'it' => [
            "Itinerary #"       => "N° di itinerario",
            "Hotel overview"    => "Panoramica hotel",
            "Reservation dates" => "Date prenotate",
            "Check-in time"     => "Orario del check-in",
            "Check-out time"    => "Ora di partenza",
            "Guests"            => "Ospiti",
            "Reserved for"      => "Prenotata per",
            "adult"             => "adult",
            //			"child"=>"NOTTRANSLATED",
            "Room"                      => "Camera",
            "Room requests"             => "Richieste relative alla camera",
            "Cancellations and changes" => "Cancellazioni e modifiche",
            "Price summary"             => "Riepilogo prezzi",
            "Room price"                => "Prezzo camera",
            "night"                     => "notti",
            "Taxes & fees"              => "Tasse e oneri di servizio",
            //			"Taxes"=>"NOTTRANSLATED",
            // "discount" => "",
            "Total"             => ["Totale", "Subtotale"],
            "#defaultCurrency#" => "#Se non diversamente specificato, le tariffe vengono calcolate in (.+?)(\.|$)#",
        ],
        'vi' => [
            "Itinerary #"       => "Số lịch trình",
            "Hotel overview"    => "Tổng quan Khách sạn",
            "Reservation dates" => "Ngày đặt phòng",
            "Check-in time"     => "Giờ nhận phòng",
            "Check-out time"    => "Giờ trả phòng",
            "Guests"            => "Khách",
            "Reserved for"      => "Đặt cho",
            "adult"             => "người lớn",
            //			"child"=>"NOTTRANSLATED",
            "Room"                      => "Phòng",
            "Room requests"             => "Yêu cầu phòng",
            "Cancellations and changes" => "Hủy và thay đổi",
            "Price summary"             => "Tóm tắt giá",
            "Room price"                => "Giá phòng",
            "night"                     => "đêm",
            "Taxes & fees"              => "Thuế & phí",
            //			"Taxes"=>"NOTTRANSLATED",
            // "discount" => "",
            "Total" => ["Tổng"],
            //            "#defaultCurrency#" => "##",
        ],
        'es' => [ // + Es_MX
            "Itinerary #"               => ["N.º de itinerario", "No. de itinerario"],
            "Hotel overview"            => ["Descripción del hotel", "Información general del hotel", 'Aspectos generales del hotel', "Hotel overview", "Aspectos generales de la propiedad",
                "Información general de la propiedad", ],
            "Reservation dates"         => ["Fechas de reserva", "Fechas de la reservación"],
            "Check-in time"             => ["Horario de entrada", "Horario para hacer el check-in", 'Horario de check-in', "Hora de entrada"],
            "Check-out time"            => ["Horario de salida", "Horario para hacer el check-out", 'Horario de check-out', "Hora de salida"],
            "Guests"                    => ["Personas", "Huéspedes", "Invitados"],
            "Reserved for"              => ["Reserva para", "Reservado para"],
            "adult"                     => "adulto",
            "child"                     => "niño",
            "Room"                      => "Habitación",
            "Room requests"             => "Solicitudes para la habitación",
            "Cancellations and changes" => ["Cancelaciones y cambios", "Cambios y cancelaciones"],
            "Price summary"             => "Resumen del precio",
            "Room price"                => "Precio de la habitación",
            "night"                     => "noches",
            "Taxes & fees"              => "Tasas e impuestos",
            //			"Taxes"=>"NOTTRANSLATED",
            // "discount" => "",
            "Total"                        => "Total",
            "For Expedia+ rewards members" => ["For Expedia+ rewards members", "por este viaje"],
            "points"                       => ["points", "puntos"],
            "#defaultCurrency#"            => "#A menos que se indique lo contrario, las tarifas se presupuestan en (.+?)(\.|$)#",
        ],
        'de' => [ // it-5774706.eml
            "Itinerary #"                  => ["Reiseplannummer", "Reis eplannummer", "Reiseplannr."],
            "Hotel overview"               => ["Einzelheiten Ihrer Hotelbuchung", "Einzelheiten Ihrer Unterkunftsbuchung", "Einzelheiten deiner Hotelbuchung"],
            "Reservation dates"            => "Buchungsdaten",
            "Check-in time"                => "Check-in-Zeit",
            "Check-out time"               => "Check-out-Zeit",
            "Guests"                       => ["Reisende", "Gäste"],
            "Reserved for"                 => ["Gebucht für", "Reserviert für"],
            "adult"                        => "Erwachsene",
            "child"                        => "Kinder",
            "Room"                         => ["Zimmer", "Schlafbereich"],
            "Room requests"                => "Wünsche bezüglich des Zimmers",
            "Cancellations and changes"    => "Stornierungen und Änderungen",
            "Price summary"                => "Preisübersicht",
            "Room price"                   => "Zimmerpreis",
            "night"                        => ["Nacht", "Nächte"],
            "Taxes & fees"                 => "Steuern und Gebühren",
            "Taxes"                        => ["Steuern:", "Steuern::"],
            "discount"                     => "Preise enthalten",
            "Total"                        => ["Insgesamt", 'Gesamt', 'Gesamtpreis (geschätzt)', 'Gesamtpreis:'],
            "For Expedia+ rewards members" => ["For Expedia+ rewards members", "für diese Reise"],
            "points"                       => ["points", "Punkte"],
            "#defaultCurrency#"            => "#Sofern nicht anders vermerkt, sind die Preise in (.+?) angegeben#",
        ],
        'nl' => [
            "Itinerary #"               => "Reisplannummer",
            "Hotel overview"            => "Hoteloverzicht",
            "Reservation dates"         => "Boekingsdatums",
            "Check-in time"             => "Inchecktijd",
            "Check-out time"            => "Uitchecktijd",
            "Guests"                    => "Gasten",
            "Reserved for"              => "Geboekt voor",
            "adult"                     => "volwassene",
            "child"                     => "kinderen",
            "Room"                      => "Kamer",
            "Room requests"             => "Kamervoorkeuren",
            "Cancellations and changes" => "Annuleringen en wijzigingen",
            "Price summary"             => "Prijsoverzicht",
            "Room price"                => "Kamerprijs",
            "night"                     => "nachten",
            "Taxes & fees"              => "Belastingen & toeslagen",
            //			"Taxes" => "NOTTRANSLATED",
            // "discount" => "",
            "Total"             => "Totaal",
            "#defaultCurrency#" => "#Tenzij anders aangegeven, worden tarieven vermeld in (.+?)'s#",
        ],
        'fi' => [
            'Itinerary #'       => 'Matkasuunnitelman nro',
            "Hotel overview"    => "Hotellin yleiskuvaus",
            "Reservation dates" => "Varauspäivät",
            "Check-in time"     => "Sisäänkirjautumisaika",
            "Check-out time"    => "Uloskirjautumisaika",
            "Guests"            => ["Vieraat", "Asiakkaat"],
            "Reserved for"      => "Varaaja:",
            "adult"             => "aikuista",
            //			"child" => "NOTTRANSLATED",
            "Room" => "Huone",
            //            "Room requests" => "",
            "Cancellations and changes" => "Peruutukset ja muutokset",
            "Taxes & fees"              => "Verot ja maksut",
            "Taxes"                     => "Verot",
            // "discount" => "",
            "Total"                     => "Yhteensä",
            "#defaultCurrency#"         => "#Mikäli toisin ei ole ilmoitettu, hintojen valuutta on (.+?)(\.|$)#",
        ],
        'zh' => [
            'Itinerary #'       => ['行程編號', "行程单编号"],
            "Hotel overview"    => ["酒店概覽", "酒店概况", "飯店簡介"],
            "Reservation dates" => ["住宿日期", "入住日期", "预订日期"],
            "Check-in time"     => ["入住時間", "入住时间"],
            "Check-out time"    => ["退房時間", "退房时间"],
            "Guests"            => "旅客",
            "Reserved for"      => "以 ",
            "adult"             => "位成人",
            //			"child" => "NOTTRANSLATED",
            "Room"                         => "客房",
            "Room requests"                => "客房要求",
            "For Expedia+ rewards members" => ["此趟行程"],
            "points"                       => "點數",
            "Cancellations and changes"    => "取消和更改",
            "Taxes & fees"                 => "稅金和費用",
            "Taxes"                        => "稅項及附加費",
            // "discount" => "",
            "Total"                        => ["合計", "總價"],
            //            "#defaultCurrency#" => "#(.+?)(\.|$)#",
        ],
        'da' => [
            'Itinerary #'       => 'Rejseplansnummer',
            "Hotel overview"    => "Hoteloversigt",
            "Reservation dates" => "Reservationsdatoer",
            "Check-in time"     => "Indtjekningstidspunkt",
            "Check-out time"    => "Udtjekningstidspunkt",
            "Guests"            => "Gæster",
            "Reserved for"      => "Reserveret til",
            "adult"             => "voksne",
            //			"child" => "NOTTRANSLATED",
            "Room" => "Værelse",
            //            "Room requests" => "",
            "Cancellations and changes" => "Afbestillinger og ændringer",
            //			"Taxes & fees" => "NOTTRANSLATED",
            "Taxes" => "Skatter og gebyrer",
            // "discount" => "",
            "Total" => ["I alt", "Pris i alt"],
            //            "#defaultCurrency#" => "#(.+?)(\.|$)#",
        ],
        'pt' => [
            'Itinerary #'                  => 'Nº do itinerário',
            "Hotel overview"               => ["Visão geral do hotel", 'Informações do hotel'],
            "Reservation dates"            => "Datas da reserva",
            "Check-in time"                => "Horário de check-in",
            "Check-out time"               => "Horário de check-out",
            "Guests"                       => "Hóspedes",
            "Reserved for"                 => "Reservado para",
            "adult"                        => "adulto",
            "child"                        => "criança",
            "Room"                         => "Quarto",
            "Room requests"                => "Solicitações de quarto",
            "Cancellations and changes"    => "Cancelamentos e alterações",
            "Taxes & fees"                 => ["Impostos e taxas ", "Impostos e taxas:"],
            "Taxes"                        => "Impostos",
            // "discount" => "",
            "Total"                        => "Total",
            "For Expedia+ rewards members" => 'com esta viagem',
            "points"                       => 'pontos',
            "#defaultCurrency#"            => "#A menos que especificado de outra forma, as tarifas são cotadas em (.+?)(\.|$)#",
        ],
        'no' => [
            'Itinerary #'               => 'Reiserutenr.',
            "Hotel overview"            => "Hotelloversikt",
            "Reservation dates"         => "Bestillingsdatoer",
            "Check-in time"             => "Innsjekkingstidspunkt",
            "Check-out time"            => "Utsjekkingstidspunkt",
            "Guests"                    => "Gjester",
            "Reserved for"              => "Reservert for",
            "adult"                     => "voksne",
            "child"                     => "barn",
            "Room"                      => "Rom",
            "Room requests"             => "Romforespørsler",
            "Cancellations and changes" => "Avbestillinger og endringer",
            "Taxes & fees"              => "Skatter og avgifter",
            //			"Taxes" => "",
            // "discount" => "",
            "Total" => "Totalt",
            //            "#defaultCurrency#" => "#(.+?)(\.|$)#",
        ],
        'fr' => [
            'Itinerary #'       => ['N° de voyage', 'Nº d’itinéraire', 'Itinéraire nº'],
            "Hotel overview"    => ["Aperçu de l'hôtel", "Aperçu de l’hôtel", 'Aperçu de l’hébergement'],
            "Reservation dates" => "Dates de réservation",
            "Check-in time"     => ["Heure d'arrivée", "Heure d’arrivée"],
            "Check-out time"    => "Heure de départ",
            "Guests"            => "Clients",
            "Reserved for"      => ["Réservation pour", "Réservé pour"],
            "adult"             => "adult",
            // "child" => "NOTTRANSLATED",
            "Room" => "Chambre",
            //            "Room requests" => "",
            "Cancellations and changes" => ["Annulations et modifications", "Règles et restrictions"],
            "Price summary"             => ["Récapitulatif du prix", "Sommaire du prix"],
            "Room price"                => "Prix de la chambre",
            "night"                     => "nuits",
            "Taxes & fees"              => "Taxes et frais",
            // "Taxes" => "",
            // "discount" => "",
            "Total" => ["Total estimé :", "Total"],
            //            "#defaultCurrency#" => "#(.+?)(\.|$)#",
            "CancellationStarts" => ['Annulation sans frais'],
        ],
        'en' => [
            "Hotel overview"               => ["Hotel overview", "Property overview", "Overview"],
            "Itinerary #"                  => ["Itinerary #", "Hotel itinerary #"],
            "Reservation dates"            => ["Reservation dates", "Travel dates"],
            "Check-in time"                => "Check-in time",
            "Guests"                       => ["Guests", "Guest Confirmation"],
            "night"                        => ["night", "nights"],
            "For Expedia+ rewards members" => ["For Expedia+ rewards members", "for this trip"],
            "Cancellations and changes"    => ["Cancellations and changes", "Cancellations and Changes"],
            "Taxes & fees"                 => ["Taxes & fees", "Tax and Service Fees"],
            // "discount" => "",
            "Total"                        => ["Total paid:", "Total due today:", "Total Lodging:", "Total"],
            //            "#defaultCurrency#" => "#(.+?)(\.|$)#",
            //"Price summary" => ["Price summary", "Price breakdown"],
        ],
        'sv' => [
            'Itinerary #'       => ['Resplansnummer'],
            "Hotel overview"    => ["Hotellöversikt"],
            "Reservation dates" => "Bokningsdatum",
            "Check-in time"     => ["Incheckningstid"],
            "Check-out time"    => "Utcheckningstid",
            "Guests"            => "Gäster",
            "Reserved for"      => ["Bokat för"],
            "adult"             => ["vuxna", "vuxen"],
            "night"             => ["nätter"],
            // "child" => "NOTTRANSLATED",
            "Room"                      => "Rum",
            "Room requests"             => "Rumsförfrågningar",
            "Cancellations and changes" => "Avbokningar och ändringar",
            // "Taxes & fees" => "NOTTRANSLATED",
            "Taxes"             => "Skatter",
            "discount"          => "En rabatt på",
            "Total"             => "Totalt",
            "#defaultCurrency#" => "#Om inget annat anges visas priserna i (.+?)(\.|$)#",
        ],
        'ja' => [
            "Itinerary #"       => '旅程番号',
            "Hotel overview"    => 'ホテルの概要',
            "Reservation dates" => '宿泊日',
            "Check-in time"     => 'チェックイン時間',
            "Check-out time"    => 'チェックアウト時間',
            "Guests"            => 'お客様',
            "Reserved for"      => 'ご予約者名 :',
            "adult"             => '大人',
            //			"child" => 'NOTTRANSLATED',
            "Room"                      => '部屋',
            "Room requests"             => "部屋に関するリクエスト",
            "Cancellations and changes" => 'キャンセルおよび変更',
            "Price summary"             => '料金の概要',
            "Room price"                => '部屋料金',
            "night"                     => '泊',
            "Taxes & fees"              => '税およびサービス料',
            //			"Taxes" => 'NOTTRANSLATED',
            // "discount" => "",
            "Total"                        => '合計',
            "For Expedia+ rewards members" => '獲得',
            "points"                       => 'ポイント',
            "#defaultCurrency#"            => "#特に指定されない限り、(?:表記料金の単位は|表示金額の元の通貨は日本) *(.+?) *です。#u",
        ],
        'ko' => [
            "Itinerary #"       => ['일정 번호', '일정 번호:'],
            "Hotel overview"    => '호텔 정보',
            "Reservation dates" => '예약 날짜',
            "Check-in time"     => '체크인 시간',
            "Check-out time"    => '체크아웃 시간',
            "Guests"            => '숙박객',
            "Reserved for"      => '예약자:',
            "adult"             => '성인',
            //			"child" => 'NOTTRANSLATED',
            "Room"                      => '객실',
            "Room requests"             => "객실 요청 사항",
            "Cancellations and changes" => '취소 및 변경',
            "Price summary"             => '요금 요약',
            "Room price"                => '객실 요금',
            "night"                     => '박',
            "Taxes & fees"              => '세금 및 수수료',
            //			"Taxes" => 'NOTTRANSLATED',
            // "discount" => "",
            "Total"                        => '총액',
            "For Expedia+ rewards members" => '獲得',
            "points"                       => 'ポイント',
            "#defaultCurrency#"            => "#달리 지정된 경우를 제외하고 요금은 (.+) 통화로 계산된 금액입니다. #u",
        ],

        'th' => [
            "Itinerary #"       => ['แผนการเดินทาง #'],
            "Hotel overview"    => 'รายละเอียดโรงแรม',
            "Reservation dates" => 'วันเข้าพัก',
            "Check-in time"     => 'เวลาเช็คอิน',
            "Check-out time"    => 'เวลาเช็คเอาท์',
            "Guests"            => 'ผู้เข้าพัก',
            "Reserved for"      => 'จองสำหรับ',
            "adult"             => 'ผู้ใหญ่',
            //			"child" => 'NOTTRANSLATED',
            "Room"                      => 'ห้องพัก',
            "Room requests"             => "คำขอเกี่ยวกับห้องพัก",
            "Cancellations and changes" => 'การยกเลิกและการเปลี่ยนแปลง',
            "Price summary"             => 'สรุปข้อมูลราคา',
            "Room price"                => 'ราคาห้องพัก:',
            "night"                     => 'คืน',
            "Taxes & fees"              => 'ภาษี:',
            //			"Taxes" => 'NOTTRANSLATED',
            "discount"                     => "ส่วนลดที่ได้รับ:",
            "Total"                        => 'ยอดรวม:',
            //"For Expedia+ rewards members" => '',
            //"points"                       => '',
            //"#defaultCurrency#"            => "#u",
        ],
        'hu' => [
            "Itinerary #"       => ['Foglalási visszaigazolás száma'],
            "Hotel overview"    => 'Hotel áttekintése',
            "Reservation dates" => 'Lefoglalt dátumok',
            "Check-in time"     => 'Bejelentkezés időpontja',
            "Check-out time"    => 'Kijelentkezés időpontja',
            "Guests"            => 'Vendégek',
            "Reserved for"      => 'Lefoglalva',
            "adult"             => 'felnőtt',
            //			"child" => 'NOTTRANSLATED',
            "Room"                      => 'Szoba',
            "Room requests"             => "Szobával kapcsolatos kérések",
            "Cancellations and changes" => 'Lemondás és módosítás',
            "Price summary"             => 'Árösszesítő',
            "Room price"                => 'Szobaár:',
            "night"                     => 'éjszaka',
            "Taxes & fees"              => ['Adók::', 'Adók és díjak:'],
            //			"Taxes" => 'NOTTRANSLATED',
            //"discount" => "",
            "Total"                        => 'Végösszeg:',
            //"For Expedia+ rewards members" => '',
            //"points"                       => '',
            //"#defaultCurrency#"            => "#u",
        ],
        'el' => [
            "Itinerary #"       => ['Αρ. δρομολογίου'],
            "Hotel overview"    => 'Επισκόπηση ξενοδοχείου',
            "Reservation dates" => 'Ημερομηνίες κράτησης',
            "Check-in time"     => 'Ώρα check-in',
            "Check-out time"    => 'Ώρα check-out',
            "Guests"            => 'Επισκέπτες',
            "Reserved for"      => 'Έγινε κράτηση για',
            "adult"             => 'ενήλικας',
            //			"child" => 'NOTTRANSLATED',
            "Room"                      => 'Δωμάτιο',
            "Room requests"             => "Αιτήματα για δωμάτια",
            "Cancellations and changes" => 'Ακυρώσεις και αλλαγές',
            "Price summary"             => 'Περίληψη τιμής',
            "Room price"                => 'Τιμή δωματίου',
            "night"                     => 'διανυκτερεύσεις',
            //"Taxes & fees"              => [''],
            //			"Taxes" => 'NOTTRANSLATED',
            //"discount" => "",
            "Total"                        => 'Σύνολο:',
            //"For Expedia+ rewards members" => '',
            //"points"                       => '',
            //"#defaultCurrency#"            => "#u",
        ],
    ];

    public $lang = 'en';

    protected $code = null;

    protected $bodies = [
        'lastminute' => [
            '//img[contains(@alt,"lastminute.com")]',
            '//a[contains(.,"lastminute.com")]/parent::*[contains(.,"Collected by")]',
        ],
        'chase' => [
            '//img[contains(@src,"chase.com")]',
            'Chase Travel',
        ],
        'cheaptickets' => [
            '//img[contains(@src,"cheaptickets.com")]',
            'cheaptickets.com',
            'Call CheapTickets customer',
        ],
        'ebookers' => [
            '//img[contains(@alt,"ebookers.com")]',
            'Collected by ebookers',
            'Maksun veloittaa ebookers',
        ],
        'hotels' => [
            '//img[contains(@src,"Hotels.com")]',
            "Hotels.com",
        ],
        'hotwire' => [
            '//img[contains(@alt,"Hotwire.com")]',
            'Or call Hotwire at',
        ],
        'mrjet' => [
            '//img[contains(@src,"MrJet.se")]',
            'MrJet.se',
        ],
        'orbitz' => [
            '//img[contains(@alt,"Orbitz.com")]',
            'This Orbitz Itinerary was sent from',
            'Call Orbitz customer care',
        ],
        'rbcbank' => [
            '//img[contains(@src,"rbcrewards.com")]',
            'rbcrewards.com',
        ],
        'travelocity' => [
            '//img[contains(@src,"travelocity.com")]',
            'travelocity.com',
            'Collected by Travelocity',
        ],
        'expedia' => [
            '//img[contains(@alt,"expedia.com")]',
            'expedia.com',
            'expediamail.com',
        ],
    ];

    protected $reBody2 = [
        'it'    => 'Panoramica hotel',
        'vi'    => 'Giờ nhận phòng',
        'es'    => 'Fechas',
        'de'    => 'Buchungsdaten',
        'nl'    => 'Zie live updates voor je reisplan',
        'nl2'   => 'Inchecktijd',
        'fi'    => 'Sisäänkirjautumisaika',
        'zh'    => '入住時間',
        'zh2'   => '酒店概况',
        'zh3'   => '飯店簡介',
        'da'    => 'Indtjekningstidspunkt',
        'pt'    => 'Visão geral do hotel',
        'pt2'   => 'Veja atualizações em tempo real de seu itinerário a qualquer hora, onde estiver',
        'pt3'   => 'Informações do hotel',
        'no'    => 'Innsjekking og utsjekking',
        'fr'    => "Aperçu de l'hôtel",
        'fr2'   => "Aperçu de l’hôtel",
        'fr3'   => "Aperçu de l’hébergement",
        'sv'    => 'Incheckningstid',
        'ja'    => '宿泊日',
        'ko'    => '체크인 시간',
        'ko2'   => '객실 세부 정보',
        'th'    => 'วันเข้าพัก',
        'hu'    => 'Lefoglalt dátumok',
        'el'    => 'Ημερομηνίες κράτησης',
        'en'    => 'Check-in',
    ];

    private $reBody = [
        'CheapTickets',
        'ebookers',
        'Hotels.com',
        'Hotwire',
        'MrJet',
        'Orbitz',
        'RBC Travel',
        'Travelocity',
        'Expedia',
        'lastminute',
    ];
    private $patterns = [
        'time'          => '(?:kl\.\s*)?(noon|meio-dia|正午|dél|mediodía|öğlen|\d{1,2}\s*[AP]M|\d{1,2}[:.h]\d{2}(?:\s*[AP]M)?|midi|\d+ h \d+|\d+ ?h|\d{3,4}|\d{1,2}\s*μ\.*μ\.|\d{2}|\d{1,2}[:.h]\d{2}(?:\s*[ap])?)(?:\s*Uhr|\s*uur|\s*hrs|\s*น\.|\s*óra|\s*το μεσημέρι)?',
        'travellerName' => '[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom || $bySubj) {
                $this->code = $code;
            }

            if ($bySubj) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() . $parser->getPlainBody();

        foreach ($this->reBody as $s) {
            if (stripos($body, $s) === false) {
                $first = true;
            }
        }

        if (empty($first)) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Hotel overview']) && $this->http->XPath->query("(//node()[{$this->contains($dict['Hotel overview'])}])[1]")->length > 0
                && !empty($dict['Check-in time']) && $this->http->XPath->query("(//node()[{$this->contains($dict['Check-in time'])}])[1]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (empty($parser->getHTMLBody()) && !empty($parser->getPlainBody()) && 0 === strpos($parser->getPlainBody(), '<html>')) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        $this->lang = null;

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Hotel overview']) && $this->http->XPath->query("(//node()[{$this->contains($dict['Hotel overview'])}])[1]")->length > 0
                && !empty($dict['Check-in time']) && $this->http->XPath->query("(//node()[{$this->contains($dict['Check-in time'])}])[1]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            return $email;
        }

        if ($code = $this->getProvider($parser)) {
            $email->setProviderCode($code);
        }
        $this->parseHtml($email);

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

    private function parseHtml(Email $email): void
    {
        // Travel Agency
        $email->obtainTravelAgency();

        $h = $email->add()->hotel();

        if ($conf = trim($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Itinerary #")) . "]/following::text()[normalize-space(.)][1])[1]"), ')( ')) {
            $h->general()->confirmation($conf);
        } else {
            $h->general()->noConfirmation();
        }

        $xpathTdThTable = '(self::td or self::th or self::table)';
        $xpathBold = '(self::b or self::strong)';

        // Hotel Name
        $hotelName = $this->http->FindSingleNode('(//tr[' . $this->eq($this->t('Hotel overview')) . ']/following-sibling::tr//img//ancestor::td[1]/following-sibling::td[normalize-space()]//tr[not(.//tr) and normalize-space()])[1][not(' . $this->contains($this->t("Reservation dates")) . ')]');

        if (empty($hotelName)) {
            // it-51109268.eml
            $hName_temp = $this->http->FindSingleNode("//h3[{$this->eq($this->t('Hotel overview'))}]/ancestor::*[{$xpathTdThTable}][ following-sibling::*[{$xpathTdThTable}][normalize-space()] ][1]/following-sibling::*[{$xpathTdThTable}][normalize-space()][1]/descendant::text()[normalize-space()][1]/ancestor::p[1]");

            if ($this->http->XPath->query("//text()[{$this->contains($hName_temp)}]")->length > 1) {
                $hotelName = $hName_temp;
            }
        }

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("//tr[{$this->eq($this->t("Hotel overview"))}]/following-sibling::tr[ following-sibling::tr[({$this->contains($this->t("Reservation dates"))})] ]/descendant::text()[normalize-space()][1]")
                ?? $this->http->FindSingleNode("//tr[ *[1][normalize-space()='']/descendant::img and *[2]/descendant::text()[{$this->eq($this->t("Map and directions"))}] and following::tr[({$this->contains($this->t("Reservation dates"))})] ]/descendant::text()[normalize-space()][1]")
            ;
        }

        if (empty($hotelName)) {
            $this->logger->debug("seem's junk. or other format or not detect lang");

            return;
        }
        $h->hotel()->name($hotelName);

        // CheckInDate
        // CheckOutDate
        $checkOutDate = $checkInDate = null;
        $dates = $this->http->FindSingleNode("(//tr[{$this->eq($this->t("Reservation dates"))}]/following-sibling::tr[normalize-space()][1])[1]");

        if (!$dates) {
            // it-51109268.eml
            $dates = $this->http->FindSingleNode("//p[{$this->eq($this->t("Reservation dates"))}]/following-sibling::p[normalize-space()][1]");
        }
        $reservationDates = preg_split('/[ ]+-[ ]+/u', $dates);

        if (count($reservationDates) === 2) {
            $checkInDate = $reservationDates[0];
            $checkOutDate = $reservationDates[1];
        }

        $checkInDate = $this->normalizeDate($checkInDate);

        if ($checkInDate) {
            $h->booked()->checkIn2($checkInDate);
        }
        $checkInTime = $this->http->FindSingleNode("(//*[{$this->eq($this->t("Check-in time"), 'normalize-space(text())')}]/following::text()[normalize-space()][1]/ancestor::td[1])[1]", null, true, "/^{$this->patterns['time']}$/iu");

        if (!$checkInTime) {
            // it-51109268.eml
            $checkInTime = $this->http->FindSingleNode("//text()[ {$this->eq($this->t("Check-in time"))} and ancestor::*[{$xpathBold}] ]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['time']}$/iu");
        }
        $checkInTime = $this->normalizeTime($checkInTime);

        if ($h->getCheckInDate() && $checkInTime) {
            $h->booked()->checkIn(strtotime($checkInTime, $h->getCheckInDate()));
        }

        $checkOutDate = $this->normalizeDate($checkOutDate);

        if ($checkOutDate) {
            $h->booked()->checkOut2($checkOutDate);
        }
        $checkOutTime = $this->http->FindSingleNode("(//*[{$this->eq($this->t("Check-out time"), 'normalize-space(text())')}]/following::text()[normalize-space()][1]/ancestor::td[1])[1]", null, true, "/^{$this->patterns['time']}$/iu");

        if (!$checkOutTime) {
            // it-51109268.eml
            $checkOutTime = $this->http->FindSingleNode("//text()[ {$this->eq($this->t("Check-out time"))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->patterns['time']}$/i");
        }
        $checkOutTime = $this->normalizeTime($checkOutTime);

        if ($h->getCheckOutDate() && $checkOutTime) {
            $h->booked()->checkOut(strtotime($checkOutTime, $h->getCheckOutDate()));
        }

        // Address
        $address = $this->http->FindSingleNode("(//tr[{$this->eq($this->t('Hotel overview'))}]/following-sibling::tr//img//ancestor::td[1]/following-sibling::td[normalize-space()]//tr[not(.//tr) and normalize-space()])[2]");

        if (empty($address) && !empty($hotelName)) {
            // it-51109268.eml
            $address = $this->http->FindSingleNode("//p[ preceding::p[normalize-space()][1][{$this->eq($hotelName)}] and following::p[normalize-space()][1][{$this->eq($this->t("Hotel Supplier"))}] ]")
                ?? $this->http->FindSingleNode("//tr[ not(.//tr) and preceding::tr[not(.//tr) and normalize-space()][1][{$this->eq($hotelName)}] and following::tr[not(.//tr) and normalize-space()][1]/descendant::text()[{$this->eq($this->t("Map and directions"))}] ]")
            ;
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Hotel overview'))}]/following-sibling::tr[./following-sibling::tr[({$this->contains($this->t("Reservation dates"))})]]/descendant::text()[normalize-space()!=''][2]");
        }
        $h->hotel()->address($address);

        // Phone
        // todo: remove regexps
        //		$it['Phone'] = nice(re("#Tel\s*:\s*([\d\(\)\s]+)#", text($this->http->Response['body'])));

        // Fax
        //		$it['Fax'] = trim(re("#Fax\s*:\s*([\d\(\)\s]+)#", text($this->http->Response['body'])));

        // GuestNames
        $guestNames = array_filter($this->http->FindNodes("//*[{$this->eq($this->t("Guests"), 'normalize-space(text())')}]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", null, "#{$this->preg_implode($this->t("Reserved for"))}\s?({$this->patterns['travellerName']})(?: 的姓名預訂|$)#u"));

        if (count($guestNames) === 0) {
            // it-51109268.eml
            $productRows = $this->http->XPath->query("//p[{$this->starts($this->t("Product d"), 'translate(normalize-space(),"0123456789","dddddddddd")')}]");

            foreach ($productRows as $pRow) {
                $firstName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("First Name"))}]", $pRow, true, "/{$this->preg_implode($this->t("First Name"))}[:\s]+({$this->patterns['travellerName']})$/");
                $lastName = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Last Name"))}]", $pRow, true, "/{$this->preg_implode($this->t("Last Name"))}[:\s]+({$this->patterns['travellerName']})$/");

                if ($firstName) {
                    $guestNames[] = $firstName . ($lastName ? ' ' . $lastName : '');
                }
            }
        }

        if (count($guestNames)) {
            $h->general()->travellers(array_unique($guestNames));
        }

        // Guests
        // Kids
        $guests = [];
        $kids = [];
        $guestTexts = $this->http->FindNodes("//*[{$this->eq($this->t("Guests"), 'normalize-space(text())')}]/ancestor::tr[1]/following-sibling::tr[2]/td[1]");

        if (empty($guestTexts)) {
            $guestTexts = $this->http->FindNodes("//text()[ {$this->eq($this->t("Guests"))} and ancestor::*[{$xpathBold}] ]/following::text()[normalize-space()][1]");
        }

        foreach ($guestTexts as $guestText) {
            if (preg_match("#\b(\d{1,3})[ ]*{$this->preg_implode($this->t("adult"))}#", $guestText, $m)
                || preg_match("#{$this->preg_implode($this->t("adult"))}[ ]*(\d{1,3})\b#", $guestText, $m)
            ) {
                $guests[] = (int) $m[1];
            }

            if (preg_match("#\b(\d{1,3})[ ]*{$this->preg_implode($this->t("child"))}#", $guestText, $m)
                || preg_match("#{$this->preg_implode($this->t("child"))}[ ]*(\d{1,3})\b#", $guestText, $m)
            ) {
                $kids[] = (int) $m[1];
            }
        }

        if ($gCount = array_sum($guests)) {
            $h->booked()->guests($gCount);
        }

        if ($kidCount = array_sum($kids)) {
            $h->booked()->kids($kidCount);
        }

        // Rooms Count
        $roomsCount = array_filter($this->http->FindNodes("//text()[ {$this->eq($this->t("Guests"))}]/following::tr[normalize-space()][1][" . $this->starts($this->t("Reserved for")) . "]"));

        if (!empty($roomsCount)) {
            $h->booked()
                ->rooms(count($roomsCount));
        }

        // CancellationPolicy
        $cancelPolicyValues = [];
        $cancelPolicyRows = $this->http->XPath->query("//tr[{$this->eq($this->t("Cancellations and changes"))} and not(.//tr)]/following-sibling::tr[normalize-space()]");

        if ($cancelPolicyRows->length === 0) {
            $cancelPolicyRows = $this->http->XPath->query("//p[{$this->eq($this->t("Cancellations and changes"))} and not(.//p)]/following-sibling::p[normalize-space()]");
        }

        foreach ($cancelPolicyRows as $cancelPolicyRow) {
            $rowText = $this->http->FindSingleNode('.', $cancelPolicyRow, true, '/^(.*[.。].*|.{40,})$/u');

            if ($rowText
                && $this->http->XPath->query("descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}] and not({$this->eq($this->t("Lodging"))})]", $cancelPolicyRow)->length === 0 // it-51109268.eml
            ) {
                $cancelPolicyValues[] = $rowText;
            } else {
                break;
            }
        }

        if (count($cancelPolicyValues) && strlen(implode(' ', $cancelPolicyValues)) <= 2000) {
            $h->general()->cancellation(str_replace('{0,,hotelName}', '', implode(' ', $cancelPolicyValues)));
        } else {
            $cancellation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('CancellationStarts'))}]");

            if (!empty($cancellation)) {
                $h->general()
                    ->cancellation($cancellation);
            }
        }

        $patternRoom = '/(.*?)\s*(?:[-(]|$)/';
        $xpathRoom1 = "//*[{$this->eq($this->t("Room"), 'normalize-space(text())')}]/ancestor::tr[1]/following-sibling::tr[1][not(.//tr)][normalize-space()]";
        $xpathRoom2 = "//text()[ {$this->eq($this->t("Room"))} and ancestor::*[{$xpathBold}] ]/following::text()[normalize-space()][1]"; // it-51109268.eml

        $nodes = array_filter($this->http->FindNodes($xpathRoom1, null, $patternRoom));

        if (count($nodes) === 0) {
            $nodes = array_filter($this->http->FindNodes($xpathRoom2, null, $patternRoom));
        }

        if (count($nodes)) {
            $rootRooms = $this->http->XPath->query($xpathRoom1);

            if ($rootRooms->length === 0) {
                $rootRooms = $this->http->XPath->query($xpathRoom2);
            }

            foreach ($rootRooms as $rootRoom) {
                $node = $this->http->FindSingleNode('.', $rootRoom, false, $patternRoom);

                if (!empty($node)) {
                    $r = $h->addRoom();
                    // RoomType
                    $r->setType($node);
                    $node = implode(", ", $this->http->FindNodes("./ancestor::tr[count(./following-sibling::tr[not(.//a)])=1]/following-sibling::tr[1][./descendant::text()[normalize-space()!=''][1][{$this->eq($this->t('Room requests'))}]]/descendant::text()[normalize-space()!=''][position()>1]", $rootRoom));

                    if (!empty($node)) {
                        // RoomTypeDescription
                        $r->setDescription($node);
                    }
                }
            }
        }

        //Rate
        $rate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price summary'))}]/ancestor::table[1]/descendant::text()[{$this->contains($this->t("night"))}][1]/ancestor::td[1]",
            null, false, "#{$this->preg_implode($this->t("night"))}\s*\:\s*(.+)#uis");

        if ($rate && (count($h->getRooms()) < 2)) {
            if (!isset($r)) {
                $r = $h->addRoom();
            }
            $r->setRate($rate);
            $cost = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Price summary'))}]/ancestor::table[1]/descendant::text()[{$this->starts($this->t("Room price"))}][1]/ancestor::td[1]",
                null, false, "#{$this->preg_implode($this->t("Room price"))}[\s:]*(.+)#");

            if (preg_match("/^\s*(?<amount>\d[,.\'\d ]*) ?(?<currency>[^\d)(\s]+)\s*$/", $cost, $matches)
                || preg_match("/^\s*(?<currency>[^\d\s)(]+) ?(?<amount>\d[,.\'\d ]*)\s*$/", $cost, $matches)
            ) {
                $currency = $this->currency($matches['currency']);

                if (!empty($currency)) {
                    $h->price()
                        ->currency($currency);
                }

                $h->price()
                    ->cost(PriceHelper::parse($matches['amount'], $currency));
            }
        }

        // Taxes
        $taxes = $this->http->FindSingleNode("//tr/*[not(.//tr) and ({$this->contains($this->t("Taxes & fees"))} or {$this->contains($this->t("Taxes"))})]", null, true, "/^(?:{$this->preg_implode($this->t("Taxes & fees"))}|{$this->preg_implode($this->t("Taxes"))})?[: ]*(.*\d.*)$/u")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t("Taxes & fees"))} or {$this->eq($this->t("Taxes"))}]/following::text()[normalize-space()][1]", null, true, '/^[: ]*(.*\d.*)$/')
            ?? $this->http->FindSingleNode("//text()[({$this->starts($this->t("Taxes & fees"))} or {$this->starts($this->t("Taxes"))}) and contains(.,':')]", null, true, "/^(?:{$this->preg_implode($this->t("Taxes & fees"))}|{$this->preg_implode($this->t("Taxes"))})[: ]*(.*\d.*)$/u")
        ;

        if (preg_match("/^\s*(?<amount>\d[,.\'\d ’]*) ?(?<currency>[^\d)(\s]+)\s*(?:\D+|$)/", $taxes, $matches)
            || preg_match("/^\s*(?<currency>[^\d\s)(]+) ?(?<amount>\d[,.\'\d ’]*)\s*(?:\D+|$)/", $taxes, $matches)
        ) {
            $h->price()
                ->tax($this->cost($matches['amount']));
        }

        $discount = $this->http->FindSingleNode("//text()[({$this->starts($this->t("discount"))})]", null, true, "/^\D+\s*([\d\.\,]+)/u");

        if (!empty($discount) && $h->getPrice()) {
            $h->price()
                ->discount(PriceHelper::parse($discount, $h->getPrice()->getCurrencySign()));
        }

        // Total
        // Currency
        $payment = $this->http->FindSingleNode("(//*[" . $this->contains($this->t("Total"), 'text()') . "]/ancestor::td[1])[1]");
        $payment = preg_replace("/(\d+)\,(\d+)\,/", "$1$2", $payment); //Total: THB 1,20,117.24 (₹261,065.93)

        if (preg_match("/{$this->preg_implode($this->t("Total"))}[\s:]*(.+?)(?:\(|$)/", $payment, $matches)
            && preg_match("/^(\d[,.\d]*\s*PTS)\s+{$this->preg_implode($this->t('and'))}\s+(.+)/i", $matches[1], $match)
        ) {
            $currency = $this->currency($match[2], $h->getAddress());

            if (!empty($currency)) {
                $h->price()
                    ->currency($currency);
            }
            // Total: 79,987 PTS and $0.00
            $h->price()
                ->spentAwards($match[1])
                ->total($this->cost($match[2], $currency));
        } elseif (preg_match("/{$this->preg_implode($this->t("Total"))}[\s:]*(?<currency>[^\d\s)(]+) ?(?<amount>\d[,.\'\d ’]*)(?:\(|$)/u", $payment, $matches)
            || preg_match("/{$this->preg_implode($this->t("Total"))}[\s:]*(?<amount>\d[,.\'\d ’]*) ?(?<currency>\D+)(?:\(|$)/u", $payment, $matches)
        ) {
            // Total: $0.00    |    Total 223,20 €
            $currency = $this->currency(trim($matches['currency'], ':'), $h->getAddress());

            if (empty($currency) && $this->http->XPath->query("//text()[{$this->contains($this->t('Unless specified otherwise, rates are quoted in US dollars.'))}]")->length > 0) {
                $currency = 'USD';
            }
            $h->price()
                ->total($this->cost($matches['amount'], $currency))
                ->currency($currency);
        } else {
            $payment = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total")) . "]/following::text()[normalize-space(.)][1]");

            if (!empty($payment) && (empty($h->getPrice()) || empty($h->getPrice()->getTotal())) && preg_match('/(.*?)(?:\(|$)/',
                    $payment, $matches)
            ) {
                $currency = $this->currency($this->re("/^(\D+)\.*/", $matches[1]), $h->getAddress());
                $h->price()
                    ->total($this->cost($matches[1], $currency))
                    ->currency($currency);
            }
        }

        $earn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('For Expedia+ rewards members'))}]/preceding::text()[normalize-space()][1][{$this->contains($this->t('points'))}]/ancestor::tr[1]");

        if (!empty($earn)) {
            $h->program()
                ->earnedAwards($earn);
        }

        if ($email->getProviderCode() === 'ebookers') {
            $earn = $this->http->FindSingleNode("//text()[{$this->eq($this->t('for this trip'))}]/preceding::text()[normalize-space()][1][{$this->contains($this->t('BONUS+'))}]/ancestor::tr[1]", null, true,
                "/^\s*(.+)\s+in BONUS\+/");

            if (!empty($earn)) {
                $h->program()
                    ->earnedAwards($earn . ' BONUS+');
            }
            $spent = $this->http->FindSingleNode("//text()[{$this->contains($this->t('BONUS+ used:'))}]", null, true,
                "/^\s*(.+\s+BONUS\+)/");

            if (!empty($spent)) {
                $h->price()
                    ->spentAwards($spent);
            }
        }
        $this->detectDeadLine($h);
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancell?ations(?i) or changes made after (?<time>\d{1,2}:\d{2}(?:\s*[ap]m?)?) \(.+?\) on (?<date>\d{1,2} [[:alpha:]]{3,}[.\s]+\d{2,4}|\w+ \d{1,2}, \d{4}) or no-shows are subject to a (?:property|hotel) fee equal to/", $cancellationText, $m)
            || preg_match("/You can cancel free of charge up until the cancellation window[.\s]+Cancellations or changes between (?<time>{$this->patterns['time']}), (?<date>[-[:alpha:]]{2,}, [[:alpha:]]{3,} \d{1,2}) and/i", $cancellationText, $m)
            || preg_match("/Free (?i)cancell?ation until (?<date>\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{4}) at (?<time>{$this->patterns['time']})(?:\s*[,.;(]|$)/u", $cancellationText, $m)
            || preg_match("/Bij annuleringen of wijzigingen na (?<time>\d{1,2}[\.:]\d{2}) uur \(.+\) op (?<date>\d{1,2} \w+ \d{2,4}|\w+ \d{1,2}, \d{4}) of no shows berekent de accommodatie een toeslag gelijk/ui", $cancellationText, $m)
            || preg_match("/Bei .nderungen oder Stornierungen nach (?<time>\d{1,2}[\.:]\d{2}) Uhr \(.+\) am (?<date>\d+\.? \w+ \d{4}|\w+ \d{1,2}, \d{4}) oder bei Nichtanreise fallen Unterkunftsgeb.hren von 100% des gezahlten Buchungspreises an/ui", $cancellationText, $m)
            || preg_match("/Les annulations ou modifications effectuées après le (?<date>\d+\.? \w+\.? \d{4}|\w+ \d{1,2}, \d{4}) à (?<time>\d{1,2}[ ]*[\.:h][ ]*\d{2}) \(.+\) ou la non-présentation à l’établissement sont soumises à des frais d’établissement correspondant/ui", $cancellationText, $m)
            || preg_match("/Cancelamentos ou alterações feitos após (?<time>\d{1,2}[ ]*[\.:h][ ]*\d{2}) \(.+\), em (?<date>\d+ \w+, \d{4}), ou no-show estão sujeitos a uma taxa cobrada pelo estabelecimento/ui", $cancellationText, $m)
            || preg_match("/(?:^|\.)\s*(?<date>\d{4} ?\w ?\d{1,2} ?\w ?\d{1,2} ?\w)\s*(?<time>\d{1,2}:\d{2})\(.*\) 이후에 취소 또는 변경하거나 노쇼의 경우 첫 \d+박분의 객실 요금과 세금 및 수수료에 해당하는 숙박 시설 수수료가 부과됩니다/ui", $cancellationText, $m)
            || preg_match("/ Annulation gratuite jusqu’au (?<date>\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{4}) à (?<time>{$this->patterns['time']})/ui", $cancellationText, $m)
            || preg_match("/Gratis afbestilling frem til kl\. (?<time>{$this->patterns['time']}) \(Vesteuropa, normaltid\) den (?<date>\d{1,2}[,. ]+[[:alpha:]]+[,. ]+\d{4})/ui", $cancellationText, $m)
            || preg_match("/Les annulations et les modifications effectuées avant (?<time>\d{1,2}:\d{2}) \(Heure normale du Pacifique \(Amérique du Sud\)\) le (?<date>\d+\.? \w+\.? \d{4}) ou les défections engendreront des frais d’hébergement équivalant à/ui", $cancellationText, $m)
            || preg_match("/Kostenlose Stornierung bis\s*(?<date>\d+\.\s*\w+\s*\d{4})\D+(?<time>\d{1,2}:\d{2})/ui", $cancellationText, $m)
            || preg_match("/Gratis avbokning fram till\s*(?<time>[\d\:]+)\s*\D+(?<date>\d+\s*\w+\s*\d{4})/ui", $cancellationText, $m)
            || preg_match("/Cancelamento grátis até\s*(?<date>\d+\s*\D+\d{4})\,\s*às\s*(?<time>[\d\:h]+)/ui", $cancellationText, $m)
        ) {
            $m['time'] = preg_replace('/(\d[ ]*[ap])$/i', '$1m', $m['time']); // 4:00p -> 4:00pm
            $m['time'] = str_replace('.', ':', $m['time']);

            if (preg_match("/^(?<wday>[-[:alpha:]]{2,})\s*,\s*(?<date>[[:alpha:]]{3,}\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]{3,})$/u", $m['date'], $matches)
                && $h->getCheckOutDate()
            ) {
                // Wed, Jan 22    |    Wed, 22 Jan
                $weekDayNumber = WeekTranslate::number1($matches['wday']);
                $date = $this->normalizeDate($matches['date']);

                if ($weekDayNumber && $date) {
                    $date = EmailDateHelper::parseDateUsingWeekDay($date . ' ' . date('Y', $h->getCheckOutDate()), $weekDayNumber);
                    $h->booked()->deadline(strtotime($m['time'], $date));

                    return;
                }
            }
            $h->booked()
                ->deadline(strtotime($this->normalizeDate($m['date']) . ', ' . str_replace([".", "h", " "], [':', ':', ''], $m['time'])));
        }

        $h->booked()
            ->parseNonRefundable("#The room type and rate selected are non-refundable#")
            ->parseNonRefundable("#The room\/unit type and rate selected are non-refundable#")
            ->parseNonRefundable("#Cancellations or changes made before \d{1,2}[\.:]\d{2} \(.+\) on (?:\d{1,2} \w+ \d{2,4}|\w+ \d{1,2}, \d{4}) or no-shows are subject to a property fee equal to the first nights rate plus taxes and fees#i")
            ->parseNonRefundable("#El tipo de habitaci.n o unidad y la tarifa seleccionados no son reembolsables#u")
            ->parseNonRefundable("#La tipologia di camera e la tariffa selezionate non sono rimborsabili#")
            ->parseNonRefundable('/Bei Änderungen oder Stornierungen fallen Gebühren von 100% des gezahlten Buchungspreises an/ui')
            ->parseNonRefundable('/Le type de chambre\\/hébergement et le tarif sélectionnés ne sont pas remboursables\./ui')
            ->parseNonRefundable('選択された部屋 / ユニットのタイプおよび料金は返金不可です。') // ja
        ;
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'expedia') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate(?string $string): ?string
    {
        //$this->logger->debug('normalizeDate ' . $string);

        if (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $string, $matches)) { // 15.6.2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(?:[-[:alpha:]]+[,\s]+)?([[:alpha:]]{3,})[.\s]+(\d{1,2})[,\s]+(\d{4})$/u', $string, $matches)) {
            // Jun 25, 2015    |    Sun, Jan 26 2020    |    Feb. 1, 2021
            $month = $matches[1];
            $day = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})[,.\s]+([^,.\d\s]{3,})[,.\s]+(\d{4})$/', $string, $matches)) { // "7 Jan 2016" or "9 Apr. 2017" or "21. Feb. 2017" or "10 feb., 2017"
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{4}) ?(?:年|년) ?(\d+) ?(?:月|월) ?(\d+) ?(?:日|일)\s*$/', $string, $matches) // 2017年6月20日; 2019년 12월 29일
            || preg_match('/^(\d{4})\/(\d+)\/(\d+)$/', $string, $matches) // 2019/5/18
        ) {
            $day = $matches[3];
            $month = $matches[2];
            $year = $matches[1];
        } elseif (preg_match('/^(\d{1,2})\s+de\s+(\w+)\.?\s+de\s+(\d{4})$/u', $string, $matches)) { // 11 de sep de 2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(?<day>\d{1,2})\/(?<month>\d{1,2})\/(?<year>\d{2,4})$/', $string, $matches)) {
            // 14/01/20
            $day = $matches['day'];
            $month = $matches['month'];
            $year = $matches['year'];
        } elseif (preg_match('/^(?<month>[[:alpha:]]{3,})\s+(?<day>\d{1,2})$/u', $string, $matches)
            || preg_match('/^(?<day>\d{1,2})\s+(?<month>[[:alpha:]]{3,})$/u', $string, $matches)
        ) {
            // Jan 22    |    22 Jan
            $day = $matches['day'];
            $month = $matches['month'];
            $year = '';
        } elseif (preg_match('/^(?<day>\d+)\s*(?<month>\D+)\s+(?<year>\d{4})$/u', $string, $matches)) {
            // 26 ธันวาคม 2022
            $day = $matches['day'];
            $month = $matches['month'];
            $year = $matches['year'];
        } elseif (preg_match('/^(?<year>\d{4})\.?\s*(?<month>\D+)\s+(?<day>\d+)\.?$/u', $string, $matches)) {
            // 2023. január 25.
            $day = $matches['day'];
            $month = $matches['month'];
            $year = $matches['year'];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*\d{1,2}\s*$/', $month)) {
                return $day . '.' . $month . ($year ? '.' . $year : '');
            }
            //$this->logger->error($this->dateStringToEnglish($day . ' ' . $month . ($year ? ' ' . $year : '')));

            return $this->dateStringToEnglish($day . ' ' . $month . ($year ? ' ' . $year : ''));
        }

        return null;
    }

    private function normalizeTime(?string $time): string
    {
        //$this->logger->debug('$time in = ' . print_r($time, true));
        $in = [
            "#^(meio-dia|noon|midi|正午|中午|mediodía|öğlen|dél)$#u",
            "#^(\d+)[\.h](\d+\s*[AP]M)$#",
            "#^(\d+)\s*h\s*(\d+)$#",
            "#^(\d+) ?h$#",
            "#^(\d{1,2})[h]?(\d{2})$#",
            '/^(\d{2})$/',
            "#^(\d+)[\.h:](\d+\s*[ap])$#",
            "#^([\d\:]+)\s*(?:μ\.μ\.)$#",
        ];
        $out = [
            '12:00 PM',
            '$1:$2',
            '$1:$2',
            '$1:00',
            '$1:$2',
            '$1:00',
            '$1:$2m',
            "$1 PM",
        ];
        //$this->logger->debug('$time out = '.print_r( preg_replace($in, $out, $time),true));

        return preg_replace($in, $out, $time);
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#\D+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }

    private function currency($s, $location = '')
    {
        //$this->logger->debug('currency = ' . print_r($s, true));

        $s = str_replace('：', '', trim($s));

        if (stripos($s, '(') !== false) {
            $s = $this->re("/^(.+)\s+\(/u", $s);
        }

        if (preg_match("#¤#", $s)) {
            $s = $this->defaultCurrency();
        }
        $sym = [
            '$CA'             => 'CAD',
            '$C'              => 'CAD',
            'R$'              => 'BRL',
            'C$'              => 'CAD',
            'CA$'             => 'CAD',
            'SG$'             => 'SGD',
            'S$'              => 'SGD',
            'HK$'             => 'HKD',
            'AU$'             => 'AUD',
            'NT$'             => 'TWD',
            'A$'              => 'AUD',
            '€'               => 'EUR',
            '$'               => '$',
            '$US'             => 'USD',
            'US$'             => 'USD',
            'US dollars'      => 'USD',
            '£'               => 'GBP',
            'svenska kronor'  => 'SEK',
            'kr'              => 'SEK',
            'RM'              => 'MYR',
            '฿'               => 'THB',
            'MXN$'            => 'MXN',
            'MX$'             => 'MXN',
            'Euro'            => 'EUR',
            'Euros'           => 'EUR',
            'euro'            => 'EUR',
            'Real brasileiro' => 'BRL',
            'JP¥'             => 'JPY',
            '円'               => 'JPY',
            '￥'               => 'JPY',
            '¥'               => 'JPY',
            'CN¥'             => 'CNY',
            '₹'               => 'INR',
            '₩'               => 'KRW',
            '원(대한민국)'         => 'KRW',
            '₪'               => 'ILS',
            'NZ$'             => 'NZD',
            'AR$'             => 'MGF',
            '₫'               => 'VND',
            //            '￥'               => 'YEN',
            'Rs'               => 'INR',
            'CFPF'             => 'XPF',
            'R'                => 'ZAR',
            'TL'               => 'TRY',
            'Ft'               => 'HUF',
            'Dkr'              => 'DKK',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d :]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        if ($s == '¥' && stripos($location, 'Japan') !== false) {
            return 'JPY';
        }

        if ($s == 'kr' && $this->lang == 'sv') {
            return 'SEK';
        }

        if ($s == 'kr' && $this->lang == 'no') {
            return 'NOK';
        }

        if ($s == 'kr' && $this->lang == 'da') {
            return 'DKK';
        }

        return null;
    }

    private function defaultCurrency()
    {
        $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Total")) . "][1]/ancestor::table[2]//text()[normalize-space(.)]"));
        $reCurrency = (array) $this->t("#defaultCurrency#");

        foreach ($reCurrency as $re) {
            if (preg_match($re, $totalText, $m) && !empty($m[1])) {
                return $this->currency($m[1]);
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

    private function cost($s, $currency = null)
    {
        $s = str_replace("’", '', $s);
        $s = trim($this->re("#^\D*(\d[\d\,\.\' ]+)\D*$#", $s));

        if (empty($s)) {
            return null;
        }
        $s = PriceHelper::parse($s, $currency);

        if (is_numeric($s)) {
            return (float) $s;
        }

        return null;
    }
}
