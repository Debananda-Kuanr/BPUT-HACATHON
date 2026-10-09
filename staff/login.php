<?php
/**
 * MEDGem — Staff Login
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
setSecurityHeaders();

// Handle logout
if (isset($_GET['logout'])) {
    logout();
}

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header('Location: /BPUT/' . strtolower($role) . '/dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $result = attemptLogin($email, $password);
        if ($result['success']) {
            $role = strtolower($result['role']);
            header("Location: /BPUT/$role/dashboard.php");
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — MEDGem</title>
    <link rel="stylesheet" href="/BPUT/assets/css/medgem.css">
    <link rel="stylesheet" href="/BPUT/assets/css/patient.css">
    <style>
        .login-roles {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-4);
            margin-bottom: var(--space-8);
        }
        .role-card {
            padding: var(--space-5);
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-2xl);
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-base);
        }
        .role-card:hover { border-color: var(--primary-300); }
        .role-card.selected { border-color: var(--primary-500); background: var(--primary-50); }
        .role-card__icon {
            width: 48px; height: 48px; border-radius: var(--radius-xl);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto var(--space-3);
        }
        .role-card__icon--nurse { background: #dcfce7; color: #166534; }
        .role-card__icon--doctor { background: var(--info-100); color: var(--info-600); }
        .role-card__title { font-weight: 700; font-size: var(--text-base); color: var(--neutral-900); }
        .role-card__desc { font-size: var(--text-xs); color: var(--neutral-500); margin-top: 4px; }
        .demo-creds {
            background: var(--neutral-50); border-radius: var(--radius-xl);
            padding: var(--space-4); margin-top: var(--space-6);
            font-size: var(--text-sm); color: var(--neutral-500);
        }
        .demo-creds code {
            background: var(--neutral-200); padding: 2px 6px;
            border-radius: var(--radius-sm); font-family: var(--font-mono);
            font-size: var(--text-xs); color: var(--neutral-700);
        }
    </style>
</head>
<body>

<div class="prototype-banner">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-2px;margin-right:4px"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    Synthetic Data &middot; Educational Prototype
</div>

<nav class="navbar">
    <div class="navbar__inner">
        <a href="/BPUT/" class="navbar__logo">
            <span class="navbar__logo-icon" style="background:linear-gradient(135deg, #1c58ef 0%, #1444c4 100%);border-radius:10px">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            </span>
            <span style="font-weight:800;font-family:var(--font-heading, 'Raleway', sans-serif);color:#0f172a">MEDGem<span style="color:#1c58ef">Care</span></span>
        </a>
        <a href="/BPUT/" class="btn btn--sm btn--secondary" style="font-size:12px">&larr; Back to Portal</a>
    </div>
</nav>

<main class="patient-flow pt-header" style="display:flex;align-items:center;justify-content:center">
    <div class="container container--sm">
        <div class="patient-card fade-up">
            <div class="patient-card__header" style="text-align:center">
                <h1 class="heading-3">Staff Login</h1>
                <p class="text-muted mt-2">Access the MEDGem clinical dashboard</p>
            </div>

            <!-- Role Selection -->
            <div class="login-roles">
                <div class="role-card selected" onclick="selectRole('nurse')" id="roleNurse">
                    <div class="role-card__icon role-card__icon--nurse">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                    </div>
                    <div class="role-card__title">Nurse</div>
                    <div class="role-card__desc">Case verification & review</div>
                </div>
                <div class="role-card" onclick="selectRole('doctor')" id="roleDoctor">
                    <div class="role-card__icon role-card__icon--doctor">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/></svg>
                    </div>
                    <div class="role-card__title">Doctor</div>
                    <div class="role-card__desc">Professional case review</div>
                </div>
            </div>

            <?php if ($error): ?>
            <div class="form-errors">
                <p><?php echo htmlspecialchars($error); ?></p>
            </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <?php echo csrfField(); ?>
                <div class="form-group">
                    <label class="form-label" for="email">Staff Email</label>
                    <input type="email" id="email" name="email" class="form-input" placeholder="nurse@medgem.demo" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Enter password" required>
                </div>
                <button type="submit" class="btn btn--primary btn--lg btn--full">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                    Login
                </button>
            </form>

            <div class="demo-creds">
                <strong style="color:var(--neutral-700)">Demo Credentials</strong><br><br>
                <strong>Nurse:</strong> <code>nurse@medgem.demo</code><br>
                <strong>Doctor:</strong> <code>doctor@medgem.demo</code><br>
                <strong>Password:</strong> <code>password</code>
            </div>
        </div>
    </div>
</main>

<script>
function selectRole(role) {
    document.querySelectorAll('.role-card').forEach(c => c.classList.remove('selected'));
    document.getElementById('role' + role.charAt(0).toUpperCase() + role.slice(1)).classList.add('selected');
    
    const emailInput = document.getElementById('email');
    emailInput.value = role + '@medgem.demo';
    document.getElementById('password').value = 'password';
}
</script>
</body>
</html>
