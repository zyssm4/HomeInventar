<?php
    session_start();

    // Redirect if not logged in
    if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header('Location: index.html');
        exit;
    }
?>

<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lager Übersicht</title>
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#2a1810">
<link rel="apple-touch-icon" href="icons/icon-192.png">
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

    <div class="action-buttons">
      <button class="scan-button" onclick="openScanner()">
        <span>📸 Scannen</span>
      </button>
      <button class="add-button" onclick="openManualAdd()">
        <span>➕ Hinzufügen</span>
      </button>
      <button class="stats-button" onclick="openAnalytics()">
        <span>📊 Statistik</span>
      </button>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-bar">
    <div class="stat-item">
      <div class="stat-value" id="totalItems">-</div>
      <div class="stat-label">Artikel</div>
    </div>
    <div class="stat-item">
      <div class="stat-value" id="totalQuantity">-</div>
      <div class="stat-label">Gesamt</div>
    </div>
    <div class="stat-item">
      <div class="stat-value" id="newToday">-</div>
      <div class="stat-label">Neu heute</div>
    </div>
  </div>

  <!-- Search and Filter Bar -->
  <div class="filter-bar">
    <div class="search-box">
      <input type="text" id="searchInput" placeholder="🔍 Suchen..." oninput="filterInventory()" />
    </div>
    <div class="filter-controls">
      <select id="categoryFilter" onchange="filterInventory()">
        <option value="">Alle Kategorien</option>
        <option value="Lebensmittel">Lebensmittel</option>
        <option value="Getränke">Getränke</option>
        <option value="Haushalt">Haushalt</option>
        <option value="Gescannt">Gescannt</option>
      </select>
      <select id="locationFilter" onchange="filterInventory()">
        <option value="">Alle Orte</option>
        <option value="Kühlschrank">Kühlschrank</option>
        <option value="Gefriertruhe">Gefriertruhe</option>
        <option value="Vorratskammer">Vorratskammer</option>
        <option value="Keller">Keller</option>
      </select>
      <select id="sortOption" onchange="filterInventory()">
        <option value="name_asc">Name A-Z</option>
        <option value="name_desc">Name Z-A</option>
        <option value="date_desc">Neueste zuerst</option>
        <option value="date_asc">Älteste zuerst</option>
        <option value="quantity_desc">Menge ↓</option>
        <option value="quantity_asc">Menge ↑</option>
      </select>
    </div>
    <div class="quick-mode-toggle">
      <label class="toggle-switch">
        <input type="checkbox" id="quickModeToggle" onchange="toggleQuickMode()">
        <span class="toggle-slider"></span>
      </label>
      <span class="toggle-label">⚡ Quick Mode</span>
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

<!-- Product Name Modal (for barcode scan) -->
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
    <div class="form-group">
      <label for="productLocation">Lagerort:</label>
      <select id="productLocation">
        <option value="Vorratskammer">Vorratskammer</option>
        <option value="Kühlschrank">Kühlschrank</option>
        <option value="Gefriertruhe">Gefriertruhe</option>
        <option value="Keller">Keller</option>
      </select>
    </div>
    <div class="modal-buttons">
      <button class="btn-cancel" onclick="closeProductModal()">Abbrechen</button>
      <button class="btn-save" onclick="saveProduct()">Speichern</button>
    </div>
  </div>
</div>

