<?php

namespace AwardWallet\Engine\aaatravel\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "aaatravel/it-163299957.eml, aaatravel/it-86680854.eml";
    public $subjects = [
        '/AAA Travel Flight Confirmation/',
    ];

    public $lang = 'en';

    public static $dictionary = [
        "en" => [
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@aaatravelsupport.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        return $this->http->XPath->query("//text()[contains(normalize-space(), 'AAA Travel')]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Reference'))}]")->length > 0
            && $this->http->XPath->query("//text()[{$this->contains($this->t('Flight Number'))}]")->length > 0;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aaatravelsupport\.com$/', $from) > 0;
    }

    public function ParseEmail(Email $email)
    {
        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[normalize-space()='Confirmation #']/following::text()[normalize-space()][1]"), 'Confirmation #')
            ->travellers(array_filter($this->http->FindNodes("//text()[normalize-space()='Travelers']/ancestor::td[1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", null, "/(\D{4,})\s*(?:\(|$)/")), true);

        $status = $this->http->FindSingleNode("//text()[normalize-space()='One-way Flight']/following::text()[normalize-space()][1]");

        if (!empty($status)) {
            $f->general()
                ->status($status);
        }

        $f->setTicketNumbers(array_filter($this->http->FindNodes("//text()[normalize-space()='Travelers']/ancestor::td[1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", null, "/E-Ticket\s*#\s*\:?\s*(\d+)/")), false);

        $f->setAccountNumbers(array_filter($this->http->FindNodes("//text()[normalize-space()='Travelers']/ancestor::td[1]/ancestor::tr[1]/descendant::td[2]/descendant::text()[normalize-space()]", null, "/Membership #\s*(\d+)/")), false);

        $segConfirmation = explode(",", $this->http->FindSingleNode("//text()[normalize-space()='Reference #']/following::text()[normalize-space()][1]"));

        $xpath = "//text()[normalize-space()='Flight Number']";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $s = $f->addSegment();

            $arrowPrevCount = count($this->http->FindNodes("./preceding::text()[contains(normalize-space(), '→')]", $root)) - 1;

            $s->airline()
                ->name($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/^(\D+)\sFlight/"))
                ->number($this->http->FindSingleNode("./following::text()[normalize-space()][1]", $root, true, "/Flight\s*(\d+)/"))
                /*->confirmation($segConfirmation[$arrowPrevCount])*/;

            $s->departure()
                ->code($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Departs')][1]", $root, true, "/[A-Z]{3}$/"))
                ->date(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Departs')][1]/following::text()[normalize-space()][1]", $root)));

            $s->arrival()
                ->code($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Arrives')][1]", $root, true, "/[A-Z]{3}$/"))
                ->date(strtotime($this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Arrives')][1]/following::text()[normalize-space()][1]", $root)));

            $flightInfo = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(), 'Flight Details')][1]/following::text()[normalize-space()][1]", $root);

            if (preg_match("/^Airplane\:\s*(\d+)\s*\|\s*Class\:\s*(\D+)\s*\|\s*Seats\:\s*Provided/", $flightInfo, $m)
            || preg_match("/^Operating Carrier:\s*Flight\s*(?<carrierNumber>\d{2,4})\s*.+by\s*(?<carrierAirline>.+)\s*\|\s*Airplane\:\s*([A-Z\d]+)\s*\|\s*Class\:\s*(\D+)\s*\|\s*Seats\:\s*Provided/", $flightInfo, $m)) {
                $s->extra()
                    ->aircraft($m[1])
                    ->cabin($m[2]);

                if (isset($m['carrierNumber']) && isset($m['carrierAirline'])) {
                    $s->airline()
                        ->carrierNumber($m['carrierNumber'])
                        ->carrierName($m['carrierAirline']);
                }
            }

            $stops = $this->http->FindSingleNode("//text()[normalize-space()='Arrives {$s->getArrCode()}']/ancestor::tr[1]/following::tr[normalize-space()][2]", null, true, "/Stop\(s\)\:\s*(\d+)/");

            if ($stops !== null) {
                $s->extra()
                        ->stops($stops);
            }

            $total = $this->http->FindSingleNode("//text()[normalize-space()='Total']/following::text()[normalize-space()][1]");

            if (preg_match("/(\D{1})([\d\.\,]+)/", $total, $m)) {
                $f->price()
                    ->total(PriceHelper::parse($m[2], $this->normalizeCurrency($m[1])))
                    ->currency($this->normalizeCurrency($m[1]));
            }
        }

        return true;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->ParseEmail($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeCurrency(string $string)
    {
        $string = trim($string);
        $currencies = [
            'GBP' => ['£'],
            'EUR' => ['€'],
            '$'   => ['$'],
            'INR' => ['Rs.'],
        ];

        foreach ($currencies as $code => $currencyFormats) {
            foreach ($currencyFormats as $currency) {
                if ($string === $currency) {
                    return $code;
                }
            }
        }

        return null;
    }
}
