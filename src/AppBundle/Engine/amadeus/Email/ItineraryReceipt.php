<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-11.eml, amadeus/it-111.eml, amadeus/it-11909370.eml, amadeus/it-11909603.eml, amadeus/it-1405641.eml, amadeus/it-1706320.eml, amadeus/it-1749840.eml, amadeus/it-1749841.eml, amadeus/it-1749843.eml, amadeus/it-1749844.eml, amadeus/it-1749848.eml, amadeus/it-1749850.eml, amadeus/it-1779667.eml, amadeus/it-1780323.eml, amadeus/it-1782476.eml, amadeus/it-1782477.eml, amadeus/it-1782695.eml, amadeus/it-1798606.eml, amadeus/it-1810484.eml, amadeus/it-1810487.eml, amadeus/it-1810488.eml, amadeus/it-1810491.eml, amadeus/it-1816237.eml, amadeus/it-1894536.eml, amadeus/it-1894537.eml, amadeus/it-1894543.eml, amadeus/it-1894621.eml, amadeus/it-1903601.eml, amadeus/it-1912392.eml, amadeus/it-1929504.eml, amadeus/it-1986916.eml, amadeus/it-1987881.eml, amadeus/it-2010318.eml, amadeus/it-2012402.eml, amadeus/it-2019074.eml, amadeus/it-2019089.eml, amadeus/it-2048620.eml, amadeus/it-222.eml, amadeus/it-2293930.eml, amadeus/it-2293992.eml, amadeus/it-2382412.eml, amadeus/it-2382415.eml, amadeus/it-2553950.eml, amadeus/it-2651841.eml, amadeus/it-270782964.eml, amadeus/it-2758165.eml, amadeus/it-2769906.eml, amadeus/it-2782249.eml, amadeus/it-29.eml, amadeus/it-3007188.eml, amadeus/it-3138585.eml, amadeus/it-3241010.eml, amadeus/it-333.eml, amadeus/it-4.eml, amadeus/it-4092487.eml, amadeus/it-44151102.eml, amadeus/it-444.eml, amadeus/it-4566446.eml, amadeus/it-4975459.eml, amadeus/it-4980191.eml, amadeus/it-5.eml, amadeus/it-555.eml, amadeus/it-5880630.eml, amadeus/it-6.eml, amadeus/it-666.eml, amadeus/it-6720448.eml, amadeus/it-6721004.eml, amadeus/it-8643103.eml, amadeus/it-9.eml";

    public $reBody = [
        'en' => ['ELECTRONIC TICKET', '/TO'],
        'pl' => ['BILET ELEKTRONICZNY', '/DO'],
        'pt' => ['BILHETE ELETRONICO', '/PARA'],
        'fr' => ['BILLET ELECTRONIQUE', 'VOL '],
        'es' => ['BILLETE ELECTRÓNICO', 'VUELO '],
        'fi' => ['ELEKTRONINEN LIPPU', 'LENTO '],
    ];
    public $lang = '';
    public $date;
    public $emailDate;
    public $text;

    public static $dict = [
        'en' => [
            'startInfo' => 'ELECTRONIC TICKET',
            'endInfo'   => ['NOTICE', 'CONDITIONS OF CONTRACT', 'PASSENGERS ON A JOURNEY INVOLVING', 'Servicing Office', 'Servicingoffice', 'B A G G A G E', 'AT CHECK-IN, PLEASE SHOW A PICTURE'], //'BAGGAGE POLICY'
            'ticketReg' => 'TICKET NUMBER\s*:\s*ETKT',
            'fees1'     => ['TAX', 'TAXES/CARRIER', 'TAXES/FEES/CARRIER', 'TAXES AND AIRLINE', 'СБОР/TAX/FEE/CHARGE'],
            'fees2'     => 'AIRLINE SURCHARGES',
            //            'feesOther' => '',
        ],
        'pl' => [ // it-4566446.eml
            'startInfo'      => 'BILET ELEKTRONICZNY',
            'endInfo'        => ['UWAGA'],
            'ticketReg'      => 'NUMER BILETU\s*:\s*ETKT',
            '/TO '           => '/DO ',
            'DATE'           => 'DATA',
            'NAME'           => 'NAZWISKO',
            'BOOKING REF'    => 'NUMER REZERWACJI',
            'AIRLINE'        => 'LINIA LOTNICZA',
            'ARRIVAL\s*TIME' => 'GODZINA\s+PRZYLOTU',
            //            'ARRIVAL\s*DATE' => '',
            //            'SEAT' => '',
            //            'FLIGHT OPERATED BY' => '',
            'AIR\s+FARE' => 'TARYFA',
            //            'EQUIV\s+FARE\s+PAID' => '',
            'fees1'     => 'PODATEK',
            'fees2'     => 'AIRLINE SURCHARGES',
            'feesOther' => 'OPŁATA',
            'TOTAL'     => 'SUMA OGÓŁEM',
        ],
        'pt' => [
            'startInfo'           => 'BILHETE ELETRONICO',
            'endInfo'             => ['AVISO', 'BAGGAGE POLICY'],
            'ticketReg'           => 'NÚMERO DO BILHETE\s*:\s*ETKT',
            '/TO '                => '/PARA',
            'DATE'                => 'DATA',
            'NAME'                => 'NOME',
            'BOOKING REF'         => 'CODIGO DE RESERVA',
            'AIRLINE'             => 'COMPANHIA AÉREA',
            'ARRIVAL\s*TIME'      => 'HORARIO\s+DE\s+CHEGADA',
            'ARRIVAL\s*DATE'      => 'DATA\s+DE\s+CHEGADA',
            'SEAT'                => 'ASSENTO',
            'FLIGHT OPERATED BY'  => 'VOO OPERADO POR',
            'AIR\s+FARE'          => 'TARIFA\s+A(?:E|É)REA',
            'EQUIV\s+FARE\s+PAID' => 'TARIFA\s+EQUIV\s+PAGA',
            'fees1'               => 'TAXA',
            'fees2'               => ['TAXAS E SOBRETAXAS IMPOSTAS PELA COMPANHIA', 'TAXAS E SOBRETAXAS'],
            //            'feesOther' => '',
            //'TOTAL'=>'',
        ],
        'fr' => [ // it-1779667.eml
            'startInfo'           => 'BILLET ELECTRONIQUE',
            'endInfo'             => ['AVIS', 'NOTICE', '*REMBOURSABLE SANS PENALITE'],
            'ticketReg'           => 'NUMÉRO DE BILLET\s*:\s*ETKT',
            '/TO '                => 'VOL ',
            'DATE'                => 'DATE',
            'NAME'                => 'NOM',
            'BOOKING REF'         => 'REFERENCE DU DOSSIER',
            'AIRLINE'             => 'AIRLINE',
            'ARRIVAL\s*TIME'      => "HEURE D'ARRIV.E",
            'ARRIVAL\s*DATE'      => "DATE D'ARRIV.E",
            'SEAT'                => '(?:SIEGE|SIÈGE)',
            'FLIGHT OPERATED BY'  => 'VOL ASSURÉ PAR',
            'AIR\s+FARE'          => '(?:TARIF\s+AÉRIEN|TARIF\s+AERIEN)',
            'EQUIV\s+FARE\s+PAID' => 'TARIF\s+EQUIV\s+PAYE',
            'fees1'               => 'TAXES',
            'fees2'               => ['SURCHARGES APPLIQUÉES PAR LA COMPAGNIE', 'SURCHARGES'],
            //            'feesOther' => '',
            //'TOTAL'=>'',
        ],
        'es' => [
            'startInfo'      => 'BILLETE ELECTRÓNICO',
            'endInfo'        => ['AVISO', 'NOTICE'],
            'ticketReg'      => 'NUMERO DE BILLETE\s*:\s*ETKT',
            '/TO '           => 'VUELO ',
            'DATE'           => 'FECHA',
            'NAME'           => 'NOMBRE',
            'BOOKING REF'    => 'LOC. RESERVA',
            'AIRLINE'        => 'AIRLINE',
            'ARRIVAL\s*TIME' => 'HORA\s+DE\s+LLEGADA',
            'ARRIVAL\s*DATE' => 'FECHA\s+DE\s+LLEGADA',
            //'SEAT'=>'SIEGE',
            'FLIGHT OPERATED BY'  => 'VUELO OPERADO POR',
            'AIR\s+FARE'          => 'TARIFA\s+AÉREA',
            'EQUIV\s+FARE\s+PAID' => 'TARIFA\s+EQUIV\s+PAGADA',
            'fees1'               => 'TASA',
            'fees2'               => ['RECARGO DE AEROLINEA', 'RECARGO DE'],
            //            'feesOther' => '',
            //'TOTAL'=>'',
        ],
        'fi' => [
            'startInfo'      => 'ELEKTRONINEN LIPPU',
            'endInfo'        => ['VARAUDU TODISTAMAAN', 'HUOMAUTUKSET'],
            'ticketReg'      => 'LIPUN NUMERO\s*:\s*ETKT',
            '/TO '           => 'MIHIN ',
            'DATE'           => 'PVM',
            'NAME'           => 'NIMI',
            'BOOKING REF'    => 'VARAUSTUNNUS',
            'AIRLINE'        => 'LENTOYHTIÖ',
            'ARRIVAL\s*TIME' => 'SAAPUMISAIKA',
            'ARRIVAL\s*DATE' => 'SAAPUMISPAIVA',
            'TERMINAL'       => ['TERMINAL', 'TERMINAALI'],
            //'SEAT'=>'',
            //            'FLIGHT OPERATED BY' => '',
            //            'AIR\s+FARE' => 'TARIFA\s+AÉREA',
            //            'EQUIV\s+FARE\s+PAID' => 'TARIFA\s+EQUIV\s+PAGADA',
            'fees1' => 'MATK. MAKSU',
            'fees2' => 'LENTOYHTIÖN LISÄM',
            //            'feesOther' => '',
            'TOTAL' => 'LENTOHINTA',
        ],
    ];

    private $code = null;
    private static $headers = [
        'amadeus' => [
            'from' => ['amadeus.'],
            'subj' => [
                '#.+?\/.+?\s*(?:MR|MS|MRS|)\s*\d{2}\w{3} [A-Z]{3} [A-Z]{3}#',
                '#YOUR ELECTRONIC TICKET RECEIPT#',
            ],
        ],
        'tapportugal' => [
            'from' => ['@flytap.com', 'ConfirmationTapPortugal@etkt.flytap.com'],
            'subj' => [
                '#.+?\/.+?\s*(?:MR|MS|MRS|)\s*\d{2}\w{3} [A-Z]{3} [A-Z]{3}#',
            ],
        ],
        'aegean' => [
            'from' => ['servicingoffice@aegeanair.com'],
            'subj' => [
                '#ΑΛΛΑΓΗ ΠΤΗΣΗΣ ΝΑΥΑΓΟΣΩΣΤΡΙΑΣ#iu',
                '#METAKINHSH NAVAGOSOSTI#ui',
                '#NAVAGOSOSTIS APO MYTILINI#ui',
                '#metakinisi navagosost#ui',
            ],
        ],
        'asia' => [
            'from' => ['@cathaypacific.com'],
            'subj' => [
                '#YOUR ITINERARY:\s*\d+\w+ [A-Z]{3} [A-Z]{3}\s*-\s*[A-Z\d]{5,6}#ui',
            ],
        ],
        'aireuropa' => [
            'from' => ['@air-europa.com'],
            'subj' => [
                '#comprovante reserva passagem#i',
            ],
        ],
        'eva' => [
            'from' => ['EVA Air'],
            'subj' => [
                '#ELECTRONIC TICKET PASSENGER ITINERARY RECEIPT#i',
            ],
        ],
        'edreams' => [
            'from' => ['@edreams.com'],
            'subj' => [
                '/\[Ticket#\d+\] eDreams/i',
            ],
        ],
    ];

    private $bodies = [
        'asia' => [
            '//img[contains(@src, ".amadeus.net/") and contains(@src, "/urln/CX/CTS/IMAGE")]',
            '//text()[contains(.,\'CATHAY PACIFIC AIRWAYS LTD\')]',
        ],
        'aireuropa' => [
            '//node()[contains(normalize-space(),"PLEASE CONTACT AIR EUROPA") or contains(.,"@air-europa.com") or contains(.,"www.aireuropa.com")]',
        ],
        'eva' => [
            '//node()[contains(normalize-space(),"EVA Air Electronic Ticket") or contains(.,"www.evaair.com") or contains(.,"WWW.EVAAIR.COM") or contains(.,"ESERVICE.EVAAIR.COM")]',
        ],
        'tapportugal' => [
            '//a[contains(@href, "www.flytap.com")]',
            '//node()[contains(.,"www.flytap.com") or contains(.,"@TAP.PT")]',
        ],
        'aegean' => [
            '//img[contains(@alt, "aegean_star_logo")]',
            '//text()[contains(.,\'aegeanair.com\')]',
        ],
        // amadeus last
        'amadeus' => [
            '//a[contains(@href, "www.amadeus.net")]',
        ],
    ];

    public function dateStringToEnglish($date, $lang)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if (($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $lang))) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");
            $this->logger->debug("Parse PDF!");
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');

            if (count($pdfs) > 0) {
                // it-44151102.eml
                $htmlPdf = '';

                foreach ($pdfs as $pdf) {
                    if (($htmlPdf .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                    } else {
                        return $email;
                    }
                }
                $NBSP = chr(194) . chr(160);
                $this->text = str_replace($NBSP, ' ', html_entity_decode($htmlPdf));
                $this->emailDate = strtotime($parser->getDate());

                if (!$this->assignLangPdf()) {
                    $this->logger->debug("Can't determine a language!");

                    return $email;
                }
                $this->parsedData($email, $parser);
                $this->parseEmail($email);

                return $email;
            } else {
                return $email;
            }
        }

        $this->text = $parser->getPlainBody();

        $len = strlen($this->findCutSection($this->text, $this->t("/TO "), "\n"));

        if (!empty($this->text) && count(explode("\n", $this->text)) > 10
            && $this->http->XPath->query("//tr[count(preceding-sibling::tr[normalize-space()]) + count(following-sibling::tr[normalize-space()]) > 10]")->length < 11
            && stripos($this->text, $this->t("/TO ")) !== false && $len > 10 && $len < 150
        ) {
            $this->http->SetEmailBody($this->text);
            $this->logger->debug('used plain body');
        } else {
            $this->text = $parser->getHTMLBody();
            $this->logger->debug('used html body');
        }

        $this->emailDate = strtotime($parser->getDate());

        $this->parsedData($email, $parser);
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(translate(@href,'AMADEUS','amadeus'),'amadeus')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(.),'AMADEUS')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(.),'This document is automatically generated')]")->length > 0
            || $this->http->XPath->query("//*[contains(normalize-space(.),'Маршрутная квитанция')]")->length > 0
        ) {
            if ($this->assignLang()) {
                return true;
            } else {
                $pdfs = $parser->searchAttachmentByName('.*\.pdf');

                if (isset($pdfs) && count($pdfs) > 0) {
                    $htmlPdf = '';

                    foreach ($pdfs as $pdf) {
                        if (($htmlPdf .= \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null) {
                        } else {
                            return null;
                        }
                    }
                    $NBSP = chr(194) . chr(160);
                    $this->text = str_replace($NBSP, ' ', html_entity_decode($htmlPdf));

                    return $this->assignLangPdf();
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

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (preg_match($subj, $headers["subject"])) {
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

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function findCutSection($input, $searchStart, $searchFinish = null)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

        if ($searchFinish === null) {
            return $left;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } elseif (!empty($searchFinish)) {
            $inputResult = mb_strstr($left, $searchFinish, true);
        } else {
            $inputResult = $left;
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
    }

    /**
     * <pre>
     * Example:
     * abc SPLIT text\n text1\n acv SPLIT text\n text1
     * /(SPLIT)/
     * [0] => SPLIT text text1 acv
     * [1] => SPLIT text text1
     * <pre>.
     */
    protected function splitter($regular, $text = null)
    {
        $result = [];

        if ($text === null) {
            $text = $this->text;
        }

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        if (!empty($this->code)) {
            return $this->code;
        }
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->code)) {
            return $this->code;
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        $this->code = $code;

                        break 2;
                    }
                }
            }
        }

        return $this->code;
    }

    private function parseEmail(Email $email): void
    {
        $xpathP = "(self::p or self::pre)";

        if ($this->http->XPath->query("//pre[string-length()>2000 and not(descendant::br)]")->length > 0
            || $this->http->XPath->query("//*[ {$xpathP} and (count(preceding-sibling::*[{$xpathP} and normalize-space()]) + count(following-sibling::*[{$xpathP} and normalize-space()]) > 9) ]")->length > 10
        ) {
            $blockTags = '(?:div|p|pre)';
            $this->text = preg_replace("/(<\/{$blockTags}\b[ ]*>)\s*(<{$blockTags}\b.*?\/?>)/i", "$1\n$2", $this->text);
            $this->text = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $this->text); // only <br> tags
            $text = $this->htmlToText($this->text, false);
        } elseif ($this->http->XPath->query("//tr[count(preceding-sibling::tr[normalize-space()]) + count(following-sibling::tr[normalize-space()]) > 9]")->length > 10) {
            $this->text = preg_replace('/\s+/', ' ', $this->text);
            $this->text = preg_replace("/(<\/tr\b[ ]*>)\s*(<tr\b.*?\/?>)/i", "$1\n$2", $this->text);
            $text = $this->htmlToText($this->text, false);
        } else {
            $text = str_replace("\r", '', $this->text);
        }

        $text = preg_replace('/^([ ]*>)+/m', '', $text); // delete forwarding symbols

        // remove bold highlighting
        $text = implode("\n", array_map(function ($item) {
            if (preg_match('/([ ]*[*][ ]*){3}/', $item) > 0
                || preg_match_all('/[*]/', $item, $asteriskMatches) && (count($asteriskMatches[0]) % 2 !== 0)
            ) {
                return $item;
            }

            return str_replace('*', '', $item);
        }, explode("\n", $text)));

        $info = $this->findCutSection($text, $this->t('startInfo'), $this->t('endInfo'));

        if (empty($info)) {
            $arr = array_filter(array_map("trim", explode(' ', $this->t('startInfo'))));
            $word = array_shift($arr);
            $info = $this->findCutSection($text, $word, $this->t('endInfo'));
        } else {
            $word = $this->t('startInfo');
        }

        $rltext = $this->findCutSection($text, $word, $this->t('/TO '));
        $rl = [];

        if (preg_match_all("/(?:{$this->t('BOOKING REF')}\s+:\s+AMADEUS.*?:\s+([A-Z\d]{5,}).*?{$this->t('AIRLINE')}|\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\/[A-Z\d]{5,}))/u", $rltext, $pnrMatches)) {
            foreach ($pnrMatches as $i => $r) {
                if ($i === 0) {
                    continue;
                }
                $rr = array_values(array_filter($r));

                foreach ($rr as $r2) {
                    $el = preg_split('/\s*\/\s*/', $r2);

                    if (count($el) === 1 && !empty($el[0])) {
                        $rl['AMADEUS'] = $el[0];
                    } elseif (count($el) === 2) {
                        $rl[$el[0]] = $el[1];
                    }
                }
            }
        }

        if (!empty($rl['AMADEUS'])
            && ($email->getProviderCode() === 'amadeus' || $email->getProviderCode() === null)
        ) {
            //$email->ota()->confirmation($rl['AMADEUS']);  //because error Inconsistent locators
            // example with blank ota.confirmation: it-2012402.eml
        }

        $this->date = strtotime($this->normalizeDate($this->re("#\s+{$this->t('DATE')}\s*:\s*(\w{2}\s*\w{3}\s*\d{2,4}|\d{2}\s*\w*?\s*\d{2,4})#ms", $info)));
        $resDate = null;

        if (!empty($this->date)) {
            $resDate = $this->date;
        } else {
            $this->date = $this->emailDate;
        }

        $accNum = $this->re("#\s+FQTV\s*:\s*(.*?)(?:\n|\s{2,}|IATA)#", $info);

        $pos = strpos($info, $this->t('/TO '));

        if ($pos !== false) {
            $flights = substr($info, $pos);
        } else {
            $flights = $info;
        }

        $f = $email->add()->flight();
        $f->general()->noConfirmation();

        if (!empty($resDate)) {
            $f->general()->date($resDate);
        }

