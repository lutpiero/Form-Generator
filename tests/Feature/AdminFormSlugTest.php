<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFormSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_form_with_custom_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.create'))
            ->post(route('admin.forms.store'), [
                'name' => 'Contact Form',
                'slug' => 'contact-form',
                'description' => 'Description',
                'captcha_type' => 'math',
            ]);

        $form = Form::first();

        $response->assertRedirect(route('admin.forms.show', $form));
        $this->assertNotNull($form);
        $this->assertSame('contact-form', $form->slug);
    }

    public function test_admin_cannot_create_form_with_invalid_slug_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.create'))
            ->post(route('admin.forms.store'), [
                'name' => 'Contact Form',
                'slug' => 'Invalid Slug!',
                'captcha_type' => 'math',
            ]);

        $response
            ->assertRedirect(route('admin.forms.create'))
            ->assertSessionHasErrors(['slug']);
    }

    public function test_admin_cannot_update_form_with_duplicate_slug(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $existing = Form::create([
            'name' => 'Contact',
            'slug' => 'contact',
            'captcha_type' => 'math',
        ]);
        $form = Form::create([
            'name' => 'Survey',
            'slug' => 'survey',
            'captcha_type' => 'math',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.forms.edit', $form))
            ->put(route('admin.forms.update', $form), [
                'name' => 'Updated Survey',
                'slug' => $existing->slug,
                'captcha_type' => 'math',
            ]);

        $response
            ->assertRedirect(route('admin.forms.edit', $form))
            ->assertSessionHasErrors(['slug']);
    }

    public function test_admin_can_keep_same_slug_when_updating_form(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $form = Form::create([
            'name' => 'Survey',
            'slug' => 'survey',
            'captcha_type' => 'math',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.forms.update', $form), [
                'name' => 'Survey Form Updated',
                'slug' => 'survey',
                'captcha_type' => 'math',
            ]);

        $form->refresh();

        $response->assertRedirect(route('admin.forms.show', $form));
        $this->assertSame('survey', $form->slug);
        $this->assertSame('Survey Form Updated', $form->name);
    }
}
