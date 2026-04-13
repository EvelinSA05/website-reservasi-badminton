<?php

require_once 'db.php';

const REMEMBER_TOKENS_FILE = __DIR__ . '/../data/remember_tokens.json';
const REMEMBER_COOKIE_NAME = 'remember_token';

function read_json_file($path) {
    if (!file_exists($path)) {
        return [];
    }

    $content = file_get_contents($path);
    if ($content === false || $content === '') {
        return [];
    }

    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

function write_json_file($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json, LOCK_EX) !== false;
}

function normalize_role($role) {
    $allowed = ['pelanggan', 'admin', 'kasir', 'owner'];
    $role = strtolower(trim((string) $role));
    return in_array($role, $allowed, true) ? $role : 'pelanggan';
}

function find_user_by_email($email) {
    $email = strtolower(trim($email));
    $stmt = db()->prepare('SELECT id_pengguna, nama_lengkap, email, password, no_telepon, role FROM pengguna WHERE LOWER(email) = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    return $stmt->fetch() ?: null;
}

function find_user_by_id($id) {
    $stmt = db()->prepare('SELECT id_pengguna, nama_lengkap, email, password, no_telepon, role FROM pengguna WHERE id_pengguna = :id LIMIT 1');
    $stmt->execute(['id' => (string) $id]);
    return $stmt->fetch() ?: null;
}

function role_prefix($role) {
    $map = [
        'pelanggan' => 'PL',
        'admin' => 'AD',
        'kasir' => 'KS',
        'owner' => 'OW',
    ];

    return $map[$role] ?? 'PL';
}

function generate_pengguna_id($role) {
    $role = normalize_role($role);
    $prefix = role_prefix($role);

    $stmt = db()->prepare('SELECT id_pengguna FROM pengguna WHERE id_pengguna LIKE :prefix');
    $stmt->execute(['prefix' => $prefix . '%']);
    $rows = $stmt->fetchAll();

    $max = 0;
    foreach ($rows as $row) {
        $id = (string) ($row['id_pengguna'] ?? '');
        if (strpos($id, $prefix) !== 0) {
            continue;
        }

        $suffix = substr($id, strlen($prefix));
        if (ctype_digit($suffix)) {
            $num = (int) $suffix;
            if ($num > $max) {
                $max = $num;
            }
        }
    }

    $next = $max + 1;
    return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
}

function needs_rehash_or_plain($storedPassword) {
    $info = password_get_info($storedPassword);
    if (($info['algo'] ?? 0) === 0) {
        return true;
    }

    return password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
}

function verify_password_with_legacy($plain, $storedPassword) {
    $info = password_get_info($storedPassword);

    if (($info['algo'] ?? 0) === 0) {
        return hash_equals((string) $storedPassword, (string) $plain);
    }

    return password_verify($plain, $storedPassword);
}

function register_user($name, $email, $password, $phone, $role) {
    $name = trim($name);
    $email = strtolower(trim($email));
    $phone = trim($phone);
    $role = normalize_role($role);

    if ($name === '' || $email === '' || $password === '' || $phone === '') {
        return ['ok' => false, 'message' => 'Semua kolom wajib diisi.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Format email tidak valid.'];
    }

    if (!preg_match('/^[0-9+\-\s]{8,15}$/', $phone)) {
        return ['ok' => false, 'message' => 'No telepon tidak valid.'];
    }

    if (strlen($password) < 8) {
        return ['ok' => false, 'message' => 'Password minimal 8 karakter.'];
    }

    if (find_user_by_email($email)) {
        return ['ok' => false, 'message' => 'Email sudah terdaftar.'];
    }

    $id = generate_pengguna_id($role);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = db()->prepare('INSERT INTO pengguna (id_pengguna, nama_lengkap, email, password, no_telepon, role) VALUES (:id, :nama, :email, :password, :telepon, :role)');
    $ok = $stmt->execute([
        'id' => $id,
        'nama' => $name,
        'email' => $email,
        'password' => $hash,
        'telepon' => $phone,
        'role' => $role,
    ]);

    if (!$ok) {
        return ['ok' => false, 'message' => 'Gagal menyimpan data user.'];
    }

    return ['ok' => true, 'message' => 'Register berhasil. Silakan login.'];
}

function attempt_login($email, $password) {
    $email = strtolower(trim($email));
    $user = find_user_by_email($email);

    if (!$user || !isset($user['password'])) {
        return ['ok' => false, 'message' => 'Email atau password salah.'];
    }

    if (!verify_password_with_legacy($password, $user['password'])) {
        return ['ok' => false, 'message' => 'Email atau password salah.'];
    }

    if (needs_rehash_or_plain($user['password'])) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = db()->prepare('UPDATE pengguna SET password = :password WHERE id_pengguna = :id');
        $stmt->execute([
            'password' => $newHash,
            'id' => $user['id_pengguna']
        ]);
        $user['password'] = $newHash;
    }

    return ['ok' => true, 'user' => $user];
}

