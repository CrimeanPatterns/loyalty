<?php

namespace AwardWallet\Engine\easyjet\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingReferencePdf extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-10014269.eml, easyjet/it-10015373.eml, easyjet/it-156268299.eml, easyjet/it-5164316.eml, easyjet/it-5243906.eml, easyjet/it-7575598.eml, easyjet/it-7628383.eml, easyjet/it-7634563.eml, easyjet/it-7764003.eml, easyjet/it-7781462.eml, easyjet/it-7802307.eml, easyjet/it-7911283.eml, easyjet/it-7968652.eml";

    public $reFrom = '@easyjet.com';

    public $reSubject = [
        'fr' => "easyJet référence de réservation",
        'pt' => "easyJet número de referência da reserva",
        'es' => "easyJet referencia de la reserva",
        'da' => "easyJet Bookingnummer",
        'de' => "easyJet Buchungsnummer",
        'it' => 'easyJet numero di prenotazione',
        'pl' => 'easyJet numer rezerwacji',
        'nl' => 'easyJet bevestigingsnummer',
        'cs' => 'easyJet číslo rezervace',
        'en' => 'easyJet booking reference',
    ];

    public $lang = '';
    public $text = '';

    public static $dictionary = [
        'fr' => [],
        'pt' => [
            "Numéro de réservation"   => "Referência da reserva",
            "Informations passager :" => "Informações do passageiro:",
            "Frais :"                 => "Custos:",
            "MONTANT TOTAL"           => "PAGAMENTO TOTAL",
            "TOTAL DÛ"                => "TOTAL DA FATURA",
            "Date de réservation"     => "Data de reserva",
        ],
        'es' => [
            "Numéro de réservation"   => "Referencia de la reserva",
            "Informations passager :" => "Detalles de los pasajeros:",
            "Frais :"                 => "Cargos:",
            "MONTANT TOTAL"           => "TOTAL A PAGAR",
            "TOTAL DÛ"                => "MPORTE TOTAL DE LA FACTURA",
            "Date de réservation"     => "Fecha de la reserva",
        ],
        'da' => [
            "Numéro de réservation"   => "Bookingreference",
            "Informations passager :" => "Passageroplysninger:",
            "Frais :"                 => "Beløb",
            "MONTANT TOTAL"           => "BETALING I ALT",
            "TOTAL DÛ"                => "FAKTURA I ALT",
            "Date de réservation"     => "Bookingdato",
        ],
        'de' => [
            "Numéro de réservation"   => "Buchungsnummer",
            "Informations passager :" => "Passagierdaten:",
            "Frais :"                 => "Gebühren:",
            "MONTANT TOTAL"           => "GESAMTZAHLUNG",
            "TOTAL DÛ"                => "GESAMTRECHNUNGSBETRAG",
            "Date de réservation"     => "Buchungsdatum",
        ],
        'it' => [
            "Numéro de réservation"   => "Numero di riferimento prenotazione",
            "Informations passager :" => "Informazioni dei passeggeri:",
            "Frais :"                 => "Costi:",
            "MONTANT TOTAL"           => "TOTALE PAGAMENTO",
            "TOTAL DÛ"                => "TOTALE FATTURA",
            "Date de réservation"     => "Data di prenotazione",
        ],
        'pl' => [
            "Numéro de réservation"   => "Numer rezerwacji",
            "Informations passager :" => "Dane pasażera:",
            "Frais :"                 => "Opłaty:",
            "MONTANT TOTAL"           => "PŁATNOŚĆ CAŁKOWITA",
            "TOTAL DÛ"                => "SUMA FAKTURY",
            "Date de réservation"     => "Data rezerwacji",
        ],
        'nl' => [
            "Numéro de réservation"   => "Boekingsreferentie",
            "Informations passager :" => "Passagiersgegevens:",
            "Frais :"                 => "Kosten:",
            "MONTANT TOTAL"           => "TOTALE BEDRAG",
            "TOTAL DÛ"                => "FACTUURBEDRAG",
            "Date de réservation"     => "Boekingsdatum",
        ],
        'cs' => [
            "Numéro de réservation"   => "Číslo rezervace",
            "Informations passager :" => "Údaje o cestujících:",
            "Frais :"                 => "Poplatky:",
            "MONTANT TOTAL"           => "PLATBA CELKEM",
            "TOTAL DÛ"                => "CELKOVÁ ČÁSTKA FAKTURY",
            "Date de réservation"     => "Datum rezervace",
        ],
        'en' => [
            "Numéro de réservation"   => "Booking reference",
            "Informations passager :" => "Passenger Details:",
            "Frais :"                 => "Charges:",
            "MONTANT TOTAL"           => "TOTAL PAYMENT",
            "TOTAL DÛ"                => "INVOICE TOTAL",
            "Date de réservation"     => "Booking date",
        ],
    ];

    protected $langDetectors = [
        'fr' => ['Votre facture avec TVA au format PDF'],
        'pt' => ['A fatura de IVA solicitada está anexada a este e-mail em formato PDF'],
        'es' => ['La factura con IVA solicitada se adjunta en este correo electrónico en formato PDF'],
        'da' => ['Din anmodede MOMS-faktura er vedhæftet denne e-mail i PDF'],
        'de' => ['Ihre angeforderte USt.-Rechnung ist dieser E-Mail als PDF'],
        'it' => ['La fattura con IVA richiesta è allegata a questa email in formato PDF'],
        'pl' => ['Żądana faktura VAT została dołączona do tej wiadomości w formacie PDF'],
        'nl' => ['De door u aangevraagde btw-factuur is als PDF-bijlage bij deze e-mail gevoegd'],
        'cs' => ['Požadovanou fakturu s DPH zasíláme v příloze tohoto e-mailu ve formátu PDF'],
        'en' => ['Your requested VAT invoice is attached to this email in PDF'],
    ];

    public function parsePdf(Email $email)
    {
        $text = $this->text;

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->re("#{$this->t("Numéro de réservation")}\s+(.+)#", $text));

        // Passengers
        $passtable = $this->splitCols(
            preg_replace("#^\s*\n#", "", mb_substr(
                $text,
                $sp = mb_strpos($text, $this->t("Informations passager :")) + mb_strlen($this->t("Informations passager :")),
                mb_strpos($text, $this->t("Frais :")) - $sp, 'UTF-8'
            ))
        );

        if (count($passtable) < 3) {
            $this->http->Log('incorrect passtable parse!');

            return false;
        }
        $pass = explode("\n", $passtable[0]);
        unset($pass[0]);
        $f->general()
            ->travellers(array_values(array_filter($pass)), true);

        $f->general()
            ->date(strtotime($this->normalizedate($this->re("#{$this->t("Date de réservation")}\s+(.+)#", $text))));


        // Price
        if (preg_match_all("#" . $this->t("MONTANT TOTAL") . "\s+(.+)#", $text, $m)) {
            $total = 0.0;

            foreach ($m[1] as $value) {
                $total += $this->correctSum($value);
            }
            $f->price()
                ->total($total);
        }
        $f->price()
            ->currency($this->re("#{$this->t("TOTAL DÛ")}\s+\(([A-Z]{3})\)#", $text));


        // Segments
        preg_match_all("#\n\s*(?<DepName>.*?)\s*\((?<DepCode>[A-Z]{3})\)\s+-\s+(?<ArrName>.*?)\s*\((?<ArrCode>[A-Z]{3})\)\s+(?<FlightNumber>\d+)\s+(?<DepTime>\d+:\d+)\s+(?<Date>\d+\D\d+\D\d{4}|\d{4}-\d+-\d+)#", $text, $segmentMatches, PREG_SET_ORDER);

        foreach ($segmentMatches as $segment) {

            foreach ($f->getSegments() as $key => $seg) {
                if ($segment['DepCode'] === $seg->getDepCode() && $segment['ArrCode'] === $seg->getArrCode()) {
                    continue 2;
                }
            }

            $s = $f->addSegment();

            // Airline
            $s->airline()
                ->name('U2')
                ->number($segment['FlightNumber']);

            // Departure
            $s->departure()
                ->code($segment['DepCode'])
                ->name($segment['DepName'])
                ->date(strtotime($this->normalizeDate($segment['Date'] . ', ' . $segment['DepTime'])))
                ->strict()
            ;

            // Arrival
            $s->arrival()
                ->code($segment['ArrCode'])
                ->name($segment['ArrName'])
                ->noDate()
            ;
        }

        return true;
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

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"easyJet airline company ltd") or contains(.,"@easyjet.com")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,"//www.easyjet.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

//        $itineraries = [];

        foreach ($parser->searchAttachmentByName('.*pdf') as $pdf) {
            $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (empty($this->text)) {
                continue;
            }

            if ($this->parsePdf($email) !== false) {
                break;
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

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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
        // $this->http->log($str);
        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+)/(\d+)/(\d{4}),\s+(\d+:\d+)$#", //14/07/2017, 19:05
            "#^(\d+)/(\d+)/(\d{4})$#", //14/07/2017
            "#^(\d{4}-\d+-\d+, \d+:\d+)$#", //2017-06-05, 11:40
            "#^(\d{4}-\d+-\d+)$#", //2017-06-05
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1.$2.$3",
            "$1",
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function TableHeadPos($row)
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

    private function SplitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->TableHeadPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function correctSum($str)
    {
        $str = preg_replace('/\s+/', '', $str);			// 11 507.00	->	11507.00
        $str = preg_replace('/[,.](\d{3})/', '$1', $str);	// 2,790		->	2790		or	4.100,00	->	4100,00
        $str = preg_replace('/^(.+),$/', '$1', $str);	// 18800,		->	18800
        $str = preg_replace('/,(\d{2})$/', '.$1', $str);	// 18800,00		->	18800.00

        return $str;
    }
}
