<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSubmission;

class DashboardController extends Controller
{
    public function index()
    {
        $totalForms = Form::count();
        $activeForms = Form::where('is_active', true)->count();
        $totalSubmissions = FormSubmission::count();
        $recentSubmissions = FormSubmission::with('form')
            ->latest()
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'totalForms',
            'activeForms',
            'totalSubmissions',
            'recentSubmissions'
        ));
    }
}
