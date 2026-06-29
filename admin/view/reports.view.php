<?php
/**
 * Analytics & Reports View
 * Converted to Tailwind CSS for Kesara Enterprises Admin Panel
 */
?>

<div class="flex-1 flex flex-col min-w-0 bg-white overflow-y-auto overflow-x-hidden no-scrollbar">
    <!-- Header -->
    <div class="px-8 py-6 border-b border-gray-100 flex items-center justify-between sticky top-0 bg-white z-10">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Analytics & Reports</h1>
            <p class="text-sm text-gray-500 mt-1">System performance and wholesale business analytics.</p>
        </div>
        <div class="flex items-center gap-3">
            <select class="px-4 py-2.5 rounded-xl border-none ring-1 ring-gray-200 focus:ring-2 focus:ring-brand bg-white text-xs font-bold transition-all">
                <option>This month (May 2025)</option>
                <option>Last month</option>
                <option>Last 3 months</option>
                <option>Last 6 months</option>
                <option>This year</option>
                <option>Custom range</option>
            </select>
            <div class="flex items-center gap-1">
                <button class="flex items-center gap-2 px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-bold text-gray-600 hover:bg-gray-50 transition-all shadow-sm" onclick="exportActiveReport()">
                    <i class="ti ti-download"></i> PDF Report
                </button>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="px-8 py-4 border-b border-gray-100 flex gap-4 overflow-x-auto no-scrollbar">
        <button class="chip on" onclick="switchTab(this,'sales')">Sales Report</button>
        <button class="chip" onclick="switchTab(this,'products')">Top Products</button>
        <button class="chip" onclick="switchTab(this,'customers')">Top Customers</button>
    </div>

    <div class="p-8 space-y-12 max-w-7xl w-full mx-auto">
        
        <!-- SALES TAB -->
        <div id="tab-sales" class="space-y-8 animate-in fade-in duration-500">
            <!-- Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Total Revenue</p>
                    <p class="text-2xl font-black text-gray-900">LKR 1.4M</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-trending-up"></i> +18% vs Apr
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Total Orders</p>
                    <p class="text-2xl font-black text-gray-900">47</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-trending-up"></i> +9 vs Apr
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Avg Order Value</p>
                    <p class="text-2xl font-black text-gray-900">LKR 29,787</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-trending-up"></i> +8% vs Apr
                    </p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm transition-all hover:shadow-md">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Units Sold</p>
                    <p class="text-2xl font-black text-gray-900">18,200</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-trending-up"></i> +2,400 vs Apr
                    </p>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <div class="flex items-center justify-between mb-8">
                        <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Monthly Revenue — Last 6 Months</h3>
                        <div class="flex items-center gap-4 text-[10px] font-bold uppercase tracking-wider">
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-brand"></span> Revenue</span>
                            <span class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-brand-light"></span> Orders</span>
                        </div>
                    </div>
                    <div class="h-80 w-full">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Revenue by Category</h3>
                    <div class="h-80 w-full relative">
                        <canvas id="catChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-tighter">Total</p>
                            <p class="text-xl font-black text-gray-900">LKR 1.4M</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- PRODUCTS TAB -->
        <div id="tab-products" class="hidden space-y-8 animate-in fade-in duration-500">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Best Seller by Units</p>
                    <p class="text-xl font-black text-gray-900">Classic Brief</p>
                    <p class="text-xs font-medium text-gray-500 mt-2 italic">6,200 units this month</p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Highest Revenue</p>
                    <p class="text-xl font-black text-gray-900">Modal Trunk</p>
                    <p class="text-xs font-medium text-gray-500 mt-2 italic">LKR 380K this month</p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Fastest Growing</p>
                    <p class="text-xl font-black text-gray-900">Ladies Hipster</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1">
                        <i class="ti ti-trending-up"></i> +34% vs last month
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
                <div class="lg:col-span-3 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Product Performance Table</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-50">
                                    <th class="pb-4 px-2">Product</th>
                                    <th class="pb-4 px-2 text-center">Units</th>
                                    <th class="pb-4 px-2 text-center">Revenue</th>
                                    <th class="pb-4 px-2 text-center">Growth</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-2 font-bold text-sm text-gray-900">Classic Brief</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">6,200</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">LKR 682K</td>
                                    <td class="py-4 px-2 text-center text-xs font-bold text-emerald-600">+12%</td>
                                </tr>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-2 font-bold text-sm text-gray-900">Stretch Boxer</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">3,800</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">LKR 589K</td>
                                    <td class="py-4 px-2 text-center text-xs font-bold text-emerald-600">+5%</td>
                                </tr>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-2 font-bold text-sm text-gray-900">Ladies Hipster</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">3,100</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">LKR 403K</td>
                                    <td class="py-4 px-2 text-center text-xs font-bold text-emerald-600">+34%</td>
                                </tr>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-2 font-bold text-sm text-gray-900">Kids Trunk</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">2,700</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">LKR 235K</td>
                                    <td class="py-4 px-2 text-center text-xs font-bold text-gray-400">0%</td>
                                </tr>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="py-4 px-2 font-bold text-sm text-gray-900">Modal Trunk</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">2,400</td>
                                    <td class="py-4 px-2 text-center text-sm font-medium">LKR 380K</td>
                                    <td class="py-4 px-2 text-center text-xs font-bold text-red-500">-8%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="lg:col-span-2 bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Top 5 Products by Units</h3>
                    <div class="h-80 w-full">
                        <canvas id="prodChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- CUSTOMERS TAB -->
        <div id="tab-customers" class="hidden space-y-8 animate-in fade-in duration-500">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Active Buyers</p>
                    <p class="text-2xl font-black text-gray-900">142</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2">+5 new this month</p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Avg Spend / Buyer</p>
                    <p class="text-2xl font-black text-gray-900">LKR 9,859</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1"><i class="ti ti-trending-up"></i> +11% vs Apr</p>
                </div>
                <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Repeat Order Rate</p>
                    <p class="text-2xl font-black text-gray-900">68%</p>
                    <p class="text-xs font-bold text-emerald-600 mt-2 flex items-center gap-1"><i class="ti ti-trending-up"></i> +4% vs Apr</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">Top Customers by Spend (May 2025)</h3>
                    <div class="space-y-6">
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-gray-900">Seylan Stores</span>
                                <span class="text-gray-500 font-medium">LKR 312K (22%)</span>
                            </div>
                            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full" style="width: 100%"></div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-gray-900">Fashion Hub</span>
                                <span class="text-gray-500 font-medium">LKR 248K (18%)</span>
                            </div>
                            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full opacity-80" style="width: 79%"></div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-gray-900">City Retail</span>
                                <span class="text-gray-500 font-medium">LKR 186K (13%)</span>
                            </div>
                            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full opacity-70" style="width: 60%"></div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-gray-900">ABC Garments</span>
                                <span class="text-gray-500 font-medium">LKR 144K (10%)</span>
                            </div>
                            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full opacity-60" style="width: 46%"></div>
                            </div>
                        </div>
                        <div class="flex flex-col gap-2">
                            <div class="flex justify-between items-center text-sm">
                                <span class="font-bold text-gray-900">Nimal Traders</span>
                                <span class="text-gray-500 font-medium">LKR 98K (7%)</span>
                            </div>
                            <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full bg-brand rounded-full opacity-50" style="width: 31%"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-3xl p-8 border border-gray-100 shadow-sm">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-8">New Buyers per Month</h3>
                    <div class="h-80 w-full">
                        <canvas id="buyerChart"></canvas>
                    </div>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
