<?php

namespace AwardWallet\Engine\fourchette\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class ReservationUpdated extends \TAccountChecker
{
    public $mailFiles = "fourchette/it-107548330.eml, fourchette/it-110756349.eml";

    public $date;
    public $lang;
    public static $dictionary = [
        'en' => [
            //            'Hello ' => '',
            //            'people' => '',
            //            'Get directions' => '',
        ],
        'fr' => [
            'Hello '         => 'Bonjour',
            'people'         => ['personnes', 'personne'],
            'Get directions' => 'Obtenir l\'itinéraire',
        ],
        'pt' => [
            'Hello '         => 'Olá,',
            'people'         => ['pessoas', 'pessoa'],
            'Get directions' => ['Obter direções', 'Veja como chegar'],
        ],
        'it' => [
            'Hello '         => 'Salve ',
            'people'         => 'persone',
            'Get directions' => ['Come raggiungerci'],
        ],
    ];

    private $detectFrom = "@thefork.";
    private $detectSubject = [
        // en
        'has been updated',
        //fr
        'a été mise à jour',
        // pt
        ' foi atualizada',
        // it
        ' è stata aggiornata',
    ];
    private $detectBody = [
        'en' => [
            'Your reservation has been updated. Take a look at the new details below',
            'Your booking has been updated. Take a look at the new details below',
        ],
        'fr' => [
            'Votre réservation a été mise à jour. Voici les nouvelles informations',
        ],
        'pt' => [
            'A sua reserva foi atualizada. Veja os novos detalhes abaixo:',
            'A reserva foi atualizada',
        ],
        'it' => [
            'La prenotazione è stata aggiornata. Vedi i nuovi dettagli sotto:',
        ],
    ];

    // Main Detects Methods
    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->detectFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
        if ($this->http->XPath->query("//a[{$this->contains(['.lafourchette.com', 'www.thefork.co'], '@href')}]")->length === 0) {
            return false;
        }

        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[{$this->contains($detectBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang() && $this->detectEmailByBody($parser) !== true) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        $this->date = strtotime($parser->getDate());
        $this->parseEmailHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function parseEmailHtml(Email $email)
    {
        $r = $email->add()->event();

        // General
        $r->general()
            ->noConfirmation()
            ->traveller($traveller ?? trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hello ")) . "]", null, true,
                "/" . $this->preg_implode($this->t("Hello ")) . "\s*([[:alpha:] \-]+)\s*[,!]?\s*$/u")), false)
        ;

        // Place
        $address = "//text()[" . $this->eq($this->t("Get directions")) . "]/preceding::tr[normalize-space()][count(*) = 2 and *[1][normalize-space() = '' and .//img]][1]/*[2]";
        $r->place()
            ->name($this->http->FindSingleNode($address . "/descendant::text()[normalize-space()][1]"))
            ->address($this->http->FindSingleNode($address . "/descendant::text()[normalize-space()][2]"))
            ->type(EVENT_RESTAURANT);

        $phone = $this->http->FindSingleNode($address . "/descendant::text()[normalize-space()][3]",
            null, true, "/^[\d ()\-+]{5,}$/");

        if (!empty($phone)) {
            $r->place()
                ->phone($phone);
        }

        // Booked
        $info = "//tr[count(*[normalize-space()='•']) = 2]";
        $r->booked()
            ->start($this->normalizeDate(
                $this->http->FindSingleNode($info . "/*[normalize-space() != '•'][2]")
                . ', ' . $this->http->FindSingleNode($info . "/*[normalize-space() != '•'][3]")
            ))
            ->noEnd()
            ->guests($this->http->FindSingleNode($info . "/*[normalize-space() != '•'][1]",
                null, true, "/^\s*(\d+) " . $this->preg_implode($this->t("people")) . "/u"))
        ;

        return true;
    }

    private function assignLang()
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (isset($dict["Réservation confirmée"])) {
                if ($this->http->XPath->query("//*[{$this->contains($dict['Réservation confirmée'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate(?string $date): ?int
    {
        // $this->logger->debug('date begin = ' . print_r($date, true));

        if (empty($date) || empty($this->date)) {
            return null;
        }
        $year = date("Y", $this->date);

        $in = [
            // Saturday, 17 Jul, 9:00 PM; Quinta-feira, 8 de out, 17:00
            '/^\s*([ [:alpha:]\-]+)\,?\s+(\d{1,2})\s+(?:de\s+)?([[:alpha:]]+)\.?\,\s+(\d{1,2}:\d{2}(\s*[ap]m)?)\s*$/ui',
        ];
        $out = [
            "$1, $2 $3 {$year}, $4",
        ];

        $date = preg_replace($in, $out, $date);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'de')) {
                $date = str_replace($m[1], $en, $date);
            } elseif ($en = MonthTranslate::translate($m[1], 'pt')) {
                $date = str_replace($m[1], $en, $date);
            }
        }

//        $this->logger->debug('date end = ' . print_r( $date, true));
        if (preg_match("/^(?<week>[\w\-]+), (?<date>\d+ \w+ .+)/u", $date, $m)) {
            $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], $this->lang));

            if (!is_numeric($weeknum)) {
                $weeknum = WeekTranslate::number1(WeekTranslate::translate($m['week'], 'pt'));
            }
            $date = EmailDateHelper::parseDateUsingWeekDay($m['date'], $weeknum);
        } elseif (preg_match("/\b\d{4}\b/", $date)) {
            $date = strtotime($date);
        } else {
            $date = null;
        }

        return $date;
    }

    private function preg_implode($field)
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
