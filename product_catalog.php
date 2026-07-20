<?php
/**
 * Product Catalog Page
 * Lists all available wholesale products with filtering and sorting capabilities.
 * Allows customers to browse by category, size, stock status, and MOQ.
 */
require_once __DIR__ . "/database/connection.php";

$filter_search   = trim($_GET['search']   ?? '');
$filter_category = trim($_GET['category'] ?? '');
$filter_sizes    = isset($_GET['sizes']) && is_array($_GET['sizes']) ? array_map('trim', $_GET['sizes']) : [];
$filter_stock    = isset($_GET['stock']) && is_array($_GET['stock'])  ? array_map('trim', $_GET['stock'])  : [];
$filter_moq      = is_numeric($_GET['moq'] ?? '') ? (int)$_GET['moq'] : null;
$filter_sort     = $_GET['sort'] ?? 'newest';

$all_categories = [];
if ($pdo) {
    try {
        $all_categories = $pdo->query("SELECT id, name, slug FROM categories ORDER BY name")->fetchAll();
    } catch (\Exception $e) {}
}

$catalog_products = [];
if ($pdo) {
    try {
        $where2  = ["p.deleted_at IS NULL"];
        $params2 = [];
        if ($filter_search !== '') {
            $where2[]  = "(p.name LIKE ? OR p.sku LIKE ?)";
            $params2[] = '%' . $filter_search . '%';
            $params2[] = '%' . $filter_search . '%';
        }
        if ($filter_category !== '') {
            $where2[]  = "c.slug = ?";
            $params2[] = $filter_category;
        }
        if (!empty($filter_stock)) {
            $status_map  = ['in_stock' => 'In Stock','low_stock' => 'Low Stock','on_order' => 'On Order'];
            $status_vals = [];
            foreach ($filter_stock as $k) { if (isset($status_map[$k])) $status_vals[] = $status_map[$k]; }
            if (!empty($status_vals)) {
                $where2[]  = "p.status IN (" . implode(',', array_fill(0, count($status_vals), '?')) . ")";
                $params2   = array_merge($params2, $status_vals);
            }
        }
        if ($filter_moq !== null) { $where2[] = "p.moq >= ?"; $params2[] = $filter_moq; }
        $size_join2 = '';
        if (!empty($filter_sizes)) {
            $size_join2 = "INNER JOIN inventory inv ON inv.product_id = p.id";
            $where2[]   = "inv.size IN (" . implode(',', array_fill(0, count($filter_sizes), '?')) . ")";
            $params2    = array_merge($params2, $filter_sizes);
        }
        $order_map = ['newest'=>'p.created_at DESC','price_asc'=>'p.base_price ASC','price_desc'=>'p.base_price DESC','alpha'=>'p.name ASC'];
        $order = $order_map[$filter_sort] ?? 'p.created_at DESC';
        $sql2 = "SELECT DISTINCT p.name, p.sku, p.moq, p.base_price AS price, p.status, p.images, c.name AS category_name, c.slug AS category_slug
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id $size_join2
                 WHERE " . implode(' AND ', $where2) . " ORDER BY $order";
        $stmt = $pdo->prepare($sql2);
        $stmt->execute($params2);
        $catalog_products = $stmt->fetchAll();
    } catch (\Exception $e) {}
}


$items_per_page = 8;
$total_items    = count($catalog_products);
$total_pages    = max(1,(int)ceil($total_items/$items_per_page));
$current_page   = max(1,min($total_pages,(int)($_GET['page']??1)));
$offset         = ($current_page-1)*$items_per_page;
$paged_products = array_slice($catalog_products,$offset,$items_per_page);

function catalog_url(array $overrides=[], array $remove=[]): string {
    $params = $_GET;
    foreach ($overrides as $k=>$v) { $params[$k]=$v; }
    foreach ($remove   as $k)       { unset($params[$k]); }
    unset($params['page']);
    $q = http_build_query($params);
    return '?'.($q!==''?$q:'');
}

