<?php

namespace AwardWallet\Engine\airtransat\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parsers with similar PDF-formats: asiana/BoardingPassPdf, aviancataca/BoardingPass, aviancataca/TicketDetails, czech/BoardingPass, lotpair/BoardingPass, sata/BoardingPass, tamair/BoardingPassPDF(object), tapportugal/AirTicket, luxair/YourBoardingPassNonPdf, saudisrabianairlin/BoardingPass

class BoardingPass extends \TAccountChecker
{
    public $mailFiles = "airtransat/it-570463240.eml, airtransat/it-570558810.eml, airtransat/it-6601883.eml, airtransat/it-7017697.eml, airtransat/it-7056213.eml, airtransat/it-7201328.eml, airtransat/it-7252162.eml, airtransat/it-7254770.eml, airtransat/it-7254773.eml"; // bcdtravel
    public $pdf;
    public $pdfNamePattern = ".*\.pdf";
    public $seats;
    public $attach;

    private static $detectLang = [
        'pt' => [
            'Confirmamos que o seu check-in foi efetuado com sucesso',
            'PASSAGEIRO FREQUENTE',
            'Leve a sua bagagem ao balcão de entrega de bagagem até às',
        ],
        'en' => [
            'Please find attached your Boarding Pass',
            'actionprintBoardingPass=Print',
            'Confirmation / This is not a boarding pass',
            'Boarding pass information',
        ],
        'fr' => [
            'Ce document n’est pas une carte d’embarquement',
            'Veuillez trouver en pièce jointe votre carte d\'embarquement',
            'Veuillez trouver en pièce jointe votre carte ',
        ],
    ];

    private $lang = '';

    private $dict = [
        'en' => [
            //			'Booking Reference' => '',
            //			'Passenger:' => '',
            //			'Flight:' => '',
            //			'From:' => '',
            //			'To:' => '',
            //          'Click' => '',

            //PDF

            //'BOOKING REFERENCE' => '',
            //'CLASS OF TRAVEL' => '',
            //'FREQUENT FLYER' => '',
            //'ETKT' => '',
        ],
        'fr' => [
            'Booking Reference'  => 'Numéro de réservation',
            'Passenger:'         => 'Passager:',
            'Flight:'            => 'Vol:',
            'From:'              => 'De:',
            'To:'                => 'À:',
            //'Click' => ''
        ],
        'pt' => [
            'Booking Reference'  => 'Código de Reserva:',
            'Passenger:'         => 'Passageiro:',
            'Flight:'            => 'Voo:',
            'From:'              => 'De:',
            'To:'                => 'Para:',
            'Click'              => 'Clique',

            //PDF

            'BOOKING REFERENCE' => 'CÓDIGO DE RESERVA',
            'CLASS OF TRAVEL'   => 'CLASSE',
            'FREQUENT FLYER'    => 'PASSAGEIRO FREQUENTE',
        ],
    ];
    private $text;
    private $reservations = [];

