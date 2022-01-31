<?php

declare(strict_types=1);

namespace plan;

use account\user;

use core\plan;
use core\plan_team;
use core\team;

use frame\app;
use frame\base;
use frame\session;
use frame\time;

class web extends app
{
    static function redirect_plan(\Base $fw, string $plan_handle): void
    {
        $fw->reroute('/dp/' . $plan_handle);
    }

    static function redirect_plan_week(\Base $fw, string $plan_handle): void
    {
        $fw->reroute('/dp/' . $plan_handle . '#week');
    }

    static function redirect_week(\Base $fw, string $plan_handle, string $week_handle): void
    {
        $fw->reroute('/dp/' . $plan_handle . '/w/' . $week_handle . '#week');
    }

    // ---

    function fetch_add(\Base $fw): void
    {
        if (!\account\service::logged_owner($fw)) {
            base::fetch_no_permission();
            return;
        }

        session::new_csrf($fw);
        base::render('plan/fetch/add.htm');
    }

    function add(\Base $fw): void
    {
        if (!\account\web::logged_owner($fw))
            return;

        if (!session::check_csrf_redirect($fw, plan::table, \account\service::get_handle($fw)))
            return;

        if (!$fw->exists('POST.name') || !$fw->exists('POST.handle')) {
            base::redirect($fw);
            return;
        }

        $name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        $handle = base::trim_fw($fw, 'POST.handle', plan::max_handle_length);
        if ((strlen($name) === 0) || (strlen($handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        if (!base::check_handle($handle)) {
            base::redirect($fw);
            return;
        }

        $description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $link = base::trim_fw($fw, 'POST.link', 'max_link_length');
        $icon = base::trim_fw($fw, 'POST.icon', 'max_icon_length');

        plan::command_add($fw, $handle, $name, $description, $link, $icon);

        base::return($fw);
    }

    function detail(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if (strlen($handle) < (int)$fw->get(plan::min_handle_length)) {
            base::redirect($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle);
        if (empty($plan)) {
            base::redirect($fw);
            return;
        }

        if ((int)$plan[plan::active] !== plan::active_on) {
            base::redirect($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];

        if (\account\service::student($fw) && !plan::visible($fw, $plan_id)) {
            base::redirect($fw);
            return;
        }

        plan::format($fw, $plan);

        $fw->set(plan::table, $plan);

        $team_list = plan_team::get_team_list($fw, $plan_id);
        $fw->set(team::list, base::filter_selected($team_list));

        $plan_time = $plan[plan::time];
        $now = time::get($fw, $plan_time);

        $today = time::to_db_date_time($now);
        time::set_week_day($fw, $today);

        if (week::set($fw, $plan_id, $now)) {
            $week_id = (int)$fw->get(week::table . '.' . week::id);

            frame::set_list($fw, $plan_id, $week_id, \account\service::get_id($fw));
        }

        if ($fw->exists('SESSION.show_current')) {
            $fw->set('show_current', $fw->get('SESSION.show_current'));
            $fw->clear('SESSION.show_current');
        }

        base::render('plan/detail.htm');
    }

    function fetch_assign_team(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if (strlen($handle) < (int)$fw->get(plan::min_handle_length)) {
            base::fetch_no_permission();
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle, plan::id);
        if (empty($plan)) {
            base::fetch_no_permission();
            return;
        }

        $plan_id = (int)$plan[plan::id];

        plan_team::set_team_list($fw, $plan_id);

        session::new_csrf($fw);
        base::render('plan/fetch/assign_team.htm');
    }

    function assign_team(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'assign_team', \account\service::get_handle($fw)))
            return;

        if (!$fw->exists('PARAMS.handle')) {
            base::redirect($fw);
            return;
        }

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle, plan::id);
        if (empty($plan)) {
            base::redirect($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];
        $teams = (array)$fw->get('POST.teams');

        plan_team::assign($fw, $plan_id, $teams);

        base::return($fw);
    }

    function my_plan(\Base $fw): void
    {
        if (!\account\web::logged($fw))
            return;

        $plan_list = (array)$fw->get(plan::list);

        if (sizeof($plan_list) === 1) {
            $plan = array_values($plan_list)[0];
            web::redirect_plan($fw, $plan[plan::handle]);
            return;
        }

        $fw->reroute('/#plans');
    }

    function reports(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        $user_id = \account\service::get_id($fw);

        $report_list = array();

        $plan_list = $fw->get(plan::list);
        foreach ($plan_list as $plan) {
            $plan_id = (int)$plan[plan::id];

            $plan_time = $plan[plan::time];
            $now = time::get($fw, $plan_time);

            week::update_time($now);

            $current_time = time::to_db_date_time($now);

            $week_list = week::get_list($fw->db, $plan_id);
            foreach ($week_list as $week) {
                $week_id = (int)$week[week::id];

                $day_list = array();

                $period_list = period::get_list_by_week_before_DESC($fw->db, $plan_id, $week_id, $current_time);
                foreach ($period_list as $period) {
                    $period_id = (int)$period[period::id];

                    $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
                    if (empty($book))
                        continue;

                    if ((int)$book[book::present] !== book::present_on)
                        continue;

                    $book_subject_id = (int)$book[book::subject];
                    $book[book::subject] = \core\service::get_subject($fw, $book_subject_id);

                    $book_lab_id = (int)$book[book::lab];
                    $book[book::lab] = \core\service::get_lab($fw, $book_lab_id);

                    if ($book[book::present_time] !== '') {
                        if ($book[book::present_time] === null)
                            $book[book::present_time] = ''; // db can be NULL or 0000-00-00 00:00:00
                        else
                            $book[book::present_time] = time::to_time($fw, $book[book::present_time]);
                    }

                    period::format($fw, $period);

                    $week_day = time::get_week_day($period[period::start]);

                    if (!isset($day_list[$week_day])) {
                        $day = array();

                        $day[week::day] = $week_day;
                        $day[period::start_date] = $period[period::start_date];
                        $day[book::report_list] = array();

                        $same_week = time::same_week($fw, $now, time::get($fw, $period[period::start]));
                        $day[week::archive] = !$same_week;

                        $day_list[$week_day] = $day;
                    }

                    $report = array();
                    $report[period::table] = $period;
                    $report[book::table] = $book;

                    $day_list[$week_day][book::report_list][] = $report;
                }

                if (empty($day_list))
                    continue;

                if (!isset($plan[week::day_list]))
                    $plan[week::day_list] = $day_list;
                else
                    $plan[week::day_list] = array_merge($day_list, $plan[week::day_list]);

                $report_list[$plan_id] = $plan;
            }
        }

        $fw->set(book::report_list, $report_list);

        base::render('plan/reports.htm');
    }

    function fetch_import_periods(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if (strlen($handle) < (int)$fw->get(plan::min_handle_length)) {
            base::fetch_no_permission();
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle, plan::id . ',' . plan::time);
        if (empty($plan)) {
            base::fetch_no_permission();
            return;
        }

        service::set_current($fw, $plan);

        week::set_list($fw, $plan);

        // get week list to import and options?

        session::new_csrf($fw);
        base::render('plan/fetch/import_periods.htm');
    }

    function import_periods(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'import_periods', \account\service::get_handle($fw)))
            return;

        if (!$fw->exists('POST.week')) {
            base::redirect($fw);
            return;
        }

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $week_handle = base::trim_fw($fw, 'POST.week', base::max_handle_length);
        if ((strlen($week_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        week::command_import_periods($fw, $plan_handle, $week_handle);

        base::return($fw);
    }

    function fetch_add_frame(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin($fw))
            return;

        session::new_csrf($fw);
        base::render('plan/fetch/add_frame.htm');
    }

    function add_frame(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'add_frame', \account\service::get_handle($fw)))
            return;

        if (!$fw->exists('POST.name') || !$fw->exists('POST.start') || !$fw->exists('POST.end')) {
            base::redirect($fw);
            return;
        }

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        $start = time::to_db_time_short(strtotime($fw->get('POST.start')));
        $end = time::to_db_time_short(strtotime($fw->get('POST.end')));

        $days = array();
        $days[time::mo] = $fw->exists('POST.mo');
        $days[time::tu] = $fw->exists('POST.tu');
        $days[time::we] = $fw->exists('POST.we');
        $days[time::th] = $fw->exists('POST.th');
        $days[time::fr] = $fw->exists('POST.fr');

        $week = '';
        if ($fw->exists('POST.week'))
            $week = $fw->get('POST.week');

        frame::command_add($fw, $plan_handle, $name, $start, $end, $days, $week);

        base::return($fw);
    }

    function fetch_edit_frame(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle, plan::id);
        if (empty($plan)) {
            base::fetch_empty($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];

        $frame = frame::get_by_handle($fw->db, $plan_id, $fw->get('PARAMS.frame'));
        if (empty($frame)) {
            base::fetch_empty($fw);
            return;
        }

        $frame['start_time_en'] = date('m/d/Y h:i A', strtotime($frame[frame::start]));
        $frame['end_time_en'] = date('m/d/Y h:i A', strtotime($frame[frame::end]));
        $fw->set(frame::table, $frame);

        session::new_csrf($fw);
        base::render('plan/fetch/edit_frame.htm');
    }

    function edit_frame(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'edit_frame', \account\service::get_handle($fw)))
            return;

