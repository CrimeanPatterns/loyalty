<?php

namespace AwardWallet\Engine\fseasons\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "fseasons/it-49005507.eml, fseasons/it-53929814.eml, fseasons/it-629141354.eml, fseasons/it-69430055.eml, fseasons/it-69473145.eml";

    public $reFrom = ["@fourseasons.com"];
    public $reBody = [
        'en'  => ['We are pleased to confirm the following reservation and look', 'RESERVATION CONFIRMATION'],
        'en2' => ['We understand plans change', 'RESERVATION CANCELLATION'],
        'es'  => ['Nos complace confirmar la siguiente reserva', 'CONFIRMACIÓN DE RESERVA'],
    ];
    public $reSubject = [
        'Confirmation - ',
        'Cancellation - Four Seasons',
        'Confirmación - Four Seasons',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'CONFIRMATION'              => ['CONFIRMATION', 'CANCELLED'],
            'STATUS'                    => ['STATUS:', 'STATUS :'],
            'ARRIVAL:'                  => ['ARRIVAL:', 'Arrival:'],
            'DEPARTURE:'                => ['DEPARTURE:', 'Departure:'],
            'nights for'                => ['nights for', 'night for', 'nights'],
            'child'                     => ['kids', 'child'],
            'adults'                    => ['adults', 'adult', 'Guests', 'Guest'],
            'Average daily rate'        => ['Average Rate', 'Average daily rate'],
            'YOUR RATE PACKAGE DETAILS' => ['YOUR RATE PACKAGE DETAILS', 'Your Rate Package Details'],
            'Room Charge'               => ['Room Charge', 'Room charge'],
            'hotelContacts'             => ['TEL:', 'PHONE:', 'E-MAIL:', 'EMAIL:', 'WEB:'],
            'TEL:'                      => ['TEL:', 'PHONE:'],
            'Hotel Check In:'           => ['Hotel Check In:', 'Hotel check in:'],
            'Hotel Check Out:'          => ['Hotel Check Out:', 'Hotel check out:'],
            'Dear'                      => ['Dear', 'Aloha'],
        ],
        'es' => [ // it-69430055.eml
            'CONFIRMATION'       => 'CONFIRMACIÓN #',
            'STATUS'             => 'ESTADO :',
            'ARRIVAL:'           => 'LLEGADA:',
            'DEPARTURE:'         => 'SALIDA:',
            'nights for'         => 'noches para',
            'adults'             => 'Huéspedes',
            'Average daily rate' => 'Tarifa media diaria',
            //'YOUR RATE PACKAGE DETAILS' => ['YOUR RATE PACKAGE DETAILS', 'Your Rate Package Details'],
            'Room Charge'      => 'Precio de la habitación',
            'hotelContacts'    => ['TEL:', 'E-MAIL:'],
            'TEL:'             => ['TEL:', 'PHONE:'],
            'Hotel Check In:'  => 'Check In Hotel:',
            'Hotel Check Out:' => 'Check Out Hotel:',
            'Dear'             => 'Apreciado/a',
        ],
    ];

    public function cleanString($string)
    {
        $search = ['&#8234;', '&lrm;', '&#8236;'];
        $replace = ['', '', ''];

        return str_replace($search, $replace, $string);
    }

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
        if ($this->http->XPath->query("//img[starts-with(@alt,'Four Seasons') or contains(@src,'.fourseasons.com')] | //a[contains(@href, '.fourseasons.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
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
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Four Seasons') === false) {
            return false;
        }

        foreach ($this->reSubject as $reSubject) {
            if (strpos($headers['subject'], $reSubject) !== false) {
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

    private function parseEmail(Email $email)
    {
        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        $xpath = "//text()[{$this->eq($this->t('ARRIVAL:'))} or {$this->starts($this->t('STATUS'))}]/ancestor::*[count(.//text()[{$this->eq($this->t('ARRIVAL:'))}]) = 1 or count(.//text()[{$this->starts($this->t('Dear'))}]) = 1][last()]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $hroot) {
            $r = $email->add()->hotel();

            $confirmation = $this->http->FindSingleNode("(.//text()[{$this->starts($this->t('CONFIRMATION'))}])[1]", $hroot);

            if (preg_match("/({$this->opt($this->t('CONFIRMATION'))}[ ]*#?)\s*(\b[-A-Z\d]{5,})/", $confirmation, $m)) {
                $r->general()->confirmation($m[2], $m[1]);
            }

            $status = $this->http->FindSingleNode("(.//text()[{$this->starts($this->t('STATUS'))}])[1]", $hroot, false,
                "/{$this->opt($this->t('STATUS'))}[:\s]*(.+)/");

            if (!empty($status)) {
                $r->general()
                    ->status($status);
            }

            if ($r->getStatus() == 'CANCELLED') {
                $r->general()
                    ->cancelled();

                $cancellationNumber = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(), 'CANCELLATION')]",
                    $hroot, true, "/^{$this->opt($this->t('CANCELLATION'))}\s*(\d{5,})/");

                if (!empty($cancellationNumber)) {
                    $r->general()
                        ->cancellationNumber($cancellationNumber)
                        ->confirmation($cancellationNumber);
                }
            }

            $traveller = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Dear'))}]", $hroot, false,
                "#{$this->opt($this->t('Dear'))}\s+(.+?)(?:,|$)#");

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Dear M'))}]", $hroot, false,
                    "#{$this->opt($this->t('Dear'))}\s+(.+?)(?:,|$)#");
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode(".//text()[normalize-space()='RESERVATION CONFIRMATION']/following::text()[starts-with(normalize-space(), 'Dear')][1]",
                    $hroot, false, "#{$this->opt($this->t('Dear'))}\s+(.+?)(?:,|$)#");
            }

            if (empty($traveller)) {
                $traveller = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Dear'))}][following::text()[normalize-space()][2][normalize-space()=',']]/following::text()[normalize-space()][1]",
                    $hroot, false, "#^\s*[[:alpha:] \-]{2,25}\s*$#");
            }

            if (preg_match("/^\s*(?:Mr\. and Mrs|Mr\.|Mr|Mrs|Ms)[.]?\D{2,}\s+(and\s*(?:Mr\.|Mrs\.))/", $traveller, $m)) {
                $traveller = preg_replace("/^\s*(Mr\. and Mrs|Mr\.|Mr|Mrs|Ms)[.]? /", "", $traveller);
                $delimiter = $m[1];
                $r->general()
                    ->travellers(explode($delimiter, $traveller));
            } else {
                $r->general()
                    // Dear Mr. and Mrs. Joshua Greer
                    ->traveller(preg_replace("/^\s*(Mr\. and Mrs|Mr\.|Mr|Mrs|Ms)[.]? /", "", $traveller));
            }

            $cancellation = null;
            $cancellationTexts = $this->http->FindNodes(".//*[{$this->eq($this->t('GUARANTEE, DEPOSIT AND CANCELLATION POLICIES'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()][not(ancestor::*[{$xpathBold}])]", $hroot);

            foreach ($cancellationTexts as $cText) {
                $cText = trim($cText, '† ');

                if (substr($cText, 0, 1) === '*') {
                    break;
                }

                if (stripos($cText, 'cancel') !== false || stripos($cText, 'refund') !== false) {
                    $cancellation .= empty($cancellation) ? $cText : ' ' . $cText;
                }
            }

            if (empty($cancellation)) {
                $cancellation = $this->http->FindSingleNode(".//text()[{$this->contains($this->t('hours prior to'))}]",
                    $hroot, true, '/^(.+\s+arrival\,)/');
            }

            if (strlen($cancellation) >= 2000) {
                $cancellation = $this->re("/(All cancellations and changes must be received.+The same penalty applies to no\-shows and early departures)/",
                    $cancellation);
            }
            $r->general()->cancellation($cancellation, false, true);

            $hotelInfo = null;
            $hotelName = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Dear'))}]/preceding::table[normalize-space()][1][{$this->contains($this->t('hotelContacts'))}]/preceding::text()[normalize-space()][1]", $hroot);

            if ($hotelName) {
                $hotelInfo = implode("\n",
                    $this->http->FindNodes(".//text()[{$this->starts($this->t('Dear'))}]/preceding::table[normalize-space()][1][{$this->contains($this->t('hotelContacts'))}]/descendant::text()[normalize-space()][not({$this->contains($hotelName)})]", $hroot));
            }
            $hotelInfo = preg_replace('/(\x{200e}|\x{200f})/u', '', $hotelInfo);

            // +255 (0) 768 982 101/2
            $patterns['phone'] = '[+(\d][\-. \d)(/]{5,}[\d)]';
            $address = preg_replace("/\s+/", ' ',
                $this->re("/^(.{3,}?)\s+{$this->opt($this->t('hotelContacts'))}/s", $hotelInfo));

            if (empty($address)) {
                $hotelInfo = implode("\n",
                    $this->http->FindNodes(".//text()[{$this->starts($this->t('Dear'))}]/preceding::table[normalize-space()][1][{$this->contains($this->t('hotelContacts'))}]/descendant::text()[normalize-space()][not({$this->contains($hotelName)})]/ancestor::table[1]", $hroot));
                $address = preg_replace("/\s+/", ' ',
                    $this->re("/^(.{3,}?)\s+{$this->opt($this->t('hotelContacts'))}/s", $hotelInfo));
            }

            $r->hotel()
                ->name($hotelName)
                ->address($address)
                ->phone($this->re("#{$this->opt($this->t('TEL:'))}[\s\W]*?({$patterns['phone']})[ ]*#m", $hotelInfo),
                    false, true)
                ->fax($this->re("#{$this->opt($this->t('FAX:'))}[\s\W]*?({$patterns['phone']})[ ]*$#m", $hotelInfo),
                    false, true);

            $rooms = $this->http->XPath->query(".//img[contains(@src,'9ea39d1.png')]/ancestor::td[1]", $hroot);

            foreach ($rooms as $root) {
                $room = $r->addRoom();
                $xpathRoomDesc = "following-sibling::td[1]//td/descendant-or-self::*[count(p[normalize-space()])=2 or count(div[normalize-space()])=2]";
                $roomSetType = $this->http->FindSingleNode($xpathRoomDesc . '/*[1]', $root);
                $roomSetDescription = $this->http->FindSingleNode($xpathRoomDesc . '/*[2]', $root);

                if (!empty($roomSetType) && !empty($roomSetDescription)) {
                    $room
                        ->setType($roomSetType)
                        ->setDescription($roomSetDescription);
                } else {
                    $roomText = $this->http->FindSingleNode('following-sibling::td[1]/descendant::text()', $root);

                    if (preg_match("/^(.+)\((.+)\)$/", $roomText, $m)) {
                        $room
                            ->setType($m[1])
                            ->setDescription($m[2]);
                    }
                }

                if ($rooms->length > 1) {
                    $rate = $this->http->FindSingleNode("following::text()[{$this->eq($this->t('Average daily rate'))}][1]/ancestor::td/following-sibling::td[1]",
                        $root);

                    if (!empty($rate)) {
                        $room->setRate($rate);
                    }
                } else {
                    $rate = implode(' ',
                        $this->http->FindNodes(".//text()[{$this->eq($this->t('Average daily rate'))}]/ancestor::tr[not({$this->eq($this->t('Average daily rate'))})][1]/descendant::text()[normalize-space()]", $hroot));

                    if (!empty($rate)) {
                        $room->setRate($rate);
                    }

                    $rateType = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('YOUR RATE PACKAGE DETAILS'))}]/following::*[normalize-space()!=''][1]", $hroot);

                    if (!preg_match("/(?:Chat|To help you plan)/", $rateType)) {
                        $room->setRateType($rateType, false, true);
                    }
                }
            }

            $adults = array_filter($this->http->FindNodes(".//text()[{$this->contains($this->t('nights for'))}]", $hroot,
                "/\b(\d{1,3})\s*{$this->opt($this->t('adults'))}/"));

            if (count($adults)) {
                $r->booked()->guests(array_sum($adults));
            }

            $kids = array_filter($this->http->FindNodes(".//text()[{$this->contains($this->t('nights for'))}]", $hroot,
                "/\b(\d{1,3})\s*{$this->opt($this->t('child'))}/"));

            if (count($kids)) {
                $r->booked()->kids(array_sum($kids));
            }

            $checkIn = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('ARRIVAL:'))}]/following::text()[normalize-space()!=''][1]", $hroot));

            if (!empty($checkIn)) {
                $r->booked()
                    ->checkIn($checkIn);
            }

            $checkOut = $this->normalizeDate($this->http->FindSingleNode(".//text()[{$this->eq($this->t('DEPARTURE:'))}]/following::text()[normalize-space()!=''][1]", $hroot));

            if (!empty($checkOut)) {
                $r->booked()
                    ->checkOut($checkOut);
            }

            $timeIn = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Hotel Check In:'))}]", $hroot, false,
                "/{$this->opt($this->t('Hotel Check In:'))}\s*(.+)/");

            if (!empty($timeIn) && $r->getCheckInDate()) {
                $r->booked()->checkIn(strtotime($timeIn, $r->getCheckInDate()));
            }

            $timeOut = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Hotel Check Out:'))}]", $hroot,
                false,
                "/{$this->opt($this->t('Hotel Check Out:'))}\s*(.+)/");

            if (!empty($timeOut) && $r->getCheckOutDate()) {
                $r->booked()->checkOut(strtotime($timeOut, $r->getCheckOutDate()));
            }
            $cost = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('Room Charge'))}]/ancestor::td[1]/following-sibling::td[1]", $hroot);
            $cost = $this->getTotalCurrency($cost);

            if (!empty($cost['currency'])) {
                $r->price()
                    ->cost($cost['total'])
                    ->currency($cost['currency']);
            }

            $this->detectDeadLine($r);
        }

        return true;
    }

    /**
     * Here we describe various variations in the definition of dates deadLine.
     */
    private function detectDeadLine(\AwardWallet\Schema\Parser\Common\Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        $patterns['time'] = '\d{1,2}(?:[:]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        if (preg_match("/All cancellations must be received by (?<time>{$patterns['time']}) .+? time at least (?<days>\d{1,3}) days? prior to guest's expected arrival date/i",
                $cancellationText, $m)
            || preg_match("/All cancellations must be received by (?<time>{$patterns['time']}) .+? time at least (?<days>\w+) days? prior to expected arrival/i",
                $cancellationText, $m)
            || preg_match("/Cancellations must be received by (?<time>{$patterns['time']}) .+? time at least (?<days>\d{1,3}) days? prior to expected arrival,/i",
                $cancellationText, $m)
            || preg_match("/All cancellations and changes must be received by (?<time>{$patterns['time']}) .+? time at least (?<days>\d{1,3}) days prior to expected arrival/i",
                $cancellationText, $m)
        ) {
            if ($m['days'] === 'one') {
                $m['days'] = 1;
            } elseif ($m['days'] === 'two') {
                $m['days'] = 2;
            } elseif ($m['days'] === 'three') {
                $m['days'] = 3;
            } elseif ($m['days'] === 'four') {
                $m['days'] = 4;
            } elseif ($m['days'] === 'five') {
                $m['days'] = 5;
            } elseif ($m['days'] === 'six') {
                $m['days'] = 6;
            } elseif ($m['days'] === 'seven') {
                $m['days'] = 7;
            }
            $h->booked()
                ->deadlineRelative($m['days'] . ' days', $m['time']);
        }
        // Cancellations must be received at least 61 days prior to the scheduled arrival date, or a cancellation charge will apply
        elseif (preg_match("/Cancellations must be received at least (?<days>\d+) days? prior to the scheduled arrival date, or a cancellation charge will apply/i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative($m['days'] . ' days');
        } elseif (preg_match("/All cancellations and changes must be received at least seven days prior to (?<time>{$patterns['time']}) .+? time on the expected arrival date./i", $cancellationText, $m)) {
            $h->booked()
                ->deadlineRelative('0 days', $m['time']);
        } elseif (preg_match("/Cancellations or changes must be received by (?<time>{$patterns['time']}) .+? time at least (?<hours>\d{1,3}) hours? prior to expected arrival,/i", $cancellationText, $m)
            || preg_match("/All cancellations and changes must be received by (?<time>{$patterns['time']}) .+? Time at least (?<hours>\d{1,3}) hours? prior to arrival,/i",
            $cancellationText, $m)
            || preg_match("/All cancellations and changes must be received by (?<time>{$patterns['time']}) .+? time at least (?<hours>\d{1,3}) hours? prior to expected arrival,/i",
                $cancellationText, $m)
            || preg_match("/All cancellations must be received by (?<time>{$patterns['time']})\sMexico City time at least (?<hours>\d{1,3}) hours prior to the day of expected arrival/i",
                $cancellationText, $m)
            || preg_match("/All cancellations must be received by (?<time>{$patterns['time']}) .+? time at least (?<hours>\d{1,3}) hours prior to expected date of arrival/iu",
                $cancellationText, $m)
            || preg_match("/All cancellations and changes must be received at least\s*(?<hours>\d+)\s*hours\s*prior to\s*(?<time>{$patterns['time']})\s*/i",
                $cancellationText, $m)
        ) {
            $h->booked()->deadlineRelative($m['hours'] . ' hours', $m['time']);
        }
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang(): bool
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['CONFIRMATION'], $words['ARRIVAL:'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['CONFIRMATION'])}]")->length > 0
                    && ($this->http->XPath->query("//*[{$this->contains($words['ARRIVAL:'])}]")->length > 0
                        || $this->http->XPath->query("//*[{$this->contains($words['STATUS'])}]")->length > 0)
                ) {
                    $this->lang = substr($lang, 0, 2);

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node): array
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = '';
        $cur = '';

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::cost($m['t']);
        }

        return ['total' => $tot, 'currency' => $cur];
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
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

    private function normalizeDate($str)
    {
        //$this->logger->warning($str);
        $in = [
            "#^\w+\,\s*(\w+)\s+(\d+)\s+(\d{4})$#u", //viernes, noviembre 27 2020
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
