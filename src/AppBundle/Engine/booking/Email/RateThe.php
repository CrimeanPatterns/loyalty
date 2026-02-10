<?php

namespace AwardWallet\Engine\booking\Email;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class RateThe extends \TAccountChecker
{
    public $mailFiles = "booking/it-34803406.eml, booking/it-35212181.eml, booking/it-35213381.eml, booking/it-35214461.eml, booking/it-35381000.eml, booking/it-35533799.eml, booking/it-35614999.eml, booking/it-35640731.eml, booking/it-35666862.eml, booking/it-35682916.eml, booking/it-36018379.eml, booking/it-36020546.eml, booking/it-36049248.eml, booking/it-36051537.eml, booking/it-55618367.eml, booking/it-56219902.eml, booking/it-251059896-ar.eml";

    public $reFrom = ["noreply@booking.com"];
    public $reSubject = [
        "#(?:Valora el|Calificá)#", // es
        "#(?:¡Solo quedan . días|está esperando tu comentario)#u", // es
        "#Nur noch . Tage#u", // de
        "#Valuta#u", // it
        "#Évaluez l'établissement#", // fr
        "#Plus que . jours#u", // fr
        "#דרגו את #", // he
        "#Rate#", // en
        "#Only . days left#u", // en
        "#Μένουν μόνο . ημέρες#u", // el
        "#Tinggal . hari lagi#u", //id
        "#Оцените#u", //ru
        "#Оценете#u", //bg
        "#tesisini puanlayın#u", //tr
        "#評價#u", //zh
        "#Aðeins . dagar eftir#u", //is
        "#Oceń obiekt#u", //pl
        "#قيّم #u", //ar
        "#Avalie #u", //pt
        "#O que você achou de #u", //pt
        "#Ohodnoťte ubytovanie#u", //sk
        "#Posa nota a#u", //ca
        "#Beoordeel#u", // nl
        "#Оцініть#u", // uk
        "#Betygsätt #u", // sv
        "#Hvordan hadde du det på #u", // no
        "#Įvertinkite #u", // lt
        "# értékelése#u", // hu
        "#Ocenite objekat #u", // bs
        "#Jaký byl Váš pobyt v ubytování#u", // cs
        "#Ocenite nastanitev #u", // sl
        "#Evaluați #u", // ro
        "#așteaptă evaluarea ta#u", // ro
        "#のクチコミを投稿しましょう#u", // ja
        "#Đánh giá #u", // vi
        "#Bedøm #u", // da
        "# 평가하기#u", // ko
        "#Arvioi #u", // fi
    ];
    public $lang = '';
    public static $dict = [
        'en' => [
            'You only have until' => [
                'You only have until',
                'Share your feedback with the property before',
                'is the last day to send your feedback',
                'The property is looking forward to receiving your feedback soon',
                'Your feedback is valuable and will help the property',
            ],
            'nights in' => ['nights in', 'night in'],
        ],
        'fr' => [
            'You only have until' => [
                "Vous avez jusqu'au",
                "Vous avez seulement jusqu'au",
                "Donnez votre avis à l'établissement avant le",
                "L'établissement est impatient de connaître votre avis",
                'Votre avis est important car il aidera ',
            ],
            'nights in' => ['nuits à', 'nuit à'],
        ],
        'ru' => [
            'You only have until' => [
                'Есть время только до', 'Оставьте отзыв о проживании до',
                '— последний день, чтобы оставить отзыв',
                'В объекте ждут ваш отзыв',
                'Ваш отзыв важен и поможет сотрудникам объекта понять',
            ],
            'nights in' => ['ночей в', 'ночь в'],
        ],
        'bg' => [
            'You only have until' => 'Имате време до',
            'nights in'           => ['нощувка в', 'нощувки в'],
        ],
        'tr' => [
            'You only have until' => 'tarihine kadar vaktiniz var',
            'nights in'           => ['gece'],
        ],
        'zh' => [
            'You only have until' => ['前填寫', '前填寫', '提交期限：', '是您可以撰寫住宿評語的最後一天',
                '住宿方期待收到您的意見回饋', '您的寶貴意見可以幫助住宿提供更好的體驗',
                '您的反馈十分宝贵', '是您可向住宿',
            ],
            'nights in' => ['晚住宿'],
            'detect'    => ['您的住宿經驗如何', '你的入住体验如何'], //only for detect
        ],
        'pl' => [
            'You only have until' => ['Masz czas tylko do', 'Podziel się opinią z obiektem do',
                'Obiekt chętnie pozna Twoją opinię',
                'Twoja opinia jest bardzo cenna i pomoże obiektowi ulepszyć',
                'to ostatni dzień, kiedy możesz podzielić',
            ],
            'nights in' => ['noc w', 'nocy w'],
        ],
        'ar' => [
            'You only have until' => 'ملاحظاتك مهمة وتساعد مكان الإقامة على تحسين خدماته',
            'nights in'           => 'ليلة في',
        ],
        'is' => [
            'You only have until' => 'Þú hefur aðeins til',
            'nights in'           => ['nætur í', 'nætur á'],
        ],
        'id' => [
            'You only have until' => 'Anda punya waktu sampai',
            'nights in'           => ['malam di'],
        ],
        'de' => [
            'You only have until' => [
                'Sie haben bis zum',
                'Übermitteln Sie Ihr Feedback an die Unterkunft bis zum',
                'ist der letzte Tag, an dem Sie Ihr Feedback',
                'Die Unterkunft freut sich darauf, bald',
                'Ihr Feedback ist wichtig und hilft der',
            ],
            'nights in' => ['Nacht in', 'Nächte in'],
        ],
        'da' => [
            'You only have until' => [
                'Overnatningsstedet glæder sig til at modtage din feedback snart',
                'Din feedback er meget værdsat og er med',
            ],
            'nights in'           => ['nat i', 'nætter i'],
        ],
        'es' => [
            'You only have until' => [
                'Solo tienes hasta el',
                'Solo tenés tiempo hasta el',
                'Puedes compartir tu opinión con el alojamiento hasta el',
                'es el último día para enviar tu opinión al alojamiento',
                'El alojamiento espera recibir tus comentarios pronto',
                'es el último día para enviar tu opinión al alojamiento',
                'Tu opinión es muy importante y ayudará al alojamiento a mejorar',
                'El alojamiento espera recibir tu opinión pronto',
                'Tu opinión es importante y va a ayudar',
            ],
            'nights in' => ['noches en', 'noche en'],
        ],
        'it' => [
            'You only have until' => [
                'Hai solo fino al', 'Lascia il tuo feedback alla struttura entro il',
                'La struttura non vede l\'ora di ricevere il tuo feedback',
                'Il tuo feedback è prezioso e aiuterà',
                'è l\'ultimo giorno utile per lasciare',
            ],
            'nights in' => ['notte a', 'notti a'],
        ],
        'he' => [
            'You only have until' => 'תוכלו לעשות זאת רק עד',
            'nights in'           => ['לילות ב', 'לילה אחד'],
        ],
        'el' => [
            'You only have until' => ['Έχετε μόνο μέχρι τις',
                'Η γνώμη σας είναι πολύτιμη και θα βοηθήσει το κατάλυμα να βελτιωθεί',
            ],
            'nights in' => ['βράδυ στο', 'βράδια στο'],
        ],
        'pt' => [
            'You only have until' => [
                'Você só tem até', 'Tem apenas até', 'Deixe seu feedback para a acomodação antes de',
                'Seu feedback é importante e ajudará a acomodação a melhorar',
                'O seu feedback é valioso e irá ajudar o alojamento a melhorar',
                'A acomodação espera receber seu feedback em breve',
                'é o último dia para enviar seu feedback para',
                'é o último dia para enviar o seu feedback',
                'O alojamento está à espera de receber',
            ],
            'nights in' => ['diária em', 'noites em', 'diárias em', 'noite em'],
        ],
        'sk' => [
            'You only have until' => ['Máte čas už len do'],
            'nights in'           => ['nocí v destinácii'],
        ],
        'ca' => [
            'You only have until' => ['Només tens fins al', 'La teva opinió és important i ajudarà'],
            'nights in'           => ['nits a', 'nit a'],
        ],
        'ja' => [
            'You only have until' => ['投稿期限：', 'が宿泊施設にご意見やご感想を送信できる締切日となります'],
            'nights in'           => ['泊（'],
            'detect'              => ['今すぐ評価する'],
        ],
        'nl' => [
            'You only have until' => [
                'Je hebt nog maar tot',
                'Deel je feedback met de accommodatie vóór',
                'Je feedback is waardevol en helpt de',
                'is de laatste dag om je feedback',
                'De accommodatie ziet je feedback',
                'U heeft maar tot uiterlijk',
            ],
            'nights in' => ['nacht in', 'nachten in'],
        ],
        'uk' => [
            'You only have until' => ['Ви маєте час лише до',
                'Ваш відгук важливий і допоможе помешканню покращити',
            ],
            'nights in' => 'ніч у',
        ],
        'sv' => [
            'You only have until' => ['Du har bara fram till', 'Din feedback betyder mycket och bidrar till att göra boendet bättre'],
            'nights in'           => 'natt i',
        ],
        'no' => [
            'You only have until' => 'Du kan skrive en omtale senest',
            'nights in'           => 'natt på ',
        ],
        'lt' => [
            'You only have until' => [
                'Turite laiko tik iki ',
                'Jūsų nuomonė yra svarbi ir padės',
            ],
            'nights in' => ['naktis ', 'naktys '],
        ],
        'hu' => [
            'You only have until' => 'Eddig még van ideje:',
            'nights in'           => ' éj ',
        ],
        'bs' => [
            'You only have until' => 'Imate vremena do datuma:',
            'nights in'           => 'noćenja u gradu',
        ],
        'cs' => [
            'You only have until' => [
                'Hodnocení můžete napsat do',
                'Zpětnou vazbu můžete ubytování poskytnout nejpozději',
                'Váš názor je důležitý a pomůže',
            ],
            'nights in' => ['nocí v', 'noc v'],
        ],
        'sl' => [
            'You only have until' => 'Čas imate le še do',
            'nights in'           => ['noč v ', 'noč na ', 'noči v'],
        ],
        'ro' => [
            'You only have until' => [
                'Mai aveţi doar până pe data de', 'este ultima zi în care poți trimite',
                'Feedbackul tău este valoros și va ajuta proprietatea',
            ],
            'nights in' => ['nopți în', 'noapte în'],
        ],
        'vi' => [
            'You only have until' => 'Chỗ nghỉ đang mong nhận được góp ý của bạn',
            'nights in'           => ['đêm ở'],
        ],
        'ko' => [
            'You only have until' => '고객님의 소중한 의견은 숙소의',
            //            'nights in' => [''],
            'detect' => ['숙박은'],
        ],
        'fi' => [
            'You only have until' => 'Palautteesi on arvokasta ja auttaa majoituspaikkaa',
            'nights in'           => ['yötä kohteessa'],
            //            'detect' => [''],
        ],
    ];
    private $keywordProv = 'Booking.com';

