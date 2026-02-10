<?php

namespace AwardWallet\Engine\alsa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Bus extends \TAccountChecker
{
    public $mailFiles = "alsa/it-604441528.eml, alsa/it-608352086.eml, alsa/it-610229706.eml, alsa/it-611744880.eml, alsa/it-613185681.eml, alsa/it-623306766.eml";
    public $subjects = [
        'Thank you for choosing Alsa',
    ];

    public $lang = 'en';
    public $pdfNamePattern = ".*pdf";

    public $depPoints = [];

    public $detectLang = [
        "en" => ["Your ticket"],
        "pt" => ["Para a sua segurança"],
        "es" => ["Tu billete"],
        "fr" => ["Votre billet"],
        "it" => ["Il tuo biglietto"],
    ];

    public static $dictionary = [
        "en" => [
        ],

        "es" => [
            'ALSA informs:' => 'Alsa informa:',
            'Your ticket'   => 'Tu billete',
            'Observations'  => 'Observaciones',
            //Bus
            'Booking reference'      => 'Localizador',
            'Ticket No.'             => ['Nº Billete'],
            'Purchase date:'         => 'Fecha de compra:',
            'Bus'                    => 'Autobús',
            'Seat'                   => ['Asiento', 'Asient'],
            'ts is mandatory.'       => 'de seguridad es',
            'a-de-privacidad'        => 'a-de-privacidad',
            'endStation'             => 'MONCLOA',
            'Total'                  => 'Importe total',
            'INFORMACION ADICIONAL:' => 'INFORMACION ADICIONAL:',
            'IMPORTANT:'             => 'IMPORTANTE:',
            //Train
            'Car'                        => 'Coche',
            'Class'                      => 'Clase',
            'Children 0 to 12 years old' => 'Niños 0 a 12 años',
        ],

        "fr" => [
            'ALSA informs:' => 'Alsa vous informe que',
            'Your ticket'   => 'Votre billet',
            'Observations'  => 'Observations',
            //Bus
            'Booking reference'      => 'Numéro de référence',
            'Ticket No.'             => ['No de billet'],
            'Purchase date:'         => "Date d'achat :",
            'Bus'                    => 'Autobus',
            'Seat'                   => ['Place assise'],
            'ts is mandatory.'       => 'nture de sécurité est',
            'a-de-privacidad'        => 'a-de-privacidad',
            'endStation'             => ['ESTACION SUR'],
            'Total'                  => 'Prix total',
            'INFORMACION ADICIONAL:' => 'INFORMACION ADICIONAL:',
            'IMPORTANT:'             => 'IMPORTANTE:',
            //Train
            //'Car' => '',
            //Class' => '',
            //'Children 0 to 12 years old' => '',
        ],

        "it" => [
            'ALSA informs:' => 'ALSA informa:',
            'Your ticket'   => 'Il tuo biglietto',
            'Observations'  => 'Osservazioni',
            //Bus
            'Booking reference' => 'Codice di prenotazione',
            'Ticket No.'        => ['N° di Biglietto'],
            'Purchase date:'    => "Data di acquisto:",
            'Bus'               => 'Autobus',
            'Seat'              => ['Sedile'],
            'ts is mandatory.'  => 'ture di sicurezza è',
            'a-de-privacidad'   => 'a-de-privacidad',
            //'endStation'        => [''],
            'Total'                  => 'Importo totale',
            'INFORMACION ADICIONAL:' => 'INFORMACION ADICIONAL:',
            'IMPORTANT:'             => 'IMPORTANTE:',
            //Train
            //'Car' => '',
            //Class' => '',
            //'Children 0 to 12 years old' => '',
        ],

        "pt" => [
            'ALSA informs:' => 'ALSA INTERNACIONAL',
            'Your ticket'   => 'Tu billete',
            'Observations'  => 'Observaciones',
            //Bus
            'Booking reference' => 'Localizador',
            'Ticket No.'        => ['Nº Billete'],
            'Purchase date:'    => "Fecha de compra:",
            'Bus'               => 'Autobús',
            'Seat'              => ['Asiento'],
            'ts is mandatory.'  => 'cinto de segurança é',
            'a-de-privacidad'   => 'a-de-privacidad',
            //'endStation'        => [''],
            'Total'                  => 'Importe total:',
            'INFORMACION ADICIONAL:' => 'INFORMACION ADICIONAL:',
            'IMPORTANT:'             => 'IMPORTANTE:',
            //Train
            //'Car' => '',
            //Class' => '',
            //'Children 0 to 12 years old' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@alsa.es') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) === true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        if (empty($text)) {
            return false;
        }

        $this->assignLang($text);

        if (stripos($text, $this->t('ALSA informs:')) !== false
        && stripos($text, $this->t('Your ticket')) !== false
        && stripos($text, $this->t('Observations')) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]alsa\.es$/', $from) > 0;
    }

