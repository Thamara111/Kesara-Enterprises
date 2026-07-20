<!-- Delivery Tracking View -->
<!-- Provides a real-time map interface (via Leaflet.js) for tracking active deliveries and dispatch routes. -->
<h2 class="sr-only">Delivery tracking and live status monitoring page for Kesara Enterprises wholesale admin platform</h2>

<!-- Leaflet.js Map Assets -->
<link rel="stylesheet" href="/assets/leaflet.css" />
<script src="/assets/leaflet.js"></script>

<div class="flex-1 flex overflow-hidden relative">
    <!-- Floating Toggle Button (Mobile Only) -->
    <button id="toggle-tracking-sidebar" class="lg:hidden absolute bottom-6 left-6 z-[1000] bg-brand text-brand-light w-12 h-12 rounded-full flex items-center justify-center shadow-2xl hover:bg-brand-dark transition-all">
        <i class="ti ti-list text-xl"></i>
    </button>

    <!-- Left Status Sidebar -->
    <div id="tracking-sidebar" class="fixed inset-y-0 left-0 z-30 w-[420px] max-w-full bg-white border-r border-gray-100 flex flex-col shadow-2xl transform -translate-x-full transition-transform duration-300 lg:relative lg:translate-x-0 lg:z-10 lg:shadow-none overflow-hidden">
        <!-- Header & Dropdown -->
        <div class="p-6 border-b border-gray-100 bg-white/50 backdrop-blur-md sticky top-0 z-10 flex flex-col">
            <div class="flex justify-between items-start">
                <div class="flex-1 min-w-0">
                    <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Live Delivery Tracking</h1>
                    <p class="text-xs text-gray-500 mt-1">Real-time GPS status and route dispatch monitoring</p>
                </div>
                <button onclick="closeTrackingSidebar()" class="lg:hidden p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
            
            <div class="mt-4">
                <label for="run-selector" class="text-[10px] font-bold text-gray-400 uppercase tracking-widest block mb-1.5">Select Active Run</label>
                <select id="run-selector" onchange="changeActiveRun(this.value)" class="w-full px-4 py-2.5 bg-gray-50 border-none rounded-xl text-sm font-semibold text-gray-800 focus:bg-white focus:border-brand/20 focus:ring-2 focus:ring-brand/10 transition-all outline-none cursor-pointer">
                    <!-- Loaded dynamically via JS -->
                </select>
            </div>
        </div>

        <!-- Scrollable Details & Timeline -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
            <!-- Driver Info Card -->
            <div class="bg-gray-50 border border-gray-100 p-4 rounded-2xl flex items-center gap-4">
                <div id="t-av" class="w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-sm shadow-md">NK</div>
                <div class="flex-1 min-w-0">
                    <p id="t-driver" class="text-sm font-bold text-gray-900"></p>
                    <p id="t-vehicle" class="text-xs text-gray-500 mt-0.5"></p>
                </div>
                <div class="text-right">
                    <span id="t-status-badge" class="px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border"></span>
                </div>
            </div>

            <!-- Route Metrics -->
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-gray-50/50 p-4 border border-gray-100 rounded-2xl text-center shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Completed Stops</p>
                    <p id="t-progress" class="text-xl font-bold text-gray-900 mt-1">0 / 0</p>
                </div>
                <div class="bg-gray-50/50 p-4 border border-gray-100 rounded-2xl text-center shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Estimated Finish</p>
                    <p id="t-eta" class="text-xl font-bold text-brand mt-1">--</p>
                </div>
            </div>

            <!-- Route Stops Timeline -->
            <div>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em] mb-4">Route Progress</h3>
                <div id="t-stops" class="space-y-6 pl-4 relative before:absolute before:left-[9px] before:top-2 before:bottom-2 before:w-px before:bg-gray-100">
                    <!-- Timeline items injected here -->
                </div>
            </div>

            <div class="h-px bg-gray-100"></div>

            <!-- Telemetry Event Logs -->
            <div>
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Activity Feed</h3>
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="text-[9px] text-gray-400 font-bold uppercase">GPS LIVE</span>
                    </div>
                </div>
                <div id="t-telemetry" class="space-y-3 bg-gray-900 text-emerald-400 font-mono text-[10px] p-4 rounded-2xl h-44 overflow-y-auto custom-scrollbar border border-gray-800 shadow-inner">
                    <!-- Simulated event logs injected here -->
                </div>
            </div>
        </div>

        <!-- Read-Only Driver Status Pane -->
        <div class="p-6 border-t border-gray-100 bg-gray-50/50 space-y-3">
            <div class="flex justify-between items-center text-xs text-gray-500 font-semibold mb-1">
                <span class="flex items-center gap-1.5"><i class="ti ti-eye text-base text-gray-400"></i> Live Observation Mode</span>
                <span id="sim-status" class="text-amber-600 font-bold uppercase tracking-wider text-[10px]">Waiting for Driver</span>
            </div>
            <!-- Read-only status row -->
            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3 flex items-center gap-3">
                <i class="ti ti-info-circle text-amber-500 text-lg flex-shrink-0"></i>
                <p class="text-[10px] font-semibold text-amber-700 leading-snug">Simulation is controlled by the <strong>Driver Portal</strong>.<br>Start / Stop updates appear here automatically.</p>
            </div>

            <div class="grid grid-cols-2 gap-3 pt-1">
                <a href="/admin-assignments" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-gray-700 hover:bg-gray-50 transition-all">
                    <i class="ti ti-arrow-left text-base"></i>
                    Assignments
                </a>
                <button onclick="triggerQuickCall()" class="flex items-center justify-center gap-2 px-4 py-2.5 bg-white border border-gray-200 rounded-xl text-xs font-bold text-emerald-600 border-emerald-100 hover:bg-emerald-50/50 transition-all">
                    <i class="ti ti-phone text-base"></i>
                    Call Driver
                </button>
            </div>
        </div>
    </div>

    <!-- Map Pane -->
    <div class="flex-1 relative bg-gray-100">
        <!-- Floating HUD Over Map -->
        <div class="absolute top-6 left-6 z-[1000] bg-white/95 backdrop-blur-md px-6 py-4 rounded-2xl border border-gray-100 shadow-xl max-w-sm">
            <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Active Tracking Details</p>
            <h4 id="hud-id" class="text-base font-bold text-gray-900 mt-1">DA-2025-0312</h4>
            <p id="hud-desc" class="text-xs text-gray-500 mt-0.5">En route to Stop 3: Fashion Hub</p>
            <div class="mt-3 flex items-center gap-2">
                <span id="hud-dot" class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                <span id="hud-sub" class="text-xs text-blue-600 font-bold">Driver is en route</span>
            </div>
        </div>

        <div id="map" class="w-full h-full z-0"></div>
    </div>
