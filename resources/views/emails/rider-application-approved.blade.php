@extends('emails.layout')

@section('title', $application->status === 'approved' ? 'You\'re Approved!' : 'Update on Your Application')
@section('heading', $application->status === 'approved' ? 'You\'re approved! 🎉' : 'Application Update')
@section('subheading', $application->status === 'approved' ? 'Welcome to the EatWella rider network' : 'Thank you for your interest in EatWella')

@section('content')
<p style="font-size: 15px; color: #555; margin-bottom: 24px;">
    Hi {{ $application->first_name }},
</p>

@if($application->status === 'approved')

<p style="font-size: 15px; color: #555; margin-bottom: 24px;">
    Great news — your rider application has been approved. Welcome to EatWella!
</p>

<div class="section-title">Account Details</div>
<div class="card">
    <div style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
        <div style="font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">Registered Email</div>
        <div style="font-size: 15px; font-weight: 600; color: #1a1a1a;">{{ $application->email }}</div>
    </div>
    <div style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
        <div style="font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">Activation Code</div>
        <div style="font-size: 22px; font-weight: 800; color: #ff6b00; letter-spacing: 4px;">{{ $application->activation_code }}</div>
    </div>
    <div style="padding: 10px 0;">
        <div style="font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">WhatsApp Community</div>
        <div><a href="{{ env('RIDER_WHATSAPP_COMMUNITY_URL', '#') }}" style="color: #ff6b00; font-weight: 600; font-size: 14px;">Click here to Join</a></div>
    </div>
</div>

<div class="section-title">Next Steps</div>
<div style="margin-bottom: 24px; font-size: 14px; color: #555;">
    <p style="margin-bottom: 10px;">1. Download the EatWella Rider app:
        <a href="{{ env('RIDER_APP_STORE_URL', '#') }}" style="color: #ff6b00;">App Store</a> |
        <a href="{{ env('RIDER_PLAY_STORE_URL', '#') }}" style="color: #ff6b00;">Play Store</a> or access via
        <a href="{{ env('RIDER_WEB_URL', '#') }}" style="color: #ff6b00;">Web</a>
    </p>
    <p style="margin-bottom: 10px;">2. Activate your account using the activation code above.</p>
    <p>3. Log in and start receiving orders.</p>
</div>

<p style="font-size: 13px; color: #888; text-align: center;">
    For help, contact <a href="mailto:support@eatwella.ng" style="color: #ff6b00;">support@eatwella.ng</a>
    or WhatsApp <strong>{{ env('RIDER_SUPPORT_NUMBER', '') }}</strong>.
</p>

@else

<p style="font-size: 15px; color: #555; margin-bottom: 24px;">
    Thank you for your interest in joining EatWella. After review, we're unable to approve your application at this time.
</p>

@if($application->admin_note)
<div class="card">
    <div class="card-label">Reason</div>
    <p style="font-size: 14px; color: #555; margin-top: 6px;">{{ $application->admin_note }}</p>
</div>
@endif

<p style="font-size: 14px; color: #555; margin-bottom: 24px;">
    You can reapply if the issue has been resolved. For questions, reply to this email.
</p>

<p style="font-size: 13px; color: #888; text-align: center;">
    We appreciate your time.
</p>

@endif
@endsection
