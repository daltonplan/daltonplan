<?php

declare(strict_types=1);

namespace account;

use frame\password;

abstract class owner
{
    static function create(\Base $fw): void
    {
        $command = \cmd::begin($fw, \cmd::create_owner);

        $user_handle = user::new_handle($fw->db);
        $user_pin = password::pin($fw);
        $user_pin_hashed = password::hash_pin($fw, $user_pin);

        $user_id = user::action_insert_owner($fw, $command, $user_handle, $user_pin_hashed);

        \cmd::end_user($fw, $command, $user_id);

        auth::set($fw, $user_handle, $user_pin);
    }
}
