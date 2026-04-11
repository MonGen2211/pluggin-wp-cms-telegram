<?php

if (! defined('ABSPATH')) {
    exit;
}

class CMS_GSC_Fetcher {

    private $web_app_url = 'https://script.google.com/macros/s/AKfycbwfhlv440h239p7whPetTJVlk9V51lLAJIJjWBAsOfNgRg9PwqNhoGyntaFXawULbTJwQ/exec';

    public function get_summary($start = '28daysAgo', $end = 'today') {
        return $this->fetch('summary', $start, $end);
    }

    public function get_by_page($start = '28daysAgo', $end = 'today') {
        return $this->fetch('by_page', $start, $end);
    }

    public function get_by_query($start = '28daysAgo', $end = 'today') {
        return $this->fetch('by_query', $start, $end);
    }

    private function fetch($type, $start, $end) {
        $url = add_query_arg([
            'type'      => $type,
            'startDate' => $start,
            'endDate'   => $end,
        ], $this->web_app_url);

        $response = wp_remote_get($url, [
            'timeout'     => 15,
            'redirection' => 5,
            'sslverify'   => false,
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'error', 'message' => $response->get_error_message()];
        }

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}