</div>

<!-- Custom Dialog Overlay (Avoid native alert for premium feel) -->
<div id="custom-toast" class="fixed bottom-6 right-6 z-[2000] transform translate-y-24 opacity-0 transition-all duration-300 pointer-events-none">
    <div class="bg-gray-900 text-white px-5 py-3 rounded-2xl shadow-2xl flex items-center gap-3 border border-white/10">
        <i id="toast-icon" class="ti ti-bell text-lg text-brand-light"></i>
        <p id="toast-msg" class="text-xs font-semibold"></p>
    </div>
</div>

<style>
/* Custom Scrollbar for Left Sidebar */
.custom-scrollbar::-webkit-scrollbar {
    width: 5px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.1);
}

/* Custom Leaflet style overrides */
.leaflet-container {
    font-family: inherit;
}
.leaflet-bar {
    border: none !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
    border-radius: 12px !important;
    overflow: hidden;
}
.leaflet-bar a {
    border-bottom: 1px solid #f3f4f6 !important;
    background-color: #ffffff !important;
    color: #1f2937 !important;
    transition: all 0.2s;
}
.leaflet-bar a:hover {
    background-color: #f9fafb !important;
    color: #0F6E56 !important;
}

/* Custom HTML styling for markers */
.custom-marker-warehouse {
    background: none;
    border: none;
}
.custom-marker-stop {
    background: none;
    border: none;
}
.custom-marker-vehicle {
    background: none;
    border: none;
    transition: transform 0.2s linear; /* Smooth vehicle rotations */
}
</style>

