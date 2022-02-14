<?php

declare(strict_types=1);

namespace plan;

use account\user;

use core\lab;
use core\plan;
use core\subject;
use core\team_user;
use core\team;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\session;
use frame\time;

abstract class period
{
    const latest = 0;
    const table = 'period';
    const list = 'period_list';
    const previous = 'previous';
    const current = 'current';
    const next = 'next';
    const commit_list = 'commit_list';

    const start_date = 'start_date';
    const start_time = 'start_time';
    const end_date = 'end_date';
    const end_time = 'end_time';

    const id = db::id;
    const handle = db::handle;
    const name = 'name';
    const description = 'description';
    const link = 'link';
    const frame = 'frame';
    const week = 'week';
    const start = 'start';
    const end = 'end';
    const blocked = 'blocked';
    const exchange = 'exchange';
    const register = 'register';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    // subject
    const subject_none = 0;

    // register
    const register_off = 0;
    const register_on  = 1;

    // blocked
    const blocked_off  = 0;
    const blocked_on   = 1;

    const all = period::id . ','
        . period::handle  . ','
        . period::name . ','
        . period::description . ','
        . period::link . ','
        . period::frame . ','
        . period::week . ','
        . period::start . ','
        . period::end . ','
        . period::blocked . ','
        . period::register . ','
        . period::exchange . ','
        . period::version . ','
        . period::created . ','
        . period::updated;

