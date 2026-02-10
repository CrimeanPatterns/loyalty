<?php

namespace AwardWallet\Engine\airasia\Email;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: fix different airasia/it-10045893.eml with parser `AirTicket`

class FlightNotice extends \TAccountChecker
{
    public $mailFiles = "airasia/it-10133638.eml, airasia/it-10133231.eml, airasia/it-10152589.eml, airasia/it-10045893.eml, airasia/it-33517913.eml, airasia/it-47687974.eml";

    public $lang = '';

    public $langDetectors = [
        'en' => ['Booking Code / PNR', 'Booking number:', 'booking number:'],
    ];

    public static $dictionary = [
        'en' => [
            'bookingNumber' => ['Booking Code / PNR', 'Booking number:', 'booking number:'],
            'newFlightTime' => ['FLIGHT TIME', 'Flight Time', 'NEW FLIGHT TIME', 'New Flight Time', 'NEW FLIGHT DETAILS', 'New Flight Details', 'New flight details'],
            'departureDate' => ['Departure date', 'Depart date'],
            'depart'        => ['Depart from', 'Depart:', 'Depart :'],
            'arrive'        => ['Arrive in', 'Arrive:', 'Arrive :'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]airasia.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'AirAsia') === false) {
            return false;
        }

        return stripos($headers['subject'], 'Notice') !== false
            || stripos($headers['subject'], 'Notification') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Dear AirAsia Guest") or contains(.,"@airasia.com") or contains(normalize-space(),"on www.airasia.com") or contains(normalize-space(),"Regards, AirAsia") or contains(normalize-space(),"Regards,AirAsia")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href,".airasia.com/")]')->length === 0
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

        $this->parseEmail($email);
        $email->setType('FlightNotice' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) + 1; // + Flight Cancellation Notice
    }

    protected function assignLang()
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $bookingNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('bookingNumber'))}]", null, true, '/:\s*([A-Z\d]{5,})$/');

        if (!$bookingNumber) {
            $bookingNumber = $this->http->FindSingleNode("//text()[{$this->contains($this->t('bookingNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');
        }
        $f->general()->confirmation($bookingNumber);

        if ($this->http->XPath->query("//node()[{$this->contains($this->t('your flight will be cancelled'))}]")->length > 0
            && $this->http->XPath->query("//node()[{$this->contains($this->t('newFlightTime'))}]")->length === 0
        ) {
            // for cancelled reservations (examples: it-10133231.eml)

            $f->general()
                ->cancelled()
                ->status('cancelled')
            ;

            $infoTexts = $this->http->FindNodes("//text()[contains(.,'{$bookingNumber}')]/ancestor::*[{$this->contains($this->t('Date of Travel'))}][1]/descendant::text()[normalize-space()]");
            $infoText = implode("\n", $infoTexts);

            if (!$infoText) {
                return $email;
            }

            $s = $f->addSegment();

            if (preg_match("/\b{$this->opt($this->t('Flight No.'))}\s*:+\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)\b/", $infoText, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('From'))}\s*:+\s*.*\(\s*([A-Z]{3})\s*\)$/m", $infoText, $m)) {
                $s->departure()->code($m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('To'))}\s*:+\s*.*\(\s*([A-Z]{3})\s*\)$/m", $infoText, $m)) {
                $s->arrival()->code($m[1]);
            }

            if (preg_match("/^[ ]*{$this->opt($this->t('Date of Travel'))}\s*:+\s*(.{6,})$/m", $infoText, $m)) {
                $s->departure()->day2($m[1]);
            }

            return $email;
        }

        $s = $f->addSegment();

        $xpathNewFlight = "//text()[{$this->eq($this->t('newFlightTime'))}]";

        $flight = $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('Flight number'))}]/ancestor::*[self::p][1]") ?? $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('Flight number'))}]");

        if (preg_match('/:\s*(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<flightNumber>\d+)$/', $flight, $m)) {
            $s->airline()
                ->name($m['airline'])
                ->number($m['flightNumber'])
            ;
        }

        // Singapore (SIN) : 09:55 AM (09:55hrs), local time
        $patterns['codeTime'] = '/\(([A-Z]{3})\)\s*[:]+\s*(\d{1,2}:\d{2}\s*[AaPp][Mm])/';

        // Depart : 3:30 PM (1530hrs)
        $patterns['time'] = '/:\s*(\d{1,2}:\d{2}\s*[AaPp][Mm])/';

        // 26 November 2017    |    Wednesday, November 13, 2019
        $dateDep = $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('departureDate'))}]/ancestor::*[self::p][1]", null, true, '/:\s*(.{6,})$/') ?? $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('departureDate'))}]", null, true, '/:\s*(.{6,})$/');

        $departure = $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('depart'))}]/ancestor::*[self::p][1]") ?? $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('depart'))}]");

        if (preg_match($patterns['codeTime'], $departure, $m)) {
            $s->departure()->code($m[1]);

            if ($dateDep) {
                $s->departure()->date2($dateDep . ' ' . $m[2]);
            }
        } elseif ($dateDep && preg_match($patterns['time'], $departure, $m)) {
            $s->departure()->date2($dateDep . ' ' . $m[1]);
        }

        $arrival = $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('arrive'))}]/ancestor::*[self::p][1]") ?? $this->http->FindSingleNode($xpathNewFlight . "/following::text()[{$this->contains($this->t('arrive'))}]");

        if (preg_match($patterns['codeTime'], $arrival, $m)) {
            $s->arrival()->code($m[1]);

            if ($dateDep) {
                $s->arrival()->date2($dateDep . ' ' . $m[2]);
            }
        } elseif ($dateDep && preg_match($patterns['time'], $arrival, $m)) {
            $s->arrival()->date2($dateDep . ' ' . $m[1]);
        }

        if (empty($s->getDepCode()) && empty($s->getArrCode())) {
            // it-33517913.eml
            $routeTexts = $this->http->FindNodes("//text()[ preceding::text()[{$this->eq($this->t('newFlightTime'))}] and following::text()[{$this->contains($this->t('Flight number'))}] ][normalize-space()]");
            $routeText = implode("\n", $routeTexts);

            $routes = preg_split("/\)\s+{$this->opt($this->t('to'))}\s+/", $routeText);

            if (count($routes) === 2) {
                // Kuching International Airport, Main Terminal (KCH)
                $patterns['nameTerminalCode'] = '/.{3,},\s*([^,]*(?i)Terminal(?-i)[^,]*?)\s*\(\s*([A-Z]{3})\s*\)?$/';

                // Kuching International Airport (KCH)
                $patterns['code'] = '/.{3,}\(\s*([A-Z]{3})\s*\)?$/';

                if (preg_match($patterns['nameTerminalCode'], $routes[0], $m)) {
                    $s->departure()
                        ->terminal(preg_replace("/\s*Terminal\s*/i", '', $m[1]))
                        ->code($m[2])
                    ;
                } elseif (preg_match($patterns['code'], $routes[0], $m)) {
                    $s->departure()->code($m[1]);
                }

                if (preg_match($patterns['nameTerminalCode'], $routes[1], $m)) {
                    $s->arrival()
                        ->terminal(preg_replace("/\s*Terminal\s*/i", '', $m[1]))
                        ->code($m[2])
                    ;
                } elseif (preg_match($patterns['code'], $routes[1], $m)) {
                    $s->arrival()->code($m[1]);
                }
            }
        }
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }
}
