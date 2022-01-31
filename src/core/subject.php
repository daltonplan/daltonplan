<?php

declare(strict_types=1);

namespace core;

use DB\SQL;

use frame\base;
use frame\db;
use frame\id;
use frame\session;

abstract class subject
{
    const latest = 0;
    const table = 'subject';
    const list = 'subject_list';
    const children = 'children';
    const amount = 'amount';

    const id = db::id;
    const handle = db::handle;
    const name = 'name';
    const description = 'description';
    const link = 'link';
    const icon = 'icon';
    const periods = 'periods';
    const exclusive = 'exclusive';
    const locked = 'locked';
    const managed = 'managed';
    const parent = 'parent';
    const active = 'active';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    const all = subject::id . ','
        . subject::handle  . ','
        . subject::name . ','
        . subject::description . ','
        . subject::link . ','
        . subject::icon . ','
        . subject::periods . ','
        . subject::exclusive . ','
        . subject::locked . ','
        . subject::managed . ','
        . subject::parent . ','
        . subject::active . ','
        . subject::version . ','
        . subject::created . ','
        . subject::updated;

    // exclusive
    const exclusive_off = 0;
    const exclusive_on  = 1;

    // locked
    const locked_off    = 0;
    const locked_on     = 1;

    // managed
    const managed_off   = 0;
    const managed_on    = 1;

    // parent
    const parent_none   = 0;

    // active
    const active_on     = 1;
    const active_off    = 0;

