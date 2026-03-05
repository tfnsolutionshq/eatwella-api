<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Career Application</title>
    <style>
        /* Base Reset */
        body { margin: 0; padding: 0; background-color: #f4f5f7; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; width: 100%; }

        /* Layout */
        .wrapper { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05); margin-top: 40px; margin-bottom: 40px; }

        /* Header */
        .header { background-color: #2d3748; padding: 30px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px; }

        /* Content */
        .content { padding: 40px; color: #4a5568; }
        .welcome-text { font-size: 16px; line-height: 1.6; margin-bottom: 25px; }
        .position-badge { display: inline-block; background-color: #ebf8ff; color: #2b6cb0; padding: 6px 12px; border-radius: 4px; font-weight: 600; font-size: 14px; margin-bottom: 20px; }

        /* Data Grid */
        .data-grid { width: 100%; margin-bottom: 30px; }
        .data-row td { padding: 12px 0; border-bottom: 1px solid #edf2f7; }
        .data-label { width: 35%; font-weight: 600; color: #718096; font-size: 14px; }
        .data-value { width: 65%; color: #2d3748; font-size: 15px; }

        /* Button */
        .btn-container { text-align: center; margin-top: 35px; }
        .btn { display: inline-block; background-color: #48bb78; color: #ffffff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 15px; transition: background-color 0.2s; }
        .btn:hover { background-color: #38a169; }

        /* Footer */
        .footer { background-color: #f7fafc; padding: 20px 40px; text-align: center; border-top: 1px solid #edf2f7; }
        .footer-text { color: #a0aec0; font-size: 12px; line-height: 1.5; }

        /* Attachments Notice */
        .attachments-notice { background-color: #fffaf0; border-left: 4px solid #ed8936; padding: 15px; margin-top: 20px; font-size: 14px; color: #c05621; }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Header -->
        <div class="header">
            <img src="{{ asset('logo.jpg') }}" alt="Eatwella" height="60" style="margin-bottom: 15px;">
            <h1>Eatwella Careers</h1>
        </div>

        <!-- Main Content -->
        <div class="content">
            <p class="welcome-text">
                Hello Admin,<br>
                You have received a new job application via the Eatwella careers portal.
            </p>

            <!-- Position Highlight -->
            <div style="text-align: center;">
                <span class="position-badge">
                    {{ $payload['opening_title'] ?? 'General Application' }}
                </span>
            </div>

            <!-- Applicant Details -->
            <table class="data-grid">
                <tr class="data-row">
                    <td class="data-label">Applicant Name</td>
                    <td class="data-value">{{ $payload['full_name'] }}</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">Email Address</td>
                    <td class="data-value">
                        <a href="mailto:{{ $payload['email'] }}" style="color: #4299e1; text-decoration: none;">{{ $payload['email'] }}</a>
                    </td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">Phone Number</td>
                    <td class="data-value">{{ $payload['phone'] }}</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">Role Category</td>
                    <td class="data-value">{{ $payload['role'] ?? 'N/A' }}</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">Application ID</td>
                    <td class="data-value" style="font-family: monospace; color: #718096;">{{ $payload['application_id'] }}</td>
                </tr>
                <tr class="data-row">
                    <td class="data-label">Opening ID</td>
                    <td class="data-value" style="font-family: monospace; color: #718096;">{{ $payload['opening_id'] ?? '-' }}</td>
                </tr>
            </table>

            <!-- Attachments Notice -->
            <div class="attachments-notice">
                <strong>📎 Attachments Included:</strong> The applicant's CV and/or Cover Letter are attached to this email.
            </div>

            <!-- CTA Button -->
            <div class="btn-container">
                <!-- Assuming there is a dashboard URL, if not, this can be a mailto or just link to admin home -->
                <a href="{{ url('/admin/careers/applications') }}" class="btn">View Application</a>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p class="footer-text">
                &copy; {{ date('Y') }} Eatwella. All rights reserved.<br>
                This is an automated notification from your recruitment system.
            </p>
        </div>
    </div>
</body>
</html>