function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function pull_flash() {
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function login_user_session($user) {
    $_SESSION['auth'] = [
        'is_logged_in' => true,
        'user_id' => $user['id_pengguna'],
        'name' => $user['nama_lengkap'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
}

function current_user() {
    if (isset($_SESSION['auth']['is_logged_in']) && $_SESSION['auth']['is_logged_in'] === true) {
        return $_SESSION['auth'];
    }
    return null;
}

function set_cookie_value($name, $value, $expires) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    setcookie($name, $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function get_all_remember_tokens() {
    return read_json_file(REMEMBER_TOKENS_FILE);
}

function save_all_remember_tokens($tokens) {
    return write_json_file(REMEMBER_TOKENS_FILE, array_values($tokens));
}

function remove_remember_token_by_hash($tokenHash) {
    if ($tokenHash === '') {
        return;
    }

    $tokens = get_all_remember_tokens();
    $filtered = [];
    foreach ($tokens as $item) {
        if (!isset($item['token_hash']) || !hash_equals($item['token_hash'], $tokenHash)) {
            $filtered[] = $item;
        }
    }
    save_all_remember_tokens($filtered);
}

function issue_remember_me($userId, $days = 30) {
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = time() + (86400 * $days);

    $tokens = get_all_remember_tokens();
    $tokens[] = [
        'user_id' => $userId,
        'token_hash' => $tokenHash,
        'expires_at' => $expiresAt,
        'created_at' => time()
    ];

    save_all_remember_tokens($tokens);
    set_cookie_value(REMEMBER_COOKIE_NAME, $rawToken, $expiresAt);
}

function clear_expired_remember_tokens() {
    $now = time();
    $tokens = get_all_remember_tokens();
    $filtered = [];

    foreach ($tokens as $token) {
        if (isset($token['expires_at']) && (int) $token['expires_at'] > $now) {
            $filtered[] = $token;
        }
    }

    if (count($filtered) !== count($tokens)) {
        save_all_remember_tokens($filtered);
    }
}

function try_login_from_remember_cookie() {
    if (current_user()) {
        return;
    }

    if (empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        return;
    }

    clear_expired_remember_tokens();

    $rawToken = $_COOKIE[REMEMBER_COOKIE_NAME];
    $tokenHash = hash('sha256', $rawToken);

    $tokens = get_all_remember_tokens();
    foreach ($tokens as $token) {
        if (!isset($token['token_hash']) || !hash_equals($token['token_hash'], $tokenHash)) {
            continue;
        }

        if (!isset($token['expires_at']) || (int) $token['expires_at'] <= time()) {
            break;
        }

        $userId = $token['user_id'] ?? '';
        $user = find_user_by_id($userId);
        if (!$user) {
            break;
        }

        login_user_session($user);
        remove_remember_token_by_hash($tokenHash);
        issue_remember_me($userId, 30);
        return;
    }

    remove_remember_token_by_hash($tokenHash);
    set_cookie_value(REMEMBER_COOKIE_NAME, '', time() - 3600);
}

function logout_user() {
    if (!empty($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $tokenHash = hash('sha256', $_COOKIE[REMEMBER_COOKIE_NAME]);
        remove_remember_token_by_hash($tokenHash);
    }

    set_cookie_value(REMEMBER_COOKIE_NAME, '', time() - 3600);

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

?>