<!-- Manual Add Modal -->
<div class="product-modal" id="manualAddModal">
  <div class="product-modal-content">
    <h3>Artikel hinzufügen</h3>
    <div class="form-group">
      <label for="manualName">Produktname:</label>
      <input type="text" id="manualName" placeholder="z.B. Milch, Brot, etc." required />
    </div>
    <div class="form-group">
      <label for="manualQuantity">Menge:</label>
      <input type="number" id="manualQuantity" value="1" min="1" />
    </div>
    <div class="form-group">
      <label for="manualCategory">Kategorie:</label>
      <select id="manualCategory">
        <option value="Lebensmittel">Lebensmittel</option>
        <option value="Getränke">Getränke</option>
        <option value="Haushalt">Haushalt</option>
      </select>
    </div>
    <div class="form-group">
      <label for="manualLocation">Lagerort:</label>
      <select id="manualLocation">
        <option value="Vorratskammer">Vorratskammer</option>
        <option value="Kühlschrank">Kühlschrank</option>
        <option value="Gefriertruhe">Gefriertruhe</option>
        <option value="Keller">Keller</option>
      </select>
    </div>
    <div class="form-group">
      <label for="manualBarcode">Barcode (optional):</label>
      <input type="text" id="manualBarcode" placeholder="EAN/UPC Code" />
    </div>
    <div class="modal-buttons">
      <button class="btn-cancel" onclick="closeManualAdd()">Abbrechen</button>
      <button class="btn-save" onclick="saveManualItem()">Speichern</button>
    </div>
  </div>
</div>

<!-- Edit Item Modal -->
<div class="product-modal" id="editModal">
  <div class="product-modal-content">
    <h3>Artikel bearbeiten</h3>
    <input type="hidden" id="editItemId" />
    <div class="form-group">
      <label for="editName">Produktname:</label>
      <input type="text" id="editName" required />
    </div>
    <div class="form-group">
      <label for="editQuantity">Menge:</label>
      <input type="number" id="editQuantity" min="1" />
    </div>
    <div class="form-group">
      <label for="editCategory">Kategorie:</label>
      <select id="editCategory">
        <option value="Lebensmittel">Lebensmittel</option>
        <option value="Getränke">Getränke</option>
        <option value="Haushalt">Haushalt</option>
        <option value="Gescannt">Gescannt</option>
      </select>
    </div>
    <div class="form-group">
      <label for="editLocation">Lagerort:</label>
      <select id="editLocation">
        <option value="Vorratskammer">Vorratskammer</option>
        <option value="Kühlschrank">Kühlschrank</option>
        <option value="Gefriertruhe">Gefriertruhe</option>
        <option value="Keller">Keller</option>
      </select>
    </div>
    <div class="modal-buttons">
      <button class="btn-cancel" onclick="closeEditModal()">Abbrechen</button>
      <button class="btn-save" onclick="saveEditItem()">Speichern</button>
    </div>
  </div>
</div>

<!-- Analytics Modal -->
<div class="product-modal" id="analyticsModal">
  <div class="analytics-modal-content">
    <h3>📊 Verbrauchsstatistik</h3>
    <div class="analytics-grid">
      <div class="analytics-card">
        <div class="analytics-value" id="analyticsTotal">0</div>
        <div class="analytics-label">Gesamte Artikel</div>
      </div>
      <div class="analytics-card">
        <div class="analytics-value" id="analyticsQuantity">0</div>
        <div class="analytics-label">Gesamte Menge</div>
      </div>
      <div class="analytics-card">
        <div class="analytics-value" id="analyticsCategories">0</div>
        <div class="analytics-label">Kategorien</div>
      </div>
      <div class="analytics-card">
        <div class="analytics-value" id="analyticsLocations">0</div>
        <div class="analytics-label">Lagerorte</div>
      </div>
    </div>
    <div class="analytics-section">
      <h4>Top Artikel nach Menge</h4>
      <div id="topItems" class="top-items-list"></div>
    </div>
    <div class="analytics-section">
      <h4>Verteilung nach Lagerort</h4>
      <div id="locationStats" class="location-stats"></div>
    </div>
    <div class="modal-buttons">
      <button class="btn-save" onclick="closeAnalytics()">Schließen</button>
    </div>
  </div>
</div>

<script>
let items = [];
let filteredItems = [];
let html5QrCode = null;
let currentBarcode = null;
let quickMode = false;

// Touch tracking for swipe gestures
let touchStartX = 0;
let touchStartY = 0;
let touchCurrentItem = null;

// Toggle Quick Mode
function toggleQuickMode() {
  quickMode = document.getElementById('quickModeToggle').checked;
  if (quickMode) {
    showToast('Quick Mode aktiviert - Tippen = -1', 'info');
  } else {
    showToast('Quick Mode deaktiviert', 'info');
  }
}

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
      filterInventory();
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

