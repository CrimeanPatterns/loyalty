<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

// parse pdf in panorama/TicketEMDPdf(multi-prov)

class ETicketReceipt extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-12402037.eml, amadeus/it-12479938.eml, amadeus/it-4606345.eml, amadeus/it-608429242.eml, amadeus/it-6630054.eml, amadeus/it-6906131.eml, amadeus/it-7112469.eml, amadeus/it-7193683.eml, amadeus/it-7234006.eml, amadeus/it-7263335.eml, amadeus/it-7263340.eml, amadeus/it-77677062.eml, amadeus/it-79760752.eml, amadeus/it-275265111-malmo.eml";

    public $reFrom = [
        'ticket@amadeus.com',
        'CARIBBEAN AIRLINES LIMITED',
        '@elal-ticketing.com',
        '@amadeus.com',
        '@malaysiaairlines.com',
        '@notify.hawaiianairlines.com',
    ];
    public $reBody = [
        'en' => ['Arrival'],
        'fr' => ['Arrivée'],
        'es' => ['Llegada'],
        'it' => ['Arrivo'],
        'nl' => ['Aankomst'],
    ];
    public $lang = '';
    public $year;
    public static $dict = [
        'en' => [
            //		    "Passenger" => "",
            "Booking ref" => ["Booking ref", "Booking code"],
            //		    "Ticket number" => "",
            //		    "Date" => "",
            //		    "Terminal" => "",
            "Class"        => ["Class", "/Class:"],
            "operatedBy"   => "Operated by",
            //		    "Seat" => "",
            //		    "Duration" => "",
            //		    "Frequent flyer number" => "",
            "Fare"            => ["Fare", "/ Fare:", "/Fare:", "Fare:", "Tarif"],
            "Fare equivalent" => ["Fare equivalent", "Fare equivalent:", "/ Fare equivalent:", "/Fare equivalent:"],
            "Taxes:"          => ["Taxes:", "/Taxes:", "/ Taxes:", 'Taxes', "Carrier Imposed Fees", "Total OB Fees"],
            "Total Amount"    => ["Total Amount", "/ Total Amount:", "/ Total amount:", "/Total amount:", "Montant total", "/Total Amount:"],
        ],
        'fr' => [
            "Passenger"             => "Passager",
            "Booking ref"           => ["Reference du dossier"],
            "Ticket number"         => "Numéro de billet",
            "Date"                  => "Date",
            "Terminal"              => "Terminal",
            "Class"                 => ["Classe"],
            "operatedBy"            => "Opéré par",
            "Seat"                  => "Siège",
            "Duration"              => "Durée",
            "Frequent flyer number" => "Nº de carte fidélité",
            "Fare"                  => ["Tarif"],
            // "Fare equivalent" => "",
            "Total Amount"          => ["Montant total:"],
        ],
        'es' => [
            "Passenger"     => "Pasajero",
            "Booking ref"   => "Código de Reserva",
            "Ticket number" => "Número de billete",
            "Date"          => "Fecha",
            //"Terminal" => "",
            "Class"       => "Clase",
            "operatedBy"  => "Operado por",
            "Seat"        => "Asiento",
            "Duration"    => "Duración",
            //"Frequent flyer number" => "",
            "Fare"         => "Tarifa",
            // "Fare equivalent" => "",
            "Total Amount" => "Valor total",
        ],
        'it' => [
            "Passenger"             => "Passeggero",
            "Booking ref"           => "Riferimento prenotazione",
            "Ticket number"         => "Numero Biglietto",
            "Date"                  => "Data",
            // "Terminal" => "",
            "Class"                 => "Classe",
            "operatedBy"            => "Operato da",
            "Seat"                  => "Posto",
            "Duration"              => "Durata volo",
            // "Frequent flyer number" => "",
            "Fare"                  => "Tariffa",
            // "Fare equivalent" => "",
            "Total Amount"          => "Valor total",
        ],
        'nl' => [
            "Passenger"             => "Passagier",
            "Booking ref"           => "Boekingscode",
            "Ticket number"         => "Ticketnummer",
            "Date"                  => "Datum",
            // "Terminal" => "",
            "Class"                 => "Klasse",
            "operatedBy"            => "Uitgevoerd door",
            "Seat"                  => "Stoel",
            "Duration"              => "Looptijd",
            // "Frequent flyer number" => "",
            "Fare"                  => "Tarief",
            // "Fare equivalent" => "",
            "Total Amount"          => "Totaalbedrag",
        ],
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider();
        $this->assignLang();

        $this->logger->debug('$this->providerCode = ' . print_r($this->providerCode, true));
        $this->parseEmail($email);

        $email->setProviderCode($this->providerCode);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        // Detecting Provider
        if ($this->assignProvider() !== true) {
            return false;
        }

        // Detecting Format
        if ($this->http->XPath->query("//node()[" . $this->contains(["TICKET RECEIPT", "REÇU DE BILLET ÉLECTRONIQUE", "ELECTRONIC TICKET"]) . "]")->length === 0) {
            return false;
        }

        // Detecting Language
        if ($this->providerCode) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from'])) {
            foreach ($this->reFrom as $reFrom) {
                if (stripos($headers['from'], $reFrom) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@amadeus.com') !== false
//			|| stripos($from, '@caribbean-airlines.com') !== false
//			|| stripos($from, 'CARIBBEAN AIRLINES LIMITED') !== false
//			|| stripos($from, '@elal-ticketing.com') !== false
        ;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return [
            'cape',
            'caribbeanair', 'israel', 'eva', 'amadeus', 'srilankan', 'tahitinui', 'lotpair', 'malaysia',
            'atlanticairways', 'hawaiian', 'tapportugal', 'malmo', 'cairo',
        ];
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

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $traveller = null;
        $travellerNames = array_filter($this->http->FindNodes("//*[(../tr or self::div) and not(.//tr) and {$this->contains($this->t('Passenger'))} and contains(.,':')]", null, "/{$this->opt($this->t('Passenger'))}(?:\s*\/\s*\S[^:]*?)?[:\s]+([[:alpha:]][-.\'’[:alpha:] ]*?[[:alpha:]])(?:\s+(?:Mrs|Mr|Miss|Ms))?(?:\s*\(.*\))?$/u"));

        if (count(array_unique($travellerNames)) === 1) {
            $traveller = array_shift($travellerNames);
        }

        if ($this->providerCode == 'tapportugal') {
            $traveller = preg_replace("/(MR|MS|MRS|MISS|DR|MSTR)\s*$/i", '', $traveller);
        }
        $traveller = preg_replace("/ (MR|MS|MRS|MISS|DR|MSTR)\s*$/i", '', $traveller);

        // General
        $conf = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Booking ref")) . "])[1]/ancestor::tr[1]", null, true, "#:\s*([A-Z\d]+)\s*$#");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Class")) . "])[1]/preceding::text()[" . $this->contains($this->t("Booking ref")) . "][1]/ancestor::tr[1]", null, true, "#:\s*([A-Z\d]+)#");
        }
        $f->general()
            ->confirmation($conf);
        $f->general()
            ->traveller($traveller)
            ->date($this->normalizeDate($this->http->FindSingleNode("(descendant::text()[{$this->contains($this->t("Booking ref"))} or {$this->contains($this->t("Issuing office"))}][1]/following::text()[{$this->contains($this->t("Date"))}])[1]/ancestor::td[1]", null, false, "/{$this->opt($this->t("Date"))}[^:]*\s*:\s*(.+)/")));

        // Issued
        $ticketNumber = $this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Ticket number")) . "]/ancestor::tr[1])[1]", null, true, "#:\s*(.+)#");

        if (stripos($ticketNumber, 'Agent Info') !== false) {
            $ticketNumber = preg_replace("/^(.+)\s+Agent Info.+/", "$1", $ticketNumber);
        }

        if (!empty($ticketNumber)) {
            $f->issued()->ticket($ticketNumber, false);
        }

        // Price
        $totalRows = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Grand Total")) . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]",
            null, "/^.*\d+.*$/"));

        if (empty($totalRows)) {
            $totalRows = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Total Amount")) . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]",
                null, "/^.*\d+.*$/"));
        }

        if (empty($totalRows)) {
            $totalRows = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Total Amount")) . "]/ancestor::td[1]/following-sibling::td[normalize-space(.)][1]",
                null, "/^.*\d+.*$/"));
        }

        $tot = $this->getTotalCurrency('');

        foreach ($totalRows as $row) {
            $t = $this->getTotalCurrency($row);

            if (empty($tot['Currency']) || $tot['Currency'] === $t['Currency']) {
                $tot['Total'] = (empty($tot['Total']) ? 0.0 : $tot['Total']) + $t['Total'];
                $tot['Currency'] = $t['Currency'];
            } else {
                $tot = $this->getTotalCurrency('');

                break;
            }
        }

        $costText = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Fare equivalent"))}])[1]/ancestor::td[position() < 3][following-sibling::td[normalize-space()]][1]/following-sibling::td[normalize-space()][1]");

        if (empty($costText)) {
            $costText = $this->http->FindSingleNode("(//text()[{$this->eq($this->t("Fare"))}])[1]/ancestor::td[position() < 3][following-sibling::td[normalize-space()]][1]/following-sibling::td[normalize-space()][1]");
        }
        $cost = $this->getTotalCurrency($costText);

        if ($tot['Total'] !== '') {
            $f->price()
                ->total($tot['Total'])
                ->currency($tot['Currency'])
            ;

            if ($cost['Total'] !== '' && $cost['Currency'] === $tot['Currency']) {
                $f->price()->cost($cost['Total']);
            }
        } elseif ($cost['Total'] !== '') {
            $f->price()->cost($cost['Total'])
                ->currency($cost['Currency']);
        }

        $currency = $tot['Currency'];

        if (empty($currency)) {
            $currency = $cost['Currency'];
        }
        $feesRows = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Taxes:")) . "]/ancestor::td[1][following::tr[1][contains(., ':')]]/following-sibling::td[string-length()>3][1]//text()[normalize-space()]",
            null, "/^.*\d+.*$/"));

        if (empty($feesRows)) {
            $feesRows = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Taxes:")) . "]/ancestor::td[1][following::tr[1][contains(., ':')]]/following-sibling::td[normalize-space(.)][1]//text()[normalize-space()]",
                null, "/^.*\d+.*$/"));
        }

        if (empty($currency)) {
            $currency = '[A-Z]{3}';
        }

        foreach ($feesRows as $row) {
            if (preg_match("/^\s*({$currency}\s*\d[\d,. ]*?)\s*([A-Z]+[A-Z\d]*)?$/", $row, $m)) {
                if (isset($m[2]) && !empty($m[2])) {
                    $f->price()
                        ->fee($m[2], $this->getTotalCurrency($m[1])['Total']);
                } else {
                    $f->price()
                        ->fee('fee', $this->getTotalCurrency($m[1])['Total']);
                }
            }
        }

        $accounts = [];

        $xpath = "//text()[{$this->eq($this->t("Class"))}]/ancestor::tr[ preceding-sibling::tr[normalize-space() and not({$this->starts(array_column(self::$dict, 'operatedBy'))})] ][1]/preceding-sibling::tr[normalize-space() and not({$this->starts($this->t("Terminal"))})][1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[" . $this->eq($this->t("Class")) . "]/ancestor::tr[2][count(.//td[normalize-space() and not(.//td)]) < 4]/preceding-sibling::tr[normalize-space(.) and not(" . $this->starts($this->t("Terminal")) . ")][1]";
            $segments = $this->http->XPath->query($xpath);
        }

        // Codes
        $baggageRows = $this->http->FindNodes("//text()[{$this->contains('CARRY-ON BAG:')}]/ancestor::*[descendant::tr[not(.//tr)][1][normalize-space()][1][{$this->contains('CARRY-ON BAG:')}]][last()]//tr[not(.//tr)][not({$this->contains('CARRY-ON BAG:')})]",
            null, "/^\s*([A-Z]{6}):/");
        $baggageCodes = [];

        if (count($baggageRows) === $segments->length || count(array_filter($baggageRows)) === $segments->length) {
            foreach ($baggageRows as $row) {
                if (preg_match("/^\s*([A-Z]{3})([A-Z]{3})\s*$/", $row, $m)) {
                    $baggageCodes[] = ['dCode' => $m[1], 'aCode' => $m[2]];
                } else {
                    $baggageCodes = [];

                    break;
                }
            }
        }

        foreach ($segments as $rootKey => $root) {
            $s = $f->addSegment();

            // Ailine
            $node = $this->http->FindSingleNode("./descendant-or-self::tr[not(.//tr)]/td[normalize-space(.)][3]//text()[normalize-space(.)]", $root);

            if (preg_match("/^([A-Z][A-Z\d]|[A-Z\d][A-Z])(\d+)$/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }

            // Departure
            $node = implode("\n", $this->http->FindNodes("./descendant-or-self::tr[not(.//tr)]/td[normalize-space(.)][1]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+)\n(.+)\s*(?:Terminal(?:[^:]*):\s*(.+))?#", $node, $m)) {
                $s->departure()
                    ->name($m[1] . '-' . $m[2])
                ;

                if (isset($baggageCodes[$rootKey])) {
                    $s->departure()
                        ->code($baggageCodes[$rootKey]['dCode']);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $terminal = $m[3];
                } else {
                    $terminal = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[*[5] and not(.//tr)]/*[2]//text()[" . $this->contains($this->t("Terminal")) . "]/ancestor::td[1]", $root, true, "#" . $this->opt($this->t("Terminal")) . "\s*:\s*(.+)#");
                }

                if (!empty($terminal)) {
                    $s->departure()->terminal($terminal);
                }
            }
            $node = implode("\n", $this->http->FindNodes("./descendant-or-self::tr[not(.//tr)]/td[normalize-space(.)][4]//text()[normalize-space(.)]", $root));

            if (preg_match("#(\d+:\d+.*)\n(.+)#", $node, $m)) {
                $s->departure()
                    ->date($this->normalizeDate($m[1] . ' ' . $m[2]));
            }

            // Arrival
            $node = implode("\n", $this->http->FindNodes("./descendant-or-self::tr[not(.//tr)]/td[normalize-space(.)][2]//text()[normalize-space(.)]", $root));

            if (preg_match("#(.+)\n(.+)\s*(?:Terminal(?:[^:]*):\s*(.+))?#", $node, $m)) {
                $s->arrival()
                    ->name($m[1] . '-' . $m[2])
                ;

                if (isset($baggageCodes[$rootKey])) {
                    $s->arrival()
                        ->code($baggageCodes[$rootKey]['aCode']);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                if (isset($m[3]) && !empty($m[3])) {
                    $terminal = $m[3];
                } else {
                    $terminal = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant-or-self::tr[*[5] and not(.//tr)]/*[4]//text()[" . $this->contains($this->t("Terminal")) . "]/ancestor::td[1]", $root, true, "#" . $this->opt($this->t("Terminal")) . "\s*:\s*(.+)#");
                }

                if (!empty($terminal)) {
                    $s->arrival()->terminal($terminal);
                }
            }
            $node = implode("\n", $this->http->FindNodes("./descendant-or-self::tr[not(.//tr)]/td[normalize-space(.)][5]//text()[normalize-space(.)]", $root));

            if (preg_match("#(\d+:\d+.*)\n(.+)#", $node, $m)) {
                $s->arrival()
                    ->date($this->normalizeDate($m[1] . ' ' . $m[2]));
            }

            // Extra
            $rowsNext = count($this->http->FindNodes("./following-sibling::tr[contains(translate(./descendant-or-self::tr[not(.//tr)]/td[normalize-space()][5], '0123456789', 'dddddddddd'), 'dd:dd')][1]/preceding-sibling::tr", $root));
            $rowsBefore = count($this->http->FindNodes("./preceding-sibling::tr", $root));

            if (!empty($rowsNext) && !empty($rowsBefore)) {
                $rowsCount = $rowsNext - $rowsBefore;
            } else {
                $rowsCount = 6;
            }
            $class = $this->http->FindSingleNode("following-sibling::tr[position()<{$rowsCount}]/descendant-or-self::tr/*[not(.//tr) and {$this->contains($this->t("Class"))}][1]", $root, true, "/{$this->opt($this->t("Class"))}[^:]*[:]+\s+(\S.*)/");

            if (preg_match("/^[(\s]*([A-Z]{1,2})[)\s]*$/", $class, $m)) {
                // (U)    |    U
                $s->extra()->bookingCode($m[1]);
            } elseif (preg_match("/^\s*([\w+ ]+?)\s*\(\s*([A-Z]{1,2})\s*\)\s*$/u", $class, $m)) {
                // Economy (U)
                $s->extra()->cabin($m[1])->bookingCode($m[2]);
            } elseif (preg_match("/^\s*([\w+ ]+?)\s*,\s*([A-Z]{1,2})\s*$/u", $class, $m)) {
                // Economy, U
                $s->extra()->cabin($m[1])->bookingCode($m[2]);
            }
            $operator = $this->http->FindSingleNode("following-sibling::tr[position()<{$rowsCount}]/descendant-or-self::tr/*[not(.//tr) and {$this->contains($this->t("operatedBy"))}][1]", $root, true, "/{$this->opt($this->t("operatedBy"))}[^:]*[:]+\s+(.+)/");

            if (!empty($operator)) {
                $s->airline()
                    ->operator($operator);
            }

            $seat = $this->http->FindSingleNode("following-sibling::tr[position()<{$rowsCount}]//td[not(.//tr) and {$this->contains($this->t("Seat"))}][1]", $root, true, "/{$this->opt($this->t("Seat"))}[^:]*[:]+\s*(\d{1,3}[A-Z])\b/");

            if (!empty($seat)) {
                $s->extra()->seat($seat, false, false, $traveller);
            }
            $s->extra()
                ->duration($this->http->FindSingleNode("following-sibling::tr[position()<{$rowsCount}]/descendant-or-self::tr/*[not(.//tr) and {$this->contains($this->t("Duration"))}][1]", $root, true, "/{$this->opt($this->t("Duration"))}[^:]*[:]+\s+(.+)\b/"));

            $accounts[] = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][position()<{$rowsCount}]/descendant-or-self::tr/*[not(.//tr) and {$this->contains($this->t("Frequent flyer number"))}][1]", $root, true, "/{$this->opt($this->t("Frequent flyer number"))}[^:]*[:]+\s*([-A-Z\d ]{5,})\b/");
        }

        $accounts = array_filter(array_unique($accounts));

        if (!empty($accounts)) {
            $f->program()->accounts($accounts, false);
        }
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('IN-' . $date);

        $in = [
            '#^(\d+)\s*(\D+?)\s*(\d{4})\s+(\d+:\d+\s*(?:[ap]m)?)\s*$#i',
            '#^(\d+)\s*(\D+?)\s*(\d{4})\s*$#i',
            '#^(\d{1,2})\s*([[:alpha:]]+)\s*(\d{2})\s*$#i', // 03June16
            '#^\s*(\d+:\d+\s*(?:[ap]m)?)\s+(\d+)\s*(\D+?)\s*(\d{4})\s*$#ui', // 11:20 21JUN2018
            '#^([\d\:]+)\s*(\d+)(\w+)\((\w+)\)$#', //09:45 27Sep(Tue)
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3',
            '$1 $2 20$3',
            '$2 $3 $4, $1',
            "$4, $2 $3 $this->year, $1",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ (?<year>20\d{2})\b.*)/u", $str, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $this->year = $m['year'];

            return EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/^\d+ [[:alpha:]]+ (?<year>20\d{2})(,\s*\d{1,2}:\d{2}(?: ?[ap]m)?)?$/ui", $str, $m)) {
            $this->year = $m['year'];

            return strtotime($str);
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

    private function assignProvider(): bool
    {
        if ($this->http->XPath->query('//*[' . $this->contains('BRA SVERIGE AB') . ' or ' . $this->contains('ORGANISATIONSNR:556966-5994', 'translate(.," ","")') . ']')->length > 0) {
            $this->providerCode = 'malmo';

            return true;
        }

        if ($this->http->XPath->query('//node()[contains(normalize-space(.),"CARIBBEAN AIRLINES LIMITED") or contains(.,"@caribbean-airlines.com") or contains(normalize-space(.),"write to Caribbean Airlines")] | //a[contains(@href,"//caribbean-airlines.com") or contains(@href,"@CARIBBEAN-AIRLINES.COM")]')->length > 0) {
            $this->providerCode = 'caribbeanair';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,".elal.com/") or contains(@href,"www.elal.com") or contains(@href,"booking.elal.com")]')->length > 0) {
            $this->providerCode = 'israel';

            return true;
        }

        if ($this->http->XPath->query('//text()[contains(normalize-space(.),"EVA Air computer system") or contains(normalize-space(.),"EVA AIRWAYS") or contains(normalize-space(.),"WWW.EVAAIR.COM")]')->length > 0) {
            $this->providerCode = 'eva';

            return true;
        }

        if ($this->http->XPath->query('//node()[contains(.,"@srilankan.com")] | //a[contains(@href,"SRILANKAN AIRLINES LIMITED") or contains(@href,".srilankan.com")]')->length > 0) {
            $this->providerCode = 'srilankan';

            return true;
        }

        if (
            $this->http->XPath->query('//a[contains(@href,"www.airtahiti.com")]')->length > 0
        ) {
            $this->providerCode = 'tahitinui';

            return true;
        }

        if (
            $this->http->XPath->query('//a[contains(@href,"airkiosk.lot.com/")]')->length > 0
        ) {
            $this->providerCode = 'lotpair';

            return true;
        }

        if (
            $this->http->XPath->query('//a[contains(@href,"www.capeair.com/")]')->length > 0
            || $this->http->XPath->query('//node()[' . $this->contains(['Thank you for choosing Cape Air', 'please call 800-CAPE-AIR']) . ']')->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'CAPE AIR')]")->length > 0
        ) {
            $this->providerCode = 'cape';

            return true;
        }

        if (
            $this->http->XPath->query('//node()[' . $this->contains(['MALAYSIA AIRLINES,ECOMMERCE']) . ']')->length > 0
        ) {
            $this->providerCode = 'malaysia';

            return true;
        }

        if (
            $this->http->XPath->query('//node()[' . $this->contains(['ATLANTIC AIRWAYS INTERNET']) . ']')->length > 0
        ) {
            $this->providerCode = 'atlanticairways';

            return true;
        }

        if (
            $this->http->XPath->query('//node()[' . $this->contains(['HAWAIIAN AIRLINES']) . ']')->length > 0
            || $this->http->XPath->query('//node()[' . $this->contains(['AIRPORT - REMOTE, HONOLULU, HONOLULU, HONOLULU, HAWAII']) . ']')->length > 0
        ) {
            $this->providerCode = 'hawaiian';

            return true;
        }

        if (
            $this->http->XPath->query('//node()[' . $this->contains(['TAP +351211234400', 'www.flytap.com/', 'TAP INTERNET SALES', 'Issuing office: TRANSPORTES AEREOS PORTUGUESES S.A.']) . ']')->length > 0
        ) {
            $this->providerCode = 'tapportugal';

            return true;
        }

        if (
            $this->http->XPath->query('//node()[' . $this->contains(['0226955555', 'WORLD WIDE E RETAIL OFFICE, WORLD WIDE INTERNET, , CAIRO']) . ']')->length > 0
        ) {
            $this->providerCode = 'cairo';

            return true;
        }

        if (
            $this->http->XPath->query('//node()[contains(.,"@amadeus.com")] | //a[contains(@href,"@amadeus.com") or contains(@href,"amadeus.")]')->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'AMASZONAS')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'SOLOMON AIRLINES')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'PERUVIAN AIR LINE')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(), 'SMARTWINGS')]")->length > 0
        ) {
            $this->providerCode = 'amadeus';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(.),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m) || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m) || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)) {
            $m['t'] = str_replace(" ", "", $m['t']);
            $m['t'] = preg_replace("#,(\d{3})$#", '$1', $m['t']);

            if (strpos($m['t'], ',') !== false && strpos($m['t'], '.') !== false) {
                if (strpos($m['t'], ',') < strpos($m['t'], '.')) {
                    $m['t'] = str_replace(',', '', $m['t']);
                } else {
                    $m['t'] = str_replace('.', '', $m['t']);
                    $m['t'] = str_replace(',', '.', $m['t']);
                }
            }
            $tot = str_replace(",", ".", str_replace(' ', '', $m['t']));
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
}
