<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;

class ConfirmationPdf extends \TAccountChecker
{
    public $mailFiles = "avis/it-35631601.eml";

    public $reFrom = "@avis-europe.com";
    public $reSubject = [
        "en" => "Avis Rental Agreement",
        "de" => "Bestätigung: E-signiertes Dokument Avis",
        "es" => "Confirmación: documento firmado electrónicamente Avis",
        "fr" => "Confirmation: document e-signé Avis",
    ];
    public $reBody = 'Avis';
    public $reBody2 = [
        "en" => "Date/Time",
        "de" => "Datum/Uhrzeit",
        "es" => "Fecha/Hora",
        "fr" => "Date/Heure",
    ];
    public $pdfPattern = "(?:(?:Avis RA\s+)?\d+|Avis Rental Agreement|Avis *[A-Z]{2} *\d{5,})\.pdf";

    public static $dictionary = [
        "en" => [],
        "de" => [
            'Personal Details'        => 'Persönliche Daten',
            'Name'                    => 'Name',
            'Vehicle Details'         => 'Fahrzeugdaten',
            'Rental Agreement Number' => 'Mietvertragsnummer',
            'Date/Time'               => 'Datum/Uhrzeit',
            'Start Location'          => 'Anmietstation',
            'Agreed Return Location'  => 'Vereinbarte Rückgabestation',
            'Vehicle Type'            => 'Fahrzeugtyp',
            'Estimated amount due'    => 'Voraussichtliche Mietkosten',
        ],
        "es" => [
            'Personal Details'        => 'Datos Personales',
            'Vehicle Details'         => 'Datos del Vehículo',
            'Name'                    => 'Nombre',
            'Rental Agreement Number' => 'Número de Contrato de Alquiler',
            'Opening Hours'           => 'Horario de Apertura',
            'Date/Time'               => 'Fecha/Hora',
            'Start Location'          => 'Oficina de Salida',
            'Agreed Return Location'  => 'Oficina Prevista de Devolución',
            'Vehicle Type'            => 'Modelo de Vehículo',
            'Estimated amount due'    => 'Estimación del Importe a Cobrar',
        ],
        "fr" => [
            'Personal Details'        => 'Vos informations personnelles',
            'Name'                    => 'Nom',
            'Vehicle Details'         => 'Eléments du véhicule',
            'Rental Agreement Number' => 'Votre contrat de location',
            'Opening Hours'           => 'Horaires d\'ouverture',
            'Date/Time'               => 'Date/Heure',
            'Start Location'          => 'Agence de départ',
            'Agreed Return Location'  => 'Agence de retour prévue',
            'Vehicle Type'            => 'Marque et modèle',
            'Estimated amount due'    => 'Estimation du montant dû de votre location',
        ],
    ];

    public $lang = "de";

    public function parsePdf(&$itineraries): void
    {
        $text = $this->text;
        //		$this->logger->debug($text);
        $table = $this->splitCols($this->re("#\n([^\n\S]*{$this->t('Personal Details')}.*?){$this->t('Vehicle Details')}#ms", $text));

        if (count($table) < 2) {
            $this->logger->debug("incorrect columns count!");

            return;
        }

        $it = [];
        $it['Kind'] = "L";
        $it['Number'] = $this->re("#{$this->t('Rental Agreement Number')}\s*:\s*(\w+)#", $text);

        if (preg_match("#{$this->t('Date/Time')}\s+(?<pickup>\d+\s+[^\s\d]+[^\s\d]+\s+\d{2}\s+\d{4})\s+(?<dropoff>\d+\s+[^\s\d]+[^\s\d]+\s+\d{2}\s+\d{4})#", $text, $m)) {
            $it['PickupDatetime'] = strtotime($this->normalizeDate($m['pickup']));
            $it['DropoffDatetime'] = strtotime($this->normalizeDate($m['dropoff']));
        }

        $patterns['phone'] = '[+(\d][-. \d)(]{5,}[\d)]';

        $it['PickupLocation'] = preg_replace('/\s+/', ' ', $this->re("#{$this->t('Start Location')}\n+([\s\S]*?)\n+{$patterns['phone']}\n#", $table[1]));

        $it['DropoffLocation'] = preg_replace('/\s+/', ' ', $this->re("#{$this->t('Agreed Return Location')}\n+([\s\S]*?)\n+{$patterns['phone']}\n#", $table[1]));

        $it['PickupPhone'] = $this->re("#{$this->t('Start Location')}\n+[\s\S]*?\n+({$patterns['phone']})\n#", $table[1]);

        $it['DropoffPhone'] = $this->re("#{$this->t('Agreed Return Location')}\n+[\s\S]*?\n+({$patterns['phone']})\n#", $table[1]);

        $it['DropoffHours'] = preg_replace(['/\s*\n\s*/', '/ {2,}/'], ['; ', ' - '], trim($this->re("#{$this->t('Agreed Return Location')}\n+[\s\S]*?\n+{$this->t('Opening Hours')}((?:\n {0,5}\S+.*){0,7})(?:\n|$)#", $table[1])));

        if (!empty($it['DropoffPhone']) && $it['DropoffPhone'] === $it['PickupPhone']
                && strlen($it['PickupLocation']) < strlen($it['DropoffLocation']) && strncmp($it['PickupLocation'], $it['DropoffLocation'], strlen($it['PickupLocation'])) === 0) {
            $it['PickupLocation'] = $it['DropoffLocation'];
            $it['PickupHours'] = $it['DropoffHours'];
        }
        $it['CarType'] = $this->re("#{$this->t('Vehicle Type')}\s+(.*?)(?:\s{2,}|Fuel at Start)#", $text);

        $it['RenterName'] = $this->re("#{$this->t('Name')}\s+(.+?)(?: *, *(?:Herr|Frau|Mr|Msr))?(?:\n|$)#", $table[0]);

        if ($amount = $this->re("#{$this->t('Estimated amount due')}:\s+(.+)#", $text)) {
            $it["TotalCharge"] = $this->amount($amount);
            $it["Currency"] = $this->currency($amount);
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($text, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($text, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $pdfs = $parser->searchAttachmentByName($this->pdfPattern);

        if (!isset($pdfs[0])) {
            return null;
        }
        $pdf = $pdfs[0];

        if (($this->text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return null;
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePdf($itineraries);
        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
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
//         $this->logger->debug('$str = '.print_r( $str,true));

        $in = [
            "#^(\d+)/(\d+)/(\d{2})$#", //06/19/16
            "#^(\d+:\d+)\s+(\d+)/(\d+)/(\d{2})$#", //08:25 06/19/16
            "#^\s*(\d{1,2})\s*([[:alpha:]]+)\s+(\d{2})\s+(\d{1,2})(\d{2})\s*$#u", // 11 mars 21 1400
        ];
        $out = [
            "$2.$1.20$3",
            "$3.$2.20$4, $1",
            "$1 $2 20$3, $4:$5",
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
            foreach ($pos as $k=>$p) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            'R'=> 'ZAR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
