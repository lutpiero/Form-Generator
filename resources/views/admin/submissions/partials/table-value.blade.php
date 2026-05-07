@if(is_array($value) && $value !== [])
    <div class="table-responsive">
        <table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    @if($field->table_auto_number)
                        <th class="text-center" style="width: 60px;">#</th>
                    @endif
                    @foreach($field->table_columns as $column)
                        <th>{{ $column['label'] }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($value as $rowIndex => $row)
                    <tr>
                        @if($field->table_auto_number)
                            <td class="text-center text-muted">{{ $rowIndex + 1 }}</td>
                        @endif
                        @foreach($field->table_columns as $column)
                            @php $columnValue = $row[$column['key']] ?? null; @endphp
                            <td>
                                @if(is_array($columnValue))
                                    {{ $columnValue !== [] ? implode(', ', $columnValue) : '—' }}
                                @elseif($columnValue !== null && $columnValue !== '')
                                    {{ $columnValue }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <span class="text-muted">—</span>
@endif
