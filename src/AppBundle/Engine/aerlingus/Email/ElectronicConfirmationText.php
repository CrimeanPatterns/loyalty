<?php

namespace AwardWallet\Engine\aerlingus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;

class ElectronicConfirmationText extends \TAccountChecker
{
    public $mailFiles = "aerlingus/it-4044207.eml, aerlingus/it-4146584.eml, aerlingus/it-4487068.eml, aerlingus/it-4583803.eml, aerlingus/it-4634573.eml, aerlingus/it-6334151.eml, aerlingus/it-6380242.eml, aerlingus/it-6434661.eml, aerlingus/it-7609467.eml, aerlingus/it-7760719.eml, aerlingus/it-8900744.eml";

    protected $detectSubject = [
        // it
        'Conferma elettronica del PNR:',
        // nl
        'Bevestigingsbericht voor PNR ref.:',
        // pt
        'Confirmación por correo electrónico del código de reserva (PNR):',
        // en
        'Email confirmation for PNR Ref:',
        // pt
        'E-mail de confirmação da ref.ª de PNR:',
    ];

    protected $airlineConfirmation;
    protected static $langDetectors = [
        'it' => [
            'Grazie per aver prenotato con Aer Lingus.',
        ],
        'nl' => [
            'Bedankt voor uw booking met Aer Lingus.',
        ],
        'es' => [
            'Gracias por reservar su vuelo con Aer Lingus.',
        ],
        'en' => [
            'Thank you for booking with Aer Lingus.',
            'For Aer Lingus journeys between',
        ],
        'de' => [
            'Vielen Dank für Ihre Buchung bei Aer Lingus',
        ],
        'pt' => [
            'Obrigada por ter feita a sua reserva na Aer Lingus',
        ],
    ];

    protected $lang = null;
    protected $dict = [
        'it' => [
            'BOOKING REF' => 'RIFERIMENTO PRENOTAZIONE',
            'DATE' => 'DATA',
            'ITINERARY' => 'ITINERARIO',
            'DEP|ARR' => 'PAR|ARR', // regexp
            'Seat Number\/s' => 'Numero\/i posto\/i', // regexp
            'All Times Local' => 'Tutti gli orari sono locali',
            'Ticket Numbers?' => 'Numero biglietto', // regexp
            'Date of Ticket Issue' => 'Data emissione biglietto',
            'Frequent Flyer Number\(s\)' => 'Numero/i Frequent Flyer', // regexp
            'Fare details' => 'Dettagli tariffa',
            'Total Taxes' => 'Totale Commissioni',
            'GRAND TOTAL' => 'TOTALE GENERALE',
            'Payment' => 'Pagamento',
        ],
        'nl' => [
            'BOOKING REF' => 'BOEKINGSREF.',
            'DATE' => 'DATUM',
            'ITINERARY' => 'REISSCHEMA',
            'DEP|ARR' => 'VERT|AANK', // regexp
//            'Seat Number\/s' => '', // regexp
            'All Times Local' => 'Alle tijden zijn lokaal',
            'Ticket Numbers?' => 'Ticketnummer', // regexp
            'Date of Ticket Issue' => 'Afgiftedatum van ticket',
            'Frequent Flyer Number\(s\)' => 'Frequent Flyer-nummer\(s\)', // regexp
            'Fare details' => 'Vluchtgegevens',
            'Total Taxes' => 'Totale Kosten',
            'GRAND TOTAL' => 'TOTAAL',
            'Payment' => 'Betaling',
        ],
        'es' => [
            'BOOKING REF' => 'DIGO DE RESERVA', // ['CÓDIGO DE RESERVA','CDIGO DE RESERVA']
            'DATE' => 'FECHA',
            'ITINERARY' => 'ITINERARIO',
            'DEP|ARR' => 'SAL|LLG', // regexp
            'Seat Number\/s' => 'N(?:ú|ï¿½|)mero de asiento', // regexp
            'All Times Local' => 'Todos los horarios se proporcionan en hora local',
            'Ticket Numbers?' => 'Nú?mero de billete', // regexp
            'Date of Ticket Issue' => 'Fecha de emisión del billete',
            'Frequent Flyer Number\(s\)' => 'Nú?mero de viajero frecuente', // regexp
            'Fare details' => 'Detalles de la tarifa',
            'Total Taxes' => 'Total Tasas',
            'GRAND TOTAL' => 'SUMA TOTAL',
            'Payment' => 'Pago',
        ],
        'de' => [
            'BOOKING REF' => 'BUCHUNGSNUMMER',
            'DATE' => 'DATUM',
            'ITINERARY' => 'STRECKE',
            'DEP|ARR' => 'ABFL|ANK', // regexp
//            'Seat Number\/s' => '', // regexp
            'All Times Local' => 'Alle Zeitangaben in Ortszeit',
            'Ticket Numbers?' => 'Flugschein-Nr', // regexp
//            'Date of Ticket Issue' => '',
//            'Frequent Flyer Number\(s\)' => '', // regexp
            'Fare details' => 'Beförderungspreis',
//            'Total Taxes' => '',
//            'GRAND TOTAL' => '',
            'Payment' => 'Zahlung',
        ],
        'pt' => [
            'BOOKING REF' => 'REF.ª DA RESERVA',
            'DATE' => 'DATA',
            'ITINERARY' => 'ITINERÁRIO',
            'DEP|ARR' => 'PAR|CHE', // regexp
//            'Seat Number\/s' => '', // regexp
            'All Times Local' => 'Todas as horas indicadas são horas locais',
            'Ticket Numbers?' => 'Nú?mero do Bilhete', // regexp
            'Date of Ticket Issue' => 'Data de Emissão do Bilhete',
//            'Frequent Flyer Number\(s\)' => '', // regexp
            'Fare details' => 'Detalhes da tarifa',
//            'Total Taxes' => 'Total De Taxas',
//            'GRAND TOTAL' => 'TOTAL',
            'Payment' => 'Pagamento',
        ],
        'en' => [
//            'BOOKING REF' => '',
//            'DATE' => '',
//            'ITINERARY' => '',
//            'DEP|ARR' => '', // regexp
//            'Seat Number\/s' => '', // regexp
//            'All Times Local' => '',
//            'Ticket Numbers?' => '', // regexp
//            'Date of Ticket Issue' => '',
//            'Frequent Flyer Number\(s\)' => '', // regexp
//            'Fare details' => '',
//            'Total Taxes' => '', // to check
//            'GRAND TOTAL' => '',
//            'Payment' => '',
        ],
    ];

