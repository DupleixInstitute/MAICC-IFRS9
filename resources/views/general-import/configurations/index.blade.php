@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Configure Template: {{ $template->template_name }}</h3>
                    <div class="card-tools">
                        <a href="{{ route('general-import.templates') }}" class="btn btn-default">
                            <i class="fas fa-arrow-left"></i> Back to Templates
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <!-- Add New Configuration Form -->
                    <form action="{{ route('general-import.configuration.store', $template->id) }}" method="POST" class="mb-4">
                        @csrf
                        <div class="row">
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Column Position <span class="text-danger">*</span></label>
                                    <input type="number" 
                                           class="form-control @error('template_column_position') is-invalid @enderror" 
                                           name="template_column_position" 
                                           value="{{ old('template_column_position') }}" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Column Description <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control @error('column_description') is-invalid @enderror" 
                                           name="column_description" 
                                           value="{{ old('column_description') }}" 
                                           required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Data Type <span class="text-danger">*</span></label>
                                    <select class="form-control @error('column_data_type') is-invalid @enderror" 
                                            name="column_data_type" 
                                            required>
                                        @foreach($dataTypes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Minimum Value</label>
                                    <input type="number" 
                                           class="form-control @error('minimum_value') is-invalid @enderror" 
                                           name="minimum_value" 
                                           value="{{ old('minimum_value') }}" 
                                           step="any">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="form-group">
                                    <label>Maximum Value</label>
                                    <input type="number" 
                                           class="form-control @error('maximum_value') is-invalid @enderror" 
                                           name="maximum_value" 
                                           value="{{ old('maximum_value') }}" 
                                           step="any">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" 
                                           class="form-check-input" 
                                           id="is_reporting_period" 
                                           name="is_reporting_period" 
                                           value="1">
                                    <label class="form-check-label" for="is_reporting_period">
                                        Is Reporting Period
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input type="checkbox" 
                                           class="form-check-input" 
                                           id="is_portfolio_group_id" 
                                           name="is_portfolio_group_id" 
                                           value="1">
                                    <label class="form-check-label" for="is_portfolio_group_id">
                                        Is Portfolio Group
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <button type="submit" class="btn btn-primary float-right">
                                    <i class="fas fa-plus"></i> Add Configuration
                                </button>
                            </div>
                        </div>
                    </form>

                    <!-- Configurations List -->
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Position</th>
                                <th>Description</th>
                                <th>Data Type</th>
                                <th>Min Value</th>
                                <th>Max Value</th>
                                <th>Special Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($template->configurations->sortBy('template_column_position') as $config)
                                <tr>
                                    <td>{{ $config->template_column_position }}</td>
                                    <td>{{ $config->column_description }}</td>
                                    <td>{{ $dataTypes[$config->column_data_type] }}</td>
                                    <td>{{ $config->minimum_value }}</td>
                                    <td>{{ $config->maximum_value }}</td>
                                    <td>
                                        @if($config->is_reporting_period)
                                            <span class="badge badge-info">Reporting Period</span>
                                        @endif
                                        @if($config->is_portfolio_group_id)
                                            <span class="badge badge-success">Portfolio Group</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" 
                                                    class="btn btn-primary btn-sm" 
                                                    data-toggle="modal" 
                                                    data-target="#editModal{{ $config->id }}">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form action="{{ route('general-import.configuration.delete', [$template->id, $config->id]) }}" 
                                                  method="POST" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this configuration?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <!-- Edit Modal -->
                                <div class="modal fade" id="editModal{{ $config->id }}" tabindex="-1" role="dialog">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <form action="{{ route('general-import.configuration.update', [$template->id, $config->id]) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Edit Configuration</h5>
                                                    <button type="button" class="close" data-dismiss="modal">
                                                        <span>&times;</span>
                                                    </button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Column Position <span class="text-danger">*</span></label>
                                                                <input type="number" 
                                                                       class="form-control" 
                                                                       name="template_column_position" 
                                                                       value="{{ $config->template_column_position }}" 
                                                                       required>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-group">
                                                                <label>Column Description <span class="text-danger">*</span></label>
                                                                <input type="text" 
                                                                       class="form-control" 
                                                                       name="column_description" 
                                                                       value="{{ $config->column_description }}" 
                                                                       required>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label>Data Type <span class="text-danger">*</span></label>
                                                                <select class="form-control" name="column_data_type" required>
                                                                    @foreach($dataTypes as $value => $label)
                                                                        <option value="{{ $value }}" 
                                                                                {{ $config->column_data_type == $value ? 'selected' : '' }}>
                                                                            {{ $label }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label>Minimum Value</label>
                                                                <input type="number" 
                                                                       class="form-control" 
                                                                       name="minimum_value" 
                                                                       value="{{ $config->minimum_value }}" 
                                                                       step="any">
                                                            </div>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <div class="form-group">
                                                                <label>Maximum Value</label>
                                                                <input type="number" 
                                                                       class="form-control" 
                                                                       name="maximum_value" 
                                                                       value="{{ $config->maximum_value }}" 
                                                                       step="any">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input type="checkbox" 
                                                                       class="form-check-input" 
                                                                       id="edit_is_reporting_period{{ $config->id }}" 
                                                                       name="is_reporting_period" 
                                                                       value="1" 
                                                                       {{ $config->is_reporting_period ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="edit_is_reporting_period{{ $config->id }}">
                                                                    Is Reporting Period
                                                                </label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-check">
                                                                <input type="checkbox" 
                                                                       class="form-check-input" 
                                                                       id="edit_is_portfolio_group_id{{ $config->id }}" 
                                                                       name="is_portfolio_group_id" 
                                                                       value="1" 
                                                                       {{ $config->is_portfolio_group_id ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="edit_is_portfolio_group_id{{ $config->id }}">
                                                                    Is Portfolio Group
                                                                </label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">No configurations found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