const grid = 'rgba(0,0,0,0.04)';
const lbl = '#9ca3af';

// Shared Chart Options
const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false }
    }
};

// Revenue Chart
new Chart(document.getElementById('revenueChart'), {
    type: 'bar',
    data: {
        labels: ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'],
        datasets: [
            { label: 'Revenue', data: [800000, 950000, 1100000, 900000, 1200000, 1400000], backgroundColor: '#0F6E56', borderRadius: 8, barThickness: 20, yAxisID: 'y' },
            { label: 'Orders', data: [31, 38, 42, 35, 38, 47], backgroundColor: '#E1F5EE', borderRadius: 8, barThickness: 20, yAxisID: 'y2' }
        ]
    },
    options: {
        ...commonOptions,
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } },
            y: { position: 'left', grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl, callback: v => 'LKR ' + (v / 1000) + 'K' } },
            y2: { position: 'right', grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } }
        }
    }
});

// Category Chart
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: ["Men's briefs", "Men's boxers", "Ladies", "Trunks", "Children"],
        datasets: [{ data: [38, 24, 22, 10, 6], backgroundColor: ['#0F6E56', '#378ADD', '#7F77DD', '#EF9F27', '#9ca3af'], borderWidth: 0, cutout: '75%' }]
    },
    options: commonOptions
});

// Products Chart
new Chart(document.getElementById('prodChart'), {
    type: 'bar',
    data: {
        labels: ['Classic brief', 'Stretch boxer', 'Ladies hipster', 'Kids trunk', 'Modal trunk'],
        datasets: [{ label: 'Units sold', data: [6200, 3800, 3100, 2700, 2400], backgroundColor: '#0F6E56', borderRadius: 6, barThickness: 16 }]
    },
    options: {
        ...commonOptions,
        indexAxis: 'y',
        scales: {
            x: { grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } },
            y: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } }
        }
    }
});

// Buyer Chart
new Chart(document.getElementById('buyerChart'), {
    type: 'bar',
    data: {
        labels: ['Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May'],
        datasets: [{ label: 'New buyers', data: [4, 7, 9, 6, 11, 5], backgroundColor: '#0F6E56', borderRadius: 6, barThickness: 24 }]
    },
    options: {
        ...commonOptions,
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl } },
            y: { grid: { color: grid }, border: { display: false }, ticks: { font: { size: 10, weight: 'bold' }, color: lbl, stepSize: 2 } }
        }
    }
});

function switchTab(el, tab) {
    document.querySelectorAll('.chip').forEach(t => t.classList.remove('on'));
    el.classList.add('on');
    ['sales', 'products', 'customers'].forEach(t => {
        const pane = document.getElementById('tab-' + t);
        if (t === tab) {
            pane.classList.remove('hidden');
            pane.classList.add('block');
        } else {
            pane.classList.remove('block');
            pane.classList.add('hidden');
        }
    });
}

function exportActiveReport() {
    let activeTab = 'sales';
    document.querySelectorAll('.chip').forEach(t => {
        if(t.classList.contains('on')) {
            if(t.innerText.includes('Sales')) activeTab = 'sales';
            else if(t.innerText.includes('Products')) activeTab = 'products';
            else if(t.innerText.includes('Customers')) activeTab = 'customers';
        }
    });
    const id = 'tab-' + activeTab;
    const name = activeTab.charAt(0).toUpperCase() + activeTab.slice(1) + "_Report";
    downloadPDF(id, name);
}
</script>
