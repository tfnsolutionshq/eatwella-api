<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\CareerApplication;
use App\Models\CareerApplication as CareerApplicationModel;
use App\Models\CareerOpening;

class CareersController extends Controller
{
    public function listOpenings(Request $request)
    {
        $openings = CareerOpening::where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('closes_at')
                      ->orWhere('closes_at', '>', now());
            })
            ->latest()
            ->get();

        return response()->json($openings);
    }

    public function index(Request $request)
    {
        if ($response = $this->requireRole($request, ['admin'])) {
            return $response;
        }

        $query = CareerApplicationModel::query()->with('opening');

        if ($request->filled('opening_id')) {
            $query->where('career_opening_id', $request->opening_id);
        }

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
            'opening_id' => 'required|uuid|exists:career_openings,id',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'required|string|max:50',
            'cv' => 'required_without:cover_letter|file|mimes:pdf,doc,docx|max:10240',
            'cover_letter' => 'required_without:cv|file|mimes:pdf,doc,docx|max:10240',
        ]);

        $opening = CareerOpening::findOrFail($validated['opening_id']);

        $cvPath = null;
        $coverLetterPath = null;

        if ($request->hasFile('cv')) {
            $cvPath = $request->file('cv')->store('careers', 'public');
        }

        if ($request->hasFile('cover_letter')) {
            $coverLetterPath = $request->file('cover_letter')->store('careers', 'public');
        }

        $application = CareerApplicationModel::create([
            'career_opening_id' => $opening->id,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $opening->role,
            'cv_path' => $cvPath,
            'cover_letter_path' => $coverLetterPath,
            'status' => 'submitted',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'submitted_at' => now(),
        ]);

        $payload = [
            'opening_id' => $opening->id,
            'opening_title' => $opening->title,
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $opening->role,
            'cv_path' => $cvPath,
            'cover_letter_path' => $coverLetterPath,
            'application_id' => $application->id,
        ];

        // $recipient = env('MAIL_USERNAME');
        $recipient = "promisedeco24@gmail.com";

        Mail::to($recipient)->send(new CareerApplication($payload));

        return response()->json([
            'message' => 'Application submitted successfully',
            'application_id' => $application->id,
            'opening_id' => $opening->id
        ], 201);
    }
}
