<?php

namespace AwardWallet\Engine\airasia;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "To continue, please enter the OTP");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
