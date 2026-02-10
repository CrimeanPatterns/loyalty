<?php

namespace AwardWallet\Engine\despegar\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class PDF extends \TAccountChecker
{
    public $mailFiles = "despegar/it-10006529.eml, despegar/it-10030729.eml, despegar/it-10048517.eml, despegar/it-10139598.eml, despegar/it-10271164.eml, despegar/it-10761678.eml, despegar/it-10763464.eml, despegar/it-11081028.eml, despegar/it-11209596.eml, despegar/it-11307424.eml, despegar/it-11399587.eml, despegar/it-11402112.eml, despegar/it-11406542.eml, despegar/it-11513881.eml, despegar/it-11514782.eml, despegar/it-11514790.eml, despegar/it-12095768.eml, despegar/it-12307834.eml, despegar/it-12310498.eml, despegar/it-12332524.eml, despegar/it-12432280.eml, despegar/it-12445097.eml, despegar/it-12529441.eml, despegar/it-12597178.eml, despegar/it-12697007.eml, despegar/it-13010920.eml, despegar/it-13033540.eml, despegar/it-1592908.eml, despegar/it-160425481.eml, despegar/it-16484106.eml, despegar/it-1805382.eml, despegar/it-2604821.eml, despegar/it-2922129.eml, despegar/it-2924298.eml, despegar/it-3016975.eml, despegar/it-31263621.eml, despegar/it-32113659.eml, despegar/it-3441930.eml, despegar/it-38143375.eml, despegar/it-38143531.eml, despegar/it-38143670.eml, despegar/it-38235171.eml, despegar/it-40394965.eml, despegar/it-4102574.eml, despegar/it-4105682.eml, despegar/it-4397169.eml, despegar/it-4487064.eml, despegar/it-4593707.eml, despegar/it-4873691.eml, despegar/it-5048730.eml, despegar/it-5048731.eml, despegar/it-5060967.eml, despegar/it-5094448.eml, despegar/it-5413795.eml, despegar/it-55289418.eml, despegar/it-55919322.eml, despegar/it-5696665.eml, despegar/it-5775837.eml, despegar/it-57948906.eml, despegar/it-5907222.eml, despegar/it-6011563.eml, despegar/it-6078927.eml, despegar/it-6172460.eml, despegar/it-6776015.eml, despegar/it-71024631.eml, despegar/it-7145444.eml, despegar/it-7169801.eml, despegar/it-7348056.eml, despegar/it-7361414.eml, despegar/it-7376506.eml, despegar/it-7400443.eml, despegar/it-7451796.eml, despegar/it-7535758.eml, despegar/it-75449590.eml, despegar/it-7550805.eml, despegar/it-7620085.eml, despegar/it-7639193.eml, despegar/it-7756892.eml, despegar/it-79411093.eml, despegar/it-8664040.eml, despegar/it-8753566.eml, despegar/it-9900067.eml, despegar/it-9933301.eml, despegar/it-9954909.eml";

    public static $detectProvider = [
        'decolar' => [
            'from'     => "noreply@decolar.com",
            'htmlBody' => [
                'decolar.com',
            ],
            'pdfBody' => [
                'pelo app do Decolar!',
                ' 0800 721 6527',
                'www.decolar.com',
                'Use este número para administrar sua reserva com ViajaNet',
                '+55 11 4933 1246', // ViajaNet (Powered by Decolar)
                'Use este número para administrar sua reserva com Livelo',
                '  114933-1248', // Livelo (Powered by Decolar)
                '  55 11 4933-1247', // PassagensAéreas.com.br (Powered by Decolar)
            ],
        ],
        'lanpass' => [
            'from'     => 'Latam Travel',
            'htmlBody' => [
                'Equipe Latam Travel',
            ],
            'pdfBody' => [
                'Use este número para administrar sua reserva com Latam Travel',
                '(+55) 08008820233',
            ],
        ],
        'bestday' => [
            'from'     => "noreply@mail.bestday.com",
            'htmlBody' => [
                'bestday.com',
            ],
            'pdfBody' => [
                '8000624334',
                'contáctese al: 800 062 4334',
                'reservación con Best Day',
            ],
        ],
        'despegar' => [
            'from'     => "noreply@despegar.com",
            'htmlBody' => [
                'despegar.com',
                'El equipo de BBVA operado por Despegar',
            ],
            'pdfBody' => [
                'despegar.com',
                'Despegar!',
                ' tu reserva con Despegar',
                'with Despegar',
                'You can contact us at 00 1 877 893 3988',
                '0800 800 1682',
                'Ingresa a Mi Cuenta',
                'Mi Reserva',
                'Ingresa a Mis viajes para gestionar tu compra.',
                'Si lo deseas puedes contactarte con nosotros al',
                ' telefónica al 00 506 2296 9351',
                'Instrucciones y datos de contacto',
                '227123303',
                'tu reserva con BBVA operado por',
            ],
        ],
    ];

    private $detectSubject = [
        'es' => [
            '¡Genial! Tu viaje está confirmado',
            'Envío de tu ticket electrónico por tu solicitud de compra nro.',
        ],
        'pt' => ['Eba! Sua viagem está confirmada'],
        'en' => ['E-ticket for your purchase No.'],
    ];

    private $langDetectors = [
        'es' => [
            'Código de ingreso', 'Este es tu voucher prepago', 'Esta es tu confirmación de reserva',
            'Si lo deseas puedes contactarte con nosotros', 'Nro. de Reserva',
            'Tu número de reserva es', 'de solicitud de compra', 'Ingresa a Mi Reserva para gestionar tu compra',
            'Pasajeros', 'cambios y cancelaciones', 'MODALIDAD DE DEVOLUCIÓN:', 'TITULAR DE LA RESERVA',
            'Políticas de cancelación',
        ],
        'pt' => [
            'Políticas de cancelamento', 'Seu número de reserva é', 'Passageiro', 'Categoria carro',
            'Você realizou um pagamento', 'Tenho tempo limite',
        ],
        'en' => ['Booking code', 'Reservation code'],
    ];

    private $lang = '';
    private $providerCode = '';

    private $currency;

