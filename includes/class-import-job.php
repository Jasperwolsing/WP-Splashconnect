<?php

/**
 * Beheert import-jobs opgeslagen in wp_options.
 * Een job doorloopt de statussen: pending → processing → complete | error
 */
class STA_Import_Job {

    private string $job_id;

    public function __construct( string $job_id ) {
        $this->job_id = $job_id;
    }

    public static function create(): self {
        $job_id = 'sta_job_' . wp_generate_uuid4();
        $job    = new self( $job_id );
        $job->update( [
            'status'     => 'pending',
            'created_at' => time(),
            'progress'   => 0,
            'message'    => 'Wachten op verwerking...',
            'result'     => null,
            'error'      => null,
        ] );
        return $job;
    }

    public function get_id(): string {
        return $this->job_id;
    }

    public function get(): ?array {
        return get_option( $this->job_id ) ?: null;
    }

    public function update( array $data ): void {
        $current = $this->get() ?? [];
        update_option( $this->job_id, array_merge( $current, $data ), false );
    }

    public function set_processing( string $message = 'PDF wordt verwerkt...' ): void {
        $this->update( [ 'status' => 'processing', 'message' => $message, 'progress' => 10 ] );
    }

    public function set_progress( int $pct, string $message ): void {
        $this->update( [ 'progress' => $pct, 'message' => $message ] );
    }

    public function set_complete( array $result ): void {
        $this->update( [
            'status'   => 'complete',
            'progress' => 100,
            'message'  => 'Import voltooid.',
            'result'   => $result,
        ] );
    }

    public function set_error( string $error ): void {
        $this->update( [
            'status'  => 'error',
            'message' => $error,
            'error'   => $error,
        ] );
    }

    public function delete(): void {
        delete_option( $this->job_id );
    }

    /** Verwijder jobs ouder dan 2 uur */
    public static function cleanup(): void {
        global $wpdb;
        $rows = $wpdb->get_col(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE 'sta_job_%'"
        );
        $cutoff = time() - 7200;
        foreach ( $rows as $name ) {
            $data = get_option( $name );
            if ( is_array( $data ) && ( $data['created_at'] ?? 0 ) < $cutoff ) {
                delete_option( $name );
            }
        }
    }
}
