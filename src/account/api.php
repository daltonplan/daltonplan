<?php

declare(strict_types=1);

namespace account;

class api extends \frame\app
{
    function info(\Base $fw): void
    {
        echo "user info";
    }
}
