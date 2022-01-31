<?php

declare(strict_types=1);

namespace account;

use frame\log;
use frame\password;
use frame\session;
use frame\time;

abstract class auth
{
    static function set(\Base $fw, string $user_handle, int $user_pin): void
    {
        $fw->set('login_handle', $user_handle);
        $fw->set('login_pin', $user_pin);
    }

    static function login(\Base $fw, string $handle, string $pin): void
    {
        $command = \cmd::begin($fw, \cmd::user_login);

        $handle = strtoupper($handle); // all uppercase

        $user = user::get_by_handle($fw->db, $handle);
        if (empty($user)) {
            log::info($fw)->write('account\auth::login - does not exist: ' . $handle);
            return;
        }

        $user_id = (int)$user[user::id];
        $user_pin = $user[user::pin];
        $user_handle = $handle;

        if (!password::check($fw, $pin, $user_pin)) {
            user::action_pin_fail($fw->db, $command, $user_id);

            log::warn($fw)->write('account\auth::login - pin fail (' . ($user['pin_fail'] + 1) . '): ' . $user_handle);

            \cmd::end_user($fw, $command, $user_id, 0);
            return;
        }

        $cmd_result = 1;

        if (password::needs_rehash($fw, $user_pin)) {
            $user_pin = password::hash($fw, $pin);

            user::action_update_pin($fw, $command, $user_id, $user_pin);

            $cmd_result++; // command info: 2 - pin rehash
        }

        $fw->set('SESSION.user_id', $user_id);
        $fw->set('SESSION.user_handle', $user_handle);

        access::insert($fw, $command, $user_id, access::logged_in);

        $post_remember = $fw->exists('POST.remember');
        if ($post_remember) {
            $selector = base64_encode(random_bytes(9));
            $authenticator = random_bytes(33);
            $fw->set('COOKIE.dp_remember', $selector . ':' . base64_encode($authenticator), session::cookie_duration);

            remember::insert($fw, $user_id, $selector, $authenticator);
        }

        session::update_cookies($fw);

        \cmd::end_user($fw, $command, $cmd_result);
    }

    static function logout(\Base $fw): void
    {
        $command = \cmd::begin($fw, \cmd::user_logout);

        access::insert($fw, $command, \frame\session::get_user_id($fw), access::logged_out);

        if ($fw->exists('COOKIE.dp_remember')) {
            $remember = $fw->get('COOKIE.dp_remember');

            list($selector) = explode(':', $remember);
            remember::delete_selector($fw, $selector);
        }

        session::reset($fw);

        \cmd::end($fw, $command);
    }

    static function remember(\Base $fw, string $remember): void
    {
        $command = \cmd::begin($fw, \cmd::remember);

        list($selector, $authenticator) = explode(':', $remember);

        $now = time::get_for_db($fw);

        $remembers = remember::get_list($fw->db, $selector, $now);
        if (empty($remembers)) {
            $fw->db->rollback();
            return;
        }

        foreach ($remembers as $value) {
            if (!hash_equals($value[remember::token], hash('sha256', base64_decode($authenticator))))
                continue;

            $user_id = (int)$value[remember::user];
            $user = service::get_user($fw, $user_id);
            if (empty($user))
                continue;

            $user_handle = $user[user::handle];

            $fw->set('SESSION.user_id', $user_id);
            $fw->set('SESSION.user_handle', $user_handle);

            access::insert($fw, $command, $user_id, access::remembered);

            $selector = base64_encode(random_bytes(9));
            $authenticator = random_bytes(33);
            $fw->set('COOKIE.dp_remember', $selector . ':' . base64_encode($authenticator), session::cookie_duration);

            remember::update($fw, (int)$value[remember::id], $selector, $authenticator);

            // remove expired tokens

            $expired = remember::get_list_expires($fw->db, $user_id, $now);
            foreach ($expired as $ex_value) {
                remember::delete_id($fw, (int)$ex_value[remember::id]);;
            }

            session::update_cookies($fw);

            \cmd::end_user($fw, $command, $user_id);

            break;
        }
    }
}
