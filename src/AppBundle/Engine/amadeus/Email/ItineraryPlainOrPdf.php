<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\TrainSegment;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers wagonlit/It1990301, priceline/It1726368, amextravel/PlatinumTravel, skywards/It4240081, airfrance/ItPlain, goibibo/Confirmation, hoggrob/It3, klm/It7, opodo/PlainText, qmiles/BookingText, tapportugal/PlainText (in favor of amadeus/ItineraryPlainOrPdf)

class ItineraryPlainOrPdf extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-11736388.eml, amadeus/it-11773180.eml, amadeus/it-11817273.eml, amadeus/it-12.eml, amadeus/it-12639921.eml, amadeus/it-12639923.eml, amadeus/it-12639927.eml, amadeus/it-16174262.eml, amadeus/it-1647020.eml, amadeus/it-1927449.eml, amadeus/it-1990301.eml, amadeus/it-2001.eml, amadeus/it-2003834.eml, amadeus/it-2003835.eml, amadeus/it-2049395.eml, amadeus/it-2310938.eml, amadeus/it-2360099.eml, amadeus/it-2360100.eml, amadeus/it-2391466.eml, amadeus/it-2402939.eml, amadeus/it-2402942.eml, amadeus/it-2411239.eml, amadeus/it-2411240.eml, amadeus/it-2411241.eml, amadeus/it-2411243.eml, amadeus/it-2411244.eml, amadeus/it-2411245.eml, amadeus/it-2411249.eml, amadeus/it-2411250.eml, amadeus/it-2411251.eml, amadeus/it-2411252.eml, amadeus/it-2415843.eml, amadeus/it-2415846.eml, amadeus/it-2416560.eml, amadeus/it-2438072.eml, amadeus/it-2491156.eml, amadeus/it-2514089.eml, amadeus/it-2514168.eml, amadeus/it-28.eml, amadeus/it-29735055.eml, amadeus/it-3.eml, amadeus/it-30.eml, amadeus/it-3001.eml, amadeus/it-3092233.eml, amadeus/it-3098589.eml, amadeus/it-31.eml, amadeus/it-32.eml, amadeus/it-33.eml, amadeus/it-3686395.eml, amadeus/it-385577803.eml, amadeus/it-4001.eml, amadeus/it-4240081.eml, amadeus/it-4366729.eml, amadeus/it-4432878.eml, amadeus/it-4465325.eml, amadeus/it-4485096.eml, amadeus/it-4485236.eml, amadeus/it-4494700.eml, amadeus/it-5095701.eml, amadeus/it-5212363.eml, amadeus/it-5454892.eml, amadeus/it-5454903.eml, amadeus/it-59304730.eml, amadeus/it-6105752.eml, amadeus/it-6695372.eml, amadeus/it-6793410.eml, amadeus/it-68406993.eml, amadeus/it-7001.eml, amadeus/it-701168200.eml, amadeus/it-7201118.eml, amadeus/it-8279401.eml, amadeus/it-8792318.eml"; // +1 bcdtravel(plain)[nl]

    public $reFrom = ["amex", "@tap.", "amadeus.", "EVA Air", "@nexuselite.in", "carlsonwagonlit", "@HRGWORLDWIDE.COM", "@aexp.com", "@emirates.com", "MAIL.CTO.DKR@AIRFRANCE.FR", "TICKET@TICKET.SE", "goibibo.com", "@KLM.COM"];

    // NB: count($reBody) === countTypes
    public static $reBody = [
        'en'  => ['FLIGHT', 'ARRIVAL'],
        'en2' => ['HOTEL BOOKING REF', 'CHECK-IN'],
        'en3' => ['CAR RENTAL', 'PICK UP'],
        'de'  => ['FLUG', 'ANKUNFT'],
        'de2' => ['BAHN', 'ANKUNFT'],
        'de3' => ['HOTEL BUCHUNGSREF', 'CHECK-IN'],
        'fr'  => ['VOL', 'ARRIVEE'],
        'fr2' => ['TRAIN', 'ARRIVEE'],
        'it'  => ['VOLO', 'ARRIVO'],
        'pl'  => ['REJS', 'PRZYLOT'],
        'es'  => ['VUELO', 'LLEGADA'],
        'es2' => ['RESERVA COCHE', 'RECOGIDA'],
        'es3' => ['FECHA', 'SALIDA'],
        'pt'  => ['VOO', 'CHEGADA'],
        'nl'  => ['VLUCHT', 'AANKOMST'],
        'no'  => ['FLYREISE', 'AVGANG'],
        'ca'  => ['VOL', 'ARRIBADA'],
        'vi'  => ['KHỞI HÀNH', 'ĐẾN'],
        'zh'  => ['啟程:', '抵達:'],
    ];
    public $reSubject = [
        '#[A-Z\s]/[A-Z\s]+\s+\d+[A-Z]+\d+ [A-Z]{3} [A-Z]{3}#',
        '# HOTEL RESERVATION#',
        '#.+?\d+\w+\d+ [A-Z]{3} [A-Z]{3}#u', // eva
        '#Your Ticket\([A-Z\d]+\) confirmation#', // nexus
        '#.+?\d+\w+\d+ [A-Z]{3} [A-Z]{3}#u',
    ];
    private $lang = '';
    private $travellers = [];
    private $pdfNamePattern = ".*pdf";
    private static $dict = [
        'en' => [
            'BOOKING REF'        => ['BOOKING REF', 'CODE OF RES .'],
            'CONFIRMED'          => ['CONFIRMED', 'RESERVATION CONFIRMED', 'CONFIRMED RESERVATION'],
            'DEPARTURE'          => ['DEPARTURE', 'OUTPUT'],
            'FREQUENT TRAVELLER' => ['FREQUENT TRAVELLER', 'FREQUENT TRAVELER'],
            'FLIGHT BOOKING REF' => ['FLIGHT BOOKING REF', 'AIRLINE LOCATOR'],
            //            'AIRCRAFT OWNER' => ['AIRCRAFT OWNER','OWNER OF PLANE'],
            'DURATION' => ['DURATION', 'TIME'],
            'MEAL'     => ['MEAL', 'FOOD'],
            'NON STOP' => ['NON STOP', 'NO STOPS'],
            //            'PICK UP' => ['PICK UP','PICK-UP','PICKUP'],
            //            'DROP OFF' => ['DROP OFF','DROP-OFF','DROPOFF']
            //            'TRAIN'=>'',
            //flight
            //'TOTAL COST FOR TICKETS:',
        ],
        'vi' => [
            'BOOKING REF' => 'MÃ ĐẶT CHỖ',
            'FLIGHT'      => 'CHUYẾN BAY',
            'CONFIRMED'   => ['ĐẶT CHỖ ĐÃ ĐƯỢC XÁC NHẬN'],
            'DEPARTURE'   => ['KHỞI HÀNH'],
            'ARRIVAL'     => 'ĐẾN',
            //            'TERMINAL' => '',
            //'FREQUENT TRAVELLER' => '',
            'FLIGHT BOOKING REF' => 'MÃ ĐẶT CHỖ CHUYẾN BAY',
            'OPERATED BY'        => 'KHAI THÁC BỞI',
            //            'AIRCRAFT OWNER'=>'',
            'DATE'                    => 'NGÀY',
            'EQUIPMENT'               => 'LOẠI MÁY BAY',
            'DURATION'                => 'ĐỘ DÀI THỜI GIAN',
            'MEAL'                    => 'SUẤT ĂN',
            //'SEAT'                    => '',
            'TICKET'                  => 'VÉ',
            'FOR'                     => 'CHO',
            //'NON STOP'                => '',
            //'TOTAL COST FOR TICKETS:' => '',
            //Train
            //'TRAIN'          => '',
            //'E-TICKETNUMBER' => '',
            //Hotel
            //'LOCATION'            => [''],
            //'HOTEL BOOKING REF'   => [''],
            //'TELEPHONE'           => '',
            //'CANCELLATION POLICY' => '',
            //'TAXES'               => '',
            //'ROOM TYPE'           => '',
            //'TOTAL RATE'          => '',
            //'RATE'                => [''],
            //'FOR'                 => '',
        ],
        'de' => [
            'BOOKING REF' => 'BUCHUNGSNR.',
            'FLIGHT'      => 'FLUG',
            'CONFIRMED'   => ['BESTAETIGT', 'BUCHUNG BESTAETIGT'],
            'DEPARTURE'   => ['ABFLUG', 'ABREISE'],
            'ARRIVAL'     => 'ANKUNFT',
            //            'TERMINAL' => '',
            'FREQUENT TRAVELLER' => 'VIELREISENDER',
            'FLIGHT BOOKING REF' => 'FLUG-BUCHUNGSREF.',
            'OPERATED BY'        => 'DURCHGEFUEHRT VON',
            //            'AIRCRAFT OWNER'=>'',
            'DATE'                    => 'DATUM',
            'EQUIPMENT'               => 'FLUGZEUG',
            'DURATION'                => 'DAUER',
            'MEAL'                    => 'MAHLZEIT',
            'SEAT'                    => 'SITZ',
            'TICKET'                  => 'TICKET',
            'NON STOP'                => 'NON-STOP',
            'TOTAL COST FOR TICKETS:' => 'GESAMTSUMME DER TICKETS:',
            //Train
            'TRAIN'          => 'BAHN',
            'E-TICKETNUMBER' => 'E-TICKETNUMMER',
            //Hotel
            'LOCATION'            => ['LOCATION', 'ADRESSE'],
            'HOTEL BOOKING REF'   => ['HOTEL BUCHUNGSREF', 'HOTEL BUCHUNGSREF.'],
            'TELEPHONE'           => 'TELEFON',
            'CANCELLATION POLICY' => 'STORNIERUNGSRICHTLINIEN',
            'TAXES'               => 'STEUERN',
            'ROOM TYPE'           => 'ZIMMERART',
            'TOTAL RATE'          => 'GESAMTPREIS',
            'RATE'                => ['TARIF', 'SERVICE'],
            'FOR'                 => 'FUER',
        ],
        'pt' => [
            'BOOKING REF'        => 'CODIGO DE RES.',
            'TELEPHONE'          => 'TELEFONE',
            'FLIGHT'             => 'VOO',
            'CONFIRMED'          => ['CONFIRMADA', 'RESERVA CONFIRMADA'],
            'DEPARTURE'          => 'PARTIDA',
            'ARRIVAL'            => 'CHEGADA',
            'TERMINAL'           => 'TERMINAL',
            'FREQUENT TRAVELLER' => 'VIAJANTE FREQUENTE',
            'FLIGHT BOOKING REF' => 'CODIGO DE RESERVA DE VOO',
            'OPERATED BY'        => 'OPERADO POR',
            //            'AIRCRAFT OWNER'=>'PROPRIETARIO DE AVIAO',
            'DATE'      => 'DATA',
            'EQUIPMENT' => 'EQUIPAMENTO',
            'DURATION'  => 'DURACAO',
            'MEAL'      => 'REFEICAO',
            'SEAT'      => 'ASSENTO',
            'TICKET'    => 'BILHETE',
            'NON STOP'  => 'SEM ESCALA',
            'CLASS'     => 'CLASSE',
            'FOR'       => 'PARA',
        ],
        'es' => [
            'BOOKING REF'        => 'CODIGO DE RES.',
            'FLIGHT'             => 'VUELO',
            'CONFIRMED'          => ['CONFIRMADA', 'RESERVA CONFIRMADA'],
            'DEPARTURE'          => 'SALIDA',
            'ARRIVAL'            => 'LLEGADA',
            'TERMINAL'           => 'TERMINAL',
            'FREQUENT TRAVELLER' => 'VIAJERO FRECUENTE',
            'FLIGHT BOOKING REF' => 'LOCALIZADOR AEROLINEA',
            'OPERATED BY'        => 'OPERADO POR',
            //            'AIRCRAFT OWNER' => '',
            'DATE'      => 'FECHA',
            'EQUIPMENT' => 'EQUIPO',
            'DURATION'  => 'DURACION',
            'MEAL'      => 'COMIDA',
            'SEAT'      => 'ASIENTO',
            'TICKET'    => 'BILLETE',
            'NON STOP'  => 'SIN PARADAS',
            'FOR'       => 'POR',
            //Hotel
            'CHECK-IN'            => 'REGISTRO DE ENTRADA',
            'CHECK-OUT'           => 'SALIDA',
            'LOCATION'            => 'DIRECCION',
            'HOTEL BOOKING REF'   => 'REFERENCIA RESERVA DE HOTEL',
            'ROOM TYPE'           => 'TIPO DE HABITACION',
            'CANCELLATION POLICY' => 'CONDICION. CANCELACION',
            'TAXES'               => 'IMPUESTOS',
            'REQUEST/COMMENTS'    => 'SOLICITUD/COMENTARIOS',
            'RATE'                => 'TARIFA',
            'PER NIGHT'           => 'POR NOCHE',
            'TOTAL RATE'          => 'TARIFA TOTAL',
            //Car
            'CAR RENTAL'          => 'RESERVA COCHE',
            'VEHICLE INFORMATION' => 'INFORMACION DE VEHICULO',
            'PICK UP'             => 'RECOGIDA',
            'DROP OFF'            => 'DEVOLUCION',
            'TELEPHONE'           => 'TELEFONO',
            'CUSTOMER ID'         => 'ID DEL CLIENTE',
            'CAR BOOKING REF'     => 'REFERENCIA DE LA RESERVA DE COCHE',
            'ESTIMATED TOTAL'     => 'TARIFA ESTIMADA',
        ],
        'fr' => [
            'BOOKING REF' => 'REF. DE DOSSIER',
            'TELEPHONE'   => 'TELEPHONE',
            'FLIGHT'      => 'VOL',
            'CONFIRMED'   => ['CONFIRMEE', 'RESERVATION CONFIRMEE', "RESERVATION EN LISTE D'ATTENTE"],
            'DEPARTURE'   => 'DEPART',
            'ARRIVAL'     => 'ARRIVEE',
            //            'TERMINAL' => '',
            'FREQUENT TRAVELLER' => 'CARTE DE FIDELITE',
            'FLIGHT BOOKING REF' => 'REF. DE LA RESERVATION',
            'OPERATED BY'        => 'OPERE PAR',
            'AIRCRAFT OWNER'     => 'PROPR. APPAREIL',
            'DATE'               => 'DATE',
            'EQUIPMENT'          => 'EQUIPEMENT',
            'DURATION'           => 'DUREE',
            'MEAL'               => 'REPAS',
            'SEAT'               => 'SIEGE',
            'TICKET'             => 'BILLET',
            'NON STOP'           => 'SANS ESCALE',
            'FOR'                => 'POUR',
            //Train
            'E-TICKETNUMBER' => 'NUMERO DE BILLET ELECTRONIQUE',
            'CLASS'          => 'CLASSE',
        ],
        'it' => [
            'BOOKING REF'        => 'PRENOTAZIONE',
            'TELEPHONE'          => 'TELEFONO',
            'FLIGHT'             => 'VOLO',
            'CONFIRMED'          => ['CONFERMATA', 'PRENOTAZIONE CONFERMATA'],
            'DEPARTURE'          => 'PARTENZA',
            'ARRIVAL'            => 'ARRIVO',
            'TERMINAL'           => 'TERMINALE',
            'FLIGHT BOOKING REF' => 'CODICE PRENOTAZIONE',
            //            'FREQUENT TRAVELLER' => '',
            'OPERATED BY'    => 'OPERATO DA',
            'AIRCRAFT OWNER' => 'PROPRIETARIO AEROMOBILE',
            'DATE'           => 'DATA',
            'EQUIPMENT'      => 'AEROMOBILE',
            'DURATION'       => 'DURATA',
            'MEAL'           => 'PASTO',
            'SEAT'           => 'POSTO',
            'TICKET'         => 'BIGLIETTO',
            'NON STOP'       => 'NON-STOP',
            'FOR'            => 'PER',
        ],
        'pl' => [
            'BOOKING REF' => 'NUMER REZER.',
            'FLIGHT'      => 'REJS',
            'CONFIRMED'   => ['POTWIERDZONA', 'REZERWACJA POTWIERDZONA'],
            'DEPARTURE'   => 'WYLOT',
            'ARRIVAL'     => 'PRZYLOT',
            //            'TERMINAL' => '',
            //            'FREQUENT TRAVELLER' => '',
            'FLIGHT BOOKING REF' => 'NUMER REZERWACJI',
            'OPERATED BY'        => 'OBSLUGIWANY PRZEZ',
            //            'AIRCRAFT OWNER' => 'WLASCICIEL SAMOLOTU',
            'DATE'      => 'DATA',
            'EQUIPMENT' => 'SAMOLOT',
            'DURATION'  => 'CZAS TRWANIA',
            'MEAL'      => 'POSILEK',
            'SEAT'      => 'MIEJSCE',
            'TICKET'    => 'BILET',
            'NON STOP'  => 'BEZPOSREDNIO',
        ],
        'nl' => [
            'BOOKING REF' => 'RES. NUMMER',
            'FLIGHT'      => 'VLUCHT',
            'CONFIRMED'   => ['BEVESTIGD', 'RESERVERING BEVESTIGD'],
            'DEPARTURE'   => 'VERTREK',
            'ARRIVAL'     => 'AANKOMST',
            //            'TERMINAL' => '',
            //            'FREQUENT TRAVELLER' => '',
            'FLIGHT BOOKING REF' => 'VLUCHT RESERVERINGSNUMMER',
            //            'OPERATED BY' => '',
            //            'AIRCRAFT OWNER'=>'',
            'DATE'      => 'DATUM',
            'EQUIPMENT' => 'VLIEGTUIGTYPE',
            'DURATION'  => 'DUUR',
            'MEAL'      => 'MAALTIJD',
            'SEAT'      => 'ZITPLAATS',
            //            'TICKET' => '',
            //            'NON STOP' => '',
        ],
        'no' => [
            'BOOKING REF' => 'BOOKING REFERANSE',
            'FLIGHT'      => 'FLYREISE',
            'CONFIRMED'   => ['BEKREFTET', 'RESERVASJON BEKREFTET'],
            'DEPARTURE'   => 'AVGANG',
            'ARRIVAL'     => 'ANKOMST',
            //            'TERMINAL' => '',
            //            'FREQUENT TRAVELLER' => '',
            'FLIGHT BOOKING REF' => 'FLY BOOKING REF.',
            //            'OPERATED BY' => '',
            //            'AIRCRAFT OWNER'=>'',
            'DATE'      => 'DATO',
            'EQUIPMENT' => 'TRANSPORTMIDDEL',
            'DURATION'  => 'FLYTID',
            'MEAL'      => 'MALTID',
            'SEAT'      => 'SETE',
            'TICKET'    => 'BILLETT',
            'NON STOP'  => 'DIREKTE',
        ],
        'ca' => [
            'BOOKING REF' => 'REF. RESERVA',
            'FLIGHT'      => 'VOL',
            'CONFIRMED'   => 'CONFIRMADA',
            'DEPARTURE'   => 'SORTIDA',
            'ARRIVAL'     => 'ARRIBADA',
            //            'TERMINAL' => '',
            //            'FREQUENT TRAVELLER' => '',
            'FLIGHT BOOKING REF' => 'REF. RESERVA',
            'OPERATED BY'        => 'OPERAT PER',
            //            'AIRCRAFT OWNER'=>'',
            'DATE'      => 'DATA',
            'EQUIPMENT' => 'EQUIP',
            'DURATION'  => 'DURADA',
            'MEAL'      => 'APAT',
            //            'SEAT' => '',
            'TICKET' => 'BITLLET',
            //            'NON STOP' => 'DIREKTE',
        ],
        'zh' => [
            'BOOKING REF'            => '訂位代號:',
            'FLIGHT'                 => '航班',
            'CONFIRMED'              => '預訂已確認',
            'Reservation on standby' => '預訂已候補',
            'DEPARTURE'              => '啟程',
            'ARRIVAL'                => '抵達',
            'TERMINAL'               => '航廈',
            'FREQUENT TRAVELLER'     => '飛行常客',
            'FLIGHT BOOKING REF'     => '航班訂位代碼',
            //'OPERATED BY'        => '',
            'DATE'           => '列印日期',
            'EQUIPMENT'      => '機型',
            'AIRCRAFT OWNER' => '飛機所有者',
            'DURATION'       => '航行時間',
            //'MEAL'      => '',
            'SEAT'           => '座位',
            'TICKET'         => '機票',
            'E-TICKETNUMBER' => '電子機票',
            'NON STOP'       => '直飛',
            'FOR'            => '旅客',
            'TELEPHONE'      => '電話',
        ],
    ];
    private $text = '';
    private $date;
    private $dateRes;
    private $keywords = [
        'avis' => [
            'AVIS RENT-A-CAR',
            'AVIS',
        ],
        'thrifty' => [
            'THRIFTY - RENO',
        ],
        'europcar' => [
            'EUROPCAR - SANTIAGO DE COMPOSTELA',
            'EUROPCAR',
        ],
        'sixt' => [
            'SIXT - DUESSELDORF',
            'SIXT',
        ],
    ];
    private static $providers = [
        'travelgenio' => [
            'THANK YOU FOR BOOKING WITH TRAVELGENIO', '@TRAVELGENIO.COM',
            'WWW.TRAVELGENIO.ES', 'WWW.TRAVELGENIO.US', 'www.travelgenio.com',
        ],
        'egencia' => [
            'EGENCIA',
        ],
        'edreams' => [
            'EDREAMS',
            'eDreams',
        ],
        'opodo' => [
            'OPODO LTD',
            'opodo.',
            'opodocorporate.',
            'OPODO',
        ],
        'eva' => [
            'EVA AIRWAYS',
        ],
        'nexus' => [
            'NEXUS ELITE LIFESTYLE PVT LTD',
        ],
        'maketrip' => [
            'MAKEMYTRIP.COM',
        ],
        'amextravel' => [
            'AEXP.COM',
        ],
        'goibibo' => [
            'Goibibo',
        ],
        'amadeus' => [
            'AMADEUS.COM',
        ],
        'hoggrob' => [
            'HRGWORLDWIDE.COM',
        ],
        'wagonlit' => [
            'CARLSONWAGONLIT',
        ],
        'skywards' => [
            'Emirates',
        ],
        'airfrance' => [
            'SUR LE SITE WWW.AIRFRANCE.SN',
        ],
        'ticketse' => [
            'HAVE A NICE FLIGHT! BEST REGARDS TICKET.SE',
        ],
        'klm' => [
            '@KLM.COM',
        ],
        'tllink' => [
            'Travellink',
        ],
        'qmiles' => [
            'QATARAIRWAYS.COM',
        ],
        'tapportugal' => [
            'TAP PORTUGAL',
        ],
        'bcd' => [
            'BCD TRAVEL',
        ],
        'cyprusair' => [
            'CYPRUS AIRWAYS CONTACT CENTER',
        ],
        'aircorsica' => [
            '@AIRCORSICA.COM', 'www.aircorsica.com',
        ],
    ];
    private $otaCode = null;
    private $otaConfNo = null;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectBody($this->text)) {
                    $this->text = $this->clearText($this->text);
                    $this->parseEmail($email, $parser);
                }
            }
        }

        if (count($email->getItineraries()) === 0) {
            $this->text = $this->getText($parser);

            if (!$this->assignLang($this->text)) {
                $this->logger->debug("Can't determine a language!");

                return $email;
            }
            $this->parseEmail($email, $parser);
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

                if ($this->detectBody($text)) {
                    return true;
                }
            }
        }
        //$text = empty($parser->getHTMLBody()) ? $parser->getPlainBody() : $parser->getHTMLBody();
        $text = $this->getText($parser);

        if ($this->detectBody($text)) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
            $flag = false;

            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    $flag = true;
                }
            }

            if ($flag) {
                foreach ($this->reSubject as $reSubject) {
                    if (preg_match($reSubject, $headers["subject"])) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        $cnt = 2; // body + attach

        return $cnt * count(self::$reBody);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function getText(\PlancakeEmailParser $parser): string
    {
        $bodyPlain = $parser->getPlainBody();

        if (!empty($bodyPlain) && strpos($bodyPlain, '  ') !== false
            && count(array_filter(array_map('trim', explode("\n", $bodyPlain)))) > 30
        ) {
            return $this->clearText($bodyPlain);
        }

        // NBSP to SPACE and other
        $bodyHtml = $parser->getHTMLBody();

        if (strpos($bodyHtml, chr(194) . chr(160)) !== false || strpos($bodyHtml, "\r") !== false) {
            $this->http->SetEmailBody(str_replace([chr(194) . chr(160), "\r"], [' ', ''], $bodyHtml));
        }

        $paragraphs = $this->http->XPath->query('//p[count(preceding-sibling::p[normalize-space()]) + count(following-sibling::p[normalize-space()]) > 29]');

        if ($paragraphs->length < 30) {
            $paragraphs = $this->http->XPath->query('//div[count(preceding-sibling::div[normalize-space()]) + count(following-sibling::div[normalize-space()]) > 29]');
        }

        if ($paragraphs->length < 30) {
            $paragraphs = $this->http->XPath->query('//pre[count(preceding-sibling::pre[normalize-space()]) + count(following-sibling::pre[normalize-space()]) > 29]');
        }

        if ($paragraphs->length >= 30) {
            $text = '';

            foreach ($paragraphs as $p) {
                $text .= "\n" . $this->htmlToText($this->http->FindHTMLByXpath('.', null, $p),
                    $this->http->XPath->query('descendant::br', $p)->length > 0);
            }

            return $text;
        }

        $bodyHtml = $parser->getHTMLBody();

        if ($this->http->XPath->query('//br[count(preceding-sibling::br) + count(following-sibling::br) > 29]')->length > 30) {
            return $this->htmlToText($bodyHtml);
        }

        return $this->clearText($bodyHtml);
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

    private function clearText($text): string
    {
        $text = strip_tags($text);
        $NBSP = chr(194) . chr(160);
        $text = str_replace($NBSP, ' ', html_entity_decode($text));
        $text = str_replace("\n>", "\n", $text);
        $text = str_replace("\n>", "\n", $text);

        return $text;
    }

    private function detectBody($text)
    {
        if ((strpos($text, '- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -') !== false
                && strpos($text, '---------------------------') !== false)
            || (strpos($text, '---------------------------') !== false
                && preg_match("#\n\s*(?:FLIGHT|CAR RENTAL|HOTEL|TRAIN|FLUG|BAHN|VOO|VUELO|RESERVA COCHE|VOL|VOLO|WYLOT|VLUCHT|FLYREISE) [^\n]+\n\s*[\-]{20,}#",
                    $text))
        ) {
            return $this->assignLang($text);
        }

        return false;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $bodyText = !empty($parser->getHTMLBody()) ? $parser->getHTMLBody() : $parser->getPlainBody();

        foreach (self::$providers as $provider => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($this->text, $keyword) !== false) {
                    return $provider;
                } elseif (strpos($bodyText, $keyword) !== false) {
                    return $provider;
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email, \PlancakeEmailParser $parser): void
    {
        $this->dateRes = strtotime($this->dateStringToEnglish($this->re("#{$this->opt($this->t('DATE'))}:\s*(\d+\s*\w+\s*\d+)#",
            $this->text)));

        if (empty($this->dateRes)) {
            $date = str_replace(['Ả', 'Á'], ['ả', 'á'], strtolower($this->re("#{$this->opt($this->t('DATE'))}:\s*(\d+.+\d{4})#", $this->text)));

            $this->dateRes = strtotime($this->dateStringToEnglish($date));
        }

        if (!empty($this->dateRes)) {
            $this->date = $this->dateRes;
        }

        $headText = $this->re("/.*(^\D+{$this->opt($this->t('DATE'))}\:.+)^ {0,5}(?:{$this->opt($this->t('TELEPHONE'))}|FAX|E-MAIL|傳真)\:/smu", $this->text);

        if (empty($headText)) {
            $headText = $this->re("/.*(^.+ {3,}{$this->opt($this->t('DATE'))}\:.+?)^ {0,5}(?:{$this->opt($this->t('TELEPHONE'))}|FAX|E-MAIL|傳真)\:/smu", $this->text);
        }

        if (empty($headText)) {
            $headText = $this->re("/.*(^.+ {3,}{$this->opt($this->t('DATE'))}\:.+?)^ {0,5}(?:{$this->opt($this->t('FLIGHT'))}) {2,}/smu", $this->text);
        }
        $passengerText = preg_replace("/^ {0,7}\S( ?\S+)*/mu", '', $headText);
        $passengerText = preg_replace("/^.{7,}? {3,}/mu", '', $passengerText);

        if (preg_match_all("/^\s*([[:alpha:]]+(?:\s[[:alpha:]]+)*\/[[:alpha:]]+(?:\s[[:alpha:]]+)*)\s*$/mu", $passengerText, $m)) {
            $this->travellers = preg_replace("#(SCANNING/MAIL)#", "", $m[1]);
            $this->travellers = preg_replace('/\s+/', ' ', $this->travellers);
            $this->travellers = preg_replace('/\s+(MR|MS|MRS|MISS|MSTR|DR)\s*$/i', '', $this->travellers);
            $this->travellers = array_filter(preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $this->travellers));
        }

        $this->otaCode = $this->getProvider($parser);

        $confNo = $this->re("#{$this->opt($this->t('BOOKING REF'))}\s*:\s*([A-Z\d]{5,6})[ \r]*$#m", $this->text);

        if (!empty($confNo) || !empty($this->dateRes)) {//if empty($confNo) && empty($this->dateRes) => there is no confno
            $this->otaConfNo = $confNo;
        }

        $flights = [];
        $trains = [];
        $hotels = [];
        $cars = [];

        $pattern = "#^[ ]*("
            . "{$this->opt($this->t('FLIGHT'))}[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+[ ]*-"
            . "|{$this->opt($this->t('CAR RENTAL'))} .+ [[:upper:]]{3} \d"
            . "|{$this->opt($this->t('HOTEL'))} .+ [[:upper:]]{3} \d"
            . "|{$this->opt($this->t('TRAIN'))} .+ [[:upper:]]{3} \d"
            . ")#m";
        $this->text = str_replace("*", "", $this->text);
        $segments = $this->splitText($this->text, $pattern, true);

        if (empty($segments)) {
            $this->text = text($parser->getHTMLBody());
            $segments = $this->splitText($this->text, $pattern, true);
        }

        if (empty($segments)) {
            $this->logger->debug("other format of segments!");

            return;
        }

        foreach ($segments as $segment) {
            if (preg_match("#^{$this->opt($this->t('FLIGHT'))}#", $segment)) {
                $flights[] = $segment;
            }

            if (preg_match("#^{$this->opt($this->t('TRAIN'))}#", $segment)) {
                $trains[] = $segment;
            }

            if (preg_match("#^{$this->opt($this->t('HOTEL'))}#", $segment)) {
                $hotels[] = $segment;
            }

            if (preg_match("#^{$this->opt($this->t('CAR RENTAL'))}#", $segment)) {
                $cars[] = $segment;
            }
        }

        if (!empty($flights)) {
            $this->parseFlight($flights, $email);
        }

        if (!empty($trains)) {
            $this->parseTrain($trains, $email);
        }

        if (!empty($hotels)) {
            $this->parseHotel($hotels, $email);
        }

        if (!empty($cars)) {
            $this->parseCar($cars, $email);
        }
    }

    private function parseCar(array $cars, Email $email)
    {
        foreach ($cars as $car) {
            $r = $email->add()->rental();

            if (!empty($this->otaConfNo)) {
                $r->ota()->confirmation($this->otaConfNo);
            }

            if (!empty($this->otaCode)) {
                $r->ota()->code($this->otaCode);
                $email->setProviderCode($this->otaCode);
            }

            $traveller = $this->re("#CAR BOOKING REF\:.*\s*CONFIRMED FOR\s*([[:alpha:]][-.\/'[:alpha:] ]*[[:alpha:]])#", $car);

            if (empty($traveller)) {
                $traveller = $this->re("#[ ]{2}([[:alpha:]]+(?: [[:alpha:]]+)*\/[[:alpha:]]+(?: [[:alpha:]]+)*)$#mu", $this->text);
            }

            $r->general()
                ->confirmation($this->re("#{$this->opt($this->t('CAR BOOKING REF'))}\s*:\s*([A-Z\d\-]{5,6})#", $car))
                ->traveller(preg_replace("/\s(?:MRS|MS|MR)/", "", $traveller));

            $confirmed = (array) $this->t('CONFIRMED');

            if ($this->re("#\n\s*{$this->opt($this->t('CONFIRMED'))}#", $car)) {
                $r->general()
                    ->status(array_shift($confirmed));
            }

            if (!empty($this->dateRes)) {
                $r->general()
                    ->date($this->dateRes);
            }
            $year = $this->re("#^{$this->opt($this->t('CAR RENTAL'))}\s+.*?\s+\w+\s+\d+\s+\w+\s+(\d{4})#", $car);
            $keyword = $this->re("#^{$this->opt($this->t('CAR RENTAL'))}\s+(.*?)\s+\w+\s+\d+\s+\w+\s+\d{4}#", $car);
            $rentalProvider = $this->getRentalProviderByKeyword($keyword);

            if (!empty($rentalProvider)) {
                $r->program()->code($rentalProvider);
            } else {
                $r->program()->keyword($keyword);
            }

            $account = $this->re("#{$this->opt($this->t('CUSTOMER ID'))}:\s+([A-Z\d]{5,})#", $car);

            if (!empty($account)) {
                $r->program()
                    ->account($account, false);
            }
            $r->car()
                ->type($this->nice($this->re("#\n\s*{$this->opt($this->t('VEHICLE INFORMATION'))}[\s:]*(.+?){$this->opt($this->t('ESTIMATED TOTAL'))}#s",
                    $car)));

            if (preg_match("#{$this->opt($this->t('TELEPHONE'))}:\s+([\d \-\+\(\)]+)\s*\({$this->opt($this->t('PICK UP'))}\),\s*([\d \-\+\(\)]+)\s*\({$this->opt($this->t('DROP OFF'))}\)#",
                $car, $m)) {
                $r->pickup()
                    ->phone($m[1]);
                $r->dropoff()
                    ->phone($m[2]);
            }

            if (preg_match("#{$this->opt($this->t('PICK UP'))}:\s+(.+?)\s*(\d+\s+\w+)\s+(\d+:\d+)#", $car, $m)) {
                $r->pickup()
                    ->location($m[1])
                    ->date(strtotime($this->dateStringToEnglish($m[2]) . ' ' . $year . ', ' . $m[3]));
            }
            $node = $this->re("#{$this->opt($this->t('PICK UP'))}:\s+(.+?)\s+{$this->opt($this->t('DROP OFF'))}#s",
                $car);
            $node = preg_replace("#(\d+\s+\w+\s+\d+:\d+)#", '', $node);

            if (!empty($node)) {
                $r->pickup()
                    ->location($this->nice($node));
            }

            if (preg_match("#{$this->opt($this->t('DROP OFF'))}:\s+(.+?)\s*(\d+\s+\w+)\s+(\d+:\d+)#", $car, $m)) {
                $r->dropoff()
                    ->location($m[1])
                    ->date(strtotime($this->dateStringToEnglish($m[2]) . ' ' . $year . ', ' . $m[3]));
            }
            $node = $this->re("#{$this->opt($this->t('DROP OFF'))}:\s+(.+?)\s+{$this->opt($this->t('TELEPHONE'))}#s",
                $car);
            $node = preg_replace("#(\d+\s+\w+\s+\d+:\d+)#", '', $node);

            if (!empty($node)) {
                $r->dropoff()
                    ->location($this->nice($node));
            }

            if ($r->getPickUpDateTime() > strtotime("+6 month", $r->getDropOffDateTime())) {
                $r->dropoff()
                    ->date(strtotime("+1 year", $r->getDropOffDateTime()));
            }

            $tot = $this->getTotalCurrency($this->re("#\n\s*{$this->opt($this->t('ESTIMATED TOTAL'))}:\s+([A-Z]{3}\s+[\d\.\,]+)#",
                $car));

            if ($tot['Total'] !== null) {
                $r->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
    }

    private function parseHotel(array $hotels, Email $email)
    {
        foreach ($hotels as $hotel) {
            $h = $email->add()->hotel();

            if (!empty($this->otaConfNo)) {
                $h->ota()->confirmation($this->otaConfNo);
            }

            if (!empty($this->otaCode)) {
                $h->ota()->code($this->otaCode);
                $email->setProviderCode($this->otaCode);
            }

            $h->general()
                ->confirmation($this->re("#{$this->opt($this->t('HOTEL BOOKING REF'))}\s*:\s*([A-Z\d\-]{5,})#", $hotel));
            $traveller = $this->re("#^[> ]*{$this->opt($this->t('BESTAETIGT FUER'))} ([[:alpha:]]+(?: [[:alpha:]]+)*\/[[:alpha:]]+(?: [[:alpha:]]+)*)[ ]*(?:\(|$)#m", $hotel)
                ?? $this->re("/[ ]{2}([[:alpha:]]+(?: [[:alpha:]]+)*\/[[:alpha:]]+(?: [[:alpha:]]+)*)$/mu", $this->text);

            if (!empty($traveller)) {
                $h->general()
                    ->traveller($traveller);
            }

            if (empty($traveller) && count($this->travellers) > 0) {
                $h->general()
                    ->travellers($this->travellers);
            }

            $confirmed = (array) $this->t('CONFIRMED');

            if ($this->re("#\n\s*({$this->opt($this->t('CONFIRMED'))})#u", $hotel)) {
                $h->general()
                    ->status(array_shift($confirmed));
            }

            if (!empty($this->dateRes)) {
                $h->general()
                    ->date($this->dateRes);
            }

            $addr = $this->re("#\n\s*{$this->opt($this->t('LOCATION'))}\s*:\s*(.*?)\s+(?:{$this->opt($this->t('HOTEL BOOKING REF'))}|{$this->opt($this->t('CONFIRMED'))}|{$this->opt($this->t('RATE'))})#s",
                $hotel);

            $addr = preg_replace([
                "#{$this->opt($this->t('CHECK-IN'))}:\s+\d+\s+\w+#",
                "#{$this->opt($this->t('CHECK-OUT'))}:\s+\d+\s+\w+#",
            ], ['', ''], $addr);

            $year = $this->re("#{$this->opt($this->t('HOTEL'))}\s+.+?\s+[A-Z]{3}\s+\d+\s+[A-Z]{3,}\s+(\d{4})#", $hotel);

            $h->hotel()
                ->name($this->re("#{$this->opt($this->t('HOTEL'))}\s+(.+?)\s+[A-Z]{3} \d+#", $hotel))
                ->address($this->nice($addr))
                ->phone(trim($this->re("#{$this->opt($this->t('TELEPHONE'))}:\s*([\d\s()\-\+\/]+)#", $hotel)), true);

            $fax = trim($this->re("#{$this->opt($this->t('FAX'))}:\s*([\d \(\)\-\+\/]+)#", $hotel));

            if (!empty($fax)) {
                $h->hotel()
                    ->fax($fax);
            }

            $h->booked()
                ->checkIn(strtotime($this->nice($this->re("#{$this->opt($this->t('CHECK-IN'))}:\s+(\d+\s+\w+)#",
                        $hotel)) . ' ' . $year))
                ->checkOut(strtotime($this->nice($this->re("#{$this->opt($this->t('CHECK-OUT'))}:\s+(\d+\s+\w+)#",
                        $hotel)) . ' ' . $year))
                ->rooms($this->re("#{$this->opt($this->t('ROOM TYPE'))}:\s+.+?\((\d+)\)#", $hotel), false, true);

            $cancellation = $this->nice($this->re("#{$this->opt($this->t('CANCELLATION POLICY'))}:\s*(.+?)\s*(?:{$this->opt($this->t('TAXES'))}|{$this->opt($this->t('REQUEST/COMMENTS'))}|SERVICE)#s",
                $hotel));

            if (empty($cancellation)) {
                $cancellation = $this->nice($this->re("#({$this->opt($this->t('CANCELLATION FREE'))}.+?)\s*(?:{$this->opt($this->t('FREE BREAKFAST'))})#s",
                    $hotel));
            }

            $h->general()
                ->cancellation($cancellation);

            $h->booked()->guests($this->re('/OCCUPANCY\s*\:\s*(\d{1,2}) ADULT/i', $hotel), false, true);

            $r = $h->addRoom();
            $rate = '';
            $cur = $this->re("#\n\s*{$this->opt($this->t('RATE'))}:\s*[^\n]*?[ ]*([A-Z]{3})[ ]*{$this->opt($this->t('PER NIGHT'))}#", $hotel);

            if (preg_match_all("#\n([^\n]*?{$this->opt($this->t('PER NIGHT'))})#", $hotel, $m)) {
                $rates = array_map(function ($e) { return (float) $this->re('/([\d\.]+)[ ]*[A-Z]{3}/', $e); }, $m[1]);
                $min = min($rates);
                $max = max($rates);

                if ($min === $max) {
                    $rate = $min . ' ' . $cur;
                } else {
                    $rate = $min . '-' . $max . ' ' . $cur;
                }
            }

            if (!empty($roomType = $this->re("/{$this->opt($this->t('ROOM TYPE'))}\:\s*(.+)/u", $hotel))) {
                $r->setType($roomType);
            }
            $r->setRate($rate, true);

            if ($h->getCheckInDate() > strtotime("+6 month", $h->getCheckOutDate())) {
                $h->booked()
                    ->checkOut(strtotime("+1 year", $h->getCheckOutDate()));
            }

            $tot = $this->getTotalCurrency($this->re("#\n\s*(.*?)\s+{$this->opt($this->t('TOTAL RATE'))}#",
                $hotel)); //GESAMTPREIS

            if ($tot['Total'] !== null) {
                $h->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
            $this->detectDeadLine($h);
        }
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^FREE OF CHARGE UP TO (?<priorHours>\d+) HOURS\s*BEFORE\s*ARRIVAL, AFTER THIS DATE THE TOTAL OF THERESERVATION WILL BE CHARGED./i",
            $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['priorHours'] . ' hours');

            return;
        }

        if (preg_match("/^CXL FEE IF CXLD LESS THAN (?<priorDays>\d+) DAYS?\s*BEFORE\s*ARRVEUR\s*[\d\.\,]+ CANCEL FEE PER ROOM/i", $cancellationText, $m)
            || preg_match("/^CXL BY (?<priorDays>\d+) DAY PRIOR TO ARRIVAL-FEE/i", $cancellationText, $m)) {
            $h->booked()->deadlineRelative($m['priorDays'] . ' days');

            return;
        }

        if (preg_match("/^TO AVOID BEING BILLED CANCEL BY (?<time>.+?) (?<date>\d+\/\d+\/\d+)/i", $cancellationText,
                $m)
            || preg_match("/^CANCEL ON (?<date>\d+\D+\d{4}) BY (?<time>.+?) LT TO AVOID/i", $cancellationText,
                $m)) {
            $h->booked()->deadline(strtotime($m['time'], strtotime($m['date'])));

            return;
        }

        if (preg_match("/^CANCELLATION FREE OF CHARGE WHEN CANCELLING BEFORE (\d+\/\d+\/\d{4}\s*[\d\:]+\s*A?P?M)\./si", $cancellationText,
            $m)) {
            $h->booked()->deadline(strtotime($m[1]));

            return;
        }

        if (preg_match("/^IF CANCEL AFTER (?<time>\d+\s*A?P?M) OF ARRIVAL DAY OR CUSTOMERS ARE NO SHOW/i",
            $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['time'], $h->getCheckInDate()));

            return;
        }

        if (preg_match("/RESERVATION MAY BE CANCELLED WITHOUT CHARGES UNTIL (?<time>\d+)H ON THE DAY OF ARRIVAL/i",
            $cancellationText, $m)) {
            $h->booked()->deadline(strtotime($m['time'] . ':00', $h->getCheckInDate()));

            return;
        }

        if (preg_match("/THE AMOUNT DUE IS NOT REFUNDABLE EVEN IF THEBOOKING ISCANCELLED OR MODIFIED/i",
            $cancellationText, $m)) {
            $h->booked()->nonRefundable();

            return;
        }

        $h->booked()
            ->parseNonRefundable("#ADVANCE PURCHASE ENTIRE STAY NONREFUNDABLE#i");
    }

    private function parseTrain(array $trains, Email $email)
    {
        $t = $email->add()->train();

        if (!empty($this->otaConfNo)) {
            $t->ota()->confirmation($this->otaConfNo);
        }

        if (!empty($this->otaCode)) {
            $t->ota()->code($this->otaCode);
            $email->setProviderCode($this->otaCode);
        }

        $t->general()
//            ->confirmation($this->re("#{$this->opt($this->t('BOOKING REF'))}\s*:\s*([A-Z\d]{5,6})#", $this->text)) // WTF?
            ->noConfirmation()
            ->traveller($this->re("/[ ]{2}([[:alpha:]]+(?: [[:alpha:]]+)*\/[[:alpha:]]+(?: [[:alpha:]]+)*)$/mu", $this->text));

        if (!empty($this->date)) {
            $t->general()
                ->date($this->date);
        }

        if (preg_match_all("#{$this->opt($this->t('E-TICKETNUMBER'))}\s*:\s+([\d+ \-]+)\s+[A-Z]#",
            $this->text,
            $m)) {
            $t->setTicketNumbers($m[1], false);
        }

        if (preg_match_all("#{$this->opt($this->t('FREQUENT TRAVELLER'))}\s+([A-Z\d\-]{5,})#", implode("\n", $trains),
            $m)) {
            $t->program()
                ->accounts($m[1], false);
        }

        foreach ($trains as $train) {
            $s = $t->addSegment();
            $this->parseTrainSeg($train, $s);
        }
    }

    private function parseTrainSeg(string $text, TrainSegment $s)
    {
        $year = $this->re("#{$this->opt($this->t('TRAIN'))}\s+.*?\s+\w+\s+\d+\s+\w+\s+(\d{4})#", $text);

        if (preg_match("#{$this->opt($this->t('DEPARTURE'))}:\s*.*?[-\s]*(\d+\s*\w{3})\s*(\d+:\d+(?: *[ap]m?)?)#i",
            $text, $m)) {
            $strDate = $this->dateStringToEnglish(preg_replace("#p$#i", 'PM',
                preg_replace("#a$#i", 'AM', $this->nice($m[1]) . ' ' . $year . ', ' . $m[2])));
            $s->departure()
                ->date(strtotime($strDate));
        }

        if (preg_match("#{$this->opt($this->t('ARRIVAL'))}:\s*.*?[-\s]*(\d+\s*\w{3})\s*(\d+:\d+(?: *[ap]m?)?)#i", $text,
            $m)) {
            $strDate = $this->dateStringToEnglish(preg_replace("#p$#i", 'PM',
                preg_replace("#a$#i", 'AM', $this->nice($m[1]) . ' ' . $year . ', ' . $m[2])));
            $s->arrival()
                ->date(strtotime($strDate));
        }

        if ($s->getDepDate() > strtotime("+6 month", $s->getArrDate())) {
            $s->arrival()
                ->date(strtotime("+1 year", $s->getArrDate()));
        }
        $node = $this->re("#{$this->opt($this->t('DEPARTURE'))}:\s*(.*?)\s+\d+\s*\w+\s*\d+:\d+#s", $text);
        $s->departure()
            ->name($node);

        $node = $this->re("#{$this->opt($this->t('ARRIVAL'))}:\s*(.*?)\s+\d+\s*\w+\s*\d+:\d+#s", $text);
        $s->arrival()
            ->name($node);

        $node = $this->re("#{$this->opt($this->t('TRAIN'))}\s+(.+?)\s+\w+\s+\d+\s+\w+\s+\d{4}#", $text);

        if (preg_match("#^(.+?)\s+\((.+)\)$#", $node, $m)) {
            $s->extra()
                ->number($m[1])
                ->model($m[2]);
        } else {
            $s->extra()
                ->number($node);
        }

        if (empty($service = $this->re("#{$this->opt($this->t('OPERATED BY'))}[\s:]+(.+)#", $text))) {
            $service = $this->re("#^(.+?)\s+\d+$#", $s->getNumber());
        }
        $s->extra()
            ->service($service);

        $class = $this->re("#{$this->opt($this->t('CLASS'))}:\s*(.+)(?:[ ]{2}|$)#mu", $text);

        if (preg_match("#^(.+?)[ ]*\([ ]*([A-Z]{1,2})[ ]*\)$#", $class, $m)
            || preg_match("#(?:^|[ ]{2})(ECONOMY)[ ]*\([ ]*([A-Z]{1,2})[ ]*\)$#m", $text, $m)
        ) {
            // ECONOMY (K)
            $s->extra()
                ->cabin($m[1])
                ->bookingCode($m[2]);
        } elseif (preg_match("#^[( ]*([A-Z]{1,2})[ )]*$#", $class, $m)) {
            // K    |    (K)
            $s->extra()->bookingCode($m[1]);
        } elseif ($class) {
            $s->extra()->cabin($class);
        }

        $s->extra()->duration($this->re("#[) ] {$this->opt($this->t('DURATION'))}[\s:]+(\d+:\d+)\s*$#m", $text), true, true);

        if (preg_match_all("# ({$this->opt($this->t('VOITURE'))}[ ]*\d+[ ]*{$this->opt($this->t('SIEGE'))}[ ]*\d+)(?: |$)#m", $text, $m)) {
            $s->extra()->seats($m[1]);
        }
    }

    private function parseFlight(array $flights, Email $email)
    {
        $f = $email->add()->flight();

        if (!empty($this->otaCode)) {
            $f->ota()->code($this->otaCode);
            $email->setProviderCode($this->otaCode);
        }

        $travellers = [];

        $f->general()->noConfirmation();

        if (!empty($this->date)) {
            $f->general()
                ->date($this->date);
        }

        if (preg_match_all("#{$this->opt($this->t('TICKET'))}\s*:\s*(?:(?:[A-Z\d]{2})?\s*\/?\-?\s*(?:ETKT|ELEKTRONISK BILLETT|VÉ ĐIỆN TỬ))?\s+([\d+ \-]+)\s+[A-Z]\w+ (.+)#u",
            $this->text, $ticketMatches)) {
            foreach ($ticketMatches[1] as $ticket) {
                $pax = '';

                if (preg_match("/$ticket\s*{$this->t('FOR')}\s*(.+)\n/", $this->text, $m)) {
                    $pax = $m[1];
                }

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, preg_replace('/\s+(MR|MS|MRS|MISS|MSTR|DR)\s*$/i', '', $pax));
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
            //$f->issued()->tickets($ticketMatches[1], false);
            $travellers = array_unique(array_map('trim', $ticketMatches[2]));
        } elseif (preg_match_all("#{$this->opt($this->t('SEAT'))}\:\s*.+{$this->opt($this->t('FOR'))}\s+(\D+MR?S?)\n#u",
            $this->text, $ticketMatches)) {
            $travellers = array_unique(array_map('trim', $ticketMatches[1]));
        } elseif (preg_match_all("#{$this->opt($this->t('FREQUENT TRAVELLER'))}[:\s]+[A-Z\d\-]{5,}\s+{$this->opt($this->t('FOR'))}\s+([A-Z\s+\/\-]+)\s*(?:$|\n)#", $this->text, $m)) {
            $travellers = array_unique(array_map('trim',
                preg_replace("/\n\s*{$this->opt($this->t('NON STOP'))}[\s\S]*/i", '', $m[1])));
        } elseif (preg_match("#{$this->opt($this->t('Passenger’s name'))}\.?(.+)\n#", $this->text, $m)) {
            $travellers = explode(",", $m[1]);
        }

        if (!empty($travellers)) {
            $travellers = preg_replace('/\s+/', ' ', $travellers);
            $travellers = preg_replace('/\s+(MR|MS|MRS|MISS|MSTR|DR)\s*$/i', '', $travellers);
            //$travellers = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", '$2 $1', $travellers);

            $f->general()->travellers(array_map(function ($name) {
                return preg_replace("/^(.+?)(?:\s+(?:MISS|MRS|MR|MS|DR))+$/", '$1', $name);
            }, $travellers));
        } elseif (count($this->travellers) > 0) {
            $f->general()
                ->travellers($this->travellers);
        }

        if (preg_match_all("#{$this->opt($this->t('FREQUENT TRAVELLER'))}[:\s]+([A-Z\d\-]{5,})#",
            implode("\n", $flights),
            $accountMatches)) {
            $f->program()->accounts(array_values(array_unique($accountMatches[1])), false);
        }

        if (preg_match_all("#{$this->opt($this->t('E-TICKETNUMBER'))}\s*\:?\s+([\d+ \-]+)\s+#",
            $this->text,
            $m)) {
            foreach ($m[1] as $ticket) {
                $pax = $this->re("/$ticket\s*{$this->t('FOR')}\s*(.+)/", $this->text);

                if (!empty($pax)) {
                    $f->addTicketNumber($ticket, false, preg_replace('/\s+(MR|MS|MRS|MISS|MSTR|DR)\s*$/i', '', $pax));
                } else {
                    $f->addTicketNumber($ticket, false);
                }
            }
        }

        $otaConfNoIsFake = false;

        foreach ($flights as $flight) {
            $s = $f->addSegment();

            if (stripos($flight, $this->t('Reservation on standby')) !== false) {
                $s->extra()
                ->status('Waitlisted');
            }

            $this->parseFlightSeg($flight, $s);

            if (!empty($s->getConfirmation()) && !empty($this->otaConfNo) && $s->getConfirmation() === $this->otaConfNo) {
                $otaConfNoIsFake = true;
            }

            foreach ($f->getSegments() as $segment) {
                if ($s->getId() === $segment->getId()) {
                    continue;
                }

                if (serialize(array_diff_key($segment->toArray(), ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                    if (!empty($s->getSeats())) {
                        $segment->extra()->seats(array_merge($segment->getSeats(), $s->getSeats()));
                    }
                    $f->removeSegment($s);

                    break;
                }
            }
        }

        if (!$otaConfNoIsFake && !empty($this->otaConfNo)) {
            $f->ota()->confirmation($this->otaConfNo);
        }

        $tot = $this->getTotalCurrency($this->re("#\n\s*{$this->opt($this->t('TOTAL COST FOR TICKETS:'))}\s*([\d\.]+\s*[A-Z]{3})\s#",
            $this->text));

        if ($tot['Total'] !== null) {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency']);
        }
    }

    private function parseFlightSeg(string $text, FlightSegment $s)
    {
        if (preg_match("#[> ]*{$this->opt($this->t('CONFIRMED'))}(?:$|[ ]{2}|[ ]*,)#m", $text)) {
            $confirmed = (array) $this->t('CONFIRMED');
            $s->extra()->status(array_shift($confirmed));
        }
        $refNo = $this->re("#{$this->opt($this->t('FLIGHT BOOKING REF'))}\s*:\s*[A-Z\d]{2}\s*\/\s*([A-Z\d]{5,6})#u",
            $text);

        if (!empty($refNo)) {
            $s->airline()
                ->confirmation($refNo);
        }

        $year = $this->re("#{$this->opt($this->t('FLIGHT'))}\s+.*?\s+(?:\w+\s+\d+\s+\w+|\w+\s+\w+\s*\d+\,|\d{1,2}\s*\D*)\s+(\d{4})#", $text);

        if (empty($year)) {
            $year = $this->re("#{$this->opt($this->t('FLIGHT'))}\s+.+(\d{4})[年]#", $text);
        }

        if (preg_match("#{$this->opt($this->t('DEPARTURE'))}:\s*.*?[-\s]*(?:{$this->opt($this->t('TERMINAL'))}\s*[A-Z\d]+\s*)?(?:\s|\-)((?:\d+\s*\w{3,}|\w{3,}\s*\d+))\s+\.?\s*(\d+:\d+(?: *[ap.]m?)?)#i",
            $text, $m)) {
            $strDate = $this->dateStringToEnglish(preg_replace("#p$#i", 'PM',
                preg_replace("#a$#i", 'AM', $this->nice($m[1]) . ' ' . $year . ', ' . $m[2])));

            $s->departure()
                ->date(strtotime($strDate));
        } elseif (preg_match("#{$this->opt($this->t('DEPARTURE'))}:.+[ ]{10,}\s*(\d+)[月](\d+)[日]\s*([\d\:]+)\n#u",
            $text, $m)) {
            $strDate = $m[2] . '.' . $m[1] . '.' . $year . ', ' . $m[3];
            $s->departure()
                ->date(strtotime($strDate));
        } elseif (preg_match("#{$this->opt($this->t('DEPARTURE'))}\:.+[ ]{5,}(\d{1,2})\s*(\D*)\n[ ]{5,}.+[ ]{5,}(\d+\:\d+)\n#", $text, $m)) {
            $date = str_replace(['Ả', 'Á'], ['ả', 'á'], strtolower($m[1] . ' ' . $m[2] . ' ' . $year . ', ' . $m[3]));
            $strDate = strtotime($this->dateStringToEnglish($date));
            $s->departure()
                ->date($strDate);
        }

        if (preg_match("#{$this->opt($this->t('ARRIVAL'))}:\s*.*?[-\s]*(\d+\s*\w{2,}\D)\.?\s*(\d+:\d+(?: *[ap.]m?)?)#i", $text,
            $m)) {
            $strDate = $this->dateStringToEnglish(preg_replace("#p$#i", 'PM',
                preg_replace("#a$#i", 'AM', $this->nice($m[1]) . ' ' . $year . ', ' . $m[2])));
            $s->arrival()
                ->date(strtotime($strDate));
        } elseif (preg_match("#{$this->opt($this->t('ARRIVAL'))}:.+[ ]{10,}\s*(\d+)[月](\d+)[日]\s*([\d\:]+)\n#u",
            $text, $m)) {
            $strDate = $m[2] . '.' . $m[1] . '.' . $year . ', ' . $m[3];
            $s->arrival()
                ->date(strtotime($strDate));
        } elseif (preg_match("#{$this->opt($this->t('ARRIVAL'))}\:.+[ ]{5,}(\d{1,2})\s*(\D*)\n[ ]{5,}.+[ ]{5,}(\d+\:\d+)\n#", $text, $m)) {
            $date = str_replace(['Ả', 'Á'], ['ả', 'á'], strtolower($m[1] . ' ' . $m[2] . ' ' . $year . ', ' . $m[3]));
            $strDate = strtotime($this->dateStringToEnglish($date));
            $s->arrival()
                ->date($strDate);
        }

        if ($s->getDepDate() > strtotime("+6 month", $s->getArrDate())) {
            $s->arrival()
                ->date(strtotime("+1 year", $s->getArrDate()));
        }
        // DEPARTURE: ORLANDO, FL (ORLANDO INTL)                                  21 NOV. 04:29 P.M.
        $node = $this->re("#{$this->opt($this->t('DEPARTURE'))}:\s*(.*?)[-\s]+\d+\s*\w+\.?\s*\d+:\d+#su", $text);

        if (empty($node)) {
            //KHỞI HÀNH:   HO CHI MINH CITY, VN (TAN SON NHAT INTL)            18 THÁNG BẢY
            $node = $this->re("#{$this->opt($this->t('DEPARTURE'))}:\s*(.+)[ ]{5,}\d+#u", $text);
        }

        if (preg_match("#^(.+?)(?:\s+(?:TERMINAL|{$this->opt($this->t('TERMINAL'))}) (\w+))#", $node, $m)) {
            $s->departure()
                ->noCode()
                ->name(trim($m[1], ", "))
                ->terminal($m[2]);
        } else {
            $s->departure()
                ->noCode()
                ->name($node);
        }

        $node = $this->re("#{$this->opt($this->t('ARRIVAL'))}:\s*(.*?)[-\s]+\d+\s*\w+\.?\s*\d+:\d+#su", $text);

        if (empty($node)) {
            $node = $this->re("#{$this->opt($this->t('ARRIVAL'))}:\s*(.+)[ ]{5,}\d+#u", $text);
        }

        if (preg_match("#^(.+?)(?:\s+(?:TERMINAL|{$this->opt($this->t('TERMINAL'))}) (\w+))#", $node, $m)) {
            $s->arrival()
                ->noCode()
                ->name(trim($m[1], ", "))
                ->terminal($m[2]);
        } else {
            $s->arrival()
                ->noCode()
                ->name($node);
        }

        $node = $this->re("#{$this->opt($this->t('FLIGHT'))}\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)#", $text);

        if (preg_match("#([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#", $node, $m)) {
            $s->airline()
                ->name($m[1])
                ->number($m[2]);
        }

        if (!empty($operator = $this->re("#{$this->opt($this->t('OPERATED BY'))}[\s:]+(.+)#", $text))) {
            if (preg_match('/^(?<operator>.{2,})[ ]*,[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $operator, $m)) {
                // SWISS INTERNATIONAL AIR LINES, LX 1377
                if ($m['name'] !== $s->getAirlineName()) {
                    $s->airline()
                        ->operator($m['operator'])
                        ->carrierName($m['name'])
                        ->carrierNumber($m['number']);
                }
            } elseif (preg_match('/^(?<operator>.{2,})[ ]*,[ ]*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])$/s', $operator, $m)) {
                // DELTA AIR LINES, DL
                if ($m['name'] !== $s->getAirlineName()) {
                    $s->airline()
                        ->operator($m['operator'])
                        ->carrierName($m['name']);
                } else {
                    $s->airline()
                        ->operator($m['operator']);
                }
            } else {
                $s->airline()->operator($operator);
            }
        }

        $meal = $this->re("#{$this->opt($this->t('MEAL'))}[\s:]+(.+?)\s+(?:{$this->opt($this->t('NON STOP'))}|{$this->opt($this->t('LIMOUSINE CONFIRMED '))}|{$this->opt($this->t('TRANSIT OR'))}|{$this->opt($this->t('EMERGENCY CONTACT'))}|{$this->opt($this->t('APAT'))}|{$this->opt($this->t('FIRST PREPAID'))}|{$this->opt($this->t('KOSHER MEAL'))}|\n{1,}\s+{$this->opt($this->t('CHECK-IN'))})|\n{1,}\s+{$this->opt($this->t('CHILD INFORMATION'))}#s",
            $text);

        if (stripos($meal, $this->t('MEAL UNAVAILABLE FOR THIS ITINERARY')) !== false) {
            $meal = 'MEAL UNAVAILABLE FOR THIS ITINERARY';
        }

        if (stripos($meal, 'OTHER INFORMATION') !== false) {
            $meal = $this->re("/^(.+)\s+{$this->t('OTHER INFORMATION')}/", $meal);
        }

        if (preg_match_all("#{$this->opt($this->t('SEAT'))}:\s*(\d+[A-Z]+)#", $text, $m)) {
            foreach ($m[1] as $seat) {
                if (preg_match("/{$this->opt($this->t('SEAT'))}\:\s*$seat\s*{$this->opt($this->t('CONFIRMED FOR'))}\s*(.+)\n/", $text, $match)) {
                    $s->extra()
                        ->seat($seat, false, false, $match[1]);
                } else {
                    $s->extra()
                        ->seat($seat);
                }
            }
        }

        if (preg_match("#{$this->opt($this->t('CONFIRMED'))},[ ]*(.+?)[ ]*\([ ]*([A-Z]{1,2})[ ]*\)#u", $text, $m)
            || preg_match("#(?:^|[ ]{2})(ECONOMY)[ ]*\([ ]*([A-Z]{1,2})[ ]*\)$#m", $text, $m)
        ) {
            $m[1] = preg_replace("/^\s*{$this->opt($this->t('CLASS'))}:\s*/", '', $m[1]);
            $s->extra()
                ->cabin($m[1], true, true)
                ->bookingCode($m[2]);
        }

        $s->extra()
            ->aircraft($this->re("/{$this->opt($this->t('EQUIPMENT'))}[\s:]+(.+?)[ ]*$/m", $text), false, true)
            ->duration($this->re("#[) ] {$this->opt($this->t('DURATION'))}[\s:]+(\d+:\d+)\s*$#m", $text), true, true)
            ->meal($this->nice($meal), false, true);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset(self::$reBody)) {
            foreach (self::$reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        if ($this->lang === 'vi') {
            if (preg_match('#^\d+\s+(?<month>.+)\s+\d{4}#iu', $date, $m)) {
                $monthNameOriginal = $m['month'];

                if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                    return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
                }
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node,
                $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node,
                $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/^(.+),$/', '$1', $m['t']);    // 18800,		->	18800
            $m['t'] = preg_replace('/,(\d{1,2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = $m['t'];
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice(?string $str): ?string
    {
        if (!is_string($str)) {
            return null;
        }

        return trim(preg_replace('/\s+/', ' ', $str));
    }

    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->keywords as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }

        return null;
    }
}
