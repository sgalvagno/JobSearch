<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/src/bootstrap.php';
$name = $argv[1] ?? null;
$scopes = explode(',', $argv[2] ?? 'data:read');
if (!$name || array_diff($scopes, ['*','data:read','data:write','imports:write'])) { fwrite(STDERR, "Usage: php bin/token.php NAME 'data:read,data:write|imports:write|*' [expiry: YYYY-MM-DD HH:MM:SS UTC]\n"); exit(1); }
$expiry = $argv[3] ?? null;
if ($expiry !== null && (!DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $expiry, new DateTimeZone('UTC')) || $expiry <= gmdate('Y-m-d H:i:s'))) { fwrite(STDERR, "Invalid or past expiry\n"); exit(1); }
$token = 'js_' . bin2hex(random_bytes(32));
$id = insertRow('api_tokens', ['name' => $name, 'token_prefix' => substr($token,0,11), 'token_hash' => hash('sha256',$token), 'scopes' => json_encode($scopes,JSON_THROW_ON_ERROR), 'expires_at' => $expiry]);
fwrite(STDOUT, "Token ID: $id\nSecret (shown once): $token\n");
