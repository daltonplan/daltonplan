<?php

declare(strict_types=1);

namespace plan;

use core\plan;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\time;

abstract class frame
{
    const latest = 0;
    const table = 'frame';
    const list = 'frame_list';

    const id = db::id;
    const handle = db::handle;
    const week = 'week';
    const name = 'name';
    const start = 'start';
    const end = 'end';
    const mo = time::mo;
    const tu = time::tu;
    const we = time::we;
    const th = time::th;
    const fr = time::fr;
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = frame::id . ','
        . frame::handle  . ','
        . frame::name . ','
        . frame::week . ','
        . frame::start . ','
        . frame::end . ','
        . frame::mo . ','
        . frame::tu . ','
        . frame::we . ','
        . frame::th . ','
        . frame::fr . ','
        . frame::version . ','
        . frame::created . ','
        . frame::updated;

    static function create(\Base $fw, string $prefix): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $prefix . frame::table . '` (
                `' . frame::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . frame::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . frame::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . frame::week . '` int(11) NOT NULL,
                `' . frame::start . '` time NOT NULL,
                `' . frame::end . '` time NOT NULL,
                `' . frame::mo . '` int(11) NOT NULL DEFAULT 0,
                `' . frame::tu . '` int(11) NOT NULL DEFAULT 0,
                `' . frame::we . '` int(11) NOT NULL DEFAULT 0,
                `' . frame::th . '` int(11) NOT NULL DEFAULT 0,
                `' . frame::fr . '` int(11) NOT NULL DEFAULT 0,
                `' . frame::version . '` int(11) NOT NULL DEFAULT ' . frame::latest . ',
                `' . frame::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . frame::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . frame::id . '`) USING BTREE,
                UNIQUE KEY `' . frame::handle . '` (`' . frame::handle . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function delete(\Base $fw, string $prefix): void
    {
        $sql = 'DROP TABLE `' . $prefix . frame::table . '`;';
        $fw->db->exec($sql);
    }

    // ---

    public static function set_list(\Base $fw, int $plan_id, int $week_id, int $user_id): void
    {
        $frames = frame::get_list($fw->db, $plan_id, $week_id);
        foreach ($frames as $f_key => $f_value) {
            $frames[$f_key][period::table] = array(1 => array(), 2 => array(), 3 => array(), 4 => array(), 5 => array());

            $periods = period::get_list_by_frame($fw->db, $plan_id, (int)$f_value[frame::id]);
            foreach ($periods as $p_value) {
                $week_day = time::get_week_day($p_value[period::start]);

                $frames[$f_key][period::table][$week_day] = $p_value;

                if ($user_id !== 0) {
                    $period_id = (int)$p_value[period::id];

                    $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
                    if (!empty($book)) {
                        $book[book::lab] = \core\service::get_lab($fw, (int)$book[book::lab]);
                        $book[book::subject] = \core\service::get_subject($fw, (int)$book[book::subject]);

                        $frames[$f_key][period::table][$week_day][book::table] = $book;
                        continue;
                    }
                }
            }

            $frames[$f_key][frame::start] = time::to_time($fw, $f_value[frame::start]);
            $frames[$f_key][frame::end] = time::to_time($fw, $f_value[frame::end]);

            frame::format($fw, $frames[$f_key]);
        }

        $fw->set(frame::list, $frames);
    }

    static function format(\Base $fw, array &$frame): void
    {
        $frame[frame::start] = time::to_time($fw, $frame[frame::start]);
        $frame[frame::end] = time::to_time($fw, $frame[frame::end]);
    }

    // --- query

    static function select(int $plan, string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? frame::all : $fields) . ' FROM ' . db::plan_prefix($plan) . frame::table . ' ';
    }

    static function get(SQL $sql, int $plan, int $id, string $fields = ''): array
    {
        $get = frame::select($plan, $fields) . 'WHERE ' . frame::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function new_handle(SQL $sql, int $plan): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(frame::get_by_handle($sql, $plan, $handle)))
                return $handle;
        }

        return '';
    }

    static function get_by_handle(SQL $sql, int $plan, string $handle, string $fields = ''): array
    {
        $get = frame::select($plan, $fields) . 'WHERE ' . frame::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_list(SQL $sql, int $plan, int $week, string $fields = ''): array
    {
        $get = frame::select($plan, $fields) . 'WHERE ' . frame::week . ' =? ORDER BY ' . frame::start;
        return $sql->exec($get, $week);
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_updated = 2;
    const event_deleted = 3;

    static function event_insert(
        \Base $fw,
        int $plan_id,
        int $id,
        int $command,
        int $event,
        string $fields = ''
    ): void {
        \domain\event::insert(
            $fw,
            \event::frame,
            $id,
            $command,
            $event,
            frame::get($fw->db, $plan_id, $id, $fields),
            frame::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        int $plan,
        int $week,
        string $handle,
        string $name,
        string $start,
        string $end,
        array $days
    ): int {
        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . frame::table . ' ('
            . frame::handle . ','
            . frame::name . ','
            . frame::week . ','
            . frame::start . ','
            . frame::end . ','
            . frame::mo . ','
            . frame::tu . ','
            . frame::we . ','
            . frame::th . ','
            . frame::fr . ','
            . frame::version . ') VALUES (?,?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $name,
            3 => $week,
            4 => $start,
            5 => $end,
            6 => $days[time::mo],
            7 => $days[time::tu],
            8 => $days[time::we],
            9 => $days[time::th],
            10 => $days[time::fr],
            11 => frame::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        frame::event_insert($fw, $plan, $id, $command, frame::event_created);

        return $id;
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $plan,
        int $frame,
        string $name,
        string $start,
        string $end,
        array $days
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . frame::table . ' SET '
            . frame::name . '=?,'
            . frame::start . '=?,'
            . frame::end . '=?,'
            . frame::mo . '=?,'
            . frame::tu . '=?,'
            . frame::we . '=?,'
            . frame::th . '=?,'
            . frame::fr . '=? WHERE '
            . frame::id . '=?';
        $fw->db->exec($update, array(
            1 => $name,
            2 => $start,
            3 => $end,
            4 => $days[time::mo],
            5 => $days[time::tu],
            6 => $days[time::we],
            7 => $days[time::th],
            8 => $days[time::fr],
            9 => $frame
        ));

        frame::event_insert($fw, $plan, $frame, $command, frame::event_updated);
    }

    static function action_delete(\Base $fw, int $command, int $plan, int $frame): void
    {
        frame::event_insert($fw, $plan, $frame, $command, frame::event_deleted);

        $delete = 'DELETE FROM ' . db::plan_prefix($plan) . frame::table . ' WHERE ' . frame::id . '=?';
        $fw->db->exec($delete, $frame);
    }

    // --- command

    static function command_add(
        \Base $fw,
        string $plan_handle,
        string $name,
        string $start,
        string $end,
        array $days,
        string $week_handle
    ): void {
        $command = \cmd::begin($fw, \cmd::frame_add);

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return;

        $plan_id = (int)$plan[plan::id];

        $result = 0;

        $plan_time = $plan[plan::time];
        $time = time::get($fw, $plan_time);

        $week = array();
        $week_created = false;

        if ($week_handle === '') {
            if (!week::set($fw, $plan_id, $time)) {
                $week_id = week::command_add_internal($fw, $command, $plan_id, $time);

                $week = week::get($fw->db, $plan_id, $week_id);
                $fw->set(week::table, $week);

                $week_created = true;
                $result++;
            }
        } else {
            $week = week::get_by_handle($fw->db, $plan_id, $week_handle);
            $fw->set(week::table, $week);
        }

        $week = $fw->get(week::table);
        if (empty($week))
            return;

        $week_id = (int)$week[week::id];

        $handle = frame::new_handle($fw->db, $plan_id);
        $frame_id = frame::action_insert($fw, $command, $plan_id, $week_id, $handle, $name, $start, $end, $days);
        $result++;

        $week_start = strtotime($week[week::start]);

        $first_register = $week_created;

        $weekdays = time::weekdays_short();
        $weekdays_full = time::weekdays_full();

        foreach ($weekdays as $w_key => $w_value) {
            if (!$days[$w_value])
                continue;

            $start_time = strtotime($weekdays_full[$w_key] . ' this week ' . $start, $week_start);
            $end_time = strtotime($weekdays_full[$w_key] . ' this week ' . $end, $week_start);

            $exchange = strtotime($plan[plan::exchange]);
            $register = false;

            if ($first_register) {
                $exchange = strtotime($plan[plan::register]);
                $register = true;

                $first_register = false;
            }

            period::command_add_internal(
                $fw,
                $command,
                $plan_id,
                $name,
                $frame_id,
                $week_id,
                $start_time,
                $end_time,
                $register,
                $exchange
            );
            $result++;
        }

        \cmd::end($fw, $command, $result);
    }

    static function command_update(
        \Base $fw,
        string $plan_handle,
        string $frame_handle,
        string $name,
        string $start,
        string $end,
        array $days
    ): void {
        $command = \cmd::begin($fw, \cmd::frame_update);

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return;

        $plan_id = (int)$plan[plan::id];

        $frame = frame::get_by_handle($fw->db, $plan_id, $frame_handle);
        if (empty($frame))
            return;

        $frame_id = (int)$frame[frame::id];
        $week_id = (int)$frame[frame::week];

        $week = week::get($fw->db, $plan_id, $week_id);
        if (empty($week))
            return;

        // sync action
        // 1 = add
        // 2 = update
        // 3 = remove

        // days[weekday] = sync action
        // days['mo'] = 1
        // days['tu'] = 2
        // days['we'] = 3
        // ...

        $sync = array();
        $sync[frame::mo] = ($days[frame::mo] && !$frame[frame::mo]) ? 1 : ((!$days[frame::mo] && $frame[frame::mo]) ? 3 : 2);
        $sync[frame::tu] = ($days[frame::tu] && !$frame[frame::tu]) ? 1 : ((!$days[frame::tu] && $frame[frame::tu]) ? 3 : 2);
        $sync[frame::we] = ($days[frame::we] && !$frame[frame::we]) ? 1 : ((!$days[frame::we] && $frame[frame::we]) ? 3 : 2);
        $sync[frame::th] = ($days[frame::th] && !$frame[frame::th]) ? 1 : ((!$days[frame::th] && $frame[frame::th]) ? 3 : 2);
        $sync[frame::fr] = ($days[frame::fr] && !$frame[frame::fr]) ? 1 : ((!$days[frame::fr] && $frame[frame::fr]) ? 3 : 2);

        // sync period and period start + end time, rename period if frame name changed and period name is old

        $periods = period::get_list_by_frame($fw->db, $plan_id, $frame_id);

        $weekdays = time::weekdays_short();
        $weekdays_full = time::weekdays_full();

        $week_start = strtotime($week[week::start]);

        $result = 0;

        foreach ($weekdays as $w_key => $w_value) {
            if ($sync[$w_value] === 1) { // add
                $start_time = strtotime($weekdays_full[$w_key] . ' this week ' . $start, $week_start);
                $end_time = strtotime($weekdays_full[$w_key] . ' this week ' . $end, $week_start);

                $exchange = strtotime($plan[plan::exchange]);
                $register = false;

                period::command_add_internal(
                    $fw,
                    $command,
                    $plan_id,
                    $name,
                    $frame_id,
                    $week_id,
                    $start_time,
                    $end_time,
                    $register,
                    $exchange
                );
                $result++;
            } else if ($sync[$w_value] === 2) { // update
                foreach ($periods as $p_value) {
                    $week_day = (int)date('w', strtotime($p_value[period::start]));
                    if ($week_day !== $w_key)
                        continue;

                    $period_id = (int)$p_value[period::id];
                    $name_changed = (($name !== $frame[frame::name]) && ($p_value[period::name] === $frame[frame::name]));

                    $start_time = strtotime($weekdays_full[$w_key] . ' this week ' . $start, $week_start);
                    $end_time = strtotime($weekdays_full[$w_key] . ' this week ' . $end, $week_start);

                    $start = date('Y-m-d H:i', $start_time);
                    $end = date('Y-m-d H:i', $end_time);

                    $time_changed = ($start_time !== strtotime($p_value[period::start])) || ($end_time !== strtotime($p_value[period::end]));
                    if ($name_changed || $time_changed) {
                        period::action_update_name_time($fw, $command, $plan_id, $period_id, $name, $start, $end);
                        $result++;
                    }
                    break;
                }
            } else if ($sync[$w_value] === 3) { // remove
                foreach ($periods as $p_value) {
                    $week_day = (int)date('w', strtotime($p_value[period::start]));
                    if ($week_day !== $w_key)
                        continue;

                    $period_id = (int)$p_value[period::id];

                    $books = book::get_list_by_period($fw->db, $plan_id, $period_id, book::id);
                    foreach ($books as $b_value) {
                        $book_id = (int)$b_value[book::id];
                        book::action_delete($fw, $command, $plan_id, $book_id);
                        $result++;
                    }

                    period::action_delete($fw, $command, $plan_id, $period_id);
                    $result++;
                    break;
                }
            }
        }

        // --

        frame::action_update($fw, $command, $plan_id, $frame_id, $name, $start, $end, $days);
        $result++;

        \cmd::end($fw, $command, $result);
    }

    static function command_remove(\Base $fw, string $plan_handle, string $frame_handle): void
    {
        $command = \cmd::begin($fw, \cmd::frame_remove);

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id);
        if (empty($plan))
            return;

        $plan_id = (int)$plan[plan::id];

        $frame = frame::get_by_handle($fw->db, $plan_id, $frame_handle);
        if (empty($frame))
            return;

        $frame_id = (int)$frame[frame::id];

        $result = 0;

        $periods = period::get_list_by_frame($fw->db, $plan_id, $frame_id, period::id);
        foreach ($periods as $p_value) {
            $period_id = (int)$p_value[period::id];

            $books = book::get_list_by_period($fw->db, $plan_id, $period_id, book::id);
            foreach ($books as $b_value) {
                $book_id = (int)$b_value[book::id];

                book::action_delete($fw, $command, $plan_id, $book_id);
                $result++;
            }

            $period_teams = period_team::get_list_by_period($fw->db, $plan_id, $period_id);
            foreach ($period_teams as $pt_value) {
                $period_team_id = (int)$pt_value[period_team::id];

                period_team::action_delete($fw, $command, $plan_id, $period_team_id);
                $result++;
            }

            period::action_delete($fw, $command, $plan_id, $period_id);
            $result++;
        }

        frame::action_delete($fw, $command, $plan_id, $frame_id);
        $result++;

        \cmd::end($fw, $command, $result);
    }
}