    protected $regexps = [
        'flightHeader' => [
            'it' => '/\s+([A-Z\d]{2})\s*(\d+).*?\s+CLASSE\s+([A-Z]{1})\/([A-Z]+)\s+([A-Z]+)/',
            'nl' => '/\s+([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1})\/([A-Z]+)\s+CLASS\s+([A-Z]+)/',
            'es' => '/\s+([A-Z\d]{2})\s*(\d+)\s+CLASE\s+([A-Z]{1})\/([A-Z]+)\s+([A-Z]+)/',
            'en' => '/\s+([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1})\s*(?:\/([A-Z]+))?\s+CLASS\s+([A-Z]+)/',
            'de' => '/\s+([A-Z\d]{2})\s*(\d+)\s+([A-Z]{1})\s*(?:\/([A-Z]+))?\s+CLASS\s+([A-Z]+)/ui',
            'pt' => '/   ([A-Z\d]{2})\s*(\d+)\s+CLASSE\s+([A-Z])[\s\/]*(\w+)\s+(\w+)/i',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from'], $headers['subject'])) {
            return false;
        }
        if (stripos($headers['from'], 'bookingsit@aerlingus.com') == false
            && stripos($headers['from'], 'bookingsnl@aerlingus.com') == false
            && stripos($headers['from'], 'bookingses@aerlingus.com') == false
            && stripos($headers['from'], 'bookingsen@aerlingus.com') == false
        ) {
            return false;
        }
        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers['subject'], $dSubject) !== false) {
                return true;
            }
        }
        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $text = $parser->getPlainBody();

        if (empty($text)) {
            $text = text($parser->getHTMLBody());
        }
        $text = preg_replace("#<br[^>]+>#", "\n", $text);
        $text = str_replace("&nbsp;", " ", $text);

        foreach (self::$langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($text, $line) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@aerlingus.com') !== false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $text = $parser->getPlainBody();

        if (empty($text)) {
            $text = text($parser->getHTMLBody());
        }

        $text = preg_replace("#<br[^>]+>#", "\n", $text);
        $text = str_replace("&nbsp;", " ", $text);
        $this->http->SetBody($text);

        foreach (self::$langDetectors as $lang => $lines) {
            foreach ($lines as $line) {
                if (stripos($text, $line) !== false) {
                    $this->lang = $lang;
                }
            }
        }
        $this->parseEmail($email, $text);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$langDetectors);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$langDetectors);
    }

    protected function t($s)
    {
        if (isset($this->lang) && isset($this->dict[$this->lang][$s])) {
            return $this->dict[$this->lang][$s];
        } else {
            return $s;
        }
    }

    protected function parseEmail(Email $email, $htmlBody)
    {
        $htmlBody = str_replace("> ", "", $htmlBody);
        $htmlBody = str_replace(">\n", "\n", $htmlBody);

        $f = $email->add()->flight();

        if (preg_match('/[A-Z\d]{5,6}/', $this->findСutSection($htmlBody, $this->t('BOOKING REF') . ':', PHP_EOL), $matches)) {
            $f->general()
                ->confirmation($matches[0]);
        }
        if (preg_match_all('/^[ \*]*(?<name>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *REF[: ]*(?<ref>[A-Z\d]{5,7})[ \*]*$/m', $this->findСutSection($htmlBody, $this->t('BOOKING REF') . ':', $this->t('DATE') . ':'), $matches)) {
            // UA REF:    IR8QH7
            foreach ($matches['ref'] as $i => $v) {
                $this->airlineConfirmation[$matches['name'][$i]] = $v;
            }
        }

        $f->general()
            ->date($this->normalizeDate($this->findСutSection($htmlBody, $this->t('DATE') . ':', "\n")));

        if (preg_match("#" . $this->t('Ticket Numbers?') . "\s*([\d\-]+)\s+#u", $htmlBody, $m)) {
            $f->issued()
                ->ticket($m[1], false);
        }

        if (preg_match("#\n[ ]*" . $this->t('Frequent Flyer Number\(s\)') . "[:\s]+(.+?)\n[=]{5,}#su", $htmlBody, $m) > 0) {
            if (preg_match_all("#^([A-Z\d]{5,})#m", $m[1], $v)) {
                $f->program()
                    ->accounts($v[1], false);
            }
        }
        $this->parsePayments($f, $this->findСutSection($htmlBody, $this->t('Fare details') . ':', $this->t('Payment')));
        $this->parseSegments($f, $this->findСutSection($htmlBody, $this->t('ITINERARY') . ':', $this->t('All Times Local')));

        return $email;
    }

    protected function parsePayments(Flight $f, $htmlBody)
    {
        if (preg_match_all('/^ *\d{2} +[A-Z]+ +([A-Z]{3})\s*(\d[\d., ]*) +[A-Z]{3}\s*(\d[\d., ]*) +[A-Z]{3}\s*\d[\d., ]*/m', $htmlBody, $match)) {
            $currency = $match[1][0];

            $f->price()
                ->currency($currency)
                ->cost(array_sum(array_map(function ($v) use ($currency) {return PriceHelper::parse($v, $currency);}, $match[2])))
            ;
            $tax = array_sum(array_map(function ($v) use ($currency) {return PriceHelper::parse($v, $currency);}, $match[3]));

            if (preg_match("/^ *{$this->t('GRAND TOTAL')} +{$currency}\s*(\d[\d., ]*)\n/m", $htmlBody, $match)) {
                $f->price()
                    ->total(PriceHelper::parse($match[1], $currency))
                ;
            }
            if (preg_match("/^ *{$this->t('Total Taxes')} +{$currency}\s*(\d[\d., ]*)\n/m", $htmlBody, $match)) {
                $tax += PriceHelper::parse($match[1], $currency);
            }
            $f->price()
                ->tax($tax);
            ;
        }
    }

    protected function parseSegments(Flight $f, $htmlBody)
    {
        $array = preg_split('/\n([^\n]*?[A-Z\d]{2}\s*\d+[^\n]*?\sCLAS)/', $htmlBody, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = [];

        $forpax = array_values(array_filter(array_map("trim", explode("\n", preg_replace("# {2,}#", "\n", str_replace("=", "", array_shift($array)))))));
        $forpax = preg_replace("/^(DHR|SIG\.|SIG\.RA|SRTA|SRA\.|MR|MS|SR|MISS) /", '', $forpax);
        $f->general()
            ->travellers($forpax, true);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        foreach ($result as $key => $sText) {
            if (mb_strlen($sText) < 50) {
                continue;
            }
            $s = $f->addSegment();

            if (preg_match($this->regexps['flightHeader'][$this->lang], $sText, $match)) {

                $s->airline()
                    ->name($match[1])
                    ->number($match[2])
                ;
                if (isset($this->airlineConfirmation[$match[1]])) {
                    $s->airline()
                        ->confirmation($this->airlineConfirmation[$match[1]]);
                }

                $s->extra()
                    ->bookingCode($match[3], true, true)
                    ->cabin($match[4], true, true)
                    ->status($match[5] ?? null, true, true)
                ;
            }

            $regexp = '/\b(?:'.$this->t('DEP|ARR').')\s+(?<name>.+?)\s+\w+\s+(?<date>\d+[A-Z]{3}\d+)\s+(?<time>\d+(?:\.\d+ *[AP]M|[ ]*NOON))(?:.*\s+(?<code>[A-Z]{3})[ \-]*TERMINAL +(?<terminal>[A-Z\d]{1,3}))?/';
            if (preg_match_all($regexp, $sText, $match)) {
//                $this->logger->debug('$match = '.print_r( $match,true));

                // Departure
                $s->departure()
                    ->name(trim($match['name'][0]))
                    ->date($this->normalizeDate($match['date'][0] . ', ' . $this->correctTimeString($match['time'][0])))
                    ->terminal($match['terminal'][0] ?? null, true, true)
                ;
                if (!empty($match['code'][0])) {
                    $s->departure()
                        ->code($match['code'][0]);
                } else {
                    $s->departure()
                        ->noCode();
                }

                // Arrival
                $s->arrival()
                    ->name(trim($match['name'][1]))
                    ->date($this->normalizeDate($match['date'][1] . ', ' . $this->correctTimeString($match['time'][1])))
                    ->terminal($match['terminal'][1] ?? null, true, true)
                ;
                if (!empty($match['code'][1])) {
                    $s->arrival()
                        ->code($match['code'][1]);
                } else {
                    $s->arrival()
                        ->noCode();
                }
            }

            if (preg_match("#{$this->t('Seat Number\/s')}[ :]+(?<seats>.+)#u", $sText, $match)) {
                if (preg_match_all("#\b(\d+[A-z])\b#", $match['seats'], $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }
            }
        }
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function findСutSection($input, $searchStart, $searchFinish)
    {
        $input = mb_strstr(mb_strstr($input, $searchStart), $searchFinish, true);

        return trim(mb_substr($input, mb_strlen($searchStart)));
    }

    private function normalizeDate($dateStr)
    {
        $date = null;
//        $this->logger->debug('$dateStr = '.print_r( $dateStr,true));
        if (preg_match("#^(\d+)\s*([[:alpha:]]+)\s*(\d{4})(?:[,\s]+(\d{1,2}[.:]\d{2}(?:\s*[ap]m)?))?$#ui", $dateStr, $v)) {
            $v[2] = $this->lang === 'en' ? $v[2] : $this->monthToEn($v[2]);
            $date = $v[1] . ' ' . $v[2] . ' ' . $v[3] . (!empty($v[4])? ', ' . $v[4] : '');
        } elseif (preg_match("#^(\d+)\s*([[:alpha:]]+)\s*(\d{2})(?:[,\s]+(\d{1,2}[.:]\d{2}(?:\s*[ap]m)?))?$#ui", $dateStr, $v)) {
            $v[2] = $this->lang === 'en' ? $v[2] : $this->monthToEn($v[2]);
            $date = $v[1] . ' ' . $v[2] . ' 20' . $v[3] . (!empty($v[4])? ', ' . $v[4] : '');
        }
//        $this->logger->debug('$date = '.print_r( $date,true));

        return strtotime($date);
    }

    protected function monthToEn($month)
    {
        $m = MonthTranslate::translate($month, $this->lang);
        if (!empty($m)) {
            $month = $m;
        }
        return $month;
    }

    private function correctTimeString($time)
    {
//        $this->logger->debug('$time = '.print_r( $time,true));
        $time = preg_replace("#^\s*(\d+)\.(\d+)\s*([ap]m)\s*#i", '$1:$2 $3', $time);
        if (preg_match("#^\s*(\d+)[:\.](\d+)\s*([ap]m)\s*$#i", $time, $m)) {
            if (($m[1] == 0 && stripos($m[3], 'am') !== false) || $m[1] > 12) {
                return $m[1] . ":" . $m[2];
            }
        } elseif (preg_match('/^\s*(\d+)\s+noon\s*$/i', $time, $m)) {
            return $m[1] . ':00';
        }
//        $this->logger->debug('$time 2 = '.print_r( $time,true));

        return $time;
    }
}
