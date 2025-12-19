<?php
namespace FdgSync;

if ( ! defined( 'ABSPATH' ) ) die;
class FdgSyndicatorRequests {

    public $options;

    public function __construct() {
        $this->options = get_option( 'fdg_syndicator_options' );
    }

    public function format_headers()
    {
        $headers = [
            'accept' => 'application/json'
        ];
    }

    public function trigger_search_request($searchTerm, $postType)
    {
        $base = rtrim($this->options['site_url'], '/') . '/wp-json/fdg_syndicator/v1/posts/search/' . $postType . '/' . $searchTerm;
        return $this->request_wrapper($base);
    }

    public function trigger_post_sync_request($data)
    {
        $base = rtrim($this->options['site_url'], '/') . '/wp-json/fdg_syndicator/v1/posts/moderate';
        return $this->request_wrapper($base, $data, 'POST');
    }

    public function request_wrapper($base, $params = [], $method = 'GET')
    {
        $result = wp_remote_request($base, [
            'headers' => self::get_auth_headers($this->options['user_token']),
            'body' => $params,
            'method' => $method
        ]);
        //var_dump($base);
        $formatted = $this->format_reponse($result);
        //var_dump($formatted);
        return $formatted;
    }

    public function format_reponse($response)
    {
        if (!isset($response['body']) || !isset($response['response'])) {
            return false;
        }
        return json_decode($response['body'], true);
    }

    private static function get_auth_headers($authToken = '')
    {
        $authToken = !empty($authToken) ? $authToken : '';

        return [
            'Authorization' => 'Basic ' . base64_encode('test@gmail.com' . ':' . $authToken),
            'X-Referrer' => 'RemoteSyndicatorRequest'
        ];
    }
}