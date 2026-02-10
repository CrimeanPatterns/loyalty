<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class LastMinuteInformation extends \TAccountChecker
{
    public $mailFiles = "delta/it-10106309.eml, delta/it-10106349.eml, delta/it-107044656.eml, delta/it-11850733.eml, delta/it-11854565.eml, delta/it-12234302.eml, delta/it-2228094.eml, delta/it-29156414.eml, delta/it-38246828.eml, delta/it-40996621.eml, delta/it-48699058.eml, delta/it-56505409.eml, delta/it-5886576.eml, delta/it-65480572.eml, delta/it-664773217.eml, delta/it-6802462.eml, delta/it-7020345.eml, delta/it-71166319.eml, delta/it-717001956.eml, delta/it-7172803.eml, delta/it-7172844.eml, delta/it-8888216.eml";

    public $lang = "en";
    public $subject;
    private $reFrom = ".delta.com";
    private $reSubject = [
        "en" => "Last Minute Information about Your Trip",
        "en1"=> "Your Flight Receipt",
        "en2"=> "Delta Reservation Itinerary",
        "en3"=> "SkyMiles Award Trip",
        "Update: Your Flight Schedule Has Changed",
        "Important: Flight Time Change",
        'It\'s Time To Check In For Your Flight',
    ];
    private $reBody = 'delta.com';
    private $reBody2 = [
        "en"  => "HERE'S YOUR NEW ITINERARY",
        "en2" => "Flight Receipt",
        "en3" => "This information is a copy of your itinerary and not a receipt",
        'en4' => 'Thank you for choosing Delta',
        'en5' => 'This email message and its contents are copyrighted and are proprietary products of Delta Air Lines',
        'en6' => "You're all set. If you need to adjust your itinerary, you can make standard changes to your flight",
        'en7' => 'DEPART ARRIVE',
    ];

    private static $dictionary = [
        "en" => [
            'hello'              => ['Hello ', 'Hello,'],
            'Confirmation #:'    => ['Confirmation #: ', 'Confirmation Number'],
            'Ticket Issue Date:' => ['Issue Date:', 'Ticket Issue Date:', 'Delta Confirmation #:'],
            'TOTAL TICKET VALUE' => ['TOTAL TICKET VALUE', 'TICKET AMOUNT'],
        ],
    ];
    private $date = null;

    public function parseHtml(Email $email): void
    {
        $r = $email->add()->flight();

        $confno = $this->re("/^([A-Z\d]{5,})(?:\s*\[.*\]|$)/", $this->nextText($this->t("Confirmation #:")));

        if (empty($confno)) {
            $confno = $this->re("/^(?:Error!\s*Filename\s*not\s*specified.)?([A-Z\d]{5,})(?:\s*\[.*\]|$)/", str_replace(" ", '',
                $this->http->FindSingleNode("(.//text()[normalize-space()='Your Trip Confirmation #:' or normalize-space()='Delta Confirmation #:'])[1]/following::*[normalize-space(.)!=''][1]")));
        }

        if (empty($confno)) {
            $confno = $this->http->FindSingleNode("(//a[contains(@href, 'DL_REC_LOC')])[1]/@href", null, true, "#DL_REC_LOC=(\w+)&#");
        }

        if (empty($confno)) {
            $confno = $this->http->FindSingleNode("(//a[contains(@href, '?confirmationNo=')])[1]/@href", null, true, "#confirmationNo=([A-Z\d]{5,7})&#");
        }

        if (empty($confno)) {
            $confno = $this->http->FindSingleNode("(//a[contains(@href, '?recordLocator=')])[1]/@href", null, true, "#recordLocator=([A-Z\d]{5,7})&#");
        }

        if (empty($confno)) {
            $confno = $this->http->FindSingleNode("(//a[contains(@href, '%3FconfirmationNo%3D')])[1]/@href", null, true, "#%3FconfirmationNo%3D([A-Z\d]{5,7})%26#");
        }

        if (empty($confno)) {
            $confno = $this->http->FindSingleNode("//text()[normalize-space()='Your upcoming flight, Trip Confirmation']/following::*[normalize-space(.)!=''][1]",
                null, true, "/^\s*#([A-Z\d]{5,7})\s*$/");
        }

        if (empty($confno)) {
            $confno = $this->http->FindSingleNode('(//text()[starts-with(normalize-space(), "CONFIRMATION #:")])[1]/ancestor::td[1]', null, true, '/CONFIRMATION\s*#\s*:\s*([A-Z\d]{6})\s*$/');
        }

        $r->general()
            ->confirmation($confno);

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]'; // Mr. Hao-Li Huang

        $travellers = $this->http->FindNodes("//text()[normalize-space()='NAME']/following::text()[normalize-space()!=''][1][not(normalize-space()='FLIGHT')]");

        if (count($travellers) === 0) {
            $travellers = $this->http->FindNodes("//text()[normalize-space()='Name:']/following::text()[normalize-space()!=''][1]");
            $travellers = array_filter($travellers);
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Name:')]/following::text()[normalize-space()][1][starts-with(normalize-space(),'Passenger:')]", null, "/Passenger:\s*({$patterns['travellerName']})[.;\s]*$/u"));
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[starts-with(normalize-space(),'Name:')]", null, "/Name:\s*(.+)/"));
        }

        if (count($travellers) === 0) {
            $link = urldecode($this->http->FindSingleNode("(//a[contains(@href, '&firstName=') and contains(@href, '&lastName=')]/@href)[1]"));

            if (empty($link)) {
                $link = urldecode($this->http->FindSingleNode("(//a[contains(@href, '%26firstName%3D') and contains(@href, '%26lastName%3D')]/@href)[1]"));
            }

            if (preg_match("/firstName=([^&]+)&lastName=([^&]+)/", $link, $m)) {
                $travellers = [urldecode($m[1] . ' ' . $m[2])];
            }
        }

        if (count($travellers) === 0) {
            $tr = $this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/Hello[, ]+({$patterns['travellerName']})[ ]*(?:,|$)/u");

            foreach ($tr as $t) {
                $travellers = array_merge($travellers, array_filter(preg_split("/\s+and\s+/i", $t)));
            }
        }

        if (count($travellers) === 0) {
            $travellersText = $this->re("/\:\s*(.+)\,\s*Your Upgraded Seat/", $this->subject);
            $travellers = explode('And', $travellersText);
        }

        if (count(array_filter($travellers)) > 0) {
            $r->general()->travellers($travellers);
        }

        if (strpos($this->http->Response['body'], "There's been an update to your departure time") !== false) {
            $r->general()->status('updated');
        }

        $resDate = $this->http->FindSingleNode("(//text()[{$this->eq($this->t('Ticket Issue Date:'))}]/following::text()[normalize-space()!=''][1])[1]");

        if (empty($resDate)) {
            $resDate = $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Ticket Issue Date:'))}][1])[1]", null, true, "#:\s*([A-Z\d]+)\s*$#");
        }

        if (!empty($resDate)) {
            if (preg_match("/^(\d+)\s*(\D+?)\s*(\d+)$/", $resDate, $m)) {
                $resDate = strtotime($m[1] . ' ' . $m[2] . ' ' . $m[3]);
            } else {
                $resDate = $this->normalizeDate($resDate);
            }

            if ($resDate) {
                $r->general()->date($resDate);
                $this->date = $resDate;
                $this->logger->info('New relative date: ' . date('r', $this->date));
            }
        }
        $tickets = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Ticket #:'))}]/following::text()[normalize-space()][not(normalize-space() = 'Error! Filename not specified.')][1]", null, '/^(\d{3}[- ]*\d{5,}[- ]*\d{1,2})(?:\s*\[.*\]|$)/'));

        if (count($tickets) > 0) {
            $r->issued()->tickets($tickets, false);
        }

        $accountNumbers = $this->http->FindNodes("//text()[{$this->starts("SkyMiles #")}]/ancestor::td[1]", null, "/\bSkyMiles #\s*([\*\d]{5,})\b/");
        $accountNumbers = array_filter($accountNumbers);

        if (count($accountNumbers) === 0) {
            $accountNumbers = $this->http->FindNodes("//tr[{$this->starts("SkyMiles ® #")}]", null, "/^\s*SkyMiles ® #\s*([\*\d]{5,})[>\s]*$/");
            $accountNumbers = array_filter($accountNumbers);
        }

        if (count($accountNumbers) === 0) {
            $accountNumbers = $this->http->FindNodes("//img[contains(@src, 'delta.com')]/following::text()[starts-with(normalize-space(), '#')]", null, "/[#]\s*(\d+)/");
            $accountNumbers = array_filter($accountNumbers);
        }

        $title = $this->http->FindSingleNode("(//text()[{$this->starts(["SkyMiles #", 'SkyMiles ® #'])}]/ancestor::td[1])[1]", null, true, "/(SkyMiles #|SkyMiles ® #)\s*([\*\d]{5,})\b/");

        if (count($accountNumbers)) {
            foreach ($accountNumbers as $acc) {
                if (preg_match("#^[*]{2,}(\d{3,})$#", $acc, $m)
                    && !preg_match("/^\d+{$m[1]}$/", implode("\n", array_column($r->getAccountNumbers(), 0)))
                ) {
                    $r->program()->account($acc, true, null, $title);
                } elseif (preg_match("#^\d{3,}$#", $acc)) {
                    $name = array_unique(array_filter(
                        $this->http->FindNodes("//text()[{$this->contains($acc)}][preceding::text()[normalize-space()][2][{$this->eq('Passenger Info')}]]/preceding::text()[normalize-space()][1][{$this->starts('Name:')}]",
                            null, "/^\s*Name:\s*([[:alpha:] \-'']+)\s*$/")));
                    $r->program()->account($acc, false, (count($name) === 1) ? $name[0] : null, $title);
                }
            }
        }

        $ruleThTd = "name() = 'th' or name() = 'td'";
        $sumFare = 0.0;
        $sumTotal = 0.0;
        $currency = '';
        $spent = 0.0;
        $baseFares = $this->http->FindNodes("//*[{$ruleThTd}][contains(normalize-space(),'Base Fare')]/following-sibling::*[normalize-space()][{$ruleThTd}][1]/descendant::text()[normalize-space()][1]", null, '/\d[,.\'\d ]*/');

        foreach ($baseFares as $baseFare) {
            $sumFare += PriceHelper::parse($baseFare);
        }
        $baseFareCurrency = $this->currency($this->http->FindSingleNode("(//*[{$ruleThTd}][contains(., 'Base Fare')]/following-sibling::*[normalize-space(.)!=''][{$ruleThTd}][1]/descendant::text()[normalize-space(.)!=''][1])[1]"));

        $baseXPATH = "//*[{$ruleThTd}][contains(., 'Base Fare')]/ancestor::tr[1][./following-sibling::tr[1][count(./*[{$ruleThTd}])=2]]";
        $fees = [];
        // check added sums
        if ($this->http->XPath->query($baseXPATH . "/following-sibling::tr[not(contains(.,'Equivalent Fare'))][1][count(./*[{$ruleThTd}])=2]")->length > 0) {
            // FE: delta/it-10106309.eml
            $checkCnt1 = $this->http->XPath->query($baseXPATH . "/following-sibling::tr[count(./*[{$ruleThTd}])=1][1]/preceding-sibling::tr[not(contains(.,'Equivalent Fare') or contains(.,'Base Fare'))]")->length;
            $checkCnt2 = $this->http->XPath->query($baseXPATH . "/preceding-sibling::tr[not(contains(.,'Equivalent Fare') or contains(.,'Base Fare'))]")->length;
            $cnt = $checkCnt1 - $checkCnt2;
            $addedXpath = $baseXPATH . "/following-sibling::tr[not(contains(.,'Equivalent Fare'))][position()<={$cnt}]";
            $addedRoots = $this->http->XPath->query($addedXpath);

            foreach ($addedRoots as $addedRoot) {
                $fname = $this->http->FindSingleNode("td[normalize-space()!=''][1]", $addedRoot);
                $ftotal = $this->http->FindSingleNode("td[normalize-space()!=''][2][not(following-sibling::td[normalize-space()!=''])]",
                    $addedRoot);

                if (!empty($fname) && $ftotal !== null && preg_match('/(?<amount>\d[,.\'\d ]*)\s*(?<currency>[A-Z]{3}\b)/', $ftotal, $m)) {
                    if (preg_match('/^\.\d{1,2}$/', $m['amount'])) {
                        $m['amount'] = '0' . $m['amount'];
                    }
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                    $fees[] = [
                        'name'  => $fname,
                        'total' => PriceHelper::parse($m['amount'], $currencyCode),
                    ];

                    if (!empty($currency) && $currency !== $m['currency']) {
                        $differentCurrency = true;
                    } else {
                        $currency = $m['currency'];
                    }
                } else {
                    $fees = [];
                    $this->logger->debug("something went wrong with fees (added)");

                    break;
                }
            }
        }
        $totals = $this->http->FindNodes("//*[{$ruleThTd}][" . $this->contains($this->t('TOTAL TICKET VALUE')) . "]/following-sibling::*[normalize-space(.)!=''][{$ruleThTd}][1]");

        foreach ($totals as $total) {
            if (preg_match('/(?<miles>\d[,.\'\d ]*) Miles\s+and\s+\D+(?<amount>\d[,.\'\d ]*)\s+(?<currency>[A-Z]{3}\b)/', $total, $m)) {
                // ??
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $sumTotal += PriceHelper::parse($m['amount'], $currencyCode);

                if (!empty($currency) && $currency !== $m['currency']) {
                    $differentCurrency = true;
                } else {
                    $currency = $m['currency'];
                }
                $spent += PriceHelper::parse($m['miles']);
            } elseif (preg_match('/(?<amount>\d[,.\'\d ]*)\s*(?<currency>[A-Z]{3}\b)/', $total, $m)) {
                // $6,452.60 USD
                $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                $sumTotal += PriceHelper::parse($m['amount'], $currencyCode);

                if (!empty($currency) && $currency !== $m[2]) {
                    $differentCurrency = true;
                } else {
                    $currency = $m[2];
                }
            }
        }

        $taxBlockXpath = "//tr[normalize-space(.) = 'Taxes, Fees and Charges']";
        $tbNodes = $this->http->XPath->query($taxBlockXpath);

        foreach ($tbNodes as $tbRoot) {
            $taxXpath = "following-sibling::tr[normalize-space()]";
            $tNodes = $this->http->XPath->query($taxXpath, $tbRoot);

            foreach ($tNodes as $tRoot) {
                if (!empty($this->http->FindSingleNode("*[normalize-space()][1][" . $this->contains($this->t('TOTAL TICKET VALUE')) . "]", $tRoot))) {
                    break;
                }
                $fname = $this->http->FindSingleNode("td[normalize-space()!=''][1]", $tRoot);
                $ftotal = $this->http->FindSingleNode("td[normalize-space()!=''][2][not(following-sibling::td[normalize-space()!=''])]", $tRoot);

                if (!empty($fname) && $ftotal !== null && preg_match('/(?<amount>\d[,.\'\d ]*)\s*(?<currency>[A-Z]{3}\b)/', $ftotal, $m)) {
                    if (preg_match('/^\.\d{1,2}$/', $m['amount'])) {
                        $m['amount'] = '0' . $m['amount'];
                    }
                    $currencyCode = preg_match('/^[A-Z]{3}$/', $m['currency']) ? $m['currency'] : null;
                    $fees[] = [
                        'name'  => $fname,
                        'total' => PriceHelper::parse($m['amount'], $currencyCode),
                    ];

                    if (!empty($currency) && $currency !== $m['currency']) {
                        $differentCurrency = true;
                    } else {
                        $currency = $m['currency'];
                    }
                } else {
                    $fees = [];
                    $this->logger->debug("something went wrong with fees");

                    break;
                }
            }
        }

        if (!isset($differentCurrency)) {
            if (!empty($sumFare)
                && (empty($baseFareCurrency) || (!empty($currency) && $baseFareCurrency === $currency))) {
                $r->price()
                    ->cost($sumFare);
            }

            if (!empty($sumTotal)) {
                $r->price()
                    ->total($sumTotal);
            }

            if (!empty($currency)) {
                $r->price()
                    ->currency($currency);
            }

            if (!empty($spent)) {
                $r->price()
                    ->spentAwards($spent . ' Miles');
            }

            if (!empty($fees)) {
                foreach ($fees as $value) {
                    $r->price()
                        ->fee($value['name'], $value['total']);
                }
            }
        }

        if ($this->http->XPath->query($query = "//text()[contains(normalize-space(),'Your Trip Confirmation #:')][last()]")->length > 0) {
            // FE: it-48699058.eml
            $preXpath = $query . '/following::';
        } else {
            $preXpath = "//";
        }

        $xpath = $preXpath . "text()[" . $this->eq("FLIGHT/DATE") . "]/ancestor::tr[2]/following-sibling::tr[.//td[3] and " . $this->contains(":") . "]//td[3][not(contains(@style, 'text-decoration: line-through'))]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = $preXpath . "text()[" . $this->eq("DEPART") . "]/ancestor::tr[contains(.,'ARRIVE')][1]/following-sibling::tr[.//td[3] and " . $this->contains(":") . "and (not(contains(normalize-space(), 'Upgrade Requested')))]//td[3]/..";
            $nodes = $this->http->XPath->query($xpath);
        }
        $lastDate = null;
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd')";

        $this->logger->debug('XPATH' . $xpath);

        foreach ($nodes as $root) {
            // TODO: UPDATE date time... it-11854565.eml, it-40996621.eml
            if ($this->http->XPath->query(".//*[self::td or self::span][contains(@style,'text-decoration:line-through') and string-length(normalize-space(.)) > 10]", $root)->length > 1) {
                continue;
            }

            if ($this->http->XPath->query(".//text()[normalize-space()][not(ancestor::s)]", $root)->length === 0) {
                continue;
            }

            $date = $this->normalizeDate($this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)!=''][2]", $root, true, '/^(?:([A-Z][a-z]{2}[,]\s[A-Z]\S+\s+\d+)|([A-Z][a-z]{2}[,]\s\d{2}[A-Z]{3}))/'));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("./preceding::text()[normalize-space()='DEPART']/ancestor::tr[1]/descendant::td[1]", $root));
            }

            if (empty($date)) {
                $newDate = $this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[.//*[self::td or self::th][3]][1]/*[self::td or self::th][1]", $root));

                if (!empty($newDate)) {
                    $date = $newDate;
                } else {
                    $date = $lastDate;
                }
            }

            $s = $r->addSegment();

            if ($this->http->XPath->query("td[1]/descendant::tr[not(.//tr) and normalize-space()]", $root)->length > 1) {
                // it-10106309.eml
                $cell1 = implode("\n", $this->http->FindNodes("td[1]/descendant::text()[normalize-space()]", $root));
            } else {
                // it-48699058.eml
                $cell1Html = $this->http->FindHTMLByXpath('td[1]', null, $root);
                $cell1 = $this->htmlToText($cell1Html);
            }

            if (preg_match("/^\s*(?<nameFull>.*?)[ ]+(?<number>\d+)(?<asterisk>\*?)[ ]*(?:\D*\n|\D*$)/", $cell1, $matches) // DELTA 4523*
                || preg_match("/^\s*(?<nameFull>.*?)[ ]+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)(?<asterisk>\*?)[ ]*(?:\D*\n|\D*$)/", $cell1, $matches) // DELTA DL4523*
                || preg_match("/^\s*(?<number>\d+)(?<asterisk>\*?)(?<operator>\s*[oO]perated by .+)?\s*$/", $cell1, $matches) // 999 Operated by Delta Air Lines, Inc
            ) {
                if (!empty($matches['name']) || !empty($matches['nameFull'])) {
                    $s->airline()
                        ->name(empty($matches['name']) ? $matches['nameFull'] : $matches['name']);
                } else {
                    $s->airline()
                        ->noName();
                }
                $s->airline()
                    ->number($matches['number']);

                $operatedText = '';

                if (!empty($matches['asterisk'])) {
                    $flight = '*Flight ';
                    $notes = $this->http->FindNodes("//text()[starts-with(normalize-space(),'{$flight}')]");
                    $flight = $matches['number'];
                    $notes = array_filter($notes, function ($s) use ($flight) {return preg_match("/\*Flight\s+" . $flight . "\s+/", $s); });

                    if (count($notes) === 1) {
                        $operatedText = array_shift($notes);
                    }
                }

                if (empty($operatedText) && !empty($matches['nameFull'])) {
                    $operatedText = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'*" . $matches['nameFull'] . ' ' . $matches['number'] . "')]");
                }

                if (empty($operatedText) && !empty($matches['operator'])) {
                    $operatedText = $matches['operator'];
                }

                if (!empty($operatedText)) {
                    if (preg_match("/[oO]perated by (.+) DBA .+/", $operatedText, $m)) {
                        //*Flight 5984 Operated by REPUBLIC AIRLINE DBA DELTA CONNECTION
                        $s->airline()->operator($m[1]);
                    } elseif (preg_match("/[oO]perated by .+ As ([A-Z\d]{2}) Flt (\d+)/", $operatedText, $m)) {
                        //*Flight 8739 Operated by CZECH AIRLINES As OK Flt 761
                        $s->airline()
                            ->carrierName($m[1])
                            ->carrierNumber($m[2]);
                    } elseif (preg_match("/[oO]perated by\s+(.+)/", $operatedText, $m)) {
                        //*Flight 5414 is operated by ExpressJet Airlines
                        $s->airline()->operator($m[1]);
                    }
                }
            }

            $nameDep = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][1]", $root);
            $nameArr = $this->http->FindSingleNode("*[normalize-space()][3]/descendant::text()[normalize-space()][1]", $root);
            $codeDep = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root, true, '/^[A-Z]{3}$/');
            $codeArr = $this->http->FindSingleNode("*[normalize-space()][3]/descendant::text()[normalize-space()][2]", $root, true, '/^[A-Z]{3}$/');

            if ($codeDep) {
                $s->departure()->code($codeDep)->name($nameDep);
            } elseif (preg_match('/^[A-Z]{3}$/', $nameDep)) {
                $s->departure()->code($nameDep);
            } else {
                $s->departure()->name($nameDep)->noCode();
            }

            if ($codeArr) {
                $s->arrival()->code($codeArr)->name($nameArr);
            } elseif (preg_match('/^[A-Z]{3}$/', $nameArr)) {
                $s->arrival()->code($nameArr);
            } else {
                $s->arrival()->name($nameArr)->noCode();
            }

            $reTime = '(\d{1,2}:\d{2}\s*(?:[AaPp][Mm]+|N)?)';
            $timeDep = $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[normalize-space()][2]", $root, false, "/{$reTime}/")
                ?? $this->http->FindSingleNode("*[normalize-space()][2]/descendant::text()[$ruleTime][1]", $root, false, "/{$reTime}/")
                ?? $this->http->FindSingleNode("*[normalize-space()][2]/descendant::td[2]", $root, false, "/{$reTime}/")
            ;

            if (empty($timeDep) || strlen($timeDep) < 3) {
                $timeDep = $this->http->FindSingleNode("*[normalize-space()][2]", $root, true, "/{$reTime}/");
            }
            $timeDep = $this->normalizeTime($timeDep);

            $s->departure()->date(strtotime($timeDep, $date));

            $node = $this->http->FindSingleNode(
                "./td[3]/descendant::text()[normalize-space(.)!=''][3][starts-with(normalize-space(.),'**')]/ancestor::td[1]",
                $root,
                false,
                "/\*{2}\s*(.+)/");

            if (!empty($node) && preg_match("/\d/", $node)) {
                $date = $this->normalizeDate($node);
            }
            $timeArr = $this->http->FindSingleNode("*[normalize-space()][3]/descendant::text()[normalize-space()][2]", $root, false, "/{$reTime}/")
                ?? $this->http->FindSingleNode("*[normalize-space()][3]/descendant::text()[$ruleTime][1]", $root, false, "/{$reTime}/")
                ?? $this->http->FindSingleNode("*[normalize-space()][3]/descendant::td[2]", $root, false, "/{$reTime}/")
            ;
            $timeArr = $this->normalizeTime($timeArr);

            if (!empty($timeArr)) {
                $s->arrival()->date(strtotime($timeArr, $date));
            } else {
                $s->arrival()->noDate();
            }
            $lastDate = $date;

            $cabin = $this->http->FindSingleNode("//text()[{$this->eq("SEAT")}]/ancestor::tr[2]/following-sibling::tr/descendant-or-self::tr[count(.//tr)=0 and count(.//td)=3]/td[3]/../td[1][{$this->ends(' ' . $s->getFlightNumber())} or {$this->ends(' ' . $s->getFlightNumber() . '*')}]/../td[2]");

            if (empty($cabin)) {
                /*
                    DELTA 4789*
                    Delta Comfort+® (S)
                */
                if (preg_match("/^\s*\S.*\n+[ ]*(?<cabin>\S.*?)[ ]*\([^)(]*\)[ ]*(?:\n|$)/", $cell1, $m)) {
                    $s->extra()->cabin($m['cabin']);
                }

                if (preg_match("/^\s*\S.*\n+.*\([ ]*(?<code>[A-Z]{1,2})[ ]*\)[ ]*(?:\n|$)/", $cell1, $m)) {
                    $s->extra()->bookingCode($m['code']);
                }
            } else {
                $s->extra()
                    ->cabin($cabin);
            }
            $seats = array_filter(explode(", ", $this->http->FindSingleNode("//text()[{$this->eq("SEAT")}]/ancestor::tr[2]/following-sibling::tr//td[3]/../td[1][{$this->ends(' ' . $s->getFlightNumber())} or {$this->ends(' ' . $s->getFlightNumber() . '*')}]/../td[3]")));

            if (empty($seats)) {
                $seats = array_filter(explode(", ", $this->http->FindSingleNode("//text()[{$this->eq("SEAT")}]/ancestor::tr[{$this->starts('FLIGHT')}][1]/following-sibling::tr[./td[2]][{$this->ends(' ' . $s->getFlightNumber())} or {$this->ends(' ' . $s->getFlightNumber() . '*')}]/td[normalize-space()][2]")));
            }

            if (empty($seats)) {
                // it-65480572.eml
                $seats = array_filter(explode(", ", join(", ",
                    $this->http->FindNodes("//text()[{$this->eq("SEAT")}]/ancestor::tr[{$this->starts('FLIGHT')}][1]
                    /following-sibling::tr[./td[2]][td[1][{$this->ends(' ' . $s->getFlightNumber())} or {$this->ends(' ' . $s->getFlightNumber() . '*')}]]/td[normalize-space()][2]"))));
            }

            if (empty($seats)) {
                $seats = explode(', ', $this->http->FindSingleNode("//text()[{$this->eq($this->t('SEAT'))}]/ancestor::tr[1]/following::tr/*[normalize-space()][1][not(.//tr) and {$this->starts([$s->getAirlineName() . $s->getFlightNumber(), $s->getAirlineName() . ' ' . $s->getFlightNumber()])}]/following-sibling::*[normalize-space()][last()]"));
            }
            $seats = array_filter(array_map("trim", $seats), function ($s) {return preg_match("/^\d+[A-Z]$/", $s); });

            if (count($seats) === 0) {
                $flight = $s->getFlightNumber();
                $strLen = strlen($flight);

                if (!empty($flight) && !empty($strLen)) {
                    $seats = $this->http->FindNodes("//text()[normalize-space()='NAME']/following::text()[contains(.,' {$flight}') and substring(normalize-space(), string-length(normalize-space()) - {$strLen})=' {$flight}']/ancestor::td[1]/following-sibling::td[1]");
                }
                $seats = array_filter($seats, function ($s) {
                    return preg_match("/^\d+[A-Z]$/", $s);
                });
            }

            if (count($seats) > 0) {
                $s->extra()->seats(array_unique($seats));
            }

            //check
            $segments = $r->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (($segment->getFlightNumber() === $s->getFlightNumber())
                        && ($segment->getAirlineName() === $s->getAirlineName())
                        && ($segment->getDepDate() === $s->getDepDate())
                        && ($segment->getArrDate() === $s->getArrDate())
                    ) {
                        $r->removeSegment($s);

                        break;
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], 'SkyMiles') === false
            && strpos($headers['subject'], 'Delta') === false
        ) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers['subject'], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false
                || $this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getDate());

        if ($this->http->XPath->query("//a[contains(@href,'/www.boxbe.com/')]")->length > 0) {
            $this->changeBody($parser);
        }

        // TODO: allocate parsing formats with the ScheduleChange2015 parser
//        if ($this->http->FindSingleNode("//text()[{$this->starts('There\'s been an update')}]")) {
//            $this->logger->debug('Go to parse by ScheduleChange2015');
//            return $email;
//        }
        $this->http->SetEmailBody(str_replace('[e.delta.com]', '', $this->http->Response['body']));
        $NBSP = chr(194) . chr(160);
        $this->http->SetBody(str_replace($NBSP, ' ', html_entity_decode($this->http->Response['body'])));
        $this->date = strtotime($parser->getDate());
        $this->logger->info('Relative date: ' . date('r', $this->date));

        $this->subject = $parser->getSubject();

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false
                || $this->http->XPath->query("//*[{$this->contains($re)}]")->length > 0
            ) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }
        $a = explode('\\', __CLASS__);
        $class = $a[count($a) - 1];
        $email->setType($class . ucfirst($this->lang));
        $this->parseHtml($email);

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($instr)
    {
        $this->logger->debug($instr);

        $year = date('Y', $this->date);

        $in = [
            '/^(?<wDay>[-[:alpha:]]+)[,\s]+(?<day>\d{1,2})[-.\s]*(?<month>[[:alpha:]]+)[-.\s]*(?<year>\d{4})$/u', // Mon, 24OCT2022
            '/^(?<wDay>[-[:alpha:]]+)[,\s]+(?<day>\d{1,2})[-.\s]*(?<month>[[:alpha:]]+)[-.\s]*(?<year>\d{2})$/u', // Mon, 24OCT22
            '/^(?<wDay>[-[:alpha:]]+)[,\s]+(?<day>\d{1,2})[-.\s]*(?<month>[[:alpha:]]+)$/u', // Fri, 17NOV
            '/^(?<wDay>[-[:alpha:]]+)[,\s]+(?<month>[[:alpha:]]+)[-.\s]*(?<day>\d{1,2})$/u', // Fri, March 30
        ];
        $out = [
            '$1 $2 $3 $4',
            '$1 $2 $3 20$4',
            "$1 $2 $3 $year",
            "$1 $3 $2 $year",
        ];
        $date = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+) (?<date>\w+ .+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);

            if ($weeknum !== null && $date === null) { // Mon 22 OCT 2022
                $date = strtotime($m['date']);
            }
        } elseif (preg_match("#\b\d{4}\b#", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function normalizeTime(?string $s): string
    {
        if (preg_match('/^(?:12)?\s*noon$/i', $s)) {
            return '12:00';
        }
        $s = str_replace('N', 'pm', $s); // 12:00N    ->    12:00PM

        if (preg_match('/^((\d{1,2})[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', $s, $m) && (int) $m[2] > 12) {
            $s = $m[1]; // 21:51 PM    ->    21:51
        }
        $s = preg_replace('/^(0{1,2}[ ]*:[ ]*\d{2})\s*[AaPp][Mm]$/', '$1', $s); // 00:25 AM    ->    00:25

        return $s;
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null): ?string
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
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

    private function ends($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }
        $rules = [];

        foreach ($field as $f) {
            $len = mb_strlen($f);

            if ($len > 0) {
                $rule = "substring(normalize-space(),string-length(normalize-space())+1-{$len},{$len})='{$f}'";
                $rules[] = $rule;
            }
        }

        if (count($rules) == 0) {
            return 'false()';
        }

        return implode(' or ', $rules);
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

    private function changeBody($parser)
    {
        $texts = implode("\n", $parser->getRawBody());

        if (substr_count($texts, 'Content-Type: text/') > 1) {
            $texts = preg_replace("#------=_NextPart.*#", "\n", $texts);
            $texts = preg_replace("#\n--[\S]+(=|--)\n#", "\n", $texts);
            $text = '';
            $posBegin1 = stripos($texts, "Content-Type: text/");
            $i = 0;

            while ($posBegin1 !== false && $i < 50) {
                $posBegin = stripos($texts, "\n\n", $posBegin1) + 2;
                $str = substr($texts, $posBegin1, $posBegin - $posBegin1);

                $posEnd = stripos($texts, "Content-Type: ", $posBegin);
                $block = substr($texts, $posBegin, $posEnd - $posBegin);
                $posEnd = strripos($block, "\n\n");
                $block = substr($texts, $posBegin, $posEnd);

                if (preg_match("#: base64#is", $str)) {
                    $block = trim($block);
                    $block = htmlspecialchars_decode(base64_decode($block));

                    if (($blockBegin = stripos($block, '<blockquote')) !== false) {
                        $blockEnd = strripos($block, '</blockquote>', $blockBegin) + strlen('</blockquote>');
                        $block = substr($block, $blockBegin, $blockEnd - $blockBegin);
                    }
                    $text .= $block;
                } elseif (preg_match("#quoted-printable#s", $str)) {
                    $text .= quoted_printable_decode($block);
                } else {
                    $text .= htmlspecialchars_decode($block);
                }
                $posBegin1 = stripos($texts, "Content-Type: text/", $posBegin);
                $i++;
            }
            $this->http->SetEmailBody($text, true);
        }
    }
}
