<?php
// Avvia la sessione per CSRF e rate limiting
session_start();

// Include delle librerie
require_once 'lib/ipam.php';
require_once 'lib/storage.php';
require_once 'lib/auth.php';

// Configurazione per hosting web - rileva automaticamente il base path
$scriptName = $_SERVER['SCRIPT_NAME'];
$basePath = rtrim(dirname($scriptName), '/');
if ($basePath === '.') $basePath = '';

// Define constants for paths
define('BASE_PATH', $basePath);
define('BASE_URL', $basePath);

// Inizializzazione directory data se non esiste
if (!is_dir('data')) {
    mkdir('data', 0755, true);
    mkdir('data/ips', 0755, true);
    // Crea .htaccess per proteggere i file JSON
    file_put_contents('data/.htaccess', "Require all denied\n");
}

// Gestione CORS per le richieste AJAX
header('Content-Type: text/html; charset=utf-8');

// Router semplice per hosting condiviso
$method = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'];

// Gestione semplificata per hosting web
$path = '/'; // Default alla home page
$action = $_GET['action'] ?? '';
$hash = $_GET['hash'] ?? '';

// Verifica se è una richiesta AJAX o API
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                 strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Se è una richiesta AJAX, usa il parametro action per il routing
if ($isAjaxRequest || !empty($action)) {
    $path = '/' . trim($action, '/');
}

// Gestione delle rotte
try {
    switch (true) {
        case $path === '/login' && $method === 'GET':
        case $action === 'login' && $method === 'GET':
            showLogin();
            break;
            
        case $path === '/login' && $method === 'POST':
        case $action === 'login' && $method === 'POST':
            handleLogin();
            break;
            
        case $path === '/logout' && $method === 'POST':
        case $action === 'logout' && $method === 'POST':
            handleLogout();
            break;
            
        case $path === '/' && $method === 'GET':
        case empty($action) && $method === 'GET':
            requireAuth();
            showDashboard();
            break;
            
        case $path === '/subnets' && $method === 'POST':
        case $action === 'subnets' && $method === 'POST':
            requireAuth();
            handleCreateSubnet();
            break;
            
        case preg_match('/^\/subnets\/([a-f0-9]{32})$/', $path, $matches) && $method === 'GET':
        case preg_match('/^subnets\/([a-f0-9]{32})$/', $action, $matches) && $method === 'GET':
            requireAuth();
            showSubnet($matches[1]);
            break;
            
        case preg_match('/^\/ips\/([a-f0-9]{32})\/toggle$/', $path, $matches) && $method === 'POST':
        case preg_match('/^ips\/([a-f0-9]{32})\/toggle$/', $action, $matches) && $method === 'POST':
            requireAuth();
            handleToggleIP($matches[1]);
            break;
            
        case preg_match('/^\/ips\/([a-f0-9]{32})\/describe$/', $path, $matches) && $method === 'POST':
        case preg_match('/^ips\/([a-f0-9]{32})\/describe$/', $action, $matches) && $method === 'POST':
            requireAuth();
            handleDescribeIP($matches[1]);
            break;
            
        case preg_match('/^\/ips\/([a-f0-9]{32})\/bulk$/', $path, $matches) && $method === 'POST':
        case preg_match('/^ips\/([a-f0-9]{32})\/bulk$/', $action, $matches) && $method === 'POST':
            requireAuth();
            handleBulkIP($matches[1]);
            break;
            
        case preg_match('/^\/subnets\/([a-f0-9]{32})\/label$/', $path, $matches) && $method === 'POST':
        case preg_match('/^subnets\/([a-f0-9]{32})\/label$/', $action, $matches) && $method === 'POST':
            requireAuth();
            handleUpdateSubnetLabel($matches[1]);
            break;
            
        case preg_match('/^\/subnets\/([a-f0-9]{32})\/delete$/', $path, $matches) && $method === 'POST':
        case preg_match('/^subnets\/([a-f0-9]{32})\/delete$/', $action, $matches) && $method === 'POST':
            requireAuth();
            handleDeleteSubnet($matches[1]);
            break;
            
        case $path === '/export' && $method === 'GET':
        case $action === 'export' && $method === 'GET':
            requireAuth();
            handleExport();
            break;
            
        case $path === '/import' && $method === 'POST':
        case $action === 'import' && $method === 'POST':
            requireAuth();
            handleImport();
            break;
            
        case $path === '/used-ips' && $method === 'GET':
        case $action === 'used-ips' && $method === 'GET':
            requireAuth();
            showUsedIPs();
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint non trovato']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Errore interno: ' . $e->getMessage()]);
}

