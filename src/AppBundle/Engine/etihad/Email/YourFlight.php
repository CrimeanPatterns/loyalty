<?php

namespace AwardWallet\Engine\etihad\Email;

use AwardWallet\Schema\Parser\Email\Email;

class YourFlight extends \TAccountChecker
{
    public $mailFiles = "etihad/it-419101182.eml, etihad/it-419368004.eml, etihad/it-420152872.eml, etihad/it-681320762-fr.eml";

    public $lang = 'en';
    public static $dictionary = [
        'fr' => [
            'confNumber' => ['Référence de la réservation:', 'Référence de la réservation :'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            // 'Direct' => '',
            // 'Non-stop' => '',
            'From'     => 'De',
            'Terminal' => ['Aerogare', 'Terminal'],
            // 'Operated by:' => '',
            'Cabin:'    => 'Cabine:',
            'Aircraft:' => 'Appareil:',
            // 'Seat' => '',
            // 'guest-single' => [''],
            // 'guests-total' => '',
            // 'loyalty-in-segment' => '',
            // 'loyalty-under-segments' => '',
            'nameSubjectStart'       => 'Votre vol Etihad Airways',
            'nameSubjectEnd'         => 'référence',
            'headersFromPdf'         => '/^[ ]*De[ ]+À[ ]+Vol[ ]+Départ[ ]+Arrivée$/m',
        ],
        'en' => [
            'confNumber' => ['Booking reference:', 'Booking reference :'],
            // 'Ticket number' => '',
            // 'Your ticket(s) is/are:' => '',
            // 'Direct' => '',
            // 'Non-stop' => '',
            // 'From' => '',
            // 'Terminal' => '',
            // 'Operated by:' => '',
            // 'Cabin:' => '',
            // 'Aircraft:' => '',
            // 'Seat' => '',
            'guest-single'           => ['Guest:', 'Guests:'],
            'guests-total'           => 'Guest(s)',
            'loyalty-in-segment'     => 'Loyalty programme number',
            'loyalty-under-segments' => 'Loyalty Number',
            'nameSubjectStart'       => 'Your Etihad Airways flight',
            'nameSubjectEnd'         => 'reference',
            'headersFromPdf'         => '/^[ ]*From[ ]+To[ ]+Flight[ ]+Departure[ ]+Arrival$/m',
        ],
    ];

    private $detectSubject = [
        // fr
        'Votre vol Etihad Airways, ',
        // en
        'Online check-in is open',
        'Your Etihad Airways flight, ',
        'Important: Your Etihad Airways flight reference ',
    ];
    private $detectBody = [
        'fr' => [
            'Nous nous réjouissons de vous accueillir bientôt à bord',
        ],
        'en' => [
            "your flight's been cancelled", "your flight's been canceled",
            'Start your journey by checking in online',
            'We look forward to welcoming you on board',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]etihad\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true
            && stripos($headers["subject"], 'Etihad Airways') === false
        ) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.etihad.com/', 'www.etihad.com', 'bookings.etihad.com', 'digital.etihad.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $subject = $parser->getSubject();
        $this->parseEmailHtml($email, $subject);

        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            foreach (self::$dictionary as $phrases) {
                if (empty($phrases['headersFromPdf'])) {
                    continue;
                }

                if (preg_match($phrases['headersFromPdf'], $textPdf)) {
                    $this->logger->debug('Found Pdf-attachment! Go to parser panorama/TicketEMDPdf');
                    $email->add()->flight(); // for 100% fail

                    break 2;
                }
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

    private function parseEmailHtml(Email $email, string $subject): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
            'loyaltyNo'     => 'EY[ ]*(?<number>\d{5,})(?:[ ]+\D{3,})?', // EY 500083966391 BRNZ
            'namePrefixes'  => '(?:Miss|Mstr|Mrs|Mr|Ms|Dr)',
        ];

        $f = $email->add()->flight();

        // General
        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,7}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $travellersText = implode('/', $this->http->FindNodes("//text()[{$this->eq($this->t('guest-single'))}]/ancestor::*[../self::tr][1]", null, "/:\s*(.+)/"));
        $travellers = preg_split('/(\s*\/\s*)+/', $travellersText);
        $travellers = preg_replace(['/\s*\(.*\)\s*$/', "/^\s*{$patterns['namePrefixes']}\s+/i"], '', $travellers);
        $travellers = array_filter($travellers);

