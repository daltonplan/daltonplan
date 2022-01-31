<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\session;

abstract class plan_team
{
    const latest = 0;
    const table = 'plan_team';

    const id = db::id;
    const plan = 'plan';
    const team = 'team';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = plan_team::id . ','
        . plan_team::team . ','
        . plan_team::version . ','
        . plan_team::created . ','
        . plan_team::updated;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . plan_team::table . '` (
                `' . plan_team::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . plan_team::plan . '` int(11) NOT NULL,
                `' . plan_team::team . '` int(11) NOT NULL,
                `' . plan_team::version . '` int(11) NOT NULL DEFAULT ' . plan_team::latest . ',
                `' . plan_team::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . plan_team::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . plan_team::id . '`) USING BTREE,
                UNIQUE KEY `' . plan_team::team . '` (`' . plan_team::plan . '`,`' . plan_team::team . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';
        $fw->db->exec($sql);
    }

    // ---

    static function get_team_list(\Base $fw, int $plan_id): array
    {
        $plan_team_list = plan_team::get_list_by_plan($fw->db, $plan_id);

        $teams = team::get_list($fw->db);
        foreach ($teams as $key => $team) {
            $teams[$key][db::selected] = false;

            foreach ($plan_team_list as $plan_team) {
                if ((int)$team[team::id] === (int)$plan_team[plan_team::team]) {
                    $teams[$key][db::selected] = true;
                    break;
                }
            }
        }

        return $teams;
    }

    static function set_team_list(\Base $fw, int $plan_id): void
    {
        $fw->set(team::list, plan_team::get_team_list($fw, $plan_id));
    }

    // TODO: change to $plan_id -> $plan_handle - check call
    static function assign(\Base $fw, int $plan_id, array $team_handles): void
    {
        plan_team::command_assign($fw, $plan_id, $team_handles);
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? plan_team::all : $fields) . ' FROM ' . db::prefix() . plan_team::table . ' ';
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = plan_team::select($fields) . 'WHERE ' . plan_team::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_team(SQL $sql, int $team, string $fields = ''): array
    {
        $get = plan_team::select($fields) . 'WHERE ' . plan_team::team . '=?';
        return base::first($sql->exec($get, $team));
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = plan_team::select($fields);

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . plan_team::created;
        else
            $get .= ' ORDER BY ' . plan_team::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_by_plan(SQL $sql, int $plan, string $fields = ''): array
    {
        $get = plan_team::select($fields) . 'WHERE ' . plan_team::plan . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . plan_team::created;
        else
            $get .= ' ORDER BY ' . plan_team::updated . ' DESC';

        return $sql->exec($get, $plan);
    }

    static function get_list_by_team(SQL $sql, int $team, string $fields = ''): array
    {
        $get = plan_team::select($fields) . 'WHERE ' . plan_team::team . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . plan_team::created;
        else
            $get .= ' ORDER BY ' . plan_team::updated . ' DESC';

        return $sql->exec($get, $team);
    }

    // --- event

    const event_revision = 0;

    const event_created = 1;
    const event_deleted = 2;

    static function event_insert(\Base $fw, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::plan_team,
            $id,
            $command,
            $event,
            plan_team::get($fw->db, $id, $fields),
            plan_team::event_revision
        );
    }

    // --- action

    static function action_insert(\Base $fw, int $command, int $team, int $plan): int
    {
        $insert = 'INSERT INTO ' . db::prefix() . plan_team::table . ' ('
            . plan_team::team . ','
            . plan_team::plan . ','
            . plan_team::version . ') VALUES (?,?,?)';

        $fw->db->exec($insert, array(
            1 => $team,
            2 => $plan,
            3 => plan_team::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        plan_team::event_insert($fw, $id, $command, plan_team::event_created);

        return $id;
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        plan_team::event_insert($fw, $id, $command, plan_team::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . plan_team::table . ' WHERE ' . plan_team::id . '=?';

        $fw->db->exec($delete, $id);
    }

    // --- command

    static function command_assign(\Base $fw, int $plan_id, array $team_handles): void
    {
        $command = \cmd::begin($fw, \cmd::plan_assign_team);

        $result = 0;

        $team_plan_list = plan_team::get_list_by_plan($fw->db, $plan_id);

        if (!empty($team_handles)) {
            $updated = array();

            foreach ($team_handles as $team_handle) {
                $team_id = team::get_id_by_handle($fw, $team_handle);
                if ($team_id === 0)
                    continue;

                $found = false;
                foreach ($team_plan_list as $team_plan) {
                    if ($team_plan[plan_team::team] === $team_id) {
                        $found = true;

                        $updated[] = $team_id;
                        break;
                    }
                }

                if (!$found) {
                    plan_team::action_insert($fw, $command, $team_id, $plan_id);

                    $updated[] = $team_id;
                    $result++;
                }
            }

            foreach ($team_plan_list as $team_plan) {
                if (!in_array((int)$team_plan[plan_team::team], $updated)) {
                    $team_plan_id = (int)$team_plan[plan_team::id];
                    plan_team::action_delete($fw, $command, $team_plan_id);

                    $result++;
                }
            }
        } else {
            foreach ($team_plan_list as $team_plan) {
                $team_plan_id = (int)$team_plan[plan_team::id];
                plan_team::action_delete($fw, $command, $team_plan_id);

                $result++;
            }
        }

        if ($result !== 0)
            \cmd::end($fw, $command, $result);
    }
}
