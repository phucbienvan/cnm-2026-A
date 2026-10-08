<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tài khoản</title>
</head>
<body>
    <p id="status" role="status">Đang kiểm tra đăng nhập…</p>
    <main id="account" hidden>
        <h1>Tài khoản</h1>
        <p id="user-name"></p>
        <button id="logout" type="button">Đăng xuất</button>
    </main>
    <p id="error" role="alert" hidden></p>
    <script>
        const account = document.getElementById('account');
        const status = document.getElementById('status');
        const error = document.getElementById('error');
        const logout = document.getElementById('logout');
        const loginUrl = @json(route('login'));
        let checking = false;

        function clearLogin() {
            account.hidden = true;
            document.getElementById('user-name').textContent = '';
            sessionStorage.removeItem('auth_token');
            window.location.replace(loginUrl);
        }

        async function checkLogin() {
            account.hidden = true;
            if (checking) return;
            const token = sessionStorage.getItem('auth_token');
            if (!token) return clearLogin();
            checking = true;
            status.hidden = false;
            error.hidden = true;
            try {
                const response = await fetch('/api/users', {
                    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` },
                    cache: 'no-store',
                });
                if (response.status === 401) return clearLogin();
                if (!response.ok) throw new Error();
                const user = await response.json();
                if (sessionStorage.getItem('auth_token') !== token) return clearLogin();
                document.getElementById('user-name').textContent = user.name;
                account.hidden = false;
            } catch {
                error.textContent = 'Không thể kiểm tra đăng nhập. Vui lòng tải lại trang.';
                error.hidden = false;
            } finally {
                checking = false;
                status.hidden = true;
            }
        }

        logout.addEventListener('click', async () => {
            if (logout.disabled) return;
            logout.disabled = true;
            logout.textContent = 'Đang đăng xuất…';
            error.hidden = true;
            try {
                const token = sessionStorage.getItem('auth_token');
                if (!token) return clearLogin();
                const response = await fetch('/api/logout', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': `Bearer ${token}` },
                });
                if (!response.ok && response.status !== 401) throw new Error();
                clearLogin();
            } catch {
                error.textContent = 'Đăng xuất thất bại. Vui lòng thử lại.';
                error.hidden = false;
                logout.disabled = false;
                logout.textContent = 'Đăng xuất';
            }
        });

        window.addEventListener('pagehide', () => {
            account.hidden = true;
            document.getElementById('user-name').textContent = '';
        });
        window.addEventListener('pageshow', checkLogin);
    </script>
</body>
</html>