//        $pax = beautifulName($pax);
        $pax = $this->re("#[\s/]+{$this->t('NAME')}\s*:\s*(.*?)(?:\n|\s{2,}|FQTV|\()#u", $info);

        $delimiter = '\s+';

        if (in_array($this->code, ['tapportugal', 'aegean'])) {
            $delimiter = '\s*';
        }

        $pax = preg_replace("/{$delimiter}(?:MR|MRS|MSTR|MS|MISS|Г-Н|Г-ЖА)$/iu", '', $pax);
        $pax = preg_replace("/^\s*(.+?)\s*\/\s*(.+?)\s*$/", "$2 $1", $pax);

        if ($this->re("#[\s/]+{$this->t('NAME')}\s*:\s*.*?\((INF)\)#u", $info)) {
            $f->general()->infant($pax, true);
        } else {
            $f->general()->traveller($pax);
        }

        if (!empty($accNum)) {
            $f->program()->account($accNum, false);
        }

        // JOHANNESBURG    SA 377  V  08FEB  2045     VSAOW 1PC  OK
        // МОСКВА            D2 152 Ц 14АВГ 0905 OK YH                         20K
        $nodes = $this->splitter("#(\n+.{4,}[ ]+(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]+\d{1,5}(?:[ ]+[[:upper:]]{1,2})?[ ]*\d{1,2}[ ]*[[:upper:]]{3,9}[ ]+\d+)#u", $flights);