    public function ParseBusPDF(Email $email, $text)
    {
        $text = preg_replace("/^(\s+\d+\s*\w+\s*\d{4}\s+\d+\s*\w+\s*\d{4})\n(\s*{$this->opt($this->t('Your ticket'))}\n)/m", "$2$1", $text);

        $text = preg_replace("/^(\d+\s*\w+\s*\d{4}.*\d{4}\n+)(\s*\d+\:\d+.*\d+\:\d+\n+)(\s*{$this->opt($this->t('Your ticket'))}\n+)/m", "$3                                                  $1$2", $text);
        /*22 December 2023      22 December 2023
        Your ticket     15:00     22:59*/
        $text = preg_replace("/^(\d+\s*\w+\s*\d{4}.*\d{4}\n+)(\s*{$this->opt($this->t('Your ticket'))})(\s*\d+\:\d+.*\d+\:\d+\n+)/m", "$2\n                                                  $1$3", $text);
        $b = $email->add()->bus();

        $currency = $this->normalizeCurrency($this->re("/{$this->opt($this->t('Total'))}[\:\s]*[\d\.\,]+(\D)/u", $text));

        if (preg_match_all("/{$this->opt($this->t('Total'))}[\:\s]*([\d\.\,]+)/", $text, $m)) {
            $summ = [];

            foreach ($m[1] as $price) {
                $summ[] = PriceHelper::parse($price, $currency);
            }

            $b->price()
                ->total(array_sum($summ))
                ->currency($currency);
        }

        if (preg_match_all("/{$this->opt($this->t('Booking reference'))}\n*\s+([A-z\d]{6,})/", $text, $m)) {
            $confs = array_unique($m[1]);

            foreach ($confs as $conf) {
                $b->general()
                    ->confirmation($conf);
            }
        }

        if (preg_match_all("/{$this->opt($this->t('Ticket No.'))}\n*\s+([\d\-]+)/", $text, $m)) {
            $b->setTicketNumbers(array_unique($m[1]), false);
        }

        if (preg_match_all("/\n\n[ ]{10,}\b(\D+)\b\n+\s+[A-Z\d]{6,}\n+\s+{$this->opt($this->t('Booking reference'))}/", $text, $m)) {
            $b->general()
                ->travellers(array_unique($m[1]));
        }

        $segments = $this->splitText($text, "/({$this->opt($this->t('Your ticket'))})/", true);

        foreach ($segments as $segment) {
            $segment = preg_replace("/({$this->opt($this->t('Your ticket'))})\n(\n+)/u", "$1", $segment);

            $segText = $this->re("/({$this->opt($this->t('Your ticket'))}.+{$this->opt($this->t('Booking reference'))})/su", $segment);

            $table = $this->splitCols($segText, [0, 50]);

            $bookingDate = $this->re("/{$this->opt($this->t('Purchase date:'))}\s*([\d\/]+)/", $table[0]);

            if (!empty($bookingDate)) {
                $b->general()
                    ->date(strtotime(str_replace('/', '.', $bookingDate)));
            }

            $depText = $this->re("/^\n*(.+)\n+{$this->opt($this->t('ts is mandatory.'))}/su", $table[1]);
            $depText = preg_replace("/\n+/", "\n", $depText);

            $depTable = $this->splitCols($depText);

            if (isset($depTable[0]) && preg_match("/\S([ ]{4,})\S/", $depTable[0])) {
                $pos = [];
                $depRows = array_filter(explode("\n", $depText));

                foreach ($depRows as $key => $depRow) {
                    if (preg_match("/\S[ ]{3,}(.+)/", $depRow, $m) && $key !== 0) {
                        $pos[] = stripos($depRow, $m[1]);
                    }
                }
                asort($pos);
                $pos = array_values($pos);

                $depTable = $this->splitCols($depText, [0, $pos[0] - 5]);
            }
            $addressText = $this->re("/{$this->opt($this->t('ts is mandatory.'))}\n+(.+){$this->opt($this->t('a-de-privacidad'))}/su", $table[1]);
            $addressTable = $this->splitCols($addressText);

            if (isset($depTable[0]) && preg_match("/\s*\n*(?<depDate>\d+\s*\w+\s*\d{4})\n(?<depTime>[\d\:]+)\n(?<depName>.+(?:\n[A-Z\s\S]{3,}\n*){0,4})/u", $depTable[0], $m)) {
                $depDate = trim($m['depDate']);
                $depTime = trim($m['depTime']);
                $depName = trim(str_replace("\n", " ", $m['depName']));
                $number = $this->re("/{$this->opt($this->t('Bus'))}\s*{$this->opt($this->t('Seat'))}\n+(?:.*\n){0,2}\s*(\d+)\s*\d+/", $segment);

                if (in_array($depDate . ' ' . $depTime . ' ' . $number, $this->depPoints) === false) {
                    $s = $b->addSegment();

                    $s->departure()
                        ->name(preg_replace("/\s+/", " ", $depName) . ', Spain')
                        ->date($this->normalizeDate($depDate . ', ' . $depTime));

                    if (isset($addressTable[0]) && !empty($addressTable[0])) {
                        $s->departure()
                            ->address(preg_replace("/\n\s*/", " ", $addressTable[0]));
                    }

                    if (!empty($number)) {
                        $s->setNumber($number);
                    } else {
                        $s->setNoNumber(true);
                    }

                    $seat = $this->re("/{$this->opt($this->t('Bus'))}\s*{$this->opt($this->t('Seat'))}\n+(?:.*\n){0,2}\s*\d+\s*(\d+)\n/", $segment);

                    if (!empty($seat)) {
                        $s->extra()
                            ->seat($seat);
                    }

                    if (preg_match("/(?<arrDate>\d+\s*\w+\s*\d{4}).*\n+(?<arrTime>[\d\:]+)\n+(?<arrName>.+(?:\n[A-Z\s\S]{3,}\n*){0,4})/u", $depTable[1], $m)
                    ) {
                        $s->arrival()
                            ->name(trim(preg_replace("/(?:\n*\s+|\,|\s+)/", " ", $m['arrName'])) . ', Spain')
                            ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']));

                        if (isset($addressTable[1]) && !empty($addressTable[1])) {
                            $s->arrival()
                                ->address(preg_replace("/\n\s*/", " ", $addressTable[1]));
                        }
                    }

                    $this->depPoints[] = $depDate . ' ' . $depTime . ' ' . $number;
                } else {
                    $segments = $b->getSegments();

                    foreach ($segments as $seg) {
                        if (stripos($seg->getNumber(), $number) !== false) {
                            $seat = $this->re("/{$this->opt($this->t('Bus'))}\s*{$this->opt($this->t('Seat'))}\n+(?:.*\n){0,2}\s+\d+\s*(\d+)\n/", $segment);

                            if (!empty($seat)) {
                                $seg->extra()
                                    ->seat($seat);
                            }
                        }
                    }

                    continue;
                }

                if (count($this->depPoints)) {
                    $this->depPoints[] = $depDate . ' ' . $depTime . ' ' . $depName;
                }
            }
        }
    }

