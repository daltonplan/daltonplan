<?php

declare(strict_types=1);

namespace account;

use frame\base;
use frame\session;

class service
{
    static function get_id(\Base $fw): int
    {
        if (session::proxy($fw)) {
            $proxy_id = session::get_proxy($fw);

            $proxy = service::get_user($fw, $proxy_id);
            if (empty($proxy)) {
                session::clear_proxy($fw);
                return session::get_user_id($fw);
            }

            if ((int)$proxy[user::active] !== user::active_on) {
                session::clear_proxy($fw);
                return session::get_user_id($fw);
            }

            return $proxy_id;
        }

        return session::get_user_id($fw);
    }

    // ---

    static function get_handle(\Base $fw): string
    {
        return session::get_user_handle($fw);
    }

    static function get_role(\Base $fw): int
    {
        return session::get_user_role($fw);
    }

    static function get_active(\Base $fw): bool
    {
        return session::get_user_active($fw);
    }

    static function logged(\Base $fw): bool
    {
        return session::has_user_id($fw);
    }

    static function student(\Base $fw): bool
    {
        return service::get_role($fw) === user::role_student;
    }

    static function coach(\Base $fw): bool
    {
        return service::get_role($fw) === user::role_coach;
    }

    static function owner(\Base $fw): bool
    {
        return service::get_role($fw) === user::role_owner;
    }

    static function admin(\Base $fw): bool
    {
        return service::get_role($fw) === user::role_admin;
    }

    static function moderator(\Base $fw): bool
    {
        return service::get_role($fw) === user::role_moderator;
    }

    static function owner_admin(\Base $fw): bool
    {
        return service::owner($fw) || service::admin($fw);
    }

    static function owner_admin_moderator(\Base $fw): bool
    {
        return service::owner($fw) || service::admin($fw) || service::moderator($fw);
    }

    static function owner_admin_moderator_coach(\Base $fw): bool
    {
        return service::owner($fw) || service::admin($fw) || service::moderator($fw) || service::coach($fw);
    }

    static function logged_owner(\Base $fw): bool
    {
        return service::logged($fw) && service::owner($fw);
    }

    static function logged_owner_admin(\Base $fw): bool
    {
        return service::logged($fw) && service::owner_admin($fw);
    }

    static function logged_owner_admin_moderator(\Base $fw): bool
    {
        return service::logged($fw) && service::owner_admin_moderator($fw);
    }

    static function logged_owner_admin_moderator_coach(\Base $fw): bool
    {
        return service::logged($fw) && service::owner_admin_moderator_coach($fw);
    }

    // ---

    static function setup_db(\Base $fw): void
    {
        access::create($fw);
        remember::create($fw);
        request::create($fw);
        user::create($fw);
    }

    // ---

    static function update(\Base $fw): void
    {
        if (!service::logged($fw))
            return;

        $user = service::get_user($fw, session::get_user_id($fw));
        if (empty($user))
            return;

        session::set_user_role($fw, $user[user::role]);
        if (((int)$user[user::role] === user::role_student) && session::proxy($fw))
            session::clear_proxy($fw);

        session::set_user_active($fw, (bool)$user[user::active]);

        if ((int)$user[user::pin_reset] === user::pin_reset_on)
            $fw->set('force_pin_reset', true);
    }

    static function check_remember(\Base $fw): void
    {
        if (!session::has_remember_cookie($fw))
            return;

        $remember = session::get_remember_cookie($fw);
        if (strlen($remember) === 0)
            return;

        auth::remember($fw, $remember);
    }

    static function has_owner(\Base $fw): bool
    {
        return !empty(user::get_by_creator($fw->db, user::creator_owner, user::id));
    }

    static function create_owner(\Base $fw): bool
    {
        if (service::has_owner($fw))
            return false;

        owner::create($fw);

        return true;
    }

    static function clear_cookie(\Base $fw, string $type): void
    {
        if ($type === 'lang')
            $fw->clear('COOKIE.dp_lang');
        else if ($type === 'sort')
            $fw->clear('COOKIE.dp_sort');
        else if ($type === 'remember')
            $fw->clear('COOKIE.dp_remember');
    }

    static function set_lang(\Base $fw, string $lang): void
    {
        if ($lang !== $fw->get('lang'))
            $fw->set('COOKIE.dp_lang', $lang);
    }

    static function toggle_sort(\Base $fw): void
    {
        if ($fw->get('COOKIE.dp_sort'))
            $fw->clear('COOKIE.dp_sort');
        else
            $fw->set('COOKIE.dp_sort', true);
    }

    static function change_view(\Base $fw, string $handle): void
    {
        $user = user::get_by_handle($fw->db, $handle, user::id);
        if (empty($user))
            return;

        $user_id = (int)$user[user::id];

        if ($user_id !== session::get_user_id($fw))
            session::set_proxy($fw, $user_id);
        else
            session::clear_proxy($fw);
    }

    static function get_user(\Base $fw, int $user_id): array
    {
        $repo_item = base::repo_item(user::table, $user_id);

        if (!$fw->exists($repo_item)) {
            $user = user::get($fw->db, $user_id);
            if (empty($user))
                return array();

            $fw->set($repo_item, $user);
            return $user;
        }

        return (array)$fw->get($repo_item);
    }
}