//        $this->logger->debug('$nodes = '.print_r( $nodes,true));
        foreach ($nodes as $n => $root) {
            $s = $f->addSegment();

            /////////////////////////
            //// 1. segmentStart ////
            /////////////////////////

            $patterns['segmentStart'] = "#"
                . "\s*(?<from>.+?)[ ]+(?<flight>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*\d+)(?:[ ]+(?<cl>[[:upper:]]{1,2}))?[ ]+(?<date>\d{2}[ ]*[[:alpha:]]+)[ ]+(?<depTime>\d{1,4}[APM]*)"
                . "[ ]+(.*?)([ ]+\d{2}[[:alpha:]]{3})?([ ]+\d{2}[[:alpha:]]{3})?([ ]+\d+PC)?[ ]*(OK)?(?<secondary>\n[\s\S]*?)?"
                . "(?:\n.+ (?:{$this->t('SEAT')}|{$this->t('ARRIVAL\s*TIME')}|{$this->t('ARRIVAL\s*DATE')})|[ ]{2,}B(?:\n|$))"
                . "#u";

            if (preg_match($patterns['segmentStart'], $root, $m)) {
                if (isset($m[3]) && !empty($m[3])) {
                    $s->extra()->bookingCode($m[3]);
                }
                $s->departure()->name(trim($m[1], " \n\t"));

                if (preg_match("#^\s*(.*?)\s*{$this->opt($this->t('TERMINAL'))}\s*.?\s*(\w+)\b#", $s->getDepName(), $v)) {
                    if (!empty($v[1])) {
                        $s->departure()->name(trim($v[1]));
                    }

                    if (isset($v[2]) && !empty($v[2])) {
                        $s->departure()->terminal($v[2]);
                    }
                }
                $s->departure()->noCode();
                /* disabled
                if (preg_match("#\s*(.+?)(?:\s+([A-Z]{3}))?$#", $m[1], $v)) {
                    $seg['DepName'] = $v[1];
                    if (isset($v[2]) && !empty($v[2]))
                        $seg['DepCode'] = $v[2];
                    else $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                }
                */
                if (preg_match("/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/", $m[2], $flightFields)) {
                    $s->airline()
                        ->name($flightFields[1])
                        ->number($flightFields[2]);
                }

                if (!empty($s->getAirlineName()) && !empty($rl[$s->getAirlineName()])) {
                    $s->airline()->confirmation($rl[$s->getAirlineName()]);
                }

                $s->departure()->date2($this->normalizeDate($m[4] . ', ' . $m[5]));

                if (!empty($m['secondary'])) {
                    if (preg_match("#^\s*([\s\S]*?)\s*(?:{$this->opt($this->t('TERMINAL'))}|{$this->t('FLIGHT OPERATED BY')}|$)#",
                        $m['secondary'], $v)) {
                        $v[1] = trim(preg_replace("/^\ *OK( {3,}.*)?(?:\n|$)/", '', $v[1]));

                        if (!empty($v[1])) {
                            $depNameSecondary = trim(preg_replace('/[ ]{3,}B$/', '', $v[1]));
                            $depNameSecondary = preg_replace("/\s+/", ' ', $depNameSecondary);
                            $s->departure()->name(empty($s->getDepName()) ? $depNameSecondary : $s->getDepName() . ' ' . $depNameSecondary);
                        }
                    }

                    if (preg_match("#{$this->opt($this->t('TERMINAL'))}\s*:\s*(\w+)\s*(?: {3,}|{$this->t('FLIGHT OPERATED BY')}|\n|$)#", $m['secondary'], $v)) {
                        $s->departure()->terminal($v[1]);
                    }

                    if (preg_match("#\s*{$this->t('FLIGHT OPERATED BY')}\s*:\s*(\S.+?)( {3,}|\n|$)#", $m['secondary'], $v)) {
                        if (strpos($v[1], 'MARKETED BY:') !== false) {
                            $v[1] = $this->re("/{$this->opt($this->t('MARKETED BY:'))}\s*(.+)/", $v[1]);
                        }
                        $s->airline()->operator($v[1]);
                    }
                }
            }

            ///////////////////////
            //// 2. segmentEnd ////
            ///////////////////////

            $segEndRe = "#\n([ ]*.{3,} +(?:{$this->t('SEAT')}|{$this->t('ARRIVAL\s*TIME')}|{$this->t('ARRIVAL\s*DATE')})[\s\S]+)#u";

            if (preg_match($segEndRe, $root, $m)) {
                $segText = $m[1];
                $name = preg_replace("/^(.{15,}?)( {3,}.+).*$/m", "$1", $segText);
                $name = preg_replace("/^( {0,5}\W.*?)\s+({$this->t('SEAT')}|{$this->t('ARRIVAL\s*TIME')}|{$this->t('ARRIVAL\s*DATE')}).*$/m", "$1", $name);

                if (preg_match("/^(?<name>[\s\S]*?)(?:\s+{$this->opt($this->t('TERMINAL'))}\s*:\s*(?<terminal>\w+))/", $name, $v)) {
                    $s->arrival()
                        ->noCode()
                        ->name(preg_replace("/\s+/", ' ', trim($v['name'])))
                        ->terminal($v['terminal']);
                } elseif (preg_match("/^(?<name>[\s\S]*?)(?:\n\s*\n|\n.*CHECK-IN|$)/", $name, $v)) {
                    $s->arrival()
                        ->noCode()
                        ->name(preg_replace("/\s+/", ' ', trim($v['name'])));
                }

                $time = null;

                if (preg_match("/{$this->t('ARRIVAL\s*TIME')} *: *(?<time>\d{4}(?: ?[AP]M?)?)(?:\s+|$)/", $segText, $v)) {
                    $time = $v[1];
                } elseif (preg_match("/{$this->t('ARRIVAL\s*TIME')} *: *(?:\D.*)?\n+ .+ {3,}(?<time>\d{4}(?: ?[AP]M?)?)(?:\s+|$)/u", $segText, $v)) {
                    $time = $v[1];
                }

                $arrDate = null;

                if (preg_match("/{$this->t('ARRIVAL\s*DATE')} *: *(?<date>\d{2}[[:alpha:]]{3})\s+/", $segText, $v)) {
                    $arrDate = $v[1];
                } elseif (preg_match("/{$this->t('ARRIVAL\s*DATE')} *: *(?:\D.*)?\n .+ {3,}(?<date>\d{2}[[:alpha:]]{3})\s+/u", $segText, $v)) {
                    $arrDate = $v[1];
                }

                if (preg_match("/{$this->t('SEAT')}[ ]*:[ ]*(?<seat>\d+[A-Z])\s+/u", $segText, $v)) {
                    $s->extra()
                        ->seat($v[1]);
                }

                if (!empty($time) && !empty($arrDate)) {
                    $s->arrival()->date2($this->normalizeDate($arrDate . ', ' . $time));
                } elseif (!empty($time) && !empty($s->getDepDate())) {
                    $s->arrival()->date(strtotime($this->normalizeDate($time), $s->getDepDate()));
                } else {
                    $s->arrival()->noDate();
                }
            }

            if (!empty($s->getDepDate()) && $s->getDepDate() < $this->date) {
                $s->departure()->date(strtotime('+1 year', $s->getDepDate()));
            }

            if (!empty($s->getArrDate()) && $s->getArrDate() < $this->date) {
                $s->arrival()->date(strtotime('+1 year', $s->getArrDate()));
            }

            if (empty($s->getArrDate()) && empty($s->getNoArrDate())) {
                $s->arrival()
                    ->noDate()
                    ->noCode();
            }
        }

        $ticket = $this->re("/{$this->t('ticketReg')}([\d\s\-]+)/", $text);

        if (!empty($ticket)) {
            $f->issued()->tickets([preg_replace('/\s/', '', $ticket)], false);
        }

        if (($val = $this->re("#\s*{$this->t('TOTAL')}\s*:\s*([A-Z]{3}\s*[\d.]+)#", $text))
            || ($val = $this->re("#\s*{$this->t('TOTAL')}\s*:\s*([\d.]+[A-Z]{3})#", $text))
        ) {
            $tot = $this->getTotalCurrency($val);

            if ($tot['Total'] !== null) {
                $f->price()
                    ->total($tot['Total'])
                    ->currency($tot['Currency']);
            }
        }
        $tot = $this->getTotalCurrency($this->re("#\s*{$this->t('AIR\s+FARE')}\s*:\s*(\w{3}\s*[\d.]+)#", $text));

        if ($tot['Total'] !== null) {
            $f->price()->cost($tot['Total']);

            if (!empty($f->getPrice())
                && $f->getPrice()->getCurrencyCode() !== $tot['Currency']
                && $f->getPrice()->getCurrencySign() !== $tot['Currency']
            ) {
                $tot = $this->getTotalCurrency($this->re("#\s*{$this->t('EQUIV\s+FARE\s+PAID')}\s*:\s*(\w{3}\s*[\d.]+)#",
                    $text));

                if ($tot['Total'] !== null) {
                    $f->price()
                        ->cost($tot['Total'])
                        ->currency($tot['Currency']);
                }
            } else {
                $f->price()->currency($tot['Currency']);
            }
        }

        /*
            TAXES    : NGN     31104YQ   NGN     648YQ     NGN     41644XT    21474SC
        */

        // OR

        /*
            TAXES    : ZZ370RUB       XT5600RUB
        */

        // OR

        /*
            TAXES    : EUR PD     28.45QX   EUR PD     45.07IZ   EUR PD     7.85FR
                       EUR PD     12.75FR   EUR PD     15.03US   EUR PD     4.76AY
                       EUR PD     3.82XF
        */
        /*
            TAXES AND AIRLINE   : BRL     26.60J9   BRL     46.16PT   BRL     117.28YP
         */
        $feesFull = $this->re("#^[ ]*{$this->opt($this->t('fees1'))}[ ]*[:]+[ ]*((?:[^:\n]*\d.*\n+)+)#m", $text)
            . "\n" . $this->re("#^[ ]*{$this->opt($this->t('fees2'))}[ ]*[:]+[ ]*((?:[^:\n]*\d.*\n+)+)#m", $text);

        if ((preg_match_all("/\b(?<value>(?:(?<currency>[A-Z]{3})(?: [A-Z]{2})?[ ]+)?\d[,.\'\d]*)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\b/", $feesFull, $matches)
                || preg_match_all("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])(?<value>\d[,.\'\d]*(?<currency>[A-Z]{3})?)\b/", $feesFull, $matches)
            )
            && count(array_unique(array_filter($matches['currency']))) === 1
            && (empty($f->getPrice())
                || !empty($f->getPrice()) && empty($f->getPrice()->getCurrencyCode()) && empty($f->getPrice()->getCurrencySign())
                || !empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === array_values(array_filter($matches['currency']))[0]
            )
        ) {
            foreach ($matches[0] as $key => $value) {
                $tot = $this->getTotalCurrency($matches['value'][$key]);

                if ($tot['Total'] !== null) {
                    $f->price()->fee($matches['name'][$key], $tot['Total']);
                }
            }

            if (empty($f->getPrice())
                || !empty($f->getPrice()) && empty($f->getPrice()->getCurrencyCode()) && empty($f->getPrice()->getCurrencySign())
            ) {
                $f->price()->currency(array_values(array_filter($matches['currency']))[0]);
            }
        }

        if (preg_match_all("#^[ ]*({$this->opt($this->t('feesOther'))}[ ]*[:]+[ ]*(?:[^:\n]*\d.*\n+)+)#m", $text, $feesOther)) {
            /*
                OPŁATA    : PLN     64.80       OBT02 PL CC
            */
            foreach ($feesOther[1] as $field) {
                if (preg_match("/^(?<name1>[^:\n]+?)[ ]*[:]+[ ]*(?<value>[A-Z]{3}[ ]*\d[,.\'\d]*)(?:[ ]{2}.+)?(?:\n+[ ]*(?<name2>[\s\S]+))?$/", rtrim($field), $m)) {
                    $tot = $this->getTotalCurrency($m['value']);

                    if ($tot['Total'] !== null
                          && (empty($f->getPrice())
                              || !empty($f->getPrice()) && empty($f->getPrice()->getCurrencyCode()) && empty($f->getPrice()->getCurrencySign())
                              || !empty($f->getPrice()) && $f->getPrice()->getCurrencyCode() === $tot['Currency']
                          )
                      ) {
                        $feeName = empty($m['name2']) ? $m['name1'] // single-line
                              : $m['name1'] . ' ' . preg_replace('/\s+/', ' ', $m['name2']); // multi-line
                          $f->price()->fee($feeName, $tot['Total']);
                    }

                    if ($tot['Currency'] !== null
                          && (empty($f->getPrice()) || !empty($f->getPrice()) && empty($f->getPrice()->getCurrencyCode()) && empty($f->getPrice()->getCurrencySign()))
                      ) {
                        $f->price()->currency($tot['Currency']);
                    }
                }
            }
        }
    }

    private function normalizeDate($date)
    {
        $year = date('Y', $this->date);
        $in = [
            //30JUN 0720A
            '#^\s*(\d+)\s*(\w+),\s+(\d{2})(\d{2})\s*([AP])\s*$#u',
            '#^\s*(\d+)\s*(\w+),\s+(\d{2})(\d{2})\s*$#u',
            //0720A
            '#^(\d{2})(\d{2})\s*([AP])$#',
            // 14АВГ, 0905
            '#^(\d)([А-Я]+), (\d{4})$#',
        ];
        $out = [
            '$1 $2 ' . $year . ' ' . '$3:$4 $5M',
            '$1 $2 ' . $year . ' ' . '$3:$4',
            '$1:$2 $3M',
            "$1 $2 {$year}, $3",
        ];
        // 14АВГ, 0905
        if (preg_match('#[А-Я]+#', $date)) {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date), 'ru');
        } else {
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date), $this->lang);
        }

        return $str;
    }

    private function parsedData(Email $email, \PlancakeEmailParser $parser): void
    {
        if (($code = $this->getProvider($parser))) {
            $email->setProviderCode($code);
        }
        $email->setType('ItineraryReceipt' . ucfirst($this->lang));
    }

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[0]}')]")->length > 0) && ($this->http->XPath->query("//*[contains(normalize-space(.),'{$reBody[1]}')]")->length > 0)) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangPdf(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($this->text, $reBody[0]) !== false && stripos($this->text, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[.\d,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[.\d,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>-*?)(?<t>\d[.\d,\s]*\d*)#", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse($m['t'], $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str = null, $c = 1)
    {
        if ($str === null) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
