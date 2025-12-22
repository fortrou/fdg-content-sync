<?php
namespace FdgSync;
class FdgFileProcess {
    public function __construct() {}

    public function get_chunk($fileName, $chunkId)
    {
        $file = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/send/' . $fileName;
        $handle = fopen($file, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Cannot open file');
        }

        $offset = ($chunkId - 1) * FDG_CONTENT_SYNDICATOR_CHUNK_SIZE;
        fseek($handle, $offset);
        $chunk = fread($handle, FDG_CONTENT_SYNDICATOR_CHUNK_SIZE);

        fclose($handle);

        return $chunk == '' ? false : $chunk;
    }

    public function put_chunk($fileName, $chunk) {
        global $wp_filesystem;

        if (! $wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        $receiveDirBase = trailingslashit( wp_upload_dir()['basedir'] ) . 'fdg-syndicator-meta/receive/';
        if (!$wp_filesystem->is_dir($receiveDirBase)) {
            $wp_filesystem->mkdir($receiveDirBase);
        }
        $file = pathinfo($fileName);
        $wp_filesystem->touch($receiveDirBase . $file['filename'] . '.tmp');

        return file_put_contents($receiveDirBase . $file['filename'] . '.tmp', base64_decode($chunk));
    }
}