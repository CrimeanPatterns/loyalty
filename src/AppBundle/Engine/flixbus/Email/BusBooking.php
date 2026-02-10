<?php

namespace AwardWallet\Engine\flixbus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BusBooking extends \TAccountChecker
{
    public $mailFiles = "flixbus/it-111317380.eml, flixbus/it-136765835.eml, flixbus/it-137381485.eml, flixbus/it-51783123.eml, flixbus/it-52817536.eml, flixbus/it-52824941.eml, flixbus/it-56504939.eml, flixbus/it-56603344.eml, flixbus/it-57617640.eml, flixbus/it-59005195.eml, flixbus/it-59207638.eml, flixbus/it-60712324.eml, flixbus/it-60986870.eml, flixbus/it-62593782.eml, flixbus/it-71007859.eml, flixbus/it-74390731.eml, flixbus/it-99403574.eml, flixbus/it-661689003.eml, flixbus/it-670797206.eml, flixbus/it-669650104.eml";

    private $detectSubject = [
        'en' => [
            'FlixBus Booking Confirmation', 'Invoices for your booking',
        ],
        'es' => [
            'Confirmación de reserva',
        ],
        'uk' => [
            'Номер бронювання FlixBus',
        ],
        'pt' => [
            'Confirmação de reserva FlixBus',
        ],
        'cs' => [
            'FlixBus potvrzení rezervace',
        ],
        'fr' => [
            'Confirmation de réservation FlixBus', 'Factures pour votre réservation',
        ],
        'de' => [
            'FlixBus Buchungsbestätigung', 'Rechnungen für Deine Buchung',
        ],
        'ru' => [
            'Ваш билет и подтверждение бронирования',
        ],
        'nl' => [
            'FlixBus boekingsbevestiging',
        ],
        'it' => [
            'FlixBus Conferma di Prenotazione',
        ],
        'sk' => [
            'Potvrdenie o rezervácii FlixBus č.', 'Potvrdenie rezervácie', 'Vaša nová rezervácia',
        ],
        'ca' => [
            'Número de confirmació de reserva de FlixBus:',
        ],
        'pl' => [
            'Potwierdzenie rezerwacji FlixBus #',
        ],
        'da' => [
            'FlixBus-bookingbekræftelse',
        ],
        'sv' => [
            'Fakturor för din bokning',
        ],
    ];

    private $detectCompany = ['FlixBus', '.flixbus.'];

    private $detectBodyHtml = [
        'en' => [
            'includes the following trips:',
        ],
        'es' => [
            'incluye los siguientes trayectos:',
        ],
        'uk' => [
            'включає такі автобусні подорожі:',
        ],
        'pt' => [
            'inclui as viagens seguintes:',
        ],
        'cs' => [
            'obsahuje tyto cesty:',
        ],
        'fr' => [
            'comprend les trajets suivants :',
        ],
        'de' => [
            'beinhaltet folgende Fahrt(en):',
        ],
        'ru' => [
            'включает следующие поездки:',
        ],
        'nl' => [
            'bevat de volgende reizen',
        ],
        'sk' => [
            'obsahuje tieto cesty:',
        ],
    ];
    private $detectBodyPdf = [
        'en' => [
            'Tickets and invoices for your booking',
            'Cancellation invoices for your booking', 'Cancelation invoices for your booking',
            'Invoices for your booking',
            'This QR-code helps us to check',
        ],
        'es' => [
            'Billetes y facturas de tu reserva',
        ],
        'uk' => [
            'Квитки та інвойси для вашого бронювання',
        ],
        'pt' => [
            'Bilhetes e faturas para a tua reserva',
            'Bilhetes de passagem eletrônicos',
            'A TUA CONFIRMAÇÃO DE RESERVA',
            'SUA CONFIRMAÇÃO DE RESERVA',
        ],
        'cs' => [
            'Jízdenky a faktury vystavené k Vaší rezervaci',
            'ČÍSLO REZERVACE:',
        ],
        'fr' => [
            'Ce code QR vous permet',
            'Factures pour votre réservation',
            'Billets et factures pour votre réservation',
        ],
        'de' => [
            'Tickets und rechnungen für deine buchung',
            'Rechnungen für Deine Buchung',
            'DEINE BUCHUNGSBESTÄTIGUNG',
        ],
        'ru' => [
            'Билеты и квитанции для вашего бронирования',
        ],
        'it' => [
            'Fattura per la tua prenotazione',
            'LA TUA PRENOTAZIONE',
        ],
        'hu' => [
            'Nyomtatott és digitális formában is érvényes',
        ],
        'sv' => [
            'Fakturor för din bokning',
        ],
    ];

