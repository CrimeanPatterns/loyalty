<?php

namespace AwardWallet\Engine\classpass\Email;

use AwardWallet\Schema\Parser\Common\Event;
use AwardWallet\Schema\Parser\Email\Email;

class Reservation extends \TAccountChecker
{
    public $mailFiles = "classpass/it-135177073.eml, classpass/it-135341504-junk.eml, classpass/it-135942963.eml, classpass/it-136103807.eml, classpass/it-652476739.eml, classpass/it-658496772-junk.eml, classpass/it-658576543-junk.eml, classpass/it-658634778-junk.eml, classpass/it-658879813-junk.eml, classpass/it-658899597-junk.eml, classpass/it-659065922-junk.eml";

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'cancellation'      => ['Cancellation policy:', 'Cancellation policy'],
            'cancellationStart' => ["Change in plans? Don't sweat it."],
            'cancellationEnd'   => ['Learn more about our Reservation cancellation policy', 'Learn more about our Cancellation policy'],
        ],
    ];

    private $detectSubject = [
        // en
        'Your reservation at ', 'Your credit purchase is confirmed',
    ];

    private $format = '';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '.classpass.com') !== false || stripos($from, '@classpass.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (preg_match("/(?:^|\W)(?:Your )?ClassPass (?:Cancell?ation Confirmation|Gift Purchase Confirmation|reservation at CycleBar|Membership Change Is Confirmed|reservation .{6,} was cancell?ed)[.!\s]*$/i", $headers['subject']) // it-658576543-junk.eml
            || preg_match("/(?:^|\W)Your ClassPass Concierge reservation is confirmed[.!\s]*$/i", $headers['subject']) // it-652476739.eml
        ) {
            return true;
        }

        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.classpass.com/', 'email.classpass.com'], '@href')}]")->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"©") and contains(.,"ClassPass")]')->length === 0
            && $this->http->XPath->query('//*[contains(normalize-space(),"Stay healthy, The ClassPass Team") or contains(normalize-space(),"Sincerely, The ClassPass Team")]')->length === 0
        ) {
            return false;
        }

        return $this->findRoot()->length > 0 || $this->isJunk();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->lang = 'en';
        $email->setType('Reservation' . ucfirst($this->lang));
        $this->parseEmailHtml($email);

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

    private function isJunk(): bool
    {
        if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'We are reaching out because') and contains(normalize-space(),\"let us know that you weren't there for class on\")]")->length > 0) {
            // it-135341504-junk.eml
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->starts(['Your ClassPass membership was successfully cancelled', 'Your ClassPass membership was successfully canceled'])}]")->length > 0) {
            // it-658576543-junk.eml
            return true;
        }

        if ($this->http->XPath->query("//text()[{$this->starts(['has cancelled the', 'has canceled the'])}]/following::a[normalize-space()='Rebook now']")->length > 0) {
            // it-658899597-junk.eml
            return true;
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'You missed a reservation at')]/following::text()[contains(normalize-space(),'have been refunded')]")->length > 0) {
            // it-658879813-junk.eml
            return true;
        }

        if ($this->http->XPath->query("//*[normalize-space()='Your switch is set']/following::tr[ count(*)>1 and *[normalize-space()][1][translate(.,': ','')='Membershipplan'] ]")->length > 0
            || $this->http->XPath->query("//*[normalize-space()='Your credits have been added']/following::tr[ count(*)>1 and *[normalize-space()][1][translate(.,'0123456789: ','')='credits'] ]")->length > 0
        ) {
            // it-659065922-junk.eml  |  it-658496772-junk.eml
            return true;
        }

        if ($this->http->XPath->query("//text()[starts-with(normalize-space(),'Your gift to')]/following::tr[ count(*)>1 and *[normalize-space()][1][translate(.,': ','')='Giftingcode'] ]")->length > 0) {
            // it-658634778-junk.eml
            return true;
        }

        return false;
    }

    private function findRoot(): \DOMNodeList
    {
        $xpathDateTime = "contains(translate(.,'0123456789: ','∆∆∆∆∆∆∆∆∆∆'), '∆∆@∆∆∆')"; // January 19, 2022 @ 6:00 AM PST

        // it-652476739.eml
        $nodes = $this->http->XPath->query("//*[ {$xpathDateTime} and following-sibling::*[normalize-space()][4][starts-with(normalize-space(),'How to prep')] ]");
        $this->format = 'one column';

        if ($nodes->length === 0) {
            // it-135177073.eml
            $nodes = $this->http->XPath->query("//*[not(.//tr or .//div) and {$xpathDateTime}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/parent::*[count(*[normalize-space()])=2]");
            $this->format = 'two columns';
        }

        return $nodes;
    }

    private function parseEmailHtml(Email $email): void
    {
        $roots = $this->findRoot();

        if ($roots->length !== 1) {
            $this->logger->debug('Root-node not found!');

            if ($roots->length === 0 && $this->isJunk()) {
                $email->setIsJunk(true);
            }

            return;
        }
        $root = $roots->item(0);

        $event = $email->add()->event();
        $event->type()->meeting();

        $event->general()
            ->noConfirmation();

        if ($this->format === 'one column') {
            $this->parseOneColumn($event, $root);
        } elseif ($this->format === 'two columns') {
            $this->parseTwoColumns($event, $root);
        }

        $cancellation = $this->http->FindSingleNode("//tr[ descendant::text()[normalize-space()][1][{$this->eq($this->t('cancellation'))}] or {$this->starts($this->t("cancellationStart"))} ][ preceding-sibling::tr[normalize-space()] ]", null, true, "/^(?:{$this->opt($this->t('cancellation'))}[:\s]*)?(?:{$this->opt($this->t("cancellationStart"))}\s+)?(.{5,}?)(?:\s+{$this->opt($this->t('cancellationEnd'))}[\s.]*)?$/i");
        $event->general()->cancellation($cancellation, false, true);
    }

    private function parseOneColumn(Event $event, \DOMNode $root): void
    {
        $event->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode('.', $root)))
            ->noEnd()
        ;

        $places = $this->http->FindNodes("following-sibling::*[ normalize-space() and following-sibling::*[starts-with(normalize-space(),'How to prep')] ]", $root);

        if (count($places) !== 3) {
            return;
        }

        $eventName = array_shift($places);
        $address = implode(', ', $places);
        $event->place()->name($eventName)->address($address);
    }

    private function parseTwoColumns(Event $event, \DOMNode $root): void
    {
        $event->booked()
            ->start($this->normalizeDate($this->http->FindSingleNode("*[normalize-space()][1]", $root)))
            ->noEnd()
        ;

        $places = $this->http->FindNodes("*[normalize-space()][2]/descendant::a[normalize-space()]", $root);
        $eventName = count($places) > 0 ? $places[0] : null;
        $address = null;

        if (count($places) === 4 || count($places) === 3) {
            $address = $places[1] . ', ' . implode(', ', $this->http->FindNodes("*[normalize-space()][2]/descendant::a[normalize-space()][3]/descendant::text()[normalize-space()]", $root));
        } else {
            $url = $this->http->FindSingleNode("//a[contains(normalize-space(),'click here to see the updated location')]/@href");

            if (!empty($url)) {
                $http2 = clone $this->http;
                $http2->setMaxRedirects(0);
                $http2->GetURL($url);

                if (!empty($http2->Response['headers']['location'])
                    && preg_match("/google\.com\\/maps\b.*?query=(-?[\d.]+,-?[\d.]+)/", $http2->Response['headers']['location'], $m)
                ) {
                    $address = $m[1];
                }
            }
        }

        $event->place()->name($eventName)->address($address);
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
//        $this->logger->debug('date begin = ' . print_r( $date, true));
        if (empty($date)) {
            return null;
        }

        $in = [
            // October 22, 2021 @ 3:00 PM PDT
            '/^\s*([[:alpha:]]+)\s+(\d{1,2})[,\s]+(\d{4})\s*@\s*(\d{1,2}:\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?)(?:\s+[A-z]{3,4})?\s*$/u',
        ];
        $out = [
            '$2 $1 $3, $4',
        ];

        $date = preg_replace($in, $out, $date);

        return strtotime($date);
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
