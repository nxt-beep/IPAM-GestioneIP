<?php

/**
 * Libreria per la gestione di subnet e indirizzi IP
 * Contiene funzioni per parsing CIDR, espansione IP e validazioni
 */

/**
 * Valida un indirizzo CIDR IPv4
 * @param string $cidr Indirizzo CIDR (es. 192.168.1.0/24)
 * @return bool True se valido, false altrimenti
 */
function validate_cidr($cidr) {
    // Regex per formato CIDR base
    if (!preg_match('/^(\d{1,3}\.){3}\d{1,3}\/\d{1,2}$/', $cidr)) {
        return false;
    }
    
    list($network, $prefix) = explode('/', $cidr);
    
    // Valida l'indirizzo di rete
    if (!filter_var($network, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }
    
    // Valida il prefisso (0-32 per IPv4)
    $prefix = (int)$prefix;
    if ($prefix < 0 || $prefix > 32) {
        return false;
    }
    
    // Valida che ogni ottetto sia tra 0-255
    $octets = explode('.', $network);
    foreach ($octets as $octet) {
        $octet = (int)$octet;
        if ($octet < 0 || $octet > 255) {
            return false;
        }
    }
    
    return true;
}

/**
 * Espande un CIDR in una lista ordinata di indirizzi IP
 * @param string $cidr Indirizzo CIDR (es. 192.168.1.0/24)
 * @param bool $includeNetBcast Se includere network e broadcast address
 * @return array Lista ordinata di indirizzi IP
 * @throws Exception Se CIDR non valido o subnet troppo grande
 */
function expand_cidr($cidr, $includeNetBcast = false) {
    if (!validate_cidr($cidr)) {
        throw new Exception("CIDR non valido: $cidr");
    }
    
    list($network, $prefix) = explode('/', $cidr);
    $prefix = (int)$prefix;
    
    // Limite di sicurezza per evitare espansioni troppo grandi
    $maxIPs = 65536; // /16 è il massimo pratico
    $numIPs = pow(2, 32 - $prefix);
    
    if ($numIPs > $maxIPs) {
        throw new Exception("Subnet troppo grande. Massimo supportato: /16");
    }
    
    // Converte l'indirizzo di rete in formato long
    $networkLong = ip2long($network);
    if ($networkLong === false) {
        throw new Exception("Impossibile convertire l'indirizzo di rete");
    }
    
    // Calcola l'indirizzo di rete reale applicando la maschera
    $mask = ~((1 << (32 - $prefix)) - 1);
    $networkLong = $networkLong & $mask;
    
    $ips = [];
    $start = $includeNetBcast ? 0 : 1;
    $end = $includeNetBcast ? $numIPs - 1 : $numIPs - 2;
    
    // Per subnet /31 e /32 include sempre tutti gli IP
    if ($prefix >= 31) {
        $start = 0;
        $end = $numIPs - 1;
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $ip = long2ip($networkLong + $i);
        if ($ip !== false) {
            $ips[] = $ip;
        }
    }
    
    return $ips;
}

/**
 * Ottiene tutte le subnet dal file di storage
 * @return array Array associativo hash => subnet_data
 */
function getAllSubnets() {
    return loadSubnets();
}

/**
 * Ottiene gli IP di una subnet con paginazione e filtri
 * @param string $hash Hash MD5 del CIDR
 * @param int $page Numero pagina (1-based)
 * @param int $perPage IP per pagina
 * @param string $search Stringa di ricerca
 * @param string $sort Campo di ordinamento (ip|status)
 * @param string $order Ordine (asc|desc)
 * @return array Array di IP filtrati e ordinati
 */
function getSubnetIPs($hash, $page = 1, $perPage = 256, $search = '', $sort = 'ip', $order = 'asc') {
    $allIPs = loadSubnetIPs($hash);
    
    // Applica filtro di ricerca
    if (!empty($search)) {
        $allIPs = array_filter($allIPs, function($data, $ip) use ($search) {
            return stripos($ip, $search) !== false || 
                   stripos($data['description'] ?? '', $search) !== false;
        }, ARRAY_FILTER_USE_BOTH);
    }
    
    // Ordinamento
    $sortFunc = function($a, $b) use ($sort, $order) {
        switch ($sort) {
            case 'status':
                $valA = $a[1]['status'];
                $valB = $b[1]['status'];
                break;
            case 'ip':
            default:
                $valA = ip2long($a[0]);
                $valB = ip2long($b[0]);
                break;
        }
        
        $result = $valA <=> $valB;
        return $order === 'desc' ? -$result : $result;
    };
    
    // Converte in array per ordinamento
    $sortableIPs = [];
    foreach ($allIPs as $ip => $data) {
        $sortableIPs[] = [$ip, $data];
    }
    
    usort($sortableIPs, $sortFunc);
    
    // Paginazione
    $offset = ($page - 1) * $perPage;
    $pageIPs = array_slice($sortableIPs, $offset, $perPage);
    
    // Riconverte in formato associativo
    $result = [];
    foreach ($pageIPs as $item) {
        $result[$item[0]] = $item[1];
    }
    
    return $result;
}

/**
 * Conta il numero totale di IP in una subnet (con filtro opzionale)
 * @param string $hash Hash della subnet
 * @param string $search Filtro di ricerca opzionale
 * @return int Numero di IP
 */
function getSubnetIPCount($hash, $search = '') {
    $allIPs = loadSubnetIPs($hash);
    
    if (!empty($search)) {
        $allIPs = array_filter($allIPs, function($data, $ip) use ($search) {
            return stripos($ip, $search) !== false || 
                   stripos($data['description'] ?? '', $search) !== false;
        }, ARRAY_FILTER_USE_BOTH);
    }
    
    return count($allIPs);
}

/**
 * Ottiene statistiche di utilizzo per una subnet
 * @param string $hash Hash della subnet
 * @return array Array con contatori total, used, free
 */
function getSubnetStats($hash) {
    $ips = loadSubnetIPs($hash);
    
    $stats = [
        'total' => count($ips),
        'used' => 0,
        'free' => 0
    ];
    
    foreach ($ips as $data) {
        if ($data['status'] === 'used') {
            $stats['used']++;
        } else {
            $stats['free']++;
        }
    }
    
    return $stats;
}

/**
 * Converte un indirizzo IP in formato numerico per ordinamento
 * @param string $ip Indirizzo IP
 * @return int Valore numerico dell'IP
 */
function ipToSortable($ip) {
    return ip2long($ip);
}

/**
 * Valida un singolo indirizzo IPv4
 * @param string $ip Indirizzo IP da validare
 * @return bool True se valido
 */
function validate_ipv4($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
}

/**
 * Ottiene informazioni di rete per un CIDR
 * @param string $cidr CIDR da analizzare
 * @return array Array con network, broadcast, mask, etc.
 */
function getCIDRInfo($cidr) {
    if (!validate_cidr($cidr)) {
        return null;
    }
    
    list($network, $prefix) = explode('/', $cidr);
    $prefix = (int)$prefix;
    
    $networkLong = ip2long($network);
    $mask = ~((1 << (32 - $prefix)) - 1);
    $networkLong = $networkLong & $mask;
    
    $numIPs = pow(2, 32 - $prefix);
    $broadcastLong = $networkLong + $numIPs - 1;
    
    return [
        'network' => long2ip($networkLong),
        'broadcast' => long2ip($broadcastLong),
        'mask' => long2ip($mask),
        'prefix' => $prefix,
        'num_ips' => $numIPs,
        'num_hosts' => max(0, $numIPs - 2) // Esclude network e broadcast
    ];
}
?>
