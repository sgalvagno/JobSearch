<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/src/bootstrap.php';
$id = $argv[1] ?? '';
if (!ctype_digit($id)) { fwrite(STDERR,"Usage: php bin/revoke-token.php ID\n"); exit(1); }
query('UPDATE api_tokens SET revoked_at=UTC_TIMESTAMP() WHERE id=?', [(int)$id]);
fwrite(STDOUT,"Revoked\n");
