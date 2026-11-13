<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
set_exception_handler(function (Throwable $e): void {
    if ($e instanceof JsonException || $e instanceof InvalidArgumentException) fail(422, $e->getMessage());
    if ($e instanceof PDOException && in_array((string) $e->getCode(), ['23000','22001','22003','22007'], true)) fail(409, 'Constraint violation or invalid field value');
    $requestId = bin2hex(random_bytes(8));
    error_log('JobSearch request=' . $requestId . ' exception=' . get_class($e));
    respond(['error' => 'Internal server error', 'request_id' => $requestId], 500);
});
query("SET time_zone = '+00:00'");
$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '', '/');
if (!str_starts_with($path, '/api/v1/')) fail(404, 'Not found');
$path = substr($path, 8);
$clientId = requireToken($path === 'imports' ? 'imports:write' : ($method === 'GET' ? 'data:read' : 'data:write'));
if ($path === 'health' && $method === 'GET') respond(['status' => 'ok']);
if ($path === 'imports' && $method === 'POST') {
    require dirname(__DIR__) . '/src/import.php';
    respond(importBatch(objectBody(), $clientId));
}
$resources = ['companies' => ['companies', COMPANY_FIELDS], 'offers' => ['job_offers', OFFER_FIELDS], 'contacts' => ['company_contacts', CONTACT_FIELDS], 'applications' => ['applications', APPLICATION_FIELDS]];
if ($path === 'follow-ups' && $method === 'GET') respond(['data' => query("SELECT * FROM applications WHERE next_follow_up_at<=UTC_TIMESTAMP() AND status IN ('sent','acknowledged','interview') ORDER BY next_follow_up_at LIMIT 100")->fetchAll()]);
if (!preg_match('~^(companies|offers|contacts|applications)(?:/([1-9][0-9]*))?(?:/(favorite|tracking|preferences|contacts|offers|applications|events))?$~', $path, $m)) fail(404, 'Not found');
$resource = $m[1]; $id = isset($m[2]) ? (int) $m[2] : null; $action = $m[3] ?? null;
[$table, $fields] = $resources[$resource];
$softDelete = in_array($resource, ['offers','contacts'], true);
if ($id) {
    $row = query("SELECT * FROM $table WHERE id=?" . ($softDelete ? ' AND deleted_at IS NULL' : ''), [$id])->fetch();
    if (!$row) fail(404, 'Not found');
}
if ($action) {
    if ($resource === 'offers' && in_array($action, ['favorite','tracking'], true)) {
        if ($action === 'favorite' && in_array($method, ['PUT','DELETE'], true)) $data = ['is_favorite' => $method === 'PUT' ? 1 : 0];
        elseif ($action === 'tracking' && $method === 'PATCH') $data = only(objectBody(), ['viewed_at','dismissed_at','dismiss_reason','personal_notes']);
        elseif ($action === 'tracking' && $method === 'GET') respond(query('SELECT * FROM job_offer_tracking WHERE job_offer_id=?', [$id])->fetch() ?: ['job_offer_id' => $id, 'is_favorite' => 0]);
        else fail(405, 'Method not allowed');
        query('INSERT IGNORE INTO job_offer_tracking(job_offer_id) VALUES (?)', [$id]);
        if (!$data) fail(422, 'Empty object');
        query('UPDATE job_offer_tracking SET ' . implode(',', array_map(fn($k) => "$k=?", array_keys($data))) . ' WHERE job_offer_id=?', [...array_values($data), $id]);
        respond(['ok' => true]);
    }
    if ($resource === 'companies' && $action === 'preferences' && in_array($method, ['GET','PUT'], true)) {
        if ($method === 'PUT') {
            $data = only(objectBody(), ['visibility','interest_level','reason','notes']);
            if (!isset($data['visibility'], $data['interest_level'])) fail(422, 'visibility and interest_level required');
            query('INSERT INTO company_preferences(company_id,visibility,interest_level,reason,notes) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE visibility=VALUES(visibility),interest_level=VALUES(interest_level),reason=VALUES(reason),notes=VALUES(notes)', [$id,$data['visibility'],$data['interest_level'],$data['reason'] ?? null,$data['notes'] ?? null]);
        }
        respond(query('SELECT * FROM company_preferences WHERE company_id=?', [$id])->fetch() ?: ['company_id' => $id,'visibility' => 'normal','interest_level' => 'neutral']);
    }
    if ($resource === 'applications' && $action === 'events') {
        if ($method === 'GET') respond(['data' => query('SELECT * FROM application_events WHERE application_id=? ORDER BY occurred_at,id', [$id])->fetchAll()]);
        if ($method === 'POST') {
            $data = only(objectBody(), ['event_type','occurred_at','notes']);
            if (empty($data['event_type']) || empty($data['occurred_at'])) fail(422, 'event_type and occurred_at required');
            respond(['id' => insertRow('application_events', [...$data, 'application_id' => $id])], 201);
        }
    }
    if ($resource === 'companies' && in_array($action, ['contacts','offers','applications'], true)) {
        [$childTable, $childFields] = $resources[$action];
        if ($method === 'GET') respond(['data' => query("SELECT * FROM $childTable WHERE company_id=?" . (in_array($action,['offers','contacts'],true) ? ' AND deleted_at IS NULL' : '') . ' ORDER BY id DESC LIMIT 100', [$id])->fetchAll()]);
        if ($method === 'POST' && $action === 'contacts') respond(['id' => insertRow($childTable, [...only(objectBody(), array_values(array_diff($childFields, ['company_id']))), 'company_id' => $id])], 201);
    }
    fail(405, 'Method not allowed');
}
if ($method === 'GET' && $id) respond($row);
if ($method === 'GET') {
    $page = max(1, (int) ($_GET['page'] ?? 1)); $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 25))); $offset = ($page-1)*$limit;
    $conditions = []; $params = []; $joins = '';
    if ($softDelete) $conditions[] = 'r.deleted_at IS NULL';
    if ($resource === 'offers') {
        $joins = ' LEFT JOIN company_preferences cp ON cp.company_id=r.company_id LEFT JOIN company_preferences rp ON rp.company_id=r.recruiter_company_id LEFT JOIN job_offer_tracking t ON t.job_offer_id=r.id';
        $visibility = $_GET['visibility'] ?? 'normal';
        if (!in_array($visibility, ['normal','deprioritized','excluded','all'], true)) fail(422, 'Invalid visibility');
        if ($visibility === 'normal') $conditions[] = "COALESCE(cp.visibility,'normal')='normal' AND COALESCE(rp.visibility,'normal')='normal'";
        if ($visibility === 'excluded') $conditions[] = "(cp.visibility='excluded' OR rp.visibility='excluded')";
        if ($visibility === 'deprioritized') $conditions[] = "(cp.visibility='deprioritized' OR rp.visibility='deprioritized') AND COALESCE(cp.visibility,'normal')<>'excluded' AND COALESCE(rp.visibility,'normal')<>'excluded'";
        if (isset($_GET['favorite'])) { $conditions[] = 'COALESCE(t.is_favorite,0)=?'; $params[] = filter_var($_GET['favorite'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0; }
    }
    foreach (['status','city','company_id','company_type'] as $key) if (isset($_GET[$key]) && in_array($key, $fields, true)) { $conditions[] = "r.$key=?"; $params[] = $_GET[$key]; }
    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $total = (int) query("SELECT COUNT(*) FROM $table r$joins$where", $params)->fetchColumn();
    respond(['data' => query("SELECT r.* FROM $table r$joins$where ORDER BY r.id DESC LIMIT $limit OFFSET $offset", $params)->fetchAll(), 'page' => $page, 'per_page' => $limit, 'total' => $total]);
}
if (($method === 'POST' && !$id) || ($method === 'PATCH' && $id)) {
    $data = only(objectBody(), $fields);
    if ($resource === 'applications') {
        $application = array_replace($row ?? [], $data);
        if (!isset($application['company_id'], $application['type'])) fail(422, 'company_id and type required');
        if (!empty($application['contact_id']) && !query('SELECT id FROM company_contacts WHERE id=? AND company_id=? AND deleted_at IS NULL', [$application['contact_id'],$application['company_id']])->fetchColumn()) fail(422, 'Contact does not belong to company');
        if (!empty($application['job_offer_id'])) {
            $offer = query('SELECT company_id,recruiter_company_id FROM job_offers WHERE id=? AND deleted_at IS NULL', [$application['job_offer_id']])->fetch();
            if (!$offer || !in_array((int) $application['company_id'], [(int) $offer['company_id'],(int) $offer['recruiter_company_id']], true)) fail(422, 'Offer does not belong to company');
        }
    }
    if ($id) { updateRow($table, $id, $data); respond(['id' => $id]); }
    respond(['id' => insertRow($table, $data)], 201);
}
if ($method === 'DELETE' && $id && $softDelete) { query("UPDATE $table SET deleted_at=UTC_TIMESTAMP() WHERE id=?", [$id]); respond(['ok' => true]); }
fail(405, 'Method not allowed');
