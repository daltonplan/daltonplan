<?php

declare(strict_types=1);

namespace plan;

use account\user;
use core\lab_subject;
use core\lab_team;
use core\lab;
use core\plan_team;
use core\plan;
use core\subject;
use core\team_subject;
use core\team_user;
use core\team;

use DB\SQL;

use frame\base;
use frame\db;
use frame\time;
use frame\session;

abstract class book
{
    const latest = 0;
    const table = 'book';
    const list = 'book_list';
    const report = 'report';
    const report_list = 'report_list';

    const id = db::id;
    const user = 'user';
    const period = 'period';
    const lab = 'lab';
    const subject = 'subject';
    const week = 'week';
    const description = 'description';
    const link = 'link';
    const review = 'review';
    const rating = 'rating';
    const present = 'present';
    const present_time = 'present_time';
    const excluded = 'excluded';
    const blocked = 'blocked';
    const version = db::version;
    const created = db::created;
    const updated = db::updated;

    // rating
    const rating_none                   =  0;
    const rating_made_good_progress     =  1;
    const rating_better_than_expected   =  2;
    const rating_could_go_better        = -1;

    // present
    const present_off     = 0;
    const present_on      = 1;
    const present_excused = 2;
    const present_free    = 3;

    // excluded
    const excluded_off    = 0;
    const excluded_on     = 1;

    // blocked
    const blocked_off     = 0;
    const blocked_on      = 1;

    const all = book::id . ','
        . book::user  . ','
        . book::period . ','
        . book::lab . ','
        . book::subject . ','
        . book::week . ','
        . book::description . ','
        . book::link . ','
        . book::review . ','
        . book::rating . ','
        . book::present . ','
        . book::present_time . ','
        . book::excluded . ','
        . book::blocked . ','
        . book::version . ','
        . book::created . ','
        . book::updated;