<script>
// Warehouse coordinates
var WAREHOUSES = {
  'Colombo': [6.9535, 79.8886],
  'Gampaha': [7.0873, 80.0144],
  'Kandy': [7.2906, 80.6337],
  'Southern': [6.0329, 80.2170]
};

// Default Preseeded Assignments (aligned with assignments page)
<?php
$php_assignments = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT da.id, da.assigned_at, da.status, da.notes,
                                    dp.id AS driver_id, dp.name AS driver_name, dp.vehicle_type, dp.vehicle_number,
                                    o.id AS order_id,
                                    u.business_name AS company, u.address AS company_address
                             FROM delivery_assignments da
                             JOIN delivery_personnel dp ON da.personnel_id = dp.id
                             JOIN orders o ON da.order_id = o.id
                             JOIN users u ON o.user_id = u.id
                             ORDER BY da.assigned_at DESC");
        $asgn_db = $stmt->fetchAll();
        
        $grouped = [];
        foreach ($asgn_db as $row) {
            $key = $row['driver_id'] . '_' . date('Y-m-d_H-i', strtotime($row['assigned_at']));
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'id' => 'DA-2025-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
                    'date' => 'Assigned ' . date('d M, g:i A', strtotime($row['assigned_at'])),
                    'driver' => $row['driver_name'],
                    'vehicle' => ucfirst($row['vehicle_type']) . ' · ' . $row['vehicle_number'],
                    'status' => $row['status'],
                    'stops' => [],
                    'driver_id' => $row['driver_id']
                ];
            }
            $status_text = $row['status'] === 'completed' ? 'Delivered' : ($row['status'] === 'in_progress' ? 'In progress' : 'Pending');
            
            // Map coordinates based on company name
            $hash = crc32($row['company'] ?: 'default');
            $lat = 6.9000 + (($hash % 100) - 50) / 1000.0;
            $lng = 79.8700 + ((intval($hash / 100) % 100) - 50) / 1000.0;
            
            $grouped[$key]['stops'][] = [
                'num' => count($grouped[$key]['stops']) + 1,
                'name' => 'KE-2025-' . str_pad($row['order_id'], 5, '0', STR_PAD_LEFT) . ' · ' . htmlspecialchars($row['company']),
                'addr' => htmlspecialchars($row['company_address']),
                'status' => $status_text,
                'lat' => $lat,
                'lng' => $lng
            ];
        }
        
        foreach ($grouped as $g) {
            $badge = 'bg-amber-50 text-amber-700 border border-amber-100';
            $badgeText = 'Pending';
            if ($g['status'] === 'completed') {
                $badge = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
                $badgeText = 'Completed';
            } elseif ($g['status'] === 'in_progress') {
                $badge = 'bg-blue-50 text-blue-700 border border-blue-100';
                $badgeText = 'Active';
            } elseif ($g['status'] === 'failed') {
                $badge = 'bg-red-50 text-red-700 border border-red-100';
                $badgeText = 'Failed';
            }
            
            $words = explode(" ", $g['driver']);
            $av = "";
            foreach ($words as $w) {
                $av .= strtoupper(substr($w, 0, 1));
            }
            $av = substr($av, 0, 2);
            
            $php_assignments[] = [
                'id' => $g['id'],
                'date' => $g['date'],
                'badge' => $badge,
                'badgeText' => $badgeText,
                'av' => $av,
                'avColor' => 'bg-emerald-100 text-emerald-700 border border-emerald-200 shadow-emerald-100',
                'driver' => $g['driver'],
                'vehicle' => $g['vehicle'],
                'canCancel' => $g['status'] === 'pending',
                'zone' => 'Colombo',
                'stops' => $g['stops']
            ];
        }
    } catch (\Exception $e) {
        // Fallback
    }
}
?>
var defaultAssignments = <?php echo !empty($php_assignments) ? json_encode($php_assignments) : '[]'; ?>;

