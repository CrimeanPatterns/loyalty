<?php

class BofARetryException extends Exception
{
}

class TAccountCheckerBofASeleniumCard extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const ONE_TIME_CODE_QUESTION_TEXT = 'One time authorization code (was sent to your phone as text message)'; /* review */
    public const ONE_TIME_CODE_QUESTION_EMAIL = 'One time authorization code (was sent to your email)';
    public const SAFE_PASS_CODE_QUESTION = "Please enter SafePass Code which was sent to your mobile device.";

    public function InitBrowser()
    {
        $this->ArchiveLogs = true;
        //$this->AccountFields['BrowserState'] = null;
        $this->InitSeleniumBrowser();
        $this->keepCookies(false);
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        if (!$this->isNewSession()) {
            $this->startNewSession();
        }
        $this->http->GetURL("https://secure.bankofamerica.com/login/sign-in/signOnScreen.go");

        return true;
    }

    public function Login()
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            if ($attempt > 0) {
                $this->http->Log("attempt #{$attempt}");
                $this->startNewSession();
            }
            $loginField = $this->waitForElement(WebDriverBy::id('enterID-input'), 15);

            if (empty($loginField)) {
                return false;
            }
            $loginField->sendKeys($this->AccountFields['Login']);
            $this->driver->findElement(WebDriverBy::xpath("//a[@name = 'enter-online-id-submit']"))->click();
            $this->waitAjax();

            try {
                if (!$this->processErrorsAndQuestions()) {
                    return false;
                }
            } catch (BofARetryException $e) {
                $this->http->Log("retrying");

                continue;
            }

            break;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "question":
                $this->driver->findElement(WebDriverBy::id('tlpvt-challenge-answer'))->sendKeys($this->Answers[$this->Question]);
                $this->driver->findElement(WebDriverBy::xpath("//a[@name = 'enter-online-id-submit']"))->click();
                $this->waitAjax();
                sleep(2);

                return $this->processErrorsAndQuestions();

                break;
        }

        return false;
    }

    public function Parse()
    {
        $points = $this->waitForElement(WebDriverBy::xpath("(//td[contains(text(), 'Total available points:')]/following-sibling::td[1])[1]"), 0, true);

        if (!empty($points)) {
            $this->SetBalance($points->getText());
            $this->keepSession(false);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'You do not have an eligible rewards credit card account to access this website')]"), 0, true)) {
            $this->SetBalanceNA();
            $this->keepSession(false);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//a[@name = 'onh_sign_off']"), 0, true)) {
            $this->SetBalanceNA();
        }
    }

    public function ParseFiles($filesStartDate)
    {
        $this->http->GetURL("https://secure.bankofamerica.com/myaccounts/brain/redirect.go?target=eccdocuments&request_locale=en-us&source=overview&fsd=y");
        $this->driver->executeScript("$('#duration-list').val('Past 12 months')");
        $this->driver->findElement(WebDriverBy::id('document-inbox-submit'))->click();
        $result = [];

        do {
            $this->waitForElement(WebDriverBy::id('documentInboxModuleStatementTable'), 30);
            $rows = $this->driver->findElements(WebDriverBy::xpath("//table[@id = 'documentInboxModuleStatementTable']//tr[contains(@class, 'even') or contains(@class, 'odd')]"));
            $this->http->Log("files rows: " . count($rows));

            foreach ($rows as $index => $row) {
                //				if($index > 1)
                //					break;
                try {
                    $date = $row->findElement(WebDriverBy::xpath("td[1]"))->getText();
                    $account = $row->findElement(WebDriverBy::xpath("td[2]"))->getText();
                    $accountNumber = $row->findElement(WebDriverBy::xpath("td[2]"))->getText();

                    if (preg_match('/(\d{4})$/ims', $accountNumber, $matches)) {
                        $accountNumber = $matches[1];
                    }
                    $filename = $row->findElement(WebDriverBy::xpath("td[3]/a"))->getText();

                    if (!empty($date) && !empty($account) && !empty($filename)) {
                        $d = strtotime($date);

                        if ($d !== false && isset($filesStartDate) && $d < $filesStartDate) {
                            continue;
                        }
                        $this->http->Log("file: $date / $account / $filename");
                        $this->clearDownloads();
                        $row->findElement(WebDriverBy::xpath("td[3]/a"))->click(); // show dropdown
                        $link = null;

                        if (!$this->waitFor(
                            function () use ($row, &$link) {
                                $elements = $row->findElements(WebDriverBy::xpath(".//a[contains(text(), 'Download PDF') and not(contains(text(), 'screen'))]"));

                                foreach ($elements as $link) {
                                    return $link->isDisplayed();
                                }

                                return false;
                            },
                            10
                        )) {
                            $this->http->Log("can't find download link");

                            continue;
                        }
                        $link->click();
                        $file = $this->getLastDownloadedFile(30);

                        if (is_object($file)) { // file is DownloadedFile
                            $tempFile = tempnam(sys_get_temp_dir(), "bofa-pdf-temp");
                            file_put_contents($tempFile, $file->getContents());
                            $file = $tempFile;
                        }

                        if (preg_match('#\.pdf$#ims', $file) && strpos(file_get_contents($file), '%PDF') === 0) {
                            $movedFile = tempnam(sys_get_temp_dir(), "bofa-pdf");
                            copy($file, $movedFile);
                            unlink($file);
                            $result[] = [
                                'AccountNumber' => $accountNumber,
                                'AccountName'   => $account,
                                'FileDate'      => strtotime($date),
                                'Name'          => $filename,
                                'Extension'     => 'pdf',
                                'Contents'      => $movedFile,
                            ];
                        } else {
                            $this->http->Log("failed to download file: $file, name check: " . var_export(preg_match('#\.pdf$#ims', $file), true) . ", data check: " . var_export(strpos(file_get_contents($file), '%PDF'), true) . ", contents: " . substr(file_get_contents($file), 0, 4), LOG_LEVEL_ERROR);
                        }
                    } else {
                        $this->http->Log("some field empy: date: $date, account: $account, filename: $filename");
                    }
                } catch (NoSuchElementException $e) {
                    $this->http->Log("no element for row $index: " . $e->getMessage());
                }
            }
            $nextPageLink = $this->waitForElement(WebDriverBy::xpath("//a[@class = 'paginate-link next']"), 3);

            if (!empty($nextPageLink)) {
                $nextPageLink->click();
                $this->waitAjax();
            }
        } while (!empty($nextPageLink));
        $this->http->Log(var_export($result, true));

        return $result;
    }

    protected function processErrorsAndQuestions()
    {
        $startTime = time();

        while ((time() - $startTime) < 30) {
            // security question
            $question = $this->waitForElement(WebDriverBy::cssSelector("label[for = 'tlpvt-challenge-answer']"), 0, true);

            if (!empty($question)) {
                $question = $question->getText();
                $this->holdSession();
                sleep(2); // wait for error message
                $this->AskQuestion($question, $this->getErrorText(), 'question');

                return false;
            }
            // one time code by email
            $radio = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'tlpvt-email1']"), 0, true);

            if (!empty($radio)) {
                $this->holdSession();
                $radio->click();
                //$this->driver->findElement(WebDriverBy::id('btnARContinue'))->click();
                unset($this->Answers[self::ONE_TIME_CODE_QUESTION_EMAIL]);
                $this->AskQuestion(self::ONE_TIME_CODE_QUESTION_EMAIL, null, 'otc');

                return false;
            }
            // one time code by text message
            $radio = $this->waitForElement(WebDriverBy::xpath("//label[@for = 'rbText1']"), 0, true);

            if (!empty($radio)) {
                $this->holdSession();
                $radio->click();
                //$this->driver->findElement(WebDriverBy::id('btnARContinue'))->click();
                unset($this->Answers[self::ONE_TIME_CODE_QUESTION_TEXT]);
                $this->AskQuestion(self::ONE_TIME_CODE_QUESTION_TEXT, null, 'otc');

                return false;
            }
            // safepass
            $button = $this->waitForElement(WebDriverBy::cssSelector("a[title = 'Send SafePass Code refreshes this panel']"), 0, true);

            if (!empty($button)) {
                $this->holdSession();
                //$button->click();
                unset($this->Answers[self::SAFE_PASS_CODE_QUESTION]);
                $this->AskQuestion(self::SAFE_PASS_CODE_QUESTION, null, 'safepass');

                return false;
            }
            // password
            $input = $this->waitForElement(WebDriverBy::id("tlpvt-passcode-input"), 0, true);

            if (!empty($input)) {
                $input->sendKeys($this->AccountFields['Pass']);
                $this->driver->findElement(WebDriverBy::id('passcode-confirm-sk-submit'))->click();
                $this->waitAjax();
            }
            // login error
            $error = $this->getErrorText();

            if (!empty($error)) {
                if ($error == 'SiteKey temporarily unavailable') {
                    throw new BofARetryException();

                    break;
                }

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            // success - account shown
            if ($this->waitForElement(WebDriverBy::cssSelector('div.account-box'), 0, true)) {
                return true;
            }
            // success - signoff shown
            if ($this->waitForElement(WebDriverBy::xpath("//a[@name = 'onh_sign_off']"), 0, true)) {
                return true;
            }
            sleep(1);
        }

        return false;
    }

    protected function getErrorText()
    {
        $result = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'error-message']/p[contains(@style, 'normal') or contains(@class, 'title-msg')]"), 0, true);

        if (!empty($result)) {
            $result = $result->getText();
            $result = preg_replace('#Common\s+reasons\s+for\s+errors\+include\:#ims', '', $result);
            $result = preg_replace('#We\s+can\'t\s+process\s+your\s+request\:#ims', '', $result);
        }

        return $result;
    }
}
