<?php
    session_start();

    // Redirect if not logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.html');
        exit;
    }

  // Load inventory data
  $inventoryFile = 'inventory.json';
  $items = [];
  if (file_exists($inventoryFile)) {
      $items = json_decode(file_get_contents($inventoryFile), true);
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lager Übersicht</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Roboto:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css" />
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body>
<div class="container">
 <div class="container">
  <!-- Header -->
  <div class="header">
    <div class="header-top">
      <h1>🏺 Lager Übersicht</h1>
      <button class="logout-btn" onclick="logout()">Logout</button>
    </div>
    
    <button class="scan-button" onclick="openScanner()">
      <span>📸 Artikel scannen</span>
    </button>
  </div>

  <!-- Stats -->
  <div class="stats-bar">
    <div class="stat-item">
      <div class="stat-value" id="totalItems">-</div>
      <div class="stat-label">Artikel</div>
    </div>
    <div class="stat-item">
      <div class="stat-value" id="totalQuantity">-</div>
      <div class="stat-label">Gesamt Anzahl</div>
    </div>
    <div class="stat-item">
      <div class="stat-value" id="newToday">-</div>
      <div class="stat-label">Neu heute</div>
    </div>
  </div>

  <!-- Inventory List -->
  <div class="inventory-section">
    <h2 class="section-title">Vorrat</h2>
    <div class="inventory-list" id="inventoryList">
      <div class="loading">⏳ Lade Inventar...</div>
    </div>
  </div>
</div>

<!-- Scanner Modal -->
<div class="scanner-modal" id="scannerModal">
  <div class="scanner-container">
    <div class="scanner-header">
      <h3>Barcode Scanner</h3>
      <button class="close-scanner" onclick="closeScanner()">&times;</button>
    </div>
    <div id="reader"></div>
    <div class="scanner-result" id="scannerResult"></div>
  </div>
</div>

<!-- Product Name Modal -->
<div class="product-modal" id="productModal">
  <div class="product-modal-content">
    <h3>Produkt nicht gefunden</h3>
    <p>Barcode: <span id="scannedBarcode"></span></p>
    <div class="form-group">
      <label for="productName">Produktname eingeben:</label>
      <input type="text" id="productName" placeholder="z.B. Milch, Brot, etc." required />
    </div>
    <div class="form-group">
      <label for="productQuantity">Menge:</label>
      <input type="number" id="productQuantity" value="1" min="1" />
    </div>
    <div class="modal-buttons">
      <button class="btn-cancel" onclick="closeProductModal()">Abbrechen</button>
      <button class="btn-save" onclick="saveProduct()">Speichern</button>
    </div>
  </div>
</div>
<script>
let items = [];
let html5QrCode = null;
let currentBarcode = null;

// Format date
function formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('de-DE', { 
    day: '2-digit', 
    month: '2-digit', 
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

// Load inventory from database
async function loadInventory() {
  try {
    const response = await fetch('db_get.php');
    const data = await response.json();
    
    if (data.success) {
      items = data.items;
      updateStats(data);
      renderInventory();
    } else {
      showError('Fehler beim Laden: ' + (data.error || 'Unbekannter Fehler'));
    }
  } catch (error) {
    console.error('Error loading inventory:', error);
    showError('Verbindungsfehler - Konnte Daten nicht laden');
  }
}

// Update statistics
function updateStats(data) {
  document.getElementById('totalItems').textContent = data.total_items || 0;
  document.getElementById('totalQuantity').textContent = data.total_quantity || 0;
  
  const newToday = items.filter(item => item.is_new == 1).length;
  document.getElementById('newToday').textContent = newToday;
}

// Render inventory
function renderInventory() {
  const listElement = document.getElementById('inventoryList');
  
  if (items.length === 0) {
    listElement.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <p>Noch keine Artikel im Inventar</p>
        <p style="font-size: 0.9em; margin-top: 10px;">Artikel werden automatisch synchronisiert</p>
      </div>
    `;
    return;
  }
  
  listElement.innerHTML = items.map(item => `
    <div class="inventory-item">
      <div class="item-icon">${item.icon}</div>
      <div class="item-info">
        <div class="item-name">
          ${item.display_name}
          ${item.is_new == 1 ? '<span class="new-badge">NEU</span>' : ''}
        </div>
        <div class="item-date">Hinzugefügt: ${formatDate(item.date)}</div>
        ${item.category ? `<div class="item-date">Kategorie: ${item.category}</div>` : ''}
      </div>
      <div class="item-quantity">${item.quantity}x</div>
      <div class="item-actions">
        <button class="btn-increment" onclick="incrementItem(${item.id})" title="Anzahl erhöhen">+1</button>
        ${item.quantity > 1 ? `<button class="btn-decrement" onclick="decrementItem(${item.id})" title="Anzahl reduzieren">-1</button>` : ''}
        <button class="btn-remove" onclick="removeItem(${item.id})" title="Artikel entfernen">🗑️</button>
      </div>
    </div>
  `).join('');
}

// Increment item quantity
async function incrementItem(id) {
  try {
    const response = await fetch('db_set.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        id: id, 
        increment_only: true 
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      showToast(`Anzahl erhöht auf ${data.item.new_quantity}x`, 'success');
      loadInventory();
    } else {
      showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showToast('Verbindungsfehler', 'error');
  }
}

// Decrement item quantity
async function decrementItem(id) {
  if (!confirm('Anzahl um 1 reduzieren?')) return;
  
  try {
    const response = await fetch('db_set.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        id: id, 
        decrement_only: true 
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Show feedback
      const message = data.action === 'decremented' 
        ? `Anzahl reduziert auf ${data.item.new_quantity}x`
        : 'Artikel entfernt (letzte Einheit)';
      showToast(message, 'success');
      
      // Reload inventory
      loadInventory();
    } else {
      showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showToast('Verbindungsfehler', 'error');
  }
}

// Remove item completely
async function removeItem(id) {
  if (!confirm('Artikel wirklich komplett entfernen?')) return;
  
  try {
    const response = await fetch('db_set.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ 
        id: id, 
        decrement_only: false 
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      showToast('Artikel entfernt', 'success');
      loadInventory();
    } else {
      showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showToast('Verbindungsfehler', 'error');
  }
}

// Show error message
function showError(message) {
  const listElement = document.getElementById('inventoryList');
  listElement.innerHTML = `
    <div class="empty-state">
      <div class="empty-state-icon">❌</div>
      <p>${message}</p>
      <button onclick="loadInventory()" style="margin-top: 15px; padding: 10px 20px; background: linear-gradient(135deg, #d4af37 0%, #b8941f 100%); color: #2a1810; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
        Erneut versuchen
      </button>
    </div>
  `;
}

// Barcode scanner using html5-qrcode
function openScanner() {
  const modal = document.getElementById('scannerModal');
  const resultDiv = document.getElementById('scannerResult');

  modal.classList.add('active');
  resultDiv.textContent = '';

  html5QrCode = new Html5Qrcode("reader");

  const config = {
    fps: 10,
    qrbox: { width: 250, height: 150 },
    formatsToSupport: [
      Html5QrcodeSupportedFormats.EAN_13,
      Html5QrcodeSupportedFormats.EAN_8,
      Html5QrcodeSupportedFormats.UPC_A,
      Html5QrcodeSupportedFormats.UPC_E,
      Html5QrcodeSupportedFormats.CODE_128,
      Html5QrcodeSupportedFormats.CODE_39,
      Html5QrcodeSupportedFormats.CODE_93
    ]
  };

  html5QrCode.start(
    { facingMode: "environment" },
    config,
    onScanSuccess,
    onScanError
  ).catch(err => {
    console.error('Error starting scanner:', err);
    resultDiv.textContent = 'Kamera konnte nicht gestartet werden: ' + err;
  });
}

function onScanSuccess(decodedText, decodedResult) {
  // Stop scanning after successful read
  if (html5QrCode) {
    html5QrCode.stop().then(() => {
      processBarcode(decodedText);
    }).catch(err => {
      console.error('Error stopping scanner:', err);
      processBarcode(decodedText);
    });
  }
}

function onScanError(errorMessage) {
  // Ignore scan errors (happens frequently when no barcode in view)
}

async function processBarcode(barcode) {
  const resultDiv = document.getElementById('scannerResult');
  resultDiv.textContent = 'Verarbeite Barcode: ' + barcode;

  try {
    const response = await fetch('barcode_scan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ barcode: barcode })
    });

    const data = await response.json();

    if (data.success) {
      // Product added or updated
      closeScanner();
      showToast(data.message + ': ' + data.item.name, 'success');
      loadInventory();
    } else if (data.needs_name) {
      // Product not found, need user input
      closeScanner();
      showProductModal(barcode);
    } else {
      resultDiv.textContent = 'Fehler: ' + (data.error || 'Unbekannter Fehler');
    }
  } catch (error) {
    console.error('Error processing barcode:', error);
    resultDiv.textContent = 'Verbindungsfehler';
  }
}

function closeScanner() {
  const modal = document.getElementById('scannerModal');

  if (html5QrCode) {
    html5QrCode.stop().catch(err => {
      console.error('Error stopping scanner:', err);
    });
    html5QrCode = null;
  }

  modal.classList.remove('active');
}

// Product name modal functions
function showProductModal(barcode) {
  currentBarcode = barcode;
  document.getElementById('scannedBarcode').textContent = barcode;
  document.getElementById('productName').value = '';
  document.getElementById('productQuantity').value = '1';
  document.getElementById('productModal').classList.add('active');
  document.getElementById('productName').focus();
}

function closeProductModal() {
  document.getElementById('productModal').classList.remove('active');
  currentBarcode = null;
}

async function saveProduct() {
  const productName = document.getElementById('productName').value.trim();
  const quantity = parseInt(document.getElementById('productQuantity').value) || 1;

  if (!productName) {
    showToast('Bitte Produktnamen eingeben', 'error');
    return;
  }

  try {
    const response = await fetch('barcode_scan.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        barcode: currentBarcode,
        product_name: productName,
        quantity: quantity
      })
    });

    const data = await response.json();

    if (data.success) {
      closeProductModal();
      showToast('Produkt hinzugefügt: ' + productName, 'success');
      loadInventory();
    } else {
      showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
    }
  } catch (error) {
    console.error('Error saving product:', error);
    showToast('Verbindungsfehler', 'error');
  }
}

// Handle Enter key in product name input
document.addEventListener('DOMContentLoaded', function() {
  const productNameInput = document.getElementById('productName');
  if (productNameInput) {
    productNameInput.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        saveProduct();
      }
    });
  }
});

function logout() {
  if (confirm('Wirklich ausloggen?')) {
    window.location.href = 'logout.php';
  }
}

// Toast notification system
function showToast(message, type = 'info') {
  // Remove existing toast if any
  const existingToast = document.querySelector('.toast');
  if (existingToast) {
    existingToast.remove();
  }
  
  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);
  
  // Trigger animation
  setTimeout(() => toast.classList.add('show'), 10);
  
  // Remove after 3 seconds
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Initialize
loadInventory();

// Auto-refresh every 30 seconds
setInterval(loadInventory, 30000);
</script>
</body>
</html>