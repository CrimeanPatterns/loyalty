<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 27/12/2016
 * Time: 14:04
 */

namespace AppBundle\Extension;


class TimeCommunicator
{

    /** @return int */
    public function getCurrentTime()
    {
        return time();
    }

    /**
     * @return \DateTime
     */
    public function getCurrentDateTime()
    {
        return new \DateTime();
    }

    /** @param int $time */
    public function sleep($time)
    {
        sleep($time);
    }

    /** @param int $time */
    public function usleep($time)
    {
        usleep($time);
    }

}