    private static $dict = [
        'es' => [
            'Reservation number:' => [
                'Número de solicitud de compra', 'Nro. de reserva:', 'Nro. de Reserva', 'Número de compra:', "Tu número de reserva es:", "Nº de reserva:",
                "N° de solicitud de compra:", "Nº de reservación:",
            ],
            'Total' => ['Total', 'TOTAL'],
            //            'Puntos Superclub:' => '',

            // Flight
            // 'segmentEnd' => '',
            // 'Stop' => '',
            'Booking code:'      => ['Código de reserva:', 'CÓDIGO DE WEB CHECK-IN', 'Código de Reserva', 'Código de reserva'],
            'Passengers'         => ['Pasajeros', 'Pasajero'],
            'E-ticket number'    => 'Número de eTicket',
            'Departs'            => ['Salida', 'Sale'],
            'Arrives'            => ['Llegada', 'LLega'],
            'Type'               => 'Clase',
            'Seats'              => 'Asientos',
            'Flight'             => 'Vuelo',
            'Duration'           => ['Duración del vuelo', 'Duración'],
            'Flight operated by' => ['Vuelo operado por', 'Operado por:'],
            'Airline:'           => 'Aerolínea:',

            // Hotel
            'Reservation details'         => 'Detalles de la reserva',
            'Accommodation check-in code' => 'Código de ingreso al',
            'td2Phrases'                  => ['Pago', 'Condiciones de la reserva', 'Políticas de cancelación'],
            'Payment'                     => 'Pago',
            'GPS coordinates'             => 'Coordenadas GPS',
            'ZIP'                         => 'CP',
            'Check-in'                    => ['Entrada', 'Check-in'],
            'Check-out'                   => ['Salida', 'Check-out'],
            'Cancellation policies'       => 'Políticas de cancelación',
            'Room'                        => ['Habitación', 'Opción'],
            //            'Total a pagar al alojamiento' => '',
            //            'TOTAL:' => '',
            //            'Base tarifaria' => '',

            // Car
            //            'Categoría del auto' => '',
            'Solicitud de Compra #:' => ['Solicitud de Compra #:', 'Nº de reserva:'],
            'Confirmation #:'        => ['Confirmation #:', 'Nº de alquiler:', 'Nº de renta:'],
            'Driver\'s Name:'        => ["CUSTOMER'S NAME:", "Driver\'s Name:", "Driver's Name:"],
            //            'Fecha y hora de retiro' => '',
            //            'Fecha y hora de devolución' => '',
            //            'Pick up and return locations' => '',
            //            'Pick up location' => '',
            //            'Company' => '',
            //            'Car Class' => '',
            //            'o similar' => '',
            //            'Voucher value' => '',
            'Pago con tarjeta de crédito' => ['Pago con tarjeta de crédito', 'Total a pagar en destino', 'Total pagado con tarjeta', 'Has realizado un pago de'],
            // rental 3
            'FECHA:'                   => 'FECHA:',
            'MODALIDAD DE RETIRO:'     => 'MODALIDAD DE RETIRO:',
            'Abierta de'               => 'Abierta de',
            'MODALIDAD DE DEVOLUCIÓN:' => 'MODALIDAD DE DEVOLUCIÓN:',

            // Transfer
            //            'Punto de encuentro' => '',
            'Su reserva' => ['Su reserva', 'Sua reserva'],
            //            'Código de reserva' => '',
            //            'Reservado por:' => '',
            //            'Desde:' => '',
            //            'Horario de recogida:' => '',
            'Flight departure' => 'Salida del vuelo',
            //            'Hasta:' => '',
            //            'Llegada' => '',
            //            'Pasajeros:' => '',
            //            'Adultos' => '',
            //            'Compañía:' => '',
            //            'Tipo:' => '',
            'Traslado para' => ['Traslado para', 'Alojamiento para'],
            'personas'      => ['personas', 'persona'],
        ],
        'pt' => [
            'earnedAwards' => ['Com esta compra você acumula', 'Com esta compra você acumulou'],

            'Reservation number:'=> [
                'Nº de reserva:', 'Número de solicitação de compra', 'N° de solicitação de compra:',
                'N° de solicitação de compra:', 'N° Reserva',
            ],
            'Total' => ['Total', 'TOTAL'],
            //            'Puntos Superclub:' => '',

            // Flight
            'segmentEnd'         => 'Lembre apresentar',
            'Stop'               => 'Parada',
            'Booking code:'      => ['CÓDIGO DE WEB CHECK-IN', 'Código de reserva', 'Código de reserva:', 'Código de Reserva:', 'CÓDIGO DE RESERVA'],
            'Passengers'         => ['Passageiros', 'Passageiro'],
            'E-ticket number'    => ['Número de eTicket', 'Número eTicket'],
            'Departs'            => ['SAI', 'Sai', 'Ida'],
            'Arrives'            => ['CHEGA', 'Chega', 'Volta'],
            'Type'               => 'Classe',
            // 'Seats' => '',
            'Flight'             => 'Voo',
            'Duration'           => ['Duração', 'Duração do voo'],
            'Flight operated by' => ['Operado por', 'Vuelo operado por', 'Voo operado pela'],
            //            'Airline:' => '',

            // Hotel
            'Reservation details'         => 'Detalhes da reserva',
            'Accommodation check-in code' => ['Código para ingressar ao hospedagem', 'Código para ingressar ao hotel'],
            'td2Phrases'                  => ['Pagamento'],
            'Payment'                     => 'Pagamento',
            'GPS coordinates'             => 'Coordenadas GPS',
            'ZIP'                         => 'CEP',
            'Check-in'                    => ['Check-in', 'Entrada'],
            'Check-out'                   => ['Check-out', 'Saida'],
            'Cancellation policies'       => 'Políticas de cancelamento',
            'Room'                        => ['Quartos', 'Quarto'],
            //            'Total a pagar al alojamiento' => '',
            //            'TOTAL:' => '',
            //            'Base tarifaria' => '',

            // Car
            'Categoría del auto'     => 'Categoria carro',
            'Solicitud de Compra #:' => 'Solicitação de Compra #:',
            'Confirmation #:'        => ['Nº de aluguel:', "Confirmação #", "Confirmation #:"],
            //            'Driver\'s Name:' => '',
            'Fecha y hora de retiro'     => 'Data e hora da retirada',
            'Fecha y hora de devolución' => 'Data e hora de retorno',
            //            'Pick up and return locations' => '',
            'Pick up location' => ['MODALIDADE DE RETIRADA:', "Pick up location"],
            'Return location'  => ['MODALIDADE DE RETORNO:', 'Return location'],
            //            'Company' => '',
            //            'Car Class' => '',
            'o similar' => 'ou similar',
            //            'Voucher value' => '',
            'Pago con tarjeta de crédito' => ['Pago com cartão de crédito', 'Total a ser pago no destino'],
            'RENTAL DAYS'                 => 'DIAS DE ALUGUEL',
            'DRIVER\'S NAME'              => 'MOTORISTA',

            // Transfer
            'Punto de encuentro'             => 'Ponto de encontro',
            'Número de solicitud de compra:' => 'Número de solicitação de compra:',
            'Su reserva'                     => 'Sua reserva',
            'Código de reserva'              => 'Código de reserva',
            'Reservado por:'                 => 'Reservado por:',
            'Desde:'                         => 'De',
            'Horario de recogida:'           => ['Horário de saída do translado:', 'Data:'],
            'Flight departure'               => 'Partida do voo',
            'Hasta:'                         => 'Até:',
            'Llegada'                        => 'Chegada do voo',
            'Pasajeros:'                     => 'Passageiros:',
            'Adultos'                        => ['Adultos', 'Adults'],
            'Compañía:'                      => 'Empresa:',
            'Tipo:'                          => 'Tipo:',
            //            'Traslado para' => '',
            //            'personas' => '',

            // Tour
            //            'Fecha de la actividad ' => '',
            /*'' => '',
            '' => '',
            '' => '',*/
        ],

        'en' => [
            'Reservation number:'=> ['Reservation number:', 'Reservartion Number', 'Booking Request No:'],
            //            'Total' => '',
            //            'Puntos Superclub:' => '',

            // Flight
            // 'segmentEnd' => '',
            // 'Stop' => '',
            'Booking code:' => ['Booking code:', 'Reservation code:', 'BOOKING CODE'],
            'Passengers'    => ['Passengers', 'Passenger'],
            //            'E-ticket number' => '',
            'Departs' => ['Departs', 'Leaves'],
            //            'Arrives' => '',
            //            'Flight operated by' => '',
            //            'Duration' => '',
            'Type' => ['Type', 'Category'],
            // 'Seats' => '',
            //            'Flight' => '',

            // Hotel
            //            'Reservation details' => '',
            //            'Accommodation check-in code' => '',
            'td2Phrases' => ['Payment'],
            //            'Payment' => '',
            //            'GPS coordinates' => '',
            //            'ZIP' => '',
            //            'Check-in' => '',
            //            'Check-out' => '',
            //            'Cancellation policies' => '',
            //            'Room' => '',
            //            'Total a pagar al alojamiento' => '',
            //            'TOTAL:' => '',
            //            'Base tarifaria' => '',

            // Car
            //            'Solicitud de Compra #:' => '',
            //            'Confirmation #:' => '',
            //            'Driver\'s Name:' => '',
            //            'Fecha y hora de retiro' => '',
            //            'Fecha y hora de devolución' => '',
            //            'Pick up and return locations' => '',
            //            'Pick up location' => '',
            //            'Company' => '',
            //            'Car Class' => '',
            //            'o similar' => '',
            "Voucher value" => ["Voucher value", "Rental value"],
            //            'Pago con tarjeta de crédito' => '',

            // Transfer
            //            'Punto de encuentro' => '',
            //            'Número de solicitud de compra:' => '',
            //            'Su reserva' => '',
            //            'Código de reserva' => '',
            //            'Reservado por:' => '',
            //            'Desde:' => '',
            //            'Horario de recogida:' => '',
            //            'Flight departure' => '',
            //            'Hasta:' => '',
            //            'Llegada' => '',
            //            'Pasajeros:' => '',
            //            'Adultos' => '',
            //            'Compañía:' => '',
            //            'Tipo:' => '',
            //            'Traslado para' => '',
            //            'personas' => '',
        ],
    ];

    private $patterns = [
        'phone' => '[+(\d][-+. \d)(]{5,}[\d)]', // +377 (93) 15 48 52    |    713.680.2992
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');
        $eventCount = 0;

        $earnedAwards = $this->http->FindSingleNode("//text()[{$this->eq($this->t('earnedAwards'))}]/following::text()[normalize-space()][1]");

        if (!empty($earnedAwards)) {
            $email->ota()
                ->earnedAwards($earnedAwards);
        }

        foreach ($pdfs as $pdf) {
            if (($body = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                if (empty($this->providerCode)) {
                    $this->assignProvider($body);
                }

                $this->assignLang($body);

                $detectedFormat = false;

                if (strpos($body, $this->t('Reservation details')) !== false) {
                    $this->logger->info("file - hotel");
                    $this->parseHotel($email, $body);
                    $detectedFormat = true;
                } elseif (strpos($body, $this->t('Categoría del auto')) !== false || strpos($body, $this->t('Car Class')) !== false) {
                    $this->logger->info("file - rental");
                    $this->parseCar($email, $body);
                    $detectedFormat = true;
                } elseif (stripos($body, $this->t('MODALIDAD DE RETIRO:')) !== false) {
                    $this->logger->info("file - rental(3)");
                    $this->parseCar3($email, $body);
                    $detectedFormat = true;
                } elseif (stripos($body, $this->t('DRIVER\'S NAME:')) !== false && stripos($body, $this->t('DIAS DE ALUGUEL')) !== false) {
                    $this->logger->info("file - rental(4)");
                    $this->parseCar4($email, $body);
                    $detectedFormat = true;
                } elseif (stripos($body, $this->t("Driver\'s Name:")) !== false) {
                    $this->logger->info("file - rental(2)");
                    $this->parseCar2($email, $body);
                    $detectedFormat = true;
                } elseif (strpos($body, $this->t('Fecha de la actividad')) !== false || strpos($body, $this->t('Tour date:')) !== false) {
                    $this->logger->info("file - event");
                    $eventCount++;
                    $this->parseEvent($email, $body);
                    $detectedFormat = true;
                } elseif (strpos($body, $this->t('Your transfer')) !== false || strpos($body, $this->t('Su servicio de Asistencia al viajero')) !== false) {
                    $this->logger->info("file - transfer(miss)");
                    $detectedFormat = true;
                    $transferSegments = $this->split("/^([ ]*{$this->preg_implode($this->t('Reservation number:'))}.+)/m", $body);

                    foreach ($transferSegments as $sText) {
                        $this->parseTransfer($email, $sText);
                    }

                    continue;
                } elseif (strpos($body, "Melhore seu seguro\n") !== false
                    || strpos($body, 'Sua assistência ao viajante') !== false
                    || strpos($body, 'Seu serviço de assistência em viagens') !== false
                    || strpos($body, 'CONDIÇÕES GERAIS DO CONTRATO DE SERVIÇOS TURISTICOS') !== false
                ) {
                    $this->logger->info("file - junk");
                    $detectedFormat = true;

                    continue;
                } else {
                    $this->logger->info("file - flight");
                    $this->parsePdf($email, $body);
                    $detectedFormat = true;
                }

                if ($detectedFormat === false) {
                    $this->logger->info("file not parsed");

                    return $email;
                }
            }
        }

        $locations = [];
        /** @var \AwardWallet\Schema\Parser\Common\Flight $it */
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight') {
                foreach ($it->getSegments() as $itSeg) {
                    if (!empty($itSeg->getDepName()) && !empty($itSeg->getDepCode())) {
                        $locations[strtolower($itSeg->getDepName())] = $itSeg->getDepCode();
                    }

                    if (!empty($itSeg->getArrName()) && !empty($itSeg->getArrCode())) {
                        $locations[strtolower($itSeg->getArrName())] = $itSeg->getArrCode();
                    }
                }
            }
        }
        $this->correctTransferLocations($email, $locations);

        $payment = $this->http->FindSingleNode('(//tr/td[position()=1 and ' . $this->eq($this->t('Total')) . ']/following-sibling::td[normalize-space(.)][last()])[1]');

        if (preg_match('/^([^\s\d][^\d]{0,5})\s+([,.\d]+)/', $payment, $matches)) {
            if (!empty($this->currency)) {
                $email->price()
                    ->currency($this->currency);
            } elseif ($matches[1] !== '$') {
                // '$' is not always means USD
                $email->price()
                    ->currency($this->currency($matches[1]));
            }
            $email->price()
                ->total($this->normalizePrice($matches[2]));
        }
        $points = $this->http->FindSingleNode('//text()[' . $this->starts($this->t('Puntos Superclub:')) . ']');

        if (preg_match("#(" . $this->t('Puntos Superclub:') . ")\s*(\d[\d,. ]+)$#", $points, $m)) {
            $email->price()
                ->spentAwards(trim($m[2]) . ' ' . trim($m[1], ':'));
        }

        if ($eventCount > 0 && $eventCount < count($email->getItineraries())) {
            /** @var \AwardWallet\Schema\Parser\Common\Event $it */
            foreach ($email->getItineraries() as $it) {
                if ($it->getType() === 'event' && !empty($it->getName()) && $it->getName() === $it->getAddress()) {
                    $email->removeItinerary($it);
                }
            }
        }

        $email->setType('PDF' . ucfirst($this->lang));

        if (empty($this->providerCode)) {
            $this->providerCode = 'despegar';
        }
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
            $textPdf = preg_replace("#[^\w\d,. \t\r\n_/+:;\"'’<>«»?~`!@\#$%^&*\[\]=\(\)\-–{}£¥₣₤₧€\$\|]#imsu", ' ', $textPdf);

