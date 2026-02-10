<?php

namespace AwardWallet\Engine\alitalia\Email;

class BoardingPassPdf2014Es extends BoardingPassPdf2014En
{
    public $mailFiles = "alitalia/it-4868833.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['subject']) && stripos($headers['subject'], 'Web Check-in: obtener resumen') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Le enviamos adjunto un resumen del check-in que acaba de realizar.') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    protected function parseEmail($pdfText)
    {
        $this->parseSegments($this->findСutSection($pdfText, 'TERMINAL', 'Podrá recoger su tarjeta'));
        $this->iterationReservations($this->findСutSection($pdfText, 'ASIENTO', 'VUELO'));
    }
}
