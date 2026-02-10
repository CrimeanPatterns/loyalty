<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Pdf extends \TAccountChecker
{
    public $mailFiles = "copaair/it-184769345.eml, copaair/it-30910625.eml, copaair/it-31002992.eml, copaair/it-84602721.eml";

    private $from = '/[@\.]copaair\.com/i';

    private $detects = [
        'es' => 'Gracias por preferir a Copa Airlines para realizar su viaje',
        'pt' => 'Obrigado por escolher a Copa Airlines para seus planos de viagem',
        'en' => 'Thank you for choosing Copa Airlines for your travel plans',
    ];

    private $prov = 'Copa Airlines';
    private $year;

    private $lang = 'en';

    private static $dict = [
        'es' => [ // it-30910625.eml
            'Total'                      => ['Total de la Tarifa Aérea', 'Total'],
            'ELECTRONIC TICKET FOR'      => 'BOLETO ELECTRÓNICO POR',
            'Thank you for choosing'     => 'Gracias por preferir a',
            'Order ID'                   => ['Order ID', 'Id de Orden'],
            'Manage'                     => ['Administrar', 'Maneje'],
            'PASSENGER DETAILS'          => 'DETALLES DEL PASAJERO',
            'FLIGHT ITINERARY'           => 'ITINERARIO DE VUELO',
            'Name'                       => 'Nombre',
            'Frequent Flyer #'           => '# de Viajero Frecuente',
            'Star Alliance Status'       => 'Nivel Star Alliance',
            'Ticket Number'              => 'Número de Boleto',
            'AIR TRANSPORTATION CHARGES' => 'CARGOS DE TRANSPORTE AÉREO',
            'Flight Number'              => 'Número de Vuelo',
            'Flight Duration'            => 'Duración del vuelo',
            'Aircraft'                   => 'Aeronave',
            'BOOKING CONFIRMATION'       => 'CONFIRMACIÓN DE RESERVA',
        ],
        'pt' => [ // it-84602721.eml
            'Total'                      => ['Total da tarifa aérea', 'Total'],
            'ELECTRONIC TICKET FOR'      => 'BILHETE ELETRÔNICO PARA',
            'Thank you for choosing'     => 'Obrigado por escolher a',
            'Order ID'                   => 'Reserva',
            'Manage'                     => ['Administre', 'reserva', 'Reservas'],
            'PASSENGER DETAILS'          => 'DETALHES DO PASSAGEIRO',
            'FLIGHT ITINERARY'           => 'ITINERÁRIO DO VOO',
            'Name'                       => 'Nome',
            'Frequent Flyer #'           => ['Nº de passageiro frequente #', 'Nº de passageiro'],
            'Star Alliance Status'       => 'Status Star Alliance',
            'Ticket Number'              => 'Número da passagem aérea',
            'AIR TRANSPORTATION CHARGES' => 'TARIFAS DE TRANSPORTE AÉREO',
            'Flight Number'              => 'Número do voo',
            'Flight Duration'            => 'Duração do voo',
            'Aircraft'                   => 'Aeronave',
            'BOOKING CONFIRMATION'       => 'CONFIRMAÇÃO DE RESERVA',
        ],
        'en' => [// it-31002992.eml
            'Flight Number'        => ['Flight Number', 'FlightNumber'],
            'Frequent Flyer #'     => ['Frequent Flyer #', 'Frequent FLyer#'],
            'Star Alliance Status' => ['Star Alliance Status', 'Star Alliance Stauts'],
            'Total'                => ['Total', 'Total Air Fare'],
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 === count($pdfs)) {
            return null;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        foreach ($this->detects as $lang => $detect) {
            if (false !== stripos($body, $detect)) {
                $this->lang = substr($lang, 0, 2);
                $this->parseEmail($email, $body);

                break;
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (0 === count($pdfs)) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        if (false === stripos($body, $this->prov)) {
            return false;
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect)) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, string $text): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}',
        ];

        $f = $email->add()->flight();

        $this->year = $this->re("/BOLETO ELECTRÓNICO.+\n.+\s(\d{4})\n/u", $text);

        $totalText = $this->re("/\n *{$this->opt($this->t('Total'))}(?: {2,}| *: *)(\d[\d,. ]* ?[^\d\s]{1,5}|[^\d\s]{1,5} ?\d[\d,. ]*|\d[\d,. ]*)\n/u", $text);

        if (preg_match('/^(?<amount>\d[,.\d ]*)[ ]*(?<currency>[A-Z]{3})$/', $totalText, $m)
            || preg_match('/^(?<currency>[A-Z]{3}) *(?<amount>\d[,.\d ]*)$/', $totalText, $m)
        ) {
            $f->price()
                ->total($m['amount'])
                ->currency($m['currency']);

            $feesText = $this->re("/((?:\n+^[ ]*[A-Z]{2}[ ]+\d[,.\d ]*(?:[^\d)(]+)?$)+)\n+^[ ]*{$this->opt($this->t('Total'))}[ ]+\d/m", $text);

            if (preg_match_all("/^[ ]*(?<name>[A-Z]{2})[ ]+(?<charge>\d[,.\d ]*)(?:" . preg_quote($m['currency'], '/') . ")?$/m", $feesText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $fee) {
                    $f->price()->fee($fee['name'], $fee['charge']);
                }
            }
        } elseif (preg_match('/^\s*(?<amount>\d[,.\d ]*)\s*$/', $totalText, $m)) {
            if (!empty($m['amount'])) {
                $f->price()
                    ->total($m['amount']);
            }

            $currency = $this->re("/\n *\d+ *ADULT(?: *\d+[+]*)? +([A-Z]{3})[ \d]/", $text);

            if (!empty($currency)) {
                $f->price()
                    ->currency($currency);
            }
        }

        $orderText = $this->cutText($this->t('ELECTRONIC TICKET FOR'), $this->t('Thank you for choosing'), $text);

        if (empty($orderText)) {
            $orderText = $this->cutText($this->t('BOOKING CONFIRMATION'), $this->t('Thank you for choosing'), $text);
        }

        if (($conf = $this->re("/[ ]+{$this->opt($this->t('Order ID'))}[ ]*.*\n?[ ]+{$this->opt($this->t('Manage'))}.*[ ]*\n(?: {20,}.*\n)?[ ]{0,20}([A-Z\d]{5,7})\s*/u", $orderText))) {
            $f->general()->confirmation($conf);
        } elseif (!empty($conf = $this->re("/\n\s*([A-Z\d]{5,7})\s*\n\s*{$this->opt($this->t('Thank you for choosing'))}/s", $text))) {
            $f->general()
                ->confirmation($conf);
        }

        $travellerText = $this->cutText($this->t('PASSENGER DETAILS'), $this->t('FLIGHT ITINERARY'), $text);
        $tablePos = [0];

        if (preg_match("/^((([ ]*{$this->opt($this->t('Name'))}.+){$this->opt($this->t('Frequent Flyer #'))}.+){$this->opt($this->t('Star Alliance Status'))}.+){$this->opt($this->t('Ticket Number'))}$/m", $travellerText, $m)) {
            $tablePos = array_merge($tablePos, [mb_strlen($m[3]), mb_strlen($m[2]), mb_strlen($m[1])]);
        } elseif (preg_match("/^(([ ]*{$this->opt($this->t('Name'))}.+){$this->opt($this->t('Frequent Flyer #'))}.+){$this->opt($this->t('Star Alliance Status'))} *$/m", $travellerText, $m)) {
            $tablePos = array_merge($tablePos, [mb_strlen($m[2]), mb_strlen($m[1])]);
        } elseif (preg_match("/{$this->opt($this->t('Name'))}.+{$this->opt($this->t('Frequent Flyer #'))}.+{$this->opt($this->t('Star Alliance Status'))}.*\n/u", $travellerText)) {
            $paxs = $this->re("/{$this->opt($this->t('Star Alliance Status'))}\n([A-Z\s]+)/s", $travellerText);
            $f->general()->travellers(array_filter(explode("\n", $paxs)));
        }

        if (count($tablePos) !== 4 && count($tablePos) !== 3) {
            $this->logger->debug('Warning! Wrong passengers table!');
        }

        $textForPax = $this->re("/{$this->t('Name')}.+{$this->opt($this->t('Frequent Flyer #'))}.*(?:\n+[ ]{25,}\S.+)?\n+([\s\S]+?)\s*$/", $travellerText);
        $tablePax = $this->splitCols($textForPax, $tablePos);

        if (!empty($tablePax[0])) {
            $passengers = array_filter(preg_split('/[ ]*\n+[ ]*/', trim($tablePax[0])), function ($item) use ($patterns) {
                return preg_match("/^{$patterns['travellerName']}$/u", $item) > 0;
            });

            foreach ($passengers as $passenger) {
                $f->addTraveller($passenger);
            }
        }

        if (!empty($tablePax[1])) {
            $ffNumbers = array_filter(preg_split('/[ ]*\n+[ ]*/', trim($tablePax[1])), function ($item) {
                return preg_match("/^[-A-Z\d]{5,}$/", $item) > 0;
            });

            foreach ($ffNumbers as $ffNumber) {
                $f->program()->account($ffNumber, false);
            }
        }

        if (!empty($tablePax[3] ?? [])) {
            $ticketNumbers = array_filter(preg_split('/[ ]*\n+[ ]*/', trim($tablePax[3])), function ($item) use ($patterns) {
                return preg_match("/^{$patterns['eTicket']}$/", $item) > 0;
            });

            foreach ($ticketNumbers as $ticketNumber) {
                $f->addTicketNumber($ticketNumber, false);
            }
        }

        $sText = $this->cutText($this->t('FLIGHT ITINERARY'), $this->t('AIR TRANSPORTATION CHARGES'), $text);
        $segments = $this->splitter("/([ ]*.+\([ ]*[A-Z]{3}[ ]*\)[ ]*-[ ]*.+\([ ]*[A-Z]{3}[ ]*\)[ ]*-[ ]*(?i){$this->opt($this->t('Flight Number'))}(?-i)[ ]*-[ ]*[A-Z \d]{3,}[ ]*-[ ]*.*)/u", $sText);

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $re = "/[ ]*(?<DepName>.+)\([ ]*(?<DepCode>[A-Z]{3})[ ]*\)[ ]*-[ ]*(?<ArrName>.+)\([ ]*(?<ArrCode>[A-Z]{3})[ ]*\)[ ]*-[ ]*{$this->opt($this->t('Flight Number'))}[ ]*-[ ]*(?<AName>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<FNum>\d+)[ ]*-[ ]*(?<Class>.+)?\n/";

            if (preg_match($re, $segment, $m)) {
                $s->departure()
                    ->name($m['DepName'])
                    ->code($m['DepCode']);
                $s->arrival()
                    ->name($m['ArrName'])
                    ->code($m['ArrCode']);
                $s->airline()
                    ->name($m['AName'])
                    ->number($m['FNum']);

                if (isset($m['Class'])) {
                    $s->extra()
                        ->cabin($m['Class']);
                }
            }

            $tablePos = [0];

            if (preg_match("/^([ ]*[-[:alpha:]]+[ ]+[[:alpha:]]{3,}[ ]+\d{1,2}[ ]*,[ ]*\d{2,4}[ ]{2,})[-[:alpha:]]+[ ]+[[:alpha:]]{3,}[ ]+\d{1,2}[ ]*,[ ]*\d{2,4}[ ]{2}/mu", $segment, $m)) {
                $tablePos[1] = mb_strlen($m[1]);
            }

            if (preg_match("/^((?:[ ]*{$patterns['time']}|.{28,})[ ]{2,}){$patterns['time']}(?:[ ]{2}|$)/m", $segment, $m)
                && (empty($tablePos[1]) || $tablePos[1] > mb_strlen($m[1]))
            ) {
                $tablePos[1] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+ ){$this->opt($this->t('Flight Duration'))}[ ]*[:]+[^:\n]*$/imu", $segment, $m)) {
                $tablePos[2] = mb_strlen($m[1]);
            }

            if (preg_match("/^(.+ ){$this->opt($this->t('Aircraft'))}[ ]*[:]+[^:\n]*$/imu", $segment, $m)
                && (empty($tablePos[2]) || $tablePos[2] > mb_strlen($m[1]))
            ) {
                $tablePos[2] = mb_strlen($m[1]);
            }
            $table = $this->splitCols($segment, $tablePos);

            if (count($table) !== 3) {
                $this->logger->debug('Table in segment not found!');

                continue;
            }

            // Friday December 28,2018
            $patterns['date'] = '(?<date>(?<wday>[-[:alpha:]]+)[ ]*[A-Z][[:alpha:]]{3,}[ ]+\d{1,2}[ ]*,\s*\d{2,4})';

            $dDate = $dWday = null;

            if (preg_match("/{$patterns['date']}/u", $table[0], $m)) {
                $dWday = $m['wday'];
                $dDate = $this->normalizeDate($m['date']);
            }

            $aDate = $aWday = null;

            if (preg_match("/{$patterns['date']}/u", $table[1], $m)) {
                $aWday = $m['wday'];
                $aDate = $this->normalizeDate($m['date']);
            } elseif (!empty($dWday) && preg_match("/\n[ ]*{$dWday}/", $table[1])) {
                $aDate = $dDate;
            }

            if (empty($dDate) && !empty($aWday) && preg_match("/\n[ ]*{$aWday}/", $table[0])) {
                $dDate = $aDate;
            }

            $dTime = $this->normalizeTime($this->re("/({$patterns['time']})/", $table[0]));
            $aTime = $this->normalizeTime($this->re("/({$patterns['time']})/", $table[1]));

            if ($dDate && $dTime) {
                $s->departure()->date(strtotime($dTime, $dDate));
            } elseif (!$dDate && !preg_match("/{$patterns['date']}/u", $table[0])) {
                $s->departure()->noDate();
            }

            if ($aDate && $aTime) {
                $s->arrival()->date(strtotime($aTime, $aDate));
            } elseif (!$aDate && !preg_match("/{$patterns['date']}/u", $table[1])) {
                $s->arrival()->noDate();
            }

            if ($dur = $this->re("/{$this->t('Flight Duration')}[ ]*[:]+[ (]*(\d[\d hm]+?)[ )]*$/im", $table[2])) {
                $s->extra()
                    ->duration($dur);
            }

            if ($aircraft = $this->re("/{$this->t('Aircraft')}[ ]?\:[ ]?([\d\-]{1,7})/i", $table[2])) {
                $s->extra()
                    ->aircraft($aircraft);
            }
        }
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

    private function normalizeDate(?string $str)
    {
        $str = preg_replace("/([a-z])([A-Z])/", "$1 $2", $str);
        // $this->logger->debug($str);
        $in = [
            //Julio 17, 202
            '/^(\w+)\s+(\w+)\s*(\d+)\,\s*(\d{3})$/',
            // Miércoles Diciembre 19,2018    |    Diciembre 19, 2018
            '/^(?:[-[:alpha:]]+[, ]+)?([[:alpha:]]{3,})[ ]+(\d{1,2})[,\s]+(\d{2,4})$/u',
        ];
        $out = [
            "$1, $3 $2 {$this->year}",
            '$2 $1 $3',
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug($str);

        if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[2], $this->lang)) {
                $str = $m[1] . $en . $m[3];
            } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
                $str = $m[1] . $en . $m[3];
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $str)) {
            $str = strtotime($str);
        } else {
            $str = null;
        }

        return $str;
    }

    private function normalizeTime(?string $s): string
    {
        $s = preg_replace('/([AaPp])\.[ ]*([Mm])\.?/', '$1$2', $s); // 2:04 p. m.    ->    2:04 pm

        return $s;
    }

    private function cutText(string $start, $end, string $text)
    {
        if (empty($start) || empty($end) || empty($text)) {
            return false;
        }

        if (is_array($end)) {
            $begin = stristr($text, $start);

            foreach ($end as $e) {
                if (stristr($begin, $e, true) !== false) {
                    return stristr($begin, $e, true);
                }
            }
        }

        return stristr(stristr($text, $start), $end, true);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