        if (count($travellers) === 0
            && preg_match("/{$this->opt($this->t('nameSubjectStart'))}\s*,\s*(?:{$patterns['namePrefixes']}\s+)?({$patterns['travellerName']})\s*,\s*{$this->opt($this->t('nameSubjectEnd'))}/iu", $subject, $m)
        ) {
            $travellers = [$m[1]];
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $tickets = array_filter($this->http->FindNodes("//tr[{$this->eq($this->t('guests-total'))}]/following::text()[{$this->starts($this->t('Ticket number'))}]", null, "/^{$this->opt($this->t('Ticket number'))}[: ]+({$patterns['eTicket']})$/"));

        if (count($tickets) === 0) {
            $ticketsText = implode("\n", $this->http->FindNodes("//text()[{$this->eq($this->t('Your ticket(s) is/are:'))}]/ancestor::*[not({$this->eq($this->t('Your ticket(s) is/are:'))})][1]//text()[normalize-space()]"));

            if (preg_match_all("/^\s*[[:alpha:]][- [:alpha:]]+:\s*({$patterns['eTicket']})$/m", $ticketsText, $ticketMatches)) {
                $tickets = $ticketMatches[1];
            }
        }

        if (count($tickets) > 0) {
            $f->issued()->tickets($tickets, false);
        }

        $etihadPresence = false;
        $loyaltyNumbers = [];

        //$xpath = "//text()[{$this->starts($this->t('guest-single'))}][not(preceding::text()[{$this->eq($this->t('Original flight details'))}])]/ancestor::*[.//text()[{$this->starts($this->t('From'))}]][count(.//text()[{$this->starts($this->t('guest-single'))}])=1][1]";
        $xpath = "//text()[{$this->eq($this->t('Direct'))} or {$this->eq($this->t('Non-stop'))}]/ancestor::table[{$this->contains($this->t('From'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("descendant::text()[normalize-space()][1]", $root));
            // Airline
            $flight = $this->http->FindSingleNode("descendant::text()[normalize-space()][contains(., '(')][1]", $root);

            if (preg_match("/^.*\(\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z]) ?(?<fn>\d+)\s*\)\s*$/", $flight, $m)) {
                $s->airline()
                    ->name($m['al'])
                    ->number($m['fn'])
                ;

                $loyaltyNumber = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->starts($this->t('loyalty-in-segment'))}]", $root, true, "/^{$this->opt($this->t('loyalty-in-segment'))}[:\s]+([^:]+)$/");

                if (preg_match("/^{$patterns['loyaltyNo']}$/", $loyaltyNumber, $matches)
                    || preg_match("/^(?<number>\d{5,})(?:[ ]+\D{3,})?$/", $loyaltyNumber, $matches) && $m['al'] === 'EY'
                ) {
                    $loyaltyNumbers[] = $matches['number'];
                }

