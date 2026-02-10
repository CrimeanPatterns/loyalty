<?php

namespace AwardWallet\Engine\annefrank\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class TicketPdf extends \TAccountChecker
{
    public $mailFiles = "annefrank/it-408118336.eml, annefrank/it-426590070.eml, annefrank/it-427217474.eml, annefrank/it-428134216.eml, annefrank/it-428134216_save.eml, annefrank/it-498475006.eml, annefrank/it-635209842.eml, annefrank/it-661030502.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $lang;
    public static $dictionary = [
        'en' => [
            // html
            // 'Your order number is:' => 'Confirmation',
            // pdf
            // 'VISIT DATE' => 'Confirmation',
            'TIME SLOT ACCESS MUSEUM' => ['TIME SLOT ACCESS MUSEUM', 'START PROGRAM', 'START & ENDING TIME PROGRAM', 'ENTRANCE TIME SLOT'],
            // 'ENTRANCE' => 'Confirmation',
            // 'TICKET' => 'Confirmation',
            // 'PRICE' => 'Confirmation',
            // 'NAME' => 'Confirmation',
            // 'A regular museum visit takes about one hour.' => 'Confirmation', // 1 hour
            'A program lasts 30 minutes, the subsequent museum visit takes about one hour.' => [
                'A program lasts 30 minutes, the subsequent museum visit takes about one hour.',
                'The program + museum visit takes          are not allowed.
about 1,5 hour.', ], // 1.5 hour
        ],
        'nl' => [
            // html
            'Your order number is:' => 'Het bestelnummer is:',
            // pdf
            'VISIT DATE'                                                                    => 'BEZOEKDATUM',
            'TIME SLOT ACCESS MUSEUM'                                                       => ['TIJDSLOT', 'BEGINTIJD PROGRAMMA'],
            'ENTRANCE'                                                                      => 'INGANG',
            'TICKET'                                                                        => 'TICKET',
            'PRICE'                                                                         => 'PRIJS',
            'NAME'                                                                          => 'PRIJS',
            'A regular museum visit takes about one hour.'                                  => 'Een museumbezoek duurt gemiddeld een uur.', // 1 hour
            'A program lasts 30 minutes, the subsequent museum visit takes about one hour.' => 'Het programma duurt een half uur, het aansluitende museumbezoek een uur.', // 1.5 hour
        ],
    ];

    private $detectFrom = "tickets@annefrank.nl";
    private $detectSubject = [
        // en
        'Anne Frank House | Tickets',
        // nl
        'Anne Frank Huis | Tickets',
    ];
    private $detectBody = [
        'en' => [
            'Tickets for a museum visit',
            'Tickets + introductory program',
            ' The program + museum visit takes',
        ],
        'nl' => [
            'Tickets voor een museumbezoek',
            'Tickets + inleidend programma',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]annefrank\.nl$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                return true;
            }
        }

