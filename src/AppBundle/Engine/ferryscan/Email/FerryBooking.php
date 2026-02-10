<?php

namespace AwardWallet\Engine\ferryscan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class FerryBooking extends \TAccountChecker
{
    public $mailFiles = "ferryscan/it-364207998.eml, ferryscan/it-372679468-fr.eml, ferryscan/it-361172975-el.eml";

    public $lang = '';

    public static $dictionary = [
        'el' => [
            'companyName'                      => ['Επωνυμία Εταιρείας:', 'Επωνυμία Εταιρείας :'],
            'shipName'                         => ['Όνομα Πλοίου:', 'Όνομα Πλοίου :'],
            'shipType'                         => ['Είδος Πλοίου:', 'Είδος Πλοίου :'],
            'Ferryscanner Booking Code'        => 'Κωδικός κράτησης Ferryscanner',
            'Reservation Number'               => ['Αριθμός κράτησης ακτοπλοϊκής εταιρείας', 'Κωδικός Κράτησης'],
            'Passenger ∆'                      => 'Επιβάτης ∆',
            'Full Name'                        => 'Ονοματεπώνυμο',
            'Seat Class'                       => 'Επιλογή Θέσης',
            'Seat Number'                      => 'Αριθμός Θέσης',
            'Cancellation Policy'              => 'Πολιτική Ακύρωσης',
            'Price for'                        => 'Τιμή για',
            'Total Price'                      => 'Τελική Τιμή',
            'Ticket Type'                      => 'Τύπος εισιτηρίων',
            'Adult'                            => 'Κανονικό Εισιτήριο (Ενήλικας)',
            //'Child' => '',
        ],
        'fr' => [
            'companyName'                      => ["Nom de l'entreprise:", "Nom de l'entreprise :"],
            'shipName'                         => ['Nom du bateau:', 'Nom du bateau :'],
            'shipType'                         => ['Type de bateau:', 'Type de bateau :'],
            'Ferryscanner Booking Code'        => 'Ferryscanner Code de réservation',
            'Reservation Number'               => 'Numéro de réservation de la compagnie de ferry',
            'Passenger ∆'                      => 'Passager ∆',
            'Full Name'                        => 'Nom complet',
            'Seat Class'                       => 'Seat class',
            'Seat Number'                      => 'Numéro de siège',
            'Cancellation Policy'              => "Politique d'annulation",
            'Price for'                        => 'Prix pour',
            'Total Price'                      => 'Prix total',
            'Ticket Type'                      => 'Type de billet',
            'Adult'                            => 'Adulte',
            //'Child' => '',
        ],
        'en' => [
            'companyName'                      => ['Company Name:', 'Company Name :', 'Company name:', 'Company name :'],
            'shipName'                         => ['Ship Name:', 'Ship Name :', 'Ship name:', 'Ship name :'],
            'shipType'                         => ['Ship Type:', 'Ship Type :', 'Ship type:', 'Ship type :'],
            'Ferryscanner Booking Code'        => ['Ferryscanner Booking Code', 'ferryscanner.com Booking code'],
            'Full Name'                        => ['Full Name', 'Full name'],
            'Seat Class'                       => ['Seat Class', 'Seat class'],
            'Seat Number'                      => ['Seat Number', 'Seat number'],
            'Total Price'                      => ['Total Price', 'Total price'],
        ],
    ];

    private $subjects = [
        'fr' => ['Confirmation de réservation du ferry'],
        'en' => ['Ferry Booking Confirmation'],
    ];

    private $detectors = [
        'el' => ['Στοιχεία ταξιδιού και επιβατών', 'Στοιχεία κράτησης'],
        'fr' => ['Détails du voyage et des passagers'],
        'en' => ['Trip & Passenger Details', 'Booking Details'],
    ];

