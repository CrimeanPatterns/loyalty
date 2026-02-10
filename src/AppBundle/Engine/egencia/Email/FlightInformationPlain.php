<?php

namespace AwardWallet\Engine\egencia\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class FlightInformationPlain extends \TAccountChecker
{
    public $mailFiles = "egencia/it-6430051.eml, egencia/it-6568761.eml";

    public $reFrom = "no-reply@egencia.com";

    public $reSubject = [
        "en" => "Confirmation code:",
    ];
    public $reBody = ['Egencia', 'If you need to speak to a travel consultant, call', 'United confirmation'];
    public $reBody2 = [
        "en" => "Scheduled Departure",
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [],
    ];

    public function parseFlight(Email $email)
    {
        $text = preg_replace('/^>+ +/m', '', str_replace('\n', "\n", $this->http->Response['body']));

//        $this->logger->debug($text);

        $otaConf = $this->re("#Itinerary number:\s*(\d{5,})[ ]*$#m", $text);

        $email->obtainTravelAgency();

        if (!empty($otaConf)) {
            $email->ota()
                ->confirmation($otaConf);
        }

        $f = $email->add()->flight();

        $f->general()
            ->confirmation($this->re("#confirmation code:\s+(\w+)#", $text))
            ->travellers(array_filter([$this->re("#Travell?er:\s+(.+)#", $text)]), true);

        $ticket = $this->re("#Airline ticket number\(s\):\s*(\d{10,}+)\n#", $text);

        if (!empty($ticket)) {
            $f->issued()
                ->ticket($ticket, false);
        }

        $s = $f->addSegment();

        if (preg_match('/\n *(?:Flight|\S.+ *[Cc]onfirmation [Cc]ode\:\s*[A-Z\d]{5,7}) *\n\s*(?<al>[A-Z].{2,}) +(?<fn>\d{1,5})\n/', $text, $m)) {
            $s->airline()
                ->name(trim($m['al']))
                ->number($m['fn']);

            $accountNumber = $this->re('/^[ ]*' . preg_quote($m['al'], '/') . '[\w \-]*#[ ]*([-A-Z\d ]{5,}?)[ ]*$/m', $text);

            if (empty($accountNumber)) {
                $m['al'] = preg_replace('/\s*\bairlines?\b\s*/i', '', $m['al']);
                $accountNumber = $this->re('/^[ ]*' . preg_quote($m['al'], '/') . '[\w \-]*#[ ]*([-A-Z\d ]{5,}?)[ ]*$/m', $text);
            }

            if ($accountNumber) {
                $f->program()
                    ->account($accountNumber, false);
            }
        }

        // Economy/Coach Class(S), Food For Purchase, 4hr 9mn, Boeing 757 (757-300)
        if (preg_match('/^[>\s]*(?<cabin>[^,.\n]+)\((?<bookingClass>[A-Z]{1,2})\)(?:,\s+(?<meal>[^,]{4,}))?,\s+(?<duration>\d{1,2}hr\s*\d{1,2}mn)(?:,\s+(?<aircraft>.{5,}))?/m', $text, $matches)) {
            $s->extra()
                ->cabin($matches['cabin'])
                ->bookingCode($matches['bookingClass']);

            if (isset($matches['meal']) && !empty($matches['meal'])) {
                $s->extra()
                    ->meal($matches['meal']);
            }

            if (isset($matches['duration']) && !empty($matches['duration'])) {
                $s->extra()
                    ->duration($matches['duration']);
            }

            if (isset($matches['aircraft']) && !empty($matches['aircraft'])) {
                $s->extra()
                    ->aircraft($matches['aircraft']);
            }
        } elseif (preg_match("/{$s->getAirlineName()}\s*{$s->getFlightNumber()}\s*\n*(\D+)\n*{$this->opt($this->t('Scheduled Departure'))}/", $text, $m)) {
            $s->extra()
                ->cabin($m[1]);
        }

        foreach (['Dep' => ['Scheduled Departure', 'Departure'], 'Arr' => ['Arrives', 'Arrival']] as $prefix => $texts) {
            // 9-Nov-2017,  8:40 AM, San Francisco, CA (SFO-San Francisco Intl.), Terminal 3
            if (preg_match("/{$texts[0]}\n(?<Date>.+\,\s+\d+:\d+(?:\s+[AP]M)?),\s+.*?\n*\((?<Code>[A-Z]{3})-(?<Name>.*?)\)(,\s+(?<Terminal>.*Terminal.*))?/iu", $text, $m)) {
                if ($prefix == 'Dep') {
                    $s->departure()
                        ->code($m['Code'])
                        ->date(strtotime($this->normalizeDate($m['Date'])));

                    if (!empty($m['Terminal'])) {
                        $s->departure()
                            ->terminal(preg_replace('/^Terminal\s+(.+)/i', '$1', $m['Terminal']));
                    }
                }

                if ($prefix == 'Arr') {
                    $s->arrival()
                        ->code($m['Code'])
                        ->date(strtotime($this->normalizeDate($m['Date'])));

                    if (!empty($m['Terminal'])) {
                        $s->arrival()
                            ->terminal(preg_replace('/^Terminal\s+(.+)/i', '$1', $m['Terminal']));
                    }
                }
            }
        }
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        if (strpos($body, $this->reBody[0]) === false && strpos($body, $this->reBody[1]) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->SetEmailBody($parser->getPlainBody());

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseFlight($email);

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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+)-([^\d\s]+)-(\d{4}),\s+(\d+:\d+\s+[AP]M)$#", //25-Apr-2017,  4:55 PM
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