    private $emailDate;

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if (!$this->assignLang()) {
            $this->logger->debug('can\'t determine a language');

            return $email;
        }

        if ($this->http->XPath->query("//img[contains(@src, '/smiley_')]")->length !== 4
            && $this->http->XPath->query("//a[{$this->contains($this->t('detect'))}]")->length === 0
        ) {
            $this->logger->debug('main detect is false');

            return $email;
        }

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        if (stripos($parser->getCleanFrom(), '@booking.com')) {
            $this->emailDate = strtotime("+1 day", strtotime($parser->getDate()));
        }

        $this->parseEmail($email);

        return $email;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (empty($this->http->Response['body'])) {
            $this->http->SetEmailBody($parser->getPlainBody());
        }

        if ($this->http->XPath->query("//a[contains(@href,'.booking.com')] | //img[contains(@alt, 'Booking.com')]")->length > 0
            && (
                $this->http->XPath->query("//img[contains(@src, '/smiley_')]")->length == 4
                || $this->http->XPath->query("//img[contains(@src, '/review_email')]")->length > 0
            )
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
        if ($this->detectEmailFromProvider($headers['from']) !== true
            && strpos($headers['subject'], $this->keywordProv) === false
        ) {
            return false;
        }

        if (isset($headers["subject"])) {
            foreach ($this->reSubject as $reSubject) {
                if (preg_match($reSubject, $headers["subject"]) > 0) {
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

    private function parseEmail(Email $email): void
    {
        $r = $email->add()->hotel();

        $r->general()
            ->noConfirmation();
        $node = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You only have until'))}]", null, false, "#^{$this->opt($this->t('You only have until'))}\s*(.{6,})#")
            ?? $this->http->FindSingleNode("//text()[{$this->contains($this->t('You only have until'))}]", null, false,
        "#^(.{6,}?)\s*{$this->opt($this->t('You only have until'))}#");

        $noDate = false;
        $dateRate = $this->normalizeDate($node);

        if (empty($dateRate) && !empty($this->emailDate)
                && $this->http->XPath->query("//text()[{$this->contains($this->t('You only have until'))}]/ancestor::*[1][contains(translate(., '0123456789', '∆∆∆∆∆∆∆∆∆∆'),'∆∆∆∆')]")->length == 0
        ) {
            $dateRate = $this->emailDate;
            $noDate = true;
        }

        if (!$dateRate) {
            $this->logger->debug('check date format');

            return;
        }
        $node = $this->http->FindSingleNode("//text()[{$this->contains($this->t('You only have until'))}]/preceding::text()[normalize-space()!=''][1]");

        $nodes = array_map("trim", explode("-", $node));

        if (count($nodes) !== 2) {
            $this->logger->debug('other format');

            return;
        }

        $year = date('Y', $dateRate);
        $checkin = $this->normalizeDate($nodes[0] . ' ' . $year);

        if ($checkin > $dateRate) {
            $year--;
            $checkin = strtotime("-1 year", $checkin);
        }
        $checkout = $this->normalizeDate($nodes[1] . ' ' . $year);

        if ($checkin > $checkout) {
            $checkout = strtotime("+1 year", $checkout);
        }

        if ($noDate === true && ($dateRate - $checkout > 60 * 60 * 24 * 20 || $dateRate - $checkout < 0)) {
            $checkin = null;
            $checkout = null;
        }

        $r->booked()
            ->checkIn($checkin)
            ->checkOut($checkout);

        if ($this->http->XPath->query("//*[{$this->contains($this->t('nights in'))}]")->length > 0) {
            $num = 3;
        } else {
            $num = 2;
        }
        $r->hotel()
            ->name($this->http->FindSingleNode("//text()[{$this->contains($this->t('You only have until'))}]/preceding::text()[normalize-space()!=''][{$num}]"))
            ->noAddress();
    }

    private function normalizeDate($date)
    {
//        $this->logger->debug('$date = '.print_r( $date,true));
        $in = [
            //23 de abril de 2019 | 23 april 2019. | 23 de Maio, 2019
            '/^\D*\s*(\d{1,2})[,.\s]+(?:de\s+)?([[:alpha:]]{2,})[.,]?\s+(?:de\s+)?(\d{4})[.\s]*$/u',
            // 26. März 2019 Zeit.    |    10 juni 2020 de tijd.
            '/^\s*(\d{1,2})[,.\s]+([[:alpha:]]+)\s+(\d{4})(?:\b|\D).*$/u',
            //請於 2019 年 5 月 9 日  |   2019年5月3日前
            '#^\D*\s*(\d{4})\s*年\s*(\d+)\s*月\s*(\d+)\s*日\s*.*$#u',
            //4 月 5 日 2019; 5월 31일 2021
            '#^\s*(\d+)\s*(?:月|월)\s*(\d+)\s*(?:日|일)\s*(\d{4})\s*$#u',
            //2020 m. lapkričio 18 d..;2020. November 20.
            '#^\s*(\d{4})(?:\s+m)?\.\s+(\w+)\s+(\d{1,2})(?:\s+d)?\.+\s*$#u',
            //ngày 5 Tháng 2 2021
            '#^\s*ngày\s*(\d+)\s*Tháng\s*(\d+)\s+(\d{4})\s*$#u',

            // bir 4 d. 2021
            '#^\s*([[:alpha:]]+)\s+(\d{1,2})\s*d\.\s+(\d{4})\s*$#u',
        ];
        $out = [
            '$1 $2 $3',
            '$1 $2 $3',
            '$1-$2-$3',
            '$3-$1-$2',
            '$3 $2 $1',
            '$3-$2-$1',

            '$2 $1 $3',
        ];

        foreach ($in as $i => $re) {
            if (preg_match($re, $date)) {
                $date = preg_replace($re, $out[$i], $date);

                break;
            }
        }
        // $this->logger->debug('$date = ' . print_r($date, true));

        return strtotime($this->dateStringToEnglish($date));
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
            if (isset($words["You only have until"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['You only have until'])}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }

            if (isset($words["You only have until"], $words["detect"])) {
                if ($this->http->XPath->query("//*[{$this->contains($words['You only have until'])}]")->length > 0
                    && $this->http->XPath->query("//*[{$this->contains($words['detect'])}]")->length > 0
                ) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
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

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }
}
