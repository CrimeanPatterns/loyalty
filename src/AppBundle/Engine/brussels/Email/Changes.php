<?php

namespace AwardWallet\Engine\brussels\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Changes extends \TAccountChecker
{
    use \DateTimeTools;
    public $mailFiles = "brussels/it-5010717.eml, brussels/it-59962915.eml, brussels/it-59982456.eml, brussels/it-7058856.eml";

    public $reSubject = [
        'en' => 'Your flight times have changed',
        'Important message - Your flight has been re-scheduled (your response is required)',
        'Important message - Your flight has been re-scheduled',
        'Important message - Your flight has been changed',
        'nl' => 'Belangrijk bericht - uw vlucht werd gewijzigd (uw reactie is vereist)',
    ];
    public $dateSubj;
    public $lang = 'en';
    public static $dict = [
        'en' => [
            'Booking Reference' => 'Booking Reference',
            //            'Passengers' => '',
            //            'Schedule Change' => '',
            //            'New Flight' => '',
            //            'Date' => '',
            'Departure' => 'Departure',
            'Arrival'   => 'Arrival',
            //            'booking reference:' => '',
            //            'cancelled' => '',
            //            'departing on' => '',
            //            '' => '',
        ],
        'nl' => [
            'Booking Reference' => 'Boekingsreferentie',
            'Passengers'        => 'Passagiers',
            'Schedule Change'   => 'Vluchtwijziging',
            'New Flight'        => 'Nieuwe vlucht',
            'Date'              => 'Datum',
            'Departure'         => 'Vertrek',
            'Arrival'           => 'Aankomst',
            //            'cancelled' => '',
        ],
    ];

    private $detectBody = [
        'en' => [
            'Timings of your flight have changed',
            'There have been some changes in your flight details',
            'There have been some changes to your flight details',
            'As a consequence, we regret to inform you that your flight',
            'There have been some changes to your booking',
        ],
        'nl' => [
            'Er zijn wijzigingen in uw vluchtschema',
        ],
    ];

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $body = $this->http->Response['body'];
        $this->AssignLang($body);
        $this->dateSubj = strtotime($parser->getDate());

        if (!empty($this->http->FindSingleNode("//text()[" . $this->eq($this->t("cancelled")) . "]"))) {
            $this->parseCancelled($email);
        } else {
            $this->parseEmail($email);
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[normalize-space() = 'brusselsairlines.com']")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $dBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($dBody) . "]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($this->reSubject)) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], $reSubject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "brusselsairlines.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    private function parseEmail(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Booking Reference') . "')]/ancestor::*[1]", null, true, "#:\s+([A-Z\d]+)#"))
            ->travellers($this->http->FindNodes("//*[contains(text(),'" . $this->t('Passengers') . "')]/ancestor::li[1]/following-sibling::li[normalize-space()][not(contains(.,'" . $this->t('Schedule Change') . "'))]"))
        ;

        $xpath = "//*[contains(text(),'" . $this->t('New Flight') . "')]/ancestor::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $node = $this->http->FindSingleNode(".", $root);

            if (preg_match("#([A-Z\d]{2})\s*(\d+)#", $node, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2])
                ;
            }
            $node = $this->http->FindSingleNode("./following-sibling::tr[1]", $root);
//            Madrid (MAD: Aeropuerto De Madrid Barajas) - Brussels (BRU: Brussels Airport)
            if (preg_match("#(?<depCity>.+?)\s*\((?<depCode>[A-Z]{3})\s*\:?\s*(?<depName>.*?)\)\s*-\s*(?<arrCity>.+?)\s*\((?<arrCode>[A-Z]{3})\s*\:?\s*(?<arrName>.*?)\)#", $node, $m)) {
                $s->departure()
                    ->code($m['depCode'])
                    ->name($m['depCity'] . (!empty($m['depName']) ? ' - ' . $m['depName'] : ''))
                ;

                $s->arrival()
                    ->code($m['arrCode'])
                    ->name($m['arrCity'] . (!empty($m['arrName']) ? ' - ' . $m['arrName'] : ''))
                ;
            }
            $year = date('Y', $this->dateSubj);
            $node = $this->http->FindSingleNode("./following-sibling::tr[2]", $root);
//            Date: 25NOV, Departure: 20:45, Arrival: 23:25
            if (preg_match("#" . $this->t('Date') . "\s*:\s*(\d+\S+?),\s*" . $this->t('Departure') . "\s*:\s*(\d+:\d+),\s*" . $this->t('Arrival') . "\s*:\s*(\d+:\d+)#", $node, $m)) {
                $date = strtotime($m[1] . $year . " " . $m[2]);

                if ($date < $this->dateSubj) {
                    $year++;
                    $date = strtotime($m[1] . $year . " " . $m[2]);
                }
                $s->departure()
                    ->date($date);
                $s->arrival()
                    ->date(strtotime($m[1] . $year . " " . $m[3]));
            }
        }

        return $email;
    }

    private function parseCancelled(Email $email)
    {
        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[" . $this->contains($this->t('booking reference:')) . "]", null, true, "#" . $this->preg_implode($this->t('booking reference:')) . "\s+([A-Z\d]+)\b#"))
        ;

        $text = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('cancelled')) . "]/ancestor::*[not(" . $this->eq($this->t('cancelled')) . ")][1]");
//        $this->logger->debug('$text = '.print_r( $text,true));

        switch ($this->lang) {
            case 'en':
                $regexp = "#(?<al>[A-Z\d][A-Z]|[A-Z][A-Z\d])(?<fn>\d{1,5}),\D*? departing on (?<date>.+?) from (?<from>.+) to (?<to>.+?) at (?<time>\d+:\d+)#";
        }

        if (!empty($regexp) && preg_match($regexp, $text, $m)) {
            $f->general()->cancelled();

            $s = $f->addSegment();

            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;

            if (preg_match("#(?<city>.+?)\s*\((?<code>[A-Z]{3})\s*\:?\s*(?<name>.*?)\)#", $m['from'], $mat)) {
                $s->departure()
                    ->code($mat['code'])
                    ->name($mat['city'] . (!empty($mat['name']) ? ' - ' . $mat['name'] : ''))
                ;
            }

            if (preg_match("#(?<city>.+?)\s*\((?<code>[A-Z]{3})\s*\:?\s*(?<name>.*?)\)#", $m['to'], $mat)) {
                $s->arrival()
                    ->code($mat['code'])
                    ->name($mat['city'] . (!empty($mat['name']) ? ' - ' . $mat['name'] : ''))
                ;
            }

            $year = date('Y', $this->dateSubj);
            $date = strtotime($m['date'] . $year . " " . $m['time']);

            if ($date < $this->dateSubj) {
                $year++;
                $date = strtotime($m['date'] . $year . " " . $m['time']);
            }
            $s->departure()
                ->date($date);
            $s->arrival()
                ->noDate();
        }

        return $email;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang($body)
    {
        foreach (self::$dict as $lang => $reBody) {
            if (!empty($reBody['Departure']) && !empty($reBody['Arrival']) && stripos($body, $reBody['Departure']) !== false && stripos($body, $reBody['Arrival']) !== false) {
                $this->lang = $lang;

                break;
            }

            if (!empty($reBody['departing on']) && stripos($body, $reBody['departing on']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        return true;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return '(?:' . implode("|", array_map(function ($v) {return preg_quote($v, '#'); }, $field)) . ')';
    }
}