// Global Variables
var assignments = [];
var activeRun = null;
var map = null;
var warehouseMarker = null;
var stopMarkers = [];
var vehicleMarker = null;
var routePolyline = null;

// Simulation State
var isSimulating = false;
var simInterval = null;
var simSpeed = 1; // Speed multiplier (1x, 2x, 5x)
var currentLegIndex = 0; // index of stop we are moving towards (0 for Stop 1, etc.)
var currentStepIndex = 0; // index along interpolated leg path
var legInterpolatedPaths = []; // array of coordinate arrays for each leg

// Load Initial Data
function initData() {
    assignments = [...defaultAssignments];
}

// Show Custom Toast
function showToast(message, type = 'success') {
    var toast = document.getElementById('custom-toast');
    var msgEl = document.getElementById('toast-msg');
    var iconEl = document.getElementById('toast-icon');
    
    msgEl.textContent = message;
    
    if (type === 'error') {
        iconEl.className = 'ti ti-alert-circle text-red-500';
    } else if (type === 'info') {
        iconEl.className = 'ti ti-info-circle text-blue-500';
    } else {
        iconEl.className = 'ti ti-circle-check text-emerald-500';
    }
    
    toast.classList.remove('translate-y-24', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');
    
    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-24', 'opacity-0');
    }, 4000);
}

// Initialize Leaflet Map
function initMap() {
    // Default center to Colombo
    map = L.map('map', {
        zoomControl: false // Position zoom control elsewhere
    }).setView([6.9271, 79.8612], 12);
    
    L.control.zoom({
        position: 'bottomright'
    }).addTo(map);

    // Load CartoDB Positron - Sleek, Minimalist light theme tiles
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
        subdomains: 'abcd',
        maxZoom: 20
    }).addTo(map);
}

// Populate UI Elements
function populateRunsDropdown() {
    var selector = document.getElementById('run-selector');
    selector.innerHTML = '';
    
    assignments.forEach(a => {
        var option = document.createElement('option');
        option.value = a.id;
        var stopsDone = a.stops.filter(s => s.status.startsWith('Delivered')).length;
        option.textContent = `${a.id} — ${a.driver.split(' ')[0]} (${stopsDone}/${a.stops.length} stops) [${a.badgeText}]`;
        selector.appendChild(option);
    });
}

// Get the starting point coordinate for an assignment (always the zone's warehouse)
function getWarehouseCoords(zone) {
    return WAREHOUSES[zone] || WAREHOUSES['Colombo'];
}

// Linear interpolation to make the vehicle move smoothly between points
function interpolatePoints(p1, p2, steps) {
    var points = [];
    for (let i = 0; i <= steps; i++) {
        var t = i / steps;
        var lat = p1[0] + (p2[0] - p1[0]) * t;
        var lng = p1[1] + (p2[1] - p1[1]) * t;
        points.push([lat, lng]);
    }
    return points;
}

