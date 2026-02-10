<?php

namespace AwardWallet\Engine\dell;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "A one-time verification code has been sent to your registered email")
            || str_starts_with($question, "Enter the code we sent to")
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