// Filter and sort inventory
function filterInventory() {
  const searchTerm = document.getElementById('searchInput').value.toLowerCase();
  const categoryFilter = document.getElementById('categoryFilter').value;
  const locationFilter = document.getElementById('locationFilter').value;
  const sortOption = document.getElementById('sortOption').value;

  // Filter
  filteredItems = items.filter(item => {
    const matchesSearch = !searchTerm ||
      item.display_name.toLowerCase().includes(searchTerm) ||
      (item.name && item.name.toLowerCase().includes(searchTerm));
    const matchesCategory = !categoryFilter || item.category === categoryFilter;
    const matchesLocation = !locationFilter || item.location === locationFilter;
    return matchesSearch && matchesCategory && matchesLocation;
  });

  // Sort
  filteredItems.sort((a, b) => {
    switch (sortOption) {
      case 'name_asc':
        return (a.display_name || '').localeCompare(b.display_name || '');
      case 'name_desc':
        return (b.display_name || '').localeCompare(a.display_name || '');
      case 'date_desc':
        return new Date(b.date) - new Date(a.date);
      case 'date_asc':
        return new Date(a.date) - new Date(b.date);
      case 'quantity_desc':
        return b.quantity - a.quantity;
      case 'quantity_asc':
        return a.quantity - b.quantity;
      default:
        return 0;
    }
  });

  renderInventory();
}

// Render inventory
function renderInventory() {
  const listElement = document.getElementById('inventoryList');

  if (filteredItems.length === 0) {
    listElement.innerHTML = `
      <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <p>Keine Artikel gefunden</p>
        <p style="font-size: 0.9em; margin-top: 10px;">Versuche andere Suchkriterien</p>
      </div>
    `;
    return;
  }

  listElement.innerHTML = filteredItems.map(item => `
    <div class="inventory-item" data-id="${item.id}" onclick="handleItemClick(${item.id}, event)">
      <div class="swipe-indicator swipe-left-indicator">-1</div>
      <div class="item-content">
        <div class="item-icon">${item.icon}</div>
        <div class="item-info">
          <div class="item-name">
            ${item.display_name}
            ${item.is_new == 1 ? '<span class="new-badge">NEU</span>' : ''}
          </div>
          <div class="item-date">Hinzugefügt: ${formatDate(item.date)}</div>
          ${item.category ? `<div class="item-meta">📁 ${item.category}</div>` : ''}
          ${item.location ? `<div class="item-meta">📍 ${item.location}</div>` : ''}
        </div>
        <div class="item-quantity">${item.quantity}x</div>
        <div class="item-actions" onclick="event.stopPropagation()">
          <button class="btn-increment" onclick="incrementItem(${item.id})" title="Anzahl erhöhen">+1</button>
          ${item.quantity > 1 ? `<button class="btn-decrement" onclick="decrementItem(${item.id}, true)" title="Anzahl reduzieren">-1</button>` : ''}
          <button class="btn-remove" onclick="removeItem(${item.id})" title="Artikel entfernen">🗑️</button>
        </div>
      </div>
    </div>
  `).join('');

  // Add touch event listeners for swipe gestures
  document.querySelectorAll('.inventory-item').forEach(item => {
    item.addEventListener('touchstart', handleTouchStart, { passive: true });
    item.addEventListener('touchmove', handleTouchMove, { passive: false });
    item.addEventListener('touchend', handleTouchEnd);
  });
}

// Handle item click (Quick Mode or Edit)
function handleItemClick(id, event) {
  // Don't trigger if clicking on actions
  if (event.target.closest('.item-actions')) return;

  if (quickMode) {
    // Quick Mode: instant decrement
    decrementItem(id, true);
  } else {
    // Normal mode: open edit modal
    openEditModal(id);
  }
}

// Touch handlers for swipe gestures
function handleTouchStart(e) {
  touchStartX = e.touches[0].clientX;
  touchStartY = e.touches[0].clientY;
  touchCurrentItem = e.currentTarget;
  touchCurrentItem.classList.add('touching');
}

