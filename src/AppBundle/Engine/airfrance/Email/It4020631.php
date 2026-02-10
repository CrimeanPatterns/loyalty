<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: Also consider klm/FlightCancelled
class It4020631 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $mailFiles = "airfrance/it-101877648.eml, airfrance/it-11098309.eml, airfrance/it-11786545.eml, airfrance/it-11844931.eml, airfrance/it-12028035.eml, airfrance/it-143268675.eml, airfrance/it-148635325.eml, airfrance/it-4020631.eml, airfrance/it-4030604.eml, airfrance/it-4036984.eml, airfrance/it-4073643.eml, airfrance/it-4081641.eml, airfrance/it-4089517.eml, airfrance/it-4090290.eml, airfrance/it-4101556.eml, airfrance/it-4993974.eml, airfrance/it-5466856.eml, airfrance/it-5496417.eml, airfrance/it-5577595.eml, airfrance/it-93988590.eml, airfrance/it-94527558.eml, airfrance/it-95094836.eml";

    public static $dictionary = [
        'en' => [
            'Booking code:'   => ['Booking code', 'Booking code:'],
            'Name:'           => ['Name:', 'Name'],
            'E-ticketnumber:' => ['E-ticketnumber:', 'Ticket number', 'Ticket number:'],
            'Flight number:'  => ['Flight number:', 'Flight number'],

            //            'Your old flight details' => '', // headers of old and new segments
            //            'Your new flight details' => '', // headers of old and new segments
            //            'seat' => '', // includes this word when the place is displayed after the flight number without a title 'Seat status:'
        ],
        'pt' => [
            'Booking code:'   => ['Código de reserva:', 'Código da reserva:', 'Código da sua reserva:'],
            'Flight number:'  => ['Voo N°:', 'Vuelo n°:'],
            'Name:'           => 'Nome:',
            'E-ticketnumber:' => 'Número do bilhete eletrónico:',
            'Departure:'      => ['Partida:', 'Salida:'],
            'From:'           => 'A partir da:',
            'Arrival:'        => 'Chegada:',
            'To:'             => 'Para:',
            'Operated by:'    => 'Operado por:',
            'Class:'          => 'Classe:',
            //			'Seat status:' => '',
            'your new departure time'  => 'O seu novo horário de partida',
            'Your new flight details:' => 'Estes são os horários atualizados do seu voo:',

            'Your old flight details' => 'Dados do seu voo anterior', // headers of old and new segments
            'Your new flight details' => 'Os dados do seu novo voo', // headers of old and new segments
            //            'seat' => '', // includes this word when the place is displayed after the flight number without a title 'Seat status:'
        ],
        'fr' => [
            'Booking code:'   => ['Dossier de réservation :', 'Code de réservation :', 'Référence de réservation:', 'Référence de réservation :'],
            'Flight number:'  => 'Vol no.:',
            'Name:'           => ['Nom:', 'Nom :'],
            'E-ticketnumber:' => 'Numéro e-billet:',
            'Departure:'      => ['Départ:', 'Départ :'],
            'From:'           => ['Départ de:', 'Départ de :'],
            'Arrival:'        => ['Arrivée:', 'Arivée:', 'Arrivée :'],
            'To:'             => ['À:', 'Arrivée à :'],
            'Operated by:'    => ['Opéré par:', 'Effectué par :'],
            'Class:'          => ['Classe:', 'Classe :'],
            //			'Seat status:' => ''
            'Your old flight details' => 'Informations sur votre ancien vol',
            'Your new flight details' => ['Votre nouvelle réservation', 'Informations sur votre nouveau vol :'],
            //            'seat' => '', // includes this word when the place is displayed after the flight number without a title 'Seat status:'
        ],
        'de' => [
            'Booking code:'   => 'Buchungscode:',
            'Flight number:'  => 'Flugnr.:',
            'Name:'           => 'Name:',
            'E-ticketnumber:' => 'E-Ticket-Nummer:',
            'Departure:'      => 'Abflug:',
            'From:'           => 'Von:',
            'Arrival:'        => 'Ankunft:',
            'To:'             => 'Nach:',
            'Operated by:'    => 'Durchgeführt von:',
            'Class:'          => 'Klasse:',
            //			'Seat status:' => '',

            'Your old flight details' => 'Ihre alten Flugdaten',
            'Your new flight details' => 'Neue Flugdaten',
        ],
        'nl' => [
            'Booking code:'   => ['Boekingscode', 'Boekingscode:', 'Uw boekingscode:'],
            'Flight number:'  => ['Vluchtnr.:', 'Vluchtnummer:'],
            'Name:'           => 'Naam:',
            'E-ticketnumber:' => ['E-ticketnummer:', 'Ticketnummer:'],
            'Departure:'      => 'Vertrek:',
            'From:'           => 'Van:',
            'Arrival:'        => 'Aankomst:',
            'To:'             => 'Naar:',
            'Operated by:'    => ['Uitgevoerd door:', 'Uitgevoerd:'],
            'Class:'          => 'Klasse:',
            //			'Seat status:' => '',
            'your new departure time'  => 'Uw nieuwe Vertrektijd',
            'Your new flight details:' => ['Uw nieuwe vluchtgegevens:', 'Uw nieuwe vluchtgegevens'],

            'Your old flight details' => 'Uw oude vluchtgegevens',
            'Your new flight details' => 'Uw nieuwe vluchtgegevens',
            'seat'                    => 'stoelnummer', // includes this word when the place is displayed after the flight number without a title 'Seat status:'
        ],
        'it' => [ // it-93988590.eml
            'Booking code:'  => ['Codice di prenotazione:'],
            'Flight number:' => ['Volo N.:'],
            'Name:'          => 'Nome:',
            //'E-ticketnumber:' => ['E-ticketnummer:', 'Ticketnummer:'],
            'Departure:'   => 'Partenza:',
            'From:'        => 'Da parte di:',
            'Arrival:'     => 'Arrivo:',
            'To:'          => 'A:',
            'Operated by:' => ['Operato da:'],
            'Class:'       => 'Classe:',
            'Seat status:' => 'Stato del posto a sedere:',

            'Your old flight details' => 'I dettagli del suo volo precedente',
            'Your new flight details' => 'Nuove informazioni di volo',
            //            'seat' => '', // includes this word when the place is displayed after the flight number without a title 'Seat status:'
        ],
        'es' => [ // it-101877648.eml
            'Booking code:'   => ['Código de reserva:'],
            'Flight number:'  => ['Vuelo n°:'],
            'Name:'           => 'Nombre:',
            'E-ticketnumber:' => ['Número de billete electrónico', 'Numéro e-billet:'],
            'Departure:'      => 'Salida:',
            'From:'           => 'De:',
            'Arrival:'        => 'Llegada:',
            'To:'             => 'A:',
            'Operated by:'    => ['Operado por:'],
            'Class:'          => 'Clase:',
            'Seat status:'    => 'Situación del asiento:',

            //            'Your old flight details' => '', // headers of old and new segments
            //            'Your new flight details' => '', // headers of old and new segments
            //            'seat' => '', // includes this word when the place is displayed after the flight number without a title 'Seat status:'
        ],
    ];

    public $lang = '';

    private $reFrom = ['airfrance-klm@connect-passengers.com', '@ticket.klm.com'];
    private $subject;
    private $reProvider = [
        'airfrance' => [
            'Air France',
            'airfrance',
        ],
        'klm' => [
            'KLM',
        ],
    ];
    private $reSubject = [
        'en' => 'Travel information',
        'pt' => 'Travel information',
        'fr' => [
            'Travel information',
            'Votre vol est annulé',
        ],
        'de' => 'Travel information',
        'nl' => 'Travel information',
    ];

    private $reBody = [
        'en' => ['Delay of your flight', 'Rebooking Information', 'Your new flight details'],
        'pt' => ['Dados da nova reserva:', 'Novos dados do voo:', 'Dados do seu novo voo', 'Estes são os horários atualizados do seu voo', 'Os dados do seu novo voo'],
        'fr' => ['Votre nouvelle réservation', 'Votre réservation a été modifiée', 'Informations sur votre nouveau vol'],
        'de' => 'Neue Flugdaten:',
        'nl' => ['Annulering van uw vlucht', 'Vertraging van uw vlucht', 'Uw vluchtschema is gewijzigd', "Uw vlucht is gewijzigd", "Uw nieuwe vluchtgegevens"],
        'it' => ["Nuove informazioni di volo"],
        'es' => 'Los datos de su nuevo vuelo',
    ];
    private $date;
    private $code;

    public static function getEmailProviders()
    {
        return ['airfrance', 'klm'];
    }

    public function detectEmailFromProvider($from)
    {
        return $this->arrikey($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        return $this->arrikey($headers['subject'], $this->reSubject) !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (!$this->getProvider($parser)) {
            return false;
        }

        if ($this->assignLang()) {
            return true;
        }

        return false;
    }

    public function parseHtml(Email $email)
    {
        $f = $email->add()->flight();

        // RecordLocator
        $conf = $this->nextCol($this->t('Booking code:'), null, "#^\s*([A-Z\d]{5,7})\s*$#");

        if (empty($conf)) {
            $conf = $this->nextText($this->t('Booking code:'), null, "#^\s*([A-Z\d]{5,7})\s*$#");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking code:'))}]", null, true, "#\s*([A-Z\d]{5,7})\s*$#");
        }
        $f->general()->confirmation($conf);
        // Passengers
        if ($travellers = array_unique($this->http->FindNodes("//td[not(.//td) and (" . $this->eq($this->t('Name:')) . ")]/following-sibling::td[1]"))) {
            $f->general()->travellers($travellers);
        }
        // TicketNumbers
        $f->issued()->tickets(array_unique($this->http->FindNodes("//td[not(.//td) and (" . $this->eq($this->t('E-ticketnumber:')) . ")]/following-sibling::td[1]")), false);

        $xpath = "//text()[" . $this->eq($this->t('Flight number:')) . "]/ancestor::tr[1]/parent::*[(" . $this->contains($this->t('Operated by:')) . ") and not(" . $this->contains($this->t('Booking code:')) . ")]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0 && stripos($this->subject, $this->t('your new departure time')) !== false) {
            $s = $f->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your new flight details:'))}]/following::text()[normalize-space()][1]", null, true, "/^([A-Z\d]{2})\d{2,4}/"))
                ->number($this->http->FindSingleNode("//text()[{$this->eq($this->t('Your new flight details:'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{2}(\d{2,4})/"));

            $s->departure()
                ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departure:'))}]/preceding::text()[normalize-space()][1]", null, true, "/^(\D+)\s*\-/"))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departure:'))}]", null, true, "/{$this->opt($this->t('Departure:'))}\s*(.+)/"))))
                ->noCode();

            $s->arrival()
                ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('Departure:'))}]/preceding::text()[normalize-space()][1]", null, true, "/\s*\-\s*(\D+)$/"))
                ->date(strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->contains($this->t('Arrival:'))}]", null, true, "/{$this->opt($this->t('Arrival:'))}\s*(.+)/"))))
                ->noCode();
        }

        $this->logger->debug("segments: $xpath");

        foreach ($nodes as $root) {
            /*
             * airfrance/it-4089517.eml
             */
            $count = count($this->http->FindNodes(".//text()[" . $this->eq($this->t('Departure:')) . "]", $root));

            for ($i = 1; $i <= $count; $i++) {
                if (!empty($this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t('Departure:')) . "][{$i}]/preceding::text()[" . $this->eq($this->t('Your old flight details')) . "][1]", $root))
                    && !empty($this->http->FindSingleNode("descendant::text()[" . $this->contains($this->t('Departure:')) . "][{$i}]/following::text()[" . $this->eq($this->t('Your new flight details')) . "][1]", $root))
                ) {
                    $this->logger->debug('$count = ' . print_r('continue', true));

                    continue;
                }

                $s = $f->addSegment();
                $s->airline()->number($this->nextCol($this->t('Flight number:'), $root, "#^[A-Z\d]{2}(\d+)#", 1, $i));
                $s->airline()->name($this->nextCol($this->t('Flight number:'), $root, '#^(\w{2})\d+$#', 1, $i));

                // DepCode
                $code = $this->nextCol($this->t('Departure:'), $root, "#\(([A-Z]{3})\)#u", 2, $i);

                if (empty($code)) {
                    $code = $this->nextCol($this->t('From:'), $root, "#\(([A-Z]{3})\)#u", 1, $i);
                }

                if (empty($code)) {
                    $code = $this->nextCol($this->t('Departure:'), $root, "#\(([A-Z]{3})\)#u", 1, $i);
                }
                $s->departure()->code($code);
                // DepDate
                $date = strtotime(
                    $this->dateStringToEnglish(
                        $this->normalizeDate(
                            $this->nextCol($this->t('Departure:'), $root, null, 1, $i) . ', ' . $this->nextCol($this->t('Departure:'), $root, '/(\d+:\d+)/', 2, $i)
                        ),
                        false, true)
                );

                if (isset($this->lastDate)) {
                    for ($d = 0; $d < 3; $d++) {
                        if ($date < $this->lastDate) {
                            $date = strtotime('+1 day', $date);
                        } else {
                            break;
                        }
                    }
                }
                $s->departure()->date($date);

                // ArrCode
                $code = $this->nextCol($this->t('Arrival:'), $root, '#\(([A-Z]{3})\)#', 2, $i);

                if (empty($code)) {
                    $code = $this->nextCol($this->t('To:'), $root, '#\(([A-Z]{3})\)#', 1, $i);
                }
                $s->arrival()->code($code);
                // ArrDate
                $date = strtotime(
                    $this->dateStringToEnglish(
                        $this->normalizeDate(
                            $this->nextCol($this->t('Arrival:'), $root, null, 1, $i) . ', ' . $this->nextCol($this->t('Arrival:'), $root, '/(\d+:\d+)/', 2, $i)
                        ),
                        false, true)
                );
                $this->lastDate = $date;
                $s->arrival()->date($date);

                // Operator
                $s->airline()->operator($this->nextCol($this->t('Operated by:'), $root, null, 1, $i));

                // BookingClass
                if ($i == $count) {
                    $class = $this->http->FindSingleNode("(./tr[" . $this->contains($this->t('Departure:')) . "])[{$i}]/following-sibling::tr[" . $this->contains($this->t('Class:')) . "]/td[" . $this->eq($this->t('Class:')) . "]/following-sibling::td[1]", $root);
                } else {
                    $trs = $this->http->FindNodes("(./tr[./td[" . $this->eq($this->t('Departure:')) . "]])[{$i}]/following-sibling::tr", $root);
                    $position = 1;

                    foreach ($trs as $tr) {
                        if (is_array($this->t('Departure:'))) {
                            foreach ($this->t('Departure:') as $dep) {
                                if (stripos($tr, $dep) !== false) {
                                    break 2;
                                }
                            }
                        } else {
                            if (stripos($tr, $this->t('Departure:')) !== false) {
                                break;
                            }
                        }
                        $position++;
                    }
                    $class = $this->http->FindSingleNode("(./tr[" . $this->contains($this->t('Departure:')) . "])[{$i}]/following-sibling::tr[position()<{$position}]/td[" . $this->eq($this->t('Class:')) . "]/following-sibling::td[1]", $root);
                }

                if (!empty($class)) {
                    if (preg_match('/^[A-Z]$/', $class)) {
                        $s->extra()->bookingCode($class);
                    } else {
                        $s->extra()->cabin($class);
                    }
                }
                // Seats
                if (!empty($s->getFlightNumber()) && !empty($s->getAirlineName())) {
                    $seats = $this->http->FindNodes("//td[normalize-space(text())='{$s->getAirlineName()}{$s->getFlightNumber()}']/ancestor::tr[1]/following-sibling::tr[position()<3]/td[" . $this->eq($this->t('Seat status:')) . "]/following-sibling::td",
                        null, "#\b(\d{1,3}[A-Z])\b#");

                    if (empty($seats)) {
                        $seats = $this->http->FindNodes("//td[normalize-space(text())='{$s->getAirlineName()}{$s->getFlightNumber()}']/ancestor::tr[1]/following-sibling::tr[1][count(*[normalize-space()]) = 1 and *[1][not(normalize-space())]]/td[normalize-space()][" . $this->contains($this->t("seat")) . "]",
                            null, "#\b(\d{1,3}[A-Z])\b#");
                    }

                    if (!empty($seats)) {
                        $s->extra()->seats(array_unique(array_filter($seats)));
                    }
                }
            }
        }
        $uniq = [];

        foreach ($f->getSegments() as $k => $s) {
            if (isset($uniq[$s->getAirlineName() . $s->getFlightNumber()])) {
                $f->removeSegment($s);
            }
            $uniq[$s->getAirlineName() . $s->getFlightNumber()] = 1;
        }
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (!$this->assignLang()) {
            $this->logger->debug("Can't determine a language");

            return $email;
        }
        $this->date = strtotime($parser->getHeader('date'));
        $this->subject = $parser->getSubject();

        $this->parseHtml($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
        $email->setProviderCode($this->getProvider($parser));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary) * 2;
    }

    private function getProvider(\PlancakeEmailParser $parser)
    {
        foreach ($this->reProvider as $code => $criteria) {
            if (count($criteria) > 0) {
                foreach ($criteria as $search) {
                    if (!(stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                        && !(stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                    ) {
                        continue 2;
                    } else {
                        return $code;
                    }
                }
            }
        }

        return null;
    }

    private function assignLang()
    {
        foreach ($this->reBody as $lang => $value) {
            if ($this->http->XPath->query("//text()[{$this->contains($value)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function nextText($field, $root = null, $regexp = null, $n = 1, $c = 1)
    {
        return $this->http->FindSingleNode("(.//text()[" . $this->eq($field) . "])[{$c}]/following::text()[normalize-space(.)!=''][{$n}]", $root, true, $regexp);
    }

    private function nextCol($field, $root = null, $regexp = null, $n = 1, $c = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and (" . $this->eq($field) . ")])[{$c}]/following-sibling::td[{$n}]", $root, true, $regexp);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $this->logger->debug('IN = ' . $str);
        $year = date('Y', $this->date);
        $in = [
            "#^([^\d\W]+)(\d+),\s+(\d+:\d+)$#",
            "#^([^\d\W]+)(\d+)\s+(\d+:\d+), $#",
            "#^\w+\s+(\d+\s+\w+\s+\d+)\s+(\d+:\d+), $#",
            "#^(\d+)([^\d\W]+)\s+(\d+:\d+), $#",
            "#^\w+\.\s+(\d+)\s+(\w+)\s+(\d+)\s+(\d+:\d+), $#",
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\s*(\d{2})\s+(\d+:\d+), $#",
            "#^(\d+)([^\d\W]+),\s+(\d+:\d+)$#",
            //12:15 27/05/2021
            "#^([\d\:]+)\s*(\d+)\/(\d+)\/(\d{4})$#",
        ];
        $out = [
            "$2 $1 $year, $3",
            "$2 $1 $year, $3",
            "$1, $2",
            "$1 $2 $year, $3",
            "$1 $2 20$3, $4",
            "$1 $2 20$3, $4",
            "$1 $2 $year, $3",
            "$2.$3.$4, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        //if( preg_match('/[^\d\s-\.\/]/', $str) ) $str = $this->dateStringToEnglish($str);
        $this->logger->debug('OUT = ' . $str);

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

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'contains(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function arrikey($haystack, array $arrayNeedle)
    {
        foreach ($arrayNeedle as $key => $needles) {
            if (is_array($needles)) {
                foreach ($needles as $needle) {
                    if (stripos($haystack, $needle) !== false) {
                        return $key;
                    }
                }
            } else {
                if (stripos($haystack, $needles) !== false) {
                    return $key;
                }
            }
        }

        return false;
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
