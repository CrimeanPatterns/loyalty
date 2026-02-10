<?php

namespace AwardWallet\Engine\choice\Transfer;

class Transfer extends \TAccountCheckerChoice
{
    protected $codes = [
        'aeroplan'      => 'aeroplan',
        'airnewzealand' => 'airnewzealand',
        'alaskaair'     => 'alaskaairlines',
        'aa'            => 'amairlines',
        'aeromexico'    => 'aeromexico',
        'spirit'        => 'spiritairlines',
        'mileageplus'   => 'unitedairlines',
        'qantas'        => 'qantas',
        'velocity'      => 'velocity',
        'rapidrewards'  => 'swairlines',
        'airberlin'     => 'airberlin',
    ];

    public function LoadLoginForm()
    {
        if (!isset($this->codes[$this->TransferFields['targetProvider']])) {
            throw new \UserInputError('Transfer to target provider is not supported');
        }

        return parent::LoadLoginForm();
    }

    public function transferMiles($targetProviderCode, $targetAccountNumber, $numberOfMiles, $fields = [])
    {
        $this->ArchiveLogs = true;
        $this->http->GetURL('https://secure.choicehotels.com/en/choice-privileges/gp/redemptions/airlines');

        if (!$this->http->ParseForm('redemptionCategory')) {
            return false;
        }
        $url = sprintf('choicehotels/en/choice-privileges/gp/merchants/%s', $this->codes[$targetProviderCode]);
        $this->http->Form['merchantID'] = $url;

        if (!$this->http->PostForm()) {
            return false;
        }
        $balance = $this->http->FindSingleNode('//span[@class="pointsBalance"]');

        if (!isset($balance)) {
            $this->http->Log('did not find balance', LOG_LEVEL_ERROR);

            return false;
        }
        $balance = intval(str_replace(',', '', $balance));

        if ($balance < $numberOfMiles) {
            throw new \UserInputError('You don\'t have enough points');
        }
        $transferRows = $this->http->XPath->query('//tr[td[@class="pointsPerAwardColumn"]]');

        if ($transferRows->length === 0) {
            return false;
        }

        if (!$this->http->ParseForm('cpAwardList')) {
            return false;
        }
        $this->http->Form = array_filter($this->http->Form, function ($key) {return strpos($key, 'qty') !== 0; }, ARRAY_FILTER_USE_KEY);
        $post = false;
        // find points * quantity combination that we can use
        for ($i = $transferRows->length; $i > 0; $i--) {
            $row = $transferRows->item($i - 1);
            $need = $this->http->FindSingleNode('td[@class="pointsPerAwardColumn"]', $row);

            if (!isset($need)) {
                return false;
            }
            $need = intval(str_replace(',', '', $need));

            if ($need > $numberOfMiles) {
                continue;
            }

            if ($numberOfMiles % $need === 0) {
                $quantity = $numberOfMiles / $need;

                if ($quantity > 25) {
                    $this->http->Log('quantity > 25, not sure');

                    return false;
                }
                $this->http->Form['awardQuantity'] = $quantity;
                $this->http->Form['totalAmt'] = number_format($numberOfMiles, 0, '.', ',');
                $type = $this->http->FindSingleNode('td/input[@name="selectedAward"]/@awardtype', $row);

                if (!isset($type)) {
                    $type = '';
                }
                $this->http->Form['awardType'] = $type;
                $this->http->Form['selectedAward'] = $this->http->FindSingleNode('td/input[@name="selectedAward"]/@value', $row);
                $selectName = $this->http->FindSingleNode('td/select[@class="qty"]/@name', $row);
                $this->http->Form[$selectName] = $quantity;
                $post = true;

                break;
            }
        }

        if (!$post) {
            throw new \UserInputError('Invalid number of points');
        }

        if (!$this->http->PostForm()) {
            return false;
        }

        if (!$this->http->ParseForm('cpAwardRedeem')) {
            return false;
        }
        $this->http->SetInputValue('parAccountNumber', $targetAccountNumber);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($error = $this->http->FindSingleNode('//p[@class="error-text"]')) {
            throw new \ProviderError($error);
        } // Is it always provider error?
        $success = 'Your transaction is now complete! Your redemption has been processed and your account balance reduced accordingly.';

        if ($this->http->FindSingleNode(sprintf('//text()[contains(., "%s")]', $success))) {
            $this->ErrorMessage = $success;

            return true;
        }

        return false;
    }
}
