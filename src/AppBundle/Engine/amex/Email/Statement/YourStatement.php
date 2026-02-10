<?php

namespace AwardWallet\Engine\amex\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class YourStatement extends \TAccountChecker
{
    // DON'T ADD BALANCE, most likely it is not balance

    public $mailFiles = "amex/statements/it-156394692.eml, amex/statements/it-159139841.eml, amex/statements/it-159416269.eml, amex/statements/it-62666390.eml, amex/statements/it-62830875.eml, amex/statements/it-62832721.eml, amex/statements/it-64379706.eml, amex/statements/it-64511560.eml, amex/statements/it-64513016.eml, amex/statements/it-64531640.eml, amex/statements/it-73164659.eml, amex/statements/it-76436016.eml, amex/statements/it-77636415.eml, amex/statements/it-97290515.eml, amex/statements/it-97447272.eml, amex/statements/it-97577631.eml";

    public $lang = 'en';

    public static $dictionary = [
        'en' => [
            'accountEnding'                                                                           => ['ACCOUNT ENDING', 'Account ending', 'Account Ending', 'Account Ending in', 'Your Account ending in', 'Your Account Number Ending', 'Card Ending In', 'Card ending', 'Account ending in |', 'Account ending in', 'Your Account Number Ending'],
            'hello'                                                                                   => ['Hello,', 'Welcome,', 'Hello ', 'Dear ', 'Hi '],
            'providing you with a One-Time Password for identification purposes on your Card Account' => [
                'providing you with a One-Time Password for identification purposes on your Card Account',
                'To verify your identity, please use the below one-time passcode',
                'This will not change the existing password you use to access your American Express online account.',
                'Please enter this re-authentication key',
            ],
        ],
        'it' => [
            'accountEnding'  => ['Ultime 5 cifre della Carta:'],
            'hello'          => ['Gentile '],
            // otc 1
            'you the one-time Re-authentication Key you need to access your American Express online account' => 'Per proteggere il tuo conto Carta American Express abbiamo',
            'Your Re-authentication Key'                                                                     => 'Il tuo codice di autenticazione temporaneo è',

            // otc 2
            //            'providing you with a One-Time Password for identification purposes on your Card Account' => [
            //                ' '
            //            ],
            // code, but not otc
            //            'To verify your identity, please use the below one-time passcode' => [''],
        ],
        'fr' => [
            'accountEnding'  => ['Numéro de Carte se terminant par :', 'Numéro de Carte se terminant par:', 'Les 5 derniers chiffres de votre carte:', 'Compte dont le numéro se termine par:',
                'Votre Carte se terminant par:', ],
            'hello'                                                                                   => ['Cher titulaire ', 'Cher Titulaire ', 'Bonjour '],
            'providing you with a One-Time Password for identification purposes on your Card Account' => [
                'Voici le code d\'authentification à usage unique nécessaire à la vérification de votre identité et à la protection de l\'accès',
                'Voici le code d’authentification à usage unique vous permettant de vérifier votre',
                'Voici votre code d’authentification pour les Services en ligne American Express',
                'code de sécurité temporaire et à usage unique que vous avez choisi de recevoir par email pour nous permettre de vérifier votre identité',
            ],
        ],
        'de' => [
            'accountEnding'  => ['Kartennummer endet mit:', 'Kartennummer:', 'Letzten Ziffern Ihrer Kartennummer:'],
            'hello'          => ['Guten Tag ', 'Hallo ', 'Sehr geehrte(r) '],
            //            'To verify your identity, please use the below one-time passcode' => ['code de sécurité temporaire et à usage unique que vous avez choisi de recevoir par email pour nous permettre de vérifier votre identité'],
            // otc 2
            'providing you with a One-Time Password for identification purposes on your Card Account' => [
                'Sie haben einen temporären Sicherheitscode per E-Mail angefordert. Ihr temporärer Sicherheitscode lautet',
            ],
        ],
        'es' => [
            'accountEnding'  => ['Tarjeta terminada en', 'Últimos dígitos de su Tarjeta:', 'Tarjeta finalizada en'],
            'hello'          => ['Estimado Titular', 'Estimado ', 'Estimado/a '],
            //            'To verify your identity, please use the below one-time passcode' => ['code de sécurité temporaire et à usage unique que vous avez choisi de recevoir par email pour nous permettre de vérifier votre identité'],
            // otc 2
            'providing you with a One-Time Password for identification purposes on your Card Account' => [
                'Usted ha solicitado que un código de seguridad temporal le sea enviado a su dirección de correo electrónico. Este es el Código de seguridad temporal',
            ],
        ],
        'sv' => [
            'accountEnding'  => ['Som slutar med:'],
            'hello'          => ['Hej '],
            //            'To verify your identity, please use the below one-time passcode' => ['code de sécurité temporaire et à usage unique que vous avez choisi de recevoir par email pour nous permettre de vérifier votre identité'],
            // otc 2
            'providing you with a One-Time Password for identification purposes on your Card Account' => [
                'du har begärt att få en engångskod mailad till dig. Den tillfälliga koden är',
            ],
        ],
        'nl' => [
            'accountEnding'                                                   => ['Kaartnummer eindigend op:', 'Uw Kaart eindigend op:'],
            'hello'                                                           => ['Beste Kaarthouder ', 'Beste ', 'Geachte ', 'Hallo '],
            'To verify your identity, please use the below one-time passcode' => ['Dit is de eenmalige toegangscode om uw identiteit te kunnen verifiëren'],
            // otc 2
            'providing you with a One-Time Password for identification purposes on your Card Account' => [
                'U heeft via American Express Online Services een tijdelijke verificatiecode opgevraagd',
            ],
        ],
        'ja' => [
            'accountEnding'  => ['カード番号(下５桁):', 'カード番号（下5桁）:'],
            //            'hello'          => [''],
            //            'To verify your identity, please use the below one-time passcode' => ['Dit is de eenmalige toegangscode om uw identiteit te kunnen verifiëren'],
            // otc 2
            //            'providing you with a One-Time Password for identification purposes on your Card Account' => [
            //                'U heeft via American Express Online Services een tijdelijke verificatiecode opgevraagd'
            //            ],
        ],
        'zh' => [
            'accountEnding'  => ['參考編號:'],
            'hello'          => ['親愛的 '],
            //            'To verify your identity, please use the below one-time passcode' => ['Dit is de eenmalige toegangscode om uw identiteit te kunnen verifiëren'],
            // otc 2
            //            'providing you with a One-Time Password for identification purposes on your Card Account' => [
            //                'U heeft via American Express Online Services een tijdelijke verificatiecode opgevraagd'
            //            ],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@welcome.aexp.com') !== false || stripos($from, 'americanexpress@member.americanexpress.com') !== false || stripos($from, 'Services@Credit.AmericanExpress.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return $this->detectEmailFromProvider($headers['from']);
//        if ($this->detectEmailFromProvider($headers['from']) !== true)
//            return false;

//        return preg_match('/Your.* statement/i', $headers['subject']) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".americanexpress.com/") or contains(@href,"www.americanexpress.com")]')->length === 0
            && $this->http->XPath->query('//*[' . $this->contains(['American Express. All rights reserved', 'American Express Italia s.r.l. - società', 'This is a customer service e-mail from American Express', 'American Express Australia']) . ']')->length === 0
        ) {
            return false;
        }

        return $this->http->XPath->query('//*[contains(normalize-space(),"Your statement is now ready") or contains(normalize-space(),"Statement Balance:") or contains(normalize-space(),", thank you for being a Card Member") or contains(normalize-space(),", thank you for being a valued Card Member") or contains(normalize-space(),"Your Re-authentication Key:") or contains(normalize-space(),"re-authentication key")] | //a[normalize-space()="View Statement"]')->length > 0;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if ($this->http->XPath->query('//*['
                    . $this->contains($dict['you the one-time Re-authentication Key you need to access your American Express online account'] ?? [])
                    . ' or ' . $this->contains($dict['providing you with a One-Time Password for identification purposes on your Card Account'] ?? [])
                    . ' or ' . $this->contains($dict['accountEnding'] ?? [])
                . ']')->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        if (empty($this->lang)) {
            foreach (self::$dictionary as $lang => $dict) {
                if ($this->http->XPath->query('//*['
                         . $this->starts($dict['hello'] ?? [])
                        . ']')->length > 0
                ) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $xpathBold = '(self::b or self::strong or ancestor-or-self::*[contains(@style,"bold")])';

        // One-Time Code
        $parseOneTime = $this->parseOneTimeCode($email);

        // Statement
        $st = $email->add()->statement();

        $balance = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'Statement Balance:')]/following::td[1]/descendant::text()[normalize-space()][1]", null, true, "/^\S(\d[,.\'\d ]*)$/");

        if ($balance === null) {
            // it-76436016.eml, it-77636415.eml
            $imgPoints = $imgAsOf = [];
            $imgUrls = $this->http->FindNodes("//img[contains(@src,'mi_points=') or contains(@src,'mr_points=')]/@src");

            foreach ($imgUrls as $url) {
                if (preg_match("/[?&](?:mi_points|mr_points)=([^=&\s]+?)(?:&|$)/i", $url, $m)) {
                    $imgPoints[] = $m[1];

                    if (preg_match("/[?&](?:mi_asof|as_of)=([^=&\s]+?)(?:&|$)/i", $url, $m)) {
                        $imgAsOf[] = $m[1];
                    }
                }
            }

            if (count(array_unique($imgPoints)) === 1 && count(array_unique($imgAsOf)) === 1) {
                $balance = $imgPoints[0];
//                $st->parseBalanceDate(urldecode($imgAsOf[0]));
            }
        }

        if ($balance !== null) {
            // IT'S NOT A BALANCE
//            $st->setBalance($this->normalizeAmount($balance));
            $st->setNoBalance(true);
            $st->setMembership(true);
        }

        $accountEnding = $this->http->FindSingleNode("//tr/*[not(.//tr) and not(preceding-sibling::*/descendant::img[contains(@src,'.serve.com/')]) and {$this->starts($this->t('accountEnding'))}]", null, true, "/^{$this->opt($this->t('accountEnding'))}[-:\s]*(\d{4,})$/i");

        if (empty($accountEnding)) {
            $accountEnding = $this->http->FindSingleNode("//text()[{$this->starts($this->t('accountEnding'))}]/following::text()[string-length(normalize-space())>2][1]", null, true, '/^\d{4,}$/');
        }

        if (empty($accountEnding)) {
            $accountEnding = $this->http->FindSingleNode("//text()[" . $this->eq(['ACCOUNT ENDING']) . "][following::text()[normalize-space()][1][" . $this->eq([':']) . "]]/following::text()[normalize-space()][2]", null, true, '/^\s*(\d{4,})$/');
        }

        if (empty($accountEnding)) {
            $accountEnding = $this->http->FindSingleNode("//text()[{$this->starts($this->t('accountEnding'))}]", null, true, "/^{$this->opt($this->t('accountEnding'))}[-:\s]+(\d{4,})$/");
        }

        if ((!empty($accountEnding)
                || preg_match("/^Your(|.+ )Statement/i", $parser->getSubject())
                || $parseOneTime
            ) && $st->getBalance() == null
        ) {
            $st->setMembership(true);
            $st->setNoBalance(true);
        }

        $patterns['travellerName'] = '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]';

        $name = $this->http->FindSingleNode("//tr/*[ not(.//td) and descendant::text()[normalize-space()][2][{$this->starts('MEMBER SINCE')}] ]/descendant::text()[normalize-space()][1][ancestor::*[{$xpathBold}]]", null, true, "/^{$patterns['travellerName']}$/u"); // it-77636415.eml

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[" . $this->eq(['Customer', 'Card Member:']) . "][following::text()[normalize-space()][2][" . $this->eq(['Account Ending', 'Account Ending:']) . "] ]/following::text()[normalize-space()][1]", null, true, "/^{$patterns['travellerName']}$/u");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[{$this->eq($this->t('hello'))}]/following::text()[normalize-space()][1]", null, true, "/^({$patterns['travellerName']})(?:\s*[,.;!?:]|$)/iu")
                ?? $this->http->FindSingleNode("//text()[{$this->starts($this->t('hello'))}]", null, true, "/^{$this->opt($this->t('hello'))}\s*({$patterns['travellerName']})(?:\s*[,.;!?:]|$)/iu");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//text()[normalize-space() = \"There's A Payment That's Past Due\" or " . $this->eq(["Your Transaction Has Been Declined", "A payment is required for your American Express Card", "Payment required for your American Express® account", "Payment required on your American Express® account"]) . "]/preceding::td[normalize-space()][1][" . $this->starts(['Account Ending:', 'Account Ending in:']) . "]/ancestor::tr[1][count(*[normalize-space()]) = 2 and count(*[.//img[contains(@src, 'americanexpress.com/axp/bu_logos')]]) = 1]/td[normalize-space()][1]", null, true,
                "/^{$patterns['travellerName']}$/u");
        }

        if (!preg_match("#(?:card\s*member|Titolare|Titular)#ui", $name) && $name !== null) {
            $st->addProperty('Name', $name);
        }

        // it's not balance
//        $statementBalance = $this->http->FindSingleNode("//tr/*[".$this->eq(['Statement Balance:', 'Current balance'])."]/following-sibling::*[normalize-space()]", null, true, '/^[^\d)(]*(\d[,.\'\d ]*)$/');
//        if ($statementBalance === null
//            && ($this->http->XPath->query('//node()[contains(normalize-space(),"It will be available to view online within")]')->length > 0
//                || $this->http->XPath->query('//node()['.$this->eq(['Statement Balance:', 'Current balance']).']')->length === 0)
//        ) {
//            $st->setNoBalance(true);
//        } else {
//            $st->setBalance( $this->normalizeAmount($statementBalance ?? '') );
//
//            $date = $this->http->FindSingleNode("(//*[".$this->starts(['Here is the latest balance on your American Express'])." and ".$this->contains(['Card as of'])."])[last()]", null, true,
//                '/^Here is the latest balance on your American Express[^.,]+ Card as of\s*(\d[\d\/]+)\s*$/');
//            if (!empty($date)) {
//                $st->setBalanceDate(strtotime($date));
//            }
//        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function parseOneTimeCode(Email $email): bool
    {
        // Step 1: find invalid one-time codes

        $passcode = $this->http->FindSingleNode("//p[" . $this->starts($this->t('To verify your identity, please use the below one-time passcode')) . "]/following::p[normalize-space()][1]", null, true, "/^\d+$/");

        if ($passcode !== null) {
            // it-97290515.eml
            return true;
        }

        // Step 2: find valid one-time codes

        $passcode = $this->http->FindSingleNode("//p[" . $this->contains($this->t('providing you with a One-Time Password for identification purposes on your Card Account')) . "]/following::p[normalize-space()][1]", null, true, "/^(\d+)\.?$/");

        if ($passcode !== null) {
            // it-97577631.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($passcode);

            return true;
        }

        $passcode = $this->http->FindSingleNode("//p[" . $this->contains($this->t('you the one-time Re-authentication Key you need to access your American Express online account')) . "]/following::p[" . $this->starts($this->t('Your Re-authentication Key')) . "]/following::p[normalize-space()][1]", null, true, "/^\d+$/");

        if ($passcode !== null) {
            // it-97577631.eml
            $code = $email->add()->oneTimeCode();
            $code->setCode($passcode);

            return true;
        }

        $passcode = $this->http->FindSingleNode("//text()[" . $this->contains("one-time passcode") . "]/following::text()[string-length()>2][1]", null, true, "/^(\d+)$/");

        if (!empty($passcode)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($passcode);

            return true;
        }

        $passcode = $this->http->FindSingleNode("//text()[" . $this->contains("Your re-authentication key is:") . "]/following::text()[string-length()>2][1]", null, true, "/^(\d+)$/");

        if (!empty($passcode)) {
            $otc = $email->add()->oneTimeCode();
            $otc->setCode($passcode);

            return true;
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