$all_sizes = ['XS','S','M','L','XL','XXL'];
$page_meta = ['title'=>'Product Catalog | Kesara Enterprises','description'=>'Browse our extensive range of quality innerwear for wholesale orders.'];
require_once __DIR__."/layouts/head.php";
require_once __DIR__."/layouts/header.php";
?><main class="bg-gray-50 py-12 min-h-screen">
  <div class="max-w-8xl mx-auto px-6 md:px-12">
    <div class="mb-10">
      <h1 class="text-3xl font-bold text-gray-900 mb-2">Product Catalog</h1>
      <p class="text-gray-500">Premium wholesale innerwear for your business.</p>
    </div>
    <div id="filters-backdrop" class="hidden fixed inset-0 bg-black/40 z-40 lg:hidden"></div>
    <div class="grid lg:grid-cols-[280px_1fr] gap-8">

      <!-- SIDEBAR -->
      <aside id="catalog-filters" class="fixed inset-y-0 left-0 w-80 bg-white z-50 p-6 border-r border-gray-100 overflow-y-auto transform -translate-x-full transition-transform duration-300 ease-in-out lg:relative lg:w-auto lg:bg-transparent lg:z-0 lg:p-0 lg:border-none lg:translate-x-0 lg:transition-none lg:block shadow-2xl lg:shadow-none">
        <form id="filter-form" method="GET" action="">
          <input type="hidden" name="sort"   value="<?php echo htmlspecialchars($filter_sort); ?>">
          <input type="hidden" name="search" value="<?php echo htmlspecialchars($filter_search); ?>">
        <div class="bg-white lg:border lg:border-gray-100 lg:rounded-2xl lg:p-6 lg:shadow-sm lg:sticky lg:top-24">
          <div class="flex items-center justify-between mb-6">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider flex items-center gap-2">
              <i class="ti ti-adjustments-horizontal text-brand"></i>Filters
            </h2>
            <div class="flex items-center gap-3">
              <a href="?" class="text-xs text-brand font-semibold hover:underline">Reset</a>
              <button type="button" id="close-filters" class="lg:hidden p-1 text-gray-400 hover:text-brand transition-colors">
                <i class="ti ti-x text-xl"></i>
              </button>
            </div>
          </div>

          <!-- Category -->
          <div class="mb-8">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Category</label>
            <div class="flex flex-wrap gap-2">
              <button type="submit" name="category" value=""
                class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors <?php echo $filter_category===''?'bg-brand text-white':'bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand hover:text-brand'; ?>">All</button>
              <?php foreach ($all_categories as $cat): ?>
              <button type="submit" name="category" value="<?php echo htmlspecialchars($cat['slug']); ?>"
                class="px-3 py-1.5 rounded-full text-xs font-semibold transition-colors <?php echo $filter_category===$cat['slug']?'bg-brand text-white':'bg-gray-50 text-gray-600 border border-gray-100 hover:border-brand hover:text-brand'; ?>">
                <?php echo htmlspecialchars($cat['name']); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <hr class="border-gray-100 my-6">

          <!-- Size -->
          <div class="mb-8">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Size</label>
            <div class="grid grid-cols-3 gap-2">
              <?php foreach ($all_sizes as $sz): $sz_active=in_array($sz,$filter_sizes,true); ?>
              <label class="relative cursor-pointer">
                <input type="checkbox" name="sizes[]" value="<?php echo $sz; ?>"
                       <?php echo $sz_active?'checked':''; ?> onchange="this.form.submit()" class="sr-only peer">
                <span class="block py-2 rounded-lg text-xs font-semibold text-center border transition-all
                             <?php echo $sz_active?'bg-brand-light text-brand border-brand/30':'bg-gray-50 text-gray-600 border-gray-100 hover:border-brand hover:text-brand'; ?>
                             peer-checked:bg-brand-light peer-checked:text-brand peer-checked:border-brand/30">
                  <?php echo $sz; ?>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <hr class="border-gray-100 my-6">

          <!-- Stock -->
          <div class="mb-8">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Stock Status</label>
            <div class="space-y-3">
              <?php foreach (['in_stock'=>'In Stock','low_stock'=>'Low Stock','on_order'=>'On Order'] as $val=>$lbl): ?>
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="stock[]" value="<?php echo $val; ?>"
                       <?php echo in_array($val,$filter_stock,true)?'checked':''; ?> onchange="this.form.submit()"
                       class="rounded text-brand focus:ring-brand border-gray-300">
                <span class="text-sm text-gray-600"><?php echo $lbl; ?></span>
              </label>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- MOQ -->
          <div class="pt-4">
            <label class="block text-xs font-bold text-gray-400 uppercase mb-4 tracking-widest">Min. Order Qty (MOQ)</label>
            <div class="flex gap-2">
              <input type="number" name="moq" placeholder="Min. units" min="0"
                     value="<?php echo $filter_moq!==null?$filter_moq:''; ?>"
                     class="w-full px-4 py-2.5 bg-gray-50 border border-gray-100 rounded-lg text-sm outline-none focus:ring-1 focus:ring-brand">
              <button type="submit" class="px-3 py-2.5 bg-brand text-white rounded-lg text-xs font-bold hover:opacity-90 transition-opacity shrink-0">Go</button>
            </div>
          </div>
        </div>
        </form>
      </aside>

      <!-- MAIN -->
      <div class="flex flex-col gap-6 h-full">

        <!-- TOP BAR -->
        <form id="topbar-form" method="GET" action="">
          <input type="hidden" name="category" value="<?php echo htmlspecialchars($filter_category); ?>">
          <?php foreach ($filter_sizes as $sz): ?><input type="hidden" name="sizes[]" value="<?php echo htmlspecialchars($sz); ?>"><?php endforeach; ?>
          <?php foreach ($filter_stock as $st): ?><input type="hidden" name="stock[]" value="<?php echo htmlspecialchars($st); ?>"><?php endforeach; ?>
          <?php if ($filter_moq!==null): ?><input type="hidden" name="moq" value="<?php echo $filter_moq; ?>"><?php endif; ?>
          <div class="bg-white border border-gray-100 rounded-2xl p-4 flex flex-col md:flex-row items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center gap-3 w-full md:w-96">
              <button type="button" id="open-filters" class="lg:hidden px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm font-bold text-gray-600 flex items-center gap-2 hover:bg-gray-100 transition-all shrink-0">
                <i class="ti ti-adjustments-horizontal text-brand"></i>Filters
              </button>
              <div class="relative flex-1">
                <i class="ti ti-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" id="search-input" placeholder="Search products..."
                       value="<?php echo htmlspecialchars($filter_search); ?>"
                       class="w-full pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-100 rounded-xl text-sm outline-none focus:ring-1 focus:ring-brand">
              </div>
            </div>
            <div class="flex items-center gap-4 w-full md:w-auto">
              <span class="text-xs font-semibold text-gray-400 uppercase whitespace-nowrap">Sort by</span>
              <select name="sort" onchange="this.form.submit()"
                      class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-1 focus:ring-brand flex-1 md:flex-none appearance-none">
                <option value="newest"     <?php echo $filter_sort==='newest'?'selected':''; ?>>Newest Arrivals</option>
                <option value="price_asc"  <?php echo $filter_sort==='price_asc'?'selected':''; ?>>Price: Low to High</option>
                <option value="price_desc" <?php echo $filter_sort==='price_desc'?'selected':''; ?>>Price: High to Low</option>
                <option value="alpha"      <?php echo $filter_sort==='alpha'?'selected':''; ?>>Alphabetical</option>
              </select>
              <span class="text-xs text-gray-400 whitespace-nowrap"><?php echo $total_items; ?> items</span>
            </div>
          </div>
        </form>

        <!-- ACTIVE FILTERS -->
        <?php
        $has_active = $filter_search!==''||$filter_category!==''||!empty($filter_sizes)||!empty($filter_stock)||$filter_moq!==null;
        if ($has_active):
        ?>
        <div class="flex items-center gap-3 flex-wrap">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-widest">Active:</span>
          <?php if ($filter_search!==''): ?>
          <a href="<?php echo catalog_url([],['search']); ?>" class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm hover:opacity-80 transition-opacity">
            Search: <?php echo htmlspecialchars($filter_search); ?> <i class="ti ti-x"></i>
          </a>
          <?php endif; ?>
          <?php if ($filter_category!==''): ?>
          <?php $cat_label=$filter_category; foreach ($all_categories as $c){if($c['slug']===$filter_category)$cat_label=$c['name'];} ?>
          <a href="<?php echo catalog_url(['category'=>'']); ?>" class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm hover:opacity-80 transition-opacity">
            Category: <?php echo htmlspecialchars($cat_label); ?> <i class="ti ti-x"></i>
          </a>
          <?php endif; ?>
          <?php if (!empty($filter_sizes)): ?>
          <a href="<?php echo catalog_url([],['sizes']); ?>" class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm hover:opacity-80 transition-opacity">
            Sizes: <?php echo implode(', ',$filter_sizes); ?> <i class="ti ti-x"></i>
          </a>
          <?php endif; ?>
          <?php if (!empty($filter_stock)): ?>
          <?php $slbl=['in_stock'=>'In Stock','low_stock'=>'Low Stock','on_order'=>'On Order']; ?>
          <a href="<?php echo catalog_url([],['stock']); ?>" class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm hover:opacity-80 transition-opacity">
            Stock: <?php echo implode(', ',array_map(fn($k)=>$slbl[$k]??$k,$filter_stock)); ?> <i class="ti ti-x"></i>
          </a>
          <?php endif; ?>
          <?php if ($filter_moq!==null): ?>
          <a href="<?php echo catalog_url([],['moq']); ?>" class="px-3 py-1 bg-brand-light text-brand text-[11px] font-bold rounded-full flex items-center gap-2 border border-brand/10 shadow-sm hover:opacity-80 transition-opacity">
            MOQ &ge; <?php echo $filter_moq; ?> pcs <i class="ti ti-x"></i>
          </a>
          <?php endif; ?>
          <a href="?" class="text-xs text-gray-400 hover:text-brand font-semibold underline transition-colors">Clear all</a>
        </div>
        <?php endif; ?>

        <!-- PRODUCT GRID -->
        <?php if (empty($paged_products)): ?>
        <div class="bg-white border border-gray-100 rounded-2xl p-16 flex flex-col items-center gap-4 text-center shadow-sm flex-1 justify-center">
          <i class="ti ti-mood-empty text-5xl text-gray-200"></i>
          <p class="text-gray-400 font-medium">No products match your filters.</p>
          <a href="?" class="px-5 py-2.5 bg-brand text-white text-sm font-bold rounded-xl hover:opacity-90 transition-opacity">Clear Filters</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
          <?php foreach ($paged_products as $p): ?>
          <a href="/product?sku=<?php echo htmlspecialchars($p['sku']); ?>" class="bg-white border border-gray-100 rounded-2xl overflow-hidden group hover:shadow-xl transition-all duration-300 hover:-translate-y-1">
            <div class="bg-gray-50 h-48 flex items-center justify-center border-b border-gray-50 relative overflow-hidden">
              <?php $prod_images=json_decode($p['images']??'[]',true); if(!empty($prod_images)&&!empty($prod_images[0])): ?>
              <img src="<?php echo htmlspecialchars($prod_images[0]); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">
              <?php else: ?>
              <i class="ti ti-shirt text-6xl text-gray-200 group-hover:scale-110 transition-transform duration-500"></i>
              <?php endif; ?>
              <div class="absolute inset-0 bg-black/0 group-hover:bg-black/5 transition-colors duration-300"></div>
            </div>
            <div class="p-5">
              <div class="flex justify-between items-start mb-2">
                <h3 class="text-[15px] font-bold text-gray-900 group-hover:text-brand transition-colors"><?php echo htmlspecialchars($p['name']); ?></h3>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo strtolower($p['status'])==='in stock'?'bg-brand-light text-brand':'bg-amber-50 text-amber-600 border border-amber-100'; ?>">
                  <?php echo htmlspecialchars($p['status']); ?>
                </span>
              </div>
              <p class="text-[11px] text-gray-400 font-medium mb-4 tracking-wider uppercase"><?php echo htmlspecialchars($p['sku']); ?></p>
              <div class="flex items-center justify-between mt-auto">
                <div class="space-y-1">
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Wholesale Price</p>
                  <p class="text-sm font-bold text-gray-900">
                    <?php if ($can_see_prices): ?>
                      LKR <?php echo is_numeric($p['price'])?number_format($p['price'],2):htmlspecialchars($p['price']); ?>
                    <?php else: ?>
                      <span style="filter: blur(4px); user-select: none; pointer-events: none;" class="select-none pointer-events-none" title="Log in to view prices">LKR <?php echo is_numeric($p['price'])?number_format($p['price'],2):htmlspecialchars($p['price']); ?></span>
                    <?php endif; ?>
                  </p>
                </div>
                <div class="text-right">
                  <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">Min Order</p>
                  <p class="text-[12px] font-bold text-gray-600"><?php echo htmlspecialchars($p['moq']); ?> pcs</p>
                </div>
              </div>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- PAGINATION -->
        <?php if ($total_pages>1): ?>
        <div class="flex justify-center gap-2 py-10">
          <?php
          $purl=function(int $pg):string{ $params=$_GET; $params['page']=$pg; return '?'.http_build_query($params); };
          $prev_disabled=$current_page<=1;
          ?>
          <a href="<?php echo $prev_disabled?'#':$purl($current_page-1); ?>"
             class="w-10 h-10 rounded-xl flex items-center justify-center border border-gray-100 bg-white shadow-sm <?php echo $prev_disabled?'opacity-40 cursor-not-allowed pointer-events-none':'hover:bg-brand hover:text-white transition-all'; ?>"
             <?php echo $prev_disabled?'aria-disabled="true"':''; ?>>
            <i class="ti ti-chevron-left"></i>
          </a>
          <?php
          $window=2; $ps=max(1,$current_page-$window); $pe=min($total_pages,$current_page+$window);
          if ($ps>1): ?>
            <a href="<?php echo $purl(1); ?>" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-gray-100 hover:border-brand hover:text-brand font-bold transition-all shadow-sm">1</a>
            <?php if ($ps>2): ?><span class="w-10 h-10 flex items-center justify-center text-gray-400 font-bold">&hellip;</span><?php endif; ?>
          <?php endif; ?>
          <?php for ($pg=$ps;$pg<=$pe;$pg++): ?>
            <?php if ($pg===$current_page): ?>
              <span class="w-10 h-10 rounded-xl flex items-center justify-center bg-brand text-white font-bold shadow-md"><?php echo $pg; ?></span>
            <?php else: ?>
              <a href="<?php echo $purl($pg); ?>" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-gray-100 hover:border-brand hover:text-brand font-bold transition-all shadow-sm"><?php echo $pg; ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($pe<$total_pages): ?>
            <?php if ($pe<$total_pages-1): ?><span class="w-10 h-10 flex items-center justify-center text-gray-400 font-bold">&hellip;</span><?php endif; ?>
            <a href="<?php echo $purl($total_pages); ?>" class="w-10 h-10 rounded-xl flex items-center justify-center bg-white border border-gray-100 hover:border-brand hover:text-brand font-bold transition-all shadow-sm"><?php echo $total_pages; ?></a>
          <?php endif; ?>
          <?php $next_disabled=$current_page>=$total_pages; ?>
          <a href="<?php echo $next_disabled?'#':$purl($current_page+1); ?>"
             class="w-10 h-10 rounded-xl flex items-center justify-center border border-gray-100 bg-white shadow-sm <?php echo $next_disabled?'opacity-40 cursor-not-allowed pointer-events-none':'hover:bg-brand hover:text-white transition-all'; ?>"
             <?php echo $next_disabled?'aria-disabled="true"':''; ?>>
            <i class="ti ti-chevron-right"></i>
          </a>
        </div>
        <?php endif; ?>

      </div>
    </div>
  </div>
</main>

<script>
  const openFiltersBtn=document.getElementById('open-filters');
  const closeFiltersBtn=document.getElementById('close-filters');
  const filtersSidebar=document.getElementById('catalog-filters');
  const filtersBackdrop=document.getElementById('filters-backdrop');
  function toggleFilters(){filtersSidebar.classList.toggle('-translate-x-full');filtersBackdrop.classList.toggle('hidden');}
  if(openFiltersBtn) openFiltersBtn.addEventListener('click',toggleFilters);
  if(closeFiltersBtn) closeFiltersBtn.addEventListener('click',toggleFilters);
  if(filtersBackdrop) filtersBackdrop.addEventListener('click',toggleFilters);
  const searchInput=document.getElementById('search-input');
  if(searchInput){searchInput.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();document.getElementById('topbar-form').submit();}});}
  const sortSel=document.querySelector('#topbar-form select[name="sort"]');
  const sortHidden=document.querySelector('#filter-form input[name="sort"]');
  if(sortSel&&sortHidden){sortSel.addEventListener('change',function(){sortHidden.value=sortSel.value;});}
</script>

<?php require_once __DIR__."/layouts/footer.php"; ?>