    static function create(\Base $fw, string $prefix): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $prefix . period::table . '` (
                `' . period::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . period::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . period::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . period::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . period::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . period::frame . '` int(11) NOT NULL,
                `' . period::week . '` int(11) NOT NULL,
                `' . period::start . '` timestamp NULL DEFAULT NULL,
                `' . period::end . '` timestamp NULL DEFAULT NULL,
                `' . period::blocked . '` int(11) NOT NULL DEFAULT 0,
                `' . period::register . '` int(11) NOT NULL DEFAULT 0,
                `' . period::exchange . '` time NOT NULL,
                `' . period::version . '` int(11) NOT NULL DEFAULT ' . period::latest . ',
                `' . period::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . period::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . period::id . '`) USING BTREE,
                UNIQUE KEY `' . period::handle . '` (`' . period::handle . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function delete(\Base $fw, string $prefix): void
    {
        $sql = 'DROP TABLE `' . $prefix . period::table . '`;';
        $fw->db->exec($sql);
    }

    // ---

    static function set(\Base $fw, string $plan_handle, string $period_handle): bool
    {
        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::name . ',' . plan::time . ',' . plan::active);
        if (empty($plan))
            return false;

        if ((int)$plan[plan::active] !== plan::active_on)
            return false;

        $plan_id = (int)$plan[plan::id];

        $fw->set(plan::table . '.' . plan::name, $plan[plan::name]);

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle);
        if (empty($period))
            return false;

        $period_id = (int)$period[period::id];

        $week_id = (int)$period[period::week];

        $week = week::get($fw->db, $plan_id, $week_id, week::handle . ',' . week::icon . ',' . week::name);
        if (empty($week))
            return false;

        $fw->set(week::table, $week);

        period::format($fw, $period);

        $fw->set(period::table, $period);

        time::set_week_day($fw, $period[period::start]);

        $plan_time = $plan[plan::time];
        $now = time::get($fw, $plan_time);

        week::update_time($now);

        $same_week = time::same_week($fw, $now, time::get($fw, $period[period::start]));
        $fw->set(week::archive, !$same_week);

        // commit_list

        $commit_list = period_team::get_list_by_period($fw->db, $plan_id, $period_id);
        if (!empty($commit_list)) {
            $filtered_commit_list = array();

            foreach ($commit_list as $c_key => $c_value) {
                $team_id = (int)$c_value[period_team::team];

                $team = \core\service::get_team($fw, $team_id);
                if (empty($team))
                    continue;

                if ((int)$team[team::active] !== team::active_on)
                    continue;

                $subject_id = (int)$c_value[period_team::subject];

                $subject = \core\service::get_subject($fw, $subject_id);
                if (empty($subject))
                    continue;

                $lab_id = (int)$c_value[period_team::lab];

                $lab = \core\service::get_lab($fw, $lab_id);
                if (empty($lab))
                    continue;

                $filtered_commit_list[$c_key][team::table] = $team;
                $filtered_commit_list[$c_key][subject::table] = $subject;
                $filtered_commit_list[$c_key][lab::table] = $lab;
            }

            if (!empty($filtered_commit_list)) {
                if (session::get_sort($fw)) {
                    usort($filtered_commit_list, function ($a, $b) {
                        return $a[lab::table][lab::name] <=> $b[lab::table][lab::name];
                    });

                    usort($filtered_commit_list, function ($a, $b) {
                        return $a[subject::table][subject::name] <=> $b[subject::table][subject::name];
                    });

                    usort($filtered_commit_list, function ($a, $b) {
                        return $a[team::table][team::name] <=> $b[team::table][team::name];
                    });
                }
            }

            $fw->set(period::commit_list, $filtered_commit_list);
        } else {
            $fw->set(period::commit_list, array()); // empty
        }

        $book_user_list = array();

        $lab_list = array();

        $all_lab_list = lab::get_list_all($fw->db);
        foreach ($all_lab_list as $l_key => $l_value) {
            $lab_id = (int)$l_value[lab::id];

            $book_list = book::get_list_by_period_lab($fw->db, $plan_id, $period_id, $lab_id);
            if (empty($book_list))
                continue;

            $coaches = array();
            $students = array();

            $amount_present = 0;
            $amount_excused = 0;
            $amount_free = 0;

            foreach ($book_list as $b_key => $b_value) {
                $user_id = (int)$b_value[book::user];

                $user = \account\service::get_user($fw, $user_id);
                if (empty($user))
                    continue;

                $b_value[user::table] = $user;

                $subject_id = (int)$b_value[book::subject];
                $subject = \core\service::get_subject($fw, $subject_id);
                $b_value[subject::table] = $subject;

                $present = (int)$b_value[book::present];
                if ($present === book::present_on)
                    $amount_present++;
                else if ($present === book::present_excused)
                    $amount_excused++;
                else if ($present === book::present_free)
                    $amount_free++;

                if ($user[user::role] === user::role_student) {
                    $students[$b_key] = $b_value;
                } else {
                    $coaches[$b_key] = $b_value;
                }

                $book_user_list[] = $user_id;
            }

            if (session::get_sort($fw)) {
                usort($coaches, function ($a, $b) {
                    return $a[user::table][user::last_name] <=> $b[user::table][user::last_name];
                });

                usort($students, function ($a, $b) {
                    return $a[user::table][user::last_name] <=> $b[user::table][user::last_name];
                });
            }

            $l_value[user::amount] = sizeof($students);

            $book_list_sorted = array_merge($coaches, $students);
            $l_value[book::list] = $book_list_sorted;

            $l_value['amount_present'] = $amount_present;
            $l_value['amount_excused'] = $amount_excused;
            $l_value['amount_free'] = $amount_free;

            $l_value['coaches'] = $coaches;
            $l_value['students'] = $students;

            $lab_list[$l_key] = $l_value;
        }

        $fw->set(lab::list, $lab_list);

        $period_start = time::to_db_date_time_str($period[period::start]);
        $previous = period::get_previous($fw->db, $plan_id, $week_id, $period_start, $period_id);
        if (!empty($previous)) {
            $previous[time::week_day] = time::get_week_day($previous[period::start]);
            $fw->set(period::previous, $previous);
        }

        $period_end = time::to_db_date_time_str($period[period::end]);
        $next = period::get_next($fw->db, $plan_id, $week_id, $period_end, $period_id);
        if (!empty($next)) {
            $next[time::week_day] = time::get_week_day($next[period::start]);
            $fw->set(period::next, $next);
        }

        $all_user_list = \core\service::get_user_list($fw, $plan_id);

        $unset_user_count = 0;

        foreach ($all_user_list as $user_id)
            if (!in_array($user_id, $book_user_list)) {
                $unset_user_count++;
            }

        $fw->set('unset_user_count', $unset_user_count);

        return true;
    }

    static function format(\Base $fw, array &$period): void
    {
        $date_format = $fw->get('DICT.date_format');
        $time_format = $fw->get('DICT.time_format');

        $period_start = strtotime($period[period::start]);
        $period_end = strtotime($period[period::end]);

        $period[period::start_date] = date($date_format, $period_start);
        $period[period::start_time] = date($time_format, $period_start);
        $period[period::end_date] = date($date_format, $period_end);
        $period[period::end_time] = date($time_format, $period_end);

        $period[period::exchange] = (int)date('i', strtotime($period[period::exchange]));
    }

    static function set_book(\Base $fw, string $plan_handle, string $period_handle): bool
    {
        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return false;

        if ((int)$plan[plan::active] !== plan::active_on)
            return false;

        $plan_id = (int)$plan[plan::id];

        $fw->set(plan::table, $plan);

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::id . ',' . period::name . ',' . period::start);
        if (empty($period))
            return false;

        $fw->set(period::table, $period);

        time::set_week_day($fw, $period[period::start]);

        lab::set_list($fw);
        subject::set_list($fw);
        team::set_list($fw);
        user::set_student_list($fw);
        user::set_coach_list($fw);

        return true;
    }

    static function set_commit(\Base $fw, string $plan_handle, string $period_handle): bool
    {
        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return false;

        if ((int)$plan[plan::active] !== plan::active_on)
            return false;

        $plan_id = (int)$plan[plan::id];

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::name . ',' . period::start);
        if (empty($period))
            return false;

        $fw->set(period::table, $period);

        time::set_week_day($fw, $period[period::start]);

        team::set_list($fw);
        subject::set_list($fw);
        lab::set_list($fw);

        return true;
    }

    static function set_unset(\Base $fw, int $plan_id, int $period_id): void
    {
        $book_student_list = array();
        $book_coach_list = array();

        $book_list = book::get_list_by_period($fw->db, $plan_id, $period_id, book::user . ',' . book::excluded);
        foreach ($book_list as $book) {
            $book_user_id = (int)$book[book::user];
            $book_excluded = (int)$book[book::excluded] === book::excluded_on;

            if ($book_excluded)
                $book_coach_list[] = $book_user_id;
            else
                $book_student_list[] = $book_user_id;
        }

        $all_user_list = \core\service::get_user_list($fw, $plan_id);

        $student_list = array();
        $coach_list = array();

        foreach ($all_user_list as $user_id) {
            if (in_array($user_id, $book_student_list))
                continue;

            if (in_array($user_id, $book_coach_list))
                continue;

            $user = \account\service::get_user($fw, $user_id);
            if (empty($user))
                continue;

            if ((int)$user[user::role] === user::role_student)
                $student_list[] = $user;
            else
                $coach_list[] = $user;
        }

        if (session::get_sort($fw)) {
            usort($coach_list, function ($a, $b) {
                return $a[user::last_name] <=> $b[user::last_name];
            });

            usort($student_list, function ($a, $b) {
                return $a[user::last_name] <=> $b[user::last_name];
            });
        }

        $fw->set(user::student_list, $student_list);
        $fw->set(user::coach_list, $coach_list);
    }

    // --- query

    static function select(int $plan, string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? period::all : $fields) . ' FROM ' . db::plan_prefix($plan) . period::table . ' ';
    }

    static function get(SQL $sql, int $plan, int $id, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function new_handle(SQL $sql, int $plan): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(period::get_by_handle($sql, $plan, $handle)))
                return $handle;
        }

        return '';
    }

    static function get_by_handle(SQL $sql, int $plan, string $handle, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_current(SQL $sql, int $plan, int $week, string $time, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::week . '=? AND (' . period::start . '<=?) AND (?<=' . period::end . ') LIMIT 1';
        return base::first($sql->exec($get, array(
            1 => $week,
            2 => $time,
            3 => $time,
        )));
    }

    static function get_next(SQL $sql, int $plan, int $week, string $time, int $exclude_period, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::week . '=? AND (' . period::start . '>=?) AND ' . period::id . '!=? ORDER BY ' . period::start . ' LIMIT 1';
        return base::first($sql->exec($get, array(
            1 => $week,
            2 => $time,
            3 => $exclude_period,
        )));
    }

    static function get_previous(SQL $sql, int $plan, int $week, string $time, int $exclude_period, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::week . '=? AND (' . period::start . '<=?) AND ' . period::id . '!=? ORDER BY ' . period::start . ' DESC LIMIT 1';
        return base::first($sql->exec($get, array(
            1 => $week,
            2 => $time,
            3 => $exclude_period,
        )));
    }

    static function get_list_by_frame(SQL $sql, int $plan, int $frame, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::frame . '=? ORDER BY ' . period::start;
        return $sql->exec($get, $frame);
    }

    static function get_list_by_week(SQL $sql, int $plan, int $week, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::week . '=? ORDER BY ' . period::start;
        return $sql->exec($get, $week);
    }

    static function get_list_by_week_before_DESC(SQL $sql, int $plan, int $week, string $time, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::week . '=? AND ' . period::start . '<=? ORDER BY ' . period::start . ' DESC';
        return $sql->exec($get, array(
            1 => $week,
            2 => $time,
        ));
    }

    static function get_list_by_week_after_max(SQL $sql, int $plan, int $week, string $time, int $max, string $fields = ''): array
    {
        $get = period::select($plan, $fields) . 'WHERE ' . period::week . '=? AND ' . period::end . '>=? ORDER BY ' . period::start . ' LIMIT ?';
        return $sql->exec($get, array(
            1 => $week,
            2 => $time,
            3 => $max,
        ));
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_updated = 2;
    const event_deleted = 3;

    static function event_insert(\Base $fw, int $plan_id, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::period,
            $id,
            $command,
            $event,
            period::get($fw->db, $plan_id, $id, $fields),
            period::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        int $plan,
        string $handle,
        string $name,
        string $description,
        string $link,
        int $frame,
        int $week,
        string $start,
        string $end,
        bool $register,
        string $exchange,
        bool $blocked = false
    ): int {
        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . period::table . ' ('
            . period::handle . ','
            . period::name . ','
            . period::description . ','
            . period::link . ','
            . period::frame . ','
            . period::week . ','
            . period::start . ','
            . period::end . ','
            . period::blocked . ','
            . period::register . ','
            . period::exchange . ','
            . period::version . ') VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $name,
            3 => $description,
            4 => $link,
            5 => $frame,
            6 => $week,
            7 => $start,
            8 => $end,
            9 => $blocked,
            10 => $register,
            11 => $exchange,
            12 => period::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        period::event_insert($fw, $plan, $id, $command, period::event_created);

        return $id;
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $plan,
        int $period,
        string $name,
        string $description,
        string $link,
        int $blocked,
        int $register,
        string $exchange
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . period::table . ' SET '
            . period::name . '=?,'
            . period::description . '=?,'
            . period::link . '=?,'
            . period::blocked . '=?,'
            . period::register . '=?,'
            . period::exchange . '=? WHERE '
            . period::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $description,
            3 => $link,
            4 => $blocked,
            5 => $register,
            6 => $exchange,
            7 => $period,
        ));

        period::event_insert($fw, $plan, $period, $command, period::event_updated);
    }

    static function action_update_name_time(
        \Base $fw,
        int $command,
        int $plan,
        int $period,
        string $name,
        string $start,
        string $end
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . period::table . ' SET '
            . period::name . '=?,'
            . period::start . '=?,'
            . period::end . '=? WHERE '
            . period::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $start,
            3 => $end,
            4 => $period
        ));

        period::event_insert(
            $fw,
            $plan,
            $period,
            $command,
            period::event_updated,
            period::name . ',' . period::start . ',' . period::end . ',' . period::version
        );
    }

    static function action_delete(\Base $fw, int $command, int $plan, int $period): void
    {
        period::event_insert($fw, $plan, $period, $command, period::event_deleted);

        $delete = 'DELETE FROM ' . db::plan_prefix($plan) . period::table . ' WHERE ' . period::id . '=?';
        $fw->db->exec($delete, $period);
    }

    // --- command

    static function command_add_internal(
        \Base $fw,
        int $command,
        int $plan_id,
        string $name,
        int $frame_id,
        int $week_id,
        int $start_time,
        int $end_time,
        bool $register,
        int $exchange_time
    ): int {
        return period::command_add_internal_all(
            $fw,
            $command,
            $plan_id,
            $name,
            '',
            '',
            $frame_id,
            $week_id,
            $start_time,
            $end_time,
            $register,
            $exchange_time
        );
    }

    static function command_add_internal_all(
        \Base $fw,
        int $command,
        int $plan_id,
        string $name,
        string $description,
        string $link,
        int $frame_id,
        int $week_id,
        int $start_time,
        int $end_time,
        bool $register,
        int $exchange_time,
        bool $blocked = false
    ): int {
        $handle = period::new_handle($fw->db, $plan_id);

        $start = date('Y-m-d H:i', $start_time);
        $end = date('Y-m-d H:i', $end_time);

        $exchange = date("H:i:s", $exchange_time);

        return period::action_insert(
            $fw,
            $command,
            $plan_id,
            $handle,
            $name,
            $description,
            $link,
            $frame_id,
            $week_id,
            $start,
            $end,
            $register,
            $exchange,
            $blocked
        );
    }

    static function command_book(
        \Base $fw,
        string $plan_handle,
        string $period_handle,
        string $lab_handle,
        string $subject_handle,
        array $teams,
        bool $only_students,
        array $students,
        array $coaches,
        bool $blocked,
        bool $present
    ): void {
        $command = \cmd::begin($fw, \cmd::book_period);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== 1)
            return;

        $plan_id = (int)$plan[plan::id];

        // check period

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::id . ',' . period::week);
        if (empty($period))
            return;

        $period_id = (int)$period[period::id];

        $week_id = (int)$period[period::week];

        // check lab

        $lab = lab::get_by_handle($fw->db, $lab_handle, lab::id);
        if (empty($lab))
            return;

        $lab_id = (int)$lab[lab::id];

        // check subject

        $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id,);
        if (empty($subject))
            return;

        $subject_id = (int)$subject[subject::id];

        $booked = array(); // user list
        $booked_ids = array(); // user.id list

        if (!empty($teams)) {
            foreach ($teams as $t_value) {
                $team = team::get_by_handle($fw->db, $t_value, team::id);
                if (empty($team))
                    continue;

                $team_id = (int)$team[team::id];

                $team_user_list = team_user::get_list_by_team($fw->db, $team_id, team_user::user);
                if (empty($team_user_list))
                    continue;

                foreach ($team_user_list as $tu_value) {
                    $user_id = (int)$tu_value[team_user::user];

                    $user = \account\service::get_user($fw, $user_id);
                    if (empty($user))
                        continue;

                    if ((int)$user[user::active] !== user::active_on)
                        continue;

                    if ($only_students && ((int)$user[user::role] !== user::role_student))
                        continue;

                    if (!in_array($user_id, $booked_ids)) {
                        $booked_ids[] = $user_id;
                        $booked[] = $user;
                    }
                }
            }
        }

        if (!empty($students)) {
            foreach ($students as $s_value) {
                $user = user::get_by_handle($fw->db, $s_value, user::id . ',' . user::role . ',' . user::active);
                if (empty($user))
                    continue;

                if ((int)$user[user::active] !== user::active_on)
                    continue;

                $user_id = (int)$user[user::id];

                if (!in_array($user_id, $booked_ids)) {
                    $booked_ids[] = $user_id;
                    $booked[] = $user;
                }
            }
        }

        if (!empty($coaches)) {
            foreach ($coaches as $c_value) {
                $user = user::get_by_handle($fw->db, $c_value, user::id . ',' . user::role . ',' . user::active);
                if (empty($user))
                    continue;

                if ((int)$user[user::active] !== user::active_on)
                    continue;

                $user_id = (int)$user[user::id];

                if (!in_array($user_id, $booked_ids)) {
                    $booked_ids[] = $user_id;
                    $booked[] = $user;
                }
            }
        }

        $result = period::command_book_user(
            $fw,
            $command,
            $plan_id,
            $period_id,
            $week_id,
            $booked,
            $lab_id,
            $subject_id,
            $blocked,
            $present
        );

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_book_users(
        \Base $fw,
        string $plan_handle,
        string $period_handle,
        string $lab_handle,
        string $subject_handle,
        array $students,
        array $coaches,
        bool $blocked,
        bool $present
    ): void {
        $command = \cmd::begin($fw, \cmd::book_period_users);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== 1)
            return;

        $plan_id = (int)$plan[plan::id];

        // check period

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::id . ',' . period::week);
        if (empty($period))
            return;

        $period_id = (int)$period[period::id];

        $week_id = (int)$period[period::week];

        // check lab

        $lab = lab::get_by_handle($fw->db, $lab_handle, lab::id);
        if (empty($lab))
            return;

        $lab_id = (int)$lab[lab::id];

        // check subject

        $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id,);
        if (empty($subject))
            return;

        $subject_id = (int)$subject[subject::id];

        $booked = array(); // user list

        if (!empty($students)) {
            foreach ($students as $s_value) {
                $user = user::get_by_handle($fw->db, $s_value, user::id . ',' . user::role . ',' . user::active);
                if (empty($user))
                    continue;

                if ((int)$user[user::active] !== user::active_on)
                    continue;

                $booked[] = $user;
            }
        }

        if (!empty($coaches)) {
            foreach ($coaches as $c_value) {
                $user = user::get_by_handle($fw->db, $c_value, user::id . ',' . user::role . ',' . user::active);
                if (empty($user))
                    continue;

                if ((int)$user[user::active] !== user::active_on)
                    continue;

                $booked[] = $user;
            }
        }

        $result = period::command_book_user(
            $fw,
            $command,
            $plan_id,
            $period_id,
            $week_id,
            $booked,
            $lab_id,
            $subject_id,
            $blocked,
            $present
        );

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_book_user(
        \Base $fw,
        int $command,
        int $plan_id,
        int $period_id,
        int $week_id,
        array $user_list,
        int $lab_id,
        int $subject_id,
        bool $blocked,
        bool $present
    ): int {
        $result = 0;

        foreach ($user_list as $user) {
            $user_id = (int)$user[user::id];

            $book_blocked = $blocked ? book::blocked_on : book::blocked_off;

            $book_excluded = book::excluded_off;
            $book_present = $present ? book::present_on : book::present_off;
            $book_present_time = null;

            $book_description = '';
            $book_review = '';
            $book_rating = book::rating_none;

            $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
            if (!empty($book)) {
                $book_id = (int)$book[book::id];

                book::action_delete($fw, $command, $plan_id, $book_id);

                $book_present = (int)$book[book::present];
                $book_present_time = $book[book::present_time];

                $book_description = $book[book::description];
                $book_review = $book[book::review];
                $book_rating = $book[book::rating];
            }

            if ((int)$user[user::role] !== user::role_student)
                $book_excluded = book::excluded_on;

            book::action_insert_all(
                $fw,
                $command,
                $plan_id,
                $user_id,
                $period_id,
                $lab_id,
                $subject_id,
                $week_id,
                $book_present,
                $book_present_time,
                $book_excluded,
                $book_blocked,
                $book_description,
                $book_review,
                $book_rating
            );

            $result++;
        }

        return $result;
    }

    static function command_rebook(
        \Base $fw,
        string $plan_handle,
        string $user_handle,
        string $period_handle,
        string $lab_handle,
        string $subject_handle,
        bool $blocked,
        bool $present
    ): void {
        $command = \cmd::begin($fw, \cmd::rebook_period);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== 1)
            return;

        $plan_id = (int)$plan[plan::id];

        // check user

        $user = user::get_by_handle($fw->db, $user_handle, user::id . ',' . user::role . ',' . user::active);
        if (empty($user))
            return;

        if ((int)$user[user::active] !== 1)
            return;

        $user_id = (int)$user[user::id];

        // check period

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::id . ',' . period::week);
        if (empty($period))
            return;

        $period_id = (int)$period[period::id];

        $week_id = (int)$period[period::week];

        // check lab

        $lab = lab::get_by_handle($fw->db, $lab_handle, lab::id);
        if (empty($lab))
            return;

        $lab_id = (int)$lab[lab::id];

        // check subject

        $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id,);
        if (empty($subject))
            return;

        $subject_id = (int)$subject[subject::id];

        $book_blocked = $blocked ? book::blocked_on : book::blocked_off;

        $book_excluded = book::excluded_off;
        $book_present = $present ? book::present_on : book::present_off;
        $book_present_time = null;

        $book_description = '';
        $book_review = '';
        $book_rating = book::rating_none;

        $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
        if (!empty($book)) {
            $book_id = (int)$book[book::id];

            book::action_delete($fw, $command, $plan_id, $book_id);

            if ($book_present !== book::present_on)
                $book_present = (int)$book[book::present];

            $book_present_time = $book[book::present_time];

            $book_description = $book[book::description];
            $book_review = $book[book::review];
            $book_rating = $book[book::rating];
        }

        if ((int)$user[user::role] !== user::role_student)
            $book_excluded = book::excluded_on;

        book::action_insert_all(
            $fw,
            $command,
            $plan_id,
            $user_id,
            $period_id,
            $lab_id,
            $subject_id,
            $week_id,
            $book_present,
            $book_present_time,
            $book_excluded,
            $book_blocked,
            $book_description,
            $book_review,
            $book_rating
        );

        \cmd::end($fw, $command);
    }

    static function command_commit(
        \Base $fw,
        string $plan_handle,
        string $period_handle,
        array $teams,
        array $subjects,
        array $labs
    ): void {
        $command = \cmd::begin($fw, \cmd::period_commit);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $plan_id = (int)$plan[plan::id];

        // check period

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::id);
        if (empty($period))
            return;

        $period_id = (int)$period[period::id];

        // check teams and subjects

        if (empty($teams) || empty($subjects))
            return;

        $result = 0;

        foreach ($teams as $t_value) {
            $team = team::get_by_handle($fw->db, $t_value, team::id . ',' . team::active);
            if (empty($team))
                continue;

            if ((int)$team[team::active] !== team::active_on)
                continue;

            $team_id = (int)$team[team::id];

            foreach ($subjects as $s_value) {
                $subject = subject::get_by_handle($fw->db, $s_value, subject::id . ',' . subject::active);
                if (empty($subject))
                    continue;

                if ((int)$subject[subject::active] !== subject::active_on)
                    continue;

                $subject_id = (int)$subject[subject::id];

                if (!empty($labs)) {
                    foreach ($labs as $l_value) {
                        $lab = lab::get_by_handle($fw->db, $l_value, lab::id . ',' . lab::active);
                        if (empty($lab))
                            continue;

                        if ((int)$lab[lab::active] !== lab::active_on)
                            continue;

                        $lab_id = (int)$lab[lab::id];

                        if (!period_team::exists($fw->db, $plan_id, $period_id, $team_id, $subject_id, $lab_id)) {
                            period_team::action_insert($fw, $command, $plan_id, $period_id, $team_id, $subject_id, $lab_id);
                            $result++;
                        }
                    }
                } else {
                    if (!period_team::exists($fw->db, $plan_id, $period_id, $team_id, $subject_id, period_team::lab_none)) {
                        period_team::action_insert($fw, $command, $plan_id, $period_id, $team_id, $subject_id, period_team::lab_none);
                        $result++;
                    }
                }
            }
        }

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_remove_commit(
        \Base $fw,
        string $plan_handle,
        string $period_handle,
        string $team_handle,
        string $subject_handle,
        string $lab_handle
    ): void {
        $command = \cmd::begin($fw, \cmd::period_remove_commit);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $plan_id = (int)$plan[plan::id];

        // check period

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle, period::id);
        if (empty($period))
            return;

        $period_id = (int)$period[period::id];

        // check team

        $team = team::get_by_handle($fw->db, $team_handle, team::id);
        if (empty($team))
            return;

        $team_id = (int)$team[team::id];

        // check subject

        $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id);
        if (empty($subject))
            return;

        $subject_id = (int)$subject[subject::id];

        // check lab

        $lab_id = period_team::lab_none;

        if ($lab_handle !== '0') {
            $lab = lab::get_by_handle($fw->db, $lab_handle, lab::id);
            if (empty($lab))
                return;

            $lab_id = (int)$lab[lab::id];
        }

        // check exists

        $period_team_id = period_team::get_id($fw->db, $plan_id, $period_id, $team_id, $subject_id, $lab_id);
        if ($period_team_id === 0)
            return;

        period_team::action_delete($fw, $command, $plan_id, $period_team_id);

        \cmd::end($fw, $command);
    }

    static function command_update(
        \Base $fw,
        string $plan_handle,
        string $handle,
        string $name,
        string $description,
        string $link,
        bool $blocked,
        bool $register,
        int $exchange
    ): void {
        $command = \cmd::begin($fw, \cmd::period_update);

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $plan_id = (int)$plan[plan::id];

        $period = period::get_by_handle($fw->db, $plan_id, $handle, period::id);
        if (empty($period))
            return;

        $period_id = (int)$period[period::id];

        $exchange_time = '00:' . $exchange . ':00';

        period::action_update(
            $fw,
            $command,
            $plan_id,
            $period_id,
            $name,
            $description,
            $link,
            $blocked ? period::blocked_on : period::blocked_off,
            $register ? period::register_on : period::register_off,
            $exchange_time
        );

        \cmd::end($fw, $command);
    }
}
