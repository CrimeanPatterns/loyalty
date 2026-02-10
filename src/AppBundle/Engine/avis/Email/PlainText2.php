<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// parsers with similar formats: avis/It2160106

class PlainText2 extends \TAccountChecker
{
    public $mailFiles = ""; //bcd

    public $reFrom = ["avis.com"];
    public $reBody = [
        'en' => ['Thank you for booking with Avis', 'This is an automatic confirmation'],
    ];
    public $reSubject = [
        'en' => 'Avis Confirmation - Dear',
        'Reservation Confirmation',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Your reservation number is'                => 'Your reservation number is',
            'Your rental will commence on'              => 'Your rental will commence on',
            'Your rental vehicle will be collected by'  => ['Your rental vehicle will be collected by', 'You will need to return your rental vehicle to'],
            'The vehicle will be delivered to you from' => ['The vehicle will be delivered to you from', 'The vehicle will be available for collection from'],
        ],
    ];

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang($parser->getPlainBody())) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }
        $text = text($parser->getPlainBody());
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email, $text);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $bodyText = $parser->getPlainBody();

        if ($this->detectBody($bodyText)) {
            return $this->assignLang($bodyText);
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $reFrom) {
            if (stripos($from, $reFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Avis') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrase) {
            if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
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

    private function parseEmail(Email $email, $text)
    {
        $r = $email->add()->rental();

        $userName = $this->re("#{$this->opt($this->t('Dear'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*,#u", $text);

        if ($userName) {
            $r->general()->traveller($userName);
        }

        $r->general()
            ->confirmation($this->re("#{$this->opt($this->t('Your reservation number is'))}\s*([\w\-]{6,})#", $text),
                'reservation number');

        $regExp = "#{$this->t('Your rental will commence on')}\s+(?<puDate>.+?)\s+{$this->t('until')}\s+(?<doDate>.+?)\s*" .
            "(?:{$this->opt($this->t('Your car will be delivered to the following address'))}[:\s]+(?<puAddr>.+?)\n\n|"
            . "{$this->opt($this->t('The vehicle will be delivered to you from'))}.*)#s";

        if (preg_match($regExp, $text, $m)) {
            $r->pickup()
                ->date($this->normalizeDate($m['puDate']));
            $r->dropoff()
                ->date($this->normalizeDate($m['doDate']));

            if (!empty($m['puAddr'])) {
                $r->pickup()
                    ->location($this->nice($m['puAddr']));
            }
        }

        $regExp = "#{$this->opt($this->t('The vehicle will be delivered to you from'))}[\s:]+(?<addr>[\s\S]{3,}?)\s+" .
            "{$this->opt(['Opening hours on day of collection', 'Opening hours on day of return'])}[\s:]+(?<h>.+)\s+" .
            "{$this->t('Telephone')}[ ]*\([ ]*(?<ph>[+(\d][-. \d)(]{5,}[\d)])[ ]*\)#";

        if (preg_match($regExp, $text, $m)) {
            if (empty($r->getPickUpLocation()) && !empty($m['addr'])) {
                $r->pickup()->location($this->nice($m['addr']));
            }
            $r->pickup()
                ->openingHours($m['h'])
                ->phone($m['ph']);
        }

        $regExp = "#{$this->opt($this->t('Your car will be collected from the following address'))}[:\s]+(?<doAddr>.+?)\n\n#s";

        if (preg_match($regExp, $text, $m)) {
            $r->dropoff()
                ->location($this->nice($m['doAddr']));
        }
        $regExp = "#{$this->opt($this->t('Your rental vehicle will be collected by'))}[\s:]+(?<addr>[\s\S]{3,}?)\s+" .
            "{$this->opt(['Opening hours on day of return', 'Opening hours on day of collection'])}[\s:]+(?<h>.+)\s+" .
            "{$this->t('Telephone')}[ ]*\([ ]*(?<ph>[+(\d][-. \d)(]{5,}[\d)])[ ]*\)#";

        if (preg_match($regExp, $text, $m)) {
            if (empty($r->getDropOffLocation()) && !empty($m['addr'])) {
                $r->dropoff()->location($this->nice($m['addr']));
            }
            $r->dropoff()
                ->openingHours($m['h'])
                ->phone($m['ph']);
        }

        $regExp = "#{$this->t('Car group')}[:\s]+(?<type>.+?)\s*" .
            "\(\s*{$this->opt($this->t('e.g.'))}\s+(?<model>.+?)\s*\)\s+" .
            "(?<price>.+)#";

        if (preg_match($regExp, $text, $m)) {
            $r->car()
                ->type($m['type'])
                ->model($m['model']);
            $total = $this->getTotalCurrency($m['price']);
            $r->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }

        return true;
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //Tuesday, 14.05.2019 17:00
            '#^\s*([\w\-]+),\s+(\d+)\.(\d+)\.(\d{4})\s+(\d+:\d+)\s*$#u',
        ];
        $out = [
            '$4-$3-$2, $5',
        ];
        $outWeek = [
            '$1',
        ];

        if (!empty($week = preg_replace($in, $outWeek, $date))) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($week, $this->lang));
            $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));
            $str = EmailDateHelper::parseDateUsingWeekDay($str, $weeknum);
        } else {
            $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));
        }

        return $str;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function detectBody($body): bool
    {
        foreach ($this->reBody as $phrases) {
            foreach ($phrases as $phrase) {
                if (stripos($body, $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang($body): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words["Your reservation number is"], $words["Your rental will commence on"])) {
                if (stripos($body, $words["Your reservation number is"]) !== false && stripos($body,
                        $words["Your rental will commence on"]) !== false
                ) {
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

    private function getTotalCurrency($node)
    {
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['Total' => $tot, 'Currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function nice($str)
    {
        return trim(preg_replace("#\s+#", ' ', $str));
    }
}
