<?php

namespace AwardWallet\Engine\airbaltic\Email;

use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class Flight extends \TAccountChecker
{
    private $lang = 'en';

    private static $dictionary = [
        'en' => [
            ", it's time" => [
                ', it\'s time to check in for your flight',
                ', last chance to sort out your meal plans',
                ', pick the baggage option that suits you best',
                ', how about delightful meal?',
                ', don’t miss the chance!',
                ', upgrade your flight experience!',
                ', add checked baggage with a discount!',
            ],
            //            'Booking reference' => '',
            'Your flight details' => 'Your flight details',
        ],
        'lt' => [
            ", it's time" => [
                ', laikas registruotis į skrydį',
                ', sėdėk norimoje vietoje!',
                ', laikas pasirinkti maitinimą lėktuve!',
                ', pasirink labiausiai sau tinkantį bagažą!',
            ],
            'Booking reference'   => 'Rezervacijos numeris',
            'Your flight details' => 'Informacija apie skrydį',
        ],
        'lv' => [
            ", it's time" => [
                ", izvēlies sev piemērotāko bagāžas veidu",
                ', izvēlies savu mīļāko vietu!',
                ', pievieno bagāžu izdevīgāk!',
                ', padari savu lidojumu vēl ērtāku!',
                ', kā būtu ar debešķīgu maltīti?',
                ', laiks reģistrēties lidojumam.',
                ', laiks izvēlēties, ko ēdīsi lidmašīnā!',
                ', rezervē sēdvietu un saņem iekāpšanas karti tūlīt.',
                ', ir pēdējais brīdis pasūtīt maltīti!',
            ],
            'Booking reference'   => 'Rezervācijas numurs',
            'Your flight details' => 'Informācija par Tavu lidojumu',
        ],
        'ru' => [
            ", it's time" => [
                ", выберите наиболее подходящий вам вид багажа",
                ", пора регистрироваться на рейс",
                ', добавьте вкуса вашему полету!',
                ', сделайте свой полёт приятнее!',
                ', начните своё путешествие на любимом месте в самолёте!',
                ', выберите свое любимое место на борту самолета!',
            ],
            'Booking reference'   => 'Номер бронирования',
            'Your flight details' => 'Информация о вашем полете',
        ],
        'et' => [
            ", it's time" => [
                ", on aeg registeeruda oma lennule",
                ', on aeg valida enda lennule eine!',
            ],
            'Booking reference'   => 'Broneeringu number',
            'Your flight details' => 'Informatsioon Sinu lennu kohta',
        ],
        'de' => [
            ", it's time" => [
                ', wählen Sie die optimale Gepäckoption für Ihre Reise.',
            ],
            'Booking reference'   => 'Buchungsnummer',
            'Your flight details' => 'Ihre Flugdaten',
        ],
        'fi' => [
            ", it's time" => [
                ', valitse tarpeisiisi parhaiten sopiva matkatavaravaihtoehto',
                ', nyt kannattaa valita ateria lennollesi!',
                ', varaa paikkasi ja hanki tarkastuskorttisi heti.',
                ', istu lempipaikallasi!',
                ', lisää kirjattava matkatavara tarjoushintaan!',
            ],
            'Booking reference'   => 'Varaustunnus',
            'Your flight details' => 'Lentosi tiedot',
        ],
    ];

    private $from = '/[@\.]*airbaltic\.com/';

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your flight details'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Your flight details'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return isset($headers['from']) && preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//a[contains(@href, '.airbaltic.com')]")->length === 0) {
            return false;
        }

        if ($this->http->XPath->query("//img[contains(@src, 'outbound')]/ancestor::tr[2]")->length === 0) {
            return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your flight details'])
                && $this->http->XPath->query("//node()[{$this->contains($dict['Your flight details'])}]")->length > 0) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseEmail(Email $email): void
    {
        $f = $email->add()->flight();

        $conf = $this->http->FindSingleNode("(//tr[{$this->eq($this->t('Booking reference'))} and not(.//tr)]/following-sibling::tr[1])[1]",
            null, true, '/^\s*([A-Z\d]{5,9})\s*$/');

        if (!empty($conf)) {
            $f->general()
                ->confirmation($conf);
        }

        $name = $this->http->FindSingleNode("//text()[{$this->contains($this->t(", it's time"))}]", null, true, "/(.+){$this->opt($this->t(", it's time"))}/u");

        if (!empty($name)) {
            $f->general()
                ->traveller($name);
        }

        $xpath = "//img[contains(@src, 'outbound')]/ancestor::tr[2]";
        $roots = $this->http->XPath->query($xpath);

        if (0 === $roots->length) {
            $this->logger->debug("Segments did not found by xpath: {$xpath}");
        }

        foreach ($roots as $root) {
            $s = $f->addSegment();

            $re = '/(.+)[ ]+\(([A-Z]{3})\)\s*(\d{1,2}\/\d{1,2}\/\d{2,4}, \d{1,2}:\d{2})/';
            $dep = $this->http->FindSingleNode('descendant::table[1]/descendant::td[1]', $root);

            if (preg_match($re, $dep, $m)) {
                $s->departure()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime(str_replace('/', '.', $m[3])));
            }

            $arr = $this->http->FindSingleNode('descendant::table[1]/descendant::td[last()]', $root);

            if (preg_match($re, $arr, $m)) {
                $s->arrival()
                    ->name($m[1])
                    ->code($m[2])
                    ->date(strtotime(str_replace('/', '.', $m[3])));
                $s->airline()
                    ->noNumber()
                    ->name('Air Baltic');
            }
        }
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
