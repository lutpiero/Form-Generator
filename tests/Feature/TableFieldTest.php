<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            ->assertSee('<th class="text-center align-top" style="width: 70px;">#</th>', false)
            ->assertSee('<th class="text-center align-top" style="width: 90px;">Action</th>', false)
            ->assertSee('<td class="align-top"', false)
            ->assertSee('class="text-muted text-center align-top"', false)
            ->assertSee('class="text-center align-top"', false)
            ->assertSee("table_fields[{$field->id}][0][item_name]", false)
            ->assertSee("table_fields[{$field->id}][0][checkbox_1_other]", false)
            ->assertSee('Lainnya')
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

    public function test_repeatable_table_requires_other_text_when_custom_checkbox_answer_is_selected(): void
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
                        'item_name' => 'Fuse Box',
                        'checkbox_1' => [\App\Models\FormField::OTHER_OPTION_VALUE],
                        'checkbox_1_other' => '   ',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.show', $form));
        $response->assertSessionHasErrors("table_fields.{$field->id}.0.checkbox_1_other");
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_repeatable_table_stores_other_checkbox_values(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributes());

        $response = $this->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'item_name' => 'Fuse Box',
                        'checkbox_1' => ['opt1', \App\Models\FormField::OTHER_OPTION_VALUE],
                        'checkbox_1_other' => 'Panel cadangan',
                        'radio_1' => 'yes',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.success', $form));
        $submission = FormSubmission::firstOrFail();

        $this->assertSame(['opt1', 'other:Panel cadangan'], $submission->data[$field->name][0]['checkbox_1']);
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

        $form->fields()->create([
            'label' => 'Section Divider',
            'name' => 'section_divider',
            'type' => 'section',
            'required' => false,
            'order' => 0,
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
                        ['label' => 'Checkbox 1', 'type' => 'checkbox', 'options' => "opt1\nopt2", 'allow_custom_answer' => '1', 'other_label' => 'Lainnya', 'visibility' => [
                            'enabled' => '1',
                            'field' => 'radio_1',
                            'operator' => 'equals',
                            'value' => 'yes',
                        ]],
                    ],
                ],
            ])
            ->assertRedirect(route('admin.forms.show', $form));

        $field = $form->fields()->where('type', 'table')->firstOrFail()->fresh();

        $this->assertSame('table', $field->type);
        $this->assertTrue($field->table_auto_number);
        $this->assertSame('item_name', $field->table_columns[0]['key']);
        $this->assertTrue($field->table_columns[2]['allow_custom_answer']);
        $this->assertSame('Lainnya', $field->table_columns[2]['other_label']);
        $this->assertSame(['yes', 'no'], $field->table_columns[1]['options']);
        $this->assertSame([
            'enabled' => true,
            'field' => 'radio_1',
            'operator' => 'equals',
            'value' => 'yes',
        ], $field->table_columns[2]['visibility']);

        $itemNameKey = $field->table_columns[0]['key'];
        $radioKey = $field->table_columns[1]['key'];
        $checkboxKey = $field->table_columns[2]['key'];

        $submission = $form->submissions()->create([
            'data' => [
                $field->name => [
                    [
                        $itemNameKey => 'Fuse Box',
                        $checkboxKey => ['other:Panel cadangan'],
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
            ->assertSee('Fuse Box')
            ->assertSee('Lainnya: Panel cadangan');

        $exportResponse = $this->actingAs($admin)
            ->get(route('admin.forms.submissions.export', $form));

        $exportResponse->assertOk();
        $this->assertStringContainsString('Row 1: Item Name: Fuse Box; Radio 1: yes; Checkbox 1: Lainnya: Panel cadangan', $exportResponse->streamedContent());

        $excelResponse = $this->actingAs($admin)
            ->get(route('admin.forms.submissions.export-excel', $form));

        $excelResponse->assertOk();
        $excelResponse->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'inventory-form-submissions-'.now()->format('Y-m-d').'.xlsx',
            $excelResponse->headers->get('content-disposition', '')
        );

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($tempFile, file_get_contents($excelResponse->baseResponse->getFile()->getPathname()));
        $spreadsheet = IOFactory::load($tempFile);
        @unlink($tempFile);

        $summarySheet = $spreadsheet->getSheetByName('Submissions');
        $this->assertNotNull($summarySheet);
        $this->assertSame('#', $summarySheet->getCell('A1')->getValue());
        $this->assertSame('Submitted At', $summarySheet->getCell('B1')->getValue());
        $this->assertSame('Inventory Items', $summarySheet->getCell('C1')->getValue());
        $this->assertSame('→ View Details', $summarySheet->getCell('C2')->getValue());
        $this->assertStringContainsString("sheet://'R1_{$field->name}'!A1", $summarySheet->getCell('C2')->getHyperlink()->getUrl());

        $tableSheet = $spreadsheet->getSheetByName("R1_{$field->name}");
        $this->assertNotNull($tableSheet);
        $this->assertSame('#', $tableSheet->getCell('A1')->getValue());
        $this->assertSame('Item Name', $tableSheet->getCell('B1')->getValue());
        $this->assertSame('Radio 1', $tableSheet->getCell('C1')->getValue());
        $this->assertSame('Checkbox 1', $tableSheet->getCell('D1')->getValue());
        $this->assertSame(1, $tableSheet->getCell('A2')->getValue());
        $this->assertSame('Fuse Box', $tableSheet->getCell('B2')->getValue());
        $this->assertSame('yes', $tableSheet->getCell('C2')->getValue());
        $this->assertSame('Lainnya: Panel cadangan', $tableSheet->getCell('D2')->getValue());
    }

    public function test_admin_field_form_re_renders_table_option_textareas_after_validation_failure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $response = $this->actingAs($admin)
            ->followingRedirects()
            ->from(route('admin.forms.fields.create', $form))
            ->post(route('admin.forms.fields.store', $form), [
                'label' => '',
                'type' => 'table',
                'config' => [
                    'columns' => [
                        ['label' => 'Radio 1', 'type' => 'radio', 'options' => "yes\nno"],
                        ['label' => 'Checkbox 1', 'type' => 'checkbox', 'options' => "opt1\nopt2", 'allow_custom_answer' => '1', 'other_label' => 'Lainnya'],
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertSee('Radio 1');
        $response->assertSee("yes\nno", false);
        $response->assertSee('Checkbox 1');
        $response->assertSee('Lainnya');
        $this->assertSame(1, substr_count($response->getContent(), 'id="customAnswerGroup"'));
        $this->assertStringContainsString('id="customAnswerGroup" style="display:none"', $response->getContent());
    }

    public function test_row_level_conditional_column_is_hidden_when_condition_is_not_met(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithConditionalColumn());

        $response = $this
            ->withSession([
                '_old_input' => [
                    'table_fields' => [
                        $field->id => [
                            ['has_custom_value' => 'no', 'custom_value' => 'secret'],
                        ],
                    ],
                ],
            ])
            ->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('data-column-key="custom_value"', false);
        $response->assertSee('data-visibility-state="hidden"', false);
    }

    public function test_row_level_conditional_column_is_shown_when_condition_is_met(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithConditionalColumn());

        $response = $this
            ->withSession([
                '_old_input' => [
                    'table_fields' => [
                        $field->id => [
                            ['has_custom_value' => 'yes', 'custom_value' => 'shown'],
                        ],
                    ],
                ],
            ])
            ->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('data-column-key="custom_value"', false);
        $response->assertSee('data-visibility-state="visible"', false);
    }

    public function test_hidden_required_conditional_table_cell_does_not_block_submission(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithConditionalColumn());

        $response = $this->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'has_custom_value' => 'no',
                        'custom_value' => '',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.success', $form));
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_row_level_required_validation_is_scoped_per_row(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithConditionalColumn());

        $response = $this->from(route('forms.show', $form))->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'has_custom_value' => 'no',
                        'custom_value' => '',
                    ],
                    [
                        '__row' => 2,
                        'has_custom_value' => 'yes',
                        'custom_value' => '',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.show', $form));
        $response->assertSessionHasErrors("table_fields.{$field->id}.1.custom_value");
        $response->assertSessionDoesntHaveErrors("table_fields.{$field->id}.0.custom_value");
    }

    public function test_hidden_table_cell_value_is_not_stored(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithConditionalColumn());

        $response = $this->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'has_custom_value' => 'no',
                        'custom_value' => 'should not persist',
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.success', $form));

        $submission = FormSubmission::firstOrFail();
        $this->assertArrayNotHasKey('custom_value', $submission->data[$field->name][0]);
    }

    public function test_new_table_rows_can_evaluate_visibility_rules_from_template(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithConditionalColumn());

        $response = $this->get(route('forms.show', $form));

        $response->assertOk();
        $response->assertSee('<template data-table-row-template>', false);
        $response->assertSee('data-visibility-field="has_custom_value"', false);
        $response->assertSee('data-visibility-operator="equals"', false);
    }

    public function test_admin_table_column_builder_includes_checkbox_dropdown_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $form = Form::create([
            'name' => 'Builder Form',
            'slug' => 'builder-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.forms.fields.create', $form))
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'value="checkbox_dropdown"'));
    }

    public function test_table_checkbox_dropdown_renders_and_stores_multiple_values(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithCheckboxDropdownColumn());

        $this->get(route('forms.show', $form))
            ->assertOk()
            ->assertSee('data-column-type="checkbox_dropdown"', false)
            ->assertSee('data-table-checkbox-dropdown', false)
            ->assertSee("table_fields[{$field->id}][0][multi_select][]", false);

        $response = $this->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'item_name' => 'Fuse Box',
                        'multi_select' => ['Opt 1', 'Opt 2'],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.success', $form));

        $submission = FormSubmission::firstOrFail();
        $this->assertSame(['Opt 1', 'Opt 2'], $submission->data[$field->name][0]['multi_select']);
    }

    public function test_table_checkbox_dropdown_required_validation_works(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $attributes = $this->tableFieldAttributesWithCheckboxDropdownColumn();
        $attributes['config']['columns'][1]['required'] = true;
        $field = $form->fields()->create($attributes);

        $response = $this->from(route('forms.show', $form))->post(route('forms.submit', $form), [
            'table_fields' => [
                $field->id => [
                    [
                        '__row' => 1,
                        'item_name' => 'Fuse Box',
                        'multi_select' => [],
                    ],
                ],
            ],
        ]);

        $response->assertRedirect(route('forms.show', $form));
        $response->assertSessionHasErrors("table_fields.{$field->id}.0.multi_select");
        $this->assertDatabaseCount('form_submissions', 0);
    }

    public function test_table_checkbox_dropdown_restores_old_input_and_summary_after_validation_failure(): void
    {
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithCheckboxDropdownColumn());

        $response = $this->followingRedirects()
            ->from(route('forms.show', $form))
            ->post(route('forms.submit', $form), [
                'table_fields' => [
                    $field->id => [
                        [
                            '__row' => 1,
                            'item_name' => '',
                            'multi_select' => ['Opt 1', 'Opt 2'],
                        ],
                    ],
                ],
            ]);

        $response->assertOk();
        $response->assertSee('value="Opt 1"', false);
        $response->assertSee('value="Opt 2"', false);
        $response->assertSee('Opt 1, Opt 2');
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), 'checked'));
    }

    public function test_table_checkbox_dropdown_template_and_exports_are_readable(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $form = Form::create([
            'name' => 'Inventory Form',
            'slug' => 'inventory-form',
            'is_active' => true,
            'captcha_enabled' => false,
            'captcha_type' => 'math',
        ]);

        $field = $form->fields()->create($this->tableFieldAttributesWithCheckboxDropdownColumn());

        $this->get(route('forms.show', $form))
            ->assertOk()
            ->assertSee('<template data-table-row-template>', false)
            ->assertSee('data-table-checkbox-dropdown-summary', false)
            ->assertSee('data-table-checkbox-dropdown-option', false);

        $submission = $form->submissions()->create([
            'data' => [
                $field->name => [
                    [
                        'item_name' => 'Fuse Box',
                        'multi_select' => ['Opt 1', 'Opt 2'],
                    ],
                ],
            ],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.forms.submissions.show', [$form, $submission]))
            ->assertOk()
            ->assertSee('Opt 1, Opt 2');

        $csv = $this->actingAs($admin)->get(route('admin.forms.submissions.export', $form));
        $csv->assertOk();
        $this->assertStringContainsString('Row 1: Item Name: Fuse Box; Multi Select: Opt 1, Opt 2', $csv->streamedContent());

        $excel = $this->actingAs($admin)->get(route('admin.forms.submissions.export-excel', $form));
        $excel->assertOk();

        $tempFile = tempnam(sys_get_temp_dir(), 'xlsx_test_');
        file_put_contents($tempFile, file_get_contents($excel->baseResponse->getFile()->getPathname()));
        $spreadsheet = IOFactory::load($tempFile);
        @unlink($tempFile);

        $tableSheet = $spreadsheet->getSheetByName("R1_{$field->name}");
        $this->assertNotNull($tableSheet);
        $this->assertSame('Multi Select', $tableSheet->getCell('C1')->getValue());
        $this->assertSame('Opt 1, Opt 2', $tableSheet->getCell('C2')->getValue());
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
                    ['key' => 'checkbox_1', 'label' => 'Checkbox 1', 'type' => 'checkbox', 'required' => false, 'options' => ['opt1', 'opt2'], 'allow_custom_answer' => true, 'other_label' => 'Lainnya'],
                    ['key' => 'radio_1', 'label' => 'Radio 1', 'type' => 'radio', 'required' => false, 'options' => ['yes', 'no']],
                ],
            ],
        ];
    }

    protected function tableFieldAttributesWithConditionalColumn(): array
    {
        return [
            'label' => 'Conditional Items',
            'name' => 'conditional_items',
            'type' => 'table',
            'required' => false,
            'order' => 0,
            'config' => [
                'auto_number' => true,
                'columns' => [
                    ['key' => 'has_custom_value', 'label' => 'Has Custom Value', 'type' => 'radio', 'required' => true, 'options' => ['yes', 'no']],
                    [
                        'key' => 'custom_value',
                        'label' => 'Custom Value',
                        'type' => 'text',
                        'required' => true,
                        'options' => [],
                        'visibility' => [
                            'enabled' => true,
                            'field' => 'has_custom_value',
                            'operator' => 'equals',
                            'value' => 'yes',
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function tableFieldAttributesWithCheckboxDropdownColumn(): array
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
                    ['key' => 'multi_select', 'label' => 'Multi Select', 'type' => 'checkbox_dropdown', 'required' => false, 'options' => ['Opt 1', 'Opt 2', 'Opt 3']],
                ],
            ],
        ];
    }
}
