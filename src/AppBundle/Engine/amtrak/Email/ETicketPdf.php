<?php

namespace AwardWallet\Engine\amtrak\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ETicketPdf extends \TAccountChecker
{
    public $mailFiles = "amtrak/it-1.eml, amtrak/it-127325405.eml, amtrak/it-128077794.eml, amtrak/it-128724610.eml, amtrak/it-153231251.eml, amtrak/it-1584686.eml, amtrak/it-1651938.eml, amtrak/it-180964021.eml, amtrak/it-183550626.eml, amtrak/it-184094205.eml, amtrak/it-184374869.eml, amtrak/it-184427109.eml, amtrak/it-184903296.eml, amtrak/it-2277689.eml, amtrak/it-6222194.eml, amtrak/it-8871695.eml, amtrak/it-99369061.eml, amtrak/it-575652195.eml, amtrak/it-582235382.eml, amtrak/it-582834956-junk.eml";

    public $reSubject = [
        'en' => ['Amtrak: eTicket for Your Upcoming Trip', 'Amtrak: eTicket and Receipt for'],
    ];

    public $langDetectors = [
        'en' => ['eTicket'],
    ];

    public static $dictionary = [
        'en' => [],
    ];

    public $lang = '';

    /** @var \HttpBrowser */
    // private $pdf;

