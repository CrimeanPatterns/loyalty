<?php

namespace AwardWallet\Engine\cvs;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "A 6-digit verification code was sen");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
