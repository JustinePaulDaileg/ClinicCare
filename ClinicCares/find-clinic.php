<?php
require_once __DIR__ . '/includes/session.php';
requireLogin();

// Fetch patient's home location if they have one
$homeLocation = null;
if ($_SESSION['role'] === 'patient') {
    $pt = db()->fetchOne(
        "SELECT p.address, p.city, p.lat, p.lng FROM patients p WHERE p.user_id = ?",
        [$_SESSION['user_id']]
    );
    if ($pt && $pt['lat'] && $pt['lng']) {
        $homeLocation = [
            'lat'     => (float)$pt['lat'],
            'lng'     => (float)$pt['lng'],
            'address' => trim(($pt['address'] ?? '') . ', ' . ($pt['city'] ?? ''), ', '),
        ];
    }
}

$pageTitle = 'Find Nearest Clinic';
$activeNav = 'find';
include __DIR__ . '/includes/header.php';
?>

<!-- Leaflet.js (open-source, no API key needed) -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
/* ── Find Clinic page styles ── */
.find-layout {
    display: grid;
    grid-template-columns: 380px 1fr;
    gap: 0;
    height: calc(100vh - var(--header-h) - 48px);
    border-radius: var(--radius-lg);
    overflow: hidden;
    border: 1px solid var(--border);
    box-shadow: var(--shadow-lg);
}

/* Left panel */
.find-panel {
    display: flex;
    flex-direction: column;
    background: var(--surface);
    border-right: 1px solid var(--border);
    overflow: hidden;
}
.find-panel-header {
    padding: 16px 18px 0;
    flex-shrink: 0;
}
.find-panel-header h2 {
    font-family: var(--font-display);
    font-size: 17px;
    font-weight: 700;
    margin-bottom: 12px;
}

/* Search controls */
.search-controls { padding: 0 18px 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }

.pill-filters {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}
.pill {
    padding: 5px 12px;
    border-radius: 20px;
    border: 1.5px solid var(--border);
    background: var(--surface);
    color: var(--text-secondary);
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
    font-family: var(--font-sans);
}
.pill:hover { border-color: var(--primary); color: var(--primary); }
.pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }
.pill.emergency.active { background: var(--danger); border-color: var(--danger); }

