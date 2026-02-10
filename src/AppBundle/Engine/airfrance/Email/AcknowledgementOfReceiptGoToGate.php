<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class AcknowledgementOfReceiptGoToGate extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-125542168.eml, airfrance/it-164302871.eml, airfrance/it-6279527.eml";

    public static $dictionary = [
        'es' => [
            'Order no'            => ['Nº de pedido'],
            'Passenger name'      => ['Nombre del pasajero'],
            'Issued'              => 'Expedido',
            'youBookingNumber'    => 'Tu número de reserva',
            'Class'               => 'Clase',
            'e-Ticket receipt(s)' => 'Comprobante(s) de billete(s) electrónico(s)',
        ],
        'de' => [
            'Order no'            => ['Buchungsnr.'],
            'Passenger name'      => ['Name des Passagiers'],
            'Issued'              => 'Ausgestellt',
            'youBookingNumber'    => 'Ihre Buchungsnummer',
            'Class'               => 'Klasse',
            'e-Ticket receipt(s)' => 'E-Ticket-Beleg(e)',
        ],
        'en' => [
            'Order no'         => ['Order no'],
            'Passenger name'   => ['Passenger name'],
            'youBookingNumber' => ['At the aiport, get your ticket using booking No.:', 'Your booking number'],
        ],
    ];

    public $lang = "en";
    public $code;
    private $pdf;

    public function parsePdf(Email $email, $textPdf)
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';
        $xpathNoEmpty2 = '(normalize-space() and normalize-space()!=" ")';
        $xpathTime = 'contains(translate(normalize-space(),"0123456789","dddddddddd"),"d:dd")';

        $f = $email->add()->flight();
        $year = 0;
        $issuedDate = strtotime($this->normalizeDate($this->pdf->FindSingleNode("//tr[not(.//tr) and td[{$this->starts($this->t('Issued'))}] ]", null, true, "/{$this->opt($this->t('Issued'))}\s*[:]+\s*(.{6,})$/")));

        if ($issuedDate) {
            $year = date('Y', $issuedDate);
        }

        // RecordLocator
        $recordLocators1 = $this->pdf->FindNodes("//tr/td[{$this->eq($this->t("youBookingNumber"))}]/following-sibling::td[{$xpathNoEmpty}][1]", null, '/^\s*([A-Z\d]{5,})\s*$/');
        $recordLocators2 = $this->pdf->FindNodes("//tr[ td[{$this->eq($this->t("youBookingNumber"))}] ]/preceding-sibling::tr[{$xpathNoEmpty}][1]/td[{$xpathNoEmpty}][last()]", null, '/^\s*([A-Z\d]{5,})\s*$/');
        $recordLocators3 = $this->pdf->FindNodes("//tr[ td[{$this->eq($this->t("youBookingNumber"))}] ]/following-sibling::tr[{$xpathNoEmpty}][1]/td[{$xpathNoEmpty}][last()]", null, '/^\s*([A-Z\d]{5,})\s*$/');
        $recordLocators = array_merge($recordLocators1, $recordLocators2, $recordLocators3);
        $recordLocators = array_values(array_unique(array_filter($recordLocators)));

        if (count($recordLocators) === 1) {
            $f->general()
                ->confirmation($recordLocators[0]);
        }

        $passengers = [];
        $eTickets = [];

        $xpath = "//text()[{$this->eq($this->t('DEPARTURE'))}]/ancestor::tr[1]";
        $nodes = $this->pdf->XPath->query($xpath);

        foreach ($nodes as $root) {
            $cols = [
                'dep' => $this->pdf->XPath->query("td[{$this->eq($this->t('DEPARTURE'))}]/preceding-sibling::td", $root)->length + 1,
                'arr' => $this->pdf->XPath->query("td[{$this->eq($this->t('ARRIVAL'))}]/preceding-sibling::td", $root)->length + 1,
            ];

            $s = $f->addSegment();
            //$itsegment = [];

            // AirlineName
            // FlightNumber
            $flight = $this->pdf->FindSingleNode("following-sibling::tr[2]/td[{$xpathNoEmpty}][1]", $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }

            // DepCode
            $s->departure()
                ->code($this->pdf->FindSingleNode("following-sibling::tr[1]/td[{$xpathNoEmpty}][1]", $root, true, "#^([A-Z]{3})$#"))
                ->name($this->pdf->FindSingleNode("following-sibling::tr[3]/td[{$xpathNoEmpty}][2]", $root));

            // DepName
            $patterns['date'] = '/^(?<wday>[[:alpha:]]{2,})\s+(?<date>\d{1,2}\s+[[:alpha:]]{3,})$/u'; // FRI 3 JUL

            // DepDate
            $xpathDepTime = "following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[{$cols['dep']}][{$xpathTime}]";
            $dateDep = $this->pdf->FindSingleNode($xpathDepTime . "/ancestor::tr[1]/preceding-sibling::tr[ td[{$cols['dep']}][{$xpathNoEmpty}] ][1]/td[{$cols['dep']}]", $root);
            $timeDep = $this->pdf->FindSingleNode($xpathDepTime, $root);

            if ($year && $timeDep && preg_match($patterns['date'], $dateDep, $m)) {
                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($weekDayNumber) {
                    $dateDep = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDayNumber);
                    $s->departure()
                        ->date(strtotime($timeDep, $dateDep));
                }
            }

            // ArrDate
            $xpathArrTime = "following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[position()={$cols['arr']} or position()=" . ($cols['arr'] + 1) . " or position()=" . ($cols['arr'] - 1) . "][{$xpathTime}]";
            $dateArr = $this->pdf->FindSingleNode($xpathArrTime . "/ancestor::tr[1]/preceding-sibling::tr[ td[{$cols['arr']}][{$xpathNoEmpty}] ][1]/td[{$cols['arr']}]", $root);
            $timeArr = $this->pdf->FindSingleNode($xpathArrTime, $root);

            if ($year && $timeArr && preg_match($patterns['date'], $dateArr, $m)) {
                $weekDayNumber = WeekTranslate::number1($m['wday']);

                if ($weekDayNumber) {
                    $dateArr = EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $year, $weekDayNumber);
                    $s->arrival()
                        ->date(strtotime($timeArr, $dateArr));
                }
            }

            // DepartureTerminal
            $terminalDep = $this->pdf->FindSingleNode("following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[{$cols['dep']}][{$this->starts($this->t('Terminal:'))}]/ancestor::tr[1]/following-sibling::tr[ td[{$cols['dep']}][{$xpathNoEmpty2}] ][1]/td[{$cols['dep']}]", $root);

            if (empty($terminalDep)) {
                $terminalDep = $this->re("/{$this->opt($timeArr)}\s*Terminal:\s*Terminal:\s*(\S+)\s*\S+/s", $textPdf);

                if (empty($terminalDep)) {
                    $terminalDep = $this->re("/{$this->opt($timeArr)}\s*{$this->opt($timeDep)}\s*Terminal:\s*Terminal:\s*\S+\s*(\S+)/s", $textPdf);
                }
            }

            if ($terminalDep) {
                $s->departure()
                    ->terminal($terminalDep);
            }

            // ArrivalTerminal
            $terminalArr = $this->pdf->FindSingleNode("following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[position()={$cols['arr']} or position()=" . ($cols['arr'] + 1) . " or position()=" . ($cols['arr'] - 1) . "][{$this->starts($this->t('Terminal:'))}]/ancestor::tr[1]/following-sibling::tr[ td[{$cols['arr']}][{$xpathNoEmpty2}] ][1]/td[{$cols['arr']}]", $root);

            if ($terminalArr == 'ARRIVAL') {
                $terminalArr = '';
            }

            if (empty($terminalArr)) {
                $terminalArr = $this->re("/{$this->opt($timeArr)}\s*Terminal:\s*Terminal:\s*\S+\s*(\S+)/s", $textPdf);

                if (empty($terminalArr)) {
                    $terminalArr = $this->re("/{$this->opt($timeArr)}\s*{$this->opt($timeDep)}\s*Terminal:\s*Terminal:\s*(\S+)/s", $textPdf);
                }
            }

            if ($terminalArr) {
                $s->arrival()
                    ->terminal($terminalArr);
            }

            // ArrCode
            $s->arrival()
                ->code($this->pdf->FindSingleNode("following-sibling::tr[1]/td[{$xpathNoEmpty}][2]", $root, true, "#^([A-Z]{3})$#"))
                ->name($this->pdf->FindSingleNode("following-sibling::tr[3]/td[{$xpathNoEmpty}][3]", $root));

            // Operator
            $operator = $this->pdf->FindSingleNode("following::tr[position()<7][{$this->contains($this->t('Operated by:'))}]/following-sibling::tr[1]/td[1]", $root);

            if (empty($operator)) {
                $operator = $this->pdf->FindSingleNode("following::tr[{$this->contains($this->t('Operated'))}]/following::tr[1]/td[string-length()>2][1]", $root);
            }

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            if (!empty($operator)) {
                // Passengers
                $passengerPos = $this->pdf->XPath->query("(following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[{$this->contains($this->t('Passenger name'))}])[1]/preceding-sibling::td", $root)->length + 1;
            }

            $passenger = array_unique(array_filter($this->pdf->FindNodes("following-sibling::tr[{$xpathNoEmpty}][position()<10][ td[{$passengerPos}][{$this->contains($this->t('Passenger name'))}] ]/following-sibling::tr[ td[{$passengerPos}][{$xpathNoEmpty} and not({$this->contains($this->t('Passenger name'))})] ]/td[{$passengerPos}][not(contains(normalize-space(), 'FLIGHT'))]", $root, '/^([A-Z\s\/]{5,})$/u')));

            if (count($passenger) > 0) {
                foreach ($passenger as $pax) {
                    $passengers[] = $pax;
                }
            }

            // BookingClass
            $classPos = $this->pdf->XPath->query("(following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[{$this->starts($this->t('Class'))}])[1]/preceding-sibling::td", $root)->length + 1;
            $class = $this->pdf->FindSingleNode("following-sibling::tr[{$xpathNoEmpty}][position()<10][ td[{$classPos}][{$this->starts($this->t('Class'))}] ]/following-sibling::tr[ td[{$classPos}][{$xpathNoEmpty2} and not({$this->contains($this->t('Class'))})] ][1]/td[{$classPos}]", $root, true, '/^\s*([A-Z]{1,2})\s*$/');

            if ($class) {
                $s->extra()
                    ->bookingCode($class);
            }

            // TicketNumbers
            $eTicketPos = $this->pdf->XPath->query("(following-sibling::tr[{$xpathNoEmpty}][position()<10]/td[{$this->contains($this->t('e-Ticket receipt(s)'))}])[1]/preceding-sibling::td", $root)->length + 1;
            $eTicket = $this->pdf->FindSingleNode("following-sibling::tr[{$xpathNoEmpty}][position()<10][ td[{$eTicketPos}][{$this->contains($this->t('e-Ticket receipt(s)'))}] ]/following-sibling::tr[ td[{$eTicketPos}][{$xpathNoEmpty} and not({$this->contains($this->t('e-Ticket receipt(s)'))})] ][1]/td[{$eTicketPos}]", $root, true, '/^\s*(\d{3}[- ]*\d{5,}[- ]*\d{1,2})\s*$/');

            if ($eTicket) {
                $eTickets[] = $eTicket;
            }
        }

        // Passengers
        if (count($passengers) > 0) {
            $f->general()
                ->travellers(preg_replace("/(?:\sMRS|\sMR|\sMS)$/", "", array_unique($passengers)));
        }

        $ticketsArray = [];

        if (preg_match_all("/^\s*[A-Z\s]+\/[A-Z\s]+[ ]{2,}[A-Z][ ]{20,}(\d{10,})$/m", $textPdf, $m)) {
            $ticketsArray = array_unique(array_filter($m[1]));
        }

        // TicketNumbers
        if (count($ticketsArray) > count(array_unique($eTickets))) {
            $f->setTicketNumbers($ticketsArray, false);
        } elseif (count($eTickets) > 0) {
            $f->setTicketNumbers(array_unique($eTickets), false);
        }

        if (count($passenger) === 0) {
            if (preg_match_all("/^\s*(?<pax>[[:alpha:]][-.\/'’[:alpha:] ]*[[:alpha:]])\s*(?:MR|MS|E T D N J)\s+(?<bookingCode>[A-Z])\s+.+\d+\s*kg\s*(?<ticket>\d{3}\-\d{5,})/mu", $textPdf, $m)) {
                $f->setTravellers(array_unique($m['pax']));
                $f->setTicketNumbers(array_unique($m['ticket']), false);

                $bookingCode = array_unique(array_filter($m['bookingCode']));

                if (count($bookingCode) === 1) {
                    $segments = $f->getSegments();

                    foreach ($segments as $segment) {
                        $segment->extra()
                            ->bookingCode($bookingCode[0]);
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@airfrance-klm.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Flying Blue') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Acknowledgement of receipt of your request') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0 || !isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (strpos($textPdf, 'mytrip.com/contact') !== false) {
            $this->code = 'trip';
        }

        if (strpos($textPdf, 'GoToGate') === false
            && stripos($textPdf, '.gotogate.com') === false
            && stripos($textPdf, '.gotogate.es') === false
            && stripos($textPdf, '@gotogate.com') === false
            && stripos($textPdf, 'mytrip.com') === false
            && stripos($textPdf, 'www.gotogate.hk/contact') === false
        ) {
            return false;
        }

        if ($this->assignLang($textPdf)) {
            return true;
        }

        return false;
    }

    public static function getEmailProviders()
    {
        return ['airfrance', 'trip'];
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0 || !isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
            return false;
        }

        if (!$this->tablePdf($parser)) {
            return null;
        }

        $this->assignLang();

        if (!empty($this->code)) {
            $email->setProviderCode($this->code);
        }

        $this->parsePdf($email, $textPdf);

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

    private function assignLang(?string $text = ''): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Order no']) || empty($phrases['Passenger name'])) {
                continue;
            }

            if (empty($text)
                && $this->pdf->XPath->query("//node()[{$this->contains($phrases['Order no'])}]")->length > 0
                && $this->pdf->XPath->query("//node()[{$this->contains($phrases['Passenger name'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            } elseif (!empty($text)
                && $this->strposArray($text, $phrases['Order no']) !== false
                && $this->strposArray($text, $phrases['Passenger name']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function strposArray(?string $text, $phrases, bool $reversed = false)
    {
        if (empty($text)) {
            return false;
        }

        foreach ((array) $phrases as $phrase) {
            if (!is_string($phrase)) {
                continue;
            }
            $result = $reversed ? strrpos($text, $phrase) : strpos($text, $phrase);

            if ($result !== false) {
                return $result;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // THU 26 DEC
            '/^[[:alpha:]]{2,}\s+(\d{1,2})\s+([[:alpha:]]{3,})$/u',
            // 2015-07-05
            '/^(\d{4})[-]*(\d{2})[-]*(\d{2})$/',
        ];
        $out = [
            '$1 $2',
            '$2/$3/$1',
        ];

        return preg_replace($in, $out, $text);
    }

    private function tablePdf($parser): bool
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        if (count($pdfs) === 0 || !isset($pdfs[0])) {
            return false;
        }
        $pdf = $pdfs[0];

        if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_COMPLEX)) === null) {
            return false;
        }
        $this->pdf = clone $this->http;
        $this->pdf->SetEmailBody($html);
        $html = "";

        $pages = $this->pdf->XPath->query("//div[starts-with(@id, 'page')]");

        foreach ($pages as $page) {
            $nodes = $this->pdf->XPath->query(".//p", $page);

            $cols = [];
            $grid = [];

            foreach ($nodes as $node) {
                $text = $this->pdf->FindSingleNode(".", $node);
                $top = $this->pdf->FindSingleNode("./@style", $node, true, "#top:(\d+)px;#");
                $left = $this->pdf->FindSingleNode("./@style", $node, true, "#left:(\d+)px;#");
                $cols[round($left / 10)] = round($left / 10);
                $grid[$top][round($left / 10)] = $text;
            }

            ksort($cols);

            // group rows by -8px;
            foreach ($grid as $row=>$c) {
                for ($i = $row - 5; $i < $row; $i++) {
                    if (isset($grid[$i])) {
                        foreach ($grid[$row] as $k=>$v) {
                            $grid[$i][$k] = $v;
                        }
                        unset($grid[$row]);

                        break;
                    }
                }
            }
            // group cols by -20px
            $translate = [];

            foreach ($cols as $left) {
                for ($i = $left - 3; $i < $left; $i++) {
                    if (isset($cols[$i])) {
                        $translate[$left] = $cols[$i];
                        unset($cols[$left]);

                        break;
                    }
                }
            }

            foreach ($grid as $row=>&$c) {
                foreach ($translate as $from=>$to) {
                    if (isset($c[$from])) {
                        $c[$to] = $c[$from];
                        unset($c[$from]);
                    }
                }
                ksort($c);
            }

            ksort($grid);

            $html .= "<table border='1'>";

            foreach ($grid as $row=>$c) {
                $html .= "<tr>";

                foreach ($cols as $col) {
                    $html .= "<td>" . ($c[$col] ?? "&nbsp;") . "</td>";
                }
                $html .= "</tr>";
            }
            $html .= "</table>";
        }
        $html = str_replace(['­'], ['-'], $html);
        $this->pdf->SetEmailBody($html);

        return true;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
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

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
