<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class HotelFreeCancellationEnding extends \TAccountChecker
{
    public $mailFiles = "expedia/it-226371210.eml";

    public static $headers = [
        //        'orbitz' => [
        //            'from' => ['orbitz.com'],
        //            'subj' => [
        //                'en' => 'Orbitz Hotel Cancellation Confirmation',
        //                'Orbitz Hotel Room Cancellation Notice',
        //                'es' => 'Orbitz Confirmación de cancelación del hotel',
        //            ],
        //        ],
        //        'lastminute' => [
        //            'from' => ['email.lastminute.com.au'],
        //            'subj' => [
        //                'en' => 'lastminute.com.au Hotel Cancellation Confirmation',
        //                'lastminute.com.au Hotel Room Cancellation Notice',
        //            ],
        //        ],
        //        'chase' => [
        //            'from' => ['chasetravelbyexpedia@'],
        //            'subj' => [],
        //        ],
        //        'travelocity' => [
        //            'from' => ['e.travelocity.com'],
        //            'subj' => [
        //                'en' => 'Travelocity Hotel Cancellation Confirmation',
        //                'Travelocity Hotel Room Cancellation Notice',
        //            ],
        //        ],
        //        'ebookers' => [
        //            'from' => [
        //                'mailer.ebookers.',
        //            ],
        //            'subj' => [
        //                'en' => 'ebookers Hotel Cancellation Confirmation',
        //                'ebookers Hotel Room Cancellation Notice',
        //                ' ebookers Hotel-Stornierungsbestätigung ',
        //            ],
        //        ],
        //        'cheaptickets' => [
        //            'from' => ['cheaptickets.com'],
        //            'subj' => [
        //                'en' => 'CheapTickets Hotel Cancellation Confirmation',
        //                'CheapTickets Hotel Room Cancellation Notice',
        //            ],
        //        ],
        //        'hotels' => [
        //            'from' => ['@support-hotels.com'],
        //            'subj' => [
        //                'en' => 'Hotels.com Hotel Cancellation Confirmation',
        //                'Hotels.com Hotel Room Cancellation Notice',
        //                // ja
        //                'Hotels.com 宿泊施設のキャンセルの確認',
        //                // ko
        //                'Hotels.com 호텔 취소 확인',
        //                // fr
        //                'Hotels.com Confirmation d\'annulation d\'hôtel -',
        //                // no
        //                'Hotels.com Avbestillingsbekreftelse for hotell -',
        //            ],
        //        ],
        //        'hotwire' => [
        //            'from' => ['noreply@Hotwire.com'],
        //            'subj' => [
        //            ],
        //        ],
        'expedia' => [ // always last
            'from' => ['expedia@eg.expedia.com', 'expediamail.com', 'expedia@ca.expediamail.com'],
            'subj' => [
                'still qualifies for free cancellation',
            ],
        ],
    ];

    public $lang = 'en';
    public static $dictionary = [
        'en' => [
            "Traveler Details" => ["Traveler Details", "Traveller details"],
        ],
    ];

    protected $code = null;

    protected $bodies = [
        //        'lastminute' => [
        //            '//img[contains(@alt,"lastminute.com")]',
        //            '//a[contains(.,"lastminute.com")]/parent::*[contains(.,"Collected by")]',
        //        ],
        //        'chase' => [
        //            '//img[normalize-space(@itemprop)="image" and (contains(@src,".chase.com/") or contains(@src,"travel.chase.com"))]',
        //            'Chase Travel',
        //            'Chase Ultimate',
        //        ],
        //        'cheaptickets' => [
        //            '//img[contains(@src,"cheaptickets.com")]',
        //            'cheaptickets.com',
        //            'Call CheapTickets customer',
        //        ],
        //        'ebookers' => [
        //            '//img[contains(@alt,"ebookers.com")]',
        //            'Collected by ebookers',
        //            'Maksun veloittaa ebookers',
        //        ],
        //        'hotels' => [
        //            '//img[contains(@src,"Hotels.com")]',
        //            "Hotels.com",
        //        ],
        //        'hotwire' => [
        //            '//img[contains(@alt,"Hotwire.com")]',
        //            'Or call Hotwire at',
        //        ],
        //        'mrjet' => [
        //            '//img[contains(@src,"MrJet.se")]',
        //            'MrJet.se',
        //        ],
        //        'orbitz' => [
        //            '//img[contains(@alt,"Orbitz.com")]',
        //            'This Orbitz Itinerary was sent from',
        //            'Call Orbitz customer care',
        //        ],
        //        'rbcbank' => [
        //            '//img[contains(@src,"rbcrewards.com")]',
        //            'rbcrewards.com',
        //        ],
        //        'travelocity' => [
        //            '//img[contains(@src,"travelocity.com")]',
        //            'travelocity.com',
        //            'Collected by Travelocity',
        //        ],
        'expedia' => [
            '//img[contains(@alt,"expedia.com")]',
            '//img[contains(@src,"expedia.com")]',
            'expedia.com',
        ],
    ];

    protected $detectBody = [
        'en' => ['free cancellation is ending soon'],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$headers);
    }

    public function detectEmailFromProvider($from)
    {
        foreach (self::$headers as $arr) {
            foreach ($arr['from'] as $f) {
                if (stripos($from, $f) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            if ($byFrom !== true) {
                continue;
            }

            if (($byFrom) && $this->code === null) {
                $this->code = $code;
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if ($this->http->XPath->query("//a[" . $this->contains('expedia.', '@href') . "]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $dBody) {
            if ($this->http->XPath->query("//node()[" . $this->contains($dBody) . "]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

//        if ($code = $this->getProvider($parser)) {
//            $email->setProviderCode($code);
//        }

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'time'  => '\d{1,2}(?:[:：]\d{2})?(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?',
            'phone' => '[+(\d][-+. \d)(]{5,}[\d)]',
        ];

        // Travel Agency
        $email->obtainTravelAgency();
        $email->ota()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t("Itinerary:"))}]/following::text()[normalize-space()][1]", null, true, "/^[\dA-Z]{5,}$/"));

        $h = $email->add()->hotel();

        // General
        $h->general()
            ->noConfirmation()
            ->traveller($this->http->FindSingleNode("//text()[{$this->eq($this->t("Traveler Details"))}]/following::text()[normalize-space()][1]"))
        ;

        // Hotel
        $h->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t("Traveler Details"))}]/following::text()[normalize-space()][2][following::text()[normalize-space()][2][{$this->eq($this->t("Check-In:"))}]]"))
            ->address($this->http->FindSingleNode("//text()[{$this->eq($this->t("Traveler Details"))}]/following::text()[normalize-space()][3][following::text()[normalize-space()][1][{$this->eq($this->t("Check-In:"))}]]"))
        ;

        // Booked
        $h->booked()
            ->checkIn($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-In:"))}]/following::text()[normalize-space()][1]")))
            ->checkOut($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->eq($this->t("Check-Out:"))}]/following::text()[normalize-space()][1]")))
        ;

        // Rooms
        $roomType = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Accommodation details"))}]/following::text()[normalize-space()][1]");

        if ($roomType) {
            $h->addRoom()
                ->setType($roomType);
        }

        $cancellation = implode(' ', $this->http->FindNodes("//p[{$this->contains($this->t("cancellationPhrases"))}]/descendant::text()[normalize-space()]"));

        if (preg_match("/You (?i)can still cancell? this booking without a fee until\s+(.{3,}?\d{4}[,\s]+{$patterns['time']})/u", $cancellation, $m)) {
            $h->booked()->deadline2($m[1]);
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->t("nonRefundable"))}]")->length > 0) {
            $h->booked()->nonRefundable();
        }
    }

    private function getProvider(PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            if ($this->code === 'expedia') {
                return null;
            } else {
                return $this->code;
            }
        }

        foreach ($this->bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    }
                }

                return $code;
            }
        }

        return null;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "#^\s*([[:alpha:]]+)\s+(\d{1,2}),\s+(\d{4})\s*$#u", // Nov 24, 2022
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([[:alpha:]]+)\s+(?:\d{4}|%Y%)#u", $str, $m) or preg_match("#\d+\s+([[:alpha:]]+)$#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function currency($s, $location = '')
    {
//        $this->logger->debug('currency = '.print_r( $s,true));
        if (preg_match("#¤#", $s)) {
            $s = $this->defaultCurrency();
        }
        $sym = [
            '$C'        => 'CAD',
            '€'         => 'EUR',
            'R$'        => 'BRL',
            'C$'        => 'CAD',
            'CA$'       => 'CAD',
            'SG$'       => 'SGD',
            'HK$'       => 'HKD',
            'AU$'       => 'AUD',
            'A$'        => 'AUD',
            '$'         => 'USD',
            'US$'       => 'USD',
            'US dollars'=> 'USD',
            '£'         => 'GBP',
            //			'kr'=>'NOK', NOK or SEK
            'RM'             => 'MYR',
            '฿'              => 'THB',
            'MXN$'           => 'MXN',
            'MX$'            => 'MXN',
            'Euro'           => 'EUR',
            'Euros'          => 'EUR',
            'Real brasileiro'=> 'BRL',
            '円'              => 'JPY',
        ];

        if ($code = $this->re("#(?:^|\s|\d)([A-Z]{3})(?:$|\s|\d)#", $s)) {
            return $code;
        }
        $s = preg_replace("#([,.\d :]+)#", '', $s);

        foreach ($sym as $f=> $r) {
            if ($s == $f) {
                return $r;
            }
        }

        if ($s == '¥' && stripos($location, 'Japan') !== false) {
            return 'JPY';
        }

        if ($s == 'kr' && $this->lang == 'sv') {
            return 'SEK';
        }

        if ($s == 'kr' && $this->lang == 'no') {
            return 'NOK';
        }

        return null;
    }

    private function defaultCurrency()
    {
        $totalText = implode(" ", $this->http->FindNodes("//text()[" . $this->contains($this->t("Total")) . "][1]/ancestor::table[2]//text()[normalize-space(.)]"));
        $reCurrency = (array) $this->t("#defaultCurrency#");

        foreach ($reCurrency as $re) {
            if (preg_match($re, $totalText, $m) && !empty($m[1])) {
                return $this->currency($m[1]);
            }
        }

        return null;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
