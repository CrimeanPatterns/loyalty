<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 18/04/2018
 * Time: 17:44
 */

namespace AppBundle\Event;


use AppBundle\Document\CheckAccount;
use Symfony\Component\EventDispatcher\Event;

class CheckAccountStartEvent extends Event
{
    /** @var CheckAccount */
    private $row;

    const NAME = 'aw.check_account.start';

    public function __construct(CheckAccount $row)
    {
        $this->row = $row;
    }

    /** @return CheckAccount */
    public function getRow(): CheckAccount
    {
        return $this->row;
    }

}