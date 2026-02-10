<?php

namespace AwardWallet\Engine\finnair\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "finnair/it-113065216.eml, finnair/it-153183559.eml, finnair/it-160127980.eml, finnair/it-161884686.eml, finnair/it-162079218.eml, finnair/it-307027149.eml, finnair/it-38719148.eml, finnair/it-38935050.eml, finnair/it-8206213.eml, finnair/it-8232987.eml, finnair/it-8273429.eml";

    private $subjects = [
        'es' => ['Confirmación de su pedido con'],
        'fi' => ['Vahvistus varaustunnukselle'],
        'de' => ['Confirmation for your booking change', 'Bestätigung Ihrer Bestellung'],
        'ko' => ['Confirmation for your booking change'],
        'sv' => ['Bekräftelse på din bokning'],
        'en' => ['Confirmation for your order with id', 'Your new flight has been confirmed', 'Confirmation of your booking cancellation', 'Booking confirmation and details for'],
    ];

    private $langDetectors = [
        'ko' => ['예약 번호'],
        'es' => ['Gracias por reservar', 'Anote o imprima la referencia de la reserva'],
        'fi' => ['Kiitos varauksestasi'],
        'de' => ['Alle Passagiere', 'BUCHUNGSREFERENZ'],
        'sv' => ['Alla passagerare', 'Din bokning har avbokats'],
        'en' => ['Thank you for your booking', 'Thank you for booking with', 'Your new flight has been', 'You have successfully'],
    ];

    private $lang = '';
    private $subject = '';

    private static $dict = [
        'fi' => [
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking reference'       => 'Varaustunnus',
            'Price'                   => ['Hinta', 'Hintaerittely'],
            'Total price'             => ['Kokonaishinta'],
            'Grand total'             => 'Yhteensä',
            'Taxes, fees and charges' => 'Verot ja maksut',
            'Base fare'               => 'Perusmaksu',
            'Outbound flight'         => 'Meno',
            'Inbound flight'          => ['Paluu'],
            'Departure'               => ['Menolento', 'Paluulento'],
            'Terminal'                => 'Terminaali',
            'Operated by'             => 'Lentoa liikennöi',
            //'Purchase seats' => '',
            'All passengers'     => ['Matkustajien tiedot', 'Kaikki matkustajat'],
            'Passenger'          => 'Matkustaja',
            'First name'         => 'Etunimi',
            'Family name'        => 'Sukunimi',
            'Seats'              => 'Istuinpaikat',
            'cabin'              => 'Lipputyyppi:',
            'cabin'              => 'Lipputyyppi:',
            'Window seat'        => ['Ikkunapaikka', 'Keskipaikka'],
            'points'             => ['Aviosta'],
        ],
        'de' => [
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking reference'       => ['Buchungsreferenz', 'BUCHUNGSREFERENZ'],
            //'Price'                   => [],
            'Total price'             => ['Total price', 'Gesamtsumme'],
            'Grand total'             => ['Preisaufschlüsselung'],
            'Taxes, fees and charges' => ['Change fee', 'Steuern gesamt'],
            'Base fare'               => ['Fare difference', 'Basistarif'],
            'Outbound flight'         => 'Hinflug',
            'Inbound flight'          => ['Rückflug'],
            //'Departure'               => [''],
            'Terminal'                => 'Terminal',
            //'Operated by'             => '',
            //'Purchase seats' => '',
            'All passengers' => ['Alle Passagiere', 'ALLE PASSAGIERE'],
            'Passenger'      => ['Passagier', 'PASSAGIER'],
            'First name'     => 'Vorname',
            'Family name'    => 'Nachname',
            'Seats'          => 'Sitzplätze',
            'Duration'       => 'Gesamtdauer',
            //'cabin' => '',
        ],

        'ko' => [
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking reference'       => '예약 번호',
            //'Price'                   => [''],
            //'Total price'             => [''],
            //'Grand total'             => '',
            //'Taxes, fees and charges' => '',
            //'Base fare'               => '',
            'Outbound flight'         => '출국',
            'Inbound flight'          => '귀국',
            //'Departure'               => [''],
            'Terminal'                => '터미널',
            //'Operated by'             => '',
            //'Purchase seats' => '',
            'All passengers' => ['모든 승객'],
            'Passenger'      => '승객',
            'First name'     => '이름',
            'Family name'    => '성',
            'Seats'          => '좌석',
            'Duration'       => '총 소요 시간',
            //'cabin' => '',
        ],

        'sv' => [
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking reference'       => 'Bokningsnummer',
            'Price'                   => ['Prisuppdelning'],
            'Total price'             => ['Totalt'],
            'Grand total'             => 'Totalbelopp',
            'Taxes, fees and charges' => 'Skatt totalt',
            'Base fare'               => 'Baspris',
            'Outbound flight'         => 'Utresa',
            'Inbound flight'          => ['Hemresa'],
            //'Departure'               => [''],
            'Terminal'                => 'Terminal',
            'Operated by'             => 'Trafikeras av',
            //'Purchase seats' => '',
            'All passengers' => ['Alla passagerare'],
            'Passenger'      => 'Passagerare',
            'First name'     => 'Förnamn',
            'Family name'    => 'Efternamn',
            'Seats'          => ['Tillval för resan tillagda', 'Sittplatser'],
            'Duration'       => 'Total längd',
            //'cabin' => '',
            'Window seat'                     => ['Sittplats i businessklass'],
            'Your booking has been cancelled' => ['Din bokning har avbokats'],
        ],

        'es' => [
            // 'statusPhrases' => '',
            // 'statusVariants' => '',
            'Booking reference' => ['Código de reserva', 'CÓDIGO DE RESERVA'],
            'Price'             => 'Desglose del precio',
            'Total price'       => ['Total', 'Total price'],
            //			'Outbound flight' => '',
            //			'Inbound flight' => '',
            'Departure' => ['Salida', 'Vuelta'],
            'Terminal'  => 'Terminal',
            //			'Purchase seats' => '',
            'All passengers'          => ['Todos los pasajeros', 'TODOS LOS PASAJEROS'],
            'Passenger'               => ['Pasajero', 'PASAJERO'],
            'First name'              => 'Nombre',
            'Family name'             => 'Apellidos',
            'Operated by'             => 'Operado por',
            'Grand total'             => 'Total',
            'Taxes, fees and charges' => 'Total de impuestos',
            'Base fare'               => 'Tarifa base',
            //'cabin' => '',
        ],
        'en' => [
            'Price'                   => ['Price', 'Price breakdown', 'PRICE BREAKDOWN'],
            'Taxes, fees and charges' => ['Taxes, fees and charges', 'Finnair Cancellation Cover'],
            'statusPhrases'           => 'Your new flight has been',
            'statusVariants'          => 'confirmed',
            'cabin'                   => 'Ticket type:',
            'All passengers'          => ['All passengers', 'ALL PASSENGERS', 'Passengers:'],
            'Passenger'               => ['Passenger', 'PASSENGER'],
            'Departure'               => ['Departure', 'Return'],
            'Booking reference'       => ['Booking reference', 'BOOKING REFERENCE', 'Booking reference number:', 'BOOKING REFERENCE NUMBER:'],
            'Seats'                   => ['Seats', 'SEATS'],
            'Window seat'             => ['Window seat', 'Aisle seat', 'Preferred seat'],
            'points'                  => ['points'],
        ],
    ];

    // Standard Methods

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Finnair Beta') !== false
            || stripos($from, '@finnair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers['from'], 'Finnair Beta') === false && stripos($headers['from'], 'reply@finnair.com') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Finnair Plus") or contains(normalize-space(.),"booking with Finnair") or contains(normalize-space(), "Finnairilla") or contains(normalize-space(.),"BOOKING WITH FINNAIR") or contains(normalize-space(.),"Finnair")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,".com/Finnair") or contains(@href,".com/finnair") or contains(@href,"finnair.com")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->subject = $parser->getSubject();

        $this->assignLang();
        $this->parseEmail($email);
        $email->setType('BookingConfirmation' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function t($phrase)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function parseEmail(Email $email): void
    {
        $patterns = [
            'time' => '/^(\d{1,2}:\d{2}(?:\s*[ap]m)?)$/i',
            'code' => '/^([A-Z]{3})$/',
        ];

        $r = $email->add()->flight();

        $statusValues = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!]|$)/"));

        if (count(array_unique($statusValues)) === 1) {
            $status = array_shift($statusValues);
            $r->general()->status($status);
        }

        $confirmation = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[normalize-space(.)!=''][1])[1]",
            null, true, '/^([A-Z\d]{5,})$/');

        if (empty($confirmation)) {
            $confirmation = $this->re("/{$this->opt($this->t('Booking confirmation and details for'))}\s*([A-Z\d]{6})/", $this->subject);
        }

        if (!empty($confirmation)) {
            $r->general()
                ->confirmation($confirmation);
        }

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('Your booking has been cancelled'))}]")->length > 0) {
            $r->general()
                ->status('cancelled')
                ->cancelled()
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancelled booking reference:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{6})$/"));
        }

        $accounts = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Finnair Plus'))}]", null,
            '/Finnair Plus[ ]*\:[ ]*([A-Z]{1,3}[ ]*.+)/'));

        if (!empty($accounts)) {
            $r->program()->accounts($accounts, false);
        }

        $payment = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Price'))}]/following-sibling::tr/descendant::text()[{$this->starts($this->t('Total price'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("//tr[{$this->eq($this->t('Price'))}]/following::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Grand total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/')
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total price'))}]/ancestor::tr[1]/descendant::td[2]")
        ;

        if (preg_match("/^\s*(?<awards>\d+ {$this->opt($this->t('points'))})$/", $payment, $matches)) {
            $r->price()
                ->spentAwards($matches['awards']);
        } elseif (preg_match("/^\s*(?:(?<awards>\d+ {$this->opt($this->t('points'))})\s*\+\s*)?(?<amount>\d[,.\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/", $payment, $matches)) {
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $r->price()
                ->total(PriceHelper::parse($matches['amount'], $currencyCode))
                ->currency($matches['currency']);

            if (!empty($matches['awards'])) {
                $r->price()
                    ->spentAwards($matches['awards']);
            }
        }

        $tax = array_sum($this->http->FindNodes("//td[({$this->starts($this->t('Taxes, fees and charges'))}) and not(.//td)]/following-sibling::td[1]",
            null, '/([\d\.]+)/'));

        if (!empty($tax)) {
            $r->price()->tax($tax);
        }

        $base = array_sum($this->http->FindNodes("//td[({$this->eq($this->t('Base fare'))}) and not(.//td)]/following-sibling::td[1][not({$this->contains($this->t('points'))})]",
            null, '/([\d\.]+)/'));

        if (!empty($base)) {
            $r->price()->cost($base);
        }

        $xpath = '//tr[ ./td[1][./descendant::text()[string-length(normalize-space(.))=3]] and ./td[2][.//img] and ./td[3][./descendant::text()[string-length(normalize-space(.))=3]] ]';
        $segments = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH]:" . $xpath);

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $date = $this->http->FindSingleNode("./ancestor::tr/preceding-sibling::tr[{$this->eq($this->t('Outbound flight'))} or {$this->eq($this->t('Inbound flight'))}][1]/following-sibling::tr[normalize-space(.)!=''][1]/descendant::text()[normalize-space(.)!=''][1]",
                $segment);

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./ancestor::tr/preceding-sibling::tr[{$this->starts($this->t('Departure'))}][1]/descendant::h2", $segment, true, "/{$this->opt($this->t('Departure'))}[ ]*(.+)/");
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./ancestor::tr/preceding-sibling::tr[{$this->starts($this->t('Departure'))}][1]/descendant::text()[normalize-space()][2]", $segment, true, "/{$this->opt($this->t('Departure'))}[ ]*(.+)/");
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./ancestor::tr/preceding::text()[{$this->eq($this->t('Outbound flight'))} or {$this->eq($this->t('Inbound flight'))} or {$this->eq($this->t('Flight'))}][1]/following::text()[normalize-space(.)!=''][1]", $segment);
            }

            if (empty($date)) {
                $date = $this->http->FindSingleNode("./ancestor::tr/preceding::text()[{$this->starts($this->t('Outbound flight'))} or {$this->starts($this->t('Inbound flight'))} or {$this->starts($this->t('Flight'))}][1]", $segment, true, "/(?:{$this->opt($this->t('Outbound flight'))}|{$this->opt($this->t('Inbound flight'))})\s*([\d\.]+)/u");
            }

            $timeDep = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)!=''][1]", $segment,
                true, $patterns['time']);

            $timeArr = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)!=''][1]", $segment,
                true, $patterns['time']);

            if ($date && $timeDep && $timeArr) {
                if ($date = $this->normalizeDate($date)) {
                    $s->departure()->date(strtotime($date . ', ' . $timeDep));
                    $s->arrival()->date(strtotime($date . ', ' . $timeArr));
                }
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./td[1]/descendant::text()[string-length(normalize-space(.))=3][1]",
                    $segment, true, $patterns['code']));

            $s->arrival()
                ->code($this->http->FindSingleNode("./td[3]/descendant::text()[string-length(normalize-space(.))=3][1]",
                    $segment, true, $patterns['code']));

            $airportDep = implode("\n", $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[1]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^\(?\s*{$this->opt($this->t('Terminal'))}\s*(.+?)\s*\)?$/im", $airportDep, $m)
                || preg_match("/^\(?\s*(\b.+?)\s*{$this->opt($this->t('Terminal'))}\s*\)?$/im", $airportDep, $m)
            ) {
                $s->departure()->terminal($m[1]);
            }

            $airportArr = implode("\n", $this->http->FindNodes("following-sibling::tr[normalize-space()][1]/*[3]/descendant::text()[normalize-space()]", $segment));

            if (preg_match("/^\(?\s*{$this->opt($this->t('Terminal'))}\s*(.+?)\s*\)?$/im", $airportArr, $m)
                || preg_match("/^\(?\s*(\b.+?)\s*{$this->opt($this->t('Terminal'))}\s*\)?$/im", $airportArr, $m)
            ) {
                $s->arrival()->terminal($m[1]);
            }

            $xpathFragment1 = './following-sibling::tr[normalize-space(.)][2]/descendant::text()[normalize-space(.)]';

            $flight = $this->http->FindSingleNode($xpathFragment1 . '[1]', $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $matches)) {
                $s->airline()
                    ->name($matches[1])
                    ->number($matches[2]);
            }

            $duration = $this->http->FindSingleNode($xpathFragment1 . '[2]', $segment, true, '/((?:\d{1,3}\s*(?:h|[min]{1,3})\s*)+)$/i');

            if (empty($duration)) {
                $duration = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Duration'))}][1]", $segment, true, "/{$this->opt($this->t('Duration'))}\s*(.+)/");
            }

            if ($duration) {
                $s->extra()->duration($duration);
            }

            $cabin = $this->http->FindSingleNode("./following::text()[{$this->starts($this->t('cabin'))}][1]", $segment, true, "/{$this->opt($this->t('cabin'))}\s*(.+)/u");

            if (!empty($cabin)) {
                $s->extra()
                    ->cabin($cabin);
            }

            $operator = $this->http->FindSingleNode($xpathFragment1 . '[3]', $segment, true,
                "/{$this->opt($this->t('Operated by'))}[ ]*(.+)/");

            if (!empty($operator) && $operator != 'undefined') {
                $s->airline()->operator($operator);
            }

            $seats = $this->http->FindNodes("./following-sibling::tr[{$this->contains($this->t('Purchase seats'))}][1]/following-sibling::tr/td[2]/descendant::text()[normalize-space(.)!=''][1]",
                $segment, '/^\d+[A-Z]$/');
            $seatsValues = array_values(array_filter($seats));

            if (count($seatsValues) == 0) {
                $seatsNodes = $this->http->XPath->query("./following::text()[{$this->eq($this->t('Seats'))}]/ancestor::tr[1]/following-sibling::tr[{$this->starts($s->getDepCode())} and {$this->contains($s->getArrCode())}][1]/following-sibling::tr[string-length()>2][position() < 20]",
                    $segment);

                foreach ($seatsNodes as $sRoot) {
                    if (preg_match("/^\s*[A-Z]{3}\s*[A-Z]{3}\s*$/", $sRoot->nodeValue)) {
                        break;
                    } elseif (preg_match("/^\s*{$this->opt($this->t('Window seat'))}\s*(\d{1,3}[A-Z])\s*$/", $sRoot->nodeValue, $m)) {
                        $seatsValues[] = $m[1];
                    }
                }
            }

            if (!empty($seatsValues[0])) {
                $s->extra()->seats($seatsValues);
            }
        }

        $passengers = $tickets = [];
        $seatsNew = [];
        $passengerNodes = $this->http->XPath->query("//tr[{$this->eq($this->t('All passengers'))}]/following-sibling::tr/descendant::td[({$this->starts($this->t('Passenger'))}) and not(.//td)]");

        foreach ($passengerNodes as $passengerNode) {
            if ($firstName = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('First name'))}]",
                $passengerNode, true, '/:\s*(.+)/')
            ) {
                $passenger = $firstName;

                if ($familyName = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Family name'))}]",
                    $passengerNode, true, '/:\s*(.+)/')
                ) {
                    $passenger .= ' ' . $familyName;
                }

                $seatsText = implode("\n",
                    $this->http->FindNodes("//table[({$this->contains($this->t('Seats'))}) and not(.//table)]/descendant::tr[not(.//tr)][string-length(normalize-space(.))>=2]"));

                if (1 === count($r->getSegments())) {
                    if (preg_match_all('/\n[a-zA-Z ()]+\n(\d{1,2}[A-Z]{1,2})/', $seatsText, $seatMatches)) {
                        foreach ($seatMatches[1] as $seat) {
                            $seatsNew[$this->re('/(\b[A-Z]{6}\b)/', $seatsText)][] = $seat;
                        }
                    }
                } elseif (2 <= count($r->getSegments())) {
                    $segs = $this->splitter('/(\b[A-Z]{6}\b)/', $seatsText);

                    foreach ($segs as $seg) {
                        if (preg_match_all('/\n.+\n(\d{1,2}[A-Z]{1,2})/', $seg, $seatMatches)) {
                            foreach ($seatMatches[1] as $seat) {
                                $seatsNew[$this->re('/(\b[A-Z]{6}\b)/', $seg)][] = $seat;
                            }
                        }
                    }
                }
                $passengers[] = $passenger;
            }

            $ticket = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('E-ticket number'))}]", $passengerNode, true, "/{$this->opt($this->t('E-ticket number'))}[:\s]+(\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3})$/");

            if ($ticket) {
                $tickets[] = $ticket;
            }
        }

        if (!empty($seatsNew)) {
            foreach ($r->getSegments() as $tripSegment) {
                if (isset($seatsNew[$tripSegment->getDepCode() . $tripSegment->getArrCode()])) {
                    $tripSegment->extra()->seats(array_unique($seatsNew[$tripSegment->getDepCode() . $tripSegment->getArrCode()]));
                }
            }
        }

        if (!empty($passengers[0])) {
            $r->general()->travellers($passengers, true);
        }

        if (count($tickets) > 0) {
            $r->issued()->tickets(array_unique($tickets), false);
        }
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function normalizeDate($string): ?string
    {
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $string, $matches)) { // 24/09/2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif (preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/', $string, $matches)) { // 15.9.2017
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
        } elseif ($this->lang == 'ko' && preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $string, $matches)) {
            $day = $matches[3];
            $month = $matches[2];
            $year = $matches[1];
        } elseif ($this->lang == 'sv' && preg_match('/^(\d{4})\-(\d{1,2})\-(\d{1,2})$/', $string, $matches)) {
            $day = $matches[3];
            $month = $matches[2];
            $year = $matches[1];
        }

        if (isset($day,$month,$year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $day . '.' . $m[1] . '.' . $year;
            }

            if ($this->lang !== 'th') {
                $month = str_replace('.', '', $month);
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year;
        }

        return null;
    }

    private function assignLang(): bool
    {
        foreach ($this->langDetectors as $lang => $phrases) {
            foreach ($phrases as $phrase) {
                if ($this->http->XPath->query('//*[contains(normalize-space(),"' . $phrase . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s, '/')); }, $field)) . ')';
    }
}
