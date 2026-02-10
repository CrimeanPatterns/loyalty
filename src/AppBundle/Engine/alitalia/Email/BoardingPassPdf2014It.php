<?php

namespace AwardWallet\Engine\alitalia\Email;

/**
 * it-4215251.eml.
 */
class BoardingPassPdf2014It extends BoardingPassPdf2014En
{
    public $mailFiles = "alitalia/it-4215251.eml, alitalia/it-5393801.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return //isset($headers['from']) && stripos($headers['from'], 'noreply@alitalia.com') !== FALSE &&
                isset($headers['subject']) && stripos($headers['subject'], 'Web Check-in - Invio riepilogo') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Ritira la tua carta d\'imbarco in aeroporto alle self') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@alitalia.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['it'];
    }

    protected function parseEmail($pdfText)
    {
        $this->parseSegments($this->findСutSection($pdfText, 'TERMINAL', 'Ritira la tua carta d\'imbarco'));
        $this->iterationReservations($this->findСutSection($pdfText, 'POSTO', 'VOLO'));
    }
}
