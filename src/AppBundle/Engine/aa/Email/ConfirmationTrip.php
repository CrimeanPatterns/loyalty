<?php

namespace AwardWallet\Engine\aa\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class ConfirmationTrip extends \TAccountChecker
{
    public $mailFiles = "aa/it-236980324.eml, aa/it-236995776.eml, aa/it-237012634.eml, aa/it-262096388.eml, aa/it-263974737.eml, aa/it-280777806.eml, aa/it-312593462.eml, aa/it-528567123.eml, aa/it-528633046.eml, aa/it-530088479.eml, aa/it-563671504.eml, aa/it-663414192.eml, aa/it-705693334.eml, aa/it-706077267.eml, aa/statements/it-560874157.eml";
    public $subjects = [
        // es
        'Confirmación de su viaje',
        'Ha habido un cambio en su viaje',
        // fr
        'Votre confirmation de voyage',
        // en
        'Trip On Hold',
        'Your trip confirmation',
        "There's been a change in your trip",
        'Your connection on',
        "It's time to check in",
        'Your flight with',
        'Your flight from',
        ' You\'ve been rebooked: Check-in required',
        // pt
        'Confirmação da sua viagem',
        'Houve uma alteração na sua viagem',
        // de
        'Ihre Reisebestätigung',
    ];

    public $lang = 'en';

    public $detectLang = [
        "es" => ["Confirmación y recibo de su viaje", 'Su vuelo ha cambiado'],
        "fr" => ["Votre confirmation de voyage et reçu"],
        "pt" => ["Confirmação e recibo da sua viagem", 'Seu voo foi alterado'],
        "de" => ['Ihre Reisebestätigung und Beleg'],
        "en" => [
            'Your flight changed',
            'Complete Your Booking',
            "Your trip confirmation and receipt",
            'Your trip has a connecting flight on',
            "Your trip is almost here and we're excited to see you on board",
            'Get ready for your trip with ',
            'Here\'s your itinerary',
            'Manage your trip',
            'Reminder: Flight',
            'Check flight status',
            'If you have an upgrade requested',
            'Your requested trip is now',
            'You can move flights at no charge.',
            'Your trip confirmation (',
            'You\'ve been rebooked',
        ],
    ];

