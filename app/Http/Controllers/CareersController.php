<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\CareerApplication;
use App\Models\CareerApplication as CareerApplicationModel;

class CareersController extends Controller
{
    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = CareerApplicationModel::query();

        if ($request->filled('role')) {
            $query->where('role', 'like', '%' . $request->role . '%');
        }

        if ($request->filled('date')) {
            $query->whereDate('submitted_at', $request->date);
        }

        return $query->latest('submitted_at')->paginate($request->get('per_page', 15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:50',
            'role' => 'required|string|max:100',
            'cv' => 'required_without:cover_letter|file|mimes:pdf,doc,docx|max:10240',
            'cover_letter' => 'required_without:cv|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $cvPath = null;
        $coverLetterPath = null;

        if ($request->hasFile('cv')) {
            $cvPath = $request->file('cv')->store('careers', 'public');
        }

        if ($request->hasFile('cover_letter')) {
            $coverLetterPath = $request->file('cover_letter')->store('careers', 'public');
        }

        $application = CareerApplicationModel::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'cv_path' => $cvPath,
            'cover_letter_path' => $coverLetterPath,
            'status' => 'submitted',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now(),
        ]);

        $payload = [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'cv_path' => $cvPath,
            'cover_letter_path' => $coverLetterPath,
            'application_id' => $application->id,
        ];

        // $recipient = env('MAIL_USERNAME');
        $recipient = "promisedeco24@gmail.com";

        Mail::to($recipient)->send(new CareerApplication($payload));

        return response()->json([
            'message' => 'Application submitted successfully',
            'application_id' => $application->id
        ], 201);
    }
}
