<?php

declare(strict_types=1);

namespace plan;

use account\user;
use core\lab;
use core\plan;
use frame\base;

class book_data
{
    public string $plan_handle;
    public string $period_handle;
    public string $lab_handle;
    public string $user_handle;

    public array $plan;
    public array $period;
    public array $lab;
    public array $user;

    public int $plan_id;
    public int $period_id;
    public int $lab_id;
    public int $user_id;

    public array $book;
    public int $book_id;

    public string $description;
    public bool $report_all = false;
    public string $review;
    public int $rating;

    public array $book_list;

    static function create_internal(\Base $fw): book_data
    {
        $data = new book_data();

        $data->plan_handle = base::trim_fw($fw, 'PARAMS.handle', plan::max_handle_length);
        if ((strlen($data->plan_handle) < (int)$fw->get(plan::min_handle_length)))
            return null;

        $data->period_handle = base::trim_fw($fw, 'PARAMS.period', base::max_handle_length);
        if ((strlen($data->period_handle) === 0))
            return null;

        return $data;
    }

    static function create_user(\Base $fw): book_data
    {
        $data = book_data::create_internal($fw);
        if ($data === null)
            return null;

        $data->user_handle = base::trim_fw($fw, 'PARAMS.user', base::max_handle_length);
        if ((strlen($data->user_handle) === 0))
            return null;

        return $data;
    }

    static function create_lab(\Base $fw): book_data
    {
        $data = book_data::create_internal($fw);
        if ($data === null)
            return null;

        $data->lab_handle = base::trim_fw($fw, 'PARAMS.lab', base::max_handle_length);
        if ((strlen($data->lab_handle) === 0))
            return null;

        return $data;
    }

    static function create_report(\Base $fw): book_data
    {
        $data = book_data::create_internal($fw);
        if ($data === null)
            return null;

        $data->description = base::trim_fw($fw, 'POST.description', 'max_description_length');

        if ($fw->exists('POST.review') && ($fw->exists('POST.rating'))) {
            $data->review = base::trim_fw($fw, 'POST.review', 'max_review_length');
            $data->rating = (int)$fw->get('POST.rating');

            $data->report_all = true;
        }

        return $data;
    }

    function load_internal(\Base $fw): bool
    {
        $this->plan = plan::get_by_handle($fw->db, $this->plan_handle, plan::id . ',' . plan::active);
        if (empty($this->plan))
            return false;

        if ((int)$this->plan[plan::active] !== plan::active_on)
            return false;

        $this->plan_id = (int)$this->plan[plan::id];

        $this->period = period::get_by_handle($fw->db, $this->plan_id, $this->period_handle, period::id);
        if (empty($this->period))
            return false;

        $this->period_id = (int)$this->period[period::id];

        return true;
    }

    function load_user(\Base $fw): bool
    {
        if (!$this->load_internal($fw))
            return false;

        $this->user = user::get_by_handle($fw->db, $this->user_handle, user::id);
        if (empty($this->user))
            return false;

        $this->user_id = (int)$this->user[user::id];

        return true;
    }

    function load_lab(\Base $fw): bool
    {
        if (!$this->load_internal($fw))
            return false;

        $this->lab = lab::get_by_handle($fw->db, $this->lab_handle, lab::id);
        if (empty($this->lab))
            return false;

        $this->lab_id = (int)$this->lab[lab::id];

        return true;
    }

    function load_book(\Base $fw): bool
    {
        if (!$this->load_user($fw))
            return false;

        $this->book = book::get_by_period_user($fw->db, $this->plan_id, $this->period_id, $this->user_id);
        if (empty($this->book))
            return false;

        $this->book_id = (int)$this->book[book::id];

        return true;
    }

    function load_book_list(\Base $fw): bool
    {
        if (!$this->load_lab($fw))
            return false;

        $this->book_list = book::get_list_by_period_lab($fw->db, $this->plan_id, $this->period_id, $this->lab_id);
        if (empty($this->book_list))
            return false;

        return true;
    }
}
