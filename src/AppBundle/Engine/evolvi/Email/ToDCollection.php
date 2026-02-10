<?php

namespace AwardWallet\Engine\evolvi\Email;

//use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ToDCollection extends \TAccountChecker
{
    public $mailFiles = "evolvi/it-34156104.eml, evolvi/it-34344191.eml, evolvi/it-34737817.eml, evolvi/it-35559906.eml, evolvi/it-44969794.eml, evolvi/it-45122611.eml, evolvi/it-48496876.eml, evolvi/it-49168922.eml, evolvi/it-641078149.eml";

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
                'Avanti Business refund',
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
                'Clarity Travel Management refund',
            ],
            'body' => [
                'Capita Travel and Events', 'CapitaTravelandEvents@railtix.', 'amberrd.evolvi.co.uk_eTicket',
                'please contact Omega World Travel',
            ],
        ],
    ];
    public static $dict = [
        'en' => [],
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
            'The ticket shown below from the following order has been cancelled and refunded',
        ],
    ];

    private $providerCode;

    private $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $body = $this->htmlToText($body);
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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
        $this->parseEmail($email, $parser);

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $body = $this->htmlToText($body);

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
                    if (strpos($body, $dBody) !== false) {
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

    private function parseEmail(Email $email, PlancakeEmailParser $parser): void
    {
        $text = $parser->getPlainBody();
        $text = preg_replace("#^>+ #m", '', $text);

        if (strlen($text) < 1000) {
            $body = $this->http->Response['body'];
            $text = $this->htmlToText($body);
        } else {
            $text = str_replace("\r", '', $text);
        }

        // remove blank lines
        $text = preg_replace("/\n{1,2}^[ \t]*(\d{1,2}[:]+\d{1,2}\D.*)\n{1,2}/m", "\n$1\n", $text);

        // Travel Agency
        $orderRef = $this->re("#Order Ref:[ ]*([\dA-Z]{5,})\s+#", $text);

        if (!empty($orderRef)) {
            $email->ota()->confirmation($orderRef, 'Order Ref');
        }
        $orderItemRef = $this->re("#Order Item Ref:[ ]*([\dA-Z]{5,})\s+#", $text);

        if (!empty($orderItemRef)) {
            $email->ota()->confirmation($orderItemRef, 'Order Item Ref');
        }
        $orderInfo = $this->re("#Order Item Ref:.+\n([\s\S]+?)\n\s*No. of passengers:#", $text);

        $t = $email->add()->train();

        // General
        if (preg_match("/^[ \t]*The following order has been (confirmed)\./m", $text, $m)) {
            $t->general()->status($m[1]);
        }

        $conf = $this->re("#Ticket Collection Reference:[ ]*([\dA-Z]{5,})\s+#", $text);

        if (!empty($conf)) {
            $t->general()
                ->confirmation($conf, 'Ticket Collection Reference');
        } else {
            $t->general()
                ->noConfirmation();
        }

        $date = $this->normalizeDate($this->re("#Order Date:\s*(.+)#", $text));

        if (!empty($date)) {
            $t->general()
                ->date($date);
        }

        if ($this->re("#(order has been cancelled)#", $text)) {
            $t->general()
                ->cancelled()
                ->status('Cancelled')
            ;
        }

        $trCount = $this->re("#No\. of passengers:\s*(\d+)#", $text);

        if (!empty($trCount) && preg_match("#No\. of passengers:.+\n((?:\s*.+\n){" . $trCount . "})#", $text, $m)) {
            $t->general()->travellers(preg_replace("#^\s*(?:(?:MR|MRS|MISS|MS) )?(.+?)\s*\(.+#i", '$1', array_filter(explode("\n", trim($m[1])))));
        }

        // Price
        $total = $this->re("#Order Item Cost:[ ]*(.+)#", $text);

        if (preg_match("#^\s*(?<curr>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#u", $total, $m)
                || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<curr>[^\d\s]{1,5})\s*$#u", $total, $m)) {
            $email->price()
                ->total($this->amount($m['amount']))
                ->currency($this->currency($m['curr']))
            ;
        }

        // Segments
        preg_match_all("#\n[ \t]*(?:OUTBOUND|RETURN)[ \t]*\n[ \t]*([\s\S]*?\n[ \t]*Arr[ \t]*Dep\s+(?:.*\n){2,20}?)\s*(?=\n[ \t]*[^\d\s]+)#", $text, $m);

        if (empty($m)) {
            return;
        }

        foreach ($m[1] as $stext) {
            $date = $this->normalizeDate($this->re("#Date of travel:\s*(.+)#", $stext));

            $stations = $this->res("#^\s*(\d{1,2}:\d{2}\s+.+?\s*(?:\(Reserved:.+?\).*|$))#m", $stext);
            $s = $t->addSegment();
            $s->extra()->noNumber();

            if (preg_match("#^\s*(?:\d+:\d+\s+)?(\d+:\d+)\s+([^\d\s].+?)\s*(?:\(Reserved:.+?\).*|$)#", $stations[0], $m)) {
                // search extended info about departure FE: it-45122611
                $dep = preg_quote($m[2]);

                if (preg_match("#^[ ]*({$dep}.*?)\s+to\s+#m", $orderInfo, $v)) {
                    $m[2] = $v[1];
                }
                $s->departure()
                    ->name($m[2])
                    ->date(!empty($date) ? strtotime($m[1], $date) : false)
                ;
            }

            if (preg_match("#\(Reserved:\s*(.+?)\)\s*#", $stations[0], $m)) {
                $seats = array_filter(preg_split("#\s*,\s*#", $m[1]), function ($v) {return (preg_match("#^[A-Z\d]{1,5}$#", $v)) ? true : false; });

                if (!empty($seats)) {
                    $s->extra()
                        ->seats($seats)
                    ;
                }
            }

            for ($i = 1; $i < count($stations); $i++) {
                if (preg_match("#^\s*(\d+:\d+)\s+(\d+:\d+)?\s*(\D.+?)\s*(?:\(Reserved:.+?\).*|$)#", $stations[$i], $m)) {
                    $arr = preg_quote($m[3]);

                    if (preg_match("#^[ ]*(.+?)\s+to\s+({$arr}.*?)(?:\(|\n)#m", $orderInfo, $v)) {
                        $m[3] = $v[2];
                    }
                    $s->arrival()
                        ->name($m[3])
                        ->date(!empty($date) ? strtotime($m[1], $date) : false)
                    ;

                    if (!empty($m[2])) {
                        if ($m[1] === $m[2] && $i == count($stations) - 1) {
                            continue;
                        }
                        $s = $t->addSegment();
                        $s->extra()->noNumber();
                        $s->departure()
                            ->name($m[3])
                            ->date(!empty($date) ? strtotime($m[2], $date) : false)
                        ;

                        if (preg_match("#\(Reserved:\s*(.+)\)\s*$#", $stations[$i], $mat)) {
                            $seats = array_filter(preg_split("#\s*,\s*#", $mat[1]), function ($v) {return (preg_match("#^[A-Z\d]{1,5}$#", $v)) ? true : false; });

                            if (!empty($seats)) {
                                $s->extra()
                                    ->seats($seats)
                                ;
                            }
                        }
                    }
                }
            }
        }

        if (strpos($text, 'uk.fcm.travel')
            || strpos($text, 'www.nationalrail.co.uk')
            || strpos($text, 'FCmTravelSolutions@railtix.co.uk')
        ) {
            $ukFlag = true;
        }

        foreach ($t->getSegments() as $s) {
            // for google help (kostyl) FE: it-44969794.eml, it-48496876.eml, it-49168922.eml
            if ((
                    (isset($this->providerCode) && ($this->providerCode === 'evolvi'))
                    || (
                        $this->http->XPath->query("//text()[contains(normalize-space(.), 'Travel@railtix.co.uk')]")->length > 0
                        || ($this->http->XPath->query("//text()[contains(normalize-space(.), 'nationalrail.co.uk')]")->length > 0 && $this->providerCode === 'flightcentre')
                    )
                    || isset($ukFlag)
                )
                && $s->getDepName() && $s->getArrName()
                && strpos($s->getDepName(), ',') === false
                && strpos($s->getArrName(), ',') === false
            ) {
                $s->departure()->geoTip('uk');
                $s->arrival()->geoTip('uk');
            }
        }
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
        //			if ($en = MonthTranslate::translate($m[1], $this->lang))
        //				$date = str_replace($m[1], $en, $date);
        //		}
        return strtotime($date);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
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
}
