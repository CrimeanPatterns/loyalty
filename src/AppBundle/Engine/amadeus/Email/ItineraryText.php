<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryText extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-1593594.eml, amadeus/it-1636247.eml, amadeus/it-1636262.eml, amadeus/it-1991527.eml, amadeus/it-2026574.eml, amadeus/it-2096835.eml, amadeus/it-2096863.eml, amadeus/it-2143970.eml, amadeus/it-2401450.eml, amadeus/it-2491155.eml, amadeus/it-2491164.eml, amadeus/it-2511741.eml, amadeus/it-2511759.eml, amadeus/it-2514224.eml, amadeus/it-2514650.eml, amadeus/it-3384015.eml, amadeus/it-372580444.eml, amadeus/it-4675040.eml, amadeus/it-4793714.eml";
    public $reFrom = 'amadeus.com';

    public $reSubject = ['/\d+[A-Z]{3}(?:\d{4})?\s+[A-Z]{3}\s+[A-Z]{3}$/'];

    public $reBody = [
        'nl' => ['DATUM', 'RESERVATIEBEVESTIGING'],
        'de' => ['DATUM', 'VERKEHRSMITTEL'],
        'es' => ['FECHA', 'LOCALIZADOR DE RESERVA'],
        'pt' => ['DATA', 'CODIGO DE RESERVA'],
        'pl' => ['DATA', 'NUMER REZERWACJI'],
        'en' => ['DATE', 'BOOKING REF'],
    ];

    public $date;
    public $lang = '';

    public static $providers = [
        'eurobonus' => [
            'from' => ['@flysas.com'],
            'body' => ['SCANDINAVIAN AIRLINES SYSTEM', 'PLEASE SEE WWW.FLYSAS.COM'],
        ],
        'asia' => [
            'from' => ['@cathaypacific.com'],
            'body' => ['CATHAY PACIFIC AIRWAYS LTD'],
        ],
    ];

    public static $dictionary = [
        "en" => [
            'EndSegments'  => ['RESERVATION NUMBER(S)', 'AB  FREQUENT FLYER', ' '],
            'Confirmation' => ['RESERVATION NUMBER(S)', 'REF.'],
            //'BOOKING REF' => '',
            //'DATE' => '',
            //'TICKET' => '',
            //'RESERVATION' => '',
            //'ON BOARD' => '',
            //'DURATION' => '',
            'EQUIPMENT' => ['EQUIPMENT', 'AIRCRAFT'],
            //'TERMINAL' => '',
            'FLIGHT OPERATED BY' => ['FLIGHT OPERATED BY', '- OPERATED BY'],
            'NON SMOKING'        => ['NON SMOKING', 'NO SMOKING'],
            'SEAT'               => ['SEATS', 'SEAT'],
            'TOTAL'              => ['TOTAL', 'AIR TOTAL'],
            'taxAndFees'         => 'TAXES AND AIRLINE IMPOSED FEES',
            'ARRIVE'             => ['ARRIVE', 'ARRIVAL'],
        ],

        "de" => [
            'EndSegments'  => ['RESERVIERUNGSNUMMER', 'PNR NUMMER'],
            'Confirmation' => ['RESERVIERUNGSNUMMER', 'PNR NUMMER'],
            'BOOKING REF'  => ['BUCHUNGSNR', 'RESERVERINGSNUMMER'],
            'DATE'         => 'DATUM',
            //'TICKET' => '',
            'RESERVATION' => 'BUCHUNG',
            //'ON BOARD' => '',
            'DURATION'  => ['DAUER', 'DUUR'],
            'EQUIPMENT' => 'VLIEGTUIGTYPE',
            //'TERMINAL' => '',
            'FLIGHT OPERATED BY' => 'FLUGZEUGEIGNER',
            //'SEAT' => '',
            //NON SMOKING = '',
            //'TOTAL' => '',
            //'AIR FARE' => '',
            //'taxAndFees' => '',

            //'SERVICE' => '',
            //'FROM' => '',
            //'TO' => '',
            //'DEPART' => '',
            //'ARRIVE' => '',
        ],
        "nl" => [
            'EndSegments'  => ['RESERVIERUNGSNUMMER', 'PNR NUMMER'],
            'Confirmation' => ['RESERVIERUNGSNUMMER', 'PNR NUMMER'],
            'BOOKING REF'  => ['BUCHUNGSNR', 'RESERVERINGSNUMMER'],
            'DATE'         => 'DATUM',
            //'TICKET' => '',
            'RESERVATION' => ['BUCHUNG', 'RESERVERING'],
            //'ON BOARD' => '',
            'DURATION'  => 'DUUR',
            'EQUIPMENT' => 'VLIEGTUIGTYPE',
            //'TERMINAL' => '',
            'FLIGHT OPERATED BY' => 'FLUGZEUGEIGNER',
            //'SEAT' => '',
            'NON SMOKING' => 'NIET ROKEN',
            // 'TOTAL' => '',
            // 'AIR FARE' => '',
            // 'taxAndFees' => '',

            'SERVICE' => 'DIENST',
            'FROM'    => 'VAN',
            'TO'      => 'NAAR',
            'DEPART'  => 'VERTREK',
            'ARRIVE'  => 'AANK',
        ],
        "es" => [
            'EndSegments'  => ['LOCALIZADOR(ES)'],
            'Confirmation' => ['LOCALIZADOR(ES)'],
            'BOOKING REF'  => ['LOCALIZADOR DE RESERVA'],
            'DATE'         => 'FECHA',
            'TICKET'       => 'BILLETE',
            'RESERVATION'  => 'RESERVA',
            'ON BOARD'     => 'A BORDO',
            'DURATION'     => ['DURACION'],
            'EQUIPMENT'    => 'TIPO DE EQUIPO',
            //'TERMINAL' => '',
            'FLIGHT OPERATED BY' => 'FLUGZEUGEIGNER',
            'SEAT'               => 'ASIENTO',
            'NON SMOKING'        => 'NO FUMADOR',
            // 'TOTAL' => '',
            // 'AIR FARE' => '',
            // 'taxAndFees' => '',

            'SERVICE' => 'SERVICIO',
            'FROM'    => 'DE',
            'TO'      => 'A',
            'DEPART'  => 'SALIDA',
            'ARRIVE'  => 'LLEGADA',
        ],
        "pl" => [ // it-4675040.eml
            'EndSegments'  => ['NR REZ. PRZEWOZNIKA'],
            'Confirmation' => 'NR REZ. PRZEWOZNIKA',
            'BOOKING REF'  => ['NUMER REZERWACJI'],
            'DATE'         => 'DATA',
            'TICKET'       => 'BILET',
            'RESERVATION'  => 'REZERWACJA',
            //'ON BOARD' => '',
            //'DURATION' => [''],
            'EQUIPMENT' => 'SRODEK TRANSPORTU',
            //'TERMINAL' => '',
            'FLIGHT OPERATED BY' => 'REJS OBSLUGUJE',
            //'SEAT' => '',
            //NON SMOKING = '',
            'TOTAL' => 'KWOTA OGOLEM',
            //'AIR FARE' => '',
            //'taxAndFees' => '',

            'SERVICE' => 'USLUGA',
            'FROM'    => 'Z',
            'TO'      => 'DO',
            'DEPART'  => 'WYLOT',
            'ARRIVE'  => 'PRZYLOT',
        ],
        "pt" => [
            'EndSegments'  => ['REFERENCIA(S) DE RESE'],
            'Confirmation' => ['REFERENCIA(S) DE RESE'],
            'BOOKING REF'  => ['CODIGO DE RESERVA'],
            'DATE'         => 'DATA',
            'TICKET'       => 'BILHETE',
            'RESERVATION'  => 'RESERVA',
            'ON BOARD'     => 'A BORDO',
            'DURATION'     => 'DURACAO',
            'EQUIPMENT'    => 'EQP',
            //'TERMINAL' => '',
            'FLIGHT OPERATED BY' => '',
            'SEAT'               => 'ASSENTO',
            'NON SMOKING'        => ['NAO-FUMANTE', 'NAO FUMANTE'],
            //'TOTAL' => '',
            //'AIR FARE' => '',
            //'taxAndFees' => '',

            'SERVICE' => 'SERVICO',
            'FROM'    => 'DE',
            'TO'      => 'PARA',
            'DEPART'  => 'PARTIDA',
            'ARRIVE'  => 'CHEGADA',
        ],
    ];

    private $patterns = [
        'travellerName' => '[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]', // SALIM/TALA
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?(?:\d{1,3})', // 932 6536800367
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->date = strtotime("-1 week", strtotime($parser->getDate()));
        $text = strip_tags($parser->getHTMLBody());

        //Segments
        $allSegments = $this->re("/\n([A-Z\s]+-\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s\d+.+)\n{$this->opt($this->t('EndSegments'))}/msu", $text);

        if (empty($allSegments)) { //it-1593594
            $text = $parser->getBodyStr();
            // remove garbage
            $text = preg_replace("/^.+\nSubject/ms", "", $text);
            $text = preg_replace("/[-]+\=\_NextPart.+$/ms", "", $text);

            $allSegments = $this->re("/\n([A-Z\s]+-\s+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s\d+.+)\n{$this->opt($this->t('EndSegments'))}/msu", $text);
            $allSegments = str_replace("=20", "", $allSegments);
        }

        // $this->logger->error($allSegments);

        if ($this->lang == 'es') {
            $segments = preg_split("/\n\s*\n\s*\n/u", $allSegments);
        } else {
            $segments = preg_split("/\n\s*\n/u", $allSegments);
        }

        $busSegments = [];
        $trainSegments = [];
        $flightSegments = [];

        foreach ($segments as $segment) {
            if (preg_match("/ BUS /", $segment) && preg_match("/^\D+\s+\-\s+[A-Z]{2}\s+\d{2,4}/", $segment, $m)) {
                $busSegments[] = $segment;
            } elseif (preg_match("/INTER-CITY EXPRESS/", $segment) && preg_match("/^\D+\s+\-\s+[A-Z\d]{2}\s+(?<trainNumber>\d{2,4})/", $segment, $m)) {
                $trainSegments[] = $segment;
            } elseif (preg_match("/^\D+\s+\-\s+(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<airlineNUmber>\d{2,4})/", $segment, $m)) {
                $flightSegments[] = $segment;
            }
        }

        if (count($busSegments) > 0) {
            $this->SegmentBus($email, $busSegments, $text);
        }

        if (count($trainSegments) > 0) {
            $this->SegmentTrain($email, $trainSegments, $text);
        }

        if (count($flightSegments) > 0) {
            $this->SegmentFlight($email, $flightSegments, $text);
        }

        $providerCode = null;

        foreach (self::$providers as $code => $params) {
            if (!empty($params['from'])) {
                foreach ($params['from'] as $pf) {
                    if (stripos(implode('', $parser->getFrom()), $pf) !== false) {
                        $providerCode = $code;

                        break 2;
                    }
                }
            }

            if (!empty($params['body'])) {
                if (preg_match("/{$this->opt($params['body'])}.*{$this->opt($this->t('SERVICE'))}/s", $text)) {
                    $providerCode = $code;

                    break;
                }
            }
        }

        if (!empty($providerCode)) {
            $email->setProviderCode($providerCode);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
    }

    public function SegmentFlight(Email $email, $segments, $text): void
    {
        $f = $email->add()->flight();

        $bookingRef = $this->re("/{$this->opt($this->t('BOOKING REF'))}\s+([\dA-Z]{6})/", $text);

        if (!empty($bookingRef)) {
            $f->general()
                ->confirmation($bookingRef);
        }

        $dateResrv = $this->normalizeDate($this->re("/\s+{$this->t($this->opt($this->t('DATE')))}\s+(\d+[A-Z]+\d{2})\s+/", $text));
        $f->general()
            ->date($dateResrv);

        $tRe1 = "/^ *(?<tName>{$this->patterns['travellerName']})\s*;\s*(?:TICKET|{$this->opt($this->t('TICKET'))})[: ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\D+(?<ticket>{$this->patterns['eTicket']}) *$/mu";
        // MINDEL/CHARLES MR                        TICKET:LA/ETKT 045 7718172202
        // MINDEL/CHARLES MR AT3 27DEC 30           TICKET:LA/ETKT 045 7718172202
        $tRe2 = "/^[^\n\S]*(?<tName>{$this->patterns['travellerName']})(?: AT3 27DEC 30)?[^\n\S]{3,}(?:TICKET|{$this->opt($this->t('TICKET'))})[: ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?(?:[ \/]*ETKT)? *(?<ticket>{$this->patterns['eTicket']})[^\n\S]*$/mu";

        if ((preg_match_all($tRe1, $text, $m) && $this->lang !== 'pl')
            || preg_match_all($tRe2, $text, $m)
        ) {
            $f->general()->travellers($this->niceName(array_unique($m['tName'])), true);
            $f->issued()->tickets(array_unique($m['ticket']), false);
        } elseif (preg_match_all("/{$this->opt($this->t('FOR'))}\s+(\D+ (?:MR|MS|MRS))/u", $text, $m)) {
            $travellers = array_map(function ($s) {
                return preg_replace("/\s+/", ' ', $s);
            }, $m[1]);
            $f->general()->travellers($this->niceName(array_filter(array_unique($travellers))), true);
        } elseif (preg_match_all("/ETKT[: ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\D+(?<ticket>\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})\s*\n/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);

            if (preg_match("/\:\s*([A-Z]+\/[A-Z]+\s*[A-Z]+)\s*\d+\w+\s*[A-Z]{3}/", $text, $m)) {
                $f->general()
                    ->traveller($this->niceName($m[1]));
            }

            if (preg_match_all("/{$this->opt($this->t('ACCOUNT NUMBER'))}\s*(\d{5,})/", $text, $m)) {
                $f->setAccountNumbers($m[1], false);
            }
        }

        if (count($f->getTravellers()) == 0) {
            if (preg_match("/^(.+?{$this->opt($this->t('DATE'))}.+?)\s*{$this->opt($this->t('SERVICE'))}\s+{$this->opt($this->t('FROM'))}/smu", $text, $match)) {
                if (preg_match("/(?:\S\s*|\d)\n([A-Z]{2,}\D+{$this->opt($this->t('DATE'))}.+)/msu", $match[1], $m)
                 || preg_match("/(?:^|\n)[A-Z]{2,}(?: [A-Z\-]+)* {2,}{$this->opt($this->t('DATE'))} *[A-Z\d]+\s*\n+([\s\S]+)/u", $match[1], $m)
                 || preg_match("/[A-Z]{2,}(?: [A-Z\-]+)* {2,}{$this->opt($this->t('DATE'))} *[A-Z\d]+\s*\n+([\s\S]+)/u", $match[1], $m)
                ) {
                    $table = $this->SplitCols($m[1]);

                    if (count($table) == 2 && preg_match_all("/\n\s*([A-Z ]+\/[A-Z\s*]+)(?=\n|$)/", $table[1], $p)) {
                        $f->general()->travellers($this->niceName($p[1]));
                    }
                }
                $tRe = "/^\s*(?:TICKET|{$this->opt($this->t('TICKET'))})[: ]*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])?(?:[ \/]*ETKT)? *(?<ticket>{$this->patterns['eTicket']})[^\n\S]*$/mu";

                if (preg_match_all($tRe, $text, $m)) {
                    $f->issued()->tickets(array_filter(array_unique($m[1])), false);
                }
            }
        }

        if (count($f->getTravellers()) == 0) {
            if (preg_match_all("/-([A-Z\/]+\s*MRS)/", $text, $m)) {
                // it-4675040.eml
                $f->general()->travellers($this->niceName(array_filter(array_unique($m[1]))), true);

                if (preg_match_all("/{$this->opt($this->t('TICKET'))}[:\s]+[A-Z]+\/ETKT[:\s]+({$this->patterns['eTicket']})/u",
                    $text, $m)) {
                    $f->issued()->tickets(array_filter(array_unique($m[1])), false);
                }
            }
        }

        //Accounts
        $ffNumbers = [];

        //Price
        if (preg_match("/^\s*{$this->opt($this->t('TOTAL'))}[: ]+(?<currency>[A-Z]{3})\s*(?<amount>\d[,.\'\d ]*?)\s*$/m", $text, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));

            if (preg_match('/^\s*' . $this->opt($this->t('AIR FARE')) . '[: ]+(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*?)\s*$/m', $text, $m)) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            if (preg_match('/^\s*(?<name>' . $this->opt($this->t('taxAndFees')) . ')[: ]+(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<charge>\d[,.\'\d ]*?)\s*$/m', $text, $m)) {
                $f->price()->fee($m['name'], PriceHelper::parse($m['charge'], $currencyCode));
            }
        }

        foreach ($segments as $segment) {
            if (preg_match("/^\D+\s+-\s+(?<airlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<airlineNUmber>\d+)/", $segment, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['airlineName'])
                    ->number($m['airlineNUmber']);

                if (preg_match_all("/{$m['airlineName']}[ ]+{$this->opt($this->t('FREQUENT FLYER'))}\s*([A-Z\d]{5,})(?:[- ]+{$this->patterns['travellerName']}|\/|$)/mu", $text, $accountMatches)) {
                    $ffNumbers = array_merge($ffNumbers, $accountMatches[1]);
                }

                $confNumber = $this->re("/{$this->opt($this->t('Confirmation'))}.+{$s->getAirlineName()}\/?([A-Z\d]{6,})\s*/u", $text);

                if (empty($confNumber)) {
                    $confNumber = $this->re("/{$this->opt($this->t('Confirmation'))}\s+([A-Z\d]{6,})/u", $segment);
                }

                if (!empty($confNumber)) {
                    $s->airline()
                        ->confirmation($confNumber);
                }

                $operatedBy = $this->re("/{$this->opt($this->t('FLIGHT OPERATED BY'))}\s+(.+)/", $segment);

                if (stripos($segment, '-') !== false) {
                    $operatedBy = preg_replace("/\-.+/", "", $operatedBy);
                }

                if (!empty($operatedBy)) {
                    $s->airline()
                        ->operator($operatedBy);
                }

                if (preg_match("/({$this->opt($this->t('NON SMOKING'))})/", $segment)) {
                    $s->extra()
                        ->smoking(false);
                }

                //Search Depart/Arrival Block

                if (preg_match("/^(?:.+\n)?.+\n(.+\n.+\n(?:NON\s+STOP|\d+\s*STOP|NON-STOP|DIRECTO|SEM ESCALA).+)\n/u", trim($segment), $m)
                    || preg_match("/^(?:.+\n)?.+\n(.+\n?.+\n(?:NON\s+STOP|\d+\s*STOP|NON-STOP).+)\n/u", trim($segment), $m)
                    || preg_match("/^(?:.+\n)?.+\n(.+\n.+\n\s+(?:TERMINAL).+)\n/u", trim($segment), $m)
                    || preg_match("/^.+\-.+\n(.+)\n.+\n/u", trim($segment), $m)
                ) {
                    //if depDate in row №2 to Depart/Arrival Block
                    if (strlen($this->re("/^(\s+)/u", $m[1])) > 6) {
                        $m[1] = preg_replace("/^[ ]{6}/u", "COLUMN", $m[1]);
                    }

                    if (preg_match("/^([A-Z]{2}\s+\d{2}[A-Z]+)/", $m[1], $match)) {
                        $m[1] = preg_replace("/{$match[1]}/u", str_replace(" ", "", $match[1]), $m[1]);
                    }

                    $depArrInfo = $this->splitCols($m[1]);

                    if (!isset($depArrInfo[4])) {
                        $m[1] = preg_replace("/^(\s)/u", "1", $m[1]);
                        $depArrInfo = $this->splitCols($m[1]);
                    }

                    if (!isset($depArrInfo[4])) {
                        $this->logger->notice("SEGMENT NOT FOUND!!!");

                        return;
                    }

                    $depDate = $this->re("/^([A-Z]+\s*\d+[A-Z]+)/mu", $depArrInfo[0]);

                    if (empty($depDate)) {
                        $depDate = $this->re("/^(\d+\w+)\s/mu", $depArrInfo[1]);
                        $depArrInfo[1] = str_replace($depDate, "", $depArrInfo[1]);
                    }

                    $arrDate = $this->re("/\d{4}[A-Z]\s*(\d+[A-Z]+)/u", $depArrInfo[4]);

                    if (empty($arrDate)) {
                        $arrDate = $depDate;
                    }

                    $depName = $this->re("/^(.+?)(?:\n\s*terminal \w+[\s\S]*)?$/usi", $depArrInfo[1]);
                    $arrName = $this->re("/^(.+?)(?:\n\s*terminal \w+[\s\S]*)?$/usi", $depArrInfo[2]);

                    $depTime = $this->re("/(\d{3,4}[A-Z]?)/u", $depArrInfo[3]);
                    $arrTime = $this->re("/(\d{3,4}[A-Z]?)/u", $depArrInfo[4]);

                    $s->departure()
                        ->name(preg_replace("#\s+#u", " ", $depName))
                        ->date($this->normalizeDate($depDate . ', ' . $depTime, $dateResrv ?? $this->date))
                        ->noCode();

                    $s->arrival()
                        ->name(preg_replace("#\s+#u", " ", $arrName))
                        ->date($this->normalizeDate($arrDate . ', ' . $arrTime, $dateResrv ?? $this->date))
                        ->noCode();

                    if ($s->getDepDate() > $s->getArrDate()) {
                        $s->arrival()
                            ->date(strtotime('+1 day', $s->getArrDate()));
                    }

                    if (!empty($depTerminal = $this->re("/TERMINAL\s+(\S+)$/u", $depArrInfo[1]))) {
                        $s->departure()
                            ->terminal($depTerminal);
                    }

                    if (!empty($arrTerminal = $this->re("/TERMINAL\s+(\S+)$/u", $depArrInfo[2]))) {
                        $s->arrival()
                            ->terminal($arrTerminal);
                    }
                }

                if (preg_match("/^[>\s]*{$this->opt($this->t('EQUIPMENT'))}[: ]*([^:\n]+?)\s*$/mu", $segment, $m)) {
                    $s->extra()->aircraft($m[1]);
                }

                if (preg_match("/{$this->opt($this->t('RESERVATION'))}\s+(\w+)\s*-\s*([A-Z]{1})\s+(\w+)/u", $segment, $m)) {
                    $s->setStatus($m[1]);

                    $s->extra()
                        ->bookingCode($m[2])
                        ->cabin($m[3]);
                } elseif (preg_match("/^(?<code>[A-Z]{1,2})\s+(?<cabin>\w+)\s*AIRCRAFT\:\s+(?<aircraft>.+)(?:\n|$)/mu", $segment, $m)) {
                    $s->extra()
                        ->bookingCode($m['code'])
                        ->cabin($m['cabin'])
                        ->aircraft($m['aircraft']);
                }

                if (preg_match_all("/{$this->opt($this->t('SEAT'))}[:\s]+([A-Z\d]{3,4})\s/u", $segment, $m)) {
                    $s->extra()
                        ->seats(array_unique(array_filter($m[1])));
                } else {
                    $seat = $this->re("/{$this->opt($this->t('SEAT'))}[:\s]+(\d[A-Z\d\/]*[A-Z])\s/u", $segment);
                    $seats = explode("/", $seat);

                    if (count($seats) > 0) {
                        $s->extra()
                            ->seats(array_unique(array_filter($seats)));
                    }
                }

                $duration = $this->re("/{$this->opt($this->t('DURATION'))}\s*\:?\s*([\d\:]+)/u", $segment);

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $meal = $this->re("/{$this->opt($this->t('ON BOARD'))}\s*\:?\s*([A-Z\/]+)/smu", $segment);

                if (!empty($meal)) {
                    $s->extra()
                        ->meal($meal);
                }

                $stop = $this->re("/(\d+)\s*{$this->opt($this->t('STOP'))}/u", $segment);

                if (!empty($stop)) {
                    $s->extra()
                        ->stops($stop);
                }
            }
        }

        if (count($ffNumbers) > 0) {
            $f->program()->accounts(array_unique($ffNumbers), false);
        }
    }

    public function SegmentBus(Email $email, $segments, $text): void
    {
        $b = $email->add()->bus();

        //General
        $bookingRef = $this->re("/{$this->opt($this->t('BOOKING REF'))}\s+([\dA-Z]{6})/", $text);

        if (!empty($bookingRef)) {
            $b->general()
                ->confirmation($bookingRef);
        }

        $dateResrv = $this->normalizeDate($this->re("/\s+{$this->t($this->opt($this->t('DATE')))}\s+(\d+[A-Z]+\d{2})\s+/", $text));
        $b->general()
            ->date($dateResrv);
        //Travellers
        if (preg_match_all("/{$this->opt($this->t('Confirmation'))}.+\n(\D+)\s+{$this->opt($this->t('TICKET'))}\D+([\d\s]+)/", $text, $m) && $this->lang !== 'pl') {
            $b->general()
                ->travellers($this->niceName(array_filter(array_unique($m[1]))), true);
            $b->setTicketNumbers(array_filter(array_unique($m[2])), false);
        } elseif (preg_match_all("/{$this->opt($this->t('FOR'))}\s+(\D+ (?:MR|MS))/msu", $text, $m)) {
            $b->general()
                ->travellers($this->niceName(array_filter(array_unique($m[1]))), true);
        } elseif (preg_match_all("/({$this->patterns['travellerName']})\s+MR/u", $text, $m)) {
            $b->general()
                ->travellers($this->niceName(array_filter(array_unique($m[1]))), true);
        }

        foreach ($segments as $segment) {
            if (preg_match("/^\D+\s+\-\s+([A-Z]{2}\s+\d{2,4})/", $segment, $m)) {
                $s = $b->addSegment();

                $s->extra()->number($m[1]);

                //Search Depart/Arrival Block

                if (preg_match("/^(?:.+\n)?.+\n(.+\n.+\n(?:NON\s+STOP|\d+\s*STOP|NON-STOP|DIRECTO|SEM ESCALA).+)\n/u", trim($segment), $m)
                    || preg_match("/^(?:.+\n)?.+\n(.+\n?.+\n(?:NON\s+STOP|\d+\s*STOP|NON-STOP).+)\n/u", trim($segment), $m)
                    || preg_match("/^(?:.+\n)?.+\n(.+\n.+\n\s+(?:TERMINAL).+)\n/u", trim($segment), $m)
                ) {
                    //if depDate in row №2 to Depart/Arrival Block
                    if (strlen($this->re("/^(\s+)/u", $m[1])) > 6) {
                        $m[1] = preg_replace("/^[ ]{6}/u", "COLUMN", $m[1]);
                    }

                    if (preg_match("/^([A-Z]{2}\s+\d{2}[A-Z]+)/", $m[1], $match)) {
                        $m[1] = preg_replace("/{$match[1]}/u", str_replace(" ", "", $match[1]), $m[1]);
                    }

                    $depArrInfo = $this->splitCols($m[1]);

                    if (!isset($depArrInfo[4])) {
                        $this->logger->notice("SEGMENT NOT FOUND!!!");

                        return;
                    }

                    $depDate = $this->re("/[A-Z]+\s*(\d+[A-Z]+)/u", $depArrInfo[0]);

                    $arrDate = $this->re("/\d{4}[A-Z]\s*(\d+[A-Z]+)/u", $depArrInfo[4]);

                    if (empty($arrDate)) {
                        $arrDate = $depDate;
                    }

                    $depName = $this->re("/^(.+)\n/u", $depArrInfo[1]);
                    $arrName = $this->re("/^(.+)\n/u", $depArrInfo[2]);

                    $depTime = $this->re("/(\d{4}[A-Z]?)/u", $depArrInfo[3]);
                    $arrTime = $this->re("/(\d{4}[A-Z]?)/u", $depArrInfo[4]);

                    $s->departure()
                        ->name(preg_replace("#\s+#u", " ", $depName))
                        ->date($this->normalizeDate($depDate . ', ' . $depTime, $dateResrv ?? $this->date));

                    $s->arrival()
                        ->name(preg_replace("#\s+#u", " ", $arrName))
                        ->date($this->normalizeDate($arrDate . ', ' . $arrTime, $dateResrv ?? $this->date));
                }

                if (preg_match("/{$this->opt($this->t('RESERVATION'))}\s+(\w+)\s*-\s*([A-Z]{1})\s+(\w+)/u", $segment, $m)) {
                    $s->setStatus($m[1]);

                    $s->extra()
                        ->bookingCode($m[2])
                        ->cabin($m[3]);
                }

                $seat = $this->re("/{$this->opt($this->t('SEAT'))}\:?\s+(\d+[A-Z]{1})\s+\w+/u", $segment);

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat);
                }

                $duration = $this->re("/{$this->opt($this->t('DURATION'))}\s*\:?\s*([\d\:]+)/u", $segment);

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $meal = $this->re("/{$this->opt($this->t('ON BOARD'))}\s*\:?\s*([A-Z\/]+)/smu", $segment);

                if (!empty($meal)) {
                    $s->extra()
                        ->meal($meal);
                }

                $stop = $this->re("/(\d+)\s*{$this->opt($this->t('STOP'))}/u", $segment);

                if (!empty($stop)) {
                    $s->extra()
                        ->stops($stop);
                }
            }
        }
    }

    public function SegmentTrain(Email $email, $segments, $text): void
    {
        $t = $email->add()->train();

        $bookingRef = $this->re("/{$this->opt($this->t('BOOKING REF'))}\s+([\dA-Z]{6})/", $text);

        if (!empty($bookingRef)) {
            $t->general()
                ->confirmation($bookingRef);
        }

        $dateResrv = $this->normalizeDate($this->re("/\s+{$this->t($this->opt($this->t('DATE')))}\s+(\d+[A-Z]+\d{2})\s+/", $text));
        $t->general()
            ->date($dateResrv);

        if (preg_match_all("/{$this->opt($this->t('Confirmation'))}.+\n(\D+)\s+{$this->opt($this->t('TICKET'))}\D+([\d\s]+)/", $text, $m) && $this->lang !== 'pl') {
            $t->general()
                ->travellers($this->niceName(array_filter(array_unique($m[1]))), true);
            $t->setTicketNumbers(array_filter(array_unique($m[2])), false);
        } elseif (preg_match_all("/{$this->opt($this->t('FOR'))}\s+(\D+ (?:MR|MS))/msu", $text, $m)) {
            $t->general()
                ->travellers($this->niceName(array_filter(array_unique($m[1]))), true);
        } elseif (preg_match_all("/({$this->patterns['travellerName']})\s+MR/u", $text, $m)) {
            $t->general()
                ->travellers($this->niceName(array_filter(array_unique($m[1]))), true);
        }

        foreach ($segments as $segment) {
            if (preg_match("/^\D+\s+\-\s+(?<service>[A-Z\d]{2})\s+(?<trainNumber>\d{2,4})/", $segment, $m)) {
                $s = $t->addSegment();

                $s->extra()
                    ->service($m['service'])
                    ->number($m['trainNumber']);

                //Search Depart/Arrival Block

                if (preg_match("/^(?:.+\n)?.+\n(.+\n.+\n(?:NON\s+STOP|\d+\s*STOP|NON-STOP|DIRECTO|SEM ESCALA).+)\n/u", trim($segment), $m)
                    || preg_match("/^(?:.+\n)?.+\n(.+\n?.+\n(?:NON\s+STOP|\d+\s*STOP|NON-STOP).+)\n/u", trim($segment), $m)
                    || preg_match("/^(?:.+\n)?.+\n(.+\n.+\n\s+(?:TERMINAL).+)\n/u", trim($segment), $m)
                ) {
                    //if depDate in row №2 to Depart/Arrival Block
                    if (strlen($this->re("/^(\s+)/u", $m[1])) > 6) {
                        $m[1] = preg_replace("/^[ ]{6}/u", "COLUMN", $m[1]);
                    }

                    if (preg_match("/^([A-Z]{2}\s+\d{2}[A-Z]+)/", $m[1], $match)) {
                        $m[1] = preg_replace("/{$match[1]}/u", str_replace(" ", "", $match[1]), $m[1]);
                    }

                    $depArrInfo = $this->splitCols($m[1]);

                    if (!isset($depArrInfo[4])) {
                        $this->logger->notice("SEGMENT NOT FOUND!!!");

                        return;
                    }

                    $depDate = $this->re("/([A-Z]+\s*\d+[A-Z]+)/u", $depArrInfo[0]);

                    $arrDate = $this->re("/\d{4}[A-Z]\s*(\d+[A-Z]+)/u", $depArrInfo[4]);

                    if (empty($arrDate)) {
                        $arrDate = $depDate;
                    }

                    $depName = $this->re("/^(.+)\n/u", $depArrInfo[1]);
                    $arrName = $this->re("/^(.+)\n/u", $depArrInfo[2]);

                    $depTime = $this->re("/(\d{4}[A-Z]?)/u", $depArrInfo[3]);
                    $arrTime = $this->re("/(\d{4}[A-Z]?)/u", $depArrInfo[4]);

                    $s->departure()
                        ->name(preg_replace("#\s+#u", " ", $depName))
                        ->date($this->normalizeDate($depDate . ', ' . $depTime, $dateResrv ?? $this->date));

                    $s->arrival()
                        ->name(preg_replace("#\s+#u", " ", $arrName))
                        ->date($this->normalizeDate($arrDate . ', ' . $arrTime, $dateResrv ?? $this->date));
                }

                if (preg_match("/{$this->opt($this->t('RESERVATION'))}\s+(\w+)\s*-\s*([A-Z]{1})\s+(\w+)/u", $segment, $m)) {
                    $s->setStatus($m[1]);

                    $s->extra()
                        ->bookingCode($m[2])
                        ->cabin($m[3]);
                }

                $seat = $this->re("/{$this->opt($this->t('SEAT'))}\:?\s+(\d+[A-Z]{1})\s+\w+/u", $segment);

                if (!empty($seat)) {
                    $s->extra()
                        ->seat($seat);
                }

                $duration = $this->re("/{$this->opt($this->t('DURATION'))}\s*\:?\s*([\d\:]+)/u", $segment);

                if (!empty($duration)) {
                    $s->extra()
                        ->duration($duration);
                }

                $meal = $this->re("/{$this->opt($this->t('ON BOARD'))}\s*\:?\s*([A-Z\/]+)/smu", $segment);

                if (!empty($meal)) {
                    $s->extra()
                        ->meal($meal);
                }

                $stop = $this->re("/(\d+)\s*{$this->opt($this->t('STOP'))}/u", $segment);

                if (!empty($stop)) {
                    $s->extra()
                        ->stops($stop);
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, $this->reFrom) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) === false) {
            foreach ($this->reSubject as $subject) {
                if (preg_match($subject, $headers["subject"])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignLang() == true) {
            $text = text($parser->getHTMLBody());
            //FLIGHT
            if (preg_match("/{$this->opt($this->t('SERVICE'))}\s+{$this->opt($this->t('FROM'))}\s+{$this->opt($this->t('TO'))}\s+{$this->opt($this->t('DEPART'))}\s+[=]?\s*{$this->opt($this->t('ARRIVE'))}\s+[-]{14}\s+[-]{19}\s+[-]{18,22}\s+[-]{6,8}\s+[=]?\s*[-]{7}/u", $text)
                || preg_match("/{$this->opt($this->t('SERVICE'))}\s+{$this->opt($this->t('DATE'))}\s+{$this->opt($this->t('FROM'))}\s+{$this->opt($this->t('TO'))}\s+{$this->opt($this->t('DEPART'))}\s+[=]?\s*{$this->opt($this->t('ARRIVE'))}\s+\n/u", $text)
                || preg_match("/AIR/", $text) && preg_match("/TERMINAL/", $text) && preg_match("/ON BOARD/", $text) && preg_match("/DURATION/", $text)
                || preg_match("/AIR/", $text) && preg_match("/TERMINAL/", $text) && preg_match("/ON BOARD/", $text) && preg_match("/DURATION/", $text)
            ) {
                foreach ($this->reBody as $body) {
                    foreach ($body as $word) {
                        if (strpos($text, $word[0]) && strpos($text, $word[1])) {
                            return true;
                        }
                    }
                }
            }

            //TRAIN OR BUS
            if (preg_match("/INTER-CITY EXPRESS/", $text) && preg_match("/ BUS /", $text)) {
                foreach ($this->reBody as $body) {
                    foreach ($body as $word) {
                        if (strpos($text, $word[0]) && strpos($text, $word[1])) {
                            return true;
                        }
                    }
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
        return array_keys(self::$providers);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if (strpos($this->http->Response["body"], $reBody[0]) !== false && strpos($this->http->Response["body"], $reBody[1]) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str, $dateRelative = null)
    {
        // $this->logger->debug('$date IN = '.print_r( $str,true));
        // $this->logger->debug('$dateRelative = '.print_r( $dateRelative,true));
        $year = date("Y", $dateRelative);
        $in = [
            '#^(\d+)([A-Z]+)(\d{2})$#',
            '#^\s*([A-Z]+) *(\d+)([A-Z]+)\,\s+(\d{1,2})(\d{2}[AP])$#', //MON 09OCT, 1230P
            '#^\s*([A-Z]+) *(\d+)([A-Z]+)\,\s+(\d{1,2})(\d{2})N?$#', //MON 09OCT, 1230N
            '#^\s*(\d+)([A-Z]+)\,\s+(\d{1,2})(\d{2}[AP])$#', //09OCT, 1230P
            '#^\s*(\d+)([A-Z]+)\,\s+(\d{1,2})(\d{2})N?$#', //09OCT, 1230N
        ];
        // $year - for date without year and with week
        // %year% - for date without year and without week
        $out = [
            '$1 $2 20$3',
            "$1, $2 $3 $year, $4:$5M",
            "$1, $2 $3 $year, $4:$5",
            "$1 $2 %year%, $3:$4M",
            "$1 $2 %year%, $3:$4",
        ];

        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$date Replace = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%year%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->debug('$date Translate = '.print_r( $str,true));

        if (!empty($dateRelative) && strpos($str, '%year%') !== false && preg_match('/^\s*(?<date>\d+ \w+) %year%(?:\s*,\s(?<time>\d{1,2}:\d{1,2}.*))?$/', $str, $m)) {
            // $this->logger->debug('$date (no week, no year) = '.print_r( $m['date'],true));
            $str = EmailDateHelper::parseDateRelative($m['date'], $dateRelative);

            if (!empty($str) && !empty($m['time'])) {
                return strtotime($m['time'], $str);
            }

            return $str;
        } elseif ($year > 2000 && preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $str, $m)) {
            // $this->logger->debug('$date (week no year) = '.print_r( $str,true));
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}$/", $str)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($str);
        } else {
            return null;
        }

        return null;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#u", "|", $row))));
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

    private function niceName($travellers)
    {
        if (is_array($travellers)) {
            $travellers = array_filter($travellers, function ($v) {
                return preg_match("/^\s*AGENT [A-Z]{2}\/[A-Z]{2}\s*$/", $v) ? false : true;
            });
        } elseif (is_string($travellers) && preg_match("/^\s*AGENT [A-Z]{2}\/[A-Z]{2}\s*$/", $travellers)) {
            return null;
        }

        return preg_replace("/ (MS|MR|MRS|MSTR|MISS)\s*$/", '', $travellers);
    }
}