    private $pdfPattern = ".*\.pdf";
    private $lang = 'en';
    private $travellers = [];
    private static $dictionary = [
        'en' => [
            // Html
            //            "includes the following trips:" => "",
            //            "Line" => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "",
            //            "BOOKING NUMBER:" => "",
            //            "DEPARTURE" => "",
            "Please note"    => ["Please note", "Operated by", "The line ", "The platform number", "Tickets are"],
            "Bus connection" => ["Bus connection", "Connection"],
            //            "Train connection" => "",
            //            "TRANSFER IN" => "",
            //            "ARRIVAL" => "",
            //            "Seat:" => "",
            //            "Seats" => "",
            "Tickets and invoices for your booking" => [
                "Tickets and invoices for your booking", "Invoices for your booking", "Tax Invoices for your booking",
                "Cancellation invoices for your booking", "Cancelation invoices for your booking",
            ],
            "Invoice" => ["Invoice", "Cancellation Invoice", "Cancelation Invoice", "Cancellation Tax Invoice", "Cancelation Tax Invoice"],
            //            "Booking number:" => "", // from invoice
            //            "COUNTRY" => "",
            //            "NET" => "",
            //            "VAT" => "",
            //            "GROSS" => "",
            //            "Total" => "",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "BOARDING PASS",
            //            "Train" => "",
            //            "Bus" => "",
            // "Route" => "",
            //            "Adults" => "",
            "Additional Information" => "Additional Information",
            //            "Total price:" => "",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "STREET / HOUSE NO.",
        ],
        'es' => [
            // Html
            "includes the following trips:" => "incluye los siguientes trayectos:",
            "Line"                          => "Línea",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "TU CONFIRMACIÓN DE RESERVA",
            "BOOKING NUMBER:"           => "NÚMERO DE RESERVA:",
            "DEPARTURE"                 => "SALIDA",
            "Please note"               => "Por favor,",
            "Bus connection"            => "Conexión de autobús",
            //            "Train connection" => "",
            //            "TRANSFER IN" => "",
            "ARRIVAL" => "LLEGADA",
            //            "Seat:" => "",
            "Seats"                                 => "Asiento",
            "Tickets and invoices for your booking" => "Billetes y facturas de tu reserva",
            "Invoice"                               => "Factura",
            // "Booking number:" => "",
            "COUNTRY"                               => "PAÍS",
            "NET"                                   => "NETO",
            "VAT"                                   => "IVA",
            "GROSS"                                 => "BRUTO",
            "Total"                                 => "Total",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "BILLETE",
            //            "Train" => "",
            "Bus"                    => "Autobús",
            "Route"                  => "Ruta",
            "Adults"                 => ["Adultos", "Adulto"],
            "Additional Information" => "información adicional",
            "Total price:"           => "Precio total:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "CALLE/N.º",
        ],
        'uk' => [
            // Html
            "includes the following trips:" => "включає такі автобусні подорожі:",
            "Line"                          => "Рейс",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "ПІДТВЕРДЖЕННЯ ВАШОГО",
            "BOOKING NUMBER:"           => "НОМЕР БРОНЮВАННЯ:",
            "DEPARTURE"                 => "ВІДПРАВЛЕННЯ",
            //            "Please note" => "",
            "Bus connection" => "Автобусний маршрут",
            //            "Train connection" => "",
            //            "TRANSFER IN" => "",
            "ARRIVAL" => "ПРИБУТТЯ",
            //            "Seat:" => "",
            //            "Seats" => "",
            "Tickets and invoices for your booking" => "Квитки та інвойси для вашого бронювання",
            "Invoice"                               => "Інвойс",
            // "Booking number:" => "",
            "COUNTRY"                               => "КРАЇНА",
            "NET"                                   => "НЕТТО",
            "VAT"                                   => "ПДВ",
            //            "GROSS" => "",
            "Total" => "Усього",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "ПОСАДКОВИЙ ТАЛОН",
            //            "Train" => "",
            "Bus"                    => "Автобус",
            // "Route" => "",
            "Adults"                 => "Дорослі",
            "Additional Information" => "Додаткова інформація",
            "Total price:"           => "Вартість:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "№ ВУЛ. / БУД.",
        ],
        'pt' => [
            // Html
            "includes the following trips:" => "inclui as viagens seguintes:",
            "Line"                          => "Linha",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => ["A TUA CONFIRMAÇÃO DE RESERVA", "SUA CONFIRMAÇÃO DE RESERVA"],
            "BOOKING NUMBER:"           => "NÚMERO DE RESERVA:",
            "DEPARTURE"                 => ["PARTIDA", "SAÍDA"],
            //            "Please note" => "",
            "Bus connection" => ["Ligação de autocarro", "Linha de ônibus", "Linha"],
            //            "Train connection" => "",
            //            "TRANSFER IN" => "",
            "Seat:" => ["Lugar:", "Assento:"],
            //            "Seats" => "",
            'Adult'                                 => 'Adulto',
            'Ticket'                                => 'Bilhete de passagem',
            "ARRIVAL"                               => "CHEGADA",
            "Tickets and invoices for your booking" => ["Bilhetes e faturas para a tua reserva", "Bilhetes de passagem eletrônicos (faturas) de sua"],
            "Invoice"                               => ["Fatura", "fatura"],
            // "Booking number:" => "",
            "COUNTRY"                               => ["PAÍS"],
            "NET"                                   => "LÍQUIDO",
            "VAT"                                   => "IVA",
            "GROSS"                                 => "BRUTO",
            "Total"                                 => "Total",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS"          => ["PASSAGEM", "BILHETE"],
            "Train"                  => "Trem",
            "Bus"                    => ["Ônibus", "Autocarro"],
            // "Route" => "",
            "Adults"                 => "Adultos",
            "Additional Information" => ["Informações adicionais", 'Informação adicional'],
            "Total price:"           => "Preço total:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "RUA / N.º CASA",
        ],
        'cs' => [
            // Html
            "includes the following trips:" => "obsahuje tyto cesty:",
            "Line"                          => "Spoj",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "POTVRZENÍ REZERVACE",
            "BOOKING NUMBER:"           => "ČÍSLO REZERVACE:",
            "DEPARTURE"                 => "ODJEZD",
            "Please note"               => "Pamatuj,",
            "Bus connection"            => "Autobusový spoj",
            //            "Train connection" => "",
            //            "TRANSFER IN" => "",
            //            "Seats" => "",
            "Seat:"                                 => "Sedadlo:",
            "ARRIVAL"                               => "PŘÍJEZD",
            "Tickets and invoices for your booking" => "Jízdenky a faktury vystavené k Vaší rezervaci",
            "Invoice"                               => "Faktura",
            // "Booking number:" => "",
            "COUNTRY"                               => "ZEMĚ",
            "NET"                                   => "BEZ DANĚ",
            "VAT"                                   => "DPH",
            //            "GROSS" => "",
            "Total" => "Celkem",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            //            "BOARDING PASS" => "",
            //            "Train" => "",
            //            "Bus" => "",
            // "Route" => "",
            //            "Adults" => "",
            //            "Additional Information" => "",
            //            "Total price:" => "",

            // pdf Luggage tags
            //            "STREET / HOUSE NO." => "",
        ],
        'fr' => [
            // Html
            "includes the following trips:" => "comprend les trajets suivants :",
            "Line"                          => "Ligne",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "VOTRE CONFIRMATION DE",
            "BOOKING NUMBER:"           => "NUMÉRO DE COMMANDE:",
            "DEPARTURE"                 => "DÉPART",
            //            "Please note" => "",
            "Bus connection" => ["Liaison par bus"],
            //            "Train connection" => "",
            "TRANSFER IN"                           => "CHANGEMENT À",
            "ARRIVAL"                               => "ARRIVÉE",
            "Seat:"                                 => "Siège:",
            "Seats"                                 => "Sièges",
            "Tickets and invoices for your booking" => ["Billets et factures pour votre réservation", "Factures pour votre réservation"],
            "Invoice"                               => "Facture",
            "Booking number:"                       => "Numéro de commande:",
            "COUNTRY"                               => "PAYS",
            "NET"                                   => "NET",
            "VAT"                                   => ["TAXE", "TVA"],
            "GROSS"                                 => "BRUT",
            "Total"                                 => "Total",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "BILLET",
            //            "Train" => "",
            "Bus"                    => "Bus",
            // "Route" => "",
            "Adults"                 => "Adultes",
            "Additional Information" => "Informations supplémentaires :",
            "Total price:"           => "Prix total :",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "RUE / NUMÉRO",
        ],
        'de' => [
            // Html
            "includes the following trips:" => "beinhaltet folgende Fahrt(en):",
            "Line"                          => "Linie",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "DEINE BUCHUNGSBESTÄTIGUNG",
            "BOOKING NUMBER:"           => "BUCHUNGSNUMMER:",
            "DEPARTURE"                 => "AB",
            //            "Please note" => [],
            "Bus connection" => ["Busverbindung"],
            //            "Train connection" => "",
            //            "TRANSFER IN" => "",
            "ARRIVAL"                               => "AN",
            "Seat:"                                 => "Sitz:",
            "Seats"                                 => "Sitz",
            "Tickets and invoices for your booking" => [
                "Tickets und rechnungen für deine buchung", "Rechnungen für Deine Buchung",
                "Stornorechnungen für Deine Buchung",
            ],
            "Invoice"                               => ["Rechnung", "Stornobeleg"],
            "Booking number:"                       => "Buchungsnummer:",
            "COUNTRY"                               => "LAND",
            "NET"                                   => "NETTO",
            "VAT"                                   => "MWST.",
            "GROSS"                                 => "BRUTTO",
            "Total"                                 => "Gesamtpreis",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS"          => ["BORDKARTE", "TICKET"],
            "Train"                  => "Zug",
            "Bus"                    => "Bus",
            "Route"                  => "Strecke",
            "Adults"                 => ["Erwachsene", "Erwachsener"],
            "Additional Information" => "Weitere Informationen",
            "Total price:"           => "Gesamtpreis:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "STRASSE / NR.",
        ],
        'ru' => [
            // Html
            "includes the following trips:" => "включает следующие поездки:",
            "Line"                          => "Маршрут",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "ПОДТВЕРЖДЕНИЕ БРОНИРОВАНИЯ И",
            "BOOKING NUMBER:"           => "НОМЕР БРОНИРОВАНИЯ:",
            "DEPARTURE"                 => "ОТПРАВЛЕНИЕ В",
            //            "Please note" => [],
            "Bus connection" => ["Автобусный маршрут"],
            //            "Train connection" => "",
            "TRANSFER IN"                           => "ПЕРЕСАДКА В:",
            "ARRIVAL"                               => ["ПРИБЫТИЕ В:", "ПРИБЫТИЕ В"],
            "Seats"                                 => "Места",
            //            "Seat:" => "",
            "Tickets and invoices for your booking" => "Билеты и квитанции для вашего бронирования",
            "Invoice"                               => "Квитанция/билет",
            // "Booking number:" => "",
            "COUNTRY"                               => "СТРАНА",
            "NET"                                   => "НЕТТО",
            "VAT"                                   => "НДС",
            //            "GROSS" => "",
            "Total" => "Общая стоимость",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            //            "BOARDING PASS" => "",
            //            "Train" => "",
            //            "Bus" => "",
            // "Route" => "",
            //            "Adults" => "",
            //            "Additional Information" => "",
            //            "Total price:" => "",

            // pdf Luggage tags
            //            "STREET / HOUSE NO." => "",
        ],
        'nl' => [
            // Html
            "includes the following trips:" => "bevat de volgende reizen:",
            "Line"                          => "Lijn",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "JOUW BOEKINGSBEVESTIGING",
            "BOOKING NUMBER:"           => "BESTELNUMMER:",
            "DEPARTURE"                 => "VERTREK",
            //            "Please note" => [],
            "Bus connection" => "Bus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            "ARRIVAL" => "AANKOMST",
            "Seats"   => "Zitplaats",
            "Seat:"   => "Zitplaats:",
            "Adult"   => "Adulto",
            //"Tickets and invoices for your booking" => "Билеты и квитанции для вашего бронирования",
            //"Invoice" => "Квитанция/билет",
            //"Booking number:" => "",
            //"COUNTRY" => "СТРАНА",
            //"NET" => "НЕТТО",
            //"VAT" => "НДС",
            //            "GROSS" => "",
            "Total" => "Totaal",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            //            "BOARDING PASS" => "",
            //            "Train" => "",
            //            "Bus" => "",
            // "Route" => "",
            //            "Adults" => "",
            //            "Additional Information" => "",
            //            "Total price:" => "",

            // pdf Luggage tags
            //            "STREET / HOUSE NO." => "",
        ],
        'it' => [
            // Html
            //"includes the following trips:" => "",
            //"Line"                          => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            "BOOKING NUMBER:"           => ["NUMERO DI PRENOTAZIONE:", "NUMERO DI"],
            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            "Bus connection"   => "Linea dell'autobus",
            "Train connection" => "Tratta",
            //"TRANSFER IN" => "",
            "ARRIVAL" => "ARRIVO",
            //"Seats"   => "",
            "Seat:"                                 => "Posto a sedere:",
            "Tickets and invoices for your booking" => "Fattura per la tua prenotazione",
            "Invoice"                               => "Ricevuta",
            "Booking number:"                       => "Numero di prenotazione:",
            "COUNTRY"                               => "PAESE",
            "NET"                                   => "NETTO",
            "VAT"                                   => "IVA",
            "GROSS"                                 => "LORDO",
            "Total"                                 => "Totale",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "BIGLIETTO",
            //            "Train" => "",
            "Bus"                    => "Autobus",
            // "Route" => "",
            "Adults"                 => "Adulti/e",
            "Additional Information" => "Ulteriori informazioni",
            "Total price:"           => " Tariffa totale:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "STRADA/ NO CIVICO",
        ],
        'sk' => [
            // Html
            "includes the following trips:" => "obsahuje tieto cesty:",
            "Line"                          => "Linka",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            //            "BOOKING NUMBER:"           => "NUMERO DI PRENOTAZIONE:",
            //            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            //            "Bus connection" => "Linea dell'autobus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            //            "ARRIVAL" => "ARRIVO",
            "Seats" => "Miesto na sedenie",
            //            "Seat:"                                 => "Posto a sedere:",
            //            "Tickets and invoices for your booking" => "Fattura per la tua prenotazione",
            //"Invoice"                               => "Ricevuta",
            //"Booking number:" => "",
            //            "COUNTRY"                               => "PAESE",
            //            "NET"                                   => "NETTO",
            //            "VAT"                                   => "IVA",
            //            "GROSS"                                 => "LORDO",
            // "Total" => "",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "LÍSTOK",
            //            "Train" => "",
            "Bus"                    => "Autobus",
            "Route"                  => "Spoj",
            "Adults"                 => ["Dospelí", "Dospelý"],
            "Additional Information" => "Ďalšie informácie",
            "Total price:"           => "Celková cena:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "ULICA / BUDOVA Č.",
        ],
        'ca' => [
            // Html
            //            "includes the following trips:" => "",
            //            "Line"                          => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            //            "BOOKING NUMBER:"           => "NUMERO DI PRENOTAZIONE:",
            //            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            //            "Bus connection" => "Linea dell'autobus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            //            "ARRIVAL" => "ARRIVO",
            //"Seats"   => "",
            //            "Seat:"                                 => "Posto a sedere:",
            //            "Tickets and invoices for your booking" => "Fattura per la tua prenotazione",
            //"Invoice"                               => "Ricevuta",
            //"Booking number:" => "",
            //            "COUNTRY"                               => "PAESE",
            //            "NET"                                   => "NETTO",
            //            "VAT"                                   => "IVA",
            //            "GROSS"                                 => "LORDO",
            // "Total" => "",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS"          => "TARGETA D'EMBARCAMENT",
            "Train"                  => "Tren",
            "Bus"                    => "Autobús",
            // "Route" => "",
            "Adults"                 => "Adults",
            "Additional Information" => "Informació addicional",
            "Total price:"           => "Preu total:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "CARRER / NÚM.",
        ],
        'pl' => [
            // Html
            //            "includes the following trips:" => "",
            //            "Line"                          => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            //            "BOOKING NUMBER:"           => "NUMERO DI PRENOTAZIONE:",
            //            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            //            "Bus connection" => "Linea dell'autobus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            //            "ARRIVAL" => "ARRIVO",
            //"Seats"   => "",
            //            "Seat:"                                 => "Posto a sedere:",
            //            "Tickets and invoices for your booking" => "Fattura per la tua prenotazione",
            //"Invoice"                               => "Ricevuta",
            //"Booking number:" => "",
            //            "COUNTRY"                               => "PAESE",
            //            "NET"                                   => "NETTO",
            //            "VAT"                                   => "IVA",
            //            "GROSS"                                 => "LORDO",
            // "Total" => "",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "BILET",
            //            "Train" => "",
            "Bus"                    => "Autobus",
            // "Route" => "",
            "Adults"                 => "Dorośli",
            "Additional Information" => "Dodatkowe informacje",
            "Total price:"           => "Łącznie:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "ULICA / NR DOMU",
        ],
        'da' => [
            // Html
            //            "includes the following trips:" => "",
            //            "Line"                          => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            //            "BOOKING NUMBER:"           => "NUMERO DI PRENOTAZIONE:",
            //            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            //            "Bus connection" => "Linea dell'autobus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            //            "ARRIVAL" => "ARRIVO",
            //"Seats"   => "",
            //            "Seat:"                                 => "Posto a sedere:",
            //            "Tickets and invoices for your booking" => "Fattura per la tua prenotazione",
            //"Invoice"                               => "Ricevuta",
            //"Booking number:" => "",
            //            "COUNTRY"                               => "PAESE",
            //            "NET"                                   => "NETTO",
            //            "VAT"                                   => "IVA",
            //            "GROSS"                                 => "LORDO",
            // "Total" => "",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "BOARDINGPAS",
            //            "Train" => "",
            "Bus"                    => "Bus",
            // "Route" => "",
            "Adults"                 => "Voksne",
            "Additional Information" => "Yderligere oplysninger:",
            "Total price:"           => "Samlet pris:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "GADE/VEJ NR.",
        ],
        'hu' => [
            // Html
            //            "includes the following trips:" => "",
            //            "Line"                          => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            //            "BOOKING NUMBER:"           => "NUMERO DI PRENOTAZIONE:",
            //            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            //            "Bus connection" => "Linea dell'autobus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            //            "ARRIVAL" => "ARRIVO",
            //"Seats"   => "",
            //            "Seat:"                                 => "Posto a sedere:",
            //            "Tickets and invoices for your booking" => "Fattura per la tua prenotazione",
            //"Invoice"                               => "Ricevuta",
            //"Booking number:" => "",
            //            "COUNTRY"                               => "PAESE",
            //            "NET"                                   => "NETTO",
            //            "VAT"                                   => "IVA",
            //            "GROSS"                                 => "LORDO",
            // "Total" => "",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            "BOARDING PASS" => "JEGY",
            //            "Train" => "",
            "Bus"                    => "Busz",
            // "Route" => "",
            "Adults"                 => "Felnőtt",
            "Additional Information" => "További információk",
            "Total price:"           => "Teljes ár:",

            // pdf Luggage tags
            "STREET / HOUSE NO." => "UTCA / HÁZSZÁM",
        ],
        'sv' => [
            // Html
            //            "includes the following trips:" => "",
            //            "Line"                          => "",
            //            "(train)" => "",
            //            "Coach" => "",
            // Pdf
            //            "YOUR BOOKING CONFIRMATION" => "LA TUA PRENOTAZIONE",
            //            "BOOKING NUMBER:"           => "Bokningsnummer:",
            //            "DEPARTURE"                 => "PARTENZA",
            //            "Please note" => [],
            //            "Bus connection" => "Linea dell'autobus",
            //            "Train connection" => "",
            //"TRANSFER IN" => "",
            //            "ARRIVAL" => "ARRIVO",
            //"Seats"   => "",
            //            "Seat:"                                 => "Posto a sedere:",
            "Tickets and invoices for your booking" => "Fakturor för din bokning",
            "Invoice"                               => "Kvitto",
            "Booking number:"                       => "Bokningsnummer:",
            "COUNTRY"                               => "LAND",
            "NET"                                   => "NETTO",
            "VAT"                                   => "VAT",
            "GROSS"                                 => "BRUTTO",
            "Total"                                 => "Totalt",

            // Pdf, Type 2: the sheet is divided into 4 equal parts
            //            "BOARDING PASS" => "",
            //            "Train" => "",
            //            "Bus" => "",
            // "Route" => "",
            //            "Adults" => "",
            //            "Additional Information" => "",
            //            "Total price:" => "",

            // pdf Luggage tags
            //            "STREET / HOUSE NO." => "",
        ],
    ];

