<?php
declare(strict_types=1);
/** Atomic, insert-only ingestion. Existing records are deliberately not overwritten. */
function importBatch(array $body, int $clientId): array {
    if (array_diff(array_keys($body), ['schema_version','batch_id','collected_at','search','companies','offers'])) fail(422, 'Unknown batch fields');
    if (($body['schema_version'] ?? null) !== 1 || !is_string($body['batch_id'] ?? null) || !preg_match('/^[a-zA-Z0-9._-]{1,150}$/', $body['batch_id'])) fail(422, 'Invalid schema_version or batch_id');
    foreach (['companies','offers'] as $key) if (!isset($body[$key]) || !is_array($body[$key]) || !array_is_list($body[$key]) || count($body[$key]) > 200) fail(422, 'companies/offers must be lists of at most 200 items');
    $hash = hash('sha256', json_encode($body, JSON_THROW_ON_ERROR));
    $existing = query('SELECT * FROM import_runs WHERE external_batch_id=?', [$body['batch_id']])->fetch();
    if ($existing) {
        if (!hash_equals($existing['payload_hash'], $hash)) fail(409, 'batch_id already used with different content');
        return json_decode($existing['result'], true, 64, JSON_THROW_ON_ERROR);
    }
    db()->beginTransaction();
    try {
        $result = ['batch_id' => $body['batch_id'], 'companies_created' => 0, 'offers_created' => 0, 'offers_ignored' => 0, 'items' => []];
        $companyMap = [];
        foreach ($body['companies'] as $item) {
            if (!is_array($item)) throw new InvalidArgumentException('Invalid company');
            $ref = $item['ref'] ?? null;
            unset($item['ref']);
            if (!is_string($ref) || !preg_match('/^[a-zA-Z0-9._-]{1,100}$/', $ref) || isset($companyMap[$ref])) throw new InvalidArgumentException('Unique company ref required');
            $data = only($item, COMPANY_FIELDS);
            if (empty($data['name'])) throw new InvalidArgumentException('Company name required');
            $existingCompany = !empty($data['siren']) ? query('SELECT id FROM companies WHERE siren=?', [$data['siren']])->fetchColumn() : false;
            // Never merge on name/domain alone. Uncertain records need human review.
            $companyMap[$ref] = $existingCompany ? (int) $existingCompany : insertRow('companies', $data);
            if (!$existingCompany) $result['companies_created']++;
        }
        foreach ($body['offers'] as $index => $item) {
            if (!is_array($item)) throw new InvalidArgumentException('Invalid offer');
            $source = $item['source'] ?? null;
            $ref = $item['company_ref'] ?? null;
            unset($item['source'], $item['company_ref']);
            // Import clients cannot attach arbitrary database company IDs.
            if (isset($item['company_id']) || isset($item['recruiter_company_id'])) throw new InvalidArgumentException('Use company_ref instead of internal IDs');
            if ($ref !== null && (!is_string($ref) || !isset($companyMap[$ref]))) throw new InvalidArgumentException('Unknown company_ref');
            $data = only($item, OFFER_FIELDS);
            if (empty($data['title']) || !is_array($source)) throw new InvalidArgumentException('Offer title/source required');
            $source = only($source, ['source_name','external_id','source_url']);
            if (empty($source['source_name']) || !filter_var($source['source_url'] ?? '', FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($source['source_url'], PHP_URL_SCHEME) ?? ''), ['https','http'], true)) throw new InvalidArgumentException('Valid HTTP source URL required');
            $urlHash = hash('sha256', trim($source['source_url']));
            $found = query('SELECT job_offer_id FROM job_offer_sources WHERE source_url_hash=? OR (source_name=? AND external_id IS NOT NULL AND external_id=?) LIMIT 1', [$urlHash, $source['source_name'], $source['external_id'] ?? null])->fetchColumn();
            if ($found) {
                $result['offers_ignored']++;
                $result['items'][] = ['index' => $index, 'id' => (int) $found, 'action' => 'ignored'];
                continue;
            }
            if ($ref !== null) $data['company_id'] = $companyMap[$ref];
            $id = insertRow('job_offers', $data);
            insertRow('job_offer_sources', [...$source, 'job_offer_id' => $id, 'source_url_hash' => $urlHash]);
            $result['offers_created']++;
            $result['items'][] = ['index' => $index, 'id' => $id, 'action' => 'created'];
        }
        insertRow('import_runs', ['client_id' => $clientId, 'external_batch_id' => $body['batch_id'], 'payload_hash' => $hash, 'result' => json_encode($result, JSON_THROW_ON_ERROR)]);
        db()->commit();
        return $result;
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        throw $e;
    }
}
