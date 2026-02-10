<?php

namespace AwardWallet\Engine\checkmytrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class FlightModification extends \TAccountChecker
{
    public $mailFiles = "checkmytrip/it-12234194.eml, checkmytrip/it-122359630.eml, checkmytrip/it-269011444.eml, checkmytrip/it-40011707.eml, checkmytrip/it-40137375.eml, checkmytrip/it-57952335.eml, checkmytrip/it-57993950.eml, checkmytrip/it-58186879.eml, checkmytrip/it-6818682.eml, checkmytrip/it-6891672.eml";

    public $reBody = [
        'fr' => [
            'Modification du vol',
            'Informations sur vos nouveaux vols',
        ],
        'zh' => [
            ['班機異動通知', '班機取消通知'],
            ['異動後的航班資訊', '異動前的航班資訊'],
        ],
        'en' => [
            ['Flight cancellation', 'Flight Cancellation', 'FLIGHT CANCELLATION', 'Flight modification', 'Flight Modification', 'FLIGHT MODIFICATION', 'SCHEDULE CHANGE', 'BOARDING GATE CHANGE'],
            ['Your cancelled flight', 'Your Cancelled Flight', 'Your itinerary', 'Your Itinerary', 'Your new flight', 'Your New Flight', 'Your flight(s):'],
        ],
        'is' => [
            ['Breyting á flugi'],
            ['Ný flug:'],
        ],
        'de' => [
            ['Informationen zu Ihren neuen Flügen'],
            ['Ihr Reiseplan:'],
        ],
    ];
    public $reSubject = [
        'en' => ['Your flight information', 'FLIGHT INFO from GARUDA INDONESIA', 'Flight Info'],
    ];
    public $lang = '';
    public static $dict = [
        'fr' => [
            'Flight' => 'Vol',
            //            'Date' => '',
            //            'Departure' => '',
            'Your booking reference' => 'Votre référence de réservation',
            'flight'                 => 'vol',
            'depart from'            => 'De',
            //            'cancelledPhrases' => '',
            'segmentsPairs' => [
                ['start' => 'Informations sur vos nouveaux vols', 'end' => 'Informations sur vos vols précédents'],
                ['start' => 'Votre itinéraire', 'end' => ''],
            ],
        ],
        'zh' => [
            'Flight'                 => '班機號碼',
            'Date'                   => '日期',
            'Departure'              => '起飛時間',
            'Your booking reference' => '您的訂位代號',
            //            'flight' => '',
            //            'depart from' => '',
            'cancelledPhrases' => ')班機取消。',
            'segmentsPairs'    => [
                ['start' => '異動後的航班資訊', 'end' => '異動前的航班資訊'],
                ['start' => '異動前的航班資訊', 'end' => '~ 旅遊'], // it-57993950.eml
            ],
        ],
        'en' => [
            'Your booking reference' => ['Your booking reference', 'Your Booking Reference'],
            'cancelledPhrases'       => 'Your cancelled flight',
            'Departure'              => ['Departure', 'Departur'],
            'segmentsPairs'          => [
                ['start' => 'Your itinerary:', 'end' => 'For further information'],
                ['start' => 'Your cancelled flight', 'end' => 'reminds you'],
                ['start' => 'Your new flight', 'end' => 'Your previous flight'],
                ['start' => 'Your new flight', 'end' => 'please contact the travel agency'],
                ['start' => '', 'end' => 'Information about your previous flight'],
                ['start' => 'Your new flight(s) information', 'end' => 'Your previous flight(s) information'],
                ['start' => 'Your flight(s):', 'end' => ''],
                ['start' => 'Your itinerary', 'end' => 'please contact your travel agency'],
            ],
        ],
        'is' => [
            'Flight'                 => 'Flug',
            'Date'                   => 'Dags.',
            'Departure'              => 'Frá',
            'Your booking reference' => 'Bókunarnúmer:',
            'flight'                 => 'flugi',
            'depart from'            => 'frá',
            //            'cancelledPhrases' => '',
            'segmentsPairs' => [
                ['start' => 'Ný flug:', 'end' => ''],
            ],
        ],
        'de' => [
            'Flight'                 => 'Flug',
            'Date'                   => 'Datum',
            'Departure'              => 'Von',
            'Your booking reference' => 'Ihre Buchungsreferenz:',
            'flight'                 => 'Flugplan',
            'depart from'            => 'geändert wurde',
            //            'cancelledPhrases' => '',
            'segmentsPairs' => [
                ['start' => 'Ihr Reiseplan:', 'end' => ''],
            ],
        ],
    ];

    private $providerCode = '';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->assignProvider($parser->getHeaders());

