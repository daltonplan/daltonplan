<?php

declare(strict_types=1);

class cmd
{
    const revision = 0;

    // result
    const error = 0;
    const ok = 1;
    // result > 1 = event count

    // cmd
    const create_owner              = 1;
    const remember                  = 2;
    const user_login                = 3;
    const user_logout               = 4;
    const user_change_settings      = 5;
    const user_request_join         = 6;
    const user_join                 = 7;
    const user_request_verify       = 8;
    const user_verify               = 9;
    const user_request_reset        = 10;
    const user_reset                = 11;
    const plan_add                  = 12;
    const user_add                  = 13;
    const lab_add                   = 14;
    const subject_add               = 15;
    const team_add                  = 16;
    const plan_assign_team          = 17;
    const team_assign_user          = 18;
    const week_add                  = 19;
    const frame_add                 = 20;
    const frame_update              = 21;
    const frame_remove              = 22;
    const lab_update                = 23;
    const lab_remove                = 24;
    const team_update               = 25;
    const team_remove               = 26;
    const subject_update            = 27;
    const subject_remove            = 28;
    const book                      = 29;
    const unbook                    = 30;
    const book_period               = 31;
    const period_commit             = 32;
    const plan_time_machine         = 33;
    const plan_reset_time_machine   = 34;
    const plan_update               = 35;
    const plan_remove               = 36;
    const week_update               = 37;
    const week_delete               = 38;
    const period_update             = 39;
    const user_absent               = 40;
    const user_present              = 41;
    const user_pin_reset            = 42;
    const user_update               = 43;
    const user_remove               = 44;
    const user_assign_team          = 45;
    const book_present              = 46;
    const book_exclused             = 47;
    const book_free                 = 48;
    const book_blocked              = 49;
    const book_remove               = 50;
    const book_lab_present          = 51;
    const book_lab_blocked          = 52;
    const book_lab_clear            = 53;
    const user_pin_new              = 54;
    const lab_restrict              = 55;
    const week_import_periods       = 56;
    const lab_change_code           = 57;
    const team_assign_subject       = 58;
    const rebook_period             = 59;
    const book_participation        = 60;
    const period_remove_commit      = 61;
    const book_period_users         = 62;
    const user_register             = 63;
    const book_update_report        = 64;
    const subject_archive           = 65;
    const subject_activate          = 66;
    const lab_archive               = 67;
    const lab_activate              = 68;

    static function begin(\Base $fw, int $cmd): int
    {
        return \domain\command::begin($fw, $cmd);
    }

    static function begin_input(\Base $fw, int $cmd, array $payload): int
    {
        return \domain\command::begin_input($fw, $cmd, $payload);
    }

    static function end(\Base $fw, int $id, int $result = 1): void
    {
        \domain\command::end($fw, $id, $result);
    }

    // overrides user
    static function end_user(\Base $fw, int $id, int $user, int $result = 1): void
    {
        \domain\command::end_user($fw, $id, $user, $result);
    }

    // overrides cmd
    static function end_cmd(\Base $fw, int $id, int $cmd, int $result = 1): void
    {
        \domain\command::end_cmd($fw, $id, $cmd, $result);
    }
}