function showDashboard() {
    $subnets = getAllSubnets();
    $search = $_GET['search'] ?? '';
    
    // Filtra le subnet se c'è una ricerca
    if (!empty($search)) {
        $subnets = array_filter($subnets, function($subnet) use ($search) {
            return stripos($subnet['cidr'], $search) !== false || 
                   stripos($subnet['label'] ?? '', $search) !== false;
        });
    }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPAM - Gestione Indirizzi IP</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div>
                    <h1>🌐 IPAM - Gestione Indirizzi IP</h1>
                    <p>Sistema di gestione subnet e indirizzi IPv4</p>
                </div>
                <div class="user-info">
                    <span>👤 Benvenuto, <strong><?= getCurrentUser() ?></strong></span>
                    <form method="POST" action="<?= BASE_URL ?>/index.php?action=logout" style="display: inline;">
                        <button type="submit" class="btn btn-secondary" style="margin-left: 10px;">🚪 Esci</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="actions">
            <button id="addSubnetBtn" class="btn btn-primary">➕ Aggiungi Subnet</button>
            <button id="usedIpsBtn" class="btn btn-info">📋 IP Usati</button>
            <button id="exportBtn" class="btn btn-secondary">📥 Esporta JSON</button>
            <button id="importBtn" class="btn btn-secondary">📤 Importa JSON</button>
            <input type="file" id="importFile" accept=".json" style="display: none;">
        </div>

        <div class="search-box">
            <input type="text" id="searchInput" placeholder="🔍 Cerca per CIDR o label..." value="<?= htmlspecialchars($search) ?>">
        </div>

        <div class="subnets-grid">
            <?php if (empty($subnets)): ?>
                <div class="empty-state">
                    <h3>📝 Nessuna subnet configurata</h3>
                    <p>Inizia aggiungendo la tua prima subnet IPv4</p>
                </div>
            <?php else: ?>
                <?php foreach ($subnets as $hash => $subnet): ?>
                    <?php $stats = getSubnetStats($hash); ?>
                    <div class="subnet-card" onclick="viewSubnet('<?= $hash ?>')" data-label="<?= htmlspecialchars($subnet['label'] ?? 'Subnet') ?>">
                        <div class="subnet-header">
                            <h3><?= htmlspecialchars($subnet['cidr']) ?></h3>
                        </div>
                        <div class="subnet-stats">
                            <div class="stat">
                                <span class="stat-value"><?= $stats['total'] ?></span>
                                <span class="stat-label">Totali</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value used"><?= $stats['used'] ?></span>
                                <span class="stat-label">Usati</span>
                            </div>
                            <div class="stat">
                                <span class="stat-value free"><?= $stats['free'] ?></span>
                                <span class="stat-label">Liberi</span>
                            </div>
                        </div>
                        <div class="subnet-date">
                            Creata: <?= date('d/m/Y H:i', $subnet['created_at']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal per aggiungere subnet -->
    <div id="addSubnetModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addSubnetModal')">&times;</span>
            <h2>➕ Aggiungi Nuova Subnet</h2>
            <form id="addSubnetForm">
                <div class="form-group">
                    <label for="cidr">CIDR (obbligatorio) *</label>
                    <input type="text" id="cidr" name="cidr" placeholder="es. 192.168.1.0/24" required>
                    <small>Formato: indirizzo_rete/prefisso (es. 192.168.1.0/24)</small>
                </div>
                <div class="form-group">
                    <label for="label">Label (opzionale)</label>
                    <input type="text" id="label" name="label" placeholder="es. Rete Ufficio" maxlength="100">
                </div>
                <div class="form-group">
                    <label class="checkbox">
                        <input type="checkbox" id="includeNetBcast" name="include_net_bcast">
                        <span class="checkmark"></span>
                        Includi indirizzi di rete e broadcast
                    </label>
                    <small>Se non selezionato, il primo e ultimo IP saranno esclusi</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addSubnetModal')">Annulla</button>
                    <button type="submit" class="btn btn-primary">Crea Subnet</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script src="<?= BASE_URL ?>/public/app.js"></script>
</body>
</html>
<?php
}

function showSubnet($hash) {
    $subnets = getAllSubnets();
    if (!isset($subnets[$hash])) {
        http_response_code(404);
        echo "Subnet non trovata";
        return;
    }
    
    $subnet = $subnets[$hash];
    $page = max(1, (int)($_GET['page'] ?? 1));
    $search = $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'ip';
    $order = $_GET['order'] ?? 'asc';
    $perPage = 256;
    
    $ips = getSubnetIPs($hash, $page, $perPage, $search, $sort, $order);
    $totalIPs = getSubnetIPCount($hash, $search);
    $totalPages = ceil($totalIPs / $perPage);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subnet <?= htmlspecialchars($subnet['cidr']) ?> - IPAM</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div>
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/">🏠 Dashboard</a> / 
                        <span>🌐 <?= htmlspecialchars($subnet['cidr']) ?></span>
                    </div>
                    <h1>Gestione Subnet: <?= htmlspecialchars($subnet['cidr']) ?></h1>
                    <?php if (!empty($subnet['label'])): ?>
                        <p class="subnet-description"><?= htmlspecialchars($subnet['label']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="user-info">
                    <span>👤 <strong><?= getCurrentUser() ?></strong></span>
                    <form method="POST" action="<?= BASE_URL ?>/index.php?action=logout" style="display: inline;">
                        <button type="submit" class="btn btn-secondary" style="margin-left: 10px;">🚪 Esci</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="subnet-controls">
            <div class="controls-left">
                <input type="text" id="searchIP" placeholder="🔍 Cerca IP o descrizione..." value="<?= htmlspecialchars($search) ?>">
                <select id="sortBy">
                    <option value="ip" <?= $sort === 'ip' ? 'selected' : '' ?>>Ordina per IP</option>
                    <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Ordina per Stato</option>
                </select>
                <select id="sortOrder">
                    <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>Crescente</option>
                    <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Decrescente</option>
                </select>
            </div>
            <div class="controls-right">
                <button class="btn btn-info" onclick="editSubnetLabel('<?= $hash ?>', '<?= htmlspecialchars($subnet['label'] ?? '', ENT_QUOTES) ?>')">✏️ Modifica Label</button>
                <button class="btn btn-secondary" onclick="selectAll()">✅ Seleziona Tutti</button>
                <button class="btn btn-secondary" onclick="deselectAll()">❌ Deseleziona</button>
                <button class="btn btn-warning" onclick="bulkAction('used')">🔴 Segna Usati</button>
                <button class="btn btn-success" onclick="bulkAction('free')">🟢 Segna Liberi</button>
                <button class="btn btn-secondary" onclick="bulkAction('clear-description')">🧹 Pulisci Descrizioni</button>
                <button class="btn btn-danger" onclick="deleteSubnet('<?= $hash ?>')">🗑️ Elimina Subnet</button>
            </div>
        </div>

        <?php $stats = getSubnetStats($hash); ?>
        <div class="subnet-summary">
            <div class="stat-card">
                <span class="stat-number"><?= $stats['total'] ?></span>
                <span class="stat-text">IP Totali</span>
            </div>
            <div class="stat-card used">
                <span class="stat-number"><?= $stats['used'] ?></span>
                <span class="stat-text">IP Usati</span>
            </div>
            <div class="stat-card free">
                <span class="stat-number"><?= $stats['free'] ?></span>
                <span class="stat-text">IP Liberi</span>
            </div>
        </div>

        <div class="ip-table-container">
            <table class="ip-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllCheckbox"></th>
                        <th>Indirizzo IP</th>
                        <th>Stato</th>
                        <th>Descrizione</th>
                        <th>Ultima Modifica</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ips)): ?>
                        <tr>
                            <td colspan="5" class="no-results">
                                <?php if (!empty($search)): ?>
                                    🔍 Nessun risultato trovato per "<?= htmlspecialchars($search) ?>"
                                <?php else: ?>
                                    📝 Nessun IP configurato
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($ips as $ip => $data): ?>
                            <tr data-ip="<?= $ip ?>">
                                <td><input type="checkbox" class="ip-checkbox" value="<?= $ip ?>"></td>
                                <td class="ip-address"><?= $ip ?></td>
                                <td>
                                    <span class="status-badge <?= $data['status'] ?>" onclick="toggleIPStatus('<?= $hash ?>', '<?= $ip ?>')">
                                        <?= $data['status'] === 'used' ? '🔴 Usato' : '🟢 Libero' ?>
                                    </span>
                                </td>
                                <td>
                                    <input type="text" class="description-input" 
                                           value="<?= htmlspecialchars($data['description'] ?? '') ?>" 
                                           placeholder="Aggiungi descrizione..." 
                                           onkeypress="handleDescriptionKeypress(event, '<?= $hash ?>', '<?= $ip ?>')"
                                           onblur="saveDescription('<?= $hash ?>', '<?= $ip ?>', this.value)">
                                </td>
                                <td class="last-updated">
                                    <?= isset($data['updated_at']) ? date('d/m/Y H:i', $data['updated_at']) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn btn-secondary">← Precedente</a>
                <?php endif; ?>
                
                <span class="page-info">
                    Pagina <?= $page ?> di <?= $totalPages ?> 
                    (<?= number_format($totalIPs) ?> IP totali)
                </span>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&sort=<?= $sort ?>&order=<?= $order ?>" class="btn btn-secondary">Successiva →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal per modifica label subnet -->
    <div id="editLabelModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editLabelModal')">&times;</span>
            <h2>✏️ Modifica Label Subnet</h2>
            <form id="editLabelForm">
                <div class="form-group">
                    <label for="editLabel">Label Subnet</label>
                    <input type="text" id="editLabel" name="label" placeholder="Inserisci nuovo label..." maxlength="100">
                    <small>Lascia vuoto per rimuovere il label</small>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editLabelModal')">Annulla</button>
                    <button type="submit" class="btn btn-primary">Salva Modifiche</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>
    
    <script src="<?= BASE_URL ?>/public/app.js"></script>
    <script>
        // Imposta l'hash corrente per questa subnet
        window.currentHash = '<?= $hash ?>';
    </script>
</body>
</html>
<?php
}

