<?php

namespace AwardWallet\Engine\fareharbor\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AdventureOn extends \TAccountChecker
{
    public $mailFiles = "fareharbor/it-397234375.eml, fareharbor/it-399479459.eml, fareharbor/it-401277586.eml, fareharbor/it-47947597.eml, fareharbor/it-49337397.eml, fareharbor/it-49337442.eml, fareharbor/it-51241655.eml, fareharbor/it-60219661.eml, fareharbor/it-60682819.eml, fareharbor/it-64682950.eml, fareharbor/it-667735083.eml, fareharbor/it-668024430.eml, fareharbor/it-67870168.eml, fareharbor/it-69140770.eml, fareharbor/it-80089794.eml, fareharbor/it-80272666.eml";

    public $reFrom = ["@fareharbor.com"];
    public $reBody = [
        'en' => ['powered by FareHarbor', 'your FareHarbor settings'],
        'fr' => ['optimisés par FareHarbor'],
    ];
    public $reSubject = [
        'Confirmation:',
        'Reminder of your booking for',
        ' Cancelled (',
        'New online booking:',
        //it
        'Promemoria della tua prenotazione per',
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'Booking #'                       => 'Booking #',
            'Cancelled'                       => 'Cancelled', // Booking #214883646 Cancelled
            'Add to your calendar:'           => 'Add to your calendar:',
            'Your booking has been cancelled' => 'Your booking has been cancelled',
            'Google Link Map'                 => ['Google Link Map', 'Google Map Link', 'Click here for a map of the location',
                'Google Map', 'Directions', 'Click for driving directions', 'Click here for google map link',
                'Click here for directions', 'follow this link', 'Click here for map link', 'Click Here for Map',
                'Click Here for directions', 'Click Here For Swift Street location Map', 'Click here for directions',
                'Get directions ›',
            ],
            'We are located at'     => ['We are located at', 'Our address is'],
            'Meeting Location'      => ['Meeting Location', 'Meeting Location:', 'Meeting location:'],
            'Directions'            => ['Directions', 'Directions:', 'DIRECTIONS:', 'IMPORTANT NOTE:'],
            'Activity Location'     => ['Activity Location', 'Location', 'Check-In Location'],
            'addressPrefix'         => ['Address:', 'Check-in at the', 'Please meet us at the', 'Our boat is located in the', 'The tour meets at the', 'Pickup will be at', 'Location', 'Check-In Location'],
            'addressPostfix'        => ['boat ramps located', 'Please call', 'Click for directions'],
            'Taxes & Fees'          => ['Taxes & Fees', 'Fees', 'Taxes'],
            'The address is'        => ['The address is', 'The address of the event is'],
        ],
        'fr' => [
            'Booking #'                       => 'Réservation n°',
            // 'Cancelled'                       => '', //Booking #214883646 Cancelled
            'Add to your calendar:'           => ['Ajouter à votre calendrier :', 'Ajouter à votre calendrier:'],
            'Your booking has been cancelled' => 'Votre réservation a été annulée',

            'Details' => 'Détails',
            // 'Full Name:' => '',
            'View online'           => 'Voir en ligne',
            'Taxes & Fees'          => 'Taxes et frais',
            'Total'                 => 'Total',
            'All prices in'         => 'Tous les tarifs sont en',
            'Google Link Map'       => ['Obtenir des indications ›', 'Voir le lieu de prise en charge sur une carte »'],
        ],
        'it' => [
            'Booking #'             => 'Prenotazione #',
            // 'Cancelled'                       => '', //Booking #214883646 Cancelled
            'Add to your calendar:' => ['Aggiungi al tuo calendario:'],
            // 'Your booking has been cancelled' => '',

            'Details' => 'Dettagli',
            // 'Full Name:' => '',
            'View online'     => 'Visualizza online',
            'Taxes & Fees'    => 'Tasse & costi',
            'Total'           => 'Totale',
            'All prices in'   => 'Tutti i prezzi in',
            'Google Link Map' => 'Ottieni indicazioni ›',
        ],
    ];
    private $keywordProv = 'FareHarbor';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        $xpath = "//text()[{$this->starts($this->t('Add to your calendar:'))}]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length > 0) {
            $this->parseEmail($email, $roots);
        }

        if ($this->http->XPath->query("//text()[{$this->starts($this->t('Your booking has been cancelled'))}]")->length > 0
            || $this->http->XPath->query("//text()[{$this->starts($this->t('Booking #'))} and {$this->contains($this->t('Cancelled'))}]")->length > 0
        ) {
            $this->parseEmail($email, $this->http->XPath->query("."));
        }

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src, '.fareharbor.com')] | //a[contains(@href, '.fareharbor.com') or contains(@href, '/fareharbor.com')]")->length > 0) {
            foreach ($this->reBody as $reBody) {
                if ($this->http->XPath->query("//*[{$this->contains($reBody)}]")->length > 0
                    || $this->http->XPath->query("//a[contains(@href, 'messages.fareharbor.com')]")->length > 0) {
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
        $fromProv = true;

        if (!isset($headers['from']) || $this->detectEmailFromProvider($headers['from']) !== true) {
            $fromProv = false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (($fromProv || preg_match("/\b{$this->opt($this->keywordProv)}\b/i", $headers["subject"]) > 0)
                    && stripos($headers["subject"], $reSubject) !== false
                ) {
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

    private function parseEmail(Email $email, $roots): void
    {
        $order = $this->http->FindSingleNode("//text()[contains(normalize-space(), 'booking with us!')]/following::text()[starts-with(normalize-space(), 'Order #')]",
            null, true, "/[#]([A-Z]+)/");

        if (!empty($order)) {
            $email->ota()
                ->confirmation($order, 'Order #');
        }

        $currencyText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('All prices in'))}]", null, true, "/{$this->opt($this->t('All prices in'))}\s*(.+)/");

        if (in_array($currencyText, ['US dollars'])) {
            $currency = 'USD';
        }

        if (in_array($currencyText, ['Euro'])) {
            $currency = 'EUR';
        }

        if (in_array($currencyText, ['Canadian dollars'])) {
            $currency = 'CAD';
        }

        $total = $this->http->FindSingleNode("//text()[starts-with(normalize-space(), 'Will appear on your statement as')]/ancestor::table[2]", null, true, "/^[$]([\d\.]+)/u");

        if (!empty($total)) {
            $email->price()
                ->total($total)
                ->currency($currency);
        }

        $condition = '';
        $pattern = 'Booking %p% of %l%';
        $this->logger->debug('$rootCount = ' . print_r($roots->length, true));

        foreach ($roots as $rootKey => $root) {
            // DON'T FORGET USE {$condition}  - condition for 2 or more reservation
            $rootCount = $roots->length;

            if ($rootCount === 1) {
                $condition = '';
            } elseif ($rootKey !== null && $rootCount !== null) {
                if ($rootKey + 1 == $rootCount) {
                    $condition = "[preceding::text()[{$this->eq(str_replace(['%p%', '%l%'], [$rootKey + 1, $rootCount], $pattern))}]]";
                } else {
                    $condition = "[preceding::text()[{$this->eq(str_replace(['%p%', '%l%'], [$rootKey + 1, $rootCount], $pattern))}]][following::text()[{$this->eq(str_replace(['%p%', '%l%'], [$rootKey + 2, $rootCount], $pattern))}]]";
                }
            } elseif ($rootKey !== null || $rootCount !== null) {
                $condition = '[false()]';
            }

            // $this->logger->debug('$condition = ' . print_r($condition, true));

            $r = $email->add()->event();

            if ($this->http->FindSingleNode("(//text()[{$this->contains($this->t('Your booking has been cancelled'))}])[1]")
                || $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Booking #'))} and {$this->contains($this->t('Cancelled'))}])[1]")
            ) {
                $r->general()
                    ->cancelled()
                    ->status('Cancelled')
                ;
            }

            $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Add to your calendar:'))}]{$condition}/preceding::text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})][1]",
                null, false, "/{$this->opt($this->t('Booking #'))}\s*(.+)/");

            if (empty($conf)) {
                $conf = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})][1]",
                    null, false, "/{$this->opt($this->t('Booking #'))}\s*(.+)/");
            }
            $r->general()
                ->confirmation($conf,
                    $this->http->FindSingleNode("(//text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})]{$condition})[1]",
                        null, false, "/({$this->opt($this->t('Booking #'))})\s*.+/")
                );

            // travellers
            $travellers = $this->http->FindNodes("//h2[{$this->eq($this->t('Details'))}]/following::table[normalize-space()][1]{$condition}//text()[{$this->eq($this->t('Full Name:'))}]/following::text()[normalize-space()][1]");

            if (empty($travellers)) {
                $traveller = $this->http->FindSingleNode("//text()[{$this->starts($this->t('View online'))}]{$condition}/following::text()[normalize-space()!=''][1]");

                if (!empty($traveller)) {
                    $travellers = [$traveller];
                }
            }

            if (!empty($travellers)) {
                $r->general()->travellers($travellers, true);
            }

            // name event
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Add to your calendar:'))}]{$condition}/preceding::text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})]{$condition}[1]/following::text()[normalize-space()!=''][1]");

            if (empty($name)) {
                $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})]{$condition}[1]/following::text()[normalize-space()!=''][1]");
            }
            $r->place()
                ->name($name);

            // date
            $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Add to your calendar:'))}]{$condition}/preceding::text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})]{$condition}[1]/following::text()[normalize-space()!=''][2]");

            if (empty($name)) {
                $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})]{$condition}[1]/following::text()[normalize-space()!=''][2]");
            }

            $delimiter = '(?: +@ +|\s*at\s*)';

            switch ($this->lang) {
                case 'fr':
                    $delimiter = ' +à +';

                    break;

                case 'it':
                    $delimiter = ' +alle +';

                    break;
            }

            if (preg_match("/(.+?){$delimiter}(\d+:\d+(?:\s*[ap]m)?)[ ]\-[ ](\d+:\d+(?:\s*[ap]m)?)$/iu", $node, $m)) {
                $r->booked()
                    ->start($this->normalizeDate($m[1] . ', ' . $m[2]))
                    ->end($this->normalizeDate($m[1] . ', ' . $m[3]));
            } elseif (preg_match("/(.+?){$delimiter}(\d+:\d+(?:\s*[ap]m)?)[ ]\-[ ](.+?){$delimiter}(\d+:\d+(?:\s*[ap]m)?)$/iu",
                $node, $m)) {
                $r->booked()
                    ->start($this->normalizeDate($m[1] . ', ' . $m[2]))
                    ->end($this->normalizeDate($m[3] . ', ' . $m[4]));
            } elseif (preg_match("/(.+?){$delimiter}(\d+:\d+(?:\s*[ap]m)?)$/iu", $node, $m)) {
                $r->booked()
                    ->start($this->normalizeDate($m[1] . ', ' . $m[2]))
                    ->noEnd();
            } elseif (preg_match("/^[-[:alpha:]]{2,}\s*,\s*[[:alpha:]]{3,}\s+\d{1,2}\s+\d{4}$/u", $node, $m)) {
                // Tuesday, July 7 2020
                $r->booked()
                    ->start($this->normalizeDate($node))
                    ->noEnd();
            } elseif (preg_match("/^\s*([-[:alpha:]]{2,}\s*,\s*[[:alpha:]]{3,}\s+\d{1,2}\s*,\s*\d{4}) - ([-[:alpha:]]{2,}\s*,\s*[[:alpha:]]{3,}\s+\d{1,2}\s*,\s*\d{4})\s*$/u", $node, $m)) {
                // Friday, June 7, 2024 - Sunday, June 9, 2024
                $r->booked()
                    ->start($this->normalizeDate($m[1]))
                    ->end($this->normalizeDate($m[2]));
            }

            // type
            $type = 'event';
            $name = '';
            $description = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking #'))}][not({$this->contains($this->t('Cancelled'))})]{$condition}/following::text()[normalize-space()!=''][3]");

            if (preg_match("/.* (?:days?|hours?) Rentals?$/i", $description)) {
                $type = 'rental';
                $name = $r->getName();
                $duration = $description;
            } elseif (preg_match("/.* (?:days?|hours?) Rentals?$/i", $r->getName())) {
                $type = 'rental';
                $name = $description;
                $duration = $r->getName();
            }

            if ($type == 'rental') {
                $ev = $r->toArray();
                $email->removeItinerary($r);
                $r = $email->add()->rental();

                if (isset($ev['confirmationNumbers'])) {
                    foreach ($ev['confirmationNumbers'] as $conf) {
                        $r->general()
                            ->confirmation($conf[0], $conf[1]);
                    }
                }

                if (isset($ev['travellers'])) {
                    foreach ($ev['travellers'] as $conf) {
                        $r->general()
                            ->traveller($conf[0], $conf[1]);
                    }
                }
                $r->pickup()->date($ev['startDate'] ?? null);

                if (!empty($ev['startDate']) && empty($ev['endDate']) && !empty($duration)) {
                    if (preg_match("/(?<dur>.* (?:days?|hours?)) Rentals?$/i", $duration, $m)) {
                        $m['dur'] = preg_replace(['/^\s*half days?\s*$/i', '/^\s*half hours?$/i'],
                            ['12 hours', '30 minutes'], $m['dur']);
                        $m['dur'] = str_ireplace(['one', 'two', 'three', 'four', 'five'], ['1', '2', '3', '4', '5'],
                            $m['dur']);

                        $m['dur'] = preg_replace("/^\s*(?:\d |.*[[:alpha:]]+\s+)(\d+\s*(?:days?|hours?))$/i", '$1', $m['dur']);

                        if (preg_match("/^\s*\d+\s*(?:days?|hours?)$/i", $m['dur'])) {
                            $ev['endDate'] = strtotime('+' . $m['dur'], $ev['startDate']);
                        }
                    }
                }
                $r->dropoff()->date($ev['endDate'] ?? null);

                $r->car()->model($name);
            } else {
                $r->place()->type(EVENT_EVENT);
            }

            // address, phone

            $value = $this->getAddress($condition);
            $address = $value['address'] ?? null;
            $phone = $value['phone'] ?? null;

            if (!empty($address)) {
                if ($r->getType() == 'rental') {
                    $r->pickup()->location($address);
                    $r->dropoff()->same();
                } else {
                    $r->place()->address($address);
                }
            }

            if (!empty($phone)) {
                if ($r->getType() == 'rental') {
                    $r->pickup()->phone($phone);
                    $r->dropoff()->same();
                } else {
                    $r->place()->phone($phone);
                }
            }

            // guests
            $node = implode("\n",
                $this->http->FindNodes("//text()[{$this->starts($this->t('Booking #'))}]{$condition}/following::text()[normalize-space()][position()<5]"));

            if ($r->getType() == 'event' && preg_match("/((?<min>\d{1,3})\s*-\s*(?<max>\d{1,3})\s*Guests?)/i", $node,
                    $m)) {
                $cost = $this->http->FindSingleNode("//text()[{$this->eq($m[1])}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1][count(descendant::text()[normalize-space()!=''])=1]{$condition}");
                $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('How many in your party?'))}]/following::text()[normalize-space()!=''][1]{$condition}",
                    null, false, "/^\d+$/");

                if (!empty($node)) {
                    $r->booked()->guests($node);
                }
            } elseif ($r->getType() == 'event') {
                $guests = $this->re("/\b(\d{1,3})\s*(?:People|Adults?|Day Visitors,|Students?|מבוגר)/iu", $node);
                $guestsChildren = $this->re("/\b(\d{1,3})\s*(?:Children|Child|Junior|Youth)/i", $node);

                if (!empty($guestsChildren)) {
                    $r->booked()->kids($guestsChildren);
                }

                if (empty($guests) && preg_match("#^\s*(\d+) Tandem Kayaks?\s*$#", $node, $m)) {
                    $guests = $m[1] * 2;
                }

                if (isset($guests)) {
                    $r->booked()->guests($guests);
                }
            }

            // sums
            $currencyText = $this->http->FindSingleNode("//text()[{$this->starts($this->t('All prices in'))}]", null,
                true, "/{$this->opt($this->t('All prices in'))}\s*(.+)/");

            if (in_array($currencyText, ['US dollars'])) {
                $currency = 'USD';
            }

            if (in_array($currencyText, ['Euro'])) {
                $currency = 'EUR';
            }

            $fees = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Taxes & Fees'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1][count(descendant::text()[normalize-space()])=1]{$condition}");
            $total = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Total'))}]/ancestor::td[1]/following-sibling::td[normalize-space()!=''][1][count(descendant::text()[normalize-space()!=''])=1]{$condition}");
            $total = $this->getTotalCurrency($total);

            if ($total['total'] !== null && !$r->getCancelled()) {
                $r->price()->total($total['total'], $currency ?? null);

                if (!empty($currency) && preg_match("/^[A-Z]{3}$/", $total['currency']) == 0) {
                    $r->price()->currency($currency);
                } else {
                    $r->price()->currency($total['currency']);
                }
            }

            if ($fees !== null && !$r->getCancelled()) {
                $fees = $this->getTotalCurrency($fees);

                if ($fees['total'] !== null) {
                    $r->price()->tax($fees['total']);

                    if (!$r->getPrice()->getCurrencyCode() && !$r->getPrice()->getCurrencySign()) {
                        if (!empty($currency) && preg_match("/^[A-Z]{3}$/", $fees['currency']) == 0) {
                            $r->price()->currency($currency);
                        } else {
                            $r->price()->currency($fees['currency']);
                        }
                    }
                }
            }

            if (isset($cost) && !$r->getCancelled()) {
                $cost = $this->getTotalCurrency($cost);

                if ($cost['total'] !== null) {
                    $r->price()->cost($cost['total']);

                    if (!$r->getPrice()->getCurrencyCode() && !$r->getPrice()->getCurrencySign()) {
                        if (!empty($currency) && preg_match("/^[A-Z]{3}$/", $cost['currency']) == 0) {
                            $r->price()->currency($currency);
                        } else {
                            $r->price()->currency($cost['currency']);
                        }
                    }
                }
            }
        }
    }

    private function getAddress($condition = ''): array
    {
        switch ($this->lang) {
            case 'en':
                $result = $this->getAddressEn($condition);

                break;

            case 'fr':
                $result = $this->getAddressFr($condition);

                break;

            case 'it':
                $result = $this->getAddressIt($condition);

                break;
        }

        if (empty($result)) {
            $result = ['address' => null, 'phone' => null];
        }

        return $result;
    }

    private function getAddressEn($condition = ''): array
    {
        ///////////////////////////////////////////////////////////////////////
        // DON'T FORGET USE {$condition}  - condition for 2 or more reservation
        ///////////////////////////////////////////////////////////////////////
        $address = $phone = null;

        // it-47947597.eml
        $address = $this->http->FindSingleNode("//td[{$this->starts($this->t('Please meet us at:'))} and descendant::td[not(.//td)][normalize-space()][1][{$this->eq($this->t('Please meet us at:'))}] and descendant::td[not(.//td)]//img][following-sibling::*[normalize-space()='Get directions ›']]/descendant::td[not(.//td)][normalize-space()][2]{$condition}");

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//td[{$this->starts($this->t('Our Location:'))} and descendant::td[not(.//td)][normalize-space()][1][{$this->eq($this->t('Our Location:'))}] and descendant::td[not(.//td)]//img][following-sibling::*[normalize-space()='Get directions ›']]/descendant::td[not(.//td)][normalize-space()][2]{$condition}");
        }

        if (empty($address)) {
            $addressCell = implode(' ',
                $this->http->FindNodes(".//*[self::h1 or self::h2][{$this->eq($this->t('Activity Location'))} or {$this->contains('departs from:')}]/following::text()[normalize-space()][1]/ancestor::*[self::li or self::p][1]/descendant::text()[normalize-space()]{$condition}"));

            if (preg_match("/(?:{$this->opt($this->t('addressPrefix'))})?\s*(.{3,})$/i", $addressCell, $m)) {
                $address = $m[1];
            }
        }

        if (empty($address)) {
            // it-49337397.eml
            $address = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Directions'))}]/following::*[normalize-space()!=''][1]/descendant::text()[{$this->contains($this->t('We are located at'))}]{$condition}",
                null, false, "/{$this->opt($this->t('We are located at'))}[ ]*(.{3,}?)(?:[ ]*[.!]|$)/");
            $phone = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Directions'))}]/following::*[normalize-space()][1]/descendant::text()[{$this->contains('call us at')}]{$condition}",
                null, false, "/{$this->opt('call us at')}\s*([+(\d][-. \d)(]{5,}[\d)])\s*(?:\.|$)/");
        }

        if (empty($address)) {
            // it-60219661.eml
            $address = $this->http->FindSingleNode("//text()[{$this->starts('Before we begin:')}]/ancestor::table[1]/ancestor::td[1]{$condition}", null, false, "/^{$this->opt('Before we begin:')}\s*(.{3,75})$/");
        }

        if (empty($address)) {
            // it-51241655.eml
            $address = $this->http->FindSingleNode("//*[{$this->starts($this->t('Our Location:'))}]/ancestor-or-self::*[./a[{$this->contains($this->t('Google Maps'))}]][1]{$condition}", null, false, "/{$this->opt($this->t('Our Location:'))}\s*(.{3,}?)[-\s]*{$this->t('Google Maps')}/")
            ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('Our Location:'))}]/ancestor::table[1]/ancestor::td[1]{$condition}", null, false, "/^{$this->opt($this->t('Our Location:'))}\s*(.{3,75}?)[-\s]*$/");
        }

        if (empty($address)) {
            // Please meet us at the Keauhou Bay boat ramps located down the road from
            $address = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('addressPrefix'))}]{$condition}", null, false, "/^{$this->opt($this->t('addressPrefix'))}\s+(.{3,}?)\s+{$this->opt($this->t('addressPostfix'))}/")
            // ?
            ?? $this->http->FindSingleNode("//text()[{$this->starts('Check In information')}]{$condition}", null, false, "/^{$this->opt('Check In information')}\s*-\s*(.{3,75})$/")
            ?? $this->http->FindSingleNode(".//text()[{$this->eq($this->t('addressPrefix'))}]/ancestor::p[1]{$condition}", null, false, "/^{$this->opt($this->t('addressPrefix'))}\:?\s+(.{3,}?)\.\s+{$this->opt($this->t('addressPostfix'))}/");
        }

        if (empty($address)) {
            // it-60219661.eml
            $address = implode(' ', $this->http->FindNodes(".//*[ count(table)=2 and table[1][{$this->eq($this->t('Please meet us at:'))}] ]/table[2]/descendant::text()[normalize-space()]{$condition}"));
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//h1[{$this->eq($this->t('Please meet us at:'))}]/following::text()[normalize-space()][1][not(ancestor::a)]{$condition}");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->starts('Please arrive')}]{$condition}", null, false, "/{$this->opt($this->t('addressPrefix'))}\s*(.{3,75}?)\s*{$this->opt($this->t('addressPostfix'))}/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[contains(normalize-space(),'The starting address is')]{$condition}", null, true, "/The starting address is\s*(.{3,75})$/");
        }

        if (empty($address)) {
            // it-67870168.eml
            // Argonaut Hotel - Argonaut Hotel (495 Jefferson St) (8:45am)
            $address = $this->http->FindSingleNode("//text()[{$this->contains('Please select your accommodations from the list below')}]/following::text()[normalize-space()][1]{$condition}", null, false, '/^(.{3,75}?)\s*\(\s*\d{1,2}:/');
        }

        if (empty($address)) {
            $addr = array_filter($this->http->FindNodes(".//text()[{$this->eq($this->t('Meeting Location'))}]/following::text()[normalize-space()!=''][position()<3]{$condition}", null, "#^\d.{5,50} [A-Z][a-z]+, [A-Z]{2}\.?$#"));

            if (count($addr) == 1) {
                $address = array_shift($addr);
            } elseif (count($addr) == 0) {
                $addr = array_filter($this->http->FindNodes(".//text()[{$this->eq($this->t('Meeting Location'))}]/following::text()[normalize-space()!=''][position()<3]{$condition}", null, "#^\d.{5,50} [A-Z][a-z]+, [A-Za-z]{2}[,\s]+\d{5}$#"));

                if (count($addr) == 1) {
                    $address = array_shift($addr);
                }
            }
        }

        if (empty($address)) {
            $link = $this->http->FindSingleNode("(//text()[" . $this->eq($this->t('Google Link Map')) . "]/ancestor::a[1]/@href{$condition})[1]");

            if (empty($link)) {
                $link = $this->http->FindSingleNode("//text()[" . $this->contains('www.google.com/maps/') . "]/ancestor::a[1]/@href{$condition}");
            }

            if (empty($link)) {
                $link = $this->http->FindSingleNode(".//text()[" . $this->contains('https://www.google.com/maps/place/') . "]{$condition}");
            }

            if (empty($link)) {
                $link = $this->http->FindSingleNode("(//a[contains(@href, '/goo.gl/maps/') or contains(@href, 'https://www.google.com/maps/place/')]{$condition})[1]/@href");
            }

            if (empty($link)) {
                $link = $this->http->FindSingleNode("(//a[contains(@href, 'https://www.google.com/maps/place/')]{$condition})[1]/@href");
            }

            if (empty($link)) {
                $link = $this->http->FindSingleNode("(//text()[contains(., 'https://g.page/') or contains(., 'https://goo.gl/maps/') or contains(., 'https://maps.app.goo.gl/')]{$condition})[1]", null, true,
                    "/^.*\b((?:" . preg_quote('https://g.page/', '/') . "|" . preg_quote('https://goo.gl/maps/', '/') . "|" . preg_quote('https://maps.app.goo.gl/', '/') . ")\S+?)[\)]?$/");
            }

            if (!empty($link)) {
                $http = clone $this->http;
                $http->GetURL($link);
                $url = $http->currentUrl();

                $address = $http->FindSingleNode("//meta[@itemprop = 'name']/@content");

                if (empty($address) || stripos($address, 'Google Maps') !== false) {
                    $address = null;

                    if (preg_match("/https:\/\/www\.google\.com(?:\.pr)?\/maps\/place\/([^\/]{5,})/", $url, $m)) {
                        // it-49337442.eml
                        $address = urldecode($m[1]);
                    } elseif (preg_match("/https:\/\/www\.google\.com(?:\.pr)?\/maps\/dir\/\/([^\/]+?)\/@/", $url, $m) && strlen($url) > 10) {
                        // it-49337442.eml
                        $address = urldecode($m[1]);
                    } elseif (preg_match("/" . preg_quote('https://www.google.com/maps/dir/?api=1&destination=', '/') . "([^\/&]+?)($|&|\/)/", $url, $m) && strlen($url) > 10) {
                        $address = urldecode($m[1]);
                    }
                }
            }
        }

        if (empty($address)) {
            // Meet at the floating dock located at 10 South New River Dr. East, Fort Lauderdale, FL 33301 (in front of the Historic DownTowner approximately 5-10 minutes before departure.
            $address = $this->http->FindSingleNode("//text()[{$this->contains('floating dock located at')}]{$condition}", null, false, "/{$this->opt('floating dock located at')}\s*(.{3,75}?)\s*\(/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Directions'))}]/following::*[normalize-space()!=''][1]/descendant::text()[{$this->contains($this->t('We are located at'))}]/ancestor::p[1]{$condition}",
                null, false, "/{$this->opt($this->t('We are located at'))}\s*(.+?)\./");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->eq(['Meet us at the:', 'Location:'])}]/ancestor::p[1]{$condition}", null, false, "/^\s*{$this->opt(['Meet us at the:', 'Location:'])}\s*(.+?)$/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->starts(['Meet us at the:', 'We are located at:'])}]/ancestor::p[1]{$condition}",
                null, false, "/^\s*{$this->opt(['Meet us at the:', 'We are located at:'])}\s*(.+?[\s,]+[a-z]{2}[,\s]+\d{5})(?:\s*$| neighbors to)/i");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->starts(['Meet us at the:', 'We are located at:', 'Check in at'])}]/ancestor::p[1]{$condition}",
                null, false, "/^\s*{$this->opt(['Meet us at the:', 'We are located at:', 'Check in at'])}\s*(.+?[,\s]+\d{5})(?:\s*$| neighbors to|\s*View Map)/i");
            $address = str_replace('located at ', '', $address);
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(),'Our office is located at')]/ancestor::p[normalize-space()][1]{$condition}", null, false, "/^Our office is located at ([^\.]*\d[^\.]*), please arrive [\w ]+ prior to your/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->eq(['Please provide the address of the hotel you are staying.:', 'Hotel Address:'])}]/following::text()[normalize-space()][1]{$condition}", null, true, "/^[^:]*\d[^:]*$/");
        }

        if (empty($address)) {
            // very strict and universal [always last!!!] (examples: it-60219661.eml)
            $address = $this->http->FindSingleNode(".//tr[ count(*)=2 and *[1][normalize-space()='' and descendant::img[contains(@src,'/e-pin-location.')]] and *[2][normalize-space()] ]/ancestor::table[1]/following-sibling::table[normalize-space()]", null, true, "/^([^:]{3,}\d[^:]*|[^:]*\d[^:]{3,})$/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->starts($this->t('addressPrefix'))}]", null, true, "/^{$this->opt($this->t('addressPrefix'))}(.+)/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->starts('Sea Quest check in is located at this address')}]/ancestor::tr[1]", null, true, "/{$this->opt('Sea Quest check in is located at this address')}\s*(.{3,75}?)\s*{$this->opt('Please call reservations for further information')}/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[{$this->contains('is located at')}]/ancestor::tr[1]", null, true, "/{$this->opt('is located at')}\s*(.{3,75}?)\s*{$this->opt('Please Note')}/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[{$this->contains($this->t('The address is'))}]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('The address is'))}\s*(.{3,75}?)\s*Park anywhere/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("(.//text()[{$this->contains($this->t('The address is'))}])[1]/ancestor::tr[1]", null, true, "/{$this->opt($this->t('The address is'))}([^.]+?[\s,]+[a-z]{2}[,\s]+\d{5})\s*(?:\.|$)/i");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Check-in is located at') or {$this->starts($this->t('Please meet us at:'))} or normalize-space()='Parking:']/following::text()[normalize-space()][position()<3]/ancestor::a[1]", null, true, "/^.*\d.*$/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[normalize-space()='What’s the location?']/following::text()[normalize-space()][1]");
        }

        if (empty($address)) {
            $addressSrts = implode("\n", $this->http->FindNodes(".//text()[starts-with(normalize-space(),'Please arrive by')]/following::text()[normalize-space()][1]/ancestor::div[1]/p[position() < 3]"));

            if (preg_match_all("/^.*\d.*, [A-Z]{2} \d{5}$/m", $addressSrts, $addressMatches)) {
                if (count($addressMatches[0]) === 1 && strlen($addressMatches[0][0]) > 10 && strlen($addressMatches[0][0]) < 100) {
                    $address = $addressMatches[0][0];
                }
            }
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'Click here to view our location on Google Maps')]/preceding::text()[string-length()>5][1]/ancestor::p[1][{$this->starts($this->t('addressPrefix'))}]",
                null, true, "/^{$this->opt($this->t('addressPrefix'))}\s+(.+)/s");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[contains(normalize-space(), 'Our physical address is:')]/following::text()[normalize-space()][1]/ancestor::p[1][contains(normalize-space(), '• Our location is located in the')]",
                null, true, "/^(.+)[\s\•]{$this->opt($this->t('Our location is located in the'))}/s");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(),'Check-in is located in front of the')]", null, true, "/Check-in is located in front of the (.+?)\./");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(),'(IF USING GPS OR GOOGLE MAPS, SEARCH FOR')]", null, true,
                "/\(IF USING GPS OR GOOGLE MAPS, SEARCH FOR DIRECTIONS TO (.+)\)\s*$/");
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//*[normalize-space() = 'Please meet us at :']/following-sibling::*[1]", null, true, "/.*\b\d+\b.*/");

            if (strlen($address) < 20) {
                $address = null;
            }
        }

        if (empty($address)) {
            $address = $this->http->FindSingleNode(".//*[normalize-space() = 'Please meet us:' or normalize-space() = 'Are you ready to reach our meeting point? Please meet us:']/following-sibling::*[1]");

            if (strlen($address) < 20) {
                $address = null;
            }
        }

        if (empty($phone)) {
            $phone = $this->http->FindSingleNode("(.//div/a[starts-with(@href, 'tel:')])[1]");
        }

        return ['address' => $address, 'phone' => $phone];
    }

    private function getAddressFr($condition = ''): array
    {
        // DON'T FORGET USE {$condition}  - condition for 2 or more reservation
        $address = $phone = null;

        if (empty($address)) {
            $link = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t('Google Link Map')) . "]/ancestor::a[1]{$condition}/@href");

            if (!empty($link)) {
                $http = clone $this->http;
                $http->GetURL($link);
                $url = $http->currentUrl();

                $address = $http->FindSingleNode("//meta[@itemprop = 'name']/@content");

                if (empty($address) || stripos($address, 'Google Maps') !== false) {
                    $address = null;

                    if (preg_match("/https:\/\/www\.google\.com(?:\.pr)?\/maps\/place\/([^\/]{5,})/", $url, $m)) {
                        $address = urldecode($m[1]);
                    } elseif (preg_match("/https:\/\/www\.google\.com(?:\.pr)?\/maps\/dir\/\/([^\/]+?)\/@/", $url, $m) && strlen($url) > 10) {
                        $address = urldecode($m[1]);
                    } elseif (preg_match("/" . preg_quote('https://www.google.com/maps/dir/?api=1&destination=', '/') . "([^\/&]+?)($|&|\/)/", $url, $m) && strlen($url) > 10) {
                        $address = urldecode($m[1]);
                    }
                }
            }
        }

        return ['address' => $address, 'phone' => $phone];
    }

    private function getAddressIt($condition = ''): array
    {
        // DON'T FORGET USE {$condition}  - condition for 2 or more reservation
        $address = $phone = null;

        if (empty($address)) {
            $link = $this->http->FindSingleNode("//text()[" . $this->eq($this->t('Google Link Map')) . "]/ancestor::a[1]{$condition}/@href");

            if (!empty($link)) {
                $http = clone $this->http;
                $http->GetURL($link);
                $url = $http->currentUrl();

                $address = $http->FindSingleNode("//meta[@itemprop = 'name']/@content");

                if (empty($address) || stripos($address, 'Google Maps') !== false) {
                    $address = null;

                    if (preg_match("/https:\/\/www\.google\.com(?:\.pr)?\/maps\/place\/([^\/]{5,})/", $url, $m)) {
                        // it-49337442.eml
                        $address = urldecode($m[1]);
                    } elseif (preg_match("/https:\/\/www\.google\.com(?:\.pr)?\/maps\/dir\/\/([^\/]+?)\/@/", $url, $m) && strlen($url) > 10) {
                        // it-49337442.eml
                        $address = urldecode($m[1]);
                    } elseif (preg_match("/" . preg_quote('https://www.google.com/maps/dir/?api=1&destination=', '/') . "([^\/&]+?)($|&|\/)/", $url, $m) && strlen($url) > 10) {
                        $address = urldecode($m[1]);
                    }
                }
            }
        }

        return ['address' => $address, 'phone' => $phone];
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
            if (!empty($words['Booking #']) && $this->http->XPath->query("//text()[{$this->contains($words['Booking #'])}]")->length > 0) {
                if ((!empty($words['Add to your calendar:']) && $this->http->XPath->query("//text()[{$this->contains($words['Add to your calendar:'])}]")->length > 0)
                    || (!empty($words['Your booking has been cancelled']) && $this->http->XPath->query("//text()[{$this->contains($words['Your booking has been cancelled'])}]")->length > 0)
                    || (!empty($words['Cancelled']) && $this->http->XPath->query("//text()[{$this->starts($words['Booking #'])} and {$this->contains($words['Cancelled'])}]")->length > 0)
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function getTotalCurrency($node, $currency = null): array
    {
        $node = str_replace(["€", "£", "₹"], ["EUR", "GBP", "INR"], $node);
        $tot = null;
        $cur = null;

        if (preg_match("#(?<c>[A-Z]{3})\s*(?<t>\d[\.\d\,\s]*\d*)#", $node, $m)
            || preg_match("#(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[A-Z]{3})#", $node, $m)
            || preg_match("#^(?<c>[^\s\d])\s*(?<t>\d[\.\d\,\s]*\d*)$#", trim($node), $m)
            || preg_match("#^(?<t>\d[\.\d\,\s]*\d*)\s*(?<c>[^\s\d])$#", trim($node), $m)
        ) {
            $cur = $m['c'];
            $tot = PriceHelper::parse($m['t'], $currency ?? $cur);
        }

        return ['total' => $tot, 'currency' => $cur];
    }

    private function re($re, $str, $c = 1): ?string
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
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

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
        }, $field)) . ')';
    }

    private function normalizeDate($date)
    {
        // $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            // Mardi, 6 Juin 2023, 09:00
            '#^\s*[[:alpha:]\-]+,\s*(\d+)\s+([[:alpha:]]+)\s+(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#ui',
            // Wednesday, November 6 2019
            '#^\s*[[:alpha:]\-]+,\s*([[:alpha:]]+)\s+(\d+)\s+(\d{4})\s*,\s*(\d{1,2}:\d{2}(?:\s*[ap]m)?)\s*$#ui',
        ];
        $out = [
            '$1 $2 $3, $4',
            "$2 $1 $3, $4",
        ];
        $date = preg_replace($in, $out, $date);
        // $this->logger->debug('$date = '.print_r( $date,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $date, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $date = str_replace($m[1], $en, $date);
            }
        }

        return strtotime($date);
    }
}
