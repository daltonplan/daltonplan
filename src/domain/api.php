<?php

declare(strict_types=1);

namespace domain;

use frame\time;

class api extends \frame\app
{
    function version(\Base $fw): void
    {
        echo $fw->get('version');
    }

    function time(\Base $fw): void
    {
        echo time::get_for_db($fw);
    }
}
