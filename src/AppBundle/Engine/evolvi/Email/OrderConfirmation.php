<?php

namespace AwardWallet\Engine\evolvi\Email;

//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class OrderConfirmation extends \TAccountChecker
{
    public $mailFiles = "evolvi/it-62418829.eml, evolvi/it-641085267.eml";

    public static $detectProvider = [
        'flightcentre' => [
            'from'    => '@flightcentre.',
            'subject' => [
                'Flight Centre Duty Travel Order Confirmation ToD Collection Ref',
            ],
            'body' => ['Flight Centre Duty Travel', 'FlightCentreDutyTravel@railtix'],
        ],
        'fcmtravel' => [
            //            'from' => '',
            'subject' => [
                'FCm Travel Solutions Order Confirmation Order Ref',
            ],
            'body' => ['FCm Travel Solutions', 'FCmTravelSolutions@railtix.'],
        ],
        'ctmanagement' => [
            //            'from' => '',
            'subject' => [
                'CTM (North) Ltd Order Confirmation ToD Collection Ref',
                'CTM (North) Ltd Order Confirmation Kiosk Collection Ref',
                'CTM (North) Ltd Order Confirmation Order Ref',
            ],
            'body' => ['CTM (North) Ltd', 'CTMNorthLtd@railtix.'],
        ],
        'ctraveller' => [
            'from'    => '@corptraveller',
            'subject' => [
                'CORPORATE TRAVELLER Order Confirmation ToD Collection Ref',
            ],
            'body' => ['CORPORATE TRAVELLER', 'CORPORATETRAVELLER@railtix.'],
        ],
        'hays' => [
            //            'from' => '',
            'subject' => [
                'Hays Travel Order Confirmation Order Ref',
            ],
            'body' => ['Hays Travel'],
        ],
        'awc' => [
            //            'from' => '',
            'subject' => [
                'Avanti Business Order Confirmation Order Ref',
                'Avanti Business Order Confirmation ToD Collection Ref',
            ],
            'body' => ['@avantiwestcoast.co.uk', 'Avanti Business'],
        ],
        'evolvi' => [
            //            'from' => '',
            'subject' => [
                'Capita Travel and Events Order Confirmation ToD Collection Ref',
                'Amber Road Travel Order Confirmation Order Ref',
                'Omega World Travel Order Confirmation ToD Collection Ref', // Omega World Travel
                'Bookit Travel Order Confirmation ToD Collection Ref',
                'Business Travel Order Confirmation Order Ref',
            ],
            'body' => [
                'Capita Travel and Events', 'CapitaTravelandEvents@railtix.', 'amberrd.evolvi.co.uk_eTicket',
                'please contact Omega World Travel',
            ],
        ],
    ];
    public static $dict = [
        'en' => [
            'confirmation' => ['Order Reference:', 'Order Item Reference:', 'Ticket Collection Reference:'],
        ],
    ];

    private $defaultSubject = [
        'en' => 'Order Confirmation ToD Collection Ref',
        'Order Confirmation Kiosk Collection Ref',
        'Order Confirmation Order Ref',
    ];

    private $detectBody = [
        'en' => [
            'To collect your tickets insert any credit or debit card',
            'The following order has been confirmed',
            'The following eTicket order has been confirmed',
        ],
    ];

    private $providerCode;

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseTrain($email);

        $body = $this->http->Response['body'];

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detects) {
                if (isset($detects['body'])) {
                    foreach ($detects['body'] as $dCompany) {
                        if (stripos($body, $dCompany) !== false) {
                            $this->providerCode = $code;

                            break 2;
                        }
                    }
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

        if (stripos($body, '@railtix.') !== false) {
            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if (strpos($body, $dBody) !== false) {
                        return true;
                    }
                }
            }
        }
        $foundCompany = false;

        foreach (self::$detectProvider as $detects) {
            if (isset($detects['body'])) {
                foreach ($detects['body'] as $dCompany) {
                    if (stripos($body, $dCompany) !== false) {
                        $foundCompany = true;

                        break;
                    }
                }
            }

            if ($foundCompany === false) {
                continue;
            }

            foreach ($this->detectBody as $detectBody) {
                foreach ($detectBody as $dBody) {
                    if ($this->http->XPath->query("//*[contains(normalize-space(.),'{$dBody}')]")->length > 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $detects) {
            if (isset($detects['subject'])) {
                foreach ($detects['subject'] as $dSubject) {
                    if (stripos($headers["subject"], $dSubject) !== false) {
                        $this->providerCode = $code;

                        return true;
                    }
                }
            }
        }

        if (stripos($headers["from"], '@railtix.') !== false) {
            foreach ($this->defaultSubject as $dSubject) {
                if (stripos($headers["subject"], $dSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        if (stripos($from, '@railtix.') !== false) {
            return true;
        }

        foreach (self::$detectProvider as $detects) {
            if (isset($detects['from']) && stripos($from, $detects['from']) !== false) {
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

    public static function getEmailProviders()
    {
        return array_filter(array_keys(self::$detectProvider));
    }

    private function parseTrain(Email $email)
    {
        // Travel Agency
        $email->obtainTravelAgency();

        if (is_array($this->t('confirmation'))) {
            foreach ($this->t('confirmation') as $confName) {
                $conf = $this->http->FindSingleNode("//text()[" . $this->eq($confName) . "]/following::text()[normalize-space()][1]");

                if (!empty($conf)) {
                    $email->ota()
                        ->confirmation($conf, trim($confName, ':'));
                }
            }
        } elseif (is_string($this->t('confirmation'))) {
            $email->ota()
                ->confirmation($this->http->FindSingleNode("//text()[" . $this->eq($this->t('confirmation')) . "]/following::text()[normalize-space()][1]"),
                    trim($this->t('confirmation'), ':'));
        }

        $t = $email->add()->train();

        // General
        $t->general()
            ->noConfirmation()
            ->travellers(preg_replace('/^\s*(Mr|Mrs|Dr|Ms|Miss|Mstr) /i', '',
                $this->http->FindNodes("//text()[" . $this->eq($this->t('Traveller Details')) . "]/following::text()[normalize-space()][1][" . $this->eq($this->t('Name')) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space()]/td[1]")), true)
        ;

        $date = strtotime($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Order Date:")) . "]/following::text()[normalize-space()][1]"));

        if (!empty($date)) {
            $t->general()
                ->date($date);
        }

        $total = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Order Item Cost:")) . "]/following::text()[normalize-space()][1]");

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#", $total, $m)) {
            $t->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Segments
        $xpath = "//tr[./td[1][" . $this->eq($this->t('Departs')) . "] and ./td[2][" . $this->eq($this->t('Arrives')) . "]]/following-sibling::tr[normalize-space() and count(td) > 3]";
//        $this->logger->debug('$xpath = '.print_r( $xpath,true));
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug("Segments not found");

            return false;
        }

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[" . $this->starts($this->t("Travel on")) . "][1]", $root, true, "#" . $this->opt($this->t("Travel on")) . "\s*(.+)#"));

            if (empty($date)) {
                $date = $this->normalizeDate($this->http->FindSingleNode("preceding::text()[" . $this->eq($this->t("Travel on")) . "][1]/following::text()[normalize-space()][1]", $root));
            }

            $regexp = "#^\s*(?<time>\d{1,2}:\d{1,2}(?: ?[ap]m)?)\s+(?<name>.+)#ui";

            // Departure
            $depart = $this->http->FindSingleNode("td[1]", $root);

            if (preg_match($regexp, $depart, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->geoTip('uk')
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : null)
                ;
            }
            // Arrival
            $arrive = $this->http->FindSingleNode("td[2]", $root);

            if (preg_match($regexp, $arrive, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->geoTip('uk')
                    ->date((!empty($date)) ? strtotime($m['time'], $date) : null)
                ;
            }

            // Extra
            $s->extra()
                ->noNumber()
            ;

            $seats = array_filter(explode(',', $this->http->FindSingleNode("td[4]", $root, true, "#\(\w+:([A-Z\d, ]+)\)#")));

            if (!empty($seats)) {
                $s->extra()->seats($seats);
            }
        }

        return $email;
    }

    private function normalizeDate($date)
    {
        $in = [
            //            '#^\s*(\d{1,2})/(\d{2})/(\d{4})\s*$#u',//21/03/2019
        ];
        $out = [
            //            '$1.$2.$3',
        ];
        $date = preg_replace($in, $out, $date);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)){
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $date = str_replace($m[1], $en, $date);
//        }
        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function eq($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return $text . '="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) { return 'contains(' . $text . ',"' . $s . '")'; }, $field)) . ')';
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function res($re, $str, $c = 1)
    {
        preg_match_all($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return [];
    }

    private function amount($price)
    {
        $price = trim($price);

        if (preg_match("#^([\d,. ]+)[.,](\d{2})$#", $price, $m)) {
            $price = str_replace([' ', ',', '.'], '', $m[1]) . '.' . $m[2];
        }

        if (is_numeric($price)) {
            return (float) $price;
        }

        return null;
    }

    private function currency($s)
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

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