    public function ParseTrainPDF(Email $email, $text)
    {
        $b = $email->add()->train();

        $currency = $this->normalizeCurrency($this->re("/{$this->opt($this->t('Total'))}[\:\s]*[\d\.\,]+(\D)/u", $text));

        if (preg_match_all("/{$this->opt($this->t('Total'))}[\:\s]*([\d\.\,]+)/", $text, $m)) {
            $summ = [];

            foreach ($m[1] as $price) {
                $summ[] = PriceHelper::parse($price, $currency);
            }

            $b->price()
                ->total(array_sum($summ))
                ->currency($currency);
        }

        if (preg_match_all("/{$this->opt($this->t('Booking reference'))}\n*\s+([A-z\d]{6,})/", $text, $m)) {
            $confs = array_unique($m[1]);

            foreach ($confs as $conf) {
                $b->general()
                    ->confirmation($conf);
            }
        }

        if (preg_match_all("/{$this->opt($this->t('Ticket No.'))}\n*\s+([\d\-]+)/", $text, $m)) {
            $b->setTicketNumbers(array_unique($m[1]), false);
        }

        if (preg_match_all("/\n\n[ ]{10,}\b(\D+)\b\n+\s+[A-Z\d]{6,}\n+\s+{$this->opt($this->t('Booking reference'))}/", $text, $m)) {
            $b->general()
                ->travellers(array_unique($m[1]));
        }

        $segments = $this->splitText($text, "/({$this->opt($this->t('Your ticket'))})/", true);

        foreach ($segments as $segment) {
            $segText = $this->re("/({$this->opt($this->t('Your ticket'))}.+{$this->opt($this->t('Booking reference'))})/su", $segment);

            $segText = preg_replace("/({$this->opt($this->t('Total'))}[\:\s]*[\d\.\,]+\S\s+)(\(.+\))/u", "$1                   ", $segText);
            $segText = preg_replace("/({$this->opt($this->t('Children 0 to 12 years old'))}[\:\s]*)(\(.+\))/u", "$1                            ", $segText);

            $table = $this->splitCols($segText, [0, 50]);

            $bookingDate = $this->re("/{$this->opt($this->t('Purchase date:'))}\s*([\d\/]+)/", $table[0]);

            if (!empty($bookingDate)) {
                $b->general()
                    ->date(strtotime(str_replace('/', '.', $bookingDate)));
            }

            $depTable = $this->splitCols($table[1], [0, 45]);

            if (isset($depTable[0]) && preg_match("/\s*\n*(?<depDate>\d+\s*\w+\s*\d{4})\n+(?<depTime>[\d\:]+)\n+(?<depName>(?:.+\n){1,3})\n*(?<address>(?:.+\n){1,5})\n*{$this->opt($this->t('Car'))}/u", $depTable[0], $m)) {
                $depDate = trim($m['depDate']);
                $depTime = trim($m['depTime']);
                $depName = trim(str_replace("\n", " ", $m['depName']));
                $number = $this->re("/{$this->opt($this->t('Bus'))}\s*{$this->opt($this->t('Seat'))}\n+(?:.*\n){0,2}\s*(\d+)\s*\d+/", $segment);

                if (in_array($depDate . ' ' . $depTime . ' ' . $number, $this->depPoints) === false) {
                    $s = $b->addSegment();

                    $s->departure()
                        ->name(preg_replace("/\s+/", " ", $depName) . ', Spain')
                        ->date($this->normalizeDate($depDate . ', ' . $depTime))
                        ->address(preg_replace("/\s+/", " ", $m['address']));

                    if (!empty($number)) {
                        $s->setNumber($number);
                    } else {
                        $s->setNoNumber(true);
                    }

                    if (preg_match("/{$this->opt($this->t('Car'))}\s*{$this->opt($this->t('Seat'))}\s*{$this->opt($this->t('Class'))}\n+\s*(?<car>\d+)\s+(?<seat>\d+)\s*(?<cabin>.+)\n/", $segment, $m)) {
                        $s->extra()
                            ->cabin($m['cabin'])
                            ->seat($m['seat'])
                            ->car($m['car']);
                    }

                    $s->setServiceName($this->re("/Línea:\s*(.+)\n/", $segment));

                    if (preg_match("/\s*\n*(?<arrDate>\d+\s*\w+\s*\d{4})\n+(?<arrTime>[\d\:]+)\n+(?<arrName>(?:.+\n){1,3})\n*(?<address>(?:.+\n){1,5})\n+/u", $depTable[1], $m)
                    ) {
                        $s->arrival()
                            ->name(trim(preg_replace("/(?:\n*\s+|\,|\s+)/", " ", $m['arrName'])) . ', Spain')
                            ->date($this->normalizeDate($m['arrDate'] . ', ' . $m['arrTime']))
                            ->address(preg_replace("/\s+/", " ", $m['address']));
                    }

                    $this->depPoints[] = $depDate . ' ' . $depTime . ' ' . $number;
                } else {
                    $segments = $b->getSegments();

                    foreach ($segments as $seg) {
                        if (stripos($seg->getNumber(), $number) !== false) {
                            $seat = $this->re("/{$this->opt($this->t('Bus'))}\s*{$this->opt($this->t('Seat'))}\n+(?:.*\n){0,2}\s+\d+\s*(\d+)\n/", $segment);

                            if (!empty($seat)) {
                                $seg->extra()
                                    ->seat($seat);
                            }
                        }
                    }

                    continue;
                }

                if (count($this->depPoints)) {
                    $this->depPoints[] = $depDate . ' ' . $depTime . ' ' . $depName;
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            $this->assignLang($text);

            if (stripos($text, $this->t('Bus')) !== false) {
                $this->ParseBusPDF($email, $text);
            } elseif (stripos($text, $this->t('Car')) !== false) {
                $this->ParseTrainPDF($email, $text);
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s);
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }

    private function splitText($textSource = '', $pattern = '', $saveDelimiter = false)
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, null, PREG_SPLIT_DELIM_CAPTURE);
            array_shift($textFragments);

            for ($i = 0; $i < count($textFragments) - 1; $i += 2) {
                $result[] = $textFragments[$i] . $textFragments[$i + 1];
            }
        } else {
            $result = preg_split($pattern, $textSource);
            array_shift($result);
        }

        return $result;
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $value = mb_substr($row, $p, null, 'UTF-8');

                if (preg_match("/^[ ]{40,}\S/", $value)) {
                    $cols[$k][] = $value;
                } else {
                    $cols[$k][] = trim($value);
                }
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
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

    private function assignLang($text)
    {
        foreach ($this->detectLang as $lang => $arrayWords) {
            foreach ($arrayWords as $word) {
                if (stripos($text, $word) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^(\d+)\s*(\w+)\s*(\d{4})\,\s*([\d\:]+)$#u", //10 octubre 2023, 17:30
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], 'es')) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function normalizeCurrency($string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'AUD' => ['A$'],
            'EUR' => ['€', 'Euro'],
            'USD' => ['US Dollar'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return $string;
    }
}
