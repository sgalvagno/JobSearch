<?php
declare(strict_types=1);
$configFile = dirname(__DIR__) . '/config/local.php';
$config = is_file($configFile) ? require $configFile : [];
function setting(string $name, mixed $default = null): mixed {
    global $config;
    $env = getenv('JOBSEARCH_' . strtoupper($name));
    return $env !== false ? $env : ($config[$name] ?? $default);
}
function db(): PDO {
    static $db;
    return $db ??= new PDO((string) setting('dsn'), (string) setting('username'), (string) setting('password'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
}
function query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function respond(mixed $data, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}
function fail(int $status, string $message): never { respond(['error' => $message], $status); }
function objectBody(): array {
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) fail(415, 'application/json required');
    $raw = file_get_contents('php://input', false, null, 0, 2097153);
    if ($raw === false || strlen($raw) > 2097152) fail(413, 'Body exceeds 2 MiB');
    $value = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($value) || !str_starts_with(ltrim($raw), '{')) fail(422, 'JSON object required');
    return $value;
}
function only(array $input, array $fields): array {
    $unknown = array_diff(array_keys($input), $fields);
    if ($unknown) fail(422, 'Unknown fields: ' . implode(', ', $unknown));
    foreach ($input as $value) if (is_array($value) || is_object($value)) fail(422, 'Scalar values required');
    return $input;
}
function insertRow(string $table, array $data): int {
    if (!$data) fail(422, 'Empty object');
    $columns = array_keys($data);
    query('INSERT INTO ' . $table . ' (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')', array_values($data));
    return (int) db()->lastInsertId();
}
function updateRow(string $table, int $id, array $data): void {
    if (!$data) fail(422, 'Empty object');
    query('UPDATE ' . $table . ' SET ' . implode(',', array_map(fn($key) => '`' . $key . '`=?', array_keys($data))) . ' WHERE id=?', [...array_values($data), $id]);
}
function requireToken(string $scope): int {
    $secure = ($_SERVER['HTTPS'] ?? '') === 'on';
    if (!$secure && !filter_var(setting('allow_insecure_http', false), FILTER_VALIDATE_BOOLEAN)) fail(400, 'HTTPS required');
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer (\S+)$/', $header, $matches)) fail(401, 'Bearer token required');
    $token = query('SELECT * FROM api_tokens WHERE token_hash=? AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>UTC_TIMESTAMP())', [hash('sha256', $matches[1])])->fetch();
    if (!$token) fail(401, 'Invalid token');
    $scopes = json_decode($token['scopes'], true, 16, JSON_THROW_ON_ERROR);
    if (!in_array('*', $scopes, true) && !in_array($scope, $scopes, true)) fail(403, 'Insufficient permissions');
    query('UPDATE api_tokens SET last_used_at=UTC_TIMESTAMP() WHERE id=?', [$token['id']]);
    return (int) $token['id'];
}
const COMPANY_FIELDS = ['name','legal_name','siren','website_url','domain','linkedin_url','company_type','industry','summary','presentation_text','presentation_source_url','presentation_collected_at','size_range','address_line','postal_code','city','country_code','latitude','longitude','contact_email','phone','careers_url','spontaneous_application_url','information_source_url','last_verified_at'];
const OFFER_FIELDS = ['company_id','recruiter_company_id','title','description','requirements_text','benefits_text','contract_type','employment_type','location_text','city','postal_code','country_code','remote_policy','remote_days_per_week','salary_min','salary_max','salary_currency','salary_period','experience_min_years','seniority','education_level','published_at','expires_at','first_seen_at','last_seen_at','last_checked_at','status','status_reason','closed_at','application_url','application_email','review_status','data_confidence'];
const CONTACT_FIELDS = ['company_id','first_name','last_name','job_title','department','email','phone','linkedin_url','profile_url','contact_type','source_url','verified_at','notes'];
const APPLICATION_FIELDS = ['company_id','job_offer_id','contact_id','type','status','channel','applied_at','next_follow_up_at','cv_reference','cover_letter_reference','notes'];
