<?php

declare(strict_types=1);

namespace frame;

use account\user;

class session
{
    const cookie_duration = 4838400; // 8 weeks in seconds

    static function get_user_id(\Base $fw): int
    {
        if ($fw->exists('SESSION.user_id'))
            return (int)$fw->get('SESSION.user_id');

        return 0;
    }

    static function set_user_id(\Base $fw, int $id): void
    {
        $fw->set('SESSION.user_id', $id);
    }

    static function has_user_id(\Base $fw): bool
    {
        return $fw->exists('SESSION.user_id');
    }

    static function get_user_handle(\Base $fw): string
    {
        if ($fw->exists('SESSION.user_handle'))
            return $fw->get('SESSION.user_handle');

        return '';
    }

    static function set_user_handle(\Base $fw, string $handle): void
    {
        $fw->set('SESSION.user_handle', $handle);
    }

    static function get_user_role(\Base $fw): int
    {
        if ($fw->exists('SESSION.user_role'))
            return (int)$fw->get('SESSION.user_role');

        return user::role_student;
    }

    static function get_user_role_fw(): int
    {
        return session::get_user_role(base::get());
    }

    static function set_user_role(\Base $fw, int $role): void
    {
        $fw->set('SESSION.user_role', $role);
    }

    static function has_user_role(\Base $fw): bool
    {
        return $fw->exists('SESSION.user_role');
    }

    static function get_user_active(\Base $fw): bool
    {
        if ($fw->exists('SESSION.user_active'))
            return (bool)$fw->get('SESSION.user_active');

        return false;
    }

    static function set_user_active(\Base $fw, bool $active): void
    {
        $fw->set('SESSION.user_active', $active);
    }

    static function has_user_active(\Base $fw): bool
    {
        return $fw->exists('SESSION.user_active');
    }

    static function clear_user(\Base $fw): void
    {
        $fw->clear('SESSION.user_id');
        $fw->clear('SESSION.user_handle');
        $fw->clear('SESSION.user_role');
        $fw->clear('SESSION.user_active');
    }

    static function has_remember_cookie(\Base $fw): bool
    {
        return $fw->exists('COOKIE.dp_remember');
    }

    static function get_remember_cookie(\Base $fw): string
    {
        return $fw->get('COOKIE.dp_remember');
    }

    static function clear_cookies(\Base $fw): void
    {
        $fw->clear('COOKIE.dp_remember');
        $fw->clear('COOKIE.dp_sort');
        $fw->clear('COOKIE.dp_lang');
    }

    static function get_sort(\Base $fw): bool
    {
        if ($fw->exists('COOKIE.dp_sort'))
            return (int)$fw->get('COOKIE.dp_sort') === 1;

        return false;
    }

    static function get_sort_fw(): bool
    {
        return session::get_sort(base::get());
    }

    static function update_cookies(\Base $fw): void
    {
        if ($fw->exists('COOKIE.dp_sort'))
            $fw->set('COOKIE.dp_sort', $fw->get('COOKIE.dp_sort'), session::cookie_duration);
        else
            $fw->set('COOKIE.dp_sort', true, session::cookie_duration);

        if ($fw->exists('COOKIE.dp_lang'))
            $fw->set('COOKIE.dp_lang', $fw->get('COOKIE.dp_lang'), session::cookie_duration);
        else
            $fw->set('COOKIE.dp_lang', $fw->get('lang'), session::cookie_duration);
    }

    static function proxy(\Base $fw): bool
    {
        return $fw->exists('SESSION.proxy');
    }

    static function get_proxy(\Base $fw): int
    {
        return (int)$fw->get('SESSION.proxy');
    }

    static function set_proxy(\base $fw, int $user_id): void
    {
        $fw->set('SESSION.proxy', $user_id);
    }

    static function clear_proxy(\Base $fw): void
    {
        $fw->clear('SESSION.proxy');
    }

    // ---

    static function new_csrf(\Base $fw): string
    {
        $csrf = id::gen_max(64, false);
        $fw->set('SESSION.csrf', $csrf);
        return $csrf;
    }

    static function get_csrf(\Base $fw): string
    {
        if (!$fw->exists('SESSION.csrf'))
            return '';

        $csrf = $fw->get('SESSION.csrf');
        $fw->clear('SESSION.csrf');
        return (strlen($csrf) === 0) ? '' : $csrf;
    }

    static function csrf(\Base $fw, string $token): bool
    {
        $csrf = session::get_csrf($fw);
        return ((strlen($csrf) !== 0) && (strlen($token) !== 0) && ($token === $csrf));
    }

    static function check_csrf(\Base $fw, string $scope, string $info): bool
    {
        if (!$fw->exists('POST.token')) {
            log::warn($fw)->write($scope . ' - csrf missing: ' . $info);
            return false;
        }

        if (!session::csrf($fw, $fw->get('POST.token'))) {
            log::warn($fw)->write($scope . ' - csrf wrong: ' . $info);
            return false;
        }

        return true;
    }

    static function check_csrf_redirect(\Base $fw, string $scope, string $info): bool
    {
        if (session::check_csrf($fw, $scope, $info))
            return true;

        base::redirect($fw);
        return false;
    }

    // ---

    static function clear(\Base $fw): void
    {
        $fw->clear('SESSION');
        $fw->clear('COOKIE');
    }

    static function clear_cookie(): void
    {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', 0, $params['path'], $params['domain'], $params['secure'], isset($params['httponly']));
    }

    static function reset(\Base $fw): void
    {
        session::clear_user($fw);
        session::clear_cookies($fw);

        session::clear($fw);
        session::clear_cookie();
    }
}
