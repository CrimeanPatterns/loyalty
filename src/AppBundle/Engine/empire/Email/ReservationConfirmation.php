<?php

namespace AwardWallet\Engine\empire\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "empire/it-36947877.eml, empire/it-36952598.eml, empire/it-36952602.eml, empire/it-37414950.eml, empire/it-37828416.eml, empire/it-43176378.eml, empire/it-43249696.eml, empire/it-43305738.eml, empire/it-43511510.eml, empire/it-43722831.eml, empire/it-43845075.eml, empire/it-43915478.eml";

    public $reFrom = ["@empirecls.com"];
    public $reBody = [
        'en' => ['TRAVEL ITINERARY:', 'UNABLE TO LOCATE YOUR CHAUFFEUR'],
    ];
    public $reSubject = [
        'EmpireCLS Change Reservation Confirmation',
        'EmpireCLS New Reservation Confirmation',
        'EmpireCLS Reservation Confirmation',
        'EmpireCLS Reservation Receipt - Final Charges',
    ];
    public $lang = '';
    public $pdfNamePattern = ".*pdf";
    public static $dict = [
        'en' => [
            'PICKUP:'          => ['PICKUP:', 'PICKUP LOCATION:'],
            'DROP OFF:'        => ['DROP OFF:', 'DROPOFF LOCATION:'],
            'Customer:'        => 'Customer:',
            'Passenger:'       => ['Passenger:', 'Passenger name:'],
            'ESTIMATED COST'   => ['ESTIMATED COST', 'FINAL CHARGES'],
            'Estimated Total:' => ['Estimated Total:', 'Trip Total:', 'Final Total:'],
        ],
    ];
    private $keywordProv = ['EmpireCLS', 'Empire CLS'];
    private $keywordPlain = '/////////////////////////';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);
        $type = '';

        if (isset($pdfs) && count($pdfs) > 0) {
            foreach ($pdfs as $pdf) {
                if (($text = \PDF::convertToText($parser->getAttachmentBody($pdf))) !== null
                    && $this->detectBody($text)
                    && $this->assignLangText($text)
                ) {
                    if ($this->parseEmailPdf($text, $email) === false
                    ) {
                        return $email;
                    } elseif (count($email->getItineraries()) > 0) {
                        $type = 'Pdf';
                    }
                }
            }
        }

        if (!isset($type) && $this->http->XPath->query("//img | //a")->length > 0) {
            if (!$this->assignLangHttp()) {
                $this->logger->debug('can\'t determine a language');

                return $email;
            }
            $this->parseEmail($email);
            $type = 'Html';
        }

        if (!isset($type) && strpos($parser->getPlainBody(), $this->keywordPlain) !== false) {
            if (!$this->assignLangText($parser->getPlainBody())) {
                $this->logger->debug('can\'t determine a language');

                return $email;
            }
            $this->parseEmailPlain($parser->getPlainBody(), $email);
            $type = 'Plain';
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($type) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[@alt='EmpireCLS' or @alt='Empire CLS' or contains(@src,'.empirecls.com/')] | //a[contains(@href,'.empirecls.com/')]")->length > 0) {
            return $this->assignLangHttp();
        }
        $pdfs = $parser->searchAttachmentByName($this->pdfNamePattern);

        foreach ($pdfs as $pdf) {
            $text = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (($this->stripos($text, $this->keywordProv))
                && $this->detectBody($text)
                && $this->assignLangText($text)
            ) {
                return true;
            }
        }

        if ($this->stripos($parser->getPlainBody(), $this->keywordProv)
            && strpos($parser->getPlainBody(), $this->keywordPlain) !== false
        ) {
            return $this->assignLangText($parser->getPlainBody());
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
                if (($fromProv && stripos($headers["subject"], $reSubject) !== false)
                    || $this->stripos($headers["subject"], $this->keywordProv)
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
        $formats = 3; // html | pdf | plane
        $cnt = $formats * count(self::$dict);

        return $cnt;
    }

    /**
     * "Przed" -> "P ?r ?z ?e ?d ?".
     */
    public function addSpace($text)
    {
        return preg_replace("#([^\s\\\])#u", "$1 ?", $text);
    }

    private function parseEmailPdf($textPDF, Email $email): bool
    {
        $customerBlock = $this->findCutSection($textPDF, $this->t('CUSTOMER INFORMATION'),
            $this->t('TRIP INFORMATION'));
        $tripBlock = $this->findCutSection($textPDF, $this->t('TRIP INFORMATION'), $this->t('ADDITIONAL DETAILS'));
        $additionalBlock = $this->findCutSection($textPDF, $this->t('ADDITIONAL DETAILS'),
            $this->t('UNABLE TO LOCATE YOUR CHAUFFEUR'));

        if (empty($customerBlock) || empty($tripBlock) || empty($additionalBlock)) {
            $this->logger->debug("other format pdf");

            return true; // go to byBody
        }

        $r = $email->add()->transfer();

        $r->program()
            ->account($this->re("#{$this->opt($this->t('Customer ID:'))}[ ]*([\w\-]+)#", $customerBlock), false);

        //general info
        $status = $this->re("#{$this->opt($this->t('TRAVEL ITINERARY:'))}[^\n]*\s+?.*?\n[ ]{0,5}([A-Z]+(?:[ ][A-Z]+)?)(?:[ ]{3,}|\n)#s", $textPDF);
        $r->general()
            ->traveller(trim($this->re("#{$this->opt($this->t('Passenger:'))}[ ]*(.+)#", $tripBlock)))
            ->confirmation($this->re("#{$this->opt($this->t('Confirmation Number'))}[ ]+([\w\-]+)#", $textPDF))
            ->status($status);

        if (in_array($status, ['CANCELLED', 'CANCELED'])) {
            $r->general()->cancelled();
        }

        // provider phones
        $phones = [];
        $node = $this->re("#{$this->opt($this->t('Please call dispatch'))}.+#s", $tripBlock);

        if (preg_match("#{$this->opt($this->t('Please call dispatch'))} ([\(\) \-\+\d]{5,})\s*([^\.]*)#s", $node, $m)) {
            $m[2] = trim(preg_replace("#\s+#", ' ', $m[2]));
            $descr = !empty($m[2]) ? $m[2] : null;

            $r->program()
                ->phone(trim($m[1]), $descr);
            $phones[] = trim($m[1]);
        }
        $node = $this->re("#({$this->opt($this->t('FOR HELP IN LOCATING YOUR DRIVER CALL'), true)}.+)#s", $tripBlock);

        if (preg_match("#({$this->opt($this->t('FOR HELP IN LOCATING YOUR DRIVER CALL'), true)})\s+([\(\) \-\+\d]{5,})\s*([^\.]*)#",
            $node, $m)) {
            $descr = !empty(trim($m[1])) ? trim($m[1]) : null;

            $r->program()
                ->phone(trim($m[2]), $descr);
            $phones[] = trim($m[2]);
        }
        $node = $this->re("#{$this->opt($this->t('UNABLE TO LOCATE YOUR CHAUFFEUR'))}(.+)#s", $textPDF);
        $node = $this->http->FindPreg("#{$this->opt($this->t('CALL'))}(.+?)(?:{$this->opt($this->t('FOR ASSISTANCE'))}|\n\n)#s", false, $node);

        if (preg_match_all("#([\(\)\d \-\+]{5,})#", $node, $m)) {
            foreach ($m[1] as $v) {
                if (!in_array(trim($v), $phones)) {
                    $r->program()
                        ->phone(trim($v));
                    $phones[] = trim($v);
                }
            }
        }

        // prices
        $nodesSum = $this->re("#{$this->opt($this->t('ESTIMATED COST'))}.+?{$this->t('CARD NO')}[^\n]+\n(.+)#s",
            $additionalBlock);

        if (!empty($nodesSum)) {
            $pos = [0, mb_strlen($this->re("#([^\n]+?[ ]{4,}){$this->opt($this->t('Estimated Total:'))}#", $nodesSum))];
            $table = $this->splitCols($nodesSum, $pos);
            $nodesSum = array_filter(array_map("trim", explode("\n", $table[1])));
            $notFees = array_map(function ($s) {
                return trim($s, ": ");
            }, array_merge((array) $this->t('Tax/BF/VAT'), (array) $this->t('Estimated Total:')));

            foreach ($nodesSum as $root) {
                $subj = explode("|", preg_replace("#[ ]{3,}#", "|", $root));

                if (count($subj) !== 2) {
                    $this->logger->debug("check format ESTIMATED COST");

                    return false;
                }

                $name = trim($subj[0], ": ");

                if ($name == $this->t('Subtotal')) {
                    break;
                }

                if ($this->stripos($name, $this->t('DISCOUNT DEDUCTED'))) {
                    $sum = $this->getTotalCurrency($this->re("#(?:\[.+\])?(.+)$#", $subj[1]));

                    if ($sum['Total'] !== null) {
                        $r->price()->discount($sum['Total']);
                    }

                    continue;
                }

                if (in_array($name, $notFees)) {
                    continue;
                }
                $sum = $this->getTotalCurrency($this->re("#(?:\[.+\])?(.+)$#", $subj[1]));

                if ($sum['Total'] !== null) {
                    $r->price()->fee($name, $sum['Total']);
                }
            }
            $sum = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Estimated Total:'))}[ ]{3,}(.+)#",
                $table[1]));
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
            $sum = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Tax/BF/VAT'))}[ ]{3,}(.+)#", $table[1]));

            if ($sum['Total'] !== null) {
                $r->price()
                    ->tax($sum['Total']);
            }
        }

        // Segments
        $s = $r->addSegment();

        //segment table
        $node = $this->re("#{$this->opt($this->t('Date:'), true)}[^\n]+\n(.+?)(?:\n\n|HOURLY DIRECTIONS|TOTAL HOURS:)#s", $tripBlock);
        $pos = $this->colsPos($this->re("#(.+)#", $node));
        $pos[0] = 0;

        if (count($pos) != 4) {
            $this->logger->debug("other format TripInfo");

            return true;
        }
        $table = $this->splitCols($node, $pos);

        // date
        $node = $this->re("#{$this->opt($this->t('Date:'), true)}([^\n]+?)\|#", $tripBlock);
        $date = $this->normalizeDate(trim($node));
        $time = $this->re("#(\d+:\d+(\s*[ap]m)?)#i",
            $this->re("#{$this->opt($this->t('Time:'), true)}([^\n]+?)\|#", $tripBlock));
        $s->departure()
            ->date(strtotime($time, $date));

        // departure
        $node = $this->trimPoint($this->re("#{$this->opt($this->t('PICKUP:'), true)}\s*(.+)#s", $table[0]));

        if (preg_match("#^(.*)\s*\(([A-Z]{3})\)$#", $node, $m)) {
            if (!empty($m[1])) {
                $s->departure()
                    ->name($m[1]);
            }
            $s->departure()
                ->code($m[2]);
        } else {
            $s->departure()
                ->name($node);
        }

        // arrival
        $node = $this->re("#{$this->opt($this->t('DROP OFF:'), true)}\s*(.+?)(?:\n\n|$)#s", $table[1]);
        $point = $this->trimPoint($node);

        if (preg_match("#^(.*)\s*\(([A-Z]{3})\)$#", $point, $m)) {
            if (!empty($m[1])) {
                $s->arrival()
                    ->name($m[1]);
            }
            $s->arrival()
                ->code($m[2]);
        } else {
            $s->arrival()
                ->name($point);
        }

        // search arrival time otherwise noDate
        if (preg_match("#{$this->opt($this->t('Flight/Train Time:'), true)}\s*(?<time>.+)$#", $node, $m)) {
            $s->arrival()
                ->date(strtotime($m['time'], $date));
        } else {
            $node = preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Details:'), true)}\s+(.+)#s", $tripBlock));

            if (preg_match("#FOR A (?<time>\d+:\d+(?:\s*[ap]m)?) WHEELS UP\s*\*\*FINAL#i", $node, $m)
                || preg_match("#TO .+ AIRPORT BY (?<time>\d+:\d+(?:\s*[ap]m)?) \*\*FINAL\*\*#i", $node, $m)
            ) {
                $s->arrival()
                    ->date(strtotime($m['time'], $date));
            } elseif (!empty($node) && preg_match_all("#\b(?<time>\d+:\d+(?:\s*[ap]m)?)\b#", $node,
                    $v) && count($v['time']) == 1
            ) {
                $s->arrival()
                    ->date(strtotime(array_shift($v['time']), $date));
            } else {
                $node = $this->re("#{$this->opt($this->t('TOTAL HOURS:'))}[ ]+(\d[\d\.]+)#", $node);

                if (!empty($node)) {
                    $s->arrival()
                        ->date(strtotime("+ " . (float) $node . ' hour', $s->getDepDate()));
                } else {
                    $s->arrival()->noDate();
                }
            }
        }

        unset($table[0], $table[1]);
        $tableVertical = implode("\n", $table);

        $adults = $this->re("#{$this->opt($this->t('PASSENGERS:'), true)}\s*(\d{1,3})$#m", $tableVertical);
        $type = $this->re("#{$this->opt($this->t('VEHICLE MAKE:'), true)}\s*(.+)#", $tableVertical);
        $s->extra()
            ->adults($adults)
            ->type($type);

        return true;
    }

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->transfer();

        if ($acc = str_replace('.', '', $this->getTdText($this->t('Customer ID:')))) {
            $r->program()
                ->account($acc, false);
        }

        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('Confirmation Number'))}]/following::text()[normalize-space()!=''][1]"))
            ->status($status = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TRAVEL ITINERARY:'))}]/following::text()[normalize-space()!=''][1]"));

        if (in_array($status, ['CANCELLED', 'CANCELED'])) {
            $r->general()->cancelled();
        }

        // provider phones
        $phones = [];
        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Please call dispatch'))}]");

        if (preg_match("#{$this->opt($this->t('Please call dispatch'))} ([\(\) \-\+\d]{5,})\s*([^\.]*)#", $node, $m)) {
            $descr = !empty(trim($m[2])) ? trim($m[2]) : null;

            $r->program()
                ->phone(trim($m[1]), $descr);
            $phones[] = trim($m[1]);
        }
        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('FOR HELP IN LOCATING YOUR DRIVER CALL'))}]");

        if (preg_match("#{$this->opt($this->t('FOR HELP IN LOCATING YOUR DRIVER CALL'))} ([\(\) \-\+\d]{5,})\s*([^\.]*)#",
            $node, $m)) {
            $descr = !empty(trim($m[2])) ? trim($m[2]) : null;

            $r->program()
                ->phone(trim($m[1]), $descr);
            $phones[] = trim($m[1]);
        }
        $node = $this->http->FindSingleNode("//text()[({$this->contains($this->t('UNABLE TO LOCATE YOUR CHAUFFEUR'))}) and ({$this->contains($this->t('CALL'))}) and ({$this->contains($this->t('FOR ASSISTANCE'))})]");

        if (preg_match_all("#\b([\(\)\d \-\+]{5,})\b#", $node, $m)) {
            foreach ($m[1] as $v) {
                if (!in_array(trim($v), $phones)) {
                    $r->program()
                        ->phone(trim($v));
                    $phones[] = trim($v);
                }
            }
        }

        // prices
        $nodesSum = $this->http->XPath->query("//text()[{$this->starts($this->t('Estimated Total:'))}]/ancestor::table[1]/descendant::tr[normalize-space()!=''][position()>1]");

        if ($nodesSum->length > 0) {
            $notFees = array_map(function ($s) {
                return trim($s, ": ");
            }, array_merge((array) $this->t('Tax/BF/VAT'), (array) $this->t('Estimated Total:')));

            foreach ($nodesSum as $root) {
                $name = trim($this->http->FindSingleNode("./td[normalize-space()!=''][1]", $root), ": ");

                if ($name == $this->t('Subtotal')) {
                    break;
                }

                if (in_array($name, $notFees)) {
                    continue;
                }
                $sum = $this->getTotalCurrency($this->http->FindSingleNode("./td[normalize-space()!=''][2]", $root,
                    false,
                    "#(?:\[.+\])?(.+)$#"));

                if ($sum['Total'] !== null) {
                    $r->price()->fee($name, $sum['Total']);
                }
            }
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Estimated Total:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
            $sum = $this->getTotalCurrency($this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Tax/BF/VAT'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]"));

            if ($sum['Total'] !== null) {
                $r->price()
                    ->tax($sum['Total']);
            }
        }

        // Segments
        $nodes = $this->http->XPath->query("//text()[{$this->eq($this->t('TRIP INFORMATION'))}]/ancestor::table[{$this->contains($this->t('PICKUP:'))}][1]");
        $travellers = [];

        foreach ($nodes as $root) {
            // travellers
            $pax = $this->getTdText($this->t('Passenger:'), $root);

            if (!in_array($pax, $travellers)) {
                $r->general()
                    ->traveller($pax);
                $travellers[] = $pax;
            }

            $s = $r->addSegment();
            // date
            $date = $this->normalizeDate($this->getTdText($this->t('Date:'), $root));
            $s->departure()
                ->date(strtotime($this->re("#(\d+:\d+(?:\s*[ap]m)?)#i", $this->getTdText($this->t('Time:'), $root)),
                    $date));

            // departure
            $pickup = $this->trimPoint($this->getTdText($this->t('PICKUP:'), $root));

            if (preg_match("#^(.*)\s*\(([A-Z]{3})\)$#", $pickup, $m)) {
                if (!empty($m[1])) {
                    $s->departure()
                        ->name($m[1]);
                }
                $s->departure()
                    ->code($m[2]);
            } else {
                $s->departure()->name($pickup);
            }

            // arrival
            $dropoff = $this->getTdText($this->t('DROP OFF:'), $root);

            if (preg_match("#(?<name>.+?)\s*(?:{$this->opt($this->t('Flight/Train #:'))}.*?{$this->opt($this->t('Flight/Train Time:'))}\s*(?<time>.+))?$#", $dropoff, $m)) {
                $point = $this->trimPoint($m['name']);

                if (preg_match("#^(.*)\s*\(([A-Z]{3})\)$#", $point, $v)) {
                    if (!empty($v[1])) {
                        $s->arrival()
                            ->name($v[1]);
                    }
                    $s->arrival()
                        ->code($v[2]);
                } else {
                    $s->arrival()
                        ->name($point);
                }

                // search arrival time otherwise noDate
                $dateArr = null;

                if (!empty($m['time'])) {
                    $dateArr = strtotime($m['time'], $date);
                } else {
                    $node = $this->getTdText($this->t('Details:'));

                    if (preg_match("#FOR A (?<time>\d+:\d+(?:\s*[ap]m)?) WHEELS UP\s*\*\*FINAL#i", $node, $m)
                        || preg_match("#TO .+ AIRPORT BY (?<time>\d+:\d+(?:\s*[ap]m)?) \*\*FINAL\*\*#i", $node, $m)
                    ) {
                        $dateArr = strtotime($m['time'], $date);
                    } elseif (!empty($node) && preg_match_all("#\b(?<time>\d+:\d+(?:\s*[ap]m)?)\b#", $node,
                            $v) && count($v['time']) == 1
                    ) {
                        $dateArr = strtotime(array_shift($v['time']), $date);
                    } else {
                        $node = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('TOTAL HOURS:'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1]");

                        if (!empty($node)) {
                            $dateArr = strtotime('+ ' . ((float) $node) * 60 . ' minutes', $s->getDepDate());
                        } else {
                            $s->arrival()->noDate();
                        }
                    }
                }

                if (!empty($s->getDepDate()) && !empty($dateArr) && $s->getDepDate() === $dateArr) {
                    $s->arrival()->noDate();
                } elseif ($dateArr) {
                    $s->arrival()->date($dateArr);
                }
            } elseif ($dropoff === '') {
                $email->removeItinerary($r);
                $email->setIsJunk(true, 'Required both location fields.');

                return;
            }

            $s->extra()
                ->adults($this->getTdText($this->t('PASSENGERS:')))
                ->type($this->getTdText($this->t('VEHICLE MAKE:')));
        }
    }

    private function parseEmailPlain($text, Email $email): void
    {
        $tripBlock = $this->findCutSection($text, $this->t('TRIP INFORMATION:'), $this->t('ADDITIONAL DETAILS'));
        $customerBlock = $this->findCutSection($text, $this->t('CUSTOMER INFORMATION:'),
            $this->t('TRIP INFORMATION:'));

        $r = $email->add()->transfer();

        if ($acc = str_replace('.', '',
            $this->http->FindPreg("#{$this->opt($this->t('Customer ID:'))}[ ]+(.+)#", false, $text))
        ) {
            $r->program()
                ->account($acc, false);
        }

        $r->general()
            ->traveller(trim($this->re("#{$this->opt($this->t('Passenger:'))}[ ]*(.+)#", $customerBlock)))
            ->confirmation($this->http->FindPreg("#{$this->opt($this->t('Confirmation Number'))}[ ]+(.+)#", false,
                $text))
            ->status($status = $this->http->FindPreg("#{$this->opt($this->t('TRAVEL ITINERARY:'))}[ ]+(.+)#", false,
                $text));

        if (in_array($status, ['CANCELLED', 'CANCELED'])) {
            $r->general()->cancelled();
        }

        // provider phones
        $phones = [];
        $node = $this->re("#{$this->opt($this->t('Please call dispatch'))}.+#s", $tripBlock);

        if (preg_match("#{$this->opt($this->t('Please call dispatch'))} ([\(\) \-\+\d]{5,})\s*([^\.]*)#s", $node, $m)) {
            $m[2] = trim(preg_replace("#\s+#", ' ', $m[2]));
            $descr = !empty($m[2]) ? $m[2] : null;

            $r->program()
                ->phone(trim($m[1]), $descr);
            $phones[] = trim($m[1]);
        }
        $node = $this->re("#({$this->opt($this->t('FOR HELP IN LOCATING YOUR DRIVER CALL'), true)}.+)#s", $tripBlock);

        if (preg_match("#({$this->opt($this->t('FOR HELP IN LOCATING YOUR DRIVER CALL'), true)})\s+([\(\) \-\+\d]{5,})\s*([^\.]*)#",
            $node, $m)) {
            $descr = !empty(trim($m[1])) ? trim($m[1]) : null;

            $r->program()
                ->phone(trim($m[2]), $descr);
            $phones[] = trim($m[2]);
        }
        $node = $this->re("#{$this->opt($this->t('UNABLE TO LOCATE YOUR CHAUFFEUR'))}(.+)#s", $text);
        $node = $this->http->FindPreg("#{$this->opt($this->t('CALL'))}(.+?)(?:{$this->opt($this->t('FOR ASSISTANCE'))}|\n\n)#s",
            false, $node);

        if (preg_match_all("#([\(\)\d \-\+]{5,})#", $node, $m)) {
            foreach ($m[1] as $v) {
                if (!in_array(trim($v), $phones)) {
                    $r->program()
                        ->phone(trim($v));
                    $phones[] = trim($v);
                }
            }
        }

        // prices
        $nodesSum = $this->re("#{$this->opt($this->t('ESTIMATED COST'))}.*?{$this->t('CARD NO')}[^\n]+\n(.+?)\n{$this->opt($this->t('ADDITIONAL DETAILS'))}#s",
            $text);

        if (!empty($nodesSum)) {
            $table = $nodesSum;
            $nodesSum = array_filter(array_map("trim", explode("\n", $nodesSum)));
            $notFees = array_map(function ($s) {
                return trim($s, ": ");
            }, array_merge((array) $this->t('Tax/BF/VAT'), (array) $this->t('Estimated Total:')));

            foreach ($nodesSum as $root) {
                $subj = explode(":", $root);

                if (count($subj) !== 2) {
                    $this->logger->debug("check format ESTIMATED COST");

                    return;
                }

                $name = trim($subj[0], ": ");

                if ($name == $this->t('Subtotal')) {
                    break;
                }

                if (in_array($name, $notFees)) {
                    continue;
                }
                $sum = $this->getTotalCurrency($this->re("#(?:\[.+\])?(.+)$#", $subj[1]));

                if ($sum['Total'] !== null) {
                    $r->price()->fee($name, $sum['Total']);
                }
            }
            $sum = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Estimated Total:'))}[ ]*(.+)#",
                $table));
            $r->price()
                ->total($sum['Total'])
                ->currency($sum['Currency']);
            $sum = $this->getTotalCurrency($this->re("#{$this->opt($this->t('Tax/BF/VAT'))}:[ ]*(.+)#", $table));

            if ($sum['Total'] !== null) {
                $r->price()
                    ->tax($sum['Total']);
            }
        }

        // Segments
        $s = $r->addSegment();

        //segment table
        $table = $this->findCutSection($text, $this->t('TRIP INFORMATION:'), $this->keywordPlain);

        // date
        $node = $this->re("#{$this->opt($this->t('Date:'), true)}([^\n]+)#", $table);
        $date = $this->normalizeDate(trim($node));
        $time = $this->re("#(\d+:\d+(\s*[ap]m)?)#i",
            $this->re("#{$this->opt($this->t('Time:'), true)}([^\n]+)#", $table));
        $s->departure()
            ->date(strtotime($time, $date));

        // departure
        $node = $this->trimPoint($this->re("#{$this->opt($this->t('PICKUP:'), true)}\s*(.+?)\s*{$this->opt($this->t('DROP OFF:'), true)}#s",
            $table));

        if (preg_match("#^(.*)\s*\(([A-Z]{3})\)#", $node, $m)) {
            if (!empty($m[1])) {
                $s->departure()
                    ->name($m[1]);
            }
            $s->departure()
                ->code($m[2]);
        } else {
            $s->departure()
                ->name($node);
        }

        // arrival
        $node = $this->re("#{$this->opt($this->t('DROP OFF:'), true)}\s*(.+?)(?:\n\n|$)#s", $table);
        $point = $this->trimPoint($node);

        if (preg_match("#^(.*)\s*\(([A-Z]{3})\)#", $point, $m)) {
            if (!empty($m[1])) {
                $s->arrival()
                    ->name($m[1]);
            }
            $s->arrival()
                ->code($m[2]);
        } else {
            $s->arrival()
                ->name($point);
        }

        // search arrival time otherwise noDate (logic from pdf... no examples)
        if (preg_match("#{$this->opt($this->t('Flight/Train Time:'), true)}\s*(?<time>.+)$#", $node, $m)) {
            $s->arrival()
                ->date(strtotime($m['time'], $date));
        } else {
            $node = preg_replace("#\s+#", ' ',
                $this->re("#{$this->opt($this->t('Details:'), true)}\s+(.+)#s", $tripBlock));

            if (preg_match("#FOR A (?<time>\d+:\d+(?:\s*[ap]m)?) WHEELS UP\s*\*\*FINAL#i", $node, $m)
                || preg_match("#TO .+ AIRPORT BY (?<time>\d+:\d+(?:\s*[ap]m)?) \*\*FINAL\*\*#i", $node, $m)
            ) {
                $s->arrival()
                    ->date(strtotime($m['time'], $date));
            } elseif (!empty($node) && preg_match_all("#\b(?<time>\d+:\d+(?:\s*[ap]m)?)\b#", $node,
                    $v) && count($v['time']) == 1
            ) {
                $s->arrival()
                    ->date(strtotime(array_shift($v['time']), $date));
            } else {
                $node = $this->re("#{$this->opt($this->t('TOTAL HOURS:'))}[ ]+(\d[\d\.]+)#", $node);

                if (!empty($node)) {
                    $s->arrival()
                        ->date(strtotime("+ " . (float) $node . ' hour', $s->getDepDate()));
                } else {
                    $s->arrival()->noDate();
                }
            }
        }
    }

    private function getTdText($field, $root = null): ?string
    {
        return $this->http->FindSingleNode("descendant::text()[{$this->eq($field)}]/ancestor::td[1]", $root, true,
            "#{$this->opt($field)}\s*(.*)$#");
    }

    private function trimPoint($str): string
    {
        $str = $this->re("#(.+?)(?:{$this->opt($this->t('Flight/Train #:'), true)}|$)#s", $str);
        $str = preg_replace("#\s+#", ' ', $str);

        if (preg_match("#^(.*\([A-Z]{3}\))\s*(?:[A-Z\d][A-Z]|[A-Z][A-Z\d]) \- .+#", $str, $m)) {
            $str = $m[1];
        }

        return $str;
    }

    private function normalizeDate($date)
    {
        $in = [
            //05/20/19 MONDAY
            '#^(\d+)\/(\d+)\/(\d{2})\s+[\w\-]+$#u',
        ];
        $out = [
            '20$3-$1-$2',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body)
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangText($body)
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["PICKUP:"], $words["Customer:"])) {
                if ($this->stripos($body, $words["PICKUP:"]) && $this->stripos($body, $words["Customer:"])) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLangHttp()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["PICKUP:"], $words["Customer:"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words["PICKUP:"])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words["Customer:"])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace("€", "EUR", $node);
        $node = str_replace("$", "USD", $node);
        $node = str_replace("£", "GBP", $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function stripos($haystack, $arrayNeedle): bool
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function findCutSection($input, $searchStart, $searchFinish)
    {
        $inputResult = null;

        if ($searchStart) {
            $left = mb_strstr($input, $searchStart);
        } else {
            $left = $input;
        }

        if (is_array($searchFinish)) {
            $i = 0;

            while (empty($inputResult)) {
                if (isset($searchFinish[$i])) {
                    $inputResult = mb_strstr($left, $searchFinish[$i], true);
                } else {
                    return false;
                }
                $i++;
            }
        } else {
            if (!empty($searchFinish)) {
                $inputResult = mb_strstr($left, $searchFinish, true);
            } else {
                $inputResult = $left;
            }
        }

        return mb_substr($inputResult, mb_strlen($searchStart));
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

    private function opt($field, $addSpace = false, $quote = true)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        if ($quote == true) {
            $fields = array_map(function ($s) {
                return preg_quote($s, '#');
            }, $field);
        } else {
            $fields = $field;
        }

        if ($addSpace == true) {
            $fields = array_map([$this, 'addSpace'], $fields);
        }

        return '(?:' . implode('|', $fields) . ')';
    }

    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
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

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function colsPos($table, $correct = 5)
    {
        $pos = [];
        $rows = explode("\n", $table);

        foreach ($rows as $row) {
            $pos = array_merge($pos, $this->rowColsPos($row));
        }
        $pos = array_unique($pos);
        sort($pos);
        $pos = array_merge([], $pos);

        foreach ($pos as $i => $p) {
            if (isset($pos[$i], $pos[$i - 1])) {
                if ($pos[$i] - $pos[$i - 1] < $correct) {
                    unset($pos[$i]);
                }
            }
        }
        sort($pos);
        $pos = array_merge([], $pos);

        return $pos;
    }
}