    public static $dictionary = [
        "en" => [
            'statusPhrases'      => ['Your flight', 'Your requested trip is now'],
            'statusVariants'     => ['changed', 'On Hold'],
            'aaRecordLocator'    => ['American Airlines confirmation / record locator:', 'American Airlines confirmation / record locator :', 'American Airlines confirmation / Record locator:', 'American Confirmation code:', 'American Airlines confirmation code:',
                'American Airlines confirmation / Record Locator:', ],
            'confirmation code:' => ['confirmation code:', 'Record Locator:', 'Confirmation Code:', 'Confirmation Code :', 'Confirmation code:', 'Confirmation code :', 'Record locator:', 'Record Locator:', 'common_confirmationCode:'],

            'Operated By' => ['Operated By', 'Operated by', 'OPERATED BY'],
            'as'          => ['as', 'As', 'AS'], // Operated by Envoy Air as American Eagle
            //            'Seat:' => ':',
            //            'Class:' => ':',
            //            'Meals:' => ':',

            'New ticket' => ['New ticket', 'Ticket #'],
            'Join the'   => ['Join the', 'Join AAdvantage'],
            // 'Earn miles with this trip.' => '',
            //            'Total cost' => '',
            'taxesFees'       => ['Taxes & carrier-imposed fees'],
            'UPDATED FLIGHT'  => ['UPDATED FLIGHT', 'updated flight', 'Updated Flight', 'Updated flight', 'NEW FLIGHT'], // it-312593462.eml
            'ORIGINAL FLIGHT' => ['ORIGINAL FLIGHT', 'original flight', 'Original Flight', 'Original flight', 'MISSED CONNECTION'],
            'STATUS'          => ['UPDATED', 'DELAYED', 'CANCELED', 'ON TIME'],
            'CURRENT FLIGHT'  => 'CURRENT FLIGHT',
        ],
        "es" => [
            // 'statusPhrases'      => [''],
            // 'statusVariants'     => [''],
            'aaRecordLocator'    => ['American Airlines confirmation / record locator:', 'American Airlines confirmation / record locator :', 'American Airlines confirmation / Record locator:'],
            'confirmation code:' => ['código de confirmación:', 'Código de la reservación:', 'Código de confirmación:'],

            'Operated By' => ['Operado Por', 'Operado por'],
            'as'          => 'por', // Operated by Envoy Air as American Eagle
            'Seat:'       => 'Asiento:',
            'Class:'      => 'Clase:',
            'Meals:'      => 'Comidas:',

            'New ticket'                 => ['Nuevo boleto', 'N° del boleto:'],
            'Join the'                   => ['Únase a ', 'Únase a '],
            'Earn miles with this trip.' => 'Obtenga millas con este viaje',
            'Total cost'                 => 'Costo total',
            'taxesFees'                  => ['Impuestos y cargos fijados por la aerolínea'],
            'UPDATED FLIGHT'             => ['VUELO ACTUALIZADO'],
            'ORIGINAL FLIGHT'            => ['VUELO ORIGINAL'],
            'Issued:'                    => 'Emitido:',
            // 'CURRENT FLIGHT' => '',
        ],
        "fr" => [
            // 'statusPhrases'      => [''],
            // 'statusVariants'     => [''],
            'aaRecordLocator'    => ['American Airlines confirmation / record locator:', 'American Airlines confirmation / record locator :', 'American Airlines confirmation / Record locator:'],
            'confirmation code:' => ['Référence de dossier:', 'Code de confirmation:'],

            'Operated By' => ['Opéré par'],
            'as'          => 'comme', // Operated by Envoy Air as American Eagle // to translate
            'Seat:'       => 'Siège:',
            'Class:'      => 'Classe:',
            'Meals:'      => 'Repas:',

            'New ticket' => 'Nouveau billet',
            'Join the'   => 'Únase a Rejoindre',
            // 'Earn miles with this trip.' => '',
            'Total cost' => 'Coût total',
            // 'taxesFees' => [''],
            // 'UPDATED FLIGHT' => [''],
            // 'ORIGINAL FLIGHT' => [''],
            'Issued:' => 'Émise:',
            // 'CURRENT FLIGHT' => '',
        ],
        "pt" => [
            // 'statusPhrases'      => [''],
            // 'statusVariants'     => [''],
            'aaRecordLocator'    => ['American Airlines confirmation / record locator:', 'American Airlines confirmation / record locator :', 'American Airlines confirmation / Record locator:'],
            'confirmation code:' => ['Código da reserva:', 'código de confirmação:', 'Código de confirmação:'],

            'Operated By' => ['Voo operado pela'],
            'as'          => 'como', // Operated by Envoy Air as American Eagle // to translate
            'Seat:'       => 'Assento:',
            'Class:'      => 'Classe:',
            'Meals:'      => 'Refeições:',

            'New ticket'                 => ['Novo bilhete', 'N° do bilhete'],
            'Join the'                   => 'Participar do',
            'Earn miles with this trip.' => 'Ganhe milhas com esta viagem.',
            'Total cost'                 => 'Custo total',
            'taxesFees'                  => ['Taxes et frais imposés par le transporteur'],
            'UPDATED FLIGHT'             => ['VOO ATUALIZADO'],
            'ORIGINAL FLIGHT'            => ['VOO ORIGINAL'],
            //'Issued:' => '',
            // 'CURRENT FLIGHT' => '',
        ],
        "de" => [
            // 'statusPhrases'      => [''],
            // 'statusVariants'     => [''],
            // 'aaRecordLocator'    => [''],
            'confirmation code:' => ['Buchungscode:'],

            'Operated By' => ['durchgeführt von'],
            'as'          => 'als', // Operated by Envoy Air as American Eagle
            'Seat:'       => 'Sitz:',
            'Class:'      => 'Klasse:',
            'Meals:'      => 'Mahlzeiten:',

            'New ticket' => ['Neues Ticket'],
            // 'Join the' => 'Participar do',
            // 'Earn miles with this trip.' => 'Ganhe milhas com esta viagem.',
            'Total cost' => 'Gezahlte Gesamtsumme',
            // 'taxesFees'  => ['Taxes et frais imposés par le transporteur'],
            // 'UPDATED FLIGHT' => ['VOO ATUALIZADO'],
            // 'ORIGINAL FLIGHT' => ['VOO ORIGINAL'],
            //'Issued:' => '',
            // 'CURRENT FLIGHT' => '',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers['from']) && stripos($headers['from'], '@info.email.aa.com') !== false) {
            foreach ($this->subjects as $subject) {
                if (stripos($headers['subject'], $subject) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//text()[{$this->contains('American Airlines', 'AAdvantage® #:')}]")->length === 0
            && $this->http->XPath->query("//*[contains(@href,'.aa.com')] | //img[contains(@src,'.aa.com')]")->length < 2
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]info.email.aa.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $xpathTime = '(starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆:∆∆") or starts-with(translate(translate(.," ",""),"0123456789：.Hh","∆∆∆∆∆∆∆∆∆∆::::"),"∆∆:∆∆"))';

        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*?[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $allConfs = [];
        $f = $email->add()->flight();

        $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->contains($this->t('statusPhrases'))}]", null, "/{$this->opt($this->t('statusPhrases'))}[:\s]+({$this->opt($this->t('statusVariants'))})(?:\s*[,.;:!?]|$)/i"));

        if (count(array_unique($statusTexts)) === 1) {
            $status = array_shift($statusTexts);
            $f->general()->status($status);
        } else {
            $statusTexts = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('statusPhrases'))}]/following::text()[normalize-space()][1]", null, "/^{$this->opt($this->t('statusVariants'))}$/i"));