function handleTouchMove(e) {
  if (!touchCurrentItem) return;

  const touchX = e.touches[0].clientX;
  const touchY = e.touches[0].clientY;
  const diffX = touchStartX - touchX;
  const diffY = Math.abs(touchStartY - touchY);

  // Only allow horizontal swipes (not vertical scrolling)
  if (diffY > 30) {
    touchCurrentItem.classList.remove('touching', 'swiping-left');
    touchCurrentItem = null;
    return;
  }

  // Swipe left detection
  if (diffX > 20) {
    e.preventDefault();
    const swipeAmount = Math.min(diffX, 100);
    touchCurrentItem.style.transform = `translateX(-${swipeAmount}px)`;
    touchCurrentItem.classList.add('swiping-left');
  } else {
    touchCurrentItem.style.transform = '';
    touchCurrentItem.classList.remove('swiping-left');
  }
}

function handleTouchEnd(e) {
  if (!touchCurrentItem) return;

  const touchEndX = e.changedTouches[0].clientX;
  const diffX = touchStartX - touchEndX;

  // Reset visual state
  touchCurrentItem.style.transform = '';
  touchCurrentItem.classList.remove('touching', 'swiping-left');

  // If swiped left more than 80px, decrement
  if (diffX > 80) {
    const itemId = touchCurrentItem.dataset.id;
    touchCurrentItem.classList.add('swipe-complete');
    setTimeout(() => {
      decrementItem(itemId, true);
    }, 150);
  }

  touchCurrentItem = null;
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
async function decrementItem(id, skipConfirm = false) {
  if (!skipConfirm && !confirm('Anzahl um 1 reduzieren?')) return;

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
      const message = data.action === 'decremented'
        ? `Anzahl reduziert auf ${data.item.new_quantity}x`
        : 'Artikel entfernt (letzte Einheit)';
      showToast(message, 'success');
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

// Manual Add Modal
function openManualAdd() {
  document.getElementById('manualName').value = '';
  document.getElementById('manualQuantity').value = '1';
  document.getElementById('manualCategory').value = 'Lebensmittel';
  document.getElementById('manualLocation').value = 'Vorratskammer';
  document.getElementById('manualBarcode').value = '';
  document.getElementById('manualAddModal').classList.add('active');
  document.getElementById('manualName').focus();
}

function closeManualAdd() {
  document.getElementById('manualAddModal').classList.remove('active');
}

async function saveManualItem() {
  const name = document.getElementById('manualName').value.trim();
  const quantity = parseInt(document.getElementById('manualQuantity').value) || 1;
  const category = document.getElementById('manualCategory').value;
  const location = document.getElementById('manualLocation').value;
  const barcode = document.getElementById('manualBarcode').value.trim();

  if (!name) {
    showToast('Bitte Produktnamen eingeben', 'error');
    return;
  }

  try {
    const response = await fetch('item_manage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'add',
        name: name,
        quantity: quantity,
        category: category,
        location: location,
        barcode: barcode || null
      })
    });

    const data = await response.json();

    if (data.success) {
      closeManualAdd();
      showToast('Artikel hinzugefügt: ' + name, 'success');
      loadInventory();
    } else {
      showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showToast('Verbindungsfehler', 'error');
  }
}

// Edit Modal
function openEditModal(id) {
  const item = items.find(i => i.id == id);
  if (!item) return;

  document.getElementById('editItemId').value = id;
  document.getElementById('editName').value = item.display_name || item.name;
  document.getElementById('editQuantity').value = item.quantity;
  document.getElementById('editCategory').value = item.category || 'Lebensmittel';
  document.getElementById('editLocation').value = item.location || 'Vorratskammer';
  document.getElementById('editModal').classList.add('active');
}

function closeEditModal() {
  document.getElementById('editModal').classList.remove('active');
}

