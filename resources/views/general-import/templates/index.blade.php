@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Import Templates</h3>
                    <div class="card-tools">
                        <a href="{{ route('general-import.template.create') }}" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Template
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

                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Template Name</th>
                                <th>Description</th>
                                <th>Source Table</th>
                                <th>Columns</th>
                                <th>Import Count</th>
                                <th>Status</th>
                                <th>Last Updated</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($templates as $template)
                                <tr>
                                    <td>{{ $template->template_name }}</td>
                                    <td>{{ $template->template_description }}</td>
                                    <td>{{ $template->source_table_name }}</td>
                                    <td>{{ $template->column_count }}</td>
                                    <td>{{ $template->import_count }}</td>
                                    <td>
                                        @if($template->active_status === 1)
                                            <span class="badge badge-success">Active</span>
                                        @else
                                            <span class="badge badge-danger">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $template->update_date ? $template->update_date->format('Y-m-d H:i') : 'Never' }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('general-import.configuration.index', $template->id) }}" 
                                               class="btn btn-info btn-sm" 
                                               title="Configure Columns">
                                                <i class="fas fa-cogs"></i>
                                            </a>
                                            <a href="{{ route('general-import.template.edit', $template->id) }}" 
                                               class="btn btn-primary btn-sm"
                                               title="Edit Template">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            @if($template->active_status === 1)
                                                <a href="{{ route('general-import.form', $template->id) }}" 
                                                   class="btn btn-success btn-sm"
                                                   title="Import Data">
                                                    <i class="fas fa-upload"></i>
                                                </a>
                                            @endif
                                            <form action="{{ route('general-import.template.toggle-status', $template->id) }}" 
                                                  method="POST" 
                                                  style="display: inline;">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" 
                                                        class="btn btn-{{ $template->active_status === 1 ? 'warning' : 'success' }} btn-sm"
                                                        title="{{ $template->active_status === 1 ? 'Deactivate' : 'Activate' }}">
                                                    <i class="fas fa-{{ $template->active_status === 1 ? 'ban' : 'check' }}"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('general-import.template.delete', $template->id) }}" 
                                                  method="POST" 
                                                  style="display: inline;"
                                                  onsubmit="return confirm('Are you sure you want to delete this template?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-danger btn-sm"
                                                        title="Delete Template">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center">No templates found</td>
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