        if (!$fw->exists('POST.name') || !$fw->exists('POST.start') || !$fw->exists('POST.end')) {
            base::redirect($fw);
            return;
        }

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $frame_handle = base::trim_fw($fw, 'POST.frame', base::max_handle_length);
        if ((strlen($frame_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        $start = time::to_db_time_short(strtotime($fw->get('POST.start')));
        $end = time::to_db_time_short(strtotime($fw->get('POST.end')));

        $days = array();
        $days[time::mo] = $fw->exists('POST.mo');
        $days[time::tu] = $fw->exists('POST.tu');
        $days[time::we] = $fw->exists('POST.we');
        $days[time::th] = $fw->exists('POST.th');
        $days[time::fr] = $fw->exists('POST.fr');

        frame::command_update($fw, $plan_handle, $frame_handle, $name, $start, $end, $days);

        base::return($fw);
    }

    function remove_frame(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $frame_handle = base::trim_fw($fw, 'PARAMS.frame', base::max_handle_length);
        if ((strlen($frame_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        frame::command_remove($fw, $plan_handle, $frame_handle);

        base::return($fw);
    }

    function fetch_book(\Base $fw): void
    {
        if (!\account\web::fetch_logged($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if (strlen($period_handle) === 0) {
            base::fetch_empty($fw);
            return;
        }

        $user_id = \account\service::get_id($fw);

        if ($fw->exists('PARAMS.user') && !\account\service::student($fw)) {
            $user = user::get_by_handle($fw->db, $fw->get('PARAMS.user'), user::id . ',' . user::active);
            if (!empty($user) && ((int)$user[user::active] === user::active_on))
                $user_id = (int)$user[user::id];
        }

        user::set($fw, $user_id);

        $result = book::set($fw, $user_id, $plan_handle, $period_handle);

        if ($result === book::result_failed) {
            base::fetch_no_content($fw);
            return;
        }

        $fw->set('book_result', $result);

        if ($result === book::result_book_present)
            session::new_csrf($fw);

        base::render('plan/fetch/book.htm');
    }

    function book(\Base $fw): void
    {
        if (!\account\web::logged($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if ((strlen($period_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $lab_handle = base::trim_fw($fw, 'PARAMS.lab', base::max_handle_length);
        if ((strlen($lab_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $subject_handle = base::trim_fw($fw, 'PARAMS.subject', base::max_handle_length);
        if ((strlen($subject_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $user_id = \account\service::get_id($fw);

        if ($fw->exists('PARAMS.user') && !\account\service::student($fw))
            $user_id = user::get_by_handle($fw->db, $fw->get('PARAMS.user'), user::id);

        book::command_book($fw, $user_id, $plan_handle, $period_handle, $lab_handle, $subject_handle);

        base::return($fw);
    }

    function period(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if (strlen($period_handle) === 0) {
            base::redirect($fw);
            return;
        }

        if (!period::set($fw, $plan_handle, $period_handle)) {
            base::redirect($fw);
            return;
        }

        base::render('plan/period.htm');
    }

    function fetch_book_period(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator_coach($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if (strlen($period_handle) === 0) {
            base::fetch_empty($fw);
            return;
        }

        if (!period::set_book($fw, $plan_handle, $period_handle)) {
            base::fetch_empty($fw);
            return;
        }

        session::new_csrf($fw);
        base::render('plan/fetch/book_period.htm');
    }

    function book_period(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'book_period', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'POST.period', base::max_handle_length);
        if ((strlen($period_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $lab_handle = base::trim_fw($fw, 'POST.lab', base::max_handle_length);
        if ((strlen($lab_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $subject_handle = base::trim_fw($fw, 'POST.subject', base::max_handle_length);
        if ((strlen($subject_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $teams = (array)$fw->get('POST.teams');
        $only_students = $fw->exists('POST.only_students');

        $students = (array)$fw->get('POST.students');
        $coaches = (array)$fw->get('POST.coaches');

        $blocked = $fw->exists('POST.blocked');
        $present = $fw->exists('POST.present');

        period::command_book(
            $fw,
            $plan_handle,
            $period_handle,
            $lab_handle,
            $subject_handle,
            $teams,
            $only_students,
            $students,
            $coaches,
            $blocked,
            $present
        );

        base::return($fw);
    }

    function fetch_book_period_user(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator_coach($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if (strlen($period_handle) === 0) {
            base::fetch_empty($fw);
            return;
        }

        if (!period::set_book($fw, $plan_handle, $period_handle)) {
            base::fetch_empty($fw);
            return;
        }

        $user_handle = base::trim_fw($fw, 'PARAMS.user', user::handle_length);
        if (strlen($user_handle) === 0) {
            base::fetch_empty($fw);
            return;
        }

        if (!user::set_by_handle($fw, $user_handle)) {
            base::fetch_empty($fw);
            return;
        }

        session::new_csrf($fw);
        base::render('plan/fetch/book_period_user.htm');
    }

    function book_period_user(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'book_period_user', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'POST.period', base::max_handle_length);
        if ((strlen($period_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $lab_handle = base::trim_fw($fw, 'POST.lab', base::max_handle_length);
        if ((strlen($lab_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $subject_handle = base::trim_fw($fw, 'POST.subject', base::max_handle_length);
        if ((strlen($subject_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $user_handle = base::trim_fw($fw, 'POST.user', user::handle_length);
        if (strlen($user_handle) === 0) {
            base::redirect($fw);
            return;
        }

        $blocked = $fw->exists('POST.blocked');
        $present = $fw->exists('POST.present');

        period::command_rebook(
            $fw,
            $plan_handle,
            $user_handle,
            $period_handle,
            $lab_handle,
            $subject_handle,
            $blocked,
            $present
        );

        base::return($fw);
    }

    function fetch_book_period_unset(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator_coach($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if (strlen($period_handle) === 0) {
            base::fetch_empty($fw);
            return;
        }

        if (!period::set_book($fw, $plan_handle, $period_handle)) {
            base::fetch_empty($fw);
            return;
        }

        $plan_id = (int)$fw->get(plan::table . '.' . plan::id);
        $period_id = (int)$fw->get(period::table . '.' . period::id);

        period::set_unset($fw, $plan_id, $period_id);

        session::new_csrf($fw);
        base::render('plan/fetch/book_period_unset.htm');
    }

    function book_period_users(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'book_period_users', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'POST.period', base::max_handle_length);
        if ((strlen($period_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $lab_handle = base::trim_fw($fw, 'POST.lab', base::max_handle_length);
        if ((strlen($lab_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $subject_handle = base::trim_fw($fw, 'POST.subject', base::max_handle_length);
        if ((strlen($subject_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $students = (array)$fw->get('POST.students');
        $coaches = (array)$fw->get('POST.coaches');

        $blocked = $fw->exists('POST.blocked');
        $present = $fw->exists('POST.present');

        period::command_book_users(
            $fw,
            $plan_handle,
            $period_handle,
            $lab_handle,
            $subject_handle,
            $students,
            $coaches,
            $blocked,
            $present
        );

        base::return($fw);
    }

    function fetch_commit_period(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator_coach($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if (strlen($period_handle) === 0) {
            base::fetch_empty($fw);
            return;
        }

        if (!period::set_commit($fw, $plan_handle, $period_handle)) {
            base::fetch_empty($fw);
            return;
        }

        session::new_csrf($fw);
        base::render('plan/fetch/commit_period.htm');
    }

    function commit_period(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'commit_period', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'POST.period', base::max_handle_length);
        if ((strlen($period_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $teams = (array)$fw->get('POST.teams');
        $subjects = (array)$fw->get('POST.subjects');
        $labs = (array)$fw->get('POST.labs');

        period::command_commit(
            $fw,
            $plan_handle,
            $period_handle,
            $teams,
            $subjects,
            $labs
        );

        base::return($fw);
    }

    function remove_commit(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if ((strlen($period_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $team_handle = base::trim_fw($fw, 'PARAMS.team', base::max_handle_length);
        if ((strlen($team_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $subject_handle = base::trim_fw($fw, 'PARAMS.subject', base::max_handle_length);
        if ((strlen($subject_handle)) === 0) {
            base::redirect($fw);
            return;
        }

        $lab_handle = base::trim_fw($fw, 'PARAMS.lab', base::max_handle_length);

        period::command_remove_commit(
            $fw,
            $plan_handle,
            $period_handle,
            $team_handle,
            $subject_handle,
            $lab_handle
        );

        base::return($fw);
    }

    function fetch_edit_plan(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan)) {
            base::fetch_empty($fw);
            return;
        }

        if ((int)$plan[plan::active] !== plan::active_on) {
            base::fetch_empty($fw);
            return;
        }

        plan::format($fw, $plan);

        $fw->set(plan::table, $plan);

        session::new_csrf($fw);
        base::render('plan/fetch/edit.htm');
    }

    function edit_plan(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'edit_plan', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        // TODO: move to plan_data ?

        if (!$fw->exists('POST.name')) {
            base::redirect($fw);
            return;
        }

        $name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        if (strlen($name) === 0) {
            base::redirect($fw);
            return;
        }

        $description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $link = base::trim_fw($fw, 'POST.link', 'max_link_length');
        $icon = base::trim_fw($fw, 'POST.icon', 'max_icon_length');

        $register = (int)$fw->get('POST.register');
        if ($register < 0)
            $register = 0;
        else if ($register > 59)
            $register = 59;

        $exchange = (int)$fw->get('POST.exchange');
        if ($exchange < 0)
            $exchange = 0;
        else if ($exchange > 59)
            $exchange = 59;

        plan::command_update($fw, $plan_handle, $name, $description, $link, $icon, $register, $exchange);

        base::return($fw);
    }

    function fetch_time_machine(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner($fw))
            return;

        if ((int)$fw->get('dp_time_machine') !== 1) {
            base::fetch_no_permission();
            return;
        }

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::time . ',' . plan::active);
        if (empty($plan)) {
            base::fetch_empty($fw);
            return;
        }

        if ((int)$plan[plan::active] !== plan::active_on) {
            base::fetch_empty($fw);
            return;
        }

        $time = '';
        if ($plan[plan::time] === null)
            $time = $fw->get('date_time');
        else
            $time = time::to_ui_date_time($plan[plan::time]);

        $fw->set('plan.time', $plan[plan::time]);
        $fw->set('plan.date_time', $time);

        session::new_csrf($fw);
        base::render('plan/fetch/time_machine.htm');
    }

    function time_machine(\Base $fw): void
    {
        if (!\account\web::logged_owner($fw))
            return;

        if ((int)$fw->get('dp_time_machine') !== 1) {
            base::return($fw);
            return;
        }

        if (!session::check_csrf_redirect($fw, 'time_machine', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        if (!$fw->exists('POST.time')) {
            base::redirect($fw);
            return;
        }

        $time = $fw->get('POST.time');

        plan::command_time_machine($fw, $plan_handle, $time);

        base::return($fw);
    }

    function reset_time_machine(\Base $fw): void
    {
        if (!\account\web::logged_owner($fw))
            return;

        if ((int)$fw->get('dp_time_machine') !== 1) {
            base::return($fw);
            return;
        }

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        plan::command_reset_time_machine($fw, $plan_handle);

        base::return($fw);
    }

    function remove_plan(\Base $fw): void
    {
        if (!\account\web::logged_owner($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        plan::command_remove($fw, $plan_handle);

        base::redirect($fw);
    }

    function fetch_edit_week(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan)) {
            base::fetch_empty($fw);
            return;
        }

        if ((int)$plan[plan::active] !== plan::active_on) {
            base::fetch_empty($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];

        $week_handle = base::trim_fw($fw, 'PARAMS.week', base::max_handle_length);
        if ((strlen($week_handle) === 0)) {
            base::fetch_empty($fw);
            return;
        }

        $week = week::get_by_handle($fw->db, $plan_id, $week_handle);
        if (empty($week)) {
            base::fetch_empty($fw);
            return;
        }

        $fw->set(week::table, $week);

        session::new_csrf($fw);
        base::render('plan/fetch/edit_week.htm');
    }

    function edit_week(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'edit_week', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $week_handle = base::trim_fw($fw, 'PARAMS.week', base::max_handle_length);
        if ((strlen($week_handle) === 0)) {
            base::fetch_empty($fw);
            return;
        }

        // TODO: move to week_data ?

        if (!$fw->exists('POST.name')) {
            base::redirect($fw);
            return;
        }

        $name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        if (strlen($name) === 0) {
            base::redirect($fw);
            return;
        }

        $description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $link = base::trim_fw($fw, 'POST.link', 'max_link_length');
        $icon = base::trim_fw($fw, 'POST.icon', 'max_icon_length');

        week::command_update($fw, $plan_handle, $week_handle, $name, $description, $link, $icon);

        base::return($fw);
    }

    function delete_week(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $week_handle = base::trim_fw($fw, 'PARAMS.week', base::max_handle_length);
        if ((strlen($week_handle) === 0)) {
            base::redirect($fw);
            return;
        }

        week::command_delete($fw, $plan_handle, $week_handle);

        web::redirect_plan($fw, $plan_handle);
    }

    function fetch_edit_period(\Base $fw): void
    {
        if (!\account\web::fetch_logged_owner_admin_moderator($fw))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::fetch_empty($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan)) {
            base::fetch_empty($fw);
            return;
        }

        if ((int)$plan[plan::active] !== plan::active_on) {
            base::fetch_empty($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if ((strlen($period_handle) === 0)) {
            base::fetch_empty($fw);
            return;
        }

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle);
        if (empty($period)) {
            base::fetch_empty($fw);
            return;
        }

        period::format($fw, $period);

        $fw->set(period::table, $period);

        session::new_csrf($fw);
        base::render('plan/fetch/edit_period.htm');
    }

    function edit_period(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'edit_period', \account\service::get_handle($fw)))
            return;

        $plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($plan_handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if ((strlen($period_handle) === 0)) {
            base::redirect($fw);
            return;
        }

        // TODO: move to period_data ?

        if (!$fw->exists('POST.name')) {
            base::redirect($fw);
            return;
        }

        $name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        if (strlen($name) === 0) {
            base::redirect($fw);
            return;
        }

        $description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $link = base::trim_fw($fw, 'POST.link', 'max_link_length');

        $blocked = $fw->exists('POST.blocked');
        $register = $fw->exists('POST.register');

        $exchange = (int)$fw->get('POST.exchange');
        if ($exchange < 0)
            $exchange = 0;
        else if ($exchange > 59)
            $exchange = 59;

        period::command_update(
            $fw,
            $plan_handle,
            $period_handle,
            $name,
            $description,
            $link,
            $blocked,
            $register,
            $exchange
        );

        base::return($fw);
    }

    function present(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_user($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_present($fw, $book_data);

        base::return($fw);
    }

    function excused(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_user($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_exclused($fw, $book_data);

        base::return($fw);
    }

    function free(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_user($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_free($fw, $book_data);

        base::return($fw);
    }

    function blocked(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_user($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_blocked($fw, $book_data);

        base::return($fw);
    }

    function remove_book(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_user($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_remove($fw, $book_data);

        base::return($fw);
    }

    function lab_present(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_lab($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_lab_present($fw, $book_data);

        base::return($fw);
    }

    function lab_blocked(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_lab($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_lab_blocked($fw, $book_data);

        base::return($fw);
    }

    function lab_clear(\Base $fw): void
    {
        if (!\account\web::logged_owner_admin_moderator_coach($fw))
            return;

        $book_data = book_data::create_lab($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        book::command_lab_clear($fw, $book_data);

        base::return($fw);
    }

    function participation(\Base $fw): void
    {
        if (!\account\web::logged($fw))
            return;

        $participation = base::trim_fw($fw, 'PARAMS.participation', base::max_handle_length);
        if ((strlen($participation) === 0)) {
            base::redirect($fw);
            return;
        }

        $result_array = book::command_participation($fw, $participation);
        if (empty($result_array)) {
            base::return($fw);
            return;
        }

        $plan_handle = $result_array[plan::table];
        $period_handle = $result_array[period::table];

        $fw->set('SESSION.show_current', $period_handle);

        web::redirect_plan_week($fw, $plan_handle);
    }

    function update_book(\Base $fw): void
    {
        if (!\account\web::logged($fw))
            return;

        $book_data = book_data::create_report($fw);
        if ($book_data === null) {
            base::redirect($fw);
            return;
        }

        $book_data->user_id = \account\service::get_id($fw);

        if ($fw->exists('PARAMS.user') && !\account\service::student($fw)) {
            $user = user::get_by_handle($fw->db, $fw->get('PARAMS.user'), user::id . ',' . user::active);
            if (!empty($user) && ((int)$user[user::active] === user::active_on))
                $book_data->user_id = (int)$user[user::id];
        }

        book::command_update_book($fw, $book_data);

        base::return($fw);
    }

    function fetch_live(\Base $fw): void
    {
        if (!\account\web::fetch_logged($fw))
            return;

        if (!$fw->exists(period::current)) {
            base::fetch_no_content();
            return;
        }

        $current = $fw->get(period::current);

        $plan_handle = $current[plan::table][plan::handle];
        $period_handle = $current[period::handle];

        $fw->set('PARAMS.handle', $plan_handle);
        $fw->set('PARAMS.period', $period_handle);

        $this->fetch_book($fw);
    }

    function fetch_archive(\Base $fw): void
    {
        if (!\account\web::fetch_logged($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if (strlen($handle) < (int)$fw->get(plan::min_handle_length)) {
            base::fetch_no_permission();
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle, plan::id . ',' . plan::time);
        if (empty($plan)) {
            base::fetch_no_permission();
            return;
        }

        service::set_current($fw, $plan);

        week::set_list($fw, $plan);

        session::new_csrf($fw);
        base::render('plan/fetch/archive.htm');
    }

    function show_week(\Base $fw): void
    {
        if (!\account\web::logged($fw))
            return;

        if (!session::check_csrf_redirect($fw, 'show_week', \account\service::get_handle($fw)))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($handle) < (int)$fw->get(plan::min_handle_length))) {
            base::redirect($fw);
            return;
        }

        $week_handle = base::trim_fw($fw, 'POST.week', base::max_handle_length);
        if ((strlen($week_handle) === 0)) {
            base::redirect($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle, plan::id . ',' . plan::time);
        if (empty($plan)) {
            base::redirect($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];

        $week = week::get_by_handle($fw->db, $plan_id, $week_handle, week::id);
        if (empty($week)) {
            base::redirect($fw);
            return;
        }

        service::set_current($fw, $plan);

        if (!empty($plan[week::table])) {
            if ((int)$week[week::id] === (int)$plan[week::table][week::id]) {
                web::redirect_plan_week($fw, $handle);
                return;
            }
        }

        web::redirect_week($fw, $handle, $week_handle);
    }

    function week(\Base $fw): void
    {
        if (!\account\web::logged_user($fw))
            return;

        $handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if (strlen($handle) < (int)$fw->get(plan::min_handle_length)) {
            base::redirect($fw);
            return;
        }

        $week_handle = base::trim_fw($fw, 'PARAMS.week', base::max_handle_length);
        if ((strlen($week_handle) === 0)) {
            base::redirect($fw);
            return;
        }

        $plan = plan::get_by_handle($fw->db, $handle);
        if (empty($plan)) {
            base::redirect($fw);
            return;
        }

        if ((int)$plan[plan::active] !== plan::active_on) {
            base::redirect($fw);
            return;
        }

        $plan_id = (int)$plan[plan::id];

        if (\account\service::student($fw) && !plan::visible($fw, $plan_id)) {
            base::redirect($fw);
            return;
        }

        plan::format($fw, $plan);

        $fw->set(plan::table, $plan);

        $team_list = plan_team::get_team_list($fw, $plan_id);
        $fw->set(team::list, base::filter_selected($team_list));

        $plan_time = $plan[plan::time];
        $now = time::get($fw, $plan_time);

        $today = time::to_db_date_time($now);
        time::set_week_day($fw, $today);

        $week = week::get_by_handle($fw->db, $plan_id, $week_handle);
        if (empty($week)) {
            base::redirect($fw);
            return;
        }

        service::set_current($fw, $plan);

        if (!empty($plan[week::table])) {
            if ((int)$week[week::id] === (int)$plan[week::table][week::id]) {
                web::redirect_plan_week($fw, $handle);
                return;
            }
        }

        week::format($fw, $week);

        $fw->set(week::table, $week);

        $week_id = (int)$week[week::id];

        frame::set_list($fw, $plan_id, $week_id, \account\service::get_id($fw));

        base::render('plan/detail.htm');
    }
}
