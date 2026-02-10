<?php

namespace AwardWallet\Engine\aegean\Email;

use AwardWallet\Schema\Parser\Email\Email;

class AirTicket extends \TAccountChecker
{
    public $mailFiles = "aegean/it-44978207.eml, aegean/it-5345391.eml, aegean/it-5406524.eml";

    public $reFrom = ["aegeanair.com"];
    public $reBody = [
        'en' => ['Flight notification message', 'We apologize for any inconvenience we might have caused you'],
        'el' => ['Μήνυμα αλλαγής πτήσης', 'Λυπούμαστε ειλικρινά για οποιαδήποτε ταλαιπωρία σας έχουμε'],
    ];
    public $reSubject = [
        'AEGEAN AIRLINES NOTIFICATION',
        'AEGEAN AIRLINES SCHEDULE CHANGE NOTIFICATION',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Flight' => 'Flight',
            'Date'   => 'Date',
        ],
        'el' => [
            'Booking reference' => 'Κωδικός κράτησης',
            'Passengers'        => 'Επιβάτες',
            'Ticket number'     => 'Aριθμός εισιτηρίου',
            'Flight'            => 'Πτήση',
            'Date'              => 'Ημερομηνία',
        ],
    ];
    private $keywordProv = 'Aegean';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'.aegeanair.com')] | //a[contains(@href,'.aegeanair.com')]")->length > 0) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    return $this->assignLang();
                }
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
                if (($fromProv || preg_match("#\b{$this->opt($this->keywordProv)}\b#i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
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

    private function parseEmail(Email $email): bool
    {
        $r = $email->add()->flight();
        $r->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]/following::text()[normalize-space()!=''][1]"))
            ->travellers($this->http->FindNodes("//tr[({$this->eq($this->t('Passengers'))}) and not(.//tr)]/following-sibling::tr[1]/td[1]/descendant::text()[normalize-space(.)!='']"));

        $tickets = $this->http->FindNodes("//tr[({$this->eq($this->t('Passengers'))}) and not(.//tr)]/following-sibling::tr[1]/td[2]/descendant::text()[normalize-space(.)!='']",
            null, "/{$this->opt($this->t('Ticket number'))}\s+([\d\-]+)/");

        if (!empty($tickets)) {
            $r->issued()->tickets($tickets, false);
        }

        if ($this->http->XPath->query("//span[contains(normalize-space(text()), 'CANCELLED')]")->length > 0) {
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        }

        $xpath = "//tr[count(td)>=4 and ({$this->contains($this->t('Flight'))}) and ({$this->contains($this->t('Date'))})]/following-sibling::tr[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->info('Segments not found: ' . $xpath);

            return false;
        }

        foreach ($roots as $root) {
            $s = $r->addSegment();

            $airNameFNum = $this->http->FindSingleNode('td[1]', $root);

            if (preg_match('/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s+(\d+)/', $airNameFNum, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (preg_match('/(\d+)\/(\d+)\/(\d{4})/', $this->http->FindSingleNode('td[2]', $root), $m)) {
                $date = $m[2] . '/' . $m[1] . '/' . $m[3];
            }

            if (!empty($date) && ($depTime = $this->http->FindSingleNode('td[3]',
                    $root)) && ($arrTime = $this->http->FindSingleNode('td[4]', $root))
            ) {
                $date = strtotime($date);
                $s->departure()->date(strtotime($depTime, $date));
                $s->arrival()->date(strtotime($arrTime, $date));

                if ($s->getDepDate() && $s->getArrDate()
                    && $s->getDepDate() > $s->getArrDate()
                ) {
                    $s->arrival()->date(strtotime("+ 1 day", $s->getArrDate()));
                }
            }

            $flights = $this->http->FindSingleNode('preceding-sibling::tr[2]', $root);

            if (preg_match('/(.+)\s+-\s+(.+)/', $flights, $m)) {
                $s->departure()
                    ->noCode()
                    ->name($m[1]);
                $s->arrival()
                    ->noCode()
                    ->name($m[2]);
            }
        }

        return true;
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['Flight'], $words['Date'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['Flight'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['Date'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
