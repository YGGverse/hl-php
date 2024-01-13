<?php

declare(strict_types=1);

namespace Yggverse\Hl\Xash3D;

class Master
{
    private string $_host;
    private int    $_port;
    private int    $_timeout;

    private array  $_errors = [];

    public function __construct(
        string $host,
        int    $port,
        int    $timeout = 5
    )
    {
        $this->_host    = $host;
        $this->_port    = $port;
        $this->_timeout = $timeout;
    }

    private function _fclose(
        mixed $socket
    )
    {
        if (true === is_resource($socket))
        {
            fclose(
                $socket
            );
        }
    }

    public function getServersIPv6(
        int    $limit   = 100,
        string $region  = "\xFF",
        string $host    = "0.0.0.0:0",
        int    $port    = 0,
        string $gamedir = "valve"
    ): ?array
    {
        // Init connection
        $socket = fsockopen(
            "udp://{$this->_host}",
            $this->_port,
            $code,
            $message,
            $this->_timeout
        );

        // Is connected
        if (true === is_resource($socket))
        {
            stream_set_timeout(
                $socket,
                $this->_timeout
            );
        }

        else
        {
            $this->_errors[] = sprintf(
                _('Connection error: %s'),
                $message
            );

            $this->_fclose(
                $socket
            );

            return null;
        }

        // Filter query
        if (false === fwrite($socket, "1{$region}{$host}:{$port}\0\gamedir\t{$gamedir}\0"))
        {
            $this->_errors[] = _('Could not send socket query');

            $this->_fclose(
                $socket
            );

            return null;
        }

        // Skip header
        if (false === fread($socket, 6))
        {
            $this->_errors[] = _('Could not init packet header');

            $this->_fclose(
                $socket
            );

            return null;
        }

        // Get servers
        $servers = [];

        for ($i = 0; $i < $limit; $i++)
        {
            // Get host
            if (false === $host = fread($socket, 16))
            {
                break;
            }

            // Is end of packet
            if (true === str_ends_with(bin2hex($host), bin2hex("\0\0\0\0\0\0")))
            {
                break;
            }

            // Skip invalid host value
            if (false === $host = inet_ntop($host))
            {
                // Shift port bytes
                fread($socket, 2);

                continue;
            }

            // Decode first byte of port
            if (false === $byte1 = fread($socket, 1))
            {
                // Shift port byte
                fread($socket, 1);

                continue;
            }

            // Decode second byte of port
            if (false === $byte2 = fread($socket, 1))
            {
                continue;
            }

            // Calculate port value
            $port = ord($byte1) * 256 + ord($byte2);

            // Validate IPv6 result
            if (
                false !== strpos($host, '.') || // filter_var not always works with mixed IPv6
                false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ||
                false === filter_var($port, FILTER_VALIDATE_INT)
            )
            {
                continue;
            }

            $servers["[{$host}]:{$port}"] = // keep unique
            [
                'host' => $host,
                'port' => $port
            ];
        }

        $this->_fclose(
            $socket
        );

        return $servers;
    }

    public function getErrors(): array
    {
        return $this->_errors;
    }
}