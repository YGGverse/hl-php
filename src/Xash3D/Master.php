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

    // Legacy protocol implementation does not support mixed address families
    // in the binary master socket response, use separated method for IPv4 servers.
    public function getServersIPv6(
        int    $limit   = 100,
        string $host    = "[::]",
        int    $port    = 0,
        string $gamedir = "valve",
        string $region  = "\xFF"
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

        $master = "{$this->_host}:{$this->_port}";

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
                _("Connection error for $master: %s"),
                $message
            );

            $this->_fclose(
                $socket
            );

            return null;
        }

        // Filter query
        if (false === fwrite($socket, "1{$region}{$host}:{$port}\0\\gamedir\\{$gamedir}\0"))
        {
            $this->_errors[] = _("Could not send socket query for $master");

            $this->_fclose(
                $socket
            );

            return null;
        }

        // Skip header
        if (false === fread($socket, 6))
        {
            $this->_errors[] = _("Could not init packet header for $master");

            $this->_fclose(
                $socket
            );

            return null;
        }

        // Get servers
        $servers = [];

        for ($i = 0; $i < $limit; $i++)
        {
            // Get host bytes
            if (false === $h = fread($socket, 16))
            {
                $this->_errors[] = _("Invalid `host` fragment in packet at $i for $master");
                break;
            }

            // End of packet
            if (true === str_ends_with(bin2hex($h), bin2hex("\0\0\0\0\0\0")))
            {
                break;
            }

            // Get host string
            if (false === $h = inet_ntop($h))
            {
                $this->_errors[] = _("Invalid `host` value in packet at $i for $master");
                break;
            }

            // Get port bytes
            if (false === $p = fread($socket, 2))
            {
                $this->_errors[] = _("Invalid `port` fragment in packet at $i for $master");
                break;
            }

            // Get port value
            if (false === $p = unpack('nport', $p))
            {
                $this->_errors[] = _("Invalid `port` value in packet at $i for $master");
                continue;
            }

            // Validate result
            if (false === filter_var($h, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) || empty($p['port']))
            {
                $this->_errors[] = _("Invalid socket address in packet at $i for $master");
                continue;
            }

            $servers["{$h}{$p['port']}"] = // keep unique
            [
                'host' => $h,
                'port' => $p['port']
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