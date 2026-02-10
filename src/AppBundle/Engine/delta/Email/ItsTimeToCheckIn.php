<?php

namespace AwardWallet\Engine\delta\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\delta\Email\Airport\Resolver;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ItsTimeToCheckIn extends \TAccountChecker
{
    public $mailFiles = "delta/it-106779019.eml, delta/it-11511599.eml, delta/it-11697008.eml, delta/it-15900967.eml, delta/it-3799064.eml, delta/it-38409153.eml, delta/it-6287616.eml, delta/it-6288888.eml, delta/it-6303565.eml, delta/it-6305072.eml, delta/it-6331303.eml, delta/it-6337843.eml, delta/it-6389184.eml, delta/it-67905823.eml, delta/it-6810839.eml, delta/it-6818121.eml, delta/it-7297432.eml, delta/it-7356561.eml, delta/it-9794661.eml, delta/it-9795599.eml, delta/it-9815595.eml";

    /* @var Resolver $resolver */
    public $resolver;

    private $year = '';
    private $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->parseEmail($parser, $email);
        $email->setType('TimeToCheckIn');

        return $email;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'Delta Air Lines') !== false
            || preg_match('/@[a-z]\.delta\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        return stripos($headers['subject'], "It's Time To Check") !== false
            || stripos($headers['subject'], 'Vivek Delta flight') !== false
            || stripos($headers['subject'], ', Your Upgraded Seat') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query('//a[contains(@href,"e.delta.com") or contains(@href, "t.delta.com")]')->length > 0
            && (
                $this->http->XPath->query('//text()[contains(normalize-space(),"Your flight on") and contains(normalize-space(),"is available for check-in")]')->length > 0
                || $this->http->XPath->query('//text()[contains(normalize-space(),"our pleasure to confirm your Complimentary Upgrade")]')->length > 0
            );
    }

    private function parseEmail(\PlancakeEmailParser $parser, Email $email): void
    {
        $f = $email->add()->flight();
        $this->resolver = new Resolver();
        $this->http->SetEmailBody(str_replace('&nbsp;', ' ', $this->http->Response['body']));

        $dateEmail = $parser->getHeader('date');

        if (!empty($dateEmail)) {
            $this->year = date('Y', strtotime($dateEmail));
        } else {
            $this->year = date('Y');
        }
        $this->year = intval($this->year);

        $withoutYear = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Your flight on')]", null, true, "/Your flight on\s*(\w+\,\s*\w+\s*\d+)\s*is/");

        if (empty($withoutYear)) {
            $withoutYear = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'DEPART')]/ancestor::tr[1]/descendant::td[1]");
        }

        if (empty($withoutYear)) {
            $withoutYear = $this->http->FindSingleNode("//text()[normalize-space()='Privacy Policy']/preceding::strong[contains(normalize-space(), 'SEAT')][1]/ancestor::tr[1]/descendant::strong[1]");
        }

        if (empty($withoutYear)) {
            $withoutYear = $this->http->FindSingleNode("//text()[normalize-space()='Privacy Policy' or normalize-space()='PRIVACY POLICY']/preceding::text()[contains(normalize-space(), 'SEAT')][1]/ancestor::tr[1]/descendant::text()[normalize-space()][1]/ancestor::td[1]");
        }

        //Check year by week date
        $this->year = $this->normalizeYear($withoutYear . ' ' . $this->year);

        $dateWelcome = $this->http->FindSingleNode('//text()[contains(normalize-space(),"is available for check-in")]', null, true, '/Your flight on ([[:alpha:]]{2,}[,\s]+[[:alpha:]]{3,}\s+\d{1,2}) is available for check-in/iu');

        if ($dateWelcome) {
            // it-11511599.eml
            $date = $this->textToDate($dateWelcome);
        }
        $this->logger->debug('flight year ' . $this->year);

        $conf = $this->http->FindSingleNode("//text()[normalize-space()='Confirmation Number']/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,35}$/")
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'Delta Confirmation')]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]", null, true, "/Delta Confirmation[#:\s]+([A-Z\d]{5,35})$/")
            ?? $this->http->FindSingleNode("//text()[contains(normalize-space(),'Confirmation') and contains(.,'#')]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]/descendant::a[normalize-space()]", null, true, "/^([A-Z\d]{5,7})(?:$|\s*(?i)\[click\.t)/")
            ?? $this->http->FindSingleNode("descendant::text()[starts-with(normalize-space(),'CONFIRMATION #') and contains(.,':')][1]/ancestor::*[ descendant::text()[normalize-space()][2] ][1]", null, true, "/^CONFIRMATION #\s*[:]+\s*([A-Z\d]{5,7})$/")
        ;
        $f->general()->confirmation($conf);

        $traveller = $this->http->FindSingleNode('//text()[starts-with(normalize-space(),"Hello,")]', null, true, '/Hello,\s*([[:alpha:]][-.\'&[:alpha:] ]*[[:alpha:]])(?:\s*[,;:!?]|$)/iu');

        if ($traveller) {
            $f->general()->travellers(array_filter(preg_split("/\s+(?:and|&)\s+/i", $traveller), function ($item) {
                return !preg_match('/\.$/', $item);
            }));
        }

        $accountText = implode("\n", $this->http->FindNodes('descendant::tr[(starts-with(normalize-space(),"SkyMiles") or descendant::a[contains(normalize-space(@title),"Your Account")] or *[descendant::img[contains(@class,"header_logo")] and normalize-space()="" and count(following-sibling::*[normalize-space()])=1]) and contains(.,"#")][1]/descendant::text()[normalize-space()]'));

        if (preg_match("/(?:^|[ #])(\d{5,})\D*$/m", $accountText, $m)) {
            $f->program()->account($m[1], false);
        }

        //$headers = $this->http->XPath->query('//tr[th[contains(.,"DEPART")] and th[contains(.,"ARRIVE")]]');
        //rule of time in text()
        $ruleTime = "contains(translate(normalize-space(.),'0123456789','dddddddddd--'),'d:dd') and translate(translate(substring(normalize-space(.),string-length(normalize-space(.))-1),'APM','apm'),'apm','ddd')='dd'";
        $headers = $this->http->XPath->query($xpath = "//text()[contains(translate(.,'SEAT','seat'),'seat') or ({$ruleTime})]/ancestor::tr[count(*)=4 or *[normalize-space()][3]][1]");
        $this->logger->debug($xpath);

        if ($headers->length === 0) {
            return;
        }

        $row = $headers->item(0);
        $limit = 100;
        $i = 0;

        while (isset($row) && $limit > 0) {
            $limit--;

            if ($this->http->FindSingleNode('./self::tr[*[self::th or self::td][contains(.,"DEPART")] and *[self::th or self::td][contains(.,"ARRIVE")]]', $row)) {
                // Example: it-15900967.eml ...
                $dateRoute = implode(' ', $this->http->FindNodes('*[self::th or self::td][1]//text()[normalize-space()]', $row));

                if (!preg_match('/[[:alpha:]]{2,}[,\s]+[[:alpha:]]{3,}\s+\d{1,2}$/u', $dateRoute)) {
                    $dateRoute = null;
                }

                if (!$dateRoute || !strtotime($dateRoute)) {
                    break;
                }
                $date = $this->textToDate($dateRoute);
            }

            $tds = [];
            $tdNodes = $this->http->XPath->query('*', $row);

            foreach ($tdNodes as $tdNode) {
                $tdText = '';
                $internalRows = $this->http->XPath->query("table[normalize-space() and (preceding-sibling::table[normalize-space()] or following-sibling::table[normalize-space()])]", $tdNode);

                foreach ($internalRows as $intRow) {
                    // it-6810839.eml
                    $intRowHtml = $this->http->FindHTMLByXpath('.', null, $intRow);
                    $tdText .= $this->htmlToText($intRowHtml) . "\n";
                }

                if ($internalRows->length === 0) {
                    // it-67905823.eml
                    $tdHtml = $this->http->FindHTMLByXpath('.', null, $tdNode);
                    $tdText = $this->htmlToText($tdHtml);
                }

                if ($tdText !== '') {
                    $tds[] = $tdText;
                }
            }

            if (preg_match("/^\d+(?:\n|$)/", $tds[0])) {
                $tds[0] = 'DL ' . $tds[0];
            }

            if (!array_key_exists(3, $tds)) {
                $tds[3] = '';
            }

            if (count($tds) === 4
                && preg_match('/^\s*(?<airline>[\w\s]+?)[ ]+(?<number>\d{1,5})[*]*(?:\s*(?<operatorCabin>[\s\S]+))?$/', $tds[0], $flight)
                //&& preg_match('/^([\w\s]+?) (\d{1,5})/', $tds[0], $flightMatches)
                && preg_match('/^(?<name>[^*]+?)(\*\*)?\s*(?<time>\d{1,2}:\d{2} [ap]m)(?: on (?<date>\w+ \d+))?\b/', $tds[1], $dep)
                && preg_match('/^(?<name>[^*]+?)(\*\*)?\s*(?<time>\d{1,2}:\d{2} [ap]m)(?: on (?<date>\w+ \d+))?\b/', $tds[2], $arr)
                && (preg_match('/^(?:Seat\s+)?(\d{1,2}[A-Z][,\dA-Z ]*)$/', $tds[3], $seatMatches)
                    || preg_match('/^(\s*)$/', $tds[3], $seatMatches)
                    || preg_match('/^Seat$/i', $tds[3], $seatMatches)
                    || preg_match('/^(?:Seat Assigned after Check-In|Choose Seat)$/i', $tds[3], $seatMatches))
                && isset($date)
            ) {
                $s = $f->addSegment();

                $s->departure()->name($dep['name']);
                $s->arrival()->name($arr['name']);

                $s->departure()->date(strtotime($dep['time'], $date));

                if (!empty($arr['date']) && $s->getDepDate()) {
                    // it-3799064.eml
                    $dateArr = EmailDateHelper::parseDateRelative($arr['date'], $s->getDepDate());
                    $s->arrival()->date(strtotime($arr['time'], $dateArr));
                    $date = $dateArr;
                } else {
                    $s->arrival()->date(strtotime($arr['time'], $date));
                }

                if (strcasecmp($flight['airline'], 'delta') === 0) {
                    $s->airline()->name('DL');
                } else {
                    $s->airline()->name($flight['airline']);
                }

                $s->airline()->number($flight['number']);

                if (isset($seatMatches[1])) {
                    $s->extra()->seats(preg_split('/\s*,\s*/', $seatMatches[1]));
                }

                // Operator
                // Cabin
                if (!empty($flight['operatorCabin'])
                    && preg_match('/^Operated by[ ]+(?<operator>.{2,}?)(?:[ ]*\n+[ ]*(?<cabin>.{2,}))?$/', $flight['operatorCabin'], $m)
                ) {
                    $s->airline()->operator($m['operator']);

                    if (!empty($m['cabin'])) {
                        $s->extra()->cabin($m['cabin']);
                    }
                } elseif (!empty($flight['operatorCabin'])
                    && preg_match('/^.{2,}$/', $flight['operatorCabin'])
                ) {
                    $s->extra()->cabin($flight['operatorCabin']);
                }

                if (isset($this->resolver)) {
                    if ($code = $this->resolver->resolve($s->getDepName())) {
                        $s->departure()->code($code);
                    } else {
                        $s->departure()->noCode();
                    }

                    if ($code = $this->resolver->resolve($s->getArrName())) {
                        $s->arrival()->code($code);
                    } else {
                        $s->arrival()->noCode();
                    }
                }
            }
            $next = $this->http->XPath->query('./following-sibling::tr', $row);

            if ($next->length > 0) {
                $row = $next->item(0);
            } elseif (!empty($headers->item($i + 1))) {
                $row = $headers->item($i + 1);
            } else {
                unset($row);
            }
            $i++;
        }
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

    private function textToDate(?string $dateValue): ?int
    {
        if (preg_match('/(?<wday>[[:alpha:]]{2,})[,\s]+(?<date>[[:alpha:]]{3,}\s+\d{1,2})$/u', $dateValue, $m)) {
            // Thursday, May 5
            $weekDayNumber = WeekTranslate::number1($m['wday']);

            if ($weekDayNumber && $this->year) {
                return EmailDateHelper::parseDateUsingWeekDay($m['date'] . ' ' . $this->year, $weekDayNumber);
            }
        }

        return null;
    }

    private function normalizeYear($date)
    {
        $in = [
            '/^(\w+)\,?\s*(\w+)\s*(\d+)\s*(\d{4})$/ui', // Friday, June 29 2018
        ];
        $out = [
            '$1, $3 $2 $4',
        ];
        $date = preg_replace($in, $out, $date);
        $this->logger->debug('AfterReplace-' . $date);

        if (preg_match("#\d+\s+([^\d\s]+)\,?#u", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        if (preg_match("#^(?<week>\w+), (?<date>\d+ \w+\,?\s*.+)#u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum, $this->year);
            $year = date('Y', $date);
        } else {
            $year = null;
        }

        return $year;
    }
}
