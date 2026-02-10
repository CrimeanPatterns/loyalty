<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

// TODO: merge with parsers tahitinui/ConfirmationReservation, lotpair/ConfirmChanges, cubana/It2818120 (in favor of amadeus/AirTicketHtml2016)

class AirTicketHtml2016 extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-1.eml, amadeus/it-10114885.eml, amadeus/it-10114897.eml, amadeus/it-10141584.eml, amadeus/it-10214111.eml, amadeus/it-10217026.eml, amadeus/it-10223842.eml, amadeus/it-10224950.eml, amadeus/it-10277730.eml, amadeus/it-10287357.eml, amadeus/it-10637853.eml, amadeus/it-10956293.eml, amadeus/it-10976308.eml, amadeus/it-11181667.eml, amadeus/it-12002159.eml, amadeus/it-12049994.eml, amadeus/it-12094975.eml, amadeus/it-12532703.eml, amadeus/it-1618289.eml, amadeus/it-1655462.eml, amadeus/it-1680532.eml, amadeus/it-1732818.eml, amadeus/it-173376012.eml, amadeus/it-1842850.eml, amadeus/it-1928655.eml, amadeus/it-1934274.eml, amadeus/it-1972172.eml, amadeus/it-1991121.eml, amadeus/it-2.eml, amadeus/it-21.eml, amadeus/it-2130074.eml, amadeus/it-2353045.eml, amadeus/it-2453525.eml, amadeus/it-2559000.eml, amadeus/it-2566627.eml, amadeus/it-2568968.eml, amadeus/it-2587965.eml, amadeus/it-2617603.eml, amadeus/it-2638344.eml, amadeus/it-2638348.eml, amadeus/it-2893563.eml, amadeus/it-2912883.eml, amadeus/it-30620432.eml, amadeus/it-3072336.eml, amadeus/it-3097644.eml, amadeus/it-3109415.eml, amadeus/it-3194159.eml, amadeus/it-33791713.eml, amadeus/it-33809317.eml, amadeus/it-3795791.eml, amadeus/it-40.eml, amadeus/it-41.eml, amadeus/it-4318052.eml, amadeus/it-4464454.eml, amadeus/it-4549537.eml, amadeus/it-4589389.eml, amadeus/it-4713877.eml, amadeus/it-4732622.eml, amadeus/it-4873081.eml, amadeus/it-49700391-sv.eml, amadeus/it-5009642.eml, amadeus/it-5061029.eml, amadeus/it-5138562.eml, amadeus/it-5144367.eml, amadeus/it-5144369.eml, amadeus/it-5156754.eml, amadeus/it-5156755.eml, amadeus/it-5156757.eml, amadeus/it-5200830.eml, amadeus/it-5212378.eml, amadeus/it-57294012.eml, amadeus/it-57344827.eml, amadeus/it-5775008.eml, amadeus/it-5784249.eml, amadeus/it-5851064.eml, amadeus/it-5914075.eml, amadeus/it-5928558.eml, amadeus/it-5942614.eml, amadeus/it-5944586.eml, amadeus/it-5953607.eml, amadeus/it-6128719.eml, amadeus/it-6151738.eml, amadeus/it-6182351.eml, amadeus/it-6224584.eml, amadeus/it-6231251.eml, amadeus/it-6268400.eml, amadeus/it-6321371.eml, amadeus/it-6357549.eml, amadeus/it-6561848.eml, amadeus/it-6692816.eml, amadeus/it-7008479.eml, amadeus/it-7047458.eml, amadeus/it-7091957.eml, amadeus/it-7115121.eml, amadeus/it-7146410.eml, amadeus/it-7225719.eml, amadeus/it-7228059.eml, amadeus/it-7230889.eml, amadeus/it-7263853.eml, amadeus/it-7303226.eml, amadeus/it-7493629.eml, amadeus/it-7501871.eml, amadeus/it-7542701.eml, amadeus/it-7717506.eml, amadeus/it-8274046.eml, amadeus/it-8559453.eml, amadeus/it-8603896.eml, amadeus/it-8606151.eml, amadeus/it-8782026.eml, amadeus/it-302488833-sv-malmo.eml";
    public static $provider = [
        'malmo'                 => ['Tack för att du valde BRA', 'Tack för att du bokat din resa med BRA', 'www.flygbra.se'],
        'mea'                   => ['Thank you for booking on http://www.mea.com.lb', 'Thank you for using http://www.mea.com.lb', 'call the nearest MEA office', 'please contact reservationcontrol@mea.com.lb'],
        'airlink'               => ['.flyairlink.com/', 'www.flyairlink.com', 'Thank you for choosing Airlink', 'Click here for complete Airlink Conditions'],
        'qmiles'                => ['Thank you for choosing Qatar Airways', 'qatarairways.com'],
        'eva'                   => ['.evaair.com', 'evaair.co2analytics.com', '長榮航'],
        'algerie'               => ['//airalgerie.dz/', 'Thank you for choosing Air Algerie', "Merci d'avoir choisi Air Algérie"],
        'saudisrabianairlin'    => 'saudiairlines.com',
        'thaiair'               => 'thaiairways.com',
        'aireuropa'             => ['@aireuropa.com', 'Obrigado por escolher a Air Europa'],
        'singaporeair'          => ['singaporeair.', 'SQmobile'],
        'garuda'                => 'garuda-indonesia',
        'aircaraibes'           => 'Air Caraïbes',
        'astana'                => 'airastana',
        'aviancataca'           => 'avianca',
        'china'                 => 'China Airlines',
        'egyptair'              => 'EgyptAir',
        'finnair'               => 'Finnair',
        'flyerbonus'            => 'Bangkok Airways',
        'israel'                => 'ELAL',
        'japanair'              => ['japanair.com', 'choosing JAPAN AIRLINES'],
        'luxair'                => 'Luxair',
        'sata'                  => 'Sata',
        'tapportugal'           => 'flytap',
        'tunisair'              => ['tunisair', 'choisi Tunisair Online'],
        'tarom'                 => 'tarom',
        'wideroe'               => 'wideroe',
        'mabuhay'               => ['Philippine Airlines', '.philippineairlines.com'],
        'jordanian'             => ['Royal Jordanian', '@rj.com'],
        'malaysia'              => ['Malaysia Airlines', 'malaysiaairlines.com'],
        'aerolineas'            => ['Aerolineas Argentinas', '.aerolineas.com.ar'],
        'srilankan'             => ['SriLankan Airlines', '.srilankan.com'],
        'caribbeanair'          => ['choosing Caribbean Airlines', '.caribbean-airlines.com'],
        'airmaroc'              => ['Royal Air Maroc CALL CENTER', 'choosing Royal Air Maroc', '@royalairmaroc.com'],
        'airindia'              => ['Thank you for choosing Air India', 'Team Air India', '://www.airindia.in', '@airindia.in'],
        'vistara'               => ['Thank you for choosing Vistara', 'custrelations@airvistara.com', 'reservations@airvistara.com'],
        'airgreenland'          => ['@airgreenland.gl', 'you aboard Air Greenland'],
        'tahitinui'             => ['.airtahiti.', 'Merci d\'avoir choisi Air Tahiti', 'choosing Air Tahiti Nui'],
        'kestrelflyer'          => ['booking on Air Mauritius', 'airmauritius', 'choosing Air Mauritius'],
        'czech'                 => ['choosing Czech Airlines', 'www.csa.cz/'],

        'amadeus' => 'amadeus', // last
    ];

    public $lang = '';
    public static $dict = [
        'de' => [
            'Booking reference:' => ['Buchungsreferenz', 'Buchungsreferenz:', 'Reservierungsnummer:', 'Buchungsnummer:'],
            'Document'           => ['Dokument', 'Dokument-Nummer:'],
            'Traveler'           => 'Reisender',
            'Seat Request'       => ['Sitzplatzreservierung', 'Sitzplatz'],
            //			'Seat' => '',
            'Departure:' => ['Abflug:', 'Hinflug'],
            'Arrival:'   => 'Ankunft:',
            'Flight'     => 'Flug',
            'Airline:'   => ['Fluggesellschaft:', 'Airline'],
            'Aircraft:'  => ['Flugzeugtyp:', 'Flugzeugtyp'],
            'Fare type:' => 'Tariftyp:',
            // 'Fare / Cabin:' => '',
            'Class'                 => ['Klasse:', 'Klasse'],
            'Trip status:'          => 'Reisestatus:',
            'Traveller Information' => ['ANGABEN ZUM REISENDEN', 'Angaben zum Reisenden'],
            'Frequent flyer(s)'     => 'Vielflieger',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => 'Dauer:',
            'Total for all travellers' => ['Insgesamt für alle Passagiere', 'Insgesamt für alle Reisenden'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'Kontaktinformationen',
            'Operated by'         => 'Betreiber:',
            //            'Cancellation Reservation'      => '',
        ],
        'pt' => [
            'Document'               => 'Documento',
            'Booking reference:'     => ['Código da reserva:', 'Código de reserva:', 'Número do Pedido', 'Número da reserva:'],
            'Traveler'               => 'Passageiro',
            'Seat Request'           => 'Solicitação de assento',
            'Seat'                   => 'Lugar',
            'Departure:'             => ['Partida:', 'Saída:'],
            'Arrival:'               => 'Chegada:',
            'Flight'                 => 'Voo',
            'Airline:'               => ['Companhia aérea:', 'Linha Aérea:', 'Companhia:', 'Companhia de aviação:', 'Companhia de aviação :', 'Companhia de aviação'],
            'Aircraft:'              => ['Avião:', 'Avião', 'Aeronave:', 'Aeronave'],
            'Fare type:'             => ['Tipo de tarifa:', 'Refeição'],
            'Fare / Cabin:'          => ['Tarifa / Cabine:', 'Tarifa / Cabine'],
            'Class'                  => ['Classe:', 'Classe'],
            'Trip status:'           => ['Situação da viagem:', 'Status da viagem:', 'Estado do pagamento:'],
            'Traveller Information'  => ['Informações do passageiro', 'Informação dos Passageiros', 'INFORMAÇÕES DO PASSAGEIRO', 'informações do passageiro', 'Informações do viajante'],
            'Frequent flyer(s)'      => ['Passageiro(s) frequente(s)', 'Passageiro(s) freqüente(s)'],
            //			'Note:' => '',
            'Technical stop'           => 'Parada técnica',
            'Duration'                 => 'Duração',
            'Total for all travellers' => ['Total para todos os passageiros', 'Total para todos os Passageiros', 'Total para todos os viajantes'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => ['Contactos', 'Informações', 'informação', 'informações de contacto', 'informações de contato'],
            'Operated by'         => 'Operado por',
            //            'Cancellation Reservation'      => '',
        ],
        'es' => [
            'Document'           => 'Documento',
            'Booking reference:' => ['Referencia de la reserva:', 'Número de reserva:', 'Número de referencia (PNR):', 'Código de reserva:'],
            'Seat Request'       => ['Asiento', 'Solicitud de asiento'],
            //			'Seat' => '',
            'Departure:' => ['Salida:', 'Ida'],
            'Arrival:'   => 'Llegada:',
            'Flight'     => 'Vuelo',
            'Airline:'   => ['Línea aérea:', 'Cia Aérea:', 'Aerolínea:'],
            'Aircraft:'  => ['Avión:', 'Avión'],
            'Fare type:' => ['Tipo de tarifa:', 'Clase:'],
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => ['Estado del viaje:', 'Estado de Compra:', 'Estado del pago:'],
            'Traveller Information' => ['Información del viajero', 'información del viajero', 'Información de los Pasajeros'],
            'Frequent flyer(s)'     => 'Pasajero(s) frecuente(s)',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                      => 'Duración:',
            'Total for all travellers'      => ['Total para todos los pasajeros', 'Total para todos los Pasajero', 'Total para todos los viajeros'],
            'Total taxes, fees and charges' => 'Total de impuestos, recargos y suplementos',
            'Contact Information'           => 'información',
            'Operated by'                   => 'Operado por',
            //            'Cancellation Reservation'      => '',
        ],
        'it' => [
            'Document'           => 'Documento',
            'Booking reference:' => ['Codice prenotazione:', 'Codice di prenotazione:'],
            //			'Seat Request' => '',
            //			'Seat' => '',
            'Departure:' => ['Partenza:', 'Andata'],
            'Arrival:'   => 'Arrivo:',
            'Flight'     => 'Volo',
            'Airline:'   => 'Compagnia aerea:',
            'Aircraft:'  => 'Aereo:',
            'Fare type:' => 'Tipo tariffa:',
            // 'Fare / Cabin:' => '',
            'Class'                 => ['Classe:', 'Classe'],
            'Trip status:'          => 'Stato del viaggio:',
            'Traveller Information' => ['INFORMAZIONI SUL PASSEGGERO', 'informazioni sul passeggero'],
            'Frequent flyer(s)'     => 'Frequent flyer:',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => 'Durata:',
            'Total for all travellers' => 'Totale per tutti i passeggeri',
            //			'Total taxes, fees and charges' => '',
            'Terminal'            => 'terminale',
            'Contact Information' => 'Informazioni di contatto',
            'Operated by'         => 'condotta da',
            //            'Cancellation Reservation'      => '',
        ],
        'fr' => [
            'Document'           => 'Document',
            'Booking reference:' => ['Référence de la réservation:', 'Numéro de réservation :'],
            'Seat Request'       => ['Demande de siège', 'Réservation de siège', 'siège'],
            'Seat'               => 'Sièges',
            'Departure:'         => ['Départ :', 'Départ'],
            'Arrival:'           => 'Arrivée :',
            'Flight'             => 'Vol',
            'Airline:'           => 'Compagnie :',
            'Aircraft:'          => ['Appareil :', 'Appareil'],
            'Fare type:'         => 'Type de tarif :',
            // 'Fare / Cabin:' => '',
            'Class'                         => 'Classe',
            'Trip status:'                  => 'État du voyage :',
            'Traveller Information'         => ['informations sur le voyageur', 'Informations sur le voyageur', "INFORMATIONS SUR LE(S) VOYAGEUR(S)"],
            'Frequent flyer(s)'             => 'Carte(s) de fidélité',
            'Note:'                         => 'Remarque :',
            'Technical stop'                => 'escale(s) technique(s)',
            'Duration'                      => 'Durée :',
            'Total for all travellers'      => ['Total pour tous les passagers', 'Total pour tous les voyageurs', 'total pour tous les passagers', 'Prix total des modifications'],
            'Total taxes, fees and charges' => ['total des taxes', 'Total des taxes et frais'],
            'Contact Information'           => ['informations', 'coordonnées', 'voyageur'],
            'Operated by'                   => 'Operé par',
            //            'Cancellation Reservation'      => '',
        ],
        'zh' => [
            // TODO: bcdtravel Maybe not an exact translation
            'Document'              => ['票號', '文件', '機票號碼：'],
            'Booking reference:'    => ['航空公司確認編號', '訂位代號:', '预订编号：', '訂位代號：', '確認編號:‎'],
            'Seat Request'          => '服務',
            'Seat'                  => '座位',
            'Departure:'            => ['去程:', '啟程:', '启程：', '去程 :', '去程'],
            'Arrival:'              => ['到達:', '到达：', '到達 :', '到達'],
            'Terminal'              => ['候机楼', '航站', '航廈'],
            'Flight'                => '航班',
            'Airline:'              => ['航空公司:', '航空公司 (班號):', '航空公司：', '班號 :', '航班班號:'],
            'Aircraft:'             => ['機型:', '飞机：', '機型：'],
            'Fare type:'            => ['票价类型：', '票價類型:'],
            'Fare / Cabin:'         => ['票價類型/艙等', '艙等 :'],
            'Class'                 => ['訂位艙等代碼：', '訂位艙等代碼:', '艙等'],
            'Trip status:'          => '機位狀態:',
            'Traveller Information' => ['旅客資訊', '旅客信息'],
            'Frequent flyer(s)'     => ['會員卡號', '會員卡號：'],
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                      => ['飛行時間:', '历时：', '飛行時間 :'],
            'Total for all travellers'      => ['所有旅客的合計', '所有旅客的总计', '變更的總價'],
            'Total taxes, fees and charges' => '稅金總額',
            'Contact Information'           => '联系信息',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'no' => [
            'Booking reference:' => ['Reservasjonsnummer:'],
            //			'Document' => '',
            //			'Traveler' => '',
            //			'Seat Request' => [''],
            //			'Seat' => '',
            'Departure:' => ['Avreise:'],
            'Arrival:'   => 'Ankomst:',
            'Flight'     => 'Flyavgang',
            'Airline:'   => ['Flyselskap:', 'Flyselskap'],
            'Aircraft:'  => 'Fly:',
            'Fare type:' => 'Pristype:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => 'Reisestatus:',
            'Traveller Information' => ['Passasjerer', 'Passasjerinformasjon'],
            'Frequent flyer(s)'     => 'Bonusprogrammer:',
            'Note:'                 => 'Mer:',
            //			'Technical stop' => '',
            'Duration'                 => 'Varighet:',
            'Total for all travellers' => 'Totalt for alle passasjerer',
            //			'Total taxes, fees and charges' => '',
            //			'Contact Information' => '',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'ro' => [
            'Booking reference:' => ['Număr de rezervare:', 'Cod de rezervare:'],
            //			'Document' => '',
            //			'Traveler' => '',
            'Seat Request' => ['Servicii'],
            'Seat'         => 'loc',
            'Departure:'   => ['Plecare:'],
            'Arrival:'     => 'Sosire:',
            'Flight'       => 'Zbor',
            'Airline:'     => ['Linia aeriană:'],
            'Aircraft:'    => 'Aeronavă:',
            'Fare type:'   => 'Tip de tarif:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => 'Starea călătoriei:',
            'Traveller Information' => ['informaţii călători'],
            'Frequent flyer(s)'     => 'Călători frecvenţi',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => 'Durată:',
            'Total for all travellers' => ['Total pentru toţi pasagerii', 'Total pentru toţi călătorii'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'Date de contact',
            'Operated by'         => 'Operat de',
            //            'Cancellation Reservation'      => '',
        ],
        'nl' => [
            'Booking reference:' => ['Reserveringsnummer boeking:'],
            'Document'           => 'Document',
            //			'Traveler' => '',
            //			'Seat Request' => [''],
            //			'Seat' => '',
            'Departure:' => ['Vertrek:'],
            'Arrival:'   => 'Aankomst:',
            'Flight'     => 'Vlucht',
            'Airline:'   => ['Luchtvaartmaatschappij:'],
            'Aircraft:'  => 'Toestel:',
            'Fare type:' => 'Tarieftype:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => 'Reisstatus:',
            'Traveller Information' => ['reizigersgegevens'],
            //			'Frequent flyer(s)' => '',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => 'Duur:',
            'Total for all travellers' => ['totaal voor alle reizigers'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'contactgegevens',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'ja' => [
            'Booking reference:' => ['予約番号:'],
            'Document'           => '航空券',
            //			'Traveler' => '',
            //			'Seat Request' => [''],
            'Seat'       => '座席番号',
            'Departure:' => ['出発:'],
            'Arrival:'   => '到着:',
            'Terminal'   => 'ターミナル',
            'Flight'     => 'フライト',
            'Airline:'   => ['航空会社:'],
            'Aircraft:'  => '機材:',
            'Fare type:' => '運賃の種別:',
            // 'Fare / Cabin:' => '',
            'Class'                 => '予約クラス指定',
            'Trip status:'          => '旅行のステータス:',
            'Traveller Information' => ['旅行者'],
            'Frequent flyer(s)'     => 'フリークエントフライヤー',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => '所要時間:',
            'Total for all travellers' => ['全旅行者の合計'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => '連絡先',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'th' => [
            'Booking reference:' => ['หมายเลขยืนยันการจอง:', 'รหัสการจอง:'],
            'Document'           => 'เอกสารหมายเลข',
            //			'Traveler' => '',
            //			'Seat Request' => [''],
            'Seat'       => 'หมายเลขที่นั่ง',
            'Departure:' => ['ออกเดินทาง:'],
            'Arrival:'   => 'เดินทางถึง:',
            'Terminal'   => 'อาคารผู้โดยสาร',
            'Flight'     => 'เที่ยวบิน',
            'Airline:'   => ['สายการบิน'],
            'Aircraft:'  => 'เครื่องบิน',
            'Fare type:' => 'ประเภทค่าโดยสาร:',
            // 'Fare / Cabin:' => '',
            'Class'                 => 'ชั้น',
            'Trip status:'          => ['สถานะการเดินทาง:', 'สถานะการจอง:'],
            'Traveller Information' => ['ข้อมูลผู้เดินทง', 'ข้อมูลผู้เดินทาง'],
            //			'Frequent flyer(s)' => '',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => 'ระยะเวลา',
            'Total for all travellers' => ['จำนวนรวมผู้เดินทางทั้งหมด'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'ข้อมูลการติดต่อ',
            'Operated by'         => 'ปฏิบัติการโดย',
            //            'Cancellation Reservation'      => '',
        ],
        'ru' => [
            'Booking reference:' => ['Номер заказа:', 'Номер бронирования:'],
            'Document'           => 'Документ пассажира',
            //			'Traveler' => '',
            'Seat Request' => ['Особые запросы'],
            'Seat'         => 'Место',
            'Departure:'   => ['Отправление:', 'Отправление'],
            'Arrival:'     => 'Прибытие:',
            'Terminal'     => 'терминал',
            'Flight'       => 'Авиарейс',
            'Airline:'     => ['Авиакомпания:'],
            'Aircraft:'    => ['Самолет:', 'Самолет'],
            'Fare type:'   => 'Тип тарифа:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => 'Статус поездки:',
            'Traveller Information' => ['информация о путешественнике', 'информация о пассажире', 'Пассажиры'],
            'Frequent flyer(s)'     => 'Постоянные клиенты авиакомпании',
            //			'Note:' => '',
            //			'Technical stop' => '',
            //			'Duration' => '',
            'Total for all travellers' => ['Итоговая сумма для всех пассажиров', 'итого для всех пассажиров'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'Контактная информация',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'id' => [
            'Booking reference:' => ['Nomor reservasi pemesanan:'],
            'Document'           => 'Dokumen',
            //			'Traveler' => '',
            'Seat Request' => ['Permintaan khusus penerbangan'],
            'Seat'         => 'Wisatawan',
            'Departure:'   => ['Keberangkatan:'],
            'Arrival:'     => 'Kedatangan:',
            'Terminal'     => 'terminal',
            'Flight'       => 'Penerbangan',
            'Airline:'     => ['Maskapai:'],
            'Aircraft:'    => 'Pesawat:',
            'Fare type:'   => 'Tipe tarif:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => 'Status perjalanan:',
            'Traveller Information' => ['informasi wisatawan'],
            'Frequent flyer(s)'     => 'Frequent flyer',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration'                 => ['Durasi:', 'Durasi'],
            'Total for all travellers' => ['total untuk semua wisatawan', 'Total untuk semua penumpang'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'Informasi kontak',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'fi' => [
            'Booking reference:' => ['Varaustunnus:'],
            //			'Document' => '',
            //			'Traveler' => '',
            'Seat Request' => ['Lennon erityistoiveet'],
            //			'Seat' => '',
            'Departure:' => ['Lähtö:'],
            'Arrival:'   => 'Saapuminen:',
            'Terminal'   => 'terminaali',
            //			'Flight' => '',
            'Airline:'   => ['Lentoyhtiö:'],
            'Aircraft:'  => 'Konetyyppi:',
            'Fare type:' => 'Matkustusluokka:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            //			'Trip status:' => '',
            'Traveller Information' => ['Matkustajat'],
            //			'Frequent flyer(s)' => '',
            //			'Note:' => '',
            //			'Technical stop' => '',
            'Duration' => ['Kesto:'],
            //			'Total for all travellers' => [''],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'yhteystiedot',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'sl' => [
            'Booking reference:' => ['Koda rezervacije:'],
            //			'Document' => '',
            //			'Traveler' => '',
            //			'Seat Request' => [''],
            //			'Seat' => '',
            'Departure:' => ['Odhod:'],
            'Arrival:'   => 'Prihod:',
            'Terminal'   => 'Terminal',
            'Flight'     => 'Let',
            'Airline:'   => ['Letalska družba:'],
            'Aircraft:'  => ['Letalo:', 'Letalo'],
            'Fare type:' => 'Skupina cen:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'          => 'Status potovanja:',
            'Traveller Information' => ['informacija o potniku'],
            'Frequent flyer(s)'     => 'Frequent flyer(s):',
            //			'Note:' => '',
            //			'Technical stop' => '',
            //			'Duration' => [''],
            'Total for all travellers' => ['skupaj za vse potnike'],
            //			'Total taxes, fees and charges' => '',
            'Contact Information' => 'Kontaktne informacije',
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        'sv' => [
            'Booking reference:'       => ['Bokningsreferens', 'Bokningsnummer'],
            'Departure:'               => ['Avr:'],
            'Arrival:'                 => ['Ank:'],
            'Airline:'                 => ['Flygbolag:'],
            'Aircraft:'                => ['Flygplan:', 'Flygplan'],
            'Trip status:'             => ['Resestatus:'],
            'Traveller Information'    => ['Passageraruppgifter', 'resenärsinformation'],
            'Frequent flyer(s)'        => 'Bonuskort',
            // 'Note:' => '',
            // 'Technical stop' => '',
            'Duration'                 => 'Restid',
            'Total for all travellers' => ['Totalt för alla resenärer'],
            //            'Seat Request'             => [''],
            'Contact Information'      => ['Kontaktinformation'],
            'Fare / Cabin:'            => ['Pristyp:'],
            'Class'                    => ['Klass:', 'Klass'],
            //            'Cancellation Reservation'      => '',
        ],
        'kk' => [
            'Booking reference:'       => ['Брондау нөмірі:'],
            //			'Document' => '',
            //			'Traveler' => '',
            //			'Seat Request' => [''],
            //			'Seat' => '',
            'Departure:'               => ['Ұшу уақыты'],
            'Arrival:'                 => ['Келу:'],
            'Terminal'                 => 'терминал',
            'Flight'                   => 'Let',
            'Airline:'                 => ['Әуекомпания'],
            'Aircraft:'                => ['Ұшақ'],
            'Fare type:'               => 'Skupina cen:',
            // 'Fare / Cabin:' => '',
            //			'Class' => '',
            'Trip status:'             => ['Брондау статусы:'],
            'Traveller Information'    => ['Саяхат туралы мәлімет'],
            //            'Frequent flyer(s)'     => '',
            //			'Note:' => '',
            //			'Technical stop' => '',
            //			'Duration' => [''],
            //            'Total for all travellers' => [''],
            //			'Total taxes, fees and charges' => '',
            'Contact Information'      => ['Байланыс ақпараты'],
            'Class'                    => ['Тариф түрі'],
            //			'Operated by' => '',
            //            'Cancellation Reservation'      => '',
        ],
        // shoul be last
        'en' => [
            'Booking reference:'            => ['Booking reference:', 'Booking reservation number:', 'Airline confirmation number(s):', 'Booking code:', 'Reservation Code:', 'Reference number (PNR):', 'Booking number:', 'Booking Reservation Number:', 'Booking reference :', 'Reservation code :', 'Reservation number:'],
            'Departure:'                    => ['Departure:', 'Departure :', 'Departure'],
            'Arrival:'                      => ['Arrival:', 'Arrival :', 'Arrival'],
            'Airline:'                      => ['Airline:', 'Flight:', 'Airline', 'Flight No.:', 'Flight No.', "Flight Number:", 'Flight Number', 'Airline & Flight Number'],
            'Aircraft:'                     => ['Aircraft:', 'Aircraft :', 'Plane:', 'Plane :', 'Aircraft'],
            'Trip status:'                  => ['Trip status:', 'Purchase Status:', 'Booking status:', 'Payment status:', 'Reservation status:', 'Status :'],
            'Traveller Information'         => ['Traveller Information', 'Traveller information', 'Information about Passengers', 'TRAVELER INFORMATION', 'Traveler information', 'traveller information', 'PASSENGER INFORMATION', 'Passenger details', 'Passenger Details', 'Passenger information', 'Traveller details', 'traveller details', 'Traveler Information', 'TRAVELLER INFORMATION'],
            'Frequent flyer(s)'             => ['Frequent flyer(s)', 'Frequent flyer number :'],
            'Technical stop'                => ['technical stop(s)', 'Technical stop'],
            'Duration'                      => ['Duration:', 'Duration :', 'Duration'],
            'Total for all travellers'      => ['Total for all travellers', 'Total for all passengers', 'total for all travellers', 'Total for all travelers', 'Total for all the passengers', 'Total for all Passengers', 'Total including taxes and surcharges for all travellers', 'Total for All Travellers', 'total for traveller(s)', 'amount for all passengers'],
            'Total taxes, fees and charges' => ['Total taxes, fees and charges', 'Total government taxes & fees and carrier imposed fees & surcharges'],
            'Seat Request'                  => ['Seat Request', 'Seat request', 'seat request', 'Seat:', 'Services'],
            'Contact Information'           => ['information', 'Information', 'Contact Details'],
            'Fare / Cabin:'                 => ['Fare / Cabin:', 'Fare / Cabin', 'Fare type/Cabin:', 'Fare type/Cabin', 'Cabin Class:', 'Cabin Class :'],
            'Class'                         => ['Class:', 'Class', 'Travel class:', 'Booking Class:', 'Booking Class :'],
            'Document'                      => ['Document', 'E-ticket number', 'E-Ticket'],
            'Seat'                          => ['Seat', 'Seats'],
            'Cancellation Reservation'      => ['CANCELLATION OF RESERVATION', 'Your trip has been cancelled'],
        ],
    ];
    private $from = [
        '@tap.', '@amadeus.', '@csair.', '@sata.pt', 'thaiairways.com', '@wias.no', '@wideroe.no', '@tarom.ro', '@garuda-indonesia.com',
        'airastana.com', '@finnair.com', '@luxair.lu', '@egyptair.com', 'qatarairways.com', '@malaysiaairlines.com',
        '@royalairmaroc.com', '@airalgerie.dz', '@airgreenland.gl', '@airtahiti.pf', '@airtahitinui.com',
    ];
    private $subject = ['Confirmation for reservation ',
        'Changes to special request for reservation', 'Flight confirmation ref',
        'pt' => 'Alterações de pedidos especiais da reserva', 'Cambios en la solicitud especial de la reserva ',
        'Modifiche alle informazioni passeggero per la prenotazione ',
        'Modifications des informations sur les passagers pour la réservation ',
        'Billet électronique EVA Air', 'EVA Air Electronic Ticket Service Information', '中華航空訂位記錄',
        'Änderungen der Passagierdaten',
        'Changes to passenger information',
        'Confirmation de réservation et',
        'Modification de la requête particulière pour la',
        'Änderungen der Sonderanfrage für Reservierung',         //de
        'Reconocimiento de los cambios de horario de la reserva',  //es
        'Qatar Itinerary',
        'Acknowledgment for schedule changes for reservation',
        'China Airline Reservation Record',
        'pt1' => 'Confirmação da reserva',
        'no'  => 'Bekreftelse for reservasjon',
        'ro'  => 'Confirmare pentru rezervarea',
        'zh'  => '長榮航空手機購票訊息',
        'zh2' => '确认预订',
        'nl'  => 'Bevestiging van reservering',
        'ja'  => '旅行日程表',
        'th'  => 'การยันยันการจอง',
        'ru'  => 'Подтверждение для заказа',
        'fr'  => 'Modifications des informations sur les passagers pour la réservation',
        'fr1' => 'Confirmation de réservation',
        'id'  => 'Konfirmasi reservasi',
        'fi'  => 'Vahvistus varaukselle',
        'it'  => 'Conferma prenotazione',
        'es'  => 'Confirmación de la reserva',
        'it1' => 'Modifiche alle informazioni passeggero per la',
        'sv'  => 'Bekräftelse av bokning',
    ];
    private $body = [
        'en' => ['Thank you for using EVA Air Award Booking system', 'reservation, you can go to ', 'Thank you for choosing ', 'Trip Information', 'Changes to special requests', 'Your flight selection', 'Thank you for booking your flights with', 'Changes to passenger information'],
        'de' => ['Aktuelle Informationen zu Ihrer Reservierung finden', 'Danke, dass Sie sich für ', 'Änderungen an den Sonderanfragen', 'Bestätigung der Reservierung'],
        'pt' => ['Informação da viagem', 'sobre a sua reserva, vá a ', 'para fazer sua reserva de viagem', 'sobre sua reserva, siga para ', 'para fazer a sua reserva de viagem', 'Obrigado por escolher a'],
        'es' => ['reserva, puede ir a ', 'Gracias por haber elegido', 'GRACIAS POR ELEGIRNOS', 'Información del viaje'],
        'it' => ['sulla prenotazione, passare a ', 'Grazie per aver scelto', 'Grazie per avere scelto', 'Modifiche alle informazioni passeggero'],
        'fr' => ['à votre réservation, allez sur', 'de réservation par internet ', "Confirmation de la réservation", 'sur le voyageur', "Merci d'avoir choisi"],
        'zh' => ['感謝您使用 中華航空公司', '訂位完成', '感謝您使用', '确认预订', '變更特殊要求'],
        'no' => ['Takk for at du velger', 'Takk for at du bruker'],
        'ro' => ['Confirmarea rezervării', 'informaţii călători'],
        'nl' => ['Bevestiging voor reservering', 'Reserveringsnummer boeking'],
        'ja' => ['旅行のステータス', '旅行日程表'],
        'th' => ['หมายเลขยืนยันการจอง', 'การยืนยันการจอง'],
        'ru' => ['Благодарим за выбор', 'Номер заказа', 'Подтверждение бронирования'],
        'id' => ['informasi wisatawan', 'Nomor reservasi pemesanan'],
        'fi' => ['Kiitos varauksestasi', 'Matkustajat'],
        // 'sl' => ['Kiitos varauksestasi', 'Matkustajat'],
        'sv' => ['Bokningsbekräftelse', 'Bekräftelse av bokning', 'Tack för att du valde'],
        'kk' => ['Бағдар құжатын көру'],
    ];

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->from) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers["from"]) && isset($headers["subject"])
                && $this->arrikey($headers["from"], $this->from) !== false && $this->arrikey($headers["subject"], $this->subject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->arrikey($parser->getHTMLBody(), self::$provider) !== false && $this->arrikey($parser->getHTMLBody(), $this->body);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $its = $this->parseEmail();

        return [
            'providerCode' => $this->getProviderCode($parser->getHTMLBody()),
            'emailType'    => 'AirTicketHtml2016' . ucfirst($this->lang),
            'parsedData'   => ['Itineraries' => $its],
        ];
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
        return array_keys(self::$provider);
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } elseif (stripos($haystack, $needles) !== false) {
                return $key;
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking reference:'], $words['Departure:'])) {
                if ($this->http->XPath->query("//*[{$this->xpathArray($words['Booking reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->xpathArray($words['Departure:'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(): array
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[{$this->xpathArray($this->t('Booking reference:'))}]/ancestor::td[1])[1]",
            null, true, "/{$this->opt($this->t('Booking reference:'))}\s*(.+)/u");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("(//node()[not(.//td)][{$this->xpathArray($this->t('Booking reference:'), 'normalize-space()', 'starts-with')}]/ancestor-or-self::td[1])[1]",
                null, true, "/{$this->opt($this->t('Booking reference:'))}\s*(.+)/u");
        }

        if (!preg_match('#^[\w\-/\\\.?]{5,6}$#u', $it['RecordLocator'])) {
            // it-10214111.eml
            $it['RecordLocator'] = preg_replace(['/(\x{200e}|\x{200f})/u', '/:/'], '', $it['RecordLocator']);
            // it-1680532.eml, "China Airlines Ltd. KHATJJ"
            $it['RecordLocator'] = trim(preg_replace('/^[\w\s\.]+\s+([A-Z\d]{5,6}).*?$/u', '$1', $it['RecordLocator']));
            // it-57344827.eml
            $it['RecordLocator'] = trim(preg_replace('/^\s*([A-Z\d]{5,6}).*?$/u', '$1', $it['RecordLocator']));
        }

        $travellers = $this->http->FindNodes(".//h3[contains(., '" . $this->t('Traveler') . "')]/text()", null, "#" . $this->t('Traveler') . " \d*: (.+)#");

        if (empty($travellers)) {
            $travellers = $this->http->FindNodes("//th[{$this->xpathArray($this->t('Document'), 'normalize-space(text())')}]/following-sibling::td[last()]");
        }

        if (empty($travellers)) {
            $passengers = array_filter($this->http->FindNodes("//text()[({$this->xpathArray($this->t('Traveller Information'))}) and (not(./ancestor::tr[1]/following-sibling::tr[1]))]/ancestor::table[1]/following::table[1]/descendant::tr[1]/ancestor::*[1]/tr[normalize-space()][count(.//td[normalize-space()])<2][not(" . $this->xpathArray($this->t('Contact Information')) . ")]"));

            foreach ($passengers as $passenger) {
                if (preg_match("#^(([\w\-]{2,4}[.]?\s+)?(?:\b[\w\-.]+\s*?){2,7})(\s*\(\w+\)\s*)?$#u", $passenger, $m)) {
                    $travellers[] = $m[1];
                }
                // LIONEL RANDALL Frequent flyer number : BR1307064205
                elseif (preg_match("/^([\w\s]+){$this->opt($this->t('Frequent flyer(s)'))}\s+(\w+)/", $passenger, $m)) {
                    $travellers[] = $m[1];
                    $it['AccountNumbers'][] = $m[2];
                }
            }
        }

        if (empty($travellers)) {
            $travellers = array_filter($this->http->FindNodes("//text()[({$this->xpathArray($this->t('Traveller Information'))}) and (not(./ancestor::tr[1]/following-sibling::tr[1]))]/ancestor::table[1]/following::table[1]/descendant::tr[1]/ancestor::*[1]/tr[normalize-space()]"
            . "//td[not(.//td) and string-length(normalize-space())>0 and ancestor::tr[1][not(" . $this->xpathArray([':', '@', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '/', '*']) . ")]][not(" . $this->xpathArray($this->t('Contact Information')) . ")]", null,
                    "#^(([\w\-]{2,4}[.]?\s+)?(?:\b[\w\-\.]+\s*?){2,7})(\s*\(\w+\)\s*)?$#u"));
        }

        foreach ($travellers as $tName) {
            $it['Passengers'][] = preg_replace("/^(?:Mlle|Mme|Sra|Sr|Г-н|M)[.\s]+(.{2,})$/iu", '$1', $tName);
        }

        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->xpathArray($this->t('Document'), 'normalize-space(.)', 'starts-with')}]", null, "#{$this->opt($this->t('Document'))}:?\s+(\d[\d\-]+)#"));
        $it['TicketNumbers'] = array_unique($ticketNumbers);

        $ffNumbers = array_filter($this->http->FindNodes("//td[({$this->xpathArray($this->t('Frequent flyer(s)'), 'normalize-space(.)', 'starts-with')}) and not(.//td)]/following-sibling::td[1]"));
        $ffNumbersList = $ffNumbers;
        $ffNumbers = [];

        foreach ($ffNumbersList as $ffNumber) {
            $ffNumbers = array_merge($ffNumbers, array_map("trim", explode(",", $ffNumber)));
        }
        $ffNumbers = array_unique($ffNumbers);

        if (!empty($ffNumbers)) {
            $it['AccountNumbers'] = $ffNumbers;
        }

        // Status : Waiting list, Please refer to the flight information below.
        $status = $this->http->FindSingleNode("(//text()[{$this->xpathArray($this->t('Trip status:'))}]/ancestor::td[1])[1]");

        if (preg_match("/{$this->opt($this->t('Trip status:'))}\s*(.+?),\s+/", $status, $m)) {
            $it['Status'] = $m[1];
        } elseif (preg_match("/{$this->opt($this->t('Trip status:'))}\s*(.+)/", $status, $m)) {
            $it['Status'] = $m[1];
        }

        if ($this->http->FindSingleNode("(//text()[{$this->xpathArray($this->t('Cancellation Reservation'))}])[1]")) {
            $it['Status'] = 'Cancelled';
            $it['Cancelled'] = true;
        }

        $pointsRequired = $this->http->FindSingleNode("//td[({$this->xpathArray($this->t('Total de pontos exigido para todos os passageiros:'), 'normalize-space()', 'starts-with')}) and not(.//td)]/following-sibling::td[string-length(normalize-space())>2][1]", null, true, '/^\d.*\D$/');

        if ($pointsRequired) {
            $it['SpentAwards'] = $pointsRequired;
        }

        $total = $this->http->FindSingleNode("//td[({$this->xpathArray($this->t('Total for all travellers'), 'normalize-space(.)', 'starts-with')}) and not(.//td)]/following-sibling::td[string-length(normalize-space(.)) > 2][1]");

        if ($total === null) {
            $total = $this->http->FindSingleNode("//td[({$this->xpathArray($this->t('Total for all travellers'), 'normalize-space(.)', 'starts-with')}) and not(.//td)]/preceding-sibling::td[string-length(normalize-space(.)) > 2][1]");
        }

        if (preg_match('/(?<amount>\d[,.\'\d ]*?)\s+(?<currency>[A-Z]{3})\b/', $total, $m)
            || preg_match('/\b(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*)\b/', $total, $m)
        ) {
            // 7,551 TWD    |    10,835,00 THB
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
            $m['amount'] = preg_replace('/^([\d, ]*\d),(\d{1,2})\s*$/', '$1.$2', $m['amount']);
            $it['TotalCharge'] = PriceHelper::parse($m['amount'], $currencyCode);
            $it['Currency'] = $m['currency'];

            $tax = $this->http->FindSingleNode("//td[({$this->xpathArray($this->t('Total taxes, fees and charges'), 'normalize-space(.)', 'starts-with')}) and not(.//td)]/following-sibling::td[string-length(normalize-space(.)) > 2][1]");
            $passengerCount = $this->http->FindSingleNode("//td[({$this->xpathArray($this->t('Total taxes, fees and charges'), 'normalize-space(.)', 'starts-with')}) and not(.//td)]/following-sibling::td[string-length(normalize-space(.)) > 2][1]"
                . "/ancestor::tr[1]/following-sibling::tr[(starts-with(normalize-space(), 'x ') or starts-with(normalize-space(), 'X ')) and count(td[normalize-space()]) = 1][1]", null, true, "/^\s*x (\d+) [[:alpha:]]+/iu");

            if (!empty($passengerCount) && preg_match('/(?<amount>\d[,.\'\d ]*?)\s*' . preg_quote($m['currency'], '/') . '/', $tax, $matches)) {
                $matches['amount'] = preg_replace('/^([\d, ]*\d),(\d{1,2})\s*$/', '$1.$2', $matches['amount']);
                $it['Tax'] = $passengerCount * PriceHelper::parse($matches['amount'], $currencyCode);
            }

            // Taxes
            $nodes = $this->http->XPath->query("//b[contains(text(),'Total Taxes')]/ancestor::tr[1]/following-sibling::tr");

            foreach ($nodes as $node) {
                $taxText = $this->http->FindSingleNode('*[2]', $node);

                if (preg_match('/(?<amount>\d[,.\'\d ]*?)\s*' . preg_quote($m['currency'], '/') . '/', $taxText, $matches)) {
                    $matches['amount'] = preg_replace('/^([\d, ]*\d),(\d{1,2})\s*$/', '$1.$2', $matches['amount']);
                    $it['Tax'] += PriceHelper::parse($matches['amount'], $currencyCode);
                }
            }
        }

        $xpath = "//tr[ *[normalize-space()][1][{$this->eq($this->t('Departure:'))}] ]/ancestor::tr[position()<3][count(descendant::tr/*[{$this->eq($this->t('Arrival:'))}])=1][1]";
        $roots = $this->http->XPath->query($xpath);

        $this->logger->debug('Segments found by: ' . $xpath);

        if ($roots->length === 0) {
            return [];
        }

        foreach ($roots as $root) {
            $seg = [];
            $timeDep = $this->getNode($this->t('Departure:'), $root);
            $timeDep = preg_replace("/^\s*下午 (\d{1,2}:\d{2})\s*$/", '$1 am', $timeDep);
            $timeArr = $this->getNode($this->t('Arrival:'), $root);
            $timeArr = preg_replace("/^\s*下午 (\d{1,2}:\d{2})\s*$/", '$1 am', $timeArr);

            $timeArrClear = preg_replace(['/\+\d+ .+/', '/\(\+\d+\)/'], '', $timeArr);

            $dateText = $this->http->FindSingleNode("ancestor-or-self::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][1]/*[2]", $root, true, '/^.*\d.*$/');
            // it-57294012.eml
            if (!$dateText) {
                $dateText = $this->http->FindSingleNode("td[2]/b[1]", $root);
            }

            $date = '';
//            $this->logger->debug('$dateText = '.print_r( $dateText,true));
            if (
                // วันพฤหัสบดี, 8 ธันวาคม 2016    |    星期六, 8 二月 2020    |    Tuesday, 19 February , 2019
                preg_match("/(?<Day>\d{1,2})[,.\s]+(?:\bde\b)?[,.\s]*(?<Month>[ธั[:alpha:]]+)[,.\s]+(?:\bde\b)?[,.\s]*(?<Year>\d{2,4})\b/u", $dateText, $m)
                // Thursday, June 23, 2016
                || preg_match("#,[ ]*(?<Month>[^\d\W]{3,})[ ]+(?<Day>\d{1,2})[ ]*,[ ]*(?<Year>\d{2}|\d{4})\b#u", $dateText, $m)
                // May 14,2020 Thursday
                || preg_match("#(?<Month>[^\d\W]{3,})\s+(?<Day>\d{1,2})\s*,\s*(?<Year>\d{2}|\d{4})\b#u", $dateText, $m)
                // Mar. 28,2020
                || preg_match("#(?<Month>[^\d\W]{3,})\.\s+(?<Day>\d{1,2})\s*,\s*(?<Year>\d{2}|\d{4})\b#u", $dateText, $m)
                // อา., 14 ก.พ. 2021
                || ($this->lang == 'th' && preg_match("#\D*,\s*(?<Day>\d{1,2})\s+(?<Month>\D+)\s+(?<Year>\d{4})\b#u", $dateText, $m))
            ) {
                $m['Month'] = trim($m['Month']);

                if (($monthNew = MonthTranslate::translate($m['Month'], $this->lang)) !== false) {
                    $date = $m['Day'] . ' ' . $monthNew . ' ' . $m['Year'];
                } else {
                    $date = $m['Day'] . ' ' . $m['Month'] . ' ' . $m['Year'];
                }
            } elseif (preg_match("#\b(?<Year>\d{4})年(?<Month>\d{1,2})月(?<Day>\d{1,2})日#u", $dateText, $m)// 2017年2月19日
                // 2023.11.26
                || preg_match("#^\s*(?<Year>\d{4})\.(?<Month>\d{1,2})\.(?<Day>\d{1,2})\s*$#u", $dateText, $m)
            ) {
                $m['Month'] = str_pad($m['Month'], 2, '0', STR_PAD_LEFT);
                $date = $m['Day'] . '.' . $m['Month'] . '.' . $m['Year'];
            } elseif (preg_match("#\b(?<Day>\d+) (?<Month>\w+) (?<Year>\d{2})$#", $dateText, $m)) {
                $date = $m['Day'] . ' ' . $this->dateStringToEnglish(trim($m['Month'])) . ' 20' . $m['Year'];
            }
//                $date = $m['Day'] . ' ' . \AwardWallet\Engine\MonthTranslate::translate(trim($m['Month']), $this->lang) . ' 20' . $m['Year'];

            if (!empty($date) && !empty($timeDep)) {
                $seg['DepDate'] = strtotime($date . ', ' . $timeDep, false);
                $seg['ArrDate'] = strtotime($date . ', ' . $timeArrClear, false);

                if (preg_match('/\+\s*(\d+)\s+.+/', $timeArr, $m)) {
                    $seg['ArrDate'] = strtotime("+{$m[1]} days", $seg['ArrDate']);
                }

                if (preg_match('/\(\+(\d+)\)/', $timeArr, $m)) {
                    $seg['ArrDate'] = strtotime("+{$m[1]} days", $seg['ArrDate']);
                }
            }

            $depN = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Departure:'))}]/following-sibling::*[normalize-space()][2]", $root);

            if (preg_match('#(.+), ' . $this->opt($this->t("Terminal")) . '\s+(.+)#is', $depN, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepartureTerminal'] = $m[2];
            } elseif ($this->lang === 'zh' && preg_match('#(.+), 第 (.+) ' . $this->opt($this->t("Terminal")) . '#is', $depN, $m)) {
                $seg['DepName'] = $m[1];
                $seg['DepartureTerminal'] = $m[2];
            } else {
                $seg['DepName'] = $depN;
            }

            $arrN = $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t('Arrival:'))}]/following-sibling::*[normalize-space()][2]", $root);

            if (($this->lang === 'zh' && preg_match('#(.+?)(?:, 第 (.+?)' . $this->opt($this->t("Terminal")) . ')(?:\s*(?:' . $this->opt($this->t("Note:")) . ').* (\d+) .+)?$#is', $arrN, $m))
                || preg_match('#(.+?)(?:, ' . $this->opt($this->t("Terminal")) . '\s+(.+?))?(?:\s*(?:' . $this->opt($this->t("Note:")) . ').* (\d+) .+)?$#is', $arrN, $m)
            ) {
                $seg['ArrName'] = $m[1];

                if (!empty($m[2])) {
                    $seg['ArrivalTerminal'] = $m[2];
                }

                if (!empty($m[3])) {
                    $seg['Stops'] = (int) $m[3];
                }
            }

            if (empty($seg['Stops'])) {
                $stops = $this->http->XPath->query(".//td[({$this->xpathArray($this->t('Technical stop'), 'normalize-space()', 'starts-with')}) and not(.//td) and ./following-sibling::td[contains(.,':') and string-length(normalize-space())>3]]", $root)->length;

                if ($stops > 0) {
                    $seg['Stops'] = $stops;
                }
            }

            $flightNumAirName = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::tr/*[{$this->eq($this->t('Airline:'))}]/following-sibling::*[normalize-space()][1]", $root)
            ?? $this->http->FindSingleNode("(descendant::tr/*[{$this->eq($this->t('Airline:'))}]/following-sibling::*[normalize-space()])[1]", $root);

            if (preg_match('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(\d+)\b/', $flightNumAirName, $math)) {
                $seg['AirlineName'] = $math[1];
                $seg['FlightNumber'] = $math[2];
            }

            $seg['Operator'] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<3]/descendant-or-self::tr/*[({$this->xpathArray($this->t('Operated by'), 'normalize-space()', 'starts-with')}) and not(.//tr)][1]", $root, true, "/{$this->opt($this->t('Operated by'))}\s*:*\s*(.{2,})$/i");

            $seg['BookingClass'] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<4]/descendant-or-self::tr/*[{$this->eq($this->t('Class'))}]/following-sibling::*[normalize-space()]", $root)
            ?? $this->http->FindSingleNode("(descendant::tr/*[{$this->eq($this->t('Class'))}]/following-sibling::*[normalize-space()])[1]", $root);

            if (!empty($seg['BookingClass']) && !preg_match("#^\s*[A-Z]{1,2}\s*$#u", $seg['BookingClass'])) {
                $seg['Cabin'] = trim($seg['BookingClass'], ' .');
                unset($seg['BookingClass']);
            }

            $seg['Aircraft'] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::tr/*[{$this->eq($this->t('Aircraft:'))}]/following-sibling::*[normalize-space()]", $root)
            ?? $this->http->FindSingleNode("(descendant::tr/*[{$this->eq($this->t('Aircraft:'))}]/following-sibling::*[normalize-space()])[1]", $root);

            $seg['Duration'] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::tr/*[{$this->eq($this->t('Duration'))}]/following-sibling::*[normalize-space()]", $root, true, '/^\d.*/')
            ?? $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::tr/*[({$this->xpathArray($this->t('Duration'))}) and not(.//tr)]/following-sibling::*[normalize-space()]", $root, true, '/^\d.*/')
            ?? $this->http->FindSingleNode("(descendant::tr/*[{$this->eq($this->t('Duration'))}]/following-sibling::*[normalize-space()])[1]", $root);

            if (empty($seg['Cabin'])) {
                $seg['Cabin'] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<4]/descendant-or-self::tr/*[{$this->eq($this->t('Fare type:'))}]/following-sibling::*[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("(descendant::tr/*[{$this->eq($this->t('Fare type:'))}]/following-sibling::*[normalize-space()])[1]", $root);
            }

            if (empty($seg['Cabin'])) {
                $seg['Cabin'] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<4]/descendant-or-self::tr/*[{$this->eq($this->t('Fare / Cabin:'))}]/following-sibling::*[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("(descendant::tr/*[{$this->eq($this->t('Fare / Cabin:'))}]/following-sibling::*[normalize-space()])[1]", $root);

                if (preg_match("#.+/\s*(.+)#u", $seg['Cabin'], $m)) {
                    $seg['Cabin'] = $m[1];
                }
            }

            if (strlen($seg['Cabin']) > 50) {
                $seg['Cabin'] = preg_replace("#^\s*(\S.+)/.*#u", '$1', $seg['Cabin']);
            }

            if (!empty($seg['DepName'])) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            if (!empty($seg['ArrName'])) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            }

            if (empty($seg['Seats']) && !empty($seg['DepName']) && !empty($seg['ArrName'])) {
                $city1 = preg_split('/\s*,\s*/', $seg['DepName'])[0];
                $city2 = preg_split('/\s*,\s*/', $seg['ArrName'])[0];

                $routes = [];
                $separators = [
                    ' - ',
                    ' to ', // en
                ];

                foreach ($separators as $separator) {
                    $routes[] = $city1 . $separator . $city2;
                }

                $seats = array_filter($this->http->FindNodes("//li[({$this->xpathArray($this->t('Seat Request'))}) and ({$this->xpathArray($routes, 'normalize-space(preceding-sibling::li[normalize-space()][1])')})]", null, "/:\s*(\d{1,5}[A-Z])\b/"));

                if (count($seats) === 0) {
                    //Flight 1: Amsterdam - Bucharest		Seat 8A
                    $seats = array_filter($this->http->FindNodes("//*[not(self::ul) and ({$this->xpathArray($this->t('Seat Request'))} or {$this->xpathArray($this->t('Seat'))}) and ({$this->xpathArray($routes, "normalize-space(preceding-sibling::*[not({$this->xpathArray($this->t('meal'))}) and not(.//table) and string-length(normalize-space())>2][1])")})]", null, "/(?:{$this->opt($this->t('Seat Request'))}|{$this->opt($this->t('Seat'))})\s*(\d{1,5}[A-Z])\b/"));
                }

                if (count($seats) === 0) {
                    // it-33791713.eml
                    $segSeats = [];
                    $seatRows = $this->http->XPath->query("//text()[({$this->xpathArray($this->t('Seat Request'))})]/following::tr[ *[1][({$this->xpathArray($routes)})] and *[2] ]");

                    foreach ($seatRows as $seatRow) {
                        $seatCells = array_filter($this->http->FindNodes('*[position()>1]', $seatRow, '/^\s*(\d{1,5}[A-Z])\s*$/'));

                        if (count($seatCells)) {
                            $segSeats = array_merge($segSeats, $seatCells);
                        }

                        $followRows = $this->http->XPath->query('following-sibling::tr', $seatRow);

                        foreach ($followRows as $row) {
                            if ($this->http->XPath->query("*[1][({$this->xpathArray($separators)})]", $row)->length > 0) {
                                break;
                            }
                            $seatCells = array_filter($this->http->FindNodes('*[position()>1]', $row, '/^\s*(\d{1,5}[A-Z])\s*$/'));

                            if (count($seatCells)) {
                                $segSeats = array_merge($segSeats, $seatCells);
                            }
                        }
                    }

                    if (count($segSeats)) {
                        $seats = $segSeats;
                    }
                }

                if (count($seats) > 0) {
                    $seg['Seats'] = array_values(array_unique($seats));
                }
            }

            $it['TripSegments'][] = $seg;
        }

        if (!empty($it['TripSegments']) && count($it['TripSegments']) === 1) {
            if ($miles = $this->http->FindSingleNode("//td[normalize-space(.)='Mileage']/following-sibling::td")) {
                $it['TripSegments'][0]['TraveledMiles'] = $miles;
            }
        }

        return [$it];
    }

    private function getNode($str, $root = null): ?string
    {
        return $this->http->FindSingleNode("(descendant::text()[{$this->xpathArray($str)}]/ancestor::td[1]/following-sibling::td[normalize-space()][1])[1]", $root);
    }

    private function getProviderCode($text)
    {
        $provider = $this->arrikey($text, self::$provider);

        return $provider ?? '';
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function xpathArray($array, $str1 = 'normalize-space(.)', $method = 'contains', $operator = 'or')
    {
        $arr = [];

        if (!is_array($array)) {
            $array = [$array];
        }

        foreach ($array as $str2) {
            $arr[] = "{$method}({$str1}, \"" . $str2 . "\")";
        }

        return join(" {$operator} ", $arr);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }
}
