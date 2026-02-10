<?php

namespace AwardWallet\Engine\avis;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "For added security, please enter the verification code that has been sent to your");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
