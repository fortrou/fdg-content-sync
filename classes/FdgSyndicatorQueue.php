<?php
namespace FdgSync;
class FdgSyndicatorQueue {

    private static $instance = null;
    private $table;

    private function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'sync_queue';

        add_action('sync_queue_worker', [$this, 'run_worker']);
    }

    public static function instance() {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Create queue table
     */
    public function install_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "
            CREATE TABLE {$this->table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                type VARCHAR(50) NOT NULL,
                payload LONGTEXT NOT NULL,
                status ENUM('pending','processing','done','failed') DEFAULT 'pending',
                priority INT NOT NULL DEFAULT 50,
                chain_id BIGINT NULL,
                parent_id BIGINT NULL,
                attempts INT NOT NULL DEFAULT 0,
                scheduled_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                INDEX (status),
                INDEX (scheduled_at),
                INDEX (priority),
                INDEX (chain_id),
                INDEX (parent_id)
            ) $charset_collate;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Add event to queue
     */
    public function enqueue($type, $payload = [], $priority = 50, $chain_id = null, $parent_id = null, $delay_seconds = 0) {
        global $wpdb;

        $wpdb->insert($this->table, [
            'type'         => $type,
            'payload'      => wp_json_encode($payload),
            'status'       => 'pending',
            'priority'     => intval($priority),
            'chain_id'     => $chain_id,
            'parent_id'    => $parent_id,
            'attempts'     => 0,
            'scheduled_at' => gmdate('Y-m-d H:i:s', time() + $delay_seconds),
            'created_at'   => current_time('mysql', true),
            'updated_at'   => current_time('mysql', true),
        ]);

        return $wpdb->insert_id;
    }

    /**
     * Trigger processing (will be called by cron or manually)
     */
    public function trigger_worker() {
        if (!wp_next_scheduled('sync_queue_worker')) {
            wp_schedule_single_event(time() + 100, 'sync_queue_worker');
        }
    }

    /**
     * Main worker loop
     */
    public function run_worker() {
        global $wpdb;

        // Get up to 10 tasks ready for processing
        $tasks = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$this->table}
            WHERE status = 'pending'
              AND scheduled_at <= %s
            ORDER BY priority ASC, id ASC
            LIMIT 10
        ", gmdate('Y-m-d H:i:s')));

        if (empty($tasks)) {
            return;
        }

        foreach ($tasks as $task) {
            $this->process_task($task);
        }
    }

    /**
     * Process single task
     */
    private function process_task($task) {
        global $wpdb;

        // Lock task
        $updated = $wpdb->update(
            $this->table,
            [
                'status'     => 'processing',
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $task->id, 'status' => 'pending']
        );

        if (!$updated) {
            return; // already taken by another worker
        }

        $payload = json_decode($task->payload, true);

        try {
            /**
             * EMPTY HANDLER — You implement processing via your own hooks
             *
             * Example usage:
             * do_action('fdg_sync_queue_handle_' . $task->type, $task, $payload, $this);
             */
            do_action("fdg_sync_queue_handle_{$task->type}", $task, $payload, $this);

            $this->mark_done($task->id);

        } catch (\Exception $e) {
            $this->mark_failed($task);
        }
    }

    /**
     * Mark task completed
     */
    public function mark_done($id) {
        global $wpdb;
        $wpdb->update($this->table, [
            'status'     => 'done',
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id]);
    }

    /**
     * Handle failures + retry mechanism
     */
    public function mark_failed($task) {
        global $wpdb;

        $attempts = $task->attempts + 1;

        // Retry logic: exponential backoff
        if ($attempts <= 5) {
            $delay = pow(2, $attempts) * 30; // 30s, 60s, 120s, etc.

            $wpdb->update($this->table, [
                'attempts'     => $attempts,
                'status'       => 'pending',
                'scheduled_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                'updated_at'   => current_time('mysql', true),
            ], ['id' => $task->id]);

        } else {
            // Mark permanently failed
            $wpdb->update($this->table, [
                'status'     => 'failed',
                'updated_at' => current_time('mysql', true),
            ], ['id' => $task->id]);
        }
    }

    /**
     * Clear old tasks
     */
    public function cleanup($days = 7) {
        global $wpdb;

        $wpdb->query($wpdb->prepare("
            DELETE FROM {$this->table}
            WHERE status IN ('done','failed')
              AND updated_at < %s
        ", gmdate('Y-m-d H:i:s', strtotime("-{$days} days"))));
    }
}
