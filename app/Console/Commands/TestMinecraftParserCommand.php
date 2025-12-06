<?php

namespace Pterodactyl\Console\Commands;

use Illuminate\Console\Command;
use Pterodactyl\Services\Proxy\MinecraftProtocolParser;

class TestMinecraftParserCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:minecraft-parser 
                            {--hex= : Hex-encoded packet data to test}
                            {--file= : Path to file containing hex-encoded packet data}
                            {--generate-sample : Generate a sample packet for testing}';

    /**
     * The console command description.
     */
    protected $description = 'Test the Minecraft protocol parser for hostname extraction';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $parser = new MinecraftProtocolParser();

        if ($this->option('generate-sample')) {
            $this->generateSamplePacket();
            return 0;
        }

        $hexData = null;

        if ($this->option('hex')) {
            $hexData = $this->option('hex');
        } elseif ($this->option('file')) {
            $filePath = $this->option('file');
            if (!file_exists($filePath)) {
                $this->error("File not found: {$filePath}");
                return 1;
            }
            $hexData = trim(file_get_contents($filePath));
            // Remove any whitespace, newlines, etc.
            $hexData = preg_replace('/\s+/', '', $hexData);
        } else {
            $this->error('Please provide either --hex or --file option');
            $this->info('');
            $this->info('Usage examples:');
            $this->info('  php artisan test:minecraft-parser --hex="000f3132372e302e302e310063d4"');
            $this->info('  php artisan test:minecraft-parser --file=/path/to/packet.hex');
            $this->info('  php artisan test:minecraft-parser --generate-sample');
            return 1;
        }

        // Convert hex to binary
        $packet = hex2bin($hexData);
        if ($packet === false) {
            $this->error('Invalid hex data provided');
            return 1;
        }

        $this->info('Testing Minecraft Protocol Parser');
        $this->info('================================');
        $this->info('');

        // Get packet info
        $packetInfo = $parser->getPacketInfo($packet);

        $this->info("Packet Length: {$packetInfo['length']} bytes");
        $this->info("Hex: {$packetInfo['hex']}");
        $this->info('');
        $this->info('First 20 bytes:');
        $this->line(implode(' ', $packetInfo['first_bytes']));
        $this->info('');

        // Try to extract hostname
        $result = $parser->extractHostname($packet);

        if ($result['error']) {
            $this->error("Error: {$result['error']}");
            return 1;
        }

        $this->info('Parsed Results:');
        $this->info('---------------');
        $this->line("Hostname: " . ($result['hostname'] ?? 'NULL'));
        $this->line("Protocol Version: " . ($result['protocol_version'] ?? 'NULL'));
        $this->line("Port: " . ($result['port'] ?? 'NULL'));
        $this->line("Next State: " . ($result['next_state'] ?? 'NULL'));
        $this->info('');

        if ($result['hostname']) {
            $this->info('✅ Successfully extracted hostname!');
            return 0;
        } else {
            $this->warn('⚠️  Could not extract hostname from packet');
            return 1;
        }
    }

    /**
     * Generate a sample packet for testing.
     * 
     * This creates a sample handshake packet with hostname "smp.cool.com"
     */
    private function generateSamplePacket(): void
    {
        $this->info('Generating sample Minecraft handshake packet...');
        $this->info('');

        // Sample packet structure:
        // [Packet ID: 0x00] [Protocol Version: 763] [Hostname: "smp.cool.com"] [Port: 25565] [Next State: 1]

        $packet = '';

        // Packet ID: 0x00 (VarInt)
        $packet .= "\x00";

        // Protocol Version: 763 (VarInt) - Minecraft 1.20.1
        // VarInt encoding for 763:
        // 763 = 0x02FB = 251 + (2 << 7) = 251 + 256
        // First byte: 251 (0xFB) with continuation bit = 0xFB
        // Second byte: 2 (0x02) without continuation bit = 0x02
        // So: 0xFB 0x02
        $packet .= "\xFB\x02";

        // Hostname: "smp.cool.com" (String)
        $hostname = 'smp.cool.com';
        $hostnameLength = strlen($hostname);
        // Length as VarInt (12 = 0x0C, since 12 < 128, it's just one byte)
        $packet .= "\x0C";
        // Hostname bytes
        $packet .= $hostname;

        // Port: 25565 (Unsigned Short, big-endian)
        // 25565 = 0x63DD
        $packet .= "\x63\xDD";

        // Next State: 1 (VarInt) - Status
        $packet .= "\x01";

        $hex = bin2hex($packet);

        $this->info('Sample Packet Generated:');
        $this->info('------------------------');
        $this->line("Hostname: {$hostname}");
        $this->line("Hex: {$hex}");
        $this->info('');
        $this->info('Test it with:');
        $this->line("php artisan test:minecraft-parser --hex=\"{$hex}\"");
        $this->info('');

        // Also save to file
        $filePath = storage_path('app/minecraft_sample_packet.hex');
        file_put_contents($filePath, $hex);
        $this->info("Saved to: {$filePath}");
        $this->info('');

        // Test it immediately
        $this->info('Testing the generated packet...');
        $this->info('');
        $parser = new MinecraftProtocolParser();
        $result = $parser->extractHostname($packet);
        
        if ($result['error']) {
            $this->error("Error parsing sample: {$result['error']}");
        } else {
            $this->info('✅ Sample packet parsed successfully!');
            $this->line("Extracted hostname: {$result['hostname']}");
            $this->line("Protocol version: {$result['protocol_version']}");
            $this->line("Port: {$result['port']}");
        }
    }
}
