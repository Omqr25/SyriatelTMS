
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Syriatel TMS</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 4px 15px #0001;
        }

        h2 { text-align: center; }
        label { display: block; margin-top: 16px; }
        input {
            box-sizing: border-box;
            width: 100%;
            padding: 11px;
            margin-top: 6px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            width: 100%;
            margin-top: 22px;
            padding: 12px;
            border: 0;
            border-radius: 6px;
            background: #1268a8;
            color: white;
            cursor: pointer;
        }

        #message { margin-top: 16px; }
    </style>
</head>
<body>
<div class="card">
    <h2>Reset Your Password</h2>

    <form id="resetForm">
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            required
        >

        <label for="password">New Password</label>
        <input
            type="password"
            id="password"
            name="password"
            minlength="8"
            required
        >

        <label for="password_confirmation">Confirm Password</label>
        <input
            type="password"
            id="password_confirmation"
            name="password_confirmation"
            minlength="8"
            required
        >

        <button type="submit">Reset Password</button>
        <p id="message" role="status"></p>
    </form>
</div>

<script>
    const params = new URLSearchParams(window.location.search);

    document.getElementById('email').value =
        params.get('email') || '';

    const token = params.get('token');

    document.getElementById('resetForm').addEventListener(
        'submit',
        async function (event) {
            event.preventDefault();

            const message = document.getElementById('message');
            const password = document.getElementById('password').value;
            const confirmation =
                document.getElementById('password_confirmation').value;

            if (!token) {
                message.textContent = 'Reset token is missing from the link.';
                return;
            }

            if (password !== confirmation) {
                message.textContent = 'Passwords do not match.';
                return;
            }

            message.textContent = 'Resetting password...';

            try {
                const response = await fetch(
                    '/api/v1/auth/reset-password',
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            email: document.getElementById('email').value,
                            token: token,
                            password: password,
                            password_confirmation: confirmation
                        })
                    }
                );

                const result = await response.json();

                if (response.ok) {
                    message.textContent =
                        result.message || 'Password reset successfully.';
                    document.getElementById('resetForm').reset();
                } else {
                    message.textContent =
                        result.message || 'Unable to reset password.';
                }
            } catch (error) {
                message.textContent =
                    'Could not connect to the server. Please try again.';
            }
        }
    );
</script>
</body>
</html>
