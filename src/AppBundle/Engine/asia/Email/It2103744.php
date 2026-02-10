<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Schema\Parser\Email\Email;

class It2103744 extends \TAccountCheckerExtended
{
    public $mailFiles = "asia/it-37842570.eml, asia/it-57027560.eml";
    public $from = "#booking@asiamiles.com#i";
    public $subject = [
        'BookingConfirmation_',
    ];
    public $body = [
        'Asia Miles Travel Services Limited',
    ];
    public $prov = "asia";
    public static $dictionary = [
        "en" => [
        ],
    ];
    public $lang = 'en';

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            $hotel = $email->add()->hotel();

            $confirmation = $this->re("/{$this->opt($this->t('Confirmation Number:'))}\s+(\d+)/", $textPdf);
            $dateReserv = $this->re("/{$this->opt($this->t('Date of Issue:'))}\s+(\d{2}\s+\w+\s+\d{4})/", $textPdf);
            $travellers = $this->re("/{$this->opt($this->t('Guest Name:'))}\s+(\D+)Room Type[:]/", $textPdf);

            $hotel->general()
                ->confirmation($confirmation)
                ->date(strtotime($dateReserv))
                ->travellers(explode('\n', $travellers), true);

            $name = $this->re("/{$this->opt($this->t('Hotel:'))}\s+([\w+\s+]+)\s+Address:/", $textPdf);
            $address = $this->re("/{$this->opt($this->t('Address:'))}\s+(.+)\s+Room\s+\d\:/s", $textPdf);

            $hotel->hotel()
                ->name($name)
                ->address(preg_replace(['/\s{2,}/', '/\n/'], ['', ''], $address));

            $adults = $this->re("/(\d+)\s{$this->opt($this->t('Adults'))}[.]/", $textPdf);
            $checkIn = $this->re("/{$this->opt($this->t('Check-in'))}\/\D+[:]\s+(\d+\s+\w+\s+\d{4})\s+[-]/", $textPdf);
            $checkOut = $this->re("/{$this->opt($this->t('Check- out'))}\s+\D+[:]\s+\d+\s+\w+\s+\d{4}\s+[-]\s+(\d+\s+\w+\s+\d{4})/", $textPdf);

            $hotel->booked()
                ->guests($adults)
                ->checkIn(strtotime($checkIn))
                ->checkOut(strtotime($checkOut));

            $otaConfirm = $this->re("/{$this->opt($this->t('Booking Reference Number:'))}\s+([A-Z\d]{7})/", $textPdf);
            $otaAccount = $this->re("/{$this->opt($this->t('Asia Miles Membership Number:'))}\s+([A-Z\d]+)/", $textPdf);
            $hotel->ota()
                ->confirmation($otaConfirm)
                ->account($otaAccount, preg_match("/^\d+[Xx]+\d+$/", $otaAccount) > 0);

            $setType = $this->re("/{$this->opt($this->t('Room Type:'))}\s+(.+)\n\s+Meal/", $textPdf);
            $setDescription = $this->re("/{$this->opt($this->t('Special Request:'))}\s+(.+)\n\s+Please/", $textPdf);

            $hotel->addRoom()
                ->setType($setType)
                ->setDescription($setDescription);

            $cancellationPolicy = $this->re("/{$this->opt($this->t('Cancellation Penalties:'))}\s+.+(Cancellations.+after\s+\d+\s+\w+\s+\d{4}\s[-]\s[\d\:]+.+fees[)][.])/s", $textPdf);

            if (!empty($cancellationPolicy)) {
                $hotel->general()
                    ->cancellation(preg_replace(['/\s{2,}/', '/\n/'], [' ', ''], $cancellationPolicy));

                $deadLine = $this->re("/(\d+\s+\w+\s+\d{4}\s[-]\s[\d\:]+)/", $cancellationPolicy);
                $hotel->setDeadline(strtotime(str_replace(' - ', ', ', $deadLine)));
            }

            /*TODO: need more examples
            $total = $this->re("/{$this->opt($this->t('Total:'))}\s+([\d\.\,]+)/", $textPdf);
            $hotel->price()
                ->total(str_replace(',','', $total));*/
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->subject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $pdfs = $parser->searchAttachmentByName('.*pdf');

        foreach ($pdfs as $pdf) {
            $textPdf = \PDF::convertToText($parser->getAttachmentBody($pdf));

            if (!$textPdf) {
                continue;
            }

            if (false === stripos($textPdf, $this->prov)) {
                return false;
            }

            foreach ($this->body as $detect) {
                if (false !== stripos($textPdf, $detect)) {
                    return true;
                }
            }

            return false;
        }
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

        return '(?:' . implode("|", $field) . ')';
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }
}
