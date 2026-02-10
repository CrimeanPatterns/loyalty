<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPDF extends \TAccountChecker
{
    public $mailFiles = "amoma/it-34912159.eml, amoma/it-39150175.eml, amoma/it-39309858.eml, amoma/it-40143784.eml, amoma/it-40146490.eml"; // +2 bcdtravel(pdf)[de,en]

    public $reFrom = "@amoma.com";
    public $reFromH = "amoma.com";
    public $reBody = [
        'es'  => ['Sólo los servicios mencionados en el comprobante están', 'INFORMACIÓN DEL HOTEL'],
        'es2' => ['No se debe cobrar al huésped por los servicios', 'INFORMACIÓN DEL HOTEL'],
        'it'  => ['Solo le prestazioni menzionate sul voucher sono', 'INFORMAZIONI SULL\'HOTEL'],
        'it2' => ['I servizi elencati su questo voucher non devono essere addebitati all', 'INFORMAZIONI SULL\'HOTEL'],
        'fr'  => ['Seules les prestations mentionnées exclusivement sur', 'INFORMATION HÔTEL'],
        'de'  => ['Nur Dienste, die im Gutschein genannt', 'HOTELINFORMATIONEN'],
        'pt'  => ['Apenas os serviços mencionados no voucher estão incluídos', 'INFORMAÇÃO DO HOTEL'],
        'en'  => ['Only services mentioned on the voucher are included in the', 'HOTEL INFORMATION'],
        'en2' => ['The guest must not be charged for the services listed on', 'HOTEL INFORMATION'],
    ];
    public $reSubject = [
        'es' => 'Su confirmación de reserva con',
        'it' => 'La tua conferma della prenotazione con',
        'fr' => 'Votre confirmation de réservation avec',
        'de' => 'Ihre Buchungsbestätigung mit',
        'pt' => 'A confirmação da sua reserva com',
        'en' => 'Your booking confirmation with',
    ];
    public $lang = '';
    /** @var \HttpBrowser */
    public $pdf;
    public $pdfNamePattern = "(?:VOUCHER|file).*pdf";
    public static $dict = [
        'es' => [
            'Reference number:'     => 'Número de reserva:',
            'REFERENCE NUMBER:'     => 'NÚMERO DE RESERVA:',
            'Your Booking ID:'      => 'Tu identificación de reserva:',
            'Your Customer Number:' => 'Tu número de cliente:',
            'HOTEL:'                => 'HOTEL:',
            'COUNTRY:'              => 'PAÍS:',
            'ADDRESS:'              => 'DIRECCIÓN:',
            'GUEST NAME:'           => 'NOMBRE DEL HUÉSPED:',
            'CHECK-IN DATE:'        => "FECHA DE LLEGADA:",
            'CHECK-OUT DATE:'       => 'FECHA DE SALIDA:',
            'TYPE OF ROOM:'         => 'TIPO DE HABITACIÓN:',
            'adult'                 => 'adultos',
            'children'              => 'niños',
        ],
        'it' => [
            'Reference number:'     => 'Numero di prenotazione:',
            'REFERENCE NUMBER:'     => 'NUMERO DI PRENOTAZIONE:',
            'Your Booking ID:'      => 'ID della tua prenotazione:',
            'Your Customer Number:' => 'Il tuo numero di cliente:',
            'HOTEL:'                => 'HOTEL:',
            'COUNTRY:'              => 'PAESE:',
            'ADDRESS:'              => 'INDIRIZZO',
            'GUEST NAME:'           => 'NOME OSPITE:',
            'CHECK-IN DATE:'        => "DATA DI ARRIVO:",
            'CHECK-OUT DATE:'       => 'DATA DI PARTENZA:',
            'checkOut'              => 'ARRIVO:.+?DATA DI(.+?)PARTENZA:',
            'TYPE OF ROOM:'         => 'TIPO DI CAMERA:',
            'adult'                 => 'adulti',
            'children'              => 'bambini',
        ],
        'fr' => [
            'Reference number:' => 'Numéro de réservation:',
            'REFERENCE NUMBER:' => 'NUMÉRO DE RÉSERVATION:',
            'Your Booking ID:'  => 'Votre identifiant de réservation',
            //            'Your Customer Number:' => '',
            'HOTEL:'          => 'HÔTEL:',
            'COUNTRY:'        => 'PAYS:',
            'ADDRESS:'        => 'ADRESSE:',
            'GUEST NAME:'     => 'NOM DU CLIENT:',
            'CHECK-IN DATE:'  => "DATE D'ARRIVÉE:",
            'CHECK-OUT DATE:' => 'DATE DE DÉPART:',
            'TYPE OF ROOM:'   => 'TYPE DE CHAMBRE:',
            'adult'           => 'adultes',
            'children'        => 'enfants',
        ],
        'de' => [
            'Reference number:' => 'Reservierungsnummer:',
            'REFERENCE NUMBER:' => 'RESERVIERUNGSNUMMER:',
            'Your Booking ID:'  => 'Ihre Buchungsnummer:',
            //            'Your Customer Number:' => '',
            'HOTEL:'          => 'HOTEL:',
            'COUNTRY:'        => 'LAND:',
            'ADDRESS:'        => 'ADRESSE:',
            'GUEST NAME:'     => 'NAME DES GASTES:',
            'CHECK-IN DATE:'  => "CHECK-IN-DATUM:",
            'CHECK-OUT DATE:' => 'CHECK-OUT-DATUM:',
            'TYPE OF ROOM:'   => 'ZIMMERTYP:',
            'adult'           => 'Erwachsene',
            'children'        => 'Kinder',
        ],
        'pt' => [
            'Reference number:'     => 'Número de referência:',
            'REFERENCE NUMBER:'     => 'NÚMERO DE REFERÊNCIA:',
            'Your Booking ID:'      => 'A Identificação da sua Reserva:',
            'Your Customer Number:' => 'O seu Número de Cliente:',
            'HOTEL:'                => 'HOTEL:',
            'COUNTRY:'              => 'PAÍS:',
            'ADDRESS:'              => 'MORADA:',
            'GUEST NAME:'           => 'NOME DO ACOMPANHANTE:',
            'CHECK-IN DATE:'        => 'DATA DE CHEGADA:',
            'CHECK-OUT DATE:'       => 'DATA DE SAÍDA:',
            'TYPE OF ROOM:'         => 'TIPO DE QUARTO:',
            'adult'                 => 'adultos',
            'children'              => 'crianças',
        ],
        'en' => [
            //            'Reference number:' => '',
            //            'REFERENCE NUMBER:' => '',
            //            'Your Booking ID:' => '',
            //            'Your Customer Number:' => '',
            'HOTEL:'          => ['HOTEL:', 'Hotel:'],
            'COUNTRY:'        => ['COUNTRY:', 'Country:'],
            'ADDRESS:'        => ['ADDRESS:', 'Address:'],
            'GUEST NAME:'     => ['GUEST NAME:', 'Guest name:'],
            'CHECK-IN DATE:'  => ['CHECK-IN DATE:', 'Check-in date:'],
            'CHECK-OUT DATE:' => ['CHECK-OUT DATE:', 'Check-out date:'],
            'TYPE OF ROOM:'   => ['TYPE OF ROOM:', 'Type of room:'],
            'adult'           => ['adult', 'adults'],
            'children'        => ['children', 'child'],
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;
            $htmlPdf = '';

            foreach ($pdfs as $pdf) {
                if (($htmlPdf .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $this->pdf->SetEmailBody(str_replace($NBSP, ' ', html_entity_decode($htmlPdf)));
        } else {
            return null;
        }

        $body = $this->pdf->Response['body'];
        $this->assignLang($body);

        $its = $this->parseEmail($body);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ConfirmationPDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

            return $this->assignLang($textPdf);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], ' AMOMA.com') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (stripos($headers["subject"], $reSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'AMOMA.com') !== false
            || stripos($from, '@amoma.com') !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail($body)
    {
        $it = ['Kind' => 'R'];

        $patterns['confNumber'] = '(?:[A-Z\d]{9,}|\d{3}[-\/]\d{7,}|[a-zA-Z\-\d]{5,})'; // L6PF222632  |  010/27122215  |  102-8940722  |  L6PF222632 - 010/27122215 |zbr-98398

        // ConfirmationNumber
        $confNumber = $this->pdf->FindSingleNode("//text()[{$this->contains($this->t('Reference number:'))}]", null, true, "/:\s*({$patterns['confNumber']}) *$/");

        if (empty($confNumber)) {
            $confNumber = $this->pdf->FindSingleNode("//text()[{$this->eq($this->t('REFERENCE NUMBER:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "/^({$patterns['confNumber']})(?:\s*-|$)/");
        }

        if (empty($confNumber)) {
            $confNumber = $this->pdf->FindSingleNode("//text()[{$this->eq($this->t('Reference number:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "/^({$patterns['confNumber']})(?:\s*-|$)/");
        }

        if (empty($confNumber)) {
            $confNumber = $this->pdf->FindSingleNode("//text()[{$this->eq($this->t('Reference number:'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "/^({$patterns['confNumber']})/");
        }
        $it['ConfirmationNumber'] = $confNumber;

        $it['TripNumber'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Your Booking ID:')}')]/following::text()[normalize-space(.)!=''][1]");
        $it['AccountNumbers'][] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'{$this->t('Your Customer Number:')}')]/following::text()[normalize-space(.)!=''][1]");
        $it['HotelName'] = $this->nextText($this->t('HOTEL:'));
        $it['Address'] = $this->nextText($this->t('ADDRESS:'));
        $country = $this->nextText($this->t('COUNTRY:'));

        if (substr(trim($country), -1) !== ":") {
            $it['Address'] = $country . '. ' . $it['Address'];
        }
        $it['Address'] = strip_tags(html_entity_decode($it['Address']));
        $it['GuestNames'][] = $this->nextText($this->t('GUEST NAME:'));
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText($this->t('CHECK-IN DATE:'))));
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText($this->t('CHECK-OUT DATE:'))));

        if (!$it['CheckOutDate']) {
            $body = strip_tags($body);

            if (preg_match("/{$this->t('checkOut')}/s", $body, $m)) {
                $it['CheckOutDate'] = strtotime($this->normalizeDate($m[1]));
            }
        }
        $it['RoomType'] = $this->nextText($this->t('TYPE OF ROOM:'));

        if (preg_match("#(\d+)\s*x\s*(.+)#", $it['RoomType'], $m)) {
            $it['RoomType'] = $m[2];
            $it['Rooms'] = $m[1];
        }
        $node = $this->pdf->FindSingleNode("//text()[{$this->contains($this->t('adult'))}]");
        $it['Guests'] = $this->re("#(\d+)\s+{$this->opt($this->t('adult'))}#", $node);
        $it['Kids'] = $this->re("#(\d+)\s+{$this->opt($this->t('children'))}#", $node);

        return [$it];
    }

    private function nextText($field)
    {
        return $this->pdf->FindSingleNode("//text()[{$this->starts($field)}]/following::text()[normalize-space(.)!=''][1]");
    }

    private function normalizeDate($date)
    {
        $in = [
            //Wednesday, June 7, 2017
            '#^\s*\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s*$#u',
            //lundi 10 juil et 2017
            '#^\s*\w+\s+(\d+)\s+(\w+)\s+(?:et\s+)?(\d{4})\s*$#u',
            // Sonntag, 17. Juni 2018
            '#^\s*\w+,\s*(\d+)[\s.]+(\w+)\s+(\d{4})\s*$#u',
            // martes, 11 de junio de 2019
            '#^\s*[\w\-]+,\s*(\d+)\s+de\s+(\w+)\s+de\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
            '$1 $2 $3',
            '$1 $2 $3',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return $str;
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
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
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

        return $date;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
