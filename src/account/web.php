<?php

declare(strict_types=1);

namespace account;

use core\lab;
use core\plan;
use core\subject;
use core\team;

use frame\app;
use frame\base;
use frame\session;

class web extends app
{
    static function logged(\Base $fw): bool
    {
        if (!service::logged($fw)) {
            web::redirect_login($fw);
            return false;
        }

        return true;
    }

    static function logged_user(\Base $fw): bool
    {
        if (!web::logged($fw))
            return false;

        return web::set_user($fw);
    }

    static function set_user(\Base $fw): bool
    {
        $user_id = service::get_id($fw);

        if (!user::set($fw, $user_id)) {
            web::redirect_logout($fw);
            return false;
        }

        return true;
    }

    static function redirect_login(\Base $fw): void
    {
        $fw->reroute('/login');
    }

    static function redirect_logout(\Base $fw): void
    {
        auth::logout($fw);

        base::redirect($fw);
    }

    static function redirect_view(\Base $fw, string $user_handle): void
    {
        $fw->reroute('/u/' . $user_handle);
    }

    static function login_unchecked(\Base $fw): void
    {
        session::new_csrf($fw);
        base::render('account/login.htm');
    }

    static function logged_owner(\Base $fw): bool
    {
        if (!web::logged($fw))
            return false;

        if (service::owner($fw))
            return true;

        base::redirect($fw);
        return false;
    }

    static function logged_admin(\Base $fw): bool
    {
        if (!web::logged($fw))
            return false;

        if (service::admin($fw))
            return true;

        base::redirect($fw);
        return false;
    }

    static function logged_owner_admin(\Base $fw): bool
    {
        if (!web::logged($fw))
            return false;

        if (service::owner($fw))
            return true;

        if (service::admin($fw))
            return true;

        base::redirect($fw);
        return false;
    }

    static function logged_owner_admin_moderator(\Base $fw): bool
    {
        if (!web::logged($fw))
            return false;

        if (service::owner($fw))
            return true;

        if (service::admin($fw))
            return true;

        if (service::moderator($fw))
            return true;

        base::redirect($fw);
        return false;
    }

    static function logged_owner_admin_moderator_coach(\Base $fw): bool
    {
        if (!web::logged($fw))
            return false;

        if (service::owner($fw))
            return true;

        if (service::admin($fw))
            return true;

        if (service::moderator($fw))
            return true;

        if (service::coach($fw))
            return true;

        base::redirect($fw);
        return false;
    }

    static function fetch_logged(\Base $fw): bool
    {
        if (!service::logged($fw)) {
            base::fetch_no_permission();
            return false;
        }

        return true;
    }

    static function fetch_logged_owner(\Base $fw): bool
    {
        if (!service::logged_owner($fw)) {
            base::fetch_no_permission();
            return false;
        }

        return true;
    }

    static function fetch_logged_owner_admin(\Base $fw): bool
    {
        if (!service::logged_owner_admin($fw)) {
            base::fetch_no_permission();
            return false;
        }

        return true;
    }

    static function fetch_logged_owner_admin_moderator(\Base $fw): bool
    {
        if (!service::logged_owner_admin_moderator($fw)) {
            base::fetch_no_permission();
            return false;
        }

        return true;
    }

    static function fetch_logged_owner_admin_moderator_coach(\Base $fw): bool
    {
        if (!service::logged_owner_admin_moderator_coach($fw)) {
            base::fetch_no_permission();
            return false;
        }

        return true;
    }

    // ---

    function login(\Base $fw): void
    {
        if (service::logged($fw)) {
            base::redirect($fw);
            return;
        }

        web::login_unchecked($fw);
    }

    function login_action(\Base $fw): void
    {
        if (service::logged($fw)) {
            base::redirect($fw);
            return;
        }

        if (!$fw->exists('POST.handle') || !$fw->exists('POST.pin')) {
            base::redirect($fw);
            return;
        }

        $post_handle = base::trim_fw($fw, 'POST.handle', user::handle_length);
        $post_pin = base::trim_fw($fw, 'POST.pin', 'user_pin_length');

        if ((strlen($post_handle) === 0) || (strlen($post_pin) === 0)) {
            base::redirect($fw);
            return;
        }

        if (!session::check_csrf($fw, 'user::login', $post_handle)) {
            base::redirect($fw);
            return;
        }

        auth::login($fw, $post_handle, $post_pin);

        base::redirect($fw);
    }

