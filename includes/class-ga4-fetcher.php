<?php
class CMS_GA4_Fetcher {

    private $web_app_url = 'https://script.google.com/macros/s/AKfycbwyNKy1B3EZmoyHKpYx2hxtfq4dudb9rO_acvPeA4yhMC6vqRO4Q-3LJl2ONUMKyMwOyg/exec'; // ← URL của bạn

    /**
     * Lấy tổng Users + Thời lượng (summary)
     */
    public function get_summary($start = '28daysAgo', $end = 'today') {
        return $this->fetch('summary', $start, $end);
    }

    /**
     * Lấy tổng Users
     */
    public function get_total_users($start = '28daysAgo', $end = 'today') {
        return $this->fetch('users', $start, $end);
    }

    /**
     * Lấy tổng thời lượng (giây)
     */
    public function get_total_duration($start = '28daysAgo', $end = 'today') {
        return $this->fetch('duration', $start, $end);
    }

    /**
     * Lấy top pages
     */
    public function get_top_pages($start = '28daysAgo', $end = 'today') {
        return $this->fetch('pages', $start, $end);
    }

    /**
     * Hàm fetch chung (dùng wp_remote_get để đúng chuẩn WordPress)
     */
    private function fetch($type, $start, $end) {
        $url = add_query_arg([
            'type'      => $type,
            'startDate' => $start,
            'endDate'   => $end,
        ], $this->web_app_url);

        $response = wp_remote_get($url, [
            'timeout'   => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'error', 'message' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }
}