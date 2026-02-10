<?php

namespace AwardWallet\Engine\carey\Email;

use AwardWallet\Schema\Parser\Email\Email;

class BookingConfirmationHtml2016 extends \TAccountChecker
{
    public $mailFiles = "carey/it-16049732.eml, carey/it-16391939.eml, carey/it-26875095.eml, carey/it-27348993.eml, carey/it-41368867.eml, carey/it-41790509.eml, carey/it-42578853.eml, carey/it-42703654.eml";

    public $reFrom = ["confirmations@carey.com", "confirmations@careyconnect.com"];
    public $reSubject = [
        'Carey Reservation',
        'Carey Updated Reservation',
        'Carey Cancellation for',
    ];
    private $keywordProv = 'Carey';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $type = '';

        $node = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'If you would like to change or cancel this reservation')]/ancestor::*[contains(translate(.,'0123456789','#'),'#')][1]");

        if (preg_match_all("#([\d\+\-\(\)\. ]{5,})\s*\((.+?)\)#", $node, $m, PREG_SET_ORDER)) {
            $addedPhones = [];

            foreach ($m as $v) {
                $num = preg_replace(["#\s*\(\s*#", "#\s*\)\s*#"], ['(', ')'], trim($v[1], " ("));

                if (!in_array($num, $addedPhones)) {
                    $email->ota()->phone($num, trim($v[2], " ."));
                    $addedPhones[] = $num;
                }
            }
        }
        $email->ota()->code('carey');

        $xpathFirst = "//text()[{$this->starts(['Reservation Number', 'Reservation:'])}]/ancestor::*[./following-sibling::*[{$this->starts(['Reservation Number', 'Reservation:'])}]]";
        $xpathNext = "/following-sibling::*[{$this->starts(['Reservation Number', 'Reservation:'])}]";
        $xpathFull = "{$xpathFirst} | {$xpathFirst}{$xpathNext}";

        if (($nodes = $this->http->XPath->query($xpathFull))->length > 1) {
            $this->logger->debug("[XPATH]: " . $xpathFull);
            $this->logger->debug($nodes->length);

            foreach ($nodes as $i => $rootStart) {
                if ($i + 1 < $nodes->length) {
                    $prev1 = $this->http->XPath->query("./preceding-sibling::*", $rootStart)->length;
                    $prev2 = $this->http->XPath->query(".{$xpathNext}[1]/preceding-sibling::*", $rootStart)->length;
                    $cnt = $prev2 - $prev1;
                    $num = $i + 1;
                    $xpathRoot = "($xpathFull)[$num] | ($xpathFull)[$num]/following-sibling::*[position()<{$cnt}]";
                } else {
                    $num = $i + 1;
                    $xpathRoot = "($xpathFull)[$num] | ($xpathFull)[$num]/following-sibling::*";
                }

//                if ($this->http->FindSingleNode("({$xpathRoot})//text()[contains(.,'Trip Type')]/following::text()[string-length(normalize-space(.))>3][1]") === 'Transfer'
//                ) {
                $this->parseTripTransfer($xpathRoot, $email);
                $type = 'parseAirTrip';
//                } else {
//                    $this->parseCarRental($xpathRoot, $email);
//                    $type = 'parseCarRental';
//                }
            }
        } else {
            $xpath = "//*[{$this->contains(['Reservation Number', 'Reservation:'], 'text()')}]/ancestor::div[contains(.,'Passenger')][1]";

            if ($this->http->XPath->query("//img[(contains(@src, 'carey_') and contains(@src, '_car_icon.')) or contains(@alt,'Carey')]")->length > 0 //carey_cobranded_car_icon or carey_car_icon
                && ($nodes = $this->http->XPath->query($xpath))->length > 0
            ) {
                foreach ($nodes as $i => $root) {
//                    if ($this->http->FindSingleNode(".//text()[contains(.,'Trip Type')]/following::text()[string-length(normalize-space(.))>3][1]",
//                            $root) === 'Transfer'
//                    ) {
                    $num = $i + 1;
                    $xpathRoot = "({$xpath})[{$num}]";
                    $this->parseTripTransfer($xpathRoot, $email);
                    $type = 'parseAirTrip';
//                    } else {
//                        $num = $i + 1;
//                        $xpathRoot = "({$xpath})[{$num}]";
//                        $this->parseCarRental($xpathRoot, $email);
//                        $type = 'parseCarRental';
//                    }
                }
            } elseif ($this->http->XPath->query("//img[(contains(@src, 'carey_') and contains(@src, '_car_icon.')) or contains(@alt,'Carey')]")->length > 0) {
                $this->parseCarRentalText($email);
                $type = 'parseCarRentalText';
            } else {
                $xpath = '//*[contains(text(), "Reservation Number:")]/ancestor::div[1]';
                $nodes = $this->http->XPath->query($xpath);

                foreach ($nodes as $i=>$root) {
                    $num = $i + 1;
                    $this->parseTripTransfer('(' . $xpath . ")[$num]", $email);
                }
                $type = 'parseAirTrip';
            }
        }
        $email->setType($type);

        return $email;
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
                if (($fromProv || stripos($headers["subject"], $this->keywordProv) !== false)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(.), 'Thank you for choosing Carey')]")->length > 0
            || $this->http->XPath->query("//text()[contains(normalize-space(.), 'The following Carey reservation for')]")->length > 0
            || $this->http->XPath->query("//text()[contains(.,'Carey International, Inc. All rights reserved')]")->length > 0;
    }

    public static function getEmailTypesCount()
    {
        return 3;
    }

    private function parseTripTransfer(string $xpath, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $root = null;
        $xpath = '(' . $xpath . ')';
        $t = $email->add()->transfer();
        $confNo = str_replace(" ", "",
            $this->http->FindSingleNode("{$xpath}//span[contains(normalize-space(.), 'Reservation Number:') or contains(normalize-space(.), 'Reservation:')]/*[self::b or self::strong]",
                $root));

        if (!$confNo) {
            $confNo = str_replace(" ", "",
                $this->http->FindSingleNode("{$xpath}//text()[contains(normalize-space(.), 'Reservation Number:') or contains(normalize-space(.), 'Reservation:')]/following::*[1]",
                    $root));
        }

        $cancelled = false;
        $status = $this->http->FindSingleNode("{$xpath}//text()[contains(normalize-space(.),'Status:')]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root); //$this->array_shift_local($this->getNode(1, $root));
        $t->general()
            ->confirmation($confNo, 'Reservation Number', true)
            ->status($status)
            ->traveller($this->http->FindSingleNode("{$xpath}//text()[contains(normalize-space(.),'Passenger:')]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
                $root)); //$this->array_shift_local($this->getNode(1, $root, 'Reservation', 2)));

        if ($status === 'Cancelled') {
            $t->general()->cancelled();
            $confNo = str_replace(" ", "",
                $this->http->FindSingleNode("{$xpath}//text()[contains(normalize-space(.), 'Cancellation #')]/following::*[normalize-space(.)!=''][1]",
                    $root));

            if (!empty($confNo)) {
                $t->general()
                    ->cancellationNumber($confNo);
            }
            $cancelled = true;
        }

        $phoneText = [
            'If you experience difficulty locating your chauffeur, please call',
            'If you experience difficulty locating your driver, please call',
            'If your Passengers experience difficulty locating their chauffeur, please call',
        ];

        $phone = trim(
            $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($phoneText)}]/ancestor::*[contains(translate(.,'0123456789','##########'),'#')][1]",
                $root,
                false,
                "#please call\s*([\d\+\-\(\)\. ]{5,})#"),
            ' .');
        $desc = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($phoneText)}]",
            $root,
            false,
            "#(.+?),#");

        if (!empty($phone) || !$cancelled) {
            $t->program()
                ->phone($phone, $desc);
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("{$xpath}//text()[contains(., 'Estimate Quote:')]/following::text()[normalize-space(.)!=''][1]",
            $root, true, "#(.*?)\+#"));

        if (!empty($tot['Total'])) {
            $t->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }
        $tot = $this->getTotalCurrency($this->http->FindSingleNode("{$xpath}//text()[contains(., 'Estimate Quote:')]/following::text()[normalize-space(.)!=''][1]",
            $root, true, "#\+\s*(?:Taxes|Incidentals)(.*)#"));

        if (!empty($tot['Total'])) {
            $t->price()
                ->tax($tot['Total'])/*
                ->currency($tot['Currency'])*/;
        }

        if (!empty($accountNumber = str_replace(" ", ', ',
            $this->http->FindSingleNode("//text()[contains(., 'Booked By')]/following::text()[normalize-space(.)!=''][1]", null, false, "#([A-Z\d]{5,})\s*$#")))
        ) {
            $t->program()
                ->account($accountNumber, false);
        } elseif (!empty($accountNumber =
            $this->http->FindSingleNode("//text()[contains(., 'Account:')]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
                $root, false, "#([A-Z\d]+)\s*$#"))
        ) {
            $t->program()
                ->account($accountNumber, false);
        }

        $date = $this->http->FindSingleNode("{$xpath}//text()[contains(normalize-space(.),'Date of Service:')]/ancestor::td[1]", $root, false, '/:\s*(.+)/');

        $s = $t->addSegment();

        $s->extra()
            ->type($this->http->FindSingleNode("{$xpath}//text()[contains(., 'Vehicle:')]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
                $root), false, true);

        $pickupTimeText = ['Pickup Time:', 'Pick up Time:', 'Pick-up Time:'];
        $dropofTimeText = ['Dropoff Time:', 'Drop off Time:', 'Drop-off Time:'];
        $pickupTime = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($pickupTimeText)}]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root);
        $dropofTime = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($dropofTimeText)}]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root);

        $depNameAddress = ['Pickup Address', 'Pick up Address', 'Pick-up Address'];
        $depNameLocation = ['Pickup Location', 'Pick up Location', 'Pick-up Location'];
        $depName = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($depNameAddress)}]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root);
        $depNameLoc = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($depNameLocation)}]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root);
        $depName = implode(', ', array_filter([$depName, $depNameLoc]));
        $s->departure()
            ->date(strtotime($date . ', ' . $pickupTime));

        if (!empty($depName) || !$cancelled) {
            if (preg_match("/(.+)\s+A\/P$/", $depName, $m)) {
                $depName = $m[1] . ' Airport';
            }
            $s->departure()->name($depName);
        }

        $arrNameAddress = ['Dropoff Address', 'Drop off Address', 'Drop-off Address'];
        $arrNameLocation = ['Dropoff Location', 'Drop off Location', 'Drop-off Location'];
        $arrName = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($arrNameAddress)}]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root);
        $arrNameLoc = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($arrNameLocation)}]/following::text()[normalize-space()!=''][1]/ancestor::*[position()<=3][self::strong or self::b]",
            $root);
        $arrName = implode(', ', array_filter([$arrName, $arrNameLoc]));

        if (!empty($arrName) || !$cancelled) {
            if (preg_match("/(.+)\s+A\/P$/", $arrName, $m)) {
                $arrName = $m[1] . ' Airport';
            }
            $s->arrival()->name($arrName);
        }

        if (empty($dropofTime)) {
            $s->arrival()
                ->noDate();
        } else {
            $s->arrival()
                ->date(strtotime($date . ', ' . $dropofTime));
        }

        return true;
    }

    private function parseCarRental(string $xpath, Email $email)
    {
        $this->logger->debug(__METHOD__);
        $root = null;
        $xpath = '(' . $xpath . ')';
        $r = $email->add()->rental();
        $r->general()
            ->confirmation(str_replace(" ", "",
                $this->http->FindSingleNode("{$xpath}//*[{$this->contains(['Reservation Number', 'Reservation:'], 'text()')}]", $root, false,
                    '/(?:Reservation Number:|Reservation:)\s*(.*)/')))
            ->status($status = $this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Status:')]/strong", $root))
            ->traveller($this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Passenger:')]/strong", $root));

        if (in_array($status, ['Cancelled', 'Canceled'])
            && !empty($cancelNo = $this->http->FindSingleNode("//text()[{$this->starts('Cancellation #:')}]/following::text()[normalize-space()!=''][1]",
                $root))
        ) {
            $r->general()
                ->cancellationNumber($cancelNo)
                ->cancelled();
        }

        $phoneText = [
            'If you experience difficulty locating your chauffeur, please call',
            'If you experience difficulty locating your driver, please call',
            'If your Passengers experience difficulty locating their chauffeur, please call',
        ];

        $phone = trim(
            $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($phoneText)}]/ancestor::*[contains(translate(.,'0123456789','##########'),'#')][1]",
                $root,
                false,
                "#please call\s*([\d\+\-\(\)\. ]{5,})#"),
            ' .');
        $desc = $this->http->FindSingleNode("{$xpath}//text()[{$this->contains($phoneText)}]",
            $root,
            false,
            "#(.+?),#");

        if (!$r->getCancelled()) {
            $r->program()->phone($phone, $desc);
        }

        $r->extra()
            ->company($this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Service Provider:')]/strong", $root));

        $r->car()
            ->type($this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Vehicle:')]/strong", $root), false, true);

        $date = strtotime($this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Date of Service:')]/strong", $root));

        $name = $this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Pick up Address:')]", $root, false,
            '/Pick up Address:\s*(.*)/');

        if (empty($name)) {
            $name = $this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Pick up Location:')]", $root, false,
                '/Pick up Location:\s*(.*)/');
        }

        $r->pickup()
            ->date(strtotime($this->http->FindSingleNode("{$xpath}//*[{$this->contains(['Pick up Time:', 'Pickup Time:'])}]/strong", $root),
                $date));

        if (!$r->getCancelled()) {
            $r->pickup()->location($name);
        }

        $name = $this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Drop off Address:')]", $root, false,
            '/Drop off Address:\s*(.*)/');

        if (empty($name)) {
            $name = $this->http->FindSingleNode("{$xpath}//*[contains(text(), 'Drop-off Location:')]", $root, false,
                '/Drop-off Location:\s*(.*)/');
        }

        if (preg_match('/(.*?)\s+(\d+:\d+\s*[AP]M)/', $name, $matches)) {
            $r->dropoff()
                ->date(strtotime($matches[2], $date))
                ->location($matches[1]);
        } else {
            $r->dropoff()->noDate();

            if (!$r->getCancelled()) {
                $r->dropoff()->location($name);
            }
        }

        $tot = $this->getTotalCurrency($this->http->FindSingleNode("{$xpath}//text()[contains(., 'Estimate Quote:')]/following-sibling::strong[1]",
            $root, false, '/^[^\+]*([A-Z]{3}\s+[\d.,]+)\s+[^+]*$/'));

        if (!empty($tot['Total'])) {
            $r->price()
                ->cost($tot['Total'])
                ->currency($tot['Currency']);
        }

        $accountNumbers = array_filter([
            $this->http->FindSingleNode("{$xpath}//text()[contains(., 'Account:')]/following-sibling::strong[1]", $root, false,
                '/\bWA\d{5,}\s*$/'),
        ]);

        if (!empty($accountNumbers)) {
            $r->program()
                ->accounts($accountNumbers, false);
        }

        return true;
    }

    // it-4719660.eml
    private function parseCarRentalText(Email $email)
    {
        $this->logger->debug(__METHOD__);

        foreach ($this->http->XPath->query('//img[contains(@src, "carey_car_icon.")]/ancestor::div[1]') as $root) {
            $r = $email->add()->rental();
            $date = null;
            $root->nodeValue = str_replace(' ', ' ', $root->nodeValue);

            if (preg_match('/Reservation:\s*([A-Z\d-]+)/', $root->nodeValue, $matches)) {
                $r->general()->confirmation($matches[1]);
            }

            if (preg_match('/Status:\s*(\w+)/', $root->nodeValue, $matches)) {
                $r->general()->status($matches[1]);
            }

            if (preg_match('/Service Provider:\s*(.*)/', $root->nodeValue, $matches)) {
                $r->extra()->company(trim($matches[1]));
            }

            if (preg_match('/Passenger:\s*(.*)/', $root->nodeValue, $matches)) {
                $r->general()->traveller(trim($matches[1]));
            }

            if (preg_match('/Date of Service:\s*(.*)/', $root->nodeValue, $matches)) {
                $date = strtotime($matches[1]);
            }

            if (preg_match('/Pickup Time:\s*(\d+:\d+)/', $root->nodeValue, $matches)) {
                $r->pickup()->date(strtotime($matches[1], $date));
            }

            if (preg_match('/(?:Pickup Location|Pick up Address):\s*(.*)/', $root->nodeValue, $matches)) {
                $r->pickup()->location(trim($matches[1]));
            }

            if (preg_match('/(?:Dropoff Location|Drop off Address):\s*(.*)/', $root->nodeValue, $matches)) {
                $r->dropoff()->location(trim($matches[1]));
            } else {
                if (isset($item['PickupLocation'])) {
                    $r->dropoff()->location($item['PickupLocation']);
                }
            }

            if (preg_match('/Account:\s*(.*)/', $root->nodeValue, $matches)) {
                $r->program()->account(trim($matches[1]), false);
            }

            $r->dropoff()->noDate();
        }

        return true;
    }

    private function array_shift_local(array $array)
    {
        if (isset($array[0])) {
            return $array[0];
        } else {
            return null;
        }
    }

    private function getTotalCurrency($node)
    {
        $node = trim($node, "., ");
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,]*\d)#", $node, $m)
            || preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#(?<c>\-*?)(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
        ) {
            $m['t'] = preg_replace('/\s+/', '', $m['t']);            // 11 507.00	->	11507.00
            $m['t'] = preg_replace('/[,.](\d{3})/', '$1', $m['t']);    // 2,790		->	2790		or	4.100,00	->	4100,00
            $m['t'] = preg_replace('/,(\d{2})$/', '.$1', $m['t']);    // 18800,00		->	18800.00
            $tot = trim($m['t'], "., ");
            $cur = $m['c'];
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function contains($field, $text = ".")
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(normalize-space(' . $text . '),"' . $s . '")';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }
}
