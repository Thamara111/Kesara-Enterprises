<?php
/**
 * Inventory Management View
 * Converted to Tailwind CSS for Kesara Enterprises Admin Panel
 */

$rows = [];
if (isset($pdo) && $pdo !== null) {
    try {
        $stmt = $pdo->query("SELECT i.id, i.product_id, i.size, i.colour, i.quantity AS stock, i.restock_min AS thresh, p.name AS product_name, p.sku 
                             FROM inventory i 
                             JOIN products p ON i.product_id = p.id");
        $db_rows = $stmt->fetchAll();
        foreach ($db_rows as $row) {
            // Fetch logs for this inventory item
            $log_stmt = $pdo->prepare("SELECT adj_type, qty_before, qty_after, note, created_at FROM inventory_log WHERE inventory_id = ? ORDER BY created_at DESC");
            $log_stmt->execute([$row['id']]);
            $item_logs = [];
            foreach ($log_stmt->fetchAll() as $log) {
                $diff = $log['qty_after'] - $log['qty_before'];
                $sign = $diff >= 0 ? '+' : '';
                $item_logs[] = [
                    'date' => date('d M', strtotime($log['created_at'])),
                    'change' => $sign . $diff . ' (' . htmlspecialchars($log['note']) . ')',
                    'class' => $diff >= 0 ? 'text-emerald-600' : 'text-red-600',
                    'by' => 'Admin'
                ];
            }

            $rows[] = [
                'id' => (int)$row['id'],
                'name' => $row['product_name'] . ' · ' . $row['colour'] . ' · ' . $row['size'],
                'sku' => $row['sku'],
                'size' => $row['size'],
                'stock' => (int)$row['stock'],
                'thresh' => (int)$row['thresh'],
                'logs' => $item_logs
            ];
        }
    } catch (\Exception $e) {
        $db_error = $e->getMessage();
    }
}

if (empty($rows)) {
    $rows = [
      [ 'id' => 1, 'name'=>'Classic brief · Black · XL', 'sku'=>'KB-001', 'size'=> 'XL', 'stock'=>18, 'thresh'=>200, 'logs' => [
          [ 'date' => '11 May', 'change' => '− 120 (order KE-00847)', 'class' => 'text-red-600', 'by' => 'Admin' ],
          [ 'date' => '2 May', 'change' => '− 80 (order KE-00821)', 'class' => 'text-red-600', 'by' => 'System' ],
          [ 'date' => '28 Apr', 'change' => '+ 500 (restock batch #38)', 'class' => 'text-emerald-600', 'by' => 'Admin' ]
      ]],
      [ 'id' => 2, 'name'=>'Ladies hipster · White · S', 'sku'=>'KL-003', 'size'=> 'S', 'stock'=>34, 'thresh'=>200, 'logs' => [
          [ 'date' => '11 May', 'change' => '− 120 (order KE-00847)', 'class' => 'text-red-600', 'by' => 'Admin' ],
          [ 'date' => '2 May', 'change' => '− 80 (order KE-00821)', 'class' => 'text-red-600', 'by' => 'System' ]
      ]],
      [ 'id' => 3, 'name'=>'Kids trunk · Navy · M',      'sku'=>'KC-012', 'size'=> 'M', 'stock'=>62, 'thresh'=>200, 'logs' => [] ],
      [ 'id' => 4, 'name'=>'Stretch boxer · Navy · S',   'sku'=>'KB-008', 'size'=> 'S', 'stock'=>88, 'thresh'=>200, 'logs' => [] ],
      [ 'id' => 5, 'name'=>'Modal trunk · Black · M',    'sku'=>'KB-015', 'size'=> 'M', 'stock'=>0,  'thresh'=>200, 'logs' => [] ],
      [ 'id' => 6, 'name'=>'Classic brief · White · M',  'sku'=>'KB-001', 'size'=> 'M', 'stock'=>480,'thresh'=>200, 'logs' => [] ]
    ];
}

$total_skus = count($rows);
$critical_count = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;

foreach ($rows as $r) {
    $p = 0;
    if ($r['thresh'] > 0) {
        $p = min(100, (int)round(($r['stock'] / $r['thresh']) * 100));
    }
    if ($r['stock'] === 0) {
        $out_of_stock_count++;
    } elseif ($p <= 15) {
        $critical_count++;
    } elseif ($p <= 50) {
        $low_stock_count++;
    }
}
?>

<div class="flex-1 flex overflow-hidden">
    <!-- List Pane -->
    <div class="flex-1 flex flex-col min-w-0 bg-white">
        <!-- Header -->
        <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Inventory</h1>
                <p class="text-sm text-gray-500 mt-1">Manage stock levels and warehouse replenishment.</p>
            </div>
            <button class="flex items-center gap-2 px-4 py-2 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition-all">
                <i class="ti ti-download"></i>
                Export CSV
            </button>
        </div>

        <!-- Stats -->
        <div class="px-8 py-6 grid grid-cols-4 gap-4 border-b border-gray-100">
            <div class="bg-gray-50/50 rounded-2xl p-4 border border-gray-100">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Total SKUs</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?= $total_skus ?></p>
            </div>
            <div class="bg-red-50/50 rounded-2xl p-4 border border-red-100">
                <p class="text-[10px] font-bold text-red-500 uppercase tracking-wider">Critical</p>
                <p class="text-2xl font-bold text-red-600 mt-1"><?= $critical_count ?></p>
            </div>
            <div class="bg-amber-50/50 rounded-2xl p-4 border border-amber-100">
                <p class="text-[10px] font-bold text-amber-500 uppercase tracking-wider">Low Stock</p>
                <p class="text-2xl font-bold text-amber-600 mt-1"><?= $low_stock_count ?></p>
            </div>
            <div class="bg-gray-100/50 rounded-2xl p-4 border border-gray-200">
                <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Out of Stock</p>
                <p class="text-2xl font-bold text-gray-500 mt-1"><?= $out_of_stock_count ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="px-8 py-4 bg-gray-50/30 flex items-center gap-4 border-b border-gray-100">
            <div class="relative flex-1">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" placeholder="Search product or SKU..." class="w-full pl-11 pr-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm transition-all">
            </div>
            <select class="px-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm font-medium transition-all">
                <option>All categories</option>
                <option>Men's briefs</option>
                <option>Men's boxers</option>
                <option>Men's trunks</option>
                <option>Ladies</option>
                <option>Children</option>
            </select>
        </div>

        <!-- Tabs -->
        <div class="px-8 py-3 flex flex-nowrap gap-2 border-b border-gray-100 overflow-x-auto no-scrollbar">
            <button class="chip on" onclick="chipFilter(this)">All</button>
            <button class="chip" onclick="chipFilter(this)">Critical</button>
            <button class="chip" onclick="chipFilter(this)">Low stock</button>
            <button class="chip" onclick="chipFilter(this)">Out of stock</button>
            <button class="chip" onclick="chipFilter(this)">In stock</button>
        </div>

        <!-- List Header -->
        <div class="px-8 py-3 bg-gray-50/50 grid grid-cols-[minmax(200px,2fr)_60px_70px_80px_1fr_100px] gap-4 border-b border-gray-100 text-[10px] font-bold text-gray-400 uppercase tracking-wider items-center">
            <span>Product / Variant</span>
            <span class="text-center">Size</span>
            <span class="text-center">Stock</span>
            <span class="text-center">Min</span>
            <span class="text-center">Health</span>
            <span class="text-right">Status</span>
        </div>

        <!-- List -->
        <div class="flex-1 overflow-y-auto overflow-x-auto no-scrollbar" id="inv-list-container">
            <div class="min-w-[800px] p-6 space-y-1" id="inv-list">
                <!-- Dynamic items -->
            </div>
        </div>

        <!-- Pagination -->
        <div class="px-8 py-4 border-t border-gray-100 flex items-center justify-between">
            <p class="text-xs font-medium text-gray-500">Showing <span class="text-gray-900"><?= count($rows) ?></span> of <span class="text-gray-900"><?= $total_skus ?></span> variants</p>
            <div class="flex gap-2">
                <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50 transition-all"><i class="ti ti-chevron-left text-sm"></i></button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg bg-brand text-white font-bold text-xs shadow-lg shadow-brand/20">1</button>
                <button class="w-8 h-8 flex items-center justify-center rounded-lg border border-gray-200 text-gray-400 hover:bg-gray-50 transition-all"><i class="ti ti-chevron-right text-sm"></i></button>
            </div>
        </div>
    </div>

    <!-- Adjustment Pane -->
    <!-- Backdrop -->
    <div id="inventory-detail-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 backdrop-blur-[2px] transition-opacity duration-300" onclick="closeAdjPane()"></div>
    <div class="fixed inset-y-0 right-0 z-50 w-[400px] max-w-full bg-gray-50 border-l border-gray-200 overflow-y-auto flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300" id="adj-pane">
        <div class="p-8 border-b border-gray-200 bg-white">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Selected Variant</h3>
                <button onclick="closeAdjPane()" class="p-1.5 text-gray-400 hover:text-brand transition-colors focus:outline-none" aria-label="Close details">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>
            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-200">
                <p class="text-lg font-bold text-gray-900" id="d-name">Classic brief · Black · XL</p>
                <p class="text-xs text-gray-500 mt-1" id="d-sku">SKU: KB-001</p>
                
                <div class="mt-6 flex justify-between items-end">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Current Stock</p>
                        <p class="text-4xl font-bold mt-1" id="d-stock">18</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Threshold</p>
                        <p class="text-lg font-bold text-gray-900 mt-1" id="d-thresh">200</p>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" id="d-bar" style="width: 9%; background-color: #E24B4A;"></div>
                    </div>
                    <p class="text-xs font-bold mt-2" id="d-status-text" style="color: #791F1F">9% of threshold — restock urgently</p>
                </div>
            </div>
        </div>

        <div class="p-8 space-y-8 flex-1">
            <!-- Adjust Stock -->
            <div>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Adjust Stock</h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5">Adjustment Type</label>
                        <select id="adj-type" onchange="updateAdjLabel()" class="w-full px-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm font-medium transition-all">
                            <option value="add">Add stock (restock received)</option>
                            <option value="remove">Remove stock (damaged / loss)</option>
                            <option value="set">Set exact quantity</option>
                        </select>
                    </div>

                    <div>
                        <label id="adj-qty-label" class="block text-xs font-bold text-gray-500 mb-1.5">Quantity to add</label>
                        <input type="number" id="adj-qty" value="200" min="0" class="w-full px-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm transition-all font-bold">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-500 mb-1.5">Reason / Note</label>
                        <input type="text" placeholder="e.g. Restock batch #42 received" id="adj-note" class="w-full px-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm transition-all">
                    </div>

                    <div class="p-4 bg-gray-100 rounded-2xl flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-500">New stock will be:</span>
                        <span class="text-sm font-bold text-gray-900" id="adj-preview">218 units</span>
                    </div>

                    <button class="w-full py-3 rounded-xl bg-brand text-brand-light font-bold text-sm shadow-lg shadow-brand/20 hover:scale-[1.02] active:scale-[0.98] transition-all flex items-center justify-center gap-2" onclick="applyAdj()">
                        Apply adjustment
                    </button>
                </div>
            </div>

            <!-- Adjustment Log -->
            <div>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Adjustment Log</h3>
                <div class="space-y-1" id="adj-log">
                    <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 text-xs">
                        <span class="font-bold text-gray-400">11 May</span>
                        <span class="font-bold text-red-600">− 120 (order KE-00847)</span>
                        <span class="font-bold text-gray-400 uppercase text-[9px]">Admin</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 text-xs">
                        <span class="font-bold text-gray-400">2 May</span>
                        <span class="font-bold text-red-600">− 80 (order KE-00821)</span>
                        <span class="font-bold text-gray-400 uppercase text-[9px]">System</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 text-xs">
                        <span class="font-bold text-gray-400">28 Apr</span>
                        <span class="font-bold text-emerald-600">+ 500 (restock batch #38)</span>
                        <span class="font-bold text-gray-400 uppercase text-[9px]">Admin</span>
                    </div>
                </div>
            </div>

            <!-- Threshold Update -->
            <div>
                <h3 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-4">Update Restock Threshold</h3>
                <div class="flex gap-2">
                    <input type="number" id="thresh-input" value="200" class="flex-1 px-4 py-2 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-sm transition-all font-bold">
                    <button class="px-6 py-2 rounded-xl border border-gray-200 text-gray-600 font-bold text-sm hover:bg-gray-50 transition-all">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .chip {
        padding: 0.5rem 1rem;
        border-radius: 0.75rem;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid #e5e7eb;
        color: #6b7280;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        white-space: nowrap;
        flex-shrink: 0;
        background-color: transparent;
    }
    .chip:hover {
        background-color: #f3f4f6;
    }
    .chip.on {
        background-color: #0F6E56;
        color: #ffffff;
        border-color: #0F6E56;
        box-shadow: 0 10px 15px -3px rgba(15, 110, 86, 0.2);
    }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
const rows = <?php echo json_encode($rows); ?>;
let selIdx = 0;

function pct(s,t){ return Math.min(100, Math.round(s/t*100)); }
function barColor(p){ return p<=15?'#E24B4A':p<=50?'#EF9F27':'#1D9E75'; }
function stockColor(p){ return p<=15?'#791F1F':p<=50?'#633806':'#111827'; }
function statusText(p){ return p===0?'Out of stock — cannot fulfil orders':p<=15?`${p}% of threshold — restock urgently`:p<=50?`${p}% of threshold — restock soon`:`${p}% of threshold — healthy`; }
function statusColor(p){ return p<=15?'#791F1F':p<=50?'#633806':'#085041'; }
function badgeClass(p){ return p===0?'bg-gray-100 text-gray-500 border-gray-200':p<=15?'bg-red-100 text-red-700':p<=50?'bg-amber-100 text-amber-700':'bg-emerald-100 text-emerald-700'; }
function badgeText(p){ return p===0?'Out of stock':p<=15?'Critical':p<=50?'Low stock':'In stock'; }

let activeFilter = 'All';

function renderList() {
    const list = document.getElementById('inv-list');
    
    const filteredRows = rows.map((r, i) => ({ ...r, originalIndex: i })).filter(r => {
        const p = pct(r.stock, r.thresh);
        const status = r.stock === 0 ? 'Out of stock' : p <= 15 ? 'Critical' : p <= 50 ? 'Low stock' : 'In stock';
        
        if (activeFilter === 'All') return true;
        if (activeFilter === 'Critical') return status === 'Critical';
        if (activeFilter === 'Low stock') return status === 'Low stock';
        if (activeFilter === 'Out of stock') return status === 'Out of stock';
        if (activeFilter === 'In stock') return status === 'In stock';
        return true;
    });

    if (filteredRows.length === 0) {
        list.innerHTML = `<div class="text-xs text-gray-400 text-center py-10 italic">No variants match this filter.</div>`;
        return;
    }

    list.innerHTML = filteredRows.map((r) => {
        const p = pct(r.stock, r.thresh);
        const isSelected = r.originalIndex === selIdx;
        return `
        <div class="grid grid-cols-[minmax(200px,2fr)_60px_70px_80px_1fr_100px] gap-4 p-4 rounded-2xl cursor-pointer transition-all border border-transparent items-center ${isSelected ? 'bg-brand/5 border-brand/10 shadow-sm' : 'hover:bg-gray-50'}" onclick="selectRow(this, ${r.originalIndex})">
            <div class="min-w-0 flex flex-col justify-center">
                <p class="text-sm font-bold text-gray-900 leading-tight truncate">${r.name.split(' · ').slice(0,2).join(' · ')}</p>
                <p class="text-[10px] text-gray-400 mt-1 uppercase font-bold tracking-tight">${r.sku}</p>
            </div>
            <div class="flex items-center justify-center text-xs font-bold text-gray-500 bg-gray-100/50 rounded-lg h-8">${r.size}</div>
            <div class="flex items-center justify-center text-sm font-black" style="color: ${stockColor(p)}">${r.stock}</div>
            <div class="flex items-center justify-center text-xs font-bold text-gray-400">${r.thresh}</div>
            <div class="flex items-center px-2">
                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500" style="width: ${p}%; background-color: ${barColor(p)}"></div>
                </div>
            </div>
            <div class="flex items-center justify-end">
                <span class="px-2.5 py-1 rounded-full text-[9px] font-black uppercase tracking-widest ${badgeClass(p)} border border-transparent shadow-sm">${badgeText(p)}</span>
            </div>
        </div>`;
    }).join('');
}

function renderDetail(idx){
  if (!rows || rows.length === 0) return;
  const r = rows[idx];
  const p = pct(r.stock, r.thresh);
  document.getElementById('d-name').textContent = r.name;
  document.getElementById('d-sku').textContent = 'SKU: '+r.sku;
  document.getElementById('d-stock').textContent = r.stock;
  document.getElementById('d-stock').style.color = stockColor(p);
  document.getElementById('d-thresh').textContent = r.thresh;
  document.getElementById('d-bar').style.width = p+'%';
  document.getElementById('d-bar').style.backgroundColor = barColor(p);
  document.getElementById('d-status-text').textContent = statusText(p);
  document.getElementById('d-status-text').style.color = statusColor(p);
  document.getElementById('adj-qty').value = Math.max(0, r.thresh - r.stock);
  document.getElementById('thresh-input').value = r.thresh;
  
  const logContainer = document.getElementById('adj-log');
  if (r.logs && r.logs.length > 0) {
      logContainer.innerHTML = r.logs.map(l => `
          <div class="flex items-center justify-between p-3 bg-white rounded-xl border border-gray-100 text-xs">
              <span class="font-bold text-gray-400">${l.date}</span>
              <span class="font-bold ${l.class}">${l.change}</span>
              <span class="font-bold text-gray-400 uppercase text-[9px]">${l.by}</span>
          </div>
      `).join('');
  } else {
      logContainer.innerHTML = `<div class="text-xs text-gray-400 text-center py-4 italic">No adjustments recorded.</div>`;
  }
  updatePreview();
}

function updateAdjLabel(){
  const t = document.getElementById('adj-type').value;
  const labels = {add:'Quantity to add',remove:'Quantity to remove',set:'Set exact quantity'};
  document.getElementById('adj-qty-label').textContent = labels[t];
  updatePreview();
}

function updatePreview(){
  const t = document.getElementById('adj-type').value;
  const q = parseInt(document.getElementById('adj-qty').value)||0;
  if (!rows || rows.length === 0) return;
  const r = rows[selIdx];
  let next = t==='add'?r.stock+q:t==='remove'?Math.max(0,r.stock-q):q;
  document.getElementById('adj-preview').textContent = next+' units';
}

document.getElementById('adj-qty').addEventListener('input', updatePreview);

function applyAdj(){
  const t = document.getElementById('adj-type').value;
  const q = parseInt(document.getElementById('adj-qty').value)||0;
  const note = document.getElementById('adj-note').value || 'Manual adjustment';
  if (!rows || rows.length === 0) return;
  const r = rows[selIdx];
  const prev = r.stock;
  r.stock = t==='add'?r.stock+q:t==='remove'?Math.max(0,r.stock-q):q;
  const diff = r.stock - prev;
  const sign = diff>=0?'+':'';
  
  if (!r.logs) r.logs = [];
  r.logs.unshift({
      date: 'Now',
      change: `${sign}${diff} (${note})`,
      class: diff>=0?'text-emerald-600':'text-red-600',
      by: 'Admin'
  });
  
  renderList();
  renderDetail(selIdx);
}

function selectRow(el, idx, openDrawer = true){
  selIdx = idx;
  renderList();
  renderDetail(idx);
  if (openDrawer) {
    const pane = document.getElementById('adj-pane');
    const backdrop = document.getElementById('inventory-detail-backdrop');
    if (pane) pane.classList.remove('translate-x-full');
    if (backdrop) {
        backdrop.classList.remove('hidden');
        requestAnimationFrame(() => backdrop.classList.add('opacity-100'));
    }
  }
}

function chipFilter(el){
  document.querySelectorAll('.chip').forEach(c=>c.classList.remove('on'));
  el.classList.add('on');
  activeFilter = el.textContent.trim();
  
  // Ensure the slide bar is closed when clicking on filters
  closeAdjPane();
  
  // Find first matching row's index to load details
  const filtered = rows.map((r, i) => ({ ...r, originalIndex: i })).filter(r => {
      const p = pct(r.stock, r.thresh);
      const status = r.stock === 0 ? 'Out of stock' : p <= 15 ? 'Critical' : p <= 50 ? 'Low stock' : 'In stock';
      if (activeFilter === 'All') return true;
      if (activeFilter === 'Critical') return status === 'Critical';
      if (activeFilter === 'Low stock') return status === 'Low stock';
      if (activeFilter === 'Out of stock') return status === 'Out of stock';
      if (activeFilter === 'In stock') return status === 'In stock';
      return true;
  });
  
  if (filtered.length > 0) {
      selIdx = filtered[0].originalIndex;
      renderDetail(selIdx);
  }
  
  renderList();
}

function closeAdjPane() {
  const pane = document.getElementById('adj-pane');
  const backdrop = document.getElementById('inventory-detail-backdrop');
  if (pane) pane.classList.add('translate-x-full');
  if (backdrop) {
      backdrop.classList.remove('opacity-100');
      backdrop.classList.add('hidden');
  }
}

// Initial render
renderList();
if (rows && rows.length > 0) {
  renderDetail(0);
}
closeAdjPane();
</script>