    private $patterns = [
        'time' => '\b\d{1,2}[.:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|\b)?', // 4:19PM    |    2:00 p. m.    |    17.25
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = '';
        $error = false;
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['STREET / HOUSE NO.']) && $this->strposAll($text, $dict['STREET / HOUSE NO.']) === true
                    && preg_match_all("/\n\n\n( {0,15}[[:alpha:]](?:.*\n+){1,3})[ ]+{$this->preg_implode($dict['STREET / HOUSE NO.'])}/u", $text, $m)
                ) {
                    $this->travellers = array_unique(array_map('trim', preg_replace(["/^(.{15,}?)[ ]{3}.*/m", '/\s+/'], ['$1', ' '], $m[1])));
                }

                if (empty($dict['BOARDING PASS']) || empty($dict['Additional Information'])) {
                    continue;
                }

                if ($this->strposAll($text, $dict['BOARDING PASS']) === true
                    && $this->strposAll($text, $dict['Additional Information']) === true
                ) {
                    $this->lang = $lang;
                    $this->parsePdf2($email, $text);
                    $type = 'Pdf2';

                    continue 2;
                }
            }

            foreach ($this->detectBodyPdf as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        if ($type !== 'Pdf2') {
                            $this->lang = $lang;

                            if (!$this->parsePdf($email, $text)) {
                                $this->logger->info("parsePdf is failed'");
                                $error = true;
                            }
                            $type = 'Pdf1';

                            continue 3;
                        } else {
                            $this->parsePdf2Invoice($email, $text);

                            continue 3;
                        }
                    }
                }
            }
        }

        if ($type === 'Pdf2' && count($this->travellers) > 0) {
            foreach ($email->getItineraries() as $it) {
                $itTravellers = array_column($it->getTravellers(), 0);

                if (count($itTravellers) === 0) {
                    $it->general()->travellers($this->travellers);

                    continue;
                }

                foreach ($this->travellers as $newName) {
                    $travellerFound = false;

                    foreach ($itTravellers as $name) {
                        if (strcasecmp($newName, $name) === 0) {
                            $travellerFound = true;

                            break;
                        }
                    }

                    if ($travellerFound === false) {
                        $it->general()->traveller($newName);
                    }
                }
            }
        }

        if ($error == true || empty($email->getItineraries())) {
            if (!empty($email->getItineraries())) {
                $email->clearItineraries();
            }
            $body = html_entity_decode($this->http->Response["body"]);

            foreach ($this->detectBodyHtml as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (stripos($body, $dBody) !== false
                        || !empty($this->http->FindSingleNode("(//*[" . $this->contains($dBody) . "])[1]"))) {
                        $this->lang = $lang;

                        break;
                    }
                }
            }
            $type = 'Html';
            $this->parseHtml($email);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]flixbus\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->detectSubject as $lang => $detectSubject) {
            foreach ($detectSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false
                    && (stripos($headers["subject"], 'flixbus') !== false
                        || !empty($headers['from']) && stripos($headers['from'], 'flixbus') !== false
                    )
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        foreach ($this->detectCompany as $dc) {
            if (strpos($body, $dc) !== false) {
                foreach ($this->detectBodyHtml as $detectBody) {
                    foreach ($detectBody as $dBody) {
                        if (strpos($body, $dBody) !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                continue;
            }

            foreach ($this->detectBodyPdf as $lang => $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($text, $dBody) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
            $part = substr($text, 0, 100);

            foreach (self::$dictionary as $lang => $dict) {
                if (!isset($dict['BOARDING PASS'])) {
                    continue;
                }

                foreach ((array) $dict['BOARDING PASS'] as $bpText) {
                    if (strpos($part, $bpText) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parsePdf(Email $email, string $text): bool
    {
        $this->logger->debug(__FUNCTION__);

        $routes = array_filter(preg_split("/^[ ]*" . $this->preg_implode($this->t("YOUR BOOKING CONFIRMATION")) . "\b.*/m", $text));

        if (empty($routes)) {
            $this->logger->debug(__FUNCTION__ . ': $routes is empty');

            return false;
        }

        $b = $email->add()->bus();
        $t = $email->add()->train();

        if (preg_match("/\n\s*" . str_replace(' ', '(?: |[ ]{3,}.*\n[ ]*)', $this->preg_implode($this->t("BOOKING NUMBER:"))) . "\s+(?:.*\n){1,4}[ ]{0,5}\#(\d{9,})(?:\n| {5,})/", $text, $m)) {
            $b->general()->confirmation($m[1]);
            $t->general()->confirmation($m[1]);
        } elseif (preg_match("/{$this->preg_implode($this->t('BOOKING NUMBER:'))}\n.+\n+\s*\#(\d{9,})\b/", $text, $m)) {
            $b->general()->confirmation($m[1]);
            $t->general()->confirmation($m[1]);
        } elseif (preg_match("/(?:^|\s){$this->preg_implode($this->t('Booking number:'))}\s*#[ ]*(\d{9,})\n/", preg_replace("/^[ ]*{$this->preg_implode($this->t('Tickets and invoices for your booking'))}(?:[ ]{2}|$)/imu", '', $text), $m)) {
            // from invoice
            $b->general()->confirmation($m[1]);
            $t->general()->confirmation($m[1]);
        }

        $total = 0;
        $changeTotal = false;
        $currency = null;
        $cost = 0;
        $changeCost = false;
        $taxes = [];

        foreach ($routes as $route) {
            $row = $this->inOneRow(preg_replace("#^.+\n#", '', substr($route, 0, 1500)));

            $parts = preg_split("/\n[ ]*{$this->preg_implode($this->t('Tickets and invoices for your booking'))}/iu", $route, 2);

            if (preg_match("/^\s*{$this->preg_implode($this->t('Tickets and invoices for your booking'))}/iu", $route)) {
                $parts[0] = '';
                $parts[1] = $route;
            }

            if (count($parts) !== 2 && count($parts) !== 1) {
                $this->logger->debug("don't exists invoices");

                return false;
            }
            $segments = [];

            $tableHeaders = $this->TableHeadPos($row);
            $table = $this->SplitCols(preg_replace("#^.+\n#", '', $parts[0]), $tableHeaders);

            if (count($table) == 3) {
                if (preg_match_all("/([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\nBagagli a mano\:/", $table[2], $m)) {
                    // TODO: make it reliable
                    $t->general()
                        ->travellers($m[1], true);
                    $b->general()
                        ->travellers($m[1], true);
                }

                if (preg_match_all("#^([ ]{0," . ($tableHeaders[1] - 10) . "}.*?)(?:[ ]{2,}|$)#mu", $parts[0], $rowsMatches)) {
                    // determine the number of reservation rows in the center column
                    foreach ($rowsMatches[1] as $i => $s) {
                        if (mb_strlen($s) > ($tableHeaders[1] - 10)) {
                            $blockLen = $i;

                            break;
                        }
                    }
                }

                if (!empty($blockLen) && preg_match("#^((?:.*\n){" . $blockLen . "})[\s\S]+#u", $table[1], $m)) {
                    $table[1] = $m[1];
                }

                $regexp = "/\s*(?<date>.+?) \d{1,2}:\d{2}.*,[\s\S]+\n\s*{$this->preg_implode($this->t("DEPARTURE"))}[ ]+(?<dtime>{$this->patterns['time']}).*\s+(?<dstation>[\s\S]+)\n\s*"
                    . "(?<type>{$this->preg_implode($this->t("Bus connection"))}|Bus connection|{$this->preg_implode($this->t("Train connection"))}|Train connection)\s+(?<number>\S+)\s+[\s\S]+\n"
                    . "{$this->preg_implode($this->t("ARRIVAL"))}[ ]+(?<atime>{$this->patterns['time']})(?:\(.*|.{1,3})?\s*\n\s*(?<astation>[\s\S]+)/u";

                if (preg_match($regexp, $table[1], $segMatch)) {
                    if (empty($blockLen)) {
                        $segMatch['astation'] = preg_replace("#(.*)\n\n\s*.*#s", '$1', $segMatch['astation']);
                    }

                    if (preg_match("/(?<dstat>[\s\S]+?)\s*\n(\s*(?:{$this->preg_implode($this->t("Bus connection"))}|Bus connection|{$this->preg_implode($this->t("Train connection"))}|Train connection)[\s\S]+)/u",
                        $segMatch['dstation'], $m)) {
                        $nextDay = 0;
                        // segment with transit
                        $transitregexp = "/\s*(?<type>{$this->preg_implode($this->t("Bus connection"))}|Bus connection|{$this->preg_implode($this->t("Train connection"))}|Train connection)\s+(?<number>\S+)\s+"
                            . "[\s\S]+?\n\n(?<tstation>[\s\S]+?)\s+{$this->preg_implode($this->t("ARRIVAL"))}\s+(?<atime>{$this->patterns['time']}).*?"
                            . "[ ]+{$this->preg_implode($this->t("DEPARTURE"))}\s+(?<dtime>{$this->patterns['time']})/u";

                        if (preg_match_all($transitregexp, $segMatch['dstation'], $trans)) {
                            $fromDateTime = strtotime($this->normalizeTime($segMatch['dtime']), $this->normalizeDate($segMatch['date']));
                            $seg = [
                                'from' => $this->niceStationName(preg_replace("/^([\s\S]+?)\s+(?:{$this->preg_implode($this->t("Bus connection"))}|Bus connection|{$this->preg_implode($this->t("Train connection"))}|Train connection)\s+[\s\S]+/",
                                    '$1', $segMatch['dstation'])),
                                'fromDateTime' => $fromDateTime,
                            ];

                            foreach ($trans[0] as $key => $tr) {
                                $trans['tstation'][$key] = preg_replace("#^.*\s*" . $this->preg_implode($this->t("TRANSFER IN")) . "\s*(\S.+)#s", '$1',
                                    $trans['tstation'][$key]);
                                $seg['to'] = $this->niceStationName($trans['tstation'][$key]);

                                //it-71007859.eml
                                $toDateTime = strtotime($this->normalizeTime($trans['atime'][$key]), $this->normalizeDate($segMatch['date']));

                                if (($fromDateTime - $toDateTime) < 0) {
                                    $seg['toDateTime'] = $toDateTime;
                                } else {
                                    $seg['toDateTime'] = strtotime('+1 day', $toDateTime);
                                    $nextDay = 1;
                                }

                                $seg['number'] = $trans['number'][$key];
                                $seg['type'] = $trans['type'][$key];
                                $segments[] = $seg;

                                //it-71007859.eml
                                if ($nextDay == 0) {
                                    $fromDateTime = strtotime($this->normalizeTime($trans['dtime'][$key]), $this->normalizeDate($segMatch['date']));
                                }

                                if ($nextDay == 1) {
                                    $fromDateTime = strtotime('+1 day', strtotime($this->normalizeTime($trans['dtime'][$key]), $this->normalizeDate($segMatch['date'])));
                                }

                                $seg = [
                                    'from'         => $this->niceStationName($trans['tstation'][$key]),
                                    'fromDateTime' => $fromDateTime,
                                ];
                            }
                            $seg['to'] = $this->niceStationName($segMatch['astation']);

                            //it-71007859.eml
                            if ($nextDay == 0) {
                                $toDateTime = strtotime($this->normalizeTime($segMatch['atime']), $this->normalizeDate($segMatch['date']));
                            }

                            if ($nextDay == 1) {
                                $toDateTime = strtotime('+1 day', strtotime($this->normalizeTime($segMatch['atime']), $this->normalizeDate($segMatch['date'])));
                            }
                            $seg['toDateTime'] = $toDateTime;

                            $seg['number'] = $segMatch['number'];
                            $seg['type'] = $segMatch['type'];
                            $segments[] = $seg;

                            if (preg_match_all("#^\s*" . $this->preg_implode($this->t("Seats")) . "[ ]*\(.+(?:\n.+)?\s-\s.+(?:\n.+)?\)\n{1,3}(.+)#mu", $table[2], $sm) && count($sm[0]) == count($segments)) {
                                foreach ($sm[1] as $i => $s) {
                                    if (preg_match("#^\s*(?:" . $this->preg_implode($this->t("Coach")) . "[ ]*(?<car>[\dA-Z]{1,3}):)?[ ]*(?<seats>\d{1,2}[A-Z]((?:,[ ]{0,2})\d{1,2}[A-Z])*)\s*$#",
                                        $s, $match)) {
                                        $segments[$i]['seats'] = array_map('trim', explode(",", $match['seats']));

                                        if (!empty($match['car'])) {
                                            $segments[$i]['car'] = $match['car'];
                                        }
                                    }
                                }
                            } elseif (preg_match_all("#\n\s*" . $this->preg_implode($this->t("Seat:")) . "\s*\n((?:.*\n)+?)(?:\n{2,}|$|" . $this->preg_implode($this->t("Total")) . ")#u", $table[2], $sm) && count($sm[0]) == count($segments)) {
                                foreach ($sm[1] as $s) {
                                    if (preg_match_all("#^\s*[ ]*.+\s[›]\s+.+ (?<seats>\d{1,2}[A-Z])$#mu", $s, $matches)) {
                                        foreach ($matches['seats'] as $i => $match) {
                                            $segments[$i]['seats'][] = trim($match);
                                        }
                                    }
                                }
                            }
                        }
                    } else {
                        $segments[] = [
                            'number'       => $segMatch['number'],
                            'type'         => $segMatch['type'],
                            'from'         => $this->niceStationName($segMatch['dstation']),
                            'fromDateTime' => strtotime($this->normalizeTime($segMatch['dtime']), $this->normalizeDate($segMatch['date'])),
                            'to'           => $this->niceStationName($segMatch['astation']),
                            'toDateTime'   => strtotime($this->normalizeTime($segMatch['atime']), $this->normalizeDate($segMatch['date'])),
                        ];

                        if (preg_match("#\n\s*(?:" . $this->preg_implode($this->t("Coach")) . "[ ]*(?<car>[\dA-Z]{1,3}):)?[ ]*(?<seats>\d{1,2}[A-Z]((?:,[ ]{0,2})\d{1,2}[A-Z])*)\s*(?:\n{5,}|$|" . $this->preg_implode($this->t("Total")) . ")#", $table[2], $match)) {
                            $segments[0]['seats'] = array_map('trim', explode(",", $match['seats']));

                            if (!empty($match['car'])) {
                                $segments[0]['car'] = $match['car'];
                            }
                        } elseif (preg_match_all("/{$this->preg_implode($this->t('Seat:'))}\s*(\d+[A-Z])/", $table[2], $m)) {
                            $segments[0]['seats'] = $m[1];
                        } elseif (preg_match_all("#\n\s*" . $this->preg_implode($this->t("Seat:")) . "[ ]*(?<seats>\d{1,2}[A-Z]((?:[ ]{0,2})\d{1,2}[A-Z])*)\s*(?:\n{3,}|$|" . $this->preg_implode($this->t("Total")) . ")#", $table[2], $seatsMatches)) {
                            $segments[0]['seats'] = [];

                            foreach ($seatsMatches['seats'] as $mat) {
                                $segments[0]['seats'] = array_merge($segments[0]['seats'], array_map('trim', explode(" ", $mat)));
                            }
                        }
                    }
                }
            }
            $paymentblocks = [];

            if (isset($parts[1])) {
                $paymentblocks = $this->split("/\n(.*{$this->preg_implode($this->t("Invoice"))}[ ]{0,5}#[-\w\d]{5,})/u", $parts[1]);
            } else {
                //it-99403574
                $travellersText = $this->re("/\n\n(\s+TRANSFER IN\s+Adult.+Total:)/msu", $text);

                if (empty($travellersText)) {
                    $travellersText = $this->re("/{$this->preg_implode($this->t('DEPARTURE'))}.+\n{2}(.+{$this->preg_implode($this->t('Adult'))}.+{$this->preg_implode($this->t('Total'))}\b)/su", $text);
                }
                $travelTable = $this->SplitCols($travellersText);

                if (count($travelTable) > 1 && !empty($travelTable[1])) {
                    if (preg_match_all("/\n(\D+)\nHand\s*/", $travelTable[1], $paxMatches)
                        || preg_match_all("/^[ ]*{$this->preg_implode($this->t('Adult'))}\n+[ ]*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\n+.+:/mu", $travelTable[1], $paxMatches)
                    ) {
                        $paxs = [];

                        foreach ($paxMatches[1] as $pax) {
                            $traveller = trim(str_replace("\n", " ", $pax));

                            if (!in_array($traveller, $this->travellers)) {
                                $paxs[] = trim(str_replace("\n", " ", $pax));
                            }
                        }

                        if (count($paxs) > 0) {
                            $b->general()->travellers($paxs, true);
                        }
                    }
                } elseif (empty($travellersText)) {
                    if (preg_match_all("/[ ]{4,}((?:[A-Z]{3,}\s*\w+\s*)?[A-z]+\s{1,4}[A-z]+)\s*\n(?:\s*Handgepäck|.+Handgepäck|.+eletrônico)/u", $text, $m)) {
                        $b->general()
                            ->travellers($m[1], true);
                    }
                }
            }
            $paidSeg = [];

            foreach ($paymentblocks as $block) {
                $isTicket = false;
                // Traveller
                if (preg_match("/^.*#\w[-\w\/]{4,}[ ]{5,}(?:{$this->preg_implode(['Miss', 'Mrs', 'Mr', 'Ms'])}\.? ?)?(\b[[:alpha:]][-[:alpha:] .]+)\n/iu", $block, $m)) {
                    if (!in_array($m[1], array_column($b->getTravellers(), 0))) {
                        $b->general()->traveller($m[1], true);
                        $t->general()->traveller($m[1], true);
                    }
                    $isTicket = true;

                    if (preg_match("#\n[ ]{0,10}\S+\d{4}.*(?:[ ]{4,}|\n)(?:.*\n)+?[ ]{0,10}\S+\d{4}.*(?:[ ]{4,}|\n)(?:.*\n)+?([ ]{15,}|\n\n|$)#", $block, $match)) {
                        $rows = preg_replace("#^(.{15,}?)[ ]{4,}.*#m", '$1', $match[0]);

                        if (preg_match("/\s*(?<dDate>.*?)[, ]+(?<dTime>{$this->patterns['time']}).*\s+(?<dName>[\s\S]+?)\s*\n\s*(?<aDate>.*?)[, ]+(?<aTime>{$this->patterns['time']}).*\s+(?<aName>[\s\S]+?)(?:\n\n|$)/", $rows, $sp)) {
                            if (preg_match_all("/^.{60,160} #(\w{1,40})-\d{5}/m", mb_strtolower($block), $tTypeMatches)
                                && count(array_unique($tTypeMatches[1])) === 1 && $tTypeMatches[1][0] === 'train'
                            ) {
                                $ticketType = 'Train connection'; // it-60986870.eml
                            } else {
                                $ticketType = null;
                            }

                            $paidSeg[] = [
                                'from'         => $this->niceStationName($sp['dName']),
                                'fromDateTime' => strtotime($this->normalizeTime($sp['dTime']), $this->normalizeDate($sp['dDate'])),
                                'to'           => $this->niceStationName($sp['aName']),
                                'toDateTime'   => strtotime($this->normalizeTime($sp['aTime']), $this->normalizeDate($sp['aDate'])),
                                'type'         => $ticketType,
                            ];
                        }
                    }
                }

                // Price
                // total

                if (isset($total)
                    && (preg_match("#[ ]{5,}" . $this->preg_implode($this->t("Total")) . "[ ]{5,}(.+)#", $block, $match)
                    || preg_match("#[ ]{5,}" . $this->preg_implode($this->t("Total")) . "\n.{50,}[ ]{5,}(.+)#", $block, $match))
                    && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $match[1], $m)
                        || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $match[1], $m))) {
                    $currency = $currency ?? $this->currency($m['curr']);
                    $m['amount'] = PriceHelper::parse($m['amount'], $currency);

                    if ($this->currency($m['curr']) === $currency) {
                        $total += $m['amount'];
                    } else {
                        unset($total);
                    }
                    $changeTotal = true;

                    if (isset($taxes) && !$isTicket && preg_match("#^\s*(?:flixbus)?[ ]*\d? (.+) " . $this->preg_implode($this->t("Invoice")) . "#i",
                            $block, $m2)) {
                        $foundTax = false;

                        foreach ($taxes as $i => $tax) {
                            if ($tax["name"] == $m2[1]) {
                                $taxes[$i]["value"] += $m['amount'];
                                $foundTax = true;

                                break;
                            }
                        }

                        if ($foundTax == false) {
                            $taxes[] = ["name" => $m2[1], "value" => $m['amount']];
                        }
                    }
                }

                if (!$isTicket || !isset($cost) || !isset($taxes)) {
                    continue;
                }

                // cost, tax
                if (preg_match("#\n(.*) " . $this->preg_implode($this->t("COUNTRY")) . " .* " . $this->preg_implode($this->t("GROSS")) . "\n((?:.*\n){1,10}).*[ ]{5,}" . $this->preg_implode($this->t("Total")) . "[ ]{5,}#",
                    $block, $match)) {
                    $rows = array_filter(explode("\n", $match[2]));

                    foreach ($rows as $row) {
                        $row = substr_replace($row, '', 0, strlen($match[1]));

                        if (isset($taxes) && preg_match("#^[ ]{0,5}(?<name>.+?)[ ]{3,}.+[ ]{3,}(?<tax>.*?\d.*?|--)[ ]{3,}(?<total>.*?\d.*?)$#", $row, $str)) {
                            if ((preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $str['tax'], $m)
                                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $str['tax'],
                                    $m))) {
                                $currency = $currency ?? $this->currency($m['curr']);
                                $m['amount'] = PriceHelper::parse($m['amount'], $currency);
                                $foundTax = false;

                                if ($this->currency($m['curr']) === $currency) {
                                    foreach ($taxes as $i => $tax) {
                                        if ($tax['name'] == $str['name']) {
                                            $taxes[$i]['value'] += $m['amount'];
                                            $foundTax = true;
                                        }
                                    }

                                    if ($foundTax == false) {
                                        $taxes[] = ["name" => $str['name'], "value" => $m['amount']];
                                    }
                                } else {
                                    unset($taxes);
                                }
                            }
                        }
                    }
                } elseif (preg_match("#.+ " . $this->preg_implode($this->t("NET")) . "[ ]+(.*\d.*)\n#", $block,
                        $match)
                    && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $match[1], $m)
                        || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $match[1], $m))) {
                    $currency = $currency ?? $this->currency($m['curr']);

                    if ($this->currency($m['curr']) === $currency) {
                        $cost += PriceHelper::parse($m['amount'], $currency);
                    } else {
                        unset($cost);
                    }
                    $changeCost = true;

                    if (preg_match("#.+ (" . $this->preg_implode($this->t("VAT")) . ")[ ]+((?:\S+ )*\d.*?)\n#u", $block,
                            $match)
                        && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $match[2], $m)
                            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $match[2],
                                $m))) {
                        $currency = $currency ?? $this->currency($m['curr']);
                        $m['amount'] = PriceHelper::parse($m['amount'], $currency);

                        if ($this->currency($m['curr']) === $currency) {
                            $foundTax = false;

                            foreach ($taxes as $i => $tax) {
                                if ($tax['name'] == $match[1]) {
                                    $taxes[$i]['value'] += $m['amount'];
                                    $foundTax = true;
                                }
                            }

                            if ($foundTax == false) {
                                $taxes[] = ["name" => $match[1], "value" => $m['amount']];
                            }
                        } else {
                            unset($taxes);
                        }
                    }
                }
            }

            if (count($b->getTravellers()) == 0 && !empty($parts[1])) {
                if (preg_match_all("/\d\s*{$this->preg_implode($this->t('Adult'))}[ ]{10,}{$this->preg_implode($this->t('Ticket'))}[ ]{10,}(\D+)\n\n/", $parts[1], $m)) {
                    $this->travellers = $m[1];
                    $b->general()
                        ->travellers($m[1], true);
                    $t->general()
                        ->travellers($m[1], true);
                }
            }

            if (empty($paymentblocks) && isset($total, $table[2])
                && (preg_match("#\n\s*" . $this->preg_implode($this->t("Total")) . ":[ ]*(.+)#", $table[2], $match))
                && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $match[1], $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $match[1], $m))) {
                $currency = $currency ?? $this->currency($m['curr']);
                $m['amount'] = PriceHelper::parse($m['amount'], $currency);

                if ($this->currency($m['curr']) === $currency) {
                    $total += $m['amount'];
                } else {
                    unset($total);
                }
                $changeTotal = true;
            }
            $paidSeg = array_map('unserialize', array_unique(array_map('serialize', $paidSeg)));

            if (empty($segments) && !empty($paidSeg)) {
                $segments = $paidSeg;
                $paidSeg = [];
            }

            foreach ($segments as $seg) {
                if (isset($seg['type']) && preg_match("/(?:{$this->preg_implode($this->t("Train connection"))}|Train connection)/", $seg['type'])) {
                    $s = $t->addSegment();
                } else {
                    $s = $b->addSegment();
                }

                if (!empty($seg['from']) && !empty($seg['to'])) {
                    $sF = str_replace(' ', '', $seg['from']);
                    $sT = str_replace(' ', '', $seg['to']);

                    foreach ($paidSeg as $p => $pSeg) {
                        if (!empty($pSeg['from']) && !empty($pSeg['to'])) {
                            $psF = str_replace(' ', '', $pSeg['from']);
                            $psT = str_replace(' ', '', $pSeg['to']);

                            if (($psF === $sF || strncasecmp($psF, $sF, strlen($psF)) === 0)
                                && ($psT === $sT || strncasecmp($psT, $sT, strlen($psT)) === 0)) {
                                $s->departure()
                                    ->name($pSeg['from'])
                                ;

                                if (!empty($pSeg['fromDateTime'])) {
                                    $s->departure()
                                        ->date($pSeg['fromDateTime'])
                                    ;
                                }
                                $s->arrival()
                                    ->name($pSeg['to'])
                                ;

                                if (!empty($pSeg['toDateTime'])) {
                                    $s->arrival()
                                        ->date($pSeg['toDateTime'])
                                    ;
                                }
                                unset($paidSeg[$p]);

                                break;
                            }
                        }
                    }
                }

                if (empty($s->getDepName())) {
                    $s->departure()
                        ->name($seg['from'])
                    ;
                }

                if (empty($s->getDepDate())) {
                    $s->departure()
                        ->date($seg['fromDateTime'])
                    ;
                }

                if (empty($s->getArrName())) {
                    $s->arrival()
                        ->name($seg['to'])
                    ;
                }

                if (empty($s->getArrDate())) {
                    if ($s->getDepDate() < $seg['toDateTime']) {
                        $s->arrival()
                            ->date($seg['toDateTime']);
                    } else {
                        $s->arrival()
                            ->date(strtotime('+1 day', $seg['toDateTime']));
                    }
                }

                // Extra
                if (!empty($seg['number'])) {
                    $s->extra()
                        ->number($seg['number']);
                } elseif (stripos($s->getId(), 'train') === 0) {
                    $s->extra()->noNumber();
                }

                if (!empty($seg['seats'])) {
                    $s->extra()->seats($seg['seats']);

                    if (!empty($seg['car'])) {
                        $s->extra()->car($seg['car']);
                    }
                }
            }
        }

        foreach ($email->getItineraries() as $value) {
            /** @var \AwardWallet\Schema\Parser\Common\Bus $value */
            if (count($value->getSegments()) == 0) {
                $email->removeItinerary($value);
            }
        }

        if (empty($total) && empty($changeTotal) && empty($currency)) {
            $total = $this->re("/{$this->preg_implode($this->t('Total'))}[:\s]*(\d[\d,.]*\s*\D)(?:\s|\n)/u", $text)
            ?? $this->re("/{$this->preg_implode($this->t('Total'))}[:\s]*([^\d\s:]+\s*\d[\d,.]*)(?:\s|\n)/u", $text);

            if (preg_match("/(?<total>\d[\d.,]*)\s*(?<currency>\D+)/", $total, $m)
                || preg_match("/(?<currency>\D+)\s*(?<total>\d[\d.,]*)/", $total, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $total = PriceHelper::parse($m['total'], $currency);
                $changeTotal = true;
            }
        }

        if (isset($total) && $changeTotal && $currency) {
            $email->price()
                ->total($total)
                ->currency($currency);
        }

        if (isset($cost) && $changeCost && $currency) {
            $email->price()
                ->cost($cost)
                ->currency($currency);
        }

        if (!empty($taxes) && $currency) {
            foreach ($taxes as $tax) {
                $email->price()->fee(empty(trim($tax['name'])) ? 'Unknown Fee' : $tax['name'], $tax['value']);
            }
        }

        if (count($t->getTravellers()) == 0 && count($this->travellers) > 0) {
            $t->general()
                ->travellers($this->travellers, true);
            $b->general()
                ->travellers($this->travellers, true);
        }

        return true;
    }

    private function parsePdf2(Email $email, string $text): void
    {
        // examples: it-661689003.eml, it-670797206.eml, it-669650104.eml
        $this->logger->debug(__FUNCTION__);

        $routes = array_filter($this->split("#^([ ]*[\d ]{10,} {3,}" . $this->preg_implode($this->t("BOARDING PASS")) . "\b.*)#mu", $text));

        foreach ($routes as $route) {
            $confirmation = null;

            if (preg_match("/^\s*(\d{3} ?\d{3} ?\d{4})[ ]*{$this->preg_implode($this->t("BOARDING PASS"))}(?:[ ]{5}.*)?\n/u", $text, $m)
                || preg_match("/^\s*(?:.+\S[ ]+)?{$this->preg_implode($this->t("BOARDING PASS"))}(?:[ ]{5}.*)?\n[ ]*(\d{3} ?\d{3} ?\d{4})(?:\n|[ ]+[[:alpha:]])/u", $text, $m)
            ) {
                $confirmation = str_replace(' ', '', $m[1]);
            }

            $route = preg_replace("#^.+\n#", '', $route);

            $datas = preg_split("/\n( *" . $this->preg_implode($this->t("Additional Information")) . ")/u", $route, false);

            $currency = $totalAmount = null;
            $totalPrice = $this->re("/{$this->preg_implode($this->t('Total price:'))}[: ]*(\d[\d,. ]*[ ]*\D{1,7}|\s*\D{1,7}[ ]*\d[\d,. ]*?)(?:[ ]{2}|\n)/u", $datas[1] ?? '');

            if (preg_match("/^\s*(?<total>\d[\d.,]*)\s*(?<currency>\D*)\s*$/u", $totalPrice, $m)
                || preg_match("/^\s*(?<currency>\D+?)\s*(?<total>\d[\d.,]*)\s*$/u", $totalPrice, $m)
            ) {
                $currency = $this->currency($m['currency']);
                $totalAmount = PriceHelper::parse($m['total'], $currency);
            }

            $tableText = $datas[0];
            $tableHeaders = $this->TableHeadPos(preg_replace('/^(\s+\d{3,4} ?\d{3,4} ?\d{3,4})[ ]{5,}(\S)/', '$1    $2', $this->re('/^(.+)/', $tableText)));

            if (count($tableHeaders) !== 2) {
                continue;
            }
            $tableHeaders[0] = 0;
            $table = $this->SplitCols(preg_replace("#^.+\n#", '', $tableText), $tableHeaders, false);

            if (in_array($this->lang, ['da'])) {
                $timeFormat = "\b\d{1,2}\.\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|\b)?"; // 12.20  |  06.15 PM
            } else {
                $timeFormat = "\b\d{1,2}:\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?|\b)?"; // 12:20  |  06:15 PM
            }

            $segment = [];

            $rows = $this->split("/(\n {0,10}{$timeFormat}.*? {2,})/", $table[0]);
            $sDate = $this->re("/\n {0,10}(?<date>.+?)\n{1,2}\s*(?<dtime>{$timeFormat}).* {2,}/", $table[0]);

            $rowSegments = [];

            if (count($rows) % 2 === 0) {
                for ($i = 0; $i < count($rows) - 1; $i = $i + 2) {
                    $rowSegments[] = $rows[$i] . "\n" . $rows[$i + 1];
                }
            }

            $segmentsSeats = [];

            $extraText = $this->re("/\n( *[^\w\s] *{$this->preg_implode($this->t("Adults"))}.*(\n.*)+?)(?:\n {0,15}[^\w\s] *\w+|\s*$)/u", $table[1]);
            $extraText = preg_replace("/\n\n {0,20}\d+ × .+[\s\S]+/u", '', $extraText);
            $headers = $this->TableHeadPos($this->inOneRow($extraText));

            if (count($headers) > 0) {
                $extraTable = $this->SplitCols($extraText, [0, ($headers[1] < 15) ? $headers[2] ?? 0 : $headers[1]], false);

                $travellersText = trim(preg_replace("/^\s*.+/", '', $extraTable[0]));

                if (empty($travellersText)) {
                    $travellers = [];
                } elseif (preg_match($pattern = "/\n[ ]*\d{1,2}\.\d{1,2}\.\d{2,4}$/m", $travellersText)) {
                    $travellers = array_filter(array_map('trim', preg_replace('/\s+/', ' ', preg_split($pattern, $travellersText))));
                } else {
                    $travellers = preg_split("/(\s*\n+\s*)+/", $travellersText);
                }

                $seatsText = trim(preg_replace("/^\s*.+/", '', $extraTable[1]));
                $seatRows = empty($seatsText) ? [] : preg_split("/(\s*[+\n]+\s*)+/", $seatsText);

                foreach ($seatRows as $st) {
                    if (count($rowSegments) == 1) {
                        if (preg_match("/^[A-Z\d]{1,4}$/", $st)) {
                            $segmentsSeats[0]['seats'][] = $st;
                        } elseif (preg_match("/^(\d+) *[^\w\s] *([A-Z\d]{1,4})$/", $st, $m)) {
                            $segmentsSeats[0]['car'][] = $m[1];
                            $segmentsSeats[0]['seats'][] = $m[2];
                        }
                    } else {
                        $seatTabs = $this->SplitCols($st, $this->TableHeadPos($this->inOneRow($st)), false);

                        if (count($seatTabs) == count($rowSegments)) {
                            foreach ($seatTabs as $sti => $seattab) {
                                if (preg_match("/^\s*[A-Z\d]{1,4}\s*$/", $seattab)) {
                                    $segmentsSeats[$sti]['seats'][] = trim($seattab);
                                }
                            }
                        }
                    }
                }
            }

            $regexp = "/^\s*(?<dtime>{$timeFormat}).*?[ ]{2,}(?<dstation>[\s\S]+)\n\s*(?<type>{$this->preg_implode($this->t("Route"))}|{$this->preg_implode($this->t("Bus"))}|Bus|{$this->preg_implode($this->t("Train"))}|Train)[ \[]+(?<number>[A-Z]*\d+[a-zA-Z]?)[\]\s]+(?<info>[\s\S]+?)\n[ ]*(?<atime>{$timeFormat}).*? {2,}(?<astation>[\s\S]+)/u";

            $regexp2 = "/^\s*(?<dtime>{$timeFormat}).*?[ ]{2,}(?<dstation>[\s\S]+)\n\s* +[^\s\w.,\-] {0,3}(?<number>[A-Z]{0,3}\d+[a-zA-Z]?) {0,3}[^\s\w.,\-](?: {0,23}\S.+|[ ]*\n)\s+(?<info>[\s\S]+?)\n[ ]*(?<atime>{$timeFormat}).*? {2,}(?<astation>[\s\S]+)/u";

            foreach ($rowSegments as $i => $sText) {
                if (isset($rowSegments[$i + 1])) {
                    $sText = preg_replace("/\n {15,}\S.+\s*$/", '', $sText);
                }

                if (preg_match($regexp, $sText, $segMatch) || preg_match($regexp2, $sText, $segMatch)) {
                    $segMatch['dDate'] = $segMatch['aDate'] = null;

                    if (preg_match("/^(.+\n) {0,10}([[:alpha:]]+[\.]? (?:de )?\d{1,2}[\.]?|\d{1,2}[\.]? (?:de )?[[:alpha:]]+[\.]?)( {2,}[\s\S]*)/u", $segMatch['dstation'], $m)) {
                        $segMatch['dDate'] = $m[2];
                        $segMatch['dstation'] = $m[1] . str_pad('', strlen($m[2]), ' ') . $m[3];
                    }

                    if (preg_match("/(?:^|\n) {0,10}([[:alpha:]]+[\.]? (?:de )?\d{1,2}[\.]?|\d{1,2}[\.]? (?:de )?[[:alpha:]]+[\.]?)((?: {2,}.*)?(?:\n {20}[\S\s]+)?\s*)$/u", $segMatch['info'], $m)) {
                        $segMatch['aDate'] = $m[1];
                        $segMatch['astation'] = trim($m[2]) . "\n" . $segMatch['astation'];
                    }

                    $segMatch['dstationAddress'] = $segMatch['astationAddress'] = null;

                    if (preg_match($pattern = "/^\s*(.+(?:\s*\n *\w.+)*)\s*\n *[^\w\s] *([\[(]? ?\w.+(?:\s*\n *\w.+)*)(?:\s*\n *[^\w\s] *[\S\s]*)?\s*$/u", $segMatch['dstation'], $m)) {
                        $segMatch['dstation'] = $m[1];
                        $segMatch['dstationAddress'] = preg_replace('/^([\s\S]{3,}?)(?:[ ]*\n[ ]*){3,}\S.*$/', '$1', $m[2]); // remove garbage on bottom
                    }

                    if (preg_match($pattern, $segMatch['astation'], $m)) {
                        $segMatch['astation'] = $m[1];
                        $segMatch['astationAddress'] = $m[2];
                    }

                    if (!empty($sDate)) {
                        $fromDate = strtotime($this->normalizeTime($segMatch['dtime']), $this->normalizeDate($sDate));
                        $toDate = strtotime($this->normalizeTime($segMatch['atime']), $this->normalizeDate($sDate));

                        if (!empty($segMatch['dDate'])) {
                            $year = $this->re("/\b(\d{4})\b/", $sDate);
                            $segMatch['dDate'] .= ' ' . $year;
                            $fromDate1 = strtotime($this->normalizeTime($segMatch['dtime']), $this->normalizeDate($segMatch['dDate']));

                            if ($fromDate1 - $fromDate < 0) {
                                $fromDate1 = strtotime("+1 year", $fromDate1);

                                if ($fromDate1 - $fromDate < 0 && $fromDate1 - $fromDate > 60 * 60 * 24 * 5) {
                                    $fromDate1 = null;
                                }
                            }
                            $fromDate = $fromDate1;
                        }

                        if (!empty($segMatch['aDate'])) {
                            $year = $this->re("/\b(\d{4})\b/", $sDate);
                            $segMatch['aDate'] .= ' ' . $year;
                            $toDate1 = strtotime($this->normalizeTime($segMatch['atime']), $this->normalizeDate($segMatch['aDate']));

                            if ($toDate1 - $toDate < 0) {
                                $toDate1 = strtotime("+1 year", $toDate1);

                                if ($toDate1 - $toDate < 0 && $toDate1 - $toDate > 60 * 60 * 24 * 5) {
                                    $toDate1 = null;
                                }
                            }
                            $toDate = $toDate1;
                        }
                    }

                    if (!isset($segMatch['type'])
                        || preg_match("/^\s*(?:{$this->preg_implode($this->t("Bus"))}|Bus)/iu", $segMatch['type'])
                    ) {
                        $segMatch['type'] = 'bus';
                    } elseif (preg_match("/^\s*(?:{$this->preg_implode($this->t("Train"))}|Train)/iu", $segMatch['type'])) {
                        $segMatch['type'] = 'train';
                    }
                    $segment = [
                        'number'       => $segMatch['number'],
                        'type'         => $segMatch['type'],
                        'from'         => $this->niceStationName($segMatch['dstation']),
                        'fromAddress'  => $this->niceStationName($segMatch['dstationAddress'] ?? ''),
                        'fromDateTime' => $fromDate,
                        'to'           => $this->niceStationName($segMatch['astation']),
                        'toAddress'    => $this->niceStationName($segMatch['astationAddress'] ?? ''),
                        'toDateTime'   => $toDate,
                    ];

                    if (isset($segmentsSeats[$i], $segmentsSeats[$i]['seats'])) {
                        $segment['seats'] = $segmentsSeats[$i]['seats'];

                        if (isset($segmentsSeats[$i], $segmentsSeats[$i]['car'])) {
                            $segment['car'] = $segmentsSeats[$i]['car'];
                        }
                    }
                }

                if (empty($segment)) {
                    $this->logger->debug('segment not parse');
                    $email->add()->bus();

                    continue;
                }

                $trText = $this->re("/\n +{$this->preg_implode($this->t('Tipo de passaporte'))} +{$this->preg_implode($this->t('Número de passaporte'))}\s*\n(( *[[:alpha:]][[:alpha:] \-]+ {2,}\w{2,4} {2,}\d{5,}\s*\n+)+)/u", $datas[1] ?? '');

                if (!empty($trText)) {
                    $travellers = preg_replace("/^ {0,10}([[:alpha:]][[:alpha:] \-]+?) {2,}.*/mu", "$1", array_filter(preg_split("/\s*\n+\s*/", $trText)));
                }

                if (empty($this->travellers) && !empty($travellers)) {
                    $this->travellers = $travellers;
                }

                $foundItinerary = false;

                foreach ($email->getItineraries() as $gt) {
                    /** @var \AwardWallet\Schema\Parser\Common\Bus $gt */
                    if ($gt->getType() === $segment['type'] && !empty($gt->getConfirmationNumbers())
                        && in_array($confirmation, $gt->getConfirmationNumbers()[0])
                    ) {
                        $s = $gt->addSegment();
                        $foundItinerary = true;

                        break;
                    }
                }

                if ($foundItinerary === false) {
                    if ($segment['type'] == 'train') {
                        $t = $email->add()->train();

                        $t->general()
                            ->confirmation($confirmation)
                            ->travellers($travellers ?? []);

                        if ($totalAmount !== null) {
                            $t->price()->currency($currency)->total($totalAmount);
                        }

                        $s = $t->addSegment();
                    } else {
                        $b = $email->add()->bus();

                        $b->general()
                            ->confirmation($confirmation)
                        ;

                        if ($totalAmount !== null) {
                            $b->price()->currency($currency)->total($totalAmount);
                        }

                        $s = $b->addSegment();
                    }
                }

                $s->departure()
                    ->date($segment['fromDateTime'])
                    ->name($segment['from']);

                if (!empty($segment['fromAddress'])) {
                    $s->departure()
                        ->address($segment['fromAddress']);
                }
                $s->arrival()
                    ->date($segment['toDateTime'])
                    ->name($segment['to']);

                if (!empty($segment['toAddress'])) {
                    $s->arrival()
                        ->address($segment['toAddress']);
                }

                $s->extra()
                    ->number($segment['number']);

                if (!empty($segment['car'])) {
                    $s->extra()
                        ->car($segment['car']);
                }

                if (!empty($segment['seats'])) {
                    $s->extra()
                        ->seats($segment['seats']);
                }
            }
        }
    }

    private function parsePdf2Invoice(Email $email, string $text): void
    {
        $this->logger->debug(__FUNCTION__);

        $paymentblocks = $this->split("#\n(.*" . $this->preg_implode($this->t("Invoice")) . "[ ]{0,5}\#[\w\-]{5,})#",
            $text);

        $total = 0;
        $changeTotal = false;
        $currency = null;
        $cost = 0;
        $changeCost = false;
        $taxes = [];

        foreach ($paymentblocks as $block) {
            $isTicket = false;

            if (isset($total)
                && (preg_match("#[ ]{5,}" . $this->preg_implode($this->t("Total")) . "[ ]{5,}(.+)#", $block, $match)
                    || preg_match("#[ ]{5,}" . $this->preg_implode($this->t("Total")) . "\n.{50,}[ ]{5,}(.+)#", $block, $match))
                && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $match[1], $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $match[1], $m))) {
                $currency = $currency ?? $this->currency($m['curr']);
                $m['amount'] = PriceHelper::parse($m['amount'], $currency);

                if ($this->currency($m['curr']) === $currency) {
                    $total += $m['amount'];
                } else {
                    unset($total);
                }
                $changeTotal = true;

                if (isset($taxes) && !$isTicket && preg_match("#^\s*(?:flixbus)?[ ]*\d? (.+) " . $this->preg_implode($this->t("Invoice")) . "#i",
                        $block, $m2)) {
                    $foundTax = false;

                    foreach ($taxes as $i => $tax) {
                        if ($tax["name"] == $m2[1]) {
                            $taxes[$i]["value"] += $m['amount'];
                            $foundTax = true;

                            break;
                        }
                    }

                    if ($foundTax == false) {
                        $taxes[] = ["name" => $m2[1], "value" => $m['amount']];
                    }
                }
            }

            if (!$isTicket || !isset($cost) || !isset($taxes)) {
                continue;
            }

            // cost, tax
            if (preg_match("#\n(.*) " . $this->preg_implode($this->t("COUNTRY")) . " .* " . $this->preg_implode($this->t("GROSS")) . "\n((?:.*\n){1,10}).*[ ]{5,}" . $this->preg_implode($this->t("Total")) . "[ ]{5,}#",
                $block, $match)) {
                $rows = array_filter(explode("\n", $match[2]));

                foreach ($rows as $row) {
                    $row = substr_replace($row, '', 0, strlen($match[1]));

                    if (isset($taxes) && preg_match("#^[ ]{0,5}(?<name>.+?)[ ]{3,}.+[ ]{3,}(?<tax>.*?\d.*?|--)[ ]{3,}(?<total>.*?\d.*?)$#", $row, $str)
                    ) {
                        if ((preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $str['tax'], $m)
                            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $str['tax'],
                                $m))) {
                            $currency = $currency ?? $this->currency($m['curr']);
                            $m['amount'] = PriceHelper::parse($m['amount'], $currency);
                            $foundTax = false;

                            if ($this->currency($m['curr']) === $currency) {
                                foreach ($taxes as $i => $tax) {
                                    if ($tax['name'] == $str['name']) {
                                        $taxes[$i]['value'] += $m['amount'];
                                        $foundTax = true;
                                    }
                                }

                                if ($foundTax == false) {
                                    $taxes[] = ["name" => $str['name'], "value" => $m['amount']];
                                }
                            } else {
                                unset($taxes);
                            }
                        }
                    }
                }
            } elseif (preg_match("#.+ " . $this->preg_implode($this->t("NET")) . "[ ]+(.*\d.*)\n#", $block,
                    $match)
                && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $match[1], $m)
                    || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $match[1], $m))) {
                $currency = $currency ?? $this->currency($m['curr']);

                if ($this->currency($m['curr']) === $currency) {
                    $cost += PriceHelper::parse($m['amount'], $currency);
                } else {
                    unset($cost);
                }
                $changeCost = true;

                if (preg_match("#.+ (" . $this->preg_implode($this->t("VAT")) . ")[ ]+((?:\S+ )*\d.*?)\n#u", $block,
                        $match)
                    && (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $match[2], $m)
                        || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $match[2],
                            $m))) {
                    $currency = $currency ?? $this->currency($m['curr']);
                    $m['amount'] = PriceHelper::parse($m['amount'], $currency);

                    if ($this->currency($m['curr']) === $currency) {
                        $foundTax = false;

                        foreach ($taxes as $i => $tax) {
                            if ($tax['name'] == $match[1]) {
                                $taxes[$i]['value'] += $m['amount'];
                                $foundTax = true;
                            }
                        }

                        if ($foundTax == false) {
                            $taxes[] = ["name" => $match[1], "value" => $m['amount']];
                        }
                    } else {
                        unset($taxes);
                    }
                }
            }
        }

        if (isset($total) && $changeTotal && $currency) {
            $email->price()
                ->total($total)
                ->currency($currency);
        }

        if (isset($cost) && $changeCost && $currency) {
            $email->price()
                ->cost($cost)
                ->currency($currency);
        }

        if (!empty($taxes) && $currency) {
            foreach ($taxes as $tax) {
                $email->price()->fee(empty(trim($tax['name'])) ? 'Unknown Fee' : $tax['name'], $tax['value']);
            }
        }
    }

    private function niceStationName(string $name): ?string
    {
        if (empty($name)) {
            return null;
        }
        $name = preg_replace("#(\S+[\s\S]+?)\n\s*.*" . $this->preg_implode($this->t("Please note")) . "[\s\S]+#", '$1', trim($name));
        $name = preg_replace("#(\S+[\s\S]+\([\s\S]+\))\s+[\s\S]+#", '$1', $name);
        $name = preg_replace("#\s*\n\s*#", ' ', $name);
        $name = preg_replace("#\*.+#", '', $name);
        $name = preg_replace("# \(FlixTrain\)\s*$#", '', $name);

        return $name;
    }

    private function parseHtml(Email $email): void
    {
        $this->logger->debug(__FUNCTION__);

        if (empty($this->http->FindSingleNode("(//*[" . $this->contains($this->t("includes the following trips:")) . "])[1]"))) {
            return;
        }
        $b = $email->add()->bus();

        // General
        $b->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->contains($this->t("includes the following trips:")) . "][1]", null, true, "#\([^\d\(\)]{1,5}\s*([A-Z\d]{5,})\s*\)#"))
        ;

        // Segments
        $xpath = "//text()[" . $this->starts($this->t("Line")) . "]";
        $this->logger->debug('$xpath-' . $xpath);
        $nodes = $this->http->XPath->query($xpath);

        //62593782
        if ($nodes->count() > 0
            && empty($this->re("#^\s*(\D+ → \D+)\n#ms", $nodes->item(0)->nodeValue))
            && empty($this->re("#(\D+ → \D+)$#ms", $nodes->item(0)->nodeValue))
        ) {
            $xpath = "//text()[" . $this->starts($this->t("Line")) . "]/ancestor::div[1]";
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $s = $b->addSegment();

            $text = $root->nodeValue;

            if (preg_match("/{$this->preg_implode($this->t("Line"))} (?<number>\w+?)\W.+? (?<date>\S*(?:\d{4}|\d{2}[.\/]\d{2}[.\/]\d{2}\b))\S* (?<time>{$this->patterns['time']})\s+(?<from>.+) → (?<to>\D+)\d?/", $text, $m)) {
                $s->departure()
                    ->name($m['from'])
                    ->date(strtotime($this->normalizeTime($m['time']), $this->normalizeDate($m['date'])));
                $s->arrival()
                    ->name($m['to'])
                    ->noDate();

                $s->extra()
                    ->number($m['number']);

                $seats = $this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "#:\s*(\d{1,2}[A-Z]((?:,[ ]{0,2})\d{1,2}[A-Z])*)\s*$#");

                if (empty($seats)) {
                    $seats = $this->re("/Sitz\:\s+\D+\d?\:\s+(\d+[A-Z]{1})\s+/", $text);
                }

                if (!empty($seats)) {
                    $s->extra()
                        ->seats(array_map('trim', explode(",", $seats)));
                }
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str1 = ' . print_r($str, true));
        $in = [
            // diumenge, 07 d’ag. de 2022
            // Sonntag, 07. Aug. 2022
            // terça-feira, 01 de nov. de 2022
            // 16. Okt. 2022
            // вівторок, 29 лист. 2022 р.
            // søndag den 23. okt. 2022
            "/^\s*(?:[-[:alpha:] ]+[,\s]+)?(\d{1,2})\.?\s+(?:d’|d'|de\s+)?([[:alpha:]]+)\.?(?:\s+de)?\s+(\d{4})\s*(?:р\.)?\s*$/u",

            '/^\s*(\d{1,2})\.(\d{2})\.(\d{2})\s*$/', // 11.08.17
            '/^\s*(\d{1,2})\.(\d{2})\.(\d{4})\s*$/', // 11.01.2020

            // 2022. nov. 26., szombat
            '/^\s*(\d{4})\.\s*([[:alpha:]]+)\.\s*(\d{1,2})\.\s*,\s*[-[:alpha:]]+\s*$/u',
            // nov. 27. 2022
            '/^\s*([-[:alpha:]]+)\.\s*(\d{1,2})\.\s*(\d{4})\s*$/u',
        ];
        $out = [
            '$1 $2 $3',

            '$1.$2.20$3',
            '$1.$2.$3',

            '$3 $2 $1',
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str2 = ' . print_r($str, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeTime(?string $s): string
    {
        // $this->logger->debug('$s1 = ' . print_r($s, true));
        $s = preg_replace([
            '/([AaPp])\.[ ]*([Mm])\.?/', // 2:04 p. m.    ->    2:04 pm
            '/(\d)[.：](\d)/u', // 17.25    ->    17:25
        ], [
            '$1$2',
            '$1:$2',
        ], $s);

        return $s;
    }

    private function TableHeadPos($row): array
    {
        $head = array_filter(array_map('trim', explode("%", preg_replace("#\s{2,}#", "%", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function SplitCols($text, $pos = false, $trim = true): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k=>$p) {
                if ($trim === true) {
                    $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                } else {
                    $cols[$k][] = mb_substr($row, $p, null, 'UTF-8');
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function split($re, $text): array
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));

        if (empty($textRows)) {
            return '';
        }
        $pos = [];
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }

    private function currency($s): ?string
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '$'   => 'USD',
            '€'   => 'EUR',
            '£'   => 'GBP',
            'zł'  => 'PLN',
            '₽'   => 'RUB',
            'Kč'  => 'CZK',
            'R$'  => 'BRL',
            '₹'   => 'INR',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function strposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }
}
