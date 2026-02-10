<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class ConfirmOrChangeFlight extends \TAccountChecker
{
    public $mailFiles = "aa/it-134793905.eml, aa/it-138857303.eml, aa/it-175523664.eml, aa/it-35066183.eml, aa/it-65499102.eml, aa/it-65934778.eml, aa/it-90942398.eml";
    public $reFrom = ["no-reply@notify.email.aa.com", "no-reply@info.email.aa.com"];
    public $reBody = [
        'en'    => ['We rebooked your trip', 'Record locator'],
        'en2'   => ["ve been rebooked", 'Record locator'],
        'en3'   => ['Choose a new flight', 'Record locator'],
        'en4'   => ['Rebooking is available', 'Record locator'],
        'en5'   => ['Your trip confirmation and receipt', 'American'],
        'en6'   => ['Your flight changed', 'American Airlines'],
        'en7'   => ['One of your flights was canceled', 'American Airlines'],
        'en8'   => ['Seat:', 'Class:'],
        'en9'   => ['You booked a', 'trip you booked'],
        'es'    => ['Código de la reservación', 'American Airlines'],
        'es2'   => ['Código de reservación', 'American Airlines'],
        'pt'    => ['Código de reserva:', 'American Airlines'],
        'pt2'   => ['Código da reserva:', 'American Airlines'],
        'fr'    => ['Référence de dossier:', 'American Airlines'],
        'de'    => ['Ausstellungsdatum:', 'American Airlines'],
        'de2'   => ['Bearbeitungsnummer:', 'American Airlines'],
    ];
    public $reSubject = [
        'Confirm or change flight',
        'Confirm new flight',
        'Choose a new flight',
        'Your trip confirmation',
        "There's been a change in your trip.",
        'Confirm upcoming trip on American Airlines',
        // pt
        'Houve uma alteração na sua viagem',
        // fr
        'Votre voyage a été modifié',
        //de
        'Ihre Reisebestätigung',
        'Ihre Reise hat sich geändert',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Record Locator:'     => ['Record Locator:', 'Record locator:', 'RECORD LOCATOR:', 'record locator:', 'Confirmation code:'],
            'Join the AAdvantage' => ['Join the AAdvantage', 'AAdvantage', 'Join AAdvantage'],
        ],
        'es' => [
            'Record Locator:'     => ['Código de la reservación:', 'Código de reservación:'],
            'Seat'                => 'Asiento',
            'Class'               => 'Clase',
            // 'Operated by'         => '',
            'Ticket #'            => ['del boleto', 'N° del boleto'],
            'Total cost'          => 'Costo total',
            'Earn miles'          => 'Obtenga millas',
            'Join the AAdvantage' => ['Únase a AAdvantage', 'AAdvantage #'],
            // 'Preferred seat' => '',
            'Meals'               => 'Comidas',
            'ORIGINAL FLIGHT'     => 'VUELO ORIGINAL',
            'Flight arrives'      => 'El vuelo llega el',
            'New ticket'          => 'Nuevo boleto',
        ],
        'pt' => [
            'Record Locator:'     => ['Código de reserva:', 'Código da reserva:', 'código de confirmação:'],
            'Seat'                => 'Assento',
            'Class'               => 'Classe',
            'Operated by'         => 'Voo operado pela',
            'Ticket #'            => ['Nº do bilhete', 'No do bilhete:'],
            'Total cost'          => 'Custo total',
            'Earn miles'          => 'Ganhe milhas',
            'Join the AAdvantage' => ['Participar do AAdvantage', 'AAdvantage #'],
            // 'Preferred seat' => '',
            'Meals'               => 'Refeições',
            'ORIGINAL FLIGHT'     => 'VOO ORIGINAL',
            'Flight arrives'      => 'Chegada do voo',
            'New ticket'          => 'Novo bilhete',
            //            'CANCELED'     => '',
        ],
        'fr' => [
            'Record Locator:'     => ['Référence de dossier:'],
            'Seat'                => 'Siège',
            'Class'               => 'Classe',
            // 'Operated by'         => '',
            'Ticket #'            => ['Numéro de billet'],
            //            'Total cost'          => 'Custo total',
            'Earn miles'          => 'Gagnez des miles',
            'Join the AAdvantage' => ['Rejoindre AAdvantage', 'AAdvantage #'],
            // 'Preferred seat' => '',
            'Meals'               => 'Repas',
            'ORIGINAL FLIGHT'     => 'VOL D\'ORIGINE',
            'Flight arrives'      => 'Arrivée du vol',
            //            'New ticket'     => 'Novo bilhete',
            //            'CANCELED'     => '',
        ],
        'de' => [
            'Record Locator:'     => ['Buchungscode:'],
            'Seat'                => ['Sitz', 'Sitzplatz:'],
            'Class'               => 'Klasse',
            'Meals'               => 'Mahlzeiten',
            'Operated by'         => 'durchgeführt von',
            'Ticket #'            => ['Ticketnummer', 'Ticket #'],
            'Total cost'          => 'Gesamtkosten',
            'Earn miles'          => 'Sammeln Sie mit dieser Buchung Meilen',
            'Join the AAdvantage' => ['Bei AAdvantage', 'AAdvantage #'],
            // 'Preferred seat' => '',
            'ORIGINAL FLIGHT'     => 'URSPRÜNGLICHER FLUG',
            'Flight arrives'      => 'Flugankunft',
            'New ticket'          => 'Neues Ticket',
            //            'CANCELED'     => '',
        ],
    ];

    /*private function parseEmail()
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'Record locator')]/following::text()[normalize-space(.)!=''][1]",
            null, false, "#^([A-Z\d]{5,})$#");
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1][{$ruleTime}]/ancestor::table[1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug($xpath);
        $lastDate = null;
        foreach ($nodes as $root) {
            if ($this->http->XPath->query("./preceding::tr[contains(.,'MISSED CONNECTION')]",
                    $root)->length > 0
            ) {
                continue;
            }
            $seg = [];
            $date = strtotime($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][1]", $root));
            if (!empty($date)) {
                $lastDate = $date;
            } else {
                $date = $lastDate;
            }
            if ($this->http->XPath->query("./preceding::tr[contains(.,'CANCELED')]",
                    $root)->length > 0
            ) {
                continue;
            }
            $seg['DepCode'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][1]",
                $root, false, "#[A-Z]{3}#");
            $seg['ArrCode'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][1]/td[normalize-space(.)!=''][2]",
                $root, false, "#[A-Z]{3}#");
            $this->logger->warning($this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][1]",
                $root));
            $seg['DepDate'] = strtotime(str_replace(';', '', $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][1]",
                $root)), $date);
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][2]/td[normalize-space(.)!=''][2]",
                $root), $date);
            $seg['DepName'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][3]/td[normalize-space(.)!=''][1]",
                $root);
            $seg['ArrName'] = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][3]/td[normalize-space(.)!=''][2]",
                $root);
            $node = implode("\n",
                $this->http->FindNodes("./following::table[1]//text()[normalize-space(.)!='']", $root));
            if (preg_match("#\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\b#", $node, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];
            }
            if (preg_match("#Aircraft\s*:\s*(.+)\s+Class\s*:\s*(.+)#", $node, $m)) {
                $seg['Aircraft'] = $m[1];
                $seg['Cabin'] = $m[2];
            }
            $it['TripSegments'][] = $seg;
        }
        return [$it];
    }*/
    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmail($email, $parser->getSubject());
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'aa.com')] | //a[contains(@href,'aa.com')] | //*[contains(normalize-space(),'Save time with the American app') or contains(normalize-space(),'You can check in via the American app')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && (stripos($headers['from'], $this->reFrom[0]) || stripos($headers['from'], $this->reFrom[1])) !== false && isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return in_array($from, $this->reFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email, $subject): void
    {
        $xpathNoEmpty = 'string-length(normalize-space())>1';

        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        // travellers
        $xpathNoTraveller = "(not({$this->contains($this->t('Credit Card'))}) and not({$this->contains($this->t('Earn miles'))}) and not(contains(.,'AAdvantage')))";

        $travellers = [];

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('New ticket'))}]/ancestor::tr[1]/preceding::tr[string-length(normalize-space())>3][{$xpathNoTraveller}][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellerNames) > 0) {
            $travellers = array_merge($travellers, $travellerNames);
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Join the AAdvantage'))}]/ancestor::tr[1]/preceding::tr[string-length(normalize-space())>3][{$xpathNoTraveller}][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellerNames) > 0) {
            $travellers = array_merge($travellers, $travellerNames);
        }

        $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Preferred seat'))}]/ancestor::tr[1]/preceding::tr[string-length(normalize-space())>3][{$xpathNoTraveller}][1]", null, "/^{$patterns['travellerName']}$/u"));

        if (count($travellerNames) > 0) {
            $travellers = array_merge($travellers, $travellerNames);
        }

        if (count($travellers) > 0) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        // Issued
        $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Ticket #'))}]", null, "/^\s*{$this->opt($this->t('Ticket #'))}[:\s]*({$patterns['eTicket']})$/"));

        if (empty($tickets)) {
            $tickets = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Ticket #'))}]/following::text()[string-length(normalize-space())>1][1]", null, "/^{$patterns['eTicket']}$/"));
        }

        if (empty($tickets)) {
            $tickets = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Ticket #'))}]/following::text()[string-length(normalize-space())>1][1]", null, "/^{$patterns['eTicket']}$/"));
        }

        if (count($tickets) > 0) {
            $f->setTicketNumbers(array_unique($tickets), false);
        }

        // Program
        $accounts = array_filter(
            $this->http->FindNodes("//*[not(.//tr) and not(self::span) and {$this->starts(['AAdvantage #', 'AAdvantage® #'])}]", null, "/^AAdvantage.*#[:\s]*(?-i)([A-Z\d]+(?:\s*[A-Z\d]+)*)$/i"),
            function ($item) {
                return !empty($item) && !preg_match('/^[Xx]+$/', $item);
            }
        );

        if (count($accounts) > 0) {
            $f->program()->accounts(array_unique($accounts), false);
        }

        // Price
        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Total cost'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $matches)) {
            // $ 10.791,70    |    MXN 2,852.00
            $matches['currency'] = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()
                ->currency($matches['currency'])
                ->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

        $codesFromSubject = preg_match('/\(\s*([A-Z]{3})\s*-\s*([A-Z]{3})\s*\)/', $subject, $m) ? [$m[1], $m[2]] : null;

        // Segments
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";
        $xpath = "//text()[" . $this->eq($this->t('ORIGINAL FLIGHT')) . "]/preceding::text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1][{$ruleTime}]/ancestor::table[1]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $xpath = "//text()[{$ruleTime}]/ancestor::td[1]/following-sibling::td[string-length(normalize-space(.))>2][1][{$ruleTime}]/ancestor::table[1]";
            $segments = $this->http->XPath->query($xpath);
        }

        $this->logger->debug('XPATH: ' . $xpath);

        $lastDate = null;

        foreach ($segments as $root) {
            $s = $f->addSegment();

            if ($this->http->XPath->query("./preceding::tr[" . $this->eq($this->t("CANCELED")) . "]", $root)->length > 0
                || $this->http->XPath->query("./preceding::tr[contains(.,'MISSED CONNECTION')]", $root)->length > 0
            ) {
                $s->extra()
                    ->status('Cancelled')
                    ->cancelled();
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space(.)!=''][not(contains(normalize-space(), 'DELAYED'))][1]", $root, true, '/.*\b20\d{2}\b.*/'));

            if (!empty($date)) {
                $lastDate = $date;
            } else {
                $date = $lastDate;
            }

            $name = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][3][count(td[normalize-space()]) = 2]/td[normalize-space(.)!=''][1]", $root);

            if (!empty($name)) {
                $s->departure()
                    ->name($name);
            }

            $codeDep = $this->http->FindSingleNode("descendant::tr[normalize-space()][1]/*[{$xpathNoEmpty}][1]", $root, false, '/^[A-Z]{3}$/');

            if ($codeDep) {
                $s->departure()->code($codeDep);
            }

            $s->departure()->date(strtotime(str_replace(';', '', $this->http->FindSingleNode("descendant::tr[normalize-space()][2]/td[normalize-space()][1]", $root)), $date));

            //it-134793905.eml
            $dateArrives = $this->http->FindSingleNode("./descendant::text()[" . $this->starts($this->t('Flight arrives')) . "][1]/ancestor::tr[1]", $root, true, "/" . $this->opt($this->t('Flight arrives')) . "\s*(.+)/");
            //$this->logger->debug('NEW-DATE-'.$dateArrives);
            if (!empty($dateArrives)) {
                $date = $this->normalizeDate($dateArrives);
                $lastDate = $date;
            }

            $name = $this->http->FindSingleNode("./descendant::tr[normalize-space(.)!=''][3][count(td[normalize-space()]) = 2]/td[normalize-space(.)!=''][2]", $root);

            if (!empty($name)) {
                $s->arrival()
                    ->name($name);
            }

            $codeArr = $this->http->FindSingleNode("descendant::tr[normalize-space()][1]/*[{$xpathNoEmpty}][2]", $root, false, '/^[A-Z]{3}$/');

            if ($codeArr) {
                $s->arrival()->code($codeArr);
            }

            $s->arrival()->date(strtotime($this->http->FindSingleNode("descendant::tr[normalize-space()][2]/td[normalize-space()][2]", $root), $date));

            $node = implode("\n",
                $this->http->FindNodes("./following::table[1]//text()[normalize-space(.)!='']", $root));

            if (empty($node)) {
                $node = $this->http->FindSingleNode("./ancestor::table[1]", $root);
            }

            if (preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)$/", $node, $m)
            || preg_match("/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\b/", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match("#Aircraft\s*:\s*(?<aircraft>.+)\s+{$this->opt($this->t('Class'))}\s*:\s*(?<class>.+)#", $node, $m)
                || preg_match("#{$this->opt($this->t('Class'))}\s*:\s*(?<class>.+)\s*\((?<bookigCode>[A-Z])\)#", $node, $m)) {
                if (isset($m['aircraft'])) {
                    $s->extra()
                        ->aircraft($m['aircraft']);
                }

                if (isset($m['class'])) {
                    $s->extra()
                        ->cabin($m['class']);
                }

                if (isset($m['bookigCode'])) {
                    $s->extra()
                        ->bookingCode($m['bookigCode']);
                }
            }

            if (preg_match("#^{$this->opt($this->t('Seat'))}\s*\:(?<seat>[^:]*)\s*{$this->opt($this->t('Class'))}\s*\:\s*(?<class>[^:]*)\s+{$this->opt($this->t('Meals'))}\s*\:\s*(?<meal>\D*)?$#u", $node, $m)) {
                if (preg_match("/\d+/", $m['seat'])) {
                    $seats = array_map('trim', explode(',', $m['seat']));

                    foreach ($seats as $seat) {
                        if (preg_match_all("#^\d{1,2}[A-Z]$#", $seat)) {
                            $s->extra()
                                ->seat($seat);
                        }
                    }
                }

                if (preg_match("#^\s*(?<cabin>\S[[:alpha:] ]*)\s*\(\s*(?<class>[A-Z]{1,2})\s*\)\s*$#", $m['class'], $mat)) {
                    $s->extra()
                        ->cabin($mat[1])
                        ->bookingCode($mat[2])
                    ;
                } elseif (preg_match("#^\s*\(\s*(?<class>[A-Z]{1,2})\s*\)\s*$#", $m['class'], $mat)) {
                    $s->extra()
                        ->bookingCode($mat[1])
                    ;
                }

                $m['meal'] = $m['meal'] ? trim($m['meal']) : '';

                if (!empty($m['meal'])) {
                    $s->extra()
                        ->meal(preg_replace(["/\s*,\s*/", "/\s{2,}/"], [', ', ' '], trim($m['meal'])));
                }
            } else {
                if (preg_match_all("#(?:^|\n)\s*{$this->opt($this->t('Seat'))}\s*(\d{1,2}[A-Z])\b#", $node, $m)) {
                    $s->extra()
                        ->seats($m[1]);
                }

                if (preg_match("#(?:^|\s+){$this->opt($this->t('Class'))}\s*\:\s*(?<cabin>\S.*)?\s*\(\s*(?<class>[A-Z]{1,2})\s*\)#", $node, $m)) {
                    $s->extra()
                        ->cabin($m[1])
                        ->bookingCode($m[2])
                    ;
                }

                if (preg_match("#(?:^|\s+){$this->opt($this->t('Meals'))}\s*\:\s*(?<meal>\D+?)\s*$#", $node, $m)
                    && trim($m['meal']) !== '--') {
                    $s->extra()
                        ->meal(preg_replace(["/\s*,\s*/", "/\s{2,}/"], [', ', ' '], trim($m['meal'])));
                }
            }

            if (empty($s->getAirlineName())) {
                $node = $this->http->FindSingleNode("./descendant::tr[normalize-space()][4]", $root);

                if (preg_match('/\b([A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(\d+)\b(?:\s*(.+))?\s*/', $node, $m)) {
                    $s->airline()
                        ->name($m[1])
                        ->number($m[2]);

                    $s->extra()
                        ->aircraft($m[3] ?? null, true, true);
                }
            }

            // it-175523664.eml
            $operator = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Operated by'))}]/following::text()[normalize-space()][1][not(contains(.,':'))]", $root)
                ?? $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Operated by'))}]", $root, true, "/^{$this->opt($this->t('Operated by'))}\s+([^:]+)$/");
            $segConfirmation = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$this->contains($this->t('Record Locator:'))}]", $root);

            if (!empty($operator) && preg_match("/^" . preg_quote($operator, '/') . "\s+{$this->opt($this->t('Record Locator:'))}\s*([A-Z\d]{5,})$/", $segConfirmation, $m)) {
                $s->airline()->carrierName($operator)->carrierConfirmation($m[1]);
            } elseif (preg_match("/^{$this->opt($this->t('Record Locator:'))}\s*([A-Z\d]{5,})$/", $segConfirmation, $m)) {
                $s->airline()->confirmation($m[1]);
            }

            if (empty($s->getCarrierAirlineName()) && !empty($operator)) {
                $s->airline()->operator($operator);
            }

            if ($segments->length === 1 && (empty($s->getDepCode()) || empty($s->getArrCode())) && $codesFromSubject !== null) {
                $s->departure()->code($codesFromSubject[0]);
                $s->arrival()->code($codesFromSubject[1]);
            }
        }

        $confirmationNumbers = array_filter($this->http->FindNodes("({$xpath})[1]/preceding::tr[not(.//tr) and {$this->starts($this->t('Record Locator:'))}]", null, "/^{$this->opt($this->t('Record Locator:'))}[:\s]*[A-Z\d]{5,}$/i"));
        $confirmation = count(array_unique($confirmationNumbers)) === 1 ? array_shift($confirmationNumbers) : null;

        if (preg_match("/^({$this->opt($this->t('Record Locator:'))})[:\s]*([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], rtrim($m[1], ': '));
        } elseif (preg_match("/^({$this->opt($this->t('Record Locator:'))})[:\s]*([Xx]+)$/", $confirmation, $m)
            || $this->http->XPath->query("//*[{$this->contains($this->t('Record Locator:'))}]")->length === 0
        ) {
            $f->general()->noConfirmation();
        }
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = ' . print_r($str, true));

        $in = [
            "#^[\w\-]+\,\s*(\d+)\.?\s*(?:de\s+)?(\w+)(?:\s+de)?\s*(\d{4})$#u", //Miércoles, 19 de mayo de 2021
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);
//        $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $words) {
            if ($this->http->XPath->query("//*[{$this->contains($words[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($words[1])}]")->length > 0) {
                $this->lang = substr($lang, 0, 2);

                return true;
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

        return '(' . implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
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

    private function t($word)
    {
        if (!isset(self::$dict[$this->lang]) || !isset(self::$dict[$this->lang][$word])) {
            return $word;
        }

        return self::$dict[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function currency($s)
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return trim($s);
        }
        $sym = [
            'R$' => 'BRL',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