            if (count(array_unique($statusTexts)) === 1) {
                $status = array_shift($statusTexts);
                $f->general()->status($status);
            }
        }

        $aaConf = $this->http->FindSingleNode("(//*[{$this->starts($this->t('aaRecordLocator'))}])[last()]",
            null, true, "/{$this->opt($this->t('aaRecordLocator'))}\s*([A-Z\d]{5,7})\s*$/");

        if (empty($aaConf)) {
            $aaConf = $this->http->FindSingleNode("//*[{$this->eq($this->t('aaRecordLocator'))}]/following::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
        }

        if (!empty($aaConf)) {
            $allConfs[] = $aaConf;
            $f->general()
                ->confirmation($aaConf);
        }

        $dateRes = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Issued:'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('Issued:'))}\s*(.+)/");
        // $this->logger->debug($this->normalizeDate($dateRes));

        if (!empty($dateRes)) {
            $f->general()
                ->date($this->normalizeDate($dateRes));
        }

        // remove garbage
        $nodesToStip = $this->http->XPath->query("//tr[not(.//tr) and {$this->eq($this->t('Earn miles with this trip.'))}]");

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('New ticket'))}]/ancestor::table[1]/preceding::tr[not({$this->starts($this->t('Join the'))})][string-length()>3][1]", null, "/^({$patterns['travellerName']})(?: - |$)/u"));
        $isNameFull = true;

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('New ticket'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1][not({$this->starts($this->t('Join the'))}) and not({$this->starts('AAdvantage')})]", null, "/^({$patterns['travellerName']})(?: - |$)/u"));
            $isNameFull = true;
        }

        if (count($travellers) === 0) {
            $travellers = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('New ticket'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1][{$this->starts($this->t('Join the'))} or {$this->starts('AAdvantage')}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]", null, "/^({$patterns['travellerName']})(?: - |$)/u"));
            $isNameFull = true;
        }

        if (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Hi'))}]", null, "/^{$this->opt($this->t('Hi'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $isNameFull = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, $isNameFull);
        }

        $tickets = array_unique(array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('New ticket'))}]", null, "/{$this->opt($this->t('New ticket'))}\s*\(?\s*({$patterns['eTicket']})(?:\s*\)|$)/")));

        foreach ($tickets as $ticket) {
            $paxAssignTicket = trim($this->http->FindSingleNode("//text()[{$this->contains($ticket)}]/preceding::text()[normalize-space()][string-length()>1][not(contains(normalize-space(), 'Program') or contains(normalize-space(), 'Join'))][1]/ancestor::p[1]"));

            if (empty($paxAssignTicket)) {
                $paxAssignTicket = implode(" ", $this->http->FindNodes("//text()[{$this->contains($ticket)}]/preceding::text()[normalize-space()][string-length()>1][not(contains(normalize-space(), 'Program') or contains(normalize-space(), 'Join') or contains(normalize-space(), '#') or contains(normalize-space(), 'AAdvantage') or contains(normalize-space(), '*'))][1]/ancestor::td[1]/descendant::span[not(contains(normalize-space(), '#'))]"));
            }

            if (!empty($paxAssignTicket) && in_array($paxAssignTicket, $travellers) !== false) {
                $f->addTicketNumber($ticket, false, $paxAssignTicket);
            } else {
                $f->addTicketNumber($ticket, false);
            }
        }

        $accounts = array_unique(array_filter($this->http->FindNodes("//tr[not(.//tr) and {$this->starts($this->t('New ticket'))}]/preceding::text()[normalize-space()][1]/ancestor::tr[1]", null, "/AAdvantage[®]*\s*[#:\s]+([A-Z\d]{5,}|[A-Z\d]+[*]+)$/u")));

        foreach ($accounts as $account) {
            $paxAssignAccount = $this->http->FindSingleNode("//text()[{$this->contains($account)}]/ancestor::td[1]", null, true, "/^(.+)\s+\-\s*AAdvantage/");

            if (empty($paxAssignAccount)) {
                $paxAssignAccount = $this->http->FindSingleNode("//text()[{$this->contains($account)}]/ancestor::td[1][starts-with(normalize-space(), 'AAdvantage')][1]/preceding::text()[normalize-space()][1]");
            }

            if (!empty($paxAssignAccount)) {
                $f->program()->account($account, preg_match('/^.+[*]$/', $account) > 0, $paxAssignAccount);
            } else {
                $f->program()->account($account, preg_match('/^.+[*]$/', $account) > 0);
            }
        }

        $priceText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total cost'))}]/ancestor::tr[1]",
            null, true, "/{$this->opt($this->t('Total cost'))}\s*(?:\([^\)]+\))?\s*(.+)/");

        if (preg_match("/^\s*(?<amount>\d[,.‘\'\d ]*)\s*(?<currency>[^\-\d)(]+?)\s*$/u", $priceText, $matches)
            || preg_match("/^\s*(?<currency>[^\-\d)(]+?)\s*(?<amount>\d[,.‘\'\d ]*)\s*$/u", $priceText, $matches)
        ) {
            // $2,590.82    |    CLP 332.655
            $currency = $this->currency($matches['currency']);
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            //  1,31,207.00 ->  131,207.00
            $matches['amount'] = preg_replace("/^\s*(\d),(\d{2}),(\d{3}\.\d{2})\s*$/", '$1$2$3', $matches['amount']);
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $costAmounts = $taxAmounts = [];
            $costTaxRows = $this->http->XPath->query("//text()[{$this->contains($this->t('taxesFees'))}]/ancestor::tr[1]");

            foreach ($costTaxRows as $ctRow) {
                $costTax = $this->http->FindSingleNode('.', $ctRow, true, '/^[\[\s]*(.*?\d.*?)[\]\s]*$/');

                if ($costTax === null) {
                    continue;
                }

                $costTaxParts = preg_split('/(\s*[+]+\s*)+/', $costTax);

                foreach ($costTaxParts as $ctPart) {
                    if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $ctPart, $m)
                        || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $ctPart, $m)
                    ) {
                        // $1,160.01
                        $costAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                    } elseif (preg_match("/^{$this->opt($this->t('taxesFees'))}\s*(.*\d.*)$/", $ctPart, $mm)
                        && (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $mm[1], $m)
                            || preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $mm[1], $m)
                        )
                    ) {
                        // Taxes & carrier-imposed fees $135.40
                        $taxAmounts[] = PriceHelper::parse($m['amount'], $currencyCode);
                    } else {
                        $this->logger->debug('Wrong cost-tax row!');
                        $costAmounts = $taxAmounts = [];

                        break;
                    }
                }
            }

            if (count($costAmounts) > 0) {
                $f->price()->cost(array_sum($costAmounts));
            }

            if (count($taxAmounts) > 0) {
                $f->price()->tax(array_sum($taxAmounts));
            }
        }

        $depDate = null;
        $airlinesCodes = [];
        $travellers = [];
        $xpathSegments = "//tr[not(.//tr) and {$xpathTime}]/ancestor::*[count(descendant::tr[not(.//tr) and {$xpathTime}])=2][1]/ancestor::tr[1][not(following::*[{$this->starts($this->t('UPDATED FLIGHT'))}] or preceding::*[{$this->starts($this->t('ORIGINAL FLIGHT'))}])]";
        $segments = $this->http->XPath->query($xpathSegments);

        foreach ($segments as $root) {
            $depText = $this->http->FindSingleNode("./preceding::text()[normalize-space()][not({$this->eq($this->t('Manage your trip'))})][1]", $root);

            if (preg_match("/\d{4}$/", $depText)) {
                $depDate = $this->normalizeDate($depText);
            }

            $s = $f->addSegment();

            $airline = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),':')][1]/following::text()[normalize-space()][1]", $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/")
                ?? $this->http->FindSingleNode("descendant-or-self::tr[count(.//td[normalize-space()])=2]/td[last()]/descendant::tr[1]/td[last()]", $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/")
            ;

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("descendant::text()[contains(normalize-space(),':')][1]/following::text()[normalize-space()][1]/ancestor::*[1]", $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/");
            }

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("descendant-or-self::tr[count(td[normalize-space()])=2]/td[normalize-space()][last()]/descendant::td[not(.//td)][normalize-space()][1]",
                    $root, true, "/^\s*(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+/");
            }

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("descendant::text()[not(.//td)][{$this->starts($this->t('Sold as'))}][normalize-space()][1]",
                    $root, true, "/{$this->opt($this->t('Sold as'))}\s+((?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d+)\s*$/");
            }

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("descendant::img[contains(@src, 'depart-return-indicator')]/following::text()[normalize-space()][1]/ancestor::table[1]/following::text()[normalize-space()][1][not(contains(., 'AAdvantage'))]", $root);
            }

            if (preg_match("/^\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)/", $airline, $m)) {
                $s->airline()
                    ->name($m[1])
                    ->number($m[2]);
            }

            if (empty($airline) && $this->http->XPath->query("//text()[{$this->eq($this->t('CURRENT FLIGHT'))}]")->length > 0) {
                $s->airline()
                    ->noName()
                    ->noNumber();
            }

            $operator = implode(' ', $this->http->FindNodes("./descendant::text()[{$this->contains($this->t('Operated By'))}]/ancestor::tr[1]", $root));

            if (preg_match("/^{$this->opt($this->t('Operated By'))}\s+(.{2,}?)(?:\s+{$this->opt($this->t('as'))}\s+(.{2,}))?$/", $operator, $m)) {
                // Operated by ENVOY AIR as AMERICAN EAGLE

                $conf = $this->http->FindSingleNode("//text()[{$this->eq($m[1])}]/following::text()[normalize-space()][1][{$this->eq($this->t('confirmation code:'))}]/following::text()[normalize-space()][1]",
                    null, true, "/^\s*([A-Z\d]{5,7})\s*$/");

                if (empty($conf) && isset($m[2])) {
                    $conf = $this->http->FindSingleNode("//text()[{$this->eq($m[2])}]/following::text()[normalize-space()][1][{$this->eq($this->t('confirmation code:'))}]/following::text()[normalize-space()][1]",
                        null, true, "/^\s*([A-Z\d]{5,7})\s*$/");
                }

                if (!empty($conf)) {
                    $allConfs[] = $conf;
                    $s->airline()
                        ->carrierName($m[1])
                        ->carrierConfirmation($conf);
                } else {
                    $s->airline()->operator($m[1]);
                }
            }

            if (empty($s->getConfirmation()) && !($s->getAirlineName() == 'AA' && $aaConf)) {
                $airlinesCodes[] = $s->getAirlineName();
            }

            $depTime = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$xpathTime}][1]", $root, true, "/^\s*({$patterns['time']})/");
            $depCode = $this->http->FindSingleNode("./self::*[count(.//text()[string-length(normalize-space())=3]) = 2]/descendant::text()[string-length(normalize-space())=3][1]", $root, true,
                "/^([A-Z]{3})$/");
            $arrCode = $this->http->FindSingleNode("./self::*[count(.//text()[string-length(normalize-space())=3]) = 2]/descendant::text()[string-length(normalize-space())=3][2]", $root, true,
                "/^([A-Z]{3})$/");

            if (empty($this->http->FindSingleNode("./self::*[count(.//text()[string-length(normalize-space())=3]) = 2]", $root))) {
                $timeXpath = "contains(translate(., '0123456789', 'dddddddddd'), 'd:dd')";
                $depCode = $this->http->FindSingleNode("./self::*[count(.//text()[{$timeXpath}]) = 2]/descendant::text()[normalize-space()][1][following::text()[normalize-space()][position() = 1 or position() = 2][{$timeXpath}]]", $root, true,
                    "/^([A-Z]{3})$/");
                $arrCode = $this->http->FindSingleNode("(./self::*[count(.//text()[{$timeXpath}]) = 2]/descendant::text()[{$timeXpath}][2]/preceding::text()[normalize-space()][position() = 1 or position() = 2][string-length(normalize-space())=3])[1]", $root, true,
                    "/^([A-Z]{3})$/");
            }

            if (empty($depCode) && empty($arrCode)) {
                $depCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]", $root, true,
                    "/^([A-Z]{3})$/");
                $arrCode = $this->http->FindSingleNode("./descendant::text()[normalize-space()][5]", $root, true,
                    "/^\s*([A-Z]{3})\s*$/");
            }

            if (empty($depCode) || empty($arrCode)) {
                $segText = implode("\n", $this->http->FindNodes("./self::*/descendant::text()[normalize-space()]", $root));

                if (preg_match("/(?:\n|^) *(?<dCode>[A-Z]{3}) *\n(?:.*\n){0,2} *\d{1,2}:\d{2}.*\n(?:.*\n){0,4} *(?<aCode>[A-Z]{3}) *\n(?:.*\n){0,2} *\d{1,2}:\d{2}/", $segText, $m)) {
                    $depCode = $m['dCode'];
                    $arrCode = $m['aCode'];
                }
            }

            if (!empty($depCode)) {
                $name = $this->http->FindSingleNode("./descendant::text()[normalize-space()='{$depCode}'][1]/following::text()[normalize-space()][1]", $root);

                if (!preg_match("/\d+:\d+/", $name)) {
                    $s->departure()
                        ->name($name);
                }
            }
            $s->departure()
                ->code($depCode)
                ->date(($depDate && $depTime) ? strtotime($depTime, $depDate) : null);

            $arrTime = $this->http->FindSingleNode("descendant::tr[not(.//tr) and {$xpathTime}][2]", $root, true, "/^\s*({$patterns['time']})/");

            if (!empty($arrCode)) {
                $name = $this->http->FindSingleNode("./descendant::text()[normalize-space()='{$arrCode}'][1]/following::text()[normalize-space()][1]", $root);

                if (!preg_match("/\d+:\d+/", $name)) {
                    $s->arrival()
                        ->name($name);
                }
            }
            $s->arrival()
                ->code($arrCode)
                ->date(($depDate && $arrTime) ? strtotime($arrTime, $depDate) : null);

            $terminals = $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Terminal:'))}]/following::text()[normalize-space()][1]",
                $root, "/^\s*([\w \-]+?)\s*$/");

            if (count($terminals) == 2) {
                $terminals = preg_replace("/^--$/", '', $terminals);

                if (!empty($terminals[0])) {
                    $s->departure()
                        ->terminal($terminals[0]);
                }

                if (!empty($terminals[1])) {
                    $s->arrival()
                        ->terminal($terminals[1]);
                }
            }

            $seats = explode(',',
                $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Seat:'))}]/ancestor::tr[1]",
                    $root, true, "/{$this->opt($this->t('Seat:'))}\s*(.+)/"));
            $seats = array_values(array_filter(preg_replace("/^\s*(?:TBD|{$this->opt($this->t('Class:'))}.+)\s*$/", '', $seats)));

            if (count($seats) > 0) {
                $s->extra()
                    ->seats($seats);
            }

            $cabinText = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Class:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]",
                $root, true, "/{$this->opt($this->t('Class:'))}\s*(.+)/");

            if (preg_match("/^(?<cabin>\D+)\s*\((?<bookingCode>[A-Z])\)/", $cabinText, $m)) {
                $s->extra()
                    ->cabin($m['cabin'])
                    ->bookingCode($m['bookingCode']);
            } elseif (preg_match("/^\s*\((?<bookingCode>[A-Z])\)/", $cabinText, $m)) {
                $s->extra()
                    ->bookingCode($m['bookingCode']);
            }

            $meal = $this->http->FindSingleNode("./descendant::text()[{$this->contains($this->t('Meals:'))}]/following::text()[normalize-space()][1]/ancestor::tr[1]",
                $root, true, "/{$this->opt($this->t('Meals:'))}\s*(.+)/");

            if (!empty($meal)) {
                $s->extra()
                    ->meal($meal);
            }

            $status = $this->http->FindSingleNode("preceding::text()[normalize-space()][{$this->eq($this->t('STATUS'))}][1]", $root);

            if (!empty($status)
                // && $this->http->XPath->query("//*[{$this->eq($this->t('Check flight status'))}]")->length > 0
                // && $segments->length == 1
            ) {
                $s->extra()
                    ->status($status);

                if ($status == 'CANCELED') {
                    $s->extra()
                        ->cancelled();
                }
            }

            if (empty($s->getCabin())) {
                $trs = implode("\n", array_filter($this->http->FindNodes("descendant-or-self::tr[count(td[normalize-space()])=2]/td[last()]/descendant::tr[not(.//tr)][normalize-space()]",
                    $root, "/^\s*\d{1,3}[A-Z] ?\(Business\)\s*-\s*.*/")));

                if (preg_match_all("/^\s*(?<seats>\d{1,3}[A-Z]) ?\((?<cabin>Business)\)\s*-\s*(?<traveller>[[:alpha:]][[:alpha:]\-\' ]+?)\s*$/m", $trs, $m)) {
                    $s->extra()
                        ->cabin(array_shift($m['cabin']))
                        ->seats($m['seats'])
                    ;
                    $travellers = array_merge($travellers, $m['traveller']);
                }
            }
        }

        $confs = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('confirmation code:'))}]/following::text()[normalize-space()][1]", null, "/^\s*([A-Z\d]{5,7})\s*$/"));

        if (count($confs) === 0) {
            $confs = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('confirmation code:'))}]", null, "/^\s*{$this->opt($this->t('confirmation code:'))}\s*([A-Z\d]{5,7})\s*$/"));
        }

        $codes = array_diff($confs, array_unique($allConfs));

        if (count($codes) > 0 && count(array_unique($airlinesCodes)) === 1) {
            $conf = array_shift($codes);

            if ($airlinesCodes[0] == 'AA') {
                $f->general()
                    ->confirmation($conf);
            } else {
                foreach ($f->getSegments() as $seg) {
                    if (empty($seg->getConfirmation()) && $seg->getAirlineName() == $airlinesCodes[0]) {
                        $seg->airline()
                            ->confirmation($conf);
                    }
                }

                if (empty($aaConf)) {
                    $f->general()->noConfirmation();
                }
            }
        } elseif (count($codes) === 1 && count($airlinesCodes) !== 0) {
            $conf = array_shift($codes);
            $f->general()->confirmation($conf);
        }

        if (count($f->getConfirmationNumbers()) === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('confirmation code:'))} or {$this->contains(preg_replace('/\s*:\s*$/', '', $this->t('confirmation code:')))}]")->length === 0
            && count($f->getSegments()) > 1
            && count($f->getSegments()[0]->toArray()) > 9
        ) {
            $f->general()->noConfirmation();
        }

        if (count($f->getConfirmationNumbers()) === 0
            && $this->http->XPath->query("//*[{$this->contains($this->t('confirmation code:'))} or {$this->contains(preg_replace('/\s*:\s*$/', '', $this->t('confirmation code:')))}]")->length === 0
            && $this->http->XPath->query("//*[{$this->eq($this->t('Check flight status'))}]")->length > 0
            && count($f->getSegments()) == 1
        ) {
            $text = implode("\n", $this->http->FindNodes("preceding::text()[normalize-space()][not(ancestor::style)]", $segments->item(0)));

            if (!preg_match("/:\s*[A-Z\d]{5,7}\n/", $text)) {
                $f->general()->noConfirmation();
            }
        }

        if (!empty($travellers)) {
            $f->general()
                ->travellers(array_unique(array_filter($travellers)), true);
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function assignLang(): bool
    {
        foreach ($this->detectLang as $lang => $dBody) {
            if ($this->http->XPath->query("//*[{$this->contains($dBody)}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field)) . ')';
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$date in = ' . print_r($str, true));
        $in = [
            // mercredi, 8 mars 2023
            "/^\s*[-[:alpha:]]+\s*,\s*(\d{1,2})[.]?\s+(?:de\s+)?([[:alpha:]]+)(?:\s+de)?\s+(\d{4})\s*$/iu",
            //lunes, 26 de junio de 2023
            "/^\w+\,\s*(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})\s*$/iu",
            //18 de mayo de 2023
            "/^(\d+)\s*de\s*(\w+)\s*de\s*(\d{4})\s*$/",
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        // $this->logger->debug('$date out = ' . print_r($str, true));

        return strtotime($str);
    }

    private function currency(string $s): string
    {
        if (preg_match("#^\s*([A-Z]{3})\s*$#", $s)) {
            return trim($s);
        }
        $sym = [
            'R$' => 'BRL',
        ];

        foreach ($sym as $f => $r) {
            if ($s == $f) {
                return $r;
            }
        }

        return $s;
    }
}
