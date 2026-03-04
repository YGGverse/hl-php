<?php

declare(strict_types=1);

namespace Yggverse\Hl\Xash3D;

enum Family: int {
    case IPv4 = 4;
    case IPv6 = 16;
}

enum Region: string {
    case Europe  = "\x03";
    case US_East = "\x00";
    case World   = "\xFF";
}

enum Game: string {
    case Valve = "valve";
}

class Master
{
    private string $_host;
    private int    $_port;
    private int    $_timeout;

    private array  $_errors = [];

    public function __construct(
        string $host,
        int    $port    = 27010,
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
    public function getServers(
        int    $limit   = 100,
        string $host    = "0.0.0.0",
        int    $port    = 0,
        Game   $game    = Game::Valve,
        Region $region  = Region::World
    ): ?array
    {
        $family = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? Family::IPv4 : Family::IPv6;

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
        if (false === fwrite($socket, "1{$region->value}{$host}:{$port}\0\\gamedir\\{$game->value}\0"))
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
            if (false === $host = fread($socket, $family->value))
            {
                $this->_errors[] = _("Invalid `host` fragment in packet at $i for $master");
                break;
            }

            // End of packet
            if (true === str_ends_with(bin2hex($host), bin2hex("\0\0\0\0\0\0")))
            {
                break;
            }

            // Get host string
            if (false === $host = inet_ntop($host))
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
                break;
            }

            // Validate result
            if (false === filter_var($host, FILTER_VALIDATE_IP, $family == Family::IPv6 ? FILTER_FLAG_IPV6
                                                                                        : FILTER_FLAG_IPV4) || empty($p['port']))
            {
                $this->_errors[] = _("Invalid socket address in packet at $i for $master");
                break;
            }

            $servers["{$host}{$p['port']}"] = // keep unique
            [
                'host' => $host,
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