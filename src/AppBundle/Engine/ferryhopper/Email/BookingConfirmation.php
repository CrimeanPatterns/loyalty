<?php

namespace AwardWallet\Engine\ferryhopper\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "ferryhopper/it-150218669.eml, ferryhopper/it-158322253.eml, ferryhopper/it-98264154.eml, ferryhopper/it-98461270.eml";
    public $subjects = [
        // en
        'Ferry booking confirmation',
        // el
        'Επιβεβαίωση κράτησης ακτοπλοϊκών εισιτηρίων',
        // fr
        'Confirmation de votre réservation',
        // it
        'Conferma di prenotazione del traghetto',
    ];

    public $detectBody = [
        'en' => ['Here is all your booking information', 'you will find all the information you need concerning',
            'Thank you for choosing us. Here you will find all your booking information', 'Did you enjoy your experience with Ferryhopper', ],
        'el' => 'Εδώ θα βρεις όλες τις πληροφορίες για την κράτησή σου',
        'fr' => 'Voici l\'ensemble des informations attachées à votre réservation',
        'it' => 'Qui troverai tutte le informazioni riguardo la tua prenotazione',
        'de' => 'Danke, dass du Ferryhopper gewählt hast',
    ];

    public $lang = 'en';
    public $date;

    public static $dictionary = [
        "en" => [
            'Booking details'      => ['Booking details'],
            'Reservation details'  => ['Reservation details'],
            'Ferryhopper code'     => ['Booking reference', 'Ferryhopper code'],
            //            'Payment details' => '',
            //            'Lead passenger' => '',
            //            'Bonus Club' => '',

            // Type 1
            //            'Reservation code:' => '',
            //            'Total' => '',
            'Adult fare'      => ['Adult fare', 'Senior', 'Adult (full fare)'],
            'Child fare'      => ['Child fare', 'Infant'],
            "Payment details" => ["Payment details", "Payment information", "Boarding process", "Price breakdown"],

            // Type 2
            //            'Overall price' => '',
            //            'Company Code' => '',
            //            'Tickets' => '',
            //            'car' => '',
        ],
        "el" => [
            'Booking details'  => ['Στοιχεία κράτησης'],
            //            'Reservation details'  => [''],
            'Ferryhopper code' => 'Κωδικός Ferryhopper',
            'Payment details'  => 'Στοιχεία πληρωμής',
            'Lead passenger'   => 'Όνομα κράτησης',
            //            'Bonus Club' => '',

            // Type 1
            'Reservation code:' => 'Κωδικός κράτησης:',
            'Total'             => 'Σύνολο',
            'Adult fare'        => 'Κανονικό εισιτήριο',
            //            'Child fare' => '',

            // Type 2
            //            'Overall price' => '',
            //            'Company Code' => '',
            //            'Tickets' => '',
            //            'car' => '',
        ],
        "fr" => [
            'Booking details'  => ['Récapitulatif de la réservation'],
            //            'Reservation details'  => ['Reservation details'],
            'Ferryhopper code' => ['Référence Ferryhopper', 'Référence de réservation'],
            'Payment details'  => 'Détails de votre paiement',
            'Lead passenger'   => 'Passager principal',
            //            'Bonus Club' => '',

            // Type 1
            'Reservation code:' => ['N° de réservation:', 'N° de réservation :'],
            'Total'             => 'Total',
            'Adult fare'        => 'Adulte',
            //            'Child fare' => '',

            // Type 2
            //            'Overall price' => '',
            //            'Company Code' => '',
            //            'Tickets' => '',
            //            'car' => '',
        ],
        "it" => [
            'Booking details'  => ['Dettagli prenotazione'],
            // 'Reservation details'  => [''],
            'Ferryhopper code' => 'Numero di prenotazione',
            'Payment details'  => 'Dettagli di pagamento',
            'Lead passenger'   => 'Passeggero principale',
            // 'Bonus Club' => '',

            // Type 1
            'Reservation code:' => ['Codice della prenotazione:', 'Codice della prenotazione :'],
            'Total'             => 'Prezzo finale',
            'Adult fare'        => 'Tariffa adulti',
            'Child fare'        => 'Tariffa bambini',

            // Type 2
            // 'Overall price' => '',
            // 'Company Code' => '',
            // 'Tickets' => '',
            // 'car' => '',
        ],
        "de" => [
            'Booking details'  => ['Buchungsdaten'],
            // 'Reservation details'  => [''],
            'Ferryhopper code' => 'Ferryhopper-Code',
            'Payment details'  => 'Zahlungsdetails',
            'Lead passenger'   => 'Buchungsname',
            // 'Bonus Club' => '',

            // Type 1
            'Reservation code:' => ['Buchungsnummer:'],
            'Total'             => 'Gesamt',
            'Adult fare'        => ['Erwachsene', 'Student', 'Ermäßigung griechische Universität'],
            //'Child fare'        => '',

            // Type 2
            // 'Overall price' => '',
            // 'Company Code' => '',
            // 'Tickets' => '',
            // 'car' => '',
        ],
    ];

    private $patterns = [
        'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
        'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]',
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@ferryhopper.com') !== false) {
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
        if ($this->http->XPath->query("//a[contains(@href, 'ferryhopper')]")->length == 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]ferryhopper\.com$/', $from) > 0;
    }

    public function ParseFerry(Email $email): void
    {
        // Examples: it-98461270.eml

        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->ferry();

        $f->setAllowTzCross(true);

        $confirmationVals = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t("Payment details"))}]/following::text()[{$this->starts($this->t("Reservation code:"))}][1]"));

        foreach ($confirmationVals as $confirmationVal) {
            $this->logger->warning($confirmationVal);

            if (preg_match("/({$this->opt($this->t('Reservation code:'))})[:\s]*([-A-z\d]{4,})$/", $confirmationVal, $m)) {
                $f->general()->confirmation($m[2], rtrim($m[1], ': '));
            }
        }

        $traveller = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t("Lead passenger"))}] ]/*[normalize-space()][2]", null, true, "/^{$this->patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//tr/*[ count(table[normalize-space()])=2 and table[normalize-space()][1][{$this->starts($this->t("Lead passenger"))}] ]/table[normalize-space()][2]", null, true, "/^{$this->patterns['travellerName']}$/u")
            ?? $this->http->FindSingleNode("//tr/*[ count(div[normalize-space()])=2 and div[normalize-space()][1][{$this->starts($this->t("Lead passenger"))}] ]/div[normalize-space()][2]", null, true, "/^{$this->patterns['travellerName']}$/u")
        ;
        $f->general()->traveller($traveller, true);

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Payment details"))}]/following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Total"))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 201.20 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
        }

        $accounts = $this->http->FindNodes("//text()[" . $this->contains($this->t("Bonus Club")) . "]", null, "/{$this->opt($this->t('Bonus Club'))}\s*(\d+)/");

        if (!empty($accounts)) {
            $f->setAccountNumbers($accounts, true);
        }

        $xpathArrow = "(contains(.,'→') or contains(.,'¡æ'))";
        $xpath = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$xpathArrow}] and *[normalize-space()][2][not({$xpathArrow})] ]    |    //tr/*[ count(table[normalize-space()])=2 and table[normalize-space()][1][{$xpathArrow}] and table[normalize-space()][2][not({$xpathArrow})] ]    |    //tr/*[ count(div[normalize-space()])=2 and div[normalize-space()][1][{$xpathArrow}] and div[normalize-space()][2][not({$xpathArrow})] ]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $depArrInfo = $this->http->FindSingleNode("*[normalize-space()][1]", $root);

            if (preg_match("/^(.{2,}?)\s*(?:→|¡æ)\s*(.{2,})$/u", $depArrInfo, $m)) {
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            $date = $this->http->FindSingleNode("*[normalize-space()][2]", $root, null, '/^.*\d.*$/');

            $xpathSeparator = "(contains(.,'··') or contains(.,'¡¤¡¤'))";
            $xpathRow = '(self::tr or self::table or self::div)';
            $xpathFollowRow = "ancestor-or-self::*[ {$xpathRow} and following-sibling::*[{$xpathRow} and normalize-space()] ][1]/following-sibling::*[{$xpathRow} and normalize-space()][1]";

            if ($this->http->XPath->query($xpathNextRow = $xpathFollowRow . "/descendant-or-self::tr[ count(*[normalize-space()]) and *[normalize-space()][2][{$xpathSeparator}] ][1]", $root)->length !== 1
                && $this->http->XPath->query($xpathNextRow = $xpathFollowRow . "/descendant-or-self::tr/*[ count(table[normalize-space()]) and table[normalize-space()][2][{$xpathSeparator}] ][1]", $root)->length !== 1
                && $this->http->XPath->query($xpathNextRow = $xpathFollowRow . "/descendant-or-self::tr/*[ count(div[normalize-space()]) and div[normalize-space()][2][{$xpathSeparator}] ][1]", $root)->length !== 1
            ) {
                $xpathNextRow = '/';
            }

            $s->extra()
                ->vessel($this->http->FindSingleNode($xpathNextRow . "/*[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][1]", $root))
                ->carrier($this->http->FindSingleNode($xpathNextRow . "/*[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()][2]", $root));

            $overnight = $this->http->XPath->query($xpathNextRow . "/*[normalize-space()][2]/descendant::img", $root)->length === 1;

            $times = $this->http->FindSingleNode($xpathNextRow . "/*[normalize-space()][2]", $root);

            if (preg_match("/^(?<time1>{$this->patterns['time']})\s*(?:··|¡¤¡¤)\s*(?<duration>\d[\d hm]*)\s*(?:··|¡¤¡¤)\s*(?<time2>{$this->patterns['time']})$/", $times, $m)) {
                // 15:00 ·· 2h 5m ·· 17:05
                $dateDep = $this->normalizeDate($date . ' ' . $m['time1']);
                $s->departure()->date($dateDep);
                $dateArr = $this->normalizeDate($date . ' ' . $m['time2']);
                $s->arrival()->date($overnight && !empty($dateDep) && !empty($dateArr) && $dateDep > $dateArr ? strtotime('+1 day', $dateArr) : $dateArr);
                $s->extra()->duration($m['duration']);
            }

            $adult = $this->http->FindNodes("./following::text()[" . $this->starts($this->t("Adult fare")) . "][1]/ancestor::table[1]/descendant::text()[" . $this->starts($this->t("Adult fare")) . "]", $root);
            $s->booked()
                ->adults(count($adult));

            $accom = array_filter(array_unique($this->http->FindNodes("./following::text()[" . $this->starts($this->t("Adult fare")) . "][1]/ancestor::table[1]/descendant::text()[" . $this->starts($this->t("Adult fare")) . " or " . $this->starts($this->t("Child fare")) . "]", $root, "/\,(.+)/")));

            if (count($accom) > 0) {
                $s->setAccommodations($accom);
            }

            $child = $this->http->FindNodes("./following::text()[" . $this->starts($this->t("Adult fare")) . "][1]/ancestor::table[1]/descendant::text()[" . $this->starts($this->t("Child fare")) . "]", $root);

            if (count($child) > 0) {
                $s->booked()
                    ->kids(count($child));
            }

            $vehicleType = $this->http->FindNodes("./following::text()[" . $this->starts($this->t("Adult fare")) . "][1]/ancestor::table[1]/descendant::text()[" . $this->contains($this->t(" car")) . "]", $root);

            foreach ($vehicleType as $vt) {
                $s->addVehicle()->setType(str_replace(['>'], ['more'], $vt));
            }
        }
    }

    public function ParseFerry2(Email $email): void
    {
        // Examples: it-98264154.eml

        $this->logger->debug(__FUNCTION__);

        $f = $email->add()->ferry();

        $f->setAllowTzCross(true);

        $f->general()
            ->traveller($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Lead passenger")) . "]/following::text()[normalize-space()][1]"), true);

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Payment details"))}]/following::*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Overall price"))}] ]/*[normalize-space()][2]", null, true, "/^.*\d.*$/");

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 558.08 €
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode))->currency($currency);
        }

        $accounts = $this->http->FindNodes("//text()[" . $this->contains($this->t("Bonus Club")) . "]", null, "/{$this->opt($this->t('Bonus Club'))}\s*(\d+)/");

        if (!empty($accounts)) {
            $f->setAccountNumbers($accounts, true);
        }

        $xpath = "//img[contains(@src, 'email-angle-right')]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $bookingCode = $this->http->FindSingleNode("./following::text()[" . $this->contains($this->t("Company Code")) . "][1]/following::text()[normalize-space()][1]", $root, true, "/^([A-Z\d]+)$/");

            if (!empty($f->getConfirmationNumbers()[0][0])) {
                if (!empty($bookingCode) && !in_array($bookingCode, $f->getConfirmationNumbers()[0])) {
                    $f->general()
                        ->confirmation($bookingCode);
                }
            } else {
                $f->general()
                    ->confirmation($bookingCode);
            }

            $date = $this->http->FindSingleNode("./preceding::text()[normalize-space()][4]", $root);
            $depTime = $this->http->FindSingleNode("./preceding::text()[normalize-space()][1]", $root);
            $arrTime = $this->http->FindSingleNode("./following::text()[normalize-space()][2]", $root);

            $s->departure()
                ->date($this->normalizeDate($date . ' ' . $depTime))
                ->name($this->http->FindSingleNode("./preceding::text()[normalize-space()][2]", $root));

            $s->arrival()
                ->date($this->normalizeDate($date . ' ' . $arrTime))
                ->name($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root));

            $vesselText = $this->http->FindSingleNode("./preceding::text()[contains(normalize-space(), ':')][1]/ancestor::tr[1]/preceding::tr[1]", $root);

            if (preg_match("/^(.+)\,\s*(.+)/", $vesselText, $m)) {
                $s->extra()
                    ->vessel($m[2])
                    ->carrier($m[1]);
            }

            $accom = array_filter($this->http->FindNodes("./following::text()[" . $this->contains($this->t("Tickets")) . "][1]/following::text()[contains(normalize-space(), 'x')][1]/ancestor::tr[1]/descendant::text()[not(" . $this->contains($this->t("car")) . ")]", $root, "/^\d+\s*x\s*(.+)/"));

            if (count($accom) > 0) {
                $s->setAccommodations($accom);
            }

            $vehicleType = $this->http->FindSingleNode("./following::text()[" . $this->contains($this->t("Tickets")) . "][1]/following::text()[contains(normalize-space(), 'x')][1]/ancestor::tr[1]/descendant::text()[" . $this->contains($this->t("car")) . "]", $root, true, "/^\d+\s*x\s*(.+)/");

            if (!empty($vehicleType)) {
                $s->addVehicle()->setType($vehicleType);
            }
        }
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict['Booking details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Booking details'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }

            if (isset($dict['Reservation details']) && $this->http->XPath->query("//text()[{$this->contains($dict['Reservation details'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $this->date = strtotime($parser->getDate());

        $otaConfirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Ferryhopper code"))}]/following::text()[normalize-space()][1]");

        if ($otaConfirmation) {
            $otaConfirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Ferryhopper code"))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($otaConfirmation, $otaConfirmationTitle);
        }

        if ($this->http->XPath->query("//text()[" . $this->contains($this->t("Booking details")) . "]")->length > 0) {
            $this->ParseFerry($email);
        }

        if ($this->http->XPath->query("//text()[" . $this->contains($this->t("Reservation details")) . "]")->length > 0) {
            $this->ParseFerry2($email);
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
        return count(self::$dictionary) * 2;
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
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
//         $this->logger->debug('$date = '.print_r( $date,true));
        $year = date('Y', $this->date);
        $in = [
            // Fri, 16 July 15:00; mar., 20 juillet 17:00
            '#^\w+[.]?\,\s*(\d+)\s*(\w+)\s*([\d\:]+)$#u',
        ];
        $out = [
            '$1 $2 ' . $year . ', $3',
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'GBP' => ['£'],
            'EUR' => ['€'],
            'INR' => ['Rs.', 'Rs'],
            'AUD' => ['AU $'],
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
