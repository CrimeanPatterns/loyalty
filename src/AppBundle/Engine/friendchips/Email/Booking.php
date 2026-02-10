<?php

namespace AwardWallet\Engine\friendchips\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Booking extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-276604116.eml, friendchips/it-280932848.eml, friendchips/it-5883388.eml, friendchips/it-5888094.eml, friendchips/it-5953325.eml, friendchips/it-6019894.eml, friendchips/it-6027713.eml, friendchips/it-7173707.eml, friendchips/it-7669137.eml, friendchips/it-8219693.eml, friendchips/it-8568647.eml";

    protected static $dict = [
        'it' => [],
        'fr' => [
            'Volo' => 'Vol',
            //			'per la prenotazion' => '',
            'Partenza'       => 'Départ',
            'Arrivo'         => 'Arrivée',
            'Posti a sedere' => 'Siège',
            //pdf
            'Codice volo' => ['Numéro du vol', 'NUMÉRO DU VOL'],
        ],
        'es' => [
            //			'Volo' => '',
            'per la prenotazion' => 'para la reserva',
            'Partenza'           => 'Salida del vuelo',
            'Arrivo'             => 'Llegada',
            'Posti a sedere'     => 'Asientos',
            //pdf
            'Codice volo' => ['N  de vuelo', 'N° de vuelo'],
        ],
        'de' => [
            //			'Volo' => '',
            'per la prenotazion' => 'Bordkarte für Buchung',
            'Partenza'           => 'Abflug',
            'Arrivo'             => 'Ankunft',
            'Posti a sedere'     => 'Sitzplätze',
            //pdf
            'Codice volo' => ['Flug-Nr.'],
        ],
        'nl' => [
            'Volo' => 'Vlucht',
            //			'per la prenotazion' => '',
            'Partenza'       => 'Vertrek',
            'Arrivo'         => 'Aankomst',
            'Posti a sedere' => 'Stoel',
            //pdf
            'Codice volo' => ['Vluchtnummer', 'VLUCHTNUMMER'],
        ],
        'en' => [
            'Volo' => 'Flight',
            //			'per la prenotazion' => '',
            'Partenza'       => 'Departure',
            'Arrivo'         => 'Arrival',
            'Posti a sedere' => ['seat', 'Seat'],
            //pdf
            'Codice volo' => ['Flight number', 'FLIGHT NUMBER', 'Flight no.', 'Flight no'],
        ],
    ];

    private $reSubject = [
        'it' => ["carte d'imbarco"],
        'fr' => ["Carte d'embarquement"],
        'es' => ['tarjeta de embarque'],
        'de' => ['Bordkarten'],
        //		'nl' => [''],
        'en' => ['Boarding pass'],
    ];

    private $langDetectors = [
        'it' => ['ti augura un piacevole volo!'],
        'fr' => ['Vous trouverez la confirmation en pièce jointe'],
        'es' => ['le desea un buen vuelo'],
        'de' => ['wünscht Ihnen einen angenehmen Flug'],
        'nl' => ['U bent ingecheckt voor uw vlucht', 'Hier is uw instapkaart voor uw vlucht'],
        'en' => ['You have successfully checked in'],
    ];

    private $lang = '';

    /** @var \HttpBrowser */
    private $pdf;
    private $pdfNamePattern = ".*(?:Boarding|Carta|Tarjeta|Bordkarte).*(?:pass|imbarco|embarque)?.*pdf";
    private $arrayPDF;

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        if (isset($pdfs) && count($pdfs) > 0) {
            $htmlPdf = '';

            foreach ($pdfs as $pdf) {
                if (($htmlPdf .= \PDF::convertToHtml($parser->getAttachmentBody($pdf), \PDF::MODE_SIMPLE)) !== null) {
                    $file = $parser->getAttachment($pdf);
                    $this->arrayPDF[] = $this->re('/filename["=]+(.+\.pdf)\"/u', $file['headers']['content-disposition']);
                } else {
                    return null;
                }
            }
            $NBSP = chr(194) . chr(160);
            $htmlPdf = str_replace([$NBSP, '&#160;', '&nbsp;', '  ', '​'], ' ', html_entity_decode($htmlPdf));
            $this->pdf = clone $this->http;
            $this->pdf->SetEmailBody($htmlPdf);
        }

        if ($this->assignLang() === false) {
            return false;
        }

        $this->parseEmail($email);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->detectBody($parser);
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]tuifly\.com/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['subject'], ' TUIfly') === false && strpos($headers['subject'], ' TUI fly') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
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

    protected function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    protected function getFlightFromPdf($departureArr, $arrivalArr)
    {
        $result = [];

        $patternFlight = '/^([A-Z\d]{2})\s*(\d+)$/';
        $xpathFragment1 = '[' . $this->eq($this->t('Codice volo')) . '][1]/following::text()[normalize-space(.)][position()<8]';

        $flightTexts = $this->pdf->FindNodes('//text()[' . $this->eq((array) $departureArr) . ']/following::text()[normalize-space(.)][1][' . $this->eq((array) $arrivalArr) . ']/following::text()[normalize-space(.)][position()<10]' . $xpathFragment1);

        foreach ($flightTexts as $flightText) {
            if (preg_match($patternFlight, $flightText, $matches)) {
                $result['AirlineName'] = $matches[1];
                $result['FlightNumber'] = $matches[2];

                break;
            }
        }

        if (empty($result['AirlineName']) || empty($result['FlightNumber'])) {
            $flightTexts = $this->pdf->FindNodes('//text()[' . $this->eq((array) $arrivalArr) . ']/preceding::text()[normalize-space(.)][1][' . $this->eq((array) $departureArr) . ']/preceding::text()[normalize-space(.)][position()<18]' . $xpathFragment1);

            foreach ($flightTexts as $flightText) {
                if (preg_match($patternFlight, $flightText, $matches)) {
                    $result['AirlineName'] = $matches[1];
                    $result['FlightNumber'] = $matches[2];

                    break;
                }
            }
        }
        //it-276604116.eml
        if (empty($result['AirlineName']) || empty($result['FlightNumber'])) {
            $newArrivalArr = [];

            foreach ($arrivalArr as $arrival) {
                $newArrivalArr[] = $this->re("/^(\w+)/", $arrival);
            }

            $newDepartureArr = [];

            foreach ($departureArr as $departure) {
                $newDepartureArr[] = $this->re("/^(\w+)/", $departure);
            }

            $flightTexts = $this->pdf->FindNodes("//text()[{$this->eq($newArrivalArr)} or {$this->eq($newDepartureArr)}]/following::text()[{$this->eq($this->t('Codice volo'))}]/following::text()[normalize-space()][3]");

            foreach ($flightTexts as $flightText) {
                if (preg_match($patternFlight, $flightText, $matches)) {
                    $result['AirlineName'] = $matches[1];
                    $result['FlightNumber'] = $matches[2];

                    break;
                }
            }
        }

        return $result;
    }

    protected function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    protected function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $confNumber = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t('per la prenotazion') . "')]", null, true, '/\b([A-Z\d]{5,7})\b/');

        if (empty($confNumber) && ($this->lang === 'fr' || $this->lang === 'nl' || $this->lang === 'en')) {
            $f->general()
                ->noConfirmation();
        } else {
            $f->general()
                ->confirmation($confNumber);
        }

        $names = [];
        $inf = [];

        $xpath = "//img[contains(@src,'mail/flugzeug')]/ancestor::tr[2]/following-sibling::tr[1]";

        if ($this->http->XPath->query($xpath)->length === 0) {
            $xpath = "//td[normalize-space(.)='" . $this->t('Volo') . "']/ancestor::tr[3]/following-sibling::tr[contains(.,'" . $this->t('Partenza') . "')]/td/table";
        }
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $s = $f->addSegment();

            $codesOrNames = $this->http->FindSingleNode('descendant::tr[ancestor::*[count(tr)=2]][1]', $root);

            if (preg_match('/\b([A-Z]{3})\b\s*\D\s*\b([A-Z]{3})\b/', $codesOrNames, $m)) {
                $s->departure()
                    ->code($m[1]);
                $s->arrival()
                    ->code($m[2]);
            } elseif (preg_match('/(.+)\s+>\s+(.+)/', $codesOrNames, $m)) {
                $s->departure()
                    ->name($m[1]);
                $s->arrival()
                    ->name($m[2]);
            }

            $xpathDep = 'descendant::tr[contains(.,"' . $this->t('Partenza') . '")]/descendant::td[contains(.,"' . $this->t('Partenza') . '") and descendant::tr]';

            if ($this->http->XPath->query($xpathDep, $root)->length !== 1) {
                $xpathDep = 'descendant::tr[contains(.,"' . $this->t('Partenza') . '") and contains(.,"' . $this->t('Arrivo') . '")]/following-sibling::tr[1]/td[1]';
            }

            if ($this->http->XPath->query($xpathDep, $root)->length !== 1) {
                $xpathDep = 'descendant::tr[contains(.,"' . $this->t('Partenza') . '")]/following-sibling::tr[1]/td[1]';
                $s->arrival()
                    ->noDate();
            }
            $depDate = $this->http->FindSingleNode($xpathDep, $root);
            $s->departure()
                ->date($this->normalizeDate($depDate));

            $xpathArr = 'descendant::tr[contains(.,"' . $this->t('Partenza') . '")]/descendant::td[contains(.,"' . $this->t('Arrivo') . '") and descendant::tr]';

            if ($this->http->XPath->query($xpathArr, $root)->length !== 1) {
                $xpathArr = 'descendant::tr[contains(.,"' . $this->t('Partenza') . '") and contains(.,"' . $this->t('Arrivo') . '")]/following-sibling::tr[1]/td[2]';
            }
            $arrDate = $this->http->FindSingleNode($xpathArr, $root);

            if (($d = $this->normalizeDate($arrDate))) {
                $s->arrival()
                    ->date($d);
            }

            // Passengers
            $xpathNamesSeats = 'descendant::td[' . $this->contains($this->t('Posti a sedere')) . ']/descendant::img[contains(@src,"mail/mobilePass")]/ancestor::tr[3]/preceding-sibling::tr[2]';

            if ($this->http->XPath->query($xpathNamesSeats, $root)->length === 0) {
                $xpathNamesSeats = 'descendant::td[' . $this->contains($this->t('Posti a sedere')) . ']/descendant::tr[2]/descendant::tr[not(descendant::a[contains(@style,"092a5e")])][string-length(.) > 2]';
            }

            $inf = array_merge($inf, $this->http->FindNodes($xpathNamesSeats . '/td[1]/ancestor::tr[1][contains(normalize-space(), "INF")]/descendant::td[1]', $root));
            $this->logger->debug(var_export($this->http->FindNodes($xpathNamesSeats . '/td[1]/ancestor::tr[1][contains(normalize-space(), "INF")]/descendant::td[1]', $root), true));

            $names = array_merge($names, $this->http->FindNodes($xpathNamesSeats . '/td[1]/ancestor::tr[1][not(contains(normalize-space(), "INF"))]/descendant::td[1]', $root));

            $xpathSeats = $xpathNamesSeats . '/td[2]';

            if ($this->http->XPath->query($xpathSeats, $root)->length === 0) {
                $xpathSeats = $xpathNamesSeats . '/td[2]';
            }
            $seats = $this->http->FindNodes($xpathSeats . "[not(contains(normalize-space(), 'INF'))]", $root);
            $s->extra()
                ->seats($seats);

            if (isset($this->pdf)) {
                if (!empty($s->getDepName()) && !empty($s->getArrName())) { // example: it-5888094.eml, it-6027713.eml, it-7173707.eml, it-8352654.eml
                    // mb_ for: Priština -> PRIŠTINA
                    $depNameVariants = [$s->getDepName(), mb_strtoupper($s->getDepName())];
                    $arrNameVariants = [$s->getArrName(), mb_strtoupper($s->getArrName())];

                    $airportCodes = $this->pdf->FindNodes('//text()[' . $this->eq($arrNameVariants) . ']/preceding::text()[normalize-space(.)][1][' . $this->eq($depNameVariants) . ']/preceding::text()[normalize-space(.)][position()<3]', null, '/^([A-Z]{3})$/');

                    if (empty($airportCodes[0]) || empty($airportCodes[1])) {
                        $airportCodes = $this->pdf->FindNodes('//text()[' . $this->eq($depNameVariants) . ']/following::text()[normalize-space(.)][1][' . $this->eq($arrNameVariants) . ']/following::text()[normalize-space(.)][position()<3]', null, '/^([A-Z]{3})$/');
                    }

                    if (empty($airportCodes[0]) || empty($airportCodes[1])) {
                        $airportCodes = $this->pdf->FindNodes("//text()[{$this->eq($arrNameVariants)} or {$this->eq($depNameVariants)}]/preceding::text()[normalize-space(.)][1]", null, '/^([A-Z]{3})$/');
                        //it-276604116.eml
                        if (count(array_filter($airportCodes)) == 0) {
                            $newArrivalArr = [];

                            foreach ($arrNameVariants as $arrival) {
                                $newArrivalArr[] = $this->re("/^(\w+)/", $arrival);
                            }

                            $newDepartureArr = [];

                            foreach ($depNameVariants as $departure) {
                                $newDepartureArr[] = $this->re("/^(\w+)/", $departure);
                            }

                            $airportCodes = (array_filter($this->pdf->FindNodes("//text()[{$this->eq($newArrivalArr)} or {$this->eq($newDepartureArr)}]/preceding::text()[string-length()<5][1]", null, '/^([A-Z]{3})$/')));
                        }
                    }

                    if (!empty($airportCodes[0]) && !empty($airportCodes[1])) {
                        $s->departure()
                            ->code($airportCodes[0]);
                        $s->arrival()
                            ->code($airportCodes[1]);
                    }

                    $flightElements = $this->getFlightFromPdf($depNameVariants, $arrNameVariants);

                    if (!empty($flightElements['AirlineName']) && !empty($flightElements['FlightNumber'])) {
                        $s->airline()
                            ->name($flightElements['AirlineName'])
                            ->number($flightElements['FlightNumber']);
                    }
                } elseif (!empty($s->getDepCode()) && !empty($s->getArrCode())) { // examples: it-5883388.eml, it-5953325.eml, it-6019894.eml, it-7669137.eml
                    $flightElements = $this->getFlightFromPdf($s->getDepCode(), $s->getArrCode());

                    if (!empty($flightElements['AirlineName']) && !empty($flightElements['FlightNumber'])) {
                        $s->airline()
                            ->name($flightElements['AirlineName'])
                            ->number($flightElements['FlightNumber']);
                    } else {
                        $s->airline()
                            ->name('X3')
                            ->noNumber();
                    }
                }
            } else {
                if (!empty($s->getDepDate()) && !empty($s->getArrDate())) {
                    $s->airline()
                        ->name('X3')
                        ->noNumber();
                }

                if (empty($s->getDepCode()) && empty($s->getArrCode())) {
                    $s->departure()
                        ->noCode();

                    $s->arrival()
                        ->noCode();
                }
            }
        }

        $f->general()
            ->travellers(array_unique($names));

        if (count($inf) > 0) {
            $f->general()
                ->infants($inf);
        }

        foreach (array_unique($names) as $name) {
            foreach ($this->arrayPDF as $pdfFile) {
                $nameTemp = str_replace(['é'], ['e'], str_replace(" ", "_", $name));

                if (stripos($pdfFile, $nameTemp) !== false && !empty($f->getConfirmationNumbers()[0][0])) {
                    $bp = $email->add()->bpass();
                    $bp->setDepDate($s->getDepDate());
                    $bp->setTraveller($name);
                    $bp->setDepCode($s->getDepCode());
                    $bp->setFlightNumber($s->getAirlineName() . ' ' . $s->getFlightNumber());
                    $bp->setRecordLocator($f->getConfirmationNumbers()[0][0]);
                    $bp->setAttachmentName($pdfFile);
                }
            }
        }
    }

    private function normalizeDate($str)
    {
        $reReplace = [
            '/.*\s*(\d{2})[\/\.\-](\d{2})[\/\.\-](\d{4})\s+(\d{1,2}:\d{2})/' => '$2/$1/$3, $4',
        ];

        foreach ($reReplace as $re => $replacement) {
            return strtotime(preg_replace($re, $replacement, $str));
        }

        return null;
    }

    private function detectBody(PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(.,"TUIfly.com") or contains(.,"www.tuifly.com") or contains(.,"@www.tuifly.com")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.tuifly.com") or contains(@href,"//www.tuifly.be") or contains(@href,"//be.tuifly.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
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