        $this->parseEmail($parser, $email);
        $a = explode('\\', __CLASS__);
        $email->setType($a[count($a) - 1] . ucfirst($this->lang));
        $email->setProviderCode($this->providerCode);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->assignProvider($parser->getHeaders()) === false) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            foreach ($reSubject as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, 'CheckMyTrip') !== false
            || stripos($from, '@checkmytrip.com') !== false
            //garuda
            || stripos($from, 'GARUDA INDONESIA') !== false
            //icelandair
            || stripos($from, '@icelandair.com') !== false
            || stripos($from, 'ICELANDAIR') !== false
            || stripos($from, 'Icelandair') !== false
            //eva
            || stripos($from, '@evaair.com') !== false
            || stripos($from, 'EVA') !== false;
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
        return ['checkmytrip', 'icelandair', 'eva', 'garuda', 'cape'];
    }

    private function parseEmail(\PlancakeEmailParser $parser, Email $email): void
    {
        $f = $email->add()->flight();

        $travellers = [];
        $passengerRows = $this->http->XPath->query("//text()[{$this->starts($this->t('Passenger Name(s)'))}]/ancestor::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()][1]/descendant::tr[not(.//tr) and normalize-space()]");

        foreach ($passengerRows as $pRow) {
            $pName = $this->http->FindSingleNode('.', $pRow);

            if (preg_match("/^[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]$/u", $pName)) {
                $travellers[] = $pName;
            } else {
                $travellers = [];

                break;
            }
        }

        if (count($travellers)) {
            $f->general()->travellers($travellers);
        }

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking reference'))}]", null, true, "#:\s*([A-Z\d]{5,})$#u");

        if (!$confirmation) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking reference'))}]/following::text()[normalize-space()][1]", null, true, "#^[A-Z\d]{5,}$#");
        }
        //it-6891672.eml
        if (!$confirmation) {
            $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your booking reference'))}]/ancestor::tr[1]/following::tr[normalize-space()][not(contains(normalize-space(), '..'))][1]", null, true, "#^[A-Z\d]{5,}$#");
        }
        $f->general()->confirmation($confirmation);

        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        if ($this->http->XPath->query("//text()[{$this->eq($this->t('Flight'))}]/ancestor::td[ following-sibling::td[normalize-space()][1][{$this->contains($this->t('Date'))}]]/ancestor::tr[1][ preceding::tr[not(.//tr) and normalize-space()][{$this->contains($this->t('cancelledPhrases'))}] ]/following::tr[not(.//tr) and normalize-space()]/descendant-or-self::tr[count(td)>6 and {$xpathTime}]")->length > 0) {
            // it-40011707.eml, it-57993950.eml
            $f->general()->cancelled();
        }

        $xpathSegmentsAll = "//tr[count(.//tr[normalize-space() and not(.//tr)]) < 2 and descendant-or-self::tr[not(.//tr)][ *[{$this->eq($this->t('Departure'))}] and *[{$this->eq($this->t('Flight'))}] ]]/following-sibling::tr[count(.//tr[normalize-space() and not(.//tr)]) < 2][descendant-or-self::tr[count(*)>8 and (count(*[normalize-space()])=6 or count(*[normalize-space()])=7) and {$xpathTime} and not({$this->contains($this->t('Departure'))})]]";

        foreach ((array) $this->t('segmentsPairs') as $pair) {
            $xpathSegmentsFilters = [];

            if (is_array($pair) && !empty($pair['start'])) {
                $xpathSegmentsFilters[] = "preceding::tr[{$this->starts($pair['start'])}]";
                $xpathSegmentsFilters[] = "not(following::tr[{$this->starts($pair['start'])}])";
            }

            if (is_array($pair) && !empty($pair['end'])) {
                $xpathSegmentsFilters[] = "following::tr[{$this->contains($pair['end'])}]";
                $xpathSegmentsFilters[] = "not(preceding::tr[{$this->contains($pair['end'])}])";
            }

            $segments = $this->http->XPath->query($xpathSegmentsAll
                . (count($xpathSegmentsFilters) ? '[' . implode(' and ', $xpathSegmentsFilters) . ']' : '') . '/descendant-or-self::tr[not(.//tr)][normalize-space()]');

            if ($segments->length > 0) {
                break;
            }
        }

        if ($segments->length === 0) {
            $this->logger->debug('Segments not found!');

            return;
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('td[normalize-space()][1]', $root);

            if (preg_match("/\b(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[ ]*(?<number>\d+)/", $flight, $m)
                || preg_match("/^(?<name>.{2,}?)[ ]+(?<number>\d+)$/", $flight, $m)
            ) {
                // GA690    |    CAPE AIR 2811
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
                $node = $this->http->FindSingleNode("//text()[contains(.,'{$this->t('flight')}') and contains(.,'{$m['name']}') and contains(.,'{$m['number']}')]");
                $terminalDep = $this->re("#{$this->opt($this->t('depart from'))} .+? \([A-Z]{3}\), {$this->opt($this->t('Terminal'))} (\w+)#", $node);
                $s->departure()->terminal($terminalDep, false, true);
            }

            $date = 0;
            $dateValue = $this->normalizeDate($this->http->FindSingleNode("td[normalize-space()][2]", $root));

            if (!preg_match('/\d{4}$/', $dateValue)) {
                $date = EmailDateHelper::calculateDateRelative($dateValue, $this, $parser, '%D%/%Y%');
            } elseif ($dateValue) {
                $date = strtotime($dateValue);
            }

            $class = $this->http->FindSingleNode("td[normalize-space()][3]", $root, true, "#^[A-Z]{1,2}$#");

            if (!empty($class)) {
                $s->extra()->bookingCode($class);
            }

            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][last()-3]", $root);

            if (preg_match("/^(.+?)\s*\(\s*([A-Z]{3})\s*\)/", $node, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2]);
            } elseif (strpos($node, '(') !== false && strpos($node, ':') === false) {
                $s->departure()->name($node)->noCode();
            }

            $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][last()-2]", $root);

            if (preg_match("/^(.+?)\s*\(\s*([A-Z]{3})\s*\)/", $node, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2]);
            } elseif (strpos($node, '(') !== false && strpos($node, ':') === false) {
                $s->arrival()->name($node)->noCode();
            }

            $patterns['time'] = '\d{1,2}(?:[:：]+\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

            $timeDep = $this->http->FindSingleNode('td[normalize-space()][last()-1]', $root, true, "/^{$patterns['time']}$/");
            $timeArr = $this->http->FindSingleNode('td[normalize-space()][last()]', $root);

            if (empty($timeDep) && !empty($timeArr)) {
                $countRow = count($this->http->FindNodes("td[normalize-space()]", $root));

                if ($countRow == 6) {
                    $timeDep = $this->http->FindSingleNode('td[normalize-space()][last()]', $root, true, "/^{$patterns['time']}$/");
                    $s->departure()
                        ->date(strtotime(str_replace('：', ':', $timeDep), $date));

                    $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][last()-2]", $root);

                    if (preg_match("/^(.+?)\s*\(\s*([A-Z]{3})\s*\)/", $node, $m)) {
                        $s->departure()
                            ->name($m[1])
                            ->code($m[2]);
                    } elseif (strpos($node, '(') !== false && strpos($node, ':') === false) {
                        $s->departure()->name($node)->noCode();
                    }

                    $node = $this->http->FindSingleNode("./td[normalize-space(.)!=''][last()-1]", $root);

                    if (preg_match("/^(.+?)\s*\(\s*([A-Z]{3})\s*\)/", $node, $m)) {
                        $s->arrival()
                            ->name($m[1])
                            ->code($m[2])
                            ->noDate();
                    } elseif (strpos($node, '(') !== false && strpos($node, ':') === false) {
                        $s->arrival()->name($node)->noCode();
                    }
                }
            } else {
                if ($timeDep && $date) {
                    $s->departure()->date(strtotime(str_replace('：', ':', $timeDep), $date));
                }

                if (preg_match("/^({$patterns['time']})(?:\s*[+]\s*(\d{1,3}))?$/", $timeArr, $m) && $date) {
                    $s->arrival()->date(strtotime(str_replace('：', ':', $m[1]), $date));

                    if (!empty($m[2])) {
                        $s->arrival()->date(strtotime("+{$m[2]} days", $s->getArrDate()));
                    }
                }

                if (empty($s->getArrDate()) && preg_match('/^[:-]$/', $this->http->FindSingleNode('td[normalize-space()][last()]', $root))) {
                    $s->arrival()->noDate();
                }
            }
        }
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})[-,.\s]*([[:alpha:]]{3,})[-,.\s]*(\d{2})$/u', $text, $m)) {
            // 03JUN20
            $day = $m[1];
            $month = $m[2];
            $year = '20' . $m[3];
        } elseif (preg_match('/^(\d{1,2})\s*月\s*(\d{1,2})\s*日$/u', $text, $m)) {
            // 5月25日
            $month = $m[1];
            $day = $m[2];
            $year = '';
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dict, $this->lang) || empty(self::$dict[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dict[$this->lang][$phrase];
    }

    private function assignProvider($headers): bool
    {
        if ($this->http->XPath->query('//a[contains(@href,"www.checkmytrip.com") or contains(@href,"@checkmytrip.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"The CheckMyTrip team")]')->length > 0
        ) {
            $this->providerCode = 'checkmytrip';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,".icelandair.is/")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"THANK YOU FOR FLYING WITH ICELANDAIR") or contains(normalize-space(),"For bookings directly with Icelandair") or contains(normalize-space(),"Regards Icelandair") or contains(.,"www.icelandair.com")]')->length > 0
        ) {
            $this->providerCode = 'icelandair';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"www.evaair.com/")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"EVA Airways informs") or contains(normalize-space(),"EVA Airways reminds you") or contains(normalize-space(),"please visit EVA website")]')->length > 0
        ) {
            $this->providerCode = 'eva';

            return true;
        }

        if ($this->http->XPath->query('//a[contains(@href,"www.garuda-indonesia.com/")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Garuda Indonesia")]')->length > 0
        ) {
            $this->providerCode = 'garuda';

            return true;
        }

        if (strpos($headers['subject'], 'Cape Air') !== false
            || $this->http->XPath->query('//a[contains(@href,".capeair.com/") or contains(@href,"www.capeair.com")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"visit us at capeair.com")]')->length > 0
        ) {
            $this->providerCode = 'cape';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody[0])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($reBody[1])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
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
