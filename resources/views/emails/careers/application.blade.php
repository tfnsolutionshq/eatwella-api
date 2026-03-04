<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Career Application</title>
    <style>
        @font-face { font-family: Inter; src: local("Inter"); }
        :root {
            --bg: #0f1220;
            --card: #121629;
            --muted: #98a2b3;
            --text: #e5e7eb;
            --primary: #6ee7b7;
            --accent: #a78bfa;
            --danger: #f43f5e;
            --success: #22c55e;
        }
        body { margin: 0; background: linear-gradient(180deg,#0b1020,#0f1220 40%,#111827); font-family: Inter, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif; color: var(--text); }
        .container { max-width: 720px; margin: 0 auto; padding: 32px 16px; }
        .card { background: radial-gradient(1200px 600px at 0% 0%, rgba(167,139,250,.15), transparent), radial-gradient(1200px 600px at 100% 0%, rgba(110,231,183,.1), transparent), var(--card); border: 1px solid rgba(255,255,255,.06); border-radius: 20px; overflow: hidden; box-shadow: 0 30px 80px rgba(0,0,0,.35); }
        .header { padding: 28px 28px 16px; display: flex; align-items: center; gap: 14px; border-bottom: 1px solid rgba(255,255,255,.06); background: linear-gradient(180deg, rgba(255,255,255,.02), transparent); }
        .logo { width: 40px; height: 40px; border-radius: 12px; background: linear-gradient(135deg, var(--accent), var(--primary)); box-shadow: 0 10px 30px rgba(167,139,250,.35); }
        .title { font-size: 18px; letter-spacing: .2px; }
        .subtitle { font-size: 13px; color: var(--muted); }
        .content { padding: 28px; display: grid; gap: 22px; }
        .pill { display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: 999px; background: rgba(110,231,183,.08); color: var(--primary); border: 1px solid rgba(110,231,183,.18); font-size: 12px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .item { background: rgba(255,255,255,.02); border: 1px solid rgba(255,255,255,.06); border-radius: 14px; padding: 16px; }
        .label { color: var(--muted); font-size: 12px; margin-bottom: 6px; }
        .value { font-size: 14px; }
        .actions { display: flex; gap: 12px; padding: 16px 28px 28px; }
        .btn { display: inline-block; text-decoration: none; padding: 12px 16px; border-radius: 12px; font-weight: 600; font-size: 13px; border: 1px solid rgba(255,255,255,.08); }
        .btn-primary { background: linear-gradient(135deg, var(--accent), var(--primary)); color: #0a0f1c; box-shadow: 0 12px 24px rgba(167,139,250,.35); }
        .btn-secondary { background: rgba(255,255,255,.04); color: var(--text); }
        .footer { padding: 20px 28px 28px; color: var(--muted); font-size: 12px; }
        .divider { height: 1px; background: rgba(255,255,255,.06); margin: 0 28px; }
        @media (max-width: 640px) { .grid { grid-template-columns: 1fr; } .actions { flex-direction: column; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="logo"></div>
                <div>
                    <div class="title">New Career Application</div>
                    <div class="subtitle">A candidate submitted an application</div>
                </div>
            </div>
            <div class="content">
                <span class="pill">Role: {{ $payload['role'] }}</span>
                <div class="grid">
                    <div class="item">
                        <div class="label">Full Name</div>
                        <div class="value">{{ $payload['full_name'] }}</div>
                    </div>
                    <div class="item">
                        <div class="label">Email</div>
                        <div class="value">{{ $payload['email'] }}</div>
                    </div>
                    <div class="item">
                        <div class="label">Phone</div>
                        <div class="value">{{ $payload['phone'] }}</div>
                    </div>
                    <div class="item">
                        <div class="label">Application ID</div>
                        <div class="value">{{ $payload['application_id'] }}</div>
                    </div>
                </div>
            </div>
            <div class="divider"></div>
            <div class="actions">
                <a class="btn btn-primary">Open Attachments</a>
                <a class="btn btn-secondary">Mark As Reviewed</a>
            </div>
            <div class="footer">
                Delivered to {{ env('MAIL_USERNAME') }}. Attachments are included.
            </div>
        </div>
    </div>
</body>
</html>