            if ($this->assignProvider($textPdf) && $this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $dProvider) {
            if (empty($dProvider['from']) || stripos($headers["from"], $dProvider['from']) === false) {
                continue;
            }

            foreach ($this->detectSubject as $detectSubject) {
                foreach ($detectSubject as $dSubject) {
                    if (stripos($headers["subject"], $dSubject) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@despegar.com') !== false;
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
        return array_keys(self::$detectProvider);
    }

    private function parsePdf(Email $email, $text): void
    {
        //        $this->logger->debug('Flight Text:'."\n".print_r( $text,true));

        // Travel Agency
        $email->obtainTravelAgency();
        $regexp = "/{$this->preg_implode($this->t('Reservation number:'))}[ ]*(\d+)(\s{2,}|\n)/u";
        $confTA = $this->re($regexp, $text);

        if (empty($confTA)) {
            $regexp = "/(?:^.*|.{40,}[ ]{5,}){$this->preg_implode($this->t('Reservation number:'))}\n.{40,}[ ]{5,}[ ]*(\d+)\n/u";
            $confTA = $this->re($regexp, $text);
        }

        if (empty($confTA)) {
            $regexp = "/(?:^|\n) *.+?[ ]{2,}{$this->preg_implode($this->t('Reservation number:'))}[ ]{2,}.+\n +.+?[ ]{2,}(\d{5,})[ ]{2,}.+\n/u";
            $confTA = $this->re($regexp, $text);
        }

        if (empty($confTA)) {
            // при переносе тестовки на две строки
            $regexp = "/(?:^|\n)(?:.+?[ ]{2,})?" . str_replace(' ', '(?:(?:[ ]{2,}.*)?\n(?:.*[ ]{2,})| ){0,2}', $this->preg_implode($this->t('Reservation number:'))) . "(?:[ ]{2,}.*)?\n +.+?[ ]{2,}(\d{5,})(?:[ ]{2,}|\n)/u";
            $confTA = $this->re($regexp, $text);
        }

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        $f = $email->add()->flight();

        $segmentType = '';

        $f->general()
            ->noConfirmation();

        // Travellers
        $regexp = "/\n\s*" . $this->preg_implode($this->t('Passengers')) . "(?:\s*.*\n)?.*[ ]{2,}" . $this->preg_implode($this->t('E-ticket number')) . ".*\n+((?:.*\n+){1,20}?)(?:\n{3,}| *\w+:\s+| *\S.+\n{1,4} *.*\b" . $this->preg_implode($this->t('Departs')) . "\s*)/iu";

        $travellers = [];
        $tickets = [];
        $travellersText = $this->re($regexp, $text);

        if (!empty($travellersText)) {
            $travellersTable = $this->splitCols($travellersText);

            if (count($travellersTable) == 1) {
                $travellersTable = $this->splitCols($travellersText, [0, 80, 120]);
            }

            if (!empty($travellersTable[0])) {
                $travellers = array_filter(explode("\n", $travellersTable[0]),
                    function ($v) { if (preg_match("/^[[:alpha:] \-]+,[[:alpha:] \-]+$/u", $v)) { return true; } else { return false; }}
                );
            }

            if (count($travellersTable) > 1) {
                $travellersTable[count($travellersTable) - 1] = preg_replace('/\s+\//mu', '', $travellersTable[count($travellersTable) - 1]);
                $tickets = array_filter(explode("\n", $travellersTable[count($travellersTable) - 1]),
                    function ($v) { if (preg_match("/^\s*\/?(\d{3}\-?\d{10}(?:[\/\-]\d{2})?)$/", $v)) { return true; } else { return false; }}
                );
            }
        } elseif (!empty($this->re("/(?:^|\n) *(" . $this->preg_implode($this->t('Passengers')) . "):[ ]{3,}/u", $text))
            && !empty($this->re("/\n *(" . $this->preg_implode($this->t('E-ticket number')) . "):[ ]+/u", $text))) {
            $segmentType = '3';
            $travellersText = $this->re("/(?:^|\n) *( *" . $this->preg_implode($this->t('Passengers')) . ":[ ]{3,}(?:.*\n)+?) {0,10}\S/u", $text);
            $travellersText = preg_replace(["/^(.{50,}?) {5,}\S.+/um", "/^\s*" . $this->preg_implode($this->t('Passengers')) . ":\s*/"], ['$1', ''], $travellersText);
            $travellersText = preg_replace("/\s+-\s+\d{5,}(?:\n|$)/u", "\n", $travellersText);
            $travellers = array_filter(array_map('trim', array_filter(explode("\n", $travellersText))));

            $ticketsText = $this->re("/\n( *" . $this->preg_implode($this->t('E-ticket number')) . ":[ ]+(?:.*\n)+?)(?: {0,10}\S|\n{3,})/u", $text);
            $ticketsText = preg_replace(["/^(.{50,}) {5,}\S.+/um", "/^\s*" . $this->preg_implode($this->t('E-ticket number')) . ":\s*/"], ['$1', ''], $ticketsText);
            $tickets = array_filter(array_map('trim', explode("\n", $ticketsText)), function ($v) {return (preg_match("/^\d{3}-?\d{10}(?:[\/\-]\d{2})?$/", $v)) ? true : false; });

            $codesText = $this->re("/\n *" . $this->preg_implode($this->t('Booking code:')) . ":?([ ]*(?:.*\n)+?)(?: {0,10}\S|\n{3,})/u", $text);

            if (preg_match_all("/\s+(?<from>[A-Z]{3})\s+-\s+(?<to>[A-Z]{3}):\s+(?<code>[A-Z\d]{5,7})\b/", $codesText, $m)) {
                foreach ($m[0] as $i => $value) {
                    $airlinesCodes[$m['from'][$i] . $m['to'][$i]] = $m['code'][$i];
                }
            } elseif (preg_match("/^\s*[A-Z\d]{5,7}\s*$/", $codesText)) {
                $airlinesCodes['all'] = trim($codesText);
            }
        } elseif (empty($this->re("/\s+(" . $this->preg_implode($this->t('E-ticket number')) . ")/u", $text))
                && !empty($this->re("/(?:^|\n) *(" . $this->preg_implode($this->t('Passengers')) . ") *\([^\)\n]+\):[ ]{3,}.*[\w ]+\w:\n/u", $text))) {
            $regexp = "/\n\s*" . $this->preg_implode($this->t('Passengers')) . " *\([^\)\n]+\):[ ]{3,}.*[\w ]+\w:\n+((?:.*\n+){1,20}?)(?:\n{3,}| *\w+:\s+|\s*\S.+\n\s*.*\b" . $this->preg_implode($this->t('Departs')) . "\s*)/iu";

            $travellers = [];
            $tickets = [];
            $travellersText = $this->re($regexp, $text);

            if (!empty($travellersText)) {
                $travellersTable = $this->splitCols($travellersText);

                if (!empty($travellersTable[0])) {
                    $travellers = array_filter(explode("\n", $travellersTable[0]),
                        function ($v) {
                            if (preg_match("/^[[:alpha:] \-]+,[[:alpha:] \-]+$/u", $v)) {
                                return true;
                            } else {
                                return false;
                            }
                        }
                    );
                }

                if (count($travellersTable) > 1) {
                    $tickets = array_filter(explode("\n", $travellersTable[count($travellersTable) - 1]),
                        function ($v) {
                            if (preg_match("/^(\d{3}\-?\d{10}(?:[\/\-]\d{2})?)$/", $v)) {
                                return true;
                            } else {
                                return false;
                            }
                        }
                    );
                }
            }
        } elseif (preg_match("/\s*{$this->preg_implode($this->t('Passengers'))}.+\n(\D+)\n\n\s*{$this->preg_implode($this->t('Airline:'))}/", $text, $m)) {
            $travellers = explode(',', $m[1]);
        }

        $f->general()->travellers(array_filter(array_unique($travellers)), true);

        // Issued
        if (!empty($tickets)) {
            $f->issued()->tickets($tickets, false);
        }

        if ($segmentType === '3') {
            $this->parseFlight3($f, $text, $airlinesCodes ?? []);
        } elseif (preg_match("/" . $this->preg_implode($this->t('Departs')) . "[ ]{2,}" . $this->preg_implode($this->t('Arrives')) . "\n/iu", $text)) {
            // LATAM Airlines        SAI                                                           CHEGA
            // Group                 Sun. 06 Dec. 2020 18:10                                       Sun. 06 Dec. 2020 19:35
            // Voo 3252                                                      Duração

            $segmentType = '2';
            $this->parseFlight2($f, $text);
        } elseif (preg_match("/" . $this->preg_implode($this->t('Departs')) . " *\S+.*[ ]{2,}" . $this->preg_implode($this->t('Arrives')) . " *\S+.*\n/iu", $text)) {
            //IDA
            //                    Salida Mar 17 Jul. 2018                         Llegada Mar 17 Jul. 2018                     Duración:
            //Interjet                                                                                                           1h 40m
            //4O 2520
            //                    MEX 11:10                                       MID 12:50
            $segmentType = '1';
            $this->parseFlight1($f, $text);
        } elseif (preg_match("/" . $this->preg_implode($this->t('Departs')) . "[ ]{4,}" . $this->preg_implode($this->t('Arrives')) . "[ ]{4,}\S+.*\n/iu", $text)) {
            // TRAMO 4: Sábado 26 de marzo de 2016
            //                         Salida                                            Llegada                                            Duración:
            //         LAN             ADZ 19:30                                         BOG 21:35                                          2h 05m
            //         LA 3047                                                                                                              Clase:
            //   Código de reserva:    Isla de San Andrés (CO)                           Bogotá (CO)                                        Económica
            $segmentType = '4';
            $this->parseFlight4($f, $text);
        }
        $this->logger->debug('Segment type ' . $segmentType);
    }

    private function parseFlight1(Flight $f, $text)
    {
        $segments = $this->split("/\n(.*  " . $this->preg_implode($this->t('Departs')) . " .+[ ]{2,}" . $this->preg_implode($this->t('Arrives')) . " .+\n)/ui", $text);
//        $this->logger->debug('$segments type 1 = '.print_r( $segments,true));

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("/^((.*\n){5,}?)(?:{$this->preg_implode($this->t('segmentEnd'))}|[ ]+\d{1,3}[ ]*{$this->preg_implode($this->t('Stop'))}|\n{2,})/", $stext, $m)) {
                $stext = $m[1];
            }
//            $this->logger->debug('$stext = '."\n".print_r( $stext,true));

            $tablePos = [0];

            if (!preg_match_all("/^(.+?[ ]{2})[A-Z]{3}[ ]+\d{1,2}:\d{2}/m", $stext, $depMatches)
                || count($depMatches[1]) !== 1
            ) {
                $this->logger->debug('Wrong segment table! (1)');

                continue;
            } else {
                $tablePos[] = mb_strlen($depMatches[1][0]);
            }
            $table = $this->splitCols($stext, $tablePos);

            if (count($table) !== 2) {
                $this->logger->debug('Wrong segment table! (2)');

                continue;
            }

            if (preg_match("/\n[ ]*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d+)\n/", $table[0], $m)) {
                $s->airline()->name($m[1])->number($m[2]);
            }

            if (preg_match("/\n[ ]*{$this->preg_implode($this->t("Booking code:"))}.*\n+[ ]*([A-Z\d]{5,7})(?:[ ]{2}|\n|$)/", $table[0], $m)) {
                $s->airline()->confirmation($m[1]);
            }

            if (preg_match("/^([\s\S]+?)\n+\s*{$this->preg_implode($this->t("Flight operated by"))}[ ]*(.+?)[ ]*\./", $table[1], $m)) {
                $table[1] = $m[1];
                $s->airline()->operator($m[2]);
            }

            $headerPos = $this->rowColsPos($this->inOneRow($table[1]));

            if (count($headerPos) !== 4) {
                $headerPos = $this->rowColsPos($this->inOneRow($this->re("/^((?:.*\n){1,4})/", $table[1])));
            }

            if (preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Duration'))}[: ]*$/m", $table[1], $m)
                && preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Type'))}[: ]*$/m", $table[1], $m2)
                && preg_match("/^(.+[ ]{2}){$this->preg_implode($this->t('Seats'))}[: ]*$/m", $table[1], $m3)
            ) {
                $headerPos[2] = min([mb_strlen($m[1]), mb_strlen($m2[1]), mb_strlen($m3[1])]);
            }
            $headerPos[0] = 0;

            $table = $this->splitCols($table[1], $headerPos);

            if (count($table) === 4 && preg_match("/^\s*" . $this->preg_implode($this->t('Duration')) . ":?\s*$/", $table[3])) {
                $table[2] = $table[3] . $table[2];
                unset($table[3]);
            }
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) !== 3) {
                $this->logger->debug('error parse table');

                return $f;
            }

            // Column 1
            //SAI
            //Sat. 05 Dec. 2020 09:15
            //VIX Vitória (BR)
            //Aeroporto Eurico Sales
            if (preg_match("/^\s*" . $this->preg_implode($this->t('Departs')) . " *(?<date>.+)\n\s*(?<code>[A-Z]{3}) (?<time>\d+:\d+.*)\n+\s*(?<name>[\s\S]{3,}?)\s*$/", $table[0], $m)) {
                $s->departure()
                    ->date($this->normalizeDateTime($m['date'] . ' ' . $m['time']))
                    ->code($m['code'])
                    ->name(preg_replace(['/^[\s\S]{2,}?\s*\(\s*[A-Z]{2}\s*\)\s*([\s\S]{3,})$/', '/\s+/'], ['$1', ' '], $m['name']))
                ;
            }

            // Column 2
            if (preg_match("/^\s*" . $this->preg_implode($this->t('Arrives')) . " *(?<date>.+)\n\s*(?<code>[A-Z]{3}) (?<time>\d+:\d+.*)\n+\s*(?<name>[\s\S]{3,}?)\s*$/", $table[1], $m)) {
                $m['name'] = preg_replace('/^(.{9,})[ ]{2}.+$/m', '$1', $m['name']);
                $s->arrival()
                    ->date($this->normalizeDateTime($m['date'] . ' ' . $m['time']))
                    ->code($m['code'])
                    ->name(preg_replace(['/^[\s\S]{2,}?\s*\(\s*[A-Z]{2}\s*\)\s*([\s\S]{3,})$/', '/\s+/'], ['$1', ' '], $m['name']))
                ;
            }

            // Column 3
            if (preg_match("/" . $this->preg_implode($this->t('Duration')) . ":\s*(\d.+)\n/", $table[2], $m)) {
                $s->extra()
                    ->duration($m[1])
                ;
            }

            if (preg_match("/" . $this->preg_implode($this->t('Type')) . ":\s*(.+)\n/", $table[2], $m)) {
                $s->extra()
                    ->cabin($m[1])
                ;
            }
        }

