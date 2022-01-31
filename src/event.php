<?php

declare(strict_types=1);

abstract class event
{
    const revision = 0;

    const request           = 1;    // account
    const user              = 2;    // account

    const lab_subject       = 3;    // core
    const lab_team          = 4;    // core
    const lab               = 5;    // core
    const plan_team         = 6;    // core
    const plan              = 7;    // core
    const subject           = 8;    // core
    const team_subject      = 9;    // core
    const team_user         = 10;   // core
    const team              = 11;   // core

    const book              = 12;   // plan
    const frame             = 13;   // plan
    const period            = 14;   // plan
    const period_team       = 15;   // plan
    const register          = 16;   // plan
    const week              = 17;   // plan
}