function handleCreateSubnet() {
    // Verifica CSRF (implementazione semplice)
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido. Riprova tra qualche secondo.']);
        return;
    }

    $cidr = trim($_POST['cidr'] ?? '');
    $label = trim($_POST['label'] ?? '');
    $includeNetBcast = isset($_POST['include_net_bcast']);

    // Validazione
    if (empty($cidr)) {
        http_response_code(400);
        echo json_encode(['error' => 'CIDR è obbligatorio']);
        return;
    }

    if (!validate_cidr($cidr)) {
        http_response_code(400);
        echo json_encode(['error' => 'CIDR non valido. Usa formato IPv4/prefisso (es. 192.168.1.0/24)']);
        return;
    }

    $hash = md5($cidr);
    $subnets = getAllSubnets();

    if (isset($subnets[$hash])) {
        http_response_code(409);
        echo json_encode(['error' => 'Subnet già esistente']);
        return;
    }

    // Crea la subnet
    $subnet = [
        'cidr' => $cidr,
        'label' => $label,
        'created_at' => time(),
        'include_net_bcast' => $includeNetBcast
    ];

    // Espande gli IP
    try {
        $ips = expand_cidr($cidr, $includeNetBcast);
        $ipData = [];
        foreach ($ips as $ip) {
            $ipData[$ip] = [
                'status' => 'free',
                'description' => '',
                'updated_at' => time()
            ];
        }

        // Salva tutto atomicamente
        $subnets[$hash] = $subnet;
        saveSubnets($subnets);
        saveSubnetIPs($hash, $ipData);

        echo json_encode(['success' => true, 'hash' => $hash]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'espansione della subnet: ' . $e->getMessage()]);
    }
}

