<?php

declare(strict_types=1);

namespace Yggverse\Hl\Xash3D;

class Master
{
    private $_socket;

    public function __construct(
        string $host,
        int    $port,
        int    $timeout = 5
    )
    {
        $this->_socket = fsockopen(
            "udp://{$host}",
            $port
        );

        if ($this->_socket)
        {
            stream_set_timeout(
                $this->_socket,
                $timeout
            );
        }
    }

    public function __destruct()
    {
        if ($this->_socket)
        {
            fclose(
                $this->_socket
            );
        }
    }

    public static function getServersIPv6(
        int    $limit   = 100,
        string $region  = "\xFF",
        string $host    = "0.0.0.0:0",
        int    $port    = 0,
        string $gamedir = "valve"
    ): ?array
    {
        // Is connected
        if (!$this->_socket)
        {
            return null;
        }

        // Filter query
        if (!fwrite($this->_socket, "1{$region}{$host}:{$port}\0\gamedir\t{$gamedir}\0"))
        {
            fclose(
                $this->_socket
            );

            return null;
        }

        // Skip header
        if (!fread($this->_socket, 6))
        {
            return null;
        }

        // Get servers
        $servers = [];

        for ($i = 0; $i < $limit; $i++)
        {
            // Get host
            if (false === $host = fread($this->_socket, 16))
            {
                return null;
            }

            // Is end of packet
            if (true === str_starts_with($host, 0))
            {
                break;
            }

            // Skip invalid host
            if (false === $host = inet_ntop($host))
            {
                continue;
            }

            // Decode first byte for port
            if (false === $byte1 = fread($this->_socket, 1))
            {
                return null;
            }

            // Decode second byte for port
            if (false === $byte2 = fread($this->_socket, 1))
            {
                return null;
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
}