// Populate the Sidebar & HUD Information
function updateUI(run) {
    // Driver Details
    document.getElementById('t-av').textContent = run.av;
    document.getElementById('t-av').className = 'w-12 h-12 rounded-2xl flex items-center justify-center font-bold text-sm shadow-md ' + run.avColor;
    document.getElementById('t-driver').textContent = run.driver;
    document.getElementById('t-vehicle').textContent = run.vehicle;
    
    // Status Badge
    var badge = document.getElementById('t-status-badge');
    badge.className = 'px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider border ' + run.badge;
    badge.textContent = run.badgeText;
    
    // Metrics
    var completedCount = run.stops.filter(s => s.status.startsWith('Delivered')).length;
    document.getElementById('t-progress').textContent = `${completedCount} / ${run.stops.length}`;
    
    var estFinishEl = document.getElementById('t-eta');
    if (run.badgeText === 'Completed') {
        estFinishEl.textContent = 'Finished';
        estFinishEl.className = 'text-xl font-bold text-emerald-600 mt-1';
    } else if (run.badgeText === 'Pending') {
        estFinishEl.textContent = 'Not started';
        estFinishEl.className = 'text-xl font-bold text-gray-400 mt-1';
    } else {
        estFinishEl.textContent = '12:30 PM';
        estFinishEl.className = 'text-xl font-bold text-brand mt-1';
    }

    // Stop Timeline
    var timeline = document.getElementById('t-stops');
    timeline.innerHTML = run.stops.map(s => {
        var statusClass = 'bg-gray-50 text-gray-500 border border-gray-100';
        var markerDot = 'bg-gray-200 border-gray-300';
        
        if (s.status.startsWith('Delivered')) {
            statusClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
            markerDot = 'bg-emerald-500 border-white ring-2 ring-emerald-100';
        } else if (s.status === 'In progress') {
            statusClass = 'bg-blue-50 text-blue-700 border border-blue-100 animate-pulse';
            markerDot = 'bg-blue-500 border-white ring-4 ring-blue-100';
        } else if (s.status === 'Not started') {
            statusClass = 'bg-amber-50 text-amber-700 border border-amber-100';
            markerDot = 'bg-amber-400 border-white ring-2 ring-amber-100';
        }
        
        return `
          <div class="relative flex gap-4">
              <div class="w-5 h-5 rounded-full border text-xs font-bold flex items-center justify-center shrink-0 z-10 transition-all ${markerDot === 'bg-gray-200 border-gray-300' ? 'bg-white text-gray-400 border-gray-200' : 'bg-brand-light text-brand border-brand/20'}">${s.num}</div>
              <div class="flex-1 min-w-0 pb-4">
                  <p class="text-xs font-bold text-gray-900">${s.name}</p>
                  <p class="text-[11px] text-gray-500 mt-0.5">${s.addr}</p>
                  <div class="mt-2">
                    <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${statusClass}">${s.status}</span>
                  </div>
              </div>
          </div>
        `;
    }).join('');

    // Update Floating Map HUD
    document.getElementById('hud-id').textContent = run.id;
    var hudDesc = document.getElementById('hud-desc');
    var hudSub = document.getElementById('hud-sub');
    var hudDot = document.getElementById('hud-dot');
    
    if (run.badgeText === 'Completed') {
        hudDesc.textContent = 'Delivery run completed successfully.';
        hudSub.textContent = 'All deliveries completed';
        hudSub.className = 'text-xs text-emerald-600 font-bold';
        hudDot.className = 'w-2.5 h-2.5 rounded-full bg-emerald-500';
    } else if (run.badgeText === 'Pending') {
        hudDesc.textContent = 'Pending start. Dispatch run to begin tracking.';
        hudSub.textContent = 'Pending dispatch';
        hudSub.className = 'text-xs text-amber-600 font-bold';
        hudDot.className = 'w-2.5 h-2.5 rounded-full bg-amber-500';
    } else {
        var nextInProg = run.stops.find(s => s.status === 'In progress') || run.stops.find(s => s.status === 'Not started');
        hudDesc.textContent = nextInProg ? `En route to Stop ${nextInProg.num}: ${nextInProg.name.split(' · ')[1] || nextInProg.name}` : 'En route';
        hudSub.textContent = 'Live GPS Connection Active';
        hudSub.className = 'text-xs text-blue-600 font-bold';
        hudDot.className = 'w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse';
    }
}

