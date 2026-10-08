<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập</title>
</head>
<body>
    <main>
        <h1>Đăng nhập</h1>
        <form id="login-form">
            <p><label>Email <input name="email" type="email" autocomplete="username" required></label></p>
            <p><label>Mật khẩu <input name="password" type="password" autocomplete="current-password" required></label></p>
            <button type="submit">Đăng nhập</button>
            <p id="error" role="alert" hidden></p>
        </form>
    </main>
    <script>
        const form = document.getElementById('login-form');
        const button = form.querySelector('button');
        const error = document.getElementById('error');
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (button.disabled) return;
            button.disabled = true;
            button.textContent = 'Đang đăng nhập…';
            error.hidden = true;
            try {
                const response = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(Object.fromEntries(new FormData(form))),
                });
                const data = await response.json();
                if (!response.ok || !data.access_token) throw new Error('Email hoặc mật khẩu không đúng. Vui lòng thử lại.');
                sessionStorage.setItem('auth_token', data.access_token);
                window.location.replace(@json(route('account')));
            } catch (failure) {
                error.textContent = failure instanceof TypeError ? 'Không thể kết nối. Vui lòng thử lại.' : failure.message;
                error.hidden = false;
                button.disabled = false;
                button.textContent = 'Đăng nhập';
            }
        });
    </script>
</body>
</html>