        return $f;
    }

    private function parseFlight2(Flight $f, $text)
    {
        $segments = $this->split("/\n(.*  " . $this->preg_implode($this->t('Departs')) . "[ ]{2,}" . $this->preg_implode($this->t('Arrives')) . "\n)/ui", $text);
//        $this->logger->debug('$segments Type 2= '.print_r( $segments,true));

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("/^((.*\n){5,}?)\n{2,}/", $stext, $m)) {
                $stext = $m[1];
            }

//            $this->logger->debug('$stext = '."\n".print_r( $stext,true));

            $headerPos = $this->rowColsPos($this->inOneRow($stext));

            if (count($headerPos) !== 4) {
                $headerPos = $this->rowColsPos($this->inOneRow($this->re("/^((?:.*\n){1,4})/", $stext)));
            }
            $headerPos[0] = 0;
            $table = $this->splitCols($stext, $headerPos);

//            $this->logger->debug('$table = '.print_r( $table,true));
            if (count($table) !== 4) {
                $this->logger->debug('error parse table');

                return $f;
            }
            // Column 1
            if (preg_match("/([\s\S]+)\n\s*" . $this->preg_implode($this->t("Flight")) . " *(\d{1,5})\s+/", $table[0], $m)) {
                $s->airline()
                    ->name(trim(preg_replace("/\s+/", ' ', $m[1])))
                    ->number($m[2])
                ;
            }

            if (preg_match("/\n\s*" . $this->preg_implode($this->t("Flight operated by")) . " *(.+)/", $table[0], $m)) {
                $s->airline()
                    ->operator($m[1])
                ;
            }

            if (preg_match("/\n\s*" . $this->preg_implode($this->t("Booking code:")) . "\s+([A-Z\d]{5,7})(?:\n|$)/", $table[0], $m)) {
                $s->airline()
                    ->confirmation($m[1])
                ;
            }

            // Column 2
//            SAI
//            Sat. 05 Dec. 2020 09:15
//            VIX Vitória (BR)
//            Aeroporto Eurico Sales
            if (preg_match("/^.+\n\s*(?<date>.+)\n\s*(?<code>[A-Z]{3}) (?<name>[\s\S]+)/", $table[1], $m)) {
                $s->departure()
                    ->date($this->normalizeDateTime($m['date']))
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            }
            // Column 3
            if (preg_match("/^\s*.+\n\s*(\d.+)\n\s*(.+)\s*$/", $table[2], $m)) {
                $s->extra()
                    ->duration($m[1])
                    ->cabin($m[2])
                ;
            }

            // Column 4
            if (preg_match("/^.+\n\s*(?<date>.+)\n\s*(?<code>[A-Z]{3}) (?<name>[\s\S]+)/", $table[3], $m)) {
                $s->arrival()
                    ->date($this->normalizeDateTime($m['date']))
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            }
        }

        return $f;
    }

    private function parseFlight3(Flight $f, $text, array $airlinesCodes)
    {
        $segments = $this->split("/\n([ ]{0,10}(?:[[:alpha:]]+(?: \d)?.*\b\d{4}\b.*\n+)?.*  " . $this->preg_implode($this->t('Departs')) . "[ ]{2,}.* " . $this->preg_implode($this->t('Arrives')) . ".*\n)/ui", $text);
//        $this->logger->debug('$segments type 3= '.print_r( $segments,true));

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("/^[ ]{0,10}[[:alpha:]]+(?: \d)? *(.*\d{4}.*?)[ ]{2,}.*\n+([\s\S]+)/u", $stext, $m)) {
                $date = $m[1];
                $stext = $m[2];
//                $this->logger->debug('$date = '.print_r( $date,true));
            }

            if (preg_match("/^(?:.*\n){4,}?\n{2,}([\S\s]+)/", $stext, $m)) {
                $additionInfo = $m[1];
            }

            if (preg_match("/^((.*\n){4,}?)\n{2,}/", $stext, $m)) {
                $stext = $m[1];
            }
