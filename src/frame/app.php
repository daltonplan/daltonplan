<?php

declare(strict_types=1);

namespace frame;

class app
{
    static function invite_only(\Base $fw): bool
    {
        return $fw->get('dp_invite_only') === 1;
    }

    static function no_rename(\Base $fw): bool
    {
        return $fw->get('dp_no_rename') === 1;
    }

    static function show_terms(\Base $fw): bool
    {
        return $fw->get('dp_terms') === 1;
    }

    static function email(\Base $fw): bool
    {
        return $fw->get('dp_email') === 1;
    }

    function beforeRoute(\Base $fw): void
    {
        version::set_info($fw);

        log::setup($fw);

        db::setup($fw);

        lang::setup($fw);

        time::set($fw);

        if (!\account\service::logged($fw))
            \account\service::check_remember($fw);

        if ($fw->get('log_view'))
            \domain\view::insert($fw, session::get_user_id($fw));

        \account\service::update($fw);

        if ($fw->exists('force_pin_reset')) {
            $user_handle = session::get_user_handle($fw);

            \account\auth::logout($fw);

            \account\web::redirect_view($fw, $user_handle);
            return;
        }

        $fw->set('BASE_URL', $fw->get('SCHEME') . '://' . $fw->get('HOST') . $fw->get('BASE') . '/');

        if ($fw->exists('last_update')) {
            $date_format = $fw->get('DICT.date_format');

            $last_update = strtotime($fw->get('last_update'));
            $fw->set('last_update', date($date_format, $last_update));
        }

        \plan\service::set_list($fw);
        \plan\service::set_current_list($fw);
    }

    function afterRoute(\Base $fw): void
    {
        if ($fw->get('log_db_print') && $fw->exists('db'))
            echo $fw->db->log();
    }
}
