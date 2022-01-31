<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\time;
use frame\session;

abstract class plan
{
    const latest = 0;
    const table = 'plan';
    const list = 'plan_list';

    const min_handle_length = 'min_plan_handle_length';
    const max_handle_length = 'max_plan_handle_length';

    const id = db::id;
    const handle = db::handle;
    const name = 'name';
    const description = 'description';
    const link = 'link';
    const icon = 'icon';
    const periods = 'periods';
    const start = 'start';
    const end = 'end';
    const register = 'register';
    const exchange = 'exchange';
    const period = 'period';
    const time = 'time';
    const active = db::active;
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = plan::id . ','
        . plan::handle  . ','
        . plan::name . ','
        . plan::description . ','
        . plan::link . ','
        . plan::icon . ','
        . plan::periods . ','
        . plan::start . ','
        . plan::end . ','
        . plan::register . ','
        . plan::exchange . ','
        . plan::period . ','
        . plan::time . ','
        . plan::active . ','
        . plan::version . ','
        . plan::created . ','
        . plan::updated;

    // active
    const active_on  = 1;
    const active_off = 0;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . plan::table . '` (
                `' . plan::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . plan::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . plan::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . plan::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . plan::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . plan::icon . '` varchar(' . $fw->get('max_icon_length') . ') NOT NULL,
                `' . plan::periods . '` int(11) NOT NULL DEFAULT 0,
                `' . plan::start . '` timestamp NULL DEFAULT NULL,
                `' . plan::end . '` timestamp NULL DEFAULT NULL,
                `' . plan::register . '` time NOT NULL,
                `' . plan::exchange . '` time NOT NULL,
                `' . plan::period . '` varchar(32) NOT NULL,
                `' . plan::time . '` timestamp NULL DEFAULT NULL,
                `' . plan::active . '` int(11) NOT NULL DEFAULT 1,
                `' . plan::version . '` int(11) NOT NULL DEFAULT ' . plan::latest . ',
                `' . plan::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . plan::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . plan::id . '`) USING BTREE,
                UNIQUE KEY `' . plan::handle . '` (`' . plan::handle . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    static function visible(\Base $fw, int $plan_id): bool
    {
        foreach ($fw->get(plan::list) as $plan) {
            if ((int)$plan[plan::id] === $plan_id)
                return true;
        }

        return false;
    }

    static function get_formatted(\Base $fw, int $plan_id): array
    {
        $plan = plan::get($fw->db, $plan_id);
        if (empty($plan))
            return array();

        plan::format($fw, $plan);

        return $plan;
    }

    static function get_by_handle_formatted(\Base $fw, string $handle): array
    {
        $plan = plan::get_by_handle($fw->db, $handle);
        if (empty($plan))
            return array();

        plan::format($fw, $plan);

        return $plan;
    }

    static function get_list_formatted(\Base $fw): array
    {
        $plan_list = plan::get_list($fw->db);
        if (empty($plan_list))
            return array();

        $result = array();

        foreach ($plan_list as $p_key => $p_value) {
            if ((int)$p_value[plan::active] !== plan::active_on)
                continue;

            plan::format($fw, $p_value);

            $result[$p_key] = $p_value;
        }

        return $result;
    }

    static function set_list(\Base $fw): void
    {
        $plan_list = plan::get_list_formatted($fw);

        $fw->set(plan::list, $plan_list);
    }

    static function set_list_only_team_list(\Base $fw, array $team_list): void
    {
        $plan_list = array();
        $plan_id_list = array();

        foreach ($team_list as $team) {
            $plan_team_list = plan_team::get_list_by_team($fw->db, (int)$team[team::id], plan_team::plan);
            foreach ($plan_team_list as $pt_value) {
                $plan_id = (int)$pt_value[plan_team::plan];

                if (in_array($plan_id, $plan_id_list))
                    continue;

                $plan = plan::get($fw->db, $plan_id);
                if (empty($plan))
                    continue;

                if ((int)$plan[plan::active] !== plan::active_on)
                    continue;

                plan::format($fw, $plan);

                $plan_list[] = $plan;
                $plan_id_list[] = $plan_id;
            }
        }

        if (session::get_sort($fw)) {
            usort($plan_list, function ($a, $b) {
                return $a[plan::name] <=> $b[plan::name];
            });
        }

        $fw->set(plan::list, $plan_list);
    }

    static function format(\Base $fw, array &$plan): void
    {
        $plan[plan::register] = (int)date('i', strtotime($plan[plan::register]));
        $plan[plan::exchange] = (int)date('i', strtotime($plan[plan::exchange]));

        plan::set_date_time($fw, $plan);
    }

    static function set_date_time(\Base $fw, array &$plan): void
    {
        if ($plan[plan::time] !== null)
            $plan['date_time'] = time::get_format($fw, strtotime($plan[plan::time]));
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? plan::all : $fields) . ' FROM ' . db::prefix() . plan::table . ' ';
    }

    static function exists(SQL $sql, string $handle): bool
    {
        $get = plan::select(plan::id) . 'WHERE ' . plan::handle . '=?';
        return !empty($sql->exec($get, $handle));
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE ' . plan::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_handle(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE ' . plan::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE ' . plan::active . '=1';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . plan::name;
        else
            $get .= ' ORDER BY ' . plan::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_range(SQL $sql, string $start, string $end, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE (' . plan::start . '<=?) AND (?<=' . plan::end . ')';
        return $sql->exec($get, array(
            1 => $start,
            2 => $end
        ));
    }

    static function get_list_before_start(SQL $sql, string $date, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE (' . plan::start . '<=?) ORDER BY ' . plan::start . ' DESC';
        return $sql->exec($get, $date);
    }

    static function get_list_after_date(SQL $sql, string $date, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE (' . plan::end . '>?) AND (' . plan::start . '>?) ORDER BY ' . plan::start;
        return $sql->exec($get, array(
            1 => $date,
            2 => $date
        ));
    }

    static function get_list_before_date(SQL $sql, string $date, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE (' . plan::end . '<?) ORDER BY ' . plan::start . ' DESC';
        return $sql->exec($get, $date);
    }

    static function get_list_without(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = plan::select($fields) . 'WHERE ' . plan::handle . '!=? ORDER BY ' . plan::start . ' DESC';
        return $sql->exec($get, $handle);
    }

    // --- event

    const event_revision = 0;

    const event_created     = 1;
    const event_updated     = 2;
    const event_deleted     = 3;
    const event_deactivated = 4;
    const event_activated   = 5;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::plan,
            $id,
            $command,
            $event,
            plan::get($fw->db, $id, $fields),
            plan::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        string $handle,
        string $name,
        string $description,
        string $link,
        string $icon
    ): int {
        $plan_register_minutes = time::to_db_time(mktime(0, (int)$fw->get('plan_register_minutes'), 0));
        $plan_exchange_minutes = time::to_db_time(mktime(0, (int)$fw->get('plan_exchange_minutes'), 0));

        $plan_period_name = '';
        if ($fw->exists('plan_period_name'))
            $plan_period_name = $fw->get('plan_period_name');

        $insert = 'INSERT INTO ' . db::prefix() . plan::table . ' ('
            . plan::handle . ','
            . plan::name . ','
            . plan::description . ','
            . plan::link . ','
            . plan::icon . ','
            . plan::periods . ','
            . plan::register . ','
            . plan::exchange . ','
            . plan::period . ','
            . plan::version . ') VALUES (?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $name,
            3 => $description,
            4 => $link,
            5 => $icon,
            6 => (int)$fw->get('plan_max_periods'),
            7 => $plan_register_minutes,
            8 => $plan_exchange_minutes,
            9 => $plan_period_name,
            10 => plan::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        plan::event_insert($fw, $id, $command, plan::event_created);

        return $id;
    }

    static function action_update_time(
        \Base $fw,
        int $command,
        int $plan,
        string|null $time
    ): void {
        $update = 'UPDATE ' . db::prefix() . plan::table . ' SET '
            . plan::time . '=? WHERE '
            . plan::id . '=?';

        $fw->db->exec($update, array(
            1 => $time,
            2 => $plan,
        ));

        plan::event_insert($fw, $plan, $command, plan::event_updated, plan::time . ',' . plan::version);
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $plan,
        string $name,
        string $description,
        string $link,
        string $icon,
        string $register,
        string $exchange
    ): void {
        $update = 'UPDATE ' . db::prefix() . plan::table . ' SET '
            . plan::name . '=?,'
            . plan::description . '=?,'
            . plan::link . '=?,'
            . plan::icon . '=?,'
            . plan::register . '=?,'
            . plan::exchange . '=? WHERE '
            . plan::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $description,
            3 => $link,
            4 => $icon,
            5 => $register,
            6 => $exchange,
            7 => $plan,
        ));

        plan::event_insert($fw, $plan, $command, plan::event_updated);
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        plan::event_insert($fw, $id, $command, plan::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . plan::table . ' WHERE ' . plan::id . '=?';

        $fw->db->exec($delete, $id);
    }

    static function action_deactivate(\Base $fw, int $command, int $id): void
    {
        plan::event_insert($fw, $id, $command, plan::event_deactivated);

        $update = 'UPDATE ' . db::prefix() . plan::table . ' SET ' . plan::active . '=0 WHERE ' . plan::id . '=?';

        $fw->db->exec($update, $id);
    }

    // --- command

    static function command_add(\Base $fw, string $handle, string $name, string $description, string $link, string $icon): void
    {
        $command = \cmd::begin($fw, \cmd::plan_add);

        if (plan::exists($fw->db, $handle)) {
            return;
        }

        $id = plan::action_insert($fw, $command, $handle, $name, $description, $link, $icon);

        \plan\service::create_db($fw, $id);

        \cmd::end($fw, $command);
    }

    static function command_time_machine(\Base $fw, string $handle, string $time): void
    {
        $command = \cmd::begin($fw, \cmd::plan_time_machine);

        $plan = plan::get_by_handle($fw->db, $handle, plan::id . ',' . plan::time . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $db_time = time::to_db_date_time_str($time);
        if ($db_time === $plan[plan::time])
            return;

        $plan_id = (int)$plan[plan::id];

        plan::action_update_time($fw, $command, $plan_id, $db_time);

        \cmd::end($fw, $command);
    }

    static function command_reset_time_machine(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::plan_reset_time_machine);

        $plan = plan::get_by_handle($fw->db, $handle, plan::id . ',' . plan::time . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        if ($plan[plan::time] === null)
            return;

        $plan_id = (int)$plan[plan::id];

        plan::action_update_time($fw, $command, $plan_id, null);

        \cmd::end($fw, $command);
    }

    static function command_update(
        \Base $fw,
        string $handle,
        string $name,
        string $description,
        string $link,
        string $icon,
        int $register,
        int $exchange
    ) {
        $command = \cmd::begin($fw, \cmd::plan_update);

        $plan = plan::get_by_handle($fw->db, $handle);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
            return;

        $plan_id = (int)$plan[plan::id];

        $register_time = '00:' . $register . ':00';
        $exchange_time = '00:' . $exchange . ':00';

        plan::action_update(
            $fw,
            $command,
            $plan_id,
            $name,
            $description,
            $link,
            $icon,
            $register_time,
            $exchange_time
        );

        \cmd::end($fw, $command);
    }

    static function command_remove(\Base $fw, string $handle)
    {
        $command = \cmd::begin($fw, \cmd::plan_remove);

        $plan = plan::get_by_handle($fw->db, $handle);
        if (empty($plan))
            return;

        $plan_id = (int)$plan[plan::id];

        plan::action_deactivate($fw, $command, $plan_id);

        \cmd::end($fw, $command);
    }
}