    private $enDatesInverted = null;

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@ferryscanner.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".ferryscanner.com/") or contains(@href,"www.ferryscanner.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Ferryscanner All rights reserved")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('FerryBooking' . ucfirst($this->lang));

        $this->enDatesInverted = true;

        $xpathTime = '(starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(normalize-space(),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $confirmation = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Ferryscanner Booking Code'))}] ]/*[normalize-space()][2]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 ]/*[normalize-space()][1][{$this->eq($this->t('Ferryscanner Booking Code'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $email->ota()->confirmation($confirmation, $confirmationTitle);
        }

        $xpathRoute = "tr[ count(*)=3 and *[1]/descendant::text()[{$xpathTime}] and *[2][normalize-space()='' and descendant::img] and *[3]/descendant::text()[{$xpathTime}] ]";

        $tripList = $this->http->XPath->query("//{$xpathRoute}/ancestor::tr[{$this->contains($this->t('companyName'))}][1]/ancestor::*[ tr[normalize-space()][2] ][1]");

        foreach ($tripList as $root) {
            $f = $email->add()->ferry();

            $accommodations = [];
            $adults = 0;
            $kids = 0;
            $passengerRows = $this->http->XPath->query("tr[{$this->starts($this->t('Passenger ∆'), 'translate(normalize-space(),"0123456789","∆∆∆∆∆∆∆∆∆∆")')}]", $root);

            foreach ($passengerRows as $pRow) {
                $traveller = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Full Name'))}]", $pRow, true, "/^{$this->opt($this->t('Full Name'))}[:\s]*({$patterns['travellerName']})$/u");
                $f->general()->traveller($traveller, true);

                $adult = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Ticket Type'))}]", $pRow, true, "/^{$this->opt($this->t('Ticket Type'))}.*({$this->opt($this->t('Adult'))})$/u");
                $kid = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Ticket Type'))}]", $pRow, true, "/^{$this->opt($this->t('Ticket Type'))}.*({$this->opt($this->t('Child'))})$/u");
                $this->logger->debug($adult);

                if (!empty($adult)) {
                    $adults++;
                }

                if (!empty($kid)) {
                    $kids++;
                }

                $seatParts = [];

                $seatClass = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Seat Class'))}]", $pRow, true, "/^{$this->opt($this->t('Seat Class'))}[:\s]*([^–\-\s].+?)[*\s]*$/u");

                if ($seatClass) {
                    $seatParts[] = $seatClass;
                }

                $seatNumber = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant::tr/*[not(.//tr) and {$this->starts($this->t('Seat Number'))}]", $pRow, true, "/^{$this->opt($this->t('Seat Number'))}[:\s]*([^–\-\s].*?)[*\s]*$/u");

                if ($seatNumber) {
                    $seatParts[] = $seatNumber;
                }

                if (count($seatParts) > 0) {
                    $accommodations[] = implode(', ', $seatParts);
                }
            }

            $departureText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::{$xpathRoute}[1]/*[1]", null, $root));
            $depCity = preg_match("/^[ ]*(.{2,}?)[ ]*(?:\n|$)/", $departureText, $m) ? $m[1] : null;

            $arrivalText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::{$xpathRoute}[1]/*[3]", null, $root));
            $arrCity = preg_match("/^[ ]*(.{2,}?)[ ]*(?:\n|$)/", $arrivalText, $m) ? $m[1] : null;

            if ($depCity && $arrCity) {
                $xpathResNum = "//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('Reservation Number'))}] and preceding::tr[*[2] and *[normalize-space()][1]][1]/*[normalize-space()][1][{$this->eq($depCity . ' - ' . $arrCity)}] ]";
                $confirmation = $this->http->FindSingleNode($xpathResNum . "/*[normalize-space()][2]/descendant::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

                if ($confirmation) {
                    $confirmationTitle = $this->http->FindSingleNode($xpathResNum . "/*[normalize-space()][1]", null, true, '/^(.+?)[\s:：]*$/u');
                    $f->general()->confirmation($confirmation, $confirmationTitle);
                }
            }

            $companyName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('companyName'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);

            $vessel = null;
            $vesselParts = [];

            $shipName = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('shipName'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);

            if ($shipName !== null) {
                $vesselParts[] = $shipName;
            }

            $shipType = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('shipType'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root);

            if ($shipType !== null) {
                $vesselParts[] = $shipType;
            }

            if (count($vesselParts) > 0) {
                $vessel = implode(', ', $vesselParts);
            }

            $segmentsText = $this->htmlToText($this->http->FindHTMLByXpath("descendant::{$xpathRoute}[1]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]", null, $root));

            if (!preg_match_all("/^[ ]*(?<date>.{6,}?)[ ]+(?<time>{$patterns['time']})[ ]+(?<name>.{2,}?)[ ]*$/m", $segmentsText, $pointMatches, PREG_SET_ORDER)) {
                // 06/06/2023 13:20 Ios Ios (IOS), Greece
                continue;
            }

            $pointsCount = count($pointMatches);

            foreach ($pointMatches as $i => $m) {
                $dateTime = strtotime($m['time'], strtotime($this->normalizeDate($m['date'])));

                if ($i === 0) {
                    $s = $f->addSegment();

                    if (!empty($adults)) {
                        $s->booked()
                            ->adults($adults);
                    }

                    if (!empty($kids)) {
                        $s->booked()
                            ->kids($kids);
                    }
                    $s->departure()->date($dateTime)->name($m['name']);

                    continue;
                }

                if (isset($s)) {
                    /** @var \AwardWallet\Schema\Parser\Common\FerrySegment $s */
                    $s->arrival()->date($dateTime)->name($m['name']);
                    $s->extra()->carrier($companyName, false, true)->vessel($vessel, false, true);

                    if (count($accommodations) > 0) {
                        $s->booked()->accommodations(array_unique($accommodations));
                    }
                }

                if ($i !== ($pointsCount - 1)) {
                    $s = $f->addSegment();
                    $s->departure()->date($dateTime)->name($m['name']);
                }
            }

            $cancellation = $this->http->FindSingleNode("tr[{$this->eq($this->t('Cancellation Policy'))}]/following::tr[not(.//tr) and normalize-space()][1]", $root);
            $f->general()->cancellation($cancellation);

            $priceFor = $this->http->FindSingleNode("tr/descendant-or-self::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Price for'))}] ]/*[normalize-space()][2]", $root, true, '/^.*\d.*$/');

            if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $priceFor, $matches)
                || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $priceFor, $matches)
            ) {
                // €182.96
                $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
                $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
            }
        }

        $totalPrice = $this->http->FindSingleNode("//*[(self::p or self::div) and {$this->eq($this->t('Total Price'))}]/following-sibling::node()[normalize-space()][1]", $root, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
        ) {
            // €365.92    |    148,30 EUR
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $email->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['companyName'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['companyName'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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

    /**
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): string
    {
        if (!is_string($text) || empty($text)) {
            return '';
        }
        $in = [
            // 16/08/2023
            '/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/',
        ];
        $out[0] = $this->enDatesInverted === true ? '$2/$1/$3' : '$1/$2/$3';

        return preg_replace($in, $out, $text);
    }

    private function htmlToText(?string $s, bool $brConvert = true): string
    {
        if (!is_string($s) || $s === '') {
            return '';
        }
        $s = str_replace("\r", '', $s);
        $s = preg_replace('/<!--.*?-->/s', '', $s); // comments

        if ($brConvert) {
            $s = preg_replace('/\s+/', ' ', $s);
            $s = preg_replace('/<[Bb][Rr]\b.*?\/?>/', "\n", $s); // only <br> tags
        }
        $s = preg_replace('/<[A-z][A-z\d:]*\b.*?\/?>/', '', $s); // opening tags
        $s = preg_replace('/<\/[A-z][A-z\d:]*\b[ ]*>/', '', $s); // closing tags
        $s = html_entity_decode($s);
        $s = str_replace(chr(194) . chr(160), ' ', $s); // NBSP to SPACE

        return trim($s);
    }
}
