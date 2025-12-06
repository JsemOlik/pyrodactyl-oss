<?php

namespace Pterodactyl\Services\Proxy;

/**
 * Parser for extracting hostname from Minecraft protocol packets.
 * 
 * This class is used for testing and validation during Phase 1 research.
 * The actual HAProxy implementation will use Lua scripts, but this PHP
 * implementation helps us understand and test the protocol structure.
 */
class MinecraftProtocolParser
{
    /**
     * Extract hostname from Minecraft handshake packet.
     * 
     * The handshake packet structure:
     * [Packet ID: VarInt] [Protocol Version: VarInt] [Server Address: String] [Server Port: Unsigned Short] [Next State: VarInt]
     * 
     * @param string $packet Raw packet bytes
     * @return array{hostname: string|null, protocol_version: int|null, port: int|null, next_state: int|null, error: string|null}
     */
    public function extractHostname(string $packet): array
    {
        $result = [
            'hostname' => null,
            'protocol_version' => null,
            'port' => null,
            'next_state' => null,
            'error' => null,
        ];

        if (empty($packet)) {
            $result['error'] = 'Empty packet';
            return $result;
        }

        $offset = 0;
        $packetLength = strlen($packet);

        try {
            // Read packet ID (VarInt) - should be 0x00 for Handshake
            $packetId = $this->readVarInt($packet, $offset, $packetLength);
            
            if ($packetId !== 0) {
                $result['error'] = "Expected packet ID 0x00 (Handshake), got 0x" . dechex($packetId);
                return $result;
            }

            // Read protocol version (VarInt)
            $result['protocol_version'] = $this->readVarInt($packet, $offset, $packetLength);

            // Read server address (String) - THIS IS THE HOSTNAME
            $result['hostname'] = $this->readString($packet, $offset, $packetLength);

            // Read server port (Unsigned Short, 2 bytes, big-endian)
            if ($offset + 2 <= $packetLength) {
                $result['port'] = unpack('n', substr($packet, $offset, 2))[1]; // 'n' = unsigned short (16 bit, big endian)
                $offset += 2;
            }

            // Read next state (VarInt)
            if ($offset < $packetLength) {
                $result['next_state'] = $this->readVarInt($packet, $offset, $packetLength);
            }

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Read a VarInt (Variable-length Integer) from the packet.
     * 
     * @param string $data Packet data
     * @param int $offset Current offset (passed by reference, will be updated)
     * @param int $maxLength Maximum length to prevent reading beyond packet
     * @return int The decoded VarInt value
     * @throws \Exception If VarInt is invalid or too long
     */
    private function readVarInt(string $data, int &$offset, int $maxLength): int
    {
        $value = 0;
        $position = 0;
        $maxPosition = 5; // VarInt can be at most 5 bytes

        while ($position < $maxPosition) {
            if ($offset >= $maxLength) {
                throw new \Exception("Unexpected end of packet while reading VarInt");
            }

            $currentByte = ord($data[$offset]);
            $value |= ($currentByte & 0x7F) << (7 * $position);

            $offset++;

            if (($currentByte & 0x80) === 0) {
                return $value;
            }

            $position++;
        }

        throw new \Exception("VarInt too long (exceeded 5 bytes)");
    }

    /**
     * Read a String from the packet.
     * 
     * Minecraft strings are length-prefixed with VarInt, then UTF-8 encoded bytes.
     * 
     * @param string $data Packet data
     * @param int $offset Current offset (passed by reference, will be updated)
     * @param int $maxLength Maximum length to prevent reading beyond packet
     * @return string The decoded string
     * @throws \Exception If string is invalid or too long
     */
    private function readString(string $data, int &$offset, int $maxLength): string
    {
        // Read length (VarInt)
        $length = $this->readVarInt($data, $offset, $maxLength);

        // Minecraft has a maximum string length (typically 32767 for hostname)
        if ($length < 0 || $length > 32767) {
            throw new \Exception("Invalid string length: {$length}");
        }

        // Check if we have enough bytes
        if ($offset + $length > $maxLength) {
            throw new \Exception("String length ({$length}) exceeds remaining packet data");
        }

        // Read the string bytes
        $string = substr($data, $offset, $length);
        $offset += $length;

        // Validate UTF-8 encoding
        if (!mb_check_encoding($string, 'UTF-8')) {
            throw new \Exception("String is not valid UTF-8");
        }

        return $string;
    }

    /**
     * Validate that a packet looks like a Minecraft handshake packet.
     * 
     * @param string $packet Raw packet bytes
     * @return bool True if packet appears to be a valid handshake packet
     */
    public function isValidHandshakePacket(string $packet): bool
    {
        if (empty($packet)) {
            return false;
        }

        try {
            $result = $this->extractHostname($packet);
            return $result['hostname'] !== null && $result['error'] === null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get packet information for debugging.
     * 
     * @param string $packet Raw packet bytes
     * @return array Debug information about the packet
     */
    public function getPacketInfo(string $packet): array
    {
        $info = [
            'length' => strlen($packet),
            'hex' => bin2hex($packet),
            'first_bytes' => [],
        ];

        // Show first 20 bytes in hex
        $bytesToShow = min(20, strlen($packet));
        for ($i = 0; $i < $bytesToShow; $i++) {
            $info['first_bytes'][] = sprintf('0x%02X', ord($packet[$i]));
        }

        // Try to parse
        $parsed = $this->extractHostname($packet);
        $info['parsed'] = $parsed;

        return $info;
    }
}
