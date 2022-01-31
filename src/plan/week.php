<?php

declare(strict_types=1);

namespace plan;

use core\plan;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\time;
use frame\session;

abstract class week
{
    const latest = 0;
    const table = 'week';
    const list = 'week_list';
    const current = 'current';
    const day = 'day';
    const day_list = 'day_list';
    const archive = 'week_archive';

    const id = db::id;
    const handle = db::handle;
    const name = 'name';
    const description = 'description';
    const link = 'link';
    const icon = 'icon';
    const start = 'start';
    const end = 'end';
    const paused = 'paused';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = week::id . ','
        . week::handle  . ','
        . week::name . ','
        . week::description . ','
        . week::link . ','
        . week::icon . ','
        . week::start . ','
        . week::end . ','
        . week::paused . ','
        . week::version . ','
        . week::created . ','
        . week::updated;

    // paused
    const paused_on  = 1;
    const paused_off = 0;

    static function create(\Base $fw, string $prefix): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $prefix . week::table . '` (
                `' . week::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . week::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . week::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . week::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . week::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . week::icon . '` varchar(' . $fw->get('max_icon_length') . ') NOT NULL,
                `' . week::start . '` date DEFAULT NULL,
                `' . week::end . '` date DEFAULT NULL,
                `' . week::paused . '` int(11) NOT NULL DEFAULT 0,
                `' . week::version . '` int(11) NOT NULL DEFAULT ' . week::latest . ',
                `' . week::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . week::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . week::id . '`) USING BTREE,
                UNIQUE KEY `' . week::handle . '` (`' . week::handle . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function delete(\Base $fw, string $prefix): void
    {
        $sql = 'DROP TABLE `' . $prefix . week::table . '`;';
        $fw->db->exec($sql);
    }

    // ---

    static function set(\Base $fw, int $plan_id, int &$time): bool
    {
        week::update_time($time);

        $date = time::to_db_date($time);

        $weeks = week::get_list_date($fw->db, $plan_id, $date, $date);
        if (empty($weeks))
            return false;

        $week = $weeks[0]; // only one

        week::format($fw, $week);

        $fw->set(week::table, $week);

        return true;
    }

    static function set_list(\Base $fw, array $plan): void
    {
        $week_list = array();

        $plan_id = (int)$plan[plan::id];

        $current_week_id = 0;
        if (!empty($plan[week::table]))
            $current_week_id = (int)$plan[week::table][week::id];

        $all_list = week::get_list_latest($fw->db, $plan_id);
        foreach ($all_list as $week) {
            if ((int)$week[week::id] === $current_week_id)
                continue;

            week::format($fw, $week);

            $week_list[] = $week;
        }

        $fw->set(week::list, $week_list);
    }

    static function format(\Base $fw, array &$week): void
    {
        $week[week::start] = time::to_date($fw, $week[week::start]);
        $week[week::end] = time::to_date($fw, $week[week::end]);
    }

    static function update_time(int &$time): bool
    {
        // saturday - sunday -> next week

        $week_day = (int)date('w', $time);
        if (($week_day === 6) || ($week_day === 0)) {
            $time = strtotime('monday next week', $time);
            return true;
        }

        return false;
    }

    // --- query

    static function select(int $plan, string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? week::all : $fields) . ' FROM ' . db::plan_prefix($plan) . week::table . ' ';
    }

    static function get(SQL $sql, int $plan, int $id, string $fields = ''): array
    {
        $get = week::select($plan, $fields) . 'WHERE ' . week::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function new_handle(SQL $sql, int $plan): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(week::get_by_handle($sql, $plan, $handle)))
                return $handle;
        }

        return '';
    }

    static function get_by_handle(SQL $sql, int $plan, string $handle, string $fields = ''): array
    {
        $get = week::select($plan, $fields) . 'WHERE ' . week::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_current(SQL $sql, int $plan, string $time, string $fields = ''): array
    {
        $get = week::select($plan, $fields) . 'WHERE (' . week::start . '<=?) AND (?<=' . week::end . ')';
        return base::first($sql->exec($get, array(
            1 => $time,
            2 => $time,
        )));
    }

    static function get_list(SQL $sql, int $plan, string $fields = ''): array
    {
        $get = week::select($plan, $fields);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . week::name;
        else
            $get .= ' ORDER BY ' . week::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_latest(SQL $sql, int $plan, string $fields = ''): array
    {
        $get = week::select($plan, $fields) . ' ORDER BY ' . week::start . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_date(SQL $sql, int $plan, string $start, string $end, string $fields = ''): array
    {
        $get = week::select($plan, $fields) . 'WHERE (' . week::start . '<=?) AND (?<=' . week::end . ')';
        return $sql->exec($get, array(
            1 => $start,
            2 => $end,
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
            \event::week,
            $id,
            $command,
            $event,
            week::get($fw->db, $plan_id, $id, $fields),
            week::event_revision
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
        string $icon,
        int $time
    ): int {
        $start = date('Y-m-d', strtotime('monday this week', $time));
        $end = date('Y-m-d', strtotime('friday this week', $time));

        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . week::table . ' ('
            . week::handle . ','
            . week::name . ','
            . week::description . ','
            . week::link . ','
            . week::icon . ','
            . week::start . ','
            . week::end . ','
            . week::version . ') VALUES (?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $name,
            3 => $description,
            4 => $link,
            5 => $icon,
            6 => $start,
            7 => $end,
            8 => week::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        week::event_insert($fw, $plan, $id, $command, week::event_created);

        return $id;
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $plan,
        int $week,
        string $name,
        string $description,
        string $link,
        string $icon
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . week::table . ' SET '
            . week::name . '=?,'
            . week::description . '=?,'
            . week::link . '=?,'
            . week::icon . '=? WHERE '
            . week::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $description,
            3 => $link,
            4 => $icon,
            5 => $week,
        ));

        week::event_insert($fw, $plan, $week, $command, week::event_updated);
    }

    static function action_delete(\Base $fw, int $command, int $plan, int $id): void
    {
        week::event_insert($fw, $plan, $id, $command, week::event_deleted);

        $delete = 'DELETE FROM ' . db::plan_prefix($plan) . week::table . ' WHERE ' . week::id . '=?';

        $fw->db->exec($delete, $id);
    }

    // --- command

    static function command_add_internal(\Base $fw, int $command, int $plan_id, int $time): int  // TODO: internal?
    {
        $handle = week::new_handle($fw->db, $plan_id);

        $name = $fw->get('DICT.week');

        $week = date("W", $time);
        $name .= ' ' . $week;

        return week::action_insert($fw, $command, $plan_id, $handle, $name, '', '', '', $time);
    }

    static function command_add(\Base $fw, string $plan_handle, int $time): int
    {
        $command = \cmd::begin($fw, \cmd::week_add);

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return 0;

        if ((int)$plan[plan::active] !== plan::active_on)
            return 0;

        $plan_id = (int)$plan[plan::id];

        $week_id = week::command_add_internal($fw, $command, $plan_id, $time);

        \cmd::end($fw, $command);

        return $week_id;
    }

    static function command_update(
        \Base $fw,
        string $plan_handle,
        string $handle,
        string $name,
        string $description,
        string $link,
        string $icon
    ) {
        $command = \cmd::begin($fw, \cmd::week_update);

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $plan_id = (int)$plan[plan::id];

        $week = week::get_by_handle($fw->db, $plan_id, $handle, week::id);
        if (empty($week))
            return;

        $week_id = (int)$week[week::id];

        week::action_update(
            $fw,
            $command,
            $plan_id,
            $week_id,
            $name,
            $description,
            $link,
            $icon
        );

        \cmd::end($fw, $command);
    }

    static function command_delete(\Base $fw, string $plan_handle, string $handle)
    {
        $command = \cmd::begin($fw, \cmd::week_delete);

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return;

        $plan_id = (int)$plan[plan::id];

        $week = week::get_by_handle($fw->db, $plan_id, $handle, week::id);
        if (empty($week))
            return;

        $week_id = (int)$week[week::id];

        $result = 0;

        $frame_list = frame::get_list($fw->db, $plan_id, $week_id, frame::id);
        foreach ($frame_list as $f_value) {
            $frame_id = (int)$f_value[frame::id];

            $period_list = period::get_list_by_frame($fw->db, $plan_id, $frame_id, period::id);
            foreach ($period_list as $p_value) {
                $period_id = (int)$p_value[period::id];

                $period_team_list = period_team::get_list_by_period($fw->db, $plan_id, $period_id, period_team::id);
                foreach ($period_team_list as $pt_value) {
                    $period_team_id = (int)$pt_value[period_team::id];

                    period_team::action_delete($fw, $command, $plan_id, $period_team_id);
                    $result++;
                }

                $book_list = book::get_list_by_period($fw->db, $plan_id, $period_id, book::id);
                foreach ($book_list as $b_value) {
                    $book_id = (int)$b_value[book::id];

                    book::action_delete($fw, $command, $plan_id, $book_id);
                    $result++;
                }

                period::action_delete($fw, $command, $plan_id, $period_id);
                $result++;
            }

            frame::action_delete($fw, $command, $plan_id, $frame_id);
            $result++;
        }

        week::action_delete($fw, $command, $plan_id, $week_id);
        $result++;

        \cmd::end($fw, $command, $result);
    }

    static function command_import_periods(\Base $fw, string $plan_handle, string $import_handle)
    {
        $command = \cmd::begin_input($fw, \cmd::week_import_periods, array(period::import => $import_handle));

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return;

        $plan_id = (int)$plan[plan::id];

        $import_week = week::get_by_handle($fw->db, $plan_id, $import_handle, week::id);
        if (empty($import_week))
            return;

        $week_import_id = (int)$import_week[week::id];

        $result = 0;

        $plan_time = $plan[plan::time];
        $time = time::get($fw, $plan_time);

        week::update_time($time);

        $week_id = week::command_add_internal($fw, $command, $plan_id, $time);
        if ($week_id === 0)
            return;

        $week = week::get($fw->db, $plan_id, $week_id);
        if (empty($week))
            return;

        $week_start = strtotime($week[week::start]);

        $weekdays_full = time::weekdays_full();

        $frame_list = frame::get_list($fw->db, $plan_id, $week_import_id);
        foreach ($frame_list as $frame) {
            $frame_id = (int)$frame[frame::id];

            $days = array();
            $days[time::mo] = $frame[frame::mo];
            $days[time::tu] = $frame[frame::tu];
            $days[time::we] = $frame[frame::we];
            $days[time::th] = $frame[frame::th];
            $days[time::fr] = $frame[frame::fr];

            $handle = frame::new_handle($fw->db, $plan_id);
            $new_frame_id = frame::action_insert(
                $fw,
                $command,
                $plan_id,
                $week_id,
                $handle,
                $frame[frame::name],
                $frame[frame::start],
                $frame[frame::end],
                $days
            );

            $period_list = period::get_list_by_frame($fw->db, $plan_id, $frame_id);
            foreach ($period_list as $period) {
                $week_day = time::get_week_day($period[period::start]);

                $start_time = strtotime($weekdays_full[$week_day] . ' this week ' . $frame[frame::start], $week_start);
                $end_time = strtotime($weekdays_full[$week_day] . ' this week ' . $frame[frame::end], $week_start);

                period::command_add_internal_all(
                    $fw,
                    $command,
                    $plan_id,
                    $period[period::name],
                    $period[period::description],
                    $period[period::link],
                    $new_frame_id,
                    $week_id,
                    $start_time,
                    $end_time,
                    $period[period::register] === period::register_on,
                    strtotime($period[period::exchange]),
                    $period[period::blocked] === period::blocked_on
                );

                $result++;
            }

            $result++;
        }

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }
}