/* Results list */
.results-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.result-card {
    padding: 14px 18px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background 0.15s;
    position: relative;
}
.result-card:hover, .result-card.active {
    background: var(--primary-pale);
}
.result-card.active {
    border-left: 3px solid var(--primary);
}
.result-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 6px;
}
.result-icon {
    width: 38px; height: 38px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.result-icon.hospital  { background: #fef2f2; }
.result-icon.clinic    { background: #eff6ff; }
.result-icon.doctor    { background: #f0fdf4; }
.result-icon.diagnostic{ background: #fffbeb; }
.result-icon.specialty { background: #f5f3ff; }

.result-name {
    font-weight: 700;
    font-size: 13px;
    color: var(--text-primary);
    line-height: 1.3;
}
.result-sub {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: capitalize;
    margin-top: 1px;
}
.result-dist {
    margin-left: auto;
    flex-shrink: 0;
    font-size: 12px;
    font-weight: 700;
    color: var(--primary);
    background: var(--primary-pale);
    padding: 3px 8px;
    border-radius: 20px;
    white-space: nowrap;
}
.result-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    font-size: 11px;
    color: var(--text-muted);
}
.result-tag {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    padding: 2px 7px;
    border-radius: 12px;
    background: var(--surface-2);
    font-size: 10px;
    font-weight: 600;
    color: var(--text-secondary);
}
.result-tag.green  { background: #dcfce7; color: #15803d; }
.result-tag.blue   { background: #dbeafe; color: #1d4ed8; }
.result-tag.red    { background: #fee2e2; color: #b91c1c; }
.result-tag.purple { background: #ede9fe; color: #7c3aed; }
.result-tag.amber  { background: #fef9c3; color: #a16207; }

.no-results {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
    color: var(--text-muted);
    text-align: center;
}
.no-results .icon { font-size: 48px; margin-bottom: 12px; }
.no-results h3 { font-family: var(--font-display); font-size: 16px; font-weight: 600; margin-bottom: 6px; color: var(--text-secondary); }
.no-results p { font-size: 13px; }

/* Map */
.find-map { position: relative; }
#map {
    width: 100%;
    height: 100%;
    z-index: 1;
}

/* Map controls overlay */
.map-overlay-top {
    position: absolute;
    top: 14px;
    left: 50%;
    transform: translateX(-50%);
    z-index: 800;
    display: flex;
    gap: 8px;
    pointer-events: none;
}
.map-badge {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
    box-shadow: var(--shadow);
    pointer-events: auto;
    white-space: nowrap;
}
.map-badge.loading { color: var(--primary); }

.locate-btn {
    position: absolute;
    bottom: 80px;
    right: 14px;
    z-index: 800;
    width: 44px; height: 44px;
    background: var(--surface);
    border: 2px solid var(--border);
    border-radius: 10px;
    font-size: 20px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    box-shadow: var(--shadow);
    transition: all 0.2s;
}
.locate-btn:hover { background: var(--primary); border-color: var(--primary); }
.locate-btn:hover .locate-icon { filter: brightness(10); }

/* Detail popup */
.detail-popup {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    background: var(--surface);
    border-top: 1px solid var(--border);
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    padding: 20px;
    z-index: 900;
    box-shadow: 0 -8px 32px rgba(0,0,0,0.12);
    transform: translateY(100%);
    transition: transform 0.3s ease;
    max-height: 60%;
    overflow-y: auto;
}
.detail-popup.show { transform: translateY(0); }
.detail-popup-close {
    position: absolute;
    top: 12px; right: 14px;
    width: 28px; height: 28px;
    border: none; background: var(--surface-2);
    border-radius: 50%; cursor: pointer;
    font-size: 14px; display: flex; align-items: center; justify-content: center;
    color: var(--text-muted);
}

/* Stars */
.stars { color: #f59e0b; font-size: 13px; letter-spacing: 1px; }

/* Leaflet marker override */
.custom-marker {
    width: 36px; height: 36px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    display: flex; align-items: center; justify-content: center;
    border: 3px solid white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.25);
}
.custom-marker-inner { transform: rotate(45deg); font-size: 16px; }

/* Loading spinner overlay */
#mapLoader {
    position: absolute;
    inset: 0; z-index: 1000;
    background: rgba(255,255,255,0.75);
    display: flex; align-items: center; justify-content: center;
    backdrop-filter: blur(2px);
}

/* Radius slider */
.radius-control {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 0;
}
.radius-control label { font-size: 12px; font-weight: 600; color: var(--text-muted); white-space: nowrap; }
.radius-control input[type=range] {
    flex: 1;
    accent-color: var(--primary);
    height: 4px;
}
.radius-control .val { font-size: 12px; font-weight: 700; color: var(--primary); min-width: 40px; }

/* Panel result count */
.panel-count {
    font-size: 11px;
    color: var(--text-muted);
    padding: 6px 18px 4px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}

/* Responsive */
@media (max-width: 900px) {
    .find-layout {
        grid-template-columns: 1fr;
        grid-template-rows: 260px 1fr;
        height: auto;
        min-height: calc(100vh - var(--header-h) - 80px);
    }
    .find-panel { order: 2; height: 460px; }
    .find-map   { order: 1; height: 260px; }
    .detail-popup { position: fixed; }
}
</style>

<div style="margin-bottom:14px;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:10px;">
    <div>
        <h1 style="font-family:var(--font-display);font-size:22px;font-weight:700;margin-bottom:3px;">📍 Find Nearest Clinic & Doctor</h1>
        <p style="color:var(--text-muted);font-size:13px;" id="pageSubtitle">
            <?php if ($homeLocation): ?>
                Showing results near your home address · <strong><?= htmlspecialchars($homeLocation['address']) ?></strong>
            <?php else: ?>
                Discover clinics, hospitals, and doctors near your location
            <?php endif; ?>
        </p>
    </div>

    <?php if ($_SESSION['role'] === 'patient'): ?>
    <!-- Location source toggle (patients only) -->
    <div id="locationToggleWrap" style="display:flex;align-items:center;gap:10px;background:var(--surface);border:1.5px solid var(--border);border-radius:12px;padding:10px 14px;">
        <div style="line-height:1.3;">
            <div style="font-size:12px;font-weight:700;color:var(--text-primary);">📡 Use Live GPS</div>
            <div style="font-size:11px;color:var(--text-muted);" id="toggleSubtext">
                <?= $homeLocation ? 'Currently using home address' : 'No home address set' ?>
            </div>
        </div>
        <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;" title="Toggle live device location">
            <input type="checkbox" id="liveGpsToggle" style="opacity:0;width:0;height:0;"
                   <?= !$homeLocation ? 'checked' : '' ?>>
            <span id="toggleSlider" style="
                position:absolute;inset:0;background:var(--border);border-radius:24px;transition:background 0.25s;
            "></span>
            <span id="toggleThumb" style="
                position:absolute;top:3px;left:3px;width:18px;height:18px;background:#fff;
                border-radius:50%;transition:transform 0.25s;box-shadow:0 1px 4px rgba(0,0,0,0.25);
            "></span>
        </label>
    </div>
    <?php endif; ?>
</div>

<div class="find-layout">

    <!-- ── LEFT PANEL ────────────────────────────────── -->
    <div class="find-panel">
        <div class="find-panel-header">
            <h2>Search Results</h2>
        </div>

        <div class="search-controls">
            <!-- Search input -->
            <div style="position:relative;margin-bottom:10px;">
                <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--text-muted);">🔍</span>
                <input type="text" id="searchInput" placeholder="Search clinic, specialty, city…"
                    style="width:100%;padding:9px 12px 9px 34px;border:1.5px solid var(--border);border-radius:8px;font-size:13px;font-family:var(--font-sans);outline:none;color:var(--text-primary);background:var(--surface);"
                    onfocus="this.style.borderColor='var(--primary)'" onblur="this.style.borderColor='var(--border)'">
            </div>

            <!-- Type filters -->
            <div class="pill-filters" id="typeFilters">
                <button class="pill active" data-type="all">🏥 All</button>
                <button class="pill" data-type="hospital">🏨 Hospital</button>
                <button class="pill" data-type="clinic">🏪 Clinic</button>
                <button class="pill" data-type="doctor">👨‍⚕️ Doctor</button>
                <button class="pill" data-type="diagnostic">🔬 Diagnostic</button>
                <button class="pill emergency" data-type="emergency">🚨 Emergency</button>
            </div>

            <!-- Feature filters -->
            <div class="pill-filters" id="featureFilters">
                <button class="pill" data-feat="walkin" id="walkinPill">🚶 Walk-in</button>
                <button class="pill" data-feat="tele" id="telePill">📱 Telemedicine</button>
            </div>

            <!-- Radius control -->
            <div class="radius-control">
                <label>📏 Radius</label>
                <input type="range" id="radiusSlider" min="1" max="50" value="25" step="1" oninput="updateRadius(this.value)">
                <span class="val" id="radiusVal">25 km</span>
            </div>

            <!-- Locate button -->
            <button class="btn btn-secondary btn-block btn-sm" style="margin-top:4px;" onclick="goHome()" id="homeBtn"
                    <?= !$homeLocation ? 'style="display:none;"' : '' ?>>
                🏠 Go to Home Address
            </button>
        </div>

        <!-- Count bar -->
        <div class="panel-count" id="panelCount">Loading…</div>

        <!-- Results list -->
        <div class="results-list" id="resultsList">
            <div class="no-results">
                <div class="icon">📡</div>
                <h3>Searching for your location…</h3>
                <p>Allow location access to find nearby clinics and doctors.</p>
            </div>
        </div>
    </div>

    <!-- ── MAP ──────────────────────────────────────── -->
    <div class="find-map">
        <div id="map"></div>

        <!-- Loading overlay -->
        <div id="mapLoader">
            <div style="text-align:center;">
                <div class="spinner" style="margin:0 auto 12px;"></div>
                <div style="font-size:13px;color:var(--text-muted);">Getting your location…</div>
            </div>
        </div>

        <!-- Count badge -->
        <div class="map-overlay-top">
            <div class="map-badge" id="mapBadge">📍 Waiting for location…</div>
        </div>

        <!-- Locate me button -->
        <button class="locate-btn" onclick="locateMe()" title="Center on my location">
            <span class="locate-icon">🎯</span>
        </button>

        <!-- Detail popup (slides up from bottom of map) -->
        <div class="detail-popup" id="detailPopup">
            <button class="detail-popup-close" onclick="closeDetail()">✕</button>
            <div id="detailContent"></div>
        </div>
    </div>
</div>

<script>
/* ═══════════════════════════════════════════════════
   ClinicCare – Find Nearest Clinic/Doctor
   Home address (from profile) OR live GPS toggle
═══════════════════════════════════════════════════ */

// ── PHP-injected home location ────────────────────
const HOME_LOCATION = <?= $homeLocation
    ? json_encode($homeLocation)
    : 'null' ?>;

let map, userMarker, userCircle;
let markers = [];
let allResults = [];
let activeType = 'all';
let walkinFilter = false;
let teleFilter   = false;
let userLat = null, userLng = null;
let radiusKm = 25;
let searchTimer = null;
let activeCardId = null;
let usingLiveGps = !HOME_LOCATION; // live GPS if no home addr

// ── Toggle slider visual sync ─────────────────────
function syncToggleUI(on) {
    const slider = document.getElementById('toggleSlider');
    const thumb  = document.getElementById('toggleThumb');
    const sub    = document.getElementById('toggleSubtext');
    if (!slider) return;
    if (on) {
        slider.style.background = 'var(--primary)';
        thumb.style.transform   = 'translateX(20px)';
        sub.textContent = 'Using live device GPS';
    } else {
        slider.style.background = 'var(--border)';
        thumb.style.transform   = 'translateX(0)';
        sub.textContent = HOME_LOCATION ? 'Using home address' : 'No home address set';
    }
}

// ── Initialise Leaflet map ──────────────────────────
function initMap(lat, lng) {
    if (map) { map.setView([lat, lng], 13); return; }

    map = L.map('map', { center: [lat, lng], zoom: 13, zoomControl: true });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map);

    document.getElementById('mapLoader').style.display = 'none';
}

// ── Place user dot on map ──────────────────────────
function setUserLocation(lat, lng, label) {
    userLat = lat; userLng = lng;

    if (!map) { initMap(lat, lng); }
    else { map.setView([lat, lng], 13); }

    if (userMarker) { userMarker.remove(); userCircle.remove(); }

    const userIcon = L.divIcon({
        html: `<div style="width:18px;height:18px;background:#1d4ed8;border-radius:50%;border:3px solid white;box-shadow:0 0 0 4px rgba(29,78,216,0.25);"></div>`,
        iconSize: [18, 18], iconAnchor: [9, 9], className: '',
    });
    userMarker = L.marker([lat, lng], { icon: userIcon, zIndexOffset: 1000 })
        .addTo(map)
        .bindPopup(`<strong style="font-family:sans-serif;font-size:13px;">${label || '📍 You are here'}</strong>`);

    userCircle = L.circle([lat, lng], {
        color: '#1d4ed8', fillColor: '#1d4ed8',
        fillOpacity: 0.06, weight: 1, radius: radiusKm * 1000,
    }).addTo(map);

    // Update subtitle
    const sub = document.getElementById('pageSubtitle');
    if (sub) {
        if (usingLiveGps) {
            sub.innerHTML = '📡 <strong>Using your current GPS location</strong>';
        } else if (HOME_LOCATION) {
            sub.innerHTML = `🏠 Showing results near your home address · <strong>${escHtml(HOME_LOCATION.address)}</strong>`;
        }
    }

    fetchResults();
}

// ── Load home location ─────────────────────────────
function goHome() {
    if (!HOME_LOCATION) {
        showToast('No home address saved. Update your profile first.', 'warning');
        return;
    }
    usingLiveGps = false;
    const toggle = document.getElementById('liveGpsToggle');
    if (toggle) toggle.checked = false;
    syncToggleUI(false);
    setUserLocation(HOME_LOCATION.lat, HOME_LOCATION.lng, '🏠 Home address');
}

// ── Live GPS locate ────────────────────────────────
function locateMe() {
    document.getElementById('mapLoader').style.display = 'flex';

    if (!navigator.geolocation) {
        showToast('Geolocation is not supported by your browser.', 'danger');
        document.getElementById('mapLoader').style.display = 'none';
        if (HOME_LOCATION) goHome();
        return;
    }

    navigator.geolocation.getCurrentPosition(
        pos => {
            document.getElementById('mapLoader').style.display = 'none';
            setUserLocation(pos.coords.latitude, pos.coords.longitude, '📡 Your current location');
        },
        err => {
            document.getElementById('mapLoader').style.display = 'none';
            showToast('Could not get GPS location. ' + (HOME_LOCATION ? 'Falling back to home address.' : 'Showing Metro Manila.'), 'warning');
            usingLiveGps = false;
            const toggle = document.getElementById('liveGpsToggle');
            if (toggle) toggle.checked = false;
            syncToggleUI(false);
            if (HOME_LOCATION) {
                setUserLocation(HOME_LOCATION.lat, HOME_LOCATION.lng, '🏠 Home address');
            } else {
                setUserLocation(14.5995, 120.9842, '📍 Metro Manila (default)');
            }
        },
        { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
    );
}

// ── Fetch results from API ─────────────────────────
function fetchResults() {
    if (!userLat || !userLng) return;

    const q = document.getElementById('searchInput').value.trim();
    const params = new URLSearchParams({
        lat: userLat, lng: userLng,
        radius: radiusKm,
        type: activeType,
    });
    if (walkinFilter) params.set('walkin', '1');
    if (teleFilter)   params.set('tele', '1');
    if (q)            params.set('q', q);

    document.getElementById('panelCount').textContent = 'Searching…';
    document.getElementById('mapBadge').innerHTML = '<span class="loading">⏳ Searching…</span>';

    fetch(`/api/find-clinic.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast(data.error || 'Search failed', 'danger'); return; }
            allResults = data.results;
            renderResults(allResults);
            renderMarkers(allResults);
            document.getElementById('panelCount').textContent =
                `${data.count} result${data.count !== 1 ? 's' : ''} within ${radiusKm} km`;
            document.getElementById('mapBadge').textContent = `📍 ${data.count} nearby`;
        })
        .catch(() => {
            document.getElementById('panelCount').textContent = 'Error loading results.';
            document.getElementById('mapBadge').textContent = '❌ Error';
        });
}

// ── Render list panel ──────────────────────────────
function renderResults(results) {
    const list = document.getElementById('resultsList');

    if (!results.length) {
        list.innerHTML = `<div class="no-results">
            <div class="icon">🔍</div>
            <h3>No results found</h3>
            <p>Try increasing the search radius or changing filters.</p>
        </div>`;
        return;
    }

    list.innerHTML = results.map(r => `
        <div class="result-card" id="card-${r.id}" onclick="selectResult('${r.id}')">
            <div class="result-header">
                <div class="result-icon ${r.subtype}">${typeIcon(r.subtype)}</div>
                <div style="flex:1;min-width:0;">
                    <div class="result-name">${escHtml(r.name)}</div>
                    <div class="result-sub">${r.doctor_name ? escHtml(r.doctor_name) + ' · ' : ''}${escHtml(r.subtype)}</div>
                </div>
                <div class="result-dist">${r.distance_km < 1
                    ? Math.round(r.distance_km * 1000) + ' m'
                    : r.distance_km.toFixed(1) + ' km'
                }</div>
            </div>
            <div class="result-meta">
                ${r.city ? `<span>📍 ${escHtml(r.city)}</span>` : ''}
                ${r.specializations && r.specializations.length ? `<span>🏷️ ${r.specializations.slice(0,2).join(', ')}</span>` : ''}
            </div>
            <div class="result-meta" style="margin-top:4px;">
                ${r.accepts_walkin  ? '<span class="result-tag green">🚶 Walk-in</span>' : ''}
                ${r.telemedicine    ? '<span class="result-tag blue">📱 Telemedicine</span>' : ''}
                ${r.emergency       ? '<span class="result-tag red">🚨 Emergency</span>' : ''}
                ${r.consultation_fee? `<span class="result-tag amber">₱${r.consultation_fee.toFixed(0)}</span>` : ''}
                ${r.rating          ? `<span class="result-tag purple">⭐ ${r.rating}</span>` : ''}
            </div>
        </div>
    `).join('');
}

// ── Render Leaflet markers ─────────────────────────
function renderMarkers(results) {
    markers.forEach(m => m.remove());
    markers = [];

    results.forEach(r => {
        const colors = {
            hospital: '#dc2626', clinic: '#1d4ed8',
            doctor: '#16a34a', diagnostic: '#d97706',
            specialty: '#7c3aed',
        };
        const color = colors[r.subtype] || '#1d4ed8';

        const icon = L.divIcon({
            html: `<div style="
                width:32px;height:32px;
                background:${color};
                border-radius:50% 50% 50% 0;
                transform:rotate(-45deg);
                border:2.5px solid white;
                box-shadow:0 2px 6px rgba(0,0,0,0.3);
                display:flex;align-items:center;justify-content:center;
            "><span style="transform:rotate(45deg);font-size:14px;line-height:1;">${typeIcon(r.subtype)}</span></div>`,
            iconSize: [32, 32], iconAnchor: [16, 32], popupAnchor: [0, -34], className: '',
        });

        const marker = L.marker([r.lat, r.lng], { icon })
            .addTo(map)
            .on('click', () => selectResult(r.id, true));

        markers.push(marker);
        marker._resultId = r.id;
    });
}

// ── Select a result ────────────────────────────────
function selectResult(id, fromMap = false) {
    const r = allResults.find(x => x.id === id);
    if (!r) return;

    document.querySelectorAll('.result-card').forEach(c => c.classList.remove('active'));
    const card = document.getElementById('card-' + id);
    if (card) { card.classList.add('active'); card.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }

    activeCardId = id;

    if (!fromMap) { map.flyTo([r.lat, r.lng], 15, { animate: true, duration: 0.8 }); }
    else          { map.setView([r.lat, r.lng], 15); }

    showDetailPopup(r);
}

// ── Detail popup ───────────────────────────────────
function showDetailPopup(r) {
    const stars  = r.rating ? '⭐'.repeat(Math.round(r.rating)) + ` (${r.rating})` : '';
    const specs  = r.specializations && r.specializations.length
        ? r.specializations.map(s => `<span style="background:#eff6ff;color:#1d4ed8;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;">${s}</span>`).join(' ')
        : '';
    const dirUrl  = `https://www.google.com/maps/dir/?api=1&destination=${r.lat},${r.lng}`;
    const mapsUrl = `https://www.openstreetmap.org/?mlat=${r.lat}&mlon=${r.lng}&zoom=17`;

    document.getElementById('detailContent').innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;padding-right:24px;">
            <div class="result-icon ${r.subtype}" style="flex-shrink:0;">${typeIcon(r.subtype)}</div>
            <div style="flex:1;min-width:0;">
                <h3 style="font-family:var(--font-display);font-size:17px;font-weight:700;margin-bottom:2px;">${escHtml(r.name)}</h3>
                ${r.doctor_name ? `<div style="font-size:13px;font-weight:600;color:var(--primary);margin-bottom:2px;">${escHtml(r.doctor_name)}</div>` : ''}
                <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                    <span style="font-size:12px;color:var(--text-muted);text-transform:capitalize;">${r.subtype}</span>
                    ${stars ? `<span style="font-size:12px;color:#d97706;">${stars}</span>` : ''}
                    <span style="font-size:12px;font-weight:700;color:var(--primary);background:var(--primary-pale);padding:2px 8px;border-radius:20px;">
                        ${r.distance_km < 1 ? Math.round(r.distance_km*1000)+' m away' : r.distance_km.toFixed(1)+' km away'}
                    </span>
                </div>
            </div>
        </div>
        ${specs ? `<div style="margin-bottom:12px;display:flex;flex-wrap:wrap;gap:4px;">${specs}</div>` : ''}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:14px;font-size:13px;">
            ${r.address ? `<div style="display:flex;align-items:flex-start;gap:6px;"><span>📍</span><span style="color:var(--text-secondary);">${escHtml(r.address)}</span></div>` : ''}
            ${r.phone   ? `<div style="display:flex;align-items:center;gap:6px;"><span>📞</span><a href="tel:${r.phone}" style="color:var(--primary);text-decoration:none;font-weight:600;">${escHtml(r.phone)}</a></div>` : ''}
            ${r.hours   ? `<div style="display:flex;align-items:flex-start;gap:6px;"><span>🕐</span><span style="color:var(--text-secondary);">${escHtml(r.hours)}</span></div>` : ''}
            ${r.consultation_fee ? `<div style="display:flex;align-items:center;gap:6px;"><span>💰</span><span style="font-weight:700;color:var(--primary);">₱${r.consultation_fee.toFixed(2)} / visit</span></div>` : ''}
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
            ${r.accepts_walkin ? '<span class="result-tag green">🚶 Walk-in accepted</span>' : '<span class="result-tag">🚫 Appointment only</span>'}
            ${r.telemedicine   ? '<span class="result-tag blue">📱 Telemedicine available</span>' : ''}
            ${r.emergency      ? '<span class="result-tag red">🚨 24/7 Emergency</span>' : ''}
        </div>
        ${r.description ? `<p style="font-size:13px;color:var(--text-secondary);margin-bottom:14px;line-height:1.6;">${escHtml(r.description)}</p>` : ''}
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="${dirUrl}" target="_blank" class="btn btn-primary btn-sm">🗺️ Get Directions</a>
            <a href="${mapsUrl}" target="_blank" class="btn btn-secondary btn-sm">🌍 View on Map</a>
            ${r.phone    ? `<a href="tel:${r.phone}" class="btn btn-secondary btn-sm">📞 Call</a>` : ''}
            ${r.book_url ? `<a href="${r.book_url}" class="btn btn-success btn-sm">📅 Book Appointment</a>` : ''}
            ${r.website  ? `<a href="${r.website}" target="_blank" class="btn btn-secondary btn-sm">🌐 Website</a>` : ''}
        </div>
    `;
    document.getElementById('detailPopup').classList.add('show');
}

function closeDetail() {
    document.getElementById('detailPopup').classList.remove('show');
    activeCardId = null;
    document.querySelectorAll('.result-card').forEach(c => c.classList.remove('active'));
}

// ── Helpers ────────────────────────────────────────
function typeIcon(type) {
    const icons = { hospital:'🏨', clinic:'🏥', doctor:'👨‍⚕️', diagnostic:'🔬', specialty:'⚕️', pharmacy:'💊' };
    return icons[type] || '🏥';
}
function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function updateRadius(val) {
    radiusKm = parseInt(val);
    document.getElementById('radiusVal').textContent = val + ' km';
    if (userCircle) userCircle.setRadius(radiusKm * 1000);
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchResults, 600);
}

// ── Filter pills ───────────────────────────────────
document.querySelectorAll('#typeFilters .pill').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('#typeFilters .pill').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        activeType = this.dataset.type;
        closeDetail();
        fetchResults();
    });
});
document.getElementById('walkinPill').addEventListener('click', function() {
    walkinFilter = !walkinFilter;
    this.classList.toggle('active', walkinFilter);
    fetchResults();
});
document.getElementById('telePill').addEventListener('click', function() {
    teleFilter = !teleFilter;
    this.classList.toggle('active', teleFilter);
    fetchResults();
});

// ── Live search ────────────────────────────────────
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(fetchResults, 500);
});

// ── GPS toggle switch ──────────────────────────────
const liveToggle = document.getElementById('liveGpsToggle');
if (liveToggle) {
    // Init visual state
    syncToggleUI(liveToggle.checked);

    liveToggle.addEventListener('change', function() {
        usingLiveGps = this.checked;
        syncToggleUI(usingLiveGps);
        if (usingLiveGps) {
            locateMe();
        } else {
            if (HOME_LOCATION) {
                goHome();
            } else {
                showToast('No home address saved. Go to Settings & Profile to add one.', 'warning');
                // revert toggle
                this.checked = true;
                usingLiveGps = true;
                syncToggleUI(true);
            }
        }
    });
}

// ── Escape closes detail ───────────────────────────
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });

// ── Auto-load on page ready ────────────────────────
window.addEventListener('load', () => {
    if (HOME_LOCATION && !usingLiveGps) {
        // Patient has a home address and GPS toggle is OFF → use home
        initMap(HOME_LOCATION.lat, HOME_LOCATION.lng);
        setUserLocation(HOME_LOCATION.lat, HOME_LOCATION.lng, '🏠 Home address');
    } else {
        // No home address, or GPS is already toggled ON → try live GPS
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => setUserLocation(pos.coords.latitude, pos.coords.longitude, '📡 Your current location'),
                ()  => {
                    if (HOME_LOCATION) {
                        goHome();
                    } else {
                        showToast('Location not available — showing Metro Manila', 'warning');
                        setUserLocation(14.5995, 120.9842, '📍 Metro Manila (default)');
                    }
                },
                { timeout: 6000, maximumAge: 120000 }
            );
        } else {
            if (HOME_LOCATION) { goHome(); }
            else { setUserLocation(14.5995, 120.9842, '📍 Metro Manila (default)'); }
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>