function handleToggleIP($hash) {
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ip = $input['ip'] ?? '';

    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        http_response_code(400);
        echo json_encode(['error' => 'IP non valido']);
        return;
    }

    try {
        $ips = loadSubnetIPs($hash);
        if (!isset($ips[$ip])) {
            http_response_code(404);
            echo json_encode(['error' => 'IP non trovato nella subnet']);
            return;
        }

        $ips[$ip]['status'] = $ips[$ip]['status'] === 'used' ? 'free' : 'used';
        $ips[$ip]['updated_at'] = time();

        saveSubnetIPs($hash, $ips);
        echo json_encode(['success' => true, 'status' => $ips[$ip]['status']]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()]);
    }
}

function handleDescribeIP($hash) {
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ip = $input['ip'] ?? '';
    $description = trim($input['description'] ?? '');

    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        http_response_code(400);
        echo json_encode(['error' => 'IP non valido']);
        return;
    }

    if (strlen($description) > 255) {
        http_response_code(400);
        echo json_encode(['error' => 'Descrizione troppo lunga (max 255 caratteri)']);
        return;
    }

    try {
        $ips = loadSubnetIPs($hash);
        if (!isset($ips[$ip])) {
            http_response_code(404);
            echo json_encode(['error' => 'IP non trovato nella subnet']);
            return;
        }

        $ips[$ip]['description'] = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');
        $ips[$ip]['updated_at'] = time();

        saveSubnetIPs($hash, $ips);
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()]);
    }
}

