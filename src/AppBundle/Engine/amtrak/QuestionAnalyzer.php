<?php

namespace AwardWallet\Engine\amtrak;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A verification code has been sent to your email");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
