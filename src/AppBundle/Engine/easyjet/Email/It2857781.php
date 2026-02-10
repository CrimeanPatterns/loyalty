<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It2857781 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "easyjet/it-2857781.eml, easyjet/it-4028318.eml, easyjet/it-4029775.eml, easyjet/it-4030080.eml, easyjet/it-4111247.eml, easyjet/it-7573240.eml, easyjet/it-4191420.eml, easyjet/it-4304851.eml, easyjet/it-4898727.eml, easyjet/it-4969594.eml, easyjet/it-4993880.eml";

    public $reFrom = '@easyjet.com';
    public $reSubject = [
        'en' => 'easyJet booking reference',
        'pt' => 'easyJet numero de referencia da reserva',
        'de' => 'easyJet Buchungsnummer',
        'it' => 'easyJet numero di prenotazione',
        'es' => 'easyJet referencia de la reserva',
        'fr' => 'easyJet référence de réservation',
        'pl' => 'easyJet numer rezerwacji',
        'nl' => 'easyJet bevestigingsnummer',
    ];
    public $reBody = 'easyJet';
    public $reBody2 = [
        'en' => 'Most computers will open PDF',
        'pt' => 'email em formato PDF',
        'de' => 'ieser E-Mail als PDF',
        'it' => 'email in formato PDF',
        'es' => 'ordenadores abren los documentos PDF',
        'fr' => 'votre paiement au format pdf',
        'pl' => 'tej wiadomości w formacie PDF',
        'nl' => 'meeste computers openen PDF',
    ];

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            "Date"   => '\d+\/\d+\/\d+',
            "Flight" => 'Flight(?:\s+\([^)(]+\)|)',
        ],
        'pt' => [
            "to"                => "até",
            "Date"              => "\d+-\d+-\d+",
            "Flight"            => "Voo",
            "Segment"           => 'Segmento',
            "Booking Reference" => "Reserva",
            "Customer"          => "Cliente",
            "Issue Date"        => "Data de Recibo",
            "Grand Total"       => "Total geral",
        ],
        'de' => [
            "to"                => "nach",
            "Date"              => '\d+\.\d+\.\d+',
            "Flight"            => 'Flug(?:\s+\([^)(]+\)|)',
            "Segment"           => 'Segment',
            "Booking Reference" => "Buchungsnummer",
            "Customer"          => "Auftraggeber",
            "Issue Date"        => "Ausstellungsdatum",
            "Grand Total"       => "Gesamtsumme",
        ],
        'it' => [
            "to"                => "a",
            "Date"              => '\d+\/\d+\/\d+',
            "Flight"            => 'Volo',
            "Segment"           => 'Segmento',
            "Booking Reference" => "Numero di riferimento",
            "Customer"          => "Cliente",
            "Issue Date"        => "Data di emissione",
            "Grand Total"       => "Importo totale",
        ],
        'es' => [
            "to"                => "a",
            "Date"              => '\d+\/\d+\/\d+',
            "Flight"            => 'Vuelo',
            "Segment"           => 'Segmento',
            "Booking Reference" => "Referencia de la reserva",
            "Customer"          => "Cliente",
            "Issue Date"        => "Fecha de emisión",
            "Grand Total"       => "Total",
        ],
        'fr' => [
            "to"                => "à",
            "Date"              => '\d+\/\d+\/\d+',
            "Flight"            => 'Vol[*]*',
            "Segment"           => '(?:Trajet|Segment)',
            "Booking Reference" => "Numéro de réservation",
            "Customer"          => "Client",
            "Issue Date"        => "Date de création",
            "Grand Total"       => "Total",
        ],
        'pl' => [
            "to"                => "do",
            "Date"              => '\d{4}-\d{2}-\d{2}',
            "Flight"            => 'Lot',
            "Segment"           => 'Segment',
            "Booking Reference" => "Numer rezerwacji",
            "Customer"          => "Nabywca",
            "Issue Date"        => "Data wydania",
            "Grand Total"       => "Łącznie",
        ],
        'nl' => [
            "to"                => "naar",
            "Date"              => '\d{1,2}-\d{1,2}-\d{4}',
            "Flight"            => 'Vlucht(?:\s+\([^)(]+\)|)',
            "Segment"           => 'Segment',
            "Booking Reference" => "Boekingsnummer",
            "Customer"          => "Klant",
            "Issue Date"        => "Afgiftedatum",
            "Grand Total"       => "Eindtotaal",
        ],
    ];

    private $enDatesInverted = true;

    public function parsePdf(Email $email)
    {
        $pdf = implode("\n", $this->pdf->FindNodes('./descendant::text()[normalize-space(.)]'));

        if (preg_match_all('/\b\d{1,2}\/(\d{1,2})\/\d{4}\b/', $pdf, $dateMatches)) {
            foreach ($dateMatches[1] as $simpleDate) {
                if ($simpleDate > 12) {
                    $this->enDatesInverted = false;
                }
            }
        }

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#" . $this->t('Booking Reference') . "\s+(\w+)#ms", $pdf))
            ->traveller(trim($this->re("#" . $this->t('Customer') . "\s+([^\n]+)#ms", $pdf)), true)
            ->date($this->normalizeDate($this->re("#" . $this->t('Issue Date') . "\s+(" . $this->t('Date') . ")#", $pdf)))
        ;

        // Price
        $total = $this->re("#" . $this->t('Grand Total') . "\s+(\d[^\n]+)#", $pdf);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $f->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Segments
        preg_match_all('/(?<ReservationDate>' . $this->t('Date') . ')\s+(?<From>[^\n]+)\s+(?:' . $this->t('to') . ')\s+(?<AirlineName>\w{3})(?<FlightNumber>\d+)\s+(?<FlightDate>' . $this->t('Date') . ')\s+' . $this->t('Flight') . '\D*\s+\d+(?:\s+\d)?\s+' . $this->t('Segment') . '\s+(?<Total>[,.\d]+)\s+(?<Currency>\S+)\s+(?<To>[^\n]+)/', $pdf, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $data) {
            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name($data['AirlineName'])
                ->number($data['FlightNumber'])
            ;

            // Departure
            $s->departure()
                ->noCode()
                ->noDate()
                ->name($data['From'])
                ->day($this->normalizeDate($data['FlightDate']))
            ;

            // Arrival
            $s->arrival()
                ->noCode()
                ->noDate()
                ->name($data['To'])
            ;
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->http->FilterHTML = false;

        foreach ($this->reBody2 as $lang => $re) {
            if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $re . '")]')->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                $this->pdf = clone $this->http;
                $this->pdf->SetEmailBody($html);

                if ($this->pdf->XPath->query('//node()[contains(normalize-space(.),"' . $this->t('Booking Reference') . '")]')->length > 0) {
                    $this->parsePdf($email);

                    break;
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        switch ($this->lang) {
            case 'en':
                $in = "#^(\d+)/(\d+)/(\d{4})$#";
                $out = $this->enDatesInverted ? "$1.$2.$3" : "$2.$1.$3";

                break;

            case 'pt':
                $in = "#^(\d+)-(\d+)-(\d{4})$#";
                $out = "$1.$2.$3";

                break;

            case 'de':
                $in = "#^(\d+)\.(\d+)\.(\d{4})$#";
                $out = "$1.$2.$3";

                break;

            case 'it':
                $in = "#^(\d+)/(\d+)/(\d{4})$#";
                $out = $this->enDatesInverted ? "$1.$2.$3" : "$2.$1.$3";

                break;

            case 'es':
                $in = "#^(\d+)/(\d+)/(\d{4})$#";
                $out = $this->enDatesInverted ? "$1.$2.$3" : "$2.$1.$3";

                break;

            case 'fr':
                $in = "#^(\d+)/(\d+)/(\d{4})$#";
                $out = $this->enDatesInverted ? "$1.$2.$3" : "$2.$1.$3";

                break;

            case 'nl':
                $in = '/^(\d{1,2})-(\d{1,2})-(\d{4})$/';
                $out = '$1.$2.$3';

                break;

            default:
                $in = "#(.+)$#";
                $out = "$1";
        }

        return strtotime(preg_replace($in, $out, $str));
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($price)
    {
        $price = str_replace([' '], '', $price);

        switch ($this->lang) {
            case 'en':
                $price = str_replace(',', '', $price);
                // no break
            default:
                $price = str_replace(',', '.', $price);
        }
        $price = str_replace(',', '', $price);

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
    }
}
