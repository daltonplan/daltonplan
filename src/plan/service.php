<?php

declare(strict_types=1);

namespace plan;

use core\plan;
use core\team;

use frame\db;
use frame\session;
use frame\time;

class service
{
    static function create_db(\Base $fw, int $plan_id): void
    {
        $prefix = db::plan_prefix($plan_id);

        book::create($fw, $prefix);
        frame::create($fw, $prefix);
        period_team::create($fw, $prefix);
        period::create($fw, $prefix);
        register::create($fw, $prefix);
        week::create($fw, $prefix);
    }

    static function delete_db(\Base $fw, int $plan_id): void
    {
        $prefix = db::plan_prefix($plan_id);

        book::delete($fw, $prefix);
        frame::delete($fw, $prefix);
        period_team::delete($fw, $prefix);
        period::delete($fw, $prefix);
        register::delete($fw, $prefix);
        week::delete($fw, $prefix);
    }

    static function set_list(\Base $fw): void
    {
        if (!\account\service::logged($fw))
            return;

        if (\account\service::student($fw)) {
            team::set_list_only_user($fw, session::get_user_id($fw));
            plan::set_list_only_team_list($fw, $fw->get(team::list));
        } else {
            plan::set_list($fw);
        }
    }

    static function set_current(\Base $fw, array &$plan): void
    {
        // plan.week = current week 
        // plan.current = current period

        $plan_id = (int)$plan[plan::id];

        $plan_time = $plan[plan::time];
        $now = time::get($fw, $plan_time);

        week::update_time($now);

        $current_time = time::to_db_date_time($now);
        $current_date = time::to_db_date($now);

        $current_week = week::get_current($fw->db, $plan_id, $current_date);
        if (empty($current_week))
            return;

        week::format($fw, $current_week);

        $plan[week::table] = $current_week;

        $week_id = (int)$current_week[week::id];

        $current_period = period::get_current($fw->db, $plan_id, $week_id, $current_time);
        if (empty($current_period))
            return;

        period::format($fw, $current_period);

        $plan[period::current] = $current_period;

        if (!$fw->exists(period::current)) {
            $current_period[plan::table] = $plan;
            $fw->set(period::current, $current_period);
        }
    }

    static function set_current_list(\Base $fw): void
    {
        $plan_list = $fw->get(plan::list);
        if (empty($plan_list))
            return;

        $updated = array();

        foreach ($plan_list as $plan) {
            service::set_current($fw, $plan);

            $updated[$plan[plan::id]] = $plan;
        }

        $fw->set(plan::list, $updated);
    }

    static function get_current(\Base $fw, int $plan_id): array
    {
        $plan_list_id = plan::list . '.' . $plan_id;
        if (!$fw->exists($plan_list_id))
            return array();

        $plan = $fw->get($plan_list_id);
        if (empty($plan) || !isset($plan[period::current]))
            return array();

        return $plan[period::current];
    }

    static function count_book(\Base $fw, int $user_id, int $subject_id): int
    {
        $plan_list = $fw->get(plan::list);
        if (empty($plan_list))
            return 0;

        $amount = 0;

        foreach ($plan_list as $plan) {
            if (!isset($plan[week::table]))
                continue;

            $week_id = (int)$plan[period::week][week::id];

            $amount += book::count_user_subject($fw->db, (int)$plan[plan::id], $week_id, $user_id, $subject_id);
        }

        return $amount;
    }

    static function count_reports(\Base $fw, int $user_id): int
    {
        $plan_list = $fw->get(plan::list);
        if (empty($plan_list))
            return 0;

        $amount = 0;

        $latest_report_list = array();
        $max_latest = 2;

        foreach ($plan_list as $plan) {
            $plan_id = (int)$plan[plan::id];

            $plan_time = $plan[plan::time];
            $now = time::get($fw, $plan_time);

            week::update_time($now);

            $current_time = time::to_db_date_time($now);

            $week_list = week::get_list_latest($fw->db, $plan_id);
            foreach ($week_list as $week) {
                $week_id = (int)$week[week::id];

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

                    if ($amount < $max_latest) {
                        $report = array();

                        $report[plan::table] = $plan;

                        period::format($fw, $period);

                        $week_day = time::get_week_day($period[period::start]);
                        $report[week::day] = $week_day;
                        $report[period::start_date] = $period[period::start_date];

                        $report[period::table] = $period;
                        $report[book::table] = $book;

                        $latest_report_list[$amount] = $report;
                    }

                    $amount++;
                }
            }
        }

        $fw->set('latest_report_list', $latest_report_list);

        return $amount;
    }

    static function set_next_book_list(\Base $fw, int $user_id): void
    {
        $plan_list = $fw->get(plan::list);
        if (empty($plan_list))
            return;

        $amount = 0;
        $max_next = 2;

        $next_book_list = array();

        foreach ($plan_list as $plan) {
            $plan_id = (int)$plan[plan::id];

            if (!isset($plan[week::table]))
                continue;

            if (!empty($plan[period::current]) && !$fw->exists(period::current)) {
                $plan[period::current][plan::table] = $plan;
                $fw->set(period::current, $plan[period::current]);
            }

            $week_id = (int)$plan[week::table][week::id];

            $plan_time = $plan[plan::time];
            $now = time::get($fw, $plan_time);

            week::update_time($now);

            $current_time = time::to_db_date_time($now);

            $period_list = period::get_list_by_week_after_max($fw->db, $plan_id, $week_id, $current_time, $max_next);
            foreach ($period_list as $period) {
                if ($amount >= $max_next)
                    break;

                $period_id = (int)$period[period::id];

                $book = book::get_by_period_user($fw->db, $plan_id, $period_id, $user_id);
                if (!empty($book)) {
                    $book_subject_id = (int)$book[book::subject];
                    $book[book::subject] = \core\service::get_subject($fw, $book_subject_id);

                    $book_lab_id = (int)$book[book::lab];
                    $book[book::lab] = \core\service::get_lab($fw, $book_lab_id);
                }

                $report = array();

                $report[plan::table] = $plan;

                period::format($fw, $period);

                $week_day = time::get_week_day($period[period::start]);
                $report[week::day] = $week_day;
                $report[period::start_date] = $period[period::start_date];

                $report[period::table] = $period;
                $report[book::table] = $book;

                $next_book_list[$amount] = $report;

                $amount++;
            }
        }

        $fw->set(book::list, $next_book_list);
    }
}
