<?php

declare(strict_types=1);

namespace Yggverse\Hl\Xash3D;

class Master
{
    private $_socket;
    private $_errors = [];

    public function __construct(
        string $host,
        int    $port,
        int    $timeout = 5
    )
    {
        $this->_socket = fsockopen(
            "udp://{$host}",
            $port,
            $code,
            $message,
            $timeout
        );

        if (is_resource($this->_socket))
        {
            stream_set_timeout(
                $this->_socket,
                $timeout
            );
        }

        else
        {
            $this->_errors[] = sprintf(
                _('Connection error: %s'),
                $message
            );
        }
    }

    public function __destruct()
    {
        if (true === is_resource($this->_socket))
        {
            fclose(
                $this->_socket
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
        // Is connected
        if (false === is_resource($this->_socket))
        {
            $this->_errors[] = _('Socket connection error');

            return null;
        }

        // Filter query
        if (false === fwrite($this->_socket, "1{$region}{$host}:{$port}\0\gamedir\t{$gamedir}\0"))
        {
            $this->_errors[] = _('Could not send socket query');

            return null;
        }

        // Skip header
        if (false === fread($this->_socket, 6))
        {
            $this->_errors[] = _('Could not init packet header');

            return null;
        }

        // Get servers
        $servers = [];

        for ($i = 0; $i < $limit; $i++)
        {
            // Get host
            if (false === $host = fread($this->_socket, 16))
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
                fread($this->_socket, 2);

                continue;
            }

            // Decode first byte for port
            if (false === $byte1 = fread($this->_socket, 1))
            {
                // Shift port byte
                fread($this->_socket, 1);

                continue;
            }

            // Decode second byte for port
            if (false === $byte2 = fread($this->_socket, 1))
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

        return $servers;
    }

    public function getErrors(): array
    {
        return $this->_errors;
    }
}