function handleBulkIP($hash) {
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido']);
        return;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $ips = $input['ips'] ?? [];
    $action = $input['action'] ?? '';

    if (empty($ips) || !is_array($ips)) {
        http_response_code(400);
        echo json_encode(['error' => 'Lista IP non valida']);
        return;
    }

    if (!in_array($action, ['used', 'free', 'clear-description'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Azione non valida']);
        return;
    }

    try {
        $subnetIPs = loadSubnetIPs($hash);
        $updated = 0;

        foreach ($ips as $ip) {
            if (!isset($subnetIPs[$ip])) continue;

            switch ($action) {
                case 'used':
                    $subnetIPs[$ip]['status'] = 'used';
                    break;
                case 'free':
                    $subnetIPs[$ip]['status'] = 'free';
                    break;
                case 'clear-description':
                    $subnetIPs[$ip]['description'] = '';
                    break;
            }
            $subnetIPs[$ip]['updated_at'] = time();
            $updated++;
        }

        saveSubnetIPs($hash, $subnetIPs);
        echo json_encode(['success' => true, 'updated' => $updated]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'operazione bulk: ' . $e->getMessage()]);
    }
}

function handleUpdateSubnetLabel($hash) {
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido']);
        return;
    }

    // Decodifica il JSON dal body
    $input = json_decode(file_get_contents('php://input'), true);
    $label = trim($input['label'] ?? '');

    try {
        $subnets = getAllSubnets();
        if (!isset($subnets[$hash])) {
            http_response_code(404);
            echo json_encode(['error' => 'Subnet non trovata']);
            return;
        }

        // Aggiorna il label
        $subnets[$hash]['label'] = $label;
        saveSubnets($subnets);

        echo json_encode(['success' => true, 'label' => $label]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'aggiornamento: ' . $e->getMessage()]);
    }
}

function handleDeleteSubnet($hash) {
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido']);
        return;
    }

    try {
        $subnets = getAllSubnets();
        if (!isset($subnets[$hash])) {
            http_response_code(404);
            echo json_encode(['error' => 'Subnet non trovata']);
            return;
        }

        // Elimina la subnet dalla lista
        unset($subnets[$hash]);
        saveSubnets($subnets);

        // Elimina il file degli IP
        $ipFile = "data/ips/{$hash}.json";
        if (file_exists($ipFile)) {
            unlink($ipFile);
        }

        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'eliminazione: ' . $e->getMessage()]);
    }
}

