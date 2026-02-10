<?php

namespace AwardWallet\Engine\decolar\Email;

class VoucherPdf2016Pt extends \TAccountChecker
{
    use \DateTimeTools;

    public $mailFiles = "decolar/it-5010972.eml";

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName('Voucher\.pdf');

        if (empty($pdf)) {
            $this->http->Log('Pdf is not found or is empty!');

            return false;
        }

        $pdfText = \PDF::convertToText($parser->getAttachmentBody(array_shift($pdf)));
        $rsrv = $this->parseReservations(str_replace(' ', ' ', $this->findСutToWord($pdfText, 'Imprima este voucher para')));

        return [
            'parsedData' => ['Itineraries' => [$rsrv]],
        ];
    }

    public function findСutToWord($text, $word)
    {
        return substr($text, 0, strpos($text, $word));
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'Vendas@decolar.com') !== false
                && isset($headers['subject']) && (
                stripos($headers['subject'], 'Decolar.com - Solicitação de compra - Número:') !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return stripos($parser->getHTMLBody(), 'Enviamos em anexo o voucher correspondente a sua reserva') !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@decolar.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return ['pt'];
    }

    protected function parseReservations($text)
    {
        $rsrv['Kind'] = 'R';

        if (preg_match('/N° de solicitação de compra:\s+(\d+)\s+(.+?)\s+Endereço:/s', $text, $matches)) {
            $rsrv['ConfirmationNumber'] = $matches[1];
            $rsrv['HotelName'] = $matches[2];
        }

        if (preg_match('/Endereço:\s+(.+?)\s{3,}/s', $text, $matches)) {
            $rsrv['Address'] = $matches[1];
        }

        if (preg_match('/Entrada:.+?,\s*(\d+ \w+ \d+)\s*-\s*(\d+:\d+).+?Saida:.+?,\s*(\d+ \w+ \d+)\s*-\s*(\d+:\d+)/su', $text, $matches)) {
            $rsrv['CheckInDate'] = strtotime($this->dateStringToEnglish($matches[1]) . ', ' . $matches[2]);
            $rsrv['CheckOutDate'] = strtotime($this->dateStringToEnglish($matches[3]) . ', ' . $matches[4]);
        }

        if (preg_match_all('/Quartos\s+(\d+)\s+/s', $text, $matches)) {
            $rsrv['Rooms'] = array_sum($matches[1]);
        }

        if (preg_match('/Tipo:\s+(.+?)\s+Refeiçóes:\s+(.+?)\s+Hóspedes:\s+(\d+) adultos/s', $text, $matches)) {
            $rsrv['RoomType'] = str_replace("\n", ' ', $matches[1]);
            $rsrv['Guests'] = $matches[3];
        }

        return $rsrv;
    }
}
