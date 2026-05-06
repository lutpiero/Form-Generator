<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Form;
use App\Models\FormField;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        // Demo contact form
        $form = Form::firstOrCreate(
            ['slug' => 'contact'],
            [
                'name' => 'Contact Form',
                'description' => 'A sample contact form to get started.',
                'is_active' => true,
                'captcha_enabled' => true,
                'captcha_type' => 'math',
                'success_message' => 'Thank you for contacting us! We will get back to you shortly.',
            ]
        );

        if ($form->fields()->count() === 0) {
            $fields = [
                ['label' => 'Full Name', 'name' => 'full_name', 'type' => 'text', 'required' => true, 'placeholder' => 'Your full name', 'order' => 0],
                ['label' => 'Email Address', 'name' => 'email_address', 'type' => 'email', 'required' => true, 'placeholder' => 'you@example.com', 'order' => 1],
                ['label' => 'Phone Number', 'name' => 'phone_number', 'type' => 'phone', 'required' => false, 'placeholder' => '+1 234 567 8900', 'order' => 2],
                ['label' => 'Subject', 'name' => 'subject', 'type' => 'dropdown', 'required' => true, 'options' => json_encode(['General Inquiry', 'Support', 'Sales', 'Other']), 'order' => 3],
                ['label' => 'Message', 'name' => 'message', 'type' => 'textarea', 'required' => true, 'placeholder' => 'Your message...', 'order' => 4],
            ];

            foreach ($fields as $fieldData) {
                $form->fields()->create($fieldData);
            }
        }
    }
}

