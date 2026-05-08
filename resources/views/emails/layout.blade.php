<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - EatWella</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background-color: #f5f5f5; color: #1a1a1a; line-height: 1.6; }
        .wrapper { max-width: 520px; margin: 30px auto; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 2px 16px rgba(0,0,0,0.08); }
        .top-bar { background: #ff6b00; height: 6px; }
        .header { padding: 36px 40px 24px; text-align: center; background: #fff; }
        .header .logo-wrap { display: inline-block; margin-bottom: 16px; }
        .header .logo-wrap img { width: 140px; height: auto; max-width: 100%; display: block; }
        .header h1 { font-size: 22px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px; }
        .header p { font-size: 14px; color: #888; }
        .body { padding: 0 40px 32px; }
        .card { background: #fafafa; border: 1px solid #f0f0f0; border-radius: 12px; padding: 20px 24px; margin-bottom: 20px; }
        .card-label { font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 4px; }
        .card-value { font-size: 20px; font-weight: 700; color: #1a1a1a; }
        .badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-success { background: #e6f9f0; color: #00a651; }
        .badge-warning { background: #fff8e6; color: #f59e0b; }
        .badge-danger  { background: #fef2f2; color: #dc2626; }
        .badge-info    { background: #eff6ff; color: #3b82f6; }
        .section-title { font-size: 15px; font-weight: 700; color: #1a1a1a; margin-bottom: 14px; }
        .row { display: flex; justify-content: space-between; align-items: flex-start; padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .row:last-child { border-bottom: none; }
        .row .label { color: #888; }
        .row .value { color: #1a1a1a; font-weight: 500; text-align: right; max-width: 60%; }
        .row .value.orange { color: #ff6b00; font-weight: 700; }
        .divider { height: 1px; background: #f0f0f0; margin: 20px 0; }
        .item-row { padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
        .item-row:last-child { border-bottom: none; }
        .item-name { font-size: 14px; color: #ff6b00; font-weight: 500; }
        .item-meta { font-size: 12px; color: #aaa; margin-top: 2px; }
        .item-price { font-size: 14px; font-weight: 600; color: #1a1a1a; float: right; }
        .total-row { display: flex; justify-content: space-between; font-size: 14px; padding: 6px 0; }
        .total-row.final { font-size: 16px; font-weight: 700; padding-top: 12px; margin-top: 8px; border-top: 1px solid #f0f0f0; }
        .total-row.final .amount { color: #ff6b00; }
        .btn { display: block; background: #ff6b00; color: #fff; text-decoration: none; text-align: center; padding: 14px 24px; border-radius: 30px; font-size: 15px; font-weight: 600; margin: 8px 0; }
        .btn-outline { display: block; background: #fff; color: #ff6b00; text-decoration: none; text-align: center; padding: 14px 24px; border-radius: 30px; font-size: 15px; font-weight: 600; margin: 8px 0; border: 2px solid #ff6b00; }
        .btn-link { text-align: center; font-size: 13px; color: #ff6b00; text-decoration: none; display: block; margin-top: 12px; font-weight: 600; }
        .notice { background: #fff8f0; border-left: 3px solid #ff6b00; border-radius: 8px; padding: 14px 16px; font-size: 13px; color: #555; margin-bottom: 20px; font-style: italic; text-align: center; }
        .pin-box { background: #fff8f0; border: 2px dashed #ff6b00; border-radius: 10px; padding: 16px; text-align: center; margin: 16px 0; }
        .pin-box .pin { font-size: 32px; font-weight: 800; color: #ff6b00; letter-spacing: 8px; }
        .pin-box p { font-size: 12px; color: #888; margin-top: 6px; }
        .footer { background: #fafafa; border-top: 1px solid #f0f0f0; padding: 24px 40px; text-align: center; }
        .footer p { font-size: 12px; color: #bbb; margin-bottom: 4px; }
        .footer a { color: #ff6b00; text-decoration: none; }
        @media (max-width: 560px) {
            .wrapper { margin: 0; border-radius: 0; }
            .header, .body, .footer { padding-left: 20px; padding-right: 20px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="top-bar"></div>
        <div class="header">
            <div class="logo-wrap">
                <img src="{{ asset('eatwella.png') }}" alt="EatWella">
            </div>
            <h1>@yield('heading')</h1>
            <p>@yield('subheading')</p>
        </div>
        <div class="body">
            @yield('content')
        </div>
        <div class="footer">
            <p>🍽️ <strong>Eatwella</strong> — Delicious meals, delivered fresh</p>
            <p style="margin-top: 8px;">Questions? <a href="mailto:support@eatwella.ng">support@eatwella.ng</a></p>
            <p style="margin-top: 8px;">© {{ date('Y') }} Eatwella. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
