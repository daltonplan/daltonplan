<?php

declare(strict_types=1);

namespace frame;

class mail
{
    static function send($fw, string $email, string $subject, string $template): void
    {
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=' . $fw->get('ENCODING');
        $headers[] = 'From: ' . $fw->get('DICT.hello') . '@' . $fw->get('HOST');

        $message = \Template::instance()->render('email/' . $template . '.htm', 'text/html');

        mail($email, $subject, $message, implode("\r\n", $headers));
    }

    static function send_verify(\Base $fw, string $email, string $handle, string $subject, string $msg): void
    {
        $limit = strtotime("+" . time::get_request_time($fw), time::get($fw));
        $fw->set('email.valid_time', time::get_format($fw, $limit));

        if ((int)$fw->get('dp_device_info') === 1)
            mail::set_device_info($fw);

        $fw->set('email.handle', $handle);
        $fw->set('email.msg', $msg);

        mail::send($fw, $email, $subject, 'verify');
    }

    static function set_device_info(\Base $fw): void
    {
        $device_info = $fw->get('DICT.unknown');
        $browser = get_browser($fw->get('ag'), true);
        if (!empty($browser))
            $device_info = $browser['browser'] . ' / ' . $browser['platform'];
        $fw->set('email.device_info', $device_info);
    }

    static function send_feedback(\Base $fw, string $description): void
    {
        if ((int)$fw->get('dp_device_info') === 1)
            mail::set_device_info($fw);

        $fw->set('email.handle', session::get_user_handle($fw));
        $fw->set('email.description', $description);

        mail::send($fw, $fw->get('feedback_email'), 'Feedback', 'feedback');
    }
}
