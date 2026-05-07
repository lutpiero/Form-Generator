<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_form_renders_and_stores_repeatable_table_rows(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributes());

        $this->get(route('forms.show', $form))
            ->assertOk()
            ->assertSee('Inventory Items')
            ->assertSee("table_fields[{$field->id}][0][item_name]", false)
            ->assertSee('Add Row');

        $response = $this->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'item_name' => 'Fuse Box',
                        'field_1' => 'Electronic',
                        'checkbox_1' => ['opt1'],
                        'radio_1' => 'yes',
                    ],
                    [
                        '__row' => 1,
                        'item_name' => 'Cable',
                        'field_1' => 'Wiring',
                        'checkbox_1' => ['opt2'],
                        'radio_1' => 'no',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.success', $form));

        $submission = FormSubmission::firstOrFail();

        $this->assertSame([
            [
                'item_name' => 'Fuse Box',
                'field_1' => 'Electronic',
                'checkbox_1' => ['opt1'],
                'radio_1' => 'yes',
            ],
            [
                'item_name' => 'Cable',
                'field_1' => 'Wiring',
                'checkbox_1' => ['opt2'],
                'radio_1' => 'no',
            ],
        ], $submission->data[$field->name]);
    }

    public function test_repeatable_table_requires_configured_required_columns(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributes());

        $response = $this->from(route('forms.show', $form))->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'item_name' => '',
                        'field_1' => 'Electronic',
                        'checkbox_1' => ['opt1'],
                        'radio_1' => 'yes',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.show', $form));
        $response->assertSessionHasErrors("table_fields.{$field->id}.0.item_name");
    }

    public function test_admin_can_create_table_fields_and_view_export_submission_values(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.forms.fields.store', $form), [
                'label' => 'Inventory Items',
                'type' => 'table',
                'config' => [
                    'auto_number' => '1',
                    'columns' => [
                        ['label' => 'Item Name', 'type' => 'text', 'required' => '1'],
                        ['label' => 'Radio 1', 'type' => 'radio', 'options' => "yes\nno"],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.forms.show', $form));

        $field = $form->fields()->firstOrFail()->fresh();

        $this->assertSame('table', $field->type);
        $this->assertTrue($field->table_auto_number);
        $this->assertSame('item_name', $field->table_columns[0]['key']);
        $this->assertSame(['yes', 'no'], $field->table_columns[1]['options']);

        $itemNameKey = $field->table_columns[0]['key'];
        $radioKey = $field->table_columns[1]['key'];

        $submission = $form->submissions()->create([
            'data' => [
                $field->name => [
                    [
                        $itemNameKey => 'Fuse Box',
                        $radioKey => 'yes',
                    ],
                ],
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.forms.submissions.show', [$form, $submission]))
            ->assertOk()
            ->assertSee('Item Name')
            ->assertSee('Fuse Box');

        $exportResponse = $this->actingAs($admin)
            ->get(route('admin.forms.submissions.export', $form));

        $exportResponse->assertOk();
        $this->assertStringContainsString('Row 1: Item Name: Fuse Box; Radio 1: yes', $exportResponse->streamedContent());
    }

    protected function tableFieldAttributes(): array
    {
        return [
            'label' => 'Inventory Items',
            'name' => 'inventory_items',
            'type' => 'table',
            'required' => false,
            'order' => 0,
            'config' => [
                'auto_number' => true,
                'columns' => [
                    ['key' => 'item_name', 'label' => 'Item Name', 'type' => 'text', 'required' => true, 'options' => []],
                    ['key' => 'field_1', 'label' => 'Field 1', 'type' => 'text', 'required' => false, 'options' => []],
                    ['key' => 'checkbox_1', 'label' => 'Checkbox 1', 'type' => 'checkbox', 'required' => false, 'options' => ['opt1', 'opt2']],
                    ['key' => 'radio_1', 'label' => 'Radio 1', 'type' => 'radio', 'required' => false, 'options' => ['yes', 'no']],
                ],
            ],
        ];
    }
}
