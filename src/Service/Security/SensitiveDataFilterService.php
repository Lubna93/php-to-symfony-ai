<?php

namespace App\Service\Security;

final class SensitiveDataFilterService
{
    public function isSensitiveFile(string $path): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $filename = basename($path);

        $blockedNames = [
            'config.php',
            'configuration.php',
            'database.php',
            'db.php',
            'db_config.php',
            'credentials.php',
            'secrets.php',
            'settings.php',
            'parameters.php',
            'parameters.yml',
            'parameters.yaml',
            'application.ini',
            'auth.json',
            'local.php',
            'global.php',
        ];

        if (in_array($filename, $blockedNames, true)) {
            return true;
        }

        $patterns = [
            '#(^|/)\.env(\.|$)#',
            '#(^|/)(\.ssh|\.aws|secrets?|private)/#',
            '#(^|/)config/(secrets|jwt)/#',
            '#(^|/)config/autoload/.*\.local\.php$#',
            '#(^|/)storage/oauth-#',
            '#(^|/)(vendor|node_modules|var/cache)/#',
            '#\.(pem|key|p12|pfx|jks|keystore)$#',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public function redact(string $content): string
    {
        $patterns = [
            '/((?:password|passwd|secret|api_key|access_token|client_secret)\s*[:=]\s*)["\'][^"\']+["\']/i',
            '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/i',
            '/\bsk-[A-Za-z0-9_-]{16,}\b/',
            '/\bgh[pousr]_[A-Za-z0-9_]{20,}\b/',
        ];

        $replacements = [
            '$1"[REDACTED]"',
            '$1[REDACTED]',
            '[REDACTED]',
            '[REDACTED]',
        ];

        return preg_replace($patterns, $replacements, $content) ?? $content;
    }
}
