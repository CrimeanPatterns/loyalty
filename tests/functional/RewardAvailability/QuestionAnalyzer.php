<?php

namespace Tests\Functional\RewardAvailability;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "Confirm your email");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
