<?php

namespace AwardWallet\Engine\ctrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItineraryFlight2023 extends \TAccountChecker
{
    public $mailFiles = "ctrip/it-638650809.eml, ctrip/it-628947120-pt.eml, ctrip/it-702784250-it.eml, ctrip/it-704937866-zh.eml, ctrip/it-714299772-de.eml";

    public $lang = '';

    public static $dictionary = [
        'zh' => [
            'otaConfNumber'             => ['訂單編號：'],
            'Airline Booking Reference' => ['航空公司預訂參考編號', '預訂參考編號'],
            'statusPhrases'             => ['您的機票訂單已'],
            'statusVariants'            => ['確認'],
            // 'and' => '',
            'Given names'               => ['名字', '名'],
            'Surname'                   => ['姓氏', '姓'],
            'Request Update'            => '申請更新',
            'Ticket Number'             => '機票編號',
            'Total'                     => ['總計', '總額'],
        ],
        'de' => [
            'otaConfNumber'             => ['Buchungsnr.'],
            'Airline Booking Reference' => ['Referenzcode der Fluggesellschaft'],
            'statusPhrases'             => ['Die folgende Reiseroute wurde', 'Ihre Flugbuchung wurde'],
            'statusVariants'            => ['bestätigt'],
            'and'                       => 'und',
            'Given names'               => 'Vorname(n)',
            'Surname'                   => 'Nachname',
            'Request Update'            => 'Ändern',
            'Ticket Number'             => 'Ticketnummer',
            'Total'                     => 'Gesamt',
        ],
        'it' => [
            'otaConfNumber'             => ['Prenotazione n.'],
            'Airline Booking Reference' => ['Codice di prenotazione della compagnia aerea'],
            'statusPhrases'             => ['Il seguente itinerario è stato', 'La tua prenotazione è stata'],
            'statusVariants'            => ['confermato', 'confermata'],
            'and'                       => 'e',
            'Given names'               => 'Nome(i) di battesimo',
            'Surname'                   => 'Cognome',
            'Request Update'            => 'Richiedi modifica',
            'Ticket Number'             => 'Numero del biglietto',
            'Total'                     => 'Totale',
        ],
        'pt' => [
            'otaConfNumber'             => ['N.º da reserva'],
            'Airline Booking Reference' => ['Referência de reserva da companhia aérea/Localizador'],
            'statusPhrases'             => ['Sua reserva de voo foi'],
            'statusVariants'            => ['confirmada'],
            // 'and' => '',
            'Given names'               => 'Nome e nome do meio',
            'Surname'                   => 'Sobrenome',
            // 'Request Update' => '',
            'Ticket Number'             => 'Número da passagem',
            // 'Total' => '',
        ],
        'en' => [
            'otaConfNumber'             => ['Booking No.'],
            'Airline Booking Reference' => ['Airline Booking Reference'],
            'statusPhrases'             => ['Your flight booking has been'],
            'statusVariants'            => ['confirmed'],
            // 'and' => '',
            // 'Given names' => '',
            // 'Surname' => '',
            // 'Request Update' => '',
            // 'Ticket Number' => '',
            // 'Total' => '',
        ],
    ];

    private $subjects = [
        'zh' => ['機票訂單確認郵件'],
        'de' => ['Flugbuchungsbestätigung'],
        'it' => ['Prenotazione del volo confermata'],
        'pt' => ['Confirmação de reserva de voo'],
        'en' => ['Flight Booking Confirmed'],
    ];

