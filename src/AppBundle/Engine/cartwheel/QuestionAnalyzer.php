<?php

namespace AwardWallet\Engine\cartwheel;

use AwardWallet\Common\OneTimeCode\EmailQuestionAnalyzerInterface;

class QuestionAnalyzer implements EmailQuestionAnalyzerInterface
{
    public static function isOtcQuestion(string $question): bool
    {
        return stripos($question, " code to ") !== false;
    }

    public static function getHoldsSession(): bool
    {
        return true;
    }
}