    private $patterns = [
        'date'          => '[[:alpha:]]{3} \d{1,2}, \d{4}', // Nov 3, 2023
        'time'          => '\d{1,2}[:：]\d{2}[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?', // 5:08 PM
        'travellerName' => '[[:alpha:]][-,.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    public function parsePdf(Email $email, $text, &$pdfJunkStatuses): void
    {
//        $this->logger->debug('$text = '.print_r( $text,true));

        if (preg_match('/^[ ]*RES# ([A-Z\d]{5,})-(\d+[A-Z]{3}\d+)/m', $text, $m)) {
            $confirmation = $m[1];
            $resDate = strtotime($m[2]);
        } else {
            $confirmation = $this->re("/ RESERVATION NUMBER[ ]+([A-Z\d]{5,})$/m", $text);
        }

        // remove footers
        $text = preg_replace("/\n[ ]*RES ?#[ ]*[-A-Z\d]+ .*(?i)Travel Date ?[:]+.*\n/", "\n", $text);

        $segmentsText = $this->re("/\n([ ]*(?:TRAIN|BUS|FERRY) .+?)\n+[ ]*PASSENGERS\b/s", $text);

        if (!$segmentsText && preg_match("/ RESERVATION NUMBER(?:[ ]+[A-Z\d]{5,})?\n+(.+?)\n+[ ]*PASSENGERS\b/s", $text, $m)
            && !preg_match("/{$this->patterns['time']}/u", $m[1]) && preg_match("/\bMulti-ride Ticket/i", $m[1]) > 0
        ) {
            // it-582834956-junk.eml
            $this->logger->debug('PDF-document is junk!');
            $pdfJunkStatuses[] = true;

            return;
        } else {
            $pdfJunkStatuses[] = false;
        }

        $ticket = $this->http->FindSingleNode("//text()[contains(normalize-space(),'Summary - Ticket Number')]", null, true, "/Summary - Ticket Number\s+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})$/");

        $payment =
            $this->http->FindSingleNode("//text()[normalize-space()='Total Charged by Amtrak']/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//text()[normalize-space()='Revised Fare']/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
            ?? $this->http->FindSingleNode("//*[ descendant::text()[normalize-space()][1][normalize-space()='Subtotal'] ]/following-sibling::*[normalize-space()][position()<3]/descendant::text()[normalize-space()][1][normalize-space()='Total']/following::text()[normalize-space()][1]", null, true, "/^.*\d.*$/")
        ;

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $payment, $matches)) {
            // $332.00
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $email->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $this->patterns['travellerType'] = '(?:ADULT-REDUCED MOBILITY|RAIL PASSENGERS ASSOCI|SPECIAL ITEM TICKET|AMTRAK PASS TRAVEL|PWD ADULT|PASS TRAVEL - ADULT|MILITARY ADULT|MILITARY VETERAN|H126 ADULT|STUDENT ADVANTAGE|SENIOR|CHILD|PWD ADULT|PWD COMPANION|ADULT)';

        $passengers = array_filter(preg_split('/\s*,\s*/', $this->http->FindSingleNode("//text()[normalize-space() = 'Passengers']/following::text()[normalize-space()][1][following::text()[normalize-space()][1][normalize-space() = 'Important Information']]")));

        if (empty($passengers)) {
            $passengers = preg_match_all("/\n[ ]*({$this->patterns['travellerName']})(?: {2,}(\S ?)* | ?,?[ ]+){$this->patterns['travellerType']} /u",
                $text, $passengerMatches) ? $passengerMatches[1] : [];
        }

        if (empty($passengers)) {
            $passengers = preg_match_all("/\n[ ]*({$this->patterns['travellerName']}) ?,?{$this->patterns['travellerType']} /u", $text, $passengerMatches) ? $passengerMatches[1] : [];
        }

        if (empty($passengers)) {
            $passengers = preg_match_all("/\n[ ]*({$this->patterns['travellerName']}) ?,?\s+{$this->patterns['travellerType']} /u", $text, $passengerMatches) ? $passengerMatches[1] : [];

            foreach ($passengers as $p) {
                if (preg_match("/ {2}/", $p)) {
                    $passengers = [];

                    break;
                }
            }
        }

        $passengers = preg_replace("/^\s*(\S.{10,}) {2,}.+/", '$1', $passengers);
        $passengers = preg_replace("/^\s*(.+?)\s*,\s*(.+?)\s*$/", '$2 $1', $passengers);
        $passengers = array_map('ucwords', array_map('strtolower', $passengers));
        $accounts = [];

        if (preg_match_all("/^(.+{$this->patterns['travellerType']}.*)[ ]{2}(\d{5,})(?: \||$)/m", $text, $accountMatches)) {
            $passengersText = implode("\n", $passengers);
            $accountMatches[1] = array_map('trim', $accountMatches[1]);

            foreach ($accountMatches[1] as $i => $v) {
                $v = trim($v);

                if (preg_match("/\s{2,}/u", $v)) {
                    $v = preg_replace("/^\s*(.*?)\s{2,}.*/", '$1', $v);
                } else {
                    $v = preg_replace("/^\s*(.*?) {$this->patterns['travellerType']}/", '$1', $v);
                }
                $v = ucwords(strtolower($v));

                $v1 = trim(preg_replace("/^\s*(.+?)\s*,\s*(.+?)\s*$/", '$2 $1', $v));

                if (in_array($v1, $passengers)) {
                    $accounts[$accountMatches[2][$i]] = $v1;
                } elseif (preg_match("/^\s*(.+?)\s*,\s*(.+?)\s*$/", $v, $m1)
                    && (preg_match("/^ *({$m[2]}.* {$m[1]})$/m", $passengersText, $m2))
                ) {
                    $accounts[$accountMatches[2][$i]] = $m2[1];
                } else {
                    $accounts[$accountMatches[2][$i]] = null;
                }
            }
        }

        $segmentsText = preg_replace("/^[ ]*(?:Depart|Return)\n+([ ]*(?:TRAIN|BUS|FERRY) .+)/im", '$1', $segmentsText);
        $segments = $this->splitText($segmentsText, "/^([ ]*(?:TRAIN|BUS|FERRY) (?:.{45,}|.{10,45}\n .{45,})? (?:DEPARTS|ARRIVES(?:[ ]*\(.+)?))$/m", true);
//        $this->logger->debug('$segments = '.print_r( $segments,true));

        foreach ($segments as $key => $sText) {
//            $this->logger->debug('$sText = '.print_r( $sText,true));

            $sText = preg_replace([
                "/^([\s\S]+?)\n{2}[ ]*SELF-TRANSFER\b[\s\S]*$/i",
                "/({$this->patterns['time']}) ?({$this->patterns['time']})/",
            ], [
                '$1',
                '$1                              $2',
            ], $sText);

            $extraText = '';

            if (preg_match("/^((?:.*\n){3,}(?:(?: {20,}.*\s*\n){2,}|\n{3,}))( {0,15}\S.*(?:\n+|$))+\s*$/", $sText, $m)) {
                $sText = $m[1];
                $extraText = $m[2];
            }

            if (preg_match("/(\n[ ]{0,20}Operated by(?: .+?)?)(?: {2}|\n)/", $sText, $m)) {
                $sText = str_replace($m[1], "\n" . str_pad('', strlen($m[1]) - 1), $sText);
                $marginLeft = 2;
            } else {
                $marginLeft = 0;
            }

            $tablePos = [0];

            if (preg_match("/^(.{10,}? ){$this->patterns['date']}(?: |$)/mu", $sText, $matches)) {
                $tablePos[1] = mb_strlen($matches[1]);
            }

            if (preg_match('/^(.{40,}?[ ]{2})To \S.{2,}/m', $sText, $matches)
                || preg_match('/^.{40,}[ ]{2}.{2,}\S To(?:[ ]{2}.+| DEPARTS\b.*| ARRIVES\b.*|$)\n+^(.{40,}?[ ]{2})\S.{2,}/m', $sText, $matches)
            ) {
                // it-153231251.eml
                $tablePos[2] = mb_strlen($matches[1]);
            }

            if (preg_match('/^(.{35,}? )DEPARTS(?:[ ]+ARRIVES|$)/im', $sText, $matches)) {
                $tablePos[3] = mb_strlen($matches[1]) - $marginLeft;
            }

            if (preg_match('/^(.{55,}? )ARRIVES(?:[ ]*\(.+)?$/im', $sText, $matches)) {
                $tablePos[4] = mb_strlen($matches[1]);
            }
            $table = $this->splitCols($sText, $tablePos);

            $tableType = 1;

            if (!array_key_exists(2, $table)) {
                if (!empty($table[1]) && preg_match("/^(.{11,}?  )\S/m", $table[1], $matches)) {
                    // it-1584686.eml
                    $subTable = $this->splitCols($table[1], [0, mb_strlen($matches[1])]);
                    $table[1] = $subTable[0];
                    $table[2] = $subTable[1];
                } else {
                    // it-183550626.eml
                    $tableType = 2;
                    $table[2] = $extraText;
                }
                ksort($table);
            }

            /*
            if (count(array_filter(array_map('trim', $table))) !== 5) {
                if (preg_match("/^(.+(?:\n.+)? DEPARTS\b.*)\n([\s\S]+)/", $sText, $m)) {
                    $headerText = $m[1];
                    $sText = $m[2];

                    $tableM = $this->splitCols($sText, $this->rowColsPos($this->inOneRow($sText)));
                    $tableH = $this->splitCols($headerText, $this->rowColsPos($this->inOneRow($headerText)));

                    if (count($tableM) == 4 && count($tableH) == 4) {
                        $tableType = 2;
                        $table = [
                            0 => $tableH[0] . "\n" . $tableM[0],
                            1 => $tableH[1] . "\n" . $tableM[1],
                            2 => $extraText,
                            3 => $tableH[2] . "\n" . $tableM[2],
                            4 => $tableH[3] . "\n" . $tableM[3],
                        ];
                    }
                }
            }
            */

            $this->logger->debug('Table type: ' . $tableType);

            if (count($table) !== 5) {
                $this->logger->debug('Wrong table segment!');

                return;
            }
//            $this->logger->debug('$table = '.print_r( $table,true));

            if (preg_match("/^[ ]*TRAIN\n/i", $table[0]) > 0) {
                $this->logger->debug("Segment-{$key}: TRAIN");

                if (!isset($train)) {
                    $train = $email->add()->train();
                }
                $s = $train->addSegment();
            } elseif (preg_match("/^[ ]*BUS\n/i", $table[0]) > 0) {
                $this->logger->debug("Segment-{$key}: BUS");

                if (!isset($bus)) {
                    // it-128077794.eml, it-127325405.eml
                    $bus = $email->add()->bus();
                }
                $s = $bus->addSegment();
            } elseif (preg_match("/^[ ]*FERRY\n/i", $table[0]) > 0) {
                $this->logger->debug("Segment-{$key}: FERRY");

                if (!isset($ferry)) {
                    $ferry = $email->add()->ferry();
                }
                $s = $ferry->addSegment();
            } else {
                $this->logger->debug("Segment-{$key}: unknown");
                $email->add()->hotel(); // for 100% fail

                return;
            }

            $flightNumber = $this->re("/^[ ]*(?:TRAIN|BUS|FERRY)\n+[ ]*(\d+)$/m", $table[0]);

            if (stripos($s->getId(), 'ferry') !== false) {
                /** @var \AwardWallet\Schema\Parser\Common\FerrySegment $s */
                $s->extra()->vessel($flightNumber);
            } else {
                $s->extra()->number($flightNumber);
            }

            $serviceName = null;

            $dateDep = $dateArr = 0;

            if (preg_match("/^[ ]*(?<serviceName>[\s\S]{2,})\n+[ ]*(?<date>.*\d.*)\s*(?:Operated by|\n+[ ]*[[:upper:]][- [:upper:]]*[[:upper:]]|$)/", $table[1], $m)) {
                /*
                    MISSOURI RIVER
                    RUNNER

                    Dec 11, 2021
                    SELF TRANSFER
                */
                $serviceName = preg_replace('/\s+/', ' ', rtrim($m['serviceName']));

                if (stripos($s->getId(), 'train') !== false) {
                    $s->extra()->service($serviceName);
                } elseif (stripos($s->getId(), 'ferry') !== false) {
                    /** @var \AwardWallet\Schema\Parser\Common\FerrySegment $s */
                    $s->extra()->carrier($serviceName);
                }
                $dateDep = strtotime($this->normalizeDate($m['date']));
            } elseif (preg_match("/^\s*(?<date>{$this->patterns['date']})(?:\s|$)/u", $table[1], $m)) {
                // it-575652195.eml
                $dateDep = strtotime($this->normalizeDate($m['date']));
            }

            $nameDep = $nameArr = null;

            if (stripos($s->getId(), 'train') !== false) {
                $namePrefix = 'Train Station, ';
            } elseif (stripos($s->getId(), 'bus') !== false) {
                $namePrefix = 'Bus Station, ';
            } else {
                $namePrefix = '';
            }

            if ($tableType === 1) {
                /*
                    NEW YORK (PENN STATION) -
                    HARRISBURG
                    1 Reserved Coach Seat
                */
                $stationsText = preg_replace('/\s+/', ' ', $this->re("/^((?:[^[:lower:]\n]+\n+)+)/u", $table[2]));
                $stations = preg_split("/\s+-\s+/", rtrim($stationsText)); // it-1.eml

                if (count($stations) !== 2) { // it-128724610.eml
                    /*
                        San Diego, CA - Santa Fe Depot
                        To Los Angeles, CA - Union Station
                        1 Unreserved Coach Seat
                    */
                    $stationsText = preg_replace('/\s+/', ' ',
                        $this->re("/^([\s\S]{3,}?\s+To\s+[\s\S]{3,}?)\n+[ ]*(?:\d+[ ]+[[:upper:]][[:lower:]]|.*Car|.*Seat)/",
                            $table[2]));
                    $stations = preg_split("/\s+To\s+/", $stationsText);
                }

                if (count($stations) === 2) {
                    $in = [
                        '/^(.{2,},[ ]*[A-Z]{2})$/',
                        '/^(.{2,},[ ]*[A-Z]{2})[ ]+[-–]+[ ]+([\s\S]{2,})$/',
                    ];

                    $out[0] = empty($namePrefix) ? '$1' : $namePrefix . '$1';
                    $out[1] = '$2, $1';

                    $nameDep = preg_replace($in, $out, $stations[0]);
                    $nameArr = preg_replace($in, $out, $stations[1]);
                }
            } elseif ($tableType == 2) {
                /*
                    DEPARTS
                    8:01 AM
                    Boston, MA
                    South Station
                */
                $pattern = '/\d{1,2}:\d{2}.*\n+[ ]*([\s\S]{2,})$/';
                $in = [
                    '/^(.{2,},[ ]*[A-Z]{2})$/',
                    '/^(.{2,},[ ]*[A-Z]{2})\n+[ ]*([\s\S]{2,})$/',
                    '/([ ]*\n+[ ]*)+/',
                ];

                $out[0] = empty($namePrefix) ? '$1' : $namePrefix . '$1';
                $out[1] = '$2, $1';
                $out[2] = ', ';

                $nameDep = preg_replace($in, $out, trim($this->re($pattern, $table[3])));
                $nameArr = preg_replace($in, $out, trim($this->re($pattern, $table[4])));
            }

            // TODO: temporarily. until the question is solved how to work with local (provider) station codes
            if (preg_match("/(?:One-way|Round-|trip)\n+(.{3,}?)[ ]{2,}(.{3,}?)[ ]{2,}[[:alpha:]]+ \d{1,2}, \d{4}\n/iu", $text, $matches)) {
                // only for $tableType === 1

                $pattern = '/^(?<first>\w+).*, ?(?<state>[A-Z]{2,3})$/u';
                $pattern2 = '/(?:\bStation\b)/i';

                if (preg_match($pattern, $matches[1], $m)) {
                    if (preg_match("/^" . preg_quote($m['first'], '/') . "\b/ui", $nameDep)
                        && !preg_match('/\b' . preg_quote($m['state'], '/') . '\b/', $nameDep)
                    ) {
                        $nameDep = (preg_match($pattern2, $nameDep) > 0 ? '' : $namePrefix) . $nameDep . ', ' . $m['state'];
                    }

                    if (preg_match("/^" . preg_quote($m['first'], '/') . "\b/ui", $nameArr)
                        && !preg_match('/\b' . preg_quote($m['state'], '/') . '\b/', $nameArr)
                    ) {
                        $nameArr = (preg_match($pattern2, $nameArr) > 0 ? '' : $namePrefix) . $nameArr . ', ' . $m['state'];
                    }
                }

                if (preg_match($pattern, $matches[2], $m)) {
                    if (preg_match("/^" . preg_quote($m['first'], '/') . "\b/ui", $nameDep)
                        && !preg_match('/\b' . preg_quote($m['state'], '/') . '\b/', $nameDep)
                    ) {
                        $nameDep = (preg_match($pattern2, $nameDep) > 0 ? '' : $namePrefix) . $nameDep . ', ' . $m['state'];
                    }

                    if (preg_match("/^" . preg_quote($m['first'], '/') . "\b/ui", $nameArr)
                        && !preg_match('/\b' . preg_quote($m['state'], '/') . '\b/', $nameArr)
                    ) {
                        $nameArr = (preg_match($pattern2, $nameArr) > 0 ? '' : $namePrefix) . $nameArr . ', ' . $m['state'];
                    }
                }
            }

            if ((stripos($s->getId(), 'train') !== false || stripos($s->getId(), 'bus') !== false)
                && preg_match("/(?:\bLos Angeles\b|\bNew York\b|\bWashington\b|\bSan Francisco\b|\bSan Diego\b|\bSanta Rosa\b|\bSanta Monica\b|\bOceanside\b|\bLongview\b|\bHanford\b|\bFresno\b)/i", $nameDep . "\n" . $nameArr) > 0
            ) {
                if (!empty($nameDep) && !preg_match('/\bUSA\b/', $nameDep)) {
                    $nameDep .= ', USA';
                }

                if (!empty($nameArr) && !preg_match('/\bUSA\b/', $nameArr)) {
                    $nameArr .= ', USA';
                }
            }

            $s->departure()->name($nameDep);
            $s->arrival()->name($nameArr);

            if (preg_match("/^[ ]*\d+[ ]+([\s\S]{2,}?)\s+Seats?(?:[ ]{2,}\S.*|\s*\|[\s\S]*)?$/im", $table[2], $m)) {
                $m[1] = preg_replace('/\s+/', ' ', $m[1]);
                $s->extra()->cabin($serviceName !== null ? preg_replace('/^' . preg_quote($serviceName, '/') . '\s+/i', '', $m[1]) : $m[1]);
            }

            if (preg_match("/(?:\n|\|)[ ]*Car\s+(?<car>\d+)\s*[\|\-]\s*Seats?\s*(?-i)(?<seats>\d+[A-Z][,\s\dA-Z]*)\s*$/i", $table[2], $m)) {
                // Car 2153 | Seats 5A, 5C, 5F
                $s->extra()->car($m['car'])->seats(preg_split('/\s*,\s*/', $m['seats']));
            } elseif (preg_match("/(?:\n|\|)[ ]*Car\s+(?<car>\d+)[\|\-\s]+Rooms?\s*-?\s*(?-i)(?:\d+)\s*$/i", $table[2], $m)) {
                // 1 Roomette | Car 330 Room - 11
                $s->extra()->car($m['car']);
            } elseif (preg_match("/(?:\n|\|)[ ]*Seats?\s*(?-i)(?<seats>\d+[A-Z][,\s\dA-Z]*)\s*$/i", $table[2], $m)) {
                // 1 Business Class Seat | Seat 9F
                $s->extra()->seats(preg_split('/\s*,\s*/', $m['seats']));
            }

            if (preg_match("/^\s*ARRIVES[ ]*\([ ]*(?<date>.*\d.*?)[ ]*\)(?:[ ]*\n|\s*$)/i", $table[4], $matches)) {
                /*
                    ARRIVES (Thu May 15)
                */
                if ($dateDep && !preg_match("/\d{4}$/", $matches['date']) && preg_match("/^(?<wday>[-[:alpha:]]+)[,\s]+(?<date>[[:alpha:]]+\s+\d{1,2})$/u", $matches['date'], $m)) {
                    // Thu May 15
                    $yearDep = date('Y', $dateDep);
                    $weekDateNumber = WeekTranslate::number1($m['wday']);
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($this->normalizeDate($m['date']) . ' ' . $yearDep, $weekDateNumber);
                } else {
                    $dateArr = strtotime($this->normalizeDate($matches['date']));
                }
            } elseif (preg_match("/^\s*ARRIVES\s*\n/i", $table[4], $matches)) {
                /*
                    ARRIVES
                */
                $dateArr = $dateDep;
            } else {
                $dateArr = 0;
            }

            $timeDep = $this->re("/^\s*DEPARTS\n+[ ]*({$this->patterns['time']})(?:[ ]*\n|\s*$)/i", $table[3]);
            $timeArr = $this->re("/^\s*ARRIVES(?:[ ]*\([ ]*.*\d.*?[ ]*\)[ ]*)?\n+[ ]*({$this->patterns['time']})(?:[ ]*\n|\s*$)/i", $table[4]);

            if ((empty($timeDep) || empty($timeArr))
                && preg_match("/^.{45,} (?<time1>{$this->patterns['time']})[ ]+(?<time2>{$this->patterns['time']})$/m", $sText, $m)
            ) {
                // it-153231251.eml
                $timeDep = $m['time1'];
                $timeArr = $m['time2'];
            }

            if ($dateDep && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $dateDep));
            }

            if ($dateArr && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $dateArr));
            }
        }

        foreach ($email->getItineraries() as $it) {
            /** @var \AwardWallet\Schema\Parser\Common\Train $it */
            if (empty($it->getSegments())) {
                continue;
            }

            $it->general()
                ->confirmation($confirmation)
                ->date($resDate)
                ->travellers($passengers, true)
            ;

            if ($ticket) {
                $it->addTicketNumber($ticket, false);
            }

            foreach ($accounts as $num => $name) {
                if (count($accounts) > 0) {
                    $it->program()->account($num, false, $name);
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amtrak.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            if (strpos($textPdf, 'Amtrak') === false) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $textPdfFull = '';

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($textPdf === null) {
                continue;
            }

            if ($this->assignLang($textPdf)) {
                $textPdfFull .= "\n" . $textPdf;
            }
        }

        $pdfJunkStatuses = [];
        $documents = $this->splitText($textPdfFull, "/^(.*[ ]{2}RESERVATION NUMBER[ ]+[-A-Z\d]{5,})$/m", true); // it-99369061.eml

        foreach ($documents as $docText) {
            $this->parsePdf($email, $docText, $pdfJunkStatuses);
        }

        $email->setType('ETicketPdf' . ucfirst($this->lang));

        if (count(array_unique($pdfJunkStatuses)) === 1 && $pdfJunkStatuses[0] === true) {
            $email->setIsJunk(true);
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

    /*
    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $pos = [];
        $length = [];

        foreach ($textRows as $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $row) {
                $sym = mb_substr($row, $l, 1);

                if ($sym !== false && trim($sym) !== '') {
                    $notspace = true;
                    $oneRow[$l] = 'a';
                }
            }

            if ($notspace == false) {
                $oneRow[$l] = ' ';
            }
        }

        return $oneRow;
    }
    */

    private function splitText(?string $textSource, string $pattern, bool $saveDelimiter = false): array
    {
        $result = [];

        if ($saveDelimiter) {
            $textFragments = preg_split($pattern, $textSource, -1, PREG_SPLIT_DELIM_CAPTURE);
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

    private function rowColsPos(?string $row): array
    {
        $pos = [];
        $head = preg_split('/\s{2,}/', $row, -1, PREG_SPLIT_NO_EMPTY);
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function splitCols(?string $text, ?array $pos = null): array
    {
        $cols = [];

        if ($text === null) {
            return $cols;
        }
        $rows = explode("\n", $text);

        if ($pos === null || count($pos) === 0) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = rtrim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function assignLang($text): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($text, $phrase) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // May 15, 2021
            "/^([[:alpha:]]+)\s+(\d{1,2})\s*,\s*(\d{4})$/u",
            // Thu May 15    |    May 15
            "/^(?:[-[:alpha:]]+\s+)?([[:alpha:]]+)\s+(\d{1,2})$/u",
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str 2 = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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

    /*
    private function sortedPdf($parser): bool
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (!isset($pdfs[0])) {
            return false;
        }

        $pdfHtmlFull = '';

        foreach ($pdfs as $pdf) {
            if (($pdfHtml = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
                continue;
            }

            if ($this->assignLang($pdfHtml)) {
                $pdfHtmlFull .= $pdfHtml . "\n";
            }
        }

        if (!$pdfHtmlFull) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($pdfHtmlFull);
        $res = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $grid = [];

            foreach ($nodes as $node) {
                $text = implode("\n", $this->pdf->FindNodes("./descendant::text()[normalize-space(.)!='']", $node));
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $grid[$top][$left] = $text;
            }

            foreach ($grid as &$c) {
                ksort($c);
                $r = "";

                foreach ($c as $t) {
                    $r .= $t . "\n";
                }
                $c = $r;
            }

            ksort($grid);

            foreach ($grid as $r) {
                $res .= $r;
            }
        }
        $this->pdf->SetEmailBody($res);

        return true;
    }
    */

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
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
