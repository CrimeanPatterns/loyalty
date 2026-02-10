<?php

namespace AwardWallet\Engine\amadeus\Email;

class ReservationAllText2016Es extends ReservationAllText2016En
{
    public $mailFiles = "amadeus/it-4720364.eml";

    protected $lang = [
        'hotel'       => ['HOTEL', 'HOTEL RESERVATION'],
        'flight'      => 'FLIGHT',
        'hotelRef'    => 'REFERENCIA RESERVA DE HOTEL:',
        'checkIn'     => 'REGISTRO DE ENTRADA:',
        'checkOut'    => 'SALIDA:',
        'location'    => 'DIRECCION:',
        'reservation' => 'RESERVATION',
        'chainName'   => 'HOTEL CHAIN NAME:', // no
        'roomType'    => 'TIPO DE HABITACION:',
        'tel'         => 'TELEFONO:',
        'fax'         => 'FAX:',
        'total'       => 'TARIFA TOTAL',
        'departure'   => 'DEPARTURE:', // no
        'arrival'     => 'ARRIVAL:', // no
        'flightRef'   => 'FLIGHT BOOKING REF:', // no
        'seat'        => 'SEAT:', // no
        'sex'         => 'MR|MIS|MRS', // no
        'status'      => 'RESERVA',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'itinerary@amadeus.com') !== false
                // SEDOGO/LEOPOLD MR 24MAR2016 SUV
                && preg_match('/.+?\s+\d+\w+\s+[A-Z]{3}/u', $headers['subject']);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return strpos($parser->getHTMLBody(), 'REGISTRO DE ENTRADA:') !== false
                && strpos($parser->getHTMLBody(), 'INFORMACION GENERAL') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ['es'];
    }
}