// Generate logs to the Activity telemetry window
function logTelemetry(message, type = 'INFO') {
    var telDiv = document.getElementById('t-telemetry');
    var timestamp = new Date().toLocaleTimeString([], { hour12: false });
    
    var colorClass = 'text-emerald-400';
    if (type === 'GPS') colorClass = 'text-blue-300';
    if (type === 'DELIVERED') colorClass = 'text-emerald-300 font-bold';
    if (type === 'SYSTEM') colorClass = 'text-gray-400';
    if (type === 'WARN') colorClass = 'text-amber-400';
    
    var logSpan = document.createElement('div');
    logSpan.className = `py-0.5 border-b border-white/5 last:border-b-0 ${colorClass}`;
    logSpan.innerHTML = `[${timestamp}] [${type}] ${message}`;
    telDiv.appendChild(logSpan);
    telDiv.scrollTop = telDiv.scrollHeight;
}

// Draw Route, Warehouse, and Stop Markers on Leaflet Map
function drawRouteOnMap(run) {
    // Clear existing elements
    if (warehouseMarker) map.removeLayer(warehouseMarker);
    stopMarkers.forEach(m => map.removeLayer(m));
    stopMarkers = [];
    if (vehicleMarker) map.removeLayer(vehicleMarker);
    vehicleMarker = null;
    if (routePolyline) map.removeLayer(routePolyline);
    routePolyline = null;
    
    var warehouseCoords = getWarehouseCoords(run.zone || 'Colombo');
    
    // 1. Draw Warehouse Marker
    var whHtml = `<div class="w-8 h-8 rounded-full bg-brand border border-white flex items-center justify-center shadow-lg text-white"><i class="ti ti-building-warehouse text-base"></i></div>`;
    var whIcon = L.divIcon({
        html: whHtml,
        className: 'custom-marker-warehouse',
        iconSize: [32, 32],
        iconAnchor: [16, 16]
    });
    warehouseMarker = L.marker(whHtml ? warehouseCoords : [6.95, 79.88], { icon: whIcon }).addTo(map)
        .bindPopup(`<b>KE Central Warehouse (${run.zone || 'Colombo'})</b>`);
        
    // 2. Draw Stop Markers & Gather Route Coordinates
    var routeCoords = [warehouseCoords];
    
    run.stops.forEach(s => {
        // Fallback coordinates if missing
        if (!s.lat || !s.lng) {
            // Generate minor random offset from warehouse for custom runs
            var offset = (Math.random() - 0.5) * 0.05;
            s.lat = warehouseCoords[0] + offset;
            s.lng = warehouseCoords[1] + (Math.random() - 0.5) * 0.05;
        }
        
        var markerColor = 'bg-amber-500';
        if (s.status.startsWith('Delivered')) markerColor = 'bg-emerald-500';
        if (s.status === 'In progress') markerColor = 'bg-blue-500';
        
        var stopHtml = `<div class="w-7 h-7 rounded-full border border-white flex items-center justify-center shadow-md text-white text-xs font-bold ${markerColor}">${s.num}</div>`;
        var stopIcon = L.divIcon({
            html: stopHtml,
            className: 'custom-marker-stop',
            iconSize: [28, 28],
            iconAnchor: [14, 14]
        });
        
        var stopM = L.marker([s.lat, s.lng], { icon: stopIcon }).addTo(map)
            .bindPopup(`<b>Stop ${s.num}: ${s.name}</b><br><span class="text-xs text-gray-500">${s.addr}</span><br><b class="text-xs mt-1 block">Status: ${s.status}</b>`);
        
        stopMarkers.push(stopM);
        routeCoords.push([s.lat, s.lng]);
    });
    
    // 3. Draw Route Polyline
    routePolyline = L.polyline(routeCoords, {
        color: '#0F6E56',
        weight: 3,
        opacity: 0.6,
        dashArray: '8, 8'
    }).addTo(map);
    
    // 4. Place Vehicle Marker
    // Prioritize simulated GPS coordinates from localStorage if they exist
    var initialVehiclePos = warehouseCoords;
    if (run.sim_coords) {
        initialVehiclePos = run.sim_coords;
    } else if (run.badgeText === 'Completed') {
        var lastStop = run.stops[run.stops.length - 1];
        initialVehiclePos = [lastStop.lat, lastStop.lng];
    } else if (run.badgeText === 'Active') {
        // Find current active index
        var firstIncompleteIdx = run.stops.findIndex(s => !s.status.startsWith('Delivered'));
        if (firstIncompleteIdx > 0) {
            var lastCompletedStop = run.stops[firstIncompleteIdx - 1];
            initialVehiclePos = [lastCompletedStop.lat, lastCompletedStop.lng];
        } else if (firstIncompleteIdx === 0) {
            initialVehiclePos = warehouseCoords;
        }
    }
    
    var vehicleIconClass = 'ti-motorbike';
    if (run.vehicle.toLowerCase().includes('van')) vehicleIconClass = 'ti-van';
    else if (run.vehicle.toLowerCase().includes('lorry')) vehicleIconClass = 'ti-truck';
    
    var vehicleHtml = `<div class="w-9 h-9 rounded-full bg-brand-light border-2 border-brand flex items-center justify-center shadow-lg text-brand text-base transition-all"><i class="ti ${vehicleIconClass}"></i></div>`;
    var vIcon = L.divIcon({
        html: vehicleHtml,
        className: 'custom-marker-vehicle',
        iconSize: [36, 36],
        iconAnchor: [18, 18]
    });
    
    vehicleMarker = L.marker(initialVehiclePos, { icon: vIcon }).addTo(map)
        .bindPopup(`<b>Live Tracking: ${run.driver}</b><br><span class="text-xs text-gray-500">${run.vehicle}</span>`);
        
    // 5. Fit map bounds to show route
    var bounds = L.latLngBounds(routeCoords);
    map.fitBounds(bounds, { padding: [50, 50] });
    
    // 6. Precompute leg interpolation coordinates for simulation
    legInterpolatedPaths = [];
    for (let i = 0; i < routeCoords.length - 1; i++) {
        legInterpolatedPaths.push(interpolatePoints(routeCoords[i], routeCoords[i+1], 40));
    }
    
    // Determine active index for simulation leg
    if (run.badgeText === 'Active') {
        var activeIdx = run.stops.findIndex(s => !s.status.startsWith('Delivered'));
        currentLegIndex = activeIdx !== -1 ? activeIdx : 0;
        currentStepIndex = 0;
    } else {
        currentLegIndex = 0;
        currentStepIndex = 0;
    }
}

