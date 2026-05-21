@extends('emails.layout')

@section('title', 'New Career Application')

@section('heading', 'New Application Received')
@section('subheading', 'A new candidate has applied via the careers portal')

@section('content')
<div class="card">
    <div class="card-label">Position</div>
    <div class="card-value" style="font-size: 16px;">{{ $payload['opening_title'] ?? 'General Application' }}</div>
    <div style="margin-top: 10px;">
        <span class="badge badge-info">NEW APPLICATION</span>
    </div>
</div>

<div class="section-title">Applicant Details</div>
<div style="margin-bottom: 20px;">
    <div class="row">
        <span class="label">Full Name:&nbsp;</span>
        <span class="value">{{ $payload['full_name'] }}</span>
    </div>
    <div class="row">
        <span class="label">Email:&nbsp;</span>
        <span class="value">{{ $payload['email'] }}</span>
    </div>
    <div class="row">
        <span class="label">Phone:&nbsp;</span>
        <span class="value">{{ $payload['phone'] }}</span>
    </div>
    <div class="row">
        <span class="label">Role Category:&nbsp;</span>
        <span class="value">{{ $payload['role'] ?? 'N/A' }}</span>
    </div>
    <div class="row">
        <span class="label">Application ID:&nbsp;</span>
        <span class="value" style="font-family: monospace; font-size: 12px;">{{ $payload['application_id'] }}</span>
    </div>
</div>

<div class="notice">📎 The applicant's CV and/or Cover Letter are attached to this email.</div>

<a href="{{ url('/admin/careers/applications') }}" class="btn">Review Application</a>
@endsection
