<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';

/** Documents de commandes stockés dans Apache CouchDB. Aucun accès SQL dans les lectures du graphique. */
final class NoSqlStatistics
{
    private string $base;
    private string $database;
    private string $user;
    private string $password;

    public function __construct()
    {
        $url = appConfig('COUCHDB_URL');
        $database = appConfig('COUCHDB_DATABASE');
        $this->user = (string) (appConfig('COUCHDB_USER') ?: '');
        $this->password = (string) (appConfig('COUCHDB_PASSWORD') ?: '');
        if (!is_string($url) || $url === '' || !preg_match('#^https?://#', $url)
            || !is_string($database) || !preg_match('/^[a-z][a-z0-9_$()+-]*$/', $database)
            || $this->user === '' || $this->password === '') {
            throw new RuntimeException('Base NoSQL non configurée.');
        }
        if (appConfig('APP_ENV') === 'test' && !str_ends_with($database, '_test')) {
            throw new RuntimeException('En test, la base NoSQL doit se terminer par _test.');
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (!in_array($host, ['localhost', '127.0.0.1', '::1'], true) && !str_starts_with($url, 'https://')) {
            throw new RuntimeException('HTTPS est requis pour une base NoSQL distante.');
        }
        $this->base = rtrim($url, '/');
        $this->database = $database;
    }

    private function call(string $method, string $path, ?array $body = null, array $ok = [200, 201, 202]): array
    {
        $handle = curl_init($this->base . '/' . rawurlencode($this->database) . $path);
        if ($handle === false) throw new RuntimeException('cURL indisponible.');
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->user . ':' . $this->password,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
        ];
        if ($body !== null) $options[CURLOPT_POSTFIELDS] = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if (!in_array($status, $ok, true) || !is_string($response)) {
            throw new RuntimeException('CouchDB indisponible ou réponse inattendue (HTTP ' . $status . ').');
        }
        $decoded = json_decode($response, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) throw new RuntimeException('Réponse CouchDB invalide.');
        return $decoded;
    }

    public function upsertOrder(PDO $pdo, int $orderId): void
    {
        $query = $pdo->prepare('SELECT c.commande_id, c.menu_id, m.titre AS menu_titre, c.date_prestation, c.statut, c.prix_total FROM commande c INNER JOIN menu m ON m.menu_id = c.menu_id WHERE c.commande_id = ?');
        $query->execute([$orderId]);
        $order = $query->fetch(PDO::FETCH_ASSOC);
        if (!$order) throw new RuntimeException('Commande introuvable pour la projection NoSQL.');
        $id = 'commande:' . $orderId;
        $path = '/' . rawurlencode($id);
        $revision = null;
        try {
            $existing = $this->call('GET', $path, null, [200]);
            $revision = $existing['_rev'] ?? null;
        } catch (RuntimeException $e) {
            if (!str_contains($e->getMessage(), 'HTTP 404')) throw $e;
        }
        $document = [
            '_id' => $id,
            'type' => 'commande',
            'commande_id' => (int) $order['commande_id'],
            'menu_id' => (int) $order['menu_id'],
            'menu_titre' => $order['menu_titre'],
            'date_prestation' => $order['date_prestation'],
            'statut' => $order['statut'],
            'prix_total' => (float) $order['prix_total'],
        ];
        if (is_string($revision)) $document['_rev'] = $revision;
        $this->call('PUT', $path, $document, [201, 202]);
    }

    /** Supprime uniquement la projection d'une commande temporaire de recette. */
    public function deleteOrder(int $orderId): void
    {
        $path = '/' . rawurlencode('commande:' . $orderId);
        try {
            $existing = $this->call('GET', $path, null, [200]);
        } catch (RuntimeException $error) {
            if (str_contains($error->getMessage(), 'HTTP 404')) return;
            throw $error;
        }
        $revision = $existing['_rev'] ?? null;
        if (!is_string($revision) || $revision === '') throw new RuntimeException('Révision CouchDB absente.');
        $this->call('DELETE', $path . '?rev=' . rawurlencode($revision), null, [200, 202]);
    }

    public function statistics(?string $start, ?string $end, ?int $menu): array
    {
        $bookmark = null;
        $rows = [];
        do {
            $query = ['selector' => ['type' => 'commande'], 'limit' => 500];
            if ($bookmark !== null) $query['bookmark'] = $bookmark;
            $response = $this->call('POST', '/_find', $query, [200]);
            $docs = $response['docs'] ?? null;
            if (!is_array($docs)) throw new RuntimeException('Documents statistiques invalides.');
            foreach ($docs as $doc) {
                if (!is_array($doc) || ($doc['statut'] ?? '') === 'annulée') continue;
                $date = $doc['date_prestation'] ?? '';
                $id = (int) ($doc['menu_id'] ?? 0);
                if (($start !== null && $date < $start) || ($end !== null && $date > $end) || ($menu !== null && $id !== $menu)) continue;
                if (!isset($rows[$id])) $rows[$id] = ['menu_id' => $id, 'titre' => (string) ($doc['menu_titre'] ?? ''), 'nombre_commandes' => 0, 'chiffre_affaires' => 0.0];
                $rows[$id]['nombre_commandes']++;
                $rows[$id]['chiffre_affaires'] += (float) ($doc['prix_total'] ?? 0);
            }
            $next = $response['bookmark'] ?? null;
            if (count($docs) < 500 || !is_string($next) || $next === $bookmark) break;
            $bookmark = $next;
        } while (true);
        $result = array_values($rows);
        usort($result, fn(array $a, array $b) => $b['nombre_commandes'] <=> $a['nombre_commandes'] ?: strcmp($a['titre'], $b['titre']));
        $total = 0;
        $revenue = 0.0;
        foreach ($result as &$row) {
            $total += $row['nombre_commandes'];
            $revenue += $row['chiffre_affaires'];
            $row['chiffre_affaires'] = number_format($row['chiffre_affaires'], 2, '.', '');
        }
        return ['source' => 'couchdb', 'nombre_commandes' => $total, 'chiffre_affaires' => number_format($revenue, 2, '.', ''), 'par_menu' => $result];
    }
}

function projectOrderToNoSql(PDO $pdo, int $orderId): void
{
    // Destination et champs autorisés pour la recette locale uniquement.
    if (appConfig('APP_ENV') !== 'test'
        || appConfig('COUCHDB_URL') !== 'http://127.0.0.1:5984'
        || appConfig('COUCHDB_DATABASE') !== 'vite_gourmand_stats_test') return;
    try {
        if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'vite_gourmand_test') {
            throw new RuntimeException('Projection refusée hors vite_gourmand_test.');
        }
        (new NoSqlStatistics())->upsertOrder($pdo, $orderId);
    } catch (Throwable $error) {
        error_log('Commande SQL enregistrée mais projection NoSQL à resynchroniser : ' . $error->getMessage());
    }
}
