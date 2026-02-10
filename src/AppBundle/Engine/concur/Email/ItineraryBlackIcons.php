<?php

namespace AwardWallet\Engine\concur\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar formats: Itinerary1

class ItineraryBlackIcons extends \TAccountChecker
{
    public $mailFiles = "concur/it-1.eml, concur/it-10564455.eml, concur/it-58772094.eml, concur/it-10753532.eml, concur/it-12224832.eml, concur/it-12227772.eml, concur/it-12447684.eml, concur/it-13338065-fr.eml, concur/it-13418090.eml, concur/it-1471677.eml, concur/it-1471678.eml, concur/it-1550936.eml, concur/it-1569266.eml, concur/it-1569267.eml, concur/it-1603246.eml, concur/it-1604597.eml, concur/it-1604887.eml, concur/it-1608405.eml, concur/it-1608406.eml, concur/it-2.eml, concur/it-2003514.eml, concur/it-2082827.eml, concur/it-2082828.eml, concur/it-21.eml, concur/it-2360755.eml, concur/it-2407841.eml, concur/it-2427240.eml, concur/it-2514699.eml, concur/it-2544670.eml, concur/it-2585676.eml, concur/it-2585681.eml, concur/it-2779347.eml, concur/it-2784739.eml, concur/it-3.eml, concur/it-3034384.eml, concur/it-3034617.eml, concur/it-3102005.eml, concur/it-3211896.eml, concur/it-3449480.eml, concur/it-35687380.eml, concur/it-3884982.eml, concur/it-4.eml, concur/it-6437407-fr.eml, concur/it-6437419-fr.eml, concur/it-6437429-fr.eml, concur/it-6918636.eml, concur/it-9834665-pt.eml, concur/it-713186897-de.eml"; // +3 bcdtravel(html)[es,fr,sv]

    public $lang = '';
    public $emailDate;

    public $langDetectors = [
        'fr' => ['Réservations', 'Votre voyage a été annulé avec succès'],
        'es' => ['Este itinerario se envió por correo electrónico por solicitud', 'Reservaciones', 'Información general del viaje', 'La agencia debe emitir el boleto del aéreo antes de'],
        'en' => ['Reservations', 'Start Date:', 'Additional Details', 'was cancelled'],
        'pt' => ['Visão geral da viagem'],
        'de' => ['Reservierungen', 'Abreise'],
        'it' => ['Prenotazioni'],
        'ja' => ['開始日'],
        'sv' => ['Reseöversikt'],
        'sk' => ['Rezervácie'],
        'da' => ['Reservationer'],
        'cs' => ['Rezervace'],
        'hu' => ['Foglalások'],
        'pl' => ['Rezerwacje'],
        'nl' => ['Reisoverzicht'],
    ];
    public $subject = '';
    public $generalValue = [];

