<?php

namespace AwardWallet\Engine\bla\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "bla/it-256393909.eml, bla/it-257981982.eml, bla/it-259722067.eml, bla/it-269482168.eml, bla/it-494632969.eml, bla/it-497248246.eml";

    public $pdfNamePattern = ".*\.pdf";

    public $date;
    public $lang = "en";
    public static $dictionary = [
        'en' => [
            // HTML
            'Bus:'                    => 'Bus:',
            'Passenger'               => 'Passenger',
            'Total price (incl. tax)' => 'Total price (incl. tax)',
            'Booking reference:'      => 'Booking reference:',

            // PDF Ticket
            'E-ticket'    => 'E-ticket',
            'Booking:'    => 'Booking:',
            'Price:'      => 'Price:',
            'Passenger:'  => 'Passenger:',
            'Departure:'  => 'Departure:',
            'Arrival:'    => 'Arrival:',
            'Bus number:' => 'Bus number:',

            // PDF Receipt
            'PAYMENT RECEIPT'   => 'PAYMENT RECEIPT',
            'Booking reference' => 'Booking reference',
            'Booking details'   => 'Booking details',
            'Total Price'       => 'Total Price',
        ],
        'de' => [
            // HTML
            'Bus:'                    => 'Bus:',
            'Passenger'               => 'Reisende/r',
            'Total price (incl. tax)' => 'Gesamtpreis (inkl. Steuern)',
            'Booking reference:'      => 'Buchungsnummer:',

            // PDF Ticket
            'E-ticket'    => 'E-ticket',
            'Booking:'    => 'Buchungscode:',
            'Price:'      => 'Preis:',
            'Passenger:'  => 'Passagier:',
            'Departure:'  => 'Abfahrt am:',
            'Arrival:'    => 'Ankunft am:',
            'Bus number:' => 'Buslinie:',

            // PDF Receipt
            'PAYMENT RECEIPT'   => 'PAYMENT RECEIPT',
            'Booking reference' => 'Booking reference',
            'Booking details'   => 'Booking details',
            'Total Price'       => 'Total Price',
        ],
        'it' => [
            // HTML
            'Bus:'                    => 'Autobus:',
            'Passenger'               => 'Passeggero',
            'Total price (incl. tax)' => 'Prezzo totale (tasse incl.)',
            'Booking reference:'      => 'Riferimento della prenotazione:',

            // PDF Ticket
            'E-ticket'    => ['Ecco il tuo e-ticket', 'E-TICKET'],
            'Booking:'    => 'Riferimento della prenotazione:',
            'Price:'      => 'Prezzo:',
            'Passenger:'  => 'Passeggero:',
            'Departure:'  => 'Partenza :',
            'Arrival:'    => 'Arrivo :',
            'Bus number:' => 'Autobus n°:',
            // PDF Receipt
            'PAYMENT RECEIPT'   => 'PAYMENT RECEIPT',
            'Booking reference' => 'Booking reference',
            'Booking details'   => 'Booking details',
            'Total Price'       => 'Total Price',
        ],
        'fr' => [
            // HTML
            'Bus:'                    => 'Bus:',
            'Passenger'               => 'Passager',
            'Total price (incl. tax)' => 'Prix TTC',
            'Booking reference:'      => 'Numéro de réservation :',

            // PDF Ticket
            'E-ticket'    => 'E-BILLET',
            'Booking:'    => 'N° de réservation :',
            'Price:'      => 'Prix :',
            'Passenger:'  => ['Passager :', 'Passager:'],
            'Departure:'  => 'Départ :',
            'Arrival:'    => 'Arrivée :',
            'Bus number:' => 'N° bus :',
            // PDF Receipt
            'PAYMENT RECEIPT'   => 'REÇU DE PAIEMENT',
            'Booking reference' => 'Référence de réservation',
            'Booking details'   => 'Récapitulatif de la réservation',
            'Total Price'       => 'Prix Total',
        ],
        'pt' => [
            // HTML
            'Bus:'                    => 'Autocarro:',
            'Passenger'               => 'Passageiro',
            'Total price (incl. tax)' => 'Total (impostos incl.)',
            'Booking reference:'      => 'Referência da reserva:',

            // PDF Ticket
            'E-ticket'    => 'Bilhete eletrónico',
            'Booking:'    => 'Reserva:',
            'Price:'      => 'Preço:',
            'Passenger:'  => 'Passageiro:',
            'Departure:'  => 'Partida:',
            'Arrival:'    => 'Chegada:',
            'Bus number:' => 'Número do autocarro:',
            // PDF Receipt
            'PAYMENT RECEIPT'   => 'RECIBO DE PAGAMENTO',
            'Booking reference' => 'Referência da reserva:',
            'Booking details'   => 'Detalhes da reserva',
            'Total Price'       => 'Total (impostos',
        ],
        'es' => [
            // HTML
            'Bus:'                    => 'Bus:',
            'Passenger'               => 'Pasajero',
            'Total price (incl. tax)' => 'Total (impuestos incluidos)',
            'Booking reference:'      => 'Referencia de la reserva:',

            // PDF Ticket
            'E-ticket'    => 'Billete electrónico',
            'Booking:'    => 'Reserva:',
            'Price:'      => 'Precio:',
            'Passenger:'  => 'Pasajero(a):',
            'Departure:'  => 'Salida:',
            'Arrival:'    => 'Llegada:',
            'Bus number:' => 'Nº de bus:',
            // PDF Receipt
            'PAYMENT RECEIPT'   => 'PAYMENT RECEIPT',
            'Booking reference' => 'Booking reference',
            'Booking details'   => 'Booking details',
            'Total Price'       => 'Total Price',
        ],
    ];

    private $detectFrom = "notification@blablacar.com";
    private $detectSubject = [
        // en
        'BlaBlaCar - Your booking confirmation for your travel on',
        'BlaBlaCar - Reissue of your booking confirmation',
        // de
        'BlaBlaCar - Deine Buchungsbestätigung für deine Fahrt am',
        // it
        'BlaBlaCar - La prenotazione per il viaggio al',
        // fr
        'BlaBlaCar - Votre réservation pour votre voyage du',
        'BlaBlaCar - Réédition de votre confirmation de réservation',
        // pt
        'BlaBlaCar – Confirmação de reserva da tua viagem a ',
        // es
        'Reserva de tu viaje BlaBlaCar Bus del ',
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && stripos($headers["subject"], 'BlaBlaCar') === false) {
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

        if (
            $this->http->XPath->query("//a[{$this->contains(['.blablacar.'], '@href')}]")->length === 0
            || $this->http->XPath->query("//*[{$this->contains(['BlaBlaCar'])}]")->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Booking reference:']) && $this->http->XPath->query("//*[{$this->starts($dict['Booking reference:'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $totals = [];
        $type = '';

        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if ($this->detectPdf($text) == true) {
                $type = 'Pdf';
                $this->parseEmailPdf($email, $text);

                continue;
            }

            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['PAYMENT RECEIPT']) && $this->containsText($text, $dict['PAYMENT RECEIPT']) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }

            if ($this->containsText($text, $this->t('PAYMENT RECEIPT')) !== false) {
                $code = $this->re("/\n *{$this->opt($this->t('Booking reference'))} +([A-Z\d]+)(?: {3,}|\n)/", $text);

                if ($code && preg_match("/\n *{$this->opt($this->t('Booking details'))}\n\s*.*{$this->opt($this->t('Total Price'))}(?:.*\n){1,3}.* {2,}(?<currency>[A-Z]{3}) {2,}(?<tax>\d[\d., ]*) {2,}(?<amount>\d[\d., ]*)\n/u", $text, $m)) {
                    $totals[$code] = [
                        'total'    => PriceHelper::parse($m['amount'], $m['currency']),
                        'tax'      => PriceHelper::parse($m['tax'], $m['currency']),
                        'currency' => $m['currency'],
                    ];
                }
            }
        }

        if (!empty($totals)) {
            foreach ($email->getItineraries() as $it) {
                $otaCode = array_unique(array_column($it->getTravelAgency()->getConfirmationNumbers(), 0));

                if (count($otaCode) === 1 && isset($totals[$otaCode[0]])) {
                    $it->price()
                        ->total($totals[$otaCode[0]]['total'])
                        ->currency($totals[$otaCode[0]]['currency'])
                        ->tax($totals[$otaCode[0]]['tax']);
                }
            }
        }

        if (count($email->getItineraries()) === 0) {
            foreach (self::$dictionary as $lang => $dict) {
                if (!empty($dict['Booking reference:']) && $this->http->XPath->query("//*[{$this->starts($dict['Booking reference:'])}]")->length > 0) {
                    $this->lang = $lang;

                    break;
                }
            }

            $this->date = strtotime($parser->getDate());
            $type = 'Html';
            $this->parseEmailHtml($email);
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
        // TODO check count types
        return count(self::$dictionary);
    }

    public function detectPdf($text)
    {
        if ($this->containsText($text, ['BlaBlaCar']) === false) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['E-ticket']) && $this->containsText($text, $dict['E-ticket']) !== false
                && !empty($dict['Bus number:']) && $this->containsText($text, $dict['Bus number:']) !== false
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailPdf(Email $email, ?string $textPdf = null)
    {
        $segments = $this->split("/\n *{$this->opt($this->t('E-ticket'))}\n( +{$this->opt($this->t('Booking:'))})/", "\n\n" . $textPdf);

        foreach ($segments as $sText) {
            $booking = $this->re("/^ *{$this->opt($this->t('Booking:'))} *([A-Z\d]{5,})\n/", $sText);

            if (empty($booking)) {
                $email->add()->bus()->obtainTravelAgency();

                return false;
            }

            unset($b);

            foreach ($email->getItineraries() as $it) {
                if (in_array($booking, array_column($it->getTravelAgency()->getConfirmationNumbers(), 0))) {
                    $b = $it;

                    break;
                }
            }

            if (!isset($b)) {
                $b = $email->add()->bus();

                $b->obtainTravelAgency();

                $b->ota()
                    ->confirmation($booking);

                $b->general()
                    ->noConfirmation();
            }

            $traveller = $this->re("/\n *{$this->opt($this->t('Passenger:'))} *(.+?)\s*\n/", $sText);

            if (empty($traveller) && $this->containsText($sText, $this->t('Passenger:')) == false) {
                $traveller = $this->re("/^\s*{$this->opt($this->t('Booking:'))} *(?:.+\s*\n){1,7} *([[:alpha:]\W]+)\n.*\d{1,2}\.\d{1,2}\.\d{4}\n/", $sText);

                if (preg_match("/^\s*((?:[[:alpha:]][ \-]?)+)\s*$/u", $traveller, $m)) {
                } elseif (preg_match("/^\s*[[:alpha:] ]+: *((?:[[:alpha:]][ \-]?)+)\s*$/u", $traveller, $m)) {
                    $traveller = trim($m[1]);
                } else {
                    $traveller = null;
                }
            }

            if (empty($traveller) || !in_array($traveller, array_column($b->getTravellers(), 0))) {
                $b->general()
                    ->traveller($traveller);
            }

            if (preg_match("/\n *{$this->opt($this->t('Price:'))} *(?<currency>[A-Z]{3}) *(?<amount>\d[\d., ]*)\s*\n/", $sText, $m)) {
                if ($b->getPrice()) {
                    $b->price()
                        ->total($b->getPrice()->getTotal() + (float) PriceHelper::parse($m['amount'], $m['currency']))
                        ->currency($m['currency']);
                } else {
                    $b->price()
                        ->total((float) PriceHelper::parse($m['amount'], $m['currency']))
                        ->currency($m['currency']);
                }
            }
            $tableText = $this->re("/\n( *{$this->opt($this->t('Departure:'))}(.+\n)+)\n/", $sText);
            $table = $this->createTable($tableText, $this->rowColumnPositions($this->inOneRow($tableText)));
            // $this->logger->debug('$table = '.print_r( $table,true));

            $s = $b->addSegment();

            if (count($table) == 2) {
                $re = "/(?:{$this->opt($this->t('Departure:'))}|{$this->opt($this->t('Arrival:'))})\n(?<date>.+)\n(?<name>.+)\n(?<address>[\s\S]+)$/";

                if (preg_match($re, $table[0], $m)) {
                    $s->departure()
                        ->name($m['name'])
                        ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
                        ->date($this->normalizeDate($m['date']))
                    ;
                }

                if (preg_match($re, $table[1], $m)) {
                    $s->arrival()
                        ->name($m['name'])
                        ->address(preg_replace('/\s+/', ' ', trim($m['address'])))
                        ->date($this->normalizeDate($m['date']))
                    ;
                }
            } else {
                $email->removeItinerary($b);

                return false;
            }

            $s->extra()
                ->number($this->re("/{$this->opt($this->t('Bus number:'))} *(.+)/", $sText));

            $seats = $this->getSeats($s->getNumber());

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }

            $segments = $b->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize($segment->toArray()) === serialize($s->toArray())) {
                        $b->removeSegment($s);

                        break;
                    }
                }
            }
        }

        return $email;
    }

    private function parseEmailHtml(Email $email)
    {
        $b = $email->add()->bus();

        $b->obtainTravelAgency();

        $b->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t("Booking reference:"))}]/following::text()[normalize-space()][1]"));

        $b->general()
            ->noConfirmation()
            ->travellers(array_unique($this->http->FindNodes("//tr[td[{$this->eq($this->t('Passenger'))}]]/following-sibling::tr[normalize-space()][1]/*[normalize-space()][1]")))
        ;

        $xpath = "//text()[" . $this->starts($this->t("Bus:")) . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $b->addSegment();

            if (preg_match("/^\s*{$this->opt($this->t('Bus:'))} *(\w{1,6})/", $root->nodeValue, $m)) {
                $s->extra()
                    ->number($m[1]);
            }

            $dxpath = "preceding::text()[normalize-space()][1]/ancestor::*[count(.//img)>1][1]";
            $text = implode("\n", $this->http->FindNodes($dxpath . "//text()[normalize-space()]", $root));
            $re = "/^\s*(?<date>.+)\s+(?<dTime>\d{1,2}:\d{1,2})\n(?<dAddress>(?<dName>.+)[\s\S]+?)\n\s*(?<aTime>\d{1,2}:\d{1,2})\n(?<aAddress>(?<aName>.+)[\s\S]+?)$/";

            if (preg_match($re, $text, $m)) {
                $date = $this->normalizeDate($m['date']);
                $s->departure()
                    ->date(!empty($date) ? strtotime($m['dTime'], $date) : null)
                    ->name($m['dName'])
                    ->address(preg_replace('/\s+/', ' ', trim($m['dAddress'])))
                ;
                $s->arrival()
                    ->date(!empty($date) ? strtotime($m['aTime'], $date) : null)
                    ->name($m['aName'])
                    ->address(preg_replace('/\s+/', ' ', trim($m['aAddress'])))
                ;

                if (!empty($s->getDepDate()) && !empty($s->getArrDate())
                    && $s->getArrDate() < $s->getDepDate() && strtotime("+1 day", $s->getArrDate()) > $s->getDepDate()) {
                    $s->arrival()
                        ->date(strtotime("+1 day", $s->getArrDate()));
                }
            }
            $seats = $this->getSeats($s->getNumber());

            if (!empty($seats)) {
                $s->extra()
                    ->seats($seats);
            }
        }

        $priceXpath = "//td[" . $this->eq($this->t("Total price (incl. tax)")) . "]/ancestor::tr[1]/ancestor::*[1]/tr[normalize-space()]";
        $priceNodes = $this->http->XPath->query($priceXpath);

        foreach ($priceNodes as $i => $root) {
            $name = $this->http->FindSingleNode("td[normalize-space()][1]", $root);
            $valueText = $this->http->FindSingleNode("td[normalize-space()][2]", $root);
            $valueCurrency = $valueAmount = null;

            if (preg_match("/^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/u", $valueText, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*\s*$/u", $valueText, $m)
            ) {
                $valueCurrency = $this->currency($m['currency']);
                $valueAmount = PriceHelper::parse($m['amount'], $valueCurrency);
            }

            if ($i == 0 && preg_match("/^\s*{$this->opt($this->t("Total price (incl. tax)"))}\s*$/", $name)) {
                $b->price()
                    ->total($valueAmount)
                    ->currency($valueCurrency);

                continue;
            }
            $b->price()
                ->fee($name, $valueAmount);
        }

        return true;
    }

    private function getSeats($number)
    {
        if (empty($number)) {
            return [];
        }
        $seats = [];
        $name = preg_replace("/^(.+)$/", '$1 ' . $number, $this->t('Bus:'));
        $xpath = "//*[{$this->eq($name)}]/following::text()[{$this->eq($this->t('Passenger'))}][1]/following::tr[normalize-space()][position() < 20]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if ($this->http->XPath->query(".//text()[{$this->eq($this->t('Passenger'))}]", $root)->length > 0) {
                continue;
            } elseif ($this->http->XPath->query("preceding::tr[not(.//tr)]/*[1][{$this->eq($this->t('Passenger'))}]", $root)->length > 0) {
                $seats[] = $this->http->FindSingleNode("*[2]", $root, true, "/^\s*([\dA-Z]{1,4})\s*$/");
            } else {
                break;
            }
        }

        return array_filter($seats);
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

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        $year = date("Y", $this->date);
        $in = [
            //  sab 17-12
            '/^\s*([[:alpha:]\-]+)[.]?[\s,]+(\d+)-(\d+)\s*$/ui',
        ];
        $out = [
            "$1, $2.$3.$year",
            //            "$1, $2.$3.",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date begin = ' . print_r( $date, true));

        if (preg_match("/^(?<week>[-[:alpha:]]+), (?<date>\d+\.\d+\.\d+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

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

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return preg_quote($s, $delimiter);
        }, $field)) . ')';
    }

    private function striposArray($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
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

    private function inOneRow($text)
    {
        if (empty($text)) {
            return '';
        }
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

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }
}