    private $patterns = [
        'date'          => '(?:\b.{4,20}?\b\d{4}\b|\b\d{4}\s*年\s*\d{1,2}\s*月\s*\d{1,2}\s*日)', // Feb 7, 2024    |    7. Feb. 2024    |    2024 年 7 月 31 日
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]trip\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".trip.com/") or contains(@href,".trip.com%2F") or contains(@href,"www.trip.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Thanks for choosing Trip.com")] | //text()[starts-with(normalize-space(),"Copyright ©") and contains(.,"Trip.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ItineraryFlight2023' . ucfirst($this->lang));

        $xpathDigits = "contains(translate(.,'0123456789','∆∆∆∆∆∆∆∆∆∆'),'∆')";
        $xpathTime = 'contains(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆")';

        $otaConfirmations = array_values(array_unique(array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('otaConfNumber'))}]/following::text()[normalize-space()][1]", null, '/^[A-Z\d]{5,}$/'))));

        if (count($otaConfirmations) === 1) {
            $otaConfirmationTitle = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('otaConfNumber'))}][last()]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmations[0], $otaConfirmationTitle);
        }

        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]*({$this->opt($this->t('statusVariants'))})(?:\s*[，,.;:!?]|\s+{$this->opt($this->t('and'))}\s|$)/iu"));

        if (count(array_unique(array_map('mb_strtolower', $statusTexts))) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        }

        $routes = [];

        $xpathSegPoint = "(count(*[normalize-space()])=2 and *[1][{$xpathTime}] and *[5][normalize-space()])";
        $xpathSegment = "{$xpathSegPoint} and following-sibling::tr[normalize-space()][1][count(*[normalize-space()])=1 and *[5][normalize-space()]] and following-sibling::tr[normalize-space()][2][{$xpathSegPoint} or starts-with(normalize-space(),'➤')]";
        $xpathRouteHeader = "{$xpathDigits} and contains(.,'|') and contains(.,'-') and string-length(normalize-space())>6 and following-sibling::tr[{$xpathSegment}]";

        foreach ($this->http->XPath->query("//tr[{$xpathRouteHeader}]") as $routeNode) {
            $routeDB = [];
            $routeDB['headerNode'] = $routeNode;

            $sgmts = [];
            $followingRows = $this->http->XPath->query("following-sibling::tr[normalize-space()]", $routeNode);

            foreach ($followingRows as $row) {
                if ($this->http->XPath->query("self::tr[{$xpathRouteHeader}]", $row)->length > 0) {
                    break;
                }

                if ($this->http->XPath->query("self::tr[{$xpathSegment}]", $row)->length > 0) {
                    $sgmts[] = $row;
                }
            }

            $routeDB['segmentsNodes'] = $sgmts;
            $routes[] = $routeDB;
        }

        $segConfNoStatuses = [];

        foreach ($routes as $routeDB) {
            $routePoints = [];
            $headerText = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $routeDB['headerNode']));

            if (preg_match("/^{$this->patterns['date']}\s*\|\s*([^\|]{5,})$/u", $headerText, $m)) {
                $routePoints = preg_split('/\s+-\s+/', $m[1]);
            }

            foreach ($routeDB['segmentsNodes'] as $i => $root) {
                $s = $f->addSegment();

                $dateDep = $dateDepShort = null;
                $preRoots = $this->http->XPath->query("preceding-sibling::tr[normalize-space()][1]", $root);
                $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;

                while ($preRoot) {
                    $dateVal = $this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $preRoot);

                    if (preg_match("/^({$this->patterns['date']})(?:\s*\||$)/u", $dateVal, $matches)) {
                        $matches = array_map('trim', $matches);
                        $dateDep = strtotime($this->normalizeDate($matches[1]));

                        if (preg_match('/^\d{4}\s*年\s*(\d{1,2})\s*(月)\s*(\d{1,2})\s*(日)$/', $matches[1], $m)) {
                            // 2024 年 7 月 31 日  ->  7月31日
                            $dateDepShort = $m[1] . $m[2] . $m[3] . $m[4];
                        } elseif (preg_match('/^([[:alpha:]]{1,3})[[:alpha:]]*\s+(\d{1,2})(?:\s*,\s*\d{2,4})?$/u', $matches[1], $m)
                            || preg_match('/^(\d{1,2})\s+(?:de\s+)?([[:alpha:]]{1,3})[[:alpha:]]*(?:(?:\s+de)?\s+\d{2,4})?$/iu', $matches[1], $m)
                        ) {
                            // January 29, 2024  ->  Jan 29
                            // 4 de janeiro de 2024  ->  4 jan
                            $dateDepShort = $m[1] . ' ' . $m[2];
                        } elseif ($this->lang === 'de' && preg_match('/^(\d{1,2})\s*\.\s*(Juni)(?:\s+\d{2,4})?$/iu', $matches[1], $m)) {
                            // 27. Juni 2025  ->  27. Juni
                            $dateDepShort = $m[1] . '. ' . $m[2];
                        } elseif ($this->lang === 'de' && preg_match('/^(\d{1,2})\s*\.\s*(Febr|Sept|[[:alpha:]]{1,3})[[:alpha:]]*(?:[.\s]+\d{2,4})?$/iu', $matches[1], $m)) {
                            // 1. Februar 2025  ->  1. Febr.
                            // 7. Dez. 2024  ->  7. Dez.
                            $dateDepShort = $m[1] . '. ' . $m[2] . '.';
                        }

                        break;
                    }

                    $preRoots = $this->http->XPath->query("preceding-sibling::tr[normalize-space()][1]", $preRoot);
                    $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;
                }

                $timeDep = $this->http->FindSingleNode("*[1]", $root, true, "/^{$this->patterns['time']}/");

                if ($dateDep && $timeDep) {
                    $s->departure()->date(strtotime($timeDep, $dateDep));
                }

                $airportDep = $this->http->FindSingleNode("*[5]", $root);

                if (preg_match($pattern = "/^(?<name>.{2,}?)\s+T[-\s]*(?<terminal>[A-Z\d]|\d+[A-Z]?)$/", $airportDep, $m)) {
                    // Parigi Charles de Gaulle T2D
                    $s->departure()->name($m['name'])->terminal($m['terminal']);
                } else {
                    $s->departure()->name($airportDep);
                }

                if ($airportDep) {
                    $s->departure()->noCode();
                }

                $flightText = implode("\n", $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/(?:^|\s)(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<number>\d+)(?:\s*•|$)/", $flightText, $m)) {
                    $s->airline()->name($m['name'])->number($m['number']);
                }

                if (preg_match("/•[ ]*(\d[.std小時分鐘hora mins\d]+)$/imu", $flightText, $m)) {
                    $s->extra()->duration($m[1]);
                }

                $dateArr = $timeArr = null;
                $timeArrVal = implode(' ', $this->http->FindNodes("following-sibling::tr[normalize-space() and not(starts-with(normalize-space(),'➤'))][2]/*[1]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<date>{$this->patterns['date']})\s+(?<time>{$this->patterns['time']})/u", $timeArrVal, $m)) {
                    $dateArr = strtotime($this->normalizeDate($m['date']));
                    $timeArr = $m['time'];
                } elseif (preg_match("/^{$this->patterns['time']}/u", $timeArrVal, $m)) {
                    $dateArr = $dateDep;
                    $timeArr = $m[0];
                }

                if ($dateArr && $timeArr) {
                    $s->arrival()->date(strtotime($timeArr, $dateArr));
                }

                $airportArr = $this->http->FindSingleNode("following-sibling::tr[normalize-space() and not(starts-with(normalize-space(),'➤'))][2]/*[5]", $root);

                if (preg_match($pattern, $airportArr, $m)) {
                    $s->arrival()->name($m['name'])->terminal($m['terminal']);
                } else {
                    $s->arrival()->name($airportArr);
                }

                if ($airportArr) {
                    $s->arrival()->noCode();
                }

                $segRoute = array_key_exists($i, $routePoints) && array_key_exists($i + 1, $routePoints)
                ? $routePoints[$i] . ' - ' . $routePoints[$i + 1] : null;

                if (!$segRoute || !$dateDepShort) {
                    $this->logger->debug('Fields from segment header not found!');
                    $f->addSegment(); // for 100% fail

                    continue;
                }

                $bookingReferenceHeader = $segRoute . ' • ' . $dateDepShort;
                $xpathBookingReference = "count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Airline Booking Reference'))}]";

                $bookingReferences = array_merge(
                    array_filter($this->http->FindNodes("//tr[ *[1][{$this->eq($bookingReferenceHeader)}] ]/following-sibling::tr[normalize-space()][1]/*[1]/descendant-or-self::*[{$xpathBookingReference}]/*[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/')),
                    array_filter($this->http->FindNodes("//tr[ *[3][{$this->eq($bookingReferenceHeader)}] ]/following-sibling::tr[normalize-space()][1]/*[3]/descendant-or-self::*[{$xpathBookingReference}]/*[normalize-space()][2]", null, '/^[A-Z\d]{5,}$/'))
                );

                if (count(array_unique($bookingReferences)) === 1) {
                    $bookingReference = array_shift($bookingReferences);
                    $s->airline()->confirmation($bookingReference);
                    $segConfNoStatuses[] = true;
                } elseif (count(array_unique($bookingReferences)) > 1) {
                    $segConfNoStatuses[] = false;
                }
            }
        }

        if (count(array_unique($segConfNoStatuses)) === 1 && $segConfNoStatuses[0] === true) {
            $f->general()->noConfirmation();
        }

        $travellers = [];
        $passengerNameRecords = $this->http->FindNodes("//tr[not(.//tr[normalize-space()])][{$this->contains($this->t('Given names'))} or {$this->contains($this->t('Surname'))}]");

        foreach ($passengerNameRecords as $record) {
            $passengerName = $this->parsePassengerName($record);

            if ($passengerName) {
                $travellers[] = $passengerName;
            }
        }

        $tickets = [];
        $ticketRows = $this->http->XPath->query("//*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Ticket Number'))}] ]");

        foreach ($ticketRows as $tktRow) {
            $passengerText = $this->http->FindSingleNode("ancestor::tr[ preceding-sibling::tr[normalize-space()] ][1]/preceding-sibling::tr[normalize-space()][{$this->contains($this->t('Given names'))} or {$this->contains($this->t('Surname'))}][1]", $tktRow);
            $passengerName = $this->parsePassengerName($passengerText);
            $ticket = $this->http->FindSingleNode("*[normalize-space()][2]", $tktRow, true, "/^{$this->patterns['eTicket']}$/");

            if ($ticket && !in_array($ticket, $tickets)) {
                $f->issued()->ticket($ticket, false, $passengerName);
                $tickets[] = $ticket;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, true);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+?)$/u', $totalPrice, $matches)
        ) {
            // $ 1,471.80    |    R$ 3.371,25    |    62,12 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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

    private function parsePassengerName(?string $s): ?string
    {
        $passengerName = null;

        $s = preg_replace([
            "/[\s(]*{$this->opt($this->t('Request Update'))}[)\s]*$/i",
        ], '', $s);

        if (preg_match("/^(?<name>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Given names'))}[)\s]*(?<surname>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Surname'))}[)\s]*$/iu", $s, $m)
            || preg_match("/^(?<surname>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Surname'))}[)\s]*(?<name>{$this->patterns['travellerName']})[\s(]*{$this->opt($this->t('Given names'))}[)\s]*$/iu", $s, $m)
        ) {
            $passengerName = $m['name'] . ' ' . $m['surname'];
        }

        return $passengerName;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['otaConfNumber']) || empty($phrases['Airline Booking Reference'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['otaConfNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Airline Booking Reference'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u', $text, $m)) {
            // Feb 13, 2024
            $month = $m[1];
            $day = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{1,2})[.\s]+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?[.\s]+(\d{4})$/u', $text, $m)) {
            // 4 jan 2024    |    4 de janeiro de 2024    |    8. August 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^\b(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日$/', $text, $m)) {
            // 2024 年 7 月 31 日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'HKD' => ['HK$'],
            'TWD' => ['NT$'],
        ];

        foreach ($currencies as $currencyCode => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $currencyCode;
                }
            }
        }

        return $string;
    }
}
