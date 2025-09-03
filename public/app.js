// Variabili globali
let selectedIPs = new Set();

// Rileva automaticamente il base path dell'applicazione
function getBasePath() {
    const path = window.location.pathname;
    const scriptName = document.querySelector('script[src*="app.js"]').src;
    const scriptPath = new URL(scriptName).pathname;
    return scriptPath.replace('/public/app.js', '');
}

// Base path per le chiamate API
const BASE_PATH = getBasePath();

// Inizializzazione quando il DOM è caricato
document.addEventListener('DOMContentLoaded', function() {
    initializeEventListeners();
    
    // Auto-cleanup per evitare accumulazione di listeners
    window.addEventListener('beforeunload', function() {
        selectedIPs.clear();
    });
});

/**
 * Inizializza tutti gli event listeners
 */
function initializeEventListeners() {
    // Bottone aggiungi subnet
    const addSubnetBtn = document.getElementById('addSubnetBtn');
    if (addSubnetBtn) {
        addSubnetBtn.addEventListener('click', () => openModal('addSubnetModal'));
    }
    
    // Bottone IP usati
    const usedIpsBtn = document.getElementById('usedIpsBtn');
    if (usedIpsBtn) {
        usedIpsBtn.addEventListener('click', () => {
            window.location.href = `${BASE_PATH}/index.php?action=used-ips`;
        });
    }
    
    // Form aggiungi subnet
    const addSubnetForm = document.getElementById('addSubnetForm');
    if (addSubnetForm) {
        addSubnetForm.addEventListener('submit', handleAddSubnet);
    }
    
    // Search input nella dashboard
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                window.location.search = this.value ? `?search=${encodeURIComponent(this.value)}` : '';
            }, 500);
        });
    }
    
    // Search input nella vista subnet
    const searchIP = document.getElementById('searchIP');
    if (searchIP) {
        let searchTimeout;
        searchIP.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                updateURL({ search: this.value, page: 1 });
            }, 500);
        });
    }
    
    // Ordinamento
    const sortBy = document.getElementById('sortBy');
    const sortOrder = document.getElementById('sortOrder');
    if (sortBy && sortOrder) {
        sortBy.addEventListener('change', () => updateURL({ sort: sortBy.value, page: 1 }));
        sortOrder.addEventListener('change', () => updateURL({ order: sortOrder.value, page: 1 }));
    }
    
    // Checkbox seleziona tutto
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.ip-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
                if (this.checked) {
                    selectedIPs.add(cb.value);
                } else {
                    selectedIPs.delete(cb.value);
                }
            });
        });
    }
    
    // Checkbox singoli IP
    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('ip-checkbox')) {
            if (e.target.checked) {
                selectedIPs.add(e.target.value);
            } else {
                selectedIPs.delete(e.target.value);
            }
        }
    });
    
    // Export
    const exportBtn = document.getElementById('exportBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', handleExport);
    }
    
    // Import
    const importBtn = document.getElementById('importBtn');
    const importFile = document.getElementById('importFile');
    if (importBtn && importFile) {
        importBtn.addEventListener('click', () => importFile.click());
        importFile.addEventListener('change', handleImport);
    }
}

/**
 * Apre un modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

/**
 * Chiude un modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Gestisce l'aggiunta di una nuova subnet
 */
async function handleAddSubnet(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch(BASE_PATH + '/index.php?action=subnets', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('✅ Subnet creata con successo!', 'success');
            closeModal('addSubnetModal');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore di connessione', 'error');
    }
}

/**
 * Naviga alla vista di una subnet
 */
function viewSubnet(hash) {
    window.location.href = `${BASE_PATH}/index.php?action=subnets/${hash}`;
}

/**
 * Toggle dello stato di un IP
 */
