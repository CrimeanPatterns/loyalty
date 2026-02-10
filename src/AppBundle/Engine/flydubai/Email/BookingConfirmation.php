<?php

namespace AwardWallet\Engine\flydubai\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "flydubai/it-141078271.eml, flydubai/it-142898237.eml, flydubai/it-144015183.eml, flydubai/it-268653727.eml, flydubai/it-268657340.eml, flydubai/it-74410360.eml, flydubai/it-75963572.eml";
    public $subjects = [
        'Booking confirmation #',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
            'Departure from' => ['Departure from', 'Flight from'],
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@flydubai.com') !== false) {
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
        if ($this->http->XPath->query("//text()[contains(normalize-space(), 'flydubai booking reference')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Your booking is'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Departure from'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Passenger details'))}]")->length > 0
        ) {
            return true;
        }

        $pdfs = $parser->searchAttachmentByName(".*\.pdf");

        foreach ($pdfs as $pdf) {
            if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                return null;
            }

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, '@flydubai.com') !== false;
    }

    public function ParseEmail(Email $email)
    {
        $timeFotmat = 'translate(normalize-space(), "0123456789", "dddddddddd") = "dd:dd"';
        $xpath = "//text()[" . $timeFotmat . "]/ancestor::*[count(.//text()[" . $timeFotmat . "]) = 2][following-sibling::*[normalize-space()][1]][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            return false;
        }

        $f = $email->add()->flight();

        // General
        $confs = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'flydubai booking reference')][1]/preceding::text()[normalize-space()][1]",
            null, "/^\s*([A-Z\d]{5,7})\s*$/")));

        foreach ($confs as $conf) {
            $f->general()
                ->confirmation($conf, 'flydubai booking reference');
        }
        // no example for 2 or more airline
        $airlineConfsText = array_unique(array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Partner airline booking reference')][1]/preceding::text()[normalize-space()][1]",
            null, "/^\s*([A-Z\d ]{5,})\s*$/")));
        $airlineConfs = [];

        foreach ($airlineConfsText as $conf) {
            if (preg_match("/^\s*([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+([A-Z\d]{5,7})\s*$/", $conf, $m)) {
                $airlineConfs[$m[1]] = $m[2];
            }
        }

        $f->general()
            ->travellers(array_filter(array_unique($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Passenger details')]/ancestor::table[1]/descendant::text()[" . $this->contains(['Adult', 'Child', 'Infant']) . "]/preceding::text()[normalize-space()][1]",
                null, "/^\s*(?:[[:alpha:]]{1,4}\.\s+)?(.+)/"))), true)
            ->date(strtotime($this->http->FindSingleNode("(//text()[starts-with(normalize-space(), 'Booked on')])[last()]", null, true, "/{$this->opt($this->t('Booked on'))}[\s:]*(.+)/u")));

        // Issued
        $tickets = array_unique(array_filter($this->http->FindNodes("//text()[contains(normalize-space(), 'e-ticket number:')]/following::text()[normalize-space()][1]", null, "/^\s*(\d{10,})\s*$/")));

        if (count($tickets) > 0) {
            $f->issued()
                ->tickets($tickets, false);
        }

        // Program
        $accounts = array_unique(array_filter($this->http->FindNodes("//text()[normalize-space() = 'Skywards membership number:']/following::text()[normalize-space()][1]", null, "/^\s*([A-Z]-\d{5,})\s*$/")));

        if (count($accounts) > 0) {
            $f->program()
                ->accounts($accounts, false);
        }

        // Price
        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Booking total') and contains(normalize-space(), '.')]", null, true, "/Booking total[\s:]+(.+)/");

        if (empty($total)) {
            $total = $this->http->FindSingleNode("//td[not(.//td)][starts-with(normalize-space(), 'Booking total')]",
                null, true, "/Booking total[\s:]+(.+)/");
        }

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
        ) {
            $f->price()
                ->total((float) PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency']);
        }

        $spentAwards = array_filter($this->http->FindNodes("//td[not(.//td)][starts-with(normalize-space(), 'Skywards membership number:')]/following-sibling::td[normalize-space()][1]", null, "/^\s*(\d+) Skywards Miles\s*$/"));

        if (empty($spentAwards)) {
            $spentAwards = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(), 'Skywards membership number:')]/preceding::td[normalize-space()][1]", null, "/^\s*(\d+) Skywards Miles\s*$/"));
        }

        if (!empty($spentAwards)) {
            $f->price()
                ->spentAwards(array_sum($spentAwards) . ' Skywards Miles');
        }

        // Segments
        foreach ($nodes as $root) {
            $s = $f->addSegment();

            // Airline
            $flightNumber = '';
            $flightsText = implode("\n", $this->http->FindNodes("preceding-sibling::*[normalize-space()][2]/descendant::td[not(.//td)]", $root));

            if (preg_match("/Stopover in/", $flightsText) && !empty($flightsNumbers)) {
                $flightNumber = array_shift($flightsNumbers);
            } else {
                $flightText = preg_replace("/.*\(\s*Flight\s*(.+?)\s*\).*/", "$1", $flightsText);
                $flightsNumbers = explode("/", $flightText);
                $flightNumber = array_shift($flightsNumbers);
            }

            if (preg_match("/\n([[:alpha:] ]+) Class\s*$/", $flightsText, $m)) {
                $s->extra()
                    ->cabin($m[1]);
            }

            if (preg_match("/(^|\n|\()\s*(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s*(?:$|\n|\))/", $flightNumber, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn']);

                if (!empty($airlineConfs[$m['al']])) {
                    $s->airline()
                        ->confirmation($airlineConfs[$m['al']]);
                }
            }

            $depDate = $arrDate = $depTime = $arrTime = null;
            $dates = $this->http->FindNodes("preceding-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()][not(contains(., 'operated by'))]", $root);

            if (count($dates) === 2) {
                $depDate = $dates[0];
                $arrDate = $dates[1];
            }
            $depCode = $arrCode = $depName = $arrName = null;

            $info = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()]", $root));
            $regexp = "/^\s*(?:[^:]+?\s+)?(?<dTime>\d{2}:\d{2}(?:\s*[ap]m)?)\s*\n\s*(?<dName>.+)\s*\n\s*(?<duration>(?:\s*\d+ ?(?:h|min))+)(?<stop>\s*.+)?"
                . "\s*\n\s*(?<aTime>\d{2}:\d{2}(?:\s*[ap]m)?)\s*\n\s*(?<aName>.+)\s*$/";

            if (preg_match($regexp, $info, $m)) {
                $depTime = $m['dTime'];
                $arrTime = $m['aTime'];

                $depName = $m['dName'];

                if (preg_match("/^(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $m['dName'], $mat)) {
                    $depName = $mat[1];
                    $depCode = $mat[2];
                }
                $arrName = $m['aName'];

                if (preg_match("/^(.+?)\s*\(\s*([A-Z]{3})\s*\)\s*$/", $m['aName'], $mat)) {
                    $arrName = $mat[1];
                    $arrCode = $mat[2];
                }

                $s->extra()
                    ->duration(trim($m['duration']));

                if (preg_match("/non[\s\W]?stop/ui", $m['stop'])) {
                    $s->extra()->stops(0);
                }
            }

            $name2Pos = 1;

            if (empty($depCode)) {
                $codes = $this->http->FindNodes("following-sibling::*[normalize-space()][1]/descendant::td[not(.//td)][normalize-space()]", $root);

                if (count($codes) == 2 && preg_match("/^\s*([A-Z]{3})\s*$/", $codes[0], $mat)) {
                    $depCode = $codes[0];
                    $arrCode = $codes[1];
                    $name2Pos = 2;
                }
            }

            $depName2 = implode("\n", $this->http->FindNodes("following-sibling::*[normalize-space()][" . $name2Pos . "]/descendant::tr[not(.//tr)][normalize-space()][count(*) > 1]/*[1]", $root));

            if (preg_match("/^\s*(.+?)\s*(?:\n|,)([^,]*\bTerminal\b.*)\s*$/i", trim($depName2), $m)) {
                $depName2 = trim($m[1]);
                $s->departure()
                    ->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m[2])));
            }
            $depName = $depName ? $depName . ', ' . $depName2 : $depName2;

            $arrName2 = implode("\n", $this->http->FindNodes("following-sibling::*[normalize-space()][" . $name2Pos . "]/descendant::tr[not(.//tr)][normalize-space()][count(*) > 1]/*[last()]", $root));

            if (preg_match("/^\s*(.+?)\s*(?:\n|,)([^,]*\bTerminal\b.*)\s*$/i", trim($arrName2), $m)) {
                $arrName2 = trim($m[1]);
                $s->arrival()
                    ->terminal(trim(preg_replace("/\s*\bTerminal\b\s*/i", ' ', $m[2])));
            }
            $arrName = $arrName ? $arrName . ', ' . $arrName2 : $arrName2;

            $s->departure()
                ->code($depCode)
                ->name($depName)
                ->date((!empty($depDate) && !empty($depTime)) ? strtotime($depDate . ', ' . $depTime) : null)
                ->strict()
            ;
            $s->arrival()
                ->code($arrCode)
                ->name($arrName)
                ->date((!empty($arrDate) && !empty($arrTime)) ? strtotime($arrDate . ', ' . $arrTime) : null)
                ->strict()
            ;

            foreach ($f->getSegments() as $key => $seg) {
                if ($s->getId() !== $seg->getId() && serialize($s->toArray()) == serialize($seg->toArray())) {
                    $f->removeSegment($s);

                    break;
                }
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $type = 'Html';
        $this->ParseEmail($email);

        if (count($email->getItineraries()) === 0) {
            $pdfs = $parser->searchAttachmentByName(".*\.pdf");

            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) === null) {
                    continue;
                }

                if ($this->detectPdf($text) == true) {
                    $type = 'Pdf';
                    $this->parseEmailPdf($email, $text);
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

        return $email;
    }

    public function detectPdf($text)
    {
        if ($this->strposAll($text, ['© flydubai 20', '@flydubai.com']) === false) {
            return false;
        }

        if ($this->strposAll($text, $this->t('flydubai booking reference')) !== false
            && $this->strposAll($text, $this->t('Your booking is')) !== false
            && $this->strposAll($text, $this->t('Departure from')) !== false
            && $this->strposAll($text, $this->t('Passenger details')) !== false
        ) {
            return true;
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $textPdf = preg_replace('/\n +© ?flydubai 20.+/', '', $textPdf);
        $email->obtainTravelAgency();

        $conf = $this->re("/.{40} {3,}([A-Z\d]{5,7})\n.{40,} {3,}flydubai booking reference\n/", $textPdf);

        if (!in_array($conf, array_column($email->getTravelAgency()->getConfirmationNumbers(), 0))) {
            $email->ota()
                ->confirmation($conf);
        }

        if (!empty($email->getItineraries()[0])) {
            $f = $email->getItineraries()[0];
        } else {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();
        }

        $passText = $this->re("/Passenger details.*\n(.+\n.+)/", $textPdf);
        $ptable = $this->createTable($passText, $this->rowColumnPositions($this->inOneRow($passText)));

        $traveller = '';

        if (preg_match("/^(.+)/", $ptable[0] ?? '', $m)) {
            $traveller = $m[1];
            $traveller = preg_replace("/^\s*(Mrs|Mr|Ms|Mstr|Miss|Dr)[.\s]+/i", '', $traveller);
        }

        if (!in_array($traveller, array_column($f->getTravellers(), 0))) {
            $f->general()
                ->traveller($traveller);
        }

        if (preg_match("/^\s*(.{8,})\ne-ticket number/", $ptable[1] ?? '', $m)
            && !in_array(trim($m[1]), array_column($f->getTicketNumbers(), 0))
        ) {
            $f->issued()
                ->ticket(trim($m[1]), false);
        }

        if ((preg_match("/^\s*(?:[A-Z]{2})? *(\d{5,})(?: *-.*)?\n.*membership number/", $ptable[1] ?? '', $m)
            || preg_match("/^\s*(?:[A-Z]{2})? *(\d{5,})(?: *-.*)?\n.*membership number/", $ptable[2] ?? '', $m))
            && !in_array(trim($m[1]), array_column($f->getAccountNumbers(), 0))
        ) {
            $f->program()
                ->account(trim($m[1]), false);
        }

        $routes = $this->split("/\n *(Departure from|Return from|Flight from)/", $textPdf);

        $routes[count($routes) - 1] = preg_replace('/\n {0,10}(?:Payment reference|Passenger total)[\s\S]+/', '', $routes[count($routes) - 1]);

        foreach ($routes as $route) {
            $flightText = $this->re("/^.*\( ?Flight\s*([^)]+) *\)/", $route);

            $flightsNumbers = explode("/", $flightText);

            $length = strlen($this->re("/^( *\d{1,2}:\d{2}.* {3,}\d{1,2}:\d{2})/m", $route));

            if ($length < 20) {
                $length = strlen($this->re("/^(.{30,} {3,}\d{1,2}:\d{2})/m", $route));
            }

            if ($length < 20) {
                return false;
            }
            $length += 3;

            $dopInfo = implode("\n", $this->res("/^.{{$length},}? {3,}(\S.*)/m", $route));
            $route = preg_replace("/^(.{{$length},}?) {3,}\S.*/m", '$1', $route);
            $segments = $this->split("/\n((?:.*operated by.*\n+(?: {30,}.*\n+){0,3}?)?.+\n+(?: {30,}.+\n+){0,2}? *\d{1,2}:\d{2}.* {3,}\d{1,2}:\d{2})/", $route);

            if (count($flightsNumbers) !== count($segments)) {
                $flightsNumbers = [];
            }

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                // Airline
                $airline = array_shift($flightsNumbers);

                if (preg_match("/^\s*(?<al>[A-Z\d]{2})\s*(?<fn>\d{1,5})\s*$/", $airline, $m)) {
                    $s->airline()
                        ->name($m['al'])
                        ->number($m['fn']);
                }

                $segment = str_replace(" -", '  ', $segment);
                $depart = "\n" . implode("\n", $this->res("/^ {0,15}(\w.+?)(?: {3,}.*)?$/m", $segment)) . "\n";
                $arrive = "\n" . implode("\n", $this->res("/^.{20,} {3,}(\S.+)$/m", $segment)) . "\n";

                $re = "/\n(?<date>.+)\n(?:[-+] *\d+.*\n)?\s*(?<time>\d{1,2}:\d{2})\s*\n(?<code>[A-Z]{3})\n(?<name>.+)\n(?i)(?<terminal>.*\bterminal\b.*)?/";
                // Departure
                if (preg_match($re, $depart, $m)) {
                    $s->departure()
                        ->date(strtotime($m['date'] . ', ' . $m['time']))
                        ->code($m['code'])
                        ->name($m['name'])
                        ->terminal(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m['terminal'] ?? '')), true, true);
                }
                // Arrival
                $arrive = preg_replace("/^\s*(?:((?: ?\d+ ?(?:h|min))+)|non[\- ]?stop)\s*$/im", '', $arrive);

                if (preg_match($re, $arrive, $m)) {
                    $s->arrival()
                        ->date(strtotime($m['date'] . ', ' . $m['time']))
                        ->code($m['code'])
                        ->name($m['name'])
                        ->terminal(preg_replace("/\s*\bterminal\b\s*/i", '', trim($m['terminal'] ?? '')), true, true);
                }

                // Extra
                $s->extra()
                    ->duration($this->re("/ {3,}((?: ?\d+ ?(?:h|min))+) {3,}/", $segment));
                $prefix = '';

                if (count($segments) > 1) {
                    if (!empty($s->getAirlineName()) && !empty($s->getFlightNumber())) {
                        $prefix = $s->getAirlineName() . ' ?' . $s->getFlightNumber() . ' +';
                    } else {
                        $prefix = 'false';
                    }
                }

                if (preg_match("/^(.*\b(?:Class|Economy|Business)\b.*)/i", $dopInfo, $m)) {
                    $s->extra()
                        ->cabin($m[1]);
                }

                if (preg_match("/^{$prefix}(.*) meal *\(included\)\s*/m", $dopInfo, $m)) {
                    $s->extra()
                        ->meal($m[1]);
                }

                if (preg_match("/^{$prefix}(\d{1,3}[A-Z]) *\(.+\)\s*/m", $dopInfo, $m)) {
                    $s->extra()
                        ->seat($m[1]);
                }
            }
        }

        if ($this->strposAll($textPdf, 'Booking total') !== false) {
            $price = $this->re("/ {2,}Booking total +(.+?)(?:\n| {2,})/", $textPdf);

            if (preg_match("/^\s*(?<currency>[A-Z]{3}) *(?<amount>\d[\d,. ]*)\s*$/", $price, $m)
                && ($amount = PriceHelper::parse($m['amount'], $m['currency'])) !== null
            ) {
                if (!$f->getPrice()) {
                    $f->price()
                        ->total($amount)
                        ->currency($m['currency']);
                } elseif ($f->getPrice()->getTotal() !== null && $f->getPrice()->getCurrencyCode() === $m['currency']) {
                    $f->price()
                        ->total($f->getPrice()->getTotal() + $amount)
                        ->currency($m['currency']);
                } else {
                    $f->price()
                        ->total(null);
                }
            } else {
                $f->price()
                    ->total(null)
                ;
            }

            $miles = $this->re("/ {2,}Booking total +.*[\s\S]+membership number: +(\d+ \w+ miles)\n/i", $textPdf);

            if (!empty($miles)) {
                if ($f->getPrice() && !empty($f->getPrice()->getSpentAwards())) {
                    $f->price()
                        ->spentAwards($f->getPrice()->getSpentAwards() . ' + ' . $miles);
                } else {
                    $f->price()
                        ->spentAwards($miles);
                }
            }
        }

        return $email;
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
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
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function strposAll($text, $needle)
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                $pos = strpos($text, $n);

                if ($pos !== false) {
                    return $pos;
                }
            }
        } elseif (is_string($needle)) {
            return strpos($text, $needle);
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

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function columnPositions($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColumnPositions($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (!isset($prev) || $prev < 0) {
                $prev = $i - 1;
            }

            if (isset($pos[$i], $pos[$prev])) {
                if ($pos[$i] - $pos[$prev] < $correct) {
                    unset($pos[$i]);
                } else {
                    $prev = $i;
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }

    private function createTable(?string $text, $pos = []): array
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColumnPositions($rows[0]);
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

    private function rowColumnPositions(?string $row): array
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function inOneRow($text)
    {
        $textRows = array_filter(explode("\n", $text));
        $length = [];

        foreach ($textRows as $key => $row) {
            $length[] = mb_strlen($row);
        }
        $length = max($length);
        $oneRow = '';

        for ($l = 0; $l < $length; $l++) {
            $notspace = false;

            foreach ($textRows as $key => $row) {
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
}