    private $code;
    private static $providers = [
        'airtransat' => [
            'from' => ['@airtransat.com'],
            'subj' => [
                'Your Boarding Pass',
                'Boarding Cards for Air Transat',
                'Carte d’embarquement',
                'Document de confirmation',
                'Conferma della prenotazione',
            ],
            'body' => [
                'www.airtransat.com',
            ],
            'keyword' => 'airtransat',
        ],
        'bulgariaair' => [
            'from' => ['@amadeus.net', 'air.bg'], //?? it's so. bulgariaair from amadeus
            'subj' => [
                'Your Email Confirmation',
            ],
            'body' => [
                'Bulgaria Air',
            ],
            'keyword' => 'Bulgaria Air',
        ],
        'sata' => [
            'from' => ['@sata.pt'],
            'subj' => [
                'Boarding Pass Confirmation',
                'O seu cartão de embarque',
            ],
            'body' => [
                'Grupo SATA',
            ],
            'keyword' => 'www.azoresairlines.pt',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $code => $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $it = [];
        $type = '';
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                $fileName = $this->getAttachmentName($parser, $pdf);

                if (($text = text(\PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE))) !== null) {
                    $this->text = \PDF::convertToText($parser->getAttachmentBody($pdf));
                    //at first by body. if no, then by attach (for record locator)
                    if (!$this->assignLang($parser->getHTMLBody())) {
                        $this->assignLang($text);
                    }
                    $code = $this->getProvider($parser, $text);
                    $type = 'Pdf';

                    if (!empty($code)) {
                        if (preg_match("/SEAT\s*\/\s*\w+\s*(?:GROUP\s*\/\s*\w+|BOARDING TIME)/u", $this->text)) {
                            $this->parseEmailPDF($text, $email, $fileName);
                        } elseif (preg_match("/CLASS OF TRAVEL\s*BOOKING REFERENCE/u", $this->text)) {
                            $this->parseEmailPDF2($text, $email, $fileName);
                        }
                    }
                } else {
                    return null;
                }
            }
        } else {
            $code = $this->getProvider($parser);
            $this->assignLang($parser->getHTMLBody());
            $type = 'Html';
            $this->parseEmailHtml($email);
        }

        if (isset($code)) {
            $email->setProviderCode($code);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $this->pdf = clone $this->http;

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    if ($this->assignLang($text)) {
                        return true;
                    }
                }
            }
        }

        return $this->assignLang($parser->getHTMLBody());
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$detectLang);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$detectLang);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            } elseif ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, 'fr')) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function splitText($pattern, $text)
    {
        if (empty($text)) {
            return $text;
        }

        $r = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    protected function match($pattern, $text, $allMatches = false)
    {
        if (preg_match($pattern, $text, $matches)) {
            if ($allMatches) {
                array_shift($matches);

                return array_map([$this, 'normalizeText'], $matches);
            } else {
                return $this->normalizeText(count($matches) > 1 ? $matches[1] : $matches[0]);
            }
        }

        return false;
    }

    protected function normalizeText($string)
    {
        return trim(preg_replace('/\s{2,}/', ' ', str_replace("\n", " ", $string)));
    }

    private function getProvider(PlancakeEmailParser $parser, $text = null)
    {
        if (!$text) {
            $text = $this->http->Response['body'];
        }
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($text, $search) !== false)) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmailHtml(Email $email)
    {
        $this->logger->debug(__METHOD__);

        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("(//span[{$this->contains($this->t('Booking Reference'))}]/following-sibling::span[1])[1]", null, false, '/[A-Z\d]{5,6}$/');

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        }

        $passengers = array_unique($this->http->FindNodes("//span[{$this->contains($this->t('Passenger:'))}]/following-sibling::span[1]"));

        $f->general()
            ->travellers(preg_replace("/(?:\s+Mrs|\s+Mr|\s+Ms)$/", "", $passengers));

        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger:'))}]");

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode("./following::span[{$this->contains($this->t('Flight:'))}][1]/following-sibling::span[1]", $root);

            if (preg_match('#([\dA-Z]{2})(\d{2,5})#', $flight, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $t = $this->http->FindSingleNode("./following::span[{$this->contains($this->t('Flight:'))}][1]/following-sibling::span[2]", $root);
                $class = $this->http->FindSingleNode("./following::span[{$this->contains($this->t('Flight:'))}][1]/following-sibling::span[3]", $root);

                if (preg_match('#\s*\-\s*#', $t) && preg_match('#\w+#', $class)) {
                    $s->extra()
                        ->cabin($class);
                }
            }

            $departAr = $this->http->FindNodes("./following::span[{$this->eq($this->t('From:'))}][1]/ancestor::td[1]//span", $root);
            $depart = implode("\n", $departAr);

            if (preg_match("#:\n([\w., ]+)\n({$this->opt($this->t('Terminal'))}\s*([\w]*)\n)?(\d{2}\s[a-z]{3}\s\d{4}\s*-\s*\d{2}:\d{2})#i", $depart, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($m[4]));

                if (!empty($m[3])) {
                    $s->departure()
                        ->terminal($m[3]);
                }
            }

            $arriveAr = $this->http->FindNodes("./following::span[{$this->eq($this->t('To:'))}][1]/ancestor::td[1]//span", $root);
            $arrive = implode("\n", $arriveAr);

            if (preg_match("#:\n([\w., ]+)\n({$this->opt($this->t('Terminal'))}\s*([\w]*)\n)?(\d{2}\s[a-z]{3}\s\d{4}\s*-\s*\d{2}:\d{2})#i", $arrive, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($m[4]));

                if (!empty($m[3])) {
                    $s->arrival()
                        ->terminal($m[3]);
                }
            }

            foreach ($passengers as $passenger) {
                $b = $email->add()->bpass();
                $b->setRecordLocator($conf);
                $b->setTraveller($passenger);
                $b->setFlightNumber($s->getAirlineName() . $s->getFlightNumber());
                $b->setDepDate($s->getDepDate());
                $b->setUrl($this->http->FindSingleNode("//a[{$this->starts($this->t('Clique'))}]/@href"));
            }
        }
    }

    private function parseEmailPDF($text, Email $email, string $fileName)
    {
        $this->logger->debug(__METHOD__);

        $f = $email->add()->flight();

        $conf = '';
        $travellers = [];

        if (preg_match("#BOOKING REFERENCE\s+(.*\n){0,5}?\s*([\dA-Z]{5,6})\s#", $text, $m) && !preg_match("/TICKET/i", $m[2])) {
            $conf = $m[2];
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode('(//span[contains(., "' . $this->t('Booking Reference') . '")]/following-sibling::span[1])[1]', null, false, '/[A-Z\d]{5,6}$/');
        }

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        } else {
            $f->general()
                ->noConfirmation();
        }

        if (preg_match_all("#(?:Boarding Pass\n?.*Carte d embarquement|Confirmation / This is not a boarding pass|Boarding Pass)\s+(.*)\s+(?:DEPARTURE|FLIGHT)#i", $text, $m)) {
            foreach ($m[1] as $key => $value) {
                $travellers[] = trim(str_replace('/ ', '', $m[1][$key]));
            }
        }

        if (count($travellers) > 0) {
            $travellers = array_unique($travellers);
        }

        $f->general()
            ->travellers(preg_replace("#(?:\s+Mrs|\s+Mr|\s+Ms)\s*$#u", "", $travellers));

        $tickets = [];

        if (preg_match_all("#(TICKET|Billet)\s+(.*\n){0,5}\s*(\d{8,25})#", $text, $m)) {
            foreach ($m[3] as $key => $value) {
                $tickets[] = $m[3][$key];
            }
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }

        $this->reservations = $this->splitText("#\n([^\n]*\bFROM\b[^\n]+\bTO\b[^\n]*)#", $this->text); //it's for detecting terminals

        $segTexts = $this->splitText("#(Boarding Pass\n)#", $text);

        foreach ($segTexts as $i=> $stext) {
            $this->parseEmailSegment($stext, $i, $f);
        }
    }

    private function parseEmailPDF2($text, Email $email, string $fileName)
    {
        $this->logger->debug(__METHOD__);

        $f = $email->add()->flight();

        $tickets = [];
        $travellers = [];
        $accounts = [];
        $confs = [];
        $textArray = array_filter(preg_split("/{$this->opt($this->t('Security nb:'))}/", $this->text));

        if (preg_match_all("/\s*Boarding Pass.+\n\s*(.+)\n*\s*FROM/", $this->text, $m)) {
            $f->general()
                ->travellers(preg_replace("#(?:\s+Mrs|\s+Mr|\s+Ms)\s*$#u", "", $travellers = array_unique($m[1])));
        }

        foreach ($textArray as $segment) {
            $s = $f->addSegment();

            $tickets[] = $this->re("/{$this->opt($this->t('Ticket:'))}\s*(\d+)/", $segment);

            if (preg_match("/Flight\/.+(?:.+\n*){1,2}(?<aName>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))(?<fNumber>\d{1,4})\s+(?:(?<seat>\d+[A-Z]))?\s*/", $segment, $m)) {
                $s->airline()
                    ->name($m['aName'])
                    ->number($m['fNumber']);

                if (!empty($m['seat'])) {
                    $s->addSeat($m['seat']);
                }
            }

            $table = $this->splitCols($this->re("/\n(FREQUENT FLYER.+TICKET\n(?:.+\n*){2,5})TRAVEL INFORMATION/u", $this->text));

            if (preg_match("/{$this->opt($this->t('FREQUENT FLYER'))}\s*(?<account>[\dA-Z]{9,})\s*/su", $table[0], $m)) {
                $accounts[] = $m['account'];
            }

            if (preg_match("/{$this->opt($this->t('CLASS OF TRAVEL'))}\s*\n(?<cabin>\D+)\b/u", $table[1], $m)) {
                $s->extra()
                    ->cabin($m['cabin']);
            }

            if (preg_match("/{$this->opt($this->t('BOOKING REFERENCE'))}\s*(?<conf>[\dA-Z]{6})\s*$/su", $table[2], $m)) {
                $confs[] = $m['conf'];
            }

            $this->logger->debug($segment);
            $flightInfo = $this->re("/TAKE\-OFF.+\n+((?:.+\n*){1,5})Flight/", $segment);
            $flightTable = $this->splitCols($flightInfo, [0, 30, 60, 95]);

            $s->departure()
                ->date(strtotime($this->re("/^([\d\:]+\s*.+\d{4})/", $flightTable[0])))
                ->code($this->re("/([A-Z]{3})/", $flightTable[1]));

            $depTerminal = $this->re("/Terminal\s*(\S*)\n/u", $flightTable[1]);

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $arrTerminal = $this->re("/Terminal\s*(\S*)\n/u", $flightTable[2]);

            if (!empty($arrTerminal)) {
                $s->arrival()
                    ->terminal($arrTerminal);
            }

            $s->arrival()
                ->date(strtotime($this->re("/^\s*([\d\:]+\s*.+\d{4})/s", $flightTable[3])))
                ->code($this->re("/([A-Z]{3})/", $flightTable[2]));

            foreach ($travellers as $key => $traveller) {
                $b = $email->add()->bpass();
                $b->setTraveller($traveller);
                $b->setDepDate($s->getDepDate());
                $b->setFlightNumber($s->getAirlineName() . $s->getFlightNumber());
                $b->setRecordLocator($confs[$key]);
                $b->setAttachmentName($fileName);
            }
        }

        $confs = array_unique($confs);

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf);
        }

        $f->setTicketNumbers(array_unique($tickets), false);

        $f->setAccountNumbers(array_unique($accounts), false);
    }

    private function parseEmailSegment($text, $i, Flight $f)
    {
        $s = $f->addSegment();

        if (preg_match("#(\d{2}:\d{2})\s+(\d{2}:\d{2})\s+(\d+\s+\w{3}\s+\d{4})\s+([A-Z]{3})\s+([A-Z]{3})\s+(\d+\s+\w{3}\s+\d{4})(\s+Terminal *(.*))?(\s+Terminal\s*(.*))?\s*(.+)\s+(.+)#u", $text, $m)) {
            $depDate = strtotime($m[1] . $m[3]);

            if (empty($depDate)) {
                $depDate = strtotime($this->dateStringToEnglish($m[1] . $m[3]));
            }

            $s->departure()
                //->name($m[11])
                ->date($depDate)
                ->code($m[4]);

            $arrDate = strtotime($m[2] . $m[6]);

            if (empty($arrDate)) {
                $arrDate = strtotime($this->dateStringToEnglish($m[2] . $m[6]));
            }

            $s->arrival()
                //->name($m[12])
                ->date($arrDate)
                ->code($m[5]);

            if (!empty($m[8]) && !empty($m[10])) {
                $s->departure()
                    ->terminal($m[8]);

                $s->arrival()
                    ->terminal($m[10]);
            } else {
                if (isset($this->reservations[$i]) && preg_match("#{$m[4]}[ ]+{$m[5]}#", $this->reservations[$i])) {
                    $node = strstr($this->reservations[$i], 'TRAVEL INFORMATION', true);
                } else {
                    foreach ($this->reservations as $res) {
                        if (preg_match("#{$m[4]}[ ]+{$m[5]}#", $res)) {
                            $node = strstr($res, 'TRAVEL INFORMATION', true);

                            break;
                        }
                    }
                }

                if (isset($node) && preg_match("#(([^\n]*\bFROM\b[^\n]+)\bTO\b[^\n]*.+)#s", $node, $mm)) {
                    $table = $this->splitCols($mm[1], [0, mb_strlen($mm[2]) - 3]);

                    if (preg_match("#\s+Terminal[ ]+(.+)#", $table[0], $mm)) {
                        $s->departure()
                            ->terminal($mm[1]);
                    }

                    if (preg_match("#\s+Terminal[ ]+(.+)#", $table[1], $mm)) {
                        $s->arrival()
                            ->terminal($mm[1]);
                    }
                }
            }
        }

        if (preg_match("#FLIGHT\s+.*\s+SEAT(\s|\n)(.*\n){0,5}\s*([\dA-Z]{2})(\d{1,5})\s*(\d{1,3}[A-Z])#", $text, $m)) {
            $s->airline()
                ->name($m[3])
                ->number($m[4]);

            $s->extra()
                ->seat($m[5]);
        }

        if ((empty($segment['DepartureTerminal']) || empty($segment['ArrivalTerminal'])) && isset($segment['AirlineName'], $segment['FlightNumber'])) {
            $term = $this->http->FindSingleNode("//tr[contains(.,'" . $segment['AirlineName'] . $segment['FlightNumber'] . "')][1]/following-sibling::tr[" . $this->containsAllLang("From:") . "][1]", null, true, "#Terminal\s*(.*)\s*\d{2}\s*\w{3}#ui");

            if (!empty($term)) {
                $s->departure()
                    ->terminal($term);
            }
            $term = $this->http->FindSingleNode("//tr[contains(.,'" . $segment['AirlineName'] . $segment['FlightNumber'] . "')][1]//following-sibling::tr[" . $this->containsAllLang("To:") . "][1]", null, true, "#Terminal\s*(.*)\s*\d{2}\s*\w{3}#ui");

            if (!empty($term)) {
                $s->arrival()
                    ->terminal($term);
            }
        }

        if (preg_match('#(CLASS OF TRAVEL|Classe)\n(.*[a-z].*\n){0,5}\s*([A-Z ]+)\s+#', $text, $m)) {
            $s->extra()
                ->cabin($m[3]);
        }
    }

    //========================================
    // Auxiliary methods
    //========================================

    private function assignLang($body)
    {
        foreach (self::$detectLang as $lang => $detect) {
            if (is_string($detect) && stripos($body, $detect) !== false) {
                $this->lang = $lang;

                return true;
            } elseif (is_array($detect)) {
                foreach ($detect as $item) {
                    if (stripos($body, $item) !== false) {
                        $this->lang = $lang;

                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            //19 JUN 2017 - 19:25
            '#^^\s*(\d{2})\s+(\w+)\s+(\d{4})\s*-\s*(\d{2}:\d{2})$#u',
        ];
        $out = [
            '$1 $2 $3 $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (empty($this->dict[$this->lang]) || empty($this->dict[$this->lang][$s])) {
            return $s;
        }

        return $this->dict[$this->lang][$s];
    }

    private function containsAllLang($field)
    {
        $fields = [$field];

        foreach ($this->dict as $key => $value) {
            if (isset($value[$field])) {
                $fields[] = $value[$field];
            }
        }

        if (count($fields) == 0) {
            return '';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $fields));
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function eq($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getAttachmentName(PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $m)) {
            return $m[1];
        }

        return null;
    }
}
