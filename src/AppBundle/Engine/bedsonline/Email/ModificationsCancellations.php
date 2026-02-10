<?php

namespace AwardWallet\Engine\bedsonline\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ModificationsCancellations extends \TAccountChecker
{
    public $mailFiles = "bedsonline/it-320013057.eml, bedsonline/it-41916968.eml, bedsonline/it-41950993.eml, bedsonline/it-44222683.eml, bedsonline/it-44222723.eml";

    public $reFrom = ["@bedsonline.com"];
    public $reBody = [
        'en' => ['Modification request receipt for booking'],
    ];
    public $reSubject = [
        '#Modifications and Cancellations \d+\-\d+$#',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Passenger name:'      => 'Passenger name:',
            'Service description:' => 'Service description:',
        ],
    ];
    private $keywordProv = 'Bedsonline';
    private $text;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->checkFormat($parser->getHTMLBody())) {
            $this->logger->debug('other format');

            return $email;
        }

        if (isset($this->text)) {
            $this->parsePlain($email, $this->text);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        foreach ($this->reBody as $lang => $reBody) {
            if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                return $this->checkFormat($parser->getHTMLBody());
            }
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
        $fromProv = true;

        if (!isset($headers['from']) || self::detectEmailFromProvider($headers['from']) === false) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || stripos($headers["subject"], $this->keywordProv))
                    && preg_match($reSubject, $headers["subject"]) > 0
                ) {
                    return true;
                }
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

    private function parsePlain(Email $email, string $text)
    {
        $traveller = $this->re("#\n\s*Passenger name:\s*(.+)\s+#", $text);
        $serviceName = preg_replace("#\s+#", ' ', $this->re("#\n\s*Service description:\s*(.+?)\n\s*Occupancy:#s", $text));
        $occupancy = $this->re("#\n\s*Occupancy:\s*(.+)\s+#", $text);
        $dates = $this->re("#\n\s*Booking dates:\s*(.+)\s+#", $text);

        $cancellation = preg_replace("#\s+#", ' ', trim($this->re("#\n\s*CANCELLATION CHARGES\s*\n\s*([\s\S]+?)\n\s*\n#", $text)));

        if (preg_match("#^\s*(.+ - .+, .+) \((.+ - .+)\)\s*$#", $serviceName, $m)) {
            // Transfer
            $it = $email->add()->transfer();

            $s = $it->addSegment();

            $routes = explode(" - ", $m[2]);

            if (count($routes) == 2) {
                $s->departure()->name($routes[0]);
                $s->arrival()->name($routes[1]);
            } elseif (preg_match("#(.+ Airport) - (.+)#", $m[2], $mat)) {
                $s->departure()->name($mat[1]);
                $s->arrival()->name($mat[2]);
            }

            $s->departure()->noDate();
            $s->arrival()->noDate();

            $s->extra()->type(trim($m[1]));
        } elseif (preg_match("#^\s*\d+ x \d+.+#", $occupancy, $mat)) {
            // Hotel
            $it = $email->add()->hotel();

            if (preg_match("#^\s*(.+) / \\1\s*$#", $serviceName, $m)) {
                $it->hotel()->name($m[1]);
            } else {
                $it->hotel()->name($serviceName);
            }
            $it->hotel()->noAddress();

            $times = explode(" - ", $dates);

            if (count($times) == 2) {
                $it->booked()->checkIn($this->normalizeDate($times[0]));
                $it->booked()->checkOut($this->normalizeDate($times[1]));
            }

            $it->booked()
                ->rooms($this->re("#^\s*(\d+) x #", $occupancy))
                ->guests($this->re("#\b(\d+) Adult#", $occupancy))
                ->kids($this->re("#\b(\d+) Child#", $occupancy), true, true)
            ;
        } else {
            // Event
            $it = $email->add()->event();

            $it->place()
                ->name($serviceName)
                ->address('???')
                ->type(\AwardWallet\Schema\Parser\Common\Event::TYPE_EVENT);

            $times = explode(" - ", $dates);

            if (count($times) == 2) {
                $it->booked()->start($this->normalizeDate($times[0]));
                $it->booked()->noEnd();
            }

            $it->booked()
                ->guests($this->re("#\b(\d+) Adult#", $occupancy))
            ;
        }

        $it->general()
            ->confirmation($this->re("#Booking Number:[ ]*(\d+)#", $text), 'Booking Number', true)
            ->traveller($traveller)
            ->cancellation($cancellation)
        ;
        $confNo = str_replace(' ', '', $this->re("#Your reference number:[ ]*([\w ]+)#", $text));

        if (!in_array($confNo, ['RE', 'AR'])) {
            $it->general()->confirmation($confNo, 'Your reference number');
        }

        if ($it->getType() === 'event') {
            // no address event -> junk
            $email->removeItinerary($it);
            $email->setIsJunk(true);
        } else {
            // Travel Agency
            $email->ota()
                ->confirmation($this->re("#Modification request receipt for booking\s*([\d\-]{6,})\s+#", $text), 'Modification request receipt');
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

    private function checkFormat($body)
    {
        // detect lang at first
        if (!$this->assignLang()) {
            return false;
        }
        $nodes = $this->http->XPath->query("//pre");

        if ($nodes->length == 1 && ($text = $this->re("#<pre>(.+)</pre>#s", $body))) {
            $this->text = $text;
        }

        if (empty($this->text) && $nodes->length > 1) {
            $this->text = trim(implode("\n", $this->http->FindNodes("//pre")));
        }

        if (empty($this->text)) {
            return false;
        }

        $condition1 = preg_match("#Modification request receipt for booking[ ]+\d+\-\d+\n\s*Thank you for trusting in us\.\nYour modification has been done successfully#",
                $this->text) > 0;
        $condition2 = preg_match("#{$this->opt($this->t('Passenger name:'))}[^\n]+\n[ \t]*Service description:[^\n]+\n[ \t]*Occupancy:#",
                $this->text) > 0;
        $condition3 = preg_match("#Occupancy:[^\n]+\s+Booking dates:[ ]+\d+\/\w+\/\d{4}[ ]+\-[ ]+\d+\/\w+\/\d{4}[ ]*\n\s*CANCELLATION CHARGES#",
                $this->text) > 0;
        $condition4 = preg_match("#(Depart|Location)#i", $this->text) === 0; //just control guess

        if ($condition1 && $condition2 && $condition3 && $condition4) {
            return true;
        }

        return false;
    }

    private function assignLang()
    {
        $body = $this->http->Response['body'];

        foreach (self::$dict as $lang => $words) {
            if (isset($words['Passenger name:'], $words['Service description:'])) {
                if ($this->stripos($body, $words['Passenger name:'])
                    && $this->stripos($body, $words['Service description:'])
                ) {
                    $this->lang = $lang;

                    return true;
                }
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function stripos($haystack, $arrayNeedle)
    {
        $arrayNeedle = (array) $arrayNeedle;

        foreach ($arrayNeedle as $needle) {
            if (stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{2})/([^\W\d]+)/(\d{4})\s*$#", // 14/Oct/2019
            "#^\s*(\d{2})/(\d{2})/(\d{4})\s*$#", // 04/13/2019
            "#^\s*(\d{2})/(\d{2})/(\d{2})\s*$#", // 04/13/19
        ];
        $out = [
            '$1 $2 $3',
            '$2.$1.$3',
            '$2.$1.20$3',
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
