@extends('emails.layout')

@section('title', 'Application Received')
@section('heading', 'Application Received')
@section('subheading', 'We\'ll be in touch within 24 working hours')

@section('content')
<p style="font-size: 15px; color: #555; margin-bottom: 24px;">
    Hi {{ $application->first_name }},
</p>
<p style="font-size: 15px; color: #555; margin-bottom: 24px;">
    Thanks for applying to join the EatWella rider network. We've received your application and our team is reviewing your details. You'll be contacted for vehicle/document inspection within 24 working hours.
</p>

<div class="card">
    <div style="padding: 10px 0; border-bottom: 1px solid #f0f0f0;">
        <div style="font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">Application ID</div>
        <div style="font-size: 15px; font-weight: 600; color: #1a1a1a;">{{ $application->application_id }}</div>
    </div>
    <div style="padding: 10px 0;">
        <div style="font-size: 11px; color: #aaa; text-transform: uppercase; letter-spacing: 0.8px; margin-bottom: 6px;">Submitted</div>
        <div style="font-size: 15px; font-weight: 600; color: #1a1a1a;">{{ $application->created_at->format('d M Y, g:i A') }}</div>
    </div>
</div>

<p style="font-size: 13px; color: #888; text-align: center; margin-top: 8px;">
    Need to update anything? Reply to this email.
</p>
@endsection
