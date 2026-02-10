<?php

namespace AwardWallet\Engine\checkmytrip\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Common\Flight;
use AwardWallet\Schema\Parser\Common\Hotel;
use AwardWallet\Schema\Parser\Common\Transfer;
use AwardWallet\Schema\Parser\Email\Email;

class YourTrip extends \TAccountChecker
{
    public $mailFiles = "checkmytrip/it-372164012.eml, checkmytrip/it-376473964.eml, checkmytrip/it-380825922.eml, checkmytrip/it-384586391.eml, checkmytrip/it-385152794.eml";

    public $providerCode;

    public static $detectProvider = [
        'fcmtravel' => [
            // 'from' => '',
            'agencyName' => 'FCM TRAVEL',
        ],
        'amadeus' => [
            'from' => 'itinerary@amadeus.com',
            // 'agencyName' => '',
        ],
        'maketrip' => [
            'agencyName' => 'MAKEMYTRIP.COM',
            // 'from' => '',
        ],
        'ctraveller' => [
            // 'agencyName' => '@corptraveller.',
            'from' => '@corptraveller.',
        ],
    ];

    public $allConfirmations = [];
    public $lang;
    public static $dictionary = [
        'en' => [
            'Your trip' => 'Your trip',
            // 'Booking ref' => '',
            'Traveler'           => ['Traveler', 'Traveller'],
            'Agency Information' => 'Agency Information',

            'JunkSegmentName' => 'Miscellaneous',
            // 'Booking status' => '',// for all type

            // **Flight**
            // 'Airline Booking Reference' => '',
            // 'Terminal' => '',
            // 'Class' => '', // + train
            // 'Seat' => '',// + train
            //
            // 'E-ticket' => '', // after all segments
            //'Frequent Flyer number' => '',

            // **Hotel**
            // 'Confirmation number' => '', // + rental
            // 'Check-in' => '',
            // 'Check-out' => '',
            // "Occupancy" => "",
            // "Adult" => "",
            // "Estimated total" => "", // + rental
            // 'Cancellation policy' => '',

            // **Train**
            // 'Railway Booking Reference' => '',
            // 'Train' => '',

            // **Transfer**
            'Taxi For Flight Arrival'   => ['Taxi For Flight Arrival', 'Airport Bus For Flight Arrival'],
            'Taxi For Flight Departure' => ['Taxi For Flight Departure', 'Airport Bus For Flight Departure'],
            // 'Pick Up:' => '',
            // 'Drop Off:' => '',
            'Phone' => ['Phone', 'Tel'], // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
            'Estimated total' => ['Estimated total', 'Estimated total :'],
        ],
        'de' => [
            'Your trip' => 'Ihre Reise',
            // 'Booking ref' => '',
            'Traveler'           => 'Reisender',
            'Agency Information' => 'Agenturinformationen',

            // 'JunkSegmentName' => '',
            'Booking status' => 'Status', // for all type

            // **Flight**
            'Airline Booking Reference' => 'Buchungsreferenz(en)',
            'Terminal'                  => 'Terminal',
            'Class'                     => 'Klasse', // + train
            // 'Seat' => '',// + train
            //
            // 'E-ticket' => '', // after all segments,
            //'Frequent Flyer number' => '',

            // **Hotel**
            // 'Confirmation number' => '', // + rental
            // 'Check-in' => '',
            // 'Check-out' => '',
            // "Occupancy" => "",
            // "Adult" => "",
            // "Estimated total" => "", // + rental
            // 'Cancellation policy' => '',

            // **Train**
            // 'Railway Booking Reference' => '',
            // 'Train' => '',

            // **Transfer**
            // 'Taxi For Flight Arrival' => '',
            // 'Taxi For Flight Departure' => '',
            // 'Pick Up:' => '',
            // 'Drop Off:' => '',
            'Phone' => ['Phone', 'Tel'], // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
        ],
        'fr' => [
            'Your trip' => 'Votre voyage',
            // 'Booking ref' => '',
            'Traveler'           => 'Voyageur',
            'Agency Information' => 'Information sur l\'agence',

            'JunkSegmentName' => ['Divers', 'Ouigo'],
            'Booking status'  => 'Statut de la réservation', // for all type

            // **Flight**
            'Airline Booking Reference' => 'Référence dossier compagnie aérienne',
            'Terminal'                  => 'Terminal',
            'Class'                     => 'Classe', // + train
            'Seat'                      => 'Siège', // + train

            'E-ticket' => 'Numéro de billet', // after all segments
            //'Frequent Flyer number' => '',

            // **Hotel**
            'Confirmation number' => 'Numéro de confirmation', // + rental
            'Check-in'            => 'Arrivée',
            'Check-out'           => 'Départ',
            // "Occupancy" => "",
            // "Adult" => "",
            "Estimated total"     => "Total estimé", // + rental
            "Cancellation policy" => "Conditions d'annulation",

            // **Train**
            'Railway Booking Reference' => 'Ref. Dossier transporteur ferroviaire',
            'Train'                     => 'Train',

            // **Transfer**
            // 'Taxi For Flight Arrival' => '',
            // 'Taxi For Flight Departure' => '',
            // 'Pick Up:' => '',
            // 'Drop Off:' => '',
            // 'Phone' => '', // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
        ],
        'zh' => [
            'Your trip'          => '您的行程',
            'Booking ref'        => '訂位代號',
            'Traveler'           => '旅客',
            'Agency Information' => '旅行社',

            'JunkSegmentName' => '其他',
            'Booking status'  => '預訂狀態', // for all type

            // **Flight**
            'Airline Booking Reference' => '訂位代號',
            'Terminal'                  => '航站',
            'Class'                     => '艙等', // + train
            'Seat'                      => '座位', // + train
            //
            'E-ticket' => '電子機票', // after all segments
            //'Frequent Flyer number' => '',

            // **Hotel**
            // 'Confirmation number' => '', // + rental
            // 'Check-in' => '',
            // 'Check-out' => '',
            // "Occupancy" => "",
            // "Adult" => "",
            // "Estimated total" => "", // + rental
            // 'Cancellation policy' => '',

            // **Train**
            // 'Railway Booking Reference' => '',
            // 'Train' => '',

            // **Transfer**
            // 'Taxi For Flight Arrival' => '',
            // 'Taxi For Flight Departure' => '',
            // 'Pick Up:' => '',
            // 'Drop Off:' => '',
            // 'Phone' => '', // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
        ],
        'pt' => [
            'Your trip'          => 'A sua viagem',
            'Booking ref'        => 'Ref. reserva',
            'Traveler'           => 'Passageiro',
            'Agency Information' => 'Informações da Agência',

            'JunkSegmentName' => 'Diversos',
            'Booking status'  => 'Estado da reserva', // for all type

            // **Flight**
            'Airline Booking Reference' => 'Referência de reserva da companhia aérea',
            'Terminal'                  => 'Terminal',
            'Class'                     => 'Classe', // + train
            'Seat'                      => 'Lugar', // + train
            //
            'E-ticket' => 'Bilhete', // after all segments
            //'Frequent Flyer number' => '',

            // **Hotel**
            'Confirmation number' => 'Número de confirmação', // + rental
            'Check-in'            => 'Check-in',
            'Check-out'           => 'Check-out',
            "Occupancy"           => "Occupancy",
            "Adult"               => "Adult",
            "Estimated total"     => "Total estimado", // + rental
            'Cancellation policy' => 'Política de cancelamento',

            // **Train**
            // 'Railway Booking Reference' => '',
            // 'Train' => '',

            // **Transfer**
            // 'Taxi For Flight Arrival' => '',
            // 'Taxi For Flight Departure' => '',
            // 'Pick Up:' => '',
            // 'Drop Off:' => '',
            // 'Phone' => '', // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
        ],
        'es' => [
            'Your trip'          => 'Su viaje',
            'Booking ref'        => 'Localizador de reserva',
            'Traveler'           => 'Viajero',
            'Agency Information' => 'Información de la agencia',

            'JunkSegmentName' => 'Otros',
            'Booking status'  => 'Estatus de la reserva', // for all type

            // **Flight**
            'Airline Booking Reference' => ['Localizador(es) de reserva de la aerolínea'],
            'Terminal'                  => 'Terminal',
            'Class'                     => 'Clase', // + train
            'Seat'                      => 'Asiento', // + train

            'E-ticket'              => 'Billete electrónico', // after all segments
            'Frequent Flyer number' => 'Número de viajero frecuente :',

            // **Hotel**
            'Confirmation number' => 'Número de confirmación', // + rental
            'Check-in'            => 'Entrada',
            'Check-out'           => 'Salida',
            "Occupancy"           => "Occupancy",
            "Adult"               => "Adult",
            "Estimated total"     => "Importe total estimado", // + rental
            'Cancellation policy' => 'Condiciones de cancelación',

            // **Train**
            // 'Railway Booking Reference' => '',
            // 'Train' => '',

            // **Transfer**
            // 'Taxi For Flight Arrival' => '',
            // 'Taxi For Flight Departure' => '',
            // 'Pick Up:' => '',
            // 'Drop Off:' => '',
            // 'Phone' => '', // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
        ],
        'sv' => [
            'Your trip'          => 'Din resa',
            'Booking ref'        => 'Bokningsreferens',
            'Traveler'           => 'Resenär',
            'Agency Information' => 'Agency Information',

            'JunkSegmentName' => 'Övrigt',
            'Booking status'  => 'Bokningsstatus', // for all type

            // **Flight**
            'Airline Booking Reference' => 'Bokningsreferens(er) flyg',
            'Terminal'                  => 'Terminal',
            'Class'                     => 'Klass', // + train
            // 'Seat'                      => '', // + train

            'E-ticket' => 'E-ticket', // after all segments
            //'Frequent Flyer number' => '',

            // **Hotel**
            // 'Confirmation number' => '', // + rental
            // 'Check-in' => '',
            // 'Check-out' => '',
            // "Occupancy" => "",
            // "Adult" => "",
            // "Estimated total" => "", // + rental
            // 'Cancellation policy' => '',

            // **Train**
            // 'Railway Booking Reference' => '',
            // 'Train' => '',

            // **Transfer**
            'Taxi For Flight Arrival'   => 'Flygbuss För Flight Ankomst',
            'Taxi For Flight Departure' => 'Egen Taxi För Flight Avgång',
            'Pick Up:'                  => 'Pick Up:',
            'Drop Off:'                 => 'Drop Off:',
            'Phone'                     => 'Telefon', // + rental

            // **Rental**
            // 'Car Rental' => '',
            // 'Pick Up' => '',
            // 'Drop Off' => '',
            // 'Car type' => '',
        ],
    ];

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProvider);
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach (self::$detectProvider as $code => $detectP) {
            if (!empty($detectP['from'])
                && $this->containsText($headers['from'], $detectP['from']) === true
                && preg_match("/[A-Z\-][A-Z\- ]+\\/ ?[A-Z\-][A-Z\- ]+ \d{2}[A-Z]+\d{4} [A-Z]{3} [A-Z]{3}\s*$/", $headers['subject'])
            ) {
                // DAL SANTO/JOHN BRETT MR 04MAY2023 SYD HNL
                $this->providerCode = $code;

                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, 'www.mrs.amadeus.net/')]")->length === 0
            && $this->http->XPath->query("//a[normalize-space() = 'CheckMyTrip App']")->length === 0
            && $this->http->XPath->query("//a[normalize-space() = '查看我的行程' and contains(@href, 'checkmytrip.app.link')]")->length === 0 // zh
        ) {
            return false;
        }

        foreach (self::$dictionary as $dict) {
            if (!empty($dict['Your trip']) && !empty($dict['Traveler']) && !empty($dict['Agency Information'])
                && $this->http->XPath->query("//*[{$this->eq($dict['Your trip'])}]/following::tr[normalize-space()][position() < 5][*[1][{$this->eq($dict['Traveler'])}] and *[2][{$this->eq($dict['Agency Information'])}]]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['Your trip']) && $this->http->XPath->query("//*[{$this->eq($dict['Your trip'])}]")->length > 0) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            $this->logger->debug("can't determine a language");

            return $email;
        }

        if (empty($this->providerCode)) {
            foreach (self::$detectProvider as $code => $detectP) {
                if (!empty($detectP['from']) && $this->containsText($parser->getCleanFrom(), $detectP['from']) === true
                ) {
                    $this->providerCode = $code;

                    break;
                }
            }
        }
        $this->parseEmail($email);

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

    private function parseEmail(Email $email)
    {
        $xpath = "//tr[descendant::td[not(.//td)][1][not(normalize-space()) and .//img[contains(@src, 'amadeus')]]][descendant::td[not(.//td)][2][contains(., ' 20')]][count(.//td[not(.//td)]) > 5]/ancestor-or-self::tr[count(.//img[contains(@src, 'amadeus')]) = 1][not(.//text()[{$this->eq($this->t('Your trip'))}])][last()]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $xpath = "//tr[descendant::td[not(.//td)][1][not(normalize-space()) and .//img[starts-with(@src, 'cid:')]]][descendant::td[not(.//td)][2][contains(., ' 20')]][count(.//td[not(.//td)]) > 5]/ancestor-or-self::tr[count(.//img[starts-with(@src, 'cid:')]) = 1][not(.//text()[{$this->eq($this->t('Your trip'))}])][last()]";
            $nodes = $this->http->XPath->query($xpath);
        }

        $types = [];

        $transferAddFlightNI = null;

        foreach ($nodes as $ni => $root) {
            if ($this->http->XPath->query(".//node()[{$this->contains($this->t('Airline Booking Reference'))}]", $root)->length > 0) {
                $this->parseFlight($email, $root);
                $types[$ni] = 'flight';

                if ($ni === $transferAddFlightNI) {
                    $s = $f = null;

                    foreach ($email->getItineraries() as $it) {
                        if ($it->getType() === 'transfer') {
                            /** @var Transfer $it */
                            $s = $it->getSegments()[count($it->getSegments()) - 1];
                        }

                        if ($it->getType() === 'flight') {
                            /** @var Flight $it */
                            $f = $it->getSegments()[count($it->getSegments()) - 1];
                        }
                    }

                    if ($s && $f) {
                        $s->arrival()
                            ->address($f->getDepName())
                            ->noDate();
                    }
                    $transferAddFlightNI = null;
                }

                continue;
            }

            if (
                $this->http->XPath->query(".//node()[{$this->eq($this->t('Check-in'))}]", $root)->length > 0
                && $this->http->XPath->query(".//node()[{$this->eq($this->t('Check-out'))}]", $root)->length > 0
            ) {
                $this->parseHotel($email, $root);
                $types[$ni] = 'hotel';

                continue;
            }

            if ($this->http->XPath->query(".//node()[{$this->contains($this->t('Railway Booking Reference'))}]", $root)->length > 0) {
                $this->parseTrain($email, $root);
                $types[$ni] = 'train';

                continue;
            }

            if ($this->http->XPath->query(".//node()[{$this->contains($this->t('Taxi For Flight Arrival'))}]", $root)->length > 0) {
                $this->parseTransfer($email, $root);
                $types[$ni] = 'transfer';
                $transferAddFlightNI = null;

                continue;
            }

            if ($this->http->XPath->query(".//node()[{$this->contains($this->t('Taxi For Flight Departure'))}]", $root)->length > 0) {
                $this->parseTransfer($email, $root);
                $transferAddFlightNI = $ni + 1;
                $types[$ni] = 'transfer';

                continue;
            }

            if ($this->http->XPath->query(".//node()[{$this->contains($this->t('Car Rental'))}]", $root)->length > 0) {
                $this->parseRental($email, $root);
                $types[$ni] = 'rental';

                continue;
            }

            if ($this->http->XPath->query(".//text()[{$this->eq($this->t('JunkSegmentName'))}]", $root)->length > 0) {
                $types[$ni] = 'junk';

                continue;
            }
            $types[$ni] = 'unknown';
            //$email->add()->flight();
            $this->logger->debug('unknown segment type');
        }
        //$this->logger->debug('$types = ' . print_r($types, true));

        $xpathT = "//tr[*[1][{$this->eq($this->t('Traveler'))}] and *[2][{$this->eq($this->t('Agency Information'))}]]/following::tr[1]";
        $travellersAll = $this->http->FindNodes($xpathT . "/*[1]//text()[normalize-space()]", null, "/^\s*(.+)/");
        $travellersAll = preg_replace('/^\s*\d+\.?\s*/', '', $travellersAll);
        $travellersAll = preg_replace('/^\s*(Mr|Ms|Mrs|Miss|Mstr|Dr)[\.\s]+/i', '', $travellersAll);

        $travellers = array_filter($travellersAll, function ($v) {return (strpos($v, 'Inf ') === 0) ? false : true; });
        $infants = array_filter($travellersAll, function ($v) {return (strpos($v, 'Inf ') === 0) ? true : false; });

        foreach ($email->getItineraries() as $it) {
            if (!empty($travellers) || empty($travellersAll)) {
                $it->general()
                    ->travellers($travellers, true);
            }

            if (!empty($infants)) {
                $infants = preg_replace('/^\s*Inf\s+/i', '', $infants);
                $it->general()
                    ->infants($infants, true);
            }
        }

        if (empty($this->providerCode) || ($this->providerCode == 'amadeus')) {
            foreach (self::$detectProvider as $code => $detectP) {
                if (!empty($detectP['agencyName']) && $this->http->XPath->query($xpathT . "/*[2][{$this->contains($detectP['agencyName'])}]")->length > 0) {
                    $this->providerCode = $code;
                }
            }
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }

        $email->obtainTravelAgency();
        $root = $this->http->XPath->query("//text()[{$this->eq($this->t('Your trip'))}]/ancestor::*[not({$this->contains($this->t('Traveler'))})][last()]");

        if ($root->length > 0) {
            $conf = $this->getParam($this->t('Booking ref'), $root->item(0), "([A-Z\d]{5,7})(?:\s*CheckMyTrip App|\s*查看我的行程)?");
        }

        if (!empty($conf) && !in_array($conf, $this->allConfirmations)) {
            $email->ota()
                ->confirmation($conf);
        }

        return true;
    }

    private function parseFlight(Email $email, $root)
    {
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() == 'flight') {
                $f = $it;

                break;
            }
        }

        if (!isset($f)) {
            $f = $email->add()->flight();

            $f->general()
                ->noConfirmation();

            // Issued
            $tickets = $this->http->FindNodes("//text()[{$this->starts($this->t('E-ticket'))}]/ancestor::td[1]",
                null, "/{$this->opt($this->t('E-ticket'))}(?:\s+[A-Z\d]{2})?\s+(\d{3}[-\d]+)\s*$/u");

            if (count($tickets) === 0) {
                $tickets = $this->http->FindNodes("//text()[{$this->eq($this->t('Ticket details'))}]/ancestor::table[2]/descendant::text()[contains(normalize-space(), '-')]", null, "/{$this->opt($this->t('Ticket'))}\s*(\d{3}\-\d+)/");
            }

            if (!empty($tickets)) {
                $f->issued()
                    ->tickets($tickets, false);
            }

            $account = $this->http->FindNodes("//text()[{$this->starts($this->t('Frequent Flyer number'))}]/ancestor::td[1]",
                null, "/{$this->opt($this->t('Frequent Flyer number'))}[\s\:]*([A-Z\d]{7,})$/u");

            if (!empty($account)) {
                $f->setAccountNumbers(array_filter(array_unique($account)), false);
            }
        }

        $s = $f->addSegment();

        $xpathTime = 'contains(translate(normalize-space(),"0123456789","dddddddddd"),"d:dd")';
        $dateRelative = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $root));

        // Airline
        $node = implode("\n", $this->http->FindNodes("(.//text()[{$xpathTime}])[1]/ancestor::td[1]/preceding::text()[normalize-space()][1]/ancestor::*[not(.//text()[{$xpathTime}])][last()]//text()[normalize-space()]", $root));

        if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<fn>\d{1,5})\s*\n/", $node, $m)) {
            $s->airline()
                ->name($m['al'])
                ->number($m['fn'])
            ;
        }

        $conf = $this->getParam($this->t('Airline Booking Reference'), $root);

        if (!empty($conf)) {
            $this->allConfirmations[] = $conf;
            $s->airline()
                ->confirmation($conf);
        }

        $sXpath = "descendant::text()[$xpathTime][1]/ancestor::tr[1]/ancestor::*[1]/tr[normalize-space()]";

        $routeRe = "/^\s*(?<date>.+\d+:\d+(?: *[ap]m?)?)\s*\n\s*(?<name>.+?)\s*(?:\n\s*{$this->opt($this->t('Terminal'))} *: *(?<terminal>[\w \-]+))?\s*$/i";

        // Departure
        $departure = implode("\n", $this->http->FindNodes($sXpath . "[1]/*", $root));

        if (preg_match($routeRe, $departure, $m)) {
            $s->departure()
                ->noCode()
                ->name($m['name'])
                ->date($this->normalizeDateRelative($m['date'], $dateRelative))
                ->terminal(trim($m['terminal'] ?? null), true, true)
            ;
        }
        // Arrival
        $arrival = implode("\n", $this->http->FindNodes($sXpath . "[2]/*", $root));

        if (preg_match($routeRe, $arrival, $m)) {
            $s->arrival()
                ->noCode()
                ->name($m['name'])
                ->date($this->normalizeDateRelative($m['date'], $dateRelative))
                ->terminal(trim($m['terminal'] ?? null), true, true)
            ;
        }

        // Extra
        $s->extra()
            ->duration($this->http->FindSingleNode($sXpath . "[3]/*[1]", $root, true, "/^\s*(\d+h ?\d+m)\s*\(/"))
            ->status($this->getParam($this->t('Booking status'), $root))
        ;
        $class = $this->getParam($this->t('Class'), $root);

        if (preg_match("/^(?<cabin>.+?)\s*\(\s*(?<code>[A-Z]{1,2})\s*\)\s*$/", $class, $m)) {
            $s->extra()
                ->cabin($m['cabin'])
                ->bookingCode($m['code']);
        } elseif (!empty($class)) {
            $s->extra()
                ->cabin($class);
        }
        $seats = preg_split('/\s*,\s*/', $this->getParam($this->t('Seat'), $root));

        if (!empty($seats)) {
            $s->extra()
                ->seats($seats);
        }

        if (count($f->getSegments()) === 0) {
            $email->removeItinerary($f);
        }

        return true;
    }

    private function parseHotel(Email $email, $root)
    {
        $h = $email->add()->hotel();

        $conf = str_replace(' ', '', $this->getParam($this->t('Confirmation number'), $root, "([A-Z \d]{5,})"));

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Hotel']/ancestor::tr[2]/following::tr[1]/descendant::td[3]", $root, true, "/{$this->opt($this->t('Conf:'))}\s*(\d{5,})/");
        }

        if (!empty($conf)) {
            $this->allConfirmations[] = $conf;
            $h->general()
                ->confirmation($conf);
        } else {
            $h->general()
                ->noConfirmation();
        }

        $h->general()
            ->status($this->getParam($this->t('Booking status'), $root))
            ->cancellation($this->getParam($this->t("Cancellation policy"), $root), true, true);

        // Hotel
        $xpathH = ".//text()[{$this->starts($this->t('Confirmation number'))}]/ancestor::tr[1]/following::td[not(.//td)][normalize-space()][1]/ancestor::td[position() < 3][following-sibling::td/descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}]][1][count(.//tr[not(.//tr)][normalize-space()]) = 2]";
        $hotelName = $this->http->FindSingleNode($xpathH . "/descendant::tr[not(.//tr)][normalize-space()][1]", $root);

        if (empty($hotelName)) {
            $hotelName = $this->http->FindSingleNode("./descendant::text()[normalize-space()='Hotel']/ancestor::tr[1]/following::tr[1]/descendant::td[1]", $root);
        }

        $address = $this->http->FindSingleNode($xpathH . "/descendant::tr[not(.//tr)][normalize-space()][2]", $root);
        $hotelText = '';

        if (empty($address)) {
            $hotelText = implode("\n", $this->http->FindNodes("./descendant::text()[normalize-space()='Hotel']/ancestor::tr[2]/following::tr[1]/descendant::td[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match("/(?:Htl\:(?<hotelName>.+))\n(?:Adr\:)\s*(?<address>.+)\n(?:City\:)\s*(?<city>.+)\n*(?:Price|Tel\:)/su", $hotelText, $m)
            || preg_match("/(?:Htl\:(?<hotelName>.+))\n(?:City\:)\s*(?<city>.+)\n*(?:Price|Tel\:)/su", $hotelText, $m)) {
                if (isset($m['address']) && !empty($m['address'])) {
                    $address = str_replace("\n", " ", $m['city'] . ', ' . $m['address']);
                } else {
                    $address = str_replace("\n", " ", $m['city']);
                }

                $hotelName = $m['hotelName'];
            }
        }

        $h->hotel()
            ->name($hotelName)
            ->address($address);

        $dateRelative = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $root));
        $xpathB = ".//tr[count(.//text()[normalize-space()]) = 2 and descendant::text()[normalize-space()][1][{$this->eq($this->t('Check-in'))}] and descendant::text()[normalize-space()][2][{$this->eq($this->t('Check-out'))}]]/following-sibling::tr[normalize-space()][1][count(.//text()[normalize-space()]) = 2]";
        $h->booked()
            ->checkIn($this->normalizeDateRelative($this->http->FindSingleNode($xpathB . "/descendant::text()[normalize-space()][1]", $root), $dateRelative))
            ->checkOut($this->normalizeDateRelative($this->http->FindSingleNode($xpathB . "/descendant::text()[normalize-space()][2]", $root), $dateRelative))
        ;

        $guests = $this->getParam($this->t('Occupancy'), $root);

        if (preg_match("/^\s*(\d+) {$this->opt($this->t('Adult'))}/i", $guests, $m)) {
            $h->booked()
                ->guests($m[1]);
        }

        // Price
        $total = $this->getParam($this->t('Estimated total'), $root);

        if (empty($total) && preg_match("/{$this->opt($this->t('Price:'))}\s*([\d\.\,\']+\s*[a-z]{3})/u", $hotelText, $m)) {
            $total = $m[1];
        }

        if (preg_match("#^\s*(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $total, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[a-z]{3})\s*$#", $total, $m)
        ) {
            $h->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        }

        $this->detectDeadLine($h);

        return true;
    }

    private function parseTrain(Email $email, $root)
    {
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() == 'train') {
                $t = $it;

                break;
            }
        }

        if (!isset($t)) {
            $t = $email->add()->train();

            $t->general()
                ->noConfirmation();
        }
        $conf = $this->http->FindSingleNode(".//td[not(.//td)][{$this->starts($this->nextColumn($this->t('Railway Booking Reference')))}][not({$this->eq($this->nextColumn($this->t('Railway Booking Reference')))})]",
            $root, true, "/:\s*([A-Z\d]{5,7})\s*$/");

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode(".//td[{$this->eq($this->nextColumn($this->t('Railway Booking Reference')))}]/following-sibling::*[normalize-space()][1]",
                $root, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        }

        if (!empty($conf) && !in_array($conf, array_column($t->getConfirmationNumbers(), 0))) {
            $this->allConfirmations[] = $conf;
            $t->general()
                ->confirmation($conf);
        }

        $s = $t->addSegment();

        $xpathTime = 'contains(translate(normalize-space(),"0123456789","dddddddddd"),"d:dd")';
        $dateRelative = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $root));

        $sXpath = "descendant::*[count(*) = 2][*[1][count(.//text()[$xpathTime]) = 1] and *[2][count(.//text()[$xpathTime]) = 1]]/ancestor::*[1]/*";

        $routeRe = "/^\s*(?<date>.+\d+:\d+(?: *[ap]m?)?)\s*\n\s*(?<name>.+?)\s*$/i";

        // Departure
        $departure = implode("\n", $this->http->FindNodes($sXpath . "/*[1]", $root));

        if (preg_match($routeRe, $departure, $m)) {
            $s->departure()
                ->name($m['name'])
                ->date($this->normalizeDateRelative($m['date'], $dateRelative))
            ;
        }
        // Arrival
        $arrival = implode("\n", $this->http->FindNodes($sXpath . "/*[2]", $root));

        if (preg_match($routeRe, $arrival, $m)) {
            $s->arrival()
                ->name($m['name'])
                ->date($this->normalizeDateRelative($m['date'], $dateRelative))
            ;
        }

        // Extra
        $node = implode("\n", $this->http->FindNodes(".//text()[{$this->starts($this->t('Train'))}]/ancestor::*[not(.//text()[{$xpathTime}])][last()]//text()[normalize-space()]", $root));

        if (preg_match("/^\s*{$this->opt($this->t('Train'))} (?<name>.+) (?<number>\d+)\s*(?:\n|$)/", $node, $m)) {
            $s->extra()
                ->service($m['name'])
                ->number($m['number'])
            ;
        }
        $s->extra()
            ->status($this->getParam($this->t('Booking status'), $root))
            ->bookingCode($this->getParam($this->t('Class'), $root))
        ;

        $seats = preg_split('/\s*,\s*/', $this->getParam($this->t('Seat'), $root));

        if (!empty($seats)) {
            $s->extra()
                ->seats($seats);
        }

        return true;
    }

    private function parseTransfer(Email $email, $root)
    {
        foreach ($email->getItineraries() as $it) {
            if ($it->getType() == 'transfer') {
                $t = $it;

                break;
            }
        }

        if (!isset($t)) {
            $t = $email->add()->transfer();

            $t->general()
                ->noConfirmation();
        }

        $s = $t->addSegment();

        $dateRelative = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $root));

        $sXpath = ".//text()[normalize-space()][1][{$this->eq($this->t('Pick Up:'))} or {$this->eq($this->t('Drop Off:'))}]/ancestor::tr[position() < 3][following-sibling::tr][1]/ancestor::*[1]";

        // Departure
        $departure = '';

        foreach ($this->http->XPath->query($sXpath . "/descendant::tr[not(.//tr)]/td[1]", $root) as $row) {
            $departure .= "\n" . implode(' ', $this->http->FindNodes(".//text()[normalize-space()]", $row));
        }

        if (preg_match("/(?:{$this->opt($this->t('Pick Up:'))}|{$this->opt($this->t('Drop Off:'))})\s*(?<date>.+)\n\s*(?<address>\S[\s\S]+?)(?:\n{$this->opt($this->t('Phone'))}\s*:\s*.*)?\s*$/", $departure, $m)) {
            if (preg_match("/\s*\(\s*([A-Z]{3})\s*\)\s*$/", $m['address'], $mt)) {
                $s->departure()
                    ->code($mt[1]);
            }
            $s->departure()
                ->address($m['address'])
                ->date($this->normalizeDate($m['date']))
            ;
        }
        // Arrival
        $arrival = '';

        foreach ($this->http->XPath->query($sXpath . "/descendant::tr[not(.//tr)]/td[2]", $root) as $row) {
            $arrival .= "\n" . implode(' ', $this->http->FindNodes(".//text()[normalize-space()]", $row));
        }

        if (preg_match("/^\s*(?<address>\S[\s\S]+?)(?:\n{$this->opt($this->t('Phone'))}\s*:\s*.*)?\s*$/", $arrival, $m)) {
            if (preg_match("/\s*\(\s*([A-Z]{3})\s*\)\s*$/", $m['address'], $mt)) {
                $s->arrival()
                    ->code($mt[1]);
            }
            $s->arrival()
                ->address($m['address'])
                ->noDate()
            ;
        }

        return true;
    }

    private function parseRental(Email $email, $root)
    {
        $r = $email->add()->rental();

        $conf = str_replace(' ', '', $this->getParam($this->t('Confirmation number'), $root, "([A-Z \d]{5,})"));
        $this->allConfirmations[] = $conf;
        $r->general()
            ->confirmation($conf)
            ->status($this->getParam($this->t('Booking status'), $root))
        ;

        $dateRelative = $this->normalizeDate($this->http->FindSingleNode("descendant::td[not(.//td)][normalize-space()][1]", $root));

        $xpathB = ".//tr[count(*) = 2 and *[1][{$this->starts($this->t('Pick Up'))}] and *[2][{$this->starts($this->t('Drop Off'))}]][following-sibling::tr[normalize-space()][1][count(*) = 2]]";

        // Pick Up
        $r->pickup()
            ->date($this->normalizeDateRelative($this->http->FindSingleNode($xpathB . "/*[1]", $root, true, "/^.+?:(.+)/"), $dateRelative))
            ->location($this->http->FindSingleNode($xpathB . "/following-sibling::tr[normalize-space()][1]/*[1]", $root))
            ->phone($this->http->FindSingleNode($xpathB . "/following-sibling::tr[normalize-space()][2]/*[1]", $root, true, "/^\s*{$this->opt($this->t('Phone'))}\s*:\s*(.+)/"), true, true)
        ;
        // Drop Off
        $r->dropoff()
            ->date($this->normalizeDateRelative($this->http->FindSingleNode($xpathB . "/*[2]", $root, true, "/^.+?:(.+)/"), $dateRelative))
            ->location($this->http->FindSingleNode($xpathB . "/following-sibling::tr[normalize-space()][1]/*[2]", $root))
            ->phone($this->http->FindSingleNode($xpathB . "/following-sibling::tr[normalize-space()][2]/*[2]", $root, true, "/^\s*{$this->opt($this->t('Phone'))}\s*:\s*(.+)/"), true, true)
        ;

        $type = $this->getParam($this->t('Car type'), $root);
        $r->car()
            ->type($type, true, true);

        // Price
        $total = $this->getParam($this->t('Estimated total'), $root);

        // *SX*EUR 446.01 11D
        if (preg_match("#^\s*.*\b(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)\s+\d+D\s*$#", $total, $m)
            || preg_match("#^\s*.*\b(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s+\d+D\s*\s*$#", $total, $m)
            || preg_match("#^(?<currency>[A-Z]{3})\s*(?<amount>\d[\d\., ]*)$#", $total, $m)
        ) {
            $r->price()
                ->total(PriceHelper::parse($m['amount'], $m['currency']))
                ->currency($m['currency'])
            ;
        } /*elseif (!empty($total)) {
            $r->price()
                ->total(null);
        }*/

        return true;
    }

    private function getParam($field, $root = null, $regexp = '(.+)')
    {
        return $this->http->FindSingleNode(".//text()[{$this->starts($field)}]/ancestor::*[not({$this->eq($field)}) and not({$this->eq($this->nextColumn($field))})][1]",
            $root, true, "/:\s*{$regexp}$/u");
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

        $in = [
            // MI, 28 Juni 2023
            '/^\s*[\w\-]+,\s*(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})\s*$/iu',
            // 星期一 2023年05月29日
            '/^\s*[[:alpha:]]+\s*(\d{4})\s*年\s*(\d{2})\s*月\s*(\d{1,2})\s*日\s*$/iu',
        ];
        $out = [
            '$1 $2 $3',
            '$3.$2.$1',
        ];

        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('date replace = ' . print_r( $date, true));
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }

    private function normalizeDateRelative($date, $relativeDate)
    {
        if (empty($relativeDate)) {
            return null;
        }
        // $this->logger->debug('dateR begin = ' . print_r($date, true));
        $year = date("Y", $relativeDate);
        $in = [
            //17May , 12:45
            '#^\s*(\d+)\s*([[:alpha:]]+)\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#iu',
            //17May , 12:45p
            '#^\s*(\d+)\s*([[:alpha:]]+)\s*,\s*(\d{1,2}:\d{2}\s*[ap])\s*$#iu',
            // 2023年05月06日, 12:25
            '/^\s*(\d{4})\s*年\s*(\d{2})\s*月\s*(\d{1,2})\s*日\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$/iu',
        ];
        $out = [
            '$1 $2 ' . $year . ', $3',
            '$1 $2 ' . $year . ', $3M',
            '$3.$2.$1, $4',
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('dateR replace = ' . print_r($date, true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }
        $date = EmailDateHelper::parseDateRelative($date, $relativeDate, true, $date);

        return $date;
    }

    private function opt($field, $delimiter = '/')
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function nextColumn($field)
    {
        $result = array_merge(
            preg_replace('/^( *\S.+\s*$)/', '$1:', (array) $field),
            preg_replace('/^( *\S.+\s*$)/', '$1 :', (array) $field)
        );

        return $result;
    }

    private function containsText($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function detectDeadLine(Hotel $h)
    {
        if (empty($cancellationText = $h->getCancellation())) {
            return;
        }

        if (preg_match("/Cancel on\s+(?<day>\d+)\s*(?<month>\w+)(?<year>\d{4})\s*by\s*(?<time>\d+\:\d+)\s+lt\s+to\s+avoid/", $cancellationText, $m)) {
            $h->booked()
                ->deadline(strtotime($m['day'] . ' ' . $m['month'] . ' ' . $m['year'] . ', ' . $m['time']));
        }
    }
}
