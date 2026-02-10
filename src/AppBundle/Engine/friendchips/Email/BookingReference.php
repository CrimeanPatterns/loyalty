<?php

namespace AwardWallet\Engine\friendchips\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class BookingReference extends \TAccountChecker
{
    public $mailFiles = "friendchips/it-282188095.eml, friendchips/it-42812999.eml, friendchips/it-43427820.eml";

    public $reFrom = ["@customerservices.tui.co.uk"];
    public $reBody = [
        'en' => ['Thanks for booking with TUI', 'Thanks for booking your flights with TUI Airways', 'Thanks for booking with holidayhypermarket.co.uk.'],
    ];
    public $reSubject = [
        'TUI Booking Reference',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'ISSUE DATE:'         => 'ISSUE DATE:',
            'Guest'               => ['Guest', 'Passenger Name'],
            'paxEnd'              => ['Lead Passenger', 'For information on the hand and hold '],
            'notReseravtion'      => ['FINANCIAL PROTECTION', 'CONTACT DETAILS', 'TRAVEL BOOKING'],
        ],
    ];
    private $keywordProv = ['TUI', 'TUIfly'];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);
        $sum = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Total Price'))}])[1]/following::text()[normalize-space()!=''][1]");

        if (!empty($sum)) {
            $sum = $this->getTotalCurrency($sum);
            $email->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
        }

        $feeXpath = $this->http->XPath->query("//text()[{$this->eq($this->t("Price Breakdown"))}]/ancestor::*[count(.//text()[normalize-space()])=2]/following-sibling::*[following-sibling::*[{$this->starts($this->t('Total Price'))}]]/descendant-or-self::*[count(*[normalize-space()]) > 1]");
        $discountSum = 0.0;
        $costSum = 0.0;
        $costs = true;

        foreach ($feeXpath as $fpath) {
            $name = $this->http->FindSingleNode("*[normalize-space()][1]", $fpath);
            $value = $this->http->FindSingleNode("*[normalize-space()][2]", $fpath);

            if ($costs && preg_match('/.+ Price\s*$/', $name)) {
                $sum = $this->getTotalCurrency($value);
                $costSum += $sum['Total'];

                continue;
            }
            $costs = false;

            if (preg_match("/^\s*\-.*\d+/", $value)) {
                $sum = $this->getTotalCurrency($value);
                $discountSum += $sum['Total'];
            } else {
                $email->price()
                    ->fee($name, $this->getTotalCurrency($value)['Total']);
            }
        }

        if (!empty($discountSum)) {
            $email->price()->discount($discountSum);
        }

        if (!empty($costSum)) {
            $email->price()->cost($costSum);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@alt,'tuiholidays') or contains(@src,'.thomson.co.uk') or contains(@src,'.tui.co.uk')] | //a[contains(@href,'.tui.co.uk')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
            }
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

    public function detectEmailByHeaders(array $headers)
    {
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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
        $types = 2; // flight | hotel
        $cnt = $types * count(self::$dict);

        return $cnt;
    }

    private function parseEmail(Email $email)
    {
        $conf = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REF:'))}]/following::text()[normalize-space()][1]");
        $confName = $this->http->FindSingleNode("//text()[{$this->eq($this->t('BOOKING REF:'))}]", null, true, "/^\s*(.+?)[\s:]*$/");
        $email->ota()
            ->confirmation($conf, $confName);

        $reservationsText = array_unique($this->http->FindNodes("//text()[{$this->starts($this->t('YOUR '))}][not({$this->contains($this->t('notReseravtion'))})]",
            null, "#{$this->opt($this->t('YOUR '))}\s*(.*)#"));

        foreach ($reservationsText as $res) {
            if (in_array($res, (array) $this->t('FLIGHTS'))) {
                $this->parseFlight($email);
            } elseif (in_array($res, (array) $this->t('ACCOMMODATION'))) {
                $this->parseHotel($email);
            } else {
                $this->logger->debug('may have missed a type reservation: ' . $res);
                $email->add()->rental(); // for broke

                return false;
            }
        }

        $dateReservation = strtotime($this->http->FindSingleNode("//text()[{$this->contains($this->t('ISSUE DATE:'))}]/following::text()[normalize-space()!=''][1]"));
        $paxRoots = $this->http->XPath->query($xpath = "//text()[{$this->eq($this->t('Guest'))}]/ancestor::tr[{$this->contains($this->t('Age'))}][1]/following::tr[normalize-space()!='']");
        $travellers = [];
        $infants = [];

        foreach ($paxRoots as $paxRoot) {
            if ($this->http->XPath->query("./td[normalize-space()!='']", $paxRoot)->length < 2
                || $this->http->XPath->query("./td[normalize-space()!=''][{$this->contains($this->t('paxEnd'))}]",
                    $paxRoot)->length > 0
            ) {
                break;
            }

            if ($this->http->XPath->query("./td[normalize-space()!=''][2]//text()[{$this->eq($this->t('Infant'))}]", $paxRoot)->length > 0) {
                $infants[] = trim($this->http->FindSingleNode("./td[normalize-space()!=''][1]", $paxRoot), "*");
            } else {
                $travellers[] = trim($this->http->FindSingleNode("./td[normalize-space()!=''][1]", $paxRoot), "*");
            }
        }

        $infants = preg_replace("/^\s*(Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master) /", '', $infants);
        $travellers = preg_replace("/^\s*(Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master) /", '', $travellers);

        foreach ($email->getItineraries() as $it) {
            $it->general()
                ->travellers($travellers, true);

            if (!empty($infants)) {
                $it->general()
                    ->infants($infants, true);
            }

            if (!empty($dateReservation)) {
                $it->general()
                    ->date($dateReservation);
            }
        }

        return true;
    }

    private function parseFlight(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Leaving'))}]/ancestor::tr[1][following::tr[{$this->contains($this->t('Arriving'))}][1]][count(./td)>4]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-flight]: " . $xpath);

        $r = $email->add()->flight();

        $r->general()
            ->noConfirmation();
        $seats = [];

        if ($nodes->length < 3 && $this->http->XPath->query("//text()[{$this->contains($this->t('Seat Number'))}]")->length > 0) {
            $seats[0] = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seat Number'))}]/ancestor::tr[1]/td[3]//text()[{$this->contains($this->t('Seat Number'))}]",
                null, "/{$this->opt($this->t('Seat Number'))}\s+(\d{1,3}[A-Z])\s*/"));
            $seats[1] = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('Seat Number'))}]/ancestor::tr[1]/td[4]//text()[{$this->contains($this->t('Seat Number'))}]",
                null, "/{$this->opt($this->t('Seat Number'))}\s+(\d{1,3}[A-Z])\s*/"));
        }

        foreach ($nodes as $i => $root) {
            $s = $r->addSegment();

            $s->setConfirmation($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][3]/descendant::text()[normalize-space()]",
                $root, false, "#^([A-Z\d]+)$#"));

            $s->departure()
                ->date(strtotime($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]",
                    $root, false, "#{$this->opt($this->t('Leaving'))}\s*(.+)#")))
                ->name($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root))
                ->terminal($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][3]",
                    $root, false, "#^(?:Terminal)?\s*\:?\s*(.+?)\s*(?:Terminal)?$#i"), false, true)
                ->code($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Leaving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[contains(normalize-space(), '(')]",
                    $root, false, "#^\(([A-Z]{3})\)$#"));

            $airline = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Flight no'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][1]",
                $root);
            $node = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Flight no'))}]/ancestor::td[1]/descendant::text()[normalize-space()!=''][2]",
                $root, false, "#{$this->opt($this->t('Flight no'))}[:\s]*(.+)#");

            if (preg_match("#^([A-Z\d]{2,3}?)\s*(\d+)$#", $node, $m)) {
                // https://en.wikipedia.org/wiki/TUI_Airways
                if ($m[1] === 'TOM' || $airline === 'TUI Airways') {
                    $airline = 'BY';
                } elseif (preg_match("#^([A-Z\d][A-Z]|[A-Z][A-Z\d])$#", $m[1])) {
                    $airline = $m[1];
                }
                $s->airline()
                    ->name($airline)
                    ->number($m[2]);
            }

            $s->arrival()
                ->date(strtotime($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]",
                    $root, false, "#{$this->opt($this->t('Arriving'))}\s*(.+)#")))
                ->name($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root))
                ->terminal($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][3]",
                    $root, false, "#^(?:Terminal)?\s*(.+?)\s*(?:Terminal)?$#i"), false, true)
                ->code($this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[contains(normalize-space(), '(')]",
                    $root, false, "#^\(([A-Z]{3})\)$#"));

            if (!empty($seats[$i])) {
                $s->extra()
                    ->seats($seats[$i]);
            }

            $aircraft = $this->http->FindSingleNode("./following::tr[{$this->contains($this->t('Arriving'))}][1]/descendant::text()[{$this->eq($this->t('Arriving'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][2]/descendant::text()[normalize-space()][1][not(contains(normalize-space(), 'Flight Extras'))]", $root);

            if (!empty($aircraft)) {
                $s->extra()
                    ->aircraft($aircraft);
            }
        }
    }

    private function parseHotel(Email $email)
    {
        $xpath = "//text()[{$this->eq($this->t('Accommodation name'))}]/ancestor::td[1]";
        $nodes = $this->http->XPath->query($xpath);
        $this->logger->debug("[XPATH-hotel]: " . $xpath);

        foreach ($nodes as $root) {
            $r = $email->add()->hotel();

            if ($this->http->XPath->query("./descendant::text()[normalize-space()!='']", $root)->length === 2) {
                $r->general()
                    ->confirmation($this->http->FindSingleNode("./following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                        $root));
                $r->hotel()
                    ->name($this->http->FindSingleNode("./following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][last()]",
                        $root));
            }

            if ($this->http->XPath->query("./following::tr[normalize-space()!=''][position()<5]/td[{$this->starts($this->t('Destination'))}]/descendant::text()[normalize-space()!='']",
                    $root)->length === 2
            ) {
                $confirmation = $this->http->FindSingleNode("./following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][1]",
                    $root, true, "/\s(\d+\-\d+)/");

                if (!empty($confirmation)) {
                    $r->general()
                        ->confirmation($confirmation);
                }

                $r->hotel()
                    ->address(implode(', ',
                        $this->http->FindNodes("./following::tr[normalize-space()!=''][position()<5]/td[{$this->starts($this->t('Destination'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!='']",
                            $root)));

                $r->hotel()
                    ->name($this->http->FindSingleNode("./following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][last()]",
                        $root));
            }

            if ($this->http->XPath->query("./following::tr[normalize-space()!=''][position()<6]/td[{$this->contains($this->t('Check-in'))}]/descendant::text()[normalize-space()!='']",
                    $root)->length === 3
            ) {
                $checkIn = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][position()<6]/td[{$this->contains($this->t('Check-in'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][2]",
                    $root);
                $checkOut = $this->http->FindSingleNode("./following::tr[normalize-space()!=''][position()<6]/td[{$this->contains($this->t('Check-in'))}]/following-sibling::td[normalize-space()!=''][1]/descendant::text()[normalize-space()!=''][3]",
                    $root);
                $r->booked()
                    ->checkIn(strtotime($checkIn))
                    ->checkOut(strtotime($checkOut));
            }
            $roomsDescr = $this->http->FindNodes("./following::tr[normalize-space()!=''][position()<10]/td[({$this->starts($this->t('Room'))}) and ({$this->contains($this->t('description'))})]",
                $root, "#{$this->opt($this->t('description'))}\s*:\s*(.+)#");

            if (($cntRooms = count($roomsDescr)) > 0) {
                $r->booked()->rooms($cntRooms);

                foreach ($roomsDescr as $item) {
                    $r->addRoom()->setDescription($item);
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

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['ISSUE DATE:'], $words['Guest'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['ISSUE DATE:'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Guest'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$", "₹"], ["EUR", "GBP", "USD", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
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

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