    function join(\Base $fw): void
    {
        if (web::logged($fw)) {
            base::redirect($fw);
            return;
        }

        if (app::invite_only($fw)) {
            base::redirect($fw);
            return;
        }

        session::new_csrf($fw);
        base::render('account/join.htm');
    }

    function join_action(\Base $fw): void
    {
        if (web::logged($fw)) {
            base::redirect($fw);
            return;
        }

        if (app::invite_only($fw)) {
            base::redirect($fw);
            return;
        }

        if (!$fw->exists('POST.email') || !$fw->exists('POST.accept')) {
            base::redirect($fw);
            return;
        }

        $email = base::trim_fw($fw, 'POST.email', 'max_email_length');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            base::redirect($fw);
            return;
        }

        if (!session::check_csrf($fw, 'user::join', $email)) {
            base::redirect($fw);
            return;
        }

        request::command_join($fw, $email);

        $fw->reroute('/verify');
    }

    function fetch_logout(\Base $fw): void
    {
        if (!web::logged($fw)) {
            base::fetch_no_permission();
            return;
        }

        session::new_csrf($fw);
        base::render('account/fetch/logout.htm');
    }

    function logout(\Base $fw): void
    {
        if (!web::logged($fw))
            return;

        if (!session::check_csrf($fw, 'user::logout', service::get_handle($fw))) {
            base::redirect($fw);
            return;
        }

        web::redirect_logout($fw);
    }

    function verify(\Base $fw): void
    {
        if (!app::email($fw)) {
            base::redirect($fw);
            return;
        }

        session::new_csrf($fw);
        base::render('account/verify.htm');
    }

    function verify_action(\Base $fw): void
    {
        if (!app::email($fw)) {
            base::redirect($fw);
            return;
        }

        if (!$fw->exists('POST.handle')) {
            base::redirect($fw);
            return;
        }

        $handle = base::trim_fw($fw, 'POST.handle', base::max_handle_length);
        if (strlen($handle) === 0) {
            base::redirect($fw);
            return;
        }

        if (!session::check_csrf($fw, 'user::verify', $handle)) {
            base::redirect($fw);
            return;
        }

        if (!service::logged($fw)) {
            verify::join($fw, $handle);

            web::login_unchecked($fw);
            return;
        }

        verify::email($fw, $handle);

        base::redirect($fw);
    }

    function overview(\Base $fw): void
    {
        if (!web::logged_user($fw))
            return;

        $user_id = service::get_id($fw);

        request::set_requests($fw, $user_id);

        $fw->set('plan_count', sizeof($fw->get(plan::list)));

        if (\account\service::student($fw))
            subject::set_list_only_team_list($fw, $fw->get(team::list));
        else
            subject::set_list($fw);

        $fw->set('subject_count', subject::count_deep($fw->get(subject::list), (\account\service::student($fw))));

        if (\account\service::student($fw)) {
            subject::set_list_only_team_list($fw, $fw->get(team::list));
            lab::set_list_only_user($fw);
        } else {
            lab::set_list($fw);
        }

        $fw->set('lab_count', sizeof($fw->get(lab::list)));

        $fw->set('team_count', team::count($fw->db));
        $fw->set('user_count', user::count($fw->db));

        $fw->set('report_count', \plan\service::count_reports($fw, $user_id));

        \plan\service::set_next_book_list($fw, $user_id);

        base::render('account/overview.htm');
    }

    function users(\Base $fw): void
    {
        if (!web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!web::set_user($fw))
            return;

        if ($fw->exists('PARAMS.team')) {
            $team_handle = $fw->get('PARAMS.team');
            if (!user::set_list_detail_by_team($fw, $team_handle)) {
                $fw->reroute('/users');
                return;
            }
        } else {
            user::set_list_detail($fw);
        }

        base::render('account/users.htm');
    }

    function fetch_users_team(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin_moderator_coach($fw))
            return;

        team::set_list($fw);

        session::new_csrf($fw);
        base::render('account/fetch/users_team.htm');
    }

    function users_team(\Base $fw): void
    {
        if (!web::logged($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'users_team', service::get_handle($fw)))
            return;

        if (!$fw->exists('POST.team')) {
            $fw->reroute('/users');
            return;
        }

        $team = $fw->get('POST.team');

        $fw->reroute('/users/t/' . $team);
    }

    function fetch_settings(\Base $fw): void
    {
        if (!web::fetch_logged($fw))
            return;

        $user_id = session::get_user_id($fw);

        if (!user::set($fw, $user_id)) {
            base::fetch_no_permission();
            return;
        }

        $user_verified = (int)$fw->get('user.verified');

        if (($user_verified === 0) && (request::has_requests($fw, $user_id)))
            $fw->set('verify_email_request', true);

        session::new_csrf($fw);
        base::render('account/fetch/settings.htm');
    }

    function settings(\Base $fw): void
    {
        if (!web::logged($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'user::settings', service::get_handle($fw)))
            return;

        $last_name = '';
        if ($fw->exists('POST.last_name'))
            $last_name = base::trim_fw($fw, 'POST.last_name', 'max_name_length');

        $first_name = '';
        if ($fw->exists('POST.first_name'))
            $first_name = base::trim_fw($fw, 'POST.first_name', 'max_name_length');

        $email = '';
        if (app::email($fw))
            $email = base::trim_fw($fw, 'POST.email', 'max_email_length');

        settings::change($fw, session::get_user_id($fw), $last_name, $first_name, $email);

        $lang = base::trim_fw($fw, 'POST.lang', 'max_lang_length');
        service::set_lang($fw, $lang);

        base::return($fw);
    }

    function sort(\Base $fw): void
    {
        if (!web::logged($fw))
            return;

        service::toggle_sort($fw);

        base::return($fw);
    }

    function delete_cookie(\Base $fw): void
    {
        if (!web::logged($fw))
            return;

        $type = $fw->get('PARAMS.type');
        service::clear_cookie($fw, $type);

        base::return($fw);
    }

    function reset(\Base $fw): void
    {
        // todo: maybe allow to reset ?
        if (!web::logged($fw))
            return;

        echo "reset";

        // TODO

        if (!web::logged_owner_admin($fw)) // <-- better only allow this roles
            return;
    }

    function fetch_change_view(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin_moderator_coach($fw))
            return;

        $user_list = user::get_list($fw->db);
        $fw->set('user_list', $user_list);

        session::new_csrf($fw);
        base::render('account/fetch/change_view.htm');
    }


    function change_view(\Base $fw): void
    {
        if (!service::logged($fw)) {
            $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);

            if (user::command_pin_new($fw, $handle)) {
                web::login_unchecked($fw);
                return;
            }
        }

        if (!web::logged_owner_admin_moderator_coach($fw))
            return;

        $handle = '';

        if ($fw->exists('PARAMS.handle'))
            $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        else if ($fw->exists('POST.user'))
            $handle = base::trim_fw($fw, 'POST.user', user::handle_length);

        if (strlen($handle) === 0) {
            base::redirect($fw);
            return;
        }

        service::change_view($fw, $handle);

        base::return($fw);
    }

    function fetch_add(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin($fw))
            return;

        team::set_list($fw);

        session::new_csrf($fw);
        base::render('account/fetch/add.htm');
    }

    function add(\Base $fw): void
    {
        if (!web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, user::table, service::get_handle($fw)))
            return;

        if (!$fw->exists('POST.last_name') || !$fw->exists('POST.first_name') || !$fw->exists('POST.role')) {
            base::redirect($fw);
            return;
        }

        if (app::email($fw) && !$fw->exists('POST.email')) {
            base::redirect($fw);
            return;
        }

        // TODO: move to user_data ? see edit/update

        $last_name = base::trim_fw($fw, 'POST.last_name', 'max_name_length');
        $first_name = base::trim_fw($fw, 'POST.first_name', 'max_name_length');

        $role = (int)$fw->get('POST.role');

        $email = '';
        if (app::email($fw)) {
            $email = base::trim_fw($fw, 'POST.email', 'max_email_length');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                $email = '';
        }

        $teams = (array)$fw->get('POST.teams');

        $user_handle = user::command_add($fw, $last_name, $first_name, $email, $role, $teams);
        if ($user_handle === '')
            base::return($fw);
        else
            web::redirect_view($fw, $user_handle);
    }

    function absent(\Base $fw): void
    {
        if (!web::logged_owner_admin_moderator_coach($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        user::command_absent($fw, $handle);

        base::return($fw);
    }

    function present(\Base $fw): void
    {
        if (!web::logged_owner_admin_moderator_coach($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        user::command_present($fw, $handle);

        base::return($fw);
    }

    function fetch_pin_reset(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        if (!user::set_by_handle($fw, $handle)) {
            base::fetch_no_permission();
            return;
        }

        session::new_csrf($fw);
        base::render('account/fetch/pin_reset.htm');
    }

    function pin_reset(\Base $fw): void
    {
        if (!web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, user::table, service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        user::command_pin_reset($fw, $handle);

        base::return($fw);
    }

    function fetch_edit(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        if (!user::set_by_handle($fw, $handle)) {
            base::fetch_no_permission();
            return;
        }

        $user_id = (int)$fw->get(user::table . '.' . user::id);

        team::set_list_by_user($fw, $user_id);

        session::new_csrf($fw);
        base::render('account/fetch/edit.htm');
    }

    function edit(\Base $fw): void
    {
        if (!web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, user::table, service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        if (!$fw->exists('POST.last_name') || !$fw->exists('POST.first_name') || !$fw->exists('POST.role')) {
            base::redirect($fw);
            return;
        }

        if (app::email($fw) && !$fw->exists('POST.email')) {
            base::redirect($fw);
            return;
        }

        $last_name = base::trim_fw($fw, 'POST.last_name', 'max_name_length');
        $first_name = base::trim_fw($fw, 'POST.first_name', 'max_name_length');

        $email = '';
        if (app::email($fw)) {
            $email = base::trim_fw($fw, 'POST.email', 'max_email_length');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                $email = '';
        }

        $role = (int)$fw->get('POST.role');

        $teams = (array)$fw->get('POST.teams');

        user::command_update($fw, $handle, $last_name, $first_name, $email, $role, $teams);

        base::return($fw);
    }

    function remove(\Base $fw): void
    {
        if (!web::logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        user::command_remove($fw, $handle);

        base::return($fw);
    }

    function fetch_assign_team(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        $user = user::get_by_handle($fw->db, $handle);
        if (empty($user)) {
            base::fetch_no_permission();
            return;
        }

        if ((int)$user[user::active] !== user::active_on) {
            base::fetch_no_permission();
            return;
        }

        $fw->set(user::table, $user);

        $user_id = (int)$user[user::id];

        team::set_list_by_user($fw, $user_id);

        session::new_csrf($fw);
        base::render('account/fetch/assign_team.htm');
    }

    function assign_team(\Base $fw): void
    {
        if (!web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, user::table, service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $teams = (array)$fw->get('POST.teams');

        user::command_assign_team($fw, $handle, $teams);

        base::return($fw);
    }

    function search(\Base $fw): void
    {
        if (!web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!web::set_user($fw))
            return;

        if (!$fw->exists('GET.user')) {
            base::redirect($fw);
            return;
        }

        $search_query = $fw->get('GET.user');

        user::set_search($fw, $search_query);

        base::render('account/search.htm');
    }

    function fetch_register(\Base $fw): void
    {
        if (!web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::fetch_no_permission();
            return;
        }

        $user = user::get_by_handle($fw->db, $handle);
        if (empty($user)) {
            base::fetch_no_permission();
            return;
        }

        if ((int)$user[user::active] !== user::active_on) {
            base::fetch_no_permission();
            return;
        }

        $fw->set(user::table, $user);

        $user_id = (int)$user[user::id];

        team::set_list_only_user($fw, $user_id);
        plan::set_list_only_team_list($fw, $fw->get(team::list));

        session::new_csrf($fw);
        base::render('account/fetch/register.htm');
    }

    function register(\Base $fw): void
    {
        if (!web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'register', service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', user::handle_length);
        if ((strlen($handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $plan_handle = base::trim_fw($fw, 'POST.plan', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        user::command_register($fw, $handle, $plan_handle);

        base::return($fw);
    }
}