function handleExport() {
    try {
        $export = [
            'version' => '1.0',
            'exported_at' => date('c'),
            'subnets' => []
        ];

        $subnets = getAllSubnets();
        foreach ($subnets as $hash => $subnet) {
            $ips = loadSubnetIPs($hash);
            $export['subnets'][] = [
                'cidr' => $subnet['cidr'],
                'label' => $subnet['label'] ?? '',
                'created_at' => $subnet['created_at'],
                'include_net_bcast' => $subnet['include_net_bcast'] ?? false,
                'ips' => $ips
            ];
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="ipam_export_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'esportazione: ' . $e->getMessage()]);
    }
}

function handleImport() {
    if (!checkRateLimit()) {
        http_response_code(429);
        echo json_encode(['error' => 'Troppo rapido']);
        return;
    }

    if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File non caricato correttamente']);
        return;
    }

    $jsonData = file_get_contents($_FILES['import_file']['tmp_name']);
    $data = json_decode($jsonData, true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'File JSON non valido']);
        return;
    }

    // Validazione schema base
    if (!isset($data['subnets']) || !is_array($data['subnets'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Schema JSON non valido: manca array "subnets"']);
        return;
    }

    try {
        $subnets = getAllSubnets();
        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($data['subnets'] as $subnetData) {
            if (!isset($subnetData['cidr']) || !validate_cidr($subnetData['cidr'])) {
                $errors[] = "CIDR non valido: " . ($subnetData['cidr'] ?? 'mancante');
                continue;
            }

            $hash = md5($subnetData['cidr']);
            
            // Se subnet esiste già, chiedi conferma (per ora skippiamo)
            if (isset($subnets[$hash])) {
                $skipped++;
                continue;
            }

            // Importa la subnet
            $subnet = [
                'cidr' => $subnetData['cidr'],
                'label' => $subnetData['label'] ?? '',
                'created_at' => $subnetData['created_at'] ?? time(),
                'include_net_bcast' => $subnetData['include_net_bcast'] ?? false
            ];

            $subnets[$hash] = $subnet;

            // Importa gli IP se presenti
            if (isset($subnetData['ips']) && is_array($subnetData['ips'])) {
                saveSubnetIPs($hash, $subnetData['ips']);
            } else {
                // Genera gli IP dalla subnet
                $ips = expand_cidr($subnet['cidr'], $subnet['include_net_bcast']);
                $ipData = [];
                foreach ($ips as $ip) {
                    $ipData[$ip] = [
                        'status' => 'free',
                        'description' => '',
                        'updated_at' => time()
                    ];
                }
                saveSubnetIPs($hash, $ipData);
            }

            $imported++;
        }

        saveSubnets($subnets);

        $result = ['success' => true, 'imported' => $imported, 'skipped' => $skipped];
        if (!empty($errors)) {
            $result['errors'] = $errors;
        }

        echo json_encode($result);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Errore durante l\'importazione: ' . $e->getMessage()]);
    }
}

function checkRateLimit() {
    // Rate limiting semplice basato su sessione
    $now = time();
    if (!isset($_SESSION['last_action'])) {
        $_SESSION['last_action'] = $now;
        return true;
    }
    
    if ($now - $_SESSION['last_action'] < 1) {
        return false; // Max 1 azione al secondo
    }
    
    $_SESSION['last_action'] = $now;
    return true;
}

/**
 * Mostra la pagina di login
 */