        return false;
    }

    public function detectPdf($text)
    {
        // detect provider
        if ($this->http->XPath->query("//node()[{$this->contains(['@annefrank.nl', 'this e-mail from the Anne Frank House', 'tickets voor het Anne Frank Huis vinden'])}]")->length === 0
            && $this->http->XPath->query("//text()[contains(normalize-space(), 'Anne Frank House')]")->length === 0
            && $this->containsText($text, ['museum is at Westermarkt 20', 'museum is op de Westermarkt 20']) === false) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->containsText($text, $detectBody) !== false) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf), false);

            if ($this->detectPdf($text) == true) {
                $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your order number is:'))}]",
                    null, true, "/{$this->opt($this->t('Your order number is:'))}\s*([A-Z\d]{5,})\.\s*$/");

                if (empty($conf)) {
                    $conf = $this->re("/Ticket\-([A-Z\d]{10,})\.pdf/", $this->getAttachmentName($parser, $pdf));
                }

                $email->ota()
                    ->confirmation($conf);
                $this->parseEmailPdf($email, $text);
            }
        }

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

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $tickets = $this->split("/(\n(?: {25,}.*\d.*\n)? *{$this->opt($this->t('VISIT DATE'))}(?: {3,}|\n))/", "\n\n" . $textPdf);

        foreach ($tickets as $ticketText) {
            $eventDate = null;

            $date = $this->re("/\n((?: {25,}.*\d.*\n)? *{$this->opt($this->t('VISIT DATE'))}(?: {3,}|\n {25,}).+)/", $ticketText);
            $date = trim(preg_replace("/(\s*{$this->opt($this->t('VISIT DATE'))}\s+|\s+)/", ' ', $date));
            $time = $this->re("/\n *{$this->opt($this->t('TIME SLOT ACCESS MUSEUM'))} {3,}(\d+:\d+)(?: -.+|\n)/", $ticketText);

            $typeText = $this->re("/\n *{$this->opt($this->t('TICKET'))} {3,}(.+)/", $ticketText);
            $type = 'adult';

            if (preg_match("/^\s*(\d+)\s*-\s*(\d+)/", $typeText, $m)
                && $m[1] <= 18 && $m[2] <= 18
            ) {
                $type = 'kid';
            }

            $price = PriceHelper::parse(
                $this->re("/\n *{$this->opt($this->t('PRICE'))} {3,}€ ?(\d[\d,. ]*)\n/", $ticketText), 'EUR');
            $currency = 'EUR';

            $travellerName = $this->re("/\n *{$this->opt($this->t('NAME'))} {3,}(.+)/", $ticketText);

            if (!empty($date) && !empty($time)) {
                $eventDate = $this->normalizeDate($date . ', ' . $time);
            } else {
                $this->logger->debug('parsing error: datetime');
                $email->add()->event();

                continue;
            }

            $its = $email->getItineraries();
            $foundTicket = false;

            foreach ($its as $it) {
                /** @var \AwardWallet\Schema\Parser\Common\Event $it */
                if ($it->getStartDate() == $eventDate) {
                    $foundTicket = true;

                    if ($type == 'adult') {
                        $it->booked()
                            ->guests(1 + ($it->getGuestCount() ?? 0));
                    } elseif ($type == 'kid') {
                        $it->booked()
                            ->kids(1 + ($it->getKidsCount() ?? 0));
                    }

                    $it->price()
                        ->total($price + $it->getPrice()->getTotal());

                    if (!in_array($travellerName, array_column($it->getTravellers(), 0))) {
                        $it->general()
                            ->traveller($travellerName, true);
                    }
                }
            }

            if ($foundTicket === true) {
                continue;
            }

            $event = $email->add()->event();
            $event
                ->type()->event();

            // General
            $event->general()
                ->noConfirmation()
                ->traveller($travellerName, true);

            $notes = [];
            $notes[] = trim($this->re("/\n *({$this->opt($this->t('ENTRANCE'))} {3,}.+)/", $ticketText));
            $notes[] = trim($this->re("/\n *{$this->opt($this->t('NAME'))} {3,}.+\n\n\n([\s\S]+?)\n *.+ Westermarkt 20/", $ticketText));
            $notes = implode('. ', preg_replace('/\s+/', ' ', array_filter($notes)));
            $event->general()
                ->notes($notes);

            // Place
            $event->place()
                ->name('Anne Frank House')
                ->address('Westermarkt 20, 1016 GV Amsterdam, Netherlands')
            ;

            // Booked
            $event->booked()
                ->start($eventDate);

            if ($eventDate && preg_match("/" . str_replace('', '\s+', $this->opt($this->t('A regular museum visit takes about one hour.'))) . "/", $ticketText)) {
                $event->booked()
                    ->end(strtotime("+ 1 hour", $eventDate));
            }

            if ($eventDate
                && (preg_match("/" . str_replace('', '\s+', $this->opt($this->t('A program lasts 30 minutes, the subsequent museum visit takes about one hour.'))) . "/", $ticketText)
                || preg_match("/" . str_replace('', '\s+', $this->opt($this->t('A program lasts 30 minutes, the subsequent museum visit takes about one hour.'))) . "/", $ticketText))) {
                $event->booked()
                    ->end(strtotime("+ 1 hour 30 min", $eventDate));
            }

            if ($type == 'adult') {
                $event->booked()
                    ->guests(1);
            } elseif ($type == 'kid') {
                $event->booked()
                    ->kids(1);
            }

            // Price
            $event->price()
                ->total($price)
                ->currency($currency);
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    // additional methods

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

    private function normalizeDate(?string $date): ?int
    {
        //$this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            // 12 March 2023, 14:30
            // 12 Ma rch 2023, 14:30
            '/^\s*(\d+)\s+([[:alpha:]]+(?: ?[[:alpha:]]+)*)\s+(\d{4}),\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
            // 26/03/2023
            '/^\s*(\d{1,2})\/(\d{2})\/(\d{4}),\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            '$1.$2.$3, $4',
        ];

        if (preg_match('/^\s*(\d+\s+)([[:alpha:]]+(?: ?[[:alpha:]]+)*)(\s+\d{4}.*)\s*$/ui', $date, $m)) {
            $date = $m[1] . str_replace(' ', '', $m[2]) . $m[3];
        }
        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        // $this->logger->debug('date end = ' . print_r($date, true));

        return strtotime($date);
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function getAttachmentName(\PlancakeEmailParser $parser, $pdf)
    {
        $header = $parser->getAttachmentHeader($pdf, 'Content-Type');

        if (preg_match('/name=[\"\']*(.+\.pdf)[\'\"]*/i', $header, $matches)) {
            return $matches[1];
        }

        return false;
    }
}
