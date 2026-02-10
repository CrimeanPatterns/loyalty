<?php

namespace AwardWallet\Engine\croatia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ElectronTicket extends \TAccountChecker
{
    public $mailFiles = "croatia/it-110706629.eml, croatia/it-2674485.eml, croatia/it-2700567.eml, croatia/it-307127583.eml";
    public $reFrom = "@croatiaairlines.hr";
    public $reSubject = [
        "en"=> "croatiaairlines.com - FlyOnLine Confirmation",
        "de"=> "FlyOnLine Electronic Ticket",
        "hr"=> "Croatia Airlines - Elektronička karta/dokument",
    ];
    public $reBody = 'www.croatiaairlines.com';
    public $reBody2 = [
        "en"=> "Your itinerary",
        "de"=> "Angaben zum Reisenden",
        "hr"=> "Vaš plan leta",
    ];

    public static $dictionary = [
        "en" => [
            'Booking reservation number:' => ['Booking reservation number:', 'Booking reference:'],
            //            "Trip status:" => '',
            //            'Payment information' => '',
            //            'Total for all travellers' => '',
            //            'Traveller information' => '',
            //            'Name' => '',
            //            "ETIX" => '',
            //            "Frequent Flyer" => '',
            'Your Itinerary' => 'Your Itinerary',
            //            'Date' => '',
            //            "Departure" => '',
            //            'Flight' => '',
            //            'Fare Families' => '',
            //            'Documents' => '',
            //            'Electronic Ticket' => '',
            'Croatia Airlines Internet Sales' => 'Croatia Airlines Internet Sales',
        ],
        "de" => [
            "Booking reservation number:"=> "Reservierungsnummer:",
            "Trip status:"               => "Reisestatus:",
            //            'Payment information' => '',
            "Total for all travellers"   => "Insgesamt für alle Reisenden",
            //            'Traveller information' => '',
            "Name"                       => "Name",
            //            "ETIX" => '',
            //            "Frequent Flyer" => '',
            //            'Your Itinerary' => '',
            //            'Date' => '',
            "Departure"                  => "Abreise",
            //            'Flight' => '',
            //            'Fare Families' => '',
            //            'Documents' => '',
            //            'Electronic Ticket' => '',
            //            'Croatia Airlines Internet Sales' => '',
        ],
        "hr" => [
            "Booking reservation number:"=> "Šifra rezervacije:",
            "Trip status:"               => "Status rezervacije:",
            'Payment information'        => 'Podaci o plaćanju',
            "Total for all travellers"   => "ukupno za sve putnike",
            "Traveller information"      => "Podaci o putniku",
            "Name"                       => "Ime",
            //            "ETIX" => '',
            "Frequent Flyer"                  => 'Frequent Flyer',
            'Your Itinerary'                  => 'Vaš plan leta',
            'Date'                            => 'Datum',
            "Departure"                       => "Odlazak",
            'Flight'                          => 'Let',
            'Fare Families'                   => 'Cjenovni razredi',
            'Documents'                       => 'Dokumenti',
            'Electronic Ticket'               => 'Elektronička karta',
            'Croatia Airlines Internet Sales' => 'Internet prodaja',
        ],
    ];
    public $lang = "en";
    private $date;

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->nextText($this->t("Booking reservation number:")))
            ->travellers($this->http->FindNodes("//text()[{$this->eq($this->t('Traveller information'))}]/following::text()[{$this->eq($this->t('Name'))}][1]/ancestor::tr[1]/following-sibling::tr/td[1]"))
            ->status($this->nextText($this->t("Trip status:")));

        $tickets = $this->http->FindNodes("//text()[" . $this->eq($this->t("ETIX")) . "]/ancestor::tr[1]/following-sibling::tr/td[2]");

        if (count($tickets) == 0) {
            $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Documents'))}]/following::text()[{$this->eq($this->t('Flight'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Electronic Ticket'))}]/ancestor::tr[1]/descendant::td[3]");
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers($tickets, false);
        }

        $accounts = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Frequent Flyer")) . "]/ancestor::tr[1]/following-sibling::tr/td[last()]"));

        if (count($accounts) > 0) {
            $f->setAccountNumbers($accounts, false);
        }

        $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Total for all travellers")) . "]/ancestor::td[1]/preceding-sibling::td[1]"));

        if (empty($total['Total'])) {
            $total = $this->getTotalCurrency($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Total for all travellers")) . "]/ancestor::*[1]"));
        }
        $f->price()
            ->total($total['Total'])
            ->currency($total['Currency']);

        $codesRoute = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Documents'))}]/following::text()[{$this->eq($this->t('Flight'))}]/ancestor::table[1]/descendant::text()[{$this->eq($this->t('Electronic Ticket'))}])[1]/ancestor::tr[1]/descendant::td[2]");
        $codes = array_filter(explode('-', $codesRoute));
        $segmentsCodes = [];

        if (count($codes) > 1) {
            for ($i = 0; $i < count($codes) - 1; $i++) {
                $segmentsCodes[] = [$codes[$i], $codes[$i + 1] ?? null];
            }
        }

        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if (count($segmentsCodes) !== $nodes->length) {
            $segmentsCodes = [];
        }

        foreach ($nodes as $i => $root) {
            $s = $f->addSegment();
            $date = strtotime($this->normalizeDate($this->http->findSingleNode("./td[1]", $root)));

            $s->airline()
                ->number($this->http->FindSingleNode("./td[8]", $root, true, "#^\w{2}(\d+)$#"))
                ->name($this->http->FindSingleNode("./td[8]", $root, true, "#^(\w{2})\d+$#"));

            $s->departure()
                ->name($this->http->FindSingleNode("./td[3]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root)), $date));

            $depTerminal = trim($this->http->FindSingleNode("./td[4]", $root), ' -');

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            $s->arrival()
                ->name($this->http->FindSingleNode("./td[5]", $root))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("./td[6]", $root)), $date));

            if (isset($segmentsCodes[$i])) {
                $s->departure()
                    ->code($segmentsCodes[$i][0]);
                $s->arrival()
                    ->code($segmentsCodes[$i][1]);
            } else {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }

            $seats = array_filter(explode(",", trim($this->http->FindSingleNode("./td[7]", $root), '- ')));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }
        }
    }

    public function parsePDF(Email $email, $text)
    {
        $f = $email->add()->flight();

        $f->general()
            ->status($this->re("/{$this->opt($this->t('Trip status:'))}\s*(\w+)/u", $text))
            ->confirmation($this->re("/{$this->opt($this->t('Booking reservation number:'))}\s*([A-Z\d]{6,})\b/u", $text),
                trim($this->re("/({$this->opt($this->t('Booking reservation number:'))})\s*[A-Z\d]{6,}/u", $text), ':'));

        if (preg_match("/{$this->opt($this->t('Payment information'))}\n([\d\.\,]+)\s*([A-Z]{3})\s*{$this->opt($this->t('Total for all travellers'))}/", $text, $m)) {
            $f->price()
                ->total(PriceHelper::cost($m[1], '.', ','))
                ->currency($m[2]);
        }

        $travellerText = $this->re("/{$this->opt($this->t('Traveller information'))}\s*{$this->opt($this->t('Name'))}\s*{$this->opt($this->t('Frequent Flyer'))}\n+(.+)\n+{$this->opt($this->t('Your Itinerary'))}/us", $text);

        if (preg_match_all("/(?:^|\n) {0,10}([[:alpha:]][-.'[:alpha:] ]*[[:alpha:]])(?= {3,}|\n)/", $travellerText, $m)) {
            $f->general()
                ->travellers($m[1], true);
        }

        if (preg_match_all("/{$this->opt($this->t('Electronic Ticket'))}\s*[A-Z\-]+\s*(\d{10,})/", $text, $m)) {
            $f->setTicketNumbers($m[1], false);
        }

        $codesRoute = $this->re("/{$this->opt($this->t('Electronic Ticket'))}\s*([A-Z\-]+)\s*\d{10,}/", $text);
        $codes = array_filter(explode('-', trim($codesRoute)));
        $segmentsCodes = [];

        if (count($codes) > 1) {
            for ($i = 0; $i < count($codes) - 1; $i++) {
                $segmentsCodes[] = [$codes[$i], $codes[$i + 1] ?? null];
            }
        }

        $segmentsText = $this->re("/{$this->opt($this->t('Your Itinerary'))}\n+{$this->opt($this->t('Date'))}.+{$this->opt($this->t('Flight'))} *\n([\s\S]+?)\n+{$this->opt($this->t('Fare Families'))}/", $text);
        $segments = $this->splitText($segmentsText, "/^(\d{1,2}[A-Z]{1,}[ ]{2,}\d{2,4}[ ]{2,})/m", true);

        if (count($segmentsCodes) !== count($segments)) {
            $segmentsCodes = [];
        }

        foreach ($segments as $i => $segment) {
            $s = $f->addSegment();

            $segTable = $this->splitCols($segment);

            $s->airline()
                ->name($this->re("/([A-Z][A-Z\d]|[A-Z\d][A-Z])/", $segTable[7]))
                ->number($this->re("/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)/", $segTable[7]));

            $depDate = trim($segTable[0]);
            $depTime = trim($segTable[1]);
            $depName = trim(str_replace("\n", " ", $segTable[2]));
            $s->departure()
                ->name($depName)
                ->date(strtotime($this->normalizeDate($depDate . ', ' . $depTime)));

            $arrName = trim($segTable[4]);
            $arrTime = trim($segTable[5]);

            $s->arrival()
                ->name($arrName)
                ->date(strtotime($this->normalizeDate($depDate . ', ' . $arrTime)));

            $seats = str_replace("-", "", trim($segTable[6]));

            if (!empty($seats)) {
                $s->setSeats(explode(",", $seats));
            }

            $depTerminal = trim(str_replace("-", "", trim($segTable[3])));

            if (!empty($depTerminal)) {
                $s->departure()
                    ->terminal($depTerminal);
            }

            if (isset($segmentsCodes[$i])) {
                $s->departure()
                    ->code($segmentsCodes[$i][0]);
                $s->arrival()
                    ->code($segmentsCodes[$i][1]);
            } else {
                $s->departure()
                    ->noCode();
                $s->arrival()
                    ->noCode();
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        $pdfs = $parser->searchAttachmentByName('.*\.pdf');

        foreach ($pdfs as $pdf) {
            if ($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) {
                foreach (self::$dictionary as $dict) {
                    if (stripos($text, 'www.croatiaairlines.com') !== false
                        && !empty($dict['Croatia Airlines Internet Sales']) && !empty($dict['Your Itinerary'])
                        && $this->containsText($text, $dict['Croatia Airlines Internet Sales']) !== false
                        && $this->containsText($text, $dict['Your Itinerary']) !== false) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $type = '';

        if ($this->http->XPath->query("//a[contains(@href, 'www.croatiaairlines.com')]")->length > 0) {
            $type = 'Html';
            $this->parseHtml($email);
        } else {
            $pdfs = $parser->searchAttachmentByName('.*\.pdf');

            foreach ($pdfs as $pdf) {
                if ($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) {
                    foreach (self::$dictionary as $dict) {
                        if (stripos($text, 'www.croatiaairlines.com') !== false
                            && !empty($dict['Croatia Airlines Internet Sales']) && !empty($dict['Your Itinerary'])
                            && $this->containsText($text, $dict['Croatia Airlines Internet Sales']) !== false
                            && $this->containsText($text, $dict['Your Itinerary']) !== false
                        ) {
                            $type = 'Pdf';
                            $this->parsePDF($email, $text);
                        }
                    }
                }
            }
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . $type . ucfirst($this->lang));

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

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\s\d]+)$#", //02MAY
            "#^(\d{2})(\d{2})$#", //1420
            "#^(\d+)\s*(\w+)\,\s*(\d{1,2})(\d{2})$#", //20 SEP, 1240
        ];
        $out = [
            "$1 $2 $year",
            "$1:$2",
            "$1 $2 $year, $3:$4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];

            if (in_array($cur, ['HRK'])) {
                $tot = PriceHelper::parse($m['t'], $cur);
            } else {
                $tot = PriceHelper::parse($m['t'], $cur);
            }
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (strpos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && strpos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

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
        $head = array_filter(array_map('trim', explode("|", preg_replace("/\s{2,}/", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }
}
