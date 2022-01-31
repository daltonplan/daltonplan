<?php

declare(strict_types=1);

namespace domain;

class service
{
    static function setup_db($fw): void
    {
        command::create($fw);
        event::create($fw);
        view::create($fw);

        \core\service::setup_db($fw);

        \account\service::setup_db($fw);
    }
}
