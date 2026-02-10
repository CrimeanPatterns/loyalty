<?php

namespace AppBundle\Worker\CheckExecutor;

use AwardWallet\ExtensionWorker\ParseHistoryOptions;

class CheckerHistoryOptionsConverter
{

    public static function setCheckerHistoryOptions(?ParseHistoryOptions $options, \TAccountChecker $checker) : void
    {
        if ($options === null) {
            return;
        }

        $checker->WantHistory = true;

        if ($options->getStartDate() !== null) {
            $checker->HistoryStartDate = $options->getStartDate()->getTimestamp();
        }

        foreach ($options->getAllSubAccountStartDates() as $code => $date) {
            $checker->setHistoryStartDate($code, $date->getTimestamp());
        }

        $checker->strictHistoryStartDate = $options->isStrictHistoryStartDate();
    }

}