                if ($m['al'] === 'EY') {
                    $etihadPresence = true;
                }
            }

            $s->airline()
                ->operator($this->http->FindSingleNode(".//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Operated by:'))}]]",
                    $root, true, "/:\s*(.+)\s*$/"), true, true);

            $xpath = ".//text()[{$this->starts($this->t('From'))}]/following::*[normalize-space()][1]/descendant::*[self::td or self::th][not(.//tr)][normalize-space()]";
            $routeText = implode("\n", $this->http->FindNodes($xpath, $root));

            $re = "/^\s*(?<dCode>[A-Z]{3})\n(?<duration>.+)\n(?<dTime>.+)\n.+\n(?<dDate>.+)\n(?<dName>.+)(?<dTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?(?:\n.+\n.+)?"
                . "\n(?<aCode>[A-Z]{3})\n(?<aTime>.+?)( *[+-] ?\d ?[[:alpha:]]+)?\n(?<aDate>.+)\n(?<aName>.+)(?<aTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?\s*$/u";

            //no duration
            $re2 = "/^\s*(?<dCode>[A-Z]{3})\n(?<dTime>\d+\:\d+)(?:\nNon\-stop)?\n(?<dDate>.+)\n(?<dName>.+)(?<dTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?(?:\nNon\-stop)?"
                . "\n(?<aCode>[A-Z]{3})\n(?<aTime>.+?)( *[+-] ?\d ?[[:alpha:]]+)?\n(?<aDate>.+)\n(?<aName>.+)(?<aTerminal>\n.*(?i){$this->opt($this->t('Terminal'))}(?-i).*)?\s*$/u";

            if (preg_match($re, $routeText, $m) || preg_match($re2, $routeText, $m)) {
                $noYear = false;

                if (preg_match("/^\s*\w+ \w+\s*$/", $m['dDate'])) {
                    $noYear = true;
                    $m['dDate'] = $m['dDate'] . ' ' . date('Y', $date);
                }

                if (preg_match("/^\s*\w+ \w+\s*$/", $m['aDate'])) {
                    $noYear = true;
                    $m['aDate'] = $m['aDate'] . ' ' . date('Y', $date);
                }
                // Departure
                $terminalDep = empty($m['dTerminal']) ? null : preg_replace(["/^\s*{$this->opt($this->t('Terminal'))}\s*/i", "/\s*{$this->opt($this->t('Terminal'))}\s*$/i"], '', trim($m['dTerminal']));
                $s->departure()
                    ->code($m['dCode'])
                    ->name($m['dName'])
                    ->date($this->normalizeDate($m['dDate'] . ', ' . $m['dTime']))
                    ->terminal($terminalDep === '' ? null : $terminalDep, false, true);

                // Arrival
                $terminalArr = empty($m['aTerminal']) ? null : preg_replace(["/^\s*{$this->opt($this->t('Terminal'))}\s*/i", "/\s*{$this->opt($this->t('Terminal'))}\s*$/i"], '', trim($m['aTerminal']));
                $s->arrival()
                    ->code($m['aCode'])
                    ->name($m['aName'])
                    ->date($this->normalizeDate($m['aDate'] . ', ' . $m['aTime']))
                    ->terminal($terminalArr === '' ? null : $terminalArr, false, true);

                if ($noYear === true) {
                    if (!empty($s->getArrDate()) && !empty($s->getDepDate()) && $s->getArrDate() < $s->getDepDate()
                        && strtotime("1 year 2 days", $s->getArrDate()) > $s->getDepDate()
                ) {
                        $s->arrival()
                            ->date(strtotime("1 year", $s->getArrDate()));
                    }
                }
            }

            // Extra
            $s->extra()
                ->cabin($this->http->FindSingleNode(".//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Cabin:'))}]]", $root, true, "/:\s*(.+?)\s*(?:\(|$)/"), false, true)
                ->bookingCode($this->http->FindSingleNode(".//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Cabin:'))}]]", $root, true, "/:\s*.+\s*\(\s*([A-Z]{1,2})\s*\)$/"), false, true)
                ->aircraft($this->http->FindSingleNode(".//td[not(.//td)][descendant::text()[normalize-space()][1][{$this->eq($this->t('Aircraft:'))}]]", $root, true, "/:\s*(.+)\s*$/"), false, true)
            ;

            $status = $this->http->FindSingleNode("descendant::text()[normalize-space()][3][ancestor::*[contains(@style, 'background-color')]]", $root);

            if (!empty($status)) {
                $s->extra()
                    ->status($status);
            }

            if (preg_match("/\bCancell?ed\b/i", $status)) {
                $s->extra()
                    ->cancelled();
            }

            $seats = $this->http->FindNodes(".//text()[{$this->contains($this->t('Seat'))}]", $root,
                "/Seat (\d{1,3}[A-Z])\b/");

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }

        if (count($loyaltyNumbers) === 0) {
            $loyaltyNoValues = $this->http->FindNodes("//tr[{$this->eq($this->t('guests-total'))}]/following::text()[{$this->starts($this->t('loyalty-under-segments'))}]", null, "/^{$this->opt($this->t('loyalty-under-segments'))}[: ]+(.{5,})$/");

            foreach ($loyaltyNoValues as $loyaltyNumber) {
                if (preg_match("/^{$patterns['loyaltyNo']}$/", $loyaltyNumber, $matches)
                    || preg_match("/^(?<number>\d{5,})(?:[ ]+\D{3,})?$/", $loyaltyNumber, $matches) && $etihadPresence
                ) {
                    $loyaltyNumbers[] = $matches['number'];
                }
            }
        }

        if (count($loyaltyNumbers) > 0) {
            $f->program()->accounts(array_unique($loyaltyNumbers), false);
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        $in = [
            //            // Sun, Apr 09
            //            '/^\s*(\w+),\s*(\w+)\s+(\d+)\s*$/iu',
            //            // Tue Jul 03, 2018 at 1 :43 PM
            //            '/^\s*[\w\-]+\s+(\w+)\s+(\d+),\s*(\d{4})\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            //            '$1, $3 $2 ' . $year,
            //            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        // $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match('/^\s*\d+\s+[[:alpha:]]+\s+(\d{4})(?:\s*,\s*\d{1,2}:\d{2}(\s*[ap]m)?)?\s*$/ui', $date)) {
            return strtotime($date);
        }

        return null;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
