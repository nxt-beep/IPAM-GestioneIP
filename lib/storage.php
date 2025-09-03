<?php

/**
 * Libreria per la gestione atomica del storage JSON
 * Implementa file locking per operazioni concurrent-safe
 */

/**
 * Carica la lista delle subnet dal file JSON
 * @return array Array associativo delle subnet
 */
function loadSubnets() {
    $file = 'data/subnets.json';
    
    if (!file_exists($file)) {
        return [];
    }
    
    $handle = fopen($file, 'r');
    if (!$handle) {
        throw new Exception("Impossibile aprire il file delle subnet");
    }
    
    // Lock condiviso per lettura
    if (flock($handle, LOCK_SH)) {
        $contents = fread($handle, filesize($file));
        flock($handle, LOCK_UN);
        fclose($handle);
        
        $data = json_decode($contents, true);
        return $data ?: [];
    } else {
        fclose($handle);
        throw new Exception("Impossibile ottenere il lock per la lettura");
    }
}

/**
 * Salva la lista delle subnet nel file JSON
 * @param array $subnets Array associativo delle subnet
 * @throws Exception Se errore durante il salvataggio
 */
function saveSubnets($subnets) {
    $file = 'data/subnets.json';
    $tempFile = $file . '.tmp';
    
    $handle = fopen($tempFile, 'w');
    if (!$handle) {
        throw new Exception("Impossibile creare il file temporaneo");
    }
    
    // Lock esclusivo per scrittura
    if (flock($handle, LOCK_EX)) {
        $json = json_encode($subnets, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $bytesWritten = fwrite($handle, $json);
        
        flock($handle, LOCK_UN);
        fclose($handle);
        
        if ($bytesWritten === false) {
            unlink($tempFile);
            throw new Exception("Errore durante la scrittura del file");
        }
        
        // Rinomina atomicamente il file temporaneo
        if (!rename($tempFile, $file)) {
            unlink($tempFile);
            throw new Exception("Impossibile completare il salvataggio");
        }
    } else {
        fclose($handle);
        unlink($tempFile);
        throw new Exception("Impossibile ottenere il lock per la scrittura");
    }
}

/**
 * Carica gli IP di una subnet specifica
 * @param string $hash Hash MD5 del CIDR della subnet
 * @return array Array associativo degli IP
 */
function loadSubnetIPs($hash) {
    $file = "data/ips/{$hash}.json";
    
    if (!file_exists($file)) {
        return [];
    }
    
    $handle = fopen($file, 'r');
    if (!$handle) {
        throw new Exception("Impossibile aprire il file degli IP");
    }
    
    // Lock condiviso per lettura
    if (flock($handle, LOCK_SH)) {
        $contents = fread($handle, filesize($file));
        flock($handle, LOCK_UN);
        fclose($handle);
        
        $data = json_decode($contents, true);
        return $data ?: [];
    } else {
        fclose($handle);
        throw new Exception("Impossibile ottenere il lock per la lettura degli IP");
    }
}

/**
 * Salva gli IP di una subnet specifica
 * @param string $hash Hash MD5 del CIDR della subnet
 * @param array $ips Array associativo degli IP con i loro dati
 * @throws Exception Se errore durante il salvataggio
 */
function saveSubnetIPs($hash, $ips) {
    // Crea directory se non esiste
    if (!is_dir('data/ips')) {
        mkdir('data/ips', 0755, true);
    }
    
    $file = "data/ips/{$hash}.json";
    $tempFile = $file . '.tmp';
    
    $handle = fopen($tempFile, 'w');
    if (!$handle) {
        throw new Exception("Impossibile creare il file temporaneo per gli IP");
    }
    
    // Lock esclusivo per scrittura
    if (flock($handle, LOCK_EX)) {
        $json = json_encode($ips, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $bytesWritten = fwrite($handle, $json);
        
        flock($handle, LOCK_UN);
        fclose($handle);
        
        if ($bytesWritten === false) {
            unlink($tempFile);
            throw new Exception("Errore durante la scrittura del file IP");
        }
        
        // Rinomina atomicamente il file temporaneo
        if (!rename($tempFile, $file)) {
            unlink($tempFile);
            throw new Exception("Impossibile completare il salvataggio degli IP");
        }
    } else {
        fclose($handle);
        unlink($tempFile);
        throw new Exception("Impossibile ottenere il lock per la scrittura degli IP");
    }
}

/**
 * Verifica se un file di subnet esiste
 * @param string $hash Hash della subnet
 * @return bool True se esiste
 */
function subnetExists($hash) {
    return file_exists("data/ips/{$hash}.json");
}

/**
 * Elimina il file degli IP di una subnet
 * @param string $hash Hash della subnet
 * @return bool True se eliminato con successo
 */
function deleteSubnetIPFile($hash) {
    $file = "data/ips/{$hash}.json";
    
    if (file_exists($file)) {
        return unlink($file);
    }
    
    return true; // File già inesistente
}

/**
 * Ottiene la dimensione del file di una subnet in bytes
 * @param string $hash Hash della subnet
 * @return int Dimensione in bytes, 0 se file non esiste
 */
function getSubnetFileSize($hash) {
    $file = "data/ips/{$hash}.json";
    return file_exists($file) ? filesize($file) : 0;
}

/**
 * Esegue backup di tutti i dati in un archivio
 * @param string $backupDir Directory di destinazione del backup
 * @return string Path del file di backup creato
 * @throws Exception Se errore durante il backup
 */
function createBackup($backupDir = 'data/backups') {
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = "{$backupDir}/ipam_backup_{$timestamp}.json";
    
    $backup = [
        'timestamp' => time(),
        'version' => '1.0',
        'subnets' => loadSubnets()
    ];
    
    // Aggiungi tutti i file IP
    foreach ($backup['subnets'] as $hash => $subnet) {
        $backup['subnet_ips'][$hash] = loadSubnetIPs($hash);
    }
    
    $handle = fopen($backupFile, 'w');
    if (!$handle) {
        throw new Exception("Impossibile creare il file di backup");
    }
    
    if (flock($handle, LOCK_EX)) {
        $json = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        fwrite($handle, $json);
        flock($handle, LOCK_UN);
        fclose($handle);
        
        return $backupFile;
    } else {
        fclose($handle);
        throw new Exception("Impossibile scrivere il file di backup");
    }
}

/**
 * Pulisce i file temporanei rimasti da operazioni interrotte
 * @return int Numero di file temporanei eliminati
 */
function cleanupTempFiles() {
    $cleaned = 0;
    $patterns = ['data/*.tmp', 'data/ips/*.tmp'];
    
    foreach ($patterns as $pattern) {
        $files = glob($pattern);
        foreach ($files as $file) {
            // Elimina file temporanei più vecchi di 1 ora
            if (time() - filemtime($file) > 3600) {
                if (unlink($file)) {
                    $cleaned++;
                }
            }
        }
    }
    
    return $cleaned;
}
?>
