<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class CheckIn extends \TAccountChecker
{
    public $mailFiles = "delta/it-6333571.eml, delta/it-6387011.eml";
    public $gYear;
    public $segYear;
    public $lang = 'en';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email, $parser);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'DeltaAirLines@e.delta.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && stripos($headers['from'], 'DeltaAirLines@e.delta.com') !== false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href, "/e.delta.com/") and contains(., "Check In")]')->length > 0;
    }

    protected function ParseEmail(Email $email, PlancakeEmailParser $parser)
    {
        $date = $parser->getHeader('date');
        $year = '';

        if (!empty($date)) {
            $this->gYear = date('Y', strtotime($date));

            if ($this->http->XPath->query("//text()[starts-with(normalize-space(), 'Date:')]")->length === 0) {
                $year = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'Delta Air Lines, Inc')]", null, true, "/(\d{4})\s*Delta Air Lines, Inc/");
            } else {
                $year = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Date:')]", null, true, "/(\d{4})/u");
            }
        }
        $f = $email->add()->flight();
        $f->general()
            ->confirmation($this->http->FindSingleNode('//td[contains(normalize-space(.), "Delta Confirmation") and not(.//td)]', null, true, '/Confirmation \#([A-Z\d]{6})$/'));

        $name = $this->http->FindSingleNode('//b[contains(., "Dear")]', null, true, '/Dear (.+),$/');

        if (!empty($name)) {
            $f->general()
                ->traveller($name);
        }

        $account = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'SkyMiles #')]", null, true, "/[#]\s*(\d+)/");

        if (!empty($account)) {
            $f->program()
                ->account($account, false);
        }

        $rows = $this->http->XPath->query('//tr[count(td[normalize-space(.) != ""]) = 3 and td[contains(., "Departs") and not(contains(., "Flight"))]]');

        foreach ($rows as $row) {
            $seg = $f->addSegment();
            $tds = $this->http->XPath->query('td[normalize-space(.) != ""]', $row);

            if (3 !== $tds->length) {
                continue;
            }
            $date = null;
            $lines = $this->lines($tds->item(0));

            if (count($lines) >= 2 && preg_match('/^(?<date>\w+, \w+ \d{1,2})$/', $lines[0], $mDate) && preg_match('/^Flight (?<airline>[\w\s\d]+) (?<number>\d+)$/', $lines[1], $mFlight)) {
                if ('Delta' === $mFlight['airline']) {
                    $seg->airline()
                        ->name('DL');
                }
                $date = $mDate['date'];
                $seg->airline()
                    ->name($mFlight['airline'])
                    ->number($mFlight['number']);
            } elseif (count($lines) === 1 && preg_match('/^(?<date>\w+, \w+ \d{1,2}) (?<airline>[\w\s\d]+) (?<number>\d+)(?: Operated by (?<operator>.+))?$/u', $lines[0], $m)) {
                $seg->airline()
                    ->name('DL');
                $date = $m['date'];
                $seg->airline()
                    ->name($m['airline'])
                    ->number($m['number']);

                if (isset($m['operator']) && !empty($m['operator'])) {
                    $seg->airline()
                        ->operator($m['operator']);
                }
            }
            $lines = $this->lines($tds->item(1));

            if (count($lines) >= 2 && isset($date) && preg_match('/^Departs (?<time>\d+:\d+ [ap]m) (?<name>[\w\,\s\/]+)/', $lines[0], $depart) && preg_match('/^Arrives (?<time>\d+:\d+ [ap]m) (?<name>[\w\,\s\/]+)/', $lines[1], $arrive)) {
                $seg->departure()
                    ->name($depart['name'])
                    ->date($this->normalizeDate($depart['time'] . ' ' . $date . ' ' . $year))
                    ->noCode();

                $seg->arrival()
                    ->name($arrive['name'])
                    ->date($this->normalizeDate($arrive['time'] . ' ' . $date . ' ' . $year))
                    ->noCode();
            } elseif (count($lines) === 1 && isset($date) && preg_match('/^Departs (?<timeDep>\d+:\d+ [ap]m) (?<nameDep>[\w\,\s\/]+) Arrives (?<timeArr>\d+:\d+ [ap]m) (?:\((?<dateArr>\w+\s+\d+)\) )?(?<nameArr>[\w\,\s\/]+)/', $lines[0], $m)) {
                $seg->departure()
                    ->name($m['nameDep'])
                    ->date($this->normalizeDate($m['timeDep'] . ' ' . $date . ' ' . $year))
                    ->noCode();

                $seg->arrival()
                    ->name($m['nameArr'])
                    ->noCode();

                if (isset($m['dateArr']) && !empty($m['dateArr'])) {
                    $seg->arrival()
                        ->date($this->normalizeDate($m['timeArr'] . ' ' . $m['dateArr'] . ' ' . $this->segYear));
                } else {
                    $seg->arrival()
                        ->date($this->normalizeDate($m['timeArr'] . ' ' . $date . ' ' . $year));
                }
            }
            $lines = $this->lines($tds->item(2));

            if (count($lines) > 0 && preg_match('/Seat (\d+[A-Z])/', $lines[0], $m)) {
                $seg->extra()
                    ->seat($m[1]);
            }

            if (count($lines) > 0 && preg_match('/Meal[\s\-]+(\D+)/', $lines[0], $m)) {
                $seg->extra()
                    ->meal($m[1]);
            }
        }

        return true;
    }

    protected function lines(\DOMNode $node)
    {
        return array_values(array_filter(array_map('CleanXMLValue', explode("\n", $node->nodeValue))));
    }

    private function normalizeDate($date)
    {
        //$this->logger->debug('IN-' . $date);

        $in = [
            '/^([\d\:]+\s*a?p?m)\s*(\w+)\,\s*(\w+)\s*(\d+)\s+(\d+)$/ui', // 10:30 pm Saturday, July 5 2011
            '/^([\d\:]+\s*a?p?m)\s*(\w+)\s*(\d+)\s+(\d+)$/ui', // 10:30 pm July 5 2011
        ];
        $out = [
            '$2, $4 $3 $5, $1',
            '$3 $2 $4, $1',
        ];
        $date = preg_replace($in, $out, $date);
        //$this->logger->debug('AfterReplace-' . $date);
        if (preg_match("#\d+\s+([^\d\s]+)\,?#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+\,?\s*.+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            //$this->logger->debug('Weeknum-' . $weeknum);
            //$this->logger->debug('Date-' . $m['date']);
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum, $this->gYear);
            //$this->logger->debug('OUT-' . $date);
            $this->segYear = date('Y', $date); //because date without weekDay, (arrival only)
            //$this->logger->error('segYear'. date('Y', $date));
            //$this->logger->error('-----------------------------------------');
        } elseif (preg_match("/^\d+\s*\w+\s*\d{4}\,\s*[\d\:]+\s*a?p?m$/u", $date)) {
            return strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }
}
