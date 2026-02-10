<?php

namespace AwardWallet\Engine\alitalia\Email;

class TicketReceiptHtml2014Es extends TicketReceiptHtml2014Fr
{
    public $mailFiles = "alitalia/it-4197135.eml";

    protected $pattern = [
        'recordLocator' => 'código de la reserva:',
        // Passangers
        'firstName'     => 'Nombre:',
        'lastName'      => 'Apellidos:',
        'ticketNumbers' => 'Nº de Billete',
        // Price list
        'totalCharge' => 'Total',
        'tax'         => 'Tasas y suplementos',
        // Segments
        'code'          => '(Desde|A):',
        'traveledMiles' => 'distancia:',
        'duration'      => 'Tiempo de vuelo:\s*(\d+\s*h\s*\d+\s*min)',
        'aircraft'      => 'Avión:(.+?)Aeropuerto',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'confirmation@alitalia.com') !== false
                && isset($headers['subject']) && stripos($headers['subject'], 'Recibo de billete electrónico') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Gracias por su compra. Éste es el código de la reserva:') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }
}
