<?php

namespace AwardWallet\Engine\british;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return
            str_starts_with($question, "To activate your account, you must open the email")
            || stripos($question, "We've sent an email with your code to") !== false
        ;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
