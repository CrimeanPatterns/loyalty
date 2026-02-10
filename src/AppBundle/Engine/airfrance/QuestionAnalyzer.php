<?php

namespace AwardWallet\Engine\airfrance;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return str_starts_with($question, "We’ve sent the PIN code to your e-mail");
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
