<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: move to disneyvacation

class ThankYouForReservation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-154058138-pt.eml, disneyresort/it-266792100.eml, disneyresort/it-27483030.eml, disneyresort/it-27896634.eml, disneyresort/it-35224030.eml, disneyresort/it-44178823.eml, disneyresort/it-44432891.eml, disneyresort/it-531508098.eml, disneyresort/it-630655073.eml"; // +1 bcd (lang es)

    public $reFrom = [
        'confirmations@experience.disneydestinations.com',
        'confirmations@reservation.disneydestinations.com',
    ];
    public $reBody = [
        'en'  => ['Reservation Details', 'Confirmation Number'],
        'en2' => ['New Flight Information', 'Attention'],
        'en3' => ['Invoice', 'Payment History'],
        'es'  => ['Detalles de la reserva', 'Fecha'],
        'pt'  => ['Detalhes da reserva', 'Número da confirmação'],
    ];
    public $reSubject = [
        // check detectEmailByHeaders if subject not contains 'Disney'
        'Thank you for making reservations at DISNEYLAND Resort',
        'Thank you for booking at WALT DISNEY WORLD Resort!',
        'Thank you for making reservations at WALT DISNEY WORLD Resort!',
        "We can't wait to welcome you home at WALT DISNEY WORLD Resort!",
        'Your flight itinerary to Walt Disney World has changed!',
        'Obrigado por fazer sua reserva no WALT DISNEY WORLD Resort!',
        'Final itinerary for your WALT DISNEY WORLD Resort vacation',
    ];
    public $lang = '';
    public $travellers = [];

    public static $dict = [
        'en' => [
            'Flight Itinerary'    => ['Flight Itinerary', 'Old Flight Information'],
            'Date'                => ['Date', 'Date:'],
            'Confirmation Number' => ['Confirmation Number', 'Confirmation Number:'],
            'checkIn'             => ['Check-in:', 'Check-in'],
            'checkOut'            => ['Check-out:', 'Checkout:', 'Checkout'],
            'Grand Total:'        => ['Grand Total:', 'Grand Total', 'Total Price for this Stay'],
            'Total Points'        => ['Total Points for this Stay:'],
            'Rate'                => ['Points per room/Per night:', 'Rate per room/Per night (plus tax):'],
        ],
        'es' => [
            'Flight Itinerary'    => ['Flight Itinerary', 'Old Flight Information'],
            'Date'                => ['Fecha', 'Fecha:'],
            'Confirmation Number' => ['Número de confirmación', 'Número de confirmación:'],
            // 'Cancellation and Refunds' => [''],
            'checkIn'             => ['Llegada:', 'Llegada'],
            'checkOut'            => ['Salida:', 'Salida'],
            'Grand Total:'        => ['Precio total de esta estadía:', 'Precio total de esta estadía'],
            // 'Total Points' => [''],
            // 'Rate' => [''],
            'Guests'              => ['Huésped(es)'],
        ],
        'pt' => [
            // 'Flight Itinerary' => [''],
            'Date'                     => ['Data', 'Date:'],
            'Confirmation Number'      => ['Número da confirmação', 'Número da confirmação:'],
            'Cancellation and Refunds' => ['Política de Cancelamento'],
            'checkIn'                  => ['Chegada:', 'Chegada'],
            'checkOut'                 => ['Saída:', 'Saída'],
            'Grand Total:'             => ['Preço total para esta estadia:', 'Preço total para esta estadia'],
            // 'Total Points' => [''],
            'Rate'                => ['Preço por quarto/noite (náo inclue impostos da Flórida):'],
            'Guests'              => ['Hóspede(s)'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->travellers = array_filter($this->http->FindNodes("//tr/*[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Guests'))}] ]/*[normalize-space()][2][self::table]/descendant::tr[not(.//tr) and normalize-space()]/*[normalize-space()]", null, "/^([[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]])(?:\s*\(|$)/u"));

        if (count($this->travellers) === 0) {
            $travellersRow = $this->http->FindSingleNode("//*[ count(tr[normalize-space()])=2 and tr[normalize-space()][1][{$this->eq($this->t('Guests'))}] ]/tr[normalize-space()][2]/*[normalize-space()]");
            $this->travellers = array_filter(preg_split('/\s*[,]+\s*/', $travellersRow));
        }

        if (count($this->travellers) === 0) {
            $travellersRow = $this->http->FindSingleNode("//text()[{$this->eq('Guests:')}][following::text()[normalize-space()][2][contains(., ':')]]/following::text()[normalize-space()][1]");
            $this->travellers = preg_split('/\s*[,]+\s*/', $travellersRow);
        }
        $this->travellers = preg_replace('/\s*\(\d+\)\s*$/', '', $this->travellers);

        $this->parseEmailHotel($email);
        $this->parseEmailCars($email);
        $this->parseEmailFlights($email);

        $spentAwards = $this->http->FindSingleNode("//*[{$this->eq($this->t('Total Points'))}]/ancestor::td[normalize-space()!=''][1]", null, true, '(\d+)');

        if (!empty($spentAwards)) {
            if (count($email->getItineraries()) === 1) {
                $r = $email->getItineraries()[0];
                $r->price()
                    ->spentAwards($spentAwards . ' points');
            } else {
                $email->price()
                        ->spentAwards($spentAwards . ' points');
            }
        }

        $totalPrice = $this->http->FindSingleNode("//*[{$this->eq($this->t('Grand Total:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // USD$ 961.36
            $currency = $this->normalizeCurrency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $total = PriceHelper::parse($matches['amount'], $currencyCode);

            if (count($email->getItineraries()) === 1) {
                $r = $email->getItineraries()[0];
                $r->price()->currency($currency)->total($total);
            } else {
                $email->price()->currency($currency)->total($total);
            }
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='Disney Destinations, LLC' or contains(@src,'disneydestinations')] | //a[contains(@href,'disneydestinations.com') or contains(@href,'disney.go.com/') or contains(@href,'disneyworld.disney.go.com') or contains(@href,'www.disneyworld.com')] | //*[contains(.,'www.disneyworld.com') or contains(.,'disneyworld.disney.go.com')]")->length === 0) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && isset($headers["subject"])) {
//            $flag = false;
//            foreach ($this->reFrom as $reFrom) {
//                if (stripos($headers['from'], $reFrom) !== false) {
//                    $flag = true;
//                }
//            }
//            if ($flag) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
//            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
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

    private function parseEmailFlights(Email $email): void
    {
        $flights = $this->http->XPath->query("//*[{$this->eq($this->t('Flight Itinerary'))}]/ancestor::table[1]/following-sibling::table[normalize-space()][1][{$this->contains($this->t('Depart'))}]");

        if ($flights->length === 0) {
            //$this->logger->debug("//*[{$this->contains($this->t('Flight Itinerary'), 'text()')}]/ancestor::table[1]");
            $flights = $this->http->XPath->query("//*[{$this->contains($this->t('Flight Itinerary'), 'text()')}]/ancestor::table[1]");
        }
        $this->logger->debug("Total {$flights->length} flight were found");

        foreach ($flights as $root) {
            //$this->logger->debug($root->nodeValue);
            if ($this->http->FindPreg('/Old\s*Flight\s*Information/i', false, $root->nodeValue)) {
                break;
            }

            $confirmationNumber = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Airline Confirmation Number'))}]", $root);

            if (preg_match("/({$this->opt($this->t('Airline Confirmation Number'))})\s*:*\s*([A-Z\d]{5,})$/", $confirmationNumber, $confM)) {
                $conf = $confM[2];
            }

            $finded = false;

            if (!empty($conf)) {
                foreach ($email->getItineraries() as $value) {
                    /** @var \AwardWallet\Schema\Parser\Common\Flight $value */
                    if ($value->getType() == 'flight') {
                        $s = $value->addSegment();
                        $finded = true;
                        $f = $value;

                        break;
                    }
                }
                $tickets = [];

                foreach ($email->getItineraries() as $value) {
                    /** @var \AwardWallet\Schema\Parser\Common\Flight $value */
                    if ($value->getType() == 'flight') {
                        $ticket = [];

                        foreach ($value->getTicketNumbers() as $ticketNumber) {
                            $ticket[] = $ticketNumber[0];
                        }
                        $tickets = array_merge($tickets, $ticket);
                    }
                }
            }

            if ($finded == false) {
                $f = $email->add()->flight();

                if (!empty($conf)) {
                    $f->general()->confirmation($conf, preg_replace('/\s*:+\s*$/', '', $confM[1]));
                }

                $f->general()
                    ->date(strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Confirmation Number'))}]/preceding::text()[{$this->eq($this->t('Date'))}])[last()]/following::text()[normalize-space()!=''][1]",
                    null, false, "/^\s*:?\s*(.+)/")));

                $s = $f->addSegment();
            }

            $xpathFragmentCell = "/ancestor::td[1]/following-sibling::td[normalize-space()][1]";
            $dateDep = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Depart'))}]" . $xpathFragmentCell . '/descendant::text()[normalize-space()][1]', $root);
            $s->departure()->date2($dateDep);

            $flight = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Depart'))}]" . $xpathFragmentCell . '/descendant::text()[normalize-space()][2]', $root);

            if (preg_match('/^(?<airline>.+)\s+(?<flightNumber>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['airline'])
                    ->number($m['flightNumber'])
                ;
            }

            $dateArr = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Arrive'))}]" . $xpathFragmentCell . '/descendant::text()[normalize-space()][1]', $root);
            $s->arrival()->date2($dateArr);

            $travellers = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Guests'))}]" . $xpathFragmentCell, $root);
            // amy wilson (29F), lana siegel (29E)
            $travellers = array_map(function ($v) {
                if (preg_match('/^(.+?)\s*(?:\((\w+)\))?$/', $v, $matches)) {
                    return $matches;
                }

                return null;
            }, preg_split('/\s*[,]+\s*/', $travellers));
            $trAdd = array_diff(array_column($travellers, 1), array_column($f->getTravellers(), 0));

            if (!empty($trAdd)) {
                $f->general()
                    ->travellers($trAdd);
            }

            $s->extra()->seats(array_column($travellers, 2));

            $route = $this->http->FindSingleNode("descendant::text()[normalize-space()][2]/ancestor::tr[1]", $root);
            $airports = preg_split('/\s*[>]+\s*/', $route);

            if (count($airports) !== 2) {
                $this->logger->debug('Flight route delimiter not found!');

                continue;
            }

            $patterns['nameCode'] = '(?<name>.{3,})\s*\((?<code>[A-Z]{3})\)';

            if (preg_match("/^{$patterns['nameCode']}/", $airports[0], $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->code($m['code'])
                ;
            }

            if (preg_match("/^{$patterns['nameCode']}/", $airports[1], $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->code($m['code'])
                ;
            }

            if (empty($f->getConfirmationNumbers()) && !empty($s->getArrName())) {
                $f->general()->noConfirmation();
            }
        }
    }

    private function parseEmailCars(Email $email): void
    {
        $nodes = $this->http->XPath->query("//*[{$this->eq($this->t('Car Rental'))}]/ancestor::tr[1]/following-sibling::tr[normalize-space()!=''][1][{$this->contains($this->t('Pickup'))}]");

        foreach ($nodes as $root) {
            $r = $email->add()->rental();
            $r->general()
                ->date(strtotime($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Confirmation Number'))}]/preceding::text()[{$this->eq($this->t('Date'))}])[last()]/following::text()[normalize-space()!=''][1]",
                    null, false, "/^\s*:?\s*(.+)/")))
                ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()!=''][1]",
                    null, false, "/:?\s*(.+)/"))
                ->travellers($this->travellers, true);

            $date = strtotime($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]", $root));
            $node = $this->http->FindSingleNode("descendant::text()[normalize-space()][position()<5][{$this->starts($this->t('Pickup'))}]/ancestor::tr[1]", $root);

            if (preg_match("/{$this->opt($this->t('Pickup'))}[ :]+(.+) +(\d+:\d+(?:\s*[ap]m)?)/i", $node, $m)) {
                $r->pickup()
                    ->date(strtotime($m[2], $date))
                    ->location($m[1]);
            }
            $node = $this->http->FindSingleNode("descendant::text()[normalize-space()][position()<7][{$this->starts($this->t('Drop Off'))}]/ancestor::tr[1]", $root);

            if (preg_match("/{$this->opt($this->t('Drop Off'))}[ :]+(.+) +(\d+:\d+(?:\s*[ap]m)?)/i", $node, $m)) {
                $dropOffDate = strtotime($m[2], $date);
                $rentalDays = $this->http->FindSingleNode("descendant::text()[normalize-space()][position()<9][{$this->contains($this->t('DAYS'))}]/ancestor::tr[1]", $root, false, "/^(\d{1,3})\s+{$this->opt($this->t('DAYS'))}/i");

                if ($rentalDays !== null) {
                    $r->dropoff()
                        ->date(strtotime("+ {$rentalDays} days", $dropOffDate))
                        ->location($m[1]);
                }
            }
            $rentalCompany = $this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][8]", $root, false,
                "/^(.+?) CAR RENTAL/");

            if (!empty($rentalCompany)) {
                $r->program()->keyword($rentalCompany);
            }
        }
    }

    private function parseEmailHotel(Email $email): void
    {
        $rule = "[not(preceding::text()[normalize-space()][1][contains(., '®')])]"; // exclude "Walt Disney World® Resort"
        // $root = "//text()[normalize-space()='Hotel' or normalize-space()='Resort']{$rule}/following::img[position()<=2][contains(@alt,'Icon hotel') or contains(@alt,'Resort Info-Resort.') or contains(@src,'icon-resort.png') or contains(@src,'IconResort')]/following::table[normalize-space()][1]/descendant::tr[1]/descendant::text()[normalize-space()]";
        $root = "//text()[normalize-space()='Hotel' or normalize-space()='Resort']{$rule}/following::img[position()<=2][contains(@alt,'Icon hotel') or contains(@alt,'Resort Info-Resort.') or contains(@src,'icon-resort.png') or contains(@src,'IconResort')]/following::text()[normalize-space()][1]/ancestor::table[not(.//img)][1]/descendant::tr[1]/descendant::text()[normalize-space()]";
        $info = $this->http->FindNodes($root);

        if (count($info) === 0) {
            $root = "//text()[normalize-space()='Hotel' or normalize-space()='Resort']{$rule}/following::img[position()<=2][contains(@alt,'Icon hotel') or contains(@alt,'Resort Info-Resort.') or contains(@src,'icon-resort.png') or contains(@src,'IconResort')]/following::text()[normalize-space()][1]/ancestor::*[not(.//img)][last()]/descendant::text()[normalize-space()]";
            $info = $this->http->FindNodes($root);
        }

        if (count($info) === 0) {
            $root = "(//text()[normalize-space() = 'Check-in']/preceding::img[@width = 30])[last()]/following::table[normalize-space()!=''][1]/descendant::tr[1]/descendant::text()[normalize-space()!='']";
            $info = $this->http->FindNodes($root);
        }

        if (count($info) === 1) {
            $rootExt = "//text()[normalize-space()='Hotel' or normalize-space()='Resort']{$rule}/following::img[position()<=2][contains(@alt,'Icon hotel') or contains(@src,'icon-resort.png')]/following::table[normalize-space()!=''][1]/descendant::tr[1]/following-sibling::tr/descendant::text()[normalize-space()!='']";
            $infoExt = $this->http->FindNodes($rootExt);

            if (count($infoExt) === 0) {
                $rootExt = "(//text()[normalize-space() = 'Check-in']/preceding::img[@width = 30])[last()]/following::table[normalize-space()!=''][1]/descendant::tr[1]/following-sibling::tr/descendant::text()[normalize-space()!='']";
                $infoExt = $this->http->FindNodes($rootExt);
            }

            if (count($infoExt) === 3) {
                $info = array_merge($info, $infoExt);
            }
        }

        if (count($info) === 0) {
            $root = "//text()[normalize-space()='Hotel' or normalize-space()='Resort']/following::img[position()<=2][contains(@alt,'DISNEY')]/following::table[normalize-space()][1]/descendant::tr[1]/descendant::text()[normalize-space()]";
            $info = $this->http->FindNodes($root);
        }
        $this->logger->debug('$root = ' . print_r($root, true));

        if (count($info) === 3 || count($info) === 4 || count($info) === 5
            || (count($info) === 2
                && (!empty($this->http->FindSingleNode('(' . $root . ")[last()]/following::text()[normalize-space()][1]", null, true, "/^\s*\d+ (night|noche).{0,5}/i"))
                || !empty($this->http->FindSingleNode('(' . $root . ")[last()]/following::text()[normalize-space()][2]", null, true, "/^\s*\d+ (night|noche).{0,5}/i"))
                )
            )
        ) {
            $r = $email->add()->hotel();
            $room = $r->addRoom();

            $rate = implode('; ', $this->http->FindNodes("//tr[{$this->eq($this->t('Rate'))}]/following-sibling::tr[normalize-space()!='']"));

            if (!empty($rate)) {
                $room->setRate($rate);
            }

            $r->hotel()
                ->name($info[0])
                ->address($info[1]);

            if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('Room'))}\w{0,3}\s*$/", $info[2] ?? '', $m)) {
                $r->booked()
                    ->rooms($m[1]);
                $room->setType($info[3]);

                if ($m[1] > 1) {
                    $types = $this->http->FindNodes("//text()[normalize-space()='Hotel' or normalize-space()='Resort']/following::text()[{$this->eq($this->t('checkIn'))}][position() > 1 and position() <= {$m[1]}]/preceding::text()[normalize-space()!=''][1]/ancestor::tr[1][count(.//text()[normalize-space()]) = 2]//text()[normalize-space()][1]");

                    foreach ($types as $t) {
                        $r->addRoom()->setType($t);
                    }
                }
            } elseif (isset($info[2])) {
                $room->setType($info[2]);
            }

            if (preg_match("/^\s*(\d+)\s*{$this->opt($this->t('Guest'))}/", $info[4] ?? '', $m)) {
                $r->booked()
                    ->guests($m[1]);
            }
        } else {
            return;
        }

        $cancellation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Cancellation and Refunds'))}]/following::text()[string-length(normalize-space())>3][1]/ancestor::tr[1]", null, false, "/^[ \W]*(.+)/");
        $r->general()
            ->date2($this->normalizeDate($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Confirmation Number'))}]/preceding::text()[{$this->eq($this->t('Date'))}])[last()]/following::text()[normalize-space()!=''][1]",
                null, false, "/:?\s*(.+)/")))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()!=''][1]",
                null, false, "/:?\s*(.+)/"))
            ->cancellation($cancellation, false, true);

        $r->general()->travellers($this->travellers, true);

        $r->booked()
            ->checkIn2($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Hotel' or normalize-space()='Resort']/following::text()[normalize-space()][{$this->eq($this->t('checkIn'))}][1]/following::text()[normalize-space()!=''][1]")))
            ->checkOut2($this->normalizeDate($this->http->FindSingleNode("//text()[normalize-space()='Hotel' or normalize-space()='Resort']/following::text()[normalize-space()][{$this->eq($this->t('checkOut'))}][1]/following::text()[normalize-space()!=''][1]")))
        ;

        if (!empty($cancellation)) {
            if (preg_match("/^For cancellations made \d+ days or more prior to Guest arrival, amounts paid(?:, | \()minus cancellation fees assessed by third party hotels or other suppliers/i", $cancellation)
            ) {
                $r->booked()->nonRefundable();
            } elseif (preg_match("/Para (?i)que ocorra o reembolso do depósito ou pagamento, uma notificação de cancelamento da reserva do quarto deve ser recebida pela Disney em até\s+(?<prior>\d{1,3})\s+dias antes da data de chegada\./u", $cancellation, $m)
            ) {
                $r->booked()->deadlineRelative($m['prior'] . ' days');
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

    private function assignLang(): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ((stripos($this->http->Response['body'], $reBody[0]) !== false)
                    && (stripos($this->http->Response['body'], $reBody[1]) !== false)
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate(?string $str): string
    {
        $in = [
            // segunda-feira, 9 de maio de 2022
            "/^\s*[-[:alpha:]]+[,\s]+(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\s+(?:de\s+)?(\d{4})$/u",
            "/^\s*[-[:alpha:]]+[,\s]+([[:alpha:]]+)\s+(\d{1,2})[\s,]\s*(\d{4})$/u",
        ];
        $out = [
            "$1 $2 $3",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("/\d+\s+([[:alpha:]]+)\s+\d{4}/u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    /**
     * @param string $string Unformatted string with currency
     */
    private function normalizeCurrency(string $string): string
    {
        $string = trim($string);
        $currencies = [
            // do not add unused currency!
            'USD' => ['US$', 'USD$'],
            'EUR' => ['€'],
            'GBP' => ['£'],
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

    private function contains($field, $node = '')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }
}