    public static $dictionary = [
        'en' => [
            //			'Created:' => '',
            "patternDate" => '/^([^,.\d\s]{3,}\s+\d{1,2}\s*,\s*\d{2,4}|\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{4}|\d{4}, +[^,.\d\s]{3,} \d+)/', // August 31, 2015 | 06 November, 2015 | 2018, August 14
            "Passengers"  => ['Passengers', 'Reservation for'],
            //			'Ticket Number(s):' => '',
            //			'Agency Name:' => '',
            'successfully cancelled' => ['successfully cancelled', 'TRIP HAS BEEN CANCELLED'],
            //			'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            //			'Total Estimated Cost:' => '',
            //			'Agency Record Locator:' => '',
            "Confirmation:" => ["Confirmation:", "Confirmation Number:"],
            //			'Status:' => '',
            'FF numbers'     => ['FF numbers', 'Air Frequent Flyer Number'],
            'Daytime Phone:' => ['Daytime Phone:', 'Nighttime Phone:'],

            // FLIGHT (and trains)
            //			'Flight' => '',
            'Departure:' => ["Departure:", 'Departs:'],
            //			'Operated by:' => '',
            //			'to' => '',
            //			'Arrival:' => '',
            //			'Aircraft:' => '',
            //			'Distance:' => '',
            //			'Cabin:' => '',
            //			'Seat:' => '',
            'No seat assignment' => ['N', 'No seat assignment'],
            //            'Seat' => '',
            //			'Duration:' => '',
            //			'Meal:' => '',
            //			'stop' => '', //nonstop

            "Airfare total:" => ["Airfare total:", "Air Total Price:"],
            //			'Airfare quoted amount:' => '',
            //			'Taxes and fees:' => '',

            // TRAIN
            "Train" => ['Train', 'Rail'],
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            "Train Base Fare:" => ["Train Base Fare:", "Rail Base Fare:", "Ticket Price:"],
            //			"Train Total Price:" => "",

            // HOTEL
            //			'Checking In:' => '',
            //			'Checking Out:' => '',
            "Rooms" => ["Rooms", "Room"],
            //			'Guests' => '',
            'Daily Rate:' => ['Daily Rate:', 'Daily rate:'],
            //'Rate:' => '',
            //			'Room Description:' => '',
            //			'Cancellation Policy' => '',
            "Total Rate:" => ['Total Rate:', 'Total rate:'],

            // CAR (and transfer)
            //			'Rental Details' => '',
            //			'Pick Up:' => '',
            //			'Pick-up at:' => '',
            //			'Phone:' => '',
            //			'Return:' => '',
            "Returning to:" => ["Returning to:", "Drop-off At:"],
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'sk' => [
            "Created:"          => 'Vytvorené:',
            "patternDate"       => '/^(\d+ [^\s\d]+, \d{4}|[^\d\s]+ \d{1,2}, \d{4})/', // 09 Septembre, 2016 | Oktober 30, 2018
            "Passengers"        => ['Rezervácia pre:'],
            'Ticket Number(s):' => 'Ticketnummer(n):',
            'Agency Name:'      => 'Názov agentúry:',
            //			'successfully cancelled' => '',
            'Reservations' => 'Rezervácie',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => 'Celková kalkulovaná cena:',
            'Agency Record Locator:' => 'Lokátor záznamu agentúry:',
            "Confirmation:"          => 'Potvrdenie:',
            "Status:"                => ['Status:'],
            "FF numbers"             => 'Rezervované priamo v',
            // 'Daytime Phone:' => '',

            // FLIGHT (and trains)
            //'Flight'     => '',
            //'Departure:' => '',
            //			'Operated by:' => '',
            //'to'                 => [''],
            //'Arrival:'           => '',
            //'Aircraft:'          => '',
            //'Distance:'          => '',
            //'Cabin:'             => [''],
            //'Seat:'              => [''],
            //'No seat assignment' => '',
            //'Seat'               => '',
            //'Duration:'          => '',
            //'Meal:'              => '',
            //			'stop' => '', //nonstop

            //			"Airfare total:" => '',
            //			'Airfare quoted amount:' => '',
            //'Taxes and fees:' => '',

            // TRAIN
            //"Train" => '',
            //			'Class of Service:' => '',
            //'Class:' => '',
            //			'Number of Stops:' => '',
            //"Train Base Fare:"   => '',
            //"Train Total Price:" => "",
            //'Coach'              => '',

            // HOTEL
            "Checking In:"        => 'Prihlásenie:',
            "Checking Out:"       => 'Odhlásenie:',
            "Rooms"               => 'Izba',
            "Guests"              => 'Hostia',
            //"Daily Rate:"         => '',
            'Rate:'               => 'Sadzba:',
            "Room Description:"   => 'Popis izby:',
            "Cancellation Policy" => "Podmienky pre storno",
            "Total Rate:"         => 'Hotel:',

            // CAR (and transfer)
            'Rental Details' => 'Detaily prenájmu',
            'Pick Up:'       => 'Vyzdvihnúť:',
            'Pick-up at:'    => 'Vyzdvihnúť v:',
            //'Phone:'         => '',
            'Return:'        => 'Vrátenie:',
            "Returning to:"  => 'Vrátenie do:',
            ' at: '          => ' do: ',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'fr' => [
            "Created:"    => 'Créé:',
            "patternDate" => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{2,4}|\d{4},\s*[^,.\d\s]{3,}\s*\d{1,2})/', // 09 Septembre, 2016; 2018, Mai 14,
            "Passengers"  => ['Passagers', 'Réservation de :'],
            //			'Ticket Number(s):' => '',
            "Agency Name:" => "Nom de l'agence :",
            //			'successfully cancelled' => '',
            'Reservations'       => 'Réservations',
            'Hotel Cancellation' => "Annulation d'hôtel",
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => 'Coût total estimé:',
            "Agency Record Locator:" => "Numéro de dossier de l'agence :",
            "Confirmation:"          => 'Confirmation',
            "Status:"                => ['Statut :', 'Statut :', 'Statut:'], // &nbsp;
            'FF numbers'             => 'Numéro de Grand voyageur',
            'Daytime Phone:'         => 'Téléphone le jour',

            // FLIGHT (and trains)
            "Flight"     => ['Vols', 'Vol'],
            "Departure:" => 'Départ',
            //			'Operated by:' => '',
            "to"        => ['à destination de', "jusqu'au", 'à'],
            "Arrival:"  => 'Arrivée',
            "Terminal"  => ["Terminal", "Aérogare", "AEROGARE"],
            "Aircraft:" => 'Avion',
            //			'Distance:' => '',
            "Cabin:"                 => 'Cabine',
            'Seat:'                  => 'Siège:',
            'No seat assignment'     => 'Aucune affectation de siège',
            'Seat'                   => 'Siège',
            'Duration:'              => 'Durée :',
            "Meal:"                  => 'Repas',
            "stop"                   => "", //nonstop
            "Airfare total:"         => 'Avion Prix total:',
            "Airfare quoted amount:" => 'Montant du tarif aérien proposé :',
            "Taxes and fees:"        => 'Taxes et frais :',

            // TRAIN
            "Train"             => 'Train',
            'Class of Service:' => 'Catégorie:',
            'Class:'            => 'Classe:',
            //			'Number of Stops:' => '',
            "Train Base Fare:"   => 'Tarif de base:',
            "Train Total Price:" => "Train Prix total:",
            'Coach'              => 'Voiture',

            // HOTEL
            "Checking In:"        => ['Arrivée :', 'Enregistrement :'],
            "Checking Out:"       => 'Départ :',
            "Rooms"               => 'Chambre',
            "Guests"              => ['Clients', 'Invités'],
            "Daily Rate:"         => 'Tarif journalier:',
            //'Rate:' => '',
            "Room Description:"   => 'Description de la chambre:',
            "Cancellation Policy" => "Politique d'annulation",
            "Total Rate:"         => ['Tarif total:', 'Prix total:'],

            // CAR (and transfer)
            "Rental Details" => 'Détails de la location',
            "Pick Up:"       => 'Retrait :',
            "Pick-up at:"    => ['Prise en charge à :', 'Prise en charge à :', 'Retrait à :'], // &nbsp;
            //			'Phone:' => '',
            "Return:"       => 'Retour :',
            "Returning to:" => ['Retour à :', 'Retour à :'], // &nbsp;
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'it' => [
            "Created:"    => 'Creato:',
            "patternDate" => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{2,4}|\d{4},\s*[^,.\d\s]{3,}\s*\d{1,2})/', // 09 Septembre, 2016; 2018, Mai 14,
            "Passengers"  => ['Prenotazione per:', 'Passeggeri:'],
            //			'Ticket Number(s):' => '',
            "Agency Name:" => "Nome agenzia:",
            //			'successfully cancelled' => '',
            //            'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => 'Costo totale stimato:',
            "Agency Record Locator:" => "Ubicatore di dati agenzia:",
            "Confirmation:"          => 'Conferma:',
            "Status:"                => ['Stato:'], // &nbsp;
            "FF numbers"             => 'Numero di viaggiatore abituale per via aerea',
            //            'Daytime Phone:' => '',

            // FLIGHT (and trains)
            "Flight"     => 'Volo',
            "Departure:" => 'Partenza:',
            //			'Operated by:' => '',
            "to"        => ['a'],
            "Arrival:"  => 'Arrivo:',
            "Aircraft:" => 'Velivolo:',
            'Distance:' => 'Distanza:',
            "Cabin:"    => 'Cabina:',
            'Seat:'     => 'Posto a sedere:',
            //            'No seat assignment' => '',
            'Seat'                   => 'Posto a sedere',
            'Duration:'              => 'Durata:',
            "Meal:"                  => 'Pasto:',
            "stop"                   => "Diretto", //nonstop
            "Airfare total:"         => 'Voli Prezzo totale:',
            "Airfare quoted amount:" => 'Importo tariffa aerea quotata:',
            "Taxes and fees:"        => 'Imposte e tariffe:',

            // TRAIN
            //			"Train" => 'Train',
            //			'Class of Service:' => ':',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //			"Train Base Fare:" => '',
            //			"Train Total Price:" => "",
            //			'Coach' => '',

            // HOTEL
            "Checking In:"        => ['Check-in:'],
            "Checking Out:"       => 'Check-out:',
            "Rooms"               => 'Camera',
            "Guests"              => ['Ospiti'],
            "Daily Rate:"         => 'Tariffa giornaliera:',
            //'Rate:' => '',
            "Room Description:"   => 'Descrizione della stanza:',
            "Cancellation Policy" => "Politica di cancellazione",
            "Total Rate:"         => ['Tariffa totale:', 'Costo totale stimato:'],

            // CAR (and transfer)
            "Rental Details" => 'Informazioni di noleggio',
            "Pick Up:"       => ['Ritiro:'],
            "Pick-up at:"    => ['Ritiro a'], // &nbsp;
            //			'Phone:' => '',
            "Return:"       => ['Ritorno:'],
            "Returning to:" => ['Restituzione a', 'Restituzione a:'], // &nbsp;
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'es' => [
            "Created:"          => ['Creado', 'Creado:'],
            "patternDate"       => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{2,4}|[^,.\d\s]{3,}\s+\d{1,2}\s*,\s*\d{2,4})/', //18 Enero, 2018; Abril 08, 2018
            "Passengers"        => ['Pasajeros', 'Reservación de:', 'Reserva para:'],
            "Ticket Number(s):" => 'Números de billetes',
            'Agency Name:'      => 'Nombre de la agencia:',
            //			'successfully cancelled' => '',
            'Reservations' => ['Reservas', 'Reservaciones'],
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => ['Costo total aproximado:', 'Coste total estimado:'],
            'Agency Record Locator:' => 'Localizador de registros de agencia:',
            "Confirmation:"          => 'Confirmación',
            "Status:"                => 'Estado:',
            //			"FF numbers" => ['Número de viajero frecuente', 'Número de viajero frecuente en avión'],
            //            'Daytime Phone:' => [''],

            // FLIGHT (and trains)
            "Flight"       => 'Vuelo',
            "Departure:"   => ['Partida', 'Salida'],
            'Operated by:' => 'Operado por:',
            "to"           => ['hasta', 'a'],
            "Arrival:"     => 'Llegada',
            "Aircraft:"    => 'Avión',
            "Distance:"    => 'Distancia',
            "Cabin:"       => 'Cabina',
            "Seat:"        => 'Asiento:',
            //            'No seat assignment' => '',
            //            'Seat' => '',
            "Duration:" => 'Duración:',
            "Meal:"     => ['Comida', 'Almuerzo:'],
            //			'stop' => '',//nonstop
            "Airfare total:"         => ['Aéreo Precio total:', 'Vuelo Precio total:'],
            "Airfare quoted amount:" => ['Monto de la tarifa aérea indicada:', 'Cantidad cotizada de tarifa aérea:'],
            "Taxes and fees:"        => 'Impuestos y honorarios:',

            // TRAIN
            //			"Train" => '',
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //			"Train Base Fare:" => '',
            //			"Train Total Price:" => "",
            //			'Coach' => '',

            // HOTEL
            "Checking In:"        => ['Registro:', 'Registro de entrada:'],
            "Checking Out:"       => ['Salida:', 'Registro de salida:'],
            "Rooms"               => 'Habitación',
            "Guests"              => ['Huéspedes', 'Clientes'],
            "Daily Rate:"         => 'Tarifa diaria:',
            'Rate:'               => 'Tarifa:',
            "Room Description:"   => 'Descripción de la habitación:',
            "Cancellation Policy" => 'Política de cancelación',
            "Total Rate:"         => 'Tarifa total:',

            // CAR (and transfer)
            'Rental Details' => ['Detalles del alquiler', 'Detalles de alquiler'],
            'Pick Up:'       => ['Origen ', 'Recogida:', 'Retiro en agencia:'],
            'Pick-up at:'    => ['Origen:', 'Recoger en:', 'Retiro en agencia en:'],
            'Phone:'         => 'Teléfono:',
            'Return:'        => ['Vuelta:', 'Volver:'],
            "Returning to:"  => ['Devolución a:', 'Regreso a:'],
            ' at: '          => ' en: ',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'pt' => [
            "Created:"    => 'Criado:',
            "patternDate" => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{2,4})/', // 09 Septembre, 2016
            "Passengers"  => 'Passageiros',
            //			'Ticket Number(s):' => '',
            "Agency Name:" => "Nome da agência:",
            //			'successfully cancelled' => '',
            //            'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => 'Custo total estimado:',
            "Agency Record Locator:" => "Localizador de registros da agência:",
            "Confirmation:"          => 'Confirmação',
            "Status:"                => ['Estado:'],
            //			"FF numbers" => '',
            'Daytime Phone:' => ['Telefone dia', 'Telefone noite'],

            // FLIGHT (and trains)
            "Flight"     => 'Voo',
            "Departure:" => 'Embarque',
            //			'Operated by:' => '',
            "to"        => ['para'],
            "Arrival:"  => 'Chegada',
            "Aircraft:" => 'Avião',
            "Distance:" => 'Distância',
            "Cabin:"    => 'Cabina',
            "Seat:"     => 'Poltrona',
            //            'No seat assignment' => '',
            //            'Seat' => '',
            "Duration:"              => 'Duração',
            "Meal:"                  => 'Refeição',
            "stop"                   => "Direto", //nonstop
            "Airfare total:"         => 'Segmento aéreo Preço total:',
            "Airfare quoted amount:" => 'Valor indicado tarifa aérea:',
            "Taxes and fees:"        => 'Impostos e taxas:',

            // TRAIN
            //			"Train" => '',
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //			"Train Base Fare:" => '',
            //			"Train Total Price:" => "",
            //			'Coach' => '',

            // HOTEL
            "Checking In:"        => 'Check-in:',
            "Checking Out:"       => 'Checkout:',
            "Rooms"               => 'Quarto',
            "Guests"              => 'Clientes',
            "Daily Rate:"         => 'Tarifa diária:',
            //'Rate:' => '',
            "Room Description:"   => 'Descrição do quarto:',
            "Cancellation Policy" => "Política de cancelamento",
            "Total Rate:"         => 'Tarifa total:',

            // CAR (and transfer)
            'Rental Details' => ['Detalhes do Aluguel'],
            'Pick Up:'       => 'Retirada:',
            'Pick-up at:'    => 'Retirada em:',
            //			'Phone:' => '',
            'Return:'       => 'Devolução:',
            "Returning to:" => 'Devolução a:',
            ' at: '         => ' em:',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'cs' => [
            "Created:"          => 'Vytvořeno:',
            "patternDate"       => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{2,4})/', // 09 Septembre, 2016
            "Passengers"        => 'Cestující',
            'Ticket Number(s):' => 'Číslo jízdenky/letenky:',
            "Agency Name:"      => "Název kanceláře:",
            //			'successfully cancelled' => '',
            //            'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => 'Celkové odhadované náklady:',
            "Agency Record Locator:" => "Lokátor záznamů kanceláře:",
            "Confirmation:"          => 'Potvrzení',
            "Status:"                => ['Stav:'],
            //			"FF numbers" => '',
            //            'Daytime Phone:' => [''],

            // FLIGHT (and trains)
            "Flight"     => 'Let',
            "Departure:" => 'Odlet',
            //			'Operated by:' => '',
            "to"                 => ['do'],
            "Arrival:"           => 'Přílet',
            "Terminal"           => ["Terminal", "Terminál"],
            "Aircraft:"          => 'Letadlo',
            "Distance:"          => 'Vzdálenost',
            "Cabin:"             => 'Třída',
            "Seat:"              => 'Sedadlo',
            'No seat assignment' => 'Bez přiřazení sedadla',
            //            'Seat' => '',
            "Duration:"      => 'Doba trvání',
            "Meal:"          => 'Jídlo',
            "stop"           => "Nonstop", //nonstop
            "Airfare total:" => 'Letadlo Celková cena:',
            //            "Airfare quoted amount:" => '',
            "Taxes and fees:" => 'Daně a poplatky:',

            // TRAIN
            //			"Train" => '',
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //			"Train Base Fare:" => '',
            //			"Train Total Price:" => "",
            //			'Coach' => '',

            // HOTEL
            "Checking In:"        => 'Registrace:',
            "Checking Out:"       => 'Odhlášení:',
            "Rooms"               => 'Pokoj',
            "Guests"              => 'Hosté',
            "Daily Rate:"         => 'Denní sazba:',
            //'Rate:' => '',
            "Room Description:"   => 'Popis pokoje:',
            "Cancellation Policy" => "Podmínky zrušení",
            "Total Rate:"         => 'Celková sazba:',

            // CAR (and transfer)
            'Rental Details' => 'Detaily pronájmu',
            'Pick Up:'       => 'Vyzvednutí:',
            'Pick-up at:'    => 'Vyzvednutí v:',
            //			'Phone:' => '',
            'Return:'       => 'Vrácení:',
            "Returning to:" => 'Vrácení v:',
            ' at: '         => 'vozidel u:',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'hu' => [
            "Created:"    => 'Létrehozva:',
            "patternDate" => '/^([^,.\d\s]{3,}\s+\d{1,2}\s*,\s*\d{4})/', // Április 04, 2019
            "Passengers"  => 'Foglalás',
            //			'Ticket Number(s):' => '',
            "Agency Name:" => "Iroda neve",
            //			'successfully cancelled' => '',
            //            'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            //            "Total Estimated Cost:" => '',
            //            "Agency Record Locator:" => "",
            "Confirmation:" => 'Visszaigazolás',
            "Status:"       => ['Állapot:'],
            //			"FF numbers" => '',
            //            'Daytime Phone:' => [''],

            // FLIGHT (and trains)
            //            "Flight" => '',
            //            "Departure:" => '',
            //			'Operated by:' => '',
            //            "to" => [''],
            //            "Arrival:" => '',
            //            "Terminal" => [""],
            //            "Aircraft:" => '',
            //            "Distance:" => '',
            //            "Cabin:" => '',
            //            "Seat:" => '',
            //            'No seat assignment' => '',
            //            'Seat' => '',
            //            "Duration:" => '',
            //            "Meal:" => '',
            //            "stop" => "",//nonstop
            //            "Airfare total:" => '',
            //            "Airfare quoted amount:" => '',
            //            "Taxes and fees:" => '',

            // TRAIN
            //			"Train" => '',
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //			"Train Base Fare:" => '',
            //			"Train Total Price:" => "",
            //			'Coach' => '',

            // HOTEL
            "Checking In:"        => 'Bejelentkezés:',
            "Checking Out:"       => 'Kijelentkezés:',
            "Rooms"               => 'Szoba',
            "Guests"              => 'Vendég',
            "Daily Rate:"         => 'Denní sazba:',
            //'Rate:' => '',
            "Room Description:"   => 'Szoba ismertetése:',
            "Cancellation Policy" => "Lemondási szabályzat",
            "Total Rate:"         => 'Szálloda:',

            // CAR (and transfer)
            //            'Rental Details' => '',
            //            'Pick Up:' => '',
            //            'Pick-up at:' => '',
            //			'Phone:' => '',
            //            'Return:' => '',
            //            "Returning to:" => '',
            //            ' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'de' => [
            "Created:"          => 'Erstellt:',
            "patternDate"       => '/^(\d{1,2} [[:alpha:]]+, \d{4}|[[:alpha:]]+ \d{1,2}, \d{4})/u', // 09 Septembre, 2016 | Oktober 30, 2018
            "Passengers"        => ['Reservierung für:', 'Passagiere:'],
            'Ticket Number(s):' => 'Ticketnummer(n):',
            'Agency Name:'      => 'Name des Reisebüros:',
            //			'successfully cancelled' => '',
            'Reservations' => 'Reservierungen',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => 'Geschätzte Gesamtkosten:',
            'Agency Record Locator:' => 'Agenturbuchungscode:',
            "Confirmation:"          => 'Bestätigung:',
            "Status:"                => ['Status:'],
            "FF numbers"             => 'Vielfliegernummer',
            'Daytime Phone:'         => 'Telefon (tagsüber):',

            // FLIGHT (and trains)
            'Flight'     => 'Flug',
            'Departure:' => ['Abreise:', 'Abflug:'],
            //			'Operated by:' => '',
            'to'                 => ['nach', 'bis'],
            'Arrival:'           => 'Ankunft:',
            'Aircraft:'          => 'Flugzeug:',
            'Distance:'          => 'Entfernung:',
            'Cabin:'             => ['Cabin:', 'Kabine:'],
            'Seat:'              => ['Sitzplatz:', 'Sitz:'],
            'No seat assignment' => 'Keine Sitzplatzzuweisung',
            'Seat'               => 'Sitz',
            'Duration:'          => 'Dauer:',
            'Meal:'              => 'Mahlzeit:',
            //			'stop' => '', //nonstop

            //			"Airfare total:" => '',
            'Airfare quoted amount:' => 'Angegebener Flugkostenbetrag:',
            'Taxes and fees:'        => 'Steuern und Gebühren:',

            // TRAIN
            "Train" => 'Zug',
            //			'Class of Service:' => '',
            'Class:' => 'Klasse:',
            //			'Number of Stops:' => '',
            "Train Base Fare:"   => 'Zugfahrkartenpreis:',
            "Train Total Price:" => "Geschätzte Gesamtkosten:",
            'Coach'              => 'Wagen',

            // HOTEL
            "Checking In:"        => 'Check-In:',
            "Checking Out:"       => 'Check-Out:',
            "Rooms"               => 'Zimmer',
            "Guests"              => 'Gäste',
            "Daily Rate:"         => 'Preis/Tag:',
            //'Rate:' => '',
            "Room Description:"   => 'Raumbeschreibung:',
            "Cancellation Policy" => "Stornierungsrichtlinien",
            "Total Rate:"         => 'Gesamttarif:',

            // CAR (and transfer)
            'Rental Details' => 'Informationen zum Mietwagen',
            'Pick Up:'       => 'Abholung:',
            'Pick-up at:'    => 'Abholung bei:',
            'Phone:'         => 'Telefon:',
            'Return:'        => 'Rückgabe:',
            "Returning to:"  => 'Zurückgeben bei:',
            ' at: '          => 'Mietwagen ab:',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'ja' => [
            "Created:"          => '作成:',
            "patternDate"       => '/^(\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日)/', // 2019年1月04日
            "Passengers"        => ['予約:'],
            'Ticket Number(s):' => 'チケット番号:',
            'Agency Name:'      => '代理店名:',
            'Daytime Phone:'    => ['日中の連絡先電話番号:', '夜間電話番号:'],
            //			'successfully cancelled' => '',
            'Reservations' => '予約',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            "Total Estimated Cost:"  => '見積費用合計:',
            'Agency Record Locator:' => '代理店レコード ロケーター:',
            "Confirmation:"          => '確認:',
            "Status:"                => ['ステータス:'],
            "FF numbers"             => 'マイレージ会員番号:',

            // FLIGHT (and trains)
            'Flight'     => 'フライト',
            'Departure:' => '出発:',
            //			'Operated by:' => '',
            'to'        => ['から'],
            'Arrival:'  => '到着:',
            'Aircraft:' => '航空機:',
            'Distance:' => '距離:',
            'Cabin:'    => ['客席:'],
            'Seat:'     => '座席:',
            //            'No seat assignment' => '',
            'Seat' => '座席',
            //            'Duration:' => '',
            //            'Meal:' => '',
            //			'stop' => '', //nonstop
            'Terminal' => 'ターミナル:', //nonstop

            //			"Airfare total:" => '',
            'Airfare quoted amount:' => 'フライト 合計価格:',
            'Taxes and fees:'        => '税および手数料:',

            // TRAIN
            //            "Train" => '',
            //            'Class of Service:' => '',
            //            'Class:' => '',
            //            'Number of Stops:' => '',
            //            "Train Base Fare:" => '',
            //            "Train Total Price:" => "",
            //            'Coach' => '',

            // HOTEL
            "Checking In:"        => 'チェックイン:',
            "Checking Out:"       => 'チェックアウト:',
            "Rooms"               => '部屋数',
            "Guests"              => 'ゲスト数',
            "Daily Rate:"         => '1 日あたりの料金:',
            //'Rate:' => '',
            "Room Description:"   => '部屋の説明:',
            "Cancellation Policy" => "キャンセル ポリシー",
            "Total Rate:"         => '合計レート:',

            // CAR (and transfer)
            //			'Rental Details' => '',
            //			'Pick Up:' => '',
            //			'Pick-up at:' => '',
            //			'Phone:' => '',
            //			'Return:' => '',
            //			"Returning to:" => '',
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'sv' => [
            'Created:'    => 'Skapades:',
            'patternDate' => '/^(\d{4}\s*,\s*[[:alpha:]]{3,}\s+\d{1,2})\b/u', // 2019, September 09
            'Passengers'  => 'Passagerare',
            //            'Ticket Number(s):' => '',
            'Agency Name:' => 'Namn på resebyrå:',
            //            'successfully cancelled' => '',
            //            'Reservations' => '',
            //            'Hotel Cancellation' => '',
            //            'Trip Cancelled' => '',
            'Total Estimated Cost:'  => 'Total uppskattad kostnad:',
            'Agency Record Locator:' => 'Resebyråns bokningsreferens:',
            'Confirmation:'          => 'Bekräftelse',
            'Status:'                => ['Status:'],
            'FF numbers'             => 'Bonusprogramnummer',
            //            'Daytime Phone:' => '',

            // FLIGHT (and trains)
            'Flight'     => 'Flyg',
            'Departure:' => 'Avgång',
            //            'Operated by:' => '',
            'to'        => ['till'],
            'Arrival:'  => 'Ankomst',
            'Aircraft:' => 'Flygplan',
            //            'Distance:' => '',
            'Cabin:'             => 'Kabin',
            'Seat:'              => 'Sittplats',
            'No seat assignment' => 'Ingen sittplatsanvisning',
            //            'Seat' => '',
            'Duration:'              => 'Restid',
            'Meal:'                  => 'Måltid',
            'stop'                   => 'Nonstop',
            'Airfare total:'         => 'Belopp offererat biljettpris:',
            'Airfare quoted amount:' => 'Belopp offererat biljettpris:',
            'Taxes and fees:'        => 'Skatter och avgifter:',

            // TRAIN
            //            'Train' => '',
            //            'Class of Service:' => '',
            //            'Class:' => '',
            //            'Number of Stops:' => '',
            //            'Train Base Fare:' => '',
            //            'Train Total Price:' => '',
            //            'Coach' => '',

            // HOTEL
            'Checking In:'        => 'Incheckning:',
            'Checking Out:'       => 'Utcheckning:',
            'Rooms'               => 'Rum',
            'Guests'              => 'Gäster',
            'Daily Rate:'         => 'Daglig taxa:',
            //'Rate:' => '',
            'Room Description:'   => 'Rumbeskrivning:',
            'Cancellation Policy' => 'Avbokningspolicy',
            'Total Rate:'         => 'Totalt pris:',

            // CAR (and transfer)
            //            'Rental Details' => '',
            //            'Pick Up:' => '',
            //            'Pick-up at:' => '',
            //            'Phone:' => '',
            //            'Return:' => '',
            //            'Returning to:' => '',
            //            ' at: ' => '',

            //TRANSFER
            //            'Ground Reservation' => '',
        ],
        'da' => [
            'Created:'    => 'Oprettet:',
            "patternDate" => '/^(\d{1,2}\s+[^,.\d\s]{3,}\s*,\s*\d{4})/', // 06 November, 2015
            "Passengers"  => 'Passagerer',
            //			'Ticket Number(s):' => '',
            'Agency Name:' => 'Bureaunavn:',
            //            'successfully cancelled' => [''],
            //            'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            'Total Estimated Cost:'  => 'Samlede estimerede omkostninger:',
            'Agency Record Locator:' => 'Rejsebureaus reservationsreference:',
            "Confirmation:"          => ["Bekræftelse:"],
            'Status:'                => 'Status:',
            "FF numbers"             => ['Bonuskundenummer for flyrejser:'],
            'Daytime Phone:'         => ['Telefon i dagtimer:'],

            // FLIGHT (and trains)
            'Flight'       => 'Fly',
            'Departure:'   => 'Afgang:',
            'Operated by:' => 'Drives af:',
            'to'           => 'til',
            'Arrival:'     => 'Ankomst:',
            'Aircraft:'    => 'Fly:',
            'Distance:'    => 'Afstand:',
            'Cabin:'       => 'Kabine:',
            //			'Seat:' => '',
            //            'No seat assignment' => '',
            //            'Seat' => '',
            'Duration:' => 'Varighed:',
            'Meal:'     => 'Måltid:',
            //			'stop' => '', //nonstop

            //            "Airfare total:" => [""],
            'Airfare quoted amount:' => 'Noteret beløb for flybillet:',
            'Taxes and fees:'        => 'Skatter og afgifter:',

            // TRAIN
            //            "Train" => [''],
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //            "Train Base Fare:" => [""],
            //			"Train Total Price:" => "",

            // HOTEL
            'Checking In:'        => 'Indtjekning:',
            'Checking Out:'       => 'Udtjekning:',
            "Rooms"               => ["Værelse"],
            'Guests'              => 'Gæster',
            'Daily Rate:'         => 'Dagssats:',
            //'Rate:' => '',
            'Room Description:'   => 'Værelsesdetaljer',
            'Cancellation Policy' => 'Afbestillingspolitik',
            "Total Rate:"         => ['Samlet pris:'],

            // CAR (and transfer)
            //			'Rental Details' => '',
            //			'Pick Up:' => '',
            //			'Pick-up at:' => '',
            //			'Phone:' => '',
            //			'Return:' => '',
            //          "Returning to:" => [""],
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'pl' => [
            'Created:'    => 'Utworzono:',
            "patternDate" => '/^(\d{1,4}[,\s]+\w{3,},?\s+\d{1,4})/u', // 06 November, 2015
            "Passengers"  => 'Rezerwacja dla',
            //			'Ticket Number(s):' => '',
            'Agency Name:' => 'Nazwa agencji:',
            //            'successfully cancelled' => [''],
            //            'Reservations' => '',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            'Total Estimated Cost:'  => 'Łączny szacowany koszt:',
            'Agency Record Locator:' => 'Lokalizator rekordu agencji:',
            "Confirmation:"          => ["Potwierdzenie:"],
            'Status:'                => 'Status:',
            //			"FF numbers" => [''],
            //            'Daytime Phone:' => [''],

            // FLIGHT (and trains)
            //			'Flight' => '',
            //            'Departure:' => '',
            //			'Operated by:' => '',
            //			'to' => '',
            //			'Arrival:' => '',
            //			'Aircraft:' => '',
            //			'Distance:' => '',
            //			'Cabin:' => '',
            //			'Seat:' => '',
            //            'No seat assignment' => '',
            //            'Seat' => '',
            //			'Duration:' => '',
            //			'Meal:' => '',
            //			'stop' => '', //nonstop

            //            "Airfare total:" => [""],
            //			'Airfare quoted amount:' => '',
            //			'Taxes and fees:' => '',

            // TRAIN
            //            "Train" => [''],
            //			'Class of Service:' => '',
            //			'Class:' => '',
            //			'Number of Stops:' => '',
            //            "Train Base Fare:" => [""],
            //			"Train Total Price:" => "",

            // HOTEL
            'Checking In:'        => 'Zameldowanie:',
            'Checking Out:'       => 'Wymeldowanie:',
            "Rooms"               => ["Pokój"],
            'Guests'              => 'Goście',
            'Daily Rate:'         => 'Stawka:',
            //'Rate:' => '',
            'Room Description:'   => 'Opis pokoju:',
            'Cancellation Policy' => 'Polityka anulowania',
            //          "Total Rate:" => [''],

            // CAR (and transfer)
            //			'Rental Details' => '',
            //			'Pick Up:' => '',
            //			'Pick-up at:' => '',
            //			'Phone:' => '',
            //			'Return:' => '',
            //          "Returning to:" => [""],
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
        'nl' => [
            'Created:'    => 'Gemaakt:',
            "patternDate" => '/^(\d{1,4}[,\s]+\w{3,},?\s+\d{1,4})/u', // 06 November, 2015
            "Passengers"  => 'Passagiers',
            //			'Ticket Number(s):' => '',
            'Agency Name:' => 'Naam bureau:',
            //            'successfully cancelled' => [''],
            'Reservations' => 'Reserveringen',
            //			'Hotel Cancellation' => '',
            //			'Trip Cancelled' => '',
            'Total Estimated Cost:'  => 'Totale geschatte kosten:',
            'Agency Record Locator:' => 'Recordlocator van reisbureau:',
            "Confirmation:"          => ["Bevestiging:"],
            'Status:'                => 'Status:',
            //			"FF numbers" => [''],
            //            'Daytime Phone:' => [''],

            // FLIGHT (and trains)
            'Flight'     => 'Vlucht',
            'Departure:' => 'Vertrek:',
            //			'Operated by:' => '',
            'to'        => ['t/m', 'naar'],
            'Arrival:'  => 'Aankomst:',
            'Aircraft:' => 'Vliegtuig:',
            //			'Distance:' => '',
            'Cabin:' => 'Cabine:',
            'Seat:'  => 'Stoel:',
            //            'No seat assignment' => '',
            'Coach'     => 'Bus',
            'Seat'      => 'Stoel',
            'Duration:' => 'Duur:',
            'Meal:'     => 'Maaltijd:',
            'stop'      => 'Non-stop', //nonstop

            //            "Airfare total:" => [":"],
            'Airfare quoted amount:' => 'Aangegeven bedrag vlucht:',
            'Taxes and fees:'        => 'Belastingen en kosten:',

            // TRAIN
            "Train" => ['Trein'],
            //			'Class of Service:' => '',
            'Class:' => 'Klasse:',
            //			'Number of Stops:' => '',
            //            "Train Base Fare:" => [""],
            //			"Train Total Price:" => "",

            // HOTEL
            'Checking In:'  => 'Inchecken:',
            'Checking Out:' => 'Uitchecken:',
            "Rooms"         => ["Kamer"],
            'Guests'        => 'Gasten',
            'Daily Rate:'   => 'Dagtarief:',
            //'Rate:' => '',
            'Room Description:'   => 'Kamerbeschrijving:',
            'Cancellation Policy' => 'Annuleringsbeleid',
            "Total Rate:"         => ['Totaalprijs:'],

            // CAR (and transfer)
            //			'Rental Details' => '',
            //			'Pick Up:' => '',
            //			'Pick-up at:' => '',
            //			'Phone:' => '',
            //			'Return:' => '',
            //          "Returning to:" => [""],
            //			' at: ' => '',

            //TRANSFER
            //			'Ground Reservation' => '',
        ],
    ];

    private $railSegmentsFlight;
    private $railCompanyInFlight = ['AccesRail'];
    private $enDatesInverted = false;

    /*
    private $travelagency = [
        'bcd'       => ['BCD Travel'],
        'amex'      => ['American Express'],
        'wagonlit'  => ['Carlson Wagonlit Travel', 'CWT '],
        'fcmtravel' => ['FCM Travel Solutions', 'FCM', 'Micron US (FCM)', 'ZS Associates (FCm)'],
        'transport' => ['Travel and Transport Statesman'],
        'hoggrob'   => ['HRG'],
    ];
    */

    private $detectCarsCode = [
        'national'     => ['National Car Rental', 'National Alquiler de automóviles'],
        'avis'         => ['Avis Car Rental', 'Avis'],
        'hertz'        => ['Hertz Car Rental', 'Hertz Aluguel automóvel', 'Hertz Alquiler de automóviles', 'Hertz'],
        'rentacar'     => ['Enterprise Car Rental', 'Enterprise Alquiler de vehículo', 'Enterprise Alquiler de automóviles', 'Enterprise Pronájem'],
        'thrifty'      => ['Thrifty Car Rental'],
        'perfectdrive' => ['Budget Car Rental'],
        'sixt'         => ['Sixt Car Rental'],
        'europcar'     => ['Europcar Car Rental', 'Europcar Alquiler de automóviles'],
        'alamo'        => ['Alamo Car Rental'],
        'dollar'       => ['Dollar Rent A Car'],
    ];

    private $patterns = [
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
    ];

    /**
     * @return array|Email|bool
     *
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     * @throws \Exception
     */
    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        if ($this->assignLang() === false) {
            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (preg_match_all('/\b(\d{1,2})\s*\/\s*\d{1,2}\s*\/\s*\d{4}\b/', $this->http->Response['body'], $dateMatches)) {
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->enDatesInverted = true;

                    break;
                }
            }
        }

        $createdDate = $this->http->FindSingleNode('(//text()[' . $this->starts($this->t("Created:")) . ']/following::text()[string-length(normalize-space(.))>2][1])[1]', null, true, $this->t('patternDate'));

        if (empty($createdDate)) {
            $createdDate = $this->http->FindSingleNode('(//text()[' . $this->starts($this->t("Created:")) . '])[1]', null, true, str_replace('/^', '/' . $this->preg_implode($this->t("Created:")) . '\s*', $this->t('patternDate')));
        }

        if (!empty($createdDate) && $createdDate = $this->normalizeDate($createdDate)) {
            $this->generalValue['reservationDate'] = $this->emailDate = $createdDate;
        } else {
            $this->emailDate = strtotime($parser->getDate());
        }

        $pax = array_filter($this->http->FindNodes('//text()[' . $this->starts($this->t("Passengers")) . ']', null,
            "/{$this->opt($this->t('Passengers'))}\s*:?\s*\b([[:alpha:]].+)/"));

        if (count($pax) === 0) {
            $pax = array_filter($this->http->FindNodes('//text()[' . $this->starts($this->t("Passengers")) . ']/following::text()[string-length(normalize-space(.))>2][1]'));
        }
        $this->generalValue['travelers'] = preg_replace("/\sJr$/", "", $pax);

        $ticketNumberText = $this->http->FindSingleNode('//div[' . $this->contains($this->t("Ticket Number(s):")) . ' and not(.//div)]', null, true, '/:\s*(.+)/');

        if ($ticketNumberText) {
            $this->generalValue['ticketNumbers'] = explode(', ', $ticketNumberText);
        }

        if (!$ota = $this->http->FindSingleNode('//text()[' . $this->starts($this->t("Agency Name:")) . ']/ancestor::*[self::tr or self::div][1][count(descendant::text()[string-length(normalize-space(.))>2])=2]/descendant::text()[string-length(normalize-space(.))>2][2]')) {
            $ota = trim($this->http->FindSingleNode('//img[contains(@src, "concursolutions.com/branding") or contains(@src, "www.concursolutions.com/logos/")]/ancestor::tr[1]/td[2]', null, true, "#^(.+?)(?:\(|\n)#"));
        }
        $ta = $email->ota();
        /* TODO: comments for now... too much unknown providers and questions from partners
        if (!empty($ota)){
//			$ta->keyword($ota);
            foreach ($this->travelagency as $code => $values) {
                foreach ($values as $name) {
                    if (stripos($ota, $name) === 0) {
                        $ta->code($code);
                    }
                }
            }
        }
        */
        $otaPhones = (array) $this->t('Daytime Phone:');
        $addedPhones = [];

        foreach ($otaPhones as $otaPhone) {
            $phone = $this->http->FindSingleNode('//text()[' . $this->starts($otaPhone) . ']/ancestor::*[self::tr or self::div][1][count(descendant::text()[string-length(normalize-space())>2])=2 or count(descendant::text()[string-length(normalize-space())>2])=3]/descendant::text()[string-length(normalize-space())>2][2]', null, true, "/^{$this->patterns['phone']}$/");

            if (!empty($phone) && !in_array($phone, $addedPhones)) {
                $addedPhones[] = $phone;
                $ta->phone($phone, trim($otaPhone, " :"));
            }
            $phone = $this->http->FindSingleNode('//text()[' . $this->starts($otaPhone) . ']/ancestor::*[self::tr or self::div][1][count(descendant::text()[string-length(normalize-space())>2])=2 or count(descendant::text()[string-length(normalize-space())>2])=3]/descendant::text()[string-length(normalize-space())>2][3]', null, true, "/^{$this->patterns['phone']}$/");

            if (!empty($phone) && !in_array($phone, $addedPhones)) {
                $addedPhones[] = $phone;
                $ta->phone($phone, trim($otaPhone, " :"));
            }
        }
        $conf = $this->http->FindSingleNode('//text()[' . $this->eq($this->t("Agency Record Locator:")) . ']/following::text()[normalize-space()][1]', null, true, '/^[-A-Z\d]{5,}$/');

        if (!empty($conf)) {
            $email->ota()->confirmation($conf);
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("successfully cancelled")) . "])[1]"))) {
            $this->generalValue['Cancelled'] = true;
        }

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Hotel Cancellation")) . "])[1]"))) {
            $this->generalValue['CancelledHotel'] = true;
        }

        $this->flight($email);
        $this->hotel($email);
        $this->car($email);
        $this->rail($email);
        $this->transfer($email);

        if (isset($this->generalValue['CancelledHotel']) && count($email->getItineraries()) == 0) {
            // it-58772094.eml
            $xpath = "//text()[{$this->starts($this->t('Checking In:'))}]/ancestor::div[1]";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $nodes = $this->http->XPath->query(".");
            }

            foreach ($nodes as $root) {
                $h = $email->add()->hotel();

                $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Confirmation:'))}][1]", $root);

                if (preg_match("/^({$this->opt($this->t('Confirmation:'))})\s*([-A-Z\d]+)$/", $confirmation, $m)) {
                    $h->general()->confirmation($m[2], rtrim($m[1], ': '));
                }

                $cancellationNumber = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Cancellation Number:'))}][1]", $root, null, "/^{$this->opt($this->t('Cancellation Number:'))}\s*([-A-Z\d]+)$/");
                $h->general()->cancellationNumber($cancellationNumber, false, true);

                if (!empty($this->generalValue['travelers'])) {
                    $h->general()->travellers($this->generalValue['travelers'], true);
                }

                $h->general()->cancelled();
                $h->general()->status('cancelled');

                $text = implode("\n",
                    $this->http->FindNodes(".//text()[" . $this->starts($this->t("Hotel Cancellation")) . "]/ancestor::*[" . $this->contains($this->t("Confirmation:")) . "][1]//text()", $root));

                if (preg_match("#" . $this->opt($this->t("Hotel Cancellation")) . "\s*\n\s*(.+)\n(.+)\n\s*" . $this->opt($this->t("Checking In:")) . "#",
                    $text, $m)) {
                    $h->hotel()->name(trim($m[1]));
                    $h->hotel()->address(trim($m[2]));
                }

                $dateCheckIn = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Checking In:'))}]", $root, true, '/:\s*(.{6,})/'));
                $dateCheckOut = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Checking Out:'))}]", $root, true, '/:\s*(.{6,})/'));
                $h->booked()
                    ->checkIn($dateCheckIn)
                    ->checkOut($dateCheckOut);

                if ($this->http->XPath->query("./descendant::text()[normalize-space()!=''][3][{$this->starts($this->t("Checking In:"))}]",
                        $root)->length == 1
                ) {
                    $h->hotel()
                        ->name($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root))
                        ->address($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][2]", $root));
                }
            }
        } elseif (isset($this->generalValue['Cancelled']) && count($email->getItineraries()) == 0) {
            // it-10564455.eml
            //TODO: comments for now... no information that it was flight and no rule to detect it as a junk for service
//			$f = $email->add()->flight();
//			if (preg_match("#".$this->t("Trip Cancelled")."[:\s\(]*([A-Z\d]{5,7})\b#", $this->subject, $m)) {
//				$f->general()->confirmation($m[1]);
//			}
//			if (!empty($this->generalValue['travelers'])) {
//				$f->general()->travellers($this->generalValue['travelers'], true);
//			}
//
//			$f->general()->cancelled();
//			$f->general()->status('cancelled');
//			$f->setCancelled(true);
        }

        $payment = $this->http->FindSingleNode("//div[ descendant::text()[normalize-space()][1][{$this->eq($this->t("Total Estimated Cost:"))}] and (preceding-sibling::div[normalize-space()] or following-sibling::div[normalize-space()]) ]", null, true, "/^{$this->opt($this->t("Total Estimated Cost:"))}\s*(.*\d.*)$/");

        if ($payment !== null) {
            $email->price()
                ->total($this->amount($payment))
                ->currency($this->currency($payment));

            if (count($email->getItineraries()) === 1) {
                $r = array_values($email->getItineraries())[0];
                $r->price()
                    ->total($this->amount($payment))
                    ->currency($this->currency($payment));
            }
        }

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'Concur Travel') !== false
            || stripos($from, '@concursolutions.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Concur Itinerary') !== false
            || stripos($headers['subject'], 'Trip Cancelled') !== false && $this->detectEmailFromProvider($headers['from']) === true;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//img[contains(@src,"concursolutions.") and contains(@src,"ItineraryUI")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"concursolutions")]')->length === 0
            && $this->http->XPath->query('//text()[contains(.,"Concur Travel")]')->length === 0
            && stripos($parser->getHTMLBody(), 'travel@concursolutions.com') === false
            && stripos($parser->getHTMLBody(), 'www.concursolutions.com') === false
            && (stripos($parser->getHTMLBody(), 'This e-mail was sent from outside the company') === false
            && stripos($parser->getHTMLBody(), 'BCD Travel') === false)
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) + 3;
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function flight(Email $email): void
    {
        $xpath = "//img[contains(@src,'/ItineraryUI/Flight')]/ancestor::tr[1]/parent::*[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = '//img/ancestor::tr[1]/following-sibling::tr[' . $this->starts($this->t("Flight")) . ']/following-sibling::tr[ ./descendant::text()[' . $this->starts($this->t("Departure:")) . '] ]/parent::*[normalize-space(.)]';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = '//text()[(' . $this->eq($this->t("Flight")) . ')]/ancestor::tr[ (' . $this->starts($this->t("Flight")) . ') and (' . $this->contains($this->t("to")) . ')][1]/ancestor::*[normalize-space(.)][' . $this->contains($this->t("Departure:")) . '][1]';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $this->logger->debug("flight segments not found");

            return;
        }
        $this->logger->debug("[xpath-flight] " . $xpath);

        $f = $email->add()->flight();

        if (!empty($this->generalValue['travelers'])) {
            $f->general()->travellers($this->generalValue['travelers'], true);
        }

        if (!empty($this->generalValue['reservationDate'])) {
            $f->general()->date($this->generalValue['reservationDate']);
        }

        if (!empty($this->generalValue['ticketNumbers'])) {
            $tickets = array_filter(array_map(function ($s) {
                if (preg_match("#\b([\d\-]{5,})\b#", $s, $m)) {
                    return $m[1];
                } else {
                    return '';
                }
            }, $this->generalValue['ticketNumbers']));

            if (!empty($tickets)) {
                $f->issued()->tickets($tickets, false);
            }
        }

        $f->general()->noConfirmation();

        if (isset($this->generalValue['Cancelled'])) {
            $f->general()->cancelled();
            $f->general()->status('cancelled');
        }

        $date = 0;
        $accounts = [];

        foreach ($nodes as $root) {
            if ($acc = $this->http->FindSingleNode("descendant::div[{$this->starts($this->t('FF numbers'))}]", $root, true, "/{$this->opt($this->t('FF numbers'))}\s*:\s*(.+)/i")) {
                $accounts[] = $acc;
            }
            $s = $f->addSegment();
            $conf = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Confirmation:")) . ']', $root, true, '/:\s*([A-z\d]{5,})$/');

            if (!empty($conf) && !in_array($conf, array_column($email->obtainTravelAgency()->getConfirmationNumbers(), 0))) {
                $s->airline()->confirmation($conf);
            }

            $s->extra()->status($this->re("#(.+?) *(?:\/|$)#", $this->nextText($this->t("Status:"), $root)));

            $datestr = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]/parent::*[ name()='h1' or name()='h2' or name()='h3' or name()='span' or self::*[contains(@class,'segdetailsdate')] ]", $root, true, '/^([^:]*\d{1,2}[^:]*)$/');

            if (empty($datestr)) {
                $datestr = $this->http->FindSingleNode("ancestor::div[ preceding-sibling::div[normalize-space()] ][1]/preceding-sibling::div[ descendant::h3[normalize-space()] ][1]/descendant::h3", $root, true, '/^([^:]*\d{1,2}[^:]*)$/');
            }

            if (empty($datestr)) {
                $datestr = $this->http->FindSingleNode("(./ancestor::*[preceding-sibling::div]/preceding::div[.//*/@style[contains(normalize-space(), 'background:#0978C8')]])[last()]", $root, true, '/^([^:]*\d{1,2}[^:]*)$/');
            }

            if ($datestr) {
                if ($newDate = $this->normalizeDate($datestr)) {
                    $date = $newDate;

                    if (empty($this->emailDate)) {
                        $this->emailDate = $date;
                    }
                }
            }

            $flight = $this->http->FindSingleNode("./descendant::text()[({$this->eq($this->t("Flight"))}) or (({$this->starts($this->t("Flight"))}) and ({$this->contains($this->t("to"))}))]/ancestor::tr[1]/following::text()[string-length(normalize-space(.))>2][1]", $root);

            if (preg_match('/^\s*' . $this->preg_implode($this->railCompanyInFlight) . '\s+(\d+)$/', $flight, $m)) {
                $f->removeSegment($s);
                $this->railSegmentsFlight[] = $root;
            } elseif (preg_match('/(.+)\s+(\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name(str_replace('.', '', $m[1]))
                    ->number($m[2]);
            }

            $operator = $this->nextText($this->t("Operated by:"), $root);

            if (!empty($operator) && $operator != $s->getAirlineName()) {
                $s->airline()->operator($operator);
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./descendant::text()[({$this->eq($this->t("Flight"))}) or (({$this->starts($this->t("Flight"))}) and ({$this->contains($this->t("to"))}))]/ancestor::tr[1]", $root, true, "#" . $this->opt($this->t("Flight")) . "\s*.*?\s*\(([A-Z]{3})\)\s+" . $this->opt($this->t("to")) . "\s*.*?\s+\([A-Z]{3}\)#"))
                ->name($this->http->FindSingleNode("./descendant::text()[({$this->eq($this->t("Flight"))}) or (({$this->starts($this->t("Flight"))}) and ({$this->contains($this->t("to"))}))]/ancestor::tr[1]", $root, true, "#" . $this->opt($this->t("Flight")) . "\s*(.*?)\s*\([A-Z]{3}\)\s+" . $this->opt($this->t("to")) . "\s*.*?\s+\([A-Z]{3}\)#"));

            $timeDep = $this->normalizeTime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Departure:"))}]", $root, true, "/:\s*(.+)$/"));

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            $s->arrival()
                ->code($this->http->FindSingleNode("./descendant::text()[({$this->eq($this->t("Flight"))}) or (({$this->starts($this->t("Flight"))}) and ({$this->contains($this->t("to"))}))]/ancestor::tr[1]", $root, true, "#" . $this->opt($this->t("Flight")) . "\s*.*?\s*\([A-Z]{3}\)\s+" . $this->opt($this->t("to")) . "\s*.*?\s+\(([A-Z]{3})\)#"))
                ->name($this->http->FindSingleNode("./descendant::text()[({$this->eq($this->t("Flight"))}) or (({$this->starts($this->t("Flight"))}) and ({$this->contains($this->t("to"))}))]/ancestor::tr[1]", $root, true, "#" . $this->opt($this->t("Flight")) . "\s*.*?\s*\([A-Z]{3}\)\s+" . $this->opt($this->t("to")) . "\s*(.*?)\s+\([A-Z]{3}\)#"));

            $timeArr = $this->normalizeTime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Arrival:"))}]", $root, true, "/:\s*(.+)$/"));

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $s->extra()
                ->aircraft($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Aircraft:")) . "]", $root, true, "#:\s+(.+)$#"), true, true)
                ->miles($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Distance:")) . "]", $root, true, "#:\s+(.+)$#"), true, true)
                ->cabin($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Cabin:")) . "]", $root, true, "#:\s+(.*?)\s+\(\w\)$#"), false, true)
                ->bookingCode($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Cabin:")) . "]", $root, true, "#\(([A-Z]{1,2})\)$#"), false, true);

            $seat = $this->http->FindSingleNode("./descendant::div[" . $this->contains($this->t('Seat:')) . " and not(.//div)]", $root);

            if (preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seat, $m)) {
                $s->extra()->seats($m[1]);
            } else {
                //try get from Remarks:
                /*
                    Remarks
                    SEAT 12B
                 * */
                $seatsTitles = [];

                foreach ((array) $this->t('Seat:') as $stitle) {
                    $seatsTitles[] = trim(strtoupper($stitle), " :");
                }
                $seat = $this->http->FindSingleNode("./descendant::div[" . $this->contains($seatsTitles) . " and not(.//div)]", $root);

                if (preg_match_all("#\b(\d{1,3}[A-Z])\b#", $seat, $m)) {
                    $s->extra()->seats($m[1]);
                }
            }

            $terminal = $this->http->FindSingleNode('./descendant::div[(' . $this->starts($this->t('Terminal')) . ') and ./preceding::div[(' . $this->starts($this->t("Departure:")) . ') and position()<7] and ./following::div[(' . $this->starts($this->t("Arrival:")) . ') and position()<7] ]', $root, true, '/:\s*(.+)/');

            if ($terminal) {
                $s->departure()->terminal(trim(preg_replace(["/^{$this->opt($this->t('Terminal'))}\b/iu", "/\b{$this->opt($this->t('Terminal'))}$/iu"], ['', ''], $terminal)), true);
            }

            $terminal = $this->http->FindSingleNode('./descendant::div[' . $this->starts($this->t("Arrival:")) . ']/following::div[(' . $this->starts($this->t('Terminal')) . ') and position()<7]', $root, true, '/:\s*(.+)/');

            if ($terminal) {
                $s->arrival()->terminal(trim(preg_replace(["/^{$this->opt($this->t('Terminal'))}\b/iu", "/\b{$this->opt($this->t('Terminal'))}$/iu"], ['', ''], $terminal)), true);
            }

            $duration = trim($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Duration:")) . "]", $root, true, "#:\s+(.+)$#"));

            if ($duration) {
                $s->extra()->duration(trim(preg_replace("#[\s,]+#", ' ', $duration)));
            }

            $meal = trim($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Meal:")) . "]", $root, true, "#:\s+(.+)$#"));

            if (!empty($meal)) {
                $s->extra()->meal($meal);
            }

            $stops = $this->http->FindSingleNode("./descendant::text()[" . $this->contains($this->t("stop")) . "][1]", $root);

            if ($this->lang == "en") {
                if (preg_match('/Non[- ]*stop/i', $stops)) {
                    $s->extra()->stops(0);
                }
            } else {
                // WTF?
                $s->extra()->stops(0);
            }
        }

        if (count($f->getSegments()) === 0) {
            $email->removeItinerary($f);
        }

        if (0 < ($accounts = array_filter(array_unique($accounts)))) {
            $f->program()->accounts($accounts, false);
        }

        if ($nodes->length >= 1 && empty($this->railSegmentsFlight)) {
            $totalPayment = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Airfare total:")) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::td[normalize-space(.)][last()]");

            if ($totalPayment !== null) {
                $f->price()
                    ->total($this->amount($totalPayment))
                    ->currency($this->currency($totalPayment));
            }

            // BaseFare
            $baseFare = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Airfare quoted amount:")) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::td[normalize-space(.)][last()]");

            if ($baseFare === null && !empty($f->getTicketNumbers()[0][0])) {
                $baseFare = $this->http->FindSingleNode("//text()[" . $this->eq("Ticket Number: {$f->getTicketNumbers()[0][0]}:") . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::td[normalize-space(.)][last()]");
            }

            if ($baseFare !== null) {
                $f->price()
                    ->cost($this->amount($baseFare))
                    ->currency($this->currency($baseFare));
            }

            $tax = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Taxes and fees:")) . "]/ancestor::*[local-name()='td' or local-name()='th'][1]/following-sibling::td[normalize-space(.)][last()]");

            if ($tax !== null) {
                $f->price()->tax($this->amount($tax));
            }

            if ($totalPayment === null && !empty($baseFare) && !empty($tax)) {
                $f->price()->total($this->amount($baseFare) + $this->amount($tax));
            }
        }
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function hotel(Email $email): void
    {
        $xpath = "//img[contains(@src,'/ItineraryUI/Hotel')]/ancestor::tr[1]/parent::*[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        $chechIns = preg_replace('/[\s:]+$/', '', (array) $this->t("Checking In:"));
        $arrivals = preg_replace('/[\s:]+$/', '', (array) $this->t("Arrival:"));

        if ($nodes->length === 0 && empty(array_intersect($chechIns, $arrivals))) {
            $xpath = '//img[not(contains(@src, "Flight") or contains(@src, "Rail") or contains(@src, "Car"))]/ancestor::tr[1]/following-sibling::tr[ ./descendant::text()[' . $this->starts($this->t("Checking In:")) . '] ]/parent::*[normalize-space(.)]';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0 && !empty(array_intersect($chechIns, $arrivals))) {
            $xpath = '//img[not(contains(@src, "Flight") or contains(@src, "Rail") or contains(@src, "Car"))]/ancestor::tr[1]/following-sibling::tr[ ./descendant::text()[' . $this->starts($this->t("Checking In:")) . '] ]/parent::*[normalize-space(.)][' . $this->contains($this->t("Rooms")) . ']';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            if (empty(array_intersect($chechIns, $arrivals))) {
                $xpath = "//div[" . $this->eq($this->t("Reservations")) . " and following-sibling::*[normalize-space()][1][count(.//text()[normalize-space()]) = 1]]/following-sibling::*[ ./descendant::text()[" . $this->starts($this->t("Checking In:")) . "]]";
            } else {
                $xpath = "//div[" . $this->eq($this->t("Reservations")) . " and following-sibling::*[normalize-space()][1][count(.//text()[normalize-space()]) = 1]]/following-sibling::*[ ./descendant::text()[" . $this->starts($this->t("Checking In:")) . "]  and " . $this->contains($this->t("Rooms")) . "]";
            }
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length > 0) {
                $count = count($this->http->FindNodes("//text()[" . $this->starts($this->t("Checking In:")) . "]"));

                if ($count !== $nodes->length) {
                    $nodes = new \DOMNodeList();
                }
            }
        }
        /*if ($nodes->length === 0) {
            $xpath = "(//tr[ ./descendant::text()[{$this->starts($this->t("Checking In:"))}] and ./descendant::text()[{$this->starts($this->t("Confirmation:"))}] ])[not({$this->starts($this->t("Checking In:"))}) and ./descendant::*[normalize-space()][self::b]][last()]/parent::*[normalize-space(.)][not(//text()[{$this->eq($this->t('Flight'))}])]";
            $nodes = $this->http->XPath->query($xpath);
        }*/
        if ($nodes->length === 0) {
            $this->logger->debug("hotel segments not found");
        }
        $this->logger->debug("hotel xpath = " . $xpath);

        foreach ($nodes as $root) {
            $h = $email->add()->hotel();

            $confirmationNumbers = $this->http->FindNodes("(./descendant::text()[{$this->starts($this->t("Confirmation:"))}])",
                $root, '/:\s+([\w\/\-]{5,})(?:\s+|$)/');

            if (empty($confirmationNumbers)) {
                $confirmationNumbers = $this->http->FindNodes("./descendant::text()[{$this->starts($this->t("Confirmation:"))}]/ancestor::*[self::div or self::tr][1][count(./descendant::text()[normalize-space(.)!=''])=2]/descendant::text()[normalize-space(.)!=''][2]",
                    $root, '/([A-Za-z\d]{5,})(?:\s+|$)/');
            }

            if (!empty(array_filter($confirmationNumbers))) {
                foreach ($confirmationNumbers as $conf) {
                    $h->general()->confirmation($conf);
                }
            } else {
                /*Confirmation: N
                Status:Booked directly in AwardWallet /N*/

                $confirmationNumbers = $this->http->FindNodes("(./descendant::text()[{$this->starts($this->t("Confirmation:"))}])",
                    $root, '/:\s+([A-Z]{1})$/');

                if (!empty($confirmationNumbers)) {
                    $h->general()
                        ->noConfirmation();
                }
            }

            if (empty($confirmationNumbers) && empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Confirmation:")) . "])[1]", $root))) {
                $h->general()->noConfirmation();
            }

            $h->hotel()->name($this->http->FindSingleNode('./descendant::text()[string-length(normalize-space(.))>1][1]', $root));

            $address = implode(', ', $this->http->FindNodes('./descendant::text()[string-length(normalize-space(.))>1][2]/ancestor::tr[1]/descendant::text()[string-length(normalize-space(.))>1][position()<last()]', $root));
            $phone = $this->http->FindSingleNode("./descendant::text()[string-length(normalize-space(.))>1][2]/ancestor::tr[1]/descendant::text()[string-length(normalize-space(.))>4][last()]", $root);
            $phone = str_replace(['|'], '', preg_replace("/^[A-Z]{2}\|/", "", $phone));

            if (empty($address)) {//in one row bcd
                $address = $this->http->FindSingleNode('./descendant::text()[string-length(normalize-space(.))>1][2]/ancestor::tr[1]/descendant::text()[string-length(normalize-space(.))>1]', $root);

                if (preg_match("#\d#", $address) == 0 && stripos($address, 'CONFIRMED') !== false) {
                    //kostyl bcd
                    /*
                     ZZ
                     NONSMOKING CONFIRMED USER SUP
                     * */
                    $h->hotel()->noAddress();
                } else {
                    $h->hotel()->address($address);
                }
            } else {
                if (strlen(preg_replace("#[\d\-]+#", "", $phone)) > 5) {
                    $h->hotel()->address($address . $phone);
                } else {
                    $h->hotel()->address($address);
                    $h->hotel()->phone($phone);
                }
            }

            $datestr = $this->http->FindSingleNode("preceding::text()[normalize-space()][1]/parent::*[ name()='h1' or name()='h2' or name()='h3' or name()='span' or self::*[contains(@class,'segdetailsdate')] ]", $root, true, '/^([^:]*\d{1,2}[^:]*)$/');

            if (empty($datestr)) {
                $datestr = $this->http->FindSingleNode("ancestor::div[ preceding-sibling::div[normalize-space()] ][1]/preceding-sibling::div[ descendant::h3[normalize-space()] ][1]/descendant::h3", $root, true, '/^([^:]*\d{1,2}[^:]*)$/');
            }

            if (empty($datestr)) {
                $datestr = $this->http->FindSingleNode("(./ancestor::*[preceding-sibling::div]/preceding::div[.//*/@style[contains(normalize-space(), 'background:#0978C8')]])[last()]", $root, true, '/^([^:]*\d{1,2}[^:]*)$/');
            }

            $hDate = null;

            if (!empty($datestr)) {
                $hDate = $this->normalizeDate($datestr);

                if (!empty($hDate)) {
                    $hDate = strtotime("-1 day", $hDate);
                }
            }

            $checkInDate = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Checking In:")) . ']', $root, true, '/:\s+(.+)/');

            if (!empty($checkInDate) && $checkInDate = $this->normalizeDate($checkInDate, $hDate)) {
                $h->booked()->checkIn($checkInDate);
            }
            $timeCheckIn = $this->normalizeTime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Checking In:"))}]/following::text()[normalize-space()][1]", $root, true, '/^\d{1,2}:\d{2}$/'));

            if (!empty($timeCheckIn) && !empty($h->getCheckInDate())) {
                $h->booked()->checkIn(strtotime($timeCheckIn, $h->getCheckInDate()));
            }

            $checkOutDate = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Checking Out:")) . ']', $root, true, '/:\s+(.+)/');

            if (!empty($checkOutDate) && $checkOutDate = $this->normalizeDate($checkOutDate, $hDate)) {
                $h->booked()->checkOut($checkOutDate);
            }
            $timeCheckOut = $this->normalizeTime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Checking Out:"))}]/following::text()[normalize-space()][1]", $root, true, '/^\d{1,2}:\d{2}$/'));

            if (!empty($timeCheckOut) && !empty($h->getCheckOutDate())) {
                $h->booked()->checkOut(strtotime($timeCheckOut, $h->getCheckOutDate()));
            }

            $subInfo = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Checking In:")) . ']/following::text()[normalize-space(.)][1]/ancestor::td[1]', $root);

            if (!preg_match('/(' . $this->preg_implode($this->t("Rooms")) . '|' . $this->preg_implode($this->t("Guests")) . ')/u', $subInfo)) {
                $subInfo = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Checking In:")) . ']/following::text()[normalize-space(.)][1]', $root);
            }

            if (preg_match('/' . $this->preg_implode($this->t("Rooms")) . '[\s\—]+(\d{1,3})/iu', $subInfo, $matches)) {
                $h->booked()->rooms($matches[1]);
            }

            if (preg_match('/' . $this->preg_implode($this->t("Guests")) . '[\s\—]+(\d{1,3})/iu', $subInfo, $matches)) {
                $h->booked()->guests($matches[1]);
            }

            if (!empty($this->generalValue['travelers'])) {
                $h->general()->travellers($this->generalValue['travelers'], true);
            }

            $rate = null;
            unset($r);
            $rateText = implode(' ', $this->http->FindNodes('./descendant::text()[' . $this->eq($this->t("Daily Rate:")) . ']/ancestor::td[1]/descendant::text()[normalize-space(.)]', $root));

            if (preg_match('/:\s*(.+)/', $rateText, $m) && !empty(trim($m[1]))) {
                $rate = trim($m[1]);
            }

            if (empty($rate)) {
                $rate = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Daily Rate:")) . ']', $root, true, '/:\s*(.+)/');
            }

            if (!empty($rate)) {
                $r = $h->addRoom();
                $r->setRate($rate);
            } else {
                $ratesStr = $this->http->FindNodes("./descendant::text()[" . $this->eq($this->t('Rate:')) . "]/ancestor::td[1]//tr[not(.//tr)][normalize-space()]", $root);
                $dateFormat = null;

                if (preg_match("/^\s*[[:alpha:]]{2,5} \d{1,2} -/u", $ratesStr[0] ?? '')) {
                    $dateFormat = '[[:alpha:]]{2,5} \d{1,2}';
                } elseif (preg_match("/^\s*\d{1,2} [[:alpha:]]{2,5} -/u", $ratesStr[0] ?? '')) {
                    $dateFormat = '\d{1,2} [[:alpha:]]{2,5}';
                }

                if (!empty($dateFormat) && !empty($h->getCheckInDate())) {
                    $rates = [];

                    foreach ($ratesStr as $rateStr) {
                        if (preg_match("/^\s*(?<from>{$dateFormat})\s*-\s*(?<till>{$dateFormat})\s+(?<rate>.+)/u", $rateStr, $m)) {
                            $day = date_diff(
                                date_create('@' . EmailDateHelper::parseDateRelative($m['from'], strtotime("-1 day", $h->getCheckInDate()))),
                                date_create('@' . EmailDateHelper::parseDateRelative($m['till'], strtotime("-1 day", $h->getCheckInDate())))
                            )->format('%a');

                            if (!empty($day)) {
                                $rates = array_merge($rates, array_pad([], $day, $m['rate']));
                            } else {
                                $rates = [];

                                break;
                            }
                        }
                    }
                    $ratesCount = count($rates);

                    if ($ratesCount > 0) {
                        $realNights = empty($h->getCheckOutDate()) ? '0' :
                            date_diff(date_create('@' . $h->getCheckInDate()), date_create('@' . $h->getCheckOutDate()))->format('%a')
                        ;

                        if ($realNights == $ratesCount) {
                            $r = $h->addRoom();
                            $r->setRates($rates);
                        } else {
                            $rateAmounts = $rateCurrencies = [];

                            foreach ($rates as $rateItem) {
                                if (preg_match("/^(?<currency>[^\d)(]+?)?[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/", $rateItem, $matches)) {
                                    // $199.00 USD
                                    $rateAmounts[] = PriceHelper::parse($matches['amount'], $matches['currencyCode']);
                                    $rateCurrencies[] = $matches['currencyCode'];
                                } else {
                                    $rateAmounts = [];

                                    break;
                                }
                            }

                            if (count($rateAmounts) > 0 && count(array_unique($rateCurrencies)) === 1) {
                                $rateMin = min($rateAmounts);
                                $rateMax = max($rateAmounts);

                                if ($rateMin === $rateMax) {
                                    $rate = number_format($rateAmounts[0], 2, '.', '') . ' ' . $rateCurrencies[0] . ' / night';
                                } else {
                                    $rate = number_format($rateMin, 2, '.', '') . ' - ' . number_format($rateMax, 2, '.', '') . ' ' . $rateCurrencies[0] . ' / night';
                                }
                                $r = $h->addRoom();
                                $r->setRate($rate);
                            }
                        }
                    }
                }
            }
            $roomDescription = trim($this->nextText($this->t("Room Description:"), $root));

            if (!empty($roomDescription)) {
                if (isset($r)) {
                    $r->setDescription($roomDescription);
                } else {
                    $r = $h->addRoom();
                    $r->setDescription($roomDescription);
                }
            }

            $h->general()->cancellation($this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Cancellation Policy")) . "]/ancestor::tr[1]/following-sibling::tr[1]", $root), true, true);

            if (!empty($account = $this->http->FindSingleNode("descendant::div[{$this->starts($this->t('Frequent Guest Number'))}]",
                $root, false, "#{$this->opt($this->t('Frequent Guest Number'))}[\s:]+([A-Z\d]{5,})#"))
            ) {
                $h->program()->account($account, false);
            }

            // Total
            // Currency
            $payment = $this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t("Total Rate:")) . ' and not(.//td)]', $root);

            if (preg_match('/([,.\d]+).?\s*([A-Z]{3,})$/u', $payment, $matches)) {
                $h->price()
                    ->cost(PriceHelper::parse($matches[1], $matches[2]))
                    ->currency($matches[2]);
            }
            $status = $this->nextText($this->t("Status:"), $root);

            if (!empty($status)) {
                $h->general()->status($status);
            } else {
                $text = $this->http->FindSingleNode("./descendant::text()[" . $this->contains($this->t("Confirmation:")) . "]/ancestor::div[1]/following-sibling::div[1][normalize-space(.)][1]", $root);

                if (stripos($text, 'reservation') !== false || stripos($text, 'purchase') !== false) {
                    $h->general()->status($text);
                }
            }

            if (isset($this->generalValue['Cancelled']) || isset($this->generalValue['CancelledHotel'])) {
                $h->general()->cancelled();
                $h->general()->status('cancelled');
            }

            if (!empty($this->generalValue['reservationDate'])) {
                $h->general()->date($this->generalValue['reservationDate']);
            }
            $this->detectDeadLine($h);
        }

        if (isset($h) && $nodes->length == 1) {
            // collect total
            $payment = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Hotel:"))}])[last()]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][last()]");

            if ($payment === null) {
                $payment = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Hotel:"))}])[last()]/following::*[normalize-space(.)!=''][1][not({$this->contains($this->t('Agency Name:'))}) and not({$this->contains($this->t('View your plans in'))})]");
            }

            if ($payment !== null) {
                $currency = $this->currency($payment);

                if (!$h->getPrice() || $currency == $h->getPrice()->getCurrencyCode()) {
                    // if no cost or cost currency == total currency
                    $h->price()
                        ->total($this->amount($payment))
                        ->currency($currency);
                } elseif ($h->getPrice()) {
                    $emailTotal = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Total Estimated Cost:"))}])[last()]/following::*[normalize-space(.)!=''][1][not({$this->contains($this->t('Agency Name:'))}) and not({$this->contains($this->t('View your plans in'))})]");
                    $currencyTotal = $this->currency($emailTotal);

                    $payment1 = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Hotel:"))}])[last()]/ancestor::td[1]/following-sibling::td[normalize-space(.)!=''][1]");

                    if ($currencyTotal === $currency && $this->currency($payment1) !== $currencyTotal) {
                        // FE: it-2082827.eml
                        // if collected cost and cost currency !== total currency => delete cost and collect total in main currency
                        $h->removePrice();
                        $h->price()
                            ->total($this->amount($payment))
                            ->currency($this->currency($payment));
                    } else {
                        // FE: it-3211896.eml
                        // if collected cost and cost currency === total currency
                        $h->price()
                            ->total($this->amount($payment1))
                            ->currency($this->currency($payment1));
                    }
                }
            }
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Feescancel (?<hours>\d+) Hrs Prior To Arrival Date To Avoid One Night Charge/", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['hours'] . ' hours');
        }

        if (preg_match("/Must Cancel (?<day>\d+) Day\(S\) Prior To Arrival/ui", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['day'] . ' days');
        }

        if (preg_match("/The Amount Due Is Not Refundable Even If The Booking Is Cancelled Or Modified/ui", $cancellationText, $m)) {
            $h->booked()->nonRefundable();
        }

        if (preg_match("#^Cancel By (?<time>\d+\s*[ap]m) (?<date>\d+-\w+-\d+)#i", $cancellationText, $m)
            || preg_match("#Letztes Stornierungsdatum: (?<date>\d+-\w+-\d+) (?<time>\d+:\d+(\s*[ap]m)?)\.#i", $cancellationText, $m)
            || preg_match("#Cancel On (?<date>\d+\w+\d{4}) By (?<time>\d+\:\d+) Lt To Avoid A Ch Arge Of#i", $cancellationText, $m)
        ) {
            $h->booked()->deadline(strtotime($m['time'], $this->normalizeDate($m['date'])));

            return;
        }

        if (preg_match("#^Cxl After (?<h>\d{2})(?<m>\d{2}) (?<date>\d+[ ]*\w+) Forfeit One Nite Stay#i", $cancellationText, $m)
            || preg_match("#Please Cancel By (?<h>\d{2})(?<m>\d{2}) On (?<date>.{6,}?) Hotel Local Time To Avoid A Cancellation Penalty#i", $cancellationText, $m)
            || preg_match("#Cancel On (?<date>.{6,}?) By (?<h>\d{2}):(?<m>\d{2}) Lt To Avoid A Ch Arge Of 82.52Eur. #i", $cancellationText, $m)
        ) {
            $date = $this->normalizeDate($m['date']);

            if ($date) {
                $h->booked()->deadline(strtotime($m['h'] . ':' . $m['m'], $date));

                return;
            }
        }

        $h->booked()
            ->parseNonRefundable("Non-Refundable Cancellation");
    }

    private function rail(Email $email): void
    {
        // it-10753532.eml, it-13338065-fr.eml, it-2082827.eml, it-2082828.eml, it-3884982.eml

        $xpath = "//img[contains(@src,'/ItineraryUI/Rail')]/ancestor::tr[1]/parent::*[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = '//img/ancestor::tr[1]/following-sibling::tr[' . $this->starts($this->t("Train")) . ']/following-sibling::tr[ ./descendant::text()[' . $this->starts($this->t("Departure:")) . '] ]/parent::*[normalize-space(.)]';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = '//tr[' . $this->starts($this->t("Train")) . ']/following-sibling::tr[normalize-space(.)][position()<3][ ./descendant::text()[' . $this->starts($this->t("Departure:")) . '] ]/parent::*[normalize-space(.)]';
            $nodes = $this->http->XPath->query($xpath);
        }

        $segments = [];

        if ($nodes->length > 0) {
            $segments[] = $nodes;
        }

        if (!empty($this->railSegmentsFlight)) {
            $segments[] = $this->railSegmentsFlight;
        }

        if (empty($segments)) {
            $this->logger->debug("rail segments not found");

            return;
        }

        foreach ($segments as $segNum => $segmentsNodes) {
            $r = $email->add()->train();

            if (!empty($this->generalValue['travelers'])) {
                $r->general()->travellers($this->generalValue['travelers'], true);
            }

            if (!empty($this->generalValue['reservationDate'])) {
                $r->general()->date($this->generalValue['reservationDate']);
            }

            if (isset($this->generalValue['Cancelled'])) {
                $r->general()->cancelled();
                $r->general()->status('cancelled');
            }

            $date = 0;
            $noConfCount = 0;

            foreach ($segmentsNodes as $root) {
                $s = $r->addSegment();
                $conf = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Confirmation:")) . "]",
                    $root, true, '/:\s*([A-z\d\-]{5,})$/');

                if (!empty($conf) && !in_array($conf, array_column($r->getConfirmationNumbers(), 0))) {
                    $r->general()->confirmation($conf);
                }

                if (empty($conf) && empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Confirmation:")) . "])[1]",
                        $root))) {
                    $noConfCount++;
                }

                $status = $this->nextText($this->t("Status:"), $root);

                if (empty($status)) {
                    $status = $this->http->FindSingleNode("./descendant::text()[starts-with(normalize-space(), 'Confirmation:')]/following::text()[normalize-space()][1]", $root, true, "/^\s*(confirmed)\s*$/i");
                }
                $s->extra()->status($status);

                $datestr = $this->http->FindSingleNode("./preceding::text()[normalize-space(.)][1]/parent::*[name()='h1' or name()='h2' or name()='h3' or name()='span']",
                    $root);

                if (!empty($datestr) && $newDate = $this->normalizeDate($datestr)) {
                    $date = $newDate;
                }

                $headertext = $this->eq($this->t("Train")) . ' or ' . $this->eq($this->t("Flight")) . ' or ((' . $this->starts($this->t("Flight")) . ' or ' . $this->starts($this->t("Train")) . ') and ' . $this->contains($this->t("to")) . ')';
                $header = $this->http->FindSingleNode('./descendant::text()[' . $headertext . ']/ancestor::tr[1]',
                    $root);

                if (preg_match('/(?:' . $this->opt($this->t("Train")) . '|' . $this->opt($this->t("Flight")) . ')\s+(?<nameDep>.*?)(?:\s+\((?<codeDep>[A-Z]{3})\))?\s+' . $this->opt($this->t("to")) . '\s*(?<nameArr>.*?)(?:\s+\((?<codeArr>[A-Z]{3})\)|$)/u',
                    $header, $matches)) {
                    $matches['nameDep'] = preg_replace("/^{$this->opt($this->t("Connecting at"))}\s+(.{3,})$/i", '$1', $matches['nameDep']);
                    $s->departure()->name($matches['nameDep']);

                    $s->arrival()->name($matches['nameArr']);

                    if (!empty($matches['codeDep'])) {
                        $s->departure()->code($matches['codeDep']);
                    }

                    if (!empty($matches['codeArr'])) {
                        $s->arrival()->code($matches['codeArr']);
                    }

                    if (empty($matches['codeDep'])) {
                        $code = $this->http->FindSingleNode("//text()[normalize-space()='Reservations']/following::text()[contains(normalize-space(), 'layover at') and contains(normalize-space(), '" . $matches['nameDep'] . "')][1]", null, true, "/\(([A-Z]{3})\)/");

                        if (!empty($code) && $this->railStationIsValid($code)) {
                            $s->departure()->code($code);
                        }
                    }

                    if (empty($matches['codeArr'])) {
                        $code = $this->http->FindSingleNode("//text()[normalize-space()='Reservations']/following::text()[contains(normalize-space(), 'layover at') and contains(normalize-space(), '" . $matches['nameArr'] . "')][1]", null, true, "/\(([A-Z]{3})\)/");

                        if (!empty($code) && $this->railStationIsValid($code)) {
                            $s->arrival()->code($code);
                        }
                    }
                }

                $fn = $this->http->FindSingleNode("./descendant::text()[" . $headertext . "][1]/ancestor::tr[1]/following::text()[normalize-space(.)][1]",
                    $root, true, "#\s+(\d+)$#");

                if (!empty($fn)) {
                    $s->extra()->number($fn);
                } else {
                    $s->extra()->noNumber();
                }

                $timeDep = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Departure:")) . ']',
                    $root, true, '/:\s+(.+)$/');

                if ($date && $timeDep) {
                    if (preg_match('/(.{4,})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i', $timeDep, $matches)) {
                        $s->departure()->date(strtotime(str_replace(',', '', $matches[1]) . ', ' . $matches[2]));
                    } else {
                        $s->departure()->date(strtotime($timeDep, $date));
                    }
                }

                $timeArr = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Arrival:")) . ']',
                    $root, true, '/:\s+(.+)$/');

                if ($date && $timeArr) {
                    if (preg_match('/(.{4,})\s+(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i', $timeArr, $matches)) {
                        $s->arrival()->date(strtotime(str_replace(',', '', $matches[1]) . ', ' . $matches[2]));
                    } else {
                        $s->arrival()->date(strtotime($timeArr, $date));
                    }
                }

                $number = !empty($s->getNumber()) ? $s->getNumber() : 'nonumber';
                $this->logger->debug("./descendant::text()[" . $headertext . "][1]/ancestor::tr[1]/following::text()[normalize-space(.)][1]");
                $s->extra()->type($this->http->FindSingleNode("./descendant::text()[" . $headertext . "][1]/ancestor::tr[1]/following::text()[normalize-space(.)][1]",
                    $root, false, "/^(.+?)(?:\s*{$number}|$)/"));

                $class = $this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t("Class of Service:")) . ' and not(.//td)]',
                    $root, true, '/:\s*(.+)$/');

                if (preg_match('/^(.{4,})\(([A-Z]{1,2})\)$/', $class, $matches)) {
                    $s->extra()->cabin($matches[1]);
                    $s->extra()->bookingCode($matches[2]);
                } elseif (preg_match('/^([A-Z]{1,2})$/', $class, $matches)) {
                    $s->extra()->bookingCode($matches[1]);
                } elseif ($class) {
                    $s->extra()->cabin($class);
                }

                if (empty($s->getCabin())) {
                    $cabin = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Class:")) . ']',
                        $root, true, '/:\s*(.+)$/');

                    if ($cabin) {
                        $s->extra()->cabin($cabin);
                    }

                    if (preg_match('/^(.{4,})\(([A-Z]{1,2})\)$/', $cabin, $matches)) {
                        $s->extra()->cabin($matches[1]);
                        $s->extra()->bookingCode($matches[2]);
                    } elseif (preg_match('/^([A-Z]{1,2})$/', $class, $matches)) {
                        $s->extra()->bookingCode($matches[1]);
                    } elseif ($class) {
                        $s->extra()->cabin($class);
                    }
                }

                $coach = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Seat:"))}]", $root, true,
                    "#{$this->opt($this->t("Coach"))}\s*(.+?)(?:,|{$this->opt($this->t('Seat'))})#u");

                if (empty($coach)) {
                    $coach = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Seat:"))}]/ancestor::div[1][{$this->starts($this->t("Seat:"))}]", $root, true,
                        "#{$this->opt($this->t("Coach"))}\s*(.+?)(?:,|{$this->opt($this->t('Seat'))})#u");
                }
                $s->extra()->car($coach !== null ? str_replace('#', '', $coach) : null, false, true);

                $seat = $this->nextText($this->t("Seat:"), $root);

                if (!$seat) {
                    $seat = $this->http->FindSingleNode(".//text()[{$this->starts($this->t("Seat:"))}]", $root, true,
                        "#{$this->opt($this->t("Seat"))}\:?\s*([A-Z]\d{1,3})#u");
                }

                if (!$seat) {
                    $seat = $this->http->FindSingleNode('./descendant::div[' . $this->starts($this->t("Seat:")) . ' and not(.//div)]',
                        $root, true, '/:\s*(.+)/');
                }

                if (!$seat) {
                    $seat = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Seat:")) . ']',
                        $root, true, "/{$this->opt($this->t('Seat'))}[:, ]+(.+?)/");
                }

                if ($seat && !preg_match("/{$this->opt($this->t('No seat assignment'))}/", $seat)) {
                    $s->extra()->seat(preg_replace("/^.*{$this->opt($this->t('Seat'))}[:, ]+(\w+).*$/", '$1', $seat));
                }

                $duration = $this->nextText($this->t("Duration:"), $root);

                if (!$duration) {
                    $duration = $this->http->FindSingleNode('./descendant::div[' . $this->starts($this->t("Duration:")) . ' and not(.//div)]',
                        $root, true, '/:\s*(.+)/');
                }

                if (!empty($duration)) {
                    $s->extra()->duration($duration);
                }

                $s->extra()->meal($this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t("Meal:")) . ' and not(.//td)]',
                    $root, true, '/:\s*(.+)$/'), true, true);
                $s->extra()->stops($this->nextText($this->t("Number of Stops:"), $root), true, true);

                // for google, to help find correct address of stations
                $region = null;
                $regionByCompany = [
                    'United Kingdom' => [
                        'EAST MIDLANDS RAILWAY',
                        'GREAT WESTERN RAILWAY',
                        'WEST MIDLANDS TRAINS',
                        'London Underground',
                        'AVANTI WEST COAST',
                        'GREATER ANGLIA',
                        'GREAT NORTHERN',
                        'NORTHERN',
                        'South Western Railway',
                        'Crosscountry',
                        'SOUTHERN',
                        'THAMESLINK',
                        'Transpennine Express',
                        'LONDON NORTH EASTERN RAILWAY',
                        'TFL RAIL',
                    ],
                    'Europe' => [
                    ],
                    'USA' => [
                        'Amtrak direct',
                    ],
                ];

                foreach ($regionByCompany as $reg => $companies) {
                    foreach ($companies as $company) {
                        if (!empty($s->getTrainType()) && strcasecmp($s->getTrainType(), $company) === 0) {
                            $region = $reg;

                            break 2;
                        }
                    }
                }

                if (empty($region)) {
                    foreach ($regionByCompany as $reg => $companies) {
                        if (!empty($s->getStatus()) && !empty($companies) && preg_match("/^\s*\w+ \w+ {$this->opt($companies)}/", $s->getStatus())) {
                            $region = $reg;

                            break;
                        }
                    }
                }

                if ($region && $s->getDepName()) {
                    $s->departure()
                        ->geoTip($region)
                        ->name($s->getDepName() . ', ' . $region);
                }

                if ($region && $s->getArrName()) {
                    $s->arrival()
                        ->geoTip($region)
                        ->name($s->getArrName() . ', ' . $region);
                }

                // kostyl for bcd
                if ($s->getDepDate() === $s->getArrDate() && $s->getTrainType() === 'London Underground') {
                    $r->removeSegment($s);
                }
            }

            if (!empty($noConfCount) && empty($r->getConfirmationNumbers())
                && ((is_array($segmentsNodes) && $noConfCount === count($segmentsNodes)) || $noConfCount === $segmentsNodes->length)) {
                $r->general()->noConfirmation();
            }

            if ($segNum === 0 && $nodes->length > 0) {
                $totalPayment = $this->http->FindSingleNode('//text()[' . $this->eq($this->t("Train Base Fare:")) . ']/following::td[normalize-space(.)][1]');

                if ($totalPayment !== null) {
                    $r->price()
                        ->cost($this->amount($totalPayment))
                        ->currency($this->currency($totalPayment));
                }
                $totalPayment = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Train Total Price:"))}]/following::td[normalize-space(.)!=''][1][contains(translate(.,'0123456789','dddddddddd'),'dd')]");

                if ($totalPayment !== null) {
                    $r->price()
                        ->total($this->amount($totalPayment))
                        ->currency($this->currency($totalPayment));
                }
            }
        }
    }

    private function railStationIsValid(?string $code): bool
    {
        $conflictedCodes = [
            'SHF', // United Kingdom, Sheffield (SHF) != India, Shirud (SHF)
            'STP', // United Kingdom, London St Pancras Intl (STP) != India, Sitapur (STP)
        ];

        return !in_array($code, $conflictedCodes);
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function car(Email $email): void
    {
        $xpath = "//img[contains(@src,'/ItineraryUI/Car')]/ancestor::tr[1]/parent::*[normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = '//img/ancestor::tr[1]/following-sibling::tr[ ./descendant::text()[' . $this->starts($this->t("Pick Up:")) . '] and ./descendant::text()[' . $this->starts($this->t("Rental Details")) . '] ]/parent::*[normalize-space(.)]';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $xpath = '(//tr[ ./descendant::text()[' . $this->starts($this->t("Pick Up:")) . '] and ./descendant::text()[' . $this->starts($this->t("Rental Details")) . '] ])[last()]/parent::*[normalize-space(.)]';
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length === 0) {
            $this->logger->debug("car segments not found");
        }

        foreach ($nodes as $root) {
            $r = $email->add()->rental();

            $conf = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Confirmation:")) . "]", $root, true, '/:\s+([-A-z\d]+)(?:\s+[A-Z]{4}|$)/');

            if (empty($conf) && empty($this->http->FindSingleNode("(./descendant::text()[" . $this->contains($this->t("Confirmation:")) . "])[1]", $root))) {
                $r->general()->noConfirmation();
            } else {
                $r->general()->confirmation($conf);
            }

            $pickupTime = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Pick Up:")) . "]", $root, true, '/' . $this->opt($this->t("Pick Up:")) . '\s*(.+)/');

            if (strpos($pickupTime, ":") === false) {
                $pickupDate = $pickupTime;
                $pickupTime = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Pick Up:")) . "]/following::text()[normalize-space(.)][1]",
                    $root);
            } else {
                $pickupDate = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Pick Up:")) . "]/following::text()[normalize-space(.)][1][not(" . $this->starts($this->t("Pick-up at:")) . ")]",
                    $root);

                if (empty($pickupDate) && preg_match("#^\s*(?<time>\d{1,2}:\d{1,2}(\s*[ap]m)?)\s+(?<date>.*\b\d{1,2}\b.*\s*)#i", $pickupTime, $m)) {
                    $pickupDate = $m['date'];
                    $pickupTime = $m['time'];
                }
            }

            $pickupTime = $this->normalizeTime($pickupTime);

            if (!empty($pickupTime) && !empty($pickupDate) && $pickupDate = $this->normalizeDate($pickupDate)) {
                //$r->pickup()->date($this->correctDate(strtotime($pickupTime, $pickupDate))); //Please, save example for function correctDate
                $r->pickup()->date(strtotime($pickupTime, $pickupDate));
            }

            $pickupLocation = $this->nextText($this->t("Pick-up at:"), $root);

            if (empty($pickupLocation)) {
                $pickupLocation = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Pick-up at:")) . '][1]', $root, true, '/:\s*(.+)/');
            }

            if (!empty($pickupLocation)) {
                $r->pickup()->location($pickupLocation);
            }

            if ($pickupPhone = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Pick-up at:")) . '][1]/following::text()[normalize-space()][1][' . $this->starts($this->t("Phone:")) . ']', $root, true, "/:\s*({$this->patterns['phone']})$/")) {
                $r->pickup()->phone($pickupPhone);
            }

            $dropoffTime = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Return:")) . "]",
                $root, true, '/:\s+(.+)/');

            if (strpos($dropoffTime, ":") === false) {
                $dropoffDate = $dropoffTime;
                $dropoffTime = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Return:")) . "]/following::text()[normalize-space(.)][1]",
                    $root);
            } else {
                $dropoffDate = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Return:")) . "]/following::text()[normalize-space(.)][1][not(" . $this->starts($this->t("Returning to:")) . ")]",
                    $root);

                if (empty($dropoffDate) && preg_match("#^\s*(?<time>\d{1,2}:\d{1,2}(\s*[ap]m)?)\s+(?<date>.*\b\d{1,2}\b.*\s*)#i", $dropoffTime, $m)) {
                    $dropoffDate = $m['date'];
                    $dropoffTime = $m['time'];
                }
            }

            $dropoffTime = $this->normalizeTime($dropoffTime);

            if (!empty($dropoffTime) && !empty($dropoffDate) && $dropoffDate = $this->normalizeDate($dropoffDate)) {
                $r->dropoff()->date($this->correctDate(strtotime($dropoffTime, $dropoffDate)));
            } else {
                $duration = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Duration:")) . "]",
                    $root, true, '/:\s+(.+)/');

                if (!empty($r->getDropOffDateTime()) && preg_match("#^(\d+)\s+hours?$#", $duration, $m)) {
                    $r->dropoff()->date(strtotime(sprintf('+%d hours', $m[1]), $r->getDropOffDateTime()));
                }
            }

            $dropoffLocation = $this->nextText($this->t("Returning to:"), $root);

            if (empty($dropoffLocation)) {
                $dropoffLocation = $this->http->FindSingleNode('./descendant::text()[' . $this->starts($this->t("Returning to:")) . '][1]', $root, true, '/:\s*(.+)/');
            }

            if (!empty($dropoffLocation)) {
                $r->dropoff()->location($dropoffLocation);
            }

            if (!empty($r->getPickUpPhone()) && $r->getPickUpLocation() == $r->getDropOffLocation()) {
                $r->dropoff()->phone($r->getPickUpPhone());
            }

            $rentalCompany = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t(' at: '))}][1]", $root, true, "/^([^:]{2,}?)\s*{$this->opt($this->t(' at: '))}/");

            if (!empty($rentalCompany)) {
                foreach ($this->detectCarsCode as $code => $values) {
                    foreach ($values as $name) {
                        if (mb_stripos($rentalCompany, $name) === 0) {
                            $r->program()->code($code);
                            $r->program()->keyword($rentalCompany);
                        }
                    }
                }
            }

            if ($rentalCompany) {
                $r->extra()->company($rentalCompany);
            }

            if (!empty($account = $this->http->FindSingleNode("descendant::div[{$this->starts($this->t('Frequent Guest Number'))}]",
                $root, false, "#{$this->opt($this->t('Frequent Guest Number'))}[\s:]+([A-Z\d]{5,})#"))
            ) {
                $r->program()->account($account, false);
            }
            $r->car()->type($this->nextText($this->t("Rental Details"), $root));

            if (!empty($this->generalValue['travelers'])) {
                $r->general()->travellers($this->generalValue['travelers'], true);
            }

            if (!empty($this->generalValue['reservationDate'])) {
                $r->general()->date($this->generalValue['reservationDate']);
            }

            $payment = $this->http->FindSingleNode('./descendant::td[' . $this->starts($this->t("Total Rate:")) . ' and not(.//td)]', $root);

            if (preg_match('/([,.\d]+).?\s*([A-Z]{3,})$/u', $payment, $matches)) {
                $r->price()
                    ->cost(PriceHelper::parse($matches[1], $matches[2]))
                    ->currency($matches[2]);
            }

            if ($s = $this->nextText($this->t("Status:"), $root)) {
                $r->general()->status($s);
            }

            if (isset($this->generalValue['Cancelled'])) {
                $r->general()->cancelled();
                $r->general()->status('cancelled');
            }
        }
    }

    /**
     * @throws \AwardWallet\Schema\Parser\Component\InvalidDataException
     */
    private function transfer(Email $email): void
    {
        $xpath = '//img/ancestor::tr[1]/parent::*[ ' . $this->starts('Ground Reservation') . ' ]';
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[xpath-transfer] " . $xpath);

        if ($nodes->length === 0) {
            $this->logger->debug("transfer segments not found");

            return;
        }

        foreach ($nodes as $root) {
            $t = $email->add()->transfer();

            if (isset($this->generalValue['Cancelled'])) {
                $t->general()->cancelled();
                $t->general()->status('cancelled');
            }

            $s = $t->addSegment();

            $conf = str_replace('*', '-',
                $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Confirmation:")) . "]",
                    $root, true, '/:\s+([-A-z\d\*]+)(?:\s+[A-Z]{4}|$)/')
            );

            if (!in_array($conf, array_column($t->getConfirmationNumbers(), 0))) {
                $t->general()->confirmation($conf);
            }
            $phone = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Renting from:"))}]/following::text()[normalize-space()!=''][1]",
                $root);

            if (preg_match("/(.+?)[ ]+({$this->patterns['phone']})/", $phone, $m)) {
                $t->program()->phone($m[2], $m[1]);
            }

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Pick Up:"))}]/../following-sibling::*[1]",
                $root, true, '/:\s*(.+)/');

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Pick Up:"))}]/ancestor::*[self::div][1]/following-sibling::*[1]",
                    $root, false, "/^(?:{$this->opt($this->t('Pick-up at'))}:)?\s*(.+)/i");
            }
            $s->departure()->name($node);

            $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Pick Up:"))}]", $root,
                true, '/:\s*(.+)/');

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Pick Up:"))}]/ancestor::*[self::div][1]",
                    $root, true, '/:\s*(.+)/');
            }
            $s->departure()->date($this->normalizeDate($node));
            $time = $this->normalizeTime($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t("Pick Up:"))}]/following::text()[normalize-space()][1]", $root));

            if (!empty($s->getDepDate()) && !empty($time)) {
                $s->departure()->date(strtotime($time, $s->getDepDate()));
            }

            $node = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Returning to:")) . "]", $root, true, '/:\s*(.+)/');

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Returning to:"))}]/ancestor::*[self::div][1]",
                    $root, true, '/:\s*(.+)/');
            }
            $s->arrival()->name($node);

            $duration = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t("Duration:")) . "]",
                $root, true, '/:\s+(.+)/');

            if ($s->getDepDate() && preg_match("#^(\d+)\s+hours?$#", $duration, $m)) {
                $s->arrival()->date(strtotime(sprintf('+%d hours', $m[1]), $s->getDepDate()));
            } else {
                $s->arrival()->noDate();
            }

            if (!empty($this->generalValue['travelers'])) {
                $t->general()->travellers($this->generalValue['travelers'], true);
            }

            if (!empty($this->generalValue['reservationDate'])) {
                $t->general()->date($this->generalValue['reservationDate']);
            }

            // TotalCharge
            // Currency
            $payment = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Confirmation:"))}]/../following-sibling::*[2]", $root, true, "/^.*\d.*$/");

            if ($payment === null) {
                $payment = $this->http->FindSingleNode("./descendant::text()[{$this->starts($this->t("Confirmation:"))}]/ancestor::*[self::div][1]/following-sibling::*[2]", $root, true, "/^.*\d.*$/");
            }

            if ($payment !== null) {
                $t->price()
                    ->cost($this->amount($payment))
                    ->currency($this->currency($payment));
            }

            $s->extra()->status($this->nextText($this->t("Status:"), $root), true, true);
        }
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    /**
     * @return int|bool|null
     */
    private function normalizeDate(?string $string, $relativeDate = null)
    {
        if (!empty($relativeDate)) {
            $year = date("Y", $relativeDate);
        } else {
            $year = date("Y", $this->emailDate);
        }

        $in = [
            // Wt. 3 Wrz
            '/^(?<wday>\w+)\. (\d+) (\w+)$/i',
            //Mi 16 Mai    |    Mar. 28 Ago.
            '/^(?<wday>[^\s\d]+?)\.? (\d{1,2}) ([^\s\d]+?)\.?$/',
            //Mer Jui 20    |    Dom. Sep. 9
            '/^(?<wday>[^,\s\d]+?)\.? ([^\s\d]+?)\.? (\d{1,2})$/',
            // June 04, 2017
            '/^([^,.\d\s]{3,})\s+(\d{1,2}),\s+(\d{4})$/',
            // 19 Septembre, 2016
            '/^(\d{1,2})\s+([^,.\d\s]{3,}),\s+(\d{4})$/u',
            // 2014, April 3, Thursday    |    2020 Janvier 27, Lundi
            '/^(\d{4}),?\s+([[:alpha:]]{3,})\s+(\d{1,2})(?:,?[ ]*(?<wday>[[:alpha:]]+))?$/u',
            // 24 Ago.    |    18Dec
            '/^(\d{1,2})\s*([^,.\d\s]{3,})\.?$/',
            // Oct 18
            '/^([^,.\d\s]{3,})\.?\s+(\d{1,2})$/',
            //Monday, January 13, 2014
            '/^(?<wday>[\w-]+),? (\w+) (\d+), (\d{4})$/u',
            // Monday, 02 December 2019    |    Saturday, 22 September, 2018
            '/^(?<wday>[\w-]+), (\d+) (\w+),? (\d{4})$/u',
            //2019年1月04日; 2022年9月01日木曜日
            '/^\s*(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\s*[[:alpha:]]{0,3}\s*$/u',
            //(土) 1月 26
            '/^\s*\((\w+)\)\s*(\d{1,2})\s*月\s*(\d{1,2})\s*$/u',
            // 01-Apr-19    |    09Dec19
            '/^(\d{1,2})[- ]*([[:alpha:]]+)[- ]*(\d{2})$/u',
            // 11-12-19
            '/^(\d{1,2})-(\d{1,2})-(\d{2,4})$/',
        ];
        // 18/03/2020
        $in[] = '/^(\d{1,2})\s*\/\s*(\d{1,2})\s*\/\s*(\d{4})$/';

        $out = [
            '$2 $3 ' . $year,
            '$2 $3 ' . $year,
            '$3 $2 ' . $year,
            '$2 $1 $3',
            '$1 $2 $3',
            '$3 $2 $1',
            '$1 $2 ' . $year,
            '$2 $1 ' . $year,
            '$3 $2 $4',
            '$2 $3 $4',
            '$3.$2.$1',
            '$3.$2.' . $year,
            '$1 $2 20$3',
            '$2/$1/$3',
        ];
        $out[] = $this->enDatesInverted ? '$2/$1/$3' : '$1/$2/$3';

        foreach ($in as $i => $pattern) {
            if (preg_match($pattern, $string, $m) && !empty($m['wday'])) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['wday'], $this->lang));
                $str = $this->dateStringToEnglish(preg_replace($pattern, $out[$i], $string));
                $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);

                return $str;
            }
        }

        return strtotime($this->dateStringToEnglish(preg_replace($in, $out, $string)));
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace([
            '/^\s*([AaPp](?:\.\s*)?[Mm]\.?)\s*(\d{1,2}(?:[:]+\d{2})?)\s*$/', // PM 5:30    ->    5:30 PM
        ], [
            '$2 $1',
        ], $s);

        return $s;
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

    private function correctDate($date)
    {
        if ($date < $this->emailDate - 60 * 60 * 24) { // -1 day
            return strtotime("+1 year", $date);
        }

        return $date;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(',', '.', preg_replace("#[.,](\d{3})#", "$1", $this->re("#([,.\d]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^,.\d]+)#", $s);

        foreach ($sym as $f => $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_replace('/([.$*)|(\/])/', '\\\\$1', $s); }, $field)) . ')';
    }

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($s) { return preg_quote($s); }, $field)) . ')';
    }
}