    static function create(\Base $fw): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . db::prefix() . subject::table . '` (
                `' . subject::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . subject::handle . '` varchar(' . $fw->get(base::max_handle_length) . ') NOT NULL,
                `' . subject::name . '` varchar(' . $fw->get('max_name_length') . ') NOT NULL,
                `' . subject::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . subject::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . subject::icon . '` varchar(' . $fw->get('max_icon_length') . ') NOT NULL,
                `' . subject::periods . '` int(11) NOT NULL DEFAULT 0,
                `' . subject::exclusive . '` int(11) NOT NULL DEFAULT 0,
                `' . subject::locked . '` int(11) NOT NULL DEFAULT 0,
                `' . subject::managed . '` int(11) NOT NULL DEFAULT 0,
                `' . subject::parent . '` int(11) NOT NULL DEFAULT 0,
                `' . subject::active . '` int(11) NOT NULL DEFAULT 1,
                `' . subject::version . '` int(11) NOT NULL DEFAULT ' . subject::latest . ',
                `' . subject::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . subject::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . subject::id . '`) USING BTREE,
                UNIQUE KEY `' . subject::handle . '` (`' . subject::handle . '`) USING BTREE,
                KEY `' . subject::parent . '` (`' . subject::parent . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    // ---

    static function count_deep(array $subject_list, bool $only_children = false): int
    {
        if (empty($subject_list))
            return 0;

        $count = 0;

        foreach ($subject_list as $subject) {
            if (isset($subject[subject::children])) {
                foreach ($subject[subject::children] as $child) {
                    $count++;
                }
            }

            if (!$only_children)
                $count++;
        }

        return $count;
    }

    static function sync(array &$children, int $c_key, array $c_value, array $s_value): void
    {
        if (((int)$c_value[subject::exclusive] === 0) && ((int)$s_value[subject::exclusive] !== 0))
            $children[$c_key][subject::exclusive] = $s_value[subject::exclusive];

        if (((int)$c_value[subject::managed] === 0) && ((int)$s_value[subject::managed] !== 0))
            $children[$c_key][subject::managed] = $s_value[subject::managed];

        if (((int)$c_value[subject::locked] === 0) && ((int)$s_value[subject::locked] !== 0))
            $children[$c_key][subject::locked] = $s_value[subject::locked];
    }

    static function set_list(\Base $fw): void
    {
        $subject_list = subject::get_list_top($fw->db);

        foreach ($subject_list as $s_key => $s_value) {
            $children = subject::get_list_children($fw->db, (int)$s_value[subject::id]);

            $amount = 0;

            foreach ($children as $c_value) {
                if ($c_value[subject::periods] > 0)
                    $amount += (int)$c_value[subject::periods];
            }

            if ($amount > 0)
                $subject_list[$s_key][subject::amount] = $amount;

            $subject_list[$s_key][db::children] = $children;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_archive(\Base $fw): void
    {
        $subject_list = subject::get_list_top_all($fw->db);

        $result = array();

        foreach ($subject_list as $s_value) {
            $subject_id = (int)$s_value[subject::id];
            $subject_active = (int)$s_value[subject::active];

            $children = array();
            if (($subject_active === subject::active_on))
                $children = subject::get_list_children_inactive($fw->db, $subject_id);
            else
                $children = subject::get_list_children_all($fw->db, $subject_id);

            if (($subject_active === subject::active_on) &&
                (empty($children))
            )
                continue;

            $amount = 0;

            foreach ($children as $c_value) {
                if ($c_value[subject::periods] > 0)
                    $amount += (int)$c_value[subject::periods];
            }

            if ($amount > 0)
                $s_value[subject::amount] = $amount;

            $s_value[db::children] = $children;

            $result[] = $s_value;
        }

        $fw->set(subject::list, $result);
    }

    static function set_list_top(\Base $fw): void
    {
        $subject_list = subject::get_list_top($fw->db);

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_by_lab(\Base $fw, int $lab_id): void
    {
        $lab_subject_list = lab_subject::get_list_by_lab($fw->db, $lab_id, lab_subject::subject);

        $subject_list = subject::get_list_top($fw->db);

        foreach ($subject_list as $s_key => $s_value) {
            $children = subject::get_list_children($fw->db, (int)$s_value[subject::id]);
            foreach ($children as $c_key => $c_value) {
                subject::sync($children, $c_key, $c_value, $s_value);

                $children[$c_key][db::selected] = false;

                foreach ($lab_subject_list as $ls_value) {
                    if ((int)$ls_value[lab_subject::subject] === (int)$c_value[subject::id]) {
                        $children[$c_key][db::selected] = true;
                        break;
                    }
                }
            }

            $subject_list[$s_key][db::selected] = false;

            foreach ($lab_subject_list as $ls_value) {
                if ((int)$ls_value[lab_subject::subject] === (int)$s_value[subject::id]) {
                    $subject_list[$s_key][db::selected] = true;
                    break;
                }
            }

            $subject_list[$s_key][db::children] = $children;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_by_team(\Base $fw, int $team_id): void
    {
        $team_subject_list = team_subject::get_list_by_team($fw->db, $team_id, team_subject::subject);

        $subject_list = subject::get_list_top($fw->db);

        foreach ($subject_list as $s_key => $s_value) {
            $children = subject::get_list_children($fw->db, (int)$s_value[subject::id]);
            foreach ($children as $c_key => $c_value) {
                subject::sync($children, $c_key, $c_value, $s_value);

                $children[$c_key][db::selected] = false;

                foreach ($team_subject_list as $ts_value) {
                    if ((int)$ts_value[team_subject::subject] === (int)$c_value[subject::id]) {
                        $children[$c_key][db::selected] = true;
                        break;
                    }
                }
            }

            $subject_list[$s_key][db::selected] = false;

            foreach ($team_subject_list as $ts_value) {
                if ((int)$ts_value[team_subject::subject] === (int)$s_value[subject::id]) {
                    $subject_list[$s_key][db::selected] = true;
                    break;
                }
            }

            $subject_list[$s_key][db::children] = $children;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_top_by_parent(\Base $fw, int $parent_id): void
    {
        $subject_list = subject::get_list_top($fw->db);

        foreach ($subject_list as $s_key => $s_value) {
            $subject_list[$s_key][db::selected] = (int)$s_value[subject::id] === $parent_id;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_top_all_by_parent(\Base $fw, int $parent_id): void
    {
        $subject_list = subject::get_list_top_all($fw->db);

        foreach ($subject_list as $s_key => $s_value) {
            $subject_list[$s_key][db::selected] = (int)$s_value[subject::id] === $parent_id;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_by_subject(\Base $fw, int $subject_id): void
    {
        $subject_list = subject::get_list_top($fw->db);

        foreach ($subject_list as $s_key => $s_value) {
            $children = subject::get_list_children($fw->db, (int)$s_value[subject::id]);
            foreach ($children as $c_key => $c_value) {
                subject::sync($children, $c_key, $c_value, $s_value);

                $children[$c_key][db::selected] = (int)$c_value[subject::id] === $subject_id;
            }

            $subject_list[$s_key][db::selected] = (int)$s_value[subject::id] === $subject_id;

            $subject_list[$s_key][db::children] = $children;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function set_list_only_team_list(\Base $fw, array $team_list): void
    {
        $top_list = array();
        $child_list = array();

        // get all team subjects and split into top and child list

        foreach ($team_list as $team) {
            $team_subject_list = team_subject::get_list_by_team($fw->db, (int)$team[team::id], team_subject::subject);
            foreach ($team_subject_list as $ts_value) {
                $subject = service::get_subject($fw, (int)$ts_value[team_subject::subject]);
                if (empty($subject))
                    continue;

                if ((int)$subject[subject::active] !== subject::active_on)
                    continue;

                if ((int)$subject[subject::locked] === subject::locked_on)
                    continue;

                $found = false;

                if (empty($subject[subject::parent])) {
                    // check if exists
                    foreach ($top_list as $s_value) {
                        if ($s_value[subject::id] === $subject[subject::id]) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found)
                        continue;

                    $top_list[] = $subject;
                } else {
                    // check if exists
                    foreach ($child_list as $s_value) {
                        if ($s_value[subject::id] === $subject[subject::id]) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found)
                        continue;

                    $child_list[] = $subject;
                }
            }
        }

        $subject_list = array();

        // fill all top subjects with children in result list

        foreach ($top_list as $s_key => $s_value) {
            $children = subject::get_list_children($fw->db, (int)$s_value[subject::id]);
            if (empty($children))
                continue;

            $s_value[subject::children] = array();

            foreach ($children as $child) {
                if ((int)$child[subject::locked] === subject::locked_on)
                    continue;

                $s_value[subject::children][] = $child;
            }

            if (!empty($s_value[subject::children]))
                $subject_list[$s_key] = $s_value;
        }

        // check all assigned childs if exists in result list
        // if not then add parent

        foreach ($child_list as $child) {
            $child_parent_id = (int)$child[subject::parent][subject::id];

            $found = false;

            foreach ($subject_list as $s_key => $s_value) {
                if ((int)$s_value[subject::id] === $child_parent_id) {

                    $found_child = false;

                    $children = $s_value[subject::children];
                    foreach ($children as $c_value) {
                        if ((int)$c_value[subject::id] === (int)$child[subject::id]) {
                            $found_child = true;
                            break;
                        }
                    }

                    if (!$found_child)
                        $subject_list[$s_key][subject::children][] = $child;

                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $parent = service::get_subject($fw, $child_parent_id);
                if (empty($parent))
                    continue;

                if ((int)$parent[subject::active] !== subject::active_on)
                    continue;

                if ((int)$parent[subject::locked] === subject::locked_on)
                    continue;

                $parent[subject::children] = array();
                $parent[subject::children][] = $child;

                $subject_list[] = $parent;
            }
        }

        if (session::get_sort($fw)) {
            usort($subject_list, function ($a, $b) {
                return $a[subject::name] <=> $b[subject::name];
            });

            foreach ($subject_list as $s_key => $s_value) {
                if (isset($s_value[subject::children]) && !empty($s_value[subject::children])) {
                    usort($subject_list[$s_key][subject::children], function ($c_a, $c_b) {
                        return $c_a[subject::name] <=> $c_b[subject::name];
                    });
                }
            }
        }

        foreach ($subject_list as $s_key => $s_value) {
            $s_amount = 0;

            foreach ($s_value[subject::children] as $c_key => $c_value) {
                $c_amount = \plan\service::count_book($fw, \account\service::get_id($fw), (int)$c_value[subject::id]);

                $subject_list[$s_key][subject::children][$c_key][subject::amount] = $c_amount;

                $s_amount += $c_amount;
            }

            $subject_list[$s_key][subject::amount] = $s_amount;
        }

        $fw->set(subject::list, $subject_list);
    }

    static function check_parent(array &$subject)
    {
        if (empty($subject[subject::parent]))
            return;

        $parent = $subject[subject::parent];

        if ((int)$parent[subject::active] !== subject::active_on)
            return;

        if ((int)$parent[subject::locked] === subject::locked_on)
            $subject[subject::locked] = subject::locked_on;

        if ((int)$parent[subject::exclusive] === subject::exclusive_on)
            $subject[subject::exclusive] = subject::exclusive_on;

        if ((int)$parent[subject::managed] === subject::managed_on)
            $subject[subject::managed] = subject::managed_on;
    }

    // --- query

    static function select(string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? subject::all : $fields) . ' FROM ' . db::prefix() . subject::table . ' ';
    }

    static function new_handle(SQL $sql): string
    {
        while (true) {
            $handle = id::gen_handle();
            if (empty(subject::get_by_handle($sql, $handle)))
                return $handle;
        }

        return '';
    }

    static function count_children(SQL $sql, int $parent): int
    {
        $get = 'SELECT count(*) as total FROM ' . db::prefix() . subject::table . ' WHERE '
            . subject::parent . '=? AND '
            . subject::active . '=' . subject::active_on;

        $result = $sql->exec($get, $parent);

        return empty($result) ? 0 : (int)$result[0]['total'];
    }

    static function count(SQL $sql): int
    {
        $get = 'SELECT count(*) as total FROM ' . db::prefix() . subject::table . ' WHERE ' . subject::active . '=' . subject::active_on;

        $result = $sql->exec($get);

        return empty($result) ? 0 : (int)$result[0]['total'];
    }

    static function get(SQL $sql, int $id, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_handle(SQL $sql, string $handle, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::handle . '=?';
        return base::first($sql->exec($get, $handle));
    }

    static function get_list(SQL $sql, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::active . '=' . subject::active_on;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . subject::name;
        else
            $get .= ' ORDER BY ' . subject::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_top(SQL $sql, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::parent . '=' . subject::parent_none . ' AND ' . subject::active . '=' . subject::active_on;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . subject::name;
        else
            $get .= ' ORDER BY ' . subject::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_top_all(SQL $sql, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::parent . '=' . subject::parent_none;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . subject::name;
        else
            $get .= ' ORDER BY ' . subject::updated . ' DESC';

        return $sql->exec($get);
    }

    static function get_list_children(SQL $sql, int $parent, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::parent . '=? AND ' . subject::active . '=' . subject::active_on;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . subject::name;
        else
            $get .= ' ORDER BY ' . subject::updated . ' DESC';

        return $sql->exec($get, $parent);
    }

    static function get_list_children_all(SQL $sql, int $parent, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::parent . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . subject::name;
        else
            $get .= ' ORDER BY ' . subject::updated . ' DESC';

        return $sql->exec($get, $parent);
    }

    static function get_list_children_inactive(SQL $sql, int $parent, string $fields = ''): array
    {
        $get = subject::select($fields) . 'WHERE ' . subject::parent . '=? AND ' . subject::active . '=' . subject::active_off;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . subject::name;
        else
            $get .= ' ORDER BY ' . subject::updated . ' DESC';

        return $sql->exec($get, $parent);
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
            \event::subject,
            $id,
            $command,
            $event,
            subject::get($fw->db, $id, $fields),
            subject::event_revision
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
        string $icon,
        int $parent,
        int $periods,
        bool $exclusive,
        bool $managed,
        bool $locked,
        bool $active
    ): int {
        $insert = 'INSERT INTO ' . db::prefix() . subject::table . ' ('
            . subject::handle . ','
            . subject::name . ','
            . subject::description . ','
            . subject::link . ','
            . subject::icon . ','
            . subject::parent . ','
            . subject::periods . ','
            . subject::exclusive . ','
            . subject::managed . ','
            . subject::locked . ','
            . subject::active . ','
            . subject::version . ') VALUES (?,?,?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $handle,
            2 => $name,
            3 => $description,
            4 => $link,
            5 => $icon,
            6 => $parent,
            7 => $periods,
            8 => $exclusive,
            9 => $managed,
            10 => $locked,
            11 => $active,
            12 => subject::latest,
        ));

        $subject_id = db::get_last_inserted_id($fw->db);
        subject::event_insert($fw, $subject_id, $command, subject::event_created);

        return $subject_id;
    }

    static function action_update(
        \Base $fw,
        int $command,
        int $subject,
        string $name,
        string $description,
        string $link,
        string $icon,
        int $parent,
        int $periods,
        bool $exclusive,
        bool $managed,
        bool $locked
    ): void {
        $update = 'UPDATE ' . db::prefix() . subject::table . ' SET '
            . subject::name . '=?,'
            . subject::description . '=?,'
            . subject::link . '=?,'
            . subject::icon . '=?,'
            . subject::parent . '=?,'
            . subject::periods . '=?,'
            . subject::exclusive . '=?,'
            . subject::managed . '=?,'
            . subject::locked . '=? WHERE '
            . subject::id . '=?';

        $fw->db->exec($update, array(
            1 => $name,
            2 => $description,
            3 => $link,
            4 => $icon,
            5 => $parent,
            6 => $periods,
            7 => $exclusive,
            8 => $managed,
            9 => $locked,
            10 => $subject,
        ));

        subject::event_insert($fw, $subject, $command, subject::event_updated);
    }

    static function action_delete(\Base $fw, int $command, int $id): void
    {
        subject::event_insert($fw, $id, $command, subject::event_deleted);

        $delete = 'DELETE FROM ' . db::prefix() . subject::table . ' WHERE ' . subject::id . '=?';

        $fw->db->exec($delete, $id);
    }

    static function action_activate(\Base $fw, int $command, int $id): void
    {
        subject::event_insert($fw, $id, $command, subject::event_activated);

        $update = 'UPDATE ' . db::prefix() . subject::table . ' SET ' . subject::active . '=' . subject::active_on .  ' WHERE ' . subject::id . '=?';

        $fw->db->exec($update, $id);
    }

    static function action_deactivate(\Base $fw, int $command, int $id): void
    {
        subject::event_insert($fw, $id, $command, subject::event_deactivated);

        $update = 'UPDATE ' . db::prefix() . subject::table . ' SET ' . subject::active . '=' . subject::active_off . ' WHERE ' . subject::id . '=?';

        $fw->db->exec($update, $id);
    }

    // --- command

    static function command_add(
        \Base $fw,
        subject_data $data
    ): void {
        $command = \cmd::begin($fw, \cmd::subject_add);

        $parent_id = 0;
        $active = true;

        if ($data->parent_handle !== '') {
            $parent = subject::get_by_handle($fw->db, $data->parent_handle, subject::id . ',' . subject::active);
            if (!empty($parent)) {
                $parent_id = (int)$parent[subject::id];
                $active = (bool)$parent[subject::active];
            }
        }

        $handle = subject::new_handle($fw->db);

        subject::action_insert(
            $fw,
            $command,
            $handle,
            $data->name,
            $data->description,
            $data->link,
            $data->icon,
            $parent_id,
            $data->periods,
            $data->exclusive,
            $data->managed,
            $data->locked,
            $active
        );

        \cmd::end($fw, $command);
    }

    static function command_update(\Base $fw, string $handle, subject_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::subject_update);

        $subject = subject::get_by_handle($fw->db, $handle, subject::id . ',' . subject::active);
        if (empty($subject))
            return;

        $subject_id = (int)$subject[subject::id];

        if (($data->parent_handle !== '') && (subject::count_children($fw->db, $subject_id) > 0))
            return;

        $parent_id = 0;
        if ($data->parent_handle !== '') {
            $parent = subject::get_by_handle($fw->db, $data->parent_handle, subject::id);
            if (!empty($parent))
                $parent_id = (int)$parent[subject::id];
        }

        subject::action_update(
            $fw,
            $command,
            $subject_id,
            $data->name,
            $data->description,
            $data->link,
            $data->icon,
            $parent_id,
            $data->periods,
            $data->exclusive,
            $data->managed,
            $data->locked
        );

        \cmd::end($fw, $command);
    }

    static function command_remove(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::subject_remove);

        $subject = subject::get_by_handle($fw->db, $handle, subject::id . ',' . subject::active);
        if (empty($subject))
            return;

        if ((int)$subject[subject::active] === subject::active_on)
            return;

        $subject_id = (int)$subject[subject::id];

        subject::action_delete($fw, $command, $subject_id);

        \cmd::end($fw, $command);
    }

    static function command_archive(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::subject_archive);

        $subject = subject::get_by_handle($fw->db, $handle, subject::id . ',' . subject::active);
        if (empty($subject))
            return;

        if ((int)$subject[subject::active] !== subject::active_on)
            return;

        $subject_id = (int)$subject[subject::id];

        subject::action_deactivate($fw, $command, $subject_id);

        \cmd::end($fw, $command);
    }

    static function command_activate(\Base $fw, string $handle): void
    {
        $command = \cmd::begin($fw, \cmd::subject_activate);

        $subject = subject::get_by_handle($fw->db, $handle, subject::id . ',' . subject::active);
        if (empty($subject))
            return;

        if ((int)$subject[subject::active] === subject::active_on)
            return;

        $subject_id = (int)$subject[subject::id];

        subject::action_activate($fw, $command, $subject_id);

        \cmd::end($fw, $command);
    }
}

class subject_data
{
    public string $name;
    public string $description;
    public string $link;
    public string $icon;

    public string $parent_handle;

    public int $periods;

    public bool $exclusive;
    public bool $managed;
    public bool $locked;

    static function create(\Base $fw): subject_data
    {
        $data = new subject_data();

        if (!$fw->exists('POST.name'))
            return null;

        $data->name = base::trim_fw($fw, 'POST.name', 'max_name_length');
        if (strlen($data->name) === 0)
            return null;

        $data->description = base::trim_fw($fw, 'POST.description', 'max_description_length');
        $data->link = base::trim_fw($fw, 'POST.link', 'max_link_length');
        $data->icon = base::trim_fw($fw, 'POST.icon', 'max_icon_length');

        $data->parent_handle = '';
        if ($fw->exists('POST.parent'))
            $data->parent_handle = $fw->get('POST.parent');

        $data->periods = (int)$fw->get('POST.periods');
        $max_periods = (int)$fw->get('max_periods');
        if ($data->periods < 0)
            $data->periods = 0;
        else if ($data->periods > $max_periods)
            $data->periods = $max_periods;

        $data->exclusive = $fw->exists('POST.exclusive');
        $data->managed = $fw->exists('POST.managed');
        $data->locked = $fw->exists('POST.locked');

        return $data;
    }
}
