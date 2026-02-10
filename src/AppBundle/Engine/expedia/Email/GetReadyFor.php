<?php

namespace AwardWallet\Engine\expedia\Email;

// TODO: delete what not use
use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class GetReadyFor extends \TAccountChecker
{
    public $mailFiles = "expedia/it-111470884.eml, expedia/it-111854512.eml, expedia/it-112035231.eml, expedia/it-112385369.eml, expedia/it-112543753.eml";

    private $detectFrom = ["@expediamail.com"];
    private $detectSubject = [
        // en
        'Here’s details about your upcoming trip', // Get ready for Anchorage: Here’s details about your upcoming trip
        // es
        'aquí los detalles sobre tu próximo viaje', //  Alístate para Tepoztlán: aquí los detalles sobre tu próximo viaje
    ];

    private $detectBody = [
        'en' => [
            ['Get ready for your trip', 'Your booked items'],
        ],
        'es' => [
            ['Prepara todo', 'Tus servicios reservados'],
        ],
    ];

    public $date;

    public $lang;
    public static $dictionary = [
        'en' => [
            // Rental
//            'Pickup' => '',
//            'Dropoff' => '',
            // Flight
//            'traveler(s)' => '',
            // Hotel
//            'Check-in' => '',
//            'Check-out' => '',
//            'room(s)' => '',
        ],
        'es' => [
            // Rental
            'Pickup' => 'Fecha de entrega el',
            'Dropoff' => 'Fecha de devolución el',
            // Flight
            'traveler(s)' => 'pasajero(s)',
            // Hotel
            'Check-in' => 'Check-in:',
            'Check-out' => 'Check-out:',
            'room(s)' => 'habitación(es)',
        ],
    ];

    private $detectRentalProviders = [
        'alamo' => [
            'Alamo Rent A Car',
        ],
        'hertz' => [
            'Hertz',
        ],
        'perfectdrive' => [
            'Budget',
        ],
        'dollar' => [
            'Dollar Rent A Car',
        ],
        'thrifty' => [
            'Thrifty Car Rental',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'expediamail.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->containsText($headers['from'], $this->detectFrom) !== true) {
            return false;
        }

        foreach ($this->detectSubject as $dSubject) {
            if (stripos($headers["subject"], $dSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (
            $this->http->XPath->query("//a[{$this->contains(['.expediamail.com'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query("//*[{$this->contains($dBody[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($dBody[1])}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            foreach ($detectBody as $dBody) {
                if ($this->http->XPath->query("//*[{$this->contains($dBody[0])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($dBody[1])}]")->length > 0) {
                    $this->lang = $lang;
                    break 2;
                }
            }
        }

        $this->date = $parser->getDate();
        $dateText = $this->http->FindSingleNode("(//a[contains(@href, '=DATE') or contains(@href, '-3DDATE')]/@href)[1]", null, true, "/(?:=|-3D)DATE(\d{8})\./");
        if (preg_match("/^(20\d{2})(\d{2})(\d{2})$/", $dateText, $m)) {
            $this->date = strtotime($m[3].'.'.$m[2].'.'.$m[1]);
        }

        $this->parseEmailHtml($email);

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
        // TODO check count types
        return count(self::$dictionary);
    }

    private function parseEmailHtml(Email $email)
    {
        $email->obtainTravelAgency();
        $conf = $this->http->FindSingleNode("(//text()[".$this->starts($this->t("Expedia itinerary:"))."])[1]", null, true, "/: (\d{5,})\s*$/");
        if (!empty($conf)) {
                $email->ota()
                ->confirmation($conf);
        }

        // Flight
//        $flightXpath = "//text()[".$this->contains($this->t("traveler(s)"))."]/ancestor::table[1]";
        $flightXpath = "//img[contains(@src, 'icon__lob_flights')]/ancestor::*[self::tr or self::th][not(normalize-space())]/following-sibling::*[normalize-space()][1]";
//        https://a.travel-assets.com/travel-assets-manager/uds-icons-color/icon__lob_flights.png
        $nodes = $this->http->XPath->query($flightXpath);
        foreach ($nodes as $root) {

            $f = $email->add()->flight();

            // General
            $f->general()
                ->noConfirmation();

            $text = implode("\n", $this->http->FindNodes(".//tr[not(.//tr)][normalize-space()]", $root));
//            $this->logger->debug('$text = '.print_r( $text,true));

            if (preg_match("/^\s*(?<dName>.+?)\s*-\s*\((?<dCode>[A-Z]{3})\)\s+(?<aName>.+?)\s*-\s*\((?<aCode>[A-Z]{3})\)\s+(?<date>[\d\/]{6,}) - (?<al>.+?)(?:\s+\d+\s*".$this->opt($this->t("traveler(s)"))."|)\s*(".$this->opt($this->t("Expedia itinerary:")).".*)?\s*$/", $text, $m)) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($m['al'])
                    ->noNumber();

                $s->departure()
                    ->name($m['dName'])
                    ->code($m['dCode'])
                    ->noDate()
                    ->day(strtotime($m['date']))
                ;
                $s->arrival()
                    ->name($m['aName'])
                    ->code($m['aCode'])
                    ->noDate()
                ;
            }
        }

        // Hotel
        $hotelXpath = "//text()[".$this->contains($this->t("Check-in"))."]/ancestor::table[1][".$this->contains($this->t("Check-out"))."]";
        $nodes = $this->http->XPath->query($hotelXpath);
        foreach ($nodes as $root) {

            $h = $email->add()->hotel();

            // General
            $h->general()
                ->noConfirmation();

            $text = implode("\n", $this->http->FindNodes(".//tr[not(.//tr)][normalize-space()]", $root));
//            $this->logger->debug('$text = '.print_r( $text,true));

            if (preg_match("/^\s*(?<name>.+)\n\s*(?<address>.+)\n\s*".$this->opt($this->t("Check-in"))."\s*(?<ciDate>.+)\s+".$this->opt($this->t("Check-out"))."\s*(?<coDate>.+)\s+(?<rooms>\d+)\s*".$this->opt($this->t("room(s)"))."/", $text, $m)) {

                $h->hotel()
                    ->name($m['name'])
                    ->address($m['address']);

                $h->booked()
                    ->checkIn($this->normalizeDate($m['ciDate']))
                    ->checkOut($this->normalizeDate($m['coDate']))
                    ->rooms($m['rooms'])
                ;
            }
        }

        // Rental
        $rentalXpath = "//text()[".$this->starts($this->t("Pickup"))."]/ancestor::table[1][".$this->contains($this->t("Dropoff"))."]";
        $nodes = $this->http->XPath->query($rentalXpath);
        foreach ($nodes as $root) {

            $r = $email->add()->rental();

            // General
            $r->general()
                ->noConfirmation();

            $text = implode("\n", $this->http->FindNodes(".//tr[not(.//tr)][normalize-space()]", $root));
//            $this->logger->debug('$text = '.print_r( $text,true));

            if (preg_match("/".$this->opt($this->t("Pickup"))." (?<pDate>[\d\/]{6,}) [[:alpha:]]+:\s*(?<pLoc>[\s\S]+?)\s*\n\s*".$this->opt($this->t("Dropoff"))." (?<dDate>[\d\/]{6,}) [[:alpha:]]+:\s*(?<dLoc>[\s\S]+)/", $text, $m)) {
                $r->pickup()
                    ->date(strtotime($m['pDate']))
                    ->location($m['pLoc']);
                $r->dropoff()
                    ->date(strtotime($m['dDate']))
                    ->location($m['dLoc']);
            }

            if (preg_match("/^(.+?), *(.+)/", $text, $m)) {
                $provider = $this->getRentalProviderByKeyword(trim($m[1]));
                if (!empty($provider)) {
                    $r->setProviderCode($provider);
                } else {
                    $r->extra()->company(trim($m[1]));
                }
                $r->car()->type(trim($m[2]));
            }


        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }
        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }


    private function eq($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }


    private function starts($field)
    {
        $field = (array)$field;
        if (count($field) == 0) {
            return 'false()';
        }
        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));

        $year = date('Y', $this->date);

        if (empty($date) || empty($year)) {
            return null;
        }

        $date = str_ireplace('- Noon',  '- 12:00 PM', $date);
        $date = preg_replace('/( - \d+)\s*([ap]m)\s*$/i',  '$1:00 $2', $date);
        $in = [
            // Thu, Sep 16 - 3:00 PM
            // Tue Jul 03, 2018 at 1 :43 PM
            '/^\s*([\w\-]+)[.]?,\s*(\w+)\s+(\d+)\s*-\s*(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            '$1, $3 $2 '.$year.', $4',
        ];

        $date = preg_replace($in, $out, $date);
//        $this->logger->debug('date replace = ' . print_r( $date, true));

//        $this->logger->debug('date end = ' . print_r( $date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }


    private function getRentalProviderByKeyword(string $keyword)
    {
        if (!empty($keyword)) {
            foreach ($this->detectRentalProviders as $code => $kws) {
                if (in_array($keyword, $kws)) {
                    return $code;
                } else {
                    foreach ($kws as $kw) {
                        if (strpos($keyword, $kw) !== false) {
                            return $code;
                        }
                    }
                }
            }
        }
        return null;
    }


    private function opt($field, $delimiter = '/')
    {
        $field = (array)$field;
        if (empty($field)) {
            $field = ['false'];
        }
        return '(?:' . implode("|", array_map(function ($s) use($delimiter) {
                return str_replace(' ', '\s+', preg_quote($s, $delimiter));
            }, $field)) . ')';
    }

    private function containsText($text, $needle): bool
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