<?php

namespace AwardWallet\Engine\airindia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Itinerary extends \TAccountChecker
{
    public $mailFiles = "airindia/it-10852928.eml, airindia/it-11073380.eml, airindia/it-11839181.eml, airindia/it-11962010.eml, airindia/it-2351966.eml, airindia/it-2351988.eml, airindia/it-2386907.eml, airindia/it-27102802.eml, airindia/it-4846346.eml, airindia/it-4888610.eml, airindia/it-5.eml";

    public $detectLang = [
        'en' => ['FOR PASSENGERS', 'DEPART'],
        'es' => ['PASAJEROS', 'SALIDA'],
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'RECLOC'    => ['LOCATOR/CONTROL NO', 'TOR/CONTROL NO', 'ESTATER/CONTROL NO', 'TER/CONTROL NO', 'MAIN ROADNO'],
            'DATE/TIME' => ['DATE/TIME', 'TE/TIME'],
        ],
        'es' => [
            'RECLOC'         => 'NUMERO/DE RSVA',
            'FOR PASSENGERS' => 'PASAJEROS',
            'TICKETED'       => 'BILLETE EMITIDO',
            'DATE/TIME'      => 'FECHA/HORA',
            'DEPART'         => 'SALIDA',
            'ARRIVE'         => 'LLEGADA',
            'CONFIRMED'      => 'CONFIRMADO',
        ],
    ];

    private $code;
    private static $bodies = [
        'panorama' => [
            '//img[@alt=\'Ukraine International Airlines\']',
            'Ukraine International Airlines',
            'UKRAINE INTERNATIONAL',
        ],
        'airindia' => [
            '//img[@alt=\'Air India\']',
            'Air India',
        ],
        'skyair' => [
            '//img[@alt=\'Sky Airline\']',
            'Sky Airline',
        ],
    ];
    private $headers = [
        'panorama' => [
            'from' => ['@ps.kiev.ua', '@flyuia.com', 'panorama'],
            'subj' => [
                'Travel Itinerary',
            ],
        ],
        'airindia' => [
            'from' => ['@airindia.in'],
            'subj' => [
                'Travel Itinerary',
                'SCHEDULE CHANGE - REVISED ITINERARY INFORMATION',
            ],
        ],
        'skyair' => [
            'from' => ['skyairline.cl'],
            'subj' => [
                'Travel Itinerary',
            ],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (!$this->assignLang($body)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $this->logger->debug('[LANG]: ' . $this->lang);

        if (null !== ($code = $this->getProvider($parser))) {
            $this->logger->debug('[PROV]: ' . $code);
            $email->setProviderCode($code);
        } else {
            $this->logger->debug('can\'t determine a provider');
        }

        $a = explode('\\', __CLASS__);
        $class = end($a);
        $email->setType($class . ucfirst($this->lang));

        $this->parseEmail($email, text($body));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody() ? $parser->getHTMLBody() : $parser->getPlainBody();

        if (strpos($body, 'THIS DOCUMENT IS AUTOMATICALLY GENERATED. PLEASE DO NOT RESPOND TO THIS MAIL') !== false
            || strpos($body, 'SALES AGENT/UPDATE') !== false
        ) {
            return $this->assignLang($body);
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
        return array_keys(self::$bodies);
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->headers as $code => $arr) {
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

        foreach ($this->headers as $code => $arr) {
            $byFrom = $bySubj = false;

            foreach ($arr['from'] as $f) {
                if (stripos($headers['from'], $f) !== false) {
                    $byFrom = true;
                }
            }

            foreach ($arr['subj'] as $subj) {
                if (stripos($headers['subject'], $subj) !== false) {
                    $bySubj = true;
                }
            }

            if ($byFrom && $bySubj) {
                $this->code = $code;

                return true;
            }
        }

        return false;
    }

    private function parseEmail(Email $email, $text)
    {
        $paxText = $this->re("#\n\s*{$this->opt($this->t('FOR PASSENGERS'))}:[^\n]*\n(.*?)\n\n#ms", $text);

        if (empty($paxText)) {
            //FE: it-4888610.eml;
            $paxText = $this->re("#\n\s*{$this->opt($this->t('FOR PASSENGERS'))}:[^\n]*\n(.*?)\n\n\s*\-{5,}#ms", $text);
        }
        $travellers = array_map(function ($s) {
            return preg_match("#^(\w+/\w+) #u", $s, $m) ? $m[1] : null;
        }, array_filter(array_map('trim',
            explode("\n", $paxText))));
        $r = $email->add()->flight();
        $confNo = $this->re("#{$this->opt($this->t('RECLOC'))}[.:\s]+([A-Z\d\-]+)#", $text);

        if (!empty($confNo)) {
            $r->general()
                ->confirmation($confNo);
        } elseif (!preg_match("#: *[A-Z\d\-]{5,}\s*\n[^\n]*{$this->opt($this->t('DATE/TIME'))}[:\s]+(\d+\D+\d+/\d+)#",
            $text)) {
            $r->general()
                ->noConfirmation();
        }
        $r->general()
            ->travellers($travellers)
            ->date($this->normalizeDate($this->re("#\n[^\n]*{$this->opt($this->t('DATE/TIME'))}[:\s]+(\d+\D+\d+/\d+)#",
                $text)));

        if (preg_match_all("#{$this->opt($this->t('TICKETED'))} (\d+)#", $text, $m)) {
            $r->issued()->tickets($m[1], false);
        }

        if (preg_match_all("#{$this->opt($this->t('FREQUENT FLYER'))}\s+([A-Z \d]+)#", $text, $m)) {
            $r->program()->accounts(array_unique($m[1]), false);
        }

        $lastDate = $this->re("#(?:^|\n)\s*[A-Z]{3}\s+(\d+[A-Z]{3}\s*\d+\s*)#", $text);
        $segments = $this->splitter("#\n([^\n]+(?:\n *.*?OPERATED BY.+)?\s+{$this->opt($this->t('DEPART'))})#", $text);

        foreach ($segments as $segment) {
            $date = trim($lastDate);
            $newDate = $this->re("#(?:^|\n)\s*[A-Z]{3}\s+(\d+[A-Z]{3}\s*\d+\s*)#", $segment);

            if (!empty($newDate)) {
                $lastDate = $newDate;
            }

            if (preg_match("#{$this->opt($this->t('CANCELLED DUE OPERATIONAL REASONS'))}#", $segment)) {
                if (count($segments) > 1) {
                    continue;
                } else {
                    $r->general()->cancelled();
                }
            }

            $s = $r->addSegment();

            if ($status = $this->re("#\n *\*{3} *({$this->opt($this->t('CONFIRMED'))}) *\*{3}#", $segment)) {
                $s->extra()->status($status);
            }

            if (preg_match("#^(.*?)\s+(\d+)\s+([A-Z]{1,2})(?:\s+(.+?)\s*\/(?: +([^\/]+?) *)?(?:\/)?(?: +(.+))?)?\n#",
                $segment, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
                $s->extra()
                    ->bookingCode($m[3]);

                if (isset($m[4]) && !empty($m[4])) {
                    $s->extra()
                        ->aircraft($m[4]);
                }

                if (isset($m[5]) && !empty($m[5])) {
                    if (preg_match("#NON[\s\-]*STOP#", $m[5])) {
                        $s->extra()->stops(0);
                    } elseif (preg_match("#[STOP]+\-(\d+)#", $m[5], $v)) {
                        $s->extra()->stops($v[1]);
                    }
                }

                if (isset($m[6]) && !empty($m[6])) {
                    $node = strstr($segment, 'SPECIAL SERVICE');

                    if (preg_match("#\d+ ([^\n]*MEAL[^\n]*)#", $node, $v)) {
                        $s->extra()->meal($m[6] . ' (' . trim($v[1]) . ')');
                    } else {
                        $s->extra()->meal($m[6]);
                    }
                }
            }

            if (preg_match("#OPERATED BY +(.+)#", $segment, $m)) {
                $s->airline()
                    ->operator($m[1]);
            }

            if (preg_match("#\n\s*{$this->opt($this->t('DEPART'))}\s*:\s*(.+?)\s+(\d{4})(?: *TERMINAL[: ]+(.+))?#",
                $segment, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($date . ', ' . $m[2]));

                if (isset($m[3]) && !empty($m[3])) {
                    $s->departure()->terminal($m[3]);
                }
            }

            if (preg_match("#\n\s*{$this->opt($this->t('ARRIVE'))}\s*:\s*(.+?)\s+(\d{4})(?: *TERMINAL[: ]+(.+))?#",
                $segment, $m)) {
                $s->arrival()
                    ->noCode()
                    ->name($m[1])
                    ->date($this->normalizeDate($date . ', ' . $m[2]));

                if (isset($m[3]) && !empty($m[3])) {
                    $s->arrival()->terminal($m[3]);
                }
            }

            if (preg_match_all("#\n\s*SEATS\s*:\s*(\d+[A-Z]+)#", $segment, $m)) {
                $s->extra()->seats($m[1]);
            }

            if (preg_match("#NO SMOKING SEAT#i", $segment)) {
                $s->extra()->smoking(false);
            }
        }

        return true;
    }

    private function getProviderByBody()
    {
        foreach (self::$bodies as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        || (stripos($search, '//') === false && strpos($this->http->Response['body'],
                                $search) !== false)
                    ) {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (isset($this->code)) {
            return $this->code;
        }

        return $this->getProviderByBody();
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body)
    {
        if (isset($this->detectLang)) {
            foreach ($this->detectLang as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false && stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function normalizeDate($date)
    {
        $in = [
            //19FEB 16, 0930  |   03DEC16/0111
            '#^(\d+) *(\D+?) *(\d{2})[,\/] *(\d+?)(\d{2})$#',
        ];
        $out = [
            '$1 $2 20$3, $4:$5',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