//            $this->logger->debug('$stext = '."\n".print_r( $stext,true));

            $headerPos = $this->rowColsPos($this->inOneRow($this->re("/^([\s\S]+\s{2,}[A-Z]{3}\s{2,}[A-Z]{3}\s+)/",
                $stext)));

            if (count($headerPos) == 5) {
                $headerPos[2] = $headerPos[3];
                unset($headerPos[3], $headerPos[4]);
            }
            $headerPos[0] = 0;

            if (count($headerPos) !== 3 && preg_match("/^(?<col3>(?<col2>.*  )" . $this->preg_implode($this->t('Departs')) . "[ ]{2,}.* )" . $this->preg_implode($this->t('Arrives')) . ".*\n/ui", $stext, $m)) {
                $headerPos = [0, mb_strlen($m['col2']), mb_strlen($m['col3'])];
            }
            $table = $this->splitCols($stext, $headerPos);
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) !== 3) {
                $this->logger->debug('error parse table');

                return $f;
            }

            // Column 1
            if (preg_match("/\n[ ]*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d+)\n/", $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            } elseif (preg_match("/^([\s\S]+?)\s+(\d{1,5})\n/", $table[0], $m)) {
                $s->airline()
                    ->name(trim(preg_replace("/\s+/", ' ', $m[1])))
                    ->number($m[2])
                ;
            }

            // Column 2
            //Sale
            //13:35 HS ,
            //26 Feb 2016
            //BCN
            //AEROPUERTO EL PRAT DE
            //LLOBREGAT, BARCELONA
            if (preg_match("/^.+\n\s*(?<time>.+),\s*\n(?<date>.+)\n\s*(?<code>[A-Z]{3})\s+(?<name>[\s\S]+)/u", $table[1], $m)) {
                $s->departure()
                    ->date($this->normalizeDateTime($m['date'] . ' ' . $m['time']))
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            } elseif (preg_match("/^\w+ {2,}(?<code>[A-Z]{3})\n\s*(?<time>.+?)(?:, +|[ ]{2,})(?<name1>.*)\n(?<date>.*?\d{4}.*?)(?:\s{2,}|\n)(?<name2>[\s\S]+)/u", $table[1], $m)) {
                //Sale                                       COR
                //15:45 HS ,    AEROPUERTO PAJAS BLANCAS,
                //18 Mar 2016
                $s->departure()
                    ->date($this->normalizeDateTime($m['date'] . ' ' . $m['time']))
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name1'] . ' ' . $m['name2'])))
                ;
            } elseif (preg_match("/^\w+ {2,}(?<code>[A-Z]{3})\n\s*(?<time>.+?)(?:, +|[ ]{2,})(?<name>[\s\S]+)/u", $table[1], $m)) {
                //Sai                                  GRU
                //02:30 HS   AEROPUERTO INTERNACIONAL
                //GUARULHOS, SAN PABLO
                $s->departure()
                    ->date((!empty($date)) ? $this->normalizeDateTime($date . ' ' . $m['time']) : null)
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            }
            // Column 3
            if (preg_match("/^.+\n\s*(?<time>.+),\s*\n(?<date>.+)\n\s*(?<code>[A-Z]{3})\s+(?<name>[\s\S]+)/u", $table[2], $m)) {
                $s->arrival()
                    ->date($this->normalizeDateTime($m['date'] . ' ' . $m['time']))
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            } elseif (preg_match("/^\w+ {2,}(?<code>[A-Z]{3})\n\s*(?<time>.+?)(?:, +|[ ]{2,})(?<name1>.*)\n(?<date>.*?\d{4}.*?)(?:\s{2,}|\n)(?<name2>[\s\S]+)/u", $table[2], $m)) {
                $s->arrival()
                    ->date($this->normalizeDateTime($m['date'] . ' ' . $m['time']))
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', trim($m['name1'] . ' ' . $m['name2']))))
                ;
            } elseif (preg_match("/^\w+ {2,}(?<code>[A-Z]{3})\n\s*(?<time>.+?)(?:, +|[ ]{2,})(?<name>[\s\S]+)/u", $table[2], $m)) {
                $s->arrival()
                    ->date((!empty($date)) ? $this->normalizeDateTime($date . ' ' . $m['time']) : null)
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            }

            if (preg_match("/\s+" . $this->preg_implode($this->t("Flight operated by")) . " *(.+)/", $additionInfo, $m)) {
                $s->airline()
                    ->operator($m[1])
                ;
            }

            if (!empty($airlinesCodes) && !empty($s->getDepCode()) && !empty($s->getArrCode())
                    && !empty($airlinesCodes[$s->getDepCode() . $s->getArrCode()])) {
                $s->airline()
                    ->confirmation($airlinesCodes[$s->getDepCode() . $s->getArrCode()])
                ;
            } elseif (!empty($airlinesCodes['all'])) {
                $s->airline()
                    ->confirmation($airlinesCodes['all']);
            }

            if (preg_match("/(?:^|\n)\s*" . $this->preg_implode($this->t("Duration")) . ": ?(.+?)(?:\s{2,}|\n|$)/", $additionInfo, $m)) {
                $s->extra()
                    ->duration($m[1])
                ;
            }

            if (preg_match("/(?:^\s*|\s{3,})" . $this->preg_implode($this->t("Type")) . ": ?(.+?)(?:\s{2,}|\n|$)/", $additionInfo, $m)) {
                $s->extra()
                    ->cabin($m[1])
                ;
            }
        }

        return $f;
    }

    private function parseFlight4(Flight $f, $text)
    {
        $segments = $this->split("/\n([ ]{0,10}(?:[[:alpha:]]+(?: \d)?:.*\d{4}\n+)? +.*  " . $this->preg_implode($this->t('Departs')) . " [ ]{4,}" . $this->preg_implode($this->t('Arrives')) . "[ ]{4,}.+\n)/ui", $text);
//        $this->logger->debug('$segments type 4= '.print_r( $segments,true));

        foreach ($segments as $stext) {
            $s = $f->addSegment();

            if (preg_match("/^[ ]{0,10}[[:alpha:]]+(?: \d)?:(.*\d{4})\n+([\s\S]+)/", $stext, $m)) {
                $date = $m[1];
                $stext = $m[2];
            }

            if (preg_match("/^((.*\n){5,}?)\n{2,}/", $stext, $m)) {
                $stext = $m[1];
            }
//            $this->logger->debug('$stext = '."\n".print_r( $stext,true));

            $headerPos = $this->rowColsPos($this->inOneRow($stext));

            if (count($headerPos) !== 4) {
                $headerPos = $this->rowColsPos($this->inOneRow($this->re("/^((?:.*\n){1,4})/", $stext)));
            }
            $headerPos[0] = 0;

            $table = $this->splitCols($stext, $headerPos);
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (count($table) !== 4) {
                $this->logger->debug('error parse table');

                return $f;
            }

            // Column 1
            if (preg_match("/\n[ ]*([A-Z\d][A-Z]|[A-Z][A-Z\d])[ ]*(\d+)\n/", $table[0], $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            if (preg_match("/\n\s*" . $this->preg_implode($this->t("Flight operated by")) . " *(.+?)(?:\n|\.)/", $table[0], $m)) {
                $s->airline()
                    ->operator($m[1])
                ;
            }

            if (preg_match("/\n\s*" . $this->preg_implode($this->t("Booking code:")) . "\s+([A-Z\d]{5,7})(?:\n|$)/", $table[0], $m)) {
                $s->airline()
                    ->confirmation($m[1])
                ;
            }

            // Column 2
//            SAI
//            Sat. 05 Dec. 2020 09:15
//            VIX Vitória (BR)
//            Aeroporto Eurico Sales
            if (preg_match("/^\s*" . $this->preg_implode($this->t('Departs')) . "\n\s*(?<code>[A-Z]{3}) (?<time>\d+:\d+.*)\n\s*(?<name>[\s\S]+)/", $table[1], $m)) {
                $s->departure()
                    ->date(($date) ? $this->normalizeDateTime($date . ' ' . $m['time']) : null)
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            }

            // Column 3
            if (preg_match("/^\s*" . $this->preg_implode($this->t('Arrives')) . "\n\s*(?<code>[A-Z]{3}) (?<time>\d+:\d+.*)\n\s*(?<name>[\s\S]+)/", $table[2], $m)) {
                $s->arrival()
                    ->date(($date) ? $this->normalizeDateTime($date . ' ' . $m['time']) : null)
                    ->code($m['code'])
                    ->name(trim(preg_replace("/\s+/", ' ', $m['name'])))
                ;
            }

            // Column 4
            if (preg_match("/" . $this->preg_implode($this->t('Duration')) . ":\s*(\d.+)\n/", $table[3], $m)) {
                $s->extra()
                    ->duration($m[1])
                ;
            }

            if (preg_match("/" . $this->preg_implode($this->t('Type')) . ":\s*(.+)\n/", $table[3], $m)) {
                $s->extra()
                    ->cabin($m[1])
                ;
            }
        }

        return $f;
    }

    private function parseHotel(Email $email, $text): void
    {
        // examples: it-40394965.eml

//        $this->logger->debug('Hotel Text:'."\n".print_r( $text,true));

        // Travel Agency
        $email->obtainTravelAgency();

        $regexp = "/{$this->opt($this->t('Reservation number:'))}[ ]+(\d+)(\s{2,}|\n)/u";
        $confTA = $this->re($regexp, $text);

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        $h = $email->add()->hotel();

        // General
        $confirmation = $this->re("#{$this->opt($this->t('Accommodation check-in code'))}\s*\w*:\n?.*?\s+([-A-Z\d]{5,})( *\/.*)?\n#u", $text);
        $h->general()->travellers(array_filter($this->res("/{$this->opt($this->t('Room'))} \d+[^\n]*\n(?: {50,}.*\n){0,2}.*?;\s*(.+?),/", $text)));

        // Hotel
        $name = $address = $phone = null;

        $tablePos = [0];

        if (preg_match("/\n(.+?[ ]{2}){$this->opt($this->t('td2Phrases'))}\n/", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($text, $tablePos);

        foreach ($table as $i => &$td) {
            $td = preg_replace("/^(?:.+\n{1,2}){0,2}?\n{3,}/", '', $td);

            if ($i === 0) {
                $td = preg_replace([
                    "/^(.*?)\n+[ ]*{$this->preg_implode($this->t('ZIP'))}:.*$/s",
                    "/\n[ ]*E[- ]*mail ?[:]* ?\S+@\S+$/im",
                ], [
                    '$1',
                    '',
                ], $td);
            }
        }

        if (count($table) && (preg_match("/^(?<name>(?:.{2,}\n{1,2}){1,2})\n{0,4}(?<address>(?:\n{1,2}.{2,}){1,3}?)(?:\n+[ ]*(?<phone>{$this->patterns['phone']}))(?:\n|$)/", $table[0], $m)
            || preg_match("/^(?<name>(?:.{2,}\n{1,2}){1,2})\n{0,4}(?<address>(?:\n{1,2}.{2,}){1,3})(?:\n|$)/", $table[0], $m))
        ) {
            $name = preg_replace('/\s+/', ' ', trim($m['name']));

            if (preg_match("/[[:alpha:]]/u", $m['address']) > 0) {
                $address = preg_replace('/\s+/', ' ', trim($m['address']));
            }

            if (!empty($m['phone'])) {
                $phone = $m['phone'];
            }
        } else {
            // parse from HTML
            $xpathHotelName = "//*[{$this->eq($this->t('Sobre tu alojamiento'))}]/following::text()[normalize-space()][1]/ancestor::p[1]";
            $name_temp = $this->http->FindSingleNode($xpathHotelName);
            $address_temp = $this->http->FindSingleNode($xpathHotelName . "/following::tr[ count(*)=2 and *[1][descendant::img and normalize-space()=''] and *[2][normalize-space()] ][1]/*[2]");

            if (count($table) && preg_match("/^[ ]*{$this->preg_implode($name_temp)}\n/", $table[0])) {
                $name = $name_temp;
            }

            if (count($table) && preg_match("/^[ ]*{$this->preg_implode($address_temp)}\n/", $table[0])) {
                $address = $address_temp;
            }
        }

        if (!$address) {
            $address = $this->re("/{$this->preg_implode($this->t('GPS coordinates'))}:\s*(.*?)(?:\s{2}|\n)/", $text);
        }

        if (!$phone && count($table) && preg_match("/^[ ]{0,10}(?<phone>{$this->patterns['phone']})(?:[ ]{2,}|$)/m", $table[0], $m) && strlen(str_replace(' ', '', $m['phone'])) > 5) {
            $phone = $m['phone'];
        }

        $h->hotel()
            ->name($name)
            ->address($address)
            ->phone($phone, false, true)
        ;

        if (!$confirmation && $name && $address) {
            // parse from HTML
            $confirmation = $this->http->FindSingleNode("//node()[{$this->eq($name)}]/ancestor::*[ descendant::node()[{$this->eq($address)}] ][1]/descendant::tr[{$this->starts($this->t('Accommodation check-in code'))}]/following::tr[not(.//tr) and normalize-space()][1]", null, true, "/^[-A-Z\d]{5,}$/");
        }

        if (!$confirmation && preg_match("/^[ ]*{$this->preg_implode($this->t('Reservation number:'))} ?[:]* ?[-_A-Z\d]{5,}[ ]{2,}E[- ]*mail ?[:]* ?(?:\S+@\S+)?\n/im", $text) > 0
            && !preg_match("/{$this->opt($this->t('Accommodation check-in code'))}/", $text)
            && $this->http->XPath->query("//*[{$this->contains($this->t('Accommodation check-in code'))}]")->length === 0
        ) {
            $h->general()->noConfirmation();
        } else {
            $h->general()->confirmation($confirmation);
        }

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDateTime($this->re("#{$this->opt($this->t('Check-in'))}:\s+(.*?)(?:\s{2,}|\n)#", $text)))
            ->checkOut($this->normalizeDateTime($this->re("#{$this->opt($this->t('Check-out'))}:\s+(.*?)(\s{2,}|\n)#", $text)))
            ->guests(array_sum($this->res("#(\d+) adult#", $text)))
            ->kids(array_sum($this->res("#(\d+) menor#", $text) ?? []), true, true)
            ->rooms($this->re("/\b(\d{1,3})[ ]{0,2}(?:room|habitaci(?:ó|o)n|quarto)/i", $text), false, true)
        ;

        $col = mb_strlen($this->re("#(.*[ ]{3,})" . $this->preg_implode($this->t('Cancellation policies')) . "#", $text));

        if ($col > 20) {
            $table = $this->SplitCols($text, [0, $col - 1]);

            if (isset($table[1]) && preg_match("#" . $this->preg_implode($this->t('Cancellation policies')) . "\s+(.+(?:\n.*){0,20}?)(\n\n\n|$)#", $table[1], $m)) {
                $h->general()->cancellation(preg_replace("#\s+#", ' ', $m[1]));
                $this->detectDeadLine($h);
            }
        }

        // Rooms
        $types = $this->res("/{$this->opt($this->t('Room'))} \d+[^\n]*\n(.*?);/", $text);

        if (count($types) == $h->getRoomsCount()) {
            foreach ($types as $type) {
                $h->addRoom()->setType($type);
            }
        }

        // Price
        if (preg_match_all('/\n\s*' . $this->preg_implode($this->t("Total a pagar al alojamiento")) . '[ ]*([^\s\d][^\d]{0,5})[ ]+(\d[,.\d]+)/', $text, $priceMatches)
            || preg_match_all('/\n\s*' . $this->preg_implode($this->t("TOTAL:")) . '(?:.*\/)?[ ]*([^\s\d][^\d]{0,5})[ ]+(\d[,.\d]+)/', $text, $priceMatches)
        ) {
            $total = 0.0;

            foreach ($priceMatches[2] as $value) {
                $total += $this->normalizePrice($value);
            }

            $h->price()
                ->total($total)
                ->currency($this->currency(array_shift($priceMatches[1])));
        }

        if (preg_match('/\n\s*' . $this->preg_implode($this->t("Base tarifaria")) . '[ ]*([^\s\d][^\d]{0,5})[ ]+(\d[,.\d]+)/', $text, $matches)) {
            if ($h->getPrice() && ($h->getPrice()->getCurrencyCode() === $this->currency(trim($matches[1])))) {
                $h->price()
                    ->cost($this->normalizePrice($matches[2]));
            }
        }
    }

    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h): void
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/^Você pode cancelar ou realizar alterações sem custo por parte da hospedagem até (?<date>\d{1,2}\/\d{2}\/\d{4} [\D ]+ \d{1,2}h\d{2}(?:\s*[ap]m)?)\./iu", $cancellationText, $m) // pt
            || preg_match("/^Você pode cancelar até (?<date>\d{1,2}\/\d{2}\/\d{4} [\D ]+ \d{1,2}h\d{2}(?:\s*[ap]m)?)\..{10,}/iu", $cancellationText, $m) // pt
            || preg_match("/^Puedes cancelar o realizar cambios sin cargo por parte del alojamiento hasta el (?<date>\d{1,2}\/\d{2}\/\d{4} [\D ]+ \d{1,2}:\d{2}(?:\s*[ap]m)?)\./iu", $cancellationText, $m) // es
            || preg_match("/^CXL BY (?<date>\d{1,2}-\w+-\d{2} \d+[ap]m)$/i", $cancellationText, $m) // en
        ) {
            $h->booked()->deadline($this->normalizeDateTime($m['date']));
        } elseif (preg_match("/^A tarifa selecionada não permite realizar alterações ou cancelamentos\.?/i", $cancellationText) // pt
        ) {
            $h->booked()->nonRefundable();
        }
    }

    private function parseCar(Email $email, $text)
    {
//        $this->logger->debug('Car Text:'."\n".print_r( $text,true));

        // Travel Agency
        $email->obtainTravelAgency();
        $confTA = $this->re("/{$this->preg_implode($this->t('Solicitud de Compra #:'))}[ ]*(\d+)(\s{2,}|\n)/u", $text);

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        $r = $email->add()->rental();

        //  General
        $r->general()
            ->confirmation($this->re("/\b" . $this->preg_implode($this->t('Confirmation #:')) . "(?:[ ]{5,}\D+.*\n +)? *([A-Z\d]{5,})(?:\s{2,}|\n)/", $text))
            ->traveller($this->re("/\b" . $this->preg_implode($this->t('Driver\'s Name:')) . " ?(.+?)(?:\s{2,}|\n)/i", $text), true)
        ;

        // Pick up
        $r->pickup()
            ->date($this->normalizeDateTime($this->re("/\b" . $this->preg_implode($this->t('Fecha y hora de retiro')) . ": *(.+)(?:\s{2,}|\n)/", $text)))
        ;

        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDateTime($this->re("/\b" . $this->preg_implode($this->t('Fecha y hora de devolución')) . ": *(.+)(?:\s{2,}|\n)/", $text)))
        ;

        if (preg_match("/" . $this->preg_implode($this->t('Pick up and return locations')) . ":[ ]*((?:.*\n)+).*[ ]{2,}" . $this->preg_implode($this->t('Fecha y hora de retiro')) . "/u", $text, $m)) {
            $location = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/", "/\n/"], ["\n", ' $1', ' '], $m[1]);
            $r->pickup()
                ->location($location);
            $r->dropoff()
                ->location($location);
        } else {
            if (preg_match("/" . $this->preg_implode($this->t('Pick up location')) . ":[ ]*((?:.*\n)+).*[ ]{2,}" . $this->preg_implode($this->t('Fecha y hora de retiro')) . "/u", $text, $m)) {
                $location = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/", "/\n/"], ["\n", ' $1', ' '], $m[1]);
                $r->pickup()
                    ->location($location);
            }

            if (preg_match("/" . $this->preg_implode($this->t('Return location')) . ":[ ]*((?:.*\n)+).*[ ]{2,}" . $this->preg_implode($this->t('Fecha y hora de devolución')) . "/u", $text, $m)) {
                $location = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/", "/\n/"], ["\n", ' $1', ' '], $m[1]);
                $r->dropoff()
                    ->location($location);
            }
        }

        $r->extra()
            ->company($this->re("/" . $this->preg_implode($this->t("Company")) . ": ?(.+?)(?:\s{2,}|\n)/", $text));

        // Car
        if (preg_match("/" . $this->preg_implode($this->t('Car Class')) . "((?:\n.*)+" . $this->preg_implode($this->t('o similar')) . ".*)/u", $text, $m)) {
            $car = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/"], ["\n", "\n$1"], $m[1]);
            $r->car()
                ->model($this->re("/(.*" . $this->preg_implode($this->t('o similar')) . ".*)/", $car))
                ->type(str_replace("\n", ' ', $this->re("/([\s\S]+)\n.*" . $this->preg_implode($this->t('o similar')) . "/", $car)))
            ;
        }

        // Price
        if (preg_match("/" . $this->preg_implode($this->t("Voucher value")) . "\s*:\s*([^\d\n]+) *(\d[\d\., ]*)\s*(?:\/|\n)/iu", $text, $m)) {
            $r->price()
                ->total($this->normalizePrice($m[2]))
                ->currency($this->currency($m[1]))
            ;
        } elseif (preg_match_all("/\n\s*" . $this->preg_implode($this->t("Pago con tarjeta de crédito")) . "[ ]*([^\d\n]{1,6})[ ]*(\d[,.\d ]*)\s+/", $text, $priceMatches)) {
            $total = 0.0;

            foreach ($priceMatches[2] as $value) {
                $total += $this->normalizePrice($value);
            }
            $r->price()
                ->total($total)
                ->currency($this->currency($priceMatches[1][0]))
            ;
        }

        return $email;
    }

    private function parseCar2(Email $email, $text)
    {
        $this->logger->debug('Car2 Text:' . "\n" . print_r($text, true));

        // Travel Agency
        $email->obtainTravelAgency();
        $confTA = $this->re("/{$this->preg_implode($this->t('Solicitud de Compra #:'))}[ ]*(\d+)(\s{2,}|\n)/u", $text);

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        $r = $email->add()->rental();

        //  General
        $r->general()
            ->confirmation($this->re("/\b" . $this->preg_implode($this->t('Confirmation #:')) . "(?:[ ]{5,}\D+.*\n +)? *([A-Z\d]{5,})(?:\s{2,}|\n)/", $text))
            ->traveller($this->re("/\b" . $this->preg_implode($this->t('Driver\'s Name:')) . " ?(.+?)(?:\s{2,}|\n)/sui", $text), true)
        ;

        // Pick up
        $r->pickup()
            ->date($this->normalizeDateTime($this->re("/\b" . $this->preg_implode($this->t('Fecha y hora de retiro')) . ": *(.+)(?:\s{2,}|\n)/", $text)))
        ;

        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDateTime($this->re("/\b" . $this->preg_implode($this->t('Fecha y hora de devolución')) . ": *(.+)(?:\s{2,}|\n)/", $text)))
        ;

        if (preg_match("/" . $this->preg_implode($this->t('Pick up and return locations')) . ":[ ]*((?:.*\n)+).*[ ]{2,}" . $this->preg_implode($this->t('Fecha y hora de retiro')) . "/u", $text, $m)) {
            $location = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/", "/\n/"], ["\n", ' $1', ' '], $m[1]);
            $r->pickup()
                ->location($location);
            $r->dropoff()
                ->location($location);
        } else {
            if (preg_match("/" . $this->preg_implode($this->t('Pick up location')) . ":[ ]*((?:.*\n)+).*[ ]{2,}" . $this->preg_implode($this->t('Fecha y hora de retiro')) . "/u", $text, $m)) {
                $location = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/", "/\n/"], ["\n", ' $1', ' '], $m[1]);
                $r->pickup()
                    ->location($location);
            }

            if (preg_match("/" . $this->preg_implode($this->t('Return location')) . ":[ ]*((?:.*\n)+).*[ ]{2,}" . $this->preg_implode($this->t('Fecha y hora de devolución')) . "/u", $text, $m)) {
                $location = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/", "/\n/"], ["\n", ' $1', ' '], $m[1]);
                $r->dropoff()
                    ->location($location);
            }
        }

        $r->extra()
            ->company($this->re("/" . $this->preg_implode($this->t("Company")) . ": ?(.+?)(?:\s{2,}|\n)/", $text));

        // Car
        if (preg_match("/" . $this->preg_implode($this->t('Car Class')) . "((?:\n.*)+" . $this->preg_implode($this->t('o similar')) . ".*)/u", $text, $m)) {
            $car = preg_replace(["/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/"], ["\n", "\n$1"], $m[1]);
            $r->car()
                ->model($this->re("/(.*" . $this->preg_implode($this->t('o similar')) . ".*)/", $car))
                ->type(str_replace("\n", ' ', $this->re("/([\s\S]+)\n.*" . $this->preg_implode($this->t('o similar')) . "/", $car)))
            ;
        }

        // Price
        if (preg_match("/" . $this->preg_implode($this->t("Voucher value")) . "\s*:\s*([^\d\n]+) *(\d[\d\., ]*)\s*(?:\/|\n)/iu", $text, $m)) {
            $r->price()
                ->total($this->normalizePrice($m[2]))
                ->currency($this->currency($m[1]))
            ;
        } elseif (preg_match_all("/\n\s*" . $this->preg_implode($this->t("Pago con tarjeta de crédito")) . "[ ]*([^\d\n]{1,6})[ ]*(\d[,.\d ]*)\s+/", $text, $priceMatches)) {
            $total = 0.0;

            foreach ($priceMatches[2] as $value) {
                $total += $this->normalizePrice($value);
            }
            $r->price()
                ->total($total)
                ->currency($this->currency($priceMatches[1][0]))
            ;
        }

        return $email;
    }

    private function parseCar3(Email $email, $text)
    {
        $this->logger->debug('Car 3 Text:' . "\n" . print_r($text, true));

        // Travel Agency
        $email->obtainTravelAgency();
        $confTA = $this->re("/{$this->preg_implode($this->t('Solicitud de Compra #:'))}[ ]*(\d+)(\s{2,}|\n)/u", $text);

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        $r = $email->add()->rental();

        //  General
        $r->general()
            ->confirmation($this->re("/\b" . $this->preg_implode($this->t('Confirmation #:')) . "(?:[ ]{5,}\D+.*\n +)? *([A-Z\d]{5,})(?:\s{2,}|\n)/", $text))
            ->traveller($this->re("/\b" . $this->preg_implode($this->t('Driver\'s Name:')) . ".*\n(?: {30,}.*\n)? {0,5}(\S.+?)(?:\s{2,}|\n)/i", $text), true)
        ;

        // Pick up
        $r->pickup()
            ->date($this->normalizeDateTime($this->re("/\n.* \/ Pick up\s*\n *" . $this->preg_implode($this->t('FECHA:')) . ".*\n+(?: {30,}.*\n+)? {0,5}(\S.+?)(?:\s{2,}|\n)/", $text)))
        ;

        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDateTime($this->re("/\n.* \/ Drop off\s*\n *" . $this->preg_implode($this->t('FECHA:')) . ".*\n+(?: {30,}.*\n+)? {0,5}(\S.+?)(?:\s{2,}|\n)/", $text)))
        ;

        if (preg_match("/" . $this->preg_implode($this->t('MODALIDAD DE RETIRO:')) . "[ ]*((?:.*\n){1,10}?)[ ]*(" . $this->preg_implode($this->t('Abierta de')) . ".+?)(?:\n| {3,})/u", $text, $m)) {
            $location = preg_replace(["/\n {40,}.*/", "/\n {0,15}(\S.+?)[ ]{3,}\S.+/", "/\s*\n\s*/"], ["\n", '$1', ' '], $m[1]);
            $r->pickup()
                ->location($location)
                ->openingHours($m[2])
            ;
        } elseif (preg_match("/" . $this->preg_implode($this->t('MODALIDAD DE RETIRO:')) . "[ ]*((?:.*\n){2,10}?)((?: {30,}.*|\s*)\n) {0,5}\S/u", $text, $m)) {
            $location = preg_replace(["/\n {40,}.*/", "/\n {0,15}(\S.+?)[ ]{3,}\S.+/", "/\s*\n\s*/"], ["\n", '$1', ' '], $m[1]);
            $r->pickup()
                ->location($location)
            ;
        }

        if (preg_match("/" . $this->preg_implode($this->t('MODALIDAD DE DEVOLUCIÓN:')) . "[ ]*((?:.*\n){1,10}?)[ ]*(" . $this->preg_implode($this->t('Abierta de')) . ".+?)(?:\n| {3,})/u", $text, $m)) {
            $location = preg_replace(["/\n {40,}.*/", "/\n {0,15}(\S.+?)[ ]{3,}\S.+/", "/\s*\n\s*/"], ["\n", '$1', ' '], $m[1]);
            $r->dropoff()
                ->location($location)
                ->openingHours($m[2])
            ;
        } elseif (preg_match("/\n{0,5}" . $this->preg_implode($this->t('MODALIDAD DE DEVOLUCIÓN:')) . "[ ]*((?:.*\n){2,10}?)((?: {30,}.*|\s*)\n) {0,5}\S/u", $text, $m)) {
            $location = preg_replace(["/\n {40,}.*/", "/\n {0,15}(\S.+?)[ ]{3,}\S.+/", "/\s*\n\s*/"], ["\n", '$1', ' '], $m[1]);
            $r->dropoff()
                ->location($location)
            ;
        }

        $r->extra()
            ->company($this->re("/\n {0,5}.{5,15}\/ " . $this->preg_implode($this->t("Company")) . ":(?: {5,}.*)?\n+ {0,15}(.+?)(?:\s{2,}|\n)/i", $text));

        // Car
        if (preg_match("/\n.{30,} {2,}(\S.*(\n.{0,50})?\n.*" . $this->preg_implode($this->t('o similar')) . ".*)/u", $text, $m)) {
//            $car = preg_replace(["/\n {40,}.*/", "/\n.{0,50}(?:\n|$)/", "/\n.{50,}[ ]{3,}(\S.+)/"], ["\n", "\n$1"], $m[1]);
            $r->car()
                ->model($this->re("/\n.+ {3,}(.+) " . $this->preg_implode($this->t('o similar')) . "/", $m[1]))
                ->type($this->re("/^(.+)/", $m[1]))
            ;
        }

        // Price
        if (preg_match_all("/\s+" . $this->preg_implode($this->t("Pago con tarjeta de crédito")) . "[ ]*([^\d\n]{1,6})[ ]*(\d[,.\d ]*)\s+/", $text, $priceMatches)) {
            $total = 0.0;

            foreach ($priceMatches[2] as $value) {
                $total += $this->normalizePrice($value);
            }

            $this->currency = $this->currency($priceMatches[1][0]);

            $r->price()
                ->cost($total)
                ->currency($this->currency);
        }

        return $email;
    }

    private function parseCar4(Email $email, $text)
    {
        $r = $email->add()->rental();

        $r->general()
            ->confirmation($this->re("/{$this->opt($this->t('Confirmation #:'))}\s*([\dA-Z]+)/", $text));

        $account = $this->re("/{$this->opt($this->t('account number'))}\s*(\d+)/", $text);

        if (!empty($account)) {
            $r->program()
                ->account($account, false);
        }

        $table = $this->SplitCols($text, [0, 50]);

        $travellers = array_filter(explode("\n", $this->re("/{$this->opt($this->t('DRIVER\'S NAME'))}.+\:\n\s*(\D+){$this->opt($this->t('RENTAL DAYS'))}/smu", $table[0])));

        $r->general()
            ->travellers($travellers, true);

        $company = trim($this->re("/COMPANY\:\n+s*(\D+)\s+\n\D+(?:Pick up)/u", $table[0]));

        if (empty($company)) {
            $company = $this->re("/COMPANY\:\n+s*(.+)\s+[+]/", $table[0]);
        }

        $r->setCompany($company);

        if (preg_match("/Minhas Viagens\.\n+(.+)\n+(.+)\n/u", $table[1], $m)) {
            $r->car()
                ->type($m[1])
                ->model($m[2]);
        }

        $r->pickup()
            ->date($this->normalizeDate($this->re("/Pick\s*up\n\D+\:\n\s*(.+)/u", $table[0])));

        $locationPickup = $this->re("/{$this->opt($this->t('MODALIDADE DE RETIRADA'))}[ ]*:((?:\n+[ ]*.+){1,10}?)\n+[ ]*Tenho/u", $table[0]);
        $locationPickup = preg_replace('/\s+/', ' ', trim($locationPickup));

        if (preg_match($pattern = "/^(?<l>.{3,}?) (?<h>Aberta .+)$/", $locationPickup, $m)) {
            $r->pickup()->location($m['l'])->openingHours($m['h']);
        } else {
            $r->pickup()->location($locationPickup);
        }

        $r->dropoff()
            ->date($this->normalizeDate($this->re("/Drop\s*off\n\D+\:\n\s*(.+)/u", $table[0])));

        $locationDropoff = $this->re("/{$this->opt($this->t('MODALIDADE DE RETORNO'))}[ ]*:((?:\n+[ ]*.+){1,10}?)\n+[ ]*Tenho/u", $table[0]);
        $locationDropoff = preg_replace('/\s+/', ' ', trim($locationDropoff));

        if (preg_match($pattern, $locationDropoff, $m)) {
            $r->dropoff()->location($m['l'])->openingHours($m['h']);
        } else {
            $r->dropoff()->location($locationDropoff);
        }

        $total = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL']/ancestor::tr[1]/descendant::td[2]", null, true, "/([\d\.\,]+)/");
        $currency = $this->http->FindSingleNode("//text()[normalize-space()='TOTAL']/ancestor::tr[1]/descendant::td[2]", null, true, "/(\D+)/");

        if (!empty($total) && !empty($currency)) {
            $r->price()
                ->total(PriceHelper::parse($total, $this->currency($currency)))
                ->currency($this->currency($currency));
        }
    }

    private function parseEvent(Email $email, $text): void
    {
//        $this->logger->debug('Event Text:'."\n".print_r( $text,true));

        // Travel Agency
        $email->obtainTravelAgency();
        $confTA = $this->re("/{$this->preg_implode($this->t('Reservation number:'))}:?[ ]*(\d{5,})(\s{2,}|\n)/u", $text);

        if (empty($confTA)) {
            $confTA = $this->re("/{$this->preg_implode($this->t('Reservation number:'))}:?(?:[ ]{5,}.*)?\n *(\d{5,})(?:\s{2,}|\n)/u", $text);
        }

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        $mainText = $this->re("/\n(.*\/ *Booking information.+[\s\S]+" . $this->preg_implode($this->t('Pick-up Name:')) . "(?:.*\n)+?)\n/u", $text);
        $table = $this->splitCols($mainText);

        $ev = $email->add()->event();

        $ev->setEventType(\AwardWallet\Schema\Parser\Common\Event::TYPE_EVENT);

        // General
        $ev->general()
            ->confirmation($this->re("/{$this->preg_implode($this->t('Booking code:'))}:?.+\n+.*?[ ]{3,}([-A-Z\d]{5,})[ ]*\n/", $text))
            ->traveller(trim(preg_replace('/\s+/', ' ', $this->re("/\b{$this->t('Pick-up Name:')}(.+(\n.+)?)(?:.+:|\n)/", $table[0] ?? null))))
        ;

        // Place
        $ev->place()
            ->name(trim(preg_replace("#\s+#", ' ', $this->re("/\/ *Booking information\n\s*([\s\S]+?)\n.*Tour date:/u", $table[0] ?? null))));

        if (preg_match("/\n[ ]*'Hotel:\s*([\s\S]{3,}?)(?:.+:|\n)/u", $table[0] ?? null, $m)
            || preg_match("/(?:^|\n|\/\/)[ ]*(?:Ponto de encontro:|Dirección:)\s*([^:]{3,}?)(?:[ ]*\/\/|\n\n|$)/i", $text, $m)
        ) {
            $ev->place()->address(trim(preg_replace("/\s+/", ' ', $m[1])));
        } else {
            $ev->place()
                ->address($ev->getName());
        }

        $ev->booked()
            ->guests($this->re("#Travelers:\s*{$this->opt($this->t('Adultos:'))}\s+(\d+)\b#", $table[1] ?? null))
            ->start($this->normalizeDateTime($this->re("#\bTour date:\s*(.+?)(?:.+\D:|\n)#", $table[0] ?? null)))
            ->noEnd()
        ;
    }

    private function parseTransfer(Email $email, $text): void
    {
        // examples: it-79411093.eml

        //$this->logger->debug('Transfer Text:'."\n".print_r( $text,true));

        // check format

        if (strpos($text, $this->t('Punto de encuentro'))) {
            $text = strstr($text, $this->t('Punto de encuentro'), true);
        } elseif (strpos($text, $this->t('Informações adicionais'))) {
            $text = strstr($text, $this->t('Informações adicionais'), true);
        }

        if (empty($text)) {
            $this->logger->debug('other format transfer(1)');

            return;
        }
        $top = $main = null;

        if (preg_match("/^(.+?)\n+([ ]*{$this->opt($this->t('Su reserva'))}.+)/s", $text, $m)) {
            $top = $m[1];
            $main = $m[2];
        }

        if (empty($main)) {
            $this->logger->debug('other format transfer(2)');

            return;
        }

        // Travel Agency
        $email->obtainTravelAgency();
        $confTA = $this->re("/{$this->preg_implode($this->t('Reservation number:'))}:?[ ]*(\d{5,})(\s{2,}|\n)/u", $text);

        if (empty($confTA)) {
            $confTA = $this->re("/{$this->preg_implode($this->t('Reservation number:'))}:?(?:[ ]{5,}.*)?\n *(\d{5,})(?:\s{2,}|\n)/u", $text);
        }

        if (!in_array($confTA, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($confTA);
        }

        // TRANSFER
        $r = $email->add()->transfer();

        // segment
        $tablePos = [0];

        if (preg_match("/^(.+\S.+[ ]{2}){$this->preg_implode($this->t('Your reservation'))}/m", $text, $matches)) {
            $tablePos[] = mb_strlen($matches[1]);
        }
        $table = $this->splitCols($main, $tablePos);

        if (count($table) === 2 && !preg_match("/^\s*{$this->preg_implode($this->t('Your reservation'))}/", $table[1])) {
            $this->logger->debug('other format (3)');

            return;
        }
        $main = $table[0];

        // General
        if (preg_match("/({$this->opt($this->t('Código de reserva'))}).+\n.*?[ ]{3,}([-_A-Z\d]{5,})[ ]*\n/", $top, $m)
            || preg_match("/({$this->opt($this->t('Código de reserva'))})(?: ?\/ ?[^:\n]{2,20})? ?[:]* ?([-_A-Z\d]{5,})(?:[ ]{2}|\n)/", $top, $m)
        ) {
            $r->general()->confirmation($m[2], $m[1]);
        }

        $r->general()->traveller($this->re("#\n\s*{$this->opt($this->t('Reservado por:'))}\s+(.+)#", $main));

        $s = $r->addSegment();

        // departure
        $departureName = $this->re("/\n\s*{$this->opt($this->t('Desde:'))}[: ]*(.{2,}?)\n+\s*{$this->opt($this->t('Hasta:'))}/s", $main);
        $s->departure()
            ->name(preg_replace('/\s+/', ' ', $departureName))
            ->date($this->normalizeDateTime($this->re("/\n\s*{$this->opt($this->t('Horario de recogida:'))}\s+(.+)/", $main)));

        // arrival
        $arrivalName = $this->re("/\n\s*{$this->opt($this->t('Hasta:'))}[: ]*(.{2,}?)\n+\s*(?:{$this->opt($this->t('Llegada'))}|{$this->opt($this->t('Horario de recogida:'))}|{$this->opt($this->t('Flight departure'))})/s", $main);
        $s->arrival()
            ->name(preg_replace('/\s+/', ' ', $arrivalName))
            ->noDate();

        // extra
        $s->extra()
            ->adults($this->re("/\n[ ]*{$this->t('Pasajeros:')}\s*{$this->opt($this->t('Adultos'))}(?: ?\([^)\n:]+\))?:[ ]+(\d{1,3})\b/", $main))
            ->type($this->re("/\n[ ]*{$this->t('Tipo:')}\s+(.{2,})/", $main));

        // price search in body
        $cnt = $s->getAdults();
        $sum = $this->http->FindSingleNode("//text()[({$this->starts($this->t('Traslado para'))}) and ({$this->contains($this->t('personas'))})]/ancestor::td[1]/following-sibling::td[normalize-space()][1]");
        $sum = $this->getTotalCurrency($sum);

        if (!empty($cnt) && $sum['Total'] !== '') {
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }
    }

    private function correctTransferLocations(Email $email, array $locations): void
    {
        if (count($locations) === 0) {
            return;
        }

        /** @var \AwardWallet\Schema\Parser\Common\Transfer $it */
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'transfer') {
                foreach ($it->getSegments() as $itSeg) {
                    if (empty($itSeg->getDepCode()) && !empty($itSeg->getDepName()) && !empty($locations[strtolower($itSeg->getDepName())])) {
                        $itSeg->departure()->code($locations[strtolower($itSeg->getDepName())]);
                    }

                    if (empty($itSeg->getArrCode()) && !empty($itSeg->getArrName()) && !empty($locations[strtolower($itSeg->getArrName())])) {
                        $itSeg->arrival()->code($locations[strtolower($itSeg->getArrName())]);
                    }
                }
            }
        }
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $node = str_replace("₹", "INR", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::parse($m['t'], $m['c']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function assignProvider($text): bool
    {
        foreach (self::$detectProvider as $code => $dProvider) {
            if (!empty($dProvider['pdfBody'])) {
                foreach ($dProvider['pdfBody'] as $phrase) {
                    if (stripos($text, $phrase) !== false) {
                        $this->providerCode = $code;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function normalizeDateTime($instr)
    {
//        $this->logger->debug('date in: '.$instr);
        $in = [
            // Sábado, 12 Dic 2015 - 12:00 hs
            // Wednesday, 6 Feb 2019 - 14:00 hours
            // 30 diciembre 2019 07:00 hs
            // Vie.. 29 abr.. 2022 23:30
            "/^\s*(?:[^\s\d]+,? )?(\d+)(?: de)? ([^\s\d.]+)[.,]{0,2}(?: de)? (\d{4})[,]?[\s\-]+(\d+:\d+)(?: [hours]+)?\s*$/iu",
            // Jueves 17 de marzo de 2016 19:10
            "/^\s*(?:[^\s\d]+,? )?(\d+) ([^\s\d.]+)[.]? (\d{4})[,]?[\s\-]+(\d+:\d+)(?: [hours]+)?\s*$/iu",
            // Feb. Wed 06 2019 09:40
            "/^\s*([[:alpha:]]+)\.\s+[[:alpha:]]+\s+(\d{1,2}) (\d{4})\s+(\d+:\d+)\s*$/iu",
            // 21 mayo 2019 08:00 AM, 08:20 AM, 09:00 AM
            "/^\s*(\d+) +([^\s\d.]+) +(\d{4})\s+(\d+:\d+(?: *[pa]m)?)\s*(?:,.*|$)/iu",
            // 28/01/2021 a las 23:59
            "/^\s*(\d{1,2})\/(\d{2})\/(\d{4}) [\D ]+ (\d{1,2})[:h](\d{2}(?:\s*[ap]m)?)$/ui",
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1 $2 $3, $4',
            '$2 $1 $3, $4',
            '$1 $2 $3',
            '$1.$2.$3, $4:$5',
        ];
        $str = preg_replace($in, $out, $instr);
        //		$this->logger->debug('date out: '. $str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4})#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //		$this->logger->debug('date out: '.$str);
        return strtotime($str);
    }

    private function normalizePrice($string = '')
    {
        if (empty($string)) {
            return $string;
        }
        $string = preg_replace('/\s+/', '', $string);               // 11 507.00  ->  11507.00
        $string = preg_replace('/[,.\'](\d{3})/', '$1', $string);   // 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
        $string = preg_replace('/,(\d{1,2})$/', '.$1', $string);      // 18800,00   ->  18800.00

        if (is_numeric($string)) {
            return (float) $string;
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        if (preg_match($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        if (preg_match_all($re, $str, $m)) {
            if (isset($m[$c])) {
                return $m[$c];
            }
        }

        return [];
    }

    private function currency($s)
    {
        $s = trim($s);
        $sym = [
            //'$'=>'ARS', // it's not error
            'U$S' => 'USD',
            'US$' => 'USD',
            'MXN$'=> 'MXN',
            'COP$'=> 'COP',
            'ARS$'=> 'ARS',
            '€'   => 'EUR',
            '£'   => 'GBP',
            '₹'   => 'INR',
            'R$'  => 'BRL',
            '₡'   => 'CRC',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) use ($text) { return "contains(" . $text . ", \"{$s}\")"; }, $field));
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function preg_implode($field, $replaceSpace = false)
    {
        $field = (array) $field;
        $result = array_map(function ($v) {return preg_quote($v, '#'); }, $field);

        if ($replaceSpace === true) {
            $result = str_replace(' ', '\s+', $result);
        }

        return '(?:' . implode("|", $result) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        $ds = 8;

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                // if table columns are not aligned
                if ($k != 0 && (empty(trim(mb_substr($row, $p - 2, 1))) && empty(trim(mb_substr($row, $p - 1, 1))))) {
                } elseif ($k != 0 && (!empty(trim(mb_substr($row, $p - 1, 1))) || !empty(trim(mb_substr($row, $p, 1))))) {
                    $str = mb_substr($row, $p - $ds, $ds, 'UTF-8');

                    if (preg_match("#(\S.*)\s{2,}(.*)#", $str, $m)) {
                        $cols[$k][] = trim($m[2] . mb_substr($row, $p, null, 'UTF-8'));
                        $row = mb_substr($row, 0, $p - strlen($m[2]) - 1, 'UTF-8');

                        continue;
                    } else {
                        $str = mb_substr($row, $p - $ds, 2 * $ds, 'UTF-8');

                        if (preg_match("#(\S*)\s{2,}(.*)#", $str, $m)) {
                            $cols[$k][] = rtrim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];

                            continue;
                        } elseif (preg_match("#(.*) (\S*:)#", $str, $m)) {
                            $cols[$k][] = trim($m[2] . mb_substr($row, $p + $ds, null, 'UTF-8'));
                            $row = mb_substr($row, 0, $p, 'UTF-8') . $m[1];
                            $row = mb_substr($row, 0, $p - $ds, 'UTF-8') . $m[1];

                            continue;
                        }
                    }
                }
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function split($re, $text)
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

    private function normalizeDate($date)
    {
        //$this->logger->warning('IN-'.$date);
        $in = [
            '#^\s*\w+\.\s*(\d+)\s*(\w+)\.\s*(\d{4})\s*([\d\:]+)\s*\D+#u', //Sáb. 14 ago. 2021 23:00 hs
            '#^[\w\-]+\,\s*(\d+\s*\w+\s*\d{4})[\s\-]+([\d\:]+)$#u', //Segunda-feira, 26 Set 2022 - 12:00 hrs
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1, $2',
        ];
        $str = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        //$this->logger->warning('OUT-'.$str);
        return strtotime($str);
    }
}