function showLogin() {
    $error = '';
    
    // Se già autenticato, reindirizza alla dashboard
    if (isAuthenticated()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IPAM</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/style.css">
    <style>
        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #718096;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #4a5568;
        }
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .login-btn {
            width: 100%;
            background: #667eea;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s ease;
        }
        .login-btn:hover {
            background: #5a67d8;
        }
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .credentials-info {
            background: #e6fffa;
            color: #285e61;
            padding: 12px;
            border-radius: 6px;
            margin-top: 1rem;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-form">
            <div class="login-header">
                <h1>🔐 Accesso IPAM</h1>
                <p>Inserisci le tue credenziali per continuare</p>
            </div>

            <?php if (isset($_SESSION['login_error'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($_SESSION['login_error']) ?>
                </div>
                <?php unset($_SESSION['login_error']); ?>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/index.php?action=login">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                
                <div class="form-group">
                    <label for="username">Nome Utente</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>

                <button type="submit" class="login-btn">🚀 Accedi</button>
            </form>

        </div>
    </div>
</body>
</html>
<?php
}

/**
 * Gestisce il processo di login
 */
function handleLogin() {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    $userIP = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Verifica CSRF token
    if (!verifyCSRFToken($csrfToken)) {
        $_SESSION['login_error'] = 'Token di sicurezza non valido.';
        header('Location: ' . BASE_URL . '/index.php?action=login');
        exit;
    }

    // Verifica rate limiting
    if (!canAttemptLogin($userIP)) {
        $_SESSION['login_error'] = 'Troppi tentativi falliti. Riprova tra 15 minuti.';
        header('Location: ' . BASE_URL . '/index.php?action=login');
        exit;
    }

    // Verifica credenziali
    if (login($username, $password)) {
        // Login riuscito
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    } else {
        // Login fallito
        recordFailedLogin($userIP);
        $_SESSION['login_error'] = 'Nome utente o password non corretti.';
        header('Location: ' . BASE_URL . '/index.php?action=login');
        exit;
    }
}

/**
 * Mostra tutti gli IP usati di tutte le subnet
 */
function showUsedIPs() {
    $subnets = getAllSubnets();
    $allUsedIPs = [];
    
    // Raccogli tutti gli IP usati da tutte le subnet
    foreach ($subnets as $hash => $subnet) {
        $ips = getSubnetIPs($hash);
        foreach ($ips as $ip => $data) {
            if ($data['status'] === 'used') {
                $allUsedIPs[] = [
                    'ip' => $ip,
                    'subnet' => $subnet['cidr'],
                    'subnet_label' => $subnet['label'] ?? '',
                    'description' => $data['description'] ?? '',
                    'subnet_hash' => $hash
                ];
            }
        }
    }
    
    // Ordina per IP
    usort($allUsedIPs, function($a, $b) {
        return ip2long($a['ip']) - ip2long($b['ip']);
    });
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Usati - IPAM</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/public/style.css">
</head>
<body>
    <div class="container">
        <header>
            <div class="header-content">
                <div>
                    <h1>📋 IP Usati - IPAM</h1>
                    <p>Elenco completo di tutti gli indirizzi IP utilizzati</p>
                </div>
                <div class="user-info">
                    <span>👤 <strong><?= getCurrentUser() ?></strong></span>
                    <form method="POST" action="<?= BASE_URL ?>/index.php?action=logout" style="display: inline;">
                        <button type="submit" class="btn btn-secondary" style="margin-left: 10px;">🚪 Esci</button>
                    </form>
                </div>
            </div>
        </header>

        <div class="actions">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-secondary">⬅️ Torna alla Dashboard</a>
        </div>

        <div class="search-box">
            <input type="text" id="usedIpSearch" placeholder="🔍 Cerca IP, subnet o descrizione...">
        </div>

        <div class="ip-table-container">
            <?php if (empty($allUsedIPs)): ?>
                <div class="empty-state">
                    <h3>📝 Nessun IP utilizzato</h3>
                    <p>Non ci sono indirizzi IP marcati come usati nelle subnet configurate</p>
                </div>
            <?php else: ?>
                <table class="ip-table" id="usedIpsTable">
                    <thead>
                        <tr>
                            <th>Indirizzo IP</th>
                            <th>Subnet</th>
                            <th>Label Subnet</th>
                            <th>Descrizione</th>
                            <th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsedIPs as $ipData): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($ipData['ip']) ?></strong>
                                </td>
                                <td>
                                    <code><?= htmlspecialchars($ipData['subnet']) ?></code>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ipData['subnet_label']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ipData['description']) ?>
                                </td>
                                <td>
                                    <a href="<?= BASE_URL ?>/index.php?action=subnets/<?= $ipData['subnet_hash'] ?>" 
                                       class="btn btn-info btn-sm">👁️ Visualizza Subnet</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="used-ips-stats">
                    <p><strong>Totale IP utilizzati:</strong> <?= count($allUsedIPs) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/public/app.js"></script>
    <script>
        // Ricerca in tempo reale per gli IP usati
        document.getElementById('usedIpSearch').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('usedIpsTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const ip = row.cells[0].textContent.toLowerCase();
                const subnet = row.cells[1].textContent.toLowerCase();
                const label = row.cells[2].textContent.toLowerCase();
                const description = row.cells[3].textContent.toLowerCase();
                
                if (ip.includes(searchTerm) || 
                    subnet.includes(searchTerm) || 
                    label.includes(searchTerm) || 
                    description.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
<?php
}

/**
 * Gestisce il logout
 */
function handleLogout() {
    logout();
    header('Location: ' . BASE_URL . '/index.php?action=login');
    exit;
}
?>
