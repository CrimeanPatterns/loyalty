<?php

namespace AwardWallet\Engine\disneyresort\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class VacationConfirmation extends \TAccountChecker
{
    public $mailFiles = "disneyresort/it-122156176.eml, disneyresort/it-58920876.eml, disneyresort/it-59032238.eml, disneyresort/it-196410283.eml";

    public $detectFrom = [
        'disneyonline.com', 'disneydestinations.com',
    ];
    public $detectSubject = [
        'Walt Disney World Vacation Confirmation!', 'Disneyland Resort Confirmation',
    ];
    public $detectBody = [
        'en' => ['Thank You. Your Order is Confirmed', 'Your Agent Has Modified Your Reservation.'],
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            "Hotel Confirmation Number:" => ["Hotel Confirmation Number:", "Package Confirmation Number:", "Hotel Confirmation Number :"],
            //            "Order Date:" => "",
            "CANCELLATION POLICY:" => ["CANCELLATION POLICY:", "Walt Disney World room only Cancellation Policy:", "Cancellation and Refunds"],
            //            "(Age" => "",
            //            "Check In" => "",
            //            "Start Check-In" => "",
            //            "Rate per Night" => "",
            "Reservation Price" => ["Reservation Price", "Package Price"],
            //            "Tax" => "",
            "noTax"                        => ["Reservation Price", "Total Order Price", "Package Price", 'Total Room Price'],
            "totalPrice"                   => ["Total Order Price", "Total Package Price", 'Total Room Price'],
            "Tickets Confirmation Number:" => ["Tickets Confirmation Number:", "Ticket Confirmation Number:"],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (!empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Tickets Confirmation Number:")) . "])[1]"))
            && $this->http->XPath->query("//text()[" . $this->contains($this->t("Confirmation Number:")) . "]")->length == 1
        ) {
            $email->setIsJunk(true);
        } elseif (
            !empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Tickets Confirmation Number:")) . "])[1]"))
            && !empty($this->http->FindSingleNode("(//text()[" . $this->starts($this->t("Theme Park Confirmation Number:")) . "])[1]"))
            && $this->http->XPath->query("//text()[" . $this->contains($this->t("Confirmation Number:")) . "][not(" . $this->starts($this->t("Tickets Confirmation Number:")) . ") and not(" . $this->starts($this->t("Theme Park Confirmation Number:")) . ")]")->length == 0
        ) {
            $email->setIsJunk(true);
        } else {
            $this->parseHotel($email);
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".disney.") or contains(@href,"disneydestinations.com/")] | //*[contains(normalize-space(),"© Disney and its related entities")] | //*[contains(normalize-space(),"DISNEYLAND® RESORT")]')->length > 0
        && $this->http->XPath->query("//text()[starts-with(normalize-space(), 'Check In')]/ancestor::tr[1][contains(normalize-space(), 'Check Out')]")->length > 0) {
            return $this->assignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["subject"])) {
            return false;
        }

        if ($this->striposAll($headers["subject"], $this->detectSubject) !== false) {
            return true;
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return $this->striposAll($from, $this->detectFrom) !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseHotel(Email $email): void
    {
        $count = count($this->http->FindNodes("//text()[" . $this->eq($this->t("Check In")) . "]"));

        if ($count > 1) {
            $xpath = "//text()[" . $this->eq($this->t("Check In")) . "]/ancestor::*[" . $this->contains($this->t("totalPrice")) . " and " . $this->contains($this->t('CANCELLATION POLICY:')) . "][1]";
            $roots = $this->http->XPath->query($xpath);

            if ($count !== $roots->length) {
                $this->logger->debug("Check XPath");
            }
        } else {
            $roots = [null];
        }

        foreach ($roots as $root) {
            $h = $email->add()->hotel();

            // General
            $confirmation = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Hotel Confirmation Number:'))}]/following::text()[normalize-space()][1]", $root, true, '/^[-A-Z\d]{5,}$/');
            $confirmationTitle = $this->http->FindSingleNode("descendant::text()[ {$this->eq($this->t('Hotel Confirmation Number:'))} and following::text()[normalize-space()] ]", $root, true, '/^(.+?)[\s:：]*$/u');

            if (!$confirmation && preg_match("/^({$this->preg_implode($this->t('Hotel Confirmation Number:'))})[:\s]*([-A-Z\d]{5,})$/m", implode("\n", $this->http->FindNodes("descendant::text()[{$this->starts($this->t('Hotel Confirmation Number:'))}]", $root)), $m)) {
                $confirmation = $m[2];
                $confirmationTitle = rtrim($m[1], ': ');
            }

            $h->general()
                ->confirmation($confirmation, $confirmationTitle)
                ->travellers(array_unique($this->http->FindNodes(".//text()[" . $this->contains($this->t("(Age")) . "]/ancestor::tr[1][.//img]",
                    $root, "#^\s*(?:(?:Mstr|Miss|Mr|Mrs|Ms)\.? )?(\S.+?)\s*\(#")), true);
            $cancellation = $this->http->FindSingleNode(".//h4[{$this->eq($this->t('CANCELLATION POLICY:'))}]/following::ul[1]", $root);

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('CANCELLATION POLICY:'))}]/ancestor::*[position() < 4][self::span][1]", $root, true, "/{$this->preg_implode($this->t("CANCELLATION POLICY:"))}\s*(.+)/");
            }
            $h->general()
                ->cancellation($cancellation, true, true);

            $orderDate = $this->http->FindSingleNode("descendant::text()[{$this->starts($this->t('Order Date:'))}][1]", $root, false, '/:\s*(.*\d.*)/')
                ?? $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Order Date:'))}]/following::text()[normalize-space()][1]", $root, false, '/^.*\d.*$/');
            $date = $this->normalizeDate($orderDate);

            if (!empty($date)) {
                $h->general()->date($date);
            }

            // Hotel
            $h->hotel()
                ->name($this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Check In")) . "]/preceding::text()[normalize-space()][1]", $root))
                ->noAddress();

            // Booked
            $xpathDates = "descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("Check In"))}] and *[normalize-space()][2][{$this->eq($this->t("Check Out"))}] ]/following-sibling::tr[normalize-space()][1]";
            $checkIn = $this->http->FindSingleNode($xpathDates . "/*[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t("Check In"))}]/following-sibling::tr[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::td[descendant::text()[normalize-space()][1][{$this->eq($this->t("Check In"))}]][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]", $root)
            ;

            $checkOut = $this->http->FindSingleNode($xpathDates . "/*[normalize-space()][2]", $root)
                ?? $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t("Check Out"))}]/following-sibling::tr[normalize-space()][1]", $root)
                ?? $this->http->FindSingleNode("descendant::td[descendant::text()[normalize-space()][1][{$this->eq($this->t("Check Out"))}]][count(.//text()[normalize-space()]) = 2]/descendant::text()[normalize-space()][2]", $root)
            ;
            $adults = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("(Age"))}][1]/ancestor::tr[1][.//img]/preceding::tr[not(.//tr)][1]", $root, true, "/^\s*(\d+)\s*adult/i");
            $children = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t("(Age"))}][1]/ancestor::tr[1][.//img]/preceding::tr[not(.//tr)][1]", $root, true, "/\D+(\d+)\s*child/i");
            $h->booked()
                ->checkIn($this->normalizeDate($checkIn))
                ->checkOut($this->normalizeDate($checkOut))
                ->guests($adults, false, true)
                ->kids($children, false, true);

            $type = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Start Check-In")) . "]/following::text()[normalize-space()][1]/ancestor::td[1]", $root);

            if (empty($type)) {
                $type = $this->http->FindSingleNode(".//td[{$this->eq($this->t("Rate per Night"))}]/ancestor::tr[1]/preceding::tr[normalize-space()][1]");
            }

            if (empty($type)) {
                $type = $this->http->FindSingleNode("//text()[contains(normalize-space(), ' Adult')]/ancestor::tr[1]/preceding::text()[normalize-space()][1]");
            }

            if (!empty($type)) {
                $r = $h->addRoom();
                $r->setType($type);

                $rates = array_values(array_unique(array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Rate per Night")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()]/td[2]"))));

                if (!empty($rates)) {
                    if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $rates[0], $m)) {
                        $currencySign = $m['curr'];
                        $currencyBefore = true;
                    } elseif (preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $rates[0], $m)) {
                        $currencySign = $m['curr'];
                        $currencyBefore = false;
                    } else {
                        $currencySign = $currencyBefore = null;
                    }
                    $rateRe = "#^\s*" . ($currencyBefore ? '(?<curr>[^\d\s]{1,5})\s*' : '') . "(?<amount>\d[\d\., ]*)\s*" . ($currencyBefore ? '' : '(?<curr>[^\d\s]{1,5})\s*') . "$#";
                    $rate = [];

                    foreach ($rates as $rateText) {
                        if (preg_match($rateRe, $rateText, $m)) {
                            $currencyCode = preg_match('/^[A-Z]{3}$/', $currencySign) ? $currencySign : null;
                            $rate[] = PriceHelper::parse($m['amount'], $currencyCode);
                        } else {
                            $rate = [];

                            break;
                        }
                    }
                    $rate = array_unique($rate);

                    if (count($rate) == 1) {
                        $r->setRate($currencySign . $rate[0]);
                    } elseif (count($rate) > 1) {
                        $r->setRate($currencySign . min($rate) . ' - ' . $currencySign . max($rate));
                    }
                }
            }

            $cost = $this->http->FindSingleNode(".//td[" . $this->eq($this->t("Reservation Price")) . "]/following-sibling::td[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$/", $cost, $m)
                || preg_match("/^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$/", $cost, $m)
            ) {
                $currency = $this->currency($m['curr']);
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $h->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $taxNodes = $this->http->XPath->query("descendant::text()[{$this->eq($this->t("totalPrice"))}]/ancestor::table[1]/descendant::tr/td[2]/ancestor::tr[1][not({$this->contains($this->t("noTax"))})]", $root);

            foreach ($taxNodes as $tRoot) {
                $feeName = $this->http->FindSingleNode("./td[1]", $tRoot);
                $feeSum = $this->http->FindSingleNode("./td[2]", $tRoot, true, "/\D*([\d\.\,]+)/");

                if (!empty($feeName) && !empty($feeSum)) {
                    $h->price()->fee($feeName, PriceHelper::parse($feeSum));
                }
            }

            $total = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t("totalPrice"))}]/following-sibling::td[normalize-space()][1]", $root);

            if (preg_match("/^\s*(?<currency>[^\-\d)(]{1,5})\s*(?<amount>\d[,.\'\d ]*?)\s*(?<currencyCode>[A-Z]{3})?$/", $total, $m)
                || preg_match("/^\s*(?<amount>\d[,.\'\d ]*?)\s*(?<currency>[^\-\d)(]{1,5})\s*$/", $total, $m)
            ) {
                // $1,314.00    |    $2,776.32 USD
                $currency = empty($m['currencyCode']) ? $this->currency($m['currency']) : $m['currencyCode'];
                $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
                $h->price()->currency($currency)->total(PriceHelper::parse($m['amount'], $currencyCode));
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
        foreach ($this->detectBody as $lang => $dBody) {
            if (($this->striposAll($this->http->Response['body'], $dBody) !== false)) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date = '.print_r( $str,true));
        $in = [
            // November 16, 2019; Thu, Dec 19, 2019
            "#^\s*(?:\w+[, ]+)?(\w+)\s+(\d+)[, ]+(\d{4})\s*$#u",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$date = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s): ?string
    {
        if ($code = $this->re("#^\s*([A-Z]{3})\s*$#", $s)) {
            return $code;
        }
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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

    private function preg_implode($field): string
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '/'); }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
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
}