// Change Tracking View target
function changeActiveRun(runId) {
    // Clean up running simulation first
    stopSimulation();
    
    activeRun = assignments.find(a => a.id === runId);
    if (!activeRun) return;
    
    updateUI(activeRun);
    drawRouteOnMap(activeRun);
    
    // Log dispatch event to telemetry
    document.getElementById('t-telemetry').innerHTML = '';
    logTelemetry(`Tracking connection initialized for ${activeRun.id}`, 'SYSTEM');
    logTelemetry(`Driver: ${activeRun.driver} (${activeRun.vehicle})`, 'SYSTEM');
    logTelemetry(`Base Warehouse: Central Warehouse (${activeRun.zone})`, 'SYSTEM');
    
    if (activeRun.badgeText === 'Pending') {
        logTelemetry(`Run is awaiting warehouse release. Ready for dispatch.`, 'WARN');
    } else if (activeRun.badgeText === 'Completed') {
        logTelemetry(`All ${activeRun.stops.length} deliveries completed successfully. Run finished.`, 'SYSTEM');
        activeRun.stops.forEach(s => {
            logTelemetry(`Stop ${s.num}: ${s.name.split(' · ')[1]} -- DELIVERED (${s.status.replace('Delivered ', '')})`, 'DELIVERED');
        });
    } else {
        // Active
        logTelemetry(`GPS Connection Established. Telemetry active.`, 'SYSTEM');
        activeRun.stops.forEach(s => {
            if (s.status.startsWith('Delivered')) {
                logTelemetry(`Stop ${s.num}: ${s.name.split(' · ')[1]} -- DELIVERED (${s.status.replace('Delivered ', '')})`, 'DELIVERED');
            } else if (s.status === 'In progress') {
                logTelemetry(`Stop ${s.num}: ${s.name.split(' · ')[1]} -- EN ROUTE`, 'GPS');
            } else {
                logTelemetry(`Stop ${s.num}: ${s.name.split(' · ')[1]} -- QUEUED`, 'SYSTEM');
            }
        });
    }
}

