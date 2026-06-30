<?php

declare(strict_types=1);

namespace VpnBot\Infrastructure\Runtime;

use Error;
use Exception;

final class ContainerShell
{
    /**
     * @param callable(string): void|null $onError
     */
    public function exec(string $command, string $service = 'wg', bool $wait = true, string $log = '/dev/null', ?callable $onError = null): string
    {
        $data = '';

        try {
            $connection = ssh2_connect($service, 22);
            if (empty($connection)) {
                throw new Exception("no connection to $service: \n$command\n" . var_export($connection, true));
            }

            $auth = ssh2_auth_pubkey_file($connection, 'root', '/ssh/key.pub', '/ssh/key');
            if (empty($auth)) {
                throw new Exception("auth fail: \n$command\n" . var_export($auth, true));
            }

            if (! $wait) {
                $command = "nohup sh -c \"$command 2>&1 | tee -a $log >&3\" 3>/proc/1/fd/1 </dev/null &";
            }

            $stream = ssh2_exec($connection, $command);
            if (empty($stream)) {
                throw new Exception("exec fail: \n$command\n" . var_export($stream, true));
            }

            if ($wait) {
                stream_set_blocking($stream, true);
                while ($buffer = fread($stream, 4096)) {
                    $data .= $buffer;
                }
            } else {
                stream_set_blocking($stream, false);
                usleep(100000);
            }

            fclose($stream);
            ssh2_disconnect($connection);
        } catch (Exception | Error $exception) {
            if ($onError !== null) {
                $onError($exception->getMessage());
            }
        }

        return $data;
    }
}