async function toggleIPStatus(hash, ip) {
    // Ottieni l'hash dalla URL corrente se non fornito
    if (!hash) {
        // Ottieni hash dai parametri URL per hosting web
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const pathParts = action ? action.split('/') : [];
        if (pathParts[1] === 'subnets' && pathParts[2]) {
            hash = pathParts[2];
        }
    }
    
    if (!hash) {
        showToast('❌ Errore: Hash subnet non trovato', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${BASE_PATH}/index.php?action=ips/${hash}/toggle`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ip: ip })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            // Aggiorna l'interfaccia
            const statusBadge = document.querySelector(`tr[data-ip="${ip}"] .status-badge`);
            if (statusBadge) {
                statusBadge.className = `status-badge ${result.status}`;
                statusBadge.textContent = result.status === 'used' ? '🔴 Usato' : '🟢 Libero';
            }
            
            showToast('✅ Stato aggiornato', 'success');
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore di connessione', 'error');
    }
}

/**
 * Gestisce il tasto Enter nell'input descrizione
 */
function handleDescriptionKeypress(e, hash, ip) {
    if (e.key === 'Enter') {
        e.target.blur(); // Trigger onblur
    }
}

/**
 * Salva la descrizione di un IP
 */
async function saveDescription(hash, ip, description) {
    // Ottieni l'hash dalla URL corrente se non fornito
    if (!hash) {
        // Ottieni hash dai parametri URL per hosting web
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const pathParts = action ? action.split('/') : [];
        if (pathParts[1] === 'subnets' && pathParts[2]) {
            hash = pathParts[2];
        }
    }
    
    if (!hash) {
        showToast('❌ Errore: Hash subnet non trovato', 'error');
        return;
    }
    
    try {
        const response = await fetch(`${BASE_PATH}/index.php?action=ips/${hash}/describe`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ ip: ip, description: description })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            // Aggiorna timestamp ultima modifica
            const row = document.querySelector(`tr[data-ip="${ip}"]`);
            if (row) {
                const lastUpdatedCell = row.querySelector('.last-updated');
                if (lastUpdatedCell) {
                    lastUpdatedCell.textContent = new Date().toLocaleString('it-IT');
                }
            }
            
            showToast('✅ Descrizione salvata', 'success');
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore di connessione', 'error');
    }
}

/**
 * Seleziona tutti gli IP
 */
function selectAll() {
    const checkboxes = document.querySelectorAll('.ip-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = true;
        selectedIPs.add(cb.value);
    });
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = true;
    }
}

/**
 * Deseleziona tutti gli IP
 */
function deselectAll() {
    const checkboxes = document.querySelectorAll('.ip-checkbox');
    checkboxes.forEach(cb => {
        cb.checked = false;
        selectedIPs.delete(cb.value);
    });
    
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = false;
    }
}

/**
 * Esegue azione bulk sugli IP selezionati
 */
async function bulkAction(action) {
    if (selectedIPs.size === 0) {
        showToast('⚠️ Seleziona almeno un IP', 'info');
        return;
    }
    
    // Ottieni l'hash dalla URL corrente se currentHash non è definito
    let hash = window.currentHash;
    if (!hash) {
        // Ottieni hash dai parametri URL per hosting web
        const urlParams = new URLSearchParams(window.location.search);
        const action = urlParams.get('action');
        const pathParts = action ? action.split('/') : [];
        if (pathParts[1] === 'subnets' && pathParts[2]) {
            hash = pathParts[2];
        }
    }
    
    if (!hash) {
        showToast('❌ Errore: Hash subnet non trovato', 'error');
        return;
    }
    
    const actionNames = {
        'used': 'segnare come usati',
        'free': 'segnare come liberi',
        'clear-description': 'pulire le descrizioni'
    };
    
    if (!confirm(`Confermi di voler ${actionNames[action]} i ${selectedIPs.size} IP selezionati?`)) {
        return;
    }
    
    try {
        const response = await fetch(`${BASE_PATH}/index.php?action=ips/${hash}/bulk`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ 
                ips: Array.from(selectedIPs), 
                action: action 
            })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast(`✅ ${result.updated} IP aggiornati`, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore di connessione', 'error');
    }
}

/**
 * Elimina una subnet
 */
async function deleteSubnet(hash) {
    if (!confirm('⚠️ ATTENZIONE: Questa operazione eliminerà definitivamente la subnet e tutti i suoi IP. Continuare?')) {
        return;
    }
    
    if (!confirm('🚨 ULTIMA CONFERMA: I dati verranno persi per sempre. Sei assolutamente sicuro?')) {
        return;
    }
    
    try {
        const response = await fetch(`${BASE_PATH}/index.php?action=subnets/${hash}/delete`, {
            method: 'POST'
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('✅ Subnet eliminata', 'success');
            setTimeout(() => window.location.href = BASE_PATH + '/index.php', 1000);
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore di connessione', 'error');
    }
}

/**
 * Gestisce l'export dei dati
 */
function handleExport() {
    window.location.href = BASE_PATH + '/index.php?action=export';
}

/**
 * Gestisce l'import dei dati
 */
async function handleImport(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    if (!file.name.toLowerCase().endsWith('.json')) {
        showToast('❌ Seleziona un file JSON', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('import_file', file);
    
    try {
        const response = await fetch(BASE_PATH + '/index.php?action=import', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (response.ok) {
            let message = `✅ Import completato: ${result.imported} subnet importate`;
            if (result.skipped > 0) {
                message += `, ${result.skipped} ignorate (già esistenti)`;
            }
            
            showToast(message, 'success');
            
            if (result.errors && result.errors.length > 0) {
                console.warn('Errori durante import:', result.errors);
            }
            
            setTimeout(() => window.location.reload(), 2000);
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore durante l\'import', 'error');
    }
    
    // Reset del file input
    e.target.value = '';
}

/**
 * Aggiorna l'URL con nuovi parametri
 */
function updateURL(params) {
    const url = new URL(window.location);
    
    Object.keys(params).forEach(key => {
        if (params[key]) {
            url.searchParams.set(key, params[key]);
        } else {
            url.searchParams.delete(key);
        }
    });
    
    window.location.href = url.toString();
}

/**
 * Mostra un toast notification
 */
function showToast(message, type = 'info') {
    const toast = document.getElementById('toast');
    if (!toast) return;
    
    toast.textContent = message;
    toast.className = `toast ${type}`;
    
    // Forza il reflow per l'animazione
    toast.offsetHeight;
    
    toast.classList.add('show');
    
    setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}

// Chiudi modali cliccando fuori
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
});

// Gestisci tasto ESC per chiudere modali
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }
});

/**
 * Apre il modal per modificare il label di una subnet
 */
function editSubnetLabel(hash, currentLabel) {
    const modal = document.getElementById('editLabelModal');
    const input = document.getElementById('editLabel');
    
    if (modal && input) {
        input.value = currentLabel || '';
        modal.style.display = 'block';
        input.focus();
        
        // Memorizza l'hash corrente per il salvataggio
        modal.setAttribute('data-hash', hash);
    }
}

/**
 * Gestisce il submit del form di modifica label
 */
document.addEventListener('DOMContentLoaded', function() {
    const editLabelForm = document.getElementById('editLabelForm');
    if (editLabelForm) {
        editLabelForm.addEventListener('submit', handleEditLabelSubmit);
    }
});

async function handleEditLabelSubmit(e) {
    e.preventDefault();
    
    const modal = document.getElementById('editLabelModal');
    const input = document.getElementById('editLabel');
    const hash = modal.getAttribute('data-hash');
    
    if (!hash) {
        showToast('❌ Errore: Hash subnet non trovato', 'error');
        return;
    }
    
    const newLabel = input.value.trim();
    
    try {
        const response = await fetch(`${BASE_PATH}/index.php?action=subnets/${hash}/label`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ label: newLabel })
        });
        
        const result = await response.json();
        
        if (response.ok) {
            showToast('✅ Label aggiornato con successo', 'success');
            closeModal('editLabelModal');
            
            // Aggiorna il label nella pagina
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showToast(`❌ ${result.error}`, 'error');
        }
    } catch (error) {
        showToast('❌ Errore di connessione', 'error');
    }
}
