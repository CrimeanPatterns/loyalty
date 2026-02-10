<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

// for html parse use this class - MemoVoyage
class ETicketPDF extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-32664074.eml, airfrance/it-32922789.eml, airfrance/it-32954271.eml, airfrance/it-33050670.eml, airfrance/it-33699877.eml, airfrance/it-4009241.eml, airfrance/it-4042945.eml, airfrance/it-4063772.eml, airfrance/it-4089108.eml, airfrance/it-5079166.eml, airfrance/it-5132522.eml, airfrance/it-5493992.eml, airfrance/it-6118998.eml, airfrance/it-6156832.eml";

    public $reFrom = "airfrance.com";
    public $reBody = [
        'ja' => ['ご予約番号', 'Eチケット'],
        'fr' => ['RÉFÉRENCE DE VOTRE RÉSERVATION', 'BILLET ELECTRONIQUE'],
        'en' => ['YOUR BOOKING REFERENCE', 'ELECTRONIC TICKET'],
        'cs' => ['PODROBNOSTI O VAŠICH LETECH', 'Číslo letenky'],
    ];
    public $reSubject = [
        'en' => 'Ticket and information for your trip on',
        'fr' => 'Billet et informations pour votre voyage du',
        'it' => 'Biglietto e informazioni per il suo viaggio del',
        'ru' => 'Билет и информация о вашем путешествии',
        'nl' => 'Ticket en informatie over uw reis op',
        'pt' => 'Bilhete e informações para a sua viagem de',
        'hu' => 'Repülőjegy és tudnivalók az ön',
        'de' => 'Ticket und Informationen für Ihre Reise am',
        'pl' => 'Bilet i informacje dotyczące Twojej podróży w dniu',
        'ko' => '여행을 위한 항공권 및 정보',
        'ro' => 'Bilet şi informaţii pentru călătoria dumneavoastră din',
        'cs' => 'Letenka a informace k vaší cestě dne',
    ];
    public $lang = '';
    public $date;
    public $pdfNamePattern = ".*pdf"; //"(?:Billet|Electronic\_ticket|Biglietto_elettronico).*pdf";
    public static $dict = [
        'fr' => [
            'Class'              => 'Classe',
            'Flight provided by' => 'Volo effettuato da',
            'Aircraft'           => 'Aereo',
            'Card '              => 'Carte ',
        ],
        'ja' => [
            'Terminal'           => 'ターミナル',
            'Class'              => 'キャビンクラス',
            'Flight provided by' => 'による運航便',
            'Aircraft'           => '機種',
            'Card '              => '会員番号 ',
        ],
        'cs' => [
            'Terminal'           => 'Terminál',
            'Class'              => 'Třída',
            'Flight provided by' => 'Let zajišťuje',
            //            'Aircraft' => '',
            //            'Card ' => ''
        ],
        'en' => [
            'Card ' => ['Carte ', 'Card '],
        ],
    ];
    public $textPdf;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getDate());
        $its = [];

        $pdfs = $parser->searchAttachmentByType('application/pdf');

        if (count($pdfs) === 0) {
            $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        }

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $text = text($html);
                    $this->textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));
                    $this->assignLang($text);
                    $its[] = $this->parseEmail($text);

                    break;
                } else {
                    return null;
                }
            }
        } else {
            return null;
        }

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => 'ETicketPDF' . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdf = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdf[0])) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf[0]));

            if (stripos($text, 'www.airfrance.com') !== false || stripos($text, 'Air France') !== false) {
                return $this->assignLang($text);
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], $this->reFrom) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;
        $left = mb_strstr($input, $searchStart);

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

    private function parseEmail($textPDF): ?array
    {
        $patterns = [
            'time'            => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName'   => '[[:upper:]][-.\'’[:upper:] ]*[[:upper:]]', // PARKER RICHARD MR
            'noTravellerName' => '(?:SAVE TIME|PASSENGERS|ITINÉRAIRE|FOID)',
            'namePrefixes'    => '(?:MISS|MSTR|MRS|MS|MR|DR)',
            'passengerType'   => '(?:Osoba dorosła|Erwachsener|Взрослый|Adulte|Adulto|Felnőtt|Odrase|Adult|Child|Youth|성인|PAX|YCD)',
            'eTicket'         => '\d{3} \d{3} \d{3} \d{3} \d', // 057 238 309 516 0
        ];

        $textInfo = $this->findCutSection($textPDF, 'YOUR BOOKING REFERENCE', 'ITINERARY');
        $textPass = $this->findCutSection($textPDF, 'PASSENGERS', 'ITINERARY');

        $textIt = $this->findCutSection($textPDF, 'ITINERARY', ['BEFORE YOUR FLIGHT', 'AIR FRANCE WISHES YOU A VERY PLEASANT TRIP']);
        $textPay = $this->findCutSection($textPDF, 'Receipt', null);

        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = [];
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->re("#(?:RÉFÉRENCE DE VOTRE RÉSERVATION|YOUR BOOKING REFERENCE)\s+\b([A-Z\d]{5,8})\b#", $textPDF);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->re("#\n\s*([A-Z\d]{5,8})\n\s*(?:RÉFÉRENCE DE VOTRE RÉSERVATION|YOUR BOOKING REFERENCE)#", $textPDF);
        }
        //		 $this->logger->info($textPDF);

        $dateIssue = $this->normalizeDate($this->re("#(?:Date and place of issue|Date et lieu d'émission)\s*:\s*(\d+\s+\w+\s+\d+)#", $textPay));

        if ($dateIssue) {
            $this->date = $it['ReservationDate'] = $dateIssue;
        }

        if (preg_match_all("#(?:\n\s*|[ ]{3,}){$this->preg_implode($this->t('Card '))}([A-Z\d]{2}) .+?([A-Z\d]{5,})#s", $textInfo, $accountMatches, PREG_SET_ORDER)) {
            foreach ($accountMatches as $v) {
                $it['AccountNumbers'][] = $v[1] . ' ' . $v[2];
            }
        }
        $node = array_filter(array_unique($this->http->FindNodes("//td[{$this->starts($this->t('Card '))}]", null, "#{$this->preg_implode($this->t('Card '))}([A-Z\d]{2} [\w\-]+)$#")));

        if (count($node) > 0) {
            $node = preg_replace("/^AF 0+/", 'AF ', $node);

            if (isset($it['AccountNumbers'])) {
                $it['AccountNumbers'] = array_values(array_unique(array_merge($it['AccountNumbers'], $node)));
            } else {
                $it['AccountNumbers'] = $node;
            }
        }

        $it['Passengers'] = [];
        $it['TicketNumbers'] = [];

        if (preg_match_all("/\n[ ]{0,8}({$patterns['travellerName']})\s*\(.*{$patterns['passengerType']}.+?\s+(\d.+)/u", $textPass, $passengerMatches)) {
            $it['Passengers'] = $passengerMatches[1];
            $it['TicketNumbers'] = $passengerMatches[2];
        }

        if (preg_match_all("/\n[ ]{0,8}({$patterns['travellerName']})\s+({$patterns['eTicket']})\n(?:.*\n){0,2}[ ]{0,8}({$patterns['travellerName']})\s*\(.*{$patterns['passengerType']}.*/u", $textPass, $passengerMatches)) {
            /* in pdf
             * NGUEMA EP DESRAISSES ABYALE RENEE    057 141 658 738 8
             * MADELEINE MRS (Adulte / Adult)
             */
            foreach ($passengerMatches[1] as $key => $v) {
                $it['Passengers'][] = trim($passengerMatches[1][$key]) . ' ' . trim($passengerMatches[3][$key]);
            }
            $it['TicketNumbers'] = array_merge($it['TicketNumbers'], $passengerMatches[2]);
        }

        if (preg_match_all("/\n[ ]{0,8}({$patterns['travellerName']})\([^\)\n]*?\s+({$patterns['eTicket']})\n(?:.*\n){0,2}[ ]{0,8}[^\(\n]*{$patterns['passengerType']}.*/u", $textPass, $passengerMatches)) {
            /* in pdf
             * MADILIAN NATALIA MRS (Взрослый /   057 142 805 036 4
             * Adult)
             */

            $it['Passengers'] = array_merge($it['Passengers'], array_map("trim", $passengerMatches[1]));
            $it['TicketNumbers'] = array_merge($it['TicketNumbers'], $passengerMatches[2]);
        }

        if (count(array_filter($it['Passengers'])) === 0
            && (preg_match_all("/^[ ]{0,8}({$patterns['travellerName']} {$patterns['namePrefixes']})\s+({$patterns['eTicket']})[ ]{0,8}$/mu", $textPass, $passengerMatches)
                    && !preg_match("/^{$patterns['noTravellerName']}$/imu", implode("\n", $passengerMatches[1]))
                || preg_match_all("/^[ ]{0,8}({$patterns['travellerName']})\s+({$patterns['eTicket']})[ ]{0,8}$/mu", $textPass, $passengerMatches)
                    && !preg_match("/^{$patterns['noTravellerName']}$/imu", implode("\n", $passengerMatches[1]))
            )
        ) {
            // BIELECKA NATACHA 051 234 304 516 0
            $it['Passengers'] = $passengerMatches[1];
            $it['TicketNumbers'] = $passengerMatches[2];
        }

        $it['Passengers'] = preg_replace("/[ ]+{$patterns['namePrefixes']}\s*$/", '', array_unique(array_filter($it['Passengers'])));
        $passTitle = "Adult";

        if (!empty(MemoVoyage::$dict[$this->lang]) && !empty(MemoVoyage::$dict[$this->lang][$passTitle])) {
            $passTitle = MemoVoyage::$dict[$this->lang][$passTitle];
        }
        $passengers = array_filter($this->http->FindNodes("//text()[" . $this->contains($passTitle) . "]/preceding::span[1]"));

        if (empty($passengers) || count($it['Passengers']) !== count($passengers)) {
            foreach (MemoVoyage::$dict as $dictL) {
                $passTitle = "Adult";

                if (!empty($dictL[$passTitle])) {
                    $passengers = array_filter($this->http->FindNodes("//text()[" . $this->contains($dictL[$passTitle]) . "]/preceding::span[position()<=2][normalize-space()][1]"));

                    if (!empty($passengers)) {
                        break;
                    }
                }
            }
        }

        if (empty($passengers)) {
            $passTitle = "Passenger(s)";

            if (!empty(MemoVoyage::$dict[$this->lang]) && !empty(MemoVoyage::$dict[$this->lang][$passTitle])) {
                $passTitle = MemoVoyage::$dict[$this->lang][$passTitle];
            }
            $passengers = array_values(array_unique(array_filter($this->http->FindNodes("//td[not(.//td) and (" . $this->contains($passTitle) . ")]/ancestor::thead[1]/following-sibling::tbody[1]/tr/descendant::td[not(.//td) and string-length() > 5][1]", null, "#(.+?\S)(\s*\(.+\))?$#"))));
        }

        if (!empty($passengers) && count($it['Passengers']) == count($passengers)) {
            $it['Passengers'] = $passengers;
        }

        $it['TicketNumbers'] = array_unique(str_replace(' ', '', $it['TicketNumbers']));
        $it['TicketNumbers'] = array_filter($it['TicketNumbers'], function ($v) {
            return preg_match('/^\d{13}$/', $v) === 1;
        });

        $passTable = $this->re("#^([\s\S]+)\n.*\bTotal cost#", $textPay);

        /* В резервации может быть больше пассажиров, чем указано

         Total cost - это полная стомость резервации за всех пассажиров(даже если они не указаны)
         Fare и Taxes, Fees - только за указанных

        Для билетов купленных за мили, обменянных или других не полностью оплаченных деньгами total cost в валюте не соответсвует, в милях - соответствует
        */

        if (preg_match("/\b(?:Award ticket|Exchange|FORFAIT|NOFARE)\b/", $passTable)) {
            if (preg_match("#Total cost[ ]*(?:/[^:]*)?:\s*.*\b(MILES \d+)\n#", $textPay, $m)) {
                $it['SpentAwards'] = $m[1];
            }
        } else {
            $tot = $this->getTotalCurrency($this->re("/Total cost[ ]*(?:\/[^:]*)?:\s*(.+)/", $textPay));

            if ($tot['Total'] !== null) {
                $it['TotalCharge'] = $tot['Total'];
                $it['Currency'] = $tot['Currency'];
            }

            $taxesRows = explode("\n", $passTable);
            $fees = [];

            foreach ($taxesRows as $tRow) {
                if (preg_match("/^ *(?<currency>" . ($it['Currency'] ?? '[A-Z]{3}') . " )?(?<amount>\d+\.\d{2}) (?<name>\w+(?: \w+)* \/ \w+(?: \w+)*)$/u",
                    $tRow, $m)) {
                    if (empty($it['Currency']) && !empty($m['currency'])) {
                        $it['Currency'] = trim($m['currency']);
                    }

                    if (isset($fees[$m['name']])) {
                        $fees[$m['name']] += round((float) $m['amount'], 2);
                    } else {
                        $fees[$m['name']] = round((float) $m['amount'], 2);
                    }
                }
            }

            if (preg_match_all("/\s+(?:\d{3} ){4}\d *\n.*\n(" . ($it['Currency'] ?? '[A-Z]{3}') . ") (?<cost>\d+[.\d]*)\n/", $passTable, $priceMatches)) {
                if (empty($it['Currency']) && !empty($priceMatches['currency'])) {
                    $it['Currency'] = $priceMatches['currency'];
                }
                $fare = array_sum($priceMatches['cost']);

                if (empty($fare)) {
                    // скорее всего билет неполностью оплачен деньгами и тотал может быть неверный
                    unset($it['TotalCharge']);
                }
            } elseif (preg_match("/\s+(?:\d{3} ){4}\d *\n.*\n\s*([A-Z]{3})? *(?<cost>\d+[.\d]*)\n/", $passTable, $m)
                && empty((float) $m['cost'])) {
                // скорее всего билет неполностью оплачен деньгами и тотал может быть неверный
                unset($it['TotalCharge']);
            } elseif (preg_match("/\s+(?:\d{3} ){4}\d *\n.*\n\s*[^\d\s]+[^\d\n]*\n/", $passTable, $m)) {
                // скорее всего билет неполностью оплачен деньгами и тотал может быть неверный
                unset($it['TotalCharge']);
            }

            if (!empty($fees) && !empty($fare) && !empty($it['TotalCharge'])) {
                if (array_sum($fees) + $fare == $it['TotalCharge']) {
                    $it['BaseFare'] = $fare;

                    foreach ($fees as $k => $fee) {
                        $it['Fees'][] = ["Name" => $k, "Charge" => $fee];
                    }
                } elseif (abs($it['TotalCharge']) % (array_sum($fees) + $fare) < 0.01) {
                    $cp = round($it['TotalCharge'] / (array_sum($fees) + $fare), 0);
                    $it['BaseFare'] = $cp * $fare;

                    foreach ($fees as $k => $fee) {
                        $it['Fees'][] = ["Name" => $k, "Charge" => $cp * $fee];
                    }
                }
            }
        }

        $nodes = $this->splitter("/\n({$patterns['time']}\n{$patterns['time']}(?:\s*\((?:Day\/Jour|[^\d\W]{3,5}\/Day)\s*\+\s*\d\))?\n\d+\s*\w+)/iu", $textIt);

        foreach ($nodes as $root) {
            $seg = [];

            if (preg_match("/^\s*({$patterns['time']})\n({$patterns['time']})(?:\s*\((?:Day\/Jour|[^\d\W]{3,5}\/Day)\s*\+\s*(\d)\))?\n(\d+\s*\w+)/iu", $root, $m)) {
                $date = $this->normalizeDate($m[4], $this->date);
                $depTime = $m[1];
                $arrTime = $m[2];
                $seg['DepDate'] = !empty($date) ? strtotime($depTime, $date) : null;
                $seg['ArrDate'] = !empty($date) ? strtotime($arrTime, $date) : null;

                if (!empty($m[3])) {
                    $seg['ArrDate'] = strtotime("+{$m[3]} days", $seg['ArrDate']);
                }
            }

            if (preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)\b/", $root, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#\n([A-Z]{3})\n([A-Z]{3})#", $root, $m)) {
                $seg['DepCode'] = $m[1];
                $seg['ArrCode'] = $m[2];
            }
            //detect order of points (it's important!!! not delete)
            if (isset($depTime, $arrTime, $seg['AirlineName'], $seg['FlightNumber'])) {
                if (preg_match("/\n([ ]+{$depTime}[ ]+{$arrTime}.*\s+(?:.+\n+){0,2}.*{$seg['AirlineName']}{$seg['FlightNumber']}(?:.+\n+){7})/", $this->textPdf, $m)) {
                    $m[1] = preg_replace("/^([ ]+{$depTime}[ ]+{$arrTime}[\s\S]+?\n+)[ ]+{$patterns['time']}[ ]+{$patterns['time']}[\s\S]*/", '$1', $m[1]); // remove next segment

                    if (preg_match_all("/(.+?[ ]{2})([A-Z]{3})(?:[ ]{2}|\n)/", $m[1], $v, PREG_SET_ORDER) && count($v) === 2) {
                        if (strlen($v[0][1]) < strlen($v[1][1])) {
                            $seg['DepCode'] = $v[0][2];
                            $seg['ArrCode'] = $v[1][2];
                        } elseif (strlen($v[1][1]) < strlen($v[0][1])) {
                            $seg['DepCode'] = $v[1][2];
                            $seg['ArrCode'] = $v[0][2];
                        }
                    } elseif (!empty($seg['DepCode']) && !empty($seg['ArrCode'])
                            && preg_match("#^(.*?)\b(" . $seg['DepCode'] . ")\b#m", $m[1], $v1)
                            && preg_match("#^(.*?)\b(" . $seg['ArrCode'] . ")\b#m", $m[1], $v2)
                    ) {
                        if (strlen($v1[1]) > strlen($v2[1])) {
                            $seg['DepCode'] = $v1[2];
                            $seg['ArrCode'] = $v2[2];
                        }
                    }
                }
            }

            if (!empty($seg['AirlineName']) && !empty($seg['FlightNumber'])) {
                if (preg_match("#{$seg['AirlineName']}{$seg['FlightNumber']}\n\d+:\d+\n#", $root)) {
                    if (preg_match("#\d+:\d+\n.+\n(.{5,})\n([A-Z]{1,2})\n.+\n[A-Z]{3}\n[A-Z]{3}#", $root, $m)
                            || preg_match("#\d+:\d+\n.+\n(.{5,})\n([A-Z]{1,2})\nOK\n#", $root, $m)) {
                        $seg['Cabin'] = $m[1];
                        $seg['BookingClass'] = $m[2];
                    } elseif (stripos($root, "No baggage") === false && preg_match("#\d+:\d+\n([^\d\n]{5,})\n([A-Z]{1,2})\n.+\n[A-Z]{3}\n[A-Z]{3}#", $root, $m)) {
                        $seg['Cabin'] = $m[1];
                        $seg['BookingClass'] = $m[2];
                    }
                } else {
                    if (preg_match("#{$seg['AirlineName']}{$seg['FlightNumber']}\n.+\n(.{5,})\n([A-Z]{1,2})\n.+\n[A-Z]{3}\n[A-Z]{3}#", $root, $m)
                            || preg_match("#{$seg['AirlineName']}{$seg['FlightNumber']}\n.+\n(.{5,})\n([A-Z]{1,2})\nOK\n#", $root, $m)) {
                        $seg['Cabin'] = $m[1];
                        $seg['BookingClass'] = $m[2];
                    } elseif (stripos($root, "No baggage") === false && preg_match("#{$seg['AirlineName']}{$seg['FlightNumber']}\n([^\d\n]{5,})\n([A-Z]{1,2})\n.+\n[A-Z]{3}\n[A-Z]{3}#", $root, $m)) {
                        $seg['Cabin'] = $m[1];
                        $seg['BookingClass'] = $m[2];
                    }
                }

                if (empty($seg['BookingClass']) && preg_match("#\n([A-Z]{1,2})\nOK\n.+\n[A-Z]{3}#", $root, $m)) {
                    $seg['BookingClass'] = $m[1];
                }

                if (preg_match("#Flight operated by +(.+)#", $root, $m)) {
                    $seg['Operator'] = trim($m[1], '/');
                }

                if (preg_match("#Seat *: *(\d{1,3}[A-Z]([ ]+\d{1,3}[A-Z])*)\n#", $root, $m) || preg_match("#Siège *: *(\d{1,3}[A-Z]([ ]+\d{1,3}[A-Z])*)\n#", $root, $m)) {
                    $seg['Seats'] = array_filter(array_map('trim', explode(" ", $m[1])));
                }
            }

            if (isset($seg['AirlineName'], $seg['FlightNumber'], $seg['DepCode'], $seg['ArrCode'])
                && ($root = $this->http->XPath->query("//text()[contains(.,'{$seg['AirlineName']}') and contains(.,'{$seg['FlightNumber']}')]/ancestor::table[contains(.,'{$seg['DepCode']}')][1]"))->length === 1
            ) {
                //trying to get terminal  from body
                $root = $root->item(0);
                $terminalTitle = ['Terminal', 'ターミナル', 'Терминал', '터미널', "航站楼：", "terminál"];
                $seg['DepartureTerminal'] = $this->http->FindSingleNode("./descendant::text()[contains(.,'{$seg['DepCode']}')]/following::text()[normalize-space(.)!=''][1][" . $this->contains($terminalTitle) . "]",
                    $root, false, "#" . $this->preg_implode($terminalTitle) . "[:]?\s+(.+)$#u");
                $seg['ArrivalTerminal'] = $this->http->FindSingleNode("./descendant::text()[contains(.,'{$seg['ArrCode']}')]/following::text()[normalize-space(.)!=''][1][" . $this->contains($terminalTitle) . "]",
                    $root, false, "#" . $this->preg_implode($terminalTitle) . "[:]?\s+(.+)$#u");

                $node = $this->http->FindSingleNode("./following-sibling::table[2][contains(.,'{$this->t('Class')}')]/descendant::text()[contains(.,'{$this->t('Class')}')]/ancestor::td[1]",
                        $root, true, "#\b" . $this->t('Class') . "\s+(.+)#");

                if (!empty($node)) {
                    $seg['Cabin'] = $node;
                }
                $node = trim(str_replace($this->t('Aircraft'), '',
                    $this->http->FindSingleNode("./following-sibling::table[2][contains(.,'{$this->t('Aircraft')}')]/descendant::text()[contains(.,'{$this->t('Aircraft')}')]/ancestor::td[1]",
                        $root)));

                if (!empty($node)) {
                    $seg['Aircraft'] = $node;
                }
                $node = trim(str_replace($this->t('Flight provided by'), '',
                    $this->http->FindSingleNode("./following-sibling::table[2][contains(.,'{$this->t('Flight provided by')}')]/descendant::text()[contains(.,'{$this->t('Flight provided by')}')]/ancestor::td[1]",
                        $root)));

                if (!empty($node)) {
                    $seg['Operator'] = $node;
                }

                $node = array_filter($this->http->FindNodes("following-sibling::table[1]/descendant::img[contains(@src,'siege.')]/following::text()[normalize-space()!=''][1]",
                    $root, "#^\d+[A-z]$#"));

                if (count($node) > 0) {
                    if (isset($seg['Seats'])) {
                        $seg['Seats'] = array_values(array_unique(array_merge($seg['Seats'], $node)));
                    } else {
                        $seg['Seats'] = $node;
                    }
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return $it;
    }

    private function normalizeDate($str, $dateRelative = null)
    {
        $in = [
            '#^(\d+\s+\w+\s+\d+)$#u',
            '#^(\d{2})\s*(\w{3})$#',
        ];
        $out = [
            '$1',
            '$1 $2 %year%',
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
        } elseif (preg_match("/^\d+ [[:alpha:]]+ \d{4}$/", $str)) {
            // $this->logger->debug('$date (year) = '.print_r( $str,true));
            return strtotime($str);
        } else {
            return null;
        }

        return null;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function getTotalCurrency($node): array
    {
        $tot = null;
        $cur = null;

        if (preg_match("/^(?<c>[A-Z]{3})\s*(?<t>\d[,.‘\'\d ]*)/u", $node, $m)
            || preg_match("/^(?<t>\d[,.‘\'\d ]*?)\s*(?<c>[A-Z]{3})\b/u", $node, $m)
            || preg_match("/^(?<c>-*?)(?<t>\d[,.‘\'\d ]*)/u", $node, $m)
        ) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $m['c']) ? $m['c'] : null;
            $tot = PriceHelper::parse(rtrim($m['t'], ',. '), $currencyCode);
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
