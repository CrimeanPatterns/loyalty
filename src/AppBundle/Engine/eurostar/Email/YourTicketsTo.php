<?php

namespace AwardWallet\Engine\eurostar\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
// use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourTicketsTo extends \TAccountChecker
{
    public $mailFiles = "eurostar/it-563110509.eml, eurostar/it-563205017.eml, eurostar/it-567137050.eml";

    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            '(local time)'  => '(local time)',
            'Seat'          => ['Seat', 'SEAT'],
            'TICKET NUMBER' => ['TICKET NUMBER'],
            'confNumber'    => ['Booking Reference:', 'Booking reference:'],
        ],
    ];

    private $detectFrom = "noreply@eurostar.com";
    private $detectSubject = [
        // en
        'Your Eurostar tickets to ',
    ];
    private $detectBody = [
        'en' => [
            'We look forward to seeing you soon on board our trains',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return preg_match("/[@.]eurostar\.com$/", $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->detectFrom) === false
            && strpos($headers["subject"], 'Eurostar') === false
        ) {
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
        // detect Provider
        if (
            $this->http->XPath->query("//a[{$this->contains(['.eurostar.com'], '@href')}]")->length === 0
            && $this->http->XPath->query("//*[{$this->contains(['Eurostar International Limited'])}]")->length === 0
        ) {
            return false;
        }

        // detect Format
        foreach ($this->detectBody as $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->date = strtotime($parser->getDate());

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
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (
                !empty($dict["Seat"]) && $this->http->XPath->query("//*[{$this->contains($dict['Seat'])}]")->length > 0
                && (!empty($dict["(local time)"]) && $this->http->XPath->query("//*[{$this->contains($dict['(local time)'])}]")->length > 0
                    || !empty($dict["TICKET NUMBER"]) && $this->http->XPath->query("//*[{$this->contains($dict['TICKET NUMBER'])}]")->length > 0)
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function parseEmailHtml(Email $email): void
    {
        $xpathTime = 'starts-with(translate(normalize-space(),"0123456789","dddddddddd"),"dd:dd")';
        $t = $email->add()->train();

        // General
        $travellers = array_unique($this->http->FindNodes("//text()[{$this->eq($this->t('Seat'))}]/ancestor::*[not({$this->contains($this->t('(local time)'))}) and not(.//text()[{$xpathTime}])][last()]/descendant::text()[normalize-space()][1]"));

        $t->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/'))
            ->travellers($travellers, true)
        ;

        $xpath = "//text()[{$this->eq($this->t('(local time)'))}]/ancestor::*[.//text()[{$this->eq($this->t('Seat'))}]][1]";
        $nodes = $this->http->XPath->query($xpath);
        //$this->logger->debug('$xpath = '.print_r( $xpath,true));

        if ($nodes->length == 0) {
            $xpath = "//text()[{$xpathTime}]/ancestor::*[.//text()[{$this->eq($this->t('Seat'))}]][1]";
            // $this->logger->debug('$xpath = '.print_r( $xpath,true));
            $nodes = $this->http->XPath->query($xpath);
        }

        foreach ($nodes as $root) {
            $s = $t->addSegment();

            $node = '';

            if ($this->http->XPath->query("descendant::text()[normalize-space()][not(ancestor::div/ancestor::td[not(.//td)])]", $root)->length == 0) {
                $node = implode("\n", $this->http->FindNodes("descendant::div[not(.//div)][normalize-space()]", $root));
            } else {
                $node = implode("\n", $this->http->FindNodes("descendant::td[not(.//td)][normalize-space()]", $root));
            }
            $re = "/^.+\n(?<date>.+)\n(?<dName>.+)\n(?<aName>.+)\n(?<dTime>\d{1,2}:\d{2}\d{0,5})(?:\n{$this->opt($this->t('(local time)'))})?\n(?<aTime>\d{1,2}:\d{2}\d{0,5})\n/";

            if (preg_match($re, $node, $m)) {
                $date = $this->normalizeDate($m['date']);

                // Departure
                $s->departure()
                    ->name($m['dName'] . ', Europe')
                    ->date($date ? strtotime($m['dTime'], $date) : null)
                ;

                // Arrival
                $s->arrival()
                    ->name($m['aName'] . ', Europe')
                    ->date($date ? strtotime($m['aTime'], $date) : null)
                ;
            }

            $re2 = "/\n{$this->opt($this->t('Coach'))}\n(?<car>\w+)\n{$this->opt($this->t('Seat'))}\n(?<seat>\w+)\n{$this->opt($this->t('TRAIN'))}\n(?<number>\d+)\n{$this->opt($this->t('TICKET NUMBER'))}\n(?<ticket>\d+)\s*/i";

            if (preg_match($re2, $node, $m)) {
                $s->extra()
                    ->car($m['car'])
                    ->number($m['number']);

                $pax = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Seat'))}]/following::text()[normalize-space()][1][{$this->eq($m['seat'])}]/ancestor::table[10]/descendant::text()[{$this->contains($travellers)}]");

                if (!empty($pax)) {
                    $s->extra()
                        ->seat($m['seat'], false, false, $pax);

                    $t->addTicketNumber($m['ticket'], false, $pax);
                } else {
                    $s->extra()
                        ->seat($m['seat']);

                    $t->addTicketNumber($m['ticket'], false);
                }
            }

            $segments = $t->getSegments();

            foreach ($segments as $segment) {
                if ($segment->getId() !== $s->getId()) {
                    if (serialize(array_diff_key($segment->toArray(),
                            ['seats' => []])) === serialize(array_diff_key($s->toArray(), ['seats' => []]))) {
                        if (!empty($s->getSeats())) {
                            $segment->extra()->seats(array_unique(array_merge($segment->getSeats(),
                                $s->getSeats())));
                        }
                        $t->removeSegment($s);

                        break;
                    }
                }
            }
        }
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
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date)
    {
        // $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date) || empty($this->date)) {
            return null;
        }

        $year = date("Y", $this->date);

        $in = [
            // Sun, 18 Jun, 09:00 AM
            "/^(\w+)\,\s*(\d+)\s*([[:alpha:]]+)\s*$/iu",
        ];
        $out = [
            "$1, $2 $3 $year",
        ];
        $date = preg_replace($in, $out, $date);

        // if (preg_match("#(.*\d+\s+)([^\d\s]+)(\s+\d{4}.*)#", $str, $m)) {
        //     if ($en = MonthTranslate::translate($m[2], $this->lang)) {
        //         $str = $m[1] . $en . $m[3];
        //     } elseif ($en = MonthTranslate::translate($m[2], 'de')) {
        //         $str = $m[1] . $en . $m[3];
        //     }
        // }
        // $this->logger->debug('$str = '.print_r( $str,true));

        if (preg_match("/^(?<week>\w+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $str = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $date)) {
            $str = strtotime($date);
        } else {
            $str = null;
        }

        return $str;
    }

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }
}
