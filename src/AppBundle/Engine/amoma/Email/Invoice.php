<?php

namespace AwardWallet\Engine\amoma\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class Invoice extends \TAccountChecker
{
    public $mailFiles = "amoma/it-42294673.eml, amoma/it-42644918.eml";
    public $reFrom = ["@amoma.com"];
    public $reBody = [
        'en' => ['Invoice details:', 'Invoice n°:'],
        'fr' => ['Détails de la facture:', 'Facture n°:'],
    ];
    public $reSubject = [
        '#Invoice for your hotel booking n°\s+\d+#',
        "#Facture pour votre réservation d'hôtel n°\s+\d+#",
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'date'         => 'Date:',
            'confirmation' => 'Booking n°:',
            'traveller'    => 'Name:',
            'hotelName'    => 'Hotel:',
            'hotelAddress' => 'Invoice n°:',
            'total'        => 'Total price',
            'rate'         => 'Price per night',
            'cost'         => 'Total without taxes',
            'tax'          => 'Taxes and fees',
        ],
        'fr' => [
            'date'         => 'Date:',
            'confirmation' => 'Numéro de réservation:',
            'traveller'    => 'Nom:',
            'hotelName'    => 'Hôtel:',
            'hotelAddress' => 'Facture n°:',
            'total'        => 'Prix total',
            'rate'         => 'Prix par nuit',
            //'cost' => '',
            //'tax' => '',
        ],
    ];

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
        if ($this->http->XPath->query("//text()[contains(., 'AMOMA SARL')]")->length > 0
            && $this->detectBody()
        ) {
            return $this->assignLang();
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
        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (stripos($headers["subject"], 'AMOMA') !== false && preg_match($reSubject, $headers["subject"]) > 0) {
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

    private function parseEmail(Email $email)
    {
        $h = $email->add()->hotel();

        $h->general()
            ->date2($this->dateStringToEnglish($this->http->FindSingleNode("//text()[{$this->contains($this->t('date'))}]/following-sibling::strong")))
            ->confirmation($this->http->FindSingleNode("//text()[{$this->contains($this->t('confirmation'))}]/following-sibling::strong"))
            ->traveller($this->http->FindSingleNode("//text()[{$this->contains($this->t('traveller'))}]/following-sibling::strong[1]"));

        $h->hotel()->name($this->http->FindSingleNode("//td/text()[{$this->contains($this->t('hotelName'))}]", null, false, '/:\s+(.+)/'));

        // Address
        $arr = $this->http->FindNodes("//td[{$this->contains($this->t('hotelAddress'), 'text()')}]/preceding-sibling::td[1]//text()");
        $address = join(' ', array_filter($arr, function ($v, $k) {
            return stripos($v, '@') === false;
        }, ARRAY_FILTER_USE_BOTH));

        if (!empty($address)) {
            $h->hotel()->address($address);
        }

        // Date
        $date = $this->http->FindSingleNode("//td/text()[{$this->contains($this->t('hotelName'))}]/following-sibling::text()", null, false, '/(?:From|De).+/');
        $h->booked()
            // De lundi 7 janvier 2019 à mardi 8 janvier 2019 - 1 nuit(s)
            ->checkIn($this->normalizeDate($this->http->FindPreg('/(?:From|De) (.+?) (?:to|à) /', false, $date)))
            ->checkOut($this->normalizeDate($this->http->FindPreg('/ (?:to|à) (.+?) - /', false, $date)));

        // Price
        if ($price = $this->http->FindSingleNode("//text()[{$this->contains($this->t('total'))}]/ancestor::td[1]/following-sibling::td")) {
            $total = $this->getTotalCurrency($price);
            $h->price()
                ->total($total['Total'])
                ->currency($total['Currency']);
        }
        $h->price()->cost($this->http->FindSingleNode("//text()[{$this->contains($this->t('cost'))}]/ancestor::td[1]/following-sibling::td", null, false, '/[\d.,]+/'), false, true);

        if ($tax = $this->http->FindSingleNode("//text()[{$this->contains($this->t('tax'))}]/ancestor::td[1]/following-sibling::td", null, false, '/[\d.,]+/')) {
            $h->price()->tax($tax);
        }

        if ($rate = $this->http->FindSingleNode("//text()[{$this->contains($this->t('rate'))}]/ancestor::td[1]/following-sibling::td")) {
            $r = $h->addRoom();
            $r->setRate($rate);
        }

        return true;
    }

    private function getTotalCurrency($node)
    {
        $node = str_replace(["€", "£", "$"], ["EUR", "GBP", "USD"], $node);
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

    private function detectBody()
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang()
    {
        foreach (self::$dict as $lang => $words) {
            if (isset($words['confirmation'], $words['traveller'])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['confirmation'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['traveller'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function normalizeDate($date)
    {
        $in = [
            // fr - undi 7 janvier 2019
            '#^\w+ (\d+ \w+ \d{4})$#u',
        ];
        $out = [
            '$1',
        ];
        $str = strtotime($this->dateStringToEnglish(preg_replace($in, $out, $date)));

        return $str;
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

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return null;
        }

        return self::$dict[$this->lang][$s];
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
