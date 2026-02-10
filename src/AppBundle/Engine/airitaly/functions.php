<?php

class TAccountCheckerAiritaly extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.airitaly.com/en');

        if ($this->http->FindPreg('/<strong>From 11 to 25 February2020 inclusive<\/strong>, all Air Italy flights will be operated by other carriers at the times and on the days previously scheduled; all passengers who booked flights \(outward or return\) after 25 February 2020 will be re-protected or fully refunded\./')) {
            throw new CheckException('From 11 to 25 February 2020 inclusive, all Air Italy flights will be operated by other carriers at the times and on the days previously scheduled; all passengers who booked flights (outward or return) after 25 February 2020 will be re-protected or fully refunded.', ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->setCookie('meridiana#lang', 'en');

        $this->http->PostURL('https://www.airitaly.com/api/users/Login', json_encode([
            'LoginEmail'    => $this->AccountFields['Login'],
            'LoginPassword' => $this->AccountFields['Pass'],
            'RememberMe'    => true,
            'PageId'        => '',
        ]), [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With' => 'XMLHttpRequest',
        ]);

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (empty($response)) {
            return false;
        }

        if (isset($response->Messages[0]->Key)) {
            if (in_array($response->Messages[0]->Descr, ['Login fails, data are not correct'])) {
                throw new CheckException('Login fails, data are not correct', ACCOUNT_INVALID_PASSWORD);
            }

            if ('ERR' == $response->Messages[0]->Key) {
                return false;
            }
        }// if (isset($response->Messages[0]->Key))

        if (empty($response->Messages) && isset($response->Success) && 1 == $response->Success) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://www.airitaly.com/en/user-profile/update-profile');
        // Name, Last name
        $this->SetProperty('Name', beautifulName(trim($this->http->FindSingleNode('//input[@id="name"]/@value') . ' ' . $this->http->FindSingleNode('//input[@id="surname"]/@value'))));
        !empty($this->Properties['Name']) ?: $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//a[contains(@href, "/update-profile")]/span[@class="brand-primary"]')));
        // Frequent Flyer code
        $this->SetProperty('FlyerCode', $this->http->FindPreg('/Meridiana Club Code:\s*<strong>(\d+)<\/strong>/i'));
        // Total AVIOS
        $this->SetBalance($this->http->FindPreg('/Total Avios:\s*<strong>(\d+)<\/strong>/i'));

        // Exp Date, refs #16178
        if ($this->Balance > 0) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->http->GetURL('https://www.airitaly.com/en/user-profile/avios-point');
            $nodes = $this->http->XPath->query("//table[contains(@class,'table-responsive')]//tr[count(td)=8]");

            foreach ($nodes as $node) {
                $expDate = $this->http->FindSingleNode("td[1]", $node);
                $avios = $this->http->FindSingleNode("td[7]/span", $node);
                $description = $this->http->FindSingleNode("td[8]", $node);
                $this->logger->debug("Avios: {$avios}, Flight date: $expDate");

                if ($avios > 0 && $this->http->FindPreg('/flight/i', false, $description) && ($expDate = strtotime($expDate, false))) {
                    $this->SetExpirationDate(strtotime('+36 month', $expDate));

                    break;
                }
            }
        }

        // not a member
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($this->http->FindSingleNode("//h2[contains(text(), 'Registration to Meridiana Club')]") || $this->http->FindSingleNode('//p[contains(text(), "A few steps to join Meridiana Club")]'))) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
    }
}