async function saveEditItem() {
  const id = document.getElementById('editItemId').value;
  const name = document.getElementById('editName').value.trim();
  const quantity = parseInt(document.getElementById('editQuantity').value) || 1;
  const category = document.getElementById('editCategory').value;
  const location = document.getElementById('editLocation').value;

  if (!name) {
    showToast('Bitte Produktnamen eingeben', 'error');
    return;
  }

  try {
    const response = await fetch('item_manage.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'edit',
        id: id,
        name: name,
        quantity: quantity,
        category: category,
        location: location
      })
    });

    const data = await response.json();

    if (data.success) {
      closeEditModal();
      showToast('Artikel aktualisiert', 'success');
      loadInventory();
    } else {
      showToast('Fehler: ' + (data.error || 'Unbekannter Fehler'), 'error');
    }
  } catch (error) {
    console.error('Error:', error);
    showToast('Verbindungsfehler', 'error');
  }
}

// Analytics Modal
function openAnalytics() {
  // Calculate statistics
  const totalItems = items.length;
  const totalQuantity = items.reduce((sum, item) => sum + parseInt(item.quantity), 0);
  const categories = [...new Set(items.map(item => item.category).filter(c => c))];
  const locations = [...new Set(items.map(item => item.location).filter(l => l))];

  document.getElementById('analyticsTotal').textContent = totalItems;
  document.getElementById('analyticsQuantity').textContent = totalQuantity;
  document.getElementById('analyticsCategories').textContent = categories.length;
  document.getElementById('analyticsLocations').textContent = locations.length;

  // Top items by quantity
  const topItems = [...items].sort((a, b) => b.quantity - a.quantity).slice(0, 5);
  document.getElementById('topItems').innerHTML = topItems.map(item => `
    <div class="top-item">
      <span>${item.icon} ${item.display_name}</span>
      <span class="top-item-qty">${item.quantity}x</span>
    </div>
  `).join('') || '<p>Keine Artikel</p>';

  // Location distribution
  const locationCounts = {};
  items.forEach(item => {
    const loc = item.location || 'Unbekannt';
    locationCounts[loc] = (locationCounts[loc] || 0) + parseInt(item.quantity);
  });

  document.getElementById('locationStats').innerHTML = Object.entries(locationCounts)
    .sort((a, b) => b[1] - a[1])
    .map(([loc, count]) => `
      <div class="location-stat-item">
        <span>${loc}</span>
        <div class="location-bar">
          <div class="location-bar-fill" style="width: ${(count / totalQuantity * 100)}%"></div>
        </div>
        <span>${count}</span>
      </div>
    `).join('') || '<p>Keine Daten</p>';

  document.getElementById('analyticsModal').classList.add('active');
}

function closeAnalytics() {
  document.getElementById('analyticsModal').classList.remove('active');
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
  // Ignore scan errors
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
      closeScanner();
      showToast(data.message + ': ' + data.item.name, 'success');
      loadInventory();
    } else if (data.needs_name) {
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

// Product name modal functions (for barcode scan)
function showProductModal(barcode) {
  currentBarcode = barcode;
  document.getElementById('scannedBarcode').textContent = barcode;
  document.getElementById('productName').value = '';
  document.getElementById('productQuantity').value = '1';
  document.getElementById('productLocation').value = 'Vorratskammer';
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
  const location = document.getElementById('productLocation').value;

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
        quantity: quantity,
        location: location
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

// Handle Enter keys
document.addEventListener('DOMContentLoaded', function() {
  const inputs = ['productName', 'manualName', 'editName'];
  inputs.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
          if (id === 'productName') saveProduct();
          else if (id === 'manualName') saveManualItem();
          else if (id === 'editName') saveEditItem();
        }
      });
    }
  });
});

function logout() {
  if (confirm('Wirklich ausloggen?')) {
    window.location.href = 'logout.php';
  }
}

// Toast notification system
function showToast(message, type = 'info') {
  const existingToast = document.querySelector('.toast');
  if (existingToast) {
    existingToast.remove();
  }

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.textContent = message;
  document.body.appendChild(toast);

  setTimeout(() => toast.classList.add('show'), 10);

  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Initialize
loadInventory();

// Auto-refresh every 30 seconds
setInterval(loadInventory, 30000);

// Register service worker for PWA
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('sw.js')
    .then(reg => console.log('Service Worker registered'))
    .catch(err => console.log('Service Worker not registered', err));
}
</script>
</body>
</html>
