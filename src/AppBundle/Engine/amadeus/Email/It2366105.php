<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parse html in amadeus/It1640513

class It2366105 extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-16949360.eml, amadeus/it-2264971.eml, amadeus/it-2264974.eml, amadeus/it-2366105.eml, amadeus/it-40243111.eml, amadeus/it-4586195.eml, amadeus/it-4586195.eml, amadeus/it-4614272.eml, amadeus/it-4614272.eml, amadeus/it-5092033.eml, amadeus/it-5092033.eml, amadeus/it-5316432.eml, amadeus/it-6882950.eml, amadeus/it-6891417.eml, amadeus/it-6900049.eml, amadeus/it-6978351.eml, amadeus/it-6992187.eml";

    private static $providerDetects = [
        'azul' => [
            'AZUL LINHAS AEREAS BRASILEIRAS',
        ],
        'montenegro' => [
            'MONTENEGRO AIRLINES',
        ],
        'aviancataca' => [
            'AVIANCA BRASIL',
            'AVIANCA BRAZIL',
        ],
        'sata' => [
            'GRUPO SATA',
            'SATA AIR ACORES',
            'WWW.AZORESAIRLINES.',
        ],
        'airmaroc' => [
            'ROYAL AIR MAROC',
        ],
        'klm' => [
            'KLM ',
        ],
        'airfrance' => [
            'Corporate Air France',
        ],
        'bcd' => [
            'BCD TRAVEL',
        ],
        'china' => [
            'www.china-airlines.com',
        ],
        'asia' => [
            'CATHAY PACIFIC AIRWAYS LTD',
            'CATHAY PACIFIC CITY',
            'www.cathaypacific.com/',
        ],
        'finnair' => [
            'FINNAIR',
        ],
        // last
        'amadeus' => [
            'amadeus',
        ],
        'checkmytrip' => [
            'Check My Trip',
            'Check trip itinerary',
        ],
        'airgreenland' => [
            'AIR GREENLAND',
        ],
    ];

    private $langDetects = [
        'en' => ['Electronic Ticket Receipt'],
        'fr' => [
            'Mémo Voyage Billet Electronique',
        ],
        'pt' => [
            'Recibo do Etkt', 'Recibo de bilhete eletrônico',
        ],
        'es' => [
            'Comprobante de Billete',
        ],
        'zh' => [
            '電子機票收據',
        ],
    ];

    private static $dictionary = [
        'en' => [
            // Header
            'Booking Reference' => ['Booking Reference', 'Booking ref'],
            // type 1: with blue stripe (checkMyTrip)
            //            'Issue date' => '',
            //            'Airline booking ref' => '',
            //            'Ticket:' => '',
            //            'Traveler' => 'Passageiro',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            //            'Passenger' => '',
            //            'Ticket number' => '', // + in receipt

            // Itinerary
            //            'Itinerary' => '',
            //            'Terminal' => '',
            //            'Operated by' => '',
            //            'Arrival Day' => '',
            //            'Marketed by' => '',
            //            'Frequent flyer number' => '',
            //            'Equipment' => '',
            //            'Duration' => '',

            // Receipt
            //            'Receipt' => '',
            //            'Name' => '',
            //            'Fare' => '',
            'Fare Equivalent' => ['Fare Equivalent', 'Equiv Fare Paid'],
            'Tax'             => ['Tax', 'Taxes', 'Taxes and Airline Imposed Fees'],
            //            'Total Amount' => '',
            //            'Issuing Airline and date' => '',
        ],
        'pt' => [
            // Header
            'Booking Reference' => 'Código de reserva',
            // type 1: with blue stripe (check my trip)
            'Issue date'          => 'Data de emissão',
            'Airline booking ref' => 'Referencia de reserva',
            'Ticket:'             => 'Bilhete:',
            'Traveler'            => 'Passageiro',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            'Passenger'     => 'Passageiro',
            'Ticket number' => ['Número do Bilhete', 'Número do bilhete'], // + in receipt

            // Itinerary
            'Itinerary' => 'Itinerário',
            //            'Terminal' => '',
            'Operated by' => 'Operado por',
            'Arrival Day' => 'Dia da chegada',
            'Marketed by' => 'Comercializado por',
            //            'Frequent flyer number' => '',
            //            'Equipment' => '',
            //            'Duration' => '',

            // Receipt
            'Receipt'                  => 'Recibo',
            'Name'                     => 'Nome',
            'Fare'                     => ['Tarifa Aérea', 'Tarifa'],
            'Fare Equivalent'          => 'Tarifa Equiv Paga',
            'Tax'                      => ['Taxa', 'Sobretaxas Cobradas Pela Companhia Aérea', 'Taxas'],
            'Total Amount'             => 'Valor Total',
            'Issuing Airline and date' => ['Empresa Emissora e data', 'Cia Aérea Emissora e data'],
        ],
        'zh' => [
            // Header
            'Booking Reference' => ['訂位代號'],
            // type 1: with blue stripe (checkMyTrip)
            //            'Issue date' => '',
            //            'Airline booking ref' => '',
            //            'Ticket:' => '',
            //            'Traveler' => 'Passageiro',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            'Passenger'     => '旅客',
            'Ticket number' => '機票號碼', // + in receipt

            // Itinerary
            'Itinerary'             => '行程',
            'Terminal'              => ['航站'],
            'Operated by'           => '營運由',
            'Arrival Day'           => '抵達日',
            'Marketed by'           => '銷售由',
            'Frequent flyer number' => '會員編號',
            //            'Equipment' => '',
            //            'Duration' => '',

            // Receipt
            'Receipt' => '收據',
            //            'Name' => '',
            'Fare' => '票面價',
            //            'Fare Equivalent' => '',
            'Tax'                      => '稅額',
            'Total Amount'             => '總額',
            'Issuing Airline and date' => '開票航空公司 與 日期',
        ],
        'fr' => [
            //            'Booking Reference',
            //            'Itinerary',
            //            'Passenger',
            //            'Receipt',
            //            'Operated by',
            'Marketed by'              => 'Commercialisé par',
            'Issuing Airline and date' => 'Compagnie Emettrice et date',

            // Header
            'Booking Reference' => ['Numéro de réservation'],
            // type 1: with blue stripe (checkMyTrip)
            //            'Issue date' => '',
            //            'Airline booking ref' => '',
            //            'Ticket:' => '',
            //            'Traveler' => 'Passageiro',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            'Passenger'     => 'Passager',
            'Ticket number' => 'Numéro de billet', // + in receipt

            // Itinerary
            'Itinerary' => 'Itinéraire',
            //            'Terminal' => '',
            'Operated by' => 'Opéré par',
            'Arrival Day' => 'Arrivée Jour',
            'Marketed by' => 'Commercialisé par',
            //            'Frequent flyer number' => '',
            //            'Equipment' => '',
            //            'Duration' => '',

            // Receipt
            'Receipt' => 'Reçu de paiement',
            'Name'    => 'Nom',
            'Fare'    => 'Tarif',
            //            'Fare Equivalent' => [],
            'Tax'                      => ['Taxes et Frais de la Compagnie', 'Taxes'],
            'Total Amount'             => 'Montant total',
            'Issuing Airline and date' => 'Compagnie Emettrice et date',
        ],
        'es' => [
            //            'Booking Reference',
            //            'Itinerary',
            //            'Passenger',
            //            'Receipt',
            //            'Operated by',
            //            'Marketed by'              => 'Commercialisé par',
            //            'Issuing Airline and date' => 'Compagnie Emettrice et date',

            // Header
            'Booking Reference' => ['Loc. Reserva'],
            // type 1: with blue stripe (checkMyTrip)
            'Issue date'          => 'Fecha de Emisión',
            'Airline booking ref' => 'Codigo de Reserva',
            'Ticket:'             => 'Billete Electrónico:',
            'Traveler'            => 'Viajero',
            // type 2:
            //    Passenger                   Ticket number
            //    Yang Benchung Mr            297 2403544740
            //            'Passenger' => 'Passager',
            'Ticket number' => 'Numero de Billete', // + in receipt

            // Itinerary
            'Itinerary'   => 'Itinerario',
            'Terminal'    => 'Terminal',
            'Operated by' => 'Operado por',
            //            'Arrival Day' => '',
            'Marketed by' => 'Comercializado por',
            //            'Frequent flyer number' => '',
            'Equipment' => 'Equipo',
            'Duration'  => 'Duración',

            // Receipt
            'Receipt'                  => 'Comprobante',
            'Name'                     => 'Nombre',
            'Fare'                     => 'Tarifa aérea',
            'Fare Equivalent'          => 'Tarifa Equiv Pagada',
            'Tax'                      => ['Tasa', 'Recargo De Aerolinea'],
            'Total Amount'             => 'Importe Total',
            'Issuing Airline and date' => 'Compania Emisora y fecha',
        ],
    ];

    private $lang;
    private $secondLang;

    /** @var \HttpBrowser */
    private $pdf;

    private $emailDate;
    private $relativeDate;
    private $providerCode;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $pdfTexts = $this->convertToText($parser);

        if (empty($pdfTexts)) {
            $this->logger->info('Pdf attachment not found!');

            return null;
        }
        $this->emailDate = strtotime($parser->getDate());

        foreach ($pdfTexts as $pdfText) {
            $this->detectLang($pdfText);
            $this->logger->debug('$this->lang = ' . print_r($this->lang, true));
            $this->logger->debug('$this->secondLang = ' . print_r($this->secondLang, true));

            $this->parseEmail($email, $parser, $pdfText);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.amadeus.') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $body = \PDF::convertToText($parser->getAttachmentBody($pdfs[0]));

        if ($this->detectPdf($body) === true) {
            return true;
        }

        return false;
    }

    public function detectPdf($body)
    {
        foreach (self::$providerDetects as $providerDetects) {
            if ($this->containsText($body, $providerDetects) !== false) {
                foreach ($this->langDetects as $detects) {
                    if ($this->containsText($body, $detects) !== false) {
                        return true;
                    }
                }
            }
        }
        $detects = [
            // en
            'This document establishes the creation of your electronic ticket(s) in our computer systems.',
            // es
            'Este documento implica la creación de su billete(s) electrónico(s) en nuestros sistemas informáticos.',
            // pt
            'Este documento estabelece a criação do(s) seu(s) bilhete(s) eletrônico(s) em nossos sistemas.',
        ];

        if ($this->containsText($body, $detects) !== false) {
            foreach ($this->langDetects as $detects) {
                if ($this->containsText($body, $detects) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$providerDetects);
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail(Email $email, PlancakeEmailParser $parser, string $text)
    {
//        $this->logger->debug('$text = '.print_r( $text,true));
        $f = $email->add()->flight();

        $header = $this->cutText(0, $this->t('Itinerary'), $text);
//        $this->logger->debug('$header = ' . print_r($header, true));

        foreach (self::$providerDetects as $provider => $providerDetect) {
            if ($this->containsText($header, $providerDetect) !== false) {
                $this->providerCode = $provider;

                break;
            }
        }

        if (empty($this->providerCode)) {
            $afterReceipt = $this->cutText($this->t('Receipt'), 0, $text);

            foreach (self::$providerDetects as $provider => $providerDetect) {
                if ($this->containsText($afterReceipt, $providerDetect) !== false) {
                    $this->providerCode = $provider;

                    break;
                }
            }
        }

        if (
            preg_match('/' . $this->opt($this->t('Booking Reference')) . '(?: *\/[[:alpha:] ]*)?: *([A-Z\d]{5,7})\b/u', $header, $m)
            || preg_match('/\/ *' . $this->opt($this->t('Booking Reference', $this->secondLang)) . ': *([A-Z\d]{5,7})\b/u', $header, $m)
        ) {
            $f->general()
                ->confirmation($m[1]);
        }

        if (
            preg_match('/' . $this->opt($this->t('Airline booking ref')) . ' *: *([A-Z\d\/\s,]+)\n/u', $header, $m)
            && preg_match_all('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\/([A-Z\d]{5,7})\b/u', $m[1], $mat)
        ) {
            foreach ($mat[1] as $i => $value) {
                $airlineConfirmation[$value] = $mat[2][$i];
            }
        }

        // type 1: with blue stripe (checkMyTrip)
        if (preg_match('/\n( *' . $this->opt($this->t('Traveler')) . ' *\S.+?(?: {2,}.*)?\n(.*\n+){0,10}?)(\n| {40,}.*\n){2,}/ui', $header, $m)) {
            $table = $this->SplitCols($m[1]);

            if (preg_match("/^\s*" . $this->opt($this->t('Traveler')) . "\s*$/", $table[0] ?? '')
                && !preg_match("/(\S {2,}\S)/", $table[1] ?? '')
                && preg_match_all("/(?:^|\n) *([[:alpha:]\-\s]+?) *\([A-Z]{3,7}\)/u", $table[1] ?? '', $mat)
            ) {
                $mat[1] = preg_replace('/^\s*(mrs|mr|ms|miss|mstr)\s+/i', '', $mat[1]);
                $mat[1] = preg_replace('/\s+(mrs|mr|ms|miss|mstr)\s*$/i', '', $mat[1]);
                $mat[1] = preg_replace('/\s*\n\s*/i', ' ', $mat[1]);
                $f->general()
                    ->travellers($mat[1]);
            }
        }

        if (preg_match('/\s{2,}' . $this->opt($this->t('Ticket:')) . ' *(\d{3}[\d \-]{7,})\s*\n/u', $header, $m)) {
            $f->issued()
                ->ticket(trim($m[1]), false);
        }

        // type 2:
        //    Passenger                   Ticket number
        //    Yang Benchung Mr            297 2403544740
        $nameTicketRe = ' *([[:alpha:]][[:alpha:] ]+)(?: *\([A-Z]{1,6}\))? {2,}(\d{3}[\d \-]{7,})\s+';

        if (preg_match('/\n *' . $this->opt($this->t('Passenger')) . ' *(?:\/[[:alpha:] ]*)? {2,}' . $this->opt($this->t('Ticket number')) . '\s+.*\n'
                . '(?:.*\n+){0,3}' . $nameTicketRe . '/ui', $header, $m)
            || preg_match('/\n *[[:alpha:] ]+\/ *' . $this->opt(preg_replace("/^(.{5}\S*)(?: .*|$)/", '$1', $this->t('Passenger', $this->secondLang))) . '[[:alpha:] ]* {2,}[[:alpha:] ]+\/ *' . $this->opt(preg_replace("/^(.{5}\S*)(?: .*|$)/", '$1', $this->t('Ticket number', $this->secondLang))) . '\b.*\n'
                . '(?:.*\n+){0,3}' . $nameTicketRe . '/ui', $header, $m)
        ) {
            $m[1] = preg_replace('/^\s*(mrs|mr|ms|miss|mstr)\s+/i', '', $m[1]);
            $m[1] = preg_replace('/\s+(mrs|mr|ms|miss|mstr)\s*$/i', '', $m[1]);
            $f->general()
                ->traveller($m[1]);
            $f->issued()
                ->ticket(trim($m[2]), false);
        }

        // Price
        $totalSum = 0;

        if (preg_match("#(?:\n[ ]+|/[ ]*)" . $this->opt($this->t("Fare Equivalent")) . "(?:\s*/[[:alpha:] ]+?)?[ ]*:[ ]*([A-Z]{3})[ ]*(\d[\d., ]+)\s*\n#u", $text, $m)) {
            $cost = PriceHelper::parse($m[2], $m[1]);
            $currency = $m[1];
            $totalSum += $cost;

            if ($cost == 0.0 && preg_match("/{$this->opt($this->t("Total Amount"))}(.*\n){1,7}.+\/ *Asia Miles\W[^\/\n]* Apply/i", $text)) {
            } else {
                $f->price()
                    ->cost($cost)
                    ->currency($currency);
            }
        } elseif (preg_match("#(?:\n[ ]+|/[ ]*)" . $this->opt($this->t("Fare")) . "(?:\s*/.+?)?[ ]*:[ ]*([A-Z]{3})[ ]*(\d[\d., ]+)\s*\n#", $text, $m)) {
            $cost = PriceHelper::parse($m[2], $m[1]);
            $currency = $m[1];
            $totalSum += $cost;

            if ($cost == 0.0 && preg_match("/{$this->opt($this->t("Total Amount"))}(.*\n){1,7}.+\/Asia Miles\W.* Apply/i", $text)) {
            } else {
                $f->price()
                    ->cost($cost)
                    ->currency($currency);
            }
        }

        if (!empty($currency) && preg_match("#\n *" . $this->opt($this->t("Fare")) . "\s+(?:.*\n)+?\s*" . $this->opt($this->t("Tax")) . "[^:]+[ ]+:([\s\S]+?)\n.*" . $this->opt($this->t("Total Amount")) . "#u", $text, $m)
            && preg_match_all("#" . $currency . "(?: +[A-Z]{2})?[ ]+(\d[\d., ]*) ?([A-Z]{2})\b#", $m[1], $fees)) {
            foreach ($fees[0] as $key => $value) {
                $amount = PriceHelper::parse($fees[1][$key], $currency);
                $totalSum += $amount;
                $f->price()
                    ->fee($fees[2][$key], $amount);
            }
        }

        if (preg_match("#(?:\n[ ]+|/[ ]*)" . $this->opt($this->t("Total Amount")) . "[ ]*:[ ]*(.+)\s*\n#", $text, $mat)) {
            if (preg_match("#^[ ]*([A-Z]{3})[ ]*(\d[\d., ]*)\s*$#", $mat[1], $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m[2], $m[1]))
                    ->currency($m[1]);
            } elseif (preg_match("#^\s*(\d[\d., ]*)\s*$#", $mat[1], $m)) {
                $total = PriceHelper::parse($m[1], $currency);

                if ($total == $totalSum) {
                    $f->price()
                        ->total($total);
                }
            }
        }

        if (preg_match("#" . $this->opt($this->t('Issuing Airline and date')) . "[^:]*:[ ]*.*? (\d{2}\w{3,4}\d{2})\s#u", $text, $m)) {
            $this->relativeDate = $this->normalizeDate($m[1]);
        }

        $segText = $this->cutText($this->t('Itinerary'), $this->t('Receipt'), $text);

        $re = '/(.+ +(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?\d{1,5} +.*\d{1,2}:\d{2}.+ +[A-Z\d]{1,3}[ ]*\n)/u';
        $segmentsFull = preg_split($re, $segText, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        array_shift($segmentsFull);
        $textSeg = [];
        $segments = [];
        $accounts = [];

        foreach ($segmentsFull as $i => $segment) {
            if ($i % 2 === 0) {
                $segments[] = $segment;
            } else {
                $textSeg[] = $segment;
            }
        }

        foreach ($segments as $i => $segment) {
            $s = $f->addSegment();

            $depName = null;
            $arrName = null;
            $airline = null;
            $departureTerminal = null;
            $arrivalTerminal = null;
            $textSeg[$i] = $textSeg[$i] ?? '';

            //   SAO PAULO          RIO DE JANEIRO O66024              Q       24Jul   11:25       12:28       Ok                                                   23K
            $re = '/(?<route>[A-Z][A-Z\s\.]+)\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s+(?<code>[A-Z])\s+(?<day>\d{1,2})\s*(?<month>\w+)\s+(?<dtime>\d{1,2}:\d{2})\s+(?<atime>\d{1,2}:\d{2})\s+(?<status>\w+)?\s+(\w* )?.*?(?:[ ]+(?<seat>\d{1,3}[A-Z]\b))?\s*$/u';
            $reZH = '/(?<route>[[:alpha:]\s\.]+)\s+(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d{1,5})\s+(?<code>[A-Z])\s+(?<day>\d{1,2})\s*(?<month>\w+)\s+(?<dtime>\d{1,2}:\d{2})\s+(?<atime>\d{1,2}:\d{2})\s+(?<status>\w+)?\s+(\w* )?.*?(?:[ ]+(?<seat>\d{1,3}[A-Z]\b))?\s*$/u';

            if (preg_match($re, $segment, $m)
                || ($this->lang == 'zh' && preg_match($reZH, $segment, $m))
            ) {
//                $this->logger->debug('$m = '.print_r( $m,true));
                $airline = $m['al'] . $m['fn'];

                if (preg_match("/^(\S.+)\s{2,}(\S.+)$/", trim($m['route']), $mat)) {
                    $depName = $mat[1];
                    $arrName = $mat[2];
                } else {
                    if (preg_match('/^([[:alpha:]\s\.]+\s?(?:airport|機場))\s+([[:alpha:]\s\.]+)$/iu', trim($m['route']),
                        $mat)) {
                        $depName = $mat[1];
                        $arrName = $mat[2];
                    }
                }

                $re = '/(?:\n|^).{0,18}(?:' . $this->opt($this->t('Terminal')) . '|' . $this->opt($this->t('Terminal',
                        $this->secondLang)) . ')(?: ?\/ ?' . $this->opt($this->t('Terminal')) . '|' . $this->opt($this->t('Terminal',
                        $this->secondLang)) . ')? *:?(.*?)(?: {2,}| +(?:' . $this->opt($this->t('Terminal')) . '|' . $this->opt($this->t('Terminal',
                        $this->secondLang)) . '))/ui';
                $departureTerminal = $this->re($re, $textSeg[$i]);
                $re = '/(?:\n|^).{18,} (?:' . $this->opt($this->t('Terminal')) . '|' . $this->opt($this->t('Terminal',
                        $this->secondLang)) . ')(?: ?\/ ?' . $this->opt($this->t('Terminal')) . '|' . $this->opt($this->t('Terminal',
                        $this->secondLang)) . ')? *:?(.*?) {2,}/ui';
                $arrivalTerminal = $this->re($re, $textSeg[$i]);

                if (!empty($depName) && !empty($arrName)
                    && preg_match("#^(?<c2>(?<c1>.+?)" . $arrName . ")\s+([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)#s",
                        $segment, $column)
                    && preg_match('#^(.+?)(?:' . $this->opt($this->t('Operated by')) . '|' . $this->opt($this->t('Operated by',
                            $this->secondLang)) . ')#s', $textSeg[$i], $match)
                ) {
                    $table = $this->SplitCols($match[1], [0, strlen($column['c1']), strlen($column['c2']) + 15]);

                    if (preg_match("#^(.*?\n)?\s*([^\n]*(?:" . $this->opt($this->t('Terminal')) . "|" . $this->opt($this->t('Terminal', $this->secondLang)) . ").+)$#si",
                        $table[0], $mat)) {
                        $depName .= ' ' . str_replace("\n", ' ', trim($mat[1]));
                    } else {
                        $depName .= ' ' . str_replace("\n", ' ', trim($table[0]));
                    }
                    $depName = trim($depName);

                    if (preg_match("#^(.*?\n)?\s*([^\n]*(?:" . $this->opt($this->t('Terminal')) . "|" . $this->opt($this->t('Terminal', $this->secondLang)) . ").+)$#si",
                        $table[1], $mat)) {
                        $arrName .= ' ' . str_replace("\n", ' ', trim($mat[1]));
                    } else {
                        $arrName .= ' ' . str_replace("\n", ' ', trim($table[1]));
                    }
                    $arrName = trim($arrName);
                }

                if (!empty($departureTerminal)) {
                    $s->departure()
                        ->terminal($departureTerminal);
                }

                if (!empty($arrivalTerminal)) {
                    $s->arrival()
                        ->terminal($arrivalTerminal);
                }

                // Airline
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if (!empty($airlineConfirmation[$m['al']])) {
                    $s->airline()
                        ->confirmation($airlineConfirmation[$m['al']]);
                }

                $year = $this->re("/\s+\w+\s+(\d{4})\n\s*{$segment}/", $segText);

                if (!empty($year)) {
                    $date = $this->normalizeDate($m['day'] . ' ' . $m['month'] . ' ' . $year);
                } else {
                    $date = $this->normalizeDate($m['day'] . ' ' . $m['month']);
                }

                if (!empty($date)) {
                    $s->departure()
                        ->date(strtotime($m['dtime'], $date));
                }

                if (!empty($date)) {
                    $s->arrival()
                        ->date(strtotime($m['atime'], $date));
                }

                if (!empty($s->getArrDate()) && preg_match("#\b(?:" . $this->opt($this->t('Arrival Day')) . "|" . $this->opt($this->t('Arrival Day',
                            $this->secondLang)) . ")[ ]?([-+]\d+)\b#u", $textSeg[$i], $days)
                ) {
                    $s->arrival()
                        ->date(strtotime($days[1] . " days", $s->getArrDate()));
                }

                // Extra
                $s->extra()
                    ->bookingCode($m['code'])
                    ->status($m['status']);

                if (!empty($m['seat'])) {
                    $s->extra()
                        ->seat($m['seat']);
                }

                if (preg_match('/(?:' . $this->opt($this->t('Duration')) . '|' . $this->opt($this->t('Duration',
                        $this->secondLang)) . ') +(.+?)(?:\(|\n)/ui', $textSeg[$i], $m)) {
                    $s->extra()
                        ->duration(trim($m[1]));
                }

                if (preg_match('/(?:' . $this->opt($this->t('Equipment')) . '|' . $this->opt($this->t('Equipment',
                        $this->secondLang)) . ') +(.+?)(?: {2,}| {2}' . $this->opt($this->t('Duration')) . '|\n|$)/ui', $textSeg[$i], $m)) {
                    $s->extra()
                        ->aircraft(trim($m[1]));
                }

                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }

            if (preg_match('/(?:' . $this->opt($this->t('Operated by')) . '|' . $this->opt($this->t('Operated by',
                    $this->secondLang)) . ') +([^\/\s].+?)(?:\s{2,}|\s+' . $this->opt($this->t('Marketed by')) . ')/ui',
                $textSeg[$i], $m)) {
                $s->airline()
                    ->operator(trim($m[1]));
            }

            if ((empty($depName) || empty($arrName)) && !empty($airline)) {
                $names = $this->http->FindNodes("/tr/td[normalize-space()][3][normalize-space()='" . $airline . "']/preceding-sibling::td[normalize-space()]");

                if (count($names) == 2) {
                    $depName = $names[0];
                    $arrName = $names[1];
                }
            }

            if ((empty($depName) || empty($arrName)) && !empty($airline)) {
                if ($this->pdf == null) {
                    $this->convertToHtml($parser);
                }

                $names = [];
                $names[0] = $this->re("/(.+?)\s*{$airline}/",
                    $this->pdf->XPath->query("//text()[normalize-space()='" . $airline . "']/ancestor::p[starts-with(@class, 'ft')][1][not(starts-with(normalize-space(), '" . $airline . "'))]")->item(0)->textContent ?? '');

                if (empty($names[0])) {
                    $names[0] = $this->pdf->XPath->query("//text()[normalize-space()='" . $airline . "']/ancestor::p[starts-with(@class, 'ft')][1]/preceding-sibling::p[1]")->item(0)->textContent ?? '';
                    $names[1] = $this->pdf->XPath->query("//text()[normalize-space()='" . $airline . "']/ancestor::p[starts-with(@class, 'ft')][1]/preceding-sibling::p[2]")->item(0)->textContent ?? '';
                } else {
                    $names[1] = $this->pdf->XPath->query("//text()[normalize-space()='" . $airline . "']/ancestor::p[starts-with(@class, 'ft')][1]/preceding-sibling::p[1]")->item(0)->textContent ?? '';
                }

                $t = $names;
                $names = [$t[1], $t[0]];

                if (!preg_match("/^[[:upper:]\W]+$/u", $names[0])) {
                    unset($names[0]);
                }

                if (!preg_match("/^[[:upper:]\W]+$/u", $names[1])) {
                    unset($names[0], $names[1]);
                }

                if (!empty($names[1])) {
                    $n1 = explode("\n", $names[1]);

                    if (count($n1) == 2) {
                        $depName = $n1[0];
                        $arrName = $n1[1];
                    } elseif (count($n1) == 1) {
                        $arrName = $n1[0];
                    }

                    if (!empty($names[0])) {
                        $n1 = explode("\n", $names[0]);

                        if (count($n1) == 2) {
                            $depName = ($depName ?? '') . ' ' . $n1[0];
                            $arrName = $n1[1];
                        } elseif (count($n1) == 1) {
                            $depName = ($depName ?? '') . ' ' . $n1[0];
                        }
                    }
                }
            }

            if (!empty($depName)) {
                $s->departure()
                    ->name(preg_replace("/\s+/", " ", $depName));
            }

            if (!empty($arrName)) {
                $s->arrival()
                    ->name(preg_replace("/\s+/", " ", $arrName));
            }

            if (preg_match("#Frequent flyer number[ ]*([\dA-Z\-]{7,})(?:\n|$|\s{2,})#s", $textSeg[$i], $m)) {
                $accounts[] = $m[1];
            }
        }

        if (!empty($accounts)) {
            $f->program()
                ->accounts(array_unique($accounts), false);
        }

        foreach ($email->getItineraries() as $it) {
            if ($it->getType() === 'flight' && $it->getId() != $f->getId()) {
                $fSegments = array_map(function ($v) {
                    unset($v['seats']);

                    return $v;
                }, $f->toArray()['segments']);
                $itSegments = array_map(function ($v) {
                    unset($v['seats']);

                    return $v;
                }, $it->toArray()['segments']);

                if (serialize($it->getConfirmationNumbers()) === serialize($f->getConfirmationNumbers())
                    && serialize($itSegments) === serialize($fSegments)
                ) {
                    foreach ($f->getSegments() as $i => $seg) {
                        if (!empty($seg->getSeats())) {
                            $it->getSegments()[$i]->extra()
                                ->seats(array_unique(array_merge($it->getSegments()[$i]->getSeats(), $seg->getSeats())));
                        }
                    }
                    $addTravellers = array_diff(array_column($f->getTravellers(), 0), array_column($it->getTravellers(), 0));

                    if (!empty($addTravellers)) {
                        $it->general()
                            ->travellers($addTravellers, true);
                    }
                    $addTickets = array_diff(array_column($f->getTicketNumbers(), 0), array_column($it->getTicketNumbers(), 0));

                    if (!empty($addTickets)) {
                        $it->issued()
                            ->tickets($addTickets, false);
                    }
                    $addAccounts = array_diff(array_column($f->getAccountNumbers(), 0), array_column($it->getAccountNumbers(), 0));

                    if (!empty($addAccounts)) {
                        $it->program()
                            ->accounts($addAccounts, false);
                    }

                    if (!empty($f->getPrice())) {
                        $fPrice = $f->getPrice()->toArray();
                        $itPrice = $it->getPrice()->toArray();

                        if (!empty($fPrice['cost'])) {
                            $it->price()
                                ->cost($fPrice['cost'] + $itPrice['cost'] ?? 0.0);
                        }

                        if (!empty($fPrice['total'])) {
                            $it->price()
                                ->total($fPrice['total'] + $itPrice['total'] ?? 0.0);
                        }

                        if (!empty($fPrice['fees'])) {
                            foreach ($fPrice['fees'] as $value) {
                                $it->price()
                                    ->fee($value[0], $value[1]);
                            }
                        }
                    }

                    $email->removeItinerary($f);

                    break;
                }
            }
        }

        return $email;
    }

    private function convertToHtml(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return false;
        }
        $body = \PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_COMPLEX);

        if (!empty($body)) {
            $this->pdf = clone $this->http;
            $body = str_replace('&#160;', "\n", $body);
            $this->pdf->SetEmailBody($body);
        }

        return false;
    }

    private function cutText($start, $end, $text)
    {
        if (empty($start) && empty($end) || empty($text)) {
            return false;
        }
        $result = false;

        if ($start === 0) {
            $result = $text;
        } elseif (is_string($start)) {
            $result = stristr($text, $start);
        } elseif (is_array($start)) {
            $positions = [];

            foreach ($start as $i => $st) {
                $pos = stripos($text, $st);

                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }

            if (!empty($positions)) {
                $result = substr($text, min($positions));
            }
        }

        if ($result === false) {
            return false;
        }

        $text = $result;
        $result = false;

        if ($end === 0) {
            $result = $text;
        } elseif (is_string($end)) {
            $result = stristr($text, $end, true);
        } elseif (is_array($end)) {
            $positions = [];

            foreach ($end as $i => $st) {
                $pos = stripos($text, $st);

                if ($pos !== false) {
                    $positions[] = $pos;
                }
            }

            if (!empty($positions)) {
                $result = substr($text, 0, min($positions));
            }
        }

        return $result;
    }

    private function convertToText(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0) {
            return null;
        }

        $result = [];

        foreach ($pdfs as $pdf) {
            $body = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!empty($body) && $this->detectPdf($body) === true) {
                $result[] = $body;
            }
        }

        return $result;
    }

    private function detectLang($body)
    {
        $body = substr($body, 0, 500);
        $detected = false;

        foreach ($this->langDetects as $lang => $langDetect) {
            if (preg_match("/(^|\n) *" . $this->opt($langDetect) . "/u", $body)) {
                $this->lang = $lang;
                $detected = true;

                if (!empty($this->secondLang)) {
                    return true;
                }
            }

            if (preg_match("/(^|\n) *[[:alpha:] ]+ *\/ *" . $this->opt($langDetect) . "/u", $body)) {
                $this->secondLang = $lang;
                $detected = true;

                if (!empty($this->lang)) {
                    return true;
                }
            }
        }

        if ($detected === true) {
            return true;
        }

        return false;
    }

    private function t($s, $lang = null)
    {
        if (empty($lang)) {
            $lang = $this->lang;
        }

        if (!empty($lang) && !empty(self::$dictionary[$lang][$s])) {
            return self::$dictionary[$lang][$s];
        }

        return $s;
    }

    private function normalizeDate($str)
    {
        //$this->logger->debug('$str = '.$str);
        $year = date("Y", $this->relativeDate);

        if ($year == 1970) {
            $year = date("Y", $this->date);
        }

        $in = [
            "#^\s*(\d{2})(\w{3,4})(\d{2})\s*$#", //12Jul18
            "#^(\d+)\s*([^\s\d]+)$#", //25Dec
            "#^(\d+\s*\w+\s*\d{4})$#", //03 Jan 2022
        ];
        $out = [
            "$1 $2 20$3",
            "$1 $2 %year%",
            "$1",
        ];

        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (!empty($this->relativeDate) && strpos($str, '%year%') !== false) {
            $str = preg_replace('/\s*%year%\s*/', '', $str);

            return EmailDateHelper::parseDateRelative($str, $this->relativeDate);
        }

        if (preg_match("#\d{4}#", $str)) {
            return strtotime($str);
        }

        return false;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
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
}
