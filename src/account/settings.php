<?php

declare(strict_types=1);

namespace account;

use frame\app;
use frame\log;

abstract class settings
{
    static function change(\Base $fw, int $user_id, string $last_name, string $first_name, string $email): void
    {
        $command = \cmd::begin($fw, \cmd::user_change_settings);

        $user = service::get_user($fw, $user_id);
        if (empty($user)) {
            log::error($fw)->write('account\settings::change - not found: ' . $user_id);
            return;
        }

        $cmd_result = 0;

        $rename = !app::no_rename($fw);
        if (!$rename && !service::student($fw))
            $rename = true;

        if ($rename) {
            if (($last_name !== $user[user::last_name]) || ($first_name !== $user[user::first_name])) {
                user::action_update_name($fw, $command, $user_id, $last_name, $first_name);

                $cmd_result++;
            }
        }

        if (app::email($fw)) {
            if (($email !== $user[user::email]) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if (request::command_verify($fw, $user_id, $command, $email, 'account\settings::change'))
                    $cmd_result++;
            }
        }

        if ($cmd_result !== 0)
            \cmd::end($fw, $command, $cmd_result);
    }
}