// Telemetry call simulated
function triggerQuickCall() {
    if (!activeRun) return;
    showToast(`Dialing driver ${activeRun.driver} (${activeRun.vehicle.split(' · ')[0]})...`, 'info');
    logTelemetry(`Voice link established with driver: ${activeRun.driver}`, 'SYSTEM');
}

// Instantly skip current leg to next stop — DRIVER PORTAL ONLY
// (Admin tracking view is read-only; simulation is driven by the driver portal)


// Listen for storage events from the Driver Portal
window.addEventListener('storage', (e) => {
    if (e.key === 'ke_assignments') {
        // Reload assignments from localStorage
        initData();
        
        // Find current active run in updated array
        if (activeRun) {
            var updatedRun = assignments.find(a => a.id === activeRun.id);
            if (updatedRun) {
                // Sync status and stops
                var oldSimCoords = activeRun.sim_coords;
                activeRun = updatedRun;
                updateUI(activeRun);
                
                // Redraw route or move marker
                if (activeRun.sim_coords && vehicleMarker) {
                    vehicleMarker.setLatLng(activeRun.sim_coords);
                }
                
                // Redraw stops on map if status changed (e.g. delivered, failed)
                drawRouteOnMap(activeRun);
            }
        }
        
        // Refresh runs selector dropdown text
        var currentSelVal = document.getElementById('run-selector').value;
        populateRunsDropdown();
        document.getElementById('run-selector').value = currentSelVal;
    }
});

function closeTrackingSidebar() {
    var sidebar = document.getElementById('tracking-sidebar');
    if (sidebar) sidebar.classList.add('-translate-x-full');
}

function openTrackingSidebar() {
    var sidebar = document.getElementById('tracking-sidebar');
    if (sidebar) sidebar.classList.remove('-translate-x-full');
}

// Initialization on load
function startApp() {
    if (typeof L === 'undefined') {
        setTimeout(startApp, 50);
        return;
    }
    initData();
    initMap();
    populateRunsDropdown();
    
    // Route to requested ID from query parameters or default to first
    var urlParams = new URLSearchParams(window.location.search);
    var requestedId = urlParams.get('id');
    
    if (requestedId && assignments.some(a => a.id === requestedId)) {
        document.getElementById('run-selector').value = requestedId;
        changeActiveRun(requestedId);
    } else if (assignments.length > 0) {
        document.getElementById('run-selector').value = assignments[0].id;
        changeActiveRun(assignments[0].id);
    }

    document.getElementById('toggle-tracking-sidebar').addEventListener('click', openTrackingSidebar);
    if (window.innerWidth < 1024) {
        closeTrackingSidebar();
    }
}

document.addEventListener('DOMContentLoaded', startApp);
</script>
