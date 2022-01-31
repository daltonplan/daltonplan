<?php

declare(strict_types=1);

namespace account;

use frame\log;
use frame\password;
use frame\session;
use frame\time;

abstract class verify
{
    static function join(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_join);

        $request = request::get_by_handle($fw->db, $handle);
        if (empty($request)) {
            log::warn($fw)->write('account\verify::join - not found: ' . $handle);
            return;
        }

        $request_id = (int)$request['id'];
        $email = $request['email'];

        if (!verify::check_request($fw, $handle, $request, $email, request::join_verify, 'account\verify::join'))
            return;

        $user_handle = user::new_handle($fw->db);
        $user_pin = password::pin($fw);
        $user_pin_hashed = password::hash_pin($fw, $user_pin);

        $user_id = user::action_insert(
            $fw,
            $command,
            $user_handle,
            '',
            '',
            $email,
            1,
            $user_pin_hashed,
            0,
            $request_id,
            user::role_student,
            session::get_user_id($fw)
        );

        request::action_delete($fw, $command, $request_id, $user_id, request::event_joined);

        auth::set($fw, $user_handle, $user_pin);

        \cmd::end($fw, $command, 2);
    }

    static function user(\Base $fw, int $user_id): void
    {
        $command = \cmd::begin($fw, \cmd::user_request_verify);

        $user = service::get_user($fw, $user_id);
        if (empty($user)) {
            log::error($fw)->write('account\verify::user - not found: ' . $user_id);
            return;
        }

        $email = $user['email'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            log::error($fw)->write('account\verify::user - wrong email: ' . $user_id);
            return;
        }

        if (!request::command_verify($fw, $user_id, $command, $email, 'verify_request'))
            return;

        \cmd::end($fw, $command);
    }

    static function email(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::user_verify);

        $request = request::get_by_handle($fw->db, $handle);
        if (empty($request)) {
            log::warn($fw)->write('account\verify::email - not found: ' . $handle);
            return;
        }

        $request_id = (int)$request['id'];
        $email = $request['email'];

        if (!verify::check_request($fw, $handle, $request, $email, request::user_verify, 'account\verify::email'))
            return;

        $user_id = (int)$request['user'];

        user::action_update_email($fw, $command, $user_id, $email);

        request::action_delete($fw, $command, $request_id, $user_id, request::event_verifed);

        \cmd::end($fw, $command, 2);
    }

    static function check_request(
        \Base $fw,
        string $handle,
        array $request,
        string $email,
        int $user_request_state,
        string $command_name
    ): bool {
        if ((int)$request['state'] !== $user_request_state) {
            log::warn($fw)->write($command_name . ' - state mismatch: ' . $handle);
            return false;
        }

        if (time::request_time_expired($fw, $request['updated'])) {
            log::info($fw)->write($command_name . '  time expired: ' . $handle);
            return false;
        }

        if (user::exists($fw->db, $email)) {
            $user = user::get_by_email($fw->db, $email, 'id');
            if ((int)$user['id'] !== (int)$request['user']) {
                log::info($fw)->write($command_name . ' id mismatch: ' . $email . ' for handle ' . $handle);
                return false;
            }
        }

        return true;
    }
}
