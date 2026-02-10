<?php

namespace Tests\Functional\RewardAvailability;


/**
 * @ignore
 */
class RegisterParser extends \TAccountChecker
{
    const withJsonForDB = 0;
    const withoutJsonForDB = 1;
    const userInputError = 2;
    const engineError = 3;
    const throwRetry = 4;
    const withJsonForDBInactive = 5;
    const question = 6;

    public static $checkState;

    public static function reset(): void
    {
        self::$checkState = null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setRandomUserAgent();
    }

    public function registerAccount(array $fields)
    {
        switch (self::$checkState) {
            case self::withJsonForDB:
                $this->ErrorMessage = $this->message($fields);
                return true;
            case self::withJsonForDBInactive:
                $this->ErrorMessage = $this->message($fields, false);
                return true;
            case self::question:
                $question = 'Confirm your email';
                $this->AskQuestion($question, null, 'Question');
                return false;
            case self::withoutJsonForDB:
                $this->ErrorMessage = "Registration is successful! Login: " . $fields['Email'];
                return true;
            case self::userInputError:
                throw new \UserInputError('wrong fields');
            case self::engineError:
                throw new \EngineError('something went wrong');
            case self::throwRetry:
                throw new \CheckRetryNeededException(3, 0);
        }

        return false;
    }

    public function getRegisterFields()
    {
        return [
            "Email" => [
                "Type" => "string",
                "Caption" => "Email address",
                "Required" => true,
            ],
            "Password" => [
                "Type" => "string",
                "Caption" => "Your password must contain at least 8 characters.",
                "Required" => true,
            ],
            "FirstName" => [
                "Type" => "string",
                "Caption" => "First Name",
                "Required" => true,
            ],
            "LastName" => [
                "Type" => "string",
                "Caption" => "Last Name",
                "Required" => true,
            ],
        ];
    }

    private function message($fields, $isActive = true)
    {
        return json_encode([
            "status" => "success",
            "message" => "Registration is successful!",
            "active" => $isActive,
            "login" => "19203872342" . $fields['LastName'],
            "login2" => $fields['LastName'],
            "login3" => "",
            "questions" => [
                [
                    "question" => "Mother's maiden name",
                    "answer" => $fields['LastName'],
                ],
            ],
            "registerInfo" => [
                [
                    "key" => "Email",
                    "value" => $fields['Email'],
                ],
                [
                    "key" => "Last Name",
                    "value" => $fields['LastName'],
                ],
                [
                    "key" => "Country",
                    "value" => "US",
                ],
            ],
        ]);
    }

    // maybe it's worth testing too
    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->Question) {
            $this->logger->error("something went wrong");
            return false;
        }

        if (isset($this->Answers[$this->Question])) {
            $this->logger->info('Got verification link.');
            $verificationLink = $this->Answers[$this->Question];
            unset($this->Answers[$this->Question]);

            $success = ($verificationLink==='success'); // some action
            if($success) {
                $membershipNumber =  '00123456';

                $this->ErrorMessage = json_encode([
                    "status" => "success",
                    "message" => "Registration is successful!",
                    "login" => $membershipNumber,
                    "login2" => "US",
                    "active" => true,
                ], JSON_PRETTY_PRINT);

                return true;
            }
            $this->logger->info('Something wrong with activation');
        } else {
            $this->logger->info('Answer is empty.');
        }

        $this->ErrorMessage = json_encode([
            "status" => "success",
            "message" => "Registration is successful! Activate account on email and login after.",
            "login" => $this->State['login'] ?? null,
            "login2" => "US",
            "active" => false,
        ], JSON_PRETTY_PRINT);

        return true;
    }
}