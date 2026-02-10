<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// TODO: merge with parsers malaysia/FlightRetiming(object), flyerbonus/TripReminder(object), thaiair/Cancellation(object), rapidrewards/Changes, mabuhay/FlightChange(object), lotpair/FlightChange(object) (in favor of malaysia/FlightRetiming)

class Air extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-121847049.eml, aviancataca/it-28794809.eml, aviancataca/it-28800363.eml, aviancataca/it-29001828.eml, aviancataca/it-40705061.eml, aviancataca/it-41506209.eml, aviancataca/it-42171975.eml, aviancataca/it-42227851.eml";

    private $lang = 'en';

    private $detects = [
        'en' => [
            'Thank you for flying with us',
            'We confirm the ticket issuance for your reservation',
            'Did you know you could perform your check-in online',
            'Please be informed that your seat in',
            'We wish you a very pleasant flight.',
            'The new information for your reservation',
            'SCHEDULE CHANGE NOTIFICATION',
            'your flight is now open for Check-In',
        ],
        'es' => ['Gracias por volar con nosotros', 'Te deseamos un feliz viaje!', 'Referencia de la reserva'],
    ];

    private static $dict = [
        'en' => [
            'Some flights may be modified'        => ['Some flights may be modified', 'IMPORTANT REMINDERS:', 'If you have already checked in online', 'For further information, please ', 'We do apologize for any'],
            'Información sobre el vuelo anterior' => ['Información sobre el vuelo anterior', 'Your Previous Flight information'],
            'Booking reference:'                  => 'Booking reference:',
            'Itinerary information'               => ['Itinerary information', 'Itinerary Information', 'Active flight information'],
            'From'                                => 'From',
            'Hello'                               => ['Hello', 'Dear'],
            'Class'                               => ['Class', 'class'],
            'Seat'                                => ['Seat', 'seat'],
            'CancellationSubject'                 => ['Flight Cancellation', 'your flight is cancelled'],
            //            'has been cancelled' => '',// in email body
        ],
        'es' => [
            'Booking reference:'                  => 'Referencia de la reserva:',
            'Frequent flyer:'                     => 'Viajero frecuente:',
            'Hello'                               => 'Hola',
            'From'                                => 'De',
            'To'                                  => 'A',
            'Some flights may be modified'        => 'Algunos vuelos podrían presentar modificaciones',
            'Información sobre el vuelo anterior' => 'Información sobre el vuelo anterior',
            'Itinerary information'               => 'Información sobre el itinerario',
            'Class'                               => ['Clase', 'clase'],
            //            'Seat' => [''],
            //            'CancellationSubject' => '',
            //            'has been cancelled' => '',// in email body
        ],
    ];
    private $emailSubject;

    private $code;
    private static $providers = [
        'aviancataca' => [
            'from' => ['avianca.com'],
            'subj' => [
                'You can check in, Now!',
                '¡Desde ya puedes hacer check in!',
            ],
            'body' => [
                '//a[contains(@href,"avianca.com")]',
                'Avianca',
            ],
        ],
        'saudisrabianairlin' => [
            'from' => ['saudiairlines.com', '@saudia.com'],
            'subj' => [
                'Your electronic ticket has been issued',
                'Ticketing time limit has been reached for your reservation',
            ],
            'body' => [
                '//a[contains(@href,"saudiairlines.com")]',
                '//a[contains(@href,".saudia.com")]',
                'Saudia',
            ],
        ],
        'mabuhay' => [
            'from' => ['philippineairlines.com'],
            'subj' => [
                'You can now check-in for your Flight',
                'The online check-in of your flight is open',
                'Your Seat For Flight',
            ],
            'body' => [
                '//a[contains(@href,"philippineairlines.com")]',
                '//text()[contains(.,"philippineairlines.com")]',
                'Philippine Airlines',
            ],
        ],
        'jordanian' => [
            'from' => ['@rj.com'],
            'subj' => [
                'kindly note your flight is cancelled.',
            ],
            'body' => [
                '//a[contains(@href,".rj.com")]',
                '//text()[contains(.,"@rj.com")]',
                'Thank you for choosing ROYAL JORDANIAN',
            ],
        ],
        'thaiair' => [
            'from' => ['no-reply@thaiairways.com'],
            'subj' => [
                'Flight Cancellation',
            ],
            'body' => [
                '//a[contains(@href,"www.thaiairways.com")]',
                '//text()[contains(.,"www.thaiairways.com")]',
                'THAI regrets to inform you that',
                'Thank you for flying THAI',
            ],
        ],
    ];
    private $dateFirstFlight; // for aviancataca

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $this->emailSubject = $parser->getSubject();

        $cl = explode('\\', __CLASS__);
        $email->setType(end($cl) . ucfirst($this->lang));

        $this->parseEmail($email);

        if (null !== ($code = $this->getProvider($parser)) && $code !== 'aviancataca') {
            $email->setProviderCode($code);
        } else {
            if (isset($this->dateFirstFlight) && $this->dateFirstFlight >= strtotime('2019-07-01')) {
                $email->setProviderCode('aviancataca');
            }
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if (null === $this->getProvider($parser)) {
            return false;
        }

        if ($this->detectBody()) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$providers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
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

        foreach (self::$providers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
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

    public static function getEmailProviders()
    {
        return array_keys(self::$providers);
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict) + 1; // full itinerary + modified segments
    }

    private function getProvider(PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function getProviderByBody()
    {
        foreach (self::$providers as $code => $arr) {
            $criteria = $arr['body'];

            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        if ($conf = $this->http->FindSingleNode("//td[normalize-space(.)='{$this->t('Booking reference:')}' and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]")) {
            if (preg_match("#^([A-Z\d]{5,6})\s*\-\s*(\d+)$#", $conf, $m)) {
                $f->general()
                    ->confirmation($m[1]);
                $f->ota()
                    ->confirmation($m[2]);
            } else {
                $f->general()
                    ->confirmation($conf);
            }
        }

        $rootPaxs = $this->http->XPath->query("//text()[{$this->eq($this->t('Passenger name'))}]/ancestor::tr[{$this->contains($this->t('Ticket number'))}][1]/following-sibling::tr[normalize-space()!='']");

        foreach ($rootPaxs as $rootPax) {
            if ($this->http->XPath->query("./td[normalize-space()!='']", $rootPax)->length !== 2) {
                break;
            }
            $names[] = $this->http->FindSingleNode("./td[normalize-space()!=''][1]", $rootPax);
            $tickets[] = $this->http->FindSingleNode("./td[normalize-space()!=''][2]", $rootPax, null, "#^[\d\- ]+$#");
        }

        if (!isset($names)) {
            $name = $this->http->FindSingleNode("//td[normalize-space(.)='{$this->t('Frequent flyer:')}' and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]",
                null, true, "/(.+?)\s*\//");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("(//td[normalize-space(.)='{$this->t('Booking reference:')}' and not(.//td)]/following::text()[{$this->starts($this->t('Hello'))}][1])[1]",
                    null, true, "/{$this->preg_implode($this->t('Hello'))}\s+([\w ]+?)\W?$/");
            }

            if (!empty($name) && !preg_match("/\b(Customer|Guest)/ui", $name)) {
                $f->addTraveller($name);
            }
        } else {
            $f->general()->travellers($names, true);

            if (isset($tickets) and !empty($tickets = array_filter($tickets))) {
                $f->issued()->tickets($tickets, false);
            }
        }

        // Cancelled
        if (preg_match("/\b" . $this->preg_implode($this->t("CancellationSubject")) . "\b/", $this->emailSubject) || !empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("has been cancelled")) . "])[1]"))) {
            $f->general()
                ->status('Cancelled')
                ->cancelled()
            ;
        }

        $account = $this->http->FindSingleNode("//td[normalize-space(.)='{$this->t('Frequent flyer:')}' and not(.//td)]/following-sibling::td[normalize-space(.)!=''][1]", null, true, "/\/\s*([A-Z\d]{5,})\b/");

        if (!empty($account)) {
            $f->program()->account($account, false);
        }

        $startXpath = "//tr[" . $this->eq($this->t("Itinerary information")) . "]/following::tr[contains(normalize-space(.), '{$this->t('From')}') and contains(normalize-space(.), '{$this->t('To')}') and not(.//tr)]/preceding-sibling::tr";
        $startOfSegmentsPos = $this->http->XPath->query($startXpath)->length;
        $endXpath = "//tr[{$this->contains($this->t('Some flights may be modified'))}]/preceding-sibling::tr";
        $endOfSegmentsPos = $this->http->XPath->query($endXpath)->length;

        if (empty($endOfSegmentsPos)) {
            $endOfSegmentsPos = $this->http->XPath->query("//tr[" . $this->eq($this->t("Itinerary information")) . "]/following::tr[normalize-space() = '' and following-sibling::tr[normalize-space() = '']]/preceding-sibling::tr")->length;
        }

        if (!empty($startOfSegmentsPos) && !empty($endOfSegmentsPos)) {
            $pos = $endOfSegmentsPos - $startOfSegmentsPos;
            $xpath = "//text()[" . $this->eq($this->t("Itinerary information")) . "]/following::tr[contains(normalize-space(.), '{$this->t('From')}') and contains(normalize-space(.), '{$this->t('To')}') and not(.//tr)][1]/following-sibling::tr[position()<{$pos}][normalize-space()]";
            $roots = $this->http->XPath->query($xpath);

            if ($roots->length == 0) {
                $xpath = "//tr[" . $this->eq($this->t("Itinerary information")) . "]/following-sibling::tr[count(descendant::td[not(.//td)][normalize-space()]) = 6][contains(normalize-space(.), '{$this->t('From')}') and contains(normalize-space(.), '{$this->t('To')}')][1]/following-sibling::tr[position()<{$pos}][normalize-space()]/descendant-or-self::tr";
                $roots = $this->http->XPath->query($xpath);
            }
        }

        if (!isset($roots) || $roots->length === 0) {
            if (count($this->http->FindNodes("//tr[contains(normalize-space(.), '{$this->t('From')}') and contains(normalize-space(.), '{$this->t('To')}') and not(.//tr)]")) == 1) {
                $tablePos = 1;
            } else {
                $tablePos = 2;
            }
            $startOfSegmentsPos = $this->http->XPath->query("//tr[contains(normalize-space(.), '{$this->t('From')}') and contains(normalize-space(.), '{$this->t('To')}') and not(.//tr)][{$tablePos}]/preceding-sibling::tr")->length;
            $endOfSegmentsPos = $this->http->XPath->query("//tr[{$this->contains($this->t('Some flights may be modified'))}]/preceding-sibling::tr")->length;
            $pos = $endOfSegmentsPos - $startOfSegmentsPos;
            $xpath = "//tr[contains(normalize-space(.), '{$this->t('From')}') and contains(normalize-space(.), '{$this->t('To')}') and not(.//tr)][{$tablePos}]/following-sibling::tr[position()<{$pos}][normalize-space()]";
            $roots = $this->http->XPath->query($xpath);
        }

        if (isset($xpath) && (!isset($roots) || 0 === $roots->length)) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        $airs = [];

        foreach ($roots as $root) {
            if (!empty($this->getNode($root, 5, '/([A-Z][A-Z\d]|[A-Z\d][A-Z]\s*\d+)/'))) {
                $airs[] = $root;
            }
        }

        foreach ($airs as $root) {
            if (!empty($this->getNode($root, 5)) && preg_match('/([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/', $this->getNode($root, 5), $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);

                $patterns['airport'] = '/^(.+?)\s+([A-Z]{3})(?:\s+(.+))?$/'; // Bangkok BKK Suvarnabhumi Intl

                // TODO: airport code maybу is city codes?

                $from = $this->getNode($root);

                if (preg_match_all('/\b[A-Z]{3}\b/', $from, $matches) && count($matches[0]) === 1
                    && preg_match($patterns['airport'], $from, $matches)
                ) {
                    $nameDepParts = [$matches[1]];

                    if (!empty($matches[3])) {
                        $nameDepParts[] = $matches[3];
                    }
                    $s->departure()
                        ->name(implode(', ', array_unique($nameDepParts)))
                        ->code($matches[2]);
                } else {
                    $s->departure()
                        ->name($from)
                        ->noCode();
                }

                $to = $this->getNode($root, 2);

                if (preg_match_all('/\b[A-Z]{3}\b/', $to, $matches) && count($matches[0]) === 1
                    && preg_match($patterns['airport'], $to, $matches)
                ) {
                    $nameArrParts = [$matches[1]];

                    if (!empty($matches[3])) {
                        $nameArrParts[] = $matches[3];
                    }
                    $s->arrival()
                        ->name(implode(', ', array_unique($nameArrParts)))
                        ->code($matches[2]);
                } else {
                    $s->arrival()
                        ->name($to)
                        ->noCode();
                }

                $dTime = $this->getNode($root, 3, '/(\d{2}:\d{2})/');
                $dDate = $this->http->FindSingleNode("following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][1]", $root);

                if (preg_match("#-\d{3}$#", $dDate) && ($d2 = $this->http->FindSingleNode("(//td[not(.//td) and starts-with(normalize-space(), '{$dDate}')][1])[1]", null, true, "#" . preg_quote($dDate) . "\d\s*$#"))) {
                    $dDate = $d2;
                }

                if (!empty($dTime) && !empty($dDate)) {
                    $s->departure()
                        ->date(strtotime($dTime, $this->normalizeDate($dDate)));
                }

                $aTime = $this->getNode($root, 4, '/(\d{1,2}:\d{2})/');
                $aDate = $this->http->FindSingleNode("following-sibling::tr[normalize-space(.)][1]/td[normalize-space(.)][2]", $root);

                if (preg_match("#-\d{3}$#", $aDate) && ($d2 = $this->http->FindSingleNode("(//td[not(.//td) and starts-with(normalize-space(), '{$aDate}')][1])[1]", null, true, "#" . preg_quote($aDate) . "\d\s*$#"))) {
                    $aDate = $d2;
                }
                $this->logger->debug('$aDate = ' . print_r($this->http->FindSingleNode("following-sibling::tr[normalize-space(.)][1]", $root), true));

                if (!empty($aTime) && !empty($aDate)) {
                    $s->arrival()
                        ->date(strtotime($aTime, $this->normalizeDate($aDate)));
                }

                if ($this->http->FindSingleNode("(./preceding-sibling::tr[normalize-space()]/td[normalize-space(.)!=''][6][" . $this->contains($this->t("Class")) . "])[1]", $root)
                    || $this->http->FindSingleNode("(./preceding::tr[td[normalize-space(.)!=''][1][{$this->eq($this->t('From'))}] and td[normalize-space(.)!=''][2][{$this->eq($this->t('To'))}]]/td[normalize-space(.)!=''][6][" . $this->contains($this->t("Class")) . "])[1]", $root)
                ) {
                    $cabin = $this->getNode($root, 6);

                    if (preg_match("#^\s*([A-Z]{1,2})\s*$#", $cabin)) {
                        $s->extra()->bookingCode($cabin);
                    } else {
                        $s->extra()->cabin($cabin);
                    }
                } elseif ($this->http->FindSingleNode("(./preceding-sibling::tr[normalize-space()]/td[normalize-space(.)!=''][6][" . $this->contains($this->t("Seat")) . "])[1]", $root)
                    || $this->http->FindSingleNode("(./preceding::tr[td[normalize-space(.)!=''][1][{$this->eq($this->t('From'))}] and td[normalize-space(.)!=''][2][{$this->eq($this->t('To'))}]]/td[normalize-space(.)!=''][6][" . $this->contains($this->t("Seat")) . "])[1]", $root)
                ) {
                    $seat = $this->getNode($root, 6);

                    if (preg_match("#^\s*(\d{1,3}[A-Z])\s*$#", $seat)) {
                        $s->extra()->seat($seat);
                    }
                }

                if ($s->getDepDate() && !isset($this->dateFirstFlight)) {
                    $this->dateFirstFlight = $s->getDepDate();
                }
            }
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody(): bool
    {
        if (isset($this->detects)) {
            foreach ($this->detects as $detect) {
                if ($this->http->XPath->query("//*[{$this->contains($detect)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Booking reference:'], $words['From'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Booking reference:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['From'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate(string $s)
    {
        $in = [
            '/(\w+) (\d{1,2}), (\d{2,4})\b/',
            '/(\d{1,2})\-(\w+)\-(\d{2,4})\b/',
        ];
        $out = [
            '$2 $1 $3',
            '$1 $2 $3',
        ];
        $s = preg_replace($in, $out, $s);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $s, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $s = str_replace($m[1], $en, $s);
            }
        }

        return strtotime($s);
    }

    private function getNode(\DOMNode $root, int $pos = 1, ?string $re = null): ?string
    {
        return $this->http->FindSingleNode("td[normalize-space(.)!=''][position()={$pos}]", $root, true, $re);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
