<?php

declare(strict_types=1);

namespace domain;

class api extends \frame\app
{
    function version(\Base $fw): void
    {
        echo $fw->get('version');
    }
}
