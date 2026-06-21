<?php
// Handle AJAX POST requests to verify credentials
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Required fields missing. Please provide credentials.']);
        exit;
    }
    
    $admin = null;
    if (isset($pdo) && $pdo !== null) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
        } catch (\Exception $e) {
            // Fallback
        }
    }
    
    // Auth logic
    if ($admin) {
        if (password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            echo json_encode(['success' => true]);
            exit;
        }
    } else {
        // Offline / demo fallback mode
        $offline_admins = [
            'admin@kesara.lk' => ['id' => 1, 'username' => 'admin', 'password' => 'admin123', 'role' => 'admin'],
            'finance@kesara.lk' => ['id' => 2, 'username' => 'finance', 'password' => 'finance123', 'role' => 'finance_manager'],
            'supplier@kesara.lk' => ['id' => 3, 'username' => 'supplier', 'password' => 'supplier123', 'role' => 'supplier_manager'],
            'delivery@kesara.lk' => ['id' => 4, 'username' => 'delivery', 'password' => 'delivery123', 'role' => 'delivery_manager']
        ];
        
        if (isset($offline_admins[$email]) && $password === $offline_admins[$email]['password']) {
            $_SESSION['admin_id'] = $offline_admins[$email]['id'];
            $_SESSION['admin_username'] = $offline_admins[$email]['username'];
            $_SESSION['admin_role'] = $offline_admins[$email]['role'];
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid email or password.']);
    exit;
}
?>
<main class="min-h-screen bg-gray-900 flex items-center justify-center p-6 relative overflow-hidden w-full">
  <!-- Abstract Background Pattern -->
  <div class="absolute inset-0 opacity-10">
    <div class="absolute top-0 -left-1/4 w-1/2 h-1/2 bg-brand rounded-full blur-[120px]"></div>
    <div class="absolute bottom-0 -right-1/4 w-1/2 h-1/2 bg-brand rounded-full blur-[120px]"></div>
  </div>

  <div class="w-full max-w-md relative z-10">

    <!-- Branding -->
    <div class="flex flex-col items-center mb-10">
      <div class="w-16 h-16 bg-brand rounded-2xl flex items-center justify-center mb-4 shadow-xl shadow-brand/20 ring-4 ring-brand/10">
        <i class="ti ti-shield-lock text-3xl text-brand-light"></i>
      </div>
      <h2 class="text-xs font-bold text-brand-light/40 uppercase tracking-[0.3em]">Kesara Enterprises</h2>
    </div>

    <!-- Login Card -->
    <div class="bg-white rounded-[2.5rem] p-10 md:p-12 shadow-2xl relative overflow-hidden">

      <div class="relative z-10">
        <div class="mb-8">
          <h1 class="text-2xl font-bold text-gray-900 mb-1">Admin Access</h1>
          <p class="text-xs font-medium text-gray-400">Restricted to authorised staff only</p>
        </div>

        <!-- Environment Badge -->
        <div class="flex items-center gap-3 px-4 py-2 bg-gray-50 border border-gray-100 rounded-xl mb-8">
          <i class="ti ti-world text-gray-400"></i>
          <span class="text-[11px] font-bold text-gray-500 tracking-wider">ADMIN.KESARA.LK</span>
          <i class="ti ti-lock text-green-500 ml-auto"></i>
        </div>

        <div id="state-normal" class="space-y-6">
          <div class="space-y-2">
            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Admin Email</label>
            <div class="relative group">
              <i class="ti ti-mail absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
              <input type="email" id="email-input" placeholder="admin@kesara.lk" class="w-full pl-12 pr-6 py-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
            </div>
          </div>

          <div class="space-y-2">
            <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest ml-1">Password</label>
            <div class="relative group">
              <i class="ti ti-key absolute left-5 top-1/2 -translate-y-1/2 text-gray-400 group-focus-within:text-brand transition-colors"></i>
              <input type="password" id="pw-input" placeholder="••••••••" class="w-full pl-12 pr-12 py-4 bg-gray-50 border border-gray-100 rounded-2xl text-sm font-medium outline-none focus:ring-2 focus:ring-brand/10 focus:border-brand transition-all">
              <button onclick="togglePw()" class="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-brand transition-colors">
                <i id="eye-icon" class="ti ti-eye text-lg"></i>
              </button>
            </div>
          </div>

          <div id="warn-area"></div>

          <button onclick="tryLogin()" class="w-full bg-brand text-brand-light font-bold py-4 rounded-2xl hover:bg-brand-dark transition-all transform hover:-translate-y-px shadow-lg shadow-brand/20 active:scale-95 flex items-center justify-center gap-2 mt-4">
            <i class="ti ti-login text-xl"></i>
            Sign In to Control Panel
          </button>

          <div class="relative py-4">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-50"></div>
            </div>
            <div class="relative flex justify-center"><span class="bg-white px-4 text-[10px] font-bold text-gray-300 uppercase tracking-widest">Security Policy</span></div>
          </div>

          <ul class="space-y-3">
            <li class="flex items-center gap-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
              <i class="ti ti-shield-check text-green-500 text-lg"></i>
              Session Timeout: 60 min
            </li>
            <li class="flex items-center gap-3 text-[10px] font-bold text-gray-400 uppercase tracking-widest">
              <i class="ti ti-shield-check text-green-500 text-lg"></i>
              Lockout: 5 failed attempts
            </li>
          </ul>
        </div>

        <div id="state-locked" class="hidden text-center">
          <div class="w-20 h-20 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="ti ti-lock text-4xl text-red-500"></i>
          </div>
          <h2 class="text-xl font-bold text-gray-900 mb-2">Access Locked</h2>
          <p class="text-sm text-gray-500 leading-relaxed mb-8">
            Too many failed attempts. For your security, access has been restricted.
            Try again in <span id="countdown" class="font-bold text-red-600">05:00</span>
          </p>
          <button onclick="resetDemo()" class="w-full py-4 rounded-2xl text-xs font-bold text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-all border border-gray-100 border-dashed">
            Reset for Demo
          </button>
        </div>
      </div>

      <!-- Background Decoration -->
      <div class="absolute -bottom-10 -right-10 opacity-[0.03]">
        <i class="ti ti-shield-lock text-[200px]"></i>
      </div>
    </div>

    <p class="text-center mt-10 text-[11px] font-medium text-gray-500 tracking-wide">
      Not your device? <a href="#" class="text-brand font-bold hover:underline">Use Private Browsing</a>
    </p>
  </div>
</main>

<script>
  let attempts = 0;
  let locked = false;
  let countdownTimer = null;

  function tryLogin() {
    if (locked) return;
    const email = document.getElementById('email-input').value.trim();
    const pw = document.getElementById('pw-input').value;
    const warnArea = document.getElementById('warn-area');

    if (!email || !pw) {
      warnArea.innerHTML = `
      <div class="flex items-start gap-3 p-4 bg-amber-50 border border-amber-100 rounded-2xl text-amber-600 animate-shake">
        <i class="ti ti-alert-triangle text-xl shrink-0 mt-0.5"></i>
        <p class="text-xs font-bold leading-relaxed">Required fields missing. Please provide credentials.</p>
      </div>
    `;
      return;
    }

    // Prepare URL-encoded form data
    const formData = new URLSearchParams();
    formData.append('email', email);
    formData.append('password', pw);

    // Call dynamic backend
    fetch(window.location.href, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        warnArea.innerHTML = `
          <div class="flex items-center gap-3 p-4 bg-green-50 border border-green-100 rounded-2xl text-green-600">
            <i class="ti ti-circle-check text-xl shrink-0"></i>
            <p class="text-xs font-bold">Login successful! Redirecting to dashboard...</p>
          </div>
        `;
        setTimeout(() => window.location.href = '/admin-dashboard', 1200);
      } else {
        attempts++;
        const remaining = 5 - attempts;
        
        if (attempts >= 5) {
          locked = true;
          document.getElementById('state-normal').classList.add('hidden');
          document.getElementById('state-locked').classList.remove('hidden');
          startCountdown(300);
          return;
        }
        
        warnArea.innerHTML = `
          <div class="flex items-start gap-3 p-4 bg-red-50 border border-red-100 rounded-2xl text-red-600 animate-shake">
            <i class="ti ti-lock text-xl shrink-0 mt-0.5"></i>
            <div class="space-y-1">
              <p class="text-xs font-bold">Invalid Credentials</p>
              <p class="text-[10px] font-medium opacity-80">${data.message || 'Invalid email or password.'} ${remaining} attempts remaining.</p>
            </div>
          </div>
        `;
      }
    })
    .catch(err => {
      warnArea.innerHTML = `
        <div class="flex items-start gap-3 p-4 bg-red-50 border border-red-100 rounded-2xl text-red-600">
          <i class="ti ti-alert-triangle text-xl shrink-0 mt-0.5"></i>
          <p class="text-xs font-bold leading-relaxed">Connection error. Please try again later.</p>
        </div>
      `;
    });
  }

  function startCountdown(seconds) {
    const el = document.getElementById('countdown');
    countdownTimer = setInterval(() => {
      seconds--;
      const m = Math.floor(seconds / 60);
      const s = seconds % 60;
      el.textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
      if (seconds <= 0) {
        clearInterval(countdownTimer);
        resetDemo();
      }
    }, 1000);
  }

  function resetDemo() {
    if (countdownTimer) clearInterval(countdownTimer);
    attempts = 0;
    locked = false;
    document.getElementById('state-normal').classList.remove('hidden');
    document.getElementById('state-locked').classList.add('hidden');
    document.getElementById('warn-area').innerHTML = '';
    document.getElementById('email-input').value = '';
    document.getElementById('pw-input').value = '';
  }

  function togglePw() {
    const inp = document.getElementById('pw-input');
    const icon = document.getElementById('eye-icon');
    if (inp.type === 'password') {
      inp.type = 'text';
      icon.classList.remove('ti-eye');
      icon.classList.add('ti-eye-off');
    } else {
      inp.type = 'password';
      icon.classList.remove('ti-eye-off');
      icon.classList.add('ti-eye');
    }
  }
</script>

<style>
  @keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-4px); }
    75% { transform: translateX(4px); }
  }
  .animate-shake { animation: shake 0.2s ease-in-out 0s 2; }
</style>