    static function create(\Base $fw, string $prefix): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $prefix . book::table . '` (
                `' . book::id . '` int(11) NOT NULL AUTO_INCREMENT,
                `' . book::user . '` int(11) NOT NULL,
                `' . book::period . '` int(11) NOT NULL,
                `' . book::lab . '` int(11) NOT NULL,
                `' . book::subject . '` int(11) NOT NULL,
                `' . book::week . '` int(11) NOT NULL,
                `' . book::description . '` varchar(' . $fw->get('max_description_length') . ') NOT NULL,
                `' . book::link . '` varchar(' . $fw->get('max_link_length') . ') NOT NULL,
                `' . book::review . '` varchar(' . $fw->get('max_review_length') . ') NOT NULL,
                `' . book::rating . '` int(11) NOT NULL DEFAULT 0,
                `' . book::present . '` int(11) NOT NULL DEFAULT 0,
                `' . book::present_time . '` timestamp NULL DEFAULT NULL,
                `' . book::excluded . '` int(11) NOT NULL DEFAULT 0,
                `' . book::blocked . '` int(11) NOT NULL DEFAULT 0,
                `' . book::version . '` int(11) NOT NULL DEFAULT ' . book::latest . ',
                `' . book::created . '` timestamp NOT NULL DEFAULT current_timestamp(),
                `' . book::updated . '` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`' . book::id . '`) USING BTREE,
                UNIQUE KEY `' . book::user . '` (`' . book::user . '`,`' . book::period . '`,`' . book::lab . '`,`' . book::subject . '`) USING BTREE,
                KEY `' . book::week . '` (`' . book::week . '`) USING BTREE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;';

        $fw->db->exec($sql);
    }

    static function delete(\Base $fw, string $prefix): void
    {
        $sql = 'DROP TABLE `' . $prefix . book::table . '`;';
        $fw->db->exec($sql);
    }

    // ---

    const result_failed              =  0;
    const result_ok                  =  1;
    const result_book_present        =  2;
    const result_book_blocked        =  3;
    const result_no_team             = -1;
    const result_no_subject          = -2;
    const result_no_lab              = -3;
    const result_already_expired     = -4;
    const result_too_late            = -5;
    const result_blocked             = -6;

    static function set(\Base $fw, int $user_id, string $plan_handle, string $period_handle): int
    {
        // check user

        $user = \account\service::get_user($fw, $user_id);
        if (empty($user))
            return book::result_failed;

        if ((int)$user[user::active] !== user::active_on)
            return book::result_failed;

        $user_role = (int)$user[user::role];

        $user_student = (session::get_user_role($fw) === user::role_student) || ($user_role === user::role_student);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle);
        if (empty($plan))
            return book::result_failed;

        if ((int)$plan[plan::active] !== plan::active_on)
            return book::result_failed;

        $plan_id = (int)$plan[plan::id];

        if (\account\service::student($fw) && !plan::visible($fw, $plan_id))
            return book::result_failed;

        // check period

        $period = period::get_by_handle($fw->db, $plan_id, $period_handle);
        if (empty($period))
            return book::result_failed;

        period::format($fw, $period);

        $fw->set(period::table, $period);

        $period_id = (int)$period[period::id];

        time::set_week_day($fw, $period[period::start]);

        $plan_time = $plan[plan::time];
        $now = time::get($fw, $plan_time);

        $period_end = strtotime($period[period::end]);

        $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
        if (!empty($book)) {
            $book_subject_id = (int)$book[book::subject];
            $book[book::subject] = \core\service::get_subject($fw, $book_subject_id);

            $book_lab_id = (int)$book[book::lab];
            $book[book::lab] = \core\service::get_lab($fw, $book_lab_id);
        }

        $fw->set(book::table, $book);

        if (session::get_user_role($fw) === user::role_student) {
            if ((int)$period[period::blocked] === period::blocked_on)
                return book::result_blocked;

            $is_current = false;

            if (!empty($book)) {
                $current = service::get_current($fw, $plan_id);
                $is_current = !empty($current);
                if ($is_current && ((int)$current[period::id] == $period_id)) {
                    $fw->set(period::current, true);
                } else {
                    if ($period_end < $now)
                        $fw->set(period::previous, true);
                    else
                        $fw->set(period::next, true);
                }

                if ((int)$book[book::present] !== book::present_off)
                    return book::result_book_present;

                if ((int)$book[book::blocked] === book::blocked_on)
                    return book::result_book_blocked;
            }

            if ($period_end < $now)
                return book::result_already_expired;

            if (!book::can_book($fw, $plan_id, $user_id, $period, $now)) {
                if ($is_current && ((int)$current[period::id] == $period_id) && !empty($book) && ((int)$book[book::present] === book::present_off))
                    return book::result_too_late;

                return book::result_failed;
            }
        } else { // role_coach
            if (!empty($book)) {
                $current = service::get_current($fw, $plan_id);
                $is_current = !empty($current);
                if ($is_current && ((int)$current[period::id] == $period_id)) {
                    $fw->set(period::current, true);
                } else {
                    if ($period_end < $now)
                        $fw->set(period::previous, true);
                    else
                        $fw->set(period::next, true);
                }

                if ((int)$book[book::present] !== book::present_off)
                    return book::result_book_present;
            }
        }

        // check team

        $team_user_list = team_user::get_list_by_user($fw->db, $user_id, team_user::team);
        if (empty($team_user_list))
            return book::result_no_team;

        $all_team_list = array();

        $plan_team_list = plan_team::get_list_by_plan($fw->db, $plan_id, plan_team::team);
        if (empty($plan_team_list))
            return book::result_no_team;

        foreach ($plan_team_list as $plan_team) {
            $pt_team_id = (int)$plan_team[plan_team::team];

            foreach ($team_user_list as $team_user) {
                $tu_team_id = (int)$team_user[team_user::team];
                if ($tu_team_id === $pt_team_id) {
                    $all_team_list[] = $tu_team_id;
                    break;
                }
            }
        }

        if (empty($all_team_list))
            return book::result_no_team;

        $team_list = array();

        foreach ($all_team_list as $t_value) {
            $team_id = (int)$t_value;
            $team = \core\service::get_team($fw, $team_id);
            if (empty($team))
                continue;

            if ((int)$team[team::active] !== team::active_on)
                continue;

            $team_list[] = $team;
        }

        if (empty($team_list))
            return book::result_no_team;

        // check commit

        $commit_list = array();

        $period_team_list = period_team::get_list_by_period($fw->db, $plan_id, $period_id);
        foreach ($period_team_list as $period_team) {
            $pt_team_id = (int)$period_team[period_team::team];

            foreach ($team_list as $team) {
                $team_id = (int)$team[team::id];

                if ($team_id === $pt_team_id)
                    $commit_list[] = $period_team;
            }
        }

        if (!empty($commit_list)) {
            $lab_list = array();

            foreach ($commit_list as $period_team) {
                $lab_id = (int)$period_team[period_team::lab];

                if (!isset($lab_list[$lab_id])) {
                    $lab = \core\service::get_lab($fw, $lab_id);
                    if (empty($lab))
                        continue;

                    if ((int)$lab[lab::active] !== lab::active_on)
                        continue;

                    $amount = 0;

                    $capacity = (int)$lab[lab::capacity];
                    if ($capacity !== 0) {
                        $book_list = book::get_list_by_period_lab_non_excluded($fw->db, $plan_id, $period_id, $lab_id, book::user);

                        $amount = sizeof($book_list);

                        foreach ($book_list as $b_value) {
                            if (!user::check_active($fw->db, (int)$b_value[book::user]))
                                $amount = $amount - 1;
                        }

                        if ($amount >= $capacity)
                            continue;
                    };

                    $lab[user::amount] = $amount;

                    $lab[subject::list] = array();

                    $lab_list[$lab_id] = $lab;
                }

                $subject_id = (int)$period_team[period_team::subject];

                $subject = \core\service::get_subject($fw, $subject_id);
                if (empty($subject))
                    continue;

                if ((int)$subject[subject::active] !== subject::active_on)
                    continue;

                if (empty($subject[subject::parent]))
                    continue;

                if ((int)$subject[subject::parent][subject::active] !== subject::active_on)
                    continue;

                $lab_list[$lab_id][subject::list][$subject_id] = $subject;
            }

            if (empty($lab_list))
                return book::result_no_lab;

            $checked_lab_list = array();

            foreach ($lab_list as $lab) {
                if (empty($lab[subject::list]))
                    continue;

                $checked_lab_list[(int)$lab[lab::id]] = $lab;
            }

            if (empty($checked_lab_list))
                return book::result_no_subject;

            if (session::get_sort($fw)) {
                foreach ($checked_lab_list as $l_key => $l_value) {
                    usort($checked_lab_list[$l_key][subject::list], function ($a, $b) {
                        return $a[subject::name] <=> $b[subject::name];
                    });

                    usort($checked_lab_list[$l_key][subject::list], function ($a, $b) {
                        return $a[subject::parent][subject::name] <=> $b[subject::parent][subject::name];
                    });
                }
            }

            $fw->set(lab::list, $checked_lab_list);

            return book::result_ok;
        }

        // check subject

        $all_subject_list = array();

        foreach ($team_list as $t_value) {
            $team_id = (int)$t_value[team::id];

            $team_subject_list = team_subject::get_list_by_team($fw->db, $team_id, team_subject::subject);
            foreach ($team_subject_list as $ts_value) {
                $all_subject_list[] = (int)$ts_value[team_subject::subject];
            }
        }

        $subject_list = array();

        foreach ($all_subject_list as $s_value) {
            $subject_id = (int)$s_value;

            $subject = \core\service::get_subject($fw, $subject_id);
            if (empty($subject))
                continue;

            if ((int)$subject[team::active] !== team::active_on)
                continue;

            if (((int)$subject[subject::locked] === subject::locked_on) && $user_student)
                continue;

            if ((int)$subject[subject::exclusive] === subject::exclusive_on)
                continue;

            if (empty($subject[subject::parent])) {
                $children = subject::get_list_children($fw->db, $subject_id, subject::id . ','
                    . subject::handle . ','
                    . subject::name . ','
                    . subject::icon . ','
                    . subject::exclusive . ','
                    . subject::locked . ','
                    . subject::managed);

                foreach ($children as $c_value) {
                    if (((int)$c_value[subject::locked] === subject::locked_on) && $user_student)
                        continue;

                    if ((int)$c_value[subject::exclusive] === subject::exclusive_on)
                        continue;

                    if ((int)$subject[subject::managed] === subject::managed_on)
                        $c_value[subject::managed] = subject::managed_on;

                    $child_id = (int)$c_value[subject::id];

                    $c_value[subject::parent] = $subject;

                    $subject_list[$child_id] = $c_value;
                }
            } else {
                if ((int)$subject[subject::parent][subject::active] !== subject::active_on)
                    continue;

                $subject_list[$subject_id] = $subject;
            }
        }

        if (empty($subject_list))
            return book::result_no_subject;

        // check lab

        $lab_list = array();

        $all_lab_list = lab::get_list(
            $fw->db,
            lab::id . ','
                . lab::handle . ','
                . lab::name . ','
                . lab::icon . ','
                . lab::capacity . ','
                . lab::locked . ','
                . lab::managed
        );

        foreach ($all_lab_list as $l_key => $l_value) {
            if ((int)$l_value[lab::locked] === lab::locked_on)
                continue;

            $lab_id = (int)$l_value[lab::id];

            $has_coach = false; {
                $book_list = book::get_list_by_period_lab_excluded($fw->db, $plan_id, $period_id, $lab_id, book::user);

                foreach ($book_list as $b_value) {
                    if (user::check_active($fw->db, (int)$b_value[book::user])) {
                        $has_coach = true;
                        break;
                    }
                }
            }

            if (((int)$l_value[lab::managed] === lab::managed_on) && !$has_coach)
                continue;

            $lab_team_list = lab_team::get_list_by_lab($fw->db, $lab_id, lab_team::team);
            if (!empty($lab_team_list)) {
                $found = false;

                foreach ($lab_team_list as $lt_value) {
                    $lt_team_id = (int)$lt_value[lab_team::team];

                    foreach ($team_list as $t_value) {
                        $team_id = (int)$t_value[team::id];
                        if ($team_id === $lt_team_id) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found)
                        break;
                }

                if (!$found)
                    continue;
            }

            $amount = 0;

            $capacity = (int)$l_value[lab::capacity];
            if ($capacity !== 0) {
                $book_list = book::get_list_by_period_lab_non_excluded($fw->db, $plan_id, $period_id, $lab_id, book::user);

                $amount = sizeof($book_list);

                foreach ($book_list as $b_value) {
                    if (!user::check_active($fw->db, (int)$b_value[book::user]))
                        $amount = $amount - 1;
                }

                if ($amount >= $capacity)
                    continue;
            };

            $l_value[user::amount] = $amount;

            $book_subject_list = array();

            $lab_subject_list = lab_subject::get_list_by_lab($fw->db, $lab_id, lab_subject::subject);
            if (!empty($lab_subject_list)) {
                foreach ($lab_subject_list as $ls_value) {
                    $ls_subject_id = (int)$ls_value[lab_subject::subject];

                    $ls_subject = \core\service::get_subject($fw, $ls_subject_id);
                    if (empty($ls_subject))
                        continue;

                    if ((int)$ls_subject[subject::active] !== subject::active_on)
                        continue;

                    if (((int)$ls_subject[subject::locked] === subject::locked_on) && $user_student)
                        continue;

                    if ((int)$ls_subject[subject::exclusive] === subject::exclusive_on)
                        continue;

                    if (((int)$ls_subject[subject::managed] === subject::managed_on) && !$has_coach)
                        continue;

                    if (empty($ls_subject[subject::parent])) {
                        $children = subject::get_list_children($fw->db, $ls_subject_id, subject::id . ','
                            . subject::handle . ','
                            . subject::name . ','
                            . subject::icon . ','
                            . subject::exclusive . ','
                            . subject::locked . ','
                            . subject::managed);

                        foreach ($children as $c_value) {
                            if (((int)$c_value[subject::locked] === subject::locked_on) && $user_student)
                                continue;

                            if ((int)$c_value[subject::exclusive] === subject::exclusive_on)
                                continue;

                            $child_id = (int)$c_value[subject::id];

                            foreach ($subject_list as $s_value) {
                                if (((int)$s_value[subject::managed] === subject::managed_on) && !$has_coach)
                                    continue;

                                $subject_id = (int)$s_value[subject::id];

                                if ($subject_id === $child_id) {
                                    $book_subject_list[$subject_id] = $s_value;
                                    break;
                                }
                            }
                        }
                    } else {
                        foreach ($subject_list as $s_value) {
                            if (((int)$s_value[subject::managed] === subject::managed_on) && !$has_coach)
                                continue;

                            $subject_id = (int)$s_value[subject::id];

                            if ($subject_id === $ls_subject_id) {
                                $book_subject_list[$subject_id] = $s_value;
                                break;
                            }
                        }
                    }
                }
            } else {
                foreach ($subject_list as $s_value) {
                    if (((int)$s_value[subject::managed] === subject::managed_on) && !$has_coach)
                        continue;

                    $subject_id = (int)$s_value[subject::id];

                    $book_subject_list[$subject_id] = $s_value;
                }
            }

            if (empty($book_subject_list))
                continue;

            $l_value[subject::list] = $book_subject_list;

            $lab_list[$l_key] = $l_value;
        }

        if (empty($lab_list))
            return book::result_no_lab;

        if (session::get_sort($fw)) {
            foreach ($lab_list as $l_key => $l_value) {
                usort($lab_list[$l_key][subject::list], function ($a, $b) {
                    return $a[subject::name] <=> $b[subject::name];
                });

                usort($lab_list[$l_key][subject::list], function ($a, $b) {
                    return $a[subject::parent][subject::name] <=> $b[subject::parent][subject::name];
                });
            }
        }

        $fw->set(lab::list, $lab_list);

        return book::result_ok;
    }

    static function can_book(\Base $fw, int $plan_id, int $user_id, array $period, int $time): bool
    {
        $register = false;

        $student = \account\service::student($fw);

        $user_register = register::get_by_user($fw->db, $plan_id, $user_id);
        if (!empty($user_register)) {
            $register_time = strtotime($user_register[register::register]);
            if ($time < $register_time)
                $register = true;
        }

        if ($register) {
            if (strtotime($period[period::end]) < $time)
                return false;
        } else {
            $current = service::get_current($fw, $plan_id);
            $is_current = !empty($current);

            if (!$is_current && $student)
                return false;

            if ($is_current && $student) {
                $exchange = $current[period::exchange];
                $start = strtotime($current[period::start]);

                $addition = "+" . $exchange . " minutes";

                $limit = strtotime($addition, $start);

                if ($time > $limit) {
                    return false;
                }

                if ((int)$current[period::id] !== (int)$period[period::id]) {
                    if ((int)$current[period::register] === period::register_off)
                        return false;

                    if (strtotime($period[period::start]) < strtotime($current[period::start]))
                        return false;
                }
            }
        }

        return true;
    }

    // --- query

    static function select(int $plan, string $fields): string
    {
        return 'SELECT ' . (($fields === '') ? book::all : $fields) . ' FROM ' . db::plan_prefix($plan) . book::table . ' ';
    }

    static function count_user_subject(SQL $sql, int $plan, int $week, int $user, int $subject): int
    {
        $get = 'SELECT count(*) as total FROM ' . db::plan_prefix($plan) . book::table . ' WHERE ' . book::user . '=? AND ' . book::subject . '=? AND ' . book::week . '=?';

        $result = $sql->exec($get, array(
            1 => $user,
            2 => $subject,
            3 => $week,
        ));

        return empty($result) ? 0 : (int)$result[0]['total'];
    }

    static function get(SQL $sql, int $plan, int $id, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::id . '=?';
        return base::first($sql->exec($get, $id));
    }

    static function get_by_period_user(SQL $sql, int $plan, int $period, int $user, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=? AND ' . book::user . '=?';
        return base::first($sql->exec($get, array(
            1 => $period,
            2 => $user,
        )));
    }

    static function get_by_period_user_lab(SQL $sql, int $plan, int $period, int $user, int $lab, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=? AND ' . book::user . '=? AND ' . book::lab . '=?';
        return base::first($sql->exec($get, array(
            1 => $period,
            2 => $user,
            3 => $lab,
        )));
    }

    static function get_list_by_period(SQL $sql, int $plan, int $period, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . book::created . ' DESC';
        else
            $get .= ' ORDER BY ' . book::updated . ' DESC';

        return $sql->exec($get, $period);
    }

    static function get_list_by_period_lab(SQL $sql, int $plan, int $period, int $lab, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=? AND ' . book::lab . '=?';

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . book::created;
        else
            $get .= ' ORDER BY ' . book::updated . ' DESC';

        return $sql->exec($get, array(
            1 => $period,
            2 => $lab,
        ));
    }

    static function get_list_by_period_excluded(SQL $sql, int $plan, int $period, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=? AND ' . book::excluded . '=' . book::excluded_on;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . book::created;
        else
            $get .= ' ORDER BY ' . book::updated . ' DESC';

        return $sql->exec($get, $period);
    }

    static function get_list_by_period_lab_excluded(SQL $sql, int $plan, int $period, int $lab, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=? AND ' . book::lab . '=? AND ' . book::excluded . '=' . book::excluded_on;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . book::created;
        else
            $get .= ' ORDER BY ' . book::updated . ' DESC';

        return $sql->exec($get, array(
            1 => $period,
            2 => $lab,
        ));
    }

    static function get_list_by_period_lab_non_excluded(SQL $sql, int $plan, int $period, int $lab, string $fields = ''): array
    {
        $get = book::select($plan, $fields) . 'WHERE ' . book::period . '=? AND ' . book::lab . '=? AND ' . book::excluded . '=' . book::excluded_off;

        if (session::get_sort_fw())
            $get .= ' ORDER BY ' . book::created;
        else
            $get .= ' ORDER BY ' . book::updated . ' DESC';

        return $sql->exec($get, array(
            1 => $period,
            2 => $lab,
        ));
    }

    // --- event

    const event_revision = 0;

    const event_created         = 1;
    const event_updated         = 2;
    const event_deleted         = 3;
    const event_present_updated = 4;
    const event_blocked_updated = 5;
    const event_report_updated  = 6;

    static function event_insert(\Base $fw, int $plan_id, int $id, int $command, int $event, string $fields = ''): void
    {
        \domain\event::insert(
            $fw,
            \event::book,
            $id,
            $command,
            $event,
            book::get($fw->db, $plan_id, $id, $fields),
            book::event_revision
        );
    }

    // --- action

    static function action_insert(
        \Base $fw,
        int $command,
        int $plan,
        int $user,
        int $period,
        int $lab,
        int $subject,
        int $week,
        int $excluded,
        int $blocked
    ): int {
        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . book::table . ' ('
            . book::user . ','
            . book::period . ','
            . book::lab . ','
            . book::subject . ','
            . book::week . ','
            . book::excluded . ','
            . book::blocked . ','
            . book::version . ') VALUES (?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $user,
            2 => $period,
            3 => $lab,
            4 => $subject,
            5 => $week,
            6 => $excluded,
            7 => $blocked,
            8 => book::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        book::event_insert($fw, $plan, $id, $command, book::event_created);

        return $id;
    }

    static function action_insert_all(
        \Base $fw,
        int $command,
        int $plan,
        int $user,
        int $period,
        int $lab,
        int $subject,
        int $week,
        int $present,
        string|null $present_time,
        int $excluded,
        int $blocked,
        string $description,
        string $review,
        int $rating
    ): int {
        $insert = 'INSERT INTO ' . db::plan_prefix($plan) . book::table . ' ('
            . book::user . ','
            . book::period . ','
            . book::lab . ','
            . book::subject . ','
            . book::week . ','
            . book::present . ','
            . book::present_time . ','
            . book::excluded . ','
            . book::blocked . ','
            . book::description . ','
            . book::review . ','
            . book::rating . ','
            . book::version . ') VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)';

        $fw->db->exec($insert, array(
            1 => $user,
            2 => $period,
            3 => $lab,
            4 => $subject,
            5 => $week,
            6 => $present,
            7 => $present_time,
            8 => $excluded,
            9 => $blocked,
            10 => $description,
            11 => $review,
            12 => $rating,
            13 => book::latest,
        ));

        $id = db::get_last_inserted_id($fw->db);
        book::event_insert($fw, $plan, $id, $command, book::event_created);

        return $id;
    }

    static function action_update_blocked(
        \Base $fw,
        int $command,
        int $plan,
        int $book,
        int $blocked
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . book::table . ' SET '
            . book::blocked . '=? WHERE '
            . book::id . '=?';

        $fw->db->exec($update, array(
            1 => $blocked,
            2 => $book,
        ));

        book::event_insert($fw, $plan, $book, $command, book::event_blocked_updated, book::blocked);
    }

    static function action_update_report(
        \Base $fw,
        int $command,
        int $plan,
        int $book,
        string $description,
        string $review,
        int $rating
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . book::table . ' SET '
            . book::description . '=?, '
            . book::review . '=?, '
            . book::rating . '=? WHERE '
            . book::id . '=?';

        $fw->db->exec($update, array(
            1 => $description,
            2 => $review,
            3 => $rating,
            4 => $book,
        ));

        book::event_insert($fw, $plan, $book, $command, book::event_report_updated, book::description . ',' . book::review . ',' . book::rating);
    }

    static function action_update_present(
        \Base $fw,
        int $command,
        int $plan,
        int $book,
        int $present
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . book::table . ' SET '
            . book::present . '=? WHERE '
            . book::id . '=?';

        $fw->db->exec($update, array(
            1 => $present,
            2 => $book,
        ));

        book::event_insert($fw, $plan, $book, $command, book::event_present_updated, book::present);
    }

    static function action_update_present_time(
        \Base $fw,
        int $command,
        int $plan,
        int $book,
        int $present
    ): void {
        $update = 'UPDATE ' . db::plan_prefix($plan) . book::table . ' SET '
            . book::present . '=?, '
            . 'present_time = CURRENT_TIMESTAMP() WHERE '
            . book::id . '=?';

        $fw->db->exec($update, array(
            1 => $present,
            2 => $book,
        ));

        book::event_insert($fw, $plan, $book, $command, book::event_present_updated, book::present . ',' . book::present_time);
    }

    static function action_delete(\Base $fw, int $command, int $plan, int $book): void
    {
        book::event_insert($fw, $plan, $book, $command, book::event_deleted);

        $delete = 'DELETE FROM ' . db::plan_prefix($plan) . book::table . ' WHERE ' . book::id . '=?';
        $fw->db->exec($delete, $book);
    }

    // --- command

    static function command_book(\Base $fw, int $user_id, string $plan_handle, string $period_handle, string $lab_handle, string $subject_handle): void
    {
        $command = \cmd::begin($fw, \cmd::book);

        // check plan

        $plan = plan::get_by_handle($fw->db, $plan_handle, plan::id . ',' . plan::active);
        if (empty($plan))
            return;

        if ((int)$plan[plan::active] !== plan::active_on)
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

        $subject = subject::get_by_handle($fw->db, $subject_handle, subject::id);
        if (empty($subject))
            return;

        $subject_id = (int)$subject[subject::id];

        if (\account\service::student($fw)) {
            $user = \account\service::get_user($fw, \account\service::get_id($fw));
            if (empty($user))
                return;

            if ((int)$user[user::absent] === user::absent_on)
                return;

            if (!book::set($fw, $user_id, $plan_handle, $period_handle))
                return;

            // check 'lab_list'

            $found = false;

            foreach ($fw->get(lab::list) as $l_value) {
                if ((int)$l_value[lab::id] === $lab_id) {
                    foreach ($l_value[subject::list] as $s_value) {
                        if ((int)$s_value[subject::id] === $subject_id) {
                            $found = true;
                            break;
                        }
                    }

                    if ($found)
                        break;
                }
            }

            if (!$found)
                return;
        }

        $result = 0;

        $user = \account\service::get_user($fw, $user_id);
        if (empty($user))
            return;

        $user_role = $user[user::role];

        $excluded = $user_role === user::role_student ? book::excluded_off : book::excluded_on;
        $blocked = book::blocked_off;

        $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
        if (!empty($book)) {
            if ((int)$book[book::present] !== book::present_off)
                return;

            $book_id = (int)$book[book::id];

            book::action_delete($fw, $command, $plan_id, $book_id);

            $result++;

            $old_lab = (int)$book[book::lab];
            $old_subject = (int)$book[book::subject];

            if (($old_lab === $lab_id) && ($old_subject === $subject_id)) {
                \cmd::end_cmd($fw, $command, \cmd::unbook, $result); // change to unbook command
                return;
            }
        }

        book::action_insert($fw, $command, $plan_id, $user_id, $period_id, $lab_id, $subject_id, $week_id, $excluded, $blocked);

        $result++;

        \cmd::end($fw, $command, $result);
    }

    static function command_present(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_present);

        if (!$data->load_book($fw))
            return;

        $present = ((int)$data->book[book::present] === book::present_on) ? book::present_off : book::present_on;

        book::action_update_present($fw, $command, $data->plan_id, $data->book_id, $present);

        \cmd::end($fw, $command);
    }

    static function command_exclused(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_exclused);

        if (!$data->load_book($fw))
            return;

        $present = ((int)$data->book[book::present] === book::present_excused) ? book::present_off : book::present_excused;

        book::action_update_present($fw, $command, $data->plan_id, $data->book_id, $present);

        \cmd::end($fw, $command);
    }

    static function command_free(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_free);

        if (!$data->load_book($fw))
            return;

        $present = ((int)$data->book[book::present] === book::present_free) ? book::present_off : book::present_free;

        book::action_update_present($fw, $command, $data->plan_id, $data->book_id, $present);

        \cmd::end($fw, $command);
    }

    static function command_blocked(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_blocked);

        if (!$data->load_book($fw))
            return;

        $blocked = ((int)$data->book[book::blocked] === book::blocked_off) ? book::present_on : book::blocked_off;

        book::action_update_blocked($fw, $command, $data->plan_id, $data->book_id, $blocked);

        \cmd::end($fw, $command);
    }

    static function command_remove(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_remove);

        if (!$data->load_book($fw))
            return;

        book::action_delete($fw, $command, $data->plan_id, $data->book_id);

        \cmd::end($fw, $command);
    }

    static function command_lab_present(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_lab_present);

        if (!$data->load_book_list($fw))
            return;

        $result = 0;

        foreach ($data->book_list as $book) {
            book::action_update_present($fw, $command, $data->plan_id, (int)$book[book::id], book::present_on);
            $result++;
        }

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_lab_blocked(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_lab_blocked);

        if (!$data->load_book_list($fw))
            return;

        $result = 0;

        foreach ($data->book_list as $book) {
            book::action_update_blocked($fw, $command, $data->plan_id, (int)$book[book::id], book::blocked_on);
            $result++;
        }

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_lab_clear(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_lab_clear);

        if (!$data->load_book_list($fw))
            return;

        $result = 0;

        foreach ($data->book_list as $book) {
            book::action_delete($fw, $command, $data->plan_id, (int)$book[book::id]);
            $result++;
        }

        if ($result > 0)
            \cmd::end($fw, $command, $result);
    }

    static function command_participation(\Base $fw, string $participation): array // 'plan' -> plan.handle, 'period' -> period.handle
    {
        $command = \cmd::begin($fw, \cmd::book_participation);

        $lab = lab::get_by_participation($fw->db, $participation, lab::id . ',' . lab::active);
        if (empty($lab))
            return array();

        if ((int)$lab[lab::active] !== lab::active_on)
            return array();

        $lab_id = (int)$lab[lab::id];

        $user_id = session::get_user_id($fw);

        $user = \account\service::get_user($fw, $user_id);
        if (empty($user))
            return array();

        if ((int)$user[user::absent] === user::absent_on)
            return array();

        $result = 0;
        $result_array = array();

        $plan_list = $fw->get(plan::list);

        foreach ($plan_list as $plan) {
            if (!isset($plan[period::current]))
                continue;

            $current = $plan[period::current];

            $start = strtotime($current[plan::start]);
            $addition = "+" . $current[period::exchange] . " minutes";
            $limit = strtotime($addition, $start);

            $plan_time = $plan[plan::time];
            $now = time::get($fw, $plan_time);
            if ($now > $limit)
                continue;

            $plan_id = (int)$plan[plan::id];
            $period_id = (int)$current[period::id];

            $period = period::get($fw->db, $plan_id, $period_id, period::handle);
            if (empty($period))
                continue;

            $book = book::get_by_period_user_lab($fw->db, $plan_id, $period_id, $user_id, $lab_id, book::id . ',' . book::present);
            if (empty($book))
                continue;

            if ($book[book::present] !== book::present_off)
                continue;

            $book_id = (int)$book[book::id];

            book::action_update_present_time($fw, $command, $plan_id, $book_id, book::present_on);

            $result++;

            $result_array[plan::table] = $plan[plan::handle];
            $result_array[period::table] = $period[period::handle];
        }

        if ($result > 0)
            \cmd::end($fw, $command);

        return $result_array;
    }

    static function command_update_book(\Base $fw, book_data $data): void
    {
        $command = \cmd::begin($fw, \cmd::book_update_report);

        if (!$data->load_internal($fw))
            return;

        $plan_id = $data->plan_id;
        $period_id = $data->period_id;
        $user_id = $data->user_id;

        $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
        if (empty($book))
            return;

        $description = $data->description;

        $review = $book[book::review];
        $rating = (int)$book[book::rating];

        if ($data->report_all) {
            $review = $data->review;
            $rating = $data->rating;
        }

        if (($description === $book[book::description]) && ($review === $book[book::review]) && ($rating === (int)$book[book::rating]))
            return;

        $book_id = (int)$book[book::id];

        book::action_update_report($fw, $command, $plan_id, $book_id, $description, $review, $rating);

        \cmd::end($fw, $command);
    }
}
